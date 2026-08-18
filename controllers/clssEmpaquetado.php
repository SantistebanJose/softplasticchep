<?php

/**
 * controllers/clssEmpaquetado.php
 * Controlador del módulo de Empaquetado
 *
 * ACTUALIZADO (2026-07-30): listarEnsamblajesParaEmpaquetado() ahora
 * excluye del grid CUALQUIER ensamblaje que ya tenga al menos un
 * registro en `empaquetado` (LEFT JOIN empaquetado ee ... WHERE
 * ee.emsamblaje_id IS NULL). Antes esos ensamblajes seguían apareciendo
 * con el botón "Ver empaquetado"; con este cambio desaparecen del grid
 * en cuanto tienen su primer registro. Esos registros siguen visibles
 * en el listado general (LISTARTODOSEMPAQUETADOS) más abajo.
 *
 * ACTUALIZADO (2026-07-27 v3): sesión de correcciones y mejoras:
 *
 *   1) BUG: unidad_medida NO tiene columna `activo` (se confirmó con
 *      information_schema.columns), tiene `deleted_at` como el resto del
 *      sistema. Se corrigió en buscarUnidadesMedida(), crearEmpaquetado()
 *      y editarEmpaquetado(), que hacían `WHERE activo = true` y tronaban
 *      con SQLSTATE[42703] (columna inexistente).
 *
 *   2) BUG: listarEnsamblajesParaEmpaquetado() no filtraba ensamblajes que
 *      ya fueron ABSORBIDOS como complemento de otro ensamblaje (columna
 *      ensamblaje.ensamblaje_id_referido con valor). Un ensamblaje así ya
 *      no es una unidad independiente empaquetable por separado: su peso
 *      ahora vive dentro del ensamblaje padre. Se agregó el filtro
 *      "e.ensamblaje_id_referido IS NULL".
 *
 *   3) MEJORA: unidad_medida tiene unidad_base_id + equivalencia (patrón
 *      de conversión, ej. 1 GRUESA = 24 UNIDAD). Se agregó el cálculo
 *      normalizado (cantidad_tota * equivalencia) tanto en el detalle de
 *      un ensamblaje (listarEmpaquetados) como en el total agregado del
 *      grid principal (listarEnsamblajesParaEmpaquetado), para que sumar
 *      registros en distintas unidades no mezcle peras con manzanas.
 *
 *   4) NUEVO: acción LISTARTODOSEMPAQUETADOS, listado general (no filtrado
 *      por un solo ensamblaje) para la tabla que vive debajo del grid en
 *      empaquetado.php. Soporta filtros de texto, estado (disponible /
 *      vendido) y rango de fechas.
 *
 * Tabla real:
 *   empaquetado (id, producto_id -> producto, emsamblaje_id -> ensamblaje,
 *                unidad_medida -> unidad_medida, operario_id -> operario,
 *                pasado_venta [timestamp, NULL = aún no pasó a venta],
 *                venta_id_ref [FUTURO: se llenará cuando exista el módulo de
 *                ventas; por ahora esta columna no se toca desde aquí],
 *                cantidad_tota, js_cantidades,
 *                js_session, js_historial, created_at, update_at, deleted_at)
 *
 *   unidad_medida (id, nombre, nombre_corto, unidad_base_id, equivalencia,
 *                  js_session, js_historial, created_at, update_at,
 *                  deleted_at)
 *   Semántica de equivalencia (confirmada por el usuario con datos reales):
 *   1 <esta unidad> = equivalencia <unidad_base_id>. Ej. GRUESA:
 *   equivalencia = 24, unidad_base_id -> UNIDAD => 1 GRU = 24 UND.
 *
 * MODELO (igual que antes, sin cambios):
 *   producto_id se DERIVA siempre de emsamblaje_id (producto_id del
 *   ensamblaje asociado), nunca se elige aparte en el formulario.
 *
 *   Un ensamblaje solo puede empaquetarse una vez finalizado (fin IS NOT
 *   NULL). Un mismo ensamblaje puede tener VARIAS filas de empaquetado
 *   (cada fila = una operación de empaquetado con uno o más bultos, en UNA
 *   sola unidad de medida y UN solo operario).
 *
 *   pasado_venta / venta_id_ref: columnas FUTURAS para cuando exista el
 *   módulo de ventas. Por ahora solo se muestran (de solo lectura) en el
 *   listado; este controlador no las modifica. No se permite eliminar ni
 *   editar una fila que ya tenga pasado_venta con valor (se considera
 *   "cerrada" una vez que salió a venta).
 *
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

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
        case 'LISTAREMPAQUETADOS':
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
// =============================================================================
// LISTADOS AUXILIARES
// =============================================================================

function buscarOperarios()
{
    $conectar = conectar_oll_BD();
    $texto = trim($_POST['texto'] ?? '');

    // Igual que en Producción/Ensamblaje: solo operarios activos con la
    // etapa "Empaquetado" marcada en su js_etapas_relacionadas.
    $where = [
        "activo = true",
        "EXISTS (
            SELECT 1 FROM jsonb_array_elements(COALESCE(js_etapas_relacionadas, '[]'::jsonb)) AS et
            WHERE et->>'nombre' ILIKE '%EMPAQUETA%'
        )"
    ];
    $params = [];
    if ($texto !== '') {
        $where[] = "LOWER(nombre_completo) LIKE LOWER(:texto)";
        $params['texto'] = "%$texto%";
    }

    $sql = "SELECT id, nombre_completo, cargo FROM operario
            WHERE " . implode(' AND ', $where) . " ORDER BY nombre_completo";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['operario' => $result]);
}

// unidad_medida NO tiene columna `activo` (confirmado): usa deleted_at
// como el resto del sistema. Se incluyen unidad_base_id y equivalencia
// por si el frontend los necesita más adelante.
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

// Ensamblajes finalizados disponibles para empaquetar (grid principal).
// Incluye conteo y suma NORMALIZADA (a unidad base) de lo ya empaquetado.
// Excluye:
//   - ensamblajes ya absorbidos como complemento de otro ensamblaje
//     (ensamblaje_id_referido IS NOT NULL);
//   - ensamblajes que YA TIENEN al menos un registro de empaquetado
//     (LEFT JOIN empaquetado ee ... WHERE ee.emsamblaje_id IS NULL).
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
        "ee.emsamblaje_id IS NULL",
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
            SELECT 1 FROM empaquetado emp
            WHERE emp.emsamblaje_id = e.id AND emp.deleted_at IS NULL
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
                    SELECT COUNT(*) FROM empaquetado emp
                    WHERE emp.emsamblaje_id = e.id AND emp.deleted_at IS NULL
                ) AS empaquetados_count,
                (
                    SELECT COALESCE(SUM(
                        emp.cantidad_tota * COALESCE(um.equivalencia, 1)
                    ), 0)
                    FROM empaquetado emp
                    LEFT JOIN unidad_medida um ON um.id = emp.unidad_medida
                    WHERE emp.emsamblaje_id = e.id AND emp.deleted_at IS NULL
                ) AS cantidad_total_empaquetada
            FROM ensamblaje e
            LEFT JOIN empaquetado ee ON e.id = ee.emsamblaje_id
            LEFT JOIN producto p ON p.id = e.producto_id
            LEFT JOIN operario o ON o.id = e.operario_ortorgado
            LEFT JOIN unidad_medida us ON us.id = e.unidad_salida_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY e.fin DESC";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['ensamblajes' => $result]);
}

/**
 * Avances de producción que, según la configuración del molde/producto
 * (item.necesita_ensamblaje = 'no'), van DIRECTO a empaquetado sin pasar
 * por ensamblaje. Se listan los que ya fueron enviados
 * (enviado_ensamblaje = TRUE, seteado por clssProduccion::enviarAEnsamblaje)
 * y que aún no tienen ningún registro de empaquetado propio.
 */
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
        "ee.id IS NULL", // sin ningún registro de empaquetado todavía (mismo criterio que el grid de ensamblajes)
        "COALESCE(cfg.item->>'necesita_ensamblaje', 'no') = 'no'",
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
            SELECT 1 FROM empaquetado emp2
            WHERE emp2.produccion_id = pd.id AND emp2.deleted_at IS NULL
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
            (
                SELECT COUNT(*) FROM empaquetado emp
                WHERE emp.produccion_id = pd.id AND emp.deleted_at IS NULL
            ) AS empaquetados_count,
            (
                SELECT COALESCE(SUM(emp.cantidad_tota * COALESCE(um.equivalencia, 1)), 0)
                FROM empaquetado emp
                LEFT JOIN unidad_medida um ON um.id = emp.unidad_medida
                WHERE emp.produccion_id = pd.id AND emp.deleted_at IS NULL
            ) AS cantidad_total_empaquetada
        FROM produccion pd
        LEFT JOIN empaquetado ee ON ee.produccion_id = pd.id
        INNER JOIN producto pr ON pr.id = split_part(pd.unico_molde_producto, '-', 2)::bigint
        LEFT JOIN molde mo ON mo.id = pd.molde_id
        LEFT JOIN color co ON co.id = pd.color_id
        LEFT JOIN operario op ON op.id = pd.operario_id
        LEFT JOIN LATERAL jsonb_array_elements(pr.js_configuracion) AS x(item)
            ON (x.item->>'molde_id')::bigint = mo.id
        LEFT JOIN LATERAL (SELECT COALESCE(pd.js_configuracion_moment, x.item) AS item) cfg ON true
        WHERE " . implode(' AND ', $where) . "
        ORDER BY pd.fecha_hora_fin DESC
    ";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['producciones' => $result]);
}
// =============================================================================
// EMPAQUETADO (registro plano, con bultos internos en js_cantidades)
// =============================================================================

// Detalle de registros de UN ensamblaje (para el modal). Incluye
// equivalencia/unidad base para poder mostrar la conversión en pantalla.
function listarEmpaquetados(int $ensamblajeId)
{
    if (!$ensamblajeId) responder(false, 'ID de ensamblaje inválido.');
    $conectar = conectar_oll_BD();

    $result = executeQuery($conectar, "
        SELECT
            emp.id, emp.producto_id, emp.emsamblaje_id, emp.unidad_medida,
            emp.cantidad_tota, emp.js_cantidades,
            emp.operario_id, emp.pasado_venta, emp.venta_id_ref,
            emp.created_at, emp.update_at,
            um.nombre AS unidad_nombre, um.nombre_corto AS unidad_corto,
            um.equivalencia, um.unidad_base_id,
            ub.nombre_corto AS unidad_base_corto,
            CASE WHEN um.unidad_base_id IS NOT NULL
                 THEN emp.cantidad_tota * um.equivalencia
                 ELSE NULL END AS cantidad_tota_en_base,
            op.nombre_completo AS operario_nombre
        FROM empaquetado emp
        LEFT JOIN unidad_medida um ON um.id = emp.unidad_medida
        LEFT JOIN unidad_medida ub ON ub.id = um.unidad_base_id
        LEFT JOIN operario op ON op.id = emp.operario_id
        WHERE emp.emsamblaje_id = :ensamblaje_id AND emp.deleted_at IS NULL
        ORDER BY emp.created_at DESC
    ", ['ensamblaje_id' => $ensamblajeId]);

    responder(true, 'OK', ['empaquetados' => $result]);
}
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
            emp.operario_id, emp.pasado_venta, emp.venta_id_ref,
            emp.created_at, emp.update_at,
            um.nombre AS unidad_nombre, um.nombre_corto AS unidad_corto,
            um.equivalencia, um.unidad_base_id,
            ub.nombre_corto AS unidad_base_corto,
            CASE WHEN um.unidad_base_id IS NOT NULL
                 THEN emp.cantidad_tota * um.equivalencia
                 ELSE NULL END AS cantidad_tota_en_base,
            op.nombre_completo AS operario_nombre
        FROM empaquetado emp
        LEFT JOIN unidad_medida um ON um.id = emp.unidad_medida
        LEFT JOIN unidad_medida ub ON ub.id = um.unidad_base_id
        LEFT JOIN operario op ON op.id = emp.operario_id
        WHERE $condicion AND emp.deleted_at IS NULL
        ORDER BY emp.created_at DESC
    ", ['origen_id' => $origenId]);

    responder(true, 'OK', ['empaquetados' => $result]);
}

// Listado GENERAL (todos los ensamblajes) para la tabla que vive debajo
// del grid principal en empaquetado.php. Soporta filtro de texto
// (producto), estado (disponible / vendido) y rango de fechas
// (sobre empaquetado.created_at).
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
            emp.operario_id, op.nombre_completo AS operario_nombre,
            su.nombre AS sucursal_nombre,
            emp.pasado_venta, emp.venta_id_ref,
            emp.created_at, emp.update_at,
            um.nombre_corto AS unidad_corto, um.equivalencia, um.unidad_base_id,
            ub.nombre_corto AS unidad_base_corto,
            CASE WHEN um.unidad_base_id IS NOT NULL
                THEN emp.cantidad_tota * um.equivalencia
                ELSE NULL END AS cantidad_tota_en_base,
            CASE WHEN emp.emsamblaje_id IS NOT NULL THEN 'ensamblaje' ELSE 'produccion' END AS origen_tipo
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
    responder(true, 'OK', ['empaquetados' => $result]);
}

function obtenerEmpaquetado(int $id)
{
    if (!$id) responder(false, 'ID inválido.');
    $conectar = conectar_oll_BD();

    $result = executeQuery($conectar, "
        SELECT emp.*, um.nombre_corto AS unidad_corto
        FROM empaquetado emp
        LEFT JOIN unidad_medida um ON um.id = emp.unidad_medida
        WHERE emp.id = :id AND emp.deleted_at IS NULL
    ", ['id' => $id]);

    if (empty($result)) responder(false, 'Registro de empaquetado no encontrado o inactivo.');
    responder(true, 'OK', ['empaquetado' => $result[0]]);
}

// Normaliza el arreglo de bultos que llega del frontend (JSON de números o
// de objetos {cantidad: n}) a un arreglo limpio [{"cantidad": n}, ...],
// descartando cualquier valor <= 0. Devuelve también el total ya sumado.
function normalizarBultos($bultosEntrada): array
{
    if (!is_array($bultosEntrada)) return [[], 0.0];

    $bultos = [];
    foreach ($bultosEntrada as $b) {
        $cantidad = is_array($b) ? floatval($b['cantidad'] ?? 0) : floatval($b);
        if ($cantidad > 0) {
            $bultos[] = ['cantidad' => $cantidad];
        }
    }
    $total = array_sum(array_column($bultos, 'cantidad'));
    return [$bultos, $total];
}

function crearEmpaquetado()
{
    $conectar = conectar_oll_BD();

    $ensamblajeId = intval($_POST['ensamblaje_id'] ?? 0);
    $sucursalId = !empty($_POST['sucursal_id']) ? intval($_POST['sucursal_id']) : null;
    $produccionId = intval($_POST['produccion_id'] ?? 0);
    $unidadMedida = intval($_POST['unidad_medida'] ?? 0);
    $operarioId   = intval($_POST['operario_id'] ?? 0);
    $bultosJson   = trim($_POST['bultos'] ?? '[]');

    if (!$ensamblajeId && !$produccionId) {
        responder(false, 'Debes indicar el ensamblaje o la producción de origen.');
    }
    if ($ensamblajeId && $produccionId) {
        responder(false, 'Un empaquetado solo puede tener un origen: ensamblaje o producción, no ambos.');
    }
    if ($sucursalId !== null) {
        $suc = executeQuery($conectar, "SELECT id FROM sucursal WHERE id = :id AND delete_at IS NULL", ['id' => $sucursalId]);
        if (empty($suc)) responder(false, 'La sucursal indicada no existe o está inactiva.');
    }
    if (!$unidadMedida) responder(false, 'Debes indicar la unidad de medida.');
    if (!$operarioId) responder(false, 'Debes indicar el operario.');

    [$bultos, $cantidadTotal] = normalizarBultos(json_decode($bultosJson, true));
    if (empty($bultos)) responder(false, 'Debes registrar al menos un bulto con cantidad mayor a 0.');

    $productoId = null;

    if ($ensamblajeId) {
        $ensamblaje = executeQuery(
            $conectar,
            "SELECT id, producto_id, fin, deleted_at FROM ensamblaje WHERE id = :id",
            ['id' => $ensamblajeId]
        );
        if (empty($ensamblaje)) responder(false, 'El ensamblaje indicado no existe.');
        if (!empty($ensamblaje[0]['deleted_at'])) responder(false, 'Este ensamblaje está inactivo.');
        if (empty($ensamblaje[0]['fin'])) responder(false, 'Este ensamblaje aún no ha finalizado; no se puede empaquetar todavía.');
        $productoId = intval($ensamblaje[0]['producto_id']);
    } else {
        // Origen: producción directa (sin ensamblaje), validado contra la
        // misma configuración que usa clssProduccion::enviarAEnsamblaje().
        $prod = executeQuery(
            $conectar,
            "SELECT id, deleted_at, fecha_hora_fin, unico_molde_producto, js_configuracion_moment
             FROM produccion WHERE id = :id",
            ['id' => $produccionId]
        );
        if (empty($prod)) responder(false, 'La producción indicada no existe.');
        if (!empty($prod[0]['deleted_at'])) responder(false, 'Esta producción está inactiva.');
        if (empty($prod[0]['fecha_hora_fin'])) responder(false, 'Esta producción aún no ha finalizado su corrida.');

        $item = !empty($prod[0]['js_configuracion_moment']) ? json_decode($prod[0]['js_configuracion_moment'], true) : null;
        $necesitaEnsamblaje = empty($item['necesita_ensamblaje']) || strtolower(trim($item['necesita_ensamblaje'])) !== 'no';
        if ($necesitaEnsamblaje) {
            responder(false, 'Esta producción está configurada para pasar por ensamblaje; no puede empaquetarse directamente.');
        }

        $yaUsada = executeQuery($conectar, "SELECT id FROM empaquetado WHERE produccion_id = :id", ['id' => $produccionId]);
        if (!empty($yaUsada)) responder(false, 'Esta producción ya tiene un registro de empaquetado.');

        $partes = explode('-', $prod[0]['unico_molde_producto'] ?? '');
        $productoId = isset($partes[1]) ? intval($partes[1]) : 0;
        if (!$productoId) responder(false, 'No se pudo determinar el producto de esta producción.');
    }

    $unidad = executeQuery($conectar, "SELECT id FROM unidad_medida WHERE id = :id AND deleted_at IS NULL", ['id' => $unidadMedida]);
    if (empty($unidad)) responder(false, 'La unidad de medida indicada no existe o está inactiva.');

    $operario = executeQuery($conectar, "SELECT id FROM operario WHERE id = :id AND activo = true", ['id' => $operarioId]);
    if (empty($operario)) responder(false, 'El operario indicado no existe o está inactivo.');

    $origenTexto = $ensamblajeId ? "ensamblaje #$ensamblajeId" : "producción #$produccionId";
    $cambios = [[
        'campo' => 'Empaquetado', 'valor_antes' => '(nuevo)',
        'valor_despues' => count($bultos) . " bulto(s), total $cantidadTotal, origen: $origenTexto",
    ]];
    $movimiento   = obtenerMovimientoSesionEmp('crear', $cambios);
    $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);
    $js_cantidades = json_encode($bultos, JSON_UNESCAPED_UNICODE);

    $nuevo = executeQuery($conectar, "
        INSERT INTO empaquetado (
            producto_id, emsamblaje_id, produccion_id, unidad_medida, operario_id, sucursal,
            cantidad_tota, js_cantidades,
            created_at, js_session, js_historial
        ) VALUES (
            :producto_id, :emsamblaje_id, :produccion_id, :unidad_medida, :operario_id, :sucursal_id,
            :cantidad_tota, :js_cantidades,
            NOW(), :js_session, :js_historial
        ) RETURNING id
    ", [
        'producto_id'    => $productoId,
        'emsamblaje_id'  => $ensamblajeId ?: null,
        'produccion_id'  => $produccionId ?: null,
        'unidad_medida'  => $unidadMedida,
        'operario_id'    => $operarioId,
        'sucursal_id'    => $sucursalId,
        'cantidad_tota'  => $cantidadTotal,
        'js_cantidades'  => $js_cantidades,
        'js_session'     => $js_session,
        'js_historial'   => $js_historial,
    ]);

    responder(true, 'Empaquetado registrado correctamente.', ['id' => $nuevo[0]['id'] ?? null]);
}
function editarEmpaquetado()
{
    $conectar = conectar_oll_BD();

    $id           = intval($_POST['id'] ?? 0);
    $sucursalId = !empty($_POST['sucursal_id']) ? intval($_POST['sucursal_id']) : null;
    $unidadMedida = intval($_POST['unidad_medida'] ?? 0);
    $operarioId   = intval($_POST['operario_id'] ?? 0);
    $bultosJson   = trim($_POST['bultos'] ?? '[]');

    if (!$id) responder(false, 'ID inválido.');
    if (!$sucursalId) responder(false, 'Debes indicar la sucursal.');
    if (!$unidadMedida) responder(false, 'Debes indicar la unidad de medida.');
    if (!$operarioId) responder(false, 'Debes indicar el operario.');

    [$bultos, $cantidadTotal] = normalizarBultos(json_decode($bultosJson, true));
    if (empty($bultos)) responder(false, 'Debes registrar al menos un bulto con cantidad mayor a 0.');

    $actual = executeQuery($conectar, "SELECT * FROM empaquetado WHERE id = :id AND deleted_at IS NULL", ['id' => $id]);
    if (empty($actual)) responder(false, 'Registro de empaquetado no encontrado o inactivo.');
    if (!empty($actual[0]['pasado_venta'])) {
        responder(false, 'Este registro ya pasó a venta y no se puede editar.');
    }

    $unidad = executeQuery($conectar, "SELECT id FROM unidad_medida WHERE id = :id AND deleted_at IS NULL", ['id' => $unidadMedida]);
    if (empty($unidad)) responder(false, 'La unidad de medida indicada no existe o está inactiva.');

    $operario = executeQuery($conectar, "SELECT id FROM operario WHERE id = :id AND activo = true", ['id' => $operarioId]);
    if (empty($operario)) responder(false, 'El operario indicado no existe o está inactivo.');

    $cambios = [[
        'campo' => 'Empaquetado',
        'valor_antes' => "{$actual[0]['cantidad_tota']} / unidad #{$actual[0]['unidad_medida']}",
        'valor_despues' => "$cantidadTotal / unidad #$unidadMedida",
    ]];
    $movimiento   = obtenerMovimientoSesionEmp('editar', $cambios);
    $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);
    $js_cantidades = json_encode($bultos, JSON_UNESCAPED_UNICODE);

    executeNonQuery($conectar, "
        UPDATE empaquetado SET
            unidad_medida  = :unidad_medida,
            operario_id    = :operario_id,
            sucursal        = :sucursal_id,
            cantidad_tota  = :cantidad_tota,
            js_cantidades  = :js_cantidades,
            update_at      = NOW(),
            js_session     = :js_session,
            js_historial   = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id
    ", [
        'unidad_medida' => $unidadMedida,
        'operario_id'   => $operarioId,
        'sucursal_id'   => $sucursalId,
        'cantidad_tota' => $cantidadTotal,
        'js_cantidades' => $js_cantidades,
        'js_session'    => $js_session,
        'js_historial'  => $js_historial,
        'id'            => $id,
    ]);

    responder(true, 'Empaquetado actualizado correctamente.', ['id' => $id]);
}

// Soft-delete. No se permite si ya pasó a venta (pasado_venta con valor).
function eliminarEmpaquetado(int $id)
{
    if (!$id) responder(false, 'ID inválido.');
    $conectar = conectar_oll_BD();

    $existe = executeQuery($conectar, "SELECT id, pasado_venta FROM empaquetado WHERE id = :id AND deleted_at IS NULL", ['id' => $id]);
    if (empty($existe)) responder(false, 'Registro de empaquetado no encontrado o ya inactivo.');
    if (!empty($existe[0]['pasado_venta'])) {
        responder(false, 'Este registro ya pasó a venta y no se puede eliminar.');
    }

    $cambios = [['campo' => 'Estado', 'valor_antes' => 'Activo', 'valor_despues' => 'Inactivo']];
    $movimiento   = obtenerMovimientoSesionEmp('desactivar', $cambios);
    $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeNonQuery($conectar, "
        UPDATE empaquetado SET
            deleted_at   = NOW(),
            update_at    = NOW(),
            js_session   = :js_session,
            js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id
    ", ['id' => $id, 'js_session' => $js_session, 'js_historial' => $js_historial]);

    responder(true, 'Empaquetado eliminado correctamente.');
}

function reactivarEmpaquetado(int $id)
{
    if (!$id) responder(false, 'ID inválido.');
    $conectar = conectar_oll_BD();

    $existe = executeQuery($conectar, "SELECT id, deleted_at FROM empaquetado WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Registro de empaquetado no encontrado.');
    if (empty($existe[0]['deleted_at'])) responder(false, 'Este registro ya estaba activo.');

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

    responder(true, 'Empaquetado reactivado correctamente.');
}

// =============================================================================
// AUDITORÍA (idéntico patrón al resto de controladores)
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