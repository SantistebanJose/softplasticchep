<?php

/**
 * controllers/clssSucursal.php
 * Controlador del módulo de Sucursales
 * Tabla real: sucursal (id, nombre, direccion, created_at, json_moldes,
 *             update_at, delete_at)
 *
 * Soft delete vía delete_at (mismo patrón que 'operario' / 'moldes'),
 * pero esta tabla NO tiene columna 'activo' booleana: la visibilidad
 * se resuelve únicamente con delete_at IS NULL / IS NOT NULL.
 *
 * NOTA: a diferencia de operario/producción, esta tabla todavía no tiene
 * columnas js_session / js_historial, así que este controlador no registra
 * auditoría de cambios. Si luego quieres el mismo historial de movimientos,
 * agrega esas dos columnas jsonb y lo integramos igual que en operario.
 *
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

function controladorSucursal($accion)
{
    switch ($accion) {
        case 'LISTARSUCURSALES':
            listarSucursales();
            break;
        case 'OBTENERSUCURSAL':
            obtenerSucursal(intval($_POST['id'] ?? 0));
            break;
        case 'GUARDARSUCURSAL':
            guardarSucursal();
            break;
        case 'ELIMINARSUCURSAL':
            eliminarSucursal();
            break;
        case 'REACTIVARSUCURSAL':
            reactivarSucursal();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// SUCURSALES
// =============================================================================

function listarSucursales()
{
    $conectar = conectar_oll_BD();

    $texto       = trim($_POST['texto'] ?? '');
    $visibilidad = trim($_POST['visibilidad'] ?? 'activas'); // activas, eliminadas, todas

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(nombre) LIKE LOWER(:texto) OR LOWER(direccion) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }
    if ($visibilidad === 'eliminadas') {
        $where[] = "delete_at IS NOT NULL";
    } elseif ($visibilidad !== 'todas') {
        $where[] = "delete_at IS NULL";
    }

    $sql = "SELECT * FROM sucursal
            WHERE " . implode(' AND ', $where) . "
            ORDER BY nombre";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['sucursales' => $result]);
}

function obtenerSucursal($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $result = executeQuery($conectar, "SELECT * FROM sucursal WHERE id = :id", ['id' => $id]);
    if (empty($result)) responder(false, 'Sucursal no encontrada.');
    responder(true, 'OK', ['sucursal' => $result[0]]);
}

function guardarSucursal()
{
    $conectar = conectar_oll_BD();

    $id        = intval($_POST['id'] ?? 0);
    $nombre    = trim($_POST['nombre'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');

    if (empty($nombre)) responder(false, 'El nombre es obligatorio.');

    // Validar unicidad del nombre (excluyendo al propio registro en edición)
    $existe = executeQuery($conectar, "
        SELECT id FROM sucursal WHERE LOWER(nombre) = LOWER(:nombre) AND id <> :id
    ", ['nombre' => $nombre, 'id' => $id]);
    if (!empty($existe)) {
        responder(false, 'Ya existe una sucursal registrada con ese nombre.');
    }

    if ($id === 0) {
        $result = executeQuery($conectar, "
            INSERT INTO sucursal (nombre, direccion, created_at)
            VALUES (:nombre, :direccion, NOW())
            RETURNING id
        ", [
            'nombre'    => $nombre,
            'direccion' => $direccion !== '' ? $direccion : null,
        ]);
        $nuevo_id = $result[0]['id'] ?? null;
        responder(true, 'Sucursal creada correctamente.', ['id' => $nuevo_id, 'modo' => 'crear']);
    } else {
        $actual = executeQuery($conectar, "SELECT * FROM sucursal WHERE id = :id", ['id' => $id]);
        if (empty($actual)) responder(false, 'Sucursal no encontrada.');

        if (!empty($actual[0]['delete_at'])) {
            responder(false, 'No puedes editar una sucursal inactiva. Reactívala primero.');
        }

        executeQuery($conectar, "
            UPDATE sucursal SET
                nombre     = :nombre,
                direccion  = :direccion,
                update_at  = NOW()
            WHERE id = :id
        ", [
            'nombre'    => $nombre,
            'direccion' => $direccion !== '' ? $direccion : null,
            'id'        => $id,
        ]);
        responder(true, 'Sucursal actualizada correctamente.', ['id' => $id, 'modo' => 'editar']);
    }
}

function eliminarSucursal()
{
    $conectar = conectar_oll_BD();
    $id = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $actual = executeQuery($conectar, "SELECT delete_at FROM sucursal WHERE id = :id", ['id' => $id]);
    if (empty($actual)) responder(false, 'Sucursal no encontrada.');
    if (!empty($actual[0]['delete_at'])) responder(false, 'Esta sucursal ya estaba inactiva.');

    executeQuery($conectar, "
        UPDATE sucursal SET
            delete_at = NOW(),
            update_at = NOW()
        WHERE id = :id
    ", ['id' => $id]);

    responder(true, 'Sucursal desactivada correctamente.');
}

function reactivarSucursal()
{
    $conectar = conectar_oll_BD();
    $id = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $actual = executeQuery($conectar, "SELECT delete_at FROM sucursal WHERE id = :id", ['id' => $id]);
    if (empty($actual)) responder(false, 'Sucursal no encontrada.');
    if (empty($actual[0]['delete_at'])) responder(false, 'Esta sucursal ya estaba activa.');

    executeQuery($conectar, "
        UPDATE sucursal SET
            delete_at = NULL,
            update_at = NOW()
        WHERE id = :id
    ", ['id' => $id]);

    responder(true, 'Sucursal reactivada correctamente.');
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

// =============================================================================
// DISPATCH
// =============================================================================

if (isset($_POST["accion"])) {
    controladorSucursal($_POST["accion"]);
}