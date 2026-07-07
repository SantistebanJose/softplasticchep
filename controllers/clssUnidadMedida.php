<?php

/**
 * controllers/clssUnidadMedida.php
 * Controlador del módulo de Unidad de Medida
 * Tabla real: unidad_medida (id, nombre, nombre_corto, js_session, js_historial, created_at, update_at, deleted_at)
 * Soft delete vía deleted_at.
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    controladorUnidadMedida($_POST["accion"]);
}

function controladorUnidadMedida($accion)
{
    switch ($accion) {
        case 'LISTARUNIDADESMEDIDA':
            listarUnidadesMedida();
            break;
        case 'OBTENERUNIDADMEDIDA':
            obtenerUnidadMedida(intval($_POST['id'] ?? 0));
            break;
        case 'GUARDARUNIDADMEDIDA':
            guardarUnidadMedida();
            break;
        case 'ELIMINARUNIDADMEDIDA':
            eliminarUnidadMedida();
            break;
        case 'REACTIVARUNIDADMEDIDA':
            reactivarUnidadMedida();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// UNIDAD DE MEDIDA
// =============================================================================

function listarUnidadesMedida()
{
    $conectar = conectar_oll_BD();

    $texto  = trim($_POST['texto'] ?? '');
    $estado = trim($_POST['estado'] ?? ''); // '', 'activa', 'inactiva'

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(nombre) LIKE LOWER(:texto) OR LOWER(nombre_corto) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }
    if ($estado === 'activa') {
        $where[] = "deleted_at IS NULL";
    } elseif ($estado === 'inactiva') {
        $where[] = "deleted_at IS NOT NULL";
    }

    $sql = "SELECT * FROM unidad_medida WHERE " . implode(' AND ', $where) . " ORDER BY nombre";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['unidades' => $result]);
}

function obtenerUnidadMedida($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $result = executeQuery(
        $conectar,
        "SELECT * FROM unidad_medida WHERE id = :id",
        ['id' => $id]
    );
    if (empty($result)) responder(false, 'Unidad de medida no encontrada.');
    responder(true, 'OK', ['unidad' => $result[0]]);
}

function guardarUnidadMedida()
{
    $conectar     = conectar_oll_BD();
    $id           = intval($_POST['id'] ?? 0);
    $nombre       = trim($_POST['nombre'] ?? '');
    $nombreCorto  = trim($_POST['nombre_corto'] ?? '');

    // ── Validaciones ──────────────────────────────────────────────────────────
    if (empty($nombre))      responder(false, 'El nombre es obligatorio.');
    if (empty($nombreCorto)) responder(false, 'El nombre corto (abreviatura) es obligatorio.');

    // Nombre único (excluyendo el propio registro si es edición)
    $chk = executeQuery(
        $conectar,
        "SELECT id FROM unidad_medida WHERE LOWER(nombre) = LOWER(:nombre) AND id <> :id",
        ['nombre' => $nombre, 'id' => $id]
    );
    if (!empty($chk)) responder(false, 'Ya existe una unidad de medida con ese nombre.');

    if ($id === 0) {
        $result = executeQuery($conectar, "
            INSERT INTO unidad_medida (nombre, nombre_corto, created_at)
            VALUES (:nombre, :nombre_corto, NOW())
            RETURNING id
        ", [
            'nombre'       => $nombre,
            'nombre_corto' => $nombreCorto,
        ]);
        $nuevo_id = $result[0]['id'] ?? null;
        responder(true, 'Unidad de medida creada correctamente.', ['id' => $nuevo_id, 'modo' => 'crear']);
    } else {
        executeQuery($conectar, "
            UPDATE unidad_medida SET
                nombre       = :nombre,
                nombre_corto = :nombre_corto,
                update_at    = NOW()
            WHERE id = :id
        ", [
            'nombre'       => $nombre,
            'nombre_corto' => $nombreCorto,
            'id'           => $id,
        ]);
        responder(true, 'Unidad de medida actualizada correctamente.', ['id' => $id, 'modo' => 'editar']);
    }
}

// Soft delete: se marca deleted_at, no se borra físicamente.
function eliminarUnidadMedida()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at FROM unidad_medida WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Unidad de medida no encontrada.');
    if (!empty($existe[0]['deleted_at'])) {
        responder(false, 'Esta unidad de medida ya estaba inactiva.');
    }

    // No permitir desactivar una unidad de medida que está en uso por algún material
    $enUso = executeQuery(
        $conectar,
        "SELECT id FROM material WHERE unidad_medida_id = :id AND deleted_at IS NULL",
        ['id' => $id]
    );
    if (!empty($enUso)) {
        responder(false, 'No puedes desactivar esta unidad: está siendo usada por uno o más materiales activos.');
    }

    executeQuery(
        $conectar,
        "UPDATE unidad_medida SET deleted_at = NOW(), update_at = NOW() WHERE id = :id",
        ['id' => $id]
    );
    responder(true, 'Unidad de medida desactivada correctamente.');
}

function reactivarUnidadMedida()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    executeQuery(
        $conectar,
        "UPDATE unidad_medida SET deleted_at = NULL, update_at = NOW() WHERE id = :id",
        ['id' => $id]
    );
    responder(true, 'Unidad de medida reactivada correctamente.');
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