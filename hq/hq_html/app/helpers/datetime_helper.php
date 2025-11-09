<?php
/**
 * Toptea HQ - CPSYS 诊断注册表（只读自检）
 * 功能：返回函数存在性、关键文件存在性、关键表只读探测、注册表占位符检查结果
 * 仅使用 HQ 公用函数；不跨 POS/KDS。
 * Version: 1.0.2
 * Date: 2025-11-09
 */

declare(strict_types=1);

require_once realpath(__DIR__ . '/../../../../app/helpers/http_json_helper.php');
require_once realpath(__DIR__ . '/../../../../app/helpers/datetime_helper.php');
require_once realpath(__DIR__ . '/../../../../app/helpers/kds_helper.php');
require_once realpath(__DIR__ . '/../../../../app/helpers/auth_helper.php');

/** 自检：GET/POST 均可，无参数 */
function handle_diag_selfcheck(PDO $pdo, array $config, array $input): void
{
    $checks = [];

    // 1) 函数存在性（JSON/时间 + 报表 + 首页兼容封装）
    $checks['functions'] = [
        // JSON helper
        'json_ok'                            => function_exists('json_ok'),
        'json_error'                         => function_exists('json_error'),
        // 时间助手
        'utc_now'                            => function_exists('utc_now'),
        'to_utc_window'                      => function_exists('to_utc_window'),
        // 首页驼峰封装（如存在）
        'getDashboardKpisUtc'                => function_exists('getDashboardKpisUtc'),
        'getTopSellingProductsTodayUtc'      => function_exists('getTopSellingProductsTodayUtc'),
        // 报表下划线原函数
        'get_dashboard_kpis_utc'             => function_exists('get_dashboard_kpis_utc'),
        'get_top_selling_products_today_utc' => function_exists('get_top_selling_products_today_utc'),
        'get_invoice_list_utc'               => function_exists('get_invoice_list_utc'),
        'get_eod_summary_utc'                => function_exists('get_eod_summary_utc'),
    ];

    // 2) 关键文件存在性
    $api_root = realpath(__DIR__ . '/..'); // 到 html/cpsys/api
    $checks['files'] = [
        'gateway'         => is_file($api_root . '/cpsys_api_gateway.php'),
        'registry_ext'    => is_file($api_root . '/registries/cpsys_registry_ext.php'),
        'registry_diag'   => is_file($api_root . '/registries/cpsys_registry_diag.php'),
        'registry_base'   => is_file($api_root . '/registries/cpsys_registry_base.php'),
        'registry_bms'    => is_file($api_root . '/registries/cpsys_registry_bms.php'),
        'datetime_helper' => is_file($api_root . '/../app/helpers/datetime_helper.php'),
        'kds_helper'      => is_file($api_root . '/../app/helpers/kds_helper.php'),
    ];

    // 3) 关键表可访问性（只读探测，LIMIT 1）
    $tables = ['kds_stores','pos_invoices','pos_invoice_items','pos_members'];
    $table_ok = [];
    foreach ($tables as $t) {
        try {
            $stmt = $pdo->query("SELECT 1 FROM {$t} LIMIT 1");
            $table_ok[$t] = $stmt !== false;
        } catch (Throwable $e) {
            $table_ok[$t] = false;
        }
    }
    $checks['tables'] = $table_ok;

    // 4) 注册表是否含“裸 ...”占位符（用于提示哪些文件仍需修复）
    $has_naked = function (string $p): ?bool {
        if (!is_file($p)) return null;
        $txt = @file_get_contents($p);
        if ($txt === false) return null;
        // 移除注释与字符串，检测裸省略号
        $code = preg_replace('#/\*.*?\*/#s', '', $txt);
        $code = preg_replace('#//.*$#m', '', $code);
        $code = preg_replace('#^\s*#.*$#m', '', $code);
        $code = preg_replace("#'([^'\\\\]|\\\\.)*'#s", "''", $code);
        $code = preg_replace('#"([^"\\\\]|\\\\.)*"#s', '""', $code);
        return strpos($code, '...') !== false;
    };
    $reg_dir = $api_root . '/registries/';
    $checks['registry_placeholders'] = [
        'base_has_naked_ellipsis' => $has_naked($reg_dir . 'cpsys_registry_base.php'),
        'bms_has_naked_ellipsis'  => $has_naked($reg_dir . 'cpsys_registry_bms.php'),
    ];

    json_ok($checks, 'diag_ok');
}

// 公开只读；如需收紧，将 auth_role 改为 ROLE_SUPER_ADMIN
return [
    'diag' => [
        'table' => null,
        'pk' => null,
        'soft_delete_col' => null,
        'auth_role' => null,
        'custom_actions' => [
            'selfcheck' => 'handle_diag_selfcheck',
        ],
    ],
];
