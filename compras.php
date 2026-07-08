<?php
$pageTitle    = 'Compras';
$pageSubtitle = 'Compras a proveedores';
$activePage = 'compras';

include("header.php");
?>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Compras</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrearCompra()">
            <i class="fa-solid fa-plus"></i> Nueva compra
        </button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <br>
        <input type="text" id="fcompra_texto" class="form-control" style="max-width:260px"
               placeholder="Buscar por proveedor o descripción...">
        <select id="fcompra_proveedor" class="form-select" style="max-width:220px">
            <option value="">Todos los proveedores</option>
        </select>
        <input type="date" id="fcompra_desde" class="form-control" style="max-width:160px" title="Desde">
        <input type="date" id="fcompra_hasta" class="form-control" style="max-width:160px" title="Hasta">
        <select id="fcompra_estado" class="form-select" style="max-width:160px">
            <option value="">Todos</option>
            <option value="activa" selected>Activas</option>
            <option value="inactiva">Inactivas</option>
        </select>
    </div>

    <div class="pc-table-wrap pc-table-responsive-cards">
    <table class="pc-table" id="tablaCompras">
        <thead>
            <tr>
                <th>#</th>
                <th>Proveedor</th>
                <th>Fecha</th>
                <th>Descripción</th>
                <th>Materiales</th>
                <th>Total</th>
                <th>Comprobante</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tbodyCompras">
            <tr><td colspan="9" style="text-align:center;">Cargando...</td></tr>
        </tbody>
    </table>
    </div>
</div>

<!-- Modal Crear/Editar -->
<div class="modal fade" id="modalCompra" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <form id="formCompra">
        <div class="modal-header">
          <h5 class="modal-title" id="modalCompraTitulo">Nueva compra</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">

          <div class="row">
            <div class="col-md-6 mb-2">
                <label class="form-label">Proveedor *</label>
                <select class="form-select" id="compra_proveedor_id" required>
                    <option value="">Selecciona un proveedor...</option>
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Fecha de compra *</label>
                <input type="date" class="form-control" id="compra_fecha" required>
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Comprobante</label>
                <input type="file" class="form-control" id="compra_comprobante" accept=".jpg,.jpeg,.png,.webp,.pdf">
                <div class="form-text" id="compra_comprobante_actual"></div>
            </div>
          </div>

          <div class="mb-2">
            <label class="form-label">Descripción</label>
            <textarea class="form-control" id="compra_descripcion" rows="2"
                      placeholder="Ej: 80 toneladas de fantasía..."></textarea>
          </div>

          <hr>

          <!-- Detalle de materiales (dinámico) -->
          <div class="mb-2">
            <label class="form-label d-flex justify-content-between align-items-center">
                Materiales comprados *
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="agregarFilaMaterial()">
                    <i class="fa-solid fa-plus"></i> Agregar material
                </button>
            </label>
            <div class="pc-table-wrap">
            <table class="pc-table" id="tablaDetalleCompra">
                <thead>
                    <tr>
                        <th style="min-width:200px">Material</th>
                        <th style="min-width:150px">Unidad</th>
                        <th style="width:130px">Cantidad</th>
                        <th style="width:130px">Sub total</th>
                        <th style="width:130px">Total</th>
                        <th>Comentario</th>
                        <th style="width:40px"></th>
                    </tr>
                </thead>
                <tbody id="compra_detalle_wrap"></tbody>
            </table>
            </div>
          </div>

          <div class="d-flex justify-content-end">
            <div class="text-end">
                <div class="form-label mb-0">Total de la compra</div>
                <h4 id="compra_total_visual">S/ 0.00</h4>
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
const CONTROLADOR_COMPRAS = 'controllers/clssCompra.php';
const modalCompra = new bootstrap.Modal(document.getElementById('modalCompra'));

let modoEdicionCompra = false;
let compraIdActual = 0;
let comprobanteActualRuta = null; // ruta que ya está guardada en BD (modo edición)
let eliminarComprobanteFlag = false;
let contadorFilaMaterial = 0;
let unidadesCache = null; // cache de unidad_medida (id, nombre, nombre_corto, equivalencia)

document.addEventListener('DOMContentLoaded', () => {
    cargarProveedoresFiltro();
    cargarCompras().catch(err => {
        console.error('Error cargando datos iniciales:', err);
        document.getElementById('tbodyCompras').innerHTML =
            `<tr><td colspan="9" style="text-align:center;color:red;">Error de conexión con el servidor. Revisa la consola (F12).</td></tr>`;
    });

    let debounceTimer = null;
    document.getElementById('fcompra_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarCompras, 350);
    });
    ['fcompra_proveedor', 'fcompra_estado', 'fcompra_desde', 'fcompra_hasta'].forEach(id => {
        document.getElementById(id).addEventListener('change', cargarCompras);
    });
});

// ── Llamada genérica (para acciones sin archivos) ────────────────────────────
async function llamarCompras(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_COMPRAS, {
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

function badgeRegistro(deletedAt) {
    return !deletedAt
        ? '<span class="badge bg-success">Activa</span>'
        : '<span class="badge bg-secondary">Inactiva</span>';
}

function formatearMoneda(n) {
    return 'S/ ' + Number(n ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatearCantidad(n) {
    return Number(n ?? 0).toLocaleString('es-PE', { maximumFractionDigits: 4 });
}

// ── Selects auxiliares ───────────────────────────────────────────────────────
async function cargarProveedoresFiltro() {
    const json = await llamarCompras('BUSCARPROVEEDORES', {});
    if (!json.success) return;
    const select = document.getElementById('fcompra_proveedor');
    json.proveedores.forEach(p => {
        select.insertAdjacentHTML('beforeend', `<option value="${p.ruc}">${p.razon_social}</option>`);
    });
}

async function cargarProveedoresModal(seleccionarRuc = '') {
    const select = document.getElementById('compra_proveedor_id');
    select.innerHTML = '<option value="">Selecciona un proveedor...</option>';
    const json = await llamarCompras('BUSCARPROVEEDORES', {});
    if (json.success) {
        json.proveedores.forEach(p => {
            select.insertAdjacentHTML('beforeend',
                `<option value="${p.ruc}">${p.razon_social}${p.nombre_comercial ? ' - ' + p.nombre_comercial : ''}</option>`);
        });
    }
    if (seleccionarRuc) select.value = seleccionarRuc;
}

async function obtenerOpcionesMateriales() {
    const json = await llamarCompras('BUSCARMATERIALES', {});
    if (!json.success) return [];
    return json.materiales;
}

// Trae y cachea las unidades de medida (se comparten en todas las filas de la tabla).
async function obtenerOpcionesUnidades() {
    if (unidadesCache) return unidadesCache;
    const json = await llamarCompras('BUSCARUNIDADES', {});
    unidadesCache = json.success ? json.unidades : [];
    return unidadesCache;
}

// ── Listado ──────────────────────────────────────────────────────────────────
async function cargarCompras() {
    const params = {
        texto: document.getElementById('fcompra_texto').value.trim(),
        proveedor_id: document.getElementById('fcompra_proveedor').value,
        estado: document.getElementById('fcompra_estado').value,
        fecha_desde: document.getElementById('fcompra_desde').value,
        fecha_hasta: document.getElementById('fcompra_hasta').value,
    };

    const json = await llamarCompras('LISTARCOMPRAS', params);
    const tbody = document.getElementById('tbodyCompras');

    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;">${json.message}</td></tr>`;
        return;
    }

    const compras = json.compras || [];
    if (compras.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;">No hay compras registradas.</td></tr>';
        return;
    }

    tbody.innerHTML = compras.map(c => `
        <tr id="fila-compra-${c.id}">
            <td data-label="#">${c.id}</td>
            <td data-label="Proveedor">${c.razon_social}</td>
            <td data-label="Fecha">${c.fecha_compra}</td>
            <td data-label="Descripción">${c.descripcion ?? '-'}</td>
            <td data-label="Materiales">${c.items_count}</td>
            <td data-label="Total">${formatearMoneda(c.total)}</td>
            <td data-label="Comprobante">${c.img_comprobante
                ? `<a href="${c.img_comprobante}" target="_blank" class="pc-icon-btn" title="Ver comprobante"><i class="fa-solid fa-file-lines"></i></a>`
                : '-'}</td>
            <td data-label="Estado">${badgeRegistro(c.deleted_at)}</td>
            <td data-label="Acciones" class="pc-td-acciones">
                <button class="pc-icon-btn" onclick="abrirModalEditarCompra(${c.id})" title="Editar">
                    <i class="fa-solid fa-pen"></i>
                </button>
                ${!c.deleted_at
                    ? `<button class="pc-icon-btn" onclick="eliminarCompra(${c.id})" title="Desactivar">
                           <i class="fa-solid fa-trash"></i></button>`
                    : `<button class="pc-icon-btn" onclick="reactivarCompra(${c.id})" title="Reactivar">
                           <i class="fa-solid fa-rotate-left"></i></button>`
                }
            </td>
        </tr>`).join('');
}

// ── Detalle de materiales (dinámico) ─────────────────────────────────────────
// datos (modo edición) puede traer: material_id, unidad_medida_id, cantidad,
// sub_total, total, comentario (tal cual vienen de OBTENERCOMPRA).
async function agregarFilaMaterial(datos = null) {
    const [materiales, unidades] = await Promise.all([
        obtenerOpcionesMateriales(),
        obtenerOpcionesUnidades()
    ]);
    const filaId = 'fila-mat-' + (++contadorFilaMaterial);
    const wrap = document.getElementById('compra_detalle_wrap');

    const opcionesMaterialHtml = materiales.map(m =>
        `<option value="${m.id}"
                 data-unidad-id="${m.unidad_medida_id ?? ''}"
                 data-unidad-corto="${m.unidad_corto ?? ''}"
                 ${datos && datos.material_id == m.id ? 'selected' : ''}>
            ${m.nombre} (stock: ${m.stock_actual ?? 0} ${m.unidad_corto ?? ''})
         </option>`
    ).join('');

    const opcionesUnidadHtml = unidades.map(u =>
        `<option value="${u.id}" data-equiv="${u.equivalencia}"
                 ${datos && datos.unidad_medida_id == u.id ? 'selected' : ''}>
            ${u.nombre} (${u.nombre_corto})
         </option>`
    ).join('');

    const tr = document.createElement('tr');
    tr.id = filaId;
    tr.className = 'fila-detalle-material';
    tr.innerHTML = `
        <td><select class="form-select mat-select" required>
                <option value="">Selecciona...</option>${opcionesMaterialHtml}
            </select></td>
        <td><select class="form-select mat-unidad" required>
                <option value="">Selecciona...</option>${opcionesUnidadHtml}
            </select></td>
        <td>
            <input type="number" class="form-control mat-cantidad" min="0.01" step="0.01"
                    value="${datos ? datos.cantidad : ''}" required>
            <div class="form-text mat-conversion"></div>
        </td>
        <td><input type="number" class="form-control mat-subtotal" min="0" step="0.01"
                    value="${datos ? datos.sub_total : ''}"></td>
        <td><input type="number" class="form-control mat-total" min="0" step="0.01"
                    value="${datos ? datos.total : ''}"></td>
        <td><input type="text" class="form-control mat-comentario" placeholder="Opcional"
                    value="${datos ? (datos.comentario ?? '') : ''}"></td>
        <td><button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('tr').remove(); recalcularTotalCompra();">
                <i class="fa-solid fa-xmark"></i></button></td>
    `;
    wrap.appendChild(tr);

    const matSelect      = tr.querySelector('.mat-select');
    const unidadSelect    = tr.querySelector('.mat-unidad');
    const cantidadInput   = tr.querySelector('.mat-cantidad');
    const conversionDiv   = tr.querySelector('.mat-conversion');

    function actualizarConversion() {
        const unidadOpt = unidadSelect.selectedOptions[0];
        const equiv = unidadOpt ? parseFloat(unidadOpt.dataset.equiv || '1') : 1;
        const matOpt = matSelect.selectedOptions[0];
        const unidadBaseCorto = matOpt ? (matOpt.dataset.unidadCorto || '') : '';
        const cantidad = parseFloat(cantidadInput.value) || 0;
        if (cantidad > 0 && equiv && equiv !== 1) {
            conversionDiv.textContent = `= ${formatearCantidad(cantidad * equiv)} ${unidadBaseCorto}`.trim();
        } else {
            conversionDiv.textContent = '';
        }
    }

    // Al elegir un material, si todavía no hay unidad elegida, se autoselecciona
    // la unidad base de ESE material (el usuario puede cambiarla después).
    matSelect.addEventListener('change', () => {
        if (!unidadSelect.value) {
            const matOpt = matSelect.selectedOptions[0];
            const unidadIdDefault = matOpt ? matOpt.dataset.unidadId : '';
            if (unidadIdDefault) unidadSelect.value = unidadIdDefault;
        }
        actualizarConversion();
    });
    unidadSelect.addEventListener('change', actualizarConversion);
    cantidadInput.addEventListener('input', actualizarConversion);

    // Si estamos precargando una fila (edición) sin unidad_medida_id explícita
    // (líneas antiguas), se usa la unidad base del material como fallback.
    if (datos && !datos.unidad_medida_id) {
        const matOpt = matSelect.selectedOptions[0];
        if (matOpt && matOpt.dataset.unidadId) unidadSelect.value = matOpt.dataset.unidadId;
    }
    actualizarConversion();

    // Sub total -> autocompleta total (si el usuario no lo tocó manualmente todavía)
    const subtotalInput = tr.querySelector('.mat-subtotal');
    const totalInput = tr.querySelector('.mat-total');
    subtotalInput.addEventListener('input', () => {
        if (!totalInput.dataset.tocadoManual) totalInput.value = subtotalInput.value;
        recalcularTotalCompra();
    });
    totalInput.addEventListener('input', () => {
        totalInput.dataset.tocadoManual = '1';
        recalcularTotalCompra();
    });

    recalcularTotalCompra();
}

function recalcularTotalCompra() {
    let total = 0;
    document.querySelectorAll('#compra_detalle_wrap .mat-total').forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    document.getElementById('compra_total_visual').textContent = formatearMoneda(total);
}

function obtenerDetalleJson() {
    const filas = document.querySelectorAll('#compra_detalle_wrap .fila-detalle-material');
    const detalle = [];
    filas.forEach(fila => {
        const material_id = fila.querySelector('.mat-select').value;
        const unidad_medida_id = fila.querySelector('.mat-unidad').value;
        const cantidad = fila.querySelector('.mat-cantidad').value;
        const sub_total = fila.querySelector('.mat-subtotal').value;
        const total = fila.querySelector('.mat-total').value;
        const comentario = fila.querySelector('.mat-comentario').value.trim();
        if (material_id && unidad_medida_id && cantidad) {
            detalle.push({
                material_id,
                unidad_medida_id,
                cantidad,
                sub_total: sub_total || 0,
                total: total || sub_total || 0,
                comentario
            });
        }
    });
    return JSON.stringify(detalle);
}

// ── Crear / Editar ───────────────────────────────────────────────────────────
function limpiarFormularioCompra() {
    document.getElementById('formCompra').reset();
    document.getElementById('compra_detalle_wrap').innerHTML = '';
    document.getElementById('compra_comprobante_actual').innerHTML = '';
    document.getElementById('compra_total_visual').textContent = formatearMoneda(0);
    compraIdActual = 0;
    comprobanteActualRuta = null;
    eliminarComprobanteFlag = false;
}

async function abrirModalCrearCompra() {
    limpiarFormularioCompra();
    modoEdicionCompra = false;
    document.getElementById('modalCompraTitulo').textContent = 'Nueva compra';
    await cargarProveedoresModal();
    await agregarFilaMaterial();
    modalCompra.show();
}

async function abrirModalEditarCompra(id) {
    const json = await llamarCompras('OBTENERCOMPRA', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    limpiarFormularioCompra();
    modoEdicionCompra = true;
    compraIdActual = id;

    const c = json.compra;
    document.getElementById('modalCompraTitulo').textContent = 'Editar compra #' + id;
    document.getElementById('compra_fecha').value = c.fecha_compra;
    document.getElementById('compra_descripcion').value = c.descripcion ?? '';

    await cargarProveedoresModal(c.proveedor_id);

    comprobanteActualRuta = c.img_comprobante || null;
    const infoComprobante = document.getElementById('compra_comprobante_actual');
    if (comprobanteActualRuta) {
        infoComprobante.innerHTML = `
            <a href="${comprobanteActualRuta}" target="_blank">Ver comprobante actual</a>
            &nbsp;·&nbsp;
            <a href="#" onclick="quitarComprobanteActual(event)">Quitar</a>`;
    }

    const detalle = json.detalle || [];
    if (detalle.length === 0) {
        await agregarFilaMaterial();
    } else {
        for (const d of detalle) {
            await agregarFilaMaterial(d);
        }
    }
    recalcularTotalCompra();

    modalCompra.show();
}

function quitarComprobanteActual(e) {
    e.preventDefault();
    eliminarComprobanteFlag = true;
    document.getElementById('compra_comprobante_actual').innerHTML =
        '<span class="text-danger">El comprobante actual se eliminará al guardar.</span>';
}

document.getElementById('formCompra').addEventListener('submit', async function (e) {
    e.preventDefault();

    const detalleJson = obtenerDetalleJson();
    if (JSON.parse(detalleJson).length === 0) {
        Swal.fire('Atención', 'Agrega al menos un material con cantidad y unidad de medida válidas.', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('accion', 'GUARDARCOMPRA');
    formData.append('id', compraIdActual);
    formData.append('proveedor_id', document.getElementById('compra_proveedor_id').value);
    formData.append('fecha_compra', document.getElementById('compra_fecha').value);
    formData.append('descripcion', document.getElementById('compra_descripcion').value.trim());
    formData.append('detalle', detalleJson);
    formData.append('eliminar_comprobante', eliminarComprobanteFlag ? '1' : '0');

    const archivo = document.getElementById('compra_comprobante').files[0];
    if (archivo) formData.append('img_comprobante', archivo);

    let json;
    try {
        const resp = await fetch(CONTROLADOR_COMPRAS, { method: 'POST', body: formData });
        json = await resp.json();
    } catch (err) {
        console.error(err);
        Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
        return;
    }

    if (json.success) {
        modalCompra.hide();
        Swal.fire('Listo', json.message, 'success');
        cargarCompras();
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});

// ── Eliminar / Reactivar ─────────────────────────────────────────────────────
function eliminarCompra(id) {
    Swal.fire({
        title: '¿Desactivar esta compra?',
        text: 'El stock de los materiales comprados se revertirá. Podrás reactivarla luego.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarCompras('ELIMINARCOMPRA', { id });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarCompras();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

function reactivarCompra(id) {
    llamarCompras('REACTIVARCOMPRA', { id }).then(json => {
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarCompras();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>