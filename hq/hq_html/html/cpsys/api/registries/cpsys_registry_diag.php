<?php
/**
 * Toptea HQ - CPSYS 诊断注册表（零依赖兜底 + 只读）
 * - 在 require helper 前做存在性检查；若缺失则提供最小 JSON 兜底，避免 500
 * - 仅做环境/函数/表/注册表“裸 …”自检，不改业务
 * Version: 1.0.3
 * Date: 2025-11-09
 */

declare(strict_types=1);

/* ---------- 最小 JSON 兜底（若官方 helper 不在则用本地降级） ---------- */
if (!function_exists('json_ok')) {
    function json_ok($data = null, string $message = 'OK', int $code = 200): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'success', 'message' => $message, 'data' => $data],
            JSON_UNESCAPED_UNICODE);
        exit;
    }
}
if (!function_exists('json_error')) {
    function json_error(string $message = 'ERROR', int $code = 500, $data = null): void {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
        echo json_encode(['status' => 'error', 'message' => $message, 'data' => $data],
            JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/* ---------- 尝试加载官方 helper（存在才加载，不存在不报致命） ---------- */
$hroot = dirname(__DIR__, 3) . '/app/helpers';
$helpers = [
    $hroot . '/http_json_helper.php',
    $hroot . '/datetime_helper.php',
    $hroot . '/kds_helper.php',
    $hroot . '/auth_helper.php',
];
foreach ($helpers as $hp) {
    if (is_file($hp)) {
        require_once $hp;
    }
}

/* ---------- 内部工具：检测文件是否含“裸 …” ---------- */
if (!function_exists('_diag_has_naked_ellipsis')) {
    function _diag_has_naked_ellipsis(string $path): ?bool {
        if (!is_file($path)) return null;
        $txt = @file_get_contents($path);
        if ($txt === false)  return null;
        $code = preg_replace('#/\*.*?\*/#s', '', $txt);
        $code = preg_replace('#//.*$#m', '', $code);
        $code = preg_replace('#^\s*#.*$#m', '', $code);
        $code = preg_replace("#'([^'\\\\]|\\\\.)*'#s", "''", $code);
        $code = preg_replace('#"([^"\\\\]|\\\\.)*"#s', '""', $code);
        return strpos($code, '...') !== false;
    }
}

/* ---------- Handler: diag/selfcheck ---------- */
if (!function_exists('handle_diag_selfcheck')) {
    function handle_diag_selfcheck(PDO $pdo = null, array $config = [], array $input = []): void
    {
        $api_root = dirname(__DIR__); // html/cpsys/api
        $checks   = [];

        // 1) 函数存在性（官方 or 兜底）
        $checks['functions'] = [
            'json_ok'  => function_exists('json_ok'),
            'json_error' => function_exists('json_error'),
            'utc_now'    => function_exists('utc_now'),
            'to_utc_window' => function_exists('to_utc_window'),
            // 报表（可能不存在但不致命）
            'get_dashboard_kpis_utc'             => function_exists('get_dashboard_kpis_utc'),
            'get_top_selling_products_today_utc' => function_exists('get_top_selling_products_today_utc'),
            'get_invoice_list_utc'               => function_exists('get_invoice_list_utc'),
            'get_eod_summary_utc'                => function_exists('get_eod_summary_utc'),
        ];

        // 2) 关键文件存在性
        $checks['files'] = [
            'gateway'         => is_file($api_root . '/cpsys_api_gateway.php'),
            'registry_ext'    => is_file($api_root . '/registries/cpsys_registry_ext.php'),
            'registry_diag'   => is_file($api_root . '/registries/cpsys_registry_diag.php'),
            'registry_base'   => is_file($api_root . '/registries/cpsys_registry_base.php'),
            'registry_bms'    => is_file($api_root . '/registries/cpsys_registry_bms.php'),
            'datetime_helper' => is_file(dirname($api_root, 1) . '/../app/helpers/datetime_helper.php'), // 兼容旧相对层级
            'kds_helper'      => is_file(dirname($api_root, 1) . '/../app/helpers/kds_helper.php'),
        ];

        // 3) 关键表可访问性（只读探测，允许 $pdo 为空）
        $tables = ['kds_stores','pos_invoices','pos_invoice_items','pos_members'];
        $table_ok = [];
        foreach ($tables as $t) {
            try {
                if ($pdo instanceof PDO) {
                    $stmt = $pdo->query("SELECT 1 FROM {$t} LIMIT 1");
                    $table_ok[$t] = $stmt !== false;
                } else {
                    $table_ok[$t] = null; // 未注入 PDO，保持非致命
                }
            } catch (Throwable $e) {
                $table_ok[$t] = false;
            }
        }
        $checks['tables'] = $table_ok;

        // 4) 注册表是否含“裸 …”
        $reg_dir = $api_root . '/registries/';
        $checks['registry_placeholders'] = [
            'base_has_naked_ellipsis' => _diag_has_naked_ellipsis($reg_dir . 'cpsys_registry_base.php'),
            'bms_has_naked_ellipsis'  => _diag_has_naked_ellipsis($reg_dir . 'cpsys_registry_bms.php'),
            'ext_has_naked_ellipsis'  => _diag_has_naked_ellipsis($reg_dir . 'cpsys_registry_ext.php'),
        ];

        json_ok($checks, 'diag_ok');
    }
}

/* ---------- 注册表导出（公开只读） ---------- */
return [
    'diag' => [
        'table' => null,
        'pk'    => null,
        'soft_delete_col' => null,
        'auth_role' => null, // 公开只读，便于随时自检
        'custom_actions' => [
            'selfcheck' => 'handle_diag_selfcheck',
        ],
    ],
];
