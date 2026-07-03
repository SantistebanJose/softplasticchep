<?php
$pageTitle    = 'Modelos';
$pageSubtitle = 'Modelos de producto por categoría';
$activePage   = 'modelos';

include("header.php");
?>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Modelos de producto</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrear()">
            <i class="fa-solid fa-plus"></i> Nuevo modelo
        </button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <input type="text" id="f_texto" class="form-control" style="max-width:260px"
               placeholder="Buscar por nombre...">
        <select id="f_categoria" class="form-select" style="max-width:220px">
            <option value="">Todas las categorías</option>
        </select>
        <select id="f_estado" class="form-select" style="max-width:160px">
            <option value="">Todos</option>
            <option value="activo" selected>Activos</option>
            <option value="inactivo">Inactivos</option>
        </select>
        <button class="pc-btn pc-btn-secondary" onclick="cargarModelos()">
            <i class="fa-solid fa-filter"></i> Filtrar
        </button>
    </div>

    <table class="pc-table" id="tablaModelos">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Categoría</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tbodyModelos">
            <tr><td colspan="4" style="text-align:center;">Cargando...</td></tr>
        </tbody>
    </table>
</div>

<!-- Modal Crear/Editar -->
<div class="modal fade" id="modalModelo" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formModelo">
        <div class="modal-header">
          <h5 class="modal-title" id="modalModeloTitulo">Nuevo modelo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="mod_id">

          <div class="mb-2">
            <label class="form-label">Categoría *</label>
            <select class="form-select" name="categoria_id" id="mod_categoria_id" required>
              <option value="">Seleccione...</option>
            </select>
          </div>

          <div class="mb-2">
            <label class="form-label">Nombre *</label>
            <input type="text" class="form-control" name="nombre" id="mod_nombre" required>
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
const CONTROLADOR = 'controllers/clssModelos.php'; // clssModelos.php vive en su propia carpeta
const modalModelo = new bootstrap.Modal(document.getElementById('modalModelo'));
let categoriasCache = [];

document.addEventListener('DOMContentLoaded', () => {
    cargarCategorias()
        .then(cargarModelos)
        .catch(err => {
            console.error('Error cargando datos iniciales:', err);
            document.getElementById('tbodyModelos').innerHTML =
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

// ── Catálogo de categorías (para los selects) ────────────────────────────────
async function cargarCategorias() {
    const json = await llamar('LISTARCATEGORIASACTIVAS');
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }
    categoriasCache = json.categorias || [];

    const selFiltro = document.getElementById('f_categoria');
    const selModal  = document.getElementById('mod_categoria_id');

    selFiltro.innerHTML = '<option value="">Todas las categorías</option>';
    selModal.innerHTML  = '<option value="">Seleccione...</option>';

    categoriasCache.forEach(c => {
        selFiltro.insertAdjacentHTML('beforeend', `<option value="${c.id}">${c.nombre}</option>`);
        selModal.insertAdjacentHTML('beforeend', `<option value="${c.id}">${c.nombre}</option>`);
    });
}

// ── Listado ──────────────────────────────────────────────────────────────────
async function cargarModelos() {
    const texto       = document.getElementById('f_texto').value.trim();
    const categoriaId = document.getElementById('f_categoria').value;
    const estado      = document.getElementById('f_estado').value;

    const json = await llamar('LISTARMODELOS', { texto, categoria_id: categoriaId, estado });
    const tbody = document.getElementById('tbodyModelos');

    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;">${json.message}</td></tr>`;
        return;
    }

    const modelos = json.modelos || [];
    if (modelos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No hay modelos registrados.</td></tr>';
        return;
    }

    tbody.innerHTML = modelos.map(m => `
        <tr id="fila-${m.id}">
            <td>${m.nombre}</td>
            <td>${m.categoria_nombre ?? '-'}</td>
            <td>${m.activo
                ? '<span class="badge bg-success">Activo</span>'
                : '<span class="badge bg-secondary">Inactivo</span>'}
            </td>
            <td>
                <button class="pc-icon-btn" onclick="abrirModalEditar(${m.id})" title="Editar">
                    <i class="fa-solid fa-pen"></i>
                </button>
                ${m.activo
                    ? `<button class="pc-icon-btn" onclick="eliminarModelo(${m.id})" title="Desactivar">
                           <i class="fa-solid fa-trash"></i></button>`
                    : `<button class="pc-icon-btn" onclick="reactivarModelo(${m.id})" title="Reactivar">
                           <i class="fa-solid fa-rotate-left"></i></button>`
                }
            </td>
        </tr>
    `).join('');
}

// ── Crear / Editar ───────────────────────────────────────────────────────────
function abrirModalCrear() {
    document.getElementById('formModelo').reset();
    document.getElementById('mod_id').value = '';
    document.getElementById('modalModeloTitulo').textContent = 'Nuevo modelo';
    modalModelo.show();
}

async function abrirModalEditar(id) {
    const json = await llamar('OBTENERMODELO', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    const m = json.modelo;
    document.getElementById('modalModeloTitulo').textContent = 'Editar modelo';
    document.getElementById('mod_id').value = m.id;
    document.getElementById('mod_categoria_id').value = m.categoria_id ?? '';
    document.getElementById('mod_nombre').value = m.nombre ?? '';

    modalModelo.show();
}

document.getElementById('formModelo').addEventListener('submit', async function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('accion', 'GUARDARMODELO');

    const resp = await fetch(CONTROLADOR, { method: 'POST', body: formData });
    const json = await resp.json();

    if (json.success) {
        modalModelo.hide();
        Swal.fire('Listo', json.message, 'success');
        cargarModelos();
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});

// ── Eliminar / Reactivar ─────────────────────────────────────────────────────
function eliminarModelo(id) {
    Swal.fire({
        title: '¿Desactivar modelo?',
        text: 'Podrás reactivarlo luego desde el listado de inactivos.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamar('ELIMINARMODELO', { id });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarModelos();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

function reactivarModelo(id) {
    llamar('REACTIVARMODELO', { id }).then(json => {
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarModelos();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>