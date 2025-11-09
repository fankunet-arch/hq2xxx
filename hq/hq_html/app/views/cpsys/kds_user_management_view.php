<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="index.php?page=store_management" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> 返回门店列表
    </a>
    <button class="btn btn-primary" id="create-btn" data-bs-toggle="offcanvas" data-bs-target="#data-drawer">
        <i class="bi bi-plus-circle me-2"></i>创建新账户
    </button>
</div>

<div class="card">
    <div class="card-header">
        门店 "<?php echo htmlspecialchars($store_data['store_name']); ?>" 的 KDS 账户列表
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>用户名</th>
                        <th>显示名称</th>
                        <th>角色</th>
                        <th>状态</th>
                        <th>最后登录 (Madrid)</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($kds_users)): ?>
                        <tr><td colspan="6" class="text-center">该门店暂无 KDS 账户。</td></tr>
                    <?php else: ?>
                        <?php foreach ($kds_users as $user): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                <td><?php echo htmlspecialchars($user['display_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['role']); ?></td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge text-bg-success">已激活</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">已禁用</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $user['last_login_at'] ? htmlspecialchars(fmt_local($user['last_login_at'], 'Y-m-d H:i')) : '从未'; ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary edit-btn" data-id="<?php echo $user['id']; ?>" data-bs-toggle="offcanvas" data-bs-target="#data-drawer">编辑</button>
                                    <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?php echo $user['id']; ?>" data-name="<?php echo htmlspecialchars($user['username']); ?>">删除</button>
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
        <h5 class="offcanvas-title" id="drawer-label">创建/编辑 KDS 账户</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form id="data-form">
            <input type="hidden" id="data-id" name="id">
            <input type="hidden" id="store-id" name="store_id" value="<?php echo $store_data['id']; ?>">
            
            <div class="mb-3">
                <label for="username" class="form-label">用户名 <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="display_name" class="form-label">显示名称 <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="display_name" name="display_name" required>
            </div>
            <hr>
            <div class="mb-3">
                <label for="password" class="form-label">新密码</label>
                <input type="password" class="form-control" id="password" name="password">
                <div class="form-text">仅在需要设置或重置密码时填写。留空则不修改。</div>
            </div>
             <div class="mb-3">
                <label for="password_confirm" class="form-label">确认新密码</label>
                <input type="password" class="form-control" id="password_confirm" name="password_confirm">
            </div>
            <hr>
             <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" checked>
                <label class="form-check-label" for="is_active">账户是否激活</label>
            </div>
            <div class="d-flex justify-content-end">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="offcanvas">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>