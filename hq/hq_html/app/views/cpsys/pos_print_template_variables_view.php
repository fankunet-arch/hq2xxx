<?php
/**
 * TopTea HQ - POS Print Template Variables View
 * Version: 3.5.0 (Receipts are now Bilingual)
 * Engineer: Gemini | Date: 2025-11-04
 * Update:
 * 1. [Q1 FIX] All item loop variables (Receipt/Kitchen/Cup) are now bilingual.
 * The DB snapshot table `pos_invoice_items` has been upgraded.
 * 2. [Q2 FIX] Added fallback default templates.
 *
 * [PHASE 5] (V3.6.0) Add new invoice numbering and KDS variables
 * Date: 2025-11-08
 */

// Helper to format and display JSON
function render_json_template($title, $json_content) {
    $formatted_json = '';
    if (!empty($json_content)) {
        $json_decoded = json_decode($json_content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $formatted_json = htmlspecialchars(json_encode($json_decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $formatted_json = htmlspecialchars($json_content);
        }
    } else {
        $formatted_json = " (暂无默认模板)";
    }

    echo '<div class="card mb-4">';
    echo '<div class="card-header">' . htmlspecialchars($title) . '</div>';
    echo '<div class="card-body">';
    echo '<pre><code class="language-json">' . $formatted_json . '</code></pre>';
    echo '</div>';
    echo '</div>';
}

// --- [Q2 FIX] START: Define fallback default templates ---
// These will be used if they weren't loaded from the DB by index.php

$default_templates['RECEIPT'] = $default_templates['RECEIPT'] ?? '[
    {"type": "text", "value": "{store_name}", "align": "center", "size": "double"},
    {"type": "text", "value": "{store_address}", "align": "center", "size": "normal"},
    {"type": "text", "value": "NIF: {store_tax_id}", "align": "center", "size": "normal"},
    {"type": "divider", "char": "-"},
    {"type": "kv", "key": "Factura Simplificada", "value": "{invoice_full}"},
    {"type": "kv", "key": "Nº Pedido (Recogida)", "value": "{pickup_number}"},
    {"type": "kv", "key": "Fecha (Date)", "value": "{issued_at}"},
    {"type": "kv", "key": "Cajero (Cashier)", "value": "{cashier_name}"},
    {"type": "divider", "char": "-"},
    {"type": "items_loop", "items": [
        {"type": "text", "value": "{item_name_es}", "align": "left", "size": "normal"},
        {"type": "text", "value": "{item_name_zh}", "align": "left", "size": "normal"},
        {"type": "kv", "key": "  {item_qty} x {item_unit_price}", "value": "{item_total_price}"},
        {"type": "text", "value": "  ( {item_customizations} )", "align": "left", "size": "normal"}
    ]},
    {"type": "divider", "char": "-"},
    {"type": "kv", "key": "Subtotal", "value": "{subtotal}"},
    {"type": "kv", "key": "Descuento", "value": "{discount_amount}"},
    {"type": "kv", "key": "TOTAL", "value": "{final_total}", "bold_value": true},
    {"type": "divider", "char": "-"},
    {"type": "kv", "key": "Base Imponible", "value": "{taxable_base}"},
    {"type": "kv", "key": "IVA", "value": "{vat_amount}"},
    {"type": "divider", "char": "."},
    {"type": "text", "value": "{payment_methods}", "align": "left", "size": "normal"},
    {"type": "kv", "key": "Cambio", "value": "{change}"},
    {"type": "feed", "lines": 1},
    {"type": "qr_code", "value": "{qr_code}", "align": "center"},
    {"type": "text", "value": "Gracias por su visita", "align": "center", "size": "normal"},
    {"type": "feed", "lines": 3},
    {"type": "cut"}
]';

$default_templates['KITCHEN_ORDER'] = $default_templates['KITCHEN_ORDER'] ?? '[
    {"type": "text", "value": "Cocina: #{pickup_number}", "align": "left", "size": "double"},
    {"type": "kv", "key": "Hora:", "value": "{issued_at}", "bold_value": false},
    {"type": "divider", "char": "="},
    {"type": "items_loop", "items": [
        {"type": "text", "value": "{item_qty} x {item_name_zh}", "align": "left", "size": "double"},
        {"type": "text", "value": "( {item_variant_zh} )", "align": "left", "size": "normal"},
        {"type": "text", "value": ">> {item_customizations}", "align": "left", "size": "double"}
    ]},
    {"type": "divider", "char": "="},
    {"type": "feed", "lines": 3},
    {"type": "cut"}
]';

$default_templates['EOD_REPORT'] = $default_templates['EOD_REPORT'] ?? '[
    {"type": "text", "value": "Informe Cierre Diario (Z)", "align": "center", "size": "double"},
    {"type": "kv", "key": "Tienda", "value": "{store_name}"},
    {"type": "kv", "key": "Fecha Informe", "value": "{report_date}"},
    {"type": "kv", "key": "Hora Impresión", "value": "{print_time}"},
    {"type": "kv", "key": "Cajero", "value": "{user_name}"},
    {"type": "divider", "char": "="},
    {"type": "text", "value": "RESUMEN DE VENTAS", "align": "left", "size": "normal"},
    {"type": "divider", "char": "-"},
    {"type": "kv", "key": "Nº Transacciones", "value": "{transactions_count}"},
    {"type": "kv", "key": "Ventas Brutas", "value": "{system_gross_sales}"},
    {"type": "kv", "key": "Descuentos", "value": "{system_discounts}"},
    {"type": "kv", "key": "Ventas Netas", "value": "{system_net_sales}"},
    {"type": "kv", "key": "Impuestos (IVA)", "value": "{system_tax}"},
    {"type": "divider", "char": "="},
    {"type": "text", "value": "RESUMEN DE CAJA", "align": "left", "size": "normal"},
    {"type": "divider", "char": "-"},
    {"type": "kv", "key": "Sistema: Efectivo", "value": "{system_cash}"},
    {"type": "kv", "key": "Sistema: Tarjeta", "value": "{system_card}"},
    {"type": "kv", "key": "Sistema: Plataforma", "value": "{system_platform}"},
    {"type": "divider", "char": "-"},
    {"type": "kv", "key": "EFECTIVO CONTADO", "value": "{counted_cash}", "bold_value": true},
    {"type": "kv", "key": "DIFERENCIA", "value": "{cash_discrepancy}", "bold_value": true},
    {"type": "feed", "lines": 3},
    {"type": "cut"}
]';

$default_templates['EXPIRY_LABEL'] = $default_templates['EXPIRY_LABEL'] ?? '[
    {
        "type": "text",
        "value": "{material_name}",
        "align": "left",
        "size": "normal"
    },
    {
        "type": "text",
        "value": "{material_name_es}",
        "align": "left",
        "size": "normal"
    },
    {
        "type": "divider",
        "char": "-"
    },
    {
        "type": "kv",
        "key": "Ini:",
        "value": "{opened_at_time}"
    },
    {
        "type": "kv",
        "key": "Cad:",
        "value": "{expires_at_time}",
        "bold_value": true
    },
    {
        "type": "kv",
        "key": "Op:",
        "value": "{operator_name}"
    },
    {
        "type": "feed",
        "lines": 1
    }
]';
// --- [Q2 FIX] END ---
?>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                顾客小票 (RECEIPT) & 厨房出品单 (KITCHEN_ORDER)
            </div>
            <div class="card-body">
                <p class="card-text">
                    这两类模板共享大部分订单相关的变量。厨房单通常只关注商品信息，而顾客小票则包含所有信息。
                </p>
                
                <h6 class="mt-4">全局变量</h6>
                <table class="table table-sm table-bordered">
                    <thead><tr><th>变量</th><th>说明</th></tr></thead>
                    <tbody>
                        <tr><td><code>{store_name}</code></td><td>门店名称</td></tr>
                        <tr><td><code>{store_address}</code></td><td>门店地址</td></tr>
                        <tr><td><code>{store_tax_id}</code></td><td>门店税号 (NIF)</td></tr>
                        <tr><td><code>{issued_at}</code></td><td>开票时间 (YYYY-MM-DD HH:MM:SS)</td></tr>
                        <tr><td><code>{cashier_name}</code></td><td>收银员姓名</td></tr>
                        <tr><td colspan="2"><hr class="my-1"></td></tr>
                        <tr><td><code>{pickup_number}</code></td><td><b>(新)</b> 取餐号 (e.g., 1001)</td></tr>
                        <tr><td><code>{invoice_full}</code></td><td><b>(新)</b> 完整票号 (e.g., S1Y25-1001)</td></tr>
                        <tr><td><code>{invoice_series}</code></td><td><b>(新)</b> 票号系列 (e.g., S1Y25)</td></tr>
                        <tr><td><code>{invoice_sequence}</code></td><td><b>(新)</b> 票号序号 (e.g., 1001)</td></tr>
                        <tr><td colspan="2"><hr class="my-1"></td></tr>
                        <tr><td><code>{qr_code}</code></td><td>合规二维码内容 (TicketBAI/Veri*Factu)</td></tr>
                        <tr><td><code>{invoice_number}</code></td><td>(旧) 完整票号 (S1Y25-1001)</td></tr>
                    </tbody>
                </table>

                <h6 class="mt-4">财务变量 (仅顾客小票适用)</h6>
                <table class="table table-sm table-bordered">
                     <thead><tr><th>变量</th><th>说明</th></tr></thead>
                    <tbody>
                        <tr><td><code>{subtotal}</code></td><td>小计金额 (折扣前)</td></tr>
                        <tr><td><code>{discount_amount}</code></td><td>总折扣金额</td></tr>
                        <tr><td><code>{final_total}</code></td><td>最终应付总额</td></tr>
                        <tr><td><code>{taxable_base}</code></td><td>税前基数</td></tr>
                        <tr><td><code>{vat_amount}</code></td><td>总税额</td></tr>
                        <tr><td><code>{payment_methods}</code></td><td>支付方式明细 (自动格式化)</td></tr>
                        <tr><td><code>{change}</code></td><td>找零金额</td></tr>
                    </tbody>
                </table>

                <h6 class="mt-4">商品循环变量 (在 "items_loop" 中使用)</h6>
                <div class="alert alert-success small">
                    <b>好消息:</b> 感谢数据库升级，现在小票和厨房单的商品循环 **完全支持双语**。
                </div>
                 <table class="table table-sm table-bordered">
                    <thead><tr><th>变量</th><th>说明</th></tr></thead>
                    <tbody>
                        <tr><td><code>{item_name_zh}</code></td><td><b>(推荐)</b> 商品名称 (中文)</td></tr>
                        <tr><td><code>{item_name_es}</code></td><td><b>(推荐)</b> 商品名称 (西语)</td></tr>
                        <tr><td><code>{variant_name_zh}</code></td><td><b>(推荐)</b> 规格名称 (中文)</td></tr>
                        <tr><td><code>{variant_name_es}</code></td><td><b>(推荐)</b> 规格名称 (西语)</td></tr>
                        <tr><td><code>{item_qty}</code></td><td>商品数量</td></tr>
                        <tr><td><code>{item_unit_price}</code></td><td>商品单价 (含税)</td></tr>
                        <tr><td><code>{item_total_price}</code></td><td>商品行总价 (含税)</td></tr>
                        <tr><td><code>{item_customizations}</code></td><td>商品自定义选项 (<b>单语言</b>)</td></tr>
                        <tr><td><code>{item_name}</code></td><td>商品名称 (下单时语言)</td></tr>
                        <tr><td><code>{item_variant}</code></td><td>商品规格 (下单时语言)</td></tr>
                    </tbody>
                </table>
                </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                日结报告 (EOD_REPORT) 变量
            </div>
            <div class="card-body">
                <p class="card-text">用于 `EOD_REPORT` 类型的模板。</p>
                <table class="table table-sm table-bordered">
                    <thead><tr><th>变量</th><th>说明</th></tr></thead>
                    <tbody>
                        <tr><td><code>{report_date}</code></td><td>报告所属日期 (YYYY-MM-DD)</td></tr>
                        <tr><td><code>{store_name}</code></td><td>门店名称</td></tr>
                        <tr><td><code>{user_name}</code></td><td>执行日结的收银员姓名</td></tr>
                        <tr><td><code>{print_time}</code></td><td>小票打印的当前时间</td></tr>
                        <tr><td><code>{transactions_count}</code></td><td>总交易笔数</td></tr>
                        <tr><td><code>{system_gross_sales}</code></td><td>总销售额 (含税)</td></tr>
                        <tr><td><code>{system_discounts}</code></td><td>总折扣额</td></tr>
                        <tr><td><code>{system_net_sales}</code></td><td>净销售额 (总销售 - 总折扣)</td></tr>
                        <tr><td><code>{system_tax}</code></td><td>总税额</td></tr>
                        <tr><td><code>{system_cash}</code></td><td>系统记录的现金收款总额</td></tr>
                        <tr><td><code>{system_card}</code></td><td>系统记录的刷卡收款总额</td></tr>
                        <tr><td><code>{system_platform}</code></td><td>系统记录的平台收款总额</td></tr>
                        <tr><td><code>{counted_cash}</code></td><td>收银员清点的现金金额</td></tr>
                        <tr><td><code>{cash_discrepancy}</code></td><td>现金差异 (清点 - 系统)</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-warning text-dark">
                杯贴标签 (CUP_STICKER) 变量
            </div>
            <div class="card-body">
                <p class="card-text">用于 `CUP_STICKER` 类型的模板。此数据包由 `submit_order.php` 针对订单中的 *每一项商品* 单独生成。</p>
                <div class="alert alert-success small">
                    <b>提示:</b> 杯贴变量是实时生成的，<b>支持双语</b>。
                </div>
                <table class="table table-sm table-bordered">
                    <thead><tr><th>变量</th><th>说明</th></tr></thead>
                    <tbody>
                        <tr><td><code>{pickup_number}</code></td><td><b>(新)</b> 取餐号 (e.g., 1001)</td></tr>
                        <tr><td><code>{kds_id}</code></td><td><b>(新)</b> 内部KDS唯一码 (e.g., S1-1001-1)</td></tr>
                        <tr><td><code>{store_prefix}</code></td><td><b>(新)</b> 门店前缀 (e.g., S1)</td></tr>
                        <tr><td><code>{cup_index}</code></td><td><b>(新)</b> 杯序号 (e.g., 1, 2)</td></tr>
                        <tr><td><code>{product_code}</code></td><td><b>(新)</b> 产品P-Code (e.g., A1)</td></tr>
                        <tr><td><code>{cup_code}</code></td><td><b>(新)</b> 杯型A-Code (e.g., 1)</td></tr>
                        <tr><td><code>{ice_code}</code></td><td><b>(新)</b> 冰量M-Code (e.g., 1)</td></tr>
                        <tr><td><code>{sweet_code}</code></td><td><b>(新)</b> 甜度T-Code (e.g., 1)</td></tr>
                        <tr><td colspan="2"><hr class="my-1"></td></tr>
                        <tr><td><code>{cup_order_number}</code></td><td><span class="text-muted text-decoration-line-through">杯号 (例如: A2025-XXXX)</span> <span class="badge text-bg-warning">已弃用</span><br><small class="text-muted">请改用 <code>{kds_id}</code> 或 <code>{pickup_number}</code></small></td></tr>
                        <tr><td><code>{customization_detail}</code></td><td>定制详情 (<b>单语言</b>, 如: 少冰/50%糖)</td></tr>
                        <tr><td><code>{remark}</code></td><td>备注信息</td></tr>
                        <tr><td><code>{store_name}</code></td><td>门店名称</td></tr>
                        <tr><td colspan="2"><hr class="my-1"></td></tr>
                        <tr><td><code>{item_name_zh}</code></td><td><b>(推荐)</b> 商品名称 (中文)</td></tr>
                        <tr><td><code>{item_name_es}</code></td><td><b>(推荐)</b> 商品名称 (西语)</td></tr>
                        <tr><td><code>{variant_name_zh}</code></td><td><b>(推荐)</b> 规格名称 (中文)</td></tr>
                        <tr><td><code>{variant_name_es}</code></td><td><b>(推荐)</b> 规格名称 (西语)</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                效期标签 (EXPIRY_LABEL) 变量
            </div>
            <div class="card-body">
                <p class="card-text">用于 `EXPIRY_LABEL` 类型的模板。此数据包由 KDS 端的 `record_expiry_item.php` 在开封/制备时生成。</p>
                <table class="table table-sm table-bordered">
                    <thead><tr><th>变量</th><th>说明</th></tr></thead>
                    <tbody>
                        <tr><td><code>{material_name}</code></td><td>物料名称 (中文)</td></tr>
                        <tr><td><code>{material_name_es}</code></td><td>物料名称 (西语)</td></tr>
                        <tr><td><code>{opened_at_time}</code></td><td>开封/制备时间 (YYYY-MM-DD HH:MM)</td></tr>
                        <tr><td><code>{expires_at_time}</code></td><td>过期时间 (YYYY-MM-DD HH:MM)</td></tr>
                        <tr><td><code>{time_left}</code></td><td>剩余时间 (格式化文本, e.g., 3小时5分钟)</td></tr>
                        <tr><td><code>{operator_name}</code></td><td>操作员工姓名</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<hr class="my-4">

<h3 class="mb-3">默认模板示例</h3>
<div class="row">
    <div class="col-12">
        <?php 
        // [Q2 FIX] These will now render the fallback defaults defined above if not found in the DB
        render_json_template('默认顾客小票 (RECEIPT)', $default_templates['RECEIPT'] ?? '');
        render_json_template('默认厨房出品单 (KITCHEN_ORDER)', $default_templates['KITCHEN_ORDER'] ?? '');
        render_json_template('默认日结报告 (EOD_REPORT)', $default_templates['EOD_REPORT'] ?? '');
        render_json_template('默认效期标签 (EXPIRY_LABEL)', $default_templates['EXPIRY_LABEL'] ?? '');
        ?>
    </div>
</div>