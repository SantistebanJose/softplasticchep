<?php
$pageTitle    = 'Proveedores';
$pageSubtitle = 'Proveedores de materiales';
$activePage = 'proveedores';

include("header.php");
?>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Proveedores</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrearProveedor()">
            <i class="fa-solid fa-plus"></i> Nuevo proveedor
        </button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <br>
        <input type="text" id="fprov_texto" class="form-control" style="max-width:280px"
               placeholder="Buscar por razón social, nombre comercial o RUC/DNI...">
        <select id="fprov_estado" class="form-select" style="max-width:160px">
            <option value="">Todos</option>
            <option value="activa" selected>Activos</option>
            <option value="inactiva">Inactivos</option>
        </select>
    </div>

    <div class="pc-table-wrap pc-table-responsive-cards">
    <table class="pc-table" id="tablaProveedores">
        <thead>
            <tr>
                <th>RUC/DNI</th>
                <th>Razón social</th>
                <th>Nombre comercial</th>
                <th>Correo</th>
                <th>Teléfonos</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tbodyProveedores">
            <tr><td colspan="7" style="text-align:center;">Cargando...</td></tr>
        </tbody>
    </table>
    </div>
</div>

<!-- Modal Crear/Editar -->
<div class="modal fade" id="modalProveedor" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="formProveedor">
        <div class="modal-header">
          <h5 class="modal-title" id="modalProveedorTitulo">Nuevo proveedor</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">

          <!-- RUC / DNI + consulta API -->
          <div class="mb-2">
            <label class="form-label">RUC (11 dígitos) o DNI (8 dígitos) *</label>
            <div class="d-flex gap-2">
                <input type="text" class="form-control" name="ruc" id="prov_ruc"
                       maxlength="11" inputmode="numeric" required>
                <button type="button" class="btn btn-outline-primary" id="btnConsultarRuc" onclick="consultarDocumentoProveedor()">
                    <i class="fa-solid fa-magnifying-glass"></i> Consultar
                </button>
            </div>
            <div class="form-text" id="prov_consulta_info"></div>
          </div>

          <div class="mb-2">
            <label class="form-label">Razón social / Nombre completo *</label>
            <input type="text" class="form-control" name="razon_social" id="prov_razon_social" required>
          </div>

          <div class="mb-2">
            <label class="form-label">Nombre comercial</label>
            <input type="text" class="form-control" name="nombre_comercial" id="prov_nombre_comercial">
          </div>

          <div class="row">
            <div class="col-md-6 mb-2">
                <label class="form-label">Correo</label>
                <input type="email" class="form-control" name="correo" id="prov_correo">
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Ubigeo</label>
                <input type="text" class="form-control" name="ubigeo" id="prov_ubigeo">
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">&nbsp;</label>
            </div>
          </div>

          <div class="mb-2">
            <label class="form-label">Ubicación / Dirección</label>
            <input type="text" class="form-control" name="ubicacion" id="prov_ubicacion">
          </div>

          <!-- Teléfonos de contacto (dinámico) -->
          <div class="mb-3">
            <label class="form-label d-flex justify-content-between align-items-center">
                Teléfonos de contacto
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="agregarFilaTelefono()">
                    <i class="fa-solid fa-plus"></i> Agregar
                </button>
            </label>
            <div id="prov_telefonos_wrap"></div>
          </div>

          <!-- Datos crudos de la última consulta a la API, para guardarlos en js_consulta_api -->
          <input type="hidden" name="js_consulta_api" id="prov_js_consulta_api" value="">
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
const CONTROLADOR_PROVEEDORES = 'controllers/clssProveedor.php';
const modalProveedor = new bootstrap.Modal(document.getElementById('modalProveedor'));

let modoEdicion = false; // true = editando (RUC ya no se puede cambiar)

document.addEventListener('DOMContentLoaded', () => {
    cargarProveedores().catch(err => {
        console.error('Error cargando datos iniciales:', err);
        document.getElementById('tbodyProveedores').innerHTML =
            `<tr><td colspan="7" style="text-align:center;color:red;">Error de conexión con el servidor. Revisa la consola (F12).</td></tr>`;
    });

    // Búsqueda automática
    let debounceTimer = null;
    document.getElementById('fprov_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarProveedores, 350);
    });

    document.getElementById('fprov_estado').addEventListener('change', cargarProveedores);

    // Solo dígitos en el campo de documento
    ['prov_ruc'].forEach(id => {
        document.getElementById(id).addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '');
        });
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

const llamarProveedores = (accion, params = {}) => llamar(CONTROLADOR_PROVEEDORES, accion, params);

function badgeRegistro(deletedAt) {
    return !deletedAt
        ? '<span class="badge bg-success">Activo</span>'
        : '<span class="badge bg-secondary">Inactivo</span>';
}

// ── Listado ──────────────────────────────────────────────────────────────────
async function cargarProveedores() {
    const texto  = document.getElementById('fprov_texto').value.trim();
    const estado = document.getElementById('fprov_estado').value;

    const json = await llamarProveedores('LISTARPROVEEDORES', { texto, estado });
    const tbody = document.getElementById('tbodyProveedores');

    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;">${json.message}</td></tr>`;
        return;
    }

    const proveedores = json.proveedores || [];
    if (proveedores.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No hay proveedores registrados.</td></tr>';
        return;
    }

    tbody.innerHTML = proveedores.map(p => {
        let telefonos = [];
        try {
            telefonos = typeof p.telefonos_contacto === 'string'
                ? JSON.parse(p.telefonos_contacto)
                : (p.telefonos_contacto || []);
        } catch (e) { telefonos = []; }

        const resumenTelefonos = telefonos.length
            ? telefonos.map(t => t.telefono).join(', ')
            : '-';

        return `
        <tr id="fila-proveedor-${p.ruc}">
            <td data-label="RUC/DNI">${p.ruc}</td>
            <td data-label="Razón social">${p.razon_social}</td>
            <td data-label="Nombre comercial">${p.nombre_comercial ?? '-'}</td>
            <td data-label="Correo">${p.correo ?? '-'}</td>
            <td data-label="Teléfonos">${resumenTelefonos}</td>
            <td data-label="Estado">${badgeRegistro(p.deleted_at)}</td>
            <td data-label="Acciones" class="pc-td-acciones">
                <button class="pc-icon-btn" onclick="abrirModalEditarProveedor('${p.ruc}')" title="Editar">
                    <i class="fa-solid fa-pen"></i>
                </button>
                ${!p.deleted_at
                    ? `<button class="pc-icon-btn" onclick="eliminarProveedor('${p.ruc}')" title="Desactivar">
                           <i class="fa-solid fa-trash"></i></button>`
                    : `<button class="pc-icon-btn" onclick="reactivarProveedor('${p.ruc}')" title="Reactivar">
                           <i class="fa-solid fa-rotate-left"></i></button>`
                }
            </td>
        </tr>`;
    }).join('');
}

// ── Teléfonos dinámicos ──────────────────────────────────────────────────────
function agregarFilaTelefono(telefono = '', contacto = '') {
    const wrap = document.getElementById('prov_telefonos_wrap');
    const fila = document.createElement('div');
    fila.className = 'd-flex gap-2 mb-2 fila-telefono';
    fila.innerHTML = `
        <input type="text" class="form-control tel-numero" placeholder="Teléfono" value="${telefono}">
        <input type="text" class="form-control tel-contacto" placeholder="Nombre del contacto (opcional)" value="${contacto}">
        <button type="button" class="btn btn-outline-danger" onclick="this.closest('.fila-telefono').remove()">
            <i class="fa-solid fa-xmark"></i>
        </button>
    `;
    wrap.appendChild(fila);
}

function obtenerTelefonosJson() {
    const filas = document.querySelectorAll('#prov_telefonos_wrap .fila-telefono');
    const telefonos = [];
    filas.forEach(fila => {
        const telefono = fila.querySelector('.tel-numero').value.trim();
        const contacto = fila.querySelector('.tel-contacto').value.trim();
        if (telefono !== '') {
            telefonos.push({ telefono, contacto });
        }
    });
    return JSON.stringify(telefonos);
}

// ── Consulta API RUC/DNI ─────────────────────────────────────────────────────
async function consultarDocumentoProveedor() {
    const numero = document.getElementById('prov_ruc').value.trim();
    const info = document.getElementById('prov_consulta_info');

    if (!/^\d{8}$|^\d{11}$/.test(numero)) {
        Swal.fire('Atención', 'Ingresa un RUC (11 dígitos) o DNI (8 dígitos) válido antes de consultar.', 'warning');
        return;
    }

    info.textContent = 'Consultando...';
    const json = await llamarProveedores('CONSULTARDOCUMENTO', { numero });

    if (!json.success) {
        info.textContent = '';
        Swal.fire('Error', json.message, 'error');
        return;
    }

    const datos = json.data;
    document.getElementById('prov_razon_social').value = datos.name ?? '';

    if (datos.tipo === 'RUC') {
        document.getElementById('prov_ubigeo').value = datos.ubigeo ?? '';
        const partesDireccion = [datos.address, datos.district, datos.province, datos.region]
            .filter(Boolean);
        document.getElementById('prov_ubicacion').value = partesDireccion.join(', ');
        info.textContent = `Empresa · Estado: ${datos.state ?? '-'} · Condición: ${datos.condition ?? '-'}`;
    } else {
        info.textContent = 'Persona natural (DNI)';
    }

    // Guardamos el JSON crudo para persistirlo en js_consulta_api
    document.getElementById('prov_js_consulta_api').value = json.raw ?? '';
}

// ── Crear / Editar ───────────────────────────────────────────────────────────
function limpiarFormularioProveedor() {
    document.getElementById('formProveedor').reset();
    document.getElementById('prov_telefonos_wrap').innerHTML = '';
    document.getElementById('prov_consulta_info').textContent = '';
    document.getElementById('prov_js_consulta_api').value = '';
}

function abrirModalCrearProveedor() {
    limpiarFormularioProveedor();
    modoEdicion = false;
    document.getElementById('prov_ruc').disabled = false;
    document.getElementById('modalProveedorTitulo').textContent = 'Nuevo proveedor';
    agregarFilaTelefono(); // al menos una fila vacía para empezar
    modalProveedor.show();
}

async function abrirModalEditarProveedor(ruc) {
    const json = await llamarProveedores('OBTENERPROVEEDOR', { ruc });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    limpiarFormularioProveedor();
    modoEdicion = true;

    const p = json.proveedor;
    document.getElementById('modalProveedorTitulo').textContent = 'Editar proveedor';
    document.getElementById('prov_ruc').value = p.ruc;
    document.getElementById('prov_ruc').disabled = true; // el RUC/DNI es la llave primaria, no se edita
    document.getElementById('prov_razon_social').value = p.razon_social ?? '';
    document.getElementById('prov_nombre_comercial').value = p.nombre_comercial ?? '';
    document.getElementById('prov_correo').value = p.correo ?? '';
    document.getElementById('prov_ubigeo').value = p.ubigeo ?? '';
    document.getElementById('prov_ubicacion').value = p.ubicacion ?? '';

    let telefonos = [];
    try {
        telefonos = typeof p.telefonos_contacto === 'string'
            ? JSON.parse(p.telefonos_contacto)
            : (p.telefonos_contacto || []);
    } catch (e) { telefonos = []; }
    if (telefonos.length === 0) {
        agregarFilaTelefono();
    } else {
        telefonos.forEach(t => agregarFilaTelefono(t.telefono ?? '', t.contacto ?? ''));
    }

    modalProveedor.show();
}

document.getElementById('formProveedor').addEventListener('submit', async function (e) {
    e.preventDefault();

    const params = {
        accion: 'GUARDARPROVEEDOR',
        ruc: document.getElementById('prov_ruc').value.trim(),
        razon_social: document.getElementById('prov_razon_social').value.trim(),
        nombre_comercial: document.getElementById('prov_nombre_comercial').value.trim(),
        correo: document.getElementById('prov_correo').value.trim(),
        ubigeo: document.getElementById('prov_ubigeo').value.trim(),
        ubicacion: document.getElementById('prov_ubicacion').value.trim(),
        telefonos_contacto: obtenerTelefonosJson(),
        js_consulta_api: document.getElementById('prov_js_consulta_api').value,
    };

    const json = await llamarProveedores('GUARDARPROVEEDOR', params);

    if (json.success) {
        modalProveedor.hide();
        Swal.fire('Listo', json.message, 'success');
        cargarProveedores();
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});

// ── Eliminar / Reactivar ─────────────────────────────────────────────────────
function eliminarProveedor(ruc) {
    Swal.fire({
        title: '¿Desactivar proveedor?',
        text: 'Podrás reactivarlo luego desde el listado de inactivos.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarProveedores('ELIMINARPROVEEDOR', { ruc });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarProveedores();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

function reactivarProveedor(ruc) {
    llamarProveedores('REACTIVARPROVEEDOR', { ruc }).then(json => {
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarProveedores();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>