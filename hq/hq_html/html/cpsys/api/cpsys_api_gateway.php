<?php
/**
 * Toptea HQ - CPSYS 统一 API 网关
 * Version: 1.5.1 (Fatal Error Path Fix & Syntax Fix)
 * Date: 2025-11-05
 *
 * [GEMINI V1.5 FIX]:
 * - Corrected critical path error for loading config.php.
 * - Path was ../../../../ (WRONG)
 * - Path is now ../../../ (CORRECT)
 *
 * [GEMINI V1.5.1 FIX]:
 * - Removed stray '}' at the end of the file which caused a 500 Fatal Syntax Error.
 */

// 1. 加载核心配置 (提供 $pdo 和 APP_PATH)
require_once realpath(__DIR__ . '/../../../core/config.php');


// 2. 加载核心助手 (提供 json_ok, json_error 等)
require_once APP_PATH . '/helpers/http_json_helper.php';

// 3. 加载核心引擎 (提供 run_api)
require_once APP_PATH . '/core/api_core.php';

// 4. 加载所有资源注册表
$registry_base = require_once __DIR__ . '/registries/cpsys_registry_base.php';
$registry_bms = require_once __DIR__ . '/registries/cpsys_registry_bms.php';
$registry_rms = require_once __DIR__ . '/registries/cpsys_registry_rms.php';
$registry_ext = require_once __DIR__ . '/registries/cpsys_registry_ext.php';
$registry_kds = require_once __DIR__ . '/registries/cpsys_registry_kds.php';

// 5. 合并注册表
$full_registry = array_merge(
    $registry_base,
    $registry_bms,
    $registry_rms,
    $registry_ext,
    $registry_kds
);

// 6. 运行引擎
run_api($full_registry, $pdo);