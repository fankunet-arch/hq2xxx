<?php
/**
 * Toptea HQ - Product Status Management View
 * Engineer: Gemini | Date: 2025-10-31
 */
?>
<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-primary" id="create-btn" data-bs-toggle="offcanvas" data-bs-target="#data-drawer">
        <i class="bi bi-plus-circle me-2"></i>创建新状态
    </button>
</div>

<div class="card">
    <div class="card-header">产品状态列表</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>状态编号</th>
                        <th>状态名称 (中)</th>
                        <th>状态名称 (西)</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($statuses)): ?>
                        <tr><td colspan="4" class="text-center">暂无产品状态数据。</td></tr>
                    <?php else: ?>
                        <?php foreach ($statuses as $status): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($status['status_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($status['status_name_zh']); ?></td>
                                <td><?php echo htmlspecialchars($status['status_name_es']); ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary edit-btn" data-id="<?php echo $status['id']; ?>" data-bs-toggle="offcanvas" data-bs-target="#data-drawer">编辑</button>
                                    <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?php echo $status['id']; ?>" data-name="<?php echo htmlspecialchars($status['status_name_zh']); ?>">删除</button>
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
        <h5 class="offcanvas-title" id="drawer-label">创建/编辑状态</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form id="data-form">
            <input type="hidden" id="data-id" name="id">
            <div class="mb-3">
                <label for="status_code" class="form-label">状态编号 <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="status_code" name="status_code" required>
            </div>
            <div class="mb-3">
                <label for="status_name_zh" class="form-label">状态名称 (中) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="status_name_zh" name="status_name_zh" required>
            </div>
            <div class="mb-3">
                <label for="status_name_es" class="form-label">状态名称 (西) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="status_name_es" name="status_name_es" required>
            </div>
            <div class="d-flex justify-content-end mt-4">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="offcanvas">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>