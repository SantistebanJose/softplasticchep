<?php
$pageTitle    = 'Venta Rápida';
$pageSubtitle = 'Registra una venta de stock disponible';
$activePage   = 'punto_venta';
include("header.php");
$esAdminPV = strtoupper(trim((string)($_SESSION['rol_usuario'] ?? ''))) === 'ADMINISTRADOR';
?>

<style>
.pc-venta-item-resultados{ position:absolute; z-index:20; background:#fff; border:1px solid #eee2c8; border-radius:8px; width:100%; max-height:220px; overflow-y:auto; box-shadow:0 6px 18px rgba(0,0,0,.08); display:none; }
.pc-venta-item-resultados div{ padding:8px 10px; cursor:pointer; font-size:.85em; border-bottom:1px dashed #f0ead9; display:flex; align-items:center; flex-wrap:wrap; gap:0; }
.pc-venta-item-resultados div:hover{ background:#fdf6e3; }
.pc-venta-item-resultados .disp{ color:#8a8578; font-size:.8em; }
.pc-venta-item-resultados .disp b{ color:#2F6FED; }
.pc-venta-cliente-buscador{ position:relative; }

.pc-venta-color-dot{ display:inline-block; width:9px; height:9px; border-radius:50%; background:#ccc; margin-right:5px; vertical-align:middle; }
.pc-venta-color-dot.sin-color{ background:repeating-linear-gradient(45deg, #ccc, #ccc 2px, #fff 2px, #fff 4px); border:1px solid #bbb; }
.pc-venta-color-dot.mezcla{ background: conic-gradient(#2F6FED 0deg 90deg, #E0574C 90deg 180deg, #F0B429 180deg 270deg, #2FB170 270deg 360deg); }

.pc-venta-grid{ display:grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap:12px; }
.pc-venta-card{ position:relative; border:1px solid #eee2c8; border-radius:10px; padding:10px; display:flex; flex-direction:column; gap:5px; background:#fff; }
.pc-venta-card-img{ width:100%; height:84px; border-radius:8px; object-fit:cover; background:#f1efe9; }
.pc-venta-card-img-vacio{ width:100%; height:84px; border-radius:8px; background:#f1efe9; color:#c8c3b3; display:flex; align-items:center; justify-content:center; font-size:1.4em; }
.pc-venta-card-codigo{ font-size:.72em; color:#8a8578; font-weight:700; text-transform:uppercase; }
.pc-venta-card-nombre{ font-size:.9em; font-weight:600; color:#333; line-height:1.2; }
.pc-venta-card-color{ font-size:.8em; color:#555; }
.pc-venta-card-stock{ font-size:.78em; color:#555; }
.pc-venta-card-add{ position:absolute; top:8px; right:8px; width:30px; height:30px; border-radius:50%; border:none; background:#2FB170; color:#fff; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 6px rgba(0,0,0,.15); }
.pc-venta-card-add:hover{ background:#26965d; }

.pc-venta-carrito-sticky{ position:sticky; top:16px;container-type:inline-size }
#pv_total{ font-weight:700; font-size:1.2em; color:#2F6FED; }
.pc-venta-carrito-fila-color{ font-size:.75em; color:#8a8578; }
.pv-pagos{border:1px solid #e5eaf2;border-radius:12px;padding:12px;background:#fbfcff;margin-top:16px}
.pv-moneda-venta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.pv-moneda-venta small{grid-column:1/-1}
.pv-pagos{container-type:inline-size}
.pv-pago-row{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.15fr) minmax(0,.8fr) 40px;grid-template-areas:"medio cuenta monto eliminar" "referencia referencia referencia referencia";gap:8px;align-items:center;margin-top:8px}
.pv-pago-row.sin-cuenta{grid-template-columns:minmax(0,1fr) minmax(0,.8fr) 40px;grid-template-areas:"medio monto eliminar"}
.pv-pago-row .pv-medio{grid-area:medio}.pv-pago-row .pv-cuenta-select{grid-area:cuenta}.pv-pago-row .pv-monto{grid-area:monto}.pv-pago-row .pv-referencia{grid-area:referencia}.pv-pago-row>button{grid-area:eliminar;width:40px;padding:.375rem}
.pv-pago-row input,.pv-pago-row select{min-width:0;min-height:42px}
.pv-pago-row .pv-receptor-note{grid-column:1/-1;line-height:1.3}
.pv-pagos-total{display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;border-top:1px solid #e5eaf2;margin-top:12px;padding-top:10px;font-size:.9rem}
.pv-pagos-total .saldo{font-weight:800;color:#178448}.pv-pagos-total .saldo.invalido{color:#c0392b}
@container (max-width:520px){
    .pv-pagos{padding:10px}
    .pv-moneda-venta{grid-template-columns:1fr}
    .pv-pago-row{grid-template-columns:minmax(0,1fr) minmax(0,1fr) 40px;grid-template-areas:"medio monto eliminar" "cuenta cuenta cuenta" "referencia referencia referencia"}
    .pv-pago-row.sin-cuenta{grid-template-areas:"medio monto eliminar"}
    .pc-venta-carrito-sticky .pc-table-wrap{overflow:visible}
    .pc-venta-carrito-sticky .pc-table{width:100%;min-width:0}
    .pc-venta-carrito-sticky .pc-table thead{display:none}
    .pc-venta-carrito-sticky .pc-table tbody tr{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px;padding:10px 0;border-bottom:1px solid #e5eaf2}
    .pc-venta-carrito-sticky .pc-table tbody td{display:block;min-width:0;padding:4px 2px;border:0;overflow-wrap:anywhere}
    .pc-venta-carrito-sticky .pc-table tbody td:first-child{grid-column:1/-1}
    .pc-venta-carrito-sticky .pc-table tbody td:nth-child(2)::before{content:'Cantidad';display:block;font-size:.68rem;color:#788397;text-transform:uppercase}
    .pc-venta-carrito-sticky .pc-table tbody td:nth-child(3)::before{content:'P. unitario';display:block;font-size:.68rem;color:#788397;text-transform:uppercase}
    .pc-venta-carrito-sticky .pc-table tbody td:nth-child(4)::before{content:'Subtotal';display:block;font-size:.68rem;color:#788397;text-transform:uppercase}
    .pc-venta-carrito-sticky .pc-table tbody td:last-child{grid-column:1/-1;text-align:right}
}
</style>

<div class="pc-card mb-3">
    <div class="pc-card-header">
        <h2>Venta Rápida</h2>
    </div>
    <div class="p-3 pc-venta-cliente-buscador" style="max-width:460px;">
        <label class="form-label">Cliente <span class="text-muted fw-normal">(opcional)</span></label>
        <input type="text" class="form-control" id="pv_cliente_texto" placeholder="Buscar cliente por nombre o RUC/DNI..." autocomplete="off">
        <div class="pc-venta-item-resultados" id="pv_cliente_resultados"></div>
        <input type="hidden" id="pv_cliente_ruc">
        <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
            <div class="form-text m-0" id="pv_cliente_seleccionado">Cliente: Clientes Varios · RUC/DNI: 99999999</div>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="abrirRegistroClientePV()">
                <i class="fa-solid fa-user-plus"></i> Registrar cliente
            </button>
        </div>
    </div>
</div>

<!-- Modal de alta rápida de cliente -->
<div class="modal fade" id="modalClientePV" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="formClientePV">
        <div class="modal-header">
          <h5 class="modal-title">Registrar cliente</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">RUC / DNI *</label>
            <div class="input-group">
              <input type="text" class="form-control" id="pv_nuevo_cliente_ruc" maxlength="11" inputmode="numeric" required>
              <button type="button" class="btn btn-outline-primary" onclick="consultarClientePV()"><i class="fa-solid fa-magnifying-glass"></i> Consultar</button>
            </div>
            <div class="form-text" id="pv_nuevo_cliente_info">8 dígitos (DNI) u 11 dígitos (RUC).</div>
          </div>
          <div class="mb-2">
            <label class="form-label">Razón social / Nombre completo *</label>
            <input type="text" class="form-control" id="pv_nuevo_cliente_nombre" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Nombre comercial</label>
            <input type="text" class="form-control" id="pv_nuevo_cliente_comercial">
          </div>
          <input type="hidden" id="pv_nuevo_cliente_api">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar cliente</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if ($esAdminPV): ?>
<div class="modal fade" id="modalCuentasReceptorasPV" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Administrar cuentas receptoras</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small">Estas cuentas se usan para depósitos y transferencias. Puedes cambiar el nombre sin afectar los pagos registrados.</p>
        <form id="formCuentaReceptoraPV" class="border rounded p-3 mb-3">
          <input type="hidden" id="pv_cuenta_id">
          <label class="form-label fw-semibold" for="pv_cuenta_nombre">Nombre de la cuenta</label>
          <div class="input-group">
            <input type="text" class="form-control" id="pv_cuenta_nombre" maxlength="100" placeholder="Ej. Cuenta empresa - BCP" required>
            <button type="submit" class="btn btn-primary" id="pv_cuenta_guardar">Agregar</button>
          </div>
          <button type="button" class="btn btn-sm btn-link px-0 d-none" id="pv_cuenta_cancelar" onclick="cancelarEdicionCuentaPV()">Cancelar edición</button>
        </form>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Cuenta</th><th class="text-end">Acción</th></tr></thead>
            <tbody id="pv_cuentas_tbody"><tr><td colspan="2" class="text-muted">Cargando cuentas...</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="pc-card">
            <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h2>Artículos</h2>
            </div>
            <div class="p-3">
                <input type="text" id="pv_producto_texto" class="form-control mb-3" placeholder="Buscar producto..." autocomplete="off">
                <div id="pv_grid" class="pc-venta-grid">
                    <div class="text-muted text-center py-4" style="grid-column:1/-1;">Cargando artículos...</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="pc-card pc-venta-carrito-sticky">
            <div class="pc-card-header"><h2>Carrito</h2></div>
            <div class="p-3">
                <div id="pv_carrito_vacio" class="text-muted text-center py-4">Aún no agregaste productos.</div>
                <div class="pc-table-wrap" id="pv_carrito_wrap" style="display:none;">
                    <table class="pc-table">
                        <thead>
                            <tr><th>Artículo</th><th>Cant.</th><th>P. Unit.</th><th>Subtotal</th><th></th></tr>
                        </thead>
                        <tbody id="pv_carrito_tbody"></tbody>
                    </table>
                </div>
                <div class="text-end mt-3">
                    Total: <span id="pv_total">S/ 0.00</span>
                    <div id="pv_total_pen_info" class="small text-muted"></div>
                </div>
                <div class="pv-pagos">
                    <div class="pv-moneda-venta mb-3">
                        <div>
                            <label class="form-label fw-semibold" for="pv_moneda">Moneda de cobro</label>
                            <select id="pv_moneda" class="form-select">
                                <option value="PEN">Soles (PEN)</option>
                                <option value="USD">Dólares (USD)</option>
                                <option value="EUR">Euros (EUR)</option>
                            </select>
                        </div>
                        <div id="pv_tc_fuente_wrap" hidden>
                            <label class="form-label fw-semibold" for="pv_tc_fuente">Tipo de cambio</label>
                            <select id="pv_tc_fuente" class="form-select">
                                <option value="oficial">Tipo de cambio oficial</option>
                                <option value="calle">Cambio de calle</option>
                            </select>
                        </div>
                        <div id="pv_tc_manual_wrap" hidden>
                            <label class="form-label fw-semibold" for="pv_tc_manual">Cambio aplicado (S/ por unidad)</label>
                            <input id="pv_tc_manual" type="number" class="form-control" min="0.0001" step="0.0001" placeholder="Ej. 3.7500">
                        </div>
                        <small id="pv_tc_info" class="text-muted" hidden></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="pv_vendedor">Vendedor *</label>
                        <select id="pv_vendedor" class="form-select"><option value="">Cargando vendedores...</option></select>
                        <small class="text-muted">Se selecciona inicialmente el usuario que inició sesión.</small>
                    </div>
                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                        <strong>Forma de pago *</strong>
                        <?php if ($esAdminPV): ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="gestionarCuentasReceptorasPV()"><i class="fa-solid fa-wallet"></i> Administrar cuentas</button>
                        <?php endif; ?>
                    </div>
                    <div id="pv_pagos_lista"></div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="agregarPagoPV()"><i class="fa-solid fa-plus"></i> Agregar otro pago</button>
                    <div class="pv-pagos-total">
                        <span>Pagado: <b id="pv_pago_total">S/ 0.00</b></span>
                        <span class="saldo" id="pv_pago_saldo">Pendiente: S/ 0.00</span>
                    </div>
                </div>
                <button type="button" class="pc-btn pc-btn-primary w-100 mt-2" id="pv_btn_registrar" onclick="registrarVentaRapida()">
                    <i class="fa-solid fa-cart-check"></i> Registrar venta
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const CONTROLADOR_VENTA = 'controllers/clssVenta.php';
const CONTROLADOR_PROVEEDORES = 'controllers/clssProveedor.php';
const TICKET_PDF_URL = 'controllers/ticketPdf.php';
const CLIENTE_VARIOS_RUC = '99999999';
const USUARIO_ID_ACTUAL_PV = <?= json_encode((int)($_SESSION['usuario_id'] ?? 0)) ?>;
let monedaVentaPV = 'PEN';
let tasaCambioVentaPV = 1;
let tipoCambioOficialVentaPV = null;
const modalClientePV = new bootstrap.Modal(document.getElementById('modalClientePV'));
const elementoModalCuentasPV = document.getElementById('modalCuentasReceptorasPV');
const modalCuentasPV = elementoModalCuentasPV ? new bootstrap.Modal(elementoModalCuentasPV) : null;

async function llamarVenta(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_VENTA, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body
    });
    const texto = await resp.text();
    try {
        return JSON.parse(texto);
    } catch (e) {
        console.error(`Respuesta no es JSON válido para accion=${accion}:`, texto);
        throw new Error(`El servidor no devolvió JSON válido (accion=${accion}).`);
    }
}

async function llamarProveedoresPV(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_PROVEEDORES, {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body
    });
    const texto = await resp.text();
    try { return JSON.parse(texto); }
    catch (e) { console.error(`Respuesta no válida para accion=${accion}:`, texto); throw new Error('El servidor no devolvió JSON válido.'); }
}

function formatearMoneda(n, moneda = 'PEN') {
    return new Intl.NumberFormat('es-PE', { style: 'currency', currency: moneda, minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n || 0));
}
function formatearCantidadVenta(n) {
    return Number(n || 0).toLocaleString('es-PE', { maximumFractionDigits: 0 });
}

const PALETA_COLOR_FALLBACK_VENTA = {
    'VERDE': '#639922', 'AZUL': '#378ADD', 'CELESTE': '#5DB8E8', 'ROJO': '#E24B4A',
    'AMARILLO': '#EF9F27', 'NARANJA': '#D85A30', 'MORADO': '#7F77DD', 'VIOLETA': '#7F77DD',
    'ROSADO': '#D4537E', 'ROSA': '#D4537E', 'NEGRO': '#2C2C2A', 'BLANCO': '#D3D1C7',
    'GRIS': '#888780', 'MARRON': '#712B13', 'CAFE': '#712B13', 'TURQUESA': '#1D9E75',
    'PLOMO': '#5F5E5A', 'BEIGE': '#B4B2A9',
};
function colorHexParaVenta(colorNombre, colorHexBD) {
    if (colorHexBD) return colorHexBD.startsWith('#') ? colorHexBD : `#${colorHexBD}`;
    const clave = String(colorNombre ?? '').trim().toUpperCase();
    if (PALETA_COLOR_FALLBACK_VENTA[clave]) return PALETA_COLOR_FALLBACK_VENTA[clave];
    let hash = 0;
    for (let i = 0; i < clave.length; i++) hash = clave.charCodeAt(i) + ((hash << 5) - hash);
    const hue = Math.abs(hash) % 360;
    return `hsl(${hue}, 45%, 55%)`;
}
function resolverImagenVenta(ruta) {
    if (!ruta) return null;
    return ruta.startsWith('http') ? ruta : ruta;
}

document.addEventListener('DOMContentLoaded', () => {
    cargarGridProductos('');
    cargarCatalogosPagoPV();
    actualizarTipoCambioVentaPV();
    seleccionarClienteVariosPV();

    let debounceProducto = null;
    document.getElementById('pv_producto_texto').addEventListener('input', function () {
        clearTimeout(debounceProducto);
        const valor = this.value.trim();
        debounceProducto = setTimeout(() => cargarGridProductos(valor), 300);
    });
});

function fechaActualVentaPV() {
    const hoy = new Date();
    return `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`;
}

async function actualizarTipoCambioVentaPV() {
    const moneda = document.getElementById('pv_moneda').value;
    const fuente = document.getElementById('pv_tc_fuente').value;
    const fuenteWrap = document.getElementById('pv_tc_fuente_wrap');
    const manualWrap = document.getElementById('pv_tc_manual_wrap');
    const info = document.getElementById('pv_tc_info');
    monedaVentaPV = moneda;
    if (moneda === 'PEN') {
        tasaCambioVentaPV = 1;
        fuenteWrap.hidden = true;
        manualWrap.hidden = true;
        info.hidden = true;
        actualizarResumenEquivalenciaVentaPV();
        ajustarPagoAutomaticoPV();
        renderPagosPV();
        return;
    }

    fuenteWrap.hidden = false;
    info.hidden = false;
    if (fuente === 'calle') {
        manualWrap.hidden = false;
        const campo = document.getElementById('pv_tc_manual');
        if (!campo.value && tipoCambioOficialVentaPV && tipoCambioOficialVentaPV.moneda === moneda) campo.value = Number(tipoCambioOficialVentaPV.venta).toFixed(4);
        tasaCambioVentaPV = Number(campo.value) || 0;
        info.textContent = tasaCambioVentaPV > 0
            ? `Cambio manual aplicado: ${tasaCambioVentaPV.toFixed(4)} S/ por ${moneda}.`
            : 'Ingresa el tipo de cambio de calle que acordaron.';
        actualizarResumenEquivalenciaVentaPV();
        ajustarPagoAutomaticoPV();
        renderPagosPV();
        return;
    }

    manualWrap.hidden = true;
    info.textContent = 'Consultando tipo de cambio oficial…';
    tasaCambioVentaPV = 0;
    actualizarResumenEquivalenciaVentaPV();
    ajustarPagoAutomaticoPV();
    renderPagosPV();
    try {
        const respuesta = await llamarVenta('CONSULTARTIPOCAMBIOVENTA', { fecha: fechaActualVentaPV(), moneda });
        if (!respuesta.success) throw new Error(respuesta.message || 'No se pudo consultar el tipo de cambio oficial.');
        tipoCambioOficialVentaPV = { ...respuesta.tipo_cambio, moneda };
        tasaCambioVentaPV = Number(tipoCambioOficialVentaPV.venta) || 0;
        info.textContent = `${moneda} · compra ${Number(tipoCambioOficialVentaPV.compra).toFixed(4)} / venta ${tasaCambioVentaPV.toFixed(4)} (${tipoCambioOficialVentaPV.fecha})`;
        actualizarResumenEquivalenciaVentaPV();
        ajustarPagoAutomaticoPV();
        renderPagosPV();
    } catch (error) {
        info.textContent = `${error.message} Puedes elegir cambio de calle e ingresarlo manualmente.`;
        actualizarResumenEquivalenciaVentaPV();
        ajustarPagoAutomaticoPV();
        renderPagosPV();
    }
}

function actualizarResumenEquivalenciaVentaPV() {
    const total = totalCarritoPV();
    const nodo = document.getElementById('pv_total_pen_info');
    if (!nodo) return;
    nodo.textContent = monedaVentaPV === 'PEN' ? '' : (tasaCambioVentaPV > 0
        ? `Cobro equivalente: ${formatearMoneda(total / tasaCambioVentaPV, monedaVentaPV)} · cambio ${tasaCambioVentaPV.toFixed(4)} S/ por ${monedaVentaPV}`
        : 'Ingresa o consulta el tipo de cambio para calcular el cobro.');
}

document.getElementById('pv_moneda').addEventListener('change', async event => {
    tipoCambioOficialVentaPV = null;
    document.getElementById('pv_tc_manual').value = '';
    await actualizarTipoCambioVentaPV();
});
document.getElementById('pv_tc_fuente').addEventListener('change', actualizarTipoCambioVentaPV);
document.getElementById('pv_tc_manual').addEventListener('input', () => {
    if (document.getElementById('pv_tc_fuente').value !== 'calle') return;
    tasaCambioVentaPV = Number(document.getElementById('pv_tc_manual').value) || 0;
    document.getElementById('pv_tc_info').textContent = tasaCambioVentaPV > 0
        ? `Cambio manual aplicado: ${tasaCambioVentaPV.toFixed(4)} S/ por ${monedaVentaPV}.`
        : 'Ingresa el tipo de cambio de calle que acordaron.';
    actualizarResumenEquivalenciaVentaPV();
    ajustarPagoAutomaticoPV();
    actualizarResumenPagosPV();
});

// ── Cliente: buscador ────────────────────────────────────────────────────────
let debounceClienteTimer = null;
document.getElementById('pv_cliente_texto').addEventListener('input', function () {
    document.getElementById('pv_cliente_ruc').value = '';
    document.getElementById('pv_cliente_seleccionado').textContent = `Cliente: Clientes Varios · RUC/DNI: ${CLIENTE_VARIOS_RUC}`;
    clearTimeout(debounceClienteTimer);
    const valor = this.value.trim();
    const cont = document.getElementById('pv_cliente_resultados');
    if (valor.length < 2) { cont.style.display = 'none'; return; }
    debounceClienteTimer = setTimeout(async () => {
        const json = await llamarVenta('BUSCARCLIENTES', { texto: valor });
        if (!json.success || !json.clientes.length) { cont.style.display = 'none'; return; }
        cont.innerHTML = json.clientes.map(c => `
            <div onclick='seleccionarClientePV(${JSON.stringify(c.ruc)}, ${JSON.stringify(c.razon_social)})'>
                <b>${c.razon_social}</b> ${c.nombre_comercial ? '(' + c.nombre_comercial + ')' : ''}
                <div class="disp">${c.ruc}</div>
            </div>
        `).join('');
        cont.style.display = 'block';
    }, 300);
});

function seleccionarClientePV(ruc, nombre) {
    document.getElementById('pv_cliente_ruc').value = ruc;
    document.getElementById('pv_cliente_texto').value = nombre;
    document.getElementById('pv_cliente_seleccionado').textContent = `Seleccionado: ${nombre} (${ruc})`;
    document.getElementById('pv_cliente_resultados').style.display = 'none';
}

function seleccionarClienteVariosPV() {
    document.getElementById('pv_cliente_ruc').value = CLIENTE_VARIOS_RUC;
    document.getElementById('pv_cliente_texto').value = '';
    document.getElementById('pv_cliente_seleccionado').textContent = `Cliente: Clientes Varios · RUC/DNI: ${CLIENTE_VARIOS_RUC}`;
}

function abrirRegistroClientePV() {
    document.getElementById('formClientePV').reset();
    document.getElementById('pv_nuevo_cliente_api').value = '';
    document.getElementById('pv_nuevo_cliente_info').textContent = '8 dígitos (DNI) u 11 dígitos (RUC).';
    modalClientePV.show();
}

async function consultarClientePV() {
    const numero = document.getElementById('pv_nuevo_cliente_ruc').value.trim();
    if (!/^\d{8}$|^\d{11}$/.test(numero)) {
        Swal.fire('Atención', 'Ingresa un RUC (11 dígitos) o DNI (8 dígitos) válido.', 'warning');
        return;
    }
    const info = document.getElementById('pv_nuevo_cliente_info');
    info.textContent = 'Consultando...';
    const json = await llamarProveedoresPV('CONSULTARDOCUMENTO', { numero });
    if (!json.success) { info.textContent = ''; Swal.fire('Error', json.message, 'error'); return; }
    document.getElementById('pv_nuevo_cliente_nombre').value = json.data.name || '';
    document.getElementById('pv_nuevo_cliente_api').value = json.raw || '';
    info.textContent = json.data.tipo === 'RUC' ? `Empresa · Estado: ${json.data.state || '-'}` : 'Persona natural (DNI)';
}

document.getElementById('pv_nuevo_cliente_ruc').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '');
});

document.getElementById('formClientePV').addEventListener('submit', async function (e) {
    e.preventDefault();
    const ruc = document.getElementById('pv_nuevo_cliente_ruc').value.trim();
    const razon = document.getElementById('pv_nuevo_cliente_nombre').value.trim();
    if (!/^\d{8}$|^\d{11}$/.test(ruc) || !razon) {
        Swal.fire('Atención', 'Completa un RUC/DNI válido y el nombre del cliente.', 'warning');
        return;
    }

    // Un cliente registrado desde esta pantalla debe ser nuevo. No se debe
    // sobrescribir ni convertir un proveedor o cliente que ya existe.
    const existente = await llamarProveedoresPV('OBTENERPROVEEDOR', { ruc });
    if (existente.success) {
        let descripcion = 'proveedor y/o cliente';
        try {
            const tiposExistentes = JSON.parse(existente.proveedor.js_tipo || '[]');
            if (tiposExistentes.includes('cliente') && tiposExistentes.includes('proveedor')) descripcion = 'proveedor y cliente';
            else if (tiposExistentes.includes('cliente')) descripcion = 'cliente';
            else if (tiposExistentes.includes('proveedor')) descripcion = 'proveedor';
        } catch (_) { /* mantenemos el mensaje general */ }
        Swal.fire('Cliente ya registrado', `El RUC/DNI ${ruc} ya está registrado como ${descripcion}. Búscalo y selecciónalo en el campo Cliente.`, 'warning');
        return;
    }
    const json = await llamarProveedoresPV('GUARDARPROVEEDOR', {
        ruc, razon_social: razon,
        nombre_comercial: document.getElementById('pv_nuevo_cliente_comercial').value.trim(),
        js_tipo: JSON.stringify(['cliente']), telefonos_contacto: '[]',
        solo_nuevo_cliente: '1',
        js_consulta_api: document.getElementById('pv_nuevo_cliente_api').value,
    });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }
    modalClientePV.hide();
    seleccionarClientePV(ruc, razon);
    Swal.fire('Listo', 'Cliente registrado y seleccionado para esta venta.', 'success');
});

document.addEventListener('click', (e) => {
    if (!e.target.closest('.pc-venta-cliente-buscador')) {
        document.getElementById('pv_cliente_resultados').style.display = 'none';
    }
});

// ── Grid de productos disponibles ────────────────────────────────────────────
async function cargarGridProductos(texto) {
    const grid = document.getElementById('pv_grid');
    const json = await llamarVenta('BUSCARDISPONIBLESVENTA', { texto });

    if (!json.success) {
        grid.innerHTML = `<div class="text-danger text-center py-4" style="grid-column:1/-1;">${json.message}</div>`;
        return;
    }
    if (!json.disponibles.length) {
        grid.innerHTML = '<div class="text-muted text-center py-4" style="grid-column:1/-1;">Sin paquetes disponibles con ese texto.</div>';
        return;
    }

    grid.innerHTML = json.disponibles.map(d => {
        const imagen = resolverImagenVenta(d.producto_imagen);
        const imgHtml = imagen
            ? `<img class="pc-venta-card-img" src="${imagen}" loading="lazy" alt="" onerror="this.outerHTML='<div class=&quot;pc-venta-card-img-vacio&quot;><i class=&quot;fa-regular fa-image&quot;></i></div>'">`
            : `<div class="pc-venta-card-img-vacio"><i class="fa-regular fa-image"></i></div>`;

        return `
        <div class="pc-venta-card">
            ${imgHtml}
            <div class="pc-venta-card-codigo">${d.producto_codigo}</div>
            <div class="pc-venta-card-nombre">${d.producto}</div>
            <div class="pc-venta-card-color">Todos los colores</div>
            <div class="pc-venta-card-stock">Disponible: <b>${formatearCantidadVenta(d.paquetes_disponibles)} ${d.unidad_venta_corto ?? 'paq.'}</b></div>
            <button type="button" class="pc-venta-card-add" onclick='abrirAgregarCarrito(${JSON.stringify(d)})' title="Agregar al carrito">
                <i class="fa-solid fa-plus"></i>
            </button>
        </div>`;
    }).join('');
}

// ── Carrito ──────────────────────────────────────────────────────────────────
let carritoVenta = [];
let contadorCarrito = 0;
let vendedoresPV = [];
let cuentasPV = [];
let pagosPV = [{ uid: 1, medio_pago: 'efectivo', cuenta_receptora_id: '', monto: 0, referencia: '', automatico: true }];
let contadorPagoPV = 1;

const MEDIOS_PAGO_PV = {
    efectivo: 'Efectivo', deposito: 'Depósito', transferencia: 'Transferencia', yape: 'Yape', plin: 'Plin'
};

async function cargarCatalogosPagoPV() {
    const vendedorSelect = document.getElementById('pv_vendedor');
    try {
        const [jsonVendedores, jsonCuentas] = await Promise.all([
            llamarVenta('LISTARVENDEDORESVENTA'),
            llamarVenta('LISTARCUENTASRECEPTORAS')
        ]);
        if (!jsonVendedores.success) throw new Error(jsonVendedores.message || 'No se pudieron cargar los vendedores.');
        if (!jsonCuentas.success) throw new Error(jsonCuentas.message || 'No se pudieron cargar las cuentas receptoras.');
        vendedoresPV = jsonVendedores.vendedores || [];
        cuentasPV = jsonCuentas.cuentas || [];
        vendedorSelect.innerHTML = '<option value="">Selecciona un vendedor</option>' + vendedoresPV.map(v =>
            `<option value="${Number(v.id)}">${escaparHtmlPV(v.nombre_completo)}</option>`
        ).join('');
        if (vendedoresPV.some(v => Number(v.id) === USUARIO_ID_ACTUAL_PV)) vendedorSelect.value = String(USUARIO_ID_ACTUAL_PV);
        if (!vendedoresPV.length) vendedorSelect.innerHTML = '<option value="">No hay usuarios habilitados como vendedores</option>';
        renderPagosPV();
        actualizarResumenPagosPV();
    } catch (error) {
        vendedorSelect.innerHTML = '<option value="">No se pudieron cargar los vendedores</option>';
        document.getElementById('pv_pagos_lista').innerHTML = `<div class="text-danger small mt-2">${escaparHtmlPV(error.message)}</div>`;
    }
}

function totalCarritoPV() {
    return carritoVenta.reduce((suma, item) => suma + Number(item.cantidad) * Number(item.precio), 0);
}

function totalCobroPV() {
    const totalPen = totalCarritoPV();
    return monedaVentaPV === 'PEN' ? totalPen : (tasaCambioVentaPV > 0 ? totalPen / tasaCambioVentaPV : 0);
}

function renderPagosPV() {
    const contenedor = document.getElementById('pv_pagos_lista');
    if (!contenedor) return;
    const opcionesCuentasDeposito = '<option value="">Selecciona cuenta receptora</option>' + cuentasPV.map(c =>
        `<option value="${Number(c.id)}">${escaparHtmlPV(c.nombre)}</option>`
    ).join('');
    const opcionesCuentasDigital = '<option value="">Billetera del vendedor</option>' + cuentasPV.map(c =>
        `<option value="${Number(c.id)}">${escaparHtmlPV(c.nombre)}</option>`
    ).join('');
    contenedor.innerHTML = pagosPV.map(pago => {
        const requiereCuenta = ['deposito', 'transferencia'].includes(pago.medio_pago);
        const esDigital = ['yape', 'plin'].includes(pago.medio_pago);
        const permiteDestinoCuenta = requiereCuenta || esDigital;
        const vendedorSeleccionado = vendedoresPV.find(v => Number(v.id) === Number(document.getElementById('pv_vendedor').value));
        const cuentaSeleccionada = cuentasPV.find(c => Number(c.id) === Number(pago.cuenta_receptora_id));
        const opcionesMedios = Object.entries(MEDIOS_PAGO_PV).map(([valor, texto]) =>
            `<option value="${valor}" ${pago.medio_pago === valor ? 'selected' : ''}>${texto}</option>`
        ).join('');
        const notaDestinoDigital = cuentaSeleccionada
            ? `Se recibirá en la cuenta de la empresa: ${escaparHtmlPV(cuentaSeleccionada.nombre)}`
            : `Se abonará al vendedor: ${escaparHtmlPV(vendedorSeleccionado?.nombre_completo || 'selecciona un vendedor')}`;
        return `<div class="pv-pago-row ${permiteDestinoCuenta ? '' : 'sin-cuenta'}" data-pago="${pago.uid}">
            <select class="form-select pv-medio" aria-label="Medio de pago">${opcionesMedios}</select>
            ${permiteDestinoCuenta ? `<select class="form-select pv-cuenta-select" aria-label="${esDigital ? 'Destino de billetera digital' : 'Cuenta receptora'}">${esDigital ? opcionesCuentasDigital : opcionesCuentasDeposito}</select>` : ''}
            <input class="form-control pv-monto" type="number" min="0.01" step="0.01" inputmode="decimal" placeholder="Monto ${monedaVentaPV}" value="${pago.monto ? Number(pago.monto).toFixed(2) : ''}" aria-label="Monto en ${monedaVentaPV}">
            <button type="button" class="btn btn-outline-danger" onclick="quitarPagoPV(${pago.uid})" title="Quitar pago" ${pagosPV.length === 1 ? 'disabled' : ''}><i class="fa-solid fa-trash"></i></button>
            ${permiteDestinoCuenta ? `<input class="form-control pv-referencia" maxlength="100" placeholder="Referencia (opcional)" value="${escaparHtmlPV(pago.referencia)}" aria-label="Referencia">` : ''}
            ${esDigital ? `<small class="pv-receptor-note text-muted">${notaDestinoDigital}</small>` : ''}
        </div>`;
    }).join('');
    pagosPV.forEach(pago => {
        const fila = contenedor.querySelector(`[data-pago="${pago.uid}"]`);
        const cuentaSelect = fila?.querySelector('.pv-cuenta-select');
        if (cuentaSelect && pago.cuenta_receptora_id) cuentaSelect.value = String(pago.cuenta_receptora_id);
    });
    actualizarResumenPagosPV();
}

function actualizarResumenPagosPV() {
    const total = totalCobroPV();
    const pagado = pagosPV.reduce((suma, pago) => suma + (Number(pago.monto) || 0), 0);
    const diferencia = Math.round((pagado - total) * 100) / 100;
    const pagoTotal = document.getElementById('pv_pago_total');
    const saldo = document.getElementById('pv_pago_saldo');
    if (!pagoTotal || !saldo) return;
    pagoTotal.textContent = formatearMoneda(pagado, monedaVentaPV);
    saldo.classList.toggle('invalido', Math.abs(diferencia) > 0.009);
    saldo.textContent = Math.abs(diferencia) <= 0.009 ? 'Pago completo' : diferencia < 0
        ? `Pendiente: ${formatearMoneda(-diferencia, monedaVentaPV)}` : `Exceso: ${formatearMoneda(diferencia, monedaVentaPV)}`;
}

function ajustarPagoAutomaticoPV(excluirUid = null) {
    const automatico = pagosPV.find(pago => pago.automatico && pago.uid !== excluirUid);
    if (!automatico) return;
    const otros = pagosPV.filter(pago => pago !== automatico).reduce((suma, pago) => suma + (Number(pago.monto) || 0), 0);
    automatico.monto = Math.max(0, Math.round((totalCobroPV() - otros) * 100) / 100);
    const fila = document.querySelector(`#pv_pagos_lista [data-pago="${automatico.uid}"]`);
    const input = fila?.querySelector('.pv-monto');
    if (input && document.activeElement !== input) input.value = automatico.monto ? automatico.monto.toFixed(2) : '';
}

function agregarPagoPV() {
    const pagado = pagosPV.reduce((suma, pago) => suma + (Number(pago.monto) || 0), 0);
    pagosPV.push({ uid: ++contadorPagoPV, medio_pago: 'efectivo', cuenta_receptora_id: '', monto: Math.max(0, Math.round((totalCobroPV() - pagado) * 100) / 100), referencia: '', automatico: false });
    renderPagosPV();
}

function quitarPagoPV(uid) {
    if (pagosPV.length <= 1) return;
    pagosPV = pagosPV.filter(pago => pago.uid !== uid);
    renderPagosPV();
}

document.getElementById('pv_pagos_lista').addEventListener('change', event => {
    const fila = event.target.closest('[data-pago]');
    if (!fila) return;
    const pago = pagosPV.find(p => p.uid === Number(fila.dataset.pago));
    if (!pago) return;
    if (event.target.classList.contains('pv-medio')) {
        pago.medio_pago = event.target.value;
        pago.cuenta_receptora_id = '';
        renderPagosPV();
        return;
    }
    if (event.target.classList.contains('pv-cuenta-select')) {
        pago.cuenta_receptora_id = event.target.value;
        if (['yape', 'plin'].includes(pago.medio_pago)) {
            renderPagosPV();
            return;
        }
    }
    if (event.target.classList.contains('pv-monto')) { pago.monto = Number(event.target.value) || 0; pago.automatico = false; }
    if (event.target.classList.contains('pv-referencia')) pago.referencia = event.target.value;
    ajustarPagoAutomaticoPV(pago.uid);
    actualizarResumenPagosPV();
});
document.getElementById('pv_vendedor').addEventListener('change', renderPagosPV);
document.getElementById('pv_pagos_lista').addEventListener('input', event => {
    if (event.target.classList.contains('pv-monto') || event.target.classList.contains('pv-referencia')) {
        const fila = event.target.closest('[data-pago]');
        const pago = pagosPV.find(p => p.uid === Number(fila?.dataset.pago));
        if (!pago) return;
        if (event.target.classList.contains('pv-monto')) { pago.monto = Number(event.target.value) || 0; pago.automatico = false; }
        else pago.referencia = event.target.value;
        ajustarPagoAutomaticoPV(pago.uid);
        actualizarResumenPagosPV();
    }
});

async function gestionarCuentasReceptorasPV() {
    if (!modalCuentasPV) return;
    cancelarEdicionCuentaPV();
    await actualizarListaCuentasGestionPV();
    modalCuentasPV.show();
}

async function actualizarListaCuentasGestionPV() {
    const tbody = document.getElementById('pv_cuentas_tbody');
    const json = await llamarVenta('LISTARCUENTASRECEPTORAS');
    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="2" class="text-danger">${escaparHtmlPV(json.message)}</td></tr>`;
        return;
    }
    cuentasPV = json.cuentas || [];
    tbody.innerHTML = cuentasPV.length ? cuentasPV.map(cuenta => `
        <tr>
            <td>${escaparHtmlPV(cuenta.nombre)}</td>
            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" onclick="editarCuentaReceptoraPV(${Number(cuenta.id)})"><i class="fa-solid fa-pen"></i> Editar</button></td>
        </tr>`).join('') : '<tr><td colspan="2" class="text-muted">Aún no hay cuentas registradas.</td></tr>';
    renderPagosPV();
}

function editarCuentaReceptoraPV(id) {
    const cuenta = cuentasPV.find(item => Number(item.id) === Number(id));
    if (!cuenta) return;
    document.getElementById('pv_cuenta_id').value = String(cuenta.id);
    document.getElementById('pv_cuenta_nombre').value = cuenta.nombre;
    document.getElementById('pv_cuenta_guardar').textContent = 'Guardar cambios';
    document.getElementById('pv_cuenta_cancelar').classList.remove('d-none');
    document.getElementById('pv_cuenta_nombre').focus();
}

function cancelarEdicionCuentaPV() {
    const formulario = document.getElementById('formCuentaReceptoraPV');
    if (!formulario) return;
    formulario.reset();
    document.getElementById('pv_cuenta_id').value = '';
    document.getElementById('pv_cuenta_guardar').textContent = 'Agregar';
    document.getElementById('pv_cuenta_cancelar').classList.add('d-none');
}

document.getElementById('formCuentaReceptoraPV')?.addEventListener('submit', async event => {
    event.preventDefault();
    const id = document.getElementById('pv_cuenta_id').value;
    const nombre = document.getElementById('pv_cuenta_nombre').value.trim();
    const accion = id ? 'EDITARCUENTARECEPTORA' : 'CREARCUENTARECEPTORA';
    const json = await llamarVenta(accion, { id, nombre });
    if (!json.success) { Swal.fire('No se pudo guardar', json.message, 'error'); return; }
    cancelarEdicionCuentaPV();
    await actualizarListaCuentasGestionPV();
    Swal.fire('Listo', id ? 'Nombre de cuenta actualizado.' : 'Cuenta agregada.', 'success');
});

function validarDatosVentaPV() {
    if (!document.getElementById('pv_vendedor').value) throw new Error('Selecciona al vendedor de esta venta.');
    if (monedaVentaPV !== 'PEN' && (!Number.isFinite(tasaCambioVentaPV) || tasaCambioVentaPV <= 0)) throw new Error('Consulta o ingresa un tipo de cambio válido antes de registrar la venta.');
    if (!pagosPV.length) throw new Error('Agrega al menos una forma de pago.');
    const pagos = pagosPV.map(pago => ({
        medio_pago: pago.medio_pago,
        cuenta_receptora_id: ['deposito', 'transferencia', 'yape', 'plin'].includes(pago.medio_pago) ? (Number(pago.cuenta_receptora_id) || null) : null,
        monto: Math.round((Number(pago.monto) || 0) * 100) / 100,
        referencia: pago.referencia.trim()
    }));
    if (pagos.some(p => p.monto <= 0)) throw new Error('Cada forma de pago debe tener un monto mayor que cero.');
    if (pagos.some(p => ['deposito', 'transferencia'].includes(p.medio_pago) && !p.cuenta_receptora_id)) throw new Error('Selecciona la cuenta receptora de cada depósito o transferencia.');
    if (Math.abs(pagos.reduce((s, p) => s + p.monto, 0) - totalCobroPV()) > 0.009) throw new Error(`Los montos de pago deben sumar exactamente el total de cobro en ${monedaVentaPV}.`);
    return {
        vendedor_usuario_id: document.getElementById('pv_vendedor').value,
        pagos,
        moneda: monedaVentaPV,
        fuente_tipo_cambio: monedaVentaPV === 'PEN' ? 'local' : document.getElementById('pv_tc_fuente').value,
        tipo_cambio: monedaVentaPV === 'PEN' ? 1 : tasaCambioVentaPV
    };
}

function abrirAgregarCarrito(datos, filaExistente = null) {
    const disp = Number(datos.paquetes_disponibles) || 0;
    const cantidadInicial = filaExistente ? filaExistente.cantidad : 1;
    const precioInicial   = filaExistente ? filaExistente.precio : '';

    Swal.fire({
        title: `${datos.producto_codigo} - ${datos.producto}`,
        html: `
          <div class="text-start">
            <p class="mb-2">Stock agrupado de todos los colores &middot; Disponible: <b>${formatearCantidadVenta(disp)} ${datos.unidad_venta_corto ?? 'paq.'}</b></p>
            <label class="form-label mb-1">Cantidad (paquetes)</label>
            <input type="number" id="swal_pv_cantidad" class="swal2-input" min="1" max="${disp}" step="1" value="${cantidadInicial}">
            <label class="form-label mb-1">Precio unitario (S/)</label>
            <input type="number" id="swal_pv_precio" class="swal2-input" min="0" step="0.01" value="${precioInicial}" placeholder="Ej. 155.00" inputmode="decimal">
          </div>
        `,
        showCancelButton: true,
        confirmButtonText: filaExistente ? 'Guardar' : 'Agregar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const cantidad = parseInt(document.getElementById('swal_pv_cantidad').value, 10);
            const precio = parseFloat(document.getElementById('swal_pv_precio').value);
            if (!cantidad || cantidad <= 0) { Swal.showValidationMessage('Ingresa una cantidad válida.'); return false; }
            if (disp > 0 && cantidad > disp) { Swal.showValidationMessage(`Solo hay ${formatearCantidadVenta(disp)} paquete(s) disponibles.`); return false; }
            if (isNaN(precio) || precio < 0) { Swal.showValidationMessage('Ingresa un precio válido.'); return false; }
            return { cantidad, precio };
        }
    }).then(res => {
        if (!res.isConfirmed) return;
        if (filaExistente) {
            filaExistente.cantidad = res.value.cantidad;
            filaExistente.precio = res.value.precio;
        } else {
            carritoVenta.push({
                uid: ++contadorCarrito,
                producto_id: datos.producto_id,
                producto_codigo: datos.producto_codigo,
                producto: datos.producto,
                color_id: datos.color_id ?? null,
                color: 'Todos los colores',
                disponible: disp,
                unidad: datos.unidad_venta_corto ?? 'paq.',
                cantidad: res.value.cantidad,
                precio: res.value.precio,
            });
        }
        renderCarrito();
    });
}

function editarItemCarrito(uid) {
    const item = carritoVenta.find(i => i.uid === uid);
    if (!item) return;
    abrirAgregarCarrito({
        producto_id: item.producto_id,
        producto_codigo: item.producto_codigo,
        producto: item.producto,
        color_id: item.color_id,
        color: item.color,
        paquetes_disponibles: item.disponible,
        unidad_venta_corto: item.unidad,
    }, item);
}

function eliminarItemCarrito(uid) {
    carritoVenta = carritoVenta.filter(i => i.uid !== uid);
    renderCarrito();
}

function renderCarrito() {
    const vacio = document.getElementById('pv_carrito_vacio');
    const wrap  = document.getElementById('pv_carrito_wrap');
    const tbody = document.getElementById('pv_carrito_tbody');

    if (carritoVenta.length === 0) {
        vacio.style.display = '';
        wrap.style.display = 'none';
        document.getElementById('pv_total').textContent = formatearMoneda(0);
        pagosPV.forEach(pago => { if (pago.automatico) pago.monto = 0; });
        renderPagosPV();
        actualizarResumenEquivalenciaVentaPV();
        return;
    }

    vacio.style.display = 'none';
    wrap.style.display = '';

    let total = 0;
    tbody.innerHTML = carritoVenta.map(item => {
        const subtotal = item.cantidad * item.precio;
        total += subtotal;
        return `
        <tr>
            <td>
                <div>${item.producto_codigo} - ${item.producto}</div>
                <div class="pc-venta-carrito-fila-color">${item.color}</div>
            </td>
            <td>${formatearCantidadVenta(item.cantidad)} ${item.unidad}</td>
            <td>${formatearMoneda(item.precio)}</td>
            <td>${formatearMoneda(subtotal)}</td>
            <td class="text-end">
                <button type="button" class="pc-icon-btn" onclick="editarItemCarrito(${item.uid})" title="Editar">
                    <i class="fa-solid fa-pen"></i>
                </button>
                <button type="button" class="pc-icon-btn" onclick="eliminarItemCarrito(${item.uid})" title="Quitar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </td>
        </tr>`;
    }).join('');

    document.getElementById('pv_total').textContent = formatearMoneda(total);
    const pagoAutomatico = pagosPV.find(pago => pago.automatico);
    if (pagoAutomatico) pagoAutomatico.monto = Math.max(0, Math.round((totalCobroPV() - pagosPV.filter(p => p !== pagoAutomatico).reduce((s, p) => s + (Number(p.monto) || 0), 0)) * 100) / 100);
    renderPagosPV();
    actualizarResumenEquivalenciaVentaPV();
}

// ── Registrar venta ──────────────────────────────────────────────────────────
function escaparHtmlPV(valor) {
    return String(valor ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

async function confirmarVentaRapida(clienteRuc) {
    const esClienteVarios = clienteRuc === CLIENTE_VARIOS_RUC;
    const clienteNombre = esClienteVarios
        ? 'Clientes Varios'
        : (document.getElementById('pv_cliente_texto').value.trim() || 'Cliente seleccionado');
    const total = carritoVenta.reduce((acumulado, item) => acumulado + (item.cantidad * item.precio), 0);
    const datosPago = validarDatosVentaPV();
    const vendedor = vendedoresPV.find(v => Number(v.id) === Number(datosPago.vendedor_usuario_id));
    const resumenPagos = datosPago.pagos.map(p => {
        const cuenta = cuentasPV.find(c => Number(c.id) === Number(p.cuenta_receptora_id));
        const destino = cuenta ? ` · ${escaparHtmlPV(cuenta.nombre)}` : (['yape', 'plin'].includes(p.medio_pago) ? ` · vendedor ${escaparHtmlPV(vendedor?.nombre_completo || '')}` : '');
        return `<li>${escaparHtmlPV(MEDIOS_PAGO_PV[p.medio_pago])}${destino}: <b>${formatearMoneda(p.monto, monedaVentaPV)}</b>${p.referencia ? ` <small>(${escaparHtmlPV(p.referencia)})</small>` : ''}</li>`;
    }).join('');
    const filas = carritoVenta.map(item => `
        <tr>
            <td class="text-start">
                <div class="fw-semibold">${escaparHtmlPV(item.producto_codigo)} - ${escaparHtmlPV(item.producto)}</div>
                <small class="text-muted">${escaparHtmlPV(item.color)}</small>
            </td>
            <td class="text-end">${formatearCantidadVenta(item.cantidad)} ${escaparHtmlPV(item.unidad)}</td>
            <td class="text-end">${formatearMoneda(item.precio)}</td>
            <td class="text-end">${formatearMoneda(item.cantidad * item.precio)}</td>
        </tr>
    `).join('');

    const resultado = await Swal.fire({
        title: '¿Confirmar venta?',
        icon: 'question',
        html: `
            <div class="text-start mb-2"><b>Cliente:</b> ${escaparHtmlPV(clienteNombre)}<br><b>RUC/DNI:</b> ${escaparHtmlPV(clienteRuc)}<br><b>Vendedor:</b> ${escaparHtmlPV(vendedor?.nombre_completo || '')}</div>
            <div class="table-responsive">
                <table class="table table-sm mb-2" style="font-size:.8rem;">
                    <thead><tr><th>Artículo</th><th class="text-end">Cant.</th><th class="text-end">P. Unit.</th><th class="text-end">Subtotal</th></tr></thead>
                    <tbody>${filas}</tbody>
                </table>
            </div>
            <div class="text-start"><b>Pagos:</b><ul class="mb-2">${resumenPagos}</ul></div>
            <div class="text-end fs-5"><b>Total de productos: ${formatearMoneda(total, 'PEN')}</b></div>
            ${monedaVentaPV !== 'PEN' ? `<div class="text-end text-muted small">Cobro: ${formatearMoneda(total / datosPago.tipo_cambio, monedaVentaPV)} · Cambio ${datosPago.tipo_cambio.toFixed(4)} S/ por ${monedaVentaPV} (${datosPago.fuente_tipo_cambio === 'oficial' ? 'oficial' : 'calle'})</div>` : ''}
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-check"></i> Sí, registrar venta',
        cancelButtonText: 'Revisar venta',
        focusCancel: true,
        width: 760,
    });
    return resultado.isConfirmed;
}

async function registrarVentaRapida() {
    const clienteRuc = document.getElementById('pv_cliente_ruc').value || CLIENTE_VARIOS_RUC;
    if (carritoVenta.length === 0) {
        Swal.fire('Atención', 'Agrega al menos un producto al carrito.', 'warning');
        return;
    }

    let datosVenta;
    try {
        datosVenta = validarDatosVentaPV();
    } catch (error) {
        Swal.fire('Revisa la venta', error.message, 'warning');
        return;
    }
    if (!await confirmarVentaRapida(clienteRuc)) return;

    const items = carritoVenta.map(item => ({
        producto_id: item.producto_id,
        color_id: item.color_id,
        cantidad: item.cantidad,
        precio_unitario: item.precio,
    }));

    const btn = document.getElementById('pv_btn_registrar');
    btn.disabled = true;
    let json;
    try {
        json = await llamarVenta('GUARDARVENTA', {
            cliente_ruc: clienteRuc,
            items: JSON.stringify(items),
            vendedor_usuario_id: datosVenta.vendedor_usuario_id,
            pagos: JSON.stringify(datosVenta.pagos),
            moneda: datosVenta.moneda,
            fuente_tipo_cambio: datosVenta.fuente_tipo_cambio,
            tipo_cambio: datosVenta.tipo_cambio,
        });
    } catch (error) {
        btn.disabled = false;
        Swal.fire('Error', error.message || 'No se pudo registrar la venta.', 'error');
        return;
    }
    btn.disabled = false;

    if (json.success) {
        Swal.fire('Listo', `Venta ${json.codigo} registrada correctamente.`, 'success');
        carritoVenta = [];
        monedaVentaPV = 'PEN';
        tasaCambioVentaPV = 1;
        document.getElementById('pv_moneda').value = 'PEN';
        document.getElementById('pv_tc_fuente').value = 'oficial';
        document.getElementById('pv_tc_manual').value = '';
        actualizarTipoCambioVentaPV();
        pagosPV = [{ uid: ++contadorPagoPV, medio_pago: 'efectivo', cuenta_receptora_id: '', monto: 0, referencia: '', automatico: true }];
        renderCarrito();
        seleccionarClienteVariosPV();
        cargarGridProductos(document.getElementById('pv_producto_texto').value.trim());
        imprimirTicketVenta(json.venta_id);
    } else {
        Swal.fire('Error', json.message, 'error');
    }
}

function imprimirTicketVenta(id) {
    window.open(`${TICKET_PDF_URL}?id=${id}`, '_blank');
}
</script>

<?php require __DIR__ . '/footer.php'; ?>
