/**
 * Toptea HQ - JavaScript for POS Shift Review
 * Engineer: Gemini | Date: 2025-11-04
 * Revision: 1.0.001 (API Gateway Refactor)
 */
$(document).ready(function() {

    // --- 新的 API 网关入口 ---
    const API_GATEWAY_URL = 'api/cpsys_api_gateway.php';

    const reviewModal = new bootstrap.Modal(document.getElementById('review-modal'));
    const form = $('#review-form');
    const shiftIdInput = $('#review-shift-id');
    const expectedCashInput = $('#review-expected-cash');
    const countedCashInput = $('#review-counted-cash');
    const cashDiffInput = $('#review-cash-diff');

    /**
     * 打开模态框时填充数据
     */
    $('.review-btn').on('click', function() {
        const shiftId = $(this).data('shift-id');
        const expectedCash = parseFloat($(this).data('expected-cash')).toFixed(2);
        const userName = $(this).data('user-name');
        const startTime = $(this).data('start-time');

        form[0].reset();
        shiftIdInput.val(shiftId);
        expectedCashInput.val(expectedCash);
        $('#review-user-name').val(userName);
        $('#review-start-time').val(startTime);
        cashDiffInput.val('€ 0.00');
    });

    /**
     * 实时计算差异
     */
    countedCashInput.on('input', function() {
        const expected = parseFloat(expectedCashInput.val()) || 0;
        const counted = parseFloat($(this).val()) || 0;
        const diff = counted - expected;
        
        cashDiffInput.val('€ ' + diff.toFixed(2));
        if (diff < 0) {
            cashDiffInput.css('color', '#dc3545'); // Danger
        } else if (diff > 0) {
            cashDiffInput.css('color', '#198754'); // Success
        } else {
            cashDiffInput.css('color', '#adb5bd'); // Secondary
        }
    });

    /**
     * 提交复核
     */
    form.on('submit', function(e) {
        e.preventDefault();
        
        const payload = {
            // action: 'review', // 旧 handler 需要
            shift_id: shiftIdInput.val(),
            counted_cash: countedCashInput.val()
        };

        if (payload.counted_cash === '' || isNaN(parseFloat(payload.counted_cash))) {
            alert('请输入有效的“实际清点现金”金额。');
            return;
        }

        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL + '?res=shifts&act=review',
            // url: 'api/shift_review_handler.php', // 旧
            // --- END MOD ---
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload), // 载荷不变
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    window.location.reload();
                } else {
                    alert('复核失败: ' + (response.message || '未知错误'));
                }
            },
            error: function(jqXHR) {
                const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : '保存过程中发生网络或服务器错误。';
                alert('操作失败: ' + errorMsg);
            },
            complete: function() {
                reviewModal.hide();
            }
        });
    });
});