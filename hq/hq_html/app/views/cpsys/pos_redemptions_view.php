<?php
/**
 * Toptea HQ - 次卡核销(TP)查询
 * Engineer: Gemini | Date: 2025-11-12
 * [R2.5] Implements: 3.2 CPSYS-BMS (B2)
 */
?>
<div class="card">
    <div class="card-header">
        次卡核销 (TP) 查询
    </div>
    <div class="card-body">
        <div class="alert alert-secondary">
            <i class="bi bi-search me-2"></i>
            此页面用于审计所有 POS 端完成的次卡核销记录。
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>批次ID</th>
                        <th>核销时间 (UTC)</th>
                        <th>会员手机</th>
                        <th>次卡方案</th>
                        <th>核销门店</th>
                        <th>核销次数</th>
                        <th>额外收费 (€)</th>
                        <th>关联加价发票</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($redemption_batches)): ?>
                        <tr><td colspan="8" class="text-center">暂无核销记录。</td></tr>
                    <?php else: ?>
                        <?php foreach ($redemption_batches as $batch): ?>
                            <tr>
                                <td><strong>#<?php echo htmlspecialchars($batch['batch_id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($batch['redeemed_at']); ?></td>
                                <td><?php echo htmlspecialchars($batch['member_phone']); ?></td>
                                <td><?php echo htmlspecialchars($batch['plan_name']); ?></td>
                                <td><?php echo htmlspecialchars($batch['store_name']); ?></td>
                                <td><strong><?php echo htmlspecialchars($batch['uses_redeemed']); ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars(number_format($batch['extra_charge_total'], 2)); ?>
                                </td>
                                <td>
                                    <?php if ($batch['invoice_series'] && $batch['invoice_number']): ?>
                                        <?php echo htmlspecialchars($batch['invoice_series'] . '-' . $batch['invoice_number']); ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>