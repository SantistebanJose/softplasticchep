<?php

/**
 * controllers/clssMaterial.php
 * Controlador del módulo de Materia Prima
 * Tabla real: material (id, nombre, unidad_medida_id, stock_minimo, stock_actual,
 *             derivado, js_session, js_historial, created_at, update_at, deleted_at)
 * unidad_medida_id es OPCIONAL y puede ser CUALQUIER unidad activa de
 * unidad_medida — una raíz (ej: Gramo) o una compuesta ya creada (ej:
 * Kilogramo, Saco 25kg). El stock del material se maneja siempre en la
 * unidad que tenga asignada, tal cual, sin forzarla a la raíz de su familia.
 * Al registrar una compra en otra unidad de la misma familia, la conversión
 * hacia esta unidad debe hacerse de forma RELATIVA usando
 * unidad_medida.equivalencia (que ya guarda "cuánto equivale 1 unidad de
 * esta hacia la raíz real de su familia": 1 para las raíces, un factor para
 * las compuestas):
 *
 *     cantidad_en_unidad_material = cantidad_comprada
 *         * (equivalencia_unidad_compra / equivalencia_unidad_material)
 *
 * `derivado` (boolean, default false) distingue:
 *   - false => material COMPUESTO (materia prima que se compra a proveedores)
 *   - true  => material DERIVADO (sale como subproducto/derivado de un proceso
 *              interno, ej. "CLICK DE GANCHO", y no se compra directamente)
 * `color` (boolean, default false) marca si este material es un TINTE.
 * `rgb` (text, nullable) guarda el color real del tinte (HEX/RGB), usado
 *   tanto para pintar su card en Producción como para sincronizar el
 *   registro correspondiente en la tabla `color` (ver asegurarColorParaTinte()).
 * Soft delete vía deleted_at.
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

if (isset($_POST["accion"])) {
    controladorMaterial($_POST["accion"]);
}

function controladorMaterial($accion)
{
    switch ($accion) {
        case 'LISTARMATERIALES':
            listarMateriales();
            break;
        case 'BUSCARPRODUCTOS':
            buscarProductosMaterial();
            break;
        case 'OBTENERMATERIAL':
            obtenerMaterial(intval($_POST['id'] ?? 0));
            break;
        case 'GUARDARMATERIAL':
            guardarMaterial();
            break;
        case 'ELIMINARMATERIAL':
            eliminarMaterial();
            break;
        case 'REACTIVARMATERIAL':
            reactivarMaterial();
            break;
        default:
            responder(false, 'Acción no reconocida: ' . htmlspecialchars($accion));
    }
}

// =============================================================================
// MATERIAL
// =============================================================================

function listarMateriales()
{
    $conectar = conectar_oll_BD();

    $texto  = trim($_POST['texto'] ?? '');
    $estado = trim($_POST['estado'] ?? ''); // '', 'activa', 'inactiva'
    $tipo   = trim($_POST['tipo'] ?? '');   // '', 'derivado', 'compuesto'

    $where  = ["1=1"];
    $params = [];

    if ($texto !== '') {
        $where[] = "LOWER(m.nombre) LIKE LOWER(:texto)";
        $params['texto'] = "%$texto%";
    }
    if ($estado === 'activa') {
        $where[] = "m.deleted_at IS NULL";
    } elseif ($estado === 'inactiva') {
        $where[] = "m.deleted_at IS NOT NULL";
    }
    if ($tipo === 'derivado') {
        $where[] = "m.derivado IS TRUE";
    } elseif ($tipo === 'compuesto') {
        $where[] = "COALESCE(m.derivado, FALSE) IS FALSE";
    }

    $sql = "
        SELECT
            m.*,
            u.nombre       AS unidad_nombre,
            u.nombre_corto AS unidad_corto,
            CASE
                WHEN m.derivado IS TRUE THEN 'DERIVADO'
                ELSE 'COMPUESTO'
            END AS derivado_formato
        FROM material m
        LEFT JOIN unidad_medida u ON u.id = m.unidad_medida_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY m.nombre
    ";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['materiales' => $result]);
}

function obtenerMaterial($id)
{
    $conectar = conectar_oll_BD();
    if (!$id) responder(false, 'ID inválido.');

    $result = executeQuery(
        $conectar,
        "SELECT * FROM material WHERE id = :id",
        ['id' => $id]
    );
    if (empty($result)) responder(false, 'Material no encontrado.');
    responder(true, 'OK', ['material' => $result[0]]);
}

function buscarProductosMaterial()
{
    $conectar = conectar_oll_BD();
    $texto = trim($_POST['texto'] ?? '');

    $where  = ["activo = true"];
    $params = [];
    if ($texto !== '') {
        $where[] = "(LOWER(codigo) LIKE LOWER(:texto) OR LOWER(descripcion) LIKE LOWER(:texto))";
        $params['texto'] = "%$texto%";
    }

    $sql = "SELECT id, codigo, descripcion FROM producto
            WHERE " . implode(' AND ', $where) . " ORDER BY descripcion LIMIT 200";

    $result = executeQuery($conectar, $sql, $params);
    responder(true, 'OK', ['productos' => $result]);
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
 * Traduce un id de unidad_medida a su nombre legible, para que el historial
 * no quede lleno de números sueltos.
 */
function obtenerNombreUnidad($conectar, $unidadMedidaId): string
{
    if (empty($unidadMedidaId)) return 'Sin unidad de medida';

    $result = executeQuery(
        $conectar,
        "SELECT nombre, nombre_corto FROM unidad_medida WHERE id = :id",
        ['id' => $unidadMedidaId]
    );
    if (empty($result)) return "Unidad #$unidadMedidaId (no encontrada)";

    return $result[0]['nombre'] . ' (' . $result[0]['nombre_corto'] . ')';
}

/**
 * Convierte cualquier representación de checkbox/select ('on','1','true',etc.)
 * a un boolean real de PHP.
 */
function obtenerDerivadoPost(): bool
{
    return filter_var($_POST['derivado'] ?? false, FILTER_VALIDATE_BOOLEAN);
}

function obtenerColorPost(): bool
{
    return filter_var($_POST['color'] ?? false, FILTER_VALIDATE_BOOLEAN);
}

function asegurarColorParaTinte($conectar, bool $esTinte, string $nombre, ?string $rgb): void
{
    if (!$esTinte) {
        return;
    }

    $existente = executeQuery(
        $conectar,
        "SELECT id FROM color WHERE nombre ILIKE :nombre",
        ['nombre' => $nombre]
    );

    if (!empty($existente)) {
        executeNonQuery(
            $conectar,
            "UPDATE color SET rgb = :rgb, update_at = NOW() WHERE id = :id",
            ['rgb' => $rgb ?: null, 'id' => $existente[0]['id']]
        );
        return;
    }

    executeNonQuery($conectar, "
        INSERT INTO color (nombre, rgb, created_at)
        VALUES (:nombre, :rgb, NOW())
    ", ['nombre' => $nombre, 'rgb' => $rgb ?: null]);
}

function guardarMaterial()
{
    $conectar    = conectar_oll_BD();
    $id          = intval($_POST['id'] ?? 0);
    $nombre      = trim($_POST['nombre'] ?? '');
    $nombre      = mb_strtoupper($nombre, 'UTF-8'); // NUEVO: normaliza a mayúsculas, respalda al frontend
    $rgb   = trim($_POST['rgb'] ?? '');
    $color = obtenerColorPost();
    $colorNombre = trim($_POST['color_nombre'] ?? '');
    if ($colorNombre === '') {
        $colorNombre = $nombre; // fallback si no mandan nombre de color explícito
    }
    $colorNombre = mb_strtoupper($colorNombre, 'UTF-8');
    $stockMinimo = $_POST['stock_minimo'] !== '' ? floatval($_POST['stock_minimo'] ?? 0) : 0;
    $stockActual = $_POST['stock_actual'] !== '' ? floatval($_POST['stock_actual'] ?? 0) : 0;
    $derivado    = obtenerDerivadoPost();
    $productosIdsJson = trim($_POST['productos_ids'] ?? '[]');
    $productosIds = json_decode($productosIdsJson, true);
    if (!is_array($productosIds)) $productosIds = [];
    $productosIds = array_values(array_unique(array_map('intval', $productosIds)));

    $jsProducto = [];
    if (!empty($productosIds)) {
        $placeholders = [];
        $paramsProd = [];
        foreach ($productosIds as $i => $pid) {
            $key = "pid$i";
            $placeholders[] = ":$key";
            $paramsProd[$key] = $pid;
        }
        $sqlProd = "SELECT id, codigo, descripcion FROM producto WHERE id IN (" . implode(',', $placeholders) . ")";
        $filas = executeQuery($conectar, $sqlProd, $paramsProd);
        foreach ($filas as $f) {
            $jsProducto[] = [
                'codigo'      => $f['codigo'],
                'descripcion' => $f['descripcion'],
                'producto_id' => (int)$f['id'],
            ];
        }
    }
    $jsProductoJson = json_encode($jsProducto, JSON_UNESCAPED_UNICODE);

    // La unidad de medida es OPCIONAL: si no viene o viene vacía, queda en NULL.
    $unidadMedidaId = !empty($_POST['unidad_medida_id']) ? intval($_POST['unidad_medida_id']) : null;

    // ── Validaciones ──────────────────────────────────────────────────────────
    if (empty($nombre))    responder(false, 'El nombre es obligatorio.');
    if ($stockMinimo < 0)  responder(false, 'El stock mínimo no puede ser negativo.');
    if ($stockActual < 0)  responder(false, 'El stock actual no puede ser negativo.');

    // Si se envió una unidad de medida, solo validamos que exista y esté
    // activa. Puede ser una raíz (Gramo) o una compuesta (Kilogramo, Saco
    // 25kg) — el stock del material se maneja en la unidad que sea, y la
    // conversión al comprar en otra unidad de la misma familia se hace de
    // forma relativa usando unidad_medida.equivalencia (ver docblock arriba).
    if ($unidadMedidaId !== null) {
        $unidad = executeQuery(
            $conectar,
            "SELECT id FROM unidad_medida WHERE id = :id AND deleted_at IS NULL",
            ['id' => $unidadMedidaId]
        );
        if (empty($unidad)) responder(false, 'La unidad de medida seleccionada no existe o está inactiva.');
    }

    // Nombre único (excluyendo el propio registro si es edición)
    $chk = executeQuery(
        $conectar,
        "SELECT id FROM material WHERE LOWER(nombre) = LOWER(:nombre) AND id <> :id",
        ['nombre' => $nombre, 'id' => $id]
    );
    if (!empty($chk)) responder(false, 'Ya existe un material con ese nombre.');

    $mapaCampos = [
        'nombre'          => 'Nombre',
        'nombre_unidad'   => 'Unidad de medida',
        'stock_minimo'    => 'Stock mínimo',
        'stock_actual'    => 'Stock actual',
        'derivado_texto'  => 'Tipo',
        'color_texto'     => 'Es tinte',
    ];

    $datosNuevos = [
        'nombre'         => $nombre,
        'nombre_unidad'  => obtenerNombreUnidad($conectar, $unidadMedidaId),
        'stock_minimo'   => $stockMinimo,
        'stock_actual'   => $stockActual,
        'derivado_texto' => $derivado ? 'DERIVADO' : 'COMPUESTO',
        'color_texto'    => $color ? 'Sí' : 'No',
    ];
    asegurarColorParaTinte($conectar, $color, $colorNombre, $rgb);

    if ($id === 0) {
        $cambios = compararCambios([], $datosNuevos, $mapaCampos);

        $movimiento          = obtenerMovimientoSesion('crear', $cambios);
        $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        $result = executeQuery($conectar, "
            
            INSERT INTO material (nombre, unidad_medida_id, stock_minimo, stock_actual, derivado, color, rgb, js_producto, created_at, js_session, js_historial)
            VALUES (:nombre, :unidad_medida_id, :stock_minimo, :stock_actual, :derivado, :color, :rgb, :js_producto, NOW(), :js_session, :js_historial)
            RETURNING id
        ", [
            'nombre'           => $nombre,
            'unidad_medida_id' => $unidadMedidaId,
            'stock_minimo'     => $stockMinimo,
            'stock_actual'     => $stockActual,
            'derivado'         => $derivado ? 'true' : 'false',
            'color'            => $color ? 'true' : 'false',
            'rgb'              => $rgb !== '' ? $rgb : null,
            'js_session'       => $js_session,
            'js_historial'     => $js_historial_nuevo,
            'js_producto'      => $jsProductoJson,
        ]);
        $nuevo_id = $result[0]['id'] ?? null;
        responder(true, 'Material creado correctamente.', ['id' => $nuevo_id, 'modo' => 'crear']);
    } else {
        $actual = executeQuery($conectar, "SELECT * FROM material WHERE id = :id", ['id' => $id]);
        if (empty($actual)) responder(false, 'Material no encontrado.');
        $registroAnterior = $actual[0];
        $registroAnterior['nombre_unidad']  = obtenerNombreUnidad($conectar, $registroAnterior['unidad_medida_id']);
        $registroAnterior['derivado_texto'] = filter_var($registroAnterior['derivado'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'DERIVADO' : 'COMPUESTO';
        $registroAnterior['color_texto']    = filter_var($registroAnterior['color'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'Sí' : 'No';

        $cambios = compararCambios($registroAnterior, $datosNuevos, $mapaCampos);

        $movimiento          = obtenerMovimientoSesion('editar', $cambios);
        $js_session          = json_encode($movimiento, JSON_UNESCAPED_UNICODE);
        $js_historial_nuevo  = json_encode([$movimiento], JSON_UNESCAPED_UNICODE);

        executeQuery($conectar, "
            
            UPDATE material SET
                nombre           = :nombre,
                unidad_medida_id = :unidad_medida_id,
                stock_minimo     = :stock_minimo,
                stock_actual     = :stock_actual,
                derivado         = :derivado,
                color            = :color,
                rgb              = :rgb,
                js_producto      = :js_producto,
                update_at        = NOW(),
                js_session       = :js_session,
                js_historial     = COALESCE(js_historial, '[]'::jsonb) || :js_historial::jsonb
            WHERE id = :id
        ", [
            'nombre'           => $nombre,
            'unidad_medida_id' => $unidadMedidaId,
            'stock_minimo'     => $stockMinimo,
            'stock_actual'     => $stockActual,
            'derivado'         => $derivado ? 'true' : 'false',
            'color'            => $color ? 'true' : 'false',
            'rgb'              => $rgb !== '' ? $rgb : null,
            'id'               => $id,
            'js_session'       => $js_session,
            'js_historial'     => $js_historial_nuevo,
            'js_producto'      => $jsProductoJson,
        ]);
        responder(true, 'Material actualizado correctamente.', ['id' => $id, 'modo' => 'editar']);
    }
}

// Soft delete: se marca deleted_at, no se borra físicamente.
function eliminarMaterial()
{
    $conectar = conectar_oll_BD();
    $id       = intval($_POST['id'] ?? 0);
    if (!$id) responder(false, 'ID inválido.');

    $existe = executeQuery($conectar, "SELECT id, deleted_at FROM material WHERE id = :id", ['id' => $id]);
    if (empty($existe)) responder(false, 'Material no encontrado.');
    if (!empty($existe[0]['deleted_at'])) {
        responder(false, 'Este material ya estaba inactivo.');
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
        "UPDATE material SET
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
    responder(true, 'Material desactivado correctamente.');
}

function reactivarMaterial()
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
        "UPDATE material SET
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
    responder(true, 'Material reactivado correctamente.');
}

function responder(bool $ok, string $msg, array $extra = []): void
{
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}