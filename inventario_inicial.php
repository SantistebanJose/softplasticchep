<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['usuario_id']) || strtoupper(trim((string)($_SESSION['rol_usuario'] ?? ''))) !== 'ADMINISTRADOR') {
    http_response_code(403);
    exit('No tienes permiso para registrar inventario inicial.');
}
$pageTitle = 'Inventario Inicial';
$pageSubtitle = 'Carga las existencias que ya estaban empaquetadas antes de implementar el sistema.';
$activePage = 'inventario_inicial';
include 'header.php';
?>
<style>
 .ii-layout{display:grid;grid-template-columns:minmax(280px,420px) minmax(0,1fr);gap:18px;align-items:start}
 .ii-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px}
 .ii-note{border:1px solid #f3d58c;background:#fff8e7;color:#755312;border-radius:10px;padding:12px 14px;font-size:.92rem}
 .ii-preview{background:#f7f9fc;border-radius:10px;padding:12px;margin-top:14px;color:#455064}
 @media(max-width:900px){.ii-layout{grid-template-columns:1fr}}
 @media(max-width:560px){.ii-card{padding:14px}.ii-actions .btn{width:100%}}
</style>
<div class="ii-layout">
  <section class="ii-card">
    <h2 class="h5 mb-3">Registrar existencias de apertura</h2>
    <div class="ii-note mb-3"><i class="fa-solid fa-circle-info me-1"></i> Registra aquí productos que ya estaban listos para la venta. No se crearán producciones ni ensamblajes.</div>
    <form id="iiForm">
      <div class="mb-3"><label for="iiProducto" class="form-label">Producto</label><select id="iiProducto" class="form-select" required><option value="">Cargando productos...</option></select></div>
      <div class="mb-3"><label for="iiSucursal" class="form-label">Sucursal</label><select id="iiSucursal" class="form-select" required><option value="">Cargando sucursales...</option></select></div>
      <div class="mb-3"><label for="iiCantidad" class="form-label">Cantidad disponible para venta</label><div class="input-group"><input id="iiCantidad" type="number" class="form-control" min="0.0001" step="0.0001" required><span class="input-group-text" id="iiUnidad">Unidad</span></div><div class="form-text">Usa la unidad de empaquetado configurada para el producto.</div></div>
      <div class="mb-3"><label for="iiFecha" class="form-label">Fecha del conteo físico</label><input id="iiFecha" type="date" class="form-control" required></div>
      <div class="mb-3"><label for="iiObservacion" class="form-label">Observación</label><textarea id="iiObservacion" class="form-control" rows="2" maxlength="500" placeholder="Ej. Stock existente al iniciar el sistema"></textarea></div>
      <div class="ii-preview" id="iiResumen">Selecciona un producto para ver su unidad.</div>
      <div class="ii-actions d-flex gap-2 mt-3"><button type="submit" class="btn btn-primary" id="iiGuardar"><i class="fa-solid fa-boxes-stacked me-1"></i> Registrar inventario</button><button type="reset" class="btn btn-outline-secondary">Limpiar</button></div>
    </form>
  </section>
  <section class="ii-card">
    <h2 class="h5">Cómo se aplicará</h2>
    <ul class="mb-0">
      <li>El stock aparecerá en la disponibilidad de venta de la sucursal elegida.</li>
      <li>Las ventas lo descontarán con el mecanismo actual de empaquetados.</li>
      <li>El reporte de empaquetado lo identificará como <b>Inventario inicial</b>.</li>
      <li>La carga conserva quién la registró, cuándo y la fecha del conteo físico.</li>
    </ul>
  </section>
</div>
<script>
const II_API='controllers/clssInventarioInicial.php';
const ii=id=>document.getElementById(id);
let iiProductos=[];
function iiEsc(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
async function iiCall(data){const r=await fetch(II_API,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(data)});const j=await r.json();if(!j.success)throw new Error(j.message||'No se pudo completar la operación.');return j}
function iiProductoCambio(){const p=iiProductos.find(x=>String(x.id)===ii('iiProducto').value);ii('iiUnidad').textContent=p?.unidad||'Unidad';ii('iiResumen').textContent=p?`El ingreso se guardará como inventario inicial de ${p.codigo||''} ${p.descripcion} (${p.unidad}), en la sucursal seleccionada.`:'Selecciona un producto para ver su unidad.'}
async function iiCargar(){try{const j=await iiCall({accion:'LISTAR_CATALOGOS'});iiProductos=j.productos||[];ii('iiProducto').innerHTML='<option value="">Selecciona un producto</option>'+iiProductos.map(p=>`<option value="${iiEsc(p.id)}">${iiEsc(p.codigo)} — ${iiEsc(p.descripcion)} (${iiEsc(p.unidad)})</option>`).join('');ii('iiSucursal').innerHTML='<option value="">Selecciona una sucursal</option>'+(j.sucursales||[]).map(s=>`<option value="${iiEsc(s.id)}">${iiEsc(s.nombre)}</option>`).join('');}catch(e){ii('iiProducto').innerHTML=`<option value="">${iiEsc(e.message)}</option>`}}
function iiHoy(){const d=new Date(Date.now()-new Date().getTimezoneOffset()*60000);return d.toISOString().slice(0,10)}
ii('iiFecha').value=iiHoy();
ii('iiProducto').addEventListener('change',iiProductoCambio);
ii('iiForm').addEventListener('submit',async e=>{e.preventDefault();const button=ii('iiGuardar');button.disabled=true;try{const j=await iiCall({accion:'REGISTRAR',producto_id:ii('iiProducto').value,sucursal_id:ii('iiSucursal').value,cantidad:ii('iiCantidad').value,fecha_inventario:ii('iiFecha').value,observacion:ii('iiObservacion').value.trim()});if(window.Swal)await Swal.fire({icon:'success',title:'Registrado',text:j.message});else alert(j.message);ii('iiForm').reset();ii('iiFecha').value=iiHoy();iiProductoCambio()}catch(err){if(window.Swal)Swal.fire({icon:'error',title:'No se pudo registrar',text:err.message});else alert(err.message)}finally{button.disabled=false}});
iiCargar();
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php include 'footer.php'; ?>
