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

/**
 * Obtiene la IP real del cliente, considerando proxies/balanceadores comunes.
 */
function obtenerIpCliente(): string
{
    // Si el servidor está detrás de un proxy (Cloudflare, Nginx, etc.)
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Puede venir una lista "ip_cliente, ip_proxy1, ip_proxy2" — tomamos la primera
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        return trim($_SERVER['HTTP_X_REAL_IP']);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'N/A';
}

/**
 * Arma el bloque de auditoría (usuario/sesión) para un movimiento dado.
 * $cambios: arreglo de ['campo' => .., 'valor_antes' => .., 'valor_despues' => ..]
 */
function obtenerMovimientoSesion(string $accion, array $cambios = []): array
{
    return [
        'usuario'   => $_SESSION['usuario_id'] ?? 'Sistema',
        'nombre'    => $_SESSION['nombre_usuario'] ?? 'Usuario Desconocido',
        'user'      => $_SESSION['user_usuario'] ?? 'N/A',
        'perfiles'  => $_SESSION['perfiles'] ?? 'N/A',
        'rol'       => $_SESSION['rol_usuario'] ?? 'N/A',
        'accion'    => $accion,
        'ip'        => obtenerIpCliente(),
        'cambios'   => $cambios,
        'timestamp' => date('Y-m-d H:i:s'),
    ];
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

    // Mapa de campos editables → etiqueta legible para el historial
    $mapaCampos = [
        'nombre'       => 'Nombre',
        'nombre_corto' => 'Abreviatura',
    ];

    $datosNuevos = [
        'nombre'       => $nombre,
        'nombre_corto' => $nombreCorto,
    ];

    if ($id === 0) {
        // Creación: "antes" está vacío para todos los campos
        $cambios = compararCambios([], $datosNuevos, $mapaCampos);

        $movimiento          = obtenerMovimientoSesion('crear', $cambios);
        $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        $result = executeQuery($conectar, "
            INSERT INTO unidad_medida (nombre, nombre_corto, created_at, js_session, js_historial)
            VALUES (:nombre, :nombre_corto, NOW(), :js_session, :js_historial)
            RETURNING id
        ", [
            'nombre'       => $datosNuevos['nombre'],
            'nombre_corto' => $datosNuevos['nombre_corto'],
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
        ]);
        $nuevo_id = $result[0]['id'] ?? null;
        responder(true, 'Unidad de medida creada correctamente.', ['id' => $nuevo_id, 'modo' => 'crear']);
    } else {
        // Edición: traemos el registro actual para comparar campo por campo
        $actual = executeQuery($conectar, "SELECT * FROM unidad_medida WHERE id = :id", ['id' => $id]);
        if (empty($actual)) responder(false, 'Unidad de medida no encontrada.');
        $registroAnterior = $actual[0];

        $cambios = compararCambios($registroAnterior, $datosNuevos, $mapaCampos);

        $movimiento          = obtenerMovimientoSesion('editar', $cambios);
        $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeQuery($conectar, "
            UPDATE unidad_medida SET
                nombre       = :nombre,
                nombre_corto = :nombre_corto,
                update_at    = NOW(),
                js_session   = :js_session,
                js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id
        ", [
            'nombre'       => $datosNuevos['nombre'],
            'nombre_corto' => $datosNuevos['nombre_corto'],
            'id'           => $id,
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
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
        "UPDATE unidad_medida SET
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
    responder(true, 'Unidad de medida desactivada correctamente.');
}

function reactivarUnidadMedida()
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
        "UPDATE unidad_medida SET
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