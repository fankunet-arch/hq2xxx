<?php
/**
 * Toptea HQ - CPSYS API 注册表 (Base System)
 * 注册核心系统资源 (用户, 门店, 字典, 打印模板等)
 *
 * Revision: 1.2.070 (Invoice Prefix & Multi-Printer Refactor)
 *
 * [GEMINI SECURITY FIX V1.0 - 2025-11-10]
 * - Fixed handle_profile_save() to use password_verify() and password_hash(..., PASSWORD_BCRYPT)
 * - This resolves the critical hash mismatch with login_handler and user_management.
 */

// 确保助手已加载 (网关会处理，但作为保险)
require_once realpath(__DIR__ . '/../../../../app/helpers/kds_helper.php');
require_once realpath(__DIR__ . '/../../../../app/helpers/auth_helper.php');

/* ===== Fallback helpers for Units & Next-Code (only if missing) ===== */
if (!function_exists('getUnitById')) {
    function getUnitById(PDO $pdo, int $id): ?array {
        $sql = "
            SELECT
                u.*,
                zh.unit_name AS name_zh,
                es.unit_name AS name_es
            FROM kds_units u
            LEFT JOIN kds_unit_translations zh
                ON zh.unit_id = u.id AND zh.language_code = 'zh-CN'
            LEFT JOIN kds_unit_translations es
                ON es.unit_id = u.id AND es.language_code = 'es-ES'
            WHERE u.id = ? AND u.deleted_at IS NULL
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
/* ===== end fallback ===== */

/**
 * [V2] 助手: 将 V1 规则配置转为 V2 (用于前端编辑)
 */
function convert_v1_config_to_v2_for_editing(array $data): array {
    $config_json = $data['config_json'] ?? '{}';
    $config = json_decode($config_json, true);
    if (json_last_error() !== JSON_ERROR_NONE) $config = [];

    // 已经是 V2 (或无法识别)，直接返回
    if (isset($config['template']) || empty($config)) {
         $data['config'] = $config; // JS 读取 .config
         unset($data['config_json']);
         return $data;
    }

    $v2_config = [
        'template' => '',
        'mapping' => [ 'p' => 'P', 'a' => 'A', 'm' => 'M', 't' => 'T', 'ord' => 'ORD' ] // 默认映射
    ];

    if ($data['extractor_type'] === 'DELIMITER') {
        $format = $config['format'] ?? 'P-A-M-T';
        $separator = $config['separator'] ?? '-';
        $prefix = $config['prefix'] ?? '';
        
        $template_string = str_replace(
            ['P', 'A', 'M', 'T'],
            ['{P}', '{A}', '{M}', '{T}'],
            $format
        );
        $v2_config['template'] = $prefix . $template_string;
        // Delimiter 模式使用 V2 默认映射即可
        
    } elseif ($data['extractor_type'] === 'KEY_VALUE') {
        // V1 的 KeyValue 格式: { "P_key": "p", "A_key": "c", ... }
        // V2 的 模板格式: { "template": "?p={P}&c={A}", "mapping": { "p": "P", "a": "A" } }
        
        $p_key = $config['P_key'] ?? 'p'; // V1 key
        $a_key = $config['A_key'] ?? '';
        $m_key = $config['M_key'] ?? '';
        $t_key = $config['T_key'] ?? '';
        
        // V2 占位符 (P/A/M/T)
        $p_placeholder = 'P';
        $a_placeholder = 'A';
        $m_placeholder = 'M';
        $t_placeholder = 'T';

        $template_parts = [];
        $v2_mapping = [];
        
        if ($p_key) {
            $template_parts[] = "{$p_key}={{{$p_placeholder}}}";
            $v2_mapping['p'] = $p_placeholder;
        }
        if ($a_key) {
            $template_parts[] = "{$a_key}={{{$a_placeholder}}}";
            $v2_mapping['a'] = $a_placeholder;
        }
        if ($m_key) {
            $template_parts[] = "{$m_key}={{{$m_placeholder}}}";
            $v2_mapping['m'] = $m_placeholder;
        }
        if ($t_key) {
            $template_parts[] = "{$t_key}={{{$t_placeholder}}}";
            $v2_mapping['t'] = $t_placeholder;
        }

        $v2_config['template'] = '?' . implode('&', $template_parts);
        $v2_config['mapping'] = $v2_mapping;
    }
    
    $data['config'] = $v2_config;
    unset($data['config_json']);
    return $data;
}

/**
 * 处理器: KDS SOP 解析规则 (kds_sop_query_rules)
 */
function handle_kds_rule_get_list(PDO $pdo, array $config, array $input_data): void {
    // 按门店优先、优先级排序
    $sql = "
        SELECT
            r.id, r.store_id, r.rule_name, r.priority, r.is_active, r.config_json,
            s.store_name
        FROM kds_sop_query_rules r
        LEFT JOIN kds_stores s ON r.store_id = s.id
        ORDER BY
            r.store_id IS NOT NULL DESC, -- 门店专属规则优先 (NULLs last)
            s.store_name ASC,
            r.priority ASC
    ";
    $stmt = $pdo->query($sql);
    json_ok($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function handle_kds_rule_get(PDO $pdo, array $config, array $input_data): void {
    $id = $_GET['id'] ?? json_error('缺少 id', 400);
    $stmt = $pdo->prepare("SELECT * FROM kds_sop_query_rules WHERE id = ?");
    $stmt->execute([(int)$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($data) {
        // [V2] 自动转换 V1/V2 格式
        $data = convert_v1_config_to_v2_for_editing($data);
        json_ok($data);
    } else {
        json_error('未找到规则', 404);
    }
}

function handle_kds_rule_save(PDO $pdo, array $config, array $input_data): void {
    // V2: JS 直接提交了所有字段，不再需要 'data' 包装器
    $data = $input_data;
    
    $id = $data['id'] ? (int)$data['id'] : null;

    // 1. 基础字段
    $rule_name = trim($data['rule_name'] ?? '');
    $priority = (int)($data['priority'] ?? 100);
    $is_active = (int)($data['is_active'] ?? 0);
    $store_id = !empty($data['store_id']) ? (int)$data['store_id'] : null; // 0 或 '' 视为 NULL

    if (empty($rule_name)) {
        json_error('规则名称不能为空。', 400);
    }

    // 2. V2 模板配置
    // V2 JS 提交的是 config_json (字符串) 和 extractor_type (TEMPLATE_V2)
    $config_json_string = $data['config_json'] ?? null;
    $extractor_type = $data['extractor_type'] ?? 'TEMPLATE_V2'; // 默认为 V2
    
    // 校验 V2 JSON
    if (empty($config_json_string)) {
        json_error('配置 JSON 不能为空。', 400);
    }
    $config_decoded = json_decode($config_json_string, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
         json_error('配置 JSON 格式无效。', 400);
    }
    if (empty($config_decoded['template']) || empty($config_decoded['mapping'])) {
         json_error('V2 配置必须包含 "template" 和 "mapping"。', 400);
    }
    
    // 3. 准备 SQL 参数
    $params = [
        ':store_id' => $store_id,
        ':rule_name' => $rule_name,
        ':priority' => $priority,
        ':is_active' => $is_active,
        ':extractor_type' => $extractor_type, // 存储 "TEMPLATE_V2"
        ':config_json' => $config_json_string // 存储 V2 JSON 字符串
    ];

    if ($id) {
        $params[':id'] = $id;
        $sql = "UPDATE kds_sop_query_rules SET
                    store_id = :store_id, rule_name = :rule_name, priority = :priority,
                    is_active = :is_active, extractor_type = :extractor_type, config_json = :config_json
                WHERE id = :id";
        $message = 'SOP 解析规则已更新。';
    } else {
        $sql = "INSERT INTO kds_sop_query_rules
                    (store_id, rule_name, priority, is_active, extractor_type, config_json)
                VALUES
                    (:store_id, :rule_name, :priority, :is_active, :extractor_type, :config_json)";
        $message = 'SOP 解析规则已创建。';
    }

    $pdo->prepare($sql)->execute($params);
    json_ok(['id' => $id ?? $pdo->lastInsertId()], $message);
}

function handle_kds_rule_delete(PDO $pdo, array $config, array $input_data): void {
    $id = $input_data['id'] ?? json_error('缺少 id', 400);
    $id = (int)$id;

    if ($id === 1) {
        json_error('删除失败：无法删除 ID 为 1 的 KDS 内部标准规则。', 403);
    }

    $stmt = $pdo->prepare("DELETE FROM kds_sop_query_rules WHERE id = ?");
    $stmt->execute([$id]);
    json_ok(null, 'SOP 解析规则已删除。');
}


// --- START: 缺失的处理器 (Handlers for missing resources) ---

// --- 处理器: HQ 用户 (users) ---
function handle_user_get(PDO $pdo, array $config, array $input_data): void {
    $id = $_GET['id'] ?? json_error('缺少 id', 400);
    $data = getUserById($pdo, (int)$id);
    $data ? json_ok($data) : json_error('未找到用户', 404);
}
function handle_user_save(PDO $pdo, array $config, array $input_data): void {
    // 兼容 {user:{...}} 或 {data:{...}}
    $u = $input_data['user'] ?? $input_data['data'] ?? json_error('缺少 data', 400);

    $id           = (int)($u['id'] ?? 0);
    $username     = trim((string)($u['username'] ?? ''));
    $display_name = trim((string)($u['display_name'] ?? ''));
    $email        = trim((string)($u['email'] ?? ''));
    $role_id      = (int)($u['role_id'] ?? 0);
    $is_active    = isset($u['is_active']) ? (int)!!$u['is_active'] : 1;

    // 新密码字段可能叫 new_password 或 password（兼容旧前端）
    $new_password = (string)($u['new_password'] ?? $u['password'] ?? '');

    // 基本校验
    if ($id <= 0) {
        if ($username === '' || $new_password === '' || $role_id <= 0) {
            json_error('新增用户：username / password / role 为必填。', 400);
        }
    } else {
        if ($username === '' || $role_id <= 0) {
            json_error('更新用户：username / role 为必填。', 400);
        }
    }

    // 规范化：空字符串转 NULL（避免严格模式写入错误）
    $email        = ($email === '') ? null : $email;
    $display_name = ($display_name === '') ? null : $display_name;

    $pdo->beginTransaction();
    try {
        // 唯一性（username）
        if ($id > 0) {
            $q = $pdo->prepare("SELECT id FROM cpsys_users WHERE username=? AND id<>? AND deleted_at IS NULL");
            $q->execute([$username, $id]);
            if ($q->fetchColumn()) { $pdo->rollBack(); json_error('用户名已存在：'.$username, 409); }
        } else {
            $q = $pdo->prepare("SELECT id FROM cpsys_users WHERE username=? AND deleted_at IS NULL");
            $q->execute([$username]);
            if ($q->fetchColumn()) { $pdo->rollBack(); json_error('用户名已存在：'.$username, 409); }
        }

        if ($id > 0) {
            // 更新：只有在给了新密码时才更新 password_hash
            if ($new_password !== '') {
                $hash = password_hash($new_password, PASSWORD_BCRYPT);
                $sql = "UPDATE cpsys_users
                           SET username=?, display_name=?, email=?, role_id=?, is_active=?, password_hash=?
                         WHERE id=?";
                $pdo->prepare($sql)->execute([
                    $username, $display_name, $email, $role_id, $is_active, $hash, $id
                ]);
            } else {
                $sql = "UPDATE cpsys_users
                           SET username=?, display_name=?, email=?, role_id=?, is_active=?
                         WHERE id=?";
                $pdo->prepare($sql)->execute([
                    $username, $display_name, $email, $role_id, $is_active, $id
                ]);
            }
            $pdo->commit();
            json_ok(['id'=>$id], '用户已更新。');
        } else {
            // 新增
            $hash = password_hash($new_password, PASSWORD_BCRYPT);
            $sql = "INSERT INTO cpsys_users (username, password_hash, email, display_name, is_active, role_id)
                    VALUES (?,?,?,?,?,?)";
            $pdo->prepare($sql)->execute([$username, $hash, $email, $display_name, $is_active, $role_id]);
            $newId = (int)$pdo->lastInsertId();
            $pdo->commit();
            json_ok(['id'=>$newId], '用户已创建。');
        }
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_error('数据库错误（users）', 500, ['debug' => $e->getMessage()]);
    }
}

function handle_user_delete(PDO $pdo, array $config, array $input_data): void {
    $id = $input_data['id'] ?? json_error('缺少 id', 400);
    $stmt = $pdo->prepare("UPDATE cpsys_users SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([(int)$id]);
    json_ok(null, '用户已成功删除。');
}

// --- 处理器: 门店 (stores) ---
function handle_store_get(PDO $pdo, array $config, array $input_data): void {
    $id = $_GET['id'] ?? json_error('缺少 id', 400);
    // [GEMINI V17.0 REFACTOR] getStoreById 已经存在于 kds_repo_a.php
    $data = getStoreById($pdo, (int)$id);
    $data ? json_ok($data) : json_error('未找到门店', 404);
}
function handle_store_save(PDO $pdo, array $config, array $input_data): void {
    $s = $input_data['store'] ?? $input_data['data'] ?? json_error('缺少 data', 400);

    $id         = (int)($s['id'] ?? 0);
    $store_code = trim((string)($s['store_code'] ?? ''));
    $store_name = trim((string)($s['store_name'] ?? ''));
    // [MODIFIED 1.3] 读取新字段
    $invoice_prefix = trim((string)($s['invoice_prefix'] ?? ''));

    // 基本校验
    if ($store_code === '' || $store_name === '') {
        json_error('门店编码与名称为必填。', 400);
    }
    // [MODIFIED 1.3] 添加新校验
    if ($invoice_prefix === '' || !preg_match('/^[A-Za-z0-9]+$/', $invoice_prefix)) {
        json_error('票号前缀为必填项，且只能包含字母和数字。', 400);
    }

    // 允许为空的字段 → NULL
    $tax_id       = ($s['tax_id'] ?? '') === '' ? null : trim((string)$s['tax_id']);
    $store_city   = ($s['store_city'] ?? '') === '' ? null : trim((string)$s['store_city']);
    $store_addr   = ($s['store_address'] ?? '') === '' ? null : (string)$s['store_address'];
    $store_phone  = ($s['store_phone'] ?? '') === '' ? null : trim((string)$s['store_phone']);
    $store_cif    = ($s['store_cif'] ?? '') === '' ? null : trim((string)$s['store_cif']);

    // NOT NULL 数字字段（给默认）
    $default_vat_rate = (string)($s['default_vat_rate'] ?? '') === '' ? 10.00 : (float)$s['default_vat_rate'];
    $eod_cutoff_hour  = (string)($s['eod_cutoff_hour'] ?? '') === '' ? 3 : (int)$s['eod_cutoff_hour'];

    // ENUM 规范化
    $billing_allowed = ['TICKETBAI','VERIFACTU','NONE'];
    $billing_system = strtoupper(trim((string)($s['billing_system'] ?? 'NONE')));
    if (!in_array($billing_system, $billing_allowed, true)) $billing_system = 'NONE';

    // [MODIFIED 1.3] 读取所有12个新打印机字段
    $printer_allowed = ['NONE','WIFI','BLUETOOTH','USB'];
    
    $pr_receipt_type = strtoupper(trim((string)($s['pr_receipt_type'] ?? 'NONE')));
    $pr_receipt_type = in_array($pr_receipt_type, $printer_allowed, true) ? $pr_receipt_type : 'NONE';
    $pr_receipt_ip   = ($pr_receipt_type === 'WIFI') ? ($s['pr_receipt_ip'] ?? null) : null;
    $pr_receipt_port = ($pr_receipt_type === 'WIFI') ? ($s['pr_receipt_port'] ?? null) : null;
    $pr_receipt_mac  = ($pr_receipt_type === 'BLUETOOTH') ? ($s['pr_receipt_mac'] ?? null) : null;
    
    $pr_sticker_type = strtoupper(trim((string)($s['pr_sticker_type'] ?? 'NONE')));
    $pr_sticker_type = in_array($pr_sticker_type, $printer_allowed, true) ? $pr_sticker_type : 'NONE';
    $pr_sticker_ip   = ($pr_sticker_type === 'WIFI') ? ($s['pr_sticker_ip'] ?? null) : null;
    $pr_sticker_port = ($pr_sticker_type === 'WIFI') ? ($s['pr_sticker_port'] ?? null) : null;
    $pr_sticker_mac  = ($pr_sticker_type === 'BLUETOOTH') ? ($s['pr_sticker_mac'] ?? null) : null;
    
    $pr_kds_type = strtoupper(trim((string)($s['pr_kds_type'] ?? 'NONE')));
    $pr_kds_type = in_array($pr_kds_type, $printer_allowed, true) ? $pr_kds_type : 'NONE';
    $pr_kds_ip   = ($pr_kds_type === 'WIFI') ? ($s['pr_kds_ip'] ?? null) : null;
    $pr_kds_port = ($pr_kds_type === 'WIFI') ? ($s['pr_kds_port'] ?? null) : null;
    $pr_kds_mac  = ($pr_kds_type === 'BLUETOOTH') ? ($s['pr_kds_mac'] ?? null) : null;

    $is_active = isset($s['is_active']) ? (int)!!$s['is_active'] : 1;

    $pdo->beginTransaction();
    try {
        // 唯一性（store_code）
        if ($id > 0) {
            $q = $pdo->prepare("SELECT id FROM kds_stores WHERE store_code=? AND id<>? AND deleted_at IS NULL");
            $q->execute([$store_code, $id]);
            if ($q->fetchColumn()) { $pdo->rollBack(); json_error('门店编码已存在：'.$store_code, 409); }
        } else {
            $q = $pdo->prepare("SELECT id FROM kds_stores WHERE store_code=? AND deleted_at IS NULL");
            $q->execute([$store_code]);
            if ($q->fetchColumn()) { $pdo->rollBack(); json_error('门店编码已存在：'.$store_code, 409); }
        }

        // [MODIFIED 1.3] 唯一性（invoice_prefix）
        if ($id > 0) {
            $q = $pdo->prepare("SELECT id FROM kds_stores WHERE invoice_prefix=? AND id<>? AND deleted_at IS NULL");
            $q->execute([$invoice_prefix, $id]);
            if ($q->fetchColumn()) { $pdo->rollBack(); json_error('票号前缀已存在：'.$invoice_prefix, 409); }
        } else {
            $q = $pdo->prepare("SELECT id FROM kds_stores WHERE invoice_prefix=? AND deleted_at IS NULL");
            $q->execute([$invoice_prefix]);
            if ($q->fetchColumn()) { $pdo->rollBack(); json_error('票号前缀已存在：'.$invoice_prefix, 409); }
        }

        if ($id > 0) {
            // [MODIFIED 1.3] 更新 UPDATE 语句
            $sql = "UPDATE kds_stores
                       SET store_code=?, store_name=?, invoice_prefix=?, tax_id=?, default_vat_rate=?,
                           store_city=?, store_address=?, store_phone=?, store_cif=?, is_active=?,
                           billing_system=?, eod_cutoff_hour=?, 
                           pr_receipt_type=?, pr_receipt_ip=?, pr_receipt_port=?, pr_receipt_mac=?,
                           pr_sticker_type=?, pr_sticker_ip=?, pr_sticker_port=?, pr_sticker_mac=?,
                           pr_kds_type=?, pr_kds_ip=?, pr_kds_port=?, pr_kds_mac=?
                     WHERE id=?";
            $pdo->prepare($sql)->execute([
                $store_code, $store_name, $invoice_prefix, $tax_id, $default_vat_rate,
                $store_city, $store_addr, $store_phone, $store_cif, $is_active,
                $billing_system, $eod_cutoff_hour,
                $pr_receipt_type, $pr_receipt_ip, $pr_receipt_port, $pr_receipt_mac,
                $pr_sticker_type, $pr_sticker_ip, $pr_sticker_port, $pr_sticker_mac,
                $pr_kds_type, $pr_kds_ip, $pr_kds_port, $pr_kds_mac,
                $id
            ]);
            $pdo->commit();
            json_ok(['id'=>$id], '门店已更新。');
        } else {
            // [MODIFIED 1.3] 更新 INSERT 语句
            $sql = "INSERT INTO kds_stores
                        (store_code, store_name, invoice_prefix, tax_id, default_vat_rate,
                         store_city, store_address, store_phone, store_cif, is_active,
                         billing_system, eod_cutoff_hour, 
                         pr_receipt_type, pr_receipt_ip, pr_receipt_port, pr_receipt_mac,
                         pr_sticker_type, pr_sticker_ip, pr_sticker_port, pr_sticker_mac,
                         pr_kds_type, pr_kds_ip, pr_kds_port, pr_kds_mac)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $pdo->prepare($sql)->execute([
                $store_code, $store_name, $invoice_prefix, $tax_id, $default_vat_rate,
                $store_city, $store_addr, $store_phone, $store_cif, $is_active,
                $billing_system, $eod_cutoff_hour,
                $pr_receipt_type, $pr_receipt_ip, $pr_receipt_port, $pr_receipt_mac,
                $pr_sticker_type, $pr_sticker_ip, $pr_sticker_port, $pr_sticker_mac,
                $pr_kds_type, $pr_kds_ip, $pr_kds_port, $pr_kds_mac
            ]);
            $newId = (int)$pdo->lastInsertId();
            $pdo->commit();
            json_ok(['id'=>$newId], '门店已创建。');
        }
    } catch (Throwable $e) {
        $pdo->rollBack();
        // [MODIFIED 1.3] 捕获唯一键冲突
        if ($e instanceof PDOException && $e->errorInfo[1] == 1062) {
             if (strpos($e->getMessage(), 'uniq_invoice_prefix') !== false) {
                 json_error('票号前缀 "' . htmlspecialchars($invoice_prefix) . '" 已被占用。', 409);
             }
             if (strpos($e->getMessage(), 'store_code') !== false) {
                 json_error('门店编码 "' . htmlspecialchars($store_code) . '" 已被占用。', 409);
             }
        }
        json_error('数据库错误（stores）', 500, ['debug' => $e->getMessage()]);
    }
}

function handle_store_delete(PDO $pdo, array $config, array $input_data): void {
    $id = $input_data['id'] ?? json_error('缺少 id', 400);
    $stmt = $pdo->prepare("UPDATE kds_stores SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([(int)$id]);
    json_ok(null, '门店已成功删除。');
}

// --- 处理器: KDS 用户 (kds_users) ---
function handle_kds_user_get(PDO $pdo, array $config, array $input_data): void {
    $id = $_GET['id'] ?? json_error('缺少 id', 400);
    $data = getKdsUserById($pdo, (int)$id);
    $data ? json_ok($data) : json_error('未找到KDS用户', 404);
}
function handle_kds_user_save(PDO $pdo, array $config, array $input_data): void {
    // 前端发的是 { data: {...} }
    $data = $input_data['data'] ?? json_error('缺少 data', 400);

    $id         = isset($data['id']) && $data['id'] !== '' ? (int)$data['id'] : 0;
    $store_id   = (int)($data['store_id'] ?? 0);
    $username   = trim((string)($data['username'] ?? ''));
    $display    = trim((string)($data['display_name'] ?? ''));
    $is_active  = (int)($data['is_active'] ?? 0);
    $password   = (string)($data['password'] ?? '');

    if ($store_id <= 0 || $username === '') {
        json_error('用户名和门店ID不能为空。', 400);
    }
    if ($display === '') {
        json_error('显示名称为必填项。', 400);
    }

    $pdo->beginTransaction();
    try {
        if ($id > 0) {
            // 更新：仅当传了新密码才更新 password_hash
            $params = [
                ':store_id'     => $store_id,
                ':id'           => $id,
                ':display_name' => $display,
                ':is_active'    => $is_active,
            ];

            if ($password !== '') {
                // 与原代码保持一致：使用 sha256
                $params[':password_hash'] = hash('sha256', $password);
                $sql = "UPDATE kds_users
                           SET display_name = :display_name,
                               is_active    = :is_active,
                               password_hash = :password_hash
                         WHERE id = :id AND store_id = :store_id";
            } else {
                $sql = "UPDATE kds_users
                           SET display_name = :display_name,
                               is_active    = :is_active
                         WHERE id = :id AND store_id = :store_id";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $pdo->commit();
            json_ok(['id' => $id], 'KDS用户已成功更新！');
        } else {
            // 新增：需要校验同门店下的用户名唯一，并且必须提供密码
            if ($password === '') {
                $pdo->rollBack();
                json_error('创建新用户时必须设置密码。', 400);
            }

            $chk = $pdo->prepare("SELECT id FROM kds_users
                                   WHERE username = ? AND store_id = ? AND deleted_at IS NULL
                                   LIMIT 1");
            $chk->execute([$username, $store_id]);
            if ($chk->fetchColumn()) {
                $pdo->rollBack();
                json_error('用户名 \"' . htmlspecialchars($username) . '\" 在此门店已被使用。', 409);
            }

            $params = [
                ':store_id'     => $store_id,
                ':username'     => $username,
                ':display_name' => $display,
                ':is_active'    => $is_active,
                // 与原有实现保持一致：sha256
                ':password_hash'=> hash('sha256', $password),
            ];

            $sql = "INSERT INTO kds_users
                        (store_id, username, display_name, is_active, password_hash)
                    VALUES (:store_id, :username, :display_name, :is_active, :password_hash)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $newId = (int)$pdo->lastInsertId();
            $pdo->commit();
            json_ok(['id' => $newId], '新KDS用户已成功创建！');
        }
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_error('数据库错误（kds_users）', 500, ['debug' => $e->getMessage()]);
    }
}

function handle_kds_user_delete(PDO $pdo, array $config, array $input_data): void {
    $id = $input_data['id'] ?? json_error('缺少 id', 400);
    $stmt = $pdo->prepare("UPDATE kds_users SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([(int)$id]);
    json_ok(null, 'KDS用户已成功删除。');
}

// --- 处理器: 个人资料 (profile) ---
function handle_profile_save(PDO $pdo, array $config, array $input_data): void {
    @session_start();
    $user_id = $_SESSION['user_id'] ?? 0;
    if ($user_id <= 0) json_error('会话无效或已过期，请重新登录。', 401);

    $display_name = trim($input_data['display_name'] ?? '');
    $email = trim($input_data['email'] ?? null);
    $current_password = $input_data['current_password'] ?? '';
    $new_password = $input_data['new_password'] ?? '';

    if (empty($display_name)) json_error('显示名称不能为空。', 400);

    // 检查是否需要验证当前密码
    $user = getUserById($pdo, $user_id);
    if ($user['email'] !== $email || !empty($new_password)) {
        if (empty($current_password)) json_error('修改邮箱或密码时，必须提供当前密码。', 403);
        
        // [GEMINI SECURITY FIX V1.0] Verify against the DB hash using password_verify()
        $stmt_check = $pdo->prepare("SELECT password_hash FROM cpsys_users WHERE id = ?");
        $stmt_check->execute([$user_id]);
        $current_hash_db = $stmt_check->fetchColumn();

        if (!$current_hash_db || !password_verify($current_password, $current_hash_db)) {
            json_error('当前密码不正确。', 403);
        }
    }

    $params = [':display_name' => $display_name, ':email' => $email, ':id' => $user_id];
    $password_sql = "";
    if (!empty($new_password)) {
        // [GEMINI SECURITY FIX V1.0] Store new password using Bcrypt
        $params[':password_hash'] = password_hash($new_password, PASSWORD_BCRYPT);
        $password_sql = ", password_hash = :password_hash";
    }

    $sql = "UPDATE cpsys_users SET display_name = :display_name, email = :email {$password_sql} WHERE id = :id";
    $pdo->prepare($sql)->execute($params);

    // 更新会话
    $_SESSION['display_name'] = $display_name;
    json_ok(null, '个人资料已成功更新！');
}

// --- 处理器: 打印模板 (print_templates) ---
function handle_template_get(PDO $pdo, array $config, array $input_data): void {
    $id = $_GET['id'] ?? json_error('缺少 id', 400);
    $stmt = $pdo->prepare("SELECT * FROM pos_print_templates WHERE id = ?");
    $stmt->execute([(int)$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $data ? json_ok($data) : json_error('未找到模板', 404);
}
function handle_template_save(PDO $pdo, array $config, array $input_data): void {
    $data = $input_data['data'] ?? json_error('缺少 data', 400);
    $id = $data['id'] ? (int)$data['id'] : null;
    $params = [
        ':template_name' => trim($data['template_name'] ?? ''),
        ':template_type' => $data['template_type'] ?? null,
        ':physical_size' => $data['physical_size'] ?? null,
        ':template_content' => $data['template_content'] ?? '[]',
        ':is_active' => (int)($data['is_active'] ?? 0),
        ':store_id' => null // 暂时只支持全局
    ];
    if (empty($params[':template_name']) || empty($params[':template_type']) || empty($params[':physical_size'])) json_error('模板名称、类型和物理尺寸为必填项。', 400);
    if ($id) {
        $params[':id'] = $id;
        $sql = "UPDATE pos_print_templates SET store_id = :store_id, template_name = :template_name, template_type = :template_type, template_content = :template_content, physical_size = :physical_size, is_active = :is_active WHERE id = :id";
        $message = '模板已成功更新。';
    } else {
        $sql = "INSERT INTO pos_print_templates (store_id, template_name, template_type, template_content, physical_size, is_active) VALUES (:store_id, :template_name, :template_type, :template_content, :physical_size, :is_active)";
        $message = '新模板已成功创建。';
    }
    $pdo->prepare($sql)->execute($params);
    json_ok(['id' => $id ?? $pdo->lastInsertId()], $message);
}
function handle_template_delete(PDO $pdo, array $config, array $input_data): void {
    $id = $input_data['id'] ?? json_error('缺少 id', 400);
    $stmt = $pdo->prepare("DELETE FROM pos_print_templates WHERE id = ?");
    $stmt->execute([(int)$id]);
    json_ok(null, '模板已删除。');
}

// --- 处理器: 杯型 (cups) ---
function handle_cup_get(PDO $pdo, array $config, array $input_data): void {
    $id = $_GET['id'] ?? json_error('缺少 id', 400);
    $data = getCupById($pdo, (int)$id);
    $data ? json_ok($data) : json_error('未找到杯型', 404);
}
function handle_cup_save(PDO $pdo, array $config, array $input_data): void {
    $data = $input_data['data'] ?? json_error('缺少 data', 400);
    $id = $data['id'] ? (int)$data['id'] : null;
    $code = trim($data['cup_code'] ?? ''); $name = trim($data['cup_name'] ?? '');
    $sop_zh = trim($data['sop_zh'] ?? ''); $sop_es = trim($data['sop_es'] ?? '');
    if (empty($code) || empty($name) || empty($sop_zh) || empty($sop_es)) json_error('编号、名称和双语SOP描述均为必填项。', 400);
    $stmt_check = $pdo->prepare("SELECT id FROM kds_cups WHERE cup_code = ? AND deleted_at IS NULL" . ($id ? " AND id != ?" : ""));
    $params_check = $id ? [$code, $id] : [$code];
    $stmt_check->execute($params_check);
    if ($stmt_check->fetch()) json_error('此编号已被使用。', 409);
    $params = [':code' => $code, ':name' => $name, ':sop_zh' => $sop_zh, ':sop_es' => $sop_es];
    if ($id) {
        $params[':id'] = $id;
        $sql = "UPDATE kds_cups SET cup_code = :code, cup_name = :name, sop_description_zh = :sop_zh, sop_description_es = :sop_es WHERE id = :id";
        $pdo->prepare($sql)->execute($params);
        json_ok(['id' => $id], '杯型已更新。');
    } else {
        $sql = "INSERT INTO kds_cups (cup_code, cup_name, sop_description_zh, sop_description_es) VALUES (:code, :name, :sop_zh, :sop_es)";
        $pdo->prepare($sql)->execute($params);
        json_ok(['id' => (int)$pdo->lastInsertId()], '新杯型已创建。');
    }
}
function handle_cup_delete(PDO $pdo, array $config, array $input_data): void {
    $id = $input_data['id'] ?? json_error('缺少 id', 400);
    $stmt = $pdo->prepare("UPDATE kds_cups SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([(int)$id]);
    json_ok(null, '杯型已删除。');
}

// --- 处理器: 冰量 (ice_options) ---
function handle_ice_get(PDO $pdo, array $config, array $input_data): void {
    $id = $_GET['id'] ?? json_error('缺少 id', 400);
    $data = getIceOptionById($pdo, (int)$id);
    $data ? json_ok($data) : json_error('未找到选项', 404);
}
function handle_ice_save(PDO $pdo, array $config, array $input_data): void {
    $data = $input_data['data'] ?? json_error('缺少 data', 400);
    $id = $data['id'] ? (int)$data['id'] : null;
    $code = trim($data['code'] ?? ''); $name_zh = trim($data['name_zh'] ?? ''); $name_es = trim($data['name_es'] ?? '');
    $sop_zh = trim($data['sop_zh'] ?? ''); $sop_es = trim($data['sop_es'] ?? '');
    if (empty($code) || empty($name_zh) || empty($name_es) || empty($sop_zh) || empty($sop_es)) json_error('编号、双语名称和双语SOP描述均为必填项。', 400);
    $pdo->beginTransaction();
    if ($id) {
        $stmt_check = $pdo->prepare("SELECT id FROM kds_ice_options WHERE ice_code = ? AND id != ? AND deleted_at IS NULL");
        $stmt_check->execute([$code, $id]);
        if ($stmt_check->fetch()) { $pdo->rollBack(); json_error('此编号已被使用。', 409); }
        $pdo->prepare("UPDATE kds_ice_options SET ice_code = ? WHERE id = ?")->execute([$code, $id]);
    } else {
        $stmt_check = $pdo->prepare("SELECT id FROM kds_ice_options WHERE ice_code = ? AND deleted_at IS NULL");
        $stmt_check->execute([$code]);
        if ($stmt_check->fetch()) { $pdo->rollBack(); json_error('此编号已被使用。', 409); }
        $pdo->prepare("INSERT INTO kds_ice_options (ice_code) VALUES (?)")->execute([$code]);
        $id = (int)$pdo->lastInsertId();
    }
    $pdo->prepare("INSERT INTO kds_ice_option_translations (ice_option_id, language_code, ice_option_name, sop_description) VALUES (?, 'zh-CN', ?, ?) ON DUPLICATE KEY UPDATE ice_option_name = VALUES(ice_option_name), sop_description = VALUES(sop_description)")->execute([$id, $name_zh, $sop_zh]);
    $pdo->prepare("INSERT INTO kds_ice_option_translations (ice_option_id, language_code, ice_option_name, sop_description) VALUES (?, 'es-ES', ?, ?) ON DUPLICATE KEY UPDATE ice_option_name = VALUES(ice_option_name), sop_description = VALUES(sop_description)")->execute([$id, $name_es, $sop_es]);
    $pdo->commit();
    json_ok(['id' => $id], '冰量选项已保存。');
}
function handle_ice_delete(PDO $pdo, array $config, array $input_data): void {
    $id = $input_data['id'] ?? json_error('缺少 id', 400);
    $stmt = $pdo->prepare("UPDATE kds_ice_options SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([(int)$id]);
    json_ok(null, '冰量选项已删除。');
}
function handle_ice_get_next_code(PDO $pdo, array $config, array $input_data): void {
    $next = getNextAvailableCustomCode($pdo, 'kds_ice_options', 'ice_code');
    json_ok(['next_code' => $next]);
}

// --- 处理器: 甜度 (sweetness_options) ---
function handle_sweetness_get(PDO $pdo, array $config, array $input_data): void {
    $id = $_GET['id'] ?? json_error('缺少 id', 400);
    $data = getSweetnessOptionById($pdo, (int)$id);
    $data ? json_ok($data) : json_error('未找到选项', 404);
}
function handle_sweetness_save(PDO $pdo, array $config, array $input_data): void {
    $data = $input_data['data'] ?? json_error('缺少 data', 400);
    $id = $data['id'] ? (int)$data['id'] : null;
    $code = trim($data['code'] ?? ''); $name_zh = trim($data['name_zh'] ?? ''); $name_es = trim($data['name_es'] ?? '');
    $sop_zh = trim($data['sop_zh'] ?? ''); $sop_es = trim($data['sop_es'] ?? '');
    if (empty($code) || empty($name_zh) || empty($name_es) || empty($sop_zh) || empty($sop_es)) json_error('编号、双语名称和双语SOP描述均为必填项。', 400);
    $pdo->beginTransaction();
    if ($id) {
        $stmt_check = $pdo->prepare("SELECT id FROM kds_sweetness_options WHERE sweetness_code = ? AND id != ? AND deleted_at IS NULL");
        $stmt_check->execute([$code, $id]);
        if ($stmt_check->fetch()) { $pdo->rollBack(); json_error('此编号已被使用。', 409); }
        $pdo->prepare("UPDATE kds_sweetness_options SET sweetness_code = ? WHERE id = ?")->execute([$code, $id]);
    } else {
        $stmt_check = $pdo->prepare("SELECT id FROM kds_sweetness_options WHERE sweetness_code = ? AND deleted_at IS NULL");
        $stmt_check->execute([$code]);
        if ($stmt_check->fetch()) { $pdo->rollBack(); json_error('此编号已被使用。', 409); }
        $pdo->prepare("INSERT INTO kds_sweetness_options (sweetness_code) VALUES (?)")->execute([$code]);
        $id = (int)$pdo->lastInsertId();
    }
    $pdo->prepare("INSERT INTO kds_sweetness_option_translations (sweetness_option_id, language_code, sweetness_option_name, sop_description) VALUES (?, 'zh-CN', ?, ?) ON DUPLICATE KEY UPDATE sweetness_option_name = VALUES(sweetness_option_name), sop_description = VALUES(sop_description)")->execute([$id, $name_zh, $sop_zh]);
    $pdo->prepare("INSERT INTO kds_sweetness_option_translations (sweetness_option_id, language_code, sweetness_option_name, sop_description) VALUES (?, 'es-ES', ?, ?) ON DUPLICATE KEY UPDATE sweetness_option_name = VALUES(sweetness_option_name), sop_description = VALUES(sop_description)")->execute([$id, $name_es, $sop_es]);
    $pdo->commit();
    json_ok(['id' => $id], '甜度选项已保存。');
}
function handle_sweetness_delete(PDO $pdo, array $config, array $input_data): void {
    $id = $input_data['id'] ?? json_error('缺少 id', 400);
    $stmt = $pdo->prepare("UPDATE kds_sweetness_options SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([(int)$id]);
    json_ok(null, '甜度选项已删除。');
}
function handle_sweetness_get_next_code(PDO $pdo, array $config, array $input_data): void {
    $next = getNextAvailableCustomCode($pdo, 'kds_sweetness_options', 'sweetness_code');
    json_ok(['next_code' => $next]);
}

// --- 处理器: 单位 (units) ---
function handle_unit_get(PDO $pdo, array $config, array $input_data): void {
    $id = $_GET['id'] ?? json_error('缺少 id', 400);
    $data = getUnitById($pdo, (int)$id);
    $data ? json_ok($data) : json_error('未找到单位', 404);
}
function handle_unit_save(PDO $pdo, array $config, array $input_data): void {
    $data = $input_data['data'] ?? json_error('缺少 data', 400);
    $id = $data['id'] ? (int)$data['id'] : null;
    $code = trim($data['unit_code'] ?? ''); $name_zh = trim($data['name_zh'] ?? ''); $name_es = trim($data['name_es'] ?? '');
    if (empty($code) || empty($name_zh) || empty($name_es)) json_error('编号和双语名称均为必填项。', 400);
    $pdo->beginTransaction();
    if ($id) {
        $stmt_check = $pdo->prepare("SELECT id FROM kds_units WHERE unit_code = ? AND id != ? AND deleted_at IS NULL");
        $stmt_check->execute([$code, $id]);
        if ($stmt_check->fetch()) { $pdo->rollBack(); json_error('此编号已被使用。', 409); }
        $pdo->prepare("UPDATE kds_units SET unit_code = ? WHERE id = ?")->execute([$code, $id]);
    } else {
        $stmt_check = $pdo->prepare("SELECT id FROM kds_units WHERE unit_code = ? AND deleted_at IS NULL");
        $stmt_check->execute([$code]);
        if ($stmt_check->fetch()) { $pdo->rollBack(); json_error('此编号已被使用。', 409); }
        $pdo->prepare("INSERT INTO kds_units (unit_code) VALUES (?)")->execute([$code]);
        $id = (int)$pdo->lastInsertId();
    }
    $pdo->prepare("INSERT INTO kds_unit_translations (unit_id, language_code, unit_name) VALUES (?, 'zh-CN', ?) ON DUPLICATE KEY UPDATE unit_name = VALUES(unit_name)")->execute([$id, $name_zh]);
    $pdo->prepare("INSERT INTO kds_unit_translations (unit_id, language_code, unit_name) VALUES (?, 'es-ES', ?) ON DUPLICATE KEY UPDATE unit_name = VALUES(unit_name)")->execute([$id, $name_es]);
    $pdo->commit();
    json_ok(['id' => $id], '单位已保存。');
}
function handle_unit_delete(PDO $pdo, array $config, array $input_data): void {
    $id = $input_data['id'] ?? json_error('缺少 id', 400);
    $stmt = $pdo->prepare("UPDATE kds_units SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([(int)$id]);
    json_ok(null, '单位已删除。');
}
function handle_unit_get_next_code(PDO $pdo, array $config, array $input_data): void {
    $next = getNextAvailableCustomCode($pdo, 'kds_units', 'unit_code');
    json_ok(['next_code' => $next]);
}

// --- 处理器: 产品状态 (product_statuses) ---
function handle_status_get(PDO $pdo, array $config, array $input_data): void {
    $id = $_GET['id'] ?? json_error('缺少 id', 400);
    $stmt = $pdo->prepare("SELECT * FROM kds_product_statuses WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([(int)$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $data ? json_ok($data) : json_error('未找到状态', 404);
}
function handle_status_save(PDO $pdo, array $config, array $input_data): void {
    $data = $input_data['data'] ?? json_error('缺少 data', 400);
    $id = $data['id'] ? (int)$data['id'] : null;
    $code = trim($data['status_code'] ?? '');
    $name_zh = trim($data['status_name_zh'] ?? ''); $name_es = trim($data['status_name_es'] ?? '');
    if (empty($code) || empty($name_zh) || empty($name_es)) json_error('编号和双语名称均为必填项。', 400);
    $stmt_check = $pdo->prepare("SELECT id FROM kds_product_statuses WHERE status_code = ? AND deleted_at IS NULL" . ($id ? " AND id != ?" : ""));
    $params_check = $id ? [$code, $id] : [$code];
    $stmt_check->execute($params_check);
    if ($stmt_check->fetch()) json_error('此编号已被使用。', 409);
    $params = [':code' => $code, ':name_zh' => $name_zh, ':name_es' => $name_es];
    if ($id) {
        $params[':id'] = $id;
        $sql = "UPDATE kds_product_statuses SET status_code = :code, status_name_zh = :name_zh, status_name_es = :name_es WHERE id = :id";
        $pdo->prepare($sql)->execute($params);
        json_ok(['id' => $id], '状态已更新。');
    } else {
        $sql = "INSERT INTO kds_product_statuses (status_code, status_name_zh, status_name_es) VALUES (:code, :name_zh, :name_es)";
        $pdo->prepare($sql)->execute($params);
        json_ok(['id' => (int)$pdo->lastInsertId()], '新状态已创建。');
    }
}
function handle_status_delete(PDO $pdo, array $config, array $input_data): void {
    $id = $input_data['id'] ?? json_error('缺少 id', 400);
    $stmt = $pdo->prepare("UPDATE kds_product_statuses SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([(int)$id]);
    json_ok(null, '状态已删除。');
}
// --- END: 缺失的处理器 ---


// --- 注册表 ---
return [

    // KDS SOP Rules (V2 Refactor)
    'kds_sop_rules' => [
        'table' => 'kds_sop_query_rules', 'pk' => 'id', 'soft_delete_col' => null, 'auth_role' => ROLE_SUPER_ADMIN,
        'custom_actions' => [
            'get_list' => 'handle_kds_rule_get_list', 'get' => 'handle_kds_rule_get',
            'save' => 'handle_kds_rule_save', 'delete' => 'handle_kds_rule_delete',
        ],
    ],

    // --- START: 缺失的注册条目 ---

    'users' => [
        'table' => 'cpsys_users', 'pk' => 'id', 'soft_delete_col' => 'deleted_at', 'auth_role' => ROLE_SUPER_ADMIN,
        'custom_actions' => [
            'get' => 'handle_user_get', 'save' => 'handle_user_save', 'delete' => 'handle_user_delete',
        ],
    ],

    'stores' => [
        'table' => 'kds_stores', 'pk' => 'id', 'soft_delete_col' => 'deleted_at', 'auth_role' => ROLE_SUPER_ADMIN,
        'custom_actions' => [
            'get' => 'handle_store_get', 'save' => 'handle_store_save', 'delete' => 'handle_store_delete',
        ],
    ],

    'kds_users' => [
        'table' => 'kds_users', 'pk' => 'id', 'soft_delete_col' => 'deleted_at', 'auth_role' => ROLE_SUPER_ADMIN,
        'custom_actions' => [
            'get' => 'handle_kds_user_get', 'save' => 'handle_kds_user_save', 'delete' => 'handle_kds_user_delete',
        ],
    ],

    'profile' => [
        'table' => 'cpsys_users', 'pk' => 'id', 'auth_role' => ROLE_PRODUCT_MANAGER, // 允许所有登录用户
        'custom_actions' => [
            'save' => 'handle_profile_save',
        ],
    ],

    'print_templates' => [
        'table' => 'pos_print_templates', 'pk' => 'id', 'soft_delete_col' => null, 'auth_role' => ROLE_SUPER_ADMIN,
        'custom_actions' => [
            'get' => 'handle_template_get', 'save' => 'handle_template_save', 'delete' => 'handle_template_delete',
        ],
    ],

    // --- 字典 Dictionaries ---
    'cups' => [
        'table' => 'kds_cups', 'pk' => 'id', 'soft_delete_col' => 'deleted_at', 'auth_role' => ROLE_SUPER_ADMIN,
        'custom_actions' => [
            'get' => 'handle_cup_get', 'save' => 'handle_cup_save', 'delete' => 'handle_cup_delete',
        ],
    ],

    'ice_options' => [
        'table' => 'kds_ice_options', 'pk' => 'id', 'soft_delete_col' => 'deleted_at', 'auth_role' => ROLE_SUPER_ADMIN,
        'custom_actions' => [
            'get' => 'handle_ice_get', 'save' => 'handle_ice_save', 'delete' => 'handle_ice_delete', 'get_next_code' => 'handle_ice_get_next_code',
        ],
    ],

    'sweetness_options' => [
        'table' => 'kds_sweetness_options', 'pk' => 'id', 'soft_delete_col' => 'deleted_at', 'auth_role' => ROLE_SUPER_ADMIN,
        'custom_actions' => [
            'get' => 'handle_sweetness_get', 'save' => 'handle_sweetness_save', 'delete' => 'handle_sweetness_delete', 'get_next_code' => 'handle_sweetness_get_next_code',
        ],
    ],

    'units' => [
        'table' => 'kds_units', 'pk' => 'id', 'soft_delete_col' => 'deleted_at', 'auth_role' => ROLE_SUPER_ADMIN,
        'custom_actions' => [
            'get' => 'handle_unit_get', 'save' => 'handle_unit_save', 'delete' => 'handle_unit_delete', 'get_next_code' => 'handle_unit_get_next_code',
        ],
    ],

    'product_statuses' => [
        'table' => 'kds_product_statuses', 'pk' => 'id', 'soft_delete_col' => 'deleted_at', 'auth_role' => ROLE_SUPER_ADMIN,
        'custom_actions' => [
            'get' => 'handle_status_get', 'save' => 'handle_status_save', 'delete' => 'handle_status_delete',
        ],
    ],

    // --- END: 缺失的注册条目 ---

];
