<?php
/**
 * Toptea HQ - KDS SOP Query Rule Management View
 * Engineer: Gemini | Date: 2025-11-06
 * Revision: 6.0 (Template Parser Refactor)
 */
?>
<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-primary" id="create-rule-btn" data-bs-toggle="offcanvas" data-bs-target="#rule-drawer">
        <i class="bi bi-plus-circle me-2"></i>创建新解析规则
    </button>
</div>

<div class="card">
    <div class="card-header">
        KDS SOP 解析规则列表
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <h4 class="alert-heading">规则说明</h4>
            <p>此页面定义了 KDS（厨房显示器）如何解析二维码或手动输入的SOP查询码。</p>
            <ul>
                <li><b>全局规则 (store_id 为 NULL):</b> 适用于所有门店，作为后备规则。</li>
                <li><b>门店专属规则:</b> 仅适用于指定门店，且优先级高于全局规则。</li>
                <li><b>优先级:</b> 数字越小，越先被尝试解析。</li>
                <li><b>内部规则 (ID=1):</b> 是 KDS JS 依赖的默认规则 (P|A|M|T)，请勿删除或修改其映射关系（P/A/M/T）。</li>
            </ul>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>适用门店</th>
                        <th>规则名称</th>
                        <th>优先级</th>
                        <th>解析器模板 (V2)</th>
                        <th>状态</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody id="rules-table-body">
                    <tr><td colspan="6" class="text-center"><div class="spinner-border spinner-border-sm"></div> 正在加载...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="rule-drawer" aria-labelledby="drawer-label" style="width: 600px;">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="drawer-label">创建/编辑规则</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form id="rule-form">
            <input type="hidden" id="rule-id" name="id">
            <input type="hidden" id="extractor_type" name="extractor_type" value="TEMPLATE_V2"> <div class="card mb-3">
                <div class="card-header">基本信息</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="rule_name" class="form-label">规则名称 <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="rule_name" name="rule_name" placeholder="例如: 门店A扫码枪 (P|M|T)" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="store_id" class="form-label">适用门店</label>
                        <select class="form-select" id="store_id" name="store_id">
                            <option value="">[ 全局规则 ] (适用于所有门店)</option>
                            <?php foreach ($stores as $store): ?>
                                <option value="<?php echo $store['id']; ?>"><?php echo htmlspecialchars($store['store_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <label for="priority" class="form-label">优先级 <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="priority" name="priority" value="100" required>
                            <div class="form-text">数字越小，越先尝试。</div>
                        </div>
                        <div class="col-6 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" checked>
                                <label class="form-check-label" for="is_active">启用此规则</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">解析器配置 (V2 模板)</div>
                <div class="card-body">
                    
                    <div class="mb-3">
                        <label for="config_template_string" class="form-label">解析模板字符串 <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="config_template_string" name="config_template_string" placeholder="例如: {P}-{A}-{M}-{T} 或 ?p={P}&o={ORD}" required>
                        <div class="form-text">
                            输入扫码枪原始数据的格式。使用下方定义的占位符 (如 <code>{P}</code>) 来标记动态数据。
                        </div>
                    </div>
                    
                    <hr>
                    <label class="form-label">占位符定义 (Mapping)</label>
                    <div class="form-text mb-2">定义模板字符串中 <code>{...}</code> 占位符的含义。键名必须是1-3个字母。</div>
                    
                    <div class="input-group input-group-sm mb-2">
                        <span class="input-group-text" style="width: 120px;">产品 (Product)</span>
                        <input type="text" class="form-control" style="max-width: 80px;" id="map_p_key" name="map_p_key" value="P" maxlength="3">
                        <span class="input-group-text">→</span>
                        <input type="text" class="form-control" value="product_code" readonly disabled>
                    </div>
                    <div class="input-group input-group-sm mb-2">
                        <span class="input-group-text" style="width: 120px;">杯型 (Cup)</span>
                        <input type="text" class="form-control" style="max-width: 80px;" id="map_a_key" name="map_a_key" value="A" maxlength="3">
                        <span class="input-group-text">→</span>
                        <input type="text" class="form-control" value="cup_code" readonly disabled>
                    </div>
                    <div class="input-group input-group-sm mb-2">
                        <span class="input-group-text" style="width: 120px;">冰量 (Ice)</span>
                        <input type="text" class="form-control" style="max-width: 80px;" id="map_m_key" name="map_m_key" value="M" maxlength="3">
                        <span class="input-group-text">→</span>
                        <input type="text" class="form-control" value="ice_code" readonly disabled>
                    </div>
                    <div class="input-group input-group-sm mb-2">
                        <span class="input-group-text" style="width: 120px;">甜度 (Sweet)</span>
                        <input type="text" class="form-control" style="max-width: 80px;" id="map_t_key" name="map_t_key" value="T" maxlength="3">
                        <span class="input-group-text">→</span>
                        <input type="text" class="form-control" value="sweetness_code" readonly disabled>
                    </div>
                    <div class="input-group input-group-sm mb-2">
                        <span class="input-group-text" style="width: 120px;">订单号 (Order)</span>
                        <input type="text" class="form-control" style="max-width: 80px;" id="map_ord_key" name="map_ord_key" value="ORD" maxlength="3">
                        <span class="input-group-text">→</span>
                        <input type="text" class="form-control" value="order_uuid (或其它)" readonly disabled>
                    </div>

                    <div class="alert alert-secondary mt-3" id="live-example-container">
                        <small>
                            <strong>可用占位符:</strong><br>
                            <code id="live-example-p">{P}</code>
                            <code id="live-example-a" class="ms-2">{A}</code>
                            <code id="live-example-m" class="ms-2">{M}</code>
                            <code id="live-example-t" class="ms-2">{T}</code>
                            <code id="live-example-ord" class="ms-2">{ORD}</code>
                        </small>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-4">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="offcanvas">取消</button>
                <button type="submit" class="btn btn-primary">保存规则</button>
            </div>
        </form>
    </div>
</div>