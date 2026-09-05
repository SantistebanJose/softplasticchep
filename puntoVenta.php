<?php
$pageTitle    = 'Venta Rápida';
$pageSubtitle = 'Registra una venta de stock disponible';
$activePage   = 'punto_venta';

include("header.php");
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

.pc-venta-carrito-sticky{ position:sticky; top:16px; }
#pv_total{ font-weight:700; font-size:1.2em; color:#2F6FED; }
.pc-venta-carrito-fila-color{ font-size:.75em; color:#8a8578; }
</style>

<div class="pc-card mb-3">
    <div class="pc-card-header">
        <h2>Venta Rápida</h2>
    </div>
    <div class="p-3 pc-venta-cliente-buscador" style="max-width:460px;">
        <label class="form-label">Cliente *</label>
        <input type="text" class="form-control" id="pv_cliente_texto" placeholder="Buscar cliente por nombre o RUC/DNI..." autocomplete="off">
        <div class="pc-venta-item-resultados" id="pv_cliente_resultados"></div>
        <input type="hidden" id="pv_cliente_ruc">
        <div class="form-text" id="pv_cliente_seleccionado"></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="pc-card">
            <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h2>Artículos</h2>
            </div>
            <div class="p-3">
                <input type="text" id="pv_producto_texto" class="form-control mb-3" placeholder="Buscar producto o color..." autocomplete="off">
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
const TICKET_PDF_URL = 'controllers/ticketPdf.php';

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

function formatearMoneda(n) {
    return 'S/ ' + Number(n || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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

    let debounceProducto = null;
    document.getElementById('pv_producto_texto').addEventListener('input', function () {
        clearTimeout(debounceProducto);
        const valor = this.value.trim();
        debounceProducto = setTimeout(() => cargarGridProductos(valor), 300);
    });
});

// ── Cliente: buscador ────────────────────────────────────────────────────────
let debounceClienteTimer = null;
document.getElementById('pv_cliente_texto').addEventListener('input', function () {
    document.getElementById('pv_cliente_ruc').value = '';
    document.getElementById('pv_cliente_seleccionado').textContent = '';
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
        const esLegado = d.color_id === null || d.color_id === undefined;
        const esMezcla = d.color_id === -1;
        let dotClase = 'pc-venta-color-dot';
        let dotStyle = '';
        if (esLegado) dotClase += ' sin-color';
        else if (esMezcla) dotClase += ' mezcla';
        else dotStyle = `style="background:${colorHexParaVenta(d.color, d.color_hex)};"`;

        const imagen = resolverImagenVenta(d.producto_imagen);
        const imgHtml = imagen
            ? `<img class="pc-venta-card-img" src="${imagen}" loading="lazy" alt="" onerror="this.outerHTML='<div class=&quot;pc-venta-card-img-vacio&quot;><i class=&quot;fa-regular fa-image&quot;></i></div>'">`
            : `<div class="pc-venta-card-img-vacio"><i class="fa-regular fa-image"></i></div>`;

        return `
        <div class="pc-venta-card">
            ${imgHtml}
            <div class="pc-venta-card-codigo">${d.producto_codigo}</div>
            <div class="pc-venta-card-nombre">${d.producto}</div>
            <div class="pc-venta-card-color"><span class="${dotClase}" ${dotStyle}></span>${d.color ?? 'Sin color'}</div>
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

function abrirAgregarCarrito(datos, filaExistente = null) {
    const disp = Number(datos.paquetes_disponibles) || 0;
    const cantidadInicial = filaExistente ? filaExistente.cantidad : 1;
    const precioInicial   = filaExistente ? filaExistente.precio : 0;

    Swal.fire({
        title: `${datos.producto_codigo} - ${datos.producto}`,
        html: `
          <div class="text-start">
            <p class="mb-2">Color: <b>${datos.color ?? 'Sin color'}</b> &middot; Disponible: <b>${formatearCantidadVenta(disp)} ${datos.unidad_venta_corto ?? 'paq.'}</b></p>
            <label class="form-label mb-1">Cantidad (paquetes)</label>
            <input type="number" id="swal_pv_cantidad" class="swal2-input" min="1" max="${disp}" step="1" value="${cantidadInicial}">
            <label class="form-label mb-1">Precio unitario (S/)</label>
            <input type="number" id="swal_pv_precio" class="swal2-input" min="0" step="0.01" value="${precioInicial}">
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
                color: datos.color ?? 'Sin color',
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
}

// ── Registrar venta ──────────────────────────────────────────────────────────
async function registrarVentaRapida() {
    const clienteRuc = document.getElementById('pv_cliente_ruc').value;
    if (!clienteRuc) {
        Swal.fire('Atención', 'Selecciona un cliente de la lista antes de guardar.', 'warning');
        return;
    }
    if (carritoVenta.length === 0) {
        Swal.fire('Atención', 'Agrega al menos un producto al carrito.', 'warning');
        return;
    }

    const items = carritoVenta.map(item => ({
        producto_id: item.producto_id,
        color_id: item.color_id,
        cantidad: item.cantidad,
        precio_unitario: item.precio,
    }));

    const btn = document.getElementById('pv_btn_registrar');
    btn.disabled = true;

    const json = await llamarVenta('GUARDARVENTA', {
        cliente_ruc: clienteRuc,
        items: JSON.stringify(items),
    });

    btn.disabled = false;

    if (json.success) {
        Swal.fire('Listo', `Venta ${json.codigo} registrada correctamente.`, 'success');
        carritoVenta = [];
        renderCarrito();
        document.getElementById('pv_cliente_ruc').value = '';
        document.getElementById('pv_cliente_texto').value = '';
        document.getElementById('pv_cliente_seleccionado').textContent = '';
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