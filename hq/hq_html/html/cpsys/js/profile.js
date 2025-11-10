/**
 * Toptea HQ - cpsys
 * JavaScript for User Profile Page
 * Engineer: Gemini | Date: 2025-10-23
 * Revision: 2.0 (API Gateway Refactor)
 */
$(document).ready(function() {

    // --- 新的 API 网关入口 ---
    const API_GATEWAY_URL = 'api/cpsys_api_gateway.php';

    $('#profile-form').on('submit', function(e) {
        e.preventDefault();

        const newPassword = $('#new_password').val();
        const confirmPassword = $('#confirm_password').val();

        if (newPassword !== confirmPassword) {
            alert('新密码和确认密码不匹配！');
            return;
        }

        const formData = {
            display_name: $('#display_name').val(),
            email: $('#email').val(),
            current_password: $('#current_password').val(),
            new_password: newPassword
        };

        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData), // 载荷不变
            dataType: 'json',
            beforeSend: function (xhr, settings) {
                settings.url += "?res=profile&act=save";
            },
            // --- END MOD ---
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    window.location.reload(); // Reload to see changes reflected in header
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
});