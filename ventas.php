<?php
$pageTitle    = 'Ventas';
$pageSubtitle = 'Registro de ventas de stock disponible';
$activePage   = 'venta';

include("header.php");
?>

<style>
.pc-venta-item-row{ display:grid; grid-template-columns: 1fr 100px 110px 110px 34px; gap:8px; align-items:start; margin-bottom:8px; }
.pc-venta-item-buscador{ position:relative; }
.pc-venta-item-resultados{ position:absolute; z-index:20; background:#fff; border:1px solid #eee2c8; border-radius:8px; width:100%; max-height:220px; overflow-y:auto; box-shadow:0 6px 18px rgba(0,0,0,.08); display:none; }
.pc-venta-item-resultados div{ padding:8px 10px; cursor:pointer; font-size:.85em; border-bottom:1px dashed #f0ead9; }
.pc-venta-item-resultados div:hover{ background:#fdf6e3; }
.pc-venta-item-resultados .disp{ color:#8a8578; font-size:.8em; }
.pc-venta-item-nombre{ font-size:.82em; color:#555; margin-top:2px; }
#ventaMontoTotal{ font-weight:700; font-size:1.2em; color:#2F6FED; }

.pc-venta-color-dot{ display:inline-block; width:9px; height:9px; border-radius:50%; background:#ccc; margin-right:5px; vertical-align:middle; }
.pc-venta-color-dot.sin-color{ background:repeating-linear-gradient(45deg, #ccc, #ccc 2px, #fff 2px, #fff 4px); border:1px solid #bbb; }
.pc-venta-color-dot.mezcla{ background: conic-gradient(#2F6FED 0deg 90deg, #E0574C 90deg 180deg, #F0B429 180deg 270deg, #2FB170 270deg 360deg); }
.pc-venta-item-resultados .disp b{ color:#2F6FED; }


.pc-venta-item-thumb, .pc-venta-item-thumb-vacio{
    width:28px; height:28px; border-radius:7px; object-fit:cover; flex-shrink:0;
    display:inline-flex; align-items:center; justify-content:center; vertical-align:middle; margin-right:6px;
}
.pc-venta-item-thumb-vacio{ background:#f1efe9; color:#c8c3b3; font-size:.7em; }
.pc-venta-item-resultados div{ display:flex; align-items:center; flex-wrap:wrap; gap:0; }
.pc-venta-item-thumb-mini{ width:20px; height:20px; border-radius:5px; object-fit:cover; vertical-align:middle; margin-right:5px; }
</style>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Ventas</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrearVenta()">
            <i class="fa-solid fa-plus"></i> Nueva venta
        </button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <br>
        <input type="text" id="fventa_texto" class="form-control" style="max-width:280px"
            placeholder="Buscar por código o cliente...">
        <select id="fventa_estado" class="form-select" style="max-width:160px">
            <option value="">Todos</option>
            <option value="completada" selected>Completadas</option>
            <option value="anulada">Anuladas</option>
        </select>
    </div>

    <div class="pc-table-wrap pc-table-responsive-cards">
    <table class="pc-table" id="tablaVentas">
        <thead>
            <tr>
                <th>Código</th>
                <th>Cliente</th>
                <th>Fecha</th>
                <th>Ítems</th>
                <th>Monto</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tbodyVentas">
            <tr><td colspan="7" style="text-align:center;">Cargando...</td></tr>
        </tbody>
    </table>
    </div>
</div>

<!-- Modal Nueva Venta -->
<div class="modal fade" id="modalVenta" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <form id="formVenta">
        <div class="modal-header">
          <h5 class="modal-title">Nueva venta</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">

          <div class="mb-3 pc-venta-item-buscador">
            <label class="form-label">Cliente *</label>
            <input type="text" class="form-control" id="venta_cliente_texto" placeholder="Buscar cliente por nombre o RUC/DNI..." autocomplete="off">
            <div class="pc-venta-item-resultados" id="venta_cliente_resultados"></div>
            <input type="hidden" id="venta_cliente_ruc">
            <div class="form-text" id="venta_cliente_seleccionado"></div>
          </div>

          <div class="mb-2 d-flex justify-content-between align-items-center">
            <label class="form-label mb-0">Productos</label>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="agregarFilaItemVenta()">
                <i class="fa-solid fa-plus"></i> Agregar producto
            </button>
          </div>
          <div id="venta_items_wrap"></div>

          <div class="text-end mt-3">
            Total: <span id="ventaMontoTotal">S/ 0.00</span>
          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Registrar venta</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const CONTROLADOR_VENTA = 'controllers/clssVenta.php';
const TICKET_PDF_URL = 'controllers/ticketPdf.php';
const modalVenta = new bootstrap.Modal(document.getElementById('modalVenta'));

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

document.addEventListener('DOMContentLoaded', () => {
    cargarVentas();

    let debounceTimer = null;
    document.getElementById('fventa_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarVentas, 350);
    });
    document.getElementById('fventa_estado').addEventListener('change', cargarVentas);
});

// ── Listado ──────────────────────────────────────────────────────────────────
async function cargarVentas() {
    const texto  = document.getElementById('fventa_texto').value.trim();
    const estado = document.getElementById('fventa_estado').value;

    const json = await llamarVenta('LISTARVENTAS', { texto, estado });
    const tbody = document.getElementById('tbodyVentas');

    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;">${json.message}</td></tr>`;
        return;
    }

    const ventas = json.ventas || [];
    if (ventas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No hay ventas registradas.</td></tr>';
        return;
    }

    tbody.innerHTML = ventas.map(v => {
        const badgeEstado = v.estado === 'anulada'
            ? '<span class="badge bg-secondary">Anulada</span>'
            : '<span class="badge bg-success">Completada</span>';

        const fecha = new Date(v.fecha_venta).toLocaleString('es-PE');

        return `
        <tr>
            <td data-label="Código"><b>${v.codigo}</b></td>
            <td data-label="Cliente">${v.cliente_nombre}</td>
            <td data-label="Fecha">${fecha}</td>
            <td data-label="Ítems">${v.items_count}</td>
            <td data-label="Monto">${formatearMoneda(v.monto_total)}</td>
            <td data-label="Estado">${badgeEstado}</td>
            <td data-label="Acciones" class="pc-td-acciones">
                <button class="pc-icon-btn" onclick="imprimirTicketVenta(${v.id})" title="Ver ticket (PDF)">
                    <i class="fa-solid fa-file-pdf"></i>
                </button>
                ${v.estado !== 'anulada'
                    ? `<button class="pc-icon-btn" onclick="anularVenta(${v.id})" title="Anular">
                           <i class="fa-solid fa-ban"></i></button>`
                    : ''
                }
            </td>
        </tr>`;
    }).join('');
}

// ── Cliente: buscador ────────────────────────────────────────────────────────
let debounceClienteTimer = null;
document.getElementById('venta_cliente_texto').addEventListener('input', function () {
    document.getElementById('venta_cliente_ruc').value = '';
    document.getElementById('venta_cliente_seleccionado').textContent = '';
    clearTimeout(debounceClienteTimer);
    const valor = this.value.trim();
    const cont = document.getElementById('venta_cliente_resultados');
    if (valor.length < 2) { cont.style.display = 'none'; return; }
    debounceClienteTimer = setTimeout(async () => {
        const json = await llamarVenta('BUSCARCLIENTES', { texto: valor });
        if (!json.success || !json.clientes.length) { cont.style.display = 'none'; return; }
        cont.innerHTML = json.clientes.map(c => `
            <div onclick='seleccionarClienteVenta(${JSON.stringify(c.ruc)}, ${JSON.stringify(c.razon_social)})'>
                <b>${c.razon_social}</b> ${c.nombre_comercial ? '(' + c.nombre_comercial + ')' : ''}
                <div class="disp">${c.ruc}</div>
            </div>
        `).join('');
        cont.style.display = 'block';
    }, 300);
});

function seleccionarClienteVenta(ruc, nombre) {
    document.getElementById('venta_cliente_ruc').value = ruc;
    document.getElementById('venta_cliente_texto').value = nombre;
    document.getElementById('venta_cliente_seleccionado').textContent = `Seleccionado: ${nombre} (${ruc})`;
    document.getElementById('venta_cliente_resultados').style.display = 'none';
}

document.addEventListener('click', (e) => {
    if (!e.target.closest('.pc-venta-item-buscador')) {
        document.querySelectorAll('.pc-venta-item-resultados').forEach(el => el.style.display = 'none');
    }
});

// ── Ítems dinámicos ──────────────────────────────────────────────────────────
// Nota: la cantidad siempre es en PAQUETES (unidad real de venta del
// producto), igual que exige el backend. Por eso el input es entero
// (step=1, min=1), sin decimales.
let contadorFilaItem = 0;

function agregarFilaItemVenta() {
    const wrap = document.getElementById('venta_items_wrap');
    const idFila = 'item_' + (++contadorFilaItem);

    const fila = document.createElement('div');
    fila.className = 'pc-venta-item-row';
    fila.id = idFila;
    fila.innerHTML = `
        <div class="pc-venta-item-buscador">
            <input type="text" class="form-control item-buscar" placeholder="Buscar producto/color..." autocomplete="off">
            <div class="pc-venta-item-resultados"></div>
            <div class="pc-venta-item-nombre text-muted"></div>
            <input type="hidden" class="item-producto-id">
            <input type="hidden" class="item-color-id">
            <input type="hidden" class="item-disponible">
            <input type="hidden" class="item-unidad">
        </div>
        <input type="number" class="form-control item-cantidad" placeholder="Paquetes" min="1" step="1">
        <input type="number" class="form-control item-precio" placeholder="Precio" min="0" step="0.01">
        <div class="form-control-plaintext text-end item-subtotal">S/ 0.00</div>
        <button type="button" class="btn btn-outline-danger btn-sm" onclick="document.getElementById('${idFila}').remove(); recalcularMontoTotal();">
            <i class="fa-solid fa-xmark"></i>
        </button>
    `;
    wrap.appendChild(fila);

    const inputBuscar = fila.querySelector('.item-buscar');
    const contResultados = fila.querySelector('.pc-venta-item-resultados');
    let debounceItemTimer = null;

    inputBuscar.addEventListener('input', () => {
        fila.querySelector('.item-producto-id').value = '';
        fila.querySelector('.item-color-id').value = '';
        fila.querySelector('.item-disponible').value = '';
        clearTimeout(debounceItemTimer);
        const valor = inputBuscar.value.trim();
        if (valor.length < 2) { contResultados.style.display = 'none'; return; }
        debounceItemTimer = setTimeout(async () => {
            const json = await llamarVenta('BUSCARDISPONIBLESVENTA', { texto: valor });
            if (!json.success) {
                contResultados.innerHTML = `<div class="disp text-danger">Error: ${json.message}</div>`;
                contResultados.style.display = 'block';
                return;
            }
            if (!json.disponibles.length) {
                contResultados.innerHTML = '<div class="disp">Sin paquetes disponibles con ese texto.</div>';
                contResultados.style.display = 'block';
                return;
            }
            contResultados.innerHTML = json.disponibles.map(d => {
                const esLegado = d.color_id === null || d.color_id === undefined;
                const esMezcla = d.color_id === -1;
                let dotClase = 'pc-venta-color-dot';
                let dotStyle = '';
                if (esLegado) dotClase += ' sin-color';
                else if (esMezcla) dotClase += ' mezcla';
                else dotStyle = `style="background:${colorHexParaVenta(d.color, d.color_hex)};"`;

                const imagen = resolverImagenVenta(d.producto_imagen);
                const thumbHtml = imagen
                    ? `<img class="pc-venta-item-thumb" src="${imagen}" loading="lazy" alt="" onerror="this.remove()">`
                    : `<span class="pc-venta-item-thumb-vacio"><i class="fa-regular fa-image"></i></span>`;

                return `
                <div onclick='seleccionarItemVenta("${idFila}", ${JSON.stringify(d)})'>
                    ${thumbHtml}
                    <span class="${dotClase}" ${dotStyle}></span>
                    <b>${d.producto_codigo}</b> - ${d.producto} · ${d.color ?? '-'}
                    <div class="disp" style="flex-basis:100%;">
                        Disponible: <b>${formatearCantidadVenta(d.paquetes_disponibles)} ${d.unidad_venta_corto ?? 'paq.'}</b>
                    </div>
                </div>`;
            }).join('');
            contResultados.style.display = 'block';
        }, 300);
    });

    fila.querySelector('.item-cantidad').addEventListener('input', () => actualizarSubtotalFila(fila));
    fila.querySelector('.item-precio').addEventListener('input', () => actualizarSubtotalFila(fila));

    document.addEventListener('click', (e) => {
        if (!fila.contains(e.target)) contResultados.style.display = 'none';
    });
}

function seleccionarItemVenta(idFila, datos) {
    const fila = document.getElementById(idFila);
    fila.querySelector('.item-buscar').value = `${datos.producto_codigo} - ${datos.producto} (${datos.color ?? 'sin color'})`;
    fila.querySelector('.item-producto-id').value = datos.producto_id;
    fila.querySelector('.item-color-id').value = datos.color_id ?? '';
    fila.querySelector('.item-disponible').value = datos.paquetes_disponibles;
    fila.querySelector('.item-unidad').value = datos.unidad_venta_corto ?? '';
    fila.querySelector('.pc-venta-item-resultados').style.display = 'none';

    const imagen = resolverImagenVenta(datos.producto_imagen);
    const thumbHtml = imagen ? `<img class="pc-venta-item-thumb-mini" src="${imagen}" loading="lazy" alt="" onerror="this.remove()">` : '';
    fila.querySelector('.pc-venta-item-nombre').innerHTML =
        `${thumbHtml}Disponible: ${formatearCantidadVenta(datos.paquetes_disponibles)} ${datos.unidad_venta_corto ?? 'paq.'}`;

    const cantidadInput = fila.querySelector('.item-cantidad');
    cantidadInput.max = datos.paquetes_disponibles;
    cantidadInput.placeholder = `Máx. ${formatearCantidadVenta(datos.paquetes_disponibles)} ${datos.unidad_venta_corto ?? 'paq.'}`;
    actualizarSubtotalFila(fila);
}
function actualizarSubtotalFila(fila) {
    const cantidadInput = fila.querySelector('.item-cantidad');
    const cantidad = parseFloat(cantidadInput.value) || 0;
    const precio   = parseFloat(fila.querySelector('.item-precio').value) || 0;
    const disponible = parseFloat(fila.querySelector('.item-disponible').value) || 0;

    const noEsEntero = Math.abs(cantidad - Math.round(cantidad)) > 0.0001;
    const excedeStock = disponible > 0 && cantidad > disponible;
    cantidadInput.classList.toggle('is-invalid', noEsEntero || excedeStock);

    fila.querySelector('.item-subtotal').textContent = formatearMoneda(cantidad * precio);
    recalcularMontoTotal();
}

function recalcularMontoTotal() {
    let total = 0;
    document.querySelectorAll('#venta_items_wrap .pc-venta-item-row').forEach(fila => {
        const cantidad = parseFloat(fila.querySelector('.item-cantidad').value) || 0;
        const precio   = parseFloat(fila.querySelector('.item-precio').value) || 0;
        total += cantidad * precio;
    });
    document.getElementById('ventaMontoTotal').textContent = formatearMoneda(total);
}

// ── Crear venta ──────────────────────────────────────────────────────────────
function abrirModalCrearVenta() {
    document.getElementById('formVenta').reset();
    document.getElementById('venta_cliente_ruc').value = '';
    document.getElementById('venta_cliente_seleccionado').textContent = '';
    document.getElementById('venta_items_wrap').innerHTML = '';
    document.getElementById('ventaMontoTotal').textContent = 'S/ 0.00';
    agregarFilaItemVenta();
    modalVenta.show();
}

document.getElementById('formVenta').addEventListener('submit', async function (e) {
    e.preventDefault();

    const clienteRuc = document.getElementById('venta_cliente_ruc').value;
    if (!clienteRuc) {
        Swal.fire('Atención', 'Selecciona un cliente de la lista antes de guardar.', 'warning');
        return;
    }

    const filas = document.querySelectorAll('#venta_items_wrap .pc-venta-item-row');
    const items = [];
    for (const fila of filas) {
        const productoId = fila.querySelector('.item-producto-id').value;
        const cantidad    = parseFloat(fila.querySelector('.item-cantidad').value);
        const precio      = parseFloat(fila.querySelector('.item-precio').value);
        const disponible  = parseFloat(fila.querySelector('.item-disponible').value) || 0;

        if (!productoId || !cantidad || cantidad <= 0) continue;

        if (Math.abs(cantidad - Math.round(cantidad)) > 0.0001) {
            Swal.fire('Atención', 'La cantidad debe ser en paquetes completos (sin decimales).', 'warning');
            return;
        }
        if (disponible > 0 && cantidad > disponible) {
            Swal.fire('Atención', `Solo hay ${formatearCantidadVenta(disponible)} paquete(s) disponibles para ese producto.`, 'warning');
            return;
        }

        items.push({
            producto_id: productoId,
            color_id: fila.querySelector('.item-color-id').value || null,
            cantidad: Math.round(cantidad),
            precio_unitario: precio || 0,
        });
    }

    if (items.length === 0) {
        Swal.fire('Atención', 'Agrega al menos un producto válido con cantidad mayor a 0.', 'warning');
        return;
    }

    const json = await llamarVenta('GUARDARVENTA', {
        cliente_ruc: clienteRuc,
        items: JSON.stringify(items),
    });

    if (json.success) {
        modalVenta.hide();
        Swal.fire('Listo', `Venta ${json.codigo} registrada correctamente.`, 'success');
        cargarVentas();
        imprimirTicketVenta(json.venta_id);
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});

// ── Anular ───────────────────────────────────────────────────────────────────
function anularVenta(id) {
    Swal.fire({
        title: '¿Anular esta venta?',
        text: 'Se repondrá el stock consumido en empaquetado.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, anular',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarVenta('ANULARVENTA', { id });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarVentas();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
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

// ── Ticket (PDF, generado por el servidor) ──────────────────────────────────
function imprimirTicketVenta(id) {
    window.open(`${TICKET_PDF_URL}?id=${id}`, '_blank');
}
</script>

<?php require __DIR__ . '/footer.php'; ?>