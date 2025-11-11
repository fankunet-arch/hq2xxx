$(function () {
    // --- MODIFIED ---
    const API_GATEWAY_URL = 'api/cpsys_api_gateway.php';
    // const API_URL = 'api/pos_promotion_handler.php'; // 旧
    // --- END MOD ---

    const DRAWER = new bootstrap.Offcanvas('#promo-drawer');
    const MENU_ITEMS_RAW = JSON.parse($('#menu-items-json').html() || '[]');
    
    $('[data-bs-toggle="popover"]').popover({ html: true });

    function getSelectOptions() {
        return MENU_ITEMS_RAW.map(item => `<option value="${item.id}">${item.name_zh}</option>`).join('');
    }

    function renderConditionParams($container, type, data = {}) {
        let content = '';
        if (type === 'ITEM_QUANTITY') { content = $('#param-item-quantity').html(); }
        $container.html(content);
        $container.find('.multi-select-items').append(getSelectOptions());
        if (data.item_ids) $container.find('[data-param="item_ids"]').val(data.item_ids);
        if (data.min_quantity) $container.find('[data-param="min_quantity"]').val(data.min_quantity);
    }

    function renderActionParams($container, type, data = {}) {
        let content = '';
        if (type === 'SET_PRICE_ZERO') { content = $('#param-set-price-zero').html(); } 
        else if (type === 'PERCENTAGE_DISCOUNT') { content = $('#param-percentage-discount').html(); }
        $container.html(content);
        $container.find('.multi-select-items').append(getSelectOptions());
        if (data.item_ids) $container.find('[data-param="item_ids"]').val(data.item_ids);
        if (data.quantity) $container.find('[data-param="quantity"]').val(data.quantity);
        if (data.percentage) $container.find('[data-param="percentage"]').val(data.percentage);
    }
    
    function togglePromoCodeInput() {
        if ($('#promo_trigger_type').val() === 'COUPON_CODE') {
            $('#promo-code-container').slideDown();
        } else {
            $('#promo-code-container').slideUp();
        }
    }
    $('#promo_trigger_type').on('change', togglePromoCodeInput);

    function addRow(containerSelector, templateSelector, isAction = false) {
        const $template = $(templateSelector).clone().removeAttr('id').addClass('p-3');
        $(containerSelector).append($template);
        if (isAction) { renderActionParams($template.find('.action-params'), ''); } 
        else { renderConditionParams($template.find('.condition-params'), ''); }
    }

    $('#add-condition-btn').on('click', () => addRow('#conditions-container', '#condition-template', false));
    $('#add-action-btn').on('click', () => addRow('#actions-container', '#action-template', true));

    $(document).on('change', '.condition-type', function() { renderConditionParams($(this).closest('.dynamic-row').find('.condition-params'), $(this).val()); });
    $(document).on('change', '.action-type', function() { renderActionParams($(this).closest('.dynamic-row').find('.action-params'), $(this).val()); });
    $(document).on('click', '.remove-row-btn', function() { $(this).closest('.dynamic-row').remove(); });

    function resetForm() {
        $('#promo-form')[0].reset();
        $('#promo-id').val('');
        $('#conditions-container, #actions-container').empty();
        $('#promo-drawer-label').text('创建新活动');
        togglePromoCodeInput();
    }

    $('#create-btn').on('click', resetForm);

    $('.edit-btn').on('click', function() {
        resetForm();
        const promoId = $(this).data('id');
        $('#promo-drawer-label').text('编辑活动');
        
        // --- MODIFIED ---
        $.get(API_GATEWAY_URL, { 
            res: 'pos_promotions',
            act: 'get',
            id: promoId 
        }, function(res) {
        // --- END MOD ---
            if (res.status === 'success') {
                const p = res.data;
                $('#promo-id').val(p.id);
                $('#promo_name').val(p.promo_name);
                $('#promo_priority').val(p.promo_priority);
                $('#promo_exclusive').prop('checked', p.promo_exclusive == 1);
                $('#promo_is_active').prop('checked', p.promo_is_active == 1);
                $('#promo_trigger_type').val(p.promo_trigger_type);
                $('#promo_code').val(p.promo_code);
                $('#promo_start_date').val(p.promo_start_date ? p.promo_start_date.replace(' ', 'T') : '');
                $('#promo_end_date').val(p.promo_end_date ? p.promo_end_date.replace(' ', 'T') : '');
                togglePromoCodeInput();

                const conditions = p.promo_conditions ? JSON.parse(p.promo_conditions) : [];
                if (conditions) {
                    conditions.forEach(cond => {
                        addRow('#conditions-container', '#condition-template', false);
                        const $row = $('#conditions-container .dynamic-row').last();
                        $row.find('.condition-type').val(cond.type);
                        renderConditionParams($row.find('.condition-params'), cond.type, cond);
                    });
                }

                const actions = p.promo_actions ? JSON.parse(p.promo_actions) : [];
                if (actions) {
                    actions.forEach(act => {
                        addRow('#actions-container', '#action-template', true);
                        const $row = $('#actions-container .dynamic-row').last();
                        $row.find('.action-type').val(act.type);
                        renderActionParams($row.find('.action-params'), act.type, act);
                    });
                }
            } else { alert(res.message); }
        });
    });

    function buildRules(containerSelector, isAction = false) {
        const rules = [];
        $(containerSelector).find('.dynamic-row').each(function() {
            const $row = $(this), type = $row.find(isAction ? '.action-type' : '.condition-type').val(); if (!type) return;
            const params = {};
            $row.find('[data-param]').each(function() {
                const $p = $(this), key = $p.data('param'); let val = $p.val();
                if ($p.is('select[multiple]')) { val = (val || []).map(Number); } 
                else if ($p.attr('type') === 'number') { val = parseFloat(val) || 0; }
                params[key] = val;
            });
            rules.push({ type, ...params });
        });
        return rules;
    }

    $('#promo-form').on('submit', function(e) {
        e.preventDefault();
        const data = {
            id: $('#promo-id').val(),
            promo_name: $('#promo_name').val(),
            promo_priority: $('#promo_priority').val(),
            promo_exclusive: $('#promo_exclusive').is(':checked') ? 1 : 0,
            promo_is_active: $('#promo_is_active').is(':checked') ? 1 : 0,
            promo_trigger_type: $('#promo_trigger_type').val(),
            promo_code: $('#promo_code').val(),
            promo_start_date: $('#promo_start_date').val(),
            promo_end_date: $('#promo_end_date').val(),
            promo_conditions: buildRules('#conditions-container', false),
            promo_actions: buildRules('#actions-container', true)
        };

        $.ajax({
            // --- MODIFIED ---
            url: API_GATEWAY_URL, 
            type: 'POST', 
            contentType: 'application/json', 
            data: JSON.stringify({ data: data }),
            dataType: 'json',
            beforeSend: function (xhr, settings) {
                settings.url += "?res=pos_promotions&act=save";
            },
            // --- END MOD ---
            success: (res) => { if (res.status === 'success') { alert(res.message); location.reload(); } else { alert(res.message); } },
            error: () => alert('保存时发生错误。')
        });
    });

    $('.delete-btn').on('click', function() {
        const id = $(this).data('id'), name = $(this).data('name');
        if (confirm(`确定要删除活动 "${name}" 吗？此操作不可撤销。`)) {
             $.ajax({
                // --- MODIFIED ---
                url: API_GATEWAY_URL, 
                type: 'POST', 
                contentType: 'application/json', 
                data: JSON.stringify({ id: id }),
                dataType: 'json',
                beforeSend: function (xhr, settings) {
                    settings.url += "?res=pos_promotions&act=delete";
                },
                // --- END MOD ---
                success: (res) => { if (res.status === 'success') { alert(res.message); location.reload(); } else { alert(res.message); } },
                error: () => alert('删除时发生错误。')
            });
        }
    });
});