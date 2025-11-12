<?php
/**
 * Toptea HQ - 售卡订单(VR)管理
 * Engineer: Gemini | Date: 2025-11-12
 * [R2.4] Implements: 3.2 CPSYS-BMS (B1)
 */
?>
<div class="card">
    <div class="card-header">
        售卡订单 (Topup Orders) 管理
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="bi bi-info-circle-fill me-2"></i>
            此页面用于审核 POS 端提交的“售卡”订单。审核“通过”后，将为会员激活相应次卡。
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>订单ID</th>
                        <th>状态</th>
                        <th>会员手机</th>
                        <th>次卡方案</th>
                        <th>数量</th>
                        <th>总金额 (€)</th>
                        <th>售卡门店</th>
                        <th>售卡时间 (UTC)</th>
                        <th>凭证</th>
                        <th>审核人</th>
                        <th>审核时间 (UTC)</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topup_orders)): ?>
                        <tr><td colspan="12" class="text-center">暂无售卡订单数据。</td></tr>
                    <?php else: ?>
                        <?php foreach ($topup_orders as $order): ?>
                            <tr>
                                <td><strong>#<?php echo htmlspecialchars($order['order_id']); ?></strong></td>
                                <td>
                                    <?php
                                    $status = htmlspecialchars($order['status']);
                                    $badge_class = 'bg-secondary';
                                    if ($status === 'APPROVED') $badge_class = 'bg-success';
                                    if ($status === 'PENDING') $badge_class = 'bg-warning text-dark';
                                    if ($status === 'REJECTED') $badge_class = 'bg-danger';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo $status; ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($order['member_phone']); ?></td>
                                <td><?php echo htmlspecialchars($order['plan_name']); ?></td>
                                <td><?php echo htmlspecialchars($order['quantity']); ?></td>
                                <td><?php echo htmlspecialchars(number_format($order['amount_total'], 2)); ?></td>
                                <td><?php echo htmlspecialchars($order['store_name']); ?></td>
                                <td><?php echo htmlspecialchars($order['sale_time']); ?></td>
                                <td><?php echo htmlspecialchars($order['voucher_series'] . '-' . $order['voucher_number']); ?></td>
                                <td><?php echo htmlspecialchars($order['reviewer_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($order['reviewed_at'] ?? 'N/A'); ?></td>
                                <td class="text-end">
                                    <?php if ($order['status'] === 'PENDING'): ?>
                                        <button class="btn btn-sm btn-primary review-btn" 
                                                data-id="<?php echo $order['order_id']; ?>" 
                                                data-member="<?php echo htmlspecialchars($order['member_phone']); ?>"
                                                data-plan="<?php echo htmlspecialchars($order['plan_name'] . ' x ' . $order['quantity']); ?>"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#review-modal">
                                            审核
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-secondary" disabled>已处理</button>
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

<div class="modal fade" id="review-modal" tabindex="-1" aria-labelledby="modal-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-label">审核售卡订单</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>您正在审核以下订单：</p>
                <ul>
                    <li><strong>订单 ID:</strong> <span id="modal-order-id"></span></li>
                    <li><strong>会员手机:</strong> <span id="modal-member-phone"></span></li>
                    <li><strong>购买内容:</strong> <span id="modal-plan-name"></span></li>
                </ul>
                <p class="text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><strong>警告：</strong>此操作不可逆转！</p>
                
                <input type="hidden" id="modal-review-order-id-input">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-danger" id="review-reject-btn">
                    <i class="bi bi-x-circle me-2"></i>拒绝
                </button>
                <button type="button" class="btn btn-success" id="review-approve-btn">
                    <i class="bi bi-check-circle me-2"></i>通过 (激活次卡)
                </button>
            </div>
        </div>
    </div>
</div>