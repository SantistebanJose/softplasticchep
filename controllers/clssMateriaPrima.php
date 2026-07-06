<?php

/**
 * controllers/clssMateriaPrima.php
 * Controlador del módulo de Materia Prima
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    controladorMateriaPrima($_POST["accion"]);
}

function controladorMateriaPrima($accion)
{
    switch ($accion) {
        case 'LISTARMATERIAPRIMA':
            listarMateriaPrima();
            break;
        case 'OBTENERMATERIAPRIMA':
            obtenerMateriaPrima(intval($_POST['id'] ?? 0));
            break;
        case 'GUARDARMATERIAPRIMA':
            guardarMateriaPrima();
            break;
        case 'ELIMINARMATERIAPRIMA':
            eliminarMateriaPrima();
            break;
        case 'REACTIVARMATERIAPRIMA':
            reactivarMateriaPrima();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// MATERIA PRIMA
// =============================================================================

function listarMateriaPrima()
{
    $conectar = conectar_oll_BD();

    $texto  = trim($_POST['texto']  ?? '');
    $estado = trim($_POST['estado'] ?? ''); // '', 'activo', 'inactivo'

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "LOWER(nombre) LIKE LOWER(:texto)";
        $params['texto'] = "%$texto%";
    }
    if ($estado === 'activo') {
        $where[] = "activo = TRUE";
    } elseif ($estado === 'inactivo') {
        $where[] = "activo = FALSE";
    }

    $sql = "
        SELECT *
        FROM materia_prima
        WHERE " . implode(' AND ', $where) . "
        ORDER BY nombre
    ";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['materia_prima' => $result]);
}

function obtenerMateriaPrima($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $result = executeQuery(
        $conectar,
        "SELECT * FROM materia_prima WHERE id = :id",
        ['id' => $id]
    );
    if (empty($result)) responder(false, 'Materia prima no encontrada.');
    responder(true, 'OK', ['materia_prima' => $result[0]]);
}

function guardarMateriaPrima()
{
    $conectar       = conectar_oll_BD();
    $id             = intval($_POST['id'] ?? 0);
    $nombre         = trim($_POST['nombre'] ?? '');
    $unidad_medida  = trim($_POST['unidad_medida'] ?? '');
    $stock_actual   = is_numeric($_POST['stock_actual']  ?? '') ? floatval($_POST['stock_actual'])  : 0;
    $stock_minimo   = is_numeric($_POST['stock_minimo']  ?? '') ? floatval($_POST['stock_minimo'])  : 0;

    // ── Validaciones ──────────────────────────────────────────────────────────
    if (empty($nombre))        responder(false, 'El nombre es obligatorio.');
    if (empty($unidad_medida)) responder(false, 'La unidad de medida es obligatoria.');
    if ($stock_actual < 0)     responder(false, 'El stock actual no puede ser negativo.');
    if ($stock_minimo < 0)     responder(false, 'El stock mínimo no puede ser negativo.');

    // Nombre único (excluyendo el propio registro si es edición)
    $chk = executeQuery(
        $conectar,
        "SELECT id FROM materia_prima WHERE LOWER(nombre) = LOWER(:nombre) AND id <> :id",
        ['nombre' => $nombre, 'id' => $id]
    );
    if (!empty($chk)) responder(false, 'Ya existe una materia prima con ese nombre.');

    if ($id === 0) {
        $result = executeQuery($conectar, "
            INSERT INTO materia_prima
                (nombre, unidad_medida, stock_actual, stock_minimo, activo, created_at, updated_at)
            VALUES (:nombre, :unidad, :stock_actual, :stock_minimo, TRUE, NOW(), NOW())
            RETURNING id
        ", [
            'nombre'       => $nombre,
            'unidad'       => $unidad_medida,
            'stock_actual' => $stock_actual,
            'stock_minimo' => $stock_minimo,
        ]);
        $nuevo_id = $result[0]['id'] ?? null;
        responder(true, 'Materia prima creada correctamente.', ['id' => $nuevo_id, 'modo' => 'crear']);
    } else {
        executeQuery($conectar, "
            UPDATE materia_prima SET
                nombre = :nombre,
                unidad_medida = :unidad,
                stock_actual = :stock_actual,
                stock_minimo = :stock_minimo,
                updated_at = NOW()
            WHERE id = :id
        ", [
            'nombre'       => $nombre,
            'unidad'       => $unidad_medida,
            'stock_actual' => $stock_actual,
            'stock_minimo' => $stock_minimo,
            'id'           => $id,
        ]);
        responder(true, 'Materia prima actualizada correctamente.', ['id' => $id, 'modo' => 'editar']);
    }
}

// Soft delete: igual que en productos, no se borra físicamente.
function eliminarMateriaPrima()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, activo FROM materia_prima WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Materia prima no encontrada.');
    if ($existe[0]['activo'] === false) responder(false, 'Esta materia prima ya estaba inactiva.');

    executeQuery(
        $conectar,
        "UPDATE materia_prima SET activo = FALSE, updated_at = NOW() WHERE id = :id",
        ['id' => $id]
    );
    responder(true, 'Materia prima desactivada correctamente.');
}

function reactivarMateriaPrima()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    executeQuery(
        $conectar,
        "UPDATE materia_prima SET activo = TRUE, updated_at = NOW() WHERE id = :id",
        ['id' => $id]
    );
    responder(true, 'Materia prima reactivada correctamente.');
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