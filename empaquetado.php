<?php
$pageTitle    = 'Empaquetado';
$pageSubtitle = 'Registro de empaquetado por producto (con mezcla de colores)';
$activePage = 'empaquetado';

include("header.php");
?>



<style>
/* ── Tabla de registros ── */
.pc-emp-tabla-wrap{ max-height:260px; overflow-y:auto; border:1px solid #eee7db; border-radius:10px; margin-bottom:16px; }
.pc-emp-tabla{ width:100%; font-size:.85em; border-collapse:collapse; }
.pc-emp-tabla th{ position:sticky; top:0; background:#fdfcfa; text-align:left; padding:8px 10px; border-bottom:1px solid #eee7db; font-size:.78em; color:#8a8578; text-transform:uppercase; }
.pc-emp-tabla td{ padding:8px 10px; border-bottom:1px dashed #eee2c8; vertical-align:top; }
.pc-emp-tabla tr:last-child td{ border-bottom:none; }
.pc-emp-tabla .bultos-detalle{ font-size:.85em; color:#8a8578; }
.pc-emp-tabla .acciones{ display:flex; gap:6px; white-space:nowrap; }
.badge-vendido{ background:#FDF1E0; color:#D97706; border:1px solid #f4dcb0; border-radius:8px; padding:2px 8px; font-size:.75em; font-weight:700; }
.pc-icon-btn{ border:1px solid #eee2c8; background:#fff; border-radius:8px; padding:5px 8px; color:#6b6656; }
.pc-icon-btn:hover{ background:#fdfcfa; }

/* ── Bultos con mezcla de colores ── */
.pc-bulto-card{ border:1px solid #eee2c8; border-radius:10px; padding:10px 12px; margin-bottom:10px; background:#fffefb; }
.pc-bulto-card-head{ display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; font-size:.85em; font-weight:700; color:#3a3730; }
.pc-color-row{ display:flex; gap:8px; align-items:center; margin-bottom:6px; }
.pc-color-row select{ flex:2; min-width:0; }
.pc-color-row input{ flex:1; min-width:0; }
.pc-bulto-remove{ border:none; background:none; color:#c94a4a; font-size:1em; flex:0 0 auto; }
.pc-bultos-total{
    display:flex; justify-content:space-between; align-items:center;
    padding:8px 12px; background:#fffaf0; border:1px solid #f4e8c8; border-radius:10px;
    font-size:.9em; margin-top:6px;
}
.pc-bultos-total b{ color:#2F6FED; font-size:1.05em; }
.pc-origenes-readonly{ background:#fdfcfa; border:1px solid #eee7db; border-radius:10px; padding:10px 12px; font-size:.85em; color:#6b6656; line-height:1.6; }

/* ── Listado general ── */
.pc-listado-filtros{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:12px; }
.pc-listado-filtros select, .pc-listado-filtros input[type="date"]{ max-width:180px; }

/* ── Tabs por producto ── */
.pc-emp-tabsbar{
    display:flex; align-items:center; justify-content:space-between; gap:16px;
    flex-wrap:wrap; border-bottom:1px solid #eee7db; margin-bottom:16px; padding-bottom:0;
}
.pc-emp-tabs{ display:flex; align-items:center; gap:4px; overflow-x:auto; flex:1; scrollbar-width:thin; }
.pc-emp-tab{
    display:flex; align-items:center; gap:8px; padding:10px 14px;
    border:none; background:transparent; white-space:nowrap;
    font-size:.86em; font-weight:600; color:#8a8578;
    border-bottom:2px solid transparent; cursor:pointer; transition:.12s ease;
}
.pc-emp-tab:hover{ color:#3a3730; }
.pc-emp-tab.activo{ color:#1f2937; border-bottom-color:#2F6FED; }
.pc-emp-tab .tab-icono{ font-size:.9em; color:#b7b1a1; }
.pc-emp-tab.activo .tab-icono{ color:#2F6FED; }
.pc-emp-tab-count{
    background:#f1efe9; color:#8a8578; border-radius:999px;
    font-size:.78em; font-weight:700; padding:1px 8px; min-width:20px; text-align:center;
}
.pc-emp-tab.activo .pc-emp-tab-count{ background:#1f2937; color:#fff; }
.pc-emp-tabs-right{ display:flex; align-items:center; gap:14px; flex-wrap:wrap; padding-bottom:10px; }
.pc-emp-tabs-right input[type="text"]{ max-width:220px; }

/* ── Estación visual de sacos/colores (mezcla y bultos) ── */
.pc-estacion{ display:grid; grid-template-columns:1.3fr 1fr; gap:16px; align-items:start; }
@media (max-width:760px){ .pc-estacion{ grid-template-columns:1fr; } }
.pc-estacion-hint{ font-size:.78em; color:#9a9585; margin:0 0 8px; }
.pc-sacos-grid{ display:grid; grid-template-columns:repeat(auto-fill, minmax(130px,1fr)); gap:10px; }
.pc-saco-card{
    border:1px solid #eee2c8; border-radius:12px; padding:9px; background:#fff;
    cursor:pointer; transition:.12s ease; text-align:left; position:relative;
}
.pc-saco-card:hover{ border-color:#2F6FED; box-shadow:0 3px 10px rgba(0,0,0,.06); }
.pc-saco-card.agotado{ opacity:.45; cursor:not-allowed; pointer-events:none; }
.pc-saco-card .swatch{ width:100%; height:34px; border-radius:8px; }
.pc-saco-card .nombre{ font-size:.8em; font-weight:700; margin:7px 0 1px; color:#3a3730; }
.pc-saco-card .origen{ font-size:.72em; color:#9a9585; }
.pc-saco-card .disp{ font-size:.72em; color:#6b6656; margin-top:2px; }
.pc-saco-card .meta{ font-size:.64em; color:#b7b1a1; margin:2px 0 0; line-height:1.3; }
.pc-saco-card .en-mezcla{
    position:absolute; top:6px; right:6px; background:#2F6FED; color:#fff;
    font-size:.68em; font-weight:700; border-radius:999px; padding:1px 7px;
}
.pc-mezcla-panel{ background:#fffefb; border:1px solid #eee7db; border-radius:12px; padding:12px 14px; }
.pc-mezcla-panel-titulo{ font-size:.78em; color:#9a9585; margin:0 0 8px; text-transform:uppercase; letter-spacing:.03em; }
.pc-mezcla-lista{ display:flex; flex-direction:column; gap:6px; min-height:32px; }
.pc-mezcla-fila{ display:flex; align-items:center; gap:8px; font-size:.85em; }
.pc-mezcla-fila .swatch-mini{ width:14px; height:14px; border-radius:4px; flex:0 0 auto; border:1px solid rgba(0,0,0,.08); }
.pc-mezcla-fila .nombre{ flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.pc-mezcla-fila input{ width:76px; flex:0 0 auto; }
.pc-paquete-box{ margin-top:12px; background:#fffaf0; border:1px solid #f4e8c8; border-radius:10px; padding:10px 12px; }
.pc-paquete-box .fila{ display:flex; justify-content:space-between; align-items:center; font-size:.85em; }
.pc-paquete-barra{ height:8px; background:#f1efe9; border-radius:4px; margin-top:8px; overflow:hidden; }
.pc-paquete-barra > div{ height:100%; background:#2F6FED; transition:width .15s ease; }
.pc-swatch-picker{ display:flex; flex-wrap:wrap; gap:5px; margin-bottom:5px; }
.pc-swatch-chip{
    width:24px; height:24px; border-radius:7px; border:2px solid transparent;
    cursor:pointer; padding:0; position:relative;
}
.pc-swatch-chip:hover{ border-color:#b7b1a1; }
.pc-swatch-chip.activo{ border-color:#2F6FED; }
.pc-swatch-chip.agotado{ opacity:.35; cursor:not-allowed; pointer-events:none; }

/* ── Chips de operarios (selección múltiple) ── */
.pc-operario-chips-wrap{ display:flex; flex-wrap:wrap; gap:6px; min-height:34px; align-items:flex-start; padding-top:2px; }
.pc-operario-chip{
    border:1px solid #eee2c8; background:#fff; border-radius:999px; padding:5px 12px;
    font-size:.82em; cursor:pointer; color:#3a3730; transition:.12s ease; white-space:nowrap;
}
.pc-operario-chip:hover{ border-color:#2F6FED; }
.pc-operario-chip.activo{ background:#2F6FED; border-color:#2F6FED; color:#fff; font-weight:600; }

/* ── Estación de armado inline (nueva) ── */
.pc-estacion-vacia{ text-align:center; color:#9a9585; padding:34px 12px; font-size:.9em; }
</style>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Empaquetado</h2>
    </div>

    <div class="pc-emp-tabsbar">
        <div class="pc-emp-tabs" id="tabsEmpaquetado">
            <button type="button" class="pc-emp-tab activo" data-tab="__todos__" onclick="seleccionarTabEmp('__todos__')">
                <i class="fa-solid fa-grip"></i>
                <span>Todos</span>
                <span class="pc-emp-tab-count" id="tabCountTodos">0</span>
            </button>
        </div>
        <div class="pc-emp-tabs-right">
            <input type="text" id="femp_texto" class="form-control form-control-sm" placeholder="Buscar por producto...">
            <div class="form-check d-flex align-items-center gap-2 m-0">
                <input class="form-check-input" type="checkbox" id="femp_solo_sin">
                <label class="form-check-label" for="femp_solo_sin" style="font-size:.85em;">Solo sin empaquetar</label>
            </div>
        </div>
    </div>

</div>

<!-- ═══════════════ ESTACIÓN DE ARMADO (inline, reemplaza al modal para crear registros) ═══════════════ -->

<div class="pc-card mt-3" id="estacionVaciaCard">
    <div class="pc-estacion-vacia">
        <i class="fa-solid fa-hand-pointer" style="font-size:1.4em; display:block; margin-bottom:8px;"></i>
        Selecciona un producto en las pestañas de arriba para empezar a armar un paquete.
    </div>
</div>

<div class="pc-card mt-3" id="estacionArmadoCard" style="display:none;">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 id="estacionArmadoTitulo">Armar paquete</h2>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="abrirModalRegistrosProducto()">
            <i class="fa-solid fa-list"></i> Ver registros de este producto
        </button>
    </div>

    <form id="formEstacionArmado">
        <input type="hidden" id="est_producto_id" value="0">
        <div class="row">
            <div class="col-md-4 mb-2">
                <label class="form-label">Unidad de medida *</label>
                <select class="form-select" id="est_unidad_medida" required></select>
                <small class="text-muted" id="avisoUnidadEstacion" style="display:none;">
                    Este producto no tiene "Salida en Empaquetado" configurada — selecciónala aquí y configúrala en Productos para la próxima vez.
                </small>
            </div>
            <div class="col-md-5 mb-2">
                <label class="form-label">Operarios *</label>
                <div id="est_operarios_chips" class="pc-operario-chips-wrap"></div>
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Sucursal</label>
                <select class="form-select" id="est_sucursal_id"></select>
            </div>
        </div>

        <!-- Modo BULTO (docena / unidad por color, con reparto uniforme) -->
        <div id="bloqueBultos">
            <label class="form-label">Paquetes (toca un saco para agregar) *</label>
            <p class="pc-estacion-hint">Toca un saco para agregarlo al paquete actual. Puedes ajustar la cantidad exacta abajo.</p>
            <div class="pc-sacos-grid" id="sacosBultoGrid" style="margin-bottom:14px;"></div>

            <div id="listaBultos"></div>
            <button type="button" class="btn btn-sm btn-outline-secondary mb-2" onclick="agregarBulto()">
                <i class="fa-solid fa-plus"></i> Agregar Paquete
            </button>
            <div class="pc-bultos-total">
                <span><span id="bultosCount">0</span> paquete(s)</span>
                <span>Total: <b id="bultosTotal">0</b></span>
            </div>
        </div>

        <!-- Modo MEZCLA (kg mezclados → bolsas de 144 → paquetes de 24 bolsas) -->
        <div id="bloqueMezcla" style="display:none;">
            <label class="form-label">Mezcla de sacos (kg por color/origen) *</label>
            <p class="pc-estacion-hint">Toca un saco para llevarlo a la mezcla. Puedes ajustar el kg exacto después.</p>
            <div class="pc-estacion">
                <div>
                    <div class="pc-sacos-grid" id="sacosMezclaGrid"></div>
                </div>
                <div class="pc-mezcla-panel">
                    <p class="pc-mezcla-panel-titulo">Mezcla actual</p>
                    <div class="pc-mezcla-lista" id="listaMezclaOrigenes"></div>

                    <div class="pc-paquete-box">
                        <div class="fila">
                            <span>Kg totales mezclados</span>
                            <b id="mezclaKgTotal">0</b>
                        </div>
                        <div class="fila mt-2">
                            <label class="form-label mb-0">Bolsas producidas (144 und c/u) *</label>
                            <input type="number" min="1" step="1" class="form-control form-control-sm" style="max-width:110px;"
                                id="mezclaBolsasProducidas" oninput="actualizarBolsasProducidas(this.value)" placeholder="Ej. 50">
                        </div>
                        <div class="fila mt-2" style="font-size:.8em;">
                            <span class="text-muted">Estimado teórico</span>
                            <span><b id="mezclaBolsasTeoricas">-</b> bolsas <span id="mezclaDiferenciaBadge" class="badge" style="display:none; margin-left:6px;"></span></span>
                        </div>
                        <div class="fila mt-2" style="font-size:.78em; color:#9a9585;">
                            <span>Paquete de 24 bolsas</span>
                            <span id="mezclaPaqueteFrac">0 / 24</span>
                        </div>
                        <div class="pc-paquete-barra"><div id="mezclaPaqueteBarra" style="width:0%;"></div></div>
                        <div class="text-muted mt-1" style="font-size:.75em;">
                            ≈ <span id="mezclaPaquetesEstimados">0</span> paquete(s) de 24 bolsas (solo referencia para transporte)
                        </div>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="agregarOrigenMezcla()">
                <i class="fa-solid fa-plus"></i> Agregar saco/color manualmente
            </button>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-floppy-disk"></i> Guardar paquete
            </button>
        </div>
    </form>
</div>


<div class="pc-card mt-3">
    <div class="pc-card-header">
        <h2>Registros de empaquetado</h2>
    </div>

    <div class="pc-listado-filtros">
        <select class="form-select form-select-sm" id="flist_estado" style="max-width:160px">
            <option value="">Todos los estados</option>
            <option value="disponible">Disponible</option>
            <option value="vendido">Vendido</option>
        </select>
        <input type="date" class="form-control form-control-sm" id="flist_fecha_desde" title="Desde">
        <input type="date" class="form-control form-control-sm" id="flist_fecha_hasta" title="Hasta">
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="limpiarFiltrosListado()">
            <i class="fa-solid fa-eraser"></i> Limpiar filtros
        </button>
    </div>

    <div class="pc-emp-tabla-wrap" style="max-height:420px;">
        <table class="pc-emp-tabla">
            <thead>
                <tr>
                    <th>Origen</th>
                    <th>Producto</th>
                    <th>Sucursal</th>
                    <th>Unidades Paquetes</th>
                    <th>Total</th>
                    <th>Operarios</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tablaListadoGeneralEmp">
                <tr><td colspan="9" class="text-center text-muted">Cargando...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal por PRODUCTO: SOLO lista de registros + edición (unidad/operarios/sucursal) + eliminación.
     La creación de registros nuevos ahora vive en la Estación de armado, en la página principal. -->
<div class="modal fade" id="modalEmpaquetado" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalEmpaquetadoTitulo">Registros</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <h6 class="mb-2"><i class="fa-solid fa-list"></i> Registros de empaquetado</h6>
        <div class="pc-emp-tabla-wrap">
            <table class="pc-emp-tabla">
                <thead>
                    <tr>
                        <th>Unidades Paquetes</th>
                        <th>Total</th>
                        <th>Operarios</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tablaRegistrosEmp">
                    <tr><td colspan="6" class="text-center text-muted">Cargando...</td></tr>
                </tbody>
            </table>
        </div>

        <div id="bloqueEdicionRegistro" style="display:none;">
            <hr>
            <h6 class="mb-2" id="formEmpTitulo"><i class="fa-solid fa-pen"></i> Editando registro</h6>
            <form id="formEditarEmp">
                <input type="hidden" id="emp_id" value="0">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Unidad de medida *</label>
                        <select class="form-select" id="emp_unidad_medida" required></select>
                    </div>
                    <div class="col-md-5 mb-2">
                        <label class="form-label">Operarios *</label>
                        <div id="emp_operarios_chips" class="pc-operario-chips-wrap"></div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label">Sucursal</label>
                        <select class="form-select" id="emp_sucursal_id"></select>
                    </div>
                </div>

                <div id="bloqueOrigenesReadonly" style="display:none;"></div>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar cambios
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="cancelarEdicionEmp()">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>

      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const CONTROLADOR_EMPAQUETADO = 'controllers/clssEmpaquetado.php';
const modalEmpaquetado = new bootstrap.Modal(document.getElementById('modalEmpaquetado'));

// ── Estado de la ESTACIÓN DE ARMADO (creación de registros, inline en la página principal) ──
let estacionProductoIdActual = 0;
let empUnidadesCache = null;
let empOperariosCache = null;
let origenesDisponiblesCache = []; // BUSCARORIGENESDISPONIBLES del producto de la estación activa
let unidadEmpaquetadoProductoActual = null;
let reglasEmpaquetadoActuales = null;
let bultosState = [];  // [{tempId, colores:[{tempColorId, origen_tipo, origen_id, color_id, color_nombre, cantidad}]}]
let mezclaOrigenes = [];
let contadorMezclaOrigen = 0;
let bolsasProducidasValor = '';
let contadorBulto = 0;
let contadorColorRow = 0;
let estOperariosSeleccionados = []; // ids de operario elegidos en la estación de armado

// ── Estado del MODAL (solo listado / edición / eliminación de registros ya guardados) ──
let modalProductoIdActual = 0;
let empIdEnEdicion = 0;
let empOperariosSeleccionados = []; // ids de operario elegidos en la edición

// ── Tabs por producto (100% client-side) ──
let cacheFilasEmpaquetado = [];
let tabActivoEmp = '__todos__';

document.addEventListener('DOMContentLoaded', () => {
    cargarPendientesEmpaquetado();
    cargarListadoGeneralEmp();

    let debounceTimer = null;
    document.getElementById('femp_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            cargarPendientesEmpaquetado();
            cargarListadoGeneralEmp();
        }, 350);
    });
    document.getElementById('femp_solo_sin').addEventListener('change', () => {
        cargarPendientesEmpaquetado();
    });

    ['flist_estado', 'flist_fecha_desde', 'flist_fecha_hasta'].forEach(id => {
        document.getElementById(id).addEventListener('change', cargarListadoGeneralEmp);
    });
});
function limpiarFiltrosListado() {
    document.getElementById('flist_estado').value = '';
    document.getElementById('flist_fecha_desde').value = '';
    document.getElementById('flist_fecha_hasta').value = '';
    cargarListadoGeneralEmp();
}

async function llamarEmpaquetado(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_EMPAQUETADO, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body
    });
    const texto = await resp.text();
    try {
        return JSON.parse(texto);
    } catch (e) {
        console.error(`Respuesta no es JSON válido para accion=${accion}:`, texto);
        throw new Error(`El servidor no devolvió JSON válido (accion=${accion}). Revisa la consola.`);
    }
}

function formatearCantidadEmp(n) {
    if (n === null || n === undefined || n === '') return '-';
    return Number(n).toLocaleString('es-PE', { maximumFractionDigits: 4 });
}

function formatearFechaHoraLegibleEmp(fechaIso) {
    if (!fechaIso) return '';
    const [fecha, hora] = String(fechaIso).split(' ');
    if (!fecha) return fechaIso;
    const [y, m, d] = fecha.split('-');
    return `${d}/${m}/${y}${hora ? ' ' + hora.substring(0, 5) : ''}`;
}

function parseJsonColumnaEmp(v) {
    if (!v) return [];
    if (typeof v === 'string') {
        try { return JSON.parse(v) || []; } catch (e) { return []; }
    }
    return Array.isArray(v) ? v : [];
}

function textoEquivalenteEmp(r) {
    if (r.unidad_base_id && r.cantidad_tota_en_base != null) {
        return ` <span class="text-muted" style="font-size:.85em;">(= ${formatearCantidadEmp(r.cantidad_tota_en_base)} ${r.unidad_base_corto ?? ''})</span>`;
    }
    return '';
}

// Texto compacto del detalle de un registro: "12 (VERDE: 6, AZUL: 6) + 8 (ROJO: 8)"
function textoBultosDetalle(bultos) {
    if (!bultos || bultos.length === 0) return '-';
    return bultos.map(b => {
        if (b.kg_total_mezclados !== undefined) {
            const colores = (b.colores || [])
                .map(c => `${c.color_nombre ?? 'Sin color'}: ${formatearCantidadEmp(c.cantidad_kg)} kg`)
                .join(', ');
            return `${formatearCantidadEmp(b.cantidad)} bolsas (mezcla — ${colores})`;
        }
        const colores = (b.colores || [])
            .map(c => `${c.color_nombre ?? 'Sin color'}: ${formatearCantidadEmp(c.cantidad)}`)
            .join(', ');
        return `${formatearCantidadEmp(b.cantidad)}${colores ? ` (${colores})` : ''}`;
    }).join(' + ');
}

// Texto de operarios de un registro: usa js_operarios (varios) con fallback
// a operario_nombre (registros legacy de un solo operario).
function textoOperariosEmp(r) {
    const lista = parseJsonColumnaEmp(r.js_operarios);
    if (lista.length > 0) {
        return lista.map(o => o.nombre_completo).join(', ');
    }
    return r.operario_nombre ?? '-';
}

// ── Colores: hex real del backend (color.rgb) con degradado a una paleta
// fija por nombre, para que los sacos siempre se vean distinguibles aunque
// el color aún no tenga rgb configurado en el catálogo. ──
const PALETA_COLOR_FALLBACK = {
    'VERDE': '#639922', 'AZUL': '#378ADD', 'CELESTE': '#5DB8E8', 'ROJO': '#E24B4A',
    'AMARILLO': '#EF9F27', 'NARANJA': '#D85A30', 'MORADO': '#7F77DD', 'VIOLETA': '#7F77DD',
    'ROSADO': '#D4537E', 'ROSA': '#D4537E', 'NEGRO': '#2C2C2A', 'BLANCO': '#D3D1C7',
    'GRIS': '#888780', 'MARRON': '#712B13', 'CAFE': '#712B13', 'TURQUESA': '#1D9E75',
    'PLOMO': '#5F5E5A', 'BEIGE': '#B4B2A9',
};
function colorHexPara(colorNombre, colorHexBD) {
    if (colorHexBD) return colorHexBD.startsWith('#') ? colorHexBD : `#${colorHexBD}`;
    const clave = String(colorNombre ?? '').trim().toUpperCase();
    if (PALETA_COLOR_FALLBACK[clave]) return PALETA_COLOR_FALLBACK[clave];
    // Degradado determinístico (mismo nombre = mismo color) para
    // nombres que no están en la paleta fija (ej. "Pruebas").
    let hash = 0;
    for (let i = 0; i < clave.length; i++) hash = clave.charCodeAt(i) + ((hash << 5) - hash);
    const hue = Math.abs(hash) % 360;
    return `hsl(${hue}, 45%, 55%)`;
}

// =============================================================================
// OPERARIOS (chips de selección múltiple, compartidos por estación y modal)
// =============================================================================

// Pinta la lista de operarios como chips togglables dentro de containerId.
// seleccionados: array de ids ya elegidos. toggleFnName: nombre (string) de
// la función global a invocar en el onclick de cada chip.
function renderOperariosChips(containerId, seleccionados, toggleFnName) {
    const cont = document.getElementById(containerId);
    if (!cont) return;
    if (!empOperariosCache || empOperariosCache.length === 0) {
        cont.innerHTML = '<div class="text-muted" style="font-size:.85em;">(sin operarios disponibles para esta etapa - revisar consola)</div>';
        return;
    }
    cont.innerHTML = empOperariosCache.map(o => `
        <button type="button" class="pc-operario-chip ${seleccionados.includes(o.id) ? 'activo' : ''}"
                onclick="${toggleFnName}(${o.id})">
            ${o.nombre_completo}
        </button>`).join('');
}

function toggleOperarioEstacion(id) {
    const i = estOperariosSeleccionados.indexOf(id);
    if (i >= 0) estOperariosSeleccionados.splice(i, 1); else estOperariosSeleccionados.push(id);
    renderOperariosChips('est_operarios_chips', estOperariosSeleccionados, 'toggleOperarioEstacion');
}

function toggleOperarioModalEdicion(id) {
    const i = empOperariosSeleccionados.indexOf(id);
    if (i >= 0) empOperariosSeleccionados.splice(i, 1); else empOperariosSeleccionados.push(id);
    renderOperariosChips('emp_operarios_chips', empOperariosSeleccionados, 'toggleOperarioModalEdicion');
}

// =============================================================================
// GRID DE ENSAMBLAJES FINALIZADOS + TABS + ESTACIÓN DE ARMADO
// =============================================================================
async function cargarPendientesEmpaquetado() {
    const params = {
        texto: document.getElementById('femp_texto').value.trim(),
        solo_sin_empaquetar: document.getElementById('femp_solo_sin').checked ? '1' : '0',
    };

    const [jsonEns, jsonProd] = await Promise.all([
        llamarEmpaquetado('LISTARENSAMBLAJESPARAEMPAQUETADO', params),
        llamarEmpaquetado('LISTARPRODUCCIONESPARAEMPAQUETADO', params),
    ]);

    if (!jsonEns.success) console.error('Error LISTARENSAMBLAJESPARAEMPAQUETADO:', jsonEns.message);
    if (!jsonProd.success) console.error('Error LISTARPRODUCCIONESPARAEMPAQUETADO:', jsonProd.message);

    // Normaliza ambas fuentes a una forma común para que tabs / estación
    // de armado no necesiten saber de dónde vino cada fila.
    const filasEns = (jsonEns.success ? jsonEns.ensamblajes : []).map(f => ({
        origen_tipo: 'ensamblaje',
        origen_id: f.ensamblaje_id,
        producto_id: f.producto_id,
        producto_codigo: f.producto_codigo,
        producto_descripcion: f.producto_descripcion,
        unidad_salida_codigo: f.unidad_salida_codigo,
        cantidad_disponible: f.cantidad_disponible,
        cantidad_total_empaquetada: f.cantidad_total_empaquetada,
        empaquetados_count: f.empaquetados_count,
        fecha_fin: f.fin,
    }));

    const filasProd = (jsonProd.success ? jsonProd.producciones : []).map(f => ({
        origen_tipo: 'produccion',
        origen_id: f.produccion_id,
        producto_id: f.producto_id,
        producto_codigo: f.producto_codigo,
        producto_descripcion: f.producto_descripcion,
        unidad_salida_codigo: f.unidad_salida_codigo,
        cantidad_disponible: f.cantidad_disponible,
        cantidad_total_empaquetada: f.cantidad_total_empaquetada,
        empaquetados_count: f.empaquetados_count,
        fecha_fin: f.fecha_hora_fin,
    }));

    cacheFilasEmpaquetado = [...filasEns, ...filasProd];

    if (tabActivoEmp !== '__todos__' && !cacheFilasEmpaquetado.some(f => claveTabEmp(f) === tabActivoEmp)) {
        tabActivoEmp = '__todos__';
    }

    renderTabsEmp();
    actualizarEstacionArmado();
}

function claveTabEmp(fila) {
    return fila.producto_id != null ? `id:${fila.producto_id}` : `cod:${fila.producto_codigo ?? ''}`;
}

function renderTabsEmp() {
    const cont = document.getElementById('tabsEmpaquetado');

    const grupos = new Map();
    cacheFilasEmpaquetado.forEach(f => {
        const clave = claveTabEmp(f);
        const label = `${f.producto_codigo ?? ''} - ${f.producto_descripcion ?? 'Sin nombre'}`;
        if (!grupos.has(clave)) grupos.set(clave, { label, count: 0 });
        grupos.get(clave).count++;
    });

    const tabs = [...grupos.entries()].sort((a, b) => a[1].label.localeCompare(b[1].label));

    const tabTodosHtml = `
        <button type="button" class="pc-emp-tab ${tabActivoEmp === '__todos__' ? 'activo' : ''}"
                onclick="seleccionarTabEmp('__todos__')">
            <i class="fa-solid fa-grip tab-icono"></i>
            <span>Todos</span>
            <span class="pc-emp-tab-count">${cacheFilasEmpaquetado.length}</span>
        </button>`;

    const tabsProductoHtml = tabs.map(([clave, info]) => `
        <button type="button" class="pc-emp-tab ${tabActivoEmp === clave ? 'activo' : ''}"
                onclick="seleccionarTabEmp('${clave}')" title="${info.label.replace(/"/g, '&quot;')}">
            <i class="fa-solid fa-layer-group tab-icono"></i>
            <span>${info.label}</span>
            <span class="pc-emp-tab-count">${info.count}</span>
        </button>`).join('');

    cont.innerHTML = tabTodosHtml + tabsProductoHtml;
}

function seleccionarTabEmp(clave) {
    tabActivoEmp = clave;
    renderTabsEmp();
    actualizarEstacionArmado();
}

function infoOrigenExtra(origenTipo, origenId) {
    return cacheFilasEmpaquetado.find(f => f.origen_tipo === origenTipo && f.origen_id == origenId) || null;
}

// ── Estación de armado: se activa/actualiza según la pestaña de producto elegida ──
async function actualizarEstacionArmado() {
    const estCard = document.getElementById('estacionArmadoCard');
    const vacCard = document.getElementById('estacionVaciaCard');

    if (tabActivoEmp === '__todos__') {
        estCard.style.display = 'none';
        vacCard.style.display = 'block';
        estacionProductoIdActual = 0;
        return;
    }

    const fila = cacheFilasEmpaquetado.find(f => claveTabEmp(f) === tabActivoEmp);
    if (!fila) {
        estCard.style.display = 'none';
        vacCard.style.display = 'block';
        estacionProductoIdActual = 0;
        return;
    }

    const productoId = fila.producto_id;
    const label = `${fila.producto_codigo ?? ''} - ${fila.producto_descripcion ?? ''}`;

    vacCard.style.display = 'none';
    estCard.style.display = 'block';
    document.getElementById('estacionArmadoTitulo').textContent = `Armar paquete — ${label}`;
    document.getElementById('est_producto_id').value = productoId;

    // Ya está cargado este producto: no recargar (evita perder lo que el operario ya armó)
    if (estacionProductoIdActual === productoId) return;

    await cargarEstacionParaProducto(productoId);
}

async function cargarEstacionParaProducto(productoId) {
    estacionProductoIdActual = productoId;
    bultosState = [];
    mezclaOrigenes = [];
    bolsasProducidasValor = '';

    await Promise.all([
        cargarSelectsEstacion(),
        cargarOrigenesDisponibles(productoId),
    ]);
    aplicarUnidadEmpaquetadoFija();
    inicializarBloqueFormulario();
}

// =============================================================================
// LISTADO GENERAL
// =============================================================================

async function cargarListadoGeneralEmp() {
    const tbody = document.getElementById('tablaListadoGeneralEmp');
    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted"><i class="fa-solid fa-spinner fa-spin"></i> Cargando...</td></tr>';

    const params = {
        texto: document.getElementById('femp_texto').value.trim(),
        estado: document.getElementById('flist_estado').value,
        fecha_desde: document.getElementById('flist_fecha_desde').value,
        fecha_hasta: document.getElementById('flist_fecha_hasta').value,
    };
    const json = await llamarEmpaquetado('LISTARTODOSEMPAQUETADOS', params);
    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">${json.message}</td></tr>`;
        return;
    }

    const registros = json.empaquetados || [];
    if (registros.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No hay registros de empaquetado con estos filtros.</td></tr>';
        return;
    }

    tbody.innerHTML = registros.map(r => {
        const bultos = parseJsonColumnaEmp(r.js_cantidades);
        const bultosTexto = textoBultosDetalle(bultos);
        const vendido = !!r.pasado_venta;
        const productoLabel = `${(r.producto_codigo ?? '')} - ${(r.producto_descripcion ?? '')}`.replace(/'/g, "\\'");
        const origenTexto = r.origen_tipo === 'mixto' ? 'Mixto'
            : r.origen_tipo === 'produccion' ? 'Producción directa'
            : r.origen_tipo === 'ensamblaje' ? 'Ensamblaje' : '-';
        return `
        <tr>
            <td>${origenTexto}</td>
            <td>${r.producto_codigo ?? ''} - ${r.producto_descripcion ?? '-'}</td>
            <td>${r.sucursal_nombre ?? '-'}</td>
            <td class="bultos-detalle">${bultosTexto}</td>
            <td><b>${formatearCantidadEmp(r.cantidad_tota)}</b> ${r.unidad_corto ?? ''}${textoEquivalenteEmp(r)}</td>
            <td>${textoOperariosEmp(r)}</td>
            <td>${formatearFechaHoraLegibleEmp(r.created_at)}</td>
            <td>${vendido
                ? `<span class="badge-vendido">Vendido ${formatearFechaHoraLegibleEmp(r.pasado_venta)}</span>`
                : '<span class="badge bg-success">Disponible</span>'}</td>
            <td>
                <button type="button" class="pc-icon-btn" title="Ver registros"
                        onclick="abrirModalEmpaquetado(${r.producto_id}, '${productoLabel}')">
                    <i class="fa-solid fa-box-open"></i>
                </button>
            </td>
        </tr>`;
    }).join('');
}

// =============================================================================
// SELECTS AUXILIARES
// =============================================================================

async function obtenerUnidadesEmp() {
    if (empUnidadesCache) return empUnidadesCache;
    const json = await llamarEmpaquetado('BUSCARUNIDADESMEDIDA', { texto: '' });
    if (!json.success) console.error('Error BUSCARUNIDADESMEDIDA:', json.message);
    empUnidadesCache = json.success ? json.unidades : [];
    return empUnidadesCache;
}
async function obtenerOperariosEmp() {
    if (empOperariosCache) return empOperariosCache;
    const json = await llamarEmpaquetado('BUSCAROPERARIOS');
    if (!json.success) console.error('Error BUSCAROPERARIOS:', json.message);
    empOperariosCache = json.success ? json.operario : [];
    return empOperariosCache;
}

// Selects/chips de la ESTACIÓN DE ARMADO (creación de registros nuevos)
async function cargarSelectsEstacion() {
    const [unidades, operarios, sucursales] = await Promise.all([
        obtenerUnidadesEmp(), obtenerOperariosEmp(), obtenerSucursalesEmp()
    ]);
    const sUnidad = document.getElementById('est_unidad_medida');
    sUnidad.innerHTML = unidades.length
        ? '<option value="">Selecciona...</option>' + unidades.map(u => `<option value="${u.id}">${u.nombre} (${u.nombre_corto})</option>`).join('')
        : '<option value="">(sin unidades disponibles - revisar consola)</option>';

    const sSuc = document.getElementById('est_sucursal_id');
    sSuc.innerHTML = '<option value="">Selecciona...</option>' + (sucursales || []).map(s => `<option value="${s.id}">${s.nombre}</option>`).join('');

    estOperariosSeleccionados = [];
    renderOperariosChips('est_operarios_chips', estOperariosSeleccionados, 'toggleOperarioEstacion');
}

// Selects/chips del MODAL de edición (registros ya existentes)
async function cargarSelectsModalEdicion() {
    const [unidades, operarios, sucursales] = await Promise.all([
        obtenerUnidadesEmp(), obtenerOperariosEmp(), obtenerSucursalesEmp()
    ]);
    const sUnidad = document.getElementById('emp_unidad_medida');
    sUnidad.innerHTML = unidades.length
        ? '<option value="">Selecciona...</option>' + unidades.map(u => `<option value="${u.id}">${u.nombre} (${u.nombre_corto})</option>`).join('')
        : '<option value="">(sin unidades disponibles - revisar consola)</option>';

    const sSuc = document.getElementById('emp_sucursal_id');
    sSuc.innerHTML = '<option value="">Selecciona...</option>' + (sucursales || []).map(s => `<option value="${s.id}">${s.nombre}</option>`).join('');

    empOperariosSeleccionados = [];
    renderOperariosChips('emp_operarios_chips', empOperariosSeleccionados, 'toggleOperarioModalEdicion');
}

async function cargarOrigenesDisponibles(productoId) {
    const json = await llamarEmpaquetado('BUSCARORIGENESDISPONIBLES', { producto_id: productoId });
    if (!json.success) console.error('Error BUSCARORIGENESDISPONIBLES:', json.message);
    origenesDisponiblesCache = json.success ? (json.origenes || []) : [];
    unidadEmpaquetadoProductoActual = json.success ? (json.unidad_empaquetado || null) : null;
    reglasEmpaquetadoActuales = json.success ? (json.reglas_empaquetado || null) : null;
}

function aplicarUnidadEmpaquetadoFija() {
    const sUnidad = document.getElementById('est_unidad_medida');
    const aviso = document.getElementById('avisoUnidadEstacion');

    if (unidadEmpaquetadoProductoActual && unidadEmpaquetadoProductoActual.id) {
        sUnidad.innerHTML = `<option value="${unidadEmpaquetadoProductoActual.id}" selected>
            ${unidadEmpaquetadoProductoActual.nombre} (${unidadEmpaquetadoProductoActual.nombre_corto})
        </option>`;
        sUnidad.disabled = true;
        aviso.style.display = 'none';
    } else {
        // Producto sin "Salida en Empaquetado" configurada: fallback
        // editable (ya trae la lista completa cargada por cargarSelectsEstacion)
        sUnidad.disabled = false;
        aviso.style.display = 'block';
    }
}
// =============================================================================
// MODAL: SOLO listado de registros + edición + eliminación
// =============================================================================

async function abrirModalEmpaquetado(productoId, productoLabel) {
    modalProductoIdActual = productoId;
    document.getElementById('modalEmpaquetadoTitulo').textContent = `Registros — ${productoLabel}`;
    cancelarEdicionEmp();
    await cargarSelectsModalEdicion();
    await cargarRegistrosEmp();
    modalEmpaquetado.show();
}

// Atajo desde la estación de armado: abre el modal para el producto activo.
function abrirModalRegistrosProducto() {
    if (!estacionProductoIdActual) return;
    const fila = cacheFilasEmpaquetado.find(f => f.producto_id === estacionProductoIdActual);
    const label = fila ? `${fila.producto_codigo ?? ''} - ${fila.producto_descripcion ?? ''}` : 'Producto';
    abrirModalEmpaquetado(estacionProductoIdActual, label);
}

async function cargarRegistrosEmp() {
    const tbody = document.getElementById('tablaRegistrosEmp');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted"><i class="fa-solid fa-spinner fa-spin"></i> Cargando...</td></tr>';

    const json = await llamarEmpaquetado('LISTAREMPAQUETADOSPORPRODUCTO', { producto_id: modalProductoIdActual });
    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger">${json.message}</td></tr>`;
        return;
    }

    const registros = json.empaquetados || [];
    if (registros.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Aún no hay registros de empaquetado para este producto.</td></tr>';
        return;
    }

    tbody.innerHTML = registros.map(r => {
        const bultos = parseJsonColumnaEmp(r.js_cantidades);
        const bultosTexto = textoBultosDetalle(bultos);
        const vendido = !!r.pasado_venta;
        return `
        <tr>
            <td class="bultos-detalle">${bultosTexto}</td>
            <td><b>${formatearCantidadEmp(r.cantidad_tota)}</b> ${r.unidad_corto ?? ''}${textoEquivalenteEmp(r)}</td>
            <td>${textoOperariosEmp(r)}</td>
            <td>${formatearFechaHoraLegibleEmp(r.created_at)}</td>
            <td>${vendido
                ? `<span class="badge-vendido">Vendido ${formatearFechaHoraLegibleEmp(r.pasado_venta)}</span>`
                : '<span class="badge bg-success">Disponible</span>'}</td>
            <td>
                <div class="acciones">
                    ${!vendido ? `
                        <button type="button" class="pc-icon-btn" title="Editar" onclick="editarRegistroEmp(${r.id})"><i class="fa-solid fa-pen"></i></button>
                        <button type="button" class="pc-icon-btn" title="Eliminar" onclick="eliminarRegistroEmp(${r.id})"><i class="fa-solid fa-trash"></i></button>
                    ` : ''}
                </div>
            </td>
        </tr>`;
    }).join('');
}

// ── Bultos con mezcla de colores ────────────────────────────────────────────

function claveOrigen(tipo, id) { return `${tipo}:${id}`; }

// Cuánto de un origen ya está comprometido en los bultos armados (client-side)
function cantidadComprometidaOrigen(tipo, id) {
    let total = 0;
    bultosState.forEach(b => b.colores.forEach(c => {
        if (c.origen_tipo === tipo && c.origen_id === id) total += (parseFloat(c.cantidad) || 0);
    }));
    return total;
}

function disponibleRestanteOrigen(tipo, id, excluirTempColorId = null) {
    const o = origenesDisponiblesCache.find(x => x.origen_tipo === tipo && x.origen_id == id);
    if (!o) return 0;
    let comprometido = 0;
    bultosState.forEach(b => b.colores.forEach(c => {
        if (c.origen_tipo === tipo && c.origen_id === id && c.tempColorId !== excluirTempColorId) {
            comprometido += (parseFloat(c.cantidad) || 0);
        }
    }));
    return (parseFloat(o.disponible) || 0) - comprometido;
}

function totalBulto(bulto) {
    return bulto.colores.reduce((s, c) => s + (parseFloat(c.cantidad) || 0), 0);
}

function agregarBulto() {
    bultosState.push({ tempId: ++contadorBulto, colores: [] });
    agregarColorABulto(bultosState[bultosState.length - 1].tempId);
}

function esModoMezcla() {
    return !!(reglasEmpaquetadoActuales && reglasEmpaquetadoActuales.conversion_peso_a_unidad);
}

function agregarOrigenMezcla() {
    mezclaOrigenes.push({ tempId: ++contadorMezclaOrigen, origen_tipo: '', origen_id: 0, color_id: null, color_nombre: '', cantidad_kg: '' });
    renderMezcla();
}

function quitarOrigenMezcla(tempId) {
    mezclaOrigenes = mezclaOrigenes.filter(o => o.tempId !== tempId);
    renderMezcla();
}

function disponibleKgOrigen(tipo, id, excluirTempId = null) {
    const o = origenesDisponiblesCache.find(x => x.origen_tipo === tipo && x.origen_id == id);
    if (!o) return 0;
    const dispKgBase = o.disponible_kg !== undefined ? parseFloat(o.disponible_kg) : parseFloat(o.disponible);
    let comprometido = 0;
    mezclaOrigenes.forEach(m => {
        if (m.origen_tipo === tipo && m.origen_id === id && m.tempId !== excluirTempId) {
            comprometido += (parseFloat(m.cantidad_kg) || 0);
        }
    });
    return dispKgBase - comprometido;
}

// Toca un saco en la grilla visual (modo MEZCLA): si ya está en la mezcla,
// le suma todo lo que le queda disponible; si no está, lo agrega con su
// disponible completo. El operario después ajusta el número exacto.
function tocarSacoMezcla(origenTipo, origenId) {
    const o = origenesDisponiblesCache.find(x => x.origen_tipo === origenTipo && x.origen_id == origenId);
    if (!o) return;
    const restante = disponibleKgOrigen(origenTipo, origenId);
    if (restante <= 0.0001) return;

    let fila = mezclaOrigenes.find(m => m.origen_tipo === origenTipo && m.origen_id == origenId);
    if (!fila) {
        fila = { tempId: ++contadorMezclaOrigen, origen_tipo: origenTipo, origen_id: parseInt(origenId, 10), color_id: o.color_id, color_nombre: o.color_nombre, cantidad_kg: '' };
        mezclaOrigenes.push(fila);
    }
    const actual = parseFloat(fila.cantidad_kg) || 0;
    fila.cantidad_kg = Math.round((actual + restante) * 10000) / 10000;
    renderMezcla();
}

// Toca un saco en la grilla visual (modo BULTO/docena): lo agrega o le suma
// una unidad de "granularidad" (ej. 1 docena) al paquete actual, respetando
// la capacidad del paquete y el disponible del saco. Si el paquete actual
// ya está lleno, abre uno nuevo automáticamente.
function tocarSacoBulto(origenTipo, origenId) {
    if (bultosState.length === 0) agregarBulto();
    let bulto = bultosState[bultosState.length - 1];

    const capacidad = unidadEmpaquetadoProductoActual?.equivalencia
        ? parseFloat(unidadEmpaquetadoProductoActual.equivalencia) : null;
    const granularidad = reglasEmpaquetadoActuales?.granularidad_color || 1;
    const incremento = granularidad > 1 ? granularidad : 1;

    if (capacidad !== null && totalBulto(bulto) >= capacidad - 0.0001) {
        agregarBulto();
        bulto = bultosState[bultosState.length - 1];
    }

    let fila = bulto.colores.find(c => c.origen_tipo === origenTipo && c.origen_id == origenId);
    const restanteOrigen = disponibleRestanteOrigen(origenTipo, origenId, fila ? fila.tempColorId : null);
    if (restanteOrigen <= 0.0001) return;

    const espacioBulto = capacidad !== null
        ? Math.max(0, capacidad - totalBulto(bulto) + (fila ? (parseFloat(fila.cantidad) || 0) : 0))
        : Infinity;
    const aAgregar = Math.min(incremento, restanteOrigen, espacioBulto);
    if (aAgregar <= 0) return;

    if (!fila) {
        const o = origenesDisponiblesCache.find(x => x.origen_tipo === origenTipo && x.origen_id == origenId);
        // Reutiliza la fila vacía que deja agregarBulto()/agregarColorABulto()
        const filaVacia = bulto.colores.find(c => !c.origen_tipo);
        if (filaVacia) {
            filaVacia.origen_tipo = origenTipo;
            filaVacia.origen_id = parseInt(origenId, 10);
            filaVacia.color_id = o ? o.color_id : null;
            filaVacia.color_nombre = o ? o.color_nombre : '';
            filaVacia.cantidad = aAgregar;
        } else {
            bulto.colores.push({
                tempColorId: ++contadorColorRow, origen_tipo: origenTipo, origen_id: parseInt(origenId, 10),
                color_id: o ? o.color_id : null, color_nombre: o ? o.color_nombre : '', cantidad: aAgregar,
            });
        }
    } else {
        fila.cantidad = (parseFloat(fila.cantidad) || 0) + aAgregar;
    }

    renderBultos();
}

function actualizarOrigenMezcla(tempId, valorSelect) {
    const fila = mezclaOrigenes.find(m => m.tempId === tempId);
    if (!fila) return;
    if (!valorSelect) {
        fila.origen_tipo = ''; fila.origen_id = 0; fila.color_id = null; fila.color_nombre = '';
    } else {
        const [tipo, id] = valorSelect.split(':');
        const o = origenesDisponiblesCache.find(x => x.origen_tipo === tipo && x.origen_id == id);
        fila.origen_tipo = tipo; fila.origen_id = parseInt(id, 10);
        fila.color_id = o ? o.color_id : null;
        fila.color_nombre = o ? o.color_nombre : '';
    }
    renderMezcla();
}

function actualizarCantidadKgMezcla(tempId, valor) {
    const fila = mezclaOrigenes.find(m => m.tempId === tempId);
    if (fila) fila.cantidad_kg = valor;
    actualizarResumenMezcla();
}

function actualizarBolsasProducidas(valor) {
    bolsasProducidasValor = valor;
    actualizarResumenMezcla();
}

function actualizarResumenMezcla() {
    const kgTotal = mezclaOrigenes.reduce((s, m) => s + (parseFloat(m.cantidad_kg) || 0), 0);
    document.getElementById('mezclaKgTotal').textContent = formatearCantidadEmp(kgTotal);

    const pesoUnitG = reglasEmpaquetadoActuales?.peso_unitario_g;
    const elTeorico = document.getElementById('mezclaBolsasTeoricas');
    const elDiff = document.getElementById('mezclaDiferenciaBadge');
    const elPaquetes = document.getElementById('mezclaPaquetesEstimados');

    elPaquetes.textContent = formatearCantidadEmp((parseFloat(bolsasProducidasValor) || 0) / 24);

    const bolsas = parseFloat(bolsasProducidasValor) || 0;
    const frac = Math.min(bolsas, 24);
    document.getElementById('mezclaPaqueteFrac').textContent = `${Math.round(frac)} / 24`;
    document.getElementById('mezclaPaqueteBarra').style.width = ((frac / 24) * 100) + '%';

    if (pesoUnitG && kgTotal > 0) {
        const bolsasTeoricas = (kgTotal * 1000) / pesoUnitG / 144;
        elTeorico.textContent = formatearCantidadEmp(bolsasTeoricas);
        const declarado = parseFloat(bolsasProducidasValor) || 0;
        if (declarado > 0 && bolsasTeoricas > 0) {
            const diffPct = Math.abs(declarado - bolsasTeoricas) / bolsasTeoricas * 100;
            elDiff.style.display = 'inline-block';
            if (diffPct > 20) {
                elDiff.className = 'badge bg-danger';
                elDiff.textContent = `⚠ Difiere ${diffPct.toFixed(0)}% de lo esperado`;
            } else if (diffPct > 8) {
                elDiff.className = 'badge bg-warning text-dark';
                elDiff.textContent = `Difiere ${diffPct.toFixed(0)}% de lo esperado`;
            } else {
                elDiff.className = 'badge bg-success';
                elDiff.textContent = `Dentro de lo esperado`;
            }
        } else {
            elDiff.style.display = 'none';
        }
    } else {
        elTeorico.textContent = '-';
        elDiff.style.display = 'none';
    }
}

// Grilla de sacos disponibles (tarjetas de color) para la mezcla al azar.
function renderSacosMezclaGrid() {
    const cont = document.getElementById('sacosMezclaGrid');
    if (!cont) return;

    if (origenesDisponiblesCache.length === 0) {
        cont.innerHTML = '<div class="text-muted" style="font-size:.85em;">No hay sacos disponibles para este producto.</div>';
        return;
    }

    cont.innerHTML = origenesDisponiblesCache.map(o => {
        const restante = disponibleKgOrigen(o.origen_tipo, o.origen_id);
        const enMezcla = mezclaOrigenes.find(m => m.origen_tipo === o.origen_tipo && m.origen_id == o.origen_id);
        const enMezclaKg = enMezcla ? (parseFloat(enMezcla.cantidad_kg) || 0) : 0;
        const agotado = restante <= 0.0001;
        const origenLabel = o.origen_tipo === 'ensamblaje' ? `Ensamblaje #${o.origen_id}` : `Producción #${o.origen_id}`;
        const unidadOrigenLabel = o.unidad_salida_codigo ? o.unidad_salida_codigo.toUpperCase() : '';
        const hex = colorHexPara(o.color_nombre, o.color_hex);
        const infoExtra = infoOrigenExtra(o.origen_tipo, o.origen_id);
        const metaHtml = infoExtra
            ? `<p class="meta">empaq: ${formatearCantidadEmp(infoExtra.cantidad_total_empaquetada)} · ${infoExtra.empaquetados_count ?? 0} reg.</p>
               <p class="meta">fin: ${formatearFechaHoraLegibleEmp(infoExtra.fecha_fin)}</p>`
            : '';
        return `
        <button type="button" class="pc-saco-card ${agotado ? 'agotado' : ''}"
                onclick="tocarSacoMezcla('${o.origen_tipo}', ${o.origen_id})" title="Tocar para agregar a la mezcla">
            ${enMezclaKg > 0 ? `<span class="en-mezcla">${formatearCantidadEmp(enMezclaKg)} kg</span>` : ''}
            <div class="swatch" style="background:${hex};"></div>
            <p class="nombre">${o.color_nombre ?? 'Sin color'}</p>
            <p class="origen">${origenLabel}${unidadOrigenLabel ? ` · <b>${unidadOrigenLabel}</b>` : ''}</p>
            <p class="disp">disp: ${formatearCantidadEmp(restante)} kg</p>
            ${metaHtml}
        </button>`;
    }).join('');
}
// Grilla de sacos disponibles (modo BULTO/docena): tocar agrega al paquete actual.
function renderSacosBultoGrid() {
    const cont = document.getElementById('sacosBultoGrid');
    if (!cont) return;

    if (origenesDisponiblesCache.length === 0) {
        cont.innerHTML = '<div class="text-muted" style="font-size:.85em;">No hay sacos disponibles para este producto.</div>';
        return;
    }

    cont.innerHTML = origenesDisponiblesCache.map(o => {
        const restante = disponibleRestanteOrigen(o.origen_tipo, o.origen_id);
        const comprometido = cantidadComprometidaOrigen(o.origen_tipo, o.origen_id);
        const agotado = restante <= 0.0001;
        const origenLabel = o.origen_tipo === 'ensamblaje' ? `Ensamblaje #${o.origen_id}` : `Producción #${o.origen_id}`;
        const unidadOrigenLabel = o.unidad_salida_codigo ? o.unidad_salida_codigo.toUpperCase() : '';
        const hex = colorHexPara(o.color_nombre, o.color_hex);
        const infoExtra = infoOrigenExtra(o.origen_tipo, o.origen_id);
        const metaHtml = infoExtra
            ? `<p class="meta">empaq: ${formatearCantidadEmp(infoExtra.cantidad_total_empaquetada)} · ${infoExtra.empaquetados_count ?? 0} reg.</p>
               <p class="meta">fin: ${formatearFechaHoraLegibleEmp(infoExtra.fecha_fin)}</p>`
            : '';
        return `
        <button type="button" class="pc-saco-card ${agotado ? 'agotado' : ''}"
                onclick="tocarSacoBulto('${o.origen_tipo}', ${o.origen_id})" title="Tocar para agregar al paquete actual">
            ${comprometido > 0 ? `<span class="en-mezcla">${formatearCantidadEmp(comprometido)}</span>` : ''}
            <div class="swatch" style="background:${hex};"></div>
            <p class="nombre">${o.color_nombre ?? 'Sin color'}</p>
            <p class="origen">${origenLabel}${unidadOrigenLabel ? ` · <b>${unidadOrigenLabel}</b>` : ''}</p>
            <p class="disp">disp: ${formatearCantidadEmp(restante)}${unidadOrigenLabel ? ' ' + unidadOrigenLabel : ''}</p>
            ${metaHtml}
        </button>`;
    }).join('');
}
function renderMezcla() {
    renderSacosMezclaGrid();

    const cont = document.getElementById('listaMezclaOrigenes');
    if (!cont) return;

    const filasConValor = mezclaOrigenes.filter(m => m.origen_tipo || parseFloat(m.cantidad_kg) > 0);

    if (filasConValor.length === 0) {
        cont.innerHTML = '<div class="text-muted" style="font-size:.85em;">Toca un saco a la izquierda para empezar a mezclar.</div>';
        actualizarResumenMezcla();
        return;
    }

    cont.innerHTML = filasConValor.map(m => {
        const hex = m.origen_tipo ? colorHexPara(m.color_nombre, (origenesDisponiblesCache.find(x => x.origen_tipo === m.origen_tipo && x.origen_id == m.origen_id) || {}).color_hex) : '#d3d1c7';
        const maxKg = m.origen_tipo ? disponibleKgOrigen(m.origen_tipo, m.origen_id, m.tempId) + (parseFloat(m.cantidad_kg) || 0) : null;
        const origenLabel = m.origen_tipo ? (m.origen_tipo === 'ensamblaje' ? `Ensamblaje #${m.origen_id}` : `Producción #${m.origen_id}`) : 'Sin asignar';
        return `
        <div class="pc-mezcla-fila">
            <span class="swatch-mini" style="background:${hex};"></span>
            <span class="nombre" title="${m.color_nombre ?? 'Sin color'} · ${origenLabel}">${m.color_nombre ?? 'Sin color'} · ${origenLabel}</span>
            <input type="number" min="0.0001" step="0.0001" ${maxKg !== null ? `max="${maxKg}"` : ''} class="form-control form-control-sm"
                   placeholder="Kg" value="${m.cantidad_kg}"
                   oninput="actualizarCantidadKgMezcla(${m.tempId}, this.value)">
            <button type="button" class="pc-bulto-remove" onclick="quitarOrigenMezcla(${m.tempId})" title="Quitar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>`;
    }).join('');
    actualizarResumenMezcla();
}

function inicializarBloqueFormulario() {
    const modoMezcla = esModoMezcla();
    document.getElementById('bloqueBultos').style.display = modoMezcla ? 'none' : 'block';
    document.getElementById('bloqueMezcla').style.display = modoMezcla ? 'block' : 'none';

    if (modoMezcla) {
        renderMezcla();
    } else {
        if (bultosState.length === 0) agregarBulto(); else renderBultos();
    }
}

function quitarBulto(tempId) {
    bultosState = bultosState.filter(b => b.tempId !== tempId);
    renderBultos();
}
function distribuirParejoBulto(bultoTempId) {
    const bulto = bultosState.find(b => b.tempId === bultoTempId);
    if (!bulto) return;

    const colores = bulto.colores.filter(c => c.origen_tipo && c.origen_id);
    if (colores.length < 2) return;

    const capacidad = unidadEmpaquetadoProductoActual?.equivalencia
        ? parseFloat(unidadEmpaquetadoProductoActual.equivalencia) : null;
    const granularidad = reglasEmpaquetadoActuales?.granularidad_color || 1;
    if (!capacidad) return;

    // Reparto base: capacidad / nColores, redondeado hacia abajo al
    // múltiplo de granularidad más cercano.
    const base = Math.floor((capacidad / colores.length) / granularidad) * granularidad;

    colores.forEach(c => {
        const restante = disponibleRestanteOrigen(c.origen_tipo, c.origen_id, c.tempColorId) + (parseFloat(c.cantidad) || 0);
        c.cantidad = Math.min(base, restante); // nunca más de lo disponible real de ese origen
    });

    renderBultos();
}
function agregarColorABulto(bultoTempId) {
    const bulto = bultosState.find(b => b.tempId === bultoTempId);
    if (!bulto) return;
    bulto.colores.push({ tempColorId: ++contadorColorRow, origen_tipo: '', origen_id: 0, color_id: null, color_nombre: '', cantidad: '' });
    renderBultos();
}

function quitarColorDeBulto(bultoTempId, tempColorId) {
    const bulto = bultosState.find(b => b.tempId === bultoTempId);
    if (!bulto) return;
    bulto.colores = bulto.colores.filter(c => c.tempColorId !== tempColorId);
    renderBultos();
}

function actualizarOrigenColor(bultoTempId, tempColorId, valorSelect) {
    const bulto = bultosState.find(b => b.tempId === bultoTempId);
    if (!bulto) return;
    const fila = bulto.colores.find(c => c.tempColorId === tempColorId);
    if (!fila) return;
    if (!valorSelect) {
        fila.origen_tipo = ''; fila.origen_id = 0; fila.color_id = null; fila.color_nombre = '';
    } else {
        const [tipo, id] = valorSelect.split(':');
        const o = origenesDisponiblesCache.find(x => x.origen_tipo === tipo && x.origen_id == id);
        fila.origen_tipo = tipo; fila.origen_id = parseInt(id, 10);
        fila.color_id = o ? o.color_id : null;
        fila.color_nombre = o ? o.color_nombre : '';
    }
    renderBultos();
}

// Atajo visual: tocar la pastilla de color de un origen concreto hace lo
// mismo que elegirlo en el <select> de esa fila, sin tener que abrir el
// desplegable. El <select> se mantiene debajo por accesibilidad y para
// ver el disponible exacto de cada origen en texto.
function elegirColorEnFilaBulto(bultoTempId, tempColorId, origenTipo, origenId) {
    actualizarOrigenColor(bultoTempId, tempColorId, claveOrigen(origenTipo, origenId));
}

function actualizarCantidadColor(bultoTempId, tempColorId, valor) {
    const bulto = bultosState.find(b => b.tempId === bultoTempId);
    if (!bulto) return;
    const fila = bulto.colores.find(c => c.tempColorId === tempColorId);
    if (fila) fila.cantidad = valor;
    actualizarResumenBultos(); // solo el total; no re-renderiza para no perder el foco del input
}

function renderBultos() {
    renderSacosBultoGrid();

    const cont = document.getElementById('listaBultos');
    const capacidad = unidadEmpaquetadoProductoActual?.equivalencia
        ? parseFloat(unidadEmpaquetadoProductoActual.equivalencia)
        : null;
    const unidadPaquete = unidadEmpaquetadoProductoActual?.nombre_corto || ''; // <-- NUEVO: se declara aquí, junto a capacidad
    const granularidad = reglasEmpaquetadoActuales?.granularidad_color || 1;

    if (bultosState.length === 0) {
        cont.innerHTML = '<div class="text-muted" style="font-size:.85em;">Agrega al menos un bulto.</div>';
        actualizarResumenBultos();
        return;
    }

    cont.innerHTML = bultosState.map((b, idx) => {
        const total = totalBulto(b);
        const excedido = capacidad !== null && total > capacidad + 0.0001;
        const textoTotal = capacidad !== null
            ? `total: ${formatearCantidadEmp(total)} / ${formatearCantidadEmp(capacidad)} ${unidadPaquete}`
            : `total: ${formatearCantidadEmp(total)} ${unidadPaquete}`;

        const filasColores = b.colores.map(c => {
            const valorActual = c.origen_tipo ? claveOrigen(c.origen_tipo, c.origen_id) : '';

            // Pastillas de color: acceso rápido a cada origen disponible.
            const swatchesHtml = origenesDisponiblesCache.map(o => {
                const clave = claveOrigen(o.origen_tipo, o.origen_id);
                const restante = disponibleRestanteOrigen(o.origen_tipo, o.origen_id, c.tempColorId);
                const deshabilitado = restante <= 0.0001 && clave !== valorActual;
                const hex = colorHexPara(o.color_nombre, o.color_hex);
                const origenLabel = o.origen_tipo === 'ensamblaje' ? `Ensamblaje #${o.origen_id}` : `Producción #${o.origen_id}`;
                return `<button type="button" class="pc-swatch-chip ${clave === valorActual ? 'activo' : ''} ${deshabilitado ? 'agotado' : ''}"
                    style="background:${hex};" title="${o.color_nombre ?? 'Sin color'} · ${origenLabel} (disp: ${formatearCantidadEmp(restante)} ${o.unidad_salida_codigo ?? ''})"
                    onclick="elegirColorEnFilaBulto(${b.tempId}, ${c.tempColorId}, '${o.origen_tipo}', ${o.origen_id})"></button>`;
            }).join('');

            const opciones = origenesDisponiblesCache.map(o => {
                const clave = claveOrigen(o.origen_tipo, o.origen_id);
                const restante = disponibleRestanteOrigen(o.origen_tipo, o.origen_id, c.tempColorId);
                const deshabilitado = restante <= 0.0001 && clave !== valorActual;
                const origenLabel = o.origen_tipo === 'ensamblaje' ? `Ensamblaje #${o.origen_id}` : `Producción #${o.origen_id}`;
                return `<option value="${clave}" ${clave === valorActual ? 'selected' : ''} ${deshabilitado ? 'disabled' : ''}>
                    ${o.color_nombre ?? 'Sin color'} · ${origenLabel} (disp: ${formatearCantidadEmp(restante)})
                </option>`;
            }).join('');

            // Tope del input: lo más restrictivo entre lo disponible del
            // origen elegido y lo que le queda de espacio al bulto según
            // la capacidad de la unidad de empaquetado.
            const restanteOrigenActual = c.origen_tipo ? disponibleRestanteOrigen(c.origen_tipo, c.origen_id, c.tempColorId) : null;
            const espacioRestanteBulto = capacidad !== null ? Math.max(0, capacidad - (total - (parseFloat(c.cantidad) || 0))) : null;
            const topes = [restanteOrigenActual, espacioRestanteBulto].filter(v => v !== null && v !== undefined);
            const maxInput = topes.length > 0 ? Math.min(...topes) : null;

            return `
            <div class="mb-2">
                <div class="pc-swatch-picker">${swatchesHtml}</div>
                <div class="pc-color-row">
                    <select class="form-select form-select-sm" onchange="actualizarOrigenColor(${b.tempId}, ${c.tempColorId}, this.value)">
                        <option value="">Selecciona color/origen...</option>
                        ${opciones}
                    </select>
                    <input type="number" min="0.0001" step="0.0001" ${maxInput !== null ? `max="${maxInput}"` : ''} class="form-control form-control-sm"
                           placeholder="Cantidad" value="${c.cantidad}"
                           oninput="actualizarCantidadColor(${b.tempId}, ${c.tempColorId}, this.value)">
                    <button type="button" class="pc-bulto-remove" onclick="quitarColorDeBulto(${b.tempId}, ${c.tempColorId})" title="Quitar color">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>`;
        }).join('');

        return `
        <div class="pc-bulto-card" style="${excedido ? 'border-color:#c94a4a;' : ''}">
            <div class="pc-bulto-card-head" style="${excedido ? 'color:#c94a4a;' : ''}">
                <span>Bulto ${idx + 1} — ${textoTotal}${excedido ? ' ⚠ excede la capacidad' : ''}</span>
                <div>
                    ${reglasEmpaquetadoActuales?.modo_distribucion_color === 'uniforme' && b.colores.length > 1 ? `
                        <button type="button" class="btn btn-sm btn-outline-primary" style="margin-right:6px;" onclick="distribuirParejoBulto(${b.tempId})">
                            <i class="fa-solid fa-scale-balanced"></i> Repartir parejo
                        </button>` : ''}
                    <button type="button" class="pc-bulto-remove" onclick="quitarBulto(${b.tempId})" title="Quitar bulto">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>
            ${filasColores}
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="agregarColorABulto(${b.tempId})">
                <i class="fa-solid fa-plus"></i> Agregar color a este bulto
            </button>
        </div>`;
    }).join('');
    actualizarResumenBultos();
}

function actualizarResumenBultos() {
    const total = bultosState.reduce((s, b) => s + totalBulto(b), 0);
    document.getElementById('bultosCount').textContent = bultosState.length;
    const unidadPaquete = unidadEmpaquetadoProductoActual?.nombre_corto || '';
    document.getElementById('bultosTotal').textContent = `${formatearCantidadEmp(total)}${unidadPaquete ? ' ' + unidadPaquete : ''}`;
}

function obtenerBultosJsonEmp() {
    const bultos = bultosState.map(b => ({
        colores: b.colores
            .filter(c => c.origen_tipo && c.origen_id && parseFloat(c.cantidad) > 0)
            .map(c => ({
                origen_tipo: c.origen_tipo, origen_id: c.origen_id,
                color_id: c.color_id, color_nombre: c.color_nombre,
                cantidad: parseFloat(c.cantidad),
            })),
    })).filter(b => b.colores.length > 0);
    return JSON.stringify(bultos);
}

const CONTROLADOR_SUCURSAL = 'controllers/clssSucursal.php';

async function llamarSucursal(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_SUCURSAL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body
    });
    return resp.json();
}

let empSucursalesCache = null;
async function obtenerSucursalesEmp() {
    if (empSucursalesCache) return empSucursalesCache;
    const json = await llamarSucursal('LISTARSUCURSALES', { visibilidad: 'activas' });
    empSucursalesCache = json.success ? json.sucursales : [];
    return empSucursalesCache;
}

// =============================================================================
// FORMULARIO: ESTACIÓN DE ARMADO (crear registro nuevo)
// =============================================================================

document.getElementById('formEstacionArmado').addEventListener('submit', async function (e) {
    e.preventDefault();

    if (estOperariosSeleccionados.length === 0) {
        Swal.fire('Falta información', 'Selecciona al menos un operario.', 'warning');
        return;
    }

    let params;

    if (esModoMezcla()) {
        const origenesValidos = mezclaOrigenes.filter(m => m.origen_tipo && m.origen_id && parseFloat(m.cantidad_kg) > 0);
        if (origenesValidos.length === 0) {
            Swal.fire('Falta información', 'Toca al menos un saco/color y verifica que tenga kg mayor a 0.', 'warning');
            return;
        }
        const bolsas = parseInt(bolsasProducidasValor, 10);
        if (!bolsas || bolsas <= 0) {
            Swal.fire('Falta información', 'Indica cuántas bolsas se produjeron.', 'warning');
            return;
        }

        params = {
            producto_id: estacionProductoIdActual,
            operarios: JSON.stringify(estOperariosSeleccionados),
            sucursal_id: document.getElementById('est_sucursal_id').value,
            mezcla_origenes: JSON.stringify(origenesValidos.map(m => ({
                origen_tipo: m.origen_tipo, origen_id: m.origen_id,
                color_id: m.color_id, color_nombre: m.color_nombre,
                cantidad_kg: parseFloat(m.cantidad_kg),
            }))),
            bolsas_producidas: bolsas,
        };
    } else {
        const bultosJson = obtenerBultosJsonEmp();
        const bultosParsed = JSON.parse(bultosJson);
        if (bultosParsed.length === 0) {
            Swal.fire('Falta información', 'Agrega al menos un bulto con un color/origen y cantidad mayor a 0.', 'warning');
            return;
        }

        const capacidad = unidadEmpaquetadoProductoActual?.equivalencia
            ? parseFloat(unidadEmpaquetadoProductoActual.equivalencia)
            : null;
        if (capacidad !== null) {
            const totalesExcedidos = bultosParsed
                .map((b, i) => ({ idx: i + 1, total: b.colores.reduce((s, c) => s + c.cantidad, 0) }))
                .filter(b => b.total > capacidad + 0.0001);
            if (totalesExcedidos.length > 0) {
                const detalle = totalesExcedidos.map(b => `Bulto ${b.idx}: ${formatearCantidadEmp(b.total)}`).join(', ');
                Swal.fire('Bulto excede la capacidad',
                    `${detalle} — la capacidad de ${unidadEmpaquetadoProductoActual.nombre} es ${formatearCantidadEmp(capacidad)}. Ajusta las cantidades.`,
                    'warning');
                return;
            }
        }

        const granularidad = reglasEmpaquetadoActuales?.granularidad_color || 1;
        if (granularidad > 1) {
            for (const b of bultosParsed) {
                for (const c of b.colores) {
                    if (c.cantidad % granularidad !== 0) {
                        Swal.fire('Cantidad inválida', `Cada color debe ser múltiplo de ${granularidad} (docena) — revisa las cantidades ingresadas.`, 'warning');
                        return;
                    }
                }
            }
        }

        // ── Validación de reparto UNIFORME entre colores ────────────────────────
        // Antes: si el total del bulto no era exactamente divisible entre el
        // número de colores, este bloque se saltaba entero y dejaba pasar
        // repartos desparejos sin ningún aviso. Ahora se valida explícitamente
        // que (a) el total sea divisible entre n colores, y (b) el "esperado"
        // resultante respete la granularidad (docena) del producto, antes de
        // comparar que cada color tenga esa cantidad exacta.
        if (reglasEmpaquetadoActuales?.modo_distribucion_color === 'uniforme') {
            for (let i = 0; i < bultosParsed.length; i++) {
                const b = bultosParsed[i];
                const n = b.colores.length;
                if (n < 2) continue;
                const total = b.colores.reduce((s, c) => s + c.cantidad, 0);

                if (total % n !== 0) {
                    Swal.fire('Reparto no uniforme posible',
                        `El bulto ${i + 1} tiene ${formatearCantidadEmp(total)} unidades entre ${n} colores — no se puede repartir exactamente parejo. Ajusta la cantidad total o el número de colores.`,
                        'warning');
                    return;
                }

                const esperado = total / n;
                if (granularidad > 1 && esperado % granularidad !== 0) {
                    Swal.fire('Reparto no uniforme posible',
                        `El bulto ${i + 1}: repartido parejo tocarían ${formatearCantidadEmp(esperado)} unidades por color, pero cada color debe ser múltiplo de ${granularidad} (docena). Ajusta la cantidad total o el número de colores.`,
                        'warning');
                    return;
                }

                const desparejo = b.colores.some(c => c.cantidad !== esperado);
                if (desparejo) {
                    Swal.fire('Reparto desparejo', `El bulto ${i + 1} debe repartirse parejo entre colores (${esperado} c/u).`, 'warning');
                    return;
                }
            }
        }

        params = {
            producto_id: estacionProductoIdActual,
            operarios: JSON.stringify(estOperariosSeleccionados),
            sucursal_id: document.getElementById('est_sucursal_id').value,
            bultos: bultosJson,
        };
    }

    const json = await llamarEmpaquetado('CREAREMPAQUETADO', params);

    if (json.success) {
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: json.message, showConfirmButton: false, timer: 2200 });

        const productoRecienUsado = estacionProductoIdActual;
        await Promise.all([
            cargarPendientesEmpaquetado(),
            cargarListadoGeneralEmp(),
        ]);
        // Recarga forzada de la estación (disponibles cambiaron)
        estacionProductoIdActual = 0;
        tabActivoEmp = tabActivoEmp; // conserva la pestaña actual
        await cargarEstacionParaProducto(productoRecienUsado);

        // Si el modal de registros de este producto está abierto, refrescarlo también
        if (modalProductoIdActual === productoRecienUsado) {
            await cargarRegistrosEmp();
        }
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});

// =============================================================================
// FORMULARIO: MODAL DE EDICIÓN (registros ya existentes)
// =============================================================================

function cancelarEdicionEmp() {
    document.getElementById('formEditarEmp').reset();
    document.getElementById('emp_id').value = '0';
    document.getElementById('bloqueEdicionRegistro').style.display = 'none';
    document.getElementById('bloqueOrigenesReadonly').style.display = 'none';
    empIdEnEdicion = 0;
    empOperariosSeleccionados = [];
    const cont = document.getElementById('emp_operarios_chips');
    if (cont) cont.innerHTML = '';
}

async function editarRegistroEmp(id) {
    await cargarSelectsModalEdicion();

    const json = await llamarEmpaquetado('OBTENEREMPAQUETADO', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    const r = json.empaquetado;
    empIdEnEdicion = id;
    document.getElementById('emp_id').value = id;
    document.getElementById('emp_unidad_medida').value = r.unidad_medida;
    document.getElementById('emp_sucursal_id').value = r.sucursal ?? '';
    document.getElementById('formEmpTitulo').innerHTML = `<i class="fa-solid fa-pen"></i> Editando registro #${id} (solo unidad / operarios / sucursal)`;

    const operariosDelRegistro = parseJsonColumnaEmp(r.js_operarios);
    empOperariosSeleccionados = operariosDelRegistro.length > 0
        ? operariosDelRegistro.map(o => o.operario_id)
        : (r.operario_id ? [r.operario_id] : []); // fallback para registros legacy
    renderOperariosChips('emp_operarios_chips', empOperariosSeleccionados, 'toggleOperarioModalEdicion');

    // La composición de colores es inmutable en edición: se muestra de solo lectura.
    const origenes = parseJsonColumnaEmp(r.js_origenes);
    const bloqueRO = document.getElementById('bloqueOrigenesReadonly');
    bloqueRO.style.display = 'block';
    bloqueRO.innerHTML = origenes.length
        ? '<div class="pc-origenes-readonly"><b>Composición (no editable):</b><br>' +
          origenes.map(o => `<span class="swatch-mini" style="display:inline-block; width:12px; height:12px; border-radius:4px; margin-right:6px; vertical-align:-1px; background:${colorHexPara(o.color_nombre, o.color_hex)};"></span>${o.color_nombre ?? 'Sin color'} — ${o.origen_tipo === 'ensamblaje' ? 'Ensamblaje' : 'Producción'} #${o.origen_id}: ${formatearCantidadEmp(o.cantidad)}`).join('<br>') +
          '<br><small class="text-muted">Para corregir la mezcla, elimina este registro y crea uno nuevo desde la estación de armado.</small></div>'
        : '<div class="pc-origenes-readonly">Sin detalle de origen (registro legacy).</div>';

    document.getElementById('bloqueEdicionRegistro').style.display = 'block';
    document.getElementById('bloqueEdicionRegistro').scrollIntoView({ behavior: 'smooth' });
}

document.getElementById('formEditarEmp').addEventListener('submit', async function (e) {
    e.preventDefault();
    if (empIdEnEdicion <= 0) return;

    if (empOperariosSeleccionados.length === 0) {
        Swal.fire('Falta información', 'Selecciona al menos un operario.', 'warning');
        return;
    }

    const params = {
        id: empIdEnEdicion,
        unidad_medida: document.getElementById('emp_unidad_medida').value,
        operarios: JSON.stringify(empOperariosSeleccionados),
        sucursal_id: document.getElementById('emp_sucursal_id').value,
    };

    const json = await llamarEmpaquetado('EDITAREMPAQUETADO', params);

    if (json.success) {
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: json.message, showConfirmButton: false, timer: 2200 });
        cancelarEdicionEmp();
        await Promise.all([
            cargarRegistrosEmp(),
            cargarListadoGeneralEmp(),
        ]);
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});

function eliminarRegistroEmp(id) {
    Swal.fire({
        title: '¿Eliminar este registro de empaquetado?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarEmpaquetado('ELIMINAREMPAQUETADO', { id });
        if (json.success) {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: json.message, showConfirmButton: false, timer: 1800 });
            if (empIdEnEdicion === id) cancelarEdicionEmp();

            const productoAfectado = modalProductoIdActual;
            await Promise.all([
                cargarRegistrosEmp(),
                cargarPendientesEmpaquetado(),
                cargarListadoGeneralEmp(),
            ]);

            // Si la estación de armado activa es del mismo producto, refrescar
            // sus disponibles (la eliminación libera cantidad).
            if (estacionProductoIdActual === productoAfectado) {
                await cargarOrigenesDisponibles(productoAfectado);
                aplicarUnidadEmpaquetadoFija();
                if (esModoMezcla()) renderMezcla(); else renderBultos();
            }
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>