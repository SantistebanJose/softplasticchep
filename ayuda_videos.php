<?php
require __DIR__ . '/controllers/bd.php';
require_once __DIR__ . '/controllers/clssAyudaVideo.php';
require_once __DIR__ . '/controllers/clssVerificarSession.php';

iniciarSesionSegura();
if (empty($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
$usuarioSesion = exigirSesion();
exigirRol($usuarioSesion, ['administrador']);

$pdo = conectar_oll_BD();
$controladorVideos = new ClssAyudaVideo($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $accion = (string)$_POST['accion'];
        if ($accion === 'listar') {
            echo json_encode(['ok' => true, 'videos' => $controladorVideos->listar()], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($accion === 'guardar') {
            $controladorVideos->guardar($_POST);
            echo json_encode(['ok'=>true, 'msg'=>'Video guardado correctamente.']);
            exit;
        }
        if ($accion === 'estado') {
            $activo = $controladorVideos->cambiarEstado((int)($_POST['id'] ?? 0));
            echo json_encode(['ok'=>true, 'msg'=>$activo ? 'Video activado.' : 'Video desactivado.']);
            exit;
        }
        if ($accion === 'eliminar') {
            $controladorVideos->eliminar((int)($_POST['id'] ?? 0));
            echo json_encode(['ok'=>true, 'msg'=>'Video eliminado.']);
            exit;
        }
        http_response_code(400);
        echo json_encode(['ok'=>false, 'msg'=>'Acción no reconocida.']);
    } catch (InvalidArgumentException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(422);
        echo json_encode(['ok'=>false, 'msg'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Error en ayuda_videos.php: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok'=>false, 'msg'=>'No se pudo procesar la solicitud. Revisa el registro del servidor.']);
    }
    exit;
}

$activePage = 'ayuda_videos';
$pageTitle = 'Videos de ayuda';
$pageSubtitle = 'Administra los tutoriales visibles para cada rol.';
require __DIR__ . '/header.php';
?>
<div class="pc-card">
    <div class="pc-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div><h2 class="mb-1">Videos de ayuda</h2><div class="text-muted">Asigna cada tutorial a uno o más roles y módulos.</div></div>
        <button class="pc-btn pc-btn-primary" type="button" onclick="abrirVideo()"><i class="fa-solid fa-plus"></i> Agregar video</button>
    </div>
    <div class="table-responsive">
        <table class="pc-table">
            <thead><tr><th>Video</th><th>Módulo</th><th>Visible para</th><th>Orden</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody id="videosBody"><tr><td colspan="6" class="text-center">Cargando videos...</td></tr></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalVideo" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <form id="formVideo">
      <div class="modal-header"><h5 class="modal-title" id="videoTituloModal">Agregar video de ayuda</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
      <div class="modal-body">
        <input type="hidden" name="id" id="videoId">
        <div class="mb-3"><label class="form-label" for="videoTitulo">Título *</label><input class="form-control" id="videoTitulo" name="titulo" maxlength="150" required></div>
        <div class="mb-3"><label class="form-label" for="videoUrl">Enlace de YouTube *</label><input class="form-control" id="videoUrl" name="youtube_url" type="url" placeholder="https://www.youtube.com/watch?v=..." required><div class="form-text">Se guardará automáticamente el ID del video.</div></div>
        <div class="mb-3"><label class="form-label" for="videoDescripcion">Descripción</label><textarea class="form-control" id="videoDescripcion" name="descripcion" rows="3"></textarea></div>
        <div class="row g-3 mb-3"><div class="col-md-8"><label class="form-label" for="videoModulo">Módulo *</label><select class="form-select" id="videoModulo" name="modulo" required><option value="general">General</option><option value="compras">Compras</option><option value="perfil">Perfil</option><option value="produccion">Producción</option><option value="ensamblaje">Ensamblaje</option><option value="empaquetado">Empaquetado</option><option value="reportes">Reportes</option></select></div><div class="col-md-4"><label class="form-label" for="videoOrden">Orden</label><input class="form-control" id="videoOrden" name="orden" type="number" step="1" value="0"></div></div>
        <fieldset><legend class="fs-6">Roles que podrán verlo *</legend><div class="d-flex flex-wrap gap-3"><label class="form-check"><input class="form-check-input" type="checkbox" name="roles[]" value="conductor"> <span class="form-check-label">Conductor</span></label><label class="form-check"><input class="form-check-input" type="checkbox" name="roles[]" value="operario"> <span class="form-check-label">Operario</span></label><label class="form-check"><input class="form-check-input" type="checkbox" name="roles[]" value="administrador"> <span class="form-check-label">Administrador</span></label></div></fieldset>
        <div class="alert alert-danger mt-3 d-none" id="videoError"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar video</button></div>
    </form>
  </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const modalAyudaVideo = new bootstrap.Modal(document.getElementById('modalVideo'));
let videosAyuda = [];
const escVideo = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
async function pedirVideos(accion, datos = {}) {
    const fd = new FormData(); fd.append('accion', accion);
    Object.entries(datos).forEach(([k,v]) => Array.isArray(v) ? v.forEach(x => fd.append(k, x)) : fd.append(k, v));
    const res = await fetch('ayuda_videos.php', {method:'POST', body:fd});
    const json = await res.json(); if (!res.ok || !json.ok) throw new Error(json.msg || 'No se pudo completar la solicitud.');
    return json;
}
async function cargarVideos() {
    const json = await pedirVideos('listar'); videosAyuda = json.videos;
    const tbody = document.getElementById('videosBody');
    if (!videosAyuda.length) { tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Aún no hay videos. Agrega el primero.</td></tr>'; return; }
    tbody.innerHTML = videosAyuda.map(v => `<tr>
      <td data-label="Video"><strong>${escVideo(v.titulo)}</strong><div class="small text-muted">${escVideo(v.descripcion || '')}</div><a href="https://www.youtube.com/watch?v=${encodeURIComponent(v.youtube_id)}" target="_blank" rel="noopener">Abrir en YouTube <i class="fa-solid fa-arrow-up-right-from-square"></i></a></td>
      <td data-label="Módulo">${escVideo(v.modulo)}</td><td data-label="Visible para">${(v.roles || []).map(r => `<span class="badge bg-light text-dark me-1">${escVideo(r)}</span>`).join('')}</td><td data-label="Orden">${Number(v.orden)}</td>
      <td data-label="Estado"><span class="badge ${v.activo === true || v.activo === 't' ? 'bg-success' : 'bg-secondary'}">${v.activo === true || v.activo === 't' ? 'Activo' : 'Inactivo'}</span></td>
      <td data-label="Acciones" class="text-nowrap"><button class="btn btn-sm btn-outline-primary me-1" onclick="editarVideo(${Number(v.id)})" title="Editar"><i class="fa-solid fa-pen"></i></button><button class="btn btn-sm btn-outline-secondary me-1" onclick="cambiarEstadoVideo(${Number(v.id)})" title="Activar o desactivar"><i class="fa-solid fa-power-off"></i></button><button class="btn btn-sm btn-outline-danger" onclick="eliminarVideo(${Number(v.id)})" title="Eliminar"><i class="fa-solid fa-trash"></i></button></td>
    </tr>`).join('');
}
function abrirVideo(video = null) {
    const form = document.getElementById('formVideo'); form.reset(); document.getElementById('videoError').classList.add('d-none');
    document.getElementById('videoId').value = video?.id || '';
    document.getElementById('videoTituloModal').textContent = video ? 'Editar video de ayuda' : 'Agregar video de ayuda';
    if (video) {
        document.getElementById('videoTitulo').value = video.titulo || '';
        document.getElementById('videoUrl').value = `https://www.youtube.com/watch?v=${video.youtube_id}`;
        document.getElementById('videoDescripcion').value = video.descripcion || '';
        document.getElementById('videoModulo').value = video.modulo || 'general';
        document.getElementById('videoOrden').value = video.orden ?? 0;
        (video.roles || []).forEach(r => { const cb = form.querySelector(`input[name="roles[]"][value="${r}"]`); if (cb) cb.checked = true; });
    }
    modalAyudaVideo.show();
}
function editarVideo(id) { const v = videosAyuda.find(x => Number(x.id) === id); if (v) abrirVideo(v); }
document.getElementById('formVideo').addEventListener('submit', async e => {
    e.preventDefault(); const form = e.currentTarget; const data = new FormData(form); data.append('accion', 'guardar');
    const errorBox = document.getElementById('videoError'); errorBox.classList.add('d-none');
    try { const res = await fetch('ayuda_videos.php', {method:'POST', body:data}); const json = await res.json(); if (!res.ok || !json.ok) throw new Error(json.msg); modalAyudaVideo.hide(); await cargarVideos(); }
    catch (err) { errorBox.textContent = err.message || 'No se pudo guardar el video.'; errorBox.classList.remove('d-none'); }
});
async function cambiarEstadoVideo(id) { try { await pedirVideos('estado', {id}); await cargarVideos(); } catch(e) { alert(e.message); } }
async function eliminarVideo(id) { if (!confirm('¿Eliminar este video y sus asignaciones de roles?')) return; try { await pedirVideos('eliminar', {id}); await cargarVideos(); } catch(e) { alert(e.message); } }
document.addEventListener('DOMContentLoaded', () => cargarVideos().catch(e => { document.getElementById('videosBody').innerHTML = `<tr><td colspan="6" class="text-danger text-center">${escVideo(e.message)}</td></tr>`; }));
</script>
<?php require __DIR__ . '/footer.php'; ?>
