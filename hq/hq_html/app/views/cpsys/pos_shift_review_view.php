<?php
/**
 * Toptea HQ - POS Shift Review View (Ghost Shift Guardian)
 * Engineer: Gemini | Date: 2025-11-04
 *
 * [A2 UTC SYNC]: Using fmt_local() for all timestamps.
 */
?>

<div class="card">
    <div class="card-header">
        异常班次复核
    </div>
    <div class="card-body">
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>待处理</strong>
            <p class="mb-0">以下班次被系统检测到未正常交接 (FORCE_CLOSED)，需要您手动复核并补全清点金额。</p>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>门店</th>
                        <th>员工</th>
                        <th>开始时间 (Madrid)</th>
                        <th>强制结束时间 (Madrid)</th>
                        <th class="text-end">系统理论现金 (Expected)</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pending_reviews)): ?>
                        <tr><td colspan="6" class="text-center">没有需要复核的异常班次。</td></tr>
                    <?php else: ?>
                        <?php foreach ($pending_reviews as $shift): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($shift['store_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($shift['user_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(fmt_local($shift['start_time'], 'Y-m-d H:i:s')); ?></td>
                                <td><?php echo htmlspecialchars(fmt_local($shift['end_time'], 'Y-m-d H:i:s')); ?></td>
                                <td class="text-end fw-bold">€ <?php echo htmlspecialchars(number_format((float)$shift['expected_cash'], 2)); ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-primary review-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#review-modal"
                                            data-shift-id="<?php echo $shift['id']; ?>"
                                            data-expected-cash="<?php echo htmlspecialchars($shift['expected_cash']); ?>"
                                            data-user-name="<?php echo htmlspecialchars($shift['user_name'] ?? 'N/A'); ?>"
                                            data-start-time="<?php echo htmlspecialchars(fmt_local($shift['start_time'], 'Y-m-d H:i:s')); ?>">
                                        <i class="bi bi-pencil-square me-1"></i> 复核
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="review-modal" tabindex="-1" aria-labelledby="review-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="review-modal-label">复核异常班次</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="review-form">
          <div class="modal-body">
            <input type="hidden" id="review-shift-id" name="shift_id">
            
            <div class="mb-3">
                <label class="form-label">员工</label>
                <input type="text" class="form-control" id="review-user-name" readonly disabled>
            </div>
            <div class="mb-3">
                <label class="form-label">班次开始时间 (Madrid)</label>
                <input type="text" class="form-control" id="review-start-time" readonly disabled>
            </div>
            <div class="mb-3">
                <label class="form-label">系统理论现金 (Expected)</label>
                <input type="number" step="0.01" class="form-control" id="review-expected-cash" readonly disabled>
            </div>
            <hr>
            <div class="mb-3">
                <label for="review-counted-cash" class="form-label">补填：实际清点现金 (Counted) <span class="text-danger">*</span></label>
                <input type="number" step="0.01" class="form-control" id="review-counted-cash" name="counted_cash" required>
                <div class="form-text">请在与门店核对后，填入该班次结束时钱箱的实际金额。</div>
            </div>
            <div class="mb-3">
                <label class="form-label">计算差异 (Difference)</label>
                <input type="text" class="form-control" id="review-cash-diff" readonly disabled style="font-weight: bold; color: #dc3545;">
            </div>
            
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
            <button type="submit" class="btn btn-primary">确认并标记为已复核</button>
          </div>
      </form>
    </div>
  </div>
</div>