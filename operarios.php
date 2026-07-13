<?php
$pageTitle    = 'Operarios';
$pageSubtitle = 'Personal de planta';
$activePage = 'operarios';

include("header.php");
?>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Operarios</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrearOperario()">
            <i class="fa-solid fa-plus"></i> Nuevo operario
        </button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <input type="text" id="fop_texto" class="form-control" style="max-width:280px"
               placeholder="Buscar por nombre o cargo...">
        <select id="fop_estado" class="form-select" style="max-width:160px">
            <option value="activas" selected>Activos</option>
            <option value="eliminadas">Inactivos</option>
            <option value="todas">Todos</option>
        </select>
    </div>

    <div class="pc-table-wrap pc-table-responsive-cards">
    <table class="pc-table" id="tablaOperarios">
        <thead>
            <tr>
                <th>#</th>
                <th>Nombre completo</th>
                <th>Cargo</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tbodyOperarios">
            <tr><td colspan="5" style="text-align:center;">Cargando...</td></tr>
        </tbody>
    </table>
    </div>
</div>

<!-- Modal Crear/Editar -->
<div class="modal fade" id="modalOperario" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formOperario">
        <div class="modal-header">
          <h5 class="modal-title" id="modalOperarioTitulo">Nuevo operario</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Nombre completo *</label>
            <input type="text" class="form-control" id="op_nombre_completo" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Cargo</label>
            <input type="text" class="form-control" id="op_cargo" placeholder="Opcional">
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
const CONTROLADOR_OPERARIO = 'controllers/clssOperario.php';
const modalOperario = new bootstrap.Modal(document.getElementById('modalOperario'));

let modoEdicionOperario = false;
let operarioIdActual = 0;

document.addEventListener('DOMContentLoaded', () => {
    cargarOperarios().catch(err => {
        console.error('Error cargando operarios:', err);
        document.getElementById('tbodyOperarios').innerHTML =
            `<tr><td colspan="5" style="text-align:center;color:red;">Error de conexión con el servidor. Revisa la consola (F12).</td></tr>`;
    });

    let debounceTimer = null;
    document.getElementById('fop_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarOperarios, 350);
    });
    document.getElementById('fop_estado').addEventListener('change', cargarOperarios);
});

async function llamarOperario(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_OPERARIO, {
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

function badgeEstadoOperario(deletedAt) {
    return !deletedAt
        ? '<span class="badge bg-success">Activo</span>'
        : '<span class="badge bg-secondary">Inactivo</span>';
}

async function cargarOperarios() {
    const params = {
        texto: document.getElementById('fop_texto').value.trim(),
        visibilidad: document.getElementById('fop_estado').value,
    };

    const json = await llamarOperario('LISTAROPERARIOS', params);
    const tbody = document.getElementById('tbodyOperarios');

    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;">${json.message}</td></tr>`;
        return;
    }

    const operarios = json.operarios || [];
    if (operarios.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No hay operarios registrados.</td></tr>';
        return;
    }

    tbody.innerHTML = operarios.map(o => `
        <tr id="fila-operario-${o.id}">
            <td data-label="#">${o.id}</td>
            <td data-label="Nombre completo">${o.nombre_completo}</td>
            <td data-label="Cargo">${o.cargo ?? '-'}</td>
            <td data-label="Estado">${badgeEstadoOperario(o.deleted_at)}</td>
            <td data-label="Acciones" class="pc-td-acciones">
                <button class="pc-icon-btn" onclick="abrirModalEditarOperario(${o.id})" title="Editar">
                    <i class="fa-solid fa-pen"></i>
                </button>
                ${!o.deleted_at
                    ? `<button class="pc-icon-btn" onclick="eliminarOperario(${o.id})" title="Desactivar">
                           <i class="fa-solid fa-trash"></i></button>`
                    : `<button class="pc-icon-btn" onclick="reactivarOperario(${o.id})" title="Reactivar">
                           <i class="fa-solid fa-rotate-left"></i></button>`
                }
            </td>
        </tr>
    `).join('');
}

function limpiarFormularioOperario() {
    document.getElementById('formOperario').reset();
    operarioIdActual = 0;
}

function abrirModalCrearOperario() {
    limpiarFormularioOperario();
    modoEdicionOperario = false;
    document.getElementById('modalOperarioTitulo').textContent = 'Nuevo operario';
    modalOperario.show();
}

async function abrirModalEditarOperario(id) {
    const json = await llamarOperario('OBTENEROPERARIO', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    limpiarFormularioOperario();
    modoEdicionOperario = true;
    operarioIdActual = id;

    const o = json.operario;
    document.getElementById('modalOperarioTitulo').textContent = 'Editar operario #' + id;
    document.getElementById('op_nombre_completo').value = o.nombre_completo;
    document.getElementById('op_cargo').value = o.cargo ?? '';

    modalOperario.show();
}

document.getElementById('formOperario').addEventListener('submit', async function (e) {
    e.preventDefault();

    const params = {
        id: operarioIdActual,
        nombre_completo: document.getElementById('op_nombre_completo').value.trim(),
        cargo: document.getElementById('op_cargo').value.trim(),
    };

    const json = await llamarOperario('GUARDAROPERARIO', params);

    if (json.success) {
        modalOperario.hide();
        Swal.fire('Listo', json.message, 'success');
        cargarOperarios();
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});

function eliminarOperario(id) {
    Swal.fire({
        title: '¿Desactivar este operario?',
        text: 'Podrás reactivarlo luego.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarOperario('ELIMINAROPERARIO', { id });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarOperarios();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

function reactivarOperario(id) {
    llamarOperario('REACTIVAROPERARIO', { id }).then(json => {
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarOperarios();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>