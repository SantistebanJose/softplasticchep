<?php
$pageTitle    = 'Unidad de Medida';
$pageSubtitle = 'Unidades de medida para materiales';
$activePage   = 'unidad_medida';

include("header.php");
?>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Unidad de Medida</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrearUnidad()">
            <i class="fa-solid fa-plus"></i> Nueva unidad
        </button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <br>
        <input type="text" id="fu_texto" class="form-control" style="max-width:260px"
               placeholder="Buscar por nombre o abreviatura...">
        <select id="fu_estado" class="form-select" style="max-width:160px">
            <option value="">Todos</option>
            <option value="activa" selected>Activos</option>
            <option value="inactiva">Inactivos</option>
        </select>
    </div>

    <div class="pc-table-wrap pc-table-responsive-cards">
    <table class="pc-table" id="tablaUnidades">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Abreviatura</th>
                <th>Familia</th>
                <th>Equivalencia</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tbodyUnidades">
            <tr><td colspan="6" style="text-align:center;">Cargando...</td></tr>
        </tbody>
    </table>
    </div>
</div>

<!-- Modal Crear/Editar -->
<div class="modal fade" id="modalUnidad" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formUnidad">
        <div class="modal-header">
          <h5 class="modal-title" id="modalUnidadTitulo">Nueva unidad de medida</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="unidad_id">

          <div class="mb-2">
            <label class="form-label">Nombre *</label>
            <input type="text" class="form-control" name="nombre" id="unidad_nombre"
                   placeholder="Ej: Kilogramo, Saco 25kg" required>
          </div>

          <div class="mb-2">
            <label class="form-label">Abreviatura *</label>
            <input type="text" class="form-control" name="nombre_corto" id="unidad_nombre_corto"
                   placeholder="Ej: kg, saco25" required>
          </div>

          <div class="mb-2">
            <label class="form-label">Tipo *</label>
            <select class="form-select" id="unidad_tipo" onchange="toggleTipoUnidad()">
                <option value="raiz">Unidad raíz (kg, metro, unidad, litro...)</option>
                <option value="compuesta">Unidad compuesta (saco, bolsa, rollo, cartón...)</option>
            </select>
            <div class="form-text">
                Una unidad <strong>raíz</strong> es la base de una familia (ej: Kilogramo).
                Una unidad <strong>compuesta</strong> pertenece a la familia de una raíz y define
                cuánto equivale de esa raíz (ej: "Saco 25kg" equivale a 25 Kilogramos).
            </div>
          </div>

          <div class="mb-2" id="grupo_unidad_base" style="display:none;">
            <label class="form-label">Unidad base (familia) *</label>
            <select class="form-select" name="unidad_base_id" id="unidad_base_id">
                <option value="">Selecciona la unidad raíz...</option>
            </select>
          </div>

          <div class="mb-2" id="grupo_equivalencia" style="display:none;">
            <label class="form-label">Equivalencia *</label>
            <input type="number" step="0.0001" min="0.0001" class="form-control"
                   name="equivalencia" id="unidad_equivalencia" placeholder="Ej: 25">
            <div class="form-text" id="texto_ayuda_equivalencia">
                ¿A cuántas unidades base equivale una unidad de esta?
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
const CONTROLADOR_UNIDADES = 'controllers/clssUnidadMedida.php';
const modalUnidad = new bootstrap.Modal(document.getElementById('modalUnidad'));
let unidadIdEnEdicion = null; // para excluirla del select de unidad base al editar

document.addEventListener('DOMContentLoaded', () => {
    cargarUnidades().catch(err => {
        console.error('Error cargando datos iniciales:', err);
        document.getElementById('tbodyUnidades').innerHTML =
            `<tr><td colspan="6" style="text-align:center;color:red;">Error de conexión con el servidor. Revisa la consola (F12).</td></tr>`;
    });

    let debounceTimer = null;
    document.getElementById('fu_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarUnidades, 350);
    });

    document.getElementById('fu_estado').addEventListener('change', () => {
        cargarUnidades();
    });
});

// ── Llamada genérica al controlador ─────────────────────────────────────────
async function llamarUnidades(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_UNIDADES, {
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

// ── Toggle Raíz / Compuesta dentro del modal ─────────────────────────────────
function toggleTipoUnidad() {
    const esCompuesta = document.getElementById('unidad_tipo').value === 'compuesta';
    document.getElementById('grupo_unidad_base').style.display   = esCompuesta ? '' : 'none';
    document.getElementById('grupo_equivalencia').style.display  = esCompuesta ? '' : 'none';

    document.getElementById('unidad_base_id').required     = esCompuesta;
    document.getElementById('unidad_equivalencia').required = esCompuesta;

    if (!esCompuesta) {
        document.getElementById('unidad_base_id').value = '';
        document.getElementById('unidad_equivalencia').value = '';
    }
}

// Carga el select de unidades raíz (para elegir la familia de una compuesta).
// Excluye la unidad que se está editando (una unidad no puede ser su propia base).
async function cargarSelectUnidadesBase() {
    const json = await llamarUnidades('LISTARUNIDADESRAIZ');
    const select = document.getElementById('unidad_base_id');
    if (!json.success) return;

    const unidades = (json.unidades || []).filter(u => u.id != unidadIdEnEdicion);
    select.innerHTML = '<option value="">Selecciona la unidad raíz...</option>' +
        unidades.map(u => `<option value="${u.id}">${u.nombre} (${u.nombre_corto})</option>`).join('');
}

// ── Listado ──────────────────────────────────────────────────────────────────
async function cargarUnidades() {
    const texto  = document.getElementById('fu_texto').value.trim();
    const estado = document.getElementById('fu_estado').value;

    const json = await llamarUnidades('LISTARUNIDADESMEDIDA', { texto, estado });
    const tbody = document.getElementById('tbodyUnidades');

    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;">${json.message}</td></tr>`;
        return;
    }

    const unidades = json.unidades || [];
    if (unidades.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No hay unidades de medida registradas.</td></tr>';
        return;
    }

    tbody.innerHTML = unidades.map(u => `
    <tr id="fila-unidad-${u.id}">
        <td data-label="Nombre">${u.nombre}</td>
        <td data-label="Abreviatura">${u.nombre_corto ?? '-'}</td>
        <td data-label="Familia">${u.unidad_base_id
            ? `<span class="badge bg-info text-dark">${u.base_nombre} (${u.base_corto})</span>`
            : '<span class="badge bg-primary">Raíz</span>'}
        </td>
        <td data-label="Equivalencia">${u.unidad_base_id ? `${u.equivalencia} ${u.base_corto}` : '1 (es raíz)'}</td>
        <td data-label="Estado">${!u.deleted_at
            ? '<span class="badge bg-success">Activo</span>'
            : '<span class="badge bg-secondary">Inactivo</span>'}
        </td>
        <td data-label="Acciones" class="pc-td-acciones">
            <button class="pc-icon-btn" onclick="abrirModalEditarUnidad(${u.id})" title="Editar">
                <i class="fa-solid fa-pen"></i>
            </button>
            ${!u.deleted_at
                ? `<button class="pc-icon-btn" onclick="eliminarUnidad(${u.id})" title="Desactivar">
                       <i class="fa-solid fa-trash"></i></button>`
                : `<button class="pc-icon-btn" onclick="reactivarUnidad(${u.id})" title="Reactivar">
                       <i class="fa-solid fa-rotate-left"></i></button>`
            }
        </td>
    </tr>
`).join('');
}

// ── Crear / Editar ───────────────────────────────────────────────────────────
async function abrirModalCrearUnidad() {
    document.getElementById('formUnidad').reset();
    document.getElementById('unidad_id').value = '';
    document.getElementById('modalUnidadTitulo').textContent = 'Nueva unidad de medida';
    document.getElementById('unidad_tipo').value = 'raiz';
    unidadIdEnEdicion = null;
    await cargarSelectUnidadesBase();
    toggleTipoUnidad();
    modalUnidad.show();
}

async function abrirModalEditarUnidad(id) {
    const json = await llamarUnidades('OBTENERUNIDADMEDIDA', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    const u = json.unidad;
    unidadIdEnEdicion = u.id;

    document.getElementById('modalUnidadTitulo').textContent = 'Editar unidad de medida';
    document.getElementById('unidad_id').value = u.id;
    document.getElementById('unidad_nombre').value = u.nombre ?? '';
    document.getElementById('unidad_nombre_corto').value = u.nombre_corto ?? '';

    await cargarSelectUnidadesBase();

    const esCompuesta = !!u.unidad_base_id;
    document.getElementById('unidad_tipo').value = esCompuesta ? 'compuesta' : 'raiz';
    toggleTipoUnidad();

    if (esCompuesta) {
        document.getElementById('unidad_base_id').value = u.unidad_base_id;
        document.getElementById('unidad_equivalencia').value = u.equivalencia;
    }

    modalUnidad.show();
}

document.getElementById('formUnidad').addEventListener('submit', async function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('accion', 'GUARDARUNIDADMEDIDA');

    // Si es raíz, no mandamos unidad_base_id ni equivalencia (el back los ignora/fuerza a 1 igual)
    if (document.getElementById('unidad_tipo').value === 'raiz') {
        formData.delete('unidad_base_id');
        formData.delete('equivalencia');
    }

    const resp = await fetch(CONTROLADOR_UNIDADES, { method: 'POST', body: formData });
    const json = await resp.json();

    if (json.success) {
        modalUnidad.hide();
        Swal.fire('Listo', json.message, 'success');
        cargarUnidades();
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});

// ── Eliminar / Reactivar ─────────────────────────────────────────────────────
function eliminarUnidad(id) {
    Swal.fire({
        title: '¿Desactivar unidad de medida?',
        text: 'Podrás reactivarla luego desde el listado de inactivos.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarUnidades('ELIMINARUNIDADMEDIDA', { id });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarUnidades();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

function reactivarUnidad(id) {
    llamarUnidades('REACTIVARUNIDADMEDIDA', { id }).then(json => {
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarUnidades();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>