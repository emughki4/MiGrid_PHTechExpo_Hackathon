<?php
require_once 'config.php';
include_once 'sidebar.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $house_name = trim($_POST['house_name'] ?? '');
    $location   = trim($_POST['location'] ?? '');

    if (empty($house_name)) {
        $error = "House name is required.";
    } elseif (empty($location)) {
        $error = "House location is required.";
    } else {

        try {
            $pdo->beginTransaction();

            // 1. Insert house (NOT verified yet)
            $stmt = $pdo->prepare("
                INSERT INTO houses (user_id, name, location, verified, mode)
                VALUES (?, ?, ?, 0, 'idle')
            ");
            $stmt->execute([$user_id, $house_name, $location]);

            $house_id = $pdo->lastInsertId();

            // 2. Generate UID
            $house_uid = "HOUSE_" . strtoupper(substr(md5($house_id . time()), 0, 8));

            $stmt = $pdo->prepare("
                UPDATE houses SET house_uid = ? WHERE id = ?
            ");
            $stmt->execute([$house_uid, $house_id]);

            $pdo->commit();

            $success = "House created successfully and is pending admin verification.";

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to create house.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create House</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-950 text-white flex items-center justify-center min-h-screen">

<div class="w-full max-w-md bg-gray-900 p-6 rounded-xl">

    <h2 class="text-xl font-bold mb-4">Create New House</h2>

    <?php if ($success): ?>
        <div class="bg-green-700 p-3 rounded mb-3"><?= $success ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-700 p-3 rounded mb-3"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">

        <label class="text-sm">House Name</label>
        <input name="house_name" class="w-full p-2 mb-3 bg-gray-800 rounded" required>

        <label class="text-sm">Location</label>
        <input name="location" class="w-full p-2 mb-3 bg-gray-800 rounded" required>

        <button class="w-full bg-emerald-600 p-2 rounded">
            Create House
        </button>

    </form>

</div>

</body>
</html>