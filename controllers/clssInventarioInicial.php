<?php
ob_start();
require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
require_once __DIR__ . '/auditoria.php';
require_once __DIR__ . '/clssVerificarSession.php';

iniciarSesionSegura();
$usuario = exigirSesion();
exigirRol($usuario, ['administrador']);

try {
    $accion = strtoupper(trim((string)($_POST['accion'] ?? '')));
    if ($accion === 'LISTAR_CATALOGOS') {
        listarCatalogosInventarioInicial();
    } elseif ($accion === 'REGISTRAR') {
        registrarInventarioInicial();
    } else {
        inventarioInicialResponder(false, 'Acción no reconocida.', 400);
    }
} catch (PDOException $e) {
    error_log('Error de base de datos en clssInventarioInicial.php: ' . $e->getMessage());
    inventarioInicialResponder(false, 'No se pudo completar la operación. Verifica que se haya aplicado la migración de inventario inicial.');
} catch (Throwable $e) {
    error_log('Error en clssInventarioInicial.php: ' . $e->getMessage());
    inventarioInicialResponder(false, $e->getMessage());
}

function listarCatalogosInventarioInicial(): void
{
    $db = conectar_oll_BD();
    $productos = executeQuery($db, "
        SELECT p.id, p.codigo, p.descripcion,
               um.id AS unidad_id, um.nombre_corto AS unidad
        FROM producto p
        JOIN unidad_medida um
          ON um.id = NULLIF(p.js_configuracion_empaquetado->>'salida_empaquetado_unidad_medida_id', '')::bigint
         AND um.deleted_at IS NULL
        WHERE p.activo = TRUE
        ORDER BY p.descripcion, p.codigo
    ");
    $sucursales = executeQuery($db, "
        SELECT id, nombre FROM sucursal
        WHERE delete_at IS NULL
        ORDER BY nombre
    ");
    inventarioInicialResponder(true, 'OK', 200, ['productos' => $productos, 'sucursales' => $sucursales]);
}

function registrarInventarioInicial(): void
{
    $productoId = (int)($_POST['producto_id'] ?? 0);
    $sucursalId = (int)($_POST['sucursal_id'] ?? 0);
    $cantidad = filter_var($_POST['cantidad'] ?? null, FILTER_VALIDATE_FLOAT);
    $fecha = trim((string)($_POST['fecha_inventario'] ?? ''));
    $observacion = trim((string)($_POST['observacion'] ?? ''));

    if ($productoId <= 0 || $sucursalId <= 0 || $cantidad === false || $cantidad <= 0) {
        inventarioInicialResponder(false, 'Selecciona producto y sucursal, e ingresa una cantidad mayor a cero.');
    }
    if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        inventarioInicialResponder(false, 'Indica una fecha de conteo válida.');
    }
    if (mb_strlen($observacion) > 500) {
        inventarioInicialResponder(false, 'La observación no puede superar 500 caracteres.');
    }

    $db = conectar_oll_BD();
    $db->beginTransaction();
    try {
        $producto = executeQuery($db, "
            SELECT p.id, p.codigo, p.descripcion,
                   NULLIF(p.js_configuracion_empaquetado->>'salida_empaquetado_unidad_medida_id', '')::bigint AS unidad_id,
                   um.nombre_corto AS unidad
            FROM producto p
            JOIN unidad_medida um
              ON um.id = NULLIF(p.js_configuracion_empaquetado->>'salida_empaquetado_unidad_medida_id', '')::bigint
             AND um.deleted_at IS NULL
            WHERE p.id = :id AND p.activo = TRUE
            FOR UPDATE OF p
        ", ['id' => $productoId]);
        if (!$producto) throw new RuntimeException('El producto no existe, está inactivo o no tiene unidad configurada para venta.');
        $sucursal = executeQuery($db, "SELECT id FROM sucursal WHERE id = :id AND delete_at IS NULL", ['id' => $sucursalId]);
        if (!$sucursal) throw new RuntimeException('La sucursal seleccionada no existe o está inactiva.');

        $movimiento = obtenerMovimientoSesion('inventario_inicial', [[
            'campo' => 'Inventario inicial',
            'valor_antes' => '(sin registro)',
            'valor_despues' => sprintf(
                '%s %s en sucursal #%d; conteo %s%s',
                rtrim(rtrim(number_format((float)$cantidad, 4, '.', ''), '0'), '.'),
                $producto[0]['unidad'],
                $sucursalId,
                $fecha,
                $observacion !== '' ? '; ' . $observacion : ''
            ),
        ]]);
        $jsHistorial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $jsSession = json_encode($movimiento, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $detalle = json_encode([['cantidad' => (float)$cantidad, 'colores' => []]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $insertado = executeQuery($db, "
            INSERT INTO empaquetado (
                producto_id, unidad_medida, maquina_id, operario_id, js_operarios, sucursal,
                cantidad_tota, cantidad_disponible_tmp, js_cantidades,
                fuente_inventario, fecha_inventario, created_at, js_session, js_historial
            ) VALUES (
                :producto_id, :unidad_id, NULL, NULL, '[]'::jsonb, :sucursal_id,
                :cantidad, :cantidad, CAST(:detalle AS jsonb),
                'INVENTARIO_INICIAL', :fecha_inventario, NOW(), CAST(:js_session AS jsonb), CAST(:js_historial AS jsonb)
            ) RETURNING id
        ", [
            'producto_id' => $productoId,
            'unidad_id' => (int)$producto[0]['unidad_id'],
            'sucursal_id' => $sucursalId,
            'cantidad' => (float)$cantidad,
            'detalle' => $detalle,
            'fecha_inventario' => $fecha,
            'js_session' => $jsSession,
            'js_historial' => $jsHistorial,
        ]);
        $db->commit();
        inventarioInicialResponder(true, 'Inventario inicial registrado correctamente.', 200, ['id' => (int)$insertado[0]['id']]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

function inventarioInicialResponder(bool $ok, string $mensaje, int $codigo = 200, array $extra = []): never
{
    if (ob_get_level() > 0) ob_clean();
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $ok, 'message' => $mensaje], $extra), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
