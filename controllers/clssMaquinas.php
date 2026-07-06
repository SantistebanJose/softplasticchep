<?php

/**
 * controllers/clssMaquinas.php
 * Controlador del módulo de Máquinas
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    controladorMaquinas($_POST["accion"]);
}

function controladorMaquinas($accion)
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
        case 'CAMBIARMOLDE':
            cambiarMolde();
            break;
        case 'HISTORIALMOLDES':
            historialMoldes(intval($_POST['maquina_id'] ?? 0));
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// MÁQUINAS
// =============================================================================

function listarMaquinas()
{
    $conectar = conectar_oll_BD();

    $texto  = trim($_POST['texto'] ?? '');
    $estado = trim($_POST['estado'] ?? ''); // '', 'activa', 'inactiva'

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(m.nombre) LIKE LOWER(:texto) OR LOWER(m.ubicacion) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }
    if ($estado === 'activa') {
        $where[] = "m.estado = 'activa'";
    } elseif ($estado === 'inactiva') {
        $where[] = "m.estado = 'inactiva'";
    }

    // El LEFT JOIN LATERAL trae, por cada máquina, el molde que tiene puesto
    // ahora mismo (el registro de maquina_moldes con fecha_fin IS NULL más reciente).
    $sql = "
        SELECT
            m.*,
            actual.molde_id      AS molde_actual_id,
            actual.molde_nombre  AS molde_actual_nombre,
            actual.molde_codigo  AS molde_actual_codigo,
            actual.fecha_inicio  AS molde_fecha_inicio
        FROM maquinas m
        LEFT JOIN LATERAL (
            SELECT mo.id AS molde_id, mo.nombre AS molde_nombre, mo.codigo AS molde_codigo, mm.fecha_inicio
            FROM maquina_moldes mm
            JOIN moldes mo ON mo.id = mm.molde_id
            WHERE mm.maquina_id = m.id AND mm.fecha_fin IS NULL
            ORDER BY mm.fecha_inicio DESC
            LIMIT 1
        ) actual ON TRUE
        WHERE " . implode(' AND ', $where) . "
        ORDER BY m.nombre
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
        "SELECT * FROM maquinas WHERE id = :id",
        ['id' => $id]
    );
    if (empty($result)) responder(false, 'Máquina no encontrada.');
    responder(true, 'OK', ['maquina' => $result[0]]);
}

function guardarMaquina()
{
    $conectar  = conectar_oll_BD();
    $id        = intval($_POST['id'] ?? 0);
    $nombre    = trim($_POST['nombre'] ?? '');
    $ubicacion = trim($_POST['ubicacion'] ?? '');

    // ── Validaciones ──────────────────────────────────────────────────────────
    if (empty($nombre))    responder(false, 'El nombre es obligatorio.');
    if (empty($ubicacion)) responder(false, 'La ubicación es obligatoria.');

    // Nombre único (excluyendo el propio registro si es edición)
    $chk = executeQuery(
        $conectar,
        "SELECT id FROM maquinas WHERE LOWER(nombre) = LOWER(:nombre) AND id <> :id",
        ['nombre' => $nombre, 'id' => $id]
    );
    if (!empty($chk)) responder(false, 'Ya existe una máquina con ese nombre.');

    if ($id === 0) {
        $result = executeQuery($conectar, "
            INSERT INTO maquinas (nombre, ubicacion, estado, created_at, updated_at)
            VALUES (:nombre, :ubicacion, 'activa', NOW(), NOW())
            RETURNING id
        ", [
            'nombre'    => $nombre,
            'ubicacion' => $ubicacion,
        ]);
        $nuevo_id = $result[0]['id'] ?? null;
        responder(true, 'Máquina creada correctamente.', ['id' => $nuevo_id, 'modo' => 'crear']);
    } else {
        executeQuery($conectar, "
            UPDATE maquinas SET
                nombre = :nombre,
                ubicacion = :ubicacion,
                updated_at = NOW()
            WHERE id = :id
        ", [
            'nombre'    => $nombre,
            'ubicacion' => $ubicacion,
            'id'        => $id,
        ]);
        responder(true, 'Máquina actualizada correctamente.', ['id' => $id, 'modo' => 'editar']);
    }
}

// Soft delete: se cambia el estado a 'inactiva', no se borra físicamente.
function eliminarMaquina()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, estado FROM maquinas WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Máquina no encontrada.');
    if ($existe[0]['estado'] === 'inactiva') responder(false, 'Esta máquina ya estaba inactiva.');

    executeQuery(
        $conectar,
        "UPDATE maquinas SET estado = 'inactiva', updated_at = NOW() WHERE id = :id",
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
        "UPDATE maquinas SET estado = 'activa', updated_at = NOW() WHERE id = :id",
        ['id' => $id]
    );
    responder(true, 'Máquina reactivada correctamente.');
}

// =============================================================================
// CAMBIO DE MOLDE
// =============================================================================

/**
 * Cierra el montaje de molde vigente de una máquina (si existe) y crea uno nuevo.
 * Esto conserva el historial completo en maquina_moldes (fecha_inicio / fecha_fin).
 */
function cambiarMolde()
{
    $conectar   = conectar_oll_BD();
    $maquina_id = intval($_POST['maquina_id'] ?? 0);
    $molde_id   = intval($_POST['molde_id'] ?? 0);

    if (!$maquina_id) responder(false, 'Máquina inválida.');
    if (!$molde_id)   responder(false, 'Debes seleccionar un molde.');

    $maquina = executeQuery(
        $conectar,
        "SELECT id FROM maquinas WHERE id = :id AND estado = 'activa'",
        ['id' => $maquina_id]
    );
    if (empty($maquina)) responder(false, 'Máquina no encontrada o inactiva.');

    $molde = executeQuery(
        $conectar,
        "SELECT id FROM moldes WHERE id = :id AND activo = true",
        ['id' => $molde_id]
    );
    if (empty($molde)) responder(false, 'Molde no encontrado o inactivo.');

    // Evitar "cambiar" al mismo molde que ya tiene puesto
    $actual = executeQuery(
        $conectar,
        "SELECT molde_id FROM maquina_moldes WHERE maquina_id = :mid AND fecha_fin IS NULL",
        ['mid' => $maquina_id]
    );
    if (!empty($actual) && intval($actual[0]['molde_id']) === $molde_id) {
        responder(false, 'Esa máquina ya tiene puesto ese molde.');
    }

    // Cierra el montaje anterior, si lo hay
    executeQuery(
        $conectar,
        "UPDATE maquina_moldes SET fecha_fin = NOW() WHERE maquina_id = :mid AND fecha_fin IS NULL",
        ['mid' => $maquina_id]
    );

    // Crea el nuevo montaje
    executeQuery(
        $conectar,
        "INSERT INTO maquina_moldes (maquina_id, molde_id, fecha_inicio) VALUES (:mid, :moid, NOW())",
        ['mid' => $maquina_id, 'moid' => $molde_id]
    );

    responder(true, 'Molde cambiado correctamente.');
}

/**
 * Historial de moldes montados en una máquina, del más reciente al más antiguo.
 * Útil si más adelante quieres mostrar una línea de tiempo por máquina.
 */
function historialMoldes($maquina_id)
{
    $conectar = conectar_oll_BD();
    if (!$maquina_id) responder(false, 'Máquina inválida.');

    $result = executeQuery($conectar, "
        SELECT mm.id, mo.nombre AS molde_nombre, mo.codigo AS molde_codigo,
               mm.fecha_inicio, mm.fecha_fin
        FROM maquina_moldes mm
        JOIN moldes mo ON mo.id = mm.molde_id
        WHERE mm.maquina_id = :mid
        ORDER BY mm.fecha_inicio DESC
    ", ['mid' => $maquina_id]);

    responder(true, 'OK', ['historial' => $result]);
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