<?php
/**
 * Toptea HQ - POS Invoice Detail View
 * Engineer: Gemini | Date: 2025-10-27 | Revision: 3.3 (Re-enabled Cancel/Correct Buttons)
 *
 * [A2 UTC SYNC]: Using fmt_local() for issued_at.
 */

function renderComplianceData($data) {
    if (empty($data)) {
        echo '<p class="text-muted">无合规性数据。</p>';
        return;
    }
    echo '<table class="table table-bordered table-sm mt-3">';
    foreach ($data as $key => $value) {
        $displayValue = is_array($value) ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : htmlspecialchars($value ?? '');
        $labelClass = (in_array($key, ['hash', 'previous_hash', 'signature'])) ? 'fw-bold text-primary' : '';
        echo '<tr>';
        echo '<td style="width: 20%;" class="' . $labelClass . '">' . htmlspecialchars(strtoupper($key)) . '</td>';
        echo '<td style="width: 80%;"><textarea class="form-control form-control-sm" rows="'.(substr_count($displayValue, "\n") + 2).'" readonly style="font-family: monospace; font-size: 0.8rem; white-space: pre-wrap;">' . $displayValue . '</textarea></td>';
        echo '</tr>';
    }
    echo '</table>';
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="index.php?page=pos_invoice_list" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> 返回票据列表</a>
    <div class="btn-group">
        <?php if ($invoice_data['status'] === 'ISSUED'): ?>
            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#correctInvoiceModal">开具更正票据</button>
            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#cancelInvoiceModal">作废此票据</button>
        <?php elseif ($invoice_data['status'] === 'CANCELLED'): ?>
             <button class="btn btn-secondary" disabled>已作废</button>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header bg-success text-white">基本信息</div>
            <div class="card-body">
                <p><strong>票号:</strong> <?php echo htmlspecialchars($invoice_data['series'] . '-' . $invoice_data['number']); ?></p>
                <p><strong>门店:</strong> <?php echo htmlspecialchars($invoice_data['store_name'] ?? '未知门店'); ?></p>
                <p><strong>收银员:</strong> <?php echo htmlspecialchars($invoice_data['cashier_name'] ?? 'N/A'); ?></p>
                <p><strong>开具时间 (Europe/Madrid):</strong> <?php echo htmlspecialchars(fmt_local($invoice_data['issued_at'], 'Y-m-d H:i:s.u')); // [A2 UTC SYNC] ?></p>
                <p><strong>交易状态:</strong> <span class="badge text-bg-<?php echo ($invoice_data['status'] === 'ISSUED' ? 'success' : 'danger'); ?>"><?php echo ($invoice_data['status'] === 'ISSUED' ? '已开具' : '已作废'); ?></span></p>
                <hr>
                <h5>财务信息</h5>
                <p><strong>税前基数:</strong> <?php echo number_format($invoice_data['taxable_base'], 2); ?> €</p>
                <p><strong>增值税额:</strong> <?php echo number_format($invoice_data['vat_amount'], 2); ?> €</p>
                <p class="fs-4"><strong>最终总额:</strong> <?php echo number_format($invoice_data['final_total'], 2); ?> €</p>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">支付信息</div>
            <div class="card-body">
                <?php $payment_summary = $invoice_data['payment_summary_decoded']; ?>
                <?php if (!empty($payment_summary) && isset($payment_summary['summary'])): ?>
                    <dl class="row">
                        <dt class="col-sm-5">应付总额 (Total)</dt>
                        <dd class="col-sm-7"><?php echo number_format($payment_summary['total'] ?? 0, 2); ?> €</dd>
                        
                        <dt class="col-sm-5">实付总额 (Paid)</dt>
                        <dd class="col-sm-7"><?php echo number_format($payment_summary['paid'] ?? 0, 2); ?> €</dd>

                        <dt class="col-sm-5">找零 (Change)</dt>
                        <dd class="col-sm-7"><?php echo number_format($payment_summary['change'] ?? 0, 2); ?> €</dd>
                    </dl>
                    <hr>
                    <h6 class="mt-3">支付方式明细</h6>
                    <ul class="list-group">
                    <?php foreach ($payment_summary['summary'] as $part): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <?php if ($part['method'] === 'Cash'): ?><i class="bi bi-cash-coin me-2"></i><?php endif; ?>
                                <?php if ($part['method'] === 'Card'): ?><i class="bi bi-credit-card me-2"></i><?php endif; ?>
                                <?php if ($part['method'] === 'Platform'): ?><i class="bi bi-qr-code me-2"></i><?php endif; ?>
                                <?php echo htmlspecialchars($part['method']); ?>
                                <?php if (!empty($part['reference'])): ?>
                                    <code class="ms-2">(<?php echo htmlspecialchars($part['reference']); ?>)</code>
                                <?php endif; ?>
                            </span>
                            <span class="fw-bold"><?php echo number_format($part['amount'], 2); ?> €</span>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php elseif (!empty($payment_summary)): // Fallback for old, simple format ?>
                    <?php foreach ($payment_summary as $method => $amount): ?>
                         <p><strong><?php echo htmlspecialchars(ucfirst($method)); ?>:</strong> <?php echo number_format((float)$amount, 2); ?> €</p>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">无支付记录。</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header" style="background-color: #6f42c1; color: white;">合规与凭证 (<?php echo htmlspecialchars($invoice_data['compliance_system'] ?? 'N/A'); ?>)</div>
            <div class="card-body">
                <h5>合规性数据</h5>
                <?php renderComplianceData($invoice_data['compliance_data_decoded']); ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">商品明细</div>
    <div class="card-body">
        <?php if (!empty($invoice_data['items'])): ?>
        <table class="table table-striped align-middle">
            <thead><tr><th>商品</th><th>规格 / 定制</th><th>数量</th><th>单价(含税)</th><th>总价(含税)</th></tr></thead>
            <tbody>
                <?php foreach ($invoice_data['items'] as $item): ?>
                    <?php $customizations = json_decode($item['customizations'], true); ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                        <td>
                            <?php echo htmlspecialchars($item['variant_name']); ?><br>
                            <small class="text-muted">I:<?php echo htmlspecialchars($customizations['ice'] ?? 'N/A'); ?> | S:<?php echo htmlspecialchars($customizations['sugar'] ?? 'N/A'); ?> | +:<?php echo htmlspecialchars(implode(', ', $customizations['addons'] ?? [])); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                        <td><?php echo number_format($item['unit_price'], 2); ?> €</td>
                        <td><?php echo number_format($item['unit_price'] * $item['quantity'], 2); ?> €</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p class="text-muted">此票据不包含商品明细。</p>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="cancelInvoiceModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h1 class="modal-title fs-5" id="cancelModalLabel">作废票据: <?php echo htmlspecialchars($invoice_data['series'] . '-' . $invoice_data['number']); ?></h1><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <p>请选择或输入作废原因（此操作不可逆）：</p>
        <div class="list-group mb-3">
            <button type="button" class="list-group-item list-group-item-action reason-btn" data-reason-es="Error en la emisión">错误开具</button>
            <button type="button" class="list-group-item list-group-item-action reason-btn" data-reason-es="Pedido duplicado">重复下单</button>
            <button type="button" class="list-group-item list-group-item-action reason-btn" data-reason-es="Cliente cancela el pedido">客人取消</button>
        </div>
        <div class="mb-3">
            <label for="cancellation_reason_text" class="form-label">或输入其他原因 (将以此为准)</label>
            <input type="text" class="form-control" id="cancellation_reason_text" placeholder="原因将以西班牙语记录">
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button type="button" class="btn btn-danger" id="btn-confirm-cancellation" data-invoice-id="<?php echo $invoice_data['id']; ?>">确认作废</button></div>
    </div>
  </div>
</div>

<div class="modal fade" id="correctInvoiceModal" tabindex="-1" aria-labelledby="correctModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h1 class="modal-title fs-5" id="correctModalLabel">开具更正票据 (Factura Rectificativa)</h1><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="alert alert-warning"><strong>原始票号:</strong> <?php echo htmlspecialchars($invoice_data['series'] . '-' . $invoice_data['number']); ?><br><strong>原始总额:</strong> <?php echo number_format($invoice_data['final_total'], 2); ?> €</div>
        <div class="mb-4">
            <h6 class="fw-bold">第一步：选择更正类型</h6>
            <div class="form-check"><input class="form-check-input" type="radio" name="correctionType" id="correctionTypeS" value="S" checked><label class="form-check-label" for="correctionTypeS"><strong>全额替换 (Por Sustitución)</strong> - 用于全单退货。将生成一张与原单金额相反的负数票据。</label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="correctionType" id="correctionTypeI" value="I"><label class="form-check-label" for="correctionTypeI"><strong>部分差额 (Por Diferencias)</strong> - 用于部分退货或价格调整。您需要手动指定最终的正确总额。</label></div>
        </div>
        <div>
            <h6 class="fw-bold">第二步：提供详情与原因</h6>
            <div id="correction-by-difference-section" class="mb-3" style="display: none;">
                 <label for="new_final_total" class="form-label">更正后的最终总额 (€) <span class="text-danger">*</span></label>
                 <input type="number" step="0.01" class="form-control" id="new_final_total" placeholder="例如: 3.50">
                 <div class="form-text">系统将自动计算差额。例如，原单5€，输入3.5€，将生成一张 -1.50€ 的更正票据。</div>
            </div>
            <div class="mb-3">
                <label for="correction_reason_text" class="form-label">更正原因 <span class="text-danger">*</span></label>
                 <div class="list-group list-group-horizontal-sm mb-2">
                    <button type="button" class="list-group-item list-group-item-action correction-reason-btn" data-reason-es="Devolución de producto">商品退货</button>
                    <button type="button" class="list-group-item list-group-item-action correction-reason-btn" data-reason-es="Ajuste de precio">价格调整</button>
                </div>
                <input type="text" class="form-control" id="correction_reason_text" placeholder="或手动输入原因 (西班牙语)">
            </div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button><button type="button" class="btn btn-warning" id="btn-confirm-correction" data-invoice-id="<?php echo $invoice_data['id']; ?>">确认并生成更正票据</button></div>
    </div>
  </div>
</div>