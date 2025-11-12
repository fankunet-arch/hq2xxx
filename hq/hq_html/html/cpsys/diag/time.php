<?php
/**
 * Toptea HQ - CPSYS 平台
 * UTC 时间诊断页面 (A1 UTC SYNC)
 *
 * [A2 UTC SYNC] Phase A1: New diagnostics file.
 * [A2.1 FIX]: Corrected realpath() include paths from ../../../../ (4 levels) to ../../../ (3 levels).
 *
 * 检查 PHP、数据库和票据时间是否正确统一到 UTC。
 */

// 1. 加载核心依赖
// (必须使用相对路径，因为这是独立入口)
try {
    // [A2.1 FIX] 路径从 /../../../../ (4级) 修正为 /../../../ (3级)
    require_once realpath(__DIR__ . '/../../../core/auth_core.php');
    require_once realpath(__DIR__ . '/../../../core/config.php'); // $pdo 在此
    require_once realpath(__DIR__ . '/../../../app/helpers/auth_helper.php'); // ROLE_* 常量
    require_once realpath(__DIR__ . '/../../../app/helpers/datetime_helper.php'); // fmt_local()
} catch (Throwable $e) {
    http_response_code(500);
    die("Failed to load core files: " . $e->getMessage());
}

// 2. 权限检查 (仅限超级管理员)
if (($_SESSION['role_id'] ?? null) !== ROLE_SUPER_ADMIN) {
    header('HTTP/1.1 403 Forbidden');
    die('Access Denied. Requires Super Admin role.');
}

// 3. 执行检查
$php_utc_time = utc_now();
$php_madrid_time = fmt_local($php_utc_time, 'Y-m-d H:i:s.u P', APP_DEFAULT_TIMEZONE);

$db_status = 'OK';
$db_utc_time = null;
$db_tz = null;

try {
    $db_row = $pdo->query("SELECT NOW(6) AS db_utc, @@session.time_zone AS db_tz")->fetch(PDO::FETCH_ASSOC);
    $db_utc_time = $db_row['db_utc'];
    $db_tz = $db_row['db_tz'];
    
    if ($db_tz !== '+00:00') {
        $db_status = 'FAIL: DB time_zone is not +00:00';
    }

} catch (Exception $e) {
    $db_status = 'FAIL: ' . $e->getMessage();
}

$invoices = [];
try {
    $invoices = $pdo->query("SELECT id, issued_at FROM pos_invoices ORDER BY id DESC LIMIT 3")
                    ->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // 可能是表不存在，忽略
}

header('Content-Type: text/plain; charset=utf-8');
?>
Toptea HQ - UTC Time Diagnostics (A1)
=======================================

--- [A1.1] PHP/App Environment ---
PHP Default Timezone: <?php echo date_default_timezone_get(); ?> (Should be UTC or irrelevant if app manages it)
App Default Timezone: <?php echo APP_DEFAULT_TIMEZONE; ?> (Must be Europe/Madrid)

PHP UTC Now (utc_now()): <?php echo $php_utc_time->format('Y-m-d H:i:s.u P'); ?>

PHP Madrid Time (fmt_local): <?php echo $php_madrid_time; ?>


--- [A1.2] Database Connection (SET time_zone='+00:00') ---
DB Connection Status: <?php echo $db_status; ?>

DB Session Timezone (@@session.time_zone): <?php echo $db_tz; ?> (Must be +00:00)

DB UTC Time (NOW(6)): <?php echo $db_utc_time; ?>


--- [A1.3] Sample Data Check (pos_invoices) ---
Checking last 3 invoices

<?php if (empty($invoices)): ?>
(No invoices found or table missing)
<?php else: ?>
<?php foreach ($invoices as $inv): ?>
[Invoice #<?php echo $inv['id']; ?>]
  DB UTC Time (issued_at): <?php echo $inv['issued_at']; ?>

  Formatted Local (Madrid): <?php echo fmt_local($inv['issued_at'], 'Y-m-d H:i:s (P)', APP_DEFAULT_TIMEZONE); ?>

<?php endforeach; ?>
<?php endif; ?>

=======================================
Self-Check [A1]:
1. PHP UTC and DB UTC should be almost identical (ms difference).
2. [cite_start]DB Session Timezone MUST be "+00:00". [cite: 150]
3. [cite_start]Formatted Local (Madrid) time must match your local time in Madrid. [cite: 151]
4. [cite_start]Invoice times must show UTC in DB and correct Madrid time when formatted. [cite: 151]