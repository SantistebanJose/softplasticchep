<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$activePage   = 'dashboard';
$pageTitle    = 'Dashboard de operaciones';
$pageSubtitle = 'Ventas, inventario y trabajo pendiente en un solo lugar.';

// Bandera de bienvenida (seteada por clssAuth.php al hacer login exitoso)
$mostrarBienvenida = !empty($_SESSION['mostrar_bienvenida']);
unset($_SESSION['mostrar_bienvenida']); // se muestra una sola vez
$nombreUsuario = $_SESSION['nombre_usuario'] ?? 'Usuario';
$ayudaVideosRol = strtolower(trim((string)($_SESSION['rol_usuario'] ?? 'administrador')));

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

<style>
.dash-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:0 0 16px}
.dash-periodos{display:flex;gap:8px;flex-wrap:wrap}
.dash-periodos button{border:1px solid #d8dfeb;background:#fff;color:#475569;border-radius:9px;padding:8px 13px;font-weight:700;min-height:40px}
.dash-periodos button.activo{background:#246bfe;border-color:#246bfe;color:#fff}
.dash-periodo-label{color:#64748b;font-size:.9rem}
.dash-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:12px;margin-bottom:18px}
.dash-grid[hidden]{display:none}
.dash-card{background:#fff;border:1px solid #e5e7eb;border-top:3px solid #246bfe;border-radius:13px;padding:15px;min-width:0}
.dash-card .label{color:#64748b;text-transform:uppercase;font-size:.72rem;font-weight:800;letter-spacing:.035em}
.dash-card .value{color:#14213d;font-size:1.55rem;font-weight:800;margin-top:5px;overflow-wrap:anywhere}
.dash-card .note{color:#64748b;font-size:.8rem;margin-top:3px}
.dash-card.money-pen{border-top-color:#16a34a}.dash-card.money-usd{border-top-color:#2563eb}.dash-card.money-eur{border-top-color:#7c3aed}
.dash-card.alert-card{border-top-color:#f59e0b}.dash-card.danger-card{border-top-color:#dc3545}
.dash-section{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin-bottom:16px}
.dash-section h2{font-size:1.05rem;font-weight:750;margin:0 0 14px;color:#17233a}
.dash-flow-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px}
.dash-flow{display:flex;gap:12px;align-items:center;padding:14px;border:1px solid #e8edf4;border-radius:12px;color:#17233a;text-decoration:none;transition:transform .15s,border-color .15s}
.dash-flow:hover{transform:translateY(-2px);border-color:#9bbaff;color:#17233a}
.dash-flow .icon{width:42px;height:42px;border-radius:12px;background:#eef4ff;color:#246bfe;display:grid;place-items:center;font-size:1.1rem;flex:0 0 auto}
.dash-flow .count{font-size:1.4rem;font-weight:800;line-height:1.15}.dash-flow .title{font-size:.85rem;font-weight:700}.dash-flow .sub{font-size:.74rem;color:#64748b;margin-top:2px}
.dash-shortcut-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.dash-shortcut{min-height:96px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;padding:14px;border-radius:12px;color:#fff;text-decoration:none;font-weight:750;text-align:center;box-shadow:0 3px 8px rgba(15,23,42,.12);transition:transform .15s,filter .15s}
.dash-shortcut:hover{transform:translateY(-2px);filter:brightness(1.05);color:#fff}
.dash-shortcut i{font-size:1.35rem}.dash-shortcut.venta{background:#1671dc}.dash-shortcut.produccion{background:#1740b8}.dash-shortcut.ensamblaje{background:#26364f}.dash-shortcut.empaquetado{background:#b87908}.dash-shortcut.stock{background:#16885e}.dash-shortcut.externos{background:#7043c1}
.dash-stock-table{width:100%;border-collapse:collapse}.dash-stock-table th,.dash-stock-table td{padding:9px 10px;border-bottom:1px solid #edf0f5;text-align:left}.dash-stock-table th{font-size:.74rem;color:#64748b;text-transform:uppercase}.dash-stock-table .num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
.dash-empty{text-align:center;color:#64748b;padding:20px!important}
.dash-quick-head{font-weight:750;color:#14213d;margin:0 0 10px}
@media(max-width:850px){.dash-shortcut-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:600px){.dash-section{padding:13px}.dash-card{padding:12px}.dash-card .value{font-size:1.3rem}.dash-flow-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.dash-flow{padding:10px;gap:8px}.dash-flow .icon{width:36px;height:36px}.dash-flow .count{font-size:1.2rem}.dash-shortcut{min-height:82px;padding:10px}}
@media(max-width:390px){.dash-flow-grid{grid-template-columns:1fr}.dash-shortcut-grid{grid-template-columns:1fr 1fr;gap:8px}.dash-periodos{width:100%}.dash-periodos button{flex:1;padding:7px 8px}}
</style>

<div class="dash-toolbar">
    <div class="dash-periodos" role="group" aria-label="Periodo de ventas">
        <button type="button" data-dash-periodo="dia">Hoy</button>
        <button type="button" data-dash-periodo="semana">Esta semana</button>
        <button type="button" data-dash-periodo="mes" class="activo">Este mes</button>
    </div>
    <span class="dash-periodo-label" id="dashPeriodoLabel">Cargando periodo…</span>
</div>

<section class="dash-grid" id="dashVentas" aria-live="polite">
    <div class="dash-card"><div class="label">Ventas completadas</div><div class="value">—</div><div class="note">Del periodo</div></div>
    <div class="dash-card"><div class="label">Equivalente en soles</div><div class="value">—</div><div class="note">Total contable del periodo</div></div>
    <div class="dash-card money-pen"><div class="label">Recibido en soles</div><div class="value">—</div><div class="note">Pagos en PEN</div></div>
    <div class="dash-card money-usd"><div class="label">Recibido en dólares</div><div class="value">—</div><div class="note">Pagos en USD</div></div>
    <div class="dash-card money-eur"><div class="label">Recibido en euros</div><div class="value">—</div><div class="note">Pagos en EUR</div></div>
</section>

<section class="dash-section">
    <h2><i class="fa-solid fa-bolt me-2 text-primary"></i>Accesos rápidos</h2>
    <div class="dash-shortcut-grid">
        <a class="dash-shortcut venta" href="puntoVenta.php"><i class="fa-solid fa-cash-register"></i><span>Venta rápida</span></a>
        <a class="dash-shortcut produccion" href="produccion.php"><i class="fa-solid fa-industry"></i><span>Registrar producción</span></a>
        <a class="dash-shortcut ensamblaje" href="ensamblaje.php"><i class="fa-solid fa-puzzle-piece"></i><span>Registrar ensamblaje</span></a>
        <a class="dash-shortcut empaquetado" href="empaquetado.php"><i class="fa-solid fa-box-open"></i><span>Registrar empaquetado</span></a>
        <a class="dash-shortcut stock" href="stock.php"><i class="fa-solid fa-boxes-stacked"></i><span>Consultar stock</span></a>
        <?php if (strtoupper(trim((string)($_SESSION['rol_usuario'] ?? ''))) === 'ADMINISTRADOR'): ?>
        <a class="dash-shortcut externos" href="reporte_servicios_externos.php"><i class="fa-solid fa-truck-ramp-box"></i><span>Servicios externos</span></a>
        <?php endif; ?>
    </div>
</section>

<section class="dash-section">
    <h2><i class="fa-solid fa-arrow-right-arrow-left me-2 text-primary"></i>Trabajo pendiente ahora</h2>
    <div class="dash-flow-grid" id="dashFlujo">
        <a class="dash-flow" href="produccion.php"><span class="icon"><i class="fa-solid fa-industry"></i></span><span><span class="count">—</span><span class="title d-block">Producciones en curso</span><span class="sub">— pausadas</span></span></a>
        <a class="dash-flow" href="ensamblaje.php"><span class="icon"><i class="fa-solid fa-puzzle-piece"></i></span><span><span class="count">—</span><span class="title d-block">Por ensamblar</span><span class="sub">Lotes con saldo local</span></span></a>
        <a class="dash-flow" href="empaquetado.php"><span class="icon"><i class="fa-solid fa-box-open"></i></span><span><span class="count">—</span><span class="title d-block">Por empaquetar</span><span class="sub">Fuentes con saldo disponible</span></span></a>
        <?php if (strtoupper(trim((string)($_SESSION['rol_usuario'] ?? ''))) === 'ADMINISTRADOR'): ?>
        <a class="dash-flow" href="reporte_servicios_externos.php"><span class="icon"><i class="fa-solid fa-truck-ramp-box"></i></span><span><span class="count">—</span><span class="title d-block">Servicios externos</span><span class="sub">Envíos sin retorno</span></span></a>
        <?php endif; ?>
    </div>
</section>

<section class="dash-section">
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
        <h2 class="mb-0"><i class="fa-solid fa-triangle-exclamation me-2 text-warning"></i>Materiales bajo el mínimo</h2>
        <a href="materiales.php" class="btn btn-sm btn-outline-primary">Ver materiales</a>
    </div>
    <div class="table-responsive">
        <table class="dash-stock-table">
            <thead><tr><th>Material</th><th class="num">Stock actual</th><th class="num">Mínimo</th></tr></thead>
            <tbody id="dashMateriales"><tr><td colspan="3" class="dash-empty">Cargando alertas...</td></tr></tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/components/ayuda_videos.php'; ?>

<script>
const DASHBOARD_API = 'controllers/clssDashboard.php';
let periodoDashboard = 'mes';
const dashDinero = (valor, moneda = 'PEN') => new Intl.NumberFormat('es-PE', {style:'currency', currency:moneda, minimumFractionDigits:2, maximumFractionDigits:2}).format(Number(valor || 0));
const dashEsc = valor => String(valor ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const dashFecha = valor => {
    const partes = String(valor || '').split('-');
    return partes.length === 3 ? `${partes[2]}/${partes[1]}/${partes[0]}` : String(valor || '');
};

async function cargarDashboard() {
    const body = new URLSearchParams({accion:'RESUMENDASHBOARD', periodo:periodoDashboard});
    try {
        const response = await fetch(DASHBOARD_API, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body});
        const data = await response.json();
        if (!data.success) throw new Error(data.message || 'No se pudo cargar el dashboard.');
        const v = data.ventas || {};
        document.getElementById('dashPeriodoLabel').textContent = `${data.periodo.etiqueta} · ${dashFecha(data.periodo.desde)} — ${dashFecha(data.periodo.hasta)}`;
        const resumenVentas = document.getElementById('dashVentas');
        resumenVentas.hidden = !data.ventas_visibles;
        resumenVentas.innerHTML = data.ventas_visibles ? `
            <div class="dash-card"><div class="label">Ventas completadas</div><div class="value">${Number(v.ventas || 0).toLocaleString('es-PE')}</div><div class="note">Del periodo seleccionado</div></div>
            <div class="dash-card"><div class="label">Equivalente en soles</div><div class="value">${dashDinero(v.total_pen)}</div><div class="note">Importe contable de las ventas</div></div>
            <div class="dash-card money-pen"><div class="label">Recibido en soles</div><div class="value">${dashDinero(v.ingresos_pen, 'PEN')}</div><div class="note">Pagos en PEN</div></div>
            <div class="dash-card money-usd"><div class="label">Recibido en dólares</div><div class="value">${dashDinero(v.ingresos_usd, 'USD')}</div><div class="note">Pagos en USD</div></div>
            <div class="dash-card money-eur"><div class="label">Recibido en euros</div><div class="value">${dashDinero(v.ingresos_eur, 'EUR')}</div><div class="note">Pagos en EUR</div></div>` : '';
        const p = data.produccion || {};
        const flujo = document.getElementById('dashFlujo');
        const valores = [
            [p.en_curso || 0, `${p.pausadas || 0} pausadas`],
            [data.pendiente_ensamblaje || 0, 'Lotes con saldo local'],
            [data.pendiente_empaquetado || 0, 'Fuentes con saldo disponible'],
            [data.servicios_externos_pendientes || 0, 'Envíos sin retorno']
        ];
        flujo.querySelectorAll('.dash-flow').forEach((card, i) => {
            if (!valores[i]) return;
            card.querySelector('.count').textContent = Number(valores[i][0]).toLocaleString('es-PE');
            card.querySelector('.sub').textContent = valores[i][1];
        });
        const materiales = data.materiales_bajos || [];
        document.getElementById('dashMateriales').innerHTML = materiales.length ? materiales.map(m => `
            <tr><td><strong>${dashEsc(m.nombre)}</strong></td><td class="num">${Number(m.stock_actual || 0).toLocaleString('es-PE')} ${dashEsc(m.unidad || '')}</td><td class="num">${Number(m.stock_minimo || 0).toLocaleString('es-PE')} ${dashEsc(m.unidad || '')}</td></tr>`).join('') : '<tr><td colspan="3" class="dash-empty">No hay materiales por debajo del mínimo configurado.</td></tr>';
    } catch (error) {
        document.getElementById('dashPeriodoLabel').textContent = error.message || 'No se pudo cargar el dashboard.';
        document.getElementById('dashVentas').innerHTML = `<div class="dash-section text-danger">${dashEsc(error.message || 'Error de conexión.')}</div>`;
        document.getElementById('dashMateriales').innerHTML = `<tr><td colspan="3" class="dash-empty text-danger">${dashEsc(error.message || 'No se pudieron cargar las alertas.')}</td></tr>`;
    }
}

document.querySelectorAll('[data-dash-periodo]').forEach(boton => boton.addEventListener('click', () => {
    periodoDashboard = boton.dataset.dashPeriodo;
    document.querySelectorAll('[data-dash-periodo]').forEach(item => item.classList.toggle('activo', item === boton));
    cargarDashboard();
}));
cargarDashboard();
</script>

            


<?php include("footer.php");?>
