<?php
/**
 * Toptea HQ - cpsys
 * Core Configuration File (Final Production Version)
 * Engineer: Gemini | Date: 2025-10-29 | Revision: 1.2.001 (Log Path Fix)
 *
 * [A1 UTC SYNC]: Added $pdo->exec("SET time_zone='+00:00'")
 */

// --- PHP Environment Setup ---
ini_set('display_errors', '0'); // Turn off displaying errors
ini_set('display_startup_errors', '0'); // Turn off displaying startup errors
ini_set('log_errors', '1'); // Enable logging errors

// [GEMINI V5.0 FATAL FIX]
// 诊断报告 (env_check.php) 确认 '/web_toptea/logs' 目录不存在，导致500错误。
// 更改为相对路径，将日志写入此文件所在的 'core' 目录中。
ini_set('error_log', __DIR__ . '/php_errors_hq.log');
// [END GEMINI FIX]

error_reporting(E_ALL); // Report all errors
mb_internal_encoding('UTF-8');

// --- Database Configuration ---
$db_host = 'mhdlmskv3gjbpqv3.mysql.db';
$db_name = 'mhdlmskv3gjbpqv3';
$db_user = 'mhdlmskv3gjbpqv3';
$db_pass = 'zqVdVfAWYYaa4gTAuHWX7CngpRDqR';
$db_char = 'utf8mb4';

// --- Application Settings ---
define('BASE_URL', 'http://hq.toptea.es/cpsys/');

// --- Directory Paths ---
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('CORE_PATH', ROOT_PATH . '/core');
define('PUBLIC_PATH', ROOT_PATH . '/html');

// --- Database Connection (PDO) ---
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_char";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    
    // [A1 UTC SYNC] 阶段 A1：设置数据库连接时区为 UTC
    // 
    // **方案最终确认：**
    // 必须保留此行。
    // 1. `DEFAULT (UTC_TIMESTAMP(6))` (用于 created_at) *不* 依赖此设置。
    // 2. `ON UPDATE CURRENT_TIMESTAMP(6)` (用于 updated_at) *依赖* 此设置。
    // 此行可确保 "安全网" 的两部分都正确写入 UTC。
    $pdo->exec("SET time_zone='+00:00'");
    
} catch (\PDOException $e) {
    // Log the error
    error_log("HQ Database connection failed: " . $e->getMessage());
    // Optionally throw exception or display generic error page for HQ
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

?>