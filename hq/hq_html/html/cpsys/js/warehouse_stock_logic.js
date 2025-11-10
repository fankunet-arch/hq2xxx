/**
 * Toptea HQ - cpsys
 * JavaScript for Warehouse Stock Management Page
 *
 * Engineer: Gemini
 * Date: 2025-10-26
 * Revision: 2.0.0 (3-Level-Unit Support)
 */
$(document).ready(function() {

    // --- 新的 API 网关入口 ---
    const API_GATEWAY_URL = 'api/cpsys_api_gateway.php';

    // --- START: Search/Filter Logic (无变化) ---
    const searchInput = $('#stock-search-input');
    const typeFilter = $('#stock-type-filter');
    const tableBody = $('#stock-table-body');
    const noDataRow = $('#no-matching-row');

    function filterStock() {
        const searchTerm = searchInput.val().toLowerCase();
        const filterType = typeFilter.val();
        let hasVisibleRows = false;
        tableBody.find('tr[data-name]').each(function() {
            const $row = $(this);
            const name = $row.data('name') || '';
            const type = $row.data('type') || '';
            const nameMatch = name.includes(searchTerm);
            const typeMatch = (filterType === 'ALL' || filterType === type);
            if (nameMatch && typeMatch) {
                $row.show();
                hasVisibleRows = true;
            } else {
                $row.hide();
            }
        });
        if (hasVisibleRows) {
            noDataRow.hide();
        } else {
            if (tableBody.find('tr[data-name]').length > 0) {
                noDataRow.show();
            }
        }
    }
    searchInput.on('keyup', filterStock);
    typeFilter.on('change', filterStock);
    // --- END: Search/Filter Logic ---


    const addStockModal = new bootstrap.Modal(document.getElementById('add-stock-modal'));
    const form = $('#add-stock-form');
    const materialIdInput = $('#material-id-input');
    const materialNameDisplay = $('#material-name-display');
    const quantityInput = $('#quantity-input');
    const unitSelect = $('#unit-id-select');

    $('.add-stock-btn').on('click', function() {
        const materialId = $(this).data('material-id');
        const materialName = $(this).data('material-name');

        form[0].reset();
        materialIdInput.val(materialId);
        materialNameDisplay.val(materialName);
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
                     alert('获取物料单位信息失败: ' + response.message);
                     addStockModal.hide();
                }
            },
            error: function() {
                alert('获取物料单位信息时发生网络错误。');
                addStockModal.hide();
            }
        });
    });

    form.on('submit', function(e) {
        e.preventDefault();

        const stockData = {
            material_id: materialIdInput.val(),
            quantity: quantityInput.val(),
            unit_id: unitSelect.val()
        };

        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ 
                action: 'add_warehouse_stock', // 旧 handler 兼容
                data: stockData 
            }),
            dataType: 'json',
            beforeSend: function (xhr, settings) {
                // act=add_warehouse_stock 在 registry 中被路由
                settings.url += "?res=stock&act=add_warehouse_stock"; 
            },
            // --- END MOD ---
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    window.location.reload();
                } else {
                    alert('入库失败: ' + (response.message || '未知错误'));
                }
            },
            error: function(jqXHR) {
                if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                    alert('操作失败: ' + jqXHR.responseJSON.message);
                } else {
                    alert('入库过程中发生网络或服务器错误。');
                }
            }
        });
    });
});