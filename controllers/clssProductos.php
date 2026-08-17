<?php
ob_start();

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
    $producto['js_configuracion'] = json_decode($producto['js_configuracion'] ?? '[]', true) ?: [];
    $producto['js_configuracion_empaquetado'] = json_decode($producto['js_configuracion_empaquetado'] ?? '{}', true) ?: [];

    // 1) Recolectar TODOS los ids de unidad usados, tanto por molde
    //    (js_configuracion) como a nivel producto (js_configuracion_empaquetado),
    //    para resolverlos en una sola consulta.
    $unidadIds = [];
    foreach ($producto['js_configuracion'] as $c) {
        foreach (['salida_produccion_unidad_medida_id', 'salida_merma_unidad_medida_id'] as $campo) {
            if (!empty($c[$campo])) $unidadIds[] = (int) $c[$campo];
        }
    }
    foreach ([
        'se_vende_por_unidad_medida_id',
        'salida_empaquetado_unidad_medida_id',
        'salida_ensamblaje_unidad_medida_id',
    ] as $campo) {
        if (!empty($producto['js_configuracion_empaquetado'][$campo])) {
            $unidadIds[] = (int) $producto['js_configuracion_empaquetado'][$campo];
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

    $detalleLegible = function ($uid) use ($unidadesDetalle) {
        return isset($unidadesDetalle[$uid])
            ? $unidadesDetalle[$uid]['nombre_corto'] . ' - ' . $unidadesDetalle[$uid]['nombre']
            : null;
    };

    // 3a) Adjuntar detalle legible a cada fila de js_configuracion (por molde)
    if (!empty($producto['js_configuracion'])) {
        foreach ($producto['js_configuracion'] as &$c) {
            foreach ([
                'salida_produccion_unidad_medida_id' => 'salida_produccion_detalle',
                'salida_merma_unidad_medida_id'      => 'salida_merma_detalle',
            ] as $campoId => $campoDetalle) {
                $uid = $c[$campoId] ?? null;
                $c[$campoDetalle] = $detalleLegible($uid);
            }
        }
        unset($c);
    }

    // 3b) Adjuntar detalle legible a js_configuracion_empaquetado (a nivel producto)
    if (!empty($producto['js_configuracion_empaquetado'])) {
        foreach ([
            'se_vende_por_unidad_medida_id'       => 'se_vende_por_detalle',
            'salida_empaquetado_unidad_medida_id' => 'salida_empaquetado_detalle',
            'salida_ensamblaje_unidad_medida_id'  => 'salida_ensamblaje_detalle',
        ] as $campoId => $campoDetalle) {
            $uid = $producto['js_configuracion_empaquetado'][$campoId] ?? null;
            $producto['js_configuracion_empaquetado'][$campoDetalle] = $detalleLegible($uid);
        }
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
 *     "salida_produccion_unidad_medida_id": 1,   "salida_produccion": "unidades",
 *     "salida_merma_unidad_medida_id": 1,        "salida_merma": "unidades"
 *   },
 *   ...
 * ]
 * Se sobreescribe por completo el arreglo (siempre se manda el set completo
 * de moldes del producto desde el front).
 *
 * Además se recibe un JSON (string) con la configuración a nivel PRODUCTO
 * (no por molde), guardado en producto.js_configuracion_empaquetado:
 * {
 *   "se_vende_por_unidad_medida_id": 5,
 *   "salida_empaquetado_unidad_medida_id": 1,
 *   "salida_ensamblaje_unidad_medida_id": 1   (solo si algún molde necesita ensamblaje)
 * }
 */
function guardarConfigProducto()
{
    $conectar        = conectar_oll_BD();
    $producto_id     = intval($_POST['producto_id'] ?? 0);
    $configJson      = $_POST['configuraciones'] ?? '[]';
    $empaquetadoJson = $_POST['configuracion_venta'] ?? '{}';

    if (!$producto_id) responder(false, 'Producto inválido.');

    $configuraciones = json_decode($configJson, true);
    if (!is_array($configuraciones)) responder(false, 'Formato de configuración inválido.');
    // Un mismo molde no puede aparecer dos veces en la config del producto:
    // rompería obtenerItemConfigProductoMolde() (que asume unicidad) y
    // dejaría ambigüedad de cuál fila es la "vigente" para ese molde.
    $moldeIdsVistos = [];
    foreach ($configuraciones as $c) {
        $mid = $c['molde_id'] ?? null;
        if ($mid === null) continue; // esto ya se valida más abajo como "falta el molde"
        if (in_array($mid, $moldeIdsVistos, true)) {
            responder(false, 'El molde "' . ($c['molde'] ?? $mid) . '" aparece más de una vez en la configuración.');
        }
        $moldeIdsVistos[] = $mid;
    }
    $configuracionEmpaquetado = json_decode($empaquetadoJson, true);
    if (!is_array($configuracionEmpaquetado)) responder(false, 'Formato de configuración de empaquetado inválido.');

    $existe = executeQuery($conectar, "SELECT id FROM producto WHERE id = :id", ['id' => $producto_id]);
    if (empty($existe)) responder(false, 'Producto no encontrado.');

    // "Se vende por" es a nivel producto (una sola vez, no por molde)
    if (empty($configuracionEmpaquetado['se_vende_por_unidad_medida_id'])) {
        responder(false, 'Falta "Se vende por" para el producto.');
    }
    if (empty($configuracionEmpaquetado['salida_empaquetado_unidad_medida_id'])) {
        responder(false, 'Falta "Salida en Empaquetado" para el producto.');
    }

    // "Salida en Ensamblaje" también es a nivel producto (el ensamblaje
    // arma el producto terminado a partir de varios moldes, así que la
    // unidad de salida no depende de un molde puntual). Solo es
    // obligatoria si al menos un molde del producto necesita ensamblaje;
    // si ninguno lo necesita, ese producto nunca genera una fila en
    // `ensamblaje` (ver clssProduccion::enviarAEnsamblaje), así que no
    // tiene sentido exigir el dato.
    $algunMoldeNecesitaEnsamblaje = false;
    foreach ($configuraciones as $c) {
        if (($c['necesita_ensamblaje'] ?? 'no') === 'sí') {
            $algunMoldeNecesitaEnsamblaje = true;
            break;
        }
    }
    if ($algunMoldeNecesitaEnsamblaje && empty($configuracionEmpaquetado['salida_ensamblaje_unidad_medida_id'])) {
        responder(false, 'Falta "Salida en Ensamblaje" para el producto.');
    }

    // Validación mínima de cada fila (una por molde) — ya SIN se_vende_por
    // y SIN salida_ensamblaje (ahora viven en configuracionEmpaquetado, no aquí).
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
        // Normaliza complementa_a: array de {producto_id, codigo, descripcion}.
        // Opcional — solo lo llenan los moldes cuyo armado puede complementar
        // a otro producto (ej. MOLDE GANCHO PINZA).
        $c['complementa_a'] = is_array($c['complementa_a'] ?? null)
            ? array_values(array_filter($c['complementa_a'], fn($p) => !empty($p['producto_id'])))
            : [];
    }
    unset($c);

    $jsConfiJson       = json_encode($configuraciones, JSON_UNESCAPED_UNICODE);
    $jsEmpaquetadoJson = json_encode($configuracionEmpaquetado, JSON_UNESCAPED_UNICODE);

    executeQuery($conectar, "
        UPDATE producto SET
            js_configuracion             = :js_configuracion,
            js_configuracion_empaquetado = :js_configuracion_empaquetado,
            updated_at = NOW()
        WHERE id = :id
    ", [
        'js_configuracion'             => $jsConfiJson,
        'js_configuracion_empaquetado' => $jsEmpaquetadoJson,
        'id'                           => $producto_id,
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