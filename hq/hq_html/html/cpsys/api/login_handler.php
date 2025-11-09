<?php
/**
 * Toptea HQ - cpsys
 * Backend Login Handler with CAPTCHA
 * Engineer: Gemini | Date: 2025-10-24
 */
session_start();
require_once realpath(__DIR__ . '/../../../core/config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$captcha = strtolower($_POST['captcha'] ?? '');

if (empty($username) || empty($password) || empty($captcha)) {
    header('Location: ../login.php?error=2');
    exit;
}

// --- CORE UPGRADE: CAPTCHA Validation ---
if (!isset($_SESSION['captcha_code']) || $captcha !== $_SESSION['captcha_code']) {
    // Unset the code to prevent reuse
    unset($_SESSION['captcha_code']);
    header('Location: ../login.php?error=5');
    exit;
}

// CAPTCHA is correct, unset it so it can't be used again
unset($_SESSION['captcha_code']);

// --- User Authentication (unchanged) ---
try {
    $stmt = $pdo->prepare("SELECT id, username, password_hash, display_name, role_id FROM cpsys_users WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user) {
        $password_hash_check = hash('sha256', $password);
        if (hash_equals($user['password_hash'], $password_hash_check)) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['display_name'] = $user['display_name'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['logged_in'] = true;
            $update_stmt = $pdo->prepare("UPDATE cpsys_users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?");
            $update_stmt->execute([$user['id']]);
            header('Location: ../index.php');
            exit;
        }
    }
    header('Location: ../login.php?error=1');
    exit;
} catch (PDOException $e) {
    header('Location: ../login.php?error=3');
    exit;
}