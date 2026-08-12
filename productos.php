<?php
$pageTitle    = 'Productos';
$pageSubtitle = 'Catálogo de mercadería para la venta';
$activePage   = 'productos';

include("header.php");
?>

<style>
    /* ── Mejoras visuales específicas de esta vista (no rompe tus estilos globales) ── */
    .pc-card {
        box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        border-radius: 12px;
        overflow: hidden;
    }
    .pc-card-header h2 {
        font-weight: 600;
        letter-spacing: -0.01em;
        margin: 0;
    }
    .pc-filtros {
        background: #f8f9fb;
        padding: 14px 16px;
        border-radius: 10px;
        border: 1px solid #eceef1;
    }
    .pc-filtros .form-control,
    .pc-filtros .form-select {
        border-radius: 8px;
    }
    .pc-table-wrapper {
        overflow-x: auto;
        border-radius: 10px;
        border: 1px solid #eceef1;
    }
    .pc-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin: 0;
    }
    .pc-table thead th {
        background: #f8f9fb;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6b7280;
        font-weight: 600;
        padding: 12px 14px;
        border-bottom: 2px solid #eceef1;
        position: sticky;
        top: 0;
        white-space: nowrap;
    }
    .pc-table tbody td {
        padding: 11px 14px;
        border-bottom: 1px solid #f1f2f4;
        vertical-align: middle;
        font-size: 0.92rem;
    }
    .pc-table tbody tr {
        transition: background-color 0.15s ease;
    }
    .pc-table tbody tr:hover {
        background-color: #f6f8fb;
    }
    .pc-table tbody tr:last-child td {
        border-bottom: none;
    }
    .pc-badge-estado {
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.02em;
    }
    .pc-icon-btn {
        border: none;
        background: #f1f2f4;
        color: #4b5563;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 4px;
        transition: all 0.15s ease;
    }
    .pc-icon-btn:hover {
        background: #e5e7eb;
        color: #111827;
    }
    .pc-icon-btn[title="Desactivar"]:hover {
        background: #fee2e2;
        color: #b91c1c;
    }
    .pc-icon-btn[title="Reactivar"]:hover {
        background: #dcfce7;
        color: #15803d;
    }
    .pc-icon-btn[title="Configurar"]:hover {
        background: #e0e7ff;
        color: #3730a3;
    }
    .pc-btn {
        border-radius: 8px;
        font-weight: 500;
        padding: 8px 16px;
    }
    .pc-btn-export {
        background: #1d6f42; /* verde estilo Excel */
        color: #fff;
        border: none;
    }
    .pc-btn-export:hover {
        background: #175c37;
        color: #fff;
    }
    .pc-empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #9ca3af;
    }

    /* ── Modal de configuración por molde ── */
    #configNavTabs .nav-link {
        font-weight: 500;
    }
    .config-tab-pane {
        padding-top: 14px;
    }
    .config-venta-block {
        background: #f8f9fb;
        border: 1px solid #eceef1;
        border-radius: 10px;
        padding: 12px 14px;
        margin-bottom: 16px;
    }
</style>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Mercadería para la venta</h2>
        <div class="d-flex gap-2 flex-wrap">
            <button class="pc-btn pc-btn-export" onclick="exportarExcel()">
                <i class="fa-solid fa-file-excel"></i> Exportar a Excel
            </button>
            <button class="pc-btn pc-btn-primary" onclick="abrirModalCrear()">
                <i class="fa-solid fa-plus"></i> Nuevo producto
            </button>
        </div>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <input type="text" id="f_texto" class="form-control" style="max-width:260px"
               placeholder="Buscar por código o descripción...">
        <select id="f_estado" class="form-select" style="max-width:160px">
            <option value="">Todos</option>
            <option value="activo" selected>Activos</option>
            <option value="inactivo">Inactivos</option>
        </select>
       
    </div>

    <div class="pc-table-wrapper">
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

<!-- Modal Configuración de Producto (por molde) -->
<div class="modal fade" id="modalConfigProducto" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formConfigProducto">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="fa-solid fa-gear"></i>
            <span id="configProductoTitulo">Configuración de Producto</span>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="config_producto_id">

          <!-- "Se vende por" es una sola configuración a nivel de producto,
               no depende del molde, por eso va aquí arriba y no dentro de los tabs. -->
          <div class="config-venta-block">
              <label class="form-label mb-1">Se vende por</label>
              <select class="form-select" id="config_se_vende_por" required>
                  <option value="">Seleccione...</option>
              </select>
          </div>

          <ul class="nav nav-tabs" id="configNavTabs" role="tablist"></ul>
          <div class="tab-content" id="configTabContent"></div>
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
<!-- SheetJS: librería para generar archivos Excel (.xlsx) 100% en el navegador -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
const CONTROLADOR        = 'controllers/clssProductos.php'; // clssProductos.php vive en su propia carpeta
const CONTROLADOR_MOLDES = 'controllers/clssMoldes.php';     // se reutiliza para saber qué moldes tiene el producto

const modalProducto       = new bootstrap.Modal(document.getElementById('modalProducto'));
const modalConfigProducto = new bootstrap.Modal(document.getElementById('modalConfigProducto'));

let unidadesCache  = [];
let productosCache = []; // guarda el último listado cargado, para exportar exactamente lo que se ve en pantalla

document.addEventListener('DOMContentLoaded', () => {
    cargarUnidades()
        .then(cargarProductos)
        .catch(err => {
            console.error('Error cargando datos iniciales:', err);
            document.getElementById('tbodyProductos').innerHTML =
                `<tr><td colspan="7" style="text-align:center;color:red;">Error de conexión con el servidor. Revisa la consola (F12).</td></tr>`;
        });

    // ── Filtrado automático ──────────────────────────────────────────────
    // Texto: espera 350ms desde la última tecla presionada (debounce),
    // así no se dispara una petición al servidor por cada letra escrita.
    let debounceTimer;
    document.getElementById('f_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarProductos, 350);
    });

    // Estado: se filtra apenas el usuario cambia la opción del select.
    document.getElementById('f_estado').addEventListener('change', cargarProductos);
});


// ── Llamada genérica a un controlador (por defecto, el de Productos) ────────
async function llamar(accion, params = {}, controlador = CONTROLADOR) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(controlador, {
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
        productosCache = [];
        return;
    }

    const productos = json.productos || [];
    productosCache = productos; // se guarda para el export

    if (productos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="pc-empty-state"><i class="fa-solid fa-box-open fa-2x mb-2"></i><br>No hay productos registrados.</div></td></tr>';
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
                ? '<span class="pc-badge-estado bg-success text-white">Activo</span>'
                : '<span class="pc-badge-estado bg-secondary text-white">Inactivo</span>'}
            </td>
            <td>
                <button class="pc-icon-btn" onclick="abrirModalEditar(${p.id})" title="Editar">
                    <i class="fa-solid fa-pen"></i>
                </button>
                <button class="pc-icon-btn" onclick="abrirModalConfiguracion(${p.id}, '${escapeAttr(p.descripcion)}')" title="Configurar">
                    <i class="fa-solid fa-gear"></i>
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

function escapeAttr(texto) {
    return String(texto ?? '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

// ── Exportar a Excel ─────────────────────────────────────────────────────────
function exportarExcel() {
    if (!productosCache || productosCache.length === 0) {
        Swal.fire('Sin datos', 'No hay productos para exportar. Carga o filtra el listado primero.', 'info');
        return;
    }

    // Se arma la data en el mismo orden/columnas que se ve en la tabla
    const datos = productosCache.map(p => ({
        'Código'            : p.codigo,
        'Descripción'       : p.descripcion,
        'U. Medida'         : p.unidad_venta_codigo ?? '',
        'Cant. Equivale'    : p.cant_equivale ?? '',
        'U. Equivale'       : p.unidad_equivale_codigo ?? '',
        'Peso unitario (g)' : p.peso_unitario_g ?? '',
        'Estado'            : p.activo ? 'Activo' : 'Inactivo',
    }));

    const hoja = XLSX.utils.json_to_sheet(datos);

    // Ancho de columnas aproximado según el contenido
    hoja['!cols'] = [
        { wch: 14 }, // Código
        { wch: 40 }, // Descripción
        { wch: 12 }, // U. Medida
        { wch: 14 }, // Cant. Equivale
        { wch: 12 }, // U. Equivale
        { wch: 16 }, // Peso unitario
        { wch: 10 }, // Estado
    ];

    const libro = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(libro, hoja, 'Productos');

    const fecha = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(libro, `productos_${fecha}.xlsx`);
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

// =============================================================================
// CONFIGURACIÓN DE PRODUCTO
// "Se vende por" vive a nivel producto (arriba del todo, fuera de los tabs).
// Cada tab de molde solo trae: necesita ensamblaje, salida en producción,
// salida de merma.
// =============================================================================

/**
 * Abre el modal de configuración: obtiene los moldes asociados a este
 * producto (reutilizando LISTARMOLDESPRODUCTO de clssMoldes.php, filtrado
 * por producto_id), la configuración por molde ya guardada
 * (producto.js_configuracion) y la configuración de venta ya guardada
 * (producto.js_configuracion_venta), y arma un tab por cada molde.
 */
async function abrirModalConfiguracion(productoId, productoDescripcion) {
    const [jsonMoldes, jsonProd] = await Promise.all([
        llamar('LISTARMOLDESPRODUCTO', {}, CONTROLADOR_MOLDES),
        llamar('OBTENERPRODUCTO', { id: productoId })
    ]);

    if (!jsonMoldes.success) { Swal.fire('Error', jsonMoldes.message, 'error'); return; }
    if (!jsonProd.success)   { Swal.fire('Error', jsonProd.message, 'error'); return; }

    const moldesDelProducto = (jsonMoldes.moldes_producto || [])
        .filter(m => m.producto_id === productoId);

    if (moldesDelProducto.length === 0) {
        Swal.fire('Sin moldes asociados', 'Este producto no tiene ningún molde asociado todavía. Ve al módulo de Moldes y asócialo primero.', 'info');
        return;
    }

    const configActual      = jsonProd.producto.js_configuracion || [];
    const configVentaActual = jsonProd.producto.js_configuracion_venta || {};

    document.getElementById('config_producto_id').value = productoId;
    document.getElementById('configProductoTitulo').textContent = `Configuración — ${productoDescripcion}`;

    llenarSelectUnidades('config_se_vende_por', configVentaActual.se_vende_por_unidad_medida_id);
    construirTabsConfiguracion(moldesDelProducto, configActual);
    modalConfigProducto.show();
}

function construirTabsConfiguracion(moldes, configActual) {
    const navTabs    = document.getElementById('configNavTabs');
    const tabContent = document.getElementById('configTabContent');
    navTabs.innerHTML    = '';
    tabContent.innerHTML = '';

    moldes.forEach((m, idx) => {
        const activo = idx === 0;
        const cfg    = configActual.find(c => c.molde_id === m.molde_id) || {};
        const esSi   = cfg.necesita_ensamblaje === 'sí';

        navTabs.insertAdjacentHTML('beforeend', `
            <li class="nav-item" role="presentation">
                <button class="nav-link ${activo ? 'active' : ''}" data-bs-toggle="tab"
                        data-bs-target="#tabMolde${m.molde_id}" type="button">
                    ${m.nombre}
                </button>
            </li>
        `);

        tabContent.insertAdjacentHTML('beforeend', `
            <div class="tab-pane fade config-tab-pane ${activo ? 'show active' : ''}"
                 id="tabMolde${m.molde_id}"
                 data-molde-id="${m.molde_id}"
                 data-molde-nombre="${escapeAttr(m.nombre)}">

                <div class="mb-3">
                    <label class="form-label d-block">Necesita Ensamblaje</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="ensamblaje_${m.molde_id}"
                               id="ens_si_${m.molde_id}" value="si" ${esSi ? 'checked' : ''}>
                        <label class="form-check-label" for="ens_si_${m.molde_id}">Sí</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="ensamblaje_${m.molde_id}"
                               id="ens_no_${m.molde_id}" value="no" ${!esSi ? 'checked' : ''}>
                        <label class="form-check-label" for="ens_no_${m.molde_id}">No</label>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Salida en Producción</label>
                    <select class="form-select" id="sel_salidaprod_${m.molde_id}"></select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Salida de Merma</label>
                    <select class="form-select" id="sel_salidamerma_${m.molde_id}"></select>
                </div>
            </div>
        `);

        llenarSelectUnidades(`sel_salidaprod_${m.molde_id}`, cfg.salida_produccion_unidad_medida_id);
        llenarSelectUnidades(`sel_salidamerma_${m.molde_id}`, cfg.salida_merma_unidad_medida_id);
    });
}

function llenarSelectUnidades(selectId, unidadIdSeleccionada) {
    const sel = document.getElementById(selectId);
    sel.innerHTML = '<option value="">Seleccione...</option>' +
        unidadesCache.map(u => `<option value="${u.id}" ${String(u.id) === String(unidadIdSeleccionada) ? 'selected' : ''}>${u.codigo} - ${u.nombre}</option>`).join('');
}

document.getElementById('formConfigProducto').addEventListener('submit', async function (e) {
    e.preventDefault();

    const productoId = parseInt(document.getElementById('config_producto_id').value, 10);

    // ── "Se vende por" (a nivel producto) ───────────────────────────────
    const ventaSel = document.getElementById('config_se_vende_por');
    if (!ventaSel.value) {
        Swal.fire('Falta información', 'Selecciona "Se vende por" para el producto.', 'warning');
        return;
    }
    const ventaOpt = ventaSel.selectedOptions[0];
    const configuracionVenta = {
        se_vende_por_unidad_medida_id: parseInt(ventaSel.value, 10),
        se_vende_por: ventaOpt.textContent.split(' - ')[0].trim().toLowerCase(),
    };

    // ── Configuración por molde ──────────────────────────────────────────
    const panes = document.querySelectorAll('#configTabContent .tab-pane');
    const configuraciones = [];
    let valido = true;

    panes.forEach(pane => {
        if (!valido) return;

        const moldeId     = parseInt(pane.dataset.moldeId, 10);
        const moldeNombre = pane.dataset.moldeNombre;

        const ensamblaje     = pane.querySelector(`input[name="ensamblaje_${moldeId}"]:checked`)?.value ?? 'no';
        const salidaProdSel  = document.getElementById(`sel_salidaprod_${moldeId}`);
        const salidaMermaSel = document.getElementById(`sel_salidamerma_${moldeId}`);

        if (!salidaProdSel.value || !salidaMermaSel.value) {
            Swal.fire('Falta información', `Completa todos los campos de configuración para "${moldeNombre}".`, 'warning');
            valido = false;
            return;
        }

        const salidaProdOpt  = salidaProdSel.selectedOptions[0];
        const salidaMermaOpt = salidaMermaSel.selectedOptions[0];

        configuraciones.push({
            molde_id: moldeId,
            molde: moldeNombre,
            necesita_ensamblaje: ensamblaje === 'si' ? 'sí' : 'no',
            salida_produccion_unidad_medida_id: parseInt(salidaProdSel.value, 10),
            salida_produccion: salidaProdOpt.textContent.split(' - ')[0].trim().toLowerCase(),
            salida_merma_unidad_medida_id: parseInt(salidaMermaSel.value, 10),
            salida_merma: salidaMermaOpt.textContent.split(' - ')[0].trim().toLowerCase(),
        });
    });

    if (!valido) return;

    const json = await llamar('GUARDARCONFIGPRODUCTO', {
        producto_id: productoId,
        configuraciones: JSON.stringify(configuraciones),
        configuracion_venta: JSON.stringify(configuracionVenta),
    });

    if (json.success) {
        modalConfigProducto.hide();
        Swal.fire('Listo', json.message, 'success');
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});
</script>

<?php require __DIR__ . '/footer.php'; ?>