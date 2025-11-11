<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="index.php?page=pos_menu_management" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> 返回商品列表
    </a>
    <button class="btn btn-primary" id="create-btn" data-bs-toggle="offcanvas" data-bs-target="#data-drawer">
        <i class="bi bi-plus-circle me-2"></i>创建新规格
    </button>
</div>

<div class="card">
    <div class="card-header">
        管理商品 "<?php echo htmlspecialchars($menu_item['name_zh']); ?>" 的规格与定价
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>排序</th>
                        <th>规格名称 (中)</th>
                        <th>关联配方</th>
                        <th>售价 (€)</th>
                        <th>默认</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($variants)): ?>
                        <tr><td colspan="6" class="text-center">该商品暂无规格，请创建。</td></tr>
                    <?php else: ?>
                        <?php foreach ($variants as $variant): ?>
                            <tr>
                                <td><span class="badge text-bg-secondary"><?php echo htmlspecialchars($variant['sort_order']); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($variant['variant_name_zh']); ?></strong></td>
                                <td><?php echo htmlspecialchars(($variant['product_sku'] ?? 'N/A') . ' - ' . ($variant['recipe_name_zh'] ?? 'N/A')); ?></td>
                                <td><?php echo htmlspecialchars(number_format($variant['price_eur'], 2)); ?></td>
                                <td>
                                    <?php if ($variant['is_default']): ?>
                                        <span class="badge text-bg-primary">是</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary edit-btn" data-id="<?php echo $variant['id']; ?>" data-bs-toggle="offcanvas" data-bs-target="#data-drawer">编辑</button>
                                    <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?php echo $variant['id']; ?>" data-name="<?php echo htmlspecialchars($variant['variant_name_zh']); ?>">删除</button>
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
        <h5 class="offcanvas-title" id="drawer-label">创建/编辑规格</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form id="data-form">
            <input type="hidden" id="data-id" name="id">
            <input type="hidden" id="menu-item-id" name="menu_item_id" value="<?php echo $menu_item['id']; ?>">
            
            <div class="mb-3">
                <label for="variant_name_zh" class="form-label">规格名称 (中) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="variant_name_zh" name="variant_name_zh" placeholder="例如: 中杯" required>
            </div>
            <div class="mb-3">
                <label for="variant_name_es" class="form-label">规格名称 (西) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="variant_name_es" name="variant_name_es" placeholder="Ej: Mediano" required>
            </div>
            <div class="mb-3">
                <label for="price_eur" class="form-label">售价 (€) <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="price_eur" name="price_eur" step="0.01" required>
            </div>
             <div class="mb-3">
                <label for="product_id" class="form-label">关联配方 <span class="text-danger">*</span></label>
                <select class="form-select" id="product_id" name="product_id" required>
                    <option value="" selected disabled>-- 从配方库选择 --</option>
                    <?php foreach ($recipes as $recipe): ?>
                        <option value="<?php echo $recipe['id']; ?>"><?php echo htmlspecialchars(($recipe['product_sku'] ?? 'N/A') . ' - ' . ($recipe['name_zh'] ?? 'N/A')); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">此规格在POS售出后，厨房将按照此配方制作。</div>
            </div>
            <div class="mb-3">
                <label for="sort_order" class="form-label">排序</label>
                <input type="number" class="form-control" id="sort_order" name="sort_order" value="99">
                <div class="form-text">数字越小，在POS机上排序越靠前。</div>
            </div>
             <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" role="switch" id="is_default" name="is_default" value="1">
                <label class="form-check-label" for="is_default">设为默认规格</label>
            </div>
            <div class="d-flex justify-content-end mt-4">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="offcanvas">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>