<?php
$pageTitle    = 'Ensamblaje';
$pageSubtitle = 'Armado de productos finales';
$activePage = 'ensamblaje';

include("header.php");
?>

<style>
:root{
    --resina-1:#2F6FED; --resina-1-bg:#EAF0FE;
    --resina-2:#E23744; --resina-2-bg:#FCEAEC;
    --resina-3:#16A34A; --resina-3-bg:#E8F7EE;
    --resina-4:#D97706; --resina-4-bg:#FDF1E0;
    --resina-5:#7C3AED; --resina-5-bg:#F1EAFD;
    --resina-6:#0E9488; --resina-6-bg:#E2F5F3;
}

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
.pc-ens-card-head .badges{ display:flex; flex-direction:column; gap:4px; align-items:flex-end; }
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
.pc-btn-complementar{
    padding:7px 12px; font-size:.8em; border-radius:8px; border:1px solid #7C3AED;
    background:#F1EAFD; color:#7C3AED; font-weight:700; display:inline-flex; align-items:center; gap:6px;
    transition:.12s ease;
}
.pc-btn-complementar:hover{ background:#7C3AED; color:#fff; }
.pc-btn-empaquetado{
    padding:7px 12px; font-size:.8em; border-radius:8px; border:1px solid #0E9488;
    background:#E2F5F3; color:#0E9488; font-weight:700; display:inline-flex; align-items:center; gap:6px;
    transition:.12s ease;
}
.pc-btn-empaquetado:hover{ background:#0E9488; color:#fff; }

.badge-condicion-propio{ background:#EAF0FE; color:#2F6FED; border:1px solid #cddafc; }
.badge-condicion-derivado{ background:#F1EAFD; color:#7C3AED; border:1px solid #dccdfa; }
.badge-complemento{
    display:inline-flex; align-items:center; gap:5px; background:#F1EAFD; color:#7C3AED;
    border:1px solid #dccdfa; border-radius:8px; padding:3px 9px; font-size:.85em; font-weight:700;
}
.badge-complemento-estado{
    display:inline-flex; align-items:center; gap:5px; border-radius:8px; padding:2px 8px;
    font-size:.75em; font-weight:700; margin-top:4px;
}
.badge-complemento-estado.disponible{ background:#E8F7EE; color:#16A34A; border:1px solid #bfe8cf; }
.badge-complemento-estado.usado{ background:#FDF1E0; color:#D97706; border:1px solid #f4dcb0; }

.pc-condicion-group{ display:flex; gap:8px; }
.pc-condicion-opt{ flex:1; }
.pc-condicion-opt input{ position:absolute; opacity:0; pointer-events:none; }
.pc-condicion-opt label{
    display:flex; flex-direction:column; gap:2px; border:1.5px solid #e2ddcd; border-radius:10px;
    padding:9px 12px; cursor:pointer; transition:.12s ease; margin:0;
}
.pc-condicion-opt label .t{ font-weight:700; font-size:.85em; color:#3a3730; }
.pc-condicion-opt label .d{ font-size:.72em; color:#8a8578; }
.pc-condicion-opt input:checked + label{ border-color:#2F6FED; background:#EAF0FE; }
.pc-condicion-opt input:checked + label .t{ color:#2F6FED; }

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
.pc-mat-card .meta .prioridad{ color:#16A34A; font-weight:700; }
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


/* ---------- Pestañas de producto (igual que en Producción) ---------- */
.pc-tabs-toolbar{
    display:flex; align-items:center; justify-content:space-between; gap:16px;
    flex-wrap:wrap; border-bottom:1px solid #e7e4dd; margin-bottom:18px;
}
.pc-tabs-row{
    display:flex; align-items:center; gap:22px; flex-wrap:wrap; row-gap:4px;
}
.pc-tab-item{
    display:flex; align-items:center; gap:8px; padding:10px 2px 12px 2px;
    border:none; background:none; cursor:pointer; font-size:.92em; font-weight:600;
    color:#8a8578; border-bottom:2px solid transparent; white-space:nowrap;
    transition:color .12s ease, border-color .12s ease;
}
.pc-tab-item:hover{ color:#152238; }
.pc-tab-item i{ font-size:.95em; }
.pc-tab-item .cnt{
    background:#EEECE6; color:#5c5947; font-size:.75em; font-weight:700;
    border-radius:999px; padding:2px 8px; min-width:20px; text-align:center;
}
.pc-tab-item.activo{ color:#152238; border-bottom-color:#2F6FED; }
.pc-tab-item.activo .cnt{ background:#152238; color:#fff; }

.pc-toggle-inactivos{
    display:flex; align-items:center; gap:7px; font-size:.82em; color:#8a8578;
    cursor:pointer; user-select:none; padding-bottom:12px; white-space:nowrap;
}
.pc-toggle-inactivos input{ width:15px; height:15px; cursor:pointer; accent-color:#2F6FED; }

/* ---------- Agrupación por producto ("escalera"), igual que Producción ---------- */
.pc-ens-group{ margin-bottom:26px; }
.pc-ens-group:last-child{ margin-bottom:4px; }
.pc-ens-group-header{
    display:flex; align-items:center; gap:10px; margin:4px 0 12px 0;
}
.pc-ens-group-header .linea{ flex:1; height:1px; background:#e7e4dd; }
.pc-ens-group-header .texto{
    font-size:.78em; font-weight:800; letter-spacing:.06em; text-transform:uppercase;
    color:#8a5a10; background:#FDF1E0; border:1px solid #f0dcae; border-radius:999px;
    padding:6px 16px; white-space:nowrap; display:flex; align-items:center; gap:6px;
}
.pc-ens-group-count{ font-weight:600; color:#b8834a; opacity:.85; }

.pc-btn-fusionar{
    padding:7px 12px; font-size:.8em; border-radius:8px; border:1px solid #475569;
    background:#EEF1F5; color:#475569; font-weight:700; display:inline-flex; align-items:center; gap:6px;
    transition:.12s ease;
}
.pc-btn-fusionar:hover{ background:#475569; color:#fff; }

/* ---------- TomSelect multi-operarios: chips compactas tipo "pill" ---------- */
#ens_operarios_ids-ts-control{
    display:flex; flex-wrap:wrap; gap:6px; align-items:center;
    min-height:42px; padding:6px 8px; border:1px solid #ced4da; border-radius:8px;
    background:#fff;
}
.ts-wrapper.multi .ts-control{
    padding:6px 8px !important;
}
.ts-wrapper.multi .ts-control > div{
    display:inline-flex; align-items:center; gap:6px;
    background:#EAF0FE !important; color:#1f2430 !important;
    border:1px solid #cddafc !important; border-radius:999px !important;
    padding:4px 6px 4px 4px !important; margin:2px !important;
    font-size:.82em; font-weight:600; max-width:100%;
}
.ts-wrapper.multi .ts-control > div .pc-op-avatar{
    width:20px; height:20px; border-radius:50%; flex:0 0 auto;
    background:#2F6FED; color:#fff; font-size:.68em; font-weight:800;
    display:flex; align-items:center; justify-content:center; text-transform:uppercase;
}
.ts-wrapper.multi .ts-control > div .pc-op-nombre{
    overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:150px;
}
.ts-wrapper.multi .ts-control > div .remove{
    border-left:none !important; color:#7d8aa8 !important; padding:0 2px !important;
    margin-left:2px !important; font-size:1.05em; line-height:1;
}
.ts-wrapper.multi .ts-control > div .remove:hover{
    color:#c94a4a !important; background:transparent !important;
}
.ts-wrapper.multi .ts-control input{
    font-size:.85em !important;
}
.ts-dropdown{
    border-radius:10px !important; border-color:#e2ddcd !important;
    box-shadow:0 8px 20px rgba(0,0,0,.09) !important;
}
.ts-dropdown .option{
    padding:8px 12px !important; font-size:.88em;
}
.ts-dropdown .option:hover, .ts-dropdown .active{
    background:#EAF0FE !important;
}
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
        <select id="fens_operario" class="form-select" style="max-width:200px">
            <option value="">Todos los operarios</option>
        </select>
        <input type="date" id="fens_desde" class="form-control" style="max-width:160px" title="Desde">
        <input type="date" id="fens_hasta" class="form-control" style="max-width:160px" title="Hasta">
    </div>

    <!-- Pestañas de producto: reemplazan el <select> de producto y el de
         estado. "Ver inactivos" es el único toggle de estado que queda. -->
    <div class="pc-tabs-toolbar">
        <div class="pc-tabs-row" id="ensProductoTabs"></div>
        <label class="pc-toggle-inactivos" title="Incluir también los ensamblajes desactivados">
            <input type="checkbox" id="ensVerInactivos">
            Ver inactivos
        </label>
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
            <div class="col-md-5 mb-2">
                <label class="form-label">Producto a ensamblar *</label>
                <select class="form-select" id="ens_producto_id" required onchange="cambioProductoEnsamblaje()">
                    <option value="">Selecciona un producto...</option>
                </select>
            </div>
            <div class="col-md-4 mb-2">
                <label class="form-label">Operarios que participaron *</label>
                <select class="form-select" id="ens_operarios_ids" multiple></select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Sucursal</label>
                <select class="form-select" id="ens_sucursal_id">
                    <option value="">Selecciona...</option>
                </select>
            </div>
        </div>

          <hr>

          <div class="mb-1 d-flex justify-content-between align-items-center">
            <label class="form-label mb-0">Vinculados a este armado *</label>
            <span class="form-text mb-0">Al menos una producción finalizada, un derivado o un complemento.</span>
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
                    <div class="pc-tab-detalle" id="tab_complementos" onclick="cambiarTabDetalle('complemento')">
                        <i class="fa-solid fa-puzzle-piece"></i> Complemento
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
                        <span class="detalle" id="ens_ticket_detalle">0 producción(es) · 0 derivado(s) · 0 complemento(s)</span>
                        <span class="detalle">Cantidad producida vinculada: <b id="ens_ticket_peso_producido">0</b></span>                    </div>
                </div>
                <div class="form-text" style="padding:0 14px 10px 14px;">
                    El peso real de salida de este armado se registrará al pulsar <b>Finalizar</b> desde la card del listado.
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
let tabDetalleActiva = 'produccion'; // 'produccion' | 'derivado' | 'complemento'
let contadorLineaTicketEns = 0;
let ticketDetalleEns = []; // [{tempId, tipo, molde_produccion_id, derivado_id, ensamblaje_complemento_id, nombre, meta, color, bg, icono}]
let productosDisponiblesEnsCache = null; // cache para el select del modal (producto+color pendientes)
let ensamblajesCache = [];       // último listado recibido del backend
let productoTabActivoEns = null; // clave del producto activo; null = aún sin definir

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
    ['fens_operario', 'fens_desde', 'fens_hasta'].forEach(id => {
        document.getElementById(id).addEventListener('change', cargarEnsamblajes);
    });
    document.getElementById('ensVerInactivos').addEventListener('change', () => cargarEnsamblajes());

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

// ── Agrupación por producto ("escalera"), igual patrón que Producción ────
function claveProductoEns(e) {
    return `${e.producto_codigo ?? ''} - ${e.producto_descripcion ?? 'Sin producto'}`;
}

function agruparEnsamblajesPorProducto(ensamblajes) {
    const grupos = new Map();
    ensamblajes.forEach(e => {
        const clave = claveProductoEns(e);
        if (!grupos.has(clave)) grupos.set(clave, []);
        grupos.get(clave).push(e);
    });
    return grupos;
}

function renderTabsProductoEns(grupos) {
    const contenedor = document.getElementById('ensProductoTabs');
    const totalGeneral = [...grupos.values()].reduce((s, items) => s + items.length, 0);

    let html = `
        <button type="button" class="pc-tab-item ${productoTabActivoEns === 'TODOS' ? 'activo' : ''}" onclick="seleccionarTabProductoEns('TODOS')">
            <i class="fa-solid fa-grip"></i> Todos <span class="cnt">${totalGeneral}</span>
        </button>`;

    for (const [nombreProducto, items] of grupos) {
        const nombreEscapado = nombreProducto.replace(/'/g, "\\'");
        html += `
            <button type="button" class="pc-tab-item ${productoTabActivoEns === nombreProducto ? 'activo' : ''}" onclick="seleccionarTabProductoEns('${nombreEscapado}')">
                <i class="fa-solid fa-layer-group"></i> ${nombreProducto} <span class="cnt">${items.length}</span>
            </button>`;
    }

    contenedor.innerHTML = html;
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

let sucursalesEnsCache = null;
async function obtenerSucursalesEns() {
    if (sucursalesEnsCache) return sucursalesEnsCache;
    const json = await llamarSucursal('LISTARSUCURSALES', { visibilidad: 'activas' });
    sucursalesEnsCache = json.success ? json.sucursales : [];
    return sucursalesEnsCache;
}

function seleccionarTabProductoEns(nombre) {
    productoTabActivoEns = nombre;
    const grupos = agruparEnsamblajesPorProducto(ensamblajesCache);
    renderTabsProductoEns(grupos);
    renderGridEnsamblajes(ensamblajesCache);
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

// Convierte columnas jsonb ARRAY (pueden llegar como string o ya decodificadas
// según el driver) a array de JS de forma segura.
function parseJsonColumna(v) {
    if (!v) return [];
    if (typeof v === 'string') {
        try { return JSON.parse(v) || []; } catch (e) { return []; }
    }
    return Array.isArray(v) ? v : [];
}

// Convierte columnas jsonb OBJETO (ej. js_producto_emsamblado) a objeto JS,
// o null si no hay nada / no se puede parsear.
function parseJsonObjetoColumna(v) {
    if (!v) return null;
    if (typeof v === 'string') {
        try { return JSON.parse(v) || null; } catch (e) { return null; }
    }
    return (typeof v === 'object' && !Array.isArray(v)) ? v : null;
}

// Categoría de material del armado actual, derivada de las líneas de
// producción ya agregadas al ticket. null si aún no hay ninguna, o si hay
// más de una categoría distinta (mixta) — en ese caso no se puede
// determinar con certeza y no se ofrecen complementos.
function categoriaMaterialTicketActual() {
    const cats = ticketDetalleEns
        .filter(l => l.tipo === 'produccion' && l.categoria_material_id)
        .map(l => l.categoria_material_id);
    if (cats.length === 0) return null;
    return new Set(cats).size === 1 ? cats[0] : null;
}

// ── Selects auxiliares ────────────────────────────────────────────────────
async function obtenerProductosEns() {
    if (productosEnsCache && productosEnsCache.length > 0) return productosEnsCache;
    const json = await llamarEnsamblaje('BUSCARPRODUCTOS', { texto: '' });
    productosEnsCache = json.success ? json.productos : [];
    return productosEnsCache;
}

async function cargarSelectsFiltroEns() {
    const operario = await llamarEnsamblaje('BUSCAROPERARIOS');
    if (operario.success) {
        const sOp = document.getElementById('fens_operario');
        operario.operario.forEach(o => sOp.insertAdjacentHTML('beforeend',
            `<option value="${o.id}">${o.nombre_completo}</option>`));
    }
}
async function obtenerProductosDisponiblesEns(incluirEnsamblajeId = 0) {
    if (!incluirEnsamblajeId) {
        if (productosDisponiblesEnsCache && productosDisponiblesEnsCache.length > 0) return productosDisponiblesEnsCache;
        const json = await llamarEnsamblaje('BUSCARPRODUCTOSDISPONIBLESENSAMBLAJE', { texto: '' });
        productosDisponiblesEnsCache = json.success ? json.productos : [];
        return productosDisponiblesEnsCache;
    }
    const json = await llamarEnsamblaje('BUSCARPRODUCTOSDISPONIBLESENSAMBLAJE', { texto: '', incluir_ensamblaje_id: incluirEnsamblajeId });
    return json.success ? json.productos : [];
}
async function cargarSelectsModalEns(seleccion = {}, incluirEnsamblajeId = 0) {
    const [productos, operario, sucursales] = await Promise.all([
        obtenerProductosDisponiblesEns(incluirEnsamblajeId),
        llamarEnsamblaje('BUSCAROPERARIOS'),
        obtenerSucursalesEns(),
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
    const sSuc = document.getElementById('ens_sucursal_id');
        sSuc.innerHTML = '<option value="">Selecciona...</option>' +
            (sucursales || []).map(s => `<option value="${s.id}">${s.nombre}</option>`).join('');
        if (seleccion.sucursal_id) sSuc.value = seleccion.sucursal_id;
    

    inicializarTomSelectOperariosEns();
    tsOperariosEns.clearOptions();
    tsOperariosEns.clear();
    if (operario.success) operario.operario.forEach(o => {
        tsOperariosEns.addOption({ id: o.id, nombre_completo: o.nombre_completo, cargo: o.cargo });
    });
    if (Array.isArray(seleccion.operario_ids) && seleccion.operario_ids.length > 0) {
        tsOperariosEns.setValue(seleccion.operario_ids.map(String));
    }
}


function obtenerProductoIdSeleccionadoEns() {
    const valorSelect = document.getElementById('ens_producto_id').value;
    return valorSelect ? valorSelect.split('_')[0] : '';
}

// ── Card individual de ensamblaje ──────────────────────────────────────────
function tarjetaEnsamblajeHtml(e) {
    const producciones = e.producciones_count ?? parseJsonColumna(e.js_moldes_utilizados).length;
    const derivadosCount = e.derivados_count ?? parseJsonColumna(e.js_derivados_utilizados).length;
    const complementosCount = e.complementos_count ?? parseJsonColumna(e.js_complementos_utilizados).length;
    const puedeIniciar   = !e.deleted_at && !e.inicio;
    const puedeFinalizar = !e.deleted_at && e.inicio && !e.fin;
    const productoEmsamblado = parseJsonObjetoColumna(e.js_producto_emsamblado);
    const puedeFusionar = !e.deleted_at && !e.enviado_empaquetado && !productoEmsamblado && !e.ensamblaje_id_referido;
    const esDePrimera = (e.categoria_material_nombre ?? '').trim().toLowerCase() === 'de primera';
    const puedeDecidirDestino = !e.deleted_at && e.fin && !productoEmsamblado && !e.enviado_empaquetado;
    const puedeComplementar = puedeDecidirDestino && esDePrimera;
    const complementoUsado = !!e.ensamblaje_id_referido;
    return `
    <div class="pc-ens-card ${e.deleted_at ? 'inactiva' : ''}" id="fila-ensamblaje-${e.ensamblaje_id}">
        <div class="pc-ens-card-head">
            <div class="titulo">
                <span class="id">#${e.ensamblaje_id}</span>
                <span class="producto-titulo">${e.producto_codigo ?? ''} - ${e.producto_descripcion ?? '-'}</span>
            </div>
            <div class="badges">
                ${badgeRegistroEns(e.deleted_at)}
            </div>
        </div>
        <div class="pc-ens-card-body">
            <div class="pc-ens-field span-2">
                <span class="lbl">Operarios</span>
                <span class="val">
                    ${parseJsonColumna(e.js_operarios).length > 0
                        ? parseJsonColumna(e.js_operarios).map(o =>
                            `<span class="badge-complemento" style="background:#EEF1F5;color:#475569;border-color:#d6dbe3;margin:2px 6px 2px 0;">
                                <i class="fa-solid fa-user"></i> ${o.nombre_completo}
                            </span>`
                        ).join('')
                        : '-'}
                </span>
            </div>
            <div class="pc-ens-field">
                <span class="lbl">Sucursal</span>
                <span class="val">${e.sucursal_nombre ?? '-'}</span>
            </div>
            <div class="pc-ens-field">
                <span class="lbl">Cantidad de salida</span>
                <span class="val">${formatearCantidadEns(e.cantidad_peso_kg)} ${e.unidad_salida_codigo || e.producto_unidad_ensamblaje_codigo || 'kg'}</span>
            </div>
            <div class="pc-ens-field">
                <span class="lbl">Categoría material</span>
                <span class="val">${e.categoria_material_nombre ?? '-'}</span>
            </div>
            <div class="pc-ens-field span-2">
                <span class="lbl">Producciones vinculadas</span>
                <span class="val">
                    ${producciones > 0
                        ? parseJsonColumna(e.js_moldes_utilizados).map(m =>
                            `<span class="badge-complemento" style="background:#EAF0FE;color:#2F6FED;border-color:#cddafc;margin:2px 6px 2px 0;">
                                <i class="fa-solid fa-industry"></i> ${m.molde_nombre ?? ('Producción #' + m.produccion_id)}
                            </span>`
                        ).join('')
                        : '0'}
                </span>
            </div>
            <div class="pc-ens-field span-2">
                <span class="lbl">Derivados vinculados</span>
                <span class="val">
                    ${derivadosCount > 0
                        ? parseJsonColumna(e.js_derivados_utilizados).map(d =>
                            `<span class="badge-complemento" style="background:#E8F7EE;color:#16A34A;border-color:#bfe8cf;margin:2px 6px 2px 0;">
                                <i class="fa-solid fa-flask"></i> ${d.derivado_nombre ?? ('Derivado #' + d.derivado_id)}
                            </span>`
                        ).join('')
                        : '0'}
                </span>
            </div>
            <div class="pc-ens-field span-2">
                <span class="lbl">Complementos vinculados</span>
                <span class="val">
                    ${complementosCount > 0
                        ? parseJsonColumna(e.js_complementos_utilizados).map(c =>
                            `<span class="badge-complemento" style="margin:2px 6px 2px 0;">
                                <i class="fa-solid fa-puzzle-piece"></i> ${c.producto_codigo ?? ''} - ${c.producto_descripcion ?? ''}
                            </span>`
                        ).join('')
                        : '0'}
                </span>
            </div>
            ${productoEmsamblado ? `
            <div class="pc-ens-field">
                <span class="lbl">Complementa a</span>
                <span class="val">
                    <span class="badge-complemento"><i class="fa-solid fa-puzzle-piece"></i> ${productoEmsamblado.codigo ?? ''} - ${productoEmsamblado.descripcion ?? ''}</span>
                    <br>
                    <span class="badge-complemento-estado ${complementoUsado ? 'usado' : 'disponible'}">
                        <i class="fa-solid ${complementoUsado ? 'fa-lock' : 'fa-circle-check'}"></i>
                        ${complementoUsado ? `Usado en armado #${e.ensamblaje_id_referido}` : 'Disponible para vincular'}
                    </span>
                </span>
            </div>` : ''}
            ${e.enviado_empaquetado ? `
            <div class="pc-ens-field">
                <span class="lbl">Empaquetado</span>
                <span class="val">
                    <span class="badge-complemento-estado usado">
                        <i class="fa-solid fa-box"></i> Enviado ${formatearFechaHoraLegibleEns(e.fecha_envio_empaquetado)}
                    </span>
                </span>
            </div>` : ''}
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
            ${puedeDecidirDestino ? `
                ${puedeComplementar ? `
                    <button type="button" class="pc-btn-complementar" onclick="marcarComplementoAccion(${e.ensamblaje_id})" title="Marcar como complemento de otro producto">
                        <i class="fa-solid fa-puzzle-piece"></i> Complementar</button>
                ` : ''}
                <button type="button" class="pc-btn-empaquetado" onclick="pasarAEmpaquetadoAccion(${e.ensamblaje_id})" title="Enviar directo a empaquetado">
                    <i class="fa-solid fa-box"></i> A Empaquetado</button>
            ` : ''}
            ${puedeFusionar
                ? `<button type="button" class="pc-btn-fusionar" onclick="fusionarEnsamblajeAccion(${e.ensamblaje_id})" title="Fusionar con otro armado del mismo producto">
                    <i class="fa-solid fa-code-merge"></i> Fusionar</button>`
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
}

// ── Pinta el grid según la pestaña de producto activa ──────────────────────
function renderGridEnsamblajes(ensamblajes) {
    const grid = document.getElementById('gridEnsamblajes');

    if (ensamblajes.length === 0) {
        grid.className = 'pc-ens-grid';
        grid.innerHTML = '<div class="pc-ens-empty">No hay registros de ensamblaje.</div>';
        return;
    }

    const grupos = agruparEnsamblajesPorProducto(ensamblajes);
    let html = '';

    if (productoTabActivoEns === 'TODOS') {
        grid.className = ''; // cada grupo trae su propio pc-ens-grid interno
        for (const [nombreProducto, items] of grupos) {
            html += `
                <div class="pc-ens-group">
                    <div class="pc-ens-group-header">
                        <span class="linea"></span>
                        <span class="texto"><i class="fa-solid fa-layer-group"></i> ${nombreProducto} <span class="pc-ens-group-count">· ${items.length}</span></span>
                        <span class="linea"></span>
                    </div>
                    <div class="pc-ens-grid">
                        ${items.map(tarjetaEnsamblajeHtml).join('')}
                    </div>
                </div>`;
        }
    } else {
        grid.className = 'pc-ens-grid';
        const items = grupos.get(productoTabActivoEns) || [];
        html = items.length
            ? items.map(tarjetaEnsamblajeHtml).join('')
            : '<div class="pc-ens-empty">No hay armados registrados para este producto.</div>';
    }

    grid.innerHTML = html;
}

// ── Carga desde el servidor + calcula pestaña por defecto ──────────────────
async function cargarEnsamblajes() {
    const params = {
        texto: document.getElementById('fens_texto').value.trim(),
        operario_id: document.getElementById('fens_operario').value,
        estado: document.getElementById('ensVerInactivos').checked ? '' : 'activa',
        fecha_desde: document.getElementById('fens_desde').value,
        fecha_hasta: document.getElementById('fens_hasta').value,
    };

    const json = await llamarEnsamblaje('LISTARENSAMBLAJES', params);
    const grid = document.getElementById('gridEnsamblajes');

    if (!json.success) {
        grid.innerHTML = `<div class="pc-ens-empty">${json.message}</div>`;
        return;
    }

    ensamblajesCache = json.ensamblajes || [];
    const grupos = agruparEnsamblajesPorProducto(ensamblajesCache);

    // Igual que en Producción: por defecto se abre el primer producto del
    // listado (no "Todos"). Si la pestaña activa ya no existe (se
    // desactivó su único registro, se cambió el filtro, etc.), cae al
    // primer producto disponible.
    if (productoTabActivoEns === null || (productoTabActivoEns !== 'TODOS' && !grupos.has(productoTabActivoEns))) {
        const primerProducto = grupos.keys().next().value;
        productoTabActivoEns = primerProducto ?? 'TODOS';
    }

    renderTabsProductoEns(grupos);
    renderGridEnsamblajes(ensamblajesCache);
}

// =============================================================================
// COMPLEMENTAR: marcar un armado finalizado como complemento de otro producto
// =============================================================================

// Trae SOLO los productos que aún siguen "vivos" dentro del módulo de
// ensamblaje (tienen un ensamblaje propio finalizado y todavía libre) --
// NO el catálogo completo de producto. Se excluye el propio ensamblaje
// que se está marcando para que no pueda elegirse a sí mismo como objetivo.
async function obtenerProductosParaComplementar(excluirId) {
    const json = await llamarEnsamblaje('BUSCARPRODUCTOSPARACOMPLEMENTAR', { excluir_id: excluirId, texto: '' });
    return json.success ? (json.productos || []) : [];
}

async function fusionarEnsamblajeAccion(origenId) {
    const origen = ensamblajesCache.find(e => e.ensamblaje_id === origenId);
    if (!origen) { Swal.fire('Error', 'No se encontró el ensamblaje de origen.', 'error'); return; }

    // Candidatos: mismo producto, activos, aún abiertos (sin finalizar),
    // sin enviar a empaquetado, sin marcar como complemento.
    const candidatos = ensamblajesCache.filter(e =>
        e.ensamblaje_id !== origenId &&
        e.producto_id === origen.producto_id &&
        !e.deleted_at &&
        !e.fin &&
        !e.enviado_empaquetado &&
        !parseJsonObjetoColumna(e.js_producto_emsamblado)
    );

    if (candidatos.length === 0) {
        Swal.fire('Aviso', 'No hay otro armado abierto (sin finalizar) del mismo producto para fusionar. Solo se puede fusionar hacia un armado que aún no haya sido finalizado.', 'warning');
        return;
    }

    const opciones = candidatos.map(e =>
        `<option value="${e.ensamblaje_id}">#${e.ensamblaje_id} · ${formatearCantidadEns(e.cantidad_peso_kg)} kg · ${e.inicio ? 'En curso' : 'Sin iniciar'}</option>`
    ).join('');

    const { value: destinoId } = await Swal.fire({
        title: `Fusionar #${origenId} dentro de...`,
        html: `<p style="font-size:.85em;color:#666;text-align:left;">
                   El armado #${origenId} quedará inactivo y sus producciones/derivados se moverán al armado que elijas.
               </p>
               <select id="swal-fusion-destino" class="form-select">
                   <option value="">Selecciona un armado destino...</option>
                   ${opciones}
               </select>`,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Continuar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const val = document.getElementById('swal-fusion-destino').value;
            if (!val) { Swal.showValidationMessage('Debes elegir un armado destino.'); return false; }
            return val;
        }
    });
    if (!destinoId) return;

    const confirmacion = await Swal.fire({
        title: '¿Confirmar fusión?',
        text: `El armado #${origenId} quedará inactivo (como constancia) y sus líneas pasarán a formar parte de #${destinoId}. No se puede deshacer automáticamente.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, fusionar',
        cancelButtonText: 'Cancelar'
    });
    if (!confirmacion.isConfirmed) return;

    const json = await llamarEnsamblaje('FUSIONARENSAMBLAJE', { origen_id: origenId, destino_id: destinoId });
    if (json.success) { Swal.fire('Listo', json.message, 'success'); cargarEnsamblajes(); }
    else { Swal.fire('Error', json.message, 'error'); }
}
async function marcarComplementoAccion(id) {
    const confirmacion = await Swal.fire({
        title: '¿Pasar este armado a Complementar?',
        text: 'Quedará disponible para ser consumido dentro de otro ensamblaje distinto y ya no podrá enviarse a empaquetado.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar'
    });
    if (!confirmacion.isConfirmed) return;

    const productos = await obtenerProductosParaComplementar(id);
    if (!productos || productos.length === 0) {
        Swal.fire('Aviso', 'No hay productos disponibles para complementar (deben tener al menos un ensamblaje propio finalizado y aún libre, de la misma categoría de material).', 'warning');
        return;
    }
    // Se muestra "Código - Descripción (Color)" para que el usuario elija
    // el producto+color exacto al que va a complementar, ya que un mismo
    // producto puede tener varios armados libres en colores distintos.
    const opciones = productos.map(p =>
        `<option value="${p.producto_id}">${p.codigo} - ${p.producto}${p.color_nombre ? ' (' + p.color_nombre + ')' : ''}</option>`
    ).join('');

    const { value: productoObjetivoId } = await Swal.fire({
        title: 'Marcar como complemento',
        html: `<p style="font-size:.85em;color:#666;text-align:left;">Elige el producto y color final al que este armado ya finalizado va a complementar.</p>
               <select id="swal-complemento-producto" class="form-select">
                   <option value="">Selecciona un producto...</option>
                   ${opciones}
               </select>`,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Marcar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const val = document.getElementById('swal-complemento-producto').value;
            if (!val) { Swal.showValidationMessage('Debes elegir un producto.'); return false; }
            return val;
        }
    });
    if (!productoObjetivoId) return;

    const json = await llamarEnsamblaje('COMPLEMENTAR', { id, producto_objetivo_id: productoObjetivoId });
    if (json.success) { Swal.fire('Listo', json.message, 'success'); cargarEnsamblajes(); }
    else { Swal.fire('Error', json.message, 'error'); }
}

async function pasarAEmpaquetadoAccion(id) {
    const confirmacion = await Swal.fire({
        title: '¿Enviar este armado a Empaquetado?',
        text: 'Se registrará como producto terminado independiente y ya no podrá marcarse como complemento de otro producto.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, enviar',
        cancelButtonText: 'Cancelar'
    });
    if (!confirmacion.isConfirmed) return;

    const json = await llamarEnsamblaje('PASARAEMPAQUETADO', { id });
    if (json.success) { Swal.fire('Listo', json.message, 'success'); cargarEnsamblajes(); }
    else { Swal.fire('Error', json.message, 'error'); }
}
// =============================================================================
// PANEL DE DETALLE: tabs (producciones / derivados / complemento) + ticket
// =============================================================================

function cambiarTabDetalle(tipo) {
    tabDetalleActiva = tipo;
    document.getElementById('tab_producciones').classList.toggle('activa', tipo === 'produccion');
    document.getElementById('tab_derivados').classList.toggle('activa', tipo === 'derivado');
    document.getElementById('tab_complementos').classList.toggle('activa', tipo === 'complemento');
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
                        categoria_material_id: p.categoria_material_id,
                        unidad_codigo: p.unidad_produccion_codigo || 'KG',
                    })})'>
                <span class="pellet"><i class="fa-solid fa-industry"></i></span>
                <span class="nombre">${p.molde_nombre ?? ('Producción #' + p.produccion_id)}</span>
                <span class="meta">#${p.produccion_id} · <b>${formatearCantidadEns(p.cantidad_kg ?? p.cantidad)}</b> ${p.unidad_produccion_codigo || 'KG'}</span>
                <span class="meta">Color: <b>${colorNombre || '-'}</b></span>
                <span class="meta">${formatearFechaHoraLegibleEns(p.fecha_hora_fin)}</span>
            </button>`;
        }).join('');
    } else if (tabDetalleActiva === 'derivado') {
        if (!productoId) {
            grid.innerHTML = '<div class="pc-mat-empty">Selecciona un producto para ver sus derivados relacionados.</div>';
            return;
        }
        grid.innerHTML = '<div class="pc-mat-empty"><i class="fa-solid fa-spinner fa-spin"></i> Buscando...</div>';
        const json = await llamarEnsamblaje('BUSCARDERIVADOS', { texto, producto_id: productoId });
        if (!json.success) {
            grid.innerHTML = `<div class="pc-mat-empty" style="color:#c94a4a;">${json.message}</div>`;
            return;
        }
        const derivados = json.derivados || [];

        if (derivados.length === 0) {
            grid.innerHTML = '<div class="pc-mat-empty">No hay derivados relacionados con este producto.</div>';
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
                <span class="meta">Stock: <b>${formatearCantidadEns(d.stock_actual)} ${d.unidad_corto ?? ''}</b></span>
            </button>`;
        }).join('');
    } else {
        if (!productoId) {
            grid.innerHTML = '<div class="pc-mat-empty">Selecciona un producto para ver complementos disponibles.</div>';
            return;
        }
        const categoriaActual = categoriaMaterialTicketActual();
        if (!categoriaActual) {
            grid.innerHTML = '<div class="pc-mat-empty">Agrega al menos una producción con categoría de material definida para ver complementos compatibles.</div>';
            return;
        }
        grid.innerHTML = '<div class="pc-mat-empty"><i class="fa-solid fa-spinner fa-spin"></i> Buscando...</div>';
        const json = await llamarEnsamblaje('BUSCARCOMPLEMENTOS', {
            producto_id: productoId,
            texto,
            categoria_material_id: categoriaActual,
        });
        const complementos = json.success ? (json.complementos || []) : [];

        if (complementos.length === 0) {
            grid.innerHTML = '<div class="pc-mat-empty">No hay armados finalizados de la misma categoría de material marcados como complemento de este producto.</div>';
            return;
        }

        grid.innerHTML = complementos.map(c => {
            const est = estiloPorNombre(c.producto_codigo || '');
            const yaAgregada = ticketDetalleEns.some(l => l.tipo === 'complemento' && l.ensamblaje_complemento_id == c.ensamblaje_id);
            return `
            <button type="button" class="pc-mat-card ${yaAgregada ? 'ya-agregada' : ''}" ${yaAgregada ? 'disabled' : ''}
                    style="--card-color:${est.color};--card-bg:${est.bg};"
                    onclick='agregarLineaDetalle("complemento", ${JSON.stringify({
                        ensamblaje_id: c.ensamblaje_id,
                        producto_codigo: c.producto_codigo,
                        producto_descripcion: c.producto_descripcion,
                        cantidad_peso_kg: c.cantidad_peso_kg,
                        fin: c.fin,
                    })})'>
                <span class="pellet"><i class="fa-solid fa-puzzle-piece"></i></span>
                <span class="nombre">${c.producto_codigo ?? ''} - ${c.producto_descripcion ?? ''}</span>
                <span class="meta">Armado #${c.ensamblaje_id} · <b>${formatearCantidadEns(c.cantidad_peso_kg)}</b> kg</span>
                <span class="meta">${formatearFechaHoraLegibleEns(c.fin)}</span>
            </button>`;
        }).join('');
    }
}

function agregarLineaDetalle(tipo, datos) {
    const nombreParaEstilo = tipo === 'produccion' ? (datos.molde_nombre || '')
        : tipo === 'derivado' ? (datos.nombre || '')
        : (datos.producto_codigo || '');
    const est = estiloPorNombre(nombreParaEstilo);

    if (tipo === 'produccion') {
        ticketDetalleEns.push({
            tempId: ++contadorLineaTicketEns,
            tipo: 'produccion',
            molde_produccion_id: datos.produccion_id,
            derivado_id: null,
            ensamblaje_complemento_id: null,
            nombre: datos.molde_nombre ?? ('Producción #' + datos.produccion_id),
            meta: `#${datos.produccion_id} · Color: ${datos.color_nombre || '-'} · ${formatearCantidadEns(datos.cantidad_kg)} ${datos.unidad_codigo || 'KG'} · ${formatearFechaHoraLegibleEns(datos.fecha_hora_fin)}`,
            icono: 'fa-industry',
            color: est.color, bg: est.bg,
            cantidad_kg: parseFloat(datos.cantidad_kg) || 0,
            unidad_codigo: datos.unidad_codigo || 'KG',
            categoria_material_id: datos.categoria_material_id,
        });
    } else if (tipo === 'derivado') {
        ticketDetalleEns.push({
            tempId: ++contadorLineaTicketEns,
            tipo: 'derivado',
            molde_produccion_id: null,
            derivado_id: datos.derivado_id,
            ensamblaje_complemento_id: null,
            nombre: datos.nombre,
            meta: `Derivado #${datos.derivado_id}`,
            icono: 'fa-flask',
            color: est.color, bg: est.bg,
        });
    } else {
        ticketDetalleEns.push({
            tempId: ++contadorLineaTicketEns,
            tipo: 'complemento',
            molde_produccion_id: null,
            derivado_id: null,
            ensamblaje_complemento_id: datos.ensamblaje_id,
            nombre: `${datos.producto_codigo ?? ''} - ${datos.producto_descripcion ?? ''}`,
            meta: `Armado #${datos.ensamblaje_id} · ${formatearCantidadEns(datos.cantidad_peso_kg)} kg`,
            icono: 'fa-puzzle-piece',
            color: est.color, bg: est.bg,
        });
    }
    renderTicketDetalle();
    renderGridDetalle();
}

let tsOperariosEns = null;
function inicializarTomSelectOperariosEns() {
    if (tsOperariosEns) return;
    tsOperariosEns = new TomSelect('#ens_operarios_ids', {
        valueField: 'id',
        labelField: 'nombre_completo',
        searchField: ['nombre_completo'],
        options: [],
        plugins: ['remove_button'],
        placeholder: 'Buscar y agregar operarios...',
        render: {
            option: function (data, escape) {
                const iniciales = obtenerInicialesOperario(data.nombre_completo);
                return `<div style="display:flex;align-items:center;gap:8px;">
                    <span class="pc-op-avatar" style="width:24px;height:24px;border-radius:50%;background:#2F6FED;color:#fff;font-size:.7em;font-weight:800;display:flex;align-items:center;justify-content:center;flex:0 0 auto;">${iniciales}</span>
                    <div>
                        <div>${escape(data.nombre_completo)}</div>
                        ${data.cargo ? `<div class="text-muted small">${escape(data.cargo)}</div>` : ''}
                    </div>
                </div>`;
            },
            item: function (data, escape) {
                const iniciales = obtenerInicialesOperario(data.nombre_completo);
                return `<div>
                    <span class="pc-op-avatar">${iniciales}</span>
                    <span class="pc-op-nombre">${escape(data.nombre_completo)}</span>
                </div>`;
            },
        }
    });
}

// Toma las 2 primeras iniciales relevantes del nombre (ej. "FARROÑAN
// SANTAMARIA ELAR DANIEL" -> "FS"), para el avatar circular de cada chip.
function obtenerInicialesOperario(nombreCompleto) {
    const partes = (nombreCompleto || '').trim().split(/\s+/).filter(Boolean);
    if (partes.length === 0) return '?';
    if (partes.length === 1) return partes[0].substring(0, 2).toUpperCase();
    return (partes[0][0] + partes[1][0]).toUpperCase();
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
    const nComp = ticketDetalleEns.filter(l => l.tipo === 'complemento').length;
    total.textContent = ticketDetalleEns.length;
    detalle.textContent = `${nProd} producción(es) · ${nDer} derivado(s) · ${nComp} complemento(s)`;

    const gruposCantidad = {};
    ticketDetalleEns.filter(l => l.tipo === 'produccion').forEach(l => {
        const u = l.unidad_codigo || 'KG';
        gruposCantidad[u] = (gruposCantidad[u] || 0) + Number(l.cantidad_kg || 0);
    });
    const textoCantidad = Object.entries(gruposCantidad)
        .map(([u, v]) => `${formatearCantidadEns(v)} ${u}`)
        .join(' + ') || '0';
    pesoEl.textContent = textoCantidad;
}

function obtenerDetalleJsonEns() {
    return JSON.stringify(ticketDetalleEns.map(l => ({
        tipo: l.tipo,
        molde_produccion_id: l.molde_produccion_id,
        derivado_id: l.derivado_id,
        ensamblaje_complemento_id: l.ensamblaje_complemento_id ?? null,
    })));
}

function cambioProductoEnsamblaje() {
    renderGridDetalle();
}

// ── Crear / Editar ────────────────────────────────────────────────────────
function limpiarFormularioEnsamblaje() {
    document.getElementById('formEnsamblaje').reset();
    document.getElementById('ens_buscar_detalle').value = '';
    ensamblajeIdActual = 0;
    ticketDetalleEns = [];
    if (tsOperariosEns) tsOperariosEns.clear(); // <-- nuevo
    tabDetalleActiva = 'produccion';
    document.getElementById('tab_producciones').classList.add('activa');
    document.getElementById('tab_derivados').classList.remove('activa');
    document.getElementById('tab_complementos').classList.remove('activa');
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
        unidad_codigo: p.unidad_produccion_codigo || 'KG',
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
    const operariosVinculados = parseJsonColumna(e.js_operarios).map(o => o.operario_id);

    await cargarSelectsModalEns({
        producto_id: e.producto_id,
        color_id: e.color_id_actual,
        operario_ids: operariosVinculados,
        sucursal_id: e.sucursal,
    }, id);

    const moldes = parseJsonColumna(e.js_moldes_utilizados);
    const derivados = parseJsonColumna(e.js_derivados_utilizados);
    const complementos = parseJsonColumna(e.js_complementos_utilizados);

    moldes.forEach(item => {
        const est = estiloPorNombre(item.molde_nombre || '');
        ticketDetalleEns.push({
            tempId: ++contadorLineaTicketEns,
            tipo: 'produccion',
            molde_produccion_id: item.produccion_id,
            derivado_id: null,
            ensamblaje_complemento_id: null,
            nombre: item.molde_nombre ?? ('Producción #' + item.produccion_id),
            meta: `#${item.produccion_id} · ${formatearCantidadEns(item.cantidad_kg)} ${item.unidad_produccion_codigo || 'KG'}`
                + (item.categoria_material_nombre ? ` · ${item.categoria_material_nombre}` : '')
                + (item.fecha ? ` · ${formatearFechaHoraLegibleEns(item.fecha)}` : ''),
            icono: 'fa-industry',
            color: est.color, bg: est.bg,
            cantidad_kg: parseFloat(item.cantidad_kg) || 0,
            unidad_codigo: item.unidad_produccion_codigo || 'KG',
        });
    });

    derivados.forEach(item => {
        const est = estiloPorNombre(item.derivado_nombre || '');
        ticketDetalleEns.push({
            tempId: ++contadorLineaTicketEns,
            tipo: 'derivado',
            molde_produccion_id: null,
            derivado_id: item.derivado_id,
            ensamblaje_complemento_id: null,
            nombre: item.derivado_nombre ?? ('Derivado #' + item.derivado_id),
            meta: `Derivado #${item.derivado_id}`,
            icono: 'fa-flask',
            color: est.color, bg: est.bg,
        });
    });

    complementos.forEach(item => {
        const est = estiloPorNombre(item.producto_codigo || '');
        ticketDetalleEns.push({
            tempId: ++contadorLineaTicketEns,
            tipo: 'complemento',
            molde_produccion_id: null,
            derivado_id: null,
            ensamblaje_complemento_id: item.ensamblaje_complemento_id,
            nombre: `${item.producto_codigo ?? ''} - ${item.producto_descripcion ?? ''}`,
            meta: `Armado #${item.ensamblaje_complemento_id} · ${formatearCantidadEns(item.cantidad_peso_kg)} kg`,
            icono: 'fa-puzzle-piece',
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
        Swal.fire('Falta vincular', 'Debes vincular al menos una producción finalizada, un derivado o un complemento.', 'warning');
        return;
    }

    const params = {
        id: ensamblajeIdActual,
        producto_id: obtenerProductoIdSeleccionadoEns(),
        operarios: JSON.stringify(tsOperariosEns ? tsOperariosEns.getValue() : []),
        sucursal_id: document.getElementById('ens_sucursal_id').value,
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
        text: 'Las producciones y complementos vinculados quedarán libres para usarse en otro ensamblaje. Podrás reactivarlo luego.',
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

// El controlador exige 'peso_kg' (nombre conservado por compatibilidad,
// >0) para finalizar: se pide con un input numérico, mostrando la unidad
// que el producto tiene configurada en Productos > Salida en Ensamblaje
// (fallback a kg si el producto aún no tiene esa config).
function finalizarEnsamblajeAccion(id) {
    const e = ensamblajesCache.find(x => x.ensamblaje_id === id);
    const unidadCodigo = e?.producto_unidad_ensamblaje_codigo || 'kg';
    const unidadNombre = e?.producto_unidad_ensamblaje_nombre || 'kilogramos';

    Swal.fire({
        title: '¿Finalizar el armado?',
        html: `Indica la cantidad de salida (<b>${unidadCodigo}</b> · ${unidadNombre}) de este armado.`,
        icon: 'question',
        input: 'number',
        inputAttributes: { min: 0, step: '0.01' },
        inputPlaceholder: `Cantidad en ${unidadCodigo}`,
        showCancelButton: true,
        confirmButtonText: 'Sí, finalizar',
        cancelButtonText: 'Cancelar',
        inputValidator: (value) => {
            if (!value || parseFloat(value) <= 0) {
                return 'Ingresa una cantidad válida mayor a 0.';
            }
        }
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarEnsamblaje('FINALIZARENSAMBLAJE', { id, peso_kg: result.value });
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