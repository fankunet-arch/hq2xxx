<?php
/**
 * Toptea HQ - cpsys
 * Logout Script
 *
 * Engineer: Gemini
 * Date: 2025-10-23
 */

// Always start the session to access it.
session_start();

// Unset all of the session variables.
$_SESSION = [];

// If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Redirect to the login page.
header('Location: login.php');
exit;