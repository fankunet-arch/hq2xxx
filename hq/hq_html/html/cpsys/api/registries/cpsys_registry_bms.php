<?php
/**
 * Toptea HQ - CPSYS API 注册表 (BMS - POS Management)
 * 注册 POS 菜单、商品、会员、促销等资源
 * Version: 1.3.001 (Syntax Fix & Material Usage Report)
 * Date: 2025-11-09
 *
 * [GEMINI V1.3.001]: CRITICAL FIX - Replaced trailing '}' with ';' to fix PHP Parse Error (500).
 * [GEMINI V1.3.000]: Added handle_menu_get_material_usage_report and registered 'get_material_usage_report' action.
 * [GEMINI V1.2.005]: Added `mi.product_code` to the SELECT list in `handle_menu_get_with_materials` per user request.
 */

require_once realpath(__DIR__ . '/../../../../app/helpers/kds_helper.php');
require_once realpath(__DIR__ . '/../../../../app/helpers/auth_helper.php');

if (!defined('ROLE_SUPER_ADMIN')) define('ROLE_SUPER_ADMIN', 9);
if (!defined('ROLE_PRODUCT_MANAGER')) define('ROLE_PRODUCT_MANAGER', 5);

function json_ok($data = null, $message = 'ok', $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'success', 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}
function json_error($message = 'error', $code = 400, $data = null) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

/* -------------------- 工具函数 -------------------- */
function require_role($role_required) {
    $role = (int)($_SESSION['hq_user_role'] ?? 0);
    if ($role < $role_required) {
        json_error('权限不足', 403);
    }
}
function str_bool($v) { return in_array(strtolower((string)$v), ['1','true','yes','on'], true); }

/* -------------------- 类目 CRUD -------------------- */
function handle_pos_category_get(PDO $pdo, array $config, array $input_data): void {
    $id = $_GET['id'] ?? json_error('缺少 id', 400);
    $stmt = $pdo->prepare("SELECT * FROM pos_categories WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([(int)$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $data ? json_ok($data) : json_error('未找到分类', 404);
}
function handle_pos_category_save(PDO $pdo, array $config, array $input_data): void {
    $data = $input_data['data'] ?? json_error('缺少 data', 400);
    $id = $data['id'] ? (int)$data['id'] : null;
    $code = trim($data['category_code'] ?? '');
    $name_zh = trim($data['name_zh'] ?? '');
    $name_es = trim($data['name_es'] ?? '');
    $sort = (int)($data['sort_order'] ?? 99);
    if (empty($code) || empty($name_zh) || empty($name_es)) json_error('字段不完整', 422);
    if ($id) {
        $stmt = $pdo->prepare("UPDATE pos_categories SET category_code=?, name_zh=?, name_es=?, sort_order=? WHERE id=?");
        $stmt->execute([$code, $name_zh, $name_es, $sort, $id]);
        json_ok(['id'=>$id], '分类已更新。');
    } else {
        $stmt = $pdo->prepare("INSERT INTO pos_categories (category_code, name_zh, name_es, sort_order) VALUES (?,?,?,?)");
        $stmt->execute([$code, $name_zh, $name_es, $sort]);
        json_ok(['id'=>(int)$pdo->lastInsertId()], '分类已创建。');
    }
}
function handle_pos_category_delete(PDO $pdo, array $config, array $input_data): void {
    $id = (int)($input_data['id'] ?? 0);
    if ($id <= 0) json_error('无效 id', 400);
    $pdo->prepare("UPDATE pos_categories SET deleted_at = CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);
    json_ok(['id'=>$id], '分类已删除。');
}

/* -------------------- 菜品 CRUD & 查询 -------------------- */
function handle_menu_item_get(PDO $pdo, array $config, array $input_data): void {
    $id = $_GET['id'] ?? json_error('缺少 id', 400);
    $stmt = $pdo->prepare("SELECT * FROM pos_menu_items WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([(int)$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $row ? json_ok($row) : json_error('未找到菜品', 404);
}
function handle_menu_item_save(PDO $pdo, array $config, array $input_data): void {
    $d = $input_data['data'] ?? json_error('缺少 data', 400);
    $id = $d['id'] ? (int)$d['id'] : null;
    $code = trim($d['product_code'] ?? '');
    $name_zh = trim($d['title_zh'] ?? '');
    $price = (float)($d['price'] ?? 0);
    if ($code === '' || $name_zh === '' || $price <= 0) json_error('字段不完整', 422);
    if ($id) {
        $sql = "UPDATE pos_menu_items SET product_code=?, title_zh=?, price=? WHERE id=?";
        $pdo->prepare($sql)->execute([$code, $name_zh, $price, $id]);
        json_ok(['id'=>$id], '菜品已更新。');
    } else {
        $sql = "INSERT INTO pos_menu_items (product_code, title_zh, price) VALUES (?,?,?)";
        $pdo->prepare($sql)->execute([$code, $name_zh, $price]);
        json_ok(['id'=>(int)$pdo->lastInsertId()], '菜品已创建。');
    }
}
function handle_menu_item_delete(PDO $pdo, array $config, array $input_data): void {
    $id = (int)($input_data['id'] ?? 0);
    if ($id <= 0) json_error('无效 id', 400);
    $pdo->prepare("UPDATE pos_menu_items SET deleted_at = CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);
    json_ok(['id'=>$id], '菜品已删除。');
}

/* -------------------- 变体 & 加料 -------------------- */
function handle_variant_save(PDO $pdo, array $config, array $input_data): void {
    $d = $input_data['data'] ?? json_error('缺少 data', 400);
    $id = $d['id'] ? (int)$d['id'] : null;
    $menu_id = (int)($d['menu_item_id'] ?? 0);
    $name = trim($d['name'] ?? '');
    $price_delta = (float)($d['price_delta'] ?? 0);
    if ($menu_id <= 0 || $name === '') json_error('字段不完整', 422);
    if ($id) {
        $pdo->prepare("UPDATE pos_variants SET name=?, price_delta=? WHERE id=?")
            ->execute([$name, $price_delta, $id]);
        json_ok(['id'=>$id], '变体已更新。');
    } else {
        $pdo->prepare("INSERT INTO pos_variants (menu_item_id, name, price_delta) VALUES (?,?,?)")
            ->execute([$menu_id, $name, $price_delta]);
        json_ok(['id'=>(int)$pdo->lastInsertId()], '变体已创建。');
    }
}
function handle_addon_save(PDO $pdo, array $config, array $input_data): void {
    $d = $input_data['data'] ?? json_error('缺少 data', 400);
    $id = $d['id'] ? (int)$d['id'] : null;
    $menu_id = (int)($d['menu_item_id'] ?? 0);
    $name = trim($d['name'] ?? '');
    $price = (float)($d['price'] ?? 0);
    if ($menu_id <= 0 || $name === '' || $price < 0) json_error('字段不完整', 422);
    if ($id) {
        $pdo->prepare("UPDATE pos_addons SET name=?, price=? WHERE id=?")
            ->execute([$name, $price, $id]);
        json_ok(['id'=>$id], '加料已更新。');
    } else {
        $pdo->prepare("INSERT INTO pos_addons (menu_item_id, name, price) VALUES (?,?,?)")
            ->execute([$menu_id, $name, $price]);
        json_ok(['id'=>(int)$pdo->lastInsertId()], '加料已创建。');
    }
}

/* -------------------- 会员 & 促销 -------------------- */
function handle_member_get(PDO $pdo, array $config, array $input_data): void {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_error('缺少 id', 400);
    $stmt = $pdo->prepare("SELECT * FROM pos_members WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $row ? json_ok($row) : json_error('未找到会员', 404);
}
function handle_promo_get(PDO $pdo, array $config, array $input_data): void {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_error('缺少 id', 400);
    $stmt = $pdo->prepare("SELECT * FROM pos_promotions WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $row ? json_ok($row) : json_error('未找到促销', 404);
}
function handle_promo_save(PDO $pdo, array $config, array $input_data): void {
    $d = $input_data['data'] ?? json_error('缺少 data', 400);
    $id = $d['id'] ? (int)$d['id'] : null;
    $name = trim($d['name'] ?? '');
    $type = trim($d['type'] ?? '');
    $value = (float)($d['value'] ?? 0);
    if ($name === '' || $type === '') json_error('字段不完整', 422);
    if ($id) {
        $pdo->prepare("UPDATE pos_promotions SET name=?, type=?, value=? WHERE id=?")->execute([$name, $type, $value, $id]);
        json_ok(['id'=>$id], '促销已更新。');
    } else {
        $pdo->prepare("INSERT INTO pos_promotions (name, type, value) VALUES (?,?,?)")->execute([$name, $type, $value]);
        json_ok(['id'=>(int)$pdo->lastInsertId()], '促销已创建。');
    }
}
function handle_promo_delete(PDO $pdo, array $config, array $input_data): void {
    $id = (int)($input_data['id'] ?? 0);
    if ($id <= 0) json_error('无效 id', 400);
    $pdo->prepare("UPDATE pos_promotions SET deleted_at = CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);
    json_ok(['id'=>$id], '促销已删除。');
}

/* -------------------- 设置（含 SIF） -------------------- */
function handle_settings_load(PDO $pdo, array $config, array $input_data): void {
    $store_id = (int)($_GET['store_id'] ?? 0);
    if ($store_id <= 0) json_error('缺少 store_id', 400);
    $stmt = $pdo->prepare("SELECT * FROM kds_stores WHERE id = ?");
    $stmt->execute([$store_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $row ? json_ok($row) : json_error('未找到门店', 404);
}
function handle_settings_save(PDO $pdo, array $config, array $input_data): void {
    require_role(ROLE_SUPER_ADMIN);
    $d = $input_data['data'] ?? json_error('缺少 data', 400);
    $id = (int)($d['id'] ?? 0);
    if ($id <= 0) json_error('缺少 id', 400);
    $invoice_prefix = trim($d['invoice_prefix'] ?? '');
    $default_vat_rate = (float)($d['default_vat_rate'] ?? 21.0);
    $eod_cutoff_hour = (int)($d['eod_cutoff_hour'] ?? 3);
    $compliance_system = trim($d['billing_system'] ?? 'VERIFACTU');
    $tax_id = trim($d['tax_id'] ?? '');
    $pdo->prepare("UPDATE kds_stores SET invoice_prefix=?, default_vat_rate=?, eod_cutoff_hour=?, billing_system=?, tax_id=? WHERE id=?")
        ->execute([$invoice_prefix, $default_vat_rate, $eod_cutoff_hour, $compliance_system, $tax_id, $id]);
    json_ok(['id'=>$id], '设置已保存。');
}
function handle_sif_load(PDO $pdo, array $config, array $input_data): void {
    $store_id = (int)($_GET['store_id'] ?? 0);
    if ($store_id <= 0) json_error('缺少 store_id', 400);
    $stmt = $pdo->prepare("SELECT billing_system, tax_id, invoice_prefix FROM kds_stores WHERE id=?");
    $stmt->execute([$store_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $row ? json_ok($row) : json_error('未找到门店', 404);
}
function handle_sif_save(PDO $pdo, array $config, array $input_data): void {
    require_role(ROLE_SUPER_ADMIN);
    $store_id = (int)($input_data['store_id'] ?? 0);
    $billing_system = trim($input_data['billing_system'] ?? 'VERIFACTU');
    $tax_id = trim($input_data['tax_id'] ?? '');
    $invoice_prefix = trim($input_data['invoice_prefix'] ?? '');
    if ($store_id <= 0 || $tax_id === '' || $invoice_prefix === '') json_error('参数不完整', 422);
    $pdo->prepare("UPDATE kds_stores SET billing_system=?, tax_id=?, invoice_prefix=? WHERE id=?")
        ->execute([$billing_system, $tax_id, $invoice_prefix, $store_id]);
    json_ok(['store_id'=>$store_id], 'SIF 已保存。');
}

/* -------------------- 菜单与物料关联报表（示例） -------------------- */
function handle_menu_get_with_materials(PDO $pdo, array $config, array $input_data): void {
    $stmt = $pdo->query("
        SELECT mi.id, mi.product_code, mi.title_zh, mm.material_name, mm.unit, mm.qty_per
        FROM pos_menu_items mi
        LEFT JOIN pos_material_map mm ON mi.id = mm.menu_item_id
        WHERE mi.deleted_at IS NULL
        ORDER BY mi.id DESC
        LIMIT 200
    ");
    json_ok($stmt->fetchAll(PDO::FETCH_ASSOC));
}
function handle_menu_get_material_usage_report(PDO $pdo, array $config, array $input_data): void {
    $from = $_GET['from'] ?? date('Y-m-01');
    $to   = $_GET['to']   ?? date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT mm.material_name, SUM(pi.quantity * mm.qty_per) AS total_usage, mm.unit
        FROM pos_invoice_items pi
        JOIN pos_material_map mm ON pi.menu_item_id = mm.menu_item_id
        JOIN pos_invoices p ON pi.invoice_id = p.id
        WHERE p.issued_at BETWEEN ? AND ? AND p.status = 'ISSUED'
        GROUP BY mm.material_name, mm.unit
        ORDER BY total_usage DESC
        LIMIT 200
    ");
    $stmt->execute([$from . ' 00:00:00', $to . ' 23:59:59']);
    json_ok($stmt->fetchAll(PDO::FETCH_ASSOC));
}

/* -------------------- 发票：作废 -------------------- */
function handle_invoice_cancel(PDO $pdo, array $config, array $input_data): void {
    $original_invoice_id = (int)($input_data['id'] ?? 0);
    $cancellation_reason = trim($input_data['reason'] ?? 'Error en la emisión');
    if ($original_invoice_id <= 0) json_error('无效的原始票据ID。', 400);
    $pdo->beginTransaction();
    try {
        $stmt_original = $pdo->prepare("SELECT * FROM pos_invoices WHERE id = ? FOR UPDATE");
        $stmt_original->execute([$original_invoice_id]);
        $original_invoice = $stmt_original->fetch();
        if (!$original_invoice) { $pdo->rollBack(); json_error("原始票据不存在。", 404); }
        if ($original_invoice['status'] === 'CANCELLED') { $pdo->rollBack(); json_error("已作废的票据不可重复作废。", 409); }

        $compliance_system = $original_invoice['compliance_system'];
        $store_id = $original_invoice['store_id'];

        $handler_class = "{$compliance_system}Handler";
        $handler = new $handler_class();
        $series = $original_invoice['series'];
        $issued_at = utc_now()->format('Y-m-d H:i:s.u'); // ✅ 统一为 UTC
        $stmt_store = $pdo->prepare("SELECT tax_id FROM kds_stores WHERE id = ?");
        $stmt_store->execute([$store_id]);
        $store_config = $stmt_store->fetch();
        $issuer_nif = $store_config['tax_id'];
        $stmt_prev = $pdo->prepare("SELECT compliance_data FROM ... series = ? AND issuer_nif = ? ORDER BY `number` DESC LIMIT 1");
        $stmt_prev->execute([$compliance_system, $series, $issuer_nif]);
        $prev_invoice = $stmt_prev->fetch();
        $previous_hash = $prev_invoice ? (json_decode($prev_invoice['compliance_data'], true)['hash'] ?? null) : null;
        $cancellationData = ['cancellation_reason' => $cancellation_reason, 'issued_at' => $issued_at];
        $compliance_data = $handler->generateCancellationData($pdo, $original_invoice, $cancellationData, $previous_hash);
        $next_number = 1 + ($pdo->query("SELECT IFNULL(MAX(numbe... '{$series}' AND issuer_nif = '{$issuer_nif}'")->fetchColumn());
        $sql_cancel = "INSERT INTO pos_invoices (invoice_uuid, s... ?, ?, ?, ?, ?, 'R5', 'ISSUED', ?, ?, ?, ?, 0.00, 0.00, 0.00 )";
        $stmt_cancel = $pdo->prepare($sql_cancel);
        $stmt_cancel->execute([ uniqid('can-', true), $store_id,...nvoice_id, $compliance_system, json_encode($compliance_data) ]);
        $cancellation_invoice_id = $pdo->lastInsertId();
        $stmt_update_original = $pdo->prepare("UPDATE pos_invoice
            SET status = 'CANCELLED', updated_at = UTC_TIMESTAMP(6) WHERE id = ?");
        $stmt_update_original->execute([$original_invoice_id]);

        $pdo->commit();
        json_ok(['id'=>(int)$cancellation_invoice_id, 'series'=>$series, 'number'=>$next_number], '已作废。');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_error('作废失败：' . $e->getMessage(), 500);
    }
}

/* -------------------- 发票：更正（差额 / 全额） -------------------- */
function handle_invoice_correct(PDO $pdo, array $config, array $input_data): void {
    $original_invoice_id = (int)($input_data['id'] ?? 0);
    $correction_type = $input_data['type'] ?? ''; // 'S' 全额冲销；'I' 按差额调整
    $new_total_str = $input_data['new_total'] ?? null;
    $reason = trim($input_data['reason'] ?? '');

    if ($original_invoice_id <= 0 || !in_array($correction_type, ['S','I'], true) || $reason === '') {
        json_error('请求参数无效 (ID, 类型, 原因)。', 400);
    }
    if ($correction_type === 'I' && ($new_total_str === null || !is_numeric($new_total_str) || (float)$new_total_str < 0)) {
        json_error('按差额更正时，必须提供一个有效的、非负的最终总额。', 400);
    }

    $pdo->beginTransaction();
    try {
        $stmt_original = $pdo->prepare("SELECT * FROM pos_invoices WHERE id = ? FOR UPDATE");
        $stmt_original->execute([$original_invoice_id]);
        $original_invoice = $stmt_original->fetch();
        if (!$original_invoice) { $pdo->rollBack(); json_error("原始票据不存在。", 404); }
        if ($original_invoice['status'] === 'CANCELLED') { $pdo->rollBack(); json_error("已作废的票据不能被更正。", 409); }

        $compliance_system = $original_invoice['compliance_system'];
        $store_id = $original_invoice['store_id'];

        $series = $original_invoice['series'];
        $issued_at = utc_now()->format('Y-m-d H:i:s.u'); // ✅ 统一为 UTC
        $stmt_prev = $pdo->prepare("SELECT compliance_data FROM
            pos_invoices WHERE compliance_system = ? AND series = ? AND issuer_nif = ?
            ORDER BY `number` DESC LIMIT 1");
        $stmt_store = $pdo->prepare("SELECT tax_id, default_vat_rate FROM kds_stores WHERE id = ?");
        $stmt_store->execute([$store_id]);
        $store_cfg = $stmt_store->fetch(PDO::FETCH_ASSOC);
        $issuer_nif = (string)($store_cfg['tax_id'] ?? '');
        $vat_rate   = (float)($store_cfg['default_vat_rate'] ?? 21.0);

        // 计算差额
        if ($correction_type === 'S') {
            $final_total = -1 * (float)$original_invoice['final_total'];
        } else {
            $new_total = (float)$new_total_str;
            $final_total = $new_total - (float)$original_invoice['final_total'];
        }
        $taxable_base = round($final_total / (1 + ($vat_rate / 100)), 2);
        $vat_amount   = $final_total - $taxable_base;

        $stmt_prev->execute([$compliance_system, $series, $issuer_nif]);
        $prev_invoice = $stmt_prev->fetch();
        $previous_hash = $prev_invoice ? (json_decode($prev_invoice['compliance_data'], true)['hash'] ?? null) : null;

        $correctionData = [
            'reason' => $reason,
            'final_total_delta' => $final_total,
            'issued_at' => $issued_at
        ];
        $handler_class = "{$compliance_system}Handler";
        $handler = new $handler_class();
        $compliance_data = $handler->generateCorrectionData($pdo, $original_invoice, $correctionData, $previous_hash);

        $stmt_max = $pdo->prepare("SELECT COALESCE(MAX(number), 0) FROM pos_invoices WHERE series = ? AND issuer_nif = ?");
        $stmt_max->execute([$series, $issuer_nif]);
        $next_number = (int)$stmt_max->fetchColumn() + 1;

        $sql = "INSERT INTO pos_invoices
                (invoice_uuid, store_id, user_id, shift_id, issuer_nif,
                 series, number, issued_at, invoice_type,
                 taxable_base, vat_amount, discount_amount, final_total,
                 status, compliance_system, compliance_data, payment_summary, related_invoice_id, related_reason, correction_type)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            uniqid('cor-', true), $store_id, (int)($_SESSION['pos_user_id'] ?? 0), (int)($_SESSION['pos_shift_id'] ?? 0), $issuer_nif,
            $series, $next_number, $issued_at, 'R5',
            $taxable_base, $vat_amount, 0.00, $final_total,
            'ISSUED', $compliance_system, json_encode(['reason'=>$reason], JSON_UNESCAPED_UNICODE), json_encode([], JSON_UNESCAPED_UNICODE),
            (int)$original_invoice['id'], $reason, $correction_type
        ]);

        $pdo->commit();
        json_ok([
            'id'=>(int)$pdo->lastInsertId(),
            'series'=>$series, 'number'=>$next_number,
            'final_total'=>$final_total
        ], '更正完成。');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_error('更正失败：' . $e->getMessage(), 500);
    }
}

/* -------------------- 班次复核 -------------------- */
function handle_shift_review(PDO $pdo, array $config, array $input_data): void {
    $shift_id = (int)($input_data['shift_id'] ?? 0);
    $counted_cash_str = $input_data['counted_cash'] ?? null;
    if ($shift_id <= 0 || $counted_cash_str === null || !is_numeric($counted_cash_str)) json_error('无效的参数 (shift_id or counted_cash)。', 400);
    $counted_cash = (float)$counted_cash_str;

    $pdo->beginTransaction();
    try {
        $stmt_get = $pdo->prepare("SELECT id, expected_cash FROM pos_shifts WHERE id = ? AND status = 'FORCE_CLOSED' AND admin_reviewed = 0 FOR UPDATE");
        $stmt_get->execute([$shift_id]);
        $shift = $stmt_get->fetch(PDO::FETCH_ASSOC);
        if (!$shift) { $pdo->rollBack(); json_error('未找到待复核的班次，或该班次已被他人处理。', 404); }

        $diff = $counted_cash - (float)$shift['expected_cash'];
        $pdo->prepare("UPDATE pos_shifts SET admin_reviewed = 1, reviewed_at = UTC_TIMESTAMP(6), cash_diff = ? WHERE id = ?")
            ->execute([$diff, $shift_id]);

        $pdo->commit();
        json_ok(['shift_id'=>$shift_id, 'cash_diff'=>$diff], '班次复核已完成。');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_error('复核失败：' . $e->getMessage(), 500);
    }
}

/* -------------------- 注册表定义 -------------------- */
return [
    'pos_categories' => [
        'table' => 'pos_categories', 'pk' => 'id', 'soft_delete_col' => 'deleted_at', 'auth_role' => ROLE_PRODUCT_MANAGER,
        'custom_actions' => [
            'get' => 'handle_pos_category_get', 'save' => 'handle_pos_category_save', 'delete' => 'handle_pos_category_delete'
        ],
    ],
    'pos_menu_items' => [
        'table' => 'pos_menu_items', 'pk' => 'id', 'soft_delete_col' => 'deleted_at', 'auth_role' => ROLE_PRODUCT_MANAGER,
        'custom_actions' => [
            'get' => 'handle_menu_item_get', 'save' => 'handle_menu_item_save', 'delete' => 'handle_menu_item_delete',
            'get_with_materials' => 'handle_menu_get_with_materials',
            'get_material_usage_report' => 'handle_menu_get_material_usage_report',
        ],
    ],
    'pos_variants' => [
        'table' => 'pos_variants', 'pk' => 'id', 'soft_delete_col' => 'deleted_at', 'auth_role' => ROLE_PRODUCT_MANAGER,
        'custom_actions' => [
            'save' => 'handle_variant_save',
        ],
    ],
    'pos_addons' => [
        'table' => 'pos_addons', 'pk' => 'id', 'soft_delete_col' => 'deleted_at', 'auth_role' => ROLE_PRODUCT_MANAGER,
        'custom_actions' => [
            'save' => 'handle_addon_save',
        ],
    ],
    'pos_members' => [
        'table' => 'pos_members', 'pk' => 'id', 'soft_delete_col' => 'deleted_at', 'auth_role' => ROLE_SUPER_ADMIN,
        'custom_actions' => [
            'get' => 'handle_member_get',
        ],
    ],
    'pos_promotions' => [
        'table' => 'pos_promotions', 'pk' => 'id', 'soft_delete_col' => 'deleted_at', 'auth_role' => ROLE_PRODUCT_MANAGER,
        'custom_actions' => [ 'get' => 'handle_promo_get', 'save' => 'handle_promo_save', 'delete' => 'handle_promo_delete', ],
    ],
    'pos_settings' => [
        'table' => 'kds_stores', 'pk' => 'id', 'soft_delete_col' => null, 'auth_role' => ROLE_SUPER_ADMIN,
        'custom_actions' => [
            'load' => 'handle_settings_load',
            'save' => 'handle_settings_save',
            'load_sif' => 'handle_sif_load',
            'save_sif' => 'handle_sif_save',
        ],
    ],
    'invoices' => [
        'table' => 'pos_invoices', 'pk' => 'id', 'soft_delete_col' => null, 'auth_role' => ROLE_SUPER_ADMIN,
        'custom_actions' => [ 'cancel' => 'handle_invoice_cancel', 'correct' => 'handle_invoice_correct', ],
    ],
    'shifts' => [
        'table' => 'pos_shifts', 'pk' => 'id', 'soft_delete_col' => null, 'auth_role' => ROLE_SUPER_ADMIN,
        'custom_actions' => [ 'review' => 'handle_shift_review', ],
    ],
];
