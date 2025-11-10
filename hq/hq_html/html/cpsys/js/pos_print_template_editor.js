/**
 * TopTea HQ - JavaScript for POS Print Template Management
 * Version: 1.0.629 (API Gateway Refactor)
 * Engineer: Gemini | Date: 2025-11-04
 */
$(document).ready(function() {

    // --- 新的 API 网关入口 ---
    const API_GATEWAY_URL = 'api/cpsys_api_gateway.php';

    // --- Global Elements (Outside Offcanvas) ---
    const dataDrawerEl = document.getElementById('data-drawer');
    const dataDrawer = new bootstrap.Offcanvas(dataDrawerEl);
    const form = $('#data-form');
    const drawerLabel = $('#drawer-label');
    const dataIdInput = $('#data-id');
    const hiddenJsonInput = $('#template_content_json');
    const templates = $('#visual-editor-templates');

    let canvas, previewPane, previewPaper, btnAddLoop, physicalSizeSelect;
    let mainSortable = null;
    let loopSortables = [];
    
    // --- Mock Data (无变化) ---
    const mockData = {
        "{store_name}": "TopTea 演示门店",
        "{store_address}": "Calle Ficticia 123, 28080 Madrid",
        "{store_tax_id}": "B12345678",
        "{invoice_number}": "A-10001",
        "{issued_at}": new Date().toLocaleString('sv-SE'),
        "{cashier_name}": "Gemini Admin",
        "{qr_code}": "[QR Code Placeholder]",
        "{subtotal}": "10.00 €",
        "{discount_amount}": "1.00 €",
        "{final_total}": "9.00 €",
        "{taxable_base}": "8.18 €",
        "{vat_amount}": "0.82 €",
        "{payment_methods}": "现金 (Cash): 10.00 €",
        "{change}": "1.00 €",
        "{item_name}": "烤布蕾黑糖啵啵奶茶",
        "{item_variant}": "大杯",
        "{item_name_zh}": "烤布蕾黑糖啵啵奶茶",
        "{item_name_es}": "Té con Leche Boba Brown Sugar y Brûlée",
        "{item_variant_zh}": "大杯",
        "{item_variant_es}": "Grande",
        "{item_qty}": "1",
        "{item_unit_price}": "5.50",
        "{item_total_price}": "5.50",
        "{item_customizations}": "少冰/七分糖",
        "{report_date}": new Date().toLocaleDateString('sv-SE'),
        "{user_name}": "Gemini Admin",
        "{print_time}": new Date().toLocaleString('sv-SE'),
        "{transactions_count}": "150",
        "{system_gross_sales}": "1500.00 €",
        "{system_discounts}": "-50.00 €",
        "{system_net_sales}": "1450.00 €",
        "{system_tax}": "131.82 €",
        "{system_cash}": "800.00 €",
        "{system_card}": "650.00 €",
        "{system_platform}": "0.00 €",
        "{counted_cash}": "801.00 €",
        "{cash_discrepancy}": "1.00 € (盈余)",
        "{material_name}": "茉莉绿茶",
        "{material_name_es}": "Té Verde Jazmín",
        "{opened_at_time}": new Date().toLocaleString('sv-SE').substring(0, 16),
        "{expires_at_time}": new Date(Date.now() + 4 * 3600 * 1000).toLocaleString('sv-SE').substring(0, 16),
        "{time_left}": "4小时0分钟",
        "{operator_name}": "Gemini Admin",
        "{cup_order_number}": "A-101",
        "{remark}": "打包"
    };

    
    function initializeEditor(jsonString) {
        canvas = $('#visual-editor-canvas');
        previewPane = $('#template-preview-pane');
        previewPaper = $('#template-preview-paper');
        btnAddLoop = $('#btn-add-loop');
        physicalSizeSelect = $('#physical_size');

        const $drawer = $('#data-drawer');
        $drawer.off('click.editor').on('click.editor', '#btn-add-text', () => createRow('.visual-editor-row[data-type="text"]'));
        $drawer.on('click.editor', '#btn-add-kv', () => createRow('.visual-editor-row[data-type="kv"]'));
        $drawer.on('click.editor', '#btn-add-divider', () => createRow('.visual-editor-row[data-type="divider"]'));
        $drawer.on('click.editor', '#btn-add-feed', () => createRow('.visual-editor-row[data-type="feed"]'));
        $drawer.on('click.editor', '#btn-add-qr', () => createRow('.visual-editor-row[data-type="qr_code"]'));
        $drawer.on('click.editor', '#btn-add-cut', () => createRow('.visual-editor-row[data-type="cut"]'));
        $drawer.on('click.editor', '#btn-add-loop', () => createRow('.visual-editor-row[data-type="items_loop"]'));
        $drawer.on('click.editor', '.btn-remove-row', function() {
            $(this).closest('.visual-editor-row').remove();
            updateAll();
        });
        $drawer.off('input.editor change.editor').on('input.editor change.editor', '.prop-value, .prop-key, .prop-align, .prop-size, .prop-char, .prop-lines, .prop-bold', function() {
            updatePreviewPane();
        });
        physicalSizeSelect.off('change.editor').on('change.editor', function() {
            updatePreviewPaperSize();
        });

        renderVisualEditor(jsonString);
    }

    
    function initializeSortable() {
        if (mainSortable) mainSortable.destroy();
        loopSortables.forEach(s => s.destroy());
        loopSortables = [];
        if (!canvas || canvas.length === 0) { console.error("Editor canvas not found."); return; }
        mainSortable = new Sortable(canvas[0], {
            group: 'shared-print-group',
            animation: 150,
            handle: '.drag-handle',
            ghostClass: 'sortable-ghost',
            onEnd: updateAll,
        });
        canvas.find('.visual-editor-loop-canvas').each(function() {
            initializeLoopSortable(this);
        });
    }

    function initializeLoopSortable(element) {
        const loopSortable = new Sortable(element, {
            group: 'shared-print-group',
            animation: 150,
            handle: '.drag-handle',
            ghostClass: 'sortable-ghost',
            onEnd: updateAll,
        });
        loopSortables.push(loopSortable);
    }

    function createRow(templateId) {
        let $clone;
        if (templateId === '.visual-editor-row[data-type="items_loop"]') {
            if (btnAddLoop.prop('disabled')) return; 
            $clone = templates.find(templateId).clone();
            canvas.append($clone);
            initializeLoopSortable($clone.find('.visual-editor-loop-canvas')[0]);
        } else {
            $clone = templates.find(templateId).clone();
            canvas.append($clone);
        }
        updateAll();
    }
    
    function renderVisualEditor(jsonString) {
        if (!canvas) return;
        canvas.empty();
        let items = [];
        try { items = JSON.parse(jsonString || '[]'); } catch (e) { items = []; }
        items.forEach(item => {
            let $row;
            switch (item.type) {
                case 'text':
                    $row = templates.find('.visual-editor-row[data-type="text"]').clone();
                    $row.find('.prop-value').val(item.value || '');
                    $row.find('.prop-align').val(item.align || 'left');
                    $row.find('.prop-size').val(item.size || 'normal');
                    break;
                case 'kv':
                    $row = templates.find('.visual-editor-row[data-type="kv"]').clone();
                    $row.find('.prop-key').val(item.key || '');
                    $row.find('.prop-value').val(item.value || '');
                    $row.find('.prop-bold').prop('checked', !!item.bold_value);
                    break;
                case 'divider':
                    $row = templates.find('.visual-editor-row[data-type="divider"]').clone();
                    $row.find('.prop-char').val(item.char || '-');
                    break;
                case 'feed':
                    $row = templates.find('.visual-editor-row[data-type="feed"]').clone();
                    $row.find('.prop-lines').val(item.lines || 1);
                    break;
                case 'qr_code':
                    $row = templates.find('.visual-editor-row[data-type="qr_code"]').clone();
                    $row.find('.prop-value').val(item.value || '{qr_code}');
                    $row.find('.prop-align').val(item.align || 'center');
                    break;
                case 'cut':
                    $row = templates.find('.visual-editor-row[data-type="cut"]').clone();
                    break;
                case 'items_loop':
                    $row = templates.find('.visual-editor-row[data-type="items_loop"]').clone();
                    const $loopCanvas = $row.find('.visual-editor-loop-canvas');
                    if (item.items && Array.isArray(item.items)) {
                        item.items.forEach(loopItem => {
                            let $loopRow;
                            if (loopItem.type === 'text') {
                                $loopRow = templates.find('.visual-editor-row[data-type="text"]').clone();
                                $loopRow.find('.prop-value').val(loopItem.value || '');
                                $loopRow.find('.prop-align').val(loopItem.align || 'left');
                                $loopRow.find('.prop-size').val(loopItem.size || 'normal');
                            } else if (loopItem.type === 'kv') {
                                $loopRow = templates.find('.visual-editor-row[data-type="kv"]').clone();
                                $loopRow.find('.prop-key').val(loopItem.key || '');
                                $loopRow.find('.prop-value').val(loopItem.value || '');
                                $loopRow.find('.prop-bold').prop('checked', !!loopItem.bold_value);
                            }
                            else { $loopRow = null; }
                            if ($loopRow) $loopCanvas.append($loopRow);
                        });
                    }
                    break;
                default: $row = null;
            }
            if ($row) canvas.append($row);
        });
        initializeSortable();
        updateAll();
    }

    function buildJsonFromVisualEditor() {
        let templateData = [];
        if (!canvas) return '[]';
        canvas.children('.visual-editor-row').each(function() {
            const $row = $(this);
            const type = $row.data('type');
            let item = { type: type };
            switch (type) {
                case 'text':
                    item.value = $row.find('.prop-value').val();
                    item.align = $row.find('.prop-align').val();
                    item.size = $row.find('.prop-size').val();
                    break;
                case 'kv':
                    item.key = $row.find('.prop-key').val();
                    item.value = $row.find('.prop-value').val();
                    item.bold_value = $row.find('.prop-bold').is(':checked');
                    break;
                case 'divider':
                    item.char = $row.find('.prop-char').val();
                    break;
                case 'feed':
                    item.lines = parseInt($row.find('.prop-lines').val(), 10) || 1;
                    break;
                case 'qr_code':
                    item.value = $row.find('.prop-value').val();
                    item.align = $row.find('.prop-align').val();
                    break;
                case 'cut': break;
                case 'items_loop':
                    item.items = [];
                    $row.find('.visual-editor-loop-canvas .visual-editor-row').each(function() {
                        const $loopRow = $(this);
                        const loopType = $loopRow.data('type');
                        if (loopType === 'text' || loopType === 'kv') {
                            let loopItem = { type: loopType };
                            if (loopType === 'text') {
                                loopItem.value = $loopRow.find('.prop-value').val();
                                loopItem.align = $loopRow.find('.prop-align').val();
                                loopItem.size = $loopRow.find('.prop-size').val();
                            } else if (loopType === 'kv') {
                                loopItem.key = $loopRow.find('.prop-key').val();
                                loopItem.value = $loopRow.find('.prop-value').val();
                                loopItem.bold_value = $loopRow.find('.prop-bold').is(':checked');
                            }
                            item.items.push(loopItem);
                        }
                    });
                    break;
            }
            templateData.push(item);
        });
        return JSON.stringify(templateData);
    }

    function updatePreviewPane() {
        if (!previewPaper) return;
        let jsonString = "[]";
        try { jsonString = buildJsonFromVisualEditor(); } catch (e) {
            previewPaper.html('<pre class="text-danger">Error building JSON:\n' + e.message + '</pre>'); return;
        }
        let items = JSON.parse(jsonString);
        const renderItem = (item) => {
            let itemHtml = '';
            let value = item.value || '';
            let key = item.key || '';
            Object.keys(mockData).forEach(mockKey => {
                const regex = new RegExp(RegExp.escape(mockKey), 'g');
                value = value.replace(regex, mockData[mockKey]);
                key = key.replace(regex, mockData[mockKey]);
            });
            switch (item.type) {
                case 'text': itemHtml += `<pre class="preview-align-${item.align} preview-size-${item.size}">${escapeHtml(value)}</pre>`; break;
                case 'kv': itemHtml += `<pre class="preview-kv-row"><span class="preview-kv-key">${escapeHtml(key)}</span><span class="preview-kv-value ${item.bold_value ? 'bold' : ''}">${escapeHtml(value)}</span></pre>`; break;
                case 'divider': itemHtml += `<pre class="preview-align-center">${escapeHtml(String(item.char || '-').repeat(32))}</pre>`; break;
                case 'feed': for(let i=0; i < item.lines; i++) { itemHtml += '<br>'; } break;
                case 'qr_code': itemHtml += `<pre class="preview-align-${item.align}"><span class="preview-qr-code">[二维码: ${escapeHtml(value)}]</span></pre>`; break;
                case 'cut': itemHtml += `<pre class="preview-cut">-- (切纸) --</pre>`; break;
                case 'items_loop':
                    itemHtml += `<div class="preview-loop-box">`;
                    for(let i=0; i < 2; i++) {
                        itemHtml += `<div class="preview-loop-item">`;
                        if (i > 0) itemHtml += `<pre>${escapeHtml("-".repeat(28))}</pre>`;
                        if (item.items && Array.isArray(item.items)) {
                            item.items.forEach(loopItem => {
                                let itemToRender = loopItem;
                                if (i > 0) {
                                    itemToRender = JSON.parse(JSON.stringify(loopItem)
                                        .replace(new RegExp(RegExp.escape(mockData["{item_name}"]), 'g'), "芝芝芒芒")
                                        .replace(new RegExp(RegExp.escape(mockData["{item_customizations}"]), 'g'), "标准冰/三分糖")
                                        .replace(new RegExp(RegExp.escape(mockData["{item_total_price}"]), 'g'), "6.00")
                                        .replace(new RegExp(RegExp.escape(mockData["{item_name_zh}"]), 'g'), "芝芝芒芒")
                                        .replace(new RegExp(RegExp.escape(mockData["{item_name_es}"]), 'g'), "Mango Smoothie con Queso")
                                        .replace(new RegExp(RegExp.escape(mockData["{item_variant_zh}"]), 'g'), "中杯")
                                        .replace(new RegExp(RegExp.escape(mockData["{item_variant_es}"]), 'g'), "Mediano")
                                );
                                }
                                itemHtml += renderItem(itemToRender);
                            });
                        }
                        itemHtml += `</div>`;
                    }
                    itemHtml += `</div>`;
                    break;
            }
            return itemHtml;
        };
        const finalHtml = items.map(item => renderItem(item)).join('');
        previewPaper.html(finalHtml);
    }
    
    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }
    RegExp.escape = function(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    };
    function checkLoopButtonState() {
        if (!btnAddLoop) return;
        const loopExists = $('#visual-editor-canvas .visual-editor-loop-canvas').length > 0;
        btnAddLoop.prop('disabled', loopExists);
    }
    function updatePreviewPaperSize() {
        if (!previewPaper || !physicalSizeSelect) return;
        const selectedSize = physicalSizeSelect.val();
        let sizeClass = 'preview-paper-80mm';
        if (selectedSize) sizeClass = 'preview-paper-' + selectedSize;
        previewPaper.removeClass (function (index, className) {
            return (className.match (/(^|\s)preview-paper-\S+/g) || []).join(' ');
        });
        previewPaper.addClass(sizeClass);
    }
    function updateAll() {
        try {
            checkLoopButtonState();
            updatePreviewPaperSize();
            updatePreviewPane();
        } catch (e) {
            console.error("Error during updateAll():", e);
            if(previewPaper) {
                previewPaper.html('<pre class="text-danger">预览时发生错误:\n' + e.message + '</pre>');
            }
        }
    }

    // Handle 'Create' button click
    $('#create-btn').on('click', function() {
        drawerLabel.text('创建新模板');
        form[0].reset();
        dataIdInput.val('');
        $('#is_active').prop('checked', true);
        dataDrawerEl.addEventListener('shown.bs.offcanvas', () => {
            initializeEditor('[]'); 
            physicalSizeSelect.val('80mm').trigger('change.editor');
        }, { once: true });
    });

    // Handle 'Edit' button click
    $('.table').on('click', '.edit-btn', function() {
        const dataId = $(this).data('id');
        drawerLabel.text('编辑模板');
        form[0].reset();
        dataIdInput.val(dataId);
        
        const $canvas = $('#visual-editor-canvas');
        if ($canvas.length) $canvas.empty().html('<p class="text-muted">加载中...</p>');

        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL,
            type: 'GET',
            data: { 
                res: 'print_templates',
                act: 'get',
                id: dataId 
            },
            dataType: 'json',
            // --- END MOD ---
            success: function(response) {
                if (response.status === 'success') {
                    const tpl = response.data;
                    $('#template_name').val(tpl.template_name);
                    $('#template_type').val(tpl.template_type);
                    $('#physical_size').val(tpl.physical_size);
                    $('#is_active').prop('checked', tpl.is_active == 1);
                    const contentToLoad = tpl.template_content;
                    dataDrawerEl.addEventListener('shown.bs.offcanvas', () => {
                        initializeEditor(contentToLoad);
                    }, { once: true });
                } else {
                    alert('获取模板数据失败: ' + response.message);
                    dataDrawer.hide();
                }
            },
            error: function() {
                alert('获取数据时发生网络错误。');
                dataDrawer.hide();
            }
        });
    });

    // Handle form submission
    form.on('submit', function(e) {
        e.preventDefault();
        let contentVal;
        try {
            contentVal = buildJsonFromVisualEditor();
            hiddenJsonInput.val(contentVal);
        } catch(e) {
            alert('构建JSON时出错: ' + e.message);
            return;
        }
        const formData = {
            id: dataIdInput.val(),
            template_name: $('#template_name').val(),
            template_type: $('#template_type').val(),
            physical_size: $('#physical_size').val(),
            template_content: contentVal,
            is_active: $('#is_active').is(':checked') ? 1 : 0
        };
        if (!formData.template_type) { alert('请选择模板类型！'); return; }
        if (!formData.physical_size) { alert('请选择物理尺寸！'); return; }

        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ data: formData }),
            dataType: 'json',
            beforeSend: function (xhr, settings) {
                settings.url += "?res=print_templates&act=save";
            },
            // --- END MOD ---
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    window.location.reload();
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

    // Handle 'Delete' button click
    $('.table').on('click', '.delete-btn', function() {
        const dataId = $(this).data('id');
        const dataName = $(this).data('name');
        if (confirm(`您确定要删除模板 "${dataName}" 吗？此操作不可撤销。`)) {
            $.ajax({
                // --- MODIFIED ---
                url: API_GATEWAY_URL,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ id: dataId }),
                dataType: 'json',
                beforeSend: function (xhr, settings) {
                    settings.url += "?res=print_templates&act=delete";
                },
                // --- END MOD ---
                success: function(response) {
                    if (response.status === 'success') {
                        alert(response.message);
                        window.location.reload();
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

    // Handle Offcanvas closing
    dataDrawerEl.addEventListener('hidden.bs.offcanvas', () => {
        if (mainSortable) mainSortable.destroy();
        loopSortables.forEach(s => s.destroy());
        loopSortables = [];
        mainSortable = null;
        $('#data-drawer').off('.editor');
        if (physicalSizeSelect) physicalSizeSelect.off('.editor');
        canvas = null;
        previewPane = null;
        previewPaper = null;
        btnAddLoop = null;
        physicalSizeSelect = null;
        const $canvas = $('#visual-editor-canvas');
        if ($canvas.length) $canvas.empty();
        const $preview = $('#template-preview-paper');
        if ($preview.length) $preview.empty();
    });
});