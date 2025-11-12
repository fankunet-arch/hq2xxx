/**
 * TopTea HQ - JavaScript for POS Tag Management (Seasons Pass Whitelist)
 * Engineer: Gemini | Date: 2025-11-12
 * [R2.1] Implements: 3.1 CPSYS-RMS (R2)
 */
$(document).ready(function() {

    const API_GATEWAY_URL = 'api/cpsys_api_gateway.php';
    const API_RES = 'pos_tags';

    const dataDrawer = new bootstrap.Offcanvas(document.getElementById('data-drawer'));
    const form = $('#data-form');
    const drawerLabel = $('#drawer-label');
    const dataIdInput = $('#data-id');
    const codeInput = $('#tag_code');

    $('#create-btn').on('click', function() {
        drawerLabel.text('创建新标签');
        form[0].reset();
        dataIdInput.val('');
        codeInput.prop('readonly', false);
    });

    $('.table').on('click', '.edit-btn', function() {
        const dataId = $(this).data('id');
        drawerLabel.text('编辑标签');
        form[0].reset();
        dataIdInput.val(dataId);
        codeInput.prop('readonly', true); // Prevent changing the key

        $.ajax({
            url: API_GATEWAY_URL,
            type: 'GET',
            data: { 
                res: API_RES,
                act: 'get',
                id: dataId 
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const tag = response.data;
                    codeInput.val(tag.tag_code);
                    $('#tag_name').val(tag.tag_name);
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
            tag_code: codeInput.val(),
            tag_name: $('#tag_name').val()
        };
        $.ajax({
            url: API_GATEWAY_URL,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ data: formData }),
            dataType: 'json',
            beforeSend: function (xhr, settings) {
                settings.url += `?res=${API_RES}&act=save`;
            },
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
        if (confirm(`您确定要删除标签 "${dataName}" 吗？\n警告：如果标签正在被商品使用，删除可能会失败或导致关联失效。`)) {
            $.ajax({
                url: API_GATEWAY_URL,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ id: dataId }),
                dataType: 'json',
                beforeSend: function (xhr, settings) {
                    settings.url += `?res=${API_RES}&act=delete`;
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
});