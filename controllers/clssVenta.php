<?php

/**
 * controllers/clssVenta.php
 * Módulo de Ventas (simple, sin SUNAT).
 *
 * FIX (2026-08-29): la venta ahora se hace por PAQUETE (la unidad real de
 * venta del producto, producto.unidad_venta_id + cant_equivale +
 * unidad_equivale_id — mismo mecanismo ya usado en el reporte de
 * Disponibilidad de Venta), no por unidad base (ganchitos/UND) ni por la
 * unidad operativa de empaquetado (bolsas/GRU). Al negocio solo le
 * importa el paquete; la unidad base sigue existiendo por dentro
 * únicamente para poder descontar contra `empaquetado` con precisión.
 *
 * Si un producto no tiene su unidad de venta configurada
 * (cant_equivale / unidad_equivale_id), NO se puede vender por paquete
 * todavía: se avisa en vez de dejar vender en una unidad ambigua.
 *
 * Los paquetes se venden COMPLETOS (cantidad entera). Si se necesita
 * vender fracciones de paquete en el futuro, hay que decidirlo
 * explícitamente — por ahora se valida como error.
 *
 * FIX (2026-08-29 bis): confirmado con el negocio que TODA la venta es
 * por paquete, sin excepciones de unidad. Por eso buscarDisponiblesVenta()
 * ahora excluye directamente del buscador cualquier producto sin su
 * unidad de venta configurada (antes aparecía en los resultados y recién
 * fallaba al guardar, con nombres de campo que además no coincidían con
 * los que leía el frontend: unidad_paquete_corto/cantidad_disponible no
 * existían en la respuesta — el frontend fue corregido junto con esto).
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
// UNIDAD DE VENTA (paquete real) DE UN PRODUCTO
// Mismo mecanismo ya usado en clssDisponibilidadVenta.php: capacidad del
// paquete de venta, expresada en unidad base, a partir de
// producto.cant_equivale x equivalencia de producto.unidad_equivale_id.
// =============================================================================

function obtenerCapacidadPaqueteVenta($conectar, int $productoId): array
{
    $rows = executeQuery($conectar, "
        SELECT
            uv.nombre_corto AS unidad_venta_corto,
            p.cant_equivale,
            p.unidad_equivale_id,
            ue.equivalencia AS unidad_equivale_equivalencia
        FROM producto p
        LEFT JOIN unidad_medida uv ON uv.id = p.unidad_venta_id
        LEFT JOIN unidad_medida ue ON ue.id = p.unidad_equivale_id
        WHERE p.id = :id
    ", ['id' => $productoId]);

    if (empty($rows)) {
        return ['capacidad_base' => null, 'unidad_venta_corto' => null];
    }
    $r = $rows[0];
    if (empty($r['cant_equivale']) || empty($r['unidad_equivale_id'])) {
        return ['capacidad_base' => null, 'unidad_venta_corto' => $r['unidad_venta_corto']];
    }

    $capacidad = (float)$r['cant_equivale'] * (float)($r['unidad_equivale_equivalencia'] ?? 1);
    return ['capacidad_base' => $capacidad, 'unidad_venta_corto' => $r['unidad_venta_corto']];
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
// REESCRITO (2026-08-29): "paquetes_disponibles" sale de la unidad REAL de
// venta del producto (igual que el reporte de Disponibilidad), no de
// cantidad_tota en la unidad operativa de empaquetado. Se sigue
// clasificando el color vía rel_empaquetado_origen (único / mezcla / legado).
//
// REESCRITO (2026-08-29 bis): confirmado que no hay venta por otra unidad
// como excepción, así que ahora el JOIN a producto_venta es INNER y exige
// capacidad_paquete_venta_base > 0 — un producto sin su unidad de venta
// configurada simplemente no aparece en el buscador (antes aparecía y
// recién fallaba al guardar). Se quita el campo sin_config_venta, que ya
// no aplica.
// =============================================================================

function buscarDisponiblesVenta()
{
    $conectar = conectar_oll_BD();
    $texto = trim($_POST['texto'] ?? '');

    $whereDetalle = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $whereDetalle[] = "(LOWER(p.codigo) LIKE LOWER(:texto) OR LOWER(p.descripcion) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }

    $sql = "
        WITH color_stats AS (
            SELECT
                reo.empaquetado_id,
                COUNT(DISTINCT reo.color_id) AS colores_distintos,
                MIN(reo.color_id) AS unico_color_id
            FROM rel_empaquetado_origen reo
            WHERE reo.deleted_at IS NULL
            GROUP BY reo.empaquetado_id
        ),
        detalle AS (
            SELECT
                emp.id AS empaquetado_id,
                emp.producto_id,
                CASE
                    WHEN cs.colores_distintos IS NULL THEN NULL   -- legado
                    WHEN cs.colores_distintos = 1 THEN cs.unico_color_id
                    ELSE -1                                        -- mezcla
                END AS color_id_efectivo,
                -- Cantidad en unidad base: uso interno para calcular paquetes.
                emp.cantidad_tota * COALESCE(um.equivalencia, 1) AS cantidad_base
            FROM empaquetado emp
            LEFT JOIN color_stats cs ON cs.empaquetado_id = emp.id
            JOIN unidad_medida um ON um.id = emp.unidad_medida
            WHERE emp.deleted_at IS NULL AND emp.pasado_venta IS NULL
        ),
        producto_venta AS (
            SELECT
                p.id AS producto_id,
                uv.nombre_corto AS unidad_venta_corto,
                p.cant_equivale * COALESCE(ue.equivalencia, 1) AS capacidad_paquete_venta_base
            FROM producto p
            LEFT JOIN unidad_medida uv ON uv.id = p.unidad_venta_id
            LEFT JOIN unidad_medida ue ON ue.id = p.unidad_equivale_id
            WHERE p.cant_equivale IS NOT NULL AND p.unidad_equivale_id IS NOT NULL
        )
        SELECT
            dc.producto_id,
            p.codigo AS producto_codigo,
            p.descripcion AS producto,
            dc.color_id_efectivo AS color_id,
            CASE
                WHEN dc.color_id_efectivo IS NULL THEN 'Sin color (registro legado)'
                WHEN dc.color_id_efectivo = -1 THEN 'Mezcla'
                ELSE co.nombre
            END AS color,
            FLOOR(SUM(dc.cantidad_base) / pv.capacidad_paquete_venta_base) AS paquetes_disponibles,
            pv.unidad_venta_corto
        FROM detalle dc
        JOIN producto p ON p.id = dc.producto_id
        JOIN producto_venta pv ON pv.producto_id = dc.producto_id
                                AND pv.capacidad_paquete_venta_base > 0
        LEFT JOIN color co ON co.id = dc.color_id_efectivo AND dc.color_id_efectivo <> -1
        WHERE " . implode(' AND ', $whereDetalle) . "
        GROUP BY dc.producto_id, p.codigo, p.descripcion, dc.color_id_efectivo, co.nombre,
                 pv.unidad_venta_corto, pv.capacidad_paquete_venta_base
        HAVING FLOOR(SUM(dc.cantidad_base) / pv.capacidad_paquete_venta_base) > 0
        ORDER BY p.descripcion,
                 CASE WHEN dc.color_id_efectivo = -1 THEN 1 ELSE 0 END,
                 co.nombre NULLS LAST
        LIMIT 30
    ";

    $result = executeQuery($conectar, $sql, $params);
    responderVenta(true, 'OK', ['disponibles' => $result]);
}

// =============================================================================
// CONSUMO FIFO DE STOCK (empaquetado)
// Sigue operando en UNIDAD BASE por dentro — eso no cambia. Lo que cambia
// es quién calcula la cantidad en unidad base a pedir: ahora es
// guardarVenta(), convirtiendo paquetes -> base con
// obtenerCapacidadPaqueteVenta(), en vez de recibir la cantidad ya en
// base directo del frontend.
// =============================================================================

function consumirStockFIFOVenta($conectar, int $productoId, ?int $colorIdEfectivo, float $cantidadNecesariaBase): array
{
    $stmt = $conectar->prepare("
        SELECT t1.id, t1.cantidad_tota, um.equivalencia, um.unidad_base_id
        FROM empaquetado t1
        JOIN unidad_medida um ON um.id = t1.unidad_medida
        WHERE t1.producto_id = :producto_id
          AND t1.deleted_at IS NULL
          AND t1.pasado_venta IS NULL
          AND t1.cantidad_tota > 0
          AND (
              SELECT CASE
                  WHEN COUNT(DISTINCT reo.color_id) = 0 THEN NULL
                  WHEN COUNT(DISTINCT reo.color_id) = 1 THEN MIN(reo.color_id)
                  ELSE -1
              END
              FROM rel_empaquetado_origen reo
              WHERE reo.empaquetado_id = t1.id AND reo.deleted_at IS NULL
          ) IS NOT DISTINCT FROM :color_id_efectivo
        ORDER BY t1.created_at ASC, t1.id ASC
        FOR UPDATE OF t1
    ");
    $stmt->execute([
        'producto_id'       => $productoId,
        'color_id_efectivo' => $colorIdEfectivo,
    ]);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $restante = $cantidadNecesariaBase;
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
        $etiquetaColor = $colorIdEfectivo === null
            ? 'sin color (registro legado)'
            : ($colorIdEfectivo === -1 ? 'Mezcla' : "color #$colorIdEfectivo");
        throw new RuntimeException("Stock insuficiente para producto #$productoId ($etiquetaColor): faltan $restante (unidad base).");
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

function restaurarStockVenta($conectar, array $consumo): void
{
    foreach ($consumo as $c) {
        $stmt = $conectar->prepare("SELECT cantidad_tota FROM empaquetado WHERE id = :id FOR UPDATE");
        $stmt->execute(['id' => $c['empaquetado_id']]);
        $actual = $stmt->fetchColumn();
        if ($actual === false) continue;

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

// Resuelve el texto de producto/color para el snapshot de un ítem.
function resolverInfoItemVenta($conectar, int $productoId, ?int $colorIdEfectivo): array
{
    $productoRow = executeQuery($conectar,
        "SELECT id, codigo, descripcion FROM producto WHERE id = :id",
        ['id' => $productoId]
    );
    if (empty($productoRow)) {
        throw new RuntimeException("No se encontró el producto #$productoId.");
    }

    if ($colorIdEfectivo === null) {
        $colorNombre = 'Sin color (registro legado)';
    } elseif ($colorIdEfectivo === -1) {
        $colorNombre = 'Mezcla';
    } else {
        $colorRow = executeQuery($conectar, "SELECT nombre FROM color WHERE id = :id", ['id' => $colorIdEfectivo]);
        if (empty($colorRow)) {
            throw new RuntimeException("El color #$colorIdEfectivo indicado no existe.");
        }
        $colorNombre = $colorRow[0]['nombre'];
    }

    return [
        'producto_codigo' => $productoRow[0]['codigo'],
        'producto'        => $productoRow[0]['descripcion'],
        'color'           => $colorNombre,
    ];
}

// FIX (2026-08-29): $item['cantidad'] ahora es PAQUETES (unidad real de
// venta), no unidad base. Se convierte a base con
// obtenerCapacidadPaqueteVenta() antes de descontar stock. Se exige
// cantidad de paquetes ENTERA (no se venden fracciones de paquete). Si el
// producto no tiene su unidad de venta configurada, se rechaza el ítem
// con un mensaje claro en vez de vender en una unidad ambigua.
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
            $productoId       = (int)($item['producto_id'] ?? 0);
            $cantidadPaquetes = (float)($item['cantidad'] ?? 0);
            $precio           = (float)($item['precio_unitario'] ?? 0);
            $colorIdEfectivo  = !empty($item['color_id']) ? (int)$item['color_id'] : null;

            if ($productoId <= 0 || $cantidadPaquetes <= 0) {
                throw new RuntimeException('Cada ítem debe tener producto y cantidad válidos.');
            }
            if (abs($cantidadPaquetes - round($cantidadPaquetes)) > 0.0001) {
                throw new RuntimeException('La cantidad debe ser en paquetes completos (sin decimales).');
            }
            $cantidadPaquetes = (float) round($cantidadPaquetes);

            $capacidadVenta = obtenerCapacidadPaqueteVenta($conectar, $productoId);
            if ($capacidadVenta['capacidad_base'] === null) {
                $info = resolverInfoItemVenta($conectar, $productoId, $colorIdEfectivo);
                throw new RuntimeException(
                    "\"{$info['producto']}\" no tiene configurada su unidad de venta " .
                    "(cant_equivale / unidad_equivale_id). Configúrala en Productos antes de vender por paquete."
                );
            }

            $info = resolverInfoItemVenta($conectar, $productoId, $colorIdEfectivo);
            $cantidadBaseNecesaria = round($cantidadPaquetes * $capacidadVenta['capacidad_base'], 4);

            $consumo  = consumirStockFIFOVenta($conectar, $productoId, $colorIdEfectivo, $cantidadBaseNecesaria);
            $subtotal = round($cantidadPaquetes * $precio, 2);
            $montoTotal += $subtotal;

            $itemsFinal[] = [
                'producto_id'         => $productoId,
                'producto_codigo'     => $info['producto_codigo'],
                'producto'            => $info['producto'],
                'color_id'            => $colorIdEfectivo,
                'color'               => $info['color'],
                'cantidad'            => $cantidadPaquetes,           // en PAQUETES
                'unidad_venta_corto'  => $capacidadVenta['unidad_venta_corto'],
                'cantidad_base_consumida' => $cantidadBaseNecesaria,  // auditoría
                'precio_unitario'     => $precio,
                'subtotal'            => $subtotal,
                'js_consumo'          => $consumo,
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