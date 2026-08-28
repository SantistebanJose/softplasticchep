<?php

/**
 * controllers/clssReporteProduccion.php
 * Controlador del módulo de Reportes -> Producción (por operario y por molde)
 *
 * Este controlador NO escribe nada en la base de datos: solo lee y agrega
 * lo que ya registra clssProduccion.php. Vive aparte (no dentro de
 * clssProduccion.php) porque conceptualmente es un reporte, no parte del
 * flujo de registro de avances.
 *
 * MÉTRICA PRINCIPAL: se usa `produccion.cantidad` (kg insertados en
 * máquina) como base del ranking, porque SIEMPRE está presente y es un
 * valor entero. `cantidad_producida_kg` (kg realmente producidos) también
 * se suma aparte como "kg confirmados", pero solo existe en los avances
 * que ya fueron enviados a ensamblaje/empaquetado (enviado_ensamblaje =
 * true); por eso no se usa como base del ranking, para no penalizar a
 * operarios con corridas todavía en curso.
 *
 * PERIODOS:
 *   - dia:    la fecha indicada (un solo día).
 *   - semana: semana ISO (lunes a domingo) que contiene la fecha indicada.
 *   - mes:    mes calendario completo de la fecha indicada.
 *   - rango:  fecha_desde / fecha_hasta indicadas directamente.
 *
 * "Operario destacado" del periodo = el de mayor total de kg insertados
 * (con total de avances como criterio de desempate). Se calcula sobre los
 * mismos filtros que el ranking (máquina/sucursal/operario si se aplican).
 *
 * Solo se cuentan avances activos (produccion.deleted_at IS NULL).
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
        case 'REPORTEOPERARIOS':
            reporteOperarios();
            break;
        case 'REPORTEDETALLEOPERARIO':
            reporteDetalleOperario();
            break;
        case 'BUSCAROPERARIOSREPORTE':
            buscarOperariosReporte();
            break;
        case 'BUSCARMAQUINASREPORTE':
            buscarMaquinasReporte();
            break;
        case 'BUSCARSUCURSALESREPORTE':
            buscarSucursalesReporte();
            break;
        case 'REPORTEMOLDES':
            reporteMoldes();
            break;
        case 'REPORTEDETALLEMOLDE':
            reporteDetalleMolde();
            break;
        case 'BUSCARMOLDESREPORTE':
            buscarMoldesReporte();
            break;
        case 'BUSCARCOLORESREPORTE':
            buscarColoresReporte();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// LISTADOS AUXILIARES (para los <select> de filtros)
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

function buscarMoldesReporte()
{
    $conectar = conectar_oll_BD();
    $sql = "SELECT id, nombre FROM molde WHERE deleted_at IS NULL ORDER BY nombre";
    $result = executeQuery($conectar, $sql, []);
    responder(true, 'OK', ['moldes' => $result]);
}

function buscarColoresReporte()
{
    $conectar = conectar_oll_BD();
    $sql = "SELECT id, nombre, rgb FROM color WHERE deleted_at IS NULL ORDER BY nombre";
    $result = executeQuery($conectar, $sql, []);
    responder(true, 'OK', ['colores' => $result]);
}

// =============================================================================
// REPORTE PRINCIPAL — POR OPERARIO
// =============================================================================

/**
 * Ranking de operarios en un periodo, más la serie diaria (para la
 * tabla/gráfico de tendencia) y el resumen general del periodo.
 */
function reporteOperarios()
{
    $conectar = conectar_oll_BD();

    $modo             = trim($_POST['modo'] ?? 'dia'); // dia | semana | mes | rango
    $fechaRef         = trim($_POST['fecha'] ?? date('Y-m-d'));
    $fechaDesdeInput  = trim($_POST['fecha_desde'] ?? '');
    $fechaHastaInput  = trim($_POST['fecha_hasta'] ?? '');
    $operarioId       = intval($_POST['operario_id'] ?? 0);
    $maquinaId        = intval($_POST['maquina_id'] ?? 0);
    $sucursalId       = intval($_POST['sucursal_id'] ?? 0);
    $soloConActividad = ($_POST['solo_con_actividad'] ?? '1') !== '0';

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

    // Condiciones que filtran los AVANCES (se usan en el JOIN, para no
    // excluir operarios que simplemente no tuvieron actividad).
    $joinConditions = ["pd.deleted_at IS NULL", "pd.fecha::date BETWEEN :fecha_desde AND :fecha_hasta"];
    $params = ['fecha_desde' => $fechaDesde, 'fecha_hasta' => $fechaHasta];

    if ($maquinaId > 0) {
        $joinConditions[] = "pd.maquina_id = :maquina_id";
        $params['maquina_id'] = $maquinaId;
    }
    if ($sucursalId > 0) {
        $joinConditions[] = "pd.sucursal = :sucursal_id";
        $params['sucursal_id'] = $sucursalId;
    }
    $condicionJoin = implode(' AND ', $joinConditions);

    // Condiciones que filtran los OPERARIOS mostrados.
    $whereOperario = [
        "op.activo = true",
        "EXISTS (
            SELECT 1 FROM jsonb_array_elements(COALESCE(op.js_etapas_relacionadas, '[]'::jsonb)) AS et
            WHERE et->>'nombre' ILIKE '%PRODUC%'
        )"
    ];
    if ($operarioId > 0) {
        $whereOperario[] = "op.id = :operario_id";
        $params['operario_id'] = $operarioId;
    }
    $condicionOperario = implode(' AND ', $whereOperario);

    $having = $soloConActividad ? "HAVING COUNT(pd.id) > 0" : "";

    $sql = "
        SELECT
            op.id AS operario_id,
            op.nombre_completo AS operario_nombre,
            c.nombre AS cargo,
            COUNT(pd.id) AS total_avances,
            COALESCE(SUM(pd.cantidad), 0) AS total_kg_insertado,
            COALESCE(SUM(pd.cantidad_producida_kg), 0) AS total_kg_producido,
            COUNT(pd.cantidad_producida_kg) AS avances_finalizados,
            ROUND(COALESCE(SUM(pd.cantidad), 0)::numeric / NULLIF(COUNT(pd.id), 0), 2) AS promedio_kg_avance
        FROM operario op
        LEFT JOIN cargo c ON c.id = op.cargo_id
        LEFT JOIN produccion pd ON pd.operario_id = op.id AND $condicionJoin
        WHERE $condicionOperario
        GROUP BY op.id, op.nombre_completo, c.nombre
        $having
        ORDER BY total_kg_insertado DESC, total_avances DESC, operario_nombre ASC
    ";

    $filas = executeQuery($conectar, $sql, $params);

    // El primero de la lista con actividad real es el destacado del periodo.
    $destacado = null;
    foreach ($filas as $f) {
        if ((int) $f['total_avances'] > 0) {
            $destacado = $f;
            break;
        }
    }

    // Serie diaria del periodo (todos los operarios filtrados juntos),
    // para la tabla/tendencia de "kg por día".
    $sqlSerie = "
        SELECT
            pd.fecha::date AS dia,
            COUNT(pd.id) AS total_avances,
            COALESCE(SUM(pd.cantidad), 0) AS total_kg
        FROM produccion pd
        JOIN operario op ON op.id = pd.operario_id
        WHERE $condicionJoin AND $condicionOperario
        GROUP BY pd.fecha::date
        ORDER BY dia
    ";
    $serie = executeQuery($conectar, $sqlSerie, $params);

    // Resumen general del periodo (sobre las filas ya filtradas).
    $totalKg      = 0;
    $totalAvances = 0;
    $operariosConActividad = 0;
    foreach ($filas as $f) {
        $totalKg      += (float) $f['total_kg_insertado'];
        $totalAvances += (int) $f['total_avances'];
        if ((int) $f['total_avances'] > 0) $operariosConActividad++;
    }

    responder(true, 'OK', [
        'periodo' => [
            'modo'      => $modo,
            'desde'     => $fechaDesde,
            'hasta'     => $fechaHasta,
            'etiqueta'  => $etiquetaPeriodo,
        ],
        'resumen' => [
            'total_kg_insertado'     => $totalKg,
            'total_avances'          => $totalAvances,
            'operarios_con_actividad' => $operariosConActividad,
        ],
        'destacado'    => $destacado,
        'filas'        => $filas,
        'serie_diaria' => $serie,
    ]);
}

/**
 * Detalle día por día de UN operario dentro de un rango de fechas ya
 * calculado por el reporte principal (se usa para el modal "Ver detalle").
 */
function reporteDetalleOperario()
{
    $conectar = conectar_oll_BD();

    $operarioId = intval($_POST['operario_id'] ?? 0);
    $fechaDesde = trim($_POST['fecha_desde'] ?? '');
    $fechaHasta = trim($_POST['fecha_hasta'] ?? '');

    if (!$operarioId) responder(false, 'Debes indicar un operario.');
    if (!$fechaDesde || !$fechaHasta) responder(false, 'Debes indicar el rango de fechas del periodo.');

    $operario = executeQuery(
        $conectar,
        "SELECT nombre_completo FROM operario WHERE id = :id",
        ['id' => $operarioId]
    );
    if (empty($operario)) responder(false, 'Operario no encontrado.');

    $sql = "
        SELECT
            pd.fecha::date AS dia,
            COUNT(pd.id) AS avances,
            COALESCE(SUM(pd.cantidad), 0) AS kg_insertado,
            COALESCE(SUM(pd.cantidad_producida_kg), 0) AS kg_producido,
            STRING_AGG(DISTINCT mo.nombre, ', ') AS moldes,
            STRING_AGG(DISTINCT co.nombre, ', ') AS colores
        FROM produccion pd
        LEFT JOIN molde mo ON mo.id = pd.molde_id
        LEFT JOIN color co ON co.id = pd.color_id
        WHERE pd.operario_id = :operario_id
          AND pd.deleted_at IS NULL
          AND pd.fecha::date BETWEEN :fecha_desde AND :fecha_hasta
        GROUP BY pd.fecha::date
        ORDER BY dia
    ";
    $detalle = executeQuery($conectar, $sql, [
        'operario_id' => $operarioId,
        'fecha_desde' => $fechaDesde,
        'fecha_hasta' => $fechaHasta,
    ]);

    responder(true, 'OK', [
        'operario_nombre' => $operario[0]['nombre_completo'],
        'detalle'         => $detalle,
    ]);
}

// =============================================================================
// REPORTE PRINCIPAL — POR MOLDE
// =============================================================================

/**
 * Ranking de moldes en un periodo. Se agrupa por (molde_id, unico_molde_producto)
 * y NO solo por molde_id, porque un mismo molde puede fabricar más de un
 * producto (js_producto de molde) y eso es información relevante: separa
 * "MOLDE X haciendo el producto A" de "MOLDE X haciendo el producto B" en
 * vez de mezclarlos en una sola fila.
 *
 * "Molde destacado" del periodo = el de mayor total de kg insertados
 * (con total de avances como desempate), igual criterio que en operarios.
 */
function reporteMoldes()
{
    $conectar = conectar_oll_BD();

    $modo             = trim($_POST['modo'] ?? 'dia'); // dia | semana | mes | rango
    $fechaRef         = trim($_POST['fecha'] ?? date('Y-m-d'));
    $fechaDesdeInput  = trim($_POST['fecha_desde'] ?? '');
    $fechaHastaInput  = trim($_POST['fecha_hasta'] ?? '');
    $moldeId          = intval($_POST['molde_id'] ?? 0);
    $operarioId       = intval($_POST['operario_id'] ?? 0);
    $maquinaId        = intval($_POST['maquina_id'] ?? 0);
    $sucursalId       = intval($_POST['sucursal_id'] ?? 0);
    $colorId          = intval($_POST['color_id'] ?? 0);
    $soloConActividad = ($_POST['solo_con_actividad'] ?? '1') !== '0';

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

    // Condiciones que filtran los AVANCES (van en el JOIN, para no excluir
    // moldes que simplemente no tuvieron actividad en el periodo).
    $joinConditions = ["pd.deleted_at IS NULL", "pd.fecha::date BETWEEN :fecha_desde AND :fecha_hasta"];
    $params = ['fecha_desde' => $fechaDesde, 'fecha_hasta' => $fechaHasta];

    if ($operarioId > 0) {
        $joinConditions[] = "pd.operario_id = :operario_id";
        $params['operario_id'] = $operarioId;
    }
    if ($maquinaId > 0) {
        $joinConditions[] = "pd.maquina_id = :maquina_id";
        $params['maquina_id'] = $maquinaId;
    }
    if ($sucursalId > 0) {
        $joinConditions[] = "pd.sucursal = :sucursal_id";
        $params['sucursal_id'] = $sucursalId;
    }
    if ($colorId > 0) {
        $joinConditions[] = "pd.color_id = :color_id";
        $params['color_id'] = $colorId;
    }
    $condicionJoin = implode(' AND ', $joinConditions);

    // Condiciones que filtran los MOLDES mostrados.
    $whereMolde = ["mo.deleted_at IS NULL"];
    if ($moldeId > 0) {
        $whereMolde[] = "mo.id = :molde_id";
        $params['molde_id'] = $moldeId;
    }
    $condicionMolde = implode(' AND ', $whereMolde);

    $having = $soloConActividad ? "HAVING COUNT(pd.id) > 0" : "";

    $sql = "
        SELECT
            mo.id AS molde_id,
            mo.nombre AS molde_nombre,
            pd.unico_molde_producto,
            pr.descripcion AS producto_descripcion,
            COUNT(pd.id) AS total_avances,
            COALESCE(SUM(pd.cantidad), 0) AS total_kg_insertado,
            COALESCE(SUM(pd.cantidad_producida_kg), 0) AS total_kg_producido,
            COUNT(pd.cantidad_producida_kg) AS avances_finalizados,
            ROUND(COALESCE(SUM(pd.cantidad), 0)::numeric / NULLIF(COUNT(pd.id), 0), 2) AS promedio_kg_avance
        FROM molde mo
        LEFT JOIN produccion pd ON pd.molde_id = mo.id AND $condicionJoin
        LEFT JOIN producto pr ON NULLIF(split_part(pd.unico_molde_producto, '-', 2), '')::bigint = pr.id
        WHERE $condicionMolde
        GROUP BY mo.id, mo.nombre, pd.unico_molde_producto, pr.descripcion
        $having
        ORDER BY total_kg_insertado DESC, total_avances DESC, molde_nombre ASC
    ";

    $filas = executeQuery($conectar, $sql, $params);

    $destacado = null;
    foreach ($filas as $f) {
        if ((int) $f['total_avances'] > 0) {
            $destacado = $f;
            break;
        }
    }

    // Serie diaria del periodo (todos los moldes filtrados juntos).
    $sqlSerie = "
        SELECT
            pd.fecha::date AS dia,
            COUNT(pd.id) AS total_avances,
            COALESCE(SUM(pd.cantidad), 0) AS total_kg
        FROM produccion pd
        JOIN molde mo ON mo.id = pd.molde_id
        WHERE $condicionJoin AND $condicionMolde
        GROUP BY pd.fecha::date
        ORDER BY dia
    ";
    $serie = executeQuery($conectar, $sqlSerie, $params);

    $totalKg    = 0;
    $totalAvances = 0;
    $moldesConActividad = 0;
    foreach ($filas as $f) {
        $totalKg      += (float) $f['total_kg_insertado'];
        $totalAvances += (int) $f['total_avances'];
        if ((int) $f['total_avances'] > 0) $moldesConActividad++;
    }

    responder(true, 'OK', [
        'periodo' => [
            'modo'     => $modo,
            'desde'    => $fechaDesde,
            'hasta'    => $fechaHasta,
            'etiqueta' => $etiquetaPeriodo,
        ],
        'resumen' => [
            'total_kg_insertado'   => $totalKg,
            'total_avances'        => $totalAvances,
            'moldes_con_actividad' => $moldesConActividad,
        ],
        'destacado'    => $destacado,
        'filas'        => $filas,
        'serie_diaria' => $serie,
    ]);
}

/**
 * Detalle día por día de UN molde (opcionalmente acotado a un
 * unico_molde_producto puntual, para distinguir el producto exacto cuando
 * el molde fabrica más de uno) dentro de un rango ya calculado por el
 * reporte principal.
 */
function reporteDetalleMolde()
{
    $conectar = conectar_oll_BD();

    $moldeId           = intval($_POST['molde_id'] ?? 0);
    $unicoMoldeProducto = trim($_POST['unico_molde_producto'] ?? '');
    $fechaDesde        = trim($_POST['fecha_desde'] ?? '');
    $fechaHasta        = trim($_POST['fecha_hasta'] ?? '');

    if (!$moldeId) responder(false, 'Debes indicar un molde.');
    if (!$fechaDesde || !$fechaHasta) responder(false, 'Debes indicar el rango de fechas del periodo.');

    $molde = executeQuery($conectar, "SELECT nombre FROM molde WHERE id = :id", ['id' => $moldeId]);
    if (empty($molde)) responder(false, 'Molde no encontrado.');

    $where = [
        "pd.molde_id = :molde_id",
        "pd.deleted_at IS NULL",
        "pd.fecha::date BETWEEN :fecha_desde AND :fecha_hasta",
    ];
    $params = [
        'molde_id'    => $moldeId,
        'fecha_desde' => $fechaDesde,
        'fecha_hasta' => $fechaHasta,
    ];
    if ($unicoMoldeProducto !== '') {
        $where[] = "pd.unico_molde_producto = :unico_molde_producto";
        $params['unico_molde_producto'] = $unicoMoldeProducto;
    }

    $sql = "
        SELECT
            pd.fecha::date AS dia,
            COUNT(pd.id) AS avances,
            COALESCE(SUM(pd.cantidad), 0) AS kg_insertado,
            COALESCE(SUM(pd.cantidad_producida_kg), 0) AS kg_producido,
            STRING_AGG(DISTINCT op.nombre_completo, ', ') AS operarios,
            STRING_AGG(DISTINCT co.nombre, ', ') AS colores
        FROM produccion pd
        LEFT JOIN operario op ON op.id = pd.operario_id
        LEFT JOIN color co ON co.id = pd.color_id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY pd.fecha::date
        ORDER BY dia
    ";
    $detalle = executeQuery($conectar, $sql, $params);

    responder(true, 'OK', [
        'molde_nombre' => $molde[0]['nombre'],
        'detalle'      => $detalle,
    ]);
}

// =============================================================================
// HELPERS
// =============================================================================

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