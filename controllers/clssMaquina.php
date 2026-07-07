<?php

/**
 * controllers/clssMaquina.php
 * Controlador del módulo de Máquinas
 * Tabla real: maquina (id, nombre, descripcion, estado, js_session, js_historial,
 *             created_at, update_at, deleted_at)
 * estado: 'A' = Activa | 'M' = Mantenimiento
 * Soft delete vía deleted_at.
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    controladorMaquina($_POST["accion"]);
}

function controladorMaquina($accion)
{
    switch ($accion) {
        case 'LISTARMAQUINAS':
            listarMaquinas();
            break;
        case 'OBTENERMAQUINA':
            obtenerMaquina(intval($_POST['id'] ?? 0));
            break;
        case 'GUARDARMAQUINA':
            guardarMaquina();
            break;
        case 'ELIMINARMAQUINA':
            eliminarMaquina();
            break;
        case 'REACTIVARMAQUINA':
            reactivarMaquina();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// MAQUINA
// =============================================================================

function listarMaquinas()
{
    $conectar = conectar_oll_BD();

    $texto         = trim($_POST['texto'] ?? '');
    $estado        = trim($_POST['estado'] ?? '');          // '', 'activa', 'inactiva' (registro)
    $estadoMaquina = trim($_POST['estado_maquina'] ?? '');  // '', 'A', 'M'

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
    if (in_array($estadoMaquina, ['A', 'M'], true)) {
        $where[] = "estado = :estado_maquina";
        $params['estado_maquina'] = $estadoMaquina;
    }

    $sql = "
        SELECT *
        FROM maquina
        WHERE " . implode(' AND ', $where) . "
        ORDER BY nombre
    ";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['maquinas' => $result]);
}

function obtenerMaquina($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $result = executeQuery(
        $conectar,
        "SELECT * FROM maquina WHERE id = :id",
        ['id' => $id]
    );
    if (empty($result)) responder(false, 'Máquina no encontrada.');
    responder(true, 'OK', ['maquina' => $result[0]]);
}

function guardarMaquina()
{
    $conectar    = conectar_oll_BD();
    $id          = intval($_POST['id'] ?? 0);
    $nombre      = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $estado      = trim($_POST['estado'] ?? 'A');

    // ── Validaciones ──────────────────────────────────────────────────────────
    if (empty($nombre)) responder(false, 'El nombre es obligatorio.');
    if (!in_array($estado, ['A', 'M'], true)) {
        responder(false, 'El estado debe ser Activa (A) o Mantenimiento (M).');
    }

    // Nombre único (excluyendo el propio registro si es edición)
    $chk = executeQuery(
        $conectar,
        "SELECT id FROM maquina WHERE LOWER(nombre) = LOWER(:nombre) AND id <> :id",
        ['nombre' => $nombre, 'id' => $id]
    );
    if (!empty($chk)) responder(false, 'Ya existe una máquina con ese nombre.');

    if ($id === 0) {
        $result = executeQuery($conectar, "
            INSERT INTO maquina (nombre, descripcion, estado, created_at)
            VALUES (:nombre, :descripcion, :estado, NOW())
            RETURNING id
        ", [
            'nombre'      => $nombre,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
            'estado'      => $estado,
        ]);
        $nuevo_id = $result[0]['id'] ?? null;
        responder(true, 'Máquina creada correctamente.', ['id' => $nuevo_id, 'modo' => 'crear']);
    } else {
        executeQuery($conectar, "
            UPDATE maquina SET
                nombre      = :nombre,
                descripcion = :descripcion,
                estado      = :estado,
                update_at   = NOW()
            WHERE id = :id
        ", [
            'nombre'      => $nombre,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
            'estado'      => $estado,
            'id'          => $id,
        ]);
        responder(true, 'Máquina actualizada correctamente.', ['id' => $id, 'modo' => 'editar']);
    }
}

// Soft delete: se marca deleted_at, no se borra físicamente.
function eliminarMaquina()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at FROM maquina WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Máquina no encontrada.');
    if (!empty($existe[0]['deleted_at'])) {
        responder(false, 'Esta máquina ya estaba inactiva.');
    }

    executeQuery(
        $conectar,
        "UPDATE maquina SET deleted_at = NOW(), update_at = NOW() WHERE id = :id",
        ['id' => $id]
    );
    responder(true, 'Máquina desactivada correctamente.');
}

function reactivarMaquina()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    executeQuery(
        $conectar,
        "UPDATE maquina SET deleted_at = NULL, update_at = NOW() WHERE id = :id",
        ['id' => $id]
    );
    responder(true, 'Máquina reactivada correctamente.');
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