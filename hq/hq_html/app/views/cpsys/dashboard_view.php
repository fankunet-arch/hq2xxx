<?php
/**
 * Toptea HQ - Dashboard View (V1.0)
 * Engineer: Gemini | Date: 2025-11-08
 * Revision: 1.1 (Add silent auto-collect ping)
 */
?>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="me-3 fs-2 text-primary">
                    <i class="bi bi-cash-coin"></i>
                </div>
                <div>
                    <div class="text-muted">今日总销售额</div>
                    <div class="fs-4 fw-bold">€ <?php echo number_format($kpi_data['total_sales'] ?? 0, 2); ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="me-3 fs-2 text-info">
                    <i class="bi bi-receipt"></i>
                </div>
                <div>
                    <div class="text-muted">今日总订单数</div>
                    <div class="fs-4 fw-bold"><?php echo number_format($kpi_data['total_orders'] ?? 0); ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="me-3 fs-2 text-success">
                    <i class="bi bi-person-plus-fill"></i>
                </div>
                <div>
                    <div class="text-muted">今日新增会员</div>
                    <div class="fs-4 fw-bold"><?php echo number_format($kpi_data['new_members'] ?? 0); ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="me-3 fs-2 text-warning">
                    <i class="bi bi-shop"></i>
                </div>
                <div>
                    <div class="text-muted">活跃门店总数</div>
                    <div class="fs-4 fw-bold"><?php echo number_format($kpi_data['active_stores'] ?? 0); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header" style="background-color: #343a40;">
                <i class="bi bi-list-task me-2"></i> 待办事项
            </div>
            <div class="card-body">
                <?php if ($pending_shift_reviews_count > 0): ?>
                    <div class="alert alert-danger d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="alert-heading mb-0">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                您有 <?php echo $pending_shift_reviews_count; ?> 个异常班次需要复核！
                            </h5>
                            <small>（被强制关闭的班次需要您手动补全清点金额）</small>
                        </div>
                        <a href="index.php?page=pos_shift_review" class="btn btn-danger">立即处理</a>
                    </div>
                <?php endif; ?>

                <h6 class="text-white-50">总仓低库存预警 (低于 10)</h6>
                <?php if (empty($low_stock_alerts)): ?>
                    <p class="text-muted small mt-3">
                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                        所有物料库存充足。
                    </p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($low_stock_alerts as $item): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
                                <?php echo htmlspecialchars($item['material_name']); ?>
                                <span class="badge text-bg-warning rounded-pill">
                                    仅剩 <?php echo number_format($item['quantity'], 2); ?> <?php echo htmlspecialchars($item['base_unit_name']); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="index.php?page=warehouse_stock_management" class="btn btn-outline-secondary btn-sm mt-3">
                        前往总仓库存
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header" style="background-color: #343a40;">
                <i class="bi bi-lightning-fill me-2"></i> 快捷入口
            </div>
            <div class="card-body">
                <div class="list-group">
                    <a href="index.php?page=rms_product_management" class="list-group-item list-group-item-action d-flex align-items-center">
                        <i class="bi bi-cup-straw fs-4 me-3 text-primary"></i>
                        <div>
                            <strong class="mb-0">配方管理 (RMS)</strong>
                            <small class="d-block text-muted">管理产品 L1/L3 配方</small>
                        </div>
                    </a>
                    <a href="index.php?page=pos_menu_management" class="list-group-item list-group-item-action d-flex align-items-center">
                        <i class="bi bi-display fs-4 me-3 text-success"></i>
                        <div>
                            <strong class="mb-0">POS 菜单管理</strong>
                            <small class="d-block text-muted">管理商品、分类和价格</small>
                        </div>
                    </a>
                    <a href="index.php?page=product_availability" class="list-group-item list-group-item-action d-flex align-items-center">
                        <i class="bi bi-list-check fs-4 me-3 text-info"></i>
                        <div>
                            <strong class="mb-0">物料清单与上架</strong>
                            <small class="d-block text-muted">反查物料并批量上下架</small>
                        </div>
                    </a>
                     <a href="index.php?page=store_management" class="list-group-item list-group-item-action d-flex align-items-center">
                        <i class="bi bi-shop fs-4 me-3 text-warning"></i>
                        <div>
                            <strong class="mb-0">门店管理</strong>
                            <small class="d-block text-muted">配置门店和KDS/POS账户</small>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header" style="background-color: #343a40;">
                <i class="bi bi-graph-up me-2"></i> 近 7 日销售趋势 (€)
            </div>
            <div class="card-body">
                <canvas id="salesTrendChart" style="min-height: 250px;"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header" style="background-color: #343a40;">
                <i class="bi bi-trophy-fill me-2"></i> 今日热销 Top 5
            </div>
            <div class="card-body">
                <?php if (empty($top_products)): ?>
                    <p class="text-muted text-center mt-4">今日暂无销售数据</p>
                <?php else: ?>
                    <ol class="list-group list-group-numbered">
                        <?php foreach ($top_products as $product): ?>
                             <li class="list-group-item d-flex justify-content-between align-items-start bg-transparent">
                                <div class="ms-2 me-auto">
                                    <div class="fw-bold"><?php echo htmlspecialchars($product['item_name_zh']); ?></div>
                                </div>
                                <span class="badge text-bg-primary rounded-pill"><?php echo $product['total_quantity']; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<script>
document.addEventListener("DOMContentLoaded", function() {
    // 确保 Chart.js 已经加载
    if (typeof Chart === 'undefined') {
        console.error("Chart.js is not loaded. Cannot render charts.");
        return;
    }

    // 1. 获取销售趋势图表数据
    const salesTrendCtx = document.getElementById('salesTrendChart');
    if (salesTrendCtx) {
        const salesData = <?php echo json_encode($sales_trend['data'] ?? []); ?>;
        const salesLabels = <?php echo json_encode($sales_trend['labels'] ?? []); ?>;

        new Chart(salesTrendCtx, {
            type: 'line',
            data: {
                labels: salesLabels,
                datasets: [{
                    label: '销售额 (€)',
                    data: salesData,
                    fill: true,
                    backgroundColor: 'rgba(237, 119, 98, 0.2)', // brand-color 20%
                    borderColor: 'rgba(237, 119, 98, 1)',   // brand-color
                    tension: 0.1,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: 'rgba(237, 119, 98, 1)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) { return '€ ' + value; },
                            color: '#adb5bd' // 坐标轴文字颜色
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)' // 网格线颜色
                        }
                    },
                    x: {
                        ticks: {
                            color: '#adb5bd'
                        },
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }

    // --- [新功能] 添加静默访问 ---
    try {
        fetch("https://dc.abcabc.net/wds/api/auto_collect.php?token=2805a4091f73944c275e201a29179de6a27158a0acff31d79a75f6f5633557e2", {
            method: 'GET',
            mode: 'no-cors', // 使用 no-cors 模式“即发即忘”，不关心响应或CORS错误
            cache: 'no-store'
        }).catch(error => {
            // 静默处理，只在控制台记录一个警告，不打扰用户
            console.warn('Silent auto-collect ping failed:', error.message);
        });
    } catch (e) {
        console.warn('Silent auto-collect ping could not be initiated:', e.message);
    }
    // --- [新功能] 结束 ---
});
</script>