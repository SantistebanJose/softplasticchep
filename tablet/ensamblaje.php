<?php
session_start();
require __DIR__ . '/../controllers_tablet/clssAuthOperario.php';

if (empty($_SESSION['operario_id'])) {
    header('Location: loginoperarios.php');
    exit;
}

exigirAccesoEtapa('ENSAMBLA', 'Ensamblaje'); // <-- NUEVO

$operarioId     = (int) $_SESSION['operario_id'];
$operarioNombre = $_SESSION['operario_nombre'] ?? 'Operario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Ensamblaje · Plásticos Chepito</title>
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
            <span class="pc-op-brand-name">Ensamblaje</span>
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

.pc-tabs-toolbar{ display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; border-bottom:1px solid #e7e4dd; margin-bottom:18px; }
.pc-tabs-row{ display:flex; align-items:center; gap:20px; flex-wrap:wrap; row-gap:6px; overflow-x:auto; -webkit-overflow-scrolling:touch; }
.pc-tab-item{ display:flex; align-items:center; gap:8px; padding:12px 4px 14px 4px; border:none; background:none; cursor:pointer; font-size:1em; font-weight:600; color:#8a8578; border-bottom:2px solid transparent; white-space:nowrap; }
.pc-tab-item.activo{ color:#152238; border-bottom-color:#2F6FED; }
.pc-tab-item .cnt{ background:#EEECE6; color:#5c5947; font-size:.78em; font-weight:700; border-radius:999px; padding:3px 9px; min-width:20px; text-align:center; }
.pc-tab-item.activo .cnt{ background:#152238; color:#fff; }

.pc-ens-group{ margin-bottom:26px; }
.pc-ens-group-header{ display:flex; align-items:center; gap:10px; margin:4px 0 12px 0; }
.pc-ens-group-header .linea{ flex:1; height:1px; background:#e7e4dd; }
.pc-ens-group-header .texto{
    font-size:.8em; font-weight:800; letter-spacing:.06em; text-transform:uppercase;
    color:#8a5a10; background:#FDF1E0; border:1px solid #f0dcae; border-radius:999px;
    padding:7px 16px; white-space:nowrap; display:flex; align-items:center; gap:6px;
}
.pc-ens-group-count{ font-weight:600; color:#b8834a; opacity:.85; }

.pc-ens-grid{ display:grid; grid-template-columns:repeat(auto-fill, minmax(320px,1fr)); gap:16px; margin-top:4px; }
.pc-ens-card{ border:1px solid #ece9e1; border-radius:16px; background:#fff; padding:18px; display:flex; flex-direction:column; gap:10px; }
.pc-ens-card-top{ display:flex; align-items:center; gap:8px; }
.pc-ens-dot{ width:8px; height:8px; border-radius:50%; flex:0 0 auto; background:#c8c3b4; }
.pc-ens-card.estado-curso .pc-ens-dot{ background:#0B4DA6; animation:pc-pulse-blue 1.6s infinite; }
.pc-ens-card.estado-fin .pc-ens-dot{ background:#16A34A; }
.pc-ens-id{ font-size:.8em; color:#a7a293; font-weight:600; }
.pc-ens-estado-txt{ font-size:.8em; color:#8a8578; font-weight:600; }
.pc-ens-card.estado-curso .pc-ens-estado-txt{ color:#0B4DA6; }
.pc-ens-card.estado-fin .pc-ens-estado-txt{ color:#16A34A; }
.pc-ens-card-spacer{ flex:1; }
.pc-ens-edit-btn{ border:none; background:none; color:#c3beae; padding:10px; font-size:1.05em; cursor:pointer; border-radius:8px; min-width:44px; min-height:44px; }
.pc-ens-edit-btn:active{ color:#2F6FED; background:#EAF0FE; }
.pc-ens-title{ font-size:1.2em; font-weight:700; color:#1f2430; line-height:1.25; }
.pc-ens-meta{ font-size:.87em; color:#9a9585; line-height:1.4; }
.pc-ens-meta span:not(:last-child)::after{ content:"·"; margin:0 6px; color:#d8d4c8; }
.pc-ens-stats{ display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; }
.pc-ens-stat{ min-width:0; }
.pc-ens-stat .num{ font-size:1.25em; font-weight:700; color:#1f2430; line-height:1.15; overflow-wrap:break-word; }
.pc-ens-stat .lbl{ font-size:.68em; color:#9a9585; margin-top:3px; text-transform:uppercase; letter-spacing:.02em; line-height:1.25; overflow-wrap:break-word; }
.pc-ens-tags{ display:flex; flex-wrap:wrap; gap:6px; }
.pc-ens-tag{ font-size:.78em; color:#8a8578; background:#f6f4ee; border-radius:6px; padding:5px 10px; font-weight:600; display:inline-flex; align-items:center; gap:5px; }
.pc-ens-tag.complemento{ background:#F1EAFD; color:#7C3AED; }
.pc-ens-tag.usado{ background:#FDF1E0; color:#D97706; }
.pc-ens-tag.libre{ background:#E8F7EE; color:#16A34A; }
.pc-ens-tag.empaquetado{ background:#E2F5F3; color:#0E9488; }
.pc-ens-corrida-line{ font-size:.85em; color:#9a9585; display:flex; align-items:center; gap:6px; }
.pc-ens-corrida-line b{ color:#5c5947; font-weight:600; }
.pc-ens-card-foot{ display:flex; align-items:center; gap:8px; padding-top:12px; margin-top:2px; border-top:1px solid #f1efe8; flex-wrap:wrap; }
.pc-ens-ghost-btn{ border:none; background:#f6f4ee; color:#5c5947; font-size:.87em; font-weight:600; padding:12px 14px; border-radius:10px; display:inline-flex; align-items:center; gap:6px; cursor:pointer; min-height:48px; }
.pc-ens-ghost-btn.success{ color:#16A34A; background:#E8F7EE; }
.pc-ens-ghost-btn.warn{ color:#D97706; background:#FDF1E0; }
.pc-ens-ghost-btn.purple{ color:#7C3AED; background:#F1EAFD; }
.pc-ens-ghost-btn.teal{ color:#0E9488; background:#E2F5F3; }
.pc-ens-ghost-btn:active{ transform:scale(.97); }
.pc-ens-empty{ text-align:center; color:#9a9585; padding:50px 12px; grid-column:1/-1; font-size:1.05em; }
.pc-ens-card.pc-flash{ animation:pc-flash-bg 1.8s ease; }
@keyframes pc-flash-bg{ 0%{ background:#FFF6DC; box-shadow:0 0 0 2px #F5D98A inset; } 100%{ background:#fff; box-shadow:none; } }
@keyframes pc-pulse-blue{ 0%{ box-shadow:0 0 0 0 rgba(11,77,166,.6); } 70%{ box-shadow:0 0 0 6px rgba(11,77,166,0); } 100%{ box-shadow:0 0 0 0 rgba(11,77,166,0); } }

/* Encabezado de la tarjeta principal: título a la izquierda, acción de
   registrar a la derecha (antes flotaba como FAB tapando la esquina). */
.pc-card-header-ens{ display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px; }
.pc-card-header-ens .subt{ color:#9a9585; font-size:.9em; margin-top:2px; }

.pc-btn-registrar{
    border:none; border-radius:12px; background:#152238; color:#fff; flex:0 0 auto;
    padding:14px 22px; font-weight:700; font-size:.98em; display:flex; align-items:center; gap:10px;
    box-shadow:0 6px 16px rgba(21,34,56,.18); cursor:pointer; min-height:50px; white-space:nowrap;
}
.pc-btn-registrar:active{ transform:scale(.97); }
.pc-btn-registrar i{ font-size:1em; }
@media (max-width:560px){ .pc-btn-registrar{ width:100%; justify-content:center; } }

/* =========================================================================
   FORMULARIO DE ENSAMBLAJE — rediseño: flujo guiado en 2 pasos + ticket
   siempre visible. Nada de <select>: todo son cards ya abiertas.
   ========================================================================= */
.pc-modal-tablet .modal-content{ background:#fbfaf7; }
.pc-ens-form-body{ display:flex; flex-direction:column; gap:14px; padding-bottom:6px; height:100%; overflow-y:auto; }

/* Barra superior compacta: sucursal + operarios, simétrica en dos mitades */
.pc-ens-topbar{ display:grid; grid-template-columns:1fr 1fr; gap:14px; flex:0 0 auto; }
@media (max-width:560px){ .pc-ens-topbar{ grid-template-columns:1fr; } }
.pc-ens-topbar-group{ border:1px solid #e7e4dd; border-radius:14px; background:#fff; padding:12px 14px; }
.pc-ens-topbar-group > label{ display:flex; align-items:center; gap:6px; font-size:.78em; font-weight:800; text-transform:uppercase; letter-spacing:.04em; color:#8a8578; margin-bottom:8px; }

.pc-chip-toggle-row{ display:flex; flex-wrap:wrap; gap:8px; }
.pc-chip-toggle{ border:1px solid #e2ddcd; background:#fff; color:#5c5947; font-weight:600; font-size:.9em; padding:10px 16px; border-radius:999px; cursor:pointer; min-height:44px; }
.pc-chip-toggle.activo{ background:#152238; border-color:#152238; color:#fff; }
.pc-chip-toggle:active{ transform:scale(.96); }

.pc-op-chips-selected{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
.pc-op-chip{ display:inline-flex; align-items:center; gap:8px; background:#EAF0FE; border:1px solid #cddafc; border-radius:999px; padding:6px 8px 6px 6px; }
.pc-op-chip .pc-op-avatar{ width:26px; height:26px; border-radius:50%; background:#2F6FED; color:#fff; font-size:.72em; font-weight:800; display:flex; align-items:center; justify-content:center; text-transform:uppercase; flex:0 0 auto; }
.pc-op-chip .nom{ font-size:.88em; font-weight:600; color:#1f2430; max-width:130px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.pc-op-chip .quitar{ border:none; background:none; color:#7d8aa8; padding:4px; font-size:1em; line-height:1; }
.pc-op-chip .quitar:active{ color:#c94a4a; }
.pc-op-chip-add{ border:1.5px dashed #c7c2b3; background:none; color:#5c5947; font-weight:700; font-size:.88em; padding:9px 14px; border-radius:999px; display:inline-flex; align-items:center; gap:6px; cursor:pointer; min-height:44px; }
.pc-op-chip-add:active{ transform:scale(.96); }

/* Aviso de requisito, ahora como banner visible (antes texto gris perdido) */
.pc-ens-requisito{
    flex:0 0 auto; font-size:.85em; color:#7a5b12; background:#FFF8E8; border:1px solid #f0dcae;
    border-radius:10px; padding:10px 14px; display:flex; align-items:center; gap:8px;
}
.pc-ens-requisito i{ flex:0 0 auto; }

/* Layout principal: contenido (Producto + Vincular) a la izquierda, Ticket
   siempre visible a la derecha en tablets apaisadas; apilado en vertical
   en pantallas angostas / portrait, con el ticket como último bloque pero
   con su propio scroll interno para no perder de vista el resumen. */
.pc-ens-layout{ flex:1; min-height:0; display:flex; flex-direction:column; gap:14px; }
.pc-ens-content{ display:flex; flex-direction:column; gap:14px; }
@media (min-width:880px) and (orientation:landscape){
    .pc-ens-layout{ display:grid; grid-template-columns:1fr 320px; align-items:start; gap:14px; }
    .pc-ens-content{ max-height:100%; }
    .pc-ens-ticket-col{ position:sticky; top:0; align-self:start; display:flex; flex-direction:column; max-height:calc(100vh - 210px); }
    .pc-ens-ticket-col .pc-panel{ flex:1; min-height:0; }
}
@media (min-width:1180px) and (orientation:landscape){
    .pc-ens-layout{ grid-template-columns:1fr 340px; }
}

/* Paso numerado, para dar orden visual claro: 1. Producto, 2. Vincular */
.pc-step-badge{ width:22px; height:22px; border-radius:50%; background:#152238; color:#fff; font-size:.7em; font-weight:800; display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; }

.pc-panel{ border:1px solid #e7e4dd; border-radius:14px; background:#fff; display:flex; flex-direction:column; min-height:0; overflow:hidden; }
.pc-panel.accent-blue{ border-top:3px solid #2F6FED; }
.pc-panel.accent-violet{ border-top:3px solid #7C3AED; }
.pc-panel.accent-teal{ border-top:3px solid #0E9488; }
.pc-panel-head{ padding:12px 14px; border-bottom:1px solid #eee7db; background:#fffefb; flex:0 0 auto; }
.pc-panel-head h6{ margin:0; font-weight:700; font-size:1em; display:flex; align-items:center; gap:8px; }
.pc-panel-head .sub{ font-size:.78em; color:#9a9585; margin-top:2px; font-weight:500; }
.pc-panel-search{ padding:10px 12px 0 12px; flex:0 0 auto; }
.pc-panel-body-scroll{ flex:1; min-height:140px; max-height:44vh; overflow-y:auto; padding:12px; }
@media (min-width:880px) and (orientation:landscape){ .pc-panel-body-scroll{ max-height:none; } }

/* Panel producto: cards ya abiertas, sin combo */
.pc-prod-grid{ display:grid; grid-template-columns:repeat(auto-fill, minmax(220px,1fr)); gap:10px; }
.pc-prod-card{ position:relative; text-align:left; border:1.5px solid #eae6da; border-radius:12px; background:#fff; padding:12px; display:flex; align-items:center; gap:10px; cursor:pointer; min-height:64px; }
.pc-prod-card:active{ transform:scale(.98); }
.pc-prod-card.activo{ border-color:#2F6FED; background:#EAF0FE; }
.pc-prod-card .pellet{ width:36px; height:36px; border-radius:10px; flex:0 0 auto; display:flex; align-items:center; justify-content:center; background:var(--card-bg,#EAF0FE); color:var(--card-color,#2F6FED); font-size:1em; }
.pc-prod-card .cuerpo{ min-width:0; }
.pc-prod-card .nom{ font-weight:700; font-size:.92em; color:#1f2430; line-height:1.25; }
.pc-prod-card .disp{ font-size:.78em; color:#8a8578; margin-top:2px; }
.pc-prod-card .check{ position:absolute; top:8px; right:8px; color:#2F6FED; font-size:1em; opacity:0; }
.pc-prod-card.activo .check{ opacity:1; }

/* Panel vincular: tabs internos simétricos, ahora dentro del header */
.pc-mat-tabs{ display:flex; gap:6px; padding:10px 12px 0 12px; flex:0 0 auto; }
.pc-mat-tab{ flex:1; border:1px solid #e2ddcd; background:#fff; border-radius:10px; padding:12px 8px; font-size:.85em; font-weight:700; color:#8a8578; display:flex; align-items:center; justify-content:center; gap:6px; cursor:pointer; min-height:48px; }
.pc-mat-tab.activo{ background:#152238; border-color:#152238; color:#fff; }
.pc-mat-grid{ display:grid; grid-template-columns:repeat(auto-fill, minmax(170px,1fr)); gap:10px; }
.pc-mat-card{ position:relative; border:1px solid #eae6da; border-radius:12px; background:#fff; padding:12px 10px 10px 10px; cursor:pointer; text-align:left; min-height:104px; }
.pc-mat-card:active{ transform:scale(0.96); }
.pc-mat-card:disabled, .pc-mat-card.ya-agregada{ opacity:.4; cursor:not-allowed; }
.pc-mat-card .pellet{ width:34px; height:34px; border-radius:9px; display:flex; align-items:center; justify-content:center; background:var(--card-bg,#EAF0FE); color:var(--card-color,#2F6FED); font-size:1em; margin-bottom:8px; }
.pc-mat-card .nombre{ font-weight:600; font-size:.9em; line-height:1.25; display:block; min-height:2.2em; }
.pc-mat-card .meta{ font-size:.78em; color:#8a8578; margin-top:4px; display:block; }
.pc-mat-card .meta b{ color:#4a4636; }
.pc-mat-empty{ grid-column:1/-1; text-align:center; color:#9a9585; font-size:.92em; padding:22px 8px; }

/* Panel ticket */
.pc-tk-list{ list-style:none; margin:0; padding:0; }
.pc-tk-item{ border-bottom:1px dashed #eee2c8; padding:12px; display:flex; gap:10px; align-items:flex-start; }
.pc-tk-item:last-child{ border-bottom:none; }
.pc-tk-item .pellet-sm{ width:30px; height:30px; border-radius:8px; flex:0 0 auto; display:flex; align-items:center; justify-content:center; background:var(--card-bg,#EAF0FE); color:var(--card-color,#2F6FED); font-size:.85em; margin-top:2px; }
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

/* Modal (aparte) de selección de operarios: reemplaza al "drawer" que
   antes tapaba el panel de vincular y confundía al operario. */
.pc-op-picker-grid{ display:grid; grid-template-columns:repeat(auto-fill, minmax(180px,1fr)); gap:10px; }
.pc-op-picker-card{ border:1.5px solid #eae6da; border-radius:12px; background:#fff; padding:12px; display:flex; align-items:center; gap:10px; cursor:pointer; text-align:left; min-height:60px; position:relative; }
.pc-op-picker-card:active{ transform:scale(.97); }
.pc-op-picker-card.activo{ border-color:#2F6FED; background:#EAF0FE; }
.pc-op-picker-card .pc-op-avatar{ width:32px; height:32px; border-radius:50%; background:#2F6FED; color:#fff; font-size:.78em; font-weight:800; display:flex; align-items:center; justify-content:center; text-transform:uppercase; flex:0 0 auto; }
.pc-op-picker-card .cuerpo{ min-width:0; }
.pc-op-picker-card .nom{ font-weight:600; font-size:.9em; color:#1f2430; }
.pc-op-picker-card .cargo{ font-size:.78em; color:#8a8578; }
.pc-op-picker-card .check{ position:absolute; top:8px; right:8px; color:#2F6FED; opacity:0; }
.pc-op-picker-card.activo .check{ opacity:1; }

/* Pie del formulario: dos zonas simétricas, una para cada pulgar */
.pc-ens-footer{ display:grid; grid-template-columns:1fr 1fr; gap:12px; padding:14px calc(14px + var(--safe-l)) calc(14px + var(--safe-b)) 14px !important; }
.pc-ens-footer .btn{ min-height:54px; font-size:1.02em; font-weight:700; border-radius:12px; }
</style>

<div class="pc-card" style="margin:20px;">
    <div class="pc-card-header pc-card-header-ens">
        <div>
            <h2>Ensamblaje</h2>
            <div class="subt">Toca "Registrar ensamblaje" para armar uno nuevo.</div>
        </div>
        <button type="button" class="pc-btn-registrar" onclick="abrirModalCrearEnsamblaje()">
            <i class="fa-solid fa-plus"></i> Registrar ensamblaje
        </button>
    </div>
    <br>
    <div class="pc-stat-row" id="statRowEnsamblaje"></div>

    <div class="pc-tabs-toolbar">
        <div class="pc-tabs-row" id="ensProductoTabs"></div>
    </div>

    <div id="gridEnsamblajes">
        <div class="pc-ens-empty">Cargando...</div>
    </div>
</div>

<!-- Modal Crear/Editar -->
<div class="modal fade pc-modal-tablet" id="modalEnsamblaje" tabindex="-1">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content">
      <form id="formEnsamblaje" class="d-flex flex-column" style="height:100%;">
        <div class="modal-header">
          <h5 class="modal-title" id="modalEnsamblajeTitulo">Registrar ensamblaje</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body pc-ens-form-body">

            <!-- Barra superior: sucursal (chips) + operarios (chips + botón que abre el modal de selección) -->
            <div class="pc-ens-topbar">
                <div class="pc-ens-topbar-group">
                    <label><i class="fa-solid fa-store"></i> Sucursal</label>
                    <div class="pc-chip-toggle-row" id="ens_sucursal_chips">
                        <span class="pc-ens-requisito" style="padding:6px 10px;">Cargando sucursales...</span>
                    </div>
                </div>
                <div class="pc-ens-topbar-group">
                    <label><i class="fa-solid fa-users"></i> Operarios que participaron *</label>
                    <div class="pc-op-chips-selected" id="ens_operarios_chips"></div>
                </div>
            </div>

            <div class="pc-ens-requisito">
                <i class="fa-solid fa-circle-info"></i>
                Vincula al menos una producción finalizada, un derivado o un complemento.
            </div>

            <!-- Layout: contenido (Producto + Vincular) | Ticket siempre visible -->
            <div class="pc-ens-layout">

                <div class="pc-ens-content">

                    <!-- Paso 1: Producto -->
                    <div class="pc-panel accent-blue">
                        <div class="pc-panel-head">
                            <h6><span class="pc-step-badge">1</span> <i class="fa-solid fa-box"></i> Producto a ensamblar</h6>
                        </div>
                        <div class="pc-panel-search">
                            <input type="text" id="ens_buscar_producto" class="form-control form-control-lg" placeholder="Buscar producto...">
                        </div>
                        <div class="pc-panel-body-scroll">
                            <div class="pc-prod-grid" id="ens_producto_grid">
                                <div class="pc-mat-empty">Cargando productos...</div>
                            </div>
                        </div>
                    </div>

                    <!-- Paso 2: Vincular producciones / derivados / complementos -->
                    <div class="pc-panel accent-violet" id="pc_panel_vincular">
                        <div class="pc-panel-head">
                            <h6><span class="pc-step-badge">2</span> <i class="fa-solid fa-link"></i> Vincular al armado</h6>
                        </div>
                        <div class="pc-mat-tabs">
                            <button type="button" class="pc-mat-tab activo" id="tab_producciones" onclick="cambiarTabDetalle('produccion')">
                                <i class="fa-solid fa-industry"></i> Producciones
                            </button>
                            <button type="button" class="pc-mat-tab" id="tab_derivados" onclick="cambiarTabDetalle('derivado')">
                                <i class="fa-solid fa-flask"></i> Derivados
                            </button>
                            <button type="button" class="pc-mat-tab" id="tab_complementos" onclick="cambiarTabDetalle('complemento')">
                                <i class="fa-solid fa-puzzle-piece"></i> Complemento
                            </button>
                        </div>
                        <div class="pc-panel-search">
                            <input type="text" id="ens_buscar_detalle" class="form-control form-control-lg" placeholder="Buscar...">
                        </div>
                        <div class="pc-panel-body-scroll">
                            <div class="pc-mat-grid" id="ens_detalle_grid">
                                <div class="pc-mat-empty">Selecciona un producto para ver producciones disponibles.</div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Ticket: columna fija en tablet apaisada, bloque final en vertical -->
                <div class="pc-ens-ticket-col">
                    <div class="pc-panel accent-teal">
                        <div class="pc-panel-head"><h6><i class="fa-solid fa-receipt"></i> Ticket de este armado</h6></div>
                        <div class="pc-panel-body-scroll" style="padding:0;">
                            <ul class="pc-tk-list" id="ens_ticket_list">
                                <li class="pc-tk-empty"><i class="fa-solid fa-basket-shopping"></i>Aún no vinculas nada.<br>Toca una card de arriba para empezar.</li>
                            </ul>
                        </div>
                        <div class="pc-tk-resumen">
                            <div class="pc-tk-resumen-icon"><i class="fa-solid fa-layer-group"></i></div>
                            <div class="pc-tk-resumen-texto">
                                <span class="total"><b id="ens_ticket_total">0</b> ítem(s)</span>
                                <span class="detalle" id="ens_ticket_detalle">0 producción(es) · 0 derivado(s) · 0 complemento(s)</span>
                                <span class="detalle">Peso producido vinculado: <b id="ens_ticket_peso_producido">0</b> kg</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>

        <!-- Pie: dos zonas simétricas, una por pulgar, al sostener la tablet con ambas manos -->
        <div class="modal-footer pc-ens-footer">
          <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary btn-lg">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal aparte para elegir operarios (antes era un "drawer" que tapaba
     el panel de vincular; ahora es un diálogo independiente que no
     interrumpe el flujo de armado). -->
<div class="modal fade pc-modal-tablet" id="modalOperariosEns" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-users"></i> Elige quién participó</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="text" id="ens_buscar_operario" class="form-control form-control-lg mb-3" placeholder="Buscar operario...">
        <div class="pc-op-picker-grid" id="ens_operario_picker_grid">
            <div class="pc-mat-empty">Cargando operarios...</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary btn-lg w-100" data-bs-dismiss="modal">Listo</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const OPERARIO_ID     = <?= json_encode($operarioId) ?>;
const OPERARIO_NOMBRE = <?= json_encode($operarioNombre) ?>;

const CONTROLADOR_ENSAMBLAJE = '../controllers/clssEnsamblaje.php';
const CONTROLADOR_SUCURSAL   = '../controllers/clssSucursal.php';
const modalEnsamblaje   = new bootstrap.Modal(document.getElementById('modalEnsamblaje'));
const modalOperariosEns = new bootstrap.Modal(document.getElementById('modalOperariosEns'));

let modoEdicionEnsamblaje = false;
let ensamblajeIdActual = 0;
let tabDetalleActiva = 'produccion'; // 'produccion' | 'derivado' | 'complemento'
let contadorLineaTicketEns = 0;
let ticketDetalleEns = [];
let productosDisponiblesEnsCache = null;
let ensamblajesCache = [];
let productoTabActivoEns = null;
let sucursalesEnsCache = null;

// ── Estado de selección "en cards" (reemplaza a los <select>) ──────────
let productoSeleccionadoEns = { producto_id: null, color_id: null };
let productosGridDataEns = [];   // productos ya cargados para el picker
let sucursalSeleccionadaEns = ''; // id de sucursal o ''
let operariosCatalogoEns = [];    // catálogo completo de operarios
let operariosSeleccionadosEns = []; // [{id, nombre_completo, cargo}]

document.addEventListener('DOMContentLoaded', () => {
    cargarEnsamblajes().catch(err => {
        console.error('Error cargando datos iniciales:', err);
        document.getElementById('gridEnsamblajes').innerHTML =
            `<div class="pc-ens-empty" style="color:red;">Error de conexión con el servidor.</div>`;
    });

    let debounceDetalle = null;
    document.getElementById('ens_buscar_detalle').addEventListener('input', () => {
        clearTimeout(debounceDetalle);
        debounceDetalle = setTimeout(renderGridDetalle, 300);
    });

    let debounceProducto = null;
    document.getElementById('ens_buscar_producto').addEventListener('input', () => {
        clearTimeout(debounceProducto);
        debounceProducto = setTimeout(() => renderProductoGridEns(), 250);
    });

    let debounceOperario = null;
    document.getElementById('ens_buscar_operario').addEventListener('input', () => {
        clearTimeout(debounceOperario);
        debounceOperario = setTimeout(() => renderOperarioPickerGrid(), 250);
    });

    // Al cerrar el picker de operarios (con "Listo", el X, o tocando fuera),
    // refresca los chips por si hubo cambios.
    document.getElementById('modalOperariosEns').addEventListener('hidden.bs.modal', renderOperariosChipsEns);

    iniciarAutoRefreshEns();
});

// ── Auto-refresh silencioso, igual patrón que Producción ───────────────────
const POLL_INTERVAL_MS_ENS = 8000;
let pollTimerEns = null;
let snapshotEstadosEns = {};

function iniciarAutoRefreshEns() {
    if (pollTimerEns) clearInterval(pollTimerEns);
    pollTimerEns = setInterval(() => {
        if (document.hidden) return;
        if (modalEnsamblaje._element.classList.contains('show')) return;
        cargarEnsamblajes(true);
    }, POLL_INTERVAL_MS_ENS);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) cargarEnsamblajes(true);
    });

    document.getElementById('modalEnsamblaje').addEventListener('hidden.bs.modal', () => {
        cargarEnsamblajes(true);
    });
}

// ── Llamadas genéricas ───────────────────────────────────────────────────
async function llamarEnsamblaje(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_ENSAMBLAJE, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body
    });
    const texto = await resp.text();
    try { return JSON.parse(texto); }
    catch (e) {
        console.error(`Respuesta no es JSON válido para accion=${accion}:`, texto);
        throw new Error(`El servidor no devolvió JSON válido (accion=${accion}).`);
    }
}

async function llamarSucursal(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_SUCURSAL, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
    return resp.json();
}
async function obtenerSucursalesEns() {
    if (sucursalesEnsCache) return sucursalesEnsCache;
    const json = await llamarSucursal('LISTARSUCURSALES', { visibilidad: 'activas' });
    sucursalesEnsCache = json.success ? json.sucursales : [];
    return sucursalesEnsCache;
}

// ── Utilidades de formato ───────────────────────────────────────────────
function formatearCantidadEns(n) {
    if (n === null || n === undefined || n === '') return '-';
    return Number(n).toLocaleString('es-PE', { maximumFractionDigits: 4 });
}
function formatearFechaHoraLegibleEns(fechaIso) {
    if (!fechaIso) return '';
    const [fecha, hora] = fechaIso.split(' ');
    if (!fecha) return fechaIso;
    const [y, m, d] = fecha.split('-');
    return `${d}/${m}/${y}${hora ? ' ' + hora.substring(0, 5) : ''}`;
}
function formatearFechaCortaEns(fechaIso) {
    if (!fechaIso) return '';
    const [fecha] = fechaIso.split(' ');
    if (!fecha) return fechaIso;
    const [, m, d] = fecha.split('-');
    return `${d}/${m}`;
}
function parseJsonColumna(v) {
    if (!v) return [];
    if (typeof v === 'string') { try { return JSON.parse(v) || []; } catch (e) { return []; } }
    return Array.isArray(v) ? v : [];
}
function parseJsonObjetoColumna(v) {
    if (!v) return null;
    if (typeof v === 'string') { try { return JSON.parse(v) || null; } catch (e) { return null; } }
    return (typeof v === 'object' && !Array.isArray(v)) ? v : null;
}

const PALETA_RESINA = [
    { color: '#2F6FED', bg: '#EAF0FE' }, { color: '#E23744', bg: '#FCEAEC' },
    { color: '#16A34A', bg: '#E8F7EE' }, { color: '#D97706', bg: '#FDF1E0' },
    { color: '#7C3AED', bg: '#F1EAFD' }, { color: '#0E9488', bg: '#E2F5F3' },
];
function estiloPorNombre(nombre) {
    let hash = 0;
    const str = nombre || '';
    for (let i = 0; i < str.length; i++) hash = (hash * 31 + str.charCodeAt(i)) >>> 0;
    return PALETA_RESINA[hash % PALETA_RESINA.length];
}

function estadoCortoEns(e) {
    if (!e.inicio) return 'sin';
    if (!e.fin) return 'curso';
    return 'fin';
}

// Categoría del armado actual, derivada de las producciones ya agregadas al ticket.
function categoriaMaterialTicketActual() {
    const cats = ticketDetalleEns
        .filter(l => l.tipo === 'produccion' && l.categoria_material_id)
        .map(l => l.categoria_material_id);
    if (cats.length === 0) return null;
    return new Set(cats).size === 1 ? cats[0] : null;
}

function obtenerInicialesOperarioEns(nombreCompleto) {
    const partes = (nombreCompleto || '').trim().split(/\s+/).filter(Boolean);
    if (partes.length === 0) return '?';
    if (partes.length === 1) return partes[0].substring(0, 2).toUpperCase();
    return (partes[0][0] + partes[1][0]).toUpperCase();
}

// =============================================================================
// PANEL 1: PRODUCTO — cards ya abiertas (sin combo)
// =============================================================================

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

function renderProductoGridEns() {
    const cont = document.getElementById('ens_producto_grid');
    const filtro = (document.getElementById('ens_buscar_producto').value || '').trim().toLowerCase();
    const lista = filtro
        ? productosGridDataEns.filter(p => (p.productoformato || '').toLowerCase().includes(filtro))
        : productosGridDataEns;

    if (lista.length === 0) {
        cont.innerHTML = '<div class="pc-mat-empty">No se encontraron productos.</div>';
        return;
    }

    cont.innerHTML = lista.map(p => {
        const est = estiloPorNombre(p.productoformato || '');
        const activo = productoSeleccionadoEns.producto_id == p.producto_id && productoSeleccionadoEns.color_id == p.color_id;
        return `
        <button type="button" class="pc-prod-card ${activo ? 'activo' : ''}"
                style="--card-color:${est.color};--card-bg:${est.bg};"
                onclick="seleccionarProductoEns('${p.producto_id}','${p.color_id ?? ''}')">
            <span class="pellet"><i class="fa-solid fa-box"></i></span>
            <span class="cuerpo">
                <span class="nom">${p.productoformato}</span>
                <span class="disp">${p.disponibles} disponible(s)</span>
            </span>
            <i class="fa-solid fa-circle-check check"></i>
        </button>`;
    }).join('');
}

function seleccionarProductoEns(productoId, colorId) {
    productoSeleccionadoEns = { producto_id: productoId, color_id: colorId || null };
    renderProductoGridEns();
    cambioProductoEnsamblaje();
}

function obtenerProductoIdSeleccionadoEns() {
    return productoSeleccionadoEns.producto_id || '';
}

function cambioProductoEnsamblaje() {
    renderGridDetalle();
}

// =============================================================================
// BARRA SUPERIOR: sucursal (chips) + operarios (chips + modal de selección)
// =============================================================================

function renderSucursalChipsEns(sucursales) {
    const cont = document.getElementById('ens_sucursal_chips');
    if (!sucursales || sucursales.length === 0) {
        cont.innerHTML = '<span class="pc-ens-requisito" style="padding:6px 10px;">No hay sucursales activas.</span>';
        return;
    }
    cont.innerHTML = sucursales.map(s => `
        <button type="button" class="pc-chip-toggle ${sucursalSeleccionadaEns == s.id ? 'activo' : ''}"
                onclick="seleccionarSucursalEns('${s.id}')">${s.nombre}</button>
    `).join('');
}
function seleccionarSucursalEns(id) {
    sucursalSeleccionadaEns = (sucursalSeleccionadaEns == id) ? '' : id; // toca de nuevo para des-seleccionar
    renderSucursalChipsEns(sucursalesEnsCache || []);
}

function renderOperariosChipsEns() {
    const cont = document.getElementById('ens_operarios_chips');
    const chips = operariosSeleccionadosEns.map(o => `
        <span class="pc-op-chip">
            <span class="pc-op-avatar">${obtenerInicialesOperarioEns(o.nombre_completo)}</span>
            <span class="nom">${o.nombre_completo}</span>
            <button type="button" class="quitar" onclick="quitarOperarioEns(${o.id})" title="Quitar"><i class="fa-solid fa-xmark"></i></button>
        </span>
    `).join('');
    cont.innerHTML = chips + `
        <button type="button" class="pc-op-chip-add" onclick="abrirPickerOperarios()">
            <i class="fa-solid fa-plus"></i> Agregar
        </button>`;
}

function quitarOperarioEns(id) {
    operariosSeleccionadosEns = operariosSeleccionadosEns.filter(o => o.id != id);
    renderOperariosChipsEns();
    renderOperarioPickerGrid();
}

// El picker de operarios ahora es un modal independiente (no tapa el panel
// de vincular ni el ticket): se puede abrir/cerrar en cualquier momento sin
// perder el contexto del armado que se está haciendo.
function abrirPickerOperarios() {
    renderOperarioPickerGrid();
    modalOperariosEns.show();
}
function cerrarPickerOperarios() {
    modalOperariosEns.hide();
}

function renderOperarioPickerGrid() {
    const cont = document.getElementById('ens_operario_picker_grid');
    const filtro = (document.getElementById('ens_buscar_operario').value || '').trim().toLowerCase();
    const lista = filtro
        ? operariosCatalogoEns.filter(o => (o.nombre_completo || '').toLowerCase().includes(filtro))
        : operariosCatalogoEns;

    if (lista.length === 0) {
        cont.innerHTML = '<div class="pc-mat-empty">No se encontraron operarios.</div>';
        return;
    }

    cont.innerHTML = lista.map(o => {
        const activo = operariosSeleccionadosEns.some(sel => sel.id == o.id);
        return `
        <button type="button" class="pc-op-picker-card ${activo ? 'activo' : ''}" onclick="toggleOperarioEns(${o.id})">
            <span class="pc-op-avatar">${obtenerInicialesOperarioEns(o.nombre_completo)}</span>
            <span class="cuerpo">
                <span class="nom">${o.nombre_completo}</span>
                ${o.cargo ? `<span class="cargo">${o.cargo}</span>` : ''}
            </span>
            <i class="fa-solid fa-circle-check check"></i>
        </button>`;
    }).join('');
}

function toggleOperarioEns(id) {
    const existe = operariosSeleccionadosEns.some(o => o.id == id);
    if (existe) {
        operariosSeleccionadosEns = operariosSeleccionadosEns.filter(o => o.id != id);
    } else {
        const op = operariosCatalogoEns.find(o => o.id == id);
        if (op) operariosSeleccionadosEns.push(op);
    }
    renderOperarioPickerGrid();
    renderOperariosChipsEns();
}

// ── Carga de todos los "paneles" del modal (antes: selects) ────────────
async function cargarSelectsModalEns(seleccion = {}, incluirEnsamblajeId = 0) {
    const [productos, operario, sucursales] = await Promise.all([
        obtenerProductosDisponiblesEns(incluirEnsamblajeId),
        llamarEnsamblaje('BUSCAROPERARIOS'),
        obtenerSucursalesEns(),
    ]);

    // Producto
    productosGridDataEns = productos || [];
    if (seleccion.producto_id) {
        productoSeleccionadoEns = { producto_id: String(seleccion.producto_id), color_id: seleccion.color_id != null ? String(seleccion.color_id) : null };
    }
    renderProductoGridEns();

    // Sucursal
    sucursalSeleccionadaEns = seleccion.sucursal_id ? String(seleccion.sucursal_id) : '';
    renderSucursalChipsEns(sucursales || []);

    // Operarios
    operariosCatalogoEns = operario.success ? operario.operario : [];
    if (Array.isArray(seleccion.operario_ids) && seleccion.operario_ids.length > 0) {
        operariosSeleccionadosEns = operariosCatalogoEns.filter(o => seleccion.operario_ids.some(id => id == o.id));
    } else {
        // Al crear un ensamblaje nuevo se autoselecciona al operario logueado en la tablet.
        const yo = operariosCatalogoEns.find(o => o.id == OPERARIO_ID);
        operariosSeleccionadosEns = yo ? [yo] : [];
    }
    renderOperariosChipsEns();
    renderOperarioPickerGrid();
}

// ── Agrupación por producto ("escalera") ────────────────────────────────
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
    let html = `<button type="button" class="pc-tab-item ${productoTabActivoEns === 'TODOS' ? 'activo' : ''}" onclick="seleccionarTabProductoEns('TODOS')"><i class="fa-solid fa-grip"></i> Todos <span class="cnt">${totalGeneral}</span></button>`;
    for (const [nombreProducto, items] of grupos) {
        const nombreEscapado = nombreProducto.replace(/'/g, "\\'");
        html += `<button type="button" class="pc-tab-item ${productoTabActivoEns === nombreProducto ? 'activo' : ''}" onclick="seleccionarTabProductoEns('${nombreEscapado}')"><i class="fa-solid fa-layer-group"></i> ${nombreProducto} <span class="cnt">${items.length}</span></button>`;
    }
    contenedor.innerHTML = html;
}
function seleccionarTabProductoEns(nombre) {
    productoTabActivoEns = nombre;
    const grupos = agruparEnsamblajesPorProducto(ensamblajesCache);
    renderTabsProductoEns(grupos);
    renderGridEnsamblajes(ensamblajesCache, false);
}

function renderStatRowEns(ensamblajes) {
    const activos = ensamblajes.filter(e => !e.deleted_at);
    const sinIniciar = activos.filter(e => estadoCortoEns(e) === 'sin').length;
    const enCurso = activos.filter(e => estadoCortoEns(e) === 'curso').length;
    const finalizados = activos.filter(e => estadoCortoEns(e) === 'fin').length;
    const complementados = activos.filter(e => parseJsonObjetoColumna(e.js_producto_emsamblado)).length;

    document.getElementById('statRowEnsamblaje').innerHTML = `
        <div class="pc-stat-chip s-gray"><div class="ico"><i class="fa-solid fa-hourglass-half"></i></div><div class="txt"><div class="n">${sinIniciar}</div><div class="l">Sin iniciar</div></div></div>
        <div class="pc-stat-chip s-info"><div class="ico"><i class="fa-solid fa-gear"></i></div><div class="txt"><div class="n">${enCurso}</div><div class="l">En curso</div></div></div>
        <div class="pc-stat-chip s-success"><div class="ico"><i class="fa-solid fa-flag-checkered"></i></div><div class="txt"><div class="n">${finalizados}</div><div class="l">Finalizados</div></div></div>
        <div class="pc-stat-chip s-purple"><div class="ico"><i class="fa-solid fa-puzzle-piece"></i></div><div class="txt"><div class="n">${complementados}</div><div class="l">Complementados</div></div></div>
    `;
}

// ── Card individual de ensamblaje ───────────────────────────────────────
function tarjetaEnsamblajeHtml(e, nuevosEstados, silencioso) {
    const estado = estadoCortoEns(e);
    nuevosEstados[e.ensamblaje_id] = estado;
    const cambioDeEstado = silencioso && snapshotEstadosEns[e.ensamblaje_id] && snapshotEstadosEns[e.ensamblaje_id] !== estado;

    const producciones = e.producciones_count ?? parseJsonColumna(e.js_moldes_utilizados).length;
    const derivadosCount = e.derivados_count ?? parseJsonColumna(e.js_derivados_utilizados).length;
    const complementosCount = e.complementos_count ?? parseJsonColumna(e.js_complementos_utilizados).length;

    const puedeIniciar   = !e.deleted_at && !e.inicio;
    const puedeFinalizar = !e.deleted_at && e.inicio && !e.fin;
    const productoEmsamblado = parseJsonObjetoColumna(e.js_producto_emsamblado);
    const esDePrimera = (e.categoria_material_nombre ?? '').trim().toLowerCase() === 'de primera';
    const puedeDecidirDestino = !e.deleted_at && e.fin && !productoEmsamblado && !e.enviado_empaquetado;
    const puedeComplementar = puedeDecidirDestino && esDePrimera;
    const complementoUsado = !!e.ensamblaje_id_referido;

    const metaPartes = [];
    if (parseJsonColumna(e.js_operarios).length > 0) {
        metaPartes.push(parseJsonColumna(e.js_operarios).map(o => o.nombre_completo).join(', '));
    }
    if (e.sucursal_nombre) metaPartes.push(e.sucursal_nombre);
    if (e.inicio) metaPartes.push(formatearFechaCortaEns(e.inicio));

    const tags = [];
    if (e.categoria_material_nombre) tags.push(e.categoria_material_nombre);
    if (productoEmsamblado) {
        tags.push({ texto: `Complementa a ${productoEmsamblado.codigo ?? ''}`, clase: 'complemento' });
        tags.push({ texto: complementoUsado ? `Usado en #${e.ensamblaje_id_referido}` : 'Disponible para vincular', clase: complementoUsado ? 'usado' : 'libre' });
    }
    if (e.enviado_empaquetado) tags.push({ texto: `Empaquetado ${formatearFechaCortaEns(e.fecha_envio_empaquetado)}`, clase: 'empaquetado' });

    const textoEstadoMap = { sin: 'Sin iniciar', curso: 'En curso', fin: 'Finalizado' };

    let lineaCorrida = 'Armado sin iniciar';
    if (e.inicio && !e.fin) lineaCorrida = `Iniciado · <b>${formatearFechaHoraLegibleEns(e.inicio)}</b>`;
    else if (e.inicio && e.fin) lineaCorrida = `Inicio <b>${formatearFechaHoraLegibleEns(e.inicio)}</b> — Fin <b>${formatearFechaHoraLegibleEns(e.fin)}</b>`;

    return `
    <div class="pc-ens-card estado-${estado} ${cambioDeEstado ? 'pc-flash' : ''}" id="fila-ensamblaje-${e.ensamblaje_id}">
        <div class="pc-ens-card-top">
            <span class="pc-ens-dot"></span>
            <span class="pc-ens-id">#${e.ensamblaje_id}</span>
            <span class="pc-ens-estado-txt">${textoEstadoMap[estado]}</span>
            <span class="pc-ens-card-spacer"></span>
            <button type="button" class="pc-ens-edit-btn" onclick="abrirModalEditarEnsamblaje(${e.ensamblaje_id})" title="Editar">
                <i class="fa-solid fa-pen"></i>
            </button>
        </div>
        <div class="pc-ens-title">${e.producto_codigo ?? ''} - ${e.producto_descripcion ?? '-'}</div>
        <div class="pc-ens-meta">${metaPartes.map(t => `<span>${t}</span>`).join('')}</div>
        <div class="pc-ens-stats">
            <div class="pc-ens-stat"><div class="num">${producciones}</div><div class="lbl">Producciones</div></div>
            <div class="pc-ens-stat"><div class="num">${derivadosCount}</div><div class="lbl">Derivados</div></div>
            <div class="pc-ens-stat"><div class="num">${complementosCount}</div><div class="lbl">Complementos</div></div>
            <div class="pc-ens-stat"><div class="num">${formatearCantidadEns(e.cantidad_peso_kg)}</div><div class="lbl">${e.unidad_salida_codigo || e.producto_unidad_ensamblaje_codigo || 'kg'} salida</div></div>
        </div>
        ${tags.length ? `<div class="pc-ens-tags">${tags.map(t => typeof t === 'string' ? `<span class="pc-ens-tag">${t}</span>` : `<span class="pc-ens-tag ${t.clase}">${t.texto}</span>`).join('')}</div>` : ''}
        <div class="pc-ens-corrida-line"><i class="fa-regular fa-clock"></i> ${lineaCorrida}</div>
        <div class="pc-ens-card-foot">
            ${puedeIniciar ? `<button type="button" class="pc-ens-ghost-btn success" onclick="iniciarEnsamblajeAccion(${e.ensamblaje_id})"><i class="fa-solid fa-play"></i> Iniciar</button>` : ''}
            ${puedeFinalizar ? `<button type="button" class="pc-ens-ghost-btn warn" onclick="finalizarEnsamblajeAccion(${e.ensamblaje_id})"><i class="fa-solid fa-flag-checkered"></i> Finalizar</button>` : ''}
            ${puedeComplementar ? `<button type="button" class="pc-ens-ghost-btn purple" onclick="marcarComplementoAccion(${e.ensamblaje_id})"><i class="fa-solid fa-puzzle-piece"></i> Complementar</button>` : ''}
            ${puedeDecidirDestino ? `<button type="button" class="pc-ens-ghost-btn teal" onclick="pasarAEmpaquetadoAccion(${e.ensamblaje_id})"><i class="fa-solid fa-box"></i> A Empaquetado</button>` : ''}
        </div>
    </div>`;
}

function renderGridEnsamblajes(ensamblajes, silencioso) {
    const grid = document.getElementById('gridEnsamblajes');

    if (ensamblajes.length === 0) {
        grid.innerHTML = '<div class="pc-ens-empty">No tienes armados de ensamblaje registrados todavía.</div>';
        snapshotEstadosEns = {};
        return;
    }

    const nuevosEstados = {};
    const grupos = agruparEnsamblajesPorProducto(ensamblajes);
    let html = '';

    if (productoTabActivoEns === 'TODOS') {
        for (const [nombreProducto, items] of grupos) {
            html += `
                <div class="pc-ens-group">
                    <div class="pc-ens-group-header">
                        <span class="linea"></span>
                        <span class="texto"><i class="fa-solid fa-layer-group"></i> ${nombreProducto} <span class="pc-ens-group-count">· ${items.length}</span></span>
                        <span class="linea"></span>
                    </div>
                    <div class="pc-ens-grid">${items.map(e => tarjetaEnsamblajeHtml(e, nuevosEstados, silencioso)).join('')}</div>
                </div>`;
        }
    } else {
        const items = grupos.get(productoTabActivoEns) || [];
        html = items.length
            ? `<div class="pc-ens-grid">${items.map(e => tarjetaEnsamblajeHtml(e, nuevosEstados, silencioso)).join('')}</div>`
            : '<div class="pc-ens-empty">No hay armados registrados para este producto.</div>';
        ensamblajes.forEach(e => { if (!(e.ensamblaje_id in nuevosEstados)) nuevosEstados[e.ensamblaje_id] = estadoCortoEns(e); });
    }

    grid.innerHTML = html;
    snapshotEstadosEns = nuevosEstados;
}

// ── Carga desde el servidor: siempre filtrado al operario logueado ─────
async function cargarEnsamblajes(silencioso = false) {
    const grid = document.getElementById('gridEnsamblajes');
    if (!silencioso) grid.innerHTML = '<div class="pc-ens-empty">Cargando...</div>';

    const json = await llamarEnsamblaje('LISTARENSAMBLAJES', { estado: 'activa', operario_id: OPERARIO_ID });
    if (!json.success) {
        grid.innerHTML = `<div class="pc-ens-empty">${json.message}</div>`;
        return;
    }

    ensamblajesCache = json.ensamblajes || [];
    renderStatRowEns(ensamblajesCache);
    const grupos = agruparEnsamblajesPorProducto(ensamblajesCache);

    if (productoTabActivoEns === null || (productoTabActivoEns !== 'TODOS' && !grupos.has(productoTabActivoEns))) {
        const primerProducto = grupos.keys().next().value;
        productoTabActivoEns = primerProducto ?? 'TODOS';
    }

    renderTabsProductoEns(grupos);
    renderGridEnsamblajes(ensamblajesCache, silencioso);
}

// =============================================================================
// COMPLEMENTAR / A EMPAQUETADO
// =============================================================================

async function obtenerProductosParaComplementar(excluirId) {
    const json = await llamarEnsamblaje('BUSCARPRODUCTOSPARACOMPLEMENTAR', { excluir_id: excluirId, texto: '' });
    return json.success ? (json.productos || []) : [];
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
    const opciones = productos.map(p =>
        `<option value="${p.producto_id}">${p.codigo} - ${p.producto}${p.color_nombre ? ' (' + p.color_nombre + ')' : ''}</option>`
    ).join('');

    const { value: productoObjetivoId } = await Swal.fire({
        title: 'Marcar como complemento',
        html: `<p style="font-size:.85em;color:#666;text-align:left;">Elige el producto y color final al que este armado ya finalizado va a complementar.</p>
               <select id="swal-complemento-producto" class="form-select form-select-lg">
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
    document.getElementById('tab_producciones').classList.toggle('activo', tipo === 'produccion');
    document.getElementById('tab_derivados').classList.toggle('activo', tipo === 'derivado');
    document.getElementById('tab_complementos').classList.toggle('activo', tipo === 'complemento');
    document.getElementById('ens_buscar_detalle').value = '';
    renderGridDetalle();
}

async function renderGridDetalle() {
    const grid = document.getElementById('ens_detalle_grid');
    const texto = document.getElementById('ens_buscar_detalle').value.trim();
    const productoId = productoSeleccionadoEns.producto_id;
    const colorId = productoSeleccionadoEns.color_id;

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
                    })})'>
                <span class="pellet"><i class="fa-solid fa-industry"></i></span>
                <span class="nombre">${p.molde_nombre ?? ('Producción #' + p.produccion_id)}</span>
                <span class="meta">#${p.produccion_id} · <b>${formatearCantidadEns(p.cantidad_kg ?? p.cantidad)}</b> kg</span>
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
                    onclick='agregarLineaDetalle("derivado", ${JSON.stringify({ derivado_id: d.id, nombre: d.nombre })})'>
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
        const json = await llamarEnsamblaje('BUSCARCOMPLEMENTOS', { producto_id: productoId, texto, categoria_material_id: categoriaActual });
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
            tempId: ++contadorLineaTicketEns, tipo: 'produccion',
            molde_produccion_id: datos.produccion_id, derivado_id: null, ensamblaje_complemento_id: null,
            nombre: datos.molde_nombre ?? ('Producción #' + datos.produccion_id),
            meta: `#${datos.produccion_id} · Color: ${datos.color_nombre || '-'} · ${formatearCantidadEns(datos.cantidad_kg)} kg · ${formatearFechaHoraLegibleEns(datos.fecha_hora_fin)}`,
            icono: 'fa-industry', color: est.color, bg: est.bg,
            cantidad_kg: parseFloat(datos.cantidad_kg) || 0,
            categoria_material_id: datos.categoria_material_id,
        });
    } else if (tipo === 'derivado') {
        ticketDetalleEns.push({
            tempId: ++contadorLineaTicketEns, tipo: 'derivado',
            molde_produccion_id: null, derivado_id: datos.derivado_id, ensamblaje_complemento_id: null,
            nombre: datos.nombre, meta: `Derivado #${datos.derivado_id}`,
            icono: 'fa-flask', color: est.color, bg: est.bg,
        });
    } else {
        ticketDetalleEns.push({
            tempId: ++contadorLineaTicketEns, tipo: 'complemento',
            molde_produccion_id: null, derivado_id: null, ensamblaje_complemento_id: datos.ensamblaje_id,
            nombre: `${datos.producto_codigo ?? ''} - ${datos.producto_descripcion ?? ''}`,
            meta: `Armado #${datos.ensamblaje_id} · ${formatearCantidadEns(datos.cantidad_peso_kg)} kg`,
            icono: 'fa-puzzle-piece', color: est.color, bg: est.bg,
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
        list.innerHTML = `<li class="pc-tk-empty"><i class="fa-solid fa-basket-shopping"></i>Aún no vinculas nada.<br>Toca una card de arriba para empezar.</li>`;
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
        ensamblaje_complemento_id: l.ensamblaje_complemento_id ?? null,
    })));
}

// ── Crear / Editar ───────────────────────────────────────────────────────
function limpiarFormularioEnsamblaje() {
    document.getElementById('ens_buscar_detalle').value = '';
    document.getElementById('ens_buscar_producto').value = '';
    document.getElementById('ens_buscar_operario').value = '';
    ensamblajeIdActual = 0;
    ticketDetalleEns = [];
    productoSeleccionadoEns = { producto_id: null, color_id: null };
    sucursalSeleccionadaEns = '';
    operariosSeleccionadosEns = [];
    tabDetalleActiva = 'produccion';
    document.getElementById('tab_producciones').classList.add('activo');
    document.getElementById('tab_derivados').classList.remove('activo');
    document.getElementById('tab_complementos').classList.remove('activo');
    cerrarPickerOperarios();
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
            tempId: ++contadorLineaTicketEns, tipo: 'produccion',
            molde_produccion_id: item.produccion_id, derivado_id: null, ensamblaje_complemento_id: null,
            nombre: item.molde_nombre ?? ('Producción #' + item.produccion_id),
            meta: `#${item.produccion_id} · ${formatearCantidadEns(item.cantidad_kg)} kg`
                + (item.categoria_material_nombre ? ` · ${item.categoria_material_nombre}` : '')
                + (item.fecha ? ` · ${formatearFechaHoraLegibleEns(item.fecha)}` : ''),
            icono: 'fa-industry', color: est.color, bg: est.bg,
            cantidad_kg: parseFloat(item.cantidad_kg) || 0,
        });
    });

    derivados.forEach(item => {
        const est = estiloPorNombre(item.derivado_nombre || '');
        ticketDetalleEns.push({
            tempId: ++contadorLineaTicketEns, tipo: 'derivado',
            molde_produccion_id: null, derivado_id: item.derivado_id, ensamblaje_complemento_id: null,
            nombre: item.derivado_nombre ?? ('Derivado #' + item.derivado_id),
            meta: `Derivado #${item.derivado_id}`, icono: 'fa-flask', color: est.color, bg: est.bg,
        });
    });

    complementos.forEach(item => {
        const est = estiloPorNombre(item.producto_codigo || '');
        ticketDetalleEns.push({
            tempId: ++contadorLineaTicketEns, tipo: 'complemento',
            molde_produccion_id: null, derivado_id: null, ensamblaje_complemento_id: item.ensamblaje_complemento_id,
            nombre: `${item.producto_codigo ?? ''} - ${item.producto_descripcion ?? ''}`,
            meta: `Armado #${item.ensamblaje_complemento_id} · ${formatearCantidadEns(item.cantidad_peso_kg)} kg`,
            icono: 'fa-puzzle-piece', color: est.color, bg: est.bg,
        });
    });

    renderTicketDetalle();
    await renderGridDetalle();
    modalEnsamblaje.show();
}

document.getElementById('formEnsamblaje').addEventListener('submit', async function (e) {
    e.preventDefault();

    if (!obtenerProductoIdSeleccionadoEns()) {
        Swal.fire('Falta el producto', 'Toca una card de producto para seleccionarlo.', 'warning');
        return;
    }
    if (operariosSeleccionadosEns.length === 0) {
        Swal.fire('Faltan operarios', 'Agrega al menos un operario que haya participado.', 'warning');
        return;
    }
    if (ticketDetalleEns.length === 0) {
        Swal.fire('Falta vincular', 'Debes vincular al menos una producción finalizada, un derivado o un complemento.', 'warning');
        return;
    }

    const params = {
        id: ensamblajeIdActual,
        producto_id: obtenerProductoIdSeleccionadoEns(),
        operarios: JSON.stringify(operariosSeleccionadosEns.map(o => String(o.id))),
        sucursal_id: sucursalSeleccionadaEns,
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

// ── Iniciar / Finalizar (acciones directas desde la card) ──────────────
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
        if (json.success) { Swal.fire('Listo', json.message, 'success'); cargarEnsamblajes(); }
        else { Swal.fire('Error', json.message, 'error'); }
    });
}

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
            if (!value || parseFloat(value) <= 0) return 'Ingresa una cantidad válida mayor a 0.';
        }
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarEnsamblaje('FINALIZARENSAMBLAJE', { id, peso_kg: result.value });
        if (json.success) { Swal.fire('Listo', json.message, 'success'); cargarEnsamblajes(); }
        else { Swal.fire('Error', json.message, 'error'); }
    });
}
</script>
</body>
</html>