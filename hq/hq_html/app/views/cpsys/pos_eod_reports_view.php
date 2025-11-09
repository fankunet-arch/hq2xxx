<?php
/**
 * Toptea HQ - POS EOD Reports View
 * Engineer: Gemini | Date: 2025-10-27
 *
 * [A2 UTC SYNC]: Using fmt_local() for executed_at.
 */
?>

<div class="card">
    <div class="card-header">门店日结报告</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>报告日期</th>
                        <th>门店</th>
                        <th>净销售额</th>
                        <th>系统现金</th>
                        <th>清点现金</th>
                        <th class="text-center">现金差异</th>
                        <th>执行人</th>
                        <th>执行时间 (Madrid)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($eod_reports)): ?>
                        <tr><td colspan="8" class="text-center">暂无已提交的日结报告。</td></tr>
                    <?php else: ?>
                        <?php foreach ($eod_reports as $report): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($report['report_date']); ?></strong></td>
                                <td><?php echo htmlspecialchars($report['store_name']); ?></td>
                                <td><?php echo number_format($report['system_net_sales'], 2); ?> €</td>
                                <td><?php echo number_format($report['system_cash'], 2); ?> €</td>
                                <td><?php echo number_format($report['counted_cash'], 2); ?> €</td>
                                <td class="text-center">
                                    <?php 
                                        $discrepancy = (float)$report['cash_discrepancy'];
                                        $badge_class = 'text-bg-success';
                                        if ($discrepancy != 0) {
                                            $badge_class = 'text-bg-danger';
                                        }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo number_format($discrepancy, 2); ?> €
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($report['user_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(fmt_local($report['executed_at'], 'Y-m-d H:i')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>