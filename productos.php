<?php
$pageTitle    = 'Productos';
$pageSubtitle = 'Catálogo de mercadería para la venta';
$activePage   = 'productos';

include("header.php");
?>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Mercadería para la venta</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrear()">
            <i class="fa-solid fa-plus"></i> Nuevo producto
        </button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <input type="text" id="f_texto" class="form-control" style="max-width:260px"
               placeholder="Buscar por código o descripción...">
        <select id="f_estado" class="form-select" style="max-width:160px">
            <option value="">Todos</option>
            <option value="activo" selected>Activos</option>
            <option value="inactivo">Inactivos</option>
        </select>
        <button class="pc-btn pc-btn-secondary" onclick="cargarProductos()">
            <i class="fa-solid fa-filter"></i> Filtrar
        </button>
    </div>

    <table class="pc-table" id="tablaProductos">
        <thead>
            <tr>
                <th>Código</th>
                <th>Descripción</th>
                <th>U. Medida</th>
                <th>Equivale</th>
                <th>Peso unit. (g)</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tbodyProductos">
            <tr><td colspan="7" style="text-align:center;">Cargando...</td></tr>
        </tbody>
    </table>
</div>

<!-- Modal Crear/Editar -->
<div class="modal fade" id="modalProducto" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formProducto">
        <div class="modal-header">
          <h5 class="modal-title" id="modalProductoTitulo">Nuevo producto</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="prod_id">

          <div class="mb-2">
            <label class="form-label">Código *</label>
            <input type="text" class="form-control" name="codigo" id="prod_codigo" required>
          </div>

          <div class="mb-2">
            <label class="form-label">Descripción *</label>
            <input type="text" class="form-control" name="descripcion" id="prod_descripcion" required>
          </div>

          <div class="row">
            <div class="col-6 mb-2">
              <label class="form-label">Unidad de venta *</label>
              <select class="form-select" name="unidad_venta_id" id="prod_unidad_venta" required>
                <option value="">Seleccione...</option>
              </select>
            </div>
            <div class="col-6 mb-2">
              <label class="form-label">Peso unitario (g)</label>
              <input type="number" step="0.001" min="0" class="form-control" name="peso_unitario_g" id="prod_peso">
            </div>
          </div>

          <div class="row">
            <div class="col-6 mb-2">
              <label class="form-label">Cantidad equivale</label>
              <input type="number" step="0.01" min="0" class="form-control" name="cant_equivale" id="prod_cant_equivale"
                     placeholder="Ej: 24">
            </div>
            <div class="col-6 mb-2">
              <label class="form-label">Unidad equivale</label>
              <select class="form-select" name="unidad_equivale_id" id="prod_unidad_equivale">
                <option value="">Seleccione...</option>
              </select>
            </div>
          </div>
          <small class="text-muted">
            Ej: código PI, U. Medida "PK", equivale a 24 "GRUESAS".
          </small>
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
const CONTROLADOR = 'controllers/clssProductos.php'; // clssProductos.php vive en su propia carpeta
const modalProducto = new bootstrap.Modal(document.getElementById('modalProducto'));
let unidadesCache = [];

document.addEventListener('DOMContentLoaded', () => {
    cargarUnidades()
        .then(cargarProductos)
        .catch(err => {
            console.error('Error cargando datos iniciales:', err);
            document.getElementById('tbodyProductos').innerHTML =
                `<tr><td colspan="7" style="text-align:center;color:red;">Error de conexión con el servidor. Revisa la consola (F12).</td></tr>`;
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

// ── Catálogo de unidades ────────────────────────────────────────────────────
async function cargarUnidades() {
    const json = await llamar('LISTARUNIDADES');
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }
    unidadesCache = json.unidades || [];

    const selVenta    = document.getElementById('prod_unidad_venta');
    const selEquivale = document.getElementById('prod_unidad_equivale');
    selVenta.innerHTML    = '<option value="">Seleccione...</option>';
    selEquivale.innerHTML = '<option value="">Seleccione...</option>';

    unidadesCache.forEach(u => {
        const label = `${u.codigo} - ${u.nombre}`;
        selVenta.insertAdjacentHTML('beforeend', `<option value="${u.id}">${label}</option>`);
        selEquivale.insertAdjacentHTML('beforeend', `<option value="${u.id}">${label}</option>`);
    });
}

// ── Listado ──────────────────────────────────────────────────────────────────
async function cargarProductos() {
    const texto  = document.getElementById('f_texto').value.trim();
    const estado = document.getElementById('f_estado').value;

    const json = await llamar('LISTARPRODUCTOS', { texto, estado });
    const tbody = document.getElementById('tbodyProductos');

    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;">${json.message}</td></tr>`;
        return;
    }

    const productos = json.productos || [];
    if (productos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No hay productos registrados.</td></tr>';
        return;
    }

    tbody.innerHTML = productos.map(p => `
        <tr id="fila-${p.id}">
            <td>${p.codigo}</td>
            <td>${p.descripcion}</td>
            <td>${p.unidad_venta_codigo ?? '-'}</td>
            <td>${p.cant_equivale ? p.cant_equivale + ' ' + (p.unidad_equivale_codigo ?? '') : '-'}</td>
            <td>${p.peso_unitario_g ?? '-'}</td>
            <td>${p.activo
                ? '<span class="badge bg-success">Activo</span>'
                : '<span class="badge bg-secondary">Inactivo</span>'}
            </td>
            <td>
                <button class="pc-icon-btn" onclick="abrirModalEditar(${p.id})" title="Editar">
                    <i class="fa-solid fa-pen"></i>
                </button>
                ${p.activo
                    ? `<button class="pc-icon-btn" onclick="eliminarProducto(${p.id})" title="Desactivar">
                           <i class="fa-solid fa-trash"></i></button>`
                    : `<button class="pc-icon-btn" onclick="reactivarProducto(${p.id})" title="Reactivar">
                           <i class="fa-solid fa-rotate-left"></i></button>`
                }
            </td>
        </tr>
    `).join('');
}

// ── Crear / Editar ───────────────────────────────────────────────────────────
function abrirModalCrear() {
    document.getElementById('formProducto').reset();
    document.getElementById('prod_id').value = '';
    document.getElementById('modalProductoTitulo').textContent = 'Nuevo producto';
    modalProducto.show();
}

async function abrirModalEditar(id) {
    const json = await llamar('OBTENERPRODUCTO', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    const p = json.producto;
    document.getElementById('modalProductoTitulo').textContent = 'Editar producto';
    document.getElementById('prod_id').value = p.id;
    document.getElementById('prod_codigo').value = p.codigo ?? '';
    document.getElementById('prod_descripcion').value = p.descripcion ?? '';
    document.getElementById('prod_unidad_venta').value = p.unidad_venta_id ?? '';
    document.getElementById('prod_unidad_equivale').value = p.unidad_equivale_id ?? '';
    document.getElementById('prod_cant_equivale').value = p.cant_equivale ?? '';
    document.getElementById('prod_peso').value = p.peso_unitario_g ?? '';

    modalProducto.show();
}

document.getElementById('formProducto').addEventListener('submit', async function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('accion', 'GUARDARPRODUCTO');

    const resp = await fetch(CONTROLADOR, { method: 'POST', body: formData });
    const json = await resp.json();

    if (json.success) {
        modalProducto.hide();
        Swal.fire('Listo', json.message, 'success');
        cargarProductos();
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});

// ── Eliminar / Reactivar ─────────────────────────────────────────────────────
function eliminarProducto(id) {
    Swal.fire({
        title: '¿Desactivar producto?',
        text: 'Podrás reactivarlo luego desde el listado de inactivos.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamar('ELIMINARPRODUCTO', { id });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarProductos();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

function reactivarProducto(id) {
    llamar('REACTIVARPRODUCTO', { id }).then(json => {
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarProductos();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>