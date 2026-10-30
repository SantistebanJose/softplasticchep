<?php
$pageTitle    = 'Stock de productos';
$pageSubtitle = 'Existencias actuales por producto en su unidad de venta';
$activePage   = 'disponibilidad_venta';

include("header.php");
?>

<style>
.pc-dv-filtros{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:14px; }
.pc-dv-filtros select, .pc-dv-filtros input[type="text"]{ max-width:220px; flex:1; min-width:160px; }

.pc-dv-filtros select, .pc-dv-filtros input[type="text"]{ max-width:320px; flex:1; min-width:180px; }
.pc-dv-table-wrap{overflow-x:auto;border:1px solid #e1e6ef;border-radius:12px;background:#fff;}
.pc-dv-table{width:100%;min-width:680px;border-collapse:collapse;margin:0;}
.pc-dv-table th{padding:11px 14px;background:#f4f6fa;color:#64748b;font-size:.76rem;text-transform:uppercase;letter-spacing:.03em;white-space:nowrap;}
.pc-dv-table td{padding:10px 14px;border-top:1px solid #edf0f5;vertical-align:middle;color:#263247;}
.pc-dv-table tbody tr:hover{background:#f8faff;}
.pc-dv-product{display:flex;align-items:center;gap:10px;min-width:240px;}
.pc-dv-thumb{width:42px;height:42px;border-radius:9px;object-fit:cover;background:#f1f3f6;flex:none;}
.pc-dv-thumb-empty{display:flex;align-items:center;justify-content:center;color:#9aa4b2;font-size:1.1rem;}
.pc-dv-name{font-weight:700;line-height:1.25;}
.pc-dv-code{font-size:.78rem;color:#7b8798;margin-top:3px;}
.pc-dv-number{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums;}
.pc-dv-stock{font-size:1rem;font-weight:800;color:#2563eb;}
.pc-dv-unit{font-size:.8rem;font-weight:700;color:#64748b;margin-left:4px;}
.pc-dv-badge{display:inline-block;border-radius:999px;padding:4px 9px;font-size:.75rem;font-weight:700;background:#e9f8ef;color:#16804a;white-space:nowrap;}
.pc-dv-badge.bajo{background:#fff4db;color:#a56800;}
.pc-dv-badge-agotado{background:#eef1f5;color:#64748b;}
.pc-dv-empty{text-align:center;color:#7b8798;padding:28px 12px;}
.pc-card-header h2{font-size:1.15rem;margin:0;}
@media(max-width:640px){.pc-dv-filtros select,.pc-dv-filtros input[type="text"]{max-width:none;width:100%;}}
</style>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Disponibilidad de Venta</h2>
    </div>

    <div class="pc-dv-filtros">
        <input type="text" id="dv_texto" class="form-control" placeholder="Buscar por producto...">
        <div class="form-check d-flex align-items-center gap-2">
            <input class="form-check-input" type="checkbox" id="dv_incluir_vendidos">
            <label class="form-check-label" for="dv_incluir_vendidos" style="font-size:.85em;">Incluir agotados</label>
        </div>
    </div>

    <div class="pc-dv-table-wrap">
        <table class="pc-dv-table">
            <thead><tr><th>Producto</th><th class="text-end">Stock actual</th><th class="text-end">Mínimo</th><th class="text-end">Máximo</th><th>Estado</th></tr></thead>
            <tbody id="dvTableBody"><tr><td colspan="5" class="pc-dv-empty">Cargando productos...</td></tr></tbody>
        </table>
    </div>
</div>

<script>
const CONTROLADOR_DV = 'controllers/clssDisponibilidadVenta.php';

function escaparHTMLDV(valor) {
    return String(valor ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function resolverImagenDV(ruta) { return ruta || null; }

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

function formatearNumeroDV(n, decimales = 2) {
    if (n === null || n === undefined || n === '') return '-';
    return Number(n).toLocaleString('es-PE', { maximumFractionDigits: decimales });
}

async function cargarDisponibilidadVenta() {
    const tbody = document.getElementById('dvTableBody');
    tbody.innerHTML = '<tr><td colspan="5" class="pc-dv-empty"><i class="fa-solid fa-spinner fa-spin"></i> Cargando...</td></tr>';

    const params = {
        texto: document.getElementById('dv_texto').value.trim(),
        incluir_vendidos: document.getElementById('dv_incluir_vendidos').checked ? '1' : '0',
    };
    const json = await llamarDV('LISTARDISPONIBILIDADVENTA', params);

    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="5" class="pc-dv-empty text-danger">${json.message}</td></tr>`;
        return;
    }
    const filas = json.disponibilidad || [];
    if (!filas.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="pc-dv-empty">No hay productos que coincidan con la búsqueda.</td></tr>';
        return;
    }
    tbody.innerHTML = filas.map(f => {
        const stock = Number(f.paquetes_disponibles || 0);
        const minimo = Number(f.stock_minimo || 0);
        const maximo = Number(f.stock_maximo || 0);
        const bajo = minimo > 0 && stock < minimo;
        const agotado = stock <= 0;
        const unidad = f.unidad_venta_corto || '';
        const imagen = resolverImagenDV(f.producto_imagen);
        const thumb = imagen
            ? `<img class="pc-dv-thumb" src="${escaparHTMLDV(imagen)}" loading="lazy" alt="" onerror="this.style.visibility='hidden'">`
            : '<span class="pc-dv-thumb pc-dv-thumb-empty"><i class="fa-regular fa-image"></i></span>';
        const estado = agotado ? '<span class="pc-dv-badge pc-dv-badge-agotado">Agotado</span>' : bajo ? '<span class="pc-dv-badge bajo">Bajo mínimo</span>' : '<span class="pc-dv-badge">Disponible</span>';
        return `<tr>
            <td><div class="pc-dv-product">${thumb}<div><div class="pc-dv-name">${escaparHTMLDV(f.producto || 'Sin nombre')}</div><div class="pc-dv-code">${escaparHTMLDV(f.producto_codigo || '')}</div></div></div></td>
            <td class="pc-dv-number"><span class="pc-dv-stock">${formatearNumeroDV(stock)}</span><span class="pc-dv-unit">${escaparHTMLDV(unidad)}</span></td>
            <td class="pc-dv-number">${formatearNumeroDV(minimo)} <span class="pc-dv-unit">${escaparHTMLDV(unidad)}</span></td>
            <td class="pc-dv-number">${maximo > 0 ? `${formatearNumeroDV(maximo)} <span class="pc-dv-unit">${escaparHTMLDV(unidad)}</span>` : '—'}</td>
            <td>${estado}</td>
        </tr>`;
    }).join('');
}

document.addEventListener('DOMContentLoaded', () => {
    cargarDisponibilidadVenta();

    let debounceTimer = null;
    document.getElementById('dv_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarDisponibilidadVenta, 350);
    });
    document.getElementById('dv_incluir_vendidos').addEventListener('change', cargarDisponibilidadVenta);
});
</script>

<?php require __DIR__ . '/footer.php'; ?>
