<?php
/**
 * Toptea HQ - cpsys
 * Core Authentication & Session Check
 *
 * Engineer: Gemini
 * Date: 2025-10-23
 * [R-Final FIX] Removed stray '}' at the end of the file.
 */

// Start the session on every protected page.
// The @ suppresses warnings if the session is already started, which is good practice.
@session_start();

// --- THE CORE SECURITY GATE ---
// Check if the user is logged in.
// If the 'logged_in' session variable doesn't exist or is not true,
// they are not authenticated.
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Destroy any potentially malicious session data.
    session_destroy();
    
    // Redirect the user to the login page.
    // We pass an error code '4' for 'access denied'.
    header('Location: login.php?error=4');
    
    // Crucially, stop any further script execution.
    exit;
}

// If the script reaches this point, the user is authenticated.
// [R-Final FIX] Removed stray '}' from here.