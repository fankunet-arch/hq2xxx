<div class="card">
    <div class="card-header">
        向门店调拨库存
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>门店码</th>
                        <th>门店名称</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stores)): ?>
                        <tr>
                            <td colspan="3" class="text-center">暂无门店数据。</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($stores as $store): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($store['store_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($store['store_name']); ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary allocate-btn" 
                                            data-store-id="<?php echo $store['id']; ?>"
                                            data-store-name="<?php echo htmlspecialchars($store['store_name']); ?>"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#allocation-modal">
                                        调拨
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="allocation-modal" tabindex="-1" aria-labelledby="modal-label" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5" id="modal-label">库存调拨</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="allocation-form">
            <input type="hidden" id="store-id-input" name="store_id">
            <div class="mb-3">
                <label class="form-label">目标门店</label>
                <input type="text" class="form-control" id="store-name-display" readonly disabled>
            </div>
            <div class="mb-3">
                <label for="material-id-select" class="form-label">物料 <span class="text-danger">*</span></label>
                <select class="form-select" id="material-id-select" name="material_id" required>
                    <option value="" selected disabled>-- 请选择物料 --</option>
                    <?php foreach ($materials as $material): ?>
                        <option value="<?php echo $material['id']; ?>"><?php echo htmlspecialchars($material['name_zh']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="quantity-input" class="form-label">调拨数量 <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="quantity-input" name="quantity" step="0.01" required>
            </div>
            <div class="mb-3">
                <label for="unit-id-select" class="form-label">单位 <span class="text-danger">*</span></label>
                <select class="form-select" id="unit-id-select" name="unit_id" required>
                    <option value="" selected disabled>-- 请先选择物料 --</option>
                </select>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
        <button type="submit" class="btn btn-primary" form="allocation-form">确认调拨</button>
      </div>
    </div>
  </div>
</div>