<?php
/**
 * Toptea HQ - POS Member Level Management View
 * Engineer: Gemini | Date: 2025-10-28
 */
?>
<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-primary" id="create-btn" data-bs-toggle="offcanvas" data-bs-target="#data-drawer">
        <i class="bi bi-plus-circle me-2"></i>创建新等级
    </button>
</div>

<div class="card">
    <div class="card-header">会员等级列表</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>排序</th>
                        <th>等级名称 (中)</th>
                        <th>等级名称 (西)</th>
                        <th>升级积分门槛</th>
                        <th>升级奖励 (促销活动)</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($member_levels)): ?>
                        <tr><td colspan="6" class="text-center">暂无会员等级数据。</td></tr>
                    <?php else: ?>
                        <?php foreach ($member_levels as $level): ?>
                            <tr>
                                <td><span class="badge text-bg-secondary"><?php echo htmlspecialchars($level['sort_order']); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($level['level_name_zh']); ?></strong></td>
                                <td><?php echo htmlspecialchars($level['level_name_es']); ?></td>
                                <td><span class="badge text-bg-primary"><?php echo htmlspecialchars($level['points_threshold']); ?></span></td>
                                <td>
                                    <?php if (!empty($level['promo_name'])): ?>
                                        <span class="badge text-bg-info"><?php echo htmlspecialchars($level['promo_name']); ?></span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">未设置</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary edit-btn" data-id="<?php echo $level['id']; ?>" data-bs-toggle="offcanvas" data-bs-target="#data-drawer">编辑</button>
                                    <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?php echo $level['id']; ?>" data-name="<?php echo htmlspecialchars($level['level_name_zh']); ?>">删除</button>
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
        <h5 class="offcanvas-title" id="drawer-label">创建/编辑会员等级</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form id="data-form">
            <input type="hidden" id="data-id" name="id">
            <div class="mb-3">
                <label for="level_name_zh" class="form-label">等级名称 (中) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="level_name_zh" name="level_name_zh" required>
            </div>
            <div class="mb-3">
                <label for="level_name_es" class="form-label">等级名称 (西) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="level_name_es" name="level_name_es" required>
            </div>
            <div class="mb-3">
                <label for="points_threshold" class="form-label">升级积分门槛 <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="points_threshold" name="points_threshold" value="0" step="0.01" required>
                <div class="form-text">会员累计积分达到此数值时自动升级。</div>
            </div>
            <div class="mb-3">
                <label for="level_up_promo_id" class="form-label">升级奖励</label>
                <select class="form-select" id="level_up_promo_id" name="level_up_promo_id">
                    <option value="">-- 无奖励 --</option>
                    <?php foreach ($promotions_for_select as $promo): ?>
                        <option value="<?php echo $promo['id']; ?>"><?php echo htmlspecialchars($promo['promo_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">会员升级到此等级时，自动赠送关联的促销活动/优惠券。</div>
            </div>
            <div class="mb-3">
                <label for="sort_order" class="form-label">排序</label>
                <input type="number" class="form-control" id="sort_order" name="sort_order" value="99">
                <div class="form-text">数字越小，等级越高。</div>
            </div>
            <div class="d-flex justify-content-end mt-4">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="offcanvas">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>