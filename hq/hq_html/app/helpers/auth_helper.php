<?php
/**
 * Toptea HQ - cpsys
 * Role-Based Access Control (RBAC) Helper (Final Production Version)
 * Date: 2025-10-23 | Revision: 3.9
 */

define('ROLE_SUPER_ADMIN', 1);
define('ROLE_PRODUCT_MANAGER', 2);
define('ROLE_STORE_MANAGER', 3);
// 新增（兜底补齐，保持一贯顺序）
define('ROLE_STORE_USER', 4);

function getRolePermissions(): array {
    return [
        ROLE_PRODUCT_MANAGER => [ 'product_list', 'product_management', 'product_edit' ],
        ROLE_STORE_MANAGER => [ 'product_list' ],
    ];
}

function hasPermission(int $role_id, string $page): bool {
    if ($role_id === ROLE_SUPER_ADMIN) {
        return true;
    }
    $permissions = getRolePermissions();
    if (isset($permissions[$role_id])) {
        return in_array($page, $permissions[$role_id]);
    }
    return false;
}





