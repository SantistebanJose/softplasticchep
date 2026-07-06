<?php
$pageTitle    = 'Moldes';
$pageSubtitle = 'Moldes de producción';
$activePage   = 'moldes';

include("header.php");
?>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Moldes</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrearMolde()">
            <i class="fa-solid fa-plus"></i> Nuevo molde
        </button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <input type="text" id="fm_texto" class="form-control" style="max-width:260px"
               placeholder="Buscar por nombre o código...">
        <select id="fm_estado" class="form-select" style="max-width:160px">
            <option value="">Todos</option>
            <option value="activa" selected>Activos</option>
            <option value="inactiva">Inactivos</option>
        </select>
        <button class="pc-btn pc-btn-secondary" onclick="cargarMoldes()">
            <i class="fa-solid fa-filter"></i> Filtrar
        </button>
    </div>

    <table class="pc-table" id="tablaMoldes">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Código</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tbodyMoldes">
            <tr><td colspan="4" style="text-align:center;">Cargando...</td></tr>
        </tbody>
    </table>
</div>

<!-- Modal Crear/Editar -->
<div class="modal fade" id="modalMolde" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formMolde">
        <div class="modal-header">
          <h5 class="modal-title" id="modalMoldeTitulo">Nuevo molde</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="molde_id">

          <div class="mb-2">
            <label class="form-label">Nombre *</label>
            <input type="text" class="form-control" name="nombre" id="molde_nombre" required>
          </div>

          <div class="mb-2">
            <label class="form-label">Código *</label>
            <input type="text" class="form-control" name="codigo" id="molde_codigo" required>
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
const CONTROLADOR_MOLDES = 'controllers/clssMoldes.php'; // clssMoldes.php vive en su propia carpeta
const modalMolde = new bootstrap.Modal(document.getElementById('modalMolde'));

document.addEventListener('DOMContentLoaded', () => {
    cargarMoldes().catch(err => {
        console.error('Error cargando datos iniciales:', err);
        document.getElementById('tbodyMoldes').innerHTML =
            `<tr><td colspan="4" style="text-align:center;color:red;">Error de conexión con el servidor. Revisa la consola (F12).</td></tr>`;
    });
});

// ── Llamada genérica al controlador ─────────────────────────────────────────
async function llamarMoldes(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_MOLDES, {
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

// ── Listado ──────────────────────────────────────────────────────────────────
async function cargarMoldes() {
    const texto  = document.getElementById('fm_texto').value.trim();
    const estado = document.getElementById('fm_estado').value;

    const json = await llamarMoldes('LISTARMOLDES', { texto, estado });
    const tbody = document.getElementById('tbodyMoldes');

    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;">${json.message}</td></tr>`;
        return;
    }

    const moldes = json.moldes || [];
    if (moldes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No hay moldes registrados.</td></tr>';
        return;
    }

    tbody.innerHTML = moldes.map(m => `
        <tr id="fila-molde-${m.id}">
            <td>${m.nombre}</td>
            <td>${m.codigo ?? '-'}</td>
            <td>${(m.activo === true || m.activo === 't')
                ? '<span class="badge bg-success">Activo</span>'
                : '<span class="badge bg-secondary">Inactivo</span>'}
            </td>
            <td>
                <button class="pc-icon-btn" onclick="abrirModalEditarMolde(${m.id})" title="Editar">
                    <i class="fa-solid fa-pen"></i>
                </button>
                ${(m.activo === true || m.activo === 't')
                    ? `<button class="pc-icon-btn" onclick="eliminarMolde(${m.id})" title="Desactivar">
                           <i class="fa-solid fa-trash"></i></button>`
                    : `<button class="pc-icon-btn" onclick="reactivarMolde(${m.id})" title="Reactivar">
                           <i class="fa-solid fa-rotate-left"></i></button>`
                }
            </td>
        </tr>
    `).join('');
}

// ── Crear / Editar ───────────────────────────────────────────────────────────
function abrirModalCrearMolde() {
    document.getElementById('formMolde').reset();
    document.getElementById('molde_id').value = '';
    document.getElementById('modalMoldeTitulo').textContent = 'Nuevo molde';
    modalMolde.show();
}

async function abrirModalEditarMolde(id) {
    const json = await llamarMoldes('OBTENERMOLDE', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    const m = json.molde;
    document.getElementById('modalMoldeTitulo').textContent = 'Editar molde';
    document.getElementById('molde_id').value = m.id;
    document.getElementById('molde_nombre').value = m.nombre ?? '';
    document.getElementById('molde_codigo').value = m.codigo ?? '';

    modalMolde.show();
}

document.getElementById('formMolde').addEventListener('submit', async function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('accion', 'GUARDARMOLDE');

    const resp = await fetch(CONTROLADOR_MOLDES, { method: 'POST', body: formData });
    const json = await resp.json();

    if (json.success) {
        modalMolde.hide();
        Swal.fire('Listo', json.message, 'success');
        cargarMoldes();
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});

// ── Eliminar / Reactivar ─────────────────────────────────────────────────────
function eliminarMolde(id) {
    Swal.fire({
        title: '¿Desactivar molde?',
        text: 'Podrás reactivarlo luego desde el listado de inactivos.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarMoldes('ELIMINARMOLDE', { id });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarMoldes();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

function reactivarMolde(id) {
    llamarMoldes('REACTIVARMOLDE', { id }).then(json => {
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarMoldes();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>