<?php
$pageTitle    = 'Áreas';
$pageSubtitle = 'Áreas del personal';
$activePage   = 'area';

include("header.php");
?>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Áreas</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrearArea()">
            <i class="fa-solid fa-plus"></i> Nueva área
        </button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <br>
        <input type="text" id="fa_texto" class="form-control" style="max-width:260px"
               placeholder="Buscar por nombre o descripción...">
        <select id="fa_estado" class="form-select" style="max-width:160px">
            <option value="">Todos</option>
            <option value="activa" selected>Activas</option>
            <option value="inactiva">Inactivas</option>
        </select>
    </div>

    <div class="pc-table-wrap pc-table-responsive-cards">
    <table class="pc-table" id="tablaAreas">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Cargos</th>
                <th>Orden</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tbodyAreas">
            <tr><td colspan="6" style="text-align:center;">Cargando...</td></tr>
        </tbody>
    </table>
    </div>

</div>

<!-- Modal Crear/Editar -->
<div class="modal fade" id="modalArea" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formArea">
        <div class="modal-header">
          <h5 class="modal-title" id="modalAreaTitulo">Nueva área</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="area_id">

          <div class="mb-2">
            <label class="form-label">Nombre *</label>
            <input type="text" class="form-control text-uppercase" name="nombre" id="area_nombre" required>
          </div>

          <div class="mb-2">
            <label class="form-label">Descripción</label>
            <textarea class="form-control" name="descripcion" id="area_descripcion" rows="2"></textarea>
          </div>

          <div class="mb-2">
            <label class="form-label">Orden</label>
            <input type="number" class="form-control" name="orden" id="area_orden" value="0" min="0">
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

<!-- Modal Ver cargos del área (solo lectura, viene de js_cargos) -->
<div class="modal fade" id="modalCargosArea" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Cargos de <span id="cargosAreaNombre"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <ul class="list-group" id="listaCargosArea">
        </ul>
        <div class="form-text mt-2">
            Esta lista se calcula automáticamente a partir de los cargos activos
            asignados a esta área. Para agregar o quitar cargos, ve al módulo de Cargos.
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
const CONTROLADOR_AREA = 'controllers/clssArea.php'; // clssArea.php vive en su propia carpeta
const modalArea       = new bootstrap.Modal(document.getElementById('modalArea'));
const modalCargosArea = new bootstrap.Modal(document.getElementById('modalCargosArea'));

document.addEventListener('DOMContentLoaded', () => {
    cargarAreas().catch(err => {
        console.error('Error cargando datos iniciales:', err);
        document.getElementById('tbodyAreas').innerHTML =
            `<tr><td colspan="6" style="text-align:center;color:red;">Error de conexión con el servidor. Revisa la consola (F12).</td></tr>`;
    });

    // ── Búsqueda automática ──────────────────────────────────────────────────
    let debounceTimer = null;
    document.getElementById('fa_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarAreas, 350);
    });

    document.getElementById('fa_estado').addEventListener('change', cargarAreas);
});

// ── Llamada genérica al controlador ──────────────────────────────────────────
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

// ── Listado ──────────────────────────────────────────────────────────────────
let areasCache = {}; // id -> area, para no volver a pedir el detalle al abrir el modal de cargos

async function cargarAreas() {
    const texto  = document.getElementById('fa_texto').value.trim();
    const estado = document.getElementById('fa_estado').value;

    const json = await llamarArea('LISTARAREAS', { texto, estado });
    const tbody = document.getElementById('tbodyAreas');

    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;">${json.message}</td></tr>`;
        return;
    }

    const areas = json.areas || [];
    areasCache = {};
    areas.forEach(a => areasCache[a.id] = a);

    if (areas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No hay áreas registradas.</td></tr>';
        return;
    }

    tbody.innerHTML = areas.map(a => {
        const cargos = a.js_cargos || [];
        const badgeCargos = cargos.length > 0
            ? `<button type="button" class="btn btn-sm btn-outline-secondary" onclick="verCargosArea(${a.id})">
                   ${cargos.length} cargo${cargos.length === 1 ? '' : 's'}
               </button>`
            : '<span class="text-muted">Sin cargos</span>';

        return `
    <tr id="fila-area-${a.id}">
        <td data-label="Nombre">${a.nombre}</td>
        <td data-label="Descripción">${a.descripcion ?? ''}</td>
        <td data-label="Cargos">${badgeCargos}</td>
        <td data-label="Orden">${a.orden ?? 0}</td>
        <td data-label="Estado">${!a.deleted_at
            ? '<span class="badge bg-success">Activa</span>'
            : '<span class="badge bg-secondary">Inactiva</span>'}
        </td>
        <td data-label="Acciones" class="pc-td-acciones">
            <button class="pc-icon-btn" onclick="abrirModalEditarArea(${a.id})" title="Editar">
                <i class="fa-solid fa-pen"></i>
            </button>
            ${!a.deleted_at
                ? `<button class="pc-icon-btn" onclick="eliminarArea(${a.id})" title="Desactivar">
                       <i class="fa-solid fa-trash"></i></button>`
                : `<button class="pc-icon-btn" onclick="reactivarArea(${a.id})" title="Reactivar">
                       <i class="fa-solid fa-rotate-left"></i></button>`
            }
        </td>
    </tr>`;
    }).join('');
}

// ── Ver cargos de un área (solo lectura) ─────────────────────────────────────
function verCargosArea(id) {
    const a = areasCache[id];
    if (!a) return;

    document.getElementById('cargosAreaNombre').textContent = a.nombre;

    const cargos = a.js_cargos || [];
    const lista = document.getElementById('listaCargosArea');
    lista.innerHTML = cargos.length > 0
        ? cargos.map(c => `<li class="list-group-item">${c.nombre}</li>`).join('')
        : '<li class="list-group-item text-muted">Esta área no tiene cargos activos.</li>';

    modalCargosArea.show();
}

// ── Crear / Editar ───────────────────────────────────────────────────────────
function abrirModalCrearArea() {
    document.getElementById('formArea').reset();
    document.getElementById('area_id').value = '';
    document.getElementById('area_orden').value = '0';
    document.getElementById('modalAreaTitulo').textContent = 'Nueva área';
    modalArea.show();
}

async function abrirModalEditarArea(id) {
    const json = await llamarArea('OBTENERAREA', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    const a = json.area;
    document.getElementById('modalAreaTitulo').textContent = 'Editar área';
    document.getElementById('area_id').value = a.id;
    document.getElementById('area_nombre').value = a.nombre ?? '';
    document.getElementById('area_descripcion').value = a.descripcion ?? '';
    document.getElementById('area_orden').value = a.orden ?? 0;

    modalArea.show();
}

document.getElementById('formArea').addEventListener('submit', async function (e) {
    e.preventDefault();

    const formData = new FormData(this);
    formData.append('accion', 'GUARDARAREA');

    const resp = await fetch(CONTROLADOR_AREA, { method: 'POST', body: formData });
    const json = await resp.json();

    if (json.success) {
        modalArea.hide();
        Swal.fire('Listo', json.message, 'success');
        cargarAreas();
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});

// ── Eliminar / Reactivar ─────────────────────────────────────────────────────
function eliminarArea(id) {
    Swal.fire({
        title: '¿Desactivar área?',
        text: 'Solo puedes desactivar un área si ya no tiene cargos activos asignados.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarArea('ELIMINARAREA', { id });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarAreas();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

function reactivarArea(id) {
    llamarArea('REACTIVARAREA', { id }).then(json => {
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarAreas();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>