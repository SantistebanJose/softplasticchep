<?php
$pageTitle    = 'Materia Prima';
$pageSubtitle = 'Materiales e insumos de producción';
$activePage   = 'materiales';

include("header.php");
?>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Materia Prima</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrearMaterial()">
            <i class="fa-solid fa-plus"></i> Nuevo material
        </button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <br>
        <input type="text" id="fmat_texto" class="form-control" style="max-width:260px"
               placeholder="Buscar por nombre...">
        <select id="fmat_estado" class="form-select" style="max-width:160px">
            <option value="">Todos</option>
            <option value="activa" selected>Activos</option>
            <option value="inactiva">Inactivos</option>
        </select>
    </div>

    <div class="pc-table-wrap pc-table-responsive-cards">
    <table class="pc-table" id="tablaMateriales">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Unidad</th>
                <th>Stock actual</th>
                <th>Stock mínimo</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tbodyMateriales">
            <tr><td colspan="6" style="text-align:center;">Cargando...</td></tr>
        </tbody>
    </table>
    </div>
</div>

<!-- Modal Crear/Editar -->
<div class="modal fade" id="modalMaterial" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formMaterial">
        <div class="modal-header">
          <h5 class="modal-title" id="modalMaterialTitulo">Nuevo material</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="material_id">

          <div class="mb-2">
            <label class="form-label">Nombre *</label>
            <input type="text" class="form-control" name="nombre" id="material_nombre" required>
          </div>

          <div class="mb-2">
            <label class="form-label">Unidad de medida (opcional)</label>
            <select class="form-select" name="unidad_medida_id" id="material_unidad_medida_id">
                <option value="">Sin unidad de medida</option>
            </select>
            <div class="form-text">
                Solo se muestran unidades <strong>raíz</strong> (ej: Kilogramo, Unidad, Litro).
                Las unidades compuestas (ej: Saco 25kg, Rollo 50m) se eligen únicamente al registrar
                una compra, y el sistema convierte automáticamente a esta unidad para actualizar el stock.
            </div>
          </div>

          <div class="row">
            <div class="col-6 mb-2">
                <label class="form-label">Stock mínimo</label>
                <input type="number" step="0.01" min="0" class="form-control" name="stock_minimo" id="material_stock_minimo" value="0">
            </div>
            <div class="col-6 mb-2">
                <label class="form-label">Stock actual</label>
                <input type="number" step="0.01" min="0" class="form-control" name="stock_actual" id="material_stock_actual" value="0">
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
const CONTROLADOR_MATERIALES = 'controllers/clssMaterial.php';
const CONTROLADOR_UNIDADES   = 'controllers/clssUnidadMedida.php';
const modalMaterial = new bootstrap.Modal(document.getElementById('modalMaterial'));

document.addEventListener('DOMContentLoaded', () => {
    cargarUnidadesSelect();
    cargarMateriales().catch(err => {
        console.error('Error cargando datos iniciales:', err);
        document.getElementById('tbodyMateriales').innerHTML =
            `<tr><td colspan="6" style="text-align:center;color:red;">Error de conexión con el servidor. Revisa la consola (F12).</td></tr>`;
    });

    let debounceTimer = null;
    document.getElementById('fmat_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarMateriales, 350);
    });

    document.getElementById('fmat_estado').addEventListener('change', () => {
        cargarMateriales();
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

const llamarMateriales = (accion, params = {}) => llamar(CONTROLADOR_MATERIALES, accion, params);
const llamarUnidades   = (accion, params = {}) => llamar(CONTROLADOR_UNIDADES, accion, params);

// ── Selector de unidades: solo RAÍZ, activas ─────────────────────────────────
// Una unidad "compuesta" (ej. Saco 25kg, unidad_base_id -> Kilogramo) NO debe
// poder asignarse como unidad propia de un material: rompería la conversión
// que se usa en Compras (cantidad_base = cantidad_comprada * equivalencia),
// porque esa fórmula asume que el stock del material ya está en su unidad raíz.
// El filtro real vive en el backend (LISTARUNIDADESRAIZ + validación en
// GUARDARMATERIAL); aquí solo consumimos esa acción dedicada.
async function cargarUnidadesSelect() {
    const json = await llamarUnidades('LISTARUNIDADESRAIZ');
    const select = document.getElementById('material_unidad_medida_id');
    if (!json.success) return;

    const unidades = json.unidades || [];
    select.innerHTML = '<option value="">Sin unidad de medida</option>' +
        unidades.map(u => `<option value="${u.id}">${u.nombre} (${u.nombre_corto})</option>`).join('');
}

// ── Listado ──────────────────────────────────────────────────────────────────
async function cargarMateriales() {
    const texto  = document.getElementById('fmat_texto').value.trim();
    const estado = document.getElementById('fmat_estado').value;

    const json = await llamarMateriales('LISTARMATERIALES', { texto, estado });
    const tbody = document.getElementById('tbodyMateriales');

    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;">${json.message}</td></tr>`;
        return;
    }

    const materiales = json.materiales || [];
    if (materiales.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No hay materiales registrados.</td></tr>';
        return;
    }

    tbody.innerHTML = materiales.map(m => {
    const stockBajo = parseFloat(m.stock_actual) < parseFloat(m.stock_minimo);
    return `
    <tr id="fila-material-${m.id}">
        <td data-label="Nombre">${m.nombre}</td>
        <td data-label="Unidad">${m.unidad_nombre ? `${m.unidad_nombre} (${m.unidad_corto})` : '-'}</td>
        <td data-label="Stock actual">${stockBajo
            ? `<span class="badge bg-danger" title="Por debajo del stock mínimo">${m.stock_actual}</span>`
            : m.stock_actual}
        </td>
        <td data-label="Stock mínimo">${m.stock_minimo}</td>
        <td data-label="Estado">${!m.deleted_at
            ? '<span class="badge bg-success">Activo</span>'
            : '<span class="badge bg-secondary">Inactivo</span>'}
        </td>
        <td data-label="Acciones" class="pc-td-acciones">
            <button class="pc-icon-btn" onclick="abrirModalEditarMaterial(${m.id})" title="Editar">
                <i class="fa-solid fa-pen"></i>
            </button>
            ${!m.deleted_at
                ? `<button class="pc-icon-btn" onclick="eliminarMaterial(${m.id})" title="Desactivar">
                       <i class="fa-solid fa-trash"></i></button>`
                : `<button class="pc-icon-btn" onclick="reactivarMaterial(${m.id})" title="Reactivar">
                       <i class="fa-solid fa-rotate-left"></i></button>`
            }
        </td>
    </tr>
`}).join('');
}

// ── Crear / Editar ───────────────────────────────────────────────────────────
function abrirModalCrearMaterial() {
    document.getElementById('formMaterial').reset();
    document.getElementById('material_id').value = '';
    document.getElementById('modalMaterialTitulo').textContent = 'Nuevo material';
    modalMaterial.show();
}

async function abrirModalEditarMaterial(id) {
    const json = await llamarMateriales('OBTENERMATERIAL', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    const m = json.material;
    document.getElementById('modalMaterialTitulo').textContent = 'Editar material';
    document.getElementById('material_id').value = m.id;
    document.getElementById('material_nombre').value = m.nombre ?? '';
    document.getElementById('material_unidad_medida_id').value = m.unidad_medida_id ?? '';
    document.getElementById('material_stock_minimo').value = m.stock_minimo ?? 0;
    document.getElementById('material_stock_actual').value = m.stock_actual ?? 0;

    modalMaterial.show();
}

document.getElementById('formMaterial').addEventListener('submit', async function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('accion', 'GUARDARMATERIAL');

    const resp = await fetch(CONTROLADOR_MATERIALES, { method: 'POST', body: formData });
    const json = await resp.json();

    if (json.success) {
        modalMaterial.hide();
        Swal.fire('Listo', json.message, 'success');
        cargarMateriales();
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});

// ── Eliminar / Reactivar ─────────────────────────────────────────────────────
function eliminarMaterial(id) {
    Swal.fire({
        title: '¿Desactivar material?',
        text: 'Podrás reactivarlo luego desde el listado de inactivos.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarMateriales('ELIMINARMATERIAL', { id });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarMateriales();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

function reactivarMaterial(id) {
    llamarMateriales('REACTIVARMATERIAL', { id }).then(json => {
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarMateriales();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>