<?php
ob_start();

/**
 * controllers/clssProductos.php
 * Controlador del módulo de Productos (Mercadería para la venta)
 * Tabla real: producto (singular) — id, codigo, descripcion, unidad_venta_id,
 *             cant_equivale, unidad_equivale_id, peso_unitario_g, activo,
 *             js_configuracion (jsonb — configuración por producto+molde),
 *             created_at, updated_at
 *
 * Convención de nombres dentro de cada fila de js_configuracion:
 *   se_vende_por_unidad_medida_id / se_vende_por          (texto corto en minúscula)
 *   salida_produccion_unidad_medida_id / salida_produccion
 *   salida_merma_unidad_medida_id / salida_merma
 * El sufijo "_unidad_medida_id" deja explícito que ese id apunta a
 * unidad_medida.id, así cualquier query o revisión rápida del JSON queda
 * clara sin tener que adivinar a qué tabla referencia.
 *
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    controladorProductos($_POST["accion"]);
}

function controladorProductos($accion)
{
    switch ($accion) {
        case 'LISTARUNIDADES':
            listarUnidades();
            break;
        case 'LISTARPRODUCTOS':
            listarProductos();
            break;
        case 'OBTENERPRODUCTO':
            obtenerProducto(intval($_POST['id'] ?? 0));
            break;
        case 'GUARDARPRODUCTO':
            guardarProducto();
            break;
        case 'ELIMINARPRODUCTO':
            eliminarProducto();
            break;
        case 'REACTIVARPRODUCTO':
            reactivarProducto();
            break;
        case 'GUARDARCONFIGPRODUCTO':
            guardarConfigProducto();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// CATÁLOGOS
// =============================================================================

function listarUnidades()
{
    $conectar = conectar_oll_BD();
    $result   = executeQuery(
        $conectar,
        "SELECT id, nombre_corto AS codigo, nombre 
         FROM unidad_medida 
         WHERE deleted_at IS NULL
         ORDER BY nombre"
    );
    responder(true, 'OK', ['unidades' => $result]);
}
// =============================================================================
// PRODUCTOS
// =============================================================================

function listarProductos()
{
    $conectar = conectar_oll_BD();

    $texto  = trim($_POST['texto']  ?? '');
    $estado = trim($_POST['estado'] ?? ''); // '', 'activo', 'inactivo'

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(p.codigo) LIKE LOWER(:texto) OR LOWER(p.descripcion) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }
    if ($estado === 'activo') {
        $where[] = "p.activo = TRUE";
    } elseif ($estado === 'inactivo') {
        $where[] = "p.activo = FALSE";
    }

    $sql = "
        SELECT p.*,
            uv.nombre_corto AS unidad_venta_codigo,
            uv.nombre AS unidad_venta_nombre,
            ue.nombre_corto AS unidad_equivale_codigo,
            ue.nombre AS unidad_equivale_nombre
        FROM producto p
        LEFT JOIN unidad_medida uv ON uv.id = p.unidad_venta_id
        LEFT JOIN unidad_medida ue ON ue.id = p.unidad_equivale_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY p.codigo
    ";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['productos' => $result]);
}

/**
 * Obtiene un producto por id, junto con su js_configuracion decodificado.
 * Cada fila de js_configuracion se enriquece con el detalle real (nombre_corto
 * - nombre) de se_vende_por_unidad_medida_id, salida_produccion_unidad_medida_id
 * y salida_merma_unidad_medida_id, resuelto en el momento contra unidad_medida.
 * Así el front (o cualquier otro módulo que consuma esta configuración) no
 * depende del texto que haya quedado guardado en el JSON, que podría
 * desactualizarse si alguien renombra una unidad de medida más adelante.
 */
function obtenerProducto($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $result = executeQuery(
        $conectar,
        "SELECT p.*,
            uv.nombre_corto AS unidad_venta_codigo,
            uv.nombre AS unidad_venta_nombre,
            ue.nombre_corto AS unidad_equivale_codigo,
            ue.nombre AS unidad_equivale_nombre
         FROM producto p
         LEFT JOIN unidad_medida uv ON uv.id = p.unidad_venta_id
         LEFT JOIN unidad_medida ue ON ue.id = p.unidad_equivale_id
         WHERE p.id = :id",
        ['id' => $id]
    );
    if (empty($result)) responder(false, 'Producto no encontrado.');

    $producto = $result[0];
    // js_configuracion: lista de configuraciones, una por cada molde asociado al producto
    $producto['js_configuracion'] = json_decode($producto['js_configuracion'] ?? '[]', true) ?: [];

    if (!empty($producto['js_configuracion'])) {
        // 1) Recolectar todos los ids de unidad usados en toda la configuración
        $unidadIds = [];
        foreach ($producto['js_configuracion'] as $c) {
            foreach (['salida_produccion_unidad_medida_id', 'salida_merma_unidad_medida_id'] as $campo) {
                if (!empty($c[$campo])) $unidadIds[] = (int) $c[$campo];
            }
        }
        $unidadIds = array_unique($unidadIds);

        // 2) Resolverlos todos en una sola consulta
        $unidadesDetalle = [];
        if (!empty($unidadIds)) {
            $placeholders = [];
            $params = [];
            foreach ($unidadIds as $i => $uid) {
                $key = "u$i";
                $placeholders[] = ":$key";
                $params[$key] = $uid;
            }
            $rowsUnidades = executeQuery(
                $conectar,
                "SELECT id, nombre_corto, nombre FROM unidad_medida WHERE id IN (" . implode(',', $placeholders) . ")",
                $params
            );
            foreach ($rowsUnidades as $u) {
                $unidadesDetalle[$u['id']] = $u;
            }
        }

        // 3) Adjuntar el detalle legible a cada fila de configuración
        foreach ($producto['js_configuracion'] as &$c) {
            foreach ([
                'salida_produccion_unidad_medida_id' => 'salida_produccion_detalle',
                'salida_merma_unidad_medida_id'      => 'salida_merma_detalle',
            ] as $campoId => $campoDetalle) {
                $uid = $c[$campoId] ?? null;
                $c[$campoDetalle] = isset($unidadesDetalle[$uid])
                    ? $unidadesDetalle[$uid]['nombre_corto'] . ' - ' . $unidadesDetalle[$uid]['nombre']
                    : null;
            }
        }
        unset($c);
    }

    responder(true, 'OK', ['producto' => $producto]);
}

function guardarProducto()
{
    $conectar           = conectar_oll_BD();
    $id                 = intval($_POST['id'] ?? 0);
    $codigo             = strtoupper(trim($_POST['codigo'] ?? ''));
    $descripcion        = strtoupper(trim($_POST['descripcion'] ?? ''));
    $unidad_venta_id    = intval($_POST['unidad_venta_id'] ?? 0);
    $cant_equivale      = is_numeric($_POST['cant_equivale'] ?? '') ? floatval($_POST['cant_equivale']) : null;
    $unidad_equivale_id = intval($_POST['unidad_equivale_id'] ?? 0) ?: null;
    $peso_unitario_g    = is_numeric($_POST['peso_unitario_g'] ?? '') ? floatval($_POST['peso_unitario_g']) : null;

    // ── Validaciones ──────────────────────────────────────────────────────────
    if (empty($codigo))          responder(false, 'El código es obligatorio.');
    if (empty($descripcion))     responder(false, 'La descripción es obligatoria.');
    if ($unidad_venta_id <= 0)   responder(false, 'Selecciona la unidad de venta.');

    // Código único (excluyendo el propio registro si es edición)
    $chk = executeQuery(
        $conectar,
        "SELECT id FROM producto WHERE LOWER(codigo) = LOWER(:cod) AND id <> :id",
        ['cod' => $codigo, 'id' => $id]
    );
    if (!empty($chk)) responder(false, 'Ya existe un producto con ese código.');

    if ($id === 0) {
        $result = executeQuery($conectar, "
            INSERT INTO producto
                (codigo, descripcion, unidad_venta_id, cant_equivale, unidad_equivale_id, peso_unitario_g, activo)
            VALUES (:cod, :desc, :uv, :ceq, :ueq, :peso, TRUE)
            RETURNING id
        ", [
            'cod'  => $codigo,
            'desc' => $descripcion,
            'uv'   => $unidad_venta_id,
            'ceq'  => $cant_equivale,
            'ueq'  => $unidad_equivale_id,
            'peso' => $peso_unitario_g,
        ]);
        $nuevo_id = $result[0]['id'] ?? null;
        responder(true, 'Producto creado correctamente.', ['id' => $nuevo_id, 'modo' => 'crear']);
    } else {
        executeQuery($conectar, "
            UPDATE producto SET
                codigo = :cod,
                descripcion = :desc,
                unidad_venta_id = :uv,
                cant_equivale = :ceq,
                unidad_equivale_id = :ueq,
                peso_unitario_g = :peso,
                updated_at = NOW()
            WHERE id = :id
        ", [
            'cod'  => $codigo,
            'desc' => $descripcion,
            'uv'   => $unidad_venta_id,
            'ceq'  => $cant_equivale,
            'ueq'  => $unidad_equivale_id,
            'peso' => $peso_unitario_g,
            'id'   => $id,
        ]);
        responder(true, 'Producto actualizado correctamente.', ['id' => $id, 'modo' => 'editar']);
    }
}

/**
 * Guarda la configuración de un producto por cada molde asociado.
 * Se recibe un JSON (string) con un array de objetos:
 * [
 *   {
 *     "molde_id": 2,
 *     "molde": "molde 1",
 *     "necesita_ensamblaje": "sí" | "no",
 *     "se_vende_por_unidad_medida_id": 5,        "se_vende_por": "gruesas",
 *     "salida_produccion_unidad_medida_id": 1,   "salida_produccion": "unidades",
 *     "salida_merma_unidad_medida_id": 1,        "salida_merma": "unidades"
 *   },
 *   ...
 * ]
 * Se sobreescribe por completo el arreglo (siempre se manda el set completo
 * de moldes del producto desde el front).
 */
function guardarConfigProducto()
{
    $conectar     = conectar_oll_BD();
    $producto_id  = intval($_POST['producto_id'] ?? 0);
    $configJson   = $_POST['configuraciones'] ?? '[]';
    $ventaJson    = $_POST['configuracion_venta'] ?? '{}';

    if (!$producto_id) responder(false, 'Producto inválido.');

    $configuraciones = json_decode($configJson, true);
    if (!is_array($configuraciones)) responder(false, 'Formato de configuración inválido.');

    $configuracionVenta = json_decode($ventaJson, true);
    if (!is_array($configuracionVenta)) responder(false, 'Formato de configuración de venta inválido.');

    $existe = executeQuery($conectar, "SELECT id FROM producto WHERE id = :id", ['id' => $producto_id]);
    if (empty($existe)) responder(false, 'Producto no encontrado.');

    // "Se vende por" es a nivel producto (una sola vez, no por molde)
    if (empty($configuracionVenta['se_vende_por_unidad_medida_id'])) {
        responder(false, 'Falta "Se vende por" para el producto.');
    }

    // Validación mínima de cada fila (una por molde) — ya SIN se_vende_por
    foreach ($configuraciones as $c) {
        if (empty($c['molde_id'])) responder(false, 'Falta el molde en una de las configuraciones.');
        if (empty($c['salida_produccion_unidad_medida_id'])) responder(false, 'Falta "Salida en Producción" para el molde "' . ($c['molde'] ?? '') . '".');
        if (empty($c['salida_merma_unidad_medida_id']))      responder(false, 'Falta "Salida de Merma" para el molde "' . ($c['molde'] ?? '') . '".');
    }

    // Se conserva el created_at de cada fila si ya existía antes (misma molde_id)
    $actual = executeQuery($conectar, "SELECT js_configuracion FROM producto WHERE id = :id", ['id' => $producto_id]);
    $configAnterior = json_decode($actual[0]['js_configuracion'] ?? '[]', true) ?: [];
    $creadosPorMolde = [];
    foreach ($configAnterior as $prev) {
        if (!empty($prev['molde_id'])) {
            $creadosPorMolde[$prev['molde_id']] = $prev['created_at'] ?? null;
        }
    }

    $ahora = date('Y-m-d H:i:s');
    foreach ($configuraciones as &$c) {
        $c['created_at'] = $creadosPorMolde[$c['molde_id']] ?? $ahora;
        $c['updated_at'] = $ahora;
    }
    unset($c);

    $jsConfiJson  = json_encode($configuraciones, JSON_UNESCAPED_UNICODE);
    $jsVentaJson  = json_encode($configuracionVenta, JSON_UNESCAPED_UNICODE);

    executeQuery($conectar, "
        UPDATE producto SET
            js_configuracion       = :js_configuracion,
            js_configuracion_venta = :js_configuracion_venta,
            updated_at = NOW()
        WHERE id = :id
    ", [
        'js_configuracion'       => $jsConfiJson,
        'js_configuracion_venta' => $jsVentaJson,
        'id'                     => $producto_id,
    ]);

    responder(true, 'Configuración guardada correctamente.', ['producto_id' => $producto_id]);
}

// Soft delete: no se borra físicamente, solo se marca activo = FALSE.
function eliminarProducto()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, activo FROM producto WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Producto no encontrado.');
    if ($existe[0]['activo'] === false) responder(false, 'Este producto ya estaba inactivo.');

    executeQuery(
        $conectar,
        "UPDATE producto SET activo = FALSE, updated_at = NOW() WHERE id = :id",
        ['id' => $id]
    );
    responder(true, 'Producto desactivado correctamente.');
}

function reactivarProducto()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, activo FROM producto WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Producto no encontrado.');
    if ($existe[0]['activo'] === true) responder(false, 'Este producto ya estaba activo.');

    executeQuery(
        $conectar,
        "UPDATE producto SET activo = TRUE, updated_at = NOW() WHERE id = :id",
        ['id' => $id]
    );
    responder(true, 'Producto reactivado correctamente.');
}

// =============================================================================
// HELPER
// =============================================================================

function responder(bool $ok, string $msg, array $extra = []): void
{
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}