<?php
$pageTitle    = 'Pase de Producción a Ensamblaje';
$pageSubtitle = 'Rendimiento de kg que pasan de producción a ensamblaje, con detalle de materiales';
$activePage   = 'reporte_pase_ensamblaje';

include("header.php");
?>

<style>
    .ppe-hero {
        background: linear-gradient(135deg, var(--pc-navy, #1f2937), #3016d5);
        color: #fff;
        border-radius: 14px;
        padding: 22px 24px;
        margin-bottom: 18px;
    }
    .ppe-hero h2 { font-size: 20px; font-weight: 700; margin: 0 0 2px; color: #fff; }
    .ppe-hero p { margin: 0 0 16px; font-size: 13px; opacity: .75; }
    .ppe-filtros { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
    .ppe-filtros label { display: block; font-size: 12px; opacity: .8; margin-bottom: 4px; }
    .ppe-filtros select, .ppe-filtros input { min-width: 150px; }

    .ppe-stats { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
    .ppe-stat-card {
        flex: 1 1 170px; background: #fff; border: 1px solid #e5e7eb;
        border-top: 3px solid var(--pc-red, #c0392b); border-radius: 10px;
        padding: 14px 16px; text-align: center;
    }
    .ppe-stat-card .valor { font-size: 22px; font-weight: 700; color: var(--pc-navy, #1f2937); }
    .ppe-stat-card .label {
        font-size: 11px; text-transform: uppercase; letter-spacing: .03em;
        color: #6b7280; margin-top: 2px;
    }

    .ppe-vacio { text-align: center; padding: 40px 20px; color: #6b7280; }

    .ppe-badge-rend { padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
    .ppe-rend-alto  { background: #d1fae5; color: #065f46; }
    .ppe-rend-medio { background: #fef3c7; color: #92400e; }
    .ppe-rend-bajo  { background: #fee2e2; color: #991b1b; }

    .ppe-badge-estado { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
    .ppe-badge-pendiente { background: #fef3c7; color: #92400e; }
    .ppe-badge-enviado   { background: #d1fae5; color: #065f46; }

    .ppe-btn-mat {
        background: #eef2ff; color: #3730a3; border: 1px solid #c7d2fe;
        border-radius: 8px; padding: 4px 10px; font-size: 12px; cursor: pointer;
    }
    .ppe-btn-mat:hover { background: #e0e7ff; }

    .ppe-mat-item {
        display: flex; justify-content: space-between; gap: 10px;
        padding: 8px 0; border-bottom: 1px solid #f0f1f3; font-size: 13px; text-align: left;
    }
    .ppe-mat-item:last-child { border-bottom: none; }
    .ppe-mat-item .nombre { font-weight: 600; }
    .ppe-mat-item .cant { color: #374151; white-space: nowrap; }
    .ppe-mat-coment { font-size: 11px; color: #6b7280; display: block; }
</style>

<div class="ppe-hero">
    <h2><i class="fa-solid fa-right-left"></i> Pase de Producción a Ensamblaje</h2>
    <p>Cuánto entra de producción, cuánto sale ensamblado y qué materiales se usaron en cada corrida.</p>

    <div class="ppe-filtros">
        <div>
            <label>Periodo</label>
            <select id="ppe_modo" class="form-select">
                <option value="dia">Día</option>
                <option value="semana">Semana</option>
                <option value="mes" selected>Mes</option>
                <option value="rango">Rango personalizado</option>
            </select>
        </div>
        <div id="ppe_wrap_fecha">
            <label>Fecha</label>
            <input type="date" id="ppe_fecha" class="form-control">
        </div>
        <div id="ppe_wrap_desde" style="display:none;">
            <label>Desde</label>
            <input type="date" id="ppe_fecha_desde" class="form-control">
        </div>
        <div id="ppe_wrap_hasta" style="display:none;">
            <label>Hasta</label>
            <input type="date" id="ppe_fecha_hasta" class="form-control">
        </div>
        <div>
            <label>Molde</label>
            <select id="ppe_molde" class="form-select">
                <option value="">Todos</option>
            </select>
        </div>
        <div>
            <label>Categoría material</label>
            <select id="ppe_categoria" class="form-select">
                <option value="">Todas</option>
            </select>
        </div>
    </div>
</div>

<div id="ppe_dashboard">
    <div class="pc-card"><div class="ppe-vacio"><i class="fa-solid fa-spinner fa-spin"></i> Cargando panel...</div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const CONTROLADOR_PPE = 'controllers/clssReportePaseEnsamblaje.php';

let filasActuales = []; // guarda las filas del último fetch, para abrir el modal de materiales sin re-consultar

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('ppe_fecha').value = hoyISO();

    Promise.all([cargarMoldes(), cargarCategorias()])
        .then(cargarDashboard)
        .catch(err => {
            console.error(err);
            Swal.fire('Error', 'No se pudo cargar el reporte de pase a ensamblaje.', 'error');
        });

    document.getElementById('ppe_modo').addEventListener('change', () => {
        actualizarVisibilidadFechas();
        cargarDashboard();
    });
    actualizarVisibilidadFechas();

    ['ppe_fecha', 'ppe_fecha_desde', 'ppe_fecha_hasta', 'ppe_molde', 'ppe_categoria']
        .forEach(id => document.getElementById(id).addEventListener('change', cargarDashboard));
});

function hoyISO() { return new Date().toISOString().slice(0, 10); }

function actualizarVisibilidadFechas() {
    const esRango = document.getElementById('ppe_modo').value === 'rango';
    document.getElementById('ppe_wrap_fecha').style.display = esRango ? 'none' : 'block';
    document.getElementById('ppe_wrap_desde').style.display = esRango ? 'block' : 'none';
    document.getElementById('ppe_wrap_hasta').style.display = esRango ? 'block' : 'none';
    if (esRango) {
        const desde = document.getElementById('ppe_fecha_desde');
        const hasta = document.getElementById('ppe_fecha_hasta');
        if (!desde.value) desde.value = hoyISO();
        if (!hasta.value) hasta.value = hoyISO();
    }
}

async function llamar(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_PPE, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
    const texto = await resp.text();
    try { return JSON.parse(texto); }
    catch (e) { console.error(`Respuesta no JSON (accion=${accion}):`, texto); throw e; }
}

async function cargarMoldes() {
    const json = await llamar('BUSCARMOLDESREPORTEPASE');
    const select = document.getElementById('ppe_molde');
    if (!json.success) return;
    (json.moldes || []).forEach(m => {
        const opt = document.createElement('option');
        opt.value = m.id; opt.textContent = m.nombre;
        select.appendChild(opt);
    });
}

async function cargarCategorias() {
    const json = await llamar('BUSCARCATEGORIASMATERIALREPORTEPASE');
    const select = document.getElementById('ppe_categoria');
    if (!json.success) return;
    (json.categorias || []).forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id; opt.textContent = c.nombre;
        select.appendChild(opt);
    });
}

function filtrosPeriodo() {
    return {
        modo: document.getElementById('ppe_modo').value,
        fecha: document.getElementById('ppe_fecha').value || hoyISO(),
        fecha_desde: document.getElementById('ppe_fecha_desde').value,
        fecha_hasta: document.getElementById('ppe_fecha_hasta').value,
    };
}

async function cargarDashboard() {
    const params = {
        ...filtrosPeriodo(),
        molde_id: document.getElementById('ppe_molde').value,
        categoria_material_id: document.getElementById('ppe_categoria').value,
    };
    const json = await llamar('REPORTEPASEENSAMBLAJEDASHBOARD', params);
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }
    filasActuales = json.filas || [];
    pintarDashboard(json);
}

function pintarDashboard(json) {
    const { periodo, resumen, filas } = json;

    const rendClase = pct => pct === null || pct === undefined ? ''
        : pct >= 90 ? 'ppe-rend-alto' : pct >= 70 ? 'ppe-rend-medio' : 'ppe-rend-bajo';

    const filasHtml = (filas || []).map((f, i) => `
        <tr>
            <td data-label="Fecha">${formatearFechaCorta(f.fecha_produccion)}</td>
            <td data-label="Molde">${f.molde_nombre ?? '-'}</td>
            <td data-label="Producto">${f.producto_codigo ? `${f.producto_codigo} — ${f.producto_descripcion ?? ''}` : (f.producto_descripcion ?? '-')}</td>
            <td data-label="Kg entrada">${formatearKg(f.cantidad_utilizada_kg)}</td>
            <td data-label="Kg salida">${formatearKg(f.cantidad_salida_ensamblaje_kg)}</td>
            <td data-label="Diferencia">${formatearKg(f.diferencia_kg)}</td>
            <td data-label="Rendimiento">${f.rendimiento_porcentaje !== null && f.rendimiento_porcentaje !== undefined
                ? `<span class="ppe-badge-rend ${rendClase(f.rendimiento_porcentaje)}">${f.rendimiento_porcentaje}%</span>`
                : '-'}</td>
            <td data-label="Operario">${f.operario_display ?? '-'}</td>
            <td data-label="Sucursal">${f.sucursal_ensamblaje ?? '-'}</td>
            <td data-label="Estado">${f.enviado_empaquetado
                ? '<span class="ppe-badge-estado ppe-badge-enviado">Enviado</span>'
                : '<span class="ppe-badge-estado ppe-badge-pendiente">Pendiente</span>'}</td>
            <td data-label="Materiales"><button type="button" class="ppe-btn-mat" onclick="verMateriales(${i})"><i class="fa-solid fa-flask"></i> Ver</button></td>
        </tr>
    `).join('');

    document.getElementById('ppe_dashboard').innerHTML = `
        <div class="pc-card">
            <div class="pc-card-header"><h2><i class="fa-solid fa-chart-line"></i> ${periodo.etiqueta}</h2></div>
            <div class="ppe-stats">
                <div class="ppe-stat-card"><div class="valor">${resumen.total_ensamblajes ?? 0}</div><div class="label">Ensamblajes</div></div>
                <div class="ppe-stat-card"><div class="valor">${formatearKg(resumen.total_producido_kg)}</div><div class="label">Producido (kg)</div></div>
                <div class="ppe-stat-card"><div class="valor">${formatearKg(resumen.total_ensamblado_kg)}</div><div class="label">Ensamblado (kg)</div></div>
                <div class="ppe-stat-card"><div class="valor">${formatearKg(resumen.total_diferencia_kg)}</div><div class="label">Diferencia / merma (kg)</div></div>
                <div class="ppe-stat-card"><div class="valor">${resumen.rendimiento_promedio !== null && resumen.rendimiento_promedio !== undefined ? resumen.rendimiento_promedio + '%' : '-'}</div><div class="label">Rendimiento promedio</div></div>
                <div class="ppe-stat-card"><div class="valor">${resumen.pendientes ?? 0}</div><div class="label">Pendientes</div></div>
                <div class="ppe-stat-card"><div class="valor">${resumen.enviados_empaquetado ?? 0}</div><div class="label">A empaquetado</div></div>
            </div>
        </div>

        <div class="pc-card">
            <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h2><i class="fa-solid fa-table"></i> Detalle</h2>
                <span class="text-muted">${(filas || []).length} registro${(filas || []).length === 1 ? '' : 's'}</span>
            </div>
            <div class="pc-table-wrap pc-table-responsive-cards">
                <table class="pc-table">
                    <thead><tr>
                        <th>Fecha</th><th>Molde</th><th>Producto</th>
                        <th>Kg entrada</th><th>Kg salida</th><th>Diferencia</th><th>Rendimiento</th>
                        <th>Operario</th><th>Sucursal</th><th>Estado</th><th>Materiales</th>
                    </tr></thead>
                    <tbody>${filasHtml || '<tr><td colspan="11" class="ppe-vacio">Sin registros en este periodo.</td></tr>'}</tbody>
                </table>
            </div>
        </div>
    `;
}

function verMateriales(indice) {
    const fila = filasActuales[indice];
    if (!fila) return;

    const materiales = fila.materiales_utilizados || [];
    const contenido = materiales.length === 0
        ? '<div class="ppe-vacio">Esta producción no tiene materiales registrados.</div>'
        : materiales.map(m => `
            <div class="ppe-mat-item">
                <div>
                    <span class="nombre">${m.material ?? 'Material #' + m.material_id}</span>
                    ${m.comentario ? `<span class="ppe-mat-coment">${m.comentario}</span>` : ''}
                </div>
                <div class="cant">${formatearCantidadUnidad(m.cantidad, m.unidad_medida)}</div>
            </div>
        `).join('');

    Swal.fire({
        title: `Materiales — Producción #${fila.produccion_id}`,
        html: `<div style="text-align:left; max-height:340px; overflow-y:auto;">${contenido}</div>`,
        confirmButtonText: 'Cerrar',
        width: 480,
    });
}

function formatearKg(valor) {
    const n = parseFloat(valor);
    if (isNaN(n)) return '-';
    return n.toLocaleString('es-PE', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' kg';
}

function formatearCantidadUnidad(valor, unidad) {
    const n = parseFloat(valor);
    if (isNaN(n)) return '-';
    return n.toLocaleString('es-PE', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' ' + (unidad ?? '');
}

function formatearFechaCorta(fecha) {
    if (!fecha) return '-';
    const d = new Date(fecha.length <= 10 ? fecha + 'T00:00:00' : fecha.replace(' ', 'T'));
    if (isNaN(d)) return fecha;
    const meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    return `${d.getDate()} ${meses[d.getMonth()]} ${d.getFullYear()}`;
}
</script>

<?php require __DIR__ . '/footer.php'; ?>