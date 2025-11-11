/**
 * Toptea HQ - RMS (Recipe Management System) JavaScript
 * Engineer: Gemini | Date: 2025-11-06
 * Revision: 2.0.0 (True Template Parsing FIX)
 *
 * [GEMINI SOP LINK v2.0 - THE FIX]:
 * 1. `getGlobalSopTemplate()`: Now returns an object {template, mapping}
 * - It correctly converts V1 (DELIMITER/KEY_VALUE) rules into a V2-compatible template string and mapping.
 * - It defaults to '{P}-{M}-{A}-{T}' as requested if no global rule is found.
 * 2. `updatePamtCodeDisplay(card)`:
 * - Fetches the template (e.g., "({M}-{A}-{T}|{P})").
 * - Uses `template.replace()` to substitute placeholders ({M}, {A}, {P} etc.)
 * with the *actual codes* selected on that specific card.
 * - Correctly handles missing codes (replaces with empty string).
 * 3. `generatePmatListForRule(card)`:
 * - Fetches the same template string.
 * - Iterates through all Gating combinations (M-list, A-list, T-list).
 * - For *each combination*, it uses `template.replace()` to build the
 * final string (e.g., "(1-2-1|A1)"), precisely matching the KDS rule.
 * - This fixes the hardcoded `join('-')` bug.
 */
$(document).ready(function() {

    // --- 新的 API 网关入口 ---
    const API_GATEWAY_URL = 'api/cpsys_api_gateway.php';

    const productListContainer = $('#product-list-container');
    const editorContainer = $('#product-editor-container');
    const templatesContainer = $('#rms-templates');

    // --- DELEGATED EVENT HANDLERS ---
    productListContainer.on('click', '.list-group-item-action', function(e) {
        e.preventDefault();
        productListContainer.find('.list-group-item-action').removeClass('active');
        $(this).addClass('active');
        loadProductEditor($(this).data('productId'));
    });

    $('#btn-add-product').on('click', function() {
        productListContainer.find('.list-group-item-action').removeClass('active');
        renderProductEditor(null);
        $.ajax({
            url: API_GATEWAY_URL, 
            type: 'GET', 
            data: { 
                res: 'rms_products',
                act: 'get_next_product_code' 
            }, 
            dataType: 'json',
            success: response => {
                if (response.status === 'success') {
                    $('#product_code').val(response.data.next_code);
                    $('.adjustment-rule-card').each(function() {
                        updatePamtCodeDisplay($(this));
                    });
                }
            }
        });
    });

    editorContainer.on('submit', '#product-form', function(e) { e.preventDefault(); saveProduct(); });

    editorContainer.on('click', '#btn-delete-product', function() {
        const productId = $('#product-id').val();
        if (!productId) { alert('这是一个新产品，尚未保存，无法删除。'); return; }
        if (confirm('您确定要删除这个产品及其所有配方吗？此操作不可撤销。')) {
            deleteProduct(productId);
        }
    });

    editorContainer.on('click', '#btn-add-base-recipe-row', () => addRecipeRow('#base-recipe-body'));
    editorContainer.on('click', '#btn-add-adjustment-rule', () => addAdjustmentRule());
    editorContainer.on('click', '.btn-remove-adjustment-rule', function() {
        $(this).closest('.adjustment-rule-card').remove();
        if ($('.adjustment-rule-card').length === 0) { $('#no-adjustments-placeholder').show(); }
    });
    editorContainer.on('click', '.btn-add-adjustment-recipe-row', function() {
        const targetBody = $(this).closest('.card-body').find('.adjustment-recipe-body');
        addRecipeRow(targetBody);
    });
    editorContainer.on('click', '.btn-remove-row', function() { $(this).closest('tr').remove(); });
    
    // --- PAMT Code Generation Handlers ---
    editorContainer.on('change', '.cup-condition, .sweetness-condition, .ice-condition', function() {
        const $ruleCard = $(this).closest('.adjustment-rule-card');
        updatePamtCodeDisplay($ruleCard);
    });
    editorContainer.on('change', '#product_code', function() {
        $('.adjustment-rule-card').each(function() {
            updatePamtCodeDisplay($(this));
        });
    });
    editorContainer.on('click', '.btn-copy-pamt', function() {
        const $input = $(this).closest('.input-group').find('input.pamt-code-display');
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText($input.val()).then(() => {
                feedback($(this));
            }).catch(err => {
                console.error('Clipboard copy failed:', err);
                fallbackCopy($input[0]); // Fallback
            });
        } else {
            fallbackCopy($input[0]);
        }
    });
    editorContainer.on('click', '.btn-show-pmat-list', function() {
        generatePmatListForRule($(this).closest('.adjustment-rule-card'));
    });

    function fallbackCopy(inputElement) {
        try {
            inputElement.select();
            document.execCommand('copy');
            feedback($(inputElement).next('.btn-copy-pamt'));
        } catch (e) {
            alert('复制失败');
        }
    }
    
    function feedback($button) {
        const $icon = $button.find('i');
        const originalIcon = $icon.attr('class');
        $icon.removeClass('bi-clipboard bi-list-task').addClass('bi-check-lg text-success');
        setTimeout(() => {
            $icon.removeClass('bi-check-lg text-success').addClass(originalIcon);
        }, 1500);
    }

    // --- CORE FUNCTIONS ---
    
    /**
     * [GEMINI SOP LINK v2.0]
     * Reads the global KDS SOP rule injected by PHP and returns a usable template and mapping.
     * @returns {{template: string, mapping: {p: string, a: string, m: string, t: string, ord: string}}}
     */
    
function getGlobalSopTemplate() {
  const fallback = { template: '{P}-{M}-{A}-{T}', mapping: {p:'P',m:'M',a:'A',t:'T',ord:'ORD'} };
  const cfg = window.KDS_SOP_GLOBAL_RULE_CONFIG;
  if (!cfg || !cfg.config) return fallback;
  const c = cfg.config;

  // 优先：只要有 template + mapping 就直接用（避免 extractor_type 不一致导致回退）
  if (c.template && c.mapping) {
    // 规范化 key/value
    const norm = {};
    Object.keys(c.mapping).forEach(k => {
      norm[String(k).toLowerCase()] = String(c.mapping[k]).toUpperCase();
    });
    return { template: String(c.template), mapping: norm };
  }

  // V1: 分隔符模式（如 P-A-M-T）
  if (cfg.type === 'DELIMITER' && c.format) {
    let t = String(c.format).replace(/P/g,'{P}').replace(/A/g,'{A}').replace(/M/g,'{M}').replace(/T/g,'{T}');
    const sep = c.separator || '-';
    t = t.split('-').join(sep);
    return { template: (c.prefix||'') + t, mapping: {p:'P',a:'A',m:'M',t:'T'} };
  }

  // V1: 键值对模式（如 p={P}&a={A}...）
  if (cfg.type === 'KEY_VALUE') {
    const parts=[], map={};
    if (c.P_key){ parts.push(`${c.P_key}={P}`); map.p='P'; }
    if (c.M_key){ parts.push(`${c.M_key}={M}`); map.m='M'; }
    if (c.A_key){ parts.push(`${c.A_key}={A}`); map.a='A'; }
    if (c.T_key){ parts.push(`${c.T_key}={T}`); map.t='T'; }
    if (parts.length){ return { template:'?'+parts.join('&'), mapping: map }; }
  }

  return fallback;
}



    /**
     * [GEMINI SOP LINK v2.0 - FIX]
     * Updates the preview input on the L3 card using TRUE template replacement.
     */
    
function updatePamtCodeDisplay($ruleCard) {
  const p = $('#product_code').val() || 'P';
  const a = $ruleCard.find('.cup-condition option:selected').data('code') || '';
  const m = $ruleCard.find('.ice-condition option:selected').data('code') || '';
  const t = $ruleCard.find('.sweetness-condition option:selected').data('code') || '';
  const spec = getGlobalSopTemplate();
  let s = spec.template;
  const dict = {p, a, m, t, ord: ''};
  Object.keys(spec.mapping).forEach(k => {
    const ph = spec.mapping[k];
    if (ph) s = s.replace(new RegExp(RegExp.escape(`{${ph}}`),'g'), dict[k] ?? '');
  });
  s = s.replace(/{[A-Z0-9_]+}/g, '');
  $ruleCard.find('.pamt-code-display').val(s);
}

    
    // Helper for RegExp.escape
    if (!RegExp.escape) {
        RegExp.escape = function(s) {
            return String(s).replace(/[\\^$*+?.()|[\]{}]/g, '\\$&');
        };
    }


    function loadProductEditor(productId) {
        editorContainer.html('<div class="card-body text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>');
        $.ajax({
            url: API_GATEWAY_URL, 
            type: 'GET', 
            data: { 
                res: 'rms_products',
                act: 'get_product_details',
                id: productId 
            }, 
            dataType: 'json',
            success: response => {
                if (response.status === 'success') { renderProductEditor(response.data); } 
                else { editorContainer.html(`<div class="alert alert-danger">${response.message}</div>`); }
            },
            error: () => editorContainer.html('<div class="alert alert-danger">加载产品数据时发生网络错误。</div>')
        });
    }

    function renderProductEditor(data) {
        editorContainer.html(templatesContainer.find('#editor-template').html());

        if (data) {
            // 1. Fill Base Info
            $('#editor-title').text(`${data.product_code} - ${data.name_zh}`);
            $('#product-id').val(data.id);
            $('#product_code').val(data.product_code);
            $('#name_zh').val(data.name_zh);
            $('#name_es').val(data.name_es);
            $('#status_id').val(data.status_id);

            // 2. Populate Gating Checkboxes
            if (data.allowed_sweetness_ids) {
                data.allowed_sweetness_ids.forEach(id => {
                    $(`#gating-sweetness-list input[value="${id}"]`).prop('checked', true);
                });
            }
            if (data.allowed_ice_ids) {
                data.allowed_ice_ids.forEach(id => {
                    $(`#gating-ice-list input[value="${id}"]`).prop('checked', true);
                });
            }

            // 3. Populate Base Recipes (L1)
            const baseRecipeBody = $('#base-recipe-body');
            if(data.base_recipes && data.base_recipes.length > 0){
                data.base_recipes.forEach(recipe => addRecipeRow(baseRecipeBody, recipe));
            } else {
                baseRecipeBody.html('<tr><td colspan="5" class="text-center text-muted">暂无基础配方步骤。</td></tr>');
            }
            
            // 4. Populate Overrides (L3)
            if(data.adjustments && data.adjustments.length > 0) {
                $('#no-adjustments-placeholder').hide();
                data.adjustments.forEach(ruleGroup => addAdjustmentRule(ruleGroup));
            }
        } else {
            $('#editor-title').text('新产品');
            $('#btn-delete-product').hide();
            $('#gating-sweetness-list input[type="checkbox"]').prop('checked', true);
            $('#gating-ice-list input[type="checkbox"]').prop('checked', true);
        }
    }

    function addRecipeRow(targetBody, data = null) {
        if ($(targetBody).find('td[colspan]').length) $(targetBody).empty();
        const $newRow = templatesContainer.find('#recipe-row-template').clone().removeAttr('id');
        if (data) {
            if (data.step_category) {
                $newRow.find('.step-category-select').val(data.step_category);
            }
            $newRow.find('.material-select').val(data.material_id);
            $newRow.find('.quantity-input').val(data.quantity);
            $newRow.find('.unit-select').val(data.unit_id);
        }
        $(targetBody).append($newRow);
    }

    function addAdjustmentRule(ruleGroup = null) {
        $('#no-adjustments-placeholder').hide();
        const $newRule = templatesContainer.find('#adjustment-rule-template').clone().removeAttr('id');
        
        if (ruleGroup) {
            $newRule.find('.cup-condition').val(ruleGroup.cup_id);
            $newRule.find('.sweetness-condition').val(ruleGroup.sweetness_option_id);
            $newRule.find('.ice-condition').val(ruleGroup.ice_option_id);
            
            const recipeBody = $newRule.find('.adjustment-recipe-body');
            if(ruleGroup.overrides && ruleGroup.overrides.length > 0){
                 ruleGroup.overrides.forEach(override => addRecipeRow(recipeBody, override));
            }
        }
        $('#adjustments-body').append($newRule);
        
        updatePamtCodeDisplay($newRule);
    }

    function saveProduct() {
        const allowed_sweetness_ids = $('#gating-sweetness-list input:checked').map(function() { return $(this).val(); }).get();
        const allowed_ice_ids = $('#gating-ice-list input:checked').map(function() { return $(this).val(); }).get();

        const productData = {
            id: $('#product-id').val() || null,
            product_code: $('#product_code').val(),
            name_zh: $('#name_zh').val(),
            name_es: $('#name_es').val(),
            status_id: $('#status_id').val(),
            allowed_sweetness_ids: allowed_sweetness_ids,
            allowed_ice_ids: allowed_ice_ids,
            base_recipes: [],
            adjustments: []
        };

        $('#base-recipe-body tr').each(function(index) {
            const row = $(this);
            if (row.find('td[colspan]').length) return;
            productData.base_recipes.push({
                step_category: row.find('.step-category-select').val(),
                material_id: row.find('.material-select').val(),
                quantity: row.find('.quantity-input').val(),
                unit_id: row.find('.unit-select').val(),
                sort_order: index
            });
        });

        $('.adjustment-rule-card').each(function() {
            const card = $(this);
            const cup_id = card.find('.cup-condition').val() || null;
            const sweetness_option_id = card.find('.sweetness-condition').val() || null;
            const ice_option_id = card.find('.ice-condition').val() || null;
            
            card.find('.adjustment-recipe-body tr').each(function() {
                const row = $(this);
                if (row.find('td[colspan]').length) return;
                 productData.adjustments.push({
                    cup_id: cup_id,
                    sweetness_option_id: sweetness_option_id,
                    ice_option_id: ice_option_id,
                    step_category: row.find('.step-category-select').val(),
                    material_id: row.find('.material-select').val(),
                    quantity: row.find('.quantity-input').val(),
                    unit_id: row.find('.unit-select').val()
                });
            });
        });

        $.ajax({
            url: API_GATEWAY_URL, 
            type: 'POST', 
            contentType: 'application/json', 
            data: JSON.stringify({ product: productData }),
            dataType: 'json',
            beforeSend: function (xhr, settings) {
                settings.url += "?res=rms_products&act=save_product";
            },
            success: response => {
                if (response.status === 'success') { alert(response.message); window.location.reload(); } 
                else { alert('保存失败: ' + response.message); }
            },
            error: (jqXHR) => {
                const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : '保存过程中发生网络或服务器错误。';
                alert(`操作失败: ${errorMsg}`);
            }
        });
    }

    function deleteProduct(productId) {
        $.ajax({
            url: API_GATEWAY_URL, 
            type: 'POST', 
            contentType: 'application/json', 
            data: JSON.stringify({ id: productId }),
            dataType: 'json',
            beforeSend: function (xhr, settings) {
                settings.url += "?res=rms_products&act=delete_product";
            },
            success: response => {
                if (response.status === 'success') { alert(response.message); window.location.reload(); } 
                else { alert('删除失败: ' + response.message); }
            },
            error: () => alert('删除过程中发生网络错误。')
        });
    }
    
    /**
     * [GEMINI SOP LINK v2.0 - FIX]
     * Generates the PMAT list using TRUE template replacement.
     */
    
function generatePmatListForRule($card) {
  const p = $('#product_code').val() || 'P';
  const pick = ($sel) => $sel.val() ? [$sel.find('option:selected').data('code')] : [];
  const listFrom = ($wrap) => $wrap.find('input:checked').map((_,cb)=>{
    const m = $(cb).closest('.form-check').find('label').text().match(/\[(.*?)\]/);
    return m ? m[1] : null;
  }).get();

  const aList = pick($card.find('.cup-condition'));
  const mList = pick($card.find('.ice-condition')).concat(aList.length?[]:listFrom($('#gating-ice-list')));
  const tList = pick($card.find('.sweetness-condition')).concat(aList.length?[]:listFrom($('#gating-sweetness-list')));
  const A = aList.length ? aList : [''];
  const M = mList.length ? mList : [''];
  const T = tList.length ? tList : [''];

  const spec = getGlobalSopTemplate();
  const out = [];
  M.forEach(m => A.forEach(a => T.forEach(t => {
    let s = spec.template;
    const dict = {p, a, m, t, ord: ''};
    Object.keys(spec.mapping).forEach(k => {
      const ph = spec.mapping[k];
      if (ph) s = s.replace(new RegExp(RegExp.escape(`{${ph}}`),'g'), dict[k] ?? '');
    });
    out.push(s.replace(/{[A-Z0-9_]+}/g,'').trim());
  })));

  const txt = [...new Set(out)].filter(Boolean).join('\n') || '（无匹配）';
  $('#pmat-list-textarea').val(txt);
}

});