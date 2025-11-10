/**
 * Toptea HQ - JavaScript for SIF Declaration Page
 * Engineer: Gemini | Date: 2025-11-03
 * Revision: 1.0.003 (API Gateway Refactor)
 */
$(document).ready(function() {
    
    // --- 新的 API 网关入口 ---
    const API_GATEWAY_URL = 'api/cpsys_api_gateway.php';

    const form = $('#sif-declaration-form');
    const feedbackDiv = $('#settings-feedback');
    const declarationTextarea = $('#sif_declaration_text');

    function loadDeclaration() {
        feedbackDiv.html('<div class="spinner-border spinner-border-sm text-secondary" role="status"><span class="visually-hidden">Loading...</span></div>');
        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL,
            type: 'GET',
            data: { 
                res: 'pos_settings',
                act: 'load_sif' 
                // 旧: action: 'load'
            },
            dataType: 'json',
            // --- END MOD ---
            success: function(response) {
                if (response.status === 'success') {
                    if (response.data && response.data.declaration_text !== null && response.data.declaration_text !== undefined) {
                        declarationTextarea.val(response.data.declaration_text);
                    }
                    feedbackDiv.empty();
                } else {
                    feedbackDiv.html(`<div class="alert alert-danger">加载声明失败: ${response.message || '未知错误'}</div>`);
                }
            },
            error: function(jqXHR) {
                 const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : '加载时发生网络错误。';
                 feedbackDiv.html(`<div class="alert alert-danger">${errorMsg}</div>`);
            }
        });
    }

    form.on('submit', function(e) {
        e.preventDefault();
        feedbackDiv.html('<div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Saving...</span></div>');
        
        const submitButton = $(this).find('button[type="submit"]');
        submitButton.prop('disabled', true);

        const declarationText = declarationTextarea.val();

        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ 
                declaration_text: declarationText 
                // 旧: action: 'save', declaration_text: ...
            }),
            dataType: 'json',
            beforeSend: function (xhr, settings) {
                settings.url += "?res=pos_settings&act=save_sif";
            },
            // --- END MOD ---
            success: function(response) {
                if (response.status === 'success') {
                    feedbackDiv.html('<div class="alert alert-success">声明已成功保存！</div>');
                     setTimeout(() => feedbackDiv.empty(), 3000);
                } else {
                    feedbackDiv.html(`<div class="alert alert-danger">保存失败: ${response.message || '未知错误'}</div>`);
                }
            },
            error: function(jqXHR) {
                const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : '保存过程中发生网络或服务器错误。';
                feedbackDiv.html(`<div class="alert alert-danger">操作失败: ${errorMsg}</div>`);
            },
            complete: function() {
                 submitButton.prop('disabled', false);
                 if (!feedbackDiv.find('.alert-success').length) {
                     setTimeout(() => feedbackDiv.empty(), 5000);
                 }
            }
        });
    });

    // Initial load
    loadDeclaration();
});