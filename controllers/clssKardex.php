<?php

/**
 * controllers/clssKardex.php
 * Controlador del módulo de Kardex (solo lectura / reporte)
 *
 * Este controlador NO implementa lógica de movimientos propia: todo el
 * cálculo de entradas, salidas y saldo acumulado vive en la vista SQL
 * `movimientos` (COMPRA, PRODUCCION, EMPAQUETADO, VENTA unificadas para
 * MATERIAL y PRODUCTO, con saldo calculado vía window function SUM() OVER()).
 * Aquí solo se consulta esa vista con filtros — no se toca ni se duplica
 * esa lógica.
 *
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function controladorKardex($accion)
{
    switch ($accion) {
        case 'LISTARCATALOGOITEMS':
            listarCatalogoItems();
            break;
        case 'LISTARSTOCKGENERAL':
            listarStockGeneral();
            break;
        case 'OBTENERKARDEX':
            obtenerKardex();
            break;
        case 'LISTARKARDEXGENERAL':
            listarKardexGeneral();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}



function listarStockGeneral()
{
    $conectar = conectar_oll_BD();
    $tipoItem = trim($_POST['tipo_item'] ?? ''); // '', MATERIAL, PRODUCTO
    $texto    = trim($_POST['texto'] ?? '');

    $where  = [];
    $params = [];
    if (in_array($tipoItem, ['MATERIAL', 'PRODUCTO'], true)) {
        $where[] = "tipo_item = :tipo_item";
        $params['tipo_item'] = $tipoItem;
    }
    if ($texto !== '') {
        $where[] = "LOWER(item_nombre) LIKE LOWER(:texto)";
        $params['texto'] = "%$texto%";
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "
        SELECT DISTINCT ON (tipo_item, item_id)
            tipo_item, item_id, item_nombre, unidad_medida_id, saldo, fecha
        FROM movimientos
        $whereSql
        ORDER BY tipo_item, item_id, fecha DESC, movimiento_id DESC
    ";

    $stock = executeQuery($conectar, $sql, $params);

    // Resolver nombres de unidad (una sola consulta extra)
    $unidadIds = [];
    foreach ($stock as $s) {
        if (!empty($s['unidad_medida_id'])) $unidadIds[$s['unidad_medida_id']] = true;
    }
    $nombresUnidad = [];
    if (!empty($unidadIds)) {
        $ids = array_keys($unidadIds);
        $placeholders = [];
        $paramsUnidad = [];
        foreach ($ids as $i => $uid) {
            $key = "u$i";
            $placeholders[] = ":$key";
            $paramsUnidad[$key] = $uid;
        }
        $resultUnidad = executeQuery($conectar,
            "SELECT id, nombre FROM unidad_medida WHERE id IN (" . implode(',', $placeholders) . ")",
            $paramsUnidad
        );
        foreach ($resultUnidad as $u) $nombresUnidad[$u['id']] = $u['nombre'];
    }

    foreach ($stock as &$s) {
        // FIX: no usar null como clave de array (deprecated en PHP 8.1+)
        $uid = $s['unidad_medida_id'];
        $s['unidad_medida_nombre'] = ($uid !== null && isset($nombresUnidad[$uid]))
            ? $nombresUnidad[$uid]
            : null;
    }
    unset($s);

    usort($stock, fn($a, $b) => strcasecmp($a['item_nombre'], $b['item_nombre']));

    responder(true, 'OK', ['stock' => $stock]);
}
// =============================================================================
// CATÁLOGO DE ÍTEMS (materiales + productos, para el buscador del kardex)
// =============================================================================

function listarCatalogoItems()
{
    $conectar = conectar_oll_BD();
    $texto = trim($_POST['texto'] ?? '');

    $whereMat  = "m.deleted_at IS NULL";
    $paramsMat = [];
    if ($texto !== '') {
        $whereMat .= " AND LOWER(m.nombre) LIKE LOWER(:texto)";
        $paramsMat['texto'] = "%$texto%";
    }

    $materiales = executeQuery($conectar, "
        SELECT m.id AS item_id, m.nombre AS item_nombre, 'MATERIAL'::varchar AS tipo_item
        FROM material m
        WHERE $whereMat
        ORDER BY m.nombre
    ", $paramsMat);

    $whereProd  = "p.activo = true";
    $paramsProd = [];
    if ($texto !== '') {
        $whereProd .= " AND LOWER(p.descripcion) LIKE LOWER(:texto)";
        $paramsProd['texto'] = "%$texto%";
    }

    $productos = executeQuery($conectar, "
        SELECT p.id AS item_id, p.descripcion AS item_nombre, 'PRODUCTO'::varchar AS tipo_item
        FROM producto p
        WHERE $whereProd
        ORDER BY p.descripcion
    ", $paramsProd);

    responder(true, 'OK', ['items' => array_merge($materiales, $productos)]);
}


// =============================================================================
// KARDEX (consulta directa sobre la vista `movimientos`, sin lógica propia)
// =============================================================================

function obtenerKardex()
{
    $conectar = conectar_oll_BD();

    $tipoItem      = trim($_POST['tipo_item'] ?? '');
    $itemId        = intval($_POST['item_id'] ?? 0);
    $fechaInicio   = trim($_POST['fecha_inicio'] ?? '');
    $fechaFin      = trim($_POST['fecha_fin'] ?? '');
    $tipoMovimiento = trim($_POST['tipo_movimiento'] ?? ''); // NUEVO

    if (!in_array($tipoItem, ['MATERIAL', 'PRODUCTO'], true)) {
        responder(false, 'Tipo de ítem inválido.');
    }
    if (!$itemId) {
        responder(false, 'Debes seleccionar un ítem.');
    }

    $where  = ["tipo_item = :tipo_item", "item_id = :item_id"];
    $params = ['tipo_item' => $tipoItem, 'item_id' => $itemId];

    if ($fechaInicio !== '') {
        $where[] = "fecha >= :fecha_inicio";
        $params['fecha_inicio'] = $fechaInicio;
    }
    if ($fechaFin !== '') {
        $where[] = "fecha <= :fecha_fin";
        $params['fecha_fin'] = $fechaFin . ' 23:59:59';
    }
    if ($tipoMovimiento !== '' && in_array($tipoMovimiento, ['COMPRA','PRODUCCION','EMPAQUETADO','VENTA'], true)) {
        $where[] = "tipo_movimiento = :tipo_movimiento";
        $params['tipo_movimiento'] = $tipoMovimiento;
    }

    $sql = "
        SELECT *
        FROM movimientos
        WHERE " . implode(' AND ', $where) . "
        ORDER BY fecha, movimiento_id
    ";

    $movimientos = executeQuery($conectar, $sql, $params);

    // ── Rellenar unidad_medida_id faltante (sin tocar la vista) ─────────────
    // Las filas de VENTA no traen unidad_medida_id. Se arrastra la última
    // unidad conocida del mismo ítem dentro del propio resultado.
    $ultimaUnidad = null;
    $unidadIds    = [];
    foreach ($movimientos as &$m) {
        if (!empty($m['unidad_medida_id'])) {
            $ultimaUnidad = $m['unidad_medida_id'];
        } else {
            $m['unidad_medida_id'] = $ultimaUnidad;
        }
        if (!empty($m['unidad_medida_id'])) {
            $unidadIds[$m['unidad_medida_id']] = true;
        }
    }
    unset($m);

    // Resolver nombres de unidad en una sola consulta extra (no por fila)
    $nombresUnidad = [];
    if (!empty($unidadIds)) {
        $ids          = array_keys($unidadIds);
        $placeholders = [];
        $paramsUnidad = [];
        foreach ($ids as $i => $uid) {
            $key = "u$i";
            $placeholders[] = ":$key";
            $paramsUnidad[$key] = $uid;
        }
        // Ajusta el nombre de columna si tu tabla unidad_medida no usa 'nombre'.
        $resultUnidad = executeQuery(
            $conectar,
            "SELECT id, nombre FROM unidad_medida WHERE id IN (" . implode(',', $placeholders) . ")",
            $paramsUnidad
        );
        foreach ($resultUnidad as $u) {
            $nombresUnidad[$u['id']] = $u['nombre'];
        }
    }

    $saldoActual   = 0;
    $totalEntradas = 0;
    $totalSalidas  = 0;

    foreach ($movimientos as &$m) {
        $m['unidad_medida_nombre'] = $nombresUnidad[$m['unidad_medida_id']] ?? null;
        $saldoActual    = $m['saldo']; // la última fila deja el saldo vigente
        $totalEntradas += (float) $m['entrada'];
        $totalSalidas  += (float) $m['salida'];
    }
    unset($m);

    responder(true, 'OK', [
        'movimientos' => $movimientos,
        'resumen' => [
            'saldo_actual'   => $saldoActual,
            'total_entradas' => $totalEntradas,
            'total_salidas'  => $totalSalidas,
        ],
    ]);
}

// =============================================================================
// KARDEX GENERAL (todos los movimientos de todos los ítems, filtrado por
// mes/año — por defecto el mes y año en curso)
// =============================================================================

function listarKardexGeneral()
{
    $conectar = conectar_oll_BD();

    $mes  = intval($_POST['mes'] ?? date('n'));   // mes actual por defecto
    $anio = intval($_POST['anio'] ?? date('Y'));  // año actual por defecto
    $tipoItem       = trim($_POST['tipo_item'] ?? '');       // opcional: MATERIAL, PRODUCTO
    $tipoMovimiento = trim($_POST['tipo_movimiento'] ?? ''); // opcional: COMPRA, PRODUCCION, EMPAQUETADO, VENTA
    $texto          = trim($_POST['texto'] ?? '');           // opcional: nombre de material/producto

    if ($mes < 1 || $mes > 12) {
        responder(false, 'Mes inválido.');
    }

    $where  = [
        "EXTRACT(MONTH FROM fecha) = :mes",
        "EXTRACT(YEAR FROM fecha) = :anio",
    ];
    $params = ['mes' => $mes, 'anio' => $anio];

    if (in_array($tipoItem, ['MATERIAL', 'PRODUCTO'], true)) {
        $where[] = "tipo_item = :tipo_item";
        $params['tipo_item'] = $tipoItem;
    }
    if (in_array($tipoMovimiento, ['COMPRA', 'PRODUCCION', 'EMPAQUETADO', 'VENTA'], true)) {
        $where[] = "tipo_movimiento = :tipo_movimiento";
        $params['tipo_movimiento'] = $tipoMovimiento;
    }
    if ($texto !== '') {
        $where[] = "LOWER(item_nombre) LIKE LOWER(:texto)";
        $params['texto'] = "%$texto%";
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $sql = "
        SELECT *
        FROM movimientos
        $whereSql
        ORDER BY fecha, movimiento_id
    ";

    $movimientos = executeQuery($conectar, $sql, $params);

    // ── Rellenar unidad_medida_id faltante, arrastrando la última unidad ────
    // conocida POR ÍTEM (aquí hay muchos ítems mezclados, a diferencia de
    // obtenerKardex donde todo el resultado es de un solo ítem).
    $ultimaUnidadPorItem = [];
    $unidadIds           = [];
    foreach ($movimientos as &$m) {
        $clave = $m['tipo_item'] . ':' . $m['item_id'];
        if (!empty($m['unidad_medida_id'])) {
            $ultimaUnidadPorItem[$clave] = $m['unidad_medida_id'];
        } else {
            $m['unidad_medida_id'] = $ultimaUnidadPorItem[$clave] ?? null;
        }
        if (!empty($m['unidad_medida_id'])) {
            $unidadIds[$m['unidad_medida_id']] = true;
        }
    }
    unset($m);

    $nombresUnidad = [];
    if (!empty($unidadIds)) {
        $ids          = array_keys($unidadIds);
        $placeholders = [];
        $paramsUnidad = [];
        foreach ($ids as $i => $uid) {
            $key = "u$i";
            $placeholders[] = ":$key";
            $paramsUnidad[$key] = $uid;
        }
        $resultUnidad = executeQuery(
            $conectar,
            "SELECT id, nombre FROM unidad_medida WHERE id IN (" . implode(',', $placeholders) . ")",
            $paramsUnidad
        );
        foreach ($resultUnidad as $u) {
            $nombresUnidad[$u['id']] = $u['nombre'];
        }
    }

    foreach ($movimientos as &$m) {
        $uid = $m['unidad_medida_id'];
        $m['unidad_medida_nombre'] = ($uid !== null && isset($nombresUnidad[$uid]))
            ? $nombresUnidad[$uid]
            : null;
    }
    unset($m);

    // Más reciente primero, para revisar el mes de arriba hacia abajo
    usort($movimientos, function ($a, $b) {
        return strcmp($b['fecha'], $a['fecha']) ?: ($b['movimiento_id'] <=> $a['movimiento_id']);
    });

    responder(true, 'OK', [
        'movimientos' => $movimientos,
        'periodo'     => ['mes' => $mes, 'anio' => $anio],
    ]);
}

// =============================================================================
// HELPER
// =============================================================================

if (!function_exists('responder')) {
    function responder(bool $ok, string $msg, array $extra = []): void
    {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
        exit;
    }
}

// =============================================================================
// DISPATCH
// =============================================================================
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    controladorKardex($_POST['accion'] ?? '');
}