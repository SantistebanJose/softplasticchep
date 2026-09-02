<?php

/**
 * controllers/clssArea.php
 * Controlador del módulo de Área
 * Tabla real: area (id, nombre, descripcion, orden, js_cargos, created_at, updated_at, deleted_at)
 * js_cargos: jsonb array denormalizado [{cargo_id, nombre}, ...] con los cargos
 *   ACTIVOS que pertenecen a esta área (cargo.area_id -> area.id es la FK real).
 *   Se recalcula automáticamente desde clssCargo.php cada vez que se
 *   crea/edita/desactiva/reactiva un cargo — no se edita a mano desde aquí.
 * Soft delete vía deleted_at (no existe columna 'activo').
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 *
 * IMPORTANTE: este archivo es incluido con require_once desde clssCargo.php
 * (para reutilizar sincronizarJsCargosArea). Como ambos comparten el mismo
 * $_POST, el dispatcher de abajo solo debe ejecutarse cuando ESTE archivo
 * es el que fue llamado directamente por el navegador/fetch, no cuando
 * llega "de paso" vía require_once desde otro controlador.
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function controladorArea($accion)
{
    switch ($accion) {
        case 'LISTARAREAS':
            listarAreas();
            break;
        case 'OBTENERAREA':
            obtenerArea(intval($_POST['id'] ?? 0));
            break;
        case 'GUARDARAREA':
            guardarArea();
            break;
        case 'ELIMINARAREA':
            eliminarArea();
            break;
        case 'REACTIVARAREA':
            reactivarArea();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// AREA
// =============================================================================

function listarAreas()
{
    $conectar = conectar_oll_BD();

    $texto  = trim($_POST['texto'] ?? '');
    $estado = trim($_POST['estado'] ?? ''); // '', 'activa', 'inactiva'

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(a.nombre) LIKE LOWER(:texto) OR LOWER(COALESCE(a.descripcion,'')) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }
    if ($estado === 'activa') {
        $where[] = "a.deleted_at IS NULL";
    } elseif ($estado === 'inactiva') {
        $where[] = "a.deleted_at IS NOT NULL";
    }

    $sql = "
        SELECT a.*
        FROM area a
        WHERE " . implode(' AND ', $where) . "
        ORDER BY a.orden, a.nombre
    ";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['areas' => decodificarJsCargosLista($result)]);
}

function obtenerArea($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $result = executeQuery(
        $conectar,
        "SELECT * FROM area WHERE id = :id",
        ['id' => $id]
    );
    if (empty($result)) responder(false, 'Área no encontrada.');

    $area = $result[0];
    $area['js_cargos'] = json_decode($area['js_cargos'] ?? '[]', true) ?: [];
    responder(true, 'OK', ['area' => $area]);
}

/**
 * Decodifica js_cargos (texto jsonb) de cada fila en un array PHP real,
 * para que el JSON de salida no venga como string escapado.
 */
function decodificarJsCargosLista(array $filas): array
{
    foreach ($filas as &$fila) {
        $fila['js_cargos'] = json_decode($fila['js_cargos'] ?? '[]', true) ?: [];
    }
    unset($fila);
    return $filas;
}

function guardarArea()
{
    $conectar    = conectar_oll_BD();
    $id          = intval($_POST['id'] ?? 0);
    $nombre      = mb_strtoupper(trim($_POST['nombre'] ?? ''), 'UTF-8');   // ← antes: trim($_POST['nombre'] ?? '')
    $descripcion = trim($_POST['descripcion'] ?? '');
    $orden       = $_POST['orden'] ?? null;
    $orden       = ($orden === '' || $orden === null) ? 0 : intval($orden);

    // ── Validaciones ──────────────────────────────────────────────────────────
    if (empty($nombre)) responder(false, 'El nombre es obligatorio.');

    // Nombre único (excluyendo el propio registro si es edición)
    $chk = executeQuery(
        $conectar,
        "SELECT id FROM area WHERE LOWER(nombre) = LOWER(:nombre) AND id <> :id",
        ['nombre' => $nombre, 'id' => $id]
    );
    if (!empty($chk)) responder(false, 'Ya existe un área con ese nombre.');

    if ($id === 0) {
        // Creación
        $result = executeQuery($conectar, "
            INSERT INTO area (nombre, descripcion, orden, created_at)
            VALUES (:nombre, :descripcion, :orden, NOW())
            RETURNING id
        ", [
            'nombre'      => $nombre,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
            'orden'       => $orden,
        ]);
        $nuevo_id = $result[0]['id'] ?? null;
        responder(true, 'Área creada correctamente.', ['id' => $nuevo_id, 'modo' => 'crear']);
    } else {
        // Edición
        $actual = executeQuery($conectar, "SELECT id FROM area WHERE id = :id", ['id' => $id]);
        if (empty($actual)) responder(false, 'Área no encontrada.');

        executeQuery($conectar, "
            UPDATE area SET
                nombre      = :nombre,
                descripcion = :descripcion,
                orden       = :orden,
                updated_at  = NOW()
            WHERE id = :id
        ", [
            'nombre'      => $nombre,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
            'orden'       => $orden,
            'id'          => $id,
        ]);
        responder(true, 'Área actualizada correctamente.', ['id' => $id, 'modo' => 'editar']);
    }
}

// Soft delete: se marca deleted_at, no se borra físicamente.
function eliminarArea()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at FROM area WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Área no encontrada.');
    if (!empty($existe[0]['deleted_at'])) {
        responder(false, 'Esta área ya estaba inactiva.');
    }

    // No permitir desactivar un área que todavía tiene cargos activos asignados
    $cargosActivos = executeQuery(
        $conectar,
        "SELECT id FROM cargo WHERE area_id = :id AND deleted_at IS NULL",
        ['id' => $id]
    );
    if (!empty($cargosActivos)) {
        responder(false, 'No puedes desactivar esta área: todavía tiene cargos activos asignados.');
    }

    executeQuery(
        $conectar,
        "UPDATE area SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id",
        ['id' => $id]
    );
    responder(true, 'Área desactivada correctamente.');
}

function reactivarArea()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at FROM area WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Área no encontrada.');
    if (empty($existe[0]['deleted_at'])) {
        responder(false, 'Esta área ya estaba activa.');
    }

    executeQuery(
        $conectar,
        "UPDATE area SET deleted_at = NULL, updated_at = NOW() WHERE id = :id",
        ['id' => $id]
    );
    responder(true, 'Área reactivada correctamente.');
}

/**
 * Recalcula el js_cargos denormalizado de un área a partir de sus cargos
 * activos actuales. La llama clssCargo.php cada vez que un cargo cambia
 * de área, se crea, se edita, se desactiva o se reactiva.
 * Definida aquí para mantener toda la lógica de 'area' en un solo archivo;
 * clssCargo.php la incluye con require_once.
 */
if (!function_exists('sincronizarJsCargosArea')) {
    function sincronizarJsCargosArea($conectar, ?int $areaId): void
    {
        if (!$areaId) return;

        $cargos = executeQuery(
            $conectar,
            "SELECT id, nombre FROM cargo WHERE area_id = :area_id AND deleted_at IS NULL ORDER BY orden, nombre",
            ['area_id' => $areaId]
        );

        $jsCargos = array_map(fn($c) => [
            'cargo_id' => (int) $c['id'],
            'nombre'   => $c['nombre'],
        ], $cargos);

        executeQuery(
            $conectar,
            "UPDATE area SET js_cargos = :js_cargos, updated_at = NOW() WHERE id = :id",
            [
                'js_cargos' => json_encode($jsCargos, JSON_UNESCAPED_UNICODE),
                'id'        => $areaId,
            ]
        );
    }
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



// =============================================================================
// DISPATCHER — solo se ejecuta si ESTE archivo fue llamado directamente
// (no cuando clssCargo.php lo incluye vía require_once para reutilizar
// sincronizarJsCargosArea)
// =============================================================================
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    controladorArea($_POST['accion'] ?? '');
}