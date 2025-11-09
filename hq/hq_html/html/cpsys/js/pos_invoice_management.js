/**
 * Toptea HQ - JavaScript for POS Invoice Management (Detail View)
 * Engineer: Gemini | Date: 2025-10-26 | Revision: 1.0.001 (API Gateway Refactor)
 */
$(document).ready(function() {
    
    // --- 新的 API 网关入口 ---
    const API_GATEWAY_URL = 'api/cpsys_api_gateway.php';

    // --- CANCELLATION (作废) Logic ---
    const cancelReasonInput = $('#cancellation_reason_text');
    $('.reason-btn').on('click', function() {
        cancelReasonInput.val($(this).data('reason-es'));
    });

    $('#btn-confirm-cancellation').on('click', function() {
        const invoiceId = $(this).data('invoice-id');
        const reason = cancelReasonInput.val().trim();
        if (reason === '') { alert('作废原因不能为空。'); return; }

        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL + '?res=invoices&act=cancel',
            // url: 'api/cancel_invoice.php', // 旧
            // --- END MOD ---
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id: invoiceId, reason: reason }), // 载荷不变
            dataType: 'json',
            beforeSend: () => $(this).prop('disabled', true).text('处理中...'),
            success: (response) => {
                if (response.status === 'success') {
                    alert(`作废成功！页面将刷新。`);
                    window.location.reload();
                } else {
                    alert('作废失败: ' + (response.data?.debug || response.message));
                }
            },
            error: (jqXHR) => {
                const errorMsg = jqXHR.responseJSON?.data?.debug || jqXHR.responseJSON?.message || '网络或服务器错误。';
                alert('操作失败: ' + errorMsg);
            },
            complete: () => $(this).prop('disabled', false).text('确认作废')
        });
    });

    // --- CORRECTION (更正) Logic ---
    const correctionDifferenceSection = $('#correction-by-difference-section');
    const correctionReasonInput = $('#correction_reason_text');

    $('input[name="correctionType"]').on('change', function() {
        if (this.value === 'I') {
            correctionDifferenceSection.slideDown();
        } else {
            correctionDifferenceSection.slideUp();
        }
    });

    $('.correction-reason-btn').on('click', function() {
        correctionReasonInput.val($(this).data('reason-es'));
    });

    $('#btn-confirm-correction').on('click', function() {
        const invoiceId = $(this).data('invoice-id');
        const correctionType = $('input[name="correctionType"]:checked').val();
        const newTotal = $('#new_final_total').val();
        const reason = correctionReasonInput.val().trim();

        if (!reason) {
            alert('更正原因不能为空。');
            return;
        }
        if (correctionType === 'I' && (newTotal === '' || isNaN(parseFloat(newTotal)) || parseFloat(newTotal) < 0)) {
            alert('选择“部分差额”时，必须输入一个有效的、大于等于0的更正后总额。');
            return;
        }
        
        const payload = {
            id: invoiceId,
            type: correctionType,
            reason: reason,
            new_total: (correctionType === 'I') ? newTotal : null
        };

        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL + '?res=invoices&act=correct',
            // url: 'api/correct_invoice.php', // 旧
            // --- END MOD ---
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload), // 载荷不变
            dataType: 'json',
            beforeSend: () => $(this).prop('disabled', true).text('生成中...'),
            success: (response) => {
                if (response.status === 'success') {
                    alert(`更正票据已成功生成 (新票据ID: ${response.data.corrective_invoice_id})。页面将刷新。`);
                    window.location.reload();
                } else {
                    alert('更正失败: ' + (response.data?.debug || response.message));
                }
            },
            error: (jqXHR) => {
                const errorMsg = jqXHR.responseJSON?.data?.debug || jqXHR.responseJSON?.message || '网络或服务器错误。';
                alert('操作失败: 'D + errorMsg);
            },
            complete: () => $(this).prop('disabled', false).text('确认并生成更正票据')
        });
    });
});