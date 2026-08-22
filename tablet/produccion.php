<?php
session_start();
require __DIR__ . '/../controllers_tablet/clssAuthOperario.php';

if (empty($_SESSION['operario_id'])) {
    header('Location: loginoperarios.php');
    exit;
}

$operarioId     = (int) $_SESSION['operario_id'];
$operarioNombre = $_SESSION['operario_nombre'] ?? 'Operario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Producción · Plásticos Chepito</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/produccion_tablet.css">
</head>
<body>

<header class="pc-op-brand-bar pc-op-brand-bar-inline">
    <div class="pc-op-brand">
        <a href="panel.php" class="pc-op-back" title="Volver al panel"><i class="fa-solid fa-arrow-left"></i></a>
        <img src="../assets/img/logo.png" alt="Plásticos Chepito" class="pc-op-brand-mark">
        <div class="pc-op-brand-text">
            <span class="pc-op-brand-name">Producción</span>
            <span class="pc-op-brand-tag">Operario: <?= htmlspecialchars($operarioNombre) ?></span>
        </div>
    </div>
    <a href="logoutoperario.php" class="pc-op-panel-logout">
        <i class="fa-solid fa-right-from-bracket"></i> Salir
    </a>
</header>

<style>
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
.pc-mat-layout{ display:grid; grid-template-columns:1.35fr 1fr; gap:16px; align-items:start; }
@media (max-width: 900px){ .pc-mat-layout{ grid-template-columns:1fr; } }
.pc-mat-panel, .pc-tk-panel{ border:1px solid #e7e4dd; border-radius:14px; background:#fdfcfa; overflow:hidden; }
.pc-mat-panel-head, .pc-tk-panel-head{ padding:10px 14px; border-bottom:1px solid #eee7db; display:flex; justify-content:space-between; align-items:center; background:#fffefb; }
.pc-mat-panel-head h6, .pc-tk-panel-head h6{ margin:0; font-weight:700; font-size:.95em; }
.pc-mat-search{ padding:10px 12px 0 12px; }
.pc-mat-grid{ display:grid; grid-template-columns:repeat(auto-fill, minmax(140px,1fr)); gap:10px; padding:12px; max-height:420px; overflow-y:auto; }
.pc-mat-card{ position:relative; border:1px solid #eae6da; border-radius:12px; background:#fff; padding:12px 10px 10px 10px; cursor:pointer; text-align:left; transition:transform .12s ease, box-shadow .12s ease, border-color .12s ease; }
.pc-mat-card:active{ transform:scale(0.96); }
.pc-mat-card.activa{ border-color:var(--card-color, #2F6FED); box-shadow:0 0 0 2px var(--card-color, #2F6FED) inset; }
.pc-mat-card .pellet{ width:34px; height:34px; border-radius:9px; display:flex; align-items:center; justify-content:center; background:var(--card-bg,#EAF0FE); color:var(--card-color,#2F6FED); font-size:1em; margin-bottom:8px; }
.pc-mat-card .nombre{ font-weight:600; font-size:.88em; line-height:1.25; display:block; min-height:2.2em; }
.pc-mat-card .stock{ font-size:.75em; color:#8a8578; margin-top:4px; display:block; }
.pc-mat-card .stock b{ color:#4a4636; }
.pc-mat-card .badge-en-ticket{ position:absolute; top:-6px; right:-6px; background:var(--card-color,#2F6FED); color:#fff; font-size:.72em; font-weight:700; border-radius:999px; min-width:20px; height:20px; padding:0 5px; display:flex; align-items:center; justify-content:center; }
.pc-mat-empty{ grid-column:1/-1; text-align:center; color:#9a9585; font-size:.9em; padding:20px 6px; }
.pc-mat-tabs{ display:flex; gap:6px; padding:0 12px 10px 12px; }
.pc-mat-tab{ flex:1; border:1px solid #e2ddcd; background:#fff; border-radius:9px; padding:10px 10px; font-size:.85em; font-weight:600; color:#8a8578; display:flex; align-items:center; justify-content:center; gap:6px; cursor:pointer; transition:.12s ease; }
.pc-mat-tab.activo{ background:#152238; border-color:#152238; color:#fff; }
.pc-tk-list{ list-style:none; margin:0; padding:0; max-height:420px; overflow-y:auto; }
.pc-tk-item{ border-bottom:1px dashed #eee2c8; padding:12px; display:flex; gap:10px; }
.pc-tk-item:last-child{ border-bottom:none; }
.pc-tk-item .pellet-sm{ width:28px; height:28px; border-radius:8px; flex:0 0 auto; display:flex; align-items:center; justify-content:center; background:var(--card-bg,#EAF0FE); color:var(--card-color,#2F6FED); font-size:.85em; margin-top:2px; }
.pc-tk-item .cuerpo{ flex:1; min-width:0; }
.pc-tk-item .nombre{ font-weight:600; font-size:.9em; }
.pc-tk-item .lote-info{ font-size:.76em; color:#8a8578; margin-top:1px; }
.pc-tk-item .lote-info b{ color:#5c5947; }
.pc-tk-item input.comentario{ font-size:.8em; border:none; border-bottom:1px dashed #ddd6c0; width:100%; padding:4px 0; margin-top:6px; background:transparent; }
.pc-tk-item input.comentario:focus{ outline:none; border-color:#d97706; }
.pc-tk-qty{ display:flex; align-items:center; gap:4px; flex:0 0 auto; }
.pc-tk-qty button{ width:34px; height:34px; border:1px solid #e2ddcd; background:#fff; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:.85em; cursor:pointer; }
.pc-tk-qty button:disabled{ opacity:.35; cursor:not-allowed; }
.pc-tk-qty input{ width:60px; text-align:center; border:none; font-variant-numeric:tabular-nums; font-weight:700; font-size:.95em; }
.pc-tk-remove{ border:none; background:none; color:#c94a4a; font-size:1em; align-self:flex-start; padding:6px; }
.pc-tk-empty{ text-align:center; color:#9a9585; font-size:.9em; padding:26px 12px; }
.pc-tk-empty i{ font-size:1.6em; display:block; margin-bottom:6px; opacity:.5; }
.pc-tk-resumen{ display:flex; align-items:center; gap:12px; padding:14px; border-top:1px solid #eee7db; background:linear-gradient(0deg,#fffaf0,#fffefb); }
.pc-tk-resumen-icon{ width:38px; height:38px; border-radius:10px; flex:0 0 auto; background:var(--pc-blue-light,#EAF0FE); color:var(--pc-blue,#2F6FED); display:flex; align-items:center; justify-content:center; font-size:1em; }
.pc-tk-resumen-texto{ display:flex; flex-direction:column; gap:1px; min-width:0; }
.pc-tk-resumen-texto .total{ font-size:1em; color:#3a3730; }
.pc-tk-total-input{ width:100px; border:none; border-bottom:2px solid transparent; background:transparent; font-weight:700; font-size:1.2em; color:var(--pc-blue,#2F6FED); text-align:right; font-variant-numeric:tabular-nums; }
.pc-tk-total-input:not([readonly]){ border-bottom-color:#d97706; }
.pc-tk-total-input:focus{ outline:none; }
.pc-tk-resumen-texto .detalle{ font-size:.78em; color:#8a8578; }

.pc-prod-group{ margin-bottom:26px; }
.pc-prod-group-header{ display:flex; align-items:center; gap:10px; margin:4px 0 12px 0; }
.pc-prod-group-header .linea{ flex:1; height:1px; background:#e7e4dd; }
.pc-prod-group-header .texto{ font-size:.8em; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:#8a5a10; background:#FDF1E0; border:1px solid #f0dcae; border-radius:999px; padding:7px 16px; white-space:nowrap; display:flex; align-items:center; gap:6px; }
.pc-prod-group-count{ font-weight:600; color:#b8834a; opacity:.85; }
.pc-prod-grid{ display:grid; grid-template-columns:repeat(auto-fill, minmax(300px,1fr)); gap:14px; margin-top:4px; }
.pc-prod-card{ border:1px solid #ece9e1; border-radius:14px; background:#fff; padding:18px; display:flex; flex-direction:column; gap:10px; }
.pc-prod-card.inactiva{ opacity:.55; }
.pc-prod-card-top{ display:flex; align-items:center; gap:8px; }
.pc-prod-dot{ width:8px; height:8px; border-radius:50%; flex:0 0 auto; background:#c8c3b4; }
.pc-prod-card.estado-curso .pc-prod-dot{ background:#0B4DA6; animation:pc-pulse-blue 1.6s infinite; }
.pc-prod-card.estado-fin .pc-prod-dot{ background:#16A34A; }
.pc-prod-card.estado-ensamblaje .pc-prod-dot{ background:#8a8578; }
.pc-prod-id{ font-size:.78em; color:#a7a293; font-weight:600; }
.pc-prod-estado-txt{ font-size:.78em; color:#8a8578; font-weight:600; }
.pc-prod-card.estado-curso .pc-prod-estado-txt{ color:#0B4DA6; }
.pc-prod-card.estado-fin .pc-prod-estado-txt{ color:#16A34A; }
.pc-prod-card-spacer{ flex:1; }
.pc-prod-edit-btn{ border:none; background:none; color:#c3beae; padding:8px; font-size:1em; cursor:pointer; border-radius:8px; }
.pc-prod-edit-btn:active{ color:#2F6FED; background:#EAF0FE; }
.pc-prod-title{ font-size:1.15em; font-weight:700; color:#1f2430; line-height:1.25; }
.pc-prod-meta{ font-size:.85em; color:#9a9585; line-height:1.4; }
.pc-prod-meta span:not(:last-child)::after{ content:"·"; margin:0 6px; color:#d8d4c8; }
.pc-prod-stats{ display:flex; gap:26px; }
.pc-prod-stat .num{ font-size:1.35em; font-weight:700; color:#1f2430; line-height:1; }
.pc-prod-stat .lbl{ font-size:.7em; color:#9a9585; margin-top:3px; text-transform:uppercase; letter-spacing:.03em; }
.pc-prod-tags{ display:flex; flex-wrap:wrap; gap:6px; }
.pc-prod-tag{ font-size:.75em; color:#8a8578; background:#f6f4ee; border-radius:6px; padding:4px 9px; font-weight:600; }
.pc-prod-tag.no-ensamblaje{ background:#EEECE6; color:#6b6656; }
.pc-prod-corrida-line{ font-size:.82em; color:#9a9585; display:flex; align-items:center; gap:6px; }
.pc-prod-corrida-line b{ color:#5c5947; font-weight:600; }
.pc-prod-sin-ensamblaje{ font-size:.78em; color:#a7a293; font-style:italic; display:flex; align-items:center; gap:5px; margin-top:-2px; }
.pc-prod-card-foot{ display:flex; align-items:center; gap:6px; padding-top:12px; margin-top:2px; border-top:1px solid #f1efe8; flex-wrap:wrap; }
.pc-prod-ghost-btn{ border:none; background:#f6f4ee; color:#5c5947; font-size:.85em; font-weight:600; padding:10px 12px; border-radius:9px; display:inline-flex; align-items:center; gap:6px; cursor:pointer; }
.pc-prod-ghost-btn.success{ color:#16A34A; background:#E8F7EE; }
.pc-prod-ghost-btn.warn{ color:#D97706; background:#FDF1E0; }
.pc-btn-ensamblaje{ margin-left:auto; padding:10px 16px; font-size:.85em; border-radius:9px; border:none; background:#1f2430; color:#fff; font-weight:700; display:inline-flex; align-items:center; gap:6px; cursor:pointer; }
.pc-prod-empty{ text-align:center; color:#9a9585; padding:50px 12px; grid-column:1/-1; font-size:1.05em; }
.pc-stat-row{ display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:18px; }
.pc-stat-chip{ border:1px solid #e7e4dd; border-radius:14px; background:#fff; padding:14px; display:flex; align-items:center; gap:10px; }
.pc-stat-chip .ico{ width:38px; height:38px; border-radius:10px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:16px; }
.pc-stat-chip .txt .n{ font-size:21px; font-weight:700; line-height:1.15; color:#152238; }
.pc-stat-chip .txt .l{ font-size:11.5px; color:#8a8578; }
.pc-stat-chip.s-gray .ico{ background:#EEECE6; color:#8a8578; }
.pc-stat-chip.s-info .ico{ background:#E3F2FD; color:#0B4DA6; }
.pc-stat-chip.s-success .ico{ background:#E8F7EE; color:#16A34A; }
.pc-stat-chip.s-warning .ico{ background:#FDF1E0; color:#D97706; }
@media (max-width:900px){ .pc-stat-row{ grid-template-columns:repeat(2,1fr); } }

.pc-ens-step{ display:flex; gap:12px; padding:16px 0; }
.pc-ens-step + .pc-ens-step{ border-top:1px solid #eee7db; }
.pc-ens-step-num{ width:30px; height:30px; border-radius:50%; flex:0 0 auto; background:#152238; color:#fff; font-weight:700; font-size:.85em; display:flex; align-items:center; justify-content:center; margin-top:2px; }
.pc-ens-step-num.alt{ background:#D97706; }
.pc-ens-step-body{ flex:1; min-width:0; }
.pc-merma-lista{ display:flex; flex-direction:column; gap:6px; margin-bottom:10px; }
.pc-merma-lista:empty{ display:none; }
.pc-merma-item{ display:flex; align-items:center; gap:8px; font-size:.85em; background:#FDF1E0; border:1px solid #f0dcae; border-radius:9px; padding:8px 12px; }
.pc-merma-item .dots{ display:flex; gap:2px; flex:0 0 auto; }
.pc-merma-item .cant{ font-weight:700; color:#8a5a10; flex:0 0 auto; }
.pc-merma-item .nota{ color:#8a8578; flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.pc-merma-form{ background:#fdfcfa; border:1px dashed #e2ddcd; border-radius:10px; padding:14px; }
.pc-merma-chip{ display:inline-flex; align-items:center; gap:5px; padding:8px 12px; border:1px solid #e2ddcd; background:#fff; border-radius:999px; font-size:.82em; font-weight:600; color:#5c5947; cursor:pointer; }
.pc-merma-chip.activo{ background:#152238; border-color:#152238; color:#fff; }
.pc-merma-chip.activo .pc-color-dot{ border-color:rgba(255,255,255,.5); }

.pc-prod-card.pc-flash{ animation:pc-flash-bg 1.8s ease; }
@keyframes pc-flash-bg{ 0%{ background:#FFF6DC; box-shadow:0 0 0 2px #F5D98A inset; } 100%{ background:#fff; box-shadow:none; } }
@keyframes pc-pulse-blue{ 0%{ box-shadow:0 0 0 0 rgba(11,77,166,.6); } 70%{ box-shadow:0 0 0 6px rgba(11,77,166,0); } 100%{ box-shadow:0 0 0 0 rgba(11,77,166,0); } }

.pc-tabs-toolbar{ display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; border-bottom:1px solid #e7e4dd; margin-bottom:18px; }
.pc-tabs-row{ display:flex; align-items:center; gap:20px; flex-wrap:wrap; row-gap:6px; }
.pc-tab-item{ display:flex; align-items:center; gap:8px; padding:12px 4px 14px 4px; border:none; background:none; cursor:pointer; font-size:1em; font-weight:600; color:#8a8578; border-bottom:2px solid transparent; white-space:nowrap; }
.pc-tab-item.activo{ color:#152238; border-bottom-color:#2F6FED; }
.pc-tab-item .cnt{ background:#EEECE6; color:#5c5947; font-size:.78em; font-weight:700; border-radius:999px; padding:3px 9px; min-width:20px; text-align:center; }
.pc-tab-item.activo .cnt{ background:#152238; color:#fff; }
</style>

<div class="pc-card" style="margin:20px;">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Producción</h2>
        <button class="pc-btn pc-btn-primary pc-btn-lg" onclick="abrirModalCrearProduccion()">
            <i class="fa-solid fa-plus"></i> Registrar producción
        </button>
    </div>
    <br>
    <div class="pc-stat-row" id="statRowProduccion"></div>

    <div class="pc-tabs-toolbar">
        <div class="pc-tabs-row" id="prodProductoTabs"></div>
    </div>

    <div id="gridProducciones">
        <div class="pc-prod-empty">Cargando...</div>
    </div>
</div>

<!-- Modal Crear/Editar -->
<div class="modal fade" id="modalProduccion" tabindex="-1">
  <div class="modal-dialog modal-fullscreen-lg-down modal-xl">
    <div class="modal-content">
      <form id="formProduccion">
        <div class="modal-header">
            <h5 class="modal-title" id="modalProduccionTitulo">Registrar producción</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">

          <div class="row">
            <div class="col-md-3 mb-3">
                <label class="form-label">Operario</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($operarioNombre) ?>" disabled>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Máquina</label>
                <select class="form-select form-select-lg" id="prod_maquina_id">
                    <option value="">Selecciona...</option>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Categoría de material</label>
                <select class="form-select form-select-lg" id="prod_categoria_material_id">
                    <option value="">Selecciona...</option>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Sucursal</label>
                <select class="form-select form-select-lg" id="prod_sucursal_id">
                    <option value="">Selecciona...</option>
                </select>
            </div>
        </div>

          <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Producto *</label>
                <select class="form-select form-select-lg" id="prod_producto_id" required>
                    <option value="">Selecciona un producto...</option>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Molde *</label>
                <select class="form-select form-select-lg" id="prod_molde_id" required disabled>
                    <option value="">Primero selecciona un producto...</option>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Color *</label>
                <select class="form-select form-select-lg" id="prod_color_id" required>
                    <option value="">Selecciona un color...</option>
                </select>
            </div>
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Fecha de registro</label>
                <input type="datetime-local" class="form-control form-control-lg" id="prod_fecha">
            </div>
            <div class="col-md-8 mb-3">
                <label class="form-label">Observaciones</label>
                <input type="text" class="form-control form-control-lg" id="prod_observaciones" placeholder="Opcional">
            </div>
        </div>

          <hr>

          <div class="mb-2 d-flex justify-content-between align-items-center flex-wrap gap-1">
            <label class="form-label mb-0">Materiales consumidos (opcional)</label>
            <span class="form-text mb-0">Si este avance no consume material nuevo, deja el ticket vacío.</span>
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
                    <input type="text" id="prod_mat_buscar" class="form-control form-control-lg" placeholder="Buscar material...">
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
                    <input type="number" step="1" min="1" id="prod_cantidad" class="pc-tk-total-input" required> Kg en total
                    </span>
                    <span class="detalle" id="prod_ticket_total_detalle">0 material(es) en este avance</span>
                </div>
            </div>
            </div>
          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary btn-lg">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Cantidad producida / merma -->
<div class="modal fade" id="modalCantidadEnsamblaje" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
    <div class="modal-content">
      <form id="formCantidadEnsamblaje">
        <div class="modal-header">
            <h5 class="modal-title" id="tituloModalCantidadEnsamblaje"><i class="fa-solid fa-weight-hanging"></i> Cantidad producida</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">

            <div class="pc-ens-step" id="pasoCantidadProducida">
                <div class="pc-ens-step-num">1</div>
                <div class="pc-ens-step-body">
                <label class="form-label mb-1" id="lbl_cantidad_producida">Cantidad producida (kg) *</label>
                    <input type="number" step="0.0001" min="0.0001" class="form-control form-control-lg"
                           id="cantidad_producida_ensamblaje" placeholder="Ej. 25.5" required autofocus>
                </div>
            </div>

            <div class="pc-ens-step">
                <div class="pc-ens-step-num alt">2</div>
                <div class="pc-ens-step-body">
                    <label class="form-label mb-1">Merma <span class="text-muted fw-normal">(opcional)</span></label>

                    <div id="merma_lista_registrada" class="pc-merma-lista"></div>

                    <div class="pc-merma-form">
                        <div class="d-flex flex-wrap gap-2 mb-2" id="merma_colores_chips"></div>
                        <input type="text" class="form-control form-control-lg mb-2" id="merma_nota"
                               placeholder='Nota opcional (ej. "combinado azul y rojo", "purga")'>
                        <div class="input-group input-group-lg">
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
          <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary btn-lg" id="btnSubmitCantidadEnsamblaje">Continuar <i class="fa-solid fa-arrow-right"></i></button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const OPERARIO_ID     = <?= json_encode($operarioId) ?>;
const OPERARIO_NOMBRE = <?= json_encode($operarioNombre) ?>;

const CONTROLADOR_PRODUCCION = '../controllers/clssProduccion.php';
const CONTROLADOR_MOLDES     = '../controllers/clssMoldes.php';
const CONTROLADOR_COLOR      = '../controllers/clssColor.php';
const CONTROLADOR_SUCURSAL   = '../controllers/clssSucursal.php';
const modalProduccion = new bootstrap.Modal(document.getElementById('modalProduccion'));

let modoEdicionProduccion = false;
let produccionIdActual = 0;
let materialesProdCache = null;
let productosMoldeProdCache = null;
let categoriasMaterialProdCache = null;
let contadorLineaTicket = 0;
let tipoMaterialActivo = 'material';
let ticketLineas = [];

let produccionesCache = [];
let productoTabActivo = null;

document.addEventListener('DOMContentLoaded', () => {
    cargarProducciones().catch(err => {
        console.error('Error cargando datos iniciales:', err);
        document.getElementById('gridProducciones').innerHTML =
            `<div class="pc-prod-empty" style="color:red;">Error de conexión con el servidor.</div>`;
    });

    document.getElementById('prod_mat_buscar').addEventListener('input', renderGridMateriales);

    document.getElementById('prod_producto_id').addEventListener('change', (e) => {
        cargarMoldesDeProducto(e.target.value, null);
    });

    iniciarAutoRefresh();
});

function esTinte(m) { return m.color === true || m.color === 't' || m.color === 'true'; }

function seleccionarTabMaterial(tipo) {
    tipoMaterialActivo = tipo;
    document.querySelectorAll('.pc-mat-tab').forEach(btn => {
        btn.classList.toggle('activo', btn.dataset.tipo === tipo);
    });
    renderGridMateriales();
}

const POLL_INTERVAL_MS = 8000;
let pollTimer = null;
let snapshotEstados = {};

function iniciarAutoRefresh() {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(() => {
        if (document.hidden) return;
        if (modalProduccion._element.classList.contains('show')) return;
        cargarProducciones(true);
    }, POLL_INTERVAL_MS);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) cargarProducciones(true);
    });

    document.getElementById('modalProduccion').addEventListener('hidden.bs.modal', () => {
        cargarProducciones(true);
    });
}

async function llamarSucursal(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_SUCURSAL, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
    return resp.json();
}

let sucursalesProdCache = null;
async function obtenerSucursalesProd() {
    if (sucursalesProdCache) return sucursalesProdCache;
    const json = await llamarSucursal('LISTARSUCURSALES', { visibilidad: 'activas' });
    sucursalesProdCache = json.success ? json.sucursales : [];
    return sucursalesProdCache;
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
        <div class="pc-stat-chip s-gray"><div class="ico"><i class="fa-solid fa-hourglass-half"></i></div><div class="txt"><div class="n">${sinIniciar}</div><div class="l">Sin iniciar</div></div></div>
        <div class="pc-stat-chip s-info"><div class="ico"><i class="fa-solid fa-gear"></i></div><div class="txt"><div class="n">${enCurso}</div><div class="l">En curso</div></div></div>
        <div class="pc-stat-chip s-success"><div class="ico"><i class="fa-solid fa-flag-checkered"></i></div><div class="txt"><div class="n">${finalizadas}</div><div class="l">Finalizadas</div></div></div>
        <div class="pc-stat-chip s-warning"><div class="ico"><i class="fa-solid fa-weight-hanging"></i></div><div class="txt"><div class="n">${formatearCantidadProd(kgHoy)}</div><div class="l">Kg hoy</div></div></div>
    `;
}

async function llamarProduccion(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_PRODUCCION, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
    const texto = await resp.text();
    try { return JSON.parse(texto); }
    catch (e) {
        console.error(`Respuesta no es JSON válido para accion=${accion}:`, texto);
        throw new Error(`El servidor no devolvió JSON válido (accion=${accion}).`);
    }
}

async function llamarMoldes(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_MOLDES, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
    return resp.json();
}

async function llamarColor(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_COLOR, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
    return resp.json();
}

function renderListaMermas(p) {
    const cont = document.getElementById('merma_lista_registrada');
    const mermas = p && Array.isArray(p.js_cantidades_merma) ? p.js_cantidades_merma : [];
    if (mermas.length === 0) { cont.innerHTML = ''; return; }
    cont.innerHTML = mermas.map(m => {
        const colores = m.colores || (m.color_nombre ? [{ nombre: m.color_nombre, rgb: m.color_rgb }] : []);
        const dots = colores.map(c => `<span class="pc-color-dot" style="background:${c.rgb || '#ccc'}"></span>`).join('');
        return `<div class="pc-merma-item"><span class="dots">${dots}</span><span class="cant">${formatearCantidadProd(m.cantidad)} ${m.unidad_medida || 'KG'}</span><span class="nota">${m.merma || (colores.map(c => c.nombre).join(', ') || 'Sin descripción')}</span></div>`;
    }).join('');
}
function formatearCantidadProd(n) { return Number(n ?? 0).toLocaleString('es-PE', { maximumFractionDigits: 4 }); }

function unidadEtapa(p, campo) {
    const item = p && p.item ? p.item : null;
    if (item && item[campo]) return String(item[campo]).trim();
    if (campo === 'salida_merma' && item && item['salida_produccion']) return String(item['salida_produccion']).trim();
    return 'kg';
}
function esUnidadEntera(unidad) { return (unidad || '').toLowerCase() !== 'kg'; }

function necesitaEnsamblaje(p) {
    const item = p && p.item ? p.item : null;
    if (!item || item.necesita_ensamblaje === undefined || item.necesita_ensamblaje === null) return true;
    return String(item.necesita_ensamblaje).trim().toLowerCase() !== 'no';
}

function aplicarUnidadesEtapaModal(p) {
    const unidadProduccion = unidadEtapa(p, 'salida_produccion');
    const unidadMerma = unidadEtapa(p, 'salida_merma');
    const lbl = document.getElementById('lbl_cantidad_producida');
    const inputProducida = document.getElementById('cantidad_producida_ensamblaje');
    lbl.textContent = `Cantidad producida (${unidadProduccion}) *`;
    inputProducida.step = esUnidadEntera(unidadProduccion) ? '1' : '0.0001';
    inputProducida.min = esUnidadEntera(unidadProduccion) ? '1' : '0.0001';
    inputProducida.placeholder = esUnidadEntera(unidadProduccion) ? 'Ej. 120' : 'Ej. 25.5';
    inputProducida.dataset.unidad = unidadProduccion;

    const inputMerma = document.getElementById('cantidad_merma_kg');
    inputMerma.placeholder = `${unidadMerma} de merma`;
    inputMerma.step = esUnidadEntera(unidadMerma) ? '1' : '0.0001';
    inputMerma.min = esUnidadEntera(unidadMerma) ? '1' : '0.0001';
    inputMerma.dataset.unidad = unidadMerma;

    document.getElementById('btnRegistrarMerma').innerHTML = `<i class="fa-solid fa-triangle-exclamation"></i> Registrar (${unidadMerma})`;
}

function formatearFechaHoraLocal(fechaIso) {
    if (!fechaIso) return '';
    return fechaIso.replace(' ', 'T').substring(0, 16);
}
function formatearFechaHoraLegible(fechaIso) {
    if (!fechaIso) return '';
    const [fecha, hora] = fechaIso.split(' ');
    if (!fecha) return fechaIso;
    const [y, m, d] = fecha.split('-');
    return `${d}/${m}/${y}${hora ? ' ' + hora.substring(0, 5) : ''}`;
}
function formatearFechaCorta(fechaIso) {
    if (!fechaIso) return '';
    const [fecha] = fechaIso.split(' ');
    if (!fecha) return fechaIso;
    const [, m, d] = fecha.split('-');
    return `${d}/${m}`;
}

function estadoCorridaTexto(p) {
    if (p.enviado_ensamblaje) {
        const etapa = necesitaEnsamblaje(p) ? 'ensamblaje' : 'empaquetado';
        return `Enviado a ${etapa} · <b>${formatearFechaHoraLegible(p.fecha_envio_ensamblaje)}</b>`;
    }
    if (!p.fecha_hora_inicio) return 'Corrida sin iniciar';
    if (!p.fecha_hora_fin) return `Iniciada · <b>${formatearFechaHoraLegible(p.fecha_hora_inicio)}</b>`;
    return `Inicio <b>${formatearFechaHoraLegible(p.fecha_hora_inicio)}</b> — Fin <b>${formatearFechaHoraLegible(p.fecha_hora_fin)}</b>`;
}

const PALETA_RESINA = [
    { color: '#2F6FED', bg: '#EAF0FE' }, { color: '#E23744', bg: '#FCEAEC' },
    { color: '#16A34A', bg: '#E8F7EE' }, { color: '#D97706', bg: '#FDF1E0' },
    { color: '#7C3AED', bg: '#F1EAFD' }, { color: '#0E9488', bg: '#E2F5F3' },
];
const ICONOS_MATERIAL = ['fa-cube', 'fa-flask', 'fa-layer-group', 'fa-industry', 'fa-vial', 'fa-box-open', 'fa-recycle', 'fa-weight-hanging'];
function estiloMaterial(material) {
    const nombre = material.nombre || '';
    let hash = 0;
    for (let i = 0; i < nombre.length; i++) hash = (hash * 31 + nombre.charCodeAt(i)) >>> 0;
    const icono = esTinte(material) ? 'fa-droplet' : ICONOS_MATERIAL[hash % ICONOS_MATERIAL.length];
    if (esTinte(material) && material.rgb) return { color: material.rgb, bg: material.rgb + '22', icono };
    return { ...PALETA_RESINA[hash % PALETA_RESINA.length], icono };
}

async function obtenerCategoriasMaterialProd() {
    if (categoriasMaterialProdCache) return categoriasMaterialProdCache;
    const json = await llamarProduccion('BUSCARCATEGORIASMATERIAL');
    categoriasMaterialProdCache = json.success ? json.categorias : [];
    return categoriasMaterialProdCache;
}

async function obtenerProductosMoldeProd() {
    if (productosMoldeProdCache) return productosMoldeProdCache;
    const json = await llamarProduccion('BUSCARPRODUCTOSMOLDE');
    productosMoldeProdCache = json.success ? json.productos : [];
    return productosMoldeProdCache;
}

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
    const [maquinas, colores, categorias, productos, sucursales] = await Promise.all([
        llamarProduccion('BUSCARMAQUINAS'),
        llamarColor('LISTARCOLORES', { texto: '', estado: 'activa' }),
        obtenerCategoriasMaterialProd(),
        obtenerProductosMoldeProd(),
        obtenerSucursalesProd(),
    ]);

    const sucursalSelect = document.getElementById('prod_sucursal_id');
    sucursalSelect.innerHTML = '<option value="">Selecciona...</option>' +
        (sucursales || []).map(s => `<option value="${s.id}">${s.nombre}</option>`).join('');
    if (seleccion.sucursal_id) sucursalSelect.value = seleccion.sucursal_id;

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

function agruparProduccionesPorProducto(producciones) {
    const grupos = new Map();
    producciones.forEach(p => {
        const nombreProducto = p.producto_descripcion || 'Sin producto asociado';
        if (!grupos.has(nombreProducto)) grupos.set(nombreProducto, []);
        grupos.get(nombreProducto).push(p);
    });
    return grupos;
}

function renderTabsProducto(grupos) {
    const contenedor = document.getElementById('prodProductoTabs');
    const totalGeneral = [...grupos.values()].reduce((s, items) => s + items.length, 0);
    let html = `<button type="button" class="pc-tab-item ${productoTabActivo === 'TODOS' ? 'activo' : ''}" onclick="seleccionarTabProducto('TODOS')"><i class="fa-solid fa-grip"></i> Todos <span class="cnt">${totalGeneral}</span></button>`;
    for (const [nombreProducto, items] of grupos) {
        const nombreEscapado = nombreProducto.replace(/'/g, "\\'");
        html += `<button type="button" class="pc-tab-item ${productoTabActivo === nombreProducto ? 'activo' : ''}" onclick="seleccionarTabProducto('${nombreEscapado}')"><i class="fa-solid fa-layer-group"></i> ${nombreProducto} <span class="cnt">${items.length}</span></button>`;
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

    const requiereEnsamblaje = necesitaEnsamblaje(p);
    const etapaTexto = requiereEnsamblaje ? 'ensamblaje' : 'empaquetado';
    const mostrarBotonAvanzar = corridaFinalizada;

    const textoEstadoMap = { sin: 'Sin iniciar', curso: 'En curso', fin: 'Finalizada', ensamblaje: requiereEnsamblaje ? 'En ensamblaje' : 'En empaquetado' };
    const textoEstado = textoEstadoMap[estado];

    const metaPartes = [];
    if (p.color_nombre) metaPartes.push(`${p.color_rgb ? `<span class="pc-color-dot" style="background:${p.color_rgb}"></span>` : ''}${p.color_nombre}`);
    if (p.maquina_nombre) metaPartes.push(p.maquina_nombre);
    if (p.sucursal_nombre) metaPartes.push(p.sucursal_nombre);
    if (p.fecha) metaPartes.push(formatearFechaCorta(p.fecha));

    const tags = [];
    if (p.categoria_material_nombre) tags.push(p.categoria_material_nombre);
    const mermas = Array.isArray(p.js_cantidades_merma) ? p.js_cantidades_merma : [];
    const totalMerma = mermas.reduce((s, m) => s + Number(m.cantidad || 0), 0);
    if (totalMerma > 0) tags.push(`Merma: ${formatearCantidadProd(totalMerma)} kg`);
    if (!requiereEnsamblaje && !p.enviado_ensamblaje) tags.push({ texto: 'Va directo a empaquetado', clase: 'no-ensamblaje' });

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
            <div class="pc-prod-stat"><div class="num">${formatearCantidadProd(p.cantidad)}</div><div class="lbl">Kg insertados</div></div>
            <div class="pc-prod-stat"><div class="num">${p.items_count}</div><div class="lbl">Material${p.items_count == 1 ? '' : 'es'}</div></div>
        </div>
        ${tags.length ? `<div class="pc-prod-tags">${tags.map(t => typeof t === 'string' ? `<span class="pc-prod-tag">${t}</span>` : `<span class="pc-prod-tag ${t.clase}">${t.texto}</span>`).join('')}</div>` : ''}
        <div class="pc-prod-corrida-line"><i class="fa-regular fa-clock"></i> ${estadoCorridaTexto(p)}</div>
        ${!requiereEnsamblaje ? `<div class="pc-prod-sin-ensamblaje"><i class="fa-solid fa-circle-info"></i> Este molde no pasa por ensamblaje</div>` : ''}
        <div class="pc-prod-card-foot">
            ${puedeIniciar ? `<button type="button" class="pc-prod-ghost-btn success" onclick="iniciarProduccion(${p.id})"><i class="fa-solid fa-play"></i> Iniciar</button>` : ''}
            ${puedeFinalizar ? `<button type="button" class="pc-prod-ghost-btn warn" onclick="finalizarProduccion(${p.id})"><i class="fa-solid fa-flag-checkered"></i> Finalizar</button>` : ''}
            ${mostrarBotonAvanzar ? `<button type="button" class="pc-btn-ensamblaje" onclick="abrirModalCantidadParaEnsamblaje(${p.id})">Pasar a ${etapaTexto} <i class="fa-solid fa-arrow-right"></i></button>` : ''}
        </div>
    </div>`;
}

function renderGridProducciones(producciones, silencioso) {
    const grid = document.getElementById('gridProducciones');
    renderStatRow(producciones);

    if (producciones.length === 0) {
        grid.innerHTML = '<div class="pc-prod-empty">No tienes avances de producción registrados todavía.</div>';
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
                    <div class="pc-prod-grid">${items.map(p => tarjetaProduccionHtml(p, nuevosEstados, silencioso)).join('')}</div>
                </div>`;
        }
    } else {
        const items = grupos.get(productoTabActivo) || [];
        html = items.length
            ? `<div class="pc-prod-grid">${items.map(p => tarjetaProduccionHtml(p, nuevosEstados, silencioso)).join('')}</div>`
            : '<div class="pc-prod-empty">No hay avances registrados para este producto.</div>';
        producciones.forEach(p => { if (!(p.id in nuevosEstados)) nuevosEstados[p.id] = estadoCorto(p); });
    }

    grid.innerHTML = html;
    snapshotEstados = nuevosEstados;
}

async function cargarProducciones(silencioso = false) {
    const grid = document.getElementById('gridProducciones');
    if (!silencioso) grid.innerHTML = '<div class="pc-prod-empty">Cargando...</div>';

    const json = await llamarProduccion('LISTARPRODUCCIONES', { estado: 'activa', operario_id: OPERARIO_ID });
    if (!json.success) {
        grid.innerHTML = `<div class="pc-prod-empty">${json.message}</div>`;
        return;
    }

    produccionesCache = json.producciones || [];
    const grupos = agruparProduccionesPorProducto(produccionesCache);

    if (productoTabActivo === null || (productoTabActivo !== 'TODOS' && !grupos.has(productoTabActivo))) {
        const primerProducto = grupos.keys().next().value;
        productoTabActivo = primerProducto ?? 'TODOS';
    }

    renderTabsProducto(grupos);
    renderGridProducciones(produccionesCache, silencioso);
}

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
        const est = estiloMaterial(m);
        const enTicket = ticketLineas.filter(l => l.material_id == m.id).reduce((s, l) => s + Number(l.cantidad || 0), 0);
        return `
        <button type="button" class="pc-mat-card ${enTicket > 0 ? 'activa' : ''}" style="--card-color:${est.color};--card-bg:${est.bg};" data-material-id="${m.id}" onclick="seleccionarMaterial(${m.id})">
            ${enTicket > 0 ? `<span class="badge-en-ticket">${formatearCantidadProd(enTicket)}</span>` : ''}
            <span class="pellet"><i class="fa-solid ${est.icono}"></i></span>
            <span class="nombre">${m.nombre}</span>
            <span class="stock">stock: <b>${formatearCantidadProd(m.stock_actual)}</b> ${m.unidad_corto ?? ''}</span>
        </button>`;
    }).join('');
}

async function seleccionarMaterial(materialId) {
    const materiales = await obtenerOpcionesMaterialesProd();
    const material = materiales.find(m => m.id == materialId);
    if (!material) return;

    const existente = ticketLineas.find(l => l.material_id == materialId);
    if (existente) { cambiarCantidadTicket(existente.tempId, 1); return; }

    const est = estiloMaterial(material);
    ticketLineas.push({
        tempId: ++contadorLineaTicket, material_id: material.id, material_nombre: material.nombre,
        unidad_corto: material.unidad_corto, color: est.color, bg: est.bg, icono: est.icono,
        disponible: parseFloat(material.stock_actual), cantidad: 1, comentario: '',
    });
    renderTicket();
    renderGridMateriales();
}

function cambiarCantidadTicket(tempId, delta) {
    const linea = ticketLineas.find(l => l.tempId === tempId);
    if (!linea) return;
    const nueva = Math.round((linea.cantidad + delta) * 10000) / 10000;
    if (nueva < 0.0001) return;
    if (nueva > linea.disponible + 0.0001) return;
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
                <input type="text" class="comentario" placeholder="Comentario opcional" value="${l.comentario ?? ''}" onchange="fijarComentarioTicket(${l.tempId}, this.value)">
            </div>
            <div class="pc-tk-qty">
                <button type="button" onclick="cambiarCantidadTicket(${l.tempId}, -1)"><i class="fa-solid fa-minus"></i></button>
                <input type="number" step="0.0001" min="0.0001" value="${l.cantidad}" onchange="fijarCantidadTicket(${l.tempId}, this.value)">
                <button type="button" onclick="cambiarCantidadTicket(${l.tempId}, 1)" ${l.cantidad + 1 > l.disponible + 0.0001 ? 'disabled' : ''}><i class="fa-solid fa-plus"></i></button>
            </div>
            <button type="button" class="pc-tk-remove" onclick="quitarLineaTicket(${l.tempId})"><i class="fa-solid fa-xmark"></i></button>
        </li>
    `).join('');

    const subtotalesPorUnidad = {};
    ticketLineas.forEach(l => {
        const u = (l.unidad_corto || '').trim() || '-';
        subtotalesPorUnidad[u] = (subtotalesPorUnidad[u] || 0) + Number(l.cantidad || 0);
    });

    const totalKg = Object.entries(subtotalesPorUnidad)
        .filter(([u]) => u.toLowerCase() === 'kg')
        .reduce((s, [, cant]) => s + cant, 0);

    totalInput.readOnly = true;
    totalInput.value = Math.round(totalKg);

    const desglose = Object.entries(subtotalesPorUnidad).map(([u, cant]) => `${formatearCantidadProd(cant)} ${u}`).join(' · ');
    detalle.textContent = `${ticketLineas.length} material${ticketLineas.length === 1 ? '' : 'es'} en este avance — ${desglose}`;
}

function obtenerDetalleJsonProd() {
    return JSON.stringify(ticketLineas.map(l => ({ material_id: l.material_id, cantidad: l.cantidad, comentario: l.comentario })));
}

function limpiarFormularioProduccion() {
    document.getElementById('formProduccion').reset();
    document.getElementById('prod_mat_buscar').value = '';
    document.getElementById('prod_molde_id').innerHTML = '<option value="">Primero selecciona un producto...</option>';
    document.getElementById('prod_molde_id').disabled = true;
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

    const partesUnico = (p.unico_molde_producto || '').split('-');
    const productoIdDesdeUnico = partesUnico.length > 1 ? partesUnico[1] : null;

    await cargarSelectsModal({
        maquina_id: p.maquina_id, producto_id: productoIdDesdeUnico,
        unico_molde: p.unico_molde_producto, color_id: p.color_id,
        categoria_material_id: p.categoria_material_id, sucursal_id: p.sucursal_id,
    });
    await renderGridMateriales();

    const detalle = json.detalle || [];
    const agregadoPorMaterial = {};
    detalle.forEach(d => {
        if (!agregadoPorMaterial[d.material_id]) {
            agregadoPorMaterial[d.material_id] = { material_id: d.material_id, material_nombre: d.material_nombre, unidad_corto: d.unidad_base_corto, cantidad: 0, comentario: d.comentario ?? '' };
        }
        agregadoPorMaterial[d.material_id].cantidad += parseFloat(d.cantidad);
    });

    const materiales = await obtenerOpcionesMaterialesProd();
    ticketLineas = Object.values(agregadoPorMaterial).map(d => {
        const materialActual = materiales.find(m => m.id == d.material_id);
        const est = estiloMaterial(materialActual || { nombre: d.material_nombre });
        const disponibleParaEditar = (materialActual ? parseFloat(materialActual.stock_actual) : 0) + d.cantidad;
        return { tempId: ++contadorLineaTicket, material_id: d.material_id, material_nombre: d.material_nombre, unidad_corto: d.unidad_corto, color: est.color, bg: est.bg, icono: est.icono, disponible: disponibleParaEditar, cantidad: d.cantidad, comentario: d.comentario };
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
    const uniqueMolde  = moldeSelect.value;
    const moldeProducto = opcionMolde?.dataset.etiqueta || '';

    const params = {
        id: produccionIdActual,
        operario_id: OPERARIO_ID,
        maquina_id: document.getElementById('prod_maquina_id').value,
        categoria_material_id: document.getElementById('prod_categoria_material_id').value,
        sucursal_id: document.getElementById('prod_sucursal_id').value,
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

function iniciarProduccion(id) {
    Swal.fire({
        title: '¿Iniciar la corrida ahora?', icon: 'question',
        showCancelButton: true, confirmButtonText: 'Sí, iniciar', cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarProduccion('INICIARCORRIDA', { id });
        if (json.success) { Swal.fire('Listo', json.message, 'success'); cargarProducciones(); }
        else Swal.fire('Error', json.message, 'error');
    });
}

function finalizarProduccion(id) {
    Swal.fire({
        title: '¿Finalizar la corrida ahora?', icon: 'question',
        showCancelButton: true, confirmButtonText: 'Sí, finalizar', cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarProduccion('FINALIZARCORRIDA', { id });
        if (json.success) { Swal.fire('Listo', json.message, 'success'); cargarProducciones(); }
        else Swal.fire('Error', json.message, 'error');
    });
}

const modalCantidadEnsamblaje = new bootstrap.Modal(document.getElementById('modalCantidadEnsamblaje'));
let produccionIdParaEnsamblaje = null;
let coloresMermaCache = null;
let mermaColoresSeleccionados = [];

function abrirModalCantidadParaEnsamblaje(produccionId) {
    produccionIdParaEnsamblaje = produccionId;
    document.getElementById('formCantidadEnsamblaje').reset();

    const p = produccionesCache.find(x => x.id == produccionId);
    const etapaTexto = necesitaEnsamblaje(p) ? 'Ensamblaje' : 'Empaquetado';

    document.getElementById('tituloModalCantidadEnsamblaje').innerHTML = `<i class="fa-solid fa-weight-hanging"></i> Cantidad producida — antes de pasar a ${etapaTexto}`;
    document.getElementById('btnSubmitCantidadEnsamblaje').innerHTML = `Enviar a ${etapaTexto} <i class="fa-solid fa-arrow-right"></i>`;

    aplicarUnidadesEtapaModal(p);
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
        return `<button type="button" class="pc-merma-chip ${activo ? 'activo' : ''}" onclick="toggleColorMerma(${c.id})">${activo ? '<i class="fa-solid fa-check"></i>' : `<span class="pc-color-dot" style="background:${c.rgb || '#ccc'}"></span>`} ${c.nombre}</button>`;
    }).join('');
}

async function renderInfoMermaModal(p) {
    const cont = document.getElementById('merma_colores_chips');
    const notaInput = document.getElementById('merma_nota');
    document.getElementById('cantidad_merma_kg').value = '';
    if (notaInput) notaInput.value = '';
    mermaColoresSeleccionados = [];

    if (!p) { if (cont) cont.innerHTML = ''; renderListaMermas(null); return; }

    const colores = await obtenerColoresParaMerma();
    cont.dataset.colores = JSON.stringify(colores);

    if (p.color_id) mermaColoresSeleccionados = [Number(p.color_id)];
    renderChipsColorMerma();

    renderListaMermas(p);
}

document.getElementById('btnRegistrarMerma').addEventListener('click', async () => {
    const inputMerma = document.getElementById('cantidad_merma_kg');
    const unidadMerma = inputMerma.dataset.unidad || 'kg';
    const valor = parseFloat(inputMerma.value);
    const nota = document.getElementById('merma_nota').value.trim();

    if (isNaN(valor) || valor <= 0) { Swal.fire('Dato inválido', 'Ingresa una cantidad de merma mayor a 0.', 'warning'); return; }
    if (esUnidadEntera(unidadMerma) && !Number.isInteger(valor)) {
        Swal.fire('Dato inválido', `La cantidad de merma debe ser un número entero (unidad: ${unidadMerma}).`, 'warning');
        return;
    }

    const json = await llamarProduccion('REGISTRARMERMA', { id: produccionIdParaEnsamblaje, cantidad_merma: valor, colores: JSON.stringify(mermaColoresSeleccionados), nota: nota });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    const p = produccionesCache.find(x => x.id == produccionIdParaEnsamblaje);
    if (p) { p.js_cantidades_merma = Array.isArray(p.js_cantidades_merma) ? p.js_cantidades_merma : []; p.js_cantidades_merma.push(json.merma); }

    document.getElementById('cantidad_merma_kg').value = '';
    document.getElementById('merma_nota').value = '';
    mermaColoresSeleccionados = p && p.color_id ? [Number(p.color_id)] : [];
    renderChipsColorMerma();
    renderListaMermas(p);

    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Merma registrada', showConfirmButton: false, timer: 1500 });
});

document.getElementById('formCantidadEnsamblaje').addEventListener('submit', async function (e) {
    e.preventDefault();

    const inputProducida = document.getElementById('cantidad_producida_ensamblaje');
    const unidadProducida = inputProducida.dataset.unidad || 'kg';
    const valor = parseFloat(inputProducida.value);

    if (isNaN(valor) || valor <= 0) { Swal.fire('Dato inválido', 'Ingresa una cantidad producida mayor a 0.', 'warning'); return; }
    if (esUnidadEntera(unidadProducida) && !Number.isInteger(valor)) {
        Swal.fire('Dato inválido', `La cantidad producida debe ser un número entero (unidad: ${unidadProducida}).`, 'warning');
        return;
    }

    const json = await llamarProduccion('ENVIARAENSAMBLAJE', { id: produccionIdParaEnsamblaje, cantidad_producida: valor, unidad: unidadProducida });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    modalCantidadEnsamblaje.hide();
    Swal.fire('Listo', json.message, 'success');
    cargarProducciones();
});
</script>
</body>
</html>