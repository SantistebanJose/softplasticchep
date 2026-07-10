<?php

/**
 * controllers/clssProductos.php
 * Controlador del módulo de Productos (Mercadería para la venta)
 * Tabla real: producto (singular) — id, codigo, descripcion, unidad_venta_id,
 *             cant_equivale, unidad_equivale_id, peso_unitario_g, activo,
 *             created_at, updated_at
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    controladorProductos($_POST["accion"]);
}

function controladorProductos($accion)
{
    switch ($accion) {
        case 'LISTARUNIDADES':
            listarUnidades();
            break;
        case 'LISTARPRODUCTOS':
            listarProductos();
            break;
        case 'OBTENERPRODUCTO':
            obtenerProducto(intval($_POST['id'] ?? 0));
            break;
        case 'GUARDARPRODUCTO':
            guardarProducto();
            break;
        case 'ELIMINARPRODUCTO':
            eliminarProducto();
            break;
        case 'REACTIVARPRODUCTO':
            reactivarProducto();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// CATÁLOGOS
// =============================================================================

function listarUnidades()
{
    $conectar = conectar_oll_BD();
    $result   = executeQuery(
        $conectar,
        "SELECT id, nombre_corto AS codigo, nombre 
         FROM unidad_medida 
         WHERE deleted_at IS NULL
         ORDER BY nombre"
    );
    responder(true, 'OK', ['unidades' => $result]);
}
// =============================================================================
// PRODUCTOS
// =============================================================================

function listarProductos()
{
    $conectar = conectar_oll_BD();

    $texto  = trim($_POST['texto']  ?? '');
    $estado = trim($_POST['estado'] ?? ''); // '', 'activo', 'inactivo'

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(p.codigo) LIKE LOWER(:texto) OR LOWER(p.descripcion) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }
    if ($estado === 'activo') {
        $where[] = "p.activo = TRUE";
    } elseif ($estado === 'inactivo') {
        $where[] = "p.activo = FALSE";
    }

    $sql = "
        SELECT p.*,
            uv.nombre_corto AS unidad_venta_codigo,
            uv.nombre AS unidad_venta_nombre,
            ue.nombre_corto AS unidad_equivale_codigo,
            ue.nombre AS unidad_equivale_nombre
        FROM producto p
        LEFT JOIN unidad_medida uv ON uv.id = p.unidad_venta_id
        LEFT JOIN unidad_medida ue ON ue.id = p.unidad_equivale_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY p.codigo
    ";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['productos' => $result]);
}

function obtenerProducto($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $result = executeQuery(
        $conectar,
        "SELECT p.*,
            uv.nombre_corto AS unidad_venta_codigo,
            uv.nombre AS unidad_venta_nombre,
            ue.nombre_corto AS unidad_equivale_codigo,
            ue.nombre AS unidad_equivale_nombre
         FROM producto p
         LEFT JOIN unidad_medida uv ON uv.id = p.unidad_venta_id
         LEFT JOIN unidad_medida ue ON ue.id = p.unidad_equivale_id
         WHERE p.id = :id",
        ['id' => $id]
    );
    if (empty($result)) responder(false, 'Producto no encontrado.');
    responder(true, 'OK', ['producto' => $result[0]]);
}

function guardarProducto()
{
    $conectar           = conectar_oll_BD();
    $id                 = intval($_POST['id'] ?? 0);
    $codigo             = strtoupper(trim($_POST['codigo'] ?? ''));
    $descripcion        = strtoupper(trim($_POST['descripcion'] ?? ''));
    $unidad_venta_id    = intval($_POST['unidad_venta_id'] ?? 0);
    $cant_equivale      = is_numeric($_POST['cant_equivale'] ?? '') ? floatval($_POST['cant_equivale']) : null;
    $unidad_equivale_id = intval($_POST['unidad_equivale_id'] ?? 0) ?: null;
    $peso_unitario_g    = is_numeric($_POST['peso_unitario_g'] ?? '') ? floatval($_POST['peso_unitario_g']) : null;

    // ── Validaciones ──────────────────────────────────────────────────────────
    if (empty($codigo))          responder(false, 'El código es obligatorio.');
    if (empty($descripcion))     responder(false, 'La descripción es obligatoria.');
    if ($unidad_venta_id <= 0)   responder(false, 'Selecciona la unidad de venta.');

    // Código único (excluyendo el propio registro si es edición)
    $chk = executeQuery(
        $conectar,
        "SELECT id FROM producto WHERE LOWER(codigo) = LOWER(:cod) AND id <> :id",
        ['cod' => $codigo, 'id' => $id]
    );
    if (!empty($chk)) responder(false, 'Ya existe un producto con ese código.');

    if ($id === 0) {
        $result = executeQuery($conectar, "
            INSERT INTO producto
                (codigo, descripcion, unidad_venta_id, cant_equivale, unidad_equivale_id, peso_unitario_g, activo)
            VALUES (:cod, :desc, :uv, :ceq, :ueq, :peso, TRUE)
            RETURNING id
        ", [
            'cod'  => $codigo,
            'desc' => $descripcion,
            'uv'   => $unidad_venta_id,
            'ceq'  => $cant_equivale,
            'ueq'  => $unidad_equivale_id,
            'peso' => $peso_unitario_g,
        ]);
        $nuevo_id = $result[0]['id'] ?? null;
        responder(true, 'Producto creado correctamente.', ['id' => $nuevo_id, 'modo' => 'crear']);
    } else {
        executeQuery($conectar, "
            UPDATE producto SET
                codigo = :cod,
                descripcion = :desc,
                unidad_venta_id = :uv,
                cant_equivale = :ceq,
                unidad_equivale_id = :ueq,
                peso_unitario_g = :peso,
                updated_at = NOW()
            WHERE id = :id
        ", [
            'cod'  => $codigo,
            'desc' => $descripcion,
            'uv'   => $unidad_venta_id,
            'ceq'  => $cant_equivale,
            'ueq'  => $unidad_equivale_id,
            'peso' => $peso_unitario_g,
            'id'   => $id,
        ]);
        responder(true, 'Producto actualizado correctamente.', ['id' => $id, 'modo' => 'editar']);
    }
}

// Soft delete: no se borra físicamente, solo se marca activo = FALSE.
function eliminarProducto()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, activo FROM producto WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Producto no encontrado.');
    if ($existe[0]['activo'] === false) responder(false, 'Este producto ya estaba inactivo.');

    executeQuery(
        $conectar,
        "UPDATE producto SET activo = FALSE, updated_at = NOW() WHERE id = :id",
        ['id' => $id]
    );
    responder(true, 'Producto desactivado correctamente.');
}

function reactivarProducto()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, activo FROM producto WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Producto no encontrado.');
    if ($existe[0]['activo'] === true) responder(false, 'Este producto ya estaba activo.');

    executeQuery(
        $conectar,
        "UPDATE producto SET activo = TRUE, updated_at = NOW() WHERE id = :id",
        ['id' => $id]
    );
    responder(true, 'Producto reactivado correctamente.');
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