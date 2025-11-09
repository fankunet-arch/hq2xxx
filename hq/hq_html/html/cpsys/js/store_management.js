/**
 * Toptea HQ - cpsys
 * JavaScript for Store Management Page
 * Engineer: Gemini | Date: 2025-10-26
 * Revision: 1.3.050 (Invoice Prefix & Multi-Printer Refactor)
 */

// --- [GEMINI PRINTER_CONFIG_UPDATE] START: Global Callbacks ---
// (无变化)
window.onConfigSaveSuccess = function() {
    alert('打印机配置已成功保存到设备！');
};
window.onConfigSaveFailure = function(error) {
    alert('打印机配置保存失败！\n错误: ' + error);
};
// --- [GEMINI PRINTER_CONFIG_UPDATE] END ---


$(document).ready(function() {
    
    // --- 新的 API 网关入口 ---
    const API_GATEWAY_URL = 'api/cpsys_api_gateway.php';
    
    // --- 1. 获取所有需要的元素 ---
    const dataDrawerEl = document.getElementById('data-drawer');
    const dataDrawer = new bootstrap.Offcanvas(dataDrawerEl);
    const form = $('#data-form');
    const drawerLabel = $('#drawer-label');
    const dataIdInput = $('#data-id');

    // [MODIFIED 1.4] 打印机字段 (按前缀组织)
    const printerPrefixes = ['pr_receipt', 'pr_sticker', 'pr_kds'];
    
    // 发票确认逻辑元素
    const confirmEnableModalEl = document.getElementById('confirmEnableInvoicingModal');
    const confirmEnableModal = new bootstrap.Modal(confirmEnableModalEl);
    const confirmEnableBtn = $('#btn-confirm-enable-invoicing');
    const confirmInvoiceCheckbox = $('#confirmInvoiceCheckbox');
    const formSubmitButton = form.find('button[type="submit"]');

    /**
     * [MODIFIED 1.4] 重构 togglePrinterFields
     * @param {string} prefix (e.g., 'pr_receipt', 'pr_sticker', 'pr_kds')
     */
    function togglePrinterFields(prefix) {
        const type = $(`#${prefix}_type`).val();
        const $wifiGroup = $(`#${prefix}_wifi_group`);
        const $btGroup = $(`#${prefix}_bt_group`);
        const $usbGroup = $(`#${prefix}_usb_group`);
        
        $wifiGroup.hide();
        $btGroup.hide();
        $usbGroup.hide();

        if (type === 'WIFI') {
            $wifiGroup.show();
        } else if (type === 'BLUETOOTH') {
            $btGroup.show();
        } else if (type === 'USB') {
            $usbGroup.show();
        }
    }
    
    // [MODIFIED 1.4] 绑定三个独立的事件
    printerPrefixes.forEach(prefix => {
        $(`#${prefix}_type`).on('change', function() {
            togglePrinterFields(prefix);
        });
    });

    // (Sync button 逻辑无变化, 暂且保留)
    const syncButton = $('#btn-sync-device');


    $('#create-btn').on('click', function() {
        drawerLabel.text('创建新门店');
        form[0].reset();
        dataIdInput.val('');
        $('#is_active').prop('checked', true);
        $('#default_vat_rate').val('10.00');
        // [MODIFIED 1.4] 移除 invoice_number_offset
        $('#eod_cutoff_hour').val('3');
        
        // [MODIFIED 1.4] 重置所有打印机
        printerPrefixes.forEach(prefix => {
            $(`#${prefix}_type`).val('NONE');
            togglePrinterFields(prefix);
        });
        
        form.data('original-billing-system', 'NONE');
        $('#billing_system').val('NONE');
    });

    $('.table').on('click', '.edit-btn', function() {
        const dataId = $(this).data('id');
        drawerLabel.text('编辑门店');
        form[0].reset();
        dataIdInput.val(dataId);
        $.ajax({
            url: API_GATEWAY_URL, 
            type: 'GET', 
            data: { 
                res: 'stores',
                act: 'get',
                id: dataId 
            }, 
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const store = response.data;
                    $('#store_code').val(store.store_code);
                    $('#store_name').val(store.store_name);
                    $('#tax_id').val(store.tax_id);
                    $('#invoice_prefix').val(store.invoice_prefix); // [MODIFIED 1.4]
                    $('#billing_system').val(store.billing_system);
                    $('#default_vat_rate').val(store.default_vat_rate);
                    // [MODIFIED 1.4] 移除 invoice_number_offset
                    $('#eod_cutoff_hour').val(store.eod_cutoff_hour);
                    $('#store_city').val(store.store_city);
                    $('#is_active').prop('checked', store.is_active == 1);
                    
                    // [MODIFIED 1.4] 填充所有12个打印机字段并触发toggle
                    printerPrefixes.forEach(prefix => {
                        const printerType = store[`${prefix}_type`] || 'NONE';
                        $(`#${prefix}_type`).val(printerType);
                        $(`#${prefix}_ip`).val(store[`${prefix}_ip`]);
                        $(`#${prefix}_port`).val(store[`${prefix}_port`]);
                        $(`#${prefix}_mac`).val(store[`${prefix}_mac`]);
                        togglePrinterFields(prefix);
                    });
                    
                    form.data('original-billing-system', store.billing_system || 'NONE');
                    
                } else { alert('获取数据失败: ' + response.message); dataDrawer.hide(); }
            },
            error: function() { alert('获取数据时发生网络错误。'); dataDrawer.hide(); }
        });
    });

    /**
     * 将实际的 AJAX 提交操作封装成一个函数
     */
    function performSave() {
        formSubmitButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>保存中...');
        confirmEnableBtn.prop('disabled', true);

        // [MODIFIED 1.4] 更新 formData
        const formData = {
            id: dataIdInput.val(),
            store_code: $('#store_code').val(),
            store_name: $('#store_name').val(),
            tax_id: $('#tax_id').val(),
            invoice_prefix: $('#invoice_prefix').val(), // <--- 新增
            billing_system: $('#billing_system').val(),
            default_vat_rate: $('#default_vat_rate').val(),
            // invoice_number_offset: ... // <--- 移除
            eod_cutoff_hour: $('#eod_cutoff_hour').val(),
            store_city: $('#store_city').val(),
            is_active: $('#is_active').is(':checked') ? 1 : 0,
            
            // --- 角色 1 ---
            pr_receipt_type: $('#pr_receipt_type').val(),
            pr_receipt_ip: $('#pr_receipt_ip').val(),
            pr_receipt_port: $('#pr_receipt_port').val(),
            pr_receipt_mac: $('#pr_receipt_mac').val(),
            
            // --- 角色 2 ---
            pr_sticker_type: $('#pr_sticker_type').val(),
            pr_sticker_ip: $('#pr_sticker_ip').val(),
            pr_sticker_port: $('#pr_sticker_port').val(),
            pr_sticker_mac: $('#pr_sticker_mac').val(),

            // --- 角色 3 ---
            pr_kds_type: $('#pr_kds_type').val(),
            pr_kds_ip: $('#pr_kds_ip').val(),
            pr_kds_port: $('#pr_kds_port').val(),
            pr_kds_mac: $('#pr_kds_mac').val()
        };
        
        $.ajax({
            url: API_GATEWAY_URL, 
            type: 'POST', 
            contentType: 'application/json',
            data: JSON.stringify({ data: formData }), 
            dataType: 'json',
            beforeSend: function (xhr, settings) {
                settings.url += "?res=stores&act=save";
            },
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    dataDrawer.hide();
                    confirmEnableModal.hide();
                    window.location.reload(); // 成功后刷新
                } else {
                    alert('保存失败: ' + (response.message || '未知错误'));
                }
            },
            error: function(jqXHR) {
                if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                    alert('操作失败: ' + jqXHR.responseJSON.message);
                } else {
                    alert('保存过程中发生网络或服务器错误。');
                }
            },
            complete: function() {
                formSubmitButton.prop('disabled', false).text('保存到云端');
                confirmEnableBtn.prop('disabled', false);
            }
        });
    }

    // 2. 修改表单提交事件，使其成为一个"网关" (逻辑无变化)
    form.on('submit', function(e) {
        e.preventDefault();
        
        const originalSystem = form.data('original-billing-system') || 'NONE';
        const newSystem = $('#billing_system').val();

        if (originalSystem === 'NONE' && (newSystem === 'TICKETBAI' || newSystem === 'VERIFACTU')) {
            $('#confirmModalStoreName').text($('#store_name').val() || '新门店');
            $('#confirmModalNewSystem').text(newSystem);
            confirmEnableModal.show();
        } else {
            performSave();
        }
    });

    // 3. 为模态框的确认按钮添加点击事件 (逻辑无变化)
    confirmEnableBtn.on('click', function() {
        performSave();
    });
    
    // (模态框勾选逻辑无变化)
    $(confirmEnableModalEl).on('show.bs.modal', function() {
        confirmInvoiceCheckbox.prop('checked', false);
        confirmEnableBtn.prop('disabled', true);
    });
    confirmInvoiceCheckbox.on('change', function() {
        if ($(this).is(':checked')) {
            confirmEnableBtn.prop('disabled', false);
        } else {
            confirmEnableBtn.prop('disabled', true);
        }
    });


    // "删除" 按钮 (逻辑无变化)
    $('.table').on('click', '.delete-btn', function() {
        const dataId = $(this).data('id');
        const dataName = $(this).data('name');
        if (confirm(`您确定要删除门店 "${dataName}" 吗？`)) {
            $.ajax({
                url: API_GATEWAY_URL,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ id: dataId }), 
                dataType: 'json',
                beforeSend: function (xhr, settings) {
                    settings.url += "?res=stores&act=delete";
                },
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

    // "同步到设备" 按钮 (逻辑无变化)
    syncButton.on('click', function() {
        if (typeof window.AndroidBridge === 'undefined' || typeof window.AndroidBridge.savePrinterConfig === 'undefined') {
            alert('错误：找不到 AndroidBridge.savePrinterConfig 接口。\n请确认您是在 TopTea 安卓应用中运行此页面。');
            return;
        }
        // ... (同步逻辑无变化) ...
    });
});