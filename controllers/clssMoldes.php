<?php

/**
 * controllers/clssMoldes.php
 * Controlador del módulo de Moldes
 * Tabla real: molde (id, nombre, forma, js_session, js_historial, created_at, update_at, deleted_at)
 * Soft delete vía deleted_at (no existe columna 'activo').
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    controladorMoldes($_POST["accion"]);
}

function controladorMoldes($accion)
{
    switch ($accion) {
        case 'LISTARMOLDES':
            listarMoldes();
            break;
        case 'OBTENERMOLDE':
            obtenerMolde(intval($_POST['id'] ?? 0));
            break;
        case 'GUARDARMOLDE':
            guardarMolde();
            break;
        case 'ELIMINARMOLDE':
            eliminarMolde();
            break;
        case 'REACTIVARMOLDE':
            reactivarMolde();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// MOLDES
// =============================================================================

function listarMoldes()
{
    $conectar = conectar_oll_BD();

    $texto  = trim($_POST['texto'] ?? '');
    $estado = trim($_POST['estado'] ?? ''); // '', 'activa', 'inactiva'

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(nombre) LIKE LOWER(:texto) OR LOWER(forma) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }
    if ($estado === 'activa') {
        $where[] = "deleted_at IS NULL";
    } elseif ($estado === 'inactiva') {
        $where[] = "deleted_at IS NOT NULL";
    }

    $sql = "SELECT * FROM molde WHERE " . implode(' AND ', $where) . " ORDER BY nombre";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['moldes' => $result]);
}

function obtenerMolde($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $result = executeQuery(
        $conectar,
        "SELECT * FROM molde WHERE id = :id",
        ['id' => $id]
    );
    if (empty($result)) responder(false, 'Molde no encontrado.');
    responder(true, 'OK', ['molde' => $result[0]]);
}

function guardarMolde()
{
    $conectar = conectar_oll_BD();
    $id     = intval($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $forma  = trim($_POST['forma'] ?? '');

    // ── Validaciones ──────────────────────────────────────────────────────────
    if (empty($nombre)) responder(false, 'El nombre es obligatorio.');
    if (empty($forma))  responder(false, 'La forma es obligatoria.');

    // Nombre único (excluyendo el propio registro si es edición)
    $chk = executeQuery(
        $conectar,
        "SELECT id FROM molde WHERE LOWER(nombre) = LOWER(:nombre) AND id <> :id",
        ['nombre' => $nombre, 'id' => $id]
    );
    if (!empty($chk)) responder(false, 'Ya existe un molde con ese nombre.');

    if ($id === 0) {
        $result = executeQuery($conectar, "
            INSERT INTO molde (nombre, forma, created_at)
            VALUES (:nombre, :forma, NOW())
            RETURNING id
        ", [
            'nombre' => $nombre,
            'forma'  => $forma,
        ]);
        $nuevo_id = $result[0]['id'] ?? null;
        responder(true, 'Molde creado correctamente.', ['id' => $nuevo_id, 'modo' => 'crear']);
    } else {
        executeQuery($conectar, "
            UPDATE molde SET
                nombre    = :nombre,
                forma     = :forma,
                update_at = NOW()
            WHERE id = :id
        ", [
            'nombre' => $nombre,
            'forma'  => $forma,
            'id'     => $id,
        ]);
        responder(true, 'Molde actualizado correctamente.', ['id' => $id, 'modo' => 'editar']);
    }
}

// Soft delete: se marca deleted_at, no se borra físicamente.
function eliminarMolde()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at FROM molde WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Molde no encontrado.');
    if (!empty($existe[0]['deleted_at'])) {
        responder(false, 'Este molde ya estaba inactivo.');
    }

    // No permitir desactivar un molde que está puesto ahora mismo en una máquina
    $enUso = executeQuery(
        $conectar,
        "SELECT maquina_id FROM maquina_moldes WHERE molde_id = :id AND fecha_fin IS NULL",
        ['id' => $id]
    );
    if (!empty($enUso)) {
        responder(false, 'No puedes desactivar este molde: actualmente está puesto en una máquina.');
    }

    executeQuery(
        $conectar,
        "UPDATE molde SET deleted_at = NOW(), update_at = NOW() WHERE id = :id",
        ['id' => $id]
    );
    responder(true, 'Molde desactivado correctamente.');
}

function reactivarMolde()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    executeQuery(
        $conectar,
        "UPDATE molde SET deleted_at = NULL, update_at = NOW() WHERE id = :id",
        ['id' => $id]
    );
    responder(true, 'Molde reactivado correctamente.');
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