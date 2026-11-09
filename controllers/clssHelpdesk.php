<?php
declare(strict_types=1);

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
require_once __DIR__ . '/clssVerificarSession.php';
// El helper compartido responde mediante responder(); en esta API traducimos
// sus errores a excepciones para mantener una única respuesta JSON.
if (!function_exists('responder')) {
    function responder(bool $ok, string $msg, array $extra = []): void
    {
        if (!$ok) throw new RuntimeException($msg);
    }
}
require_once __DIR__ . '/cloudinaryHelper.php';

function hd_json(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function hd_actor(): array
{
    iniciarSesionSegura();
    if (!empty($_SESSION['operario_id']) && empty($_SESSION['usuario_id'])) {
        return ['type'=>'operario','id'=>(int)$_SESSION['operario_id'],'name'=>(string)($_SESSION['operario_nombre'] ?? 'Operario')];
    }
    if (!empty($_SESSION['usuario_id'])) {
        $u = exigirSesion();
        return ['type'=>'usuario','id'=>(int)$u['id'],'name'=>(string)($u['nombre'] ?: 'Administrador'),'user'=>$u];
    }
    hd_json(401, ['success'=>false,'message'=>'Inicia sesión para usar soporte.']);
}

function hd_is_admin(array $actor): bool
{
    return $actor['type'] === 'usuario' && usuarioTieneRol($actor['user'], 'administrador');
}

function hd_event(PDO $db, int $ticketId, array $actor, ?string $state, string $description): void
{
    $q=$db->prepare('INSERT INTO helpdesk_evento(ticket_id,estado,actor_tipo,operario_id,usuario_id,actor_nombre,descripcion) VALUES(:ticket,:estado,:tipo,:op,:usr,:nombre,:descripcion)');
    $q->execute(['ticket'=>$ticketId,'estado'=>$state,'tipo'=>$actor['type'],'op'=>$actor['type']==='operario'?$actor['id']:null,'usr'=>$actor['type']==='usuario'?$actor['id']:null,'nombre'=>$actor['name'],'descripcion'=>$description]);
}

function hd_parse_attachments(array $files, array &$uploadedUrls): array
{
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $out = [];
    if (empty($files['name']) || !is_array($files['name'])) return $out;
    foreach ($files['name'] as $i=>$original) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        if (($files['error'][$i] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($files['size'][$i] ?? 0) > 5*1024*1024) throw new RuntimeException('Cada imagen debe pesar hasta 5 MB.');
        if (count($out) >= 5) throw new RuntimeException('Puedes adjuntar hasta 5 imágenes por mensaje.');
        $tmp = $files['tmp_name'][$i] ?? '';
        // fileinfo no está habilitado en todos los entornos locales de Apache;
        // getimagesize valida el contenido real de la imagen sin esa extensión.
        $info = is_uploaded_file($tmp) ? @getimagesize($tmp) : false;
        $mime = is_array($info) ? ($info['mime'] ?? false) : false;
        if (!$mime || !isset($allowed[$mime])) throw new RuntimeException('Adjunta solo imágenes JPG, PNG o WEBP.');
        $subida = subirImagenACloudinary($tmp, basename((string)$original), 'helpdesk');
        if (empty($subida['url'])) throw new RuntimeException('Cloudinary no devolvió la URL de la imagen.');
        $uploadedUrls[] = $subida['url'];
        $out[] = ['url'=>$subida['url'],'nombre'=>basename((string)$original)];
    }
    return $out;
}

function hd_route(): never
{
    $actor = hd_actor();
    $admin = hd_is_admin($actor);
    $db = conectar_oll_BD();
    $action = (string)($_REQUEST['action'] ?? 'list');
    $id = (int)($_REQUEST['id'] ?? 0);
    $ownerWhere = $admin ? '' : ($actor['type']==='operario'
        ? ' AND (t.operario_id = :owner_id OR EXISTS (SELECT 1 FROM helpdesk_participante hp WHERE hp.ticket_id=t.id AND hp.operario_id=:participant_id))'
        : ' AND t.usuario_id = :owner_id');
    $ownerParams = $admin ? [] : ($actor['type']==='operario'
        ? ['owner_id'=>$actor['id'],'participant_id'=>$actor['id']]
        : ['owner_id'=>$actor['id']]);
    $uploadedUrls = [];
    try {
        if ($action === 'create' && $_SERVER['REQUEST_METHOD']==='POST') {
            $title=trim((string)($_POST['titulo']??'')); $body=trim((string)($_POST['mensaje']??''));
            $category=(string)($_POST['categoria']??''); $priority=(string)($_POST['prioridad']??'media');
            $cats=['Producción','Ensamblaje','Empaquetado','Inventario','Acceso','Error del sistema','Otro'];
            if ($title==='' || mb_strlen($title)>180 || $body==='' || !in_array($category,$cats,true) || !in_array($priority,['baja','media','alta','urgente'],true)) hd_json(422,['success'=>false,'message'=>'Completa el asunto, el tema y la descripción.']);
            $files=hd_parse_attachments($_FILES['adjuntos']??[],$uploadedUrls);
            $db->beginTransaction();
            $q=$db->prepare("INSERT INTO helpdesk_ticket(operario_id,usuario_id,solicitante_nombre,categoria,titulo,prioridad) VALUES(:op,:usr,:nombre,:cat,:titulo,:prioridad) RETURNING id");
            $q->execute(['op'=>$actor['type']==='operario'?$actor['id']:null,'usr'=>$actor['type']==='usuario'?$actor['id']:null,'nombre'=>$actor['name'],'cat'=>$category,'titulo'=>$title,'prioridad'=>$priority]); $ticketId=(int)$q->fetchColumn();
            $q=$db->prepare("INSERT INTO helpdesk_mensaje(ticket_id,actor_tipo,operario_id,usuario_id,autor_nombre,mensaje,adjuntos) VALUES(:ticket,:tipo,:op,:usr,:nombre,:mensaje,CAST(:adjuntos AS jsonb))");
            $q->execute(['ticket'=>$ticketId,'tipo'=>$actor['type'],'op'=>$actor['type']==='operario'?$actor['id']:null,'usr'=>$actor['type']==='usuario'?$actor['id']:null,'nombre'=>$actor['name'],'mensaje'=>$body,'adjuntos'=>json_encode($files,JSON_UNESCAPED_UNICODE)]);
            // El mensaje inicial queda en la conversación y este evento alimenta el seguimiento del ticket.
            hd_event($db,$ticketId,$actor,'pendiente','Solicitud creada'); $db->commit();
            $uploadedUrls=[];
            hd_json(201,['success'=>true,'id'=>$ticketId,'message'=>'Consulta enviada.']);
        }
        if ($action === 'list') {
            $params=$ownerParams; $where=$ownerWhere;
            if (!$admin && $actor['type']==='usuario') hd_json(403,['success'=>false,'message'=>'Solo administradores pueden acceder a esta bandeja.']);
            if (!empty($_GET['estado']) && in_array($_GET['estado'],['pendiente','en_proceso','requiere_info','resuelto','cerrado'],true)) { $where.=' AND t.estado=:estado'; $params['estado']=$_GET['estado']; }
            $rows=executeQuery($db,"SELECT t.*, (SELECT COUNT(*) FROM helpdesk_mensaje m WHERE m.ticket_id=t.id) AS mensajes FROM helpdesk_ticket t WHERE 1=1 $where ORDER BY CASE t.estado WHEN 'pendiente' THEN 0 WHEN 'requiere_info' THEN 1 WHEN 'en_proceso' THEN 2 ELSE 3 END, t.updated_at DESC",$params);
            hd_json(200,['success'=>true,'tickets'=>$rows]);
        }
        if ($action === 'operators') {
            if (!$admin) hd_json(403,['success'=>false,'message'=>'Solo un administrador puede agregar participantes.']);
            if ($id < 1) hd_json(422,['success'=>false,'message'=>'Ticket inválido.']);
            $ticket=executeQuery($db,'SELECT id FROM helpdesk_ticket WHERE id=:id',['id'=>$id]);
            if (!$ticket) hd_json(404,['success'=>false,'message'=>'Consulta no encontrada.']);
            $ops=executeQuery($db,"SELECT o.id,o.nombre_completo,COALESCE(c.nombre,'') AS cargo FROM operario o LEFT JOIN cargo c ON c.id=o.cargo_id WHERE o.activo=true AND o.deleted_at IS NULL AND o.id NOT IN (SELECT hp.operario_id FROM helpdesk_participante hp WHERE hp.ticket_id=:ticket UNION SELECT COALESCE(t.operario_id,0) FROM helpdesk_ticket t WHERE t.id=:ticket_owner) ORDER BY o.nombre_completo",['ticket'=>$id,'ticket_owner'=>$id]);
            hd_json(200,['success'=>true,'operarios'=>$ops]);
        }
        if ($action === 'add_participant' && $_SERVER['REQUEST_METHOD']==='POST' && $id>0) {
            if (!$admin) hd_json(403,['success'=>false,'message'=>'Solo un administrador puede agregar participantes.']);
            $operarioId=(int)($_POST['operario_id']??0); if ($operarioId<1) hd_json(422,['success'=>false,'message'=>'Selecciona un operario.']);
            $db->beginTransaction();
            $ticket=executeQuery($db,'SELECT operario_id FROM helpdesk_ticket WHERE id=:id FOR UPDATE',['id'=>$id]);
            if (!$ticket) { $db->rollBack(); hd_json(404,['success'=>false,'message'=>'Consulta no encontrada.']); }
            if ((int)($ticket[0]['operario_id']??0)===$operarioId) { $db->rollBack(); hd_json(422,['success'=>false,'message'=>'Esa persona ya es quien creó la consulta.']); }
            $person=executeQuery($db,'SELECT nombre_completo FROM operario WHERE id=:id AND activo=true AND deleted_at IS NULL',['id'=>$operarioId]);
            if (!$person) { $db->rollBack(); hd_json(422,['success'=>false,'message'=>'El operario no existe o está inactivo.']); }
            $insert=$db->prepare('INSERT INTO helpdesk_participante(ticket_id,operario_id,agregado_por_usuario_id) VALUES(:ticket,:operario,:usuario) ON CONFLICT(ticket_id,operario_id) DO NOTHING');
            $insert->execute(['ticket'=>$id,'operario'=>$operarioId,'usuario'=>$actor['id']]);
            if ($insert->rowCount()>0) hd_event($db,$id,$actor,null,'Se agregó a '.$person[0]['nombre_completo'].' a la conversación');
            executeNonQuery($db,'UPDATE helpdesk_ticket SET updated_at=NOW() WHERE id=:id',['id'=>$id]);
            $db->commit(); hd_json(200,['success'=>true,'message'=>'Participante agregado.']);
        }
        if ($action === 'detail' && $id>0) {
            $rows=executeQuery($db,"SELECT t.* FROM helpdesk_ticket t WHERE t.id=:id $ownerWhere",['id'=>$id]+$ownerParams);
            if (!$rows) hd_json(404,['success'=>false,'message'=>'Consulta no encontrada.']);
            $messages=executeQuery($db,'SELECT * FROM helpdesk_mensaje WHERE ticket_id=:id ORDER BY created_at,id',['id'=>$id]);
            foreach ($messages as &$message) {
                $attachments = $message['adjuntos'] ?? [];
                if (is_string($attachments)) {
                    $decoded = json_decode($attachments, true);
                    $attachments = is_array($decoded) ? $decoded : [];
                }
                $message['adjuntos'] = is_array($attachments) ? $attachments : [];
            }
            unset($message);
            $events=executeQuery($db,'SELECT * FROM helpdesk_evento WHERE ticket_id=:id ORDER BY created_at,id',['id'=>$id]);
            $participants=executeQuery($db,"SELECT hp.operario_id,o.nombre_completo,c.nombre AS cargo,hp.created_at FROM helpdesk_participante hp JOIN operario o ON o.id=hp.operario_id LEFT JOIN cargo c ON c.id=o.cargo_id WHERE hp.ticket_id=:id ORDER BY hp.created_at,hp.id",['id'=>$id]);
            hd_json(200,['success'=>true,'ticket'=>$rows[0],'mensajes'=>$messages,'eventos'=>$events,'participantes'=>$participants,'admin'=>$admin]);
        }
        if ($action === 'reply' && $_SERVER['REQUEST_METHOD']==='POST' && $id>0) {
            $body=trim((string)($_POST['mensaje']??'')); if ($body==='') hd_json(422,['success'=>false,'message'=>'Escribe una respuesta.']);
            $db->beginTransaction();
            $rows=executeQuery($db,"SELECT t.id,t.estado FROM helpdesk_ticket t WHERE t.id=:id $ownerWhere FOR UPDATE",['id'=>$id]+$ownerParams);
            if (!$rows) { $db->rollBack(); hd_json(404,['success'=>false,'message'=>'Consulta no encontrada.']); }
            $files=hd_parse_attachments($_FILES['adjuntos']??[],$uploadedUrls);
            $q=$db->prepare("INSERT INTO helpdesk_mensaje(ticket_id,actor_tipo,operario_id,usuario_id,autor_nombre,mensaje,adjuntos) VALUES(:ticket,:tipo,:op,:usr,:nombre,:mensaje,CAST(:adjuntos AS jsonb))");
            $q->execute(['ticket'=>$id,'tipo'=>$actor['type'],'op'=>$actor['type']==='operario'?$actor['id']:null,'usr'=>$actor['type']==='usuario'?$actor['id']:null,'nombre'=>$actor['name'],'mensaje'=>$body,'adjuntos'=>json_encode($files,JSON_UNESCAPED_UNICODE)]);
            $oldState=$rows[0]['estado']; $newState=$oldState; if ($admin) $newState='en_proceso'; elseif ($newState==='requiere_info') $newState='en_proceso';
            $q=$db->prepare('UPDATE helpdesk_ticket SET estado=:estado,updated_at=NOW(),resolved_at=CASE WHEN :estado2 = \'resuelto\' THEN NOW() ELSE NULL END WHERE id=:id'); $q->execute(['estado'=>$newState,'estado2'=>$newState,'id'=>$id]);
            if ($newState !== $oldState) hd_event($db,$id,$actor,$newState,'Estado actualizado a '.str_replace('_',' ',$newState).' al responder');
            $db->commit();
            $uploadedUrls=[];
            hd_json(200,['success'=>true,'message'=>'Mensaje enviado.']);
        }
        if ($action === 'status' && $_SERVER['REQUEST_METHOD']==='POST' && $id>0) {
            if (!$admin) hd_json(403,['success'=>false,'message'=>'Solo un administrador puede cambiar el estado.']);
            $state=(string)($_POST['estado']??''); if (!in_array($state,['pendiente','en_proceso','requiere_info','resuelto','cerrado'],true)) hd_json(422,['success'=>false,'message'=>'Estado inválido.']);
            $db->beginTransaction();
            $prev=executeQuery($db,'SELECT estado FROM helpdesk_ticket WHERE id=:id FOR UPDATE',['id'=>$id]);
            if (!$prev) { $db->rollBack(); hd_json(404,['success'=>false,'message'=>'Consulta no encontrada.']); }
            executeNonQuery($db,'UPDATE helpdesk_ticket SET estado=:estado,updated_at=NOW(),resolved_at=CASE WHEN :estado2=\'resuelto\' THEN NOW() ELSE NULL END WHERE id=:id',['estado'=>$state,'estado2'=>$state,'id'=>$id]);
            if ($prev[0]['estado'] !== $state) hd_event($db,$id,$actor,$state,'Estado cambiado de '.str_replace('_',' ',$prev[0]['estado']).' a '.str_replace('_',' ',$state));
            $db->commit(); hd_json(200,['success'=>true]);
        }
        hd_json(400,['success'=>false,'message'=>'Solicitud no reconocida.']);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        foreach ($uploadedUrls as $url) borrarImagenCloudinary($url);
        error_log('Helpdesk: '.$e->getMessage());
        $message = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'No se pudo guardar la consulta. Revisa la conexión con la base de datos e inténtalo otra vez.';
        hd_json(500,['success'=>false,'message'=>$message]);
    }
}

if (basename((string)($_SERVER['SCRIPT_FILENAME']??'')) === basename(__FILE__)) hd_route();
