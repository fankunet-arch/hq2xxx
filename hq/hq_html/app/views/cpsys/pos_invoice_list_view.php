<div class="card">
    <div class="card-header">票据查询</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>票号</th>
                        <th>开具时间 (Europe/Madrid)</th>
                        <th>所属门店</th>
                        <th>总金额</th>
                        <th>状态</th>
                        <th>合规系统</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr><td colspan="7" class="text-center">暂无票据数据。</td></tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($invoice['series'] . '-' . $invoice['number']); ?></strong></td>
                                <td><?php echo htmlspecialchars(fmt_local($invoice['issued_at'], 'Y-m-d H:i:s')); // [A2 UTC SYNC] ?></td>
                                <td><?php echo htmlspecialchars($invoice['store_name']); ?></td>
                                <td><strong><?php echo number_format($invoice['final_total'], 2); ?> €</strong></td>
                                <td>
                                    <?php if ($invoice['status'] === 'ISSUED'): ?>
                                        <span class="badge text-bg-success">已开具</span>
                                    <?php elseif ($invoice['status'] === 'CANCELLED'): ?>
                                        <span class="badge text-bg-danger">已作废</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary"><?php echo htmlspecialchars($invoice['status']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($invoice['compliance_system'] === 'TICKETBAI'): ?>
                                        <span class="badge text-bg-primary">TicketBAI</span>
                                    <?php elseif ($invoice['compliance_system'] === 'VERIFACTU'): ?>
                                        <span class="badge" style="background-color: #6f42c1; color: white;">Veri*Factu</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="index.php?page=pos_invoice_detail&id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-outline-secondary">详情</a>
                                    <?php if ($invoice['status'] === 'ISSUED'): ?>
                                        <button class="btn btn-sm btn-outline-warning" disabled>更正</button>
                                        <button class="btn btn-sm btn-outline-danger" disabled>作废</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-3 form-text">
            * “作废”与“更正”功能将在后续版本中启用。
        </div>
    </div>
</div>