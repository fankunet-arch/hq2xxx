<?php
/**
 * Toptea HQ - CPSYS API 注册表 (BMS - POS Management)
 * 注册 POS 菜单、商品、会员、促销等资源
 * Version: 1.3.004 (A2 UTC Precision Fix)
 * Date: 2025-11-11
 *
 * [A2.2 UTC FIX]:
 * - 修复了 A2 阶段的严重 Bug：
 * - `deleted_at` 字段 (如 pos_categories, pos_menu_items 等) 和 pos_shifts.updated_at
 * 是 `TIMESTAMP` (0精度)，不是 `TIMESTAMP(6)`。
 * - 之前使用 .u (毫秒) 格式 (e.g., 'Y-m-d H:i:s.u') 导致 UPDATE 失败，deleted_at 保持 NULL。
 * - 现为这些字段统一使用 `Y-m-d H:i:s` (0精度) 格式。
 * - 仅在 `pos_invoices.issued_at` (timestamp(6)) 字段上保留 .u (毫秒) 格式。
 *
 * [A2 UTC SYNC]:
 * - 引入 datetime_helper.php (utc_now())
 *
 * [R2.1] Added: pos_tags resource and handlers (handle_pos_tag_*)
 * [R2.2] Updated: handle_addon_get/save to include tag maps
 * [R2.4] Added: topup_orders resource and handler (handle_topup_order_review)
 */

require_once realpath(__DIR__ . '/../../../../app/helpers/kds_helper.php');
require_once realpath(__DIR__ . '/../../../../app/helpers/auth_helper.php');
// [A2 UTC SYNC] 引入时间助手
require_once realpath(__DIR__ . '/../../../../app/helpers/datetime_helper.php');


// --- 处理器: POS 分类 (pos_categories) ---
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
    if (empty($code) || empty($name_zh) || empty($name_es)) json_error('分类编码和双语名称均为必填项。', 400);
    $sql_check = "SELECT id FROM pos_categories WHERE category_code = ? AND deleted_at IS NULL" . ($id ? " AND id != ?" : "");
    $params_check = $id ? [$code, $id] : [$code];
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute($params_check);
    if ($stmt_check->fetch()) json_error('分类编码 "' . htmlspecialchars($code) . '" 已被使用。', 409);

    if ($id) {
        $stmt = $pdo->prepare("UPDATE pos_categories SET category_code = ?, name_zh = ?, name_es = ?, sort_order = ? WHERE id = ?");
        $stmt->execute([$code, $name_zh, $name_es, $sort, $id]);
        json_ok(['id' => $id], '分类已成功更新！');
    } else {
        $stmt = $pdo->prepare("INSERT INTO pos_categories (category_code, name_zh, name_es, sort_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([$code, $name_zh, $name_es, $sort]);
        json_ok(['id' => (int)$pdo->lastInsertId()], '新分类已成功创建！');
    }
}
function handle_pos_category_delete(PDO $pdo, array $config, array $input_data): void {
    $id = $input_data['id'] ?? json_error('缺少 id', 400);
    // [A2.2 UTC FIX] 
    // pos_categories.deleted_at 是 timestamp(0)。必须使用 'Y-m-d H:i:s'
    $now_utc_str = utc_now()->format('Y-m-d H:i:s');
    $stmt = $pdo->prepare("UPDATE pos_categories SET deleted_at = ? WHERE id = ?");
    $stmt->execute([$now_utc_str, (int)$id]);
    json_ok(null, '分类已成功删除。');
}

// --- 处理器: POS 菜单商品 (pos_menu_items) ---
function handle_menu_item_get(PDO $pdo, array $config, array $input_data): void {
    $id = $_GET['id'] ?? json_error('缺少 id', 400);
    $stmt = $pdo->prepare("SELECT * FROM pos_menu_items WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([(int)$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // [R2.2] 获取关联的 tags
    if ($data) {
        $data['tag_ids'] = getProductTagIds($pdo, (int)$id);
    }
    
    $data ? json_ok($data) : json_error('未找到商品', 404);
}
function handle_menu_item_save(PDO $pdo, array $config, array $input_data): void {
    $data = $input_data['data'] ?? json_error('缺少 data', 400);
    $id = $data['id'] ? (int)$data['id'] : null;
    $params = [
        ':name_zh' => trim($data['name_zh']),
        ':name_es' => trim($data['name_es']),
        ':pos_category_id' => (int)$data['pos_category_id'],
        ':description_zh' => trim($data['description_zh']) ?: null,
        ':description_es' => trim($data['description_es']) ?: null,
        ':sort_order' => (int)($data['sort_order'] ?? 99),
        ':is_active' => (int)($data['is_active'] ?? 0)
    ];
    if (empty($params[':name_zh']) || empty($params[':name_es']) || empty($params[':pos_category_id'])) json_error('双语名称和POS分类均为必填项。', 400);
    
    // [R2.2] 标签
    $tag_ids = $data['tag_ids'] ?? [];
    
    $pdo->beginTransaction();
    try {
        if ($id) {
            $params[':id'] = $id;
            $sql = "UPDATE pos_menu_items SET name_zh = :name_zh, name_es = :name_es, pos_category_id = :pos_category_id, description_zh = :description_zh, description_es = :description_es, sort_order = :sort_order, is_active = :is_active WHERE id = :id";
            $pdo->prepare($sql)->execute($params);
        } else {
            $sql = "INSERT INTO pos_menu_items (name_zh, name_es, pos_category_id, description_zh, description_es, sort_order, is_active) VALUES (:name_zh, :name_es, :pos_category_id, :description_zh, :description_es, :sort_order, :is_active)";
            $pdo->prepare($sql)->execute($params);
            $id = (int)$pdo->lastInsertId();
        }

        // [R2.2] START: 更新 Tag 关联
        $stmt_del_tags = $pdo->prepare("DELETE FROM pos_product_tag_map WHERE product_id = ?");
        $stmt_del_tags->execute([$id]);

        if (!empty($tag_ids)) {
            $sql_ins_tags = "INSERT INTO pos_product_tag_map (product_id, tag_id) VALUES (?, ?)";
            $stmt_ins_tags = $pdo->prepare($sql_ins_tags);
            foreach ($tag_ids as $tag_id) {
                if (filter_var($tag_id, FILTER_VALIDATE_INT)) {
                    $stmt_ins_tags->execute([$id, (int)$tag_id]);
                }
            }
        }
        // [R2.2] END: 更新 Tag 关联
        
        $pdo->commit();
        json_ok(['id' => $id], $id ? '商品信息已成功更新！' : '新商品已成功创建！');
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_error('保存商品时出错。', 500, ['debug' => $e->getMessage()]);
    }
}
function handle_menu_item_delete(PDO $pdo, array $config, array $input_data): void {
    $id = $input_data['id'] ?? json_error('缺少 id', 400);
    $id = (int)$id;

    // [A2.2 UTC FIX] 
    // pos_menu_items.deleted_at 和 pos_item_variants.deleted_at 都是 timestamp(0)。
    $now_utc_str = utc_now()->format('Y-m-d H:i:s');
    
    $pdo->beginTransaction();
    $stmt_variants = $pdo->prepare("UPDATE pos_item_variants SET deleted_at = ? WHERE menu_item_id = ?");
    $stmt_variants->execute([$now_utc_str, $id]);
    $stmt_item = $pdo->prepare("UPDATE pos_menu_items SET deleted_at = ? WHERE id = ?");
    $stmt_item->execute([$now_utc_str, $id]);
    
    // [R2.2] 删除关联 (虽然 FK 已设置 CASCADE，但显式执行更安全)
    $stmt_tags = $pdo->prepare("DELETE FROM pos_product_tag_map WHERE product_id = ?");
    $stmt_tags->execute([$id]);

    $pdo->commit();
    json_ok(null, '商品及其所有规格已成功删除。');
}

/**
* [新功能] Handler: 获取带物料清单的产品列表 (L1+L3)
*/
function handle_menu_get_with_materials(PDO $pdo, array $config, array $input_data): void {
    $search_material_id = $_GET['material_id'] ?? null;

    // 1. 获取所有 POS 菜单项及其关联的 KDS Product ID
    $sql = "
        SELECT
            mi.id, mi.product_code, mi.name_zh, mi.name_es, mi.is_active,
            p.id AS kds_product_id
        FROM pos_menu_items mi
        LEFT JOIN kds_products p ON mi.product_code = p.product_code AND p.deleted_at IS NULL
        WHERE mi.deleted_at IS NULL
        ORDER BY mi.sort_order ASC, mi.id ASC
    ";
    $products = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);

    // 2. 准备物料查询语句
    $sql_materials = "
        (SELECT DISTINCT material_id FROM kds_product_recipes WHERE product_id = ?)
        UNION
        (SELECT DISTINCT material_id FROM kds_recipe_adjustments WHERE product_id = ?)
    ";
    $stmt_materials = $pdo->prepare($sql_materials);

    // 3. 准备物料名称查询语句 [FIX START: HY093]
    $sql_material_names_base = "
        SELECT m.id, mt.material_name AS name_zh
        FROM kds_materials m
        JOIN kds_material_translations mt ON m.id = mt.material_id AND mt.language_code = 'zh-CN'
        WHERE m.deleted_at IS NULL AND m.id IN
    ";
    // [FIX END]

    $results = [];
    $material_name_cache = []; // 缓存物料名称

    foreach ($products as $pid => $product) {
        
        // --- [CRITICAL FIX V1.2.004] ---
        // 将 $pid (即 mi.id) 重新注入到 $product 数组中
        $product['id'] = $pid;
        // --- [END FIX] ---

        $kds_pid = $product['kds_product_id'];
        $product['materials'] = [];
        $material_ids = [];

        if ($kds_pid) {
            $stmt_materials->execute([$kds_pid, $kds_pid]);
            $material_ids = $stmt_materials->fetchAll(PDO::FETCH_COLUMN);
        }

        // 4. (可选) 如果按物料搜索，则过滤
        if ($search_material_id !== null && !in_array($search_material_id, $material_ids)) {
            continue; // 跳过不包含该物料的产品
        }

        if (!empty($material_ids)) {
            // 批量获取物料名称
            $ids_to_fetch = array_diff($material_ids, array_keys($material_name_cache));
            if (!empty($ids_to_fetch)) {
                
                // [FIX START: HY093]
                // 1. 创建占位符
                $in_placeholders = implode(',', array_fill(0, count($ids_to_fetch), '?'));
                // 2. 安全地构建 SQL
                $sql_to_prepare = $sql_material_names_base . " ( " . $in_placeholders . " )";
                // 3. 准备新语句
                $stmt_material_names_dynamic = $pdo->prepare($sql_to_prepare);
                
                // 4. [CRITICAL FIX] 使用 array_values() 重置键名
                $stmt_material_names_dynamic->execute(array_values($ids_to_fetch));
                
                // 5. 抓取 (fetch)
                while ($mat = $stmt_material_names_dynamic->fetch(PDO::FETCH_ASSOC)) {
                    $material_name_cache[$mat['id']] = $mat['name_zh'];
                }
                $stmt_material_names_dynamic->closeCursor();
                // [FIX END]
            }

            // 组装物料列表
            foreach ($material_ids as $mid) {
                $product['materials'][] = [
                    'id' => $mid,
                    'name_zh' => $material_name_cache[$mid] ?? 'ID:'.$mid.' (已删除)'
                ];
            }
        }

        $results[] = $product;
    }

    json_ok($results, '产品物料清单加载成功。');
}

/**
* [新功能] Handler: 切换 POS 菜单项的上架状态
*/
function handle_menu_toggle_active(PDO $pdo, array $config, array $input_data): void {
    $id = $input_data['id'] ?? null;
    $is_active = isset($input_data['is_active']) ? (int)$input_data['is_active'] : null;

    if ($id === null || $is_active === null) {
        json_error('缺少 "id" 或 "is_active" 参数。', 400);
    }

    $stmt = $pdo->prepare("UPDATE pos_menu_items SET is_active = ? WHERE id = ?");
    $stmt->execute([$is_active, (int)$id]);

    json_ok(['id' => $id, 'is_active' => $is_active], '产品状态已更新。');
}

/**
* [新功能] Handler: 获取物料使用总览
*/
function handle_menu_get_material_usage_report(PDO $pdo, array $config, array $input_data): void {
    try {
        // 1. 获取在售产品使用的物料
        $sql_on_sale = "
            SELECT DISTINCT m.id, m.material_code, mt.material_name AS name_zh
            FROM kds_materials m
            JOIN kds_material_translations mt ON m.id = mt.material_id AND mt.language_code = 'zh-CN'
            JOIN (
                SELECT DISTINCT material_id FROM kds_product_recipes r
                JOIN kds_products p ON r.product_id = p.id
                JOIN pos_menu_items mi ON p.product_code = mi.product_code
                WHERE mi.deleted_at IS NULL AND p.deleted_at IS NULL AND mi.is_active = 1
                UNION
                SELECT DISTINCT material_id FROM kds_recipe_adjustments ra
                JOIN kds_products p ON ra.product_id = p.id
                JOIN pos_menu_items mi ON p.product_code = mi.product_code
                WHERE mi.deleted_at IS NULL AND p.deleted_at IS NULL AND mi.is_active = 1
            ) AS used_materials ON m.id = used_materials.material_id
            WHERE m.deleted_at IS NULL
            ORDER BY m.material_code;
        ";
        $on_sale_list = $pdo->query($sql_on_sale)->fetchAll(PDO::FETCH_ASSOC);
        $on_sale_ids = array_column($on_sale_list, 'id');
        $on_sale_ids_placeholders = !empty($on_sale_ids) ? implode(',', array_fill(0, count($on_sale_ids), '?')) : 'NULL';

        // 2. 获取已下架产品使用的物料 (且未在在售产品中使用)
        $sql_off_sale = "
            SELECT DISTINCT m.id, m.material_code, mt.material_name AS name_zh
            FROM kds_materials m
            JOIN kds_material_translations mt ON m.id = mt.material_id AND mt.language_code = 'zh-CN'
            JOIN (
                SELECT DISTINCT material_id FROM kds_product_recipes r
                JOIN kds_products p ON r.product_id = p.id
                JOIN pos_menu_items mi ON p.product_code = mi.product_code
                WHERE mi.deleted_at IS NULL AND p.deleted_at IS NULL AND mi.is_active = 0
                UNION
                SELECT DISTINCT material_id FROM kds_recipe_adjustments ra
                JOIN kds_products p ON ra.product_id = p.id
                JOIN pos_menu_items mi ON p.product_code = mi.product_code
                WHERE mi.deleted_at IS NULL AND p.deleted_at IS NULL AND mi.is_active = 0
            ) AS used_off_materials ON m.id = used_off_materials.material_id
            WHERE m.deleted_at IS NULL
            AND m.id NOT IN ({$on_sale_ids_placeholders})
            ORDER BY m.material_code;
        ";
        $stmt_off_sale = $pdo->prepare($sql_off_sale);
        $stmt_off_sale->execute($on_sale_ids);
        $off_sale_list = $stmt_off_sale->fetchAll(PDO::FETCH_ASSOC);

        // 3. 获取所有已使用的物料 ID (用于查询未使用)
        $all_used_ids = array_merge($on_sale_ids, array_column($off_sale_list, 'id'));
        $all_used_ids_placeholders = !empty($all_used_ids) ? implode(',', array_fill(0, count($all_used_ids), '?')) : 'NULL';

        // 4. 获取未被任何产品使用的物料
        $sql_unused = "
            SELECT m.id, m.material_code, mt.material_name AS name_zh
            FROM kds_materials m
            JOIN kds_material_translations mt ON m.id = mt.material_id AND mt.language_code = 'zh-CN'
            WHERE m.deleted_at IS NULL
            AND m.id NOT IN ({$all_used_ids_placeholders})
            ORDER BY m.material_code;
        ";
        $stmt_unused = $pdo->prepare($sql_unused);
        $stmt_unused->execute($all_used_ids);
        $unused_list = $stmt_unused->fetchAll(PDO::FETCH_ASSOC);

        $response_data = [
            'on_sale'  => $on_sale_list,
            'off_sale' => $off_sale_list,
            'unused'   => $unused_list
        ];

        json_ok($response_data, '物料使用报告已生成。');

    } catch (Throwable $e) {
        json_error('生成物料报告时出错。', 500, ['debug' => $e->getMessage()]);
    }
}


// --- 处理器: POS 规格 (pos_item_variants) ---
function handle_variant_get(PDO $pdo, array $config, array $input_data): void {
    $id = $_GET['id'] ?? json_error('缺少 id', 400);
    $sql = "SELECT v.id, v.menu_item_id, v.variant_name_zh, v.variant_name_es, v.price_eur, v.sort_order, v.is_default, mi.product_code, p.id AS product_id
            FROM pos_item_variants v
            INNER JOIN pos_menu_items mi ON v.menu_item_id = mi.id
            LEFT JOIN kds_products p ON mi.product_code = p.product_code AND p.deleted_at IS NULL
            WHERE v.id = ? AND v.deleted_at IS NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([(int)$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $row ? json_ok($row) : json_error('记录不存在', 404);
}
function handle_variant_save(PDO $pdo, array $config, array $input_data): void {
    $d = $input_data['data'] ?? json_error('缺少 data', 400);
    $id              = isset($d['id']) ? (int)$d['id'] : null;
    $menu_item_id    = isset($d['menu_item_id']) ? (int)$d['menu_item_id'] : 0;
    $variant_name_zh = trim($d['variant_name_zh'] ?? '');
    $variant_name_es = trim($d['variant_name_es'] ?? '');
    $price_eur       = isset($d['price_eur']) ? (float)$d['price_eur'] : 0.0;
    $sort_order      = isset($d['sort_order']) ? (int)$d['sort_order'] : 99;
    $is_default      = !empty($d['is_default']) ? 1 : 0;
    $product_id      = isset($d['product_id']) && $d['product_id'] !== '' ? (int)$d['product_id'] : null;
    if ($menu_item_id <= 0 || $variant_name_zh === '' || $variant_name_es === '' || $price_eur <= 0) json_error('缺少必填项或价格无效', 400);
    $pdo->beginTransaction();
    if ($product_id) {
        $stmt = $pdo->prepare("SELECT product_code FROM kds_products WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$product_id]);
        $pc = $stmt->fetchColumn();
        if ($pc) {
            $stmt2 = $pdo->prepare("UPDATE pos_menu_items SET product_code = ? WHERE id = ? AND deleted_at IS NULL");
            $stmt2->execute([$pc, $menu_item_id]);
        }
    }
    if ($id) {
        $sql = "UPDATE pos_item_variants SET variant_name_zh = ?, variant_name_es = ?, price_eur = ?, sort_order = ?, is_default = ? WHERE id = ? AND deleted_at IS NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$variant_name_zh, $variant_name_es, $price_eur, $sort_order, $is_default, $id]);
    } else {
        $sql = "INSERT INTO pos_item_variants (menu_item_id, variant_name_zh, variant_name_es, price_eur, is_default, sort_order) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$menu_item_id, $variant_name_zh, $variant_name_es, $price_eur, $is_default, $sort_order]);
        $id = (int)$pdo->lastInsertId();
    }
    if ($is_default === 1) {
        $stmt = $pdo->prepare("UPDATE pos_item_variants SET is_default = 0 WHERE menu_item_id = ? AND id <> ?");
        $stmt->execute([$menu_item_id, $id]);
    }
    $pdo->commit();
    json_ok(['id' => $id], '规格已保存');
}
function handle_variant_delete(PDO $pdo, array $config, array $input_data): void {
    $id = $input_data['id'] ?? json_error('缺少 id', 400);
    // [A2.2 UTC FIX] 
    // pos_item_variants.deleted_at 是 timestamp(0)。必须使用 'Y-m-d H:i:s'
    $now_utc_str = utc_now()->format('Y-m-d H:i:s');
    $stmt = $pdo->prepare("UPDATE pos_item_variants SET deleted_at = ? WHERE id = ?");
    $stmt->execute([$now_utc_str, (int)$id]);
    json_ok(null, '规格已删除');
}

// --- 处理器: POS 加料 (pos_addons) ---
function handle_addon_get(PDO $pdo, array $config, array $input_data): void {
    $id = $_GET['id'] ?? json_error('缺少 id', 400);
    $stmt = $pdo->prepare("SELECT * FROM pos_addons WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([(int)$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // [R2.2] 获取关联的 tags
    if ($data) {
        $data['tag_ids'] = getAddonTagIds($pdo, (int)$id);
    }
    
    $data ? json_ok($data) : json_error('未找到加料', 404);
}
function handle_addon_save(PDO $pdo, array $config, array $input_data): void {
    $data = $input_data['data'] ?? json_error('缺少 data', 400);
    $id = $data['id'] ? (int)$data['id'] : null;
    $params = [
        ':addon_code' => trim($data['addon_code']),
        ':name_zh' => trim($data['name_zh']),
        ':name_es' => trim($data['name_es']),
        ':price_eur' => (float)($data['price_eur'] ?? 0),
        ':material_id' => !empty($data['material_id']) ? (int)$data['material_id'] : null,
        ':sort_order' => (int)($data['sort_order'] ?? 99),
        ':is_active' => (int)($data['is_active'] ?? 0)
    ];
    if (empty($params[':addon_code']) || empty($params[':name_zh']) || empty($params[':name_es'])) json_error('编码和双语名称均为必填项。', 400);
    
    // [R2.2] 标签
    $tag_ids = $data['tag_ids'] ?? [];
    
    // 检查 code 唯一性
    $sql_check = "SELECT id FROM pos_addons WHERE addon_code = ? AND deleted_at IS NULL" . ($id ? " AND id != ?" : "");
    $params_check = $id ? [$params[':addon_code'], $id] : [$params[':addon_code']];
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute($params_check);
    if ($stmt_check->fetch()) json_error('此编码 (KEY)已被使用。', 409);
    
    $pdo->beginTransaction();
    try {
        if ($id) {
            $params[':id'] = $id;
            $sql = "UPDATE pos_addons SET addon_code = :addon_code, name_zh = :name_zh, name_es = :name_es, price_eur = :price_eur, material_id = :material_id, sort_order = :sort_order, is_active = :is_active WHERE id = :id";
            $pdo->prepare($sql)->execute($params);
        } else {
            $sql = "INSERT INTO pos_addons (addon_code, name_zh, name_es, price_eur, material_id, sort_order, is_active) VALUES (:addon_code, :name_zh, :name_es, :price_eur, :material_id, :sort_order, :is_active)";
            $pdo->prepare($sql)->execute($params);
            $id = (int)$pdo->lastInsertId();
        }

        // [R2.2] START: 更新 Tag 关联
        $stmt_del_tags = $pdo->prepare("DELETE FROM pos_addon_tag_map WHERE addon_id = ?");
        $stmt_del_tags->execute([$id]);

        if (!empty($tag_ids)) {
            $sql_ins_tags = "INSERT INTO pos_addon_tag_map (addon_id, tag_id) VALUES (?, ?)";
            $stmt_ins_tags = $pdo->prepare($sql_ins_tags);
            foreach ($tag_ids as $tag_id) {
                // 确保 tag_id 是有效的整数
                if (filter_var($tag_id, FILTER_VALIDATE_INT)) {
                    $stmt_ins_tags->execute([$id, (int)$tag_id]);
                }
            }
        }
        // [R2.2] END: 更新 Tag 关联

        $pdo->commit();
        json_ok(['id' => $id], $id ? '加料已成功更新！' : '新加料已成功创建！');

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_error('保存加料时出错。', 500, ['debug' => $e->getMessage()]);
    }
}
function handle_addon_delete(PDO $pdo, array $config, array $input_data): void {
    $id = $input_data['id'] ?? json_error('缺少 id', 400);
    // [A2.2 UTC FIX] 
    // pos_addons.deleted_at 是 timestamp(0)。必须使用 'Y-m-d H:i:s'
    $now_utc_str = utc_now()->format('Y-m-d H:i:s');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE pos_addons SET deleted_at = ? WHERE id = ?");
        $stmt->execute([$now_utc_str, (int)$id]);
        
        // [R2.2] 删除关联 (虽然 FK 已设置 CASCADE，但显式执行更安全)
        $stmt_tags = $pdo->prepare("DELETE FROM pos_addon_tag_map WHERE addon_id = ?");
        $stmt_tags->execute([(int)$id]);
        
        $pdo->commit();
        json_ok(null, '加料已成功删除。');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_error('删除加料时出错。', 500, ['debug' => $e->getMessage()]);
    }
}

// --- [R2.1] START: 处理器: POS 标签 (pos_tags) ---
function handle_pos_tag_get(PDO $pdo, array $config, array $input_data): void {
    $id = $_GET['id'] ?? json_error('缺少 id', 400);
    $data = getTagById($pdo, (int)$id);
    $data ? json_ok($data) : json_error('未找到标签', 404);
}
function handle_pos_tag_save(PDO $pdo, array $config, array $input_data): void {
    $data = $input_data['data'] ?? json_error('缺少 data', 400);
    $id = $data['id'] ? (int)$data['id'] : null;
    $code = trim($data['tag_code'] ?? '');
    $name = trim($data['tag_name'] ?? '');
    
    if (empty($code) || empty($name)) json_error('标签编码和名称均为必填项。', 400);
    
    // 检查 code 唯一性
    $sql_check = "SELECT tag_id FROM pos_tags WHERE tag_code = ?" . ($id ? " AND tag_id != ?" : "");
    $params_check = $id ? [$code, $id] : [$code];
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute($params_check);
    if ($stmt_check->fetch()) json_error('标签编码 "' . htmlspecialchars($code) . '" 已被使用。', 409);

    if ($id) {
        $stmt = $pdo->prepare("UPDATE pos_tags SET tag_code = ?, tag_name = ? WHERE tag_id = ?");
        $stmt->execute([$code, $name, $id]);
        json_ok(['id' => $id], '标签已成功更新！');
    } else {
        $stmt = $pdo->prepare("INSERT INTO pos_tags (tag_code, tag_name) VALUES (?, ?)");
        $stmt->execute([$code, $name]);
        json_ok(['id' => (int)$pdo->lastInsertId()], '新标签已成功创建！');
    }
}
function handle_pos_tag_delete(PDO $pdo, array $config, array $input_data): void {
    $id = $input_data['id'] ?? json_error('缺少 id', 400);
    // 硬删除 (Hard Delete)，因为此表没有 deleted_at
    // [R2.2] 增加事务，确保 map 表也被删除 (虽然 FK 已设置 CASCADE)
    $pdo->beginTransaction();
    try {
        $stmt_map = $pdo->prepare("DELETE FROM pos_addon_tag_map WHERE tag_id = ?");
        $stmt_map->execute([(int)$id]);
        
        $stmt_map_prod = $pdo->prepare("DELETE FROM pos_product_tag_map WHERE tag_id = ?");
        $stmt_map_prod->execute([(int)$id]);
        
        $stmt = $pdo->prepare("DELETE FROM pos_tags WHERE tag_id = ?");
        $stmt->execute([(int)$id]);
        
        $pdo->commit();
        json_ok(null, '标签已成功删除。');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // 检查是否为 FK 约束失败
        if ($e instanceof PDOException && $e->errorInfo[1] == 1451) {
             json_error('删除失败：此标签可能仍被其他系统组件使用 (例如：次卡方案)，请先解除关联。', 409);
        }
        json_error('删除标签时出错。', 500, ['debug' => $e->getMessage()]);
    }
}
// --- [R2.1] END: 处理器: POS 标签 (pos_tags) ---


// --- [R2.4] START: 处理器: 售卡订单 (topup_orders) ---
function handle_topup_order_review(PDO $pdo, array $config, array $input_data): void {
    $order_id = (int)($input_data['order_id'] ?? 0);
    $action = trim($input_data['action'] ?? '');
    $reviewer_id = (int)($_SESSION['user_id'] ?? 0);

    if ($order_id <= 0 || !in_array($action, ['APPROVE', 'REJECT']) || $reviewer_id <= 0) {
        json_error('无效的请求参数 (order_id, action) 或未登录。', 400);
    }
    
    // A2 UTC: 确保所有时间戳使用 0 精度 (Y-m-d H:i:s)
    // topup_orders.reviewed_at 和 member_passes.activated_at/expires_at 均为 timestamp(0)
    $now_utc_str = utc_now()->format('Y-m-d H:i:s');
    
    $pdo->beginTransaction();
    try {
        if ($action === 'APPROVE') {
            // 调用辅助函数激活次卡
            $result = activate_member_pass($pdo, $order_id, $reviewer_id, $now_utc_str);
            if ($result !== true) {
                $pdo->rollBack();
                json_error($result, 409); // 409 Conflict (e.g., already processed)
            }
        } else {
            // 仅拒绝
            $stmt_check = $pdo->prepare("SELECT status FROM topup_orders WHERE order_id = ? FOR UPDATE");
            $stmt_check->execute([$order_id]);
            $status = $stmt_check->fetchColumn();

            if ($status !== 'PENDING') {
                 $pdo->rollBack();
                 json_error("订单状态不是 PENDING，无法拒绝。", 409);
            }

            $sql = "UPDATE topup_orders SET status = 'REJECTED', reviewed_by_user_id = ?, reviewed_at = ? WHERE order_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$reviewer_id, $now_utc_str, $order_id]);
        }

        $pdo->commit();
        json_ok(null, '订单 (ID: ' . $order_id . ') 已成功处理为: ' . $action);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_error('审核订单时发生严重错误。', 500, ['debug' => $e->getMessage()]);
    }
}
// --- [R2.4] END: 处理器: 售卡订单 (topup_orders) ---


// --- 处理器: 会员等级 (pos_member_levels) ---
function handle_member_level_get(PDO $pdo, array $config, array $input_data): void {
    $id = $_GET['id'] ?? json_error('缺少 id', 400);
    $data = getMemberLevelById($pdo, (int)$id);
    $data ? json_ok($data) : json_error('未找到等级', 404);
}
function handle_member_level_save(PDO $pdo, array $config, array $input_data): void {
    $data = $input_data['data'] ?? json_error('缺少 data', 400);
    $id = $data['id'] ? (int)$data['id'] : null;
    $params = [
        ':level_name_zh' => trim($data['level_name_zh']),
        ':level_name_es' => trim($data['level_name_es']),
        ':points_threshold' => (float)($data['points_threshold'] ?? 0),
        ':sort_order' => (int)($data['sort_order'] ?? 99),
        ':level_up_promo_id' => !empty($data['level_up_promo_id']) ? (int)$data['level_up_promo_id'] : null,
    ];
    if (empty($params[':level_name_zh']) || empty($params[':level_name_es'])) json_error('双语等级名称均为必填项。', 400);
    if ($id) {
        $params[':id'] = $id;
        $sql = "UPDATE pos_member_levels SET level_name_zh = :level_name_zh, level_name_es = :level_name_es, points_threshold = :points_threshold, sort_order = :sort_order, level_up_promo_id = :level_up_promo_id WHERE id = :id";
        $pdo->prepare($sql)->execute($params);
        json_ok(['id' => $id], '会员等级已成功更新！');
    } else {
        $sql = "INSERT INTO pos_member_levels (level_name_zh, level_name_es, points_threshold, sort_order, level_up_promo_id) VALUES (:level_name_zh, :level_name_es, :points_threshold, :sort_order, :level_up_promo_id)";
        $pdo->prepare($sql)->execute($params);
        json_ok(['id' => (int)$pdo->lastInsertId()], '新会员等级已成功创建！');
    }
}
function handle_member_level_delete(PDO $pdo, array $config, array $input_data): void {
    $id = $input_data['id'] ?? json_error('缺少 id', 400);
    $stmt = $pdo->prepare("DELETE FROM pos_member_levels WHERE id = ?");
    $stmt->execute([(int)$id]);
    json_ok(null, '会员等级已成功删除。');
}

// --- 处理器: 会员 (pos_members) ---
function handle_member_get(PDO $pdo, array $config, array $input_data): void {
    $id = $_GET['id'] ?? json_error('缺少 id', 400);
    $data = getMemberById($pdo, (int)$id);
    $data ? json_ok($data) : json_error('未找到会员', 404);
}
function handle_member_save(PDO $pdo, array $config, array $input_data): void {
    $data = $input_data['data'] ?? json_error('缺少 data', 400);
    $id = $data['id'] ? (int)$data['id'] : null;
    $phone = trim($data['phone_number'] ?? '');
    if (empty($phone)) json_error('手机号为必填项。', 400);
    $stmt_check = $pdo->prepare("SELECT id FROM pos_members WHERE phone_number = ? AND deleted_at IS NULL" . ($id ? " AND id != ?" : ""));
    $params_check = $id ? [$phone, $id] : [$phone];
    $stmt_check->execute($params_check);
    if ($stmt_check->fetch()) json_error('此手机号已被其他会员使用。', 409);
    $params = [
        ':phone_number' => $phone,
        ':first_name' => !empty($data['first_name']) ? trim($data['first_name']) : null,
        ':last_name' => !empty($data['last_name']) ? trim($data['last_name']) : null,
        ':email' => !empty($data['email']) ? trim($data['email']) : null,
        ':birthdate' => !empty($data['birthdate']) ? trim($data['birthdate']) : null,
        ':points_balance' => (float)($data['points_balance'] ?? 0),
        ':member_level_id' => !empty($data['member_level_id']) ? (int)$data['member_level_id'] : null,
        ':is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1
    ];
    if ($id) {
        $params[':id'] = $id;
        $sql = "UPDATE pos_members SET phone_number = :phone_number, first_name = :first_name, last_name = :last_name, email = :email, birthdate = :birthdate, points_balance = :points_balance, member_level_id = :member_level_id, is_active = :is_active WHERE id = :id";
        $pdo->prepare($sql)->execute($params);
        json_ok(['id' => $id], '会员信息已成功更新！');
    } else {
        $params[':member_uuid'] = bin2hex(random_bytes(16));
        $sql = "INSERT INTO pos_members (member_uuid, phone_number, first_name, last_name, email, birthdate, points_balance, member_level_id, is_active) VALUES (:member_uuid, :phone_number, :first_name, :last_name, :email, :birthdate, :points_balance, :member_level_id, :is_active)";
        $pdo->prepare($sql)->execute($params);
        json_ok(['id' => (int)$pdo->lastInsertId()], '新会员已成功创建！');
    }
}
function handle_member_delete(PDO $pdo, array $config, array $input_data): void {
    $id = $input_data['id'] ?? json_error('缺少 id', 400);
    // [A2.2 UTC FIX] 
    // pos_members.deleted_at 是 timestamp(0)。必须使用 'Y-m-d H:i:s'
    $now_utc_str = utc_now()->format('Y-m-d H:i:s');
    $stmt = $pdo->prepare("UPDATE pos_members SET deleted_at = ? WHERE id = ?");
    $stmt->execute([$now_utc_str, (int)$id]);
    json_ok(null, '会员已成功删除。');
}

// --- 处理器: 积分兑换规则 (pos_point_redemption_rules) ---
function handle_redemption_rule_get(PDO $pdo, array $config, array $input_data): void {
    $id = $_GET['id'] ?? json_error('缺少 id', 400);
    $stmt = $pdo->prepare("SELECT * FROM pos_point_redemption_rules WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([(int)$id]);
    $rule = $stmt->fetch(PDO::FETCH_ASSOC);
    $rule ? json_ok($rule) : json_error('未找到指定的规则。', 404);
}
function handle_redemption_rule_save(PDO $pdo, array $config, array $input_data): void {
    $data = $input_data['data'] ?? json_error('缺少 data', 400);
    $id = $data['id'] ? (int)$data['id'] : null;
    $name_zh = trim($data['rule_name_zh'] ?? ''); $name_es = trim($data['rule_name_es'] ?? '');
    $points = filter_var($data['points_required'] ?? null, FILTER_VALIDATE_INT);
    $reward_type = $data['reward_type'] ?? '';
    $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 0;
    $reward_value_decimal = null; $reward_promo_id = null;
    if (empty($name_zh) || empty($name_es) || $points === false || $points <= 0) json_error('规则名称和所需积分为必填项，且积分必须大于0。', 400);
    if (!in_array($reward_type, ['DISCOUNT_AMOUNT', 'SPECIFIC_PROMOTION'])) json_error('无效的奖励类型。', 400);
    if ($reward_type === 'DISCOUNT_AMOUNT') {
        $reward_value_decimal = filter_var($data['reward_value_decimal'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($reward_value_decimal === false || $reward_value_decimal <= 0) json_error('选择减免金额时，必须提供一个大于0的有效金额。', 400);
        $reward_value_decimal = number_format($reward_value_decimal, 2, '.', '');
    } elseif ($reward_type === 'SPECIFIC_PROMOTION') {
        $reward_promo_id = filter_var($data['reward_promo_id'] ?? null, FILTER_VALIDATE_INT);
        if ($reward_promo_id === false || $reward_promo_id <= 0) json_error('选择赠送活动时，必须选择一个有效的活动。', 400);
    }
    $params = [
        ':rule_name_zh' => $name_zh, ':rule_name_es' => $name_es, ':points_required' => $points,
        ':reward_type' => $reward_type, ':reward_value_decimal' => $reward_value_decimal,
        ':reward_promo_id' => $reward_promo_id, ':is_active' => $is_active
    ];
    if ($id) {
        $params[':id'] = $id;
        $sql = "UPDATE pos_point_redemption_rules SET rule_name_zh = :rule_name_zh, rule_name_es = :rule_name_es, points_required = :points_required, reward_type = :reward_type, reward_value_decimal = :reward_value_decimal, reward_promo_id = :reward_promo_id, is_active = :is_active WHERE id = :id AND deleted_at IS NULL";
        $pdo->prepare($sql)->execute($params);
        json_ok(['id' => $id], '兑换规则已成功更新！');
    } else {
        $sql = "INSERT INTO pos_point_redemption_rules (rule_name_zh, rule_name_es, points_required, reward_type, reward_value_decimal, reward_promo_id, is_active) VALUES (:rule_name_zh, :rule_name_es, :points_required, :reward_type, :reward_value_decimal, :reward_promo_id, :is_active)";
        $pdo->prepare($sql)->execute($params);
        json_ok(['id' => (int)$pdo->lastInsertId()], '新兑换规则已成功创建！');
    }
}
function handle_redemption_rule_delete(PDO $pdo, array $config, array $input_data): void {
    $id = $input_data['id'] ?? json_error('缺少 id', 400);
    // [A2.2 UTC FIX] 
    // pos_point_redemption_rules.deleted_at 是 timestamp(0)。必须使用 'Y-m-d H:i:s'
    $now_utc_str = utc_now()->format('Y-m-d H:i:s');
    $stmt = $pdo->prepare("UPDATE pos_point_redemption_rules SET deleted_at = ? WHERE id = ?");
    $stmt->execute([$now_utc_str, (int)$id]);
    json_ok(null, '兑换规则已成功删除。');
}

// --- 处理器: POS 设置 (pos_settings) ---
function handle_settings_load(PDO $pdo, array $config, array $input_data): void {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM pos_settings WHERE setting_key LIKE 'points_%'");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!isset($settings['points_euros_per_point'])) $settings['points_euros_per_point'] = '1.00';
    json_ok($settings, 'Settings loaded.');
}
function handle_settings_save(PDO $pdo, array $config, array $input_data): void {
    $settings_data = $input_data['settings'] ?? json_error('No settings data provided.', 400);
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO pos_settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ($settings_data as $key => $value) {
        if ($key === 'points_euros_per_point') {
            $floatVal = filter_var($value, FILTER_VALIDATE_FLOAT);
            if ($floatVal === false || $floatVal <= 0) { $pdo->rollBack(); json_error('“每积分所需欧元”必须是一个大于0的数字。', 400); }
            $value = number_format($floatVal, 2, '.', '');
        }
        if (strpos($key, 'points_') === 0) {
            $stmt->execute([':key' => $key, ':value' => $value]);
        }
    }
    $pdo->commit();
    json_ok(null, '设置已成功保存！');
}

// --- [新增] 处理器: SIF 声明 (pos_settings 的特殊动作) ---
const SIF_SETTING_KEY = 'sif_declaracion_responsable';
function handle_sif_load(PDO $pdo, array $config, array $input_data): void {
    $stmt = $pdo->prepare("SELECT setting_value FROM pos_settings WHERE setting_key = ?");
    $stmt->execute([SIF_SETTING_KEY]);
    $value = $stmt->fetchColumn();
    if ($value === false) $value = null; // 区分 '未找到' (null) 和 '空字符串' ('')
    json_ok(['declaration_text' => $value], 'Declaración cargada.');
}
function handle_sif_save(PDO $pdo, array $config, array $input_data): void {
    // SIF handler 不使用 'data' 包装器
    $declaration_text = $input_data['declaration_text'] ?? null;
    if ($declaration_text === null) json_error('No se proporcionó texto de declaración.', 400);
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO pos_settings (setting_key, setting_value, description) VALUES (:key, :value, :desc) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([
        ':key' => SIF_SETTING_KEY,
        ':value' => $declaration_text,
        ':desc' => 'Declaración Responsable (SIF Compliance Statement)'
    ]);
    $pdo->commit();
    json_ok(null, 'Declaración Responsable guardada con éxito.');
}


// --- 处理器: 营销活动 (pos_promotions) ---
function handle_promo_get(PDO $pdo, array $config, array $input_data): void {
    $id = $_GET['id'] ?? json_error('无效的ID。', 400);
    $stmt = $pdo->prepare("SELECT * FROM pos_promotions WHERE id = ?");
    $stmt->execute([(int)$id]);
    $promo = $stmt->fetch(PDO::FETCH_ASSOC);
    $promo ? json_ok($promo, '活动已加载。') : json_error('未找到指定的活动。', 404);
}
function handle_promo_save(PDO $pdo, array $config, array $input_data): void {
    $data = $input_data['data'] ?? json_error('缺少 data', 400);
    $id   = !empty($data['id']) ? (int)$data['id'] : null;
    $promo_name         = trim((string)($data['promo_name'] ?? ''));
    $promo_priority     = (int)($data['promo_priority'] ?? 0);
    $promo_exclusive    = (int)($data['promo_exclusive'] ?? 0);
    $promo_is_active    = (int)($data['promo_is_active'] ?? 0);
    $promo_trigger_type = trim((string)($data['promo_trigger_type'] ?? 'AUTO_APPLY'));
    $promo_code         = trim((string)($data['promo_code'] ?? ''));
    $promo_start_date   = trim((string)($data['promo_start_date'] ?? ''));
    $promo_end_date     = trim((string)($data['promo_end_date'] ?? ''));
    $promo_conditions   = json_encode($data['promo_conditions'] ?? [], JSON_UNESCAPED_UNICODE);
    $promo_actions      = json_encode($data['promo_actions'] ?? [], JSON_UNESCAPED_UNICODE);
    if ($promo_name === '') json_error('活动名称不能为空。', 400);
    if ($promo_trigger_type === 'COUPON_CODE' && $promo_code === '') json_error('优惠码类型的活动，优惠码不能为空。', 400);
    if ($promo_trigger_type === 'COUPON_CODE' && $promo_code !== '') {
        $sql = "SELECT id FROM pos_promotions WHERE LOWER(TRIM(promo_code)) = LOWER(TRIM(?))";
        $params = [$promo_code];
        if ($id) { $sql .= " AND id != ?"; $params[] = $id; }
        $dup = $pdo->prepare($sql);
        $dup->execute($params);
        if ($dup->fetch()) json_error('此优惠码已被其他活动使用。', 409);
    }
    // [A2.2 UTC FIX] promo_start/end_date 是 DATETIME, 不是 TIMESTAMP(6)。
    // 移除 .u
    $startDate = ($promo_start_date !== '' ? str_replace('T',' ', $promo_start_date) : null);
    $endDate = ($promo_end_date   !== '' ? str_replace('T',' ', $promo_end_date)   : null);
    
    $codeValue = ($promo_trigger_type === 'COUPON_CODE' ? $promo_code : null);
    if ($id) {
        $stmt = $pdo->prepare("UPDATE pos_promotions SET promo_name = ?, promo_priority = ?, promo_exclusive = ?, promo_is_active = ?, promo_trigger_type = ?, promo_code = ?, promo_conditions = ?, promo_actions = ?, promo_start_date = ?, promo_end_date = ? WHERE id = ?");
        $stmt->execute([$promo_name, $promo_priority, $promo_exclusive, $promo_is_active, $promo_trigger_type, $codeValue, $promo_conditions, $promo_actions, $startDate, $endDate, $id]);
        json_ok(['id' => $id], '活动已成功更新！');
    } else {
        $stmt = $pdo->prepare("INSERT INTO pos_promotions (promo_name, promo_priority, promo_exclusive, promo_is_active, promo_trigger_type, promo_code, promo_conditions, promo_actions, promo_start_date, promo_end_date) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$promo_name, $promo_priority, $promo_exclusive, $promo_is_active, $promo_trigger_type, $codeValue, $promo_conditions, $promo_actions, $startDate, $endDate]);
        json_ok(['id' => (int)$pdo->lastInsertId()], '新活动已成功创建！');
    }
}
function handle_promo_delete(PDO $pdo, array $config, array $input_data): void {
    $id = $input_data['id'] ?? json_error('无效的ID。', 400);
    $stmt = $pdo->prepare("DELETE FROM pos_promotions WHERE id = ?");
    $stmt->execute([(int)$id]);
    json_ok(null, '活动已成功删除。');
}

// --- 处理器: 票据操作 (invoices) ---
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
        if ($original_invoice['status'] === 'CANCELLED') { $pdo->rollBack(); json_error("此票据已被作废，无法重复操作。", 409); }
        $compliance_system = $original_invoice['compliance_system'];
        $store_id = $original_invoice['store_id'];
        $handler_path = realpath(__DIR__ . "/../../../app/helpers/compliance/{$compliance_system}Handler.php");
        if (!$handler_path || !file_exists($handler_path)) {
             // Fallback path for different server structure (e.g., store vs hq)
             $handler_path = realpath(__DIR__ . "/../../../../../../store/store_html/pos_backend/compliance/{$compliance_system}Handler.php");
             if (!$handler_path || !file_exists($handler_path)) {
                throw new Exception("Compliance handler for '{$compliance_system}' not found at either path.");
             }
        }
        require_once $handler_path;
        $handler_class = "{$compliance_system}Handler";
        $handler = new $handler_class();
        $series = $original_invoice['series'];
        // [A2.2 UTC FIX] 
        // pos_invoices.issued_at 是 timestamp(6)，必须使用 .u
        $issued_at = utc_now()->format('Y-m-d H:i:s.u');
        $stmt_store = $pdo->prepare("SELECT tax_id FROM kds_stores WHERE id = ?");
        $stmt_store->execute([$store_id]);
        $store_config = $stmt_store->fetch();
        $issuer_nif = $store_config['tax_id'];
        $stmt_prev = $pdo->prepare("SELECT compliance_data FROM pos_invoices WHERE compliance_system = ? AND series = ? AND issuer_nif = ? ORDER BY `number` DESC LIMIT 1");
        $stmt_prev->execute([$compliance_system, $series, $issuer_nif]);
        $prev_invoice = $stmt_prev->fetch();
        $previous_hash = $prev_invoice ? (json_decode($prev_invoice['compliance_data'], true)['hash'] ?? null) : null;
        $cancellationData = ['cancellation_reason' => $cancellation_reason, 'issued_at' => $issued_at];
        $compliance_data = $handler->generateCancellationData($pdo, $original_invoice, $cancellationData, $previous_hash);
        $next_number = 1 + ($pdo->query("SELECT IFNULL(MAX(number), 0) FROM pos_invoices WHERE compliance_system = '{$compliance_system}' AND series = '{$series}' AND issuer_nif = '{$issuer_nif}'")->fetchColumn());
        $sql_cancel = "INSERT INTO pos_invoices (invoice_uuid, store_id, user_id, issuer_nif, series, `number`, issued_at, invoice_type, status, cancellation_reason, references_invoice_id, compliance_system, compliance_data, taxable_base, vat_amount, final_total) VALUES ( ?, ?, ?, ?, ?, ?, ?, 'R5', 'ISSUED', ?, ?, ?, ?, 0.00, 0.00, 0.00 )";
        $stmt_cancel = $pdo->prepare($sql_cancel);
        $stmt_cancel->execute([ uniqid('can-', true), $store_id, $_SESSION['user_id'] ?? 1, $issuer_nif, $series, $next_number, $issued_at, $cancellation_reason, $original_invoice_id, $compliance_system, json_encode($compliance_data) ]);
        $cancellation_invoice_id = $pdo->lastInsertId();
        $stmt_update_original = $pdo->prepare("UPDATE pos_invoices SET status = 'CANCELLED', cancellation_reason = ? WHERE id = ?");
        $stmt_update_original->execute([$cancellation_reason, $original_invoice_id]);
        $pdo->commit();
        json_ok(['cancellation_invoice_id' => $cancellation_invoice_id], '票据已成功作废并生成作废记录。');
    } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); json_error('作废票据失败。', 500, ['debug' => $e->getMessage()]); }
}
function handle_invoice_correct(PDO $pdo, array $config, array $input_data): void {
    $original_invoice_id = (int)($input_data['id'] ?? 0);
    $correction_type = $input_data['type'] ?? '';
    $new_total_str = $input_data['new_total'] ?? null;
    $reason = trim($input_data['reason'] ?? '');
    if ($original_invoice_id <= 0 || !in_array($correction_type, ['S', 'I']) || empty($reason)) json_error('请求参数无效 (ID, 类型, 原因)。', 400);
    if ($correction_type === 'I' && ($new_total_str === null || !is_numeric($new_total_str) || (float)$new_total_str < 0)) json_error('按差额更正时，必须提供一个有效的、非负的最终总额。', 400);
    $pdo->beginTransaction();
    try {
        $stmt_original = $pdo->prepare("SELECT * FROM pos_invoices WHERE id = ? FOR UPDATE");
        $stmt_original->execute([$original_invoice_id]);
        $original_invoice = $stmt_original->fetch();
        if (!$original_invoice) { $pdo->rollBack(); json_error("原始票据不存在。", 404); }
        if ($original_invoice['status'] === 'CANCELLED') { $pdo->rollBack(); json_error("已作废的票据不能被更正。", 409); }
        $compliance_system = $original_invoice['compliance_system'];
        $store_id = $original_invoice['store_id'];
        $handler_path = realpath(__DIR__ . "/../../../app/helpers/compliance/{$compliance_system}Handler.php");
        if (!$handler_path || !file_exists($handler_path)) {
             // Fallback path
             $handler_path = realpath(__DIR__ . "/../../../../../../store/store_html/pos_backend/compliance/{$compliance_system}Handler.php");
             if (!$handler_path || !file_exists($handler_path)) {
                 throw new Exception("合规处理器 '{$compliance_system}' 未找到。");
             }
        }
        require_once $handler_path;
        $handler_class = "{$compliance_system}Handler";
        $handler = new $handler_class();
        $stmt_store = $pdo->prepare("SELECT tax_id, default_vat_rate FROM kds_stores WHERE id = ?");
        $stmt_store->execute([$store_id]);
        $store_config = $stmt_store->fetch();
        $issuer_nif = $store_config['tax_id'];
        $vat_rate = $store_config['default_vat_rate'];
        if ($correction_type === 'S') { $final_total = -$original_invoice['final_total']; }
        else { $new_total = (float)$new_total_str; $final_total = $new_total - (float)$original_invoice['final_total']; }
        $taxable_base = round($final_total / (1 + ($vat_rate / 100)), 2);
        $vat_amount = $final_total - $taxable_base;
        $series = $original_invoice['series'];
        // [A2.2 UTC FIX] 
        // pos_invoices.issued_at 是 timestamp(6)，必须使用 .u
        $issued_at = utc_now()->format('Y-m-d H:i:s.u');
        $stmt_prev = $pdo->prepare("SELECT compliance_data FROM pos_invoices WHERE compliance_system = ? AND series = ? AND issuer_nif = ? ORDER BY `number` DESC LIMIT 1");
        $stmt_prev->execute([$compliance_system, $series, $issuer_nif]);
        $prev_invoice = $stmt_prev->fetch();
        $previous_hash = $prev_invoice ? (json_decode($prev_invoice['compliance_data'], true)['hash'] ?? null) : null;
        $next_number = 1 + ($pdo->query("SELECT IFNULL(MAX(number), 0) FROM pos_invoices WHERE compliance_system = '{$compliance_system}' AND series = '{$series}' AND issuer_nif = '{$issuer_nif}'")->fetchColumn());
        $invoiceData = ['series' => $series, 'number' => $next_number, 'issued_at' => $issued_at, 'final_total' => $final_total];
        $compliance_data = $handler->generateComplianceData($pdo, $invoiceData, $previous_hash);
        $sql_corrective = "INSERT INTO pos_invoices (invoice_uuid, store_id, user_id, issuer_nif, series, `number`, issued_at, invoice_type, status, correction_type, references_invoice_id, compliance_system, compliance_data, taxable_base, vat_amount, final_total) VALUES (?, ?, ?, ?, ?, ?, ?, 'R5', 'ISSUED', ?, ?, ?, ?, ?, ?, ?)";
        $stmt_corrective = $pdo->prepare($sql_corrective);
        $stmt_corrective->execute([ uniqid('cor-', true), $store_id, $_SESSION['user_id'] ?? 1, $issuer_nif, $series, $next_number, $issued_at, $correction_type, $original_invoice_id, $compliance_system, json_encode($compliance_data), $taxable_base, $vat_amount, $final_total ]);
        $corrective_invoice_id = $pdo->lastInsertId();
        $pdo->commit();
        json_ok(['corrective_invoice_id' => $corrective_invoice_id], '更正票据已成功生成。');
    } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); json_error('生成更正票据失败。', 500, ['debug' => $e->getMessage()]); }
}

// --- 处理器: 班次复核 (shifts) ---
function handle_shift_review(PDO $pdo, array $config, array $input_data): void {
    $shift_id = (int)($input_data['shift_id'] ?? 0);
    $counted_cash_str = $input_data['counted_cash'] ?? null;
    if ($shift_id <= 0 || $counted_cash_str === null || !is_numeric($counted_cash_str)) json_error('无效的参数 (shift_id or counted_cash)。', 400);
    $counted_cash = (float)$counted_cash_str;
    
    // [A2.2 UTC FIX] 
    // pos_shifts.updated_at 是 timestamp(0)。必须使用 'Y-m-d H:i:s'
    $now_utc_str = utc_now()->format('Y-m-d H:i:s');
    
    $pdo->beginTransaction();
    try {
        $stmt_get = $pdo->prepare("SELECT id, expected_cash FROM pos_shifts WHERE id = ? AND status = 'FORCE_CLOSED' AND admin_reviewed = 0 FOR UPDATE");
        $stmt_get->execute([$shift_id]);
        $shift = $stmt_get->fetch(PDO::FETCH_ASSOC);
        if (!$shift) { $pdo->rollBack(); json_error('未找到待复核的班次，或该班次已被他人处理。', 404); }
        $expected_cash = (float)$shift['expected_cash'];
        $cash_diff = $counted_cash - $expected_cash;
        $stmt_update = $pdo->prepare("UPDATE pos_shifts SET counted_cash = ?, cash_variance = ?, admin_reviewed = 1, updated_at = ? WHERE id = ?");
        $stmt_update->execute([$counted_cash, $cash_diff, $now_utc_str, $shift_id]);
        
        // ================== [GEMINI HEALTH CHECK FIX V2.0] ==================
        // 移除了对 `pos_eod_records.notes` 字段的写入，因为该字段不存在。
        try {
            $stmt_eod = $pdo->prepare("UPDATE pos_eod_records SET counted_cash = ?, cash_diff = ? WHERE shift_id = ?");
            $stmt_eod->execute([$counted_cash, $cash_diff, $shift_id]);
        } catch (PDOException $e) {
            if ($e->getCode() !== '42S02') { throw $e; }
            error_log("Warning: pos_eod_records table not found during shift review. Skipping update.");
        }
        // ================== [GEMINI HEALTH CHECK FIX V2.0] ==================

        $pdo->commit();
        json_ok(null, '班次复核成功！');
    } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); json_error('班次复核失败', 500, ['debug' => $e->getMessage()]); }
}


// --- 注册表 ---
return [

    'pos_categories' => [
        'table' => 'pos_categories', 'pk' => 'id', 'soft_delete_col' => 'deleted_at', 'auth_role' => ROLE_SUPER_ADMIN,
        'custom_actions' => [ 'get' => 'handle_pos_category_get', 'save' => 'handle_pos_category_save', 'delete' => 'handle_pos_category_delete', ],
    ],
    'pos_menu_items' => [
        'table' => 'pos_menu_items', 'pk' => 'id', 'soft_delete_col' => 'deleted_at', 'auth_role' => ROLE_SUPER_ADMIN,
        'custom_actions' => [
            'get' => 'handle_menu_item_get',
            'save' => 'handle_menu_item_save',
            'delete' => 'handle_menu_item_delete',
            // --- [新功能] START ---
            'get_with_materials' => 'handle_menu_get_with_materials',
            'toggle_active' => 'handle_menu_toggle_active',
            'get_material_usage_report' => 'handle_menu_get_material_usage_report', // <-- [新功能] 注册
            // --- [新功能] END ---
        ],
    ],
    'pos_item_variants' => [
        'table' => 'pos_item_variants', 'pk' => 'id', 'soft_delete_col' => 'deleted_at', 'auth_role' => ROLE_SUPER_ADMIN,
        'custom_actions' => [ 'get' => 'handle_variant_get', 'save' => 'handle_variant_save', 'delete' => 'handle_variant_delete', ],
    ],
    'pos_addons' => [
        'table' => 'pos_addons', 'pk' => 'id', 'soft_delete_col' => 'deleted_at', 'auth_role' => ROLE_SUPER_ADMIN,
        'custom_actions' => [ 'get' => 'handle_addon_get', 'save' => 'handle_addon_save', 'delete' => 'handle_addon_delete', ],
    ],
    
    // --- [R2.1] START: 注册 pos_tags ---
    'pos_tags' => [
        'table' => 'pos_tags', 'pk' => 'tag_id', 'soft_delete_col' => null, 'auth_role' => ROLE_SUPER_ADMIN,
        'custom_actions' => [
            'get' => 'handle_pos_tag_get',
            'save' => 'handle_pos_tag_save',
            'delete' => 'handle_pos_tag_delete',
        ],
    ],
    // --- [R2.1] END ---
    
    // --- [R2.4] START: 注册 topup_orders ---
    'topup_orders' => [
        'table' => 'topup_orders', 'pk' => 'order_id', 'soft_delete_col' => 'deleted_at', 'auth_role' => ROLE_SUPER_ADMIN,
        'custom_actions' => [
            'review' => 'handle_topup_order_review',
        ],
    ],
    // --- [R2.4] END ---
    
    'pos_member_levels' => [
        'table' => 'pos_member_levels', 'pk' => 'id', 'soft_delete_col' => null, 'auth_role' => ROLE_SUPER_ADMIN,
        'custom_actions' => [ 'get' => 'handle_member_level_get', 'save' => 'handle_member_level_save', 'delete' => 'handle_member_level_delete', ],
    ],
    'pos_members' => [
        'table' => 'pos_members', 'pk' => 'id', 'soft_delete_col' => 'deleted_at', 'auth_role' => ROLE_SUPER_ADMIN,
        'custom_actions' => [ 'get' => 'handle_member_get', 'save' => 'handle_member_save', 'delete' => 'handle_member_delete', ],
    ],
    'pos_redemption_rules' => [
        'table' => 'pos_point_redemption_rules', 'pk' => 'id', 'soft_delete_col' => 'deleted_at', 'auth_role' => ROLE_SUPER_ADMIN,
        'custom_actions' => [ 'get' => 'handle_redemption_rule_get', 'save' => 'handle_redemption_rule_save', 'delete' => 'handle_redemption_rule_delete', ],
    ],
    'pos_settings' => [
        'table' => 'pos_settings', 'pk' => 'setting_key', 'soft_delete_col' => null, 'auth_role' => ROLE_SUPER_ADMIN,
        'custom_actions' => [
            'load' => 'handle_settings_load',
            'save' => 'handle_settings_save',
            'load_sif' => 'handle_sif_load',     // <-- 新增 SIF 动作
            'save_sif' => 'handle_sif_save',     // <-- 新增 SIF 动作
        ],
    ],
    'pos_promotions' => [
        'table' => 'pos_promotions', 'pk' => 'id', 'soft_delete_col' => null, 'auth_role' => ROLE_PRODUCT_MANAGER,
        'custom_actions' => [ 'get' => 'handle_promo_get', 'save' => 'handle_promo_save', 'delete' => 'handle_promo_delete', ],
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
