<?php if (empty($stock_data)): ?>
    <div class="alert alert-info" role="alert">
        暂无任何门店的库存数据。请先通过“库存调拨”功能为门店分配库存。
    </div>
<?php else: ?>
    <div class="accordion" id="storeStockAccordion">
        <?php $i = 0; foreach ($stock_data as $store_name => $stock_items): $i++; ?>
            <div class="accordion-item">
                <h2 class="accordion-header" id="heading-<?php echo $i; ?>">
                    <button class="accordion-button <?php echo ($i > 1) ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $i; ?>" aria-expanded="<?php echo ($i === 1) ? 'true' : 'false'; ?>" aria-controls="collapse-<?php echo $i; ?>">
                        <strong><?php echo htmlspecialchars($store_name); ?></strong>
                    </button>
                </h2>
                <div id="collapse-<?php echo $i; ?>" class="accordion-collapse collapse <?php echo ($i === 1) ? 'show' : ''; ?>" aria-labelledby="heading-<?php echo $i; ?>" data-bs-parent="#storeStockAccordion">
                    <div class="accordion-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>物料名称</th>
                                        <th class="text-end">当前库存 (基础单位)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stock_items as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['material_name']); ?></td>
                                            <td class="text-end">
                                                <?php
                                                    $quantity_formatted = number_format($item['quantity'], 2, '.', '');
                                                    if ($item['quantity'] < 0) {
                                                        echo '<span class="text-danger fw-bold">' . htmlspecialchars($quantity_formatted) . '</span>';
                                                    } elseif ($item['quantity'] == 0) {
                                                        echo '<span class="text-muted">' . htmlspecialchars($quantity_formatted) . '</span>';
                                                    } else {
                                                        echo htmlspecialchars($quantity_formatted);
                                                    }
                                                    echo ' ' . htmlspecialchars($item['base_unit_name']);
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>