<?php
/**
 * auth.php — Session-based authentication helper for Admin Panel
 * Include at the top of every protected admin page.
 */

$sessionDir = __DIR__ . '/sessions';
if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0777, true);
}
if (is_dir($sessionDir) && is_writable($sessionDir)) {
    @session_save_path($sessionDir);
}
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Admin credentials
if (!defined('ADMIN_USER')) {
    define('ADMIN_USER', 'admin');
}
if (!defined('ADMIN_PASS_HASH')) {
    define('ADMIN_PASS_HASH', password_hash('astro2025', PASSWORD_BCRYPT));
}

// Redirect to login if not authenticated
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// CSRF helper functions
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}
