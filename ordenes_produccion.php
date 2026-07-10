<?php
$pageTitle    = 'Orden de Producción';
$pageSubtitle = 'Gestión de órdenes de producción';
$activePage   = 'orden_produccion';

include("header.php");
?>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Órdenes de producción</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrearOrden()">
            <i class="fa-solid fa-plus"></i> Nueva orden
        </button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <br>
        <input type="text" id="fo_texto" class="form-control" style="max-width:260px"
               placeholder="Buscar por código, máquina o producto...">
        <select id="fo_estado" class="form-select" style="max-width:180px">
            <option value="">Todos los estados</option>
            <option value="pendiente">Pendiente</option>
            <option value="en_proceso">En proceso</option>
            <option value="completada">Completada</option>
            <option value="cancelada">Cancelada</option>
        </select>
        <select id="fo_visibilidad" class="form-select" style="max-width:160px">
            <option value="activas" selected>Activas</option>
            <option value="eliminadas">Eliminadas</option>
            <option value="todas">Todas</option>
        </select>
    </div>

    <div class="pc-table-wrap pc-table-responsive-cards">
    <table class="pc-table" id="tablaOrdenes">
        <thead>
            <tr>
                <th>Código</th>
                <th>Producto</th>
                <th>Máquina</th>
                <th>Cantidad</th>
                <th>Estado</th>
                <th>Inicio</th>
                <th>Fin</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tbodyOrdenes">
            <tr><td colspan="8" style="text-align:center;">Cargando...</td></tr>
        </tbody>
    </table>
    </div>

</div>

<!-- Modal Crear/Editar -->
<div class="modal fade" id="modalOrden" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formOrden">
        <div class="modal-header">
          <h5 class="modal-title" id="modalOrdenTitulo">Nueva orden</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="orden_id">

          <div class="mb-2">
            <label class="form-label">Código *</label>
            <input type="text" class="form-control" name="codigo" id="orden_codigo" required>
          </div>

          <div class="mb-2">
            <label class="form-label">Producto *</label>
            <select class="form-select" name="producto_id" id="orden_producto_id" required>
              <option value="">Selecciona un producto...</option>
            </select>
          </div>

          <div class="mb-2">
            <label class="form-label">Máquina</label>
            <input type="text" class="form-control" name="maquina" id="orden_maquina"
                   placeholder="Ej: Inyectora 1">
          </div>

          <div class="mb-2">
            <label class="form-label">Cantidad *</label>
            <input type="number" class="form-control" name="cantidad" id="orden_cantidad" min="1" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const CONTROLADOR_ORDENES = 'controllers/clssOrdenProduccion.php'; // vive en su propia carpeta
const modalOrden = new bootstrap.Modal(document.getElementById('modalOrden'));

const ESTADOS_LABEL = {
    pendiente:  '<span class="badge bg-warning text-dark">Pendiente</span>',
    en_proceso: '<span class="badge bg-primary">En proceso</span>',
    completada: '<span class="badge bg-success">Completada</span>',
    cancelada:  '<span class="badge bg-secondary">Cancelada</span>',
};

document.addEventListener('DOMContentLoaded', () => {
    Promise.all([cargarProductos(), cargarOrdenes()]).catch(err => {
        console.error('Error cargando datos iniciales:', err);
        document.getElementById('tbodyOrdenes').innerHTML =
            `<tr><td colspan="8" style="text-align:center;color:red;">Error de conexión con el servidor. Revisa la consola (F12).</td></tr>`;
    });

    // ── Búsqueda automática ──────────────────────────────────────────────────
    let debounceTimer = null;
    document.getElementById('fo_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarOrdenes, 350);
    });

    document.getElementById('fo_estado').addEventListener('change', cargarOrdenes);
    document.getElementById('fo_visibilidad').addEventListener('change', cargarOrdenes);
});

// ── Llamada genérica al controlador ─────────────────────────────────────────
async function llamarOrdenes(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_ORDENES, {
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

// ── Combo de productos ───────────────────────────────────────────────────────
async function cargarProductos() {
    const json = await llamarOrdenes('LISTARPRODUCTOS');
    const select = document.getElementById('orden_producto_id');

    if (!json.success) {
        console.error('No se pudieron cargar los productos:', json.message);
        return;
    }

    const productos = json.productos || [];
    select.innerHTML = '<option value="">Selecciona un producto...</option>' +
        productos.map(p => `<option value="${p.id}">${p.nombre}</option>`).join('');
}

// ── Listado ──────────────────────────────────────────────────────────────────
function formatearFecha(f) {
    if (!f) return '-';
    return f.replace('T', ' ').substring(0, 16);
}

async function cargarOrdenes() {
    const texto       = document.getElementById('fo_texto').value.trim();
    const estado      = document.getElementById('fo_estado').value;
    const visibilidad = document.getElementById('fo_visibilidad').value;

    const json = await llamarOrdenes('LISTARORDENES', { texto, estado, visibilidad });
    const tbody = document.getElementById('tbodyOrdenes');

    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;">${json.message}</td></tr>`;
        return;
    }

    const ordenes = json.ordenes || [];
    if (ordenes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;">No hay órdenes registradas.</td></tr>';
        return;
    }

    tbody.innerHTML = ordenes.map(o => {
        const badgeEstado = ESTADOS_LABEL[o.estado] ?? o.estado;
        const inactiva = !!o.deleted_at;
        const badge = inactiva
            ? `${badgeEstado} <span class="badge bg-dark">Inactiva</span>`
            : badgeEstado;

        let acciones = `<button class="pc-icon-btn" onclick="verOrden(${o.id})" title="Ver detalle">
                            <i class="fa-solid fa-eye"></i></button>`;

        if (inactiva) {
            acciones += `
                <button class="pc-icon-btn" onclick="reactivarOrden(${o.id})" title="Reactivar">
                    <i class="fa-solid fa-rotate-left"></i></button>`;
        } else if (o.estado === 'pendiente') {
            acciones += `
                <button class="pc-icon-btn" onclick="abrirModalEditarOrden(${o.id})" title="Editar">
                    <i class="fa-solid fa-pen"></i></button>
                <button class="pc-icon-btn" onclick="iniciarOrden(${o.id})" title="Iniciar">
                    <i class="fa-solid fa-play"></i></button>
                <button class="pc-icon-btn" onclick="cancelarOrden(${o.id})" title="Cancelar">
                    <i class="fa-solid fa-ban"></i></button>
                <button class="pc-icon-btn" onclick="eliminarOrden(${o.id})" title="Desactivar">
                    <i class="fa-solid fa-trash"></i></button>`;
        } else if (o.estado === 'en_proceso') {
            acciones += `
                <button class="pc-icon-btn" onclick="finalizarOrden(${o.id})" title="Finalizar">
                    <i class="fa-solid fa-flag-checkered"></i></button>
                <button class="pc-icon-btn" onclick="cancelarOrden(${o.id})" title="Cancelar">
                    <i class="fa-solid fa-ban"></i></button>`;
        }

        return `
        <tr id="fila-orden-${o.id}">
            <td data-label="Código">${o.codigo}</td>
            <td data-label="Producto">${o.producto_nombre ?? '-'}</td>
            <td data-label="Máquina">${o.maquina ?? '-'}</td>
            <td data-label="Cantidad">${o.cantidad}</td>
            <td data-label="Estado">${badge}</td>
            <td data-label="Inicio">${formatearFecha(o.fecha_inicio)}</td>
            <td data-label="Fin">${formatearFecha(o.fecha_fin)}</td>
            <td data-label="Acciones" class="pc-td-acciones">${acciones}</td>
        </tr>`;
    }).join('');
}

// ── Ver detalle ──────────────────────────────────────────────────────────────
async function verOrden(id) {
    const json = await llamarOrdenes('OBTENERORDEN', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    const o = json.orden;
    const estadoVisibilidad = o.deleted_at
        ? '<span class="badge bg-dark">Inactiva</span>'
        : '<span class="badge bg-success">Activa</span>';

    Swal.fire({
        title: `Orden ${o.codigo}`,
        html: `
            <p style="text-align:left"><b>Producto:</b> ${o.producto_nombre ?? '-'}</p>
            <p style="text-align:left"><b>Máquina:</b> ${o.maquina ?? '-'}</p>
            <p style="text-align:left"><b>Cantidad:</b> ${o.cantidad}</p>
            <p style="text-align:left"><b>Estado:</b> ${ESTADOS_LABEL[o.estado] ?? o.estado}</p>
            <p style="text-align:left"><b>Visibilidad:</b> ${estadoVisibilidad}</p>
            <p style="text-align:left"><b>Inicio:</b> ${formatearFecha(o.fecha_inicio)}</p>
            <p style="text-align:left"><b>Fin:</b> ${formatearFecha(o.fecha_fin)}</p>
        `
    });
}

// ── Crear / Editar ───────────────────────────────────────────────────────────
function abrirModalCrearOrden() {
    document.getElementById('formOrden').reset();
    document.getElementById('orden_id').value = '';
    document.getElementById('modalOrdenTitulo').textContent = 'Nueva orden';
    modalOrden.show();
}

async function abrirModalEditarOrden(id) {
    const json = await llamarOrdenes('OBTENERORDEN', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    const o = json.orden;
    document.getElementById('modalOrdenTitulo').textContent = 'Editar orden';
    document.getElementById('orden_id').value = o.id;
    document.getElementById('orden_codigo').value = o.codigo ?? '';
    document.getElementById('orden_producto_id').value = o.producto_id ?? '';
    document.getElementById('orden_maquina').value = o.maquina ?? '';
    document.getElementById('orden_cantidad').value = o.cantidad ?? '';

    modalOrden.show();
}

document.getElementById('formOrden').addEventListener('submit', async function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('accion', 'GUARDARORDEN');

    const resp = await fetch(CONTROLADOR_ORDENES, { method: 'POST', body: formData });
    const json = await resp.json();

    if (json.success) {
        modalOrden.hide();
        Swal.fire('Listo', json.message, 'success');
        cargarOrdenes();
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});

// ── Cambios de estado ────────────────────────────────────────────────────────
function iniciarOrden(id) {
    Swal.fire({
        title: '¿Iniciar orden?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, iniciar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarOrdenes('INICIARORDEN', { id });
        json.success ? (Swal.fire('Listo', json.message, 'success'), cargarOrdenes())
                      : Swal.fire('Error', json.message, 'error');
    });
}

function finalizarOrden(id) {
    Swal.fire({
        title: '¿Finalizar orden?',
        text: 'Se marcará como completada.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, finalizar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarOrdenes('FINALIZARORDEN', { id });
        json.success ? (Swal.fire('Listo', json.message, 'success'), cargarOrdenes())
                      : Swal.fire('Error', json.message, 'error');
    });
}

function cancelarOrden(id) {
    Swal.fire({
        title: '¿Cancelar orden?',
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'Volver'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarOrdenes('CANCELARORDEN', { id });
        json.success ? (Swal.fire('Listo', json.message, 'success'), cargarOrdenes())
                      : Swal.fire('Error', json.message, 'error');
    });
}

function eliminarOrden(id) {
    Swal.fire({
        title: '¿Desactivar orden?',
        text: 'Podrás reactivarla luego desde el filtro de eliminadas.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarOrdenes('ELIMINARORDEN', { id });
        json.success ? (Swal.fire('Listo', json.message, 'success'), cargarOrdenes())
                      : Swal.fire('Error', json.message, 'error');
    });
}

function reactivarOrden(id) {
    llamarOrdenes('REACTIVARORDEN', { id }).then(json => {
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarOrdenes();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>