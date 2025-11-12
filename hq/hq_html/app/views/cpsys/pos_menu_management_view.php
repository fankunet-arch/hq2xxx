<?php
/**
 * Toptea HQ - POS 菜单商品管理
 * [R2.3] Added POS Tag whitelist selection
 */
?>
<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-primary" id="create-btn" data-bs-toggle="offcanvas" data-bs-target="#data-drawer">
        <i class="bi bi-plus-circle me-2"></i>创建新商品
    </button>
</div>

<div class="card">
    <div class="card-header">POS 菜单商品管理</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>排序</th>
                        <th>商品名称 (ZH)</th>
                        <th>商品名称 (ES)</th>
                        <th>所属分类</th>
                        <th>状态</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($menu_items)): ?>
                        <tr><td colspan="6" class="text-center">暂无商品数据。</td></tr>
                    <?php else: ?>
                        <?php foreach ($menu_items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['sort_order']); ?></td>
                                <td><strong><?php echo htmlspecialchars($item['name_zh']); ?></strong></td>
                                <td><?php echo htmlspecialchars($item['name_es']); ?></td>
                                <td><?php echo htmlspecialchars($item['category_name_zh'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($item['is_active']): ?>
                                        <span class="badge bg-success">上架中</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">已下架</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary edit-btn" data-id="<?php echo $item['id']; ?>" data-bs-toggle="offcanvas" data-bs-target="#data-drawer">编辑商品</button>
                                    <a href="?page=pos_variants_management&menu_item_id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-secondary">管理规格</a>
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
                <label for="pos_category_id" class="form-label">所属分类 <span class="text-danger">*</span></label>
                <select class="form-select" id="pos_category_id" name="pos_category_id" required>
                    <option value="">-- 请选择分类 --</option>
                    <?php if (isset($pos_categories) && !empty($pos_categories)): ?>
                        <?php foreach ($pos_categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>">
                                <?php echo htmlspecialchars($category['name_zh'] . ' / ' . $category['name_es']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="name_zh" class="form-label">商品名称 (ZH) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name_zh" name="name_zh" required>
            </div>
            
            <div class="mb-3">
                <label for="name_es" class="form-label">商品名称 (ES) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name_es" name="name_es" required>
            </div>
            
            <div class="mb-3">
                <label for="description_zh" class="form-label">商品描述 (ZH)</label>
                <textarea class="form-control" id="description_zh" name="description_zh" rows="3"></textarea>
            </div>
            
            <div class="mb-3">
                <label for="description_es" class="form-label">商品描述 (ES)</label>
                <textarea class="form-control" id="description_es" name="description_es" rows="3"></textarea>
            </div>

            <div class="mb-3">
                <label for="sort_order" class="form-label">排序 (越小越靠前) <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="sort_order" name="sort_order" value="99" required>
            </div>

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" checked>
                <label class="form-check-label" for="is_active">是否上架 (Is Active)</label>
            </div>
            
            <div class="mb-3">
                <label for="tag_ids" class="form-label">标签白名单 (用于次卡)</label>
                <select class="form-select" id="tag_ids" name="tag_ids" multiple size="5">
                    <?php if (isset($all_pos_tags) && !empty($all_pos_tags)): ?>
                        <?php foreach ($all_pos_tags as $tag): ?>
                            <option value="<?php echo $tag['tag_id']; ?>">
                                <?php echo htmlspecialchars($tag['tag_name'] . ' ('. $tag['tag_code'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                         <option value="" disabled>-- 无法加载标签 (请先在POS标签管理中创建) --</option>
                    <?php endif; ?>
                </select>
                <div class="form-text">
                    按住 Ctrl (或 Command) 进行多选。例如：<code>pass_eligible_beverage</code> 或 <code>card_bundle</code>。
                </div>
            </div>
            <div class="d-flex justify-content-end mt-4">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="offcanvas">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>