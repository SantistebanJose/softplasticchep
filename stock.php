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
.pc-dv-bolsas{ color:#9a9585; font-size:.85em; }

.pc-dv-filtros{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:14px; }
.pc-dv-filtros select, .pc-dv-filtros input[type="text"]{ max-width:220px; }
.pc-dv-legado{ font-style:italic; color:#9a9585; }

.pc-dv-color-dot.mezcla{
    background: conic-gradient(#2F6FED 0deg 90deg, #E0574C 90deg 180deg, #F0B429 180deg 270deg, #2FB170 270deg 360deg);
}
.pc-dv-mezcla-texto{ font-weight:600; color:#3a3730; }

.pc-dv-warning-mix{ color:#c9822a; margin-left:6px; cursor:help; }
.pc-dv-warning-config{ color:#c9482a; margin-left:6px; cursor:help; }
.pc-dv-sin-config{ color:#9a9585; font-style:italic; font-size:.85em; }

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
                    <th>Bolsas empaquetadas</th>
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
                config_venta_inconsistente: f.config_venta_inconsistente === true || f.config_venta_inconsistente === 't',
                filas: [],
            };
            grupos.push(actual);
        }
        actual.filas.push(f);
    });
    return grupos;
}

function celdaPaquetesHtmlDV(f) {
    if (f.paquetes_venta_sin_configurar) {
        return `<span class="pc-dv-sin-config" title="Este producto no tiene configurada su unidad de venta (cant_equivale / unidad_equivale_id). Configúrala en Productos.">Sin config. de venta</span>`;
    }
    return `<span class="pc-dv-paquetes">${formatearNumeroDV(f.paquetes_disponibles)} <small>${f.unidad_venta_corto ?? ''}</small></span>`;
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

    const warningBolsasMix = f.unidades_bolsa_distintas
        ? `<span class="pc-dv-warning-mix" title="Este grupo mezcla registros con distinta unidad de empaquetado configurada. La suma de bolsas es aproximada.">⚠️</span>`
        : '';

    return `
    <tr>
        <td><b>${f.producto_codigo ?? ''}</b> - ${f.producto ?? '-'}</td>
        <td><span class="${dotClase}"></span>${colorTexto}</td>
        <td>${f.registros_count ?? 0}</td>
        <td>${celdaPaquetesHtmlDV(f)}</td>
        <td class="pc-dv-bolsas">
            ${formatearNumeroDV(f.bolsas_disponibles)} ${f.unidad_bolsa_corto ?? ''}
            ${warningBolsasMix}
        </td>
    </tr>`;
}

function subtotalHtmlDV(grupo) {
    const tieneSinConfig = grupo.filas.some(f => f.paquetes_venta_sin_configurar);
    const totalPaquetes  = grupo.filas.reduce((acc, f) => acc + Number(f.paquetes_disponibles || 0), 0);
    const totalBolsas    = grupo.filas.reduce((acc, f) => acc + Number(f.bolsas_disponibles || 0), 0);

    const unidadesVenta = [...new Set(grupo.filas.map(f => f.unidad_venta_corto).filter(Boolean))];
    const unidadesBolsa = [...new Set(grupo.filas.map(f => f.unidad_bolsa_corto).filter(Boolean))];
    const sufijoPaquete  = unidadesVenta.length === 1 ? ` <small>${unidadesVenta[0]}</small>` : '';
    const sufijoBolsa    = unidadesBolsa.length === 1 ? ` ${unidadesBolsa[0]}` : '';

    const huboAvisoBolsas   = grupo.filas.some(f => f.unidades_bolsa_distintas);
    const warningBolsasTotal = (huboAvisoBolsas || unidadesBolsa.length > 1)
        ? `<span class="pc-dv-warning-mix" title="El total de bolsas de este producto combina registros con distinta unidad de empaquetado. Es una suma aproximada.">⚠️</span>`
        : '';

    const celdaPaquetesTotal = tieneSinConfig
        ? `<span class="pc-dv-sin-config">Sin config. de venta</span>`
        : `<span class="pc-dv-paquetes">${formatearNumeroDV(totalPaquetes)}${sufijoPaquete}</span>`;

    return `
    <tr class="pc-dv-subtotal">
        <td colspan="3">Total ${grupo.producto_codigo ?? ''} - ${grupo.producto ?? ''}</td>
        <td>${celdaPaquetesTotal}</td>
        <td class="pc-dv-bolsas">${formatearNumeroDV(totalBolsas)}${sufijoBolsa}${warningBolsasTotal}</td>
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
        grupo.filas.forEach((f, idx) => {
            let fila = filaHtmlDV(f);
            // Advertencia de config de venta inconsistente: solo se marca
            // una vez, en la primera fila del producto, junto al nombre.
            if (idx === 0 && grupo.config_venta_inconsistente) {
                fila = fila.replace(
                    '</b>',
                    `</b> <span class="pc-dv-warning-config" title="La unidad de venta configurada en 'Productos' no coincide con la de 'Configuración de empaquetado'. Revisa ambas para que 'Paquetes disponibles' sea correcto.">⚠️</span>`
                );
            }
            html += fila;
        });
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