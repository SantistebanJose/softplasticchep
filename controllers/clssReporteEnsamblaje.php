<?php

/**
 * controllers/clssReporteEnsamblaje.php
 * Reporte del módulo de Ensamblaje.
 *
 * OPERARIO NORMALIZADO:
 * ensamblaje tiene dos fuentes de operario que coexisten:
 *   - e.operario_ortorgado (columna escalar, un solo operario)
 *   - e.js_operarios (jsonb array, multi-operario: [{"operario_id":21,
 *     "nombre_completo":"...","cargo":"..."}])
 * Regla acordada: si js_operarios trae elementos, esa es la fuente de
 * verdad; si viene vacío/null, se usa operario_ortorgado (resuelto contra
 * las tablas operario/cargo). Ver joinOperarioEnsamblajeSql().
 *
 * LIMITACIÓN CONOCIDA: un ensamblaje no reparte cantidad_peso_kg por
 * operario (a diferencia de produccion.js_operarios, que sí trae
 * cantidad_producida por persona). Cuando un ensamblaje tiene varios
 * operarios en js_operarios, el kg completo del registro se le atribuye
 * a CADA operario en los rankings "top operarios" — es una atribución de
 * autoría compartida, no un reparto exacto. El total general del
 * dashboard (resumen_general) NO usa este join, así que no se infla.
 *
 * SUPUESTOS DE ESQUEMA (ajustar nombres si no calzan con tu BD real):
 *   - categoria_material(id, nombre)
 *   - unidad_medida(id, nombre)
 *   - e.proveniente es texto libre (ej. 'PRODUCCION', 'DERIVADO')
 *
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

ob_start();

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    try {
        controladorReporteEnsamblaje($_POST["accion"]);
    } catch (PDOException $e) {
        error_log("Error de base de datos en clssReporteEnsamblaje.php: " . $e->getMessage());
        responder(false, 'Error de base de datos: ' . $e->getMessage());
    } catch (Throwable $e) {
        error_log("Error inesperado en clssReporteEnsamblaje.php: " . $e->getMessage());
        responder(false, 'Error inesperado en el servidor: ' . $e->getMessage());
    }
}

function controladorReporteEnsamblaje($accion)
{
    switch ($accion) {
        case 'BUSCAROPERARIOSREPORTEENSAMBLAJE':
            buscarOperariosReporteEnsamblaje();
            break;
        case 'BUSCARPRODUCTOSREPORTEENSAMBLAJE':
            buscarProductosReporteEnsamblaje();
            break;
        case 'BUSCARCATEGORIASMATERIALREPORTE':
            buscarCategoriasMaterialReporte();
            break;
        case 'REPORTEENSAMBLAJEDASHBOARD':
            reporteEnsamblajeDashboard();
            break;
        case 'REPORTEENSAMBLAJEOPERARIODETALLE':
            reporteEnsamblajeOperarioDetalle();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// LISTADOS AUXILIARES (buscadores / filtros)
// =============================================================================

function buscarOperariosReporteEnsamblaje()
{
    $conectar = conectar_oll_BD();
    $sql = "
        SELECT o.id, o.nombre_completo, c.nombre AS cargo
        FROM operario o
        LEFT JOIN cargo c ON c.id = o.cargo_id
        WHERE o.activo = true
          AND EXISTS (
              SELECT 1 FROM jsonb_array_elements(COALESCE(o.js_etapas_relacionadas, '[]'::jsonb)) AS et
              WHERE et->>'nombre' ILIKE '%ENSAMBL%'
          )
        ORDER BY o.nombre_completo
    ";
    $result = executeQuery($conectar, $sql, []);
    responder(true, 'OK', ['operarios' => $result]);
}

function buscarProductosReporteEnsamblaje()
{
    $conectar = conectar_oll_BD();
    $sql = "
        SELECT DISTINCT p.id, p.codigo, p.descripcion
        FROM ensamblaje e
        JOIN producto p ON p.id = e.producto_id
        WHERE e.deleted_at IS NULL
        ORDER BY p.descripcion
    ";
    $result = executeQuery($conectar, $sql, []);
    responder(true, 'OK', ['productos' => $result]);
}

// Ajusta el nombre de tabla/columna si categoria_material se llama distinto.
function buscarCategoriasMaterialReporte()
{
    $conectar = conectar_oll_BD();
    $sql = "SELECT id, nombre FROM categoria_material ORDER BY nombre";
    $result = executeQuery($conectar, $sql, []);
    responder(true, 'OK', ['categorias' => $result]);
}

// =============================================================================
// DASHBOARD GENERAL (totales + rankings, con filtros de periodo/categoría/proveniente)
// =============================================================================

function reporteEnsamblajeDashboard()
{
    $conectar = conectar_oll_BD();

    $filtros = [
        'modo'                 => trim($_POST['modo'] ?? 'mes'),
        'fecha'                => trim($_POST['fecha'] ?? date('Y-m-d')),
        'fecha_desde'          => trim($_POST['fecha_desde'] ?? ''),
        'fecha_hasta'          => trim($_POST['fecha_hasta'] ?? ''),
        'categoria_material_id'=> intval($_POST['categoria_material_id'] ?? 0),
        'proveniente'          => trim($_POST['proveniente'] ?? ''),
    ];

    [$fechaDesde, $fechaHasta, $etiquetaPeriodo] = calcularRangoPeriodoEnsamblaje(
        $filtros['modo'], $filtros['fecha'], $filtros['fecha_desde'], $filtros['fecha_hasta']
    );
    if (!$fechaDesde || !$fechaHasta) {
        responder(false, 'Debes indicar un rango de fechas válido.');
    }

    $condiciones = [
        "e.deleted_at IS NULL",
        "e.inicio::date BETWEEN :fecha_desde AND :fecha_hasta",
    ];
    $params = ['fecha_desde' => $fechaDesde, 'fecha_hasta' => $fechaHasta];

    if ($filtros['categoria_material_id'] > 0) {
        $condiciones[] = "e.categoria_material_id = :categoria_material_id";
        $params['categoria_material_id'] = $filtros['categoria_material_id'];
    }
    if ($filtros['proveniente'] !== '') {
        $condiciones[] = "e.proveniente = :proveniente";
        $params['proveniente'] = $filtros['proveniente'];
    }
    $condicionBase = implode(' AND ', $condiciones);
    $joinOperario = joinOperarioEnsamblajeSql();
    $joinUnidad = unidadEnsamblajeJoinSql();
    $unidadSql = unidadEnsamblajeSql();

    // ── Conteo general (unit-agnostic: son conteos de registros, no sumas de cantidad) ──
    $sqlConteo = "
        SELECT
            COUNT(e.id) AS total_ensamblajes,
            COUNT(e.id) FILTER (WHERE e.enviado_empaquetado IS NOT TRUE) AS pendientes,
            COUNT(e.id) FILTER (WHERE e.enviado_empaquetado IS TRUE) AS enviados_empaquetado
        FROM ensamblaje e
        WHERE $condicionBase
    ";
    $conteoFilas = executeQuery($conectar, $sqlConteo, $params);
    $conteo = $conteoFilas[0] ?? ['total_ensamblajes' => 0, 'pendientes' => 0, 'enviados_empaquetado' => 0];

    // ── Cantidad total, SEPARADA POR UNIDAD REAL (e.unidad_salida_id) ──
    // cantidad_peso_kg no siempre está en kg pese al nombre de la columna;
    // la unidad real de cada registro está en unidad_salida_id -> unidad_medida.
    $sqlResumenPorUnidad = "
        SELECT
            $unidadSql AS unidad,
            COUNT(e.id) AS ensamblajes,
            COALESCE(SUM(e.cantidad_peso_kg), 0) AS cantidad_total
        FROM ensamblaje e
        $joinUnidad
        WHERE $condicionBase
        GROUP BY 1
        ORDER BY cantidad_total DESC
    ";
    $resumenPorUnidad = executeQuery($conectar, $sqlResumenPorUnidad, $params);

    $resumen = array_merge($conteo, ['por_unidad' => $resumenPorUnidad]);

    // ── Top operarios, separado por unidad (ver nota de atribución compartida arriba) ──
    $sqlTopOperarios = "
        SELECT
            (op->>'operario_id')::bigint AS operario_id,
            op->>'nombre_completo' AS operario,
            op->>'cargo' AS cargo,
            $unidadSql AS unidad,
            COUNT(DISTINCT e.id) AS ensamblajes,
            COALESCE(SUM(e.cantidad_peso_kg), 0) AS cantidad_total
        FROM ensamblaje e
        $joinOperario
        $joinUnidad
        WHERE $condicionBase AND (op->>'operario_id') IS NOT NULL
        GROUP BY 1, 2, 3, 4
        ORDER BY cantidad_total DESC
        LIMIT 15
    ";
    $topOperarios = executeQuery($conectar, $sqlTopOperarios, $params);

    // ── Top productos ensamblados, separado por unidad ──
    $sqlTopProductos = "
        SELECT
            p.id AS producto_id,
            p.codigo,
            p.descripcion,
            $unidadSql AS unidad,
            COUNT(e.id) AS ensamblajes,
            COALESCE(SUM(e.cantidad_peso_kg), 0) AS cantidad_total
        FROM ensamblaje e
        $joinUnidad
        LEFT JOIN producto p ON p.id = e.producto_id
        WHERE $condicionBase
        GROUP BY p.id, p.codigo, p.descripcion, 4
        ORDER BY cantidad_total DESC
        LIMIT 15
    ";
    $topProductos = executeQuery($conectar, $sqlTopProductos, $params);

    // ── Distribución por categoría de material, separada por unidad ──
    $sqlPorCategoria = "
        SELECT
            COALESCE(cm.nombre, 'Sin categoría') AS categoria,
            $unidadSql AS unidad,
            COUNT(e.id) AS ensamblajes,
            COALESCE(SUM(e.cantidad_peso_kg), 0) AS cantidad_total
        FROM ensamblaje e
        $joinUnidad
        LEFT JOIN categoria_material cm ON cm.id = e.categoria_material_id
        WHERE $condicionBase
        GROUP BY cm.nombre, 2
        ORDER BY unidad, cantidad_total DESC
    ";
    $porCategoria = executeQuery($conectar, $sqlPorCategoria, $params);

    // ── Distribución por origen (proveniente), separada por unidad ──
    $sqlPorProveniente = "
        SELECT
            COALESCE(NULLIF(e.proveniente, ''), 'Sin especificar') AS proveniente,
            $unidadSql AS unidad,
            COUNT(e.id) AS ensamblajes,
            COALESCE(SUM(e.cantidad_peso_kg), 0) AS cantidad_total
        FROM ensamblaje e
        $joinUnidad
        WHERE $condicionBase
        GROUP BY e.proveniente, 2
        ORDER BY unidad, cantidad_total DESC
    ";
    $porProveniente = executeQuery($conectar, $sqlPorProveniente, $params);

    responder(true, 'OK', [
        'periodo'         => ['desde' => $fechaDesde, 'hasta' => $fechaHasta, 'etiqueta' => $etiquetaPeriodo],
        'resumen'         => $resumen,
        'top_operarios'   => $topOperarios,
        'top_productos'   => $topProductos,
        'por_categoria'   => $porCategoria,
        'por_proveniente' => $porProveniente,
    ]);
}

// =============================================================================
// DETALLE POR OPERARIO (buscar operario -> ver todo lo suyo en el periodo)
// =============================================================================

function reporteEnsamblajeOperarioDetalle()
{
    $conectar = conectar_oll_BD();

    $operarioId = intval($_POST['operario_id'] ?? 0);
    if (!$operarioId) {
        responder(false, 'Debes indicar un operario.');
    }

    $operario = executeQuery(
        $conectar,
        "SELECT o.id, o.nombre_completo, c.nombre AS cargo
         FROM operario o LEFT JOIN cargo c ON c.id = o.cargo_id
         WHERE o.id = :id",
        ['id' => $operarioId]
    );
    if (empty($operario)) {
        responder(false, 'Operario no encontrado.');
    }

    $modo        = trim($_POST['modo'] ?? 'mes');
    $fechaRef    = trim($_POST['fecha'] ?? date('Y-m-d'));
    $fechaDesdeIn= trim($_POST['fecha_desde'] ?? '');
    $fechaHastaIn= trim($_POST['fecha_hasta'] ?? '');

    [$fechaDesde, $fechaHasta, $etiquetaPeriodo] = calcularRangoPeriodoEnsamblaje(
        $modo, $fechaRef, $fechaDesdeIn, $fechaHastaIn
    );
    if (!$fechaDesde || !$fechaHasta) {
        responder(false, 'Debes indicar un rango de fechas válido.');
    }

    $joinOperario = joinOperarioEnsamblajeSql();
    $joinUnidad = unidadEnsamblajeJoinSql();
    $unidadSql = unidadEnsamblajeSql();
    $condicionBase = "
        e.deleted_at IS NULL
        AND (op->>'operario_id')::bigint = :operario_id
        AND e.inicio::date BETWEEN :fecha_desde AND :fecha_hasta
    ";
    $params = [
        'operario_id' => $operarioId,
        'fecha_desde' => $fechaDesde,
        'fecha_hasta' => $fechaHasta,
    ];

    // ── Conteo del operario en el periodo (unit-agnostic) ──
    $sqlConteo = "
        SELECT
            COUNT(e.id) AS total_ensamblajes,
            COUNT(DISTINCT e.producto_id) AS productos_distintos,
            COUNT(e.id) FILTER (WHERE e.enviado_empaquetado IS NOT TRUE) AS pendientes,
            COUNT(e.id) FILTER (WHERE e.enviado_empaquetado IS TRUE) AS enviados_empaquetado
        FROM ensamblaje e
        $joinOperario
        WHERE $condicionBase
    ";
    $conteoFilas = executeQuery($conectar, $sqlConteo, $params);
    $conteo = $conteoFilas[0] ?? [
        'total_ensamblajes' => 0, 'productos_distintos' => 0, 'pendientes' => 0, 'enviados_empaquetado' => 0,
    ];

    // ── Cantidad ensamblada por ESTE operario, separada por unidad real ──
    $sqlResumenPorUnidad = "
        SELECT
            $unidadSql AS unidad,
            COUNT(e.id) AS ensamblajes,
            COALESCE(SUM(e.cantidad_peso_kg), 0) AS cantidad_total
        FROM ensamblaje e
        $joinOperario
        $joinUnidad
        WHERE $condicionBase
        GROUP BY 1
        ORDER BY cantidad_total DESC
    ";
    $resumenPorUnidad = executeQuery($conectar, $sqlResumenPorUnidad, $params);
    $resumen = array_merge($conteo, ['por_unidad' => $resumenPorUnidad]);

    // ── Top productos trabajados por este operario, separado por unidad ──
    $sqlTopProductos = "
        SELECT
            p.id AS producto_id, p.codigo, p.descripcion,
            $unidadSql AS unidad,
            COUNT(e.id) AS ensamblajes,
            COALESCE(SUM(e.cantidad_peso_kg), 0) AS cantidad_total
        FROM ensamblaje e
        $joinOperario
        $joinUnidad
        LEFT JOIN producto p ON p.id = e.producto_id
        WHERE $condicionBase
        GROUP BY p.id, p.codigo, p.descripcion, 4
        ORDER BY cantidad_total DESC
        LIMIT 8
    ";
    $topProductos = executeQuery($conectar, $sqlTopProductos, $params);

    // ── Distribución por categoría de material, separada por unidad ──
    $sqlPorCategoria = "
        SELECT
            COALESCE(cm.nombre, 'Sin categoría') AS categoria,
            $unidadSql AS unidad,
            COUNT(e.id) AS ensamblajes,
            COALESCE(SUM(e.cantidad_peso_kg), 0) AS cantidad_total
        FROM ensamblaje e
        $joinOperario
        $joinUnidad
        LEFT JOIN categoria_material cm ON cm.id = e.categoria_material_id
        WHERE $condicionBase
        GROUP BY cm.nombre, 2
        ORDER BY unidad, cantidad_total DESC
    ";
    $porCategoria = executeQuery($conectar, $sqlPorCategoria, $params);

    // ── Detalle completo (registro a registro) ──
    $sqlDetalle = "
        SELECT
            e.id,
            e.inicio,
            e.fin,
            p.codigo AS producto_codigo,
            p.descripcion AS producto,
            cm.nombre AS categoria_material,
            e.cantidad_peso_kg,
            um.nombre AS unidad_salida,
            e.proveniente,
            e.enviado_empaquetado,
            e.fecha_envio_empaquetado
        FROM ensamblaje e
        $joinOperario
        LEFT JOIN producto p ON p.id = e.producto_id
        LEFT JOIN categoria_material cm ON cm.id = e.categoria_material_id
        LEFT JOIN unidad_medida um ON um.id = e.unidad_salida_id
        WHERE $condicionBase
        ORDER BY e.inicio DESC
    ";
    $detalle = executeQuery($conectar, $sqlDetalle, $params);

    responder(true, 'OK', [
        'operario'      => $operario[0],
        'periodo'       => ['desde' => $fechaDesde, 'hasta' => $fechaHasta, 'etiqueta' => $etiquetaPeriodo],
        'resumen'       => $resumen,
        'top_productos' => $topProductos,
        'por_categoria' => $porCategoria,
        'detalle'       => $detalle,
    ]);
}

// =============================================================================
// HELPERS
// =============================================================================

/**
 * Normaliza el/los operario(s) de un ensamblaje:
 *  - si e.js_operarios trae elementos, se usan tal cual (uno por elemento)
 *  - si viene vacío/null, se cae a e.operario_ortorgado resuelto contra
 *    operario/cargo, como un array de un solo elemento
 * Requiere que la tabla principal esté aliasada como "e".
 */
function joinOperarioEnsamblajeSql(): string
{
    return "
        LEFT JOIN LATERAL (
            SELECT o.id AS operario_id, o.nombre_completo, c.nombre AS cargo
            FROM operario o
            LEFT JOIN cargo c ON c.id = o.cargo_id
            WHERE o.id = e.operario_ortorgado
        ) AS op_fallback ON true
        CROSS JOIN LATERAL jsonb_array_elements(
            CASE
                WHEN jsonb_typeof(e.js_operarios) = 'array' AND jsonb_array_length(e.js_operarios) > 0
                    THEN e.js_operarios
                ELSE jsonb_build_array(jsonb_build_object(
                        'operario_id', op_fallback.operario_id,
                        'nombre_completo', op_fallback.nombre_completo,
                        'cargo', op_fallback.cargo
                     ))
            END
        ) AS op
    ";
}

/**
 * JOIN para resolver la unidad real de salida de un ensamblaje. e.cantidad_peso_kg
 * NO está siempre en kg pese al nombre de la columna; la unidad real vive en
 * e.unidad_salida_id -> unidad_medida.nombre. Requiere alias "e" en la query.
 */
function unidadEnsamblajeJoinSql(): string
{
    return "LEFT JOIN unidad_medida um ON um.id = e.unidad_salida_id";
}

function unidadEnsamblajeSql(): string
{
    return "COALESCE(um.nombre, 'kg')";
}

// NOTA: calcularRangoPeriodoEnsamblaje/formatearFechaCorta/formatearMesAnio
// duplican lo que ya existe en clssReporteProduccion.php. Si prefieres no
// repetir código, muévelas a un archivo compartido (ej. reporteHelpers.php)
// sin el bloque de dispatch de $_POST['accion'], e inclúyelo con
// require_once en ambos controladores.

function calcularRangoPeriodoEnsamblaje(string $modo, string $fechaRef, string $fechaDesdeInput, string $fechaHastaInput): array
{
    $fechaRef = $fechaRef ?: date('Y-m-d');
    $ts = strtotime($fechaRef);
    if ($ts === false) $ts = time();

    switch ($modo) {
        case 'semana':
            $diaSemana = (int) date('N', $ts);
            $inicio = date('Y-m-d', strtotime('-' . ($diaSemana - 1) . ' days', $ts));
            $fin    = date('Y-m-d', strtotime('+' . (7 - $diaSemana) . ' days', $ts));
            return [$inicio, $fin, 'Semana del ' . formatearFechaCortaEnsamblaje($inicio) . ' al ' . formatearFechaCortaEnsamblaje($fin)];

        case 'mes':
            $inicio = date('Y-m-01', $ts);
            $fin    = date('Y-m-t', $ts);
            return [$inicio, $fin, 'Mes de ' . formatearMesAnioEnsamblaje($inicio)];

        case 'rango':
            $desde = $fechaDesdeInput ?: $fechaRef;
            $hasta = $fechaHastaInput ?: $fechaRef;
            if (strtotime($desde) > strtotime($hasta)) {
                [$desde, $hasta] = [$hasta, $desde];
            }
            return [$desde, $hasta, 'Del ' . formatearFechaCortaEnsamblaje($desde) . ' al ' . formatearFechaCortaEnsamblaje($hasta)];

        case 'dia':
        default:
            return [$fechaRef, $fechaRef, 'Día ' . formatearFechaCortaEnsamblaje($fechaRef)];
    }
}

function formatearFechaCortaEnsamblaje(string $fecha): string
{
    $meses = [
        '01' => 'ene', '02' => 'feb', '03' => 'mar', '04' => 'abr',
        '05' => 'may', '06' => 'jun', '07' => 'jul', '08' => 'ago',
        '09' => 'sep', '10' => 'oct', '11' => 'nov', '12' => 'dic',
    ];
    $ts = strtotime($fecha);
    if ($ts === false) return $fecha;
    return date('d', $ts) . ' ' . $meses[date('m', $ts)] . ' ' . date('Y', $ts);
}

function formatearMesAnioEnsamblaje(string $fecha): string
{
    $meses = [
        '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
        '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
        '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
    ];
    $ts = strtotime($fecha);
    if ($ts === false) return $fecha;
    return $meses[date('m', $ts)] . ' ' . date('Y', $ts);
}

function responder(bool $ok, string $msg, array $extra = []): void
{
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}


