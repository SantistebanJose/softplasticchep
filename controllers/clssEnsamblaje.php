<?php

/**
 * controllers/clssEnsamblaje.php
 * Controlador del módulo de Ensamblaje
 *
 * Tablas reales:
 *   ensamblaje (id, producto_id -> producto, operario_ortorgado -> operario,
 *               js_derivados_utilizados, js_moldes_utilizados,
 *               js_producto_emsamblado [a qué producto complementa ESTE
 *               ensamblaje, una vez marcado con COMPLEMENTAR],
 *               ensamblaje_id_referido [NUEVO 2026-07-25 v2: referencia
 *               "suave" a ensamblaje.id, SIN FK, mismo criterio que
 *               molde_produccion_id. Se llena cuando ESTE ensamblaje (ya
 *               marcado como complemento de algún producto) es TOMADO/
 *               vinculado dentro de otro armado. NULL = libre/disponible],
 *               inicio, fin, cantidad_peso_kg,
 *               js_usuario, js_historial, created_at, update_at, deleted_at)
 *   rel_ensamblaje_producto (id, ensamblaje_id -> ensamblaje,
 *               molde_produccion_id [referencia "suave" a produccion.id, SIN FK],
 *               js_query_consulta_produccion [snapshot jsonb de la fila de
 *               produccion al momento de vincularla],
 *               derivado_id -> material.id [ver nota abajo],
 *               created_at, update_at, deleted_at)
 *
 * REESCRITO (2026-07-25 v2): módulo "Complemento", esquema simplificado.
 * Confirmado por el usuario que la ocupación de un ensamblaje-complemento
 * ya NO se registra como una fila más en rel_ensamblaje_producto. En vez de
 * eso se usa una sola columna en la propia tabla ensamblaje:
 *
 *   ensamblaje.ensamblaje_id_referido (bigint, NULL por defecto)
 *
 * Semántica: en la fila del ensamblaje-complemento (el que YA fue marcado
 * con js_producto_emsamblado), ensamblaje_id_referido = NULL significa
 * "disponible para usarse"; si tiene un valor, significa "ya fue tomado
 * por el ensamblaje con ese id como una de sus líneas".
 *
 *   - Disponibles para complementar a un producto X:
 *       fin IS NOT NULL
 *       AND deleted_at IS NULL
 *       AND (js_producto_emsamblado->>'producto_id')::bigint = X
 *       AND ensamblaje_id_referido IS NULL
 *   - Vincular uno (guardarEnsamblaje, tipo 'complemento'): UPDATE atómico
 *       UPDATE ensamblaje SET ensamblaje_id_referido = :padre
 *       WHERE id = :complementoId AND ensamblaje_id_referido IS NULL
 *     Si rowCount() = 0, alguien más lo tomó primero -> error.
 *   - Liberar uno (se quita del ticket en edición, o se desactiva el
 *     ensamblaje padre): UPDATE ensamblaje SET ensamblaje_id_referido = NULL
 *     WHERE id = :complementoId.
 *   - Resumen de complementos usados por un ensamblaje X (antes vivía en
 *     una columna js_complementos_utilizados que NO existe en la tabla):
 *     se calcula al vuelo con una subquery en listarEnsamblajes() /
 *     obtenerEnsamblaje() sobre `ensamblaje WHERE ensamblaje_id_referido = X`.
 *     No hay columna de cache que mantener sincronizada para esto.
 *
 * Con este esquema, rel_ensamblaje_producto.ensamblaje_complemento_id y
 * js_query_consulta_complemento YA NO SE USAN (no hace falta agregarlas).
 * Cada línea de detalle sigue siendo de UN solo tipo: 'produccion',
 * 'derivado' o 'complemento'. Las dos primeras siguen viviendo en
 * rel_ensamblaje_producto; la de tipo 'complemento' NO genera fila ahí,
 * solo mueve ensamblaje.ensamblaje_id_referido.
 *
 * LIMPIEZA (2026-07-25 v3): se retiró por completo el tipo 'insumo' que
 * había quedado de un intento anterior (permitía vincular directo un
 * ensamblaje terminado SIN pasar por COMPLEMENTAR). Confirmado por el
 * usuario: solo existen tres tipos de línea -> 'produccion', 'derivado' y
 * 'complemento'. Todo ensamblaje-complemento SIEMPRE debe pasar primero
 * por la acción COMPLEMENTAR (que llena js_producto_emsamblado) antes de
 * poder vincularse dentro de otro armado.
 *
 * CORREGIDO (2026-07-24, confirmado por el usuario): los "derivados" del
 * módulo de ensamblaje NO viven en una tabla `derivado` aparte. El
 * verdadero derivado es una fila de la tabla `material` con
 * material.derivado = TRUE (columna que ya existe en el módulo de Materia
 * Prima, ver clssMaterial.php). rel_ensamblaje_producto.derivado_id sigue
 * llamándose igual por compatibilidad con datos existentes, pero ahora
 * apunta a material.id, no a una tabla "derivado".
 *
 * NUEVO (2026-07-24): `material.js_producto` (jsonb, patrón similar a
 * molde.js_producto) lista los productos FINALES que consumen ese material
 * derivado como insumo. buscarDerivados() lo usa para FILTRAR los
 * derivados relevantes al producto que se está ensamblando cuando se pasa
 * producto_id.
 *
 * SUPUESTO SIN CONFIRMAR (heredado, no tocado en esta pasada): no tengo la
 * definición real de `view_ensamblaje_detalle`. Este controlador ya NO
 * depende de ninguna vista: todo se resuelve con SELECT directos sobre
 * ensamblaje / rel_ensamblaje_producto / produccion / material / producto.
 *
 * IMPORTANTE (heredado): `molde` sigue sin tener columna producto_id
 * (molde -> producto es MANY-TO-MANY vía `molde.js_producto`). El
 * producto_id de un avance de producción concreto se resuelve directo con
 * `split_part(produccion.unico_molde_producto, '-', 2)::bigint`.
 *
 * MODELO:
 *   Cada fila de `ensamblaje` es un armado de un `producto` final. Ese
 *   armado consume, línea por línea, uno de tres tipos: un AVANCE DE
 *   PRODUCCIÓN finalizado (molde_produccion_id -> produccion.id, vía
 *   rel_ensamblaje_producto), un DERIVADO preexistente (derivado_id ->
 *   material.id con material.derivado = TRUE, vía rel_ensamblaje_producto),
 *   o un ENSAMBLAJE-COMPLEMENTO ya finalizado y marcado con COMPLEMENTAR
 *   (se refleja directo en ensamblaje.ensamblaje_id_referido, SIN fila en
 *   rel_ensamblaje_producto).
 *
 * REGLA DE UNICIDAD:
 *   Un mismo avance de producción (molde_produccion_id) solo puede estar
 *   vinculado a UN ensamblaje activo a la vez (NOT EXISTS sobre
 *   rel_ensamblaje_producto). Un mismo ensamblaje-complemento solo puede
 *   estar tomado por UN ensamblaje a la vez (ensamblaje_id_referido IS NULL
 *   como condición del UPDATE atómico).
 *
 * REGLA DE PRODUCTO ÚNICO POR ARMADO:
 *   Un ensamblaje se arma para UN solo producto+color a la vez (columna
 *   `producto_id` en `ensamblaje`). El frontend se encarga del filtro
 *   visual; este controlador no lo re-valida porque `producto_id` ya viene
 *   fijo desde el formulario.
 *
 * EDICIÓN (diff-based, NO borrar-y-reinsertar):
 *   Se compara el detalle activo actual (producciones/derivados desde
 *   rel_ensamblaje_producto + complementos desde ensamblaje.ensamblaje_id_referido)
 *   contra el nuevo: las líneas que se mantienen no se tocan, las que ya no
 *   están se liberan (soft-delete para producción/derivado, o
 *   ensamblaje_id_referido = NULL para complemento), las nuevas se insertan/vinculan.
 *
 * PERMISOS ADMIN vs OPERARIO (NUEVO 2026-09-04):
 *   Un ensamblaje con enviado_empaquetado = TRUE ya no puede editarse ni
 *   desactivarse... PERO solo desde la tablet de operario. Confirmado por
 *   el usuario: el admin (panel de escritorio) SÍ debe conservar la
 *   posibilidad de editar o eliminar un ensamblaje aunque ya esté en
 *   Empaquetado, para poder corregir errores. El operario, en cambio,
 *   solo puede registrar (crear) y ver sus propios ensamblajes: una vez
 *   que un armado pasó a Empaquetado, la tablet lo muestra en modo
 *   solo-lectura (ver ensamblaje.php de la tablet, soloLecturaEns) y
 *   ahora el backend también lo bloquea si de todos modos llega la
 *   petición. La detección de "sesión de operario" usa el mismo criterio
 *   que loginoperarios.php / clssAuthOperario.php: presencia de
 *   $_SESSION['operario_id']. El panel de admin nunca setea esa variable
 *   de sesión, así que para él esImplica esOperarioSesion() = false y las
 *   validaciones de enviado_empaquetado simplemente no aplican.
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
require_once __DIR__ . '/auditoria.php';
require_once __DIR__ . '/clssVerificarSession.php';

iniciarSesionSegura();

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

function controladorEnsamblaje(string $accion): void
{
    $accionesLectura = [
        'LISTARENSAMBLAJES',
        'OBTENERENSAMBLAJE',
        'BUSCARPRODUCTOS',
        'BUSCAROPERARIOS',
        'BUSCARDERIVADOS',
        'BUSCARCOMPLEMENTOS',
        'BUSCARPRODUCTOSPARACOMPLEMENTAR',
        'BUSCARPRODUCCIONESDISPONIBLES',
        'BUSCARPRODUCTOSDISPONIBLESENSAMBLAJE',
        'BUSCARPRODUCTOSCONFIGURADOSENSAMBLAJE',
        'BUSCARCOLORESENSAMBLAJE',
        'BUSCARMAQUINASENSAMBLAJE',
        'OBTENERDATOSPRODUCCIONPARAENSAMBLAJE',
    ];

    $accionesOperacion = [
        'GUARDARENSAMBLAJE',
        'INICIARENSAMBLAJE',
        'FINALIZARENSAMBLAJE',
        'PASARAEMPAQUETADO',
        'COMPLEMENTAR',
    ];

    $accionesSoloAdmin = [
        'ELIMINARENSAMBLAJE',
        'REACTIVARENSAMBLAJE',
    ];

    // La tablet autentica operarios con operario_id, no con usuario_id.
    // Conserva ese flujo, pero no les permite desactivar ni reactivar registros.
    if (esOperarioSesion()) {
        if (!in_array($accion, array_merge($accionesLectura, $accionesOperacion), true)) {
            responderAcceso(403, 'No tienes permiso para realizar esta acción.');
        }
    } else {
        $usuario = exigirSesion();

        if (in_array($accion, $accionesLectura, true)) {
            exigirRol($usuario, ['administrador', 'produccion', 'consulta']);
        } elseif (in_array($accion, $accionesOperacion, true)) {
            exigirRol($usuario, ['administrador', 'produccion']);
        } elseif (in_array($accion, $accionesSoloAdmin, true)) {
            exigirRol($usuario, ['administrador']);
        } else {
            responderAcceso(400, 'Acción no reconocida.');
        }
    }

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
        //case 'FUSIONARENSAMBLAJE':
          //  fusionarEnsamblaje();
            //break;
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
        case 'PASARAEMPAQUETADO':
            pasarAEmpaquetado(intval($_POST['id'] ?? 0));
            break;
        case 'COMPLEMENTAR':
            marcarComplemento();
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
        case 'BUSCARCOMPLEMENTOS':
            buscarComplementos();
            break;
        case 'BUSCARPRODUCTOSPARACOMPLEMENTAR':
            buscarProductosParaComplementar();
            break;
        case 'BUSCARPRODUCCIONESDISPONIBLES':
            buscarProduccionesDisponibles();
            break;
        case 'BUSCARPRODUCTOSDISPONIBLESENSAMBLAJE':
            buscarProductosDisponiblesEnsamblaje();
            break;
        case 'BUSCARPRODUCTOSCONFIGURADOSENSAMBLAJE':
            buscarProductosConfiguradosEnsamblaje();
            break;
        case 'BUSCARCOLORESENSAMBLAJE':
            buscarColoresEnsamblaje();
            break;
        case 'BUSCARMAQUINASENSAMBLAJE':
            buscarMaquinasEnsamblaje();
            break;
        case 'OBTENERDATOSPRODUCCIONPARAENSAMBLAJE':
            obtenerDatosProduccionParaEnsamblaje(intval($_POST['produccion_id'] ?? 0));
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// PERMISOS: admin (panel de escritorio) vs operario (tablet)
// =============================================================================

/**
 * true si la petición viene de una sesión de operario (tablet), es decir,
 * pasó por loginoperarios.php / clssAuthOperario.php y tiene
 * $_SESSION['operario_id'] seteado. El panel de admin nunca setea esa
 * variable, así que para admin esto siempre es false.
 *
 * Se usa para las restricciones de "no editar/eliminar si ya está en
 * Empaquetado": esa restricción es solo para la tablet. El admin conserva
 * la posibilidad de corregir errores editando o eliminando un ensamblaje
 * aunque ya haya sido enviado a Empaquetado.
 */
function esOperarioSesion(): bool
{
    return !empty($_SESSION['operario_id']);
}

function verificarPropiedadEnsamblaje($conectar, int $ensamblajeId): array
{
    if (!esOperarioSesion()) return [];
    $filas = executeQuery($conectar,
        "SELECT operario_ortorgado, js_operarios, fin, deleted_at, enviado_empaquetado
         FROM ensamblaje WHERE id = :id",
        ['id' => $ensamblajeId]
    );
    if (empty($filas)) responder(false, 'Registro de ensamblaje no encontrado.');
    $fila = $filas[0];
    $participantes = json_decode($fila['js_operarios'] ?? '[]', true) ?: [];
    $ids = array_map(static fn($op) => (int)($op['operario_id'] ?? 0), $participantes);
    if ((int)($fila['operario_ortorgado'] ?? 0) !== (int)$_SESSION['operario_id']
        && !in_array((int)$_SESSION['operario_id'], $ids, true)) {
        responder(false, 'No puedes modificar un ensamblaje en el que no participaste.');
    }
    return $fila;
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

function buscarProductosConfiguradosEnsamblaje(): void
{
    $conectar = conectar_oll_BD();
    $result = executeQuery($conectar, "
        SELECT DISTINCT p.id AS producto_id, p.codigo, p.descripcion,
            CASE WHEN p.js_configuracion_empaquetado->>'requiere_maquina_ensamblaje' = 'true' THEN 1 ELSE 0 END AS requiere_maquina_ensamblaje
        FROM producto p
        CROSS JOIN LATERAL jsonb_array_elements(COALESCE(p.js_configuracion, '[]'::jsonb)) cfg(item)
        JOIN molde m ON m.id = NULLIF(cfg.item->>'molde_id', '')::bigint
        WHERE p.activo = TRUE AND m.deleted_at IS NULL
          AND COALESCE(cfg.item->>'necesita_ensamblaje', 'sí') <> 'no'
        ORDER BY p.descripcion
    ");
    responder(true, 'OK', ['productos' => $result]);
}

function buscarColoresEnsamblaje(): void
{
    $conectar = conectar_oll_BD();
    $result = executeQuery($conectar, "SELECT id, nombre, rgb FROM color WHERE deleted_at IS NULL ORDER BY nombre");
    responder(true, 'OK', ['colores' => $result]);
}

function buscarMaquinasEnsamblaje(): void
{
    $conectar = conectar_oll_BD();
    $result = executeQuery($conectar, "SELECT id, nombre FROM maquina WHERE deleted_at IS NULL ORDER BY nombre");
    responder(true, 'OK', ['maquinas' => $result]);
}

// Combinaciones producto+color con avances ya enviados a ensamblaje y aún
// libres. El <select> del modal usa como value "producto_id_color_id".
function buscarProductosDisponiblesEnsamblaje()
{
    $conectar = conectar_oll_BD();
    $texto = trim($_POST['texto'] ?? '');
    $incluirEnsamblajeId = intval($_POST['incluir_ensamblaje_id'] ?? 0);

    $condicionLibre = "NOT EXISTS (
        SELECT 1 FROM rel_ensamblaje_producto rep
        WHERE rep.molde_produccion_id = t1.id AND rep.deleted_at IS NULL
    )";
    if ($incluirEnsamblajeId > 0) {
        $condicionLibre = "(" . $condicionLibre . " OR EXISTS (
            SELECT 1 FROM rel_ensamblaje_producto rep2
            WHERE rep2.molde_produccion_id = t1.id AND rep2.deleted_at IS NULL
              AND rep2.ensamblaje_id = :incluir_ensamblaje_id
        ))";
    }

    $where = [
        "t1.enviado_ensamblaje = TRUE",
        "t1.deleted_at IS NULL",
        "t1.fecha_hora_fin IS NOT NULL",
        // NUEVO: solo moldes que SÍ necesitan pasar por Ensamblaje. Si el
        // avance no tiene foto de configuración guardada (viejo) ni molde
        // configurado, se asume 'sí' por compatibilidad (comportamiento
        // previo a que existiera esta bandera).
        "COALESCE(cfg.item->>'necesita_ensamblaje', 'sí') <> 'no'",
        "x.item IS NOT NULL",
        $condicionLibre,
    ];
    $params = [];
    if ($incluirEnsamblajeId > 0) $params['incluir_ensamblaje_id'] = $incluirEnsamblajeId;
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
            CROSS JOIN LATERAL jsonb_array_elements(COALESCE(t2.js_producto, '[]'::jsonb)) assoc
            INNER JOIN producto t4 ON t4.id = (assoc->>'producto_id')::bigint
            LEFT JOIN LATERAL jsonb_array_elements(t4.js_configuracion) AS x(item)
                ON (x.item->>'molde_id')::bigint = t2.id
            LEFT JOIN LATERAL (SELECT CASE
                WHEN split_part(t1.unico_molde_producto, '-', 2) = t4.id::text THEN COALESCE(t1.js_configuracion_moment, x.item)
                ELSE x.item END AS item) cfg ON true
            WHERE " . implode(' AND ', $where) . "
            ORDER BY productoformato
            LIMIT 200";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['productos' => $result]);
}
function buscarOperarios()
{
    $conectar = conectar_oll_BD();
    $texto = trim($_POST['texto'] ?? '');

    $where = [
        "o.activo = true",
        "EXISTS (
            SELECT 1 FROM jsonb_array_elements(COALESCE(o.js_etapas_relacionadas, '[]'::jsonb)) AS et
            WHERE et->>'nombre' ILIKE '%ENSAMBLA%'
        )"
    ];
    $params = [];
    if ($texto !== '') {
        $where[] = "LOWER(o.nombre_completo) LIKE LOWER(:texto)";
        $params['texto'] = "%$texto%";
    }

    $sql = "SELECT o.id, o.nombre_completo, c.nombre AS cargo
            FROM operario o
            LEFT JOIN cargo c ON c.id = o.cargo_id
            WHERE " . implode(' AND ', $where) . " ORDER BY o.nombre_completo";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['operario' => $result]);
}
// Los derivados reales son filas de `material` con derivado = TRUE.
// Si se pasa producto_id, se FILTRA de verdad usando material.js_producto
// vía jsonb_array_elements + INNER JOIN LATERAL. Sin producto_id, se listan
// todos los derivados activos.
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

    $joinProducto = "";
    if ($productoId > 0) {
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

// Ensamblajes finalizados de Primera Categoría, mismo color y aún libres.
// Se ofrecen directamente al elegir producto/color; no requieren marcarlos antes.
function buscarComplementos()
{
    $conectar   = conectar_oll_BD();
    $texto      = trim($_POST['texto'] ?? '');
    $productoId = intval($_POST['producto_id'] ?? 0);
    $colorId    = intval($_POST['color_id'] ?? 0) ?: null;
    $excluirId  = intval($_POST['excluir_id'] ?? 0);
    if ($productoId <= 0 || $colorId === null) {
        responder(true, 'OK', ['complementos' => []]);
    }

    $where = [
        "e.deleted_at IS NULL",
        "e.fin IS NOT NULL",
        "e.ensamblaje_id_referido IS NULL",
        "LOWER(COALESCE(cm.nombre, '')) LIKE '%primera%'",
        "COALESCE(e.color_id, (
            SELECT pd.color_id FROM rel_ensamblaje_producto rep
            JOIN produccion pd ON pd.id = rep.molde_produccion_id
            WHERE rep.ensamblaje_id = e.id AND rep.deleted_at IS NULL LIMIT 1
        )) = :color_id",
        "(e.js_producto_emsamblado IS NULL OR (
            (e.js_producto_emsamblado->>'producto_id')::bigint = :producto_id
            AND (e.js_producto_emsamblado->>'color_id')::bigint = :marker_color_id
        ))",
    ];
    $params = ['producto_id' => $productoId, 'color_id' => $colorId, 'marker_color_id' => $colorId];
    if ($excluirId > 0) {
        $where[] = 'e.id <> :excluir_id';
        $params['excluir_id'] = $excluirId;
    }
    if ($texto !== '') {
        $where[] = "(LOWER(p.codigo) LIKE LOWER(:texto) OR LOWER(p.descripcion) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }

    $sql = "SELECT
            e.id AS ensamblaje_id,
            e.producto_id,
            p.codigo AS producto_codigo,
            p.descripcion AS producto_descripcion,
            COALESCE(e.color_id, (
                SELECT pd.color_id FROM rel_ensamblaje_producto rep
                JOIN produccion pd ON pd.id = rep.molde_produccion_id
                WHERE rep.ensamblaje_id = e.id AND rep.deleted_at IS NULL LIMIT 1
            )) AS complemento_color_id,
            e.cantidad_peso_kg,
            e.fin,
            co.nombre AS complemento_color_nombre,
            cm.nombre AS categoria_material_nombre,
            COALESCE(
                (SELECT string_agg(DISTINCT (m->>'molde_nombre'), ', ' ORDER BY (m->>'molde_nombre'))
                 FROM jsonb_array_elements(COALESCE(e.js_moldes_utilizados,'[]'::jsonb)) m),
                (SELECT string_agg(DISTINCT (d->>'derivado_nombre'), ', ' ORDER BY (d->>'derivado_nombre'))
                 FROM jsonb_array_elements(COALESCE(e.js_derivados_utilizados,'[]'::jsonb)) d)
            ) AS moldes_nombres,
            COALESCE(
                (SELECT (m->>'unidad_produccion_codigo')
                 FROM jsonb_array_elements(COALESCE(e.js_moldes_utilizados,'[]'::jsonb)) m LIMIT 1),
                'kg'
            ) AS unidad_salida_codigo
        FROM ensamblaje e
        LEFT JOIN producto p ON p.id = e.producto_id
        LEFT JOIN color co ON co.id = COALESCE(e.color_id, (
            SELECT pd.color_id FROM rel_ensamblaje_producto rep
            JOIN produccion pd ON pd.id = rep.molde_produccion_id
            WHERE rep.ensamblaje_id = e.id AND rep.deleted_at IS NULL LIMIT 1
        ))
        LEFT JOIN categoria_material cm ON cm.id = e.categoria_material_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY e.fin DESC
        LIMIT 100";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['complementos' => $result]);
}
// Productos válidos como OBJETIVO al marcar un ensamblaje con COMPLEMENTAR
// (select del modal "Marcar como complemento"). NO es el catálogo completo
// de producto: son solo los productos que AÚN SIGUEN "vivos" dentro del
// módulo de ensamblaje -> tienen al menos un ensamblaje propio finalizado
// y todavía libre (fin IS NOT NULL AND ensamblaje_id_referido IS NULL),
// es decir, que aún no salió del todo del módulo. Se excluye tanto el
// propio ensamblaje que se está marcando (excluir_id) COMO su producto
// (producto_propio_id) -- esto último es lo que faltaba: sin esto, otro
// ensamblaje distinto pero del MISMO producto colaba como objetivo válido,
// permitiendo que un producto se complementara "a sí mismo" por otra vía.
function buscarProductosParaComplementar()
{
    $conectar  = conectar_oll_BD();
    $excluirId = intval($_POST['excluir_id'] ?? 0);
    $texto     = trim($_POST['texto'] ?? '');

    $productoPropioId = 0;
    $categoriaMaterialIdPropio = null;
    $categoriaMaterialNombrePropio = null;
    if ($excluirId > 0) {
        $propio = executeQuery(
            $conectar,
            "SELECT e.producto_id, e.categoria_material_id, cm.nombre AS categoria_material_nombre
             FROM ensamblaje e
             LEFT JOIN categoria_material cm ON cm.id = e.categoria_material_id
             WHERE e.id = :id",
            ['id' => $excluirId]
        );
        $productoPropioId = !empty($propio) ? intval($propio[0]['producto_id']) : 0;
        $categoriaMaterialIdPropio = !empty($propio) ? $propio[0]['categoria_material_id'] : null;
        $categoriaMaterialNombrePropio = !empty($propio) ? $propio[0]['categoria_material_nombre'] : null;
    }

    if ($categoriaMaterialIdPropio === null || strpos(strtolower(trim($categoriaMaterialNombrePropio ?? '')), 'primera') === false) {
        responder(true, 'OK', ['productos' => []]);
    }

    $where = [
        "t1.deleted_at IS NULL",
        "t1.fin IS NOT NULL",
        "t1.ensamblaje_id_referido IS NULL",
        "t1.categoria_material_id = :categoria_material_id_propio",
        "t1.id != :excluir_id",
    ];
    $params = ['categoria_material_id_propio' => $categoriaMaterialIdPropio, 'excluir_id' => $excluirId];
    // YA NO se excluye t2.id != producto_propio_id: el mismo producto es un
    // destino válido (auto-complemento).
    if ($texto !== '') {
        $where[] = "(LOWER(t2.codigo) LIKE LOWER(:texto) OR LOWER(t2.descripcion) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }

    $sql = "SELECT DISTINCT ON (t2.id, col.color_id)
                t1.id AS ensamblaje_id,
                t2.id AS producto_id,
                t2.codigo,
                t2.descripcion AS producto,
                col.color_id,
                col.color_nombre
            FROM ensamblaje t1
            JOIN producto t2 ON t1.producto_id = t2.id
            LEFT JOIN LATERAL (
                SELECT COALESCE(t1.color_id, pd.color_id) AS color_id, co.nombre AS color_nombre
                FROM rel_ensamblaje_producto rep
                JOIN produccion pd ON pd.id = rep.molde_produccion_id
                LEFT JOIN color co ON co.id = COALESCE(t1.color_id, pd.color_id)
                WHERE rep.ensamblaje_id = t1.id AND rep.deleted_at IS NULL
                LIMIT 1
            ) col ON true
            WHERE " . implode(' AND ', $where) . "
            ORDER BY t2.id, col.color_id, t1.fin DESC";

    $result = executeQuery($conectar, $sql, $params);

    // Siempre se ofrece primero la opción "auto-complemento": el mismo
    // producto+color que ya trae ESTE ensamblaje desde su producción.
    if ($excluirId > 0 && $productoPropioId > 0) {
        $colorPropio = executeQuery($conectar, "
            SELECT COALESCE(e.color_id, pd.color_id) AS color_id, co.nombre AS color_nombre
            FROM ensamblaje e
            LEFT JOIN rel_ensamblaje_producto rep ON rep.ensamblaje_id = e.id AND rep.deleted_at IS NULL
            JOIN produccion pd ON pd.id = rep.molde_produccion_id
            LEFT JOIN color co ON co.id = COALESCE(e.color_id, pd.color_id)
            WHERE e.id = :id
            LIMIT 1
        ", ['id' => $excluirId]);
        $propioProducto = executeQuery($conectar, "SELECT codigo, descripcion FROM producto WHERE id = :id", ['id' => $productoPropioId]);
        if (!empty($propioProducto) && !empty($colorPropio)) {
            array_unshift($result, [
                'ensamblaje_id'      => null,
                'producto_id'        => $productoPropioId,
                'codigo'             => $propioProducto[0]['codigo'],
                'producto'           => $propioProducto[0]['descripcion'],
                'color_id'           => $colorPropio[0]['color_id'],
                'color_nombre'       => $colorPropio[0]['color_nombre'],
                'es_mismo_producto'  => true,
            ]);
        }
    }

    responder(true, 'OK', ['productos' => $result]);
}
// Avances de producción finalizados y aún no consumidos por ningún
// ensamblaje activo. Resuelto directo sobre `produccion` (sin vista).
function buscarProduccionesDisponibles()
{
    $conectar = conectar_oll_BD();
    $productoId    = intval($_POST['producto_id'] ?? 0);
    $colorId       = intval($_POST['color_id'] ?? 0);
    $categoriaMaterialId = intval($_POST['categoria_material_id'] ?? 0);
    $produccionId  = intval($_POST['produccion_id'] ?? 0);
    $texto         = trim($_POST['texto'] ?? '');

    $where  = [
        "t1.deleted_at IS NULL",
        "t1.fecha_hora_fin IS NOT NULL",
        "t1.enviado_ensamblaje = TRUE",
        "x.item IS NOT NULL",
        "COALESCE(cfg.item->>'necesita_ensamblaje', 'sí') <> 'no'",
        "NOT EXISTS (
            SELECT 1 FROM rel_ensamblaje_producto rep
            WHERE rep.molde_produccion_id = t1.id AND rep.deleted_at IS NULL
        )",
    ];
    $params = [];
    $joinProducto = "INNER JOIN producto t4 ON t4.id = split_part(t1.unico_molde_producto, '-', 2)::bigint";

    if ($produccionId > 0) {
        $where[] = "t1.id = :produccion_id";
        $params['produccion_id'] = $produccionId;
    } else {
        if ($productoId > 0) {
            $joinProducto = "INNER JOIN producto t4 ON t4.id = :target_producto";
            $where[] = "EXISTS (
                SELECT 1 FROM jsonb_array_elements(COALESCE(t2.js_producto, '[]'::jsonb)) assoc
                WHERE (assoc->>'producto_id')::bigint = t4.id
            )";
            $params['target_producto'] = $productoId;
        }
    }
    if ($colorId > 0) {
        // Componentes compartidos conservan su color de producción;
        // solo las cadenitas blancas son compatibles con cualquier color final.
        $where[] = "(t1.color_id = :color_id OR (
            UPPER(COALESCE(t2.nombre, '')) LIKE '%CADENITA%'
            AND UPPER(COALESCE(t3.nombre, '')) = 'BLANCO'
            AND (SELECT COUNT(DISTINCT (assoc_color->>'producto_id')::bigint)
                 FROM jsonb_array_elements(COALESCE(t2.js_producto, '[]'::jsonb)) assoc_color) > 1
        ))";
        $params['color_id'] = $colorId;
    }
    if ($categoriaMaterialId > 0) {
        $where[] = "t1.categoria_material_id = :categoria_material_id";
        $params['categoria_material_id'] = $categoriaMaterialId;
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
            t1.categoria_material_id,
            cm.nombre AS categoria_material_nombre_verif,
            COALESCE(upv.nombre_corto, 'KG') AS unidad_produccion_codigo
        FROM produccion t1
        LEFT JOIN molde t2 ON t2.id = t1.molde_id
        LEFT JOIN color t3 ON t3.id = t1.color_id
        $joinProducto
        LEFT JOIN categoria_material cm ON cm.id = t1.categoria_material_id
        LEFT JOIN LATERAL jsonb_array_elements(t4.js_configuracion) AS x(item)
            ON (x.item->>'molde_id')::bigint = t2.id
        LEFT JOIN LATERAL (SELECT CASE
            WHEN split_part(t1.unico_molde_producto, '-', 2) = t4.id::text THEN COALESCE(t1.js_configuracion_moment, x.item)
            ELSE x.item END AS item) cfg ON true
        LEFT JOIN unidad_medida upv ON upv.id = NULLIF(cfg.item->>'salida_produccion_unidad_medida_id','')::bigint
        WHERE " . implode(' AND ', $where) . "
        ORDER BY t1.fecha_hora_fin DESC";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['producciones' => $result]);
}
// Usado por el botón "Pasar a ensamblaje" desde la card de producción.
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
             cm.nombre AS categoria_material_nombre_verif,
             COALESCE(cfg.item->>'necesita_ensamblaje', 'sí') AS necesita_ensamblaje,
             COALESCE(upv.nombre_corto, 'KG') AS unidad_produccion_codigo
         FROM produccion t1
         LEFT JOIN molde t2 ON t2.id = t1.molde_id
         LEFT JOIN color t3 ON t3.id = t1.color_id
         CROSS JOIN LATERAL jsonb_array_elements(COALESCE(t2.js_producto, '[]'::jsonb)) assoc
         INNER JOIN producto t4 ON t4.id = (assoc->>'producto_id')::bigint
         LEFT JOIN categoria_material cm ON cm.id = t1.categoria_material_id
         LEFT JOIN LATERAL jsonb_array_elements(t4.js_configuracion) AS x(item)
             ON (x.item->>'molde_id')::bigint = t2.id
         LEFT JOIN LATERAL (SELECT CASE
             WHEN split_part(t1.unico_molde_producto, '-', 2) = t4.id::text THEN COALESCE(t1.js_configuracion_moment, x.item)
             ELSE x.item END AS item) cfg ON true
         LEFT JOIN unidad_medida upv ON upv.id = NULLIF(cfg.item->>'salida_produccion_unidad_medida_id','')::bigint
         WHERE t1.id = :id
           AND t1.deleted_at IS NULL
           AND t1.fecha_hora_fin IS NOT NULL
           AND t1.enviado_ensamblaje = TRUE
           AND x.item IS NOT NULL
           AND NOT EXISTS (
               SELECT 1 FROM rel_ensamblaje_producto rep
               WHERE rep.molde_produccion_id = t1.id AND rep.deleted_at IS NULL
           )
         ORDER BY t4.id
         LIMIT 1",
        ['id' => $produccionId]
    );

    if (empty($data)) {
        responder(false, 'Esta producción no está disponible para ensamblaje (no existe, no está finalizada, no tiene producto vinculado, o ya fue usada en otro ensamblaje).');
    }
    if (strtolower(trim($data[0]['necesita_ensamblaje'])) === 'no') {
        responder(false, 'Este molde/producto no requiere ensamblaje: pasa directo a empaquetado.');
    }

    responder(true, 'OK', ['produccion' => $data[0]]);
}

// =============================================================================
// ENSAMBLAJE
// =============================================================================

function subquerySelectComplementosUtilizados(string $aliasEnsamblaje = 'e'): string
{
    return "(
        SELECT jsonb_agg(jsonb_build_object(
                   'ensamblaje_complemento_id', ec.id,
                   'producto_codigo', pc.codigo,
                   'producto_descripcion', pc.descripcion,
                   'cantidad_peso_kg', ec.cantidad_peso_kg,
                   'moldes_nombres', COALESCE(
                       (SELECT string_agg(DISTINCT (m->>'molde_nombre'), ', ' ORDER BY (m->>'molde_nombre'))
                        FROM jsonb_array_elements(COALESCE(ec.js_moldes_utilizados,'[]'::jsonb)) m),
                       (SELECT string_agg(DISTINCT (d->>'derivado_nombre'), ', ' ORDER BY (d->>'derivado_nombre'))
                        FROM jsonb_array_elements(COALESCE(ec.js_derivados_utilizados,'[]'::jsonb)) d)
                   ),
                   'unidad_salida_codigo', COALESCE(
                       (SELECT (m->>'unidad_produccion_codigo')
                        FROM jsonb_array_elements(COALESCE(ec.js_moldes_utilizados,'[]'::jsonb)) m LIMIT 1),
                       'kg'
                   )
               ))
        FROM ensamblaje ec
        LEFT JOIN producto pc ON pc.id = ec.producto_id
        WHERE ec.ensamblaje_id_referido = $aliasEnsamblaje.id
          AND ec.deleted_at IS NULL
    ) AS js_complementos_utilizados";
}
function listarEnsamblajes()
{
    $conectar = conectar_oll_BD();

    $texto        = trim($_POST['texto'] ?? '');
    $producto_id  = trim($_POST['producto_id'] ?? '');
    $operario_id  = esOperarioSesion() ? (string)(int)$_SESSION['operario_id'] : trim($_POST['operario_id'] ?? '');
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
        $where[] = "(e.operario_ortorgado = :operario_id_legacy OR EXISTS (
            SELECT 1 FROM jsonb_array_elements(COALESCE(e.js_operarios, '[]'::jsonb)) AS op
            WHERE (op->>'operario_id')::bigint = :operario_id
        ))";
        $params['operario_id'] = $operario_id;
        $params['operario_id_legacy'] = $operario_id;
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
        e.maquina_id,
        maq.nombre AS maquina_nombre,
        p.codigo AS producto_codigo,
        p.descripcion AS producto_descripcion,
        COALESCE(e.color_id, (
            SELECT pd.color_id FROM rel_ensamblaje_producto rep
            JOIN produccion pd ON pd.id = rep.molde_produccion_id
            WHERE rep.ensamblaje_id = e.id AND rep.deleted_at IS NULL LIMIT 1
        )) AS color_id_actual,
        COALESCE(co.nombre, (
            SELECT co2.nombre FROM rel_ensamblaje_producto rep
            JOIN produccion pd ON pd.id = rep.molde_produccion_id
            LEFT JOIN color co2 ON co2.id = pd.color_id
            WHERE rep.ensamblaje_id = e.id AND rep.deleted_at IS NULL LIMIT 1
        )) AS color_nombre_actual,
        e.operario_ortorgado AS operario_id,
        e.js_operarios,
        o.nombre_completo AS operario_nombre,
        su.nombre AS sucursal_nombre,
        e.inicio,
        e.fin,
        e.cantidad_peso_kg,
        e.unidad_salida_id,
        us.nombre_corto AS unidad_salida_codigo,
        us.nombre AS unidad_salida_nombre,
        NULLIF(p.js_configuracion_empaquetado->>'salida_ensamblaje_unidad_medida_id','')::bigint AS producto_unidad_ensamblaje_id,
        upe.nombre_corto AS producto_unidad_ensamblaje_codigo,
        upe.nombre AS producto_unidad_ensamblaje_nombre,
        e.deleted_at,
        e.js_moldes_utilizados,
        e.js_derivados_utilizados,
        e.categoria_material_id,
        cmm.nombre AS categoria_material_nombre,
        " . subquerySelectComplementosUtilizados('e') . ",
        e.js_producto_emsamblado,
        e.ensamblaje_id_referido,
        e.enviado_empaquetado,
        e.fecha_envio_empaquetado
    FROM ensamblaje e
    LEFT JOIN producto p ON p.id = e.producto_id
    LEFT JOIN maquina maq ON maq.id = e.maquina_id
    LEFT JOIN color co ON co.id = e.color_id
    LEFT JOIN operario o ON o.id = e.operario_ortorgado
    LEFT JOIN sucursal su ON su.id = e.sucursal
    LEFT JOIN categoria_material cmm ON cmm.id = e.categoria_material_id
    LEFT JOIN unidad_medida us ON us.id = e.unidad_salida_id
    LEFT JOIN unidad_medida upe ON upe.id = NULLIF(p.js_configuracion_empaquetado->>'salida_ensamblaje_unidad_medida_id','')::bigint
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
            e.color_id,
            e.maquina_id,
            p.codigo AS producto_codigo,
            p.descripcion AS producto_descripcion,
            e.operario_ortorgado AS operario_id,
            e.js_operarios,
            o.nombre_completo AS operario_nombre,
            e.sucursal,
            e.inicio,
            e.fin,
            e.cantidad_peso_kg,
            e.deleted_at,
            e.js_moldes_utilizados,
            e.js_derivados_utilizados,
            e.enviado_empaquetado, 
            e.fecha_envio_empaquetado,
            " . subquerySelectComplementosUtilizados('e') . ",
            e.js_producto_emsamblado,
            e.ensamblaje_id_referido,
            COALESCE(e.color_id, (
                SELECT pd.color_id
                FROM rel_ensamblaje_producto rep
                JOIN produccion pd ON pd.id = rep.molde_produccion_id
                WHERE rep.ensamblaje_id = e.id AND rep.deleted_at IS NULL
                LIMIT 1
            )) AS color_id_actual
        FROM ensamblaje e
        LEFT JOIN producto p ON p.id = e.producto_id
        LEFT JOIN operario o ON o.id = e.operario_ortorgado
        LEFT JOIN categoria_material cmm ON cmm.id = e.categoria_material_id
        WHERE e.id = :id",
        ['id' => $id]
    );
    if (empty($ensamblaje)) responder(false, 'Registro de ensamblaje no encontrado.');
    verificarPropiedadEnsamblaje($conectar, (int)$id);

    responder(true, 'OK', ['ensamblaje' => $ensamblaje[0]]);
}
// Valida y resuelve el equipo de operarios que participó en el armado.
// Mismo patrón que resolverSucursales()/resolverEtapas() en
// clssOperario.php: recibe ids, valida que existan y estén activos, y
// devuelve el arreglo denormalizado listo para guardar en js_operarios.
function resolverOperariosEnsamblaje($conectar, array $idsOperario): array
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

    $sql = "SELECT o.id, o.nombre_completo, c.nombre AS cargo
        FROM operario o
        LEFT JOIN cargo c ON c.id = o.cargo_id
        WHERE o.id IN (" . implode(',', $placeholders) . ") AND o.activo = true";
    $result = executeQuery($conectar, $sql, $params);

    if (count($result) !== count($idsOperario)) {
        throw new Exception('Uno o más operarios seleccionados no existen o están inactivos.');
    }

    return array_map(fn($o) => [
        'operario_id'     => (int)$o['id'],
        'nombre_completo' => $o['nombre_completo'],
        'cargo'           => $o['cargo'],
    ], $result);
}
function guardarEnsamblaje()
{
    $conectar = conectar_oll_BD();

    $id                  = intval($_POST['id'] ?? 0);
    $sucursal_id = intval($_POST['sucursal_id'] ?? 0);
    $producto_id         = intval($_POST['producto_id'] ?? 0);
    $color_id            = intval($_POST['color_id'] ?? 0);
    $operariosInput = json_decode($_POST['operarios'] ?? '[]', true);
    if (!is_array($operariosInput)) $operariosInput = [];
    $detalleJson         = trim($_POST['detalle'] ?? '[]');

    // ── Validaciones básicas ─────────────────────────────────────────────────
    if ($producto_id <= 0) responder(false, 'Debes seleccionar el producto a ensamblar.');
    if ($color_id <= 0) responder(false, 'Debes seleccionar el color final del producto.');
    $color = executeQuery($conectar, "SELECT id FROM color WHERE id = :id AND deleted_at IS NULL", ['id' => $color_id]);
    if (empty($color)) responder(false, 'El color seleccionado no existe o está inactivo.');

    $producto = executeQuery($conectar, "SELECT id, js_configuracion_empaquetado FROM producto WHERE id = :id AND activo = true", ['id' => $producto_id]);
    if (empty($producto)) responder(false, 'El producto seleccionado no existe o está inactivo.');

    $productoConfig = json_decode($producto[0]['js_configuracion_empaquetado'] ?? '{}', true) ?: [];
    $requiereMaquina = !empty($productoConfig['requiere_maquina_ensamblaje']);
    $maquinaId = intval($_POST['maquina_id'] ?? 0) ?: null;
    if ($requiereMaquina && !$maquinaId) responder(false, 'Este producto requiere seleccionar la máquina utilizada en Ensamblaje.');
    if ($maquinaId) {
        $maquina = executeQuery($conectar, "SELECT id FROM maquina WHERE id = :id AND deleted_at IS NULL", ['id' => $maquinaId]);
        if (empty($maquina)) responder(false, 'La máquina seleccionada no existe o está inactiva.');
    }

    if (empty($operariosInput)) {
        responder(false, 'Debes indicar al menos un operario que participó en este armado.');
    }

    if ($sucursal_id <= 0) {
        responder(false, 'Debes seleccionar la sucursal donde se realizó este armado.');
    }
    $suc = executeQuery($conectar, "SELECT id FROM sucursal WHERE id = :id AND delete_at IS NULL", ['id' => $sucursal_id]);
    if (empty($suc)) responder(false, 'La sucursal seleccionada no existe o está inactiva.');

    $detalleEntrada = json_decode($detalleJson, true);
    if (!is_array($detalleEntrada)) $detalleEntrada = [];

    // Normaliza y valida cada línea (tipo produccion / derivado / complemento,
    // mutuamente excluyentes).
    $detalle = [];
    foreach ($detalleEntrada as $linea) {
        $tipo = trim($linea['tipo'] ?? '');
        if ($tipo === 'produccion') {
            $prodId = intval($linea['molde_produccion_id'] ?? 0);
            if ($prodId <= 0) continue;
            $detalle[] = [
                'tipo' => 'produccion', 'molde_produccion_id' => $prodId,
                'derivado_id' => null, 'ensamblaje_complemento_id' => null,
            ];
        } elseif ($tipo === 'derivado') {
            $derId = intval($linea['derivado_id'] ?? 0);
            if ($derId <= 0) continue;
            $detalle[] = [
                'tipo' => 'derivado', 'molde_produccion_id' => null,
                'derivado_id' => $derId, 'ensamblaje_complemento_id' => null,
            ];
        } elseif ($tipo === 'complemento') {
            $compId = intval($linea['ensamblaje_complemento_id'] ?? 0);
            if ($compId <= 0) continue;
            $detalle[] = [
                'tipo' => 'complemento', 'molde_produccion_id' => null,
                'derivado_id' => null, 'ensamblaje_complemento_id' => $compId,
            ];
        }
        // tipos desconocidos se ignoran silenciosamente
    }

    if (empty($detalle)) {
        responder(false, 'Debes vincular al menos una producción finalizada, un derivado o un complemento a este ensamblaje.');
    }

    try {
        $operariosResueltos = resolverOperariosEnsamblaje($conectar, $operariosInput);
    } catch (Throwable $e) {
        responder(false, $e->getMessage());
    }
    if (esOperarioSesion() && !in_array((int)$_SESSION['operario_id'], array_column($operariosResueltos, 'operario_id'), true)) {
        responder(false, 'El ensamblaje debe quedar asociado al operario que inició sesión.');
    }
    $jsOperariosJson  = json_encode($operariosResueltos, JSON_UNESCAPED_UNICODE);
    $operarioRegistro = $operariosResueltos[0]['operario_id'] ?? null;

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
            $proveniente = "produccion";

            $nuevoEnsamblaje = executeQuery($conectar, " 
                INSERT INTO ensamblaje (
                    producto_id, color_id, maquina_id, operario_ortorgado, sucursal,
                    js_derivados_utilizados, js_moldes_utilizados, js_operarios,
                    created_at, js_usuario, js_historial, proveniente
                ) VALUES (
                    :producto_id, :color_id, :maquina_id, :operario_ortorgado, :sucursal_id,
                    '[]'::jsonb, '[]'::jsonb, :js_operarios,
                    NOW(), :js_usuario, :js_historial, :proveniente
                ) RETURNING id
            ", [
                'producto_id'        => $producto_id,
                'color_id'           => $color_id,
                'maquina_id'         => $maquinaId,
                'operario_ortorgado' => $operarioRegistro,
                'sucursal_id'        => $sucursal_id,
                'js_operarios'       => $jsOperariosJson,
                'js_usuario'         => $js_session,
                'js_historial'       => $js_historial,
                'proveniente'        => $proveniente,
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
            if (!empty($actual[0]['js_producto_emsamblado'])) {
                throw new Exception('Este ensamblaje ya fue marcado como complemento y no puede editarse.');
            }

            if (esOperarioSesion()) {
                $actualOperario = verificarPropiedadEnsamblaje($conectar, $id);
                if (!empty($actualOperario['fin']) || !empty($actualOperario['enviado_empaquetado'])) {
                    throw new Exception('Este ensamblaje ya finalizó. Solo un administrador puede editarlo.');
                }
            }

            // NUEVO: la restricción de "ya enviado a Empaquetado, no se
            // puede editar" solo aplica a la tablet de operario. El admin
            // (panel de escritorio) SÍ puede seguir editando para corregir
            // errores; esa edición queda registrada en js_historial con una
            // nota especial para que quede claro que fue una corrección
            // posterior al envío a empaquetado.
            $eraEnviadoEmpaquetado = !empty($actual[0]['enviado_empaquetado']);
            if ($eraEnviadoEmpaquetado && esOperarioSesion()) {
                throw new Exception('Este ensamblaje ya fue enviado a Empaquetado y no puede editarse desde la tablet. Solo puedes visualizarlo.');
            }

            // Producción / derivado: siguen viviendo en rel_ensamblaje_producto.
            $lineasActuales = executeQuery(
                $conectar,
                "SELECT * FROM rel_ensamblaje_producto WHERE ensamblaje_id = :id AND deleted_at IS NULL",
                ['id' => $id]
            );

            // Complemento: vive como ensamblaje.ensamblaje_id_referido = $id,
            // tanto si quedó reservado manualmente como si se vinculó desde
            // la lista automática de armados de Primera Categoría.
            $complementosActuales = executeQuery(
                $conectar,
                "SELECT id AS ensamblaje_complemento_id FROM ensamblaje
                 WHERE ensamblaje_id_referido = :id AND deleted_at IS NULL",
                ['id' => $id]
            );

            // Claves de comparación: "p:123" producción, "d:45" derivado,
            // "c:78" complemento.
            $clave = function ($tipo, $prodId, $derId, $compId) {
                if ($tipo === 'produccion')  return "p:$prodId";
                if ($tipo === 'derivado')    return "d:$derId";
                return "c:$compId";
            };

            $actualesPorClave = [];
            foreach ($lineasActuales as $l) {
                if ($l['molde_produccion_id'] !== null) {
                    $tipo = 'produccion';
                } else {
                    $tipo = 'derivado';
                }
                $k = $clave($tipo, $l['molde_produccion_id'], $l['derivado_id'], null);
                $actualesPorClave[$k] = ['tipo' => $tipo, 'rel_id' => $l['id']];
            }
            foreach ($complementosActuales as $c) {
                $k = $clave('complemento', null, null, $c['ensamblaje_complemento_id']);
                $actualesPorClave[$k] = ['tipo' => 'complemento', 'ensamblaje_complemento_id' => $c['ensamblaje_complemento_id']];
            }

            $nuevasPorClave = [];
            foreach ($detalle as $d) {
                $k = $clave($d['tipo'], $d['molde_produccion_id'], $d['derivado_id'], $d['ensamblaje_complemento_id']);
                $nuevasPorClave[$k] = $d;
            }

            // Líneas que ya no están -> liberar (según su tipo).
            $clavesAEliminar = array_diff(array_keys($actualesPorClave), array_keys($nuevasPorClave));
            foreach ($clavesAEliminar as $k) {
                $linea = $actualesPorClave[$k];
                if ($linea['tipo'] === 'complemento') {
                    executeNonQuery(
                        $conectar,
                        "UPDATE ensamblaje SET ensamblaje_id_referido = NULL, update_at = NOW()
                         WHERE id = :id AND ensamblaje_id_referido = :padre",
                        ['id' => $linea['ensamblaje_complemento_id'], 'padre' => $id]
                    );
                } else {
                    executeNonQuery(
                        $conectar,
                        "UPDATE rel_ensamblaje_producto SET deleted_at = NOW(), update_at = NOW() WHERE id = :id",
                        ['id' => $linea['rel_id']]
                    );
                }
            }

            // Líneas nuevas -> insertar/vincular (con su validación correspondiente).
            $clavesAInsertar = array_diff(array_keys($nuevasPorClave), array_keys($actualesPorClave));
            $detalleNuevo = array_values(array_filter(
                $detalle,
                fn($d) => in_array($clave($d['tipo'], $d['molde_produccion_id'], $d['derivado_id'], $d['ensamblaje_complemento_id']), $clavesAInsertar)
            ));
            if (!empty($detalleNuevo)) {
                insertarLineasEnsamblaje($conectar, $id, $detalleNuevo);
            }
            // Las líneas que se mantienen (intersección) no se tocan.

            $cambios = [[
                'campo' => 'Ensamblaje',
                'valor_antes' => count($lineasActuales) + count($complementosActuales) . ' ítem(s)',
                'valor_despues' => count($detalle) . ' ítem(s)'
                    . ($eraEnviadoEmpaquetado ? ' (corrección de admin: el ensamblaje ya estaba enviado a Empaquetado)' : ''),
            ]];
            $movimiento   = obtenerMovimientoSesion('editar', $cambios);
            $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
            $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

            executeNonQuery($conectar, "
                UPDATE ensamblaje SET
                    producto_id         = :producto_id,
                    color_id            = :color_id,
                    maquina_id          = :maquina_id,
                    operario_ortorgado  = :operario_ortorgado,
                    sucursal             = :sucursal_id,
                    js_operarios         = :js_operarios,
                    update_at           = NOW(),
                    js_usuario          = :js_usuario,
                    js_historial        = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
                WHERE id = :id
            ", [
                'producto_id'        => $producto_id,
                'color_id'           => $color_id,
                'maquina_id'         => $maquinaId,
                'operario_ortorgado' => $operarioRegistro,
                'sucursal_id'        => $sucursal_id,
                'js_operarios'       => $jsOperariosJson,
                'js_usuario'         => $js_session,
                'js_historial'       => $js_historial,
                'id'                 => $id,
            ]);

            recalcularResumenesEnsamblaje($conectar, $id);

            $conectar->commit();
            $mensajeEdicion = $eraEnviadoEmpaquetado
                ? 'Ensamblaje actualizado correctamente. Nota: este armado ya estaba enviado a Empaquetado; revisa si el stock de empaquetado necesita ajustarse también.'
                : 'Ensamblaje actualizado correctamente.';
            responder(true, $mensajeEdicion, [
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
 * Valida e inserta/vincula cada línea nueva:
 *   - 'produccion' y 'derivado': insertan una fila en rel_ensamblaje_producto
 *     (igual que antes).
 *   - 'complemento': NO inserta fila en rel_ensamblaje_producto. En vez de
 *     eso, hace un UPDATE atómico sobre ensamblaje.ensamblaje_id_referido
 *     del ensamblaje-complemento, condicionado a que siga libre
 *     (ensamblaje_id_referido IS NULL). Si otra transacción lo tomó
 *     primero, rowCount() = 0 y se aborta con un mensaje claro.
 */
function insertarLineasEnsamblaje($conectar, int $ensamblajeId, array $detalle): void
{
    $ensamblaje = executeQuery($conectar, "SELECT producto_id, color_id FROM ensamblaje WHERE id = :id", ['id' => $ensamblajeId]);
    $productoObjetivoId = intval($ensamblaje[0]['producto_id'] ?? 0);
    $colorObjetivoId = intval($ensamblaje[0]['color_id'] ?? 0);
    foreach ($detalle as $linea) {
        if ($linea['tipo'] === 'produccion') {
            $produccionId = $linea['molde_produccion_id'];

            $prod = executeQuery(
                $conectar,
                "SELECT id, fecha_hora_fin, deleted_at, enviado_ensamblaje FROM produccion WHERE id = :id",
                ['id' => $produccionId]
            );
            if (empty($prod)) {
                throw new Exception("La producción #$produccionId ya no existe.");
            }
            $configObjetivo = executeQuery($conectar, "
                SELECT x.item
                FROM produccion pd
                JOIN molde m ON m.id = pd.molde_id
                CROSS JOIN LATERAL jsonb_array_elements(COALESCE(m.js_producto, '[]'::jsonb)) rel
                JOIN producto pr ON pr.id = :producto_id
                CROSS JOIN LATERAL jsonb_array_elements(COALESCE(pr.js_configuracion, '[]'::jsonb)) x(item)
                WHERE pd.id = :produccion_id
                  AND (rel->>'producto_id')::bigint = pr.id
                  AND (x.item->>'molde_id')::bigint = m.id
                LIMIT 1
            ", ['produccion_id' => $produccionId, 'producto_id' => $productoObjetivoId]);
            if (empty($configObjetivo)) {
                throw new Exception("El molde de la producción #$produccionId no está configurado para el producto que intentas ensamblar.");
            }
            $configObjetivoItem = json_decode($configObjetivo[0]['item'] ?? '[]', true) ?: [];
            if (strtolower(trim($configObjetivoItem['necesita_ensamblaje'] ?? 'sí')) === 'no') {
                throw new Exception("El molde de la producción #$produccionId está configurado para pasar directo a empaquetado.");
            }
            if (!empty($prod[0]['deleted_at'])) {
                throw new Exception("La producción #$produccionId está inactiva.");
            }
            if (empty($prod[0]['fecha_hora_fin'])) {
                throw new Exception("La producción #$produccionId aún no ha finalizado su corrida.");
            }
            if (empty($prod[0]['enviado_ensamblaje'])) {
                throw new Exception("La producción #$produccionId aún no fue pasada a ensamblaje desde Producción.");
            }

            $yaUsada = executeQuery(
                $conectar,
                "SELECT id FROM rel_ensamblaje_producto
                 WHERE molde_produccion_id = :produccion_id AND deleted_at IS NULL",
                ['produccion_id' => $produccionId]
            );
            if (!empty($yaUsada)) {
                throw new Exception("La producción #$produccionId ya está vinculada a otro ensamblaje activo.");
            }

            $snapshotRows = executeQuery(
                $conectar,
                "SELECT
                     t1.id AS produccion_id,
                     mo.nombre AS molde_nombre,
                     t1.cantidad_producida_kg AS cantidad_kg,
                     t1.fecha_hora_fin,
                     (SELECT producto_id FROM ensamblaje WHERE id = :ensamblaje_id) AS producto_id,
                     t1.color_id,
                     co.nombre AS color_nombre_verif,
                     cm.nombre AS categoria_material_nombre_verif
                 FROM produccion t1
                 LEFT JOIN molde mo ON mo.id = t1.molde_id
                 LEFT JOIN color co ON co.id = t1.color_id
                 LEFT JOIN categoria_material cm ON cm.id = t1.categoria_material_id
                 WHERE t1.id = :id",
                ['id' => $produccionId, 'ensamblaje_id' => $ensamblajeId]
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
        } elseif ($linea['tipo'] === 'derivado') {
            // derivado_id apunta a material.id (material.derivado = TRUE)
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
        } elseif ($linea['tipo'] === 'complemento') {
            // tipo === 'complemento' -> se marca directo en
            // ensamblaje.ensamblaje_id_referido del ensamblaje-complemento.
            $complementoId = $linea['ensamblaje_complemento_id'];

            if ($complementoId == $ensamblajeId) {
                throw new Exception("Un ensamblaje no puede complementarse a sí mismo.");
            }

            $comp = executeQuery(
                $conectar,
                "SELECT ec.id, ec.fin, ec.deleted_at, ec.js_producto_emsamblado, ec.ensamblaje_id_referido,
                        ec.categoria_material_id, cm.nombre AS categoria_material_nombre,
                        COALESCE(ec.color_id, own.color_id) AS color_id
                 FROM ensamblaje ec
                 LEFT JOIN categoria_material cm ON cm.id = ec.categoria_material_id
                 LEFT JOIN LATERAL (
                    SELECT pd.color_id
                    FROM rel_ensamblaje_producto rep
                    JOIN produccion pd ON pd.id = rep.molde_produccion_id
                    WHERE rep.ensamblaje_id = ec.id AND rep.deleted_at IS NULL
                    LIMIT 1
                 ) own ON true
                 WHERE ec.id = :id",
                ['id' => $complementoId]
            );
            if (empty($comp)) {
                throw new Exception("El ensamblaje complemento #$complementoId ya no existe.");
            }
            if (!empty($comp[0]['deleted_at'])) {
                throw new Exception("El ensamblaje complemento #$complementoId está inactivo.");
            }
            if (empty($comp[0]['fin'])) {
                throw new Exception("El ensamblaje complemento #$complementoId aún no ha finalizado.");
            }
            if (stripos((string)($comp[0]['categoria_material_nombre'] ?? ''), 'primera') === false) {
                throw new Exception("El ensamblaje complemento #$complementoId no es de Primera Categoría.");
            }
            if ($colorObjetivoId <= 0 || intval($comp[0]['color_id'] ?? 0) !== $colorObjetivoId) {
                throw new Exception("El color del ensamblaje complemento #$complementoId no coincide con el color del producto que se está armando.");
            }
            if (!empty($comp[0]['js_producto_emsamblado'])) {
                $marcadoPara = json_decode($comp[0]['js_producto_emsamblado'], true) ?: [];
                if (intval($marcadoPara['producto_id'] ?? 0) !== $productoObjetivoId
                    || intval($marcadoPara['color_id'] ?? 0) !== $colorObjetivoId) {
                    throw new Exception("El ensamblaje complemento #$complementoId está reservado para otro producto o color.");
                }
            }
            if (!empty($comp[0]['ensamblaje_id_referido']) && intval($comp[0]['ensamblaje_id_referido']) !== $ensamblajeId) {
                throw new Exception("El ensamblaje complemento #$complementoId ya está vinculado a otro armado activo.");
            }

            // UPDATE atómico: la condición ensamblaje_id_referido IS NULL
            // es lo que garantiza la unicidad (evita condiciones de carrera
            // sin necesitar un SELECT ... FOR UPDATE aparte).
            $filas = executeNonQuery($conectar, "
                UPDATE ensamblaje
                SET ensamblaje_id_referido = :ensamblaje_id, update_at = NOW()
                WHERE id = :complemento_id AND ensamblaje_id_referido IS NULL
            ", [
                'ensamblaje_id'  => $ensamblajeId,
                'complemento_id' => $complementoId,
            ]);
            if ($filas === 0) {
                throw new Exception("El ensamblaje complemento #$complementoId ya fue tomado por otro armado justo ahora. Vuelve a intentarlo.");
            }
        }
    }
}
/**
 * Recalcula js_moldes_utilizados / js_derivados_utilizados como resúmenes
 * de conveniencia a partir del detalle activo real en
 * rel_ensamblaje_producto. El resumen de complementos YA NO se guarda como
 * columna (no existe js_complementos_utilizados en la tabla): se calcula
 * al vuelo en listarEnsamblajes()/obtenerEnsamblaje() vía
 * subquerySelectComplementosUtilizados().
 */
function recalcularResumenesEnsamblaje($conectar, int $ensamblajeId): void
{
    $moldes = executeQuery($conectar, "
        SELECT rep.molde_produccion_id AS produccion_id, mo.nombre AS molde_nombre,
            pd.cantidad_producida_kg AS cantidad_kg, pd.fecha,
            pd.categoria_material_id,
            cm.nombre AS categoria_material_nombre,
            COALESCE(upv.nombre_corto, 'KG') AS unidad_produccion_codigo
        FROM rel_ensamblaje_producto rep
        JOIN produccion pd ON pd.id = rep.molde_produccion_id
        JOIN ensamblaje e ON e.id = rep.ensamblaje_id
        LEFT JOIN molde mo ON mo.id = pd.molde_id
        LEFT JOIN categoria_material cm ON cm.id = pd.categoria_material_id
        LEFT JOIN producto pr ON pr.id = e.producto_id
        LEFT JOIN LATERAL jsonb_array_elements(pr.js_configuracion) AS x(item)
            ON (x.item->>'molde_id')::bigint = mo.id
        LEFT JOIN LATERAL (SELECT CASE
            WHEN split_part(pd.unico_molde_producto, '-', 2) = pr.id::text THEN COALESCE(pd.js_configuracion_moment, x.item)
            ELSE x.item END AS item) cfg ON true
        LEFT JOIN unidad_medida upv ON upv.id = NULLIF(cfg.item->>'salida_produccion_unidad_medida_id','')::bigint
        WHERE rep.ensamblaje_id = :id AND rep.deleted_at IS NULL AND rep.molde_produccion_id IS NOT NULL
    ", ['id' => $ensamblajeId]);

    $derivados = executeQuery($conectar, "
        SELECT rep.derivado_id, dv.nombre AS derivado_nombre
        FROM rel_ensamblaje_producto rep
        LEFT JOIN material dv ON dv.id = rep.derivado_id
        WHERE rep.ensamblaje_id = :id AND rep.deleted_at IS NULL AND rep.derivado_id IS NOT NULL
    ", ['id' => $ensamblajeId]);

    // La categoría del ARMADO es la categoría de sus producciones vinculadas,
    // solo si todas coinciden. Si hay categorías mixtas (o ninguna
    // producción, ej. armado hecho solo con derivados), queda NULL: ese
    // armado no puede complementar ni recibir complementos hasta que la
    // categoría quede definida sin ambigüedad.
    $categoriasDistintas = array_unique(array_filter(
        array_column($moldes, 'categoria_material_id'),
        fn($v) => $v !== null
    ));
    $categoriaMaterialId = count($categoriasDistintas) === 1 ? reset($categoriasDistintas) : null;

    executeNonQuery($conectar, "
        UPDATE ensamblaje SET
            js_moldes_utilizados    = :moldes,
            js_derivados_utilizados = :derivados,
            categoria_material_id   = :categoria_material_id
        WHERE id = :id
    ", [
        'id'        => $ensamblajeId,
        'moldes'    => json_encode($moldes, JSON_UNESCAPED_UNICODE),
        'derivados' => json_encode($derivados, JSON_UNESCAPED_UNICODE),
        'categoria_material_id' => $categoriaMaterialId,
    ]);
}
// Soft delete: desactiva el ensamblaje, sus líneas activas de
// rel_ensamblaje_producto (producción/derivado) Y libera los complementos
// que tuviera tomados (ensamblaje_id_referido = NULL en esos otros
// ensamblajes).
//
// NUEVO: la restricción de "ya está en Empaquetado, no se puede
// desactivar" solo aplica a la tablet de operario. El admin sí puede
// desactivar un ensamblaje aunque ya haya sido enviado a Empaquetado
// (por ejemplo, para corregir un error), y recibe un aviso extra
// recordándole revisar el stock de empaquetado, ya que desactivar el
// ensamblaje NO revierte automáticamente lo que ya se generó allá.
function eliminarEnsamblaje()
{
    $conectar = conectar_oll_BD();
    $id = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at, fin, enviado_empaquetado, js_producto_emsamblado FROM ensamblaje WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Registro de ensamblaje no encontrado.');
    if (!empty($existe[0]['deleted_at'])) responder(false, 'Este registro ya estaba inactivo.');

    if (esOperarioSesion()) {
        verificarPropiedadEnsamblaje($conectar, $id);
        if (!empty($existe[0]['fin']) || !empty($existe[0]['enviado_empaquetado'])) {
            responder(false, 'Este ensamblaje ya finalizó. Solo un administrador puede eliminarlo.');
        }
    }

    if (!empty($existe[0]['js_producto_emsamblado'])) {
        responder(false, 'Este ensamblaje ya fue marcado como complemento y no puede desactivarse.');
    }
    $eraEnviadoEmpaquetado = !empty($existe[0]['enviado_empaquetado']);
    if ($eraEnviadoEmpaquetado && esOperarioSesion()) {
        responder(false, 'Este ensamblaje ya fue enviado a Empaquetado y no puede desactivarse desde la tablet.');
    }

    $conectar->beginTransaction();
    try {
        executeNonQuery(
            $conectar,
            "UPDATE rel_ensamblaje_producto SET deleted_at = NOW(), update_at = NOW()
             WHERE ensamblaje_id = :id AND deleted_at IS NULL",
            ['id' => $id]
        );

        // Libera los ensamblajes-complemento que este armado tenía tomados.
        executeNonQuery(
            $conectar,
            "UPDATE ensamblaje SET ensamblaje_id_referido = NULL, update_at = NOW() WHERE ensamblaje_id_referido = :id",
            ['id' => $id]
        );

        $cambios = [[
            'campo' => 'Estado', 'valor_antes' => 'Activo',
            'valor_despues' => 'Inactivo (producciones/complementos liberados)'
                . ($eraEnviadoEmpaquetado ? ' — corrección de admin: ya estaba enviado a Empaquetado' : ''),
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
        $mensajeFinal = $eraEnviadoEmpaquetado
            ? 'Ensamblaje desactivado correctamente. Nota: este registro ya había sido enviado a Empaquetado; revisa si el stock de empaquetado generado a partir de él necesita corregirse también.'
            : 'Ensamblaje desactivado correctamente. Las producciones y complementos vinculados quedaron libres.';
        responder(true, $mensajeFinal);
    } catch (Throwable $e) {
        $conectar->rollBack();
        error_log("Error desactivando ensamblaje: " . $e->getMessage());
        responder(false, 'No se pudo desactivar el ensamblaje: ' . $e->getMessage());
    }
}

// Restaura el ensamblaje y las líneas que fueron desactivadas junto con él.
// Si alguna producción ya fue "atrapada" por otro ensamblaje mientras
// tanto, se aborta para no duplicar el uso.
//
// NOTA: los complementos que este ensamblaje tenía tomados se liberaron
// (ensamblaje_id_referido = NULL) al desactivar y NO se re-vinculan
// automáticamente al reactivar, porque no queda registro de cuáles eran
// (a diferencia de rel_ensamblaje_producto, que preserva la fila con
// deleted_at). Si necesitas conservar ese historial para poder
// re-vincularlos automáticamente, dímelo y lo resolvemos guardando ese
// dato en js_historial o en una columna aparte.
function reactivarEnsamblaje()
{
    if (esOperarioSesion()) responder(false, 'Solo un administrador puede reactivar ensamblajes.');
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
        responder(true, 'Ensamblaje reactivado correctamente. Nota: si tenía complementos vinculados, deberás volver a agregarlos manualmente en edición.');
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
    verificarPropiedadEnsamblaje($conectar, $id);
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

// Marca el fin real del armado con la hora del servidor. Exige el peso
// (kg) de salida del armado ('peso_kg'), que se guarda en
// ensamblaje.cantidad_peso_kg.
// Marca el fin real del armado con la hora del servidor. Exige la cantidad
// de salida del armado ('peso_kg', nombre del parámetro conservado por
// compatibilidad con el frontend, aunque ahora puede representar kg,
// unidades, u otra unidad configurada en producto.js_configuracion_empaquetado
// .salida_ensamblaje_unidad_medida_id). Se guarda en
// ensamblaje.cantidad_peso_kg (columna conservada por compatibilidad) +
// ensamblaje.unidad_salida_id (snapshot de qué unidad se usó). Si el
// producto no tiene esa config definida, se asume kg (comportamiento
// previo a este cambio, para no romper productos ya configurados solo
// parcialmente).
function finalizarEnsamblaje(int $id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $cantidadSalida = isset($_POST['peso_kg']) && $_POST['peso_kg'] !== '' ? floatval($_POST['peso_kg']) : null;

    $existe = executeQuery(
        $conectar,
        "SELECT e.id, e.deleted_at, e.inicio, e.fin, e.producto_id,
                p.js_configuracion_empaquetado
         FROM ensamblaje e
         LEFT JOIN producto p ON p.id = e.producto_id
         WHERE e.id = :id",
        ['id' => $id]
    );
    if (empty($existe)) responder(false, 'Registro de ensamblaje no encontrado.');
    $ensamblaje = $existe[0];
    verificarPropiedadEnsamblaje($conectar, $id);

    $configEmpaquetado = json_decode($ensamblaje['js_configuracion_empaquetado'] ?? '{}', true) ?: [];
    $unidadSalidaId = !empty($configEmpaquetado['salida_ensamblaje_unidad_medida_id'])
        ? intval($configEmpaquetado['salida_ensamblaje_unidad_medida_id'])
        : null;

    $unidadLabel = 'kg';
    if ($unidadSalidaId) {
        $u = executeQuery($conectar, "SELECT nombre_corto FROM unidad_medida WHERE id = :id", ['id' => $unidadSalidaId]);
        if (!empty($u)) $unidadLabel = $u[0]['nombre_corto'];
    }

    if (!empty($ensamblaje['deleted_at'])) responder(false, 'No puedes finalizar un ensamblaje inactivo.');
    if (empty($ensamblaje['inicio'])) responder(false, 'Primero debes iniciar el ensamblaje.');
    if (!empty($ensamblaje['fin'])) responder(false, 'Este ensamblaje ya fue finalizado.');
    if ($cantidadSalida === null || $cantidadSalida <= 0) {
        responder(false, "Debes indicar la cantidad de salida ($unidadLabel) de este armado para poder finalizarlo.");
    }

    $conectar->beginTransaction();
    try {
        $cambios = [[
            'campo' => 'Fin de ensamblaje',
            'valor_antes' => '(en curso)',
            'valor_despues' => "Finalizado ahora · {$cantidadSalida} {$unidadLabel}",
        ]];
        $movimiento   = obtenerMovimientoSesion('finalizar_ensamblaje', $cambios);
        $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeNonQuery($conectar, "
            UPDATE ensamblaje SET
                fin              = NOW(),
                cantidad_peso_kg = :cantidad_salida,
                unidad_salida_id = :unidad_salida_id,
                update_at        = NOW(),
                js_usuario       = :js_session,
                js_historial     = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id
        ", [
            'id'               => $id,
            'cantidad_salida'  => $cantidadSalida,
            'unidad_salida_id' => $unidadSalidaId,
            'js_session'       => $js_session,
            'js_historial'     => $js_historial,
        ]);

        $conectar->commit();
        responder(true, 'Ensamblaje finalizado.');
    } catch (Throwable $e) {
        $conectar->rollBack();
        error_log("Error finalizando ensamblaje: " . $e->getMessage());
        responder(false, 'No se pudo finalizar el ensamblaje: ' . $e->getMessage());
    }
}
// Marca un ensamblaje YA FINALIZADO como complemento de otro producto
// ($producto_objetivo_id). Solo guarda la intención en
// ensamblaje.js_producto_emsamblado; NO toca ensamblaje_id_referido — ese
// campo se llena después, cuando alguien lo VINCULA de verdad dentro de
// otro armado (vía guardarEnsamblaje / insertarLineasEnsamblaje).
function marcarComplemento()
{
    $conectar = conectar_oll_BD();
    $id = intval($_POST['id'] ?? 0);
    $productoObjetivoId = intval($_POST['producto_objetivo_id'] ?? 0);
    $colorObjetivoId = intval($_POST['color_objetivo_id'] ?? 0) ?: null;

    if (!$id) responder(false, 'ID inválido.');
    if ($productoObjetivoId <= 0) responder(false, 'Debes elegir el producto (y color) al que va a complementar.');

    $existe = executeQuery(
        $conectar,
        "SELECT e.id, e.deleted_at, e.fin, e.producto_id, e.js_producto_emsamblado, e.ensamblaje_id_referido, e.categoria_material_id, e.enviado_empaquetado,
                cm.nombre AS categoria_material_nombre
         FROM ensamblaje e
         LEFT JOIN categoria_material cm ON cm.id = e.categoria_material_id
         WHERE e.id = :id",
        ['id' => $id]
    );
    if (empty($existe)) responder(false, 'Registro de ensamblaje no encontrado.');
    $e = $existe[0];
    verificarPropiedadEnsamblaje($conectar, $id);

    if (!empty($e['deleted_at'])) responder(false, 'No puedes marcar como complemento un ensamblaje inactivo.');
    if (empty($e['fin'])) responder(false, 'Solo puedes marcar como complemento un ensamblaje ya finalizado.');
    if (!empty($e['js_producto_emsamblado'])) responder(false, 'Este ensamblaje ya fue marcado como complemento.');
    if (!empty($e['ensamblaje_id_referido'])) responder(false, 'Este ensamblaje ya está siendo utilizado como complemento.');
    if (!empty($e['enviado_empaquetado'])) responder(false, 'Este ensamblaje ya fue enviado a empaquetado; no puede marcarse como complemento.');
    if ($e['categoria_material_id'] === null) {
        responder(false, 'Este armado no tiene una categoría de material definida (o mezcla varias): no puede complementar. Envíalo a Empaquetado en su lugar.');
    }
    if (stripos((string)($e['categoria_material_nombre'] ?? ''), 'primera') === false) {
        responder(false, 'Solo los armados de Primera Categoría pueden pasar como complemento a los colgadores. Los de Segunda Categoría siguen como producto propio hacia Empaquetado.');
    }
    // Unidad de salida del PROPIO producto de este ensamblaje (misma lógica
// que finalizarEnsamblaje).
    $productoInfoConfig = executeQuery($conectar, "SELECT js_configuracion_empaquetado FROM producto WHERE id = :id", ['id' => $e['producto_id']]);
    $configEmpaquetado = json_decode($productoInfoConfig[0]['js_configuracion_empaquetado'] ?? '{}', true) ?: [];
    $unidadSalidaId = !empty($configEmpaquetado['salida_ensamblaje_unidad_medida_id'])
        ? intval($configEmpaquetado['salida_ensamblaje_unidad_medida_id'])
        : null;
    $unidadLabel = 'kg';
    if ($unidadSalidaId) {
        $u = executeQuery($conectar, "SELECT nombre_corto FROM unidad_medida WHERE id = :id", ['id' => $unidadSalidaId]);
        if (!empty($u)) $unidadLabel = $u[0]['nombre_corto'];
    }

    $cantidadEnsamblada = isset($_POST['cantidad_ensamblada']) && $_POST['cantidad_ensamblada'] !== ''
        ? floatval($_POST['cantidad_ensamblada']) : null;
    if ($cantidadEnsamblada === null || $cantidadEnsamblada <= 0) {
        responder(false, "Debes indicar la cantidad ensamblada ($unidadLabel) que va a complementar.");
    }

    // Color propio de ESTE ensamblaje, resuelto vía sus producciones vinculadas.
    $colorPropioRow = executeQuery($conectar, "
        SELECT COALESCE(e.color_id, own.color_id) AS color_id, coalesce(cfinal.nombre, co.nombre) AS color_nombre
        FROM ensamblaje e
        LEFT JOIN color cfinal ON cfinal.id = e.color_id
        LEFT JOIN LATERAL (
            SELECT p0.color_id FROM rel_ensamblaje_producto rep0
            JOIN produccion p0 ON p0.id = rep0.molde_produccion_id
            WHERE rep0.ensamblaje_id = e.id AND rep0.deleted_at IS NULL LIMIT 1
        ) own ON true
        LEFT JOIN color co ON co.id = own.color_id
        WHERE e.id = :id
    ", ['id' => $id]);
    $colorPropioId = $colorPropioRow[0]['color_id'] ?? null;
    $colorPropioNombre = $colorPropioRow[0]['color_nombre'] ?? null;

    $esAutoComplemento = ($productoObjetivoId == $e['producto_id']);

    if ($esAutoComplemento) {
        // Auto-complemento: el color SIEMPRE es el propio (heredado de su
        // producción). No requiere que exista ya otro armado abierto: el
        // producto+color ya se conocen por definición.
        if ($colorPropioId === null) {
            responder(false, 'No se pudo determinar el color de este armado (no tiene producciones vinculadas con color). No se puede auto-complementar.');
        }
        $colorFinalId = $colorPropioId;
        $colorFinalNombre = $colorPropioNombre;
    } else {
        // Complemento hacia OTRO producto: exige color EXACTO y que ese
        // producto+color+categoría siga con al menos un armado propio,
        // finalizado y libre (sigue "vivo" en el módulo).
        if ($colorObjetivoId === null) {
            responder(false, 'Debes elegir también el color del producto objetivo.');
        }
        if ($colorPropioId !== null && intval($colorPropioId) !== intval($colorObjetivoId)) {
            responder(false, 'El color de este armado no coincide con el color del producto objetivo. Deben ser exactamente el mismo color.');
        }

        $objetivoConMismaCategoria = executeQuery(
            $conectar,
            "SELECT t1.id
             FROM ensamblaje t1
             WHERE t1.producto_id = :producto_id AND t1.deleted_at IS NULL AND t1.fin IS NOT NULL
               AND t1.ensamblaje_id_referido IS NULL AND t1.categoria_material_id = :categoria_material_id
               AND t1.id != :propio_id
               AND COALESCE(t1.color_id, (
                    SELECT pd.color_id FROM rel_ensamblaje_producto rep
                    JOIN produccion pd ON pd.id = rep.molde_produccion_id
                    WHERE rep.ensamblaje_id = t1.id AND rep.deleted_at IS NULL LIMIT 1
               )) = :color_id
             LIMIT 1",
            [
                'producto_id' => $productoObjetivoId,
                'categoria_material_id' => $e['categoria_material_id'],
                'propio_id' => $id,
                'color_id' => $colorObjetivoId,
            ]
        );
        if (empty($objetivoConMismaCategoria)) {
            responder(false, 'El producto/color objetivo no tiene un armado propio, de la misma categoría de material y mismo color, para complementar.');
        }
        $colorFinalId = $colorObjetivoId;
        $colorRow = executeQuery($conectar, "SELECT nombre FROM color WHERE id = :id", ['id' => $colorObjetivoId]);
        $colorFinalNombre = $colorRow[0]['nombre'] ?? null;
    }

    $productoObjetivo = executeQuery(
        $conectar,
        "SELECT id, codigo, descripcion FROM producto WHERE id = :id AND activo = true",
        ['id' => $productoObjetivoId]
    );
    if (empty($productoObjetivo)) responder(false, 'El producto objetivo no existe o está inactivo.');
    $p = $productoObjetivo[0];

    $jsProductoEmsamblado = json_encode([
        'producto_id'  => $p['id'],
        'codigo'       => $p['codigo'],
        'descripcion'  => $p['descripcion'],
        'color_id'     => $colorFinalId,
        'color_nombre' => $colorFinalNombre,
    ], JSON_UNESCAPED_UNICODE);

    $cambios = [[
        'campo' => 'Complemento', 'valor_antes' => '(sin marcar)',
        'valor_despues' => "Complementa a {$p['codigo']} - {$p['descripcion']}" . ($colorFinalNombre ? " ({$colorFinalNombre})" : '')
            . ($esAutoComplemento ? ' [auto-complemento, mismo producto]' : ''),
    ]];
    $movimiento   = obtenerMovimientoSesion('marcar_complemento', $cambios);
    $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeNonQuery($conectar, "
        UPDATE ensamblaje SET
            js_producto_emsamblado = :js_producto_emsamblado,
            cantidad_peso_kg       = :cantidad_ensamblada,
            unidad_salida_id       = :unidad_salida_id,
            update_at              = NOW(),
            js_usuario              = :js_session,
            js_historial            = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id
    ", [
        'id'                     => $id,
        'js_producto_emsamblado' => $jsProductoEmsamblado,
        'cantidad_ensamblada'    => $cantidadEnsamblada,
        'unidad_salida_id'       => $unidadSalidaId,
        'js_session'             => $js_session,
        'js_historial'           => $js_historial,
    ]);

    responder(true, "Ensamblaje marcado como complemento de {$p['codigo']} - {$p['descripcion']}" . ($colorFinalNombre ? " ({$colorFinalNombre})" : '') . ".");
}
// Envía un ensamblaje YA FINALIZADO directo a Empaquetado, como producto
// terminado independiente, SIN pasar por COMPLEMENTAR. Mutuamente
// excluyente con "marcar como complemento": una vez elegido un camino,
// el otro deja de estar disponible (el frontend oculta el botón; aquí se
// revalida por si acaso).
function pasarAEmpaquetado(int $id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery(
        $conectar,
        "SELECT e.id, e.deleted_at, e.fin, e.enviado_empaquetado, e.js_producto_emsamblado,
                e.ensamblaje_id_referido, cm.nombre AS categoria_material_nombre
         FROM ensamblaje e
         LEFT JOIN categoria_material cm ON cm.id = e.categoria_material_id
         WHERE e.id = :id",
        ['id' => $id]
    );
    if (empty($existe)) responder(false, 'Registro de ensamblaje no encontrado.');
    $e = $existe[0];
    verificarPropiedadEnsamblaje($conectar, $id);

    if (!empty($e['deleted_at'])) responder(false, 'No puedes pasar a empaquetado un ensamblaje inactivo.');
    if (empty($e['fin'])) responder(false, 'Solo puedes pasar a empaquetado un ensamblaje ya finalizado.');
    if (!empty($e['enviado_empaquetado'])) responder(false, 'Este ensamblaje ya fue enviado a empaquetado.');
    if (!empty($e['js_producto_emsamblado'])) {
        responder(false, 'Este ensamblaje ya fue marcado como complemento de otro producto; no puede pasar también a empaquetado.');
    }
    if (!empty($e['ensamblaje_id_referido'])) {
        responder(false, 'Este ensamblaje ya está siendo utilizado como complemento y no puede pasar también a empaquetado.');
    }
    if (stripos(trim((string)($e['categoria_material_nombre'] ?? '')), 'segunda') === false) {
        responder(false, 'Solo los ensamblajes de Segunda Categoría pueden enviarse a empaquetado. Los de Primera quedan disponibles para usarse como complemento al armar un colgador.');
    }

    $cambios = [[
        'campo' => 'Envío a empaquetado', 'valor_antes' => '(no enviado)',
        'valor_despues' => 'Enviado a empaquetado',
    ]];
    $movimiento   = obtenerMovimientoSesion('enviar_empaquetado', $cambios);
    $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeNonQuery($conectar, "
        UPDATE ensamblaje SET
            enviado_empaquetado     = TRUE,
            fecha_envio_empaquetado = NOW(),
            update_at               = NOW(),
            js_usuario              = :js_session,
            js_historial            = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id
    ", ['id' => $id, 'js_session' => $js_session, 'js_historial' => $js_historial]);

    responder(true, 'Ensamblaje enviado a empaquetado correctamente.');
}

// Fusiona el ensamblaje $origenId dentro de $destinoId: mueve todas sus
// líneas activas de rel_ensamblaje_producto (producciones Y derivados,
// ambas viven en la misma tabla) hacia el destino, transfiere también
// cualquier complemento que el origen tuviera tomado (ensamblaje_id_referido
// apuntando a él), y deja el origen inactivo como constancia histórica.
// Reglas: mismo producto_id, origen no enviado a empaquetado ni marcado/
// tomado como complemento de otra cosa, destino aún abierto (sin fin,
// sin enviar a empaquetado, sin marcar como complemento).
function fusionarEnsamblaje()
{
    $conectar  = conectar_oll_BD();
    $origenId  = intval($_POST['origen_id'] ?? 0);
    $destinoId = intval($_POST['destino_id'] ?? 0);

    if (!$origenId) responder(false, 'ID de origen inválido.');
    if (!$destinoId) responder(false, 'ID de destino inválido.');
    if ($origenId === $destinoId) responder(false, 'No puedes fusionar un ensamblaje consigo mismo.');

    $origenRows  = executeQuery($conectar, "SELECT * FROM ensamblaje WHERE id = :id", ['id' => $origenId]);
    $destinoRows = executeQuery($conectar, "SELECT * FROM ensamblaje WHERE id = :id", ['id' => $destinoId]);
    if (empty($origenRows))  responder(false, 'El ensamblaje de origen no existe.');
    if (empty($destinoRows)) responder(false, 'El ensamblaje de destino no existe.');
    $o = $origenRows[0];
    $d = $destinoRows[0];

    if (!empty($o['deleted_at'])) responder(false, 'El ensamblaje de origen ya está inactivo.');
    if (!empty($d['deleted_at'])) responder(false, 'El ensamblaje de destino está inactivo.');
    if ($o['producto_id'] != $d['producto_id']) responder(false, 'Solo puedes fusionar armados del mismo producto.');
    if (!empty($o['enviado_empaquetado'])) responder(false, 'El armado de origen ya fue enviado a empaquetado; no puede fusionarse.');
    if (!empty($o['js_producto_emsamblado'])) responder(false, 'El armado de origen ya fue marcado como complemento de otro producto; no puede fusionarse.');
    if (!empty($o['ensamblaje_id_referido'])) responder(false, 'El armado de origen ya fue tomado como complemento dentro de otro armado; no puede fusionarse.');
    if (!empty($d['fin'])) responder(false, 'El armado de destino ya fue finalizado; no puede recibir más líneas.');
    if (!empty($d['enviado_empaquetado'])) responder(false, 'El armado de destino ya fue enviado a empaquetado.');
    if (!empty($d['js_producto_emsamblado'])) responder(false, 'El armado de destino ya fue marcado como complemento de otro producto.');

    $conectar->beginTransaction();
    try {
        // Mueve TODAS las líneas activas de origen -> destino (producciones
        // y derivados viven en la misma tabla, un solo UPDATE cubre ambas).
        executeNonQuery($conectar, "
            UPDATE rel_ensamblaje_producto
            SET ensamblaje_id = :destino_id, update_at = NOW()
            WHERE ensamblaje_id = :origen_id AND deleted_at IS NULL
        ", ['destino_id' => $destinoId, 'origen_id' => $origenId]);

        // Transfiere también cualquier complemento que el origen tuviera
        // tomado (ensamblaje_id_referido apuntando al origen) hacia el
        // destino, en vez de dejarlo huérfano apuntando a un registro
        // inactivo.
        executeNonQuery($conectar, "
            UPDATE ensamblaje
            SET ensamblaje_id_referido = :destino_id, update_at = NOW()
            WHERE ensamblaje_id_referido = :origen_id AND deleted_at IS NULL
        ", ['destino_id' => $destinoId, 'origen_id' => $origenId]);

        $pesoOrigenTxt = $o['cantidad_peso_kg'] !== null ? ($o['cantidad_peso_kg'] . ' kg') : 'sin peso registrado';

        $movOrigen = obtenerMovimientoSesion('fusionar_origen', [[
            'campo' => 'Fusión', 'valor_antes' => 'Activo',
            'valor_despues' => "Fusionado dentro del ensamblaje #$destinoId (inactivado)",
        ]]);
        executeNonQuery($conectar, "
            UPDATE ensamblaje SET
                deleted_at   = NOW(),
                update_at    = NOW(),
                js_usuario   = :js_session,
                js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id
        ", [
            'id'           => $origenId,
            'js_session'   => json_encode($movOrigen, JSON_UNESCAPED_UNICODE),
            'js_historial' => json_encode([$movOrigen], JSON_UNESCAPED_UNICODE),
        ]);

        $movDestino = obtenerMovimientoSesion('fusionar_destino', [[
            'campo' => 'Fusión', 'valor_antes' => '(sin fusión)',
            'valor_despues' => "Absorbió las líneas del ensamblaje #$origenId ($pesoOrigenTxt)",
        ]]);
        executeNonQuery($conectar, "
            UPDATE ensamblaje SET
                update_at    = NOW(),
                js_usuario   = :js_session,
                js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id
        ", [
            'id'           => $destinoId,
            'js_session'   => json_encode($movDestino, JSON_UNESCAPED_UNICODE),
            'js_historial' => json_encode([$movDestino], JSON_UNESCAPED_UNICODE),
        ]);

        recalcularResumenesEnsamblaje($conectar, $destinoId);

        $conectar->commit();
        responder(true, "Ensamblaje #$origenId fusionado dentro de #$destinoId correctamente.");
    } catch (Throwable $e) {
        $conectar->rollBack();
        error_log("Error fusionando ensamblaje: " . $e->getMessage());
        responder(false, 'No se pudo fusionar el ensamblaje: ' . $e->getMessage());
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
