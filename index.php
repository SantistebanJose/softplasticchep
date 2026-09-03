<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$activePage   = 'dashboard';
$pageTitle    = 'Resumen de producción';

// Bandera de bienvenida (seteada por clssAuth.php al hacer login exitoso)
$mostrarBienvenida = !empty($_SESSION['mostrar_bienvenida']);
unset($_SESSION['mostrar_bienvenida']); // se muestra una sola vez
$nombreUsuario = $_SESSION['nombre_usuario'] ?? 'Usuario';

require __DIR__ . '/controllers/bd.php';
$pdo = conectar_oll_BD();


try {
    $totalUsuarios = (int) $pdo->query('SELECT COUNT(*) FROM usuario')->fetchColumn();
    $usuariosActivos = (int) $pdo->query('SELECT COUNT(*) FROM usuario WHERE deleted_at IS NULL')->fetchColumn();
} catch (PDOException $e) {
    $totalUsuarios = 0;
    $usuariosActivos = 0;
}


include("header.php");
?>

<?php if ($mostrarBienvenida): ?>
<div id="pc-welcome-overlay" class="pc-welcome-overlay">
    <div class="pc-welcome-box">
        <div class="pc-spinner"></div>
        <h2>¡Bienvenido, <?= htmlspecialchars($nombreUsuario) ?>!</h2>
        <p>Cargando panel de producción...</p>
    </div>
</div>

<style>
.pc-welcome-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: #1331c7;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    transition: opacity 0.6s ease;
}
.pc-welcome-box {
    text-align: center;
    color: #fff;
    font-family: inherit;
}
.pc-welcome-box h2 {
    margin: 16px 0 4px;
    font-size: 1.4rem;
}
.pc-welcome-box p {
    color: white;
    font-size: 0.9rem;
}
.pc-spinner {
    width: 48px;
    height: 48px;
    margin: 0 auto;
    border: 4px solid rgba(255,255,255,0.15);
    border-top-color: #22c55e;
    border-radius: 50%;
    animation: pc-spin 0.8s linear infinite;
}
@keyframes pc-spin {
    to { transform: rotate(360deg); }
}
.pc-welcome-overlay.pc-hide {
    opacity: 0;
    pointer-events: none;
}
</style>

<script>
window.addEventListener('load', function () {
    setTimeout(function () {
        var overlay = document.getElementById('pc-welcome-overlay');
        if (overlay) {
            overlay.classList.add('pc-hide');
            setTimeout(function () { overlay.remove(); }, 600);
        }
    }, 1200);
});
</script>
<?php endif; ?>

<div class="pc-panel">
    <div class="pc-panel-head">
        <h3><i class="fa-solid fa-bolt" style="color:var(--pc-red);margin-right:6px;"></i> Acceso rápido</h3>
    </div>
    <div style="padding:20px;">

        <!-- Sección 1: Flujo de producción -->
        <h4 class="pc-quick-section-title">
            <i class="fa-solid fa-industry" style="color:var(--pc-navy);margin-right:6px;"></i> Producción
        </h4>
        <div class="pc-quick-grid">
            <a href="produccion.php" class="pc-quick-btn q-blue">
                <i class="fa-solid fa-industry"></i> Producción
            </a>
            <a href="ensamblaje.php" class="pc-quick-btn q-navy">
                <i class="fa-solid fa-puzzle-piece"></i> Ensamblaje
            </a>
            <a href="empaquetado.php" class="pc-quick-btn q-warning">
                <i class="fa-solid fa-box-open"></i> Empaquetado
            </a>
            <a href="ventas.php" class="pc-quick-btn q-success">
                <i class="fa-solid fa-cash-register"></i> Ventas
            </a>
        </div>

        <!-- Sección 2: Gestión -->
        <h4 class="pc-quick-section-title">
            <i class="fa-solid fa-clipboard-list" style="color:var(--pc-navy);margin-right:6px;"></i> Gestión
        </h4>
        <div class="pc-quick-grid">
            <a href="operarios.php" class="pc-quick-btn q-warning">
                <i class="fa-solid fa-hard-hat"></i> Operarios
            </a>
            <a href="maquinas.php" class="pc-quick-btn q-red">
                <i class="fa-solid fa-gears"></i> Máquinas
            </a>
            <a href="moldes.php" class="pc-quick-btn q-dark">
                <i class="fa-solid fa-shapes"></i> Moldes
            </a>
            <a href="sucursal.php" class="pc-quick-btn q-navy">
                <i class="fa-solid fa-map-location-dot"></i> Sucursales
            </a>
        </div>

        <!-- Sección 3: Reportes -->
        <h4 class="pc-quick-section-title">
            <i class="fa-solid fa-chart-column" style="color:var(--pc-navy);margin-right:6px;"></i> Reportes
        </h4>
        <div class="pc-quick-grid">
            <a href="stock.php" class="pc-quick-btn q-dark">
                <i class="fa-solid fa-boxes-stacked"></i> Stock
            </a>
            <a href="produccion_operario.php" class="pc-quick-btn q-dark">
                <i class="fa-solid fa-chart-column"></i> Producción por Operario
            </a>
        </div>

    </div>
</div>

<style>
.pc-quick-section-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--pc-navy);
    margin: 18px 0 10px;
    padding-bottom: 6px;
    border-bottom: 1px solid #e5e7eb;
}
.pc-quick-section-title:first-of-type {
    margin-top: 0;
}
</style>
<!--
<div class="pc-metric-grid">
    <div class="pc-card pc-metric-card">
        <div class="top">
            <span class="label">Unidades hoy</span>
            <i class="fa-solid fa-box icon"></i>
        </div>
        <div class="value"><?= number_format($unidadesHoy, 0, ',', ',') ?></div>
        <div class="delta up"><i class="fa-solid fa-arrow-up"></i> 8% vs. ayer</div>
    </div>

    <div class="pc-card pc-metric-card">
        <div class="top">
            <span class="label">Máquinas activas</span>
            <i class="fa-solid fa-gears icon"></i>
        </div>
        <div class="value"><?= $maquinasActivas ?> / <?= $maquinasTotal ?></div>
        <div class="delta neutral"><?= $maquinasTotal - $maquinasActivas ?> en mantenimiento</div>
    </div>

    <div class="pc-card pc-metric-card">
        <div class="top">
            <span class="label">Usuarios activos</span>
            <i class="fa-solid fa-user-check icon"></i>
        </div>
        <div class="value"><?= $usuariosActivos ?> / <?= $totalUsuarios ?></div>
        <div class="delta neutral"><?= max(0, $totalUsuarios - $usuariosActivos) ?> inactivos</div>
    </div>

    <div class="pc-card pc-metric-card">
        <div class="top">
            <span class="label">Merma del día</span>
            <i class="fa-solid fa-triangle-exclamation icon" style="color:var(--pc-red);"></i>
        </div>
        <div class="value"><?= $mermaPorcentaje ?>%</div>
        <div class="delta down">Sobre el promedio</div>
    </div>

    <div class="pc-card pc-metric-card">
        <div class="top">
            <span class="label">Stock crítico</span>
            <i class="fa-solid fa-flask icon"></i>
        </div>
        <div class="value"><?= $stockCritico ?></div>
        <div class="delta neutral">materias primas</div>
    </div>
</div>
-->
<!--
<div class="pc-panel">
    <div class="pc-panel-head">
        <h3>Órdenes de producción recientes</h3>
        <a href="ordenes.php" class="link">Ver todas</a>
    </div>
    <table class="pc-table">
        <thead>
            <tr>
                <th>Orden</th>
                <th>Producto</th>
                <th>Máquina</th>
                <th>Cantidad</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            
        <?php foreach ($ordenes as $o): $pill = $estadoPill[$o['estado']]; ?>
            <tr>
                <td><?= htmlspecialchars($o['codigo']) ?></td>
                <td><?= htmlspecialchars($o['producto']) ?></td>
                <td><?= htmlspecialchars($o['maquina']) ?></td>
                <td><?= number_format($o['cantidad'], 0, ',', ',') ?></td>
                <td><span class="pc-pill <?= $pill['class'] ?>"><?= $pill['label'] ?></span></td>
            </tr>
        <?php endforeach; ?> 
        </tbody>
    </table>
</div> -->

<?php include("footer.php");?>