<?php
/**
 * KDS Repo A - 数据仓库：物料 / 单位 / 门店 / 会员 / 促销 / 模板
 * 注意：本文件仅提供“查询/只读/简单写”仓库函数；不包含 API 处理器；不包含 return。
 * 末尾不使用 "?>"，避免输出空白导致 header 已发送。
 *
 * [GEMINI 500-FATAL-FIX (V1.0.1)]
 * - 删除了与 kds_repo_c.php 中重复定义的4个函数：
 * - getAllSweetnessOptions
 * - getAllIceOptions
 * - getAllCups
 * - getAllStatuses
 * (kds_repo_c.php 中的版本是正确的，包含双语支持)
 *
 * [GEMINI 500-FATAL-FIX (V2.0.0)]
 * - 删除了与 kds_repo_b.php 中重复定义的4个函数：
 * - getAllMenuItems
 * - getMenuItemById
 * - getAllVariantsByMenuItemId
 * - getAllMenuItemsForSelect
 * (kds_repo_b.php 中的版本是正确的)
 */

/* ---------- 通用：编号工具 ---------- */
function getNextAvailableCustomCode(PDO $pdo, string $tableName, string $codeColumnName, int $start_from = 1): int {
    $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    $codeColumnName = preg_replace('/[^a-zA-Z0-9_]/', '', $codeColumnName);

    $sql = "SELECT {$codeColumnName}
            FROM {$tableName}
            WHERE deleted_at IS NULL
              AND {$codeColumnName} >= :start_from
            ORDER BY {$codeColumnName} ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':start_from' => $start_from]);
    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    $i = $start_from;
    while (in_array($i, $existing, true)) $i++;
    return $i;
}

/* ---------- 单位 / 物料 ---------- */
function getAllUnits(PDO $pdo): array {
    $sql = "SELECT
                u.id,
                u.unit_code,
                ut_zh.unit_name AS name_zh,
                ut_es.unit_name AS name_es
            FROM kds_units u
            LEFT JOIN kds_unit_translations ut_zh
                ON u.id = ut_zh.unit_id AND ut_zh.language_code = 'zh-CN'
            LEFT JOIN kds_unit_translations ut_es
                ON u.id = ut_es.unit_id AND ut_es.language_code = 'es-ES'
            WHERE u.deleted_at IS NULL
            ORDER BY u.unit_code ASC";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function getUnitById(PDO $pdo, int $id) {
    $sql = "SELECT
                u.id,
                u.unit_code,
                ut_zh.unit_name AS name_zh,
                ut_es.unit_name AS name_es
            FROM kds_units u
            LEFT JOIN kds_unit_translations ut_zh
                ON u.id = ut_zh.unit_id AND ut_zh.language_code = 'zh-CN'
            LEFT JOIN kds_unit_translations ut_es
                ON u.id = ut_es.unit_id AND ut_es.language_code = 'es-ES'
            WHERE u.id = ? AND u.deleted_at IS NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

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

function getMaterialById(PDO $pdo, int $id) {
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

/* ---------- 门店 / KDS 用户 ---------- */
function getAllStores(PDO $pdo): array {
    $sql = "SELECT * FROM kds_stores
            WHERE deleted_at IS NULL
            ORDER BY store_code ASC";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function getStoreById(PDO $pdo, int $id) {
    // [!! 修复 !!] 删除了第 151 行不存在的列 (printer_type, printer_ip, printer_port, printer_mac)
    // SELECT * 已经包含了所有数据库中存在的列 (pr_receipt_type, pr_sticker_type 等)
    $stmt = $pdo->prepare("
        SELECT *
        FROM kds_stores
        WHERE id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getAllKdsUsersByStoreId(PDO $pdo, int $store_id): array {
    $stmt = $pdo->prepare("
        SELECT id, username, display_name, role, is_active, last_login_at
        FROM kds_users
        WHERE store_id = ? AND deleted_at IS NULL
        ORDER BY id ASC
    ");
    $stmt->execute([$store_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getKdsUserById(PDO $pdo, int $id) {
    $stmt = $pdo->prepare("
        SELECT id, username, display_name, role, is_active, store_id
        FROM kds_users
        WHERE id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/* ---------- POS 分类 / 促销 ---------- */
function getAllPosCategories(PDO $pdo): array {
    $sql = "SELECT *
            FROM pos_categories
            WHERE deleted_at IS NULL
            ORDER BY sort_order ASC, id ASC";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function getAllPromotions(PDO $pdo): array {
    $sql = "SELECT id, promo_name, promo_trigger_type, promo_start_date, promo_end_date, promo_is_active
            FROM pos_promotions
            ORDER BY promo_priority ASC, id DESC";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function getPromotionById(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM pos_promotions WHERE id = ?");
    $stmt->execute([$id]);
    $promo = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($promo) {
        $promo['promo_conditions'] = json_decode($promo['promo_conditions'], true);
        $promo['promo_actions']    = json_decode($promo['promo_actions'], true);
    }
    return $promo ?: null;
}

/* ---------- 会员 / 等级 ---------- */
function getAllMemberLevels(PDO $pdo): array {
    $sql = "
        SELECT pml.*, pp.promo_name
        FROM pos_member_levels pml
        LEFT JOIN pos_promotions pp
            ON pml.level_up_promo_id = pp.id
        ORDER BY pml.sort_order ASC, pml.points_threshold ASC";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function getMemberLevelById(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM pos_member_levels WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function getAllMembers(PDO $pdo): array {
    $sql = "
        SELECT m.*, ml.level_name_zh
        FROM pos_members m
        LEFT JOIN pos_member_levels ml
            ON m.member_level_id = ml.id
        WHERE m.deleted_at IS NULL
        ORDER BY m.id DESC";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function getMemberById(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM pos_members WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/* ---------- 兑换规则 / 打印模板 ---------- */
function getAllRedemptionRules(PDO $pdo): array {
    $sql = "SELECT r.*, p.promo_name
            FROM pos_point_redemption_rules r
            LEFT JOIN pos_promotions p
                ON r.reward_promo_id = p.id
            WHERE r.deleted_at IS NULL
            ORDER BY r.points_required ASC, r.id ASC";
    try {
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        if ($e->getCode() === '42S02') {
            error_log('Warning: pos_point_redemption_rules missing: '.$e->getMessage());
            return [];
        }
        throw $e;
    }
}

function getAllPrintTemplates(PDO $pdo): array {
    try {
        $stmt = $pdo->query("SELECT * FROM pos_print_templates ORDER BY template_type, template_name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        if ($e->getCode() === '42S02') {
            error_log('Warning: pos_print_templates missing: '.$e->getMessage());
            return [];
        }
        throw $e;
    }
}

/* ---------- [GEMINI 500-FATAL-FIX] 移除的函数 ---------- */
// getAllSweetnessOptions, getAllIceOptions, getAllCups, getAllStatuses
// (这些函数在 kds_repo_c.php 中被重新定义，导致冲突)

/* ---------- [GEMINI 500-FATAL-FIX V2] 移除的函数 ---------- */
// getAllMenuItems, getMenuItemById, getAllVariantsByMenuItemId, getAllMenuItemsForSelect
// (这些函数在 kds_repo_b.php 中被重新定义，导致冲突)

/* ---------- 总部用户 (遗留在此) ---------- */
function getAllUsers(PDO $pdo): array {
    $sql = "
        SELECT 
            u.id, u.username, u.display_name, u.email, u.is_active, u.last_login_at, r.role_name
        FROM cpsys_users u
        JOIN cpsys_roles r ON u.role_id = r.id
        WHERE u.deleted_at IS NULL
        ORDER BY u.id ASC
    ";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

function getAllRoles(PDO $pdo): array {
    $stmt = $pdo->query("SELECT id, role_name FROM cpsys_roles ORDER BY id ASC");
    return $stmt->fetchAll();
}

function getUserById(PDO $pdo, int $id) {
    $stmt = $pdo->prepare("SELECT id, username, display_name, email, role_id, is_active FROM cpsys_users WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    return $stmt->fetch();
}