<?php
/**
 * config.php
 * Central database configuration and session bootstrap.
 * Include this file at the top of every backend PHP file.
 */

// ── Database credentials ──────────────────────────────────────────────────
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'powerstation');
define('DB_USER', 'root');       // change to your MySQL user
define('DB_PASS', '');           // change to your MySQL password
define('DB_PORT', 3306);

// ── Application base URL (no trailing slash) ──────────────────────────────
define('APP_URL', 'http://127.0.0.1/powerstation2');

// ── Start session if not already started ─────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── PDO connection (shared across all files) ──────────────────────────────
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // In production, log this instead of echoing
    die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
}

// ── Helper: redirect ──────────────────────────────────────────────────────
// function redirect(string $path): never {
//     header('Location: ' . APP_URL . '/' . ltrim($path, '/'));
//     exit;
// }

function redirect($path)
{
    header("Location: http://" . $_SERVER['HTTP_HOST'] . "/powerstation2/" . $path);
    exit();
}

// ── Helper: is user logged in? ────────────────────────────────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// ── Helper: require login ─────────────────────────────────────────────────
function requireLogin(): void {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}
