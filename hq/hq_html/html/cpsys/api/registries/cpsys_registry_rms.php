<?php
/**
 * Toptea HQ - CPSYS API 注册表 (RMS - Recipe/Stock Management)
 * 注册物料、库存、配方等资源
 * Version: 1.3.2 (R2 Audit Implementation)
 * Date: 2025-11-13
 *
 * [R2] Added:
 * - Injected log_audit_action() into cprms_product_save
 * - Injected log_audit_action() into cprms_product_delete
 * - Added helper cprms_product_get_details_snapshot() for auditing
 *
 * [R2] Added:
 * - Injected log_audit_action() into cprms_material_save
 * - Injected log_audit_action() into cprms_material_delete
 *
 * [R1] Added:
 * - cprms_products_get_list() handler for rms.products.list
 * - cprms_recipes_get() handler for rms.recipes.get
 * - cprms_tags_get_list() handler for rms.tags.list
 * - Registered 'rms_products', 'rms_recipes', 'rms_tags' resources.
 *
 * 关键修复：
 * - [V1.2.4] 修复 cprms_product_get_details 中 'sweets_option_id' 的拼写错误。
 * - [V1.2.4] 修复文件末尾多余的 '}' 语法错误。
 * - [V1.2.4] 为顶层 getMaterialById 兜底函数添加 image_url 字段。
 * - [V1.2.4] 为 cprms_material_save 添加 image_url 清理 (parse_url/basename)。
 */

/* =========================  仅当缺失时的兜底函数  ========================= */
if (!function_exists('getMaterialById')) {
    function getMaterialById(PDO $pdo, int $id): ?array {
        $sql = "
            SELECT
                m.*,
                m.image_url,
                mt_zh.material_name AS name_zh,
                mt_es.material_name AS name_es
            FROM kds_materials m
            LEFT JOIN kds_material_translations mt_zh
                ON mt_zh.material_id = m.id AND mt_zh.language_code = 'zh-CN'
            LEFT JOIN kds_material_translations mt_es
                ON mt_es.material_id = m.id AND mt_es.language_code = 'es-ES'
            WHERE m.id = ? AND m.deleted_at IS NULL
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('getNextAvailableCustomCode')) {
    function getNextAvailableCustomCode(PDO $pdo, string $table, string $column, int $start = 1): string {
        $max = $start - 1;
        try {
            $val = $pdo->query("SELECT MAX(CAST($column AS UNSIGNED)) FROM $table WHERE deleted_at IS NULL")->fetchColumn();
            if ($val !== false && $val !== null) {
                $max = max($max, (int)$val);
            } else {
                $s = $pdo->query("SELECT $column FROM $table ORDER BY $column DESC LIMIT 1")->fetchColumn();
                if (is_string($s) && preg_match('/\d+/', $s, $m)) {
                    $max = max($max, (int)$m[0]);
                }
            }
        } catch (Throwable $e) {
            // 可按需记录：error_log($e->getMessage());
        }
        return (string)($max + 1);
    }
}

// [R1] 确保 kds_helper.php 已加载 (依赖 repo_a/b/c)
require_once realpath(__DIR__ . '/../../../../app/helpers/kds_helper.php');

/* =========================  兜底函数结束  ========================= */

/* ====== 公共换算函数（唯一命名，防冲突） ====== */
if (!function_exists('cprms_get_base_quantity')) {
    function cprms_get_base_quantity(PDO $pdo, int $material_id, float $quantity_input, int $unit_id): float {
        if ($material_id <= 0 || $quantity_input <= 0 || $unit_id <= 0) {
            json_error('物料、数量和单位均为必填项。', 400);
        }

        $material = getMaterialById($pdo, $material_id);
        if (!$material) json_error('找不到指定的物料。', 404);

        if ((int)$material['base_unit_id'] === $unit_id) {
            return $quantity_input; // 基础单位
        }
        if (!empty($material['medium_unit_id']) && (int)$material['medium_unit_id'] === $unit_id) {
            $med_rate = (float)($material['medium_conversion_rate'] ?? 0);
            if ($med_rate <= 0) json_error('该物料的“中级单位”换算率未设置或无效。', 400);
            return $quantity_input * $med_rate;
        }
        if (!empty($material['large_unit_id']) && (int)$material['large_unit_id'] === $unit_id) {
            $med_rate   = (float)($material['medium_conversion_rate'] ?? 0);
            $large_rate = (float)($material['large_conversion_rate'] ?? 0);
            if ($med_rate <= 0 || $large_rate <= 0) {
                json_error('该物料的“中级单位”或“大单位”换算率未设置或无效。', 400);
            }
            return $quantity_input * $large_rate * $med_rate; // 大 -> 中 -> 基础
        }

        json_error('选择的单位与该物料不匹配。', 400);
        return 0.0;
    }
}

/* =========================  Handlers（全部使用 cprms_* 前缀）  ========================= */
/* --- 物料 (kds_materials) --- */
function cprms_material_get(PDO $pdo, array $config, array $input_data): void {
    $id = $_GET['id'] ?? json_error('缺少 id', 400);
    $data = getMaterialById($pdo, (int)$id);
    $data ? json_ok($data) : json_error('未找到物料', 404);
}

function cprms_material_save(PDO $pdo, array $config, array $input_data): void {
    $data = $input_data['data'] ?? json_error('缺少 data', 400);

    $id           = !empty($data['id']) ? (int)$data['id'] : null;
    $code         = trim((string)($data['material_code'] ?? ''));
    $type         = trim((string)($data['material_type'] ?? ''));
    $name_zh      = trim((string)($data['name_zh'] ?? ''));
    $name_es      = trim((string)($data['name_es'] ?? ''));
    $base_unit_id = (int)($data['base_unit_id'] ?? 0);

    // [--- 功能 2 修复: START ---]
    // 清理 image_url，移除 query string 和 fragment
    $image_url_raw = !empty($data['image_url']) ? trim((string)$data['image_url']) : null;
    if ($image_url_raw) {
        // 1. 解析路径，去除 ?x=1 和 #t=2
        $path_component = parse_url($image_url_raw, PHP_URL_PATH);
        // 2. 提取文件名
        $image_url = basename((string)$path_component);
        // 3. 如果清理后文件名为空 (例如输入 '?'), 则设为 null
        if ($image_url === '' || $image_url === '.' || $image_url === '..') {
            $image_url = null;
        }
    } else {
        $image_url = null;
    }
    // [--- 功能 2 修复: END ---]

    $medium_unit_id         = (int)($data['medium_unit_id'] ?? 0) ?: null;
    $medium_conversion_rate = $medium_unit_id ? (float)($data['medium_conversion_rate'] ?? 0) : null;

    $large_unit_id          = (int)($data['large_unit_id'] ?? 0) ?: null;
    $large_conversion_rate  = $large_unit_id ? (float)($data['large_conversion_rate'] ?? 0) : null;

    $expiry_rule_input = (string)($data['expiry_rule_type'] ?? '');
    $expiry_duration   = (int)($data['expiry_duration'] ?? 0);

    if ($expiry_rule_input === '' || $expiry_rule_input === 'END_OF_DAY') {
        $expiry_rule_type = null;
        $expiry_duration  = 0;
    } elseif (in_array($expiry_rule_input, ['HOURS','DAYS'], true)) {
        if ($expiry_duration <= 0) json_error('选择按小时或天计算效期后，必须填写一个大于0的时长。', 400);
        $expiry_rule_type = $expiry_rule_input;
    } else {
        json_error('非法的效期规则。', 400);
    }

    if ($code === '' || $type === '' || $name_zh === '' || $name_es === '' || $base_unit_id <= 0) {
        json_error('编号、类型、双语名称和基础单位为必填项。', 400);
    }
    if ($large_unit_id && !$medium_unit_id) {
        json_error('必须先定义“中级单位”，才能定义“大单位”。', 400);
    }
    if ($medium_unit_id && $medium_conversion_rate !== null && $medium_conversion_rate <= 0) {
        json_error('选择“中级单位”后，其换算率必须是一个大于0的数字。', 400);
    }
    if ($large_unit_id && $large_conversion_rate !== null && $large_conversion_rate <= 0) {
        json_error('选择“大单位”后，其换算率必须是一个大于0的数字。', 400);
    }

    // [R2] 审计：获取变更前数据
    $data_before = null;
    $action_name = 'rms.material.create';
    if ($id) {
        $data_before = getMaterialById($pdo, $id); // 使用包含双语的函数
        $action_name = 'rms.material.update';
    }

    $pdo->beginTransaction();
    try {
        if ($id) {
            $stmt_check = $pdo->prepare("SELECT id FROM kds_materials WHERE material_code = ? AND id != ? AND deleted_at IS NULL");
            $stmt_check->execute([$code, $id]);
            if ($stmt_check->fetch()) json_error('自定义编号 "' . htmlspecialchars($code) . '" 已被另一个有效物料使用。', 409);

            $stmt = $pdo->prepare("
                UPDATE kds_materials SET
                    material_code = ?, material_type = ?, base_unit_id  = ?,
                    medium_unit_id = ?, medium_conversion_rate = ?,
                    large_unit_id  = ?, large_conversion_rate  = ?,
                    expiry_rule_type = ?, expiry_duration = ?,
                    image_url = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $code, $type, $base_unit_id,
                $medium_unit_id, $medium_conversion_rate,
                $large_unit_id,  $large_conversion_rate,
                $expiry_rule_type, $expiry_duration,
                $image_url,
                $id
            ]);

            $stmt_trans = $pdo->prepare("UPDATE kds_material_translations SET material_name=? WHERE material_id=? AND language_code=?");
            $stmt_trans->execute([$name_zh, $id, 'zh-CN']);
            $stmt_trans->execute([$name_es, $id, 'es-ES']);

            $pdo->commit();
            
            // [R2] 审计：写入日志
            $data_after = getMaterialById($pdo, $id);
            log_audit_action($pdo, $action_name, 'kds_materials', $id, $data_before, $data_after);

            json_ok(['id'=>$id], '物料已成功更新！');
        } else {
            $stmt_active = $pdo->prepare("SELECT id FROM kds_materials WHERE material_code=? AND deleted_at IS NULL");
            $stmt_active->execute([$code]);
            if ($stmt_active->fetch()) json_error('自定义编号 "' . htmlspecialchars($code) . '" 已被一个有效物料使用。', 409);

            $stmt_deleted = $pdo->prepare("SELECT id FROM kds_materials WHERE material_code=? AND deleted_at IS NOT NULL");
            $stmt_deleted->execute([$code]);
            $reclaim = $stmt_deleted->fetch(PDO::FETCH_ASSOC);

            if ($reclaim) {
                $id = (int)$reclaim['id'];
                $stmt = $pdo->prepare("
                    UPDATE kds_materials SET
                        material_type = ?,
                        base_unit_id  = ?,
                        medium_unit_id = ?, medium_conversion_rate = ?,
                        large_unit_id  = ?, large_conversion_rate  = ?,
                        expiry_rule_type = ?, expiry_duration = ?,
                        image_url = ?,
                        deleted_at = NULL
                    WHERE id = ?
                ");
                $stmt->execute([
                    $type, $base_unit_id,
                    $medium_unit_id, $medium_conversion_rate,
                    $large_unit_id,  $large_conversion_rate,
                    $expiry_rule_type, $expiry_duration,
                    $image_url,
                    $id
                ]);
                $stmt_trans = $pdo->prepare("UPDATE kds_material_translations SET material_name=? WHERE material_id=? AND language_code=?");
                $stmt_trans->execute([$name_zh, $id, 'zh-CN']);
                $stmt_trans->execute([$name_es, $id, 'es-ES']);
                $msg = '已从回收状态恢复该物料。';
                $action_name = 'rms.material.restore'; // [R2] 恢复也算一种更新
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO kds_materials
                        (material_code, material_type, base_unit_id,
                         medium_unit_id, medium_conversion_rate,
                         large_unit_id,  large_conversion_rate,
                         expiry_rule_type, expiry_duration, image_url)
                    VALUES (?,?,?,?,?,?,?,?,?,?)
                ");
                $stmt->execute([
                    $code, $type, $base_unit_id,
                    $medium_unit_id, $medium_conversion_rate,
                    $large_unit_id,  $large_conversion_rate,
                    $expiry_rule_type, $expiry_duration,
                    $image_url
                ]);
                $id = (int)$pdo->lastInsertId();
                $stmt_trans = $pdo->prepare("INSERT INTO kds_material_translations (material_id, language_code, material_name) VALUES (?,?,?)");
                $stmt_trans->execute([$id, 'zh-CN', $name_zh]);
                $stmt_trans->execute([$id, 'es-ES', $name_es]);
                $msg = '新物料已成功创建！';
                // $action_name 默认为 'rms.material.create'
            }

            $pdo->commit();
            
            // [R2] 审计：写入日志
            $data_after = getMaterialById($pdo, $id);
            log_audit_action($pdo, $action_name, 'kds_materials', $id, $data_before, $data_after);

            json_ok(['id'=>$id], $msg);
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        json_error('数据库操作失败', 500, ['debug' => $e->getMessage()]);
    }
}

function cprms_material_delete(PDO $pdo, array $config, array $input_data): void {
    $id = $input_data['id'] ?? json_error('缺少 id', 400);
    $id = (int)$id;

    // [R2] 审计：获取变更前数据
    $data_before = getMaterialById($pdo, $id);
    if (!$data_before) {
        json_error('未找到要删除的物料。', 404);
    }

    $stmt = $pdo->prepare("UPDATE kds_materials SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$id]);

    // [R2] 审计：写入日志
    log_audit_action($pdo, 'rms.material.delete', 'kds_materials', $id, $data_before, null);

    json_ok(null, '物料已成功删除。');
}

function cprms_material_get_next_code(PDO $pdo, array $config, array $input_data): void {
    $next_code = getNextAvailableCustomCode($pdo, 'kds_materials', 'material_code');
    json_ok(['next_code' => $next_code], '下一个可用编号已找到。');
}

/* --- 库存 (stock_handler) --- */
function cprms_stock_actions(PDO $pdo, array $config, array $input_data): void {
    $action = $input_data['action'] ?? $_GET['act'] ?? null;
    $data = $input_data['data'] ?? null;

    if ($action === 'add_warehouse_stock') {
        $material_id = (int)($data['material_id'] ?? 0);
        $quantity_to_add = (float)($data['quantity'] ?? 0);
        $unit_id = (int)($data['unit_id'] ?? 0);

        $final_quantity_to_add = cprms_get_base_quantity($pdo, $material_id, $quantity_to_add, $unit_id);

        $pdo->beginTransaction();
        $sql = "INSERT INTO expsys_warehouse_stock (material_id, quantity)
                VALUES (:material_id, :quantity)
                ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':material_id' => $material_id, ':quantity' => $final_quantity_to_add]);
        $pdo->commit();
        json_ok(null, '总仓入库成功！');
    } elseif ($action === 'allocate_to_store') {
        $store_id = (int)($data['store_id'] ?? 0);
        $material_id = (int)($data['material_id'] ?? 0);
        $quantity_to_allocate = (float)($data['quantity'] ?? 0);
        $unit_id = (int)($data['unit_id'] ?? 0);

        $final_quantity_to_allocate = cprms_get_base_quantity($pdo, $material_id, $quantity_to_allocate, $unit_id);

        $pdo->beginTransaction();
        $stmt_warehouse = $pdo->prepare("INSERT INTO expsys_warehouse_stock (material_id, quantity)
                                         VALUES (?, ?)
                                         ON DUPLICATE KEY UPDATE quantity = quantity - ?");
        $stmt_warehouse->execute([$material_id, -$final_quantity_to_allocate, $final_quantity_to_allocate]);

        $stmt_store = $pdo->prepare("INSERT INTO expsys_store_stock (store_id, material_id, quantity)
                                     VALUES (?, ?, ?)
                                     ON DUPLICATE KEY UPDATE quantity = quantity + ?");
        $stmt_store->execute([$store_id, $material_id, $final_quantity_to_allocate, $final_quantity_to_allocate]);
        $pdo->commit();
        json_ok(null, '库存调拨成功！');
    } else {
        json_error("未知的库存动作: {$action}", 400);
    }
}

/* --- RMS 全局规则 (kds_global_adjustment_rules) --- */
function cprms_global_rule_get(PDO $pdo, array $config, array $input_data): void {
    $id = $_GET['id'] ?? json_error('缺少 id', 400);
    $stmt = $pdo->prepare("SELECT * FROM kds_global_adjustment_rules WHERE id = ?");
    $stmt->execute([(int)$id]);
    $rule = $stmt->fetch(PDO::FETCH_ASSOC);
    $rule ? json_ok($rule, '规则已加载。') : json_error('未找到规则。', 404);
}

function cprms_global_rule_save(PDO $pdo, array $config, array $input_data): void {
    $data = $input_data['data'] ?? json_error('缺少 data', 400);
    $id = !empty($data['id']) ? (int)$data['id'] : null;
    $nullIfEmpty = fn($v) => ($v === '' || $v === null) ? null : $v;
    $params = [
        ':rule_name' => trim($data['rule_name'] ?? ''),
        ':priority' => (int)($data['priority'] ?? 100),
        ':is_active' => (int)($data['is_active'] ?? 0),
        ':cond_cup_id' => $nullIfEmpty($data['cond_cup_id']),
        ':cond_ice_id' => $nullIfEmpty($data['cond_ice_id']),
        ':cond_sweet_id' => $nullIfEmpty($data['cond_sweet_id']),
        ':cond_material_id' => $nullIfEmpty($data['cond_material_id']),
        ':cond_base_gt' => $nullIfEmpty($data['cond_base_gt']),
        ':cond_base_lte' => $nullIfEmpty($data['cond_base_lte']),
        ':action_type' => $data['action_type'] ?? '',
        ':action_material_id' => (int)($data['action_material_id'] ?? 0),
        ':action_value' => (float)($data['action_value'] ?? 0),
        ':action_unit_id' => $nullIfEmpty($data['action_unit_id']),
    ];
    if (empty($params[':rule_name']) || empty($params[':action_type']) || $params[':action_material_id'] === 0)
        json_error('规则名称、动作类型和目标物料为必填项。', 400);
    if ($params[':action_type'] === 'ADD_MATERIAL' && empty($params[':action_unit_id']))
        json_error('当动作类型为“添加物料”时，必须指定单位。', 400);

    if ($id) {
        $params[':id'] = $id;
        $sql = "UPDATE kds_global_adjustment_rules SET
                    rule_name = :rule_name, priority = :priority, is_active = :is_active,
                    cond_cup_id = :cond_cup_id, cond_ice_id = :cond_ice_id, cond_sweet_id = :cond_sweet_id,
                    cond_material_id = :cond_material_id, cond_base_gt = :cond_base_gt, cond_base_lte = :cond_base_lte,
                    action_type = :action_type, action_material_id = :action_material_id,
                    action_value = :action_value, action_unit_id = :action_unit_id
                WHERE id = :id";
        $message = '全局规则已更新。';
    } else {
        $sql = "INSERT INTO kds_global_adjustment_rules
                    (rule_name, priority, is_active, cond_cup_id, cond_ice_id, cond_sweet_id,
                     cond_material_id, cond_base_gt, cond_base_lte, action_type,
                     action_material_id, action_value, action_unit_id)
                VALUES
                    (:rule_name, :priority, :is_active, :cond_cup_id, :cond_ice_id, :cond_sweet_id,
                     :cond_material_id, :cond_base_gt, :cond_base_lte, :action_type,
                     :action_material_id, :action_value, :action_unit_id)";
        $message = '新全局规则已创建。';
    }
    $pdo->prepare($sql)->execute($params);
    json_ok(['id' => $id ?? $pdo->lastInsertId()], $message);
}

function cprms_global_rule_delete(PDO $pdo, array $config, array $input_data): void {
    $id = $input_data['id'] ?? json_error('缺少 id', 400);
    $stmt = $pdo->prepare("DELETE FROM kds_global_adjustment_rules WHERE id = ?");
    $stmt->execute([(int)$id]);
    json_ok(null, '全局规则已删除。');
}

/* --- RMS 产品 (kds_products) --- */
function cprms_product_get_details(PDO $pdo, array $config, array $input_data): void {
    $id = $_GET['id'] ?? json_error('无效的产品ID。', 400);
    $productId = (int)$id;

    $stmt = $pdo->prepare("
        SELECT p.id, p.product_code, p.status_id, p.is_active,
               COALESCE(tzh.product_name,'') AS name_zh,
               COALESCE(tes.product_name,'') AS name_es
        FROM kds_products p
        LEFT JOIN kds_product_translations tzh ON tzh.product_id=p.id AND tzh.language_code='zh-CN'
        LEFT JOIN kds_product_translations tes ON tes.product_id=p.id AND tes.language_code='es-ES'
        WHERE p.id=? AND p.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$productId]);
    $base = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$base) json_error('未找到产品。', 404);

    $stmt = $pdo->prepare("
        SELECT id, material_id, unit_id, quantity, step_category, sort_order
        FROM kds_product_recipes
        WHERE product_id=?
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->execute([$productId]);
    $base_recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT id, material_id, unit_id, quantity, step_category,
               cup_id, sweetness_option_id, ice_option_id
        FROM kds_recipe_adjustments
        WHERE product_id=?
        ORDER BY id ASC
    ");
    $stmt->execute([$productId]);
    $raw_adjustments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $grouped = [];
    foreach ($raw_adjustments as $row) {
        $key = ($row['cup_id'] ?? 'null') . '-' . ($row['sweetness_option_id'] ?? 'null') . '-' . ($row['ice_option_id'] ?? 'null');
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'cup_id' => $row['cup_id'] !== null ? (int)$row['cup_id'] : null,
                'sweetness_option_id' => $row['sweetness_option_id'] !== null ? (int)$row['sweetness_option_id'] : null,
                'ice_option_id' => $row['ice_option_id'] !== null ? (int)$row['ice_option_id'] : null,
                'overrides' => []
            ];
        }
        $grouped[$key]['overrides'][] = [
            'material_id'   => (int)$row['material_id'],
            'quantity'      => (float)$row['quantity'],
            'unit_id'       => (int)$row['unit_id'],
            'step_category' => $row['step_category'] ?? 'base',
        ];
    }
    $adjustments = array_values($grouped);

    $stmt_sweet = $pdo->prepare("SELECT sweetness_option_id FROM kds_product_sweetness_options WHERE product_id=?");
    $stmt_sweet->execute([$productId]);
    $allowed_sweetness_ids = array_filter(
        array_map('intval', $stmt_sweet->fetchAll(PDO::FETCH_COLUMN)),
        fn($sid) => $sid > 0
    );

    $stmt_ice = $pdo->prepare("SELECT ice_option_id FROM kds_product_ice_options WHERE product_id=?");
    $stmt_ice->execute([$productId]);
    $allowed_ice_ids = array_filter(
        array_map('intval', $stmt_ice->fetchAll(PDO::FETCH_COLUMN)),
        fn($iid) => $iid > 0
    );

    $response = $base;
    $response['base_recipes'] = $base_recipes;
    $response['adjustments']  = $adjustments;
    $response['allowed_sweetness_ids'] = $allowed_sweetness_ids;
    $response['allowed_ice_ids'] = $allowed_ice_ids;

    json_ok($response, '产品详情加载成功。');
}

function cprms_product_get_next_code(PDO $pdo, array $config, array $input_data): void {
    $next = getNextAvailableCustomCode($pdo, 'kds_products', 'product_code', 101);
    json_ok(['next_code' => $next]);
}

/**
 * [R2] 内部助手：cprms_product_get_details 的非 JSON 输出版本，供审计使用
 */
function cprms_product_get_details_snapshot(PDO $pdo, int $productId): ?array {
    $stmt = $pdo->prepare("
        SELECT p.id, p.product_code, p.status_id, p.is_active,
               COALESCE(tzh.product_name,'') AS name_zh,
               COALESCE(tes.product_name,'') AS name_es
        FROM kds_products p
        LEFT JOIN kds_product_translations tzh ON tzh.product_id=p.id AND tzh.language_code='zh-CN'
        LEFT JOIN kds_product_translations tes ON tes.product_id=p.id AND tes.language_code='es-ES'
        WHERE p.id=? AND p.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$productId]);
    $base = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$base) return null;

    $stmt_l1 = $pdo->prepare("SELECT * FROM kds_product_recipes WHERE product_id=?");
    $stmt_l1->execute([$productId]);
    $base['base_recipes'] = $stmt_l1->fetchAll(PDO::FETCH_ASSOC);

    $stmt_l3 = $pdo->prepare("SELECT * FROM kds_recipe_adjustments WHERE product_id=?");
    $stmt_l3->execute([$productId]);
    $base['adjustments_raw'] = $stmt_l3->fetchAll(PDO::FETCH_ASSOC);

    $stmt_sweet = $pdo->prepare("SELECT sweetness_option_id FROM kds_product_sweetness_options WHERE product_id=?");
    $stmt_sweet->execute([$productId]);
    $base['allowed_sweetness_ids'] = $stmt_sweet->fetchAll(PDO::FETCH_COLUMN);

    $stmt_ice = $pdo->prepare("SELECT ice_option_id FROM kds_product_ice_options WHERE product_id=?");
    $stmt_ice->execute([$productId]);
    $base['allowed_ice_ids'] = $stmt_ice->fetchAll(PDO::FETCH_COLUMN);
    
    return $base;
}

function cprms_product_save(PDO $pdo, array $config, array $input_data): void {
    $product = $input_data['product'] ?? json_error('无效的产品数据。', 400);
    $productId   = isset($product['id']) ? (int)$product['id'] : 0;

    // [R2] 审计
    $data_before = null;
    $action_name = 'rms.recipe.create';
    if ($productId > 0) {
        $data_before = cprms_product_get_details_snapshot($pdo, $productId);
        $action_name = 'rms.recipe.update';
    }

    $pdo->beginTransaction();
    try {
        $productCode = trim((string)($product['product_code'] ?? ''));
        $statusId    = (int)($product['status_id'] ?? 1);

        if ($productId > 0) {
            $stmt = $pdo->prepare("UPDATE kds_products SET product_code=?, status_id=? WHERE id=?");
            $stmt->execute([$productCode, $statusId, $productId]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM kds_products WHERE product_code=? AND deleted_at IS NULL");
            $stmt->execute([$productCode]);
            if ($stmt->fetchColumn()) { $pdo->rollBack(); json_error('产品编码已存在：'.$productCode, 409); }
            $stmt = $pdo->prepare("INSERT INTO kds_products (product_code, status_id, is_active) VALUES (?, ?, 1)");
            $stmt->execute([$productCode, $statusId]);
            $productId = (int)$pdo->lastInsertId();
        }

        $nameZh = trim((string)($product['name_zh'] ?? ''));
        $nameEs = trim((string)($product['name_es'] ?? ''));
        $qSel = $pdo->prepare("SELECT id FROM kds_product_translations WHERE product_id=? AND language_code=?");
        foreach ([['zh-CN',$nameZh], ['es-ES',$nameEs]] as [$lang,$name]) {
            $qSel->execute([$productId,$lang]);
            $tid = $qSel->fetchColumn();
            if ($tid) { $pdo->prepare("UPDATE kds_product_translations SET product_name=? WHERE id=?")->execute([$name,$tid]); }
            else { $pdo->prepare("INSERT INTO kds_product_translations (product_id, language_code, product_name) VALUES (?,?,?)")->execute([$productId,$lang,$name]); }
        }

        $allowedSweet = array_values(array_unique(array_map('intval', $product['allowed_sweetness_ids'] ?? [])));
        $allowedIce   = array_values(array_unique(array_map('intval', $product['allowed_ice_ids'] ?? [])));

        $pdo->prepare("DELETE FROM kds_product_sweetness_options WHERE product_id=?")->execute([$productId]);
        if (!empty($allowedSweet)) {
            $ins = $pdo->prepare("INSERT INTO kds_product_sweetness_options (product_id, sweetness_option_id) VALUES (?,?)");
            foreach ($allowedSweet as $sid) { if ($sid > 0) $ins->execute([$productId, $sid]); }
        } else {
            $pdo->prepare("INSERT INTO kds_product_sweetness_options (product_id, sweetness_option_id) VALUES (?,0)")->execute([$productId]);
        }

        $pdo->prepare("DELETE FROM kds_product_ice_options WHERE product_id=?")->execute([$productId]);
        if (!empty($allowedIce)) {
            $ins = $pdo->prepare("INSERT INTO kds_product_ice_options (product_id, ice_option_id) VALUES (?,?)");
            foreach ($allowedIce as $iid) { if ($iid > 0) $ins->execute([$productId, $iid]); }
        } else {
            $pdo->prepare("INSERT INTO kds_product_ice_options (product_id, ice_option_id) VALUES (?,0)")->execute([$productId]);
        }

        $base = $product['base_recipes'] ?? [];
        $pdo->prepare("DELETE FROM kds_product_recipes WHERE product_id=?")->execute([$productId]);
        if ($base) {
            $ins = $pdo->prepare("INSERT INTO kds_product_recipes (product_id, material_id, unit_id, quantity, step_category, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
            $sort = 1;
            foreach ($base as $row) {
                $ins->execute([
                    $productId,
                    (int)($row['material_id'] ?? 0),
                    (int)($row['unit_id'] ?? 0),
                    (float)($row['quantity'] ?? 0),
                    (string)($row['step_category'] ?? 'base'),
                    $sort++
                ]);
            }
        }

        $adjInput = $product['adjustments'] ?? [];
        $pdo->prepare("DELETE FROM kds_recipe_adjustments WHERE product_id=?")->execute([$productId]);
        if ($adjInput) {
            $ins = $pdo->prepare("INSERT INTO kds_recipe_adjustments (product_id, material_id, unit_id, quantity, step_category, cup_id, sweetness_option_id, ice_option_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($adjInput as $ov) {
                $ins->execute([
                    $productId,
                    (int)($ov['material_id'] ?? 0),
                    (int)($ov['unit_id'] ?? 0),
                    (float)($ov['quantity'] ?? 0),
                    (string)($ov['step_category'] ?? 'base'),
                    isset($ov['cup_id']) ? (int)$ov['cup_id'] : null,
                    isset($ov['sweetness_option_id']) ? (int)$ov['sweetness_option_id'] : null,
                    isset($ov['ice_option_id']) ? (int)$ov['ice_option_id'] : null,
                ]);
            }
        }

        $pdo->commit();
        
        // [R2] 审计：写入日志
        $data_after = cprms_product_get_details_snapshot($pdo, $productId);
        log_audit_action($pdo, $action_name, 'kds_products', $productId, $data_before, $data_after);

        json_ok(['id' => $productId], '产品数据已保存。');
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_error('保存失败: '.$e->getMessage(), 500);
    }
}

function cprms_product_delete(PDO $pdo, array $config, array $input_data): void {
    $id = $input_data['id'] ?? json_error('无效的产品ID。', 400);
    $id = (int)$id;

    // [R2] 审计：获取变更前数据
    $data_before = cprms_product_get_details_snapshot($pdo, $id);
    if (!$data_before) {
        json_error('未找到要删除的产品。', 404);
    }

    $stmt = $pdo->prepare("UPDATE kds_products SET is_active=0, deleted_at=NOW() WHERE id=?");
    $stmt->execute([$id]);

    // [R2] 审计：写入日志
    log_audit_action($pdo, 'rms.recipe.delete', 'kds_products', $id, $data_before, null);

    json_ok(null, '产品已删除。');
}


// --- [R1] START: 新增 rms.products.list 处理器 ---
/**
 * 处理器: [R1] rms.products.list
 * 目标: 供 POS 使用，获取产品、变体、标签。
 */
function cprms_products_get_list(PDO $pdo, array $config, array $input_data): void {
    // 确保 kds_helper.php 及其依赖 (kds_repo_b.php) 已被加载
    if (!function_exists('getAllMenuItems')) {
        json_error('依赖函数 getAllMenuItems 未加载。', 500);
    }

    // 1. 获取所有 POS 菜单项 (已包含分类信息)
    // 注意：kds_repo_b.php 中的 getAllMenuItems 包含已删除项，此处需过滤
    $all_menu_items = getAllMenuItems($pdo, null); // store_id=null 表示获取所有
    
    // 2. 获取所有 POS 变体
    $sql_variants = "
        SELECT 
            v.*,
            p.product_code AS kds_product_code,
            c.cup_code
        FROM pos_item_variants v
        JOIN pos_menu_items mi ON v.menu_item_id = mi.id
        LEFT JOIN kds_products p ON mi.product_code = p.product_code AND p.deleted_at IS NULL
        LEFT JOIN kds_cups c ON v.cup_id = c.id AND c.deleted_at IS NULL
        WHERE v.deleted_at IS NULL AND mi.deleted_at IS NULL
    ";
    $variants = $pdo->query($sql_variants)->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);
    
    // 3. 获取所有 POS 商品标签映射
    $sql_tags = "
        SELECT 
            ptm.product_id,
            t.tag_code
        FROM pos_product_tag_map ptm
        JOIN pos_tags t ON ptm.tag_id = t.tag_id
    ";
    $tags = $pdo->query($sql_tags)->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);

    // 4. 组装结果
    $results = [];
    foreach ($all_menu_items as $item) {
        // [R1] 过滤掉已软删除的
        if ($item['deleted_at'] !== null) {
            continue;
        }
        
        $item_id = $item['id'];
        
        // 组装变体
        $item_variants = [];
        if (isset($variants[$item_id])) {
            foreach ($variants[$item_id] as $v) {
                $item_variants[] = [
                    'variant_id' => $v['id'],
                    'name_zh' => $v['variant_name_zh'],
                    'name_es' => $v['variant_name_es'],
                    'price' => $v['price_eur'],
                    'is_default' => $v['is_default'],
                    'cup_code' => $v['cup_code'],
                    'kds_product_code' => $v['kds_product_code'] // KDS 配方 P-Code
                ];
            }
        }
        
        // 组装标签
        $item_tags = [];
        if (isset($tags[$item_id])) {
            $item_tags = array_column($tags[$item_id], 'tag_code');
        }

        $results[] = [
            'id' => $item['id'],
            'name_zh' => $item['name_zh'],
            'name_es' => $item['name_es'],
            'category_zh' => $item['category_name_zh'],
            'category_es' => $item['category_name_es'],
            'is_active' => $item['is_active'],
            'sort_order' => $item['sort_order'],
            'image_url' => $item['image_url'],
            'tags' => $item_tags,
            'variants' => $item_variants,
        ];
    }

    json_ok($results, 'POS 产品列表加载成功。');
}
// --- [R1] END ---

// --- [R1] START: 新增 rms.recipes.get 处理器 ---
/**
 * 处理器: [R1] rms.recipes.get
 * 目标: 供 POS 使用，根据 KDS P-Code (product_code) 获取配方详情
 */
function cprms_recipes_get(PDO $pdo, array $config, array $input_data): void {
    $product_code = $_GET['code'] ?? null;
    if (empty($product_code)) {
        json_error('缺少 "code" (KDS Product Code) 参数。', 400);
    }

    // 确保 kds_helper.php 及其依赖 (kds_repo_b.php) 已被加载
    if (!function_exists('getRecipeByProductCode')) {
        json_error('依赖函数 getRecipeByProductCode 未加载。', 500);
    }

    $data = getRecipeByProductCode($pdo, $product_code);
    
    if ($data) {
        json_ok($data, '配方加载成功。');
    } else {
        json_error("未找到 Product Code 为 '{$product_code}' 的配方。", 404);
    }
}
// --- [R1] END ---

// --- [R1] START: 新增 rms.tags.list 处理器 ---
/**
 * 处理器: [R1] rms.tags.list
 * 目标: 供 POS 使用，获取所有标签定义。
 */
function cprms_tags_get_list(PDO $pdo, array $config, array $input_data): void {
    // 确保 kds_helper.php 及其依赖 (kds_repo_c.php) 已被加载
    if (!function_exists('getAllPosTags')) {
        json_error('依赖函数 getAllPosTags 未加载。', 500);
    }
    
    $tags = getAllPosTags($pdo);
    json_ok($tags, 'POS 标签列表加载成功。');
}
// --- [R1] END ---


/* =========================  注册表  ========================= */
return [

    'materials' => [
        'table' => 'kds_materials',
        'pk' => 'id',
        'soft_delete_col' => 'deleted_at',
        'auth_role' => ROLE_PRODUCT_MANAGER,
        'custom_actions' => [
            'get'            => 'cprms_material_get',
            'save'           => 'cprms_material_save',
            'delete'         => 'cprms_material_delete',
            'get_next_code'  => 'cprms_material_get_next_code',
        ],
    ],

    'stock' => [
        'table' => 'expsys_warehouse_stock', // 占位
        'auth_role' => ROLE_SUPER_ADMIN,
        'custom_actions' => [
            'add_warehouse_stock' => 'cprms_stock_actions',
            'allocate_to_store'   => 'cprms_stock_actions',
        ],
    ],

    'rms_global_rules' => [
        'table' => 'kds_global_adjustment_rules',
        'pk' => 'id',
        'soft_delete_col' => null, // 硬删除
        'auth_role' => ROLE_SUPER_ADMIN,
        'custom_actions' => [
            'get'    => 'cprms_global_rule_get',
            'save'   => 'cprms_global_rule_save',
            'delete' => 'cprms_global_rule_delete',
        ],
    ],

    'rms_products' => [
        'table' => 'kds_products',
        'pk' => 'id',
        'soft_delete_col' => 'deleted_at',
        'auth_role' => ROLE_PRODUCT_MANAGER,
        'custom_actions' => [
            // [R1] 新增
            'get_list'              => 'cprms_products_get_list',
            // (旧)
            'get_product_details'   => 'cprms_product_get_details',
            'get_next_product_code' => 'cprms_product_get_next_code',
            'save_product'          => 'cprms_product_save',
            'delete_product'        => 'cprms_product_delete',
        ],
    ],

    // [R1] 新增 rms_recipes 资源
    'rms_recipes' => [
        'table' => 'kds_product_recipes', // 占位
        'pk' => 'id',
        'auth_role' => ROLE_PRODUCT_MANAGER, // POS/KDS 也可访问 (只读)
        'custom_actions' => [
            'get' => 'cprms_recipes_get', // 按 code 获取
        ],
    ],

    // [R1] 新增 rms_tags 资源
    'rms_tags' => [
        'table' => 'pos_tags', // 占位
        'pk' => 'tag_id',
        'auth_role' => ROLE_PRODUCT_MANAGER, // POS/KDS 也可访问 (只读)
        'custom_actions' => [
            'get_list' => 'cprms_tags_get_list',
        ],
    ],

];