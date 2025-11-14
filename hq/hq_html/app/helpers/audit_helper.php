<?php
/**
 * Toptea HQ - 审计日志助手 (Audit Helper)
 * 职责: 提供统一的函数向 audit_logs 表写入记录。
 * [R2] Implements: 3.1 CPSYS-RMS (R2) - 审计
 * Engineer: Gemini | Date: 2025-11-13
 */

if (!defined('ROLE_SUPER_ADMIN')) {
    // 确保权限常量已定义
    require_once realpath(__DIR__ . '/auth_helper.php');
}

if (!function_exists('log_audit_action')) {
    /**
     * 记录审计日志
     *
     * @param PDO $pdo 数据库连接
     * @param string $action 动作标识 (e.g., 'rms.material.create', 'rms.material.update', 'rms.material.delete')
     * @param string $target_type 目标资源类型 (e.g., 'kds_materials')
     * @param string|int $target_id 目标资源ID
     * @param array|null $data_before 变更前的数据快照 (可选)
     * @param array|null $data_after 变更后的数据快照 (可选)
     * @return bool 成功返回 true, 失败返回 false
     */
    function log_audit_action(
        PDO $pdo,
        string $action,
        string $target_type,
        $target_id,
        ?array $data_before = null,
        ?array $data_after = null
    ): bool {
        
        // 1. 获取操作人信息 (从会话中)
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $actor_user_id = (int)($_SESSION['user_id'] ?? 0);
        $actor_type = 'hq_user'; // 当前所有审计均来自 HQ

        // 2. 准备 JSON 数据
        $data_json = json_encode(
            ['before' => $data_before, 'after' => $data_after],
            JSON_UNESCAPED_UNICODE
        );
        if ($data_json === false) {
            $data_json = json_encode(['error' => 'JSON encoding failed']);
        }
        
        // 3. 获取客户端 IP (尽力而为)
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

        try {
            $sql = "
                INSERT INTO audit_logs 
                    (action, actor_user_id, actor_type, target_type, target_id, data_json, ip)
                VALUES 
                    (:action, :actor_user_id, :actor_type, :target_type, :target_id, :data_json, :ip)
            ";
            $stmt = $pdo->prepare($sql);
            
            $stmt->execute([
                ':action' => $action,
                ':actor_user_id' => $actor_user_id,
                ':actor_type' => $actor_type,
                ':target_type' => $target_type,
                ':target_id' => (string)$target_id,
                ':data_json' => $data_json,
                ':ip' => $ip
            ]);
            
            return true;

        } catch (Throwable $e) {
            // 审计失败不应阻塞主流程，但必须记录错误
            error_log("CRITICAL: log_audit_action() failed: " . $e->getMessage());
            return false;
        }
    }
}