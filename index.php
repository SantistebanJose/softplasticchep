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
/*
 * Ejemplo de consultas reales (comentadas). Descoméntalas cuando existan
 * las tablas correspondientes en tu base de datos.
 *
 * $unidadesHoy = $pdo->query("SELECT COALESCE(SUM(cantidad),0) FROM produccion WHERE fecha = CURRENT_DATE")->fetchColumn();
 * $maquinasActivas = $pdo->query("SELECT COUNT(*) FROM maquinas WHERE estado = 'activa'")->fetchColumn();
 */

// Datos de ejemplo mientras se conecta la base de datos real.
$unidadesHoy     = 4280;
$maquinasActivas = 6;
$maquinasTotal   = 8;
$mermaPorcentaje = 2.4;
$stockCritico    = 3;

try {
    $totalUsuarios = (int) $pdo->query('SELECT COUNT(*) FROM usuario')->fetchColumn();
    $usuariosActivos = (int) $pdo->query('SELECT COUNT(*) FROM usuario WHERE deleted_at IS NULL')->fetchColumn();
} catch (PDOException $e) {
    $totalUsuarios = 0;
    $usuariosActivos = 0;
}

$ordenes = [
    ['codigo' => 'OP-0184', 'producto' => 'Pinza de ropa 8cm',   'maquina' => 'Inyectora 03', 'cantidad' => 1200, 'estado' => 'proceso'],
    ['codigo' => 'OP-0183', 'producto' => 'Gancho reforzado',    'maquina' => 'Inyectora 01', 'cantidad' => 800,  'estado' => 'pendiente'],
    ['codigo' => 'OP-0182', 'producto' => 'Matamoscas clásico',  'maquina' => 'Inyectora 05', 'cantidad' => 2000, 'estado' => 'completada'],
    ['codigo' => 'OP-0181', 'producto' => 'Colgador universal',  'maquina' => 'Inyectora 02', 'cantidad' => 1500, 'estado' => 'completada'],
];

$estadoPill = [
    'proceso'    => ['label' => 'En proceso',  'class' => 'success'],
    'pendiente'  => ['label' => 'Pendiente',   'class' => 'warning'],
    'completada' => ['label' => 'Completada',  'class' => 'info'],
    'detenida'   => ['label' => 'Detenida',    'class' => 'danger'],
];
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
</div>

<?php include("footer.php");?>