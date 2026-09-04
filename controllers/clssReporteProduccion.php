<?php

/**
 * controllers/clssReporteProduccion.php
 * Controlador del módulo de Reportes -> Producción por operario
 *
 * REDISEÑO (referencia: dashboard "Tipificaciones por Ejecutivo"):
 * en vez de un ranking general con pestañas por operario/molde, el flujo
 * ahora es "busco al operario -> veo todo lo referente a su producción"
 * dentro del periodo y filtros elegidos. Se eliminó la pestaña "Por molde"
 * (ahora el detalle de moldes trabajados aparece dentro del propio reporte
 * del operario, como Top 5).
 *
 * Este controlador NO escribe nada en la base de datos: solo lee y agrega
 * lo que ya registra clssProduccion.php.
 *
 * MÉTRICA PRINCIPAL: `produccion.cantidad` (kg insertados en máquina),
 * porque siempre está presente. `cantidad_producida_kg` (kg realmente
 * producidos) se suma aparte como "kg confirmados", pero solo existe en
 * avances ya enviados a ensamblaje/empaquetado.
 *
 * TURNO: calculado a partir de la hora del avance (pd.fecha::time), igual
 * criterio que se venía usando en consultas manuales:
 *   - 06:00 a 11:59:59  -> Día
 *   - 12:00 a 17:59:59  -> Tarde
 *   - 18:00 a 23:59:59  -> Noche
 *   - resto (00:00-05:59:59) -> Madrugada
 *
 * PERIODOS:
 *   - dia:    la fecha indicada (un solo día).
 *   - semana: semana ISO (lunes a domingo) que contiene la fecha indicada.
 *   - mes:    mes calendario completo de la fecha indicada.
 *   - rango:  fecha_desde / fecha_hasta indicadas directamente.
 *
 * Solo se cuentan avances activos (produccion.deleted_at IS NULL).
 *
 * ACCESO:
 *   - REPORTEOPERARIODETALLE: uso administrativo (panel de reportes). El
 *     operario_id viene del POST porque el admin puede consultar a
 *     cualquier operario.
 *   - MISPRODUCCIONESOPERARIO: uso desde la tablet del propio operario.
 *     El operario_id JAMÁS se toma del POST; se fuerza desde
 *     $_SESSION['operario_id'] (sesión operario_*, ver
 *     controllers_tablet/clssAuthOperario.php) para que un operario no
 *     pueda ver la producción de otro manipulando el request.
 *
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

ob_start();

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    try {
        controladorReporteProduccion($_POST["accion"]);
    } catch (PDOException $e) {
        error_log("Error de base de datos en clssReporteProduccion.php: " . $e->getMessage());
        responder(false, 'Error de base de datos: ' . $e->getMessage());
    } catch (Throwable $e) {
        error_log("Error inesperado en clssReporteProduccion.php: " . $e->getMessage());
        responder(false, 'Error inesperado en el servidor: ' . $e->getMessage());
    }
}

function controladorReporteProduccion($accion)
{
    switch ($accion) {
        case 'BUSCAROPERARIOSREPORTE':
            buscarOperariosReporte();
            break;
        case 'BUSCARMAQUINASREPORTE':
            buscarMaquinasReporte();
            break;
        case 'BUSCARSUCURSALESREPORTE':
            buscarSucursalesReporte();
            break;
        case 'REPORTEOPERARIODETALLE':
            reporteOperarioDetalle();
            break;
        case 'MISPRODUCCIONESOPERARIO':
            misProduccionesOperario();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// LISTADOS AUXILIARES (para el buscador de operario y los filtros)
// =============================================================================

function buscarOperariosReporte()
{
    $conectar = conectar_oll_BD();
    $sql = "
        SELECT o.id, o.nombre_completo, c.nombre AS cargo
        FROM operario o
        LEFT JOIN cargo c ON c.id = o.cargo_id
        WHERE o.activo = true
          AND EXISTS (
              SELECT 1 FROM jsonb_array_elements(COALESCE(o.js_etapas_relacionadas, '[]'::jsonb)) AS et
              WHERE et->>'nombre' ILIKE '%PRODUC%'
          )
        ORDER BY o.nombre_completo
    ";
    $result = executeQuery($conectar, $sql, []);
    responder(true, 'OK', ['operarios' => $result]);
}

function buscarMaquinasReporte()
{
    $conectar = conectar_oll_BD();
    $sql = "SELECT id, nombre FROM maquina WHERE deleted_at IS NULL ORDER BY nombre";
    $result = executeQuery($conectar, $sql, []);
    responder(true, 'OK', ['maquinas' => $result]);
}

// sucursal usa update_at/delete_at (no deleted_at) y no tiene columna activo.
function buscarSucursalesReporte()
{
    $conectar = conectar_oll_BD();
    $sql = "SELECT id, nombre FROM sucursal WHERE delete_at IS NULL ORDER BY nombre";
    $result = executeQuery($conectar, $sql, []);
    responder(true, 'OK', ['sucursales' => $result]);
}

// =============================================================================
// REPORTE ADMINISTRATIVO (operario_id viene del POST; se usa desde el panel
// de reportes, donde el admin elige a qué operario quiere consultar)
// =============================================================================

function reporteOperarioDetalle()
{
    $conectar = conectar_oll_BD();

    $operarioId = intval($_POST['operario_id'] ?? 0);
    if (!$operarioId) {
        responder(false, 'Debes indicar un operario.');
    }

    $payload = reporteOperarioDetalleInterno($conectar, $operarioId, [
        'modo'         => trim($_POST['modo'] ?? 'dia'),
        'fecha'        => trim($_POST['fecha'] ?? date('Y-m-d')),
        'fecha_desde'  => trim($_POST['fecha_desde'] ?? ''),
        'fecha_hasta'  => trim($_POST['fecha_hasta'] ?? ''),
        'turno'        => trim($_POST['turno'] ?? ''),
        'maquina_id'   => intval($_POST['maquina_id'] ?? 0),
        'sucursal_id'  => intval($_POST['sucursal_id'] ?? 0),
    ]);

    responder(true, 'OK', $payload);
}

// =============================================================================
// "MIS PRODUCCIONES" (tablet del propio operario)
// El operario_id se fuerza desde la sesión, nunca desde el POST.
// =============================================================================

function misProduccionesOperario()
{
    // Sesión de la tablet del operario (ver controllers_tablet/clssAuthOperario.php,
    // mismo namespace operario_* que usa panel_operario.php).
    $operarioId = intval($_SESSION['operario_id'] ?? 0);
    if (!$operarioId) {
        responder(false, 'Sesión de operario no válida. Vuelve a iniciar sesión.');
    }

    $conectar = conectar_oll_BD();

    // Filtros reducidos: el operario ve su propio periodo, sin necesidad de
    // buscador de operario/máquina/sucursal (eso es para el reporte admin).
    $payload = reporteOperarioDetalleInterno($conectar, $operarioId, [
        'modo'         => trim($_POST['modo'] ?? 'dia'),
        'fecha'        => trim($_POST['fecha'] ?? date('Y-m-d')),
        'fecha_desde'  => trim($_POST['fecha_desde'] ?? ''),
        'fecha_hasta'  => trim($_POST['fecha_hasta'] ?? ''),
        'turno'        => '',
        'maquina_id'   => 0,
        'sucursal_id'  => 0,
    ]);

    responder(true, 'OK', $payload);
}

// =============================================================================
// LÓGICA COMPARTIDA (usada por el reporte admin y por "Mis producciones")
// =============================================================================

/**
 * Arma todo el reporte de un operario (resumen, por turno, por máquina,
 * top moldes y detalle) para el periodo y filtros indicados.
 *
 * $operarioId siempre llega ya resuelto y validado por el caller
 * (nunca se lee $_POST['operario_id'] aquí adentro), de modo que esta
 * función es segura de usar tanto para el reporte admin como para el
 * endpoint de "Mis producciones" del operario.
 */
function reporteOperarioDetalleInterno($conectar, int $operarioId, array $filtros): array
{
    $modo            = $filtros['modo'] ?? 'dia';
    $fechaRef        = $filtros['fecha'] ?? date('Y-m-d');
    $fechaDesdeInput = $filtros['fecha_desde'] ?? '';
    $fechaHastaInput = $filtros['fecha_hasta'] ?? '';
    $turno           = $filtros['turno'] ?? '';
    $maquinaId       = (int) ($filtros['maquina_id'] ?? 0);
    $sucursalId      = (int) ($filtros['sucursal_id'] ?? 0);

    $operario = executeQuery(
        $conectar,
        "SELECT o.id, o.nombre_completo, c.nombre AS cargo
         FROM operario o
         LEFT JOIN cargo c ON c.id = o.cargo_id
         WHERE o.id = :id",
        ['id' => $operarioId]
    );
    if (empty($operario)) {
        responder(false, 'Operario no encontrado.');
    }

    [$fechaDesde, $fechaHasta, $etiquetaPeriodo] = calcularRangoPeriodo(
        $modo,
        $fechaRef,
        $fechaDesdeInput,
        $fechaHastaInput
    );

    if (!$fechaDesde || !$fechaHasta) {
        responder(false, 'Debes indicar un rango de fechas válido.');
    }
    if (strtotime($fechaDesde) > strtotime($fechaHasta)) {
        responder(false, 'La fecha de inicio no puede ser posterior a la fecha final.');
    }

    // NOTA: producción ahora es multi-operario (pd.js_operarios jsonb array).
    // Este JOIN LATERAL + filtro por operario_id deja, en la práctica, una
    // sola fila por avance (la del operario buscado), igual que antes con
    // operario_id escalar — pero ahora "op" trae la cantidad_producida que
    // le corresponde específicamente a ESE operario dentro del avance.
    $joinOperario = "CROSS JOIN LATERAL jsonb_array_elements(COALESCE(pd.js_operarios, '[]'::jsonb)) AS op";
    $producidoOperario = "COALESCE((op->>'cantidad_producida')::numeric, 0)";

    $condiciones = [
        "pd.deleted_at IS NULL",
        "(op->>'operario_id')::bigint = :operario_id",
        "pd.fecha::date BETWEEN :fecha_desde AND :fecha_hasta",
    ];
    $params = [
        'operario_id' => $operarioId,
        'fecha_desde' => $fechaDesde,
        'fecha_hasta' => $fechaHasta,
    ];

    if ($maquinaId > 0) {
        $condiciones[] = "pd.maquina_id = :maquina_id";
        $params['maquina_id'] = $maquinaId;
    }
    if ($sucursalId > 0) {
        $condiciones[] = "pd.sucursal = :sucursal_id";
        $params['sucursal_id'] = $sucursalId;
    }

    $turnoCase = turnoCaseSql('pd.fecha');
    if ($turno !== '' && in_array($turno, ['dia', 'tarde', 'noche', 'madrugada'], true)) {
        $condiciones[] = "$turnoCase = :turno";
        $params['turno'] = $turno;
    }

    $condicionBase = implode(' AND ', $condiciones);
    $unidadSql = unidadCaseSql('pd');

    // ── Resumen general (no depende de la unidad; cantidad insertada sigue siendo a nivel de avance) ─
    $sqlResumenGeneral = "
        SELECT
            COUNT(pd.id) AS total_avances,
            COUNT(DISTINCT pd.molde_id) AS moldes_distintos,
            COALESCE(SUM(pd.cantidad), 0) AS total_kg_insertado
        FROM produccion pd
        $joinOperario
        WHERE $condicionBase
    ";
    $resumenGeneralFilas = executeQuery($conectar, $sqlResumenGeneral, $params);
    $resumenGeneral = $resumenGeneralFilas[0] ?? [
        'total_avances' => 0,
        'moldes_distintos' => 0,
        'total_kg_insertado' => 0,
    ];

    // ── Resumen de lo PRODUCIDO por ESTE operario, separado por unidad de salida ─
    $sqlResumenPorUnidad = "
        SELECT
            $unidadSql AS unidad,
            COUNT(pd.id) AS avances,
            COUNT(DISTINCT pd.molde_id) AS moldes_distintos,
            COALESCE(SUM($producidoOperario), 0) AS total_producido,
            ROUND(COALESCE(SUM($producidoOperario), 0)::numeric / NULLIF(COUNT(pd.id), 0), 2) AS promedio
        FROM produccion pd
        $joinOperario
        WHERE $condicionBase
        GROUP BY 1
        ORDER BY total_producido DESC
    ";
    $resumenPorUnidad = executeQuery($conectar, $sqlResumenPorUnidad, $params);

    // ── Distribución por turno, separada por unidad ──
    $sqlPorTurno = "
        SELECT
            $turnoCase AS turno,
            $unidadSql AS unidad,
            COUNT(pd.id) AS avances,
            COALESCE(SUM($producidoOperario), 0) AS cantidad
        FROM produccion pd
        $joinOperario
        WHERE $condicionBase
        GROUP BY 1, 2
        ORDER BY unidad, cantidad DESC
    ";
    $porTurno = executeQuery($conectar, $sqlPorTurno, $params);

    // ── Distribución por máquina, separada por unidad ──
    $sqlPorMaquina = "
        SELECT
            COALESCE(ma.nombre, 'Sin máquina') AS maquina,
            $unidadSql AS unidad,
            COUNT(pd.id) AS avances,
            COALESCE(SUM($producidoOperario), 0) AS cantidad
        FROM produccion pd
        $joinOperario
        LEFT JOIN maquina ma ON ma.id = pd.maquina_id
        WHERE $condicionBase
        GROUP BY ma.nombre, 2
        ORDER BY unidad, cantidad DESC
    ";
    $porMaquina = executeQuery($conectar, $sqlPorMaquina, $params);

    // ── Top 5 moldes trabajados en el periodo ──
    $sqlTopMoldes = "
        SELECT
            mo.id AS molde_id,
            mo.nombre AS molde_nombre,
            pr.descripcion AS producto_descripcion,
            COUNT(pd.id) AS avances,
            COALESCE(SUM($producidoOperario), 0) AS kg_producido,
            MAX($unidadSql) AS unidad
        FROM produccion pd
        $joinOperario
        LEFT JOIN molde mo ON mo.id = pd.molde_id
        LEFT JOIN producto pr ON NULLIF(split_part(pd.unico_molde_producto, '-', 2), '')::bigint = pr.id
        WHERE $condicionBase
        GROUP BY mo.id, mo.nombre, pr.descripcion
        ORDER BY kg_producido DESC, avances DESC
        LIMIT 5
    ";
    $topMoldes = executeQuery($conectar, $sqlTopMoldes, $params);

    // ── Detalle completo (avance a avance) ──
    $sqlDetalle = "
        SELECT
            pd.id,
            pd.fecha::date AS fecha,
            pd.fecha::time AS hora,
            $turnoCase AS turno,
            mo.nombre AS molde_nombre,
            pr.descripcion AS producto_descripcion,
            COALESCE(ma.nombre, 'Sin máquina') AS maquina_nombre,
            co.nombre AS color_nombre,
            pd.cantidad AS kg_insertado,
            $producidoOperario AS kg_producido,
            $unidadSql AS unidad,
            pd.observaciones
        FROM produccion pd
        $joinOperario
        LEFT JOIN molde mo ON mo.id = pd.molde_id
        LEFT JOIN producto pr ON NULLIF(split_part(pd.unico_molde_producto, '-', 2), '')::bigint = pr.id
        LEFT JOIN maquina ma ON ma.id = pd.maquina_id
        LEFT JOIN color co ON co.id = pd.color_id
        WHERE $condicionBase
        ORDER BY pd.fecha DESC
    ";
    $detalle = executeQuery($conectar, $sqlDetalle, $params);

    return [
        'operario' => $operario[0],
        'periodo' => [
            'modo'     => $modo,
            'desde'    => $fechaDesde,
            'hasta'    => $fechaHasta,
            'etiqueta' => $etiquetaPeriodo,
        ],
        'resumen_general'    => $resumenGeneral,
        'resumen_por_unidad' => $resumenPorUnidad,
        'por_turno'   => $porTurno,
        'por_maquina' => $porMaquina,
        'top_moldes'  => $topMoldes,
        'detalle'     => $detalle,
    ];
}

// =============================================================================
// HELPERS
// =============================================================================

/**
 * Expresión SQL (CASE) que clasifica un timestamp en turno: dia | tarde |
 * noche | madrugada. $columnaFecha debe ser un timestamp (ej. "pd.fecha").
 */
function turnoCaseSql(string $columnaFecha): string
{
    return "
        CASE
            WHEN $columnaFecha::time >= '06:00:00' AND $columnaFecha::time < '12:00:00' THEN 'dia'
            WHEN $columnaFecha::time >= '12:00:00' AND $columnaFecha::time < '18:00:00' THEN 'tarde'
            WHEN $columnaFecha::time >= '18:00:00' THEN 'noche'
            ELSE 'madrugada'
        END
    ";
}

/**
 * Expresión SQL que obtiene la unidad de salida de un avance desde el
 * jsonb js_configuracion_moment. Si no está seteada, asume 'kg'.
 */
function unidadCaseSql(string $aliasProduccion): string
{
    return "COALESCE(NULLIF($aliasProduccion.js_configuracion_moment->>'salida_produccion', ''), 'kg')";
}

function calcularRangoPeriodo(string $modo, string $fechaRef, string $fechaDesdeInput, string $fechaHastaInput): array
{
    $fechaRef = $fechaRef ?: date('Y-m-d');
    $ts = strtotime($fechaRef);
    if ($ts === false) $ts = time();

    switch ($modo) {
        case 'semana':
            // Semana ISO: lunes (1) a domingo (7) que contiene $fechaRef.
            $diaSemana = (int) date('N', $ts);
            $inicio = date('Y-m-d', strtotime('-' . ($diaSemana - 1) . ' days', $ts));
            $fin    = date('Y-m-d', strtotime('+' . (7 - $diaSemana) . ' days', $ts));
            return [$inicio, $fin, 'Semana del ' . formatearFechaCorta($inicio) . ' al ' . formatearFechaCorta($fin)];

        case 'mes':
            $inicio = date('Y-m-01', $ts);
            $fin    = date('Y-m-t', $ts);
            return [$inicio, $fin, 'Mes de ' . formatearMesAnio($inicio)];

        case 'rango':
            $desde = $fechaDesdeInput ?: $fechaRef;
            $hasta = $fechaHastaInput ?: $fechaRef;
            if (strtotime($desde) > strtotime($hasta)) {
                [$desde, $hasta] = [$hasta, $desde];
            }
            return [$desde, $hasta, 'Del ' . formatearFechaCorta($desde) . ' al ' . formatearFechaCorta($hasta)];

        case 'dia':
        default:
            return [$fechaRef, $fechaRef, 'Día ' . formatearFechaCorta($fechaRef)];
    }
}

function formatearFechaCorta(string $fecha): string
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

function formatearMesAnio(string $fecha): string
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