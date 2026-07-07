<?php

/**
 * controllers/clssModelos.php
 * Controlador del módulo de Modelos de producto
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    controladorModelos($_POST["accion"]);
}

function controladorModelos($accion)
{
    switch ($accion) {
        case 'LISTARCATEGORIASACTIVAS':
            listarCategoriasActivas();
            break;
        case 'LISTARMODELOS':
            listarModelos();
            break;
        case 'OBTENERMODELO':
            obtenerModelo(intval($_POST['id'] ?? 0));
            break;
        case 'GUARDARMODELO':
            guardarModelo();
            break;
        case 'ELIMINARMODELO':
            eliminarModelo();
            break;
        case 'REACTIVARMODELO':
            reactivarModelo();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// CATÁLOGOS
// =============================================================================

function listarCategoriasActivas()
{
    $conectar = conectar_oll_BD();
    $result   = executeQuery(
        $conectar,
        "SELECT id, nombre FROM categorias_producto WHERE activo = TRUE ORDER BY nombre"
    );
    responder(true, 'OK', ['categorias' => $result]);
}

// =============================================================================
// MODELOS
// =============================================================================

function listarModelos()
{
    $conectar = conectar_oll_BD();

    $texto       = trim($_POST['texto'] ?? '');
    $categoriaId = intval($_POST['categoria_id'] ?? 0);
    $estado      = trim($_POST['estado'] ?? ''); // '', 'activo', 'inactivo'

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "LOWER(m.nombre) LIKE LOWER(:texto)";
        $params['texto'] = "%$texto%";
    }
    if ($categoriaId > 0) {
        $where[] = "m.categoria_id = :categoria_id";
        $params['categoria_id'] = $categoriaId;
    }
    if ($estado === 'activo') {
        $where[] = "m.activo = TRUE";
    } elseif ($estado === 'inactivo') {
        $where[] = "m.activo = FALSE";
    }

    $sql = "
        SELECT m.*, c.nombre AS categoria_nombre
        FROM modelos_producto m
        LEFT JOIN categorias_producto c ON c.id = m.categoria_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.nombre, m.nombre
    ";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['modelos' => $result]);
}

function obtenerModelo($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $result = executeQuery(
        $conectar,
        "SELECT * FROM modelos_producto WHERE id = :id",
        ['id' => $id]
    );
    if (empty($result)) responder(false, 'Modelo no encontrado.');
    responder(true, 'OK', ['modelo' => $result[0]]);
}

/**
 * Arma el bloque de auditoría (usuario/sesión) para un movimiento dado.
 * Realizado por Franco.
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

function guardarModelo()
{
    $conectar    = conectar_oll_BD();
    $id          = intval($_POST['id'] ?? 0);
    $categoriaId = intval($_POST['categoria_id'] ?? 0);
    $nombre      = trim($_POST['nombre'] ?? '');

    $movimiento = obtenerMovimientoSesion($id === 0 ? 'crear' : 'editar');

    // js_session: último movimiento (objeto plano)
    $js_session = json_encode($movimiento, JSON_UNESCAPED_UNICODE);

    // js_historial: el mismo movimiento pero envuelto en array,
    // para poder concatenarlo con jsonb || jsonb
    $js_historial_nuevo = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    // ── Validaciones ──────────────────────────────────────────────────────────
    if ($categoriaId <= 0) responder(false, 'Selecciona la categoría.');
    if (empty($nombre))    responder(false, 'El nombre es obligatorio.');

    // Nombre único dentro de la misma categoría (excluyendo el propio registro si es edición)
    $chk = executeQuery(
        $conectar,
        "SELECT id FROM modelos_producto WHERE LOWER(nombre) = LOWER(:nombre) AND categoria_id = :categoria_id AND id <> :id",
        ['nombre' => $nombre, 'categoria_id' => $categoriaId, 'id' => $id]
    );
    if (!empty($chk)) responder(false, 'Ya existe un modelo con ese nombre en esta categoría.');

    if ($id === 0) {
        $result = executeQuery($conectar, "
            INSERT INTO modelos_producto (categoria_id, nombre, activo, created_at, updated_at, js_session, js_historial)
            VALUES (:categoria_id, :nombre, TRUE, NOW(), NOW(), :js_session, :js_historial)
            RETURNING id
        ", [
            'categoria_id' => $categoriaId,
            'nombre'       => $nombre,
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
        ]);
        $nuevo_id = $result[0]['id'] ?? null;
        responder(true, 'Modelo creado correctamente.', ['id' => $nuevo_id, 'modo' => 'crear']);
    } else {
        executeQuery($conectar, "
            UPDATE modelos_producto SET
                categoria_id  = :categoria_id,
                nombre        = :nombre,
                updated_at    = NOW(),
                js_session    = :js_session,
                js_historial  = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id
        ", [
            'categoria_id' => $categoriaId,
            'nombre'       => $nombre,
            'id'           => $id,
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
        ]);
        responder(true, 'Modelo actualizado correctamente.', ['id' => $id, 'modo' => 'editar']);
    }
}

// Soft delete: igual que en productos y categorías, no se borra físicamente.
function eliminarModelo()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, activo FROM modelos_producto WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Modelo no encontrado.');
    if ($existe[0]['activo'] === false) responder(false, 'Este modelo ya estaba inactivo.');

    $movimiento          = obtenerMovimientoSesion('desactivar');
    $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeQuery(
        $conectar,
        "UPDATE modelos_producto SET
            activo       = FALSE,
            updated_at   = NOW(),
            js_session   = :js_session,
            js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id",
        [
            'id'           => $id,
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
        ]
    );
    responder(true, 'Modelo desactivado correctamente.');
}

function reactivarModelo()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $movimiento          = obtenerMovimientoSesion('reactivar');
    $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeQuery(
        $conectar,
        "UPDATE modelos_producto SET
            activo       = TRUE,
            updated_at   = NOW(),
            js_session   = :js_session,
            js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id",
        [
            'id'           => $id,
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
        ]
    );
    responder(true, 'Modelo reactivado correctamente.');
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