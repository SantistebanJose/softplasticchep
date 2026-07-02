<?php
/**
 * Header — recibe (opcionalmente) $pageTitle y $pageSubtitle
 * desde la página que lo incluye, antes del include.
 */
if (!isset($pageTitle)) {
    $pageTitle = 'Dashboard';
}
if (!isset($pageSubtitle)) {
    $pageSubtitle = '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> · Plásticos Chepito</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="pc-app">

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="pc-content">
    <header class="pc-topbar">
        <div style="display:flex;align-items:center;gap:14px;">
            <i class="fa-solid fa-bars pc-menu-toggle" onclick="document.getElementById('pcSidebar').classList.toggle('open')"></i>
            <div class="pc-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" placeholder="Buscar producto, orden, operario...">
            </div>
        </div>
        <div class="pc-topbar-right">
            <div class="pc-icon-btn">
                <i class="fa-regular fa-bell"></i>
                <span class="pc-badge-dot"></span>
            </div>
            <div style="width:1px;height:20px;background:var(--pc-border);"></div>
            <span><?= date('d \d\e F, Y') ?></span>
        </div>
    </header>

    <main class="pc-main">
        <?php if ($pageTitle): ?>
        <div class="pc-page-title">
            <h1><?= htmlspecialchars($pageTitle) ?></h1>
            <?php if ($pageSubtitle): ?><p><?= htmlspecialchars($pageSubtitle) ?></p><?php endif; ?>
        </div>
        <?php endif; ?>
