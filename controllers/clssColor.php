<?php

/**
 * controllers/clssColor.php
 * Controlador del módulo de Colores
 * Tabla real: color (id, nombre, descripcion, rgb, js_session, js_historial,
 *             created_at, update_at, deleted_at)
 * Soft delete vía deleted_at.
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
require_once __DIR__ . '/auditoria.php';
require_once __DIR__ . '/clssVerificarSession.php';

if (isset($_POST['accion'])) {
    try {
        controladorColor((string) $_POST['accion']);
    } catch (PDOException $e) {
        error_log('Error de base de datos en clssColor.php: ' . $e->getMessage());
        responder(false, 'Error de base de datos.');
    } catch (Throwable $e) {
        error_log('Error inesperado en clssColor.php: ' . $e->getMessage());
        responder(false, 'Error inesperado en el servidor.');
    }
}

function controladorColor(string $accion): void
{
    iniciarSesionSegura();

    $accionesLectura = [
        'LISTARCOLORES',
        'OBTENERCOLOR',
    ];

    $accionesAdministracion = [
        'GUARDARCOLOR',
        'ELIMINARCOLOR',
        'REACTIVARCOLOR',
    ];

    if (!empty($_SESSION['operario_id'])) {
        if ($accion !== 'LISTARCOLORES') {
            responderAcceso(403, 'No tienes permiso para realizar esta acción.');
        }
    } else {
        $usuario = exigirSesion();
        if (in_array($accion, $accionesLectura, true)) {
            exigirRol($usuario, ['administrador', 'produccion', 'consulta']);
        } elseif (in_array($accion, $accionesAdministracion, true)) {
            exigirRol($usuario, ['administrador']);
        } else {
            responderAcceso(400, 'Acción no reconocida.');
        }
    }

    switch ($accion) {
        case 'LISTARCOLORES':
            listarColores();
            break;

        case 'OBTENERCOLOR':
            obtenerColor((int) ($_POST['id'] ?? 0));
            break;

        case 'GUARDARCOLOR':
            guardarColor();
            break;

        case 'ELIMINARCOLOR':
            eliminarColor();
            break;

        case 'REACTIVARCOLOR':
            reactivarColor();
            break;
    }
}

// =============================================================================
// COLOR
// =============================================================================

function listarColores()
{
    $conectar = conectar_oll_BD();

    $texto  = trim($_POST['texto'] ?? '');
    $estado = trim($_POST['estado'] ?? ''); // '', 'activa', 'inactiva'

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

    $sql = "
        SELECT *
        FROM color
        WHERE " . implode(' AND ', $where) . "
        ORDER BY nombre
    ";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['colores' => $result]);
}

function obtenerColor($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $result = executeQuery(
        $conectar,
        "SELECT * FROM color WHERE id = :id",
        ['id' => $id]
    );
    if (empty($result)) responder(false, 'Color no encontrado.');
    responder(true, 'OK', ['color' => $result[0]]);
}



/**
 * Compara un registro anterior (array asociativo de la BD) contra los datos nuevos
 * y devuelve solo los campos cuyo valor cambió, mapeados con etiqueta legible.
 *
 * $mapaCampos: ['columna_bd' => 'Etiqueta bonita']
 * $anterior:   registro actual tal cual viene de la BD (o [] si es creación)
 * $nuevo:      ['columna_bd' => valor_nuevo]
 */
function compararCambios(array $anterior, array $nuevo, array $mapaCampos): array
{
    $cambios = [];
    foreach ($mapaCampos as $campo => $etiqueta) {
        $valorAntes   = $anterior[$campo] ?? null;
        $valorDespues = $nuevo[$campo]    ?? null;

        // Normalizamos vacíos para comparar de forma justa (null vs '' se tratan igual)
        $antesComp   = ($valorAntes   === '' ? null : $valorAntes);
        $despuesComp = ($valorDespues === '' ? null : $valorDespues);

        if ($antesComp !== $despuesComp) {
            $cambios[] = [
                'campo'         => $etiqueta,
                'valor_antes'   => $valorAntes   ?? '(vacío)',
                'valor_despues' => $valorDespues ?? '(vacío)',
            ];
        }
    }
    return $cambios;
}

function guardarColor()
{
    $conectar    = conectar_oll_BD();
    $id          = intval($_POST['id'] ?? 0);
    $nombre      = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $rgb         = trim($_POST['rgb'] ?? '');

    // ── Validaciones ──────────────────────────────────────────────────────────
    if (empty($nombre)) responder(false, 'El nombre es obligatorio.');

    // Validación ligera de formato: HEX (#fff / #ffffff) o rgb(0,0,0) / rgba(0,0,0,1)
    if ($rgb !== '' && !preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $rgb)
        && !preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*[\d.]+\s*)?\)$/i', $rgb)) {
        responder(false, 'El formato de color no es válido. Usa HEX (#RRGGBB) o rgb(r,g,b).');
    }

    // Nombre único (excluyendo el propio registro si es edición)
    $chk = executeQuery(
        $conectar,
        "SELECT id FROM color WHERE LOWER(nombre) = LOWER(:nombre) AND id <> :id",
        ['nombre' => $nombre, 'id' => $id]
    );
    if (!empty($chk)) responder(false, 'Ya existe un color con ese nombre.');

    // Mapa de campos editables → etiqueta legible para el historial
    $mapaCampos = [
        'nombre'      => 'Nombre',
        'descripcion' => 'Descripción',
        'rgb'         => 'RGB/HEX',
    ];

    $datosNuevos = [
        'nombre'      => $nombre,
        'descripcion' => $descripcion !== '' ? $descripcion : null,
        'rgb'         => $rgb !== '' ? $rgb : null,
    ];

    if ($id === 0) {
        // Creación: "antes" está vacío para todos los campos
        $cambios = compararCambios([], $datosNuevos, $mapaCampos);

        $movimiento          = obtenerMovimientoSesion('crear', $cambios);
        $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        $result = executeQuery($conectar, "
            INSERT INTO color (nombre, descripcion, rgb, created_at, js_session, js_historial)
            VALUES (:nombre, :descripcion, :rgb, NOW(), :js_session, :js_historial)
            RETURNING id
        ", [
            'nombre'       => $datosNuevos['nombre'],
            'descripcion'  => $datosNuevos['descripcion'],
            'rgb'          => $datosNuevos['rgb'],
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
        ]);
        $nuevo_id = $result[0]['id'] ?? null;
        responder(true, 'Color creado correctamente.', ['id' => $nuevo_id, 'modo' => 'crear']);
    } else {
        // Edición: traemos el registro actual para comparar campo por campo
        $actual = executeQuery($conectar, "SELECT * FROM color WHERE id = :id", ['id' => $id]);
        if (empty($actual)) responder(false, 'Color no encontrado.');
        $registroAnterior = $actual[0];

        $cambios = compararCambios($registroAnterior, $datosNuevos, $mapaCampos);

        $movimiento          = obtenerMovimientoSesion('editar', $cambios);
        $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeQuery($conectar, "
            UPDATE color SET
                nombre       = :nombre,
                descripcion  = :descripcion,
                rgb          = :rgb,
                update_at    = NOW(),
                js_session   = :js_session,
                js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id
        ", [
            'nombre'       => $datosNuevos['nombre'],
            'descripcion'  => $datosNuevos['descripcion'],
            'rgb'          => $datosNuevos['rgb'],
            'id'           => $id,
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
        ]);
        responder(true, 'Color actualizado correctamente.', ['id' => $id, 'modo' => 'editar']);
    }
}

// Soft delete: se marca deleted_at, no se borra físicamente.
function eliminarColor()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at FROM color WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Color no encontrado.');
    if (!empty($existe[0]['deleted_at'])) {
        responder(false, 'Este color ya estaba inactivo.');
    }

    $cambios = [[
        'campo'         => 'Estado',
        'valor_antes'   => 'Activo',
        'valor_despues' => 'Inactivo',
    ]];

    $movimiento          = obtenerMovimientoSesion('desactivar', $cambios);
    $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeQuery(
        $conectar,
        "UPDATE color SET
            deleted_at   = NOW(),
            update_at    = NOW(),
            js_session   = :js_session,
            js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id",
        [
            'id'           => $id,
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
        ]
    );
    responder(true, 'Color desactivado correctamente.');
}

function reactivarColor()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $cambios = [[
        'campo'         => 'Estado',
        'valor_antes'   => 'Inactivo',
        'valor_despues' => 'Activo',
    ]];

    $movimiento          = obtenerMovimientoSesion('reactivar', $cambios);
    $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeQuery(
        $conectar,
        "UPDATE color SET
            deleted_at   = NULL,
            update_at    = NOW(),
            js_session   = :js_session,
            js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id",
        [
            'id'           => $id,
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
        ]
    );
    responder(true, 'Color reactivado correctamente.');
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
