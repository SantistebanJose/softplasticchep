<?php
$pageTitle    = 'Máquinas';
$pageSubtitle = 'Máquinas de producción';
$activePage   = 'maquinas';

include("header.php");
?>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Máquinas</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrear()">
            <i class="fa-solid fa-plus"></i> Nueva máquina
        </button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <input type="text" id="f_texto" class="form-control" style="max-width:260px"
               placeholder="Buscar por nombre o ubicación...">
        <select id="f_estado" class="form-select" style="max-width:160px">
            <option value="">Todos</option>
            <option value="activa" selected>Activas</option>
            <option value="inactiva">Inactivas</option>
        </select>
        <button class="pc-btn pc-btn-secondary" onclick="cargarMaquinas()">
            <i class="fa-solid fa-filter"></i> Filtrar
        </button>
    </div>

    <table class="pc-table" id="tablaMaquinas">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Ubicación</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tbodyMaquinas">
            <tr><td colspan="5" style="text-align:center;">Cargando...</td></tr>
        </tbody>
    </table>
</div>

<!-- Modal Crear/Editar Máquina -->
<div class="modal fade" id="modalMaquina" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formMaquina">
        <div class="modal-header">
          <h5 class="modal-title" id="modalMaquinaTitulo">Nueva máquina</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="maq_id">

          <div class="mb-2">
            <label class="form-label">Nombre *</label>
            <input type="text" class="form-control" name="nombre" id="maq_nombre" required>
          </div>

          <div class="mb-2">
            <label class="form-label">Ubicación *</label>
            <input type="text" class="form-control" name="ubicacion" id="maq_ubicacion" required>
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
const CONTROLADOR = 'controllers/clssMaquinas.php'; // clssMaquinas.php vive en su propia carpeta
const CONTROLADOR_MOLDES = 'controllers/clssMoldes.php'; // clssMoldes.php vive en su propia carpeta
const modalMaquina = new bootstrap.Modal(document.getElementById('modalMaquina'));
const modalCambiarMolde = new bootstrap.Modal(document.getElementById('modalCambiarMolde'));

document.addEventListener('DOMContentLoaded', () => {
    cargarMaquinas().catch(err => {
        console.error('Error cargando datos iniciales:', err);
        document.getElementById('tbodyMaquinas').innerHTML =
            `<tr><td colspan="5" style="text-align:center;color:red;">Error de conexión con el servidor. Revisa la consola (F12).</td></tr>`;
    });
});

// ── Llamada genérica al controlador de máquinas ─────────────────────────────
async function llamar(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR, {
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

// ── Llamada genérica al controlador de moldes ───────────────────────────────
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
async function cargarMaquinas() {
    const texto  = document.getElementById('f_texto').value.trim();
    const estado = document.getElementById('f_estado').value;

    const json = await llamar('LISTARMAQUINAS', { texto, estado });
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
        <tr id="fila-${m.id}">
            <td>${m.nombre}</td>
            <td>${m.ubicacion ?? '-'}</td>
            <td>${m.estado === 'activa'
                ? '<span class="badge bg-success">Activa</span>'
                : '<span class="badge bg-secondary">Inactiva</span>'}
            </td>
            <td>
                ${m.molde_actual_nombre
                    ? `<span class="badge bg-info text-dark">${m.molde_actual_nombre} (${m.molde_actual_codigo ?? '-'})</span>`
                    : '<span class="text-muted">Sin molde</span>'
                }
            </td>
            <td>
                <button class="pc-icon-btn" onclick="abrirModalEditar(${m.id})" title="Editar">
                    <i class="fa-solid fa-pen"></i>
                </button>
                ${m.estado === 'activa'
                    ? `<button class="pc-icon-btn" onclick="abrirModalCambiarMolde(${m.id}, '${(m.nombre ?? '').replace(/'/g, "\\'")}', '${(m.molde_actual_nombre ?? '').replace(/'/g, "\\'")}')" title="Cambiar molde">
                           <i class="fa-solid fa-arrows-rotate"></i></button>`
                    : ''
                }
                ${m.estado === 'activa'
                    ? `<button class="pc-icon-btn" onclick="eliminarMaquina(${m.id})" title="Desactivar">
                           <i class="fa-solid fa-trash"></i></button>`
                    : `<button class="pc-icon-btn" onclick="reactivarMaquina(${m.id})" title="Reactivar">
                           <i class="fa-solid fa-rotate-left"></i></button>`
                }
            </td>
        </tr>
    `).join('');
}

// ── Crear / Editar máquina ───────────────────────────────────────────────────
function abrirModalCrear() {
    document.getElementById('formMaquina').reset();
    document.getElementById('maq_id').value = '';
    document.getElementById('modalMaquinaTitulo').textContent = 'Nueva máquina';
    modalMaquina.show();
}

async function abrirModalEditar(id) {
    const json = await llamar('OBTENERMAQUINA', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    const m = json.maquina;
    document.getElementById('modalMaquinaTitulo').textContent = 'Editar máquina';
    document.getElementById('maq_id').value = m.id;
    document.getElementById('maq_nombre').value = m.nombre ?? '';
    document.getElementById('maq_ubicacion').value = m.ubicacion ?? '';

    modalMaquina.show();
}

document.getElementById('formMaquina').addEventListener('submit', async function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('accion', 'GUARDARMAQUINA');

    const resp = await fetch(CONTROLADOR, { method: 'POST', body: formData });
    const json = await resp.json();

    if (json.success) {
        modalMaquina.hide();
        Swal.fire('Listo', json.message, 'success');
        cargarMaquinas();
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});

// ── Eliminar / Reactivar máquina ─────────────────────────────────────────────
function eliminarMaquina(id) {
    Swal.fire({
        title: '¿Desactivar máquina?',
        text: 'Podrás reactivarla luego desde el listado de inactivas.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamar('ELIMINARMAQUINA', { id });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarMaquinas();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

function reactivarMaquina(id) {
    llamar('REACTIVARMAQUINA', { id }).then(json => {
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarMaquinas();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

// ── Cambiar molde ────────────────────────────────────────────────────────────
async function abrirModalCambiarMolde(maquinaId, maquinaNombre, moldeActualNombre) {
    document.getElementById('cm_maquina_id').value = maquinaId;
    document.getElementById('cm_maquina_nombre').textContent = maquinaNombre || '-';
    document.getElementById('cm_molde_actual').textContent = moldeActualNombre || 'Sin molde';

    const select = document.getElementById('cm_molde_id');
    select.innerHTML = '<option value="">Cargando moldes...</option>';

    const json = await llamarMoldes('LISTARMOLDES', { estado: 'activa' });
    if (!json.success) {
        select.innerHTML = '<option value="">Error al cargar moldes</option>';
        modalCambiarMolde.show();
        return;
    }

    const moldes = json.moldes || [];
    if (moldes.length === 0) {
        select.innerHTML = '<option value="">No hay moldes activos</option>';
    } else {
        select.innerHTML = '<option value="">Selecciona un molde...</option>' +
            moldes.map(mo => `<option value="${mo.id}">${mo.nombre} (${mo.codigo ?? '-'})</option>`).join('');
    }

    modalCambiarMolde.show();
}

document.getElementById('formCambiarMolde').addEventListener('submit', async function (e) {
    e.preventDefault();
    const maquina_id = document.getElementById('cm_maquina_id').value;
    const molde_id   = document.getElementById('cm_molde_id').value;

    if (!molde_id) {
        Swal.fire('Atención', 'Debes seleccionar un molde.', 'warning');
        return;
    }

    const json = await llamar('CAMBIARMOLDE', { maquina_id, molde_id });
    if (json.success) {
        modalCambiarMolde.hide();
        Swal.fire('Listo', json.message, 'success');
        cargarMaquinas();
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});
</script>

<?php require __DIR__ . '/footer.php'; ?>