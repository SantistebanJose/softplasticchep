<?php
$pageTitle = 'Modalidades y gestión';
$pageSubtitle = 'Catálogos de personal';
$activePage = 'catalogos_personal';
require_once __DIR__ . '/controllers/clssVerificarSession.php';
$usuarioPagina = exigirSesion();
exigirRol($usuarioPagina, ['administrador', 'personal'], 'personal');
include 'header.php';
?>
<div class="pc-card">
  <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div><h2>Modalidades y tipos de gestión</h2><p class="text-muted mb-0">Mantén actualizadas las opciones que se asignan a los operarios.</p></div>
    <button class="pc-btn pc-btn-primary" onclick="nuevoItem()"><i class="fa-solid fa-plus"></i> Nueva opción</button>
  </div>
  <div class="d-flex gap-2 flex-wrap align-items-center mb-3">
    <div class="btn-group" role="group" aria-label="Catálogos">
      <button type="button" class="btn btn-primary" id="btnModalidad" onclick="cambiarTipo('modalidad')">Modalidades</button>
      <button type="button" class="btn btn-outline-primary" id="btnGestion" onclick="cambiarTipo('tipo_gestion')">Tipos de gestión</button>
    </div>
    <input id="buscarItem" class="form-control" style="max-width:320px" placeholder="Buscar por nombre o descripción...">
    <select id="estadoItem" class="form-select" style="max-width:170px"><option value="activos">Activos</option><option value="inactivos">Inactivos</option><option value="todos">Todos</option></select>
  </div>
  <div class="pc-table-wrap pc-table-responsive-cards">
    <table class="pc-table"><thead><tr><th>Nombre</th><th>Descripción</th><th>Estado</th><th>Acciones</th></tr></thead>
      <tbody id="itemsBody"><tr><td colspan="4" class="text-center">Cargando...</td></tr></tbody>
    </table>
  </div>
</div>
<div class="modal fade" id="modalItem" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <form id="formItem">
    <div class="modal-header"><h5 class="modal-title" id="tituloItem">Nueva opción</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><input type="hidden" id="originalItem">
      <div class="mb-3"><label class="form-label" for="nombreItem">Nombre *</label><input id="nombreItem" class="form-control text-uppercase" maxlength="50" required></div>
      <div><label class="form-label" for="descripcionItem">Descripción</label><textarea id="descripcionItem" class="form-control" rows="3" placeholder="Opcional"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div>
  </form>
</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/device-tracking.js"></script>
<script src="assets/js/app-common.js"></script>
<script>
const URL_CATALOGO_PERSONAL = 'controllers/clssCatalogoPersonal.php';
const modalItem = new bootstrap.Modal(document.getElementById('modalItem'));
let tipoCatalogo = 'modalidad';
let debounceItem;
async function llamarCatalogo(accion, params = {}) { return llamar(URL_CATALOGO_PERSONAL, accion, { tipo: tipoCatalogo, ...params }); }
document.addEventListener('DOMContentLoaded', () => {
  cargarItems();
  document.getElementById('buscarItem').addEventListener('input', () => { clearTimeout(debounceItem); debounceItem = setTimeout(cargarItems, 300); });
  document.getElementById('estadoItem').addEventListener('change', cargarItems);
  document.getElementById('formItem').addEventListener('submit', guardarItem);
});
function cambiarTipo(tipo) {
  tipoCatalogo = tipo;
  document.getElementById('btnModalidad').className = tipo === 'modalidad' ? 'btn btn-primary' : 'btn btn-outline-primary';
  document.getElementById('btnGestion').className = tipo === 'tipo_gestion' ? 'btn btn-primary' : 'btn btn-outline-primary';
  cargarItems();
}
async function cargarItems() {
  const body = document.getElementById('itemsBody');
  try {
    const json = await llamarCatalogo('LISTARCATALOGOPERSONAL', { texto: document.getElementById('buscarItem').value.trim(), estado: document.getElementById('estadoItem').value });
    if (!json.success) throw new Error(json.message);
    const items = json.items || [];
    if (!items.length) { body.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No hay opciones para mostrar.</td></tr>'; return; }
    body.innerHTML = items.map(item => `<tr>
      <td data-label="Nombre"><strong>${escapar(item.nombre)}</strong></td><td data-label="Descripción">${escapar(item.descripcion || '-')}</td>
      <td data-label="Estado">${item.deleted_at ? '<span class="badge bg-secondary">Inactivo</span>' : '<span class="badge bg-success">Activo</span>'}</td>
      <td data-label="Acciones" class="pc-td-acciones"><button class="pc-icon-btn" title="Editar" onclick="editarItem('${js(item.nombre)}')"><i class="fa-solid fa-pen"></i></button>
      ${item.deleted_at ? `<button class="pc-icon-btn" title="Reactivar" onclick="estadoItem('${js(item.nombre)}',true)"><i class="fa-solid fa-rotate-left"></i></button>` : `<button class="pc-icon-btn" title="Desactivar" onclick="estadoItem('${js(item.nombre)}',false)"><i class="fa-solid fa-trash"></i></button>`}</td>
    </tr>`).join('');
  } catch (e) { console.error(e); body.innerHTML = '<tr><td colspan="4" class="text-center text-danger">No se pudo cargar el catálogo. Verifica que exista la tabla.</td></tr>'; }
}
function escapar(v) { return String(v).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function js(v) { return String(v).replace(/\\/g,'\\\\').replace(/'/g,"\\'"); }
function nuevoItem() { document.getElementById('formItem').reset(); document.getElementById('originalItem').value=''; document.getElementById('tituloItem').textContent = tipoCatalogo === 'modalidad' ? 'Nueva modalidad' : 'Nuevo tipo de gestión'; modalItem.show(); }
async function editarItem(nombre) { const r=await llamarCatalogo('OBTENERCATALOGOPERSONAL',{nombre}); if(!r.success){Swal.fire('Error',r.message,'error');return;} document.getElementById('originalItem').value=r.item.nombre; document.getElementById('nombreItem').value=r.item.nombre; document.getElementById('descripcionItem').value=r.item.descripcion||''; document.getElementById('tituloItem').textContent='Editar opción'; modalItem.show(); }
async function guardarItem(e) { e.preventDefault(); const r=await llamarCatalogo('GUARDARCATALOGOPERSONAL',{nombre_original:document.getElementById('originalItem').value,nombre:document.getElementById('nombreItem').value.trim(),descripcion:document.getElementById('descripcionItem').value.trim()}); if(!r.success){Swal.fire('No se pudo guardar',r.message,'error');return;} modalItem.hide(); Swal.fire('Listo',r.message,'success'); cargarItems(); }
async function estadoItem(nombre,activar) { if(!activar){const r=await Swal.fire({title:'¿Desactivar opción?',text:'Dejará de aparecer en nuevos registros; los datos guardados se conservan.',icon:'warning',showCancelButton:true,confirmButtonText:'Desactivar',cancelButtonText:'Cancelar'});if(!r.isConfirmed)return;} const r=await llamarCatalogo(activar?'REACTIVARCATALOGOPERSONAL':'DESACTIVARCATALOGOPERSONAL',{nombre}); if(!r.success){Swal.fire('Error',r.message,'error');return;} Swal.fire('Listo',r.message,'success'); cargarItems(); }
</script>
<?php require __DIR__ . '/footer.php'; ?>
