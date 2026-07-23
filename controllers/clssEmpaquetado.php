<?php

/**
 * controllers/clssEmpaquetado.php
 * Controlador del módulo de Empaquetado
 *
 * Tablas reales:
 *   producto_empaquetado_nivel (id, producto_id -> producto, nivel, nombre_nivel,
 *               cantidad_por_unidad, permite_variado, created_at, update_at,
 *               deleted_at, js_usuario, js_historial)
 *   empaquetado_unidad (id, ensamblaje_id -> ensamblaje, nivel_config_id ->
 *               producto_empaquetado_nivel, operario_id -> operario,
 *               color_id -> color [NULL = variado], cantidad_contenida,
 *               unidad_padre_id -> empaquetado_unidad [self-FK, NULL = suelta],
 *               created_at, update_at, deleted_at, js_usuario, js_historial)
 *
 * Vistas usadas (ver sql_empaquetado.sql):
 *   view_ensamblajes_pendientes_empaquetado
 *   view_empaquetado_disponible
 *
 * SUPUESTO (sin confirmar): `color` se asume color(id, nombre, ...), igual
 * patrón que en clssEnsamblaje.php. Ajustar buscarColores() si difiere.
 *
 * MODELO (decidido en conversación, ver cabecera del SQL):
 *   No existe una tabla "sesión" de empaquetado con inicio/fin. Cada fila de
 *   empaquetado_unidad es un acto discreto: "armé esta bolsa" (nivel 1) o
 *   "agrupé estas N bolsas en una gruesa" (nivel > 1). El árbol se arma vía
 *   unidad_padre_id (self-FK).
 *
 *   Niveles configurables por producto en producto_empaquetado_nivel: nivel 1
 *   es el más cercano al ensamblaje (ej. Bolsa), y así sucesivamente. Cada
 *   producto puede tener 1, 2, 3+ niveles sin tocar código.
 *
 *   cantidad_contenida:
 *     - nivel 1: cantidad de PIEZAS del producto contenidas en esa unidad
 *       (sugerido = cantidad_por_unidad del nivel 1, editable por el operario).
 *     - nivel > 1: cantidad de UNIDADES HIJAS (nivel inmediatamente inferior)
 *       agrupadas. Se calcula automático (COUNT de las hijas seleccionadas),
 *       no es editable directamente.
 *
 *   Color en niveles > 1: NUNCA se elige directo. Se DERIVA de las hijas
 *   agrupadas, y se exige que todas compartan el mismo color_id (o todas
 *   sean NULL = variado). Si hay mezcla, se rechaza (regla de negocio: "una
 *   gruesa siempre agrupa unidades del mismo tipo").
 *
 *   REGLA DE UNICIDAD (mismo patrón que produccion/ensamblaje): una unidad
 *   solo puede ser hija de UNA unidad padre a la vez. Al agruparla,
 *   unidad_padre_id deja de ser NULL y ya no aparece como "disponible".
 *
 *   Para deshacer una agrupación existe DESAGRUPARUNIDAD: libera las hijas
 *   (unidad_padre_id = NULL) y desactiva (soft-delete) la unidad padre. No
 *   se permite ELIMINARUNIDAD directo sobre una unidad que tiene hijas
 *   activas; primero hay que desagrupar.
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
        case 'LISTARENSAMBLAJESPENDIENTESEMPAQUETADO':
            listarEnsamblajesPendientesEmpaquetado();
            break;
        case 'OBTENERNIVELESPRODUCTO':
            obtenerNivelesProducto(intval($_POST['producto_id'] ?? 0));
            break;
        case 'GUARDARNIVELPRODUCTO':
            guardarNivelProducto();
            break;
        case 'ELIMINARNIVELPRODUCTO':
            eliminarNivelProducto();
            break;
        case 'LISTARUNIDADESENSAMBLAJE':
            listarUnidadesEnsamblaje(intval($_POST['ensamblaje_id'] ?? 0));
            break;
        case 'BUSCARUNIDADESDISPONIBLES':
            buscarUnidadesDisponibles();
            break;
        case 'CREARUNIDADEMPAQUETADO':
            crearUnidadEmpaquetado();
            break;
        case 'DESAGRUPARUNIDAD':
            desagruparUnidad(intval($_POST['id'] ?? 0));
            break;
        case 'ELIMINARUNIDAD':
            eliminarUnidad(intval($_POST['id'] ?? 0));
            break;
        case 'BUSCAROPERARIOS':
            buscarOperarios();
            break;
        case 'BUSCARCOLORES':
            buscarColores();
            break;
        case 'BUSCARPRODUCTOS':
            buscarProductos();
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
    $sql = "SELECT id, nombre_completo, cargo FROM operario WHERE activo = true ORDER BY nombre_completo";
    $result = executeQuery($conectar, $sql, []);
    responder(true, 'OK', ['operario' => $result]);
}

// SUPUESTO: color(id, nombre). Ajustar si la tabla real tiene otras columnas.
function buscarColores()
{
    $conectar = conectar_oll_BD();
    $texto = trim($_POST['texto'] ?? '');

    $where  = ["1=1"];
    $params = [];
    if ($texto !== '') {
        $where[] = "LOWER(nombre) LIKE LOWER(:texto)";
        $params['texto'] = "%$texto%";
    }

    $sql = "SELECT id, nombre FROM color WHERE " . implode(' AND ', $where) . " ORDER BY nombre LIMIT 100";
    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['colores' => $result]);
}

// SUPUESTO: mismo patrón que clssEnsamblaje.php -> producto(id, codigo,
// descripcion, activo, peso_unitario_g).
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

// Ensamblajes finalizados con cantidad pendiente de embolsar (nivel 1).
// Alimenta el listado/botón "Pasar a Empaquetado".
function listarEnsamblajesPendientesEmpaquetado()
{
    $conectar    = conectar_oll_BD();
    $texto       = trim($_POST['texto'] ?? '');
    $productoId  = trim($_POST['producto_id'] ?? '');
    $soloConSaldo = ($_POST['solo_con_saldo'] ?? '1') === '1';

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(producto_codigo) LIKE LOWER(:texto) OR LOWER(producto_descripcion) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }
    if ($productoId !== '') {
        $where[] = "producto_id = :producto_id";
        $params['producto_id'] = $productoId;
    }
    if ($soloConSaldo) {
        // Deja pasar también los que no tienen peso_unitario_g configurado
        // (cantidad_sugerida_pendiente = NULL) para no ocultarlos por un
        // dato de catálogo incompleto; el frontend avisa que no hay sugerido.
        $where[] = "(cantidad_sugerida_pendiente IS NULL OR cantidad_sugerida_pendiente > 0)";
    }

    $sql = "SELECT * FROM view_ensamblajes_pendientes_empaquetado
            WHERE " . implode(' AND ', $where) . "
            ORDER BY fin DESC";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['ensamblajes' => $result]);
}

// =============================================================================
// CONFIGURACIÓN DE NIVELES POR PRODUCTO
// =============================================================================

function obtenerNivelesProducto(int $productoId)
{
    if (!$productoId) responder(false, 'ID de producto inválido.');
    $conectar = conectar_oll_BD();

    $result = executeQuery(
        $conectar,
        "SELECT * FROM producto_empaquetado_nivel
         WHERE producto_id = :producto_id AND deleted_at IS NULL
         ORDER BY nivel ASC",
        ['producto_id' => $productoId]
    );
    responder(true, 'OK', ['niveles' => $result]);
}

function guardarNivelProducto()
{
    $conectar = conectar_oll_BD();

    $id                  = intval($_POST['id'] ?? 0);
    $producto_id         = intval($_POST['producto_id'] ?? 0);
    $nivel               = intval($_POST['nivel'] ?? 0);
    $nombre_nivel        = trim($_POST['nombre_nivel'] ?? '');
    $cantidad_por_unidad = floatval($_POST['cantidad_por_unidad'] ?? 0);
    $permite_variado     = filter_var($_POST['permite_variado'] ?? 'true', FILTER_VALIDATE_BOOLEAN);

    if ($producto_id <= 0) responder(false, 'Debes seleccionar el producto.');
    if ($nivel <= 0) responder(false, 'El número de nivel debe ser mayor a 0.');
    if ($nombre_nivel === '') responder(false, 'Debes indicar un nombre para el nivel (ej. "Bolsa", "Gruesa").');
    if ($cantidad_por_unidad <= 0) responder(false, 'La cantidad por unidad debe ser mayor a 0.');

    $producto = executeQuery($conectar, "SELECT id FROM producto WHERE id = :id AND activo = true", ['id' => $producto_id]);
    if (empty($producto)) responder(false, 'El producto seleccionado no existe o está inactivo.');

    // Unicidad (producto_id, nivel) entre los activos, excluyendo el propio registro en edición.
    $paramsUnicidad = ['producto_id' => $producto_id, 'nivel' => $nivel];
    $sqlUnicidad = "SELECT id FROM producto_empaquetado_nivel
                     WHERE producto_id = :producto_id AND nivel = :nivel AND deleted_at IS NULL";
    if ($id > 0) {
        $sqlUnicidad .= " AND id != :id";
        $paramsUnicidad['id'] = $id;
    }
    $choque = executeQuery($conectar, $sqlUnicidad, $paramsUnicidad);
    if (!empty($choque)) {
        responder(false, "Ya existe un nivel $nivel configurado para este producto.");
    }

    if ($id === 0) {
        $cambios = [[
            'campo' => 'Nivel de empaquetado', 'valor_antes' => '(nuevo)',
            'valor_despues' => "$nombre_nivel (nivel $nivel, x$cantidad_por_unidad)",
        ]];
        $movimiento   = obtenerMovimientoSesionEmp('crear', $cambios);
        $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        $nuevo = executeQuery($conectar, "
            INSERT INTO producto_empaquetado_nivel (
                producto_id, nivel, nombre_nivel, cantidad_por_unidad, permite_variado,
                created_at, js_usuario, js_historial
            ) VALUES (
                :producto_id, :nivel, :nombre_nivel, :cantidad_por_unidad, :permite_variado,
                NOW(), :js_usuario, :js_historial
            ) RETURNING id
        ", [
            'producto_id'         => $producto_id,
            'nivel'               => $nivel,
            'nombre_nivel'        => $nombre_nivel,
            'cantidad_por_unidad' => $cantidad_por_unidad,
            'permite_variado'     => $permite_variado ? 'true' : 'false',
            'js_usuario'          => $js_session,
            'js_historial'        => $js_historial,
        ]);

        responder(true, 'Nivel de empaquetado creado correctamente.', ['id' => $nuevo[0]['id'] ?? null]);
    } else {
        $actual = executeQuery($conectar, "SELECT id FROM producto_empaquetado_nivel WHERE id = :id AND deleted_at IS NULL", ['id' => $id]);
        if (empty($actual)) responder(false, 'Nivel de empaquetado no encontrado o inactivo.');

        $cambios = [[
            'campo' => 'Nivel de empaquetado', 'valor_antes' => '(editado)',
            'valor_despues' => "$nombre_nivel (nivel $nivel, x$cantidad_por_unidad)",
        ]];
        $movimiento   = obtenerMovimientoSesionEmp('editar', $cambios);
        $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeNonQuery($conectar, "
            UPDATE producto_empaquetado_nivel SET
                nivel               = :nivel,
                nombre_nivel        = :nombre_nivel,
                cantidad_por_unidad = :cantidad_por_unidad,
                permite_variado     = :permite_variado,
                update_at           = NOW(),
                js_usuario          = :js_usuario,
                js_historial        = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id
        ", [
            'nivel'               => $nivel,
            'nombre_nivel'        => $nombre_nivel,
            'cantidad_por_unidad' => $cantidad_por_unidad,
            'permite_variado'     => $permite_variado ? 'true' : 'false',
            'js_usuario'          => $js_session,
            'js_historial'        => $js_historial,
            'id'                  => $id,
        ]);

        responder(true, 'Nivel de empaquetado actualizado correctamente.', ['id' => $id]);
    }
}

function eliminarNivelProducto()
{
    $conectar = conectar_oll_BD();
    $id = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id FROM producto_empaquetado_nivel WHERE id = :id AND deleted_at IS NULL", ['id' => $id]);
    if (empty($existe)) responder(false, 'Nivel de empaquetado no encontrado o ya estaba inactivo.');

    $enUso = executeQuery(
        $conectar,
        "SELECT id FROM empaquetado_unidad WHERE nivel_config_id = :id AND deleted_at IS NULL LIMIT 1",
        ['id' => $id]
    );
    if (!empty($enUso)) {
        responder(false, 'No puedes eliminar este nivel: ya hay unidades de empaquetado activas registradas con él.');
    }

    $cambios = [['campo' => 'Estado', 'valor_antes' => 'Activo', 'valor_despues' => 'Inactivo']];
    $movimiento   = obtenerMovimientoSesionEmp('desactivar', $cambios);
    $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeNonQuery($conectar, "
        UPDATE producto_empaquetado_nivel SET
            deleted_at   = NOW(),
            update_at    = NOW(),
            js_usuario   = :js_usuario,
            js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id
    ", ['id' => $id, 'js_usuario' => $js_session, 'js_historial' => $js_historial]);

    responder(true, 'Nivel de empaquetado eliminado correctamente.');
}

// =============================================================================
// UNIDADES DE EMPAQUETADO
// =============================================================================

// Historial completo (sueltas + consumidas) de un ensamblaje, para armar el
// árbol visual bolsa -> gruesa -> ... en el frontend.
function listarUnidadesEnsamblaje(int $ensamblajeId)
{
    if (!$ensamblajeId) responder(false, 'ID de ensamblaje inválido.');
    $conectar = conectar_oll_BD();

    $result = executeQuery($conectar, "
        SELECT
            eu.id, eu.ensamblaje_id, eu.nivel_config_id, eu.operario_id,
            eu.color_id, eu.cantidad_contenida, eu.unidad_padre_id, eu.created_at,
            pen.nivel, pen.nombre_nivel, pen.cantidad_por_unidad,
            co.nombre AS color_nombre,
            op.nombre_completo AS operario_nombre
        FROM empaquetado_unidad eu
        JOIN producto_empaquetado_nivel pen ON pen.id = eu.nivel_config_id
        LEFT JOIN color co ON co.id = eu.color_id
        LEFT JOIN operario op ON op.id = eu.operario_id
        WHERE eu.ensamblaje_id = :ensamblaje_id AND eu.deleted_at IS NULL
        ORDER BY pen.nivel ASC, eu.created_at ASC
    ", ['ensamblaje_id' => $ensamblajeId]);

    responder(true, 'OK', ['unidades' => $result]);
}

// Unidades sueltas (disponibles) de un ensamblaje en un nivel específico.
// Usado para elegir las "hijas" al agrupar hacia el siguiente nivel.
function buscarUnidadesDisponibles()
{
    $conectar      = conectar_oll_BD();
    $ensamblajeId  = intval($_POST['ensamblaje_id'] ?? 0);
    $nivelConfigId = intval($_POST['nivel_config_id'] ?? 0);

    if (!$ensamblajeId) responder(false, 'ID de ensamblaje inválido.');

    $where  = ["ensamblaje_id = :ensamblaje_id"];
    $params = ['ensamblaje_id' => $ensamblajeId];
    if ($nivelConfigId > 0) {
        $where[] = "nivel_config_id = :nivel_config_id";
        $params['nivel_config_id'] = $nivelConfigId;
    }

    $sql = "SELECT * FROM view_empaquetado_disponible
            WHERE " . implode(' AND ', $where) . "
            ORDER BY nivel ASC, created_at ASC";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['unidades' => $result]);
}

/**
 * Crea una unidad de empaquetado.
 * - Nivel 1: cantidad_contenida viene del operario (sugerido editable),
 *   color_id opcional (o requerido si el nivel no permite_variado).
 * - Nivel > 1: se arma a partir de "hijas" (ids de unidades sueltas del
 *   nivel inmediatamente inferior). cantidad_contenida y color_id se
 *   derivan automáticamente; se exige homogeneidad de color entre hijas.
 */
function crearUnidadEmpaquetado()
{
    $conectar = conectar_oll_BD();

    $ensamblajeId  = intval($_POST['ensamblaje_id'] ?? 0);
    $nivelConfigId = intval($_POST['nivel_config_id'] ?? 0);
    $operarioId    = !empty($_POST['operario_id']) ? intval($_POST['operario_id']) : null;

    if (!$ensamblajeId) responder(false, 'ID de ensamblaje inválido.');
    if (!$nivelConfigId) responder(false, 'Debes indicar el nivel de empaquetado.');

    $ensamblaje = executeQuery(
        $conectar,
        "SELECT id, producto_id, fin, deleted_at FROM ensamblaje WHERE id = :id",
        ['id' => $ensamblajeId]
    );
    if (empty($ensamblaje)) responder(false, 'El ensamblaje indicado no existe.');
    if (!empty($ensamblaje[0]['deleted_at'])) responder(false, 'Este ensamblaje está inactivo.');
    if (empty($ensamblaje[0]['fin'])) responder(false, 'Este ensamblaje aún no ha finalizado; no se puede empaquetar todavía.');

    $nivelConfig = executeQuery(
        $conectar,
        "SELECT * FROM producto_empaquetado_nivel WHERE id = :id AND deleted_at IS NULL",
        ['id' => $nivelConfigId]
    );
    if (empty($nivelConfig)) responder(false, 'El nivel de empaquetado indicado no existe o está inactivo.');
    $nivelConfig = $nivelConfig[0];

    if ((int)$nivelConfig['producto_id'] !== (int)$ensamblaje[0]['producto_id']) {
        responder(false, 'Este nivel de empaquetado no corresponde al producto de este ensamblaje.');
    }

    $conectar->beginTransaction();
    try {
        if ((int)$nivelConfig['nivel'] === 1) {
            $cantidad = floatval($_POST['cantidad_contenida'] ?? 0);
            $colorId  = !empty($_POST['color_id']) ? intval($_POST['color_id']) : null;

            if ($cantidad <= 0) throw new Exception('Debes indicar cuántas piezas contiene esta unidad.');
            if (!$nivelConfig['permite_variado'] && $colorId === null) {
                throw new Exception('Este nivel requiere especificar un color (no admite variado).');
            }

            $cambios = [[
                'campo' => 'Empaquetado', 'valor_antes' => '(nuevo)',
                'valor_despues' => "{$nivelConfig['nombre_nivel']} x$cantidad",
            ]];
            $movimiento   = obtenerMovimientoSesionEmp('crear', $cambios);
            $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
            $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

            $nueva = executeQuery($conectar, "
                INSERT INTO empaquetado_unidad (
                    ensamblaje_id, nivel_config_id, operario_id, color_id,
                    cantidad_contenida, unidad_padre_id,
                    created_at, js_usuario, js_historial
                ) VALUES (
                    :ensamblaje_id, :nivel_config_id, :operario_id, :color_id,
                    :cantidad_contenida, NULL,
                    NOW(), :js_usuario, :js_historial
                ) RETURNING id
            ", [
                'ensamblaje_id'       => $ensamblajeId,
                'nivel_config_id'     => $nivelConfigId,
                'operario_id'         => $operarioId,
                'color_id'            => $colorId,
                'cantidad_contenida'  => $cantidad,
                'js_usuario'          => $js_session,
                'js_historial'        => $js_historial,
            ]);

            $conectar->commit();
            responder(true, "{$nivelConfig['nombre_nivel']} registrada correctamente.", ['id' => $nueva[0]['id'] ?? null]);
        } else {
            $hijasJson = trim($_POST['hijas'] ?? '[]');
            $hijasIds  = json_decode($hijasJson, true);
            if (!is_array($hijasIds)) $hijasIds = [];
            $hijasIds = array_values(array_unique(array_map('intval', array_filter($hijasIds, fn($v) => intval($v) > 0))));

            if (empty($hijasIds)) {
                throw new Exception("Selecciona al menos una unidad para agrupar en {$nivelConfig['nombre_nivel']}.");
            }

            $nivelAnterior = executeQuery(
                $conectar,
                "SELECT id FROM producto_empaquetado_nivel
                 WHERE producto_id = :producto_id AND nivel = :nivel_anterior AND deleted_at IS NULL",
                ['producto_id' => $nivelConfig['producto_id'], 'nivel_anterior' => $nivelConfig['nivel'] - 1]
            );
            if (empty($nivelAnterior)) {
                throw new Exception('No existe el nivel inmediatamente anterior configurado para este producto.');
            }
            $nivelAnteriorId = $nivelAnterior[0]['id'];

            // Placeholders dinámicos para el IN (:h0, :h1, ...)
            $placeholders = [];
            $paramsHijas  = [
                'ensamblaje_id'   => $ensamblajeId,
                'nivel_anterior'  => $nivelAnteriorId,
            ];
            foreach ($hijasIds as $i => $hid) {
                $ph = ":h$i";
                $placeholders[] = $ph;
                $paramsHijas["h$i"] = $hid;
            }

            $hijas = executeQuery($conectar, "
                SELECT id, color_id FROM empaquetado_unidad
                WHERE id IN (" . implode(',', $placeholders) . ")
                  AND ensamblaje_id = :ensamblaje_id
                  AND nivel_config_id = :nivel_anterior
                  AND unidad_padre_id IS NULL
                  AND deleted_at IS NULL
                FOR UPDATE
            ", $paramsHijas);

            if (count($hijas) !== count($hijasIds)) {
                throw new Exception('Alguna de las unidades seleccionadas ya no está disponible (pudo haber sido agrupada o eliminada por otro usuario). Actualiza la lista e inténtalo de nuevo.');
            }

            // Homogeneidad de color: todas deben compartir el mismo color_id
            // (o todas ser NULL = variado).
            $coloresDistintos = [];
            foreach ($hijas as $h) {
                $clave = $h['color_id'] === null ? '__VARIADO__' : $h['color_id'];
                $coloresDistintos[$clave] = true;
            }
            if (count($coloresDistintos) > 1) {
                throw new Exception("No puedes agrupar unidades de colores/tipos distintos en un mismo {$nivelConfig['nombre_nivel']}.");
            }
            $colorResultante = $hijas[0]['color_id'];
            $cantidadContenida = count($hijas);

            $cambios = [[
                'campo' => 'Empaquetado', 'valor_antes' => '(nuevo)',
                'valor_despues' => "{$nivelConfig['nombre_nivel']} agrupando $cantidadContenida unidad(es)",
            ]];
            $movimiento   = obtenerMovimientoSesionEmp('crear', $cambios);
            $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
            $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

            $nueva = executeQuery($conectar, "
                INSERT INTO empaquetado_unidad (
                    ensamblaje_id, nivel_config_id, operario_id, color_id,
                    cantidad_contenida, unidad_padre_id,
                    created_at, js_usuario, js_historial
                ) VALUES (
                    :ensamblaje_id, :nivel_config_id, :operario_id, :color_id,
                    :cantidad_contenida, NULL,
                    NOW(), :js_usuario, :js_historial
                ) RETURNING id
            ", [
                'ensamblaje_id'      => $ensamblajeId,
                'nivel_config_id'    => $nivelConfigId,
                'operario_id'        => $operarioId,
                'color_id'           => $colorResultante,
                'cantidad_contenida' => $cantidadContenida,
                'js_usuario'         => $js_session,
                'js_historial'       => $js_historial,
            ]);
            $nuevaId = $nueva[0]['id'] ?? null;
            if (!$nuevaId) throw new Exception('No se pudo crear la unidad agrupada.');

            // Cierra las hijas: quedan consumidas por la nueva unidad padre.
            $paramsCierre = ['padre_id' => $nuevaId];
            $placeholdersCierre = [];
            foreach ($hijasIds as $i => $hid) {
                $ph = ":c$i";
                $placeholdersCierre[] = $ph;
                $paramsCierre["c$i"] = $hid;
            }
            executeNonQuery($conectar, "
                UPDATE empaquetado_unidad
                SET unidad_padre_id = :padre_id, update_at = NOW()
                WHERE id IN (" . implode(',', $placeholdersCierre) . ")
            ", $paramsCierre);

            $conectar->commit();
            responder(true, "{$nivelConfig['nombre_nivel']} armada correctamente con $cantidadContenida unidad(es).", ['id' => $nuevaId]);
        }
    } catch (Throwable $e) {
        $conectar->rollBack();
        error_log("Error creando unidad de empaquetado: " . $e->getMessage());
        responder(false, 'No se pudo registrar: ' . $e->getMessage());
    }
}

// Deshace una agrupación: libera las hijas (vuelven a estar disponibles) y
// desactiva la unidad padre.
function desagruparUnidad(int $id)
{
    if (!$id) responder(false, 'ID inválido.');
    $conectar = conectar_oll_BD();

    $unidad = executeQuery($conectar, "SELECT * FROM empaquetado_unidad WHERE id = :id AND deleted_at IS NULL", ['id' => $id]);
    if (empty($unidad)) responder(false, 'Unidad de empaquetado no encontrada o ya inactiva.');

    $hijas = executeQuery($conectar, "SELECT id FROM empaquetado_unidad WHERE unidad_padre_id = :id AND deleted_at IS NULL", ['id' => $id]);
    if (empty($hijas)) {
        responder(false, 'Esta unidad no agrupa ninguna otra; no hay nada que desagrupar. Usa "Eliminar" si quieres quitarla.');
    }

    $conectar->beginTransaction();
    try {
        executeNonQuery(
            $conectar,
            "UPDATE empaquetado_unidad SET unidad_padre_id = NULL, update_at = NOW() WHERE unidad_padre_id = :id",
            ['id' => $id]
        );

        $cambios = [['campo' => 'Estado', 'valor_antes' => 'Activo', 'valor_despues' => 'Inactivo (unidades liberadas)']];
        $movimiento   = obtenerMovimientoSesionEmp('desagrupar', $cambios);
        $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeNonQuery($conectar, "
            UPDATE empaquetado_unidad SET
                deleted_at   = NOW(),
                update_at    = NOW(),
                js_usuario   = :js_usuario,
                js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id
        ", ['id' => $id, 'js_usuario' => $js_session, 'js_historial' => $js_historial]);

        $conectar->commit();
        responder(true, 'Se desagrupó correctamente. Las unidades quedaron disponibles de nuevo.');
    } catch (Throwable $e) {
        $conectar->rollBack();
        error_log("Error desagrupando unidad: " . $e->getMessage());
        responder(false, 'No se pudo desagrupar: ' . $e->getMessage());
    }
}

// Elimina (soft-delete) una unidad suelta que no agrupa nada (ej. una bolsa
// mal contada). Si tiene hijas activas, exige desagrupar primero.
function eliminarUnidad(int $id)
{
    if (!$id) responder(false, 'ID inválido.');
    $conectar = conectar_oll_BD();

    $unidad = executeQuery($conectar, "SELECT * FROM empaquetado_unidad WHERE id = :id AND deleted_at IS NULL", ['id' => $id]);
    if (empty($unidad)) responder(false, 'Unidad de empaquetado no encontrada o ya inactiva.');

    $tieneHijas = executeQuery($conectar, "SELECT id FROM empaquetado_unidad WHERE unidad_padre_id = :id AND deleted_at IS NULL LIMIT 1", ['id' => $id]);
    if (!empty($tieneHijas)) {
        responder(false, 'Esta unidad agrupa otras; desagrúpala primero antes de eliminarla.');
    }

    $cambios = [['campo' => 'Estado', 'valor_antes' => 'Activo', 'valor_despues' => 'Inactivo']];
    $movimiento   = obtenerMovimientoSesionEmp('eliminar', $cambios);
    $js_session   = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeNonQuery($conectar, "
        UPDATE empaquetado_unidad SET
            deleted_at   = NOW(),
            update_at    = NOW(),
            js_usuario   = :js_usuario,
            js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id
    ", ['id' => $id, 'js_usuario' => $js_session, 'js_historial' => $js_historial]);

    responder(true, 'Unidad eliminada correctamente.');
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