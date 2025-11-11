/**
 * Toptea HQ - JavaScript for KDS SOP Rule Management
 * Engineer: Gemini | Date: 2025-11-06
 * Revision: 6.0 (Template Parser Refactor)
 */
$(document).ready(function() {
    
    const API_GATEWAY_URL = 'api/cpsys_api_gateway.php';
    const API_RES = 'kds_sop_rules';

    const dataDrawer = new bootstrap.Offcanvas(document.getElementById('rule-drawer'));
    const form = $('#rule-form');
    const drawerLabel = $('#drawer-label');
    const tableBody = $('#rules-table-body');
    
    const ruleIdInput = $('#rule-id');
    
    // V2 模板字段
    const configTemplateString = $('#config_template_string');
    const mapPKey = $('#map_p_key');
    const mapAKey = $('#map_a_key');
    const mapMKey = $('#map_m_key');
    const mapTKey = $('#map_t_key');
    const mapOrdKey = $('#map_ord_key');
    
    // V1 (旧) 字段 (在V2视图中已不存在，仅用于JS转换)
    // const extractorTypeSelect = $('#extractor_type');
    // const configDelimiterDiv = $('#config-delimiter');
    // const configKeyValueDiv = $('#config-key-value');


    /**
     * [V2] 更新占位符定义区域的实时示例
     */
    function updateLivePlaceholders() {
        const p = mapPKey.val() || 'P';
        const a = mapAKey.val() || 'A';
        const m = mapMKey.val() || 'M';
        const t = mapTKey.val() || 'T';
        const ord = mapOrdKey.val() || 'ORD';

        $('#live-example-p').text(`{${p}}`);
        $('#live-example-a').text(`{${a}}`);
        $('#live-example-m').text(`{${m}}`);
        $('#live-example-t').text(`{${t}}`);
        $('#live-example-ord').text(`{${ord}}`);
    }

    /**
     * 加载规则列表
     */
    function loadRules() {
        tableBody.html('<tr><td colspan="6" class="text-center"><div class="spinner-border spinner-border-sm"></div> 正在加载...</td></tr>');
        
        $.ajax({
            url: API_GATEWAY_URL,
            type: 'GET',
            data: { 
                res: API_RES,
                act: 'get_list'
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    renderRulesTable(response.data);
                } else {
                    tableBody.html(`<tr><td colspan="6" class="text-center text-danger">加载失败: ${response.message}</td></tr>`);
                }
            },
            error: function() {
                tableBody.html(`<tr><td colspan="6" class="text-center text-danger">加载失败: 网络错误。</td></tr>`);
            }
        });
    }

    /**
     * 渲染规则表格 (V2)
     */
    function renderRulesTable(rules) {
        tableBody.empty();
        if (!rules || rules.length === 0) {
            tableBody.html('<tr><td colspan="6" class="text-center">暂无解析规则。请至少创建一个。</td></tr>');
            return;
        }

        rules.forEach(rule => {
            const storeName = rule.store_name 
                ? `<span class="badge text-bg-success">${rule.store_name}</span>` 
                : `<span class="badge text-bg-info">全局规则</span>`;
            
            const status = rule.is_active
                ? `<span class="badge text-bg-success">已启用</span>`
                : `<span class="badge text-bg-secondary">已禁用</span>`;
            
            // [V2] 显示模板字符串
            let templateDisplay = '';
            try {
                const config = JSON.parse(rule.config_json || '{}');
                // 优先显示 V2 模板
                if (config.template) {
                    templateDisplay = `<code>${escapeHTML(config.template)}</code>`;
                } 
                // 否则回退显示 V1 (旧) 格式
                else if (config.format) {
                    templateDisplay = `<code class="text-muted" title="V1旧格式">${escapeHTML(config.format)} (${escapeHTML(config.separator)})</code>`;
                } else if (config.P_key) {
                    templateDisplay = `<code class="text-muted" title="V1旧格式">P=${escapeHTML(config.P_key)}</code>`;
                }
            } catch (e) {
                templateDisplay = '<span class="text-danger">JSON无效</span>';
            }


            const rowHtml = `
                <tr ${rule.id === 1 ? 'class="table-internal-rule"' : ''}>
                    <td>${storeName}</td>
                    <td>
                        <strong>${escapeHTML(rule.rule_name)}</strong>
                        ${rule.id === 1 ? '<br><small class="text-muted">(KDS 内部 JS 依赖此规则)</small>' : ''}
                    </td>
                    <td><span class="badge text-bg-secondary">${rule.priority}</span></td>
                    <td>${templateDisplay}</td>
                    <td>${status}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary edit-btn" data-id="${rule.id}" data-bs-toggle="offcanvas" data-bs-target="#rule-drawer">编辑</button>
                        <button class="btn btn-sm btn-outline-danger delete-btn" data-id="${rule.id}" data-name="${escapeHTML(rule.rule_name)}" ${rule.id === 1 ? 'disabled' : ''}>删除</button>
                    </td>
                </tr>
            `;
            tableBody.append(rowHtml);
        });
    }

    /**
     * 重置表单 (V2)
     */
    function resetForm() {
        form[0].reset();
        ruleIdInput.val('');
        drawerLabel.text('创建新解析规则');
        $('#is_active').prop('checked', true);
        $('#priority').val('100');
        
        // 恢复默认映射
        mapPKey.val('P');
        mapAKey.val('A');
        mapMKey.val('M');
        mapTKey.val('T');
        mapOrdKey.val('ORD');
        
        updateLivePlaceholders();
    }

    // --- 事件绑定 ---

    // V2: 实时更新占位符示例
    form.on('input', '#map_p_key, #map_a_key, #map_m_key, #map_t_key, #map_ord_key', updateLivePlaceholders);

    // 创建
    $('#create-rule-btn').on('click', resetForm);

    // 编辑 (V2)
    tableBody.on('click', '.edit-btn', function() {
        resetForm();
        const ruleId = $(this).data('id');
        drawerLabel.text('编辑解析规则');
        ruleIdInput.val(ruleId);

        $.ajax({
            url: API_GATEWAY_URL,
            type: 'GET',
            data: { 
                res: API_RES,
                act: 'get',
                id: ruleId
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const rule = response.data; // rule.config 已经是 V2 格式 (由 handle_kds_rule_get 转换)
                    
                    $('#rule_name').val(rule.rule_name);
                    $('#store_id').val(rule.store_id || '');
                    $('#priority').val(rule.priority);
                    $('#is_active').prop('checked', rule.is_active == 1);
                    
                    // 填充 V2 配置
                    if (rule.config && rule.config.template) {
                        configTemplateString.val(rule.config.template);
                    }
                    if (rule.config && rule.config.mapping) {
                        const m = rule.config.mapping;
                        mapPKey.val(m.p || 'P');
                        mapAKey.val(m.a || 'A');
                        mapMKey.val(m.m || 'M');
                        mapTKey.val(m.t || 'T');
                        mapOrdKey.val(m.ord || 'ORD');
                    }
                    
                    updateLivePlaceholders();
                } else {
                    alert('获取规则失败: ' + response.message);
                    dataDrawer.hide();
                }
            },
            error: function() {
                alert('获取规则时发生网络错误。');
                dataDrawer.hide();
            }
        });
    });

    // 保存 (V2)
    form.on('submit', function(e) {
        e.preventDefault();
        
        // V2 打包
        const mapping = {
            p: mapPKey.val() || null,
            a: mapAKey.val() || null,
            m: mapMKey.val() || null,
            t: mapTKey.val() || null,
            ord: mapOrdKey.val() || null
        };
        // 清理掉空的映射
        Object.keys(mapping).forEach(key => {
            if (mapping[key] === null) delete mapping[key];
        });

        const v2_config = {
            template: configTemplateString.val(),
            mapping: mapping
        };

        const formData = {
            id: ruleIdInput.val(),
            rule_name: $('#rule_name').val(),
            store_id: $('#store_id').val(),
            priority: $('#priority').val(),
            is_active: $('#is_active').is(':checked') ? 1 : 0,
            
            // V2 核心数据
            config_json: JSON.stringify(v2_config),
            // V1 兼容字段 (虽然不用了，但还是传递，API端会忽略它)
            extractor_type: $('#extractor_type').val() 
        };

        $.ajax({
            url: API_GATEWAY_URL,
            type: 'POST',
            contentType: 'application/json',
            // 注意：V2 的 save handler (handle_kds_rule_save)
            // 现在期望的 data 结构是 {id: ..., config_json: "..."}
            // 而不是 {data: { id: ..., config_... }}
            // 我们将直接发送 formData 对象
            data: JSON.stringify(formData), 
            dataType: 'json',
            beforeSend: function (xhr, settings) {
                settings.url += `?res=${API_RES}&act=save`;
            },
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    dataDrawer.hide();
                    loadRules(); // 重新加载列表
                } else {
                    alert('保存失败: ' + (response.message || '未知错误'));
                }
            },
            error: function(jqXHR) {
                const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : '保存过程中发生网络或服务器错误。';
                alert('操作失败: ' + errorMsg);
            }
        });
    });

    // 删除 (无变化)
    tableBody.on('click', '.delete-btn', function() {
        const ruleId = $(this).data('id');
        const ruleName = $(this).data('name');
        
        if (confirm(`您确定要删除规则 "${ruleName}" 吗？此操作不可撤销。`)) {
            $.ajax({
                url: API_GATEWAY_URL,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ id: ruleId }),
                dataType: 'json',
                beforeSend: function (xhr, settings) {
                    settings.url += `?res=${API_RES}&act=delete`;
                },
                success: function(response) {
                    if (response.status === 'success') {
                        alert(response.message);
                        loadRules(); // 重新加载列表
                    } else {
                        alert('删除失败: ' + response.message);
                    }
                },
                error: function() {
                    alert('删除过程中发生网络或服务器错误。');
                }
            });
        }
    });
    
    function escapeHTML(str) {
        return String(str).replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    // 初始加载
    loadRules();
});