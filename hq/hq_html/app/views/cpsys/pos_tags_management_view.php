<?php
/**
 * Toptea HQ - POS 标签管理 (次卡白名单)
 * Engineer: Gemini | Date: 2025-11-12
 * [R2.1] Implements: 3.1 CPSYS-RMS (R2)
 */
?>
<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-primary" id="create-btn" data-bs-toggle="offcanvas" data-bs-target="#data-drawer">
        <i class="bi bi-plus-circle me-2"></i>创建新标签
    </button>
</div>

<div class="card">
    <div class="card-header">POS 标签列表 (用于次卡白名单)</div>
    <div class="card-body">
        <div class="alert alert-info">
            <h4 class="alert-heading">关键标签说明</h4>
            <p>此处的标签用于定义次卡核销规则，请确保 <code>tag_code</code> 严格一致：</p>
            <ul>
                <li><code>pass_eligible_beverage</code>: 标记允许被次卡核销的 **主饮品**。</li>
                <li><code>free_addon</code>: 标记核销时可 **免费** 添加的加料。</li>
                <li><code>paid_addon</code>: 标记核销时需 **额外付费** 的加料。</li>
                <li><code>card_bundle</code>: 标记用于 **售卖** 次卡的商品（使其在POS菜单隐藏）。</li>
            </ul>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>标签编码 (Tag Code)</th>
                        <th>标签名称/描述 (Tag Name)</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pos_tags)): ?>
                        <tr><td colspan="3" class="text-center">暂无标签数据。</td></tr>
                    <?php else: ?>
                        <?php foreach ($pos_tags as $tag): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($tag['tag_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($tag['tag_name']); ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary edit-btn" data-id="<?php echo $tag['tag_id']; ?>" data-bs-toggle="offcanvas" data-bs-target="#data-drawer">编辑</button>
                                    <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?php echo $tag['tag_id']; ?>" data-name="<?php echo htmlspecialchars($tag['tag_name']); ?>">删除</button>
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
        <h5 class="offcanvas-title" id="drawer-label">创建/编辑标签</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form id="data-form">
            <input type="hidden" id="data-id" name="id">
            <div class="mb-3">
                <label for="tag_code" class="form-label">标签编码 (Tag Code) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="tag_code" name="tag_code" required>
                <div class="form-text">必须是唯一的英文/数字标识符，例如: <code>pass_eligible_beverage</code>。创建后不可修改。</div>
            </div>
            <div class="mb-3">
                <label for="tag_name" class="form-label">标签名称/描述 <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="tag_name" name="tag_name" required>
                <div class="form-text">用于后台识别的友好名称，例如: “次卡可核销饮品”。</div>
            </div>
            <div class="d-flex justify-content-end mt-4">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="offcanvas">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>