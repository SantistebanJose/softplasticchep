<?php

/**
 * controllers/clssMaterial.php
 * Controlador del módulo de Materia Prima
 * Tabla real: material (id, nombre, unidad_medida_id, stock_minimo, stock_actual,
 *             js_session, js_historial, created_at, update_at, deleted_at)
 * unidad_medida_id es OPCIONAL: un material puede registrarse sin unidad de medida.
 * Soft delete vía deleted_at.
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    controladorMaterial($_POST["accion"]);
}

function controladorMaterial($accion)
{
    switch ($accion) {
        case 'LISTARMATERIALES':
            listarMateriales();
            break;
        case 'OBTENERMATERIAL':
            obtenerMaterial(intval($_POST['id'] ?? 0));
            break;
        case 'GUARDARMATERIAL':
            guardarMaterial();
            break;
        case 'ELIMINARMATERIAL':
            eliminarMaterial();
            break;
        case 'REACTIVARMATERIAL':
            reactivarMaterial();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// MATERIAL
// =============================================================================

function listarMateriales()
{
    $conectar = conectar_oll_BD();

    $texto  = trim($_POST['texto'] ?? '');
    $estado = trim($_POST['estado'] ?? ''); // '', 'activa', 'inactiva'

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "LOWER(m.nombre) LIKE LOWER(:texto)";
        $params['texto'] = "%$texto%";
    }
    if ($estado === 'activa') {
        $where[] = "m.deleted_at IS NULL";
    } elseif ($estado === 'inactiva') {
        $where[] = "m.deleted_at IS NOT NULL";
    }

    $sql = "
        SELECT
            m.*,
            u.nombre       AS unidad_nombre,
            u.nombre_corto AS unidad_corto
        FROM material m
        LEFT JOIN unidad_medida u ON u.id = m.unidad_medida_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY m.nombre
    ";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['materiales' => $result]);
}

function obtenerMaterial($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $result = executeQuery(
        $conectar,
        "SELECT * FROM material WHERE id = :id",
        ['id' => $id]
    );
    if (empty($result)) responder(false, 'Material no encontrado.');
    responder(true, 'OK', ['material' => $result[0]]);
}

function guardarMaterial()
{
    $conectar    = conectar_oll_BD();
    $id          = intval($_POST['id'] ?? 0);
    $nombre      = trim($_POST['nombre'] ?? '');
    $stockMinimo = $_POST['stock_minimo'] !== '' ? floatval($_POST['stock_minimo'] ?? 0) : 0;
    $stockActual = $_POST['stock_actual'] !== '' ? floatval($_POST['stock_actual'] ?? 0) : 0;

    // La unidad de medida es OPCIONAL: si no viene o viene vacía, queda en NULL.
    $unidadMedidaId = !empty($_POST['unidad_medida_id']) ? intval($_POST['unidad_medida_id']) : null;

    // ── Validaciones ──────────────────────────────────────────────────────────
    if (empty($nombre))    responder(false, 'El nombre es obligatorio.');
    if ($stockMinimo < 0)  responder(false, 'El stock mínimo no puede ser negativo.');
    if ($stockActual < 0)  responder(false, 'El stock actual no puede ser negativo.');

    // Si se envió una unidad de medida, debe existir y estar activa.
    if ($unidadMedidaId !== null) {
        $unidad = executeQuery(
            $conectar,
            "SELECT id FROM unidad_medida WHERE id = :id AND deleted_at IS NULL",
            ['id' => $unidadMedidaId]
        );
        if (empty($unidad)) responder(false, 'La unidad de medida seleccionada no existe o está inactiva.');
    }

    // Nombre único (excluyendo el propio registro si es edición)
    $chk = executeQuery(
        $conectar,
        "SELECT id FROM material WHERE LOWER(nombre) = LOWER(:nombre) AND id <> :id",
        ['nombre' => $nombre, 'id' => $id]
    );
    if (!empty($chk)) responder(false, 'Ya existe un material con ese nombre.');

    if ($id === 0) {
        $result = executeQuery($conectar, "
            INSERT INTO material (nombre, unidad_medida_id, stock_minimo, stock_actual, created_at)
            VALUES (:nombre, :unidad_medida_id, :stock_minimo, :stock_actual, NOW())
            RETURNING id
        ", [
            'nombre'           => $nombre,
            'unidad_medida_id' => $unidadMedidaId,
            'stock_minimo'     => $stockMinimo,
            'stock_actual'     => $stockActual,
        ]);
        $nuevo_id = $result[0]['id'] ?? null;
        responder(true, 'Material creado correctamente.', ['id' => $nuevo_id, 'modo' => 'crear']);
    } else {
        executeQuery($conectar, "
            UPDATE material SET
                nombre           = :nombre,
                unidad_medida_id = :unidad_medida_id,
                stock_minimo     = :stock_minimo,
                stock_actual     = :stock_actual,
                update_at        = NOW()
            WHERE id = :id
        ", [
            'nombre'           => $nombre,
            'unidad_medida_id' => $unidadMedidaId,
            'stock_minimo'     => $stockMinimo,
            'stock_actual'     => $stockActual,
            'id'               => $id,
        ]);
        responder(true, 'Material actualizado correctamente.', ['id' => $id, 'modo' => 'editar']);
    }
}

// Soft delete: se marca deleted_at, no se borra físicamente.
function eliminarMaterial()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at FROM material WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Material no encontrado.');
    if (!empty($existe[0]['deleted_at'])) {
        responder(false, 'Este material ya estaba inactivo.');
    }

    executeQuery(
        $conectar,
        "UPDATE material SET deleted_at = NOW(), update_at = NOW() WHERE id = :id",
        ['id' => $id]
    );
    responder(true, 'Material desactivado correctamente.');
}

function reactivarMaterial()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    executeQuery(
        $conectar,
        "UPDATE material SET deleted_at = NULL, update_at = NOW() WHERE id = :id",
        ['id' => $id]
    );
    responder(true, 'Material reactivado correctamente.');
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