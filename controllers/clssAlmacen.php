<?php
/** CRUD de almacenes. Tabla: almacen(id, sucursal_id, nombre, descripcion, created_at, update_at, deleted_at). */
require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
require_once __DIR__ . '/clssVerificarSession.php';

if (isset($_POST['accion'])) {
    try {
        controladorAlmacen((string) $_POST['accion']);
    } catch (PDOException $e) {
        error_log('Error de base de datos en clssAlmacen.php: ' . $e->getMessage());
        responderAlmacen(false, 'Error de base de datos.');
    } catch (Throwable $e) {
        error_log('Error inesperado en clssAlmacen.php: ' . $e->getMessage());
        responderAlmacen(false, 'Error inesperado en el servidor.');
    }
}

function controladorAlmacen(string $accion): void
{
    iniciarSesionSegura();
    $sesionOperario = empty($_SESSION['usuario_id']) && !empty($_SESSION['operario_id']);
    $lectura = ['LISTARALMACENES', 'OBTENERALMACEN', 'LISTARPAQUETESUBICADOS'];
    $escritura = ['GUARDARALMACEN', 'ELIMINARALMACEN', 'REACTIVARALMACEN'];
    $operaciones = ['REGISTRARUBICACIONQR'];
    if ($sesionOperario) {
        require_once __DIR__ . '/../controllers_tablet/clssAuthOperario.php';
        if (!operarioTieneEtapa('EMPAQUET')) responderAcceso(403, 'No tienes habilitada la etapa de Empaquetado.');
        if (!in_array($accion, ['LISTARALMACENES', 'LISTARPAQUETESUBICADOS', 'REGISTRARUBICACIONQR'], true)) {
            responderAcceso(403, 'No tienes permiso para realizar esta acción.');
        }
        if (in_array($accion, $operaciones, true)) bloquearReenvioDuplicado($accion);
    } else {
        $usuario = exigirSesion();
        if (in_array($accion, $lectura, true)) {
            exigirRol($usuario, ['administrador', 'produccion', 'consulta']);
        } elseif (in_array($accion, $escritura, true)) {
            exigirRol($usuario, ['administrador']);
            bloquearReenvioDuplicado($accion);
        } elseif (in_array($accion, $operaciones, true)) {
            exigirRol($usuario, ['administrador', 'produccion']);
            bloquearReenvioDuplicado($accion);
        } else {
            responderAcceso(400, 'Acción no reconocida.');
        }
    }

    switch ($accion) {
        case 'LISTARALMACENES':
            listarAlmacenes();
            break;
        case 'OBTENERALMACEN':
            obtenerAlmacen((int) ($_POST['id'] ?? 0));
            break;
        case 'LISTARPAQUETESUBICADOS':
            listarPaquetesUbicadosEnAlmacen();
            break;
        case 'GUARDARALMACEN':
            guardarAlmacen();
            break;
        case 'ELIMINARALMACEN':
            eliminarAlmacen();
            break;
        case 'REACTIVARALMACEN':
            reactivarAlmacen();
            break;
        case 'REGISTRARUBICACIONQR':
            registrarUbicacionPaquetePorQr();
            break;
    }
}

function listarPaquetesUbicadosEnAlmacen(): void
{
    $almacenId = (int)($_POST['almacen_id'] ?? 0);
    $params = [];
    $filtroAlmacen = '';
    if ($almacenId > 0) {
        $filtroAlmacen = ' AND ubicacion.almacen_id = :almacen_id';
        $params['almacen_id'] = $almacenId;
    }
    $filas = executeQuery(conectar_oll_BD(), "
        WITH ubicaciones AS (
            SELECT mi.empaquetado_id, mi.almacen_id, mi.unico, mi.created_at,
                   ROW_NUMBER() OVER (PARTITION BY mi.empaquetado_id ORDER BY mi.created_at DESC, mi.id DESC) AS rn
            FROM movimiento_inventario mi
            WHERE mi.es_traslado IS TRUE AND mi.tipo = 'TRASLADO'
              AND mi.empaquetado_id IS NOT NULL AND mi.almacen_id IS NOT NULL
        )
        SELECT e.id AS empaquetado_id, e.producto_id, p.codigo AS producto_codigo,
               p.descripcion AS producto_descripcion, e.cantidad_tota, um.nombre_corto AS unidad_codigo,
               u.almacen_id, a.nombre AS almacen_nombre, s.nombre AS sucursal_nombre,
               u.unico, u.created_at AS fecha_ubicacion
        FROM ubicaciones u
        JOIN empaquetado e ON e.id = u.empaquetado_id AND e.deleted_at IS NULL
        JOIN producto p ON p.id = e.producto_id
        LEFT JOIN unidad_medida um ON um.id = e.unidad_medida
        JOIN almacen a ON a.id = u.almacen_id AND a.deleted_at IS NULL
        LEFT JOIN sucursal s ON s.id = a.sucursal_id
        WHERE u.rn = 1 AND COALESCE(e.pasado_venta, FALSE) = FALSE $filtroAlmacen
        ORDER BY u.created_at DESC, e.id DESC
        LIMIT 300
    ", $params);
    responderAlmacen(true, 'OK', ['paquetes' => $filas]);
}

/** Registra la ubicación física del paquete; no modifica producto.stock. */
function registrarUbicacionPaquetePorQr(): void
{
    $codigo = strtoupper(trim((string)($_POST['codigo_qr'] ?? '')));
    $almacenId = (int)($_POST['almacen_id'] ?? 0);
    if ($almacenId <= 0) responderAlmacen(false, 'Selecciona el almacén donde quedó el paquete.');
    if (!preg_match('/^A-(\d{10})-(\d{5})-(\d{5})-(\d{10})$/', $codigo, $qr)) {
        responderAlmacen(false, 'El QR no tiene el formato de un paquete de Empaquetado.');
    }

    $db = conectar_oll_BD();
    try {
        $db->beginTransaction();
        $paquete = executeQuery($db, "
            SELECT emp.id, emp.sucursal, emp.producto_id, emp.emsamblaje_id, emp.cantidad_tota,
                   emp.js_codigo_qr_escaneado, emp.pasado_venta, emp.deleted_at,
                   p.codigo AS producto_codigo, p.descripcion AS producto_descripcion, p.stock,
                   um.nombre_corto AS unidad_codigo
            FROM empaquetado emp
            JOIN producto p ON p.id = emp.producto_id
            LEFT JOIN unidad_medida um ON um.id = emp.unidad_medida
            WHERE emp.id = :id
            FOR UPDATE OF emp
        ", ['id' => (int)$qr[1]]);
        if (!$paquete || !empty($paquete[0]['deleted_at'])) throw new RuntimeException('No se encontró un paquete activo con ese QR.');
        $p = $paquete[0];
        $codigoEsperado = sprintf('A-%010d-%05d-%05d-%010d', (int)$p['id'], (int)$p['sucursal'], (int)$p['producto_id'], (int)($p['emsamblaje_id'] ?? 0));
        if (!hash_equals($codigoEsperado, $codigo)) throw new RuntimeException('El QR no coincide con la sucursal, producto o ensamblaje del paquete.');
        if (in_array($p['pasado_venta'] ?? false, [true, 1, '1', 't', 'true'], true)) {
            throw new RuntimeException('Este paquete ya fue vendido y no se puede ubicar en almacén.');
        }

        $almacen = executeQuery($db, 'SELECT id, sucursal_id, nombre FROM almacen WHERE id = :id AND deleted_at IS NULL FOR UPDATE', ['id' => $almacenId]);
        if (!$almacen) throw new RuntimeException('El almacén seleccionado no existe o está inactivo.');
        if ((int)$almacen[0]['sucursal_id'] !== (int)$p['sucursal']) {
            throw new RuntimeException('El almacén pertenece a otra sucursal que el paquete.');
        }

        $historial = json_decode((string)($p['js_codigo_qr_escaneado'] ?? '[]'), true);
        if (!is_array($historial)) $historial = [];
        if (isset($historial['etapa'])) $historial = [$historial];
        $ultimaUbicacion = null;
        foreach ($historial as $eventoAnterior) {
            if (!is_array($eventoAnterior)) continue;
            if (($eventoAnterior['etapa'] ?? '') === 'inventario') $ultimaUbicacion = (int)($eventoAnterior['almacen_id'] ?? 0);
        }
        if ($ultimaUbicacion === $almacenId) {
            $db->commit();
            responderAlmacen(true, 'Este paquete ya está registrado en ' . $almacen[0]['nombre'] . '.', [
                'ubicacion_repetida' => true, 'producto' => trim(($p['producto_codigo'] ?? '') . ' - ' . ($p['producto_descripcion'] ?? '')),
                'cantidad' => (float)$p['cantidad_tota'], 'unidad' => $p['unidad_codigo'] ?? '',
            ]);
        }

        $evento = [
            'codigo' => $codigo, 'etapa' => 'inventario', 'empaquetado_id' => (int)$p['id'],
            'producto_id' => (int)$p['producto_id'], 'almacen_id' => $almacenId,
            'almacen_nombre' => $almacen[0]['nombre'], 'cantidad' => (float)$p['cantidad_tota'],
            'unidad' => $p['unidad_codigo'] ?? '', 'fecha' => date('c'),
            'usuario_id' => (int)($_SESSION['usuario_id'] ?? 0),
            'operario_id' => (int)($_SESSION['operario_id'] ?? 0),
        ];
        $eventoJson = json_encode($evento, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stockActual = (float)($p['stock'] ?? 0);

        // Registro logístico con saldo global neutro: el empaquetado ya aumentó
        // el stock y esta operación solo asigna el paquete a un almacén.
        executeNonQuery($db, "
            INSERT INTO movimiento_inventario
                (producto_id, empaquetado_id, almacen_id, operario_id, unico, cantidad_pase,
                 js_codigo_qr_escaneado, fecha, tipo, es_traslado, cantidad,
                 cantidad_anterior, cantidad_posterior, created_at)
            VALUES (:producto_id, :empaquetado_id, :almacen_id, :operario_id, :unico, :cantidad_pase,
                    CAST(:evento AS jsonb), CURRENT_DATE, 'TRASLADO', TRUE, 0,
                    :stock_anterior, :stock_posterior, NOW())
        ", [
            'producto_id' => (int)$p['producto_id'], 'empaquetado_id' => (int)$p['id'],
            'almacen_id' => $almacenId, 'operario_id' => !empty($_SESSION['operario_id']) ? (int)$_SESSION['operario_id'] : null,
            'unico' => substr($codigo, 2), 'cantidad_pase' => (float)$p['cantidad_tota'], 'evento' => $eventoJson,
            'stock_anterior' => $stockActual, 'stock_posterior' => $stockActual,
        ]);
        executeNonQuery($db, "
            UPDATE empaquetado
            SET js_codigo_qr_escaneado = COALESCE(js_codigo_qr_escaneado, '[]'::jsonb) || jsonb_build_array(CAST(:evento AS jsonb)),
                update_at = NOW()
            WHERE id = :id
        ", ['evento' => $eventoJson, 'id' => (int)$p['id']]);

        $db->commit();
        responderAlmacen(true, 'Ubicación registrada en ' . $almacen[0]['nombre'] . '. El stock global no cambió.', [
            'producto' => trim(($p['producto_codigo'] ?? '') . ' - ' . ($p['producto_descripcion'] ?? '')),
            'cantidad' => (float)$p['cantidad_tota'], 'unidad' => $p['unidad_codigo'] ?? '',
            'almacen' => $almacen[0]['nombre'], 'codigo_qr' => $codigo,
        ]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        if ($e instanceof RuntimeException) responderAlmacen(false, $e->getMessage());
        throw $e;
    }
}

function listarAlmacenes(): void
{
    $texto = trim((string) ($_POST['texto'] ?? ''));
    $estado = trim((string) ($_POST['estado'] ?? 'activos'));
    $sucursalId = (int) ($_POST['sucursal_id'] ?? 0);
    $where = ['1=1'];
    $params = [];

    if ($texto !== '') {
        $where[] = '(LOWER(a.nombre) LIKE LOWER(:texto) OR LOWER(COALESCE(a.descripcion, \'\')) LIKE LOWER(:texto) OR LOWER(COALESCE(s.nombre, \'\')) LIKE LOWER(:texto))';
        $params['texto'] = '%' . $texto . '%';
    }
    if ($estado === 'inactivos') {
        $where[] = 'a.deleted_at IS NOT NULL';
    } elseif ($estado !== 'todos') {
        $where[] = 'a.deleted_at IS NULL';
    }
    if ($sucursalId > 0) {
        $where[] = 'a.sucursal_id = :sucursal_id';
        $params['sucursal_id'] = $sucursalId;
    }

    $filas = executeQuery(conectar_oll_BD(), '
        SELECT a.id, a.sucursal_id, a.nombre, a.descripcion, a.created_at, a.update_at, a.deleted_at,
               s.nombre AS sucursal_nombre
        FROM almacen a
        LEFT JOIN sucursal s ON s.id = a.sucursal_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY s.nombre NULLS LAST, a.nombre, a.id
    ', $params);
    responderAlmacen(true, 'OK', ['almacenes' => $filas]);
}

function obtenerAlmacen(int $id): void
{
    if ($id <= 0) responderAlmacen(false, 'ID de almacén inválido.');
    $filas = executeQuery(conectar_oll_BD(), 'SELECT * FROM almacen WHERE id = :id', ['id' => $id]);
    if (!$filas) responderAlmacen(false, 'Almacén no encontrado.');
    responderAlmacen(true, 'OK', ['almacen' => $filas[0]]);
}

function guardarAlmacen(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    $sucursalId = (int) ($_POST['sucursal_id'] ?? 0);
    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
    if ($nombre === '') responderAlmacen(false, 'El nombre del almacén es obligatorio.');
    $longitudNombre = function_exists('mb_strlen') ? mb_strlen($nombre, 'UTF-8') : (preg_match_all('/./us', $nombre, $caracteres) ?: strlen($nombre));
    if ($longitudNombre > 50) responderAlmacen(false, 'El nombre no puede superar 50 caracteres.');
    if ($sucursalId <= 0) responderAlmacen(false, 'Selecciona una sucursal.');

    $conectar = conectar_oll_BD();
    $sucursal = executeQuery($conectar, 'SELECT id FROM sucursal WHERE id = :id AND delete_at IS NULL', ['id' => $sucursalId]);
    if (!$sucursal) responderAlmacen(false, 'La sucursal seleccionada no existe o está inactiva.');

    $duplicados = executeQuery($conectar, '
        SELECT id FROM almacen
        WHERE LOWER(nombre) = LOWER(:nombre)
          AND sucursal_id = :sucursal_id
          AND id <> :id
        LIMIT 1
    ', ['nombre' => $nombre, 'sucursal_id' => $sucursalId, 'id' => $id]);
    if ($duplicados) responderAlmacen(false, 'Ya existe un almacén con ese nombre en la sucursal seleccionada.');

    if ($id <= 0) {
        $nuevo = executeQuery($conectar, '
            INSERT INTO almacen (sucursal_id, nombre, descripcion, created_at)
            VALUES (:sucursal_id, :nombre, :descripcion, NOW())
            RETURNING id
        ', ['sucursal_id' => $sucursalId, 'nombre' => $nombre, 'descripcion' => $descripcion !== '' ? $descripcion : null]);
        responderAlmacen(true, 'Almacén creado correctamente.', ['id' => $nuevo[0]['id'] ?? null, 'modo' => 'crear']);
    }

    $actual = executeQuery($conectar, 'SELECT deleted_at FROM almacen WHERE id = :id', ['id' => $id]);
    if (!$actual) responderAlmacen(false, 'Almacén no encontrado.');
    if (!empty($actual[0]['deleted_at'])) responderAlmacen(false, 'No puedes editar un almacén inactivo. Reactívalo primero.');

    executeQuery($conectar, '
        UPDATE almacen
        SET sucursal_id = :sucursal_id, nombre = :nombre, descripcion = :descripcion, update_at = NOW()
        WHERE id = :id
    ', ['sucursal_id' => $sucursalId, 'nombre' => $nombre, 'descripcion' => $descripcion !== '' ? $descripcion : null, 'id' => $id]);
    responderAlmacen(true, 'Almacén actualizado correctamente.', ['id' => $id, 'modo' => 'editar']);
}

function eliminarAlmacen(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) responderAlmacen(false, 'ID de almacén inválido.');
    $conectar = conectar_oll_BD();
    $actual = executeQuery($conectar, 'SELECT deleted_at FROM almacen WHERE id = :id', ['id' => $id]);
    if (!$actual) responderAlmacen(false, 'Almacén no encontrado.');
    if (!empty($actual[0]['deleted_at'])) responderAlmacen(false, 'Este almacén ya está inactivo.');
    executeQuery($conectar, 'UPDATE almacen SET deleted_at = NOW(), update_at = NOW() WHERE id = :id', ['id' => $id]);
    responderAlmacen(true, 'Almacén desactivado correctamente.');
}

function reactivarAlmacen(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) responderAlmacen(false, 'ID de almacén inválido.');
    $conectar = conectar_oll_BD();
    $actual = executeQuery($conectar, 'SELECT id, sucursal_id, nombre, deleted_at FROM almacen WHERE id = :id', ['id' => $id]);
    if (!$actual) responderAlmacen(false, 'Almacén no encontrado.');
    if (empty($actual[0]['deleted_at'])) responderAlmacen(false, 'Este almacén ya está activo.');
    $sucursal = executeQuery($conectar, 'SELECT id FROM sucursal WHERE id = :id AND delete_at IS NULL', ['id' => $actual[0]['sucursal_id']]);
    if (!$sucursal) responderAlmacen(false, 'No puedes reactivar el almacén porque su sucursal está inactiva.');
    $duplicados = executeQuery($conectar, 'SELECT id FROM almacen WHERE id <> :id AND sucursal_id = :sucursal_id AND LOWER(nombre) = LOWER(:nombre) AND deleted_at IS NULL LIMIT 1', [
        'id' => $id,
        'sucursal_id' => $actual[0]['sucursal_id'],
        'nombre' => $actual[0]['nombre'],
    ]);
    if ($duplicados) responderAlmacen(false, 'Ya existe un almacén activo con ese nombre en la sucursal.');
    executeQuery($conectar, 'UPDATE almacen SET deleted_at = NULL, update_at = NOW() WHERE id = :id', ['id' => $id]);
    responderAlmacen(true, 'Almacén reactivado correctamente.');
}

function responderAlmacen(bool $ok, string $mensaje, array $extra = []): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $ok, 'message' => $mensaje], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}
