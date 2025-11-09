/**
 * TopTea HQ - JavaScript for POS Member Level Management
 * Engineer: Gemini | Date: 2025-10-28
 * Revision: 1.0.001 (API Gateway Refactor)
 */
$(document).ready(function() {

    // --- 新的 API 网关入口 ---
    const API_GATEWAY_URL = 'api/cpsys_api_gateway.php';

    const dataDrawer = new bootstrap.Offcanvas(document.getElementById('data-drawer'));
    const form = $('#data-form');
    const drawerLabel = $('#drawer-label');
    const dataIdInput = $('#data-id');

    // Handle 'Create' button click
    $('#create-btn').on('click', function() {
        drawerLabel.text('创建新会员等级');
        form[0].reset();
        dataIdInput.val('');
    });

    // Handle 'Edit' button click (using event delegation)
    $('.table').on('click', '.edit-btn', function() {
        const dataId = $(this).data('id');
        drawerLabel.text('编辑会员等级');
        form[0].reset();
        dataIdInput.val(dataId);

        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL,
            type: 'GET',
            data: { 
                res: 'pos_member_levels',
                act: 'get',
                id: dataId 
            },
            dataType: 'json',
            // --- END MOD ---
            success: function(response) {
                if (response.status === 'success') {
                    const level = response.data;
                    $('#level_name_zh').val(level.level_name_zh);
                    $('#level_name_es').val(level.level_name_es);
                    $('#points_threshold').val(level.points_threshold);
                    $('#level_up_promo_id').val(level.level_up_promo_id);
                    $('#sort_order').val(level.sort_order);
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

    // Handle form submission for both Create and Update
    form.on('submit', function(e) {
        e.preventDefault();
        const formData = {
            id: dataIdInput.val(),
            level_name_zh: $('#level_name_zh').val(),
            level_name_es: $('#level_name_es').val(),
            points_threshold: $('#points_threshold').val(),
            level_up_promo_id: $('#level_up_promo_id').val(),
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
                settings.url += "?res=pos_member_levels&act=save";
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

    // Handle 'Delete' button click (using event delegation)
    $('.table').on('click', '.delete-btn', function() {
        const dataId = $(this).data('id');
        const dataName = $(this).data('name');
        if (confirm(`您确定要删除等级 "${dataName}" 吗？\n警告：此操作不可撤销。`)) {
            $.ajax({
                // --- MODIFIED ---
                url: API_GATEWAY_URL,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ id: dataId }),
                dataType: 'json',
                beforeSend: function (xhr, settings) {
                    settings.url += "?res=pos_member_levels&act=delete";
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