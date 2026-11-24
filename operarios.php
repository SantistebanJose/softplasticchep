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
                <th>Nombres</th>
                <th>Apellido paterno</th>
                <th>Apellido materno</th>
                <th>Apodo</th>
                <th>DNI</th>
                <th>Cargo</th>
                <th>Marcación</th>
                <th>Área</th>
                <th>Sucursales</th>
                <th>Etapas</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tbodyOperarios">
            <tr><td colspan="13" style="text-align:center;">Cargando...</td></tr>
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
          <div class="row g-2">
            <div class="col-12 mb-2"><label class="form-label">Nombres *</label><input type="text" class="form-control" id="op_nombre_completo" required></div>
            <div class="col-md-6 mb-2"><label class="form-label">Apellido paterno</label><input type="text" class="form-control" id="op_ape_paterno" maxlength="100"></div>
            <div class="col-md-6 mb-2"><label class="form-label">Apellido materno</label><input type="text" class="form-control" id="op_ape_materno" maxlength="100"></div>
            <div class="col-12 mb-2"><label class="form-label">Apodo</label><input type="text" class="form-control" id="op_apodo" maxlength="100"></div>
          </div>
          <small class="text-muted d-block mb-3">El nombre completo se mantiene sincronizado para conservar compatibilidad con reportes e historial. En registros antiguos, revisa y separa los apellidos al editar.</small>
          <details class="border rounded p-3 mb-3"><summary class="fw-semibold">Datos personales y laborales</summary><div class="row g-2 mt-2">
            <div class="col-md-6"><label class="form-label">Fecha de nacimiento</label><input type="date" class="form-control" id="op_fecha_nacimiento"></div>
            <div class="col-md-6"><label class="form-label">Fecha de ingreso</label><input type="date" class="form-control" id="op_fecha_ingreso"></div>
            <div class="col-md-6"><label class="form-label">Modalidad</label><select class="form-select" id="op_modalidad"><option value="">Selecciona una modalidad</option></select></div>
            <div class="col-md-6"><label class="form-label">Tipo de gestión</label><select class="form-select" id="op_tipo_gestion"><option value="">Selecciona un tipo</option></select></div>
            <div class="col-md-6"><label class="form-label">Sueldo bruto (S/)</label><input type="number" class="form-control" id="op_sueldo_bruto" min="0" step="0.01" inputmode="decimal"></div>
          </div></details>
          <details class="border rounded p-3 mb-3"><summary class="fw-semibold">Cese y datos bancarios</summary><div class="row g-2 mt-2">
            <div class="col-md-6"><label class="form-label">Fecha de egreso</label><input type="date" class="form-control" id="op_fecha_egreso"></div>
            <div class="col-12"><label class="form-label">Motivo del egreso</label><textarea class="form-control" id="op_detalle_egreso" rows="2" maxlength="500" placeholder="Escribe el motivo del egreso"></textarea></div>
            <div class="col-md-6"><label class="form-label">Banco</label><select class="form-select" id="op_banco_catalogo"><option value="">Selecciona un banco</option></select></div>
            <div class="col-md-6"><label class="form-label" for="op_banco_cuenta">Número de cuenta</label><input type="text" class="form-control" id="op_banco_cuenta" minlength="6" maxlength="30" pattern="[0-9]{6,30}" inputmode="numeric" autocomplete="off" placeholder="Solo números"><small class="form-text">De 6 a 30 dígitos, sin espacios ni guiones.</small></div>
            <div class="col-12"><label class="form-label" for="op_banco_cci">CCI</label><input type="text" class="form-control" id="op_banco_cci" minlength="20" maxlength="20" pattern="[0-9]{20}" inputmode="numeric" autocomplete="off" placeholder="20 dígitos"><small class="form-text">Debe contener exactamente 20 dígitos.</small></div>
          </div></details>
          <div class="mb-2">
            <label class="form-label">Cargo</label>
            <select id="op_cargo" class="form-select">
                <option value="">Selecciona un cargo</option>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">Tipo de marcación *</label>
            <select id="op_marcacion" class="form-select" required>
              <option value="S">Siempre (personal regular)</option>
              <option value="T">Temporal (personal externo)</option>
            </select>
            <small class="text-muted">Usa Temporal para personal que viene domingos o feriados.</small>
          </div>
          <div class="row">
            <div class="col-md-6 mb-2">
              <label class="form-label">Día de inicio</label>
              <input type="date" class="form-control" id="op_dia_inicio">
            </div>
            <div class="col-md-6 mb-2" id="op_dia_fin_wrap">
              <label class="form-label">Día de fin *</label>
              <input type="date" class="form-control" id="op_dia_fin">
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label">Descripción</label>
            <textarea class="form-control" id="op_descripcion_marcacion" rows="2" maxlength="500" placeholder="Motivo o detalle del trabajo temporal"></textarea>
          </div>
          <div class="border rounded p-3 mb-2 d-none" id="op_calendario_temporal">
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="op_habilitar_domingo">
              <label class="form-check-label" for="op_habilitar_domingo">Permitir inicio de sesión todos los domingos</label>
            </div>
            <label class="form-label" for="op_fecha_excepcion">Fechas autorizadas (feriados o días puntuales)</label>
            <div class="input-group mb-2">
              <input type="date" class="form-control" id="op_fecha_excepcion">
              <button type="button" class="btn btn-outline-primary" id="op_agregar_fecha">Agregar fecha</button>
            </div>
            <div id="op_fechas_habilitadas" class="d-flex flex-wrap gap-2"></div>
            <small class="text-muted d-block mt-2">El ingreso a la tablet se permitirá solo los domingos marcados o en las fechas de esta lista, dentro del periodo temporal.</small>
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
<script src="assets/js/device-tracking.js"></script>
<script src="assets/js/app-common.js"></script>
<script>
const CONTROLADOR_OPERARIO  = 'controllers/clssOperario.php';
const CONTROLADOR_SUCURSAL  = 'controllers/clssSucursal.php';
const llamarOperario = (accion, params = {}) => llamar(CONTROLADOR_OPERARIO, accion, params);
const llamarSucursal = (accion, params = {}) => llamar(CONTROLADOR_SUCURSAL, accion, params);
const modalOperario = new bootstrap.Modal(document.getElementById('modalOperario'));
let modoEdicionOperario = false;
let operarioIdActual = 0;
let tomSelectSucursales = null;
let tomSelectEtapas = null;
let sucursalesActivas = [];
let etapasActivas = [];
let cargosActivos = [];
let fechasHabilitadas = [];

function fechaISODesdeMarcacion(fecha) {
    if (!fecha) return '';
    const partes = fecha.split('-');
    return partes.length === 3 && partes[0].length === 2
        ? `${partes[2]}-${partes[1]}-${partes[0]}`
        : fecha;
}

function fechaLocalISO() {
    const hoy = new Date();
    const mm = String(hoy.getMonth() + 1).padStart(2, '0');
    const dd = String(hoy.getDate()).padStart(2, '0');
    return `${hoy.getFullYear()}-${mm}-${dd}`;
}

function actualizarCamposMarcacion() {
    const temporal = document.getElementById('op_marcacion').value === 'T';
    document.getElementById('op_dia_fin_wrap').classList.toggle('d-none', !temporal);
    document.getElementById('op_calendario_temporal').classList.toggle('d-none', !temporal);
    document.getElementById('op_dia_fin').required = temporal;
    document.getElementById('op_dia_inicio').required = temporal || !modoEdicionOperario;
}

function mostrarFechasHabilitadas() {
    const contenedor = document.getElementById('op_fechas_habilitadas');
    contenedor.innerHTML = fechasHabilitadas.map((fecha, indice) => `
        <span class="badge bg-light text-dark border d-inline-flex align-items-center gap-2">
            ${fechaISODesdeMarcacion(fecha)}
            <button type="button" class="btn-close" aria-label="Quitar fecha" onclick="quitarFechaHabilitada(${indice})"></button>
        </span>
    `).join('');
}

function quitarFechaHabilitada(indice) {
    fechasHabilitadas.splice(indice, 1);
    mostrarFechasHabilitadas();
}

function agregarFechaHabilitada() {
    const fecha = document.getElementById('op_fecha_excepcion').value;
    if (!fecha) return;
    const fechaMarcacion = `${fecha.slice(8, 10)}-${fecha.slice(5, 7)}-${fecha.slice(0, 4)}`;
    if (!fechasHabilitadas.includes(fechaMarcacion)) fechasHabilitadas.push(fechaMarcacion);
    fechasHabilitadas.sort((a, b) => fechaISODesdeMarcacion(a).localeCompare(fechaISODesdeMarcacion(b)));
    document.getElementById('op_fecha_excepcion').value = '';
    mostrarFechasHabilitadas();
}

document.addEventListener('DOMContentLoaded', async () => {
    inicializarTomSelectSucursales();
    inicializarTomSelectEtapas();

    try {
        await cargarCargosActivos();
        await cargarCatalogosOperario();
        await cargarSucursalesActivas();
        await cargarEtapasActivas();
        await cargarOperarios();
    } catch (err) {
        console.error('Error cargando operarios:', err);
        document.getElementById('tbodyOperarios').innerHTML =
            `<tr><td colspan="13" style="text-align:center;color:red;">Error de conexión con el servidor. Revisa la consola (F12).</td></tr>`;
    }

    let debounceTimer = null;
    document.getElementById('fop_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarOperarios, 350);
    });
    document.getElementById('fop_estado').addEventListener('change', cargarOperarios);
    document.getElementById('fop_sucursal').addEventListener('change', cargarOperarios);
    document.getElementById('fop_etapa').addEventListener('change', cargarOperarios);
    document.getElementById('op_marcacion').addEventListener('change', actualizarCamposMarcacion);
    document.getElementById('op_agregar_fecha').addEventListener('click', agregarFechaHabilitada);

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




async function cargarCargosActivos() {
    const json = await llamarOperario('LISTARCARGOS');
    if (!json.success) return;
    cargosActivos = json.cargos || [];
    document.getElementById('op_cargo').innerHTML =
        '<option value="">Selecciona un cargo</option>' +
        cargosActivos.map(c => `<option value="${c.id}">${c.nombre}</option>`).join('');
}

async function cargarCatalogosOperario() {
    const json = await llamarOperario('LISTARCATALOGOSOPERARIO');
    if (!json.success) {
        console.warn('No se pudieron cargar los catálogos de operarios:', json.message);
        window.catalogosOperario = { modalidades: [], tipos_gestion: [], bancos: [] };
        return;
    }
    window.catalogosOperario = {
        modalidades: json.modalidades || [],
        tipos_gestion: json.tipos_gestion || [],
        bancos: json.bancos || [],
    };
    llenarSelectCatalogo('op_modalidad', window.catalogosOperario.modalidades, 'Selecciona una modalidad');
    llenarSelectCatalogo('op_tipo_gestion', window.catalogosOperario.tipos_gestion, 'Selecciona un tipo');
    llenarSelectCatalogo('op_banco_catalogo', window.catalogosOperario.bancos, 'Selecciona un banco');
}

function llenarSelectCatalogo(id, valores, placeholder, valorActual = '') {
    const select = document.getElementById(id);
    const lista = [...valores];
    if (valorActual && !lista.some(item => item.nombre === valorActual)) lista.push({ nombre: valorActual, historico: true });
    select.innerHTML = `<option value="">${placeholder}</option>` + lista.map(item =>
        `<option value="${item.nombre}">${item.nombre}${item.historico ? ' (registro anterior)' : ''}</option>`
    ).join('');
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

function obtenerNombresOperario(o) {
    const apellidos = [o.ape_parteno, o.ape_marteno].filter(Boolean).join(' ');
    let nombres = o.nombre_completo || '-';
    if (apellidos && nombres.toLocaleUpperCase('es').endsWith(apellidos.toLocaleUpperCase('es'))) {
        nombres = nombres.slice(0, nombres.length - apellidos.length).trim();
    }
    return nombres || '-';
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
        tbody.innerHTML = `<tr><td colspan="13" style="text-align:center;">${json.message}</td></tr>`;
        return;
    }

    const operarios = json.operarios || [];
    if (operarios.length === 0) {
        tbody.innerHTML = '<tr><td colspan="13" style="text-align:center;">No hay operarios registrados.</td></tr>';
        return;
    }

    tbody.innerHTML = operarios.map(o => `
        <tr id="fila-operario-${o.id}">
            <td data-label="#">${o.id}</td>
            <td data-label="Nombres">${obtenerNombresOperario(o)}</td>
            <td data-label="Apellido paterno">${o.ape_parteno ?? '-'}</td>
            <td data-label="Apellido materno">${o.ape_marteno ?? '-'}</td>
            <td data-label="Apodo">${o.apodo ?? '-'}</td>
            <td data-label="DNI">${o.dni ?? '-'}</td>
            <td data-label="Cargo">${o.cargo_nombre ?? '-'}</td>
            <td data-label="Marcación"><span class="badge ${o.marcacion === 'T' ? 'bg-warning text-dark' : 'bg-primary'}">${o.marcacion === 'T' ? 'Temporal' : 'Siempre'}</span></td>
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
    document.getElementById('op_marcacion').value = 'S';
    document.querySelectorAll('#modalOperario details').forEach(detalle => detalle.open = false);
    document.getElementById('op_dia_inicio').value = fechaLocalISO();
    document.getElementById('op_dia_fin').value = '';
    document.getElementById('op_descripcion_marcacion').value = '';
    document.getElementById('op_habilitar_domingo').checked = false;
    document.getElementById('op_fecha_excepcion').value = '';
    fechasHabilitadas = [];
    mostrarFechasHabilitadas();
    actualizarCamposMarcacion();
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
    document.getElementById('op_nombre_completo').value = obtenerNombresOperario(o) === '-' ? '' : obtenerNombresOperario(o);
    document.getElementById('op_ape_paterno').value = o.ape_parteno ?? '';
    document.getElementById('op_ape_materno').value = o.ape_marteno ?? '';
    document.getElementById('op_apodo').value = o.apodo ?? '';
    document.getElementById('op_fecha_nacimiento').value = o.fecha_nacimiento ?? '';
    document.getElementById('op_fecha_ingreso').value = o.fecha_ingreso ?? '';
    document.getElementById('op_fecha_egreso').value = o.fecha_egreso ?? '';
    llenarSelectCatalogo('op_modalidad', window.catalogosOperario?.modalidades || [], 'Selecciona una modalidad', o.modalidad ?? '');
    llenarSelectCatalogo('op_tipo_gestion', window.catalogosOperario?.tipos_gestion || [], 'Selecciona un tipo', o.tipo_gestion ?? '');
    document.getElementById('op_modalidad').value = o.modalidad ?? '';
    document.getElementById('op_tipo_gestion').value = o.tipo_gestion ?? '';
    document.getElementById('op_sueldo_bruto').value = o.sueldo_bruto ?? '';
    const detalleEgreso = o.js_detalle_egreso;
    document.getElementById('op_detalle_egreso').value = typeof detalleEgreso === 'string'
        ? detalleEgreso
        : (detalleEgreso?.motivo ?? detalleEgreso?.detalle ?? '');
    const datosBanco = (o.banco && typeof o.banco === 'object' && !Array.isArray(o.banco)) ? { ...o.banco } : {};
    const nombreBanco = datosBanco.nombre ?? datosBanco.banco ?? '';
    delete datosBanco.nombre;
    delete datosBanco.banco;
    llenarSelectCatalogo('op_banco_catalogo', window.catalogosOperario?.bancos || [], 'Selecciona un banco', nombreBanco);
    document.getElementById('op_banco_catalogo').value = nombreBanco;
    document.getElementById('op_banco_cuenta').value = datosBanco.cuenta ?? datosBanco.numero_cuenta ?? '';
    document.getElementById('op_banco_cci').value = datosBanco.cci ?? '';
    document.getElementById('op_cargo').value = o.cargo_id ?? '';
    document.getElementById('op_dni').value = o.dni ?? '';
    const detalleMarcacion = o.js_marcacion || {};
    document.getElementById('op_marcacion').value = o.marcacion || detalleMarcacion.marcacion || 'S';
    document.getElementById('op_dia_inicio').value = fechaISODesdeMarcacion(detalleMarcacion.dia_inicio);
    document.getElementById('op_dia_fin').value = fechaISODesdeMarcacion(detalleMarcacion.dia_fin);
    document.getElementById('op_descripcion_marcacion').value = detalleMarcacion['descripción'] || detalleMarcacion.descripcion || '';
    document.getElementById('op_habilitar_domingo').checked = (detalleMarcacion.dias_semana || []).includes('domingo');
    fechasHabilitadas = Array.isArray(detalleMarcacion.fechas_habilitadas) ? [...detalleMarcacion.fechas_habilitadas] : [];
    mostrarFechasHabilitadas();
    actualizarCamposMarcacion();

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

function formatearJsonTextarea(valor) {
    if (valor === null || valor === undefined || valor === '') return '';
    if (typeof valor === 'string') {
        try { return JSON.stringify(JSON.parse(valor), null, 2); } catch (_) { return valor; }
    }
    return JSON.stringify(valor, null, 2);
}

function mostrarInfoUsuarioOperario(o) {
    const infoUsuario = document.getElementById('op_usuario_info');
    if (o.usuario_id) {
        infoUsuario.className = 'alert alert-info py-2 px-3 mb-2';
        infoUsuario.innerHTML = `Cuenta de acceso vinculada: <strong>${o.usuario_login}</strong>. ` +
            `El nombre se sincroniza al guardar. Editar el DNI no cambia el usuario ni la contraseña existentes.`;
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

    const cuentaBancaria = document.getElementById('op_banco_cuenta').value.trim();
    const cciBancario = document.getElementById('op_banco_cci').value.trim();
    if (cuentaBancaria && !/^\d{6,30}$/.test(cuentaBancaria)) {
        Swal.fire('Número de cuenta inválido', 'Ingresa de 6 a 30 dígitos, sin letras, espacios ni guiones.', 'warning');
        return;
    }
    if (cciBancario && !/^\d{20}$/.test(cciBancario)) {
        Swal.fire('CCI inválido', 'El CCI debe tener exactamente 20 dígitos.', 'warning');
        return;
    }

    const params = {
        id: operarioIdActual,
        nombre_completo: document.getElementById('op_nombre_completo').value.trim(),
        ape_parteno: document.getElementById('op_ape_paterno').value.trim(),
        ape_marteno: document.getElementById('op_ape_materno').value.trim(),
        apodo: document.getElementById('op_apodo').value.trim(),
        fecha_nacimiento: document.getElementById('op_fecha_nacimiento').value,
        fecha_ingreso: document.getElementById('op_fecha_ingreso').value,
        fecha_egreso: document.getElementById('op_fecha_egreso').value,
        modalidad: document.getElementById('op_modalidad').value.trim(),
        tipo_gestion: document.getElementById('op_tipo_gestion').value.trim(),
        sueldo_bruto: document.getElementById('op_sueldo_bruto').value,
        banco_nombre: document.getElementById('op_banco_catalogo').value,
        banco_cuenta: cuentaBancaria,
        banco_cci: cciBancario,
        motivo_egreso: document.getElementById('op_detalle_egreso').value.trim(),
        cargo_id: document.getElementById('op_cargo').value,
        dni: document.getElementById('op_dni').value.trim(),
        marcacion: document.getElementById('op_marcacion').value,
        dia_inicio: document.getElementById('op_dia_inicio').value,
        dia_fin: document.getElementById('op_dia_fin').value,
        descripcion_marcacion: document.getElementById('op_descripcion_marcacion').value.trim(),
        domingo: document.getElementById('op_habilitar_domingo').checked ? '1' : '0',
        fechas_habilitadas: JSON.stringify(fechasHabilitadas),
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
