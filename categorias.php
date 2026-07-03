<?php
$pageTitle    = 'Categorías';
$pageSubtitle = 'Categorías de producto (ganchos, pinzas, colgadores, etc.)';
$activePage   = 'categorias';

include("header.php");
?>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Categorías de producto</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrear()">
            <i class="fa-solid fa-plus"></i> Nueva categoría
        </button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <input type="text" id="f_texto" class="form-control" style="max-width:260px"
               placeholder="Buscar por nombre o descripción...">
        <select id="f_estado" class="form-select" style="max-width:160px">
            <option value="">Todos</option>
            <option value="activo" selected>Activos</option>
            <option value="inactivo">Inactivos</option>
        </select>
        <button class="pc-btn pc-btn-secondary" onclick="cargarCategorias()">
            <i class="fa-solid fa-filter"></i> Filtrar
        </button>
    </div>

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
          <input type="hidden" name="id" id="cat_id">

          <div class="mb-2">
            <label class="form-label">Nombre *</label>
            <input type="text" class="form-control" name="nombre" id="cat_nombre" required>
          </div>

          <div class="mb-2">
            <label class="form-label">Descripción</label>
            <textarea class="form-control" name="descripcion" id="cat_descripcion"></textarea>
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
const CONTROLADOR = 'controllers/clssCategorias.php'; // clssCategorias.php vive en su propia carpeta
const modalCategoria = new bootstrap.Modal(document.getElementById('modalCategoria'));

document.addEventListener('DOMContentLoaded', () => {
    cargarCategorias().catch(err => {
        console.error('Error cargando datos iniciales:', err);
        document.getElementById('tbodyCategorias').innerHTML =
            `<tr><td colspan="4" style="text-align:center;color:red;">Error de conexión con el servidor. Revisa la consola (F12).</td></tr>`;
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
async function cargarCategorias() {
    const texto  = document.getElementById('f_texto').value.trim();
    const estado = document.getElementById('f_estado').value;

    const json = await llamar('LISTARCATEGORIAS', { texto, estado });
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
        <tr id="fila-${c.id}">
            <td>${c.nombre}</td>
            <td>${c.descripcion ?? '-'}</td>
            <td>${c.activo
                ? '<span class="badge bg-success">Activo</span>'
                : '<span class="badge bg-secondary">Inactivo</span>'}
            </td>
            <td>
                <button class="pc-icon-btn" onclick="abrirModalEditar(${c.id})" title="Editar">
                    <i class="fa-solid fa-pen"></i>
                </button>
                ${c.activo
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
function abrirModalCrear() {
    document.getElementById('formCategoria').reset();
    document.getElementById('cat_id').value = '';
    document.getElementById('modalCategoriaTitulo').textContent = 'Nueva categoría';
    modalCategoria.show();
}

async function abrirModalEditar(id) {
    const json = await llamar('OBTENERCATEGORIA', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    const c = json.categoria;
    document.getElementById('modalCategoriaTitulo').textContent = 'Editar categoría';
    document.getElementById('cat_id').value = c.id;
    document.getElementById('cat_nombre').value = c.nombre ?? '';
    document.getElementById('cat_descripcion').value = c.descripcion ?? '';

    modalCategoria.show();
}

document.getElementById('formCategoria').addEventListener('submit', async function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('accion', 'GUARDARCATEGORIA');

    const resp = await fetch(CONTROLADOR, { method: 'POST', body: formData });
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
        const json = await llamar('ELIMINARCATEGORIA', { id });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarCategorias();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

function reactivarCategoria(id) {
    llamar('REACTIVARCATEGORIA', { id }).then(json => {
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