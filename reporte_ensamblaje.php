<?php
$pageTitle    = 'Reporte de Ensamblaje';
$pageSubtitle = 'Panel general y detalle por operario';
$activePage   = 'reporte_ensamblaje';

include("header.php");
?>

<style>
    .ens-hero {
        background: linear-gradient(135deg, var(--pc-navy, #1f2937), #3016d5);
        color: #fff;
        border-radius: 14px;
        padding: 22px 24px;
        margin-bottom: 18px;
    }
    .ens-hero h2 { font-size: 20px; font-weight: 700; margin: 0 0 2px; color: #fff; }
    .ens-hero p { margin: 0 0 16px; font-size: 13px; opacity: .75; }
    .ens-filtros {
        display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;
    }
    .ens-filtros label { display: block; font-size: 12px; opacity: .8; margin-bottom: 4px; }
    .ens-filtros select, .ens-filtros input { min-width: 150px; }

    .ens-stats { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
    .ens-stat-card {
        flex: 1 1 170px; background: #fff; border: 1px solid #e5e7eb;
        border-top: 3px solid var(--pc-red, #c0392b); border-radius: 10px;
        padding: 14px 16px; text-align: center;
    }
    .ens-stat-card .valor { font-size: 22px; font-weight: 700; color: var(--pc-navy, #1f2937); }
    .ens-stat-card .label {
        font-size: 11px; text-transform: uppercase; letter-spacing: .03em;
        color: #6b7280; margin-top: 2px;
    }

    .ens-grid-2 { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
    .ens-panel { flex: 1 1 380px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; }
    .ens-panel h4 { font-size: 14px; margin: 0 0 12px; display: flex; align-items: center; gap: 8px; }
    .ens-dona-wrap { max-width: 260px; margin: 0 auto; }

    .ens-rank-item { margin-bottom: 12px; }
    .ens-rank-item:last-child { margin-bottom: 0; }
    .ens-rank-item .fila { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px; }
    .ens-rank-item .fila b { font-weight: 700; }
    .ens-barra-wrap { background: #eef0f3; border-radius: 6px; overflow: hidden; height: 12px; }
    .ens-barra { height: 100%; background: var(--pc-red, #c0392b); border-radius: 6px; }

    .ens-buscar-wrap { position: relative; margin-top: 22px; }
    .ens-buscar-wrap label { color: #fff; font-size: 13px; display: block; margin-bottom: 6px; }
    .ens-buscar-wrap input {
        width: 100%; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.2);
        color: #fff; border-radius: 10px; padding: 10px 14px;
    }
    .ens-buscar-wrap input::placeholder { color: rgba(255,255,255,.55); }
    .ens-buscar-dropdown {
        position: absolute; z-index: 20; top: calc(100% + 4px); left: 0; right: 0;
        max-height: 260px; overflow-y: auto; background: #fff; border-radius: 10px;
        box-shadow: 0 10px 30px rgba(0,0,0,.25); display: none;
    }
    .ens-buscar-dropdown.activo { display: block; }
    .ens-buscar-item { padding: 10px 14px; cursor: pointer; color: #1f2937; border-bottom: 1px solid #f0f1f3; }
    .ens-buscar-item:last-child { border-bottom: none; }
    .ens-buscar-item:hover { background: #f5f6f8; }
    .ens-buscar-item .nombre { font-weight: 600; font-size: 14px; }
    .ens-buscar-item .cargo { font-size: 12px; color: #6b7280; }
    .ens-chip {
        display: inline-flex; align-items: center; gap: 10px; margin-top: 10px;
        background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.3);
        border-radius: 12px; padding: 8px 14px;
    }
    .ens-chip .avatar {
        width: 34px; height: 34px; border-radius: 50%; background: #3b82f6;
        display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;
    }
    .ens-chip .nombre { font-weight: 700; font-size: 13px; }
    .ens-chip .meta { font-size: 11px; opacity: .75; }
    .ens-chip .quitar { cursor: pointer; opacity: .7; margin-left: 6px; }
    .ens-chip .quitar:hover { opacity: 1; }

    .ens-vacio { text-align: center; padding: 40px 20px; color: #6b7280; }
    .ens-badge-estado { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
    .ens-badge-pendiente { background: #fef3c7; color: #92400e; }
    .ens-badge-enviado { background: #d1fae5; color: #065f46; }
</style>

<div class="ens-hero">
    <h2><i class="fa-solid fa-layer-group"></i> Panel de Ensamblaje</h2>
    <p>Vista general del periodo. Busca un operario abajo para ver su detalle.</p>

    <div class="ens-filtros">
        <div>
            <label>Periodo</label>
            <select id="ens_modo" class="form-select">
                <option value="dia">Día</option>
                <option value="semana">Semana</option>
                <option value="mes" selected>Mes</option>
                <option value="rango">Rango personalizado</option>
            </select>
        </div>
        <div id="ens_wrap_fecha">
            <label>Fecha</label>
            <input type="date" id="ens_fecha" class="form-control">
        </div>
        <div id="ens_wrap_desde" style="display:none;">
            <label>Desde</label>
            <input type="date" id="ens_fecha_desde" class="form-control">
        </div>
        <div id="ens_wrap_hasta" style="display:none;">
            <label>Hasta</label>
            <input type="date" id="ens_fecha_hasta" class="form-control">
        </div>
        <div>
            <label>Categoría material</label>
            <select id="ens_categoria" class="form-select">
                <option value="">Todas</option>
            </select>
        </div>
        <div>
            <label>Origen (proveniente)</label>
            <select id="ens_proveniente" class="form-select">
                <option value="">Todos</option>
            </select>
        </div>
    </div>

    <div class="ens-buscar-wrap">
        <label>Buscar operario para ver su detalle</label>
        <input type="text" id="ens_buscar_operario" autocomplete="off" placeholder="Nombre del operario...">
        <div class="ens-buscar-dropdown" id="ens_buscar_dropdown"></div>
    </div>
    <div id="ens_chip_wrap"></div>
</div>

<div id="ens_dashboard">
    <div class="pc-card"><div class="ens-vacio"><i class="fa-solid fa-spinner fa-spin"></i> Cargando panel...</div></div>
</div>

<div id="ens_detalle_operario"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
const CONTROLADOR_ENS = 'controllers/clssReporteEnsamblaje.php';
const PALETA = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#84cc16', '#ec4899'];

let listaOperarios = [];
let operarioSeleccionado = null;

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('ens_fecha').value = hoyISO();

    Promise.all([cargarOperarios(), cargarCategorias(), cargarProvenientes()])
        .then(cargarDashboard)
        .catch(err => {
            console.error(err);
            Swal.fire('Error', 'No se pudo cargar el reporte de ensamblaje.', 'error');
        });

    document.getElementById('ens_modo').addEventListener('change', () => {
        actualizarVisibilidadFechas();
        recargarTodo();
    });
    actualizarVisibilidadFechas();

    ['ens_fecha', 'ens_fecha_desde', 'ens_fecha_hasta', 'ens_categoria', 'ens_proveniente']
        .forEach(id => document.getElementById(id).addEventListener('change', recargarTodo));

    const input = document.getElementById('ens_buscar_operario');
    input.addEventListener('input', () => renderDropdownOperarios(input.value));
    input.addEventListener('focus', () => renderDropdownOperarios(input.value));
    document.addEventListener('click', (e) => {
        const wrap = document.querySelector('.ens-buscar-wrap');
        if (!wrap.contains(e.target)) document.getElementById('ens_buscar_dropdown').classList.remove('activo');
    });
});

function hoyISO() { return new Date().toISOString().slice(0, 10); }



function recargarTodo() {
    if (operarioSeleccionado) {
        generarDetalleOperario();
    } else {
        cargarDashboard();
    }
}


function actualizarVisibilidadFechas() {
    const esRango = document.getElementById('ens_modo').value === 'rango';
    document.getElementById('ens_wrap_fecha').style.display = esRango ? 'none' : 'block';
    document.getElementById('ens_wrap_desde').style.display = esRango ? 'block' : 'none';
    document.getElementById('ens_wrap_hasta').style.display = esRango ? 'block' : 'none';
    if (esRango) {
        const desde = document.getElementById('ens_fecha_desde');
        const hasta = document.getElementById('ens_fecha_hasta');
        if (!desde.value) desde.value = hoyISO();
        if (!hasta.value) hasta.value = hoyISO();
    }
}

async function llamar(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_ENS, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
    const texto = await resp.text();
    try { return JSON.parse(texto); }
    catch (e) { console.error(`Respuesta no JSON (accion=${accion}):`, texto); throw e; }
}

async function cargarOperarios() {
    const json = await llamar('BUSCAROPERARIOSREPORTEENSAMBLAJE');
    if (json.success) listaOperarios = json.operarios || [];
}

async function cargarCategorias() {
    const json = await llamar('BUSCARCATEGORIASMATERIALREPORTE');
    const select = document.getElementById('ens_categoria');
    if (!json.success) return;
    (json.categorias || []).forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id; opt.textContent = c.nombre;
        select.appendChild(opt);
    });
}

// No hay endpoint dedicado para valores distintos de "proveniente";
// se llenan bajo demanda a partir de lo que devuelve el dashboard.
async function cargarProvenientes() { /* se completa en pintarDashboard */ }

function filtrosPeriodo() {
    return {
        modo: document.getElementById('ens_modo').value,
        fecha: document.getElementById('ens_fecha').value || hoyISO(),
        fecha_desde: document.getElementById('ens_fecha_desde').value,
        fecha_hasta: document.getElementById('ens_fecha_hasta').value,
    };
}

async function cargarDashboard() {
    const params = {
        ...filtrosPeriodo(),
        categoria_material_id: document.getElementById('ens_categoria').value,
        proveniente: document.getElementById('ens_proveniente').value,
    };
    const json = await llamar('REPORTEENSAMBLAJEDASHBOARD', params);
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }
    pintarDashboard(json);
}

function pintarDashboard(json) {
    const { periodo, resumen, top_operarios, top_productos, por_categoria, por_proveniente } = json;

    // completa el select de "proveniente" con lo observado, sin duplicar valores ya cargados
    const select = document.getElementById('ens_proveniente');
    const actuales = new Set(Array.from(select.options).map(o => o.value));
    (por_proveniente || []).forEach(p => {
        if (p.proveniente && p.proveniente !== 'Sin especificar' && !actuales.has(p.proveniente)) {
            const opt = document.createElement('option');
            opt.value = p.proveniente; opt.textContent = p.proveniente;
            select.appendChild(opt);
        }
    });

    const cardsUnidad = (resumen.por_unidad || []).map(r => `
        <div class="ens-stat-card">
            <div class="valor">${formatearCantidad(r.cantidad_total, r.unidad)}</div>
            <div class="label">Producido (${r.unidad}) · ${r.ensamblajes} registro${r.ensamblajes == 1 ? '' : 's'}</div>
        </div>
    `).join('');

    document.getElementById('ens_dashboard').innerHTML = `
        <div class="pc-card">
            <div class="pc-card-header"><h2><i class="fa-solid fa-chart-line"></i> ${periodo.etiqueta}</h2></div>
            <div class="ens-stats">
                <div class="ens-stat-card"><div class="valor">${resumen.total_ensamblajes ?? 0}</div><div class="label">Ensamblajes</div></div>
                <div class="ens-stat-card"><div class="valor">${resumen.pendientes ?? 0}</div><div class="label">Pendientes</div></div>
                <div class="ens-stat-card"><div class="valor">${resumen.enviados_empaquetado ?? 0}</div><div class="label">A empaquetado</div></div>
                ${cardsUnidad}
            </div>
        </div>

        <div class="ens-grid-2">
            <div class="ens-panel">
                <h4><i class="fa-solid fa-ranking-star" style="color:#c9a227"></i> Top operarios</h4>
                <div id="ens_rank_operarios"></div>
            </div>
            <div class="ens-panel">
                <h4><i class="fa-solid fa-box" style="color:#3b82f6"></i> Top productos</h4>
                <div id="ens_rank_productos"></div>
            </div>
        </div>

        <div id="ens_dona_categoria_wrap"></div>
        <div id="ens_dona_proveniente_wrap"></div>
    `;

    // Cada operario/producto puede aparecer más de una vez si trabaja en más
    // de una unidad (ej. kg y docena) — se muestran como filas separadas,
    // nunca se suman cantidades de distinta unidad entre sí.
    pintarRanking('ens_rank_operarios', (top_operarios || []).map(o => ({
        etiqueta: o.operario ?? `Operario #${o.operario_id}`, valor: o.cantidad_total, unidad: o.unidad,
    })));
    pintarRanking('ens_rank_productos', (top_productos || []).map(p => ({
        etiqueta: p.descripcion ? `${p.codigo} — ${p.descripcion}` : p.codigo, valor: p.cantidad_total, unidad: p.unidad,
    })));

    pintarDonasPorUnidad('ens_dona_categoria_wrap', por_categoria || [], 'categoria');
    pintarDonasPorUnidad('ens_dona_proveniente_wrap', por_proveniente || [], 'proveniente');
}

// Construye el HTML de una lista con barras de progreso. filas: [{etiqueta, valor, unidad}]
// El % de cada barra se calcula solo contra el máximo DENTRO de la misma unidad,
// para no comparar kg con docenas ni unidades sueltas.
function construirListaBarras(filas) {
    if (filas.length === 0) return '<div class="ens-vacio">Sin datos en este periodo.</div>';

    const maxPorUnidad = {};
    filas.forEach(f => {
        const u = f.unidad || 'kg';
        maxPorUnidad[u] = Math.max(maxPorUnidad[u] || 0.0001, parseFloat(f.valor) || 0);
    });

    return filas.map((f, i) => {
        const v = parseFloat(f.valor) || 0;
        const u = f.unidad || 'kg';
        const pct = Math.round((v / maxPorUnidad[u]) * 100);
        return `
        <div class="ens-rank-item">
            <div class="fila"><span>${i + 1}. ${f.etiqueta}</span><b>${formatearCantidad(v, u)}</b></div>
            <div class="ens-barra-wrap"><div class="ens-barra" style="width:${pct}%"></div></div>
        </div>`;
    }).join('');
}

function pintarRanking(contenedorId, filas) {
    document.getElementById(contenedorId).innerHTML = construirListaBarras(filas);
}

function formatearCantidad(valor, unidad) {
    const n = parseFloat(valor) || 0;
    const u = (unidad || 'kg').toString().trim().toLowerCase();
    const esEntero = ['und', 'unidad', 'unidades', 'doc', 'docena', 'docenas'].includes(u);
    return n.toLocaleString('es-PE', { minimumFractionDigits: 0, maximumFractionDigits: esEntero ? 0 : 2 }) + ' ' + u;
}

// Arma un panel de lista-con-barras POR CADA UNIDAD presente en las filas
// (nunca mezcla cantidades de distinta unidad en un mismo panel).
// filas: [{<labelKey>, unidad, cantidad_total}]
function pintarDonasPorUnidad(wrapId, filas, labelKey) {
    const wrap = document.getElementById(wrapId);
    const unidades = [...new Set(filas.map(f => f.unidad))];
    if (unidades.length === 0) { wrap.innerHTML = ''; return; }

    const titulo = labelKey === 'categoria' ? 'Por categoría de material' : 'Por origen';

    wrap.innerHTML = `<div class="ens-grid-2">` + unidades.map(u => {
        const filasUnidad = filas
            .filter(f => f.unidad === u)
            .map(f => ({ etiqueta: f[labelKey], valor: f.cantidad_total, unidad: u }));
        return `
        <div class="ens-panel">
            <h4><i class="fa-solid fa-list"></i> ${titulo} (${u})</h4>
            ${construirListaBarras(filasUnidad)}
        </div>`;
    }).join('') + `</div>`;
}

// ── Buscador y detalle de operario ──────────────────────────────────────────
function renderDropdownOperarios(texto) {
    const dropdown = document.getElementById('ens_buscar_dropdown');
    const q = (texto || '').trim().toLowerCase();
    const coincidencias = q === '' ? listaOperarios.slice(0, 15)
        : listaOperarios.filter(o => o.nombre_completo.toLowerCase().includes(q)).slice(0, 15);

    dropdown.innerHTML = coincidencias.length === 0
        ? '<div class="ens-buscar-item"><span class="cargo">Sin coincidencias.</span></div>'
        : coincidencias.map(o => `
            <div class="ens-buscar-item" onclick="seleccionarOperario(${o.id})">
                <div class="nombre">${o.nombre_completo}</div>
                <div class="cargo">${o.cargo ?? 'Sin cargo asignado'}</div>
            </div>`).join('');
    dropdown.classList.add('activo');
}

function seleccionarOperario(id) {
    const operario = listaOperarios.find(o => o.id === id);
    if (!operario) return;
    operarioSeleccionado = operario;
    document.getElementById('ens_buscar_operario').value = '';
    document.getElementById('ens_buscar_dropdown').classList.remove('activo');
    pintarChip();
    document.getElementById('ens_dashboard').style.display = 'none';
    generarDetalleOperario();
}

function quitarOperario() {
    operarioSeleccionado = null;
    document.getElementById('ens_chip_wrap').innerHTML = '';
    document.getElementById('ens_detalle_operario').innerHTML = '';
    document.getElementById('ens_dashboard').style.display = '';
    cargarDashboard();
}
function pintarChip() {
    const iniciales = operarioSeleccionado.nombre_completo.split(' ').filter(Boolean).slice(0, 2).map(p => p[0]).join('').toUpperCase();
    document.getElementById('ens_chip_wrap').innerHTML = `
        <div class="ens-chip">
            <div class="avatar">${iniciales}</div>
            <div>
                <div class="nombre">${operarioSeleccionado.nombre_completo}</div>
                <div class="meta">${operarioSeleccionado.cargo ?? 'Sin cargo asignado'} · ID ${operarioSeleccionado.id}</div>
            </div>
            <i class="fa-solid fa-xmark quitar" onclick="quitarOperario()"></i>
        </div>`;
}

async function generarDetalleOperario() {
    if (!operarioSeleccionado) return;
    document.getElementById('ens_detalle_operario').innerHTML = `
        <div class="pc-card"><div class="ens-vacio"><i class="fa-solid fa-spinner fa-spin"></i> Cargando...</div></div>`;

    const params = { operario_id: operarioSeleccionado.id, ...filtrosPeriodo() };
    const json = await llamar('REPORTEENSAMBLAJEOPERARIODETALLE', params);
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }
    pintarDetalleOperario(json);
}

function pintarDetalleOperario(json) {
    const { operario, periodo, resumen, top_productos, detalle } = json;

    const filas = (detalle || []).map(d => `
        <tr>
            <td data-label="Inicio">${formatearFechaHora(d.inicio)}</td>
            <td data-label="Fin">${formatearFechaHora(d.fin)}</td>
            <td data-label="Producto">${d.producto_codigo ?? ''} ${d.producto ? '— ' + d.producto : ''}</td>
            <td data-label="Categoría">${d.categoria_material ?? '-'}</td>
            <td data-label="Cantidad">${formatearCantidad(d.cantidad_peso_kg, d.unidad_salida)}</td>
            <td data-label="Unidad salida">${d.unidad_salida ?? '-'}</td>
            <td data-label="Origen">${d.proveniente ?? '-'}</td>
            <td data-label="Estado">${d.enviado_empaquetado
                ? '<span class="ens-badge-estado ens-badge-enviado">Enviado</span>'
                : '<span class="ens-badge-estado ens-badge-pendiente">Pendiente</span>'}</td>
        </tr>
    `).join('');

    const cardsUnidadOperario = (resumen.por_unidad || []).map(r => `
        <div class="ens-stat-card">
            <div class="valor">${formatearCantidad(r.cantidad_total, r.unidad)}</div>
            <div class="label">Ensamblado (${r.unidad}) · ${r.ensamblajes} registro${r.ensamblajes == 1 ? '' : 's'}</div>
        </div>
    `).join('');

    document.getElementById('ens_detalle_operario').innerHTML = `
    <div class="pc-card">
        <div class="pc-card-header"><h2>${operario.nombre_completo} <small class="text-muted">· ${periodo.etiqueta}</small></h2></div>
        <div class="ens-stats">
            <div class="ens-stat-card"><div class="valor">${resumen.total_ensamblajes ?? 0}</div><div class="label">Ensamblajes</div></div>
            <div class="ens-stat-card"><div class="valor">${resumen.productos_distintos ?? 0}</div><div class="label">Productos distintos</div></div>
            <div class="ens-stat-card"><div class="valor">${resumen.pendientes ?? 0}</div><div class="label">Pendientes</div></div>
            <div class="ens-stat-card"><div class="valor">${resumen.enviados_empaquetado ?? 0}</div><div class="label">A empaquetado</div></div>
            ${cardsUnidadOperario}
        </div>
    </div>

    <div class="pc-card">
        <div class="pc-card-header"><h2><i class="fa-solid fa-trophy" style="color:#c9a227"></i> Top productos trabajados</h2></div>
        <div id="ens_top_prod_operario"></div>
    </div>

    <div class="pc-card">
        <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2><i class="fa-solid fa-table"></i> Detalle completo</h2>
            <span class="text-muted">${(detalle || []).length} registro${(detalle || []).length === 1 ? '' : 's'}</span>
        </div>
        <div class="pc-table-wrap pc-table-responsive-cards">
            <table class="pc-table">
                <thead><tr>
                    <th>Inicio</th><th>Fin</th><th>Producto</th><th>Categoría</th>
                    <th>Kg</th><th>Unidad salida</th><th>Origen</th><th>Estado</th>
                </tr></thead>
                <tbody>${filas || '<tr><td colspan="8" class="ens-vacio">Sin ensamblajes en este periodo.</td></tr>'}</tbody>
            </table>
        </div>
    </div>
    `;

    pintarRanking('ens_top_prod_operario', (top_productos || []).map(p => ({
        etiqueta: p.descripcion ? `${p.codigo} — ${p.descripcion}` : p.codigo, valor: p.cantidad_total, unidad: p.unidad,
    })));
}

function formatearKg(valor) {
    const n = parseFloat(valor) || 0;
    return n.toLocaleString('es-PE', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' kg';
}

function formatearFechaHora(iso) {
    if (!iso) return '-';
    const d = new Date(iso.replace(' ', 'T'));
    if (isNaN(d)) return iso;
    const meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    const hh = String(d.getHours()).padStart(2, '0');
    const mm = String(d.getMinutes()).padStart(2, '0');
    return `${d.getDate()} ${meses[d.getMonth()]} ${hh}:${mm}`;
}
</script>

<?php require __DIR__ . '/footer.php'; ?>