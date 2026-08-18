<?php
$pageTitle    = 'Empaquetado';
$pageSubtitle = 'Registro de empaquetado de ensamblajes finalizados';
$activePage = 'empaquetado';

include("header.php");
?>

<!--
    empaquetado.php (REESCRITO 2026-07-30 v4)

    MODELO DE UI:
    1) Grid de ensamblajes FINALIZADOS (LISTARENSAMBLAJESPARAEMPAQUETADO).
    2) Grid de producciones DIRECTAS a empaquetado, sin pasar por ensamblaje
       (LISTARPRODUCCIONESPARAEMPAQUETADO) — moldes/productos configurados
       con necesita_ensamblaje = 'no'.
    3) Modal compartido: recibe (ensamblajeId, productoLabel, produccionId).
       Ambos ids son mutuamente excluyentes; uno de los dos siempre es 0.
    4) Tabla de "Registros de empaquetado" (histórico general, ambos
       orígenes), con columna "Origen" que distingue Ensamblaje/Producción
       vía el campo origen_tipo que devuelve el backend.
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
.pc-btn-empaquetar--hecho{
    background:#fff; color:#2F6FED; border:1px solid #2F6FED;
}
.pc-btn-empaquetar--hecho:hover{ background:#2F6FED; color:#fff; }

/* ── Tabla de registros (dentro del modal y en el listado general) ── */
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

/* ── Bultos dinámicos ── */
.pc-bulto-row{ display:flex; gap:8px; align-items:center; margin-bottom:8px; }
.pc-bulto-row input{ flex:1; }
.pc-bulto-remove{ border:none; background:none; color:#c94a4a; font-size:1em; flex:0 0 auto; }
.pc-bultos-total{
    display:flex; justify-content:space-between; align-items:center;
    padding:8px 12px; background:#fffaf0; border:1px solid #f4e8c8; border-radius:10px;
    font-size:.9em; margin-top:6px;
}
.pc-bultos-total b{ color:#2F6FED; font-size:1.05em; }

/* ── Listado general ── */
.pc-listado-filtros{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:12px; }
.pc-listado-filtros select, .pc-listado-filtros input[type="date"]{ max-width:180px; }
</style>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Empaquetado</h2>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <br>
        <input type="text" id="femp_texto" class="form-control" style="max-width:260px"
               placeholder="Buscar por producto...">
        <div class="form-check d-flex align-items-center gap-2" style="margin-left:4px;">
            <input class="form-check-input" type="checkbox" id="femp_solo_sin">
            <label class="form-check-label" for="femp_solo_sin" style="font-size:.85em;">Solo sin empaquetar aún</label>
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
                <tr><td colspan="8" class="text-center text-muted">Cargando...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal por ensamblaje/producción: lista de registros + alta/edición -->
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
            <input type="hidden" id="emp_ensamblaje_id" value="0">
            <input type="hidden" id="emp_produccion_id" value="0">
            <div class="row">
                <div class="col-md-4 mb-2">
                    <label class="form-label">Unidad de medida *</label>
                    <select class="form-select" id="emp_unidad_medida" required></select>
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

            <label class="form-label">Unidades Paquetes *</label>
            <div id="listaBultos"></div>
            <button type="button" class="btn btn-sm btn-outline-secondary mb-2" onclick="agregarFilaBulto()">
                <i class="fa-solid fa-plus"></i> Agregar Paquete
            </button>

            <div class="pc-bultos-total">
                <span><span id="bultosCount">0</span> paquete(s)</span>
                <span>Total: <b id="bultosTotal">0</b></span>
            </div>

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

// ── Estado del modal: ensamblajeId y produccionId son mutuamente
// excluyentes — solo uno de los dos es > 0 en cada apertura. ──
let empEnsamblajeIdActual = 0;
let empProduccionIdActual = 0;
let empIdEnEdicion = 0;
let empUnidadesCache = null;
let empOperariosCache = null;
let bultosState = []; // [{tempId, valor}]
let contadorBulto = 0;

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

// ── Llamada genérica al controlador ─────────────────────────────────────────
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

// Columnas jsonb pueden llegar como string o ya decodificadas según el driver.
function parseJsonColumnaEmp(v) {
    if (!v) return [];
    if (typeof v === 'string') {
        try { return JSON.parse(v) || []; } catch (e) { return []; }
    }
    return Array.isArray(v) ? v : [];
}

// Texto "(= 96 UND)" cuando la unidad tiene conversión a base; vacío si no.
function textoEquivalenteEmp(r) {
    if (r.unidad_base_id && r.cantidad_tota_en_base != null) {
        return ` <span class="text-muted" style="font-size:.85em;">(= ${formatearCantidadEmp(r.cantidad_tota_en_base)} ${r.unidad_base_corto ?? ''})</span>`;
    }
    return '';
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
        return;
    }

    const filas = json.ensamblajes || [];
    if (filas.length === 0) {
        grid.innerHTML = '<div class="pc-emp-empty">No hay ensamblajes finalizados todavía.</div>';
        return;
    }

    grid.innerHTML = filas.map(e => {
        const yaEmpaquetado = (e.empaquetados_count ?? 0) > 0;
        const btnClase  = yaEmpaquetado ? 'pc-btn-empaquetar pc-btn-empaquetar--hecho' : 'pc-btn-empaquetar';
        const btnIcono  = yaEmpaquetado ? 'fa-solid fa-eye' : 'fa-solid fa-box';
        const btnTexto  = yaEmpaquetado ? 'Ver empaquetado' : 'Empaquetar';
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
                    <span class="lbl">Registros de empaquetado</span>
                    <span class="val">${e.empaquetados_count ?? 0}</span>
                </div>
                <div class="pc-emp-field span-2">
                    <span class="lbl">Total ya empaquetado</span>
                    <span class="val">${formatearCantidadEmp(e.cantidad_total_empaquetada)}</span>
                </div>
                <div class="pc-emp-field span-2">
                    <span class="lbl">Finalizado</span>
                    <span class="val">${formatearFechaHoraLegibleEmp(e.fin)}</span>
                </div>
            </div>
            <div class="pc-emp-card-foot">
                <button type="button" class="${btnClase}"
                        onclick="abrirModalEmpaquetado(${e.ensamblaje_id}, '${productoLabel}')">
                    <i class="${btnIcono}"></i> ${btnTexto}
                </button>
            </div>
        </div>
    `;
    }).join('');
}

// =============================================================================
// GRID DE PRODUCCIONES DIRECTAS (sin ensamblaje)
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
        grid.innerHTML = '<div class="pc-emp-empty">No hay producciones directas pendientes de empaquetar.</div>';
        return;
    }

    grid.innerHTML = filas.map(pd => {
        const yaEmpaquetado = (pd.empaquetados_count ?? 0) > 0;
        const btnClase  = yaEmpaquetado ? 'pc-btn-empaquetar pc-btn-empaquetar--hecho' : 'pc-btn-empaquetar';
        const btnIcono  = yaEmpaquetado ? 'fa-solid fa-eye' : 'fa-solid fa-box';
        const btnTexto  = yaEmpaquetado ? 'Ver empaquetado' : 'Empaquetar';
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
                    <span class="lbl">Producido</span>
                    <span class="val">${formatearCantidadEmp(pd.cantidad_producida_kg)}</span>
                </div>
                <div class="pc-emp-field span-2">
                    <span class="lbl">Registros de empaquetado</span>
                    <span class="val">${pd.empaquetados_count ?? 0}</span>
                </div>
                <div class="pc-emp-field span-2">
                    <span class="lbl">Finalizado</span>
                    <span class="val">${formatearFechaHoraLegibleEmp(pd.fecha_hora_fin)}</span>
                </div>
            </div>
            <div class="pc-emp-card-foot">
                <button type="button" class="${btnClase}"
                        onclick="abrirModalEmpaquetado(0, '${productoLabel}', ${pd.produccion_id})">
                    <i class="${btnIcono}"></i> ${btnTexto}
                </button>
            </div>
        </div>`;
    }).join('');
}

// =============================================================================
// LISTADO GENERAL (debajo de los grids)
// =============================================================================

async function cargarListadoGeneralEmp() {
    const tbody = document.getElementById('tablaListadoGeneralEmp');
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted"><i class="fa-solid fa-spinner fa-spin"></i> Cargando...</td></tr>';

    const params = {
        texto: document.getElementById('femp_texto').value.trim(),
        estado: document.getElementById('flist_estado').value,
        fecha_desde: document.getElementById('flist_fecha_desde').value,
        fecha_hasta: document.getElementById('flist_fecha_hasta').value,
    };
    const json = await llamarEmpaquetado('LISTARTODOSEMPAQUETADOS', params);
    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">${json.message}</td></tr>`;
        return;
    }

    const registros = json.empaquetados || [];
    if (registros.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No hay registros de empaquetado con estos filtros.</td></tr>';
        return;
    }

    tbody.innerHTML = registros.map(r => {
        const bultos = parseJsonColumnaEmp(r.js_cantidades);
        const bultosTexto = bultos.map(b => formatearCantidadEmp(b.cantidad)).join(' + ') || '-';
        const vendido = !!r.pasado_venta;
        const productoLabel = `${(r.producto_codigo ?? '')} - ${(r.producto_descripcion ?? '')}`.replace(/'/g, "\\'");
        const esProduccion = r.origen_tipo === 'produccion';
        const origenTexto  = esProduccion ? `Producción #${r.produccion_id}` : `Ensamblaje #${r.emsamblaje_id}`;
        const onclickAbrir = esProduccion
            ? `abrirModalEmpaquetado(0, '${productoLabel}', ${r.produccion_id})`
            : `abrirModalEmpaquetado(${r.emsamblaje_id}, '${productoLabel}')`;
        return `
        <tr>
            <td>${origenTexto}</td>
            <td>${r.producto_codigo ?? ''} - ${r.producto_descripcion ?? '-'}</td>
            <td>${r.sucursal_nombre ?? '-'}</td>
            <td class="bultos-detalle">${bultosTexto} <span class="text-muted">(${bultos.length})</span></td>
            <td><b>${formatearCantidadEmp(r.cantidad_tota)}</b> ${r.unidad_corto ?? ''}${textoEquivalenteEmp(r)}</td>
            <td>${r.operario_nombre ?? '-'}</td>
            <td>${formatearFechaHoraLegibleEmp(r.created_at)}</td>
            <td>${vendido
                ? `<span class="badge-vendido">Vendido ${formatearFechaHoraLegibleEmp(r.pasado_venta)}</span>`
                : '<span class="badge bg-success">Disponible</span>'}</td>
            <td>
                <button type="button" class="pc-icon-btn" title="Abrir"
                        onclick="${onclickAbrir}">
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
    if (unidades.length === 0) {
        sUnidad.innerHTML = '<option value="">(sin unidades disponibles - revisar consola)</option>';
        console.error('BUSCARUNIDADESMEDIDA no devolvió unidades. Revisa la respuesta del servidor.');
    } else {
        sUnidad.innerHTML = '<option value="">Selecciona...</option>' +
            unidades.map(u => `<option value="${u.id}">${u.nombre} (${u.nombre_corto})</option>`).join('');
    }
    const sSuc = document.getElementById('emp_sucursal_id');
        sSuc.innerHTML = '<option value="">Selecciona...</option>' +
            (sucursales || []).map(s => `<option value="${s.id}">${s.nombre}</option>`).join('');
    

    const sOp = document.getElementById('emp_operario_id');
    if (operarios.length === 0) {
        sOp.innerHTML = '<option value="">(sin operarios disponibles - revisar consola)</option>';
    } else {
        sOp.innerHTML = '<option value="">Selecciona...</option>' +
            operarios.map(o => `<option value="${o.id}">${o.nombre_completo}</option>`).join('');
    }
}

// =============================================================================
// MODAL: lista de registros + formulario
// =============================================================================

// ensamblajeId y produccionId son mutuamente excluyentes: pasa 0 en el que
// no aplique. Si produccionId > 0, ensamblajeId debe venir en 0 (y viceversa).
async function abrirModalEmpaquetado(ensamblajeId, productoLabel, produccionId = 0) {
    empEnsamblajeIdActual = ensamblajeId || 0;
    empProduccionIdActual = produccionId || 0;

    const etiquetaOrigen = empEnsamblajeIdActual
        ? `Empaquetar #${empEnsamblajeIdActual}`
        : `Empaquetar (Producción #${empProduccionIdActual})`;
    document.getElementById('modalEmpaquetadoTitulo').textContent = `${etiquetaOrigen} — ${productoLabel}`;
    document.getElementById('emp_ensamblaje_id').value = empEnsamblajeIdActual;
    document.getElementById('emp_produccion_id').value = empProduccionIdActual;

    cancelarEdicionEmp(); // resetea el formulario a modo "nuevo"
    await cargarSelectsFormEmp();
    await cargarRegistrosEmp();

    modalEmpaquetado.show();
}

async function cargarRegistrosEmp() {
    const tbody = document.getElementById('tablaRegistrosEmp');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted"><i class="fa-solid fa-spinner fa-spin"></i> Cargando...</td></tr>';

    const json = await llamarEmpaquetado('LISTAREMPAQUETADOS', {
        ensamblaje_id: empEnsamblajeIdActual,
        produccion_id: empProduccionIdActual,
    });
    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger">${json.message}</td></tr>`;
        return;
    }

    const registros = json.empaquetados || [];
    if (registros.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Aún no hay registros de empaquetado para este origen.</td></tr>';
        return;
    }

    tbody.innerHTML = registros.map(r => {
        const bultos = parseJsonColumnaEmp(r.js_cantidades);
        const bultosTexto = bultos.map(b => formatearCantidadEmp(b.cantidad)).join(' + ') || '-';
        const vendido = !!r.pasado_venta;
        return `
        <tr>
            <td class="bultos-detalle">${bultosTexto} <span class="text-muted">(${bultos.length})</span></td>
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

// ── Bultos dinámicos ───────────────────────────────────────────────────────
function renderBultos() {
    const cont = document.getElementById('listaBultos');
    cont.innerHTML = bultosState.map(b => `
        <div class="pc-bulto-row" id="bulto_${b.tempId}">
            <input type="number" min="0.0001" step="0.0001" class="form-control form-control-sm"
                   value="${b.valor}" oninput="actualizarBulto(${b.tempId}, this.value)" placeholder="Cantidad">
            <button type="button" class="pc-bulto-remove" onclick="quitarFilaBulto(${b.tempId})" title="Quitar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    `).join('');
    actualizarResumenBultos();
}

function agregarFilaBulto(valorInicial = '') {
    bultosState.push({ tempId: ++contadorBulto, valor: valorInicial });
    renderBultos();
}

function quitarFilaBulto(tempId) {
    bultosState = bultosState.filter(b => b.tempId !== tempId);
    renderBultos();
}

function actualizarBulto(tempId, valor) {
    const b = bultosState.find(x => x.tempId === tempId);
    if (b) b.valor = valor;
    actualizarResumenBultos();
}

function actualizarResumenBultos() {
    const total = bultosState.reduce((s, b) => s + (parseFloat(b.valor) || 0), 0);
    document.getElementById('bultosCount').textContent = bultosState.length;
    document.getElementById('bultosTotal').textContent = formatearCantidadEmp(total);
}

function obtenerBultosJsonEmp() {
    return JSON.stringify(bultosState.map(b => parseFloat(b.valor) || 0).filter(v => v > 0));
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
    agregarFilaBulto();
}

async function editarRegistroEmp(id) {
    const json = await llamarEmpaquetado('OBTENEREMPAQUETADO', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    const r = json.empaquetado;
    empIdEnEdicion = id;
    document.getElementById('emp_id').value = id;
    document.getElementById('emp_unidad_medida').value = r.unidad_medida;
    document.getElementById('emp_operario_id').value = r.operario_id;
    document.getElementById('emp_sucursal_id').value = r.sucursal ?? '';document.getElementById('formEmpTitulo').innerHTML = `<i class="fa-solid fa-pen"></i> Editando registro #${id}`;
    document.getElementById('btnCancelarEdicionEmp').style.display = 'inline-block';

    const bultos = parseJsonColumnaEmp(r.js_cantidades);
    bultosState = bultos.map(b => ({ tempId: ++contadorBulto, valor: b.cantidad }));
    if (bultosState.length === 0) agregarFilaBulto();
    renderBultos();

    document.getElementById('formEmpaquetado').scrollIntoView({ behavior: 'smooth' });
}

document.getElementById('formEmpaquetado').addEventListener('submit', async function (e) {
    e.preventDefault();

    const bultosJson = obtenerBultosJsonEmp();
    if (JSON.parse(bultosJson).length === 0) {
        Swal.fire('Falta información', 'Agrega al menos un bulto con cantidad mayor a 0.', 'warning');
        return;
    }

    const params = {
        id: empIdEnEdicion,
        ensamblaje_id: empEnsamblajeIdActual,
        produccion_id: empProduccionIdActual,
        unidad_medida: document.getElementById('emp_unidad_medida').value,
        operario_id: document.getElementById('emp_operario_id').value,
        sucursal_id: document.getElementById('emp_sucursal_id').value,
        bultos: bultosJson,
    };

    const accion = empIdEnEdicion > 0 ? 'EDITAREMPAQUETADO' : 'CREAREMPAQUETADO';
    const json = await llamarEmpaquetado(accion, params);

    if (json.success) {
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: json.message, showConfirmButton: false, timer: 1800 });
        cancelarEdicionEmp();
        await Promise.all([
            cargarPendientesEmpaquetado(),
            cargarProduccionesDirectasEmpaquetado(),
            cargarListadoGeneralEmp(),
        ]);
        modalEmpaquetado.hide();
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
                cargarRegistrosEmp(),
                cargarPendientesEmpaquetado(),
                cargarProduccionesDirectasEmpaquetado(),
                cargarListadoGeneralEmp(),
            ]);
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>