<?php
/**
 * Toptea HQ - CPSYS API 注册表 (Extensional)
 * 注册未来新增的、或不属于 Base/BMS/RMS 的资源
 * Version: 1.0.001 (V1.5 Path Fix)
 * Date: 2025-11-05
 *
 * [GEMINI V1.5 FIX]: Corrected realpath() to ../../../
 */

// 确保 kds_helper 已加载
require_once realpath(__DIR__ . '/../../../../app/helpers/kds_helper.php');
// 确保 auth_helper 已加载
require_once realpath(__DIR__ . '/../../../../app/helpers/auth_helper.php');


// --- 处理器 ---
// (暂无)


// --- 注册表 ---
return [

    // (示例)
    /*
    'example_resource' => [
        'table' => 'example_table',
        'pk' => 'id',
        'soft_delete_col' => 'deleted_at',
        'auth_role' => ROLE_SUPER_ADMIN,
        'visible_cols' => ['id', 'name'],
        'writable_cols' => ['name'],
    ],
    */

];
