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
 *
 * FIX (2026-09-05): la vista de Ventas se dividió en dos páginas
 * (puntoVenta.php y listadoVentas.php). listarVentas() ahora acepta un
 * filtro adicional de rango de fechas ('hoy' | 'semana' | 'todas' |
 * 'personalizado' + fecha_inicio/fecha_fin) para el listado con tabs.
 * No cambia nada más del contrato existente (los parámetros texto/estado
 * siguen funcionando igual que antes).
 *
 * NUEVO (2026-09-08): trazabilidad venta <-> empaquetado vía
 * empaquetado.js_venta (jsonb, array). Como el consumo es FIFO y puede
 * tocar VARIOS registros de empaquetado por venta (y un mismo
 * empaquetado puede ser tocado por VARIAS ventas a lo largo del tiempo),
 * js_venta guarda un arreglo de registros de consumo — mismo patrón que
 * js_operarios/js_historial en otros módulos. La columna legacy
 * venta.empaquetado (FK a un solo id) queda sin usar: ya no representa
 * bien la realidad de "una venta puede tocar N empaquetados".
 * Al anular, NO se borra el registro de js_venta: se marca 'anulado' =>
 * true (se conserva el historial completo, igual que js_historial).
 */

ob_start();

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
require_once __DIR__ . '/auditoria.php';
require_once __DIR__ . '/clssVerificarSession.php';
require_once __DIR__ . '/tipoCambio.php';

if (isset($_POST['accion'])) {
    try {
        controladorVenta((string) $_POST['accion']);
    } catch (PDOException $e) {
        error_log('Error de base de datos en clssVenta.php: ' . $e->getMessage());
        responderVenta(false, 'Error de base de datos.');
    } catch (Throwable $e) {
        error_log('Error inesperado en clssVenta.php: ' . $e->getMessage());
        responderVenta(false, 'Error inesperado en el servidor.');
    }
}

function controladorVenta(string $accion): void
{
    $usuario = exigirSesion();

    $accionesLectura = [
        'LISTARVENTAS',
        'REPORTEVENTASPORPRODUCTO',
        'REPORTEVENTASPORVENDEDORCLIENTE',
        'BUSCARPRODUCTOSREPORTEVENTAS',
        'LISTARVENDEDORESVENTA',
        'LISTARCUENTASRECEPTORAS',
        'CONSULTARTIPOCAMBIOVENTA',
        'OBTENERVENTA',
        'BUSCARCLIENTES',
        'BUSCARDISPONIBLESVENTA',
    ];

    $accionesVentas = [
        'GUARDARVENTA',
    ];

    // Anular repone stock de empaquetado y altera una operación ya registrada.
    $accionesSoloAdmin = [
        'ANULARVENTA',
        'CREARCUENTARECEPTORA',
        'EDITARCUENTARECEPTORA',
    ];

    if (in_array($accion, $accionesLectura, true)) {
        exigirRol($usuario, ['administrador', 'ventas', 'consulta']);

    } elseif (in_array($accion, $accionesVentas, true)) {
        exigirRol($usuario, ['administrador', 'ventas']);

    } elseif (in_array($accion, $accionesSoloAdmin, true)) {
        exigirRol($usuario, ['administrador']);

    } else {
        responderAcceso(400, 'Acción no reconocida.');
    }

    switch ($accion) {
        case 'LISTARVENTAS':
            listarVentas();
            break;

        case 'REPORTEVENTASPORPRODUCTO':
            reporteVentasPorProducto();
            break;

        case 'REPORTEVENTASPORVENDEDORCLIENTE':
            reporteVentasPorVendedorCliente();
            break;

        case 'BUSCARPRODUCTOSREPORTEVENTAS':
            buscarProductosReporteVentas();
            break;

        case 'LISTARVENDEDORESVENTA':
            listarVendedoresVenta();
            break;

        case 'LISTARCUENTASRECEPTORAS':
            listarCuentasReceptorasVenta();
            break;

        case 'CONSULTARTIPOCAMBIOVENTA':
            consultarTipoCambioVenta();
            break;

        case 'CREARCUENTARECEPTORA':
            crearCuentaReceptoraVenta();
            break;

        case 'EDITARCUENTARECEPTORA':
            editarCuentaReceptoraVenta();
            break;

        case 'OBTENERVENTA':
            obtenerVenta((int) ($_POST['id'] ?? 0));
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
            anularVenta((int) ($_POST['id'] ?? 0));
            break;
    }
}

// =============================================================================
// TRAZABILIDAD VENTA <-> EMPAQUETADO (empaquetado.js_venta)
// Un mismo empaquetado puede ser consumido por varias ventas a lo largo
// del tiempo (FIFO parcial), así que js_venta es un ARRAY de registros,
// no un objeto único. Se lee/decodifica/escribe en PHP (igual que
// resolverOperariosEmpaquetado() en clssEmpaquetado.php) en vez de
// manipular el jsonb directo en SQL, porque acá sí necesitamos modificar
// un elemento puntual del arreglo al anular (no solo concatenar al final).
// =============================================================================

// Agrega un nuevo registro de consumo al js_venta del empaquetado indicado.
// Se llama DENTRO de la misma transacción de guardarVenta(), una vez que
// ya existen $ventaId y $codigo (se generan después del consumo FIFO).
function agregarRegistroJsVentaEmpaquetado($conectar, int $empaquetadoId, array $registro): void
{
    $actual = executeQuery($conectar, "SELECT js_venta FROM empaquetado WHERE id = :id", ['id' => $empaquetadoId]);
    $lista = [];
    if (!empty($actual) && !empty($actual[0]['js_venta'])) {
        $lista = json_decode($actual[0]['js_venta'], true) ?: [];
    }
    $lista[] = $registro;

    executeNonQuery($conectar, "
        UPDATE empaquetado SET js_venta = :js_venta WHERE id = :id
    ", [
        'js_venta' => json_encode($lista, JSON_UNESCAPED_UNICODE),
        'id'       => $empaquetadoId,
    ]);
}

// Marca como anulados (sin borrar) todos los registros de js_venta de un
// empaquetado que pertenezcan a la venta indicada. Se usa en anularVenta().
function marcarAnuladoJsVentaEmpaquetado($conectar, int $empaquetadoId, int $ventaId): void
{
    $actual = executeQuery($conectar, "SELECT js_venta FROM empaquetado WHERE id = :id", ['id' => $empaquetadoId]);
    if (empty($actual) || empty($actual[0]['js_venta'])) return;

    $lista = json_decode($actual[0]['js_venta'], true) ?: [];
    $huboCambios = false;
    foreach ($lista as &$reg) {
        if ((int)($reg['venta_id'] ?? 0) === $ventaId && empty($reg['anulado'])) {
            $reg['anulado']         = true;
            $reg['fecha_anulacion'] = date('Y-m-d H:i:s');
            $huboCambios = true;
        }
    }
    unset($reg);
    if (!$huboCambios) return;

    executeNonQuery($conectar, "
        UPDATE empaquetado SET js_venta = :js_venta WHERE id = :id
    ", [
        'js_venta' => json_encode($lista, JSON_UNESCAPED_UNICODE),
        'id'       => $empaquetadoId,
    ]);
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
// agrupando el inventario por producto, sin separar por color. En punto de
// venta todos los colores y mezclas del mismo producto comparten stock.
//
// REESCRITO (2026-08-29 bis): confirmado que no hay venta por otra unidad
// como excepción, así que ahora el JOIN a producto_venta es INNER y exige
// capacidad_paquete_venta_base > 0 — un producto sin su unidad de venta
// configurada simplemente no aparece en el buscador (antes aparecía y
// recién fallaba al guardar). Se quita el campo sin_config_venta, que ya
// no aplica.
//
// Nota (2026-09-05): con "texto" vacío devuelve los primeros disponibles
// sin filtrar (usado ahora por el grid de Venta Rápida al cargar la
// página, no solo por el buscador de texto).
// =============================================================================

function buscarDisponiblesVenta()
{
    $conectar = conectar_oll_BD();
    $texto = trim($_POST['texto'] ?? '');
    $params = [];
    $where = ["p.activo IS TRUE", "COALESCE(p.stock, 0) >= 1"];
    if ($texto !== '') {
        $where[] = "(LOWER(p.codigo) LIKE LOWER(:texto) OR LOWER(p.descripcion) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }
    $result = executeQuery($conectar, "
        SELECT p.id AS producto_id,
               p.codigo AS producto_codigo,
               p.descripcion AS producto,
               p.img_ruta AS producto_imagen,
               NULL::integer AS color_id,
               'Todos los colores' AS color,
               NULL::text AS color_hex,
               FLOOR(COALESCE(p.stock, 0)) AS paquetes_disponibles,
               uv.nombre_corto AS unidad_venta_corto
        FROM producto p
        JOIN unidad_medida uv ON uv.id = p.unidad_venta_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY p.descripcion
        LIMIT 30
    ", $params);
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

function consumirStockFIFOVenta($conectar, int $productoId, float $cantidadNecesariaBase): array
{
    $stmt = $conectar->prepare("
        SELECT t1.id, t1.cantidad_disponible_tmp, um.equivalencia, um.unidad_base_id
        FROM empaquetado t1
        JOIN unidad_medida um ON um.id = t1.unidad_medida
        WHERE t1.producto_id = :producto_id
          AND t1.deleted_at IS NULL
          AND t1.cantidad_disponible_tmp > 0
        ORDER BY t1.created_at ASC, t1.id ASC
        FOR UPDATE OF t1
    ");
    $stmt->execute([
        'producto_id'       => $productoId,
    ]);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $restante = $cantidadNecesariaBase;
    $consumo  = [];

    foreach ($registros as $reg) {
        if ($restante <= 0.0001) break;

        $equivalencia   = !empty($reg['equivalencia']) ? (float)$reg['equivalencia'] : 1.0;
        $esConvertible  = !empty($reg['unidad_base_id']);
        $cantidadDisp   = (float)$reg['cantidad_disponible_tmp'];
        $disponibleBase = $esConvertible ? $cantidadDisp * $equivalencia : $cantidadDisp;

        if ($disponibleBase <= 0.0001) continue;

        $aConsumirBase   = min($restante, $disponibleBase);
        $aConsumirPropio = $esConvertible ? ($aConsumirBase / $equivalencia) : $aConsumirBase;
        $nuevaCantidad   = round($cantidadDisp - $aConsumirPropio, 4);
        if ($nuevaCantidad < 0) $nuevaCantidad = 0;

        $consumo[] = [
            'empaquetado_id'      => (int)$reg['id'],
            'cantidad_antes'      => $cantidadDisp,
            'cantidad_consumida'  => round($aConsumirPropio, 4),
            'cantidad_despues'    => $nuevaCantidad,
        ];

        $restante = round($restante - $aConsumirBase, 4);
    }

    if ($restante > 0.0001) {
        throw new RuntimeException("Stock insuficiente para producto #$productoId: faltan $restante (unidad base).");
    }

    foreach ($consumo as $c) {
        // CAMBIO: se descuenta cantidad_disponible_tmp, NUNCA cantidad_tota
        // (esa queda fija como el histórico de lo que se armó).
        $upd = $conectar->prepare("
            UPDATE empaquetado SET
                cantidad_disponible_tmp = :cantidad,
                pasado_venta            = CASE WHEN :cantidad2 <= 0 THEN NOW() ELSE pasado_venta END,
                update_at               = NOW()
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
        $stmt = $conectar->prepare("SELECT cantidad_disponible_tmp FROM empaquetado WHERE id = :id FOR UPDATE");
        $stmt->execute(['id' => $c['empaquetado_id']]);
        $actual = $stmt->fetchColumn();
        if ($actual === false) continue;

        $nuevaCantidad = round((float)$actual + (float)$c['cantidad_consumida'], 4);

        $upd = $conectar->prepare("
            UPDATE empaquetado SET
                cantidad_disponible_tmp = :cantidad,
                pasado_venta            = NULL,
                update_at               = NOW()
            WHERE id = :id
        ");
        $upd->execute(['cantidad' => $nuevaCantidad, 'id' => $c['empaquetado_id']]);
    }
}

/** Descuenta unidades de venta directamente del saldo maestro del producto. */
function descontarStockProductoVenta($conectar, int $productoId, float $cantidad): array
{
    $stmt = $conectar->prepare("SELECT id, codigo, descripcion, stock FROM producto WHERE id = :id AND activo IS TRUE FOR UPDATE");
    $stmt->execute(['id' => $productoId]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$producto) throw new RuntimeException('El producto ya no existe o está inactivo.');

    $stockAnterior = round((float)($producto['stock'] ?? 0), 2);
    if ($cantidad > $stockAnterior + 0.0001) {
        throw new RuntimeException(sprintf(
            'Stock insuficiente de %s. Disponible: %s; solicitado: %s %s.',
            $producto['descripcion'],
            number_format($stockAnterior, 2, '.', ''),
            number_format($cantidad, 0, '.', ''),
            'unidad(es) de venta'
        ));
    }

    $stockPosterior = round($stockAnterior - $cantidad, 2);
    if ($stockPosterior < 0 && $stockPosterior > -0.0001) $stockPosterior = 0;
    $actualizar = $conectar->prepare('UPDATE producto SET stock = :stock, updated_at = NOW() WHERE id = :id');
    $actualizar->execute(['stock' => $stockPosterior, 'id' => $productoId]);

    return ['stock_anterior' => $stockAnterior, 'stock_posterior' => $stockPosterior];
}

/** Registra salida o reversa de venta en el kardex global del producto. */
function registrarMovimientoVentaProducto($conectar, int $productoId, int $ventaId, string $tipo, float $cantidad, float $anterior, float $posterior): void
{
    $stmt = $conectar->prepare("
        INSERT INTO movimiento_inventario
            (producto_id, venta_id, fecha, tipo, es_traslado, cantidad, cantidad_anterior, cantidad_posterior, created_at)
        VALUES
            (:producto_id, :venta_id, CURRENT_DATE, :tipo, FALSE, :cantidad, :anterior, :posterior, NOW())
    ");
    $stmt->execute([
        'producto_id' => $productoId,
        'venta_id' => $ventaId,
        'tipo' => $tipo,
        'cantidad' => $cantidad,
        'anterior' => $anterior,
        'posterior' => $posterior,
    ]);
}
// =============================================================================
// VENTAS
// =============================================================================

// ACTUALIZADO (2026-09-05): se agrega el filtro opcional de rango de
// fechas usado por la nueva vista listadoVentas.php (tabs Día / Semana /
// Todas / Personalizado). texto/estado se mantienen exactamente igual
// que antes, así que cualquier otra pantalla que llame LISTARVENTAS sin
// mandar "rango" sigue funcionando sin cambios.
function listarVentas()
{
    $conectar = conectar_oll_BD();

    $texto       = trim($_POST['texto'] ?? '');
    $estado      = trim($_POST['estado'] ?? '');
    $rango       = trim($_POST['rango'] ?? '');       // 'hoy' | 'semana' | 'todas' | 'personalizado'
    $fechaInicio = trim($_POST['fecha_inicio'] ?? '');
    $fechaFin    = trim($_POST['fecha_fin'] ?? '');

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

    if ($rango === 'hoy') {
        $where[] = "v.fecha_venta::date = CURRENT_DATE";
    } elseif ($rango === 'semana') {
        $where[] = "v.fecha_venta >= date_trunc('week', CURRENT_DATE)";
    } elseif ($rango === 'personalizado' && $fechaInicio !== '' && $fechaFin !== '') {
        $where[] = "v.fecha_venta::date BETWEEN :fecha_inicio AND :fecha_fin";
        $params['fecha_inicio'] = $fechaInicio;
        $params['fecha_fin']    = $fechaFin;
    }
    // 'todas' (o cualquier otro valor no reconocido) no agrega filtro de fecha.

    $sql = "SELECT v.id, v.codigo, v.cliente_ruc,
                   COALESCE(p.razon_social, 'Clientes Varios') AS cliente_nombre,
                   v.fecha_venta, v.monto_total, v.estado,
                   COALESCE(v.js_session #>> '{venta_moneda,moneda_cobro}', v.js_session #>> '{venta_moneda,moneda}', 'PEN') AS moneda_cobro,
                   COALESCE(NULLIF(v.js_session #>> '{venta_moneda,monto_total_cobro}', '')::numeric, NULLIF(v.js_session #>> '{venta_moneda,monto_total_moneda}', '')::numeric, v.monto_total) AS monto_total_cobro,
                   jsonb_array_length(v.js_items) AS items_count
            FROM venta v
            LEFT JOIN proveedor p ON p.ruc = v.cliente_ruc
            WHERE " . implode(' AND ', $where) . "
            ORDER BY v.fecha_venta DESC";

    $result = executeQuery($conectar, $sql, $params);
    responderVenta(true, 'OK', ['ventas' => $result]);
}

/** Reporte agregado de ventas por producto, sin desglose por sucursal. */
function reporteVentasPorProducto(): void
{
    $conectar = conectar_oll_BD();
    $fechaInicio = trim($_POST['fecha_inicio'] ?? '');
    $fechaFin = trim($_POST['fecha_fin'] ?? '');
    $texto = trim($_POST['texto'] ?? '');

    $todasLasVentas = $fechaInicio === '' && $fechaFin === '';
    $fechaValida = static function (string $fecha): bool {
        $obj = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        return $obj !== false && $obj->format('Y-m-d') === $fecha;
    };
    if (!$todasLasVentas && (!$fechaValida($fechaInicio) || !$fechaValida($fechaFin))) {
        responderVenta(false, 'Selecciona un rango de fechas válido.');
    }
    if (!$todasLasVentas && $fechaInicio > $fechaFin) {
        responderVenta(false, 'La fecha inicial no puede ser posterior a la fecha final.');
    }

    $where = ["v.estado = 'completada'"];
    $params = [];
    if (!$todasLasVentas) {
        $where[] = 'v.fecha_venta >= CAST(:fecha_inicio AS date)';
        $where[] = "v.fecha_venta < CAST(:fecha_fin AS date) + INTERVAL '1 day'";
        $params['fecha_inicio'] = $fechaInicio;
        $params['fecha_fin'] = $fechaFin;
    }
    if ($texto !== '') {
        $where[] = "(LOWER(COALESCE(p.codigo, item->>'producto_codigo', '')) LIKE LOWER(:texto)
                     OR LOWER(COALESCE(p.descripcion, item->>'producto', '')) LIKE LOWER(:texto))";
        $params['texto'] = '%' . $texto . '%';
    }

    $cte = "WITH lineas AS (
        SELECT v.id AS venta_id,
               v.fecha_venta::date AS fecha,
               item->>'producto_id' AS producto_id,
               COALESCE(p.codigo, item->>'producto_codigo', '—') AS codigo,
               COALESCE(p.descripcion, item->>'producto', 'Producto eliminado') AS producto,
               COALESCE(NULLIF(item->>'unidad_venta_corto', ''), 'PAQ') AS unidad,
               COALESCE(NULLIF(item->>'cantidad', '')::numeric, 0) AS cantidad,
               COALESCE(NULLIF(item->>'precio_unitario', '')::numeric, 0) AS precio_unitario,
               UPPER(COALESCE(NULLIF(v.js_session #>> '{venta_moneda,moneda_cobro}', ''), NULLIF(v.js_session #>> '{venta_moneda,moneda}', ''), 'PEN')) AS moneda_cobro,
               COALESCE(NULLIF(item->>'tipo_cambio_aplicado', '')::numeric,
                        NULLIF(v.js_session #>> '{venta_moneda,tipo_cambio,venta}', '')::numeric, 1) AS tipo_cambio,
               COALESCE(NULLIF(item->>'subtotal', '')::numeric,
                   COALESCE(NULLIF(item->>'cantidad', '')::numeric, 0) *
                   COALESCE(NULLIF(item->>'precio_unitario', '')::numeric, 0)) AS importe
        FROM venta v
        CROSS JOIN LATERAL jsonb_array_elements(
            CASE WHEN jsonb_typeof(v.js_items) = 'array' THEN v.js_items ELSE '[]'::jsonb END
        ) AS detalle(item)
        LEFT JOIN producto p ON p.id = NULLIF(item->>'producto_id', '')::integer
        WHERE " . implode(' AND ', $where) . "
    )";

    $productos = executeQuery($conectar, $cte . "
        SELECT producto_id, codigo, producto, unidad,
               SUM(cantidad) AS cantidad_vendida,
               ROUND(SUM(importe), 2) AS importe_total,
               ROUND(SUM(CASE WHEN moneda_cobro = 'PEN' THEN importe ELSE 0 END), 2) AS importe_pen,
               ROUND(SUM(CASE WHEN moneda_cobro = 'USD' THEN importe / NULLIF(tipo_cambio, 0) ELSE 0 END), 2) AS importe_usd,
               ROUND(SUM(CASE WHEN moneda_cobro = 'EUR' THEN importe / NULLIF(tipo_cambio, 0) ELSE 0 END), 2) AS importe_eur,
               ROUND(SUM(importe) / NULLIF(SUM(cantidad), 0), 2) AS precio_promedio,
               COUNT(DISTINCT venta_id) AS ventas
        FROM lineas
        GROUP BY producto_id, codigo, producto, unidad
        ORDER BY SUM(importe) DESC, SUM(cantidad) DESC, producto
    ", $params);

    $resumenFilas = executeQuery($conectar, $cte . "
        SELECT COUNT(DISTINCT venta_id) AS ventas,
               COUNT(DISTINCT producto_id) AS productos,
               ROUND(COALESCE(SUM(importe), 0), 2) AS importe_total
        FROM lineas
    ", $params);
    $resumen = $resumenFilas[0] ?? ['ventas' => 0, 'productos' => 0, 'importe_total' => 0];
    $resumen['promedio_venta'] = (int)$resumen['ventas'] > 0
        ? round((float)$resumen['importe_total'] / (int)$resumen['ventas'], 2)
        : 0;

    $resumenMoneda = executeQuery($conectar, $cte . "
        SELECT moneda_cobro AS moneda,
               ROUND(SUM(CASE WHEN moneda_cobro = 'PEN' THEN importe ELSE importe / NULLIF(tipo_cambio, 0) END), 2) AS importe_total
        FROM lineas
        GROUP BY moneda_cobro
        ORDER BY CASE moneda_cobro WHEN 'PEN' THEN 1 WHEN 'USD' THEN 2 WHEN 'EUR' THEN 3 ELSE 4 END, moneda_cobro
    ", $params);

    if ($todasLasVentas) {
        $dias = executeQuery($conectar, $cte . "
            SELECT TO_CHAR(DATE_TRUNC('month', fecha), 'YYYY-MM') AS fecha,
                   ROUND(SUM(importe), 2) AS importe_total,
                   COUNT(DISTINCT venta_id) AS ventas
            FROM lineas
            GROUP BY DATE_TRUNC('month', fecha)
            ORDER BY DATE_TRUNC('month', fecha)
        ", $params);
    } else {
        $paramsDias = $params + ['serie_inicio' => $fechaInicio, 'serie_fin' => $fechaFin];
        $dias = executeQuery($conectar, $cte . "
            SELECT TO_CHAR(calendario.fecha::date, 'YYYY-MM-DD') AS fecha,
                   ROUND(COALESCE(SUM(lineas.importe), 0), 2) AS importe_total,
                   COUNT(DISTINCT lineas.venta_id) AS ventas
            FROM generate_series(
                CAST(:serie_inicio AS date), CAST(:serie_fin AS date), INTERVAL '1 day'
            ) AS calendario(fecha)
            LEFT JOIN lineas ON lineas.fecha = calendario.fecha::date
            GROUP BY calendario.fecha
            ORDER BY calendario.fecha
        ", $paramsDias);
    }

    responderVenta(true, 'OK', [
        'productos' => $productos,
        'resumen' => $resumen,
        'resumen_moneda' => $resumenMoneda,
        'dias' => $dias,
        'fecha_inicio' => $todasLasVentas ? null : $fechaInicio,
        'fecha_fin' => $todasLasVentas ? null : $fechaFin,
        'todas_las_ventas' => $todasLasVentas,
    ]);
}

/** Resúmenes del mismo periodo agrupados por vendedor, cliente y destino de pago. */
function reporteVentasPorVendedorCliente(): void
{
    $conectar = conectar_oll_BD();
    $fechaInicio = trim($_POST['fecha_inicio'] ?? '');
    $fechaFin = trim($_POST['fecha_fin'] ?? '');
    $todasLasVentas = $fechaInicio === '' && $fechaFin === '';
    $fechaValida = static function (string $fecha): bool {
        $obj = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        return $obj !== false && $obj->format('Y-m-d') === $fecha;
    };
    if (!$todasLasVentas && (!$fechaValida($fechaInicio) || !$fechaValida($fechaFin))) {
        responderVenta(false, 'Selecciona un rango de fechas válido.');
    }
    if (!$todasLasVentas && $fechaInicio > $fechaFin) {
        responderVenta(false, 'La fecha inicial no puede ser posterior a la fecha final.');
    }

    $where = ["v.estado = 'completada'"];
    $params = [];
    if (!$todasLasVentas) {
        $where[] = 'v.fecha_venta >= CAST(:fecha_inicio AS date)';
        $where[] = "v.fecha_venta < CAST(:fecha_fin AS date) + INTERVAL '1 day'";
        $params['fecha_inicio'] = $fechaInicio;
        $params['fecha_fin'] = $fechaFin;
    }
    $ventasPeriodo = "WITH ventas_periodo AS (
        SELECT v.id, v.fecha_venta, v.monto_total, v.vendedor_usuario_id, v.cliente_ruc, v.js_session,
               COALESCE(u.nombre_completo, 'Sin vendedor registrado') AS vendedor,
               COALESCE(p.razon_social, 'Clientes Varios') AS cliente
        FROM venta v
        LEFT JOIN usuario u ON u.id = v.vendedor_usuario_id
        LEFT JOIN proveedor p ON p.ruc = v.cliente_ruc
        WHERE " . implode(' AND ', $where) . "
    ) ";

    $vendedores = executeQuery($conectar, $ventasPeriodo . "
        SELECT vendedor_usuario_id, vendedor,
               COUNT(*) AS ventas,
               ROUND(SUM(monto_total), 2) AS importe_total,
               ROUND(AVG(monto_total), 2) AS promedio_venta,
               COUNT(DISTINCT cliente_ruc) AS clientes
        FROM ventas_periodo
        GROUP BY vendedor_usuario_id, vendedor
        ORDER BY SUM(monto_total) DESC, vendedor
    ", $params);

    $clientes = executeQuery($conectar, $ventasPeriodo . "
        SELECT cliente_ruc, cliente,
               COUNT(*) AS ventas,
               ROUND(SUM(monto_total), 2) AS importe_total,
               ROUND(AVG(monto_total), 2) AS promedio_venta,
               MAX(fecha_venta) AS ultima_venta
        FROM ventas_periodo
        GROUP BY cliente_ruc, cliente
        ORDER BY SUM(monto_total) DESC, cliente
    ", $params);

    $pagos = executeQuery($conectar, $ventasPeriodo . ", pagos_periodo AS (
        SELECT vp.vendedor_usuario_id, vp.vendedor,
               pago.medio_pago, cuenta.nombre AS cuenta,
               pago.monto
        FROM ventas_periodo vp
        JOIN venta_pago pago ON pago.venta_id = vp.id
        LEFT JOIN cuenta_receptora cuenta ON cuenta.id = pago.cuenta_receptora_id
    )
        SELECT vendedor_usuario_id, vendedor, medio_pago,
               CASE
                   WHEN medio_pago = 'efectivo' THEN 'Caja / efectivo'
                   WHEN cuenta IS NOT NULL THEN cuenta
                   WHEN medio_pago IN ('yape', 'plin') THEN 'Billetera del vendedor'
                   ELSE 'Cuenta no disponible'
               END AS destino,
               ROUND(SUM(monto), 2) AS importe_total,
               COUNT(*) AS pagos
        FROM pagos_periodo
        GROUP BY vendedor_usuario_id, vendedor, medio_pago, destino
        ORDER BY vendedor, medio_pago, destino
    ", $params);

    $ingresosMoneda = executeQuery($conectar, $ventasPeriodo . ", pagos_moneda AS (
        SELECT vp.id AS venta_id,
               UPPER(COALESCE(NULLIF(pago->>'moneda', ''), 'PEN')) AS moneda,
               COALESCE(NULLIF(pago->>'monto', '')::numeric, 0) AS monto
        FROM ventas_periodo vp
        CROSS JOIN LATERAL jsonb_array_elements(
            CASE
                WHEN jsonb_typeof(vp.js_session #> '{venta_moneda,pagos}') = 'array'
                    THEN vp.js_session #> '{venta_moneda,pagos}'
                ELSE '[]'::jsonb
            END
        ) AS detalle(pago)
        UNION ALL
        SELECT vp.id AS venta_id, 'PEN' AS moneda, pago.monto
        FROM ventas_periodo vp
        JOIN venta_pago pago ON pago.venta_id = vp.id
        WHERE COALESCE(jsonb_array_length(
            CASE
                WHEN jsonb_typeof(vp.js_session #> '{venta_moneda,pagos}') = 'array'
                    THEN vp.js_session #> '{venta_moneda,pagos}'
                ELSE '[]'::jsonb
            END
        ), 0) = 0
    )
        SELECT moneda, ROUND(SUM(monto), 2) AS importe_total,
               COUNT(*) AS pagos, COUNT(DISTINCT venta_id) AS ventas
        FROM pagos_moneda
        GROUP BY moneda
        ORDER BY CASE moneda WHEN 'PEN' THEN 1 WHEN 'USD' THEN 2 WHEN 'EUR' THEN 3 ELSE 4 END, moneda
    ", $params);

    responderVenta(true, 'OK', [
        'vendedores' => $vendedores,
        'clientes' => $clientes,
        'pagos' => $pagos,
        'ingresos_moneda' => $ingresosMoneda,
        'fecha_inicio' => $todasLasVentas ? null : $fechaInicio,
        'fecha_fin' => $todasLasVentas ? null : $fechaFin,
        'todas_las_ventas' => $todasLasVentas,
    ]);
}

/** Sugerencias de catálogo para el autocompletado del reporte de ventas. */
function buscarProductosReporteVentas(): void
{
    $texto = trim($_POST['texto'] ?? '');
    $longitudTexto = function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : strlen($texto);
    if ($longitudTexto < 2) {
        responderVenta(true, 'OK', ['productos' => []]);
    }

    $conectar = conectar_oll_BD();
    $productos = executeQuery($conectar, "
        SELECT id, codigo, descripcion
        FROM producto
        WHERE activo = true
          AND (LOWER(codigo) LIKE LOWER(:texto)
           OR LOWER(descripcion) LIKE LOWER(:texto)
          )
        ORDER BY descripcion, codigo
        LIMIT 12
    ", ['texto' => '%' . $texto . '%']);

    responderVenta(true, 'OK', ['productos' => $productos]);
}

function listarVendedoresVenta(): void
{
    $conectar = conectar_oll_BD();
    $vendedores = executeQuery($conectar, "
        SELECT id, nombre_completo
        FROM usuario
        WHERE deleted_at IS NULL
          AND operario_id IS NULL
          AND UPPER(COALESCE(rol_y_perfiles->>'rol', '')) IN ('ADMINISTRADOR', 'VENTAS')
        ORDER BY nombre_completo
    ");
    responderVenta(true, 'OK', ['vendedores' => $vendedores]);
}

function listarCuentasReceptorasVenta(): void
{
    $conectar = conectar_oll_BD();
    $cuentas = executeQuery($conectar, "
        SELECT id, nombre
        FROM cuenta_receptora
        WHERE activo IS TRUE
        ORDER BY nombre
    ");
    responderVenta(true, 'OK', ['cuentas' => $cuentas]);
}

function consultarTipoCambioVenta(): void
{
    $fecha = trim($_POST['fecha'] ?? '');
    $moneda = strtoupper(trim($_POST['moneda'] ?? 'USD'));
    try {
        responderVenta(true, 'OK', ['tipo_cambio' => consultarTipoCambio($fecha, $moneda)]);
    } catch (Throwable $e) {
        responderVenta(false, $e->getMessage());
    }
}

function crearCuentaReceptoraVenta(): void
{
    $nombre = trim($_POST['nombre'] ?? '');
    $longitud = function_exists('mb_strlen') ? mb_strlen($nombre, 'UTF-8') : strlen($nombre);
    if ($nombre === '' || $longitud > 100) {
        responderVenta(false, 'Ingresa un nombre de cuenta de hasta 100 caracteres.');
    }
    $conectar = conectar_oll_BD();
    try {
        $stmt = $conectar->prepare("
            INSERT INTO cuenta_receptora (nombre, activo)
            VALUES (:nombre, TRUE)
            RETURNING id, nombre
        ");
        $stmt->execute(['nombre' => $nombre]);
        responderVenta(true, 'Cuenta agregada.', ['cuenta' => $stmt->fetch(PDO::FETCH_ASSOC) ?: null]);
    } catch (PDOException $e) {
        if (($e->errorInfo[0] ?? '') === '23505') {
            responderVenta(false, 'Ya existe una cuenta con ese nombre.');
        }
        throw $e;
    }
}

function editarCuentaReceptoraVenta(): void
{
    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $longitud = function_exists('mb_strlen') ? mb_strlen($nombre, 'UTF-8') : strlen($nombre);
    if ($id <= 0 || $nombre === '' || $longitud > 100) {
        responderVenta(false, 'Selecciona una cuenta e ingresa un nombre de hasta 100 caracteres.');
    }

    $conectar = conectar_oll_BD();
    try {
        $stmt = $conectar->prepare("UPDATE cuenta_receptora SET nombre = :nombre WHERE id = :id RETURNING id, nombre");
        $stmt->execute(['nombre' => $nombre, 'id' => $id]);
        $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cuenta) responderVenta(false, 'La cuenta receptora ya no existe.');
        responderVenta(true, 'Cuenta actualizada.', ['cuenta' => $cuenta]);
    } catch (PDOException $e) {
        if (($e->errorInfo[0] ?? '') === '23505') {
            responderVenta(false, 'Ya existe una cuenta con ese nombre.');
        }
        throw $e;
    }
}

function obtenerVenta(int $id)
{
    $conectar = conectar_oll_BD();
    if ($id <= 0) responderVenta(false, 'Venta inválida.');

    $result = executeQuery($conectar, "
        SELECT v.*, COALESCE(p.razon_social, 'Clientes Varios') AS cliente_nombre, p.nombre_comercial AS cliente_comercial,
               p.ubicacion AS cliente_ubicacion, p.correo AS cliente_correo
        FROM venta v
        LEFT JOIN proveedor p ON p.ruc = v.cliente_ruc
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
//
// NUEVO (2026-09-08): al final, con $ventaId y $codigo ya generados, se
// registra en empaquetado.js_venta (por cada empaquetado_id tocado en el
// consumo FIFO de cada ítem) qué venta lo consumió y cuánto.
function guardarVenta()
{
    $conectar = conectar_oll_BD();

    // Una venta sin cliente identificado se registra como venta de mostrador
    // usando el RUC/DNI reservado de "Clientes Varios".
    $cliente_ruc = trim($_POST['cliente_ruc'] ?? '');
    if ($cliente_ruc === '') $cliente_ruc = '99999999';
    $itemsJson   = trim($_POST['items'] ?? '[]');

    if ($cliente_ruc !== '99999999') {
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
    }

    $itemsInput = json_decode($itemsJson, true);
    if (!is_array($itemsInput) || count($itemsInput) === 0) {
        responderVenta(false, 'Debes agregar al menos un producto a la venta.');
    }

    $moneda = strtoupper(trim($_POST['moneda'] ?? 'PEN'));
    if (!in_array($moneda, ['PEN', 'USD', 'EUR'], true)) responderVenta(false, 'Selecciona una moneda válida.');
    $fuenteTipoCambio = strtolower(trim($_POST['fuente_tipo_cambio'] ?? 'oficial'));
    $fechaTipoCambio = (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y-m-d');
    if ($moneda === 'PEN') {
        $tipoCambioAplicado = 1.0;
        $snapshotTipoCambio = ['fecha' => $fechaTipoCambio, 'compra' => 1, 'venta' => 1, 'fuente' => 'local', 'moneda' => 'PEN'];
    } elseif ($fuenteTipoCambio === 'oficial') {
        try {
            $snapshotTipoCambio = consultarTipoCambio($fechaTipoCambio, $moneda);
        } catch (Throwable $e) {
            responderVenta(false, $e->getMessage());
        }
        $snapshotTipoCambio['moneda'] = $moneda;
        $snapshotTipoCambio['fecha'] = $snapshotTipoCambio['fecha'] ?? $fechaTipoCambio;
        $tipoCambioAplicado = (float)($snapshotTipoCambio['venta'] ?? 0);
        $snapshotTipoCambio['fuente'] = 'oficial';
    } elseif ($fuenteTipoCambio === 'calle') {
        $tipoCambioAplicado = (float)($_POST['tipo_cambio'] ?? 0);
        if (!is_finite($tipoCambioAplicado) || $tipoCambioAplicado <= 0 || $tipoCambioAplicado > 10000) {
            responderVenta(false, 'Ingresa un tipo de cambio manual válido.');
        }
        $snapshotTipoCambio = [
            'fecha' => $fechaTipoCambio,
            'compra' => null,
            'venta' => null,
            'fuente' => 'calle',
            'moneda' => $moneda,
            'tasa_aplicada' => $tipoCambioAplicado,
        ];
    } else {
        responderVenta(false, 'Selecciona un origen de tipo de cambio válido.');
    }
    if (!is_finite($tipoCambioAplicado) || $tipoCambioAplicado <= 0) {
        responderVenta(false, 'No se pudo obtener un tipo de cambio válido.');
    }

    $vendedorId = (int)($_POST['vendedor_usuario_id'] ?? 0);
    $pagosInput = json_decode(trim($_POST['pagos'] ?? '[]'), true);
    if ($vendedorId <= 0) responderVenta(false, 'Selecciona quién realizó la venta.');
    if (!is_array($pagosInput) || count($pagosInput) === 0) {
        responderVenta(false, 'Agrega al menos una forma de pago.');
    }

    $vendedorValido = executeQuery($conectar, "
        SELECT id
        FROM usuario
        WHERE id = :id
          AND deleted_at IS NULL
          AND operario_id IS NULL
          AND UPPER(COALESCE(rol_y_perfiles->>'rol', '')) IN ('ADMINISTRADOR', 'VENTAS')
    ", ['id' => $vendedorId]);
    if (empty($vendedorValido)) responderVenta(false, 'El vendedor seleccionado no está activo o no puede registrar ventas.');

    $metodosPagoPermitidos = ['efectivo', 'deposito', 'transferencia', 'yape', 'plin'];
    $pagos = [];
    foreach ($pagosInput as $pago) {
        $medio = strtolower(trim((string)($pago['medio_pago'] ?? '')));
        $cuentaId = (int)($pago['cuenta_receptora_id'] ?? 0);
        $monto = round((float)($pago['monto'] ?? 0), 2);
        $referencia = trim((string)($pago['referencia'] ?? ''));
        if (!in_array($medio, $metodosPagoPermitidos, true) || $monto <= 0) {
            responderVenta(false, 'Revisa el medio de pago y el monto de cada pago.');
        }
        $requiereCuenta = in_array($medio, ['deposito', 'transferencia'], true);
        if ($requiereCuenta && $cuentaId <= 0) {
            responderVenta(false, 'Selecciona la cuenta receptora del depósito o transferencia.');
        }
        $permiteCuenta = in_array($medio, ['deposito', 'transferencia', 'yape', 'plin'], true);
        $pagos[] = [
            'medio_pago' => $medio,
            // Yape/Plin pueden ir al vendedor (sin cuenta) o a una billetera de empresa.
            'cuenta_receptora_id' => $permiteCuenta && $cuentaId > 0 ? $cuentaId : null,
            'monto' => $monto,
            'referencia' => $referencia !== '' ? $referencia : null,
        ];
    }

    try {
        $conectar->beginTransaction();

        // venta.cliente_ruc tiene una llave foránea a proveedor. Creamos una
        // sola vez el cliente reservado dentro de la misma transacción para
        // que las ventas de mostrador siempre cumplan dicha restricción.
        if ($cliente_ruc === '99999999') {
            $movimientoClienteVarios = obtenerMovimientoSesion('crear');
            $stmtClienteVarios = $conectar->prepare("
                INSERT INTO proveedor (
                    ruc, js_tipo, razon_social, nombre_comercial, telefonos_contacto,
                    correo, ubigeo, ubicacion, js_consulta_api,
                    created_at, js_session, js_historial
                ) VALUES (
                    '99999999', :js_tipo::jsonb, 'Clientes Varios', NULL, :telefonos_contacto::jsonb,
                    NULL, NULL, NULL, NULL,
                    NOW(), :js_session, :js_historial::jsonb
                )
                ON CONFLICT (ruc) DO NOTHING
            ");
            $stmtClienteVarios->execute([
                'js_tipo'            => '["cliente"]',
                'telefonos_contacto' => '[]',
                'js_session'         => json_encode($movimientoClienteVarios, JSON_UNESCAPED_UNICODE),
                'js_historial'       => json_encode([$movimientoClienteVarios], JSON_UNESCAPED_UNICODE),
            ]);
        }

        $itemsFinal = [];
        $montoTotal = 0.0;
        $montoTotalMoneda = 0.0;

        foreach ($itemsInput as $item) {
            $productoId       = (int)($item['producto_id'] ?? 0);
            $cantidadPaquetes = (float)($item['cantidad'] ?? 0);
            $precioMoneda     = (float)($item['precio_unitario'] ?? 0); // Los precios de lista siempre están en soles.
            // El color ya no divide el artículo de venta; se consumen paquetes
            // del producto consolidando todos sus colores.
            $colorIdEfectivo  = null;

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
            // En ventas el color no identifica una línea de producto: se
            // vende por producto y paquete, consolidando todos los colores.
            $info['color'] = 'Todos los colores';
            $cantidadBaseNecesaria = round($cantidadPaquetes * $capacidadVenta['capacidad_base'], 4);

            $stockMovimiento = descontarStockProductoVenta($conectar, $productoId, $cantidadPaquetes);
            $subtotalMoneda = round($cantidadPaquetes * $precioMoneda, 2);
            $precio = round($precioMoneda, 4);
            $subtotal = $subtotalMoneda;
            $montoTotalMoneda += $subtotalMoneda;
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
                'moneda_original'     => 'PEN',
                'tipo_cambio_aplicado' => $tipoCambioAplicado,
                'precio_unitario_moneda' => $precio,
                'subtotal_moneda'     => $subtotal,
                'inventario_producto_stock' => true,
                'stock_anterior'      => $stockMovimiento['stock_anterior'],
                'stock_posterior'     => $stockMovimiento['stock_posterior'],
                'js_consumo'          => [],
            ];
        }

        $totalPagadoMoneda = round(array_sum(array_column($pagos, 'monto')), 2);
        $montoTotalCobro = $moneda === 'PEN' ? round($montoTotal, 2) : round($montoTotal / $tipoCambioAplicado, 2);
        if (abs($totalPagadoMoneda - $montoTotalCobro) > 0.009) {
            throw new RuntimeException('Los pagos deben sumar exactamente el total de cobro en ' . $moneda . '.');
        }
        $totalPagosPen = 0.0;
        foreach ($pagos as $indicePago => &$pago) {
            $pago['monto_moneda'] = $pago['monto'];
            $pagoPen = round($pago['monto_moneda'] * $tipoCambioAplicado, 2);
            if ($indicePago === array_key_last($pagos)) {
                $pagoPen = round($montoTotal - $totalPagosPen, 2);
            }
            $pago['monto'] = $pagoPen;
            $totalPagosPen += $pagoPen;
        }
        unset($pago);
        foreach ($pagos as $pago) {
            if ($pago['cuenta_receptora_id'] === null) continue;
            $cuentaValida = executeQuery($conectar, "
                SELECT id FROM cuenta_receptora
                WHERE id = :id AND activo IS TRUE
            ", ['id' => $pago['cuenta_receptora_id']]);
            if (empty($cuentaValida)) {
                throw new RuntimeException('Una de las cuentas receptoras ya no está activa.');
            }
        }

        $movimiento = obtenerMovimientoSesion('crear', [
            'vendedor_usuario_id' => $vendedorId,
            'pagos' => $pagos,
            'moneda' => $moneda,
            'tipo_cambio' => $snapshotTipoCambio,
            'monto_total_moneda' => $montoTotalCobro,
        ]);
        $movimiento['venta_moneda'] = [
            'moneda' => $moneda,
            'moneda_precio' => 'PEN',
            'moneda_cobro' => $moneda,
            'monto_total_moneda' => $montoTotalCobro,
            'monto_total_cobro' => $montoTotalCobro,
            'monto_total_pen' => round($montoTotal, 2),
            'tipo_cambio' => $snapshotTipoCambio,
            'pagos' => array_map(static function (array $pago) use ($moneda): array {
                return [
                    'medio_pago' => $pago['medio_pago'],
                    'cuenta_receptora_id' => $pago['cuenta_receptora_id'],
                    'monto' => $pago['monto_moneda'],
                    'moneda' => $moneda,
                    'referencia' => $pago['referencia'],
                ];
            }, $pagos),
        ];

        $stmtInsert = $conectar->prepare("
            INSERT INTO venta (codigo, cliente_ruc, vendedor_usuario_id, fecha_venta, js_items, monto_total, estado, created_at, js_session, js_historial)
            VALUES (:codigo, :cliente_ruc, :vendedor_id, NOW(), :js_items::jsonb, :monto_total, 'completada', NOW(), :js_session, :js_historial::jsonb)
            RETURNING id
        ");
        $stmtInsert->execute([
            'codigo'       => 'V-TEMP',
            'cliente_ruc'  => $cliente_ruc,
            'vendedor_id'  => $vendedorId,
            'js_items'     => json_encode($itemsFinal, JSON_UNESCAPED_UNICODE),
            'monto_total'  => round($montoTotal, 2),
            'js_session'   => json_encode($movimiento, JSON_UNESCAPED_UNICODE),
            'js_historial' => json_encode([$movimiento], JSON_UNESCAPED_UNICODE),
        ]);
        $ventaId = (int)$stmtInsert->fetchColumn();

        $codigo = 'V-' . str_pad((string)$ventaId, 6, '0', STR_PAD_LEFT);
        $stmtCodigo = $conectar->prepare("UPDATE venta SET codigo = :codigo WHERE id = :id");
        $stmtCodigo->execute(['codigo' => $codigo, 'id' => $ventaId]);

        $stmtPago = $conectar->prepare("
            INSERT INTO venta_pago
                (venta_id, medio_pago, cuenta_receptora_id, monto, referencia, registrado_por_usuario_id)
            VALUES
                (:venta_id, :medio_pago, :cuenta_id, :monto, :referencia, :registrado_por)
        ");
        foreach ($pagos as $pago) {
            $stmtPago->execute([
                'venta_id' => $ventaId,
                'medio_pago' => $pago['medio_pago'],
                'cuenta_id' => $pago['cuenta_receptora_id'],
                'monto' => $pago['monto'],
                'referencia' => $pago['referencia'],
                'registrado_por' => (int)($_SESSION['usuario_id'] ?? 0) ?: null,
            ]);
        }

        foreach ($itemsFinal as $item) {
            registrarMovimientoVentaProducto(
                $conectar,
                (int)$item['producto_id'],
                $ventaId,
                'SALIDA',
                (float)$item['cantidad'],
                (float)$item['stock_anterior'],
                (float)$item['stock_posterior']
            );
        }

        // Las ventas nuevas descuentan el saldo global del producto. Los
        // registros FIFO históricos de empaquetado se conservan para auditar
        // ventas antiguas y no se consumen dos veces.
        foreach ($itemsFinal as $item) {
            foreach ($item['js_consumo'] as $c) {
                agregarRegistroJsVentaEmpaquetado($conectar, $c['empaquetado_id'], [
                    'venta_id'           => $ventaId,
                    'venta_codigo'       => $codigo,
                    'producto_id'        => $item['producto_id'],
                    'producto_codigo'    => $item['producto_codigo'],
                    'color_id'           => $item['color_id'],
                    'color'              => $item['color'],
                    'cantidad_consumida' => $c['cantidad_consumida'], // en la unidad propia del empaquetado
                    'fecha'              => date('Y-m-d H:i:s'),
                    'anulado'            => false,
                ]);
            }
        }

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
            if (!empty($item['inventario_producto_stock'])) {
                $productoId = (int)($item['producto_id'] ?? 0);
                $cantidad = round((float)($item['cantidad'] ?? 0), 2);
                if ($productoId <= 0 || $cantidad <= 0) continue;

                $stmtStock = $conectar->prepare('SELECT stock FROM producto WHERE id = :id FOR UPDATE');
                $stmtStock->execute(['id' => $productoId]);
                $stockActual = $stmtStock->fetchColumn();
                if ($stockActual === false) throw new RuntimeException('No se encontró el producto para devolver el stock de la venta.');
                $anterior = round((float)($stockActual ?? 0), 2);
                $posterior = round($anterior + $cantidad, 2);
                $stmtActualizarStock = $conectar->prepare('UPDATE producto SET stock = :stock, updated_at = NOW() WHERE id = :id');
                $stmtActualizarStock->execute(['stock' => $posterior, 'id' => $productoId]);
                registrarMovimientoVentaProducto($conectar, $productoId, $id, 'ENTRADA', $cantidad, $anterior, $posterior);
                continue;
            }

            $consumo = $item['js_consumo'] ?? [];
            if (!empty($consumo)) {
                restaurarStockVenta($conectar, $consumo);
                // NUEVO: marcar (no borrar) los registros de js_venta de
                // cada empaquetado tocado por esta venta.
                foreach ($consumo as $c) {
                    marcarAnuladoJsVentaEmpaquetado($conectar, (int)$c['empaquetado_id'], $id);
                }
            }
        }

        $movimiento = obtenerMovimientoSesion('anular', [[
            'campo' => 'Estado', 'valor_antes' => 'completada', 'valor_despues' => 'anulada',
        ]]);
        $sesionVentaAnterior = json_decode((string)($venta['js_session'] ?? '{}'), true) ?: [];
        if (isset($sesionVentaAnterior['venta_moneda'])) {
            $movimiento['venta_moneda'] = $sesionVentaAnterior['venta_moneda'];
        }

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
