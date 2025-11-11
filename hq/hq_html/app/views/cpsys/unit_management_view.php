<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-primary" id="create-unit-btn" data-bs-toggle="offcanvas" data-bs-target="#unit-drawer">
        <i class="bi bi-plus-circle me-2"></i>创建新单位
    </button>
</div>

<div class="card">
    <div class="card-header">单位列表</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>自定义编号</th>
                        <th>单位名称 (中)</th>
                        <th>单位名称 (西)</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($units)): ?>
                        <tr><td colspan="4" class="text-center">暂无单位数据。</td></tr>
                    <?php else: ?>
                        <?php foreach ($units as $unit): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($unit['unit_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($unit['name_zh']); ?></td>
                                <td><?php echo htmlspecialchars($unit['name_es']); ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary edit-unit-btn" data-unit-id="<?php echo $unit['id']; ?>" data-bs-toggle="offcanvas" data-bs-target="#unit-drawer">编辑</button>
                                    <button class="btn btn-sm btn-outline-danger delete-unit-btn" data-unit-id="<?php echo $unit['id']; ?>" data-unit-name="<?php echo htmlspecialchars($unit['name_zh']); ?>">删除</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="unit-drawer" aria-labelledby="drawer-label">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="drawer-label">创建/编辑单位</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form id="unit-form">
            <input type="hidden" id="unit-id" name="unit_id">
            <div class="mb-3">
                <label for="unit-code" class="form-label">自定义编号 <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="unit-code" name="unit_code" required>
                <div class="form-text">遵循 KDS 编号规则 (1-2位数字)。</div>
            </div>
            <div class="mb-3">
                <label for="unit-name-zh" class="form-label">单位名称 (中) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="unit-name-zh" name="unit_name_zh" required>
            </div>
            <div class="mb-3">
                <label for="unit-name-es" class="form-label">单位名称 (西) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="unit-name-es" name="unit_name_es" required>
            </div>
            <div class="d-flex justify-content-end">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="offcanvas">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>