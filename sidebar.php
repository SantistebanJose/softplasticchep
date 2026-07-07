<?php

/**
 * sidebar.php
 * Recibe $activePage desde la página que lo incluye
 * para resaltar el ítem/grupo correspondiente.
 */
function pc_nav_class($page, $active)
{
    return 'pc-nav-item' . ($page === $active ? ' active' : '');
}
function pc_sub_class($page, $active)
{
    return 'pc-nav-subitem' . ($page === $active ? ' active' : '');
}

$produccionPages     = ['productos', 'categorias', 'modelos', 'ordenes', 'operarios', 'materia_prima'];
$mantenimientoPages  = ['moldes', 'materiales', 'maquinas', 'colores', 'unidades_medida'];
$administracionPages = ['usuarios', 'configuracion'];
$analisisPages       = ['reportes'];

$produccionOpen     = in_array($activePage, $produccionPages);
$mantenimientoOpen  = in_array($activePage, $mantenimientoPages);
$administracionOpen = in_array($activePage, $administracionPages);
$analisisOpen       = in_array($activePage, $analisisPages);
?>
<aside class="pc-sidebar" id="pcSidebar">
    <a href="index.php" class="pc-sidebar-brand" style="text-decoration:none;color:inherit;">
        <img src="assets/img/logo.png" alt="Plásticos Chepito">
        <div class="pc-sidebar-brand-text">
            <div class="name">Plásticos Chepito</div>
            <div class="sub">Sistema de Gestión de Producción</div>
        </div>
    </a>

    <a href="index.php" class="<?= pc_nav_class('dashboard', $activePage) ?>">
        <span class="pc-nav-icon"><i class="fa-solid fa-gauge"></i></span> Dashboard
    </a>

    <details class="pc-nav-group" <?= $produccionOpen ? 'open' : '' ?>>
        <summary class="<?= pc_nav_class('', $produccionOpen ? '' : null) ?>">
            <span class="pc-nav-icon"><i class="fa-solid fa-industry"></i></span>
            Producción
        </summary>
        <div class="pc-nav-sublist">
            <a href="productos.php" class="<?= pc_sub_class('productos', $activePage) ?>">
                <span class="dot"></span> Productos
            </a>
            <a href="categorias.php" class="<?= pc_sub_class('categorias', $activePage) ?>">
                <span class="dot"></span> Categorías
            </a>
            <a href="modelos.php" class="<?= pc_sub_class('modelos', $activePage) ?>">
                <span class="dot"></span> Modelos
            </a>
            <a href="ordenes.php" class="<?= pc_sub_class('ordenes', $activePage) ?>">
                <span class="dot"></span> Órdenes de producción
            </a>
            <a href="operarios.php" class="<?= pc_sub_class('operarios', $activePage) ?>">
                <span class="dot"></span> Operarios
            </a>
            <a href="materia_prima.php" class="<?= pc_sub_class('materia_prima', $activePage) ?>">
                <span class="dot"></span> Materia prima
            </a>
        </div>
    </details>

    <details class="pc-nav-group" <?= $mantenimientoOpen ? 'open' : '' ?>>
        <summary class="pc-nav-item">
            <span class="pc-nav-icon"><i class="fa-solid fa-toolbox"></i></span>
            Mantenimientos
        </summary>
        <div class="pc-nav-sublist">
            <a href="moldes.php" class="<?= pc_sub_class('moldes', $activePage) ?>">
                <span class="dot"></span> Moldes
            </a>
            <a href="materiales.php" class="<?= pc_sub_class('materiales', $activePage) ?>">
                <span class="dot"></span> Materiales
            </a>
            <a href="maquinas.php" class="<?= pc_sub_class('maquinas', $activePage) ?>">
                <span class="dot"></span> Máquinas
            </a>
            <a href="colores.php" class="<?= pc_sub_class('colores', $activePage) ?>">
                <span class="dot"></span> Colores
            </a>
            <a href="unidades_medida.php" class="<?= pc_sub_class('unidades_medida', $activePage) ?>">
                <span class="dot"></span> Unidades de Medida
            </a>
        </div>
    </details>

    <details class="pc-nav-group" <?= $administracionOpen ? 'open' : '' ?>>
        <summary class="pc-nav-item">
            <span class="pc-nav-icon"><i class="fa-solid fa-user-shield"></i></span>
            Administración
            
        </summary>
        <div class="pc-nav-sublist">
            <a href="usuarios.php" class="<?= pc_sub_class('usuarios', $activePage) ?>">
                <span class="dot"></span> Usuarios
            </a>
            <a href="configuracion.php" class="<?= pc_sub_class('configuracion', $activePage) ?>">
                <span class="dot"></span> Configuración
            </a>
        </div>
    </details>

    <details class="pc-nav-group" <?= $analisisOpen ? 'open' : '' ?>>
        <summary class="pc-nav-item">
            <span class="pc-nav-icon"><i class="fa-solid fa-chart-column"></i></span>
            Análisis
            
        </summary>
        <div class="pc-nav-sublist">
            <a href="reportes.php" class="<?= pc_sub_class('reportes', $activePage) ?>">
                <span class="dot"></span> Reportes
            </a>
        </div>
    </details>

    <hr class="pc-nav-divider">
    <a href="logout.php" class="pc-nav-item">
        <span class="pc-nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span> Cerrar sesión
    </a>

    <div class="pc-sidebar-user">
        <div class="pc-avatar"><?= strtoupper(substr($_SESSION['nombre_usuario'] ?? 'US', 0, 2)) ?></div>
        <div>
            <div class="name"><?= htmlspecialchars($_SESSION['nombre_usuario'] ?? 'Usuario') ?></div>
            <div class="role"><?= htmlspecialchars($_SESSION['rol_usuario'] ?? 'Administrador') ?></div>
        </div>
    </div>
</aside>