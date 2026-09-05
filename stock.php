<?php
$pageTitle    = 'Stock de productos';
$pageSubtitle = 'Stock empaquetado disponible, por producto y color';
$activePage   = 'disponibilidad_venta';

include("header.php");
?>

<style>
.pc-dv-filtros{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:14px; }
.pc-dv-filtros select, .pc-dv-filtros input[type="text"]{ max-width:220px; flex:1; min-width:160px; }

/* ── Tabs por producto (con miniatura) ── */
.pc-dv-tabs{ display:flex; align-items:center; gap:6px; overflow-x:auto; border-bottom:1px solid #eee7db; margin-bottom:16px; padding-bottom:2px; scrollbar-width:thin; -webkit-overflow-scrolling:touch; }
.pc-dv-tab{
    display:flex; align-items:center; gap:8px; padding:8px 12px 10px; white-space:nowrap;
    border:none; background:transparent; font-size:.86em; font-weight:600; color:#8a8578;
    border-bottom:2px solid transparent; cursor:pointer; transition:.12s ease; flex-shrink:0;
}
.pc-dv-tab:hover{ color:#3a3730; }
.pc-dv-tab.activo{ color:#1f2937; border-bottom-color:#2F6FED; }
.pc-dv-tab .thumb{ width:24px; height:24px; border-radius:7px; object-fit:cover; flex-shrink:0; background:#f1efe9; }
.pc-dv-tab .thumb-empty{ width:24px; height:24px; border-radius:7px; background:#f1efe9; display:flex; align-items:center; justify-content:center; color:#c8c3b3; font-size:.65em; flex-shrink:0; }
.pc-dv-tab .cnt{ background:#f1efe9; color:#8a8578; border-radius:999px; font-size:.76em; font-weight:700; padding:1px 8px; min-width:20px; text-align:center; }
.pc-dv-tab.activo .cnt{ background:#1f2937; color:#fff; }
.pc-dv-tab .warn{ color:#c9482a; }

/* ── Header del producto activo (foto + stats) ── */
.pc-dv-header{
    display:flex; gap:18px; align-items:center;
    background:#fff; border:1px solid #eee2c8; border-radius:16px; padding:16px; margin-bottom:18px;
}
.pc-dv-header .foto{
    width:clamp(76px, 12vw, 112px); height:clamp(76px, 12vw, 112px);
    border-radius:14px; overflow:hidden; flex-shrink:0; background:#f4f2ea;
    display:flex; align-items:center; justify-content:center;
}
.pc-dv-header .foto img{ width:100%; height:100%; object-fit:cover; display:block; }
.pc-dv-header .foto .sin-foto{ color:#c8c3b3; font-size:1.8em; display:flex; }
.pc-dv-header .info{ flex:1; min-width:0; }
.pc-dv-header .nombre{ font-size:1.1em; font-weight:800; color:#3a3730; margin-bottom:12px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.pc-dv-header .stats{ display:flex; gap:clamp(14px,4vw,28px); flex-wrap:wrap; }
.pc-dv-header .stat .valor{ font-size:1.4em; font-weight:800; color:#2F6FED; line-height:1.1; }
.pc-dv-header .stat .valor small{ font-size:.5em; font-weight:700; color:#5c85e8; }
.pc-dv-header .stat.sin-config .valor{ font-size:.95em; font-weight:700; color:#9a9585; font-style:italic; }
.pc-dv-header .stat .label{ font-size:.72em; color:#9a9585; text-transform:uppercase; letter-spacing:.03em; font-weight:700; margin-top:3px; }

@media (max-width:640px){
    .pc-dv-header{ flex-direction:column; text-align:center; }
    .pc-dv-header .stats{ justify-content:center; }
    .pc-dv-filtros select, .pc-dv-filtros input[type="text"]{ max-width:none; width:100%; }
}

/* ── Grid de cards por color/mezcla/legado ── */
.pc-dv-cards-grid{ display:grid; grid-template-columns:repeat(auto-fill, minmax(190px,1fr)); gap:14px; }
@media (max-width:480px){ .pc-dv-cards-grid{ grid-template-columns:repeat(auto-fill, minmax(148px,1fr)); gap:10px; } }

.pc-dv-card{ border:1px solid #eee2c8; border-radius:14px; padding:14px 16px; background:#fff; position:relative; overflow:hidden; }
.pc-dv-card .franja{ position:absolute; top:0; left:0; right:0; height:6px; background:#ccc; }
.pc-dv-card .franja.sin-color{ background:repeating-linear-gradient(45deg, #ccc, #ccc 3px, #fff 3px, #fff 6px); }
.pc-dv-card .franja.mezcla{ background: conic-gradient(#2F6FED 0deg 90deg, #E0574C 90deg 180deg, #F0B429 180deg 270deg, #2FB170 270deg 360deg); }
.pc-dv-card .color-nombre{ display:flex; align-items:center; gap:7px; font-weight:700; font-size:.94em; color:#3a3730; margin:10px 0 10px; }
.pc-dv-card .color-dot{ display:inline-block; width:11px; height:11px; border-radius:50%; background:#ccc; flex-shrink:0; border:1px solid rgba(0,0,0,.06); }
.pc-dv-card .color-dot.sin-color{ background:repeating-linear-gradient(45deg, #ccc, #ccc 2px, #fff 2px, #fff 4px); border:1px solid #bbb; }
.pc-dv-card .color-dot.mezcla{ background: conic-gradient(#2F6FED 0deg 90deg, #E0574C 90deg 180deg, #F0B429 180deg 270deg, #2FB170 270deg 360deg); }
.pc-dv-card .paquetes{ font-size:1.55em; font-weight:800; color:#2F6FED; line-height:1.1; }
.pc-dv-card .paquetes small{ font-size:.5em; font-weight:700; color:#5c85e8; }
.pc-dv-card .sin-config{ color:#9a9585; font-style:italic; font-size:.88em; }
.pc-dv-card .bolsas{ color:#9a9585; font-size:.82em; margin-top:6px; }
.pc-dv-card .registros{ position:absolute; top:12px; right:14px; background:#f1efe9; color:#8a8578; border-radius:999px; font-size:.72em; font-weight:700; padding:2px 9px; }
.pc-dv-card .warn-mix{ color:#c9822a; margin-left:4px; cursor:help; }
.pc-dv-legado .color-nombre{ font-style:italic; color:#9a9585; }

.pc-dv-empty{ text-align:center; color:#9a9585; padding:40px 12px; grid-column:1/-1; }
</style>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Disponibilidad de Venta</h2>
    </div>

    <div class="pc-dv-filtros">
        <input type="text" id="dv_texto" class="form-control" placeholder="Buscar por producto...">
        <select class="form-select" id="dv_color_id">
            <option value="">Todos los colores</option>
        </select>
        <div class="form-check d-flex align-items-center gap-2">
            <input class="form-check-input" type="checkbox" id="dv_incluir_vendidos">
            <label class="form-check-label" for="dv_incluir_vendidos" style="font-size:.85em;">Incluir ya vendidos</label>
        </div>
    </div>

    <div class="pc-dv-tabs" id="dvTabs">
        <span class="text-muted" style="padding:12px 0;">Cargando productos...</span>
    </div>

    <div class="pc-dv-header" id="dvHeader" style="display:none;"></div>

    <div class="pc-dv-cards-grid" id="dvCardsGrid">
        <div class="pc-dv-empty">Cargando...</div>
    </div>
</div>

<script>
const CONTROLADOR_DV = 'controllers/clssDisponibilidadVenta.php';

let dvGrupos = [];
let dvTabActivo = null;

const PALETA_COLOR_FALLBACK_DV = {
    'VERDE': '#639922', 'AZUL': '#378ADD', 'CELESTE': '#5DB8E8', 'ROJO': '#E24B4A',
    'AMARILLO': '#EF9F27', 'NARANJA': '#D85A30', 'MORADO': '#7F77DD', 'VIOLETA': '#7F77DD',
    'ROSADO': '#D4537E', 'ROSA': '#D4537E', 'NEGRO': '#2C2C2A', 'BLANCO': '#D3D1C7',
    'GRIS': '#888780', 'MARRON': '#712B13', 'CAFE': '#712B13', 'TURQUESA': '#1D9E75',
    'PLOMO': '#5F5E5A', 'BEIGE': '#B4B2A9',
};
function colorHexParaDV(colorNombre, colorHexBD) {
    if (colorHexBD) return colorHexBD.startsWith('#') ? colorHexBD : `#${colorHexBD}`;
    const clave = String(colorNombre ?? '').trim().toUpperCase();
    if (PALETA_COLOR_FALLBACK_DV[clave]) return PALETA_COLOR_FALLBACK_DV[clave];
    let hash = 0;
    for (let i = 0; i < clave.length; i++) hash = clave.charCodeAt(i) + ((hash << 5) - hash);
    const hue = Math.abs(hash) % 360;
    return `hsl(${hue}, 45%, 55%)`;
}

// img_ruta ya guarda URL completa de Cloudinary; se deja por si algún
// producto viejo aún tuviera ruta relativa (antes de migrar a Cloudinary).
function resolverImagenDV(ruta) {
    if (!ruta) return null;
    return ruta.startsWith('http') ? ruta : ruta;
}

async function llamarDV(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_DV, {
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

function formatearNumeroDV(n, decimales = 2) {
    if (n === null || n === undefined || n === '') return '-';
    return Number(n).toLocaleString('es-PE', { maximumFractionDigits: decimales });
}

async function cargarColoresDV() {
    const json = await llamarDV('BUSCARCOLORESDV');
    const sel = document.getElementById('dv_color_id');
    if (json.success) {
        sel.innerHTML = '<option value="">Todos los colores</option>' +
            json.colores.map(c => `<option value="${c.id}">${c.nombre}</option>`).join('');
    }
}

function agruparPorProductoDV(filas) {
    const grupos = [];
    let actual = null;
    filas.forEach(f => {
        if (!actual || actual.producto_id !== f.producto_id) {
            actual = {
                producto_id: f.producto_id,
                producto_codigo: f.producto_codigo,
                producto: f.producto,
                producto_imagen: f.producto_imagen,
                config_venta_inconsistente: f.config_venta_inconsistente === true || f.config_venta_inconsistente === 't',
                filas: [],
            };
            grupos.push(actual);
        }
        actual.filas.push(f);
    });
    return grupos;
}

async function cargarDisponibilidadVenta() {
    const grid = document.getElementById('dvCardsGrid');
    grid.innerHTML = '<div class="pc-dv-empty"><i class="fa-solid fa-spinner fa-spin"></i> Cargando...</div>';

    const params = {
        texto: document.getElementById('dv_texto').value.trim(),
        color_id: document.getElementById('dv_color_id').value,
        incluir_vendidos: document.getElementById('dv_incluir_vendidos').checked ? '1' : '0',
    };
    const json = await llamarDV('LISTARDISPONIBILIDADVENTA', params);

    if (!json.success) {
        document.getElementById('dvTabs').innerHTML = '';
        document.getElementById('dvHeader').style.display = 'none';
        grid.innerHTML = `<div class="pc-dv-empty text-danger">${json.message}</div>`;
        return;
    }

    dvGrupos = agruparPorProductoDV(json.disponibilidad || []);

    if (dvTabActivo !== null && !dvGrupos.some(g => g.producto_id === dvTabActivo)) {
        dvTabActivo = null;
    }
    if (dvTabActivo === null && dvGrupos.length > 0) {
        dvTabActivo = dvGrupos[0].producto_id;
    }

    renderTabsDV();
    renderPanelProductoDV();
}

function renderTabsDV() {
    const cont = document.getElementById('dvTabs');

    if (dvGrupos.length === 0) {
        cont.innerHTML = '<span class="text-muted" style="padding:12px 0;">No hay productos con stock disponible.</span>';
        return;
    }

    cont.innerHTML = dvGrupos.map(g => {
        const totalPaquetes = g.filas.reduce((acc, f) => acc + Number(f.paquetes_disponibles || 0), 0);
        const warn = g.config_venta_inconsistente
            ? `<i class="fa-solid fa-triangle-exclamation warn" title="Configuración de venta inconsistente para este producto."></i>`
            : '';
        const imagen = resolverImagenDV(g.producto_imagen);
        const thumb = imagen
            ? `<img class="thumb" src="${imagen}" loading="lazy" alt="" onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'thumb-empty',innerHTML:'<i class=\\'fa-regular fa-image\\'></i>'}))">`
            : `<div class="thumb-empty"><i class="fa-regular fa-image"></i></div>`;
        return `
        <button type="button" class="pc-dv-tab ${dvTabActivo === g.producto_id ? 'activo' : ''}"
                onclick="seleccionarTabDV(${g.producto_id})" title="${(g.producto_codigo ?? '') + ' - ' + (g.producto ?? '')}">
            ${thumb}
            <span>${g.producto_codigo ?? ''} - ${g.producto ?? 'Sin nombre'}</span>
            ${warn}
            <span class="cnt">${formatearNumeroDV(totalPaquetes)}</span>
        </button>`;
    }).join('');
}

function seleccionarTabDV(productoId) {
    dvTabActivo = productoId;
    renderTabsDV();
    renderPanelProductoDV();
}

function celdaPaquetesTextoDV(f) {
    if (f.paquetes_venta_sin_configurar) {
        return `<span class="sin-config" title="Este producto no tiene configurada su unidad de venta (cant_equivale / unidad_equivale_id). Configúrala en Productos.">Sin config. de venta</span>`;
    }
    return `<span class="paquetes">${formatearNumeroDV(f.paquetes_disponibles)} <small>${f.unidad_venta_corto ?? ''}</small></span>`;
}

function cardHtmlDV(f) {
    const esLegado = f.color_id === null || f.color_id === undefined;
    const esMezcla = f.color_id === -1;

    let franjaClase = 'franja';
    let dotStyle = '';
    const colorTexto = f.color ?? '-';

    if (esLegado) {
        franjaClase += ' sin-color';
    } else if (esMezcla) {
        franjaClase += ' mezcla';
    } else {
        const hex = colorHexParaDV(f.color, f.color_hex);
        dotStyle = `style="background:${hex};"`;
    }
    const dotClase = 'color-dot' + (esLegado ? ' sin-color' : esMezcla ? ' mezcla' : '');

    const warnBolsas = f.unidades_bolsa_distintas
        ? `<span class="warn-mix" title="Este grupo mezcla registros con distinta unidad de empaquetado. La suma de bolsas es aproximada.">⚠️</span>`
        : '';

    return `
    <div class="pc-dv-card ${esLegado ? 'pc-dv-legado' : ''}">
        <div class="${franjaClase}"></div>
        <span class="registros" title="Registros de empaquetado en este grupo">${f.registros_count ?? 0} reg.</span>
        <div class="color-nombre"><span class="${dotClase}" ${dotStyle}></span>${colorTexto}</div>
        ${celdaPaquetesTextoDV(f)}
        <div class="bolsas">${formatearNumeroDV(f.bolsas_disponibles)} ${f.unidad_bolsa_corto ?? ''} empaquetadas ${warnBolsas}</div>
    </div>`;
}

function renderPanelProductoDV() {
    const grid = document.getElementById('dvCardsGrid');
    const header = document.getElementById('dvHeader');

    const grupo = dvGrupos.find(g => g.producto_id === dvTabActivo);
    if (!grupo) {
        header.style.display = 'none';
        grid.innerHTML = '<div class="pc-dv-empty">No hay stock disponible con estos filtros.</div>';
        return;
    }

    const tieneSinConfig = grupo.filas.some(f => f.paquetes_venta_sin_configurar);
    const totalPaquetes  = grupo.filas.reduce((acc, f) => acc + Number(f.paquetes_disponibles || 0), 0);
    const totalBolsas    = grupo.filas.reduce((acc, f) => acc + Number(f.bolsas_disponibles || 0), 0);
    const unidadesVenta  = [...new Set(grupo.filas.map(f => f.unidad_venta_corto).filter(Boolean))];
    const unidadesBolsa  = [...new Set(grupo.filas.map(f => f.unidad_bolsa_corto).filter(Boolean))];
    const sufijoPaquete  = unidadesVenta.length === 1 ? ` <small>${unidadesVenta[0]}</small>` : '';
    const sufijoBolsa    = unidadesBolsa.length === 1 ? ` ${unidadesBolsa[0]}` : '';

    const warn = grupo.config_venta_inconsistente
        ? `<i class="fa-solid fa-triangle-exclamation" style="color:#c9482a;" title="La unidad de venta configurada en 'Productos' no coincide con la de 'Configuración de empaquetado'."></i>`
        : '';

    const imagen = resolverImagenDV(grupo.producto_imagen);
    const fotoHtml = imagen
        ? `<img src="${imagen}" loading="lazy" alt="" onerror="this.parentElement.innerHTML='<div class=&quot;sin-foto&quot;><i class=&quot;fa-regular fa-image&quot;></i></div>'">`
        : `<div class="sin-foto"><i class="fa-regular fa-image"></i></div>`;

    const statPaquetesHtml = tieneSinConfig
        ? `<div class="stat sin-config"><div class="valor">Sin config.</div><div class="label">Paquetes disponibles</div></div>`
        : `<div class="stat"><div class="valor">${formatearNumeroDV(totalPaquetes)}${sufijoPaquete}</div><div class="label">Paquetes disponibles</div></div>`;

    header.style.display = 'flex';
    header.innerHTML = `
        <div class="foto">${fotoHtml}</div>
        <div class="info">
            <div class="nombre">${grupo.producto_codigo ?? ''} - ${grupo.producto ?? ''} ${warn}</div>
            <div class="stats">
                ${statPaquetesHtml}
                <div class="stat"><div class="valor">${formatearNumeroDV(totalBolsas)}${sufijoBolsa}</div><div class="label">Bolsas empaquetadas</div></div>
                <div class="stat"><div class="valor">${grupo.filas.length}</div><div class="label">Grupos de color</div></div>
            </div>
        </div>
    `;

    if (grupo.filas.length === 0) {
        grid.innerHTML = '<div class="pc-dv-empty">No hay stock disponible con estos filtros.</div>';
        return;
    }
    grid.innerHTML = grupo.filas.map(cardHtmlDV).join('');
}

document.addEventListener('DOMContentLoaded', () => {
    cargarColoresDV();
    cargarDisponibilidadVenta();

    let debounceTimer = null;
    document.getElementById('dv_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarDisponibilidadVenta, 350);
    });
    document.getElementById('dv_color_id').addEventListener('change', cargarDisponibilidadVenta);
    document.getElementById('dv_incluir_vendidos').addEventListener('change', cargarDisponibilidadVenta);
});
</script>

<?php require __DIR__ . '/footer.php'; ?>