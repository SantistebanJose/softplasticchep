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
    .rep-panel-tabs { display:flex; flex-wrap:wrap; gap:6px; margin:0 0 10px; border-bottom:1px solid #dbe1ea; }
    .rep-panel-tab { border:0; border-bottom:3px solid transparent; background:transparent; color:#64748b; padding:9px 13px; font-size:13px; font-weight:650; }
    .rep-panel-tab.activo { color:var(--pc-navy,#1f2937); border-bottom-color:var(--pc-red,#c0392b); }
    .rep-exec-title { font-size:20px; font-weight:700; margin:0; color:#17233a; }
    .rep-exec-subtitle { color:#64748b; font-size:12px; margin-top:3px; }
    .rep-owner-summary { display:flex; justify-content:space-between; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:12px; }
    .rep-owner-pills { display:flex; gap:8px; flex-wrap:wrap; }
    .rep-download-actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:flex-end; }
    .rep-owner-pill { background:#f4f6fa; border:1px solid #e1e6ee; border-radius:8px; padding:7px 10px; font-size:12px; color:#475569; }
    .rep-owner-pill b { color:#17233a; }
    .rep-week-table { width:100%; border-collapse:collapse; font-size:13px; }
    .rep-week-table th { text-align:left; color:#64748b; font-size:11px; text-transform:uppercase; letter-spacing:.035em; padding:9px 10px; border-bottom:1px solid #dbe1ea; }
    .rep-week-table td { padding:9px 10px; border-bottom:1px solid #edf0f4; vertical-align:top; }
    .rep-week-table tr:last-child td { border-bottom:0; }
    .rep-week-op { font-weight:700; color:#17233a; }
    .rep-week-meta { font-size:11px; color:#64748b; margin-top:2px; }
    .rep-week-qty { font-weight:700; white-space:nowrap; color:#173a8a; }
    .rep-week-mold { display:block; color:#334155; }
    .rep-week-note { color:#64748b; font-size:12px; }
    .rep-cambios-turno { margin:12px 0 16px; border:1px solid #d8e3f2; border-radius:9px; overflow:hidden; }
    .rep-cambios-turno h3 { background:#f2f6fc; color:#193b88; font-size:15px; font-weight:700; margin:0; padding:10px 12px; }
    .rep-cambios-turno table { width:100%; border-collapse:collapse; font-size:12px; }
    .rep-cambios-turno th,.rep-cambios-turno td { border-bottom:1px solid #e8edf4; padding:8px 9px; text-align:left; vertical-align:top; }
    .rep-cambios-turno th { color:#64748b; font-size:10px; text-transform:uppercase; white-space:nowrap; }
    .rep-cambio-pendiente { color:#9a6700; font-weight:650; }
    .rep-operator-block { border:1px solid #e1e6ee; border-radius:9px; overflow:hidden; margin:12px 0; }
    .rep-operator-heading { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; padding:10px 13px; background:#f2f6fc; border-bottom:1px solid #dbe3ef; }
    .rep-operator-heading h3 { color:#173a8a; font-size:15px; font-weight:750; margin:0; }
    .rep-operator-heading span { color:#64748b; display:block; font-size:11px; margin-top:2px; }
    .rep-operator-heading strong { font-size:13px; color:#17233a; }
    .rep-week-detail { min-width:1120px; }
    .rep-week-detail td { font-size:12px; }
    .rep-week-detail th { white-space:nowrap; }
    .rep-pesadas-texto { min-width:100px; font-weight:650; line-height:1.65; white-space:nowrap; }
    .rep-card-title { font-size:19px !important; margin:0 !important; }
    .rep-contenido .pc-card-header h2 { font-size:19px !important; line-height:1.25; margin:0; }
    .rep-contenido .pc-card { margin-bottom:10px; }
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
                <option value="dia">Día</option>
                <option value="semana" selected>Semana</option>
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

<div class="rep-panel-tabs" role="tablist" aria-label="Vistas del reporte de producción">
    <button type="button" class="rep-panel-tab activo" id="rep_tab_semanal" role="tab" aria-selected="true" onclick="cambiarVistaReporte('semanal')">Resumen por operario</button>
    <button type="button" class="rep-panel-tab" id="rep_tab_operario" role="tab" aria-selected="false" onclick="cambiarVistaReporte('operario')">Detalle por operario</button>
    <button type="button" class="rep-panel-tab" id="rep_tab_parejas" role="tab" aria-selected="false" onclick="cambiarVistaReporte('parejas')">Producción en pareja</button>
</div>

<div id="rep_contenido" class="rep-contenido">
    <div class="pc-card">
        <div class="rep-vacio-estado">
            <i class="fa-solid fa-chart-column"></i>
            <p>Cargando el resumen de producción por operario y molde.</p>
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
let ultimoReporteOperario = null;
let chartTurno = null;
let chartMaquina = null;
let chartsPorUnidad = {}; // ej: { turno_kg: Chart, maquina_und: Chart }
let vistaReporteActiva = 'semanal';
let solicitudSemanalActual = 0;
let ultimoResumenSemanal = null;
let ultimoReporteParejas = null;
let solicitudParejasActual = 0;

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
    generarReporteSemanal();

    document.getElementById('rep_modo').addEventListener('change', () => {
        actualizarVisibilidadFechas();
        recargarVistaActiva();
    });
    actualizarVisibilidadFechas();

    ['rep_fecha', 'rep_fecha_desde', 'rep_fecha_hasta', 'rep_turno', 'rep_maquina', 'rep_sucursal']
        .forEach(id => document.getElementById(id).addEventListener('change', () => {
            if (id === 'rep_fecha') actualizarVisibilidadFechas();
            recargarVistaActiva();
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

function cambiarVistaReporte(vista) {
    vistaReporteActiva = vista;
    solicitudSemanalActual++;
    solicitudParejasActual++;
    const semanal = vista === 'semanal';
    const parejas = vista === 'parejas';
    document.getElementById('rep_tab_semanal').classList.toggle('activo', semanal);
    document.getElementById('rep_tab_operario').classList.toggle('activo', !semanal && !parejas);
    document.getElementById('rep_tab_parejas').classList.toggle('activo', parejas);
    document.getElementById('rep_tab_semanal').setAttribute('aria-selected', String(semanal));
    document.getElementById('rep_tab_operario').setAttribute('aria-selected', String(!semanal && !parejas));
    document.getElementById('rep_tab_parejas').setAttribute('aria-selected', String(parejas));
    if (semanal) generarReporteSemanal();
    else if (parejas) generarReporteParejas();
    else recargarSiHayOperario();
}

function recargarVistaActiva() {
    if (vistaReporteActiva === 'semanal') generarReporteSemanal();
    else if (vistaReporteActiva === 'parejas') generarReporteParejas();
    else recargarSiHayOperario();
}

async function generarReporteSemanal() {
    const idSolicitud = ++solicitudSemanalActual;
    const contenido = document.getElementById('rep_contenido');
    const modo = document.getElementById('rep_modo').value;
    const fecha = document.getElementById('rep_fecha').value || hoyISO();
    const params = {
        modo,
        fecha: modo === 'mes' ? `${fecha}-01` : fecha,
        fecha_desde: document.getElementById('rep_fecha_desde').value,
        fecha_hasta: document.getElementById('rep_fecha_hasta').value,
        turno: document.getElementById('rep_turno').value,
        maquina_id: document.getElementById('rep_maquina').value,
        sucursal_id: document.getElementById('rep_sucursal').value,
    };
    contenido.innerHTML = '<div class="pc-card"><div class="rep-vacio-estado"><i class="fa-solid fa-spinner fa-spin"></i><p>Preparando resumen de producción...</p></div></div>';
    try {
        const json = await llamarReporte('REPORTESEMANALOPERARIOS', params);
        if (idSolicitud !== solicitudSemanalActual || vistaReporteActiva !== 'semanal') return;
        if (!json.success) throw new Error(json.message || 'No se pudo cargar el resumen.');
        pintarResumenSemanal(json);
    } catch (error) {
        if (idSolicitud !== solicitudSemanalActual || vistaReporteActiva !== 'semanal') return;
        console.error(error);
        contenido.innerHTML = `<div class="pc-card"><div class="rep-vacio-estado"><p>${escaparHtmlReporte(error.message || 'No se pudo cargar el resumen.')}</p></div></div>`;
    }
}

async function generarReporteParejas() {
    const solicitud = ++solicitudParejasActual;
    const contenido = document.getElementById('rep_contenido');
    const modo = document.getElementById('rep_modo').value;
    const fecha = document.getElementById('rep_fecha').value || hoyISO();
    const params = {
        modo,
        fecha: modo === 'mes' ? `${fecha}-01` : fecha,
        fecha_desde: document.getElementById('rep_fecha_desde').value,
        fecha_hasta: document.getElementById('rep_fecha_hasta').value,
        turno: document.getElementById('rep_turno').value,
        maquina_id: document.getElementById('rep_maquina').value,
        sucursal_id: document.getElementById('rep_sucursal').value,
    };
    contenido.innerHTML = '<div class="pc-card"><div class="rep-vacio-estado"><i class="fa-solid fa-spinner fa-spin"></i><p>Calculando producción compartida...</p></div></div>';
    try {
        const json = await llamarReporte('REPORTEPRODUCCIONPAREJAS', params);
        if (solicitud !== solicitudParejasActual || vistaReporteActiva !== 'parejas') return;
        if (!json.success) throw new Error(json.message || 'No se pudo cargar el reporte de equipos.');
        pintarReporteParejas(json);
    } catch (error) {
        if (solicitud !== solicitudParejasActual || vistaReporteActiva !== 'parejas') return;
        console.error(error);
        contenido.innerHTML = `<div class="pc-card"><div class="rep-vacio-estado"><p>${escaparHtmlReporte(error.message || 'No se pudo cargar el reporte.')}</p></div></div>`;
    }
}

function resumenCantidadesEquipo(cantidades) {
    return Object.entries(cantidades || {}).filter(([, n]) => Number(n) > 0)
        .map(([unidad, cantidad]) => formatearCantidad(cantidad, unidad)).join(' · ') || '0';
}

function aportesEquipoHtml(aportes) {
    if (!Array.isArray(aportes) || !aportes.length) return '-';
    return aportes.map(aporte => {
        const detalle = resumenCantidadesEquipo(aporte.cantidad);
        return `${escaparHtmlReporte(aporte.nombre || `Operario #${aporte.operario_id}`)}: ${escaparHtmlReporte(detalle)}`;
    }).join('<br>');
}

function pintarReporteParejas(json) {
    ultimoReporteParejas = json;
    const equipos = Array.isArray(json.equipos) ? json.equipos : [];
    const totalAvances = equipos.reduce((suma, equipo) => suma + (Number(equipo.avances) || 0), 0);
    const totalCorridas = equipos.reduce((suma, equipo) => suma + (Number(equipo.producciones) || 0), 0);
    const tarjetas = equipos.map(equipo => {
        const nombreEquipo = equipo.operarios.map(persona => persona.nombre).join(' + ');
        const filas = equipo.detalle.map(pasada => `<tr>
            <td>${escaparHtmlReporte(formatearFecha(pasada.fecha))}<br>${escaparHtmlReporte((pasada.hora || '').slice(0, 5))}</td>
            <td>${escaparHtmlReporte(TURNO_LABEL[pasada.turno] || pasada.turno || '-')}</td>
            <td>#${Number(pasada.produccion_id)} · P${Number(pasada.pasada) || '-'}</td>
            <td>${escaparHtmlReporte(pasada.molde || '-')}</td>
            <td>${escaparHtmlReporte(pasada.producto || '-')}</td>
            <td>${escaparHtmlReporte(pasada.maquina || '-')}</td>
            <td>${escaparHtmlReporte(pasada.color || '-')}</td>
            <td><strong>${escaparHtmlReporte(resumenCantidadesEquipo(pasada.cantidades))}</strong></td>
            <td>${aportesEquipoHtml(pasada.aportes)}</td>
        </tr>`).join('');
        return `<section class="rep-operator-block">
            <div class="rep-operator-heading"><div><h3>${escaparHtmlReporte(nombreEquipo)}</h3><span>${Number(equipo.avances) || 0} pasadas compartidas · ${Number(equipo.producciones) || 0} producciones</span></div><strong>Total juntos: ${escaparHtmlReporte(resumenCantidadesEquipo(equipo.totales))}</strong></div>
            <div class="pc-table-wrap"><table class="rep-week-table rep-week-detail"><thead><tr><th>Fecha / hora</th><th>Turno</th><th>Producción / pasada</th><th>Molde</th><th>Producto</th><th>Máquina</th><th>Color</th><th>Produjeron juntos</th><th>Aporte por operario</th></tr></thead><tbody>${filas}</tbody></table></div>
        </section>`;
    }).join('');
    document.getElementById('rep_contenido').innerHTML = `<div class="pc-card">
        <div class="rep-owner-summary"><div><h2 class="rep-exec-title">Producción en pareja</h2><div class="rep-exec-subtitle">${escaparHtmlReporte(json.periodo?.etiqueta || '')}</div></div>
            <div class="rep-download-actions"><div class="rep-owner-pills"><span class="rep-owner-pill"><b>${equipos.length}</b> equipos</span><span class="rep-owner-pill"><b>${totalAvances}</b> pasadas compartidas</span><span class="rep-owner-pill"><b>${totalCorridas}</b> producciones</span></div><button type="button" class="btn btn-primary btn-sm" onclick="imprimirReporteParejas()"><i class="fa-solid fa-file-pdf"></i> Imprimir / Guardar PDF</button></div>
        </div>
        <div class="rep-week-note mb-3">Se excluye el molde Gancho Pinza / Pinza Palanita y las pasadas con aporte cero por cambio de turno.</div>
        ${tarjetas || '<div class="rep-vacio-tabla">No hay pasadas registradas por varios operarios en este periodo y con estos filtros.</div>'}
    </div>`;
}

function imprimirReporteParejas() {
    if (!ultimoReporteParejas) return;
    const ventana = window.open('', '_blank');
    if (!ventana) {
        Swal.fire('Ventana bloqueada', 'Permite las ventanas emergentes para imprimir o guardar el reporte como PDF.', 'info');
        return;
    }
    const equipos = Array.isArray(ultimoReporteParejas.equipos) ? ultimoReporteParejas.equipos : [];
    const secciones = equipos.map(equipo => {
        const nombre = equipo.operarios.map(persona => persona.nombre).join(' + ');
        const filas = equipo.detalle.map(pasada => `<tr>
            <td>${escaparHtmlReporte(formatearFecha(pasada.fecha))} ${escaparHtmlReporte((pasada.hora || '').slice(0, 5))}</td>
            <td>${escaparHtmlReporte(TURNO_LABEL[pasada.turno] || pasada.turno || '-')}</td>
            <td>#${Number(pasada.produccion_id)} · P${Number(pasada.pasada) || '-'}</td>
            <td>${escaparHtmlReporte(pasada.molde || '-')}</td><td>${escaparHtmlReporte(pasada.producto || '-')}</td>
            <td>${escaparHtmlReporte(pasada.maquina || '-')}</td><td>${escaparHtmlReporte(pasada.color || '-')}</td>
            <td>${escaparHtmlReporte(resumenCantidadesEquipo(pasada.cantidades))}</td><td>${aportesEquipoHtml(pasada.aportes)}</td>
        </tr>`).join('');
        return `<section><header class="equipo"><h2>${escaparHtmlReporte(nombre)}</h2><strong>Total juntos: ${escaparHtmlReporte(resumenCantidadesEquipo(equipo.totales))}</strong><span>${Number(equipo.avances) || 0} pasadas · ${Number(equipo.producciones) || 0} producciones</span></header>
            <table><thead><tr><th>Fecha / hora</th><th>Turno</th><th>Producción / pasada</th><th>Molde</th><th>Producto</th><th>Máquina</th><th>Color</th><th>Produjeron juntos</th><th>Aporte por operario</th></tr></thead><tbody>${filas}</tbody></table></section>`;
    }).join('');
    const documento = `<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Producción en pareja</title><style>
        @page{size:landscape;margin:10mm}*{box-sizing:border-box}body{font:10px Arial,sans-serif;color:#17233a;margin:0}h1{font-size:18px;margin:0 0 4px}p{color:#52627a;margin:0 0 12px}
        section{margin:0 0 14px;break-inside:auto}.equipo{display:flex;align-items:center;gap:12px;flex-wrap:wrap;background:#eef3fb;border:1px solid #ccd8eb;padding:7px 9px;break-after:avoid}.equipo h2{font-size:13px;color:#193b88;margin:0;flex:1}.equipo span{color:#64748b}
        table{width:100%;border-collapse:collapse;table-layout:fixed}thead{display:table-header-group}th{background:#e8edf5;color:#43516a;font-size:8px;text-transform:uppercase;text-align:left}th,td{border:1px solid #d8dee8;padding:5px 4px;vertical-align:top;overflow-wrap:anywhere}td{font-size:9px}tbody tr{break-inside:avoid}
        @media screen{body{padding:16px;background:#eef1f5}.hoja{max-width:1400px;margin:0 auto;background:white;padding:18px;box-shadow:0 2px 12px #17233a22}}
    </style></head><body><main class="hoja"><h1>Reporte de producción en pareja</h1><p>${escaparHtmlReporte(ultimoReporteParejas.periodo?.etiqueta || '')} · Generado ${new Date().toLocaleString('es-PE')}</p><p>Se excluye Gancho Pinza / Pinza Palanita y las pasadas con aporte cero por cambio de turno.</p>${secciones || '<p>No hay producción compartida en el periodo seleccionado.</p>'}</main></body></html>`;
    ventana.document.open(); ventana.document.write(documento); ventana.document.close(); ventana.focus(); ventana.print();
}

function pintarResumenSemanal(json) {
    ultimoResumenSemanal = json;
    const filas = Array.isArray(json.detalle) ? json.detalle : [];
    const porOperario = new Map();
    let totalAvances = 0;
    const totalesUnidad = new Map();
    filas.forEach(f => {
        const id = String(f.operario_id ?? f.operario);
        if (!porOperario.has(id)) porOperario.set(id, { nombre: f.operario, detalle: [], avances: 0, pendientes: 0, totalUnidades: new Map() });
        const grupo = porOperario.get(id);
        const unidad = etiquetaUnidad(f.unidad);
        const producido = Number(f.producido) || 0;
        const pendientes = Number(f.pendiente) || 0;
        grupo.detalle.push(f);
        grupo.avances++;
        grupo.pendientes += pendientes;
        const unidadesMixtas = sumarUnidadesMixtas(f.pasadas);
        if (unidadesMixtas) {
            Object.entries(unidadesMixtas).forEach(([u, cantidad]) => {
                grupo.totalUnidades.set(etiquetaUnidad(u), (grupo.totalUnidades.get(etiquetaUnidad(u)) || 0) + cantidad);
                totalesUnidad.set(etiquetaUnidad(u), (totalesUnidad.get(etiquetaUnidad(u)) || 0) + cantidad);
            });
        } else {
            grupo.totalUnidades.set(unidad, (grupo.totalUnidades.get(unidad) || 0) + producido);
            totalesUnidad.set(unidad, (totalesUnidad.get(unidad) || 0) + producido);
        }
        totalAvances++;
    });
    const totalPills = [...totalesUnidad.entries()].map(([unidad, total]) => `<span class="rep-owner-pill">Producido <b>${formatearCantidad(total, unidad)}</b></span>`).join('');
    const cambiosTurnoHtml = renderCambiosTurno(ultimoResumenSemanal.cambios_turno || []);
    const cuerpo = [...porOperario.values()].map(op => {
        const totales = [...op.totalUnidades.entries()].map(([unidad, total]) => formatearCantidad(total, unidad)).join(' · ');
        const filasDetalle = op.detalle.map(f => `<tr>
            <td>${escaparHtmlReporte(formatearFecha(f.fecha))}</td>
            <td>${escaparHtmlReporte((f.hora || '').slice(0, 5))}</td>
            <td>${escaparHtmlReporte(TURNO_LABEL[f.turno] ?? f.turno ?? '-')}</td>
            <td>${escaparHtmlReporte(f.molde || '-')}</td>
            <td>${escaparHtmlReporte(f.producto || '-')}</td>
            <td>${escaparHtmlReporte(f.maquina || '-')}</td>
            <td>${escaparHtmlReporte(f.color || '-')}</td>
            <td>${formatearKg(f.kg_insertado)}</td>
            <td class="rep-week-qty">${Number(f.pendiente) ? '<span class="text-warning">Pendiente</span>' : (formatearSalidaMixta(f.pasadas) || formatearCantidad(f.producido, f.unidad))}</td>
            <td class="rep-pesadas-texto">${renderCantidadesPesadas(f.pasadas)}</td>
            <td>${escaparHtmlReporte(f.observaciones || '-')}</td>
        </tr>`).join('');
        return `<section class="rep-operator-block"><div class="rep-operator-heading"><div><h3>${escaparHtmlReporte(op.nombre)}</h3><span>${op.avances} avances · ${op.pendientes} pendiente${op.pendientes === 1 ? '' : 's'}</span></div><strong>Total producido: ${totales || '0'}</strong></div>
            <div class="pc-table-wrap"><table class="rep-week-table rep-week-detail"><thead><tr><th>Fecha</th><th>Hora</th><th>Turno</th><th>Molde</th><th>Producto</th><th>Máquina</th><th>Color</th><th>Kg insertado</th><th>Cant. producida</th><th>Pesadas</th><th>Obs.</th></tr></thead><tbody>${filasDetalle || '<tr><td colspan="11">Sin avances</td></tr>'}</tbody></table></div></section>`;
    }).join('');
    document.getElementById('rep_contenido').innerHTML = `
        <div class="pc-card">
            <div class="rep-owner-summary">
                <div><h2 class="rep-exec-title">Resumen de producción por operario</h2><div class="rep-exec-subtitle">${escaparHtmlReporte(json.periodo?.etiqueta || '')}</div></div>
                <div class="rep-download-actions"><div class="rep-owner-pills"><span class="rep-owner-pill"><b>${porOperario.size}</b> operarios</span><span class="rep-owner-pill"><b>${totalAvances}</b> avances</span>${totalPills}</div><button type="button" class="btn btn-primary btn-sm" onclick="imprimirResumenProduccion()"><i class="fa-solid fa-file-pdf"></i> Imprimir / Guardar PDF</button></div>
            </div>
            ${cambiosTurnoHtml}
            ${filas.length ? cuerpo : '<div class="rep-vacio-tabla">No hay producción registrada en este periodo con los filtros seleccionados.</div>'}
            <div class="rep-week-note mt-2">Las pesadas muestran sus cantidades. El reporte descargado incluye esos importes y excluye las fotos.</div>
        </div>`;
}

function formatearFechaHoraCambio(fecha) {
    if (!fecha) return '-';
    const normalizada = String(fecha).replace(' ', 'T');
    const date = new Date(normalizada);
    return Number.isNaN(date.getTime()) ? escaparHtmlReporte(fecha) : escaparHtmlReporte(date.toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' }));
}

function renderCambiosTurno(cambios) {
    if (!Array.isArray(cambios) || !cambios.length) return '<section class="rep-cambios-turno"><h3><i class="fa-solid fa-right-left"></i> Cambios de turno (0)</h3><div class="rep-week-note p-3">No se registraron cambios de turno en este periodo.</div></section>';
    const filas = cambios.map(c => `<tr>
        <td>#${Number(c.produccion_id)}</td><td>${formatearFechaHoraCambio(c.pausa_fecha)}</td>
        <td>${c.reanudacion_fecha ? formatearFechaHoraCambio(c.reanudacion_fecha) : '<span class="rep-cambio-pendiente">Pendiente de reanudación</span>'}</td>
        <td>${escaparHtmlReporte(c.operarios_antes?.join(', ') || 'Sin dato')}</td>
        <td>${escaparHtmlReporte(c.operarios_despues?.join(', ') || (c.reanudacion_fecha ? 'Sin dato' : 'Aún no continúa'))}</td>
        <td>${escaparHtmlReporte(c.molde || '-')}</td>
    </tr>`).join('');
    return `<section class="rep-cambios-turno"><h3><i class="fa-solid fa-right-left"></i> Cambios de turno (${cambios.length})</h3><div class="pc-table-wrap"><table><thead><tr><th>Producción</th><th>Pausada</th><th>Reanudada</th><th>Operarios anteriores</th><th>Operarios que retomaron</th><th>Molde</th></tr></thead><tbody>${filas}</tbody></table></div></section>`;
}

function renderCantidadesPesadas(pasadas) {
    if (!Array.isArray(pasadas) || !pasadas.length) return '-';
    return pasadas.map(p => {
        const mezcla = p.cantidades_unidades;
        const detalle = mezcla && (Number(mezcla.DOC) || Number(mezcla.UND))
            ? formatearUnidadesMixtas(mezcla)
            : formatearCantidad(p.cantidad_operario ?? p.cantidad, p.unidad);
        return `P${Number(p.numero) || 1}: ${detalle}`;
    }).join('<br>');
}

function sumarUnidadesMixtas(pasadas) {
    if (!Array.isArray(pasadas)) return null;
    const suma = {};
    pasadas.forEach(p => { Object.entries(p.cantidades_unidades || {}).forEach(([u, n]) => { suma[u] = (suma[u] || 0) + (Number(n) || 0); }); });
    return Object.keys(suma).length ? suma : null;
}

function formatearSalidaMixta(pasadas) {
    const suma = sumarUnidadesMixtas(pasadas);
    return suma ? formatearUnidadesMixtas(suma) : '';
}

function formatearUnidadesMixtas(cantidades) {
    return Object.entries(cantidades || {})
        .filter(([, cantidad]) => Number(cantidad) > 0)
        .map(([unidad, cantidad]) => formatearCantidad(cantidad, unidad))
        .join(' + ');
}

function imprimirResumenProduccion() {
    if (!ultimoResumenSemanal) return;
    const ventana = window.open('', '_blank');
    if (!ventana) {
        Swal.fire('Ventana bloqueada', 'Permite las ventanas emergentes para imprimir o guardar el reporte como PDF.', 'info');
        return;
    }
    const filasPorOperario = new Map();
    (ultimoResumenSemanal.detalle || []).forEach(f => {
        const id = String(f.operario_id ?? f.operario);
        if (!filasPorOperario.has(id)) filasPorOperario.set(id, { nombre: f.operario, filas: [], totales: new Map() });
        const grupo = filasPorOperario.get(id);
        const unidad = etiquetaUnidad(f.unidad);
        const mixtas = sumarUnidadesMixtas(f.pasadas);
        if (mixtas) Object.entries(mixtas).forEach(([u, n]) => { const etiqueta = etiquetaUnidad(u); grupo.totales.set(etiqueta, (grupo.totales.get(etiqueta) || 0) + n); });
        else grupo.totales.set(unidad, (grupo.totales.get(unidad) || 0) + (Number(f.producido) || 0));
        const pesadas = renderCantidadesPesadas(f.pasadas);
        grupo.filas.push(`<tr>
            <td>${escaparHtmlReporte(formatearFecha(f.fecha))}</td><td>${escaparHtmlReporte((f.hora || '').slice(0, 5))}</td>
            <td>${escaparHtmlReporte(TURNO_LABEL[f.turno] ?? f.turno ?? '-')}</td><td>${escaparHtmlReporte(f.molde || '-')}</td>
            <td>${escaparHtmlReporte(f.producto || '-')}</td><td>${escaparHtmlReporte(f.maquina || '-')}</td>
            <td>${escaparHtmlReporte(f.color || '-')}</td><td>${formatearKg(f.kg_insertado)}</td>
            <td>${Number(f.pendiente) ? 'Pendiente de desglose' : (formatearSalidaMixta(f.pasadas) || formatearCantidad(f.producido, f.unidad))}</td>
            <td>${pesadas}</td><td>${escaparHtmlReporte(f.observaciones || '-')}</td>
        </tr>`);
    });
    const secciones = [...filasPorOperario.values()].map((op, indice) => {
        const total = [...op.totales.entries()].map(([unidad, cantidad]) => formatearCantidad(cantidad, unidad)).join(' · ');
        return `<section class="operario ${indice ? 'siguiente' : ''}"><div class="operario-titulo"><h2>${escaparHtmlReporte(op.nombre)}</h2><strong>Total producido: ${total}</strong></div>
            <table><thead><tr><th>Fecha</th><th>Hora</th><th>Turno</th><th>Molde</th><th>Producto</th><th>Máquina</th><th>Color</th><th>Kg insertado</th><th>Cant. producida</th><th>Pesadas</th><th>Obs.</th></tr></thead><tbody>${op.filas.join('')}</tbody></table></section>`;
    }).join('');
    const cambiosTurno = (ultimoResumenSemanal.cambios_turno || []).length
        ? `<section class="cambios"><h2>Cambios de turno</h2><table><thead><tr><th>Producción</th><th>Pausada</th><th>Reanudada</th><th>Operarios anteriores</th><th>Operarios que retomaron</th><th>Molde</th></tr></thead><tbody>${ultimoResumenSemanal.cambios_turno.map(c => `<tr>
            <td>#${Number(c.produccion_id)}</td><td>${formatearFechaHoraCambio(c.pausa_fecha)}</td>
            <td>${c.reanudacion_fecha ? formatearFechaHoraCambio(c.reanudacion_fecha) : 'Pendiente de reanudación'}</td>
            <td>${escaparHtmlReporte(c.operarios_antes?.join(', ') || 'Sin dato')}</td>
            <td>${escaparHtmlReporte(c.operarios_despues?.join(', ') || (c.reanudacion_fecha ? 'Sin dato' : 'Aún no continúa'))}</td>
            <td>${escaparHtmlReporte(c.molde || '-')}</td></tr>`).join('')}</tbody></table></section>`
        : '<section class="cambios"><h2>Cambios de turno</h2><p>No se registraron cambios de turno en este periodo.</p></section>';
    const documento = `<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Reporte de producción por operario</title><style>
        @page{size:landscape;margin:10mm}*{box-sizing:border-box}body{font:10px Arial,sans-serif;color:#17233a;margin:0}
        header{display:flex;justify-content:space-between;align-items:end;border-bottom:2px solid #193b88;padding:0 0 8px;margin-bottom:10px}
        h1{font-size:18px;margin:0 0 3px}header p{font-size:10px;color:#52627a;margin:0}.generado{color:#64748b;text-align:right}
        .operario{margin:0 0 12px;break-inside:auto}.operario.siguiente{break-before:page}.operario-titulo{display:flex;justify-content:space-between;align-items:center;background:#eef3fb;border:1px solid #ccd8eb;border-bottom:0;padding:7px 9px;break-after:avoid}
        .operario h2{font-size:13px;color:#193b88;margin:0}.operario strong{font-size:11px}
        table{width:100%;border-collapse:collapse;table-layout:fixed}thead{display:table-header-group}th{background:#e8edf5;color:#43516a;font-size:8px;text-transform:uppercase;text-align:left}th,td{border:1px solid #d8dee8;padding:5px 4px;vertical-align:middle;overflow-wrap:anywhere}td{font-size:9px}tbody tr{break-inside:avoid}
        th:nth-child(1){width:7%}th:nth-child(2){width:5%}th:nth-child(3){width:6%}th:nth-child(4){width:11%}th:nth-child(5){width:10%}th:nth-child(6){width:8%}th:nth-child(7){width:8%}th:nth-child(8){width:7%}th:nth-child(9){width:8%}th:nth-child(10){width:13%}th:nth-child(11){width:7%}
        .cambios{margin:10px 0 14px;break-inside:avoid}.cambios h2{font-size:13px;color:#193b88;margin:0;padding:7px 9px;background:#eef3fb;border:1px solid #ccd8eb;border-bottom:0}.cambios table{table-layout:auto}.cambios th{font-size:8px}.cambios td{font-size:9px}
        @media screen{body{padding:16px;background:#eef1f5}.hoja{max-width:1400px;margin:0 auto;background:white;padding:18px;box-shadow:0 2px 12px #17233a22}}
    </style></head><body><main class="hoja"><header><div><h1>Reporte de producción por operario</h1><p>${escaparHtmlReporte(ultimoResumenSemanal.periodo?.etiqueta || '')}</p></div><p class="generado">Generado: ${new Date().toLocaleString('es-PE')}</p></header>${cambiosTurno}${secciones || '<p>No hay producción en el periodo seleccionado.</p>'}</main></body></html>`;
    ventana.document.open();
    ventana.document.write(documento);
    ventana.document.close();
    ventana.focus();
    ventana.print();
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
    cambiarVistaReporte('operario');
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
    cambiarVistaReporte('semanal');
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
    ultimoReporteOperario = json;
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
            <div class="d-flex align-items-center gap-2"><span class="text-muted">${detalle.length} avance${detalle.length === 1 ? '' : 's'}</span>
            <button type="button" class="btn btn-primary btn-sm" onclick="imprimirDetalleOperario()"><i class="fa-solid fa-file-pdf"></i> Imprimir / Guardar PDF</button></div>
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

function imprimirDetalleOperario() {
    if (!ultimoReporteOperario) return;
    const ventana = window.open('', '_blank');
    if (!ventana) {
        Swal.fire('Ventana bloqueada', 'Permite las ventanas emergentes para imprimir o guardar el reporte como PDF.', 'info');
        return;
    }
    const { operario, periodo, resumen_general: resumen = {}, resumen_por_unidad: unidades = [], detalle = [] } = ultimoReporteOperario;
    const resumenUnidades = unidades.map(u => `${formatearCantidad(u.total_producido, u.unidad)}`).join(' · ') || 'Sin producción confirmada';
    const filas = detalle.map(d => {
        const pasadas = renderCantidadesPesadas(d.pasadas);
        const producido = Number(d.confirmado) === 1
            ? (formatearSalidaMixta(d.pasadas) || formatearCantidad(d.kg_producido, d.unidad))
            : 'Pendiente de desglose';
        return `<tr>
            <td>${escaparHtmlReporte(formatearFecha(d.fecha))}</td>
            <td>${escaparHtmlReporte((d.hora || '').slice(0, 5))}</td>
            <td>${escaparHtmlReporte(TURNO_LABEL[d.turno] ?? d.turno ?? '-')}</td>
            <td>${escaparHtmlReporte(d.molde_nombre || '-')}</td>
            <td>${escaparHtmlReporte(d.producto_descripcion || '-')}</td>
            <td>${escaparHtmlReporte(d.maquina_nombre || '-')}</td>
            <td>${escaparHtmlReporte(d.color_nombre || '-')}</td>
            <td>${escaparHtmlReporte(formatearKg(d.kg_insertado))}</td>
            <td>${escaparHtmlReporte(producido)}</td>
            <td>${pasadas === '-' ? '-' : pasadas}</td>
            <td>${escaparHtmlReporte(d.observaciones || '-')}</td>
        </tr>`;
    }).join('');
    const documento = `<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Producción - ${escaparHtmlReporte(operario.nombre_completo)}</title><style>
        @page{size:landscape;margin:10mm}*{box-sizing:border-box}body{font:10px Arial,sans-serif;color:#17233a;margin:0}
        header{display:flex;justify-content:space-between;align-items:end;border-bottom:2px solid #193b88;padding:0 0 8px;margin-bottom:10px}
        h1{font-size:18px;margin:0 0 3px}header p{font-size:10px;color:#52627a;margin:0}.generado{color:#64748b;text-align:right}
        .resumen{display:grid;grid-template-columns:repeat(5,1fr);gap:7px;margin:10px 0 14px}.dato{border:1px solid #d8dee8;border-top:3px solid #ed174c;border-radius:6px;padding:7px}.dato b{display:block;font-size:14px;color:#173b9a}.dato span{font-size:8px;color:#64748b;text-transform:uppercase}
        table{width:100%;border-collapse:collapse;table-layout:fixed}thead{display:table-header-group}th{background:#e8edf5;color:#43516a;font-size:8px;text-transform:uppercase;text-align:left}th,td{border:1px solid #d8dee8;padding:5px 4px;vertical-align:top;overflow-wrap:anywhere}td{font-size:9px}tbody tr{break-inside:avoid}
        th:nth-child(1){width:7%}th:nth-child(2){width:5%}th:nth-child(3){width:6%}th:nth-child(4){width:11%}th:nth-child(5){width:11%}th:nth-child(6){width:8%}th:nth-child(7){width:8%}th:nth-child(8){width:8%}th:nth-child(9){width:9%}th:nth-child(10){width:13%}th:nth-child(11){width:8%}
        @media screen{body{padding:16px;background:#eef1f5}.hoja{max-width:1400px;margin:0 auto;background:white;padding:18px;box-shadow:0 2px 12px #17233a22}}
    </style></head><body><main class="hoja"><header><div><h1>Reporte de producción por operario</h1><p>${escaparHtmlReporte(operario.nombre_completo)} · ${escaparHtmlReporte(operario.cargo || 'Sin cargo asignado')} · ${escaparHtmlReporte(periodo.etiqueta || '')}</p></div><p class="generado">Generado: ${new Date().toLocaleString('es-PE')}</p></header>
        <section class="resumen"><div class="dato"><b>${Number(resumen.total_avances) || 0}</b><span>Avances</span></div><div class="dato"><b>${Number(resumen.moldes_distintos) || 0}</b><span>Moldes distintos</span></div><div class="dato"><b>${escaparHtmlReporte(formatearKg(resumen.total_kg_insertado))}</b><span>Kg insertados</span></div><div class="dato"><b>${escaparHtmlReporte(formatearKg(resumen.total_merma))}</b><span>Merma</span></div><div class="dato"><b>${escaparHtmlReporte(resumenUnidades)}</b><span>Producción confirmada</span></div></section>
        <table><thead><tr><th>Fecha</th><th>Hora</th><th>Turno</th><th>Molde</th><th>Producto</th><th>Máquina</th><th>Color</th><th>Kg insertado</th><th>Cant. producida</th><th>Pasadas</th><th>Obs.</th></tr></thead><tbody>${filas || '<tr><td colspan="11">No hay avances en el periodo seleccionado.</td></tr>'}</tbody></table>
    </main></body></html>`;
    ventana.document.open();
    ventana.document.write(documento);
    ventana.document.close();
    ventana.focus();
    ventana.print();
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
            <td data-label="Cant. producida">${Number(d.confirmado) === 1 ? (formatearSalidaMixta(d.pasadas) || formatearCantidad(d.kg_producido, d.unidad)) : '<span class="badge text-bg-warning">Pendiente de desglose</span>'}</td>
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
            <span class="rep-pasada-titulo" title="${escaparHtmlReporte(tooltipAporte)}">P${Number(p.numero) || '?'}: ${escaparHtmlReporte(formatearCantidad(p.cantidad, unidad))}</span>
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
