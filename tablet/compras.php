<?php
session_start();
require __DIR__ . '/../controllers_tablet/clssAuthOperario.php';

if (empty($_SESSION['operario_id'])) {
    header('Location: loginoperarios.php');
    exit;
}

// Ajusta el nombre de la etapa/permiso si tu proyecto usa un guard como en
// ensamblaje.php (exigirAccesoEtapa). El propio controlador ya valida que
// operario_rol === 'conductor', así que este guard de página es un respaldo.
if (($_SESSION['operario_rol'] ?? '') !== 'conductor') {
    header('Location: panel.php');
    exit;
}

$operarioId     = (int) $_SESSION['operario_id'];
$operarioNombre = $_SESSION['operario_nombre'] ?? 'Operario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Compras · Plásticos Chepito</title>
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
            <span class="pc-op-brand-name">Compras</span>
            <span class="pc-op-brand-tag">Operario: <?= htmlspecialchars($operarioNombre) ?></span>
        </div>
    </div>
    <a href="logoutoperario.php" class="pc-op-panel-logout">
        <i class="fa-solid fa-right-from-bracket"></i> Salir
    </a>
</header>

<style>
:root{
    --safe-b: env(safe-area-inset-bottom, 0px);
    --safe-l: env(safe-area-inset-left, 0px);
    --safe-r: env(safe-area-inset-right, 0px);
}

/* =========================================================================
   LISTADO PRINCIPAL
   ========================================================================= */
.pc-stat-row{ display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:18px; }
.pc-stat-chip{ border:1px solid #e7e4dd; border-radius:14px; background:#fff; padding:14px; display:flex; align-items:center; gap:10px; }
.pc-stat-chip .ico{ width:38px; height:38px; border-radius:10px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:16px; }
.pc-stat-chip .txt .n{ font-size:21px; font-weight:700; line-height:1.15; color:#152238; }
.pc-stat-chip .txt .l{ font-size:11.5px; color:#8a8578; }
.pc-stat-chip.s-gray .ico{ background:#EEECE6; color:#8a8578; }
.pc-stat-chip.s-info .ico{ background:#E3F2FD; color:#0B4DA6; }
.pc-stat-chip.s-success .ico{ background:#E8F7EE; color:#16A34A; }
.pc-stat-chip.s-purple .ico{ background:#F1EAFD; color:#7C3AED; }
@media (max-width:900px){ .pc-stat-row{ grid-template-columns:repeat(2,1fr); } }

.pc-cmp-toolbar{
    display:flex; align-items:center; gap:12px; flex-wrap:wrap;
    border:1px solid #e7e4dd; border-radius:14px; background:#fff; padding:12px 14px; margin-bottom:18px;
}
.pc-cmp-toolbar input[type=text]{ flex:1; min-width:180px; }
.pc-cmp-toolbar input[type=date]{ min-width:150px; }

.pc-cmp-grid{ display:grid; grid-template-columns:repeat(auto-fill, minmax(320px,1fr)); gap:16px; }
.pc-cmp-card{ border:1px solid #ece9e1; border-radius:16px; background:#fff; padding:18px; display:flex; flex-direction:column; gap:10px; }
.pc-cmp-card-top{ display:flex; align-items:center; gap:8px; }
.pc-cmp-id{ font-size:.8em; color:#a7a293; font-weight:600; }
.pc-cmp-card-spacer{ flex:1; }
.pc-cmp-edit-btn{ border:none; background:none; color:#c3beae; padding:10px; font-size:1.05em; cursor:pointer; border-radius:8px; min-width:44px; min-height:44px; }
.pc-cmp-edit-btn:active{ color:#2F6FED; background:#EAF0FE; }
.pc-cmp-title{ font-size:1.15em; font-weight:700; color:#1f2430; line-height:1.25; }
.pc-cmp-meta{ font-size:.87em; color:#9a9585; line-height:1.4; }
.pc-cmp-meta span:not(:last-child)::after{ content:"·"; margin:0 6px; color:#d8d4c8; }
.pc-cmp-stats{ display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
.pc-cmp-stat .num{ font-size:1.25em; font-weight:700; color:#1f2430; line-height:1.15; }
.pc-cmp-stat .lbl{ font-size:.68em; color:#9a9585; margin-top:3px; text-transform:uppercase; letter-spacing:.02em; }
.pc-cmp-tag{ font-size:.78em; color:#8a8578; background:#f6f4ee; border-radius:6px; padding:5px 10px; font-weight:600; display:inline-flex; align-items:center; gap:5px; width:fit-content; }
.pc-cmp-tag.si-comprobante{ background:#E8F7EE; color:#16A34A; }
.pc-cmp-tag.no-comprobante{ background:#FDF1E0; color:#D97706; }
.pc-cmp-empty{ text-align:center; color:#9a9585; padding:50px 12px; grid-column:1/-1; font-size:1.05em; }

.pc-card-header-cmp{ display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px; }
.pc-card-header-cmp .subt{ color:#9a9585; font-size:.9em; margin-top:2px; }
.pc-btn-registrar{
    border:none; border-radius:12px; background:#152238; color:#fff; flex:0 0 auto;
    padding:14px 22px; font-weight:700; font-size:.98em; display:flex; align-items:center; gap:10px;
    box-shadow:0 6px 16px rgba(21,34,56,.18); cursor:pointer; min-height:50px; white-space:nowrap;
}
.pc-btn-registrar:active{ transform:scale(.97); }
@media (max-width:560px){ .pc-btn-registrar{ width:100%; justify-content:center; } }

/* =========================================================================
   FORMULARIO
   ========================================================================= */
.pc-modal-tablet .modal-content{ background:#fbfaf7; }
.pc-cmp-form-body{ display:flex; flex-direction:column; gap:14px; padding-bottom:6px; height:100%; overflow-y:auto; }

.pc-step-badge{ width:22px; height:22px; border-radius:50%; background:#152238; color:#fff; font-size:.7em; font-weight:800; display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; }

.pc-panel{ border:1px solid #e7e4dd; border-radius:14px; background:#fff; display:flex; flex-direction:column; min-height:0; overflow:hidden; }
.pc-panel.accent-blue{ border-top:3px solid #2F6FED; }
.pc-panel.accent-violet{ border-top:3px solid #7C3AED; }
.pc-panel.accent-teal{ border-top:3px solid #0E9488; }
.pc-panel-head{ padding:12px 14px; border-bottom:1px solid #eee7db; background:#fffefb; flex:0 0 auto; }
.pc-panel-head h6{ margin:0; font-weight:700; font-size:1em; display:flex; align-items:center; gap:8px; }
.pc-panel-head .sub{ font-size:.78em; color:#9a9585; margin-top:2px; font-weight:500; }
.pc-panel-search{ padding:10px 12px 0 12px; flex:0 0 auto; }
.pc-panel-body-scroll{ flex:1; min-height:120px; max-height:40vh; overflow-y:auto; padding:12px; }
@media (min-width:880px) and (orientation:landscape){ .pc-panel-body-scroll{ max-height:none; } }

/* Proveedor: cards abiertas, elección única */
.pc-prov-grid{ display:grid; grid-template-columns:repeat(auto-fill, minmax(220px,1fr)); gap:10px; }
.pc-prov-card{ position:relative; text-align:left; border:1.5px solid #eae6da; border-radius:12px; background:#fff; padding:12px; display:flex; align-items:center; gap:10px; cursor:pointer; min-height:64px; }
.pc-prov-card:active{ transform:scale(.98); }
.pc-prov-card.activo{ border-color:#2F6FED; background:#EAF0FE; }
.pc-prov-card .pellet{ width:36px; height:36px; border-radius:10px; flex:0 0 auto; display:flex; align-items:center; justify-content:center; background:#EAF0FE; color:#2F6FED; font-size:1em; }
.pc-prov-card .cuerpo{ min-width:0; }
.pc-prov-card .nom{ font-weight:700; font-size:.92em; color:#1f2430; line-height:1.25; }
.pc-prov-card .ruc{ font-size:.78em; color:#8a8578; margin-top:2px; }
.pc-prov-card .check{ position:absolute; top:8px; right:8px; color:#2F6FED; font-size:1em; opacity:0; }
.pc-prov-card.activo .check{ opacity:1; }
.pc-prov-selected-banner{ display:flex; align-items:center; gap:10px; padding:10px 12px; background:#EAF0FE; border:1px solid #cddafc; border-radius:10px; font-size:.9em; color:#1f2430; }
.pc-prov-selected-banner b{ font-weight:700; }
.pc-prov-selected-banner button{ margin-left:auto; border:none; background:none; color:#0B4DA6; font-weight:700; font-size:.85em; }

.pc-cmp-topbar{ display:grid; grid-template-columns:1fr 1fr; gap:14px; flex:0 0 auto; }
@media (max-width:560px){ .pc-cmp-topbar{ grid-template-columns:1fr; } }
.pc-cmp-topbar-group{ border:1px solid #e7e4dd; border-radius:14px; background:#fff; padding:12px 14px; }
.pc-cmp-topbar-group > label{ display:flex; align-items:center; gap:6px; font-size:.78em; font-weight:800; text-transform:uppercase; letter-spacing:.04em; color:#8a8578; margin-bottom:8px; }

/* Materiales */
.pc-mat-grid{ display:grid; grid-template-columns:repeat(auto-fill, minmax(170px,1fr)); gap:10px; }
.pc-mat-card{ position:relative; border:1px solid #eae6da; border-radius:12px; background:#fff; padding:12px 10px 10px 10px; cursor:pointer; text-align:left; min-height:96px; }
.pc-mat-card:active{ transform:scale(0.96); }
.pc-mat-card .pellet{ width:34px; height:34px; border-radius:9px; display:flex; align-items:center; justify-content:center; background:#EAF0FE; color:#2F6FED; font-size:1em; margin-bottom:8px; }
.pc-mat-card .nombre{ font-weight:600; font-size:.9em; line-height:1.25; display:block; min-height:2.2em; }
.pc-mat-card .meta{ font-size:.78em; color:#8a8578; margin-top:4px; display:block; }
.pc-mat-card .meta b{ color:#4a4636; }
.pc-mat-empty{ grid-column:1/-1; text-align:center; color:#9a9585; font-size:.92em; padding:22px 8px; }

/* Sub-formulario de línea (cantidad/unidad/subtotal/total/comentario) */
.pc-linea-form{ border:1.5px solid #cddafc; border-radius:14px; background:#EAF0FE; padding:14px; display:flex; flex-direction:column; gap:10px; }
.pc-linea-form .titulo{ font-weight:700; font-size:.95em; color:#152238; display:flex; align-items:center; gap:8px; }
.pc-linea-form .grid-campos{ display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.pc-linea-form .grid-campos.full{ grid-template-columns:1fr; }
.pc-linea-form label{ font-size:.78em; font-weight:700; color:#5c5947; margin-bottom:4px; display:block; }
.pc-linea-form .acciones{ display:flex; gap:10px; }
.pc-linea-form .acciones .btn{ flex:1; min-height:46px; font-weight:700; }

/* Ticket */
.pc-tk-list{ list-style:none; margin:0; padding:0; }
.pc-tk-item{ border-bottom:1px dashed #eee2c8; padding:12px; display:flex; gap:10px; align-items:flex-start; }
.pc-tk-item:last-child{ border-bottom:none; }
.pc-tk-item .pellet-sm{ width:30px; height:30px; border-radius:8px; flex:0 0 auto; display:flex; align-items:center; justify-content:center; background:#EAF0FE; color:#2F6FED; font-size:.85em; margin-top:2px; }
.pc-tk-item .cuerpo{ flex:1; min-width:0; }
.pc-tk-item .nombre{ font-weight:600; font-size:.92em; }
.pc-tk-item .lote-info{ font-size:.78em; color:#8a8578; margin-top:1px; }
.pc-tk-remove{ border:none; background:none; color:#c94a4a; font-size:1.15em; align-self:flex-start; padding:8px; min-width:40px; min-height:40px; }
.pc-tk-empty{ text-align:center; color:#9a9585; font-size:.92em; padding:28px 12px; }
.pc-tk-empty i{ font-size:1.7em; display:block; margin-bottom:6px; opacity:.5; }
.pc-tk-resumen{ display:flex; align-items:center; gap:12px; padding:14px; border-top:1px solid #eee7db; background:linear-gradient(0deg,#fffaf0,#fffefb); flex:0 0 auto; }
.pc-tk-resumen-icon{ width:38px; height:38px; border-radius:10px; flex:0 0 auto; background:#EAF0FE; color:#2F6FED; display:flex; align-items:center; justify-content:center; font-size:1em; }
.pc-tk-resumen-texto{ display:flex; flex-direction:column; gap:1px; min-width:0; }
.pc-tk-resumen-texto .total{ font-size:1em; color:#3a3730; }
.pc-tk-resumen-texto .total b{ font-size:1.2em; color:#2F6FED; }
.pc-tk-resumen-texto .detalle{ font-size:.8em; color:#8a8578; }

/* Comprobante */
.pc-comprobante-box{ border:1.5px dashed #c7c2b3; border-radius:12px; padding:14px; display:flex; flex-direction:column; gap:10px; }
.pc-comprobante-preview{ display:flex; align-items:center; gap:12px; }
.pc-comprobante-preview img{ width:64px; height:64px; object-fit:cover; border-radius:8px; border:1px solid #e7e4dd; }
.pc-comprobante-preview .placeholder{ width:64px; height:64px; border-radius:8px; background:#f6f4ee; display:flex; align-items:center; justify-content:center; color:#c7c2b3; font-size:1.4em; }
.pc-comprobante-preview .info{ flex:1; min-width:0; font-size:.85em; color:#5c5947; }

.pc-cmp-layout{ flex:1; min-height:0; display:flex; flex-direction:column; gap:14px; }
.pc-cmp-content{ display:flex; flex-direction:column; gap:14px; }
@media (min-width:880px) and (orientation:landscape){
    .pc-cmp-layout{ display:grid; grid-template-columns:1fr 320px; align-items:start; gap:14px; }
    .pc-cmp-content{ max-height:100%; }
    .pc-cmp-ticket-col{ position:sticky; top:0; align-self:start; display:flex; flex-direction:column; max-height:calc(100vh - 210px); }
    .pc-cmp-ticket-col .pc-panel{ flex:1; min-height:0; }
}
@media (min-width:1180px) and (orientation:landscape){
    .pc-cmp-layout{ grid-template-columns:1fr 340px; }
}

.pc-cmp-footer{ display:grid; grid-template-columns:1fr 1fr; gap:12px; padding:14px calc(14px + var(--safe-l)) calc(14px + var(--safe-b)) 14px !important; }
.pc-cmp-footer .btn{ min-height:54px; font-size:1.02em; font-weight:700; border-radius:12px; }
</style>

<div class="pc-card" style="margin:20px;">
    <div class="pc-card-header pc-card-header-cmp">
        <div>
            <h2>Compras</h2>
            <div class="subt">Toca "Registrar compra" para armar una nueva.</div>
        </div>
        <button type="button" class="pc-btn-registrar" onclick="abrirModalCrearCompra()">
            <i class="fa-solid fa-plus"></i> Registrar compra
        </button>
    </div>
    <br>
    <div class="pc-stat-row" id="statRowCompras"></div>

    <div class="pc-cmp-toolbar">
        <input type="text" id="cmp_buscar_texto" class="form-control form-control-lg" placeholder="Buscar por proveedor o descripción...">
        <input type="date" id="cmp_fecha_desde" class="form-control form-control-lg" title="Desde">
        <input type="date" id="cmp_fecha_hasta" class="form-control form-control-lg" title="Hasta">
    </div>

    <div class="pc-cmp-grid" id="gridCompras">
        <div class="pc-cmp-empty">Cargando...</div>
    </div>
</div>
<div class="modal fade" id="modalCamaraComprobanteTablet" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Tomar foto del comprobante</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <video id="videoCamaraComprobanteTablet" autoplay playsinline
               style="width:100%; max-height:60vh; background:#000; border-radius:8px;"></video>
        <canvas id="canvasCamaraComprobanteTablet" style="display:none;"></canvas>
        <div id="previewCamaraComprobanteTablet" style="display:none;">
            <img id="imgPreviewCamaraComprobanteTablet" style="width:100%; max-height:60vh; object-fit:contain; border-radius:8px;">
        </div>
        <div class="form-text mt-2" id="camaraComprobanteTabletError" style="color:#dc3545; display:none;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-outline-primary" id="btnRepetirFotoComprobanteTablet" style="display:none;" onclick="repetirFotoComprobanteTablet()">Repetir</button>
        <button type="button" class="btn btn-primary" id="btnCapturarFotoComprobanteTablet" onclick="capturarFotoComprobanteTablet()">
            <i class="fa-solid fa-camera"></i> Capturar
        </button>
        <button type="button" class="btn btn-success" id="btnUsarFotoComprobanteTablet" style="display:none;" onclick="usarFotoComprobanteTablet()">Usar esta foto</button>
      </div>
    </div>
  </div>
</div>
<!-- Modal Crear/Editar -->
<div class="modal fade pc-modal-tablet" id="modalCompra" tabindex="-1">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content">
      <form id="formCompra" class="d-flex flex-column" style="height:100%;">
        <div class="modal-header">
          <h5 class="modal-title" id="modalCompraTitulo">Registrar compra</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body pc-cmp-form-body">

            <div class="pc-cmp-topbar">
                <div class="pc-cmp-topbar-group">
                    <label><i class="fa-solid fa-calendar"></i> Fecha de compra *</label>
                    <input type="date" id="cmp_fecha_compra" class="form-control form-control-lg">
                </div>
                <div class="pc-cmp-topbar-group">
                    <label><i class="fa-solid fa-align-left"></i> Descripción</label>
                    <input type="text" id="cmp_descripcion" class="form-control form-control-lg" placeholder="Opcional">
                </div>
            </div>

            <div class="pc-cmp-layout">
                <div class="pc-cmp-content">

                    <!-- Paso 1: Proveedor -->
                    <div class="pc-panel accent-blue" id="pc_panel_proveedor">
                        <div class="pc-panel-head">
                            <h6><span class="pc-step-badge">1</span> <i class="fa-solid fa-truck-field"></i> Proveedor</h6>
                        </div>
                        <div id="cmp_proveedor_seleccionado_wrap" style="display:none; padding:12px;">
                            <div class="pc-prov-selected-banner">
                                <i class="fa-solid fa-circle-check"></i>
                                <span><b id="cmp_proveedor_nombre_sel"></b> · <span id="cmp_proveedor_ruc_sel"></span></span>
                                <button type="button" onclick="cambiarProveedorCompra()">Cambiar</button>
                            </div>
                        </div>
                        <div id="cmp_proveedor_buscador_wrap">
                            <div class="pc-panel-search">
                                <input type="text" id="cmp_buscar_proveedor" class="form-control form-control-lg" placeholder="Buscar proveedor por razón social o RUC...">
                            </div>
                            <div class="pc-panel-body-scroll">
                                <div class="pc-prov-grid" id="cmp_proveedor_grid">
                                    <div class="pc-mat-empty">Escribe para buscar un proveedor.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Paso 2: Materiales -->
                    <div class="pc-panel accent-violet">
                        <div class="pc-panel-head">
                            <h6><span class="pc-step-badge">2</span> <i class="fa-solid fa-boxes-stacked"></i> Materiales comprados</h6>
                        </div>
                        <div class="pc-panel-search">
                            <input type="text" id="cmp_buscar_material" class="form-control form-control-lg" placeholder="Buscar material...">
                        </div>
                        <div class="pc-panel-body-scroll">
                            <div class="pc-mat-grid" id="cmp_material_grid">
                                <div class="pc-mat-empty">Escribe para buscar un material.</div>
                            </div>
                        </div>
                        <div id="cmp_linea_form_wrap" style="padding:0 12px 12px 12px;"></div>
                    </div>

                    <!-- Comprobante -->
                    <div class="pc-panel accent-teal">
                        <div class="pc-panel-head">
                            <h6><i class="fa-solid fa-receipt"></i> Comprobante</h6>
                            <div class="sub">Foto o PDF del comprobante y el monto que figura en él (opcional).</div>
                        </div>
                        <div style="padding:12px;" class="pc-comprobante-box">
                            <div class="pc-comprobante-preview">
                                <div id="cmp_comprobante_preview_img" class="placeholder"><i class="fa-solid fa-image"></i></div>
                                <div class="info" id="cmp_comprobante_info">Sin comprobante cargado.</div>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="cmp_btn_quitar_comprobante" style="display:none;" onclick="quitarComprobanteCompra()">Quitar</button>
                            </div>
                            <div class="d-flex gap-2">
                                <input type="file" id="cmp_img_comprobante" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                <button type="button" class="btn btn-outline-secondary flex-shrink-0" onclick="abrirModalCamaraComprobanteTablet()" title="Tomar foto">
                                    <i class="fa-solid fa-camera"></i>
                                </button>
                            </div>
                            <div>
                                <label style="font-size:.78em; font-weight:700; color:#5c5947;">Monto del comprobante (S/)</label>
                                <input type="number" id="cmp_total_img_cargado" class="form-control form-control-lg" min="0" step="0.01" placeholder="Opcional">
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Ticket -->
                <div class="pc-cmp-ticket-col">
                    <div class="pc-panel accent-teal">
                        <div class="pc-panel-head"><h6><i class="fa-solid fa-cart-shopping"></i> Resumen de la compra</h6></div>
                        <div class="pc-panel-body-scroll" style="padding:0;">
                            <ul class="pc-tk-list" id="cmp_ticket_list">
                                <li class="pc-tk-empty"><i class="fa-solid fa-basket-shopping"></i>Aún no agregas materiales.<br>Toca uno de arriba para empezar.</li>
                            </ul>
                        </div>
                        <div class="pc-tk-resumen">
                            <div class="pc-tk-resumen-icon"><i class="fa-solid fa-coins"></i></div>
                            <div class="pc-tk-resumen-texto">
                                <span class="total">Total: <b id="cmp_ticket_total_monto">S/ 0.00</b></span>
                                <span class="detalle" id="cmp_ticket_detalle">0 material(es)</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>

        <div class="modal-footer pc-cmp-footer">
          <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary btn-lg" id="cmp_btn_guardar">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../assets/js/device-tracking.js"></script>
<script src="../assets/js/app-common.js"></script>
<script>
const OPERARIO_ID     = <?= json_encode($operarioId) ?>;
const OPERARIO_NOMBRE = <?= json_encode($operarioNombre) ?>;

const CONTROLADOR_COMPRA = '../controllers_tablet/clssCompraConductor.php';

const modalCompra = new bootstrap.Modal(document.getElementById('modalCompra'));

// llamar() es el helper compartido (ver app-common.js) que hace un POST
// application/x-www-form-urlencoded y devuelve el JSON ya parseado.
const llamarCompra = (accion, params = {}) => llamar(CONTROLADOR_COMPRA, accion, params);

let modoEdicionCompra = false;
let compraIdActual = 0;
let comprasCache = [];
let unidadesCache = null;

// Estado del formulario
let proveedorSeleccionadoCompra = null; // {ruc, razon_social, nombre_comercial}
let materialSeleccionadoTemp = null;    // material sobre el que se está armando la línea
let contadorLineaTicketCompra = 0;
let ticketDetalleCompra = [];           // [{tempId, id, material_id, material_nombre, unidad_medida_id, unidad_corto, cantidad, sub_total, total, comentario, usado_en_produccion}]
let comprobanteArchivo = null;          // File nuevo elegido
let comprobanteUrlActual = null;        // URL ya guardada (modo edición)
let eliminarComprobanteFlag = false;

document.addEventListener('DOMContentLoaded', () => {
    cargarCompras().catch(err => {
        console.error('Error cargando compras:', err);
        document.getElementById('gridCompras').innerHTML =
            `<div class="pc-cmp-empty" style="color:red;">Error de conexión con el servidor.</div>`;
    });

    let debTexto = null;
    document.getElementById('cmp_buscar_texto').addEventListener('input', () => {
        clearTimeout(debTexto);
        debTexto = setTimeout(() => cargarCompras(), 300);
    });
    document.getElementById('cmp_fecha_desde').addEventListener('change', () => cargarCompras());
    document.getElementById('cmp_fecha_hasta').addEventListener('change', () => cargarCompras());

    let debProv = null;
    document.getElementById('cmp_buscar_proveedor').addEventListener('input', () => {
        clearTimeout(debProv);
        debProv = setTimeout(buscarYRenderProveedores, 300);
    });

    let debMat = null;
    document.getElementById('cmp_buscar_material').addEventListener('input', () => {
        clearTimeout(debMat);
        debMat = setTimeout(buscarYRenderMateriales, 300);
    });

    document.getElementById('cmp_img_comprobante').addEventListener('change', onCambioArchivoComprobante);
});

// =============================================================================
// Utilidades de formato
// =============================================================================
function formatearMontoCompra(n) {
    const num = Number(n) || 0;
    return 'S/ ' + num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function formatearCantidadCompra(n) {
    if (n === null || n === undefined || n === '') return '-';
    return Number(n).toLocaleString('es-PE', { maximumFractionDigits: 4 });
}
function formatearFechaCortaCompra(fechaIso) {
    if (!fechaIso) return '';
    const [fecha] = fechaIso.split(' ');
    if (!fecha) return fechaIso;
    const [y, m, d] = fecha.split('-');
    return `${d}/${m}/${y}`;
}

// =============================================================================
// LISTADO
// =============================================================================
async function cargarCompras() {
    const grid = document.getElementById('gridCompras');
    grid.innerHTML = '<div class="pc-cmp-empty">Cargando...</div>';

    const json = await llamarCompra('LISTARMISCOMPRAS', {
        texto: document.getElementById('cmp_buscar_texto').value.trim(),
        fecha_desde: document.getElementById('cmp_fecha_desde').value,
        fecha_hasta: document.getElementById('cmp_fecha_hasta').value,
    });

    if (!json.success) {
        console.error('LISTARMISCOMPRAS falló:', json);
        grid.innerHTML = `<div class="pc-cmp-empty">${json.message || 'No se pudo cargar el listado de compras.'}</div>`;
        return;
    }

    comprasCache = json.compras || [];
    renderStatRowCompras(comprasCache);
    renderGridCompras(comprasCache);
}

function renderStatRowCompras(compras) {
    const cantidad = compras.length;
    const totalGastado = compras.reduce((s, c) => s + Number(c.total || 0), 0);
    const conComprobante = compras.filter(c => !!c.img_comprobante).length;
    const sinComprobante = cantidad - conComprobante;

    document.getElementById('statRowCompras').innerHTML = `
        <div class="pc-stat-chip s-gray"><div class="ico"><i class="fa-solid fa-cart-shopping"></i></div><div class="txt"><div class="n">${cantidad}</div><div class="l">Compras</div></div></div>
        <div class="pc-stat-chip s-info"><div class="ico"><i class="fa-solid fa-sack-dollar"></i></div><div class="txt"><div class="n">${formatearMontoCompra(totalGastado)}</div><div class="l">Total gastado</div></div></div>
        <div class="pc-stat-chip s-success"><div class="ico"><i class="fa-solid fa-receipt"></i></div><div class="txt"><div class="n">${conComprobante}</div><div class="l">Con comprobante</div></div></div>
        <div class="pc-stat-chip s-purple"><div class="ico"><i class="fa-solid fa-triangle-exclamation"></i></div><div class="txt"><div class="n">${sinComprobante}</div><div class="l">Sin comprobante</div></div></div>
    `;
}

function tarjetaCompraHtml(c) {
    return `
    <div class="pc-cmp-card">
        <div class="pc-cmp-card-top">
            <span class="pc-cmp-id">#${c.id}</span>
            <span class="pc-cmp-card-spacer"></span>
            <button type="button" class="pc-cmp-edit-btn" onclick="abrirModalEditarCompra(${c.id})" title="Editar">
                <i class="fa-solid fa-pen"></i>
            </button>
        </div>
        <div class="pc-cmp-title">${c.nombre_comercial || c.razon_social || 'Proveedor'}</div>
        <div class="pc-cmp-meta">
            <span>${formatearFechaCortaCompra(c.fecha_compra)}</span>
            ${c.descripcion ? `<span>${c.descripcion}</span>` : ''}
        </div>
        <div class="pc-cmp-stats">
            <div class="pc-cmp-stat"><div class="num">${c.items_count ?? 0}</div><div class="lbl">Material(es)</div></div>
            <div class="pc-cmp-stat"><div class="num">${formatearMontoCompra(c.total)}</div><div class="lbl">Total</div></div>
        </div>
        ${c.img_comprobante
            ? `<span class="pc-cmp-tag si-comprobante"><i class="fa-solid fa-check"></i> Con comprobante</span>`
            : `<span class="pc-cmp-tag no-comprobante"><i class="fa-solid fa-circle-exclamation"></i> Sin comprobante</span>`}
    </div>`;
}

function renderGridCompras(compras) {
    const grid = document.getElementById('gridCompras');
    if (compras.length === 0) {
        grid.innerHTML = '<div class="pc-cmp-empty">No tienes compras registradas todavía.</div>';
        return;
    }
    grid.innerHTML = compras.map(tarjetaCompraHtml).join('');
}

// =============================================================================
// PASO 1: PROVEEDOR
// =============================================================================
async function buscarYRenderProveedores() {
    const grid = document.getElementById('cmp_proveedor_grid');
    const texto = document.getElementById('cmp_buscar_proveedor').value.trim();
    grid.innerHTML = '<div class="pc-mat-empty"><i class="fa-solid fa-spinner fa-spin"></i> Buscando...</div>';

    const json = await llamarCompra('BUSCARPROVEEDORES', { texto });

    if (!json.success) {

        console.error('BUSCARPROVEEDORES falló:', json);
        grid.innerHTML = `<div class="pc-mat-empty" style="color:#c94a4a;">${json.message || 'Error al buscar proveedores.'}</div>`;
        return;
    }

    const proveedores = json.proveedores || [];
    if (proveedores.length === 0) {
        grid.innerHTML = `
            <div class="pc-mat-empty">
                No se encontraron proveedores.<br>
                <button type="button" class="btn btn-primary btn-sm mt-2" onclick="abrirFormularioProveedorRapidoTablet()">
                    <i class="fa-solid fa-plus"></i> Registrar proveedor nuevo
                </button>
            </div>`;
        return;
    }
    grid.innerHTML = proveedores.map(p => `
        <button type="button" class="pc-prov-card" onclick='seleccionarProveedorCompra(${JSON.stringify(p)})'>
            <span class="pellet"><i class="fa-solid fa-truck-field"></i></span>
            <span class="cuerpo">
                <span class="nom">${p.nombre_comercial || p.razon_social}</span>
                <span class="ruc">RUC ${p.ruc}${p.nombre_comercial ? ' · ' + p.razon_social : ''}</span>
            </span>
            <i class="fa-solid fa-circle-check check"></i>
        </button>`).join('');
}

function seleccionarProveedorCompra(p) {
    proveedorSeleccionadoCompra = p;
    document.getElementById('cmp_proveedor_nombre_sel').textContent = p.nombre_comercial || p.razon_social;
    document.getElementById('cmp_proveedor_ruc_sel').textContent = 'RUC ' + p.ruc;
    document.getElementById('cmp_proveedor_seleccionado_wrap').style.display = '';
    document.getElementById('cmp_proveedor_buscador_wrap').style.display = 'none';
}

function cambiarProveedorCompra() {
    proveedorSeleccionadoCompra = null;
    document.getElementById('cmp_proveedor_seleccionado_wrap').style.display = 'none';
    document.getElementById('cmp_proveedor_buscador_wrap').style.display = '';
    document.getElementById('cmp_buscar_proveedor').value = '';
    document.getElementById('cmp_proveedor_grid').innerHTML = '<div class="pc-mat-empty">Escribe para buscar un proveedor.</div>';
}

// =============================================================================
// PASO 2: MATERIALES → sub-formulario de línea → ticket
// =============================================================================
async function obtenerUnidadesCompra() {
    if (unidadesCache) return unidadesCache;
    const json = await llamarCompra('BUSCARUNIDADES');
    unidadesCache = json.success ? (json.unidades || []) : [];
    return unidadesCache;
}

async function buscarYRenderMateriales() {
    const grid = document.getElementById('cmp_material_grid');
    const texto = document.getElementById('cmp_buscar_material').value.trim();
    grid.innerHTML = '<div class="pc-mat-empty"><i class="fa-solid fa-spinner fa-spin"></i> Buscando...</div>';

    const json = await llamarCompra('BUSCARMATERIALES', { texto });

    if (!json.success) {
        console.error('BUSCARMATERIALES falló:', json);
        grid.innerHTML = `<div class="pc-mat-empty" style="color:#c94a4a;">${json.message || 'Error al buscar materiales.'}</div>`;
        return;
    }

    const materiales = json.materiales || [];
    if (materiales.length === 0) {
        grid.innerHTML = `
            <div class="pc-mat-empty">
                No se encontraron materiales.<br>
                <button type="button" class="btn btn-primary btn-sm mt-2" onclick="abrirFormularioMaterialRapidoTablet()">
                    <i class="fa-solid fa-plus"></i> Registrar material nuevo
                </button>
            </div>`;
        return;
    }

    grid.innerHTML = materiales.map(m => `
        <button type="button" class="pc-mat-card" onclick='abrirFormularioLineaCompra(${JSON.stringify(m)})'>
            <span class="pellet"><i class="fa-solid fa-cube"></i></span>
            <span class="nombre">${m.nombre}</span>
            <span class="meta">Stock: <b>${formatearCantidadCompra(m.stock_actual)} ${m.unidad_corto ?? ''}</b></span>
        </button>`).join('');
}
async function abrirFormularioProveedorRapidoTablet() {
    const texto = document.getElementById('cmp_buscar_proveedor').value.trim();
    const { value } = await Swal.fire({
        title: 'Registrar proveedor',
        html: `
            <input id="pr_ruc" class="swal2-input" placeholder="RUC / DNI">
            <input id="pr_razon" class="swal2-input" placeholder="Razón social / nombre" value="${texto.replace(/"/g,'')}">
            <input id="pr_comercial" class="swal2-input" placeholder="Nombre comercial (opcional)">
        `,
        confirmButtonText: 'Guardar',
        showCancelButton: true,
        preConfirm: () => ({
            ruc: document.getElementById('pr_ruc').value.trim(),
            razon_social: document.getElementById('pr_razon').value.trim(),
            nombre_comercial: document.getElementById('pr_comercial').value.trim(),
        })
    });
    if (!value) return;

    const json = await llamarCompra('GUARDARPROVEEDORTABLET', value);
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    Swal.fire('Listo', json.message, 'success');
    seleccionarProveedorCompra(json.proveedor);
}

async function abrirFormularioMaterialRapidoTablet() {
    const texto = document.getElementById('cmp_buscar_material').value.trim();
    const unidades = await obtenerUnidadesCompra();
    const raiz = unidades.filter(u => !u.unidad_base_id);
    const opciones = raiz.map(u => `<option value="${u.id}">${u.nombre} (${u.nombre_corto})</option>`).join('');

    const { value } = await Swal.fire({
        title: 'Registrar material',
        html: `
            <input id="mr_nombre" class="swal2-input" placeholder="Nombre del material" value="${texto.replace(/"/g,'')}">
            <select id="mr_unidad" class="swal2-select">${opciones}</select>
        `,
        confirmButtonText: 'Guardar',
        showCancelButton: true,
        preConfirm: () => ({
            nombre: document.getElementById('mr_nombre').value.trim(),
            unidad_medida_id: document.getElementById('mr_unidad').value,
        })
    });
    if (!value) return;

    const json = await llamarCompra('GUARDARMATERIALTABLET', value);
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    Swal.fire('Listo', json.message, 'success');
    abrirFormularioLineaCompra(json.material); // lo deja listo para agregar cantidad
}

async function abrirFormularioLineaCompra(material) {
    materialSeleccionadoTemp = material;
    const unidades = await obtenerUnidadesCompra();
    const wrap = document.getElementById('cmp_linea_form_wrap');

    const opcionesUnidad = unidades.map(u =>
        `<option value="${u.id}" ${u.id == material.unidad_medida_id ? 'selected' : ''}>${u.nombre} (${u.nombre_corto})</option>`
    ).join('');

    wrap.innerHTML = `
        <div class="pc-linea-form">
            <div class="titulo"><i class="fa-solid fa-cube"></i> ${material.nombre}</div>
            <div class="grid-campos">
                <div>
                    <label>Cantidad *</label>
                    <input type="number" id="linea_cantidad" class="form-control form-control-lg" min="0.0001" step="0.0001" placeholder="0">
                </div>
                <div>
                    <label>Unidad de medida *</label>
                    <select id="linea_unidad" class="form-select form-select-lg">${opcionesUnidad}</select>
                </div>
            </div>
            <div class="grid-campos">
                <div>
                    <label>Subtotal (S/)</label>
                    <input type="number" id="linea_sub_total" class="form-control form-control-lg" min="0" step="0.01" placeholder="0.00">
                </div>
                <div>
                    <label>Total (S/)</label>
                    <input type="number" id="linea_total" class="form-control form-control-lg" min="0" step="0.01" placeholder="0.00">
                </div>
            </div>
            <div class="grid-campos full">
                <div>
                    <label>Comentario</label>
                    <input type="text" id="linea_comentario" class="form-control form-control-lg" placeholder="Opcional">
                </div>
            </div>
            <div class="acciones">
                <button type="button" class="btn btn-secondary" onclick="cerrarFormularioLineaCompra()">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="confirmarLineaCompra()"><i class="fa-solid fa-plus"></i> Agregar a la compra</button>
            </div>
        </div>`;

    document.getElementById('linea_sub_total').addEventListener('input', function () {
        document.getElementById('linea_total').value = this.value;
    });

    wrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function cerrarFormularioLineaCompra() {
    materialSeleccionadoTemp = null;
    document.getElementById('cmp_linea_form_wrap').innerHTML = '';
}

function confirmarLineaCompra() {
    const cantidad = parseFloat(document.getElementById('linea_cantidad').value);
    const unidadSelect = document.getElementById('linea_unidad');
    const unidadMedidaId = parseInt(unidadSelect.value);
    const unidadTexto = unidadSelect.options[unidadSelect.selectedIndex].text;
    const subTotal = parseFloat(document.getElementById('linea_sub_total').value) || 0;
    const totalRaw = document.getElementById('linea_total').value;
    const total = totalRaw !== '' ? parseFloat(totalRaw) : subTotal;
    const comentario = document.getElementById('linea_comentario').value.trim();

    if (!cantidad || cantidad <= 0) {
        Swal.fire('Cantidad inválida', 'Ingresa una cantidad mayor a 0.', 'warning');
        return;
    }
    if (!unidadMedidaId) {
        Swal.fire('Falta la unidad', 'Selecciona la unidad de medida.', 'warning');
        return;
    }

    ticketDetalleCompra.push({
        tempId: ++contadorLineaTicketCompra,
        id: null,
        material_id: materialSeleccionadoTemp.id,
        material_nombre: materialSeleccionadoTemp.nombre,
        unidad_medida_id: unidadMedidaId,
        unidad_texto: unidadTexto,
        cantidad,
        sub_total: subTotal,
        total,
        comentario: comentario || null,
        usado_en_produccion: false,
    });

    cerrarFormularioLineaCompra();
    document.getElementById('cmp_buscar_material').value = '';
    document.getElementById('cmp_material_grid').innerHTML = '<div class="pc-mat-empty">Escribe para buscar un material.</div>';
    renderTicketCompra();
}

function quitarLineaCompra(tempId) {
    const linea = ticketDetalleCompra.find(l => l.tempId === tempId);
    if (linea && linea.usado_en_produccion) {
        Swal.fire('No se puede quitar', 'Este lote ya fue usado en un registro de producción. Puedes editar su cantidad, pero no eliminarlo.', 'warning');
        return;
    }
    ticketDetalleCompra = ticketDetalleCompra.filter(l => l.tempId !== tempId);
    renderTicketCompra();
}

function renderTicketCompra() {
    const list = document.getElementById('cmp_ticket_list');
    const totalMontoEl = document.getElementById('cmp_ticket_total_monto');
    const detalleEl = document.getElementById('cmp_ticket_detalle');

    if (ticketDetalleCompra.length === 0) {
        list.innerHTML = `<li class="pc-tk-empty"><i class="fa-solid fa-basket-shopping"></i>Aún no agregas materiales.<br>Toca uno de arriba para empezar.</li>`;
    } else {
        list.innerHTML = ticketDetalleCompra.map(l => `
            <li class="pc-tk-item">
                <span class="pellet-sm"><i class="fa-solid fa-cube"></i></span>
                <div class="cuerpo">
                    <span class="nombre">${l.material_nombre}</span>
                    <div class="lote-info">
                        ${formatearCantidadCompra(l.cantidad)} ${l.unidad_texto} · ${formatearMontoCompra(l.total)}
                        ${l.comentario ? ' · ' + l.comentario : ''}
                        ${l.usado_en_produccion ? ' · <b style="color:#D97706;">usado en producción</b>' : ''}
                    </div>
                </div>
                <button type="button" class="pc-tk-remove" onclick="quitarLineaCompra(${l.tempId})" title="Quitar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </li>`).join('');
    }

    const totalMonto = ticketDetalleCompra.reduce((s, l) => s + Number(l.total || 0), 0);
    totalMontoEl.textContent = formatearMontoCompra(totalMonto);
    detalleEl.textContent = `${ticketDetalleCompra.length} material(es)`;
}

function obtenerDetalleJsonCompra() {
    return JSON.stringify(ticketDetalleCompra.map(l => ({
        id: l.id,
        material_id: l.material_id,
        unidad_medida_id: l.unidad_medida_id,
        cantidad: l.cantidad,
        sub_total: l.sub_total,
        total: l.total,
        comentario: l.comentario,
    })));
}

// =============================================================================
// COMPROBANTE
// =============================================================================
function onCambioArchivoComprobante(e) {
    const archivo = e.target.files[0] || null;
    comprobanteArchivo = archivo;
    eliminarComprobanteFlag = false;
    renderComprobantePreview();
}

function quitarComprobanteCompra() {
    comprobanteArchivo = null;
    comprobanteUrlActual = null;
    eliminarComprobanteFlag = true;
    document.getElementById('cmp_img_comprobante').value = '';
    renderComprobantePreview();
}

function renderComprobantePreview() {
    const imgWrap = document.getElementById('cmp_comprobante_preview_img');
    const info = document.getElementById('cmp_comprobante_info');
    const btnQuitar = document.getElementById('cmp_btn_quitar_comprobante');

    if (comprobanteArchivo) {
        info.textContent = comprobanteArchivo.name;
        btnQuitar.style.display = '';
        if (comprobanteArchivo.type.startsWith('image/')) {
            const url = URL.createObjectURL(comprobanteArchivo);
            imgWrap.outerHTML = `<img id="cmp_comprobante_preview_img" src="${url}" alt="Comprobante">`;
        } else {
            imgWrap.outerHTML = `<div id="cmp_comprobante_preview_img" class="placeholder"><i class="fa-solid fa-file-pdf"></i></div>`;
        }
    } else if (comprobanteUrlActual) {
        info.textContent = 'Comprobante ya cargado.';
        btnQuitar.style.display = '';
        imgWrap.outerHTML = `<img id="cmp_comprobante_preview_img" src="${comprobanteUrlActual}" alt="Comprobante">`;
    } else {
        info.textContent = 'Sin comprobante cargado.';
        btnQuitar.style.display = 'none';
        imgWrap.outerHTML = `<div id="cmp_comprobante_preview_img" class="placeholder"><i class="fa-solid fa-image"></i></div>`;
    }
}

// =============================================================================
// CREAR / EDITAR
// =============================================================================
function limpiarFormularioCompra() {
    compraIdActual = 0;
    document.getElementById('cmp_fecha_compra').value = '';
    document.getElementById('cmp_descripcion').value = '';
    document.getElementById('cmp_total_img_cargado').value = '';
    document.getElementById('cmp_buscar_material').value = '';
    document.getElementById('cmp_material_grid').innerHTML = '<div class="pc-mat-empty">Escribe para buscar un material.</div>';
    ticketDetalleCompra = [];
    cerrarFormularioLineaCompra();
    cambiarProveedorCompra();
    comprobanteArchivo = null;
    comprobanteUrlActual = null;
    eliminarComprobanteFlag = false;
    document.getElementById('cmp_img_comprobante').value = '';
    renderComprobantePreview();
    renderTicketCompra();
}

async function abrirModalCrearCompra() {
    limpiarFormularioCompra();
    modoEdicionCompra = false;
    document.getElementById('modalCompraTitulo').textContent = 'Registrar compra';
    // Fecha de hoy por defecto
    document.getElementById('cmp_fecha_compra').value = new Date().toISOString().slice(0, 10);
    await obtenerUnidadesCompra();
    modalCompra.show();
}

async function abrirModalEditarCompra(id) {
    const json = await llamarCompra('OBTENERCOMPRA', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    limpiarFormularioCompra();
    modoEdicionCompra = true;
    compraIdActual = id;
    document.getElementById('modalCompraTitulo').textContent = 'Editar compra #' + id;

    const c = json.compra;
    document.getElementById('cmp_fecha_compra').value = (c.fecha_compra || '').split(' ')[0];
    document.getElementById('cmp_descripcion').value = c.descripcion || '';
    document.getElementById('cmp_total_img_cargado').value = c.total_img_cargado ?? '';

    seleccionarProveedorCompra({ ruc: c.proveedor_id, razon_social: c.razon_social, nombre_comercial: c.nombre_comercial });

    comprobanteUrlActual = c.img_comprobante || null;
    renderComprobantePreview();

    await obtenerUnidadesCompra();
    const detalle = json.detalle || [];
    ticketDetalleCompra = detalle.map(d => ({
        tempId: ++contadorLineaTicketCompra,
        id: d.id,
        material_id: d.material_id,
        material_nombre: d.material_nombre,
        unidad_medida_id: d.unidad_medida_id,
        unidad_texto: `${d.unidad_nombre ?? ''} (${d.unidad_corto ?? ''})`,
        cantidad: parseFloat(d.cantidad),
        sub_total: parseFloat(d.sub_total),
        total: parseFloat(d.total),
        comentario: d.comentario,
        usado_en_produccion: !!d.usado_en_produccion,
    }));
    renderTicketCompra();

    modalCompra.show();
}

document.getElementById('formCompra').addEventListener('submit', async function (e) {
    e.preventDefault();

    if (!proveedorSeleccionadoCompra) {
        Swal.fire('Falta el proveedor', 'Selecciona el proveedor de esta compra.', 'warning');
        return;
    }
    if (!document.getElementById('cmp_fecha_compra').value) {
        Swal.fire('Falta la fecha', 'Indica la fecha de la compra.', 'warning');
        return;
    }
    if (ticketDetalleCompra.length === 0) {
        Swal.fire('Falta agregar materiales', 'Debes agregar al menos un material con cantidad y unidad válidas.', 'warning');
        return;
    }

    const btnGuardar = document.getElementById('cmp_btn_guardar');
    btnGuardar.disabled = true;
    btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';

    try {
        const fd = new FormData();
        fd.append('accion', 'GUARDARCOMPRA');
        fd.append('id', compraIdActual);
        fd.append('proveedor_id', proveedorSeleccionadoCompra.ruc);
        fd.append('fecha_compra', document.getElementById('cmp_fecha_compra').value);
        fd.append('descripcion', document.getElementById('cmp_descripcion').value.trim());
        fd.append('detalle', obtenerDetalleJsonCompra());
        fd.append('total_img_cargado', document.getElementById('cmp_total_img_cargado').value);
        fd.append('eliminar_comprobante', eliminarComprobanteFlag ? '1' : '0');
        if (comprobanteArchivo) fd.append('img_comprobante', comprobanteArchivo);

        const resp = await fetch(CONTROLADOR_COMPRA, { method: 'POST', body: fd });
        const json = await resp.json();

        if (json.success) {
            modalCompra.hide();
            Swal.fire('Listo', json.message, 'success');
            cargarCompras();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    } catch (err) {
        console.error(err);
        Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
    } finally {
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = 'Guardar';
    }
});

const modalCamaraComprobanteTablet = new bootstrap.Modal(document.getElementById('modalCamaraComprobanteTablet'));
let streamCamaraComprobanteTablet = null;
let capturaComprobanteTabletBlob = null;

async function abrirModalCamaraComprobanteTablet() {
    const video = document.getElementById('videoCamaraComprobanteTablet');
    const errorEl = document.getElementById('camaraComprobanteTabletError');

    video.style.display = '';
    document.getElementById('previewCamaraComprobanteTablet').style.display = 'none';
    errorEl.style.display = 'none';
    document.getElementById('btnCapturarFotoComprobanteTablet').style.display = '';
    document.getElementById('btnCapturarFotoComprobanteTablet').disabled = false;
    document.getElementById('btnRepetirFotoComprobanteTablet').style.display = 'none';
    document.getElementById('btnUsarFotoComprobanteTablet').style.display = 'none';

    modalCamaraComprobanteTablet.show();

    try {
        streamCamaraComprobanteTablet = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } },
            audio: false
        });
        video.srcObject = streamCamaraComprobanteTablet;
    } catch (err) {
        console.error('No se pudo acceder a la cámara:', err);
        errorEl.textContent = 'No se pudo acceder a la cámara. Revisa los permisos o usa "Subir archivo".';
        errorEl.style.display = 'block';
        document.getElementById('btnCapturarFotoComprobanteTablet').disabled = true;
    }
}

function detenerStreamCamaraTablet() {
    if (streamCamaraComprobanteTablet) {
        streamCamaraComprobanteTablet.getTracks().forEach(t => t.stop());
        streamCamaraComprobanteTablet = null;
    }
}

function capturarFotoComprobanteTablet() {
    const video = document.getElementById('videoCamaraComprobanteTablet');
    const canvas = document.getElementById('canvasCamaraComprobanteTablet');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);

    canvas.toBlob((blob) => {
        capturaComprobanteTabletBlob = blob;
        document.getElementById('imgPreviewCamaraComprobanteTablet').src = URL.createObjectURL(blob);
        video.style.display = 'none';
        document.getElementById('previewCamaraComprobanteTablet').style.display = '';
        document.getElementById('btnCapturarFotoComprobanteTablet').style.display = 'none';
        document.getElementById('btnRepetirFotoComprobanteTablet').style.display = '';
        document.getElementById('btnUsarFotoComprobanteTablet').style.display = '';
        detenerStreamCamaraTablet();
    }, 'image/jpeg', 0.9);
}

function repetirFotoComprobanteTablet() {
    capturaComprobanteTabletBlob = null;
    abrirModalCamaraComprobanteTablet();
}

function usarFotoComprobanteTablet() {
    modalCamaraComprobanteTablet.hide();
    document.getElementById('cmp_img_comprobante').value = '';
    comprobanteArchivo = new File([capturaComprobanteTabletBlob], 'comprobante_camara.jpg', { type: 'image/jpeg' });
    eliminarComprobanteFlag = false;
    renderComprobantePreview();
}

document.getElementById('modalCamaraComprobanteTablet').addEventListener('hidden.bs.modal', detenerStreamCamaraTablet);

</script>
</body>
</html>