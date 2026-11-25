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
        if ($e->getCode() === '55P03') {
            responderAsistencia(false, 'La asistencia está siendo actualizada por otra persona. Espera unos segundos e inténtalo nuevamente.');
        }
        if ($e->getCode() === '57014') {
            responderAsistencia(false, 'El guardado tardó más de lo esperado y se canceló sin aplicar cambios. Inténtalo nuevamente.');
        }
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
        case 'MARCARSALIDA': marcarSalidaAsistencia($usuario); break;
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
               ra.operario_id, ra.estado, ra.comentario, ra.js_marcador,
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
                'js_marcador' => $fila['js_marcador'],
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
                'fecha_hora' => fechaHoraAsistencia(),
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
    $registrosValidos = [];
    foreach ($registros as $registro) {
        if (!is_array($registro)) continue;
        $operarioId = (int) ($registro['operario_id'] ?? 0);
        $estado = strtoupper(trim((string) ($registro['estado'] ?? 'P')));
        $comentario = trim((string) ($registro['comentario'] ?? ''));
        if ($operarioId <= 0 || !in_array($estado, $permitidos, true)) responderAsistencia(false, 'Hay un operario o estado inválido en el formulario.');
        if (mb_strlen($comentario, 'UTF-8') > 1000) responderAsistencia(false, 'Los comentarios admiten hasta 1000 caracteres.');
        // Si un operario aparece repetido en el POST, se conserva el último valor recibido.
        $registrosValidos[$operarioId] = [
            'operario_id' => $operarioId,
            'estado' => $estado,
            'comentario' => $comentario,
        ];
    }

    if (!$registrosValidos) {
        responderAsistencia(true, 'No hay cambios por guardar.', ['operarios' => listarDetalleAsistencia($db, $asistenciaId)]);
    }

    $fechaHora = fechaHoraAsistencia();
    $db->beginTransaction();
    try {
        // Las asistencias viven en una BD remota. Una consulta por cada operario
        // provocaba decenas de viajes de red y podía agotar el límite de PHP.
        // Se actualizan todos los cambios y se agrega el marcador en una sola consulta.
        $db->exec("SET LOCAL lock_timeout = '5s'");
        $db->exec("SET LOCAL statement_timeout = '20s'");
        $actualizados = executeQuery($db, "
            WITH datos AS (
                SELECT operario_id, estado, comentario
                FROM jsonb_to_recordset(CAST(:registros AS jsonb))
                    AS entrada(operario_id bigint, estado text, comentario text)
            )
            UPDATE public.rel_asistencia_operario AS ra
            SET estado = datos.estado,
                comentario = NULLIF(datos.comentario, ''),
                js_marcador = (
                    CASE
                        WHEN jsonb_typeof(ra.js_marcador) = 'array' THEN ra.js_marcador
                        WHEN jsonb_typeof(ra.js_marcador) = 'object' THEN jsonb_build_array(ra.js_marcador)
                        ELSE '[]'::jsonb
                    END
                ) || jsonb_build_array(
                    jsonb_build_object(
                        'usuario_id', CAST(:usuario_id AS integer),
                        'nombre', CAST(:usuario_nombre AS text),
                        'usuario', CAST(:usuario_login AS text),
                        'fecha_hora', CAST(:fecha_hora AS text),
                        'estado_anterior', COALESCE(NULLIF(BTRIM(ra.estado), ''), 'P'),
                        'estado_nuevo', datos.estado,
                        'comentario', datos.comentario
                    ) || CASE
                        WHEN datos.estado IN ('A', 'T')
                         AND COALESCE(NULLIF(BTRIM(ra.estado), ''), 'P') NOT IN ('A', 'T')
                        THEN jsonb_build_object('tipo_marcacion', 'ingreso')
                        ELSE '{}'::jsonb
                    END
                ),
                updated_at = NOW()
            FROM datos
            WHERE ra.asistencia_id = :asistencia_id
              AND ra.operario_id = datos.operario_id
              AND ra.deleted_at IS NULL
              AND (
                    BTRIM(COALESCE(ra.estado, '')) IS DISTINCT FROM datos.estado
                 OR COALESCE(ra.comentario, '') IS DISTINCT FROM datos.comentario
              )
            RETURNING ra.operario_id
        ", [
            'registros' => json_encode(array_values($registrosValidos), JSON_UNESCAPED_UNICODE),
            'usuario_id' => (int) $usuario['id'],
            'usuario_nombre' => (string) $usuario['nombre'],
            'usuario_login' => (string) $usuario['usuario'],
            'fecha_hora' => $fechaHora,
            'asistencia_id' => $asistenciaId,
        ]);
        $guardados = count($actualizados);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    responderAsistencia(true, $guardados ? "Asistencia guardada. {$guardados} registro(s) actualizado(s)." : 'No hay cambios por guardar.', ['operarios' => listarDetalleAsistencia($db, $asistenciaId)]);
}

function marcarSalidaAsistencia(array $usuario): void
{
    $asistenciaId = (int) ($_POST['asistencia_id'] ?? 0);
    $operarioId = (int) ($_POST['operario_id'] ?? 0);
    if ($asistenciaId <= 0 || $operarioId <= 0) responderAsistencia(false, 'La asistencia o el operario no son válidos.');

    $db = conectar_oll_BD();
    $db->beginTransaction();
    try {
        $actual = executeQuery($db, "
            SELECT estado, js_marcador
            FROM public.rel_asistencia_operario
            WHERE asistencia_id = :asistencia_id AND operario_id = :operario_id AND deleted_at IS NULL
            FOR UPDATE
        ", ['asistencia_id' => $asistenciaId, 'operario_id' => $operarioId]);
        if (!$actual) throw new RuntimeException('No se encontró el registro del operario para esta asistencia.');
        $estado = trim((string) ($actual[0]['estado'] ?? 'P'));
        if (!in_array($estado, ['A', 'T'], true)) throw new RuntimeException('Marca primero al operario como Asistió o Tarde.');

        $historial = $actual[0]['js_marcador'] ?? null;
        if (is_string($historial)) $historial = json_decode($historial, true);
        if (!is_array($historial)) $historial = [];
        elseif (!array_is_list($historial)) $historial = [$historial];

        $ingresos = [];
        $ultimaSalida = null;
        foreach ($historial as $marcador) {
            if (!is_array($marcador) || empty($marcador['fecha_hora'])) continue;
            $fechaMarcador = (string) $marcador['fecha_hora'];
            if (($marcador['tipo_marcacion'] ?? '') === 'ingreso'
                || (in_array(($marcador['estado_nuevo'] ?? ''), ['A', 'T'], true)
                    && !in_array(($marcador['estado_anterior'] ?? ''), ['A', 'T'], true))) {
                $ingresos[] = $fechaMarcador;
            }
            if (($marcador['tipo_marcacion'] ?? '') === 'salida'
                && ($ultimaSalida === null || $fechaMarcador > $ultimaSalida)) {
                $ultimaSalida = $fechaMarcador;
            }
        }
        if (!$ingresos) throw new RuntimeException('No hay una hora de ingreso registrada. Guarda primero el estado Asistió o Tarde.');
        sort($ingresos);
        $ultimoIngreso = end($ingresos);
        if ($ultimaSalida !== null && $ultimaSalida >= $ultimoIngreso) {
            throw new RuntimeException('La salida de hoy ya fue registrada.');
        }

        $marcadorSalida = array_merge([
            'tipo_marcacion' => 'salida',
            'fecha_hora' => fechaHoraAsistencia(),
            'estado' => $estado,
            'comentario' => '',
        ], [
            'usuario_id' => $usuario['id'],
            'nombre' => $usuario['nombre'],
            'usuario' => $usuario['usuario'],
        ]);
        $historial[] = $marcadorSalida;
        executeNonQuery($db, "
            UPDATE public.rel_asistencia_operario
            SET js_marcador = CAST(:js_marcador AS jsonb), updated_at = NOW()
            WHERE asistencia_id = :asistencia_id AND operario_id = :operario_id AND deleted_at IS NULL
        ", [
            'js_marcador' => json_encode($historial, JSON_UNESCAPED_UNICODE),
            'asistencia_id' => $asistenciaId,
            'operario_id' => $operarioId,
        ]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        if ($e instanceof RuntimeException) responderAsistencia(false, $e->getMessage());
        throw $e;
    }
    responderAsistencia(true, 'Salida registrada correctamente.', ['operarios' => listarDetalleAsistencia($db, $asistenciaId)]);
}

function fechaHoraAsistencia(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format(DateTimeInterface::ATOM);
}

function responderAsistencia(bool $success, string $message, array $extra = []): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}
