<?php
declare(strict_types=1);

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
require_once __DIR__ . '/clssVerificarSession.php';

if (isset($_POST['accion']) && $_POST['accion'] === 'RESUMENDASHBOARD') {
    try {
        $usuario = exigirSesion();
        exigirRol($usuario, ['administrador', 'ventas', 'produccion', 'consulta']);
        enviarResumenDashboard($usuario);
    } catch (Throwable $e) {
        error_log('Error en clssDashboard.php: ' . $e->getMessage());
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'No se pudo cargar el dashboard.']);
    }
}

function enviarResumenDashboard(array $usuario): void
{
    $db = conectar_oll_BD();
    $periodo = strtolower(trim((string)($_POST['periodo'] ?? 'mes')));
    $hoy = new DateTimeImmutable('today', new DateTimeZone('America/Lima'));
    if ($periodo === 'dia') {
        $desde = $hoy;
        $etiqueta = 'Hoy';
    } elseif ($periodo === 'semana') {
        $desde = $hoy->modify('monday this week');
        $etiqueta = 'Esta semana';
    } else {
        $periodo = 'mes';
        $desde = $hoy->modify('first day of this month');
        $etiqueta = 'Este mes';
    }
    $hastaExclusivo = $hoy->modify('+1 day');
    $paramsPeriodo = ['desde' => $desde->format('Y-m-d'), 'hasta' => $hastaExclusivo->format('Y-m-d')];

    $puedeVerVentas = in_array(strtolower(trim((string)($usuario['rol'] ?? ''))), ['administrador', 'ventas', 'consulta'], true);
    $ventas = $puedeVerVentas ? executeQuery($db, "
        WITH ventas_periodo AS (
            SELECT v.id, v.monto_total, v.js_session
            FROM venta v
            WHERE v.estado = 'completada'
              AND v.fecha_venta >= CAST(:desde AS date)
              AND v.fecha_venta < CAST(:hasta AS date)
        ), pagos_moneda AS (
            SELECT UPPER(COALESCE(NULLIF(pago->>'moneda', ''), 'PEN')) AS moneda,
                   COALESCE(NULLIF(pago->>'monto', '')::numeric, 0) AS monto
            FROM ventas_periodo vp
            CROSS JOIN LATERAL jsonb_array_elements(
                CASE WHEN jsonb_typeof(vp.js_session #> '{venta_moneda,pagos}') = 'array'
                     THEN vp.js_session #> '{venta_moneda,pagos}' ELSE '[]'::jsonb END
            ) AS detalle(pago)
            UNION ALL
            SELECT 'PEN', pago.monto
            FROM ventas_periodo vp
            JOIN venta_pago pago ON pago.venta_id = vp.id
            WHERE COALESCE(jsonb_array_length(
                CASE WHEN jsonb_typeof(vp.js_session #> '{venta_moneda,pagos}') = 'array'
                     THEN vp.js_session #> '{venta_moneda,pagos}' ELSE '[]'::jsonb END
            ), 0) = 0
        )
        SELECT (SELECT COUNT(*) FROM ventas_periodo) AS ventas,
               (SELECT ROUND(COALESCE(SUM(monto_total), 0), 2) FROM ventas_periodo) AS total_pen,
               COALESCE((SELECT ROUND(SUM(monto), 2) FROM pagos_moneda WHERE moneda = 'PEN'), 0) AS ingresos_pen,
               COALESCE((SELECT ROUND(SUM(monto), 2) FROM pagos_moneda WHERE moneda = 'USD'), 0) AS ingresos_usd,
               COALESCE((SELECT ROUND(SUM(monto), 2) FROM pagos_moneda WHERE moneda = 'EUR'), 0) AS ingresos_eur
    ", $paramsPeriodo) : [];
    $venta = $ventas[0] ?? ['ventas' => 0, 'total_pen' => 0, 'ingresos_pen' => 0, 'ingresos_usd' => 0, 'ingresos_eur' => 0];

    $produccion = executeQuery($db, "
        SELECT COUNT(*) FILTER (WHERE fecha_hora_inicio IS NOT NULL AND fecha_hora_fin IS NULL) AS en_curso,
               COUNT(*) FILTER (WHERE detenido IS NOT NULL AND fecha_hora_fin IS NULL) AS pausadas
        FROM produccion
        WHERE deleted_at IS NULL
    ");
    $pendienteEnsamblaje = executeQuery($db, "
        WITH salidas_locales AS (
            SELECT p.id,
                   SUM(COALESCE(NULLIF(salida.item->>'cantidad', '')::numeric, 0)) AS cantidad_enviada
            FROM produccion p
            CROSS JOIN LATERAL jsonb_array_elements(
                CASE WHEN jsonb_typeof(p.js_cantidades_salientes) = 'array'
                     THEN p.js_cantidades_salientes ELSE '[]'::jsonb END
            ) AS salida(item)
            WHERE p.deleted_at IS NULL
              AND COALESCE(salida.item->>'destino_ensamblaje', CASE WHEN p.realizado_por_fuera IS TRUE THEN 'externo' ELSE 'local' END) = 'local'
            GROUP BY p.id
        ), usado AS (
            SELECT molde_produccion_id AS produccion_id,
                   SUM(COALESCE(cantidad_entrada_produccion, 0)) AS cantidad_usada
            FROM rel_ensamblaje_producto
            WHERE deleted_at IS NULL AND molde_produccion_id IS NOT NULL
            GROUP BY molde_produccion_id
        )
        SELECT COUNT(*) AS producciones
        FROM salidas_locales s
        JOIN produccion p ON p.id = s.id
        LEFT JOIN usado u ON u.produccion_id = s.id
        WHERE s.cantidad_enviada - COALESCE(u.cantidad_usada, 0) > 0.0001
    ");

    $multiMoldeListo = "(
        (SELECT COUNT(DISTINCT cfg.item->>'molde_id')
         FROM jsonb_array_elements(COALESCE(pr.js_configuracion, '[]'::jsonb)) AS cfg(item)
         WHERE cfg.item->>'necesita_ensamblaje' = 'sí') > 1
        AND NOT EXISTS (
            SELECT 1 FROM jsonb_array_elements(COALESCE(pr.js_configuracion, '[]'::jsonb)) AS cfg(item)
            WHERE cfg.item->>'necesita_ensamblaje' = 'sí'
              AND NOT EXISTS (
                  SELECT 1 FROM rel_ensamblaje_producto rep
                  JOIN produccion pd ON pd.id = rep.molde_produccion_id
                  WHERE rep.ensamblaje_id = e.id AND rep.deleted_at IS NULL
                    AND pd.deleted_at IS NULL AND pd.molde_id = NULLIF(cfg.item->>'molde_id', '')::bigint
              )
              AND NOT EXISTS (
                  SELECT 1 FROM ensamblaje ec
                  JOIN categoria_material cm ON cm.id = ec.categoria_material_id
                  WHERE ec.ensamblaje_id_referido = e.id AND ec.deleted_at IS NULL AND ec.fin IS NOT NULL
                    AND LOWER(COALESCE(cm.nombre, '')) LIKE '%primera%'
                    AND EXISTS (
                        SELECT 1 FROM rel_ensamblaje_producto repc
                        JOIN produccion pdc ON pdc.id = repc.molde_produccion_id
                        WHERE repc.ensamblaje_id = ec.id AND repc.deleted_at IS NULL
                          AND pdc.deleted_at IS NULL AND pdc.molde_id = NULLIF(cfg.item->>'molde_id', '')::bigint
                    )
              )
        )
    )";
    $pendienteEmpaquetado = executeQuery($db, "
        WITH fuentes AS (
            SELECT 'ensamblaje'::text AS tipo, e.id,
                   e.cantidad_peso_kg - COALESCE((
                       SELECT SUM(reo.cantidad) FROM rel_empaquetado_origen reo
                       WHERE reo.ensamblaje_id = e.id AND reo.deleted_at IS NULL
                   ), 0) AS disponible
            FROM ensamblaje e
            LEFT JOIN producto pr ON pr.id = e.producto_id
            WHERE e.deleted_at IS NULL AND e.fin IS NOT NULL
              AND (e.enviado_empaquetado IS TRUE OR $multiMoldeListo)
              AND e.ensamblaje_id_referido IS NULL
              AND (e.js_producto_emsamblado IS NULL OR $multiMoldeListo)
            UNION ALL
            SELECT 'produccion'::text, p.id,
                   p.cantidad_producida_kg - COALESCE((
                       SELECT SUM(reo.cantidad) FROM rel_empaquetado_origen reo
                       WHERE reo.produccion_id = p.id AND reo.deleted_at IS NULL
                   ), 0)
            FROM produccion p
            JOIN producto pr ON pr.id = NULLIF(split_part(p.unico_molde_producto, '-', 2), '')::bigint
            LEFT JOIN molde m ON m.id = p.molde_id
            LEFT JOIN LATERAL (
                SELECT elem.item FROM jsonb_array_elements(COALESCE(pr.js_configuracion, '[]'::jsonb)) elem(item)
                WHERE (elem.item->>'molde_id')::bigint = m.id LIMIT 1
            ) cfg ON TRUE
            WHERE p.deleted_at IS NULL
              AND jsonb_array_length(CASE WHEN jsonb_typeof(p.js_cantidades_salientes) = 'array'
                                          THEN p.js_cantidades_salientes ELSE '[]'::jsonb END) > 0
              AND COALESCE(cfg.item->>'necesita_ensamblaje', 'no') = 'no'
              AND NOT EXISTS (SELECT 1 FROM rel_ensamblaje_producto rep WHERE rep.molde_produccion_id = p.id AND rep.deleted_at IS NULL)
        )
        SELECT COUNT(*) AS fuentes
        FROM fuentes WHERE disponible > 0.0001
    ");

    $externos = executeQuery($db, "
        SELECT COUNT(*) AS envios
        FROM produccion p
        CROSS JOIN LATERAL jsonb_array_elements(
            CASE WHEN jsonb_typeof(p.js_realizado_por_fuera) = 'array'
                 THEN p.js_realizado_por_fuera ELSE '[]'::jsonb END
        ) envio(item)
        WHERE p.deleted_at IS NULL
          AND envio.item->>'destino' = 'externo'
          AND jsonb_typeof(envio.item->'recepcion') IS DISTINCT FROM 'object'
    ");

    $materialesBajos = executeQuery($db, "
        SELECT m.id, m.nombre, m.stock_actual, m.stock_minimo, u.nombre_corto AS unidad
        FROM material m
        LEFT JOIN unidad_medida u ON u.id = m.unidad_medida_id
        WHERE m.deleted_at IS NULL AND m.stock_minimo > 0 AND m.stock_actual <= m.stock_minimo
        ORDER BY (m.stock_minimo - m.stock_actual) DESC, m.nombre
        LIMIT 8
    ");

    responderDashboard(true, [
        'periodo' => ['clave' => $periodo, 'etiqueta' => $etiqueta, 'desde' => $desde->format('Y-m-d'), 'hasta' => $hoy->format('Y-m-d')],
        'ventas_visibles' => $puedeVerVentas,
        'ventas' => $venta,
        'produccion' => $produccion[0] ?? ['en_curso' => 0, 'pausadas' => 0],
        'pendiente_ensamblaje' => $pendienteEnsamblaje[0]['producciones'] ?? 0,
        'pendiente_empaquetado' => $pendienteEmpaquetado[0]['fuentes'] ?? 0,
        'servicios_externos_pendientes' => $externos[0]['envios'] ?? 0,
        'materiales_bajos' => $materialesBajos,
    ]);
}

function responderDashboard(bool $ok, array $data = []): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $ok] + $data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
