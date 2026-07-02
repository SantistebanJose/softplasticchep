<?php
/**
 * Sidebar — recibe $activePage desde la página que lo incluye
 * para resaltar el ítem de menú correspondiente.
 */
function pc_nav_class($page, $active) {
    return 'pc-nav-item' . ($page === $active ? ' active' : '');
}
?>
<aside class="pc-sidebar" id="pcSidebar">
    <div class="pc-sidebar-brand">
        <img src="assets/img/logo.png" alt="Plásticos Chepito">
        <div class="pc-sidebar-brand-text">
            <div class="name">Plásticos Chepito</div>
            <div class="sub">Módulo de producción</div>
        </div>
    </div>

    <div class="pc-nav-label">Principal</div>
    <a href="index.php" class="<?= pc_nav_class('dashboard', $activePage) ?>">
        <i class="fa-solid fa-gauge"></i> Dashboard
    </a>
    <a href="productos.php" class="<?= pc_nav_class('productos', $activePage) ?>">
        <i class="fa-solid fa-box"></i> Productos
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
    <a href="ordenes.php" class="<?= pc_nav_class('ordenes', $activePage) ?>">
        <i class="fa-solid fa-clipboard-list"></i> Órdenes de producción
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
