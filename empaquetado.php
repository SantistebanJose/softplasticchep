<?php
$pageTitle    = 'Empaquetado';
$pageSubtitle = 'Embolsado y agrupado de productos terminados';
$activePage = 'empaquetado';

include("header.php");
?>

<!--
    empaquetado.php

    MODELO DE UI:
    1) Grid de "Ensamblajes pendientes de empaquetar" (ensamblajes finalizados
       con saldo por embolsar), igual patrón de cards que produccion.php /
       ensamblaje.php.
    2) Al tocar "Empaquetar" se abre un WORKSPACE (modal xl) para ESE
       ensamblaje: tabs por cada nivel configurado del producto (Bolsa,
       Gruesa, ...). El nivel 1 tiene un formulario simple (cantidad+color).
       Los niveles > 1 muestran un grid seleccionable de las unidades sueltas
       del nivel anterior para agruparlas.
    3) Panel derecho: árbol de TODAS las unidades del ensamblaje (sueltas y
       ya consumidas), agrupado por nivel, con acciones Eliminar/Desagrupar.
    4) Modal aparte "Configurar niveles" para dar de alta Bolsa/Gruesa/etc.
       por producto (producto_empaquetado_nivel), accesible desde el botón
       superior. Es infrecuente (se configura una vez por producto).

    SUPUESTO (igual que en el controlador): la vista view_ensamblajes_
    pendientes_empaquetado expone producto_id, producto_codigo,
    producto_descripcion, cantidad_peso_kg, cantidad_piezas_estimada,
    cantidad_ya_embolsada_nivel1, cantidad_sugerida_pendiente, fin.
-->

<style>
:root{
    --emp-1:#2F6FED; --emp-1-bg:#EAF0FE;
    --emp-2:#E23744; --emp-2-bg:#FCEAEC;
    --emp-3:#16A34A; --emp-3-bg:#E8F7EE;
    --emp-4:#D97706; --emp-4-bg:#FDF1E0;
    --emp-5:#7C3AED; --emp-5-bg:#F1EAFD;
    --emp-6:#0E9488; --emp-6-bg:#E2F5F3;
}

/* ── Grid de pendientes ── */
.pc-emp-grid{
    display:grid; grid-template-columns:repeat(auto-fill, minmax(300px,1fr));
    gap:14px; margin-top:4px;
}
.pc-emp-card{
    border:1px solid #e7e4dd; border-radius:14px; background:#fff;
    overflow:hidden; display:flex; flex-direction:column;
    transition:box-shadow .12s ease, transform .12s ease;
}
.pc-emp-card:hover{ box-shadow:0 6px 16px rgba(0,0,0,.08); transform:translateY(-1px); }
.pc-emp-card-head{
    padding:10px 14px; background:#fdfcfa; border-bottom:1px solid #eee7db;
    display:flex; justify-content:space-between; align-items:flex-start; gap:8px;
}
.pc-emp-card-head .titulo{ display:flex; flex-direction:column; gap:2px; min-width:0; }
.pc-emp-card-head .id{ font-size:.72em; color:#9a9585; font-weight:600; }
.pc-emp-card-head .producto-titulo{ font-weight:700; font-size:.95em; }
.pc-emp-card-body{ padding:12px 14px; display:grid; grid-template-columns:1fr 1fr; gap:8px 12px; flex:1; }
.pc-emp-field{ min-width:0; }
.pc-emp-field .lbl{ font-size:.68em; text-transform:uppercase; letter-spacing:.03em; color:#9a9585; display:block; margin-bottom:1px; }
.pc-emp-field .val{ font-size:.85em; color:#3a3730; font-weight:600; overflow-wrap:break-word; }
.pc-emp-field.span-2{ grid-column:1/-1; }
.pc-emp-field .val.pendiente-alta{ color:#D97706; }
.pc-emp-field .val.pendiente-cero{ color:#16A34A; }
.pc-emp-card-foot{
    padding:8px 14px; border-top:1px solid #eee7db; background:#fffefb;
    display:flex; justify-content:flex-end; align-items:center; gap:6px; flex-wrap:wrap;
}
.pc-emp-empty{ text-align:center; color:#9a9585; padding:40px 12px; grid-column:1/-1; }

.pc-btn-empaquetar{
    padding:7px 12px; font-size:.8em; border-radius:8px; border:1px solid #2F6FED;
    background:#EAF0FE; color:#2F6FED; font-weight:700; display:inline-flex; align-items:center; gap:6px;
    transition:.12s ease;
}
.pc-btn-empaquetar:hover{ background:#2F6FED; color:#fff; }

/* ── Workspace: tabs de nivel + panel de acción + árbol ── */
.pc-emp-layout{
    display:grid; grid-template-columns:1.2fr 1fr; gap:16px; align-items:start;
}
@media (max-width: 960px){ .pc-emp-layout{ grid-template-columns:1fr; } }

.pc-emp-panel, .pc-emp-arbol-panel{
    border:1px solid #e7e4dd; border-radius:14px; background:#fdfcfa; overflow:hidden;
}
.pc-emp-panel-head, .pc-emp-arbol-panel-head{
    padding:10px 14px; border-bottom:1px solid #eee7db; display:flex;
    justify-content:space-between; align-items:center; background:#fffefb;
}
.pc-emp-panel-head h6, .pc-emp-arbol-panel-head h6{ margin:0; font-weight:700; font-size:.95em; }

.pc-emp-sugerido-bar{
    padding:10px 14px; background:linear-gradient(0deg,#fffaf0,#fffefb);
    border-bottom:1px solid #eee7db; font-size:.82em; color:#5c5947;
}
.pc-emp-sugerido-bar b{ color:#3a3730; }

.pc-tabs-nivel{ display:flex; gap:6px; padding:10px 12px 0 12px; flex-wrap:wrap; }
.pc-tab-nivel{
    flex:1; min-width:90px; text-align:center; padding:7px 10px; font-size:.82em; font-weight:700;
    border:1px solid #e2ddcd; border-radius:8px; background:#fff; color:#8a8578; cursor:pointer;
    transition:.12s ease;
}
.pc-tab-nivel.activa{ background:#2F6FED; border-color:#2F6FED; color:#fff; }

.pc-emp-panel-body{ padding:12px 14px; }

/* Form nivel 1 */
.pc-form-nivel1 .row > div{ margin-bottom:10px; }

/* Grid seleccionable niveles > 1 */
.pc-mat-grid{
    display:grid; grid-template-columns:repeat(auto-fill, minmax(140px,1fr));
    gap:10px; max-height:300px; overflow-y:auto; padding-bottom:4px;
}
.pc-mat-card{
    position:relative; border:2px solid #eae6da; border-radius:12px; background:#fff;
    padding:10px 10px 8px 10px; cursor:pointer; text-align:left;
    transition:transform .1s ease, box-shadow .1s ease, border-color .1s ease;
}
.pc-mat-card:hover{ transform:translateY(-2px); box-shadow:0 6px 14px rgba(0,0,0,.07); }
.pc-mat-card.seleccionada{ border-color:#2F6FED; background:#EAF0FE; }
.pc-mat-card .pellet{
    width:28px; height:28px; border-radius:9px; display:flex; align-items:center; justify-content:center;
    background:var(--card-bg,#EAF0FE); color:var(--card-color,#2F6FED); font-size:.9em; margin-bottom:6px;
}
.pc-mat-card .nombre{ font-weight:600; font-size:.82em; line-height:1.2; }
.pc-mat-card .meta{ font-size:.7em; color:#8a8578; margin-top:3px; display:block; }
.pc-mat-card .check-ok{ position:absolute; top:8px; right:8px; color:#2F6FED; display:none; }
.pc-mat-card.seleccionada .check-ok{ display:block; }
.pc-mat-empty{ grid-column:1/-1; text-align:center; color:#9a9585; font-size:.85em; padding:20px 6px; }

.pc-emp-panel-footer{
    padding:10px 14px; border-top:1px solid #eee7db; background:#fffefb;
    display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap;
}

/* Árbol de unidades */
.pc-arbol-nivel{ padding:10px 14px; border-bottom:1px dashed #eee2c8; }
.pc-arbol-nivel:last-child{ border-bottom:none; }
.pc-arbol-nivel-titulo{ font-size:.75em; text-transform:uppercase; letter-spacing:.03em; color:#9a9585; font-weight:700; margin-bottom:6px; }
.pc-arbol-item{
    display:flex; gap:8px; align-items:flex-start; padding:6px 0;
}
.pc-arbol-item.consumida{ opacity:.45; }
.pc-arbol-item .pellet-sm{
    width:24px; height:24px; border-radius:7px; flex:0 0 auto; display:flex; align-items:center; justify-content:center;
    background:var(--card-bg,#EAF0FE); color:var(--card-color,#2F6FED); font-size:.75em; margin-top:2px;
}
.pc-arbol-item .cuerpo{ flex:1; min-width:0; }
.pc-arbol-item .nombre{ font-weight:600; font-size:.82em; }
.pc-arbol-item .meta{ font-size:.7em; color:#8a8578; margin-top:1px; }
.pc-arbol-item .acciones{ display:flex; gap:4px; }
.pc-arbol-item .acciones button{ border:none; background:none; font-size:.78em; }
.pc-arbol-empty{ text-align:center; color:#9a9585; font-size:.85em; padding:26px 12px; }

/* Modal configurar niveles */
.pc-nivel-row{
    display:flex; align-items:center; gap:8px; padding:8px 10px; border:1px solid #eae6da;
    border-radius:10px; margin-bottom:8px; background:#fff;
}
.pc-nivel-row .num{
    width:26px; height:26px; border-radius:50%; background:#EAF0FE; color:#2F6FED; font-weight:700;
    font-size:.78em; display:flex; align-items:center; justify-content:center; flex:0 0 auto;
}
.pc-nivel-row .info{ flex:1; min-width:0; }
.pc-nivel-row .info .nom{ font-weight:600; font-size:.85em; }
.pc-nivel-row .info .meta{ font-size:.72em; color:#8a8578; }
</style>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Empaquetado</h2>
        <button class="pc-btn pc-btn-secondary" onclick="abrirModalConfigNiveles()">
            <i class="fa-solid fa-sliders"></i> Configurar niveles por producto
        </button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <br>
        <input type="text" id="femp_texto" class="form-control" style="max-width:260px"
               placeholder="Buscar por producto...">
        <div class="form-check d-flex align-items-center gap-2" style="margin-left:4px;">
            <input class="form-check-input" type="checkbox" id="femp_solo_saldo" checked>
            <label class="form-check-label" for="femp_solo_saldo" style="font-size:.85em;">Solo con saldo pendiente</label>
        </div>
    </div>

    <div class="pc-emp-grid" id="gridEmpaquetado">
        <div class="pc-emp-empty">Cargando...</div>
    </div>
</div>

<!-- Workspace de empaquetado (por ensamblaje) -->
<div class="modal fade" id="modalWorkspaceEmp" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="workspaceEmpTitulo">Empaquetar</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <div class="pc-emp-layout">
            <div class="pc-emp-panel">
                <div class="pc-emp-panel-head">
                    <h6><i class="fa-solid fa-box-open"></i> Registrar</h6>
                </div>
                <div class="pc-emp-sugerido-bar" id="workspaceSugeridoBar">Cargando sugerido...</div>
                <div class="pc-tabs-nivel" id="workspaceTabsNiveles"></div>
                <div class="pc-emp-panel-body" id="workspacePanelBody">
                    <div class="pc-mat-empty">Cargando niveles del producto...</div>
                </div>
                <div class="pc-emp-panel-footer" id="workspacePanelFooter" style="display:none;"></div>
            </div>

            <div class="pc-emp-arbol-panel">
                <div class="pc-emp-arbol-panel-head">
                    <h6><i class="fa-solid fa-diagram-project"></i> Unidades de este armado</h6>
                    <button type="button" class="pc-icon-btn" onclick="refrescarWorkspace()" title="Actualizar">
                        <i class="fa-solid fa-rotate"></i>
                    </button>
                </div>
                <div id="workspaceArbol">
                    <div class="pc-arbol-empty">Aún no hay unidades registradas.</div>
                </div>
            </div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal configurar niveles por producto -->
<div class="modal fade" id="modalConfigNiveles" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Configurar niveles de empaquetado</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <label class="form-label">Producto</label>
        <select class="form-select mb-3" id="cfg_producto_id" onchange="cargarNivelesConfig()">
            <option value="">Selecciona un producto...</option>
        </select>

        <div id="cfg_niveles_lista">
            <div class="pc-mat-empty">Selecciona un producto para ver sus niveles.</div>
        </div>

        <hr>

        <h6 class="mb-2">Agregar / editar nivel</h6>
        <form id="formNivelConfig">
            <input type="hidden" id="cfg_nivel_id" value="0">
            <div class="row">
                <div class="col-md-3 mb-2">
                    <label class="form-label">Nivel (orden) *</label>
                    <input type="number" min="1" class="form-control" id="cfg_nivel_numero" required>
                </div>
                <div class="col-md-5 mb-2">
                    <label class="form-label">Nombre *</label>
                    <input type="text" class="form-control" id="cfg_nivel_nombre" placeholder="Bolsa, Gruesa..." required>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">Cantidad por unidad *</label>
                    <input type="number" min="0.0001" step="0.0001" class="form-control" id="cfg_nivel_cantidad" required>
                </div>
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="cfg_nivel_variado" checked>
                <label class="form-check-label" for="cfg_nivel_variado">
                    Admite variado (mezcla de colores) en este nivel
                </label>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Guardar nivel</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="limpiarFormNivelConfig()">Cancelar edición</button>
            </div>
        </form>

      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const CONTROLADOR_EMPAQUETADO = 'controllers/clssEmpaquetado.php';
const modalWorkspaceEmp = new bootstrap.Modal(document.getElementById('modalWorkspaceEmp'));
const modalConfigNiveles = new bootstrap.Modal(document.getElementById('modalConfigNiveles'));

// ── Estado del workspace ──────────────────────────────────────────────────
let wkEnsamblajeId = 0;
let wkProductoId = 0;
let wkNiveles = [];              // niveles configurados del producto, ordenados
let wkTabActiva = null;          // nivel_config_id activo
let wkUnidadesArbol = [];        // todas las unidades del ensamblaje (LISTARUNIDADESENSAMBLAJE)
let wkPendienteInfo = null;      // fila de view_ensamblajes_pendientes_empaquetado
let wkSeleccionHijas = new Set();
let wkColoresCache = null;
let wkOperariosCache = null;
let productosConfigCache = null;

document.addEventListener('DOMContentLoaded', () => {
    cargarPendientesEmpaquetado();

    let debounceTimer = null;
    document.getElementById('femp_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarPendientesEmpaquetado, 350);
    });
    document.getElementById('femp_solo_saldo').addEventListener('change', cargarPendientesEmpaquetado);
});

// ── Llamada genérica al controlador ─────────────────────────────────────────
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
        throw new Error(`El servidor no devolvió JSON válido (accion=${accion}). Revisa la consola.`);
    }
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

const PALETA_EMP = [
    { color: '#2F6FED', bg: '#EAF0FE' },
    { color: '#E23744', bg: '#FCEAEC' },
    { color: '#16A34A', bg: '#E8F7EE' },
    { color: '#D97706', bg: '#FDF1E0' },
    { color: '#7C3AED', bg: '#F1EAFD' },
    { color: '#0E9488', bg: '#E2F5F3' },
];
function estiloPorNombreEmp(nombre) {
    let hash = 0;
    const str = nombre || '';
    for (let i = 0; i < str.length; i++) hash = (hash * 31 + str.charCodeAt(i)) >>> 0;
    return PALETA_EMP[hash % PALETA_EMP.length];
}

// =============================================================================
// GRID DE PENDIENTES
// =============================================================================

async function cargarPendientesEmpaquetado() {
    const params = {
        texto: document.getElementById('femp_texto').value.trim(),
        solo_con_saldo: document.getElementById('femp_solo_saldo').checked ? '1' : '0',
    };
    const json = await llamarEmpaquetado('LISTARENSAMBLAJESPENDIENTESEMPAQUETADO', params);
    const grid = document.getElementById('gridEmpaquetado');

    if (!json.success) {
        grid.innerHTML = `<div class="pc-emp-empty">${json.message}</div>`;
        return;
    }

    const filas = json.ensamblajes || [];
    if (filas.length === 0) {
        grid.innerHTML = '<div class="pc-emp-empty">No hay ensamblajes pendientes de empaquetar.</div>';
        return;
    }

    grid.innerHTML = filas.map(e => {
        const pendiente = e.cantidad_sugerida_pendiente;
        const pendienteTxt = pendiente === null ? 'Sin peso unitario configurado' : `${formatearCantidadEmp(pendiente)} piezas`;
        const claseAlerta = pendiente === null ? '' : (Number(pendiente) > 0 ? 'pendiente-alta' : 'pendiente-cero');
        return `
        <div class="pc-emp-card">
            <div class="pc-emp-card-head">
                <div class="titulo">
                    <span class="id">Ensamblaje #${e.ensamblaje_id}</span>
                    <span class="producto-titulo">${e.producto_codigo ?? ''} - ${e.producto_descripcion ?? '-'}</span>
                </div>
            </div>
            <div class="pc-emp-card-body">
                <div class="pc-emp-field">
                    <span class="lbl">Peso ensamblado</span>
                    <span class="val">${formatearCantidadEmp(e.cantidad_peso_kg)} kg</span>
                </div>
                <div class="pc-emp-field">
                    <span class="lbl">Piezas estimadas</span>
                    <span class="val">${formatearCantidadEmp(e.cantidad_piezas_estimada)}</span>
                </div>
                <div class="pc-emp-field">
                    <span class="lbl">Ya embolsado</span>
                    <span class="val">${formatearCantidadEmp(e.cantidad_ya_embolsada_nivel1)}</span>
                </div>
                <div class="pc-emp-field">
                    <span class="lbl">Pendiente sugerido</span>
                    <span class="val ${claseAlerta}">${pendienteTxt}</span>
                </div>
                <div class="pc-emp-field span-2">
                    <span class="lbl">Finalizado</span>
                    <span class="val">${formatearFechaHoraLegibleEmp(e.fin)}</span>
                </div>
            </div>
            <div class="pc-emp-card-foot">
                <button type="button" class="pc-btn-empaquetar"
                        onclick="abrirWorkspace(${e.ensamblaje_id}, ${e.producto_id}, '${(e.producto_codigo ?? '').replace(/'/g, "\\'")} - ${(e.producto_descripcion ?? '').replace(/'/g, "\\'")}')">
                    <i class="fa-solid fa-box"></i> Empaquetar
                </button>
            </div>
        </div>`;
    }).join('');
}

// =============================================================================
// WORKSPACE DE EMPAQUETADO
// =============================================================================

async function obtenerColoresEmp() {
    if (wkColoresCache) return wkColoresCache;
    const json = await llamarEmpaquetado('BUSCARCOLORES', { texto: '' });
    wkColoresCache = json.success ? json.colores : [];
    return wkColoresCache;
}
async function obtenerOperariosEmp() {
    if (wkOperariosCache) return wkOperariosCache;
    const json = await llamarEmpaquetado('BUSCAROPERARIOS');
    wkOperariosCache = json.success ? json.operario : [];
    return wkOperariosCache;
}

async function abrirWorkspace(ensamblajeId, productoId, productoLabel) {
    wkEnsamblajeId = ensamblajeId;
    wkProductoId = productoId;
    wkSeleccionHijas = new Set();

    document.getElementById('workspaceEmpTitulo').textContent = `Empaquetar #${ensamblajeId} — ${productoLabel}`;
    document.getElementById('workspaceTabsNiveles').innerHTML = '';
    document.getElementById('workspacePanelBody').innerHTML = '<div class="pc-mat-empty"><i class="fa-solid fa-spinner fa-spin"></i> Cargando...</div>';
    document.getElementById('workspacePanelFooter').style.display = 'none';
    document.getElementById('workspaceArbol').innerHTML = '<div class="pc-arbol-empty">Cargando...</div>';
    document.getElementById('workspaceSugeridoBar').textContent = 'Cargando sugerido...';

    modalWorkspaceEmp.show();

    const [nivelesJson] = await Promise.all([
        llamarEmpaquetado('OBTENERNIVELESPRODUCTO', { producto_id: productoId }),
    ]);

    wkNiveles = nivelesJson.success ? (nivelesJson.niveles || []) : [];

    if (wkNiveles.length === 0) {
        document.getElementById('workspacePanelBody').innerHTML =
            `<div class="pc-mat-empty">Este producto no tiene niveles de empaquetado configurados.
             <br><br><button type="button" class="btn btn-sm btn-outline-primary" onclick="modalWorkspaceEmp.hide(); abrirModalConfigNiveles(${productoId});">
             Configurar ahora</button></div>`;
        return;
    }

    renderTabsNiveles();
    wkTabActiva = wkNiveles[0].id;
    await Promise.all([cambiarTabNivel(wkTabActiva), refrescarSugerido(), cargarArbolUnidades()]);
}

function renderTabsNiveles() {
    const cont = document.getElementById('workspaceTabsNiveles');
    cont.innerHTML = wkNiveles.map(n => `
        <div class="pc-tab-nivel ${n.id == wkTabActiva ? 'activa' : ''}" id="tab_nivel_${n.id}"
             onclick="cambiarTabNivel(${n.id})">
            ${n.nombre_nivel}
        </div>
    `).join('');
}

async function refrescarSugerido() {
    const json = await llamarEmpaquetado('LISTARENSAMBLAJESPENDIENTESEMPAQUETADO', { texto: '', solo_con_saldo: '0' });
    const bar = document.getElementById('workspaceSugeridoBar');
    if (!json.success) { bar.textContent = ''; return; }
    const fila = (json.ensamblajes || []).find(e => e.ensamblaje_id == wkEnsamblajeId);
    wkPendienteInfo = fila || null;
    if (!fila) { bar.textContent = ''; return; }
    if (fila.cantidad_sugerida_pendiente === null) {
        bar.innerHTML = `Peso ensamblado: <b>${formatearCantidadEmp(fila.cantidad_peso_kg)} kg</b> — este producto no tiene peso unitario configurado, no hay sugerido automático.`;
    } else {
        bar.innerHTML = `Peso ensamblado: <b>${formatearCantidadEmp(fila.cantidad_peso_kg)} kg</b> ·
            Piezas estimadas: <b>${formatearCantidadEmp(fila.cantidad_piezas_estimada)}</b> ·
            Ya embolsado: <b>${formatearCantidadEmp(fila.cantidad_ya_embolsada_nivel1)}</b> ·
            Pendiente sugerido: <b>${formatearCantidadEmp(fila.cantidad_sugerida_pendiente)}</b>`;
    }
}

async function cambiarTabNivel(nivelConfigId) {
    wkTabActiva = nivelConfigId;
    wkSeleccionHijas = new Set();
    document.querySelectorAll('.pc-tab-nivel').forEach(el => el.classList.remove('activa'));
    const tabEl = document.getElementById('tab_nivel_' + nivelConfigId);
    if (tabEl) tabEl.classList.add('activa');

    const nivel = wkNiveles.find(n => n.id == nivelConfigId);
    if (!nivel) return;

    if (Number(nivel.nivel) === 1) {
        await renderPanelNivel1(nivel);
    } else {
        await renderPanelNivelSuperior(nivel);
    }
}

// ── Nivel 1: formulario directo (cantidad sugerida editable + color) ──────
async function renderPanelNivel1(nivel) {
    const body = document.getElementById('workspacePanelBody');
    const footer = document.getElementById('workspacePanelFooter');
    footer.style.display = 'none';

    const [colores, operarios] = await Promise.all([obtenerColoresEmp(), obtenerOperariosEmp()]);
    const sugerido = wkPendienteInfo && wkPendienteInfo.cantidad_sugerida_pendiente !== null
        ? wkPendienteInfo.cantidad_sugerida_pendiente
        : (nivel.cantidad_por_unidad ?? '');

    body.innerHTML = `
        <div class="pc-form-nivel1">
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Cantidad de piezas *</label>
                    <input type="number" min="0.0001" step="0.0001" class="form-control" id="n1_cantidad" value="${sugerido}">
                    <div class="form-text">Sugerido según lo pendiente por embolsar. Puedes corregirlo.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Color ${nivel.permite_variado ? '(opcional = variado)' : '*'}</label>
                    <select class="form-select" id="n1_color">
                        <option value="">${nivel.permite_variado ? 'Variado / mezclado' : 'Selecciona un color...'}</option>
                        ${colores.map(c => `<option value="${c.id}">${c.nombre}</option>`).join('')}
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Operario</label>
                    <select class="form-select" id="n1_operario">
                        <option value="">Selecciona...</option>
                        ${operarios.map(o => `<option value="${o.id}">${o.nombre_completo}</option>`).join('')}
                    </select>
                </div>
            </div>
        </div>
    `;

    footer.style.display = 'flex';
    footer.innerHTML = `
        <span class="form-text mb-0">Se registra como "${nivel.nombre_nivel}" suelta, disponible para agrupar en el siguiente nivel.</span>
        <button type="button" class="btn btn-primary btn-sm" onclick="registrarUnidadNivel1(${nivel.id})">
            <i class="fa-solid fa-plus"></i> Registrar ${nivel.nombre_nivel}
        </button>
    `;
}

async function registrarUnidadNivel1(nivelConfigId) {
    const cantidad = document.getElementById('n1_cantidad').value;
    const colorId = document.getElementById('n1_color').value;
    const operarioId = document.getElementById('n1_operario').value;

    const json = await llamarEmpaquetado('CREARUNIDADEMPAQUETADO', {
        ensamblaje_id: wkEnsamblajeId,
        nivel_config_id: nivelConfigId,
        cantidad_contenida: cantidad,
        color_id: colorId,
        operario_id: operarioId,
    });

    if (json.success) {
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: json.message, showConfirmButton: false, timer: 1800 });
        await Promise.all([refrescarSugerido(), cargarArbolUnidades()]);
        const nivel = wkNiveles.find(n => n.id == nivelConfigId);
        await renderPanelNivel1(nivel);
        cargarPendientesEmpaquetado();
    } else {
        Swal.fire('Error', json.message, 'error');
    }
}

// ── Nivel > 1: grid seleccionable del nivel anterior + agrupar ────────────
async function renderPanelNivelSuperior(nivel) {
    const body = document.getElementById('workspacePanelBody');
    const footer = document.getElementById('workspacePanelFooter');

    const nivelAnterior = wkNiveles.find(n => Number(n.nivel) === Number(nivel.nivel) - 1);
    if (!nivelAnterior) {
        body.innerHTML = `<div class="pc-mat-empty">No existe el nivel anterior configurado para este producto.</div>`;
        footer.style.display = 'none';
        return;
    }

    body.innerHTML = `
        <div class="form-text mb-2">Selecciona las unidades de "${nivelAnterior.nombre_nivel}" que quieres agrupar en "${nivel.nombre_nivel}" (sugerido: ${formatearCantidadEmp(nivel.cantidad_por_unidad)}). Deben ser todas del mismo color.</div>
        <div class="pc-mat-grid" id="grid_nivel_superior"><div class="pc-mat-empty"><i class="fa-solid fa-spinner fa-spin"></i> Cargando...</div></div>
    `;
    footer.style.display = 'flex';
    footer.innerHTML = `
        <span id="txt_seleccion_hijas">0 seleccionada(s)</span>
        <button type="button" class="btn btn-primary btn-sm" id="btn_agrupar" disabled onclick="agruparSeleccion(${nivel.id})">
            <i class="fa-solid fa-layer-group"></i> Agrupar en ${nivel.nombre_nivel}
        </button>
    `;

    const json = await llamarEmpaquetado('BUSCARUNIDADESDISPONIBLES', {
        ensamblaje_id: wkEnsamblajeId,
        nivel_config_id: nivelAnterior.id,
    });
    const disponibles = json.success ? (json.unidades || []) : [];
    const grid = document.getElementById('grid_nivel_superior');

    if (disponibles.length === 0) {
        grid.innerHTML = `<div class="pc-mat-empty">No hay unidades de "${nivelAnterior.nombre_nivel}" disponibles todavía.</div>`;
        return;
    }

    grid.innerHTML = disponibles.map(u => {
        const est = estiloPorNombreEmp(u.color_nombre || 'variado');
        const seleccionada = wkSeleccionHijas.has(u.unidad_id);
        return `
        <button type="button" class="pc-mat-card ${seleccionada ? 'seleccionada' : ''}" id="card_unidad_${u.unidad_id}"
                style="--card-color:${est.color};--card-bg:${est.bg};"
                onclick="toggleSeleccionHija(${u.unidad_id})">
            <i class="fa-solid fa-check check-ok"></i>
            <span class="pellet"><i class="fa-solid fa-box"></i></span>
            <span class="nombre">#${u.unidad_id} · ${formatearCantidadEmp(u.cantidad_contenida)} pzs</span>
            <span class="meta">Color: <b>${u.color_nombre || 'Variado'}</b></span>
            <span class="meta">${formatearFechaHoraLegibleEmp(u.created_at)}</span>
        </button>`;
    }).join('');
}

function toggleSeleccionHija(unidadId) {
    if (wkSeleccionHijas.has(unidadId)) {
        wkSeleccionHijas.delete(unidadId);
    } else {
        wkSeleccionHijas.add(unidadId);
    }
    const card = document.getElementById('card_unidad_' + unidadId);
    if (card) card.classList.toggle('seleccionada', wkSeleccionHijas.has(unidadId));

    const txt = document.getElementById('txt_seleccion_hijas');
    const btn = document.getElementById('btn_agrupar');
    if (txt) txt.textContent = `${wkSeleccionHijas.size} seleccionada(s)`;
    if (btn) btn.disabled = wkSeleccionHijas.size === 0;
}

async function agruparSeleccion(nivelConfigId) {
    if (wkSeleccionHijas.size === 0) return;
    const operarios = await obtenerOperariosEmp();

    const { value: operarioId, isConfirmed } = await Swal.fire({
        title: 'Operario (opcional)',
        input: 'select',
        inputOptions: Object.fromEntries([['', 'Sin especificar'], ...operarios.map(o => [o.id, o.nombre_completo])]),
        showCancelButton: true,
        confirmButtonText: 'Agrupar',
        cancelButtonText: 'Cancelar',
    });
    if (!isConfirmed) return;

    const json = await llamarEmpaquetado('CREARUNIDADEMPAQUETADO', {
        ensamblaje_id: wkEnsamblajeId,
        nivel_config_id: nivelConfigId,
        hijas: JSON.stringify(Array.from(wkSeleccionHijas)),
        operario_id: operarioId || '',
    });

    if (json.success) {
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: json.message, showConfirmButton: false, timer: 1800 });
        wkSeleccionHijas = new Set();
        const nivel = wkNiveles.find(n => n.id == nivelConfigId);
        await Promise.all([renderPanelNivelSuperior(nivel), cargarArbolUnidades(), refrescarSugerido()]);
    } else {
        Swal.fire('Error', json.message, 'error');
    }
}

// ── Árbol de unidades del ensamblaje ───────────────────────────────────────
async function cargarArbolUnidades() {
    const json = await llamarEmpaquetado('LISTARUNIDADESENSAMBLAJE', { ensamblaje_id: wkEnsamblajeId });
    wkUnidadesArbol = json.success ? (json.unidades || []) : [];
    renderArbolUnidades();
}

function renderArbolUnidades() {
    const cont = document.getElementById('workspaceArbol');
    if (wkUnidadesArbol.length === 0) {
        cont.innerHTML = '<div class="pc-arbol-empty">Aún no hay unidades registradas.</div>';
        return;
    }

    const porNivel = {};
    wkUnidadesArbol.forEach(u => {
        const k = u.nivel;
        if (!porNivel[k]) porNivel[k] = { nombre: u.nombre_nivel, items: [] };
        porNivel[k].items.push(u);
    });

    const nivelesOrdenados = Object.keys(porNivel).sort((a, b) => a - b);

    cont.innerHTML = nivelesOrdenados.map(nivelNum => {
        const grupo = porNivel[nivelNum];
        const items = grupo.items.map(u => {
            const est = estiloPorNombreEmp(u.color_nombre || 'variado');
            const consumida = !!u.unidad_padre_id;
            const iconoAccion = consumida
                ? ''
                : `<div class="acciones">
                       <button type="button" class="text-danger" title="Eliminar" onclick="eliminarUnidadAccion(${u.id})"><i class="fa-solid fa-trash"></i></button>
                       ${wkUnidadesArbol.some(h => h.unidad_padre_id == u.id) ? `<button type="button" class="text-warning" title="Desagrupar" onclick="desagruparUnidadAccion(${u.id})"><i class="fa-solid fa-rotate-left"></i></button>` : ''}
                   </div>`;
            return `
            <div class="pc-arbol-item ${consumida ? 'consumida' : ''}">
                <span class="pellet-sm" style="--card-color:${est.color};--card-bg:${est.bg};"><i class="fa-solid fa-box"></i></span>
                <div class="cuerpo">
                    <span class="nombre">#${u.id} · ${formatearCantidadEmp(u.cantidad_contenida)} ${nivelNum == 1 ? 'pzs' : 'unid.'}</span>
                    <div class="meta">Color: ${u.color_nombre || 'Variado'} ${u.operario_nombre ? '· ' + u.operario_nombre : ''} · ${formatearFechaHoraLegibleEmp(u.created_at)} ${consumida ? '· (agrupada)' : ''}</div>
                </div>
                ${iconoAccion}
            </div>`;
        }).join('');

        return `
        <div class="pc-arbol-nivel">
            <div class="pc-arbol-nivel-titulo">${grupo.nombre} (nivel ${nivelNum})</div>
            ${items}
        </div>`;
    }).join('');
}

function eliminarUnidadAccion(id) {
    Swal.fire({
        title: '¿Eliminar esta unidad?',
        text: 'Esta acción no se puede deshacer desde aquí.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarEmpaquetado('ELIMINARUNIDAD', { id });
        if (json.success) {
            await Promise.all([cargarArbolUnidades(), refrescarSugerido()]);
            const nivel = wkNiveles.find(n => n.id == wkTabActiva);
            if (nivel) (Number(nivel.nivel) === 1 ? renderPanelNivel1(nivel) : renderPanelNivelSuperior(nivel));
            cargarPendientesEmpaquetado();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

function desagruparUnidadAccion(id) {
    Swal.fire({
        title: '¿Desagrupar esta unidad?',
        text: 'Las unidades que agrupa quedarán disponibles de nuevo.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, desagrupar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarEmpaquetado('DESAGRUPARUNIDAD', { id });
        if (json.success) {
            await Promise.all([cargarArbolUnidades(), refrescarSugerido()]);
            const nivel = wkNiveles.find(n => n.id == wkTabActiva);
            if (nivel) (Number(nivel.nivel) === 1 ? renderPanelNivel1(nivel) : renderPanelNivelSuperior(nivel));
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

function refrescarWorkspace() {
    Promise.all([refrescarSugerido(), cargarArbolUnidades()]);
    const nivel = wkNiveles.find(n => n.id == wkTabActiva);
    if (nivel) (Number(nivel.nivel) === 1 ? renderPanelNivel1(nivel) : renderPanelNivelSuperior(nivel));
}

// =============================================================================
// CONFIGURAR NIVELES POR PRODUCTO
// =============================================================================

async function obtenerProductosConfig() {
    if (productosConfigCache) return productosConfigCache;
    const json = await llamarEmpaquetado('BUSCARPRODUCTOS', { texto: '' });
    productosConfigCache = json.success ? json.productos : [];
    return productosConfigCache;
}

async function abrirModalConfigNiveles(productoIdPreseleccionado = null) {
    const sel = document.getElementById('cfg_producto_id');
    const productos = await obtenerProductosConfig();
    sel.innerHTML = '<option value="">Selecciona un producto...</option>' +
        productos.map(p => `<option value="${p.id}">${p.codigo} - ${p.descripcion}</option>`).join('');

    limpiarFormNivelConfig();
    document.getElementById('cfg_niveles_lista').innerHTML = '<div class="pc-mat-empty">Selecciona un producto para ver sus niveles.</div>';

    if (productoIdPreseleccionado) {
        sel.value = productoIdPreseleccionado;
        await cargarNivelesConfig();
    }

    modalConfigNiveles.show();
}

async function cargarNivelesConfig() {
    const productoId = document.getElementById('cfg_producto_id').value;
    const cont = document.getElementById('cfg_niveles_lista');
    if (!productoId) {
        cont.innerHTML = '<div class="pc-mat-empty">Selecciona un producto para ver sus niveles.</div>';
        return;
    }
    cont.innerHTML = '<div class="pc-mat-empty"><i class="fa-solid fa-spinner fa-spin"></i> Cargando...</div>';

    const json = await llamarEmpaquetado('OBTENERNIVELESPRODUCTO', { producto_id: productoId });
    const niveles = json.success ? (json.niveles || []) : [];

    if (niveles.length === 0) {
        cont.innerHTML = '<div class="pc-mat-empty">Este producto aún no tiene niveles configurados. Agrega el primero abajo.</div>';
        return;
    }

    cont.innerHTML = niveles.map(n => `
        <div class="pc-nivel-row">
            <div class="num">${n.nivel}</div>
            <div class="info">
                <div class="nom">${n.nombre_nivel}</div>
                <div class="meta">x${formatearCantidadEmp(n.cantidad_por_unidad)} ${n.permite_variado === true || n.permite_variado === 't' ? '· admite variado' : '· color obligatorio'}</div>
            </div>
            <button type="button" class="pc-icon-btn" title="Editar" onclick='editarNivelConfig(${JSON.stringify(n)})'><i class="fa-solid fa-pen"></i></button>
            <button type="button" class="pc-icon-btn" title="Eliminar" onclick="eliminarNivelConfigAccion(${n.id})"><i class="fa-solid fa-trash"></i></button>
        </div>
    `).join('');
}

function limpiarFormNivelConfig() {
    document.getElementById('formNivelConfig').reset();
    document.getElementById('cfg_nivel_id').value = '0';
    document.getElementById('cfg_nivel_variado').checked = true;
}

function editarNivelConfig(n) {
    document.getElementById('cfg_nivel_id').value = n.id;
    document.getElementById('cfg_nivel_numero').value = n.nivel;
    document.getElementById('cfg_nivel_nombre').value = n.nombre_nivel;
    document.getElementById('cfg_nivel_cantidad').value = n.cantidad_por_unidad;
    document.getElementById('cfg_nivel_variado').checked = (n.permite_variado === true || n.permite_variado === 't');
}

document.getElementById('formNivelConfig').addEventListener('submit', async function (e) {
    e.preventDefault();
    const productoId = document.getElementById('cfg_producto_id').value;
    if (!productoId) { Swal.fire('Falta producto', 'Selecciona un producto primero.', 'warning'); return; }

    const params = {
        id: document.getElementById('cfg_nivel_id').value,
        producto_id: productoId,
        nivel: document.getElementById('cfg_nivel_numero').value,
        nombre_nivel: document.getElementById('cfg_nivel_nombre').value,
        cantidad_por_unidad: document.getElementById('cfg_nivel_cantidad').value,
        permite_variado: document.getElementById('cfg_nivel_variado').checked ? 'true' : 'false',
    };

    const json = await llamarEmpaquetado('GUARDARNIVELPRODUCTO', params);
    if (json.success) {
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: json.message, showConfirmButton: false, timer: 1800 });
        limpiarFormNivelConfig();
        cargarNivelesConfig();
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});

function eliminarNivelConfigAccion(id) {
    Swal.fire({
        title: '¿Eliminar este nivel?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarEmpaquetado('ELIMINARNIVELPRODUCTO', { id });
        if (json.success) {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: json.message, showConfirmButton: false, timer: 1800 });
            cargarNivelesConfig();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>