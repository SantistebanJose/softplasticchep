<?php
$pageTitle    = 'Kardex';
$pageSubtitle = 'Kardex de materiales y productos';
$activePage   = 'kardex';

include("header.php");
?>

<style>
    .pc-filtros {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: flex-end;
    }
    .pc-filtros .campo {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .pc-filtros .campo-item { flex: 1 1 260px; min-width: 220px; }
    .pc-filtros .campo-fecha { flex: 1 1 150px; min-width: 140px; }
    .pc-filtros .campo-movimiento { flex: 1 1 160px; min-width: 150px; }
    .pc-filtros .campo-acciones {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-left: auto;
    }
    .pc-filtros .campo-acciones .pc-btn { white-space: nowrap; }

    /* ── Tablet / mobile (≤768px) ────────────────────────────────────── */
    @media (max-width: 768px) {
        .pc-filtros { flex-direction: column; align-items: stretch; }
        .pc-filtros .campo,
        .pc-filtros .campo-acciones { width: 100%; margin-left: 0; }
        .pc-filtros .campo-acciones .pc-btn { flex: 1 1 auto; }

        .pc-card-header h2 { font-size: 1.15rem; }
        #btnToggleStock { font-size: 0.85rem; }

        #resumenKardex .col-md-4 { flex: 0 0 100%; max-width: 100%; }
        #resumenKardex .pc-card { padding: 10px !important; }
        #resumenKardex .fs-4 { font-size: 1.25rem !important; }
    }

    /* ── Celulares chicos (≤480px) ───────────────────────────────────── */
    @media (max-width: 480px) {
        .pc-filtros .campo-acciones {
            flex-direction: column;
        }
        .pc-filtros .campo-acciones .pc-btn {
            width: 100%;
        }

        .pc-page-title h1 { font-size: 1.1rem; }
        .pc-page-subtitle { font-size: 0.8rem; }

        #kardexPaginacion {
            justify-content: center;
            text-align: center;
        }
        #kardexPaginacion span { width: 100%; order: -1; margin-bottom: 4px; }

        .pc-table-responsive-cards .badge { font-size: 0.7rem; }
    }

    #kardexPaginacion { flex-wrap: wrap; gap: 8px; }
    #kardexPaginacion span { font-size: 0.9rem; }

    .text-saldo-cero { color: #dc3545; font-weight: 700; }

    /* Fallback: si por lo que sea el card-view no aplica, que al menos scrollee */
    .pc-table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .pc-stock-filtros {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: flex-end;
        margin-bottom: 12px;
    }
    .pc-stock-filtros .campo { display: flex; flex-direction: column; gap: 4px; flex: 1 1 220px; min-width: 180px; }
    .pc-stock-filtros .campo-tipo { flex: 0 1 160px; min-width: 140px; }
    @media (max-width: 768px) {
        .pc-stock-filtros { flex-direction: column; align-items: stretch; }
        .pc-stock-filtros .campo { width: 100%; }
    }
    @media (max-width: 480px) {
        .pc-stock-filtros .campo button.pc-btn { width: 100%; }
    }

    #tablaStockGeneral tbody tr { cursor: pointer; }
    #tablaStockGeneral tbody tr:hover { background: rgba(0,0,0,0.03); }
</style>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Kardex</h2>
        <button class="pc-btn pc-btn-outline-secondary" id="btnToggleStock" onclick="toggleStockGeneral()">
            <i class="fa-solid fa-boxes-stacked"></i> Ver stock general
        </button>
    </div>

    <!-- ── Stock general (todos los ítems con saldo actual) ─────────────────── -->
    <div id="bloqueStockGeneral" style="display:none;" class="mb-4">
        <div class="pc-stock-filtros">
            <div class="campo">
                <label class="form-label">Buscar</label>
                <input type="text" id="sg_texto" class="form-control" placeholder="Nombre de material o producto...">
            </div>
            <div class="campo campo-tipo">
                <label class="form-label">Tipo</label>
                <select id="sg_tipo_item" class="form-control">
                    <option value="">Todos</option>
                    <option value="MATERIAL">Material</option>
                    <option value="PRODUCTO">Producto</option>
                </select>
            </div>
            <div class="campo" style="flex:0 1 auto;">
                <button class="pc-btn pc-btn-primary" onclick="buscarStockGeneral()">
                    <i class="fa-solid fa-magnifying-glass"></i> Filtrar
                </button>
            </div>
        </div>

        <div class="pc-table-wrap pc-table-responsive-cards">
        <table class="pc-table" id="tablaStockGeneral">
            <thead>
                <tr>
                    <th>Ítem</th>
                    <th>Tipo</th>
                    <th>Saldo actual</th>
                    <th>Última actividad</th>
                </tr>
            </thead>
            <tbody id="tbodyStockGeneral">
                <tr><td colspan="4" style="text-align:center;">Cargando stock general...</td></tr>
            </tbody>
        </table>
        </div>
        <p class="text-muted small mb-0">Clic en una fila para ver su kardex detallado.</p>
    </div>

    <div class="pc-filtros mb-3">
        <div class="campo campo-item">
            <label class="form-label">Ítem (material o producto)</label>
            <select id="fk_item" placeholder="Buscar ítem..."></select>
        </div>

        <div class="campo campo-fecha">
            <label class="form-label">Desde</label>
            <input type="date" id="fk_fecha_inicio" class="form-control">
        </div>

        <div class="campo campo-fecha">
            <label class="form-label">Hasta</label>
            <input type="date" id="fk_fecha_fin" class="form-control">
        </div>

        <div class="campo campo-movimiento">
            <label class="form-label">Movimiento</label>
            <select id="fk_tipo_movimiento" class="form-control">
                <option value="">Todos</option>
                <option value="COMPRA">Compra</option>
                <option value="PRODUCCION">Producción</option>
                <option value="EMPAQUETADO">Empaquetado</option>
                <option value="VENTA">Venta</option>
            </select>
        </div>

        <div class="campo-acciones">
            <button class="pc-btn pc-btn-primary" onclick="buscarKardex()">
                <i class="fa-solid fa-magnifying-glass"></i> Buscar
            </button>
            <button class="pc-btn pc-btn-outline-secondary" onclick="exportarKardexCSV()">
                <i class="fa-solid fa-file-csv"></i> Exportar
            </button>
        </div>
    </div>

    <div class="row mb-3 g-2" id="resumenKardex" style="display:none;">
        <div class="col-md-4">
            <div class="pc-card p-3 text-center">
                <div class="text-muted small">Saldo actual</div>
                <div class="fs-4 fw-bold" id="resSaldoActual">-</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="pc-card p-3 text-center">
                <div class="text-muted small">Total entradas (rango)</div>
                <div class="fs-4 fw-bold text-success" id="resTotalEntradas">-</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="pc-card p-3 text-center">
                <div class="text-muted small">Total salidas (rango)</div>
                <div class="fs-4 fw-bold text-danger" id="resTotalSalidas">-</div>
            </div>
        </div>
    </div>

    <div class="pc-table-wrap pc-table-responsive-cards">
    <table class="pc-table" id="tablaKardex">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Movimiento</th>
                <th>Referencia</th>
                <th>Entrada</th>
                <th>Salida</th>
                <th>Saldo</th>
            </tr>
        </thead>
        <tbody id="tbodyKardex">
            <tr><td colspan="6" style="text-align:center;">Selecciona un ítem para ver su kardex.</td></tr>
        </tbody>
    </table>
    </div>

    <div id="kardexPaginacion" class="d-flex justify-content-between align-items-center mt-2"></div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const CONTROLADOR_KARDEX = 'controllers/clssKardex.php'; // clssKardex.php vive en su propia carpeta

let tomSelectItem = null;
let ultimoKardex  = [];   // guarda la última búsqueda completa (para exportar y paginar)
let paginaActual  = 1;
const FILAS_POR_PAGINA = 20;

document.addEventListener('DOMContentLoaded', () => {
    tomSelectItem = new TomSelect('#fk_item', {
        valueField: 'valor',
        labelField: 'texto',
        searchField: 'texto',
        options: [],
        create: false,
        placeholder: 'Buscar material o producto...',
        load: function (query, callback) {
            llamarKardex('LISTARCATALOGOITEMS', { texto: query }).then(json => {
                if (!json.success) { callback(); return; }
                const items = (json.items || []).map(i => ({
                    valor: `${i.tipo_item}:${i.item_id}`,
                    texto: `${i.item_nombre} (${i.tipo_item === 'MATERIAL' ? 'Material' : 'Producto'})`
                }));
                callback(items);
            }).catch(() => callback());
        }
    });

    // Carga inicial (sin texto) para que el desplegable no arranque vacío
    llamarKardex('LISTARCATALOGOITEMS', {}).then(json => {
        if (!json.success) return;
        (json.items || []).forEach(i => {
            tomSelectItem.addOption({
                valor: `${i.tipo_item}:${i.item_id}`,
                texto: `${i.item_nombre} (${i.tipo_item === 'MATERIAL' ? 'Material' : 'Producto'})`
            });
        });
    });
});

// ── Llamada genérica al controlador ──────────────────────────────────────────
async function llamarKardex(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_KARDEX, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body
    });
    const texto = await resp.text();
    try {
        return JSON.parse(texto);
    } catch (e) {
        console.error(`Respuesta no es JSON válido para accion=${accion}:`, texto);
        throw new Error(`El servidor no devolvió JSON válido (accion=${accion}). Revisa la consola.`);
    }
}

// ── Buscar kardex ─────────────────────────────────────────────────────────────
async function buscarKardex() {
    const valor = tomSelectItem.getValue();
    const tbody = document.getElementById('tbodyKardex');

    if (!valor) {
        Swal.fire('Atención', 'Selecciona un ítem primero.', 'warning');
        return;
    }

    const [tipo_item, item_id] = valor.split(':');
    const fecha_inicio    = document.getElementById('fk_fecha_inicio').value;
    const fecha_fin       = document.getElementById('fk_fecha_fin').value;
    const tipo_movimiento = document.getElementById('fk_tipo_movimiento').value;

    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Cargando...</td></tr>';
    document.getElementById('kardexPaginacion').innerHTML = '';

    const json = await llamarKardex('OBTENERKARDEX', { tipo_item, item_id, fecha_inicio, fecha_fin, tipo_movimiento });

    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;">${json.message}</td></tr>`;
        document.getElementById('resumenKardex').style.display = 'none';
        ultimoKardex = [];
        return;
    }

    ultimoKardex = json.movimientos || [];
    paginaActual = 1;
    renderKardexPaginado();
    renderResumen(json.resumen || {});
}

// ── Exportar CSV ──────────────────────────────────────────────────────────────
function exportarKardexCSV() {
    if (!ultimoKardex.length) {
        Swal.fire('Atención', 'No hay datos para exportar.', 'warning');
        return;
    }
    const filas = [['Fecha', 'Movimiento', 'Referencia', 'Entrada', 'Salida', 'Saldo']];
    ultimoKardex.forEach(m => {
        filas.push([
            new Date(m.fecha).toLocaleString('es-PE'),
            m.tipo_movimiento,
            referenciaDe(m),
            m.entrada || 0,
            m.salida || 0,
            m.saldo || 0
        ]);
    });
    const csv = filas.map(f => f.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob(["\ufeff" + csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `kardex_${tomSelectItem.getValue()}_${Date.now()}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
}

const BADGE_MOVIMIENTO = {
    COMPRA:      'bg-success',
    PRODUCCION:  'bg-primary',
    EMPAQUETADO: 'bg-info text-dark',
    VENTA:       'bg-danger',
};

function referenciaDe(m) {
    if (m.tipo_movimiento === 'COMPRA')      return `Compra #${m.movimiento_id}`;
    if (m.tipo_movimiento === 'PRODUCCION')  return `Producción #${m.produccion_id}`;
    if (m.tipo_movimiento === 'EMPAQUETADO') return `Empaquetado #${m.empaquetado_id}`;
    if (m.tipo_movimiento === 'VENTA')       return `Venta #${m.venta_id}`;
    return '-';
}

// Para entrada/salida: oculta el 0 (esa columna no aplica a ese movimiento)
function formatoEntradaSalida(m, valor) {
    const num = parseFloat(valor) || 0;
    if (num === 0) return '-';
    const unidad = m.unidad_medida_nombre ? ` ${m.unidad_medida_nombre}` : '';
    return num.toFixed(2) + unidad;
}

// Para saldo: siempre muestra el número, incluso si es 0 (stock en cero es un dato real)
function formatoSaldo(m) {
    const num = parseFloat(m.saldo) || 0;
    const unidad = m.unidad_medida_nombre ? ` ${m.unidad_medida_nombre}` : '';
    return num.toFixed(2) + unidad;
}

function renderKardex(movimientos) {
    const tbody = document.getElementById('tbodyKardex');

    if (movimientos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Sin movimientos en el rango seleccionado.</td></tr>';
        return;
    }

    tbody.innerHTML = movimientos.map(m => {
        const fecha = new Date(m.fecha).toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' });
        const badge = BADGE_MOVIMIENTO[m.tipo_movimiento] || 'bg-secondary';
        const saldoNum   = parseFloat(m.saldo) || 0;
        const claseSaldo = saldoNum <= 0 ? 'text-saldo-cero' : 'fw-bold';

        return `
    <tr>
        <td data-label="Fecha">${fecha}</td>
        <td data-label="Movimiento"><span class="badge ${badge}">${m.tipo_movimiento}</span></td>
        <td data-label="Referencia">${referenciaDe(m)}</td>
        <td data-label="Entrada" class="text-success">${formatoEntradaSalida(m, m.entrada)}</td>
        <td data-label="Salida" class="text-danger">${formatoEntradaSalida(m, m.salida)}</td>
        <td data-label="Saldo" class="${claseSaldo}">${formatoSaldo(m)}</td>
    </tr>`;
    }).join('');
}

function renderResumen(resumen) {
    document.getElementById('resumenKardex').style.display = 'flex';
    document.getElementById('resSaldoActual').textContent   = (parseFloat(resumen.saldo_actual) || 0).toFixed(2);
    document.getElementById('resTotalEntradas').textContent = (parseFloat(resumen.total_entradas) || 0).toFixed(2);
    document.getElementById('resTotalSalidas').textContent  = (parseFloat(resumen.total_salidas) || 0).toFixed(2);
}

// ── Paginación (client-side, sobre lo ya cargado) ─────────────────────────────
function renderKardexPaginado() {
    const total = ultimoKardex.length;
    const totalPaginas = Math.max(1, Math.ceil(total / FILAS_POR_PAGINA));
    if (paginaActual > totalPaginas) paginaActual = totalPaginas;

    const inicio  = (paginaActual - 1) * FILAS_POR_PAGINA;
    const pagina  = ultimoKardex.slice(inicio, inicio + FILAS_POR_PAGINA);

    renderKardex(pagina);
    renderControlesPaginacion(totalPaginas);
}

function renderControlesPaginacion(totalPaginas) {
    const cont = document.getElementById('kardexPaginacion');
    if (totalPaginas <= 1) { cont.innerHTML = ''; return; }
    cont.innerHTML = `
        <button class="pc-btn pc-btn-sm" ${paginaActual === 1 ? 'disabled' : ''} onclick="cambiarPagina(-1)">← Anterior</button>
        <span>Página ${paginaActual} de ${totalPaginas}</span>
        <button class="pc-btn pc-btn-sm" ${paginaActual === totalPaginas ? 'disabled' : ''} onclick="cambiarPagina(1)">Siguiente →</button>
    `;
}

function cambiarPagina(delta) {
    paginaActual += delta;
    renderKardexPaginado();
}

// ── Stock general ──────────────────────────────────────────────────────────
let stockGeneralCargado = false;

function toggleStockGeneral() {
    const bloque = document.getElementById('bloqueStockGeneral');
    const btn    = document.getElementById('btnToggleStock');
    const mostrar = bloque.style.display === 'none';

    bloque.style.display = mostrar ? 'block' : 'none';
    btn.innerHTML = mostrar
        ? '<i class="fa-solid fa-xmark"></i> Ocultar stock general'
        : '<i class="fa-solid fa-boxes-stacked"></i> Ver stock general';

    if (mostrar && !stockGeneralCargado) {
        buscarStockGeneral();
    }
}

async function buscarStockGeneral() {
    const tbody = document.getElementById('tbodyStockGeneral');
    const texto     = document.getElementById('sg_texto').value.trim();
    const tipo_item = document.getElementById('sg_tipo_item').value;

    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">Cargando...</td></tr>';

    const json = await llamarKardex('LISTARSTOCKGENERAL', { texto, tipo_item });

    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;">${json.message}</td></tr>`;
        return;
    }

    stockGeneralCargado = true;
    renderStockGeneral(json.stock || []);
}

function renderStockGeneral(stock) {
    const tbody = document.getElementById('tbodyStockGeneral');

    if (stock.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">Sin ítems con movimientos registrados.</td></tr>';
        return;
    }

    tbody.innerHTML = stock.map(s => {
        const saldoNum   = parseFloat(s.saldo) || 0;
        const claseSaldo = saldoNum <= 0 ? 'text-saldo-cero' : 'fw-bold';
        const unidad     = s.unidad_medida_nombre ? ` ${s.unidad_medida_nombre}` : '';
        const fecha      = s.fecha ? new Date(s.fecha).toLocaleDateString('es-PE') : '-';
        const tipoLabel  = s.tipo_item === 'MATERIAL' ? 'Material' : 'Producto';

        return `
    <tr onclick="abrirKardexDesdeStock('${s.tipo_item}', ${s.item_id})">
        <td data-label="Ítem">${s.item_nombre}</td>
        <td data-label="Tipo"><span class="badge bg-secondary">${tipoLabel}</span></td>
        <td data-label="Saldo actual" class="${claseSaldo}">${saldoNum.toFixed(2)}${unidad}</td>
        <td data-label="Última actividad">${fecha}</td>
    </tr>`;
    }).join('');
}

function abrirKardexDesdeStock(tipo_item, item_id) {
    const valor = `${tipo_item}:${item_id}`;

    // Si la opción no está cargada aún en el TomSelect (búsqueda no coincide), la agregamos al vuelo
    if (!tomSelectItem.options[valor]) {
        const fila = Array.from(document.querySelectorAll('#tbodyStockGeneral tr'))
            .find(tr => tr.getAttribute('onclick') === `abrirKardexDesdeStock('${tipo_item}', ${item_id})`);
        const nombre = fila ? fila.children[0].textContent : valor;
        tomSelectItem.addOption({
            valor,
            texto: `${nombre} (${tipo_item === 'MATERIAL' ? 'Material' : 'Producto'})`
        });
    }

    tomSelectItem.setValue(valor);
    document.getElementById('bloqueStockGeneral').style.display = 'none';
    document.getElementById('btnToggleStock').innerHTML = '<i class="fa-solid fa-boxes-stacked"></i> Ver stock general';

    buscarKardex();
    document.querySelector('.pc-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>