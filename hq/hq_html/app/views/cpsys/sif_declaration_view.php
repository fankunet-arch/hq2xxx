<?php
/**
 * Toptea HQ - SIF Compliance Declaration View
 * Engineer: Gemini | Date: 2025-11-06 | Revision: 2.1.0
 *
 * 要点：
 * - 不包含收尾 "?>"，避免输出缓冲/空白字符引起的 500。
 * - 与 index.php 的变量名对齐：$sif_declaration_text / $sif_save_ok / $sif_error。
 * - 兼容旧控制器：当 $sif_declaration_text === false（fetchColumn 未找到）时，使用默认模板；
 *   当 $sif_declaration_text === ''（已保存为空串）时，保持为空，不自动套模板。
 * - 即使没有 sif_declaration.js，也可通过标准 POST 提交保存（action=?page=sif_declaration）。
 */

// 兜底保证这些变量存在，防止视图层 Notice/Warning
$sif_declaration_text = $sif_declaration_text ?? null; // 可能是 string|''|false|null
$sif_save_ok          = $sif_save_ok ?? false;
$sif_error            = $sif_error ?? null;

// 默认西语模板（仅在“数据库未找到记录 => false”时作为占位使用）
$default_declaration_text = <<<TEXT
DECLARACIÓN RESPONSABLE DEL SISTEMA INFORMÁTICO DE FACTURACIÓN

a) Nombre del sistema: TOPTEA POS/KDS
b) Código identificador del SIF: SIF-TOPTEA-2025-V2
c) Versión: v2.5.0
d) Componentes HW/SW y funcionalidades principales:
   - HW: Terminales TPV Android, Impresoras térmicas.
   - SW: Módulo POS (store.toptea.es), Módulo KDS (store.toptea.es), Módulo CPSYS (hq.toptea.es).
   - Funcionalidades: Gestión de pedidos, cobros, emisión de facturas simplificadas (SIF), gestión de cocina (KDS), gestión de recetas (RMS) y configuración centralizada (CPSYS).
e) Modalidad de uso: [X] Dual (VERIFACTU y no verificable, según configuración de tienda)
f) Ámbito de uso: [X] Varios OT; [X] Multitienda/Multiterminal
g) Tipos de firma aplicados a RF y registro de eventos: Firma electrónica (Simulada para desarrollo)
h) Productor (nombre/razón social): [TU NOMBRE DE EMPRESA AQUÍ]
i) NIF/otro TIN y país: [TU NIF/CIF AQUÍ] (ESPAÑA)
j) Domicilio y datos de contacto del productor: [TU DIRECCIÓN COMPLETA AQUÍ], email: [TU EMAIL DE SOPORTE AQUÍ]
k) Manifestación de conformidad: El productor certifica, bajo su responsabilidad, que este SIF cumple el art. 29.2.j) de la Ley 58/2003, el RD 1007/2023 y la Orden HAC/1177/2024 (y demás normas de desarrollo).
l) Lugar y fecha: Bilbao, España / 03 / 11 / 2025
TEXT;

// 选择用于渲染到 textarea 的文本：
// - false  => 未找到记录，用默认模板
// - null   => 控制器未设置，保守起见也用默认模板
// - ''     => 明确保存的空串，保持空
// - string => 用数据库内容
if ($sif_declaration_text === false || $sif_declaration_text === null) {
    $current_declaration_text = $default_declaration_text;
} else {
    // ''（空串）也应尊重为空，不要灌默认模板
    $current_declaration_text = $sif_declaration_text;
}
?>

<div class="row justify-content-center">
    <div class="col-lg-10">

        <?php if ($sif_save_ok): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong>Guardado.</strong> La declaración responsable se ha actualizado correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($sif_error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Error.</strong> No se pudo cargar/guardar la declaración.
                <div class="small mt-2"><code><?= htmlspecialchars($sif_error) ?></code></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <form id="sif-declaration-form" method="post" action="?page=sif_declaration">
            <div class="card mb-4">
                <div class="card-header">
                    Gestión de la Declaración Responsable (SIF)
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h4 class="alert-heading">Información Legal</h4>
                        <p>
                            Este texto es la <em>Declaración Responsable</em> requerida por la normativa española
                            (RD 1007/2023 y Orden HAC/1177/2024). Debe ser accesible desde el terminal POS.
                        </p>
                        <p>
                            Revise y complete con los datos reales del <strong>Productor del SIF</strong> (su empresa):
                            nombre social, NIF, domicilio, contacto, y la modalidad de uso (dual/verificable).
                        </p>
                    </div>

                    <div class="mb-3">
                        <label for="sif_declaration_text" class="form-label">
                            Contenido de la Declaración Responsable
                        </label>
                        <textarea
                            class="form-control"
                            id="sif_declaration_text"
                            name="sif_text"
                            rows="24"
                            style="font-family: monospace; font-size: .9rem;"
                        ><?= htmlspecialchars($current_declaration_text) ?></textarea>
                        <div class="form-text">
                            Edite el contenido conforme al Art. 15 de la Orden HAC/1177/2024.
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 justify-content-end mt-3">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-save me-2"></i> Guardar Declaración
                </button>
                <a href="?page=sif_declaration" class="btn btn-outline-secondary btn-lg">
                    <i class="bi bi-arrow-counterclockwise me-2"></i> Descartar cambios
                </a>
            </div>
        </form>

        <div id="settings-feedback" class="mt-3"></div>
    </div>
</div>
