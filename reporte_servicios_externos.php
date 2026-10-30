<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['usuario_id']) || strtolower(trim((string)($_SESSION['rol_usuario'] ?? ''))) !== 'administrador') {
    http_response_code(403);
    exit('No tienes permiso para ver este reporte.');
}
$pageTitle = 'Reporte de Servicios Externos';
$pageSubtitle = 'Seguimiento de envíos, retornos, mermas y sobrantes por componente.';
$activePage = 'reporte_servicios_externos';
include 'header.php';
?>
<style>
.rse-wrap{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin-bottom:16px}
.rse-filtros{display:flex;gap:12px;align-items:end;flex-wrap:wrap}.rse-filtros>div{flex:1 1 160px;min-width:145px}
.rse-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:16px}
.rse-stat{background:#fff;border:1px solid #e5e7eb;border-top:3px solid #2563eb;border-radius:11px;padding:14px;text-align:center}
.rse-stat b{display:block;color:#14213d;font-size:1.35rem}.rse-stat span{font-size:.76rem;color:#687386;text-transform:uppercase}
.rse-table-wrap{overflow:auto}.rse-table{width:100%;border-collapse:collapse;min-width:1200px}.rse-table th,.rse-table td{padding:9px 10px;border-bottom:1px solid #e9edf3;text-align:left;vertical-align:top}.rse-table th{position:sticky;top:0;background:#f3f5f9;color:#5d6878;font-size:.75rem;text-transform:uppercase}
.rse-empty{text-align:center;color:#6b7280;padding:32px}.rse-unit-lines{font-size:.87rem;line-height:1.5}.rse-motivo{display:block;color:#687386;font-size:.78rem;max-width:180px;white-space:normal}
@media(max-width:640px){.rse-wrap{padding:12px}.rse-filtros button{flex:1}}
</style>

<section class="rse-wrap">
  <div class="rse-filtros">
    <div><label class="form-label" for="rseDesde">Desde</label><input type="date" class="form-control" id="rseDesde"></div>
    <div><label class="form-label" for="rseHasta">Hasta</label><input type="date" class="form-control" id="rseHasta"></div>
    <div><label class="form-label" for="rseDestinatario">Destinatario</label><input class="form-control" id="rseDestinatario" maxlength="160" placeholder="Nombre del servicio externo"></div>
    <div><label class="form-label" for="rseEstado">Estado</label><select class="form-select" id="rseEstado"><option value="">Todos</option><option value="pendiente">Pendiente de retorno</option><option value="recibido">Recibido</option></select></div>
    <button type="button" class="btn btn-primary" id="rseConsultar"><i class="fa-solid fa-magnifying-glass"></i> Consultar</button>
    <button type="button" class="btn btn-outline-secondary" id="rseLimpiar">Limpiar</button>
    <button type="button" class="btn btn-outline-success" id="rseExportar"><i class="fa-solid fa-file-csv"></i> Exportar CSV</button>
  </div>
</section>

<section class="rse-stats" id="rseResumen"></section>
<section class="rse-wrap">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h2 class="h5 m-0">Detalle de envíos externos</h2><span class="text-muted" id="rseConteo"></span>
  </div>
  <div class="rse-table-wrap"><table class="rse-table">
    <thead><tr><th>Enviado</th><th>Destinatario</th><th>Componente</th><th>Origen</th><th>Cantidad enviada</th><th>Producto armado recibido</th><th>Usado</th><th>Merma</th><th>Sobrante bueno</th><th>Estado</th></tr></thead>
    <tbody id="rseFilas"><tr><td colspan="10" class="rse-empty">Consulta para ver los envíos externos.</td></tr></tbody>
  </table></div>
</section>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const RSE_API = 'controllers/clssReporteServiciosExternos.php';
let rseFilasActuales = [];
const rse = id => document.getElementById(id);
const rseEsc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const rseQty = v => Number(v || 0).toLocaleString('es-PE', { maximumFractionDigits: 3 });
const rseMap = obj => Object.entries(obj || {}).map(([u, n]) => `${rseQty(n)} ${u}`).join(' · ') || '0';
const rseFecha = v => v ? new Date(String(v).replace(' ', 'T')).toLocaleString('es-PE') : '—';
function rseFechasIniciales() {
    const hoy = new Date();
    const primero = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
    const iso = d => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
    rse('rseDesde').value = iso(primero);
    rse('rseHasta').value = iso(hoy);
}
async function rseConsultar() {
    const tbody = rse('rseFilas');
    tbody.innerHTML = '<tr><td colspan="10" class="rse-empty"><i class="fa-solid fa-spinner fa-spin"></i> Consultando...</td></tr>';
    const body = new URLSearchParams({
        accion: 'REPORTESERVICIOSEXTERNOS',
        fecha_desde: rse('rseDesde').value,
        fecha_hasta: rse('rseHasta').value,
        destinatario: rse('rseDestinatario').value.trim(),
        estado: rse('rseEstado').value,
    });
    try {
        const resp = await fetch(RSE_API, { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body });
        const data = await resp.json();
        if (!data.success) throw new Error(data.message || 'No se pudo consultar el reporte.');
        rseRender(data);
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="10" class="rse-empty text-danger">${rseEsc(error.message)}</td></tr>`;
        rse('rseResumen').innerHTML = '';
    }
}
function rseRender(data) {
    rseFilasActuales = data.filas || [];
    const s = data.resumen || {};
    rse('rseResumen').innerHTML = `
        <div class="rse-stat"><b>${s.envios || 0}</b><span>Envíos externos</span></div>
        <div class="rse-stat"><b>${s.pendientes || 0}</b><span>Pendientes de retorno</span></div>
        <div class="rse-stat"><b>${s.recibidos || 0}</b><span>Componentes recibidos</span></div>
        <div class="rse-stat"><b>${rseEsc(rseMap(s.producto_armado_por_unidad))}</b><span>Producto armado recibido</span></div>
        <div class="rse-stat"><b>${rseEsc(rseMap(s.merma_por_unidad))}</b><span>Merma externa</span></div>
        <div class="rse-stat"><b>${rseEsc(rseMap(s.sobrante_por_unidad))}</b><span>Sobrante bueno</span></div>`;
    rse('rseConteo').textContent = `${rseFilasActuales.length} componentes enviados`;
    rse('rseFilas').innerHTML = rseFilasActuales.length ? rseFilasActuales.map(f => `
        <tr>
            <td>${rseEsc(rseFecha(f.fecha_envio))}</td>
            <td>${rseEsc(f.enviado_a || '—')}</td>
            <td><b>${rseEsc(f.componente)}</b>${f.color ? `<br><small>${rseEsc(f.color)}</small>` : ''}</td>
            <td>${f.tipo_envio === 'componente_ensamblado' ? `Ensamblaje local #${rseEsc(f.ensamblaje_componente_id)}` : `Producción #${rseEsc(f.produccion_id)} · Pasada ${rseEsc(f.pasada ?? '—')}`}</td>
            <td>${rseEsc(rseQty(f.cantidad_enviada))} ${rseEsc(f.unidad_componente)}</td>
            <td>${f.estado === 'recibido' ? `${rseEsc(rseQty(f.cantidad_producto_armado))} ${rseEsc(f.unidad_producto)}<br><small>${rseEsc(f.producto_armado_codigo || '')} ${rseEsc(f.producto_armado || '')} · #${rseEsc(f.ensamblaje_id)}</small>` : '—'}</td>
            <td>${f.estado === 'recibido' ? `${rseEsc(rseQty(f.cantidad_utilizada))} ${rseEsc(f.unidad_componente)}` : '—'}</td>
            <td>${f.estado === 'recibido' ? `${rseEsc(rseQty(f.cantidad_merma))} ${rseEsc(f.unidad_componente)}${f.motivo_merma ? `<span class="rse-motivo">${rseEsc(f.motivo_merma)}</span>` : ''}` : '—'}</td>
            <td>${f.estado === 'recibido' ? `${rseEsc(rseQty(f.cantidad_sobrante))} ${rseEsc(f.unidad_componente)}` : '—'}</td>
            <td>${f.estado === 'recibido' ? '<span class="badge text-bg-success">Recibido</span>' : '<span class="badge text-bg-warning">Pendiente</span>'}</td>
        </tr>`).join('') : '<tr><td colspan="10" class="rse-empty">No hay envíos externos con esos filtros.</td></tr>';
}
function rseExportar() {
    const cols = ['Fecha de envío','Destinatario','Componente','Origen','Pasada','Cantidad enviada','Unidad','Producto armado recibido','Cantidad armada','Unidad producto','Usado','Merma','Motivo de merma','Sobrante bueno','Estado'];
    const rows = rseFilasActuales.map(f => [f.fecha_envio,f.enviado_a,f.componente,f.tipo_envio === 'componente_ensamblado' ? `Ensamblaje #${f.ensamblaje_componente_id}` : `Producción #${f.produccion_id}`,f.pasada,f.cantidad_enviada,f.unidad_componente,f.producto_armado,f.cantidad_producto_armado,f.unidad_producto,f.cantidad_utilizada,f.cantidad_merma,f.motivo_merma,f.cantidad_sobrante,f.estado]);
    const csv = '\ufeff' + [cols, ...rows].map(row => row.map(v => `"${String(v ?? '').replaceAll('"','""')}"`).join(';')).join('\r\n');
    const url = URL.createObjectURL(new Blob([csv], {type:'text/csv;charset=utf-8'}));
    const a = document.createElement('a'); a.href = url; a.download = 'reporte-servicios-externos.csv'; a.click(); URL.revokeObjectURL(url);
}
rseFechasIniciales();
rse('rseConsultar').addEventListener('click', rseConsultar);
rse('rseLimpiar').addEventListener('click', () => { rseFechasIniciales(); rse('rseDestinatario').value=''; rse('rseEstado').value=''; rseConsultar(); });
rse('rseExportar').addEventListener('click', rseExportar);
rse('rseDestinatario').addEventListener('keydown', e => { if (e.key === 'Enter') rseConsultar(); });
rseConsultar();
</script>
<?php include 'footer.php'; ?>
