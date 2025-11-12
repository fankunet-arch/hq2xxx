<?php
/**
 * Toptea HQ - 通用 API 核心引擎
 * 职责: 提供 run_api() 函数，驱动基于注册表的 CRUD 操作。
 * Version: 1.0.0
 * Date: 2025-11-04
 */

// 确保核心助手已被加载
if (!function_exists('json_error')) {
    require_once realpath(__DIR__ . '/../helpers/http_json_helper.php');
}
// 确保权限常量已定义
if (!defined('ROLE_SUPER_ADMIN')) {
    // 尝试从 auth_helper 加载
    $auth_helper_path = realpath(__DIR__ . '/../helpers/auth_helper.php');
    if ($auth_helper_path) {
        require_once $auth_helper_path;
    }
    // 如果仍然未定义，使用兜底
    if (!defined('ROLE_SUPER_ADMIN')) {
        define('ROLE_SUPER_ADMIN', 1);
    }
}


/**
 * 运行 API 网关
 *
 * @param array $registry 完整的 API 资源注册表
 * @param PDO $pdo 数据库连接
 */
function run_api(array $registry, PDO $pdo): void {
    
    // 1. 获取资源和动作
    $resource_name = $_GET['res'] ?? null;
    $action_name = $_GET['act'] ?? null;

    if (empty($resource_name) || empty($action_name)) {
        json_error('无效的 API 请求：缺少 res (资源) 或 act (动作) 参数。', 400);
    }

    // 2. 查找资源配置
    $config = $registry[$resource_name] ?? null;
    if ($config === null) {
        json_error("资源 '{$resource_name}' 未在 API 注册表中定义。", 404);
    }

    // 3. 权限检查
    @session_start();
    $required_role = $config['auth_role'] ?? ROLE_SUPER_ADMIN; // 默认超级管理员
    $user_role = $_SESSION['role_id'] ?? null;

    if ($user_role !== ROLE_SUPER_ADMIN && $user_role !== $required_role) {
        json_error('权限不足，禁止访问此资源。', 403);
    }

    // 4. 解析输入
    $input_data = get_request_data();
    
    try {
        // 5. 检查自定义动作
        if (isset($config['custom_actions'][$action_name])) {
            $function_name = $config['custom_actions'][$action_name];
            if (is_callable($function_name)) {
                // 执行自定义函数 (例如: handle_unit_save)
                call_user_func($function_name, $pdo, $config, $input_data);
            } else {
                json_error("配置错误: 资源 '{$resource_name}' 的自定义动作 '{$action_name}' 指向了无效函数 '{$function_name}'", 500);
            }
            exit; // 自定义函数必须自己调用 json_ok/json_error
        }

        // 6. 执行标准动作
        $table = $config['table'] ?? json_error("资源 '{$resource_name}' 未配置 'table'", 500);
        $pk = $config['pk'] ?? 'id';
        $soft_delete_col = $config['soft_delete_col'] ?? null;
        $base_where = $soft_delete_col ? "{$soft_delete_col} IS NULL" : "1=1";

        switch ($action_name) {
            
            // --- 标准 GET (列表或单条) ---
            case 'get':
                $id = $_GET['id'] ?? null;
                $cols_str = implode(', ', $config['visible_cols'] ?? ['*']);
                
                if ($id) {
                    // 获取单条
                    $stmt = $pdo->prepare("SELECT {$cols_str} FROM {$table} WHERE {$pk} = ? AND {$base_where}");
                    $stmt->execute([(int)$id]);
                    $item = $stmt->fetch(PDO::FETCH_ASSOC);
                    $item ? json_ok($item) : json_error('记录未找到', 404);
                } else {
                    // 获取列表
                    $order_by = $config['default_order'] ?? "{$pk} ASC";
                    $stmt = $pdo->query("SELECT {$cols_str} FROM {$table} WHERE {$base_where} ORDER BY {$order_by}");
                    json_ok($stmt->fetchAll(PDO::FETCH_ASSOC));
                }
                break;
            
            // --- 标准 SAVE (新增或更新) ---
            case 'save':
                $data = $input_data['data'] ?? json_error('保存失败：请求体必须包含 "data" 键。', 400);
                $id = $data[$pk] ?? null;
                $writable_cols = $config['writable_cols'] ?? json_error("安全错误: 资源 '{$resource_name}' 未配置 'writable_cols'。", 500);
                
                $params = [];
                $set_clause = [];

                foreach ($writable_cols as $col) {
                    if (array_key_exists($col, $data)) {
                        $set_clause[] = "{$col} = :{$col}";
                        $params[":{$col}"] = $data[$col];
                    }
                }
                if (empty($params)) json_error('保存失败：未提供任何可写字段的数据。', 400);

                // (钩子)
                if (isset($config['hooks']['before_save'])) {
                    $params = call_user_func($config['hooks']['before_save'], $params, $id, $pdo, $config);
                }
                
                if ($id) {
                    // 更新
                    $sql = "UPDATE {$table} SET " . implode(', ', $set_clause) . " WHERE {$pk} = :__pk AND {$base_where}";
                    $params[':__pk'] = $id;
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $message = '更新成功';
                } else {
                    // 新增
                    $sql = "INSERT INTO {$table} SET " . implode(', ', $set_clause);
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $id = (int)$pdo->lastInsertId();
                    $message = '创建成功';
                }

                // (钩子)
                if (isset($config['hooks']['after_save'])) {
                    call_user_func($config['hooks']['after_save'], $id, $data, $pdo, $config);
                }

                json_ok(['id' => $id], $message);
                break;
            
            // --- 标准 DELETE (硬/软) ---
            case 'delete':
                $id = $input_data['id'] ?? json_error('删除失败：必须提供 "id"。', 400);
                
                // (钩子)
                if (isset($config['hooks']['before_delete'])) {
                    call_user_func($config['hooks']['before_delete'], $id, $pdo, $config);
                }

                if ($soft_delete_col) {
                    // 软删除
                    $stmt = $pdo->prepare("UPDATE {$table} SET {$soft_delete_col} = CURRENT_TIMESTAMP WHERE {$pk} = ?");
                } else {
                    // 硬删除
                    $stmt = $pdo->prepare("DELETE FROM {$table} WHERE {$pk} = ?");
                }
                $stmt->execute([(int)$id]);

                // (钩子)
                if (isset($config['hooks']['after_delete'])) {
                    call_user_func($config['hooks']['after_delete'], $id, $pdo, $config);
                }

                json_ok(null, '删除成功');
                break;

            default:
                json_error("动作 '{$action_name}' 在资源 '{$resource_name}' 中未定义。", 400);
        }
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // 捕获数据库唯一约束等错误
        if ($e->getCode() == '23000') {
            json_error('数据库操作失败：违反唯一约束（例如：名称或编码重复）。', 409, ['debug' => $e->getMessage()]);
        }
        json_error('数据库错误', 500, ['debug' => $e->getMessage()]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_error('服务器内部错误', 500, ['debug' => $e->getMessage()]);
    }
}