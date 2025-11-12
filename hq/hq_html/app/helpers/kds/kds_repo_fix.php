<?php
/**
 * KDS Repo FIX - Minimal helper implementations required by registries
 * Purpose: Provide getUnitById, getMaterialById, getNextAvailableCustomCode
 * to avoid undefined-function fatals during Units / Materials operations.
 * Date: 2025-11-07 (Fix: Add image_url to getMaterialById)
 *
 * [GEMINI 500-FATAL-FIX (V3.0.0)]
 * - Added !function_exists() wrappers to all functions to prevent
 * redeclaration fatal errors from kds_repo_a.php.
 */

if (!function_exists('getUnitById')) {
    function getUnitById(PDO $pdo, int $id) {
        $sql = "SELECT 
                    u.id, u.unit_code,
                    tz.unit_name AS name_zh,
                    te.unit_name AS name_es
                FROM kds_units u
                LEFT JOIN kds_unit_translations tz 
                    ON tz.unit_id = u.id AND tz.language_code = 'zh-CN'
                LEFT JOIN kds_unit_translations te 
                    ON te.unit_id = u.id AND te.language_code = 'es-ES'
                WHERE u.id = ? AND u.deleted_at IS NULL
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

if (!function_exists('getMaterialById')) {
    function getMaterialById(PDO $pdo, int $id) {
        $sql = "SELECT 
                    id, material_code, material_type,
                    base_unit_id, 
                    medium_unit_id, medium_conversion_rate,
                    large_unit_id,  large_conversion_rate,
                    expiry_rule_type, expiry_duration,
                    image_url
                FROM kds_materials 
                WHERE id = ? AND deleted_at IS NULL
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

if (!function_exists('getNextAvailableCustomCode')) {
    /**
     * 返回指定表与列的下一个可用“数字编码”（字符串），尽量取最小缺口
     * 仅允许白名单中的表与列，避免 SQL 注入风险
     */
    function getNextAvailableCustomCode(PDO $pdo, string $table, string $column, int $start = 1): string {
        $whitelist = [
            'kds_units'              => ['unit_code'],
            'kds_materials'          => ['material_code'],
            'kds_ice_options'        => ['ice_code'],
            'kds_sweetness_options'  => ['sweetness_code'],
            'kds_products'           => ['product_code'],
        ];
        if (!isset($whitelist[$table]) || !in_array($column, $whitelist[$table], true)) {
            // 不在白名单：为了安全，直接返回起始值（避免拼接 SQL）
            return (string)$start;
        }

        $sql = "SELECT {$column} AS code FROM {$table} WHERE deleted_at IS NULL";
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $used = [];
        foreach ($rows as $r) {
            $code = (string)($r['code'] ?? '');
            if ($code !== '' && preg_match('/^\d+$/', $code)) {
                $used[(int)$code] = true;
            }
        }

        $n = max(1, (int)$start);
        while (isset($used[$n])) {
            $n++;
            if ($n > 9999999) { break; } // 安全护栏
        }
        return (string)$n;
    }
}
// --- RMS: Global Adjustment Rules (Layer 2) ---
// Minimal fallback: return all rules for RMS Global Rules page
if (!function_exists('getAllGlobalRules')) {
    /**
     * Fetch all global adjustment rules.
     * Expected by /html/cpsys/index.php when page=rms_global_rules
     * Columns consumed by the view:
     *  id, rule_name, priority, is_active,
     *  cond_cup_id, cond_ice_id, cond_sweet_id, cond_material_id,
     *  cond_base_gt, cond_base_lte,
     *  action_type, action_value, action_unit_id, action_material_id
     */
    function getAllGlobalRules(PDO $pdo): array {
        try {
            $sql = "SELECT
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
                        action_value,
                        action_unit_id,
                        action_material_id
                    FROM kds_global_adjustment_rules
                    ORDER BY is_active DESC, priority ASC, id ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as &$r) {
                foreach (['id','priority','is_active','cond_cup_id','cond_ice_id','cond_sweet_id','cond_material_id','action_unit_id','action_material_id'] as $k) {
                    if (isset($r[$k]) && $r[$k] !== null) $r[$k] = (int)$r[$k];
                }
                foreach (['cond_base_gt','cond_base_lte','action_value'] as $k) {
                    if (isset($r[$k]) && $r[$k] !== null) $r[$k] = (float)$r[$k];
                }
            }
            unset($r);

            return $rows;
        } catch (PDOException $e) {
            if ($e->getCode() === '42S02') {
                // 表不存在：页面将显示“暂无规则”，不致命
                error_log('Notice: kds_global_adjustment_rules not found: '.$e->getMessage());
                return [];
            }
            throw $e;
        }
    }
}

// --- RMS: Base Products (L1) ---
if (!function_exists('getAllBaseProducts')) {
    /**
     * Return minimal fields used by rms_product_management_view:
     *  id, product_code, name_zh
     */
    function getAllBaseProducts(PDO $pdo): array {
        try {
            $sql = "SELECT
                        p.id,
                        p.product_code,
                        tzh.product_name AS name_zh
                    FROM kds_products p
                    LEFT JOIN kds_product_translations tzh
                        ON tzh.product_id = p.id AND tzh.language_code = 'zh-CN'
                    WHERE p.deleted_at IS NULL
                    ORDER BY p.product_code ASC, p.id ASC";
            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as &$r) {
                if (isset($r['id'])) $r['id'] = (int)$r['id'];
                if (!isset($r['name_zh'])) $r['name_zh'] = '';
                if (!isset($r['product_code'])) $r['product_code'] = '';
            }
            unset($r);
            return $rows;
        } catch (PDOException $e) {
            if ($e->getCode() === '42S02') {
                // 缺表时返回空集，避免致命
                error_log('Notice: kds_products or kds_product_translations not found: '.$e->getMessage());
                return [];
            }
            throw $e;
        }
    }
}
