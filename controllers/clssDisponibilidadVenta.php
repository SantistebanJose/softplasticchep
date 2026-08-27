<?php

/**
 * controllers/clssDisponibilidadVenta.php
 * Reporte de Disponibilidad de Venta: stock empaquetado y aún no vendido,
 * agrupado por producto + color.
 *
 * REESCRITO (2026-08-19): el color YA NO se deriva del historial de
 * producción del producto (producto_color vía `produccion`). Ese enfoque
 * duplicaba la disponibilidad completa del producto por cada color que
 * hubiera producido alguna vez, y además dejó de tener sentido desde que
 * Empaquetado soporta MEZCLA DE COLORES por bulto: un mismo registro de
 * empaquetado puede combinar varios colores/orígenes.
 *
 * Ahora el color se lee directo de rel_empaquetado_origen (fuente real
 * de qué colores componen cada registro de empaquetado, ya en unidades
 * base — ver clssEmpaquetado.php). Se agrupa por producto + color sumando
 * rel_empaquetado_origen.cantidad de los registros activos y no vendidos.
 *
 * FALLBACK LEGACY: registros de empaquetado anteriores al modelo de
 * mezcla (sin ninguna fila activa en rel_empaquetado_origen) no tienen
 * color por línea. Para esos, se usa cantidad_tota * equivalencia (mismo
 * cálculo que el reporte usaba antes) bajo un color "Sin color (registro
 * legado)", en vez de perderlos silenciosamente del reporte.
 *
 * ACTUALIZADO (2026-08-27): se agrega PAQUETES DISPONIBLES como métrica
 * principal del reporte (ventas se hace mayormente por paquete, no por
 * unidad suelta). "Paquete" = emp.cantidad_tota, es decir la cantidad en
 * la UNIDAD DE EMPAQUETADO propia del registro (Bulto, Docena, Bolsa,
 * etc.), SIN convertir a unidad base. La categorización por color/mezcla/
 * legado sigue siendo a nivel de REGISTRO completo (un registro de
 * empaquetado cae entero en un solo bucket), así que sumar cantidad_tota
 * agrupado igual que cantidad_base es seguro y consistente: no hay
 * prorrateo de un mismo registro entre dos colores distintos.
 *
 * Si dentro de un mismo grupo (producto+color) hay registros con distinta
 * unidad de empaquetado configurada (caso raro: el producto cambió su
 * config de empaquetado con stock viejo aún disponible), el total de
 * paquetes es una SUMA APROXIMADA de cantidades en unidades distintas.
 * Se expone `unidades_paquete_distintas` para que el frontend lo marque
 * en vez de mostrar un número limpio que en realidad mezcla peras con
 * manzanas.
 *
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

ob_start();

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    try {
        controladorDisponibilidadVenta($_POST["accion"]);
    } catch (PDOException $e) {
        error_log("Error de base de datos en clssDisponibilidadVenta.php: " . $e->getMessage());
        responderDV(false, 'Error de base de datos: ' . $e->getMessage());
    } catch (Throwable $e) {
        error_log("Error inesperado en clssDisponibilidadVenta.php: " . $e->getMessage());
        responderDV(false, 'Error inesperado en el servidor: ' . $e->getMessage());
    }
}

function controladorDisponibilidadVenta($accion)
{
    switch ($accion) {
        case 'LISTARDISPONIBILIDADVENTA':
            listarDisponibilidadVenta();
            break;
        case 'BUSCARCOLORESDV':
            buscarColoresDV();
            break;
        default:
            responderDV(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// Filtro auxiliar para el <select> de color.
function buscarColoresDV()
{
    $conectar = conectar_oll_BD();
    $sql = "SELECT id, nombre FROM color WHERE deleted_at IS NULL ORDER BY nombre";
    $result = executeQuery($conectar, $sql, []);
    responderDV(true, 'OK', ['colores' => $result]);
}

// REESCRITO (2026-08-27): además de cantidad_base (unidad base, ej. UND),
// ahora se calcula paquetes_disponibles = SUM(cantidad_tota) del grupo,
// osea el conteo en la unidad de empaquetado tal cual se registró (Bulto,
// Docena, Bolsa...), que es la que le importa a ventas.
function listarDisponibilidadVenta()
{
    $conectar        = conectar_oll_BD();
    $texto           = trim($_POST['texto'] ?? '');
    $colorId         = trim($_POST['color_id'] ?? '');
    $incluirVendidos = ($_POST['incluir_vendidos'] ?? '0') === '1';

    $whereDetalle = ["dc.deleted_at IS NULL"];
    $params       = [];

    if (!$incluirVendidos) {
        $whereDetalle[] = "dc.pasado_venta IS NULL";
    }
    if ($texto !== '') {
        $whereDetalle[] = "(LOWER(p.codigo) LIKE LOWER(:texto) OR LOWER(p.descripcion) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }
    if ($colorId !== '') {
        // El filtro de color solo aplica a colores reales (nunca matchea
        // "Mezcla" ni "Sin color", que usan sentinels -1 / NULL).
        $whereDetalle[] = "dc.color_id_efectivo = :color_id";
        $params['color_id'] = $colorId;
    }

    $sql = "
        WITH color_stats AS (
            -- Por registro: cuántos colores distintos tiene y cuánto suma
            -- en total (ya en unidades base). MIN(color_id) solo se usa
            -- cuando colores_distintos = 1 (en ese caso es EL color).
            SELECT
                reo.empaquetado_id,
                COUNT(DISTINCT reo.color_id) AS colores_distintos,
                SUM(reo.cantidad) AS cantidad_base_total,
                MIN(reo.color_id) AS unico_color_id
            FROM rel_empaquetado_origen reo
            WHERE reo.deleted_at IS NULL
            GROUP BY reo.empaquetado_id
        ),
        detalle AS (
            SELECT
                emp.id AS empaquetado_id,
                emp.producto_id,
                emp.pasado_venta,
                emp.deleted_at,
                CASE
                    WHEN cs.colores_distintos IS NULL THEN NULL   -- legado
                    WHEN cs.colores_distintos = 1 THEN cs.unico_color_id
                    ELSE -1                                        -- mezcla
                END AS color_id_efectivo,
                COALESCE(
                    cs.cantidad_base_total,
                    emp.cantidad_tota * COALESCE(um.equivalencia, 1)  -- fallback legado
                ) AS cantidad_base,
                emp.cantidad_tota AS cantidad_paquetes,
                um.nombre_corto AS unidad_paquete_corto,
                COALESCE(ub.nombre_corto, um.nombre_corto) AS unidad_base_corto
            FROM empaquetado emp
            LEFT JOIN color_stats cs ON cs.empaquetado_id = emp.id
            JOIN unidad_medida um ON um.id = emp.unidad_medida
            LEFT JOIN unidad_medida ub ON ub.id = um.unidad_base_id
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
            COUNT(*) AS registros_count,
            SUM(dc.cantidad_base) AS cantidad_disponible,
            MIN(dc.unidad_base_corto) AS unidad_corto,
            SUM(dc.cantidad_paquetes) AS paquetes_disponibles,
            MIN(dc.unidad_paquete_corto) AS unidad_paquete_corto,
            (COUNT(DISTINCT dc.unidad_paquete_corto) > 1) AS unidades_paquete_distintas
        FROM detalle dc
        JOIN producto p ON p.id = dc.producto_id
        LEFT JOIN color co ON co.id = dc.color_id_efectivo AND dc.color_id_efectivo <> -1
        WHERE " . implode(' AND ', $whereDetalle) . "
        GROUP BY dc.producto_id, p.codigo, p.descripcion, dc.color_id_efectivo, co.nombre
        HAVING SUM(dc.cantidad_base) > 0
        ORDER BY p.descripcion,
                 CASE WHEN dc.color_id_efectivo = -1 THEN 1 ELSE 0 END, -- Mezcla al final de cada producto
                 co.nombre NULLS LAST
    ";

    $result = executeQuery($conectar, $sql, $params);
    responderDV(true, 'OK', ['disponibilidad' => $result]);
}

function responderDV(bool $ok, string $msg, array $extra = []): void
{
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}