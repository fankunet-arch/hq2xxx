<?php
/**
 * Toptea HQ - cpsys
 * Role-Based Access Control (RBAC) Helper (Final Production Version)
 * Date: 2025-10-23 | Revision: 4.0
 *
 * [R-Final FIX] Added AuthException and check_role() function.
 */

// --- [R-Final FIX] START: Define exception class ---
if (!class_exists('AuthException')) {
    class AuthException extends Exception {}
}
// --- [R-Final FIX] END ---

define('ROLE_SUPER_ADMIN', 1);
define('ROLE_PRODUCT_MANAGER', 2);
define('ROLE_STORE_MANAGER', 3);
// 新增（兜底补齐，保持一贯顺序）
define('ROLE_STORE_USER', 4);
// [R-Final FIX] Define a generic "logged in user" role for pages like "profile"
define('ROLE_USER', 5);
// [R-Final FIX] Alias ROLE_ADMIN to ROLE_STORE_MANAGER for simplicity
if (!defined('ROLE_ADMIN')) {
    define('ROLE_ADMIN', 3);
}

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

// --- [R-Final FIX] START: Define the missing check_role() function ---
if (!function_exists('check_role')) {
    /**
     * Checks if the current user meets the minimum required role.
     * Assumes roles are hierarchical (1 > 2 > 3 ...).
     * Throws AuthException if permission is denied.
     */
    function check_role(int $required_role) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        
        $user_role_id = $_SESSION['role_id'] ?? null;
        
        if ($user_role_id === null) {
            // Not logged in (should be caught by auth_core, but as a fallback)
            throw new AuthException('未登录或会话已过期。 (User not logged in)');
        }
        
        $user_role_id = (int)$user_role_id;
        
        // Super Admin (1) always passes
        if ($user_role_id === ROLE_SUPER_ADMIN) {
            return true;
        }
        
        // If ROLE_USER (5) is required, any logged in user (1-5) is fine.
        if ($required_role === ROLE_USER && $user_role_id <= ROLE_USER) {
            return true;
        }

        // Standard hierarchical check
        // User role ID must be less than or equal to the required role ID
        // e.g., If ROLE_PRODUCT_MANAGER (2) is required,
        // user (1) and (2) pass.
        // user (3) and (4) fail.
        if ($user_role_id > $required_role) {
             throw new AuthException('权限不足。 (Insufficient permissions)');
        }
        
        return true;
    }
}
// --- [R-Final FIX] END ---