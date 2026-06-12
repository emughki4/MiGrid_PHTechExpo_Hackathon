<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sidebar.php';
requireLogin();

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) redirect('login.php');

// 1. Get the user's house (including house_uid)
$stmt = $pdo->prepare("
    SELECT id, name, location, sell_eligibility, max_sell_kwh, verified, house_uid
    FROM houses
    WHERE user_id = ?
    LIMIT 1
");
$stmt->execute([$user_id]);
$house = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$house) {
    redirect('create_house.php');
}

$house_id       = $house['id'];
$house_uid      = $house['house_uid'];
$house_name     = htmlspecialchars($house['name']);
$house_location = htmlspecialchars($house['location']);
$can_sell       = (bool) $house['sell_eligibility'];
$max_sell_kwh   = (float) $house['max_sell_kwh'];

// 2. Get current energy balance from energy_totals
$stmt = $pdo->prepare("
    SELECT total_energy
    FROM energy_totals
    WHERE house_id = ?
    LIMIT 1
");
$stmt->execute([$house_id]);
$energyRow = $stmt->fetch(PDO::FETCH_ASSOC);
$balance_kwh = $energyRow ? (float) $energyRow['total_energy'] : 0.0;

// 3. Get selling price
$stmt = $pdo->query("SELECT selling_price FROM price_settings LIMIT 1");
$priceRow = $stmt->fetch(PDO::FETCH_ASSOC);
$selling_price = $priceRow ? (float) $priceRow['selling_price'] : 0.12;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sell Energy | MiGrid</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Copy your existing CSS from the previous version – unchanged */
        :root {
            --bg-dark: #020c16;
            --bg-card: rgba(4,30,33,0.6);
            --accent: #10b981;
            --accent-light: #6ee7b7;
            --danger: #ef4444;
            --text-primary: #e8ecf1;
            --text-secondary: #cbd5e1;
            --text-tertiary: #8ba8b5;
            --border: rgba(16,185,129,0.3);
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--bg-dark) 0%, #051419 100%);
            color: var(--text-primary);
            font-size: 0.875rem;
        }
        .main-content { margin-left:260px; padding:2rem; min-height:100vh; }
        @media (max-width:768px) { .main-content { margin-left:0; padding:1rem; } }
        .page-header { margin-bottom:2rem; }
        .page-title { font-size:2rem; font-weight:700; margin-bottom:0.5rem; color:var(--text-primary); }
        .page-subtitle { font-size:0.9rem; color:var(--text-tertiary); }
        .alert { padding:1rem; border-radius:0.75rem; margin-bottom:1.5rem; border-left:4px solid; animation:slideIn 0.3s ease; }
        .alert-success { background:rgba(16,185,129,0.15); border-color:var(--accent); color:var(--accent-light); }
        .alert-error   { background:rgba(239,68,68,0.15); border-color:var(--danger); color:#fca5a5; }
        .card {
            background: linear-gradient(135deg, var(--bg-card), rgba(1,11,12,0.8));
            border:1px solid var(--border); border-radius:1.25rem; padding:2rem;
            backdrop-filter:blur(10px); transition:all 0.3s ease; margin-bottom:2rem;
        }
        .card:hover { border-color:var(--accent); box-shadow:0 12px 32px rgba(16,185,129,0.1); }
        .form-group { margin-bottom:1.5rem; }
        .form-group label {
            display:block; font-size:0.8rem; color:var(--text-tertiary);
            font-weight:600; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.5rem;
        }
        .form-group input {
            width:100%; padding:0.75rem; background:rgba(0,0,0,0.3);
            border:1px solid var(--border); border-radius:0.75rem;
            color:var(--text-primary); font-size:1rem; font-family:inherit;
            transition:all 0.3s ease;
        }
        .form-group input:focus { outline:none; border-color:var(--accent); box-shadow:0 0 12px rgba(16,185,129,0.3); background:rgba(0,0,0,0.5); }
        .form-group input::placeholder { color:var(--text-tertiary); }
        .price-info {
            background:rgba(16,185,129,0.1); border:1px solid var(--border);
            border-radius:0.75rem; padding:1rem; margin-bottom:1.5rem;
            display:grid; grid-template-columns:1fr 1fr; gap:1rem;
        }
        .price-info-item { text-align:center; }
        .price-info-label { font-size:0.75rem; color:var(--text-tertiary); text-transform:uppercase; margin-bottom:0.3rem; font-weight:600; }
        .price-info-value { font-size:1.25rem; font-weight:700; color:var(--accent); }
        .btn {
            padding:0.75rem 1.5rem; border:none; border-radius:0.75rem;
            font-size:0.9rem; font-weight:600; cursor:pointer; transition:all 0.3s ease;
            text-transform:uppercase; letter-spacing:0.05em; width:100%; text-align:center;
        }
        .btn-primary {
            background:linear-gradient(135deg, var(--accent), var(--accent-light));
            color:#0f172a; box-shadow:0 4px 15px rgba(16,185,129,0.3);
        }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(16,185,129,0.5); }
        .btn-primary:active { transform:translateY(0); }
        .form-helper { font-size:0.75rem; color:var(--text-tertiary); margin-top:0.5rem; }
        .eligibility-notice {
            background:linear-gradient(135deg, rgba(16,185,129,0.1), rgba(16,185,129,0.05));
            border:2px solid var(--accent); border-radius:1.25rem; padding:2rem; text-align:center;
        }
        .notice-icon { font-size:3rem; margin-bottom:1rem; }
        .notice-title { font-size:1.3rem; font-weight:700; margin-bottom:0.5rem; }
        .notice-text { color:var(--text-tertiary); margin-bottom:1.5rem; line-height:1.6; }
        .calculation-box {
            background:rgba(16,185,129,0.1); border:1px solid var(--border);
            border-radius:0.75rem; padding:1rem; margin-bottom:1.5rem;
        }
        .calc-row { display:flex; justify-content:space-between; align-items:center; padding:0.5rem 0; font-size:0.85rem; }
        .calc-row-total { border-top:1px solid var(--border); padding-top:0.75rem; margin-top:0.75rem; font-weight:600; color:var(--accent-light); font-size:1rem; }
        .help-text { font-size:0.75rem; color:var(--text-tertiary); margin-top:1rem; line-height:1.6; background:rgba(16,185,129,0.05); padding:0.75rem; border-radius:0.5rem; }
        @media (max-width:640px) { .card { padding:1.5rem; } .page-title { font-size:1.5rem; } }
        .alert-container { position:fixed; top:20px; right:20px; z-index:9999; display:flex; flex-direction:column; gap:10px; }
        @keyframes slideIn { from { transform:translateX(100%); opacity:0; } to   { transform:translateX(0); opacity:1; } }
    </style>
</head>
<body>
<div class="main-content">
    <div class="page-header">
        <h1 class="page-title">⚡ Sell Energy</h1>
        <p class="page-subtitle"><?= $house_name ?> • <?= $house_location ?></p>
    </div>

    <div id="alertContainer" class="alert-container"></div>

    <?php if (!$can_sell): ?>
        <div class="card">
            <div class="eligibility-notice">
                <div class="notice-icon">🔒</div>
                <div class="notice-title">Not Eligible to Sell Yet</div>
                <div class="notice-text">
                    Complete your seller registration to start injecting energy into the grid and earn money.
                </div>
                <a href="register_as_seller.php" style="text-decoration: none;">
                    <button class="btn btn-secondary">🚀 Start Selling Now</button>
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="price-info">
                <div class="price-info-item">
                    <div class="price-info-label">Selling Rate</div>
                    <div class="price-info-value">$<?= number_format($selling_price, 4) ?>/kWh</div>
                </div>
                <div class="price-info-item">
                    <div class="price-info-label">Available Balance</div>
                    <div id="energyValue" class="price-info-value"><?= number_format($balance_kwh, 2) ?> kWh</div>
                </div>
            </div>

            <form method="post" id="sellForm">
                <div class="form-group">
                    <label for="amount_kwh">Amount to Sell (kWh)</label>
                    <input type="number" id="amount_kwh" name="amount_kwh" placeholder="0.00"
                           step="0.1" min="0" max="<?= $max_sell_kwh ?>"
                           onchange="calculateEarnings()" oninput="calculateEarnings()" required>
                    <div class="form-helper">Max per sale: <?= number_format($max_sell_kwh, 2) ?> kWh</div>
                </div>

                <div class="calculation-box">
                    <div class="calc-row"><span>Amount:</span><span id="calc-amount">0.00 kWh</span></div>
                    <div class="calc-row"><span>Rate:</span><span>$<?= number_format($selling_price, 4) ?>/kWh</span></div>
                    <div class="calc-row calc-row-total"><span>You Will Receive:</span><span id="calc-total">$0.00</span></div>
                </div>

                <button type="button" id="sellBtn" class="btn btn-primary">💰 Confirm Sale</button>
            </form>

            <div class="help-text">
                <strong>How it works:</strong> Enter the amount of energy you want to sell, review the calculation, and confirm.
                The amount will be deducted from your balance and credited to your wallet.
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    // ================= CONFIG =================
    const WS_URL = "ws://" + window.location.hostname + ":3000";
    let ws;
    const userId = <?= (int)$_SESSION['user_id'] ?>;
    const houseUid = "<?= htmlspecialchars($house_uid) ?>";
    let reconnectTimer;

    // ================= UTILITIES =================
    function showAlert(message, type = "success") {
        const container = document.getElementById("alertContainer");
        const alertDiv = document.createElement("div");
        alertDiv.className = `alert alert-${type === "error" ? "error" : "success"}`;
        alertDiv.innerText = message;
        container.appendChild(alertDiv);
        setTimeout(() => {
            alertDiv.style.opacity = "0";
            alertDiv.style.transform = "translateX(100%)";
            setTimeout(() => alertDiv.remove(), 300);
        }, 4000);
    }

    function calculateEarnings() {
        const input = document.getElementById('amount_kwh');
        const amount = parseFloat(input.value) || 0;
        const rate = <?= $selling_price ?>;
        const total = amount * rate;
        document.getElementById('calc-amount').textContent = amount.toFixed(2) + ' kWh';
        document.getElementById('calc-total').textContent = '$' + total.toFixed(2);
    }
    document.addEventListener('DOMContentLoaded', calculateEarnings);

    // ================= WEB SOCKET =================
    function connectWS() {
        if (ws && ws.readyState === WebSocket.OPEN) return;
        ws = new WebSocket(WS_URL);

        ws.onopen = () => {
            console.log("🟢 WS Connected");
            ws.send(JSON.stringify({ type: "AUTH", user_id: userId }));
        };

        ws.onmessage = (event) => {
            const data = JSON.parse(event.data);
            console.log("📩 WS:", data);

            // Update energy balance
            if (data.total_energy !== undefined) {
                const total = parseFloat(data.total_energy) || 0;
                document.getElementById("energyValue").innerText = total.toFixed(2);
                // low energy warning handled by backend or can be added here
            }

            // Handle action responses
            switch (data.type) {
                case "AUTH_SUCCESS":
                    console.log("✅ Authenticated");
                    break;
                case "AUTH_FAILURE":
                    console.error("❌ Authentication failed");
                    ws.close();
                    break;
                case "SELL_STARTED":
                    showAlert(`✅ Selling ${data.amount_kwh} kWh started.`, "success");
                    // Optionally reload page after short delay to refresh balance
                    setTimeout(() => location.reload(), 1500);
                    break;
                case "SELL_STOPPED":
                    console.log("🔴 Selling stopped");
                    break;
                case "SELL_REJECTED":
                    let msg = "Sell rejected: ";
                    switch (data.reason) {
                        case "EXCEEDS_CAP": msg += "Amount exceeds your maximum limit."; break;
                        case "INVALID_AMOUNT": msg += "Invalid amount specified."; break;
                        case "NO_HOUSE": msg += "No house associated."; break;
                        case "NOT_AUTHORIZED": msg += "You are not authorized to sell."; break;
                        case "CONFLICT_MODE": msg += "Cannot sell while buying is active."; break;
                        default: msg += data.reason || "Unknown error.";
                    }
                    showAlert(msg, "error");
                    break;
                case "ERROR":
                    showAlert(`Error: ${data.msg}`, "error");
                    break;
                default:
                    // ignore other types
                    break;
            }
        };

        ws.onclose = () => {
            console.log("🔴 Disconnected... reconnecting in 3s");
            if (reconnectTimer) clearTimeout(reconnectTimer);
            reconnectTimer = setTimeout(connectWS, 3000);
        };

        ws.onerror = (err) => {
            console.error("❌ WS Error:", err);
            ws.close();
        };
    }

    // ================= ACTIONS =================
    function startSell(houseUid, amount) {
        if (!ws || ws.readyState !== WebSocket.OPEN) {
            showAlert("WebSocket not connected. Please refresh.", "error");
            return;
        }
        ws.send(JSON.stringify({
            type: "START_SELL",
            house_uid: houseUid,
            amount_kwh: amount
        }));
    }

    // ================= EVENT LISTENERS =================
    document.getElementById("sellBtn").addEventListener("click", () => {
        const amount = parseFloat(document.querySelector("[name='amount_kwh']").value);
        if (!amount || amount <= 0) {
            showAlert("Enter a valid amount", "error");
            return;
        }
        startSell(houseUid, amount);
    });

    // ================= START =================
    connectWS();
</script>
</body>
</html>