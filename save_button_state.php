<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sidebar.php';
requireLogin();

$user_id = $_SESSION['user_id'] ?? 1;
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Emughkuipu Idema');

// -----------------------------------------------------------------
// 1. Master button state (system power) – from button_state.txt
// -----------------------------------------------------------------
$buttonStateFile = __DIR__ . '/data/button_state.txt';
if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0755, true);
$buttonState = 'off';
if (file_exists($buttonStateFile)) {
    $content = trim(file_get_contents($buttonStateFile));
    if (in_array($content, ['on', 'off'])) $buttonState = $content;
}

// -----------------------------------------------------------------
// 2. Node status (online/offline/fault) – from node_status.txt
// -----------------------------------------------------------------
$nodeStatusFile = __DIR__ . '/data/node_status.txt';
$nodeStatus = 'online'; // default
if (file_exists($nodeStatusFile)) {
    $content = trim(file_get_contents($nodeStatusFile));
    if (in_array($content, ['online', 'offline', 'fault'])) $nodeStatus = $content;
}

// -----------------------------------------------------------------
// 3. Energy threshold – still in DB (users table)
// -----------------------------------------------------------------
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN energy_threshold_kwh DECIMAL(10,2) DEFAULT 5.00");
} catch (PDOException $e) { /* ignore */ }

$stmt = $pdo->prepare("SELECT energy_threshold_kwh FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_threshold = $stmt->fetchColumn();
if ($current_threshold === false) $current_threshold = 5.00;

// -----------------------------------------------------------------
// Handle POST requests
// -----------------------------------------------------------------
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update node status (from dropdown)
    if (isset($_POST['update_node_status'])) {
        $newStatus = $_POST['node_status'];
        if (in_array($newStatus, ['online', 'offline', 'fault'])) {
            if (file_put_contents($nodeStatusFile, $newStatus) !== false) {
                $message = "Node status updated to " . strtoupper($newStatus) . ".";
                $nodeStatus = $newStatus;
            } else {
                $error = "Failed to write node status file.";
            }
        } else {
            $error = "Invalid status value.";
        }
    }
    
    // Update energy threshold
    if (isset($_POST['update_threshold'])) {
        $threshold = floatval($_POST['energy_threshold']);
        if ($threshold >= 0) {
            $stmt = $pdo->prepare("UPDATE users SET energy_threshold_kwh = ? WHERE id = ?");
            if ($stmt->execute([$threshold, $user_id])) {
                $message = "Energy threshold saved.";
                $current_threshold = $threshold;
            } else {
                $error = "Could not save threshold.";
            }
        } else {
            $error = "Threshold must be a positive number.";
        }
    }
}

// Helper to get node info (for display only – we don't use DB status anymore)
// We still need node name and location from DB (they don't change often)
$stmt = $pdo->prepare("
    SELECT n.node_name, n.location 
    FROM house_node_map hnm
    JOIN nodes n ON hnm.node_id = n.id
    WHERE hnm.house_id = (SELECT id FROM houses WHERE user_id = ? LIMIT 1)
    LIMIT 1
");
$stmt->execute([$user_id]);
$node = $stmt->fetch(PDO::FETCH_ASSOC);
$node_name = $node ? htmlspecialchars($node['node_name']) : 'Node_C';
$node_loc = $node ? htmlspecialchars($node['location']) : 'Eleme';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control Panel | Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Same base styles as before – keep all */
        :root {
            --vs-bg: #020c16;
            --vs-accent: #10b981;
            --vs-accent-dark: rgba(4,30,33,0.6);
            --vs-txt-mid: #8ba8b5;
            --vs-border-col: rgba(16,185,129,0.3);
            --space-xs: 0.3rem;
            --space-sm: 0.5rem;
            --space-md: 0.4rem;
            --space-lg: 1.2rem;
            --space-xl: 0.7rem;
            --font-base: 0.75rem;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--vs-bg);
            font-size: var(--font-base);
        }
        .main-content {
            margin-left: 260px;
            padding: var(--space-xl);
        }
        @media (max-width:768px) {
            .main-content { margin-left:0; padding:var(--space-lg); }
        }
        .session-bar {
            background: var(--vs-accent-dark);
            border-radius: 1rem;
            padding: var(--space-md) var(--space-xl);
            margin-bottom: var(--space-xl);
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: var(--space-md);
            border: 1px solid var(--vs-border-col);
        }
        .user-name {
            font-weight: 700;
            font-size: 0.85rem;
            background: var(--vs-accent);
            padding: 0.15rem 0.7rem;
            border-radius: 40px;
            color: #0f172a;
        }
        .node-details {
            font-size: 0.75rem;
            color: #e8ecf1;
        }
        .node-details span { color: var(--vs-accent); }
        
        /* ========== TECH TOGGLE BUTTON (unchanged) ========== */
        .toggle-container {
            display: flex;
            justify-content: center;
            margin-bottom: var(--space-xl);
            position: relative;
        }
        .toggle-frame {
            position: relative;
            padding: 6px;
            background: linear-gradient(135deg, rgba(16,185,129,0.5), rgba(16,185,129,0.1));
            border-radius: 50%;
            box-shadow: 0 0 15px rgba(16,185,129,0.3);
        }
        .toggle-btn {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background-color: #ef4444;
            color: white;
            font-size: 1.8rem;
            font-weight: 800;
            border: none;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.2), 0 8px 20px rgba(0,0,0,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            text-transform: uppercase;
            letter-spacing: 2px;
            position: relative;
            z-index: 2;
        }
        .toggle-btn::before {
            content: '';
            position: absolute;
            top: -3px;
            left: -3px;
            right: -3px;
            bottom: -3px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 70%);
            pointer-events: none;
        }
        .toggle-btn.on {
            background-color: var(--vs-accent);
            box-shadow: inset 0 1px 4px rgba(0,0,0,0.2), 0 0 20px rgba(16,185,129,0.7);
        }
        .toggle-btn.off {
            background-color: #ef4444;
            box-shadow: inset 0 1px 4px rgba(0,0,0,0.2), 0 0 12px rgba(239,68,68,0.5);
        }
        .toggle-btn.pulse {
            animation: techPulse 0.4s ease-out;
        }
        @keyframes techPulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.08); opacity: 0.9; box-shadow: 0 0 30px rgba(16,185,129,0.8); }
            100% { transform: scale(1); opacity: 1; }
        }
        .toggle-btn:hover {
            transform: scale(1.02);
            filter: brightness(1.05);
        }
        .toggle-container::before,
        .toggle-container::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 40px;
            height: 2px;
            background: linear-gradient(90deg, var(--vs-accent), transparent);
            transform: translateY(-50%);
        }
        .toggle-container::before {
            left: calc(50% - 140px);
        }
        .toggle-container::after {
            right: calc(50% - 140px);
            background: linear-gradient(270deg, var(--vs-accent), transparent);
        }
        @media (max-width: 640px) {
            .toggle-container::before,
            .toggle-container::after { width: 20px; left: calc(50% - 90px); right: calc(50% - 90px); }
            .toggle-btn { width: 90px; height: 90px; font-size: 1.4rem; }
            .toggle-frame { padding: 4px; }
        }
        
        /* ========== CARDS (redesigned) ========== */
        .cards-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-lg);
            margin-bottom: var(--space-xl);
        }
        .card {
            background: linear-gradient(135deg, var(--vs-accent-dark), rgba(1,11,12,0.6));
            border-radius: 1rem;
            padding: var(--space-md);
            border: 1px solid var(--vs-border-col);
            backdrop-filter: blur(4px);
        }
        .card-title {
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: var(--space-sm);
            color: var(--vs-accent);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #6b7280;
            box-shadow: 0 0 4px currentColor;
        }
        .status-indicator.online { background-color: #10b981; box-shadow: 0 0 6px #10b981; }
        .status-indicator.offline { background-color: #6b7280; }
        .status-indicator.fault { background-color: #ef4444; box-shadow: 0 0 6px #ef4444; }
        
        .control-group {
            margin-bottom: var(--space-md);
        }
        label {
            display: block;
            font-size: 0.7rem;
            color: var(--vs-txt-mid);
            margin-bottom: var(--space-xs);
        }
        select, input {
            background: rgba(0,0,0,0.5);
            border: 1px solid var(--vs-border-col);
            border-radius: 0.5rem;
            padding: 0.4rem 0.6rem;
            color: white;
            font-size: 0.75rem;
            width: 100%;
            max-width: 250px;
        }
        button:not(.toggle-btn) {
            background: var(--vs-accent);
            border: none;
            color: #0f172a;
            font-weight: 600;
            padding: 0.4rem 1rem;
            border-radius: 2rem;
            cursor: pointer;
            font-size: 0.7rem;
            margin-top: var(--space-xs);
            transition: 0.2s;
        }
        button:not(.toggle-btn):hover { background: #0e9f6e; }
        
        .alert-item {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-xs) 0;
            border-bottom: 1px solid rgba(16,185,129,0.1);
            font-size: 0.7rem;
        }
        .alert-icon {
            font-size: 1rem;
        }
        .alert-text {
            color: #e8ecf1;
            flex: 1;
        }
        .alert-time {
            color: var(--vs-txt-mid);
            font-size: 0.6rem;
        }
        
        .message, .error {
            padding: var(--space-xs) var(--space-sm);
            border-radius: 0.5rem;
            margin-bottom: var(--space-md);
            font-size: 0.7rem;
        }
        .message { background: rgba(16,185,129,0.2); border-left: 3px solid var(--vs-accent); color: #a7f3d0; }
        .error { background: rgba(239,68,68,0.2); border-left: 3px solid #ef4444; color: #fecaca; }
        
        @media (max-width: 640px) {
            .cards-grid { grid-template-columns: 1fr; gap: var(--space-md); }
            select, input { max-width: 100%; }
        }
    </style>
</head>
<body>
<div class="main-content">
    <div class="session-bar">
        <div class="user-info">
            <div class="user-name">👤 <?= $user_name ?></div>
        </div>
        <div class="node-details">
            <div>📍 Node: <span><?= $node_name ?></span> (<?= $node_loc ?>)</div>
        </div>
    </div>

    <!-- Master toggle button (system power) -->
    <div class="toggle-container">
        <div class="toggle-frame">
            <button class="toggle-btn <?= $buttonState === 'on' ? 'on' : 'off' ?>" id="masterToggleBtn">
                <?= strtoupper($buttonState) ?>
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Two cards side by side -->
    <div class="cards-grid">
        <!-- Card 1: Node Control (redesigned) -->
        <div class="card">
            <div class="card-title">
                <span class="status-indicator <?= $nodeStatus ?>"></span>
                🔌 Node Control
            </div>
            <div class="control-group">
                <label>Current node status:</label>
                <div style="margin: 0.2rem 0 0.6rem 0;">
                    <span class="status-badge" style="background: <?= 
                        $nodeStatus == 'online' ? '#10b981' : ($nodeStatus == 'offline' ? '#6b7280' : '#ef4444') 
                    ?>; padding: 0.2rem 0.8rem; border-radius: 2rem; font-size:0.7rem; color:white;">
                        <?= strtoupper($nodeStatus) ?>
                    </span>
                </div>
            </div>
            <form method="post">
                <div class="control-group">
                    <label>Change node status:</label>
                    <select name="node_status">
                        <option value="online" <?= $nodeStatus == 'online' ? 'selected' : '' ?>>Online</option>
                        <option value="offline" <?= $nodeStatus == 'offline' ? 'selected' : '' ?>>Offline</option>
                        <option value="fault" <?= $nodeStatus == 'fault' ? 'selected' : '' ?>>Fault</option>
                    </select>
                </div>
                <button type="submit" name="update_node_status">Apply</button>
            </form>
        </div>

        <!-- Card 2: System Alerts (new) -->
        <div class="card">
            <div class="card-title">
                ⚠️ System Alerts
            </div>
            <?php
            // Sample alerts – can be later replaced with real data from DB or file
            $alerts = [
                ['icon' => '🔋', 'text' => 'Energy balance below 5 kWh', 'time' => '2 min ago'],
                ['icon' => '📡', 'text' => 'Node connection unstable', 'time' => '1 hour ago'],
                ['icon' => '⚙️', 'text' => 'Firmware update available', 'time' => '1 day ago'],
            ];
            foreach ($alerts as $alert): ?>
                <div class="alert-item">
                    <div class="alert-icon"><?= $alert['icon'] ?></div>
                    <div class="alert-text"><?= htmlspecialchars($alert['text']) ?></div>
                    <div class="alert-time"><?= $alert['time'] ?></div>
                </div>
            <?php endforeach; ?>
            <div style="margin-top: var(--space-sm); text-align: right;">
                <a href="#" style="color: var(--vs-accent); font-size: 0.65rem; text-decoration: none;">View all →</a>
            </div>
        </div>
    </div>

    <!-- Energy Threshold Card (remains as is, but can be moved to the grid if you wish – I'll keep it below) -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-title">⚡ Low Energy Alert</div>
        <form method="post">
            <div class="control-group">
                <label>Notify when energy balance falls below (kWh):</label>
                <input type="number" step="0.5" name="energy_threshold" value="<?= htmlspecialchars($current_threshold) ?>" required>
            </div>
            <button type="submit" name="update_threshold">Save Threshold</button>
        </form>
    </div>
</div>

<script>
    // Master toggle button logic (saves state to button_state.txt via AJAX)
    const toggleBtn = document.getElementById('masterToggleBtn');
    let currentButtonState = '<?= $buttonState ?>'; // from PHP

    function setButtonState(state) {
        currentButtonState = state;
        if (state === 'on') {
            toggleBtn.classList.remove('off');
            toggleBtn.classList.add('on');
            toggleBtn.textContent = 'ON';
        } else {
            toggleBtn.classList.remove('on');
            toggleBtn.classList.add('off');
            toggleBtn.textContent = 'OFF';
        }
        // Pulse animation
        toggleBtn.classList.add('pulse');
        setTimeout(() => toggleBtn.classList.remove('pulse'), 400);

        // Save to server
        fetch('save_button_state.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ state: state })
        }).catch(err => console.error('AJAX error:', err));
    }

    toggleBtn.addEventListener('click', () => {
        const newState = currentButtonState === 'off' ? 'on' : 'off';
        setButtonState(newState);
    });
</script>
</body>
</html>