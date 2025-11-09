<?php
declare(strict_types=1);
/**
 * Toptea HQ - RBAC Helper (稳定版)
 * 在 PHP 8.2 上无语法/兼容性问题；函数均加 exists 保护，避免重复定义。
 * Version: 4.0.0
 * Date: 2025-11-10
 */

if (!defined('ROLE_SUPER_ADMIN'))     define('ROLE_SUPER_ADMIN', 1);
if (!defined('ROLE_PRODUCT_MANAGER')) define('ROLE_PRODUCT_MANAGER', 2);
if (!defined('ROLE_STORE_MANAGER'))   define('ROLE_STORE_MANAGER', 3);
if (!defined('ROLE_STORE_USER'))      define('ROLE_STORE_USER', 4);

if (!function_exists('getRolePermissions')) {
    function getRolePermissions(): array {
        return [
            ROLE_SUPER_ADMIN     => ['*'], // 具备全部权限
            ROLE_PRODUCT_MANAGER => ['product_list', 'product_management', 'product_edit'],
            ROLE_STORE_MANAGER   => ['product_list'],
            ROLE_STORE_USER      => [],
        ];
    }
}

if (!function_exists('hasPermission')) {
    function hasPermission(int $role_id, string $page): bool {
        if ($role_id === ROLE_SUPER_ADMIN) return true;
        $permissions = getRolePermissions();
        if (isset($permissions[$role_id])) {
            $allowed = $permissions[$role_id];
            if (in_array('*', $allowed, true)) return true;
            return in_array($page, $allowed, true);
        }
        return false;
    }
}

/* —— 以下三个函数仅在未定义时提供，避免与其他 helper 重复 —— */

if (!function_exists('getAllRoles')) {
    function getAllRoles(): array {
        return [
            ['id' => ROLE_SUPER_ADMIN,     'name' => 'Super Admin'],
            ['id' => ROLE_PRODUCT_MANAGER, 'name' => 'Product Manager'],
            ['id' => ROLE_STORE_MANAGER,   'name' => 'Store Manager'],
            ['id' => ROLE_STORE_USER,      'name' => 'Store User'],
        ];
    }
}

if (!function_exists('getAllUsers')) {
    function getAllUsers(PDO $pdo): array {
        $stmt = $pdo->query("SELECT id, username, role_id FROM cpsys_users ORDER BY id");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }
}

if (!function_exists('getUserById')) {
    function getUserById(PDO $pdo, int $id): ?array {
        $stmt = $pdo->prepare("SELECT id, username, role_id FROM cpsys_users WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
