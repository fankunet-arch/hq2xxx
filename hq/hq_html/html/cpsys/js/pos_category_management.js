/**
 * TopTea BMS - JavaScript for POS Category Management
 * Engineer: Gemini | Date: 2025-10-26
 * Revision: 1.0.001 (API Gateway Refactor)
 */
$(document).ready(function() {
    
    // --- 新的 API 网关入口 ---
    const API_GATEWAY_URL = 'api/cpsys_api_gateway.php';

    const dataDrawer = new bootstrap.Offcanvas(document.getElementById('data-drawer'));
    const form = $('#data-form');
    const drawerLabel = $('#drawer-label');
    const dataIdInput = $('#data-id');
    const codeInput = $('#category_code');

    $('#create-btn').on('click', function() {
        drawerLabel.text('创建新POS分类');
        form[0].reset();
        dataIdInput.val('');
        codeInput.prop('readonly', false);
    });

    $('.table').on('click', '.edit-btn', function() {
        const dataId = $(this).data('id');
        drawerLabel.text('编辑POS分类');
        form[0].reset();
        dataIdInput.val(dataId);
        codeInput.prop('readonly', true); // Prevent changing the key

        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL,
            type: 'GET',
            data: { 
                res: 'pos_categories',
                act: 'get',
                id: dataId 
            },
            dataType: 'json',
            // --- END MOD ---
            success: function(response) {
                if (response.status === 'success') {
                    const cat = response.data;
                    codeInput.val(cat.category_code);
                    $('#name_zh').val(cat.name_zh);
                    $('#name_es').val(cat.name_es);
                    $('#sort_order').val(cat.sort_order);
                } else {
                    alert('获取数据失败: ' + response.message);
                    dataDrawer.hide();
                }
            },
            error: function() {
                alert('获取数据时发生网络错误。');
                dataDrawer.hide();
            }
        });
    });

    form.on('submit', function(e) {
        e.preventDefault();
        const formData = {
            id: dataIdInput.val(),
            category_code: codeInput.val(),
            name_zh: $('#name_zh').val(),
            name_es: $('#name_es').val(),
            sort_order: $('#sort_order').val()
        };
        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ data: formData }),
            dataType: 'json',
            beforeSend: function (xhr, settings) {
                settings.url += "?res=pos_categories&act=save";
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

    $('.table').on('click', '.delete-btn', function() {
        const dataId = $(this).data('id');
        const dataName = $(this).data('name');
        if (confirm(`您确定要删除分类 "${dataName}" 吗？`)) {
            $.ajax({
                // --- MODIFIED ---
                url: API_GATEWAY_URL,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ id: dataId }),
                dataType: 'json',
                beforeSend: function (xhr, settings) {
                    settings.url += "?res=pos_categories&act=delete";
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