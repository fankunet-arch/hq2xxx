<?php
/**
 * cpsys_registry_kds.php
 * Registry for KDS read endpoints (SOP, dicts, menu snapshot).
 *
 * [GEMINI V1.5 FIX]:
 * - Corrected realpath() from ../../../../ to ../../../
 */

require_once realpath(__DIR__ . '/../../../../app/helpers/kds_helper.php');
require_once realpath(__DIR__ . '/../../../../app/helpers/http_json_helper.php');
require_once realpath(__DIR__ . '/../../../../app/helpers/auth_helper.php');

/** Handler: kds/sop */
function handle_kds_sop(PDO $pdo, array $config, array $input_data): void {
    $code = $input_data['code'] ?? ($_GET['code'] ?? ($_POST['code'] ?? ''));
    $lang = $input_data['lang'] ?? ($_GET['lang'] ?? ($_POST['lang'] ?? 'zh'));
    if (!$code) { json_error('Missing code', 400); return; }

    if (function_exists('getKdsSopByCode')) {
        // KDS 引擎需要一个 store_id，但 HQ 预览是全局的。
        // 传入 0 或 1 均可，它将智能拉取 store_id IS NULL 的全局规则。
        $store_id_for_parser = 1;
        $result = getKdsSopByCode($pdo, (string)$code, $store_id_for_parser);

        // 转换回此文件旧的 out() / ok() 格式
        if ($result['status'] === 'success') {
            json_ok($result['data'], $result['message']);
        } else {
            json_error($result['message'], $result['http_code'], $result['data']);
        }
    } else {
        json_error('SOP service (getKdsSopByCode) not available', 500); return;
    }
}

/** Handler: kds/dicts */
function handle_kds_dicts(PDO $pdo, array $config, array $input_data): void {
    $lang = $input_data['lang'] ?? ($_GET['lang'] ?? ($_POST['lang'] ?? 'zh'));

    // [GEMINI V1.2 NOTE] service_get_kds_dicts is not defined in the provided files.
    // This handler will fail if called, but it doesn't break the gateway.
    if (function_exists('service_get_kds_dicts')) {
        $data = service_get_kds_dicts($lang);
        json_ok($data); return;
    }
    json_error('KDS dicts service (service_get_kds_dicts) not available', 500);
}

/** Handler: kds/menu */
function handle_kds_menu(PDO $pdo, array $config, array $input_data): void {
    $store_id = $input_data['store_id'] ?? ($_GET['store_id'] ?? ($_POST['store_id'] ?? null));
    $lang = $input_data['lang'] ?? ($_GET['lang'] ?? ($_POST['lang'] ?? 'zh'));
    if (!$store_id) { json_error('Missing store_id', 400); return; }

    // [GEMINI V1.2 NOTE] service_get_menu_snapshot is not defined in the provided files.
    // This handler will fail if called, but it doesn't break the gateway.
    if (function_exists('service_get_menu_snapshot')) {
        $data = service_get_menu_snapshot((int)$store_id, $lang);
        json_ok($data); return;
    }
    json_error('KDS menu service (service_get_menu_snapshot) not available', 500);
}

// --- Registry ---
return [
    'kds/sop' => [
        'auth_role' => ROLE_PRODUCT_MANAGER, // 读权限：产品经理可读 (FIXED from ROLE_STORE_USER)
        'custom_actions' => [
            'get' => 'handle_kds_sop'
        ],
    ],
    'kds/dicts' => [
        'auth_role' => ROLE_PRODUCT_MANAGER, // (FIXED from ROLE_STORE_USER)
        'custom_actions' => [
            'get' => 'handle_kds_dicts'
        ],
    ],
    'kds/menu' => [
        'auth_role' => ROLE_PRODUCT_MANAGER, // (FIXED from ROLE_STORE_USER)
        'custom_actions' => [
            'get' => 'handle_kds_menu'
        ],
    ],
];

