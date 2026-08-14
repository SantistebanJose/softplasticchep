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

   NOTA: ya no existe selección de lote/proveedor en este formulario.
   El usuario solo elige material + cantidad; el backend reparte la
   cantidad automáticamente entre los lotes disponibles (FIFO).
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
.pc-mat-tabs{ display:flex; gap:6px; padding:0 12px 10px 12px; }
.pc-mat-tab{
    flex:1; border:1px solid #e2ddcd; background:#fff; border-radius:9px;
    padding:7px 10px; font-size:.78em; font-weight:600; color:#8a8578;
    display:flex; align-items:center; justify-content:center; gap:6px; cursor:pointer;
    transition:.12s ease;
}
.pc-mat-tab:hover{ border-color:#d8d4c8; color:#5c5947; }
.pc-mat-tab.activo{ background:#152238; border-color:#152238; color:#fff; }

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

/* ===================================================================
   Listado de producción en CARDS, agrupado por PRODUCTO ("escalera").
   Cada grupo tiene un encabezado con el nombre del producto y, debajo,
   la cuadrícula de avances (moldes) que lo están fabricando.
=================================================================== */
.pc-prod-group{ margin-bottom:26px; }
.pc-prod-group:last-child{ margin-bottom:4px; }
.pc-prod-group-header{
    display:flex; align-items:center; gap:10px; margin:4px 0 12px 0;
}
.pc-prod-group-header .linea{ flex:1; height:1px; background:#e7e4dd; }
.pc-prod-group-header .texto{
    font-size:.78em; font-weight:800; letter-spacing:.06em; text-transform:uppercase;
    color:#8a5a10; background:#FDF1E0; border:1px solid #f0dcae; border-radius:999px;
    padding:6px 16px; white-space:nowrap; display:flex; align-items:center; gap:6px;
}
.pc-prod-group-header .texto i{ font-size:.85em; opacity:.8; }
.pc-prod-group-count{ font-weight:600; color:#b8834a; opacity:.85; }

.pc-prod-grid{
    display:grid; grid-template-columns:repeat(auto-fill, minmax(266px,1fr));
    gap:12px; margin-top:4px;
}

/* Card minimalista: sin bloques de color, un solo hairline, jerarquía por
   tipografía y espaciado en vez de fondos/badges. El único acento de color
   vivo es el punto de estado (arriba-izq) y el botón "Pasar a ensamblaje". */
.pc-prod-card{
    border:1px solid #ece9e1; border-radius:12px; background:#fff;
    padding:16px 16px 14px 16px; display:flex; flex-direction:column; gap:10px;
    transition:border-color .15s ease, box-shadow .15s ease;
}
.pc-prod-card:hover{ border-color:#d8d4c8; box-shadow:0 2px 10px rgba(20,18,10,.05); }
.pc-prod-card.inactiva{ opacity:.55; }

.pc-prod-card-top{ display:flex; align-items:center; gap:8px; }
.pc-prod-dot{ width:7px; height:7px; border-radius:50%; flex:0 0 auto; background:#c8c3b4; }
.pc-prod-card.estado-curso .pc-prod-dot{ background:#0B4DA6; animation:pc-pulse-blue 1.6s infinite; }
.pc-prod-card.estado-fin .pc-prod-dot{ background:#16A34A; }
.pc-prod-card.estado-ensamblaje .pc-prod-dot{ background:#8a8578; }

.pc-prod-id{ font-size:.72em; color:#a7a293; font-weight:600; }
.pc-prod-estado-txt{ font-size:.72em; color:#8a8578; font-weight:600; }
.pc-prod-card.estado-curso .pc-prod-estado-txt{ color:#0B4DA6; }
.pc-prod-card.estado-fin .pc-prod-estado-txt{ color:#16A34A; }
.pc-prod-card-spacer{ flex:1; }
.pc-prod-edit-btn{
    border:none; background:none; color:#c3beae; padding:3px 5px; font-size:.85em; cursor:pointer;
    border-radius:6px; transition:.12s ease; line-height:1;
}
.pc-prod-edit-btn:hover{ color:#2F6FED; background:#EAF0FE; }

.pc-prod-title{ font-size:1em; font-weight:700; color:#1f2430; line-height:1.25; }

.pc-prod-meta{ font-size:.78em; color:#9a9585; line-height:1.4; }
.pc-prod-meta span:not(:last-child)::after{ content:"·"; margin:0 6px; color:#d8d4c8; }

.pc-prod-stats{ display:flex; gap:22px; }
.pc-prod-stat .num{ font-size:1.2em; font-weight:700; color:#1f2430; line-height:1; }
.pc-prod-stat .lbl{ font-size:.66em; color:#9a9585; margin-top:3px; text-transform:uppercase; letter-spacing:.03em; }

.pc-prod-tags{ display:flex; flex-wrap:wrap; gap:6px; }
.pc-prod-tag{ font-size:.7em; color:#8a8578; background:#f6f4ee; border-radius:6px; padding:3px 8px; font-weight:600; }

.pc-prod-corrida-line{ font-size:.78em; color:#9a9585; display:flex; align-items:center; gap:6px; }
.pc-prod-corrida-line b{ color:#5c5947; font-weight:600; }

.pc-prod-card-foot{
    display:flex; align-items:center; gap:2px; padding-top:10px; margin-top:2px;
    border-top:1px solid #f1efe8; flex-wrap:wrap;
}
.pc-prod-ghost-btn{
    border:none; background:none; color:#8a8578; font-size:.78em; font-weight:600;
    padding:6px 8px; border-radius:7px; display:inline-flex; align-items:center; gap:5px; cursor:pointer;
    transition:.12s ease;
}
.pc-prod-ghost-btn:hover{ background:#f6f4ee; color:#1f2430; }
.pc-prod-ghost-btn.danger:hover{ color:#c94a4a; background:#FCEAEC; }
.pc-prod-ghost-btn.success:hover{ color:#16A34A; background:#E8F7EE; }
.pc-prod-ghost-btn.warn:hover{ color:#D97706; background:#FDF1E0; }

.pc-btn-ensamblaje{
    margin-left:auto; padding:7px 13px; font-size:.78em; border-radius:8px; border:none;
    background:#1f2430; color:#fff; font-weight:600; display:inline-flex; align-items:center; gap:6px;
    cursor:pointer; transition:background .12s ease;
}
.pc-btn-ensamblaje:hover{ background:#2F6FED; color:#fff; }

.pc-prod-empty{ text-align:center; color:#9a9585; padding:40px 12px; grid-column:1/-1; }


.pc-tk-total-input{
    width:90px; border:none; border-bottom:2px solid transparent; background:transparent;
    font-weight:700; font-size:1.1em; color:var(--pc-blue,#2F6FED); text-align:right;
    font-variant-numeric:tabular-nums;
}
.pc-tk-total-input:not([readonly]){ border-bottom-color:#d97706; }
.pc-tk-total-input:focus{ outline:none; }

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

.pc-ens-step{ display:flex; gap:12px; padding:14px 0; }
.pc-ens-step + .pc-ens-step{ border-top:1px solid #eee7db; }
.pc-ens-step-num{
    width:26px; height:26px; border-radius:50%; flex:0 0 auto;
    background:#152238; color:#fff; font-weight:700; font-size:.8em;
    display:flex; align-items:center; justify-content:center; margin-top:2px;
}
.pc-ens-step-num.alt{ background:#D97706; }
.pc-ens-step-body{ flex:1; min-width:0; }

.pc-merma-lista{ display:flex; flex-direction:column; gap:6px; margin-bottom:10px; }
.pc-merma-lista:empty{ display:none; }
.pc-merma-item{
    display:flex; align-items:center; gap:8px; font-size:.8em;
    background:#FDF1E0; border:1px solid #f0dcae; border-radius:8px; padding:6px 10px;
}
.pc-merma-item .dots{ display:flex; gap:2px; flex:0 0 auto; }
.pc-merma-item .cant{ font-weight:700; color:#8a5a10; flex:0 0 auto; }
.pc-merma-item .nota{ color:#8a8578; flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

.pc-merma-form{ background:#fdfcfa; border:1px dashed #e2ddcd; border-radius:10px; padding:12px; }

@media (max-width:900px){ .pc-stat-row{ grid-template-columns:repeat(2,1fr); } }

/* ---------- Estado visual en las cards de producción ---------- */
.pc-prod-card.estado-ensamblaje{ opacity:.7; }

.pc-prod-card.pc-flash{ animation:pc-flash-bg 1.8s ease; }
@keyframes pc-flash-bg{
    0%{ background:#FFF6DC; box-shadow:0 0 0 2px #F5D98A inset; }
    100%{ background:#fff; box-shadow:none; }
}

@keyframes pc-pulse-blue{
    0%{ box-shadow:0 0 0 0 rgba(11,77,166,.6); }
    70%{ box-shadow:0 0 0 6px rgba(11,77,166,0); }
    100%{ box-shadow:0 0 0 0 rgba(11,77,166,0); }
}

/* ---------- Pestañas de producto (reemplazan los filtros anteriores) ---------- */
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

/* ---------- Chips de color para merma (multi-selección + nota libre) ---------- */
.pc-merma-chip{
    display:inline-flex; align-items:center; gap:5px; padding:5px 10px;
    border:1px solid #e2ddcd; background:#fff; border-radius:999px;
    font-size:.75em; font-weight:600; color:#5c5947; cursor:pointer;
    transition:.12s ease;
}
.pc-merma-chip:hover{ border-color:#d8d4c8; }
.pc-merma-chip.activo{ background:#152238; border-color:#152238; color:#fff; }
.pc-merma-chip.activo .pc-color-dot{ border-color:rgba(255,255,255,.5); }
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

    <!-- Antes había una fila de filtros (texto, operario, máquina, molde,
         color, fechas, estado). Se reemplazó por pestañas de producto:
         al tocar una, se cargan solo los avances de ese producto. El
         toggle "Ver inactivos" es lo único que queda como filtro aparte. -->
    <div class="pc-tabs-toolbar">
        <div class="pc-tabs-row" id="prodProductoTabs"></div>
        <label class="pc-toggle-inactivos" title="Incluir también los avances desactivados">
            <input type="checkbox" id="prodVerInactivos">
            Ver inactivos
        </label>
    </div>

    <div id="gridProducciones">
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

          <!-- Selección en cascada: primero el PRODUCTO, luego (filtrado por
               ese producto) el MOLDE. Antes era un solo select con todas
               las combinaciones "MOLDE — PRODUCTO" mezcladas, difícil de
               ubicar. -->
          <div class="row">
            <div class="col-md-4 mb-2">
                <label class="form-label">Producto *</label>
                <select class="form-select" id="prod_producto_id" required>
                    <option value="">Selecciona un producto...</option>
                </select>
            </div>
            <div class="col-md-4 mb-2">
                <label class="form-label">Molde *</label>
                <select class="form-select" id="prod_molde_id" required disabled>
                    <option value="">Primero selecciona un producto...</option>
                </select>
            </div>
            <div class="col-md-4 mb-2">
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
               comanda. Cada card es un material; al tocarla se agrega (o se
               suma 1) directamente al ticket, usando el stock total del
               material como límite. Ya no se elige lote/proveedor: eso lo
               reparte el sistema automáticamente al guardar. -->
          <div class="mb-1 d-flex justify-content-between align-items-center">
            <label class="form-label mb-0">Materiales consumidos (opcional)</label>
            <span class="form-text mb-0">Si este avance no consume material nuevo (ej. reproceso), deja el ticket vacío.</span>
          </div>

          <div class="pc-mat-layout">
            <div class="pc-mat-panel">
                <div class="pc-mat-panel-head">
                    <h6><i class="fa-solid fa-boxes-stacked"></i> Menú de materiales</h6>
                </div>
                <div class="pc-mat-tabs">
                    <button type="button" class="pc-mat-tab activo" data-tipo="material" onclick="seleccionarTabMaterial('material')">
                        <i class="fa-solid fa-cube"></i> Materiales
                    </button>
                    <button type="button" class="pc-mat-tab" data-tipo="tinte" onclick="seleccionarTabMaterial('tinte')">
                        <i class="fa-solid fa-droplet"></i> Tintes
                    </button>
                </div>
                <div class="pc-mat-search">
                    <input type="text" id="prod_mat_buscar" class="form-control form-control-sm" placeholder="Buscar material...">
                </div>
                <div class="pc-mat-grid" id="prod_materiales_grid">
                    <div class="pc-mat-empty">Cargando materiales...</div>
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
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="formCantidadEnsamblaje">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fa-solid fa-weight-hanging"></i> Cantidad producida</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">

            <div class="pc-ens-step">
                <div class="pc-ens-step-num">1</div>
                <div class="pc-ens-step-body">
                    <label class="form-label mb-1">Cantidad producida (kg) *</label>
                    <input type="number" step="0.0001" min="0.0001" class="form-control"
                           id="cantidad_producida_ensamblaje" placeholder="Ej. 25.5" required autofocus>
                </div>
            </div>

            <div class="pc-ens-step">
                <div class="pc-ens-step-num alt">2</div>
                <div class="pc-ens-step-body">
                    <label class="form-label mb-1">Merma <span class="text-muted fw-normal">(opcional)</span></label>

                    <div id="merma_lista_registrada" class="pc-merma-lista"></div>

                    <div class="pc-merma-form">
                        <div class="d-flex flex-wrap gap-1 mb-2" id="merma_colores_chips"></div>
                        <input type="text" class="form-control form-control-sm mb-2" id="merma_nota"
                               placeholder='Nota opcional (ej. "combinado azul y rojo", "purga")'>
                        <div class="input-group">
                            <input type="number" step="0.0001" min="0.0001" class="form-control"
                                   id="cantidad_merma_kg" placeholder="Kg de merma">
                            <button type="button" class="btn btn-outline-danger" id="btnRegistrarMerma">
                                <i class="fa-solid fa-triangle-exclamation"></i> Registrar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

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
const CONTROLADOR_MOLDES     = 'controllers/clssMoldes.php'; // para el filtro de moldes y el <select> de color
const CONTROLADOR_COLOR      = 'controllers/clssColor.php';  // para el <select> de color
const modalProduccion = new bootstrap.Modal(document.getElementById('modalProduccion'));

let modoEdicionProduccion = false;
let produccionIdActual = 0;
let tsOperario = null;
let materialesProdCache = null; // cache de materiales para las cards
let productosMoldeProdCache = null; // cache de productos (para el 1er select en cascada)
let categoriasMaterialProdCache = null; // cache de categorías de material para el select
let contadorLineaTicket = 0;
let tipoMaterialActivo = 'material'; // 'material' | 'tinte' — pestaña activa del menú

let ticketLineas = []; // [{tempId, material_id, material_nombre, unidad_corto, color, icono,
                        //   disponible, cantidad, comentario}]

// ── Pestañas de producto (reemplazan los filtros de arriba) ─────────────────
let produccionesCache = [];      // último listado recibido del backend
let productoTabActivo = null;    // nombre del producto seleccionado; null = aún sin definir

document.addEventListener('DOMContentLoaded', () => {
    cargarProducciones().catch(err => {
        console.error('Error cargando datos iniciales:', err);
        document.getElementById('gridProducciones').innerHTML =
            `<div class="pc-prod-empty" style="color:red;">Error de conexión con el servidor. Revisa la consola (F12).</div>`;
    });

    document.getElementById('prod_mat_buscar').addEventListener('input', renderGridMateriales);

    // Al cambiar el producto, se recarga el select de moldes filtrado por
    // ese producto (segundo paso de la cascada).
    document.getElementById('prod_producto_id').addEventListener('change', (e) => {
        cargarMoldesDeProducto(e.target.value, null);
    });

    document.getElementById('prodVerInactivos').addEventListener('change', () => cargarProducciones());

    iniciarAutoRefresh();
});


function esTinte(m) {
    return m.color === true || m.color === 't' || m.color === 'true';
}

function seleccionarTabMaterial(tipo) {
    tipoMaterialActivo = tipo;
    document.querySelectorAll('.pc-mat-tab').forEach(btn => {
        btn.classList.toggle('activo', btn.dataset.tipo === tipo);
    });
    renderGridMateriales();
}
function inicializarTomSelectOperario() {
    if (tsOperario) return;
    tsOperario = new TomSelect('#prod_operario_id', {
        valueField: 'id',
        labelField: 'nombre_completo',
        searchField: ['nombre_completo', 'dni'],
        options: [],
        placeholder: 'Buscar por nombre o DNI...',
        render: {
            option: function (data, escape) {
                return `<div>
                    <span>${escape(data.nombre_completo)}</span>
                    ${data.dni ? `<span class="text-muted small"> — DNI ${escape(data.dni)}</span>` : ''}
                    ${data.cargo ? `<div class="text-muted small">${escape(data.cargo)}</div>` : ''}
                </div>`;
            },
            item: function (data, escape) {
                return `<div>${escape(data.nombre_completo)}</div>`;
            }
        }
    });
}
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

function renderListaMermas(p) {
    const cont = document.getElementById('merma_lista_registrada');
    const mermas = p && Array.isArray(p.js_cantidades_merma) ? p.js_cantidades_merma : [];
    if (mermas.length === 0) { cont.innerHTML = ''; return; }

    cont.innerHTML = mermas.map(m => {
        const colores = m.colores || (m.color_nombre ? [{ nombre: m.color_nombre, rgb: m.color_rgb }] : []);
        const dots = colores.map(c => `<span class="pc-color-dot" style="background:${c.rgb || '#ccc'}"></span>`).join('');
        return `<div class="pc-merma-item">
            <span class="dots">${dots}</span>
            <span class="cant">${formatearCantidadProd(m.cantidad)} ${m.unidad_medida || 'KG'}</span>
            <span class="nota">${m.merma || (colores.map(c => c.nombre).join(', ') || 'Sin descripción')}</span>
        </div>`;
    }).join('');
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

function formatearFechaCorta(fechaIso) {
    // Convierte "2026-07-10 14:30:00" a "10/07", para la línea de metadatos
    if (!fechaIso) return '';
    const [fecha] = fechaIso.split(' ');
    if (!fecha) return fechaIso;
    const [, m, d] = fecha.split('-');
    return `${d}/${m}`;
}

// ── Texto de una sola línea con el estado de la corrida (dentro de la card) ─
function estadoCorridaTexto(p) {
    if (p.enviado_ensamblaje) {
        return `Enviado a ensamblaje · <b>${formatearFechaHoraLegible(p.fecha_envio_ensamblaje)}</b>`;
    }
    if (!p.fecha_hora_inicio) {
        return 'Corrida sin iniciar';
    }
    if (!p.fecha_hora_fin) {
        return `Iniciada · <b>${formatearFechaHoraLegible(p.fecha_hora_inicio)}</b>`;
    }
    return `Inicio <b>${formatearFechaHoraLegible(p.fecha_hora_inicio)}</b> — Fin <b>${formatearFechaHoraLegible(p.fecha_hora_fin)}</b>`;
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
function estiloMaterial(material) {
    const nombre = material.nombre || '';
    let hash = 0;
    for (let i = 0; i < nombre.length; i++) hash = (hash * 31 + nombre.charCodeAt(i)) >>> 0;
    const icono = esTinte(material) ? 'fa-droplet' : ICONOS_MATERIAL[hash % ICONOS_MATERIAL.length];

    // Los tintes usan su color real (material.rgb) en vez del color
    // "hasheado" por nombre — se reconocen de un vistazo, igual que en
    // el módulo de Colores.
    if (esTinte(material) && material.rgb) {
        return { color: material.rgb, bg: material.rgb + '22', icono };
    }
    return { ...PALETA_RESINA[hash % PALETA_RESINA.length], icono };
}

// Categorías de material: mismo patrón de cache que antes, se piden una
// sola vez por carga de página y se reutilizan cada vez que se abre el modal.
async function obtenerCategoriasMaterialProd() {
    if (categoriasMaterialProdCache) return categoriasMaterialProdCache;
    const json = await llamarProduccion('BUSCARCATEGORIASMATERIAL');
    categoriasMaterialProdCache = json.success ? json.categorias : [];
    return categoriasMaterialProdCache;
}

// Productos disponibles para el primer select de la cascada (los que
// tienen al menos un molde activo asociado).
async function obtenerProductosMoldeProd() {
    if (productosMoldeProdCache) return productosMoldeProdCache;
    const json = await llamarProduccion('BUSCARPRODUCTOSMOLDE');
    productosMoldeProdCache = json.success ? json.productos : [];
    return productosMoldeProdCache;
}

// Carga el select de moldes filtrado por producto (2do paso de la cascada).
// Si se pasa `seleccion`, intenta preseleccionar ese molde (acepta el valor
// "unico_molde" tipo "7-2" o directamente un molde_id numérico).
async function cargarMoldesDeProducto(productoId, seleccion) {
    const moldeSelect = document.getElementById('prod_molde_id');

    if (!productoId) {
        moldeSelect.innerHTML = '<option value="">Primero selecciona un producto...</option>';
        moldeSelect.disabled = true;
        return;
    }

    moldeSelect.disabled = false;
    moldeSelect.innerHTML = '<option value="">Cargando moldes...</option>';

    const json = await llamarProduccion('BUSCARMOLDESPORPRODUCTO', { producto_id: productoId });
    const moldes = json.success ? json.moldes : [];

    if (moldes.length === 0) {
        moldeSelect.innerHTML = '<option value="">Este producto no tiene moldes asociados</option>';
        return;
    }

    moldeSelect.innerHTML = '<option value="">Selecciona un molde...</option>' +
        moldes.map(m => `<option value="${m.unico_molde}" data-molde-id="${m.molde_id}" data-etiqueta="${m.etiqueta}">${m.molde_nombre}</option>`).join('');

    if (seleccion) {
        const porUnico   = [...moldeSelect.options].find(o => o.value == seleccion);
        const porMoldeId = [...moldeSelect.options].find(o => o.dataset.moldeId == seleccion);
        moldeSelect.value = (porUnico || porMoldeId)?.value ?? '';
    }
}

async function cargarSelectsModal(seleccion = {}) {
    const [operario, maquinas, colores, categorias, productos] = await Promise.all([
        llamarProduccion('BUSCAROPERARIOS'),
        llamarProduccion('BUSCARMAQUINAS'),
        llamarColor('LISTARCOLORES', { texto: '', estado: 'activa' }),
        obtenerCategoriasMaterialProd(),
        obtenerProductosMoldeProd(),
    ]);

    // Operario: ahora usa Tom Select (buscador por nombre o DNI) en vez
    // de un <select> nativo con insertAdjacentHTML.
    inicializarTomSelectOperario();
    tsOperario.clearOptions();
    if (operario.success) operario.operario.forEach(o => {
        tsOperario.addOption({
            id: o.id,
            nombre_completo: o.nombre_completo,
            cargo: o.cargo,
            dni: o.dni,
        });
    });
    if (seleccion.operario_id) {
        tsOperario.setValue(seleccion.operario_id);
    } else {
        tsOperario.clear();
    }

    const maquinaSelect = document.getElementById('prod_maquina_id');
    maquinaSelect.innerHTML = '<option value="">Selecciona...</option>';
    if (maquinas.success) maquinas.maquinas.forEach(m =>
        maquinaSelect.insertAdjacentHTML('beforeend', `<option value="${m.id}">${m.nombre}</option>`));
    if (seleccion.maquina_id) maquinaSelect.value = seleccion.maquina_id;

    const categoriaSelect = document.getElementById('prod_categoria_material_id');
    categoriaSelect.innerHTML = '<option value="">Selecciona...</option>' +
        (categorias || []).map(c => `<option value="${c.id}">${c.nombre}</option>`).join('');
    if (seleccion.categoria_material_id) categoriaSelect.value = seleccion.categoria_material_id;

    const productoSelect = document.getElementById('prod_producto_id');
    productoSelect.innerHTML = '<option value="">Selecciona un producto...</option>' +
        (productos || []).map(p => `<option value="${p.producto_id}">${p.descripcion}</option>`).join('');

    if (seleccion.producto_id) {
        productoSelect.value = seleccion.producto_id;
        await cargarMoldesDeProducto(seleccion.producto_id, seleccion.unico_molde || seleccion.molde_id);
    } else {
        document.getElementById('prod_molde_id').innerHTML = '<option value="">Primero selecciona un producto...</option>';
        document.getElementById('prod_molde_id').disabled = true;
    }

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

// ── Agrupación por producto ("escalera") ──────────────────────────────────
// Agrupa las producciones por el nombre del producto, extraído del campo
// molde_producto que ya guarda cada avance con el formato "MOLDE — PRODUCTO".
// El orden de los grupos respeta el orden en que aparece cada producto en
// la lista ya ordenada por el backend (enviado_ensamblaje asc, id desc).
function agruparProduccionesPorProducto(producciones) {
    const grupos = new Map();
    producciones.forEach(p => {
        const partes = (p.molde_producto || '').split(' — ');
        const nombreProducto = partes.length > 1 ? partes[1].trim() : 'Sin producto asociado';
        if (!grupos.has(nombreProducto)) grupos.set(nombreProducto, []);
        grupos.get(nombreProducto).push(p);
    });
    return grupos;
}

// ── Pestañas de producto (reemplazan la fila de filtros anterior) ────────
// Dibuja una pestaña por cada producto que tenga al menos un avance en el
// listado actual, con su conteo, más una pestaña "Todos" para ver el
// tablero completo agrupado (como antes). Al tocar una pestaña, solo se
// muestran las cards de ese producto.
function renderTabsProducto(grupos) {
    const contenedor = document.getElementById('prodProductoTabs');
    const totalGeneral = [...grupos.values()].reduce((s, items) => s + items.length, 0);

    let html = `
        <button type="button" class="pc-tab-item ${productoTabActivo === 'TODOS' ? 'activo' : ''}" onclick="seleccionarTabProducto('TODOS')">
            <i class="fa-solid fa-grip"></i> Todos <span class="cnt">${totalGeneral}</span>
        </button>`;

    for (const [nombreProducto, items] of grupos) {
        const nombreEscapado = nombreProducto.replace(/'/g, "\\'");
        html += `
            <button type="button" class="pc-tab-item ${productoTabActivo === nombreProducto ? 'activo' : ''}" onclick="seleccionarTabProducto('${nombreEscapado}')">
                <i class="fa-solid fa-layer-group"></i> ${nombreProducto} <span class="cnt">${items.length}</span>
            </button>`;
    }

    contenedor.innerHTML = html;
}

function seleccionarTabProducto(nombre) {
    productoTabActivo = nombre;
    const grupos = agruparProduccionesPorProducto(produccionesCache);
    renderTabsProducto(grupos);
    renderGridProducciones(produccionesCache, false);
}

function tarjetaProduccionHtml(p, nuevosEstados, silencioso) {
    const estado = estadoCorto(p);
    nuevosEstados[p.id] = estado;
    const cambioDeEstado = silencioso && snapshotEstados[p.id] && snapshotEstados[p.id] !== estado;

    const puedeIniciar = !p.deleted_at && !p.fecha_hora_inicio;
    const puedeFinalizar = !p.deleted_at && p.fecha_hora_inicio && !p.fecha_hora_fin;
    const corridaFinalizada = !p.deleted_at && !!p.fecha_hora_fin && !p.enviado_ensamblaje;

    const textoEstado = { sin: 'Sin iniciar', curso: 'En curso', fin: 'Finalizada', ensamblaje: 'En ensamblaje' }[estado];

    // Línea de metadatos: color · operario · máquina · fecha, separados por
    // puntos medios (::after en CSS). Se omite lo que no venga con dato.
    const metaPartes = [];
    if (p.color_nombre) {
        metaPartes.push(`${p.color_rgb ? `<span class="pc-color-dot" style="background:${p.color_rgb}"></span>` : ''}${p.color_nombre}`);
    }
    if (p.operario_nombre) metaPartes.push(p.operario_nombre);
    if (p.maquina_nombre) metaPartes.push(p.maquina_nombre);
    if (p.fecha) metaPartes.push(formatearFechaCorta(p.fecha));

    const tags = [];
    if (p.categoria_material_nombre) tags.push(p.categoria_material_nombre);

    const mermas = Array.isArray(p.js_cantidades_merma) ? p.js_cantidades_merma : [];
    const totalMerma = mermas.reduce((s, m) => s + Number(m.cantidad || 0), 0);
    if (totalMerma > 0) tags.push(`Merma: ${formatearCantidadProd(totalMerma)} kg`);
    if (p.categoria_material_nombre) tags.push(p.categoria_material_nombre);

    return `
    <div class="pc-prod-card estado-${estado} ${p.deleted_at ? 'inactiva' : ''} ${cambioDeEstado ? 'pc-flash' : ''}" id="fila-produccion-${p.id}">
        <div class="pc-prod-card-top">
            <span class="pc-prod-dot"></span>
            <span class="pc-prod-id">#${p.id}</span>
            <span class="pc-prod-estado-txt">${p.deleted_at ? 'Inactivo' : textoEstado}</span>
            <span class="pc-prod-card-spacer"></span>
            <button type="button" class="pc-prod-edit-btn" onclick="abrirModalEditarProduccion(${p.id})" title="Editar">
                <i class="fa-solid fa-pen"></i>
            </button>
        </div>

        <div class="pc-prod-title">${p.molde_nombre ?? '-'}</div>

        <div class="pc-prod-meta">${metaPartes.map(t => `<span>${t}</span>`).join('')}</div>

        <div class="pc-prod-stats">
            <div class="pc-prod-stat">
                <div class="num">${formatearCantidadProd(p.cantidad)}</div>
                <div class="lbl">Kg insertados</div>
            </div>
            <div class="pc-prod-stat">
                <div class="num">${p.items_count}</div>
                <div class="lbl">Material${p.items_count == 1 ? '' : 'es'}</div>
            </div>
        </div>

        ${tags.length ? `<div class="pc-prod-tags">${tags.map(t => `<span class="pc-prod-tag">${t}</span>`).join('')}</div>` : ''}

        <div class="pc-prod-corrida-line"><i class="fa-regular fa-clock"></i> ${estadoCorridaTexto(p)}</div>

        <div class="pc-prod-card-foot">
            ${puedeIniciar
                ? `<button type="button" class="pc-prod-ghost-btn success" onclick="iniciarProduccion(${p.id})" title="Iniciar corrida">
                    <i class="fa-solid fa-play"></i> Iniciar</button>`
                : ''
            }
            ${puedeFinalizar
                ? `<button type="button" class="pc-prod-ghost-btn warn" onclick="finalizarProduccion(${p.id})" title="Finalizar corrida">
                    <i class="fa-solid fa-flag-checkered"></i> Finalizar</button>`
                : ''
            }
            ${!p.deleted_at
                ? `<button type="button" class="pc-prod-ghost-btn danger" onclick="eliminarProduccion(${p.id})" title="Desactivar">
                       <i class="fa-solid fa-trash"></i></button>`
                : `<button type="button" class="pc-prod-ghost-btn" onclick="reactivarProduccion(${p.id})" title="Reactivar">
                       <i class="fa-solid fa-rotate-left"></i></button>`
            }
            ${corridaFinalizada
                ? `<button type="button" class="pc-btn-ensamblaje" onclick="abrirModalCantidadParaEnsamblaje(${p.id})" title="Enviar este avance a ensamblaje">
                    Pasar a ensamblaje <i class="fa-solid fa-arrow-right"></i></button>`
                : ''
            }
        </div>
    </div>`;
}

// Dibuja el tablero de cards según la pestaña de producto activa. Si es
// "TODOS", se ve como antes (secciones agrupadas por producto); si es un
// producto puntual, se ve solo su cuadrícula de avances.
function renderGridProducciones(producciones, silencioso) {
    const grid = document.getElementById('gridProducciones');
    renderStatRow(producciones);

    if (producciones.length === 0) {
        grid.innerHTML = '<div class="pc-prod-empty">No hay registros de producción.</div>';
        snapshotEstados = {};
        return;
    }

    const nuevosEstados = {};
    const grupos = agruparProduccionesPorProducto(producciones);

    let html = '';
    if (productoTabActivo === 'TODOS') {
        for (const [nombreProducto, items] of grupos) {
            html += `
                <div class="pc-prod-group">
                    <div class="pc-prod-group-header">
                        <span class="linea"></span>
                        <span class="texto"><i class="fa-solid fa-layer-group"></i> ${nombreProducto} <span class="pc-prod-group-count">· ${items.length}</span></span>
                        <span class="linea"></span>
                    </div>
                    <div class="pc-prod-grid">
                        ${items.map(p => tarjetaProduccionHtml(p, nuevosEstados, silencioso)).join('')}
                    </div>
                </div>`;
        }
    } else {
        const items = grupos.get(productoTabActivo) || [];
        html = items.length
            ? `<div class="pc-prod-grid">${items.map(p => tarjetaProduccionHtml(p, nuevosEstados, silencioso)).join('')}</div>`
            : '<div class="pc-prod-empty">No hay avances registrados para este producto.</div>';
        // Igual calculamos el estado de todos para que el snapshot no pierda
        // de vista lo que pasa en las pestañas que no se están mostrando.
        producciones.forEach(p => { if (!(p.id in nuevosEstados)) nuevosEstados[p.id] = estadoCorto(p); });
    }

    grid.innerHTML = html;
    snapshotEstados = nuevosEstados;
}

async function cargarProducciones(silencioso = false) {
    const grid = document.getElementById('gridProducciones');
    if (!silencioso) grid.innerHTML = '<div class="pc-prod-empty">Cargando...</div>';

    const verInactivos = document.getElementById('prodVerInactivos').checked;
    const json = await llamarProduccion('LISTARPRODUCCIONES', { estado: verInactivos ? '' : 'activa' });
    if (!json.success) {
        grid.innerHTML = `<div class="pc-prod-empty">${json.message}</div>`;
        return;
    }

    produccionesCache = json.producciones || [];
    const grupos = agruparProduccionesPorProducto(produccionesCache);

    // La pestaña activa por defecto es el PRIMER producto del listado (no
    // "Todos"). Si la pestaña que estaba activa ya no existe en el nuevo
    // listado (se desactivó el único avance de ese producto, por ejemplo),
    // se cae de vuelta al primer producto disponible.
    if (productoTabActivo === null || (productoTabActivo !== 'TODOS' && !grupos.has(productoTabActivo))) {
        const primerProducto = grupos.keys().next().value;
        productoTabActivo = primerProducto ?? 'TODOS';
    }

    renderTabsProducto(grupos);
    renderGridProducciones(produccionesCache, silencioso);
}
// =============================================================================
// MENÚ DE MATERIALES + TICKET (sin selección de lote)
// =============================================================================

async function renderGridMateriales() {
    const grid = document.getElementById('prod_materiales_grid');
    const materiales = await obtenerOpcionesMaterialesProd();
    const filtro = document.getElementById('prod_mat_buscar').value.trim().toLowerCase();

    let visibles = materiales.filter(m => esTinte(m) === (tipoMaterialActivo === 'tinte'));
    if (filtro) visibles = visibles.filter(m => m.nombre.toLowerCase().includes(filtro));

    if (visibles.length === 0) {
        grid.innerHTML = `<div class="pc-mat-empty">No se encontró ningún ${tipoMaterialActivo === 'tinte' ? 'tinte' : 'material'} con ese nombre.</div>`;
        return;
    }

    grid.innerHTML = visibles.map(m => {
        const est = estiloMaterial(m); // <-- antes: estiloMaterial(m.nombre)
        const enTicket = ticketLineas.filter(l => l.material_id == m.id)
            .reduce((s, l) => s + Number(l.cantidad || 0), 0);
        return `
        <button type="button" class="pc-mat-card ${enTicket > 0 ? 'activa' : ''}"
                style="--card-color:${est.color};--card-bg:${est.bg};"
                data-material-id="${m.id}" onclick="seleccionarMaterial(${m.id})" title="Tocar para agregar al ticket">
            ${enTicket > 0 ? `<span class="badge-en-ticket">${formatearCantidadProd(enTicket)}</span>` : ''}
            <span class="pellet"><i class="fa-solid ${est.icono}"></i></span>
            <span class="nombre">${m.nombre}</span>
            <span class="stock">stock: <b>${formatearCantidadProd(m.stock_actual)}</b> ${m.unidad_corto ?? ''}</span>
        </button>`;
    }).join('');
}
// Tocar una card de material la agrega al ticket con cantidad 1 (o, si ya
// estaba en el ticket, le suma 1). No se pregunta por lote/proveedor: el
// backend decide automáticamente de dónde sale el material al guardar.
async function seleccionarMaterial(materialId) {
    const materiales = await obtenerOpcionesMaterialesProd();
    const material = materiales.find(m => m.id == materialId);
    if (!material) return;

    const existente = ticketLineas.find(l => l.material_id == materialId);
    if (existente) {
        cambiarCantidadTicket(existente.tempId, 1);
        return;
    }

    const est = estiloMaterial(material); // <-- antes: estiloMaterial(material.nombre)
    ticketLineas.push({
        tempId: ++contadorLineaTicket,
        material_id: material.id,
        material_nombre: material.nombre,
        unidad_corto: material.unidad_corto,
        color: est.color,
        bg: est.bg,
        icono: est.icono,
        disponible: parseFloat(material.stock_actual),
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
                <div class="lote-info">stock disponible: <b>${formatearCantidadProd(l.disponible)}</b> ${l.unidad_corto ?? ''}</div>
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

    // Los materiales del ticket pueden venir en distintas unidades (Kg, ML,
    // L, UND) — sumarlos todos como si fueran la misma cosa no tiene
    // sentido. Se agrupan por unidad para el desglose; el campo "Kg en
    // total" (que alimenta produccion.cantidad, los kg insertados en
    // máquina) SOLO suma las líneas cuya unidad es Kg. El resto (tintes en
    // ML/L, piezas en UND) queda solo como información en el desglose.
    const subtotalesPorUnidad = {};
    ticketLineas.forEach(l => {
        const u = (l.unidad_corto || '').trim() || '-';
        subtotalesPorUnidad[u] = (subtotalesPorUnidad[u] || 0) + Number(l.cantidad || 0);
    });

    const totalKg = Object.entries(subtotalesPorUnidad)
        .filter(([u]) => u.toLowerCase() === 'kg')
        .reduce((s, [, cant]) => s + cant, 0);

    totalInput.readOnly = true;
    totalInput.value = Math.round(totalKg); // entero, igual que antes

    const desglose = Object.entries(subtotalesPorUnidad)
        .map(([u, cant]) => `${formatearCantidadProd(cant)} ${u}`)
        .join(' · ');

    detalle.textContent = `${ticketLineas.length} material${ticketLineas.length === 1 ? '' : 'es'} en este avance — ${desglose}`;
}

function obtenerDetalleJsonProd() {
    return JSON.stringify(ticketLineas.map(l => ({
        material_id: l.material_id,
        cantidad: l.cantidad,
        comentario: l.comentario,
    })));
}

function limpiarFormularioProduccion() {
    document.getElementById('formProduccion').reset();
    document.getElementById('prod_mat_buscar').value = '';
    document.getElementById('prod_molde_id').innerHTML = '<option value="">Primero selecciona un producto...</option>';
    document.getElementById('prod_molde_id').disabled = true;
    if (tsOperario) tsOperario.clear();
    produccionIdActual = 0;
    ticketLineas = [];
    tipoMaterialActivo = 'material';
    document.querySelectorAll('.pc-mat-tab').forEach(btn => btn.classList.toggle('activo', btn.dataset.tipo === 'material'));
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
    console.log('json', json);
    limpiarFormularioProduccion();
    modoEdicionProduccion = true;
    produccionIdActual = id;

    const p = json.produccion;
    document.getElementById('modalProduccionTitulo').textContent = 'Editar avance #' + id;
    document.getElementById('prod_cantidad').value = p.cantidad;
    document.getElementById('prod_fecha').value = formatearFechaHoraLocal(p.fecha);
    document.getElementById('prod_observaciones').value = p.observaciones ?? '';

    // "unico_molde_producto" tiene el formato "{molde_id}-{producto_id}":
    // de ahí se saca qué producto tenía seleccionado este avance, para
    // preseleccionar el primer paso de la cascada (Producto).
    const partesUnico = (p.unico_molde_producto || '').split('-');
    const productoIdDesdeUnico = partesUnico.length > 1 ? partesUnico[1] : null;

    await cargarSelectsModal({
        operario_id: p.operario_id, maquina_id: p.maquina_id,
        producto_id: productoIdDesdeUnico,
        unico_molde: p.unico_molde_producto,
        color_id: p.color_id,
        categoria_material_id: p.categoria_material_id,
    });
    await renderGridMateriales();

    // El detalle guardado puede tener varias filas para un mismo material
    // (una por lote, por el reparto FIFO). Se agrupan por material_id para
    // mostrar UNA sola línea en el ticket, como el usuario la ve.
    const detalle = json.detalle || [];
    const agregadoPorMaterial = {};
    detalle.forEach(d => {
        if (!agregadoPorMaterial[d.material_id]) {
            agregadoPorMaterial[d.material_id] = {
                material_id: d.material_id,
                material_nombre: d.material_nombre,
                unidad_corto: d.unidad_base_corto,
                cantidad: 0,
                comentario: d.comentario ?? '',
            };
        }
        agregadoPorMaterial[d.material_id].cantidad += parseFloat(d.cantidad);
    });

    const materiales = await obtenerOpcionesMaterialesProd();
    ticketLineas = Object.values(agregadoPorMaterial).map(d => {
        const materialActual = materiales.find(m => m.id == d.material_id);
        const est = estiloMaterial(materialActual || { nombre: d.material_nombre }); // <-- cambio
        const disponibleParaEditar = (materialActual ? parseFloat(materialActual.stock_actual) : 0) + d.cantidad;
        return {
            tempId: ++contadorLineaTicket,
            material_id: d.material_id,
            material_nombre: d.material_nombre,
            unidad_corto: d.unidad_corto,
            color: est.color,
            bg: est.bg,
            icono: est.icono,
            disponible: disponibleParaEditar,
            cantidad: d.cantidad,
            comentario: d.comentario,
        };
    });
    renderTicket();
    renderGridMateriales();

    modalProduccion.show();
}

document.getElementById('formProduccion').addEventListener('submit', async function (e) {
    e.preventDefault();

    const moldeSelect = document.getElementById('prod_molde_id');
    const opcionMolde  = moldeSelect.selectedOptions[0];
    const moldeIdReal  = opcionMolde?.dataset.moldeId || '';
    const uniqueMolde  = moldeSelect.value;                          // ej: "7-2"
    const moldeProducto = opcionMolde?.dataset.etiqueta || '';       // ej: "MOLDE BASTON OVALADO — COLGADOR OVALADO MULTIUSO"

    const params = {
        id: produccionIdActual,
        operario_id: document.getElementById('prod_operario_id').value,
        maquina_id: document.getElementById('prod_maquina_id').value,
        categoria_material_id: document.getElementById('prod_categoria_material_id').value,
        molde_id: moldeIdReal,
        unico_molde: uniqueMolde,
        molde_producto: moldeProducto,
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
let coloresMermaCache = null;       // cache de colores activos, para los chips de merma
let mermaColoresSeleccionados = []; // ids de color marcados en el modal de merma

function abrirModalCantidadParaEnsamblaje(produccionId) {
    produccionIdParaEnsamblaje = produccionId;
    document.getElementById('formCantidadEnsamblaje').reset();

    const p = produccionesCache.find(x => x.id == produccionId);
    renderInfoMermaModal(p);

    modalCantidadEnsamblaje.show();
}

async function obtenerColoresParaMerma() {
    if (coloresMermaCache) return coloresMermaCache;
    const json = await llamarColor('LISTARCOLORES', { texto: '', estado: 'activa' });
    coloresMermaCache = json.success ? json.colores : [];
    return coloresMermaCache;
}

function toggleColorMerma(id) {
    const idx = mermaColoresSeleccionados.indexOf(id);
    if (idx >= 0) mermaColoresSeleccionados.splice(idx, 1);
    else mermaColoresSeleccionados.push(id);
    renderChipsColorMerma();
}

function renderChipsColorMerma() {
    const cont = document.getElementById('merma_colores_chips');
    if (!cont || !cont.dataset.colores) return;
    const colores = JSON.parse(cont.dataset.colores);
    cont.innerHTML = colores.map(c => {
        const activo = mermaColoresSeleccionados.includes(Number(c.id));
        return `<button type="button" class="pc-merma-chip ${activo ? 'activo' : ''}" onclick="toggleColorMerma(${c.id})">
            ${activo ? '<i class="fa-solid fa-check"></i>' : `<span class="pc-color-dot" style="background:${c.rgb || '#ccc'}"></span>`}
            ${c.nombre}
        </button>`;
    }).join('');
}

// El color/tipo de la merma es independiente del color propio del avance
// (puede ser una mezcla al cambiar de molde, purga, etc.). Por defecto se
// pre-marca el color del avance como punto de partida (el caso más común
// es merma de un solo color), pero el operario puede desmarcarlo, sumar
// otros colores, y/o agregar una nota libre.
async function renderInfoMermaModal(p) {
    const cont = document.getElementById('merma_colores_chips');
    const notaInput = document.getElementById('merma_nota');
    const txt = document.getElementById('merma_registrada_txt');
    document.getElementById('cantidad_merma_kg').value = '';
    if (notaInput) notaInput.value = '';
    mermaColoresSeleccionados = [];

    if (!p) { if (cont) cont.innerHTML = ''; txt.textContent = ''; return; }

    const colores = await obtenerColoresParaMerma();
    cont.dataset.colores = JSON.stringify(colores);

    if (p.color_id) mermaColoresSeleccionados = [Number(p.color_id)];
    renderChipsColorMerma();

    const mermas = Array.isArray(p.js_cantidades_merma) ? p.js_cantidades_merma : [];
    const totalMerma = mermas.reduce((s, m) => s + Number(m.cantidad || 0), 0);
    txt.textContent = totalMerma > 0
        ? `Merma ya registrada en este avance: ${formatearCantidadProd(totalMerma)} kg`
        : 'Aún no se registró merma para este avance.';
}
document.getElementById('btnRegistrarMerma').addEventListener('click', async () => {
    const valor = parseFloat(document.getElementById('cantidad_merma_kg').value);
    if (isNaN(valor) || valor <= 0) {
        Swal.fire('Dato inválido', 'Ingresa una cantidad de merma mayor a 0.', 'warning');
        return;
    }

    const nota = document.getElementById('merma_nota').value.trim();
    if (mermaColoresSeleccionados.length === 0 && nota === '') {
        Swal.fire('Dato inválido', 'Selecciona al menos un color o escribe una nota que describa la merma (ej. "combinado", "purga").', 'warning');
        return;
    }

    const json = await llamarProduccion('REGISTRARMERMA', {
        id: produccionIdParaEnsamblaje,
        cantidad_merma: valor,
        colores: JSON.stringify(mermaColoresSeleccionados),
        nota: nota,
    });

    if (!json.success) {
        Swal.fire('Error', json.message, 'error');
        return;
    }

    const p = produccionesCache.find(x => x.id == produccionIdParaEnsamblaje);
    if (p) {
        p.js_cantidades_merma = Array.isArray(p.js_cantidades_merma) ? p.js_cantidades_merma : [];
        p.js_cantidades_merma.push(json.merma);
    }

    // Se deja el modal listo para registrar otra merma distinta en la
    // misma sesión (ej. primero la mezcla de transición, luego la merma
    // pura del nuevo color), volviendo a pre-marcar el color del avance.
    document.getElementById('cantidad_merma_kg').value = '';
    document.getElementById('merma_nota').value = '';
    mermaColoresSeleccionados = p && p.color_id ? [Number(p.color_id)] : [];
    renderChipsColorMerma();

    const mermasActualizadas = p ? p.js_cantidades_merma : [];
    const totalMerma = mermasActualizadas.reduce((s, m) => s + Number(m.cantidad || 0), 0);
    document.getElementById('merma_registrada_txt').textContent =
        `Merma ya registrada en este avance: ${formatearCantidadProd(totalMerma)} kg`;

    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Merma registrada', showConfirmButton: false, timer: 1500 });
});

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