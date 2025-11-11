<?php
/**
 * Toptea HQ - POS Member Settings View
 * Engineer: Gemini | Date: 2025-10-28
 */
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <form id="member-settings-form">
            <div class="card mb-4">
                <div class="card-header">
                    积分赚取规则
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="euros_per_point" class="form-label">每获得 1 积分需要消费的欧元金额</label>
                        <div class="input-group">
                            <span class="input-group-text">€</span>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="euros_per_point" name="points_euros_per_point" required>
                             <span class="input-group-text">= 1 积分</span>
                        </div>
                        <div class="form-text">例如：输入 `1.00` 表示消费 1 欧元获得 1 积分。输入 `0.50` 表示消费 0.5 欧元获得 1 积分。</div>
                    </div>
                </div>
            </div>

            <!-- Placeholder for future settings -->
            <!--
            <div class="card mb-4">
                <div class="card-header">
                    积分兑换设置 (待开发)
                </div>
                <div class="card-body">
                     <p class="text-muted">积分兑换优惠券等功能将在此配置。</p>
                </div>
            </div>
            -->

            <div class="d-flex justify-content-end mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>保存设置</button>
            </div>
        </form>
        <div id="settings-feedback" class="mt-3"></div>
    </div>
</div>
