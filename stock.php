<?php
$pageTitle    = 'Stock de productos';
$pageSubtitle = 'Stock empaquetado disponible, por producto y color';
$activePage   = 'disponibilidad_venta';

include("header.php");
?>

<style>
.pc-dv-tabla-wrap{ max-height:560px; overflow-y:auto; border:1px solid #eee7db; border-radius:10px; }
.pc-dv-tabla{ width:100%; font-size:.88em; border-collapse:collapse; }
.pc-dv-tabla th{ position:sticky; top:0; background:#fdfcfa; text-align:left; padding:9px 12px; border-bottom:1px solid #eee7db; font-size:.78em; color:#8a8578; text-transform:uppercase; z-index:1; }
.pc-dv-tabla td{ padding:9px 12px; border-bottom:1px dashed #eee2c8; vertical-align:top; }
.pc-dv-tabla tr:last-child td{ border-bottom:none; }
.pc-dv-color-dot{ display:inline-block; width:10px; height:10px; border-radius:50%; background:#ccc; margin-right:6px; vertical-align:middle; }
.pc-dv-color-dot.sin-color{ background:repeating-linear-gradient(45deg, #ccc, #ccc 2px, #fff 2px, #fff 4px); border:1px solid #bbb; }

.pc-dv-paquetes{ font-weight:700; color:#2F6FED; font-size:1.05em; }
.pc-dv-paquetes small{ font-weight:600; color:#5c85e8; }
.pc-dv-cantidad-base{ color:#9a9585; font-size:.85em; }

.pc-dv-filtros{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:14px; }
.pc-dv-filtros select, .pc-dv-filtros input[type="text"]{ max-width:220px; }
.pc-dv-legado{ font-style:italic; color:#9a9585; }

.pc-dv-color-dot.mezcla{
    background: conic-gradient(#2F6FED 0deg 90deg, #E0574C 90deg 180deg, #F0B429 180deg 270deg, #2FB170 270deg 360deg);
}
.pc-dv-mezcla-texto{ font-weight:600; color:#3a3730; }

.pc-dv-warning-mix{ color:#c9822a; margin-left:6px; cursor:help; }

.pc-dv-subtotal td{
    background:#fbf9f3; font-weight:700; border-bottom:2px solid #eee2c8;
    border-top:1px solid #eee2c8; color:#3a3730;
}
.pc-dv-subtotal .pc-dv-paquetes{ font-size:1em; }

.pc-dv-producto-header{ display:flex; align-items:baseline; gap:8px; }
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
                    <th>Paquetes disponibles</th>
                    <th>Cantidad (unidad base)</th>
                </tr>
            </thead>
            <tbody id="tablaDisponibilidadVenta">
                <tr><td colspan="5" class="text-center text-muted">Cargando...</td></tr>
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

function formatearNumeroDV(n, decimales = 2) {
    if (n === null || n === undefined || n === '') return '-';
    return Number(n).toLocaleString('es-PE', { maximumFractionDigits: decimales });
}

async function cargarColoresDV() {
    const json = await llamarDV('BUSCARCOLORESDV');
    const sel = document.getElementById('dv_color_id');
    if (json.success) {
        sel.innerHTML = '<option value="">Todos los colores</option>' +
            json.colores.map(c => `<option value="${c.id}">${c.nombre}</option>`).join('');
    }
}

// Arma un array de "grupos" (uno por producto), asumiendo que las filas ya
// vienen ordenadas por producto desde el backend (ORDER BY p.descripcion...).
function agruparPorProductoDV(filas) {
    const grupos = [];
    let actual = null;
    filas.forEach(f => {
        if (!actual || actual.producto_id !== f.producto_id) {
            actual = {
                producto_id: f.producto_id,
                producto_codigo: f.producto_codigo,
                producto: f.producto,
                filas: [],
            };
            grupos.push(actual);
        }
        actual.filas.push(f);
    });
    return grupos;
}

function filaHtmlDV(f) {
    const esLegado = f.color_id === null || f.color_id === undefined;
    const esMezcla = f.color_id === -1;

    let dotClase = 'pc-dv-color-dot';
    let colorTexto = f.color ?? '-';

    if (esLegado) {
        dotClase += ' sin-color';
        colorTexto = `<span class="pc-dv-legado">${colorTexto}</span>`;
    } else if (esMezcla) {
        dotClase += ' mezcla';
        colorTexto = `<span class="pc-dv-mezcla-texto">${colorTexto}</span>`;
    }

    const warningMix = f.unidades_paquete_distintas
        ? `<span class="pc-dv-warning-mix" title="Este grupo mezcla registros con distinta unidad de empaquetado configurada. La suma de paquetes es aproximada.">⚠️</span>`
        : '';

    return `
    <tr>
        <td><b>${f.producto_codigo ?? ''}</b> - ${f.producto ?? '-'}</td>
        <td><span class="${dotClase}"></span>${colorTexto}</td>
        <td>${f.registros_count ?? 0}</td>
        <td>
            <span class="pc-dv-paquetes">${formatearNumeroDV(f.paquetes_disponibles)} <small>${f.unidad_paquete_corto ?? ''}</small></span>
            ${warningMix}
        </td>
        <td class="pc-dv-cantidad-base">${formatearNumeroDV(f.cantidad_disponible, 4)} ${f.unidad_corto ?? ''}</td>
    </tr>`;
}

function subtotalHtmlDV(grupo) {
    const totalPaquetes = grupo.filas.reduce((acc, f) => acc + Number(f.paquetes_disponibles || 0), 0);
    const totalBase     = grupo.filas.reduce((acc, f) => acc + Number(f.cantidad_disponible || 0), 0);

    // Si todas las filas del producto comparten la misma unidad de
    // empaquetado, se muestra junto al total; si no, se omite (ya cada
    // fila trae su propia unidad + el aviso ⚠️ si aplica).
    const unidadesPaquete = [...new Set(grupo.filas.map(f => f.unidad_paquete_corto).filter(Boolean))];
    const unidadesBase    = [...new Set(grupo.filas.map(f => f.unidad_corto).filter(Boolean))];
    const sufijoPaquete = unidadesPaquete.length === 1 ? ` <small>${unidadesPaquete[0]}</small>` : '';
    const sufijoBase    = unidadesBase.length === 1 ? ` ${unidadesBase[0]}` : '';

    // NUEVO: si CUALQUIER fila del grupo ya venía marcada como
    // unidades_paquete_distintas, o si el propio grupo junta más de una
    // unidad de paquete entre sus colores, el total también lo advierte
    // (antes el ⚠️ solo aparecía por fila y el total podía verse "limpio").
    const huboAvisoEnFilas = grupo.filas.some(f => f.unidades_paquete_distintas);
    const totalEsAproximado = huboAvisoEnFilas || unidadesPaquete.length > 1;
    const warningTotal = totalEsAproximado
        ? `<span class="pc-dv-warning-mix" title="El total de paquetes de este producto combina registros con distinta unidad de empaquetado. Es una suma aproximada.">⚠️</span>`
        : '';

    return `
    <tr class="pc-dv-subtotal">
        <td colspan="3">Total ${grupo.producto_codigo ?? ''} - ${grupo.producto ?? ''}</td>
        <td><span class="pc-dv-paquetes">${formatearNumeroDV(totalPaquetes)}${sufijoPaquete}</span>${warningTotal}</td>
        <td class="pc-dv-cantidad-base">${formatearNumeroDV(totalBase, 4)}${sufijoBase}</td>
    </tr>`;
}

async function cargarDisponibilidadVenta() {
    const tbody = document.getElementById('tablaDisponibilidadVenta');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted"><i class="fa-solid fa-spinner fa-spin"></i> Cargando...</td></tr>';

    const params = {
        texto: document.getElementById('dv_texto').value.trim(),
        color_id: document.getElementById('dv_color_id').value,
        incluir_vendidos: document.getElementById('dv_incluir_vendidos').checked ? '1' : '0',
    };
    const json = await llamarDV('LISTARDISPONIBILIDADVENTA', params);

    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">${json.message}</td></tr>`;
        return;
    }

    const filas = json.disponibilidad || [];
    if (filas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No hay stock disponible con estos filtros.</td></tr>';
        return;
    }

    const grupos = agruparPorProductoDV(filas);
    let html = '';
    grupos.forEach(grupo => {
        grupo.filas.forEach(f => { html += filaHtmlDV(f); });
        // Subtotal solo tiene sentido si el producto tiene más de un color/bucket
        if (grupo.filas.length > 1) {
            html += subtotalHtmlDV(grupo);
        }
    });
    tbody.innerHTML = html;
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