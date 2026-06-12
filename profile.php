<?php
require_once __DIR__ . '/config.php';
requireLogin();
require_once __DIR__ . '/sidebar.php';

$user_id = $_SESSION['user_id'] ?? 0;
$user_name_disp = htmlspecialchars($_SESSION['user_name'] ?? 'User');

// Fetch current user data
$stmt = $pdo->prepare("SELECT full_name, email, phone, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    redirect('logout.php');
}

$message = '';
$error   = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');

        if (empty($full_name)) {
            $error = "Full name cannot be empty.";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
            if ($stmt->execute([$full_name, $phone, $user_id])) {
                $_SESSION['user_name'] = $full_name; // update session
                $message = "Profile updated successfully.";
                $user['full_name'] = $full_name;
                $user['phone'] = $phone;
            } else {
                $error = "Failed to update profile.";
            }
        }
    }

    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password     = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = "All password fields are required.";
        } elseif (strlen($new_password) < 8) {
            $error = "New password must be at least 8 characters.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } else {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_hash = $stmt->fetchColumn();
            if (password_verify($current_password, $user_hash)) {
                $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                if ($stmt->execute([$new_hash, $user_id])) {
                    $message = "Password changed successfully.";
                } else {
                    $error = "Failed to change password.";
                }
            } else {
                $error = "Current password is incorrect.";
            }
        }
    }

    // // Handle logout
    // if (isset($_POST['logout'])) {
    //     session_destroy();
    //     redirect('login.php');
    // }
}

// Get house and node info (optional)
$house_info = null;
$node_info = null;
$stmt = $pdo->prepare("
    SELECT h.name AS house_name, h.location AS house_location, n.node_name, n.location AS node_location
    FROM houses h
    LEFT JOIN house_node_map hnm ON hnm.house_id = h.id
    LEFT JOIN nodes n ON n.id = hnm.node_id
    WHERE h.user_id = ?
    LIMIT 1
");
$stmt->execute([$user_id]);
$info = $stmt->fetch(PDO::FETCH_ASSOC);
if ($info) {
    $house_info = $info;
    $node_info = $info;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | MiGrid</title>
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
            .main-content { margin-left: 0; padding: 1rem; }
        }
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .card-title {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        label {
            display: block;
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 0.3rem;
        }
        input {
            width: 100%;
            padding: 0.6rem 0.8rem;
            background: #011015;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            color: white;
            font-size: 0.9rem;
        }
        input:focus {
            outline: none;
            border-color: var(--accent);
        }
        .btn {
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 2rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary {
            background: var(--accent);
            color: #020c16;
        }
        .btn-primary:hover {
            background: #0e9f6e;
            transform: translateY(-1px);
        }
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .message, .error {
            padding: 0.7rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.8rem;
        }
        .message { background: rgba(16,185,129,0.2); border-left: 3px solid var(--accent); color: #a7f3d0; }
        .error { background: rgba(239,68,68,0.2); border-left: 3px solid #ef4444; color: #fecaca; }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(16,185,129,0.1);
        }
        .info-label {
            font-weight: 600;
            color: var(--text-muted);
        }
        .info-value {
            color: var(--accent);
        }
        hr {
            border-color: var(--border);
            margin: 1rem 0;
        }
        @media (max-width: 640px) {
            .main-content { padding: 1rem; }
        }
    </style>
</head>
<body>
<div class="main-content">
    <!-- Session Info Card -->
    <div class="card">
        <div class="card-title">👤 Account Overview</div>
        <div class="info-row">
            <span class="info-label">Full Name:</span>
            <span class="info-value"><?= htmlspecialchars($user['full_name']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Email:</span>
            <span class="info-value"><?= htmlspecialchars($user['email']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Phone:</span>
            <span class="info-value"><?= htmlspecialchars($user['phone'] ?? 'Not set') ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Role:</span>
            <span class="info-value"><?= ucfirst($user['role']) ?></span>
        </div>
    </div>

    <!-- House & Node Info (if exists) -->
    <?php if ($house_info && $house_info['house_name']): ?>
    <div class="card">
        <div class="card-title">🏠 Linked Property</div>
        <div class="info-row">
            <span class="info-label">House:</span>
            <span class="info-value"><?= htmlspecialchars($house_info['house_name']) ?> (<?= htmlspecialchars($house_info['house_location']) ?>)</span>
        </div>
        <?php if ($node_info && $node_info['node_name']): ?>
        <div class="info-row">
            <span class="info-label">Node:</span>
            <span class="info-value"><?= htmlspecialchars($node_info['node_name']) ?> (<?= htmlspecialchars($node_info['node_location']) ?>)</span>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Update Profile Form -->
    <div class="card">
        <div class="card-title">✏️ Edit Profile</div>
        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
            </div>
            <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
        </form>
    </div>

    <!-- Change Password Form -->
    <div class="card">
        <div class="card-title">🔒 Change Password</div>
        <form method="post">
            <div class="form-group">
                <label>Current Password</label>
                <input type="password" name="current_password" required>
            </div>
            <div class="form-group">
                <label>New Password (min 8 characters)</label>
                <input type="password" name="new_password" required>
            </div>
            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" required>
            </div>
            <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
        </form>
    </div>

    <!-- Logout Button -->
    <div class="card">
<a href="/powerstation2/logout.php" class="btn btn-danger" style="display:block; text-align:center; text-decoration:none;">
    🚪 Logout
</a>
    </div>
</div>
</body>
</html>