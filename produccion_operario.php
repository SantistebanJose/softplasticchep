<?php
$pageTitle    = 'Reporte de Producción';
$pageSubtitle = 'Producción por operario: día, semana, mes o rango de fechas';
$activePage   = 'reporte_produccion';

include("header.php");
?>

<style>
    .rep-stats {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }
    .rep-stat-card {
        flex: 1 1 200px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 14px 16px;
    }
    .rep-stat-card .rep-stat-label {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #6b7280;
        margin-bottom: 4px;
    }
    .rep-stat-card .rep-stat-valor {
        font-size: 22px;
        font-weight: 700;
        color: var(--pc-navy, #1f2937);
    }
    .rep-destacado {
        display: none;
        align-items: center;
        gap: 16px;
        background: linear-gradient(135deg, var(--pc-navy, #1f2937), #2d3d5c);
        color: #fff;
        border-radius: 12px;
        padding: 18px 20px;
        margin-bottom: 16px;
    }
    .rep-destacado.activo { display: flex; }
    .rep-destacado .rep-destacado-icono {
        font-size: 34px;
        color: #ffd75e;
    }
    .rep-destacado .rep-destacado-nombre {
        font-size: 19px;
        font-weight: 700;
    }
    .rep-destacado .rep-destacado-cargo {
        font-size: 13px;
        opacity: .85;
    }
    .rep-destacado .rep-destacado-metricas {
        margin-left: auto;
        display: flex;
        gap: 22px;
        text-align: right;
    }
    .rep-destacado .rep-destacado-metricas b {
        display: block;
        font-size: 18px;
    }
    .rep-destacado .rep-destacado-metricas span {
        font-size: 11px;
        text-transform: uppercase;
        opacity: .8;
    }
    .rep-barra-wrap {
        background: #eef0f3;
        border-radius: 6px;
        overflow: hidden;
        height: 16px;
        min-width: 90px;
    }
    .rep-barra {
        height: 100%;
        background: var(--pc-red, #c0392b);
        border-radius: 6px;
    }
    .rep-fila-top1 td:first-child { font-weight: 700; }
    .rep-vacio { text-align: center; color: #6b7280; padding: 18px 0; }
</style>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Producción por operario</h2>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap align-items-end mb-3">
        <br>
        <div>
            <label class="form-label d-block">Periodo</label>
            <select id="rep_modo" class="form-select" style="min-width:160px">
                <option value="dia" selected>Día</option>
                <option value="semana">Semana</option>
                <option value="mes">Mes</option>
                <option value="rango">Rango personalizado</option>
            </select>
        </div>

        <div id="rep_wrap_fecha">
            <label class="form-label d-block">Fecha</label>
            <input type="date" id="rep_fecha" class="form-control">
        </div>

        <div id="rep_wrap_rango" style="display:none;">
            <label class="form-label d-block">Desde</label>
            <input type="date" id="rep_fecha_desde" class="form-control">
        </div>
        <div id="rep_wrap_rango_hasta" style="display:none;">
            <label class="form-label d-block">Hasta</label>
            <input type="date" id="rep_fecha_hasta" class="form-control">
        </div>

        <div>
            <label class="form-label d-block">Operario</label>
            <select id="rep_operario" class="form-select" style="min-width:180px">
                <option value="">Todos</option>
            </select>
        </div>

        <div>
            <label class="form-label d-block">Máquina</label>
            <select id="rep_maquina" class="form-select" style="min-width:150px">
                <option value="">Todas</option>
            </select>
        </div>

        <div>
            <label class="form-label d-block">Sucursal</label>
            <select id="rep_sucursal" class="form-select" style="min-width:150px">
                <option value="">Todas</option>
            </select>
        </div>

        <div class="form-check" style="padding-top:8px;">
            <input class="form-check-input" type="checkbox" id="rep_solo_actividad" checked>
            <label class="form-check-label" for="rep_solo_actividad">
                Solo con actividad
            </label>
        </div>

        <div>
            <button class="pc-btn pc-btn-primary" onclick="generarReporte()">
                <i class="fa-solid fa-magnifying-glass"></i> Generar reporte
            </button>
        </div>
    </div>

    <p id="rep_etiqueta_periodo" class="text-muted"></p>

    <div class="rep-stats">
        <div class="rep-stat-card">
            <div class="rep-stat-label">Kg insertados (total)</div>
            <div class="rep-stat-valor" id="rep_total_kg">-</div>
        </div>
        <div class="rep-stat-card">
            <div class="rep-stat-label">Avances registrados</div>
            <div class="rep-stat-valor" id="rep_total_avances">-</div>
        </div>
        <div class="rep-stat-card">
            <div class="rep-stat-label">Operarios con actividad</div>
            <div class="rep-stat-valor" id="rep_total_operarios">-</div>
        </div>
    </div>

    <div class="rep-destacado" id="rep_destacado">
        <div class="rep-destacado-icono"><i class="fa-solid fa-trophy"></i></div>
        <div>
            <div class="rep-destacado-nombre" id="rep_destacado_nombre">-</div>
            <div class="rep-destacado-cargo" id="rep_destacado_cargo">-</div>
        </div>
        <div class="rep-destacado-metricas">
            <div><b id="rep_destacado_kg">-</b><span>Kg insertados</span></div>
            <div><b id="rep_destacado_avances">-</b><span>Avances</span></div>
        </div>
    </div>

    <div class="pc-table-wrap pc-table-responsive-cards">
        <table class="pc-table" id="tablaRanking">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Operario</th>
                    <th>Cargo</th>
                    <th>Avances</th>
                    <th>Kg insertados</th>
                    <th>Kg producidos</th>
                    <th>Promedio kg/avance</th>
                    <th>Participación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="tbodyRanking">
                <tr><td colspan="9" class="rep-vacio">Genera el reporte para ver resultados.</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="pc-card">
    <div class="pc-card-header">
        <h2>Tendencia diaria del periodo</h2>
    </div>
    <div class="pc-table-wrap pc-table-responsive-cards">
        <table class="pc-table" id="tablaTendencia">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Avances</th>
                    <th>Kg insertados</th>
                    <th>Comparativo</th>
                </tr>
            </thead>
            <tbody id="tbodyTendencia">
                <tr><td colspan="4" class="rep-vacio">Sin datos aún.</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Detalle de operario -->
<div class="modal fade" id="modalDetalleOperario" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalle de producción — <span id="modalDetalleNombre">-</span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="pc-table-wrap">
            <table class="pc-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Avances</th>
                        <th>Kg insertados</th>
                        <th>Kg producidos</th>
                        <th>Moldes</th>
                        <th>Colores</th>
                    </tr>
                </thead>
                <tbody id="tbodyDetalleOperario">
                    <tr><td colspan="6" class="rep-vacio">-</td></tr>
                </tbody>
            </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const CONTROLADOR_REPORTE = 'controllers/clssReporteProduccion.php';
const modalDetalleOperario = new bootstrap.Modal(document.getElementById('modalDetalleOperario'));

// Guardamos el rango de fechas del último reporte generado, para que el
// modal de detalle de operario consulte exactamente el mismo periodo.
let ultimoPeriodo = { desde: null, hasta: null };

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('rep_fecha').value = hoyISO();

    Promise.all([
        cargarSelectOperarios(),
        cargarSelectMaquinas(),
        cargarSelectSucursales(),
    ]).then(generarReporte).catch(err => {
        console.error('Error cargando filtros iniciales:', err);
        Swal.fire('Error', 'No se pudieron cargar los filtros del reporte.', 'error');
    });

    document.getElementById('rep_modo').addEventListener('change', actualizarVisibilidadFechas);
    actualizarVisibilidadFechas();
});

function hoyISO() {
    return new Date().toISOString().slice(0, 10);
}

function actualizarVisibilidadFechas() {
    const modo = document.getElementById('rep_modo').value;
    const esRango = modo === 'rango';
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
    const select = document.getElementById('rep_operario');
    if (!json.success) return;
    (json.operarios || []).forEach(o => {
        const opt = document.createElement('option');
        opt.value = o.id;
        opt.textContent = o.cargo ? `${o.nombre_completo} (${o.cargo})` : o.nombre_completo;
        select.appendChild(opt);
    });
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

// ── Reporte principal ────────────────────────────────────────────────────────
async function generarReporte() {
    const modo = document.getElementById('rep_modo').value;

    const params = {
        modo,
        fecha: document.getElementById('rep_fecha').value || hoyISO(),
        fecha_desde: document.getElementById('rep_fecha_desde').value,
        fecha_hasta: document.getElementById('rep_fecha_hasta').value,
        operario_id: document.getElementById('rep_operario').value,
        maquina_id: document.getElementById('rep_maquina').value,
        sucursal_id: document.getElementById('rep_sucursal').value,
        solo_con_actividad: document.getElementById('rep_solo_actividad').checked ? '1' : '0',
    };

    const json = await llamarReporte('REPORTEOPERARIOS', params);
    if (!json.success) {
        Swal.fire('Error', json.message, 'error');
        return;
    }

    ultimoPeriodo = { desde: json.periodo.desde, hasta: json.periodo.hasta };

    document.getElementById('rep_etiqueta_periodo').textContent = json.periodo.etiqueta;

    document.getElementById('rep_total_kg').textContent = formatearKg(json.resumen.total_kg_insertado);
    document.getElementById('rep_total_avances').textContent = json.resumen.total_avances;
    document.getElementById('rep_total_operarios').textContent = json.resumen.operarios_con_actividad;

    pintarDestacado(json.destacado);
    pintarRanking(json.filas || []);
    pintarTendencia(json.serie_diaria || []);
}

function pintarDestacado(destacado) {
    const card = document.getElementById('rep_destacado');
    if (!destacado) {
        card.classList.remove('activo');
        return;
    }
    card.classList.add('activo');
    document.getElementById('rep_destacado_nombre').textContent = destacado.operario_nombre;
    document.getElementById('rep_destacado_cargo').textContent = destacado.cargo || 'Sin cargo asignado';
    document.getElementById('rep_destacado_kg').textContent = formatearKg(destacado.total_kg_insertado);
    document.getElementById('rep_destacado_avances').textContent = destacado.total_avances;
}

function pintarRanking(filas) {
    const tbody = document.getElementById('tbodyRanking');

    if (filas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="rep-vacio">No hay operarios con actividad en este periodo.</td></tr>';
        return;
    }

    const maxKg = Math.max(...filas.map(f => parseFloat(f.total_kg_insertado) || 0), 0.0001);

    tbody.innerHTML = filas.map((f, i) => {
        const kg = parseFloat(f.total_kg_insertado) || 0;
        const porcentaje = Math.round((kg / maxKg) * 100);
        const filaTop = i === 0 && kg > 0 ? 'rep-fila-top1' : '';

        return `
        <tr class="${filaTop}">
            <td data-label="#">${i + 1}${i === 0 && kg > 0 ? ' <i class="fa-solid fa-trophy" style="color:#c9a227"></i>' : ''}</td>
            <td data-label="Operario">${f.operario_nombre}</td>
            <td data-label="Cargo">${f.cargo ?? '-'}</td>
            <td data-label="Avances">${f.total_avances}</td>
            <td data-label="Kg insertados">${formatearKg(f.total_kg_insertado)}</td>
            <td data-label="Kg producidos">${formatearKg(f.total_kg_producido)}</td>
            <td data-label="Promedio kg/avance">${f.promedio_kg_avance ?? '-'}</td>
            <td data-label="Participación">
                <div class="rep-barra-wrap"><div class="rep-barra" style="width:${porcentaje}%"></div></div>
            </td>
            <td data-label="Acciones" class="pc-td-acciones">
                <button class="pc-icon-btn" title="Ver detalle" onclick="verDetalleOperario(${f.operario_id}, '${escaparComillas(f.operario_nombre)}')">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
            </td>
        </tr>`;
    }).join('');
}

function pintarTendencia(serie) {
    const tbody = document.getElementById('tbodyTendencia');

    if (serie.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="rep-vacio">Sin datos en este periodo.</td></tr>';
        return;
    }

    const maxKg = Math.max(...serie.map(s => parseFloat(s.total_kg) || 0), 0.0001);

    tbody.innerHTML = serie.map(s => {
        const kg = parseFloat(s.total_kg) || 0;
        const porcentaje = Math.round((kg / maxKg) * 100);
        return `
        <tr>
            <td data-label="Fecha">${formatearFecha(s.dia)}</td>
            <td data-label="Avances">${s.total_avances}</td>
            <td data-label="Kg insertados">${formatearKg(s.total_kg)}</td>
            <td data-label="Comparativo">
                <div class="rep-barra-wrap"><div class="rep-barra" style="width:${porcentaje}%"></div></div>
            </td>
        </tr>`;
    }).join('');
}

// ── Detalle de operario (modal) ───────────────────────────────────────────────
async function verDetalleOperario(operarioId, operarioNombre) {
    if (!ultimoPeriodo.desde || !ultimoPeriodo.hasta) return;

    document.getElementById('modalDetalleNombre').textContent = operarioNombre;
    document.getElementById('tbodyDetalleOperario').innerHTML =
        '<tr><td colspan="6" class="rep-vacio">Cargando...</td></tr>';
    modalDetalleOperario.show();

    const json = await llamarReporte('REPORTEDETALLEOPERARIO', {
        operario_id: operarioId,
        fecha_desde: ultimoPeriodo.desde,
        fecha_hasta: ultimoPeriodo.hasta,
    });

    const tbody = document.getElementById('tbodyDetalleOperario');
    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="6" class="rep-vacio">${json.message}</td></tr>`;
        return;
    }

    const detalle = json.detalle || [];
    if (detalle.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="rep-vacio">Sin avances en este periodo.</td></tr>';
        return;
    }

    tbody.innerHTML = detalle.map(d => `
        <tr>
            <td data-label="Fecha">${formatearFecha(d.dia)}</td>
            <td data-label="Avances">${d.avances}</td>
            <td data-label="Kg insertados">${formatearKg(d.kg_insertado)}</td>
            <td data-label="Kg producidos">${formatearKg(d.kg_producido)}</td>
            <td data-label="Moldes">${d.moldes ?? '-'}</td>
            <td data-label="Colores">${d.colores ?? '-'}</td>
        </tr>
    `).join('');
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