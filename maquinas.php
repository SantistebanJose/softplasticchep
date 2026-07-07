<?php
$pageTitle    = 'Máquinas';
$pageSubtitle = 'Máquinas de producción';
$activePage   = 'maquina';

include("header.php");
?>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Máquinas</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrearMaquina()">
            <i class="fa-solid fa-plus"></i> Nueva máquina
        </button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <br>
        <input type="text" id="fmaq_texto" class="form-control" style="max-width:260px"
               placeholder="Buscar por nombre...">
        <select id="fmaq_estado_op" class="form-select" style="max-width:160px">
            <option value="">Todos</option>
            <option value="activa" selected>Activos</option>
            <option value="inactiva">Inactivos</option>
        </select>
        <select id="fmaq_estado_maq" class="form-select" style="max-width:180px">
            <option value="">Estado: todos</option>
            <option value="A">Activa</option>
            <option value="M">Mantenimiento</option>
        </select>
    </div>

    <div class="pc-table-wrap pc-table-responsive-cards">
    <table class="pc-table" id="tablaMaquinas">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Estado</th>
                <th>Registro</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tbodyMaquinas">
            <tr><td colspan="5" style="text-align:center;">Cargando...</td></tr>
        </tbody>
    </table>
    </div>
</div>

<!-- Modal Crear/Editar -->
<div class="modal fade" id="modalMaquina" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formMaquina">
        <div class="modal-header">
          <h5 class="modal-title" id="modalMaquinaTitulo">Nueva máquina</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="maquina_id">

          <div class="mb-2">
            <label class="form-label">Nombre *</label>
            <input type="text" class="form-control" name="nombre" id="maquina_nombre" required>
          </div>

          <div class="mb-2">
            <label class="form-label">Descripción</label>
            <textarea class="form-control" name="descripcion" id="maquina_descripcion" rows="2"></textarea>
          </div>

          <div class="mb-2">
            <label class="form-label">Estado</label>
            <select class="form-select" name="estado" id="maquina_estado">
                <option value="A">Activa</option>
                <option value="M">Mantenimiento</option>
            </select>
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
const CONTROLADOR_MAQUINAS = 'controllers/clssMaquina.php';
const modalMaquina = new bootstrap.Modal(document.getElementById('modalMaquina'));

document.addEventListener('DOMContentLoaded', () => {
    cargarMaquinas().catch(err => {
        console.error('Error cargando datos iniciales:', err);
        document.getElementById('tbodyMaquinas').innerHTML =
            `<tr><td colspan="5" style="text-align:center;color:red;">Error de conexión con el servidor. Revisa la consola (F12).</td></tr>`;
    });

    // ── Búsqueda automática ──────────────────────────────────────────────────
    let debounceTimer = null;
    document.getElementById('fmaq_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarMaquinas, 350);
    });

    document.getElementById('fmaq_estado_op').addEventListener('change', () => {
        cargarMaquinas();
    });

    document.getElementById('fmaq_estado_maq').addEventListener('change', () => {
        cargarMaquinas();
    });
});

// ── Llamadas genéricas ───────────────────────────────────────────────────────
async function llamar(url, accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(url, {
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

const llamarMaquinas = (accion, params = {}) => llamar(CONTROLADOR_MAQUINAS, accion, params);

function textoEstado(estado) {
    if (estado === 'A') return '<span class="badge bg-success">Activa</span>';
    if (estado === 'M') return '<span class="badge bg-warning text-dark">Mantenimiento</span>';
    return '<span class="badge bg-secondary">-</span>';
}

// ── Listado ──────────────────────────────────────────────────────────────────
async function cargarMaquinas() {
    const texto      = document.getElementById('fmaq_texto').value.trim();
    const estado     = document.getElementById('fmaq_estado_op').value;
    const estadoMaq  = document.getElementById('fmaq_estado_maq').value;

    const json = await llamarMaquinas('LISTARMAQUINAS', { texto, estado, estado_maquina: estadoMaq });
    const tbody = document.getElementById('tbodyMaquinas');

    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;">${json.message}</td></tr>`;
        return;
    }

    const maquinas = json.maquinas || [];
    if (maquinas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No hay máquinas registradas.</td></tr>';
        return;
    }

    tbody.innerHTML = maquinas.map(m => `
    <tr id="fila-maquina-${m.id}">
        <td data-label="Nombre">${m.nombre}</td>
        <td data-label="Descripción">${m.descripcion ?? '-'}</td>
        <td data-label="Estado">${textoEstado(m.estado)}</td>
        <td data-label="Registro">${!m.deleted_at
            ? '<span class="badge bg-success">Activo</span>'
            : '<span class="badge bg-secondary">Inactivo</span>'}
        </td>
        <td data-label="Acciones" class="pc-td-acciones">
            <button class="pc-icon-btn" onclick="abrirModalEditarMaquina(${m.id})" title="Editar">
                <i class="fa-solid fa-pen"></i>
            </button>
            ${!m.deleted_at
                ? `<button class="pc-icon-btn" onclick="eliminarMaquina(${m.id})" title="Desactivar">
                       <i class="fa-solid fa-trash"></i></button>`
                : `<button class="pc-icon-btn" onclick="reactivarMaquina(${m.id})" title="Reactivar">
                       <i class="fa-solid fa-rotate-left"></i></button>`
            }
        </td>
    </tr>
`).join('');
}

// ── Crear / Editar ───────────────────────────────────────────────────────────
function abrirModalCrearMaquina() {
    document.getElementById('formMaquina').reset();
    document.getElementById('maquina_id').value = '';
    document.getElementById('maquina_estado').value = 'A';
    document.getElementById('modalMaquinaTitulo').textContent = 'Nueva máquina';
    modalMaquina.show();
}

async function abrirModalEditarMaquina(id) {
    const json = await llamarMaquinas('OBTENERMAQUINA', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    const m = json.maquina;
    document.getElementById('modalMaquinaTitulo').textContent = 'Editar máquina';
    document.getElementById('maquina_id').value = m.id;
    document.getElementById('maquina_nombre').value = m.nombre ?? '';
    document.getElementById('maquina_descripcion').value = m.descripcion ?? '';
    document.getElementById('maquina_estado').value = m.estado ?? 'A';

    modalMaquina.show();
}

document.getElementById('formMaquina').addEventListener('submit', async function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('accion', 'GUARDARMAQUINA');

    const resp = await fetch(CONTROLADOR_MAQUINAS, { method: 'POST', body: formData });
    const json = await resp.json();

    if (json.success) {
        modalMaquina.hide();
        Swal.fire('Listo', json.message, 'success');
        cargarMaquinas();
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});

// ── Eliminar / Reactivar ─────────────────────────────────────────────────────
function eliminarMaquina(id) {
    Swal.fire({
        title: '¿Desactivar máquina?',
        text: 'Podrás reactivarla luego desde el listado de inactivos.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarMaquinas('ELIMINARMAQUINA', { id });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarMaquinas();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

function reactivarMaquina(id) {
    llamarMaquinas('REACTIVARMAQUINA', { id }).then(json => {
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarMaquinas();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>