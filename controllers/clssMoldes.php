<?php

/**
 * controllers/clssMoldes.php
 * Controlador del módulo de Moldes
 * Tabla real: molde (id, nombre, forma, producto_id, js_session, js_historial, created_at, update_at, deleted_at)
 * producto_id: FK obligatoria a producto (mercadería que produce este molde).
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
        $where[] = "(LOWER(m.nombre) LIKE LOWER(:texto) OR LOWER(m.forma) LIKE LOWER(:texto) OR LOWER(p.codigo) LIKE LOWER(:texto) OR LOWER(p.descripcion) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }
    if ($estado === 'activa') {
        $where[] = "m.deleted_at IS NULL";
    } elseif ($estado === 'inactiva') {
        $where[] = "m.deleted_at IS NOT NULL";
    }

    $sql = "
        SELECT m.*,
               p.codigo AS producto_codigo,
               p.descripcion AS producto_descripcion
        FROM molde m
        LEFT JOIN producto p ON p.id = m.producto_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY m.nombre
    ";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['moldes' => $result]);
}

function obtenerMolde($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $result = executeQuery(
        $conectar,
        "SELECT m.*,
                p.codigo AS producto_codigo,
                p.descripcion AS producto_descripcion
         FROM molde m
         LEFT JOIN producto p ON p.id = m.producto_id
         WHERE m.id = :id",
        ['id' => $id]
    );
    if (empty($result)) responder(false, 'Molde no encontrado.');
    responder(true, 'OK', ['molde' => $result[0]]);
}

/**
 * Obtiene la IP real del cliente, considerando proxies/balanceadores comunes.
 */
function obtenerIpCliente(): string
{
    // Si el servidor está detrás de un proxy (Cloudflare, Nginx, etc.)
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Puede venir una lista "ip_cliente, ip_proxy1, ip_proxy2" — tomamos la primera
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
 * $cambios: arreglo de ['campo' => .., 'valor_antes' => .., 'valor_despues' => ..]
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
 * Compara un registro anterior (array asociativo de la BD) contra los datos nuevos
 * y devuelve solo los campos cuyo valor cambió, mapeados con etiqueta legible.
 *
 * $mapaCampos: ['columna_bd' => 'Etiqueta bonita']
 * $anterior:   registro actual tal cual viene de la BD (o [] si es creación)
 * $nuevo:      ['columna_bd' => valor_nuevo]
 */
function compararCambios(array $anterior, array $nuevo, array $mapaCampos): array
{
    $cambios = [];
    foreach ($mapaCampos as $campo => $etiqueta) {
        $valorAntes   = $anterior[$campo] ?? null;
        $valorDespues = $nuevo[$campo]    ?? null;

        // Normalizamos vacíos para comparar de forma justa (null vs '' se tratan igual)
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
 * Devuelve "CODIGO - DESCRIPCION" de un producto, o null si no existe.
 * Se usa solo para dejar el historial legible (en vez de guardar el id pelado).
 */
function etiquetaProducto($conectar, ?int $productoId): ?string
{
    if (!$productoId) return null;
    $result = executeQuery(
        $conectar,
        "SELECT codigo, descripcion FROM producto WHERE id = :id",
        ['id' => $productoId]
    );
    if (empty($result)) return null;
    return $result[0]['codigo'] . ' - ' . $result[0]['descripcion'];
}

function guardarMolde()
{
    $conectar = conectar_oll_BD();
    $id          = intval($_POST['id'] ?? 0);
    $nombre      = trim($_POST['nombre'] ?? '');
    $forma       = trim($_POST['forma'] ?? '');
    $producto_id = intval($_POST['producto_id'] ?? 0);

    // ── Validaciones ──────────────────────────────────────────────────────────
    if (empty($nombre))    responder(false, 'El nombre es obligatorio.');
    if (empty($forma))     responder(false, 'La forma es obligatoria.');
    if ($producto_id <= 0) responder(false, 'Selecciona el producto asociado.');

    // El producto debe existir
    $prodExiste = executeQuery($conectar, "SELECT id FROM producto WHERE id = :id", ['id' => $producto_id]);
    if (empty($prodExiste)) responder(false, 'El producto seleccionado no existe.');

    // Nombre único (excluyendo el propio registro si es edición)
    $chk = executeQuery(
        $conectar,
        "SELECT id FROM molde WHERE LOWER(nombre) = LOWER(:nombre) AND id <> :id",
        ['nombre' => $nombre, 'id' => $id]
    );
    if (!empty($chk)) responder(false, 'Ya existe un molde con ese nombre.');

    // Mapa de campos editables → etiqueta legible para el historial
    $mapaCampos = [
        'nombre'      => 'Nombre',
        'forma'       => 'Forma',
        'producto_id' => 'Producto',
    ];

    $datosNuevos = [
        'nombre'      => $nombre,
        'forma'       => $forma,
        'producto_id' => $producto_id,
    ];

    if ($id === 0) {
        // Creación: "antes" está vacío para todos los campos
        $cambios = compararCambios([], $datosNuevos, $mapaCampos);

        // Cambiamos el valor "después" del producto de id numérico a texto legible
        foreach ($cambios as &$c) {
            if ($c['campo'] === 'Producto') {
                $c['valor_despues'] = etiquetaProducto($conectar, $producto_id) ?? $c['valor_despues'];
            }
        }
        unset($c);

        $movimiento          = obtenerMovimientoSesion('crear', $cambios);
        $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        $result = executeQuery($conectar, "
            INSERT INTO molde (nombre, forma, producto_id, created_at, js_session, js_historial)
            VALUES (:nombre, :forma, :producto_id, NOW(), :js_session, :js_historial)
            RETURNING id
        ", [
            'nombre'       => $datosNuevos['nombre'],
            'forma'        => $datosNuevos['forma'],
            'producto_id'  => $datosNuevos['producto_id'],
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

        // Cambiamos los valores de producto (antes/después) de id numérico a texto legible
        foreach ($cambios as &$c) {
            if ($c['campo'] === 'Producto') {
                $idAntes = $registroAnterior['producto_id'] ?? null;
                $c['valor_antes']   = etiquetaProducto($conectar, $idAntes ? intval($idAntes) : null) ?? $c['valor_antes'];
                $c['valor_despues'] = etiquetaProducto($conectar, $producto_id) ?? $c['valor_despues'];
            }
        }
        unset($c);

        $movimiento          = obtenerMovimientoSesion('editar', $cambios);
        $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeQuery($conectar, "
            UPDATE molde SET
                nombre       = :nombre,
                forma        = :forma,
                producto_id  = :producto_id,
                update_at    = NOW(),
                js_session   = :js_session,
                js_historial = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id
        ", [
            'nombre'       => $datosNuevos['nombre'],
            'forma'        => $datosNuevos['forma'],
            'producto_id'  => $datosNuevos['producto_id'],
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