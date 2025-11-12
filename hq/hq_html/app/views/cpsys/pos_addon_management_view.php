<?php
/**
 * Toptea HQ - POS 加料项管理
 * [R2.2] Added POS Tag whitelist selection
 */
?>
<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-primary" id="create-btn" data-bs-toggle="offcanvas" data-bs-target="#data-drawer">
        <i class="bi bi-plus-circle me-2"></i>创建新加料
    </button>
</div>

<div class="card">
    <div class="card-header">POS 加料项管理</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>排序</th>
                        <th>加料编码 (Code)</th>
                        <th>名称 (ZH)</th>
                        <th>名称 (ES)</th>
                        <th>价格 (€)</th>
                        <th>关联物料</th>
                        <th>状态</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($addons)): ?>
                        <tr><td colspan="8" class="text-center">暂无加料数据。</td></tr>
                    <?php else: ?>
                        <?php foreach ($addons as $addon): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($addon['sort_order']); ?></td>
                                <td><strong><?php echo htmlspecialchars($addon['addon_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($addon['name_zh']); ?></td>
                                <td><?php echo htmlspecialchars($addon['name_es']); ?></td>
                                <td><?php echo htmlspecialchars(number_format($addon['price_eur'], 2)); ?></td>
                                <td><?php echo htmlspecialchars($addon['material_name_zh'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($addon['is_active']): ?>
                                        <span class="badge bg-success">上架中</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">已下架</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary edit-btn" data-id="<?php echo $addon['id']; ?>" data-bs-toggle="offcanvas" data-bs-target="#data-drawer">编辑</button>
                                    <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?php echo $addon['id']; ?>" data-name="<?php echo htmlspecialchars($addon['name_zh']); ?>">删除</button>
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
        <h5 class="offcanvas-title" id="drawer-label">创建/编辑加料</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form id="data-form">
            <input type="hidden" id="data-id" name="id">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="addon_code" class="form-label">加料编码 (Code) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="addon_code" name="addon_code" required>
                    <div class="form-text">POS/API 使用的唯一键 (e.g. <code>PEARL</code>)</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="price_eur" class="form-label">价格 (€) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="price_eur" name="price_eur" step="0.01" min="0" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="name_zh" class="form-label">名称 (ZH) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name_zh" name="name_zh" required>
            </div>
            
            <div class="mb-3">
                <label for="name_es" class="form-label">名称 (ES) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name_es" name="name_es" required>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="material_id" class="form-label">关联KDS物料 (可选)</label>
                    <select class="form-select" id="material_id" name="material_id">
                        <option value="">-- 不关联 (不扣库存) --</option>
                        <?php if (isset($materials) && !empty($materials)): ?>
                            <?php foreach ($materials as $material): ?>
                                <option value="<?php echo $material['id']; ?>">
                                    <?php echo htmlspecialchars($material['material_code'] . ' - ' . $material['name_zh']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                 <div class="col-md-6 mb-3">
                    <label for="sort_order" class="form-label">排序 (越小越靠前) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="sort_order" name="sort_order" value="99" required>
                </div>
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
                    按住 Ctrl (或 Command) 进行多选。例如：选择 <code>free_addon</code> 或 <code>paid_addon</code>。
                </div>
            </div>
            <div class="d-flex justify-content-end mt-4">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="offcanvas">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>