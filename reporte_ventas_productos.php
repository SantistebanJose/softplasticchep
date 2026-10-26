<?php
$pageTitle = 'Reportes de Ventas';
$pageSubtitle = 'Analiza las ventas por producto, vendedor y cliente.';
$activePage = 'reporte_ventas_productos';
include 'header.php';
?>

<style>
.rvp-wrap{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin-bottom:16px}
.rvp-periodos{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
.rvp-periodos button{border:1px solid #d8dfeb;background:#fff;color:#475569;border-radius:9px;padding:9px 14px;font-weight:700;min-height:42px}
.rvp-periodos button.activo{background:#246bfe;border-color:#246bfe;color:#fff}
.rvp-filtros{display:grid;grid-template-columns:repeat(2,minmax(150px,1fr));gap:12px;align-items:end}
.rvp-filtros .rvp-producto{grid-column:1/-1}
.rvp-date-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;grid-column:1/-1}
.rvp-search-actions{display:flex;gap:10px;grid-column:1/-1}
.rvp-search-actions button{flex:1}
.rvp-autocomplete{position:relative}
.rvp-sugerencias{position:absolute;z-index:30;top:calc(100% + 4px);left:0;right:0;max-height:280px;overflow:auto;background:#fff;border:1px solid #dce3ef;border-radius:10px;box-shadow:0 12px 28px rgba(20,33,61,.16);display:none}
.rvp-sugerencias.abierto{display:block}
.rvp-sugerencia{display:flex;width:100%;gap:10px;align-items:center;text-align:left;border:0;border-bottom:1px solid #edf0f5;background:#fff;padding:10px 12px;color:#17233a}
.rvp-sugerencia:last-child{border-bottom:0}.rvp-sugerencia:hover,.rvp-sugerencia:focus{background:#f3f6ff}
.rvp-sugerencia-codigo{flex:0 0 auto;color:#246bfe;font-size:.78rem;font-weight:800}.rvp-sugerencia-nombre{font-weight:600}
.rvp-sugerencias-vacio{padding:12px;color:#64748b;font-size:.88rem}
#rvpFechas[hidden]{display:none!important}
.rvp-filtros label{font-size:.84rem;font-weight:600;color:#475569;margin-bottom:5px}
.rvp-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:16px}
.rvp-stat{background:#fff;border:1px solid #e5e7eb;border-top:3px solid #246bfe;border-radius:12px;padding:15px}
.rvp-stat span{display:block;color:#64748b;text-transform:uppercase;font-size:.72rem;font-weight:700;letter-spacing:.03em}
.rvp-stat strong{display:block;color:#14213d;font-size:1.35rem;margin-top:5px;overflow-wrap:anywhere}
.rvp-chart{height:280px;position:relative}
.rvp-table-wrap{overflow:auto}.rvp-table{width:100%;min-width:720px;border-collapse:collapse}
.rvp-table th,.rvp-table td{padding:11px 12px;border-bottom:1px solid #e9edf3;text-align:left;vertical-align:middle}
.rvp-table th{background:#f3f5f9;color:#5d6878;font-size:.76rem;text-transform:uppercase;white-space:nowrap}
.rvp-table td.num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
.rvp-product-name{font-weight:700;color:#17233a}.rvp-code{font-size:.78rem;color:#718096}
.rvp-bars{display:grid;gap:10px}.rvp-bar-row{display:grid;grid-template-columns:minmax(120px,240px) minmax(60px,1fr) auto;gap:10px;align-items:center;font-size:.84rem}
.rvp-bar-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600}.rvp-bar-track{height:10px;background:#edf1f7;border-radius:999px;overflow:hidden}.rvp-bar-fill{height:100%;background:linear-gradient(90deg,#246bfe,#25a7ff);border-radius:inherit}
.rvp-empty{text-align:center;color:#64748b;padding:30px!important}
.rvp-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 16px}
.rvp-tabs button{border:1px solid #d8dfeb;background:#fff;color:#475569;border-radius:9px;padding:10px 15px;font-weight:700;min-height:42px}
.rvp-tabs button.activo{background:#246bfe;border-color:#246bfe;color:#fff}
#rvpPaneVendedores[hidden],#rvpPaneClientes[hidden],#rvpPaneProductos[hidden]{display:none!important}
.rvp-producto[hidden]{display:none!important}
@media(max-width:760px){.rvp-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.rvp-stat{padding:12px}.rvp-stat strong{font-size:1.15rem}.rvp-wrap{padding:14px}.rvp-bar-row{grid-template-columns:minmax(90px,145px) minmax(40px,1fr);gap:7px}.rvp-bar-row .rvp-bar-value{grid-column:2;text-align:right;font-size:.78rem}}
@media(max-width:480px){.rvp-date-fields{grid-template-columns:1fr}.rvp-periodos{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.rvp-periodos button{padding:8px 6px;font-size:.84rem}.rvp-search-actions{flex-direction:column}.rvp-search-actions button{min-height:44px}}
</style>

<section class="rvp-wrap">
    <div class="rvp-periodos" role="group" aria-label="Periodo de ventas">
        <button type="button" data-rvp-periodo="dia">Día</button>
        <button type="button" data-rvp-periodo="semana">Semana</button>
        <button type="button" data-rvp-periodo="mes" class="activo">Este mes</button>
        <button type="button" data-rvp-periodo="rango">Rango</button>
        <button type="button" data-rvp-periodo="todas">Todas las ventas</button>
    </div>
    <div class="rvp-filtros">
        <div class="rvp-date-fields" id="rvpFechas" hidden>
            <div><label for="rvpDesde">Desde</label><input type="date" class="form-control" id="rvpDesde"></div>
            <div><label for="rvpHasta">Hasta</label><input type="date" class="form-control" id="rvpHasta"></div>
        </div>
        <div class="rvp-producto">
            <label for="rvpTexto">Producto</label>
            <div class="rvp-autocomplete">
                <input type="search" class="form-control" id="rvpTexto" placeholder="Escribe el nombre o código del producto..." autocomplete="off" aria-autocomplete="list" aria-controls="rvpSugerencias">
                <div class="rvp-sugerencias" id="rvpSugerencias" role="listbox"></div>
            </div>
        </div>
        <div class="rvp-search-actions">
            <button class="btn btn-primary" type="button" id="rvpBuscar"><i class="fa-solid fa-magnifying-glass"></i> Consultar</button>
            <button class="btn btn-outline-success" type="button" id="rvpCsv"><i class="fa-solid fa-file-csv"></i> Exportar CSV</button>
        </div>
    </div>
</section>

<nav class="rvp-tabs" role="tablist" aria-label="Reportes de ventas">
    <button type="button" role="tab" aria-selected="true" class="activo" data-rvp-tab="productos">Ventas por productos</button>
    <button type="button" role="tab" aria-selected="false" data-rvp-tab="vendedores">Ventas por vendedor</button>
    <button type="button" role="tab" aria-selected="false" data-rvp-tab="clientes">Ventas por clientes</button>
</nav>

<section class="rvp-stats" id="rvpStats" aria-live="polite">
    <div class="rvp-stat"><span>Importe vendido</span><strong>S/ 0.00</strong></div>
    <div class="rvp-stat"><span>Ventas completadas</span><strong>0</strong></div>
    <div class="rvp-stat"><span>Productos vendidos</span><strong>0</strong></div>
    <div class="rvp-stat"><span>Promedio por venta</span><strong>S/ 0.00</strong></div>
</section>

<section class="rvp-wrap">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h2 class="h5 m-0" id="rvpChartTitulo">Importe vendido por día</h2><span class="text-muted small">Solo ventas completadas</span>
    </div>
    <div class="rvp-chart"><canvas id="rvpChartVentas" aria-label="Gráfico del importe vendido por día" role="img"></canvas></div>
</section>

<div id="rvpPaneProductos" role="tabpanel">
<section class="rvp-wrap">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h2 class="h5 m-0">Productos con más ventas</h2><span class="text-muted small" id="rvpPeriodo"></span>
    </div>
    <div class="rvp-bars" id="rvpBarras"><div class="rvp-empty">Selecciona un periodo y consulta.</div></div>
</section>

<section class="rvp-wrap">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h2 class="h5 m-0">Detalle por producto</h2><span class="text-muted small" id="rvpCantidad"></span>
    </div>
    <div class="rvp-table-wrap">
        <table class="rvp-table">
            <thead><tr><th>Producto</th><th class="text-end">Cantidad vendida</th><th class="text-end">Importe total</th><th class="text-end">Precio promedio</th><th class="text-end">Ventas</th></tr></thead>
            <tbody id="rvpFilas"><tr><td colspan="5" class="rvp-empty">Cargando...</td></tr></tbody>
        </table>
    </div>
</section>

</div>

<div id="rvpPaneVendedores" role="tabpanel" hidden>
    <section class="rvp-wrap">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h2 class="h5 m-0">Resumen por vendedor</h2><span class="text-muted small" id="rvpVendedorCantidad"></span>
        </div>
        <div class="rvp-table-wrap">
            <table class="rvp-table">
                <thead><tr><th>Vendedor</th><th class="text-end">Ventas</th><th class="text-end">Clientes</th><th class="text-end">Promedio</th><th class="text-end">Importe total</th></tr></thead>
                <tbody id="rvpVendedoresFilas"><tr><td colspan="5" class="rvp-empty">Cargando...</td></tr></tbody>
            </table>
        </div>
    </section>
    <section class="rvp-wrap">
        <h2 class="h5 mb-3">Pagos registrados</h2>
        <div class="rvp-table-wrap">
            <table class="rvp-table">
                <thead><tr><th>Vendedor</th><th>Medio</th><th>Destino</th><th class="text-end">Pagos</th><th class="text-end">Importe</th></tr></thead>
                <tbody id="rvpPagosFilas"><tr><td colspan="5" class="rvp-empty">Cargando...</td></tr></tbody>
            </table>
        </div>
    </section>
</div>

<div id="rvpPaneClientes" role="tabpanel" hidden>
    <section class="rvp-wrap">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h2 class="h5 m-0">Resumen por cliente</h2><span class="text-muted small" id="rvpClienteCantidad"></span>
        </div>
        <div class="rvp-table-wrap">
            <table class="rvp-table">
                <thead><tr><th>Cliente</th><th>RUC / DNI</th><th class="text-end">Ventas</th><th class="text-end">Promedio</th><th>Última venta</th><th class="text-end">Importe total</th></tr></thead>
                <tbody id="rvpClientesFilas"><tr><td colspan="6" class="rvp-empty">Cargando...</td></tr></tbody>
            </table>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
const RVP_API = 'controllers/clssVenta.php';
let rvpProductosActuales = [];
let rvpVendedoresActuales = [];
let rvpClientesActuales = [];
let rvpPagosActuales = [];
let rvpTabActual = 'productos';
let rvpChartVentas = null;
const rvpDesde = document.getElementById('rvpDesde');
const rvpHasta = document.getElementById('rvpHasta');
const rvpTexto = document.getElementById('rvpTexto');
const rvpFilas = document.getElementById('rvpFilas');
const rvpStats = document.getElementById('rvpStats');
const rvpBarras = document.getElementById('rvpBarras');
const rvpFechas = document.getElementById('rvpFechas');
const rvpSugerencias = document.getElementById('rvpSugerencias');
let rvpPeriodoActual = 'mes';
let rvpTimerSugerencias = null;
let rvpSolicitudSugerencias = 0;
const rvpEsc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const rvpNumero = value => Number(value || 0).toLocaleString('es-PE', {maximumFractionDigits: 3});
const rvpDinero = value => 'S/ ' + Number(value || 0).toLocaleString('es-PE', {minimumFractionDigits: 2, maximumFractionDigits: 2});

function rvpFechaLocal(date) {
    return `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`;
}

function rvpObtenerFechas() {
    const hoy = new Date();
    const hoyIso = rvpFechaLocal(hoy);
    if (rvpPeriodoActual === 'todas') return {fecha_inicio:'', fecha_fin:''};
    if (rvpPeriodoActual === 'dia') return {fecha_inicio:hoyIso, fecha_fin:hoyIso};
    if (rvpPeriodoActual === 'semana') {
        const inicio = new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate());
        const diaSemana = inicio.getDay();
        inicio.setDate(inicio.getDate() - (diaSemana === 0 ? 6 : diaSemana - 1));
        return {fecha_inicio:rvpFechaLocal(inicio), fecha_fin:hoyIso};
    }
    if (rvpPeriodoActual === 'mes') {
        return {fecha_inicio:rvpFechaLocal(new Date(hoy.getFullYear(), hoy.getMonth(), 1)), fecha_fin:hoyIso};
    }
    return {fecha_inicio:rvpDesde.value, fecha_fin:rvpHasta.value};
}

async function rvpConsultar() {
    const fechas = rvpObtenerFechas();
    if (rvpPeriodoActual === 'rango' && (!fechas.fecha_inicio || !fechas.fecha_fin)) {
        rvpFilas.innerHTML = '<tr><td colspan="5" class="rvp-empty">Selecciona ambas fechas.</td></tr>';
        return;
    }
    if (fechas.fecha_inicio && fechas.fecha_inicio > fechas.fecha_fin) {
        rvpFilas.innerHTML = '<tr><td colspan="5" class="rvp-empty text-danger">La fecha inicial no puede ser posterior a la fecha final.</td></tr>';
        return;
    }
    rvpFilas.innerHTML = '<tr><td colspan="5" class="rvp-empty">Consultando ventas...</td></tr>';
    rvpBarras.innerHTML = '<div class="rvp-empty">Cargando...</div>';
    try {
        const body = new URLSearchParams({
            accion: 'REPORTEVENTASPORPRODUCTO',
            fecha_inicio: fechas.fecha_inicio,
            fecha_fin: fechas.fecha_fin,
            texto: rvpTexto.value.trim()
        });
        const response = await fetch(RVP_API, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body});
        const data = await response.json();
        if (!data.success) throw new Error(data.message || 'No se pudo consultar el reporte.');
        rvpRender(data);
        await rvpConsultarVendedoresClientes(fechas);
    } catch (error) {
        rvpProductosActuales = [];
        rvpFilas.innerHTML = `<tr><td colspan="5" class="rvp-empty text-danger">${rvpEsc(error.message || 'Error de conexión.')}</td></tr>`;
        rvpBarras.innerHTML = '<div class="rvp-empty">No se pudo cargar el resumen.</div>';
    }
}

async function rvpConsultarVendedoresClientes(fechas = rvpObtenerFechas()) {
    const cuerpoVendedores = document.getElementById('rvpVendedoresFilas');
    const cuerpoClientes = document.getElementById('rvpClientesFilas');
    cuerpoVendedores.innerHTML = '<tr><td colspan="5" class="rvp-empty">Consultando...</td></tr>';
    cuerpoClientes.innerHTML = '<tr><td colspan="6" class="rvp-empty">Consultando...</td></tr>';
    document.getElementById('rvpPagosFilas').innerHTML = '<tr><td colspan="5" class="rvp-empty">Consultando...</td></tr>';
    try {
        const body = new URLSearchParams({
            accion: 'REPORTEVENTASPORVENDEDORCLIENTE',
            fecha_inicio: fechas.fecha_inicio,
            fecha_fin: fechas.fecha_fin
        });
        const response = await fetch(RVP_API, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body});
        const data = await response.json();
        if (!data.success) throw new Error(data.message || 'No se pudieron consultar vendedores y clientes.');
        rvpVendedoresActuales = data.vendedores || [];
        rvpClientesActuales = data.clientes || [];
        rvpPagosActuales = data.pagos || [];
        document.getElementById('rvpVendedorCantidad').textContent = `${rvpVendedoresActuales.length} vendedores`;
        document.getElementById('rvpClienteCantidad').textContent = `${rvpClientesActuales.length} clientes`;
        cuerpoVendedores.innerHTML = rvpVendedoresActuales.length ? rvpVendedoresActuales.map(v => `
            <tr>
                <td><div class="rvp-product-name">${rvpEsc(v.vendedor)}</div></td>
                <td class="num">${rvpNumero(v.ventas)}</td>
                <td class="num">${rvpNumero(v.clientes)}</td>
                <td class="num">${rvpDinero(v.promedio_venta)}</td>
                <td class="num fw-bold">${rvpDinero(v.importe_total)}</td>
            </tr>`).join('') : '<tr><td colspan="5" class="rvp-empty">No hay ventas para este periodo.</td></tr>';
        cuerpoClientes.innerHTML = rvpClientesActuales.length ? rvpClientesActuales.map(c => `
            <tr>
                <td><div class="rvp-product-name">${rvpEsc(c.cliente)}</div></td>
                <td>${rvpEsc(c.cliente_ruc || '—')}</td>
                <td class="num">${rvpNumero(c.ventas)}</td>
                <td class="num">${rvpDinero(c.promedio_venta)}</td>
                <td>${c.ultima_venta ? rvpEsc(String(c.ultima_venta).slice(0, 10)) : '—'}</td>
                <td class="num fw-bold">${rvpDinero(c.importe_total)}</td>
            </tr>`).join('') : '<tr><td colspan="6" class="rvp-empty">No hay ventas para este periodo.</td></tr>';
        document.getElementById('rvpPagosFilas').innerHTML = rvpPagosActuales.length ? rvpPagosActuales.map(p => `
            <tr>
                <td>${rvpEsc(p.vendedor)}</td><td>${rvpEsc(rvpEtiquetaMedioPago(p.medio_pago))}</td><td>${rvpEsc(p.destino)}</td>
                <td class="num">${rvpNumero(p.pagos)}</td><td class="num fw-bold">${rvpDinero(p.importe_total)}</td>
            </tr>`).join('') : '<tr><td colspan="5" class="rvp-empty">No hay pagos registrados para este periodo.</td></tr>';
    } catch (error) {
        const mensaje = rvpEsc(error.message || 'Error de conexión.');
        cuerpoVendedores.innerHTML = `<tr><td colspan="5" class="rvp-empty text-danger">${mensaje}</td></tr>`;
        cuerpoClientes.innerHTML = `<tr><td colspan="6" class="rvp-empty text-danger">${mensaje}</td></tr>`;
        document.getElementById('rvpPagosFilas').innerHTML = `<tr><td colspan="5" class="rvp-empty text-danger">${mensaje}</td></tr>`;
        rvpVendedoresActuales = []; rvpClientesActuales = []; rvpPagosActuales = [];
    }
}

function rvpEtiquetaMedioPago(medio) {
    return ({efectivo:'Efectivo', deposito:'Depósito', transferencia:'Transferencia', yape:'Yape', plin:'Plin'})[medio] || medio;
}

function rvpRender(data) {
    rvpProductosActuales = data.productos || [];
    const s = data.resumen || {};
    const ventaCount = Number(s.ventas || 0);
    rvpStats.innerHTML = `
        <div class="rvp-stat"><span>Importe vendido</span><strong>${rvpDinero(s.importe_total)}</strong></div>
        <div class="rvp-stat"><span>Ventas completadas</span><strong>${rvpNumero(ventaCount)}</strong></div>
        <div class="rvp-stat"><span>Productos vendidos</span><strong>${rvpNumero(s.productos)}</strong></div>
        <div class="rvp-stat"><span>Promedio por venta</span><strong>${rvpDinero(s.promedio_venta)}</strong></div>`;
    document.getElementById('rvpPeriodo').textContent = data.todas_las_ventas
        ? 'Todas las ventas'
        : `${data.fecha_inicio} — ${data.fecha_fin}`;
    document.getElementById('rvpChartTitulo').textContent = data.todas_las_ventas
        ? 'Importe vendido por mes'
        : 'Importe vendido por día';
    document.getElementById('rvpCantidad').textContent = `${rvpProductosActuales.length} productos`;
    rvpRenderGrafico(data.dias || []);
    if (!rvpProductosActuales.length) {
        rvpFilas.innerHTML = '<tr><td colspan="5" class="rvp-empty">No hay ventas completadas para los filtros seleccionados.</td></tr>';
        rvpBarras.innerHTML = '<div class="rvp-empty">Sin productos vendidos en este periodo.</div>';
        return;
    }
    rvpFilas.innerHTML = rvpProductosActuales.map(p => `
        <tr>
            <td><div class="rvp-product-name">${rvpEsc(p.producto)}</div><div class="rvp-code">${rvpEsc(p.codigo)}</div></td>
            <td class="num">${rvpNumero(p.cantidad_vendida)} ${rvpEsc(p.unidad)}</td>
            <td class="num fw-bold">${rvpDinero(p.importe_total)}</td>
            <td class="num">${rvpDinero(p.precio_promedio)} / ${rvpEsc(p.unidad)}</td>
            <td class="num">${rvpNumero(p.ventas)}</td>
        </tr>`).join('');

    const top = rvpProductosActuales.slice(0, 8);
    const maximo = Math.max(...top.map(p => Number(p.importe_total || 0)), 1);
    rvpBarras.innerHTML = top.map(p => `
        <div class="rvp-bar-row" title="${rvpEsc(p.producto)}: ${rvpDinero(p.importe_total)}">
            <span class="rvp-bar-name">${rvpEsc(p.codigo)} · ${rvpEsc(p.producto)}</span>
            <div class="rvp-bar-track"><div class="rvp-bar-fill" style="width:${Math.max(2, Number(p.importe_total || 0) / maximo * 100)}%"></div></div>
            <strong class="rvp-bar-value">${rvpDinero(p.importe_total)}</strong>
        </div>`).join('');
}

function rvpRenderGrafico(dias) {
    const canvas = document.getElementById('rvpChartVentas');
    if (rvpChartVentas) rvpChartVentas.destroy();
    if (typeof Chart === 'undefined') return;
    const etiquetas = dias.map(d => {
        const fecha = String(d.fecha);
        const fechaJs = new Date(`${fecha.length === 7 ? `${fecha}-01` : fecha}T00:00:00`);
        return fecha.length === 7
            ? fechaJs.toLocaleDateString('es-PE', {month:'short',year:'2-digit'})
            : fechaJs.toLocaleDateString('es-PE', {day:'2-digit',month:'short'});
    });
    rvpChartVentas = new Chart(canvas, {
        type: 'line',
        data: {
            labels: etiquetas,
            datasets: [{
                label: 'Importe vendido',
                data: dias.map(d => Number(d.importe_total || 0)),
                borderColor: '#246bfe',
                backgroundColor: 'rgba(36,107,254,.12)',
                fill: true,
                tension: .28,
                borderWidth: 3,
                pointRadius: dias.length > 45 ? 0 : 3,
                pointHoverRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: context => ` ${rvpDinero(context.parsed.y)} · ${rvpNumero(dias[context.dataIndex]?.ventas)} ventas` } }
            },
            scales: {
                x: { grid: { display: false }, ticks: { maxTicksLimit: 12, maxRotation: 0 } },
                y: { beginAtZero: true, ticks: { callback: value => rvpDinero(value) } }
            }
        }
    });
}

function rvpExportar() {
    let encabezados;
    let filas;
    let nombreReporte;
    if (rvpTabActual === 'vendedores') {
        if (!rvpVendedoresActuales.length) return;
        encabezados = ['Vendedor','Ventas','Clientes','Promedio por venta','Importe total'];
        filas = rvpVendedoresActuales.map(v => [v.vendedor,v.ventas,v.clientes,v.promedio_venta,v.importe_total]);
        nombreReporte = 'ventas-por-vendedor';
    } else if (rvpTabActual === 'clientes') {
        if (!rvpClientesActuales.length) return;
        encabezados = ['Cliente','RUC / DNI','Ventas','Promedio por venta','Última venta','Importe total'];
        filas = rvpClientesActuales.map(c => [c.cliente,c.cliente_ruc,c.ventas,c.promedio_venta,c.ultima_venta,c.importe_total]);
        nombreReporte = 'ventas-por-cliente';
    } else {
        if (!rvpProductosActuales.length) return;
        encabezados = ['Código','Producto','Cantidad vendida','Unidad de venta','Importe total','Precio promedio','Ventas'];
        filas = rvpProductosActuales.map(p => [p.codigo,p.producto,p.cantidad_vendida,p.unidad,p.importe_total,p.precio_promedio,p.ventas]);
        nombreReporte = 'ventas-por-producto';
    }
    const csv = '\ufeff' + [encabezados,...filas].map(f => f.map(v => `"${String(v ?? '').replaceAll('"','""')}"`).join(';')).join('\r\n');
    const url = URL.createObjectURL(new Blob([csv], {type:'text/csv;charset=utf-8'}));
    const fechas = rvpObtenerFechas();
    const nombrePeriodo = fechas.fecha_inicio ? `${fechas.fecha_inicio}-${fechas.fecha_fin}` : 'todas';
    const a = document.createElement('a'); a.href = url; a.download = `${nombreReporte}-${nombrePeriodo}.csv`; a.click(); URL.revokeObjectURL(url);
}

async function rvpBuscarSugerencias() {
    const texto = rvpTexto.value.trim();
    const solicitud = ++rvpSolicitudSugerencias;
    if (texto.length < 2) {
        rvpSugerencias.classList.remove('abierto');
        rvpSugerencias.innerHTML = '';
        return;
    }
    try {
        const body = new URLSearchParams({accion:'BUSCARPRODUCTOSREPORTEVENTAS', texto});
        const response = await fetch(RVP_API, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body});
        const data = await response.json();
        if (solicitud !== rvpSolicitudSugerencias) return;
        if (!data.success) throw new Error(data.message || 'No se pudieron buscar productos.');
        const productos = data.productos || [];
        rvpSugerencias.innerHTML = productos.length ? productos.map(p => `
            <button type="button" class="rvp-sugerencia" role="option" data-rvp-producto="${rvpEsc(p.descripcion)}">
                <span class="rvp-sugerencia-codigo">${rvpEsc(p.codigo)}</span>
                <span class="rvp-sugerencia-nombre">${rvpEsc(p.descripcion)}</span>
            </button>`).join('') : '<div class="rvp-sugerencias-vacio">No se encontraron productos.</div>';
        rvpSugerencias.classList.add('abierto');
    } catch (error) {
        if (solicitud !== rvpSolicitudSugerencias) return;
        rvpSugerencias.innerHTML = `<div class="rvp-sugerencias-vacio">${rvpEsc(error.message)}</div>`;
        rvpSugerencias.classList.add('abierto');
    }
}

document.querySelectorAll('[data-rvp-periodo]').forEach(button => {
    button.addEventListener('click', () => {
        rvpPeriodoActual = button.dataset.rvpPeriodo;
        document.querySelectorAll('[data-rvp-periodo]').forEach(item => item.classList.toggle('activo', item === button));
        rvpFechas.hidden = rvpPeriodoActual !== 'rango';
        if (rvpPeriodoActual !== 'rango') rvpConsultar();
    });
});
document.querySelectorAll('[data-rvp-tab]').forEach(button => {
    button.addEventListener('click', () => {
        rvpTabActual = button.dataset.rvpTab;
        document.querySelectorAll('[data-rvp-tab]').forEach(tab => {
            const activo = tab === button;
            tab.classList.toggle('activo', activo);
            tab.setAttribute('aria-selected', activo ? 'true' : 'false');
        });
        document.getElementById('rvpPaneProductos').hidden = rvpTabActual !== 'productos';
        document.getElementById('rvpPaneVendedores').hidden = rvpTabActual !== 'vendedores';
        document.getElementById('rvpPaneClientes').hidden = rvpTabActual !== 'clientes';
        document.querySelector('.rvp-producto').hidden = rvpTabActual !== 'productos';
        if (rvpTabActual !== 'productos' && rvpTexto.value) {
            rvpTexto.value = '';
            rvpSugerencias.classList.remove('abierto');
            rvpConsultar();
        }
    });
});
document.getElementById('rvpBuscar').addEventListener('click', rvpConsultar);
document.getElementById('rvpCsv').addEventListener('click', rvpExportar);
rvpTexto.addEventListener('input', () => {
    clearTimeout(rvpTimerSugerencias);
    rvpTimerSugerencias = setTimeout(rvpBuscarSugerencias, 220);
});
rvpTexto.addEventListener('keydown', e => { if (e.key === 'Enter') { rvpSugerencias.classList.remove('abierto'); rvpConsultar(); } });
rvpSugerencias.addEventListener('click', e => {
    const opcion = e.target.closest('[data-rvp-producto]');
    if (!opcion) return;
    rvpTexto.value = opcion.dataset.rvpProducto;
    rvpSugerencias.classList.remove('abierto');
    rvpConsultar();
});
document.addEventListener('click', e => {
    if (!e.target.closest('.rvp-autocomplete')) rvpSugerencias.classList.remove('abierto');
});
const hoy = new Date();
rvpDesde.value = rvpFechaLocal(new Date(hoy.getFullYear(), hoy.getMonth(), 1));
rvpHasta.value = rvpFechaLocal(hoy);
rvpConsultar();
</script>

<?php include 'footer.php'; ?>
