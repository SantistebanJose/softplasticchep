<?php
$pageTitle    = 'Producción';
$pageSubtitle = 'Avances de producción';
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

.pc-color-dot{
    display:inline-block; width:12px; height:12px; border-radius:50%;
    border:1px solid rgba(0,0,0,.15); vertical-align:middle; margin-right:5px;
}

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

/* Resumen del ticket: total en kg + cantidad de materiales, justo debajo
   de la lista, a modo de "total a pagar" de una comanda. */
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

/* Estado de corrida dentro de la card */
.pc-corrida-sin{ color:#9a9585; font-size:.85em; }
.pc-corrida-curso{ font-size:.8em; }
.pc-corrida-curso small{ display:block; color:#8a8578; margin-top:2px; }
.pc-corrida-fin{ font-size:.78em; color:#5c5947; line-height:1.3; }

/* ===================================================================
   Listado de producción en CARDS (reemplaza la tabla). Ordenado por
   ID. Cada card resume un avance: encabezado con ID/estado, cuerpo
   con los datos clave en pares etiqueta/valor, y pie con acciones.
=================================================================== */
.pc-prod-grid{
    display:grid; grid-template-columns:repeat(auto-fill, minmax(300px,1fr));
    gap:14px; margin-top:4px;
}
.pc-prod-card{
    border:1px solid #e7e4dd; border-radius:14px; background:#fff;
    overflow:hidden; display:flex; flex-direction:column;
    transition:box-shadow .12s ease, transform .12s ease;
}
.pc-prod-card:hover{ box-shadow:0 6px 16px rgba(0,0,0,.08); transform:translateY(-1px); }
.pc-prod-card.inactiva{ opacity:.6; }
.pc-prod-card-head{
    padding:10px 14px; background:#fdfcfa; border-bottom:1px solid #eee7db;
    display:flex; justify-content:space-between; align-items:flex-start; gap:8px;
}
.pc-prod-card-head .titulo{ display:flex; flex-direction:column; gap:2px; min-width:0; }
.pc-prod-card-head .id{ font-size:.72em; color:#9a9585; font-weight:600; }
.pc-prod-card-head .molde-titulo{ font-weight:700; font-size:.95em; display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
.pc-prod-card-body{ padding:12px 14px; display:grid; grid-template-columns:1fr 1fr; gap:8px 12px; flex:1; }
.pc-prod-field{ min-width:0; }
.pc-prod-field .lbl{ font-size:.68em; text-transform:uppercase; letter-spacing:.03em; color:#9a9585; display:block; margin-bottom:1px; }
.pc-prod-field .val{ font-size:.85em; color:#3a3730; font-weight:600; overflow-wrap:break-word; }
.pc-prod-field.span-2{ grid-column:1/-1; }
.pc-prod-card-foot{
    padding:8px 14px; border-top:1px solid #eee7db; background:#fffefb;
    display:flex; justify-content:flex-end; align-items:center; gap:6px; flex-wrap:wrap;
}
.pc-btn-ensamblaje{
    margin-left:auto; padding:7px 12px; font-size:.8em; display:inline-flex;
    align-items:center; gap:6px; border-radius:8px;
}
.pc-prod-empty{ text-align:center; color:#9a9585; padding:40px 12px; grid-column:1/-1; }


.pc-tk-total-input{
    width:90px; border:none; border-bottom:2px solid transparent; background:transparent;
    font-weight:700; font-size:1.1em; color:var(--pc-blue,#2F6FED); text-align:right;
    font-variant-numeric:tabular-nums;
}
.pc-tk-total-input:not([readonly]){ border-bottom-color:#d97706; }
.pc-tk-total-input:focus{ outline:none; }

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
/* ---------- Chips de estadísticas rápidas ---------- */
.pc-stat-row{
    display:grid; grid-template-columns:repeat(4,1fr); gap:12px;
    margin-bottom:18px;
}
.pc-stat-chip{
    border:1px solid #e7e4dd; border-radius:12px; background:#fff;
    padding:12px 14px; display:flex; align-items:center; gap:10px;
    transition:box-shadow .15s ease;
}
.pc-stat-chip:hover{ box-shadow:0 4px 12px rgba(0,0,0,.06); }
.pc-stat-chip .ico{
    width:34px; height:34px; border-radius:9px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center; font-size:15px;
}
.pc-stat-chip .txt .n{ font-size:19px; font-weight:700; line-height:1.15; color:#152238; }
.pc-stat-chip .txt .l{ font-size:11px; color:#8a8578; }
.pc-stat-chip.s-gray .ico{ background:#EEECE6; color:#8a8578; }
.pc-stat-chip.s-info .ico{ background:#E3F2FD; color:#0B4DA6; }
.pc-stat-chip.s-success .ico{ background:#E8F7EE; color:#16A34A; }
.pc-stat-chip.s-warning .ico{ background:#FDF1E0; color:#D97706; }

@media (max-width:900px){ .pc-stat-row{ grid-template-columns:repeat(2,1fr); } }

/* ---------- Estado visual en las cards de producción ---------- */
.pc-prod-card{
    border-left:4px solid #e2ddcd;
    transition:border-color .2s ease, background .8s ease;
}
.pc-prod-card.estado-sin{ border-left-color:#c8c3b4; }
.pc-prod-card.estado-curso{ border-left-color:#0B4DA6; }
.pc-prod-card.estado-fin{ border-left-color:#16A34A; }
.pc-prod-card.estado-ensamblaje{ border-left-color:#8a8578; }
.pc-prod-card.estado-ensamblaje{ opacity:.75; }

.pc-prod-card.pc-flash{ animation:pc-flash-bg 1.8s ease; }
@keyframes pc-flash-bg{
    0%{ background:#FFF6DC; box-shadow:0 0 0 2px #F5D98A inset; }
    100%{ background:#fff; box-shadow:none; }
}

.pc-corrida-curso .badge.bg-info{ display:inline-flex; align-items:center; gap:5px; }
.pc-corrida-curso .badge.bg-info::before{
    content:""; width:6px; height:6px; border-radius:50%;
    background:#0B4DA6; animation:pc-pulse-blue 1.6s infinite;
}
@keyframes pc-pulse-blue{
    0%{ box-shadow:0 0 0 0 rgba(11,77,166,.6); }
    70%{ box-shadow:0 0 0 6px rgba(11,77,166,0); }
    100%{ box-shadow:0 0 0 0 rgba(11,77,166,0); }
}
</style>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Producción</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrearProduccion()">
            <i class="fa-solid fa-plus"></i> Registrar producción
        </button>
    </div>
<br>
    <div class="pc-stat-row" id="statRowProduccion"></div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <br>
        <input type="text" id="fprod_texto" class="form-control" style="max-width:260px"
               placeholder="Buscar por molde u observaciones...">
        <select id="fprod_operario" class="form-select" style="max-width:200px">
            <option value="">Todos los operarios</option>
        </select>
        <select id="fprod_maquina" class="form-select" style="max-width:180px">
            <option value="">Todas las máquinas</option>
        </select>
        <select id="fprod_molde" class="form-select" style="max-width:180px">
            <option value="">Todos los moldes</option>
        </select>
        <select id="fprod_color" class="form-select" style="max-width:160px">
            <option value="">Todos los colores</option>
        </select>
        <input type="date" id="fprod_desde" class="form-control" style="max-width:160px" title="Desde">
        <input type="date" id="fprod_hasta" class="form-control" style="max-width:160px" title="Hasta">
        <select id="fprod_estado" class="form-select" style="max-width:160px">
            <option value="">Todos</option>
            <option value="activa" selected>Activos</option>
            <option value="inactiva">Inactivos</option>
        </select>
    </div>

    <div class="pc-prod-grid" id="gridProducciones">
        <div class="pc-prod-empty">Cargando...</div>
    </div>
</div>

<!-- Modal Crear/Editar -->
<div class="modal fade" id="modalProduccion" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <form id="formProduccion">
        <div class="modal-header">
          <h5 class="modal-title" id="modalProduccionTitulo">Registrar producción</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">

          <div class="row">
            <div class="col-md-4 mb-2">
                <label class="form-label">Operario</label>
                <select class="form-select" id="prod_operario_id">
                    <option value="">Selecciona...</option>
                </select>
            </div>
            <div class="col-md-4 mb-2">
                <label class="form-label">Máquina</label>
                <select class="form-select" id="prod_maquina_id">
                    <option value="">Selecciona...</option>
                </select>
            </div>
            <div class="col-md-4 mb-2">
                <label class="form-label">Categoría de material</label>
                <select class="form-select" id="prod_categoria_material_id">
                    <option value="">Selecciona...</option>
                </select>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-2">
                <label class="form-label">Molde *</label>
                <select class="form-select" id="prod_molde_id" required>
                    <option value="">Selecciona un molde...</option>
                </select>
            </div>
            <div class="col-md-6 mb-2">
                <label class="form-label">Color *</label>
                <select class="form-select" id="prod_color_id" required>
                    <option value="">Selecciona un color...</option>
                </select>
            </div>
          </div>

          <div class="row">
            <div class="col-md-4 mb-2">
                <label class="form-label">Fecha de registro</label>
                <input type="datetime-local" class="form-control" id="prod_fecha">
            </div>
            <div class="col-md-8 mb-2">
                <label class="form-label">Observaciones</label>
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
                <div class="pc-tk-resumen" id="prod_ticket_footer">
                <div class="pc-tk-resumen-icon"><i class="fa-solid fa-scale-balanced"></i></div>
                <div class="pc-tk-resumen-texto">
                    <span class="total">
                    <input type="number" step="1" min="1" id="prod_cantidad" class="pc-tk-total-input" required>                        Kg en total
                    </span>
                    <span class="detalle" id="prod_ticket_total_detalle">0 material(es) en este avance</span>
                </div>
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
<!-- Modal previo: Cantidad producida (antes de pasar a ensamblaje) -->
<div class="modal fade" id="modalCantidadEnsamblaje" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <form id="formCantidadEnsamblaje">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fa-solid fa-weight-hanging"></i> Cantidad producida</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label class="form-label">Cantidad producida (kg) *</label>
          <input type="number" step="0.0001" min="0.0001" class="form-control"
                 id="cantidad_producida_ensamblaje" placeholder="Ej. 25.5" required autofocus>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Continuar <i class="fa-solid fa-arrow-right"></i></button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
var total = 0;
const CONTROLADOR_PRODUCCION = 'controllers/clssProduccion.php';
const CONTROLADOR_MOLDES     = 'controllers/clssMoldes.php'; // para el <select> de molde
const CONTROLADOR_COLOR      = 'controllers/clssColor.php';  // para el <select> de color
const modalProduccion = new bootstrap.Modal(document.getElementById('modalProduccion'));

let modoEdicionProduccion = false;
let produccionIdActual = 0;
let materialesProdCache = null; // cache de materiales para las cards
let moldesProdCache = null;     // cache de moldes para selects
let categoriasMaterialProdCache = null; // cache de categorías de material para el select
let materialSeleccionadoId = null; // material activo en el panel de lotes
let contadorLineaTicket = 0;
let ticketLineas = []; // [{tempId, material_id, material_nombre, unidad_corto, color, icono,
                        //   lote_id, lote_label, disponible, cantidad, comentario}]

document.addEventListener('DOMContentLoaded', () => {
    cargarSelectsFiltro();
    cargarProducciones().catch(err => {
        console.error('Error cargando datos iniciales:', err);
        document.getElementById('gridProducciones').innerHTML =
            `<div class="pc-prod-empty" style="color:red;">Error de conexión con el servidor. Revisa la consola (F12).</div>`;
    });

    let debounceTimer = null;
    document.getElementById('fprod_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarProducciones, 350);
    });
    ['fprod_operario', 'fprod_maquina', 'fprod_molde', 'fprod_color', 'fprod_estado', 'fprod_desde', 'fprod_hasta'].forEach(id => {
        document.getElementById(id).addEventListener('change', cargarProducciones);
    });

    document.getElementById('prod_mat_buscar').addEventListener('input', renderGridMateriales);

    iniciarAutoRefresh();
});
// =============================================================================
// TIEMPO REAL: refresco silencioso en segundo plano
// =============================================================================
const POLL_INTERVAL_MS = 8000; // cada 8s, sin avisar nada al usuario
let pollTimer = null;
let snapshotEstados = {}; // { produccion_id: 'sin' | 'curso' | 'fin' }

function iniciarAutoRefresh() {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(() => {
        if (document.hidden) return; // pestaña en segundo plano: no gastar llamadas
        if (modalProduccion._element.classList.contains('show')) return; // no interrumpir mientras editas
        cargarProducciones(true); // silencioso = true, sin "Cargando..."
    }, POLL_INTERVAL_MS);

    // Al volver a la pestaña, refresca de inmediato (sin avisar nada)
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) cargarProducciones(true);
    });

    // Al cerrar el modal (guardaste, cancelaste, o iniciaste/finalizaste desde
    // otra pestaña), refresca de inmediato al volver a esta vista
    document.getElementById('modalProduccion').addEventListener('hidden.bs.modal', () => {
        cargarProducciones(true);
    });
}

function actualizarTextoUltimaActualizacion() {
    const el = document.getElementById('lastUpdateTxt');
    if (!el || !ultimaActualizacion) return;
    const segs = Math.floor((Date.now() - ultimaActualizacion) / 1000);
    let texto;
    if (segs < 3) texto = 'Actualizado justo ahora';
    else if (segs < 60) texto = `Actualizado hace <b>${segs}s</b>`;
    else texto = `Actualizado hace <b>${Math.floor(segs / 60)} min</b>`;
    el.innerHTML = texto;
}

function estadoCorto(p) {
    if (p.enviado_ensamblaje) return 'ensamblaje';
    if (!p.fecha_hora_inicio) return 'sin';
    if (!p.fecha_hora_fin) return 'curso';
    return 'fin';
}

function renderStatRow(producciones) {
    const activas = producciones.filter(p => !p.deleted_at);
    const sinIniciar = activas.filter(p => estadoCorto(p) === 'sin').length;
    const enCurso = activas.filter(p => estadoCorto(p) === 'curso').length;
    const finalizadas = activas.filter(p => estadoCorto(p) === 'fin').length;
    const kgHoy = activas
        .filter(p => p.fecha && p.fecha.substring(0, 10) === new Date().toISOString().substring(0, 10))
        .reduce((s, p) => s + Number(p.cantidad || 0), 0);

    document.getElementById('statRowProduccion').innerHTML = `
        <div class="pc-stat-chip s-gray">
            <div class="ico"><i class="fa-solid fa-hourglass-half"></i></div>
            <div class="txt"><div class="n">${sinIniciar}</div><div class="l">Sin iniciar</div></div>
        </div>
        <div class="pc-stat-chip s-info">
            <div class="ico"><i class="fa-solid fa-gear"></i></div>
            <div class="txt"><div class="n">${enCurso}</div><div class="l">En curso</div></div>
        </div>
        <div class="pc-stat-chip s-success">
            <div class="ico"><i class="fa-solid fa-flag-checkered"></i></div>
            <div class="txt"><div class="n">${finalizadas}</div><div class="l">Finalizadas</div></div>
        </div>
        <div class="pc-stat-chip s-warning">
            <div class="ico"><i class="fa-solid fa-weight-hanging"></i></div>
            <div class="txt"><div class="n">${formatearCantidadProd(kgHoy)}</div><div class="l">Kg registrados hoy</div></div>
        </div>
    `;
}

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

async function llamarMoldes(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_MOLDES, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body
    });
    return resp.json();
}

async function llamarColor(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_COLOR, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body
    });
    return resp.json();
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

function formatearFechaHoraLegible(fechaIso) {
    // Convierte "2026-07-10 14:30:00" a "10/07/2026 14:30" para mostrar en tabla
    if (!fechaIso) return '';
    const [fecha, hora] = fechaIso.split(' ');
    if (!fecha) return fechaIso;
    const [y, m, d] = fecha.split('-');
    return `${d}/${m}/${y}${hora ? ' ' + hora.substring(0, 5) : ''}`;
}

// ── Estado de la corrida (dentro de la card) ─────────────────
function estadoCorridaTexto(p) {
    if (p.enviado_ensamblaje) {
        return `<span class="pc-corrida-fin">
                    <span class="badge bg-secondary">Finalizado</span>
                    <small>Enviado a ensamblaje: ${formatearFechaHoraLegible(p.fecha_envio_ensamblaje)}</small>
                </span>`;
    }
    if (!p.fecha_hora_inicio) {
        return '<span class="pc-corrida-sin">Sin iniciar</span>';
    }
    if (!p.fecha_hora_fin) {
        return `<span class="pc-corrida-curso"><span class="badge bg-info text-dark">En curso</span>
                <small>Inicio: ${formatearFechaHoraLegible(p.fecha_hora_inicio)}</small></span>`;
    }
    return `<span class="pc-corrida-fin">
                Inicio: ${formatearFechaHoraLegible(p.fecha_hora_inicio)}<br>
                Fin: ${formatearFechaHoraLegible(p.fecha_hora_fin)}
            </span>`;
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

// ── Selects auxiliares ───────────────────────────────────────────────────────
async function cargarSelectsFiltro() {
    const [operario, maquinas, moldes, colores] = await Promise.all([
        llamarProduccion('BUSCAROPERARIOS'),
        llamarProduccion('BUSCARMAQUINAS'),
        llamarMoldes('LISTARMOLDES', { texto: '', estado: 'activa' }),
        llamarColor('LISTARCOLORES', { texto: '', estado: 'activa' }),
    ]);
    if (operario.success) {
        const s = document.getElementById('fprod_operario');
        operario.operario.forEach(o => s.insertAdjacentHTML('beforeend', `<option value="${o.id}">${o.nombre_completo}</option>`));
    }
    if (maquinas.success) {
        const s = document.getElementById('fprod_maquina');
        maquinas.maquinas.forEach(m => s.insertAdjacentHTML('beforeend', `<option value="${m.id}">${m.nombre}</option>`));
    }
    if (moldes.success) {
        const s = document.getElementById('fprod_molde');
        moldes.moldes.forEach(m => s.insertAdjacentHTML('beforeend', `<option value="${m.id}">${m.nombre}</option>`));
    }
    if (colores.success) {
        const s = document.getElementById('fprod_color');
        colores.colores.forEach(c => s.insertAdjacentHTML('beforeend', `<option value="${c.id}">${c.nombre}</option>`));
    }
}

async function obtenerMoldesProd() {
    if (moldesProdCache) return moldesProdCache;
    const json = await llamarMoldes('LISTARMOLDES', { texto: '', estado: 'activa' });
    moldesProdCache = json.success ? json.moldes : [];
    return moldesProdCache;
}

// Categorías de material: mismo patrón de cache que moldes, se piden una
// sola vez por carga de página y se reutilizan cada vez que se abre el modal.
async function obtenerCategoriasMaterialProd() {
    if (categoriasMaterialProdCache) return categoriasMaterialProdCache;
    const json = await llamarProduccion('BUSCARCATEGORIASMATERIAL');
    categoriasMaterialProdCache = json.success ? json.categorias : [];
    return categoriasMaterialProdCache;
}

async function cargarSelectsModal(seleccion = {}) {
    const [operario, maquinas, moldes, colores, categorias] = await Promise.all([
        llamarProduccion('BUSCAROPERARIOS'),
        llamarProduccion('BUSCARMAQUINAS'),
        obtenerMoldesProd(),
        llamarColor('LISTARCOLORES', { texto: '', estado: 'activa' }),
        obtenerCategoriasMaterialProd(),
    ]);

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

    const categoriaSelect = document.getElementById('prod_categoria_material_id');
    categoriaSelect.innerHTML = '<option value="">Selecciona...</option>' +
        (categorias || []).map(c => `<option value="${c.id}">${c.nombre}</option>`).join('');
    if (seleccion.categoria_material_id) categoriaSelect.value = seleccion.categoria_material_id;

    const moldeSelect = document.getElementById('prod_molde_id');
    moldeSelect.innerHTML = '<option value="">Selecciona un molde...</option>' +
        (moldes || []).map(m => `<option value="${m.id}">${m.nombre} (${m.forma})</option>`).join('');
    if (seleccion.molde_id) moldeSelect.value = seleccion.molde_id;

    const colorSelect = document.getElementById('prod_color_id');
    colorSelect.innerHTML = '<option value="">Selecciona un color...</option>';
    if (colores.success) colores.colores.forEach(c =>
        colorSelect.insertAdjacentHTML('beforeend', `<option value="${c.id}">${c.nombre}</option>`));
    if (seleccion.color_id) colorSelect.value = seleccion.color_id;
}

async function obtenerOpcionesMaterialesProd() {
    if (materialesProdCache) return materialesProdCache;
    const json = await llamarProduccion('BUSCARMATERIALESPRODUCCION', {});
    materialesProdCache = json.success ? json.materiales : [];
    return materialesProdCache;
}

// ── Listado en CARDS (orden por ID) ───────────────────────────────────────
async function cargarProducciones(silencioso = false) {
    const params = {
        texto: document.getElementById('fprod_texto').value.trim(),
        operario_id: document.getElementById('fprod_operario').value,
        maquina_id: document.getElementById('fprod_maquina').value,
        molde_id: document.getElementById('fprod_molde').value,
        color_id: document.getElementById('fprod_color').value,
        estado: document.getElementById('fprod_estado').value,
        fecha_desde: document.getElementById('fprod_desde').value,
        fecha_hasta: document.getElementById('fprod_hasta').value,
    };

    const grid = document.getElementById('gridProducciones');
    if (!silencioso) grid.innerHTML = '<div class="pc-prod-empty">Cargando...</div>';

    const json = await llamarProduccion('LISTARPRODUCCIONES', params);

    if (!json.success) {
        grid.innerHTML = `<div class="pc-prod-empty">${json.message}</div>`;
        return;
    }

    const producciones = json.producciones || [];
    renderStatRow(producciones);
    if (producciones.length === 0) {
        grid.innerHTML = '<div class="pc-prod-empty">No hay registros de producción.</div>';
        snapshotEstados = {};
        return;
    }

    const nuevosEstados = {};
    grid.innerHTML = producciones.map(p => {
        const colorTexto = p.color_nombre
            ? `${p.color_rgb ? `<span class="pc-color-dot" style="background:${p.color_rgb}"></span>` : ''}${p.color_nombre}`
            : '-';

        const puedeIniciar = !p.deleted_at && !p.fecha_hora_inicio;
        const puedeFinalizar = !p.deleted_at && p.fecha_hora_inicio && !p.fecha_hora_fin;
        const corridaFinalizada = !p.deleted_at && !!p.fecha_hora_fin && !p.enviado_ensamblaje;
        const estado = estadoCorto(p);
        nuevosEstados[p.id] = estado;
        const cambioDeEstado = silencioso && snapshotEstados[p.id] && snapshotEstados[p.id] !== estado;

        return `
        <div class="pc-prod-card estado-${estado} ${p.deleted_at ? 'inactiva' : ''} ${cambioDeEstado ? 'pc-flash' : ''}" id="fila-produccion-${p.id}">
            <div class="pc-prod-card-head">
                <div class="titulo">
                    <span class="id">#${p.id}</span>
                    <span class="molde-titulo">${p.molde_nombre ?? '-'}</span>
                </div>
                ${badgeRegistroProd(p.deleted_at)}
            </div>
            <div class="pc-prod-card-body">
                <div class="pc-prod-field">
                    <span class="lbl">Color</span>
                    <span class="val">${colorTexto}</span>
                </div>
                <div class="pc-prod-field">
                    <span class="lbl">Operario</span>
                    <span class="val">${p.operario_nombre ?? '-'}</span>
                </div>
                <div class="pc-prod-field">
                    <span class="lbl">Máquina</span>
                    <span class="val">${p.maquina_nombre ?? '-'}</span>
                </div>
                <div class="pc-prod-field">
                    <span class="lbl">Fecha</span>
                    <span class="val">${p.fecha}</span>
                </div>
                <div class="pc-prod-field">
                    <span class="lbl">Kg insertados</span>
                    <span class="val">${formatearCantidadProd(p.cantidad)}</span>
                </div>
                <div class="pc-prod-field">
                    <span class="lbl">Materiales</span>
                    <span class="val">${p.items_count}</span>
                </div>
                <div class="pc-prod-field">
                    <span class="lbl">Categoría material</span>
                    <span class="val">${p.categoria_material_nombre ?? '-'}</span>
                </div>
                <div class="pc-prod-field span-2">
                    <span class="lbl">Corrida</span>
                    <span class="val">${estadoCorridaTexto(p)}</span>
                </div>
            </div>
            <div class="pc-prod-card-foot">
                <button class="pc-icon-btn" onclick="abrirModalEditarProduccion(${p.id})" title="Editar">
                    <i class="fa-solid fa-pen"></i>
                </button>
                ${puedeIniciar
                    ? `<button type="button" class="pc-btn-iniciar" onclick="iniciarProduccion(${p.id})" title="Iniciar corrida">
                        <i class="fa-solid fa-play"></i> Iniciar</button>`
                    : ''
                }
                ${puedeFinalizar
                    ? `<button type="button" class="pc-btn-finalizar" onclick="finalizarProduccion(${p.id})" title="Finalizar corrida">
                        <i class="fa-solid fa-flag-checkered"></i> Finalizar</button>`
                    : ''
                }
                ${!p.deleted_at
                    ? `<button class="pc-icon-btn" onclick="eliminarProduccion(${p.id})" title="Desactivar">
                           <i class="fa-solid fa-trash"></i></button>`
                    : `<button class="pc-icon-btn" onclick="reactivarProduccion(${p.id})" title="Reactivar">
                           <i class="fa-solid fa-rotate-left"></i></button>`
                }
                ${corridaFinalizada
                    ? `<button type="button" class="pc-btn pc-btn-primary pc-btn-ensamblaje" onclick="abrirModalCantidadParaEnsamblaje(${p.id})" title="Enviar este avance a ensamblaje">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i> Pasar a ensamblaje</button>`
                    : ''
                }
            </div>
        </div>`;
    }).join('');

    snapshotEstados = nuevosEstados;
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
    const totalInput = document.getElementById('prod_cantidad');
    const detalle = document.getElementById('prod_ticket_total_detalle');

    if (ticketLineas.length === 0) {
        list.innerHTML = `<li class="pc-tk-empty"><i class="fa-solid fa-basket-shopping"></i>Aún no agregas materiales.<br>Toca una card de la izquierda para empezar.</li>`;
        totalInput.readOnly = false;
        detalle.textContent = 'Sin materiales — ingresa los kg manualmente (ej. reproceso)';
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

    const total = ticketLineas.reduce((suma, linea) => suma + Number(linea.cantidad), 0);
    totalInput.readOnly = true;
    totalInput.value = Math.round(total); // entero, aunque las líneas tengan decimales
    detalle.textContent = `${ticketLineas.length} material${ticketLineas.length === 1 ? '' : 'es'} en este avance`;
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
    document.getElementById('prod_mat_buscar').value = '';
    document.getElementById('prod_lote_panel').style.display = 'none';
    produccionIdActual = 0;
    materialSeleccionadoId = null;
    ticketLineas = [];
    renderTicket();
}

async function abrirModalCrearProduccion() {
    limpiarFormularioProduccion();
    modoEdicionProduccion = false;
    document.getElementById('modalProduccionTitulo').textContent = 'Registrar producción';
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

    await cargarSelectsModal({
        operario_id: p.operario_id, maquina_id: p.maquina_id,
        molde_id: p.molde_id, color_id: p.color_id,
        categoria_material_id: p.categoria_material_id,
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

    const params = {
        id: produccionIdActual,
        operario_id: document.getElementById('prod_operario_id').value,
        maquina_id: document.getElementById('prod_maquina_id').value,
        categoria_material_id: document.getElementById('prod_categoria_material_id').value,
        molde_id: document.getElementById('prod_molde_id').value,
        color_id: document.getElementById('prod_color_id').value,
        cantidad: document.getElementById('prod_cantidad').value,
        fecha: document.getElementById('prod_fecha').value.replace('T', ' '),
        observaciones: document.getElementById('prod_observaciones').value.trim(),
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

// ── Iniciar / Finalizar corrida (acciones directas desde la card) ───────────
function iniciarProduccion(id) {
    Swal.fire({
        title: '¿Iniciar la corrida ahora?',
        text: 'Se registrará la hora actual del servidor como inicio.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, iniciar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarProduccion('INICIARCORRIDA', { id });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarProducciones();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

function finalizarProduccion(id) {
    Swal.fire({
        title: '¿Finalizar la corrida ahora?',
        text: 'Se registrará la hora actual del servidor como fin.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, finalizar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarProduccion('FINALIZARCORRIDA', { id });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarProducciones();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

const modalCantidadEnsamblaje = new bootstrap.Modal(document.getElementById('modalCantidadEnsamblaje'));
let produccionIdParaEnsamblaje = null;

function abrirModalCantidadParaEnsamblaje(produccionId) {
    produccionIdParaEnsamblaje = produccionId;
    document.getElementById('formCantidadEnsamblaje').reset();
    modalCantidadEnsamblaje.show();
}

document.getElementById('formCantidadEnsamblaje').addEventListener('submit', async function (e) {
    e.preventDefault();
    const valor = parseFloat(document.getElementById('cantidad_producida_ensamblaje').value);
    if (isNaN(valor) || valor <= 0) {
        Swal.fire('Dato inválido', 'Ingresa una cantidad producida mayor a 0.', 'warning');
        return;
    }

    const json = await llamarProduccion('ENVIARAENSAMBLAJE', {
        id: produccionIdParaEnsamblaje,
        cantidad_producida: valor,
    });

    if (!json.success) {
        Swal.fire('Error', json.message, 'error');
        return;
    }

    modalCantidadEnsamblaje.hide();
    Swal.fire('Listo', 'Avance enviado a ensamblaje correctamente.', 'success');
    cargarProducciones(); // se queda en Producción, la card pasa a "Finalizado"
});
</script>

<?php require __DIR__ . '/footer.php'; ?>