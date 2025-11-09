<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-primary" id="create-btn" data-bs-toggle="offcanvas" data-bs-target="#data-drawer">
        <i class="bi bi-plus-circle me-2"></i>创建新分类
    </button>
</div>

<div class="card">
    <div class="card-header">POS 分类列表</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>排序</th>
                        <th>分类编码 (KEY)</th>
                        <th>分类名 (中)</th>
                        <th>分类名 (西)</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pos_categories)): ?>
                        <tr><td colspan="5" class="text-center">暂无POS分类数据。</td></tr>
                    <?php else: ?>
                        <?php foreach ($pos_categories as $category): ?>
                            <tr>
                                <td><span class="badge text-bg-secondary"><?php echo htmlspecialchars($category['sort_order']); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($category['category_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($category['name_zh']); ?></td>
                                <td><?php echo htmlspecialchars($category['name_es']); ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary edit-btn" data-id="<?php echo $category['id']; ?>" data-bs-toggle="offcanvas" data-bs-target="#data-drawer">编辑</button>
                                    <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?php echo $category['id']; ?>" data-name="<?php echo htmlspecialchars($category['name_zh']); ?>">删除</button>
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
        <h5 class="offcanvas-title" id="drawer-label">创建/编辑POS分类</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form id="data-form">
            <input type="hidden" id="data-id" name="id">
            <div class="mb-3">
                <label for="category_code" class="form-label">分类编码 (KEY) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="category_code" name="category_code" required>
                <div class="form-text">POS前端使用的唯一标识，建议使用英文，例如: `fruit_tea`。</div>
            </div>
            <div class="mb-3">
                <label for="name_zh" class="form-label">分类名 (中) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name_zh" name="name_zh" required>
            </div>
            <div class="mb-3">
                <label for="name_es" class="form-label">分类名 (西) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name_es" name="name_es" required>
            </div>
            <div class="mb-3">
                <label for="sort_order" class="form-label">排序</label>
                <input type="number" class="form-control" id="sort_order" name="sort_order" value="99">
                <div class="form-text">数字越小，排序越靠前。</div>
            </div>
            <div class="d-flex justify-content-end mt-4">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="offcanvas">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>