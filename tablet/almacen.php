<?php
session_start();
require __DIR__ . '/../controllers_tablet/clssAuthOperario.php';
if (empty($_SESSION['operario_id'])) { header('Location: loginoperarios.php'); exit; }
exigirAccesoEtapa('EMPAQUET', 'Empaquetado');
$operarioNombre = $_SESSION['operario_nombre'] ?? 'Operario';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Ubicar paquete · Plásticos Chepito</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-light">
<?php $prefijoEfectosNavidenos = '../'; require __DIR__ . '/../components/efectos_navidenos.php'; ?>
<header class="pc-op-brand-bar pc-op-brand-bar-inline">
    <div class="pc-op-brand"><a href="panel.php" class="pc-op-back" title="Volver"><i class="fa-solid fa-arrow-left"></i></a>
        <div class="pc-op-brand-text"><span class="pc-op-brand-name">Ubicar paquete</span><span class="pc-op-brand-tag">Operario: <?= htmlspecialchars($operarioNombre) ?></span></div>
    </div>
    <a href="logoutoperario.php" class="pc-op-panel-logout"><i class="fa-solid fa-right-from-bracket"></i> Salir</a>
</header>
<main class="container py-4" style="max-width:760px">
    <section class="pc-card p-4">
        <h1 class="h4 mb-1">Registrar ubicación con QR</h1>
        <p class="text-muted">Selecciona el almacén y escanea el sticker del paquete. Solo se registra dónde quedó; el stock no cambia.</p>
        <label class="form-label fw-semibold" for="almacenDestino">Almacén</label>
        <select class="form-select form-select-lg mb-3" id="almacenDestino"><option value="">Cargando almacenes…</option></select>
        <button type="button" id="btnEscanearAlmacen" class="btn btn-primary btn-lg w-100" onclick="escanearPaqueteAlmacen()"><i class="fa-solid fa-qrcode me-2"></i>Escanear paquete</button>
        <div id="resultadoUbicacion" class="mt-3" role="status" aria-live="polite"></div>
    </section>
    <section class="pc-card p-3 mt-3">
        <h2 class="h6">Últimos paquetes ubicados</h2>
        <div id="ultimasUbicaciones" class="small text-muted">Cargando…</div>
    </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../assets/js/app-common.js"></script>
<script src="../assets/js/qr-camera.js?v=20261114"></script>
<script>
const URL_ALMACEN = '../controllers/clssAlmacen.php';
const pedirAlmacen = (accion, params = {}) => llamar(URL_ALMACEN, accion, params);
const html = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

document.addEventListener('DOMContentLoaded', async () => {
    const select = document.getElementById('almacenDestino');
    const json = await pedirAlmacen('LISTARALMACENES', { estado: 'activos' });
    if (!json.success) { select.innerHTML = '<option value="">No se pudieron cargar</option>'; return; }
    select.innerHTML = '<option value="">Selecciona…</option>' + (json.almacenes || []).map(a =>
        `<option value="${Number(a.id)}">${html(a.nombre)} · ${html(a.sucursal_nombre || '')}</option>`).join('');
    await cargarUltimasUbicaciones();
});

async function cargarUltimasUbicaciones() {
    const json = await pedirAlmacen('LISTARPAQUETESUBICADOS');
    const div = document.getElementById('ultimasUbicaciones');
    if (!json.success) { div.textContent = json.message || 'No se pudo cargar el historial.'; return; }
    const filas = (json.paquetes || []).slice(0, 8);
    div.innerHTML = filas.length ? filas.map(p => `<div class="border-bottom py-2"><strong>#${Number(p.empaquetado_id)}</strong> · ${html(p.producto_codigo)} · ${html(p.cantidad_tota)} ${html(p.unidad_codigo)}<br><span>${html(p.almacen_nombre)} · ${html(p.sucursal_nombre)}</span></div>`).join('') : 'Aún no hay paquetes ubicados.';
}

async function escanearPaqueteAlmacen() {
    const almacenId = document.getElementById('almacenDestino').value;
    if (!almacenId) { Swal.fire('Selecciona un almacén', 'Indica dónde quedó el paquete.', 'info'); return; }
    const codigo = await new Promise(resolve => window.abrirLectorQR(resolve));
    if (!codigo) return;
    const boton = document.getElementById('btnEscanearAlmacen');
    boton.disabled = true;
    const json = await pedirAlmacen('REGISTRARUBICACIONQR', { codigo_qr: codigo, almacen_id: almacenId });
    boton.disabled = false;
    if (!json.success) { Swal.fire('No se registró', json.message, 'error'); return; }
    Swal.fire({ icon: json.ubicacion_repetida ? 'info' : 'success', title: json.ubicacion_repetida ? 'Ya estaba ubicado' : 'Ubicación guardada', text: `${json.producto || ''} · ${json.cantidad ?? ''} ${json.unidad || ''} · ${json.message}`, confirmButtonText: 'Listo' });
    await cargarUltimasUbicaciones();
}
</script>
</body>
</html>
