<?php
/**
 * Toptea HQ - CPSYS 统一 API 网关（安全加载 + 兜底）
 * Version: 1.6.6
 * Date: 2025-11-10
 */

declare(strict_types=1);

/* 1) 安全加载核心配置（提供 $pdo、APP_PATH、ROOT_PATH 等） */
$pdo = null;
try {
    $cfg_path = __DIR__ . '/../../../core/config.php';
    if (is_file($cfg_path)) {
        require_once $cfg_path;
    } else {
        // 最小兜底（无 config.php 也能跑 diag）
        if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__, 3));
        if (!defined('APP_PATH'))  define('APP_PATH', ROOT_PATH . '/app');
    }
} catch (Throwable $e) {
    // 任何配置阶段的异常都不让网关崩溃
    if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__, 3));
    if (!defined('APP_PATH'))  define('APP_PATH', ROOT_PATH . '/app');
    $pdo = null;
    error_log('[CPSYS API Gateway] Bootstrap recovered: ' . $e->getMessage());
}

/* 2) 加载 API 引擎 */
$engine_path = APP_PATH . '/core/api_core.php';
if (!is_file($engine_path)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status'  => 'error',
        'message' => 'API engine missing: ' . $engine_path,
        'data'    => null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
require_once $engine_path;

/* 3) 工具函数：检测“裸 ...”占位，避免加载半成品文件导致 500 */
function has_naked_ellipsis(string $php): bool {
    $code = preg_replace('#/\*.*?\*/#s', '', $php);
    $code = preg_replace('#//.*$#m', '', $code);
    $code = preg_replace("#'([^'\\\\]|\\\\.)*'#s", "''", $code);
    $code = preg_replace('#"([^"\\\\]|\\\\.)*"#s', '""', $code);
    return strpos($code, '...') !== false;
}
function safe_require_registry(string $path): array {
    if (!is_file($path)) { error_log("Registry not found: {$path}"); return []; }
    $txt = @file_get_contents($path);
    if ($txt === false)  { error_log("Registry unreadable: {$path}"); return []; }
    if (has_naked_ellipsis($txt)) {
        error_log("Registry skipped (contains naked '...'): {$path}");
        return [];
    }
    $ret = require $path;
    return is_array($ret) ? $ret : [];
}

/* 4) 安全加载各注册表 */
$reg_dir       = __DIR__ . '/registries';
$registry_base = safe_require_registry($reg_dir . '/cpsys_registry_base.php');
$registry_bms  = safe_require_registry($reg_dir . '/cpsys_registry_bms.php');
$registry_rms  = safe_require_registry($reg_dir . '/cpsys_registry_rms.php');
$registry_ext  = safe_require_registry($reg_dir . '/cpsys_registry_ext.php');
$registry_kds  = safe_require_registry($reg_dir . '/cpsys_registry_kds.php');
$registry_diag = safe_require_registry($reg_dir . '/cpsys_registry_diag.php'); // 诊断兜底

/* 5) 合并注册表并运行 */
$full_registry = array_merge(
    $registry_base,
    $registry_bms,
    $registry_rms,
    $registry_ext,
    $registry_kds,
    $registry_diag
);
run_api($full_registry, $pdo);
