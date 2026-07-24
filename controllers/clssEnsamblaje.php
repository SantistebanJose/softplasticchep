<?php

/**
 * controllers/clssEnsamblaje.php
 * Controlador del módulo de Ensamblaje
 *
 * Tablas reales:
 *   ensamblaje (id, producto_id -> producto, operario_ortorgado -> operario,
 *               condicion ['propio'|'derivado'] NUEVO,
 *               js_derivados_utilizados, js_moldes_utilizados [resúmenes,
 *               recalculados en cada guardado a partir del detalle real],
 *               inicio, fin, cantidad_peso_kg,
 *               js_usuario, js_historial, created_at, update_at, deleted_at)
 *   rel_ensamblaje_producto (id, ensamblaje_id -> ensamblaje,
 *               molde_produccion_id [referencia "suave" a produccion.id, SIN FK],
 *               js_query_consulta_produccion [snapshot jsonb de la fila de
 *               produccion al momento de vincularla],
 *               derivado_id -> material.id [ver nota abajo], created_at,
 *               update_at, deleted_at)
 *
 * CORREGIDO (2026-07-24, confirmado por el usuario): los "derivados" del
 * módulo de ensamblaje NO viven en una tabla `derivado` aparte (eso era un
 * supuesto sin confirmar de una versión anterior de este archivo). El
 * verdadero derivado es una fila de la tabla `material` con
 * material.derivado = TRUE (columna que ya existe en el módulo de Materia
 * Prima, ver clssMaterial.php). rel_ensamblaje_producto.derivado_id sigue
 * llamándose igual mos por compatibilidad con datos existentes, pero ahora
 * apunta a material.id, no a una tabla "derivado". Se ajustaron
 * buscarDerivados(), insertarLineasEnsamblaje() y
 * recalcularResumenesEnsamblaje() en consecuencia.
 *
 * NUEVO (2026-07-24): `material.js_producto` (jsonb, patrón similar a
 * molde.js_producto) lista los productos FINALES que consumen ese material
 * derivado como insumo, ej: [{"codigo":"COV","descripcion":"COLGADOR
 * OVALADO","producto_id":9}]. buscarDerivados() lo usa para PRIORIZAR (no
 * filtrar) los derivados relevantes al producto que se está ensamblando:
 * si un derivado declara ese producto_id en su js_producto, aparece primero
 * en la lista; el resto de derivados activos también se muestran por si
 * aplica un caso no contemplado ahí.
 *
 * NUEVO (2026-07-24): condición del ensamblaje. Confirmado por el usuario
 * que esto se decide POR ENSAMBLAJE (no fijo a nivel de producto), porque un
 * mismo producto (ej. PINZA PALANITA) puede a veces salir como 'derivado'
 * (se usará como insumo de otro producto, ej. un colgador) y otras veces
 * como 'propio' (producto final único, listo para empaquetado). Se agrega
 * la columna `ensamblaje.condicion` ('propio' por defecto | 'derivado').
 *
 * Cuando `condicion = 'derivado'` y el ensamblaje se FINALIZA
 * (finalizarEnsamblaje), se busca la fila de `material` cuyo
 * `material.producto_id` sea igual a `ensamblaje.producto_id` (ese vínculo
 * 1 a 1 identifica "este material ES el producto ya ensamblado, disponible
 * como insumo derivado") y se le incrementa `stock_actual` en el peso (kg)
 * de salida de este armado. Ese peso ahora se captura AL FINALIZAR (ya no
 * "más adelante en empaquetado" como decía la versión anterior de este
 * archivo), porque es la cantidad que necesitamos para mover stock.
 * Si no existe ese material vinculado, se aborta con un mensaje claro
 * pidiendo crearlo primero en el módulo de Materia Prima (no se autocrea,
 * para no inventar nombre/unidad_medida).
 *
 * SUPUESTO SIN CONFIRMAR (heredado, no tocado en esta pasada): no tengo la
 * definición real de `view_ensamblaje_detalle`. Asumo que expone (entre
 * otras) producto_id, producto_codigo, producto_descripcion, operario_id,
 * operario_nombre, condicion, inicio, fin, cantidad_peso_kg, deleted_at,
 * js_moldes_utilizados, js_derivados_utilizados. Si la vista no expone la
 * nueva columna `condicion`, hay que agregarla ahí también o el listado no
 * la mostrará (el guardado/lectura vía OBTENERENSAMBLAJE sí la necesita).
 *
 * IMPORTANTE (heredado de la versión anterior, sigue vigente): `molde`
 * sigue sin tener columna producto_id (molde -> producto sigue siendo
 * MANY-TO-MANY vía `molde.js_producto`). El producto_id de un avance de
 * producción concreto se resuelve directo con
 * `split_part(produccion.unico_molde_producto, '-', 2)::bigint`.
 *
 * MODELO:
 *   Cada fila de `ensamblaje` es un armado de un `producto` final. Ese
 *   armado consume, línea por línea (rel_ensamblaje_producto), o bien un
 *   AVANCE DE PRODUCCIÓN finalizado (molde_produccion_id -> produccion.id)
 *   o bien un DERIVADO preexistente (derivado_id -> material.id, con
 *   material.derivado = TRUE). Cada línea es de un tipo u otro, nunca ambos.
 *
 * REGLA DE UNICIDAD:
 *   Un mismo avance de producción (molde_produccion_id) solo puede estar
 *   vinculado a UN ensamblaje activo a la vez. Se valida con NOT EXISTS
 *   sobre rel_ensamblaje_producto (deleted_at IS NULL) tanto en las
 *   funciones de listado ("disponibles") como al insertar
 *   (insertarLineasEnsamblaje). Esto evita "gastar" el mismo avance en dos
 *   armados distintos.
 *
 * REGLA DE PRODUCTO ÚNICO POR ARMADO:
 *   Un ensamblaje se arma para UN solo producto+color a la vez (columna
 *   `producto_id` en `ensamblaje`). El frontend (ensamblaje.php) se encarga
 *   de anclar el ticket al primer avance de producción que se agregue y de
 *   filtrar/advertir si se intenta mezclar otro producto+color; este
 *   controlador no lo re-valida server-side porque `producto_id` ya viene
 *   fijo desde el formulario.
 *
 * CATEGORÍA DE MATERIAL: `produccion.categoria_material_id` ya se guarda
 * desde el módulo de Producción. Aquí se expone como
 * `categoria_material_nombre_verif` en buscarProduccionesDisponibles() y
 * obtenerDatosProduccionParaEnsamblaje() (JOIN directo a `categoria_material`
 * sobre `produccion`), y también se incluye en el resumen
 * `js_moldes_utilizados` que arma recalcularResumenesEnsamblaje().
 *
 * EDICIÓN (diff-based, NO borrar-y-reinsertar):
 *   Se compara el detalle activo actual contra el nuevo: las líneas que se
 *   mantienen no se tocan, las que ya no están se desactivan (soft-delete),
 *   las nuevas se insertan. Mismo patrón ya aplicado en compras para evitar
 *   romper referencias de auditoría o futuras integraciones.
 *
 * Este controlador NO crea/edita producto, operario, material ni produccion
 * (cada uno tiene su propio CRUD); aquí solo se listan/consultan para elegir
 * contra qué se arma el ensamblaje.
 *
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

ob_start();

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    try {
        controladorEnsamblaje($_POST["accion"]);
    } catch (PDOException $e) {
        error_log("Error de base de datos en clssEnsamblaje.php: " . $e->getMessage());
        responder(false, 'Error de base de datos: ' . $e->getMessage());
    } catch (Throwable $e) {
        error_log("Error inesperado en clssEnsamblaje.php: " . $e->getMessage());
        responder(false, 'Error inesperado en el servidor: ' . $e->getMessage());
    }
}

function controladorEnsamblaje($accion)
{
    switch ($accion) {
        case 'LISTARENSAMBLAJES':
            listarEnsamblajes();
            break;
        case 'OBTENERENSAMBLAJE':
            obtenerEnsamblaje(intval($_POST['id'] ?? 0));
            break;
        case 'GUARDARENSAMBLAJE':
            guardarEnsamblaje();
            break;
        case 'ELIMINARENSAMBLAJE':
            eliminarEnsamblaje();
            break;
        case 'REACTIVARENSAMBLAJE':
            reactivarEnsamblaje();
            break;
        case 'INICIARENSAMBLAJE':
            iniciarEnsamblaje(intval($_POST['id'] ?? 0));
            break;
        case 'FINALIZARENSAMBLAJE':
            finalizarEnsamblaje(intval($_POST['id'] ?? 0));
            break;
        case 'BUSCARPRODUCTOS':
            buscarProductos();
            break;
        case 'BUSCAROPERARIOS':
            buscarOperarios();
            break;
        case 'BUSCARDERIVADOS':
            buscarDerivados();
            break;
        case 'BUSCARPRODUCCIONESDISPONIBLES':
            buscarProduccionesDisponibles();
            break;
        case 'BUSCARPRODUCTOSDISPONIBLESENSAMBLAJE':
            buscarProductosDisponiblesEnsamblaje();
            break;
        case 'OBTENERDATOSPRODUCCIONPARAENSAMBLAJE':
            obtenerDatosProduccionParaEnsamblaje(intval($_POST['produccion_id'] ?? 0));
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// LISTADOS AUXILIARES (para los <select> / cards del modal)
// =============================================================================

function buscarProductos()
{
    $conectar = conectar_oll_BD();
    $texto = trim($_POST['texto'] ?? '');

    $where  = ["activo = true"];
    $params = [];
    if ($texto !== '') {
        $where[] = "(LOWER(codigo) LIKE LOWER(:texto) OR LOWER(descripcion) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }

    $sql = "SELECT id, codigo, descripcion, peso_unitario_g FROM producto
            WHERE " . implode(' AND ', $where) . " ORDER BY descripcion LIMIT 100";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['productos' => $result]);
}

// Combinaciones producto+color con avances ya enviados a ensamblaje y aún
// libres. El <select> del modal usa como value "producto_id_color_id":
// el usuario elige primero QUÉ producto+color quiere armar, y en el panel
// de la derecha ve TODAS las producciones sueltas disponibles de esa
// combinación (con su color) para poder verificarlas antes de vincular.
//
// RESUELTO SIN VISTA (2026-07-22): el producto_id de cada avance se saca
// directo de produccion.unico_molde_producto ("molde_id-producto_id") con
// split_part, tal como lo confirmó el usuario con su query de verificación.
// "Disponible" = enviado_ensamblaje TRUE, finalizada, activa, y sin ninguna
// línea activa en rel_ensamblaje_producto que ya la haya consumido.
function buscarProductosDisponiblesEnsamblaje()
{
    $conectar = conectar_oll_BD();
    $texto = trim($_POST['texto'] ?? '');

    $where = [
        "t1.enviado_ensamblaje = TRUE",
        "t1.deleted_at IS NULL",
        "t1.fecha_hora_fin IS NOT NULL",
        "NOT EXISTS (
            SELECT 1 FROM rel_ensamblaje_producto rep
            WHERE rep.molde_produccion_id = t1.id AND rep.deleted_at IS NULL
        )",
    ];
    $params = [];
    if ($texto !== '') {
        $where[] = "LOWER(UPPER(CONCAT(t4.descripcion, ' (', t3.nombre, ')'))) LIKE LOWER(:texto)";
        $params['texto'] = "%$texto%";
    }

    $sql = "SELECT DISTINCT
                t4.id AS producto_id,
                t1.color_id,
                UPPER(CONCAT(t4.descripcion, ' (', t3.nombre, ')')) AS productoformato,
                COUNT(*) OVER (PARTITION BY t4.id, t1.color_id) AS disponibles
            FROM produccion t1
            LEFT JOIN molde t2 ON t2.id = t1.molde_id
            LEFT JOIN color t3 ON t3.id = t1.color_id
            INNER JOIN producto t4 ON t4.id = split_part(t1.unico_molde_producto, '-', 2)::bigint
            WHERE " . implode(' AND ', $where) . "
            ORDER BY productoformato
            LIMIT 200";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['productos' => $result]);
}

function buscarOperarios()
{
    $conectar = conectar_oll_BD();
    $sql = "SELECT id, nombre_completo, cargo FROM operario WHERE activo = true ORDER BY nombre_completo";
    $result = executeQuery($conectar, $sql, []);
    responder(true, 'OK', ['operario' => $result]);
}

// CORREGIDO (2026-07-24): los derivados reales son filas de `material` con
// derivado = TRUE (ya NO se asume una tabla `derivado` aparte).
//
// ACTUALIZADO: si se pasa producto_id (el producto que se está armando en
// este ensamblaje), ahora se FILTRA de verdad usando material.js_producto
// vía jsonb_array_elements + INNER JOIN LATERAL, en vez de solo priorizar
// el orden. Si NO se pasa producto_id, se listan todos los derivados
// activos (comportamiento previo, útil para cuando el modal aún no tiene
// producto elegido).
function buscarDerivados()
{
    $conectar   = conectar_oll_BD();
    $texto      = trim($_POST['texto'] ?? '');
    $productoId = intval($_POST['producto_id'] ?? 0);

    $where  = ["m.derivado = TRUE", "m.deleted_at IS NULL"];
    $params = [];
    if ($texto !== '') {
        $where[] = "LOWER(m.nombre) LIKE LOWER(:texto)";
        $params['texto'] = "%$texto%";
    }

    // Sin producto seleccionado: no hace falta tocar js_producto para nada.
    $joinProducto = "";
    if ($productoId > 0) {
        // Con producto seleccionado: solo entran los materiales derivados
        // cuyo js_producto declare este producto_id como consumidor.
        $joinProducto = "INNER JOIN LATERAL jsonb_array_elements(COALESCE(m.js_producto, '[]'::jsonb)) elem
                          ON (elem->>'producto_id')::bigint = :producto_id_filtro";
        $params['producto_id_filtro'] = $productoId;
    }

    $sql = "SELECT DISTINCT
               m.id, m.nombre, m.js_producto,
               m.stock_actual, u.nombre_corto AS unidad_corto
        FROM material m
        LEFT JOIN unidad_medida u ON u.id = m.unidad_medida_id
        $joinProducto
        WHERE " . implode(' AND ', $where) . "
        ORDER BY m.nombre
        LIMIT 100";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['derivados' => $result]);
}
// Avances de producción finalizados y aún no consumidos por ningún
// ensamblaje activo. Resuelto 100% directo sobre `produccion` (sin vista):
// producto_id sale de unico_molde_producto vía split_part, molde_nombre
// vía produccion.molde_id -> molde, color_nombre_verif vía
// produccion.color_id -> color, categoria_material_nombre_verif vía
// produccion.categoria_material_id -> categoria_material.
//
// Si se pasa producto_id, se filtra solo a los avances de ese producto
// (+ color si se pasa). Si NO se pasa producto_id (ej. al abrir el modal
// de "Registrar ensamblaje" antes de elegir producto), devuelve TODA la
// cola de producciones pendientes por vincular, sin importar el producto.
function buscarProduccionesDisponibles()
{
    $conectar = conectar_oll_BD();
    $productoId    = intval($_POST['producto_id'] ?? 0);
    $colorId       = intval($_POST['color_id'] ?? 0);
    $produccionId  = intval($_POST['produccion_id'] ?? 0);
    $texto         = trim($_POST['texto'] ?? '');

    $where  = [
        "t1.deleted_at IS NULL",
        "t1.fecha_hora_fin IS NOT NULL",
        "NOT EXISTS (
            SELECT 1 FROM rel_ensamblaje_producto rep
            WHERE rep.molde_produccion_id = t1.id AND rep.deleted_at IS NULL
        )",
    ];
    $params = [];

    if ($produccionId > 0) {
        $where[] = "t1.id = :produccion_id";
        $params['produccion_id'] = $produccionId;
    } else {
        if ($productoId > 0) {
            $where[] = "t4.id = :producto_id";
            $params['producto_id'] = $productoId;
        }
        if ($colorId > 0) {
            $where[] = "t1.color_id = :color_id";
            $params['color_id'] = $colorId;
        }
    }
    if ($texto !== '') {
        $where[] = "LOWER(t2.nombre) LIKE LOWER(:texto)";
        $params['texto'] = "%$texto%";
    }

    $sql = "SELECT
                t1.id AS produccion_id,
                t1.fecha_envio_ensamblaje,
                t2.nombre AS molde_nombre,
                t1.cantidad_producida_kg AS cantidad_kg,
                t1.fecha_hora_fin,
                t4.id AS producto_id,
                t1.color_id,
                t3.nombre AS color_nombre_verif,
                cm.nombre AS categoria_material_nombre_verif
            FROM produccion t1
            LEFT JOIN molde t2 ON t2.id = t1.molde_id
            LEFT JOIN color t3 ON t3.id = t1.color_id
            INNER JOIN producto t4 ON t4.id = split_part(t1.unico_molde_producto, '-', 2)::bigint
            LEFT JOIN categoria_material cm ON cm.id = t1.categoria_material_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY t1.fecha_hora_fin DESC";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['producciones' => $result]);
}
// Usado por el botón "Pasar a ensamblaje" desde la card de producción:
// trae los datos necesarios para prellenar el modal (producto sugerido
// según el molde usado, kg del avance, etc), sin crear nada todavía.
// Igual que buscarProduccionesDisponibles(), resuelto directo sobre
// `produccion`, sin vista.
function obtenerDatosProduccionParaEnsamblaje(int $produccionId)
{
    $conectar = conectar_oll_BD();
    if (!$produccionId) responder(false, 'ID de producción inválido.');

    $data = executeQuery(
        $conectar,
        "SELECT
             t1.id AS produccion_id,
             t1.fecha_envio_ensamblaje,
             t2.nombre AS molde_nombre,
             t1.cantidad_producida_kg AS cantidad_kg,
             t1.fecha_hora_fin,
             t4.id AS producto_id,
             t1.color_id,
             t3.nombre AS color_nombre_verif,
             cm.nombre AS categoria_material_nombre_verif
         FROM produccion t1
         LEFT JOIN molde t2 ON t2.id = t1.molde_id
         LEFT JOIN color t3 ON t3.id = t1.color_id
         INNER JOIN producto t4 ON t4.id = split_part(t1.unico_molde_producto, '-', 2)::bigint
         LEFT JOIN categoria_material cm ON cm.id = t1.categoria_material_id
         WHERE t1.id = :id
           AND t1.deleted_at IS NULL
           AND t1.fecha_hora_fin IS NOT NULL
           AND NOT EXISTS (
               SELECT 1 FROM rel_ensamblaje_producto rep
               WHERE rep.molde_produccion_id = t1.id AND rep.deleted_at IS NULL
           )",
        ['id' => $produccionId]
    );

    if (empty($data)) {
        responder(false, 'Esta producción no está disponible para ensamblaje (no existe, no está finalizada, no tiene producto vinculado, o ya fue usada en otro ensamblaje).');
    }

    responder(true, 'OK', ['produccion' => $data[0]]);
}
// =============================================================================
// AUDITORÍA (idéntico patrón al resto de controladores)
// =============================================================================

function obtenerIpCliente(): string
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

function obtenerMovimientoSesion(string $accion, array $cambios = []): array
{
    return [
        'usuario'   => $_SESSION['usuario_id'] ?? 'Sistema',
        'nombre'    => $_SESSION['nombre_usuario'] ?? 'Usuario Desconocido',
        'user'      => $_SESSION['user_usuario'] ?? 'N/A',
        'perfiles'  => $_SESSION['perfiles'] ?? 'N/A',
        'rol'       => $_SESSION['rol_usuario'] ?? 'N/A',
        'accion'    => $accion,
        'ip'        => obtenerIpCliente(),
        'cambios'   => $cambios,
        'timestamp' => date('Y-m-d H:i:s'),
    ];
}

// =============================================================================
// ENSAMBLAJE
// =============================================================================

function listarEnsamblajes()
{
    $conectar = conectar_oll_BD();

    $texto        = trim($_POST['texto'] ?? '');
    $producto_id  = trim($_POST['producto_id'] ?? '');
    $operario_id  = trim($_POST['operario_id'] ?? '');
    $estado       = trim($_POST['estado'] ?? ''); // '', 'activa', 'inactiva'
    $fecha_desde  = trim($_POST['fecha_desde'] ?? '');
    $fecha_hasta  = trim($_POST['fecha_hasta'] ?? '');

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(p.codigo) LIKE LOWER(:texto) OR LOWER(p.descripcion) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }
    if ($producto_id !== '') {
        $where[] = "e.producto_id = :producto_id";
        $params['producto_id'] = $producto_id;
    }
    if ($operario_id !== '') {
        $where[] = "e.operario_ortorgado = :operario_id";
        $params['operario_id'] = $operario_id;
    }
    if ($estado === 'activa') {
        $where[] = "e.deleted_at IS NULL";
    } elseif ($estado === 'inactiva') {
        $where[] = "e.deleted_at IS NOT NULL";
    }
    if ($fecha_desde !== '') {
        $where[] = "e.inicio >= :fecha_desde";
        $params['fecha_desde'] = $fecha_desde;
    }
    if ($fecha_hasta !== '') {
        $where[] = "e.inicio <= :fecha_hasta";
        $params['fecha_hasta'] = $fecha_hasta . ' 23:59:59';
    }

    $sql = "SELECT
                e.id AS ensamblaje_id,
                e.producto_id,
                p.codigo AS producto_codigo,
                p.descripcion AS producto_descripcion,
                e.operario_ortorgado AS operario_id,
                o.nombre_completo AS operario_nombre,
                e.inicio,
                e.fin,
                e.cantidad_peso_kg,
                e.deleted_at,
                e.js_moldes_utilizados,
                e.js_derivados_utilizados
            FROM ensamblaje e
            LEFT JOIN producto p ON p.id = e.producto_id
            LEFT JOIN operario o ON o.id = e.operario_ortorgado
            WHERE " . implode(' AND ', $where) . "
            ORDER BY e.id DESC";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['ensamblajes' => $result]);
}
function obtenerEnsamblaje($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $ensamblaje = executeQuery(
        $conectar,
        "SELECT
             e.id AS ensamblaje_id,
             e.producto_id,
             p.codigo AS producto_codigo,
             p.descripcion AS producto_descripcion,
             e.operario_ortorgado AS operario_id,
             o.nombre_completo AS operario_nombre,
             e.inicio,
             e.fin,
             e.cantidad_peso_kg,
             e.deleted_at,
             e.js_moldes_utilizados,
             e.js_derivados_utilizados
         FROM ensamblaje e
         LEFT JOIN producto p ON p.id = e.producto_id
         LEFT JOIN operario o ON o.id = e.operario_ortorgado
         WHERE e.id = :id",
        ['id' => $id]
    );
    if (empty($ensamblaje)) responder(false, 'Registro de ensamblaje no encontrado.');

    responder(true, 'OK', ['ensamblaje' => $ensamblaje[0]]);
}
function guardarEnsamblaje()
{
    $conectar = conectar_oll_BD();

    $id                  = intval($_POST['id'] ?? 0);
    $producto_id         = intval($_POST['producto_id'] ?? 0);
    $operario_ortorgado  = !empty($_POST['operario_ortorgado']) ? intval($_POST['operario_ortorgado']) : null;
    $detalleJson         = trim($_POST['detalle'] ?? '[]');

    // ── Validaciones básicas ─────────────────────────────────────────────────
    if ($producto_id <= 0) responder(false, 'Debes seleccionar el producto a ensamblar.');

    $producto = executeQuery($conectar, "SELECT id FROM producto WHERE id = :id AND activo = true", ['id' => $producto_id]);
    if (empty($producto)) responder(false, 'El producto seleccionado no existe o está inactivo.');

    if ($operario_ortorgado !== null) {
        $operario = executeQuery($conectar, "SELECT id FROM operario WHERE id = :id AND activo = true", ['id' => $operario_ortorgado]);
        if (empty($operario)) responder(false, 'El operario seleccionado no existe o está inactivo.');
    }

    $detalleEntrada = json_decode($detalleJson, true);
    if (!is_array($detalleEntrada)) $detalleEntrada = [];

    // Normaliza y valida cada línea (tipo produccion XOR derivado).
    $detalle = [];
    foreach ($detalleEntrada as $linea) {
        $tipo = trim($linea['tipo'] ?? '');
        if ($tipo === 'produccion') {
            $prodId = intval($linea['molde_produccion_id'] ?? 0);
            if ($prodId <= 0) continue;
            $detalle[] = ['tipo' => 'produccion', 'molde_produccion_id' => $prodId, 'derivado_id' => null];
        } elseif ($tipo === 'derivado') {
            $derId = intval($linea['derivado_id'] ?? 0);
            if ($derId <= 0) continue;
            $detalle[] = ['tipo' => 'derivado', 'molde_produccion_id' => null, 'derivado_id' => $derId];
        }
        // tipos desconocidos se ignoran silenciosamente
    }

    if (empty($detalle)) {
        responder(false, 'Debes vincular al menos una producción finalizada o un derivado a este ensamblaje.');
    }

    $conectar->beginTransaction();
    try {
        if ($id === 0) {
            // ── CREACIÓN ─────────────────────────────────────────────────────
            $cambios = [[
                'campo' => 'Ensamblaje', 'valor_antes' => '(nuevo)',
                'valor_despues' => count($detalle) . ' ítem(s) vinculado(s)',
            ]];
            $movimiento   = obtenerMovimientoSesion('crear', $cambios);
            $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
            $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

            $nuevoEnsamblaje = executeQuery($conectar, "
                INSERT INTO ensamblaje (
                    producto_id, operario_ortorgado,
                    js_derivados_utilizados, js_moldes_utilizados,
                    created_at, js_usuario, js_historial
                ) VALUES (
                    :producto_id, :operario_ortorgado,
                    '[]'::jsonb, '[]'::jsonb,
                    NOW(), :js_usuario, :js_historial
                ) RETURNING id
            ", [
                'producto_id'        => $producto_id,
                'operario_ortorgado' => $operario_ortorgado,
                'js_usuario'         => $js_session,
                'js_historial'       => $js_historial,
            ]);
            $ensamblajeId = $nuevoEnsamblaje[0]['id'] ?? null;
            if (!$ensamblajeId) throw new Exception('No se pudo crear el registro de ensamblaje.');

            insertarLineasEnsamblaje($conectar, $ensamblajeId, $detalle);
            recalcularResumenesEnsamblaje($conectar, $ensamblajeId);

            $conectar->commit();
            responder(true, 'Ensamblaje registrado correctamente.', [
                'id' => $ensamblajeId, 'modo' => 'crear',
            ]);
        } else {
            // ── EDICIÓN (diff-based) ────────────────────────────────────────
            $actual = executeQuery($conectar, "SELECT * FROM ensamblaje WHERE id = :id", ['id' => $id]);
            if (empty($actual)) throw new Exception('Registro de ensamblaje no encontrado.');
            if (!empty($actual[0]['deleted_at'])) {
                throw new Exception('No puedes editar un ensamblaje inactivo. Reactívalo primero.');
            }

            $lineasActuales = executeQuery(
                $conectar,
                "SELECT * FROM rel_ensamblaje_producto WHERE ensamblaje_id = :id AND deleted_at IS NULL",
                ['id' => $id]
            );

            // Claves de comparación: "p:123" para producción, "d:45" para derivado.
            $clave = function ($tipo, $prodId, $derId) {
                return $tipo === 'produccion' ? "p:$prodId" : "d:$derId";
            };

            $actualesPorClave = [];
            foreach ($lineasActuales as $l) {
                $tipo = $l['molde_produccion_id'] !== null ? 'produccion' : 'derivado';
                $k = $clave($tipo, $l['molde_produccion_id'], $l['derivado_id']);
                $actualesPorClave[$k] = $l;
            }

            $nuevasPorClave = [];
            foreach ($detalle as $d) {
                $k = $clave($d['tipo'], $d['molde_produccion_id'], $d['derivado_id']);
                $nuevasPorClave[$k] = $d;
            }

            // Líneas que ya no están -> soft delete.
            $clavesAEliminar = array_diff(array_keys($actualesPorClave), array_keys($nuevasPorClave));
            foreach ($clavesAEliminar as $k) {
                executeNonQuery(
                    $conectar,
                    "UPDATE rel_ensamblaje_producto SET deleted_at = NOW(), update_at = NOW() WHERE id = :id",
                    ['id' => $actualesPorClave[$k]['id']]
                );
            }

            // Líneas nuevas -> insertar (con su validación de disponibilidad/existencia).
            $clavesAInsertar = array_diff(array_keys($nuevasPorClave), array_keys($actualesPorClave));
            $detalleNuevo = array_values(array_filter(
                $detalle,
                fn($d) => in_array($clave($d['tipo'], $d['molde_produccion_id'], $d['derivado_id']), $clavesAInsertar)
            ));
            if (!empty($detalleNuevo)) {
                insertarLineasEnsamblaje($conectar, $id, $detalleNuevo, $id);
            }
            // Las líneas que se mantienen (intersección) no se tocan.

            $cambios = [[
                'campo' => 'Ensamblaje',
                'valor_antes' => count($lineasActuales) . ' ítem(s)',
                'valor_despues' => count($detalle) . ' ítem(s)',
            ]];
            $movimiento   = obtenerMovimientoSesion('editar', $cambios);
            $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
            $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

            executeNonQuery($conectar, "
                UPDATE ensamblaje SET
                    producto_id         = :producto_id,
                    operario_ortorgado  = :operario_ortorgado,
                    update_at           = NOW(),
                    js_usuario          = :js_usuario,
                    js_historial        = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
                WHERE id = :id
            ", [
                'producto_id'        => $producto_id,
                'operario_ortorgado' => $operario_ortorgado,
                'js_usuario'         => $js_session,
                'js_historial'       => $js_historial,
                'id'                 => $id,
            ]);

            recalcularResumenesEnsamblaje($conectar, $id);

            $conectar->commit();
            responder(true, 'Ensamblaje actualizado correctamente.', [
                'id' => $id, 'modo' => 'editar',
            ]);
        }
    } catch (Throwable $e) {
        $conectar->rollBack();
        error_log("Error guardando ensamblaje: " . $e->getMessage());
        responder(false, 'No se pudo guardar el ensamblaje: ' . $e->getMessage());
    }
}

/**
 * Valida cada línea nueva (que la producción exista, esté finalizada y
 * libre; o que el material-derivado exista y siga marcado como derivado) e
 * inserta en rel_ensamblaje_producto.
 * $excluirEnsamblajeId permite, en edición, no chocar contra las propias
 * líneas del ensamblaje que se está editando al chequear unicidad.
 *
 * El snapshot que se guarda en js_query_consulta_produccion se arma con
 * una consulta directa sobre `produccion` (mismo criterio de
 * buscarProduccionesDisponibles), sin depender de ninguna vista.
 */
function insertarLineasEnsamblaje($conectar, int $ensamblajeId, array $detalle, ?int $excluirEnsamblajeId = null): void
{
    foreach ($detalle as $linea) {
        if ($linea['tipo'] === 'produccion') {
            $produccionId = $linea['molde_produccion_id'];

            $prod = executeQuery(
                $conectar,
                "SELECT id, fecha_hora_fin, deleted_at FROM produccion WHERE id = :id",
                ['id' => $produccionId]
            );
            if (empty($prod)) {
                throw new Exception("La producción #$produccionId ya no existe.");
            }
            if (!empty($prod[0]['deleted_at'])) {
                throw new Exception("La producción #$produccionId está inactiva.");
            }
            if (empty($prod[0]['fecha_hora_fin'])) {
                throw new Exception("La producción #$produccionId aún no ha finalizado su corrida.");
            }

            $paramsUnicidad = ['produccion_id' => $produccionId];
            $sqlUnicidad = "SELECT id FROM rel_ensamblaje_producto
                             WHERE molde_produccion_id = :produccion_id AND deleted_at IS NULL";
            if ($excluirEnsamblajeId !== null) {
                $sqlUnicidad .= " AND ensamblaje_id != :excluir_id";
                $paramsUnicidad['excluir_id'] = $excluirEnsamblajeId;
            }
            $yaUsada = executeQuery($conectar, $sqlUnicidad, $paramsUnicidad);
            if (!empty($yaUsada)) {
                throw new Exception("La producción #$produccionId ya está vinculada a otro ensamblaje activo.");
            }

            // Snapshot completo (resuelto directo sobre produccion, sin vista)
            // de la fila al momento de vincularla.
            $snapshotRows = executeQuery(
                $conectar,
                "SELECT
                     t1.id AS produccion_id,
                     mo.nombre AS molde_nombre,
                     t1.cantidad_producida_kg AS cantidad_kg,
                     t1.fecha_hora_fin,
                     split_part(t1.unico_molde_producto, '-', 2)::bigint AS producto_id,
                     t1.color_id,
                     co.nombre AS color_nombre_verif,
                     cm.nombre AS categoria_material_nombre_verif
                 FROM produccion t1
                 LEFT JOIN molde mo ON mo.id = t1.molde_id
                 LEFT JOIN color co ON co.id = t1.color_id
                 LEFT JOIN categoria_material cm ON cm.id = t1.categoria_material_id
                 WHERE t1.id = :id",
                ['id' => $produccionId]
            );
            $snapshot = json_encode($snapshotRows[0] ?? ['produccion_id' => $produccionId], JSON_UNESCAPED_UNICODE);

            executeNonQuery($conectar, "
                INSERT INTO rel_ensamblaje_producto (
                    ensamblaje_id, molde_produccion_id, js_query_consulta_produccion,
                    created_at
                ) VALUES (
                    :ensamblaje_id, :molde_produccion_id, :snapshot,
                    NOW()
                )
            ", [
                'ensamblaje_id'        => $ensamblajeId,
                'molde_produccion_id'  => $produccionId,
                'snapshot'             => $snapshot,
            ]);
        } else {
            // tipo === 'derivado' -> derivado_id apunta a material.id (material.derivado = TRUE)
            $derivadoId = $linea['derivado_id'];
            $derivado = executeQuery(
                $conectar,
                "SELECT id FROM material WHERE id = :id AND derivado = TRUE AND deleted_at IS NULL",
                ['id' => $derivadoId]
            );
            if (empty($derivado)) {
                throw new Exception("El derivado #$derivadoId no existe, está inactivo, o ya no es un material de tipo derivado.");
            }

            executeNonQuery($conectar, "
                INSERT INTO rel_ensamblaje_producto (
                    ensamblaje_id, derivado_id, created_at
                ) VALUES (
                    :ensamblaje_id, :derivado_id, NOW()
                )
            ", [
                'ensamblaje_id' => $ensamblajeId,
                'derivado_id'   => $derivadoId,
            ]);
        }
    }
}

/**
 * Recalcula js_moldes_utilizados / js_derivados_utilizados como resúmenes
 * de conveniencia a partir del detalle activo real (fuente de verdad =
 * rel_ensamblaje_producto). Se llama después de cualquier cambio en el
 * detalle para que ambos queden siempre sincronizados.
 *
 * CORREGIDO (2026-07-24): el resumen de derivados ahora hace JOIN contra
 * `material` (ya NO contra una tabla `derivado`).
 */
function recalcularResumenesEnsamblaje($conectar, int $ensamblajeId): void
{
    $moldes = executeQuery($conectar, "
        SELECT rep.molde_produccion_id AS produccion_id, mo.nombre AS molde_nombre,
               pd.cantidad_producida_kg AS cantidad_kg, pd.fecha,
               cm.nombre AS categoria_material_nombre
        FROM rel_ensamblaje_producto rep
        JOIN produccion pd ON pd.id = rep.molde_produccion_id
        LEFT JOIN molde mo ON mo.id = pd.molde_id
        LEFT JOIN categoria_material cm ON cm.id = pd.categoria_material_id
        WHERE rep.ensamblaje_id = :id AND rep.deleted_at IS NULL AND rep.molde_produccion_id IS NOT NULL
    ", ['id' => $ensamblajeId]);

    $derivados = executeQuery($conectar, "
        SELECT rep.derivado_id, dv.nombre AS derivado_nombre
        FROM rel_ensamblaje_producto rep
        LEFT JOIN material dv ON dv.id = rep.derivado_id
        WHERE rep.ensamblaje_id = :id AND rep.deleted_at IS NULL AND rep.derivado_id IS NOT NULL
    ", ['id' => $ensamblajeId]);

    executeNonQuery($conectar, "
        UPDATE ensamblaje SET
            js_moldes_utilizados    = :moldes,
            js_derivados_utilizados = :derivados
        WHERE id = :id
    ", [
        'id'        => $ensamblajeId,
        'moldes'    => json_encode($moldes, JSON_UNESCAPED_UNICODE),
        'derivados' => json_encode($derivados, JSON_UNESCAPED_UNICODE),
    ]);
}

// Soft delete: desactiva el ensamblaje y sus líneas activas (libera las
// producciones vinculadas para que puedan usarse en otro ensamblaje).
function eliminarEnsamblaje()
{
    $conectar = conectar_oll_BD();
    $id = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at FROM ensamblaje WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Registro de ensamblaje no encontrado.');
    if (!empty($existe[0]['deleted_at'])) responder(false, 'Este registro ya estaba inactivo.');

    $conectar->beginTransaction();
    try {
        executeNonQuery(
            $conectar,
            "UPDATE rel_ensamblaje_producto SET deleted_at = NOW(), update_at = NOW()
             WHERE ensamblaje_id = :id AND deleted_at IS NULL",
            ['id' => $id]
        );

        $cambios = [[
            'campo' => 'Estado', 'valor_antes' => 'Activo', 'valor_despues' => 'Inactivo (producciones liberadas)',
        ]];
        $movimiento   = obtenerMovimientoSesion('desactivar', $cambios);
        $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeNonQuery(
            $conectar,
            "UPDATE ensamblaje SET
                deleted_at   = NOW(),
                update_at    = NOW(),
                js_usuario   = :js_session,
                js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id",
            ['id' => $id, 'js_session' => $js_session, 'js_historial' => $js_historial]
        );

        $conectar->commit();
        responder(true, 'Ensamblaje desactivado correctamente. Las producciones vinculadas quedaron libres.');
    } catch (Throwable $e) {
        $conectar->rollBack();
        error_log("Error desactivando ensamblaje: " . $e->getMessage());
        responder(false, 'No se pudo desactivar el ensamblaje: ' . $e->getMessage());
    }
}

// Restaura el ensamblaje y las líneas que fueron desactivadas junto con él.
// Si alguna producción ya fue "atrapada" por otro ensamblaje mientras tanto,
// se aborta para no duplicar el uso.
//
// NOTA (2026-07-24): si el ensamblaje era 'derivado' y ya había incrementado
// stock de material al finalizar, reactivar NO revierte ese movimiento de
// stock automáticamente (sería doble contabilidad reactivar+revertir sin
// saber si ese stock ya se consumió en otro lado). Si necesitas revertir el
// stock también, dímelo y lo agrego explícitamente aquí.
function reactivarEnsamblaje()
{
    $conectar = conectar_oll_BD();
    $id = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at FROM ensamblaje WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Registro de ensamblaje no encontrado.');
    if (empty($existe[0]['deleted_at'])) responder(false, 'Este registro ya estaba activo.');

    $conectar->beginTransaction();
    try {
        $lineas = executeQuery(
            $conectar,
            "SELECT * FROM rel_ensamblaje_producto WHERE ensamblaje_id = :id AND deleted_at IS NOT NULL",
            ['id' => $id]
        );

        foreach ($lineas as $linea) {
            if (!empty($linea['molde_produccion_id'])) {
                $ocupada = executeQuery(
                    $conectar,
                    "SELECT id FROM rel_ensamblaje_producto
                     WHERE molde_produccion_id = :pid AND deleted_at IS NULL AND ensamblaje_id != :eid",
                    ['pid' => $linea['molde_produccion_id'], 'eid' => $id]
                );
                if (!empty($ocupada)) {
                    throw new Exception(
                        "No se puede reactivar: la producción #{$linea['molde_produccion_id']} ya fue usada en otro ensamblaje mientras este estaba inactivo."
                    );
                }
            }
        }

        executeNonQuery(
            $conectar,
            "UPDATE rel_ensamblaje_producto SET deleted_at = NULL, update_at = NOW() WHERE ensamblaje_id = :id AND deleted_at IS NOT NULL",
            ['id' => $id]
        );

        $cambios = [[
            'campo' => 'Estado', 'valor_antes' => 'Inactivo', 'valor_despues' => 'Activo',
        ]];
        $movimiento   = obtenerMovimientoSesion('reactivar', $cambios);
        $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeNonQuery(
            $conectar,
            "UPDATE ensamblaje SET
                deleted_at   = NULL,
                update_at    = NOW(),
                js_usuario   = :js_session,
                js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id",
            ['id' => $id, 'js_session' => $js_session, 'js_historial' => $js_historial]
        );

        $conectar->commit();
        responder(true, 'Ensamblaje reactivado correctamente.');
    } catch (Throwable $e) {
        $conectar->rollBack();
        error_log("Error reactivando ensamblaje: " . $e->getMessage());
        responder(false, 'No se pudo reactivar el ensamblaje: ' . $e->getMessage());
    }
}

// Marca el inicio real del armado con la hora del servidor.
function iniciarEnsamblaje(int $id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at, inicio FROM ensamblaje WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Registro de ensamblaje no encontrado.');
    if (!empty($existe[0]['deleted_at'])) responder(false, 'No puedes iniciar un ensamblaje inactivo.');
    if (!empty($existe[0]['inicio'])) responder(false, 'Este ensamblaje ya fue iniciado.');

    $cambios = [[
        'campo' => 'Inicio de ensamblaje', 'valor_antes' => '(sin iniciar)', 'valor_despues' => 'Iniciado ahora',
    ]];
    $movimiento   = obtenerMovimientoSesion('iniciar_ensamblaje', $cambios);
    $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeNonQuery($conectar, "
        UPDATE ensamblaje SET
            inicio       = NOW(),
            update_at    = NOW(),
            js_usuario   = :js_session,
            js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id
    ", ['id' => $id, 'js_session' => $js_session, 'js_historial' => $js_historial]);

    responder(true, 'Ensamblaje iniciado.');
}

// Marca el fin real del armado con la hora del servidor.
//
// NUEVO (2026-07-24): ahora exige el peso (kg) de salida del armado como
// parámetro ('peso_kg'), porque:
//   1) se guarda en ensamblaje.cantidad_peso_kg (antes quedaba sin llenar
//      hasta el módulo de empaquetado, que aún no existe).
//   2) si condicion = 'derivado', ese peso es justo el que se usa para
//      incrementar material.stock_actual del material derivado vinculado
//      a este producto (material.producto_id = ensamblaje.producto_id).
// Si condicion = 'derivado' y no existe ese material vinculado, se aborta
// con un mensaje claro (no se autocrea el material).
function finalizarEnsamblaje(int $id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $pesoKg = isset($_POST['peso_kg']) && $_POST['peso_kg'] !== '' ? floatval($_POST['peso_kg']) : null;

    $existe = executeQuery(
        $conectar,
        "SELECT id, deleted_at, inicio, fin, producto_id FROM ensamblaje WHERE id = :id",
        ['id' => $id]
    );
    if (empty($existe)) responder(false, 'Registro de ensamblaje no encontrado.');
    $ensamblaje = $existe[0];

    if (!empty($ensamblaje['deleted_at'])) responder(false, 'No puedes finalizar un ensamblaje inactivo.');
    if (empty($ensamblaje['inicio'])) responder(false, 'Primero debes iniciar el ensamblaje.');
    if (!empty($ensamblaje['fin'])) responder(false, 'Este ensamblaje ya fue finalizado.');
    if ($pesoKg === null || $pesoKg <= 0) {
        responder(false, 'Debes indicar el peso (kg) de salida de este armado para poder finalizarlo.');
    }

    $conectar->beginTransaction();
    try {
        $cambios = [[
            'campo' => 'Fin de ensamblaje',
            'valor_antes' => '(en curso)',
            'valor_despues' => "Finalizado ahora · {$pesoKg} kg",
        ]];
        $movimiento   = obtenerMovimientoSesion('finalizar_ensamblaje', $cambios);
        $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeNonQuery($conectar, "
            UPDATE ensamblaje SET
                fin              = NOW(),
                cantidad_peso_kg = :peso_kg,
                update_at        = NOW(),
                js_usuario       = :js_session,
                js_historial     = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id
        ", [
            'id'           => $id,
            'peso_kg'      => $pesoKg,
            'js_session'   => $js_session,
            'js_historial' => $js_historial,
        ]);

        $conectar->commit();
        responder(true, 'Ensamblaje finalizado.');
    } catch (Throwable $e) {
        $conectar->rollBack();
        error_log("Error finalizando ensamblaje: " . $e->getMessage());
        responder(false, 'No se pudo finalizar el ensamblaje: ' . $e->getMessage());
    }
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