<?php
declare(strict_types=1);

/**
 * Toptea HQ - 通用 API 核心引擎（稳健版）
 * 文件路径：/hq_html/app/core/api_core.php
 *
 * 功能要点
 * 1) run_api(array $registry, ?PDO $pdo): void
 *    - 支持公开接口：当注册表中存在键 'auth_role' 且值为 null 时跳过权限校验
 *    - 允许 $pdo 为 null（用于无数据库依赖的诊断接口）
 * 2) 标准动作：
 *    - act=list    分页查询（支持 q 搜索 / order_by / page / page_size）
 *    - act=get     按主键读取
 *    - act=create  新增（按 writable_fields 过滤）
 *    - act=update  更新（按 writable_fields 过滤）
 *    - act=delete  删除（优先软删 soft_delete_col / deleted_at_col）
 * 3) 自定义动作：
 *    - 若注册表中存在 ['custom_actions'][$act] = '函数名'，则直接调用该函数($pdo, $config, $input)
 * 4) JSON 输出：
 *    - 内置 json_success/json_error/get_request_data 兜底；若外部 helper 存在则自动采用
 */

/* =========================
 *  统一 JSON & 输入兜底函数
 * ========================= */
if (!function_exists('json_success')) {
    function json_success(string $message = 'ok', $data = null, int $code = 200): void {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($code);
        }
        echo json_encode([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
if (!function_exists('json_error')) {
    function json_error(string $message = 'error', int $code = 400, $data = null): void {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($code);
        }
        echo json_encode([
            'status'  => 'error',
            'message' => $message,
            'data'    => $data,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
if (!function_exists('get_request_data')) {
    function get_request_data(): array {
        $raw = file_get_contents('php://input');
        $json = [];
        if (is_string($raw) && $raw !== '') {
            $tmp = json_decode($raw, true);
            if (is_array($tmp)) {
                $json = $tmp;
            }
        }
        // 表单与查询参数合并（JSON 优先）
        $data = array_merge($_GET ?? [], $_POST ?? []);
        foreach ($json as $k => $v) {
            $data[$k] = $v;
        }
        return $data;
    }
}

/* =========================
 *   尝试加载外部 helper
 * ========================= */
$http_helper = __DIR__ . '/../helpers/http_json_helper.php';
if (is_file($http_helper)) {
    require_once $http_helper; // 若存在，将覆盖上面的兜底函数
}
$auth_helper = __DIR__ . '/../helpers/auth_helper.php';
if (is_file($auth_helper)) {
    require_once $auth_helper;
}

/* =========================
 *    角色常量兜底定义
 * ========================= */
if (!defined('ROLE_SUPER_ADMIN'))     define('ROLE_SUPER_ADMIN', 1);
if (!defined('ROLE_PRODUCT_MANAGER')) define('ROLE_PRODUCT_MANAGER', 2);
if (!defined('ROLE_STORE_MANAGER'))   define('ROLE_STORE_MANAGER', 3);
if (!defined('ROLE_STORE_USER'))      define('ROLE_STORE_USER', 4);

/* =========================
 *       实用辅助函数
 * ========================= */

/**
 * 简单列名校验（仅允许 a-z0-9_）
 */
function is_safe_col(string $name): bool {
    return (bool)preg_match('/^[a-z0-9_]{1,64}$/', $name);
}

/**
 * 组合列白名单
 * 优先：配置中的 readable_fields / writable_fields / searchable_fields
 * 其次：若未提供且有 PDO，则通过 DESCRIBE 获取
 */
function get_table_columns(PDO $pdo, string $table): array {
    $cols = [];
    try {
        $stmt = $pdo->query('DESCRIBE ' . $table);
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (isset($row['Field']) && is_safe_col($row['Field'])) {
                    $cols[] = $row['Field'];
                }
            }
        }
    } catch (Throwable $e) {
        // 忽略，返回空数组
    }
    return $cols;
}

/**
 * 从输入筛选可写字段
 */
function filter_writable_fields(array $input, array $writable, string $pk, ?string $soft_col): array {
    $out = [];
    foreach ($input as $k => $v) {
        if (!is_string($k)) continue;
        if (!is_safe_col($k)) continue;
        if ($k === $pk) continue;
        if ($soft_col !== null && $k === $soft_col) continue;
        if (!in_array($k, $writable, true)) continue;
        $out[$k] = $v;
    }
    return $out;
}

/**
 * 解析 order_by（白名单 + 方向）
 */
function parse_order_by(?string $order_by, array $allowed_cols, string $fallback): string {
    if (is_string($order_by) && $order_by !== '') {
        $parts = preg_split('/\s+/', trim($order_by));
        $col = $parts[0] ?? '';
        $dir = strtoupper($parts[1] ?? 'ASC');
        if (in_array($col, $allowed_cols, true) && ($dir === 'ASC' || $dir === 'DESC')) {
            return $col . ' ' . $dir;
        }
        if (in_array($col, $allowed_cols, true)) {
            return $col . ' ASC';
        }
    }
    return $fallback;
}

/**
 * 权限校验：
 * - 若 config 显式设置 auth_role 且为 null => 公开接口
 * - 若设置为具体角色 => 需要该角色或超管
 * - 若未设置 => 默认只允许超管
 */
function assert_permission(array $config): void {
    $public = false;
    if (array_key_exists('auth_role', $config)) {
        $required = $config['auth_role'];
        if ($required === null) {
            $public = true;
        } else {
            @session_start();
            $role = $_SESSION['role_id'] ?? null;
            if ($role !== ROLE_SUPER_ADMIN && $role !== $required) {
                json_error('权限不足，禁止访问此资源。', 403);
            }
        }
    } else {
        @session_start();
        $role = $_SESSION['role_id'] ?? null;
        if ($role !== ROLE_SUPER_ADMIN) {
            json_error('权限不足，禁止访问此资源。', 403);
        }
    }
    // 公开接口不做任何事
}

/* =========================
 *          核心入口
 * ========================= */

/**
 * 运行 API
 * @param array $registry 资源注册表
 * @param ?PDO  $pdo      数据库连接，可为 null（支持无 DB 的诊断）
 */
function run_api(array $registry, ?PDO $pdo): void {
    $res = $_GET['res'] ?? null;
    $act = $_GET['act'] ?? null;

    if (!$res || !$act) {
        json_error('无效的 API 请求：缺少 res 或 act。', 400);
    }

    $config = $registry[$res] ?? null;
    if (!is_array($config)) {
        json_error("资源 '{$res}' 未在 API 注册表中定义。", 404);
    }

    // 权限
    assert_permission($config);

    // 读取输入
    $input = get_request_data();

    // 自定义动作优先
    if (isset($config['custom_actions']) && is_array($config['custom_actions'])) {
        if (isset($config['custom_actions'][$act])) {
            $fn = $config['custom_actions'][$act];
            if (is_string($fn) && is_callable($fn)) {
                call_user_func($fn, $pdo, $config, $input);
                exit;
            }
            json_error("配置错误：资源 '{$res}' 的自定义动作 '{$act}' 指向无效函数。", 500);
        }
    }

    // 标准 CRUD 需要表名
    $table = $config['table'] ?? null;
    if (!is_string($table) || $table === '') {
        json_error("资源 '{$res}' 未配置 'table'。", 500);
    }
    $pk = $config['pk'] ?? 'id';
    if (!is_safe_col($pk)) {
        json_error("资源 '{$res}' 的主键名不合法。", 500);
    }

    $soft_col      = $config['soft_delete_col']    ?? null; // 例如 is_deleted
    $deleted_at_col= $config['deleted_at_col']     ?? null; // 例如 deleted_at
    $readable_cfg  = $config['readable_fields']    ?? null; // array|null
    $writable_cfg  = $config['writable_fields']    ?? null; // array|null
    $searchable_cfg= $config['searchable_fields']  ?? null; // array|null
    $order_default = $config['order_default']      ?? ($pk . ' DESC');

    if ($pdo === null && $act !== 'selfcheck') {
        // 无 DB 时仅允许纯自定义的无 DB 动作；到这里说明未命中自定义动作
        json_error('数据库未连接，无法执行此操作。', 500);
    }

    // 计算字段白名单
    $all_cols = [];
    if ($pdo instanceof PDO) {
        $all_cols = get_table_columns($pdo, $table);
    }
    $readable = is_array($readable_cfg) ? array_values(array_filter($readable_cfg, 'is_safe_col')) : ($all_cols ?: [$pk]);
    if (!in_array($pk, $readable, true)) $readable[] = $pk;

    $writable = is_array($writable_cfg) ? array_values(array_filter($writable_cfg, 'is_safe_col')) : array_diff($readable, [$pk]);
    $searchable = is_array($searchable_cfg) ? array_values(array_filter($searchable_cfg, 'is_safe_col')) : [];

    // 动作路由
    switch ($act) {
        case 'list':
            handle_list($pdo, $table, $pk, $readable, $searchable, $soft_col, $order_default, $input);
            return;

        case 'get':
            handle_get($pdo, $table, $pk, $readable, $soft_col, $input);
            return;

        case 'create':
            handle_create($pdo, $table, $pk, $writable, $input);
            return;

        case 'update':
            handle_update($pdo, $table, $pk, $writable, $input);
            return;

        case 'delete':
            handle_delete($pdo, $table, $pk, $soft_col, $deleted_at_col, $input);
            return;

        default:
            json_error("未实现的标准动作：{$act}。", 501);
    }
}

/* =========================
 *       标准动作实现
 * ========================= */

function handle_list(PDO $pdo, string $table, string $pk, array $readable, array $searchable, ?string $soft_col, string $order_default, array $input): void {
    $page      = max(1, (int)($input['page'] ?? 1));
    $page_size = (int)($input['page_size'] ?? 20);
    if ($page_size <= 0) $page_size = 20;
    if ($page_size > 200) $page_size = 200;

    $q         = trim((string)($input['q'] ?? ''));
    $order_by  = parse_order_by($input['order_by'] ?? null, $readable, $order_default);

    $where = [];
    $params = [];

    if ($soft_col && is_safe_col($soft_col)) {
        $where[] = "{$soft_col} = 0";
    }

    if ($q !== '' && !empty($searchable)) {
        $like = [];
        foreach ($searchable as $col) {
            if (in_array($col, $readable, true) && is_safe_col($col)) {
                $like[] = "{$col} LIKE :q";
            }
        }
        if (!empty($like)) {
            $where[] = '(' . implode(' OR ', $like) . ')';
            $params[':q'] = '%' . $q . '%';
        }
    }

    // 附加的 where_* 参数（等值过滤）
    foreach ($input as $k => $v) {
        if (is_string($k) && str_starts_with($k, 'where_')) {
            $col = substr($k, 6);
            if (is_safe_col($col) && in_array($col, $readable, true)) {
                $where[] = "{$col} = :w_" . $col;
                $params[':w_' . $col] = $v;
            }
        }
    }

    $where_sql = '';
    if (!empty($where)) {
        $where_sql = ' WHERE ' . implode(' AND ', $where);
    }

    $offset = ($page - 1) * $page_size;

    // total
    $sql_cnt = "SELECT COUNT(*) AS cnt FROM {$table}{$where_sql}";
    $stmt = $pdo->prepare($sql_cnt);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $total = (int)$stmt->fetchColumn();

    // data
    $cols_sql = implode(', ', array_map(fn($c) => $c, $readable));
    $sql_data = "SELECT {$cols_sql} FROM {$table}{$where_sql} ORDER BY {$order_by} LIMIT :limit OFFSET :offset";
    $stmt2 = $pdo->prepare($sql_data);
    foreach ($params as $k => $v) $stmt2->bindValue($k, $v);
    $stmt2->bindValue(':limit', $page_size, PDO::PARAM_INT);
    $stmt2->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt2->execute();
    $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];

    json_success('ok', [
        'list'  => $rows,
        'meta'  => [
            'page'       => $page,
            'page_size'  => $page_size,
            'total'      => $total,
            'total_page' => (int)ceil($total / max(1, $page_size)),
            'order_by'   => $order_by,
            'query'      => $q,
        ],
    ]);
}

function handle_get(PDO $pdo, string $table, string $pk, array $readable, ?string $soft_col, array $input): void {
    $id = $input['id'] ?? $_GET['id'] ?? null;
    if ($id === null || $id === '') {
        json_error('缺少 id 参数。', 400);
    }

    $where = "{$pk} = :id";
    $params = [':id' => $id];

    if ($soft_col && is_safe_col($soft_col)) {
        $where .= " AND {$soft_col} = 0";
    }

    $cols_sql = implode(', ', array_map(fn($c) => $c, $readable));
    $sql = "SELECT {$cols_sql} FROM {$table} WHERE {$where} LIMIT 1";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error('记录不存在。', 404);
    }
    json_success('ok', $row);
}

function handle_create(PDO $pdo, string $table, string $pk, array $writable, array $input): void {
    $data = filter_writable_fields($input, $writable, $pk, null);
    if (empty($data)) {
        json_error('无可写入字段。', 400);
    }

    $cols = [];
    $vals = [];
    $params = [];
    foreach ($data as $k => $v) {
        $cols[] = $k;
        $vals[] = ':' . $k;
        $params[':' . $k] = $v;
    }

    $sql = "INSERT INTO {$table} (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();

    $new_id = $pdo->lastInsertId();
    json_success('created', ['id' => $new_id]);
}

function handle_update(PDO $pdo, string $table, string $pk, array $writable, array $input): void {
    $id = $input['id'] ?? $_GET['id'] ?? null;
    if ($id === null || $id === '') {
        json_error('缺少 id 参数。', 400);
    }

    $data = filter_writable_fields($input, $writable, $pk, null);
    if (empty($data)) {
        json_error('无可更新字段。', 400);
    }

    $sets = [];
    $params = [':__id' => $id];
    foreach ($data as $k => $v) {
        $sets[] = "{$k} = :{$k}";
        $params[':' . $k] = $v;
    }

    $sql = "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE {$pk} = :__id";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();

    json_success('updated', ['affected' => $stmt->rowCount()]);
}

function handle_delete(PDO $pdo, string $table, string $pk, ?string $soft_col, ?string $deleted_at_col, array $input): void {
    $id = $input['id'] ?? $_GET['id'] ?? null;
    if ($id === null || $id === '') {
        json_error('缺少 id 参数。', 400);
    }

    if ($soft_col && is_safe_col($soft_col)) {
        // 软删
        $sets = ["{$soft_col} = 1"];
        $params = [':__id' => $id];
        if ($deleted_at_col && is_safe_col($deleted_at_col)) {
            $sets[] = "{$deleted_at_col} = NOW()";
        }
        $sql = "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE {$pk} = :__id";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        json_success('deleted', ['soft' => true, 'affected' => $stmt->rowCount()]);
        return;
    }

    // 硬删
    $sql = "DELETE FROM {$table} WHERE {$pk} = :__id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':__id', $id);
    $stmt->execute();
    json_success('deleted', ['soft' => false, 'affected' => $stmt->rowCount()]);
}
