<?php

/**
 * controllers/clssMaquina.php
 * Controlador del módulo de Máquinas
 * Tabla real: maquina (id, nombre, descripcion, estado, js_session, js_historial,
 *             created_at, update_at, deleted_at, sucursal_id)
 * estado: 'A' = Activa | 'M' = Mantenimiento
 * Soft delete vía deleted_at.
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 *
 * NOTA (sucursal_id):
 * - El usuario elige la sucursal desde un <select> en el modal de crear/editar.
 * - El listado NO filtra por sucursal (se muestran todas), solo se informa la
 *   sucursal de cada máquina como columna adicional.
 * - Se asume que la tabla `sucursal` tiene al menos las columnas (id, nombre).
 *   Si `sucursal` maneja soft delete (columna deleted_at), ajustar
 *   listarSucursalesCombo() para agregar "WHERE deleted_at IS NULL".
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
        case 'LISTARSUCURSALES':
            listarSucursalesCombo();
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
        $where[] = "LOWER(m.nombre) LIKE LOWER(:texto)";
        $params['texto'] = "%$texto%";
    }
    if ($estado === 'activa') {
        $where[] = "m.deleted_at IS NULL";
    } elseif ($estado === 'inactiva') {
        $where[] = "m.deleted_at IS NOT NULL";
    }
    if (in_array($estadoMaquina, ['A', 'M'], true)) {
        $where[] = "m.estado = :estado_maquina";
        $params['estado_maquina'] = $estadoMaquina;
    }

    // Se muestran TODAS las sucursales (sin filtro), solo se informa el nombre
    // de la sucursal de cada máquina vía LEFT JOIN.
    $sql = "
        SELECT m.*,
               s.nombre AS sucursal_nombre
        FROM maquina m
        LEFT JOIN sucursal s ON s.id = m.sucursal_id
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
        "SELECT m.*, s.nombre AS sucursal_nombre
         FROM maquina m
         LEFT JOIN sucursal s ON s.id = m.sucursal_id
         WHERE m.id = :id",
        ['id' => $id]
    );
    if (empty($result)) responder(false, 'Máquina no encontrada.');
    responder(true, 'OK', ['maquina' => $result[0]]);
}

/**
 * Combo de sucursales para el <select> del modal.
 * Ajustar el WHERE si `sucursal` maneja soft delete (deleted_at).
 */
function listarSucursalesCombo()
{
    $conectar = conectar_oll_BD();

    $result = executeQuery(
        $conectar,
        "SELECT id, nombre FROM sucursal ORDER BY nombre"
    );
    responder(true, 'OK', ['sucursales' => $result]);
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

/**
 * Traduce el código de estado de la máquina ('A'/'M') a texto legible.
 */
function textoEstadoMaquina(?string $estado): string
{
    if ($estado === 'A') return 'Activa';
    if ($estado === 'M') return 'Mantenimiento';
    return '(sin estado)';
}

/**
 * Resuelve el nombre de una sucursal a partir de su id, para dejar el
 * historial legible (en vez de solo el id numérico).
 */
function obtenerNombreSucursal($conectar, $sucursalId): ?string
{
    if (empty($sucursalId)) return null;

    $result = executeQuery(
        $conectar,
        "SELECT nombre FROM sucursal WHERE id = :id",
        ['id' => $sucursalId]
    );
    return $result[0]['nombre'] ?? null;
}

function guardarMaquina()
{
    $conectar    = conectar_oll_BD();
    $id          = intval($_POST['id'] ?? 0);
    $nombre      = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $estado      = trim($_POST['estado'] ?? 'A');
    $sucursalId  = !empty($_POST['sucursal_id']) ? intval($_POST['sucursal_id']) : null;

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

    // Si mandaron sucursal_id, verificamos que exista.
    if ($sucursalId !== null) {
        $chkSuc = executeQuery($conectar, "SELECT id FROM sucursal WHERE id = :id", ['id' => $sucursalId]);
        if (empty($chkSuc)) responder(false, 'La sucursal seleccionada no existe.');
    }

    // Mapa de campos editables → etiqueta legible para el historial
    $mapaCampos = [
        'nombre'          => 'Nombre',
        'descripcion'     => 'Descripción',
        'estado_legible'  => 'Estado de la máquina',
        'sucursal_legible'=> 'Sucursal',
    ];

    $datosNuevos = [
        'nombre'           => $nombre,
        'descripcion'      => $descripcion !== '' ? $descripcion : null,
        'estado_legible'   => textoEstadoMaquina($estado),
        'sucursal_legible' => obtenerNombreSucursal($conectar, $sucursalId),
    ];

    if ($id === 0) {
        // Creación: "antes" está vacío para todos los campos
        $cambios = compararCambios([], $datosNuevos, $mapaCampos);

        $movimiento          = obtenerMovimientoSesion('crear', $cambios);
        $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        $result = executeQuery($conectar, "
            INSERT INTO maquina (nombre, descripcion, estado, sucursal_id, created_at, js_session, js_historial)
            VALUES (:nombre, :descripcion, :estado, :sucursal_id, NOW(), :js_session, :js_historial)
            RETURNING id
        ", [
            'nombre'       => $nombre,
            'descripcion'  => $descripcion !== '' ? $descripcion : null,
            'estado'       => $estado,
            'sucursal_id'  => $sucursalId,
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
        ]);
        $nuevo_id = $result[0]['id'] ?? null;
        responder(true, 'Máquina creada correctamente.', ['id' => $nuevo_id, 'modo' => 'crear']);
    } else {
        // Edición: traemos el registro actual para comparar campo por campo
        $actual = executeQuery($conectar, "SELECT * FROM maquina WHERE id = :id", ['id' => $id]);
        if (empty($actual)) responder(false, 'Máquina no encontrada.');
        $registroAnterior = $actual[0];

        // Traducimos estado y sucursal anteriores a texto legible antes de comparar
        $registroAnterior['estado_legible']   = textoEstadoMaquina($registroAnterior['estado']);
        $registroAnterior['sucursal_legible'] = obtenerNombreSucursal($conectar, $registroAnterior['sucursal_id'] ?? null);

        $cambios = compararCambios($registroAnterior, $datosNuevos, $mapaCampos);

        $movimiento          = obtenerMovimientoSesion('editar', $cambios);
        $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeQuery($conectar, "
            UPDATE maquina SET
                nombre       = :nombre,
                descripcion  = :descripcion,
                estado       = :estado,
                sucursal_id  = :sucursal_id,
                update_at    = NOW(),
                js_session   = :js_session,
                js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id
        ", [
            'nombre'       => $nombre,
            'descripcion'  => $descripcion !== '' ? $descripcion : null,
            'estado'       => $estado,
            'sucursal_id'  => $sucursalId,
            'id'           => $id,
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
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
        "UPDATE maquina SET
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
    responder(true, 'Máquina desactivada correctamente.');
}

function reactivarMaquina()
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
        "UPDATE maquina SET
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