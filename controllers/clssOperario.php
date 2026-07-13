<?php

/**
 * controllers/clssOperario.php
 * Controlador del módulo de Operarios
 * Tabla real: operario (id, nombre_completo, cargo, activo, created_at,
 *             updated_at, deleted_at, js_session, js_historial)
 *
 * Soft delete vía deleted_at (mismo patrón que 'orden_produccion' / 'moldes').
 * La columna 'activo' se mantiene sincronizada con deleted_at por
 * compatibilidad con otros módulos que ya filtran por activo = true
 * (ej. buscarOperarios() en clssProduccion.php):
 *   - Desactivar -> activo = false, deleted_at = NOW()
 *   - Reactivar  -> activo = true,  deleted_at = NULL
 *
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

function controladorOperario($accion)
{
    switch ($accion) {
        case 'LISTAROPERARIOS':
            listarOperarios();
            break;
        case 'OBTENEROPERARIO':
            obtenerOperario(intval($_POST['id'] ?? 0));
            break;
        case 'GUARDAROPERARIO':
            guardarOperario();
            break;
        case 'ELIMINAROPERARIO':
            eliminarOperario();
            break;
        case 'REACTIVAROPERARIO':
            reactivarOperario();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// AUDITORÍA (idéntico patrón a clssOrdenProduccion.php)
// =============================================================================

function obtenerIpCliente(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        return trim($_SERVER['HTTP_X_REAL_IP']);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'N/A';
}

function obtenerMovimientoSesion(string $accion, array $cambios = []): array
{
    return [
        'usuario'   => $_SESSION['usuario_id'] ?? 'Sistema',
        'nombre'    => $_SESSION['nombre_usuario'] ?? 'Usuario Desconocido',
        'user'      => $_SESSION['user_usuario'] ?? 'N/A',
        'perfiles'  => $_SESSION['perfiles'] ?? 'N/A',
        'rol'       => $_SESSION['rol_usuario'] ?? 'N/A',
        'accion'    => $accion,
        'ip'        => obtenerIpCliente(),
        'cambios'   => $cambios,
        'timestamp' => date('Y-m-d H:i:s'),
    ];
}

function compararCambios(array $anterior, array $nuevo, array $mapaCampos): array
{
    $cambios = [];
    foreach ($mapaCampos as $campo => $etiqueta) {
        $valorAntes   = $anterior[$campo] ?? null;
        $valorDespues = $nuevo[$campo]    ?? null;

        $antesComp   = ($valorAntes   === '' ? null : $valorAntes);
        $despuesComp = ($valorDespues === '' ? null : $valorDespues);

        if ($antesComp !== $despuesComp) {
            $cambios[] = [
                'campo'         => $etiqueta,
                'valor_antes'   => $valorAntes   ?? '(vacío)',
                'valor_despues' => $valorDespues ?? '(vacío)',
            ];
        }
    }
    return $cambios;
}

function registrarMovimiento($conectar, int $id, string $accion, array $cambios): void
{
    $movimiento         = obtenerMovimientoSesion($accion, $cambios);
    $js_session         = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial_nuevo = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeQuery($conectar, "
        UPDATE operario SET
            js_session   = :js_session,
            js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id
    ", [
        'id'           => $id,
        'js_session'   => $js_session,
        'js_historial' => $js_historial_nuevo,
    ]);
}

// =============================================================================
// OPERARIOS
// =============================================================================

function listarOperarios()
{
    $conectar = conectar_oll_BD();

    $texto       = trim($_POST['texto'] ?? '');
    $visibilidad = trim($_POST['visibilidad'] ?? 'activas'); // activas, eliminadas, todas

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(nombre_completo) LIKE LOWER(:texto) OR LOWER(cargo) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }
    if ($visibilidad === 'eliminadas') {
        $where[] = "deleted_at IS NOT NULL";
    } elseif ($visibilidad !== 'todas') {
        $where[] = "deleted_at IS NULL";
    }

    $sql = "SELECT * FROM operario
            WHERE " . implode(' AND ', $where) . "
            ORDER BY nombre_completo";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['operarios' => $result]);
}

function obtenerOperario($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $result = executeQuery($conectar, "SELECT * FROM operario WHERE id = :id", ['id' => $id]);
    if (empty($result)) responder(false, 'Operario no encontrado.');
    responder(true, 'OK', ['operario' => $result[0]]);
}

function guardarOperario()
{
    $conectar = conectar_oll_BD();

    $id              = intval($_POST['id'] ?? 0);
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $cargo           = trim($_POST['cargo'] ?? '');

    if (empty($nombre_completo)) responder(false, 'El nombre completo es obligatorio.');

    $mapaCampos = [
        'nombre_completo' => 'Nombre completo',
        'cargo'           => 'Cargo',
    ];

    $datosNuevos = [
        'nombre_completo' => $nombre_completo,
        'cargo'           => $cargo !== '' ? $cargo : null,
    ];

    if ($id === 0) {
        $cambios = compararCambios([], $datosNuevos, $mapaCampos);

        $movimiento         = obtenerMovimientoSesion('crear', $cambios);
        $js_session         = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial_nuevo = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        $result = executeQuery($conectar, "
            INSERT INTO operario
                (nombre_completo, cargo, activo, created_at, js_session, js_historial)
            VALUES
                (:nombre_completo, :cargo, true, NOW(), :js_session, :js_historial)
            RETURNING id
        ", [
            'nombre_completo' => $datosNuevos['nombre_completo'],
            'cargo'           => $datosNuevos['cargo'],
            'js_session'      => $js_session,
            'js_historial'    => $js_historial_nuevo,
        ]);
        $nuevo_id = $result[0]['id'] ?? null;
        responder(true, 'Operario creado correctamente.', ['id' => $nuevo_id, 'modo' => 'crear']);
    } else {
        $actual = executeQuery($conectar, "SELECT * FROM operario WHERE id = :id", ['id' => $id]);
        if (empty($actual)) responder(false, 'Operario no encontrado.');
        $registroAnterior = $actual[0];

        if (!empty($registroAnterior['deleted_at'])) {
            responder(false, 'No puedes editar un operario inactivo. Reactívalo primero.');
        }

        $cambios = compararCambios($registroAnterior, $datosNuevos, $mapaCampos);

        $movimiento         = obtenerMovimientoSesion('editar', $cambios);
        $js_session         = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial_nuevo = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeQuery($conectar, "
            UPDATE operario SET
                nombre_completo = :nombre_completo,
                cargo           = :cargo,
                updated_at      = NOW(),
                js_session      = :js_session,
                js_historial    = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id
        ", [
            'nombre_completo' => $datosNuevos['nombre_completo'],
            'cargo'           => $datosNuevos['cargo'],
            'id'              => $id,
            'js_session'      => $js_session,
            'js_historial'    => $js_historial_nuevo,
        ]);
        responder(true, 'Operario actualizado correctamente.', ['id' => $id, 'modo' => 'editar']);
    }
}

function eliminarOperario()
{
    $conectar = conectar_oll_BD();
    $id = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $actual = executeQuery($conectar, "SELECT deleted_at FROM operario WHERE id = :id", ['id' => $id]);
    if (empty($actual)) responder(false, 'Operario no encontrado.');
    if (!empty($actual[0]['deleted_at'])) responder(false, 'Este operario ya estaba inactivo.');

    executeQuery($conectar, "
        UPDATE operario SET
            activo     = false,
            deleted_at = NOW(),
            updated_at = NOW()
        WHERE id = :id
    ", ['id' => $id]);

    $cambios = [[
        'campo' => 'Estado', 'valor_antes' => 'Activo', 'valor_despues' => 'Inactivo',
    ]];
    registrarMovimiento($conectar, $id, 'desactivar', $cambios);

    responder(true, 'Operario desactivado correctamente.');
}

function reactivarOperario()
{
    $conectar = conectar_oll_BD();
    $id = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $actual = executeQuery($conectar, "SELECT deleted_at FROM operario WHERE id = :id", ['id' => $id]);
    if (empty($actual)) responder(false, 'Operario no encontrado.');
    if (empty($actual[0]['deleted_at'])) responder(false, 'Este operario ya estaba activo.');

    executeQuery($conectar, "
        UPDATE operario SET
            activo     = true,
            deleted_at = NULL,
            updated_at = NOW()
        WHERE id = :id
    ", ['id' => $id]);

    $cambios = [[
        'campo' => 'Estado', 'valor_antes' => 'Inactivo', 'valor_despues' => 'Activo',
    ]];
    registrarMovimiento($conectar, $id, 'reactivar', $cambios);

    responder(true, 'Operario reactivado correctamente.');
}

// =============================================================================
// HELPER
// =============================================================================

function responder(bool $ok, string $msg, array $extra = []): void
{
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}

// =============================================================================
// DISPATCH
// =============================================================================

if (isset($_POST["accion"])) {
    controladorOperario($_POST["accion"]);
}