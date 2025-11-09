<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-primary" id="create-btn" data-bs-toggle="offcanvas" data-bs-target="#data-drawer">
        <i class="bi bi-plus-circle me-2"></i>创建新冰量选项
    </button>
</div>

<div class="card">
    <div class="card-header">冰量选项列表</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>自定义编号</th>
                        <th>选项名称 (中)</th>
                        <th>操作说明 (中)</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ice_options)): ?>
                        <tr><td colspan="4" class="text-center">暂无冰量选项数据。</td></tr>
                    <?php else: ?>
                        <?php foreach ($ice_options as $option): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($option['ice_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($option['name_zh']); ?></td>
                                <td><?php echo htmlspecialchars($option['sop_zh'] ?? '未定义'); ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary edit-btn" data-id="<?php echo $option['id']; ?>" data-bs-toggle="offcanvas" data-bs-target="#data-drawer">编辑</button>
                                    <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?php echo $option['id']; ?>" data-name="<?php echo htmlspecialchars($option['name_zh']); ?>">删除</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="data-drawer" aria-labelledby="drawer-label">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="drawer-label">创建/编辑冰量选项</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form id="data-form">
            <input type="hidden" id="data-id" name="id">
            <div class="mb-3">
                <label for="data-code" class="form-label">自定义编号 <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="data-code" name="code" required>
            </div>
            <div class="mb-3">
                <label for="data-name-zh" class="form-label">选项名称 (中) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="data-name-zh" name="name_zh" required>
            </div>
             <div class="mb-3">
                <label for="data-sop-zh" class="form-label">操作说明 (中) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="data-sop-zh" name="sop_zh" placeholder="例如: 加冰至500ml刻度线" required>
            </div>
            <hr>
            <div class="mb-3">
                <label for="data-name-es" class="form-label">选项名称 (西) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="data-name-es" name="name_es" required>
            </div>
             <div class="mb-3">
                <label for="data-sop-es" class="form-label">操作说明 (西) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="data-sop-es" name="sop_es" placeholder="Ej: Añadir hielo hasta la línea de 500ml" required>
            </div>
            <div class="d-flex justify-content-end mt-4">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="offcanvas">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>