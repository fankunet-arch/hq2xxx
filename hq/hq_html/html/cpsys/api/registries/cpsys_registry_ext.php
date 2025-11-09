<?php
/**
 * Toptea HQ - CPSYS API 扩展注册表（Reports, UTC-safe）
 * 仅新增报表端点；不改视图、不跨 POS/KDS。
 * Version: 1.1.2
 * Date: 2025-11-09
 */

declare(strict_types=1);

require_once realpath(__DIR__ . '/../../../../app/helpers/http_json_helper.php');
require_once realpath(__DIR__ . '/../../../../app/helpers/datetime_helper.php');
require_once realpath(__DIR__ . '/../../../../app/helpers/kds_helper.php');
require_once realpath(__DIR__ . '/../../../../app/helpers/auth_helper.php');

/** 将本地日窗转换为 UTC 窗字符串 [start, end]（含当日） */
function _ext_local_day_to_utc_strings(string $from_local_date, ?string $to_local_date = null): array {
    [$utc_start_dt, $utc_end_dt] = to_utc_window($from_local_date, $to_local_date);
    return [$utc_start_dt->format('Y-m-d H:i:s'), $utc_end_dt->format('Y-m-d H:i:s')];
}

/** Handler: reports/invoice_list */
function handle_reports_invoice_list_utc(PDO $pdo, array $config, array $input): void {
    try {
        $from_date = (string)($input['from_date'] ?? ($_GET['from_date'] ?? date('Y-m-d')));
        $to_date   = (string)($input['to_date']   ?? ($_GET['to_date']   ?? $from_date));
        $store_id  = isset($input['store_id']) ? (int)$input['store_id'] : (isset($_GET['store_id']) ? (int)$_GET['store_id'] : null);
        $status    = (string)($input['status'] ?? ($_GET['status'] ?? ''));
        $status    = $status !== '' ? $status : null;

        [$start, $end] = _ext_local_day_to_utc_strings($from_date, $to_date);

        $clauses = ['p.issued_at >= :start', 'p.issued_at <= :end'];
        $params  = [':start' => $start, ':end' => $end];

        if ($store_id) { $clauses[] = 'p.store_id = :sid'; $params[':sid'] = $store_id; }
        if ($status && in_array($status, ['ISSUED','CANCELLED','CORRECTED'], true)) {
            $clauses[] = 'p.status = :status'; $params[':status'] = $status;
        }

        $sql = "
            SELECT p.id, p.series, p.number, p.issued_at, p.invoice_type, p.status,
                   p.store_id, s.store_name, p.final_total, p.taxable_base, p.vat_amount
              FROM pos_invoices p
              LEFT JOIN kds_stores s ON p.store_id = s.id
             WHERE " . implode(' AND ', $clauses) . "
             ORDER BY p.issued_at DESC, p.id DESC
             LIMIT 500
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        json_ok($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    } catch (Throwable $e) {
        json_error('invoice_list 失败: ' . $e->getMessage(), 500);
    }
}

/** Handler: reports/eod_summary */
function handle_reports_eod_summary_utc(PDO $pdo, array $config, array $input): void {
    try {
        $local_date = (string)($input['local_date'] ?? ($_GET['local_date'] ?? date('Y-m-d')));
        $store_id   = isset($input['store_id']) ? (int)$input['store_id'] : (isset($_GET['store_id']) ? (int)$_GET['store_id'] : null);

        $result = [
            'local_date' => $local_date,
            'by_store' => [],
            'total_orders' => 0,
            'total_sales'  => 0.0,
        ];

        // 门店列表
        if ($store_id) {
            $stmt = $pdo->prepare("SELECT id, store_name, eod_cutoff_hour FROM kds_stores WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$store_id]);
            $stores = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            $stores = $pdo->query("SELECT id, store_name, eod_cutoff_hour FROM kds_stores WHERE is_active = 1 AND deleted_at IS NULL")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        // 逐店换算 UTC 窗并汇总
        foreach ($stores as $s) {
            $sid = (int)$s['id'];
            $cut = (int)($s['eod_cutoff_hour'] ?? 3);

            $tz = new DateTimeZone(APP_DEFAULT_TIMEZONE);
            $start_local = new DateTime($local_date . sprintf(' %02d:00:00', $cut), $tz);
            $end_local   = (clone $start_local)->modify('+1 day');
            $start = $start_local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $end   = $end_local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

            $stmt = $pdo->prepare("
                SELECT COUNT(id) AS orders, COALESCE(SUM(final_total),0) AS sales
                  FROM pos_invoices
                 WHERE store_id = :sid AND issued_at >= :start AND issued_at <= :end AND status = 'ISSUED'
            ");
            $stmt->execute([':sid'=>$sid, ':start'=>$start, ':end'=>$end]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['orders'=>0, 'sales'=>0];

            $result['by_store'][] = [
                'store_id'   => $sid,
                'store_name' => (string)$s['store_name'],
                'cutoff_hour'=> $cut,
                'utc_window' => ['start'=>$start, 'end'=>$end],
                'orders'     => (int)$row['orders'],
                'sales'      => (float)$row['sales'],
            ];
            $result['total_orders'] += (int)$row['orders'];
            $result['total_sales']  += (float)$row['sales'];
        }

        json_ok($result);
    } catch (Throwable $e) {
        json_error('eod_summary 失败: ' . $e->getMessage(), 500);
    }
}

/** Handler: reports/dashboard_kpis */
function handle_reports_dashboard_kpis_utc(PDO $pdo, array $config, array $input): void {
    try {
        $today = date('Y-m-d');
        [$start, $end] = _ext_local_day_to_utc_strings($today, null);

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(final_total),0) AS total_sales, COUNT(id) AS total_orders
              FROM pos_invoices
             WHERE issued_at >= :s AND issued_at <= :e AND status='ISSUED'
        ");
        $stmt->execute([':s'=>$start, ':e'=>$end]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_sales'=>0, 'total_orders'=>0];

        $stmt2 = $pdo->prepare("
            SELECT COUNT(id) FROM pos_members
             WHERE created_at >= :s AND created_at <= :e AND deleted_at IS NULL
        ");
        $stmt2->execute([':s'=>$start, ':e'=>$end]);
        $new_members = (int)$stmt2->fetchColumn();

        $active_stores = (int)$pdo->query("SELECT COUNT(id) FROM kds_stores WHERE is_active = 1 AND deleted_at IS NULL")->fetchColumn();

        json_ok([
            'total_sales'   => (float)($row['total_sales'] ?? 0),
            'total_orders'  => (int)($row['total_orders'] ?? 0),
            'new_members'   => $new_members,
            'active_stores' => $active_stores
        ]);
    } catch (Throwable $e) {
        json_error('dashboard_kpis 失败: ' . $e->getMessage(), 500);
    }
}

/** Handler: reports/top_today */
function handle_reports_top_today_utc(PDO $pdo, array $config, array $input): void {
    try {
        $today = date('Y-m-d');
        [$start, $end] = _ext_local_day_to_utc_strings($today, null);

        $sql = "
            SELECT pi.item_name_zh, SUM(pi.quantity) AS total_quantity
              FROM pos_invoice_items pi
              JOIN pos_invoices p ON pi.invoice_id = p.id
             WHERE p.issued_at >= :s AND p.issued_at <= :e AND p.status='ISSUED'
             GROUP BY pi.item_name_zh
             ORDER BY total_quantity DESC
             LIMIT 5
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':s'=>$start, ':e'=>$end]);
        json_ok($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    } catch (Throwable $e) {
        json_error('top_today 失败: ' . $e->getMessage(), 500);
    }
}

// --- 注册表导出 ---
// 临时公开只读，便于链路自测；如需收紧权限，将 auth_role 改为 ROLE_SUPER_ADMIN。
return [
    'reports' => [
        'table' => null,
        'pk' => null,
        'soft_delete_col' => null,
        'auth_role' => null,
        'custom_actions' => [
            'invoice_list'   => 'handle_reports_invoice_list_utc',
            'eod_summary'    => 'handle_reports_eod_summary_utc',
            'dashboard_kpis' => 'handle_reports_dashboard_kpis_utc',
            'top_today'      => 'handle_reports_top_today_utc',
        ],
    ],
];
