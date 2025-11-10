<?php
/**
 * Toptea HQ - POS Point Redemption Rules View
 * Engineer: Gemini | Date: 2025-10-28
 */

function getRewardDescription($rule, $promotions) {
    if ($rule['reward_type'] === 'DISCOUNT_AMOUNT') {
        return '减免 €' . number_format((float)$rule['reward_value_decimal'], 2);
    } elseif ($rule['reward_type'] === 'SPECIFIC_PROMOTION') {
        $promo_name = '未知活动';
        foreach ($promotions as $promo) {
            if ($promo['id'] == $rule['reward_promo_id']) {
                $promo_name = $promo['promo_name'];
                break;
            }
        }
        return '赠送活动: ' . htmlspecialchars($promo_name);
    }
    return '未知奖励类型';
}
?>
<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-primary" id="create-rule-btn" data-bs-toggle="offcanvas" data-bs-target="#rule-drawer">
        <i class="bi bi-plus-circle me-2"></i>创建新兑换规则
    </button>
</div>

<div class="card">
    <div class="card-header">积分兑换规则列表</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>规则名称 (中)</th>
                        <th>所需积分</th>
                        <th>奖励内容</th>
                        <th>状态</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rules)): ?>
                        <tr><td colspan="5" class="text-center">暂无积分兑换规则。</td></tr>
                    <?php else: ?>
                        <?php foreach ($rules as $rule): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($rule['rule_name_zh']); ?></strong></td>
                                <td><span class="badge text-bg-primary"><?php echo htmlspecialchars($rule['points_required']); ?></span></td>
                                <td><?php echo getRewardDescription($rule, $promotions_for_select); ?></td>
                                <td>
                                    <?php if ($rule['is_active']): ?>
                                        <span class="badge text-bg-success">已启用</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">已禁用</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary edit-rule-btn" data-id="<?php echo $rule['id']; ?>" data-bs-toggle="offcanvas" data-bs-target="#rule-drawer">编辑</button>
                                    <button class="btn btn-sm btn-outline-danger delete-rule-btn" data-id="<?php echo $rule['id']; ?>" data-name="<?php echo htmlspecialchars($rule['rule_name_zh']); ?>">删除</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="rule-drawer" aria-labelledby="drawer-label">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="drawer-label">创建/编辑兑换规则</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form id="rule-form">
            <input type="hidden" id="rule-id" name="id">
            <div class="mb-3">
                <label for="rule_name_zh" class="form-label">规则名称 (中) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="rule_name_zh" name="rule_name_zh" required>
            </div>
            <div class="mb-3">
                <label for="rule_name_es" class="form-label">规则名称 (西) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="rule_name_es" name="rule_name_es" required>
            </div>
            <div class="mb-3">
                <label for="points_required" class="form-label">所需积分 <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="points_required" name="points_required" min="1" required>
            </div>
            <hr>
            <h6 class="text-white-50">奖励内容</h6>
            <div class="mb-3">
                <label for="reward_type" class="form-label">奖励类型 <span class="text-danger">*</span></label>
                <select class="form-select" id="reward_type" name="reward_type" required>
                    <option value="DISCOUNT_AMOUNT" selected>减免金额</option>
                    <option value="SPECIFIC_PROMOTION">赠送指定活动/优惠券</option>
                </select>
            </div>
            <div class="mb-3" id="reward-value-decimal-group">
                <label for="reward_value_decimal" class="form-label">减免金额 (€) <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0.01" class="form-control" id="reward_value_decimal" name="reward_value_decimal">
            </div>
            <div class="mb-3" id="reward-promo-id-group" style="display: none;">
                <label for="reward_promo_id" class="form-label">选择活动/优惠券 <span class="text-danger">*</span></label>
                <select class="form-select" id="reward_promo_id" name="reward_promo_id">
                    <option value="">-- 请选择 --</option>
                    <?php foreach ($promotions_for_select as $promo): ?>
                        <option value="<?php echo $promo['id']; ?>"><?php echo htmlspecialchars($promo['promo_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">请确保所选活动是优惠券类型或适合赠送。</div>
            </div>
             <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" checked>
                <label class="form-check-label" for="is_active">启用此兑换规则</label>
            </div>

            <div class="d-flex justify-content-end mt-4">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="offcanvas">取消</button>
                <button type="submit" class="btn btn-primary">保存规则</button>
            </div>
        </form>
    </div>
</div>
