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
                <th>Monto comprobante</th>
                <th>Comprobante</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tbodyCompras">
            <tr><td colspan="10" style="text-align:center;">Cargando...</td></tr>
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
            <div class="col-md-6 mb-2">
                <label class="form-label">Fecha de compra *</label>
                <input type="date" class="form-control" id="compra_fecha" required>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-2">
                <label class="form-label">Comprobante</label>
                <input type="file" class="form-control" id="compra_comprobante" accept=".jpg,.jpeg,.png,.webp,.pdf">
                <div class="form-text" id="compra_comprobante_actual"></div>
            </div>
            <div class="col-md-6 mb-2">
                <label class="form-label">Monto del comprobante (S/)</label>
                <input type="number" step="0.01" min="0" class="form-control" id="compra_total_img_cargado"
                       placeholder="Ej: 1180.00">
                <div class="form-text">Monto real que figura en el documento subido (para el módulo de egresos).</div>
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
            <div class="pc-table-wrap pc-table-responsive-cards" id="pc-detalle-compra-wrap">
            <table class="pc-table" id="tablaDetalleCompra">
                <thead>
                    <tr>
                        <th style="min-width:200px">Material</th>
                        <th style="min-width:150px">Unidad</th>
                        <th style="width:110px">Cantidad</th>
                        <th style="width:110px">P.U</th>
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

<!-- Modal Ver Comprobante -->
<div class="modal fade" id="modalVerComprobante" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Comprobante</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="cerrarVerComprobante()"></button>
      </div>
      <div class="modal-body text-center" id="verComprobanteBody" style="max-height:75vh; overflow:auto;">
        <!-- contenido dinámico (img o iframe) -->
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const CONTROLADOR_COMPRAS  = 'controllers/clssCompra.php';
const CONTROLADOR_UNIDADES = 'controllers/clssUnidadMedida.php';
const RUTA_VER_COMPROBANTE = 'controllers/ver_comprobante.php'; // sirve el archivo validando sesión
const modalCompra = new bootstrap.Modal(document.getElementById('modalCompra'));
const modalVerComprobante = new bootstrap.Modal(document.getElementById('modalVerComprobante'));

let modoEdicionCompra = false;
let compraIdActual = 0;
let comprobanteActualRuta = null;
let eliminarComprobanteFlag = false;
let contadorFilaMaterial = 0;
let materialesCache = null; // cache de la lista de materiales (BUSCARMATERIALES)
let unidadesPorFamiliaCache = {}; // cache: raizId -> [unidades compatibles]

document.addEventListener('DOMContentLoaded', () => {
    cargarProveedoresFiltro();
    cargarCompras().catch(err => {
        console.error('Error cargando datos iniciales:', err);
        document.getElementById('tbodyCompras').innerHTML =
            `<tr><td colspan="10" style="text-align:center;color:red;">Error de conexión con el servidor. Revisa la consola (F12).</td></tr>`;
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

// ── Llamadas genéricas ────────────────────────────────────────────────────
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
const llamarCompras  = (accion, params = {}) => llamar(CONTROLADOR_COMPRAS, accion, params);
const llamarUnidades = (accion, params = {}) => llamar(CONTROLADOR_UNIDADES, accion, params);

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

// Muestra el monto del comprobante y, si difiere del total calculado por
// materiales en más de 1 céntimo, agrega un ícono de alerta con el detalle.
function renderMontoComprobante(c) {
    if (c.total_img_cargado === null || c.total_img_cargado === undefined) {
        return '<span class="text-muted">-</span>';
    }
    const montoImg = parseFloat(c.total_img_cargado);
    const montoCalculado = parseFloat(c.total);
    const diferencia = Math.abs(montoImg - montoCalculado);
    const texto = formatearMoneda(montoImg);
    if (diferencia > 0.01) {
        return `<span title="Difiere del total calculado (${formatearMoneda(montoCalculado)}) por ${formatearMoneda(diferencia)}">
                    ${texto} <i class="fa-solid fa-triangle-exclamation text-warning"></i>
                </span>`;
    }
    return texto;
}

// ── Ver comprobante embebido (sin exponer la ruta física del archivo) ───────
// Se pasa el ID de la compra, no la ruta. El navegador pide
// controllers/ver_comprobante.php?id=X, que valida sesión antes de
// devolver el archivo. Un iframe muestra correctamente tanto imágenes
// como PDFs sin necesidad de saber la extensión en el frontend.
function verComprobante(compraId) {
    if (!compraId) return;
    const body = document.getElementById('verComprobanteBody');
    body.innerHTML = `<iframe src="${RUTA_VER_COMPROBANTE}?id=${compraId}"
                                style="width:100%; height:70vh; border:none;"></iframe>`;
    modalVerComprobante.show();
}

function cerrarVerComprobante() {
    // Limpia el iframe al cerrar para no dejar el PDF/imagen cargado en memoria
    document.getElementById('verComprobanteBody').innerHTML = '';
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
    if (materialesCache) return materialesCache;
    const json = await llamarCompras('BUSCARMATERIALES', {});
    materialesCache = json.success ? json.materiales : [];
    return materialesCache;
}

// Trae (y cachea por familia) SOLO las unidades compatibles con la raíz
// indicada: la raíz misma + las compuestas que apuntan a ella. raizId es
// el material.unidad_medida_id del material elegido en la fila.
async function obtenerUnidadesCompatibles(raizId) {
    const key = raizId || 'sin_unidad';
    if (unidadesPorFamiliaCache[key]) return unidadesPorFamiliaCache[key];
    if (!raizId) {
        unidadesPorFamiliaCache[key] = [];
        return [];
    }
    const json = await llamarUnidades('LISTARUNIDADESCOMPATIBLES', { unidad_medida_id: raizId });
    unidadesPorFamiliaCache[key] = json.success ? json.unidades : [];
    return unidadesPorFamiliaCache[key];
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
        tbody.innerHTML = `<tr><td colspan="10" style="text-align:center;">${json.message}</td></tr>`;
        return;
    }

    const compras = json.compras || [];
    if (compras.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;">No hay compras registradas.</td></tr>';
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
            <td data-label="Monto comprobante">${renderMontoComprobante(c)}</td>
            <td data-label="Comprobante">${c.img_comprobante
                ? `<button type="button" class="pc-icon-btn" onclick="verComprobante(${c.id})" title="Ver comprobante">
                       <i class="fa-solid fa-file-lines"></i></button>`
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
// sub_total (P.U guardado, aunque la BD siga llamándolo sub_total), total,
// comentario (tal cual vienen de OBTENERCOMPRA).
async function agregarFilaMaterial(datos = null) {
    const materiales = await obtenerOpcionesMateriales();
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

    const tr = document.createElement('tr');
    tr.id = filaId;
    tr.className = 'fila-detalle-material';
    tr.innerHTML = `
        <td data-label="Material"><select class="form-select mat-select" required>
                <option value="">Selecciona...</option>${opcionesMaterialHtml}
            </select></td>
        <td data-label="Unidad">
            <select class="form-select mat-unidad" required disabled>
                <option value="">Elige un material primero...</option>
            </select>
            <span class="pc-conversion-badge mat-conversion" style="display:none;">
                <i class="fa-solid fa-arrow-right-arrow-left"></i> <span class="mat-conversion-texto"></span>
            </span>
            <span class="pc-unidad-alerta mat-unidad-alerta" style="display:none;">
                <i class="fa-solid fa-triangle-exclamation"></i> Este material no tiene unidad asignada
            </span>
        </td>
        <td data-label="Cantidad">
            <input type="number" class="form-control mat-cantidad" min="0.01" step="0.01"
                    value="${datos ? datos.cantidad : ''}" required>
        </td>
        <td data-label="P.U"><input type="number" class="form-control mat-pu" min="0" step="0.01"
                    value="${datos ? datos.sub_total : ''}" placeholder="0.00"></td>
        <td data-label="Total"><input type="number" class="form-control mat-total" min="0" step="0.01"
                    value="${datos ? datos.total : ''}"></td>
        <td data-label="Comentario"><input type="text" class="form-control mat-comentario" placeholder="Opcional"
                    value="${datos ? (datos.comentario ?? '') : ''}"></td>
        <td class="pc-td-fila-acciones"><button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('tr').remove(); recalcularTotalCompra();">
                <i class="fa-solid fa-xmark"></i></button></td>
    `;
    wrap.appendChild(tr);

    const matSelect       = tr.querySelector('.mat-select');
    const unidadSelect    = tr.querySelector('.mat-unidad');
    const cantidadInput   = tr.querySelector('.mat-cantidad');
    const puInput         = tr.querySelector('.mat-pu');
    const totalInput      = tr.querySelector('.mat-total');
    const conversionBadge = tr.querySelector('.mat-conversion');
    const conversionTexto = tr.querySelector('.mat-conversion-texto');
    const unidadAlerta    = tr.querySelector('.mat-unidad-alerta');

    function actualizarConversion() {
        const unidadOpt = unidadSelect.selectedOptions[0];
        const equiv = unidadOpt ? parseFloat(unidadOpt.dataset.equiv || '1') : 1;
        const matOpt = matSelect.selectedOptions[0];
        const unidadBaseCorto = matOpt ? (matOpt.dataset.unidadCorto || '') : '';
        const cantidad = parseFloat(cantidadInput.value) || 0;
        if (cantidad > 0 && equiv && equiv !== 1) {
            conversionTexto.textContent = `= ${formatearCantidad(cantidad * equiv)} ${unidadBaseCorto}`.trim();
            conversionBadge.style.display = 'inline-flex';
        } else {
            conversionBadge.style.display = 'none';
        }
    }

    // Total = Cantidad × P.U, recalculado en cada tecla. Si el usuario edita
    // Total a mano, queda "congelado" ahí hasta que vuelva a tocar Cantidad
    // o P.U (entonces retoma el cálculo automático).
    function actualizarTotalDesdeCantidadYPU() {
        const cantidad = parseFloat(cantidadInput.value) || 0;
        const pu = parseFloat(puInput.value) || 0;
        totalInput.value = (cantidad * pu).toFixed(2);
        delete totalInput.dataset.tocadoManual;
        recalcularTotalCompra();
    }

    // Carga en el <select> de unidad SOLO las unidades compatibles con la
    // familia del material elegido: su unidad raíz + las compuestas que
    // apuntan a ella (LISTARUNIDADESCOMPATIBLES). Si el material no tiene
    // unidad base asignada, se bloquea el selector y se muestra una alerta
    // clara en vez de dejarlo como una opción más del combo.
    async function cargarUnidadesDeLaFila(unidadPreseleccionada = '') {
        const matOpt = matSelect.selectedOptions[0];
        const raizId = matOpt ? matOpt.dataset.unidadId : '';

        if (!raizId) {
            unidadSelect.disabled = true;
            unidadSelect.innerHTML = '<option value="">Elige un material primero...</option>';
            unidadAlerta.style.display = matOpt ? 'block' : 'none'; // solo alerta si YA eligió material sin unidad
            conversionBadge.style.display = 'none';
            return;
        }

        unidadAlerta.style.display = 'none';
        const compatibles = await obtenerUnidadesCompatibles(raizId);
        unidadSelect.disabled = false;
        unidadSelect.innerHTML = '<option value="">Selecciona...</option>' +
            compatibles.map(u => `<option value="${u.id}" data-equiv="${u.equivalencia}"
                    ${unidadPreseleccionada && unidadPreseleccionada == u.id ? 'selected' : ''}>
                ${u.nombre} (${u.nombre_corto})
             </option>`).join('');

        // Si no venía una unidad precargada (edición), se autoselecciona la
        // raíz del material — siempre está incluida en la lista de compatibles.
        if (!unidadPreseleccionada) {
            unidadSelect.value = raizId;
        }
        actualizarConversion();
    }

    matSelect.addEventListener('change', () => cargarUnidadesDeLaFila());
    unidadSelect.addEventListener('change', actualizarConversion);

    cantidadInput.addEventListener('input', () => {
        actualizarConversion();
        if (!totalInput.dataset.tocadoManual) actualizarTotalDesdeCantidadYPU();
    });
    puInput.addEventListener('input', actualizarTotalDesdeCantidadYPU);
    totalInput.addEventListener('input', () => {
        totalInput.dataset.tocadoManual = '1';
        recalcularTotalCompra();
    });

    // Precarga inicial: si venimos de edición (o ya hay material elegido),
    // cargamos de una vez las unidades compatibles con la unidad ya guardada.
    if (datos && datos.material_id) {
        await cargarUnidadesDeLaFila(datos.unidad_medida_id ?? '');
    }

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
        const precioUnitario = fila.querySelector('.mat-pu').value;
        const total = fila.querySelector('.mat-total').value;
        const comentario = fila.querySelector('.mat-comentario').value.trim();
        if (material_id && unidad_medida_id && cantidad) {
            detalle.push({
                material_id,
                unidad_medida_id,
                cantidad,
                sub_total: precioUnitario || 0, // el backend/BD sigue llamándolo sub_total (= P.U)
                total: total || ((precioUnitario || 0) * cantidad) || 0,
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
    document.getElementById('compra_total_img_cargado').value = '';
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
    document.getElementById('compra_total_img_cargado').value = c.total_img_cargado ?? '';

    await cargarProveedoresModal(c.proveedor_id);

    comprobanteActualRuta = c.img_comprobante || null;
    const infoComprobante = document.getElementById('compra_comprobante_actual');
    if (comprobanteActualRuta) {
        // Usa el ID de la compra (no la ruta física) para abrir el visor embebido.
        infoComprobante.innerHTML = `
            <a href="#" onclick="verComprobante(${compraIdActual}); return false;">Ver comprobante actual</a>
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
    formData.append('total_img_cargado', document.getElementById('compra_total_img_cargado').value);

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