<?php
$pageTitle    = 'Listado de Ventas';
$pageSubtitle = 'Historial de ventas registradas';
$activePage   = 'listado_ventas';

include("header.php");
?>

<style>
.lv-tab{ min-width:150px; }
.lv-tab.active{ background:#2F6FED !important; color:#fff !important; border-color:#2F6FED !important; }
.lv-resumen-card{ background:#fff; border:1px solid #eee2c8; border-radius:10px; padding:14px; height:100%; }
.lv-resumen-label{ font-size:.82em; color:#8a8578; text-align:center; }
.lv-resumen-total{ font-size:1.7em; font-weight:700; color:#2F6FED; text-align:center; }
</style>

<div class="pc-card mb-3">
    <div class="pc-card-header">
        <h2>Listado de Ventas</h2>
    </div>
    <div class="p-3">
        <div class="d-flex gap-2 flex-wrap mb-3" id="lv_tabs">
            <button type="button" class="pc-btn pc-btn-outline lv-tab active" data-rango="hoy">Ventas del Día</button>
            <button type="button" class="pc-btn pc-btn-outline lv-tab" data-rango="semana">Ventas de la Semana</button>
            <button type="button" class="pc-btn pc-btn-outline lv-tab" data-rango="todas">Todas las Ventas</button>
            <button type="button" class="pc-btn pc-btn-outline lv-tab" data-rango="personalizado">Ventas por Rango</button>
        </div>

        <div class="d-flex gap-2 flex-wrap align-items-end mb-3" id="lv_rango_fechas" style="display:none;">
            <div>
                <label class="form-label">Desde</label>
                <input type="date" id="lv_fecha_inicio" class="form-control">
            </div>
            <div>
                <label class="form-label">Hasta</label>
                <input type="date" id="lv_fecha_fin" class="form-control">
            </div>
            <button type="button" class="pc-btn pc-btn-primary" onclick="cargarListadoVentas()">Aplicar</button>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <input type="text" id="lv_texto" class="form-control" style="max-width:280px" placeholder="Buscar por código o cliente...">
            <select id="lv_estado" class="form-select" style="max-width:180px">
                <option value="">Todos los estados</option>
                <option value="completada">Completadas</option>
                <option value="anulada">Anuladas</option>
            </select>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="lv-resumen-card d-flex flex-column justify-content-center">
            <div class="lv-resumen-label">Total del período (completadas)</div>
            <div class="lv-resumen-total" id="lv_total_periodo">S/ 0.00</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="lv-resumen-card">
            <div class="lv-resumen-label mb-2">Ventas por hora</div>
            <canvas id="lv_chart_horas" height="110"></canvas>
        </div>
    </div>
    <div class="col-md-4">
        <div class="lv-resumen-card">
            <div class="lv-resumen-label mb-2">Estado de las ventas</div>
            <canvas id="lv_chart_estado" height="110"></canvas>
        </div>
    </div>
</div>

<div class="pc-card">
    <div class="pc-table-wrap pc-table-responsive-cards">
        <table class="pc-table" id="tablaListadoVentas">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Cliente</th>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Ítems</th>
                    <th>Monto</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="tbodyListadoVentas">
                <tr><td colspan="8" style="text-align:center;">Cargando...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
const CONTROLADOR_VENTA = 'controllers/clssVenta.php';
const TICKET_PDF_URL = 'controllers/ticketPdf.php';

async function llamarVenta(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_VENTA, {
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

function formatearMoneda(n) {
    return 'S/ ' + Number(n || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

let rangoActual = 'hoy';
let lvChartHoras = null;
let lvChartEstado = null;

document.addEventListener('DOMContentLoaded', () => {
    cargarListadoVentas();

    document.querySelectorAll('.lv-tab').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.lv-tab').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            rangoActual = btn.dataset.rango;
            document.getElementById('lv_rango_fechas').style.display = (rangoActual === 'personalizado') ? 'flex' : 'none';
            if (rangoActual !== 'personalizado') cargarListadoVentas();
        });
    });

    let debounceTexto = null;
    document.getElementById('lv_texto').addEventListener('input', () => {
        clearTimeout(debounceTexto);
        debounceTexto = setTimeout(cargarListadoVentas, 350);
    });
    document.getElementById('lv_estado').addEventListener('change', cargarListadoVentas);
});

async function cargarListadoVentas() {
    const texto  = document.getElementById('lv_texto').value.trim();
    const estado = document.getElementById('lv_estado').value;
    const fecha_inicio = document.getElementById('lv_fecha_inicio').value;
    const fecha_fin    = document.getElementById('lv_fecha_fin').value;

    if (rangoActual === 'personalizado' && (!fecha_inicio || !fecha_fin)) {
        return; // esperar a que elija ambas fechas y presione "Aplicar"
    }

    const json = await llamarVenta('LISTARVENTAS', {
        texto, estado, rango: rangoActual, fecha_inicio, fecha_fin
    });

    const tbody = document.getElementById('tbodyListadoVentas');
    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;">${json.message}</td></tr>`;
        return;
    }

    const ventas = json.ventas || [];
    renderTablaVentas(ventas);
    renderResumen(ventas);
}

function renderTablaVentas(ventas) {
    const tbody = document.getElementById('tbodyListadoVentas');
    if (ventas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;">No hay ventas en este período.</td></tr>';
        return;
    }

    tbody.innerHTML = ventas.map(v => {
        const badgeEstado = v.estado === 'anulada'
            ? '<span class="badge bg-secondary">Anulada</span>'
            : '<span class="badge bg-success">Completada</span>';

        const fechaObj = new Date(v.fecha_venta);
        const fecha = fechaObj.toLocaleDateString('es-PE');
        const hora  = fechaObj.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });

        return `
        <tr>
            <td data-label="Código"><b>${v.codigo}</b></td>
            <td data-label="Cliente">${v.cliente_nombre}</td>
            <td data-label="Fecha">${fecha}</td>
            <td data-label="Hora">${hora}</td>
            <td data-label="Ítems">${v.items_count}</td>
            <td data-label="Monto">${formatearMoneda(v.monto_total)}</td>
            <td data-label="Estado">${badgeEstado}</td>
            <td data-label="Acciones" class="pc-td-acciones">
                <button class="pc-icon-btn" onclick="imprimirTicketVenta(${v.id})" title="Ver ticket (PDF)">
                    <i class="fa-solid fa-file-pdf"></i>
                </button>
                ${v.estado !== 'anulada'
                    ? `<button class="pc-icon-btn" onclick="anularVenta(${v.id})" title="Anular">
                           <i class="fa-solid fa-ban"></i></button>`
                    : ''
                }
            </td>
        </tr>`;
    }).join('');
}

function renderResumen(ventas) {
    let totalCompletadas = 0;
    let countCompletadas = 0;
    let countAnuladas = 0;
    const montoPorHora = Array(24).fill(0);

    ventas.forEach(v => {
        if (v.estado === 'anulada') {
            countAnuladas++;
            return;
        }
        countCompletadas++;
        const monto = Number(v.monto_total) || 0;
        totalCompletadas += monto;
        const hora = new Date(v.fecha_venta).getHours();
        montoPorHora[hora] += monto;
    });

    document.getElementById('lv_total_periodo').textContent = formatearMoneda(totalCompletadas);

    // Gráfico de ventas por hora (solo horas con actividad)
    const horasConDatos = montoPorHora
        .map((monto, hora) => ({ hora, monto }))
        .filter(h => h.monto > 0);

    const ctxHoras = document.getElementById('lv_chart_horas');
    if (lvChartHoras) lvChartHoras.destroy();
    lvChartHoras = new Chart(ctxHoras, {
        type: 'bar',
        data: {
            labels: horasConDatos.length ? horasConDatos.map(h => `${h.hora}:00`) : ['Sin datos'],
            datasets: [{
                label: 'Ventas (S/)',
                data: horasConDatos.length ? horasConDatos.map(h => h.monto) : [0],
                backgroundColor: '#2F6FED',
                borderRadius: 4,
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });

    // Gráfico de estado (completadas vs anuladas)
    const ctxEstado = document.getElementById('lv_chart_estado');
    if (lvChartEstado) lvChartEstado.destroy();
    lvChartEstado = new Chart(ctxEstado, {
        type: 'doughnut',
        data: {
            labels: ['Completadas', 'Anuladas'],
            datasets: [{
                data: [countCompletadas, countAnuladas],
                backgroundColor: ['#2FB170', '#8a8578'],
            }]
        },
        options: {
            plugins: { legend: { position: 'bottom' } }
        }
    });
}

// ── Anular ───────────────────────────────────────────────────────────────────
function anularVenta(id) {
    Swal.fire({
        title: '¿Anular esta venta?',
        text: 'Se repondrá el stock consumido en empaquetado.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, anular',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarVenta('ANULARVENTA', { id });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarListadoVentas();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

function imprimirTicketVenta(id) {
    window.open(`${TICKET_PDF_URL}?id=${id}`, '_blank');
}
</script>

<?php require __DIR__ . '/footer.php'; ?>