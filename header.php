<?php
date_default_timezone_set('America/Lima');
/**
 * header.php
 * Recibe (opcionalmente) $pageTitle, $pageSubtitle y $activePage
 * desde la página que lo incluye, ANTES del require de este archivo.
 *
 * Se encarga de arrancar la sesión y exigir login en TODAS las páginas
 * que lo incluyan. login.php NO debe incluir este header.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
if (strtolower(trim((string)($_SESSION['rol_usuario'] ?? ''))) === 'operario') {
    http_response_code(403);
    ?>
    <!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso de Operario</title><body style="font-family:Arial,sans-serif;background:#f3f5f9;display:grid;place-items:center;min-height:100vh;margin:0;color:#17233a">
    <main style="background:white;padding:32px;border-radius:16px;max-width:480px;text-align:center;box-shadow:0 8px 30px #14213d18">
    <h1 style="font-size:1.4rem">Acceso no disponible</h1><p>Las cuentas de Operario deben ingresar desde el acceso de operarios.</p>
    <a href="tablet/loginoperarios.php">Ir al acceso de operarios</a><br><br><a href="logout.php">Cerrar esta sesión</a></main></body></html>
    <?php
    exit;
}
require_once __DIR__ . '/controllers/clssVerificarSession.php';
$usuarioMenu = exigirSesion();
$puedeVerVentasMenu = usuarioTieneRol($usuarioMenu, 'administrador', 'ventas')
    || usuarioTieneRol($usuarioMenu, 'ventas', 'ventas')
    || usuarioTieneRol($usuarioMenu, 'consulta', 'ventas');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?>Plásticos Chepito</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png?v=20261028">
    <link rel="apple-touch-icon" href="assets/img/logo.png?v=20261028">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
</head>
<body>
<div class="pc-app">

<?php require __DIR__ . '/sidebar.php'; ?>

<div class="pc-content">
    <header class="pc-topbar">
        <div style="display:flex;align-items:center;gap:14px;">
            <i class="fa-solid fa-bars pc-menu-toggle" onclick="pcToggleSidebar()"></i>
            <div class="pc-topbar-info">
                <div class="pc-info-pill">
                    <i class="fa-regular fa-clock"></i>
                    <span>7:00 A.M. – 9:00 P.M.</span>
                </div>
                <div class="pc-info-pill">
                    <i class="fa-solid fa-location-dot"></i>
                    <span>Av. San Juan 1 Mza. L Lote. 08, San Juan de Lurigancho - Lima</span>
                </div>
                <div class="pc-info-pill pc-info-whatsapp">
                    <i class="fa-brands fa-whatsapp"></i>
                    <span>+51 929 441 260</span>
                </div>
                <div class="pc-social-icons">
                    <a href="https://www.facebook.com/profile.php?id=100066605900510" class="pc-social-btn fb" title="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="https://www.instagram.com/plasticoschepito/" class="pc-social-btn ig" title="Instagram"><i class="fa-brands fa-instagram"></i></a>
                    <a href="https://www.tiktok.com/@chepitoplastic" class="pc-social-btn tt" title="TikTok"><i class="fa-brands fa-tiktok"></i></a>

                </div>
            </div>
        </div>
        <div class="pc-topbar-right">
            <a href="perfil.php" class="pc-icon-btn" title="Mi perfil">
                <i class="fa-regular fa-user"></i>
            </a>
            <div class="pc-icon-btn">
                <i class="fa-regular fa-bell"></i>
                <span class="pc-badge-dot"></span>
            </div>
            <div style="width:1px;height:20px;background:var(--pc-border);"></div>
            <span><?= date('d \d\e F, Y') ?></span>
        </div>
    </header>

    <main class="pc-main">
        <?php if (!empty($pageTitle)): ?>
        <div class="pc-page-title">
            <h1><?= htmlspecialchars($pageTitle) ?></h1>
            <?php if (!empty($pageSubtitle)): ?>
                <p class="pc-page-subtitle"><?= htmlspecialchars($pageSubtitle) ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
