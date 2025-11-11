<?php
/**
 * TopTea HQ - POS Print Template Management View
 * Version: 6.2.0 (FIX: Enable QR Code variable editing)
 * Engineer: Gemini | Date: 2025-11-04
 * Update:
 * 1. Removed 'readonly' attribute from the QR code component's value input.
 * 2. Added a 'placeholder' to the QR code input for better UX.
 * 3. (Previous) Added real-time HTML Mock Preview pane.
 * 4. (Previous) Widened Offcanvas to 90vw and refactored to a 3-column layout (3, 5, 4).
 * 5. (Previous) Improved helper text for K/V components.
 * 6. (Previous) Added new div#template-preview-paper for dynamic size switching.
 */

// Helper function to get a readable name for template types
function get_template_type_name($type) {
    $map = [
        'EOD_REPORT' => '日结报告 (Z-Out)',
        'RECEIPT' => '顾客小票',
        'KITCHEN_ORDER' => '厨房出品单',
        'SHIFT_REPORT' => '交接班报告',
        'CUP_STICKER' => '杯贴标签',
        'EXPIRY_LABEL' => '效期标签'
    ];
    return $map[$type] ?? $type;
}

// 物理尺寸列表 (key 必须是 CSS 友好的)
$physical_sizes = [
    '80mm' => '80mm (连续纸卷)',
    '50x30' => '50 × 30 mm (标签)',
    '40x30' => '40 × 30 mm (标签)',
    '58x40' => '58 × 40 mm (标签)',
    '30x40' => '30 × 40 mm (标签)',
    '25x40' => '25 × 40 mm (标签)',
    '60x40' => '60 × 40 mm (标签)',
    '40x60' => '40 × 60 mm (标签)',
    '50x40' => '50 × 40 mm (标签)',
    '50x70' => '50 × 70 mm (标签)'
];
?>

<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-primary" id="create-btn" data-bs-toggle="offcanvas" data-bs-target="#data-drawer">
        <i class="bi bi-plus-circle me-2"></i>创建新模板
    </button>
</div>

<div class="card">
    <div class="card-header">打印模板列表</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>模板名称</th>
                        <th>模板类型</th>
                        <th>物理尺寸</th>
                        <th>状态</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($templates)): ?>
                        <tr><td colspan="5" class="text-center">暂无打印模板。</td></tr>
                    <?php else: ?>
                        <?php foreach ($templates as $template): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($template['template_name']); ?></strong></td>
                                <td>
                                    <?php 
                                        $type_name = get_template_type_name($template['template_type']);
                                        $badge_class = 'text-bg-info';
                                        if ($template['template_type'] === 'CUP_STICKER') $badge_class = 'text-bg-warning';
                                        if ($template['template_type'] === 'EXPIRY_LABEL') $badge_class = 'text-bg-primary';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($type_name); ?></span>
                                </td>
                                <td>
                                    <span class="badge text-bg-secondary"><?php echo htmlspecialchars($template['physical_size'] ?? '未设置'); ?></span>
                                </td>
                                <td>
                                    <?php if ($template['is_active']): ?>
                                        <span class="badge text-bg-success">已启用</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">已禁用</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary edit-btn" data-id="<?php echo $template['id']; ?>" data-bs-toggle="offcanvas" data-bs-target="#data-drawer">编辑</button>
                                    <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?php echo $template['id']; ?>" data-name="<?php echo htmlspecialchars($template['template_name']); ?>">删除</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="alert alert-info mt-4" role="alert">
  <h4 class="alert-heading">关于打印模板 (可视化编辑器)</h4>
  <p>您现在可以使用可视化编辑器来构建模板。从“组件工具栏”中添加元素，并通过拖拽手柄 <i class="bi bi-grip-vertical"></i> 来调整它们的顺序。</p>
  <hr>
  <p class="mb-0"><b>重要提示：</b> “商品循环” (items_loop) 是一个特殊组件，它会自动打印订单中的所有商品。请将您希望为*每件商品*打印的行（例如：商品名、定制）拖拽到“商品循环”的虚线框内。</p>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="data-drawer" aria-labelledby="drawer-label" style="width: 90vw;">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="drawer-label">创建/编辑模板</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form id="data-form">
            <input type="hidden" id="data-id" name="id">
            <input type="hidden" id="template_content_json" name="template_content">

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="template_name" class="form-label">模板名称 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="template_name" name="template_name" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="template_type" class="form-label">模板类型 <span class="text-danger">*</span></label>
                    <select class="form-select" id="template_type" name="template_type" required>
                        <option value="" selected disabled>-- 请选择 --</option>
                        <option value="RECEIPT">顾客小票</option>
                        <option value="KITCHEN_ORDER">厨房出品单</option>
                        <option value="CUP_STICKER">杯贴标签</option>
                        <option value="EXPIRY_LABEL">效期标签</option>
                        <option value="EOD_REPORT">日结报告 (Z-Out)</option>
                        <option value="SHIFT_REPORT">交接班报告</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="physical_size" class="form-label">物理尺寸 <span class="text-danger">*</span></label>
                    <select class="form-select" id="physical_size" name="physical_size" required>
                        <option value="" selected disabled>-- 请选择尺寸 --</option>
                        <?php foreach ($physical_sizes as $value => $label): ?>
                            <option value="<?php echo $value; ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 mb-3 d-flex align-items-end">
                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" checked>
                        <label class="form-check-label" for="is_active">启用</label>
                    </div>
                </div>
            </div>

            <hr>

            <div class="row">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-header">
                            组件工具栏
                        </div>
                        <div class="card-body">
                            <p class="form-text">点击组件添加到画布</p>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-add-text"><i class="bi bi-fonts me-2"></i>添加 文本/变量</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-add-kv"><i class="bi bi-distribute-horizontal me-2"></i>添加 键/值 对</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-add-divider"><i class="bi bi-hr me-2"></i>添加 分割线</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-add-feed"><i class="bi bi-arrow-down-short me-2"></i>添加 走纸</button>
                                <button type="button" class="btn btn-sm btn-outline-warning" id="btn-add-loop"><i class="bi bi-arrow-repeat me-2"></i>添加 商品循环</button>
                                <button type="button" class="btn btn-sm btn-outline-info" id="btn-add-qr"><i class="bi bi-qr-code me-2"></i>添加 二维码</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="btn-add-cut"><i class="bi bi-scissors me-2"></i>添加 切刀</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-5">
                    <div class="card bg-dark" style="min-height: 500px;">
                        <div class="card-header">
                            画布 (拖拽排序)
                        </div>
                        <div class="card-body" id="visual-editor-canvas">
                            </div>
                    </div>
                </div>

                <div class="col-md-4">
                     <div class="card" style="min-height: 500px;">
                        <div class="card-header">
                            实时预览 (模拟)
                        </div>
                        <div class="card-body" id="template-preview-pane">
                            <div id="template-preview-paper" class="preview-paper-80mm">
                                </div>
                        </div>
                    </div>
                </div>

            </div>
            
            <div class="d-flex justify-content-end mt-4">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="offcanvas">取消</button>
                <button type="submit" class="btn btn-primary">保存模板</button>
            </div>
        </form>
    </div>
</div>

<div id="visual-editor-templates" class="d-none">

    <div class="visual-editor-row card card-body mb-2" data-type="text">
        <div class="d-flex align-items-center">
            <i class="bi bi-grip-vertical drag-handle me-2"></i>
            <span class="badge text-bg-secondary me-3">文本</span>
            <input type="text" class="form-control form-control-sm prop-value" placeholder="输入文本或 {变量}">
            <select class="form-select form-select-sm ms-2 prop-align" style="width: 100px;">
                <option value="left">居左</option>
                <option value="center">居中</option>
                <option value="right">居右</option>
            </select>
            <select class="form-select form-select-sm ms-2 prop-size" style="width: 100px;">
                <option value="normal">标准</option>
                <option value="wide">加宽</option>
                <option value="high">加高</option>
                <option value="double">双倍</option>
            </select>
            <button type="button" class="btn-close btn-remove-row ms-2"></button>
        </div>
    </div>

    <div class="visual-editor-row card card-body mb-2" data-type="kv">
        <div class="d-flex align-items-center flex-wrap">
            <i class="bi bi-grip-vertical drag-handle me-2"></i>
            <span class="badge text-bg-info me-3">键/值</span>
            <input type="text" class="form-control form-control-sm prop-key" placeholder="例如: 订单总额" style="flex: 1; min-width: 120px;">
            <input type="text" class="form-control form-control-sm ms-2 prop-value" placeholder="例如: {final_total} €" style="flex: 1; min-width: 120px;">
            <div class="form-check form-switch ms-3" title="值是否加粗">
                <input class="form-check-input prop-bold" type="checkbox" role="switch">
                <label class="form-check-label small">加粗值</label>
            </div>
            <button type="button" class="btn-close btn-remove-row ms-2"></button>
            <div class="form-text px-1 mt-2 w-100" style="padding-left: 28px !important;">
                “键”是左侧标题（如“总额”），“值”是右侧数据（通常是一个 {变量}）。
            </div>
        </div>
    </div>

    <div class="visual-editor-row card card-body mb-2" data-type="divider">
        <div class="d-flex align-items-center">
            <i class="bi bi-grip-vertical drag-handle me-2"></i>
            <span class="badge text-bg-light text-dark me-3">分割线</span>
            <input type="text" class="form-control form-control-sm prop-char" value="-" style="width: 80px;">
            <span class="form-text ms-2"> (使用此字符填充)</span>
            <button type="button" class="btn-close btn-remove-row ms-auto"></button>
        </div>
    </div>

    <div class="visual-editor-row card card-body mb-2" data-type="feed">
        <div class="d-flex align-items-center">
            <i class="bi bi-grip-vertical drag-handle me-2"></i>
            <span class="badge text-bg-light text-dark me-3">走纸</span>
            <input type="number" class="form-control form-control-sm prop-lines" value="1" style="width: 80px;">
            <span class="form-text ms-2"> (行)</span>
            <button type="button" class="btn-close btn-remove-row ms-auto"></button>
        </div>
    </div>

    <div class="visual-editor-row card card-body mb-2" data-type="items_loop">
        <div class="d-flex align-items-center mb-2">
            <i class="bi bi-grip-vertical drag-handle me-2"></i>
            <span class="badge text-bg-warning me-3">商品循环</span>
            <span class="form-text">将“文本”或“键/值”组件拖到下方区域</span>
            <button type="button" class="btn-close btn-remove-row ms-auto"></button>
        </div>
        <div class="visual-editor-loop-canvas p-3" style="border: 2px dashed #664d03; border-radius: 0.375rem; min-height: 80px;">
            </div>
    </div>

    <div class="visual-editor-row card card-body mb-2" data-type="qr_code">
        <div class="d-flex align-items-center">
            <i class="bi bi-grip-vertical drag-handle me-2"></i>
            <span class="badge text-bg-info me-3">二维码</span>
            <input type="text" class="form-control form-control-sm prop-value" value="{qr_code}" placeholder="输入变量 (例如: {cup_order_number})">
            <select class="form-select form-select-sm ms-2 prop-align" style="width: 100px;">
                <option value="left">居左</option>
                <option value="center" selected>居中</option>
                <option value="right">居右</option>
            </select>
            <button type="button" class="btn-close btn-remove-row ms-2"></button>
        </div>
    </div>

    <div class="visual-editor-row card card-body mb-2" data-type="cut">
        <div class="d-flex align-items-center">
            <i class="bi bi-grip-vertical drag-handle me-2"></i>
            <span class="badge text-bg-danger me-3">切刀</span>
            <span class="form-text">执行切纸动作</span>
            <button type="button" class="btn-close btn-remove-row ms-auto"></button>
        </div>
    </div>

</div>