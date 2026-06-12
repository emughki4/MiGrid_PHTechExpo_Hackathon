<?php
include_once 'sidebar.php';
require_once 'config.php';

$user_id   = $_SESSION['user_id'] ?? 0;
$uname     = htmlspecialchars($_SESSION['user_name'] ?? 'User');
$node_name = htmlspecialchars($_SESSION['node_name'] ?? 'Unassigned');
$node_loc  = htmlspecialchars($_SESSION['node_loc'] ?? '—');

// House
$stmt = $pdo->prepare("SELECT id FROM houses WHERE user_id=? LIMIT 1");
$stmt->execute([$user_id]);
$house = $stmt->fetch();
$house_id = $house['id'] ?? 0;

// Total energy
$stmt = $pdo->prepare("SELECT total_energy FROM energy_totals WHERE house_id=? LIMIT 1");
$stmt->execute([$house_id]);
$row = $stmt->fetch();
$total_energy = $row ? floatval($row['total_energy']) : 0;

// Sell limits
$stmt = $pdo->prepare("SELECT sell_cap_kwh, sold_kwh FROM sell_sessions WHERE house_id=? LIMIT 1");
$stmt->execute([$house_id]);
$sell = $stmt->fetch();
$sell_cap = $sell ? floatval($sell['sell_cap_kwh']) : 0;
$sold     = $sell ? floatval($sell['sold_kwh']) : 0;
$progress = ($sell_cap > 0) ? ($sold / $sell_cap) * 100 : 0;

// Recent transactions
$stmt = $pdo->prepare("
    SELECT type, amount_kwh, created_at 
    FROM transactions 
    WHERE house_id=? 
    ORDER BY id DESC LIMIT 5
");
$stmt->execute([$house_id]);
$transactions = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Energy Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #020c16;
            color: #e8ecf1;
        }

        :root {
            --bg: #020c16;
            --accent: #10b981;
            --accent-dark: rgba(4,30,33,0.6);
            --border: rgba(16,185,129,0.3);
            --card-bg: linear-gradient(135deg, rgba(4,30,33,0.6), rgba(1,11,12,0.6));
            --text-muted: #8ba8b5;
            --danger: #ef4444;
        }

        .main-content {
            margin-left: 260px;
            padding: 1.5rem;
            transition: margin-left 0.2s;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }

        /* Cards */
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 1.2rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(2px);
            transition: transform 0.1s, box-shadow 0.2s;
        }

        .card:hover {
            border-color: var(--accent);
            box-shadow: 0 4px 12px rgba(16,185,129,0.1);
        }

        .card-title {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 0.8rem;
        }

        .value-large {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--accent);
            line-height: 1;
        }

        .value-unit {
            font-size: 0.9rem;
            font-weight: 400;
            color: var(--text-muted);
            margin-left: 0.3rem;
        }

        /* Grid */
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.2rem;
        }

        /* Live data row */
        .live-data {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.8rem;
            margin-top: 0.8rem;
        }
        .live-item {
            background: rgba(0,0,0,0.3);
            border-radius: 0.8rem;
            padding: 0.5rem 0.8rem;
            text-align: center;
            flex: 1;
        }
        .live-label {
            font-size: 0.65rem;
            color: var(--text-muted);
        }
        .live-value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--accent);
        }

        /* Progress bar */
        .progress-container {
            margin: 0.8rem 0;
        }
        .progress-bar-bg {
            background: #1e2a3a;
            border-radius: 20px;
            height: 8px;
            overflow: hidden;
        }
        .progress-fill {
            background: var(--accent);
            width: 0%;
            height: 100%;
            border-radius: 20px;
            transition: width 0.3s;
        }

        /* Buttons */
        .btn {
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
            margin-right: 0.8rem;
            margin-top: 0.5rem;
        }
        .btn-primary {
            background: var(--accent);
            color: #020c16;
        }
        .btn-primary:hover {
            background: #0e9f6e;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(16,185,129,0.3);
        }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--accent);
        }
        .btn-outline:hover {
            border-color: var(--accent);
            background: rgba(16,185,129,0.1);
        }
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        .btn-danger:hover {
            background: #dc2626;
        }

        /* Table */
        .transaction-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }
        .transaction-table td {
            padding: 0.6rem 0;
            border-bottom: 1px solid rgba(16,185,129,0.1);
        }
        .transaction-table tr:last-child td {
            border-bottom: none;
        }
        .badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .badge-buy {
            background: #10b98120;
            color: #10b981;
        }
        .badge-sell {
            background: #f59e0b20;
            color: #f59e0b;
        }

        /* Session row */
        .session-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        @media (max-width: 640px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
            .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body>
<div class="main-content">

    <!-- Session Card -->
    <div class="card">
        <div class="session-info">
            <div>
                <strong>👤 <?= $uname ?></strong>
            </div>
            <div>
                📍 Node: <strong><?= $node_name ?></strong> — <?= $node_loc ?>
            </div>
        </div>
    </div>
        <!-- <?php
        echo "<pre>";
print_r($_SESSION);
echo "</pre>";
        ?> -->
    <!-- Energy + Live Data (2 cols) -->
    <div class="grid-2">
        <!-- Energy Balance Card -->
        <div class="card">
            <div class="card-title">Total Energy</div>
            <div>
                <span class="value-large" id="energyValue"><?= number_format($total_energy, 2) ?></span>
                <span class="value-unit">kWh</span>
            </div>
            <div class="progress-container">
                <div id="energySold">Sold: <?= number_format($sold, 2) ?> / <?= number_format($sell_cap, 2) ?> kWh</div>
                <div class="progress-bar-bg">
                    <div id="energyProgress" class="progress-fill" style="width: <?= $progress ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Live Monitoring Card -->
        <div class="card">
            <div class="card-title">Live Status</div>
            <div class="live-data">
                <div class="live-item">
                    <div class="live-label">Mode</div>
                    <div class="live-value" id="mode">IDLE</div>
                </div>
                <div class="live-item">
                    <div class="live-label">Power</div>
                    <div class="live-value" id="power">0 <span style="font-size:0.7rem;">W</span></div>
                </div>
                <div class="live-item">
                    <div class="live-label">Voltage</div>
                    <div class="live-value" id="voltage">0 <span style="font-size:0.7rem;">V</span></div>
                </div>
                <div class="live-item">
                    <div class="live-label">Current</div>
                    <div class="live-value" id="current">0 <span style="font-size:0.7rem;">A</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Control Panel Card -->
    <div class="card">
        <div class="card-title">Control Panel</div>
        <div>
            <button id="sellBtn" class="btn btn-primary">Start Selling</button>
            <button id="buyBtn" class="btn btn-outline">Start Buying</button>
        </div>
        <div style="font-size:0.7rem; color:var(--text-muted); margin-top:0.5rem;">
            * Selling injects energy into the grid; buying draws energy.
        </div>
    </div>

    <!-- Recent Transactions Card -->
    <div class="card">
        <div class="card-title">Recent Activity</div>
        <table class="transaction-table">
            <?php if (empty($transactions)): ?>
                <tr><td>No transactions yet.</td></tr>
            <?php else: ?>
                <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td>
                            <span class="badge badge-<?= $t['type'] ?>">
                                <?= strtoupper($t['type']) ?>
                            </span>
                        </td>
                        <td><?= number_format($t['amount_kwh'], 2) ?> kWh</td>
                        <td style="text-align: right;"><?= $t['transaction_date'] ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>

</div>

<!-- WebSocket Script -->
<script>
    const ws = new WebSocket("ws://" + window.location.hostname + ":3000");

    let currentMode = "idle";
    let activeHouse = null;

    // ================= CONNECT =================
    ws.onopen = () => {
        console.log("✅ WS Connected");

        // 🔐 AUTH (REQUIRED)
        ws.send(JSON.stringify({
            type: "AUTH",
            user_id: <?= $user_id ?>
        }));
    };

    // ================= RECEIVE =================
    ws.onmessage = (event) => {
        const data = JSON.parse(event.data);
        console.log("WS:", data);

        // ✅ AUTH SUCCESS
        if (data.type === "AUTH_SUCCESS") {
            console.log("🏠 Houses:", data.houses);

            // for now single house system
            activeHouse = data.houses[0];

            return;
        }
        

        // ================= TELEMETRY =================
        if (data.type === "TELEMETRY") {

            // ensure correct house (extra safety)
            if (data.house_uid !== activeHouse) return;

            if (data.total_energy !== undefined) {
                document.getElementById("energyValue").innerText =
                    parseFloat(data.total_energy).toFixed(2);
            }
            const SELL_CAP = <?= json_encode($sell_cap ?? 0) ?>;

            if (data.sold_kwh !== undefined) {
                document.getElementById("energySold").innerText =
                    `Sold: ${parseFloat(data.sold_kwh).toFixed(2)} / ${parseFloat(SELL_CAP).toFixed(2)} kWh`;

            const progressPercent = (SELL_CAP > 0) ? (data.sold_kwh / SELL_CAP) * 100 : 0;
            document.getElementById("energyProgress").style.width = progressPercent + "%";}

            if (data.power !== undefined) {
                document.getElementById("power").innerHTML =
                    data.power.toFixed(1) + ' <span style="font-size:0.7rem;">W</span>';

                document.getElementById("voltage").innerHTML =
                    data.voltage.toFixed(1) + ' <span style="font-size:0.7rem;">V</span>';

                document.getElementById("current").innerHTML =
                    data.current.toFixed(2) + ' <span style="font-size:0.7rem;">A</span>';
            }

            if (data.mode) {
                currentMode = data.mode.toLowerCase();
                document.getElementById("mode").innerText = currentMode.toUpperCase();
                updateButtons();
            }
        }
        if (data.type === "FEEDBACK") {
            switch (data.reason) {
                case "CAP_REACHED":
                    alert(data.message);
                    break;
                case "BUY_STARTED":
                    alert("Buying started.");
                    break;
                case "BUY_STOPPED":
                    alert("Buying stopped.");
                    break;
                case "SELL_STARTED":
                    alert("Selling started.");
                    break;
                case "SELL_STOPPED":
                    alert("Selling stopped.");
                    break;
                case "INVALID_AMOUNT":
                    alert("Invalid amount. Please enter a positive number.");
                    break;
                case "CONFLICT_MODE":
                    alert("Cannot start this action while the opposite mode is active.");
                default:
                }
            alert(data.message);
        }
    };

    // ================= BUTTON UI =================
    function updateButtons() {
        const sellBtn = document.getElementById("sellBtn");
        const buyBtn = document.getElementById("buyBtn");

        if (currentMode === "selling") {
            sellBtn.innerText = "Stop Selling";
            sellBtn.classList.remove("btn-primary");
            sellBtn.classList.add("btn-danger");
        } else {
            sellBtn.innerText = "Start Selling";
            sellBtn.classList.remove("btn-danger");
            sellBtn.classList.add("btn-primary");
        }

        if (currentMode === "buying") {
            buyBtn.innerText = "Stop Buying";
            buyBtn.classList.remove("btn-outline");
            buyBtn.classList.add("btn-danger");
        } else {
            buyBtn.innerText = "Start Buying";
            buyBtn.classList.remove("btn-danger");
            buyBtn.classList.add("btn-outline");
        }
    }

    // ================= SELL =================
    document.getElementById("sellBtn").onclick = () => {

        if (!activeHouse) return;

        if (currentMode === "selling") {

            ws.send(JSON.stringify({
                type: "STOP_SELL",
                house_uid: activeHouse
            }));

        } else {
            // redirect to sell page
            window.location.href = "sell.php";
        }
    };

    // ================= BUY =================
    document.getElementById("buyBtn").onclick = () => {

        if (!activeHouse) return;

        if (currentMode === "buying") {

            ws.send(JSON.stringify({
                type: "STOP_BUY",
                house_uid: activeHouse
            }));

        } else {

            ws.send(JSON.stringify({
                type: "START_BUY",
                house_uid: activeHouse
            }));
        }
    };
</script>

</body>
</html>