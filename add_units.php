<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sidebar.php';
requireLogin();

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) redirect('login.php');

// 1. Get user's house and node
$stmt = $pdo->prepare("
    SELECT h.id AS house_id, h.house_uid, h.name AS house_name,
           n.id AS node_id, n.node_uid, n.node_name
    FROM houses h
    LEFT JOIN house_node_map hnm ON hnm.house_id = h.id
    LEFT JOIN nodes n ON n.id = hnm.node_id
    WHERE h.user_id = ?
    LIMIT 1
");
$stmt->execute([$user_id]);
$houseData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$houseData || !$houseData['house_id']) {
    die("No house found. Please create a house first.");
}

$house_id = $houseData['house_id'];
$house_name = htmlspecialchars($houseData['house_name']);
$node_id = $houseData['node_id'];
$node_name = htmlspecialchars($houseData['node_name'] ?? 'Not paired');

// 2. Get wallet balance
$stmt = $pdo->prepare("SELECT balance FROM wallet WHERE user_id = ?");
$stmt->execute([$user_id]);
$wallet = $stmt->fetch(PDO::FETCH_ASSOC);
$balance = $wallet ? (float) $wallet['balance'] : 0.00;

// 3. Get buying price from price_settings
$stmt = $pdo->query("SELECT buying_price, unit FROM price_settings LIMIT 1");
$priceRow = $stmt->fetch(PDO::FETCH_ASSOC);
$buying_price = $priceRow ? (float) $priceRow['buying_price'] : 0.10;
$currency_unit = $priceRow['unit'] ?? '₦';

$message = '';
$error   = '';

// 4. Handle purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_units'])) {
    $kwh = (float) ($_POST['kwh'] ?? 0);
    if ($kwh <= 0) {
        $error = "Please enter a positive number of kWh.";
    } else {
        $total_cost = $kwh * $buying_price;
        if ($total_cost > $balance) {
            $error = "Insufficient wallet balance. Your balance is {$currency_unit}" . number_format($balance, 2);
        } else {
            try {
                $pdo->beginTransaction();

                // 4a. Deduct from wallet
                $stmt = $pdo->prepare("UPDATE wallet SET balance = balance - ? WHERE user_id = ?");
                $stmt->execute([$total_cost, $user_id]);

                // 4b. Record wallet transaction
                $stmt = $pdo->prepare("
                    INSERT INTO wallet_transactions (user_id, type, amount, description, status)
                    VALUES (?, 'payment', ?, ?, 'completed')
                ");
                $stmt->execute([$user_id, $total_cost, "Purchase of {$kwh} kWh"]);

                // 4c. Update energy_totals (if house has node)
                if ($node_id) {
                    $stmt = $pdo->prepare("
                        INSERT INTO energy_totals (house_id, node_id, total_energy)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE total_energy = total_energy + ?
                    ");
                    $stmt->execute([$house_id, $node_id, $kwh, $kwh]);
                } else {
                    // No node paired – store energy in a temporary table? Or just skip.
                    // For now, we'll just insert/update a placeholder node_id = 0? Better to require node.
                    // We'll abort if no node.
                    throw new Exception("No node paired. Please pair a node before buying units.");
                }

                // 4d. Record energy transaction
                $stmt = $pdo->prepare("
                    INSERT INTO transactions (house_id, type, amount_kwh, source)
                    VALUES (?, 'buy', ?, 'wallet')
                ");
                $stmt->execute([$house_id, $kwh]);

                $pdo->commit();

                // Update local balance
                $balance -= $total_cost;
                $message = "Successfully purchased {$kwh} kWh for {$currency_unit}" . number_format($total_cost, 2) . ". Energy added to your balance.";

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Purchase failed: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buy Energy Units | MiGrid</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
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
        }
        .main-content {
            margin-left: 260px;
            padding: 1.5rem;
            transition: margin-left 0.2s;
        }
        @media (max-width: 768px) {
            .main-content { margin-left:0; padding:1rem; }
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
            color: #8ba8b5;
            margin-bottom: 0.8rem;
        }
        .balance {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent);
        }
        .form-group {
            margin-bottom: 1.2rem;
        }
        label {
            display: block;
            font-size: 0.8rem;
            color: #8ba8b5;
            margin-bottom: 0.4rem;
        }
        input {
            background: #011015;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            padding: 0.6rem 0.8rem;
            color: white;
            font-size: 0.9rem;
            width: 100%;
        }
        input:focus {
            outline: none;
            border-color: var(--accent);
        }
        .btn {
            border: none;
            padding: 0.7rem 1.2rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
        }
        .btn-primary {
            background: var(--accent);
            color: #020c16;
        }
        .btn-primary:hover {
            background: #0e9f6e;
            transform: translateY(-1px);
        }
        .calc-box {
            background: rgba(16,185,129,0.1);
            border-radius: 0.75rem;
            padding: 0.8rem;
            margin: 1rem 0;
        }
        .calc-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
        }
        .calc-total {
            border-top: 1px solid var(--border);
            padding-top: 0.5rem;
            margin-top: 0.5rem;
            font-weight: 600;
            color: var(--accent);
        }
        .message, .error {
            padding: 0.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.75rem;
        }
        .message { background: rgba(16,185,129,0.2); border-left: 3px solid var(--accent); color: #a7f3d0; }
        .error { background: rgba(239,68,68,0.2); border-left: 3px solid #ef4444; color: #fecaca; }
        .node-info {
            font-size: 0.8rem;
            color: #8ba8b5;
            margin-top: 0.5rem;
        }
        @media (max-width: 640px) {
            .main-content { padding: 1rem; }
        }
    </style>
</head>
<body>
<div class="main-content">
    <!-- Session info -->
    <div class="card">
        <div><strong>👤 <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></strong></div>
        <div class="node-info">🏠 House: <?= $house_name ?> | 📡 Node: <?= $node_name ?></div>
    </div>

    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Wallet Balance Card -->
    <div class="card">
        <div class="card-title">💰 Your Wallet Balance</div>
        <div class="balance"><?= $currency_unit ?><?= number_format($balance, 2) ?></div>
    </div>

    <!-- Buy Units Form -->
    <div class="card">
        <div class="card-title">⚡ Buy Energy Units</div>
        <div class="node-info">Current price: <strong><?= $currency_unit ?><?= number_format($buying_price, 4) ?></strong> per kWh</div>

        <form method="post" id="buyForm">
            <div class="form-group">
                <label for="kwh">Amount (kWh)</label>
                <input type="number" step="0.1" id="kwh" name="kwh" placeholder="e.g., 10" required>
            </div>

            <div class="calc-box" id="calcBox">
                <div class="calc-row">
                    <span>Price per kWh:</span>
                    <span><?= $currency_unit ?><?= number_format($buying_price, 4) ?></span>
                </div>
                <div class="calc-row">
                    <span>Quantity:</span>
                    <span id="qtyDisplay">0 kWh</span>
                </div>
                <div class="calc-row calc-total">
                    <span>Total cost:</span>
                    <span id="totalCost"><?= $currency_unit ?>0.00</span>
                </div>
            </div>

            <button type="submit" name="buy_units" class="btn btn-primary">Confirm Purchase</button>
        </form>
    </div>
</div>

<script>
    const price = <?= $buying_price ?>;
    const currency = "<?= $currency_unit ?>";
    const kwhInput = document.getElementById('kwh');
    const qtyDisplay = document.getElementById('qtyDisplay');
    const totalCostSpan = document.getElementById('totalCost');

    function updateCalculation() {
        let kwh = parseFloat(kwhInput.value) || 0;
        qtyDisplay.innerText = kwh.toFixed(2) + ' kWh';
        let total = kwh * price;
        totalCostSpan.innerText = currency + total.toFixed(2);
    }

    kwhInput.addEventListener('input', updateCalculation);
    updateCalculation();
</script>
</body>
</html>