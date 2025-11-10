<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-primary" id="create-cup-btn" data-bs-toggle="offcanvas" data-bs-target="#cup-drawer">
        <i class="bi bi-plus-circle me-2"></i>创建新杯型
    </button>
</div>

<div class="card">
    <div class="card-header">
        杯型列表
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>自定义编号</th>
                        <th>杯型名称</th>
                        <th>操作说明 (中)</th>
                        <th>操作说明 (西)</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cups)): ?>
                        <tr>
                            <td colspan="5" class="text-center">暂无杯型数据。</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cups as $cup): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($cup['cup_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($cup['cup_name']); ?></td>
                                <td><?php echo htmlspecialchars($cup['sop_description_zh'] ?? '未定义'); ?></td>
                                <td><?php echo htmlspecialchars($cup['sop_description_es'] ?? '未定义'); ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary edit-cup-btn" 
                                            data-cup-id="<?php echo $cup['id']; ?>"
                                            data-bs-toggle="offcanvas" data-bs-target="#cup-drawer">
                                        编辑
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger delete-cup-btn"
											data-cup-id="<?php echo $cup['id']; ?>"
											data-cup-name="<?php echo htmlspecialchars($cup['cup_name']); ?>">
										删除
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

<div class="offcanvas offcanvas-end" tabindex="-1" id="cup-drawer" aria-labelledby="drawer-label">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="drawer-label">创建/编辑杯型</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form id="cup-form">
            <input type="hidden" id="cup-id" name="cup_id">

            <div class="mb-3">
                <label for="cup-code" class="form-label">自定义编号 <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="cup-code" name="cup_code" required>
            </div>

            <div class="mb-3">
                <label for="cup-name" class="form-label">杯型名称 <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="cup-name" name="cup_name" required>
            </div>

            <div class="mb-3">
                <label for="cup-sop-zh" class="form-label">操作说明 (中) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="cup-sop-zh" name="sop_zh" placeholder="例如: 使用90口径透明PP杯" required>
            </div>

            <div class="mb-3">
                <label for="cup-sop-es" class="form-label">操作说明 (西) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="cup-sop-es" name="sop_es" placeholder="Ej: Usar vaso PP transparente de 90mm" required>
            </div>

            <div class="d-flex justify-content-end mt-4">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="offcanvas">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>