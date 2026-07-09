<?php

/**
 * controllers/clssMaterial.php
 * Controlador del módulo de Materia Prima
 * Tabla real: material (id, nombre, unidad_medida_id, stock_minimo, stock_actual,
 *             js_session, js_historial, created_at, update_at, deleted_at)
 * unidad_medida_id es OPCIONAL y, si se envía, DEBE ser una unidad RAÍZ
 * (unidad_base_id IS NULL) — el stock de un material siempre se maneja en su
 * unidad base; las unidades compuestas (sacos, bolsas, rollos) solo se eligen
 * al momento de comprar, y se convierten con `equivalencia` hacia esta unidad.
 * Soft delete vía deleted_at.
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    controladorMaterial($_POST["accion"]);
}

function controladorMaterial($accion)
{
    switch ($accion) {
        case 'LISTARMATERIALES':
            listarMateriales();
            break;
        case 'OBTENERMATERIAL':
            obtenerMaterial(intval($_POST['id'] ?? 0));
            break;
        case 'GUARDARMATERIAL':
            guardarMaterial();
            break;
        case 'ELIMINARMATERIAL':
            eliminarMaterial();
            break;
        case 'REACTIVARMATERIAL':
            reactivarMaterial();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// MATERIAL
// =============================================================================

function listarMateriales()
{
    $conectar = conectar_oll_BD();

    $texto  = trim($_POST['texto'] ?? '');
    $estado = trim($_POST['estado'] ?? ''); // '', 'activa', 'inactiva'

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

    $sql = "
        SELECT
            m.*,
            u.nombre       AS unidad_nombre,
            u.nombre_corto AS unidad_corto
        FROM material m
        LEFT JOIN unidad_medida u ON u.id = m.unidad_medida_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY m.nombre
    ";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['materiales' => $result]);
}

function obtenerMaterial($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $result = executeQuery(
        $conectar,
        "SELECT * FROM material WHERE id = :id",
        ['id' => $id]
    );
    if (empty($result)) responder(false, 'Material no encontrado.');
    responder(true, 'OK', ['material' => $result[0]]);
}

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
 * Compara un registro anterior contra los datos nuevos y devuelve solo los
 * campos cuyo valor cambió, mapeados con etiqueta legible.
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
 * Traduce un id de unidad_medida a su nombre legible, para que el historial
 * no quede lleno de números sueltos.
 */
function obtenerNombreUnidad($conectar, $unidadMedidaId): string
{
    if (empty($unidadMedidaId)) return 'Sin unidad de medida';

    $result = executeQuery(
        $conectar,
        "SELECT nombre, nombre_corto FROM unidad_medida WHERE id = :id",
        ['id' => $unidadMedidaId]
    );
    if (empty($result)) return "Unidad #$unidadMedidaId (no encontrada)";

    return $result[0]['nombre'] . ' (' . $result[0]['nombre_corto'] . ')';
}

function guardarMaterial()
{
    $conectar    = conectar_oll_BD();
    $id          = intval($_POST['id'] ?? 0);
    $nombre      = trim($_POST['nombre'] ?? '');
    $stockMinimo = $_POST['stock_minimo'] !== '' ? floatval($_POST['stock_minimo'] ?? 0) : 0;
    $stockActual = $_POST['stock_actual'] !== '' ? floatval($_POST['stock_actual'] ?? 0) : 0;

    // La unidad de medida es OPCIONAL: si no viene o viene vacía, queda en NULL.
    $unidadMedidaId = !empty($_POST['unidad_medida_id']) ? intval($_POST['unidad_medida_id']) : null;

    // ── Validaciones ──────────────────────────────────────────────────────────
    if (empty($nombre))    responder(false, 'El nombre es obligatorio.');
    if ($stockMinimo < 0)  responder(false, 'El stock mínimo no puede ser negativo.');
    if ($stockActual < 0)  responder(false, 'El stock actual no puede ser negativo.');

    // Si se envió una unidad de medida, debe existir, estar activa y ser RAÍZ.
    // (el stock del material siempre se guarda en su unidad base; las unidades
    // compuestas -sacos, bolsas, rollos- solo aplican al comprar).
    if ($unidadMedidaId !== null) {
        $unidad = executeQuery(
            $conectar,
            "SELECT id, unidad_base_id FROM unidad_medida WHERE id = :id AND deleted_at IS NULL",
            ['id' => $unidadMedidaId]
        );
        if (empty($unidad)) responder(false, 'La unidad de medida seleccionada no existe o está inactiva.');
        if (!empty($unidad[0]['unidad_base_id'])) {
            responder(false, 'Debes elegir una unidad de medida raíz (ej: Kilogramo, Metro, Unidad), no una compuesta (ej: Saco 25kg).');
        }
    }

    // Nombre único (excluyendo el propio registro si es edición)
    $chk = executeQuery(
        $conectar,
        "SELECT id FROM material WHERE LOWER(nombre) = LOWER(:nombre) AND id <> :id",
        ['nombre' => $nombre, 'id' => $id]
    );
    if (!empty($chk)) responder(false, 'Ya existe un material con ese nombre.');

    $mapaCampos = [
        'nombre'         => 'Nombre',
        'nombre_unidad'  => 'Unidad de medida',
        'stock_minimo'   => 'Stock mínimo',
        'stock_actual'   => 'Stock actual',
    ];

    $datosNuevos = [
        'nombre'         => $nombre,
        'nombre_unidad'  => obtenerNombreUnidad($conectar, $unidadMedidaId),
        'stock_minimo'   => $stockMinimo,
        'stock_actual'   => $stockActual,
    ];

    if ($id === 0) {
        $cambios = compararCambios([], $datosNuevos, $mapaCampos);

        $movimiento          = obtenerMovimientoSesion('crear', $cambios);
        $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        $result = executeQuery($conectar, "
            INSERT INTO material (nombre, unidad_medida_id, stock_minimo, stock_actual, created_at, js_session, js_historial)
            VALUES (:nombre, :unidad_medida_id, :stock_minimo, :stock_actual, NOW(), :js_session, :js_historial)
            RETURNING id
        ", [
            'nombre'           => $nombre,
            'unidad_medida_id' => $unidadMedidaId,
            'stock_minimo'     => $stockMinimo,
            'stock_actual'     => $stockActual,
            'js_session'       => $js_session,
            'js_historial'     => $js_historial_nuevo,
        ]);
        $nuevo_id = $result[0]['id'] ?? null;
        responder(true, 'Material creado correctamente.', ['id' => $nuevo_id, 'modo' => 'crear']);
    } else {
        $actual = executeQuery($conectar, "SELECT * FROM material WHERE id = :id", ['id' => $id]);
        if (empty($actual)) responder(false, 'Material no encontrado.');
        $registroAnterior = $actual[0];
        $registroAnterior['nombre_unidad'] = obtenerNombreUnidad($conectar, $registroAnterior['unidad_medida_id']);

        $cambios = compararCambios($registroAnterior, $datosNuevos, $mapaCampos);

        $movimiento          = obtenerMovimientoSesion('editar', $cambios);
        $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeQuery($conectar, "
            UPDATE material SET
                nombre           = :nombre,
                unidad_medida_id = :unidad_medida_id,
                stock_minimo     = :stock_minimo,
                stock_actual     = :stock_actual,
                update_at        = NOW(),
                js_session       = :js_session,
                js_historial     = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id
        ", [
            'nombre'           => $nombre,
            'unidad_medida_id' => $unidadMedidaId,
            'stock_minimo'     => $stockMinimo,
            'stock_actual'     => $stockActual,
            'id'               => $id,
            'js_session'       => $js_session,
            'js_historial'     => $js_historial_nuevo,
        ]);
        responder(true, 'Material actualizado correctamente.', ['id' => $id, 'modo' => 'editar']);
    }
}

// Soft delete: se marca deleted_at, no se borra físicamente.
function eliminarMaterial()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at FROM material WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Material no encontrado.');
    if (!empty($existe[0]['deleted_at'])) {
        responder(false, 'Este material ya estaba inactivo.');
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
        "UPDATE material SET
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
    responder(true, 'Material desactivado correctamente.');
}

function reactivarMaterial()
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
        "UPDATE material SET
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
    responder(true, 'Material reactivado correctamente.');
}

function responder(bool $ok, string $msg, array $extra = []): void
{
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}