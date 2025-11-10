<?php
/**
 * Toptea HQ - cpsys main entry
 * Revision: 2.4.1  |  2025-11-10
 * - (Eng) Changed rms_product_management list sort order to product_code ASC.
 * - Robust view fallback (no dependency on error_view.php)
 * - Adds minimal handlers/data for: expiry_management, pos_eod_reports, pos_shift_review
 * - Safe fallbacks for commonly-missing helper functions
 * - No closing "?>" to avoid stray output
 *
 * [GEMINI DASHBOARD V1.0]
 * - Updated 'dashboard' case to load all necessary data for the new widgets.
 */

declare(strict_types=1);

/* ---------------- Debug switch ---------------- */
if (isset($_GET['__debug'])) {
    ini_set('display_errors','1');
    ini_set('display_startup_errors','1');
    error_reporting(E_ALL);
    set_exception_handler(function($e){
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "EXCEPTION: {$e->getMessage()}\n{$e->getFile()}:{$e->getLine()}\n".$e->getTraceAsString();
    });
    register_shutdown_function(function(){
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR], true)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo "FATAL: {$e['message']}\n{$e['file']}:{$e['line']}\n";
        }
    });
}
header('Content-Type: text/html; charset=utf-8');

/* ---------------- Core loads (keep order) ---------------- */
require_once realpath(__DIR__ . '/../../core/auth_core.php');
require_once realpath(__DIR__ . '/../../core/config.php'); // APP_PATH + $pdo
require_once APP_PATH . '/helpers/kds_helper.php';
require_once APP_PATH . '/helpers/auth_helper.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    die('Critical Error: $pdo not initialized.');
}

/* ---------------- ensure repo_a (best-effort) ---------------- */
function __ensure_repo_a(): void {
    static $done = false;
    if ($done) return;
    $paths = [
        APP_PATH . '/helpers/kds/kds_repo_a.php',
        realpath(__DIR__ . '/../../app/helpers/kds/kds_repo_a.php'),
    ];
    foreach ($paths as $p) {
        if ($p && is_file($p)) { require_once $p; $done = true; return; }
    }
}
__ensure_repo_a();

/* ---------------- Fallback functions (define only if missing) ---------------- */
if (!function_exists('getAllMaterials')) {
    function getAllMaterials(PDO $pdo): array {
        try { return $pdo->query("SELECT * FROM kds_materials WHERE deleted_at IS NULL ORDER BY material_code ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { return []; }
    }
}
if (!function_exists('getAllUnits')) {
    function getAllUnits(PDO $pdo): array {
        try { return $pdo->query("SELECT id, unit_code FROM kds_units WHERE deleted_at IS NULL ORDER BY unit_code ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { return []; }
    }
}
if (!function_exists('getAllStores')) {
    function getAllStores(PDO $pdo): array {
        try { return $pdo->query("SELECT * FROM kds_stores WHERE deleted_at IS NULL ORDER BY store_code ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { return []; }
    }
}
if (!function_exists('getStoreById')) {
    function getStoreById(PDO $pdo, int $id) {
        try { $s=$pdo->prepare("SELECT * FROM kds_stores WHERE id=? AND deleted_at IS NULL LIMIT 1"); $s->execute([$id]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }
        catch (Throwable $e) { return null; }
    }
}
if (!function_exists('getAllCups')) {
    function getAllCups(PDO $pdo): array {
        try { return $pdo->query("SELECT * FROM kds_cups ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { return []; }
    }
}
if (!function_exists('getAllSweetnessOptions')) {
    function getAllSweetnessOptions(PDO $pdo): array {
        try { return $pdo->query("SELECT * FROM kds_sweetness_options ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { return []; }
    }
}
if (!function_exists('getAllIceOptions')) {
    function getAllIceOptions(PDO $pdo): array {
        try { return $pdo->query("SELECT * FROM kds_ice_options ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { return []; }
    }
}
if (!function_exists('getAllStatuses')) {
    function getAllStatuses(PDO $pdo): array {
        try { return $pdo->query("SELECT * FROM kds_product_statuses ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { return []; }
    }
}

/* POS/Menu */
if (!function_exists('getAllMenuItems')) {
    function getAllMenuItems(PDO $pdo): array {
        try { return $pdo->query("SELECT * FROM pos_menu_items WHERE deleted_at IS NULL ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { return []; }
    }
}
if (!function_exists('getMenuItemById')) {
    function getMenuItemById(PDO $pdo, int $id) {
        try { $s=$pdo->prepare("SELECT * FROM pos_menu_items WHERE id=? AND deleted_at IS NULL LIMIT 1"); $s->execute([$id]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }
        catch (Throwable $e) { return null; }
    }
}
if (!function_exists('getAllVariantsByMenuItemId')) {
    function getAllVariantsByMenuItemId(PDO $pdo, int $item_id): array {
        try { $s=$pdo->prepare("SELECT * FROM pos_item_variants WHERE menu_item_id=? AND deleted_at IS NULL ORDER BY sort_order ASC, id ASC"); $s->execute([$item_id]); return $s->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { return []; }
    }
}
if (!function_exists('getAllPosCategories')) {
    function getAllPosCategories(PDO $pdo): array {
        try { return $pdo->query("SELECT * FROM pos_categories WHERE deleted_at IS NULL ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { return []; }
    }
}
if (!function_exists('getAllMenuItemsForSelect')) {
    function getAllMenuItemsForSelect(PDO $pdo): array {
        try { return $pdo->query("SELECT id, item_code, is_active FROM pos_menu_items WHERE deleted_at IS NULL ORDER BY item_code ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { return []; }
    }
}

/* Promotions / Members / Templates / Reports / Shifts */
if (!function_exists('getAllPromotions')) {
    function getAllPromotions(PDO $pdo): array {
        try { return $pdo->query("SELECT * FROM pos_promotions ORDER BY promo_priority ASC, id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { return []; }
    }
}
if (!function_exists('getAllMemberLevels')) {
    function getAllMemberLevels(PDO $pdo): array {
        try { return $pdo->query("SELECT * FROM pos_member_levels ORDER BY sort_order ASC, points_threshold ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { return []; }
    }
}
if (!function_exists('getAllMembers')) {
    function getAllMembers(PDO $pdo): array {
        try { return $pdo->query("SELECT * FROM pos_members WHERE deleted_at IS NULL ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { return []; }
    }
}
if (!function_exists('getAllRedemptionRules')) {
    function getAllRedemptionRules(PDO $pdo): array {
        try { return $pdo->query("SELECT * FROM pos_point_redemption_rules WHERE deleted_at IS NULL ORDER BY points_required ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { return []; }
    }
}
if (!function_exists('getAllPrintTemplates')) {
    function getAllPrintTemplates(PDO $pdo): array {
        try { return $pdo->query("SELECT * FROM pos_print_templates ORDER BY template_type ASC, template_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { return []; }
    }
}
if (!function_exists('getAllEodReports')) {
    function getAllEodReports(PDO $pdo): array {
        try { return $pdo->query("SELECT * FROM pos_eod_reports ORDER BY report_date DESC, id DESC LIMIT 365")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { return []; }
    }
}
if (!function_exists('getPendingShiftReviews')) {
    function getPendingShiftReviews(PDO $pdo): array {
        try { return $pdo->query("SELECT * FROM pos_shift_reviews WHERE review_status='PENDING' ORDER BY created_at DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { return []; }
    }
}

/* Expiry system (效期) */
if (!function_exists('getAllExpiryItems')) {
    function getAllExpiryItems(PDO $pdo): array {
        try { return $pdo->query("SELECT * FROM expsys_expiry_items ORDER BY id DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { return []; }
    }
}

/* Users / Roles */
if (!function_exists('getAllUsers')) {
    function getAllUsers(PDO $pdo): array {
        try { return $pdo->query("SELECT id, username, display_name, role, is_active FROM kds_users WHERE deleted_at IS NULL ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { return []; }
    }
}
if (!function_exists('getAllRoles')) {
    function getAllRoles(PDO $pdo): array {
        try { return $pdo->query("SELECT * FROM kds_roles ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { return []; }
    }
}
if (!function_exists('getUserById')) {
    function getUserById(PDO $pdo, int $id) {
        try { $s=$pdo->prepare("SELECT * FROM kds_users WHERE id=? LIMIT 1"); $s->execute([$id]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }
        catch (Throwable $e) { return null; }
    }
}

/* ---------------- Routing ---------------- */
$page   = $_GET['page'] ?? 'dashboard';
$page_js = null;

/* ACL: allow product managers to access some RMS pages; others gated */
if (($_SESSION['role_id'] ?? null) !== ROLE_SUPER_ADMIN
    && !in_array($page, ['dashboard','profile','rms_product_management','sif_declaration'], true)) {
    $page = 'access_denied';
}

switch ($page) {
    case 'dashboard':
        // --- [GEMINI DASHBOARD V1.0] START: Load data for dashboard ---
        $page_title   = '仪表盘';
        $content_view = APP_PATH . '/views/cpsys/dashboard_view.php';
        
        // 1. Get KPIs
        $kpi_data = getDashboardKpis($pdo);
        // 2. Get Pending Tasks (re-use existing function)
        $pending_shift_reviews_count = getPendingShiftReviewCount($pdo);
        // 3. Get Low Stock Alerts
        $low_stock_alerts = getLowStockAlerts($pdo, 10); // 阈值设为10
        // 4. Get Sales Trend
        $sales_trend = getSalesTrendLast7Days($pdo);
        // 5. Get Top Products
        $top_products = getTopSellingProductsToday($pdo);
        // --- [GEMINI DASHBOARD V1.0] END ---
        break;

    /* RMS - 产品 */
    case 'rms_product_management':
        $page_title        = 'RMS - 产品配方 (L1/L3)';
        
        // ================== [GEMINI SORTING FIX START] ==================
        // 修复了 `php_errors_hq.log` 中报告的 Undefined array key "name_zh" 错误。
        // 并且根据用户请求，将排序从 p.id DESC 更改为 p.product_code ASC。
        if (!function_exists('getAllBaseProducts')) {
            function getAllBaseProducts(PDO $pdo): array {
                try { 
                    $sql = "
                        SELECT 
                            p.id, 
                            p.product_code, 
                            t_zh.product_name AS name_zh 
                        FROM kds_products p
                        LEFT JOIN kds_product_translations t_zh 
                            ON p.id = t_zh.product_id AND t_zh.language_code = 'zh-CN'
                        WHERE p.deleted_at IS NULL 
                        ORDER BY p.product_code ASC
                    ";
                    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: []; 
                }
                catch (Throwable $e) { 
                    error_log("Fallback getAllBaseProducts failed: " . $e->getMessage());
                    return []; 
                }
            }
        }
        // ================== [GEMINI SORTING FIX END] ==================

        $base_products     = getAllBaseProducts($pdo);
        $material_options  = getAllMaterials($pdo);
        $unit_options      = getAllUnits($pdo);
        $cup_options       = getAllCups($pdo);
        $sweetness_options = getAllSweetnessOptions($pdo);
        $ice_options       = getAllIceOptions($pdo);
        $status_options    = getAllStatuses($pdo);
        $content_view      = APP_PATH . '/views/cpsys/rms/rms_product_management_view.php';
        $page_js           = 'rms/rms_product_management.js';
        break;

    /* RMS - 全局规则 */
    case 'rms_global_rules':
        if (($_SESSION['role_id'] ?? null) !== ROLE_SUPER_ADMIN) { $page = 'access_denied'; break; }
        $page_title        = 'RMS - 全局规则 (L2)';
        if (!function_exists('getAllGlobalRules')) {
            function getAllGlobalRules(PDO $pdo): array {
                try { return $pdo->query("SELECT * FROM kds_global_adjustment_rules ORDER BY priority ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
                catch (Throwable $e) { return []; }
            }
        }
        $global_rules      = getAllGlobalRules($pdo);
        $material_options  = getAllMaterials($pdo);
        $unit_options      = getAllUnits($pdo);
        $cup_options       = getAllCups($pdo);
        $sweetness_options = getAllSweetnessOptions($pdo);
        $ice_options       = getAllIceOptions($pdo);
        $content_view      = APP_PATH . '/views/cpsys/rms/rms_global_rules_view.php';
        $page_js           = 'rms/rms_global_rules.js';
        break;

    /* KDS - SOP 解析规则 */
    case 'kds_sop_rules':
        if (($_SESSION['role_id'] ?? null) !== ROLE_SUPER_ADMIN) { $page = 'access_denied'; break; }
        $page_title   = 'KDS - SOP 解析规则';
        $stores       = getAllStores($pdo);
        $content_view = APP_PATH . '/views/cpsys/kds_sop_rules_view.php';
        $page_js      = 'kds_sop_rules.js';
        break;

    /* 效期管理 */
    case 'expiry_management':
        $page_title   = '效期管理';
        $expiry_items = getAllExpiryItems($pdo);
        $content_view = APP_PATH . '/views/cpsys/expiry_management_view.php';
        break;

    /* 仓库/库存 */
    case 'warehouse_stock_management':
        $page_title   = '总仓库存';
        if (!function_exists('getWarehouseStock')) {
            function getWarehouseStock(PDO $pdo): array {
                try { return $pdo->query("SELECT * FROM expsys_warehouse_stock ORDER BY material_id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
                catch (Throwable $e) { return []; }
            }
        }
        $stock_items  = getWarehouseStock($pdo);
        $content_view = APP_PATH . '/views/cpsys/warehouse_stock_management_view.php';
        $page_js      = 'warehouse_stock_logic.js';
        break;

    case 'stock_allocation':
        $page_title   = '库存调拨';
        $stores       = getAllStores($pdo);
        $materials    = getAllMaterials($pdo);
        $content_view = APP_PATH . '/views/cpsys/stock_allocation_view.php';
        $page_js      = 'stock_allocation.js';
        break;

    case 'store_stock_view':
        $page_title   = '门店库存';
        if (!function_exists('getAllStoreStock')) {
            function getAllStoreStock(PDO $pdo): array {
                try { return $pdo->query("SELECT * FROM expsys_store_stock ORDER BY store_id ASC, material_id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
                catch (Throwable $e) { return []; }
            }
        }
        $stock_data   = getAllStoreStock($pdo);
        $content_view = APP_PATH . '/views/cpsys/store_stock_view.php';
        break;

    /* POS 菜单 / 分类 / 规格 */
    case 'pos_menu_management':
        $page_title     = 'POS 管理 - 菜单管理';
        $menu_items     = getAllMenuItems($pdo);
        $pos_categories = getAllPosCategories($pdo);
        $content_view   = APP_PATH . '/views/cpsys/pos_menu_management_view.php';
        $page_js        = 'pos_menu_management.js';
        break;

    case 'pos_variants_management':
        $item_id = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT);
        if (!$item_id) { die("无效的商品ID。"); }
        $menu_item = getMenuItemById($pdo, $item_id);
        if (!$menu_item) { die("未找到指定的商品。"); }
        $page_title  = 'POS 管理 - 管理规格';
        $variants    = getAllVariantsByMenuItemId($pdo, $item_id);
        if (!function_exists('getAllProductRecipesForSelect')) {
            function getAllProductRecipesForSelect(PDO $pdo): array {
                try { return $pdo->query("SELECT id, product_code FROM kds_products WHERE deleted_at IS NULL ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
                catch (Throwable $e) { return []; }
            }
        }
        $recipes     = getAllProductRecipesForSelect($pdo);
        $content_view= APP_PATH . '/views/cpsys/pos_variants_management_view.php';
        $page_js     = 'pos_variants_management.js';
        break;

    case 'pos_category_management':
        $page_title   = '字典管理 - POS分类';
        $pos_categories = getAllPosCategories($pdo);
        $content_view = APP_PATH . '/views/cpsys/pos_category_management_view.php';
        $page_js      = 'pos_category_management.js';
        break;

    case 'pos_addon_management':
        $page_title   = 'POS 管理 - 加料管理';
        if (!function_exists('getAllPosAddons')) {
            function getAllPosAddons(PDO $pdo): array {
                try { return $pdo->query("SELECT * FROM pos_addons WHERE deleted_at IS NULL ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
                catch (Throwable $e) { return []; }
            }
        }
        $addons      = getAllPosAddons($pdo);
        $materials   = getAllMaterials($pdo);
        $content_view = APP_PATH . '/views/cpsys/pos_addon_management_view.php';
        $page_js      = 'pos_addon_management.js';
        break;

    /* --- [新功能] START: 产品物料清单 (Product Availability) --- */
    case 'product_availability':
        $page_title   = 'POS 管理 - 物料清单与上架';
        // 视图需要物料列表来进行搜索
        $material_options = getAllMaterials($pdo);
        $content_view = APP_PATH . '/views/cpsys/product_availability_view.php';
        $page_js      = 'product_availability.js';
        break;
    /* --- [新功能] END --- */

    /* POS 发票/报告 */
    case 'pos_invoice_list':
        $page_title   = 'POS 管理 - 票据查询';
        if (!function_exists('getAllInvoices')) {
            function getAllInvoices(PDO $pdo): array {
                try { return $pdo->query("SELECT * FROM pos_invoices ORDER BY id DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
                catch (Throwable $e) { return []; }
            }
        }
        $invoices    = getAllInvoices($pdo);
        $content_view = APP_PATH . '/views/cpsys/pos_invoice_list_view.php';
        break;

    case 'pos_invoice_detail':
        $invoice_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$invoice_id) { die("无效的票据ID。"); }
        if (!function_exists('getInvoiceDetails')) {
            function getInvoiceDetails(PDO $pdo, int $id) {
                try { $s=$pdo->prepare("SELECT * FROM pos_invoices WHERE id=? LIMIT 1"); $s->execute([$id]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }
                catch (Throwable $e) { return null; }
            }
        }
        $invoice_data = getInvoiceDetails($pdo, $invoice_id);
        if (!$invoice_data) { die("未找到指定的票据。"); }
        $page_title   = 'POS 票据详情: ' . htmlspecialchars(($invoice_data['series'] ?? '') . '-' . ($invoice_data['number'] ?? ''));
        $content_view = APP_PATH . '/views/cpsys/pos_invoice_detail_view.php';
        $page_js      = 'pos_invoice_management.js';
        break;

    /* POS 营销 / 会员 */
    case 'pos_promotion_management':
        $page_title   = 'POS 管理 - 营销活动管理';
        $promotions   = getAllPromotions($pdo);
        $menu_items_for_select = getAllMenuItemsForSelect($pdo);
        echo "<script>window.menuItemsForSelect = " . json_encode($menu_items_for_select) . ";</script>";
        $content_view = APP_PATH . '/views/cpsys/pos_promotion_management_view.php';
        $page_js      = 'pos_promotion_management.js';
        break;

    case 'pos_member_level_management':
        $page_title   = 'POS 管理 - 会员等级';
        $member_levels = getAllMemberLevels($pdo);
        $promotions_for_select = getAllPromotions($pdo);
        $content_view = APP_PATH . '/views/cpsys/pos_member_level_management_view.php';
        $page_js      = 'pos_member_level_management.js';
        break;

    case 'pos_member_management':
        $page_title   = 'POS 管理 - 会员列表';
        $members      = getAllMembers($pdo);
        $member_levels = getAllMemberLevels($pdo);
        $content_view = APP_PATH . '/views/cpsys/pos_member_management_view.php';
        $page_js      = 'pos_member_management.js';
        break;

    case 'pos_member_settings':
        $page_title   = 'POS 管理 - 会员积分设置';
        $content_view = APP_PATH . '/views/cpsys/pos_member_settings_view.php';
        $page_js      = 'pos_member_settings.js';
        break;

    case 'pos_point_redemption_rules':
        $page_title   = 'POS 管理 - 积分兑换规则';
        $rules        = getAllRedemptionRules($pdo);
        $promotions_for_select = getAllPromotions($pdo);
        $content_view = APP_PATH . '/views/cpsys/pos_point_redemption_rules_view.php';
        $page_js      = 'pos_point_redemption_rules.js';
        break;

    /* 打印模板 */
    case 'pos_print_template_management':
        $page_title   = '系统设置 - 打印模板管理';
        $templates    = getAllPrintTemplates($pdo);
        $content_view = APP_PATH . '/views/cpsys/pos_print_template_management_view.php';
        $page_js      = 'pos_print_template_editor.js';
        break;

    case 'pos_print_template_variables':
        $page_title        = '系统设置 - 打印模板变量';
        $default_templates = [];
        try {
            $stmt = $pdo->query("SELECT template_type, template_content FROM pos_print_templates WHERE store_id IS NULL AND is_active = 1");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $default_templates[$row['template_type']] = $row['template_content'];
            }
        } catch (Throwable $e) { /* noop */ }
        $content_view = APP_PATH . '/views/cpsys/pos_print_template_variables_view.php';
        break;

    /* SIF 合规性声明（GET/POST） */
    case 'sif_declaration':
        $page_title = '系统设置 - 合规性声明 (SIF)';
        $sif_declaration_text = false;
        $sif_save_ok = false;
        $sif_error = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $posted_text = $_POST['sif_text'] ?? '';
            try {
                $stmt = $pdo->prepare("SELECT 1 FROM pos_settings WHERE setting_key='sif_declaracion_responsable' LIMIT 1");
                $stmt->execute();
                if ($stmt->fetchColumn()) {
                    $u = $pdo->prepare("UPDATE pos_settings SET setting_value=? WHERE setting_key='sif_declaracion_responsable'");
                    $u->execute([$posted_text]);
                } else {
                    $i = $pdo->prepare("INSERT INTO pos_settings (setting_key, setting_value) VALUES ('sif_declaracion_responsable', ?)");
                    $i->execute([$posted_text]);
                }
                $sif_save_ok = true;
                $sif_declaration_text = $posted_text;
            } catch (Throwable $e) {
                $sif_error = $e->getMessage();
            }
        }

        if ($sif_declaration_text === false) {
            try {
                $stmt = $pdo->prepare("SELECT setting_value FROM pos_settings WHERE setting_key='sif_declaracion_responsable' LIMIT 1");
                $stmt->execute();
                $sif_declaration_text = $stmt->fetchColumn(); // false | '' | string
            } catch (Throwable $e) {
                $sif_error = $sif_error ?: $e->getMessage();
            }
        }

        $content_view = APP_PATH . '/views/cpsys/sif_declaration_view.php';
        $page_js      = 'sif_declaration.js';
        break;

    /* 字典与系统 */
    case 'cup_management':
        $page_title   = '字典管理 - 杯型';
        $cups         = getAllCups($pdo);
        $content_view = APP_PATH . '/views/cpsys/cup_management_view.php';
        $page_js      = 'cup_management.js';
        break;

    case 'material_management':
        $page_title   = '字典管理 - 物料';
        $materials    = getAllMaterials($pdo);
        $unit_options = getAllUnits($pdo);
        $content_view = APP_PATH . '/views/cpsys/material_management_view.php';
        $page_js      = 'material_management.js';
        break;

    case 'unit_management':
        $page_title   = '字典管理 - 单位';
        $units        = getAllUnits($pdo);
        $content_view = APP_PATH . '/views/cpsys/unit_management_view.php';
        $page_js      = 'unit_management.js';
        break;

    case 'ice_option_management':
        $page_title   = '字典管理 - 冰量选项';
        $ice_options  = getAllIceOptions($pdo);
        $content_view = APP_PATH . '/views/cpsys/ice_option_management_view.php';
        $page_js      = 'ice_option_management.js';
        break;

    case 'sweetness_option_management':
        $page_title   = '字典管理 - 甜度选项';
        $sweetness_options = getAllSweetnessOptions($pdo);
        $content_view = APP_PATH . '/views/cpsys/sweetness_option_management_view.php';
        $page_js      = 'sweetness_option_management.js';
        break;

    // ================== [GEMINI SYNTAX FIX START] ==================
    // 修复了 `case 'product_status_management'` 之前缺少的 `break;`
    case 'product_status_management':
        $page_title   = '字典管理 - 产品状态';
        $statuses     = getAllStatuses($pdo);
        $content_view = APP_PATH . '/views/cpsys/product_status_management_view.php';
        $page_js      = 'product_status_management.js';
        break;
    // ================== [GEMINI SYNTAX FIX END] ==================

    case 'user_management':
        $page_title   = '系统设置 - 用户管理';
        $users        = getAllUsers($pdo);
        $roles        = getAllRoles($pdo);
        $content_view = APP_PATH . '/views/cpsys/user_management_view.php';
        $page_js      = 'user_management.js';
        break;

    case 'store_management':
        $page_title   = '系统设置 - 门店管理';
        $stores       = getAllStores($pdo);
        $content_view = APP_PATH . '/views/cpsys/store_management_view.php';
        $page_js      = 'store_management.js';
        break;

    case 'kds_user_management':
        $page_title   = 'KDS 账户管理';
        $store_id     = filter_input(INPUT_GET, 'store_id', FILTER_VALIDATE_INT);
        if (!$store_id) { die("无效的门店ID。"); }
        $store_data   = getStoreById($pdo, $store_id);
        if (!$store_data) { die("未找到指定的门店。"); }
        if (!function_exists('getAllKdsUsersByStoreId')) {
            function getAllKdsUsersByStoreId(PDO $pdo, int $store_id): array {
                try { $s=$pdo->prepare("SELECT id, username, display_name, role, is_active, last_login_at FROM kds_users WHERE store_id=? AND deleted_at IS NULL ORDER BY id ASC"); $s->execute([$store_id]); return $s->fetchAll(PDO::FETCH_ASSOC) ?: []; }
                catch (Throwable $e) { return []; }
            }
        }
        $kds_users    = getAllKdsUsersByStoreId($pdo, $store_id);
        $content_view = APP_PATH . '/views/cpsys/kds_user_management_view.php';
        $page_js      = 'kds_user_management.js';
        break;

    case 'profile':
        $page_title   = '个人资料';
        $current_user = getUserById($pdo, (int)($_SESSION['user_id'] ?? 0));
        $content_view = APP_PATH . '/views/cpsys/profile_view.php';
        $page_js      = 'profile.js';
        break;

    case 'pos_eod_reports':
        $page_title   = 'POS 管理 - 日结报告';
        $eod_reports  = getAllEodReports($pdo);
        $content_view = APP_PATH . '/views/cpsys/pos_eod_reports_view.php';
        break;

    case 'pos_shift_review':
        $page_title       = 'POS 管理 - 异常班次复核';
        $pending_reviews  = getPendingShiftReviews($pdo);
        $content_view     = APP_PATH . '/views/cpsys/pos_shift_review_view.php';
        $page_js          = 'pos_shift_review.js';
        break;

    case 'access_denied':
        $page_title   = '访问被拒绝';
        $content_view = APP_PATH . '/views/cpsys/access_denied_view.php';
        break;

    default:
        http_response_code(404);
        $page_title   = '页面未找到';
        $content_view = APP_PATH . '/views/cpsys/404_view.php';
}

/* ---------------- Render with robust fallback ---------------- */
if (!isset($content_view) || !is_file($content_view)) {
    $error_details = 'Content view file not found at path: ' . ($content_view ?? 'N/A');

    // Try fallbacks that most projects都有
    $fallbacks = [
        APP_PATH . '/views/cpsys/404_view.php',
        APP_PATH . '/views/cpsys/dashboard_view.php',
    ];
    $found = null;
    foreach ($fallbacks as $f) {
        if (is_file($f)) { $found = $f; break; }
    }

    if ($found) {
        $page_title   = '错误';
        $content_view = $found; // 交给现有布局渲染
        include APP_PATH . '/views/cpsys/layouts/main.php';
    } else {
        // 最终兜底：直接输出内联 HTML，不再依赖任何视图文件
        ?><!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><title>错误</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>body{background:#111;color:#eee;font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif} .wrap{max-width:960px;margin:6rem auto;padding:2rem;border:1px solid #333;border-radius:12px;background:#1b1b1b} .mono{font-family:ui-monospace,SFMono-Regular,Consolas,Monaco,monospace;color:#f88}</style>
        </head><body><div class="wrap"><h2>错误</h2><p>无法找到内容视图文件。</p><p class="mono"><?php echo htmlspecialchars($error_details, ENT_QUOTES, 'UTF-8'); ?></p></div></body></html><?php
    }
    exit;
}

// 正常渲染
include APP_PATH . '/views/cpsys/layouts/main.php';