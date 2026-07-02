<?php
$activePage   = 'dashboard';
$pageTitle    = 'Resumen de producción';
$pageSubtitle = 'Turno actual · ' . date('l, d \d\e F \d\e Y');

require __DIR__ . '/includes/config.php';

// Protección: si no hay sesión activa, redirigir al login
if (empty($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
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

require __DIR__ . '/includes/header.php';
?>

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

<?php require __DIR__ . '/includes/footer.php'; ?>
