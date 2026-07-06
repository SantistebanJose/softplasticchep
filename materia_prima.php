<?php
$pageTitle    = 'Materia Prima';
$pageSubtitle = 'Insumos y materiales para producción';
$activePage   = 'materia_prima';

include("header.php");
?>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Materia Prima</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrear()">
            <i class="fa-solid fa-plus"></i> Nueva materia prima
        </button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <input type="text" id="f_texto" class="form-control" style="max-width:260px"
               placeholder="Buscar por nombre...">
        <select id="f_estado" class="form-select" style="max-width:160px">
            <option value="">Todos</option>
            <option value="activo" selected>Activos</option>
            <option value="inactivo">Inactivos</option>
        </select>
        <button class="pc-btn pc-btn-secondary" onclick="cargarMateriaPrima()">
            <i class="fa-solid fa-filter"></i> Filtrar
        </button>
    </div>

    <table class="pc-table" id="tablaMateriaPrima">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>U. Medida</th>
                <th>Stock actual</th>
                <th>Stock mínimo</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tbodyMateriaPrima">
            <tr><td colspan="6" style="text-align:center;">Cargando...</td></tr>
        </tbody>
    </table>
</div>

<!-- Modal Crear/Editar -->
<div class="modal fade" id="modalMateriaPrima" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formMateriaPrima">
        <div class="modal-header">
          <h5 class="modal-title" id="modalMateriaPrimaTitulo">Nueva materia prima</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="mp_id">

          <div class="mb-2">
            <label class="form-label">Nombre *</label>
            <input type="text" class="form-control" name="nombre" id="mp_nombre" required>
          </div>

          <div class="mb-2">
            <label class="form-label">Unidad de medida *</label>
            <input type="text" class="form-control" name="unidad_medida" id="mp_unidad_medida"
                   placeholder="Ej: kg, litros, unidades" required>
          </div>

          <div class="row">
            <div class="col-6 mb-2">
              <label class="form-label">Stock actual</label>
              <input type="number" step="0.01" min="0" class="form-control" name="stock_actual" id="mp_stock_actual" value="0">
            </div>
            <div class="col-6 mb-2">
              <label class="form-label">Stock mínimo</label>
              <input type="number" step="0.01" min="0" class="form-control" name="stock_minimo" id="mp_stock_minimo" value="0">
            </div>
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
const CONTROLADOR = 'controllers/clssMateriaPrima.php'; // clssMateriaPrima.php vive en su propia carpeta
const modalMateriaPrima = new bootstrap.Modal(document.getElementById('modalMateriaPrima'));

document.addEventListener('DOMContentLoaded', () => {
    cargarMateriaPrima().catch(err => {
        console.error('Error cargando datos iniciales:', err);
        document.getElementById('tbodyMateriaPrima').innerHTML =
            `<tr><td colspan="6" style="text-align:center;color:red;">Error de conexión con el servidor. Revisa la consola (F12).</td></tr>`;
    });
});

// ── Llamada genérica al controlador ─────────────────────────────────────────
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

// ── Listado ──────────────────────────────────────────────────────────────────
async function cargarMateriaPrima() {
    const texto  = document.getElementById('f_texto').value.trim();
    const estado = document.getElementById('f_estado').value;

    const json = await llamar('LISTARMATERIAPRIMA', { texto, estado });
    const tbody = document.getElementById('tbodyMateriaPrima');

    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;">${json.message}</td></tr>`;
        return;
    }

    const items = json.materia_prima || [];
    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No hay materia prima registrada.</td></tr>';
        return;
    }

    tbody.innerHTML = items.map(mp => {
        const bajoStock = parseFloat(mp.stock_actual) <= parseFloat(mp.stock_minimo);
        return `
        <tr id="fila-${mp.id}">
            <td>${mp.nombre}</td>
            <td>${mp.unidad_medida ?? '-'}</td>
            <td>
                ${mp.stock_actual}
                ${bajoStock ? '<span class="badge bg-warning text-dark ms-1" title="Stock bajo el mínimo">Bajo</span>' : ''}
            </td>
            <td>${mp.stock_minimo}</td>
            <td>${mp.activo
                ? '<span class="badge bg-success">Activo</span>'
                : '<span class="badge bg-secondary">Inactivo</span>'}
            </td>
            <td>
                <button class="pc-icon-btn" onclick="abrirModalEditar(${mp.id})" title="Editar">
                    <i class="fa-solid fa-pen"></i>
                </button>
                ${mp.activo
                    ? `<button class="pc-icon-btn" onclick="eliminarMateriaPrima(${mp.id})" title="Desactivar">
                           <i class="fa-solid fa-trash"></i></button>`
                    : `<button class="pc-icon-btn" onclick="reactivarMateriaPrima(${mp.id})" title="Reactivar">
                           <i class="fa-solid fa-rotate-left"></i></button>`
                }
            </td>
        </tr>
    `;
    }).join('');
}

// ── Crear / Editar ───────────────────────────────────────────────────────────
function abrirModalCrear() {
    document.getElementById('formMateriaPrima').reset();
    document.getElementById('mp_id').value = '';
    document.getElementById('modalMateriaPrimaTitulo').textContent = 'Nueva materia prima';
    modalMateriaPrima.show();
}

async function abrirModalEditar(id) {
    const json = await llamar('OBTENERMATERIAPRIMA', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    const mp = json.materia_prima;
    document.getElementById('modalMateriaPrimaTitulo').textContent = 'Editar materia prima';
    document.getElementById('mp_id').value = mp.id;
    document.getElementById('mp_nombre').value = mp.nombre ?? '';
    document.getElementById('mp_unidad_medida').value = mp.unidad_medida ?? '';
    document.getElementById('mp_stock_actual').value = mp.stock_actual ?? 0;
    document.getElementById('mp_stock_minimo').value = mp.stock_minimo ?? 0;

    modalMateriaPrima.show();
}

document.getElementById('formMateriaPrima').addEventListener('submit', async function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('accion', 'GUARDARMATERIAPRIMA');

    const resp = await fetch(CONTROLADOR, { method: 'POST', body: formData });
    const json = await resp.json();

    if (json.success) {
        modalMateriaPrima.hide();
        Swal.fire('Listo', json.message, 'success');
        cargarMateriaPrima();
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});

// ── Eliminar / Reactivar ─────────────────────────────────────────────────────
function eliminarMateriaPrima(id) {
    Swal.fire({
        title: '¿Desactivar materia prima?',
        text: 'Podrás reactivarla luego desde el listado de inactivas.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamar('ELIMINARMATERIAPRIMA', { id });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarMateriaPrima();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

function reactivarMateriaPrima(id) {
    llamar('REACTIVARMATERIAPRIMA', { id }).then(json => {
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarMateriaPrima();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>