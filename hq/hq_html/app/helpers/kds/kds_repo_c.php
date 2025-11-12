<?php
/**
 * KDS Repo C - Dicts + Ops (Phase 2 consolidation)
 * V4 (2025-11-12):
 * - [GEMINI FIX] 修复 getAllCups() SQL (不再 join 不存在的 translations 表, 直接从 kds_cups 读取)
 * - [GEMINI FIX] 修复 getAllIceOptions() 函数名 (移除 "Active")
 * - [GEMINI FIX] 修复 getAllPosTags/getTagById SQL (使用 tag_name 字段)
 * - [GEMINI FIX] 修复 getProductTagIds/getAddonTagIds SQL (使用正确的 _map 表名)
 * - [GEMINI FIX] 添加缺失的 getCupById() 函数
 * - 合并了 V2/V3 的所有补丁 (Dashboard / Shift / EOD / Stock / Invoice / Pass 函数)
 */

/** ========== Ice / Sweet / Cup / Addon ========== */

// [GEMINI FIX] 重命名 getAllActiveIceOptions -> getAllIceOptions
if (!function_exists('getAllIceOptions')) {
    function getAllIceOptions(PDO $pdo): array {
        $sql = "SELECT i.id, i.ice_code, it_zh.ice_option_name AS name_zh, it_es.ice_option_name AS name_es, it_zh.sop_description AS sop_zh
                FROM kds_ice_options i
                LEFT JOIN kds_ice_option_translations it_zh ON i.id = it_zh.ice_option_id AND it_zh.language_code = 'zh-CN'
                LEFT JOIN kds_ice_option_translations it_es ON i.id = it_es.ice_option_id AND it_es.language_code = 'es-ES'
                WHERE i.deleted_at IS NULL
                ORDER BY i.ice_code ASC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ================== [ 错误修复 START ] ==================
// 补充缺失的 getIceOptionById 函数
if (!function_exists('getIceOptionById')) {
    function getIceOptionById(PDO $pdo, int $id): ?array {
        $sql = "
            SELECT 
                i.id, i.ice_code,
                it_zh.ice_option_name AS name_zh, 
                it_es.ice_option_name AS name_es,
                it_zh.sop_description AS sop_zh,
                it_es.sop_description AS sop_es
            FROM kds_ice_options i
            LEFT JOIN kds_ice_option_translations it_zh ON i.id = it_zh.ice_option_id AND it_zh.language_code = 'zh-CN'
            LEFT JOIN kds_ice_option_translations it_es ON i.id = it_es.ice_option_id AND it_es.language_code = 'es-ES'
            WHERE i.id = ? AND i.deleted_at IS NULL
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
// ================== [ 错误修复 END ] ==================

if (!function_exists('getAllSweetnessOptions')) {
    function getAllSweetnessOptions(PDO $pdo): array {
        $sql = "
            SELECT 
                s.id, s.sweetness_code,
                st_zh.sweetness_option_name AS name_zh, 
                st_es.sweetness_option_name AS name_es,
                st_zh.sop_description AS sop_zh
            FROM kds_sweetness_options s 
            LEFT JOIN kds_sweetness_option_translations st_zh ON s.id = st_zh.sweetness_option_id AND st_zh.language_code = 'zh-CN' 
            LEFT JOIN kds_sweetness_option_translations st_es ON s.id = st_es.sweetness_option_id AND st_es.language_code = 'es-ES'
            WHERE s.deleted_at IS NULL 
            ORDER BY s.sweetness_code ASC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ================== [ 错误修复 START ] ==================
// 补充缺失的 getSweetnessOptionById 函数
if (!function_exists('getSweetnessOptionById')) {
    function getSweetnessOptionById(PDO $pdo, int $id): ?array {
        $sql = "
            SELECT 
                s.id, s.sweetness_code,
                st_zh.sweetness_option_name AS name_zh, 
                st_es.sweetness_option_name AS name_es,
                st_zh.sop_description AS sop_zh,
                st_es.sop_description AS sop_es
            FROM kds_sweetness_options s
            LEFT JOIN kds_sweetness_option_translations st_zh ON s.id = st_zh.sweetness_option_id AND st_zh.language_code = 'zh-CN'
            LEFT JOIN kds_sweetness_option_translations st_es ON s.id = st_es.sweetness_option_id AND st_es.language_code = 'es-ES'
            WHERE s.id = ? AND s.deleted_at IS NULL
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
// ================== [ 错误修复 END ] ==================


if (!function_exists('getAllCups')) {
    // [GEMINI FIX] 修复了 SQL，不再 JOIN kds_cup_translations
    // 直接从 kds_cups 表读取，该表已包含所有字段
    function getAllCups(PDO $pdo): array {
        $sql = "
            SELECT 
                c.id, 
                c.cup_code, 
                c.volume_ml AS capacity_ml, /* 修复别名 (如果视图需要) */
                1 AS is_active, /* 假定为 active，因为 kds_cups 表没有 is_active */
                c.cup_name, /* [FIX] 视图需要 'cup_name' */
                c.cup_name AS name_zh, /* 兼容新视图 */
                c.cup_name AS name_es,  /* 兼容新视图 */
                c.sop_description_zh, /* [FIX] 视图需要 'sop_description_zh' */
                c.sop_description_es  /* [FIX] 视图需要 'sop_description_es' */
            FROM kds_cups c
            WHERE c.deleted_at IS NULL
            ORDER BY c.cup_code ASC
        ";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// [GEMINI FIX] 添加缺失的 getCupById
if (!function_exists('getCupById')) {
    function getCupById(PDO $pdo, int $id): ?array {
         $sql = "
            SELECT 
                c.id, 
                c.cup_code, 
                c.cup_name,
                c.sop_description_zh, 
                c.sop_description_es
            FROM kds_cups c
            WHERE c.id = ? AND c.deleted_at IS NULL
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}


if (!function_exists('getAllAddons')) {
    function getAllAddons(PDO $pdo): array {
        // 假设 kds_addons 和 kds_addon_translations 存在 (虽然不在SQL DDL中)
        try {
            $sql = "
                SELECT 
                    a.id, a.addon_code, a.is_active,
                    at_zh.addon_name AS name_zh,
                    at_es.addon_name AS name_es
                FROM kds_addons a
                LEFT JOIN kds_addon_translations at_zh ON a.id = at_zh.addon_id AND at_zh.language_code = 'zh-CN'
                LEFT JOIN kds_addon_translations at_es ON a.id = at_es.addon_id AND at_es.language_code = 'es-ES'
                WHERE a.deleted_at IS NULL
                ORDER BY a.addon_code ASC
            ";
            $stmt = $pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
             error_log("Error in getAllAddons (kds_repo_c.php): " . $e->getMessage());
             return []; // 返回空数组防止崩溃
        }
    }
}

/** ========== POS Tags ========== */

if (!function_exists('getAllPosTags')) {
    // [GEMINI FIX] 修复了 SQL (pos_tags 只有 tag_name, 且没有 deleted_at)
    function getAllPosTags(PDO $pdo): array {
        $sql = "SELECT tag_id, tag_code, tag_name FROM pos_tags ORDER BY tag_code ASC";
        try {
            $stmt = $pdo->query($sql);
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (PDOException $e) {
             error_log("Error in getAllPosTags (kds_repo_c.php): " . $e->getMessage());
             return [];
        }
    }
}

if (!function_exists('getTagById')) {
    // [GEMINI FIX] 修复了 SQL (pos_tags 只有 tag_name, 且没有 deleted_at)
    function getTagById(PDO $pdo, int $tag_id): ?array {
         $sql = "SELECT tag_id, tag_code, tag_name FROM pos_tags WHERE tag_id = ?";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tag_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log("Error in getTagById (kds_repo_c.php): " . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('getProductTagIds')) {
    // [GEMINI FIX] 修复了表名 (pos_menu_item_tags -> pos_product_tag_map)
    function getProductTagIds(PDO $pdo, int $menu_item_id): array {
        $sql = "SELECT tag_id FROM pos_product_tag_map WHERE product_id = ?";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$menu_item_id]);
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (PDOException $e) {
             error_log("Error in getProductTagIds (kds_repo_c.php): " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('getAddonTagIds')) {
    // [GEMINI FIX] 修复了表名 (pos_addon_tags -> pos_addon_tag_map)
    function getAddonTagIds(PDO $pdo, int $addon_id): array {
        // 假设表名是 'pos_addon_tag_map' (虽然 DDL 缺失，但 registry 在引用)
        $sql = "SELECT tag_id FROM pos_addon_tag_map WHERE addon_id = ?";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$addon_id]);
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (PDOException $e) {
            // 如果 pos_addon_tag_map 确实不存在，记录错误并返回
            error_log("Error in getAddonTagIds (Table 'pos_addon_tag_map' might be missing): " . $e->getMessage());
            return [];
        }
    }
}

// ================== [ 错误修复 START ] ==================
// 补充 index.php:253 (page=pos_variants_management) 所需的 getMenuItemById
if (!function_exists('getMenuItemById')) {
    function getMenuItemById(PDO $pdo, int $menu_item_id): ?array {
        $sql = "
            SELECT mi.*, pc.name_zh AS category_name_zh
            FROM pos_menu_items mi
            LEFT JOIN pos_categories pc ON mi.pos_category_id = pc.id
            WHERE mi.id = ? AND mi.deleted_at IS NULL
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$menu_item_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
// ================== [ 错误修复 END ] ==================

/** ========== Materials & Units（与 repo_a 可能重叠，故加防重复） ========== */

if (!function_exists('getAllUnits')) {
    function getAllUnits(PDO $pdo): array {
        $sql = "
            SELECT 
                u.id, u.unit_code,
                ut_zh.unit_name AS name_zh,
                ut_es.unit_name AS name_es
            FROM kds_units u
            LEFT JOIN kds_unit_translations ut_zh ON u.id = ut_zh.unit_id AND ut_zh.language_code = 'zh-CN'
            LEFT JOIN kds_unit_translations ut_es ON u.id = ut_es.unit_id AND ut_es.language_code = 'es-ES'
            WHERE u.deleted_at IS NULL
            ORDER BY u.unit_code ASC
        ";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('getAllMaterials')) {
    function getAllMaterials(PDO $pdo): array {
        $sql = "
        SELECT
            m.id,
            m.material_code,
            m.material_type,
            m.medium_conversion_rate,
            m.large_conversion_rate,
            mt_zh.material_name AS name_zh,
            mt_es.material_name AS name_es,
            ut_base_zh.unit_name   AS base_unit_name,
            ut_medium_zh.unit_name AS medium_unit_name,
            ut_large_zh.unit_name  AS large_unit_name
        FROM kds_materials m
        LEFT JOIN kds_material_translations mt_zh
            ON m.id = mt_zh.material_id AND mt_zh.language_code = 'zh-CN'
        LEFT JOIN kds_material_translations mt_es
            ON m.id = mt_es.material_id AND mt_es.language_code = 'es-ES'
        LEFT JOIN kds_unit_translations ut_base_zh
            ON m.base_unit_id = ut_base_zh.unit_id AND ut_base_zh.language_code = 'zh-CN'
        LEFT JOIN kds_unit_translations ut_medium_zh
            ON m.medium_unit_id = ut_medium_zh.unit_id AND ut_medium_zh.language_code = 'zh-CN'
        LEFT JOIN kds_unit_translations ut_large_zh
            ON m.large_unit_id = ut_large_zh.unit_id AND ut_large_zh.language_code = 'zh-CN'
        WHERE m.deleted_at IS NULL
        ORDER BY m.material_code ASC";
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('getMaterialById')) {
    function getMaterialById(PDO $pdo, int $id): ?array {
         $sql = "
        SELECT
            m.id,
            m.material_code,
            m.material_type,
            m.base_unit_id,
            m.medium_unit_id,
            m.medium_conversion_rate,
            m.large_unit_id,
            m.large_conversion_rate,
            m.expiry_rule_type,
            m.expiry_duration,
            m.image_url,
            mt_zh.material_name AS name_zh,
            mt_es.material_name AS name_es,
            ut_base.unit_name   AS base_unit_name,
            ut_medium.unit_name AS medium_unit_name,
            ut_large.unit_name  AS large_unit_name
        FROM kds_materials m
        LEFT JOIN kds_material_translations mt_zh
            ON m.id = mt_zh.material_id AND mt_zh.language_code = 'zh-CN'
        LEFT JOIN kds_material_translations mt_es
            ON m.id = mt_es.material_id AND mt_es.language_code = 'es-ES'
        LEFT JOIN kds_unit_translations ut_base
            ON m.base_unit_id = ut_base.unit_id AND ut_base.language_code = 'zh-CN'
        LEFT JOIN kds_unit_translations ut_medium
            ON m.medium_unit_id = ut_medium.unit_id AND ut_medium.language_code = 'zh-CN'
        LEFT JOIN kds_unit_translations ut_large
            ON m.large_unit_id = ut_large.unit_id AND ut_large.language_code = 'zh-CN'
        WHERE m.id = ? AND m.deleted_at IS NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

/** ========== Products（与 repo_a 可能重叠，故加防重复） ========== */

if (!function_exists('getAllBaseProducts')) {
    function getAllBaseProducts(PDO $pdo): array {
        $sql = "
            SELECT
                p.id,
                p.product_code,
                tzh.product_name AS name_zh,
                tes.product_name AS name_es
            FROM kds_products p
            LEFT JOIN kds_product_translations tzh ON tzh.product_id = p.id AND tzh.language_code = 'zh-CN'
            LEFT JOIN kds_product_translations tes ON tes.product_id = p.id AND tes.language_code = 'es-ES'
            WHERE p.deleted_at IS NULL
            ORDER BY p.product_code ASC, p.id ASC
        ";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/** ========== Status（与 repo_a 可能重叠，故加防重复） ========== */

if (!function_exists('getAllStatuses')) {
    function getAllStatuses(PDO $pdo): array {
        $sql = "
            SELECT id, status_code, status_name_zh, status_name_es
            FROM kds_product_statuses
            WHERE deleted_at IS NULL
            ORDER BY status_code ASC
        ";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/** ========== Member & Pass ========== */

if (!function_exists('getMemberByPhone')) {
    function getMemberByPhone(PDO $pdo, string $phone): ?array {
        $sql = "SELECT id, member_uuid, phone_number, first_name, last_name, created_at FROM pos_members WHERE phone_number = ? AND deleted_at IS NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$phone]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('getPassPlanById')) {
    function getPassPlanById(PDO $pdo, int $pass_plan_id): ?array {
        $sql = "SELECT pass_plan_id, name, total_uses, validity_days, allocation_strategy FROM pass_plans WHERE pass_plan_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$pass_plan_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('getActiveMemberPass')) {
    function getActiveMemberPass(PDO $pdo, int $member_id): ?array {
        $sql = "
            SELECT 
                member_pass_id, member_id, pass_plan_id, remaining_uses, status, created_at, expires_at
            FROM member_passes
            WHERE member_id = ? AND status = 'active'
            ORDER BY created_at DESC
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$member_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

/** ========== Topup Orders（修正版） ========== */

if (!function_exists('getAllTopupOrders')) {
    function getAllTopupOrders(PDO $pdo): array {
        try {
            $sql = "
                SELECT 
                    tpo.topup_order_id AS order_id, /* 视图 pos_topup_orders_view.php 期望 'order_id' */
                    tpo.review_status AS status, /* 视图期望 'status' */
                    tpo.sale_time,
                    tpo.amount_total,
                    tpo.quantity,
                    tpo.reviewed_at,
                    tpo.voucher_series,
                    tpo.voucher_number,
                    pm.phone_number AS member_phone,
                    pp.name AS plan_name,
                    ks.store_name,
                    cu.display_name AS reviewer_name
                FROM topup_orders tpo
                JOIN pos_members pm ON tpo.member_id = pm.id
                JOIN pass_plans pp ON tpo.pass_plan_id = pp.pass_plan_id
                JOIN kds_stores ks ON tpo.store_id = ks.id
                LEFT JOIN cpsys_users cu ON tpo.reviewed_by_user_id = cu.id
                ORDER BY tpo.sale_time DESC
            ";
            $stmt = $pdo->query($sql);
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (PDOException $e) {
            error_log("Error in getAllTopupOrders: " . $e->getMessage());
            return [];
        }
    }
}

/**
 * 审批并激活会员次卡（保持最小改动，字段对齐 topup_orders）
 * 返回：true | 错误字符串
 */
if (!function_exists('activate_member_pass')) {
    function activate_member_pass(PDO $pdo, int $topup_order_id, int $reviewer_id, string $now_utc_str) {
        try {
            // 1) 读取订单 & 方案
            $sql_order = "
                SELECT tpo.*, pp.total_uses, pp.validity_days
                FROM topup_orders tpo
                JOIN pass_plans pp ON tpo.pass_plan_id = pp.pass_plan_id
                WHERE tpo.topup_order_id = ?
            ";
            $stmt = $pdo->prepare($sql_order);
            $stmt->execute([$topup_order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) return "Topup order not found: {$topup_order_id}";

            // [GEMINI] 检查是否已处理
            if ($order['review_status'] !== 'pending') {
                 return "订单 (ID: {$topup_order_id}) 状态为 '{$order['review_status']}'，无法重复处理。";
            }

            $pdo->beginTransaction();

            // 2) 写入/累加 member_passes
            $quantity = (int)$order['quantity'];
            $uses_add = $quantity * (int)$order['total_uses'];
            $sql_upsert = "
                INSERT INTO member_passes (member_id, pass_plan_id, topup_order_id, total_uses, remaining_uses, purchase_amount, unit_allocated_base, status, store_id, activated_at, expires_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, DATE_ADD(?, INTERVAL ? DAY))
                ON DUPLICATE KEY UPDATE 
                    total_uses = total_uses + VALUES(total_uses),
                    remaining_uses = remaining_uses + VALUES(remaining_uses),
                    purchase_amount = purchase_amount + VALUES(purchase_amount),
                    unit_allocated_base = (purchase_amount + VALUES(purchase_amount)) / (total_uses + VALUES(total_uses)),
                    status = 'active',
                    expires_at = GREATEST(COALESCE(expires_at, '1970-01-01'), VALUES(expires_at))
            ";
            
            // 计算分摊
            $unit_base = ($uses_add > 0) ? ((float)$order['amount_total'] / $uses_add) : 0;

            $stmt2 = $pdo->prepare($sql_upsert);
            $stmt2->execute([
                $order['member_id'],
                $order['pass_plan_id'],
                $topup_order_id,
                $uses_add, // total_uses
                $uses_add, // remaining_uses
                (float)$order['amount_total'],
                $unit_base,
                $order['store_id'],
                $now_utc_str,
                $now_utc_str,
                (int)$order['validity_days']
            ]);

            // 3) 更新订单审核状态（最小改动：直接置为 approved）
            $sql_upd = "
                UPDATE topup_orders
                SET review_status = 'approved',
                    reviewed_by_user_id = ?,
                    reviewed_at = ?
                WHERE topup_order_id = ?
            ";
            $stmt3 = $pdo->prepare($sql_upd);
            $stmt3->execute([$reviewer_id, $now_utc_str, $topup_order_id]);

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("[activate_member_pass] " . $e->getMessage());
            return $e->getMessage();
        }
    }
}

/** ========== Redemption Batches（修正版） ========== */

if (!function_exists('getAllRedemptionBatches')) {
    function getAllRedemptionBatches(PDO $pdo): array {
        try {
            $sql = "
                SELECT 
                    prb.batch_id,
                    prb.created_at AS redeemed_at, /* 视图 pos_redemptions_view.php 期望 'redeemed_at' */
                    prb.redeemed_uses AS uses_redeemed, /* 视图期望 'uses_redeemed' */
                    COALESCE(pr_agg.extra_charge_total, 0) AS extra_charge_total,
                    pm.phone_number AS member_phone,
                    pp.name AS plan_name,
                    ks.store_name,
                    pi.series AS invoice_series,
                    pi.number AS invoice_number
                FROM pass_redemption_batches prb
                JOIN member_passes mp ON prb.member_pass_id = mp.member_pass_id
                JOIN pos_members pm   ON mp.member_id = pm.id
                JOIN pass_plans  pp   ON mp.pass_plan_id = pp.pass_plan_id
                JOIN kds_stores  ks   ON prb.store_id = ks.id
                LEFT JOIN pos_invoices pi ON prb.order_id = pi.id
                LEFT JOIN (
                    SELECT batch_id, SUM(extra_charge) AS extra_charge_total
                    FROM pass_redemptions
                    GROUP BY batch_id
                ) pr_agg ON pr_agg.batch_id = prb.batch_id
                ORDER BY prb.created_at DESC
            ";
            $stmt = $pdo->query($sql);
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (PDOException $e) {
            error_log("Error in getAllRedemptionBatches: " . $e->getMessage());
            return [];
        }
    }
}

/** ========== Dashboard KPIs（修正版） ========== */

if (!function_exists('getSeasonsPassDashboardKpis')) {
    function getSeasonsPassDashboardKpis(PDO $pdo): array {
        $now_utc_str = utc_now()->format('Y-m-d H:i:s');

        try {
            // 1) 活跃次卡
            $stmt_active = $pdo->prepare("
                SELECT COUNT(member_pass_id) 
                FROM member_passes 
                WHERE status = 'active' 
                  AND remaining_uses > 0
                  AND (expires_at IS NULL OR expires_at > ?)
            ");
            $stmt_active->execute([$now_utc_str]);
            $active_passes_count = (int)$stmt_active->fetchColumn();
        } catch (Exception $e) { $active_passes_count = 0; error_log("KPI Error (active_passes): ". $e->getMessage()); }

        try {
            // 2) 累计核销次数（按批次求和）
            $stmt_red = $pdo->query("SELECT COALESCE(SUM(redeemed_uses), 0) FROM pass_redemption_batches");
            $total_redemptions_count = (int)($stmt_red ? $stmt_red->fetchColumn() : 0);
        } catch (Exception $e) { $total_redemptions_count = 0; error_log("KPI Error (total_redemptions): ". $e->getMessage()); }

        try {
            // 3) 累计售卡金额（使用 review_status 口径）
            $stmt_sales = $pdo->query("
                SELECT COALESCE(SUM(amount_total), 0) 
                FROM topup_orders 
                WHERE review_status IN ('confirmed','approved')
            ");
            $total_sales_amount = (float)($stmt_sales ? $stmt_sales->fetchColumn() : 0);
        } catch (Exception $e) { $total_sales_amount = 0.0; error_log("KPI Error (total_sales): ". $e->getMessage()); }

        return [
            'active_passes_count'     => $active_passes_count,
            'total_redemptions_count' => $total_redemptions_count,
            'total_sales_amount'      => $total_sales_amount
        ];
    }
}

/** ========== Dashboard 示例 ========== */

// [GEMINI FIX] 确保此函数在 getTopSellingProductsToday 之前定义
if (!function_exists('getTodayTopItems')) {
    function getTodayTopItems(PDO $pdo, string $tzLocal = 'Europe/Madrid'): array {
        try {
            $tz = new DateTimeZone($tzLocal);
            $todayLocal = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
            $start_local = new DateTimeImmutable($todayLocal . ' 00:00:00', $tz);
            $end_local   = new DateTimeImmutable($todayLocal . ' 23:59:59', $tz);
            $utc = new DateTimeZone('UTC');
            $start = $start_local->setTimezone($utc)->format('Y-m-d H:i:s.u'); // 修复：使用 .u 匹配 issued_at(6)
            $end   = $end_local->setTimezone($utc)->format('Y-m-d H:i:s.u'); // 修复：使用 .u

            $sql = "
                SELECT 
                    pi.item_name_zh,
                    SUM(pi.quantity) AS total_quantity
                FROM pos_invoice_items pi
                JOIN pos_invoices p ON pi.invoice_id = p.id
                WHERE p.issued_at BETWEEN :start AND :end AND p.status = 'ISSUED'
                GROUP BY pi.item_name_zh
                ORDER BY total_quantity DESC
                LIMIT 5
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':start' => $start, ':end' => $end]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
             error_log("Error getTodayTopItems: " . $e->getMessage());
            return [];
        }
    }
}


// [GEMINI FATAL FIX - 2025-11-12] 
// 批量添加 index.php (Dashboard / Shift Review) 所需的缺失函数

if (!function_exists('getPendingShiftReviewCount')) {
    /**
     * [FIX] 获取待处理的强制关闭班次数 (用于侧边栏徽章)
     * (index.php:114)
     */
    function getPendingShiftReviewCount(PDO $pdo): int {
        try {
            $stmt = $pdo->query("SELECT COUNT(id) FROM pos_shifts WHERE status = 'FORCE_CLOSED' AND admin_reviewed = 0");
            return (int)($stmt ? $stmt->fetchColumn() : 0);
        } catch (PDOException $e) {
            error_log("Error getPendingShiftReviewCount: " . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('getPendingShiftReviews')) {
    /**
     * [FIX] 获取待处理的班次复核列表
     * (index.php:316, page=pos_shift_review)
     */
    function getPendingShiftReviews(PDO $pdo): array {
        try {
            // 查询基于 pos_shift_review_view.php 的显示需求
            $sql = "
                SELECT 
                    s.id, s.start_time, s.end_time, s.expected_cash,
                    st.store_name,
                    u.display_name AS user_name
                FROM pos_shifts s
                JOIN kds_stores st ON s.store_id = st.id
                JOIN kds_users u ON s.user_id = u.id
                WHERE s.status = 'FORCE_CLOSED' AND s.admin_reviewed = 0
                ORDER BY s.end_time ASC
            ";
            $stmt = $pdo->query($sql);
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (PDOException $e) {
            error_log("Error getPendingShiftReviews: " . $e->getMessage());
            return [];
        }
    }
}


if (!function_exists('getDashboardKpis')) {
    /**
     * [FIX] 获取仪表盘KPI
     * (index.php:121, page=dashboard)
     */
    function getDashboardKpis(PDO $pdo, string $tzLocal = 'Europe/Madrid'): array {
        try {
            // 1. 获取本地化今天的 UTC 时间范围
            $tz = new DateTimeZone($tzLocal);
            $todayLocal = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
            $start_local = new DateTimeImmutable($todayLocal . ' 00:00:00', $tz);
            $end_local   = new DateTimeImmutable($todayLocal . ' 23:59:59', $tz);
            $utc = new DateTimeZone('UTC');
            $start_utc = $start_local->setTimezone($utc)->format('Y-m-d H:i:s.u'); // 使用 .u 匹配 issued_at(6)
            $end_utc   = $end_local->setTimezone($utc)->format('Y-m-d H:i:s.u');

            // 2. 查询今日销售额和订单数
            $sql_sales = "SELECT COALESCE(SUM(final_total), 0) AS total_sales, COUNT(id) AS total_orders
                          FROM pos_invoices
                          WHERE status = 'ISSUED' AND issued_at BETWEEN ? AND ?";
            $stmt_sales = $pdo->prepare($sql_sales);
            $stmt_sales->execute([$start_utc, $end_utc]);
            $sales_data = $stmt_sales->fetch(PDO::FETCH_ASSOC);

            // 3. 查询今日新增会员
            $sql_members = "SELECT COUNT(id) FROM pos_members WHERE created_at BETWEEN ? AND ?";
            $stmt_members = $pdo->prepare($sql_members);
            $stmt_members->execute([$start_utc, $end_utc]);
            $new_members = (int)$stmt_members->fetchColumn();
            
            // 4. 查询活跃门店数
            $active_stores = (int)$pdo->query("SELECT COUNT(id) FROM kds_stores WHERE is_active = 1 AND deleted_at IS NULL")->fetchColumn();

            return [
                'total_sales' => (float)($sales_data['total_sales'] ?? 0),
                'total_orders' => (int)($sales_data['total_orders'] ?? 0),
                'new_members' => $new_members,
                'active_stores' => $active_stores
            ];
        } catch (Exception $e) {
            error_log("Error getDashboardKpis: " . $e->getMessage());
            return ['total_sales' => 0, 'total_orders' => 0, 'new_members' => 0, 'active_stores' => 0];
        }
    }
}

if (!function_exists('getLowStockAlerts')) {
    /**
     * [FIX] 获取低库存预警
     * (index.php:122, page=dashboard)
     */
    function getLowStockAlerts(PDO $pdo, int $threshold = 10): array {
        try {
            $sql = "
                SELECT 
                    m.id AS material_id, 
                    mt.material_name, 
                    w.quantity, 
                    ut.unit_name AS base_unit_name
                FROM expsys_warehouse_stock w
                JOIN kds_materials m ON w.material_id = m.id
                JOIN kds_material_translations mt ON m.id = mt.material_id AND mt.language_code = 'zh-CN'
                JOIN kds_units u ON m.base_unit_id = u.id
                JOIN kds_unit_translations ut ON u.id = ut.unit_id AND ut.language_code = 'zh-CN'
                WHERE w.quantity < ? AND m.deleted_at IS NULL
                ORDER BY w.quantity ASC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$threshold]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getLowStockAlerts: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('getSalesTrendLast7Days')) {
    /**
     * [FIX] 获取近7日销售趋势
     * (index.php:123, page=dashboard)
     */
    function getSalesTrendLast7Days(PDO $pdo, string $tzLocal = 'Europe/Madrid'): array {
        $labels = [];
        $data = [];
        try {
            $tz = new DateTimeZone($tzLocal);
            $utc = new DateTimeZone('UTC');
            
            for ($i = 6; $i >= 0; $i--) {
                $date = (new DateTimeImmutable("now", $tz))->sub(new DateInterval("P{$i}D"));
                $labels[] = $date->format('Y-m-d');
                
                $start_local = new DateTimeImmutable($date->format('Y-m-d') . ' 00:00:00', $tz);
                $end_local   = new DateTimeImmutable($date->format('Y-m-d') . ' 23:59:59', $tz);
                $start_utc = $start_local->setTimezone($utc)->format('Y-m-d H:i:s.u');
                $end_utc   = $end_local->setTimezone($utc)->format('Y-m-d H:i:s.u');

                $sql = "SELECT COALESCE(SUM(final_total), 0) FROM pos_invoices 
                        WHERE status = 'ISSUED' AND issued_at BETWEEN ? AND ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$start_utc, $end_utc]);
                $data[] = (float)$stmt->fetchColumn();
            }
        } catch (Exception $e) {
            error_log("Error getSalesTrendLast7Days: " . $e->getMessage());
        }
        return ['labels' => $labels, 'data' => $data];
    }
}

if (!function_exists('getTopSellingProductsToday')) {
    /**
     * [FIX] 别名函数，用于修复 index.php:124 的函数名错误
     */
    function getTopSellingProductsToday(PDO $pdo, string $tzLocal = 'Europe/Madrid'): array {
        // index.php 错误地调用了 getTopSellingProductsToday
        // 实际在 kds_repo_c.php 中定义的函数是 getTodayTopItems
        return getTodayTopItems($pdo, $tzLocal);
    }
}

// --- (Fix 2: 补充其他缺失函数) ---

if (!function_exists('getWarehouseStock')) {
    /**
     * [FIX] 获取总仓库存 (index.php:439, page=warehouse_stock_management)
     */
    function getWarehouseStock(PDO $pdo): array {
        try {
            $sql = "
                SELECT 
                    m.id AS material_id, 
                    m.material_type,
                    mt.material_name, 
                    COALESCE(w.quantity, 0) AS quantity,
                    ut.unit_name AS base_unit_name
                FROM kds_materials m
                JOIN kds_material_translations mt ON m.id = mt.material_id AND mt.language_code = 'zh-CN'
                JOIN kds_units u ON m.base_unit_id = u.id
                JOIN kds_unit_translations ut ON u.id = ut.unit_id AND ut.language_code = 'zh-CN'
                LEFT JOIN expsys_warehouse_stock w ON m.id = w.material_id
                WHERE m.deleted_at IS NULL
                ORDER BY m.material_code ASC
            ";
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getWarehouseStock: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('getAllExpiryItems')) {
    /**
     * [FIX] 获取效期物品 (index.php:455, page=expiry_management)
     */
    function getAllExpiryItems(PDO $pdo): array {
        try {
            $sql = "
                SELECT 
                    e.*,
                    s.store_name,
                    mt.material_name,
                    u.display_name AS handler_name
                FROM kds_material_expiries e
                JOIN kds_stores s ON e.store_id = s.id
                JOIN kds_materials m ON e.material_id = m.id
                JOIN kds_material_translations mt ON m.id = mt.material_id AND mt.language_code = 'zh-CN'
                LEFT JOIN kds_users u ON e.handler_id = u.id
                ORDER BY e.status ASC, e.expires_at ASC
            ";
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getAllExpiryItems: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('getAllInvoices')) {
    /**
     * [FIX] 获取票据列表 (index.php:375, page=pos_invoice_list)
     */
    function getAllInvoices(PDO $pdo): array {
        try {
            $sql = "
                SELECT 
                    i.id, i.series, i.number, i.issued_at, i.final_total, 
                    i.status, i.compliance_system,
                    s.store_name
                FROM pos_invoices i
                LEFT JOIN kds_stores s ON i.store_id = s.id
                ORDER BY i.issued_at DESC
                LIMIT 500
            ";
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getAllInvoices: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('getInvoiceDetails')) {
    /**
     * [FIX] 获取单张票据详情 (index.php:387, page=pos_invoice_detail)
     */
    function getInvoiceDetails(PDO $pdo, int $invoice_id): ?array {
        try {
            $sql_main = "
                SELECT i.*, s.store_name, u.display_name AS cashier_name
                FROM pos_invoices i
                JOIN kds_stores s ON i.store_id = s.id
                LEFT JOIN kds_users u ON i.user_id = u.id
                WHERE i.id = ?
            ";
            $stmt_main = $pdo->prepare($sql_main);
            $stmt_main->execute([$invoice_id]);
            $invoice = $stmt_main->fetch(PDO::FETCH_ASSOC);

            if ($invoice) {
                // 解码 JSON 字段
                $invoice['compliance_data_decoded'] = json_decode($invoice['compliance_data'] ?? '{}', true);
                $invoice['payment_summary_decoded'] = json_decode($invoice['payment_summary'] ?? '{}', true);
                
                // 获取 items
                $sql_items = "SELECT * FROM pos_invoice_items WHERE invoice_id = ?";
                $stmt_items = $pdo->prepare($sql_items);
                $stmt_items->execute([$invoice_id]);
                $invoice['items'] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
            }
            return $invoice;
        } catch (PDOException $e) {
            error_log("Error getInvoiceDetails: " . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('getAllEodReports')) {
    /**
     * [FIX] 获取 EOD 报告 (index.php:406, page=pos_eod_reports)
     */
    function getAllEodReports(PDO $pdo): array {
        try {
            $sql = "
                SELECT 
                    r.*,
                    s.store_name,
                    u.display_name AS user_name
                FROM pos_eod_reports r
                JOIN kds_stores s ON r.store_id = s.id
                LEFT JOIN kds_users u ON r.user_id = u.id
                ORDER BY r.report_date DESC, r.executed_at DESC
            ";
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getAllEodReports: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('getAllPosAddons')) {
    /**
     * [FIX] 别名函数 (index.php:274, page=pos_addon_management)
     * kds_repo_c.php 中定义的是 getAllAddons
     */
    function getAllPosAddons(PDO $pdo): array {
        try {
            // 视图 (pos_addon_management_view.php) 需要 material_name_zh
            $sql = "
                SELECT 
                    a.*,
                    mt.material_name AS material_name_zh
                FROM pos_addons a
                LEFT JOIN kds_materials m ON a.material_id = m.id
                LEFT JOIN kds_material_translations mt ON m.id = mt.material_id AND mt.language_code = 'zh-CN'
                WHERE a.deleted_at IS NULL
                ORDER BY a.sort_order ASC, a.id ASC
            ";
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getAllPosAddons: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('getAllStoreStock')) {
    /**
     * [FIX] 获取所有门店库存 (index.php:447, page=store_stock_view)
     */
    function getAllStoreStock(PDO $pdo): array {
        $grouped_stock = [];
        try {
            $sql = "
                SELECT 
                    s.store_name,
                    mt.material_name,
                    COALESCE(ss.quantity, 0) AS quantity,
                    ut.unit_name AS base_unit_name
                FROM expsys_store_stock ss
                JOIN kds_stores s ON ss.store_id = s.id
                JOIN kds_materials m ON ss.material_id = m.id
                JOIN kds_material_translations mt ON m.id = mt.material_id AND mt.language_code = 'zh-CN'
                JOIN kds_units u ON m.base_unit_id = u.id
                JOIN kds_unit_translations ut ON u.id = ut.unit_id AND ut.language_code = 'zh-CN'
                WHERE m.deleted_at IS NULL AND ss.quantity != 0
                ORDER BY s.store_name ASC, mt.material_name ASC
            ";
            $stmt = $pdo->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $grouped_stock[$row['store_name']][] = $row;
            }
        } catch (PDOException $e) {
            error_log("Error getAllStoreStock: " . $e->getMessage());
        }
        return $grouped_stock;
    }
}
