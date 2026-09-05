<?php
$pageTitle    = 'Moldes';
$pageSubtitle = 'Moldes de producción';
$activePage   = 'moldes';

include("header.php");
?>

<style>
    /* ── Miniatura de foto en el listado (mismo patrón que producto.php) ── */
    .pc-thumb {
        width: 42px;
        height: 42px;
        border-radius: 8px;
        object-fit: cover;
        border: 1px solid #eceef1;
        background: #f8f9fb;
        cursor: zoom-in;
    }
    .pc-thumb-placeholder {
        width: 42px;
        height: 42px;
        border-radius: 8px;
        background: #f1f2f4;
        color: #b0b4bb;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #eceef1;
    }

    /* ── Foto del molde dentro del modal Crear/Editar ── */
    .pc-img-producto-wrap {
        width: 130px;
        height: 130px;
        margin: 0 auto;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #eceef1;
        background: #f8f9fb;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .pc-img-producto-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .pc-img-placeholder {
        color: #b0b4bb;
    }
</style>

<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2>Moldes</h2>
        <button class="pc-btn pc-btn-primary" onclick="abrirModalCrearMolde()">
            <i class="fa-solid fa-plus"></i> Nuevo molde
        </button>
    </div>

    <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
        <br>
        <input type="text" id="fm_texto" class="form-control" style="max-width:260px"
               placeholder="Buscar por nombre, forma o producto...">
        <select id="fm_estado" class="form-select" style="max-width:160px">
            <option value="">Todos</option>
            <option value="activa" selected>Activos</option>
            <option value="inactiva">Inactivos</option>
        </select>
    </div>

    <div class="pc-table-wrap pc-table-responsive-cards">
    <table class="pc-table" id="tablaMoldes">
        <thead>
            <tr>
                <th>Foto</th>
                <th>Nombre</th>
                <th>Forma</th>
                <th>Productos</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tbodyMoldes">
            <tr><td colspan="6" style="text-align:center;">Cargando...</td></tr>
        </tbody>
    </table>
    </div>

</div>

<!-- Modal Crear/Editar -->
<div class="modal fade" id="modalMolde" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formMolde">
        <div class="modal-header">
          <h5 class="modal-title" id="modalMoldeTitulo">Nuevo molde</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="molde_id">

          <!-- ── Foto del molde ─────────────────────────────────────────── -->
          <div class="mb-3 text-center">
            <div class="pc-img-producto-wrap">
                <img id="molde_imagen_preview" src="" alt="Foto del molde" style="display:none;">
                <div id="molde_imagen_placeholder" class="pc-img-placeholder">
                    <i class="fa-solid fa-image fa-2x"></i>
                </div>
            </div>
            <div class="mt-2 d-flex gap-2 justify-content-center flex-wrap">
                <label class="btn btn-sm btn-outline-secondary mb-0" for="molde_imagen_camara">
                    <i class="fa-solid fa-camera"></i> Tomar foto
                </label>
                <label class="btn btn-sm btn-outline-secondary mb-0" for="molde_imagen_galeria">
                    <i class="fa-solid fa-image"></i> Subir de galería
                </label>
                <button type="button" class="btn btn-sm btn-outline-danger" id="btn_quitar_imagen_molde" style="display:none;">
                    <i class="fa-solid fa-xmark"></i> Quitar foto
                </button>
            </div>
            <!-- Dos triggers separados: uno fuerza la cámara (capture), el otro
                 no lleva capture y por eso abre el selector de archivos/galería.
                 Ambos vuelcan el archivo elegido al input real (molde_imagen,
                 el que sí se manda en el form) vía DataTransfer. -->
            <input type="file" class="d-none" id="molde_imagen_camara" accept="image/*" capture="environment">
            <input type="file" class="d-none" id="molde_imagen_galeria" accept="image/*">
            <input type="file" class="d-none" id="molde_imagen" name="imagen" accept="image/*">
            <input type="hidden" name="eliminar_imagen" id="molde_eliminar_imagen" value="0">
            <small class="text-muted d-block mt-1">JPG, PNG o WEBP, máx. 5MB.</small>
          </div>

          <div class="mb-2">
            <label class="form-label">Nombre *</label>
            <input type="text" class="form-control" name="nombre" id="molde_nombre" required>
          </div>

          <div class="mb-2">
            <label class="form-label">Forma *</label>
            <input type="text" class="form-control" name="forma" id="molde_forma"
                   placeholder="Ej: cuchara, cadena, gancho..." required>
          </div>

          <div class="mb-2">
            <label class="form-label">Productos *</label>
            <div id="molde_producto_checks" class="pc-checklist"></div>
            <div class="form-text">Toca todos los productos que usan este molde.</div>
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

<!-- Modal Ver Foto (zoom de la miniatura del listado) -->
<div class="modal fade" id="modalVerFotoMolde" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Foto del molde</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <img id="fotoMoldeAmpliada" src="" alt="" style="max-width:100%; border-radius:10px;">
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const CONTROLADOR_MOLDES    = 'controllers/clssMoldes.php';    // clssMoldes.php vive en su propia carpeta
const CONTROLADOR_PRODUCTOS = 'controllers/clssProductos.php'; // para llenar el <select> de productos
const modalMolde        = new bootstrap.Modal(document.getElementById('modalMolde'));
const modalVerFotoMolde = new bootstrap.Modal(document.getElementById('modalVerFotoMolde'));

document.addEventListener('DOMContentLoaded', () => {
    Promise.all([
        cargarProductosSelect(),
        cargarMoldes()
    ]).catch(err => {
        console.error('Error cargando datos iniciales:', err);
        document.getElementById('tbodyMoldes').innerHTML =
            `<tr><td colspan="6" style="text-align:center;color:red;">Error de conexión con el servidor. Revisa la consola (F12).</td></tr>`;
    });

    // ── Búsqueda automática ──────────────────────────────────────────────────
    let debounceTimer = null;
    document.getElementById('fm_texto').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarMoldes, 350);
    });

    document.getElementById('fm_estado').addEventListener('change', () => {
        cargarMoldes();
    });
});

// ── Foto del molde: cámara / galería, vista previa y "quitar" ────────────────
const inputImagenMolde         = document.getElementById('molde_imagen');         // el que se manda en el form (name="imagen")
const inputImagenMoldeCamara   = document.getElementById('molde_imagen_camara');  // capture="environment" -> fuerza cámara
const inputImagenMoldeGaleria  = document.getElementById('molde_imagen_galeria'); // sin capture -> selector de archivos/galería
const previewImagenMolde       = document.getElementById('molde_imagen_preview');
const placeholderImagenMolde   = document.getElementById('molde_imagen_placeholder');
const btnQuitarImagenMolde     = document.getElementById('btn_quitar_imagen_molde');
const inputEliminarImagenMolde = document.getElementById('molde_eliminar_imagen');

function mostrarPreviewImagenMolde(src) {
    if (src) {
        previewImagenMolde.src = src;
        previewImagenMolde.style.display = '';
        placeholderImagenMolde.style.display = 'none';
        btnQuitarImagenMolde.style.display = '';
    } else {
        previewImagenMolde.src = '';
        previewImagenMolde.style.display = 'none';
        placeholderImagenMolde.style.display = '';
        btnQuitarImagenMolde.style.display = 'none';
    }
}

// Redimensiona/comprime la foto en el navegador antes de subirla. Las fotos
// que salen directo de la cámara del celular pueden pesar 4-10MB sin
// comprimir y reventar el límite de upload_max_filesize del servidor; esto
// evita ese error sin depender de tocar el php.ini.
function comprimirImagenMolde(archivo, maxDimension = 1600, calidad = 0.82) {
    return new Promise((resolve) => {
        // Si por lo que sea falla la compresión (formato raro, etc.), se
        // sube el archivo original tal cual, para no bloquear al usuario.
        const imagen = new Image();
        const url = URL.createObjectURL(archivo);

        imagen.onload = () => {
            URL.revokeObjectURL(url);

            let { width, height } = imagen;
            if (width > maxDimension || height > maxDimension) {
                const escala = maxDimension / Math.max(width, height);
                width  = Math.round(width * escala);
                height = Math.round(height * escala);
            }

            const canvas = document.createElement('canvas');
            canvas.width  = width;
            canvas.height = height;
            canvas.getContext('2d').drawImage(imagen, 0, 0, width, height);

            canvas.toBlob((blob) => {
                if (!blob) { resolve(archivo); return; }
                const nombre = (archivo.name || 'foto').replace(/\.[^.]+$/, '') + '.jpg';
                resolve(new File([blob], nombre, { type: 'image/jpeg' }));
            }, 'image/jpeg', calidad);
        };

        imagen.onerror = () => {
            URL.revokeObjectURL(url);
            resolve(archivo); // no se pudo leer como imagen: se sube tal cual y que la valide el backend
        };

        imagen.src = url;
    });
}

// Toma el archivo elegido en cualquiera de los dos triggers (cámara o
// galería), lo comprime y lo copia al input real que viaja en el FormData
// del form.
async function usarArchivoImagenMolde(archivoOriginal) {
    if (!archivoOriginal) return;

    const archivo = await comprimirImagenMolde(archivoOriginal);

    const dt = new DataTransfer();
    dt.items.add(archivo);
    inputImagenMolde.files = dt.files;

    inputEliminarImagenMolde.value = '0'; // si eligió una foto nueva, ya no aplica el "quitar" previo
    const lector = new FileReader();
    lector.onload = e => mostrarPreviewImagenMolde(e.target.result);
    lector.readAsDataURL(archivo);
}

inputImagenMoldeCamara.addEventListener('change', () => {
    usarArchivoImagenMolde(inputImagenMoldeCamara.files[0]);
    inputImagenMoldeCamara.value = '';
});
inputImagenMoldeGaleria.addEventListener('change', () => {
    usarArchivoImagenMolde(inputImagenMoldeGaleria.files[0]);
    inputImagenMoldeGaleria.value = '';
});

btnQuitarImagenMolde.addEventListener('click', () => {
    inputImagenMolde.value = '';
    inputEliminarImagenMolde.value = '1';
    mostrarPreviewImagenMolde(null);
});

function verFotoMolde(url) {
    if (!url) return;
    document.getElementById('fotoMoldeAmpliada').src = url;
    modalVerFotoMolde.show();
}

// ── Llamadas genéricas a los controladores ──────────────────────────────────
async function llamarMoldes(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_MOLDES, {
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

async function llamarProductos(accion, params = {}) {
    const body = new URLSearchParams({ accion, ...params });
    const resp = await fetch(CONTROLADOR_PRODUCTOS, {
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

async function cargarProductosSelect() {
    const json = await llamarProductos('LISTARPRODUCTOS', { texto: '', estado: 'activo' });
    const cont = document.getElementById('molde_producto_checks');

    if (!json.success) {
        console.error('No se pudo cargar la lista de productos:', json.message);
        return;
    }

    const productos = json.productos || [];
    cont.innerHTML = productos.map(p => `
        <div class="pc-check-item" onclick="toggleProductoCheck(${p.id})">
            <input type="checkbox" id="prodchk_${p.id}" value="${p.id}"
                   onclick="event.stopPropagation()" onchange="marcarCheckItem(${p.id})">
            <label for="prodchk_${p.id}" onclick="event.stopPropagation()">${p.codigo} - ${p.descripcion}</label>
        </div>
    `).join('');
}

function toggleProductoCheck(id) {
    const chk = document.getElementById(`prodchk_${id}`);
    chk.checked = !chk.checked;
    marcarCheckItem(id);
}

function marcarCheckItem(id) {
    const chk = document.getElementById(`prodchk_${id}`);
    chk.closest('.pc-check-item').classList.toggle('checked', chk.checked);
}

function obtenerProductoIdsSeleccionados() {
    return [...document.querySelectorAll('#molde_producto_checks input[type="checkbox"]:checked')]
        .map(chk => chk.value);
}

function marcarProductosSeleccionados(idsSeleccionados) {
    document.querySelectorAll('#molde_producto_checks input[type="checkbox"]').forEach(chk => {
        chk.checked = idsSeleccionados.includes(chk.value);
        marcarCheckItem(chk.value);
    });
}

// ── Listado ──────────────────────────────────────────────────────────────────
function escapeAttr(texto) {
    return String(texto ?? '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

async function cargarMoldes() {
    const texto  = document.getElementById('fm_texto').value.trim();
    const estado = document.getElementById('fm_estado').value;

    const json = await llamarMoldes('LISTARMOLDES', { texto, estado });
    const tbody = document.getElementById('tbodyMoldes');

    if (!json.success) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;">${json.message}</td></tr>`;
        return;
    }

    const moldes = json.moldes || [];
    if (moldes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No hay moldes registrados.</td></tr>';
        return;
    }

    tbody.innerHTML = moldes.map(m => {
        const productos = m.js_producto || [];
        const badgesProductos = productos.length
            ? productos.map(p => `<span class="badge bg-light text-dark border me-1 mb-1">${p.codigo} - ${p.descripcion}</span>`).join('')
            : '-';

        return `
    <tr id="fila-molde-${m.id}">
        <td data-label="Foto">${m.img_ruta
            ? `<img src="${m.img_ruta}" class="pc-thumb" alt="" onclick="verFotoMolde('${m.img_ruta}')">`
            : `<span class="pc-thumb-placeholder"><i class="fa-solid fa-image"></i></span>`}
        </td>
        <td data-label="Nombre">${m.nombre}</td>
        <td data-label="Forma">${m.forma ?? '-'}</td>
        <td data-label="Productos">${badgesProductos}</td>
        <td data-label="Estado">${!m.deleted_at
            ? '<span class="badge bg-success">Activo</span>'
            : '<span class="badge bg-secondary">Inactivo</span>'}
        </td>
        <td data-label="Acciones" class="pc-td-acciones">
            <button class="pc-icon-btn" onclick="abrirModalEditarMolde(${m.id})" title="Editar">
                <i class="fa-solid fa-pen"></i>
            </button>
            ${!m.deleted_at
                ? `<button class="pc-icon-btn" onclick="eliminarMolde(${m.id})" title="Desactivar">
                       <i class="fa-solid fa-trash"></i></button>`
                : `<button class="pc-icon-btn" onclick="reactivarMolde(${m.id})" title="Reactivar">
                       <i class="fa-solid fa-rotate-left"></i></button>`
            }
        </td>
    </tr>`;
    }).join('');
}

// ── Crear / Editar ───────────────────────────────────────────────────────────
function abrirModalCrearMolde() {
    document.getElementById('formMolde').reset();
    document.getElementById('molde_id').value = '';
    marcarProductosSeleccionados([]);
    document.getElementById('modalMoldeTitulo').textContent = 'Nuevo molde';
    inputEliminarImagenMolde.value = '0';
    mostrarPreviewImagenMolde(null);
    modalMolde.show();
}

async function abrirModalEditarMolde(id) {
    const json = await llamarMoldes('OBTENERMOLDE', { id });
    if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }

    const m = json.molde;
    document.getElementById('modalMoldeTitulo').textContent = 'Editar molde';
    document.getElementById('molde_id').value = m.id;
    document.getElementById('molde_nombre').value = m.nombre ?? '';
    document.getElementById('molde_forma').value = m.forma ?? '';

    const idsSeleccionados = (m.js_producto || []).map(p => String(p.producto_id));
    marcarProductosSeleccionados(idsSeleccionados);

    inputImagenMolde.value = '';
    inputEliminarImagenMolde.value = '0';
    mostrarPreviewImagenMolde(m.img_ruta || null);

    modalMolde.show();
}

document.getElementById('formMolde').addEventListener('submit', async function (e) {
    e.preventDefault();

    const productoIds = obtenerProductoIdsSeleccionados();
    if (productoIds.length === 0) {
        Swal.fire('Falta información', 'Selecciona al menos un producto asociado.', 'warning');
        return;
    }

    const formData = new FormData(this); // incluye el archivo de "imagen" automáticamente
    formData.append('accion', 'GUARDARMOLDE');
    productoIds.forEach(pid => formData.append('producto_ids[]', pid));

    const resp = await fetch(CONTROLADOR_MOLDES, { method: 'POST', body: formData });
    const json = await resp.json();

    if (json.success) {
        modalMolde.hide();
        Swal.fire('Listo', json.message, 'success');
        cargarMoldes();
    } else {
        Swal.fire('Error', json.message, 'error');
    }
});

// ── Eliminar / Reactivar ─────────────────────────────────────────────────────
function eliminarMolde(id) {
    Swal.fire({
        title: '¿Desactivar molde?',
        text: 'Podrás reactivarlo luego desde el listado de inactivos.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const json = await llamarMoldes('ELIMINARMOLDE', { id });
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarMoldes();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}

function reactivarMolde(id) {
    llamarMoldes('REACTIVARMOLDE', { id }).then(json => {
        if (json.success) {
            Swal.fire('Listo', json.message, 'success');
            cargarMoldes();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>