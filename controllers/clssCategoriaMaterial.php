<?php

/**
 * controllers/clssCategoriaMaterial.php
 * Controlador del módulo de Categorías de Material
 * Tabla real: categoria_material (id, nombre, descripcion, created_at, update_at, deleted_at)
 * Soft delete vía deleted_at (no existe columna 'activo').
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    controladorCategoriaMaterial($_POST["accion"]);
}

function controladorCategoriaMaterial($accion)
{
    switch ($accion) {
        case 'LISTARCATEGORIASMATERIAL':
            listarCategoriasMaterial();
            break;
        case 'OBTENERCATEGORIAMATERIAL':
            obtenerCategoriaMaterial(intval($_POST['id'] ?? 0));
            break;
        case 'GUARDARCATEGORIAMATERIAL':
            guardarCategoriaMaterial();
            break;
        case 'ELIMINARCATEGORIAMATERIAL':
            eliminarCategoriaMaterial();
            break;
        case 'REACTIVARCATEGORIAMATERIAL':
            reactivarCategoriaMaterial();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// CATEGORÍAS DE MATERIAL
// =============================================================================

function listarCategoriasMaterial()
{
    $conectar = conectar_oll_BD();

    $texto  = trim($_POST['texto'] ?? '');
    $estado = trim($_POST['estado'] ?? ''); // '', 'activa', 'inactiva'

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(nombre) LIKE LOWER(:texto) OR LOWER(COALESCE(descripcion, '')) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }
    if ($estado === 'activa') {
        $where[] = "deleted_at IS NULL";
    } elseif ($estado === 'inactiva') {
        $where[] = "deleted_at IS NOT NULL";
    }

    $sql = "
        SELECT *
        FROM categoria_material
        WHERE " . implode(' AND ', $where) . "
        ORDER BY nombre
    ";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['categorias' => $result]);
}

function obtenerCategoriaMaterial($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $result = executeQuery(
        $conectar,
        "SELECT * FROM categoria_material WHERE id = :id",
        ['id' => $id]
    );
    if (empty($result)) responder(false, 'Categoría de material no encontrada.');
    responder(true, 'OK', ['categoria' => $result[0]]);
}

/**
 * Obtiene la IP real del cliente, considerando proxies/balanceadores comunes.
 */
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

/**
 * Arma el bloque de auditoría (usuario/sesión) para un movimiento dado.
 */
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

/**
 * Compara un registro anterior contra los datos nuevos y devuelve solo los
 * campos cuyo valor cambió, mapeados con etiqueta legible.
 */
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

function guardarCategoriaMaterial()
{
    $conectar = conectar_oll_BD();
    $id          = intval($_POST['id'] ?? 0);
    $nombre      = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    // ── Validaciones ──────────────────────────────────────────────────────────
    if (empty($nombre)) responder(false, 'El nombre es obligatorio.');

    // Nombre único (excluyendo el propio registro si es edición)
    $chk = executeQuery(
        $conectar,
        "SELECT id FROM categoria_material WHERE LOWER(nombre) = LOWER(:nombre) AND id <> :id",
        ['nombre' => $nombre, 'id' => $id]
    );
    if (!empty($chk)) responder(false, 'Ya existe una categoría de material con ese nombre.');

    $mapaCampos = [
        'nombre'      => 'Nombre',
        'descripcion' => 'Descripción',
    ];
    $datosNuevos = [
        'nombre'      => $nombre,
        'descripcion' => $descripcion,
    ];

    if ($id === 0) {
        // Creación
        $cambios = compararCambios([], $datosNuevos, $mapaCampos);

        $movimiento          = obtenerMovimientoSesion('crear', $cambios);
        $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        $result = executeQuery($conectar, "
            INSERT INTO categoria_material (nombre, descripcion, created_at, js_session, js_historial)
            VALUES (:nombre, :descripcion, NOW(), :js_session, :js_historial)
            RETURNING id
        ", [
            'nombre'       => $nombre,
            'descripcion'  => $descripcion !== '' ? $descripcion : null,
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
        ]);
        $nuevo_id = $result[0]['id'] ?? null;
        responder(true, 'Categoría de material creada correctamente.', ['id' => $nuevo_id, 'modo' => 'crear']);
    } else {
        // Edición: traemos el registro actual para comparar campo por campo
        $actual = executeQuery($conectar, "SELECT * FROM categoria_material WHERE id = :id", ['id' => $id]);
        if (empty($actual)) responder(false, 'Categoría de material no encontrada.');
        $registroAnterior = $actual[0];

        $cambios = compararCambios($registroAnterior, $datosNuevos, $mapaCampos);

        $movimiento          = obtenerMovimientoSesion('editar', $cambios);
        $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeQuery($conectar, "
            UPDATE categoria_material SET
                nombre       = :nombre,
                descripcion  = :descripcion,
                update_at    = NOW(),
                js_session   = :js_session,
                js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id
        ", [
            'nombre'       => $nombre,
            'descripcion'  => $descripcion !== '' ? $descripcion : null,
            'id'           => $id,
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
        ]);
        responder(true, 'Categoría de material actualizada correctamente.', ['id' => $id, 'modo' => 'editar']);
    }
}

// Soft delete: se marca deleted_at, no se borra físicamente.
function eliminarCategoriaMaterial()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at FROM categoria_material WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Categoría de material no encontrada.');
    if (!empty($existe[0]['deleted_at'])) {
        responder(false, 'Esta categoría ya estaba inactiva.');
    }

    // No permitir desactivar una categoría que está en uso por alguna producción
    $enUso = executeQuery(
        $conectar,
        "SELECT id FROM produccion WHERE categoria_material_id = :id AND deleted_at IS NULL LIMIT 1",
        ['id' => $id]
    );
    if (!empty($enUso)) {
        responder(false, 'No puedes desactivar esta categoría: está siendo usada en producciones registradas.');
    }

    $cambios = [[
        'campo'         => 'Estado',
        'valor_antes'   => 'Activo',
        'valor_despues' => 'Inactivo',
    ]];

    $movimiento          = obtenerMovimientoSesion('desactivar', $cambios);
    $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeQuery(
        $conectar,
        "UPDATE categoria_material SET
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
    responder(true, 'Categoría de material desactivada correctamente.');
}

function reactivarCategoriaMaterial()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $cambios = [[
        'campo'         => 'Estado',
        'valor_antes'   => 'Inactivo',
        'valor_despues' => 'Activo',
    ]];

    $movimiento          = obtenerMovimientoSesion('reactivar', $cambios);
    $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeQuery(
        $conectar,
        "UPDATE categoria_material SET
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
    responder(true, 'Categoría de material reactivada correctamente.');
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