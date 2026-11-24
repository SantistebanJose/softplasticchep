<?php
require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
require_once __DIR__ . '/clssVerificarSession.php';
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '0');

if (isset($_POST['accion'])) {
    try {
        controladorAsistencia((string) $_POST['accion']);
    } catch (PDOException $e) {
        error_log('Error de base de datos en clssAsistencia.php: ' . $e->getMessage());
        responderAsistencia(false, 'Error de base de datos al procesar la asistencia.');
    } catch (Throwable $e) {
        error_log('Error inesperado en clssAsistencia.php: ' . $e->getMessage());
        responderAsistencia(false, 'Error inesperado al procesar la asistencia.');
    }
}

function controladorAsistencia(string $accion): void
{
    $usuario = exigirSesion();
    exigirRol($usuario, ['administrador', 'personal'], 'personal');
    switch ($accion) {
        case 'OBTENERASISTENCIA': obtenerAsistencia(); break;
        case 'CREARASISTENCIA': crearAsistencia($usuario); break;
        case 'GUARDARASISTENCIA': guardarAsistencia($usuario); break;
        case 'LISTARPERIODOASISTENCIA': listarPeriodoAsistencia(); break;
        default: responderAcceso(400, 'Acción no reconocida.');
    }
}

function listarPeriodoAsistencia(): void
{
    $desde = trim($_POST['desde'] ?? '');
    $hasta = trim($_POST['hasta'] ?? '');
    foreach ([$desde, $hasta] as $fecha) {
        $valor = DateTime::createFromFormat('!Y-m-d', $fecha);
        if (!$valor || $valor->format('Y-m-d') !== $fecha) responderAsistencia(false, 'El periodo seleccionado no es válido.');
    }
    if ($desde > $hasta) responderAsistencia(false, 'El inicio del periodo no puede ser posterior al final.');

    $db = conectar_oll_BD();
    $filas = executeQuery($db, "
        SELECT a.id AS asistencia_id, a.fecha, a.nombre AS asistencia_nombre,
               a.created_at AS asistencia_creada,
               ra.operario_id, ra.estado, ra.comentario,
               o.nombre_completo, o.apodo, c.nombre AS cargo
        FROM public.asistencia a
        LEFT JOIN public.rel_asistencia_operario ra
          ON ra.asistencia_id = a.id AND ra.deleted_at IS NULL
        LEFT JOIN public.operario o ON o.id = ra.operario_id
        LEFT JOIN public.cargo c ON c.id = o.cargo_id
        WHERE a.fecha BETWEEN :desde AND :hasta AND a.deleted_at IS NULL
        ORDER BY a.fecha DESC, o.nombre_completo
    ", ['desde' => $desde, 'hasta' => $hasta]);

    $asistencias = [];
    foreach ($filas as $fila) {
        $id = (int) $fila['asistencia_id'];
        if (!isset($asistencias[$id])) {
            $asistencias[$id] = [
                'id' => $id,
                'fecha' => $fila['fecha'],
                'nombre' => $fila['asistencia_nombre'],
                'created_at' => $fila['asistencia_creada'],
                'operarios' => [],
            ];
        }
        if ($fila['operario_id'] !== null) {
            $asistencias[$id]['operarios'][] = [
                'operario_id' => (int) $fila['operario_id'],
                'nombre_completo' => $fila['nombre_completo'],
                'apodo' => $fila['apodo'],
                'cargo' => $fila['cargo'],
                'estado' => trim((string) $fila['estado']),
                'comentario' => $fila['comentario'],
            ];
        }
    }
    responderAsistencia(true, 'OK', ['asistencias' => array_values($asistencias)]);
}

function fechaAsistenciaInput(): string
{
    $fecha = trim($_POST['fecha'] ?? '');
    $valor = DateTime::createFromFormat('!Y-m-d', $fecha);
    $errores = DateTime::getLastErrors();
    if (!$valor || $valor->format('Y-m-d') !== $fecha || ($errores !== false && ($errores['warning_count'] || $errores['error_count']))) {
        responderAsistencia(false, 'La fecha de asistencia no es válida.');
    }
    return $fecha;
}

function nombreAsistenciaDia(string $fecha): string
{
    $nombresMes = [1=>'ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
    $f = new DateTimeImmutable($fecha);
    return 'ASISTENCIA DEL DÍA ' . $f->format('d') . ' DE ' . $nombresMes[(int) $f->format('n')];
}

function obtenerAsistencia(): void
{
    $fecha = fechaAsistenciaInput();
    $db = conectar_oll_BD();
    $cabecera = executeQuery($db, 'SELECT id, fecha, nombre, js_session, created_at FROM public.asistencia WHERE fecha = :fecha AND deleted_at IS NULL ORDER BY id DESC LIMIT 1', ['fecha' => $fecha]);
    if (!$cabecera) responderAsistencia(true, 'No hay asistencia creada para esta fecha.', ['asistencia' => null, 'operarios' => []]);
    $asistencia = $cabecera[0];
    $operarios = listarDetalleAsistencia($db, (int) $asistencia['id']);
    responderAsistencia(true, 'OK', ['asistencia' => $asistencia, 'operarios' => $operarios]);
}

function crearAsistencia(array $usuario): void
{
    $fecha = fechaAsistenciaInput();
    $db = conectar_oll_BD();
    $db->beginTransaction();
    try {
        // Serializa aperturas simultáneas para evitar dos cabeceras del mismo día.
        executeQuery($db, "SELECT pg_advisory_xact_lock(hashtext('asistencia:' || :fecha))", ['fecha' => $fecha]);
        $existente = executeQuery($db, 'SELECT id FROM public.asistencia WHERE fecha = :fecha AND deleted_at IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE', ['fecha' => $fecha]);
        if ($existente) {
            $id = (int) $existente[0]['id'];
        } else {
            $actor = [
                'usuario_id' => $usuario['id'],
                'nombre' => $usuario['nombre'],
                'usuario' => $usuario['usuario'],
                'accion' => 'crear_asistencia',
                'fecha_hora' => date('c'),
            ];
            $insertada = executeQuery($db, "
                INSERT INTO public.asistencia (fecha, nombre, js_session, created_at)
                VALUES (:fecha, :nombre, CAST(:js_session AS jsonb), NOW())
                RETURNING id
            ", ['fecha' => $fecha, 'nombre' => nombreAsistenciaDia($fecha), 'js_session' => json_encode($actor, JSON_UNESCAPED_UNICODE)]);
            $id = (int) ($insertada[0]['id'] ?? 0);
        }

        executeNonQuery($db, "
            INSERT INTO public.rel_asistencia_operario (asistencia_id, operario_id, estado, js_marcador, created_at)
            SELECT :asistencia_id, o.id, 'P', '[]'::jsonb, NOW()
            FROM public.operario o
            WHERE o.deleted_at IS NULL AND COALESCE(o.activo, TRUE) = TRUE
              AND NOT EXISTS (
                SELECT 1 FROM public.rel_asistencia_operario ra
                WHERE ra.asistencia_id = :asistencia_id_check
                  AND ra.operario_id = o.id AND ra.deleted_at IS NULL
              )
        ", ['asistencia_id' => $id, 'asistencia_id_check' => $id]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }

    $cabecera = executeQuery($db, 'SELECT id, fecha, nombre, js_session, created_at FROM public.asistencia WHERE id = :id', ['id' => $id]);
    responderAsistencia(true, 'Asistencia lista.', ['asistencia' => $cabecera[0], 'operarios' => listarDetalleAsistencia($db, $id)]);
}

function listarDetalleAsistencia(PDO $db, int $asistenciaId): array
{
    return executeQuery($db, "
        SELECT ra.operario_id, ra.estado, ra.comentario, ra.js_marcador,
               o.nombre_completo, o.ape_parteno, o.ape_marteno, o.apodo,
               c.nombre AS cargo
        FROM public.rel_asistencia_operario ra
        JOIN public.operario o ON o.id = ra.operario_id
        LEFT JOIN public.cargo c ON c.id = o.cargo_id
        WHERE ra.asistencia_id = :asistencia_id AND ra.deleted_at IS NULL
        ORDER BY o.nombre_completo
    ", ['asistencia_id' => $asistenciaId]);
}

function guardarAsistencia(array $usuario): void
{
    $asistenciaId = (int) ($_POST['asistencia_id'] ?? 0);
    $registros = json_decode($_POST['registros'] ?? '[]', true);
    if ($asistenciaId <= 0 || !is_array($registros)) responderAsistencia(false, 'Los datos de asistencia no son válidos.');
    $db = conectar_oll_BD();
    $cabecera = executeQuery($db, 'SELECT id FROM public.asistencia WHERE id = :id AND deleted_at IS NULL', ['id' => $asistenciaId]);
    if (!$cabecera) responderAsistencia(false, 'No se encontró la asistencia del día.');

    $permitidos = ['P', 'A', 'T', 'D', 'F'];
    $actorBase = ['usuario_id' => $usuario['id'], 'nombre' => $usuario['nombre'], 'usuario' => $usuario['usuario']];
    $guardados = 0;
    $db->beginTransaction();
    try {
        foreach ($registros as $registro) {
            if (!is_array($registro)) continue;
            $operarioId = (int) ($registro['operario_id'] ?? 0);
            $estado = strtoupper(trim((string) ($registro['estado'] ?? 'P')));
            $comentario = trim((string) ($registro['comentario'] ?? ''));
            if ($operarioId <= 0 || !in_array($estado, $permitidos, true)) responderAsistencia(false, 'Hay un operario o estado inválido en el formulario.');
            if (mb_strlen($comentario, 'UTF-8') > 1000) responderAsistencia(false, 'Los comentarios admiten hasta 1000 caracteres.');

            $actual = executeQuery($db, "
                SELECT estado, comentario, js_marcador
                FROM public.rel_asistencia_operario
                WHERE asistencia_id = :asistencia_id AND operario_id = :operario_id AND deleted_at IS NULL
                FOR UPDATE
            ", ['asistencia_id' => $asistenciaId, 'operario_id' => $operarioId]);
            if (!$actual) continue;
            $anterior = $actual[0];
            $estadoAnterior = trim((string) ($anterior['estado'] ?? 'P'));
            $comentarioAnterior = (string) ($anterior['comentario'] ?? '');
            if ($estado === $estadoAnterior && $comentario === $comentarioAnterior) continue;

            $historial = $anterior['js_marcador'] ?? null;
            if (is_string($historial)) $historial = json_decode($historial, true);
            if (!is_array($historial)) $historial = [];
            elseif (!array_is_list($historial)) $historial = [$historial];
            $historial[] = array_merge($actorBase, [
                'fecha_hora' => date('c'),
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $estado,
                'comentario' => $comentario,
            ]);

            executeNonQuery($db, "
                UPDATE public.rel_asistencia_operario
                SET estado = :estado, comentario = :comentario,
                    js_marcador = CAST(:js_marcador AS jsonb), updated_at = NOW()
                WHERE asistencia_id = :asistencia_id AND operario_id = :operario_id AND deleted_at IS NULL
            ", [
                'estado' => $estado,
                'comentario' => $comentario !== '' ? $comentario : null,
                'js_marcador' => json_encode($historial, JSON_UNESCAPED_UNICODE),
                'asistencia_id' => $asistenciaId,
                'operario_id' => $operarioId,
            ]);
            $guardados++;
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    responderAsistencia(true, $guardados ? "Asistencia guardada. {$guardados} registro(s) actualizado(s)." : 'No hay cambios por guardar.', ['operarios' => listarDetalleAsistencia($db, $asistenciaId)]);
}

function responderAsistencia(bool $success, string $message, array $extra = []): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}
