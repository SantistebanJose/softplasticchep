<?php

ob_start();

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
require_once __DIR__ . '/auditoria.php';
require_once __DIR__ . '/clssVerificarSession.php';

iniciarSesionSegura();

if (isset($_POST['accion'])) {
    try {
        $usuario = exigirSesion();
        exigirRol($usuario, ['administrador']);
        if ($_POST['accion'] !== 'REPORTESERVICIOSEXTERNOS') {
            responderAcceso(400, 'Acción no reconocida.');
        }
        reporteServiciosExternos();
    } catch (PDOException $e) {
        error_log('Error de base de datos en clssReporteServiciosExternos.php: ' . $e->getMessage());
        responder(false, 'No se pudo consultar el reporte de servicios externos.');
    } catch (Throwable $e) {
        error_log('Error inesperado en clssReporteServiciosExternos.php: ' . $e->getMessage());
        responder(false, 'Ocurrió un error al consultar el reporte.');
    }
}

function reporteServiciosExternos(): void
{
    $fechaDesde = trim((string)($_POST['fecha_desde'] ?? ''));
    $fechaHasta = trim((string)($_POST['fecha_hasta'] ?? ''));
    $destinatario = trim((string)($_POST['destinatario'] ?? ''));
    $estado = strtolower(trim((string)($_POST['estado'] ?? '')));

    foreach (['fecha_desde' => $fechaDesde, 'fecha_hasta' => $fechaHasta] as $campo => $fecha) {
        if ($fecha !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            responder(false, 'El rango de fechas no es válido.');
        }
    }
    if ($fechaDesde !== '' && $fechaHasta !== '' && $fechaDesde > $fechaHasta) {
        [$fechaDesde, $fechaHasta] = [$fechaHasta, $fechaDesde];
    }
    if (!in_array($estado, ['', 'pendiente', 'recibido'], true)) $estado = '';

    $conectar = conectar_oll_BD();
    $filas = executeQuery($conectar, "
        WITH filtros AS (
            SELECT
                CAST(:fecha_desde AS date) AS fecha_desde,
                CAST(:fecha_hasta AS date) AS fecha_hasta,
                CAST(:destinatario AS text) AS destinatario,
                CAST(:estado AS text) AS estado
        )
        SELECT
            p.id AS produccion_id,
            NULLIF(envio.item->>'pasada', '')::integer AS pasada,
            envio.item->>'enviado_a' AS enviado_a,
            NULLIF(envio.item->>'fecha_envio', '')::timestamp AS fecha_envio,
            COALESCE(NULLIF(envio.item->>'cantidad_enviada', '')::numeric, 0) AS cantidad_enviada,
            COALESCE(NULLIF(recepcion.unidad_componente, ''), NULLIF(envio.item->>'unidad', ''), 'UND') AS unidad_componente,
            m.id AS molde_id,
            COALESCE(m.nombre, 'Molde sin nombre') AS componente,
            co.nombre AS color,
            cm.nombre AS categoria_material,
            recepcion.ensamblaje_id,
            recepcion.fecha_recepcion,
            COALESCE(recepcion.cantidad_producto_armado, 0) AS cantidad_producto_armado,
            COALESCE(NULLIF(recepcion.unidad_producto, ''), '') AS unidad_producto,
            COALESCE(recepcion.cantidad_componente_utilizada, 0) AS cantidad_utilizada,
            COALESCE(recepcion.cantidad_merma, 0) AS cantidad_merma,
            COALESCE(NULLIF(recepcion.motivo_merma, ''), '') AS motivo_merma,
            COALESCE(recepcion.cantidad_sobrante, 0) AS cantidad_sobrante,
            producto.codigo AS producto_armado_codigo,
            producto.descripcion AS producto_armado,
            CASE WHEN recepcion.ensamblaje_id IS NULL THEN 'pendiente' ELSE 'recibido' END AS estado
        FROM produccion p
        CROSS JOIN LATERAL jsonb_array_elements(
            CASE
                WHEN jsonb_typeof(p.js_realizado_por_fuera) = 'array' THEN p.js_realizado_por_fuera
                ELSE '[]'::jsonb
            END
        ) AS envio(item)
        LEFT JOIN LATERAL (
            SELECT envio.item->'recepcion' AS datos
            WHERE jsonb_typeof(envio.item->'recepcion') = 'object'
        ) AS recepcion_json ON TRUE
        LEFT JOIN LATERAL jsonb_to_record(recepcion_json.datos) AS recepcion(
            ensamblaje_id bigint,
            fecha_recepcion text,
            cantidad_producto_armado numeric,
            unidad_producto text,
            cantidad_componente_utilizada numeric,
            cantidad_merma numeric,
            motivo_merma text,
            cantidad_sobrante numeric,
            unidad_componente text
        ) ON TRUE
        LEFT JOIN molde m ON m.id = p.molde_id
        LEFT JOIN color co ON co.id = p.color_id
        LEFT JOIN categoria_material cm ON cm.id = p.categoria_material_id
        LEFT JOIN ensamblaje e ON e.id = recepcion.ensamblaje_id
        LEFT JOIN producto ON producto.id = e.producto_id
        CROSS JOIN filtros f
        WHERE p.deleted_at IS NULL
          AND envio.item->>'destino' = 'externo'
          AND COALESCE(NULLIF(envio.item->>'fecha_envio', '')::timestamp, p.created_at)
                >= COALESCE(f.fecha_desde, '-infinity'::date)
          AND COALESCE(NULLIF(envio.item->>'fecha_envio', '')::timestamp, p.created_at)
                < COALESCE(f.fecha_hasta + INTERVAL '1 day', 'infinity'::timestamp)
          AND COALESCE(envio.item->>'enviado_a', '') ILIKE '%' || COALESCE(f.destinatario, '') || '%'
          AND CASE LOWER(COALESCE(f.estado, ''))
                WHEN 'pendiente' THEN recepcion.ensamblaje_id IS NULL
                WHEN 'recibido' THEN recepcion.ensamblaje_id IS NOT NULL
                ELSE TRUE
              END
        ORDER BY fecha_envio DESC NULLS LAST, p.id DESC, pasada DESC
    ", [
        'fecha_desde' => $fechaDesde !== '' ? $fechaDesde : null,
        'fecha_hasta' => $fechaHasta !== '' ? $fechaHasta : null,
        'destinatario' => $destinatario,
        'estado' => $estado,
    ]);

    $resumen = [
        'envios' => count($filas),
        'pendientes' => 0,
        'recibidos' => 0,
        'enviado_por_unidad' => [],
        'utilizado_por_unidad' => [],
        'merma_por_unidad' => [],
        'sobrante_por_unidad' => [],
        'producto_armado_por_unidad' => [],
    ];
    $ensamblajesContados = [];
    foreach ($filas as &$fila) {
        $fila['cantidad_enviada'] = (float)$fila['cantidad_enviada'];
        $fila['cantidad_utilizada'] = (float)$fila['cantidad_utilizada'];
        $fila['cantidad_merma'] = (float)$fila['cantidad_merma'];
        $fila['cantidad_sobrante'] = (float)$fila['cantidad_sobrante'];
        $fila['cantidad_producto_armado'] = (float)$fila['cantidad_producto_armado'];
        $fila['pasada'] = $fila['pasada'] !== null ? (int)$fila['pasada'] : null;
        if ($fila['estado'] === 'recibido') {
            $resumen['recibidos']++;
            $resumen['utilizado_por_unidad'][$fila['unidad_componente']] = ($resumen['utilizado_por_unidad'][$fila['unidad_componente']] ?? 0) + $fila['cantidad_utilizada'];
            $resumen['merma_por_unidad'][$fila['unidad_componente']] = ($resumen['merma_por_unidad'][$fila['unidad_componente']] ?? 0) + $fila['cantidad_merma'];
            $resumen['sobrante_por_unidad'][$fila['unidad_componente']] = ($resumen['sobrante_por_unidad'][$fila['unidad_componente']] ?? 0) + $fila['cantidad_sobrante'];
            $ensamblajeId = (int)($fila['ensamblaje_id'] ?? 0);
            if ($ensamblajeId > 0) {
                $ensamblajesContados[$ensamblajeId] = [
                    'unidad' => $fila['unidad_producto'] ?: 'UND',
                    'cantidad' => $fila['cantidad_producto_armado'],
                ];
            }
        } else {
            $resumen['pendientes']++;
        }
        $unidad = $fila['unidad_componente'] ?: 'UND';
        $resumen['enviado_por_unidad'][$unidad] = ($resumen['enviado_por_unidad'][$unidad] ?? 0) + $fila['cantidad_enviada'];
    }
    unset($fila);
    foreach ($ensamblajesContados as $retorno) {
        $unidad = $retorno['unidad'];
        $resumen['producto_armado_por_unidad'][$unidad] = ($resumen['producto_armado_por_unidad'][$unidad] ?? 0) + $retorno['cantidad'];
    }

    responder(true, 'OK', [
        'filas' => $filas,
        'resumen' => $resumen,
        'filtros' => [
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'destinatario' => $destinatario,
            'estado' => $estado,
        ],
    ]);
}

function responder(bool $ok, string $mensaje, array $extra = []): void
{
    if (ob_get_level() > 0) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        array_merge(['success' => $ok, 'message' => $mensaje], $extra),
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}
