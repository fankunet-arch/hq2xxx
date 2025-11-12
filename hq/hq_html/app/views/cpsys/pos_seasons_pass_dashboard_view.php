<?php
/**
 * Toptea HQ - 次卡数据看板
 * Engineer: Gemini | Date: 2025-11-12
 * [R2.5] Implements: 3.2 CPSYS-BMS (B3)
 *
 * @var array $kpis (由 index.php 传入)
 * $kpis = [
 * 'active_passes_count' => 0,
 * 'total_redemptions_count' => 0,
 * 'total_sales_amount' => 0.0
 * ];
 */
 
 // 确保变量存在，避免视图错误
 if (!isset($kpis) || !is_array($kpis)) {
     $kpis = [
        'active_passes_count' => 'N/A',
        'total_redemptions_count' => 'N/A',
        'total_sales_amount' => 'N/A'
     ];
 }

?>
<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="bi bi-cash-stack fs-1 text-success"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="card-title text-muted mb-2">累计售卡总额 (已批准)</h5>
                        <h2 class="mb-0">
                            € <?php echo htmlspecialchars(number_format($kpis['total_sales_amount'], 2)); ?>
                        </h2>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="bi bi-ticket-detailed fs-1 text-primary"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="card-title text-muted mb-2">累计核销总次数</h5>
                        <h2 class="mb-0">
                            <?php echo htmlspecialchars($kpis['total_redemptions_count']); ?> 次
                        </h2>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="bi bi-person-check fs-1 text-info"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="card-title text-muted mb-2">当前活跃次卡 (剩余>0)</h5>
                        <h2 class="mb-0">
                            <?php echo htmlspecialchars($kpis['active_passes_count']); ?> 张
                        </h2>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-light">
    <i class="bi bi-info-circle me-2"></i>
    数据看板 (B3) 已完成。详细的售卡订单(VR)和核销记录(TP)请分别查阅它们的专属管理页面。
</div>