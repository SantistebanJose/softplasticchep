<?php
session_start();
require __DIR__ . '/../controllers_tablet/clssAuthOperario.php';

if (empty($_SESSION['operario_id'])) {
    header('Location: loginoperarios.php');
    exit;
}

$nombreOperario = $_SESSION['operario_nombre'] ?? 'Operario';
$primerNombre   = trim(explode(' ', $nombreOperario)[0]);
$mostrarBienvenidaSoporte = !empty($_SESSION['mostrar_bienvenida_soporte']);
unset($_SESSION['mostrar_bienvenida_soporte']);

$puedeProduccion  = operarioTieneEtapa('PRODUC');
$puedeEnsamblaje  = operarioTieneEtapa('ENSAMBLA');
$puedeEmpaquetado = operarioTieneEtapa('EMPAQUET');
$esConductor      = ($_SESSION['operario_rol'] ?? '') === 'conductor';
$ayudaVideosRol   = $esConductor ? 'conductor' : 'operario';
$tieneAlgunAcceso = $puedeProduccion || $puedeEnsamblaje || $puedeEmpaquetado || $esConductor;

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
    <link rel="icon" type="image/png" href="../assets/img/logo.png?v=20261028">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/panel_operario.css">
    <style>
        .hd-wave-figure{display:flex;justify-content:center;align-items:center;gap:8px;margin:4px auto 12px;font-size:58px;line-height:1.2}
        .hd-wave-hand{display:inline-block;transform-origin:75% 75%;animation:hd-wave 1s ease-in-out infinite}
        @keyframes hd-wave{0%,60%,100%{transform:rotate(0)}10%,30%,50%{transform:rotate(18deg)}20%,40%{transform:rotate(-10deg)}}
        .hd-welcome-copy{font-size:1rem;line-height:1.5;color:#5d6472}
    </style>
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

        <?php if ($puedeProduccion): ?>
        <a href="mis_producciones.php" class="pc-op-panel-btn q-teal">
            <div class="pc-op-panel-icon"><i class="fa-solid fa-chart-column"></i></div>
            <span class="pc-op-panel-label">Mis producciones</span>
            <span class="pc-op-panel-sub">Revisa tu reporte de avances</span>
            <span class="pc-op-panel-arrow"><i class="fa-solid fa-arrow-right"></i></span>
        </a>
        <?php endif; ?>
        
        <?php if ($puedeEnsamblaje): ?>
        <a href="mis_ensamblajes.php" class="pc-op-panel-btn q-teal">
            <div class="pc-op-panel-icon"><i class="fa-solid fa-chart-column"></i></div>
            <span class="pc-op-panel-label">Mis ensamblajes</span>
            <span class="pc-op-panel-sub">Revisa tu reporte de armados</span>
            <span class="pc-op-panel-arrow"><i class="fa-solid fa-arrow-right"></i></span>
        </a>
        <?php endif; ?>

        <a href="soporte.php" class="pc-op-panel-btn q-blue">
            <div class="pc-op-panel-icon"><i class="fa-solid fa-headset"></i></div>
            <span class="pc-op-panel-label">Ayuda y soporte</span>
            <span class="pc-op-panel-sub">Envía una consulta al administrador</span>
            <span class="pc-op-panel-arrow"><i class="fa-solid fa-arrow-right"></i></span>
        </a>

        <?php if ($esConductor): ?>
        <a href="compras.php" class="pc-op-panel-btn q-amber">
            <div class="pc-op-panel-icon"><i class="fa-solid fa-truck-ramp-box"></i></div>
            <span class="pc-op-panel-label">Compras</span>
            <span class="pc-op-panel-sub">Registra el material que recibes</span>
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

    <?php require __DIR__ . '/../components/ayuda_videos.php'; ?>

</div>
<?php if ($mostrarBienvenidaSoporte): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', async () => {
    if (!window.Swal) return;
    const nombre = <?= json_encode($primerNombre, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const resultado = await Swal.fire({
        title: `¡Hola, ${nombre}!`,
        html: '<div class="hd-wave-figure" aria-hidden="true"><span>🧑‍🏭</span><span class="hd-wave-hand">👋</span></div><div class="hd-welcome-copy">Ahora puedes hacer tus consultas desde el mismo sistema. Si tienes dudas al registrar producción, ensamblaje u otra operación, escríbenos desde <strong>Ayuda y soporte</strong>.</div>',
        confirmButtonText: 'Hacer una consulta',
        cancelButtonText: 'Ahora no',
        showCancelButton: true,
        confirmButtonColor: '#2563eb',
        focusCancel: true,
        allowOutsideClick: false
    });
    if (resultado.isConfirmed) window.location.href = 'soporte.php';
});
</script>
<?php endif; ?>
</body>
</html>
