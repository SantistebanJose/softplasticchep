<?php
$pageTitle    = 'Colores';
$pageSubtitle = 'Colores utilizados en producción';
$activePage   = 'colores';

include("header.php");
?>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Colores</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrearColor()">
            <i class="fa-solid fa-plus"></i> Nuevo color
        </button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <br>
        <input type="text" id="fcol_texto" class="form-control" style="max-width:260px"
               placeholder="Buscar por nombre...">
        <select id="fcol_estado" class="form-select" style="max-width:160px">
            <option value="">Todos</option>
            <option value="activa" selected>Activos</option>
            <option value="inactiva">Inactivos</option>
        </select>
    </div>

    <div class="pc-table-wrap pc-table-responsive-cards">
    <table class="pc-table" id="tablaColores">
        <thead>
            <tr>
                <th>Color</th>
                <th>Descripción</th>
                <th>RGB / HEX</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tbodyColores">
            <tr><td colspan="6" style="text-align:center;">Cargando...</td></tr>
        </tbody>
    </table>
    </div>
</div>

<!-- Modal Crear/Editar -->
<div class="modal fade" id="modalColor" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formColor">
        <div class="modal-header">
          <h5 class="modal-title" id="modalColorTitulo">Nuevo color</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="color_id">

          <div class="mb-2">
            <label class="form-label">Nombre *</label>
            <input type="text" class="form-control" name="nombre" id="color_nombre" required>
          </div>

          <div class="mb-2">
            <label class="form-label">Descripción</label>
            <textarea class="form-control" name="descripcion" id="color_descripcion" rows="2"></textarea>
          </div>

          <div class="mb-2">
            <label class="form-label">Color (RGB/HEX)</label>
            <div class="d-flex gap-2 align-items-center">
                <input type="color" class="form-control form-control-color" id="color_picker" value="#000000" title="Elegir color">
                <input type="text" class="form-control" name="rgb" id="color_rgb" placeholder="#000000 o rgb(0,0,0)">
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
const CONTROLADOR_COLORES = 'controllers/clssColor.php';
const modalColor = new bootstrap.Modal(document.getElementById('modalColor'));

document.addEventListener('DOMContentLoaded', () => {
    cargarColores().catch(err => {
        console.error('Error cargando datos iniciales:', err);
        document.getElementById('tbodyColores').innerHTML =
            `<tr><td colspan="6" style="text-align:center;color:red;">Error de conexión con el servidor. Revisa la consola (F12).</td></tr>`;
    });

    // ── Búsqueda automática ──────────────────────────────────────────────────
    let debounceTimer = null;
    document.getElementById('fcol_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarColores, 350);
    });

    document.getElementById('fcol_estado').addEventListener('change', () => {
        cargarColores();
    });

    // Sincroniza el color picker con el campo de texto RGB/HEX
    document.getElementById('color_picker').addEventListener('input', (e) => {
        document.getElementById('color_rgb').value = e.target.value;
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

const llamarColores = (accion, params = {}) => llamar(CONTROLADOR_COLORES, accion, params);

function muestraColor(rgb) {
    const valor = (rgb && rgb.trim() !== '') ? rgb.trim() : '#e9ecef';
    return `<span style="display:inline-block;width:24px;height:24px;border-radius:4px;border:1px solid #ccc;background:${valor}" title="${valor}"></span>`;
}

// ── Listado ──────────────────────────────────────────────────────────────────
async function cargarColores() {
    const texto  = document.getElementById('fcol_texto').value.trim();
    const estado = document.getElementById('fcol_estado').value;

    const json = await llamarColores('LISTARCOLORES', { texto, estado });
    const tbody = document.getElementById('tbodyColores');

    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;">${json.message}</td></tr>`;
        return;
    }

    const colores = json.colores || [];
    if (colores.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No hay colores registrados.</td></tr>';
        return;
    }

    tbody.innerHTML = colores.map(c => `
    <tr id="fila-color-${c.id}">
        <td data-label="Color">
            <div style="display:flex;align-items:center;gap:8px;">
                ${muestraColor(c.rgb)}
                <span>${c.nombre}</span>
            </div>
        </td>
        <td data-label="Descripción">${c.descripcion ?? '-'}</td>
        <td data-label="RGB / HEX">${c.rgb ?? '-'}</td>
        <td data-label="Estado">${!c.deleted_at
            ? '<span class="badge bg-success">Activo</span>'
            : '<span class="badge bg-secondary">Inactivo</span>'}
        </td>
        <td data-label="Acciones" class="pc-td-acciones">
            <button class="pc-icon-btn" onclick="abrirModalEditarColor(${c.id})" title="Editar">
                <i class="fa-solid fa-pen"></i>
            </button>
            ${!c.deleted_at
                ? `<button class="pc-icon-btn" onclick="eliminarColor(${c.id})" title="Desactivar">
                       <i class="fa-solid fa-trash"></i></button>`
                : `<button class="pc-icon-btn" onclick="reactivarColor(${c.id})" title="Reactivar">
                       <i class="fa-solid fa-rotate-left"></i></button>`
            }
        </td>
    </tr>
`).join('');
}

// ── Crear / Editar ───────────────────────────────────────────────────────────
function abrirModalCrearColor() {
    document.getElementById('formColor').reset();
    document.getElementById('color_id').value = '';
    document.getElementById('color_picker').value = '#000000';
    document.getElementById('modalColorTitulo').textContent = 'Nuevo color';
    modalColor.show();
}

async function abrirModalEditarColor(id) {
    const json = await llamarColores('OBTENERCOLOR', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    const c = json.color;
    document.getElementById('modalColorTitulo').textContent = 'Editar color';
    document.getElementById('color_id').value = c.id;
    document.getElementById('color_nombre').value = c.nombre ?? '';
    document.getElementById('color_descripcion').value = c.descripcion ?? '';
    document.getElementById('color_rgb').value = c.rgb ?? '';
    document.getElementById('color_picker').value = /^#[0-9a-fA-F]{6}$/.test(c.rgb) ? c.rgb : '#000000';

    modalColor.show();
}

document.getElementById('formColor').addEventListener('submit', async function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('accion', 'GUARDARCOLOR');

    const resp = await fetch(CONTROLADOR_COLORES, { method: 'POST', body: formData });
    const json = await resp.json();

    if (json.success) {
        modalColor.hide();
        Swal.fire('Listo', json.message, 'success');
        cargarColores();
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});

// ── Eliminar / Reactivar ─────────────────────────────────────────────────────
function eliminarColor(id) {
    Swal.fire({
        title: '¿Desactivar color?',
        text: 'Podrás reactivarlo luego desde el listado de inactivos.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarColores('ELIMINARCOLOR', { id });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarColores();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

function reactivarColor(id) {
    llamarColores('REACTIVARCOLOR', { id }).then(json => {
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarColores();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>