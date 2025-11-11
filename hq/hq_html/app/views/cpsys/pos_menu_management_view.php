<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-primary" id="create-btn" data-bs-toggle="offcanvas" data-bs-target="#data-drawer">
        <i class="bi bi-plus-circle me-2"></i>创建新商品
    </button>
</div>

<div class="card">
    <div class="card-header">POS 菜单商品列表</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>排序</th>
                        <th>商品名称 (中)</th>
                        <th>已定义规格</th>
                        <th>所属分类</th>
                        <th>状态</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($menu_items)): ?>
                        <tr><td colspan="6" class="text-center">暂无菜单商品数据。</td></tr>
                    <?php else: ?>
                        <?php foreach ($menu_items as $item): ?>
                            <tr>
                                <td><span class="badge text-bg-secondary"><?php echo htmlspecialchars($item['sort_order']); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($item['name_zh']); ?></strong></td>
                                <td>
                                    <?php if (!empty($item['variants'])): ?>
                                        <?php foreach (explode(', ', $item['variants']) as $variant_name): ?>
                                            <span class="badge text-bg-info me-1"><?php echo htmlspecialchars($variant_name); ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="badge text-bg-warning">未设置</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($item['category_name_zh'] ?? '未分类'); ?></td>
                                <td>
                                    <?php if ($item['is_active']): ?>
                                        <span class="badge text-bg-success">已上架</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">已下架</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="index.php?page=pos_variants_management&item_id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-info">管理规格与定价</a>
                                    <button class="btn btn-sm btn-outline-primary edit-btn" data-id="<?php echo $item['id']; ?>" data-bs-toggle="offcanvas" data-bs-target="#data-drawer">编辑</button>
                                    <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?php echo $item['id']; ?>" data-name="<?php echo htmlspecialchars($item['name_zh']); ?>">删除</button>
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
        <h5 class="offcanvas-title" id="drawer-label">创建/编辑商品</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form id="data-form">
            <input type="hidden" id="data-id" name="id">
            <div class="mb-3">
                <label for="name_zh" class="form-label">商品名称 (中) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name_zh" name="name_zh" required>
            </div>
            <div class="mb-3">
                <label for="name_es" class="form-label">商品名称 (西) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name_es" name="name_es" required>
            </div>
             <div class="mb-3">
                <label for="pos_category_id" class="form-label">POS 分类 <span class="text-danger">*</span></label>
                <select class="form-select" id="pos_category_id" name="pos_category_id" required>
                    <option value="" selected disabled>-- 请选择 --</option>
                    <?php foreach ($pos_categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name_zh']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
             <div class="mb-3">
                <label for="description_zh" class="form-label">商品描述 (中)</label>
                <textarea class="form-control" id="description_zh" name="description_zh" rows="3"></textarea>
            </div>
             <div class="mb-3">
                <label for="description_es" class="form-label">商品描述 (西)</label>
                <textarea class="form-control" id="description_es" name="description_es" rows="3"></textarea>
            </div>
            <div class="mb-3">
                <label for="sort_order" class="form-label">排序</label>
                <input type="number" class="form-control" id="sort_order" name="sort_order" value="99">
                <div class="form-text">数字越小，在分类中排序越靠前。</div>
            </div>
             <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" checked>
                <label class="form-check-label" for="is_active">在 POS 机上架</label>
            </div>
            <div class="d-flex justify-content-end mt-4">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="offcanvas">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>