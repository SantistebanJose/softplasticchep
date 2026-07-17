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
        <br>
        <input type="text" id="fm_texto" class="form-control" style="max-width:260px"
               placeholder="Buscar por nombre, forma o producto...">
        <select id="fm_estado" class="form-select" style="max-width:160px">
            <option value="">Todos</option>
            <option value="activa" selected>Activos</option>
            <option value="inactiva">Inactivos</option>
        </select>
    </div>

    <div class="pc-table-wrap pc-table-responsive-cards">
    <table class="pc-table" id="tablaMoldes">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Forma</th>
                <th>Productos</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tbodyMoldes">
            <tr><td colspan="5" style="text-align:center;">Cargando...</td></tr>
        </tbody>
    </table>
    </div>

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
            <label class="form-label">Forma *</label>
            <input type="text" class="form-control" name="forma" id="molde_forma"
                   placeholder="Ej: cuchara, cadena, gancho..." required>
          </div>

          <div class="mb-2">
            <label class="form-label">Productos *</label>
            <div id="molde_producto_checks" class="pc-checklist"></div>
            <div class="form-text">Toca todos los productos que usan este molde.</div>
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
const CONTROLADOR_MOLDES    = 'controllers/clssMoldes.php';    // clssMoldes.php vive en su propia carpeta
const CONTROLADOR_PRODUCTOS = 'controllers/clssProductos.php'; // para llenar el <select> de productos
const modalMolde = new bootstrap.Modal(document.getElementById('modalMolde'));

document.addEventListener('DOMContentLoaded', () => {
    Promise.all([
        cargarProductosSelect(),
        cargarMoldes()
    ]).catch(err => {
        console.error('Error cargando datos iniciales:', err);
        document.getElementById('tbodyMoldes').innerHTML =
            `<tr><td colspan="5" style="text-align:center;color:red;">Error de conexión con el servidor. Revisa la consola (F12).</td></tr>`;
    });

    // ── Búsqueda automática ──────────────────────────────────────────────────
    let debounceTimer = null;
    document.getElementById('fm_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarMoldes, 350);
    });

    document.getElementById('fm_estado').addEventListener('change', () => {
        cargarMoldes();
    });
});

// ── Llamadas genéricas a los controladores ──────────────────────────────────
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

async function llamarProductos(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_PRODUCTOS, {
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

async function cargarProductosSelect() {
    const json = await llamarProductos('LISTARPRODUCTOS', { texto: '', estado: 'activo' });
    const cont = document.getElementById('molde_producto_checks');

    if (!json.success) {
        console.error('No se pudo cargar la lista de productos:', json.message);
        return;
    }

    const productos = json.productos || [];
    cont.innerHTML = productos.map(p => `
        <div class="pc-check-item" onclick="toggleProductoCheck(${p.id})">
            <input type="checkbox" id="prodchk_${p.id}" value="${p.id}"
                   onclick="event.stopPropagation()" onchange="marcarCheckItem(${p.id})">
            <label for="prodchk_${p.id}" onclick="event.stopPropagation()">${p.codigo} - ${p.descripcion}</label>
        </div>
    `).join('');
}

function toggleProductoCheck(id) {
    const chk = document.getElementById(`prodchk_${id}`);
    chk.checked = !chk.checked;
    marcarCheckItem(id);
}

function marcarCheckItem(id) {
    const chk = document.getElementById(`prodchk_${id}`);
    chk.closest('.pc-check-item').classList.toggle('checked', chk.checked);
}

function obtenerProductoIdsSeleccionados() {
    return [...document.querySelectorAll('#molde_producto_checks input[type="checkbox"]:checked')]
        .map(chk => chk.value);
}

function marcarProductosSeleccionados(idsSeleccionados) {
    document.querySelectorAll('#molde_producto_checks input[type="checkbox"]').forEach(chk => {
        chk.checked = idsSeleccionados.includes(chk.value);
        marcarCheckItem(chk.value);
    });
}

// ── Listado ──────────────────────────────────────────────────────────────────
async function cargarMoldes() {
    const texto  = document.getElementById('fm_texto').value.trim();
    const estado = document.getElementById('fm_estado').value;

    const json = await llamarMoldes('LISTARMOLDES', { texto, estado });
    const tbody = document.getElementById('tbodyMoldes');

    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;">${json.message}</td></tr>`;
        return;
    }

    const moldes = json.moldes || [];
    if (moldes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No hay moldes registrados.</td></tr>';
        return;
    }

    tbody.innerHTML = moldes.map(m => {
        const productos = m.js_producto || [];
        const badgesProductos = productos.length
            ? productos.map(p => `<span class="badge bg-light text-dark border me-1 mb-1">${p.codigo} - ${p.descripcion}</span>`).join('')
            : '-';

        return `
    <tr id="fila-molde-${m.id}">
        <td data-label="Nombre">${m.nombre}</td>
        <td data-label="Forma">${m.forma ?? '-'}</td>
        <td data-label="Productos">${badgesProductos}</td>
        <td data-label="Estado">${!m.deleted_at
            ? '<span class="badge bg-success">Activo</span>'
            : '<span class="badge bg-secondary">Inactivo</span>'}
        </td>
        <td data-label="Acciones" class="pc-td-acciones">
            <button class="pc-icon-btn" onclick="abrirModalEditarMolde(${m.id})" title="Editar">
                <i class="fa-solid fa-pen"></i>
            </button>
            ${!m.deleted_at
                ? `<button class="pc-icon-btn" onclick="eliminarMolde(${m.id})" title="Desactivar">
                       <i class="fa-solid fa-trash"></i></button>`
                : `<button class="pc-icon-btn" onclick="reactivarMolde(${m.id})" title="Reactivar">
                       <i class="fa-solid fa-rotate-left"></i></button>`
            }
        </td>
    </tr>`;
    }).join('');
}

// ── Crear / Editar ───────────────────────────────────────────────────────────
function abrirModalCrearMolde() {
    document.getElementById('formMolde').reset();
    document.getElementById('molde_id').value = '';
    marcarProductosSeleccionados([]);
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
    document.getElementById('molde_forma').value = m.forma ?? '';

    const idsSeleccionados = (m.js_producto || []).map(p => String(p.producto_id));
    marcarProductosSeleccionados(idsSeleccionados);

    modalMolde.show();
}

document.getElementById('formMolde').addEventListener('submit', async function (e) {
    e.preventDefault();

    const productoIds = obtenerProductoIdsSeleccionados();
    if (productoIds.length === 0) {
        Swal.fire('Falta información', 'Selecciona al menos un producto asociado.', 'warning');
        return;
    }

    const formData = new FormData(this);
    formData.append('accion', 'GUARDARMOLDE');
    productoIds.forEach(pid => formData.append('producto_ids[]', pid));

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