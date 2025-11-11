/**
 * TopTea HQ - JavaScript for POS Member Management
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
        drawerLabel.text('创建新会员');
        form[0].reset();
        dataIdInput.val('');
        $('#is_active').prop('checked', true);
    });

    // Handle 'Edit' button click
    $('#members-table').on('click', '.edit-btn', function() {
        const dataId = $(this).data('id');
        drawerLabel.text('编辑会员信息');
        form[0].reset();
        dataIdInput.val(dataId);

        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL,
            type: 'GET',
            data: { 
                res: 'pos_members',
                act: 'get',
                id: dataId 
            },
            dataType: 'json',
            // --- END MOD ---
            success: function(response) {
                if (response.status === 'success') {
                    const member = response.data;
                    $('#phone_number').val(member.phone_number);
                    $('#first_name').val(member.first_name);
                    $('#last_name').val(member.last_name);
                    $('#email').val(member.email);
                    $('#birthdate').val(member.birthdate);
                    $('#member_level_id').val(member.member_level_id);
                    $('#points_balance').val(member.points_balance);
                    $('#is_active').prop('checked', member.is_active == 1);
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

    // Handle form submission
    form.on('submit', function(e) {
        e.preventDefault();
        const formData = {
            id: dataIdInput.val(),
            phone_number: $('#phone_number').val(),
            first_name: $('#first_name').val(),
            last_name: $('#last_name').val(),
            email: $('#email').val(),
            birthdate: $('#birthdate').val(),
            member_level_id: $('#member_level_id').val(),
            points_balance: $('#points_balance').val(),
            is_active: $('#is_active').is(':checked') ? 1 : 0
        };

        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ data: formData }),
            dataType: 'json',
            beforeSend: function (xhr, settings) {
                settings.url += "?res=pos_members&act=save";
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

    // Handle 'Delete' button click
    $('#members-table').on('click', '.delete-btn', function() {
        const dataId = $(this).data('id');
        const dataName = $(this).data('name') || $(this).closest('tr').find('.phone-number').text();
        if (confirm(`您确定要删除会员 "${dataName}" 吗？此操作为软删除，数据将保留在数据库中。`)) {
            $.ajax({
                // --- MODIFIED ---
                url: API_GATEWAY_URL,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ id: dataId }),
                dataType: 'json',
                beforeSend: function (xhr, settings) {
                    settings.url += "?res=pos_members&act=delete";
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

    // Handle live search (无变化)
    $('#search-input').on('keyup', function() {
        const query = $(this).val().toLowerCase();
        $('#members-table tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(query) > -1)
        });
    });
});