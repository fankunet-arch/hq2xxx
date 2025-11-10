/**
 * Toptea HQ - cpsys
 * JavaScript for Stock Allocation Page
 *
 * Engineer: Gemini
 * Date: 2025-10-26
 * Revision: 1.9.0 (3-Level-Unit Support)
 */
$(document).ready(function() {

    // --- 新的 API 网关入口 ---
    const API_GATEWAY_URL = 'api/cpsys_api_gateway.php';

    const allocationModal = new bootstrap.Modal(document.getElementById('allocation-modal'));
    const form = $('#allocation-form');
    const storeIdInput = $('#store-id-input');
    const storeNameDisplay = $('#store-name-display');
    const materialSelect = $('#material-id-select');
    const quantityInput = $('#quantity-input');
    const unitSelect = $('#unit-id-select');

    $('.allocate-btn').on('click', function() {
        const storeId = $(this).data('store-id');
        const storeName = $(this).data('store-name');

        form[0].reset();
        storeIdInput.val(storeId);
        storeNameDisplay.val(storeName);
        unitSelect.empty().append('<option value="" selected disabled>-- 请先选择物料 --</option>');
    });

    materialSelect.on('change', function() {
        const materialId = $(this).val();
        if (!materialId) {
            unitSelect.empty().append('<option value="" selected disabled>-- 请先选择物料 --</option>');
            return;
        }

        unitSelect.empty().append('<option value="" selected disabled>加载中...</option>');
        $.ajax({
            // --- MODIFIED ---
            // (依赖 'materials' 资源)
            url: API_GATEWAY_URL,
            type: 'GET',
            data: { 
                res: 'materials',
                act: 'get',
                id: materialId 
            },
            dataType: 'json',
            // --- END MOD ---
            success: function(response) {
                if (response.status === 'success') {
                    const material = response.data;
                    unitSelect.empty();
                    // [MODIFIED] 3-Level-Unit Support
                    if (material.base_unit_id && material.base_unit_name) {
                        unitSelect.append(`<option value="${material.base_unit_id}">${material.base_unit_name}</option>`);
                    }
                    if (material.medium_unit_id && material.medium_unit_name) {
                        unitSelect.append(`<option value="${material.medium_unit_id}">${material.medium_unit_name}</option>`);
                    }
                    if (material.large_unit_id && material.large_unit_name) {
                        unitSelect.append(`<option value="${material.large_unit_id}">${material.large_unit_name}</option>`);
                    }
                    // [END MOD]
                } else {
                    unitSelect.empty().append('<option value="" selected disabled>获取单位失败</option>');
                }
            },
            error: function() {
                unitSelect.empty().append('<option value="" selected disabled>网络错误</option>');
            }
        });
    });

    form.on('submit', function(e) {
        e.preventDefault();
        const allocationData = {
            store_id: storeIdInput.val(),
            material_id: materialSelect.val(),
            quantity: quantityInput.val(),
            unit_id: unitSelect.val()
        };

        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ 
                action: 'allocate_to_store', // 旧 handler 兼容
                data: allocationData 
            }),
            dataType: 'json',
            beforeSend: function (xhr, settings) {
                // act=allocate_to_store 在 registry 中被路由
                settings.url += "?res=stock&act=allocate_to_store";
            },
            // --- END MOD ---
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    window.location.reload();
                } else {
                    alert('调拨失败: ' + (response.message || '未知错误'));
                }
            },
            error: function(jqXHR) {
                if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                    alert('操作失败: ' + jqXHR.responseJSON.message);
                } else {
                    alert('调拨过程中发生网络或服务器错误。');
                }
            }
        });
    });
});