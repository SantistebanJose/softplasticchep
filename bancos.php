<?php
$pageTitle = 'Bancos';
$pageSubtitle = 'Catálogo de entidades bancarias';
$activePage = 'bancos';
require_once __DIR__ . '/controllers/clssVerificarSession.php';
$usuarioPagina = exigirSesion();
exigirRol($usuarioPagina, ['administrador', 'personal'], 'personal');
include 'header.php';
?>

<div class="pc-card">
  <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div><h2>Bancos</h2><p class="text-muted mb-0">Administra los bancos disponibles para los datos de pago del personal.</p></div>
    <button class="pc-btn pc-btn-primary" onclick="abrirBanco()"><i class="fa-solid fa-plus"></i> Nuevo banco</button>
  </div>
  <div class="pc-filtros d-flex gap-2 flex-wrap mb-3">
    <input id="buscarBanco" class="form-control" style="max-width:320px" placeholder="Buscar banco o descripción...">
    <select id="estadoBanco" class="form-select" style="max-width:180px">
      <option value="activos">Activos</option><option value="inactivos">Inactivos</option><option value="todos">Todos</option>
    </select>
  </div>
  <div class="pc-table-wrap pc-table-responsive-cards">
    <table class="pc-table"><thead><tr><th>Banco</th><th>Descripción</th><th>Estado</th><th>Acciones</th></tr></thead>
      <tbody id="bancosBody"><tr><td colspan="4" class="text-center">Cargando...</td></tr></tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="modalBanco" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <form id="formBanco">
      <div class="modal-header"><h5 class="modal-title" id="tituloBanco">Nuevo banco</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" id="bancoNombreOriginal">
        <div class="mb-3"><label for="bancoNombre" class="form-label">Nombre *</label><input id="bancoNombre" class="form-control text-uppercase" maxlength="20" required placeholder="Ej.: BCP"></div>
        <div><label for="bancoDescripcion" class="form-label">Descripción</label><textarea id="bancoDescripcion" class="form-control" rows="3" placeholder="Detalle opcional"></textarea></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar banco</button></div>
    </form>
  </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/device-tracking.js"></script>
<script src="assets/js/app-common.js"></script>
<script>
const CONTROLADOR_BANCOS = 'controllers/clssBanco.php';
const modalBanco = new bootstrap.Modal(document.getElementById('modalBanco'));
let temporizadorBanco;

async function llamarBanco(accion, params = {}) {
  return llamar(CONTROLADOR_BANCOS, accion, params);
}

document.addEventListener('DOMContentLoaded', () => {
  cargarBancos();
  document.getElementById('buscarBanco').addEventListener('input', () => {
    clearTimeout(temporizadorBanco);
    temporizadorBanco = setTimeout(cargarBancos, 300);
  });
  document.getElementById('estadoBanco').addEventListener('change', cargarBancos);
  document.getElementById('formBanco').addEventListener('submit', guardarBanco);
});

async function cargarBancos() {
  const body = document.getElementById('bancosBody');
  try {
    const json = await llamarBanco('LISTARBANCOS', {
      texto: document.getElementById('buscarBanco').value.trim(),
      estado: document.getElementById('estadoBanco').value,
    });
    if (!json.success) throw new Error(json.message || 'No se pudo cargar el catálogo.');
    const bancos = json.bancos || [];
    if (!bancos.length) {
      body.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No hay bancos para mostrar.</td></tr>';
      return;
    }
    body.innerHTML = bancos.map(b => `<tr>
      <td data-label="Banco"><strong>${escaparHtmlBanco(b.nombre)}</strong></td>
      <td data-label="Descripción">${escaparHtmlBanco(b.descripcion || '-')}</td>
      <td data-label="Estado">${b.deleted_at ? '<span class="badge bg-secondary">Inactivo</span>' : '<span class="badge bg-success">Activo</span>'}</td>
      <td data-label="Acciones" class="pc-td-acciones">
        <button class="pc-icon-btn" title="Editar" onclick="editarBanco('${escaparJsBanco(b.nombre)}')"><i class="fa-solid fa-pen"></i></button>
        ${b.deleted_at ? `<button class="pc-icon-btn" title="Reactivar" onclick="cambiarEstadoBanco('${escaparJsBanco(b.nombre)}','REACTIVARBANCO')"><i class="fa-solid fa-rotate-left"></i></button>` : `<button class="pc-icon-btn" title="Desactivar" onclick="cambiarEstadoBanco('${escaparJsBanco(b.nombre)}','DESACTIVARBANCO')"><i class="fa-solid fa-trash"></i></button>`}
      </td>
    </tr>`).join('');
  } catch (error) {
    console.error('Error cargando bancos:', error);
    body.innerHTML = '<tr><td colspan="4" class="text-center text-danger">No se pudo cargar el catálogo de bancos.</td></tr>';
  }
}

function escaparHtmlBanco(valor) {
  return String(valor).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function escaparJsBanco(valor) {
  return String(valor).replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

function abrirBanco() {
  document.getElementById('formBanco').reset();
  document.getElementById('bancoNombreOriginal').value = '';
  document.getElementById('tituloBanco').textContent = 'Nuevo banco';
  modalBanco.show();
}

async function editarBanco(nombre) {
  const json = await llamarBanco('OBTENERBANCO', { nombre });
  if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }
  document.getElementById('bancoNombreOriginal').value = json.banco.nombre;
  document.getElementById('bancoNombre').value = json.banco.nombre;
  document.getElementById('bancoDescripcion').value = json.banco.descripcion || '';
  document.getElementById('tituloBanco').textContent = 'Editar banco';
  modalBanco.show();
}

async function guardarBanco(event) {
  event.preventDefault();
  const form = new URLSearchParams({
    accion: 'GUARDARBANCO',
    nombre_original: document.getElementById('bancoNombreOriginal').value,
    nombre: document.getElementById('bancoNombre').value.trim(),
    descripcion: document.getElementById('bancoDescripcion').value.trim(),
  });
  const respuesta = await fetch(CONTROLADOR_BANCOS, { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: form });
  const json = await respuesta.json();
  if (!json.success) { Swal.fire('No se pudo guardar', json.message, 'error'); return; }
  modalBanco.hide();
  Swal.fire('Listo', json.message, 'success');
  cargarBancos();
}

async function cambiarEstadoBanco(nombre, accion) {
  const activar = accion === 'REACTIVARBANCO';
  if (!activar) {
    const confirmacion = await Swal.fire({title:'¿Desactivar banco?', text:'No aparecerá en las nuevas selecciones; los datos históricos se conservan.', icon:'warning', showCancelButton:true, confirmButtonText:'Desactivar', cancelButtonText:'Cancelar'});
    if (!confirmacion.isConfirmed) return;
  }
  const json = await llamarBanco(accion, { nombre });
  if (!json.success) { Swal.fire('Error', json.message, 'error'); return; }
  Swal.fire('Listo', json.message, 'success');
  cargarBancos();
}
</script>
<?php require __DIR__ . '/footer.php'; ?>
