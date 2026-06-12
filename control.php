<?php
include_once 'sidebar.php';
require_once 'config.php';

$user_id = $_SESSION['user_id'] ?? 0;
$uname   = htmlspecialchars($_SESSION['user_name'] ?? 'User');
$node_name = htmlspecialchars($_SESSION['node_name'] ?? 'Unassigned');
$node_loc  = htmlspecialchars($_SESSION['node_loc'] ?? '—');

// Get house data (mode, buy_limit_kwh, house_uid)
$stmt = $pdo->prepare("SELECT id, mode, buy_limit_kwh, house_uid FROM houses WHERE user_id = ? LIMIT 1");
$stmt->execute([$user_id]);
$house = $stmt->fetch(PDO::FETCH_ASSOC);

$current_mode = strtolower($house['mode'] ?? 'idle');
$buy_limit    = $house['buy_limit_kwh'] ?? null;
$house_uid    = $house['house_uid'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control Panel | Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Same CSS as before – unchanged */
        * { margin:0; padding:0; box-sizing:border-box; }
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
            --warning: #f59e0b;
        }
        .main-content {
            margin-left: 260px;
            padding: 1.5rem;
            transition: margin-left 0.2s;
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 1rem; }
        }
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 1.2rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(2px);
        }
        .card-title {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 0.8rem;
        }
        .mode-badge {
            display: inline-block;
            padding: 0.3rem 1rem;
            border-radius: 2rem;
            font-weight: 700;
            font-size: 1rem;
        }
        .mode-idle { background: #334155; color: white; }
        .mode-selling { background: var(--accent); color: #020c16; }
        .mode-buying { background: var(--warning); color: #020c16; }
        .limit-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin: 0.8rem 0;
        }
        .limit-status { font-weight: 600; }
        .limit-active { color: var(--accent); }
        .limit-off { color: var(--danger); }
        .input-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }
        input {
            background: #011015;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            padding: 0.6rem 0.8rem;
            color: white;
            font-size: 0.8rem;
            flex: 1;
        }
        .buttons-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin: 1rem 0;
        }
        .btn {
            border: none;
            padding: 0.8rem;
            border-radius: 0.8rem;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .btn-primary {
            background: var(--accent);
            color: #020c16;
        }
        .btn-primary:hover { background: #0e9f6e; transform: translateY(-1px); }
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        .btn-danger:hover { background: #dc2626; }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--accent);
        }
        .btn-outline:hover { border-color: var(--accent); background: rgba(16,185,129,0.1); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .status-text {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 0.8rem;
            padding: 0.4rem;
            border-radius: 0.5rem;
            background: rgba(0,0,0,0.3);
        }
        .session-info {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
        }
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .alert {
            min-width: 250px;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease;
        }
        .alert-success { background: #059669; }
        .alert-warning { background: #f59e0b; }
        .alert-error   { background: #dc2626; }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to   { transform: translateX(0); opacity: 1; }
        }
        @media (max-width: 640px) {
            .buttons-grid { grid-template-columns: 1fr; }
            .btn { justify-content: center; }
        }
    </style>
</head>
<body>
<div class="main-content">
    <!-- Session Card with mode integrated -->
    <div class="card">
        <div class="session-info">
            <div><strong>👤 <?= $uname ?></strong></div>
            <div>📍 Node: <strong><?= $node_name ?></strong> — <?= $node_loc ?></div>
            <div>
                <span class="mode-badge mode-<?= $current_mode ?>" id="modeBadge">
                    <?= strtoupper($current_mode) ?>
                </span>
            </div>
        </div>
    </div>
    <div id="alertContainer" class="alert-container"></div>

    <!-- Buy Limit Card -->
    <div class="card">
        <div class="card-title">Energy Purchase Limit</div>
        <div class="limit-row">
            <span>Status:</span>
            <span id="limitStatus" class="limit-status <?= $buy_limit !== null ? 'limit-active' : 'limit-off' ?>">
                <?= $buy_limit !== null ? 'ACTIVE' : 'NOT SET' ?>
            </span>
        </div>
        <div class="limit-row">
            <span>Limit Value:</span>
            <span id="limitValue"><?= $buy_limit !== null ? number_format($buy_limit,2).' kWh' : '--' ?></span>
        </div>
        <div class="input-group">
            <input type="number" id="limitInput" step="0.5" placeholder="Enter limit (kWh)">
            <button class="btn btn-outline" id="setLimitBtn">Set Limit</button>
        </div>
    </div>

    <!-- Control Buttons Card -->
    <div class="card">
        <div class="card-title">Manual Control</div>
        <div class="buttons-grid">
            <button id="startSellBtn" class="btn btn-primary">▶ Start Selling</button>
            <button id="stopSellBtn" class="btn btn-danger">⏹ Stop Selling</button>
            <button id="startBuyBtn" class="btn btn-primary">▶ Start Buying</button>
            <button id="stopBuyBtn" class="btn btn-danger">⏹ Stop Buying</button>
        </div>
        <div id="statusText" class="status-text">System ready</div>
    </div>
</div>

<script>
    // ================= CONFIG =================
    const WS_URL = "ws://" + window.location.hostname + ":3000";
    let ws;
    let pendingRequest = false;
    let authenticated = false;
    let currentMode = "<?= $current_mode ?>";
    const userId = <?= (int)$user_id ?>;
    const houseUid = "<?= htmlspecialchars($house_uid) ?>";

    // DOM elements
    const modeBadge = document.getElementById("modeBadge");
    const limitStatus = document.getElementById("limitStatus");
    const limitValueSpan = document.getElementById("limitValue");
    const statusDiv = document.getElementById("statusText");
    const startSellBtn = document.getElementById("startSellBtn");
    const stopSellBtn = document.getElementById("stopSellBtn");
    const startBuyBtn = document.getElementById("startBuyBtn");
    const stopBuyBtn = document.getElementById("stopBuyBtn");
    const setLimitBtn = document.getElementById("setLimitBtn");
    const limitInput = document.getElementById("limitInput");

    // Helper: update mode UI
    function updateModeUI(mode) {
        currentMode = mode;
        modeBadge.className = `mode-badge mode-${mode}`;
        modeBadge.innerText = mode.toUpperCase();
        const isSelling = (mode === "selling");
        const isBuying = (mode === "buying");
        startSellBtn.disabled = isSelling;
        stopSellBtn.disabled = !isSelling;
        startBuyBtn.disabled = isBuying;
        stopBuyBtn.disabled = !isBuying;
    }

    function setStatus(msg, isLoading = false) {
        if (isLoading) {
            statusDiv.innerHTML = `<span class="spinner"></span> ${msg}`;
        } else {
            statusDiv.innerText = msg;
        }
    }

    function setButtonsEnabled(enabled) {
        const btns = [startSellBtn, stopSellBtn, startBuyBtn, stopBuyBtn, setLimitBtn];
        btns.forEach(btn => { if (btn) btn.disabled = !enabled; });
    }

    function showAlert(message, type = "success") {
        const container = document.getElementById("alertContainer");
        const alertDiv = document.createElement("div");
        alertDiv.className = `alert alert-${type}`;
        alertDiv.innerText = message;
        container.appendChild(alertDiv);
        setTimeout(() => {
            alertDiv.style.opacity = "0";
            alertDiv.style.transform = "translateX(100%)";
            setTimeout(() => alertDiv.remove(), 300);
        }, 4000);
    }

    // ================= WEBSOCKET =================
    function connectWS() {
        ws = new WebSocket(WS_URL);

        ws.onopen = () => {
            console.log("🟢 WS Connected");
            ws.send(JSON.stringify({ type: "AUTH", user_id: userId }));
        };

        ws.onmessage = (event) => {
            let data;
            try { data = JSON.parse(event.data); } catch(e) { console.error("Invalid JSON", event.data); return; }
            console.log("WS:", data);

            if (data.type === "AUTH_SUCCESS") {
                authenticated = true;
                setStatus("Authenticated. Ready.");
                updateModeUI(currentMode);
                setButtonsEnabled(true);
                return;
            }
            if (data.type === "AUTH_FAILURE") {
                authenticated = false;
                setStatus("❌ Authentication failed. Please refresh.");
                setButtonsEnabled(false);
                return;
            }

            if (!authenticated) return;

            pendingRequest = false;
            setButtonsEnabled(true);
            setStatus("System ready");

            switch (data.reason) {
                case "SELL_STARTED":
                    updateModeUI("selling");
                    setStatus("✅ Selling started – energy is being injected into the grid.");
                    showAlert("Selling started successfully for " + data.house_uid + " at " + data.amount_kwh + " kWh.", "success");
                    break;
                case "SELL_STOPPED":
                    updateModeUI("idle");
                    setStatus("⏹ Selling stopped.");
                    if (data.message === "BY_USER") {
                        showAlert("Selling stopped by user.", "warning");
                    }
                    break;
                case "BUY_STARTED":
                    updateModeUI("buying");
                    setStatus("✅ Buying started – drawing energy from the grid.");
                    showAlert("Buying started successfully for " + data.house_uid + " at " + data.amount_kwh + " kWh.", "success");
                    break;
                case "BUY_STOPPED":
                    updateModeUI("idle");
                    setStatus("⏹ Buying stopped.");
                    if (data.reason === "NO_ENERGY") showAlert("⚠️ Energy exhausted. Buying stopped.", "warning");
                    if (data.reason === "LIMIT_REACHED") showAlert("⚠️ Buy limit reached. Buying stopped.", "warning");
                    break;
                case "BUY_AUTO_STOP":
                    updateModeUI("idle");
                    setStatus("⚠️ Auto‑stopped: insufficient energy balance.");
                    break;
                case "BUY_LIMIT_REACHED":
                    updateModeUI("idle");
                    setStatus("🔴 Limit reached – buying automatically stopped.");
                    limitStatus.innerText = "ACTIVE (TRIGGERED)";
                    break;
                case "SELL_REJECTED":
                    updateModeUI("idle");
                    let msg = "Sell rejected: ";
                    switch (data.reason) {
                        case "CAP_REACHED": msg += "Amount exceeds your cap."; break;
                        case "INVALID_AMOUNT": msg += "Invalid amount specified."; break;
                        case "NO_HOUSE": msg += "No house associated."; break;
                        case "NOT_AUTHORIZED": msg += "You are not authorized to sell."; break;
                        case "CONFLICT_MODE": msg += "Cannot sell while buying is active."; break;
                        default: msg += data.reason || "Unknown error.";
                    }
                    showAlert(msg, "error");
                    setStatus("🔴 Selling rejected.");
                    break;
                case "ERROR":
                    setStatus(`❌ Error: ${data.msg}`);
                    showAlert(message, "error");
                    break;
                case "BUY_LIMIT_SET":
                    limitValueSpan.innerText = data.value.toFixed(2) + " kWh";
                    limitStatus.innerText = "ACTIVE";
                    limitStatus.className = "limit-status limit-active";
                    setStatus(`✅ Buy limit set to ${data.value.toFixed(2)} kWh.`);
                    showAlert("Limit set successfully.", "success");
                    document.getElementById("limitValue").innerText = data.value.toFixed(2) + " kWh";
                    break;
                default: break;
            }
        };

        ws.onerror = () => {
            setStatus("⚠️ WebSocket connection error.");
            setButtonsEnabled(false);
        };

        ws.onclose = () => {
            console.log("🔴 Disconnected, reconnecting in 3s...");
            setTimeout(connectWS, 3000);
        };
    }

    function sendCommand(type, extra = {}) {
        if (!authenticated) {
            setStatus("Not authenticated yet. Please wait.");
            return;
        }
        if (pendingRequest) {
            setStatus("Please wait, previous action still processing...");
            return;
        }
        pendingRequest = true;
        setButtonsEnabled(false);
        setStatus(`Sending ${type}...`, true);
        const payload = { type, house_uid: houseUid, ...extra };
        ws.send(JSON.stringify(payload));
        setTimeout(() => {
            if (pendingRequest) {
                pendingRequest = false;
                setButtonsEnabled(true);
                setStatus("Request timed out. Check connection.");
            }
        }, 5000);
    }

    // Button handlers
    startSellBtn.onclick = () => {
        if (currentMode === "buying") {
            setStatus("Cannot start selling while buying is active. Stop buying first.");
            return;
        }
        sendCommand("START_SELL", { amount_kwh: 5 });
    };
    stopSellBtn.onclick = () => sendCommand("STOP_SELL");
    startBuyBtn.onclick = () => {
        if (currentMode === "selling") {
            setStatus("Cannot start buying while selling is active. Stop selling first.");
            return;
        }
        sendCommand("START_BUY");
    };
    stopBuyBtn.onclick = () => sendCommand("STOP_BUY");
    setLimitBtn.onclick = () => {
        const value = parseFloat(limitInput.value);
        if (isNaN(value) || value <= 0) {
            setStatus("Please enter a valid positive limit (kWh).");
            return;
        }
        sendCommand("SET_BUY_LIMIT", { value });
        limitInput.value = "";
    };

    // Initial connection
    connectWS();
</script>
</body>
</html>