/**
 * Toptea HQ - cpsys
 * JavaScript for Unit Management Page (Bilingual Template)
 *
 * Engineer: Gemini
 * Date: 2025-10-23
 *
 * [REFACTOR V1.0 - 2025-11-04]
 * - Pointed all API calls to 'cpsys_api_gateway.php'
 * - Standardized 'data' wrapper for save actions
 */
$(document).ready(function() {
    
    // --- 新的 API 网关入口 ---
    const API_GATEWAY_URL = 'api/cpsys_api_gateway.php';

    const unitDrawer = new bootstrap.Offcanvas(document.getElementById('unit-drawer'));
    const form = $('#unit-form');
    const drawerLabel = $('#drawer-label');
    const unitIdInput = $('#unit-id');
    const unitCodeInput = $('#unit-code');
    const unitNameZhInput = $('#unit-name-zh');
    const unitNameEsInput = $('#unit-name-es');

    $('#create-unit-btn').on('click', function() {
        drawerLabel.text('创建新单位');
        form[0].reset();
        unitIdInput.val('');
        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL, 
            type: 'GET', 
            data: { 
                res: 'units',
                act: 'get_next_code' 
            }, 
            dataType: 'json',
            // --- END MOD ---
            success: function(response) { 
                if (response.status === 'success') { 
                    unitCodeInput.val(response.data.next_code); 
                } 
            }
        });
    });

    $('.table').on('click', '.edit-unit-btn', function() {
        const unitId = $(this).data('unit-id');
        drawerLabel.text('编辑单位');
        form[0].reset();
        unitIdInput.val(unitId);
        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL, 
            type: 'GET', 
            data: {
                res: 'units',
                act: 'get',
                id: unitId 
            }, 
            dataType: 'json',
            // --- END MOD ---
            success: function(response) {
                if (response.status === 'success') {
                    // (旧 handler 的 data key 是 unit_code, name_zh, name_es)
                    // (新 kds_helper getUnitById 的 key 也是 unit_code, name_zh, name_es)
                    // (新 handle_unit_get 包装了 getUnitById)
                    // 字段保持一致，无需修改
                    unitCodeInput.val(response.data.unit_code);
                    unitNameZhInput.val(response.data.name_zh);
                    unitNameEsInput.val(response.data.name_es);
                } else { 
                    alert('获取单位数据失败: ' + response.message); 
                    unitDrawer.hide(); 
                }
            },
            error: function() { 
                alert('获取单位数据时发生网络错误。'); 
                unitDrawer.hide(); 
            }
        });
    });

    form.on('submit', function(e) {
        e.preventDefault();
        const unitData = { 
            id: unitIdInput.val(), 
            unit_code: unitCodeInput.val(), 
            name_zh: unitNameZhInput.val(), 
            name_es: unitNameEsInput.val() 
        };
        
        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL, 
            type: 'POST', 
            contentType: 'application/json',
            data: JSON.stringify({ 
                // 旧 handler: { action: 'save', data: unitData }
                // 新网关 (自定义动作): { data: unitData } (act 在 query)
                data: unitData 
            }), 
            dataType: 'json',
            // 构造 query string
            beforeSend: function (xhr, settings) {
                settings.url += "?res=units&act=save";
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
    
    $('.table').on('click', '.delete-unit-btn', function() {
        const unitId = $(this).data('unit-id');
        const unitName = $(this).data('unit-name');
        if (confirm(`您确定要删除单位 "${unitName}" 吗？`)) {
            $.ajax({
                // --- MODIFIED ---
                url: API_GATEWAY_URL, 
                type: 'POST', 
                contentType: 'application/json',
                data: JSON.stringify({ 
                    id: unitId // 旧 handler: { action: 'delete', id: unitId }
                }), 
                dataType: 'json',
                // 构造 query string
                beforeSend: function (xhr, settings) {
                    settings.url += "?res=units&act=delete";
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