<?php
/**
 * KDS Repo B - Fallback implementations for CPSYS GUI
 * Purpose: Provide minimal implementations required by /html/cpsys/index.php
 * Functions: getAllGlobalRules, getAllMenuItems, getAllMenuItemsForSelect
 * Notes:
 * - Minimal columns only; avoid coupling to non-essential fields.
 * - Safe to co-exist: each function is wrapped with !function_exists to prevent redeclare fatals.
 * - No closing PHP tag to avoid BOM/whitespace issues.
 *
 * [GEMINI FIX 2025-11-12]:
 * 1. Corrected pmi.category_id -> pmi.pos_category_id (Fixes SQL Error 1054)
 * 2. Removed stray '}' at end of file (Fixes Parse Error)
 *
 * [R1] Added:
 * - getRecipeByProductCode() for rms.recipes.get API endpoint.
 */

if (!function_exists('getAllGlobalRules')) {
    /**
     * 读取全局规则 (L2)
     * 表：kds_global_adjustment_rules
     * 返回字段与旧版视图兼容：id, rule_name, priority, is_active, cond_*, action_*
     */
    function getAllGlobalRules(PDO $pdo): array {
        $sql = <<<SQL
            SELECT
                id,
                rule_name,
                priority,
                is_active,
                cond_cup_id,
                cond_ice_id,
                cond_sweet_id,
                cond_material_id,
                cond_base_gt,
                cond_base_lte,
                action_type,
                action_material_id,
                action_value,
                action_unit_id
            FROM kds_global_adjustment_rules
            ORDER BY is_active DESC, priority ASC, id ASC
        SQL;
        $stmt = $pdo->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }
}

if (!function_exists('getAllMenuItems')) {
    /**
     * 菜单条目完整列表（含分类名、门店售罄状态）
     * 表：pos_menu_items / pos_categories / pos_product_availability
     * 返回：pmi.* + category_name_zh/category_name_es + is_sold_out
     */
    function getAllMenuItems(PDO $pdo, ?int $store_id = null): array {
        $sql = <<<SQL
            SELECT
                pmi.*,
                pc.name_zh AS category_name_zh,
                pc.name_es AS category_name_es,
                COALESCE(MAX(CASE WHEN ppa.store_id = :store_id THEN ppa.is_sold_out END), 0) AS is_sold_out
            FROM pos_menu_items pmi
            LEFT JOIN pos_categories pc
                ON pc.id = pmi.pos_category_id /* [GEMINI FIX] Was pmi.category_id */
            LEFT JOIN pos_product_availability ppa
                ON ppa.menu_item_id = pmi.id
            GROUP BY pmi.id
            ORDER BY (pc.sort_order IS NULL), pc.sort_order, pmi.id
        SQL;
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':store_id', $store_id ?? 0, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('getAllMenuItemsForSelect')) {
    /**
     * 菜单下拉用的精简列表（id + 展示名）
     * 返回字段：value, label
     */
    function getAllMenuItemsForSelect(PDO $pdo, ?int $store_id = null): array {
        $rows = getAllMenuItems($pdo, $store_id);
        $out = [];
        foreach ($rows as $r) {
            $name = $r['name_es'] ?? ($r['name_zh'] ?? ('#'.($r['id'] ?? 0)));
            $cat  = $r['category_name_es'] ?? ($r['category_name_zh'] ?? null);
            $label = $cat ? ("[".$cat."] ".$name) : $name;
            $out[] = [
                'value' => (int)($r['id'] ?? 0),
                'label' => $label
            ];
        }
        return $out;
    }
}

// --- [R1] START: 新增 rms.recipes.get 依赖 ---
if (!function_exists('getRecipeByProductCode')) {
    /**
     * [R1] 根据 Product Code (P-Code) 获取 KDS 配方详情 (L1, L3, Gating)
     * (供 rms.recipes.get 接口使用)
     */
    function getRecipeByProductCode(PDO $pdo, string $product_code): ?array {
        // 1. 根据 P-Code 查找基础产品信息
        $stmt = $pdo->prepare("
            SELECT p.id, p.product_code, p.status_id, p.is_active,
                   COALESCE(tzh.product_name,'') AS name_zh,
                   COALESCE(tes.product_name,'') AS name_es
            FROM kds_products p
            LEFT JOIN kds_product_translations tzh ON tzh.product_id=p.id AND tzh.language_code='zh-CN'
            LEFT JOIN kds_product_translations tes ON tes.product_id=p.id AND tes.language_code='es-ES'
            WHERE p.product_code = ? AND p.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$product_code]);
        $base = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$base) {
            return null; // 未找到产品
        }
        
        $productId = (int)$base['id'];

        // 2. 获取 L1 基础配方
        $stmt_l1 = $pdo->prepare("
            SELECT id, material_id, unit_id, quantity, step_category, sort_order
            FROM kds_product_recipes
            WHERE product_id=?
            ORDER BY sort_order ASC, id ASC
        ");
        $stmt_l1->execute([$productId]);
        $base_recipes = $stmt_l1->fetchAll(PDO::FETCH_ASSOC);

        // 3. 获取 L3 特例规则
        $stmt_l3 = $pdo->prepare("
            SELECT id, material_id, unit_id, quantity, step_category,
                   cup_id, sweetness_option_id, ice_option_id
            FROM kds_recipe_adjustments
            WHERE product_id=?
            ORDER BY id ASC
        ");
        $stmt_l3->execute([$productId]);
        $raw_adjustments = $stmt_l3->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 3.1 按 (Cup-Sweet-Ice) 组合 L3 规则
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

        // 4. 获取甜度门控 (Gating)
        $stmt_sweet = $pdo->prepare("SELECT sweetness_option_id FROM kds_product_sweetness_options WHERE product_id=?");
        $stmt_sweet->execute([$productId]);
        $allowed_sweetness_ids = array_filter(
            array_map('intval', $stmt_sweet->fetchAll(PDO::FETCH_COLUMN)),
            fn($sid) => $sid > 0
        );

        // 5. 获取冰量门控 (Gating)
        $stmt_ice = $pdo->prepare("SELECT ice_option_id FROM kds_product_ice_options WHERE product_id=?");
        $stmt_ice->execute([$productId]);
        $allowed_ice_ids = array_filter(
            array_map('intval', $stmt_ice->fetchAll(PDO::FETCH_COLUMN)),
            fn($iid) => $iid > 0
        );

        // 6. 组装最终结果
        $response = $base;
        $response['base_recipes'] = $base_recipes;
        $response['adjustments']  = $adjustments;
        $response['allowed_sweetness_ids'] = $allowed_sweetness_ids;
        $response['allowed_ice_ids'] = $allowed_ice_ids;
        
        return $response;
    }
}
// --- [R1] END ---

/* [GEMINI FIX] Removed stray '}' from here */
// ================== [ 致命错误修复 ] ==================
// 补充了 `kds_repo_b.php` 中缺失的结尾 '}' 括号
// 这导致 kds_helper.php 在第 6 行 require 时解析失败
// 从而使 index.php 在第 112 行加载失败，引发所有 'Call to undefined function' 错误
// ====================================================