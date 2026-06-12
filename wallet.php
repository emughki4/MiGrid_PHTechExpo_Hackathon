<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sidebar.php';
requireLogin();

$user_id = $_SESSION['user_id'] ?? 1;
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Emughkuipu Idema');
$user_role = strtoupper($_SESSION['user_role'] ?? 'USER');

// Get wallet balance
$stmt = $pdo->prepare("SELECT balance FROM wallet WHERE user_id = ?");
$stmt->execute([$user_id]);
$wallet = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$wallet) {
    $pdo->prepare("INSERT INTO wallet (user_id, balance) VALUES (?, 0.00)")->execute([$user_id]);
    $balance = 0.00;
} else {
    $balance = floatval($wallet['balance']);
}

// Handle actions (fallback for non‑WebSocket)
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['fund_wallet'])) {
        $amount = floatval($_POST['amount']);
        if ($amount <= 0) {
            $error = "Amount must be positive.";
        } else {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE wallet SET balance = balance + ? WHERE user_id = ?");
                $stmt->execute([$amount, $user_id]);
                $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description, status) VALUES (?, 'deposit', ?, 'Wallet funding', 'completed')");
                $stmt->execute([$user_id, $amount]);
                $pdo->commit();
                $balance += $amount;
                $message = "Successfully added $" . number_format($amount, 2) . " to your wallet.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Transaction failed: " . $e->getMessage();
            }
        }
    }

    if (isset($_POST['withdraw'])) {
        $amount = floatval($_POST['amount']);
        if ($amount <= 0) {
            $error = "Amount must be positive.";
        } elseif ($amount > $balance) {
            $error = "Insufficient balance. Your balance is $" . number_format($balance, 2);
        } else {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE wallet SET balance = balance - ? WHERE user_id = ?");
                $stmt->execute([$amount, $user_id]);
                $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description, status) VALUES (?, 'withdrawal', ?, 'Withdrawal request', 'pending')");
                $stmt->execute([$user_id, $amount]);
                $pdo->commit();
                $balance -= $amount;
                $message = "Withdrawal of $" . number_format($amount, 2) . " initiated. It will be processed within 2 business days.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Withdrawal failed: " . $e->getMessage();
            }
        }
    }
}

// Fetch recent wallet transactions
$stmt = $pdo->prepare("
    SELECT type, amount, status, description, created_at 
    FROM wallet_transactions 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wallet | Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* (same CSS as before – unchanged) */
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
        @media (max-width: 768px) { .main-content { margin-left:0; padding:1rem; } }
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
        .balance {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--accent);
        }
        .balance-unit {
            font-size: 1rem;
            color: var(--text-muted);
        }
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.2rem;
        }
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
        .btn {
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
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
        .btn-outline:hover {
            border-color: var(--accent);
            background: rgba(16,185,129,0.1);
        }
        .transaction-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }
        .transaction-table td {
            padding: 0.6rem 0;
            border-bottom: 1px solid rgba(16,185,129,0.1);
        }
        .transaction-table tr:last-child td { border-bottom: none; }
        .badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .badge-deposit { background: #10b98120; color: #10b981; }
        .badge-withdrawal { background: #f59e0b20; color: #f59e0b; }
        .badge-payment { background: #3b82f620; color: #3b82f6; }
        .badge-pending { background: #6b728020; color: #9ca3af; }
        .badge-completed { background: #10b98120; color: #10b981; }
        .session-info {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .message, .error {
            padding: 0.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.75rem;
        }
        .message { background: rgba(16,185,129,0.2); border-left: 3px solid var(--accent); color: #a7f3d0; }
        .error { background: rgba(239,68,68,0.2); border-left: 3px solid #ef4444; color: #fecaca; }
        @media (max-width: 640px) { .grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="main-content">
    <div class="card">
        <div class="session-info">
            <div><strong>👤 <?= $user_name ?></strong></div>
            <div>📍 Node: <span id="nodeName"><?= htmlspecialchars($_SESSION['node_name'] ?? 'Node_C') ?></span></div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Balance Card with Buy Units button -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
            <div class="card-title" style="margin-bottom: 0;">Wallet Balance</div>
            <a href="add_units.php" class="btn btn-outline" style="padding: 0.25rem 0.8rem; font-size: 0.7rem;">➕ Buy Units</a>
        </div>
        <div class="balance">$<span id="walletBalance"><?= number_format($balance, 2) ?></span></div>
        <div class="balance-unit">USD</div>
    </div>

    <!-- Fund Wallet Card -->
    <div class="card">
        <div class="card-title">➕ Fund Wallet</div>
        <div class="input-group">
            <input type="number" step="0.01" id="fundAmount" placeholder="Amount (USD)">
            <button id="fundBtn" class="btn btn-primary">Add Funds</button>
        </div>
        <div style="font-size:0.7rem; color:var(--text-muted); margin-top:0.5rem;">
            * Demo: Funds are added instantly.
        </div>
    </div>

    <!-- Withdraw Card -->
    <div class="card">
        <div class="card-title">💸 Withdraw Funds</div>
        <div class="input-group">
            <input type="number" step="0.01" id="withdrawAmount" placeholder="Amount (USD)">
            <button id="withdrawBtn" class="btn btn-danger">Withdraw</button>
        </div>
        <div style="font-size:0.7rem; color:var(--text-muted); margin-top:0.5rem;">
            * Withdrawals are processed within 2 business days.
        </div>
    </div>

    <!-- Transaction History -->
    <div class="card">
        <div class="card-title">📜 Recent Wallet Activity</div>
        <?php if (empty($transactions)): ?>
            <div style="text-align:center; padding:1rem; color:var(--text-muted);">No transactions yet.</div>
        <?php else: ?>
            <table class="transaction-table">
                <?php foreach ($transactions as $tx): ?>
                <tr>
                    <td><span class="badge badge-<?= $tx['type'] ?>"><?= strtoupper($tx['type']) ?></span></td>
                    <td><?= htmlspecialchars($tx['description']) ?></td>
                    <td>$<?= number_format($tx['amount'], 2) ?></td>
                    <td style="text-align:right;"><span class="badge badge-<?= $tx['status'] ?>"><?= strtoupper($tx['status']) ?></span></td>
                    <td style="text-align:right; white-space:nowrap;"><?= date('M d, H:i', strtotime($tx['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
const ws = new WebSocket("ws://" + window.location.hostname + ":3000");

ws.onopen = () => {
    ws.send(JSON.stringify({ type: "AUTH", user_id: <?= $user_id ?> }));
};

document.getElementById("fundBtn").onclick = () => {
    const amount = parseFloat(document.getElementById("fundAmount").value);
    if (!amount || amount <= 0) {
        showAlert("Enter valid amount", "warning");
        return;
    }
    ws.send(JSON.stringify({ type: "FUND_WALLET", amount: amount }));
};

document.getElementById("withdrawBtn").onclick = () => {
    const amount = parseFloat(document.getElementById("withdrawAmount").value);
    if (!amount || amount <= 0) {
        showAlert("Enter valid amount", "warning");
        return;
    }
    ws.send(JSON.stringify({ type: "WITHDRAW_WALLET", amount: amount }));
};

ws.onmessage = (event) => {
    const data = JSON.parse(event.data);
    console.log("WS:", data);
    if (data.type === "WALLET_UPDATED") {
        document.getElementById("walletBalance").innerText = parseFloat(data.balance).toFixed(2);
        showAlert("✅ Wallet updated");
        document.getElementById("fundAmount").value = "";
        document.getElementById("withdrawAmount").value = "";
    }
    if (data.type === "FUND_FAILED") showAlert("Funding failed", "error");
    if (data.type === "WITHDRAW_FAILED") {
        if (data.reason === "INSUFFICIENT_FUNDS") showAlert("Not enough balance", "warning");
        else showAlert("Withdraw failed", "error");
    }
};

function showAlert(msg, type="success") {
    const el = document.createElement("div");
    el.innerText = msg;
    el.style.position = "fixed";
    el.style.top = "20px";
    el.style.right = "20px";
    el.style.padding = "10px 15px";
    el.style.background = type === "error" ? "#dc2626" : "#059669";
    el.style.color = "#fff";
    el.style.borderRadius = "6px";
    el.style.zIndex = 9999;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
}
</script>
</body>
</html>