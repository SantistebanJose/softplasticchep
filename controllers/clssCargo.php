<?php

/**
 * controllers/clssCargo.php
 * Controlador del módulo de Cargo
 * Tabla real: cargo (id, nombre, orden, deleted_at, area_id)
 * area_id -> area.id (FK real). Cada vez que un cargo se crea, se edita
 * (cambiando o no de área), se desactiva o se reactiva, se recalcula el
 * js_cargos denormalizado de la(s) área(s) involucrada(s) llamando a
 * sincronizarJsCargosArea() definida en clssArea.php.
 * Soft delete vía deleted_at (no existe columna 'activo').
 * No tiene created_at/update_at ni js_session/js_historial: este catálogo
 * es simple, sin auditoría de cambios.
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
require_once __DIR__ . '/clssArea.php'; // trae sincronizarJsCargosArea() (guard evita doble dispatch)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_POST["accion"])) {
    controladorCargo($_POST["accion"]);
}

function controladorCargo($accion)
{
    switch ($accion) {
        case 'LISTARCARGOS':
            listarCargos();
            break;
        case 'OBTENERCARGO':
            obtenerCargo(intval($_POST['id'] ?? 0));
            break;
        case 'GUARDARCARGO':
            guardarCargo();
            break;
        case 'ELIMINARCARGO':
            eliminarCargo();
            break;
        case 'REACTIVARCARGO':
            reactivarCargo();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// CARGO
// =============================================================================

function listarCargos()
{
    $conectar = conectar_oll_BD();

    $texto   = trim($_POST['texto'] ?? '');
    $estado  = trim($_POST['estado'] ?? '');   // '', 'activa', 'inactiva'
    $areaId  = intval($_POST['area_id'] ?? 0); // 0 = sin filtro por área

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "LOWER(c.nombre) LIKE LOWER(:texto)";
        $params['texto'] = "%$texto%";
    }
    if ($estado === 'activa') {
        $where[] = "c.deleted_at IS NULL";
    } elseif ($estado === 'inactiva') {
        $where[] = "c.deleted_at IS NOT NULL";
    }
    if ($areaId) {
        $where[] = "c.area_id = :area_id";
        $params['area_id'] = $areaId;
    }

    $sql = "
        SELECT c.*, a.nombre AS area_nombre
        FROM cargo c
        LEFT JOIN area a ON a.id = c.area_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.orden, c.nombre
    ";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['cargos' => $result]);
}

function obtenerCargo($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $result = executeQuery(
        $conectar,
        "SELECT c.*, a.nombre AS area_nombre
         FROM cargo c
         LEFT JOIN area a ON a.id = c.area_id
         WHERE c.id = :id",
        ['id' => $id]
    );
    if (empty($result)) responder(false, 'Cargo no encontrado.');

    responder(true, 'OK', ['cargo' => $result[0]]);
}

function guardarCargo()
{
    $conectar = conectar_oll_BD();
    $id      = intval($_POST['id'] ?? 0);
    $nombre  = mb_strtoupper(trim($_POST['nombre'] ?? ''), 'UTF-8');   // ← antes: trim($_POST['nombre'] ?? '')
    $orden   = $_POST['orden'] ?? null;
    $orden   = ($orden === '' || $orden === null) ? 0 : intval($orden);
    $areaId  = intval($_POST['area_id'] ?? 0);

    // ── Validaciones ──────────────────────────────────────────────────────────
    if (empty($nombre)) responder(false, 'El nombre es obligatorio.');
    if (!$areaId) responder(false, 'El área es obligatoria.');

    // El área debe existir y estar activa
    $area = executeQuery(
        $conectar,
        "SELECT id FROM area WHERE id = :id AND deleted_at IS NULL",
        ['id' => $areaId]
    );
    if (empty($area)) responder(false, 'El área seleccionada no existe o está inactiva.');

    // Nombre único (excluyendo el propio registro si es edición)
    $chk = executeQuery(
        $conectar,
        "SELECT id FROM cargo WHERE LOWER(nombre) = LOWER(:nombre) AND id <> :id",
        ['nombre' => $nombre, 'id' => $id]
    );
    if (!empty($chk)) responder(false, 'Ya existe un cargo con ese nombre.');

    if ($id === 0) {
        // Creación
        $result = executeQuery($conectar, "
            INSERT INTO cargo (nombre, orden, area_id)
            VALUES (:nombre, :orden, :area_id)
            RETURNING id
        ", [
            'nombre'  => $nombre,
            'orden'   => $orden,
            'area_id' => $areaId,
        ]);
        $nuevo_id = $result[0]['id'] ?? null;

        sincronizarJsCargosArea($conectar, $areaId);

        responder(true, 'Cargo creado correctamente.', ['id' => $nuevo_id, 'modo' => 'crear']);
    } else {
        // Edición
        $actual = executeQuery($conectar, "SELECT id, area_id FROM cargo WHERE id = :id", ['id' => $id]);
        if (empty($actual)) responder(false, 'Cargo no encontrado.');

        $areaIdAnterior = $actual[0]['area_id'] ? intval($actual[0]['area_id']) : null;

        executeQuery($conectar, "
            UPDATE cargo SET
                nombre  = :nombre,
                orden   = :orden,
                area_id = :area_id
            WHERE id = :id
        ", [
            'nombre'  => $nombre,
            'orden'   => $orden,
            'area_id' => $areaId,
            'id'      => $id,
        ]);

        // Sincroniza la nueva área siempre...
        sincronizarJsCargosArea($conectar, $areaId);
        // ...y si cambió de área, también la anterior (para que deje de aparecer ahí).
        if ($areaIdAnterior && $areaIdAnterior !== $areaId) {
            sincronizarJsCargosArea($conectar, $areaIdAnterior);
        }

        responder(true, 'Cargo actualizado correctamente.', ['id' => $id, 'modo' => 'editar']);
    }
}

// Soft delete: se marca deleted_at, no se borra físicamente.
function eliminarCargo()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at, area_id FROM cargo WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Cargo no encontrado.');
    if (!empty($existe[0]['deleted_at'])) {
        responder(false, 'Este cargo ya estaba inactivo.');
    }

    executeQuery(
        $conectar,
        "UPDATE cargo SET deleted_at = NOW() WHERE id = :id",
        ['id' => $id]
    );

    if (!empty($existe[0]['area_id'])) {
        sincronizarJsCargosArea($conectar, intval($existe[0]['area_id']));
    }

    responder(true, 'Cargo desactivado correctamente.');
}

function reactivarCargo()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at, area_id FROM cargo WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Cargo no encontrado.');
    if (empty($existe[0]['deleted_at'])) {
        responder(false, 'Este cargo ya estaba activo.');
    }

    executeQuery(
        $conectar,
        "UPDATE cargo SET deleted_at = NULL WHERE id = :id",
        ['id' => $id]
    );

    if (!empty($existe[0]['area_id'])) {
        sincronizarJsCargosArea($conectar, intval($existe[0]['area_id']));
    }

    responder(true, 'Cargo reactivado correctamente.');
}

// =============================================================================
// HELPER
// =============================================================================

if (!function_exists('responder')) {
    function responder(bool $ok, string $msg, array $extra = []): void
    {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
        exit;
    }
}