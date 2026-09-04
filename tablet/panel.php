<?php
session_start();
require __DIR__ . '/../controllers_tablet/clssAuthOperario.php';

if (empty($_SESSION['operario_id'])) {
    header('Location: loginoperarios.php');
    exit;
}

$nombreOperario = $_SESSION['operario_nombre'] ?? 'Operario';
$primerNombre   = trim(explode(' ', $nombreOperario)[0]);

$puedeProduccion  = operarioTieneEtapa('PRODUC');
$puedeEnsamblaje  = operarioTieneEtapa('ENSAMBLA');
$puedeEmpaquetado = operarioTieneEtapa('EMPAQUET');
$tieneAlgunAcceso = $puedeProduccion || $puedeEnsamblaje || $puedeEmpaquetado;

// Saludo dinámico según la hora
$hora = (int) date('H');
if ($hora < 12)      $saludo = 'Buenos días';
elseif ($hora < 19)  $saludo = 'Buenas tardes';
else                 $saludo = 'Buenas noches';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Panel operario · Plásticos Chepito</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/panel_operario.css">
</head>
<body>
<div class="pc-op-panel-shell">

    <header class="pc-op-brand-bar">
        <div class="pc-op-brand">
            <img src="../assets/img/logo.png" alt="Plásticos Chepito" class="pc-op-brand-mark">
            <div class="pc-op-brand-text">
                <span class="pc-op-brand-name">Plásticos Chepito</span>
                <span class="pc-op-brand-tag">Hecho a mano, hecho para durar</span>
            </div>
        </div>

        <div class="pc-op-actions">
            <a href="perfil_usuario.php" class="pc-op-panel-perfil">
                <i class="fa-solid fa-user"></i> Mi perfil
            </a>
            <a href="logoutoperario.php" class="pc-op-panel-logout">
                <i class="fa-solid fa-right-from-bracket"></i> Salir
            </a>
        </div>
    </header>

    <div class="pc-op-panel-greeting">
        <span class="pc-op-panel-hello"><?= $saludo ?>, <?= htmlspecialchars($primerNombre) ?> <i class="fa-solid fa-hand-sparkles"></i></span>
        <h1>¿Qué vas a registrar hoy?</h1>
    </div>

    <div class="pc-op-panel-grid">
        <?php if ($puedeProduccion): ?>
        <a href="produccion.php" class="pc-op-panel-btn q-blue">
            <div class="pc-op-panel-icon"><i class="fa-solid fa-industry"></i></div>
            <span class="pc-op-panel-label">Producción</span>
            <span class="pc-op-panel-sub">Registra tu avance de moldeado</span>
            <span class="pc-op-panel-arrow"><i class="fa-solid fa-arrow-right"></i></span>
        </a>
        <?php endif; ?>
        <?php if ($puedeProduccion): ?>
        <a href="mis_producciones.php" class="pc-op-panel-btn q-teal">
            <div class="pc-op-panel-icon"><i class="fa-solid fa-chart-column"></i></div>
            <span class="pc-op-panel-label">Mis producciones</span>
            <span class="pc-op-panel-sub">Revisa tu reporte de avances</span>
            <span class="pc-op-panel-arrow"><i class="fa-solid fa-arrow-right"></i></span>
        </a>
        <?php endif; ?>

        <?php if ($puedeEnsamblaje): ?>
        <a href="ensamblaje.php" class="pc-op-panel-btn q-navy">
            <div class="pc-op-panel-icon"><i class="fa-solid fa-puzzle-piece"></i></div>
            <span class="pc-op-panel-label">Ensamblaje</span>
            <span class="pc-op-panel-sub">Arma y une las piezas</span>
            <span class="pc-op-panel-arrow"><i class="fa-solid fa-arrow-right"></i></span>
        </a>
        <?php endif; ?>

        <?php if ($puedeEmpaquetado): ?>
        <a href="empaquetado.php" class="pc-op-panel-btn q-amber">
            <div class="pc-op-panel-icon"><i class="fa-solid fa-box-open"></i></div>
            <span class="pc-op-panel-label">Empaquetado</span>
            <span class="pc-op-panel-sub">Prepara sacos y bultos</span>
            <span class="pc-op-panel-arrow"><i class="fa-solid fa-arrow-right"></i></span>
        </a>
        <?php endif; ?>
    </div>

    <?php if (!$tieneAlgunAcceso): ?>
    <div class="pc-op-panel-empty">
        <i class="fa-solid fa-triangle-exclamation"></i>
        No tienes ninguna etapa asignada todavía. Pide a un administrador que te configure el acceso.
    </div>
    <?php endif; ?>

</div>
</body>
</html>