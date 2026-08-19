<?php
$pageTitle    = 'Empaquetado';
$pageSubtitle = 'Registro de empaquetado por producto (con mezcla de colores)';
$activePage = 'empaquetado';

include("header.php");
?>

<!--
    empaquetado.php (REESCRITO 2026-08-18 v5 — mezcla de colores por bulto)

    CAMBIO DE MODELO respecto a v4:
    - El modal ya NO se abre por un ensamblaje/producción puntual: se abre
      por PRODUCTO. Dentro, cada bulto puede combinar cantidades tomadas de
      varios orígenes (ensamblajes y/o producciones directas) de distinto
      color, siempre que el producto sea el mismo.
    - BUSCARORIGENESDISPONIBLES trae, por producto, cada origen con su
      color y su disponible restante (ya descontado lo consumido).
    - CREAREMPAQUETADO recibe producto_id + bultos[].colores[] en vez de
      ensamblaje_id/produccion_id sueltos.
    - La edición (EDITAREMPAQUETADO) solo toca cabecera (unidad, operario,
      sucursal): la mezcla de colores es de solo lectura una vez creada.
-->

<style>
.pc-emp-grid{
    display:grid; grid-template-columns:repeat(auto-fill, minmax(300px,1fr));
    gap:14px; margin-top:4px;
}
.pc-emp-card{
    border:1px solid #e7e4dd; border-radius:14px; background:#fff;
    overflow:hidden; display:flex; flex-direction:column;
    transition:box-shadow .12s ease, transform .12s ease;
}
.pc-emp-card:hover{ box-shadow:0 6px 16px rgba(0,0,0,.08); transform:translateY(-1px); }
.pc-emp-card-head{
    padding:10px 14px; background:#fdfcfa; border-bottom:1px solid #eee7db;
    display:flex; justify-content:space-between; align-items:flex-start; gap:8px;
}
.pc-emp-card-head .titulo{ display:flex; flex-direction:column; gap:2px; min-width:0; }
.pc-emp-card-head .id{ font-size:.72em; color:#9a9585; font-weight:600; }
.pc-emp-card-head .producto-titulo{ font-weight:700; font-size:.95em; }
.pc-emp-card-body{ padding:12px 14px; display:grid; grid-template-columns:1fr 1fr; gap:8px 12px; flex:1; }
.pc-emp-field{ min-width:0; }
.pc-emp-field .lbl{ font-size:.68em; text-transform:uppercase; letter-spacing:.03em; color:#9a9585; display:block; margin-bottom:1px; }
.pc-emp-field .val{ font-size:.85em; color:#3a3730; font-weight:600; overflow-wrap:break-word; }
.pc-emp-field.span-2{ grid-column:1/-1; }
.pc-emp-card-foot{
    padding:8px 14px; border-top:1px solid #eee7db; background:#fffefb;
    display:flex; justify-content:flex-end; align-items:center; gap:6px; flex-wrap:wrap;
}
.pc-emp-empty{ text-align:center; color:#9a9585; padding:40px 12px; grid-column:1/-1; }

.pc-btn-empaquetar{
    padding:7px 12px; font-size:.8em; border-radius:8px; border:1px solid #2F6FED;
    background:#EAF0FE; color:#2F6FED; font-weight:700; display:inline-flex; align-items:center; gap:6px;
    transition:.12s ease;
}
.pc-btn-empaquetar:hover{ background:#2F6FED; color:#fff; }

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

    <div class="pc-emp-grid" id="gridEmpaquetado">
        <div class="pc-emp-empty">Cargando...</div>
    </div>
</div>

<div class="pc-card mt-3">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Producciones directas a empaquetar</h2>
        <small class="text-muted">Moldes/productos configurados sin ensamblaje</small>
    </div>
    <div class="pc-emp-grid" id="gridProduccionesDirectas">
        <div class="pc-emp-empty">Cargando...</div>
    </div>
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
                    <th>Operario</th>
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

<!-- Modal por PRODUCTO: lista de registros + alta/edición con mezcla de colores -->
<div class="modal fade" id="modalEmpaquetado" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalEmpaquetadoTitulo">Empaquetar</h5>
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
                        <th>Operario</th>
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

        <hr>

        <h6 class="mb-2" id="formEmpTitulo"><i class="fa-solid fa-plus"></i> Nuevo registro de empaquetado</h6>
        <form id="formEmpaquetado">
            <input type="hidden" id="emp_id" value="0">
            <input type="hidden" id="emp_producto_id" value="0">
            <div class="row">
                <div class="col-md-4 mb-2">
                    <label class="form-label">Unidad de medida *</label>
                    <select class="form-select" id="emp_unidad_medida" required></select>
                    <small class="text-muted" id="avisoUnidadEmpaquetado" style="display:none;">
                        Este producto no tiene "Salida en Empaquetado" configurada — selecciónala aquí y configúrala en Productos para la próxima vez.
                    </small>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">Operario *</label>
                    <select class="form-select" id="emp_operario_id" required></select>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">Sucursal</label>
                    <select class="form-select" id="emp_sucursal_id"></select>
                </div>
            </div>

            <!-- Bloque de bultos: solo visible al CREAR. Cada bulto puede
                 mezclar cantidades de varios orígenes/colores del mismo
                 producto. -->
            <div id="bloqueBultos">
                <label class="form-label">Unidades Paquetes (mezcla de colores) *</label>
                <div id="listaBultos"></div>
                <button type="button" class="btn btn-sm btn-outline-secondary mb-2" onclick="agregarBulto()">
                    <i class="fa-solid fa-plus"></i> Agregar Paquete
                </button>
                <div class="pc-bultos-total">
                    <span><span id="bultosCount">0</span> paquete(s)</span>
                    <span>Total: <b id="bultosTotal">0</b></span>
                </div>
            </div>

            <!-- Bloque de solo lectura: visible al EDITAR (la mezcla ya no se toca) -->
            <div id="bloqueOrigenesReadonly" style="display:none;"></div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCancelarEdicionEmp" style="display:none;" onclick="cancelarEdicionEmp()">
                    Cancelar edición
                </button>
            </div>
        </form>

      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const CONTROLADOR_EMPAQUETADO = 'controllers/clssEmpaquetado.php';
const modalEmpaquetado = new bootstrap.Modal(document.getElementById('modalEmpaquetado'));

// ── Estado del modal: ahora keyado por PRODUCTO, no por origen puntual ──
let empProductoIdActual = 0;
let empIdEnEdicion = 0;
let empUnidadesCache = null;
let empOperariosCache = null;
let origenesDisponiblesCache = []; // BUSCARORIGENESDISPONIBLES del producto actual
let bultosState = [];  // [{tempId, colores:[{tempColorId, origen_tipo, origen_id, color_id, color_nombre, cantidad}]}]
let contadorBulto = 0;
let contadorColorRow = 0;

// ── Tabs por producto (100% client-side) ──
let cacheFilasEmpaquetado = [];
let tabActivoEmp = '__todos__';

document.addEventListener('DOMContentLoaded', () => {
    cargarPendientesEmpaquetado();
    cargarProduccionesDirectasEmpaquetado();
    cargarListadoGeneralEmp();

    let debounceTimer = null;
    document.getElementById('femp_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            cargarPendientesEmpaquetado();
            cargarProduccionesDirectasEmpaquetado();
            cargarListadoGeneralEmp();
        }, 350);
    });
    document.getElementById('femp_solo_sin').addEventListener('change', () => {
        cargarPendientesEmpaquetado();
        cargarProduccionesDirectasEmpaquetado();
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
        const colores = (b.colores || [])
            .map(c => `${c.color_nombre ?? 'Sin color'}: ${formatearCantidadEmp(c.cantidad)}`)
            .join(', ');
        return `${formatearCantidadEmp(b.cantidad)}${colores ? ` (${colores})` : ''}`;
    }).join(' + ');
}

// =============================================================================
// GRID DE ENSAMBLAJES FINALIZADOS
// =============================================================================

async function cargarPendientesEmpaquetado() {
    const params = {
        texto: document.getElementById('femp_texto').value.trim(),
        solo_sin_empaquetar: document.getElementById('femp_solo_sin').checked ? '1' : '0',
    };
    const json = await llamarEmpaquetado('LISTARENSAMBLAJESPARAEMPAQUETADO', params);
    const grid = document.getElementById('gridEmpaquetado');

    if (!json.success) {
        grid.innerHTML = `<div class="pc-emp-empty">${json.message}</div>`;
        cacheFilasEmpaquetado = [];
        renderTabsEmp();
        return;
    }

    cacheFilasEmpaquetado = json.ensamblajes || [];

    if (tabActivoEmp !== '__todos__' && !cacheFilasEmpaquetado.some(f => claveTabEmp(f) === tabActivoEmp)) {
        tabActivoEmp = '__todos__';
    }

    renderTabsEmp();
    renderGridEmpaquetadoFiltrado();
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
    renderGridEmpaquetadoFiltrado();
}

function renderGridEmpaquetadoFiltrado() {
    const grid = document.getElementById('gridEmpaquetado');
    const filas = tabActivoEmp === '__todos__'
        ? cacheFilasEmpaquetado
        : cacheFilasEmpaquetado.filter(f => claveTabEmp(f) === tabActivoEmp);

    if (filas.length === 0) {
        grid.innerHTML = '<div class="pc-emp-empty">No hay ensamblajes finalizados con disponible para este filtro.</div>';
        return;
    }

    grid.innerHTML = filas.map(e => {
        const productoLabel = `${(e.producto_codigo ?? '')} - ${(e.producto_descripcion ?? '')}`.replace(/'/g, "\\'");
        return `
        <div class="pc-emp-card">
            <div class="pc-emp-card-head">
                <div class="titulo">
                    <span class="id">Ensamblaje #${e.ensamblaje_id}</span>
                    <span class="producto-titulo">${e.producto_codigo ?? ''} - ${e.producto_descripcion ?? '-'}</span>
                </div>
            </div>
            <div class="pc-emp-card-body">
                <div class="pc-emp-field">
                    <span class="lbl">Cantidad ensamblada</span>
                    <span class="val">${formatearCantidadEmp(e.cantidad_peso_kg)} ${e.unidad_salida_codigo || 'kg'}</span>
                </div>
                <div class="pc-emp-field">
                    <span class="lbl">Disponible</span>
                    <span class="val">${formatearCantidadEmp(e.cantidad_disponible)}</span>
                </div>
                <div class="pc-emp-field span-2">
                    <span class="lbl">Ya empaquetado</span>
                    <span class="val">${formatearCantidadEmp(e.cantidad_total_empaquetada)} · ${e.empaquetados_count ?? 0} registro(s)</span>
                </div>
                <div class="pc-emp-field span-2">
                    <span class="lbl">Finalizado</span>
                    <span class="val">${formatearFechaHoraLegibleEmp(e.fin)}</span>
                </div>
            </div>
            <div class="pc-emp-card-foot">
                <button type="button" class="pc-btn-empaquetar"
                        onclick="abrirModalEmpaquetado(${e.producto_id}, '${productoLabel}')">
                    <i class="fa-solid fa-box"></i> Empaquetar
                </button>
            </div>
        </div>
    `;
    }).join('');
}

// =============================================================================
// GRID DE PRODUCCIONES DIRECTAS
// =============================================================================

async function cargarProduccionesDirectasEmpaquetado() {
    const params = {
        texto: document.getElementById('femp_texto').value.trim(),
        solo_sin_empaquetar: document.getElementById('femp_solo_sin').checked ? '1' : '0',
    };
    const json = await llamarEmpaquetado('LISTARPRODUCCIONESPARAEMPAQUETADO', params);
    const grid = document.getElementById('gridProduccionesDirectas');

    if (!json.success) {
        grid.innerHTML = `<div class="pc-emp-empty">${json.message}</div>`;
        return;
    }

    const filas = json.producciones || [];
    if (filas.length === 0) {
        grid.innerHTML = '<div class="pc-emp-empty">No hay producciones directas con disponible pendiente de empaquetar.</div>';
        return;
    }

    grid.innerHTML = filas.map(pd => {
        const productoLabel = `${(pd.producto_codigo ?? '')} - ${(pd.producto_descripcion ?? '')}`.replace(/'/g, "\\'");
        return `
        <div class="pc-emp-card">
            <div class="pc-emp-card-head">
                <div class="titulo">
                    <span class="id">Producción #${pd.produccion_id}</span>
                    <span class="producto-titulo">${pd.producto_codigo ?? ''} - ${pd.producto_descripcion ?? '-'}</span>
                </div>
            </div>
            <div class="pc-emp-card-body">
                <div class="pc-emp-field">
                    <span class="lbl">Molde / Color</span>
                    <span class="val">${pd.molde_nombre ?? '-'} · ${pd.color_nombre ?? '-'}</span>
                </div>
                <div class="pc-emp-field">
                    <span class="lbl">Disponible</span>
                    <span class="val">${formatearCantidadEmp(pd.cantidad_disponible)}</span>
                </div>
                <div class="pc-emp-field span-2">
                    <span class="lbl">Ya empaquetado</span>
                    <span class="val">${formatearCantidadEmp(pd.cantidad_total_empaquetada)} · ${pd.empaquetados_count ?? 0} registro(s)</span>
                </div>
                <div class="pc-emp-field span-2">
                    <span class="lbl">Finalizado</span>
                    <span class="val">${formatearFechaHoraLegibleEmp(pd.fecha_hora_fin)}</span>
                </div>
            </div>
            <div class="pc-emp-card-foot">
                <button type="button" class="pc-btn-empaquetar"
                        onclick="abrirModalEmpaquetado(${pd.producto_id}, '${productoLabel}')">
                    <i class="fa-solid fa-box"></i> Empaquetar
                </button>
            </div>
        </div>`;
    }).join('');
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
            <td>${r.operario_nombre ?? '-'}</td>
            <td>${formatearFechaHoraLegibleEmp(r.created_at)}</td>
            <td>${vendido
                ? `<span class="badge-vendido">Vendido ${formatearFechaHoraLegibleEmp(r.pasado_venta)}</span>`
                : '<span class="badge bg-success">Disponible</span>'}</td>
            <td>
                <button type="button" class="pc-icon-btn" title="Abrir"
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

async function cargarSelectsFormEmp() {
    const [unidades, operarios, sucursales] = await Promise.all([
        obtenerUnidadesEmp(), obtenerOperariosEmp(), obtenerSucursalesEmp()
    ]);
    const sUnidad = document.getElementById('emp_unidad_medida');
    sUnidad.innerHTML = unidades.length
        ? '<option value="">Selecciona...</option>' + unidades.map(u => `<option value="${u.id}">${u.nombre} (${u.nombre_corto})</option>`).join('')
        : '<option value="">(sin unidades disponibles - revisar consola)</option>';

    const sSuc = document.getElementById('emp_sucursal_id');
    sSuc.innerHTML = '<option value="">Selecciona...</option>' + (sucursales || []).map(s => `<option value="${s.id}">${s.nombre}</option>`).join('');

    const sOp = document.getElementById('emp_operario_id');
    sOp.innerHTML = operarios.length
        ? '<option value="">Selecciona...</option>' + operarios.map(o => `<option value="${o.id}">${o.nombre_completo}</option>`).join('')
        : '<option value="">(sin operarios disponibles - revisar consola)</option>';
}

let unidadEmpaquetadoProductoActual = null;

async function cargarOrigenesDisponibles(productoId) {
    const json = await llamarEmpaquetado('BUSCARORIGENESDISPONIBLES', { producto_id: productoId });
    if (!json.success) console.error('Error BUSCARORIGENESDISPONIBLES:', json.message);
    origenesDisponiblesCache = json.success ? (json.origenes || []) : [];
    unidadEmpaquetadoProductoActual = json.success ? (json.unidad_empaquetado || null) : null;
}
function aplicarUnidadEmpaquetadoFija() {
    const sUnidad = document.getElementById('emp_unidad_medida');
    const aviso = document.getElementById('avisoUnidadEmpaquetado');

    if (unidadEmpaquetadoProductoActual && unidadEmpaquetadoProductoActual.id) {
        sUnidad.innerHTML = `<option value="${unidadEmpaquetadoProductoActual.id}" selected>
            ${unidadEmpaquetadoProductoActual.nombre} (${unidadEmpaquetadoProductoActual.nombre_corto})
        </option>`;
        sUnidad.disabled = true;
        aviso.style.display = 'none';
    } else {
        // Producto sin "Salida en Empaquetado" configurada: fallback
        // editable (ya trae la lista completa cargada por cargarSelectsFormEmp)
        sUnidad.disabled = false;
        aviso.style.display = 'block';
    }
}
// =============================================================================
// MODAL: lista de registros + formulario
// =============================================================================

async function abrirModalEmpaquetado(productoId, productoLabel) {
    empProductoIdActual = productoId;

    document.getElementById('modalEmpaquetadoTitulo').textContent = `Empaquetar — ${productoLabel}`;
    document.getElementById('emp_producto_id').value = empProductoIdActual;

    cancelarEdicionEmp(); // resetea a modo "nuevo" (bloqueBultos visible, 1 bulto vacío)
    await Promise.all([
        cargarSelectsFormEmp(),
        cargarOrigenesDisponibles(productoId),
    ]);
    aplicarUnidadEmpaquetadoFija(); // fija/bloquea la unidad según config del producto
    renderBultos(); // re-render con el cache de orígenes ya cargado
    await cargarRegistrosEmp();

    modalEmpaquetado.show();
}
async function cargarRegistrosEmp() {
    const tbody = document.getElementById('tablaRegistrosEmp');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted"><i class="fa-solid fa-spinner fa-spin"></i> Cargando...</td></tr>';

    const json = await llamarEmpaquetado('LISTAREMPAQUETADOSPORPRODUCTO', { producto_id: empProductoIdActual });
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
            <td>${r.operario_nombre ?? '-'}</td>
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

function quitarBulto(tempId) {
    bultosState = bultosState.filter(b => b.tempId !== tempId);
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

function actualizarCantidadColor(bultoTempId, tempColorId, valor) {
    const bulto = bultosState.find(b => b.tempId === bultoTempId);
    if (!bulto) return;
    const fila = bulto.colores.find(c => c.tempColorId === tempColorId);
    if (fila) fila.cantidad = valor;
    actualizarResumenBultos(); // solo el total; no re-renderiza para no perder el foco del input
}

function renderBultos() {
    const cont = document.getElementById('listaBultos');
    const capacidad = unidadEmpaquetadoProductoActual?.equivalencia
        ? parseFloat(unidadEmpaquetadoProductoActual.equivalencia)
        : null;

    if (bultosState.length === 0) {
        cont.innerHTML = '<div class="text-muted" style="font-size:.85em;">Agrega al menos un bulto.</div>';
        actualizarResumenBultos();
        return;
    }

    cont.innerHTML = bultosState.map((b, idx) => {
        const total = totalBulto(b);
        const excedido = capacidad !== null && total > capacidad + 0.0001;
        const textoTotal = capacidad !== null
            ? `total: ${formatearCantidadEmp(total)} / ${formatearCantidadEmp(capacidad)}`
            : `total: ${formatearCantidadEmp(total)}`;

        const filasColores = b.colores.map(c => {
            const valorActual = c.origen_tipo ? claveOrigen(c.origen_tipo, c.origen_id) : '';
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
            </div>`;
        }).join('');

        return `
        <div class="pc-bulto-card" style="${excedido ? 'border-color:#c94a4a;' : ''}">
            <div class="pc-bulto-card-head" style="${excedido ? 'color:#c94a4a;' : ''}">
                <span>Bulto ${idx + 1} — ${textoTotal}${excedido ? ' ⚠ excede la capacidad' : ''}</span>
                <button type="button" class="pc-bulto-remove" onclick="quitarBulto(${b.tempId})" title="Quitar bulto">
                    <i class="fa-solid fa-trash"></i>
                </button>
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
    document.getElementById('bultosTotal').textContent = formatearCantidadEmp(total);
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

// ── Alta / edición ─────────────────────────────────────────────────────────
function cancelarEdicionEmp() {
    document.getElementById('formEmpaquetado').reset();
    document.getElementById('emp_id').value = '0';
    document.getElementById('formEmpTitulo').innerHTML = '<i class="fa-solid fa-plus"></i> Nuevo registro de empaquetado';
    document.getElementById('btnCancelarEdicionEmp').style.display = 'none';
    empIdEnEdicion = 0;
    bultosState = [];

    document.getElementById('bloqueBultos').style.display = 'block';
    document.getElementById('bloqueOrigenesReadonly').style.display = 'none';

    aplicarUnidadEmpaquetadoFija(); // re-fija la unidad de empaquetado (modo "nuevo")
    agregarBulto();
}

async function editarRegistroEmp(id) {
    const json = await llamarEmpaquetado('OBTENEREMPAQUETADO', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    const r = json.empaquetado;
    empIdEnEdicion = id;
    document.getElementById('emp_id').value = id;
    document.getElementById('emp_unidad_medida').value = r.unidad_medida;
    document.getElementById('emp_operario_id').value = r.operario_id;
    document.getElementById('emp_sucursal_id').value = r.sucursal ?? '';
    document.getElementById('formEmpTitulo').innerHTML = `<i class="fa-solid fa-pen"></i> Editando registro #${id} (solo unidad / operario / sucursal)`;
    document.getElementById('btnCancelarEdicionEmp').style.display = 'inline-block';

    // La composición de colores es inmutable en edición: se muestra de solo lectura.
    document.getElementById('bloqueBultos').style.display = 'none';
    const origenes = parseJsonColumnaEmp(r.js_origenes);
    const bloqueRO = document.getElementById('bloqueOrigenesReadonly');
    bloqueRO.style.display = 'block';
    bloqueRO.innerHTML = origenes.length
        ? '<div class="pc-origenes-readonly"><b>Composición (no editable):</b><br>' +
          origenes.map(o => `${o.color_nombre ?? 'Sin color'} — ${o.origen_tipo === 'ensamblaje' ? 'Ensamblaje' : 'Producción'} #${o.origen_id}: ${formatearCantidadEmp(o.cantidad)}`).join('<br>') +
          '<br><small class="text-muted">Para corregir la mezcla, elimina este registro y crea uno nuevo.</small></div>'
        : '<div class="pc-origenes-readonly">Sin detalle de origen (registro legacy).</div>';

    document.getElementById('formEmpaquetado').scrollIntoView({ behavior: 'smooth' });
}

document.getElementById('formEmpaquetado').addEventListener('submit', async function (e) {
    e.preventDefault();

    let accion, params;

    if (empIdEnEdicion > 0) {
        accion = 'EDITAREMPAQUETADO';
        params = {
            id: empIdEnEdicion,
            unidad_medida: document.getElementById('emp_unidad_medida').value,
            operario_id: document.getElementById('emp_operario_id').value,
            sucursal_id: document.getElementById('emp_sucursal_id').value,
        };
    } else {
        const bultosJson = obtenerBultosJsonEmp();
        const bultosParsed = JSON.parse(bultosJson);
        if (bultosParsed.length === 0) {
            Swal.fire('Falta información', 'Agrega al menos un bulto con un color/origen y cantidad mayor a 0.', 'warning');
            return;
        }

        // Validación en cliente (espejo de la validación real del backend):
        // ningún bulto puede superar la capacidad de la unidad de
        // empaquetado. Se permite quedar por debajo (paquete parcial).
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

        accion = 'CREAREMPAQUETADO';
        params = {
            producto_id: empProductoIdActual,
            operario_id: document.getElementById('emp_operario_id').value,
            sucursal_id: document.getElementById('emp_sucursal_id').value,
            bultos: bultosJson,
        };
        // Nota: ya no se manda unidad_medida — el backend la deriva del
        // producto (obtenerUnidadEmpaquetadoProducto) y la revalida ahí.
    }

    const json = await llamarEmpaquetado(accion, params);

    if (json.success) {
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: json.message, showConfirmButton: false, timer: 2200 });
        cancelarEdicionEmp();
        await Promise.all([
            cargarOrigenesDisponibles(empProductoIdActual),
            cargarPendientesEmpaquetado(),
            cargarProduccionesDirectasEmpaquetado(),
            cargarListadoGeneralEmp(),
            cargarRegistrosEmp(),
        ]);
        aplicarUnidadEmpaquetadoFija(); // por si cambió algo tras recargar orígenes
        renderBultos(); // refresca disponibles restantes tras el consumo recién guardado
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
            await Promise.all([
                cargarOrigenesDisponibles(empProductoIdActual),
                cargarRegistrosEmp(),
                cargarPendientesEmpaquetado(),
                cargarProduccionesDirectasEmpaquetado(),
                cargarListadoGeneralEmp(),
            ]);
            renderBultos();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>