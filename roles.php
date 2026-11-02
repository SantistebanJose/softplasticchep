<?php
require __DIR__ . '/controllers/bd.php';
require __DIR__ . '/controllers/executeQuery.php';
require_once __DIR__ . '/controllers/clssVerificarSession.php';
iniciarSesionSegura();
$usuarioSesion = exigirSesion();
exigirRol($usuarioSesion, ['administrador'], 'roles');
$esAdministradorReal = strtolower(trim((string)($usuarioSesion['rol'] ?? ''))) === 'administrador';
$pdo = conectar_oll_BD();

function decodificarRolesUsuario($valor): array {
    if (is_array($valor)) return $valor;
    if (!is_string($valor) || $valor === '') return [];
    $json = json_decode($valor, true);
    return is_array($json) ? $json : [];
}
function cargarCatalogoRoles(PDO $pdo): array {
    $usuarios = $pdo->query("SELECT rol_y_perfiles FROM usuario ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($usuarios as $valor) {
        $datos = decodificarRolesUsuario($valor);
        if (!empty($datos['catalogo_roles']) && is_array($datos['catalogo_roles'])) return $datos['catalogo_roles'];
    }
    $nombres = [
        'administrador'=>'Administrador', 'operario'=>'Operario', 'conductor'=>'Conductor',
        'produccion'=>'Producción', 'compras'=>'Compras', 'ventas'=>'Ventas', 'consulta'=>'Consulta',
    ];
    $porCodigo = [];
    foreach ($usuarios as $valor) {
        $datos = decodificarRolesUsuario($valor);
        $codigo = strtolower(trim((string)($datos['rol'] ?? '')));
        if ($codigo === '') continue;
        $perfiles = is_array($datos['perfiles'] ?? null) ? $datos['perfiles'] : [];
        $porCodigo[$codigo] ??= [
            'codigo'=>$codigo,
            'nombre'=>$nombres[$codigo] ?? mb_convert_case(str_replace(['_', '-'], ' ', $codigo), MB_CASE_TITLE, 'UTF-8'),
            'descripcion'=>'Rol existente en usuario.rol_y_perfiles',
            'permisos'=>array_values(array_intersect($perfiles, ['produccion','compras','ventas','consulta'])),
            'activo'=>true,
            'sistema'=>in_array($codigo, ['administrador','operario','conductor'], true),
        ];
    }
    ksort($porCodigo);
    $catalogo = array_values($porCodigo);
    foreach ($catalogo as $i => &$rol) $rol['id'] = $i + 1;
    unset($rol);
    return $catalogo;
}
function guardarCatalogoRoles(PDO $pdo, array $catalogo): void {
    $usuarios = $pdo->query("SELECT id, rol_y_perfiles FROM usuario ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    if (!$usuarios) throw new RuntimeException('No hay usuarios donde guardar el catálogo de roles.');
    $propiaTransaccion = !$pdo->inTransaction();
    if ($propiaTransaccion) $pdo->beginTransaction();
    try {
        $actualizar = $pdo->prepare("UPDATE usuario SET rol_y_perfiles=CAST(:datos AS jsonb), updated_at=NOW() WHERE id=:id");
        foreach ($usuarios as $usuario) {
            $datos = decodificarRolesUsuario($usuario['rol_y_perfiles'] ?? null);
            $datos['catalogo_roles'] = $catalogo;
            $actualizar->execute(['datos'=>json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'id'=>$usuario['id']]);
        }
        if ($propiaTransaccion) $pdo->commit();
    } catch (Throwable $e) {
        if ($propiaTransaccion && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

// Perfil acordado para Subgerencia: acceso general con Ventas bloqueado.
$catalogoInicial = cargarCatalogoRoles($pdo);
$existeSubgerencia = false;
foreach ($catalogoInicial as $rolInicial) {
    if (strtolower((string)($rolInicial['codigo'] ?? '')) === 'custom_subgerencia'
        || strtolower(trim((string)($rolInicial['nombre'] ?? ''))) === 'subgerencia') {
        $existeSubgerencia = true;
        break;
    }
}
if (!$existeSubgerencia) {
    $nuevoId = count($catalogoInicial) ? max(array_map(static fn($r)=>(int)($r['id']??0), $catalogoInicial)) + 1 : 1;
    $catalogoInicial[] = [
        'id'=>$nuevoId, 'codigo'=>'custom_subgerencia', 'nombre'=>'Subgerencia',
        'descripcion'=>'Acceso general al sistema, excepto módulos de Ventas.',
        'permisos'=>['administrador'], 'exclusiones'=>['ventas'], 'activo'=>true, 'sistema'=>false,
    ];
    guardarCatalogoRoles($pdo, $catalogoInicial);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $accion = (string)$_POST['accion'];
        if ($accion === 'listar') {
            echo json_encode(['ok' => true, 'roles' => cargarCatalogoRoles($pdo)], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($accion === 'guardar') {
            $id = (int)($_POST['id'] ?? 0);
            $nombre = trim((string)($_POST['nombre'] ?? ''));
            $descripcion = trim((string)($_POST['descripcion'] ?? ''));
            $permisos = $_POST['roles_permitidos'] ?? [];
            if (!is_array($permisos)) $permisos = [];
            $permitidos = ['produccion', 'compras', 'ventas', 'consulta', 'administrador'];
            $permisos = array_values(array_unique(array_intersect($permitidos, $permisos)));
            $exclusiones = $_POST['exclusiones'] ?? [];
            if (!is_array($exclusiones)) $exclusiones = [];
            $exclusiones = array_values(array_unique(array_intersect(['ventas', 'usuarios', 'roles'], $exclusiones)));
            if ($nombre === '' || mb_strlen($nombre) > 100) throw new InvalidArgumentException('Ingresa un nombre de rol (máximo 100 caracteres).');
            if (!$permisos) throw new InvalidArgumentException('Selecciona al menos un permiso de acceso.');
            if (in_array('administrador', $permisos, true) && !$esAdministradorReal) {
                throw new InvalidArgumentException('Solo un Administrador puede crear o cambiar perfiles con acceso general.');
            }
            if (in_array('administrador', $permisos, true) && !$exclusiones) {
                throw new InvalidArgumentException('Un perfil de acceso general debe tener al menos una restricción.');
            }
            if (mb_strlen($descripcion) > 255) throw new InvalidArgumentException('La descripción no puede superar 255 caracteres.');
            $slug = strtolower(strtr($nombre, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n']));
            $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
            $codigo = 'custom_' . trim($slug, '_');
            if (strlen($codigo) > 100) $codigo = substr($codigo, 0, 100);
            $catalogo = cargarCatalogoRoles($pdo);
            if ($id > 0) {
                $indice = array_search($id, array_column($catalogo, 'id'));
                if ($indice === false) throw new InvalidArgumentException('El rol ya no existe.');
                $codigo = $catalogo[$indice]['codigo'];
                if (!$esAdministradorReal && $codigo === strtolower(trim((string)($usuarioSesion['rol'] ?? '')))) {
                    throw new InvalidArgumentException('No puedes modificar tu propio perfil de acceso. Pide el cambio a un Administrador.');
                }
                $activo = $catalogo[$indice]['activo'] ?? true;
                $catalogo[$indice] = ['id'=>$id, 'codigo'=>$codigo, 'nombre'=>$nombre,
                    'descripcion'=>$descripcion ?: null, 'permisos'=>$permisos, 'exclusiones'=>$exclusiones, 'activo'=>$activo, 'sistema'=>false];
            } else {
                if (in_array($nombre, array_column($catalogo, 'nombre'))) throw new InvalidArgumentException('Ya existe un rol con ese nombre.');
                $codigos = array_column($catalogo, 'codigo');
                if (in_array($codigo, $codigos, true)) $codigo .= '_' . bin2hex(random_bytes(3));
                $nuevoId = count($catalogo) ? max(array_map(static fn($r)=>(int)($r['id']??0), $catalogo)) + 1 : 1;
                $catalogo[] = ['id'=>$nuevoId, 'codigo'=>$codigo, 'nombre'=>$nombre,
                    'descripcion'=>$descripcion ?: null, 'permisos'=>$permisos, 'exclusiones'=>$exclusiones, 'activo'=>true, 'sistema'=>false];
            }
            guardarCatalogoRoles($pdo, $catalogo);
            echo json_encode(['ok'=>true,'msg'=>'Rol guardado correctamente.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($accion === 'estado') {
            $id = (int)($_POST['id'] ?? 0);
            $activo = ($_POST['activo'] ?? '') === 'true';
            if ($id <= 0) throw new InvalidArgumentException('Rol inválido.');
            $catalogo = cargarCatalogoRoles($pdo);
            $indice = array_search($id, array_column($catalogo, 'id'));
            if ($indice === false) throw new InvalidArgumentException('El rol ya no existe.');
            if (!empty($catalogo[$indice]['sistema'])) throw new InvalidArgumentException('Los roles base del sistema no se pueden desactivar.');
            if (!$activo) {
                $asignados = $pdo->prepare("SELECT 1 FROM usuario WHERE rol_y_perfiles->>'rol'=:codigo LIMIT 1");
                $asignados->execute(['codigo'=>$catalogo[$indice]['codigo']]);
                if ($asignados->fetchColumn()) throw new InvalidArgumentException('No se puede desactivar mientras haya usuarios asignados a este rol.');
            }
            $catalogo[$indice]['activo'] = $activo;
            guardarCatalogoRoles($pdo, $catalogo);
            echo json_encode(['ok'=>true,'msg'=>$activo?'Rol activado.':'Rol desactivado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        throw new InvalidArgumentException('Acción no válida.');
    } catch (Throwable $e) {
        http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
        echo json_encode(['ok'=>false,'msg'=>$e instanceof InvalidArgumentException ? $e->getMessage() : 'No se pudo completar la operación.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$pageTitle = 'Roles';
$pageSubtitle = 'Revisa los roles existentes y asigna permisos';
$activePage = 'roles';
include 'header.php';
?>
<div class="pc-card">
  <div class="pc-card-header d-flex justify-content-between align-items-center"><h2>Roles</h2><button class="pc-btn pc-btn-primary" onclick="nuevoRol()"><i class="fa-solid fa-plus"></i> Nuevo rol</button></div>
  <p class="text-muted">Combina permisos de áreas o crea perfiles de acceso general con restricciones explícitas. Solo un Administrador puede crear o editar estos perfiles generales.</p>
  <div class="table-responsive"><table class="pc-table"><thead><tr><th>Rol</th><th>Permisos</th><th>Descripción</th><th>Estado</th><th>Acciones</th></tr></thead><tbody id="rolesFilas"><tr><td colspan="5">Cargando…</td></tr></tbody></table></div>
</div>
<div class="modal fade" id="modalRol" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form id="formRol"><div class="modal-header"><h5 id="rolTitulo" class="modal-title">Nuevo rol</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
  <input type="hidden" id="rolId"><label class="form-label" for="rolNombre">Nombre del rol *</label><input class="form-control mb-3" id="rolNombre" maxlength="100" required>
  <label class="form-label" for="rolDescripcion">Descripción</label><input class="form-control mb-3" id="rolDescripcion" maxlength="255">
  <fieldset><legend class="fs-6">Permisos *</legend><div class="form-text mb-2">Selecciona las áreas que podrá consultar o gestionar.</div>
    <label class="form-check"><input class="form-check-input rolPermiso" type="checkbox" value="administrador"> Acceso general tipo administrador</label>
    <label class="form-check"><input class="form-check-input rolPermiso" type="checkbox" value="produccion"> Producción y operaciones</label>
    <label class="form-check"><input class="form-check-input rolPermiso" type="checkbox" value="compras"> Compras y proveedores</label>
    <label class="form-check"><input class="form-check-input rolPermiso" type="checkbox" value="ventas"> Ventas</label>
    <label class="form-check"><input class="form-check-input rolPermiso" type="checkbox" value="consulta"> Consultas y reportes</label>
  </fieldset>
  <fieldset class="mt-3"><legend class="fs-6">Restringir módulos</legend><div class="form-text mb-2">Aplica principalmente a perfiles de acceso general.</div>
    <label class="form-check"><input class="form-check-input rolExclusion" type="checkbox" value="ventas"> Ventas (venta rápida, listado e informes)</label>
    <label class="form-check"><input class="form-check-input rolExclusion" type="checkbox" value="usuarios"> Usuarios</label>
    <label class="form-check"><input class="form-check-input rolExclusion" type="checkbox" value="roles"> Roles y permisos</label>
  </fieldset><div class="alert alert-warning mt-3 mb-0">No incluyas información sensible en el nombre o descripción del rol.</div>
</div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Guardar rol</button></div></form></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const rolModal = new bootstrap.Modal(document.getElementById('modalRol'));
const rolesBaseNombre = {administrador:'Acceso general',produccion:'Producción y operaciones',compras:'Compras y proveedores',ventas:'Ventas',consulta:'Consultas y reportes'};
async function llamarRoles(data){const r=await fetch('roles.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(data)});const j=await r.json();if(!j.ok)throw new Error(j.msg);return j}
async function cargarRoles(){try{const j=await llamarRoles({accion:'listar'});document.getElementById('rolesFilas').innerHTML=j.roles.length?j.roles.map(r=>`<tr><td>${esc(r.nombre)}</td><td>${(r.permisos||[]).map(p=>esc(rolesBaseNombre[p]||p)).join(', ')|| (r.sistema?'Acceso propio del sistema':'—')}${(r.exclusiones||[]).length?`<div class="small text-danger mt-1">Excepto: ${r.exclusiones.map(x=>esc({ventas:'Ventas',usuarios:'Usuarios',roles:'Roles y permisos'}[x]||x)).join(', ')}</div>`:''}</td><td>${esc(r.descripcion||'—')}</td><td>${r.activo?'Activo':'Inactivo'}</td><td>${r.sistema?'<span class="text-muted">Base del sistema</span>':`<button class="pc-icon-btn" onclick="editarRol(${Number(r.id)})" title="Editar"><i class="fa-solid fa-pen"></i></button><button class="pc-icon-btn" onclick="cambiarEstadoRol(${Number(r.id)},${!r.activo})" title="${r.activo?'Desactivar':'Activar'}"><i class="fa-solid fa-${r.activo?'ban':'rotate-left'}"></i></button>`}</td></tr>`).join(''):'<tr><td colspan="5">Aún no hay roles en los registros de usuario.</td></tr>'}catch(e){Swal.fire('Error',e.message,'error')}}
function esc(v){const d=document.createElement('div');d.textContent=v??'';return d.innerHTML}
function nuevoRol(){document.getElementById('formRol').reset();document.getElementById('rolId').value='';document.getElementById('rolTitulo').textContent='Nuevo rol';rolModal.show()}
async function editarRol(id){const j=await llamarRoles({accion:'listar'});const r=j.roles.find(x=>Number(x.id)===id);if(!r)return;document.getElementById('rolId').value=r.id;document.getElementById('rolNombre').value=r.nombre;document.getElementById('rolDescripcion').value=r.descripcion||'';document.querySelectorAll('.rolPermiso').forEach(c=>c.checked=(r.permisos||[]).includes(c.value));document.querySelectorAll('.rolExclusion').forEach(c=>c.checked=(r.exclusiones||[]).includes(c.value));document.getElementById('rolTitulo').textContent='Editar rol';rolModal.show()}
async function cambiarEstadoRol(id,activo){try{await llamarRoles({accion:'estado',id,activo:String(activo)});await cargarRoles()}catch(e){Swal.fire('No se pudo cambiar el estado',e.message,'warning')}}
document.getElementById('formRol').addEventListener('submit',async e=>{e.preventDefault();try{const data={accion:'guardar',id:document.getElementById('rolId').value,nombre:document.getElementById('rolNombre').value.trim(),descripcion:document.getElementById('rolDescripcion').value.trim()};document.querySelectorAll('.rolPermiso:checked').forEach((c,i)=>data[`roles_permitidos[${i}]`]=c.value);document.querySelectorAll('.rolExclusion:checked').forEach((c,i)=>data[`exclusiones[${i}]`]=c.value);await llamarRoles(data);rolModal.hide();await cargarRoles();Swal.fire('Guardado','El rol quedó actualizado.','success')}catch(err){Swal.fire('No se pudo guardar',err.message,'warning')}});
cargarRoles();
</script>
<?php include 'footer.php'; ?>
