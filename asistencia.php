<?php
$pageTitle = 'Control de asistencia';
$pageSubtitle = 'Registro diario del personal';
$activePage = 'asistencia';
require_once __DIR__ . '/controllers/clssVerificarSession.php';
$usuarioAsistencia = exigirSesion();
exigirRol($usuarioAsistencia, ['administrador', 'personal'], 'personal');
include 'header.php';
?>
<style>
.asistencia-toolbar { display:flex; align-items:end; gap:12px; flex-wrap:wrap; }
.asistencia-toolbar .form-group { min-width:190px; }
.asistencia-summary { display:grid; grid-template-columns:repeat(auto-fit,minmax(120px,1fr)); gap:10px; margin:18px 0; }
.asistencia-stat { background:#f7f9fc; border:1px solid #e4e9f1; border-radius:13px; padding:12px 15px; }
.asistencia-stat strong { display:block; color:#17243a; font-size:21px; }
.asistencia-stat span { color:#75839a; font-size:11px; text-transform:uppercase; letter-spacing:.04em; }
.asistencia-name { font-weight:650; color:#17243a; }
.asistencia-sub { color:#738198; font-size:12px; }
.asistencia-status-group { display:flex; flex-wrap:wrap; gap:5px; min-width:265px; }
.asistencia-status-btn { border:1px solid #dce3ed; border-radius:9px; background:#fff; color:#536176; padding:7px 9px; font:600 11px/1.2 Inter, sans-serif; cursor:pointer; transition:background .15s,border-color .15s,color .15s,transform .15s; }
.asistencia-status-btn:hover { border-color:#8ea6c7; transform:translateY(-1px); }
.asistencia-status-btn[aria-pressed="true"] { box-shadow:0 0 0 2px rgba(29,78,216,.12); }
.asistencia-status-btn[data-estado="P"][aria-pressed="true"] { background:#eef1f5; border-color:#8793a4; color:#39465a; }
.asistencia-status-btn[data-estado="A"][aria-pressed="true"] { background:#e7f7ed; border-color:#30a060; color:#167342; }
.asistencia-status-btn[data-estado="T"][aria-pressed="true"] { background:#fff4d9; border-color:#e2a52b; color:#865b00; }
.asistencia-status-btn[data-estado="D"][aria-pressed="true"] { background:#e9f5ff; border-color:#52a2e2; color:#17649d; }
.asistencia-status-btn[data-estado="F"][aria-pressed="true"] { background:#f1eaff; border-color:#9a72dc; color:#6440a0; }
.asistencia-comment { min-width:190px; }
.pc-btn-has-changes { box-shadow:0 0 0 3px rgba(242,166,54,.22); }
.asistencia-empty { text-align:center; padding:44px 20px; color:#748198; }
.asistencia-period-day { border:1px solid #e2e8f0; border-radius:13px; background:#fff; margin-bottom:10px; overflow:hidden; }
.asistencia-period-day summary { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:15px 18px; cursor:pointer; list-style:none; background:#f8fafc; }
.asistencia-period-day summary::-webkit-details-marker { display:none; }
.asistencia-period-day summary::after { content:'+'; color:#2463c7; font-size:20px; font-weight:700; }
.asistencia-period-day[open] summary::after { content:'−'; }
.asistencia-period-meta { color:#748198; font-size:12px; }
.asistencia-period-badges { display:flex; flex-wrap:wrap; gap:6px; padding:0 18px 12px; background:#f8fafc; }
.asistencia-period-badge { border-radius:999px; padding:4px 8px; background:#eef2f7; color:#475569; font-size:11px; font-weight:600; }
.asistencia-period-table { padding:0 12px 12px; overflow:auto; }
.asistencia-period-table table { width:100%; min-width:560px; border-collapse:collapse; }
.asistencia-period-table th,.asistencia-period-table td { padding:9px 10px; border-bottom:1px solid #edf0f4; text-align:left; font-size:13px; }
.asistencia-period-table th { color:#708098; font-size:11px; text-transform:uppercase; }
.asistencia-period-title { color:#17243a; font-weight:700; }
.asistencia-period-report { margin-top:18px; }
.asistencia-operario-link { border:0; background:transparent; padding:0; color:#164ea8; font:inherit; font-weight:700; text-align:left; cursor:pointer; }
.asistencia-operario-link:hover { color:#0b3477; text-decoration:underline; }
.asistencia-personal-history { max-height:65vh; overflow:auto; text-align:left; }
.asistencia-personal-day { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:9px; padding:13px 15px; border:1px solid #e2e8f0; border-left:4px solid #2463c7; border-radius:11px; background:#f8fafc; }
.asistencia-personal-date { color:#17243a; font-weight:700; }
.asistencia-personal-status { flex:0 0 auto; padding:5px 9px; border-radius:999px; background:#eef2f7; color:#475569; font-size:12px; font-weight:700; }
.asistencia-personal-status[data-estado="A"] { background:#e7f7ed; color:#167342; }
.asistencia-personal-status[data-estado="T"] { background:#fff4d9; color:#865b00; }
.asistencia-personal-status[data-estado="F"] { background:#fff0f0; color:#a52c34; }
.asistencia-personal-status[data-estado="D"] { background:#e9f5ff; color:#17649d; }
@media(max-width:760px){.asistencia-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.asistencia-toolbar .form-group{flex:1}.asistencia-comment{min-width:150px}}
</style>

<div class="pc-card">
  <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div><h2>Control de asistencia</h2><p class="text-muted mb-0">Abre la asistencia de una fecha y registra el estado de cada operario.</p></div>
  </div>
  <div class="asistencia-toolbar mb-3">
    <div class="form-group"><label for="tipoPeriodo" class="form-label">Mostrar por</label><select id="tipoPeriodo" class="form-select"><option value="dia">Día</option><option value="semana">Semana</option><option value="mes">Mes</option></select></div>
    <div class="form-group" id="campoFechaDia"><label for="fechaAsistencia" class="form-label">Fecha de asistencia</label><input id="fechaAsistencia" type="date" class="form-control"></div>
    <div class="form-group d-none" id="campoFechaSemana"><label for="fechaSemana" class="form-label">Elige un día de la semana</label><input id="fechaSemana" type="date" class="form-control"></div>
    <div class="form-group d-none" id="campoMes"><label for="mesAsistencia" class="form-label">Mes</label><input id="mesAsistencia" type="month" class="form-control"></div>
    <div class="form-group flex-grow-1"><label for="buscarOperario" class="form-label">Buscar operario</label><input id="buscarOperario" class="form-control" placeholder="Nombre, apellido o apodo..."></div>
    <button class="pc-btn pc-btn-primary" id="btnAbrirAsistencia"><i class="fa-solid fa-calendar-plus"></i> Iniciar asistencia</button>
    <button class="pc-btn pc-btn-primary d-none" id="btnGuardarAsistencia"><i class="fa-solid fa-floppy-disk"></i> Guardar cambios</button>
  </div>
  <div id="estadoCabecera" class="alert alert-light border d-none"></div>
  <div id="resumenAsistencia" class="asistencia-summary d-none"></div>
  <div id="mensajeAsistencia" class="asistencia-empty">Selecciona una fecha para consultar la asistencia.</div>
  <div id="resumenPeriodo" class="asistencia-summary d-none"></div>
  <div id="reportePeriodo" class="asistencia-period-report d-none"></div>
  <div class="pc-table-wrap pc-table-responsive-cards d-none" id="tablaAsistenciaWrap">
    <table class="pc-table"><thead><tr><th>Operario</th><th>Cargo</th><th>Estado</th><th>Comentario</th><th>Historial</th></tr></thead><tbody id="asistenciaBody"></tbody></table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/device-tracking.js"></script>
<script src="assets/js/app-common.js"></script>
<script>
const CONTROLADOR_ASISTENCIA = 'controllers/clssAsistencia.php';
let asistenciaActual = null;
let operariosAsistencia = [];
let modoActual = 'dia';
let asistenciasPeriodo = [];
let timerBuscarAsistencia;
const fechaHoy = new Date();
const hoyISO = `${fechaHoy.getFullYear()}-${String(fechaHoy.getMonth()+1).padStart(2,'0')}-${String(fechaHoy.getDate()).padStart(2,'0')}`;
document.getElementById('fechaAsistencia').value = hoyISO;
document.getElementById('fechaSemana').value = hoyISO;
document.getElementById('mesAsistencia').value = hoyISO.slice(0,7);

async function llamarAsistencia(accion, params = {}) { return llamar(CONTROLADOR_ASISTENCIA, accion, params); }
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('tipoPeriodo').addEventListener('change', cambiarPeriodo);
  document.getElementById('fechaAsistencia').addEventListener('change', cargarVistaPeriodo);
  document.getElementById('fechaSemana').addEventListener('change', cargarVistaPeriodo);
  document.getElementById('mesAsistencia').addEventListener('change', cargarVistaPeriodo);
  document.getElementById('btnAbrirAsistencia').addEventListener('click', iniciarAsistencia);
  document.getElementById('btnGuardarAsistencia').addEventListener('click', guardarCambiosAsistencia);
  document.getElementById('buscarOperario').addEventListener('input', () => { clearTimeout(timerBuscarAsistencia); timerBuscarAsistencia=setTimeout(()=>modoActual==='dia'?renderizarOperarios():renderizarPeriodo(),180); });
  cargarAsistencia();
});

function cambiarPeriodo() {
  modoActual = document.getElementById('tipoPeriodo').value;
  document.getElementById('campoFechaDia').classList.toggle('d-none', modoActual !== 'dia');
  document.getElementById('campoFechaSemana').classList.toggle('d-none', modoActual !== 'semana');
  document.getElementById('campoMes').classList.toggle('d-none', modoActual !== 'mes');
  const diario = modoActual === 'dia';
  ['btnAbrirAsistencia','btnGuardarAsistencia','estadoCabecera','resumenAsistencia','tablaAsistenciaWrap'].forEach(id => {
    if (!diario) document.getElementById(id).classList.add('d-none');
  });
  document.getElementById('resumenPeriodo').classList.toggle('d-none', diario);
  document.getElementById('reportePeriodo').classList.toggle('d-none', diario);
  if (!diario) { document.getElementById('mensajeAsistencia').classList.add('d-none'); cargarVistaPeriodo(); }
  else cargarAsistencia();
}

async function cargarVistaPeriodo() {
  if (modoActual === 'dia') return cargarAsistencia();
  let desde, hasta;
  if (modoActual === 'semana') {
    const elegido = parseFechaLocal(document.getElementById('fechaSemana').value);
    if (!elegido) return;
    const lunes = new Date(elegido);
    const dia = (lunes.getDay() + 6) % 7;
    lunes.setDate(lunes.getDate() - dia);
    const domingo = new Date(lunes); domingo.setDate(lunes.getDate() + 6);
    desde = fechaLocalISO(lunes); hasta = fechaLocalISO(domingo);
  } else {
    const mes = document.getElementById('mesAsistencia').value;
    if (!/^\d{4}-\d{2}$/.test(mes)) return;
    const [anio, numeroMes] = mes.split('-').map(Number);
    desde = `${mes}-01`;
    hasta = fechaLocalISO(new Date(anio, numeroMes, 0));
  }
  document.getElementById('reportePeriodo').classList.remove('d-none');
  document.getElementById('reportePeriodo').innerHTML = '<div class="asistencia-empty">Cargando asistencias del periodo...</div>';
  const json = await llamarAsistencia('LISTARPERIODOASISTENCIA', { desde, hasta });
  if (!json.success) { Swal.fire('No se pudo cargar', json.message, 'error'); return; }
  asistenciasPeriodo = json.asistencias || [];
  renderizarPeriodo();
}

function renderizarPeriodo() {
  const termino = document.getElementById('buscarOperario').value.trim().toLocaleLowerCase('es');
  const dias = asistenciasPeriodo.map(asistencia => ({
    ...asistencia,
    operarios: (asistencia.operarios || []).filter(o => `${o.nombre_completo||''} ${o.apodo||''} ${o.cargo||''}`.toLocaleLowerCase('es').includes(termino)),
  })).filter(a => !termino || a.operarios.length > 0);
  const conteo = { P:0, A:0, T:0, D:0, F:0 };
  dias.forEach(a => a.operarios.forEach(o => { const estado=String(o.estado||'P').trim(); if(conteo[estado]!==undefined)conteo[estado]++; }));
  const total = Object.values(conteo).reduce((s,n)=>s+n,0);
  const resumen = document.getElementById('resumenPeriodo');
  const resumenDatos = [
    ['DÍAS CON REGISTRO', dias.length], ['OPERARIOS REGISTRADOS', total],
    ['ASISTIERON', conteo.A], ['TARDE', conteo.T], ['PENDIENTES', conteo.P],
    ['DESCANSO', conteo.D], ['FALTA', conteo.F],
  ];
  resumen.innerHTML = resumenDatos.map(([label,valor])=>`<div class="asistencia-stat"><strong>${valor}</strong><span>${label}</span></div>`).join('');
  resumen.classList.toggle('d-none', false);
  if (!dias.length) {
    document.getElementById('reportePeriodo').innerHTML = `<div class="asistencia-empty">${termino?'No se encontraron registros con esa búsqueda.':'No hay asistencias creadas dentro de este periodo.'}</div>`;
    return;
  }
  const labels={P:'Pendiente',A:'Asistió',T:'Tarde',D:'Descanso',F:'Falta'};
  document.getElementById('reportePeriodo').innerHTML = dias.map((a,indice)=>{
    const cuenta={P:0,A:0,T:0,D:0,F:0};
    a.operarios.forEach(o=>{const e=String(o.estado||'P').trim();if(cuenta[e]!==undefined)cuenta[e]++;});
    const badges=Object.entries(cuenta).map(([e,n])=>`<span class="asistencia-period-badge">${labels[e]}: ${n}</span>`).join('');
    const rows=a.operarios.map(o=>`<tr><td><button type="button" class="asistencia-operario-link" data-operario-id="${Number(o.operario_id)}">${escAsistencia(o.nombre_completo)}</button>${o.apodo?`<div class="asistencia-sub">${escAsistencia(o.apodo)}</div>`:''}</td><td>${escAsistencia(o.cargo||'-')}</td><td>${escAsistencia(labels[String(o.estado||'P').trim()]||o.estado||'Pendiente')}</td><td>${escAsistencia(o.comentario||'-')}</td></tr>`).join('');
    return `<details class="asistencia-period-day" ${indice===0?'open':''}><summary><span><span class="asistencia-period-title">${formatearFecha(a.fecha)} · ${escAsistencia(a.nombre)}</span><span class="asistencia-period-meta d-block">${a.operarios.length} operario(s) · creada ${formatearFechaHora(a.created_at)}</span></span></summary><div class="asistencia-period-badges">${badges}</div><div class="asistencia-period-table"><table><thead><tr><th>Operario</th><th>Cargo</th><th>Estado</th><th>Comentario</th></tr></thead><tbody>${rows}</tbody></table></div></details>`;
  }).join('');
  document.querySelectorAll('#reportePeriodo .asistencia-operario-link').forEach(boton=>boton.addEventListener('click',()=>verHistorialAsistenciaOperario(Number(boton.dataset.operarioId))));
}

function verHistorialAsistenciaOperario(operarioId) {
  const registros = asistenciasPeriodo.flatMap(asistencia => (asistencia.operarios || [])
    .filter(operario => Number(operario.operario_id) === operarioId)
    .map(operario => ({ ...operario, fecha_asistencia: asistencia.fecha, nombre_asistencia: asistencia.nombre })));
  if (!registros.length) {
    Swal.fire('Sin asistencias', 'Este operario no tiene registros en el periodo seleccionado.', 'info');
    return;
  }
  const primero = registros[0];
  const nombre = primero.nombre_completo || 'Operario';
  const labels = { P:'Pendiente', A:'Asistió', T:'Tarde', D:'Descanso', F:'Falta' };
  const resumen = { P:0, A:0, T:0, D:0, F:0 };
  registros.forEach(registro => { const estado=String(registro.estado||'P').trim(); if (resumen[estado] !== undefined) resumen[estado]++; });
  const html = `<div class="asistencia-personal-history"><p class="text-muted">${escAsistencia(primero.cargo||'')} · ${registros.length} día(s) en el periodo</p><div class="d-flex flex-wrap gap-2 mb-3">${Object.entries(resumen).map(([estado,cantidad])=>`<span class="asistencia-period-badge">${labels[estado]}: ${cantidad}</span>`).join('')}</div>${registros.map(registro=>{const estado=String(registro.estado||'P').trim();return `<article class="asistencia-personal-day"><div><div class="asistencia-personal-date">${formatearFechaLarga(registro.fecha_asistencia)}</div><div class="asistencia-sub">${escAsistencia(registro.nombre_asistencia||'Asistencia diaria')}</div>${registro.comentario?`<div class="mt-2">${escAsistencia(registro.comentario)}</div>`:''}</div><span class="asistencia-personal-status" data-estado="${escAsistencia(estado)}">${escAsistencia(labels[estado]||estado)}</span></article>`;}).join('')}</div>`;
  Swal.fire({ title: nombre, html, width: 760, confirmButtonText: 'Cerrar', customClass: { popup: 'asistencia-history-popup' } });
}

function formatearFechaLarga(valor) {
  if (!valor) return '-';
  const fecha = parseFechaLocal(String(valor).slice(0,10));
  if (!fecha) return formatearFecha(valor);
  return fecha.toLocaleDateString('es-PE', { weekday:'long', day:'numeric', month:'long', year:'numeric' });
}

function parseFechaLocal(valor) { if(!valor)return null; const [y,m,d]=valor.split('-').map(Number); return new Date(y,m-1,d); }
function fechaLocalISO(fecha) { return `${fecha.getFullYear()}-${String(fecha.getMonth()+1).padStart(2,'0')}-${String(fecha.getDate()).padStart(2,'0')}`; }

async function cargarAsistencia() {
  const fecha = document.getElementById('fechaAsistencia').value;
  if (!fecha) return;
  document.getElementById('mensajeAsistencia').textContent = 'Cargando asistencia...';
  document.getElementById('mensajeAsistencia').classList.remove('d-none');
  document.getElementById('tablaAsistenciaWrap').classList.add('d-none');
  const json = await llamarAsistencia('OBTENERASISTENCIA', { fecha });
  if (!json.success) { Swal.fire('No se pudo cargar', json.message, 'error'); return; }
  asistenciaActual = json.asistencia;
  operariosAsistencia = json.operarios || [];
  document.getElementById('btnAbrirAsistencia').classList.toggle('d-none', !!asistenciaActual);
  document.getElementById('btnGuardarAsistencia').classList.toggle('d-none', !asistenciaActual);
  const cabecera = document.getElementById('estadoCabecera');
  cabecera.classList.toggle('d-none', !asistenciaActual);
  if (!asistenciaActual) {
    document.getElementById('resumenAsistencia').classList.add('d-none');
    document.getElementById('mensajeAsistencia').innerHTML = '<i class="fa-regular fa-calendar-xmark"></i><br>No hay asistencia creada para esta fecha. Iníciala para generar el registro y cargar a los operarios activos como pendientes.';
    return;
  }
  cabecera.innerHTML = `<strong>${escAsistencia(asistenciaActual.nombre)}</strong><span class="text-muted"> · ${formatearFecha(asistenciaActual.fecha)} · Creada ${formatearFechaHora(asistenciaActual.created_at)}</span>`;
  actualizarResumen();
  renderizarOperarios();
}

async function iniciarAsistencia() {
  const fecha = document.getElementById('fechaAsistencia').value;
  if (!fecha) { Swal.fire('Selecciona una fecha', '', 'info'); return; }
  const json = await llamarAsistencia('CREARASISTENCIA', { fecha });
  if (!json.success) { Swal.fire('No se pudo iniciar', json.message, 'error'); return; }
  asistenciaActual = json.asistencia;
  operariosAsistencia = json.operarios || [];
  document.getElementById('btnAbrirAsistencia').classList.add('d-none');
  document.getElementById('btnGuardarAsistencia').classList.remove('d-none');
  const cabecera = document.getElementById('estadoCabecera');
  cabecera.classList.remove('d-none');
  cabecera.innerHTML = `<strong>${escAsistencia(asistenciaActual.nombre)}</strong><span class="text-muted"> · ${formatearFecha(asistenciaActual.fecha)} · Creada ahora</span>`;
  actualizarResumen();
  renderizarOperarios();
  Swal.fire('Asistencia lista', `${operariosAsistencia.length} operario(s) cargados como pendientes.`, 'success');
}

function actualizarResumen() {
  const cuenta = {P:0,A:0,T:0,D:0,F:0};
  operariosAsistencia.forEach(o => cuenta[String(o.estado||'P').trim()]++);
  const labels={P:'Pendientes',A:'Asistieron',T:'Tarde',D:'Descanso',F:'Falta'};
  const resumen=document.getElementById('resumenAsistencia');
  resumen.innerHTML=Object.entries(cuenta).map(([k,v])=>`<div class="asistencia-stat"><strong>${v}</strong><span>${labels[k]}</span></div>`).join('');
  resumen.classList.toggle('d-none',!asistenciaActual);
}

function renderizarOperarios() {
  const body=document.getElementById('asistenciaBody');
  const termino=document.getElementById('buscarOperario').value.trim().toLocaleLowerCase('es');
  const lista=operariosAsistencia.filter(o=>`${o.nombre_completo||''} ${o.ape_parteno||''} ${o.ape_marteno||''} ${o.apodo||''}`.toLocaleLowerCase('es').includes(termino));
  document.getElementById('tablaAsistenciaWrap').classList.toggle('d-none',!asistenciaActual);
  document.getElementById('mensajeAsistencia').classList.toggle('d-none',!!asistenciaActual && lista.length>0);
  if (!asistenciaActual) return;
  if (!lista.length) { document.getElementById('mensajeAsistencia').textContent='No hay operarios que coincidan con la búsqueda.'; document.getElementById('mensajeAsistencia').classList.remove('d-none'); }
  body.innerHTML=lista.map(o=>`<tr data-operario-id="${Number(o.operario_id)}">
    <td data-label="Operario"><div class="asistencia-name">${escAsistencia(o.nombre_completo)}</div>${o.apodo?`<div class="asistencia-sub">${escAsistencia(o.apodo)}</div>`:''}</td>
    <td data-label="Cargo">${escAsistencia(o.cargo||'-')}</td>
    <td data-label="Estado"><div class="asistencia-status-group" role="group" aria-label="Estado de asistencia de ${escAsistencia(o.nombre_completo)}">
      ${[['P','Pendiente'],['A','Asistió'],['T','Tarde'],['D','Descanso'],['F','Falta']].map(([estado,label])=>`<button type="button" class="asistencia-status-btn" data-estado="${estado}" aria-pressed="${String(o.estado||'P').trim()===estado}" title="Marcar ${label}">${label}</button>`).join('')}
    </div></td>
    <td data-label="Comentario"><input class="form-control asistencia-comment" maxlength="1000" placeholder="Comentario opcional" value="${escAsistencia(o.comentario||'')}"></td>
    <td data-label="Historial"><button type="button" class="btn btn-sm btn-outline-secondary" onclick="verHistorialOperario(${Number(o.operario_id)})"><i class="fa-solid fa-clock-rotate-left"></i> Ver (${contarMarcaciones(o.js_marcador)})</button></td>
  </tr>`).join('');
  body.querySelectorAll('.asistencia-status-btn').forEach(button=>button.addEventListener('click',()=>{
    const grupo=button.closest('.asistencia-status-group');
    grupo.querySelectorAll('.asistencia-status-btn').forEach(opcion=>opcion.setAttribute('aria-pressed',String(opcion===button)));
    marcarCambiosAsistencia();
  }));
  body.querySelectorAll('input').forEach(input=>input.addEventListener('input',marcarCambiosAsistencia));
}

function marcarCambiosAsistencia(){
  document.getElementById('btnGuardarAsistencia').classList.add('pc-btn-has-changes');
  actualizarResumenDesdeTabla();
}

function actualizarResumenDesdeTabla() {
  document.querySelectorAll('#asistenciaBody tr').forEach(tr=>{
    const id=Number(tr.dataset.operarioId), estado=tr.querySelector('.asistencia-status-btn[aria-pressed="true"]')?.dataset.estado||'P', comentario=tr.querySelector('input').value.trim();
    const obj=operariosAsistencia.find(x=>Number(x.operario_id)===id); if(obj){obj.estado=estado;obj.comentario=comentario;}
  });
  actualizarResumen();
}

async function guardarCambiosAsistencia() {
  if (!asistenciaActual) return;
  actualizarResumenDesdeTabla();
  const registros=operariosAsistencia.map(o=>({operario_id:Number(o.operario_id),estado:String(o.estado||'P').trim(),comentario:String(o.comentario||'').trim()}));
  const json=await llamarAsistencia('GUARDARASISTENCIA',{asistencia_id:asistenciaActual.id,registros:JSON.stringify(registros)});
  if(!json.success){Swal.fire('No se pudo guardar',json.message,'error');return;}
  operariosAsistencia=json.operarios||[];
  document.getElementById('btnGuardarAsistencia').classList.remove('pc-btn-has-changes');
  actualizarResumen(); renderizarOperarios();
  Swal.fire('Listo',json.message,'success');
}
function escAsistencia(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function decodificarMarcaciones(v){if(Array.isArray(v))return v;if(typeof v==='string'){try{const a=JSON.parse(v);return Array.isArray(a)?a:(a&&typeof a==='object'?[a]:[])}catch(_){return []}}return v&&typeof v==='object'?[v]:[];}
function contarMarcaciones(v){return decodificarMarcaciones(v).length;}
function verHistorialOperario(id){
  const operario=operariosAsistencia.find(o=>Number(o.operario_id)===Number(id));
  if(!operario)return;
  const historial=decodificarMarcaciones(operario.js_marcador);
  const estados={P:'Pendiente',A:'Asistió',T:'Tarde',D:'Descanso',F:'Falta'};
  const html=historial.length?`<div class="text-start">${historial.slice().reverse().map(m=>`<div class="border rounded p-3 mb-2"><div class="fw-semibold">${escAsistencia(estados[m.estado_anterior]||m.estado_anterior||'')} → ${escAsistencia(estados[m.estado_nuevo]||m.estado_nuevo||'')}</div><div class="small text-muted mt-1">${escAsistencia(m.nombre||m.usuario||'Usuario')} · ${escAsistencia(m.fecha_hora?new Date(m.fecha_hora).toLocaleString('es-PE'):'')}</div>${m.comentario?`<div class="mt-2">${escAsistencia(m.comentario)}</div>`:''}</div>`).join('')}</div>`:'<p class="text-muted mb-0">Todavía no hay cambios de asistencia registrados.</p>';
  Swal.fire({title:`Historial · ${escAsistencia(operario.nombre_completo)}`,html,width:560,confirmButtonText:'Cerrar'});
}
function formatearFecha(v){if(!v)return '-';const [y,m,d]=String(v).slice(0,10).split('-');return `${d}/${m}/${y}`;}
function formatearFechaHora(v){if(!v)return '-';return new Date(v).toLocaleString('es-PE',{dateStyle:'short',timeStyle:'short'});}
</script>
<?php require __DIR__ . '/footer.php'; ?>
