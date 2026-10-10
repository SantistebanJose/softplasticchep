<?php
$pageTitle = 'Reporte de Empaquetado';
$pageSubtitle = 'Producción empaquetada, operarios, sucursales y estado de los paquetes.';
$activePage = 'reporte_empaquetado';
include 'header.php';
?>
<style>
.re-wrap{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px;margin-bottom:18px}
.re-filtros{display:flex;gap:12px;align-items:end;flex-wrap:wrap}.re-filtros>div{min-width:150px;flex:1}
.re-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin:16px 0}
.re-stat{border:1px solid #e5e7eb;border-top:3px solid #245df5;border-radius:10px;padding:14px;text-align:center;background:#fff}
.re-stat b{display:block;font-size:1.5rem;color:#14213d}.re-stat span{font-size:.78rem;color:#687386;text-transform:uppercase}
.re-table-wrap{overflow:auto}.re-table{width:100%;border-collapse:collapse;min-width:850px}.re-table th,.re-table td{padding:10px;border-bottom:1px solid #e9edf3;text-align:left;vertical-align:top}.re-table th{background:#f3f5f9;font-size:.78rem;text-transform:uppercase;color:#5d6878}
.re-empty{text-align:center;color:#6b7280;padding:35px}
</style>
<section class="re-wrap">
  <div class="re-filtros">
    <div><label class="form-label">Desde</label><input type="date" class="form-control" id="reDesde"></div>
    <div><label class="form-label">Hasta</label><input type="date" class="form-control" id="reHasta"></div>
    <div><label class="form-label">Producto</label><input class="form-control" id="reTexto" placeholder="Código o descripción"></div>
    <div><label class="form-label">Operario</label><select class="form-select" id="reOperario"><option value="0">Todos</option></select></div>
    <div><label class="form-label">Estado</label><select class="form-select" id="reEstado"><option value="">Todos</option><option value="disponible">Disponible</option><option value="vendido">Vendido</option></select></div>
    <button class="btn btn-primary" type="button" id="reBuscar"><i class="fa-solid fa-magnifying-glass"></i> Consultar</button>
    <button class="btn btn-outline-secondary" type="button" id="reLimpiar">Limpiar</button>
    <button class="btn btn-outline-success" type="button" id="reCsv"><i class="fa-solid fa-file-csv"></i> Exportar CSV</button>
  </div>
</section>
<section class="re-stats" id="reStats"></section>
<section class="re-wrap">
  <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 m-0">Detalle de empaquetados</h2><span id="reCantidad" class="text-muted"></span></div>
  <div class="re-table-wrap"><table class="re-table"><thead><tr><th>Fecha</th><th>Producto</th><th>Origen</th><th>Paquetes / total</th><th>Operario(s)</th><th>Sucursal</th><th>Estado</th></tr></thead><tbody id="reFilas"><tr><td colspan="7" class="re-empty">Ajusta los filtros y consulta el reporte.</td></tr></tbody></table></div>
</section>
<script>
const RE_API='controllers/clssEmpaquetado.php'; let reporteEmp=[];
const $reDesde=document.getElementById('reDesde'),$reHasta=document.getElementById('reHasta'),$reTexto=document.getElementById('reTexto'),$reOperario=document.getElementById('reOperario'),$reEstado=document.getElementById('reEstado'),$reBuscar=document.getElementById('reBuscar'),$reLimpiar=document.getElementById('reLimpiar'),$reCsv=document.getElementById('reCsv'),$reFilas=document.getElementById('reFilas'),$reStats=document.getElementById('reStats'),$reCantidad=document.getElementById('reCantidad');
const reEsc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function reParams(){return {fecha_desde:$reDesde.value,fecha_hasta:$reHasta.value,texto:$reTexto.value.trim(),operario_id:$reOperario.value,estado:$reEstado.value}}
async function reCall(accion,params={}){const fd=new FormData();fd.append('accion',accion);Object.entries(params).forEach(([k,v])=>fd.append(k,v));const resp=await fetch(RE_API,{method:'POST',body:fd});return resp.json()}
function reFecha(s){if(!s)return '—';return new Date(String(s).replace(' ','T')).toLocaleString('es-PE')}
function reQty(n){return Number(n||0).toLocaleString('es-PE',{maximumFractionDigits:3})}
function reOrigen(r){return r.origen_tipo==='produccion'?'Producción directa':r.origen_tipo==='ensamblaje'?'Ensamblaje':r.origen_tipo||'—'}
function reOperarios(r){let ops=r.js_operarios;try{if(typeof ops==='string')ops=JSON.parse(ops)}catch{ops=[]}return Array.isArray(ops)&&ops.length?ops.map(o=>o.nombre_completo||'').filter(Boolean).join(', '):(r.operario_nombre||'—')}
function reRender(data){reporteEmp=data.empaquetados||[];const s=data.resumen||{};const porUnidad=Object.entries(s.cantidad_por_unidad||{}).map(([u,n])=>`${reQty(n)} ${u}`).join(' · ')||'0';$reStats.innerHTML=`<div class="re-stat"><b>${s.registros||0}</b><span>Registros</span></div><div class="re-stat"><b>${reEsc(porUnidad)}</b><span>Cantidad por unidad</span></div><div class="re-stat"><b>${s.disponibles||0}</b><span>Disponibles</span></div><div class="re-stat"><b>${s.vendidos||0}</b><span>Vendidos</span></div>`;$reCantidad.textContent=`${reporteEmp.length} registros`;
 $reFilas.innerHTML=reporteEmp.length?reporteEmp.map(r=>`<tr><td>${reEsc(reFecha(r.created_at))}</td><td><b>${reEsc(r.producto_codigo||'')}</b><br>${reEsc(r.producto_descripcion||'—')}</td><td>${reEsc(reOrigen(r))}${r.emsamblaje_id?` #${reEsc(r.emsamblaje_id)}`:r.produccion_id?` #${reEsc(r.produccion_id)}`:''}</td><td>${reEsc(reQty(r.cantidad_tota)+' '+(r.unidad_corto||''))}</td><td>${reEsc(reOperarios(r))}</td><td>${reEsc(r.sucursal_nombre||'—')}</td><td>${r.pasado_venta?'<span class="badge bg-warning text-dark">Vendido</span>':'<span class="badge bg-success">Disponible</span>'}</td></tr>`).join(''):'<tr><td colspan="7" class="re-empty">No hay empaquetados en el periodo seleccionado.</td></tr>'}
async function reBuscarReporte(){ $reFilas.innerHTML='<tr><td colspan="7" class="re-empty">Cargando reporte…</td></tr>';const j=await reCall('REPORTEEMPAQUETADODASHBOARD',reParams());if(!j.success){$reFilas.innerHTML=`<tr><td colspan="7" class="re-empty text-danger">${reEsc(j.message)}</td></tr>`;return}reRender(j)}
async function reInit(){const hoy=new Date(),primero=new Date(hoy.getFullYear(),hoy.getMonth(),1),isoLocal=d=>`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;$reDesde.value=isoLocal(primero);$reHasta.value=isoLocal(hoy);const j=await reCall('BUSCAROPERARIOS');if(j.success)(j.operario||[]).forEach(o=>$reOperario.insertAdjacentHTML('beforeend',`<option value="${reEsc(o.id)}">${reEsc(o.nombre_completo)}</option>`));await reBuscarReporte()}
$reBuscar.onclick=reBuscarReporte;$reLimpiar.onclick=()=>{$reDesde.value='';$reHasta.value='';$reTexto.value='';$reOperario.value='0';$reEstado.value='';reBuscarReporte()};
$reCsv.onclick=()=>{const rows=[['Fecha','Producto','Origen','Cantidad','Unidad','Operarios','Sucursal','Estado'],...reporteEmp.map(r=>[r.created_at,r.producto_codigo+' '+r.producto_descripcion,reOrigen(r),r.cantidad_tota,r.unidad_corto,reOperarios(r),r.sucursal_nombre,r.pasado_venta?'Vendido':'Disponible'])];const csv='\ufeff'+rows.map(row=>row.map(v=>'"'+String(v??'').replaceAll('"','""')+'"').join(';')).join('\r\n');const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8'}));a.download='reporte-empaquetado.csv';a.click();URL.revokeObjectURL(a.href)};
reInit();
</script>
<?php include 'footer.php'; ?>
