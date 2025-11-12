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
/* [GEMINI FIX] Removed stray '}' from here */
// ================== [ 致命错误修复 ] ==================
// 补充了 `kds_repo_b.php` 中缺失的结尾 '}' 括号
// 这导致 kds_helper.php 在第 6 行 require 时解析失败
// 从而使 index.php 在第 112 行加载失败，引发所有 'Call to undefined function' 错误
// ====================================================