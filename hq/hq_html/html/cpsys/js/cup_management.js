/**
 * Toptea HQ - cpsys
 * JavaScript for Cup Management Page
 *
 * Engineer: Gemini
 * Date: 2025-10-25
 * Revision: 7.0 (API Gateway Refactor)
 */
$(document).ready(function() {
    
    // --- 新的 API 网关入口 ---
    const API_GATEWAY_URL = 'api/cpsys_api_gateway.php';
    
    const cupDrawer = new bootstrap.Offcanvas(document.getElementById('cup-drawer'));
    const form = $('#cup-form');
    const drawerLabel = $('#drawer-label');
    const cupIdInput = $('#cup-id');
    const cupCodeInput = $('#cup-code');
    const cupNameInput = $('#cup-name');
    const cupSopZhInput = $('#cup-sop-zh');
    const cupSopEsInput = $('#cup-sop-es');

    $('#create-cup-btn').on('click', function() {
        drawerLabel.text('创建新杯型');
        form[0].reset();
        cupIdInput.val('');
        // 'get_next_code' 未在 registry 中为 cups 实现，暂时移除
    });

    $('.table').on('click', '.edit-cup-btn', function() {
        const cupId = $(this).data('cup-id');
        drawerLabel.text('编辑杯型');
        form[0].reset();
        cupIdInput.val(cupId);

        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL,
            type: 'GET',
            data: { 
                res: 'cups',
                act: 'get',
                id: cupId 
            },
            dataType: 'json',
            // --- END MOD ---
            success: function(response) {
                if (response.status === 'success') {
                    const data = response.data;
                    cupCodeInput.val(data.cup_code);
                    cupNameInput.val(data.cup_name);
                    cupSopZhInput.val(data.sop_description_zh);
                    cupSopEsInput.val(data.sop_description_es);
                } else {
                    alert('获取杯型数据失败: ' + response.message);
                    cupDrawer.hide();
                }
            },
            error: function() {
                alert('获取杯型数据时发生网络错误。');
                cupDrawer.hide();
            }
        });
    });

    form.on('submit', function(e) {
        e.preventDefault();

        const cupData = {
            id: cupIdInput.val(),
            cup_code: cupCodeInput.val(),
            cup_name: cupNameInput.val(),
            sop_zh: cupSopZhInput.val(),
            sop_es: cupSopEsInput.val()
        };

        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ data: cupData }),
            dataType: 'json',
            beforeSend: function (xhr, settings) {
                settings.url += "?res=cups&act=save";
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
    
    $('.table').on('click', '.delete-cup-btn', function() {
        const cupId = $(this).data('cup-id');
        const cupName = $(this).data('cup-name');

        if (confirm(`您确定要删除杯型 "${cupName}" 吗？`)) {
            $.ajax({
                // --- MODIFIED ---
                url: API_GATEWAY_URL,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ id: cupId }),
                dataType: 'json',
                beforeSend: function (xhr, settings) {
                    settings.url += "?res=cups&act=delete";
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