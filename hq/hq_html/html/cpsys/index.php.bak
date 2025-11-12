<?php
/**
 * Toptea HQ - CPSYS 主入口/控制器
 *
 * [A2.1 UTC SYNC]:
 * - 引入了 auth_core.php (session_start(), check_login())
 * - 引入了 datetime_helper.php (utc_now(), fmt_local())
 *
 * [!! 修复 3.1 !!]:
 * - 修复了 kds_sop_rules 和 pos_variants_management 页面中
 * 因 `getAllVariantsByMenuItemId` 函数缺失导致的致命错误。
 * (该函数仅用于旧版回退，现已从 kds_helper.php 中移除)
 * - 临时在 index.php 中补充了此函数的简化版 (fallback) 以防止崩溃。
 * - 补充了 `getKdsProductById` 的 fallback (用于 pos_variants_management)。
 *
 * [!! 修复 3.2 !!]:
 * - 修复了 `getAllVariantsByMenuItemId` fallback 中的 'product_sku' 键名错误。
 * (此错误记录于 php_errors_hq3.log)
 * - 键名应为 'product_code' (来自 mi.product_code)。
 *
 * [R2-Final] Integrated Seasons Pass (BMS/RMS) routes and data loading:
 * - Added cases: pos_tag_management, pos_seasons_pass_dashboard, pos_topup_orders, pos_redemptions_view
 * - Modified cases: pos_addon_management, pos_menu_management (to load $all_pos_tags)
 *
 * [R-Final FIX] Removed fatal error call to non-existent function check_login().
 * The require_once 'auth_core.php' already performs the check.
 */

// --- 1. 核心引导 ---
require_once realpath(__DIR__ . '/../../core/auth_core.php');
require_once realpath(__DIR__ . '/../../core/config.php');
require_once realpath(__DIR__ . '/../../core/helpers.php');
require_once realpath(__DIR__ . '/../../app/helpers/auth_helper.php');
require_once realpath(__DIR__ . '/../../app/helpers/kds_helper.php');
// [A2.1 UTC SYNC] 引入时间助手
require_once realpath(__DIR__ . '/../../app/helpers/datetime_helper.php');


// --- 2. 身份验证 ---
// [R-Final FIX] 移除 check_login();
// 包含 'auth_core.php' (第 16 行) 已自动执行检查。


// -----------------------------------------------------------------
// [!! 修复 3.1 !!] START: 临时 Fallback 函数
// -----------------------------------------------------------------
// 这些函数在 kds_helper.php 中可能已被移除，但旧视图文件仍在引用。
// 在 index.php 中定义它们，以防止在加载这些视图时出现致命错误。

if (!function_exists('getKdsProductById')) {
    /**
     * Fallback for getKdsProductById
     * (Required by pos_variants_management_view.php)
     */
    function getKdsProductById(PDO $pdo, int $id) {
        $stmt = $pdo->prepare("SELECT id, product_code FROM kds_products WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('getAllVariantsByMenuItemId')) {
    /**
     * Fallback for getAllVariantsByMenuItemId
     * (Required by kds_sop_rules_view.php and pos_variants_management_view.php)
     *
     * [!! 修复 3.2 !!]
     * 修复了 'product_sku' 键名错误 -> 'product_code'
     * [!! 修复 3.3 !!] (来自 index.php 修复 3.1)
     * 补全了 getAllVariantsByMenuItemId 缺失的 'recipe_name_zh' 字段
     */
    function getAllVariantsByMenuItemId(PDO $pdo, int $menu_item_id): array {
        $sql = "
            SELECT 
                v.*, 
                mi.product_code, 
                p.id AS product_id,
                p.product_code AS product_sku,  /* [!! 修复 3.2 !!] 别名 */
                pt.product_name AS recipe_name_zh /* [!! 修复 3.3 !!] 关联翻译 */
            FROM pos_item_variants v
            INNER JOIN pos_menu_items mi ON v.menu_item_id = mi.id
            LEFT JOIN kds_products p ON mi.product_code = p.product_code AND p.deleted_at IS NULL
            LEFT JOIN kds_product_translations pt ON p.id = pt.product_id AND pt.language_code = 'zh-CN'
            WHERE v.menu_item_id = ? AND v.deleted_at IS NULL
            ORDER BY v.sort_order ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$menu_item_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
// -----------------------------------------------------------------
// [!! 修复 3.1 !!] END: 临时 Fallback 函数
// -----------------------------------------------------------------


// --- 3. 数据库连接 ---
try {
    // [A2.1 UTC SYNC] 数据库连接已移至 config.php，此处仅设置 $pdo
    // $pdo 变量由 core/config.php 定义和初始化
    if (!isset($pdo) || !($pdo instanceof PDO)) {
         throw new Exception("PDO connection object (\$pdo) not found. Check core/config.php.");
    }
    
} catch (Exception $e) {
    // A2: 改进错误处理
    error_log("Database Connection Error: " . $e->getMessage());
    die("数据库连接失败。请检查日志。 (DB Connection Failed)");
}

// --- 4. 全局数据加载 ---

// [A2.1] 加载待处理班次复核 (用于侧边栏徽章)
$pending_shift_review_count = getPendingShiftReviewCount($pdo);


// --- 5. 页面路由和控制器 ---

$page = $_GET['page'] ?? 'dashboard';
$data = [];
$page_title = 'Dashboard';
$js_files = [];
$view_path = '';

try {
    switch ($page) {
        
        // --- 核心 ---
        case 'dashboard':
            check_role(ROLE_USER); // 任何登录用户
            $page_title = '仪表盘';
            // [A2.1] 加载仪表盘所需 JS
            $js_files = ['https://cdn.jsdelivr.net/npm/chart.js'];
            // [A2.1] 加载仪表盘所需数据
            $data['kpi_data'] = getDashboardKpis($pdo);
            $data['low_stock_alerts'] = getLowStockAlerts($pdo, 10);
            $data['sales_trend'] = getSalesTrendLast7Days($pdo);
            $data['top_products'] = getTopSellingProductsToday($pdo);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/dashboard_view.php');
            break;

        case 'profile':
            check_role(ROLE_USER); // 任何登录用户
            $page_title = '个人资料';
            $js_files = ['profile.js'];
            $data['current_user'] = getUserById($pdo, (int)$_SESSION['user_id']);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/profile_view.php');
            break;

        // --- RMS (配方) ---
        case 'material_management':
            check_role(ROLE_PRODUCT_MANAGER);
            $page_title = '物料管理 (RMS)';
            $js_files = ['material_management.js'];
            $data['materials'] = getAllMaterials($pdo);
            $data['unit_options'] = getAllUnits($pdo); // 用于下拉
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/material_management_view.php');
            break;
            
        case 'cup_management':
            check_role(ROLE_PRODUCT_MANAGER);
            $page_title = '杯型管理 (RMS)';
            $js_files = ['cup_management.js'];
            $data['cups'] = getAllCups($pdo);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/cup_management_view.php');
            break;

        case 'ice_option_management':
            check_role(ROLE_PRODUCT_MANAGER);
            $page_title = '冰量选项 (RMS)';
            $js_files = ['ice_option_management.js'];
            $data['ice_options'] = getAllIceOptions($pdo);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/ice_option_management_view.php');
            break;

        case 'sweetness_option_management':
            check_role(ROLE_PRODUCT_MANAGER);
            $page_title = '甜度选项 (RMS)';
            $js_files = ['sweetness_option_management.js'];
            $data['sweetness_options'] = getAllSweetnessOptions($pdo);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/sweetness_option_management_view.php');
            break;

        case 'product_status_management':
            check_role(ROLE_PRODUCT_MANAGER);
            $page_title = '产品状态 (RMS)';
            $js_files = ['product_status_management.js'];
            $data['statuses'] = getAllStatuses($pdo);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/product_status_management_view.php');
            break;

        case 'unit_management':
            check_role(ROLE_PRODUCT_MANAGER);
            $page_title = '单位管理 (RMS)';
            $js_files = ['unit_management.js'];
            $data['units'] = getAllUnits($pdo);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/unit_management_view.php');
            break;
            
        case 'rms_product_management':
            check_role(ROLE_PRODUCT_MANAGER);
            $page_title = '产品配方 (L1/L3)';
            $js_files = ['js/rms/rms_product_management.js']; // 修正路径
            $data['base_products'] = getAllBaseProducts($pdo); // kds_helper
            $data['material_options'] = getAllMaterials($pdo);
            $data['unit_options'] = getAllUnits($pdo);
            $data['cup_options'] = getAllCups($pdo);
            $data['sweetness_options'] = getAllSweetnessOptions($pdo);
            $data['ice_options'] = getAllIceOptions($pdo);
            $data['status_options'] = getAllStatuses($pdo);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/rms/rms_product_management_view.php');
            break;

        case 'rms_global_rules':
            check_role(ROLE_PRODUCT_MANAGER);
            $page_title = '全局规则 (L2)';
            $js_files = ['js/rms/rms_global_rules.js']; // 修正路径
            $data['global_rules'] = getAllGlobalRules($pdo);
            $data['material_options'] = getAllMaterials($pdo);
            $data['unit_options'] = getAllUnits($pdo);
            $data['cup_options'] = getAllCups($pdo);
            $data['sweetness_options'] = getAllSweetnessOptions($pdo);
            $data['ice_options'] = getAllIceOptions($pdo);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/rms/rms_global_rules_view.php');
            break;

        // --- BMS (POS) ---
        case 'pos_category_management':
            check_role(ROLE_PRODUCT_MANAGER);
            $page_title = 'POS 分类管理';
            $js_files = ['pos_category_management.js'];
            $data['pos_categories'] = getAllPosCategories($pdo);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/pos_category_management_view.php');
            break;

        case 'pos_menu_management':
            check_role(ROLE_PRODUCT_MANAGER);
            $page_title = 'POS 菜单商品管理';
            $js_files = ['pos_menu_management.js'];
            $data['menu_items'] = getAllMenuItems($pdo); // kds_helper
            $data['pos_categories'] = getAllPosCategories($pdo);
            // [R2-Final] 注入 R2.3 页面所需的标签数据
            $data['all_pos_tags'] = getAllPosTags($pdo); 
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/pos_menu_management_view.php');
            break;

        case 'pos_variants_management':
            check_role(ROLE_PRODUCT_MANAGER);
            $page_title = 'POS 商品规格管理';
            $menu_item_id = (int)($_GET['menu_item_id'] ?? 0);
            if ($menu_item_id > 0) {
                $data['menu_item'] = getMenuItemById($pdo, $menu_item_id);
                if (!$data['menu_item']) {
                    $page_title = '错误';
                    $data['error_message'] = '未找到指定的菜单商品 (ID: ' . $menu_item_id . ')。';
                    $view_path = realpath(__DIR__ . '/../../app/views/cpsys/error_view.php');
                    break;
                }
                $page_title = '管理规格: ' . htmlspecialchars($data['menu_item']['name_zh']);
                $data['variants'] = getAllVariantsByMenuItemId($pdo, $menu_item_id);
                $data['recipes'] = getAllBaseProducts($pdo); // 用于关联 KDS P-Code
                $js_files = ['pos_variants_management.js'];
                $view_path = realpath(__DIR__ . '/../../app/views/cpsys/pos_variants_management_view.php');
            } else {
                header('Location: ?page=pos_menu_management');
                exit;
            }
            break;

        case 'pos_addon_management':
            check_role(ROLE_PRODUCT_MANAGER);
            $page_title = 'POS 加料管理';
            $js_files = ['pos_addon_management.js'];
            $data['addons'] = getAllPosAddons($pdo);
            $data['materials'] = getAllMaterials($pdo);
            // [R2-Final] 注入 R2.2 页面所需的标签数据
            $data['all_pos_tags'] = getAllPosTags($pdo);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/pos_addon_management_view.php');
            break;

        // --- [R2-Final] START: 新增次卡路由 (BMS/RMS) ---
        
        case 'pos_tag_management': // R2.1
            check_role(ROLE_PRODUCT_MANAGER);
            $page_title = 'POS 标签管理 (次卡)';
            $js_files = ['pos_tags_management.js'];
            $data['pos_tags'] = getAllPosTags($pdo);
            // ================== [ 1.png 错误修复 ] ==================
            // 修复了视图文件名的拼写错误：
            // pos_tag_management_view.php -> pos_tags_management_view.php
            // =======================================================
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/pos_tags_management_view.php');
            break;

        case 'pos_seasons_pass_dashboard': // B3
            check_role(ROLE_ADMIN);
            $page_title = '次卡数据看板 (B3)';
            $js_files = []; // 只读页面
            $data['kpis'] = getSeasonsPassDashboardKpis($pdo);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/pos_seasons_pass_dashboard_view.php');
            break;
            
        case 'pos_topup_orders': // B1
            check_role(ROLE_ADMIN);
            $page_title = '售卡(VR)审核 (B1)';
            $js_files = ['pos_topup_orders.js'];
            $data['topup_orders'] = getAllTopupOrders($pdo);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/pos_topup_orders_view.php');
            break;

        case 'pos_redemptions_view': // B2
            check_role(ROLE_ADMIN);
            $page_title = '核销(TP)查询 (B2)';
            $js_files = []; // 只读页面
            $data['redemption_batches'] = getAllRedemptionBatches($pdo);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/pos_redemptions_view.php');
            break;
            
        // --- [R2-Final] END ---
            
        case 'pos_promotion_management':
            check_role(ROLE_PRODUCT_MANAGER);
            $page_title = '营销活动管理';
            $js_files = ['pos_promotion_management.js'];
            $data['promotions'] = getAllPromotions($pdo);
            $data['menu_items_for_select'] = getAllMenuItemsForSelect($pdo); // 修正
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/pos_promotion_management_view.php');
            break;
            
        // --- CRM (会员) ---
        case 'pos_member_management':
            check_role(ROLE_ADMIN);
            $page_title = '会员管理';
            $js_files = ['pos_member_management.js'];
            $data['members'] = getAllMembers($pdo);
            $data['member_levels'] = getAllMemberLevels($pdo);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/pos_member_management_view.php');
            break;

        case 'pos_member_level_management':
            check_role(ROLE_ADMIN);
            $page_title = '会员等级管理';
            $js_files = ['pos_member_level_management.js'];
            $data['member_levels'] = getAllMemberLevels($pdo);
            $data['promotions_for_select'] = getAllPromotions($pdo); // 修正
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/pos_member_level_management_view.php');
            break;
            
        case 'pos_member_settings':
            check_role(ROLE_ADMIN);
            $page_title = '积分/会员设置';
            $js_files = ['pos_member_settings.js'];
            // 数据由 JS (load) 异步加载
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/pos_member_settings_view.php');
            break;

        case 'pos_point_redemption_rules':
            check_role(ROLE_ADMIN);
            $page_title = '积分兑换规则';
            $js_files = ['pos_point_redemption_rules.js'];
            $data['rules'] = getAllRedemptionRules($pdo);
            $data['promotions_for_select'] = getAllPromotions($pdo); // 修正
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/pos_point_redemption_rules_view.php');
            break;

        // --- 门店 / 财务 ---
        case 'store_management':
            check_role(ROLE_ADMIN);
            $page_title = '门店管理';
            $js_files = ['store_management.js'];
            $data['stores'] = getAllStores($pdo);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/store_management_view.php');
            break;
            
        case 'pos_invoice_list':
            check_role(ROLE_ADMIN);
            $page_title = '票据列表 (SIF)';
            $js_files = []; // 列表页只读
            $data['invoices'] = getAllInvoices($pdo);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/pos_invoice_list_view.php');
            break;

        case 'pos_invoice_detail':
            check_role(ROLE_ADMIN);
            $invoice_id = (int)($_GET['id'] ?? 0);
            if ($invoice_id <= 0) {
                 header('Location: ?page=pos_invoice_list'); exit;
            }
            $data['invoice_data'] = getInvoiceDetails($pdo, $invoice_id); // 修正变量名
            if (!$data['invoice_data']) {
                header('Location: ?page=pos_invoice_list'); exit;
            }
            $page_title = '票据详情: ' . htmlspecialchars($data['invoice_data']['series'] . '-' . $data['invoice_data']['number']);
            $js_files = ['pos_invoice_management.js']; // JS 用于作废/更正
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/pos_invoice_detail_view.php');
            break;

        case 'sif_declaration':
            check_role(ROLE_SUPER_ADMIN);
            $page_title = 'SIF 声明管理';
            $js_files = ['sif_declaration.js'];
            // 数据由 JS (load_sif) 异步加载
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/sif_declaration_view.php');
            break;

        case 'pos_eod_reports':
            check_role(ROLE_ADMIN);
            $page_title = 'EOD 营业报告';
            $js_files = []; // 只读
            $data['eod_reports'] = getAllEodReports($pdo);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/pos_eod_reports_view.php');
            break;
            
        case 'pos_shift_review':
            check_role(ROLE_ADMIN);
            $page_title = '班次复核';
            $js_files = ['pos_shift_review.js'];
            $data['pending_reviews'] = getPendingShiftReviews($pdo);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/pos_shift_review_view.php');
            break;

        // --- KDS / 库存 ---
        case 'kds_user_management':
            check_role(ROLE_ADMIN);
            $store_id = (int)($_GET['store_id'] ?? 0);
            if ($store_id <= 0) {
                 header('Location: ?page=store_management'); exit;
            }
            $data['store_data'] = getStoreById($pdo, $store_id);
            if (!$data['store_data']) {
                 header('Location: ?page=store_management'); exit;
            }
            $page_title = 'KDS 员工管理: ' . htmlspecialchars($data['store_data']['store_name']);
            $js_files = ['kds_user_management.js'];
            $data['kds_users'] = getAllKdsUsersByStoreId($pdo, $store_id);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/kds_user_management_view.php');
            break;

        case 'warehouse_stock_management':
            check_role(ROLE_ADMIN);
            $page_title = '总仓库存管理';
            $js_files = ['warehouse_stock_logic.js'];
            $data['stock_items'] = getWarehouseStock($pdo);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/warehouse_stock_management_view.php');
            break;
            
        case 'store_stock_view':
            check_role(ROLE_ADMIN);
            $page_title = '门店库存 (只读)';
            $js_files = []; // 只读
            $data['stock_data'] = getAllStoreStock($pdo);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/store_stock_view.php');
            break;
            
        case 'expiry_management':
            check_role(ROLE_ADMIN);
            $page_title = '效期管理';
            $js_files = []; // 只读
            $data['expiry_items'] = getAllExpiryItems($pdo);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/expiry_management_view.php');
            break;
            
        case 'product_availability':
            check_role(ROLE_PRODUCT_MANAGER); // 权限变更为产品经理
            $page_title = '物料清单与上架';
            $js_files = ['product_availability.js'];
            $data['material_options'] = getAllMaterials($pdo);
            // $data['availability'] = ...; // 数据由 JS 异步加载
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/product_availability_view.php');
            break;

        // --- 系统 ---
        case 'user_management':
            check_role(ROLE_SUPER_ADMIN);
            $page_title = 'HQ 账户管理';
            $js_files = ['user_management.js'];
            $data['users'] = getAllUsers($pdo);
            $data['roles'] = getAllRoles($pdo);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/user_management_view.php');
            break;
            
        case 'pos_print_template_management':
            check_role(ROLE_SUPER_ADMIN);
            $page_title = '打印模板管理';
            $js_files = ['pos_print_template_editor.js'];
            $data['templates'] = getAllPrintTemplates($pdo);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/pos_print_template_management_view.php');
            break;
            
        case 'pos_print_template_variables':
            check_role(ROLE_SUPER_ADMIN);
            $page_title = '打印模板变量说明';
            $js_files = [];
             try {
                $stmt = $pdo->query("SELECT template_type, template_content FROM pos_print_templates WHERE store_id IS NULL AND is_active = 1");
                $data['default_templates'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            } catch (Throwable $e) { $data['default_templates'] = []; }
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/pos_print_template_variables_view.php');
            break;

        // --- WIP / 占位符 ---
        case 'kds_sop_rules':
            check_role(ROLE_SUPER_ADMIN);
            $page_title = 'KDS SOP 解析规则';
            $js_files = ['kds_sop_rules.js'];
            $data['stores'] = getAllStores($pdo);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/kds_sop_rules_view.php');
            break;
            
        case 'stock_allocation':
            check_role(ROLE_ADMIN); // 权限变更为管理员
            $page_title = '库存调拨';
            $js_files = ['stock_allocation.js'];
            $data['stores'] = getAllStores($pdo);
            $data['materials'] = getAllMaterials($pdo);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/stock_allocation_view.php');
            break;

        // --- 默认/回退 ---
        default:
            $page = 'dashboard';
            check_role(ROLE_USER);
            $page_title = '仪表盘 (Default)';
            $js_files = ['https://cdn.jsdelivr.net/npm/chart.js'];
            $data['kpi_data'] = getDashboardKpis($pdo);
            $data['low_stock_alerts'] = getLowStockAlerts($pdo, 10);
            $data['sales_trend'] = getSalesTrendLast7Days($pdo);
            $data['top_products'] = getTopSellingProductsToday($pdo);
            $view_path = realpath(__DIR__ . '/../../app/views/cpsys/dashboard_view.php');
            break;
    }

} catch (AuthException $e) {
    // 权限不足
    $page_title = '权限不足';
    $data['error_message'] = $e->getMessage();
    $view_path = realpath(__DIR__ . '/../../app/views/cpsys/access_denied_view.php');
} catch (Exception $e) {
    // 其他所有错误
    $page_title = '系统错误';
    $data['error_message'] = $e->getMessage();
    $data['error_trace'] = $e->getTraceAsString();
    $view_path = realpath(__DIR__ . '/../../app/views/cpsys/error_view.php'); // 假设有一个通用错误视图
    
    // 记录严重错误
    error_log("Unhandled Exception in index.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
}


// --- 6. 渲染布局 ---
// $data 数组中的所有键都将作为变量在 main.php 和 $view_path 中可用
extract($data);
include realpath(__DIR__ . '/../../app/views/cpsys/layouts/main.php');