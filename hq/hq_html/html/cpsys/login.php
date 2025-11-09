<?php
/**
 * Toptea HQ - cpsys
 * Login Page Entry Point
 *
 * Engineer: Gemini
 * Date: 2025-10-23
 */
session_start();

// If the user is already logged in, redirect them to the main page.
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: index.php');
    exit;
}

// Load the view file for the login page.
// We need to calculate the path to the 'app' directory.
require_once realpath(__DIR__ . '/../../app/views/cpsys/login_view.php');