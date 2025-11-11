<?php
// 定义物料类型映射表
$material_type_map = [
    'RAW' => '原料',
    'SEMI_FINISHED' => '半成品',
    'PRODUCT' => '成品/直销品',
    'CONSUMABLE' => '耗材'
];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex gap-2">
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="stock-search-input" class="form-control" placeholder="按物料名称搜索...">
        </div>
        <div class="input-group" style="width: 200px;">
            <select class="form-select" id="stock-type-filter">
                <option value="ALL" selected>-- 所有类型 --</option>
                <?php foreach ($material_type_map as $key => $value): ?>
                    <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        总仓库存列表
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>物料名称</th>
                        <th>类型</th>
                        <th>当前库存</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody id="stock-table-body">
                    <?php if (empty($stock_items)): ?>
                        <tr>
                            <td colspan="4" class="text-center">暂无物料，请先在字典中添加。</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($stock_items as $item): ?>
                            <tr data-type="<?php echo htmlspecialchars($item['material_type']); ?>" data-name="<?php echo htmlspecialchars(strtolower($item['material_name'])); ?>">
                                <td><strong><?php echo htmlspecialchars($item['material_name']); ?></strong></td>
                                <td>
                                    <span class="badge text-bg-info"><?php echo $material_type_map[$item['material_type']] ?? '未知'; ?></span>
                                </td>
                                <td>
                                    <?php 
                                        $quantity_formatted = number_format($item['quantity'], 2, '.', '');
                                        if ($item['quantity'] < 0) {
                                            echo '<span class="text-danger fw-bold">' . htmlspecialchars($quantity_formatted) . '</span>';
                                        } else {
                                            echo htmlspecialchars($quantity_formatted);
                                        }
                                        echo ' ' . htmlspecialchars($item['base_unit_name']);
                                    ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary add-stock-btn" 
                                            data-material-id="<?php echo $item['material_id']; ?>"
                                            data-material-name="<?php echo htmlspecialchars($item['material_name']); ?>"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#add-stock-modal">
                                        入库
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <tr id="no-matching-row" style="display: none;"><td colspan="4" class="text-center">没有匹配的物料。</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="add-stock-modal" tabindex="-1" aria-labelledby="modal-label" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5" id="modal-label">物料入库</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="add-stock-form">
            <input type="hidden" id="material-id-input" name="material_id">
            <div class="mb-3">
                <label class="form-label">物料名称</label>
                <input type="text" class="form-control" id="material-name-display" readonly disabled>
            </div>
            <div class="mb-3">
                <label for="quantity-input" class="form-label">入库数量 <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="quantity-input" name="quantity" step="0.01" required>
            </div>
            <div class="mb-3">
                <label for="unit-id-select" class="form-label">单位 <span class="text-danger">*</span></label>
                <select class="form-select" id="unit-id-select" name="unit_id" required>
                    </select>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
        <button type="submit" class="btn btn-primary" form="add-stock-form">确认入库</button>
      </div>
    </div>
  </div>
</div>