const mqtt = require("mqtt");
const mysql = require("mysql2/promise");
const WebSocket = require("ws");

// ================= MQTT =================
// const mqttClient = mqtt.connect("mqtts://0d07e1603b0a4e42a643398a28b36ae8.s1.eu.hivemq.cloud", {
//     port: 8883,
//     username: "migridmqtt",
//     password: "hrJ@WTe3u2Wpj5b"
// });

const mqttClient = mqtt.connect("mqtt://127.0.0.1:1883");

mqttClient.on("connect", () => {
    console.log("✅ MQTT Connected");
    mqttClient.subscribe("powerstation/+/+/telemetry");
});

// ================= DATABASE =================
const db = mysql.createPool({
    host: "localhost",
    user: "root",
    password: "",
    database: "powerstation",
    connectionLimit: 10
});

// ================= WEBSOCKET =================
// const wss = new WebSocket.Server({ port: 3000, host: "0.0.0.0" });

const http = require("http");

const server = http.createServer();
const wss = new WebSocket.Server({ server });

const PORT = process.env.PORT || 3000;

server.listen(PORT, () => {
    console.log("✅ WebSocket running on port", PORT);
});

wss.on("connection", (ws) => {

    console.log("🟢 WS Connected");

    ws.user_id = null;
    ws.allowedHouses = [];

    ws.on("message", async (msg) => {

        const data = JSON.parse(msg);
        console.log("📥 WS:", data);

        // ================= AUTH =================
        if (data.type === "AUTH") {

            ws.user_id = data.user_id;

            // ================= GET USER HOUSES =================
            const [houses] = await db.query(`
                SELECT id, house_uid 
                FROM houses 
                WHERE user_id = ?
            `, [ws.user_id]);

            ws.allowedHouses = houses.map(h => h.house_uid);

            // if no house, return early
            if (houses.length === 0) {
                ws.send(JSON.stringify({
                    type: "AUTH_SUCCESS",
                    houses: [],
                    total_energy: 0,
                    sold_kwh: 0
                }));
                return;
            }

            // (since you're using ONE house per user for now)
            const house = houses[0];
            const house_id = house.id;
            const house_uid = house.house_uid;

            // ================= GET NODE =================
            const [[nodeRow]] = await db.query(`
                SELECT n.id AS node_id, n.node_uid
                FROM nodes n
                JOIN house_node_map m ON m.node_id = n.id
                WHERE m.house_id = ?
                LIMIT 1
            `, [house_id]);

            const node_id = nodeRow?.node_id;
            const node_uid = nodeRow?.node_uid;

            // ================= GET ENERGY TOTAL =================
            let total = 0;

            if (node_id && house_id) {
                const [rows] = await db.query(`
                    SELECT total_energy 
                    FROM energy_totals 
                    WHERE node_id=? AND house_id=?
                `, [node_id, house_id]);

                total = rows.length ? parseFloat(rows[0].total_energy) : 0;
            }

            // ================= GET SELL SESSION =================
            let sold_kwh = 0;
            let sell_cap_kwh = 0;

            const [[session]] = await db.query(`
                SELECT sold_kwh, sell_cap_kwh 
                FROM sell_sessions 
                WHERE house_id=?
            `, [house_id]);

            if (session) {
                sold_kwh = parseFloat(session.sold_kwh || 0);
                sell_cap_kwh = parseFloat(session.sell_cap_kwh || 0);
            }

            // ================= SEND TO CLIENT =================
            ws.send(JSON.stringify({
                type: "AUTH_SUCCESS",
                houses: ws.allowedHouses,
                house_uid,
                node_uid,
                total_energy: total,
                sold_kwh,
                sell_cap_kwh
            }));

            console.log("✅ AUTH INIT SENT:", {
                user: ws.user_id,
                house_uid,
                total,
                sold_kwh
            });

            return;
        }

        // 🔒 BLOCK NON-AUTH USERS
        if (!ws.user_id) return;

        // ================= START SELL =================
        if (data.type === "START_SELL") {

            const { house_uid, amount_kwh } = data;

            if (!ws.allowedHouses.includes(house_uid)) return;

            const amount = parseFloat(amount_kwh);

            if (!amount || amount <= 0) {
                ws.send(JSON.stringify({ type: "FEEDBACK", reason: "INVALID_AMOUNT" }));
                return;
            }

            const [[row]] = await db.query(`
                SELECT h.id house_id, n.node_uid, h.mode
                FROM houses h
                JOIN house_node_map m ON m.house_id = h.id
                JOIN nodes n ON n.id = m.node_id
                WHERE h.house_uid = ?
            `, [house_uid]);

            if (!row) return;

            if (row.mode === "buying") {
                ws.send(JSON.stringify({ type: "FEEDBACK", reason: "CONFLICT_MODE" }));
                return;
            }

            // ✅ CREATE OR RESET SESSION
            await db.query(`
                INSERT INTO sell_sessions (house_id, sell_cap_kwh, sold_kwh, status)
                VALUES (?, ?, 0, 'active')
                ON DUPLICATE KEY UPDATE
                    sell_cap_kwh = VALUES(sell_cap_kwh),
                    sold_kwh = 0,
                    status = 'active'
            `, [row.house_id, amount]);

            // ✅ SET MODE
            await db.query(
                "UPDATE houses SET mode='selling' WHERE id=?",
                [row.house_id]
            );

            publishControl(row.node_uid, house_uid, "SELL_ON");

            ws.send(JSON.stringify({
                type: "FEEDBACK",
                reason: "SELL_STARTED",
                amount_kwh: amount
            }));
        }
        // ================= WITHDRAW =================
        if (data.type === "WITHDRAW_WALLET") {

            const amount = parseFloat(data.amount);

            if (!amount || amount <= 0) {
                ws.send(JSON.stringify({
                    type: "WITHDRAW_FAILED",
                    reason: "INVALID_AMOUNT"
                }));
                return;
            }

            const user_id = ws.user_id;

            const [[wallet]] = await db.query(
                "SELECT balance FROM wallet WHERE user_id=?",
                [user_id]
            );

            const balance = parseFloat(wallet?.balance || 0);

            if (balance < amount) {
                ws.send(JSON.stringify({
                    type: "WITHDRAW_FAILED",
                    reason: "INSUFFICIENT_FUNDS"
                }));
                return;
            }

            // ================= UPDATE =================
            await db.query(
                "UPDATE wallet SET balance = balance - ? WHERE user_id=?",
                [amount, user_id]
            );

            await db.query(`
                INSERT INTO wallet_transactions
                (user_id, type, amount, status, description)
                VALUES (?, 'withdrawal', ?, 'completed', 'Wallet withdrawal')
            `, [user_id, amount]);

            const newBalance = balance - amount;

            ws.send(JSON.stringify({
                type: "WALLET_UPDATED",
                balance: newBalance
            }));
        }
        // ================= FUND WALLET =================
        if (data.type === "FUND_WALLET") {

            const amount = parseFloat(data.amount);

            // ================= VALIDATION =================
            if (!amount || amount <= 0) {
                ws.send(JSON.stringify({
                    type: "FUND_FAILED",
                    reason: "INVALID_AMOUNT"
                }));
                return;
            }

            const user_id = ws.user_id;

            try {

                // ================= UPDATE WALLET =================
                await db.query(`
                    INSERT INTO wallet (user_id, balance)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)
                `, [user_id, amount]);

                // ================= LOG TRANSACTION =================
                await db.query(`
                    INSERT INTO wallet_transactions
                    (user_id, type, amount, status, description)
                    VALUES (?, 'deposit', ?, 'completed', 'Manual wallet funding')
                `, [user_id, amount]);

                // ================= GET UPDATED BALANCE =================
                const [[wallet]] = await db.query(
                    "SELECT balance FROM wallet WHERE user_id=?",
                    [user_id]
                );

                // ================= SEND RESPONSE =================
                ws.send(JSON.stringify({
                    type: "WALLET_UPDATED",
                    balance: parseFloat(wallet.balance)
                }));

                console.log("💰 Wallet funded:", user_id, amount);

            } catch (err) {
                console.error("FUND ERROR:", err);

                ws.send(JSON.stringify({
                    type: "FUND_FAILED",
                    reason: "SERVER_ERROR"
                }));
            }
        }
        // ================= STOP SELL =================
        if (data.type === "STOP_SELL") {

            const { house_uid } = data;

            const [[row]] = await db.query(`
                SELECT h.id house_id, n.node_uid
                FROM houses h
                JOIN house_node_map m ON m.house_id = h.id
                JOIN nodes n ON n.id = m.node_id
                WHERE h.house_uid = ?
            `,  [house_uid]);

            await db.query("UPDATE houses SET mode='idle' WHERE id=?", [row.house_id]);

            publishControl(row.node_uid, house_uid, "SELL_OFF");

            ws.send(JSON.stringify({ type: "FEEDBACK", reason: "SELL_STOPPED", message: "buy user" }));
        }

        // ================= START BUY =================
        if (data.type === "START_BUY") {

            const { house_uid } = data;

            if (!ws.allowedHouses.includes(house_uid)) return;

            const [[row]] = await db.query(`
                SELECT h.id house_id, n.node_uid, h.mode
                FROM houses h
                JOIN house_node_map m ON m.house_id = h.id
                JOIN nodes n ON n.id = m.node_id
                WHERE h.house_uid = ?
            `, [house_uid]);

            if (!row || row.mode === "selling") return;

            await db.query("UPDATE houses SET mode='buying' WHERE id=?", [row.house_id]);

            publishControl(row.node_uid, house_uid, "BUY_ON");

            ws.send(JSON.stringify({ type: "FEEDBACK", reason: "BUY_STARTED" }));
        }

        // ================= STOP BUY =================
        if (data.type === "STOP_BUY") {

            const { house_uid } = data;

            const [[row]] = await db.query(`
                SELECT h.id house_id, n.node_uid
                FROM houses h
                JOIN house_node_map m ON m.house_id = h.id
                JOIN nodes n ON n.id = m.node_id
                WHERE h.house_uid = ?
            `, [house_uid]);

            await db.query("UPDATE houses SET mode='idle' WHERE id=?", [row.house_id]);

            publishControl(row.node_uid, house_uid, "BUY_OFF");

            ws.send(JSON.stringify({ type: "FEEDBACK", reason: "BUY_STOPPED" }));
        }

        // ================= SET BUY LIMIT =================
        if (data.type === "SET_BUY_LIMIT") {

            const { house_uid, value } = data;

            // 🔒 AUTH CHECK
            if (!ws.allowedHouses.includes(house_uid)) {
                ws.send(JSON.stringify({
                    type: "FEEDBACK",
                    reason: "UNAUTHORIZED"
                }));
                return;
            }

            // 🔍 GET HOUSE
            const [[row]] = await db.query(`
                SELECT h.id AS house_id
                FROM houses h
                WHERE h.house_uid = ?
                LIMIT 1
            `, [house_uid]);

            if (!row) {
                ws.send(JSON.stringify({
                    type: "FEEDBACK",
                    reason: "HOUSE_NOT_FOUND"
                }));
                return;
            }

            const house_id = row.house_id;

            // ✅ VALIDATE VALUE
            const limit = parseFloat(value);

            if (isNaN(limit) || limit < 0) {
                ws.send(JSON.stringify({
                    type: "FEEDBACK",
                    reason: "INVALID_LIMIT"
                }));
                return;
            }

            // 💾 UPDATE LIMIT
            await db.query(`
                UPDATE houses
                SET buy_limit_kwh = ?
                WHERE id = ?
            `, [limit, house_id]);

            // 📤 RESPONSE
            ws.send(JSON.stringify({
                type: "FEEDBACK",
                reason: "BUY_LIMIT_SET",
                value: limit,
                house_uid
            }));

            console.log("📊 Buy limit updated:", { house_uid, limit });
        }
    });
});

// ================= MQTT HANDLER =================
mqttClient.on("message", async (topic, message) => {

    const data = JSON.parse(message.toString());

       console.log("📥 MQTT:", topic, data);

    const node_uid = data.device_id;
    const house_uid = data.house;
    const mode = data.mode;
    const increment = parseFloat(data.energy_increment || 0);

    const node_id = await getNodeId(node_uid);
    const house_id = await getHouseId(house_uid);

    if (!node_id || !house_id) {
        console.log("⚠️ Unknown node or house:", node_uid, house_uid);
        return;
    } 
    // if (mode === "selling") {
    let ws_sold_kwh = await accumulateSoldEnergy(mode, node_id, house_id, node_uid, house_uid, increment);
    // }

    const total = await updateEnergy(node_id, house_id, node_uid, house_uid, mode, increment);

    // ✅ FILTERED BROADCAST
    wss.clients.forEach(client => {
        if (
            client.readyState === WebSocket.OPEN &&
            client.allowedHouses.includes(house_uid)
        ) {
            client.send(JSON.stringify({
                type: "TELEMETRY",
                house_uid,
                node_uid,
                voltage: data.voltage,
                current: data.current,
                power: data.power,
                total_energy: total,
                sold_kwh: ws_sold_kwh,
                mode
            }));
            console.log("📤 WS Sent to", client.user_id, { house_uid, node_uid, voltage: data.voltage, current: data.current, power: data.power, total_energy: total, mode });
        }
    });
});

// ================= HELPERS =================
async function getNodeId(uid) {
    const [r] = await db.query("SELECT id FROM nodes WHERE node_uid=?", [uid]);
    return r[0]?.id;
}

async function getHouseId(uid) {
    const [r] = await db.query("SELECT id FROM houses WHERE house_uid=?", [uid]);
    return r[0]?.id;
}

// ================= ENERGY LOGIC =================
async function updateEnergy(node_id, house_id, node_uid, house_uid, mode, inc) {

    const [r] = await db.query(
        "SELECT total_energy FROM energy_totals WHERE node_id=? AND house_id=?",
        [node_id, house_id]
    );

// //////////////////////
//         if (mode === "selling") {
//     const [[row]] = await db.query(
//         "SELECT sell_cap_kwh, sold_kwh FROM sell_sessions WHERE house_id=?",
//         [house_id]
//     );

//     if (!row) return 0;

//     let sold = parseFloat(row.sold_kwh) || 0;
//     let cap  = parseFloat(row.sell_cap_kwh) || 0;

// //////////////////////////



    let current = r.length ? parseFloat(r[0].total_energy) : 0;

if (mode === "buying") {
    const [[row]] = await db.query(
        "SELECT buy_limit_kwh FROM houses WHERE house_uid=?",
        [house_uid]
    )
    const buy_limit_kwh = parseFloat (row.buy_limit_kwh)||0;
    
    if (buy_limit_kwh >= current){
        publishControl(node_uid, house_uid, "IDLE");
    wss.clients.forEach(client => {
                if (
                    client.readyState === WebSocket.OPEN && 
                    client.allowedHouses.includes(house_uid)
                ) {
                    client.send(JSON.stringify({
                        type: "FEEDBACK",
                        reason: "BUY_LIMIT_REACHED",
                        message: `Buy limit of ${buy_limit_kwh} kWh reached. Buying stopped.`
                    }));
                }
            });
            console.log("🛑 BUY LIMIT REACHED:", { house_uid, current, buy_limit_kwh });
    }
}



    if (mode === "selling") current += inc;
    if (mode === "buying") current -= inc;

    if (current < 0) current = 0;

    await db.query(`
        INSERT INTO energy_totals (node_id, house_id, total_energy)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE total_energy=?
    `, [node_id, house_id, current, current]);

    return current;
}

// ================= CONTROL =================
function publishControl(node_uid, house_uid, command) {

    let payload = {
        command,
        selling_relay: command === "SELL_ON",
        buying_relay: command === "BUY_ON"
    };

    mqttClient.publish(
        `powerstation/${node_uid}/${house_uid}/control`,
        JSON.stringify(payload)
    );
    console.log("📤 MQTT Published:", `powerstation/${node_uid}/${house_uid}/control`, payload);
}

// ================= SELL LOGIC =================
async function accumulateSoldEnergy(mode, node_id, house_id, node_uid, house_uid, inc) {
    
    if (mode === "selling") {
    const [[row]] = await db.query(
        "SELECT sell_cap_kwh, sold_kwh FROM sell_sessions WHERE house_id=?",
        [house_id]
    );

    if (!row) return 0;

    let sold = parseFloat(row.sold_kwh) || 0;
    let cap  = parseFloat(row.sell_cap_kwh) || 0;

    sold += inc;

    console.log(`📈 Selling: ${sold.toFixed(4)} / ${cap}`);

    // 🔴 LIMIT REACHED
    if (cap > 0 && sold >= cap) {
        sold = cap;

        console.log("🛑 SELL LIMIT REACHED");

        await db.query(
            "UPDATE houses SET mode='idle' WHERE id=?",
            [house_id]
        );

        publishControl(node_uid, house_uid, "IDLE");
        
        // if (!ws.allowedHouses.includes(house_uid)) return;

        // ws.send(JSON.stringify({
        //     type: "FEEDBACK",
        //     reason: "CAP_REACHED"
        //     // message: `Sell cap of ${cap} kWh reached. Selling stopped.`
        // }));

        wss.clients.forEach(client => {
            if (
                client.readyState === WebSocket.OPEN &&
                client.allowedHouses.includes(house_uid)
            ) {
                client.send(JSON.stringify({
            type: "FEEDBACK",
            reason: "CAP_REACHED"
                }));
            }
        });
    }

    await db.query(
        "UPDATE sell_sessions SET sold_kwh=? WHERE house_id=?",
        [sold, house_id]
    );

    return sold; // ✅ VERY IMPORTANT
}
}