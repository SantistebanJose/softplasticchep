<?php

/**
 * controllers/clssOrdenProduccion.php
 * Controlador del módulo de Orden de Producción
 * Tabla real: orden_produccion (id, codigo, producto_id, maquina, cantidad, estado,
 *             fecha_inicio, fecha_fin, created_at, updated_at, deleted_at,
 *             js_session, js_historial)
 *
 * Soft delete vía deleted_at (mismo patrón que 'molde').
 * Además, el campo 'estado' maneja el flujo propio de la orden:
 *   pendiente -> en_proceso -> completada
 *              \-> cancelada
 *
 * Solo se puede desactivar (soft delete) una orden mientras sigue 'pendiente'.
 * Al reactivar, la orden vuelve a quedar 'pendiente'.
 *
 * NOTA: Se asume una tabla "producto" (id, nombre) para el combo de productos.
 * Si el nombre real de la tabla/columnas es distinto, ajustar solo la función
 * listarProductos().
 *
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

function controladorOrdenProduccion($accion)
{
    switch ($accion) {
        case 'LISTARORDENES':
            listarOrdenes();
            break;
        case 'OBTENERORDEN':
            obtenerOrden(intval($_POST['id'] ?? 0));
            break;
        case 'GUARDARORDEN':
            guardarOrden();
            break;
        case 'INICIARORDEN':
            iniciarOrden();
            break;
        case 'FINALIZARORDEN':
            finalizarOrden();
            break;
        case 'CANCELARORDEN':
            cancelarOrden();
            break;
        case 'ELIMINARORDEN':
            eliminarOrden();
            break;
        case 'REACTIVARORDEN':
            reactivarOrden();
            break;
        case 'LISTARPRODUCTOS':
            listarProductos();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// AUDITORÍA (mismo patrón que clssMoldes.php)
// =============================================================================

/**
 * Obtiene la IP real del cliente, considerando proxies/balanceadores comunes.
 */
function obtenerIpCliente(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
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
 * Registra un movimiento de auditoría sobre una orden ya existente:
 * actualiza js_session (último movimiento) y agrega el movimiento al arreglo
 * js_historial. No toca ninguna otra columna.
 */
function registrarMovimiento($conectar, int $id, string $accion, array $cambios): void
{
    $movimiento         = obtenerMovimientoSesion($accion, $cambios);
    $js_session         = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial_nuevo = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeQuery($conectar, "
        UPDATE orden_produccion SET
            js_session   = :js_session,
            js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id
    ", [
        'id'           => $id,
        'js_session'   => $js_session,
        'js_historial' => $js_historial_nuevo,
    ]);
}

// =============================================================================
// ÓRDENES DE PRODUCCIÓN
// =============================================================================

const ESTADOS_VALIDOS = ['pendiente', 'en_proceso', 'completada', 'cancelada'];

function listarOrdenes()
{
    $conectar = conectar_oll_BD();

    $texto       = trim($_POST['texto'] ?? '');
    $estado      = trim($_POST['estado'] ?? '');       // '', pendiente, en_proceso, completada, cancelada
    $visibilidad = trim($_POST['visibilidad'] ?? 'activas'); // activas, eliminadas, todas

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(op.codigo) LIKE LOWER(:texto)
                      OR LOWER(op.maquina) LIKE LOWER(:texto)
                      OR LOWER(p.descripcion) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }
    if (in_array($estado, ESTADOS_VALIDOS, true)) {
        $where[] = "op.estado = :estado";
        $params['estado'] = $estado;
    }
    if ($visibilidad === 'eliminadas') {
        $where[] = "op.deleted_at IS NOT NULL";
    } elseif ($visibilidad !== 'todas') {
        // por defecto solo activas
        $where[] = "op.deleted_at IS NULL";
    }

    $sql = "SELECT op.*, p.descripcion AS producto_nombre
            FROM orden_produccion op
            LEFT JOIN producto p ON p.id = op.producto_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY op.created_at DESC";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['ordenes' => $result]);
}

function obtenerOrden($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $result = executeQuery(
        $conectar,
        "SELECT op.*, p.descripcion AS producto_nombre
         FROM orden_produccion op
         LEFT JOIN producto p ON p.id = op.producto_id
         WHERE op.id = :id",
        ['id' => $id]
    );
    if (empty($result)) responder(false, 'Orden no encontrada.');
    responder(true, 'OK', ['orden' => $result[0]]);
}

function listarProductos()
{
    $conectar = conectar_oll_BD();
    // Tabla real: producto (id, codigo, descripcion, activo, ...)
    // Se alias 'descripcion' como 'nombre' para no tocar el resto del código/vista.
    $result = executeQuery(
        $conectar,
        "SELECT id, codigo, descripcion AS nombre
         FROM producto
         WHERE activo = true
         ORDER BY descripcion"
    );
    responder(true, 'OK', ['productos' => $result]);
}

function guardarOrden()
{
    $conectar = conectar_oll_BD();

    $id          = intval($_POST['id'] ?? 0);
    $codigo      = trim($_POST['codigo'] ?? '');
    $producto_id = intval($_POST['producto_id'] ?? 0);
    $maquina     = trim($_POST['maquina'] ?? '');
    $cantidad    = intval($_POST['cantidad'] ?? 0);

    // ── Validaciones ─────────────────────────────────────────────────────
    if (empty($codigo))    responder(false, 'El código es obligatorio.');
    if (!$producto_id)     responder(false, 'Debes seleccionar un producto.');
    if ($cantidad <= 0)    responder(false, 'La cantidad debe ser mayor a 0.');

    // Código único (excluyendo el propio registro si es edición)
    $chk = executeQuery(
        $conectar,
        "SELECT id FROM orden_produccion WHERE LOWER(codigo) = LOWER(:codigo) AND id <> :id",
        ['codigo' => $codigo, 'id' => $id]
    );
    if (!empty($chk)) responder(false, 'Ya existe una orden con ese código.');

    // El producto debe existir
    $prod = executeQuery($conectar, "SELECT id FROM producto WHERE id = :id", ['id' => $producto_id]);
    if (empty($prod)) responder(false, 'El producto seleccionado no existe.');

    // Mapa de campos editables → etiqueta legible para el historial
    $mapaCampos = [
        'codigo'      => 'Código',
        'producto_id' => 'Producto',
        'maquina'     => 'Máquina',
        'cantidad'    => 'Cantidad',
    ];

    $datosNuevos = [
        'codigo'      => $codigo,
        'producto_id' => $producto_id,
        'maquina'     => $maquina !== '' ? $maquina : null,
        'cantidad'    => $cantidad,
    ];

    if ($id === 0) {
        // Creación: "antes" está vacío para todos los campos
        $cambios = compararCambios([], $datosNuevos, $mapaCampos);

        $movimiento          = obtenerMovimientoSesion('crear', $cambios);
        $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        $result = executeQuery($conectar, "
            INSERT INTO orden_produccion
                (codigo, producto_id, maquina, cantidad, estado, created_at, js_session, js_historial)
            VALUES
                (:codigo, :producto_id, :maquina, :cantidad, 'pendiente', NOW(), :js_session, :js_historial)
            RETURNING id
        ", [
            'codigo'       => $datosNuevos['codigo'],
            'producto_id'  => $datosNuevos['producto_id'],
            'maquina'      => $datosNuevos['maquina'],
            'cantidad'     => $datosNuevos['cantidad'],
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
        ]);
        $nuevo_id = $result[0]['id'] ?? null;
        responder(true, 'Orden de producción creada correctamente.', ['id' => $nuevo_id, 'modo' => 'crear']);
    } else {
        // Edición: solo se permite editar datos base si la orden sigue pendiente
        $actual = executeQuery($conectar, "SELECT * FROM orden_produccion WHERE id = :id", ['id' => $id]);
        if (empty($actual)) responder(false, 'Orden no encontrada.');
        $registroAnterior = $actual[0];

        if (!empty($registroAnterior['deleted_at'])) {
            responder(false, 'No puedes editar una orden inactiva. Reactívala primero.');
        }
        if ($registroAnterior['estado'] !== 'pendiente') {
            responder(false, 'Solo puedes editar una orden mientras está pendiente.');
        }

        $cambios = compararCambios($registroAnterior, $datosNuevos, $mapaCampos);

        $movimiento          = obtenerMovimientoSesion('editar', $cambios);
        $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeQuery($conectar, "
            UPDATE orden_produccion SET
                codigo       = :codigo,
                producto_id  = :producto_id,
                maquina      = :maquina,
                cantidad     = :cantidad,
                updated_at   = NOW(),
                js_session   = :js_session,
                js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id
        ", [
            'codigo'       => $datosNuevos['codigo'],
            'producto_id'  => $datosNuevos['producto_id'],
            'maquina'      => $datosNuevos['maquina'],
            'cantidad'     => $datosNuevos['cantidad'],
            'id'           => $id,
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
        ]);
        responder(true, 'Orden de producción actualizada correctamente.', ['id' => $id, 'modo' => 'editar']);
    }
}

// pendiente -> en_proceso
function iniciarOrden()
{
    $conectar = conectar_oll_BD();
    $id = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $actual = executeQuery($conectar, "SELECT estado, deleted_at FROM orden_produccion WHERE id = :id", ['id' => $id]);
    if (empty($actual)) responder(false, 'Orden no encontrada.');
    if (!empty($actual[0]['deleted_at'])) responder(false, 'Esta orden está inactiva.');
    if ($actual[0]['estado'] !== 'pendiente') {
        responder(false, 'Solo puedes iniciar una orden que está pendiente.');
    }

    executeQuery($conectar, "
        UPDATE orden_produccion SET
            estado       = 'en_proceso',
            fecha_inicio = NOW(),
            updated_at   = NOW()
        WHERE id = :id
    ", ['id' => $id]);

    $cambios = [[
        'campo'         => 'Estado',
        'valor_antes'   => 'Pendiente',
        'valor_despues' => 'En proceso',
    ]];
    registrarMovimiento($conectar, $id, 'iniciar', $cambios);

    responder(true, 'Orden iniciada correctamente.');
}

// en_proceso -> completada
function finalizarOrden()
{
    $conectar = conectar_oll_BD();
    $id = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $actual = executeQuery($conectar, "SELECT estado, deleted_at FROM orden_produccion WHERE id = :id", ['id' => $id]);
    if (empty($actual)) responder(false, 'Orden no encontrada.');
    if (!empty($actual[0]['deleted_at'])) responder(false, 'Esta orden está inactiva.');
    if ($actual[0]['estado'] !== 'en_proceso') {
        responder(false, 'Solo puedes finalizar una orden que está en proceso.');
    }

    executeQuery($conectar, "
        UPDATE orden_produccion SET
            estado     = 'completada',
            fecha_fin  = NOW(),
            updated_at = NOW()
        WHERE id = :id
    ", ['id' => $id]);

    $cambios = [[
        'campo'         => 'Estado',
        'valor_antes'   => 'En proceso',
        'valor_despues' => 'Completada',
    ]];
    registrarMovimiento($conectar, $id, 'finalizar', $cambios);

    responder(true, 'Orden finalizada correctamente.');
}

// pendiente | en_proceso -> cancelada
function cancelarOrden()
{
    $conectar = conectar_oll_BD();
    $id = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $actual = executeQuery($conectar, "SELECT estado, deleted_at FROM orden_produccion WHERE id = :id", ['id' => $id]);
    if (empty($actual)) responder(false, 'Orden no encontrada.');
    if (!empty($actual[0]['deleted_at'])) responder(false, 'Esta orden está inactiva.');
    if (!in_array($actual[0]['estado'], ['pendiente', 'en_proceso'], true)) {
        responder(false, 'Esta orden ya no puede cancelarse.');
    }

    $estadoAnterior = $actual[0]['estado'] === 'pendiente' ? 'Pendiente' : 'En proceso';

    executeQuery($conectar, "
        UPDATE orden_produccion SET
            estado     = 'cancelada',
            updated_at = NOW()
        WHERE id = :id
    ", ['id' => $id]);

    $cambios = [[
        'campo'         => 'Estado',
        'valor_antes'   => $estadoAnterior,
        'valor_despues' => 'Cancelada',
    ]];
    registrarMovimiento($conectar, $id, 'cancelar', $cambios);

    responder(true, 'Orden cancelada correctamente.');
}

// Soft delete: se marca deleted_at, no se borra físicamente. Solo si sigue pendiente.
function eliminarOrden()
{
    $conectar = conectar_oll_BD();
    $id = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $actual = executeQuery($conectar, "SELECT estado, deleted_at FROM orden_produccion WHERE id = :id", ['id' => $id]);
    if (empty($actual)) responder(false, 'Orden no encontrada.');
    if (!empty($actual[0]['deleted_at'])) responder(false, 'Esta orden ya estaba inactiva.');
    if ($actual[0]['estado'] !== 'pendiente') {
        responder(false, 'Solo puedes desactivar una orden que sigue pendiente. Si ya no la necesitas, cancélala.');
    }

    executeQuery($conectar, "
        UPDATE orden_produccion SET
            deleted_at = NOW(),
            updated_at = NOW()
        WHERE id = :id
    ", ['id' => $id]);

    $cambios = [[
        'campo'         => 'Estado',
        'valor_antes'   => 'Activo',
        'valor_despues' => 'Inactivo',
    ]];
    registrarMovimiento($conectar, $id, 'desactivar', $cambios);

    responder(true, 'Orden desactivada correctamente.');
}

// Reactivar: quita deleted_at y vuelve a dejar la orden en 'pendiente'.
function reactivarOrden()
{
    $conectar = conectar_oll_BD();
    $id = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $actual = executeQuery($conectar, "SELECT deleted_at FROM orden_produccion WHERE id = :id", ['id' => $id]);
    if (empty($actual)) responder(false, 'Orden no encontrada.');
    if (empty($actual[0]['deleted_at'])) responder(false, 'Esta orden ya estaba activa.');

    executeQuery($conectar, "
        UPDATE orden_produccion SET
            deleted_at = NULL,
            estado     = 'pendiente',
            updated_at = NOW()
        WHERE id = :id
    ", ['id' => $id]);

    $cambios = [[
        'campo'         => 'Estado',
        'valor_antes'   => 'Inactivo',
        'valor_despues' => 'Activo',
    ]];
    registrarMovimiento($conectar, $id, 'reactivar', $cambios);

    responder(true, 'Orden reactivada correctamente.');
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
// Se ejecuta al final del archivo, una vez que ya existen todas las funciones
// y constantes (p. ej. ESTADOS_VALIDOS) que usan las acciones.
// =============================================================================

if (isset($_POST["accion"])) {
    controladorOrdenProduccion($_POST["accion"]);
}