/**
 * Toptea HQ - cpsys
 * JavaScript for User Management Page
 *
 * Engineer: Gemini
 * Date: 2025-10-23
 * Revision: 2.0 (API Gateway Refactor)
 */
$(document).ready(function() {
    
    // --- 新的 API 网关入口 ---
    const API_GATEWAY_URL = 'api/cpsys_api_gateway.php';

    const dataDrawer = new bootstrap.Offcanvas(document.getElementById('data-drawer'));
    const form = $('#data-form');
    const drawerLabel = $('#drawer-label');
    const dataIdInput = $('#data-id');
    const usernameInput = $('#username');

    // --- Reset form for CREATE ---
    $('#create-btn').on('click', function() {
        drawerLabel.text('创建新用户');
        form[0].reset();
        dataIdInput.val('');
        usernameInput.prop('readonly', false); // Allow editing username for new users
        $('#is_active').prop('checked', true); // Default to active
    });

    // --- Fetch data for EDIT ---
    $('.table').on('click', '.edit-btn', function() {
        const dataId = $(this).data('id');
        drawerLabel.text('编辑用户');
        form[0].reset();
        dataIdInput.val(dataId);
        usernameInput.prop('readonly', true); // Prevent changing username

        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL,
            type: 'GET',
            data: { 
                res: 'users',
                act: 'get',
                id: dataId 
            },
            dataType: 'json',
            // --- END MOD ---
            success: function(response) {
                if (response.status === 'success') {
                    const user = response.data;
                    usernameInput.val(user.username);
                    $('#display_name').val(user.display_name);
                    $('#email').val(user.email);
                    $('#role_id').val(user.role_id);
                    $('#is_active').prop('checked', user.is_active == 1);
                } else {
                    alert('获取用户数据失败: ' + response.message);
                    dataDrawer.hide();
                }
            },
            error: function() {
                alert('获取用户数据时发生网络错误。');
                dataDrawer.hide();
            }
        });
    });

    // --- Handle form submission for CREATE and UPDATE ---
    form.on('submit', function(e) {
        e.preventDefault();
        
        const password = $('#password').val();
        const passwordConfirm = $('#password_confirm').val();

        if (password !== passwordConfirm) {
            alert('两次输入的密码不匹配！');
            return;
        }

        const formData = {
            id: dataIdInput.val(),
            username: usernameInput.val(),
            display_name: $('#display_name').val(),
            email: $('#email').val(),
            password: password, // Send password, backend will handle if it's empty
            role_id: $('#role_id').val(),
            is_active: $('#is_active').is(':checked') ? 1 : 0
        };

        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ data: formData }), // { action: 'save', data: formData }
            dataType: 'json',
            beforeSend: function (xhr, settings) {
                settings.url += "?res=users&act=save";
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
    
    // --- Handle soft DELETE ---
    $('.table').on('click', '.delete-btn', function() {
        const dataId = $(this).data('id');
        const dataName = $(this).data('name');

        if (confirm(`您确定要删除用户 "${dataName}" 吗？`)) {
            $.ajax({
                // --- MODIFIED ---
                url: API_GATEWAY_URL,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ id: dataId }), // { action: 'delete', id: dataId }
                dataType: 'json',
                beforeSend: function (xhr, settings) {
                    settings.url += "?res=users&act=delete";
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