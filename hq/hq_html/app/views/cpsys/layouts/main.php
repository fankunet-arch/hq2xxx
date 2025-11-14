<?php
/**
 * Toptea HQ - cpsys
 * Main Layout File
 * Engineer: Gemini | Date: 2025-11-08
 * Revision: 1.20.1 (Badge Bubbling)
 *
 * [GEMINI L3 MENU]:
 * 1. Added L2 page groups: $posMenuPages, $posOpsPages, $posMemberPages.
 * 2. Updated $posPages to be the union of these groups.
 * 3. Rebuilt the HTML for the #pos-submenu to support L3 navigation.
 *
 * [GEMINI BADGE BUBBLE FIX V1.20.1]:
 * 1. Added d-flex layout and notification badge to L1 "POS 管理" link.
 * 2. Added d-flex layout and notification badge to L2 "运营与报表" link.
 *
 * [GEMINI DASHBOARD V1.0]:
 * 1. Added Chart.js CDN to <head> for dashboard charts.
 *
 * [R-Final] Integrated Seasons Pass (BMS/RMS) menu items.
 */
$page_title = $page_title ?? 'TopTea HQ';
$page = $_GET['page'] ?? 'dashboard';

// [GEMINI GHOST_SHIFT_FIX] Get pending count for badge
// $pending_shift_reviews_count 变量由 index.php 传入
$pending_shift_reviews_count = $pending_shift_reviews_count ?? 0;

// [R-Final] 更新菜单组
$rmsPages = [
    'rms_product_management', 'rms_global_rules', 'material_management', 
    'cup_management', 'ice_option_management', 'sweetness_option_management', 
    'product_status_management', 'unit_management'
];

$posMenuPages = [
    'pos_menu_management', 'pos_variants_management', 'pos_category_management', 
    'pos_addon_management', 'product_availability', 'pos_tag_management' // R2.1
];

$posOpsPages = [
    'pos_invoice_list', 'pos_invoice_detail', 'pos_eod_reports', 
    'pos_shift_review'
];

$posMemberPages = [
    'pos_promotion_management', 'pos_member_level_management', 'pos_member_management', 
    'pos_member_settings', 'pos_point_redemption_rules'
];

// [R-Final] 新增次卡组
$seasonsPassPages = [
    'pos_seasons_pass_dashboard', // B3
    'pos_topup_orders', // B1
    'pos_redemptions_view' // B2
];

$posPages = array_merge($posMenuPages, $posOpsPages, $posMemberPages, $seasonsPassPages);

$stockPages = [
    'warehouse_stock_management', 'stock_allocation', 'store_stock_view', 
    'expiry_management'
];

$systemPages = [
    'user_management', 'store_management', 'kds_user_management', 
    'pos_print_template_management', 'pos_print_template_variables', 
    'sif_declaration', 'kds_sop_rules'
];
?>
<!DOCTYPE html>
<html lang="zh-CN" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    </head>
<body>
    <div class="d-flex">
        <nav class="sidebar min-vh-100 p-3">
            <div class="sidebar-header mb-4"><h4 class="text-white">TopTea HQ</h4></div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page === 'dashboard') ? 'active' : ''; ?>" href="index.php?page=dashboard">
                        <i class="bi bi-speedometer2 me-2"></i>仪表盘
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link collapsed <?php echo (in_array($page, $rmsPages)) ? 'active' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#rms-submenu" aria-expanded="<?php echo (in_array($page, $rmsPages)) ? 'true' : 'false'; ?>">
                        <i class="bi bi-cup-straw me-2"></i>配方管理 (RMS)
                    </a>
                    <div class="collapse <?php echo (in_array($page, $rmsPages)) ? 'show' : ''; ?>" id="rms-submenu">
                        <ul class="nav flex-column ps-4">
                            <?php if (check_role(ROLE_PRODUCT_MANAGER)): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($page === 'rms_product_management') ? 'active' : ''; ?>" href="index.php?page=rms_product_management">产品配方 (L1/L3)</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($page === 'rms_global_rules') ? 'active' : ''; ?>" href="index.php?page=rms_global_rules">全局规则 (L2)</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($page === 'material_management') ? 'active' : ''; ?>" href="index.php?page=material_management">物料管理</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($page === 'cup_management') ? 'active' : ''; ?>" href="index.php?page=cup_management">杯型管理</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($page === 'ice_option_management') ? 'active' : ''; ?>" href="index.php?page=ice_option_management">冰量选项</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($page === 'sweetness_option_management') ? 'active' : ''; ?>" href="index.php?page=sweetness_option_management">甜度选项</a>
                            </li>
                             <li class="nav-item">
                                <a class="nav-link <?php echo ($page === 'product_status_management') ? 'active' : ''; ?>" href="index.php?page=product_status_management">产品状态</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($page === 'unit_management') ? 'active' : ''; ?>" href="index.php?page=unit_management">单位管理</a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </li>

                <li class="nav-item">
                    <a class="nav-link collapsed <?php echo (in_array($page, $stockPages)) ? 'active' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#stock-submenu" aria-expanded="<?php echo (in_array($page, $stockPages)) ? 'true' : 'false'; ?>"><i class="bi bi-box-seam me-2"></i>库存与效期</a>
                    <div class="collapse <?php echo (in_array($page, $stockPages)) ? 'show' : ''; ?>" id="stock-submenu">
                        <ul class="nav flex-column ps-4">
                            <?php if (check_role(ROLE_ADMIN)): ?>
                            <li class="nav-item"><a class="nav-link <?php echo ($page === 'warehouse_stock_management') ? 'active' : ''; ?>" href="index.php?page=warehouse_stock_management">总仓库存</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo ($page === 'store_stock_view') ? 'active' : ''; ?>" href="index.php?page=store_stock_view">门店库存</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo ($page === 'stock_allocation') ? 'active' : ''; ?>" href="index.php?page=stock_allocation">库存调拨</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo ($page === 'expiry_management') ? 'active' : ''; ?>" href="index.php?page=expiry_management">效期管理</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </li>
                
                <?php if (check_role(ROLE_PRODUCT_MANAGER)): // 只要有产品经理权限就能看到 POS 组 ?>
                    <li class="nav-item">
                        <a class="nav-link collapsed <?php echo (in_array($page, $posPages)) ? 'active' : ''; ?> d-flex justify-content-between align-items-center" href="#" data-bs-toggle="collapse" data-bs-target="#pos-submenu" aria-expanded="<?php echo (in_array($page, $posPages)) ? 'true' : 'false'; ?>">
                            <span><i class="bi bi-display me-2"></i>POS 管理</span>
                            <?php if ($pending_shift_reviews_count > 0 && check_role(ROLE_ADMIN)): ?>
                                <span class="badge text-bg-danger rounded-pill"><?php echo $pending_shift_reviews_count; ?></span>
                            <?php endif; ?>
                        </a>
                        
                        <div class="collapse <?php echo (in_array($page, $posPages)) ? 'show' : ''; ?>" id="pos-submenu">
                            <ul class="nav flex-column ps-4">
                                
                                <li class="nav-item">
                                    <a class="nav-link collapsed <?php echo (in_array($page, $posMenuPages)) ? 'active-l2-group' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#pos-menu-submenu" aria-expanded="<?php echo (in_array($page, $posMenuPages)) ? 'true' : 'false'; ?>">
                                        菜单与商品
                                    </a>
                                    <div class="collapse l3-nav <?php echo (in_array($page, $posMenuPages)) ? 'show' : ''; ?>" id="pos-menu-submenu">
                                        <ul class="nav flex-column ps-2">
                                            <li class="nav-item"><a class="nav-link <?php echo (in_array($page, ['pos_menu_management', 'pos_variants_management'])) ? 'active' : ''; ?>" href="index.php?page=pos_menu_management">菜单管理</a></li>
                                            <li class="nav-item"><a class="nav-link <?php echo ($page === 'pos_category_management') ? 'active' : ''; ?>" href="index.php?page=pos_category_management">POS分类管理</a></li>
                                            <li class="nav-item"><a class="nav-link <?php echo ($page === 'pos_addon_management') ? 'active' : ''; ?>" href="index.php?page=pos_addon_management">加料管理</a></li>
                                            <li class="nav-item"><a class="nav-link <?php echo ($page === 'pos_tag_management') ? 'active' : ''; ?>" href="index.php?page=pos_tag_management">标签管理 (次卡)</a></li>
                                            <li class="nav-item"><a class="nav-link <?php echo ($page === 'product_availability') ? 'active' : ''; ?>" href="index.php?page=product_availability">物料清单与上架</a></li>
                                        </ul>
                                    </div>
                                </li>
                                
                                <?php if (check_role(ROLE_ADMIN)): // 运营和会员需要 Admin 权限 ?>
                                <li class="nav-item">
                                    <a class="nav-link collapsed <?php echo (in_array($page, $posOpsPages)) ? 'active-l2-group' : ''; ?> d-flex justify-content-between align-items-center" href="#" data-bs-toggle="collapse" data-bs-target="#pos-ops-submenu" aria-expanded="<?php echo (in_array($page, $posOpsPages)) ? 'true' : 'false'; ?>">
                                        <span>运营与报表</span>
                                        <?php if ($pending_shift_reviews_count > 0): ?>
                                            <span class="badge text-bg-danger rounded-pill"><?php echo $pending_shift_reviews_count; ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <div class="collapse l3-nav <?php echo (in_array($page, $posOpsPages)) ? 'show' : ''; ?>" id="pos-ops-submenu">
                                        <ul class="nav flex-column ps-2">
                                            <li class="nav-item"><a class="nav-link <?php echo (in_array($page, ['pos_invoice_list', 'pos_invoice_detail'])) ? 'active' : ''; ?>" href="index.php?page=pos_invoice_list">票据查询</a></li>
                                            <li class="nav-item"><a class="nav-link <?php echo ($page === 'pos_eod_reports') ? 'active' : ''; ?>" href="index.php?page=pos_eod_reports">日结报告</a></li>
                                            <li class="nav-item">
                                                <a class="nav-link d-flex justify-content-between align-items-center <?php echo ($page === 'pos_shift_review') ? 'active' : ''; ?>" href="index.php?page=pos_shift_review">
                                                    异常班次复核
                                                    <?php if ($pending_shift_reviews_count > 0): ?>
                                                        <span class="badge text-bg-danger rounded-pill"><?php echo $pending_shift_reviews_count; ?></span>
                                                    <?php endif; ?>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </li>

                                <li class="nav-item">
                                    <a class="nav-link collapsed <?php echo (in_array($page, $posMemberPages)) ? 'active-l2-group' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#pos-member-submenu" aria-expanded="<?php echo (in_array($page, $posMemberPages)) ? 'true' : 'false'; ?>">
                                        会员与营销 (CRM)
                                    </a>
                                    <div class="collapse l3-nav <?php echo (in_array($page, $posMemberPages)) ? 'show' : ''; ?>" id="pos-member-submenu">
                                        <ul class="nav flex-column ps-2">
                                            <li class="nav-item"><a class="nav-link <?php echo ($page === 'pos_promotion_management') ? 'active' : ''; ?>" href="index.php?page=pos_promotion_management">营销活动管理</a></li>
                                            <li class="nav-item"><a class="nav-link <?php echo ($page === 'pos_member_management') ? 'active' : ''; ?>" href="index.php?page=pos_member_management">会员列表管理</a></li>
                                            <li class="nav-item"><a class="nav-link <?php echo ($page === 'pos_member_level_management') ? 'active' : ''; ?>" href="index.php?page=pos_member_level_management">会员等级管理</a></li>
                                            <li class="nav-item"><a class="nav-link <?php echo ($page === 'pos_member_settings') ? 'active' : ''; ?>" href="index.php?page=pos_member_settings">会员积分设置</a></li>
                                            <li class="nav-item"><a class="nav-link <?php echo ($page === 'pos_point_redemption_rules') ? 'active' : ''; ?>" href="index.php?page=pos_point_redemption_rules">积分兑换规则</a></li>
                                        </ul>
                                    </div>
                                </li>
                                
                                <li class="nav-item">
                                    <a class="nav-link collapsed <?php echo (in_array($page, $seasonsPassPages)) ? 'active-l2-group' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#pos-pass-submenu" aria-expanded="<?php echo (in_array($page, $seasonsPassPages)) ? 'true' : 'false'; ?>">
                                        会员次卡 (Pass)
                                    </a>
                                    <div class="collapse l3-nav <?php echo (in_array($page, $seasonsPassPages)) ? 'show' : ''; ?>" id="pos-pass-submenu">
                                        <ul class="nav flex-column ps-2">
                                            <li class="nav-item"><a class="nav-link <?php echo ($page === 'pos_seasons_pass_dashboard') ? 'active' : ''; ?>" href="index.php?page=pos_seasons_pass_dashboard">次卡数据看板 (B3)</a></li>
                                            <li class="nav-item"><a class="nav-link <?php echo ($page === 'pos_topup_orders') ? 'active' : ''; ?>" href="index.php?page=pos_topup_orders">售卡(VR)审核 (B1)</a></li>
                                            <li class="nav-item"><a class="nav-link <?php echo ($page === 'pos_redemptions_view') ? 'active' : ''; ?>" href="index.php?page=pos_redemptions_view">核销(TP)查询 (B2)</a></li>
                                        </ul>
                                    </div>
                                </li>
                                <?php endif; ?>

                            </ul>
                        </div>
                    </li>
                <?php endif; ?>
                
                <?php if (check_role(ROLE_SUPER_ADMIN)): // 只有 Super Admin 能看系统设置 ?>
                    <li class="nav-item">
                        <a class="nav-link collapsed <?php echo (in_array($page, $systemPages)) ? 'active' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#system-submenu" aria-expanded="<?php echo (in_array($page, $systemPages)) ? 'true' : 'false'; ?>"><i class="bi bi-gear me-2"></i>系统设置</a>
                        <div class="collapse <?php echo (in_array($page, $systemPages)) ? 'show' : ''; ?>" id="system-submenu">
                             <ul class="nav flex-column ps-4">
                                <li class="nav-item"><a class="nav-link <?php echo ($page === 'user_management') ? 'active' : ''; ?>" href="index.php?page=user_management">HQ 账户管理</a></li>
                                <li class="nav-item"><a class="nav-link <?php echo ($page === 'store_management' || $page === 'kds_user_management') ? 'active' : ''; ?>" href="index.php?page=store_management">门店管理</a></li>
                                <li class="nav-item"><a class="nav-link <?php echo ($page === 'kds_sop_rules') ? 'active' : ''; ?>" href="index.php?page=kds_sop_rules">KDS SOP 解析规则</a></li>
                                <li class="nav-item"><a class="nav-link <?php echo ($page === 'pos_print_template_management') ? 'active' : ''; ?>" href="index.php?page=pos_print_template_management">打印模板管理</a></li>
                                <li class="nav-item"><a class="nav-link <?php echo ($page === 'pos_print_template_variables') ? 'active' : ''; ?>" href="index.php?page=pos_print_template_variables">模板可用变量</a></li>
                                <li class="nav-item"><a class="nav-link <?php echo ($page === 'sif_declaration') ? 'active' : ''; ?>" href="index.php?page=sif_declaration">合规性声明 (SIF)</a></li>
                            </ul>
                        </div>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        
        <div class="main-content flex-grow-1 p-4">
            <header class="d-flex justify-content-between align-items-center mb-4">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="index.php?page=dashboard">后台管理</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></li>
                    </ol>
                </nav>
                <div class="dropdown">
                    <button class="btn btn-secondary dropdown-toggle" type="button" id="userMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-2"></i> <?php echo htmlspecialchars($_SESSION['display_name'] ?? 'User'); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuButton">
                        <li><a class="dropdown-item" href="index.php?page=profile">个人资料</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">退出登录</a></li>
                    </ul>
                </div>
            </header>
            <main>
                <?php
                    // [R-Final] 检查和提取 $data 数组
                    if (isset($data) && is_array($data)) {
                        extract($data);
                    }
                
                    if (isset($view_path) && file_exists($view_path)) {
                        include $view_path;
                    } else {
                        $error_msg = 'Error: Content view file not found';
                        if (isset($view_path)) {
                             $error_msg .= ' at path: ' . htmlspecialchars($view_path);
                        } else {
                             $error_msg .= ' (path variable is empty).';
                        }
                         echo '<div class="alert alert-danger">' . $error_msg . '</div>';
                    }
                ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    
    <?php if (isset($page_js) && $page_js): ?>
		<script src="js/<?php echo $page_js; ?>?ver=<?php echo time(); ?>"></script>
    <?php endif; ?>
</body>
</html>