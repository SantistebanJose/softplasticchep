<?php

/**
 * controllers/clssMoldes.php
 * Controlador del módulo de Moldes
 * Tabla real: molde (id, nombre, forma, js_producto, js_session, js_historial, created_at, update_at, deleted_at)
 * js_producto: jsonb array de objetos [{producto_id, codigo, descripcion}, ...]
 *   -> Un molde puede ser usado por varios productos (ej: molde ovalado sirve para
 *      "Colgador ovalado" y "Colgador osito"). Ya no existe una FK única producto_id.
 * Soft delete vía deleted_at (no existe columna 'activo').
 * bd.php y executeQuery.php viven en esta misma carpeta (controllers/).
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    controladorMoldes($_POST["accion"]);
}

function controladorMoldes($accion)
{
    switch ($accion) {
        case 'LISTARMOLDES':
            listarMoldes();
            break;
        case 'LISTARMOLDESPRODUCTO':
            listarMoldesProducto();
            break;
        case 'OBTENERMOLDE':
            obtenerMolde(intval($_POST['id'] ?? 0));
            break;
        case 'GUARDARMOLDE':
            guardarMolde();
            break;
        case 'ELIMINARMOLDE':
            eliminarMolde();
            break;
        case 'REACTIVARMOLDE':
            reactivarMolde();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// MOLDES
// =============================================================================

function listarMoldes()
{
    $conectar = conectar_oll_BD();

    $texto  = trim($_POST['texto'] ?? '');
    $estado = trim($_POST['estado'] ?? ''); // '', 'activa', 'inactiva'

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "(LOWER(m.nombre) LIKE LOWER(:texto) OR LOWER(m.forma) LIKE LOWER(:texto) OR EXISTS (
            SELECT 1 FROM jsonb_array_elements(m.js_producto) AS elem
            WHERE LOWER(elem->>'codigo') LIKE LOWER(:texto)
               OR LOWER(elem->>'descripcion') LIKE LOWER(:texto)
        ))";
        $params['texto'] = "%$texto%";
    }
    if ($estado === 'activa') {
        $where[] = "m.deleted_at IS NULL";
    } elseif ($estado === 'inactiva') {
        $where[] = "m.deleted_at IS NOT NULL";
    }

    $sql = "
        SELECT m.*
        FROM molde m
        WHERE " . implode(' AND ', $where) . "
        ORDER BY m.nombre
    ";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['moldes' => decodificarProductosLista($result)]);
}
/**
 * Lista una fila por cada combinación molde-producto (un molde puede
 * servir para varios productos). 'unico' = "moldeId-productoId", útil
 * como value del <select> cuando se necesita saber a qué producto
 * específico corresponde el avance, no solo qué molde se usó.
 */
function listarMoldesProducto()
{
    $conectar = conectar_oll_BD();

    $sql = "
        SELECT
            m.id AS molde_id,
            m.nombre,
            (elem->>'producto_id')::int AS producto_id,
            elem->>'codigo'       AS codigo_producto,
            elem->>'descripcion'  AS producto,
            CONCAT(m.id,'-',(elem->>'producto_id')) AS unico,
            CONCAT(m.nombre,' — ',elem->>'descripcion') AS etiqueta
        FROM molde m
        LEFT JOIN LATERAL jsonb_array_elements(m.js_producto) AS elem ON true
        WHERE m.deleted_at IS NULL
        ORDER BY m.nombre, elem->>'descripcion'
    ";

    $result = executeQuery($conectar, $sql, []);
    responder(true, 'OK', ['moldes_producto' => $result]);
}
function obtenerMolde($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $result = executeQuery(
        $conectar,
        "SELECT * FROM molde WHERE id = :id",
        ['id' => $id]
    );
    if (empty($result)) responder(false, 'Molde no encontrado.');

    $molde = $result[0];
    $molde['js_producto'] = json_decode($molde['js_producto'] ?? '[]', true) ?: [];
    responder(true, 'OK', ['molde' => $molde]);
}

/**
 * Convierte el js_producto (texto jsonb) de cada fila en un array PHP real,
 * para que el JSON de salida no venga como string escapado.
 */
function decodificarProductosLista(array $filas): array
{
    foreach ($filas as &$fila) {
        $fila['js_producto'] = json_decode($fila['js_producto'] ?? '[]', true) ?: [];
    }
    unset($fila);
    return $filas;
}

/**
 * Obtiene la IP real del cliente, considerando proxies/balanceadores comunes.
 */
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

/**
 * Arma el bloque de auditoría (usuario/sesión) para un movimiento dado.
 */
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

/**
 * Compara un registro anterior contra los datos nuevos y devuelve solo los
 * campos cuyo valor cambió, mapeados con etiqueta legible.
 */
function compararCambios(array $anterior, array $nuevo, array $mapaCampos): array
{
    $cambios = [];
    foreach ($mapaCampos as $campo => $etiqueta) {
        $valorAntes   = $anterior[$campo] ?? null;
        $valorDespues = $nuevo[$campo]    ?? null;

        $antesComp   = ($valorAntes   === '' ? null : $valorAntes);
        $despuesComp = ($valorDespues === '' ? null : $valorDespues);

        if ($antesComp !== $despuesComp) {
            $cambios[] = [
                'campo'         => $etiqueta,
                'valor_antes'   => $valorAntes   ?? '(vacío)',
                'valor_despues' => $valorDespues ?? '(vacío)',
            ];
        }
    }
    return $cambios;
}

/**
 * Texto legible "COD - Descripcion, COD - Descripcion" a partir de un
 * array de productos [{producto_id, codigo, descripcion}, ...].
 */
function etiquetaListaProductos(array $productos): string
{
    if (empty($productos)) return '(vacío)';
    return implode(', ', array_map(
        fn($p) => ($p['codigo'] ?? '') . ' - ' . ($p['descripcion'] ?? ''),
        $productos
    ));
}

function guardarMolde()
{
    $conectar = conectar_oll_BD();
    $id          = intval($_POST['id'] ?? 0);
    $nombre      = trim($_POST['nombre'] ?? '');
    $forma       = trim($_POST['forma'] ?? '');
    $productoIds = $_POST['producto_ids'] ?? []; // array del multi-select

    if (!is_array($productoIds)) $productoIds = [$productoIds];
    $productoIds = array_values(array_unique(array_filter(array_map('intval', $productoIds))));

    // ── Validaciones ──────────────────────────────────────────────────────────
    if (empty($nombre))      responder(false, 'El nombre es obligatorio.');
    if (empty($forma))       responder(false, 'La forma es obligatoria.');
    if (empty($productoIds)) responder(false, 'Selecciona al menos un producto asociado.');

    // Nombre único (excluyendo el propio registro si es edición)
    $chk = executeQuery(
        $conectar,
        "SELECT id FROM molde WHERE LOWER(nombre) = LOWER(:nombre) AND id <> :id",
        ['nombre' => $nombre, 'id' => $id]
    );
    if (!empty($chk)) responder(false, 'Ya existe un molde con ese nombre.');

    // Traemos codigo/descripcion frescos de los productos seleccionados
    $placeholders = [];
    $params = [];
    foreach ($productoIds as $i => $pid) {
        $key = "pid$i";
        $placeholders[] = ":$key";
        $params[$key] = $pid;
    }
    $productos = executeQuery(
        $conectar,
        "SELECT id, codigo, descripcion FROM producto WHERE id IN (" . implode(',', $placeholders) . ")",
        $params
    );

    if (count($productos) !== count($productoIds)) {
        responder(false, 'Uno o más productos seleccionados no existen.');
    }

    $jsProducto = array_map(fn($p) => [
        'producto_id' => (int) $p['id'],
        'codigo'      => $p['codigo'],
        'descripcion' => $p['descripcion'],
    ], $productos);
    $jsProductoJson = json_encode($jsProducto, JSON_UNESCAPED_UNICODE);

    $mapaCampos  = ['nombre' => 'Nombre', 'forma' => 'Forma'];
    $datosNuevos = ['nombre' => $nombre, 'forma' => $forma];

    if ($id === 0) {
        // Creación
        $cambios = compararCambios([], $datosNuevos, $mapaCampos);
        $cambios[] = [
            'campo'         => 'Productos',
            'valor_antes'   => '(vacío)',
            'valor_despues' => etiquetaListaProductos($jsProducto),
        ];

        $movimiento          = obtenerMovimientoSesion('crear', $cambios);
        $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        $result = executeQuery($conectar, "
            INSERT INTO molde (nombre, forma, js_producto, created_at, js_session, js_historial)
            VALUES (:nombre, :forma, :js_producto, NOW(), :js_session, :js_historial)
            RETURNING id
        ", [
            'nombre'       => $nombre,
            'forma'        => $forma,
            'js_producto'  => $jsProductoJson,
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
        ]);
        $nuevo_id = $result[0]['id'] ?? null;
        responder(true, 'Molde creado correctamente.', ['id' => $nuevo_id, 'modo' => 'crear']);
    } else {
        // Edición: traemos el registro actual para comparar campo por campo
        $actual = executeQuery($conectar, "SELECT * FROM molde WHERE id = :id", ['id' => $id]);
        if (empty($actual)) responder(false, 'Molde no encontrado.');
        $registroAnterior = $actual[0];

        $cambios = compararCambios($registroAnterior, $datosNuevos, $mapaCampos);

        $productosAntes  = json_decode($registroAnterior['js_producto'] ?? '[]', true) ?: [];
        $etiquetaAntes   = etiquetaListaProductos($productosAntes);
        $etiquetaDespues = etiquetaListaProductos($jsProducto);
        if ($etiquetaAntes !== $etiquetaDespues) {
            $cambios[] = [
                'campo'         => 'Productos',
                'valor_antes'   => $etiquetaAntes,
                'valor_despues' => $etiquetaDespues,
            ];
        }

        $movimiento          = obtenerMovimientoSesion('editar', $cambios);
        $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeQuery($conectar, "
            UPDATE molde SET
                nombre       = :nombre,
                forma        = :forma,
                js_producto  = :js_producto,
                update_at    = NOW(),
                js_session   = :js_session,
                js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id
        ", [
            'nombre'       => $nombre,
            'forma'        => $forma,
            'js_producto'  => $jsProductoJson,
            'id'           => $id,
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
        ]);
        responder(true, 'Molde actualizado correctamente.', ['id' => $id, 'modo' => 'editar']);
    }
}

// Soft delete: se marca deleted_at, no se borra físicamente.
function eliminarMolde()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at FROM molde WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Molde no encontrado.');
    if (!empty($existe[0]['deleted_at'])) {
        responder(false, 'Este molde ya estaba inactivo.');
    }

    // No permitir desactivar un molde que está puesto ahora mismo en una máquina
    $enUso = executeQuery(
        $conectar,
        "SELECT maquina_id FROM maquina_moldes WHERE molde_id = :id AND fecha_fin IS NULL",
        ['id' => $id]
    );
    if (!empty($enUso)) {
        responder(false, 'No puedes desactivar este molde: actualmente está puesto en una máquina.');
    }

    $cambios = [[
        'campo'         => 'Estado',
        'valor_antes'   => 'Activo',
        'valor_despues' => 'Inactivo',
    ]];

    $movimiento          = obtenerMovimientoSesion('desactivar', $cambios);
    $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeQuery(
        $conectar,
        "UPDATE molde SET
            deleted_at   = NOW(),
            update_at    = NOW(),
            js_session   = :js_session,
            js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id",
        [
            'id'           => $id,
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
        ]
    );
    responder(true, 'Molde desactivado correctamente.');
}

function reactivarMolde()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $cambios = [[
        'campo'         => 'Estado',
        'valor_antes'   => 'Inactivo',
        'valor_despues' => 'Activo',
    ]];

    $movimiento          = obtenerMovimientoSesion('reactivar', $cambios);
    $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
    $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

    executeQuery(
        $conectar,
        "UPDATE molde SET
            deleted_at   = NULL,
            update_at    = NOW(),
            js_session   = :js_session,
            js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
        WHERE id = :id",
        [
            'id'           => $id,
            'js_session'   => $js_session,
            'js_historial' => $js_historial_nuevo,
        ]
    );
    responder(true, 'Molde reactivado correctamente.');
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