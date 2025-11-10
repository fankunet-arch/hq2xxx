<?php
/**
 * Toptea HQ - RMS (Recipe Management System)
 * View for Global Adjustment Rules (Layer 2)
 * Engineer: Gemini | Date: 2025-11-02
 * Revision: 2.0 (Added Base Quantity Conditions)
 */

// Helper function to format rule display
function formatRuleCondition($rule, $cups, $ices, $sweets, $materials) {
    $parts = [];
    if (!empty($rule['cond_cup_id'])) $parts[] = '杯型=' . htmlspecialchars($cups[$rule['cond_cup_id']] ?? 'N/A');
    if (!empty($rule['cond_ice_id'])) $parts[] = '冰量=' . htmlspecialchars($ices[$rule['cond_ice_id']] ?? 'N/A');
    if (!empty($rule['cond_sweet_id'])) $parts[] = '甜度=' . htmlspecialchars($sweets[$rule['cond_sweet_id']] ?? 'N/A');
    if (!empty($rule['cond_material_id'])) $parts[] = '物料=' . htmlspecialchars($materials[$rule['cond_material_id']] ?? 'N/A');
    
    // ★★★ 新增显示逻辑 ★★★
    if (!empty($rule['cond_base_gt'])) $parts[] = 'L1用量 > ' . htmlspecialchars($rule['cond_base_gt']);
    if (!empty($rule['cond_base_lte'])) $parts[] = 'L1用量 <= ' . htmlspecialchars($rule['cond_base_lte']);
    // ★★★ 新增显示逻辑结束 ★★★
    
    return empty($parts) ? '<span class="text-muted">无 (全局应用)</span>' : implode(', ', $parts);
}

function formatRuleAction($rule, $materials, $units) {
    $action_map = [
        'SET_VALUE' => '设为定值',
        'ADD_MATERIAL' => '添加物料',
        'CONDITIONAL_OFFSET' => '偏移量',
        'MULTIPLY_BASE' => '乘基础值'
    ];
    $action_text = $action_map[$rule['action_type']] ?? $rule['action_type'];
    $material_name = htmlspecialchars($materials[$rule['action_material_id']] ?? 'N/A');
    $value = htmlspecialchars($rule['action_value']);
    $unit_name = htmlspecialchars($units[$rule['action_unit_id']] ?? '');
    
    return "<strong>[$action_text]</strong> $material_name: $value $unit_name";
}

// Prepare lookup maps for formatting
$cup_map = array_column($cup_options, 'cup_name', 'id');
$ice_map = array_column($ice_options, 'name_zh', 'id');
$sweet_map = array_column($sweetness_options, 'name_zh', 'id');
$material_map = array_column($material_options, 'name_zh', 'id');
$unit_map = array_column($unit_options, 'name_zh', 'id');

?>
<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-primary" id="create-rule-btn" data-bs-toggle="offcanvas" data-bs-target="#global-rule-drawer">
        <i class="bi bi-plus-circle me-2"></i>创建新全局规则
    </button>
</div>

<div class="card">
    <div class="card-header">
        RMS 全局规则 (Layer 2)
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="bi bi-info-circle-fill me-2"></i>
            此处的规则将应用于 <strong>所有</strong> 产品的基础配方 (Layer 1) 之后，但在“产品特例规则” (Layer 3) 之前。
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>优先级</th>
                        <th>规则名称</th>
                        <th>条件 (AND)</th>
                        <th>动作</th>
                        <th>状态</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($global_rules)): ?>
                        <tr><td colspan="6" class="text-center">暂无全局规则。</td></tr>
                    <?php else: ?>
                        <?php foreach ($global_rules as $rule): ?>
                            <tr>
                                <td><span class="badge text-bg-secondary"><?php echo htmlspecialchars($rule['priority']); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($rule['rule_name']); ?></strong></td>
                                <td><?php echo formatRuleCondition($rule, $cup_map, $ice_map, $sweet_map, $material_map); ?></td>
                                <td><?php echo formatRuleAction($rule, $material_map, $unit_map); ?></td>
                                <td>
                                    <?php if ($rule['is_active']): ?>
                                        <span class="badge text-bg-success">已启用</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">已禁用</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary edit-btn" data-id="<?php echo $rule['id']; ?>" data-bs-toggle="offcanvas" data-bs-target="#global-rule-drawer">编辑</button>
                                    <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?php echo $rule['id']; ?>" data-name="<?php echo htmlspecialchars($rule['rule_name']); ?>">删除</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="global-rule-drawer" aria-labelledby="drawer-label" style="width: 600px;">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="drawer-label">创建/编辑全局规则</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form id="global-rule-form">
            <input type="hidden" id="rule-id" name="id">

            <div class="card mb-3">
                <div class="card-header">基本信息</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="rule_name" class="form-label">规则名称 <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="rule_name" name="rule_name" placeholder="例如: 标准糖量公式" required>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <label for="priority" class="form-label">优先级 <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="priority" name="priority" value="100" required>
                            <div class="form-text">数字越小，越先执行。</div>
                        </div>
                        <div class="col-6 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" checked>
                                <label class="form-check-label" for="is_active">启用此规则</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">条件 (AND)</div>
                <div class="card-body">
                    <p class="form-text">所有条件必须同时满足时，规则才生效。留空表示“任意”。</p>
                    <div class="row g-2">
                        <div class="col-md-6 mb-2">
                            <label for="cond_cup_id" class="form-label">杯型</label>
                            <select class="form-select" id="cond_cup_id" name="cond_cup_id">
                                <option value="">-- 任意杯型 --</option>
                                <?php foreach($cup_options as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['cup_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                         <div class="col-md-6 mb-2">
                            <label for="cond_sweet_id" class="form-label">甜度</label>
                            <select class="form-select" id="cond_sweet_id" name="cond_sweet_id">
                                <option value="">-- 任意甜度 --</option>
                                <?php foreach($sweetness_options as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name_zh']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                         <div class="col-md-6 mb-2">
                            <label for="cond_ice_id" class="form-label">冰量</label>
                            <select class="form-select" id="cond_ice_id" name="cond_ice_id">
                                <option value="">-- 任意冰量 --</option>
                                <?php foreach($ice_options as $i): ?>
                                    <option value="<?php echo $i['id']; ?>"><?php echo htmlspecialchars($i['name_zh']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                         <div class="col-md-6 mb-2">
                            <label for="cond_material_id" class="form-label">基础物料</label>
                            <select class="form-select" id="cond_material_id" name="cond_material_id">
                                <option value="">-- 任意物料 --</option>
                                <?php foreach($material_options as $m): ?>
                                    <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['name_zh']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-2">
                            <label for="cond_base_gt" class="form-label">当 L1 基础用量 > (克/毫升)</label>
                            <input type="number" step="0.01" class="form-control" id="cond_base_gt" name="cond_base_gt" placeholder="例如: 60">
                        </div>
                        <div class="col-md-6 mb-2">
                            <label for="cond_base_lte" class="form-label">当 L1 基础用量 <= (克/毫升)</label>
                            <input type="number" step="0.01" class="form-control" id="cond_base_lte" name="cond_base_lte" placeholder="例如: 60">
                        </div>
                        </div>
                </div>
            </div>

             <div class="card mb-3">
                <div class="card-header">动作 (Action)</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="action_type" class="form-label">动作类型 <span class="text-danger">*</span></label>
                        <select class="form-select" id="action_type" name="action_type" required>
                            <option value="" disabled selected>-- 请选择动作 --</option>
                            <option value="SET_VALUE">设为定值</option>
                            <option value="ADD_MATERIAL">添加物料</option>
                            <option value="CONDITIONAL_OFFSET">偏移量</option>
                            <option value="MULTIPLY_BASE">乘基础值</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="action_material_id" class="form-label">目标物料 <span class="text-danger">*</span></label>
                        <select class="form-select" id="action_material_id" name="action_material_id" required>
                            <option value="" disabled selected>-- 请选择 --</option>
                            <?php foreach($material_options as $m): ?>
                                <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['name_zh']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">此动作用于哪个物料 (例如: 果糖, 冰块)。</div>
                    </div>
                    <div class="row g-2">
                        <div class="col-8">
                            <label for="action_value" class="form-label">值 <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="action_value" name="action_value" required>
                            <div class="form-text">e.g., 50, -10, 1.25</div>
                        </div>
                        <div class="col-4" id="action-unit-wrapper" style="display: none;">
                            <label for="action_unit_id" class="form-label">单位</label>
                            <select class="form-select" id="action_unit_id" name="action_unit_id">
                                <option value="">-- 单位 --</option>
                                <?php foreach($unit_options as $u): ?>
                                    <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name_zh']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-4">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="offcanvas">取消</button>
                <button type="submit" class="btn btn-primary">保存规则</button>
            </div>
        </form>
    </div>
</div>