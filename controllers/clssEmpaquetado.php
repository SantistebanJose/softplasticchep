<?php


ob_start();

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    try {
        controladorEmpaquetado($_POST["accion"]);
    } catch (PDOException $e) {
        error_log("Error de base de datos en clssEmpaquetado.php: " . $e->getMessage());
        responder(false, 'Error de base de datos: ' . $e->getMessage());
    } catch (Throwable $e) {
        error_log("Error inesperado en clssEmpaquetado.php: " . $e->getMessage());
        responder(false, 'Error inesperado en el servidor: ' . $e->getMessage());
    }
}

function controladorEmpaquetado($accion)
{
    switch ($accion) {
        case 'LISTARENSAMBLAJESPARAEMPAQUETADO':
            listarEnsamblajesParaEmpaquetado();
            break;
        case 'LISTARPRODUCCIONESPARAEMPAQUETADO':
            listarProduccionesParaEmpaquetado();
            break;
        case 'BUSCARORIGENESDISPONIBLES':
            buscarOrigenesDisponiblesParaEmpaquetar(intval($_POST['producto_id'] ?? 0));
            break;
        case 'LISTAREMPAQUETADOSPORPRODUCTO':
            listarEmpaquetadosPorProducto(intval($_POST['producto_id'] ?? 0));
            break;
        case 'LISTAREMPAQUETADOS': // LEGACY: solo encuentra registros viejos de un único origen
            listarEmpaquetadosPorOrigen(intval($_POST['ensamblaje_id'] ?? 0), intval($_POST['produccion_id'] ?? 0));
            break;
        case 'LISTARTODOSEMPAQUETADOS':
            listarTodosEmpaquetados();
            break;
        case 'OBTENEREMPAQUETADO':
            obtenerEmpaquetado(intval($_POST['id'] ?? 0));
            break;
        case 'CREAREMPAQUETADO':
            crearEmpaquetado();
            break;
        case 'EDITAREMPAQUETADO':
            editarEmpaquetado();
            break;
        case 'ELIMINAREMPAQUETADO':
            eliminarEmpaquetado(intval($_POST['id'] ?? 0));
            break;
        case 'REACTIVAREMPAQUETADO':
            reactivarEmpaquetado(intval($_POST['id'] ?? 0));
            break;
        case 'BUSCARUNIDADESMEDIDA':
            buscarUnidadesMedida();
            break;
        case 'BUSCAROPERARIOS':
            buscarOperarios();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

function buscarOperarios()
{
    $conectar = conectar_oll_BD();
    $texto = trim($_POST['texto'] ?? '');

    // id fijo de la etapa de empaquetado (tabla etapa: nombre = "EPAQUETADO", id = 3)
    $etapaId = 3;

    $where = [
        "o.activo = true",
        "EXISTS (
            SELECT 1 FROM jsonb_array_elements(COALESCE(o.js_etapas_relacionadas, '[]'::jsonb)) AS et
            WHERE (et->>'etapa_id')::int = :etapa_id
        )"
    ];
    $params = ['etapa_id' => $etapaId];

    if ($texto !== '') {
        $where[] = "LOWER(o.nombre_completo) LIKE LOWER(:texto)";
        $params['texto'] = "%$texto%";
    }

    // 'cargo' ya no es columna de operario -> se resuelve con LEFT JOIN a cargo,
    // mismo patrón que resolverOperariosEnsamblaje() en clssOperario.php.
    $sql = "SELECT o.id, o.nombre_completo, c.nombre AS cargo
            FROM operario o
            LEFT JOIN cargo c ON c.id = o.cargo_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY o.nombre_completo";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['operario' => $result]);
}


function buscarUnidadesMedida()
{
    $conectar = conectar_oll_BD();
    $texto = trim($_POST['texto'] ?? '');

    $where  = ["deleted_at IS NULL"];
    $params = [];
    if ($texto !== '') {
        $where[] = "LOWER(nombre) LIKE LOWER(:texto)";
        $params['texto'] = "%$texto%";
    }

    $sql = "SELECT id, nombre, nombre_corto, unidad_base_id, equivalencia
            FROM unidad_medida
            WHERE " . implode(' AND ', $where) . " ORDER BY nombre LIMIT 100";
    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['unidades' => $result]);
}

// FIX: ahora también trae orígenes tipo "producción" para productos que van
// directo de producción a empaquetado (necesita_ensamblaje = 'no', ej.
// COLGADOR ADULTO, MATAMOSCA CUADRADA). Antes esta función solo miraba la
// tabla ensamblaje, así que esos productos nunca tenían sacos disponibles
// para armar un paquete.
//
// FIX (unidad de origen): ahora devuelve unidad_salida_codigo (DOC, KG, etc.)
// por origen — no necesariamente es la misma unidad del paquete final
// (empaquetado), así que el frontend la muestra por separado en cada saco.
function buscarOrigenesDisponiblesParaEmpaquetar(int $productoId)
{
    if (!$productoId) responder(false, 'Debes indicar el producto.');
    $conectar = conectar_oll_BD();

    $sql = "
        SELECT * FROM (
            SELECT
                'ensamblaje' AS origen_tipo,
                e.id AS origen_id,
                e.cantidad_peso_kg AS cantidad_total,
                e.unidad_salida_id,
                us.nombre_corto AS unidad_salida_codigo,
                col.color_id, col.color_nombre, col.color_hex,
                e.cantidad_peso_kg - COALESCE((
                    SELECT SUM(reo.cantidad) FROM rel_empaquetado_origen reo
                    WHERE reo.ensamblaje_id = e.id AND reo.deleted_at IS NULL
                ), 0) AS disponible
            FROM ensamblaje e
            LEFT JOIN unidad_medida us ON us.id = e.unidad_salida_id
            LEFT JOIN LATERAL (
                SELECT pd.color_id, co.nombre AS color_nombre, co.rgb AS color_hex
                FROM rel_ensamblaje_producto rep
                JOIN produccion pd ON pd.id = rep.molde_produccion_id
                LEFT JOIN color co ON co.id = pd.color_id
                WHERE rep.ensamblaje_id = e.id AND rep.deleted_at IS NULL
                LIMIT 1
            ) col ON true
            WHERE e.producto_id = :producto_id
              AND e.deleted_at IS NULL AND e.fin IS NOT NULL
              AND e.ensamblaje_id_referido IS NULL

            UNION ALL

            SELECT
                'produccion' AS origen_tipo,
                pd.id AS origen_id,
                pd.cantidad_producida_kg AS cantidad_total,
                cfgu.uid AS unidad_salida_id,
                umx.nombre_corto AS unidad_salida_codigo,
                pd.color_id, co.nombre AS color_nombre, co.rgb AS color_hex,
                pd.cantidad_producida_kg - COALESCE((
                    SELECT SUM(reo.cantidad) FROM rel_empaquetado_origen reo
                    WHERE reo.produccion_id = pd.id AND reo.deleted_at IS NULL
                ), 0) AS disponible
            FROM produccion pd
            INNER JOIN producto pr ON pr.id = :producto_id2
            LEFT JOIN molde mo ON mo.id = pd.molde_id
            LEFT JOIN color co ON co.id = pd.color_id
            LEFT JOIN LATERAL (
                SELECT elem.item
                FROM jsonb_array_elements(pr.js_configuracion) AS elem(item)
                WHERE (elem.item->>'molde_id')::bigint = mo.id
                LIMIT 1
            ) x ON true
            LEFT JOIN LATERAL (SELECT COALESCE(pd.js_configuracion_moment, x.item) AS item) cfg ON true
            LEFT JOIN LATERAL (SELECT NULLIF(cfg.item->>'salida_produccion_unidad_medida_id','')::bigint AS uid) cfgu ON true
            LEFT JOIN unidad_medida umx ON umx.id = cfgu.uid
            WHERE split_part(pd.unico_molde_producto, '-', 2)::bigint = :producto_id3
              AND pd.deleted_at IS NULL
              AND pd.enviado_ensamblaje = TRUE
              AND pd.fecha_hora_fin IS NOT NULL
              AND COALESCE(cfg.item->>'necesita_ensamblaje', 'no') = 'no'
              AND NOT EXISTS (
                  SELECT 1 FROM rel_ensamblaje_producto rep_chk
                  WHERE rep_chk.molde_produccion_id = pd.id AND rep_chk.deleted_at IS NULL
              )
        ) t
    ";
    $result = executeQuery($conectar, $sql, [
        'producto_id'  => $productoId,
        'producto_id2' => $productoId,
        'producto_id3' => $productoId,
    ]);

    // Filtra los que ya no tienen nada disponible (redondeo)
    $result = array_values(array_filter($result, fn($r) => (float)$r['disponible'] > 0.0001));

    $unidadEmpaquetado = obtenerUnidadEmpaquetadoProducto($conectar, $productoId);
    $reglas = obtenerReglasEmpaquetadoProducto($conectar, $productoId);

    if ($reglas['conversion_peso_a_unidad'] && $reglas['peso_unitario_g']) {
        foreach ($result as &$r) {
            $r['disponible_kg'] = $r['disponible'];
            $r['disponible'] = floor(($r['disponible'] * 1000) / $reglas['peso_unitario_g']);
        }
        unset($r);
        $result = array_values(array_filter($result, fn($r) => (float)$r['disponible'] > 0.0001));
    }

    responder(true, 'OK', [
        'origenes' => $result,
        'unidad_empaquetado' => $unidadEmpaquetado,
        'reglas_empaquetado' => $reglas,
    ]);
}
// FIX: excluir "ya empaquetados" ahora se calcula contra
// rel_empaquetado_origen (fuente de verdad real del consumo), no contra
// empaquetado.emsamblaje_id (columna legacy que ya no se llena desde
// crearEmpaquetado). El filtro de disponibilidad reemplaza al viejo
// LEFT JOIN empaquetado ee ... WHERE ee.emsamblaje_id IS NULL.
function listarEnsamblajesParaEmpaquetado()
{
    $conectar          = conectar_oll_BD();
    $texto             = trim($_POST['texto'] ?? '');
    $productoId        = trim($_POST['producto_id'] ?? '');
    $soloSinEmpaquetar = ($_POST['solo_sin_empaquetar'] ?? '0') === '1';

    $where  = [
        "e.deleted_at IS NULL",
        "e.fin IS NOT NULL",
        "e.ensamblaje_id_referido IS NULL",
        "(e.cantidad_peso_kg - COALESCE((
            SELECT SUM(reo.cantidad) FROM rel_empaquetado_origen reo
            WHERE reo.ensamblaje_id = e.id AND reo.deleted_at IS NULL
        ), 0)) > 0.0001",
    ];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(p.codigo) LIKE LOWER(:texto) OR LOWER(p.descripcion) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }
    if ($productoId !== '') {
        $where[] = "e.producto_id = :producto_id";
        $params['producto_id'] = $productoId;
    }
    if ($soloSinEmpaquetar) {
        $where[] = "NOT EXISTS (
            SELECT 1 FROM rel_empaquetado_origen reo2
            WHERE reo2.ensamblaje_id = e.id AND reo2.deleted_at IS NULL
        )";
    }

    $sql = "SELECT
                e.id AS ensamblaje_id,
                e.producto_id,
                p.codigo AS producto_codigo,
                p.descripcion AS producto_descripcion,
                e.cantidad_peso_kg,
                e.unidad_salida_id,
                us.nombre_corto AS unidad_salida_codigo,
                us.nombre AS unidad_salida_nombre,
                e.fin,
                o.nombre_completo AS operario_ensamblaje_nombre,
                (
                    SELECT COUNT(DISTINCT reo.empaquetado_id) FROM rel_empaquetado_origen reo
                    WHERE reo.ensamblaje_id = e.id AND reo.deleted_at IS NULL
                ) AS empaquetados_count,
                COALESCE((
                    SELECT SUM(reo.cantidad) FROM rel_empaquetado_origen reo
                    WHERE reo.ensamblaje_id = e.id AND reo.deleted_at IS NULL
                ), 0) AS cantidad_total_empaquetada,
                e.cantidad_peso_kg - COALESCE((
                    SELECT SUM(reo.cantidad) FROM rel_empaquetado_origen reo
                    WHERE reo.ensamblaje_id = e.id AND reo.deleted_at IS NULL
                ), 0) AS cantidad_disponible
            FROM ensamblaje e
            LEFT JOIN producto p ON p.id = e.producto_id
            LEFT JOIN operario o ON o.id = e.operario_ortorgado
            LEFT JOIN unidad_medida us ON us.id = e.unidad_salida_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY e.fin DESC";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['ensamblajes' => $result]);
}
function listarProduccionesParaEmpaquetado()
{
    $conectar          = conectar_oll_BD();
    $texto             = trim($_POST['texto'] ?? '');
    $productoId        = trim($_POST['producto_id'] ?? '');
    $soloSinEmpaquetar = ($_POST['solo_sin_empaquetar'] ?? '0') === '1';

    $where = [
        "pd.deleted_at IS NULL",
        "pd.enviado_ensamblaje = TRUE",
        "pd.fecha_hora_fin IS NOT NULL",
        "COALESCE(cfg.item->>'necesita_ensamblaje', 'no') = 'no'",
        "NOT EXISTS (
            SELECT 1 FROM rel_ensamblaje_producto rep_chk
            WHERE rep_chk.molde_produccion_id = pd.id AND rep_chk.deleted_at IS NULL
        )",
        "(pd.cantidad_producida_kg - COALESCE((
            SELECT SUM(reo.cantidad) FROM rel_empaquetado_origen reo
            WHERE reo.produccion_id = pd.id AND reo.deleted_at IS NULL
        ), 0)) > 0.0001",
    ];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(pr.codigo) LIKE LOWER(:texto) OR LOWER(pr.descripcion) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }
    if ($productoId !== '') {
        $where[] = "pr.id = :producto_id";
        $params['producto_id'] = $productoId;
    }
    if ($soloSinEmpaquetar) {
        $where[] = "NOT EXISTS (
            SELECT 1 FROM rel_empaquetado_origen reo2
            WHERE reo2.produccion_id = pd.id AND reo2.deleted_at IS NULL
        )";
    }

    $sql = "
        SELECT
            pd.id AS produccion_id,
            pr.id AS producto_id,
            pr.codigo AS producto_codigo,
            pr.descripcion AS producto_descripcion,
            pd.cantidad_producida_kg,
            pd.fecha_hora_fin,
            mo.nombre AS molde_nombre,
            co.nombre AS color_nombre,
            op.nombre_completo AS operario_produccion_nombre,
            umx.nombre_corto AS unidad_salida_codigo,
            (
                SELECT COUNT(DISTINCT reo.empaquetado_id) FROM rel_empaquetado_origen reo
                WHERE reo.produccion_id = pd.id AND reo.deleted_at IS NULL
            ) AS empaquetados_count,
            COALESCE((
                SELECT SUM(reo.cantidad) FROM rel_empaquetado_origen reo
                WHERE reo.produccion_id = pd.id AND reo.deleted_at IS NULL
            ), 0) AS cantidad_total_empaquetada,
            pd.cantidad_producida_kg - COALESCE((
                SELECT SUM(reo.cantidad) FROM rel_empaquetado_origen reo
                WHERE reo.produccion_id = pd.id AND reo.deleted_at IS NULL
            ), 0) AS cantidad_disponible
        FROM produccion pd
        INNER JOIN producto pr ON pr.id = split_part(pd.unico_molde_producto, '-', 2)::bigint
        LEFT JOIN molde mo ON mo.id = pd.molde_id
        LEFT JOIN color co ON co.id = pd.color_id
        LEFT JOIN operario op ON op.id = pd.operario_id
        LEFT JOIN LATERAL (
            SELECT elem.item
            FROM jsonb_array_elements(pr.js_configuracion) AS elem(item)
            WHERE (elem.item->>'molde_id')::bigint = mo.id
            LIMIT 1
        ) x ON true
        LEFT JOIN LATERAL (SELECT COALESCE(pd.js_configuracion_moment, x.item) AS item) cfg ON true
        LEFT JOIN LATERAL (SELECT NULLIF(cfg.item->>'salida_produccion_unidad_medida_id','')::bigint AS uid) cfgu ON true
        LEFT JOIN unidad_medida umx ON umx.id = cfgu.uid
        WHERE " . implode(' AND ', $where) . "
        ORDER BY pd.fecha_hora_fin DESC
    ";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['producciones' => $result]);
}


// =============================================================================
// OPERARIOS (múltiples por registro de empaquetado)
// =============================================================================

// Valida los ids de operario contra la tabla operario: deben estar activos y
// pertenecer a la etapa de empaquetado (id 3, mismo id fijo que usa
// buscarOperarios()). Devuelve el array denormalizado listo para js_operarios.
// Lanza Exception si algún id no existe / está inactivo / no pertenece a la etapa.
function resolverOperariosEmpaquetado($conectar, array $idsOperario): array
{
    $idsOperario = array_values(array_unique(array_map('intval', $idsOperario)));
    $idsOperario = array_filter($idsOperario, fn($id) => $id > 0);
    if (empty($idsOperario)) return [];

    $placeholders = [];
    $params = [];
    foreach ($idsOperario as $i => $id) {
        $key = "op{$i}";
        $placeholders[] = ":{$key}";
        $params[$key] = $id;
    }

    $etapaId = 3; // EPAQUETADO
    $sql = "SELECT id, nombre_completo FROM operario
            WHERE id IN (" . implode(',', $placeholders) . ")
              AND activo = true
              AND EXISTS (
                  SELECT 1 FROM jsonb_array_elements(COALESCE(js_etapas_relacionadas, '[]'::jsonb)) AS et
                  WHERE (et->>'etapa_id')::int = {$etapaId}
              )";
    $result = executeQuery($conectar, $sql, $params);

    if (count($result) !== count($idsOperario)) {
        throw new Exception('Uno o más operarios seleccionados no existen, están inactivos o no están asignados a la etapa de empaquetado.');
    }

    return array_map(fn($o) => [
        'operario_id'     => (int)$o['id'],
        'nombre_completo' => $o['nombre_completo'],
    ], $result);
}

// executeQuery devuelve columnas jsonb como string JSON crudo, no como
// arreglo PHP. Mismo patrón que decodificarJsonFilas() en clssOperario.php.
// Se aplica a cualquier resultado que incluya emp.js_operarios en el SELECT.
function decodificarJsOperarios(array $filas): array
{
    foreach ($filas as &$fila) {
        if (isset($fila['js_operarios']) && is_string($fila['js_operarios'])) {
            $fila['js_operarios'] = json_decode($fila['js_operarios'], true) ?: [];
        } elseif (!isset($fila['js_operarios'])) {
            $fila['js_operarios'] = [];
        }
    }
    unset($fila);
    return $filas;
}

// =============================================================================
// EMPAQUETADO
// =============================================================================

// LEGACY: solo encuentra registros viejos de un único origen (los que
// tenían emsamblaje_id/produccion_id llenos en la cabecera). Con el modelo
// de mezcla, un registro nuevo puede consumir de varios orígenes a la vez
// y NO aparecerá aquí. Usa LISTAREMPAQUETADOSPORPRODUCTO para el flujo
// actual; esta se conserva solo para auditar un origen puntual.
function listarEmpaquetadosPorOrigen(int $ensamblajeId, int $produccionId)
{
    if (!$ensamblajeId && !$produccionId) responder(false, 'Debes indicar el ensamblaje o la producción de origen.');
    $conectar = conectar_oll_BD();

    $condicion = $ensamblajeId ? "emp.emsamblaje_id = :origen_id" : "emp.produccion_id = :origen_id";
    $origenId  = $ensamblajeId ?: $produccionId;

    $result = executeQuery($conectar, "
        SELECT
            emp.id, emp.producto_id, emp.emsamblaje_id, emp.produccion_id, emp.unidad_medida,
            emp.cantidad_tota, emp.js_cantidades,
            emp.operario_id, emp.js_operarios, emp.pasado_venta, emp.venta_id_ref,
            emp.created_at, emp.update_at,
            um.nombre AS unidad_nombre, um.nombre_corto AS unidad_corto,
            um.equivalencia, um.unidad_base_id,
            ub.nombre_corto AS unidad_base_corto,
            CASE WHEN um.unidad_base_id IS NOT NULL
                 THEN emp.cantidad_tota * um.equivalencia
                 ELSE NULL END AS cantidad_tota_en_base,
            op.nombre_completo AS operario_nombre,
            (
                SELECT jsonb_agg(jsonb_build_object(
                    'origen_tipo', CASE WHEN reo.ensamblaje_id IS NOT NULL THEN 'ensamblaje' ELSE 'produccion' END,
                    'origen_id', COALESCE(reo.ensamblaje_id, reo.produccion_id),
                    'color_id', reo.color_id, 'color_nombre', co2.nombre, 'color_hex', co2.rgb,
                    'cantidad', reo.cantidad
                ))
                FROM rel_empaquetado_origen reo
                LEFT JOIN color co2 ON co2.id = reo.color_id
                WHERE reo.empaquetado_id = emp.id AND reo.deleted_at IS NULL
            ) AS js_origenes
        FROM empaquetado emp
        LEFT JOIN unidad_medida um ON um.id = emp.unidad_medida
        LEFT JOIN unidad_medida ub ON ub.id = um.unidad_base_id
        LEFT JOIN operario op ON op.id = emp.operario_id
        WHERE $condicion AND emp.deleted_at IS NULL
        ORDER BY emp.created_at DESC
    ", ['origen_id' => $origenId]);

    responder(true, 'OK', ['empaquetados' => decodificarJsOperarios($result)]);
}

// Historial real de un PRODUCTO (fuente de verdad del flujo actual): trae
// todos los registros de empaquetado sin importar de cuántos/cuáles
// orígenes tomó cada uno.
function listarEmpaquetadosPorProducto(int $productoId)
{
    if (!$productoId) responder(false, 'ID de producto inválido.');
    $conectar = conectar_oll_BD();

    $result = executeQuery($conectar, "
        SELECT
            emp.id, emp.producto_id, emp.unidad_medida,
            emp.cantidad_tota, emp.js_cantidades,
            emp.operario_id, emp.js_operarios, emp.pasado_venta, emp.venta_id_ref,
            emp.created_at, emp.update_at,
            um.nombre AS unidad_nombre, um.nombre_corto AS unidad_corto,
            um.equivalencia, um.unidad_base_id,
            ub.nombre_corto AS unidad_base_corto,
            CASE WHEN um.unidad_base_id IS NOT NULL
                 THEN emp.cantidad_tota * um.equivalencia
                 ELSE NULL END AS cantidad_tota_en_base,
            op.nombre_completo AS operario_nombre,
            (
                SELECT jsonb_agg(jsonb_build_object(
                    'origen_tipo', CASE WHEN reo.ensamblaje_id IS NOT NULL THEN 'ensamblaje' ELSE 'produccion' END,
                    'origen_id', COALESCE(reo.ensamblaje_id, reo.produccion_id),
                    'color_id', reo.color_id, 'color_nombre', co2.nombre, 'color_hex', co2.rgb,
                    'cantidad', reo.cantidad
                ))
                FROM rel_empaquetado_origen reo
                LEFT JOIN color co2 ON co2.id = reo.color_id
                WHERE reo.empaquetado_id = emp.id AND reo.deleted_at IS NULL
            ) AS js_origenes
        FROM empaquetado emp
        LEFT JOIN unidad_medida um ON um.id = emp.unidad_medida
        LEFT JOIN unidad_medida ub ON ub.id = um.unidad_base_id
        LEFT JOIN operario op ON op.id = emp.operario_id
        WHERE emp.producto_id = :producto_id AND emp.deleted_at IS NULL
        ORDER BY emp.created_at DESC
    ", ['producto_id' => $productoId]);

    responder(true, 'OK', ['empaquetados' => decodificarJsOperarios($result)]);
}

// Listado GENERAL para la tabla que vive debajo de los grids.
// FIX: origen_tipo ahora se deriva de rel_empaquetado_origen (soporta
// 'mixto' cuando un registro combina ensamblaje + producción), con
// fallback a las columnas legacy solo para registros sin filas nuevas.
function listarTodosEmpaquetados()
{
    $conectar   = conectar_oll_BD();
    $texto      = trim($_POST['texto'] ?? '');
    $estado     = trim($_POST['estado'] ?? ''); // '', 'disponible', 'vendido'
    $fechaDesde = trim($_POST['fecha_desde'] ?? '');
    $fechaHasta = trim($_POST['fecha_hasta'] ?? '');

    $where  = ["emp.deleted_at IS NULL"];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(p.codigo) LIKE LOWER(:texto) OR LOWER(p.descripcion) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }
    if ($estado === 'disponible') {
        $where[] = "emp.pasado_venta IS NULL";
    } elseif ($estado === 'vendido') {
        $where[] = "emp.pasado_venta IS NOT NULL";
    }
    if ($fechaDesde !== '') {
        $where[] = "emp.created_at >= :fecha_desde";
        $params['fecha_desde'] = $fechaDesde;
    }
    if ($fechaHasta !== '') {
        $where[] = "emp.created_at <= :fecha_hasta";
        $params['fecha_hasta'] = $fechaHasta . ' 23:59:59';
    }

    $sql = "SELECT
            emp.id, emp.emsamblaje_id, emp.produccion_id, emp.producto_id,
            p.codigo AS producto_codigo, p.descripcion AS producto_descripcion,
            emp.cantidad_tota, emp.js_cantidades,
            emp.operario_id, emp.js_operarios, op.nombre_completo AS operario_nombre,
            su.nombre AS sucursal_nombre,
            emp.pasado_venta, emp.venta_id_ref,
            emp.created_at, emp.update_at,
            um.nombre_corto AS unidad_corto, um.equivalencia, um.unidad_base_id,
            ub.nombre_corto AS unidad_base_corto,
            CASE WHEN um.unidad_base_id IS NOT NULL
                THEN emp.cantidad_tota * um.equivalencia
                ELSE NULL END AS cantidad_tota_en_base,
            (
                SELECT jsonb_agg(jsonb_build_object(
                    'origen_tipo', CASE WHEN reo.ensamblaje_id IS NOT NULL THEN 'ensamblaje' ELSE 'produccion' END,
                    'origen_id', COALESCE(reo.ensamblaje_id, reo.produccion_id),
                    'color_id', reo.color_id, 'color_nombre', co2.nombre, 'color_hex', co2.rgb,
                    'cantidad', reo.cantidad
                ))
                FROM rel_empaquetado_origen reo
                LEFT JOIN color co2 ON co2.id = reo.color_id
                WHERE reo.empaquetado_id = emp.id AND reo.deleted_at IS NULL
            ) AS js_origenes,
            COALESCE((
                SELECT CASE
                    WHEN COUNT(*) FILTER (WHERE reo.ensamblaje_id IS NOT NULL) > 0
                     AND COUNT(*) FILTER (WHERE reo.produccion_id IS NOT NULL) > 0
                        THEN 'mixto'
                    WHEN COUNT(*) FILTER (WHERE reo.ensamblaje_id IS NOT NULL) > 0
                        THEN 'ensamblaje'
                    WHEN COUNT(*) FILTER (WHERE reo.produccion_id IS NOT NULL) > 0
                        THEN 'produccion'
                    ELSE NULL
                END
                FROM rel_empaquetado_origen reo
                WHERE reo.empaquetado_id = emp.id AND reo.deleted_at IS NULL
            ), CASE WHEN emp.emsamblaje_id IS NOT NULL THEN 'ensamblaje'
                    WHEN emp.produccion_id IS NOT NULL THEN 'produccion'
                    ELSE NULL END) AS origen_tipo
        FROM empaquetado emp
        LEFT JOIN producto p ON p.id = emp.producto_id
        LEFT JOIN operario op ON op.id = emp.operario_id
        LEFT JOIN sucursal su ON su.id = emp.sucursal
        LEFT JOIN unidad_medida um ON um.id = emp.unidad_medida
        LEFT JOIN unidad_medida ub ON ub.id = um.unidad_base_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY emp.created_at DESC
        LIMIT 300";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['empaquetados' => decodificarJsOperarios($result)]);
}

function obtenerEmpaquetado(int $id)
{
    if (!$id) responder(false, 'ID inválido.');
    $conectar = conectar_oll_BD();

    $result = executeQuery($conectar, "
        SELECT emp.*, um.nombre_corto AS unidad_corto,
            (
                SELECT jsonb_agg(jsonb_build_object(
                    'origen_tipo', CASE WHEN reo.ensamblaje_id IS NOT NULL THEN 'ensamblaje' ELSE 'produccion' END,
                    'origen_id', COALESCE(reo.ensamblaje_id, reo.produccion_id),
                    'color_id', reo.color_id, 'color_nombre', co2.nombre, 'color_hex', co2.rgb,
                    'cantidad', reo.cantidad
                ))
                FROM rel_empaquetado_origen reo
                LEFT JOIN color co2 ON co2.id = reo.color_id
                WHERE reo.empaquetado_id = emp.id AND reo.deleted_at IS NULL
            ) AS js_origenes
        FROM empaquetado emp
        LEFT JOIN unidad_medida um ON um.id = emp.unidad_medida
        WHERE emp.id = :id AND emp.deleted_at IS NULL
    ", ['id' => $id]);

    if (empty($result)) responder(false, 'Registro de empaquetado no encontrado o inactivo.');
    $filas = decodificarJsOperarios($result);
    responder(true, 'OK', ['empaquetado' => $filas[0]]);
}

function crearEmpaquetado()
{
    $conectar = conectar_oll_BD();
    $productoId   = intval($_POST['producto_id'] ?? 0);
    $sucursalId   = !empty($_POST['sucursal_id']) ? intval($_POST['sucursal_id']) : null;

    if (!$productoId) responder(false, 'Debes indicar el producto.');

    // Varios operarios pueden participar en un mismo registro de empaquetado.
    // Llega como JSON: "[3,7,12]" (ids seleccionados en la estación de armado).
    $operariosInput = json_decode($_POST['operarios'] ?? '[]', true);
    if (!is_array($operariosInput) || empty($operariosInput)) {
        responder(false, 'Debes indicar al menos un operario.');
    }
    try {
        $operariosResueltos = resolverOperariosEmpaquetado($conectar, $operariosInput);
    } catch (Throwable $e) {
        responder(false, $e->getMessage());
    }
    $operarioIdPrimario = $operariosResueltos[0]['operario_id']; // legacy: columna operario_id sigue NOT NULL

    $producto = executeQuery($conectar, "SELECT id FROM producto WHERE id = :id AND activo = true", ['id' => $productoId]);
    if (empty($producto)) responder(false, 'El producto indicado no existe o está inactivo.');

    $unidadEmpaquetado = obtenerUnidadEmpaquetadoProducto($conectar, $productoId);
    if (!$unidadEmpaquetado) {
        responder(false, 'Este producto no tiene configurada su unidad de empaquetado ("Salida en Empaquetado"). Configúrala en el módulo de Productos antes de empaquetar.');
    }
    $unidadMedida = (int) $unidadEmpaquetado['id'];
    $equivalenciaCapacidad = (float)($unidadEmpaquetado['equivalencia'] ?? 0);

    if ($sucursalId !== null) {
        $suc = executeQuery($conectar, "SELECT id FROM sucursal WHERE id = :id AND delete_at IS NULL", ['id' => $sucursalId]);
        if (empty($suc)) responder(false, 'La sucursal indicada no existe o está inactiva.');
    }

    $reglas = obtenerReglasEmpaquetadoProducto($conectar, $productoId);
    if ($reglas['conversion_peso_a_unidad'] && empty($reglas['peso_unitario_g'])) {
        responder(false, 'Este producto requiere conversión de kg a unidades para empaquetar, pero no tiene configurado el "Peso unitario (g)". Complétalo en el módulo de Productos antes de continuar.');
    }

    // Productos con mezcla al azar (ej. Pinza Palanita): flujo distinto,
    // ver crearEmpaquetadoMezcla(). No usan bultos con color manual porque
    // la máquina mezcla los colores sin que el operario pueda controlarlo.
    if ($reglas['conversion_peso_a_unidad']) {
        crearEmpaquetadoMezcla($conectar, $productoId, $operarioIdPrimario, $operariosResueltos, $sucursalId, $unidadMedida, $unidadEmpaquetado, $reglas);
        return;
    }

    $bultosJson = trim($_POST['bultos'] ?? '[]');
    $bultosEntrada = json_decode($bultosJson, true);
    if (!is_array($bultosEntrada) || empty($bultosEntrada)) {
        responder(false, 'Debes registrar al menos un bulto.');
    }

    $conectar->beginTransaction();
    try {
        $bultosLimpios    = [];
        $consumoPorOrigen = [];
        $cantidadTotal    = 0;

        foreach ($bultosEntrada as $idxBulto => $b) {
            $detalle = is_array($b['colores'] ?? null) ? $b['colores'] : [];
            $totalBulto = 0;
            $detalleLimpio = [];

            foreach ($detalle as $d) {
                $cant = floatval($d['cantidad'] ?? 0);
                if ($cant <= 0) continue;

                if ($reglas['granularidad_color'] > 1 && fmod($cant, $reglas['granularidad_color']) > 0.0001) {
                    throw new Exception(
                        "La cantidad de color debe ser múltiplo de {$reglas['granularidad_color']} (docena) — recibido: $cant."
                    );
                }

                $tipo = ($d['origen_tipo'] ?? '') === 'produccion' ? 'produccion' : 'ensamblaje';
                $oid  = intval($d['origen_id'] ?? 0);
                if (!$oid) continue;

                $totalBulto += $cant;

                $detalleLimpio[] = [
                    'origen_tipo' => $tipo, 'origen_id' => $oid,
                    'color_id' => $d['color_id'] ?? null, 'color_nombre' => $d['color_nombre'] ?? null,
                    'cantidad' => $cant,
                ];
                $clave = "$tipo:$oid";
                if (!isset($consumoPorOrigen[$clave])) {
                    $consumoPorOrigen[$clave] = ['cantidad' => 0, 'color_id' => $d['color_id'] ?? null];
                }
                $consumoPorOrigen[$clave]['cantidad'] += $cant;
            }
            if ($totalBulto <= 0) continue;

            if ($reglas['modo_distribucion_color'] === 'uniforme') {
                $nColores = count($detalleLimpio);
                if ($nColores > 1 && fmod($totalBulto, $nColores) < 0.0001) {
                    $esperadoPorColor = $totalBulto / $nColores;
                    foreach ($detalleLimpio as $linea) {
                        if (abs($linea['cantidad'] - $esperadoPorColor) > 0.0001) {
                            throw new Exception(
                                "El bulto " . ($idxBulto + 1) . " debe repartirse parejo entre colores ($esperadoPorColor c/u), "
                                . "pero \"{$linea['color_nombre']}\" tiene {$linea['cantidad']}."
                            );
                        }
                    }
                }
            }

            if ($equivalenciaCapacidad > 0 && $totalBulto > $equivalenciaCapacidad + 0.0001) {
                throw new Exception(
                    "El bulto " . ($idxBulto + 1) . " suma " . $totalBulto
                    . ", pero la capacidad de {$unidadEmpaquetado['nombre']} es {$equivalenciaCapacidad}. "
                    . "Divide el excedente en otro bulto."
                );
            }

            $bultosLimpios[] = ['cantidad' => $totalBulto, 'colores' => $detalleLimpio];
            $cantidadTotal += $totalBulto;
        }
        if (empty($bultosLimpios)) throw new Exception('No hay bultos válidos: agrega al menos un color con cantidad mayor a 0 en cada bulto.');

        $cantidadTotalEnUnidad = $equivalenciaCapacidad > 0
            ? round($cantidadTotal / $equivalenciaCapacidad, 4)
            : $cantidadTotal;

        foreach ($consumoPorOrigen as $clave => $info) {
            [$tipo, $oid] = explode(':', $clave);
            if ($tipo === 'ensamblaje') {
                $fila = executeQuery($conectar, "
                    SELECT e.cantidad_peso_kg - COALESCE((SELECT SUM(cantidad) FROM rel_empaquetado_origen
                        WHERE ensamblaje_id = e.id AND deleted_at IS NULL), 0) AS disponible
                    FROM ensamblaje e WHERE e.id = :id AND e.deleted_at IS NULL AND e.fin IS NOT NULL
                ", ['id' => $oid]);
            } else {
                $fila = executeQuery($conectar, "
                    SELECT pd.cantidad_producida_kg - COALESCE((SELECT SUM(cantidad) FROM rel_empaquetado_origen
                        WHERE produccion_id = pd.id AND deleted_at IS NULL), 0) AS disponible
                    FROM produccion pd WHERE pd.id = :id AND pd.deleted_at IS NULL
                ", ['id' => $oid]);
            }
            if (empty($fila)) throw new Exception("El origen $tipo #$oid ya no existe, está inactivo o no ha finalizado.");
            if ($info['cantidad'] > (float)$fila[0]['disponible'] + 0.0001) {
                throw new Exception("No queda suficiente disponible en $tipo #$oid (disponible: {$fila[0]['disponible']}, pedido: {$info['cantidad']}).");
            }
        }

        $operariosTexto = implode(', ', array_column($operariosResueltos, 'nombre_completo'));
        $movimiento   = obtenerMovimientoSesionEmp('crear', [[
            'campo' => 'Empaquetado', 'valor_antes' => '(nuevo)',
            'valor_despues' => count($bultosLimpios) . " bulto(s), total $cantidadTotal, " . count($consumoPorOrigen) . " origen(es), operarios: $operariosTexto",
        ]]);
        $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        $nuevo = executeQuery($conectar, "
            INSERT INTO empaquetado (producto_id, unidad_medida, operario_id, js_operarios, sucursal,
                cantidad_tota, js_cantidades, created_at, js_session, js_historial)
            VALUES (:producto_id, :unidad_medida, :operario_id, :js_operarios, :sucursal_id,
                :cantidad_tota, :js_cantidades, NOW(), :js_session, :js_historial)
            RETURNING id
        ", [
            'producto_id'   => $productoId, 'unidad_medida' => $unidadMedida,
            'operario_id'   => $operarioIdPrimario,
            'js_operarios'  => json_encode($operariosResueltos, JSON_UNESCAPED_UNICODE),
            'sucursal_id'   => $sucursalId,
            'cantidad_tota' => $cantidadTotalEnUnidad,
            'js_cantidades' => json_encode($bultosLimpios, JSON_UNESCAPED_UNICODE),
            'js_session'    => $js_session,
            'js_historial'  => $js_historial,
        ]);
        $empaquetadoId = $nuevo[0]['id'] ?? null;
        if (!$empaquetadoId) throw new Exception('No se pudo crear el registro de empaquetado.');

        foreach ($consumoPorOrigen as $clave => $info) {
            [$tipo, $oid] = explode(':', $clave);
            executeNonQuery($conectar, "
                INSERT INTO rel_empaquetado_origen
                    (empaquetado_id, ensamblaje_id, produccion_id, color_id, cantidad, created_at)
                VALUES (:eid, :ens, :prod, :color_id, :cant, NOW())
            ", [
                'eid'      => $empaquetadoId,
                'ens'      => $tipo === 'ensamblaje' ? $oid : null,
                'prod'     => $tipo === 'produccion' ? $oid : null,
                'color_id' => $info['color_id'],
                'cant'     => $info['cantidad'],
            ]);
        }

        $conectar->commit();
        responder(true, 'Empaquetado registrado correctamente.', ['id' => $empaquetadoId]);
    } catch (Throwable $e) {
        $conectar->rollBack();
        error_log("Error creando empaquetado: " . $e->getMessage());
        responder(false, 'No se pudo guardar: ' . $e->getMessage());
    }
}

/**
 * Flujo de empaquetado para productos que se mezclan al azar en máquina
 * (ej. Pinza Palanita): el operario registra cuántos KG de cada
 * saco/color entraron a la mezcla (consumo real, exacto) y cuántas
 * bolsas salieron de la máquina. NO se inventa un reparto de colores por
 * bolsa — eso lo decide la máquina y nadie lo cuenta a mano.
 *
 * $operarioIdPrimario y $operariosResueltos vienen ya resueltos desde
 * crearEmpaquetado() (múltiples operarios pueden participar).
 */
function crearEmpaquetadoMezcla($conectar, int $productoId, int $operarioIdPrimario, array $operariosResueltos, ?int $sucursalId, int $unidadMedida, array $unidadEmpaquetado, array $reglas): void
{
    $origenesJson     = trim($_POST['mezcla_origenes'] ?? '[]');
    $bolsasProducidas = floatval($_POST['bolsas_producidas'] ?? 0);

    if ($bolsasProducidas <= 0) responder(false, 'Debes indicar cuántas bolsas se produjeron.');
    if (floor($bolsasProducidas) != $bolsasProducidas) responder(false, 'La cantidad de bolsas debe ser un número entero.');

    $origenesEntrada = json_decode($origenesJson, true);
    if (!is_array($origenesEntrada) || empty($origenesEntrada)) {
        responder(false, 'Debes indicar al menos un saco/color usado en la mezcla, con su cantidad en kg.');
    }

    $conectar->beginTransaction();
    try {
        $consumoPorOrigen = []; // "tipo:id" => ['cantidad_kg', 'color_id', 'color_nombre']
        $kgTotal = 0;

        foreach ($origenesEntrada as $o) {
            $cantKg = floatval($o['cantidad_kg'] ?? 0);
            if ($cantKg <= 0) continue;
            $tipo = ($o['origen_tipo'] ?? '') === 'produccion' ? 'produccion' : 'ensamblaje';
            $oid  = intval($o['origen_id'] ?? 0);
            if (!$oid) continue;

            $clave = "$tipo:$oid";
            if (!isset($consumoPorOrigen[$clave])) {
                $consumoPorOrigen[$clave] = [
                    'cantidad_kg'  => 0,
                    'color_id'     => $o['color_id'] ?? null,
                    'color_nombre' => $o['color_nombre'] ?? null,
                ];
            }
            $consumoPorOrigen[$clave]['cantidad_kg'] += $cantKg;
            $kgTotal += $cantKg;
        }
        if (empty($consumoPorOrigen)) throw new Exception('No hay orígenes válidos en la mezcla: agrega al menos un color con kg mayor a 0.');

        // Revalida disponibilidad real en KG (unidad nativa del origen —
        // sin conversión, porque el operario ya ingresó directo en kg).
        foreach ($consumoPorOrigen as $clave => $info) {
            [$tipo, $oid] = explode(':', $clave);
            if ($tipo === 'ensamblaje') {
                $fila = executeQuery($conectar, "
                    SELECT e.cantidad_peso_kg - COALESCE((SELECT SUM(cantidad) FROM rel_empaquetado_origen
                        WHERE ensamblaje_id = e.id AND deleted_at IS NULL), 0) AS disponible
                    FROM ensamblaje e WHERE e.id = :id AND e.deleted_at IS NULL AND e.fin IS NOT NULL
                ", ['id' => $oid]);
            } else {
                $fila = executeQuery($conectar, "
                    SELECT pd.cantidad_producida_kg - COALESCE((SELECT SUM(cantidad) FROM rel_empaquetado_origen
                        WHERE produccion_id = pd.id AND deleted_at IS NULL), 0) AS disponible
                    FROM produccion pd WHERE pd.id = :id AND pd.deleted_at IS NULL
                ", ['id' => $oid]);
            }
            if (empty($fila)) throw new Exception("El origen $tipo #$oid ya no existe, está inactivo o no ha finalizado.");
            if ($info['cantidad_kg'] > (float)$fila[0]['disponible'] + 0.0001) {
                throw new Exception("No queda suficiente disponible en $tipo #$oid (disponible: {$fila[0]['disponible']} kg, pedido: {$info['cantidad_kg']} kg).");
            }
        }

        // Cálculo teórico (informativo, no bloqueante): sirve para
        // detectar mermas anormales de la máquina, no para invalidar el registro.
        $unidadesTeoricas = ($kgTotal * 1000) / $reglas['peso_unitario_g'];
        $bolsasTeoricas   = $unidadesTeoricas / 144;
        $diferenciaPct    = $bolsasTeoricas > 0 ? abs($bolsasProducidas - $bolsasTeoricas) / $bolsasTeoricas * 100 : null;

        $operariosTexto = implode(', ', array_column($operariosResueltos, 'nombre_completo'));
        $movimiento = obtenerMovimientoSesionEmp('crear_mezcla', [[
            'campo' => 'Empaquetado (mezcla)', 'valor_antes' => '(nuevo)',
            'valor_despues' => "$bolsasProducidas bolsas producidas, {$kgTotal} kg mezclados de " . count($consumoPorOrigen) . " origen(es), operarios: $operariosTexto"
                . ($diferenciaPct !== null ? " — teórico: " . round($bolsasTeoricas, 1) . " bolsas, diferencia: " . round($diferenciaPct, 1) . "%" : ""),
        ]]);
        $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        $mezclaDetalle = [[
            'cantidad' => $bolsasProducidas,
            'colores'  => array_values(array_map(function ($clave, $info) {
                [$tipo, $oid] = explode(':', $clave);
                return [
                    'origen_tipo' => $tipo, 'origen_id' => (int)$oid,
                    'color_id' => $info['color_id'], 'color_nombre' => $info['color_nombre'],
                    'cantidad_kg' => $info['cantidad_kg'],
                ];
            }, array_keys($consumoPorOrigen), $consumoPorOrigen)),
            'kg_total_mezclados'        => $kgTotal,
            'bolsas_teoricas_estimadas' => round($bolsasTeoricas, 2),
        ]];

        $nuevo = executeQuery($conectar, "
            INSERT INTO empaquetado (producto_id, unidad_medida, operario_id, js_operarios, sucursal,
                cantidad_tota, js_cantidades, created_at, js_session, js_historial)
            VALUES (:producto_id, :unidad_medida, :operario_id, :js_operarios, :sucursal_id,
                :cantidad_tota, :js_cantidades, NOW(), :js_session, :js_historial)
            RETURNING id
        ", [
            'producto_id'   => $productoId, 'unidad_medida' => $unidadMedida,
            'operario_id'   => $operarioIdPrimario,
            'js_operarios'  => json_encode($operariosResueltos, JSON_UNESCAPED_UNICODE),
            'sucursal_id'   => $sucursalId,
            'cantidad_tota' => $bolsasProducidas,
            'js_cantidades' => json_encode($mezclaDetalle, JSON_UNESCAPED_UNICODE),
            'js_session'    => $js_session,
            'js_historial'  => $js_historial,
        ]);
        $empaquetadoId = $nuevo[0]['id'] ?? null;
        if (!$empaquetadoId) throw new Exception('No se pudo crear el registro de empaquetado.');

        foreach ($consumoPorOrigen as $clave => $info) {
            [$tipo, $oid] = explode(':', $clave);
            executeNonQuery($conectar, "
                INSERT INTO rel_empaquetado_origen
                    (empaquetado_id, ensamblaje_id, produccion_id, color_id, cantidad, created_at)
                VALUES (:eid, :ens, :prod, :color_id, :cant, NOW())
            ", [
                'eid'      => $empaquetadoId,
                'ens'      => $tipo === 'ensamblaje' ? $oid : null,
                'prod'     => $tipo === 'produccion' ? $oid : null,
                'color_id' => $info['color_id'],
                'cant'     => $info['cantidad_kg'], // en KG, unidad real del origen
            ]);
        }

        $conectar->commit();
        responder(true, 'Empaquetado (mezcla) registrado correctamente.', [
            'id' => $empaquetadoId,
            'bolsas_teoricas_estimadas' => round($bolsasTeoricas, 2),
            'diferencia_pct' => $diferenciaPct !== null ? round($diferenciaPct, 1) : null,
        ]);
    } catch (Throwable $e) {
        $conectar->rollBack();
        error_log("Error creando empaquetado (mezcla): " . $e->getMessage());
        responder(false, 'No se pudo guardar: ' . $e->getMessage());
    }
}

// Edición LIMITADA a cabecera (unidad_medida, operarios, sucursal). NUNCA
// toca rel_empaquetado_origen ni la mezcla de colores. Corregir la mezcla
// = eliminar este registro (libera el consumo) y crear uno nuevo.
function editarEmpaquetado()
{
    $conectar = conectar_oll_BD();

    $id           = intval($_POST['id'] ?? 0);
    $sucursalId   = !empty($_POST['sucursal_id']) ? intval($_POST['sucursal_id']) : null;
    $unidadMedida = intval($_POST['unidad_medida'] ?? 0);

    if (!$id) responder(false, 'ID inválido.');
    if (!$sucursalId) responder(false, 'Debes indicar la sucursal.');
    if (!$unidadMedida) responder(false, 'Debes indicar la unidad de medida.');

    // Varios operarios pueden participar en un mismo registro de empaquetado.
    // Llega como JSON: "[3,7,12]" (ids seleccionados en el modal de edición).
    $operariosInput = json_decode($_POST['operarios'] ?? '[]', true);
    if (!is_array($operariosInput) || empty($operariosInput)) {
        responder(false, 'Debes indicar al menos un operario.');
    }
    try {
        $operariosResueltos = resolverOperariosEmpaquetado($conectar, $operariosInput);
    } catch (Throwable $e) {
        responder(false, $e->getMessage());
    }
    $operarioIdPrimario = $operariosResueltos[0]['operario_id']; // legacy: columna operario_id sigue NOT NULL

    $actual = executeQuery($conectar, "SELECT * FROM empaquetado WHERE id = :id AND deleted_at IS NULL", ['id' => $id]);
    if (empty($actual)) responder(false, 'Registro de empaquetado no encontrado o inactivo.');
    if (!empty($actual[0]['pasado_venta'])) {
        responder(false, 'Este registro ya pasó a venta y no se puede editar.');
    }

    if ($unidadMedida != $actual[0]['unidad_medida']) {
        $tieneConsumo = executeQuery(
            $conectar,
            "SELECT id FROM rel_empaquetado_origen WHERE empaquetado_id = :id AND deleted_at IS NULL LIMIT 1",
            ['id' => $id]
        );
        if (!empty($tieneConsumo)) {
            responder(false, 'No puedes cambiar la unidad de medida de un registro con material ya consumido. Elimina este registro y créalo de nuevo con la unidad correcta.');
        }
    }

    $unidad = executeQuery($conectar, "SELECT id FROM unidad_medida WHERE id = :id AND deleted_at IS NULL", ['id' => $unidadMedida]);
    if (empty($unidad)) responder(false, 'La unidad de medida indicada no existe o está inactiva.');

    $suc = executeQuery($conectar, "SELECT id FROM sucursal WHERE id = :id AND delete_at IS NULL", ['id' => $sucursalId]);
    if (empty($suc)) responder(false, 'La sucursal indicada no existe o está inactiva.');

    // Texto legible para el log de auditoría: nombres de operarios antes/después
    // en vez de un solo id como se hacía antes con operario_id.
    $operariosAntesArr = json_decode($actual[0]['js_operarios'] ?? '[]', true) ?: [];
    $operariosAntesTexto = !empty($operariosAntesArr)
        ? implode(', ', array_column($operariosAntesArr, 'nombre_completo'))
        : "operario #{$actual[0]['operario_id']}"; // fallback para registros legacy sin js_operarios
    $operariosDespuesTexto = implode(', ', array_column($operariosResueltos, 'nombre_completo'));

    $cambios = [[
        'campo' => 'Empaquetado (cabecera)',
        'valor_antes' => "unidad #{$actual[0]['unidad_medida']}, operarios: $operariosAntesTexto, sucursal #{$actual[0]['sucursal']}",
        'valor_despues' => "unidad #$unidadMedida, operarios: $operariosDespuesTexto, sucursal #$sucursalId",
    ]];
    $movimiento   = obtenerMovimientoSesionEmp('editar', $cambios);
    $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeNonQuery($conectar, "
        UPDATE empaquetado SET
            unidad_medida = :unidad_medida,
            operario_id   = :operario_id,
            js_operarios  = :js_operarios,
            sucursal      = :sucursal_id,
            update_at     = NOW(),
            js_session    = :js_session,
            js_historial  = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id
    ", [
        'unidad_medida' => $unidadMedida,
        'operario_id'   => $operarioIdPrimario,
        'js_operarios'  => json_encode($operariosResueltos, JSON_UNESCAPED_UNICODE),
        'sucursal_id'   => $sucursalId,
        'js_session'    => $js_session,
        'js_historial'  => $js_historial,
        'id'            => $id,
    ]);

    responder(true, 'Empaquetado actualizado correctamente. La composición de colores no se modifica: para corregirla, elimina este registro y crea uno nuevo.', ['id' => $id]);
}

function eliminarEmpaquetado(int $id)
{
    if (!$id) responder(false, 'ID inválido.');
    $conectar = conectar_oll_BD();

    $existe = executeQuery($conectar, "SELECT id, pasado_venta FROM empaquetado WHERE id = :id AND deleted_at IS NULL", ['id' => $id]);
    if (empty($existe)) responder(false, 'Registro de empaquetado no encontrado o ya inactivo.');
    if (!empty($existe[0]['pasado_venta'])) {
        responder(false, 'Este registro ya pasó a venta y no se puede eliminar.');
    }

    $conectar->beginTransaction();
    try {
        executeNonQuery(
            $conectar,
            "UPDATE rel_empaquetado_origen SET deleted_at = NOW(), update_at = NOW()
             WHERE empaquetado_id = :id AND deleted_at IS NULL",
            ['id' => $id]
        );

        $cambios = [['campo' => 'Estado', 'valor_antes' => 'Activo', 'valor_despues' => 'Inactivo']];
        $movimiento   = obtenerMovimientoSesionEmp('desactivar', $cambios);
        $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeNonQuery($conectar, "
            UPDATE empaquetado SET
                deleted_at = NOW(), update_at = NOW(),
                js_session = :js_session,
                js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id
        ", ['id' => $id, 'js_session' => $js_session, 'js_historial' => $js_historial]);

        $conectar->commit();
        responder(true, 'Empaquetado eliminado correctamente. El material vinculado quedó disponible de nuevo.');
    } catch (Throwable $e) {
        $conectar->rollBack();
        error_log("Error eliminando empaquetado: " . $e->getMessage());
        responder(false, 'No se pudo eliminar: ' . $e->getMessage());
    }
}

// FIX: antes no restauraba rel_empaquetado_origen ni revalidaba
// disponibilidad — reactivar dejaba el consumo "perdido" (el material no
// se volvía a descontar). Ahora: 1) restaura las líneas de consumo,
// revalidando antes que nadie más haya tomado ese material mientras
// estaba inactivo; 2) si algo ya no alcanza, aborta con mensaje claro.
function reactivarEmpaquetado(int $id)
{
    if (!$id) responder(false, 'ID inválido.');
    $conectar = conectar_oll_BD();

    $existe = executeQuery($conectar, "SELECT id, deleted_at FROM empaquetado WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Registro de empaquetado no encontrado.');
    if (empty($existe[0]['deleted_at'])) responder(false, 'Este registro ya estaba activo.');

    $conectar->beginTransaction();
    try {
        $lineas = executeQuery(
            $conectar,
            "SELECT * FROM rel_empaquetado_origen WHERE empaquetado_id = :id AND deleted_at IS NOT NULL",
            ['id' => $id]
        );

        foreach ($lineas as $linea) {
            if (!empty($linea['ensamblaje_id'])) {
                $fila = executeQuery($conectar, "
                    SELECT e.cantidad_peso_kg - COALESCE((
                        SELECT SUM(cantidad) FROM rel_empaquetado_origen
                        WHERE ensamblaje_id = e.id AND deleted_at IS NULL
                    ), 0) AS disponible
                    FROM ensamblaje e WHERE e.id = :id
                ", ['id' => $linea['ensamblaje_id']]);
                $origenTxt = "ensamblaje #{$linea['ensamblaje_id']}";
            } else {
                $fila = executeQuery($conectar, "
                    SELECT pd.cantidad_producida_kg - COALESCE((
                        SELECT SUM(cantidad) FROM rel_empaquetado_origen
                        WHERE produccion_id = pd.id AND deleted_at IS NULL
                    ), 0) AS disponible
                    FROM produccion pd WHERE pd.id = :id
                ", ['id' => $linea['produccion_id']]);
                $origenTxt = "producción #{$linea['produccion_id']}";
            }
            if (empty($fila) || (float)$fila[0]['disponible'] < (float)$linea['cantidad'] - 0.0001) {
                throw new Exception("No se puede reactivar: ya no queda suficiente disponible en $origenTxt (fue consumido por otro empaquetado mientras este estaba inactivo).");
            }
        }

        executeNonQuery(
            $conectar,
            "UPDATE rel_empaquetado_origen SET deleted_at = NULL, update_at = NOW() WHERE empaquetado_id = :id AND deleted_at IS NOT NULL",
            ['id' => $id]
        );

        $cambios = [['campo' => 'Estado', 'valor_antes' => 'Inactivo', 'valor_despues' => 'Activo']];
        $movimiento   = obtenerMovimientoSesionEmp('reactivar', $cambios);
        $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeNonQuery($conectar, "
            UPDATE empaquetado SET
                deleted_at   = NULL,
                update_at    = NOW(),
                js_session   = :js_session,
                js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id
        ", ['id' => $id, 'js_session' => $js_session, 'js_historial' => $js_historial]);

        $conectar->commit();
        responder(true, 'Empaquetado reactivado correctamente.');
    } catch (Throwable $e) {
        $conectar->rollBack();
        error_log("Error reactivando empaquetado: " . $e->getMessage());
        responder(false, 'No se pudo reactivar: ' . $e->getMessage());
    }
}

// =============================================================================
// AUDITORÍA
// =============================================================================

function obtenerIpClienteEmp(): string
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

function obtenerMovimientoSesionEmp(string $accion, array $cambios = []): array
{
    return [
        'usuario'   => $_SESSION['usuario_id'] ?? 'Sistema',
        'nombre'    => $_SESSION['nombre_usuario'] ?? 'Usuario Desconocido',
        'user'      => $_SESSION['user_usuario'] ?? 'N/A',
        'perfiles'  => $_SESSION['perfiles'] ?? 'N/A',
        'rol'       => $_SESSION['rol_usuario'] ?? 'N/A',
        'accion'    => $accion,
        'ip'        => obtenerIpClienteEmp(),
        'cambios'   => $cambios,
        'timestamp' => date('Y-m-d H:i:s'),
    ];
}

// =============================================================================
// HELPER
// =============================================================================

function responder(bool $ok, string $msg, array $extra = []): void
{
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}
// Unidad de empaquetado configurada para el producto
// (producto.js_configuracion_empaquetado.salida_empaquetado_unidad_medida_id).
// Es la fuente de verdad: la secuencia producción -> ensamblaje -> empaquetado
// ya usa este mismo patrón para sus respectivas "unidades de salida".
// Devuelve también 'equivalencia' (capacidad de la unidad en unidades base),
// usada tanto para convertir cantidad_tota como para validar que un bulto
// no exceda esa capacidad.
function obtenerUnidadEmpaquetadoProducto($conectar, int $productoId): ?array
{
    $rows = executeQuery($conectar, "
        SELECT NULLIF(p.js_configuracion_empaquetado->>'salida_empaquetado_unidad_medida_id','')::bigint AS unidad_id
        FROM producto p WHERE p.id = :id
    ", ['id' => $productoId]);
    if (empty($rows) || empty($rows[0]['unidad_id'])) return null;

    $unidad = executeQuery($conectar, "
        SELECT id, nombre, nombre_corto, unidad_base_id, equivalencia
        FROM unidad_medida WHERE id = :id AND deleted_at IS NULL
    ", ['id' => $rows[0]['unidad_id']]);
    return $unidad[0] ?? null;
}
// Reglas de empaquetado configuradas por producto (ver clssProductos.php ->
// guardarConfigProducto()). Viven en el mismo js_configuracion_empaquetado.
function obtenerReglasEmpaquetadoProducto($conectar, int $productoId): array
{
    $rows = executeQuery($conectar, "
        SELECT js_configuracion_empaquetado, peso_unitario_g
        FROM producto WHERE id = :id
    ", ['id' => $productoId]);

    $cfg = !empty($rows[0]['js_configuracion_empaquetado'])
        ? json_decode($rows[0]['js_configuracion_empaquetado'], true)
        : [];

    return [
        'modo_distribucion_color'  => $cfg['modo_distribucion_color'] ?? 'libre',
        'granularidad_color'       => intval($cfg['granularidad_color'] ?? 1),
        'conversion_peso_a_unidad' => !empty($cfg['conversion_peso_a_unidad']),
        'peso_unitario_g'          => !empty($rows[0]['peso_unitario_g']) ? floatval($rows[0]['peso_unitario_g']) : null,
    ];
}