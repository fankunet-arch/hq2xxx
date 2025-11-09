<?php
/**
 * Toptea HQ - POS Member Management View
 * Engineer: Gemini | Date: 2025-10-28
 */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="input-group" style="max-width: 400px;">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" id="search-input" class="form-control" placeholder="按姓名或手机号搜索会员...">
    </div>
    <button class="btn btn-primary" id="create-btn" data-bs-toggle="offcanvas" data-bs-target="#data-drawer">
        <i class="bi bi-plus-circle me-2"></i>创建新会员
    </button>
</div>

<div class="card">
    <div class="card-header">会员列表</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="members-table">
                <thead>
                    <tr>
                        <th>姓名</th>
                        <th>手机号</th>
                        <th>积分余额</th>
                        <th>等级</th>
                        <th>状态</th>
                        <th>注册时间</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($members)): ?>
                        <tr><td colspan="7" class="text-center">暂无会员数据。</td></tr>
                    <?php else: ?>
                        <?php foreach ($members as $member): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars(trim($member['first_name'] . ' ' . $member['last_name'])); ?></strong></td>
                                <td class="phone-number"><?php echo htmlspecialchars($member['phone_number']); ?></td>
                                <td><span class="badge text-bg-info"><?php echo number_format($member['points_balance'], 2); ?></span></td>
                                <td><?php echo htmlspecialchars($member['level_name_zh'] ?? '无等级'); ?></td>
                                <td>
                                    <?php if ($member['is_active']): ?>
                                        <span class="badge text-bg-success">已激活</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">已禁用</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($member['created_at'])); ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary edit-btn" data-id="<?php echo $member['id']; ?>" data-bs-toggle="offcanvas" data-bs-target="#data-drawer">编辑</button>
                                    <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?php echo $member['id']; ?>" data-name="<?php echo htmlspecialchars(trim($member['first_name'] . ' ' . $member['last_name'])); ?>">删除</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="data-drawer" aria-labelledby="drawer-label" style="width: 500px;">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="drawer-label">创建/编辑会员</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form id="data-form">
            <input type="hidden" id="data-id" name="id">
            
            <div class="mb-3">
                <label for="phone_number" class="form-label">手机号 <span class="text-danger">*</span></label>
                <input type="tel" class="form-control" id="phone_number" name="phone_number" required>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md">
                    <label for="first_name" class="form-label">名字</label>
                    <input type="text" class="form-control" id="first_name" name="first_name">
                </div>
                <div class="col-md">
                    <label for="last_name" class="form-label">姓氏</label>
                    <input type="text" class="form-control" id="last_name" name="last_name">
                </div>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">邮箱</label>
                <input type="email" class="form-control" id="email" name="email">
            </div>
            <div class="mb-3">
                <label for="birthdate" class="form-label">生日</label>
                <input type="date" class="form-control" id="birthdate" name="birthdate">
            </div>
            <hr>
            <div class="mb-3">
                <label for="member_level_id" class="form-label">会员等级</label>
                <select class="form-select" id="member_level_id" name="member_level_id">
                    <option value="">-- 无等级 --</option>
                    <?php foreach ($member_levels as $level): ?>
                        <option value="<?php echo $level['id']; ?>"><?php echo htmlspecialchars($level['level_name_zh']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="points_balance" class="form-label">积分余额</label>
                <input type="number" step="0.01" class="form-control" id="points_balance" name="points_balance" value="0">
                <div class="form-text">可手动调整会员积分。</div>
            </div>
             <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" checked>
                <label class="form-check-label" for="is_active">激活此会员账户</label>
            </div>

            <div class="d-flex justify-content-end mt-4">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="offcanvas">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>