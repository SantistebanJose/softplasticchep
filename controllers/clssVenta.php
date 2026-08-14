<?php

/**
 * controllers/clssVenta.php
 * Módulo de Ventas (simple, sin SUNAT).
 *
 * Una venta guarda un snapshot de sus ítems en js_items (jsonb), incluyendo
 * el detalle de qué registros de `empaquetado` fueron consumidos por cada
 * ítem (js_consumo dentro de cada item), para trazabilidad y para poder
 * anular la venta reponiendo stock.
 *
 * Consumo de stock: FIFO por producto_id sobre la tabla empaquetado
 * (deleted_at IS NULL, pasado_venta IS NULL, cantidad_tota > 0), ordenado
 * por created_at ASC. Se descuenta cantidad_tota parcialmente; si llega a
 * 0 se marca pasado_venta = NOW().
 *
 * NOTA (heredada de clssDisponibilidadVenta.php): el vínculo producto->color
 * se deriva vía produccion.unico_molde_producto; empaquetado no tiene color_id
 * propio, así que el consumo FIFO es por producto_id, no por color. Si un
 * producto tiene más de un color histórico en empaquetado, el consumo no
 * distingue entre colores. Mismo caveat que ya tenías anotado ahí.
 *
 * Solo se puede vender a proveedor.tipo = 'cliente' (activos).
 *
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

ob_start();

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    try {
        controladorVenta($_POST["accion"]);
    } catch (PDOException $e) {
        error_log("Error de base de datos en clssVenta.php: " . $e->getMessage());
        responderVenta(false, 'Error de base de datos: ' . $e->getMessage());
    } catch (Throwable $e) {
        error_log("Error inesperado en clssVenta.php: " . $e->getMessage());
        responderVenta(false, 'Error inesperado en el servidor: ' . $e->getMessage());
    }
}

function controladorVenta($accion)
{
    switch ($accion) {
        case 'LISTARVENTAS':
            listarVentas();
            break;
        case 'OBTENERVENTA':
            obtenerVenta((int)($_POST['id'] ?? 0));
            break;
        case 'BUSCARCLIENTES':
            buscarClientes();
            break;
        case 'BUSCARDISPONIBLESVENTA':
            buscarDisponiblesVenta();
            break;
        case 'GUARDARVENTA':
            guardarVenta();
            break;
        case 'ANULARVENTA':
            anularVenta((int)($_POST['id'] ?? 0));
            break;
        default:
            responderVenta(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// HELPERS DE AUDITORÍA (mismo patrón que clssProveedor.php)
// =============================================================================

function obtenerIpClienteVenta(): string
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

function obtenerMovimientoSesionVenta(string $accion, array $cambios = []): array
{
    return [
        'usuario'   => $_SESSION['usuario_id'] ?? 'Sistema',
        'nombre'    => $_SESSION['nombre_usuario'] ?? 'Usuario Desconocido',
        'user'      => $_SESSION['user_usuario'] ?? 'N/A',
        'rol'       => $_SESSION['rol_usuario'] ?? 'N/A',
        'accion'    => $accion,
        'ip'        => obtenerIpClienteVenta(),
        'cambios'   => $cambios,
        'timestamp' => date('Y-m-d H:i:s'),
    ];
}

// =============================================================================
// CLIENTES (proveedor.tipo = 'cliente')
// =============================================================================

function buscarClientes()
{
    $conectar = conectar_oll_BD();
    $texto = trim($_POST['texto'] ?? '');

    $where  = ["js_tipo @> '[\"cliente\"]'::jsonb", "deleted_at IS NULL"];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(razon_social) LIKE LOWER(:texto)
                     OR LOWER(nombre_comercial) LIKE LOWER(:texto)
                     OR ruc LIKE :texto)";
        $params['texto'] = "%$texto%";
    }

    $sql = "SELECT ruc, razon_social, nombre_comercial
            FROM proveedor
            WHERE " . implode(' AND ', $where) . "
            ORDER BY razon_social
            LIMIT 20";

    $result = executeQuery($conectar, $sql, $params);
    responderVenta(true, 'OK', ['clientes' => $result]);
}
// =============================================================================
// PRODUCTOS/COLORES DISPONIBLES PARA VENDER
// (mismo criterio que clssDisponibilidadVenta.php, siempre sin vendidos)
// =============================================================================

function buscarDisponiblesVenta()
{
    $conectar = conectar_oll_BD();
    $texto = trim($_POST['texto'] ?? '');

    $where  = ["t1.deleted_at IS NULL", "t1.pasado_venta IS NULL"];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(pc.producto_codigo) LIKE LOWER(:texto) OR LOWER(pc.producto) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }

    $sql = "WITH producto_color AS (
                SELECT DISTINCT
                    t2.id AS producto_id,
                    t2.codigo AS producto_codigo,
                    t2.descripcion AS producto,
                    t4.id AS color_id,
                    t4.nombre AS color
                FROM produccion t3
                JOIN producto t2 ON t2.id::varchar = split_part(t3.unico_molde_producto, '-', 2)
                JOIN color t4 ON t4.id = t3.color_id
                WHERE t4.deleted_at IS NULL
            )
            SELECT
                pc.producto_id, pc.producto_codigo, pc.producto,
                pc.color_id, pc.color,
                COALESCE(SUM(
                    CASE WHEN um.unidad_base_id IS NOT NULL
                         THEN t1.cantidad_tota * um.equivalencia
                         ELSE t1.cantidad_tota
                    END
                ), 0) AS cantidad_disponible,
                MIN(COALESCE(ub.nombre_corto, um.nombre_corto)) AS unidad_corto
            FROM producto_color pc
            JOIN empaquetado t1 ON t1.producto_id = pc.producto_id
            JOIN unidad_medida um ON um.id = t1.unidad_medida
            LEFT JOIN unidad_medida ub ON ub.id = um.unidad_base_id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY pc.producto_id, pc.producto_codigo, pc.producto, pc.color_id, pc.color
            HAVING COALESCE(SUM(
                CASE WHEN um.unidad_base_id IS NOT NULL
                     THEN t1.cantidad_tota * um.equivalencia
                     ELSE t1.cantidad_tota
                END
            ), 0) > 0
            ORDER BY pc.producto, pc.color
            LIMIT 30";

    $result = executeQuery($conectar, $sql, $params);
    responderVenta(true, 'OK', ['disponibles' => $result]);
}

// =============================================================================
// CONSUMO FIFO DE STOCK (empaquetado)
// =============================================================================

/**
 * Reserva/consume stock FIFO para un producto. Debe llamarse dentro de una
 * transacción abierta en $conectar. Lanza RuntimeException si no alcanza.
 * Devuelve el detalle de qué registros de empaquetado se afectaron.
 */
function consumirStockFIFOVenta($conectar, int $productoId, float $cantidadNecesaria): array
{
    $stmt = $conectar->prepare("
        SELECT t1.id, t1.cantidad_tota, um.equivalencia, um.unidad_base_id
        FROM empaquetado t1
        JOIN unidad_medida um ON um.id = t1.unidad_medida
        WHERE t1.producto_id = :producto_id
          AND t1.deleted_at IS NULL
          AND t1.pasado_venta IS NULL
          AND t1.cantidad_tota > 0
        ORDER BY t1.created_at ASC, t1.id ASC
        FOR UPDATE
    ");
    $stmt->execute(['producto_id' => $productoId]);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $restante = $cantidadNecesaria;
    $consumo  = [];

    foreach ($registros as $reg) {
        if ($restante <= 0.0001) break;

        $equivalencia   = !empty($reg['equivalencia']) ? (float)$reg['equivalencia'] : 1.0;
        $esConvertible  = !empty($reg['unidad_base_id']);
        $cantidadTota   = (float)$reg['cantidad_tota'];
        $disponibleBase = $esConvertible ? $cantidadTota * $equivalencia : $cantidadTota;

        if ($disponibleBase <= 0.0001) continue;

        $aConsumirBase   = min($restante, $disponibleBase);
        $aConsumirPropio = $esConvertible ? ($aConsumirBase / $equivalencia) : $aConsumirBase;
        $nuevaCantidad   = round($cantidadTota - $aConsumirPropio, 4);
        if ($nuevaCantidad < 0) $nuevaCantidad = 0;

        $consumo[] = [
            'empaquetado_id'      => (int)$reg['id'],
            'cantidad_antes'      => $cantidadTota,
            'cantidad_consumida'  => round($aConsumirPropio, 4),
            'cantidad_despues'    => $nuevaCantidad,
        ];

        $restante = round($restante - $aConsumirBase, 4);
    }

    if ($restante > 0.0001) {
        throw new RuntimeException('Stock insuficiente para cubrir la cantidad solicitada (faltan ' . $restante . ').');
    }

    foreach ($consumo as $c) {
        $upd = $conectar->prepare("
            UPDATE empaquetado SET
                cantidad_tota = :cantidad,
                pasado_venta  = CASE WHEN :cantidad2 <= 0 THEN NOW() ELSE pasado_venta END,
                update_at     = NOW()
            WHERE id = :id
        ");
        $upd->execute([
            'cantidad'  => $c['cantidad_despues'],
            'cantidad2' => $c['cantidad_despues'],
            'id'        => $c['empaquetado_id'],
        ]);
    }

    return $consumo;
}

/**
 * Reversa el consumo de un ítem anulado: repone cantidad_tota y libera
 * pasado_venta si corresponde. Debe llamarse dentro de una transacción.
 */
function restaurarStockVenta($conectar, array $consumo): void
{
    foreach ($consumo as $c) {
        $stmt = $conectar->prepare("SELECT cantidad_tota FROM empaquetado WHERE id = :id FOR UPDATE");
        $stmt->execute(['id' => $c['empaquetado_id']]);
        $actual = $stmt->fetchColumn();
        if ($actual === false) continue; // el registro ya no existe

        $nuevaCantidad = round((float)$actual + (float)$c['cantidad_consumida'], 4);

        $upd = $conectar->prepare("
            UPDATE empaquetado SET
                cantidad_tota = :cantidad,
                pasado_venta  = NULL,
                update_at     = NOW()
            WHERE id = :id
        ");
        $upd->execute(['cantidad' => $nuevaCantidad, 'id' => $c['empaquetado_id']]);
    }
}

// =============================================================================
// VENTAS
// =============================================================================

function listarVentas()
{
    $conectar = conectar_oll_BD();

    $texto  = trim($_POST['texto'] ?? '');
    $estado = trim($_POST['estado'] ?? '');

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(v.codigo) LIKE LOWER(:texto)
                     OR LOWER(p.razon_social) LIKE LOWER(:texto)
                     OR v.cliente_ruc LIKE :texto)";
        $params['texto'] = "%$texto%";
    }
    if ($estado === 'completada' || $estado === 'anulada') {
        $where[] = "v.estado = :estado";
        $params['estado'] = $estado;
    }

    $sql = "SELECT v.id, v.codigo, v.cliente_ruc, p.razon_social AS cliente_nombre,
                   v.fecha_venta, v.monto_total, v.estado,
                   jsonb_array_length(v.js_items) AS items_count
            FROM venta v
            JOIN proveedor p ON p.ruc = v.cliente_ruc
            WHERE " . implode(' AND ', $where) . "
            ORDER BY v.fecha_venta DESC";

    $result = executeQuery($conectar, $sql, $params);
    responderVenta(true, 'OK', ['ventas' => $result]);
}

function obtenerVenta(int $id)
{
    $conectar = conectar_oll_BD();
    if ($id <= 0) responderVenta(false, 'Venta inválida.');

    $result = executeQuery($conectar, "
        SELECT v.*, p.razon_social AS cliente_nombre, p.nombre_comercial AS cliente_comercial,
               p.ubicacion AS cliente_ubicacion, p.correo AS cliente_correo
        FROM venta v
        JOIN proveedor p ON p.ruc = v.cliente_ruc
        WHERE v.id = :id
    ", ['id' => $id]);

    if (empty($result)) responderVenta(false, 'Venta no encontrada.');
    responderVenta(true, 'OK', ['venta' => $result[0]]);
}

function guardarVenta()
{
    $conectar = conectar_oll_BD();

    $cliente_ruc = trim($_POST['cliente_ruc'] ?? '');
    $itemsJson   = trim($_POST['items'] ?? '[]');

    if (empty($cliente_ruc)) responderVenta(false, 'Debes seleccionar un cliente.');

    $cliente = executeQuery($conectar,
        "SELECT ruc, js_tipo, deleted_at FROM proveedor WHERE ruc = :ruc",
        ['ruc' => $cliente_ruc]
    );
    if (empty($cliente)) responderVenta(false, 'Cliente no encontrado.');

    $tiposCliente = json_decode($cliente[0]['js_tipo'] ?? '[]', true) ?: [];
    if (!in_array('cliente', $tiposCliente, true)) {
        responderVenta(false, 'El RUC/DNI seleccionado no corresponde a un cliente.');
    }
    if (!empty($cliente[0]['deleted_at'])) responderVenta(false, 'El cliente seleccionado está inactivo.');

    $itemsInput = json_decode($itemsJson, true);
    if (!is_array($itemsInput) || count($itemsInput) === 0) {
        responderVenta(false, 'Debes agregar al menos un producto a la venta.');
    }

    try {
        $conectar->beginTransaction();

        $itemsFinal = [];
        $montoTotal = 0.0;

        foreach ($itemsInput as $item) {
            $productoId = (int)($item['producto_id'] ?? 0);
            $colorId    = !empty($item['color_id']) ? (int)$item['color_id'] : null;
            $cantidad   = (float)($item['cantidad'] ?? 0);
            $precio     = (float)($item['precio_unitario'] ?? 0);

            if ($productoId <= 0 || $cantidad <= 0) {
                throw new RuntimeException('Cada ítem debe tener producto y cantidad válidos.');
            }

            $paramsInfo = ['producto_id' => $productoId];
            $sqlInfo = "SELECT DISTINCT t2.id AS producto_id, t2.codigo AS producto_codigo,
                               t2.descripcion AS producto, t4.id AS color_id, t4.nombre AS color
                        FROM produccion t3
                        JOIN producto t2 ON t2.id::varchar = split_part(t3.unico_molde_producto, '-', 2)
                        JOIN color t4 ON t4.id = t3.color_id
                        WHERE t2.id = :producto_id";
            if ($colorId) {
                $sqlInfo .= " AND t4.id = :color_id";
                $paramsInfo['color_id'] = $colorId;
            }
            $sqlInfo .= " LIMIT 1";

            $infoProducto = executeQuery($conectar, $sqlInfo, $paramsInfo);
            if (empty($infoProducto)) {
                throw new RuntimeException('No se encontró información del producto/color seleccionado (ID producto ' . $productoId . ').');
            }
            $info = $infoProducto[0];

            $consumo  = consumirStockFIFOVenta($conectar, $productoId, $cantidad);
            $subtotal = round($cantidad * $precio, 2);
            $montoTotal += $subtotal;

            $itemsFinal[] = [
                'producto_id'     => (int)$info['producto_id'],
                'producto_codigo' => $info['producto_codigo'],
                'producto'        => $info['producto'],
                'color_id'        => $info['color_id'] !== null ? (int)$info['color_id'] : null,
                'color'           => $info['color'],
                'cantidad'        => $cantidad,
                'precio_unitario' => $precio,
                'subtotal'        => $subtotal,
                'js_consumo'      => $consumo,
            ];
        }

        $movimiento = obtenerMovimientoSesionVenta('crear');

        $stmtInsert = $conectar->prepare("
            INSERT INTO venta (codigo, cliente_ruc, fecha_venta, js_items, monto_total, estado, created_at, js_session, js_historial)
            VALUES (:codigo, :cliente_ruc, NOW(), :js_items::jsonb, :monto_total, 'completada', NOW(), :js_session, :js_historial::jsonb)
            RETURNING id
        ");
        $stmtInsert->execute([
            'codigo'       => 'V-TEMP',
            'cliente_ruc'  => $cliente_ruc,
            'js_items'     => json_encode($itemsFinal, JSON_UNESCAPED_UNICODE),
            'monto_total'  => round($montoTotal, 2),
            'js_session'   => json_encode($movimiento, JSON_UNESCAPED_UNICODE),
            'js_historial' => json_encode([$movimiento], JSON_UNESCAPED_UNICODE),
        ]);
        $ventaId = (int)$stmtInsert->fetchColumn();

        $codigo = 'V-' . str_pad((string)$ventaId, 6, '0', STR_PAD_LEFT);
        $stmtCodigo = $conectar->prepare("UPDATE venta SET codigo = :codigo WHERE id = :id");
        $stmtCodigo->execute(['codigo' => $codigo, 'id' => $ventaId]);

        $conectar->commit();

        responderVenta(true, 'Venta registrada correctamente.', ['venta_id' => $ventaId, 'codigo' => $codigo]);
    } catch (Throwable $e) {
        if ($conectar->inTransaction()) $conectar->rollBack();
        responderVenta(false, 'No se pudo registrar la venta: ' . $e->getMessage());
    }
}
function anularVenta(int $id)
{
    $conectar = conectar_oll_BD();
    if ($id <= 0) responderVenta(false, 'Venta inválida.');

    try {
        $conectar->beginTransaction();

        $stmt = $conectar->prepare("SELECT * FROM venta WHERE id = :id FOR UPDATE");
        $stmt->execute(['id' => $id]);
        $venta = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$venta) throw new RuntimeException('Venta no encontrada.');
        if ($venta['estado'] === 'anulada') throw new RuntimeException('Esta venta ya estaba anulada.');

        $items = json_decode($venta['js_items'], true) ?: [];
        foreach ($items as $item) {
            $consumo = $item['js_consumo'] ?? [];
            if (!empty($consumo)) {
                restaurarStockVenta($conectar, $consumo);
            }
        }

        $movimiento = obtenerMovimientoSesionVenta('anular', [[
            'campo' => 'Estado', 'valor_antes' => 'completada', 'valor_despues' => 'anulada',
        ]]);

        $upd = $conectar->prepare("
            UPDATE venta SET
                estado       = 'anulada',
                update_at    = NOW(),
                js_session   = :js_session,
                js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id
        ");
        $upd->execute([
            'js_session'   => json_encode($movimiento, JSON_UNESCAPED_UNICODE),
            'js_historial' => json_encode([$movimiento], JSON_UNESCAPED_UNICODE),
            'id'           => $id,
        ]);

        $conectar->commit();
        responderVenta(true, 'Venta anulada y stock repuesto correctamente.');
    } catch (Throwable $e) {
        if ($conectar->inTransaction()) $conectar->rollBack();
        responderVenta(false, 'No se pudo anular la venta: ' . $e->getMessage());
    }
}

// =============================================================================
// HELPER
// =============================================================================

function responderVenta(bool $ok, string $msg, array $extra = []): void
{
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}