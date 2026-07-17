<?php
$pageTitle    = 'Categorías de Material';
$pageSubtitle = 'Categorías de calidad del material (1° de primera, 2° de segunda, etc.)';
$activePage   = 'categoria_material';

include("header.php");
?>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Categorías de Material</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrearCategoria()">
            <i class="fa-solid fa-plus"></i> Nueva categoría
        </button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <br>
        <input type="text" id="fc_texto" class="form-control" style="max-width:260px"
               placeholder="Buscar por nombre o descripción...">
        <select id="fc_estado" class="form-select" style="max-width:160px">
            <option value="">Todos</option>
            <option value="activa" selected>Activas</option>
            <option value="inactiva">Inactivas</option>
        </select>
    </div>

    <div class="pc-table-wrap pc-table-responsive-cards">
    <table class="pc-table" id="tablaCategorias">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tbodyCategorias">
            <tr><td colspan="4" style="text-align:center;">Cargando...</td></tr>
        </tbody>
    </table>
    </div>

</div>

<!-- Modal Crear/Editar -->
<div class="modal fade" id="modalCategoria" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formCategoria">
        <div class="modal-header">
          <h5 class="modal-title" id="modalCategoriaTitulo">Nueva categoría</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="categoria_id">

          <div class="mb-2">
            <label class="form-label">Nombre *</label>
            <input type="text" class="form-control" name="nombre" id="categoria_nombre"
                   placeholder="Ej: 1° DE PRIMERA" required>
          </div>

          <div class="mb-2">
            <label class="form-label">Descripción</label>
            <textarea class="form-control" name="descripcion" id="categoria_descripcion" rows="3"></textarea>
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
const CONTROLADOR_CATEGORIAS = 'controllers/clssCategoriaMaterial.php';
const modalCategoria = new bootstrap.Modal(document.getElementById('modalCategoria'));

document.addEventListener('DOMContentLoaded', () => {
    cargarCategorias().catch(err => {
        console.error('Error cargando datos iniciales:', err);
        document.getElementById('tbodyCategorias').innerHTML =
            `<tr><td colspan="4" style="text-align:center;color:red;">Error de conexión con el servidor. Revisa la consola (F12).</td></tr>`;
    });

    // ── Búsqueda automática ──────────────────────────────────────────────────
    let debounceTimer = null;
    document.getElementById('fc_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarCategorias, 350);
    });

    document.getElementById('fc_estado').addEventListener('change', () => {
        cargarCategorias();
    });
});

// ── Llamadas genéricas al controlador ────────────────────────────────────────
async function llamarCategorias(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_CATEGORIAS, {
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
async function cargarCategorias() {
    const texto  = document.getElementById('fc_texto').value.trim();
    const estado = document.getElementById('fc_estado').value;

    const json = await llamarCategorias('LISTARCATEGORIASMATERIAL', { texto, estado });
    const tbody = document.getElementById('tbodyCategorias');

    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;">${json.message}</td></tr>`;
        return;
    }

    const categorias = json.categorias || [];
    if (categorias.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No hay categorías registradas.</td></tr>';
        return;
    }

    tbody.innerHTML = categorias.map(c => `
    <tr id="fila-categoria-${c.id}">
        <td data-label="Nombre">${c.nombre}</td>
        <td data-label="Descripción">${c.descripcion ?? '-'}</td>
        <td data-label="Estado">${!c.deleted_at
            ? '<span class="badge bg-success">Activo</span>'
            : '<span class="badge bg-secondary">Inactivo</span>'}
        </td>
        <td data-label="Acciones" class="pc-td-acciones">
            <button class="pc-icon-btn" onclick="abrirModalEditarCategoria(${c.id})" title="Editar">
                <i class="fa-solid fa-pen"></i>
            </button>
            ${!c.deleted_at
                ? `<button class="pc-icon-btn" onclick="eliminarCategoria(${c.id})" title="Desactivar">
                       <i class="fa-solid fa-trash"></i></button>`
                : `<button class="pc-icon-btn" onclick="reactivarCategoria(${c.id})" title="Reactivar">
                       <i class="fa-solid fa-rotate-left"></i></button>`
            }
        </td>
    </tr>
`).join('');
}

// ── Crear / Editar ───────────────────────────────────────────────────────────
function abrirModalCrearCategoria() {
    document.getElementById('formCategoria').reset();
    document.getElementById('categoria_id').value = '';
    document.getElementById('modalCategoriaTitulo').textContent = 'Nueva categoría';
    modalCategoria.show();
}

async function abrirModalEditarCategoria(id) {
    const json = await llamarCategorias('OBTENERCATEGORIAMATERIAL', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    const c = json.categoria;
    document.getElementById('modalCategoriaTitulo').textContent = 'Editar categoría';
    document.getElementById('categoria_id').value = c.id;
    document.getElementById('categoria_nombre').value = c.nombre ?? '';
    document.getElementById('categoria_descripcion').value = c.descripcion ?? '';

    modalCategoria.show();
}

document.getElementById('formCategoria').addEventListener('submit', async function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('accion', 'GUARDARCATEGORIAMATERIAL');

    const resp = await fetch(CONTROLADOR_CATEGORIAS, { method: 'POST', body: formData });
    const json = await resp.json();

    if (json.success) {
        modalCategoria.hide();
        Swal.fire('Listo', json.message, 'success');
        cargarCategorias();
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});

// ── Eliminar / Reactivar ─────────────────────────────────────────────────────
function eliminarCategoria(id) {
    Swal.fire({
        title: '¿Desactivar categoría?',
        text: 'Podrás reactivarla luego desde el listado de inactivas.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarCategorias('ELIMINARCATEGORIAMATERIAL', { id });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarCategorias();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

function reactivarCategoria(id) {
    llamarCategorias('REACTIVARCATEGORIAMATERIAL', { id }).then(json => {
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarCategorias();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>