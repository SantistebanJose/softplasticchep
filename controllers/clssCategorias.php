<?php

/**
 * controllers/clssCategorias.php
 * Controlador del módulo de Categorías de producto
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    controladorCategorias($_POST["accion"]);
}

function controladorCategorias($accion)
{
    switch ($accion) {
        case 'LISTARCATEGORIAS':
            listarCategorias();
            break;
        case 'OBTENERCATEGORIA':
            obtenerCategoria(intval($_POST['id'] ?? 0));
            break;
        case 'GUARDARCATEGORIA':
            guardarCategoria();
            break;
        case 'ELIMINARCATEGORIA':
            eliminarCategoria();
            break;
        case 'REACTIVARCATEGORIA':
            reactivarCategoria();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// CATEGORÍAS
// =============================================================================

function listarCategorias()
{
    $conectar = conectar_oll_BD();

    $texto  = trim($_POST['texto']  ?? '');
    $estado = trim($_POST['estado'] ?? ''); // '', 'activo', 'inactivo'

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(nombre) LIKE LOWER(:texto) OR LOWER(descripcion) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }
    if ($estado === 'activo') {
        $where[] = "activo = TRUE";
    } elseif ($estado === 'inactivo') {
        $where[] = "activo = FALSE";
    }

    $sql = "
        SELECT id, nombre, descripcion, activo
        FROM categorias_producto
        WHERE " . implode(' AND ', $where) . "
        ORDER BY nombre
    ";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['categorias' => $result]);
}

function obtenerCategoria($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $result = executeQuery(
        $conectar,
        "SELECT * FROM categorias_producto WHERE id = :id",
        ['id' => $id]
    );
    if (empty($result)) responder(false, 'Categoría no encontrada.');
    responder(true, 'OK', ['categoria' => $result[0]]);
}

function guardarCategoria()
{
    $conectar    = conectar_oll_BD();
    $id          = intval($_POST['id'] ?? 0);
    $nombre      = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    // ── Validaciones ──────────────────────────────────────────────────────────
    if (empty($nombre)) responder(false, 'El nombre es obligatorio.');

    // Nombre único (excluyendo el propio registro si es edición)
    $chk = executeQuery(
        $conectar,
        "SELECT id FROM categorias_producto WHERE LOWER(nombre) = LOWER(:nombre) AND id <> :id",
        ['nombre' => $nombre, 'id' => $id]
    );
    if (!empty($chk)) responder(false, 'Ya existe una categoría con ese nombre.');

    if ($id === 0) {
        $result = executeQuery($conectar, "
            INSERT INTO categorias_producto (nombre, descripcion, activo, created_at, updated_at)
            VALUES (:nombre, :desc, TRUE, NOW(), NOW())
            RETURNING id
        ", [
            'nombre' => $nombre,
            'desc'   => $descripcion,
        ]);
        $nuevo_id = $result[0]['id'] ?? null;
        responder(true, 'Categoría creada correctamente.', ['id' => $nuevo_id, 'modo' => 'crear']);
    } else {
        executeQuery($conectar, "
            UPDATE categorias_producto SET
                nombre = :nombre,
                descripcion = :desc,
                updated_at = NOW()
            WHERE id = :id
        ", [
            'nombre' => $nombre,
            'desc'   => $descripcion,
            'id'     => $id,
        ]);
        responder(true, 'Categoría actualizada correctamente.', ['id' => $id, 'modo' => 'editar']);
    }
}

// Soft delete: igual que en productos, no se borra físicamente.
function eliminarCategoria()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, activo FROM categorias_producto WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Categoría no encontrada.');
    if ($existe[0]['activo'] === false) responder(false, 'Esta categoría ya estaba inactiva.');

    executeQuery(
        $conectar,
        "UPDATE categorias_producto SET activo = FALSE, updated_at = NOW() WHERE id = :id",
        ['id' => $id]
    );
    responder(true, 'Categoría desactivada correctamente.');
}

function reactivarCategoria()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    executeQuery(
        $conectar,
        "UPDATE categorias_producto SET activo = TRUE, updated_at = NOW() WHERE id = :id",
        ['id' => $id]
    );
    responder(true, 'Categoría reactivada correctamente.');
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