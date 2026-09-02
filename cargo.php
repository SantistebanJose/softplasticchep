<?php
$pageTitle    = 'Cargos';
$pageSubtitle = 'Cargos del personal';
$activePage   = 'cargo';

include("header.php");
?>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Cargos</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrearCargo()">
            <i class="fa-solid fa-plus"></i> Nuevo cargo
        </button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <br>
        <input type="text" id="fc_texto" class="form-control" style="max-width:260px"
               placeholder="Buscar por nombre...">
        <select id="fc_area" class="form-select" style="max-width:220px">
            <option value="">Todas las áreas</option>
        </select>
        <select id="fc_estado" class="form-select" style="max-width:160px">
            <option value="">Todos</option>
            <option value="activa" selected>Activos</option>
            <option value="inactiva">Inactivos</option>
        </select>
    </div>

    <div class="pc-table-wrap pc-table-responsive-cards">
    <table class="pc-table" id="tablaCargos">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Área</th>
                <th>Orden</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tbodyCargos">
            <tr><td colspan="5" style="text-align:center;">Cargando...</td></tr>
        </tbody>
    </table>
    </div>

</div>

<!-- Modal Crear/Editar -->
<div class="modal fade" id="modalCargo" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formCargo">
        <div class="modal-header">
          <h5 class="modal-title" id="modalCargoTitulo">Nuevo cargo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="cargo_id">

          <div class="mb-2">
            <label class="form-label">Nombre *</label>
            <input type="text" class="form-control text-uppercase" name="nombre" id="cargo_nombre" required>          </div>

          <div class="mb-2">
            <label class="form-label">Área *</label>
            <select class="form-select" name="area_id" id="cargo_area_id" required>
                <option value="">Selecciona un área...</option>
            </select>
          </div>

          <div class="mb-2">
            <label class="form-label">Orden</label>
            <input type="number" class="form-control" name="orden" id="cargo_orden" value="0" min="0">
            <div class="form-text">Define el orden de aparición en los listados (menor primero).</div>
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
const CONTROLADOR_CARGO = 'controllers/clssCargo.php'; // clssCargo.php vive en su propia carpeta
const CONTROLADOR_AREA  = 'controllers/clssArea.php';  // para poblar los selects de área
const modalCargo = new bootstrap.Modal(document.getElementById('modalCargo'));

let areasActivas = []; // cache simple para no repetir la llamada en cada apertura de modal

document.addEventListener('DOMContentLoaded', () => {
    Promise.all([cargarAreasEnSelects(), cargarCargos()]).catch(err => {
        console.error('Error cargando datos iniciales:', err);
        document.getElementById('tbodyCargos').innerHTML =
            `<tr><td colspan="5" style="text-align:center;color:red;">Error de conexión con el servidor. Revisa la consola (F12).</td></tr>`;
    });

    // ── Búsqueda automática ──────────────────────────────────────────────────
    let debounceTimer = null;
    document.getElementById('fc_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarCargos, 350);
    });

    document.getElementById('fc_estado').addEventListener('change', cargarCargos);
    document.getElementById('fc_area').addEventListener('change', cargarCargos);
});

// ── Llamadas genéricas a los controladores ───────────────────────────────────
async function llamarCargo(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_CARGO, {
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

async function llamarArea(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_AREA, {
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

// ── Áreas (para los selects de filtro y del modal) ───────────────────────────
async function cargarAreasEnSelects() {
    const json = await llamarArea('LISTARAREAS', { estado: 'activa' });
    if (!json.success) return;

    areasActivas = json.areas || [];

    const selFiltro = document.getElementById('fc_area');
    const selModal  = document.getElementById('cargo_area_id');

    const opciones = areasActivas.map(a => `<option value="${a.id}">${a.nombre}</option>`).join('');

    selFiltro.innerHTML = '<option value="">Todas las áreas</option>' + opciones;
    selModal.innerHTML  = '<option value="">Selecciona un área...</option>' + opciones;
}

// ── Listado ──────────────────────────────────────────────────────────────────
async function cargarCargos() {
    const texto  = document.getElementById('fc_texto').value.trim();
    const estado = document.getElementById('fc_estado').value;
    const areaId = document.getElementById('fc_area').value;

    const json = await llamarCargo('LISTARCARGOS', { texto, estado, area_id: areaId });
    const tbody = document.getElementById('tbodyCargos');

    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;">${json.message}</td></tr>`;
        return;
    }

    const cargos = json.cargos || [];
    if (cargos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No hay cargos registrados.</td></tr>';
        return;
    }

    tbody.innerHTML = cargos.map(c => `
    <tr id="fila-cargo-${c.id}">
        <td data-label="Nombre">${c.nombre}</td>
        <td data-label="Área">${c.area_nombre ?? '<span class="text-muted">Sin área</span>'}</td>
        <td data-label="Orden">${c.orden ?? 0}</td>
        <td data-label="Estado">${!c.deleted_at
            ? '<span class="badge bg-success">Activo</span>'
            : '<span class="badge bg-secondary">Inactivo</span>'}
        </td>
        <td data-label="Acciones" class="pc-td-acciones">
            <button class="pc-icon-btn" onclick="abrirModalEditarCargo(${c.id})" title="Editar">
                <i class="fa-solid fa-pen"></i>
            </button>
            ${!c.deleted_at
                ? `<button class="pc-icon-btn" onclick="eliminarCargo(${c.id})" title="Desactivar">
                       <i class="fa-solid fa-trash"></i></button>`
                : `<button class="pc-icon-btn" onclick="reactivarCargo(${c.id})" title="Reactivar">
                       <i class="fa-solid fa-rotate-left"></i></button>`
            }
        </td>
    </tr>`).join('');
}

// ── Crear / Editar ───────────────────────────────────────────────────────────
async function abrirModalCrearCargo() {
    document.getElementById('formCargo').reset();
    document.getElementById('cargo_id').value = '';
    document.getElementById('cargo_orden').value = '0';
    document.getElementById('modalCargoTitulo').textContent = 'Nuevo cargo';

    // Por si el usuario creó un área nueva y todavía no refrescó la página
    await cargarAreasEnSelects();

    modalCargo.show();
}

async function abrirModalEditarCargo(id) {
    await cargarAreasEnSelects(); // asegura el select actualizado antes de setear el valor

    const json = await llamarCargo('OBTENERCARGO', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    const c = json.cargo;
    document.getElementById('modalCargoTitulo').textContent = 'Editar cargo';
    document.getElementById('cargo_id').value = c.id;
    document.getElementById('cargo_nombre').value = c.nombre ?? '';
    document.getElementById('cargo_orden').value = c.orden ?? 0;
    document.getElementById('cargo_area_id').value = c.area_id ?? '';

    modalCargo.show();
}

document.getElementById('formCargo').addEventListener('submit', async function (e) {
    e.preventDefault();

    const formData = new FormData(this);
    formData.append('accion', 'GUARDARCARGO');

    const resp = await fetch(CONTROLADOR_CARGO, { method: 'POST', body: formData });
    const json = await resp.json();

    if (json.success) {
        modalCargo.hide();
        Swal.fire('Listo', json.message, 'success');
        cargarCargos();
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});

// ── Eliminar / Reactivar ─────────────────────────────────────────────────────
function eliminarCargo(id) {
    Swal.fire({
        title: '¿Desactivar cargo?',
        text: 'Podrás reactivarlo luego desde el listado de inactivos.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarCargo('ELIMINARCARGO', { id });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarCargos();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

function reactivarCargo(id) {
    llamarCargo('REACTIVARCARGO', { id }).then(json => {
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarCargos();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>