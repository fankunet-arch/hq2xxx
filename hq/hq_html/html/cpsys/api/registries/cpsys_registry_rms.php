<?php
/**
 * Toptea HQ - CPSYS API 注册表 (RMS - Recipe/Stock Management)
 * 注册物料、库存、配方等资源
 * Version: 1.2.4 (Fix: Typo, Syntax, Image URL)
 * Date: 2025-11-07
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
            }

            $pdo->commit();
            json_ok(['id'=>$id], $msg);
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        json_error('数据库操作失败', 500, ['debug' => $e->getMessage()]);
    }
}

function cprms_material_delete(PDO $pdo, array $config, array $input_data): void {
    $id = $input_data['id'] ?? json_error('缺少 id', 400);
    $stmt = $pdo->prepare("UPDATE kds_materials SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([(int)$id]);
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

function cprms_product_delete(PDO $pdo, array $config, array $input_data): void {
    $id = $input_data['id'] ?? json_error('无效的产品ID。', 400);
    $stmt = $pdo->prepare("UPDATE kds_products SET is_active=0, deleted_at=NOW() WHERE id=?");
    $stmt->execute([(int)$id]);
    json_ok(null, '产品已删除。');
}

function cprms_product_save(PDO $pdo, array $config, array $input_data): void {
    $product = $input_data['product'] ?? json_error('无效的产品数据。', 400);
    $pdo->beginTransaction();
    try {
        $productId   = isset($product['id']) ? (int)$product['id'] : 0;
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
        json_ok(['id' => $productId], '产品数据已保存。');
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_error('保存失败: '.$e->getMessage(), 500);
    }
}

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
            'get_product_details'   => 'cprms_product_get_details',
            'get_next_product_code' => 'cprms_product_get_next_code',
            'save_product'          => 'cprms_product_save',
            'delete_product'        => 'cprms_product_delete',
        ],
    ],

];