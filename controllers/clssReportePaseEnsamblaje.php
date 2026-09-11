<?php

/**
 * controllers/clssReportePaseEnsamblaje.php
 * Reporte de Pase de Producción a Ensamblaje.
 *
 * Objetivo: por cada producción que "necesita_ensamblaje" = 'sí', mostrar
 * qué ensamblaje(s) consumieron esos kg (vía js_moldes_utilizados del
 * ensamblaje), cuánto entró desde producción, cuánto salió del ensamblaje,
 * la diferencia/merma y el % de rendimiento. Incluye el detalle de
 * materiales utilizados en esa producción (rel_produccion_material) para
 * mostrarlo en un modal, sin llamada adicional al backend.
 *
 * LIMITACIÓN CONOCIDA / OJO AL SUMAR TOTALES:
 * Un mismo ensamblaje puede aparecer en varias filas si usó más de un
 * molde (js_moldes_utilizados con varios elementos). e.cantidad_peso_kg
 * es la salida TOTAL del ensamblaje (no por molde), así que sumarla
 * directo por fila infla el total. Por eso en reportePaseEnsamblajeDashboard()
 * el total_ensamblado_kg se calcula sobre ensamblajes ÚNICOS, mientras que
 * total_producido_kg sí se suma por fila (porque cantidad_kg ahí es el
 * consumo real de ESA producción puntual).
 *
 * SUPUESTOS DE ESQUEMA (ajustar si no calzan con tu BD real):
 *   - molde(id, nombre, deleted_at)
 *   - producto(id, codigo, descripcion)
 *   - operario(id, nombre_completo)
 *   - e.sucursal es un valor ya listo para mostrar (texto o id crudo, tal
 *     como lo trae la consulta original); si en tu BD es un id que debe
 *     resolverse contra la tabla sucursal, agrega el LEFT JOIN
 *     correspondiente en reportePaseEnsamblajeDashboard().
 *
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

ob_start();

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    try {
        controladorReportePaseEnsamblaje($_POST["accion"]);
    } catch (PDOException $e) {
        error_log("Error de base de datos en clssReportePaseEnsamblaje.php: " . $e->getMessage());
        responder(false, 'Error de base de datos: ' . $e->getMessage());
    } catch (Throwable $e) {
        error_log("Error inesperado en clssReportePaseEnsamblaje.php: " . $e->getMessage());
        responder(false, 'Error inesperado en el servidor: ' . $e->getMessage());
    }
}

function controladorReportePaseEnsamblaje($accion)
{
    switch ($accion) {
        case 'BUSCARMOLDESREPORTEPASE':
            buscarMoldesReportePase();
            break;
        case 'BUSCARCATEGORIASMATERIALREPORTEPASE':
            buscarCategoriasMaterialReportePase();
            break;
        case 'REPORTEPASEENSAMBLAJEDASHBOARD':
            reportePaseEnsamblajeDashboard();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// LISTADOS AUXILIARES (filtros)
// =============================================================================

// Ajusta el nombre de tabla/columna si molde no tiene deleted_at.
function buscarMoldesReportePase()
{
    $conectar = conectar_oll_BD();
    $sql = "SELECT id, nombre FROM molde WHERE deleted_at IS NULL ORDER BY nombre";
    $result = executeQuery($conectar, $sql, []);
    responder(true, 'OK', ['moldes' => $result]);
}

function buscarCategoriasMaterialReportePase()
{
    $conectar = conectar_oll_BD();
    $sql = "SELECT id, nombre FROM categoria_material ORDER BY nombre";
    $result = executeQuery($conectar, $sql, []);
    responder(true, 'OK', ['categorias' => $result]);
}

// =============================================================================
// DASHBOARD PRINCIPAL (resumen + filas con detalle de materiales embebido)
// =============================================================================

function reportePaseEnsamblajeDashboard()
{
    $conectar = conectar_oll_BD();

    $filtros = [
        'modo'                  => trim($_POST['modo'] ?? 'mes'),
        'fecha'                 => trim($_POST['fecha'] ?? date('Y-m-d')),
        'fecha_desde'           => trim($_POST['fecha_desde'] ?? ''),
        'fecha_hasta'           => trim($_POST['fecha_hasta'] ?? ''),
        'molde_id'               => intval($_POST['molde_id'] ?? 0),
        'categoria_material_id' => intval($_POST['categoria_material_id'] ?? 0),
    ];

    [$fechaDesde, $fechaHasta, $etiquetaPeriodo] = calcularRangoPeriodoPase(
        $filtros['modo'], $filtros['fecha'], $filtros['fecha_desde'], $filtros['fecha_hasta']
    );
    if (!$fechaDesde || !$fechaHasta) {
        responder(false, 'Debes indicar un rango de fechas válido.');
    }

    $params = [];
    $condicionBase = condicionesPaseSql($params, $filtros, $fechaDesde, $fechaHasta);

    // ── Resumen general (kg producido / kg ensamblado / merma / rendimiento) ──
    // Ver nota de LIMITACIÓN CONOCIDA arriba: total_ensamblado_kg se calcula
    // sobre ensamblajes únicos para no inflarse cuando un ensamblaje usó
    // más de un molde.
    $sqlResumen = "
        WITH filas AS (
            SELECT
                e.id AS ensamblaje_id,
                e.cantidad_peso_kg AS salida_kg,
                (molde->>'cantidad_kg')::numeric AS entrada_kg,
                e.enviado_empaquetado
            FROM ensamblaje e
            CROSS JOIN LATERAL jsonb_array_elements(
                COALESCE(e.js_moldes_utilizados, '[]'::jsonb)
            ) AS molde
            INNER JOIN produccion p
                ON p.id = (molde->>'produccion_id')::integer
            WHERE $condicionBase
        ),
        ensamblajes_unicos AS (
            SELECT DISTINCT ensamblaje_id, salida_kg, enviado_empaquetado
            FROM filas
        )
        SELECT
            (SELECT COUNT(*) FROM ensamblajes_unicos) AS total_ensamblajes,
            (SELECT COUNT(*) FROM ensamblajes_unicos WHERE enviado_empaquetado IS TRUE)
                AS enviados_empaquetado,
            (SELECT COUNT(*) FROM ensamblajes_unicos WHERE enviado_empaquetado IS NOT TRUE)
                AS pendientes,
            COALESCE((SELECT SUM(salida_kg) FROM ensamblajes_unicos), 0)
                AS total_ensamblado_kg,
            COALESCE((SELECT SUM(entrada_kg) FROM filas), 0)
                AS total_producido_kg
    ";
    $resumenFilas = executeQuery($conectar, $sqlResumen, $params);
    $resumen = $resumenFilas[0] ?? [
        'total_ensamblajes' => 0, 'enviados_empaquetado' => 0, 'pendientes' => 0,
        'total_ensamblado_kg' => 0, 'total_producido_kg' => 0,
    ];
    $producidoKg = (float) $resumen['total_producido_kg'];
    $ensambladoKg = (float) $resumen['total_ensamblado_kg'];
    $resumen['total_diferencia_kg'] = round($producidoKg - $ensambladoKg, 2);
    $resumen['rendimiento_promedio'] = $producidoKg > 0
        ? round(($ensambladoKg / $producidoKg) * 100, 2)
        : null;

    // ── Filas de detalle (una por cada molde utilizado en cada ensamblaje) ──
    $sqlFilas = "
        SELECT
            p.id AS produccion_id,
            p.fecha AS fecha_produccion,
            p.cantidad AS cantidad_producida,
            p.cantidad_producida_kg,

            p.js_configuracion_moment->>'molde' AS molde_nombre,
            (molde->>'molde_id')::integer AS molde_id,

            (molde->>'cantidad_kg')::numeric AS cantidad_utilizada_kg,
            molde->>'unidad_produccion_codigo' AS unidad_utilizada,
            (molde->>'categoria_material_id')::bigint AS categoria_material_id,
            molde->>'categoria_material_nombre' AS categoria_material,

            e.id AS ensamblaje_id,
            e.producto_id,
            prod.codigo AS producto_codigo,
            prod.descripcion AS producto_descripcion,

            e.cantidad_peso_kg AS cantidad_salida_ensamblaje_kg,
            e.inicio AS inicio_ensamblaje,
            e.fin AS fin_ensamblaje,

            COALESCE(op_multi.nombres, op1.nombre_completo, 'Sin operario')
                AS operario_display,

            e.sucursal AS sucursal_ensamblaje,
            e.proveniente,
            e.enviado_empaquetado,

            (
                (molde->>'cantidad_kg')::numeric
                - COALESCE(e.cantidad_peso_kg, 0)
            ) AS diferencia_kg,

            CASE
                WHEN (molde->>'cantidad_kg')::numeric > 0
                THEN ROUND(
                    (e.cantidad_peso_kg / (molde->>'cantidad_kg')::numeric) * 100,
                    2
                )
            END AS rendimiento_porcentaje,

            -- Materiales usados en ESA producción, embebidos aquí para
            -- mostrarlos en un modal sin pegarle otra vez al backend.
            COALESCE(
                (
                    SELECT jsonb_agg(
                        jsonb_build_object(
                            'material_id', m.id,
                            'material', m.nombre,
                            'cantidad', rpm.cantidad,
                            'unidad_medida', um.nombre,
                            'comentario', rpm.comentario
                        )
                        ORDER BY m.id
                    )
                    FROM rel_produccion_material rpm
                    INNER JOIN material m ON m.id = rpm.material_id
                    LEFT JOIN unidad_medida um ON um.id = m.unidad_medida_id
                    WHERE rpm.produccion_id = p.id
                      AND rpm.deleted_at IS NULL
                ),
                '[]'::jsonb
            ) AS materiales_utilizados

        FROM ensamblaje e

        LEFT JOIN LATERAL (
            SELECT string_agg(o2->>'nombre_completo', ', ') AS nombres
            FROM jsonb_array_elements(COALESCE(e.js_operarios, '[]'::jsonb)) o2
        ) AS op_multi ON true
        LEFT JOIN operario op1 ON op1.id = e.operario_ortorgado
        LEFT JOIN producto prod ON prod.id = e.producto_id

        CROSS JOIN LATERAL jsonb_array_elements(
            COALESCE(e.js_moldes_utilizados, '[]'::jsonb)
        ) AS molde

        INNER JOIN produccion p
            ON p.id = (molde->>'produccion_id')::integer

        WHERE $condicionBase

        ORDER BY p.fecha DESC, p.id DESC, e.id DESC
    ";
    $filas = executeQuery($conectar, $sqlFilas, $params);
    foreach ($filas as &$fila) {
        $fila['materiales_utilizados'] = json_decode($fila['materiales_utilizados'] ?? '[]', true) ?: [];
    }
    unset($fila);

    responder(true, 'OK', [
        'periodo' => ['desde' => $fechaDesde, 'hasta' => $fechaHasta, 'etiqueta' => $etiquetaPeriodo],
        'resumen' => $resumen,
        'filas'   => $filas,
    ]);
}

// =============================================================================
// HELPERS
// =============================================================================

/**
 * Arma el WHERE compartido entre la consulta de resumen y la de filas.
 * Llena $params por referencia. Requiere que en el SQL que lo use existan
 * los alias "e" (ensamblaje), "p" (produccion) y "molde" (jsonb_array_elements
 * de js_moldes_utilizados).
 */
function condicionesPaseSql(array &$params, array $filtros, string $fechaDesde, string $fechaHasta): string
{
    $condiciones = [
        "e.deleted_at IS NULL",
        "p.deleted_at IS NULL",
        "p.js_configuracion_moment->>'necesita_ensamblaje' = 'sí'",
        "p.fecha BETWEEN :fecha_desde AND :fecha_hasta",
    ];
    $params['fecha_desde'] = $fechaDesde;
    $params['fecha_hasta'] = $fechaHasta;

    if ($filtros['molde_id'] > 0) {
        $condiciones[] = "(molde->>'molde_id')::integer = :molde_id";
        $params['molde_id'] = $filtros['molde_id'];
    }
    if ($filtros['categoria_material_id'] > 0) {
        $condiciones[] = "(molde->>'categoria_material_id')::bigint = :categoria_material_id";
        $params['categoria_material_id'] = $filtros['categoria_material_id'];
    }

    return implode(' AND ', $condiciones);
}

// NOTA: calcularRangoPeriodoPase/formatearFechaCortaPase/formatearMesAnioPase
// duplican lo mismo que ya existe en clssReporteEnsamblaje.php y
// clssReporteProduccion.php. Si prefieres no repetir código, muévelas a un
// archivo compartido (ej. reporteHelpers.php) sin el bloque de dispatch de
// $_POST['accion'], e inclúyelo con require_once en los tres controladores.

function calcularRangoPeriodoPase(string $modo, string $fechaRef, string $fechaDesdeInput, string $fechaHastaInput): array
{
    $fechaRef = $fechaRef ?: date('Y-m-d');
    $ts = strtotime($fechaRef);
    if ($ts === false) $ts = time();

    switch ($modo) {
        case 'semana':
            $diaSemana = (int) date('N', $ts);
            $inicio = date('Y-m-d', strtotime('-' . ($diaSemana - 1) . ' days', $ts));
            $fin    = date('Y-m-d', strtotime('+' . (7 - $diaSemana) . ' days', $ts));
            return [$inicio, $fin, 'Semana del ' . formatearFechaCortaPase($inicio) . ' al ' . formatearFechaCortaPase($fin)];

        case 'mes':
            $inicio = date('Y-m-01', $ts);
            $fin    = date('Y-m-t', $ts);
            return [$inicio, $fin, 'Mes de ' . formatearMesAnioPase($inicio)];

        case 'rango':
            $desde = $fechaDesdeInput ?: $fechaRef;
            $hasta = $fechaHastaInput ?: $fechaRef;
            if (strtotime($desde) > strtotime($hasta)) {
                [$desde, $hasta] = [$hasta, $desde];
            }
            return [$desde, $hasta, 'Del ' . formatearFechaCortaPase($desde) . ' al ' . formatearFechaCortaPase($hasta)];

        case 'dia':
        default:
            return [$fechaRef, $fechaRef, 'Día ' . formatearFechaCortaPase($fechaRef)];
    }
}

function formatearFechaCortaPase(string $fecha): string
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

function formatearMesAnioPase(string $fecha): string
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