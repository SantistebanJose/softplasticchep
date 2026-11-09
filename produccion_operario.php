<?php
$pageTitle    = 'Reporte de Producción';
$pageSubtitle = 'Busca un operario y revisa todo lo referente a su producción';
$activePage   = 'reporte_produccion';

include("header.php");
?>

<style>
    .rep-buscador {
        background: linear-gradient(135deg, var(--pc-navy, #1f2937), #3016d5);
        color: #fff;
        border-radius: 14px;
        padding: 16px 20px;
        margin-bottom: 12px;
    }
    .rep-buscador h2 {
        font-size: 18px;
        font-weight: 700;
        margin: 0 0 1px;
        color: #fff;
    }
    .rep-buscador p {
        margin: 0 0 10px;
        font-size: 12px;
        opacity: .75;
    }
    .rep-filtros-top {
        display: grid;
        grid-template-columns: minmax(130px, .8fr) minmax(190px, 1.2fr) minmax(175px, 1fr) minmax(120px, .7fr) minmax(130px, .8fr) auto;
        gap: 10px;
        align-items: flex-end;
        margin-bottom: 10px;
    }
    .rep-filtros-top label {
        display: block;
        font-size: 12px;
        opacity: .8;
        margin-bottom: 4px;
    }
    .rep-filtros-top select,
    .rep-filtros-top input {
        width: 100%;
        min-width: 0;
        min-height: 40px;
    }
    .rep-fecha-ayuda {
        display: block;
        color: rgba(255,255,255,.78);
        font-size: 11px;
        margin-top: 3px;
        max-width: none;
        line-height: 1.15;
        white-space: nowrap;
    }
    .rep-badge-total {
        background: rgba(255,255,255,.12);
        border: 1px solid rgba(255,255,255,.25);
        color: #fff;
        border-radius: 20px;
        padding: 6px 11px;
        font-size: 13px;
        white-space: nowrap;
        height: 38px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .rep-buscar-wrap {
        position: relative;
        margin-bottom: 0;
        min-width: 0;
    }
    .rep-buscar-wrap input {
        width: 100%;
        background: rgba(255,255,255,.08);
        border: 1px solid rgba(255,255,255,.2);
        color: #fff;
        border-radius: 10px;
        padding: 9px 12px;
        min-height: 40px;
    }
    .rep-buscador-bottom {
        display: grid;
        grid-template-columns: minmax(260px, 1fr) auto;
        align-items: start;
        gap: 10px;
    }
    .rep-buscar-wrap input::placeholder { color: rgba(255,255,255,.55); }
    .rep-buscar-dropdown {
        position: absolute;
        z-index: 20;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        max-height: 260px;
        overflow-y: auto;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(0,0,0,.25);
        display: none;
    }
    .rep-buscar-dropdown.activo { display: block; }
    .rep-buscar-item {
        padding: 10px 14px;
        cursor: pointer;
        color: #1f2937;
        border-bottom: 1px solid #f0f1f3;
    }
    .rep-buscar-item:last-child { border-bottom: none; }
    .rep-buscar-item:hover { background: #f5f6f8; }
    .rep-buscar-item .nombre { font-weight: 600; font-size: 14px; }
    .rep-buscar-item .cargo { font-size: 12px; color: #6b7280; }
    .rep-chip-operario {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        background: rgba(255,255,255,.12);
        border: 1px solid rgba(255,255,255,.3);
        border-radius: 12px;
        padding: 8px 14px;
    }
    .rep-chip-operario .avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: #3b82f6;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 13px;
        flex-shrink: 0;
    }
    .rep-chip-operario .nombre { font-weight: 700; font-size: 13px; }
    .rep-chip-operario .meta { font-size: 11px; opacity: .75; }
    .rep-chip-operario .quitar {
        cursor: pointer;
        opacity: .7;
        margin-left: 6px;
    }
    .rep-chip-operario .quitar:hover { opacity: 1; }
    .rep-buscador .rep-chip-operario { padding: 5px 10px; gap: 8px; }
    .rep-buscador .rep-chip-operario .avatar { width: 30px; height: 30px; }

    .rep-vacio-estado {
        text-align: center;
        padding: 60px 20px;
        color: #6b7280;
    }
    .rep-vacio-estado i { font-size: 40px; margin-bottom: 12px; opacity: .4; }

    .rep-detalle-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 14px;
    }
    .rep-detalle-header h3 { margin: 0; font-size: 18px; }
    .rep-detalle-header .periodo-label { font-size: 13px; color: #6b7280; }

    .rep-stats {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 10px;
    }
    .rep-stat-card {
        flex: 1 1 145px;
        min-width: 0;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-top: 3px solid var(--pc-red, #c0392b);
        border-radius: 10px;
        padding: 9px 10px;
        text-align: center;
    }
    .rep-stat-card .rep-stat-valor {
        font-size: 20px;
        font-weight: 700;
        color: var(--pc-navy, #1f2937);
    }
    .rep-stat-card .rep-stat-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #6b7280;
        margin-top: 2px;
        line-height: 1.25;
    }

    .rep-donas {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 10px;
    }
    .rep-dona-card {
        flex: 1 1 320px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 12px;
    }
    .rep-dona-card h4 {
        font-size: 14px;
        margin: 0 0 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .rep-dona-canvas-wrap { max-width: 220px; height: 190px; margin: 0 auto; }

    .rep-top5-item { margin-bottom: 10px; }
    .rep-top5-item:last-child { margin-bottom: 0; }
    .rep-top5-item .fila {
        display: flex;
        justify-content: space-between;
        font-size: 13px;
        margin-bottom: 4px;
    }
    .rep-top5-item .fila b { font-weight: 700; }
    .rep-barra-wrap {
        background: #eef0f3;
        border-radius: 6px;
        overflow: hidden;
        height: 12px;
    }
    .rep-barra {
        height: 100%;
        background: var(--pc-red, #c0392b);
        border-radius: 6px;
    }

    .rep-vacio-tabla { text-align: center; color: #6b7280; padding: 18px 0; }
    .rep-pasadas { display: flex; flex-wrap: wrap; align-items: center; gap: 2px 5px; width: 132px; max-width: 132px; min-width: 0; }
    .rep-pasada { display: inline-flex; align-items: center; gap: 2px; padding: 0; border: 0; background: transparent; white-space: nowrap; }
    .rep-pasada-titulo { font-size: 10px; font-weight: 700; letter-spacing: -.02em; }
    .rep-pasada-aporte { color: #6b7280; font-size: 9px; }
    .rep-pasada-fotos { display: inline-flex; align-items: center; gap: 1px; }
    .rep-foto-pasada { display: inline-flex; align-items: center; justify-content: center; min-width: 14px; width: 14px; height: 14px; padding: 0; border: 0; border-radius: 3px; background: transparent; color: #2458b8; font-size: 10px; cursor: pointer; }
    .rep-foto-pasada:hover { background: #eaf0fe; }
    @media (max-width: 1200px) {
        .rep-filtros-top { grid-template-columns: repeat(3, minmax(145px, 1fr)); }
    }
    @media (max-width: 700px) {
        .rep-buscador { padding: 14px; }
        .rep-filtros-top { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
        .rep-buscador-bottom { grid-template-columns: 1fr; }
        .rep-chip-operario { justify-self: start; }
        .rep-fecha-ayuda { white-space: normal; }
        .rep-stat-card { flex-basis: calc(50% - 8px); }
    }
</style>

<div class="rep-buscador">
    <h2><i class="fa-solid fa-headset"></i> Producción por Operario</h2>
    <p>Selecciona el periodo y busca el operario para ver el detalle</p>

    <div class="rep-filtros-top">
        <div>
            <label>Periodo</label>
            <select id="rep_modo" class="form-select">
                <option value="dia" selected>Día</option>
                <option value="semana">Semana</option>
                <option value="mes">Mes</option>
                <option value="rango">Rango personalizado</option>
            </select>
        </div>
        <div id="rep_wrap_fecha">
            <label id="rep_label_fecha" for="rep_fecha">Fecha</label>
            <input type="date" id="rep_fecha" class="form-control">
            <small class="rep-fecha-ayuda" id="rep_fecha_ayuda"></small>
        </div>
        <div id="rep_wrap_rango" style="display:none;">
            <label>Desde</label>
            <input type="date" id="rep_fecha_desde" class="form-control">
        </div>
        <div id="rep_wrap_rango_hasta" style="display:none;">
            <label>Hasta</label>
            <input type="date" id="rep_fecha_hasta" class="form-control">
        </div>
        <div>
            <label>Turno</label>
            <select id="rep_turno" class="form-select">
                <option value="">Todos</option>
                <option value="dia">Día (06:00–11:59)</option>
                <option value="tarde">Tarde (12:00–17:59)</option>
                <option value="noche">Noche (18:00–23:59)</option>
                <option value="madrugada">Madrugada (00:00–05:59)</option>
            </select>
        </div>
        <div>
            <label>Máquina</label>
            <select id="rep_maquina" class="form-select">
                <option value="">Todas</option>
            </select>
        </div>
        <div>
            <label>Sucursal</label>
            <select id="rep_sucursal" class="form-select">
                <option value="">Todas</option>
            </select>
        </div>
        <div class="rep-badge-total">
            <i class="fa-solid fa-users"></i> <span id="rep_total_operarios_badge">0 operarios</span>
        </div>
    </div>

    <div class="rep-buscador-bottom">
        <div class="rep-buscar-wrap">
            <input type="text" id="rep_buscar_operario" autocomplete="off"
                   placeholder="Buscar operario por nombre...">
            <div class="rep-buscar-dropdown" id="rep_buscar_dropdown"></div>
        </div>
        <div id="rep_chip_wrap"></div>
    </div>
</div>

<div id="rep_contenido">
    <div class="pc-card">
        <div class="rep-vacio-estado">
            <i class="fa-solid fa-magnifying-glass-chart"></i>
            <p>Busca un operario arriba para ver el detalle de su producción.</p>
        </div>
    </div>
</div>

<!-- Modal Detalle de avance (observaciones completas) -->
<div class="modal fade" id="modalDetalleAvance" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalle del avance</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="modalDetalleAvanceBody">-</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
const CONTROLADOR_REPORTE = 'controllers/clssReporteProduccion.php';
const modalDetalleAvance = new bootstrap.Modal(document.getElementById('modalDetalleAvance'));

const TURNO_LABEL = { dia: 'Día', tarde: 'Tarde', noche: 'Noche', madrugada: 'Madrugada' };
const TURNO_COLOR = { dia: '#3b82f6', tarde: '#f59e0b', noche: '#1f2937', madrugada: '#8b5cf6' };
const PALETA_MAQUINAS = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#84cc16', '#ec4899'];

let listaOperarios = [];
let operarioSeleccionado = null; // { id, nombre_completo, cargo }
let chartTurno = null;
let chartMaquina = null;
let chartsPorUnidad = {}; // ej: { turno_kg: Chart, maquina_und: Chart }

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('rep_fecha').value = hoyISO();

    Promise.all([
        cargarSelectOperarios(),
        cargarSelectMaquinas(),
        cargarSelectSucursales(),
    ]).catch(err => {
        console.error('Error cargando filtros iniciales:', err);
        Swal.fire('Error', 'No se pudieron cargar los filtros del reporte.', 'error');
    });

    document.getElementById('rep_modo').addEventListener('change', () => {
        actualizarVisibilidadFechas();
        recargarSiHayOperario();
    });
    actualizarVisibilidadFechas();

    ['rep_fecha', 'rep_fecha_desde', 'rep_fecha_hasta', 'rep_turno', 'rep_maquina', 'rep_sucursal']
        .forEach(id => document.getElementById(id).addEventListener('change', () => {
            if (id === 'rep_fecha') actualizarVisibilidadFechas();
            recargarSiHayOperario();
        }));

    const input = document.getElementById('rep_buscar_operario');
    input.addEventListener('input', () => renderDropdownOperarios(input.value));
    input.addEventListener('focus', () => renderDropdownOperarios(input.value));
    document.addEventListener('click', (e) => {
        const wrap = document.querySelector('.rep-buscar-wrap');
        if (!wrap.contains(e.target)) {
            document.getElementById('rep_buscar_dropdown').classList.remove('activo');
        }
    });
});

function hoyISO() {
    const hoy = new Date();
    const pad = n => String(n).padStart(2, '0');
    return `${hoy.getFullYear()}-${pad(hoy.getMonth() + 1)}-${pad(hoy.getDate())}`;
}

function recargarSiHayOperario() {
    if (operarioSeleccionado) generarReporteOperario();
}

function actualizarVisibilidadFechas() {
    const modo = document.getElementById('rep_modo').value;
    const esRango = modo === 'rango';
    const fecha = document.getElementById('rep_fecha');
    const labelFecha = document.getElementById('rep_label_fecha');
    const ayudaFecha = document.getElementById('rep_fecha_ayuda');
    const eraMes = fecha.type === 'month';
    if (modo === 'mes') {
        if (!eraMes) {
            const valorAnterior = fecha.value;
            fecha.type = 'month';
            fecha.value = valorAnterior ? valorAnterior.slice(0, 7) : hoyISO().slice(0, 7);
        }
        labelFecha.textContent = 'Mes de consulta';
        ayudaFecha.textContent = 'Se incluirán todos los días del mes seleccionado.';
    } else {
        if (eraMes) {
            const mesAnterior = fecha.value;
            fecha.type = 'date';
            fecha.value = mesAnterior ? `${mesAnterior}-01` : hoyISO();
        }
        labelFecha.textContent = modo === 'semana' ? 'Elige una fecha de esa semana' : 'Fecha del reporte';
        ayudaFecha.textContent = modo === 'semana'
            ? descripcionSemana(fecha.value)
            : 'Se consultará únicamente este día.';
    }
    document.getElementById('rep_wrap_fecha').style.display = esRango ? 'none' : 'block';
    document.getElementById('rep_wrap_rango').style.display = esRango ? 'block' : 'none';
    document.getElementById('rep_wrap_rango_hasta').style.display = esRango ? 'block' : 'none';

    if (esRango) {
        const desde = document.getElementById('rep_fecha_desde');
        const hasta = document.getElementById('rep_fecha_hasta');
        if (!desde.value) desde.value = hoyISO();
        if (!hasta.value) hasta.value = hoyISO();
    }
}

function descripcionSemana(fechaISO) {
    if (!fechaISO) return 'El reporte incluye semanas de lunes a domingo.';
    const fecha = new Date(`${fechaISO}T12:00:00`);
    const dia = (fecha.getDay() + 6) % 7; // lunes = 0, domingo = 6
    const lunes = new Date(fecha);
    lunes.setDate(fecha.getDate() - dia);
    const domingo = new Date(lunes);
    domingo.setDate(lunes.getDate() + 6);
    const formato = valor => valor.toLocaleDateString('es-PE', {
        day: '2-digit', month: '2-digit', year: 'numeric'
    });
    return `Incluye desde el lunes ${formato(lunes)} hasta el domingo ${formato(domingo)}.`;
}

// ── Llamada genérica ─────────────────────────────────────────────────────────
async function llamarReporte(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_REPORTE, {
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

// ── Carga de selects de filtro ───────────────────────────────────────────────
async function cargarSelectOperarios() {
    const json = await llamarReporte('BUSCAROPERARIOSREPORTE');
    if (!json.success) return;
    listaOperarios = json.operarios || [];
    document.getElementById('rep_total_operarios_badge').textContent =
        `${listaOperarios.length} operario${listaOperarios.length === 1 ? '' : 's'}`;
}

async function cargarSelectMaquinas() {
    const json = await llamarReporte('BUSCARMAQUINASREPORTE');
    const select = document.getElementById('rep_maquina');
    if (!json.success) return;
    (json.maquinas || []).forEach(m => {
        const opt = document.createElement('option');
        opt.value = m.id;
        opt.textContent = m.nombre;
        select.appendChild(opt);
    });
}

async function cargarSelectSucursales() {
    const json = await llamarReporte('BUSCARSUCURSALESREPORTE');
    const select = document.getElementById('rep_sucursal');
    if (!json.success) return;
    (json.sucursales || []).forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.id;
        opt.textContent = s.nombre;
        select.appendChild(opt);
    });
}

// ── Buscador de operario (autocomplete client-side) ─────────────────────────
function renderDropdownOperarios(texto) {
    const dropdown = document.getElementById('rep_buscar_dropdown');
    const q = (texto || '').trim().toLowerCase();

    const coincidencias = q === ''
        ? listaOperarios.slice(0, 15)
        : listaOperarios.filter(o => o.nombre_completo.toLowerCase().includes(q)).slice(0, 15);

    if (coincidencias.length === 0) {
        dropdown.innerHTML = '<div class="rep-buscar-item"><span class="cargo">Sin coincidencias.</span></div>';
    } else {
        dropdown.innerHTML = coincidencias.map(o => `
            <div class="rep-buscar-item" onclick="seleccionarOperario(${o.id})">
                <div class="nombre">${o.nombre_completo}</div>
                <div class="cargo">${o.cargo ?? 'Sin cargo asignado'}</div>
            </div>
        `).join('');
    }
    dropdown.classList.add('activo');
}

function seleccionarOperario(id) {
    const operario = listaOperarios.find(o => o.id === id);
    if (!operario) return;

    operarioSeleccionado = operario;
    document.getElementById('rep_buscar_operario').value = '';
    document.getElementById('rep_buscar_dropdown').classList.remove('activo');
    pintarChipOperario();
    generarReporteOperario();
}

function quitarOperario() {
    operarioSeleccionado = null;
    document.getElementById('rep_chip_wrap').innerHTML = '';
    document.getElementById('rep_contenido').innerHTML = `
        <div class="pc-card">
            <div class="rep-vacio-estado">
                <i class="fa-solid fa-magnifying-glass-chart"></i>
                <p>Busca un operario arriba para ver el detalle de su producción.</p>
            </div>
        </div>`;
}

function pintarChipOperario() {
    const iniciales = operarioSeleccionado.nombre_completo
        .split(' ').filter(Boolean).slice(0, 2).map(p => p[0]).join('').toUpperCase();

    document.getElementById('rep_chip_wrap').innerHTML = `
        <div class="rep-chip-operario">
            <div class="avatar">${iniciales}</div>
            <div>
                <div class="nombre">${operarioSeleccionado.nombre_completo}</div>
                <div class="meta">${operarioSeleccionado.cargo ?? 'Sin cargo asignado'} · ID ${operarioSeleccionado.id}</div>
            </div>
            <i class="fa-solid fa-xmark quitar" onclick="quitarOperario()"></i>
        </div>
    `;
}

// ── Reporte del operario ─────────────────────────────────────────────────────
async function generarReporteOperario() {
    if (!operarioSeleccionado) return;

    const modo = document.getElementById('rep_modo').value;
    const params = {
        operario_id: operarioSeleccionado.id,
        modo,
        fecha: (modo === 'mes' && document.getElementById('rep_fecha').value)
            ? `${document.getElementById('rep_fecha').value}-01`
            : (document.getElementById('rep_fecha').value || hoyISO()),
        fecha_desde: document.getElementById('rep_fecha_desde').value,
        fecha_hasta: document.getElementById('rep_fecha_hasta').value,
        turno: document.getElementById('rep_turno').value,
        maquina_id: document.getElementById('rep_maquina').value,
        sucursal_id: document.getElementById('rep_sucursal').value,
    };

    document.getElementById('rep_contenido').innerHTML = `
        <div class="pc-card"><div class="rep-vacio-estado"><i class="fa-solid fa-spinner fa-spin"></i><p>Cargando producción...</p></div></div>`;

    const json = await llamarReporte('REPORTEOPERARIODETALLE', params);
    if (!json.success) {
        Swal.fire('Error', json.message, 'error');
        return;
    }

    pintarReporteOperario(json);
}

function pintarReporteOperario(json) {
    const { operario, periodo, resumen_general, resumen_por_unidad, por_turno, por_maquina, top_moldes, detalle } = json;

    const cardsUnidad = (resumen_por_unidad || []).map(r => `
        <div class="rep-stat-card">
            <div class="rep-stat-valor">${formatearCantidad(r.total_producido, r.unidad)}</div>
            <div class="rep-stat-label">Producido (${etiquetaUnidad(r.unidad)}) · ${r.avances} avance${r.avances == 1 ? '' : 's'} · prom. ${r.promedio ?? '-'}</div>
            ${parseInt(r.avances_pendientes, 10) > 0 ? `<div class="rep-stat-label text-warning">${parseInt(r.avances_pendientes, 10)} pendiente(s) de desglose</div>` : ''}
        </div>
    `).join('');

    const html = `
    <div class="pc-card">
        <div class="rep-detalle-header">
            <div>
                <h3>${operario.nombre_completo}</h3>
                <div class="periodo-label">${operario.cargo ?? 'Sin cargo asignado'} · ${periodo.etiqueta}</div>
            </div>
        </div>

        <div class="rep-stats">
            <div class="rep-stat-card">
                <div class="rep-stat-valor">${resumen_general.total_avances ?? 0}</div>
                <div class="rep-stat-label">Avances</div>
            </div>
            <div class="rep-stat-card">
                <div class="rep-stat-valor">${resumen_general.moldes_distintos ?? 0}</div>
                <div class="rep-stat-label">Moldes distintos</div>
            </div>
            <div class="rep-stat-card">
                <div class="rep-stat-valor">${formatearKg(resumen_general.total_kg_insertado)}</div>
                <div class="rep-stat-label">Kg insertados</div>
            </div>
            <div class="rep-stat-card">
                <div class="rep-stat-valor">${formatearKg(resumen_general.total_merma)}</div>
                <div class="rep-stat-label">Merma</div>
            </div>
            ${cardsUnidad}
        </div>

    </div>

    <div class="pc-card">
        <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2><i class="fa-solid fa-table"></i> Detalle de producción</h2>
            <span class="text-muted">${detalle.length} avance${detalle.length === 1 ? '' : 's'}</span>
        </div>
        <div class="pc-table-wrap pc-table-responsive-cards">
            <table class="pc-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Turno</th>
                        <th>Molde</th>
                        <th>Producto</th>
                        <th>Máquina</th>
                        <th>Color</th>
                        <th>Kg insertado</th>
                        <th>Cant. producida</th>
                        <th>Pasadas / fotos</th>
                        <th>Obs.</th>
                    </tr>
                </thead>
                <tbody id="rep_tbody_detalle"></tbody>
            </table>
        </div>
    </div>

    <div class="pc-card">
        <div class="pc-card-header"><h2><i class="fa-solid fa-trophy" style="color:#c9a227"></i> Top 5 moldes trabajados</h2></div>
        <div id="rep_top5_wrap"></div>
    </div>

    <div class="pc-card">
        <div class="pc-card-header"><h2><i class="fa-solid fa-chart-pie"></i> Resumen visual</h2></div>
        <div id="rep_donas_wrap"></div>
    </div>
    `;

    document.getElementById('rep_contenido').innerHTML = html;

    pintarTop5(top_moldes || []);
    pintarTablaDetalle(detalle || []);
    pintarDonasPorUnidad(por_turno || [], por_maquina || []);
}

function pintarTop5(filas) {
    const wrap = document.getElementById('rep_top5_wrap');
    if (filas.length === 0) {
        wrap.innerHTML = '<div class="rep-vacio-tabla">No hay moldes trabajados en este periodo.</div>';
        return;
    }
    const maxKg = Math.max(...filas.map(f => parseFloat(f.kg_producido) || 0), 0.0001);
    wrap.innerHTML = filas.map((f, i) => {
        const kg = parseFloat(f.kg_producido) || 0;
        const porcentaje = Math.round((kg / maxKg) * 100);
        const nombre = f.producto_descripcion ? `${f.molde_nombre} — ${f.producto_descripcion}` : f.molde_nombre;
        return `
        <div class="rep-top5-item">
            <div class="fila">
                <span>${i + 1}. ${nombre} ${parseInt(f.avances_pendientes, 10) > 0 ? `<small class="text-warning">${parseInt(f.avances_pendientes, 10)} pendiente(s)</small>` : ''}</span>
                <b>${formatearCantidad(f.kg_producido, f.unidad)}</b>
            </div>
            <div class="rep-barra-wrap"><div class="rep-barra" style="width:${porcentaje}%"></div></div>
        </div>`;
    }).join('');
}

function etiquetaUnidad(u) {
    const unidad = (u || 'kg').toString().trim().toLowerCase();
    return unidad || 'kg';
}

function formatearCantidad(valor, unidad) {
    const n = parseFloat(valor) || 0;
    const u = etiquetaUnidad(unidad);
    // unidades que naturalmente no llevan decimales
    const esEntero = ['und', 'unidad', 'unidades', 'doc', 'docena', 'docenas'].includes(u);
    return n.toLocaleString('es-PE', {
        minimumFractionDigits: 0,
        maximumFractionDigits: esEntero ? 0 : 2
    }) + ' ' + u;
}
function pintarTablaDetalle(detalle) {
    const tbody = document.getElementById('rep_tbody_detalle');
    if (detalle.length === 0) {
        tbody.innerHTML = '<tr><td colspan="11" class="rep-vacio-tabla">No hay avances en este periodo.</td></tr>';
        return;
    }
    tbody.innerHTML = detalle.map(d => `
        <tr>
            <td data-label="Fecha">${formatearFecha(d.fecha)}</td>
            <td data-label="Hora">${(d.hora || '').slice(0, 5)}</td>
            <td data-label="Turno">${TURNO_LABEL[d.turno] ?? d.turno}</td>
            <td data-label="Molde">${d.molde_nombre ?? '-'}</td>
            <td data-label="Producto">${d.producto_descripcion ?? '-'}</td>
            <td data-label="Máquina">${d.maquina_nombre ?? '-'}</td>
            <td data-label="Color">${d.color_nombre ?? '-'}</td>
            <td data-label="Kg insertado">${formatearKg(d.kg_insertado)}</td>
            <td data-label="Cant. producida">${Number(d.confirmado) === 1 ? formatearCantidad(d.kg_producido, d.unidad) : '<span class="badge text-bg-warning">Pendiente de desglose</span>'}</td>
            <td data-label="Pasadas / fotos">${renderPasadasReporte(d.pasadas)}</td>
            <td data-label="Obs.">
                ${d.observaciones
                    ? `<button class="pc-icon-btn" title="Ver observación" onclick="verObservacion('${escaparComillas(d.observaciones)}')"><i class="fa-solid fa-note-sticky"></i></button>`
                    : '-'}
            </td>
        </tr>
    `).join('');
    tbody.querySelectorAll('.rep-foto-pasada').forEach(boton => {
        boton.addEventListener('click', () => verFotoPasada(boton.dataset.ruta || '', boton.dataset.nombre || 'Foto de la pasada'));
    });
}

function escaparHtmlReporte(valor) {
    return String(valor ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function renderPasadasReporte(pasadas) {
    if (!Array.isArray(pasadas) || pasadas.length === 0) return '-';
    return `<div class="rep-pasadas">${pasadas.map(p => {
        const unidad = p.unidad || 'KG';
        const fotos = Array.isArray(p.fotos) ? p.fotos : [];
        const tieneAporteDistinto = p.cantidad_operario !== null && p.cantidad_operario !== undefined
            && Math.abs(Number(p.cantidad_operario) - Number(p.cantidad)) > 0.0001;
        const tooltipAporte = p.cantidad_operario !== null && p.cantidad_operario !== undefined
            ? `Aporte del operario: ${formatearCantidad(p.cantidad_operario, unidad)}`
            : 'Cantidad total de la pasada';
        return `<div class="rep-pasada">
            <span class="rep-pasada-titulo" title="${escaparHtmlReporte(tooltipAporte)}">P${Number(p.numero) || '?'} ${escaparHtmlReporte(formatearCantidad(p.cantidad, unidad).replace(/\s+/g, ''))}</span>
            ${tieneAporteDistinto ? `<span class="rep-pasada-aporte" title="${escaparHtmlReporte(tooltipAporte)}">Op. ${formatearCantidad(p.cantidad_operario, unidad)}</span>` : ''}
            ${fotos.length ? `<span class="rep-pasada-fotos">${fotos.map((foto, i) => `<button type="button" class="rep-foto-pasada" title="Ver foto ${i + 1}" aria-label="Ver foto ${i + 1}" data-ruta="${escaparHtmlReporte(encodeURIComponent(String(foto.url || '')))}" data-nombre="${escaparHtmlReporte(foto.nombre || `Foto ${i + 1}`)}"><i class="fa-solid fa-camera"></i>${fotos.length > 1 ? `<span>${i + 1}</span>` : ''}</button>`).join('')}</span>` : ''}
        </div>`;
    }).join('')}</div>`;
}

function verFotoPasada(rutaCodificada, nombre = 'Foto de la pasada') {
    let ruta = String(rutaCodificada || '');
    try { ruta = decodeURIComponent(ruta); } catch (_) { /* usa la ruta sin decodificar */ }
    ruta = ruta.replace(/^\/+/, '');
    const url = /^https:\/\/res\.cloudinary\.com\//i.test(ruta)
        ? ruta
        : `https://res.cloudinary.com/${ruta}`;
    Swal.fire({
        title: escaparHtmlReporte(nombre),
        imageUrl: url,
        imageAlt: escaparHtmlReporte(nombre),
        showCloseButton: true,
        showConfirmButton: false,
        didOpen: () => {
            const imagen = document.querySelector('.swal2-image');
            if (imagen) {
                imagen.style.maxWidth = '100%';
                imagen.style.maxHeight = '72vh';
                imagen.style.width = 'auto';
                imagen.style.objectFit = 'contain';
            }
        }
    });
}

function verObservacion(texto) {
    document.getElementById('modalDetalleAvanceBody').textContent = texto;
    modalDetalleAvance.show();
}

function pintarDonasPorUnidad(porTurno, porMaquina) {
    Object.values(chartsPorUnidad).forEach(c => c.destroy());
    chartsPorUnidad = {};

    const unidades = [...new Set([
        ...porTurno.map(f => etiquetaUnidad(f.unidad)),
        ...porMaquina.map(f => etiquetaUnidad(f.unidad)),
    ])];

    const wrap = document.getElementById('rep_donas_wrap');
    if (unidades.length === 0) {
        wrap.innerHTML = '';
        return;
    }

    wrap.innerHTML = unidades.map(u => `
        <div class="rep-donas">
            <div class="rep-dona-card">
                <h4><i class="fa-solid fa-chart-pie"></i> Por turno (${u})</h4>
                <div class="rep-dona-canvas-wrap"><canvas id="rep_chart_turno_${u}"></canvas></div>
            </div>
            <div class="rep-dona-card">
                <h4><i class="fa-solid fa-chart-pie"></i> Por máquina (${u})</h4>
                <div class="rep-dona-canvas-wrap"><canvas id="rep_chart_maquina_${u}"></canvas></div>
            </div>
        </div>
    `).join('');

    unidades.forEach(u => {
        const filasTurno = agruparFilasGrafico(
            porTurno.filter(f => etiquetaUnidad(f.unidad) === u),
            f => TURNO_LABEL[String(f.turno || '').trim().toLowerCase()] ?? String(f.turno || 'Sin turno').trim()
        );
        if (filasTurno.length > 0) {
            chartsPorUnidad[`turno_${u}`] = new Chart(document.getElementById(`rep_chart_turno_${u}`), {
                type: 'doughnut',
                data: {
                    labels: filasTurno.map(f => f.etiqueta),
                    datasets: [{
                        data: filasTurno.map(f => f.cantidad),
                        backgroundColor: filasTurno.map(f => TURNO_COLOR[String(f.turno || '').trim().toLowerCase()] ?? '#9ca3af'),
                        borderWidth: 0,
                    }]
                },
                options: {
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                        tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${formatearCantidad(ctx.parsed, u)}` } }
                    }
                }
            });
        }

        const filasMaquina = agruparFilasGrafico(
            porMaquina.filter(f => etiquetaUnidad(f.unidad) === u),
            f => String(f.maquina || 'Sin máquina').trim() || 'Sin máquina'
        );
        if (filasMaquina.length > 0) {
            chartsPorUnidad[`maquina_${u}`] = new Chart(document.getElementById(`rep_chart_maquina_${u}`), {
                type: 'doughnut',
                data: {
                    labels: filasMaquina.map(f => f.etiqueta),
                    datasets: [{
                        data: filasMaquina.map(f => f.cantidad),
                        backgroundColor: filasMaquina.map((_, i) => PALETA_MAQUINAS[i % PALETA_MAQUINAS.length]),
                        borderWidth: 0,
                    }]
                },
                options: {
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                        tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${formatearCantidad(ctx.parsed, u)}` } }
                    }
                }
            });
        }
    });
}

function agruparFilasGrafico(filas, etiquetaFn) {
    const grupos = new Map();
    filas.forEach(fila => {
        const etiqueta = etiquetaFn(fila);
        const clave = String(etiqueta).trim().toLocaleLowerCase('es-PE');
        if (!clave) return;
        if (!grupos.has(clave)) grupos.set(clave, { etiqueta: String(etiqueta).trim(), cantidad: 0, turno: fila.turno });
        grupos.get(clave).cantidad += parseFloat(fila.cantidad) || 0;
    });
    return [...grupos.values()];
}


// ── Helpers de formato ───────────────────────────────────────────────────────
function formatearKg(valor) {
    const n = parseFloat(valor) || 0;
    return n.toLocaleString('es-PE', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' kg';
}

function formatearFecha(iso) {
    if (!iso) return '-';
    const [anio, mes, dia] = iso.split('-');
    const meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    return `${dia} ${meses[parseInt(mes, 10) - 1]} ${anio}`;
}

function escaparComillas(texto) {
    return String(texto ?? '').replace(/'/g, "\\'");
}
</script>

<?php require __DIR__ . '/footer.php'; ?>
