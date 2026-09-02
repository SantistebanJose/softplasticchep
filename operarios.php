<?php
$pageTitle    = 'Trabajadores / Operarios';
$pageSubtitle = 'Personal de planta';
$activePage = 'operarios';


include("header.php");
?>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Trabajadores / Operarios</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrearOperario()">
            <i class="fa-solid fa-plus"></i> Nuevo operario
        </button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <input type="text" id="fop_texto" class="form-control" style="max-width:280px"
               placeholder="Buscar por nombre o cargo...">
        <select id="fop_estado" class="form-select" style="max-width:160px">
            <option value="activas" selected>Activos</option>
            <option value="eliminadas">Inactivos</option>
            <option value="todas">Todos</option>
        </select>
        <select id="fop_sucursal" class="form-select" style="max-width:220px">
            <option value="0">Todas las sucursales</option>
        </select>
        <select id="fop_etapa" class="form-select" style="max-width:200px">
            <option value="0">Todas las etapas</option>
        </select>
    </div>

    <div class="pc-table-wrap pc-table-responsive-cards">
    <table class="pc-table" id="tablaOperarios">
        <thead>
            <tr>
                <th>#</th>
                <th>Nombre completo</th>
                <th>DNI</th>
                <th>Cargo</th>
                <th>Área</th>
                <th>Sucursales</th>
                <th>Etapas</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tbodyOperarios">
            <tr><td colspan="8" style="text-align:center;">Cargando...</td></tr>
        </tbody>
    </table>
    </div>
</div>

<!-- Modal Crear/Editar -->
<div class="modal fade" id="modalOperario" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formOperario">
        <div class="modal-header">
          <h5 class="modal-title" id="modalOperarioTitulo">Nuevo operario</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">DNI *</label>
            <div class="input-group">
              <input type="text" class="form-control" id="op_dni" maxlength="8"
                     placeholder="Ej: 73578005" inputmode="numeric" required>
              <button type="button" class="btn btn-outline-secondary" id="btnBuscarDNI">
                <i class="fa-solid fa-magnifying-glass"></i> Buscar
              </button>
            </div>
            <small class="text-muted">Obligatorio. Se usa como usuario y contraseña iniciales de acceso al sistema.</small>
          </div>
          <div id="op_usuario_info" class="alert alert-info py-2 px-3 d-none mb-2"></div>
          <div class="mb-2">
            <label class="form-label">Nombre completo *</label>
            <input type="text" class="form-control" id="op_nombre_completo" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Cargo</label>
            <select id="op_cargo" class="form-select">
                <option value="">Selecciona un cargo</option>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">Sucursales</label>
            <select id="op_sucursales" multiple placeholder="Selecciona una o más sucursales..."></select>
            <small class="text-muted">Opcional. Donde trabaja este operario.</small>
          </div>
          <div class="mb-2">
            <label class="form-label">Etapas</label>
            <select id="op_etapas" multiple placeholder="Selecciona una o más etapas..."></select>
            <small class="text-muted">Opcional. En qué etapas puede involucrarse (producción, ensamblaje, empaquetado).</small>
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
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
const CONTROLADOR_OPERARIO  = 'controllers/clssOperario.php';
const CONTROLADOR_SUCURSAL  = 'controllers/clssSucursal.php';
const modalOperario = new bootstrap.Modal(document.getElementById('modalOperario'));

let modoEdicionOperario = false;
let operarioIdActual = 0;
let tomSelectSucursales = null;
let tomSelectEtapas = null;
let sucursalesActivas = [];
let etapasActivas = [];
let cargosActivos = [];

document.addEventListener('DOMContentLoaded', async () => {
    inicializarTomSelectSucursales();
    inicializarTomSelectEtapas();

    try {
        await cargarCargosActivos();
        await cargarSucursalesActivas();
        await cargarEtapasActivas();
        await cargarOperarios();
    } catch (err) {
        console.error('Error cargando operarios:', err);
        document.getElementById('tbodyOperarios').innerHTML =
            `<tr><td colspan="8" style="text-align:center;color:red;">Error de conexión con el servidor. Revisa la consola (F12).</td></tr>`;
    }

    let debounceTimer = null;
    document.getElementById('fop_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarOperarios, 350);
    });
    document.getElementById('fop_estado').addEventListener('change', cargarOperarios);
    document.getElementById('fop_sucursal').addEventListener('change', cargarOperarios);
    document.getElementById('fop_etapa').addEventListener('change', cargarOperarios);

    document.getElementById('btnBuscarDNI').addEventListener('click', buscarDNIOperario);
    document.getElementById('op_dni').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); buscarDNIOperario(); }
    });
});

function inicializarTomSelectSucursales() {
    tomSelectSucursales = new TomSelect('#op_sucursales', {
        valueField: 'id',
        labelField: 'nombre',
        searchField: 'nombre',
        options: [],
        plugins: ['remove_button'],
    });
}

function inicializarTomSelectEtapas() {
    tomSelectEtapas = new TomSelect('#op_etapas', {
        valueField: 'id',
        labelField: 'nombre',
        searchField: 'nombre',
        options: [],
        plugins: ['remove_button'],
    });
}

async function llamarOperario(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_OPERARIO, {
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

async function llamarSucursal(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_SUCURSAL, {
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

async function cargarCargosActivos() {
    const json = await llamarOperario('LISTARCARGOS');
    if (!json.success) return;
    cargosActivos = json.cargos || [];
    document.getElementById('op_cargo').innerHTML =
        '<option value="">Selecciona un cargo</option>' +
        cargosActivos.map(c => `<option value="${c.id}">${c.nombre}</option>`).join('');
}

async function cargarSucursalesActivas() {
    const json = await llamarSucursal('LISTARSUCURSALES', { visibilidad: 'activas' });
    if (!json.success) return;

    sucursalesActivas = json.sucursales || [];

    // Poblar Tom Select del modal
    tomSelectSucursales.clearOptions();
    sucursalesActivas.forEach(s => tomSelectSucursales.addOption({ id: s.id, nombre: s.nombre }));

    // Poblar filtro de la tabla
    const selectFiltro = document.getElementById('fop_sucursal');
    selectFiltro.innerHTML = '<option value="0">Todas las sucursales</option>' +
        sucursalesActivas.map(s => `<option value="${s.id}">${s.nombre}</option>`).join('');
}

async function cargarEtapasActivas() {
    const json = await llamarOperario('LISTARETAPASACTIVAS');
    if (!json.success) return;

    etapasActivas = json.etapas || [];

    // Poblar Tom Select del modal
    tomSelectEtapas.clearOptions();
    etapasActivas.forEach(e => tomSelectEtapas.addOption({ id: e.id, nombre: e.nombre }));

    // Poblar filtro de la tabla
    const selectFiltro = document.getElementById('fop_etapa');
    selectFiltro.innerHTML = '<option value="0">Todas las etapas</option>' +
        etapasActivas.map(e => `<option value="${e.id}">${e.nombre}</option>`).join('');
}

function badgeEstadoOperario(deletedAt) {
    return !deletedAt
        ? '<span class="badge bg-success">Activo</span>'
        : '<span class="badge bg-secondary">Inactivo</span>';
}

function badgesSucursales(jsSucursales) {
    let sucursales = jsSucursales || [];
    if (typeof sucursales === 'string') {
        try { sucursales = JSON.parse(sucursales); } catch (e) { sucursales = []; }
    }
    if (!Array.isArray(sucursales) || sucursales.length === 0) return '<span class="text-muted">-</span>';
    return sucursales.map(s => `<span class="badge bg-light text-dark border">${s.nombre}</span>`).join(' ');
}

function badgesEtapas(jsEtapas) {
    let etapas = jsEtapas || [];
    if (typeof etapas === 'string') {
        try { etapas = JSON.parse(etapas); } catch (e) { etapas = []; }
    }
    if (!Array.isArray(etapas) || etapas.length === 0) return '<span class="text-muted">-</span>';
    return etapas.map(e => `<span class="badge bg-info-subtle text-dark border">${e.nombre}</span>`).join(' ');
}

async function cargarOperarios() {
    const params = {
        texto: document.getElementById('fop_texto').value.trim(),
        visibilidad: document.getElementById('fop_estado').value,
        sucursal_id: document.getElementById('fop_sucursal').value,
        etapa_id: document.getElementById('fop_etapa').value,
    };

    const json = await llamarOperario('LISTAROPERARIOS', params);
    const tbody = document.getElementById('tbodyOperarios');

    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;">${json.message}</td></tr>`;
        return;
    }

    const operarios = json.operarios || [];
    if (operarios.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;">No hay operarios registrados.</td></tr>';
        return;
    }

    tbody.innerHTML = operarios.map(o => `
        <tr id="fila-operario-${o.id}">
            <td data-label="#">${o.id}</td>
            <td data-label="Nombre completo">${o.nombre_completo}</td>
            <td data-label="DNI">${o.dni ?? '-'}</td>
            <td data-label="Cargo">${o.cargo_nombre ?? '-'}</td>
            <td data-label="Área">${o.area_nombre ?? '<span class="text-muted">-</span>'}</td>
            <td data-label="Sucursales">${badgesSucursales(o.js_sucursales)}</td>
            <td data-label="Etapas">${badgesEtapas(o.js_etapas_relacionadas)}</td>
            <td data-label="Estado">${badgeEstadoOperario(o.deleted_at)}</td>
            <td data-label="Acciones" class="pc-td-acciones">
                <button class="pc-icon-btn" onclick="abrirModalEditarOperario(${o.id})" title="Editar">
                    <i class="fa-solid fa-pen"></i>
                </button>
                ${!o.deleted_at
                    ? `<button class="pc-icon-btn" onclick="eliminarOperario(${o.id})" title="Desactivar">
                        <i class="fa-solid fa-trash"></i></button>`
                    : `<button class="pc-icon-btn" onclick="reactivarOperario(${o.id})" title="Reactivar">
                        <i class="fa-solid fa-rotate-left"></i></button>`
                }
            </td>
        </tr>
    `).join('');
}

function limpiarFormularioOperario() {
    document.getElementById('formOperario').reset();
    tomSelectSucursales.clear();
    tomSelectEtapas.clear();
    operarioIdActual = 0;
    const infoUsuario = document.getElementById('op_usuario_info');
    infoUsuario.classList.add('d-none');
    infoUsuario.innerHTML = '';
}

function abrirModalCrearOperario() {
    limpiarFormularioOperario();
    modoEdicionOperario = false;
    document.getElementById('modalOperarioTitulo').textContent = 'Nuevo operario';
    modalOperario.show();
}

async function abrirModalEditarOperario(id) {
    const json = await llamarOperario('OBTENEROPERARIO', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    limpiarFormularioOperario();
    modoEdicionOperario = true;
    operarioIdActual = id;

    const o = json.operario;
    document.getElementById('modalOperarioTitulo').textContent = 'Editar operario #' + id;
    document.getElementById('op_nombre_completo').value = o.nombre_completo;
    document.getElementById('op_cargo').value = o.cargo_id ?? '';
    document.getElementById('op_dni').value = o.dni ?? '';

    let sucursalesAsignadas = o.js_sucursales || [];
    if (typeof sucursalesAsignadas === 'string') {
        try { sucursalesAsignadas = JSON.parse(sucursalesAsignadas); } catch (e) { sucursalesAsignadas = []; }
    }
    sucursalesAsignadas.forEach(s => tomSelectSucursales.addItem(s.sucursal_id, true));

    let etapasAsignadas = o.js_etapas_relacionadas || [];
    if (typeof etapasAsignadas === 'string') {
        try { etapasAsignadas = JSON.parse(etapasAsignadas); } catch (e) { etapasAsignadas = []; }
    }
    etapasAsignadas.forEach(e => tomSelectEtapas.addItem(e.etapa_id, true));

    mostrarInfoUsuarioOperario(o);

    modalOperario.show();
}

function mostrarInfoUsuarioOperario(o) {
    const infoUsuario = document.getElementById('op_usuario_info');
    if (o.usuario_id) {
        infoUsuario.className = 'alert alert-info py-2 px-3 mb-2';
        infoUsuario.innerHTML = `Cuenta de acceso vinculada: <strong>${o.usuario_login}</strong>. ` +
            `Editar el DNI aquí no cambia el usuario ni la contraseña ya existentes.`;
    } else {
        infoUsuario.className = 'alert alert-warning py-2 px-3 mb-2';
        infoUsuario.innerHTML = `Este operario no tiene cuenta de acceso vinculada. ` +
            `<button type="button" class="btn btn-sm btn-outline-primary ms-1" id="btnCrearUsuarioOperario">Crear cuenta ahora</button>`;
        document.getElementById('btnCrearUsuarioOperario').addEventListener('click', crearUsuarioParaOperarioActual);
    }
}

async function crearUsuarioParaOperarioActual() {
    if (!operarioIdActual) return;
    const json = await llamarOperario('CREARUSUARIODESDEOPERARIO', { id: operarioIdActual });
    if (json.success) {
        Swal.fire('Listo', json.message, 'success');
        const detalle = await llamarOperario('OBTENEROPERARIO', { id: operarioIdActual });
        if (detalle.success) mostrarInfoUsuarioOperario(detalle.operario);
    } else {
        Swal.fire('No se pudo crear', json.message, 'warning');
    }
}

async function buscarDNIOperario() {
    const dni = document.getElementById('op_dni').value.trim();
    if (!/^\d{8}$/.test(dni)) {
        Swal.fire('DNI inválido', 'Ingresa un DNI de 8 dígitos.', 'warning');
        return;
    }

    const btn = document.getElementById('btnBuscarDNI');
    const textoOriginal = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    try {
        const json = await llamarOperario('BUSCARDNI', { dni });
        if (json.success) {
            document.getElementById('op_nombre_completo').value = json.nombre_completo;
        } else {
            Swal.fire('No encontrado', json.message, 'error');
        }
    } finally {
        btn.disabled = false;
        btn.innerHTML = textoOriginal;
    }
}

document.getElementById('formOperario').addEventListener('submit', async function (e) {
    e.preventDefault();

    const params = {
        id: operarioIdActual,
        nombre_completo: document.getElementById('op_nombre_completo').value.trim(),
        cargo_id: document.getElementById('op_cargo').value,
        dni: document.getElementById('op_dni').value.trim(),
        sucursales: JSON.stringify(tomSelectSucursales.getValue()),
        etapas: JSON.stringify(tomSelectEtapas.getValue()),
    };

    const json = await llamarOperario('GUARDAROPERARIO', params);

    if (json.success) {
        modalOperario.hide();
        const esColisionUsuario = json.modo === 'crear' && json.usuario_creado === false;
        Swal.fire(esColisionUsuario ? 'Operario creado' : 'Listo', json.message, esColisionUsuario ? 'warning' : 'success');
        cargarOperarios();
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});

function eliminarOperario(id) {
    Swal.fire({
        title: '¿Desactivar este operario?',
        text: 'Podrás reactivarlo luego.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarOperario('ELIMINAROPERARIO', { id });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarOperarios();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

function reactivarOperario(id) {
    llamarOperario('REACTIVAROPERARIO', { id }).then(json => {
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarOperarios();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>