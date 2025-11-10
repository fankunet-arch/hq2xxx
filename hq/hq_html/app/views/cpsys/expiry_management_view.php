<?php
/**
 * Toptea HQ - cpsys
 * Expiry Management View (Refactored with Tabs & Status Highlight)
 * Engineer: Gemini | Date: 2025-10-31
 *
 * [A2 UTC SYNC]: Using fmt_local() for all timestamps.
 */

// Pre-process the data into separate arrays for each status
$active_items = [];
$used_items = [];
$discarded_items = [];

// [A2 UTC SYNC] 使用 PHP 的 time() (即当前UTC时间戳) 与数据库中的 UTC expires_at 比较
$now_utc_timestamp = time();

foreach ($expiry_items as $item) {
    if ($item['status'] === 'ACTIVE') {
        $active_items[] = $item;
    } elseif ($item['status'] === 'USED') {
        $used_items[] = $item;
    } elseif ($item['status'] === 'DISCARDED') {
        $discarded_items[] = $item;
    }
}
?>
<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-primary" disabled>
        <i class="bi bi-plus-circle me-2"></i>登记新开封物料 (仅限KDS端)
    </button>
</div>

<div class="card">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#active-items-tab">追踪中 <span class="badge rounded-pill text-bg-light"><?php echo count($active_items); ?></span></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#used-items-tab">已用完 <span class="badge rounded-pill text-bg-light"><?php echo count($used_items); ?></span></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#discarded-items-tab">已报废 <span class="badge rounded-pill text-bg-light"><?php echo count($discarded_items); ?></span></a>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="active-items-tab">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>所属门店</th>
                                <th>物料名称</th>
                                <th>开封时间 (Madrid)</th>
                                <th>过期时间 (Madrid)</th>
                                <th>状态</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($active_items)): ?>
                                <tr><td colspan="5" class="text-center">暂无需要追踪的物料。</td></tr>
                            <?php else: ?>
                                <?php foreach ($active_items as $item): 
                                    // [A2 UTC SYNC] $now_utc_timestamp
                                    $is_expired = strtotime($item['expires_at']) < $now_utc_timestamp;
                                    $row_class = $is_expired ? 'table-danger' : '';
                                ?>
                                    <tr class="<?php echo $row_class; ?>">
                                        <td><?php echo htmlspecialchars($item['store_name']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($item['material_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars(fmt_local($item['opened_at'], 'Y-m-d H:i')); ?></td>
                                        <td><?php echo htmlspecialchars(fmt_local($item['expires_at'], 'Y-m-d H:i')); ?></td>
                                        <td>
                                            <?php if ($is_expired): ?>
                                                <span class="badge text-bg-danger">已过期</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-success">追踪中</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="used-items-tab">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>所属门店</th>
                                <th>物料名称</th>
                                <th>开封时间 (Madrid)</th>
                                <th>处理时间 (Madrid)</th>
                                <th>处理人</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($used_items)): ?>
                                <tr><td colspan="5" class="text-center">暂无“已用完”的记录。</td></tr>
                            <?php else: ?>
                                <?php foreach ($used_items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['store_name']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($item['material_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars(fmt_local($item['opened_at'], 'Y-m-d H:i')); ?></td>
                                        <td><?php echo htmlspecialchars(fmt_local($item['handled_at'], 'Y-m-d H:i')); ?></td>
                                        <td><?php echo htmlspecialchars($item['handler_name'] ?? '未知'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="discarded-items-tab">
                <div class="table-responsive">
                     <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>所属门店</th>
                                <th>物料名称</th>
                                <th>开封时间 (Madrid)</th>
                                <th>过期时间 (Madrid)</th>
                                <th>处理时间 (Madrid)</th>
                                <th>处理人</th>
                            </tr>
                        </thead>
                        <tbody>
                             <?php if (empty($discarded_items)): ?>
                                <tr><td colspan="6" class="text-center">暂无“已报废”的记录。</td></tr>
                            <?php else: ?>
                                <?php foreach ($discarded_items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['store_name']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($item['material_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars(fmt_local($item['opened_at'], 'Y-m-d H:i')); ?></td>
                                        <td><?php echo htmlspecialchars(fmt_local($item['expires_at'], 'Y-m-d H:i')); ?></td>
                                        <td><?php echo htmlspecialchars(fmt_local($item['handled_at'], 'Y-m-d H:i')); ?></td>
                                        <td><?php echo htmlspecialchars($item['handler_name'] ?? '未知'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>