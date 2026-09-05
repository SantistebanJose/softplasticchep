<?php

/**
 * sidebar.php
 * Recibe $activePage desde la página que lo incluye
 * para resaltar el ítem/grupo correspondiente.
 *
 * NOTA (reorganización):
 * - Se agregaron al menú páginas que ya existían en el sistema pero no estaban
 *   enlazadas: ensamblaje.php, empaquetado.php, ventas.php, sucursal.php,
 *   area.php, cargo.php.
 * - Se separó "Producción" en dos grupos: "Operaciones" (el flujo diario:
 *   producción → ensamblaje → empaquetado) y "Personal"
 *   (operarios, cargos). Ajustar si tu criterio de negocio es otro.
 * - Se asume que area.php = maestro de áreas/zonas de planta y
 *   cargo.php = maestro de cargos/puestos de operario. Si no es así,
 *   moverlos de grupo es solo cuestión de cambiar el array correspondiente.
 * - configuracion.php aún no existe como archivo; el link queda listo para
 *   cuando lo crees.
 * - "disponibilidad_venta copy.php" se excluye a propósito del menú (no es
 *   una página real a enlazar).
 * - Bug corregido: los reportes usaban el mismo valor de $activePage
 *   ('reportes'), por lo que nunca se resaltaba el ítem activo individualmente.
 *   Ahora cada reporte tiene su propia clave.
 *
 * ACTUALIZADO (2026-09-05): la vista de Ventas se dividió en dos páginas
 * (puntoVenta.php y listadoVentas.php), reemplazando a ventas.php. Se
 * sacó "Ventas" del grupo Operaciones y se creó un grupo propio "Ventas"
 * con esas dos páginas como submenú, siguiendo el mismo patrón visual
 * de los demás grupos.
 */
function pc_nav_class($page, $active)
{
    return 'pc-nav-item' . ($page === $active ? ' active' : '');
}
function pc_sub_class($page, $active)
{
    return 'pc-nav-subitem' . ($page === $active ? ' active' : '');
}
function pc_group_summary_class($isOpen)
{
    return 'pc-nav-item' . ($isOpen ? ' active' : '');
}

$operacionesPages    = ['produccion', 'ensamblaje', 'empaquetado'];
$ventasPages         = ['punto_venta', 'listado_ventas'];
$personalPages       = ['operarios', 'cargo'];
$mantenimientoPages  = ['productos', 'moldes', 'materiales', 'categoria_material', 'unidad_medida', 'colores', 'area', 'maquinas'];
$administracionPages = ['usuarios', 'sucursal', 'proveedores', 'compras', 'configuracion'];
$reportesPages       = ['stock', 'produccion_operario'];

$operacionesOpen    = in_array($activePage, $operacionesPages);
$ventasOpen         = in_array($activePage, $ventasPages);
$personalOpen       = in_array($activePage, $personalPages);
$mantenimientoOpen  = in_array($activePage, $mantenimientoPages);
$administracionOpen = in_array($activePage, $administracionPages);
$reportesOpen       = in_array($activePage, $reportesPages);
?>
<div class="pc-sidebar-overlay" id="pcSidebarOverlay" onclick="pcToggleSidebar()"></div>

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

    <!-- Operaciones: el flujo diario de planta, en orden -->
    <details class="pc-nav-group" <?= $operacionesOpen ? 'open' : '' ?>>
        <summary class="<?= pc_group_summary_class($operacionesOpen) ?>">
            <span class="pc-nav-icon"><i class="fa-solid fa-industry"></i></span>
            Operaciones
        </summary>
        <div class="pc-nav-sublist">
            <a href="produccion.php" class="<?= pc_sub_class('produccion', $activePage) ?>">
                <span class="dot"></span> Producción
            </a>
            <a href="ensamblaje.php" class="<?= pc_sub_class('ensamblaje', $activePage) ?>">
                <span class="dot"></span> Ensamblaje
            </a>
            <a href="empaquetado.php" class="<?= pc_sub_class('empaquetado', $activePage) ?>">
                <span class="dot"></span> Empaquetado
            </a>
        </div>
    </details>

    <!-- Ventas -->
    <details class="pc-nav-group" <?= $ventasOpen ? 'open' : '' ?>>
        <summary class="<?= pc_group_summary_class($ventasOpen) ?>">
            <span class="pc-nav-icon"><i class="fa-solid fa-cart-shopping"></i></span>
            Ventas
        </summary>
        <div class="pc-nav-sublist">
            <a href="puntoVenta.php" class="<?= pc_sub_class('punto_venta', $activePage) ?>">
                <span class="dot"></span> Punto de Venta
            </a>
            <a href="listadoVentas.php" class="<?= pc_sub_class('listado_ventas', $activePage) ?>">
                <span class="dot"></span> Listado de Ventas
            </a>
        </div>
    </details>

    <!-- Personal: la gente que ejecuta el flujo -->
    <details class="pc-nav-group" <?= $personalOpen ? 'open' : '' ?>>
        <summary class="<?= pc_group_summary_class($personalOpen) ?>">
            <span class="pc-nav-icon"><i class="fa-solid fa-users"></i></span>
            Personal
        </summary>
        <div class="pc-nav-sublist">
            <a href="operarios.php" class="<?= pc_sub_class('operarios', $activePage) ?>">
                <span class="dot"></span> Operarios
            </a>
            <a href="cargo.php" class="<?= pc_sub_class('cargo', $activePage) ?>">
                <span class="dot"></span> Cargos
            </a>
        </div>
    </details>

    <!-- Mantenimientos: catálogos / datos maestros -->
    <details class="pc-nav-group" <?= $mantenimientoOpen ? 'open' : '' ?>>
        <summary class="<?= pc_group_summary_class($mantenimientoOpen) ?>">
            <span class="pc-nav-icon"><i class="fa-solid fa-toolbox"></i></span>
            Mantenimientos
        </summary>
        <div class="pc-nav-sublist">
            <a href="productos.php" class="<?= pc_sub_class('productos', $activePage) ?>">
                <span class="dot"></span> Productos
            </a>
            <a href="moldes.php" class="<?= pc_sub_class('moldes', $activePage) ?>">
                <span class="dot"></span> Moldes
            </a>
            <a href="materiales.php" class="<?= pc_sub_class('materiales', $activePage) ?>">
                <span class="dot"></span> Materiales
            </a>
            <a href="categoria_material.php" class="<?= pc_sub_class('categoria_material', $activePage) ?>">
                <span class="dot"></span> Categorías de Materiales
            </a>
            <a href="unidad_medida.php" class="<?= pc_sub_class('unidad_medida', $activePage) ?>">
                <span class="dot"></span> Unidad de Medida
            </a>
            <a href="colores.php" class="<?= pc_sub_class('colores', $activePage) ?>">
                <span class="dot"></span> Colores
            </a>
            <a href="area.php" class="<?= pc_sub_class('area', $activePage) ?>">
                <span class="dot"></span> Áreas
            </a>
            <a href="maquinas.php" class="<?= pc_sub_class('maquinas', $activePage) ?>">
                <span class="dot"></span> Máquinas
            </a>
        </div>
    </details>

    <!-- Administración -->
    <details class="pc-nav-group" <?= $administracionOpen ? 'open' : '' ?>>
        <summary class="<?= pc_group_summary_class($administracionOpen) ?>">
            <span class="pc-nav-icon"><i class="fa-solid fa-user-shield"></i></span>
            Administración
        </summary>
        <div class="pc-nav-sublist">
            <a href="usuarios.php" class="<?= pc_sub_class('usuarios', $activePage) ?>">
                <span class="dot"></span> Usuarios
            </a>
            <a href="sucursal.php" class="<?= pc_sub_class('sucursal', $activePage) ?>">
                <span class="dot"></span> Sucursales
            </a>
            <a href="proveedores.php" class="<?= pc_sub_class('proveedores', $activePage) ?>">
                <span class="dot"></span> Proveedores
            </a>
            <a href="compras.php" class="<?= pc_sub_class('compras', $activePage) ?>">
                <span class="dot"></span> Compras
            </a>
            <a href="configuracion.php" class="<?= pc_sub_class('configuracion', $activePage) ?>">
                <span class="dot"></span> Configuración
            </a>
        </div>
    </details>

    <!-- Reportes -->
    <details class="pc-nav-group" <?= $reportesOpen ? 'open' : '' ?>>
        <summary class="<?= pc_group_summary_class($reportesOpen) ?>">
            <span class="pc-nav-icon"><i class="fa-solid fa-chart-column"></i></span>
            Reportes
        </summary>
        <div class="pc-nav-sublist">
            <a href="stock.php" class="<?= pc_sub_class('stock', $activePage) ?>">
                <span class="dot"></span> Stock
            </a>
            <a href="produccion_operario.php" class="<?= pc_sub_class('produccion_operario', $activePage) ?>">
                <span class="dot"></span> Producción por Operario
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