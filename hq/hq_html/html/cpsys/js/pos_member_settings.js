/**
 * Toptea HQ - JavaScript for POS Member Settings Page
 * Engineer: Gemini | Date: 2025-10-28
 * Revision: 1.3.0 (Global Addon Limit Refactor - REMOVED)
 *
 * [GEMINI REFACTOR 2025-11-14]:
 * - Removed "global_free_addon_limit" logic.
 * This setting was moved to pos_addon_management.js
 */
$(document).ready(function() {

    // --- 新的 API 网关入口 ---
    const API_GATEWAY_URL = 'api/cpsys_api_gateway.php';

    const form = $('#member-settings-form');
    const feedbackDiv = $('#settings-feedback');
    const eurosPerPointInput = $('#euros_per_point');
    
    // [GEMINI REFACTOR] 移除 globalFreeAddonLimitInput


    // Function to load settings
    function loadSettings() {
        feedbackDiv.html('<div class="spinner-border spinner-border-sm text-secondary" role="status"><span class="visually-hidden">Loading...</span></div>');
        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL,
            type: 'GET',
            data: { 
                res: 'pos_settings',
                act: 'load' 
            },
            dataType: 'json',
            // --- END MOD ---
            success: function(response) {
                if (response.status === 'success') {
                    // [GEMINI REFACTOR] 修改
                    const settings = response.data || {};
                    eurosPerPointInput.val(settings.points_euros_per_point || '1.00'); 
                    // [GEMINI REFACTOR] 移除 globalFreeAddonLimitInput.val()
                    
                    feedbackDiv.empty();
                } else {
                    feedbackDiv.html(`<div class="alert alert-danger">加载设置失败: ${response.message || '未知错误'}</div>`);
                }
            },
            error: function(jqXHR) {
                 const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : '加载设置时发生网络错误。';
                 feedbackDiv.html(`<div class="alert alert-danger">${errorMsg}</div>`);
            }
        });
    }

    // Function to save settings
    form.on('submit', function(e) {
        e.preventDefault();
        feedbackDiv.html('<div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Saving...</span></div>');
        
        const submitButton = $(this).find('button[type="submit"]');
        submitButton.prop('disabled', true);

        // [GEMINI REFACTOR] 修改
        const settingsData = {
            points_euros_per_point: eurosPerPointInput.val()
            // [GEMINI REFACTOR] 移除 global_free_addon_limit
        };

        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ settings: settingsData }), // { action: 'save', settings: ... }
            dataType: 'json',
            beforeSend: function (xhr, settings) {
                settings.url += "?res=pos_settings&act=save";
            },
            // --- END MOD ---
            success: function(response) {
                if (response.status === 'success') {
                    feedbackDiv.html('<div class="alert alert-success">设置已成功保存！</div>');
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
    loadSettings();
});