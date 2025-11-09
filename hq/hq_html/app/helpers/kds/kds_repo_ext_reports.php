<?php
/**
 * Toptea HQ - KDS Repo Extension (Reports, UTC-safe)
 * Scope: HQ/CPSYS only (no cross to POS/KDS runtime).
 * Version: 1.1.0 (2025-11-09)
 *
 * Functions:
 * - get_dashboard_kpis_utc(PDO $pdo): array
 * - get_top_selling_products_today_utc(PDO $pdo): array
 * - get_invoice_list_utc(PDO $pdo, array $filters): array
 * - get_eod_summary_utc(PDO $pdo, string $local_date, ?int $store_id = null): array
 */

declare(strict_types=1);

if (!defined('APP_DEFAULT_TIMEZONE')) {
    define('APP_DEFAULT_TIMEZONE', 'Europe/Madrid');
}

/** Convert a local DateTime (with tz) to UTC 'Y-m-d H:i:s' string */
function __dt_to_utc_string(DateTime $dt): string {
    $u = clone $dt;
    $u->setTimezone(new DateTimeZone('UTC'));
    return $u->format('Y-m-d H:i:s');
}

/** Compute UTC window for a local date range [from_local_00:00, to_local_next_00:00) */
function __local_range_to_utc_window(string $from_local_date, ?string $to_local_date, string $tz = APP_DEFAULT_TIMEZONE): array {
    $tzObj = new DateTimeZone($tz);
    $from = new DateTime($from_local_date . ' 00:00:00', $tzObj);
    $to   = new DateTime(($to_local_date ?? $from_local_date) . ' 00:00:00', $tzObj);
    $to->modify('+1 day');
    return [__dt_to_utc_string($from), __dt_to_utc_string($to)];
}

/** Compute EOD UTC window using store cutoff hour (e.g., 3 => 03:00 local to next day 03:00 local) */
function __eod_local_window_to_utc(string $local_date, int $cutoff_hour = 3, string $tz = APP_DEFAULT_TIMEZONE): array {
    $tzObj = new DateTimeZone($tz);
    $start = new DateTime($local_date . sprintf(' %02d:00:00', $cutoff_hour), $tzObj);
    $end   = clone $start;
    $end->modify('+1 day');
    return [__dt_to_utc_string($start), __dt_to_utc_string($end)];
}

/** KPIs for today (local Madrid date -> UTC window) */
function get_dashboard_kpis_utc(PDO $pdo): array {
    $local_today = date('Y-m-d');
    [$start, $end] = __local_range_to_utc_window($local_today, null, APP_DEFAULT_TIMEZONE);

    // Sales & orders
    $stmt_sales = $pdo->prepare("
        SELECT COALESCE(SUM(final_total), 0) AS total_sales, COUNT(id) AS total_orders
          FROM pos_invoices 
         WHERE issued_at >= :start AND issued_at < :end AND status = 'ISSUED'
    ");
    $stmt_sales->execute([':start' => $start, ':end' => $end]);
    $sales = $stmt_sales->fetch(PDO::FETCH_ASSOC) ?: ['total_sales'=>0,'total_orders'=>0];

    // New members
    $stmt_mem = $pdo->prepare("
        SELECT COUNT(id)
          FROM pos_members
         WHERE created_at >= :start AND created_at < :end AND deleted_at IS NULL
    ");
    $stmt_mem->execute([':start' => $start, ':end' => $end]);
    $new_members = (int)$stmt_mem->fetchColumn();

    // Active stores
    $active_stores = (int)($pdo->query("SELECT COUNT(id) FROM kds_stores WHERE is_active = 1 AND deleted_at IS NULL")->fetchColumn());

    return [
        'total_sales'  => (float)($sales['total_sales'] ?? 0),
        'total_orders' => (int)($sales['total_orders'] ?? 0),
        'new_members'  => $new_members,
        'active_stores'=> $active_stores
    ];
}

/** Top 5 selling products today (by quantity), Madrid local day -> UTC window */
function get_top_selling_products_today_utc(PDO $pdo): array {
    $local_today = date('Y-m-d');
    [$start, $end] = __local_range_to_utc_window($local_today, null, APP_DEFAULT_TIMEZONE);

    $sql = "
        SELECT pi.item_name_zh, SUM(pi.quantity) AS total_quantity
          FROM pos_invoice_items pi
          JOIN pos_invoices p ON pi.invoice_id = p.id
         WHERE p.issued_at >= :start AND p.issued_at < :end AND p.status = 'ISSUED'
         GROUP BY pi.item_name_zh
         ORDER BY total_quantity DESC
         LIMIT 5
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':start' => $start, ':end' => $end]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Invoice list (UTC-safe) for a local date range; optional store filter.
 * filters: ['from_date'=>'YYYY-MM-DD','to_date'=>'YYYY-MM-DD'(inclusive),'store_id'=>int|null,'status'=>'ISSUED|CANCELLED|CORRECTED']
 */
function get_invoice_list_utc(PDO $pdo, array $filters): array {
    $from_date = isset($filters['from_date']) ? (string)$filters['from_date'] : date('Y-m-d');
    $to_date   = isset($filters['to_date']) ? (string)$filters['to_date'] : $from_date;
    [$start, $end] = __local_range_to_utc_window($from_date, $to_date, APP_DEFAULT_TIMEZONE);

    $clauses = ['p.issued_at >= :start', 'p.issued_at < :end'];
    $params  = [':start' => $start, ':end' => $end];

    if (!empty($filters['store_id'])) {
        $clauses[] = 'p.store_id = :store_id';
        $params[':store_id'] = (int)$filters['store_id'];
    }
    if (!empty($filters['status']) && in_array($filters['status'], ['ISSUED','CANCELLED','CORRECTED'], true)) {
        $clauses[] = 'p.status = :status';
        $params[':status'] = $filters['status'];
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
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** EOD summary (UTC-safe) for a given local date with store cutoff hour. */
function get_eod_summary_utc(PDO $pdo, string $local_date, ?int $store_id = null): array {
    $result = [
        'local_date' => $local_date,
        'by_store' => [],
        'total_orders' => 0,
        'total_sales'  => 0.0,
    ];

    $stores = [];
    if ($store_id !== null) {
        $stmt = $pdo->prepare("SELECT id, store_name, eod_cutoff_hour FROM kds_stores WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$store_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $stores[] = $row;
    } else {
        $stmt = $pdo->query("SELECT id, store_name, eod_cutoff_hour FROM kds_stores WHERE is_active = 1 AND deleted_at IS NULL");
        $stores = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    foreach ($stores as $s) {
        $cutoff = (int)($s['eod_cutoff_hour'] ?? 3);
        [$start, $end] = __eod_local_window_to_utc($local_date, $cutoff, APP_DEFAULT_TIMEZONE);

        $stmt = $pdo->prepare("
            SELECT COUNT(id) AS orders, COALESCE(SUM(final_total),0) AS sales
              FROM pos_invoices
             WHERE store_id = :sid AND issued_at >= :start AND issued_at < :end AND status = 'ISSUED'
        ");
        $stmt->execute([':sid' => (int)$s['id'], ':start' => $start, ':end' => $end]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['orders'=>0, 'sales'=>0];

        $result['by_store'][] = [
            'store_id' => (int)$s['id'],
            'store_name' => (string)$s['store_name'],
            'cutoff_hour' => $cutoff,
            'utc_window' => ['start' => $start, 'end' => $end],
            'orders' => (int)$row['orders'],
            'sales'  => (float)$row['sales'],
        ];
        $result['total_orders'] += (int)$row['orders'];
        $result['total_sales']  += (float)$row['sales'];
    }
    return $result;
}
