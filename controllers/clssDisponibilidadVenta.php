<?php

/**
 * controllers/clssDisponibilidadVenta.php
 * Reporte de Disponibilidad de Venta: stock empaquetado y aún no vendido,
 * agrupado por producto + color, normalizado a la unidad base cuando
 * corresponde (mismo patrón de equivalencia que clssEmpaquetado.php).
 *
 * Color: se deriva vía produccion.unico_molde_producto (split_part), NO
 * existe color_id directo en empaquetado. Ver nota en el chat sobre el
 * caso de un producto con más de un color histórico.
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

function listarDisponibilidadVenta()
{
    $conectar        = conectar_oll_BD();
    $texto           = trim($_POST['texto'] ?? '');
    $colorId         = trim($_POST['color_id'] ?? '');
    $incluirVendidos = ($_POST['incluir_vendidos'] ?? '0') === '1';

    $whereEmp = ["t1.deleted_at IS NULL"];
    $params   = [];

    if (!$incluirVendidos) {
        $whereEmp[] = "t1.pasado_venta IS NULL";
    }
    if ($texto !== '') {
        $whereEmp[] = "(LOWER(pc.producto_codigo) LIKE LOWER(:texto) OR LOWER(pc.producto) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }
    if ($colorId !== '') {
        $whereEmp[] = "pc.color_id = :color_id";
        $params['color_id'] = $colorId;
    }

    $sql = "WITH producto_color AS (
                SELECT DISTINCT
                    t2.id AS producto_id,
                    t2.codigo AS producto_codigo,
                    t2.descripcion AS producto,
                    t4.id AS color_id,
                    t4.nombre AS color
                FROM produccion t3
                JOIN producto t2 ON t2.id::varchar = split_part(t3.unico_molde_producto, '-', 2)
                JOIN color t4 ON t4.id = t3.color_id
                WHERE t4.deleted_at IS NULL
            )
            SELECT
                pc.producto_id,
                pc.producto_codigo,
                pc.producto,
                pc.color_id,
                pc.color,
                COUNT(t1.id) AS registros_count,
                COALESCE(SUM(
                    CASE WHEN um.unidad_base_id IS NOT NULL
                         THEN t1.cantidad_tota * um.equivalencia
                         ELSE t1.cantidad_tota
                    END
                ), 0) AS cantidad_disponible,
                MIN(COALESCE(ub.nombre_corto, um.nombre_corto)) AS unidad_corto
            FROM producto_color pc
            JOIN empaquetado t1 ON t1.producto_id = pc.producto_id
            JOIN unidad_medida um ON um.id = t1.unidad_medida
            LEFT JOIN unidad_medida ub ON ub.id = um.unidad_base_id
            WHERE " . implode(' AND ', $whereEmp) . "
            GROUP BY pc.producto_id, pc.producto_codigo, pc.producto, pc.color_id, pc.color
            HAVING COALESCE(SUM(
                CASE WHEN um.unidad_base_id IS NOT NULL
                     THEN t1.cantidad_tota * um.equivalencia
                     ELSE t1.cantidad_tota
                END
            ), 0) > 0
            ORDER BY pc.producto, pc.color";

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