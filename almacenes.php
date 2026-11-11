<?php
$pageTitle = 'Almacenes';
$pageSubtitle = 'Almacenes por sucursal';
$activePage = 'almacenes';
include 'header.php';
?>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div><h2>Almacenes</h2><small class="text-muted">Administra los almacenes asociados a cada sucursal.</small></div>
        <button type="button" class="pc-btn pc-btn-primary" onclick="abrirCrearAlmacen()"><i class="fa-solid fa-plus"></i> Nuevo almacén</button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <input id="alm_filtro_texto" class="form-control" style="max-width:280px" placeholder="Buscar almacén o sucursal...">
        <select id="alm_filtro_sucursal" class="form-select" style="max-width:220px"><option value="">Todas las sucursales</option></select>
        <select id="alm_filtro_estado" class="form-select" style="max-width:160px">
            <option value="activos" selected>Activos</option><option value="inactivos">Inactivos</option><option value="todos">Todos</option>
        </select>
    </div>

    <div class="pc-table-wrap pc-table-responsive-cards">
        <table class="pc-table">
            <thead><tr><th>#</th><th>Almacén</th><th>Sucursal</th><th>Descripción</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody id="alm_tbody"><tr><td colspan="6" class="text-center">Cargando almacenes...</td></tr></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalAlmacen" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formAlmacen">
                <div class="modal-header"><h5 class="modal-title" id="alm_modal_titulo">Nuevo almacén</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <div class="modal-body">
                    <input type="hidden" id="alm_id" value="0">
                    <div class="mb-3"><label for="alm_sucursal" class="form-label">Sucursal *</label><select id="alm_sucursal" class="form-select" required><option value="">Selecciona sucursal...</option></select></div>
                    <div class="mb-3"><label for="alm_nombre" class="form-label">Nombre del almacén *</label><input id="alm_nombre" class="form-control" maxlength="50" required></div>
                    <div class="mb-2"><label for="alm_descripcion" class="form-label">Descripción</label><textarea id="alm_descripcion" class="form-control" rows="3" placeholder="Opcional"></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar almacén</button></div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/device-tracking.js"></script>
<script src="assets/js/app-common.js"></script>
<script>
const URL_ALMACEN = 'controllers/clssAlmacen.php';
const URL_SUCURSAL = 'controllers/clssSucursal.php';
const modalAlmacen = new bootstrap.Modal(document.getElementById('modalAlmacen'));
let almacenesSucursales = [];

const htmlAlmacen = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const pedirAlmacen = (accion, params = {}) => llamar(URL_ALMACEN, accion, params);

document.addEventListener('DOMContentLoaded', async () => {
    try {
        await cargarSucursalesAlmacen();
        await cargarAlmacenes();
    } catch (error) {
        console.error(error);
        document.getElementById('alm_tbody').innerHTML = '<tr><td colspan="6" class="text-center text-danger">No se pudo cargar el listado.</td></tr>';
    }
    let timer = null;
    document.getElementById('alm_filtro_texto').addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(cargarAlmacenes, 300); });
    document.getElementById('alm_filtro_estado').addEventListener('change', cargarAlmacenes);
    document.getElementById('alm_filtro_sucursal').addEventListener('change', cargarAlmacenes);
    document.getElementById('formAlmacen').addEventListener('submit', guardarAlmacen);
});

async function cargarSucursalesAlmacen() {
    const json = await llamar(URL_SUCURSAL, 'LISTARSUCURSALES', { visibilidad: 'activas' });
    if (!json.success) throw new Error(json.message || 'No se pudieron cargar las sucursales.');
    almacenesSucursales = json.sucursales || [];
    const opciones = almacenesSucursales.map(s => `<option value="${Number(s.id)}">${htmlAlmacen(s.nombre)}</option>`).join('');
    document.getElementById('alm_filtro_sucursal').insertAdjacentHTML('beforeend', opciones);
    document.getElementById('alm_sucursal').insertAdjacentHTML('beforeend', opciones);
}

async function cargarAlmacenes() {
    const json = await pedirAlmacen('LISTARALMACENES', {
        texto: document.getElementById('alm_filtro_texto').value.trim(),
        sucursal_id: document.getElementById('alm_filtro_sucursal').value,
        estado: document.getElementById('alm_filtro_estado').value,
    });
    const tbody = document.getElementById('alm_tbody');
    if (!json.success) { tbody.innerHTML = `<tr><td colspan="6" class="text-center">${htmlAlmacen(json.message)}</td></tr>`; return; }
    const filas = json.almacenes || [];
    if (!filas.length) { tbody.innerHTML = '<tr><td colspan="6" class="text-center">No hay almacenes para mostrar.</td></tr>'; return; }
    tbody.innerHTML = filas.map(a => `<tr>
        <td data-label="#">${Number(a.id)}</td>
        <td data-label="Almacén"><strong>${htmlAlmacen(a.nombre)}</strong></td>
        <td data-label="Sucursal">${htmlAlmacen(a.sucursal_nombre || 'Sin sucursal')}</td>
        <td data-label="Descripción">${htmlAlmacen(a.descripcion || '-')}</td>
        <td data-label="Estado">${a.deleted_at ? '<span class="badge bg-secondary">Inactivo</span>' : '<span class="badge bg-success">Activo</span>'}</td>
        <td data-label="Acciones" class="pc-td-acciones">
            ${!a.deleted_at ? `<button type="button" class="pc-icon-btn" title="Editar" onclick="editarAlmacen(${Number(a.id)})"><i class="fa-solid fa-pen"></i></button><button type="button" class="pc-icon-btn" title="Desactivar" onclick="cambiarEstadoAlmacen(${Number(a.id)}, false)"><i class="fa-solid fa-trash"></i></button>` : `<button type="button" class="pc-icon-btn" title="Reactivar" onclick="cambiarEstadoAlmacen(${Number(a.id)}, true)"><i class="fa-solid fa-rotate-left"></i></button>`}
        </td>
    </tr>`).join('');
}

function abrirCrearAlmacen() {
    document.getElementById('formAlmacen').reset();
    document.getElementById('alm_id').value = '0';
    document.getElementById('alm_modal_titulo').textContent = 'Nuevo almacén';
    modalAlmacen.show();
}

async function editarAlmacen(id) {
    const json = await pedirAlmacen('OBTENERALMACEN', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }
    const a = json.almacen;
    document.getElementById('alm_id').value = a.id;
    document.getElementById('alm_sucursal').value = a.sucursal_id || '';
    document.getElementById('alm_nombre').value = a.nombre || '';
    document.getElementById('alm_descripcion').value = a.descripcion || '';
    document.getElementById('alm_modal_titulo').textContent = `Editar almacén #${a.id}`;
    modalAlmacen.show();
}

async function guardarAlmacen(evento) {
    evento.preventDefault();
    const json = await pedirAlmacen('GUARDARALMACEN', {
        id: document.getElementById('alm_id').value,
        sucursal_id: document.getElementById('alm_sucursal').value,
        nombre: document.getElementById('alm_nombre').value.trim(),
        descripcion: document.getElementById('alm_descripcion').value.trim(),
    });
    if (!json.success) { Swal.fire('No se pudo guardar', json.message, 'error'); return; }
    modalAlmacen.hide();
    await Swal.fire('Listo', json.message, 'success');
    await cargarAlmacenes();
}

async function cambiarEstadoAlmacen(id, reactivar) {
    if (!reactivar) {
        const confirmacion = await Swal.fire({ title: '¿Desactivar este almacén?', text: 'Podrás reactivarlo después. Los movimientos existentes se conservan.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Desactivar', cancelButtonText: 'Cancelar' });
        if (!confirmacion.isConfirmed) return;
    }
    const json = await pedirAlmacen(reactivar ? 'REACTIVARALMACEN' : 'ELIMINARALMACEN', { id });
    if (!json.success) { Swal.fire('No se pudo actualizar', json.message, 'error'); return; }
    await cargarAlmacenes();
    Swal.fire('Listo', json.message, 'success');
}
</script>

<?php require __DIR__ . '/footer.php'; ?>
