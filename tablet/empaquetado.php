<?php
session_start();
require __DIR__ . '/../controllers_tablet/clssAuthOperario.php';

if (empty($_SESSION['operario_id'])) {
    header('Location: loginoperarios.php');
    exit;
}

exigirAccesoEtapa('EMPAQUETA', 'Empaquetado'); // TODO: confirmar que esta es la clave de etapa correcta

$operarioId     = (int) $_SESSION['operario_id'];
$operarioNombre = $_SESSION['operario_nombre'] ?? 'Operario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empaquetado · Plásticos Chepito</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/produccion_tablet.css">
</head>
<body>

<header class="pc-op-brand-bar pc-op-brand-bar-inline">
    <div class="pc-op-brand">
        <a href="panel.php" class="pc-op-back" title="Volver al panel"><i class="fa-solid fa-arrow-left"></i></a>
        <img src="../assets/img/logo.png" alt="Plásticos Chepito" class="pc-op-brand-mark">
        <div class="pc-op-brand-text">
            <span class="pc-op-brand-name">Empaquetado</span>
            <span class="pc-op-brand-tag">Operario: <?= htmlspecialchars($operarioNombre) ?></span>
        </div>
    </div>
    <a href="logoutoperario.php" class="pc-op-panel-logout">
        <i class="fa-solid fa-right-from-bracket"></i> Salir
    </a>
</header>

<style>
:root{
    --emp-accent:#2F6FED; --emp-accent-bg:#EAF0FE;
}

/* ---------- Stat row (mismo patrón que Ensamblaje) ---------- */
.pc-stat-row{ display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:18px; }
.pc-stat-chip{ border:1px solid #e7e4dd; border-radius:14px; background:#fff; padding:14px; display:flex; align-items:center; gap:10px; }
.pc-stat-chip .ico{ width:38px; height:38px; border-radius:10px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:16px; }
.pc-stat-chip .txt .n{ font-size:21px; font-weight:700; line-height:1.15; color:#152238; }
.pc-stat-chip .txt .l{ font-size:11.5px; color:#8a8578; }
.pc-stat-chip.s-gray .ico{ background:#EEECE6; color:#8a8578; }
.pc-stat-chip.s-info .ico{ background:#E3F2FD; color:#0B4DA6; }
.pc-stat-chip.s-success .ico{ background:#E8F7EE; color:#16A34A; }
.pc-stat-chip.s-purple .ico{ background:#F1EAFD; color:#7C3AED; }
@media (max-width:900px){ .pc-stat-row{ grid-template-columns:repeat(2,1fr); } }

/* ---------- Tabs de producto (táctiles) ---------- */
.pc-tabs-toolbar{ display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; border-bottom:1px solid #e7e4dd; margin-bottom:18px; }
.pc-tabs-row{ display:flex; align-items:center; gap:20px; flex-wrap:wrap; row-gap:6px; overflow-x:auto; }
.pc-tab-item{ display:flex; align-items:center; gap:8px; padding:12px 6px 14px 6px; border:none; background:none; cursor:pointer; font-size:1em; font-weight:600; color:#8a8578; border-bottom:2px solid transparent; white-space:nowrap; min-height:44px; }
.pc-tab-item.activo{ color:#152238; border-bottom-color:var(--emp-accent); }
.pc-tab-item .cnt{ background:#EEECE6; color:#5c5947; font-size:.78em; font-weight:700; border-radius:999px; padding:3px 9px; min-width:20px; text-align:center; }
.pc-tab-item.activo .cnt{ background:#152238; color:#fff; }

.pc-est-empty{ text-align:center; color:#9a9585; padding:50px 12px; font-size:1.05em; }
.pc-est-empty i{ font-size:1.6em; display:block; margin-bottom:10px; opacity:.5; }

/* ---------- Chips de operarios (táctiles, grandes) ---------- */
.pc-operario-chips-wrap{ display:flex; flex-wrap:wrap; gap:8px; min-height:44px; align-items:flex-start; padding-top:2px; }
.pc-operario-chip{
    border:1px solid #e2ddcd; background:#fff; border-radius:999px; padding:10px 16px;
    font-size:.95em; font-weight:600; cursor:pointer; color:#3a3730; min-height:44px;
}
.pc-operario-chip.activo{ background:var(--emp-accent); border-color:var(--emp-accent); color:#fff; }

/* ---------- Estación visual de sacos/colores (táctil, agrandada) ---------- */
.pc-estacion{ display:grid; grid-template-columns:1.3fr 1fr; gap:16px; align-items:start; }
@media (max-width:900px){ .pc-estacion{ grid-template-columns:1fr; } }
.pc-estacion-hint{ font-size:.85em; color:#9a9585; margin:0 0 10px; }
.pc-sacos-grid{ display:grid; grid-template-columns:repeat(auto-fill, minmax(150px,1fr)); gap:12px; }
.pc-saco-card{
    border:1px solid #e2ddcd; border-radius:14px; padding:12px; background:#fff;
    cursor:pointer; text-align:left; position:relative; min-height:110px;
}
.pc-saco-card:active{ transform:scale(.96); border-color:var(--emp-accent); }
.pc-saco-card.agotado{ opacity:.4; cursor:not-allowed; pointer-events:none; }
.pc-saco-card .swatch{ width:100%; height:44px; border-radius:10px; }
.pc-saco-card .nombre{ font-size:.92em; font-weight:700; margin:9px 0 1px; color:#3a3730; }
.pc-saco-card .origen{ font-size:.8em; color:#9a9585; }
.pc-saco-card .disp{ font-size:.8em; color:#6b6656; margin-top:2px; font-weight:600; }
.pc-saco-card .en-mezcla{
    position:absolute; top:8px; right:8px; background:var(--emp-accent); color:#fff;
    font-size:.72em; font-weight:700; border-radius:999px; padding:2px 9px;
}
.pc-mezcla-panel{ background:#fdfcfa; border:1px solid #e7e4dd; border-radius:14px; padding:14px 16px; }
.pc-mezcla-panel-titulo{ font-size:.82em; color:#9a9585; margin:0 0 10px; text-transform:uppercase; letter-spacing:.03em; font-weight:700; }
.pc-mezcla-lista{ display:flex; flex-direction:column; gap:8px; min-height:36px; }
.pc-mezcla-fila{ display:flex; align-items:center; gap:8px; font-size:.92em; }
.pc-mezcla-fila .swatch-mini{ width:18px; height:18px; border-radius:5px; flex:0 0 auto; border:1px solid rgba(0,0,0,.08); }
.pc-mezcla-fila .nombre{ flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.pc-mezcla-fila input{ width:90px; flex:0 0 auto; min-height:40px; }
.pc-paquete-box{ margin-top:14px; background:#fffaf0; border:1px solid #f4e8c8; border-radius:12px; padding:12px 14px; }
.pc-paquete-box .fila{ display:flex; justify-content:space-between; align-items:center; font-size:.92em; }
.pc-paquete-barra{ height:9px; background:#f1efe9; border-radius:5px; margin-top:8px; overflow:hidden; }
.pc-paquete-barra > div{ height:100%; background:var(--emp-accent); transition:width .15s ease; }
.pc-swatch-picker{ display:flex; flex-wrap:wrap; gap:7px; margin-bottom:7px; }
.pc-swatch-chip{
    width:34px; height:34px; border-radius:9px; border:2px solid transparent;
    cursor:pointer; padding:0; position:relative;
}
.pc-swatch-chip.activo{ border-color:var(--emp-accent); }
.pc-swatch-chip.agotado{ opacity:.35; cursor:not-allowed; pointer-events:none; }

.pc-bulto-card{ border:1px solid #e2ddcd; border-radius:14px; padding:14px; margin-bottom:12px; background:#fffefb; }
.pc-bulto-card-head{ display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; font-size:.95em; font-weight:700; color:#3a3730; flex-wrap:wrap; gap:8px; }
.pc-color-row{ display:flex; gap:8px; align-items:center; margin-bottom:8px; flex-wrap:wrap; }
.pc-color-row select{ flex:2; min-width:160px; min-height:42px; }
.pc-color-row input{ flex:1; min-width:100px; min-height:42px; }
.pc-bulto-remove{ border:none; background:none; color:#c94a4a; font-size:1.15em; flex:0 0 auto; padding:8px; }
.pc-bultos-total{
    display:flex; justify-content:space-between; align-items:center;
    padding:10px 14px; background:#fffaf0; border:1px solid #f4e8c8; border-radius:12px;
    font-size:.95em; margin-top:8px;
}
.pc-bultos-total b{ color:var(--emp-accent); font-size:1.1em; }

.pc-btn-tap{ min-height:46px; font-size:.95em; }

/* ---------- Mis últimos registros ---------- */
.pc-mis-registros{ display:flex; flex-direction:column; gap:10px; }
.pc-reg-card{ border:1px solid #ece9e1; border-radius:14px; background:#fff; padding:14px 16px; display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
.pc-reg-info{ flex:1; min-width:200px; }
.pc-reg-producto{ font-weight:700; font-size:1em; color:#1f2430; }
.pc-reg-detalle{ font-size:.85em; color:#8a8578; margin-top:2px; }
.pc-reg-total{ font-size:1.1em; font-weight:700; color:#152238; white-space:nowrap; }
.pc-reg-fecha{ font-size:.8em; color:#9a9585; white-space:nowrap; }
.pc-reg-badge-vendido{ background:#FDF1E0; color:#D97706; border:1px solid #f4dcb0; border-radius:999px; padding:3px 10px; font-size:.78em; font-weight:700; white-space:nowrap; }
.pc-reg-badge-disp{ background:#E8F7EE; color:#16A34A; border:1px solid #cdeedb; border-radius:999px; padding:3px 10px; font-size:.78em; font-weight:700; white-space:nowrap; }
.pc-reg-del-btn{ border:none; background:#FCEAEC; color:#c94a4a; border-radius:10px; padding:10px 14px; font-size:.9em; min-height:44px; }
</style>

<div class="pc-card" style="margin:20px;">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Empaquetado</h2>
    </div>
    <br>
    <div class="pc-stat-row" id="statRowEmp"></div>

    <div class="pc-tabs-toolbar">
        <div class="pc-tabs-row" id="tabsEmpaquetado"></div>
    </div>

    <div id="estacionVaciaCard">
        <div class="pc-est-empty">
            <i class="fa-solid fa-hand-pointer"></i>
            Elige un producto en las pestañas de arriba para empezar a armar un paquete.
        </div>
    </div>

    <div id="estacionArmadoCard" style="display:none;">
        <h4 id="estacionArmadoTitulo" style="margin-bottom:14px;">Armar paquete</h4>

        <form id="formEstacionArmado">
            <input type="hidden" id="est_producto_id" value="0">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Unidad de medida *</label>
                    <select class="form-select form-select-lg" id="est_unidad_medida" required></select>
                    <small class="text-muted" id="avisoUnidadEstacion" style="display:none;">
                        Este producto no tiene "Salida en Empaquetado" configurada — selecciónala aquí.
                    </small>
                </div>
                <div class="col-md-5 mb-3">
                    <label class="form-label">Operarios *</label>
                    <div id="est_operarios_chips" class="pc-operario-chips-wrap"></div>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Sucursal</label>
                    <select class="form-select form-select-lg" id="est_sucursal_id"></select>
                </div>
            </div>

            <!-- Modo BULTO -->
            <div id="bloqueBultos">
                <label class="form-label">Paquetes (toca un saco para agregar) *</label>
                <p class="pc-estacion-hint">Toca un saco para agregarlo al paquete actual. Ajusta la cantidad exacta abajo si hace falta.</p>
                <div class="pc-sacos-grid" id="sacosBultoGrid" style="margin-bottom:16px;"></div>

                <div id="listaBultos"></div>
                <button type="button" class="btn btn-outline-secondary pc-btn-tap mb-2" onclick="agregarBulto()">
                    <i class="fa-solid fa-plus"></i> Agregar Paquete
                </button>
                <div class="pc-bultos-total">
                    <span><span id="bultosCount">0</span> paquete(s)</span>
                    <span>Total: <b id="bultosTotal">0</b></span>
                </div>
            </div>

            <!-- Modo MEZCLA -->
            <div id="bloqueMezcla" style="display:none;">
                <label class="form-label">Mezcla de sacos (kg por color/origen) *</label>
                <p class="pc-estacion-hint">Toca un saco para llevarlo a la mezcla. Ajusta el kg exacto después.</p>
                <div class="pc-estacion">
                    <div>
                        <div class="pc-sacos-grid" id="sacosMezclaGrid"></div>
                    </div>
                    <div class="pc-mezcla-panel">
                        <p class="pc-mezcla-panel-titulo">Mezcla actual</p>
                        <div class="pc-mezcla-lista" id="listaMezclaOrigenes"></div>

                        <div class="pc-paquete-box">
                            <div class="fila">
                                <span>Kg totales mezclados</span>
                                <b id="mezclaKgTotal">0</b>
                            </div>
                            <div class="fila mt-2">
                                <label class="form-label mb-0">Bolsas producidas (144 und c/u) *</label>
                                <input type="number" min="1" step="1" class="form-control form-control-sm" style="max-width:120px; min-height:42px;"
                                    id="mezclaBolsasProducidas" oninput="actualizarBolsasProducidas(this.value)" placeholder="Ej. 50">
                            </div>
                            <div class="fila mt-2" style="font-size:.85em;">
                                <span class="text-muted">Estimado teórico</span>
                                <span><b id="mezclaBolsasTeoricas">-</b> bolsas <span id="mezclaDiferenciaBadge" class="badge" style="display:none; margin-left:6px;"></span></span>
                            </div>
                            <div class="fila mt-2" style="font-size:.8em; color:#9a9585;">
                                <span>Paquete de 24 bolsas</span>
                                <span id="mezclaPaqueteFrac">0 / 24</span>
                            </div>
                            <div class="pc-paquete-barra"><div id="mezclaPaqueteBarra" style="width:0%;"></div></div>
                            <div class="text-muted mt-1" style="font-size:.78em;">
                                ≈ <span id="mezclaPaquetesEstimados">0</span> paquete(s) de 24 bolsas (solo referencia para transporte)
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-outline-secondary pc-btn-tap mt-2" onclick="agregarOrigenMezcla()">
                    <i class="fa-solid fa-plus"></i> Agregar saco/color manualmente
                </button>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary btn-lg pc-btn-tap">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar paquete
                </button>
            </div>
        </form>
    </div>
</div>

<div class="pc-card" style="margin:20px;">
    <div class="pc-card-header">
        <h2>Tus últimos registros</h2>
    </div>
    <div class="pc-mis-registros" id="misRegistrosLista">
        <div class="pc-est-empty">Cargando...</div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const OPERARIO_ID     = <?= json_encode($operarioId) ?>;
const OPERARIO_NOMBRE = <?= json_encode($operarioNombre) ?>;

const CONTROLADOR_EMPAQUETADO = '../controllers/clssEmpaquetado.php';
const CONTROLADOR_SUCURSAL    = '../controllers/clssSucursal.php';

// ── Estado de la ESTACIÓN DE ARMADO ──
let estacionProductoIdActual = 0;
let empUnidadesCache = null;
let empOperariosCache = null;
let origenesDisponiblesCache = [];
let unidadEmpaquetadoProductoActual = null;
let reglasEmpaquetadoActuales = null;
let bultosState = [];
let mezclaOrigenes = [];
let contadorMezclaOrigen = 0;
let bolsasProducidasValor = '';
let contadorBulto = 0;
let contadorColorRow = 0;
let estOperariosSeleccionados = [];

// ── Tabs por producto ──
let cacheFilasEmpaquetado = [];
let tabActivoEmp = null;

// ── Registros propios ──
let registrosGlobalCache = [];

document.addEventListener('DOMContentLoaded', () => {
    cargarPendientesEmpaquetado();
    cargarMisRegistros();
    iniciarAutoRefreshEmp();
});

// ── Auto-refresh silencioso ──
const POLL_INTERVAL_MS_EMP = 10000;
let pollTimerEmp = null;
function iniciarAutoRefreshEmp() {
    if (pollTimerEmp) clearInterval(pollTimerEmp);
    pollTimerEmp = setInterval(() => {
        if (document.hidden) return;
        cargarMisRegistros();
    }, POLL_INTERVAL_MS_EMP);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) cargarMisRegistros();
    });
}

async function llamarEmpaquetado(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_EMPAQUETADO, {
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

async function llamarSucursal(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_SUCURSAL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body
    });
    return resp.json();
}

function formatearCantidadEmp(n) {
    if (n === null || n === undefined || n === '') return '-';
    return Number(n).toLocaleString('es-PE', { maximumFractionDigits: 4 });
}
function formatearFechaHoraLegibleEmp(fechaIso) {
    if (!fechaIso) return '';
    const [fecha, hora] = String(fechaIso).split(' ');
    if (!fecha) return fechaIso;
    const [y, m, d] = fecha.split('-');
    return `${d}/${m}/${y}${hora ? ' ' + hora.substring(0, 5) : ''}`;
}
function esFechaHoyEmp(fechaIso) {
    if (!fechaIso) return false;
    const hoy = new Date();
    const hoyStr = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`;
    return String(fechaIso).split(' ')[0] === hoyStr;
}
function parseJsonColumnaEmp(v) {
    if (!v) return [];
    if (typeof v === 'string') { try { return JSON.parse(v) || []; } catch (e) { return []; } }
    return Array.isArray(v) ? v : [];
}
function textoBultosDetalle(bultos) {
    if (!bultos || bultos.length === 0) return '-';
    return bultos.map(b => {
        if (b.kg_total_mezclados !== undefined) {
            const colores = (b.colores || [])
                .map(c => `${c.color_nombre ?? 'Sin color'}: ${formatearCantidadEmp(c.cantidad_kg)} kg`)
                .join(', ');
            return `${formatearCantidadEmp(b.cantidad)} bolsas (mezcla — ${colores})`;
        }
        const colores = (b.colores || [])
            .map(c => `${c.color_nombre ?? 'Sin color'}: ${formatearCantidadEmp(c.cantidad)}`)
            .join(', ');
        return `${formatearCantidadEmp(b.cantidad)}${colores ? ` (${colores})` : ''}`;
    }).join(' + ');
}
function textoOperariosEmp(r) {
    const lista = parseJsonColumnaEmp(r.js_operarios);
    if (lista.length > 0) return lista.map(o => o.nombre_completo).join(', ');
    return r.operario_nombre ?? '-';
}
function registroEsDeOperarioActual(r) {
    const lista = parseJsonColumnaEmp(r.js_operarios);
    if (lista.length > 0) return lista.some(o => o.operario_id == OPERARIO_ID);
    return r.operario_id == OPERARIO_ID;
}

const PALETA_COLOR_FALLBACK = {
    'VERDE': '#639922', 'AZUL': '#378ADD', 'CELESTE': '#5DB8E8', 'ROJO': '#E24B4A',
    'AMARILLO': '#EF9F27', 'NARANJA': '#D85A30', 'MORADO': '#7F77DD', 'VIOLETA': '#7F77DD',
    'ROSADO': '#D4537E', 'ROSA': '#D4537E', 'NEGRO': '#2C2C2A', 'BLANCO': '#D3D1C7',
    'GRIS': '#888780', 'MARRON': '#712B13', 'CAFE': '#712B13', 'TURQUESA': '#1D9E75',
    'PLOMO': '#5F5E5A', 'BEIGE': '#B4B2A9',
};
function colorHexPara(colorNombre, colorHexBD) {
    if (colorHexBD) return colorHexBD.startsWith('#') ? colorHexBD : `#${colorHexBD}`;
    const clave = String(colorNombre ?? '').trim().toUpperCase();
    if (PALETA_COLOR_FALLBACK[clave]) return PALETA_COLOR_FALLBACK[clave];
    let hash = 0;
    for (let i = 0; i < clave.length; i++) hash = clave.charCodeAt(i) + ((hash << 5) - hash);
    const hue = Math.abs(hash) % 360;
    return `hsl(${hue}, 45%, 55%)`;
}

// =============================================================================
// OPERARIOS (chips)
// =============================================================================

function renderOperariosChips(containerId, seleccionados, toggleFnName) {
    const cont = document.getElementById(containerId);
    if (!cont) return;
    if (!empOperariosCache || empOperariosCache.length === 0) {
        cont.innerHTML = '<div class="text-muted" style="font-size:.9em;">(sin operarios disponibles - revisar consola)</div>';
        return;
    }
    cont.innerHTML = empOperariosCache.map(o => `
        <button type="button" class="pc-operario-chip ${seleccionados.includes(o.id) ? 'activo' : ''}"
                onclick="${toggleFnName}(${o.id})">
            ${o.nombre_completo}
        </button>`).join('');
}

function toggleOperarioEstacion(id) {
    const i = estOperariosSeleccionados.indexOf(id);
    if (i >= 0) estOperariosSeleccionados.splice(i, 1); else estOperariosSeleccionados.push(id);
    renderOperariosChips('est_operarios_chips', estOperariosSeleccionados, 'toggleOperarioEstacion');
}

// =============================================================================
// STAT ROW
// =============================================================================

function renderStatRowEmp() {
    const pendientes = new Set(cacheFilasEmpaquetado.map(f => f.producto_id)).size;
    const hoy = registrosGlobalCache.filter(r => esFechaHoyEmp(r.created_at));
    const tuyosHoy = hoy.filter(registroEsDeOperarioActual);
    const disponiblesStock = registrosGlobalCache.filter(r => !r.pasado_venta).length;

    document.getElementById('statRowEmp').innerHTML = `
        <div class="pc-stat-chip s-gray"><div class="ico"><i class="fa-solid fa-layer-group"></i></div><div class="txt"><div class="n">${pendientes}</div><div class="l">Productos pendientes</div></div></div>
        <div class="pc-stat-chip s-info"><div class="ico"><i class="fa-solid fa-box"></i></div><div class="txt"><div class="n">${hoy.length}</div><div class="l">Paquetes hoy (todos)</div></div></div>
        <div class="pc-stat-chip s-success"><div class="ico"><i class="fa-solid fa-user-check"></i></div><div class="txt"><div class="n">${tuyosHoy.length}</div><div class="l">Tuyos hoy</div></div></div>
        <div class="pc-stat-chip s-purple"><div class="ico"><i class="fa-solid fa-warehouse"></i></div><div class="txt"><div class="n">${disponiblesStock}</div><div class="l">Disponibles (stock)</div></div></div>
    `;
}

// =============================================================================
// TABS + ESTACIÓN DE ARMADO
// =============================================================================

async function cargarPendientesEmpaquetado() {
    const json = await llamarEmpaquetado('LISTARENSAMBLAJESPARAEMPAQUETADO', {});

    if (!json.success) {
        console.error('Error LISTARENSAMBLAJESPARAEMPAQUETADO:', json.message);
        cacheFilasEmpaquetado = [];
        renderTabsEmp();
        actualizarEstacionArmado();
        renderStatRowEmp();
        return;
    }

    cacheFilasEmpaquetado = json.ensamblajes || [];

    if (tabActivoEmp !== null && !cacheFilasEmpaquetado.some(f => claveTabEmp(f) === tabActivoEmp)) {
        tabActivoEmp = null;
    }
    if (tabActivoEmp === null && cacheFilasEmpaquetado.length > 0) {
        tabActivoEmp = claveTabEmp(cacheFilasEmpaquetado[0]);
    }

    renderTabsEmp();
    actualizarEstacionArmado();
    renderStatRowEmp();
}

function claveTabEmp(fila) {
    return fila.producto_id != null ? `id:${fila.producto_id}` : `cod:${fila.producto_codigo ?? ''}`;
}

function renderTabsEmp() {
    const cont = document.getElementById('tabsEmpaquetado');

    const grupos = new Map();
    cacheFilasEmpaquetado.forEach(f => {
        const clave = claveTabEmp(f);
        const label = `${f.producto_codigo ?? ''} - ${f.producto_descripcion ?? 'Sin nombre'}`;
        if (!grupos.has(clave)) grupos.set(clave, { label, count: 0 });
        grupos.get(clave).count++;
    });

    if (grupos.size === 0) {
        cont.innerHTML = '<span class="text-muted" style="padding:12px 0;">No hay productos pendientes de empaquetar.</span>';
        return;
    }

    const tabs = [...grupos.entries()].sort((a, b) => a[1].label.localeCompare(b[1].label));

    cont.innerHTML = tabs.map(([clave, info]) => `
        <button type="button" class="pc-tab-item ${tabActivoEmp === clave ? 'activo' : ''}"
                onclick="seleccionarTabEmp('${clave}')" title="${info.label.replace(/"/g, '&quot;')}">
            <i class="fa-solid fa-layer-group"></i>
            <span>${info.label}</span>
            <span class="cnt">${info.count}</span>
        </button>`).join('');
}

function seleccionarTabEmp(clave) {
    tabActivoEmp = clave;
    renderTabsEmp();
    actualizarEstacionArmado();
}

async function actualizarEstacionArmado() {
    const estCard = document.getElementById('estacionArmadoCard');
    const vacCard = document.getElementById('estacionVaciaCard');

    const fila = cacheFilasEmpaquetado.find(f => claveTabEmp(f) === tabActivoEmp);
    if (!fila) {
        estCard.style.display = 'none';
        vacCard.style.display = 'block';
        estacionProductoIdActual = 0;
        return;
    }

    const productoId = fila.producto_id;
    const label = `${fila.producto_codigo ?? ''} - ${fila.producto_descripcion ?? ''}`;

    vacCard.style.display = 'none';
    estCard.style.display = 'block';
    document.getElementById('estacionArmadoTitulo').textContent = `Armar paquete — ${label}`;
    document.getElementById('est_producto_id').value = productoId;

    if (estacionProductoIdActual === productoId) return;

    await cargarEstacionParaProducto(productoId);
}

async function cargarEstacionParaProducto(productoId) {
    estacionProductoIdActual = productoId;
    bultosState = [];
    mezclaOrigenes = [];
    bolsasProducidasValor = '';

    await Promise.all([
        cargarSelectsEstacion(),
        cargarOrigenesDisponibles(productoId),
    ]);
    aplicarUnidadEmpaquetadoFija();
    inicializarBloqueFormulario();
}

// =============================================================================
// MIS ÚLTIMOS REGISTROS (sin filtros, solo lo reciente)
// =============================================================================

async function cargarMisRegistros() {
    const cont = document.getElementById('misRegistrosLista');
    const json = await llamarEmpaquetado('LISTARTODOSEMPAQUETADOS', {});
    if (!json.success) {
        cont.innerHTML = `<div class="pc-est-empty" style="color:#c94a4a;">${json.message}</div>`;
        return;
    }

    registrosGlobalCache = json.empaquetados || [];
    renderStatRowEmp();

    const misRegistros = registrosGlobalCache
        .filter(registroEsDeOperarioActual)
        .sort((a, b) => String(b.created_at).localeCompare(String(a.created_at)))
        .slice(0, 15);

    if (misRegistros.length === 0) {
        cont.innerHTML = '<div class="pc-est-empty">Aún no tienes registros de empaquetado.</div>';
        return;
    }

    cont.innerHTML = misRegistros.map(r => {
        const bultos = parseJsonColumnaEmp(r.js_cantidades);
        const bultosTexto = textoBultosDetalle(bultos);
        const vendido = !!r.pasado_venta;
        return `
        <div class="pc-reg-card">
            <div class="pc-reg-info">
                <div class="pc-reg-producto">${r.producto_codigo ?? ''} - ${r.producto_descripcion ?? '-'}</div>
                <div class="pc-reg-detalle">${bultosTexto}</div>
                <div class="pc-reg-detalle">${textoOperariosEmp(r)}</div>
            </div>
            <div class="pc-reg-total">${formatearCantidadEmp(r.cantidad_tota)} ${r.unidad_corto ?? ''}</div>
            <div class="pc-reg-fecha">${formatearFechaHoraLegibleEmp(r.created_at)}</div>
            ${vendido
                ? `<span class="pc-reg-badge-vendido">Vendido</span>`
                : `<span class="pc-reg-badge-disp">Disponible</span>
                   <button type="button" class="pc-reg-del-btn" onclick="eliminarMiRegistro(${r.id})" title="Eliminar (corregir un error)">
                       <i class="fa-solid fa-trash"></i>
                   </button>`}
        </div>`;
    }).join('');
}

function eliminarMiRegistro(id) {
    Swal.fire({
        title: '¿Eliminar este registro?',
        text: 'Úsalo solo para corregir un error recién hecho.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarEmpaquetado('ELIMINAREMPAQUETADO', { id });
        if (json.success) {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: json.message, showConfirmButton: false, timer: 1800 });
            const productoAfectado = estacionProductoIdActual;
            await Promise.all([cargarPendientesEmpaquetado(), cargarMisRegistros()]);
            if (productoAfectado) {
                await cargarOrigenesDisponibles(productoAfectado);
                aplicarUnidadEmpaquetadoFija();
                if (esModoMezcla()) renderMezcla(); else renderBultos();
            }
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

// =============================================================================
// SELECTS AUXILIARES
// =============================================================================

async function obtenerUnidadesEmp() {
    if (empUnidadesCache) return empUnidadesCache;
    const json = await llamarEmpaquetado('BUSCARUNIDADESMEDIDA', { texto: '' });
    if (!json.success) console.error('Error BUSCARUNIDADESMEDIDA:', json.message);
    empUnidadesCache = json.success ? json.unidades : [];
    return empUnidadesCache;
}
async function obtenerOperariosEmp() {
    if (empOperariosCache) return empOperariosCache;
    const json = await llamarEmpaquetado('BUSCAROPERARIOS');
    if (!json.success) console.error('Error BUSCAROPERARIOS:', json.message);
    empOperariosCache = json.success ? json.operario : [];
    return empOperariosCache;
}
let empSucursalesCache = null;
async function obtenerSucursalesEmp() {
    if (empSucursalesCache) return empSucursalesCache;
    const json = await llamarSucursal('LISTARSUCURSALES', { visibilidad: 'activas' });
    empSucursalesCache = json.success ? json.sucursales : [];
    return empSucursalesCache;
}

async function cargarSelectsEstacion() {
    const [unidades, operarios, sucursales] = await Promise.all([
        obtenerUnidadesEmp(), obtenerOperariosEmp(), obtenerSucursalesEmp()
    ]);
    const sUnidad = document.getElementById('est_unidad_medida');
    sUnidad.innerHTML = unidades.length
        ? '<option value="">Selecciona...</option>' + unidades.map(u => `<option value="${u.id}">${u.nombre} (${u.nombre_corto})</option>`).join('')
        : '<option value="">(sin unidades disponibles - revisar consola)</option>';

    const sSuc = document.getElementById('est_sucursal_id');
    sSuc.innerHTML = '<option value="">Selecciona...</option>' + (sucursales || []).map(s => `<option value="${s.id}">${s.nombre}</option>`).join('');

    // El operario que inició sesión en la tablet queda preseleccionado; puede
    // agregar compañeros que hayan trabajado con él en este paquete.
    const yoEstoyEnLista = (operarios || []).some(o => o.id == OPERARIO_ID);
    estOperariosSeleccionados = yoEstoyEnLista ? [OPERARIO_ID] : [];
    renderOperariosChips('est_operarios_chips', estOperariosSeleccionados, 'toggleOperarioEstacion');
}

async function cargarOrigenesDisponibles(productoId) {
    const json = await llamarEmpaquetado('BUSCARORIGENESDISPONIBLES', { producto_id: productoId });
    if (!json.success) console.error('Error BUSCARORIGENESDISPONIBLES:', json.message);
    origenesDisponiblesCache = json.success ? (json.origenes || []) : [];
    unidadEmpaquetadoProductoActual = json.success ? (json.unidad_empaquetado || null) : null;
    reglasEmpaquetadoActuales = json.success ? (json.reglas_empaquetado || null) : null;
}

function aplicarUnidadEmpaquetadoFija() {
    const sUnidad = document.getElementById('est_unidad_medida');
    const aviso = document.getElementById('avisoUnidadEstacion');

    if (unidadEmpaquetadoProductoActual && unidadEmpaquetadoProductoActual.id) {
        sUnidad.innerHTML = `<option value="${unidadEmpaquetadoProductoActual.id}" selected>
            ${unidadEmpaquetadoProductoActual.nombre} (${unidadEmpaquetadoProductoActual.nombre_corto})
        </option>`;
        sUnidad.disabled = true;
        aviso.style.display = 'none';
    } else {
        sUnidad.disabled = false;
        aviso.style.display = 'block';
    }
}

// ── Bultos con mezcla de colores ────────────────────────────────────────────

function claveOrigen(tipo, id) { return `${tipo}:${id}`; }

function cantidadComprometidaOrigen(tipo, id) {
    let total = 0;
    bultosState.forEach(b => b.colores.forEach(c => {
        if (c.origen_tipo === tipo && c.origen_id === id) total += (parseFloat(c.cantidad) || 0);
    }));
    return total;
}

function disponibleRestanteOrigen(tipo, id, excluirTempColorId = null) {
    const o = origenesDisponiblesCache.find(x => x.origen_tipo === tipo && x.origen_id == id);
    if (!o) return 0;
    let comprometido = 0;
    bultosState.forEach(b => b.colores.forEach(c => {
        if (c.origen_tipo === tipo && c.origen_id === id && c.tempColorId !== excluirTempColorId) {
            comprometido += (parseFloat(c.cantidad) || 0);
        }
    }));
    return (parseFloat(o.disponible) || 0) - comprometido;
}

function totalBulto(bulto) {
    return bulto.colores.reduce((s, c) => s + (parseFloat(c.cantidad) || 0), 0);
}

function agregarBulto() {
    bultosState.push({ tempId: ++contadorBulto, colores: [] });
    agregarColorABulto(bultosState[bultosState.length - 1].tempId);
}

function esModoMezcla() {
    return !!(reglasEmpaquetadoActuales && reglasEmpaquetadoActuales.conversion_peso_a_unidad);
}

function agregarOrigenMezcla() {
    mezclaOrigenes.push({ tempId: ++contadorMezclaOrigen, origen_tipo: '', origen_id: 0, color_id: null, color_nombre: '', cantidad_kg: '' });
    renderMezcla();
}
function quitarOrigenMezcla(tempId) {
    mezclaOrigenes = mezclaOrigenes.filter(o => o.tempId !== tempId);
    renderMezcla();
}

function disponibleKgOrigen(tipo, id, excluirTempId = null) {
    const o = origenesDisponiblesCache.find(x => x.origen_tipo === tipo && x.origen_id == id);
    if (!o) return 0;
    const dispKgBase = o.disponible_kg !== undefined ? parseFloat(o.disponible_kg) : parseFloat(o.disponible);
    let comprometido = 0;
    mezclaOrigenes.forEach(m => {
        if (m.origen_tipo === tipo && m.origen_id === id && m.tempId !== excluirTempId) {
            comprometido += (parseFloat(m.cantidad_kg) || 0);
        }
    });
    return dispKgBase - comprometido;
}

function tocarSacoMezcla(origenTipo, origenId) {
    const o = origenesDisponiblesCache.find(x => x.origen_tipo === origenTipo && x.origen_id == origenId);
    if (!o) return;
    const restante = disponibleKgOrigen(origenTipo, origenId);
    if (restante <= 0.0001) return;

    let fila = mezclaOrigenes.find(m => m.origen_tipo === origenTipo && m.origen_id == origenId);
    if (!fila) {
        fila = { tempId: ++contadorMezclaOrigen, origen_tipo: origenTipo, origen_id: parseInt(origenId, 10), color_id: o.color_id, color_nombre: o.color_nombre, cantidad_kg: '' };
        mezclaOrigenes.push(fila);
    }
    const actual = parseFloat(fila.cantidad_kg) || 0;
    fila.cantidad_kg = Math.round((actual + restante) * 10000) / 10000;
    renderMezcla();
}

function tocarSacoBulto(origenTipo, origenId) {
    if (bultosState.length === 0) agregarBulto();
    let bulto = bultosState[bultosState.length - 1];

    const capacidad = unidadEmpaquetadoProductoActual?.equivalencia
        ? parseFloat(unidadEmpaquetadoProductoActual.equivalencia) : null;
    const granularidad = reglasEmpaquetadoActuales?.granularidad_color || 1;
    const incremento = granularidad > 1 ? granularidad : 1;

    if (capacidad !== null && totalBulto(bulto) >= capacidad - 0.0001) {
        agregarBulto();
        bulto = bultosState[bultosState.length - 1];
    }

    let fila = bulto.colores.find(c => c.origen_tipo === origenTipo && c.origen_id == origenId);
    const restanteOrigen = disponibleRestanteOrigen(origenTipo, origenId, fila ? fila.tempColorId : null);
    if (restanteOrigen <= 0.0001) return;

    const espacioBulto = capacidad !== null
        ? Math.max(0, capacidad - totalBulto(bulto) + (fila ? (parseFloat(fila.cantidad) || 0) : 0))
        : Infinity;
    const aAgregar = Math.min(incremento, restanteOrigen, espacioBulto);
    if (aAgregar <= 0) return;

    if (!fila) {
        const o = origenesDisponiblesCache.find(x => x.origen_tipo === origenTipo && x.origen_id == origenId);
        const filaVacia = bulto.colores.find(c => !c.origen_tipo);
        if (filaVacia) {
            filaVacia.origen_tipo = origenTipo;
            filaVacia.origen_id = parseInt(origenId, 10);
            filaVacia.color_id = o ? o.color_id : null;
            filaVacia.color_nombre = o ? o.color_nombre : '';
            filaVacia.cantidad = aAgregar;
        } else {
            bulto.colores.push({
                tempColorId: ++contadorColorRow, origen_tipo: origenTipo, origen_id: parseInt(origenId, 10),
                color_id: o ? o.color_id : null, color_nombre: o ? o.color_nombre : '', cantidad: aAgregar,
            });
        }
    } else {
        fila.cantidad = (parseFloat(fila.cantidad) || 0) + aAgregar;
    }

    renderBultos();
}

function actualizarOrigenMezcla(tempId, valorSelect) {
    const fila = mezclaOrigenes.find(m => m.tempId === tempId);
    if (!fila) return;
    if (!valorSelect) {
        fila.origen_tipo = ''; fila.origen_id = 0; fila.color_id = null; fila.color_nombre = '';
    } else {
        const [tipo, id] = valorSelect.split(':');
        const o = origenesDisponiblesCache.find(x => x.origen_tipo === tipo && x.origen_id == id);
        fila.origen_tipo = tipo; fila.origen_id = parseInt(id, 10);
        fila.color_id = o ? o.color_id : null;
        fila.color_nombre = o ? o.color_nombre : '';
    }
    renderMezcla();
}
function actualizarCantidadKgMezcla(tempId, valor) {
    const fila = mezclaOrigenes.find(m => m.tempId === tempId);
    if (fila) fila.cantidad_kg = valor;
    actualizarResumenMezcla();
}
function actualizarBolsasProducidas(valor) {
    bolsasProducidasValor = valor;
    actualizarResumenMezcla();
}

function actualizarResumenMezcla() {
    const kgTotal = mezclaOrigenes.reduce((s, m) => s + (parseFloat(m.cantidad_kg) || 0), 0);
    document.getElementById('mezclaKgTotal').textContent = formatearCantidadEmp(kgTotal);

    const pesoUnitG = reglasEmpaquetadoActuales?.peso_unitario_g;
    const elTeorico = document.getElementById('mezclaBolsasTeoricas');
    const elDiff = document.getElementById('mezclaDiferenciaBadge');
    const elPaquetes = document.getElementById('mezclaPaquetesEstimados');

    elPaquetes.textContent = formatearCantidadEmp((parseFloat(bolsasProducidasValor) || 0) / 24);

    const bolsas = parseFloat(bolsasProducidasValor) || 0;
    const frac = Math.min(bolsas, 24);
    document.getElementById('mezclaPaqueteFrac').textContent = `${Math.round(frac)} / 24`;
    document.getElementById('mezclaPaqueteBarra').style.width = ((frac / 24) * 100) + '%';

    if (pesoUnitG && kgTotal > 0) {
        const bolsasTeoricas = (kgTotal * 1000) / pesoUnitG / 144;
        elTeorico.textContent = formatearCantidadEmp(bolsasTeoricas);
        const declarado = parseFloat(bolsasProducidasValor) || 0;
        if (declarado > 0 && bolsasTeoricas > 0) {
            const diffPct = Math.abs(declarado - bolsasTeoricas) / bolsasTeoricas * 100;
            elDiff.style.display = 'inline-block';
            if (diffPct > 20) {
                elDiff.className = 'badge bg-danger';
                elDiff.textContent = `⚠ Difiere ${diffPct.toFixed(0)}%`;
            } else if (diffPct > 8) {
                elDiff.className = 'badge bg-warning text-dark';
                elDiff.textContent = `Difiere ${diffPct.toFixed(0)}%`;
            } else {
                elDiff.className = 'badge bg-success';
                elDiff.textContent = `Dentro de lo esperado`;
            }
        } else {
            elDiff.style.display = 'none';
        }
    } else {
        elTeorico.textContent = '-';
        elDiff.style.display = 'none';
    }
}

function renderSacosMezclaGrid() {
    const cont = document.getElementById('sacosMezclaGrid');
    if (!cont) return;
    if (origenesDisponiblesCache.length === 0) {
        cont.innerHTML = '<div class="text-muted" style="font-size:.9em;">No hay sacos disponibles para este producto.</div>';
        return;
    }
    cont.innerHTML = origenesDisponiblesCache.map(o => {
        const restante = disponibleKgOrigen(o.origen_tipo, o.origen_id);
        const enMezcla = mezclaOrigenes.find(m => m.origen_tipo === o.origen_tipo && m.origen_id == o.origen_id);
        const enMezclaKg = enMezcla ? (parseFloat(enMezcla.cantidad_kg) || 0) : 0;
        const agotado = restante <= 0.0001;
        const origenLabel = o.origen_tipo === 'ensamblaje' ? `Ensamblaje #${o.origen_id}` : `Producción #${o.origen_id}`;
        const hex = colorHexPara(o.color_nombre, o.color_hex);
        return `
        <button type="button" class="pc-saco-card ${agotado ? 'agotado' : ''}"
                onclick="tocarSacoMezcla('${o.origen_tipo}', ${o.origen_id})" title="Tocar para agregar a la mezcla">
            ${enMezclaKg > 0 ? `<span class="en-mezcla">${formatearCantidadEmp(enMezclaKg)} kg</span>` : ''}
            <div class="swatch" style="background:${hex};"></div>
            <p class="nombre">${o.color_nombre ?? 'Sin color'}</p>
            <p class="origen">${origenLabel}</p>
            <p class="disp">disp: ${formatearCantidadEmp(restante)} kg</p>
        </button>`;
    }).join('');
}

function renderSacosBultoGrid() {
    const cont = document.getElementById('sacosBultoGrid');
    if (!cont) return;
    if (origenesDisponiblesCache.length === 0) {
        cont.innerHTML = '<div class="text-muted" style="font-size:.9em;">No hay sacos disponibles para este producto.</div>';
        return;
    }
    cont.innerHTML = origenesDisponiblesCache.map(o => {
        const restante = disponibleRestanteOrigen(o.origen_tipo, o.origen_id);
        const comprometido = cantidadComprometidaOrigen(o.origen_tipo, o.origen_id);
        const agotado = restante <= 0.0001;
        const origenLabel = o.origen_tipo === 'ensamblaje' ? `Ensamblaje #${o.origen_id}` : `Producción #${o.origen_id}`;
        const hex = colorHexPara(o.color_nombre, o.color_hex);
        return `
        <button type="button" class="pc-saco-card ${agotado ? 'agotado' : ''}"
                onclick="tocarSacoBulto('${o.origen_tipo}', ${o.origen_id})" title="Tocar para agregar al paquete actual">
            ${comprometido > 0 ? `<span class="en-mezcla">${formatearCantidadEmp(comprometido)}</span>` : ''}
            <div class="swatch" style="background:${hex};"></div>
            <p class="nombre">${o.color_nombre ?? 'Sin color'}</p>
            <p class="origen">${origenLabel}</p>
            <p class="disp">disp: ${formatearCantidadEmp(restante)}</p>
        </button>`;
    }).join('');
}

function renderMezcla() {
    renderSacosMezclaGrid();
    const cont = document.getElementById('listaMezclaOrigenes');
    if (!cont) return;
    const filasConValor = mezclaOrigenes.filter(m => m.origen_tipo || parseFloat(m.cantidad_kg) > 0);
    if (filasConValor.length === 0) {
        cont.innerHTML = '<div class="text-muted" style="font-size:.9em;">Toca un saco a la izquierda para empezar a mezclar.</div>';
        actualizarResumenMezcla();
        return;
    }
    cont.innerHTML = filasConValor.map(m => {
        const hex = m.origen_tipo ? colorHexPara(m.color_nombre, (origenesDisponiblesCache.find(x => x.origen_tipo === m.origen_tipo && x.origen_id == m.origen_id) || {}).color_hex) : '#d3d1c7';
        const maxKg = m.origen_tipo ? disponibleKgOrigen(m.origen_tipo, m.origen_id, m.tempId) + (parseFloat(m.cantidad_kg) || 0) : null;
        const origenLabel = m.origen_tipo ? (m.origen_tipo === 'ensamblaje' ? `Ensamblaje #${m.origen_id}` : `Producción #${m.origen_id}`) : 'Sin asignar';
        return `
        <div class="pc-mezcla-fila">
            <span class="swatch-mini" style="background:${hex};"></span>
            <span class="nombre" title="${m.color_nombre ?? 'Sin color'} · ${origenLabel}">${m.color_nombre ?? 'Sin color'} · ${origenLabel}</span>
            <input type="number" min="0.0001" step="0.0001" ${maxKg !== null ? `max="${maxKg}"` : ''} class="form-control form-control-sm"
                   placeholder="Kg" value="${m.cantidad_kg}"
                   oninput="actualizarCantidadKgMezcla(${m.tempId}, this.value)">
            <button type="button" class="pc-bulto-remove" onclick="quitarOrigenMezcla(${m.tempId})" title="Quitar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>`;
    }).join('');
    actualizarResumenMezcla();
}

function inicializarBloqueFormulario() {
    const modoMezcla = esModoMezcla();
    document.getElementById('bloqueBultos').style.display = modoMezcla ? 'none' : 'block';
    document.getElementById('bloqueMezcla').style.display = modoMezcla ? 'block' : 'none';
    if (modoMezcla) {
        renderMezcla();
    } else {
        if (bultosState.length === 0) agregarBulto(); else renderBultos();
    }
}

function quitarBulto(tempId) {
    bultosState = bultosState.filter(b => b.tempId !== tempId);
    renderBultos();
}
function distribuirParejoBulto(bultoTempId) {
    const bulto = bultosState.find(b => b.tempId === bultoTempId);
    if (!bulto) return;
    const colores = bulto.colores.filter(c => c.origen_tipo && c.origen_id);
    if (colores.length < 2) return;
    const capacidad = unidadEmpaquetadoProductoActual?.equivalencia
        ? parseFloat(unidadEmpaquetadoProductoActual.equivalencia) : null;
    const granularidad = reglasEmpaquetadoActuales?.granularidad_color || 1;
    if (!capacidad) return;
    const base = Math.floor((capacidad / colores.length) / granularidad) * granularidad;
    colores.forEach(c => {
        const restante = disponibleRestanteOrigen(c.origen_tipo, c.origen_id, c.tempColorId) + (parseFloat(c.cantidad) || 0);
        c.cantidad = Math.min(base, restante);
    });
    renderBultos();
}
function agregarColorABulto(bultoTempId) {
    const bulto = bultosState.find(b => b.tempId === bultoTempId);
    if (!bulto) return;
    bulto.colores.push({ tempColorId: ++contadorColorRow, origen_tipo: '', origen_id: 0, color_id: null, color_nombre: '', cantidad: '' });
    renderBultos();
}
function quitarColorDeBulto(bultoTempId, tempColorId) {
    const bulto = bultosState.find(b => b.tempId === bultoTempId);
    if (!bulto) return;
    bulto.colores = bulto.colores.filter(c => c.tempColorId !== tempColorId);
    renderBultos();
}
function actualizarOrigenColor(bultoTempId, tempColorId, valorSelect) {
    const bulto = bultosState.find(b => b.tempId === bultoTempId);
    if (!bulto) return;
    const fila = bulto.colores.find(c => c.tempColorId === tempColorId);
    if (!fila) return;
    if (!valorSelect) {
        fila.origen_tipo = ''; fila.origen_id = 0; fila.color_id = null; fila.color_nombre = '';
    } else {
        const [tipo, id] = valorSelect.split(':');
        const o = origenesDisponiblesCache.find(x => x.origen_tipo === tipo && x.origen_id == id);
        fila.origen_tipo = tipo; fila.origen_id = parseInt(id, 10);
        fila.color_id = o ? o.color_id : null;
        fila.color_nombre = o ? o.color_nombre : '';
    }
    renderBultos();
}
function elegirColorEnFilaBulto(bultoTempId, tempColorId, origenTipo, origenId) {
    actualizarOrigenColor(bultoTempId, tempColorId, claveOrigen(origenTipo, origenId));
}
function actualizarCantidadColor(bultoTempId, tempColorId, valor) {
    const bulto = bultosState.find(b => b.tempId === bultoTempId);
    if (!bulto) return;
    const fila = bulto.colores.find(c => c.tempColorId === tempColorId);
    if (fila) fila.cantidad = valor;
    actualizarResumenBultos();
}

function renderBultos() {
    renderSacosBultoGrid();
    const cont = document.getElementById('listaBultos');
    const capacidad = unidadEmpaquetadoProductoActual?.equivalencia
        ? parseFloat(unidadEmpaquetadoProductoActual.equivalencia) : null;

    if (bultosState.length === 0) {
        cont.innerHTML = '<div class="text-muted" style="font-size:.9em;">Agrega al menos un bulto.</div>';
        actualizarResumenBultos();
        return;
    }

    cont.innerHTML = bultosState.map((b, idx) => {
        const total = totalBulto(b);
        const excedido = capacidad !== null && total > capacidad + 0.0001;
        const textoTotal = capacidad !== null
            ? `total: ${formatearCantidadEmp(total)} / ${formatearCantidadEmp(capacidad)}`
            : `total: ${formatearCantidadEmp(total)}`;

        const filasColores = b.colores.map(c => {
            const valorActual = c.origen_tipo ? claveOrigen(c.origen_tipo, c.origen_id) : '';

            const swatchesHtml = origenesDisponiblesCache.map(o => {
                const clave = claveOrigen(o.origen_tipo, o.origen_id);
                const restante = disponibleRestanteOrigen(o.origen_tipo, o.origen_id, c.tempColorId);
                const deshabilitado = restante <= 0.0001 && clave !== valorActual;
                const hex = colorHexPara(o.color_nombre, o.color_hex);
                const origenLabel = o.origen_tipo === 'ensamblaje' ? `Ensamblaje #${o.origen_id}` : `Producción #${o.origen_id}`;
                return `<button type="button" class="pc-swatch-chip ${clave === valorActual ? 'activo' : ''} ${deshabilitado ? 'agotado' : ''}"
                    style="background:${hex};" title="${o.color_nombre ?? 'Sin color'} · ${origenLabel} (disp: ${formatearCantidadEmp(restante)})"
                    onclick="elegirColorEnFilaBulto(${b.tempId}, ${c.tempColorId}, '${o.origen_tipo}', ${o.origen_id})"></button>`;
            }).join('');

            const opciones = origenesDisponiblesCache.map(o => {
                const clave = claveOrigen(o.origen_tipo, o.origen_id);
                const restante = disponibleRestanteOrigen(o.origen_tipo, o.origen_id, c.tempColorId);
                const deshabilitado = restante <= 0.0001 && clave !== valorActual;
                const origenLabel = o.origen_tipo === 'ensamblaje' ? `Ensamblaje #${o.origen_id}` : `Producción #${o.origen_id}`;
                return `<option value="${clave}" ${clave === valorActual ? 'selected' : ''} ${deshabilitado ? 'disabled' : ''}>
                    ${o.color_nombre ?? 'Sin color'} · ${origenLabel} (disp: ${formatearCantidadEmp(restante)})
                </option>`;
            }).join('');

            const restanteOrigenActual = c.origen_tipo ? disponibleRestanteOrigen(c.origen_tipo, c.origen_id, c.tempColorId) : null;
            const espacioRestanteBulto = capacidad !== null ? Math.max(0, capacidad - (total - (parseFloat(c.cantidad) || 0))) : null;
            const topes = [restanteOrigenActual, espacioRestanteBulto].filter(v => v !== null && v !== undefined);
            const maxInput = topes.length > 0 ? Math.min(...topes) : null;

            return `
            <div class="mb-2">
                <div class="pc-swatch-picker">${swatchesHtml}</div>
                <div class="pc-color-row">
                    <select class="form-select form-select-sm" onchange="actualizarOrigenColor(${b.tempId}, ${c.tempColorId}, this.value)">
                        <option value="">Selecciona color/origen...</option>
                        ${opciones}
                    </select>
                    <input type="number" min="0.0001" step="0.0001" ${maxInput !== null ? `max="${maxInput}"` : ''} class="form-control form-control-sm"
                           placeholder="Cantidad" value="${c.cantidad}"
                           oninput="actualizarCantidadColor(${b.tempId}, ${c.tempColorId}, this.value)">
                    <button type="button" class="pc-bulto-remove" onclick="quitarColorDeBulto(${b.tempId}, ${c.tempColorId})" title="Quitar color">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>`;
        }).join('');

        return `
        <div class="pc-bulto-card" style="${excedido ? 'border-color:#c94a4a;' : ''}">
            <div class="pc-bulto-card-head" style="${excedido ? 'color:#c94a4a;' : ''}">
                <span>Bulto ${idx + 1} — ${textoTotal}${excedido ? ' ⚠ excede la capacidad' : ''}</span>
                <div>
                    ${reglasEmpaquetadoActuales?.modo_distribucion_color === 'uniforme' && b.colores.length > 1 ? `
                        <button type="button" class="btn btn-sm btn-outline-primary" style="margin-right:6px;" onclick="distribuirParejoBulto(${b.tempId})">
                            <i class="fa-solid fa-scale-balanced"></i> Repartir parejo
                        </button>` : ''}
                    <button type="button" class="pc-bulto-remove" onclick="quitarBulto(${b.tempId})" title="Quitar bulto">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>
            ${filasColores}
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="agregarColorABulto(${b.tempId})">
                <i class="fa-solid fa-plus"></i> Agregar color a este bulto
            </button>
        </div>`;
    }).join('');
    actualizarResumenBultos();
}

function actualizarResumenBultos() {
    const total = bultosState.reduce((s, b) => s + totalBulto(b), 0);
    document.getElementById('bultosCount').textContent = bultosState.length;
    document.getElementById('bultosTotal').textContent = formatearCantidadEmp(total);
}

function obtenerBultosJsonEmp() {
    const bultos = bultosState.map(b => ({
        colores: b.colores
            .filter(c => c.origen_tipo && c.origen_id && parseFloat(c.cantidad) > 0)
            .map(c => ({
                origen_tipo: c.origen_tipo, origen_id: c.origen_id,
                color_id: c.color_id, color_nombre: c.color_nombre,
                cantidad: parseFloat(c.cantidad),
            })),
    })).filter(b => b.colores.length > 0);
    return JSON.stringify(bultos);
}

// =============================================================================
// FORMULARIO: crear registro
// =============================================================================

document.getElementById('formEstacionArmado').addEventListener('submit', async function (e) {
    e.preventDefault();

    if (estOperariosSeleccionados.length === 0) {
        Swal.fire('Falta información', 'Selecciona al menos un operario.', 'warning');
        return;
    }

    let params;

    if (esModoMezcla()) {
        const origenesValidos = mezclaOrigenes.filter(m => m.origen_tipo && m.origen_id && parseFloat(m.cantidad_kg) > 0);
        if (origenesValidos.length === 0) {
            Swal.fire('Falta información', 'Toca al menos un saco/color y verifica que tenga kg mayor a 0.', 'warning');
            return;
        }
        const bolsas = parseInt(bolsasProducidasValor, 10);
        if (!bolsas || bolsas <= 0) {
            Swal.fire('Falta información', 'Indica cuántas bolsas se produjeron.', 'warning');
            return;
        }
        params = {
            producto_id: estacionProductoIdActual,
            operarios: JSON.stringify(estOperariosSeleccionados),
            sucursal_id: document.getElementById('est_sucursal_id').value,
            mezcla_origenes: JSON.stringify(origenesValidos.map(m => ({
                origen_tipo: m.origen_tipo, origen_id: m.origen_id,
                color_id: m.color_id, color_nombre: m.color_nombre,
                cantidad_kg: parseFloat(m.cantidad_kg),
            }))),
            bolsas_producidas: bolsas,
        };
    } else {
        const bultosJson = obtenerBultosJsonEmp();
        const bultosParsed = JSON.parse(bultosJson);
        if (bultosParsed.length === 0) {
            Swal.fire('Falta información', 'Agrega al menos un bulto con un color/origen y cantidad mayor a 0.', 'warning');
            return;
        }

        const capacidad = unidadEmpaquetadoProductoActual?.equivalencia
            ? parseFloat(unidadEmpaquetadoProductoActual.equivalencia) : null;
        if (capacidad !== null) {
            const totalesExcedidos = bultosParsed
                .map((b, i) => ({ idx: i + 1, total: b.colores.reduce((s, c) => s + c.cantidad, 0) }))
                .filter(b => b.total > capacidad + 0.0001);
            if (totalesExcedidos.length > 0) {
                const detalle = totalesExcedidos.map(b => `Bulto ${b.idx}: ${formatearCantidadEmp(b.total)}`).join(', ');
                Swal.fire('Bulto excede la capacidad',
                    `${detalle} — la capacidad de ${unidadEmpaquetadoProductoActual.nombre} es ${formatearCantidadEmp(capacidad)}. Ajusta las cantidades.`,
                    'warning');
                return;
            }
        }

        const granularidad = reglasEmpaquetadoActuales?.granularidad_color || 1;
        if (granularidad > 1) {
            for (const b of bultosParsed) {
                for (const c of b.colores) {
                    if (c.cantidad % granularidad !== 0) {
                        Swal.fire('Cantidad inválida', `Cada color debe ser múltiplo de ${granularidad} (docena) — revisa las cantidades.`, 'warning');
                        return;
                    }
                }
            }
        }

        if (reglasEmpaquetadoActuales?.modo_distribucion_color === 'uniforme') {
            for (let i = 0; i < bultosParsed.length; i++) {
                const b = bultosParsed[i];
                const n = b.colores.length;
                if (n < 2) continue;
                const total = b.colores.reduce((s, c) => s + c.cantidad, 0);
                if (total % n !== 0) {
                    Swal.fire('Reparto no uniforme posible',
                        `El bulto ${i + 1} tiene ${formatearCantidadEmp(total)} unidades entre ${n} colores — no se puede repartir exactamente parejo.`,
                        'warning');
                    return;
                }
                const esperado = total / n;
                if (granularidad > 1 && esperado % granularidad !== 0) {
                    Swal.fire('Reparto no uniforme posible',
                        `El bulto ${i + 1}: repartido parejo tocarían ${formatearCantidadEmp(esperado)} unidades por color, pero cada color debe ser múltiplo de ${granularidad}.`,
                        'warning');
                    return;
                }
                const desparejo = b.colores.some(c => c.cantidad !== esperado);
                if (desparejo) {
                    Swal.fire('Reparto desparejo', `El bulto ${i + 1} debe repartirse parejo entre colores (${esperado} c/u).`, 'warning');
                    return;
                }
            }
        }

        params = {
            producto_id: estacionProductoIdActual,
            operarios: JSON.stringify(estOperariosSeleccionados),
            sucursal_id: document.getElementById('est_sucursal_id').value,
            bultos: bultosJson,
        };
    }

    const json = await llamarEmpaquetado('CREAREMPAQUETADO', params);

    if (json.success) {
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: json.message, showConfirmButton: false, timer: 2200 });

        const productoRecienUsado = estacionProductoIdActual;
        await Promise.all([
            cargarPendientesEmpaquetado(),
            cargarMisRegistros(),
        ]);
        estacionProductoIdActual = 0;
        await cargarEstacionParaProducto(productoRecienUsado);
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});
</script>
</body>
</html>