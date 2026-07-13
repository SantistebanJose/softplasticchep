<?php
$pageTitle    = 'Producción';
$pageSubtitle = 'Avances de producción por orden';
$activePage = 'produccion';

include("header.php");
?>

<style>
/* ===================================================================
   Módulo de materiales estilo "menú" — cards de materiales a la
   izquierda, ticket de avance a la derecha. Prefijo pc-mat- / pc-tk-
   para no chocar con el resto del sistema de diseño (pc-*).
   Paleta: tonos de "pellet" de resina plástica, coherente con el
   rubro (Plásticos Chepito). No decorativo porque sí — cada material
   recibe un color estable (hash de su nombre) para reconocerlo de un
   vistazo entre compras repetidas.
=================================================================== */
:root{
    --resina-1:#2F6FED; --resina-1-bg:#EAF0FE;
    --resina-2:#E23744; --resina-2-bg:#FCEAEC;
    --resina-3:#16A34A; --resina-3-bg:#E8F7EE;
    --resina-4:#D97706; --resina-4-bg:#FDF1E0;
    --resina-5:#7C3AED; --resina-5-bg:#F1EAFD;
    --resina-6:#0E9488; --resina-6-bg:#E2F5F3;
}

.pc-emergencia-toggle{
    border:1px solid #f1d0a6; background:#fff9f0; border-radius:10px;
    padding:10px 14px; display:flex; align-items:center; gap:10px;
    transition:.15s ease;
}
.pc-emergencia-toggle.activa{ background:#fff1e0; border-color:#e8a33d; }
.pc-emergencia-toggle .form-check-input{ width:2.4em; height:1.3em; cursor:pointer; }
.pc-emergencia-toggle .txt{ font-size:.92em; line-height:1.25; }
.pc-emergencia-toggle .txt b{ display:block; color:#8a5a10; }
#prod_orden_wrap.orden-no-aplica select{ background:#f4f4f4; color:#9a9a9a; }
#prod_orden_wrap .badge-opcional{ display:none; }
#prod_orden_wrap.orden-no-aplica .badge-opcional{ display:inline-block; }

.pc-mat-layout{
    display:grid; grid-template-columns:1.35fr 1fr; gap:16px; align-items:start;
}
@media (max-width: 900px){ .pc-mat-layout{ grid-template-columns:1fr; } }

.pc-mat-panel, .pc-tk-panel{
    border:1px solid #e7e4dd; border-radius:14px; background:#fdfcfa; overflow:hidden;
}
.pc-mat-panel-head, .pc-tk-panel-head{
    padding:10px 14px; border-bottom:1px solid #eee7db; display:flex;
    justify-content:space-between; align-items:center; background:#fffefb;
}
.pc-mat-panel-head h6, .pc-tk-panel-head h6{ margin:0; font-weight:700; font-size:.95em; }
.pc-mat-search{ padding:10px 12px 0 12px; }

.pc-mat-grid{
    display:grid; grid-template-columns:repeat(auto-fill, minmax(128px,1fr));
    gap:10px; padding:12px; max-height:340px; overflow-y:auto;
}
.pc-mat-card{
    position:relative; border:1px solid #eae6da; border-radius:12px; background:#fff;
    padding:10px 10px 8px 10px; cursor:pointer; text-align:left;
    transition:transform .12s ease, box-shadow .12s ease, border-color .12s ease;
}
.pc-mat-card:hover{ transform:translateY(-2px); box-shadow:0 6px 14px rgba(0,0,0,.07); }
.pc-mat-card.activa{ border-color:var(--card-color, #2F6FED); box-shadow:0 0 0 2px var(--card-color, #2F6FED) inset; }
.pc-mat-card .pellet{
    width:30px; height:30px; border-radius:9px; display:flex; align-items:center; justify-content:center;
    background:var(--card-bg,#EAF0FE); color:var(--card-color,#2F6FED); font-size:.95em; margin-bottom:8px;
}
.pc-mat-card .nombre{ font-weight:600; font-size:.85em; line-height:1.2; display:block; min-height:2.2em; }
.pc-mat-card .stock{ font-size:.72em; color:#8a8578; margin-top:4px; display:block; }
.pc-mat-card .stock b{ color:#4a4636; }
.pc-mat-card .badge-en-ticket{
    position:absolute; top:-6px; right:-6px; background:var(--card-color,#2F6FED); color:#fff;
    font-size:.68em; font-weight:700; border-radius:999px; min-width:18px; height:18px; padding:0 5px;
    display:flex; align-items:center; justify-content:center;
}
.pc-mat-empty{ grid-column:1/-1; text-align:center; color:#9a9585; font-size:.85em; padding:20px 6px; }

.pc-lote-panel{ border-top:1px dashed #e2ddcd; padding:10px 12px 12px 12px; background:#fbf9f3; }
.pc-lote-panel .titulo{ font-size:.8em; color:#8a5a10; font-weight:700; margin-bottom:8px; display:flex; align-items:center; gap:6px; }
.pc-lote-strip{ display:flex; gap:8px; overflow-x:auto; padding-bottom:2px; }
.pc-lote-chip{
    flex:0 0 auto; min-width:150px; border:1px solid #e8dfc7; background:#fff; border-radius:10px;
    padding:8px 10px; cursor:pointer; transition:.12s ease;
}
.pc-lote-chip:hover{ border-color:#d97706; background:#fffaf0; }
.pc-lote-chip .prov{ font-weight:600; font-size:.8em; display:block; }
.pc-lote-chip .fecha{ font-size:.72em; color:#948d78; display:block; margin-bottom:5px; }
.pc-lote-gauge{ height:5px; border-radius:3px; background:#eee7d6; overflow:hidden; margin-bottom:4px; }
.pc-lote-gauge > span{ display:block; height:100%; background:#16A34A; }
.pc-lote-chip .disp{ font-size:.72em; color:#5c7a3c; font-weight:600; }
.pc-lote-vacio{ font-size:.78em; color:#b45309; }

.pc-tk-list{ list-style:none; margin:0; padding:0; max-height:340px; overflow-y:auto; }
.pc-tk-item{ border-bottom:1px dashed #eee2c8; padding:10px 12px; display:flex; gap:10px; }
.pc-tk-item:last-child{ border-bottom:none; }
.pc-tk-item .pellet-sm{
    width:26px; height:26px; border-radius:8px; flex:0 0 auto; display:flex; align-items:center; justify-content:center;
    background:var(--card-bg,#EAF0FE); color:var(--card-color,#2F6FED); font-size:.8em; margin-top:2px;
}
.pc-tk-item .cuerpo{ flex:1; min-width:0; }
.pc-tk-item .nombre{ font-weight:600; font-size:.85em; }
.pc-tk-item .lote-info{ font-size:.72em; color:#8a8578; margin-top:1px; }
.pc-tk-item .lote-info b{ color:#5c5947; }
.pc-tk-item input.comentario{ font-size:.75em; border:none; border-bottom:1px dashed #ddd6c0; width:100%; padding:2px 0; margin-top:6px; background:transparent; }
.pc-tk-item input.comentario:focus{ outline:none; border-color:#d97706; }
.pc-tk-qty{ display:flex; align-items:center; gap:0; flex:0 0 auto; }
.pc-tk-qty button{
    width:24px; height:24px; border:1px solid #e2ddcd; background:#fff; border-radius:6px;
    display:flex; align-items:center; justify-content:center; font-size:.75em; cursor:pointer;
}
.pc-tk-qty button:disabled{ opacity:.35; cursor:not-allowed; }
.pc-tk-qty input{ width:56px; text-align:center; border:none; font-variant-numeric:tabular-nums; font-weight:700; font-size:.85em; }
.pc-tk-remove{ border:none; background:none; color:#c94a4a; font-size:.85em; align-self:flex-start; }
.pc-tk-empty{ text-align:center; color:#9a9585; font-size:.85em; padding:26px 12px; }
.pc-tk-empty i{ font-size:1.6em; display:block; margin-bottom:6px; opacity:.5; }
.pc-tk-footer{ padding:8px 14px; border-top:1px solid #eee7db; font-size:.78em; color:#8a8578; background:#fffefb; }
</style>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Producción</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrearProduccion()">
            <i class="fa-solid fa-plus"></i> Registrar avance
        </button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <br>
        <input type="text" id="fprod_texto" class="form-control" style="max-width:260px"
               placeholder="Buscar por código de orden u observaciones...">
        <select id="fprod_operario" class="form-select" style="max-width:200px">
            <option value="">Todos los operarios</option>
        </select>
        <select id="fprod_maquina" class="form-select" style="max-width:180px">
            <option value="">Todas las máquinas</option>
        </select>
        <input type="date" id="fprod_desde" class="form-control" style="max-width:160px" title="Desde">
        <input type="date" id="fprod_hasta" class="form-control" style="max-width:160px" title="Hasta">
        <select id="fprod_emergencia" class="form-select" style="max-width:170px">
            <option value="">Todos (con/sin emergencia)</option>
            <option value="si">⚡ Solo emergencias</option>
            <option value="no">Sin emergencia</option>
        </select>
        <select id="fprod_estado" class="form-select" style="max-width:160px">
            <option value="">Todos</option>
            <option value="activa" selected>Activos</option>
            <option value="inactiva">Inactivos</option>
        </select>
    </div>

    <div class="pc-table-wrap pc-table-responsive-cards">
    <table class="pc-table" id="tablaProducciones">
        <thead>
            <tr>
                <th>#</th>
                <th>Orden</th>
                <th>Producto</th>
                <th>Operario</th>
                <th>Máquina</th>
                <th>Fecha</th>
                <th>Cantidad</th>
                <th>Materiales</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tbodyProducciones">
            <tr><td colspan="10" style="text-align:center;">Cargando...</td></tr>
        </tbody>
    </table>
    </div>
</div>

<!-- Modal Crear/Editar -->
<div class="modal fade" id="modalProduccion" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <form id="formProduccion">
        <div class="modal-header">
          <h5 class="modal-title" id="modalProduccionTitulo">Registrar avance de producción</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">

          <div class="pc-emergencia-toggle mb-3" id="prod_emergencia_wrap">
            <input class="form-check-input" type="checkbox" role="switch" id="prod_emergencia">
            <div class="txt">
                <b>⚡ Avance de emergencia</b>
                Rompe el orden habitual de colores/producto porque llegó un pedido urgente.
                La orden de producción pasa a ser opcional y cuéntanos el motivo en observaciones.
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-2" id="prod_orden_wrap">
                <label class="form-label">
                    Orden de producción *
                    <span class="badge bg-warning text-dark badge-opcional">opcional (emergencia)</span>
                </label>
                <select class="form-select" id="prod_orden_id" required>
                    <option value="">Selecciona una orden...</option>
                </select>
                <div class="form-text" id="prod_orden_info"></div>
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Operario</label>
                <select class="form-select" id="prod_operario_id">
                    <option value="">Selecciona...</option>
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Máquina</label>
                <select class="form-select" id="prod_maquina_id">
                    <option value="">Selecciona...</option>
                </select>
            </div>
          </div>

          <div class="row">
            <div class="col-md-3 mb-2">
                <label class="form-label">Cantidad producida (este avance) *</label>
                <input type="number" step="1" min="1" class="form-control" id="prod_cantidad" required>
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Fecha y hora</label>
                <input type="datetime-local" class="form-control" id="prod_fecha">
            </div>
            <div class="col-md-6 mb-2">
                <label class="form-label" id="prod_observaciones_label">Observaciones</label>
                <input type="text" class="form-control" id="prod_observaciones" placeholder="Opcional">
            </div>
          </div>

          <hr>

          <!-- Materiales consumidos: menú de cards + ticket, al estilo de una
               comanda. Cada card es un material; al tocarla aparece la tira
               de lotes disponibles (proveedor / fecha / cuánto queda); al
               tocar un lote se agrega como línea al ticket de la derecha. -->
          <div class="mb-1 d-flex justify-content-between align-items-center">
            <label class="form-label mb-0">Materiales consumidos (opcional)</label>
            <span class="form-text mb-0">Si este avance no consume material nuevo (ej. reproceso), deja el ticket vacío.</span>
          </div>

          <div class="pc-mat-layout">
            <div class="pc-mat-panel">
                <div class="pc-mat-panel-head">
                    <h6><i class="fa-solid fa-boxes-stacked"></i> Menú de materiales</h6>
                </div>
                <div class="pc-mat-search">
                    <input type="text" id="prod_mat_buscar" class="form-control form-control-sm" placeholder="Buscar material...">
                </div>
                <div class="pc-mat-grid" id="prod_materiales_grid">
                    <div class="pc-mat-empty">Cargando materiales...</div>
                </div>
                <div class="pc-lote-panel" id="prod_lote_panel" style="display:none;">
                    <div class="titulo"><i class="fa-solid fa-layer-group"></i> <span id="prod_lote_panel_titulo">Elige el lote de origen</span></div>
                    <div class="pc-lote-strip" id="prod_lote_strip"></div>
                </div>
            </div>

            <div class="pc-tk-panel">
                <div class="pc-tk-panel-head">
                    <h6><i class="fa-solid fa-receipt"></i> Ticket de este avance</h6>
                </div>
                <ul class="pc-tk-list" id="prod_ticket_list">
                    <li class="pc-tk-empty"><i class="fa-solid fa-basket-shopping"></i>Aún no agregas materiales.<br>Toca una card de la izquierda para empezar.</li>
                </ul>
                <div class="pc-tk-footer" id="prod_ticket_footer" style="display:none;"></div>
            </div>
          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const CONTROLADOR_PRODUCCION = 'controllers/clssProduccion.php';
const modalProduccion = new bootstrap.Modal(document.getElementById('modalProduccion'));

let modoEdicionProduccion = false;
let produccionIdActual = 0;
let materialesProdCache = null; // cache de materiales para las cards
let materialSeleccionadoId = null; // material activo en el panel de lotes
let contadorLineaTicket = 0;
let ticketLineas = []; // [{tempId, material_id, material_nombre, unidad_corto, color, icono,
                        //   lote_id, lote_label, disponible, cantidad, comentario}]

document.addEventListener('DOMContentLoaded', () => {
    cargarSelectsFiltro();
    cargarProducciones().catch(err => {
        console.error('Error cargando datos iniciales:', err);
        document.getElementById('tbodyProducciones').innerHTML =
            `<tr><td colspan="10" style="text-align:center;color:red;">Error de conexión con el servidor. Revisa la consola (F12).</td></tr>`;
    });

    let debounceTimer = null;
    document.getElementById('fprod_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarProducciones, 350);
    });
    ['fprod_operario', 'fprod_maquina', 'fprod_estado', 'fprod_emergencia', 'fprod_desde', 'fprod_hasta'].forEach(id => {
        document.getElementById(id).addEventListener('change', cargarProducciones);
    });

    document.getElementById('prod_mat_buscar').addEventListener('input', renderGridMateriales);
    document.getElementById('prod_emergencia').addEventListener('change', aplicarEstadoEmergencia);
});

// ── Llamadas genéricas ────────────────────────────────────────────────────
async function llamarProduccion(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_PRODUCCION, {
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

function badgeRegistroProd(deletedAt) {
    return !deletedAt
        ? '<span class="badge bg-success">Activo</span>'
        : '<span class="badge bg-secondary">Inactivo</span>';
}

function formatearCantidadProd(n) {
    return Number(n ?? 0).toLocaleString('es-PE', { maximumFractionDigits: 4 });
}

function formatearFechaHoraLocal(fechaIso) {
    // Convierte "2026-07-10 14:30:00" a formato válido para datetime-local
    if (!fechaIso) return '';
    return fechaIso.replace(' ', 'T').substring(0, 16);
}

// ── Estética de cada material: color y ícono estables por nombre, para
//    reconocer un material de un vistazo entre compras repetidas. No
//    depende de datos de categoría (no existen), solo de un hash simple. ──
const PALETA_RESINA = [
    { color: '#2F6FED', bg: '#EAF0FE' },
    { color: '#E23744', bg: '#FCEAEC' },
    { color: '#16A34A', bg: '#E8F7EE' },
    { color: '#D97706', bg: '#FDF1E0' },
    { color: '#7C3AED', bg: '#F1EAFD' },
    { color: '#0E9488', bg: '#E2F5F3' },
];
const ICONOS_MATERIAL = [
    'fa-cube', 'fa-flask', 'fa-layer-group', 'fa-industry',
    'fa-vial', 'fa-box-open', 'fa-recycle', 'fa-weight-hanging',
];
function estiloMaterial(nombre) {
    let hash = 0;
    for (let i = 0; i < nombre.length; i++) hash = (hash * 31 + nombre.charCodeAt(i)) >>> 0;
    return {
        ...PALETA_RESINA[hash % PALETA_RESINA.length],
        icono: ICONOS_MATERIAL[hash % ICONOS_MATERIAL.length],
    };
}

// ── Emergencia: la orden pasa a ser opcional y se exige observaciones ────
function aplicarEstadoEmergencia() {
    const activa = document.getElementById('prod_emergencia').checked;
    const wrapToggle = document.getElementById('prod_emergencia_wrap');
    const wrapOrden = document.getElementById('prod_orden_wrap');
    const ordenSelect = document.getElementById('prod_orden_id');
    const obsLabel = document.getElementById('prod_observaciones_label');

    wrapToggle.classList.toggle('activa', activa);
    wrapOrden.classList.toggle('orden-no-aplica', activa);
    ordenSelect.required = !activa;
    obsLabel.textContent = activa ? 'Observaciones (cuéntanos el motivo de la emergencia) *' : 'Observaciones';
}

// ── Selects auxiliares ───────────────────────────────────────────────────────
async function cargarSelectsFiltro() {
    const [operario, maquinas] = await Promise.all([
        llamarProduccion('BUSCAROPERARIOS'),
        llamarProduccion('BUSCARMAQUINAS'),
    ]);
    if (operario.success) {
        const s = document.getElementById('fprod_operario');
        operario.operario.forEach(o => s.insertAdjacentHTML('beforeend', `<option value="${o.id}">${o.nombre_completo}</option>`));
    }
    if (maquinas.success) {
        const s = document.getElementById('fprod_maquina');
        maquinas.maquinas.forEach(m => s.insertAdjacentHTML('beforeend', `<option value="${m.id}">${m.nombre}</option>`));
    }
}

async function cargarSelectsModal(seleccion = {}) {
    const [ordenes, operario, maquinas] = await Promise.all([
        llamarProduccion('BUSCARORDENES'),
        llamarProduccion('BUSCAROPERARIOS'),
        llamarProduccion('BUSCARMAQUINAS'),
    ]);

    const ordenSelect = document.getElementById('prod_orden_id');
    ordenSelect.innerHTML = '<option value="">Selecciona una orden...</option>';
    if (ordenes.success) {
        ordenes.ordenes.forEach(o => {
            const faltante = (o.cantidad_objetivo ?? 0) - (o.cantidad_avanzada ?? 0);
            ordenSelect.insertAdjacentHTML('beforeend', `<option value="${o.id}"
                    data-objetivo="${o.cantidad_objetivo}" data-avanzado="${o.cantidad_avanzada}">
                    ${o.codigo} - ${o.producto_nombre ?? 'Sin producto'} (${o.cantidad_avanzada}/${o.cantidad_objetivo}, falta ${faltante})
                </option>`);
        });
    }
    if (seleccion.orden_id) ordenSelect.value = seleccion.orden_id;
    actualizarInfoOrden();

    const operarioSelect = document.getElementById('prod_operario_id');
    operarioSelect.innerHTML = '<option value="">Selecciona...</option>';
    if (operario.success) operario.operario.forEach(o =>
        operarioSelect.insertAdjacentHTML('beforeend', `<option value="${o.id}">${o.nombre_completo}${o.cargo ? ' - ' + o.cargo : ''}</option>`));
    if (seleccion.operario_id) operarioSelect.value = seleccion.operario_id;

    const maquinaSelect = document.getElementById('prod_maquina_id');
    maquinaSelect.innerHTML = '<option value="">Selecciona...</option>';
    if (maquinas.success) maquinas.maquinas.forEach(m =>
        maquinaSelect.insertAdjacentHTML('beforeend', `<option value="${m.id}">${m.nombre}</option>`));
    if (seleccion.maquina_id) maquinaSelect.value = seleccion.maquina_id;
}

function actualizarInfoOrden() {
    const sel = document.getElementById('prod_orden_id');
    const opt = sel.selectedOptions[0];
    const info = document.getElementById('prod_orden_info');
    if (!opt || !opt.value) { info.textContent = ''; return; }
    const objetivo = parseFloat(opt.dataset.objetivo || 0);
    const avanzado = parseFloat(opt.dataset.avanzado || 0);
    info.textContent = `Avanzado: ${avanzado} de ${objetivo} (falta ${Math.max(objetivo - avanzado, 0)}).`;
}
document.getElementById('prod_orden_id').addEventListener('change', actualizarInfoOrden);

async function obtenerOpcionesMaterialesProd() {
    if (materialesProdCache) return materialesProdCache;
    const json = await llamarProduccion('BUSCARMATERIALESPRODUCCION', {});
    materialesProdCache = json.success ? json.materiales : [];
    return materialesProdCache;
}

// ── Listado ──────────────────────────────────────────────────────────────────
async function cargarProducciones() {
    const params = {
        texto: document.getElementById('fprod_texto').value.trim(),
        operario_id: document.getElementById('fprod_operario').value,
        maquina_id: document.getElementById('fprod_maquina').value,
        estado: document.getElementById('fprod_estado').value,
        emergencia: document.getElementById('fprod_emergencia').value,
        fecha_desde: document.getElementById('fprod_desde').value,
        fecha_hasta: document.getElementById('fprod_hasta').value,
    };

    const json = await llamarProduccion('LISTARPRODUCCIONES', params);
    const tbody = document.getElementById('tbodyProducciones');

    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="10" style="text-align:center;">${json.message}</td></tr>`;
        return;
    }

    const producciones = json.producciones || [];
    if (producciones.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;">No hay registros de producción.</td></tr>';
        return;
    }

    tbody.innerHTML = producciones.map(p => {
        const ordenTexto = p.orden_codigo
            ? `${p.orden_codigo}${p.es_emergencia ? ' <span class="badge bg-warning text-dark" title="Avance de emergencia">⚡</span>' : ''}`
            : '<span class="text-muted fst-italic">⚡ Sin orden (emergencia)</span>';
        return `
        <tr id="fila-produccion-${p.id}">
            <td data-label="#">${p.id}</td>
            <td data-label="Orden">${ordenTexto}</td>
            <td data-label="Producto">${p.producto_nombre ?? '-'}</td>
            <td data-label="Operario">${p.operario_nombre ?? '-'}</td>
            <td data-label="Máquina">${p.maquina_nombre ?? '-'}</td>
            <td data-label="Fecha">${p.fecha}</td>
            <td data-label="Cantidad">${formatearCantidadProd(p.cantidad)}</td>
            <td data-label="Materiales">${p.items_count}</td>
            <td data-label="Estado">${badgeRegistroProd(p.deleted_at)}</td>
            <td data-label="Acciones" class="pc-td-acciones">
                <button class="pc-icon-btn" onclick="abrirModalEditarProduccion(${p.id})" title="Editar">
                    <i class="fa-solid fa-pen"></i>
                </button>
                ${!p.deleted_at
                    ? `<button class="pc-icon-btn" onclick="eliminarProduccion(${p.id})" title="Desactivar">
                           <i class="fa-solid fa-trash"></i></button>`
                    : `<button class="pc-icon-btn" onclick="reactivarProduccion(${p.id})" title="Reactivar">
                           <i class="fa-solid fa-rotate-left"></i></button>`
                }
            </td>
        </tr>`;
    }).join('');
}

// =============================================================================
// MENÚ DE MATERIALES + TICKET
// =============================================================================

async function renderGridMateriales() {
    const grid = document.getElementById('prod_materiales_grid');
    const materiales = await obtenerOpcionesMaterialesProd();
    const filtro = document.getElementById('prod_mat_buscar').value.trim().toLowerCase();

    const visibles = filtro
        ? materiales.filter(m => m.nombre.toLowerCase().includes(filtro))
        : materiales;

    if (visibles.length === 0) {
        grid.innerHTML = '<div class="pc-mat-empty">No se encontró ningún material con ese nombre.</div>';
        return;
    }

    grid.innerHTML = visibles.map(m => {
        const est = estiloMaterial(m.nombre);
        const enTicket = ticketLineas.filter(l => l.material_id == m.id)
            .reduce((s, l) => s + Number(l.cantidad || 0), 0);
        const activa = materialSeleccionadoId == m.id;
        return `
        <button type="button" class="pc-mat-card ${activa ? 'activa' : ''}"
                style="--card-color:${est.color};--card-bg:${est.bg};"
                data-material-id="${m.id}" onclick="seleccionarMaterial(${m.id})">
            ${enTicket > 0 ? `<span class="badge-en-ticket">${formatearCantidadProd(enTicket)}</span>` : ''}
            <span class="pellet"><i class="fa-solid ${est.icono}"></i></span>
            <span class="nombre">${m.nombre}</span>
            <span class="stock">stock: <b>${formatearCantidadProd(m.stock_actual)}</b> ${m.unidad_corto ?? ''}</span>
        </button>`;
    }).join('');
}

async function seleccionarMaterial(materialId) {
    materialSeleccionadoId = materialId;
    await renderGridMateriales(); // refresca el resaltado de la card activa

    const materiales = await obtenerOpcionesMaterialesProd();
    const material = materiales.find(m => m.id == materialId);
    const panel = document.getElementById('prod_lote_panel');
    const strip = document.getElementById('prod_lote_strip');
    const titulo = document.getElementById('prod_lote_panel_titulo');

    titulo.textContent = `Elige el lote de origen — ${material ? material.nombre : ''}`;
    panel.style.display = 'block';
    strip.innerHTML = '<div class="pc-lote-vacio"><i class="fa-solid fa-spinner fa-spin"></i> Buscando lotes disponibles...</div>';

    const json = await llamarProduccion('BUSCARLOTESMATERIAL', { material_id: materialId });
    const lotes = json.success ? json.lotes : [];

    if (lotes.length === 0) {
        strip.innerHTML = '<div class="pc-lote-vacio"><i class="fa-solid fa-triangle-exclamation"></i> Este material no tiene lotes con stock disponible.</div>';
        return;
    }

    strip.innerHTML = lotes.map(l => {
        const pct = l.cantidad_base > 0 ? Math.max(0, Math.min(100, (l.disponible / l.cantidad_base) * 100)) : 0;
        return `
        <div class="pc-lote-chip" onclick='agregarLineaTicket(${JSON.stringify({
            material_id: material.id,
            material_nombre: material.nombre,
            unidad_corto: material.unidad_corto,
            lote_id: l.lote_id,
            proveedor: l.proveedor,
            fecha_compra: l.fecha_compra,
            disponible: l.disponible,
        })})'>
            <span class="prov">${l.proveedor}</span>
            <span class="fecha">${l.fecha_compra}</span>
            <div class="pc-lote-gauge"><span style="width:${pct}%"></span></div>
            <span class="disp">disponible: ${formatearCantidadProd(l.disponible)} ${l.unidad_base_corto ?? ''}</span>
        </div>`;
    }).join('');
}

// Agrega una línea al ticket. Si ya existe una línea con el mismo lote,
// suma 1 a su cantidad en vez de duplicar la línea (como pedir "uno más"
// del mismo producto en una comanda).
function agregarLineaTicket(datosLote) {
    const existente = ticketLineas.find(l => l.lote_id == datosLote.lote_id);
    if (existente) {
        cambiarCantidadTicket(existente.tempId, 1);
        return;
    }

    const est = estiloMaterial(datosLote.material_nombre);
    ticketLineas.push({
        tempId: ++contadorLineaTicket,
        material_id: datosLote.material_id,
        material_nombre: datosLote.material_nombre,
        unidad_corto: datosLote.unidad_corto,
        color: est.color,
        bg: est.bg,
        icono: est.icono,
        lote_id: datosLote.lote_id,
        proveedor: datosLote.proveedor,
        fecha_compra: datosLote.fecha_compra,
        disponible: parseFloat(datosLote.disponible),
        cantidad: 1,
        comentario: '',
    });
    renderTicket();
    renderGridMateriales();
}

function cambiarCantidadTicket(tempId, delta) {
    const linea = ticketLineas.find(l => l.tempId === tempId);
    if (!linea) return;
    const nueva = Math.round((linea.cantidad + delta) * 10000) / 10000;
    if (nueva < 0.0001) return;
    if (nueva > linea.disponible + 0.0001) return; // no se puede pedir más de lo disponible
    linea.cantidad = nueva;
    renderTicket();
    renderGridMateriales();
}

function fijarCantidadTicket(tempId, valor) {
    const linea = ticketLineas.find(l => l.tempId === tempId);
    if (!linea) return;
    let n = parseFloat(valor);
    if (isNaN(n) || n < 0.0001) n = 0.0001;
    if (n > linea.disponible) n = linea.disponible;
    linea.cantidad = Math.round(n * 10000) / 10000;
    renderTicket();
    renderGridMateriales();
}

function fijarComentarioTicket(tempId, valor) {
    const linea = ticketLineas.find(l => l.tempId === tempId);
    if (linea) linea.comentario = valor;
}

function quitarLineaTicket(tempId) {
    ticketLineas = ticketLineas.filter(l => l.tempId !== tempId);
    renderTicket();
    renderGridMateriales();
}

function renderTicket() {
    const list = document.getElementById('prod_ticket_list');
    const footer = document.getElementById('prod_ticket_footer');

    if (ticketLineas.length === 0) {
        list.innerHTML = `<li class="pc-tk-empty"><i class="fa-solid fa-basket-shopping"></i>Aún no agregas materiales.<br>Toca una card de la izquierda para empezar.</li>`;
        footer.style.display = 'none';
        return;
    }

    list.innerHTML = ticketLineas.map(l => `
        <li class="pc-tk-item">
            <span class="pellet-sm" style="--card-color:${l.color};--card-bg:${l.bg};"><i class="fa-solid ${l.icono}"></i></span>
            <div class="cuerpo">
                <span class="nombre">${l.material_nombre}</span>
                <div class="lote-info"><b>${l.proveedor}</b> · ${l.fecha_compra} · disp: ${formatearCantidadProd(l.disponible)} ${l.unidad_corto ?? ''}</div>
                <input type="text" class="comentario" placeholder="Comentario opcional"
                       value="${l.comentario ?? ''}"
                       onchange="fijarComentarioTicket(${l.tempId}, this.value)">
            </div>
            <div class="pc-tk-qty">
                <button type="button" onclick="cambiarCantidadTicket(${l.tempId}, -1)"><i class="fa-solid fa-minus"></i></button>
                <input type="number" step="0.0001" min="0.0001" value="${l.cantidad}"
                       onchange="fijarCantidadTicket(${l.tempId}, this.value)">
                <button type="button" onclick="cambiarCantidadTicket(${l.tempId}, 1)"
                        ${l.cantidad + 1 > l.disponible + 0.0001 ? 'disabled' : ''}><i class="fa-solid fa-plus"></i></button>
            </div>
            <button type="button" class="pc-tk-remove" onclick="quitarLineaTicket(${l.tempId})" title="Quitar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </li>
    `).join('');

    footer.style.display = 'block';
    footer.textContent = `${ticketLineas.length} material(es) en este avance.`;
}

function obtenerDetalleJsonProd() {
    return JSON.stringify(ticketLineas.map(l => ({
        material_id: l.material_id,
        rel_compra_material_id: l.lote_id,
        cantidad: l.cantidad,
        comentario: l.comentario,
    })));
}

// ── Crear / Editar ───────────────────────────────────────────────────────────
function limpiarFormularioProduccion() {
    document.getElementById('formProduccion').reset();
    document.getElementById('prod_orden_info').textContent = '';
    document.getElementById('prod_mat_buscar').value = '';
    document.getElementById('prod_lote_panel').style.display = 'none';
    produccionIdActual = 0;
    materialSeleccionadoId = null;
    ticketLineas = [];
    aplicarEstadoEmergencia();
    renderTicket();
}

async function abrirModalCrearProduccion() {
    limpiarFormularioProduccion();
    modoEdicionProduccion = false;
    document.getElementById('modalProduccionTitulo').textContent = 'Registrar avance de producción';
    await cargarSelectsModal();
    // Fecha/hora actual por defecto
    const ahora = new Date();
    ahora.setMinutes(ahora.getMinutes() - ahora.getTimezoneOffset());
    document.getElementById('prod_fecha').value = ahora.toISOString().substring(0, 16);
    await renderGridMateriales();
    modalProduccion.show();
}

async function abrirModalEditarProduccion(id) {
    const json = await llamarProduccion('OBTENERPRODUCCION', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    limpiarFormularioProduccion();
    modoEdicionProduccion = true;
    produccionIdActual = id;

    const p = json.produccion;
    document.getElementById('modalProduccionTitulo').textContent = 'Editar avance #' + id;
    document.getElementById('prod_cantidad').value = p.cantidad;
    document.getElementById('prod_fecha').value = formatearFechaHoraLocal(p.fecha);
    document.getElementById('prod_observaciones').value = p.observaciones ?? '';
    document.getElementById('prod_emergencia').checked = !!p.es_emergencia;
    aplicarEstadoEmergencia();

    await cargarSelectsModal({
        orden_id: p.orden_id, operario_id: p.operario_id, maquina_id: p.maquina_id
    });
    await renderGridMateriales();

    const detalle = json.detalle || [];
    ticketLineas = detalle.map(d => {
        const est = estiloMaterial(d.material_nombre);
        return {
            tempId: ++contadorLineaTicket,
            material_id: d.material_id,
            material_nombre: d.material_nombre,
            unidad_corto: d.unidad_base_corto,
            color: est.color,
            bg: est.bg,
            icono: est.icono,
            lote_id: d.rel_compra_material_id,
            proveedor: d.proveedor,
            fecha_compra: d.fecha_compra,
            // el disponible "visible" del lote no incluye esta misma línea,
            // así que se lo sumamos de vuelta para no bloquear los steppers.
            disponible: parseFloat(d.lote_cantidad_base ?? d.cantidad),
            cantidad: parseFloat(d.cantidad),
            comentario: d.comentario ?? '',
        };
    });
    renderTicket();
    renderGridMateriales();

    modalProduccion.show();
}

document.getElementById('formProduccion').addEventListener('submit', async function (e) {
    e.preventDefault();

    const esEmergencia = document.getElementById('prod_emergencia').checked;
    if (esEmergencia && !document.getElementById('prod_observaciones').value.trim()) {
        Swal.fire('Falta el motivo', 'Cuéntanos brevemente el motivo de la emergencia en observaciones.', 'warning');
        return;
    }

    const params = {
        id: produccionIdActual,
        orden_id: document.getElementById('prod_orden_id').value,
        operario_id: document.getElementById('prod_operario_id').value,
        maquina_id: document.getElementById('prod_maquina_id').value,
        cantidad: document.getElementById('prod_cantidad').value,
        fecha: document.getElementById('prod_fecha').value.replace('T', ' '),
        observaciones: document.getElementById('prod_observaciones').value.trim(),
        es_emergencia: esEmergencia ? '1' : '0',
        detalle: obtenerDetalleJsonProd(),
    };

    const json = await llamarProduccion('GUARDARPRODUCCION', params);

    if (json.success) {
        modalProduccion.hide();
        Swal.fire('Listo', json.message, 'success');
        cargarProducciones();
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});

// ── Eliminar / Reactivar ─────────────────────────────────────────────────────
function eliminarProduccion(id) {
    Swal.fire({
        title: '¿Desactivar este avance de producción?',
        text: 'El material consumido se devolverá al stock. Podrás reactivarlo luego.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarProduccion('ELIMINARPRODUCCION', { id });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarProducciones();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

function reactivarProduccion(id) {
    llamarProduccion('REACTIVARPRODUCCION', { id }).then(json => {
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarProducciones();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>