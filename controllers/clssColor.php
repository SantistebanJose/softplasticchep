<?php

/**
 * controllers/clssColor.php
 * Controlador del módulo de Colores
 * Tabla real: color (id, nombre, descripcion, rgb, js_session, js_historial,
 *             created_at, update_at, deleted_at)
 * Soft delete vía deleted_at.
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    controladorColor($_POST["accion"]);
}

function controladorColor($accion)
{
    switch ($accion) {
        case 'LISTARCOLORES':
            listarColores();
            break;
        case 'OBTENERCOLOR':
            obtenerColor(intval($_POST['id'] ?? 0));
            break;
        case 'GUARDARCOLOR':
            guardarColor();
            break;
        case 'ELIMINARCOLOR':
            eliminarColor();
            break;
        case 'REACTIVARCOLOR':
            reactivarColor();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// COLOR
// =============================================================================

function listarColores()
{
    $conectar = conectar_oll_BD();

    $texto  = trim($_POST['texto'] ?? '');
    $estado = trim($_POST['estado'] ?? ''); // '', 'activa', 'inactiva'

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "LOWER(nombre) LIKE LOWER(:texto)";
        $params['texto'] = "%$texto%";
    }
    if ($estado === 'activa') {
        $where[] = "deleted_at IS NULL";
    } elseif ($estado === 'inactiva') {
        $where[] = "deleted_at IS NOT NULL";
    }

    $sql = "
        SELECT *
        FROM color
        WHERE " . implode(' AND ', $where) . "
        ORDER BY nombre
    ";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['colores' => $result]);
}

function obtenerColor($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $result = executeQuery(
        $conectar,
        "SELECT * FROM color WHERE id = :id",
        ['id' => $id]
    );
    if (empty($result)) responder(false, 'Color no encontrado.');
    responder(true, 'OK', ['color' => $result[0]]);
}

/**
 * Arma el bloque de auditoría (usuario/sesión) para un movimiento dado.
 */
function obtenerMovimientoSesion(string $accion): array
{
    return [
        'usuario'   => $_SESSION['usuario_id'] ?? 'Sistema',
        'nombre'    => $_SESSION['nombre_usuario'] ?? 'Usuario Desconocido',
        'user'      => $_SESSION['user_usuario'] ?? 'N/A',
        'perfiles'  => $_SESSION['perfiles'] ?? 'N/A',
        'rol'       => $_SESSION['rol_usuario'] ?? 'N/A',
        'accion'    => $accion,
        'timestamp' => date('Y-m-d H:i:s'),
    ];
}

function guardarColor()
{
    $conectar    = conectar_oll_BD();
    $id          = intval($_POST['id'] ?? 0);
    $nombre      = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $rgb         = trim($_POST['rgb'] ?? '');

    $movimiento          = obtenerMovimientoSesion($id === 0 ? 'crear' : 'editar');
    $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    // ── Validaciones ──────────────────────────────────────────────────────────
    if (empty($nombre)) responder(false, 'El nombre es obligatorio.');

    // Validación ligera de formato: HEX (#fff / #ffffff) o rgb(0,0,0) / rgba(0,0,0,1)
    if ($rgb !== '' && !preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $rgb)
        && !preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*[\d.]+\s*)?\)$/i', $rgb)) {
        responder(false, 'El formato de color no es válido. Usa HEX (#RRGGBB) o rgb(r,g,b).');
    }

    // Nombre único (excluyendo el propio registro si es edición)
    $chk = executeQuery(
        $conectar,
        "SELECT id FROM color WHERE LOWER(nombre) = LOWER(:nombre) AND id <> :id",
        ['nombre' => $nombre, 'id' => $id]
    );
    if (!empty($chk)) responder(false, 'Ya existe un color con ese nombre.');

    if ($id === 0) {
        $result = executeQuery($conectar, "
            INSERT INTO color (nombre, descripcion, rgb, created_at, js_session, js_historial)
            VALUES (:nombre, :descripcion, :rgb, NOW(), :js_session, :js_historial)
            RETURNING id
        ", [
            'nombre'       => $nombre,
            'descripcion'  => $descripcion !== '' ? $descripcion : null,
            'rgb'          => $rgb !== '' ? $rgb : null,
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
        ]);
        $nuevo_id = $result[0]['id'] ?? null;
        responder(true, 'Color creado correctamente.', ['id' => $nuevo_id, 'modo' => 'crear']);
    } else {
        executeQuery($conectar, "
            UPDATE color SET
                nombre       = :nombre,
                descripcion  = :descripcion,
                rgb          = :rgb,
                update_at    = NOW(),
                js_session   = :js_session,
                js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id
        ", [
            'nombre'       => $nombre,
            'descripcion'  => $descripcion !== '' ? $descripcion : null,
            'rgb'          => $rgb !== '' ? $rgb : null,
            'id'           => $id,
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
        ]);
        responder(true, 'Color actualizado correctamente.', ['id' => $id, 'modo' => 'editar']);
    }
}

// Soft delete: se marca deleted_at, no se borra físicamente.
function eliminarColor()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at FROM color WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Color no encontrado.');
    if (!empty($existe[0]['deleted_at'])) {
        responder(false, 'Este color ya estaba inactivo.');
    }

    $movimiento          = obtenerMovimientoSesion('desactivar');
    $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeQuery(
        $conectar,
        "UPDATE color SET
            deleted_at   = NOW(),
            update_at    = NOW(),
            js_session   = :js_session,
            js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id",
        [
            'id'           => $id,
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
        ]
    );
    responder(true, 'Color desactivado correctamente.');
}

function reactivarColor()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $movimiento          = obtenerMovimientoSesion('reactivar');
    $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeQuery(
        $conectar,
        "UPDATE color SET
            deleted_at   = NULL,
            update_at    = NOW(),
            js_session   = :js_session,
            js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id",
        [
            'id'           => $id,
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
        ]
    );
    responder(true, 'Color reactivado correctamente.');
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