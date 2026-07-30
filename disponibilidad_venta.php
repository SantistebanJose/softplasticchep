<?php
$pageTitle    = 'Disponibilidad de Venta';
$pageSubtitle = 'Stock empaquetado disponible, por producto y color';
$activePage   = 'disponibilidad_venta';

include("header.php");
?>

<style>
.pc-dv-tabla-wrap{ max-height:560px; overflow-y:auto; border:1px solid #eee7db; border-radius:10px; }
.pc-dv-tabla{ width:100%; font-size:.88em; border-collapse:collapse; }
.pc-dv-tabla th{ position:sticky; top:0; background:#fdfcfa; text-align:left; padding:9px 12px; border-bottom:1px solid #eee7db; font-size:.78em; color:#8a8578; text-transform:uppercase; }
.pc-dv-tabla td{ padding:9px 12px; border-bottom:1px dashed #eee2c8; vertical-align:top; }
.pc-dv-tabla tr:last-child td{ border-bottom:none; }
.pc-dv-color-dot{ display:inline-block; width:10px; height:10px; border-radius:50%; background:#ccc; margin-right:6px; vertical-align:middle; }
.pc-dv-cantidad{ font-weight:700; color:#2F6FED; }
.pc-dv-filtros{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:14px; }
.pc-dv-filtros select, .pc-dv-filtros input[type="text"]{ max-width:220px; }
</style>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Disponibilidad de Venta</h2>
    </div>

    <div class="pc-dv-filtros">
        <input type="text" id="dv_texto" class="form-control" placeholder="Buscar por producto...">
        <select class="form-select" id="dv_color_id">
            <option value="">Todos los colores</option>
        </select>
        <div class="form-check d-flex align-items-center gap-2">
            <input class="form-check-input" type="checkbox" id="dv_incluir_vendidos">
            <label class="form-check-label" for="dv_incluir_vendidos" style="font-size:.85em;">Incluir ya vendidos</label>
        </div>
    </div>

    <div class="pc-dv-tabla-wrap">
        <table class="pc-dv-tabla">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Color</th>
                    <th># Registros</th>
                    <th>Cantidad disponible</th>
                </tr>
            </thead>
            <tbody id="tablaDisponibilidadVenta">
                <tr><td colspan="4" class="text-center text-muted">Cargando...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
const CONTROLADOR_DV = 'controllers/clssDisponibilidadVenta.php';

async function llamarDV(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_DV, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body
    });
    const texto = await resp.text();
    try {
        return JSON.parse(texto);
    } catch (e) {
        console.error(`Respuesta no es JSON válido para accion=${accion}:`, texto);
        throw new Error(`El servidor no devolvió JSON válido (accion=${accion}).`);
    }
}

function formatearCantidadDV(n) {
    if (n === null || n === undefined || n === '') return '-';
    return Number(n).toLocaleString('es-PE', { maximumFractionDigits: 4 });
}

async function cargarColoresDV() {
    const json = await llamarDV('BUSCARCOLORESDV');
    const sel = document.getElementById('dv_color_id');
    if (json.success) {
        sel.innerHTML = '<option value="">Todos los colores</option>' +
            json.colores.map(c => `<option value="${c.id}">${c.nombre}</option>`).join('');
    }
}

async function cargarDisponibilidadVenta() {
    const tbody = document.getElementById('tablaDisponibilidadVenta');
    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted"><i class="fa-solid fa-spinner fa-spin"></i> Cargando...</td></tr>';

    const params = {
        texto: document.getElementById('dv_texto').value.trim(),
        color_id: document.getElementById('dv_color_id').value,
        incluir_vendidos: document.getElementById('dv_incluir_vendidos').checked ? '1' : '0',
    };
    const json = await llamarDV('LISTARDISPONIBILIDADVENTA', params);

    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="4" class="text-center text-danger">${json.message}</td></tr>`;
        return;
    }

    const filas = json.disponibilidad || [];
    if (filas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No hay stock disponible con estos filtros.</td></tr>';
        return;
    }

    tbody.innerHTML = filas.map(f => `
        <tr>
            <td><b>${f.producto_codigo ?? ''}</b> - ${f.producto ?? '-'}</td>
            <td><span class="pc-dv-color-dot"></span>${f.color ?? '-'}</td>
            <td>${f.registros_count ?? 0}</td>
            <td class="pc-dv-cantidad">${formatearCantidadDV(f.cantidad_disponible)} ${f.unidad_corto ?? ''}</td>
        </tr>
    `).join('');
}

document.addEventListener('DOMContentLoaded', () => {
    cargarColoresDV();
    cargarDisponibilidadVenta();

    let debounceTimer = null;
    document.getElementById('dv_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarDisponibilidadVenta, 350);
    });
    document.getElementById('dv_color_id').addEventListener('change', cargarDisponibilidadVenta);
    document.getElementById('dv_incluir_vendidos').addEventListener('change', cargarDisponibilidadVenta);
});
</script>

<?php require __DIR__ . '/footer.php'; ?>