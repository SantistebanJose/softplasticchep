<?php

/**
 * sidebar.php
 * Recibe $activePage desde la página que lo incluye
 * para resaltar el ítem de menú correspondiente.
 */
function pc_nav_class($page, $active)
{
    return 'pc-nav-item' . ($page === $active ? ' active' : '');
}
?>
<aside class="pc-sidebar" id="pcSidebar">
    <a href="index.php" class="pc-sidebar-brand" style="text-decoration:none;color:inherit;">
        <img src="assets/img/logo.png" alt="Plásticos Chepito">
        <div class="pc-sidebar-brand-text">
            <div class="name">Plásticos Chepito</div>
            <div class="sub">Sistema de Gestión de Producción</div>
        </div>
    </a>

    <div class="pc-nav-label">Principal</div>
    <a href="index.php" class="<?= pc_nav_class('dashboard', $activePage) ?>">
        <i class="fa-solid fa-gauge"></i> Dashboard
    </a>
    <a href="productos.php" class="<?= pc_nav_class('productos', $activePage) ?>">
        <i class="fa-solid fa-box"></i> Productos
    </a>
    <a href="categorias.php" class="<?= pc_nav_class('categorias', $activePage) ?> pc-nav-sub">
        <i class="fa-solid fa-tags"></i> Categorías
    </a>
    <a href="modelos.php" class="<?= pc_nav_class('modelos', $activePage) ?> pc-nav-sub">
        <i class="fa-solid fa-boxes-stacked"></i> Modelos
    </a>
    <a href="maquinas.php" class="<?= pc_nav_class('maquinas', $activePage) ?>">
        <i class="fa-solid fa-gears"></i> Máquinas
    </a>
    <a href="operarios.php" class="<?= pc_nav_class('operarios', $activePage) ?>">
        <i class="fa-solid fa-users"></i> Operarios
    </a>
    <a href="materia_prima.php" class="<?= pc_nav_class('materia_prima', $activePage) ?>">
        <i class="fa-solid fa-flask"></i> Materia prima
    </a>
    <a href="usuarios.php" class="<?= pc_nav_class('usuarios', $activePage) ?>">
        <i class="fa-solid fa-user-shield"></i> Usuarios
    </a>
    <a href="ordenes.php" class="<?= pc_nav_class('ordenes', $activePage) ?>">
        <i class="fa-solid fa-clipboard-list"></i> Órdenes de producción
    </a>

    <div class="pc-nav-label">Sesión</div>
    <a href="logout.php" class="pc-nav-item">
        <i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión
    </a>

    <div class="pc-nav-label">Análisis</div>
    <a href="reportes.php" class="<?= pc_nav_class('reportes', $activePage) ?>">
        <i class="fa-solid fa-chart-column"></i> Reportes
    </a>
    <a href="configuracion.php" class="<?= pc_nav_class('configuracion', $activePage) ?>">
        <i class="fa-solid fa-gear"></i> Configuración
    </a>

    <div class="pc-sidebar-user">
        <div class="pc-avatar"><?= strtoupper(substr($_SESSION['nombre_usuario'] ?? 'US', 0, 2)) ?></div>
        <div>
            <div class="name"><?= htmlspecialchars($_SESSION['nombre_usuario'] ?? 'Usuario') ?></div>
            <div class="role"><?= htmlspecialchars($_SESSION['rol_usuario'] ?? 'Administrador') ?></div>
        </div>
    </div>
</aside>