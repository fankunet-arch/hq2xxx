<?php
/**
 * Toptea HQ - POS Promotion Management View
 * Engineer: Gemini | Date: 2025-10-28 | Revision: 2.1 (Correct Dark Theme Fix)
 */
// 载入菜单项（供条件/动作使用）
$all_menu_items = getAllMenuItemsForSelect($pdo);
?>
<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-primary" id="create-btn" data-bs-toggle="offcanvas" data-bs-target="#promo-drawer">
        <i class="bi bi-plus-circle me-2"></i>创建新活动
    </button>
</div>

<div class="card">
    <div class="card-header">营销活动列表</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>活动名称</th>
                        <th>触发类型 / 优惠码</th>
                        <th>有效期</th>
                        <th>状态</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($promotions)): ?>
                    <tr><td colspan="5" class="text-center">暂无营销活动。</td></tr>
                <?php else: foreach ($promotions as $p): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($p['promo_name']); ?></strong></td>
                        <td>
                            <?php if ($p['promo_trigger_type'] === 'COUPON_CODE'): ?>
                                <span class="badge text-bg-primary">优惠码</span>
                                <?php if (!empty($p['promo_code'])): // This field is not in getAllPromotions, but we keep the logic for future enhancement ?>
                                  <code class="ms-2"><?php echo htmlspecialchars($p['promo_code']); ?></code>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge text-bg-info">自动应用</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo $p['promo_start_date'] ? date('Y-m-d H:i', strtotime($p['promo_start_date'])) : '不限'; ?>
                             ~ 
                            <?php echo $p['promo_end_date'] ? date('Y-m-d H:i', strtotime($p['promo_end_date'])) : '不限'; ?>
                        </td>
                        <td>
                            <?php if ($p['promo_is_active']): ?>
                                <span class="badge text-bg-success">已启用</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">已禁用</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary me-2 edit-btn" 
                                    data-id="<?php echo $p['id']; ?>"
                                    data-bs-toggle="offcanvas" data-bs-target="#promo-drawer">编辑</button>
                            <button class="btn btn-sm btn-outline-danger delete-btn" 
                                    data-id="<?php echo $p['id']; ?>" data-name="<?php echo htmlspecialchars($p['promo_name']); ?>">删除</button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-end" style="width: 600px;" tabindex="-1" id="promo-drawer" aria-labelledby="promo-drawer-label">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="promo-drawer-label">创建/编辑活动</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <form id="promo-form">
      <input type="hidden" id="promo-id" name="id">

      <div class="card mb-3">
        <div class="card-header">基本信息</div>
        <div class="card-body">
          <div class="mb-3">
            <label for="promo_name" class="form-label">活动名称 <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="promo_name" name="promo_name" required>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="promo_priority" class="form-label">优先级</label>
              <input type="number" class="form-control" id="promo_priority" name="promo_priority" value="10">
              <div class="form-text">数字越小，优先级越高。</div>
            </div>
            <div class="col-md-6 mb-3 d-flex align-items-center pt-3">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="promo_is_active" name="promo_is_active" value="1">
                <label class="form-check-label" for="promo_is_active">启用此活动</label>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label for="promo_trigger_type" class="form-label">触发类型</label>
            <select id="promo_trigger_type" name="promo_trigger_type" class="form-select">
              <option value="AUTO_APPLY">自动应用</option>
              <option value="COUPON_CODE">优惠码</option>
            </select>
          </div>
          <div class="mb-3" id="promo-code-container" style="display:none;">
            <label for="promo_code" class="form-label">优惠码</label>
            <input type="text" class="form-control" id="promo_code" name="promo_code" placeholder="例如：WELCOME10">
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="promo_start_date" class="form-label">开始时间</label>
              <input type="datetime-local" class="form-control" id="promo_start_date" name="promo_start_date">
            </div>
            <div class="col-md-6 mb-3">
              <label for="promo_end_date" class="form-label">结束时间</label>
              <input type="datetime-local" class="form-control" id="promo_end_date">
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">规则定义 (条件与动作)</div>
        <div class="card-body">
          <h6 class="card-subtitle mb-2 text-muted">条件 (Conditions) - 满足所有条件时触发</h6>
          <div id="conditions-container" class="mb-3"></div>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="add-condition-btn">
            <i class="bi bi-plus-circle me-1"></i> 添加条件
          </button>
          <hr>
          <h6 class="card-subtitle mb-2 text-muted">动作 (Actions) - 触发后执行</h6>
          <div id="actions-container" class="mb-3"></div>
          <button type="button" class="btn btn-sm btn-outline-info" id="add-action-btn">
            <i class="bi bi-plus-circle me-1"></i> 添加动作
          </button>
        </div>
      </div>

      <div class="d-flex justify-content-end mt-4">
        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="offcanvas">取消</button>
        <button type="submit" class="btn btn-primary">保存活动</button>
      </div>
    </form>
  </div>
</div>

<div id="templates" class="d-none">
  <script id="menu-items-json" type="application/json"><?php echo json_encode($all_menu_items); ?></script>

  <div id="condition-template" class="dynamic-row border rounded mb-2">
      <div class="d-flex align-items-center p-2 border-bottom">
          <select class="form-select form-select-sm condition-type me-2">
              <option value="" selected>-- 选择条件类型 --</option>
              <option value="ITEM_QUANTITY">特定商品数量</option>
          </select>
          <button type="button" class="btn-close ms-auto remove-row-btn"></button>
      </div>
      <div class="condition-params p-2"></div>
  </div>

  <div id="action-template" class="dynamic-row border rounded mb-2">
      <div class="d-flex align-items-center p-2 border-bottom">
          <select class="form-select form-select-sm action-type me-2">
              <option value="" selected>-- 选择动作类型 --</option>
              <option value="SET_PRICE_ZERO">指定商品免费</option>
              <option value="PERCENTAGE_DISCOUNT">指定商品百分比折扣</option>
          </select>
          <button type="button" class="btn-close ms-auto remove-row-btn"></button>
      </div>
      <div class="action-params p-2"></div>
  </div>

  <div id="param-item-quantity">
      <div class="mb-2">
          <label class="form-label form-label-sm">目标商品 (可多选)</label>
          <select class="form-select multi-select-items" multiple data-param="item_ids"></select>
      </div>
      <div>
          <label class="form-label form-label-sm">最低数量</label>
          <input type="number" class="form-control" value="1" min="1" data-param="min_quantity">
      </div>
  </div>

  <div id="param-set-price-zero">
      <div class="mb-2">
          <label class="form-label form-label-sm">目标商品 (可多选)</label>
          <select class="form-select multi-select-items" multiple data-param="item_ids"></select>
      </div>
      <div>
          <label class="form-label form-label-sm">免费数量</label>
          <input type="number" class="form-control" value="1" min="1" data-param="quantity">
      </div>
      <div class="form-text">将对选定商品中价格最低的 N 个应用此优惠。</div>
  </div>

  <div id="param-percentage-discount">
      <div class="mb-2">
          <label class="form-label form-label-sm">目标商品 (可多选)</label>
          <select class="form-select multi-select-items" multiple data-param="item_ids"></select>
      </div>
      <div>
          <label class="form-label form-label-sm">折扣百分比 (%)</label>
          <input type="number" class="form-control" value="10" min="1" max="100" data-param="percentage">
          <div class="form-text">例如，输入10表示九折。</div>
      </div>
  </div>
</div>