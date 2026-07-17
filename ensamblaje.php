<?php
$pageTitle    = 'Ensamblaje';
$pageSubtitle = 'Armado de productos finales';
$activePage = 'ensamblaje';

include("header.php");
?>

<!--
    ensamblaje.php

    SUPUESTO (sin confirmar, igual que en clssEnsamblaje.php): no tengo la
    definición real de view_ensamblaje_detalle. Asumo estas columnas,
    siguiendo el mismo patrón de nombres que ya usa listarEnsamblajes() en
    su WHERE:
        ensamblaje_id, producto_id, producto_codigo, producto_descripcion,
        operario_id, operario_nombre, inicio, fin, cantidad_peso_kg,
        deleted_at, js_moldes_utilizados, js_derivados_utilizados
    Si los nombres reales difieren, ajustar solo las funciones
    renderGridEnsamblajes() y el prellenado en abrirModalEditarEnsamblaje().

    MODELO DE UI: mismo patrón "menú de cards + ticket" que produccion.php,
    pero simplificado: cada línea del ticket es una producción finalizada
    (view_producciones_disponibles_ensamblaje) o un derivado, sin cantidad
    por línea -> el peso total del armado va aparte en cantidad_peso_kg.

    INTEGRACIÓN CON "Pasar a ensamblaje" (botón en produccion.php): si esta
    página se abre con ?produccion_id=123, se abre el modal de creación
    y se precarga esa producción como primera línea del ticket, vía
    OBTENERDATOSPRODUCCIONPARAENSAMBLAJE.
-->

<style>
:root{
    --resina-1:#2F6FED; --resina-1-bg:#EAF0FE;
    --resina-2:#E23744; --resina-2-bg:#FCEAEC;
    --resina-3:#16A34A; --resina-3-bg:#E8F7EE;
    --resina-4:#D97706; --resina-4-bg:#FDF1E0;
    --resina-5:#7C3AED; --resina-5-bg:#F1EAFD;
    --resina-6:#0E9488; --resina-6-bg:#E2F5F3;
}

/* ── Listado de ensamblajes en cards (mismo patrón que produccion.php) ── */
.pc-ens-grid{
    display:grid; grid-template-columns:repeat(auto-fill, minmax(300px,1fr));
    gap:14px; margin-top:4px;
}
.pc-ens-card{
    border:1px solid #e7e4dd; border-radius:14px; background:#fff;
    overflow:hidden; display:flex; flex-direction:column;
    transition:box-shadow .12s ease, transform .12s ease;
}
.pc-ens-card:hover{ box-shadow:0 6px 16px rgba(0,0,0,.08); transform:translateY(-1px); }
.pc-ens-card.inactiva{ opacity:.6; }
.pc-ens-card-head{
    padding:10px 14px; background:#fdfcfa; border-bottom:1px solid #eee7db;
    display:flex; justify-content:space-between; align-items:flex-start; gap:8px;
}
.pc-ens-card-head .titulo{ display:flex; flex-direction:column; gap:2px; min-width:0; }
.pc-ens-card-head .id{ font-size:.72em; color:#9a9585; font-weight:600; }
.pc-ens-card-head .producto-titulo{ font-weight:700; font-size:.95em; }
.pc-ens-card-body{ padding:12px 14px; display:grid; grid-template-columns:1fr 1fr; gap:8px 12px; flex:1; }
.pc-ens-field{ min-width:0; }
.pc-ens-field .lbl{ font-size:.68em; text-transform:uppercase; letter-spacing:.03em; color:#9a9585; display:block; margin-bottom:1px; }
.pc-ens-field .val{ font-size:.85em; color:#3a3730; font-weight:600; overflow-wrap:break-word; }
.pc-ens-field.span-2{ grid-column:1/-1; }
.pc-ens-card-foot{
    padding:8px 14px; border-top:1px solid #eee7db; background:#fffefb;
    display:flex; justify-content:flex-end; align-items:center; gap:6px; flex-wrap:wrap;
}
.pc-ens-empty{ text-align:center; color:#9a9585; padding:40px 12px; grid-column:1/-1; }

.pc-corrida-sin{ color:#9a9585; font-size:.85em; }
.pc-corrida-curso{ font-size:.8em; }
.pc-corrida-curso small{ display:block; color:#8a8578; margin-top:2px; }
.pc-corrida-fin{ font-size:.78em; color:#5c5947; line-height:1.3; }

.pc-btn-iniciar{
    padding:7px 12px; font-size:.8em; border-radius:8px; border:1px solid #16A34A;
    background:#E8F7EE; color:#16A34A; font-weight:700; display:inline-flex; align-items:center; gap:6px;
    transition:.12s ease;
}
.pc-btn-iniciar:hover{ background:#16A34A; color:#fff; }
.pc-btn-finalizar{
    padding:7px 12px; font-size:.8em; border-radius:8px; border:1px solid #D97706;
    background:#FDF1E0; color:#D97706; font-weight:700; display:inline-flex; align-items:center; gap:6px;
    transition:.12s ease;
}
.pc-btn-finalizar:hover{ background:#D97706; color:#fff; }

/* ── Panel de detalle: menú de tabs (producciones/derivados) + ticket ── */
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

.pc-tabs-detalle{ display:flex; gap:6px; padding:10px 12px 0 12px; }
.pc-tab-detalle{
    flex:1; text-align:center; padding:7px 10px; font-size:.82em; font-weight:700;
    border:1px solid #e2ddcd; border-radius:8px; background:#fff; color:#8a8578; cursor:pointer;
    transition:.12s ease;
}
.pc-tab-detalle.activa{ background:#2F6FED; border-color:#2F6FED; color:#fff; }

.pc-mat-search{ padding:10px 12px 0 12px; }

.pc-mat-grid{
    display:grid; grid-template-columns:repeat(auto-fill, minmax(150px,1fr));
    gap:10px; padding:12px; max-height:360px; overflow-y:auto;
}
.pc-mat-card{
    position:relative; border:1px solid #eae6da; border-radius:12px; background:#fff;
    padding:10px 10px 8px 10px; cursor:pointer; text-align:left;
    transition:transform .12s ease, box-shadow .12s ease, border-color .12s ease;
}
.pc-mat-card:hover{ transform:translateY(-2px); box-shadow:0 6px 14px rgba(0,0,0,.07); }
.pc-mat-card:disabled, .pc-mat-card.ya-agregada{ opacity:.4; cursor:not-allowed; }
.pc-mat-card .pellet{
    width:30px; height:30px; border-radius:9px; display:flex; align-items:center; justify-content:center;
    background:var(--card-bg,#EAF0FE); color:var(--card-color,#2F6FED); font-size:.95em; margin-bottom:8px;
}
.pc-mat-card .nombre{ font-weight:600; font-size:.85em; line-height:1.2; display:block; min-height:2.2em; }
.pc-mat-card .meta{ font-size:.72em; color:#8a8578; margin-top:4px; display:block; }
.pc-mat-card .meta b{ color:#4a4636; }
.pc-mat-empty{ grid-column:1/-1; text-align:center; color:#9a9585; font-size:.85em; padding:20px 6px; }

.pc-tk-list{ list-style:none; margin:0; padding:0; max-height:340px; overflow-y:auto; }
.pc-tk-item{ border-bottom:1px dashed #eee2c8; padding:10px 12px; display:flex; gap:10px; align-items:flex-start; }
.pc-tk-item:last-child{ border-bottom:none; }
.pc-tk-item .pellet-sm{
    width:26px; height:26px; border-radius:8px; flex:0 0 auto; display:flex; align-items:center; justify-content:center;
    background:var(--card-bg,#EAF0FE); color:var(--card-color,#2F6FED); font-size:.8em; margin-top:2px;
}
.pc-tk-item .cuerpo{ flex:1; min-width:0; }
.pc-tk-item .nombre{ font-weight:600; font-size:.85em; }
.pc-tk-item .lote-info{ font-size:.72em; color:#8a8578; margin-top:1px; }
.pc-tk-item .lote-info b{ color:#5c5947; }
.pc-tk-remove{ border:none; background:none; color:#c94a4a; font-size:.85em; align-self:flex-start; }
.pc-tk-empty{ text-align:center; color:#9a9585; font-size:.85em; padding:26px 12px; }
.pc-tk-empty i{ font-size:1.6em; display:block; margin-bottom:6px; opacity:.5; }

.pc-tk-resumen{
    display:flex; align-items:center; gap:12px;
    padding:12px 14px; border-top:1px solid #eee7db;
    background:linear-gradient(0deg,#fffaf0,#fffefb);
}
.pc-tk-resumen-icon{
    width:36px; height:36px; border-radius:10px; flex:0 0 auto;
    background:var(--pc-blue-light,#EAF0FE); color:var(--pc-blue,#2F6FED);
    display:flex; align-items:center; justify-content:center; font-size:1em;
}
.pc-tk-resumen-texto{ display:flex; flex-direction:column; gap:1px; min-width:0; }
.pc-tk-resumen-texto .total{ font-size:.95em; color:#3a3730; }
.pc-tk-resumen-texto .total b{ font-size:1.15em; color:var(--pc-blue,#2F6FED); }
.pc-tk-resumen-texto .detalle{ font-size:.75em; color:#8a8578; }
</style>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Ensamblaje</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrearEnsamblaje()">
            <i class="fa-solid fa-plus"></i> Registrar ensamblaje
        </button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <br>
        <input type="text" id="fens_texto" class="form-control" style="max-width:260px"
               placeholder="Buscar por producto...">
        <select id="fens_producto" class="form-select" style="max-width:220px">
            <option value="">Todos los productos</option>
        </select>
        <select id="fens_operario" class="form-select" style="max-width:200px">
            <option value="">Todos los operarios</option>
        </select>
        <input type="date" id="fens_desde" class="form-control" style="max-width:160px" title="Desde">
        <input type="date" id="fens_hasta" class="form-control" style="max-width:160px" title="Hasta">
        <select id="fens_estado" class="form-select" style="max-width:160px">
            <option value="">Todos</option>
            <option value="activa" selected>Activos</option>
            <option value="inactiva">Inactivos</option>
        </select>
    </div>

    <div class="pc-ens-grid" id="gridEnsamblajes">
        <div class="pc-ens-empty">Cargando...</div>
    </div>
</div>

<!-- Modal Crear/Editar -->
<div class="modal fade" id="modalEnsamblaje" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <form id="formEnsamblaje">
        <div class="modal-header">
          <h5 class="modal-title" id="modalEnsamblajeTitulo">Registrar ensamblaje</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">

        <div class="row">
            <div class="col-md-7 mb-2">
                <label class="form-label">Producto a ensamblar *</label>
                <select class="form-select" id="ens_producto_id" required onchange="cambioProductoEnsamblaje()">
                    <option value="">Selecciona un producto...</option>
                </select>
            </div>
            <div class="col-md-5 mb-2">
                <label class="form-label">Operario</label>
                <select class="form-select" id="ens_operario_id">
                    <option value="">Selecciona...</option>
                </select>
            </div>
        </div>

          <hr>

          <div class="mb-1 d-flex justify-content-between align-items-center">
            <label class="form-label mb-0">Vinculados a este armado *</label>
            <span class="form-text mb-0">Al menos una producción finalizada o un derivado.</span>
          </div>

          <div class="pc-mat-layout">
            <div class="pc-mat-panel">
                <div class="pc-mat-panel-head">
                    <h6><i class="fa-solid fa-diagram-project"></i> Elige qué vincular</h6>
                </div>
                <div class="pc-tabs-detalle">
                    <div class="pc-tab-detalle activa" id="tab_producciones" onclick="cambiarTabDetalle('produccion')">
                        <i class="fa-solid fa-industry"></i> Producciones
                    </div>
                    <div class="pc-tab-detalle" id="tab_derivados" onclick="cambiarTabDetalle('derivado')">
                        <i class="fa-solid fa-flask"></i> Derivados
                    </div>
                </div>
                <div class="pc-mat-search">
                    <input type="text" id="ens_buscar_detalle" class="form-control form-control-sm" placeholder="Buscar...">
                </div>
                <div class="pc-mat-grid" id="ens_detalle_grid">
                    <div class="pc-mat-empty">Selecciona un producto para ver producciones disponibles.</div>
                </div>
            </div>

            <div class="pc-tk-panel">
                <div class="pc-tk-panel-head">
                    <h6><i class="fa-solid fa-receipt"></i> Ticket de este armado</h6>
                </div>
                <ul class="pc-tk-list" id="ens_ticket_list">
                    <li class="pc-tk-empty"><i class="fa-solid fa-basket-shopping"></i>Aún no vinculas nada.<br>Toca una card de la izquierda para empezar.</li>
                </ul>
                <div class="pc-tk-resumen">
                    <div class="pc-tk-resumen-icon"><i class="fa-solid fa-layer-group"></i></div>
                    <div class="pc-tk-resumen-texto">
                        <span class="total"><b id="ens_ticket_total">0</b> ítem(s)</span>
                        <span class="detalle" id="ens_ticket_detalle">0 producción(es) · 0 derivado(s)</span>
                        <span class="detalle">Peso producido vinculado: <b id="ens_ticket_peso_producido">0</b> kg</span>
                    </div>
                </div>
                <div class="form-text" style="padding:0 14px 10px 14px;">
                    El peso real de salida de este armado se registrará más adelante, al pasar a empaquetado.
                </div>
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
const CONTROLADOR_ENSAMBLAJE = 'controllers/clssEnsamblaje.php';
const modalEnsamblaje = new bootstrap.Modal(document.getElementById('modalEnsamblaje'));

let modoEdicionEnsamblaje = false;
let ensamblajeIdActual = 0;
let productosEnsCache = null;   // cache de productos para selects/filtro
let tabDetalleActiva = 'produccion'; // 'produccion' | 'derivado'
let contadorLineaTicketEns = 0;
let ticketDetalleEns = []; // [{tempId, tipo, molde_produccion_id, derivado_id, nombre, meta, color, bg, icono}]
let productosDisponiblesEnsCache = null; // cache para el select del modal (producto+color pendientes)

document.addEventListener('DOMContentLoaded', () => {
    inicializarPagina();
});

async function inicializarPagina() {
    await cargarSelectsFiltroEns();
    await cargarEnsamblajes().catch(err => {
        console.error('Error cargando datos iniciales:', err);
        document.getElementById('gridEnsamblajes').innerHTML =
            `<div class="pc-ens-empty" style="color:red;">Error de conexión con el servidor. Revisa la consola (F12).</div>`;
    });

    let debounceTimer = null;
    document.getElementById('fens_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarEnsamblajes, 350);
    });
    ['fens_producto', 'fens_operario', 'fens_estado', 'fens_desde', 'fens_hasta'].forEach(id => {
        document.getElementById(id).addEventListener('change', cargarEnsamblajes);
    });

    let debounceDetalle = null;
    document.getElementById('ens_buscar_detalle').addEventListener('input', () => {
        clearTimeout(debounceDetalle);
        debounceDetalle = setTimeout(renderGridDetalle, 300);
    });

    // Integración con "Pasar a ensamblaje" desde produccion.php:
    // ensamblaje.php?produccion_id=123
    const params = new URLSearchParams(window.location.search);
    const produccionId = parseInt(params.get('produccion_id') || '0', 10);
    const cantidadProducida = parseFloat(params.get('cantidad_producida') || '');
    if (produccionId > 0) {
        await abrirModalCrearEnsamblajeDesdeProduccion(produccionId, cantidadProducida);
    }
}

// ── Llamada genérica al controlador ─────────────────────────────────────────
async function llamarEnsamblaje(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_ENSAMBLAJE, {
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

function badgeRegistroEns(deletedAt) {
    return !deletedAt
        ? '<span class="badge bg-success">Activo</span>'
        : '<span class="badge bg-secondary">Inactivo</span>';
}

function formatearCantidadEns(n) {
    if (n === null || n === undefined || n === '') return '-';
    return Number(n).toLocaleString('es-PE', { maximumFractionDigits: 4 });
}

function formatearFechaHoraLocalEns(fechaIso) {
    if (!fechaIso) return '';
    return fechaIso.replace(' ', 'T').substring(0, 16);
}

function formatearFechaHoraLegibleEns(fechaIso) {
    if (!fechaIso) return '';
    const [fecha, hora] = fechaIso.split(' ');
    if (!fecha) return fechaIso;
    const [y, m, d] = fecha.split('-');
    return `${d}/${m}/${y}${hora ? ' ' + hora.substring(0, 5) : ''}`;
}

function estadoArmadoTexto(e) {
    if (!e.inicio) {
        return '<span class="pc-corrida-sin">Sin iniciar</span>';
    }
    if (!e.fin) {
        return `<span class="pc-corrida-curso"><span class="badge bg-info text-dark">En curso</span>
                <small>Inicio: ${formatearFechaHoraLegibleEns(e.inicio)}</small></span>`;
    }
    return `<span class="pc-corrida-fin">
                Inicio: ${formatearFechaHoraLegibleEns(e.inicio)}<br>
                Fin: ${formatearFechaHoraLegibleEns(e.fin)}
            </span>`;
}

// Estética estable por nombre (mismo hash simple usado en produccion.php)
const PALETA_RESINA = [
    { color: '#2F6FED', bg: '#EAF0FE' },
    { color: '#E23744', bg: '#FCEAEC' },
    { color: '#16A34A', bg: '#E8F7EE' },
    { color: '#D97706', bg: '#FDF1E0' },
    { color: '#7C3AED', bg: '#F1EAFD' },
    { color: '#0E9488', bg: '#E2F5F3' },
];
function estiloPorNombre(nombre) {
    let hash = 0;
    const str = nombre || '';
    for (let i = 0; i < str.length; i++) hash = (hash * 31 + str.charCodeAt(i)) >>> 0;
    return PALETA_RESINA[hash % PALETA_RESINA.length];
}

// Convierte columnas jsonb (pueden llegar como string o ya decodificadas
// según el driver) a array de JS de forma segura.
function parseJsonColumna(v) {
    if (!v) return [];
    if (typeof v === 'string') {
        try { return JSON.parse(v) || []; } catch (e) { return []; }
    }
    return Array.isArray(v) ? v : [];
}

// ── Selects auxiliares ────────────────────────────────────────────────────
async function obtenerProductosEns() {
    if (productosEnsCache && productosEnsCache.length > 0) return productosEnsCache;
    const json = await llamarEnsamblaje('BUSCARPRODUCTOS', { texto: '' });
    productosEnsCache = json.success ? json.productos : [];
    return productosEnsCache;
}

async function cargarSelectsFiltroEns() {
    const [productos, operario] = await Promise.all([
        obtenerProductosEns(),
        llamarEnsamblaje('BUSCAROPERARIOS'),
    ]);
    const sProd = document.getElementById('fens_producto');
    productos.forEach(p => sProd.insertAdjacentHTML('beforeend',
        `<option value="${p.id}">${p.codigo} - ${p.descripcion}</option>`));

    if (operario.success) {
        const sOp = document.getElementById('fens_operario');
        operario.operario.forEach(o => sOp.insertAdjacentHTML('beforeend',
            `<option value="${o.id}">${o.nombre_completo}</option>`));
    }
}
async function obtenerProductosDisponiblesEns() {
    if (productosDisponiblesEnsCache && productosDisponiblesEnsCache.length > 0) return productosDisponiblesEnsCache;
    const json = await llamarEnsamblaje('BUSCARPRODUCTOSDISPONIBLESENSAMBLAJE', { texto: '' });
    productosDisponiblesEnsCache = json.success ? json.productos : [];
    return productosDisponiblesEnsCache;
}
async function cargarSelectsModalEns(seleccion = {}) {
    const [productos, operario] = await Promise.all([
        obtenerProductosDisponiblesEns(),
        llamarEnsamblaje('BUSCAROPERARIOS'),
    ]);

    const sProd = document.getElementById('ens_producto_id');
    sProd.innerHTML = '<option value="">Selecciona un producto...</option>' +
        productos.map(p => `<option value="${p.producto_id}_${p.color_id}"
                data-producto-id="${p.producto_id}"
                data-color-id="${p.color_id}">${p.productoformato} — ${p.disponibles} disponible(s)</option>`).join('');
    if (seleccion.producto_id) {
        const valorBuscado = `${seleccion.producto_id}_${seleccion.color_id ?? ''}`;
        const coincide = Array.from(sProd.options).find(o => o.value === valorBuscado);
        if (coincide) sProd.value = valorBuscado;
    }

    const sOp = document.getElementById('ens_operario_id');
    sOp.innerHTML = '<option value="">Selecciona...</option>';
    if (operario.success) operario.operario.forEach(o =>
        sOp.insertAdjacentHTML('beforeend', `<option value="${o.id}">${o.nombre_completo}${o.cargo ? ' - ' + o.cargo : ''}</option>`));
    if (seleccion.operario_id) sOp.value = seleccion.operario_id;
}
// ── Listado en CARDS ──────────────────────────────────────────────────────
async function cargarEnsamblajes() {
    const params = {
        texto: document.getElementById('fens_texto').value.trim(),
        producto_id: document.getElementById('fens_producto').value,
        operario_id: document.getElementById('fens_operario').value,
        estado: document.getElementById('fens_estado').value,
        fecha_desde: document.getElementById('fens_desde').value,
        fecha_hasta: document.getElementById('fens_hasta').value,
    };

    const json = await llamarEnsamblaje('LISTARENSAMBLAJES', params);
    const grid = document.getElementById('gridEnsamblajes');

    if (!json.success) {
        grid.innerHTML = `<div class="pc-ens-empty">${json.message}</div>`;
        return;
    }

    const ensamblajes = json.ensamblajes || [];
    if (ensamblajes.length === 0) {
        grid.innerHTML = '<div class="pc-ens-empty">No hay registros de ensamblaje.</div>';
        return;
    }

    grid.innerHTML = ensamblajes.map(e => {
        // producciones_count/derivados_count son columnas asumidas de la
        // vista (ver comentario superior). Si la vista no las trae, se
        // calculan igual a partir de los resúmenes reales que sí garantiza
        // el controlador (js_moldes_utilizados / js_derivados_utilizados).
        const producciones = e.producciones_count ?? parseJsonColumna(e.js_moldes_utilizados).length;
        const derivadosCount = e.derivados_count ?? parseJsonColumna(e.js_derivados_utilizados).length;
        const puedeIniciar   = !e.deleted_at && !e.inicio;
        const puedeFinalizar = !e.deleted_at && e.inicio && !e.fin;

        return `
        <div class="pc-ens-card ${e.deleted_at ? 'inactiva' : ''}" id="fila-ensamblaje-${e.ensamblaje_id}">
            <div class="pc-ens-card-head">
                <div class="titulo">
                    <span class="id">#${e.ensamblaje_id}</span>
                    <span class="producto-titulo">${e.producto_codigo ?? ''} - ${e.producto_descripcion ?? '-'}</span>
                </div>
                ${badgeRegistroEns(e.deleted_at)}
            </div>
            <div class="pc-ens-card-body">
                <div class="pc-ens-field">
                    <span class="lbl">Operario</span>
                    <span class="val">${e.operario_nombre ?? '-'}</span>
                </div>
                <div class="pc-ens-field">
                    <span class="lbl">Peso total</span>
                    <span class="val">${formatearCantidadEns(e.cantidad_peso_kg)} kg</span>
                </div>
                <div class="pc-ens-field">
                    <span class="lbl">Producciones vinculadas</span>
                    <span class="val">${producciones}</span>
                </div>
                <div class="pc-ens-field">
                    <span class="lbl">Derivados vinculados</span>
                    <span class="val">${derivadosCount}</span>
                </div>
                <div class="pc-ens-field span-2">
                    <span class="lbl">Armado</span>
                    <span class="val">${estadoArmadoTexto(e)}</span>
                </div>
            </div>
            <div class="pc-ens-card-foot">
                <button class="pc-icon-btn" onclick="abrirModalEditarEnsamblaje(${e.ensamblaje_id})" title="Editar">
                    <i class="fa-solid fa-pen"></i>
                </button>
                ${puedeIniciar
                    ? `<button type="button" class="pc-btn-iniciar" onclick="iniciarEnsamblajeAccion(${e.ensamblaje_id})" title="Iniciar armado">
                        <i class="fa-solid fa-play"></i> Iniciar</button>`
                    : ''
                }
                ${puedeFinalizar
                    ? `<button type="button" class="pc-btn-finalizar" onclick="finalizarEnsamblajeAccion(${e.ensamblaje_id})" title="Finalizar armado">
                        <i class="fa-solid fa-flag-checkered"></i> Finalizar</button>`
                    : ''
                }
                ${!e.deleted_at
                    ? `<button class="pc-icon-btn" onclick="eliminarEnsamblaje(${e.ensamblaje_id})" title="Desactivar">
                           <i class="fa-solid fa-trash"></i></button>`
                    : `<button class="pc-icon-btn" onclick="reactivarEnsamblaje(${e.ensamblaje_id})" title="Reactivar">
                           <i class="fa-solid fa-rotate-left"></i></button>`
                }
            </div>
        </div>`;
    }).join('');
}

// =============================================================================
// PANEL DE DETALLE: tabs (producciones / derivados) + ticket
// =============================================================================

function cambiarTabDetalle(tipo) {
    tabDetalleActiva = tipo;
    document.getElementById('tab_producciones').classList.toggle('activa', tipo === 'produccion');
    document.getElementById('tab_derivados').classList.toggle('activa', tipo === 'derivado');
    document.getElementById('ens_buscar_detalle').value = '';
    renderGridDetalle();
}

async function renderGridDetalle() {
    const grid = document.getElementById('ens_detalle_grid');
    const texto = document.getElementById('ens_buscar_detalle').value.trim();
    const sel = document.getElementById('ens_producto_id');
    const [productoId, colorId] = (sel.value || '').split('_');

    if (tabDetalleActiva === 'produccion') {
        if (!productoId) {
            grid.innerHTML = '<div class="pc-mat-empty">Selecciona un producto para ver sus producciones disponibles.</div>';
            return;
        }
        grid.innerHTML = '<div class="pc-mat-empty"><i class="fa-solid fa-spinner fa-spin"></i> Buscando...</div>';
        const json = await llamarEnsamblaje('BUSCARPRODUCCIONESDISPONIBLES', { producto_id: productoId, color_id: colorId, texto });
        const producciones = json.success ? (json.producciones || []) : [];

        if (producciones.length === 0) {
            grid.innerHTML = '<div class="pc-mat-empty">No hay producciones finalizadas y libres para este producto.</div>';
            return;
        }

        grid.innerHTML = producciones.map(p => {
            const colorNombre = p.color_nombre_verif ?? p.color_nombre ?? '';
            const est = estiloPorNombre(p.molde_nombre || 'producción');
            const yaAgregada = ticketDetalleEns.some(l => l.tipo === 'produccion' && l.molde_produccion_id == p.produccion_id);
            return `
            <button type="button" class="pc-mat-card ${yaAgregada ? 'ya-agregada' : ''}" ${yaAgregada ? 'disabled' : ''}
                    style="--card-color:${est.color};--card-bg:${est.bg};"
                    onclick='agregarLineaDetalle("produccion", ${JSON.stringify({
                        produccion_id: p.produccion_id,
                        molde_nombre: p.molde_nombre,
                        color_nombre: colorNombre,
                        cantidad_kg: p.cantidad_kg ?? p.cantidad,
                        fecha_hora_fin: p.fecha_hora_fin,
                    })})'>
                <span class="pellet"><i class="fa-solid fa-industry"></i></span>
                <span class="nombre">${p.molde_nombre ?? ('Producción #' + p.produccion_id)}</span>
                <span class="meta">#${p.produccion_id} · <b>${formatearCantidadEns(p.cantidad_kg ?? p.cantidad)}</b> kg</span>
                <span class="meta">Color: <b>${colorNombre || '-'}</b></span>
                <span class="meta">${formatearFechaHoraLegibleEns(p.fecha_hora_fin)}</span>
            </button>`;
        }).join('');
    } else {
        // Rama de derivados: sin cambios respecto a la versión anterior.
        grid.innerHTML = '<div class="pc-mat-empty"><i class="fa-solid fa-spinner fa-spin"></i> Buscando...</div>';
        const json = await llamarEnsamblaje('BUSCARDERIVADOS', { texto });
        const derivados = json.success ? (json.derivados || []) : [];

        if (derivados.length === 0) {
            grid.innerHTML = '<div class="pc-mat-empty">No se encontró ningún derivado con ese nombre.</div>';
            return;
        }

        grid.innerHTML = derivados.map(d => {
            const est = estiloPorNombre(d.nombre);
            const yaAgregada = ticketDetalleEns.some(l => l.tipo === 'derivado' && l.derivado_id == d.id);
            return `
            <button type="button" class="pc-mat-card ${yaAgregada ? 'ya-agregada' : ''}" ${yaAgregada ? 'disabled' : ''}
                    style="--card-color:${est.color};--card-bg:${est.bg};"
                    onclick='agregarLineaDetalle("derivado", ${JSON.stringify({
                        derivado_id: d.id,
                        nombre: d.nombre,
                    })})'>
                <span class="pellet"><i class="fa-solid fa-flask"></i></span>
                <span class="nombre">${d.nombre}</span>
                <span class="meta">Derivado #${d.id}</span>
            </button>`;
        }).join('');
    }
}

function agregarLineaDetalle(tipo, datos) {
    const est = estiloPorNombre(tipo === 'produccion' ? (datos.molde_nombre || '') : datos.nombre);
    if (tipo === 'produccion') {
        ticketDetalleEns.push({
            tempId: ++contadorLineaTicketEns,
            tipo: 'produccion',
            molde_produccion_id: datos.produccion_id,
            derivado_id: null,
            nombre: datos.molde_nombre ?? ('Producción #' + datos.produccion_id),
            meta: `#${datos.produccion_id} · Color: ${datos.color_nombre || '-'} · ${formatearCantidadEns(datos.cantidad_kg)} kg · ${formatearFechaHoraLegibleEns(datos.fecha_hora_fin)}`,
            icono: 'fa-industry',
            color: est.color, bg: est.bg,
            cantidad_kg: parseFloat(datos.cantidad_kg) || 0,
        });
    } else {
        ticketDetalleEns.push({
            tempId: ++contadorLineaTicketEns,
            tipo: 'derivado',
            molde_produccion_id: null,
            derivado_id: datos.derivado_id,
            nombre: datos.nombre,
            meta: `Derivado #${datos.derivado_id}`,
            icono: 'fa-flask',
            color: est.color, bg: est.bg,
        });
    }
    renderTicketDetalle();
    renderGridDetalle();
}

function quitarLineaDetalle(tempId) {
    ticketDetalleEns = ticketDetalleEns.filter(l => l.tempId !== tempId);
    renderTicketDetalle();
    renderGridDetalle();
}

function renderTicketDetalle() {
    const list = document.getElementById('ens_ticket_list');
    const total = document.getElementById('ens_ticket_total');
    const detalle = document.getElementById('ens_ticket_detalle');
    const pesoEl = document.getElementById('ens_ticket_peso_producido');

    if (ticketDetalleEns.length === 0) {
        list.innerHTML = `<li class="pc-tk-empty"><i class="fa-solid fa-basket-shopping"></i>Aún no vinculas nada.<br>Toca una card de la izquierda para empezar.</li>`;
    } else {
        list.innerHTML = ticketDetalleEns.map(l => `
            <li class="pc-tk-item">
                <span class="pellet-sm" style="--card-color:${l.color};--card-bg:${l.bg};"><i class="fa-solid ${l.icono}"></i></span>
                <div class="cuerpo">
                    <span class="nombre">${l.nombre}</span>
                    <div class="lote-info">${l.meta}</div>
                </div>
                <button type="button" class="pc-tk-remove" onclick="quitarLineaDetalle(${l.tempId})" title="Quitar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </li>
        `).join('');
    }

    const nProd = ticketDetalleEns.filter(l => l.tipo === 'produccion').length;
    const nDer  = ticketDetalleEns.filter(l => l.tipo === 'derivado').length;
    total.textContent = ticketDetalleEns.length;
    detalle.textContent = `${nProd} producción(es) · ${nDer} derivado(s)`;

    const pesoProducido = ticketDetalleEns
        .filter(l => l.tipo === 'produccion')
        .reduce((s, l) => s + Number(l.cantidad_kg || 0), 0);
    pesoEl.textContent = formatearCantidadEns(pesoProducido);
}

function obtenerDetalleJsonEns() {
    return JSON.stringify(ticketDetalleEns.map(l => ({
        tipo: l.tipo,
        molde_produccion_id: l.molde_produccion_id,
        derivado_id: l.derivado_id,
    })));
}

function cambioProductoEnsamblaje() {
    // Ya no autoagrega una producción: solo cambia el contexto producto+color
    // para que el panel de "Producciones" muestre las disponibles de esa
    // combinación, con su color visible, y el usuario elija manualmente.
    if (tabDetalleActiva === 'produccion') renderGridDetalle();
}

// ── Crear / Editar ────────────────────────────────────────────────────────
function limpiarFormularioEnsamblaje() {
    document.getElementById('formEnsamblaje').reset();
    document.getElementById('ens_buscar_detalle').value = '';
    ensamblajeIdActual = 0;
    ticketDetalleEns = [];
    tabDetalleActiva = 'produccion';
    document.getElementById('tab_producciones').classList.add('activa');
    document.getElementById('tab_derivados').classList.remove('activa');
    renderTicketDetalle();
}

async function abrirModalCrearEnsamblaje() {
    limpiarFormularioEnsamblaje();
    modoEdicionEnsamblaje = false;
    document.getElementById('modalEnsamblajeTitulo').textContent = 'Registrar ensamblaje';
    await cargarSelectsModalEns();
    await renderGridDetalle();
    modalEnsamblaje.show();
}

// Entrada desde produccion.php (?produccion_id=X): prellenar producto y
// dejar esa producción ya agregada en el ticket.
async function abrirModalCrearEnsamblajeDesdeProduccion(produccionId, cantidadProducida) {
    const json = await llamarEnsamblaje('OBTENERDATOSPRODUCCIONPARAENSAMBLAJE', { produccion_id: produccionId });
    if (!json.success) {
        Swal.fire('Aviso', json.message, 'warning');
        return;
    }
    const p = json.produccion;

    limpiarFormularioEnsamblaje();
    modoEdicionEnsamblaje = false;
    document.getElementById('modalEnsamblajeTitulo').textContent = 'Registrar ensamblaje';
    await cargarSelectsModalEns({ producto_id: p.producto_id, color_id: p.color_id });

    agregarLineaDetalle('produccion', {
        produccion_id: p.produccion_id,
        molde_nombre: p.molde_nombre,
        color_nombre: p.color_nombre_verif ?? p.color_nombre,
        cantidad_kg: p.cantidad_kg ?? p.cantidad,
        fecha_hora_fin: p.fecha_hora_fin,
    });

    await renderGridDetalle();
    modalEnsamblaje.show();
}
async function abrirModalEditarEnsamblaje(id) {
    const json = await llamarEnsamblaje('OBTENERENSAMBLAJE', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    limpiarFormularioEnsamblaje();
    modoEdicionEnsamblaje = true;
    ensamblajeIdActual = id;

    const e = json.ensamblaje;
    document.getElementById('modalEnsamblajeTitulo').textContent = 'Editar ensamblaje #' + id;
    await cargarSelectsModalEns({
        producto_id: e.producto_id,
        operario_id: e.operario_id,
    });

    // Prellenar el ticket a partir de los DOS resúmenes reales que arma
    // recalcularResumenesEnsamblaje() en el controlador (NO existe un
    // campo unificado "detalle" con "tipo"; hay dos jsonb separados):
    //   js_moldes_utilizados:    {produccion_id, molde_nombre, cantidad_kg, fecha, categoria_material_nombre}
    //   js_derivados_utilizados: {derivado_id, derivado_nombre}
    const moldes = parseJsonColumna(e.js_moldes_utilizados);
    const derivados = parseJsonColumna(e.js_derivados_utilizados);

    moldes.forEach(item => {
        const est = estiloPorNombre(item.molde_nombre || '');
        ticketDetalleEns.push({
            tempId: ++contadorLineaTicketEns,
            tipo: 'produccion',
            molde_produccion_id: item.produccion_id,
            derivado_id: null,
            nombre: item.molde_nombre ?? ('Producción #' + item.produccion_id),
            meta: `#${item.produccion_id} · ${formatearCantidadEns(item.cantidad_kg)} kg`
                + (item.categoria_material_nombre ? ` · ${item.categoria_material_nombre}` : '')
                + (item.fecha ? ` · ${formatearFechaHoraLegibleEns(item.fecha)}` : ''),
            icono: 'fa-industry',
            color: est.color, bg: est.bg,
            cantidad_kg: parseFloat(item.cantidad_kg) || 0,
        });
    });

    derivados.forEach(item => {
        const est = estiloPorNombre(item.derivado_nombre || '');
        ticketDetalleEns.push({
            tempId: ++contadorLineaTicketEns,
            tipo: 'derivado',
            molde_produccion_id: null,
            derivado_id: item.derivado_id,
            nombre: item.derivado_nombre ?? ('Derivado #' + item.derivado_id),
            meta: `Derivado #${item.derivado_id}`,
            icono: 'fa-flask',
            color: est.color, bg: est.bg,
        });
    });
    renderTicketDetalle();

    await renderGridDetalle();
    modalEnsamblaje.show();
}

document.getElementById('formEnsamblaje').addEventListener('submit', async function (e) {
    e.preventDefault();

    if (ticketDetalleEns.length === 0) {
        Swal.fire('Falta vincular', 'Debes vincular al menos una producción finalizada o un derivado.', 'warning');
        return;
    }

    const valorSelect = document.getElementById('ens_producto_id').value;
    const productoId = valorSelect ? valorSelect.split('_')[0] : '';

    const params = {
        id: ensamblajeIdActual,
        producto_id: productoId,
        operario_ortorgado: document.getElementById('ens_operario_id').value,
        detalle: obtenerDetalleJsonEns(),
    };

    const json = await llamarEnsamblaje('GUARDARENSAMBLAJE', params);

    if (json.success) {
        modalEnsamblaje.hide();
        Swal.fire('Listo', json.message, 'success');
        cargarEnsamblajes();
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});

// ── Eliminar / Reactivar ──────────────────────────────────────────────────
function eliminarEnsamblaje(id) {
    Swal.fire({
        title: '¿Desactivar este ensamblaje?',
        text: 'Las producciones vinculadas quedarán libres para usarse en otro ensamblaje. Podrás reactivarlo luego.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarEnsamblaje('ELIMINARENSAMBLAJE', { id });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarEnsamblajes();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

function reactivarEnsamblaje(id) {
    llamarEnsamblaje('REACTIVARENSAMBLAJE', { id }).then(json => {
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarEnsamblajes();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

// ── Iniciar / Finalizar (acciones directas desde la card) ────────────────
function iniciarEnsamblajeAccion(id) {
    Swal.fire({
        title: '¿Iniciar el armado ahora?',
        text: 'Se registrará la hora actual del servidor como inicio.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, iniciar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarEnsamblaje('INICIARENSAMBLAJE', { id });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarEnsamblajes();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

function finalizarEnsamblajeAccion(id) {
    Swal.fire({
        title: '¿Finalizar el armado ahora?',
        text: 'Se registrará la hora actual del servidor como fin.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, finalizar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarEnsamblaje('FINALIZARENSAMBLAJE', { id });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarEnsamblajes();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>