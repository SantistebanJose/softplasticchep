<?php
$pageTitle    = 'Sucursales';
$pageSubtitle = 'Puntos de venta / almacenamiento';
$activePage = 'sucursales';

include("header.php");
?>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Sucursales</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrearSucursal()">
            <i class="fa-solid fa-plus"></i> Nueva sucursal
        </button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <input type="text" id="fsu_texto" class="form-control" style="max-width:280px"
               placeholder="Buscar por nombre o dirección...">
        <select id="fsu_estado" class="form-select" style="max-width:160px">
            <option value="activas" selected>Activas</option>
            <option value="eliminadas">Inactivas</option>
            <option value="todas">Todas</option>
        </select>
    </div>

    <div class="pc-table-wrap pc-table-responsive-cards">
    <table class="pc-table" id="tablaSucursales">
        <thead>
            <tr>
                <th>#</th>
                <th>Nombre</th>
                <th>Dirección</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tbodySucursales">
            <tr><td colspan="5" style="text-align:center;">Cargando...</td></tr>
        </tbody>
    </table>
    </div>
</div>

<!-- Modal Crear/Editar -->
<div class="modal fade" id="modalSucursal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formSucursal">
        <div class="modal-header">
          <h5 class="modal-title" id="modalSucursalTitulo">Nueva sucursal</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Nombre *</label>
            <input type="text" class="form-control" id="su_nombre" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Dirección</label>
            <input type="text" class="form-control" id="su_direccion" placeholder="Opcional">
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
const CONTROLADOR_SUCURSAL = 'controllers/clssSucursal.php';
const modalSucursal = new bootstrap.Modal(document.getElementById('modalSucursal'));

let modoEdicionSucursal = false;
let sucursalIdActual = 0;

document.addEventListener('DOMContentLoaded', () => {
    cargarSucursales().catch(err => {
        console.error('Error cargando sucursales:', err);
        document.getElementById('tbodySucursales').innerHTML =
            `<tr><td colspan="5" style="text-align:center;color:red;">Error de conexión con el servidor. Revisa la consola (F12).</td></tr>`;
    });

    let debounceTimer = null;
    document.getElementById('fsu_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarSucursales, 350);
    });
    document.getElementById('fsu_estado').addEventListener('change', cargarSucursales);
});

async function llamarSucursal(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_SUCURSAL, {
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

function badgeEstadoSucursal(deleteAt) {
    return !deleteAt
        ? '<span class="badge bg-success">Activa</span>'
        : '<span class="badge bg-secondary">Inactiva</span>';
}

async function cargarSucursales() {
    const params = {
        texto: document.getElementById('fsu_texto').value.trim(),
        visibilidad: document.getElementById('fsu_estado').value,
    };

    const json = await llamarSucursal('LISTARSUCURSALES', params);
    const tbody = document.getElementById('tbodySucursales');

    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;">${json.message}</td></tr>`;
        return;
    }

    const sucursales = json.sucursales || [];
    if (sucursales.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No hay sucursales registradas.</td></tr>';
        return;
    }

    tbody.innerHTML = sucursales.map(s => `
        <tr id="fila-sucursal-${s.id}">
            <td data-label="#">${s.id}</td>
            <td data-label="Nombre">${s.nombre}</td>
            <td data-label="Dirección">${s.direccion ?? '-'}</td>
            <td data-label="Estado">${badgeEstadoSucursal(s.delete_at)}</td>
            <td data-label="Acciones" class="pc-td-acciones">
                <button class="pc-icon-btn" onclick="abrirModalEditarSucursal(${s.id})" title="Editar">
                    <i class="fa-solid fa-pen"></i>
                </button>
                ${!s.delete_at
                    ? `<button class="pc-icon-btn" onclick="eliminarSucursal(${s.id})" title="Desactivar">
                           <i class="fa-solid fa-trash"></i></button>`
                    : `<button class="pc-icon-btn" onclick="reactivarSucursal(${s.id})" title="Reactivar">
                           <i class="fa-solid fa-rotate-left"></i></button>`
                }
            </td>
        </tr>
    `).join('');
}

function limpiarFormularioSucursal() {
    document.getElementById('formSucursal').reset();
    sucursalIdActual = 0;
}

function abrirModalCrearSucursal() {
    limpiarFormularioSucursal();
    modoEdicionSucursal = false;
    document.getElementById('modalSucursalTitulo').textContent = 'Nueva sucursal';
    modalSucursal.show();
}

async function abrirModalEditarSucursal(id) {
    const json = await llamarSucursal('OBTENERSUCURSAL', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    limpiarFormularioSucursal();
    modoEdicionSucursal = true;
    sucursalIdActual = id;

    const s = json.sucursal;
    document.getElementById('modalSucursalTitulo').textContent = 'Editar sucursal #' + id;
    document.getElementById('su_nombre').value = s.nombre;
    document.getElementById('su_direccion').value = s.direccion ?? '';

    modalSucursal.show();
}

document.getElementById('formSucursal').addEventListener('submit', async function (e) {
    e.preventDefault();

    const params = {
        id: sucursalIdActual,
        nombre: document.getElementById('su_nombre').value.trim(),
        direccion: document.getElementById('su_direccion').value.trim(),
    };

    const json = await llamarSucursal('GUARDARSUCURSAL', params);

    if (json.success) {
        modalSucursal.hide();
        Swal.fire('Listo', json.message, 'success');
        cargarSucursales();
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});

function eliminarSucursal(id) {
    Swal.fire({
        title: '¿Desactivar esta sucursal?',
        text: 'Podrás reactivarla luego.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarSucursal('ELIMINARSUCURSAL', { id });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarSucursales();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

function reactivarSucursal(id) {
    llamarSucursal('REACTIVARSUCURSAL', { id }).then(json => {
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarSucursales();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>