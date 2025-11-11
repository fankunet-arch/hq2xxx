<div class="row justify-content-center">
    <div class="col-lg-8">
        <form id="profile-form">
            <div class="card mb-4">
                <div class="card-header">基础信息</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">用户名</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['username']); ?>" readonly disabled>
                        <div class="form-text">用户名是您的唯一标识，不可修改。</div>
                    </div>
                    <div class="mb-3">
                        <label for="display_name" class="form-label">显示名称 <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="display_name" name="display_name" value="<?php echo htmlspecialchars($_SESSION['display_name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">邮箱</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($current_user['email'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">修改密码</div>
                <div class="card-body">
                    <div class="form-text mb-3 text-white-50">如果您不需要修改密码，请将以下三个输入框全部留空。</div>
                    <div class="mb-3">
                        <label for="current_password" class="form-label">当前密码</label>
                        <input type="password" class="form-control" id="current_password" name="current_password">
                         <div class="form-text">修改密码或邮箱时，必须提供当前密码以进行验证。</div>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">新密码</label>
                        <input type="password" class="form-control" id="new_password" name="new_password">
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">确认新密码</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-end mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>保存更改</button>
            </div>
        </form>
    </div>
</div>