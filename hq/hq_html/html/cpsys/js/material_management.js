/**
 * Toptea HQ - cpsys
 * JavaScript for Material Management Page
 *
 * Engineer: Gemini
 * Date: 2025-10-26
 * Revision: 1.2.010 (3-Level-Unit Support)
 */
$(document).ready(function() {
    
    // --- 新的 API 网关入口 ---
    const API_GATEWAY_URL = 'api/cpsys_api_gateway.php';
    
    // --- START: Search/Filter Logic (无变化) ---
    const searchInput = $('#material-search-input');
    const typeFilter = $('#material-type-filter');
    const tableBody = $('#materials-table-body');
    const noDataRow = $('#no-matching-row');

    function filterMaterials() {
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
    searchInput.on('keyup', filterMaterials);
    typeFilter.on('change', filterMaterials);
    // --- END: Search/Filter Logic ---


    const materialDrawer = new bootstrap.Offcanvas(document.getElementById('material-drawer'));
    const form = $('#material-form');
    const drawerLabel = $('#drawer-label');
    const materialIdInput = $('#material-id');
    const materialCodeInput = $('#material-code');
    const materialTypeInput = $('#material-type');
    const materialNameZhInput = $('#material-name-zh');
    const materialNameEsInput = $('#material-name-es');
    const imageUrlInput = $('#image-url'); // [KDS Image] Added
    const baseUnitIdInput = $('#base-unit-id');
    // [MODIFIED]
    const mediumUnitIdInput = $('#medium-unit-id');
    const mediumConversionRateInput = $('#medium-conversion-rate');
    const largeUnitIdInput = $('#large-unit-id');
    const largeConversionRateInput = $('#large-conversion-rate');
    // [END MOD]
    const expiryRuleTypeInput = $('#expiry-rule-type');
    const expiryDurationInput = $('#expiry-duration');
    const expiryDurationWrapper = $('#expiry-duration-wrapper');
    const expiryDurationText = $('#expiry-duration-text');

    expiryRuleTypeInput.on('change', function() {
        const selectedType = $(this).val();
        if (selectedType === 'HOURS' || selectedType === 'DAYS') {
            expiryDurationWrapper.show();
            expiryDurationText.text(selectedType === 'HOURS' ? '单位: 小时' : '单位: 天');
        } else {
            expiryDurationWrapper.hide();
            expiryDurationInput.val('');
        }
    });

    $('#create-material-btn').on('click', function() {
        drawerLabel.text('创建新物料');
        form[0].reset();
        materialIdInput.val('');
        materialTypeInput.val('SEMI_FINISHED');
        expiryRuleTypeInput.trigger('change');
        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL,
            type: 'GET',
            data: { 
                res: 'materials',
                act: 'get_next_code' 
            },
            dataType: 'json',
            // --- END MOD ---
            success: function(response) {
                if (response.status === 'success') {
                    materialCodeInput.val(response.data.next_code);
                }
            }
        });
    });

    $('.table').on('click', '.edit-material-btn', function() {
        const materialId = $(this).data('material-id');
        drawerLabel.text('编辑物料');
        form[0].reset();
        materialIdInput.val(materialId);

        $.ajax({
            // --- MODIFIED ---
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
                    const data = response.data;
                    materialCodeInput.val(data.material_code);
                    materialTypeInput.val(data.material_type);
                    materialNameZhInput.val(data.name_zh);
                    materialNameEsInput.val(data.name_es);
                    imageUrlInput.val(data.image_url); // [KDS Image] Added
                    baseUnitIdInput.val(data.base_unit_id);
                    // [MODIFIED]
                    mediumUnitIdInput.val(data.medium_unit_id);
                    mediumConversionRateInput.val(data.medium_conversion_rate);
                    largeUnitIdInput.val(data.large_unit_id);
                    largeConversionRateInput.val(data.large_conversion_rate);
                    // [END MOD]
                    expiryRuleTypeInput.val(data.expiry_rule_type);
                    expiryDurationInput.val(data.expiry_duration);
                    expiryRuleTypeInput.trigger('change');
                } else {
                    alert('获取物料数据失败: ' + response.message);
                    materialDrawer.hide();
                }
            },
            error: function() {
                alert('获取物料数据时发生网络错误。');
                materialDrawer.hide();
            }
        });
    });

    form.on('submit', function(e) {
        e.preventDefault();
        const materialData = {
            id: materialIdInput.val(),
            material_code: materialCodeInput.val(),
            material_type: materialTypeInput.val(),
            name_zh: materialNameZhInput.val(),
            name_es: materialNameEsInput.val(),
            image_url: imageUrlInput.val(), // [KDS Image] Added
            base_unit_id: baseUnitIdInput.val(),
            // [MODIFIED]
            medium_unit_id: mediumUnitIdInput.val(),
            medium_conversion_rate: mediumConversionRateInput.val(),
            large_unit_id: largeUnitIdInput.val(),
            large_conversion_rate: largeConversionRateInput.val(),
            // [END MOD]
            expiry_rule_type: expiryRuleTypeInput.val(),
            expiry_duration: expiryDurationInput.val()
        };

        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ data: materialData }),
            dataType: 'json',
            beforeSend: function (xhr, settings) {
                settings.url += "?res=materials&act=save";
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
                if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                    alert('操作失败: ' + jqXHR.responseJSON.message);
                } else {
                    alert('保存过程中发生网络或服务器错误。');
                }
            }
        });
    });
    
    $('.table').on('click', '.delete-material-btn', function() {
        const materialId = $(this).data('material-id');
        const materialName = $(this).data('material-name');

        if (confirm(`您确定要删除物料 "${materialName}" 吗？`)) {
            $.ajax({
                // --- MODIFIED ---
                url: API_GATEWAY_URL,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ id: materialId }),
                dataType: 'json',
                beforeSend: function (xhr, settings) {
                    settings.url += "?res=materials&act=delete";
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
});