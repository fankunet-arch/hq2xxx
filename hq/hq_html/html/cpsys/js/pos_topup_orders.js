/**
 * TopTea HQ - JavaScript for Topup Order (VR) Review
 * Engineer: Gemini | Date: 2025-11-12
 * [R2.4] Implements: 3.2 CPSYS-BMS (B1)
 */
$(document).ready(function() {

    const API_GATEWAY_URL = 'api/cpsys_api_gateway.php';
    const API_RES = 'topup_orders';

    const reviewModal = new bootstrap.Modal(document.getElementById('review-modal'));
    const modalOrderIdInput = $('#modal-review-order-id-input');

    // 1. 打开模态框时，填充数据
    $('.table').on('click', '.review-btn', function() {
        const orderId = $(this).data('id');
        const memberPhone = $(this).data('member');
        const planName = $(this).data('plan');

        $('#modal-order-id').text('#' + orderId);
        $('#modal-member-phone').text(memberPhone);
        $('#modal-plan-name').text(planName);
        modalOrderIdInput.val(orderId);
    });

    // 2. 批准按钮点击
    $('#review-approve-btn').on('click', function() {
        const orderId = modalOrderIdInput.val();
        if (!orderId) {
            alert('获取订单ID失败。');
            return;
        }
        if (confirm(`您确定要 [通过] 订单 #${orderId} 吗？\n系统将立即为该会员激活次卡。`)) {
            sendReviewRequest(orderId, 'APPROVE');
        }
    });

    // 3. 拒绝按钮点击
    $('#review-reject-btn').on('click', function() {
        const orderId = modalOrderIdInput.val();
        if (!orderId) {
            alert('获取订单ID失败。');
            return;
        }
        if (confirm(`您确定要 [拒绝] 订单 #${orderId} 吗？\n此操作不可撤销。`)) {
            sendReviewRequest(orderId, 'REJECT');
        }
    });

    // 4. 发送 API 请求的通用函数
    function sendReviewRequest(orderId, reviewAction) {
        const button = (reviewAction === 'APPROVE') ? $('#review-approve-btn') : $('#review-reject-btn');
        const originalText = button.html();
        button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> 处理中...');

        $.ajax({
            url: API_GATEWAY_URL,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                order_id: orderId,
                action: reviewAction 
            }),
            dataType: 'json',
            beforeSend: function (xhr, settings) {
                settings.url += `?res=${API_RES}&act=review`;
            },
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    window.location.reload();
                } else {
                    alert('操作失败: ' + (response.message || '未知错误'));
                    button.prop('disabled', false).html(originalText);
                }
            },
            error: function(jqXHR) {
                let errorMsg = '审核过程中发生网络或服务器错误。';
                if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                    errorMsg = '操作失败: ' + jqXHR.responseJSON.message;
                }
                alert(errorMsg);
                button.prop('disabled', false).html(originalText);
            }
        });
    }
});