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
    $lectura = ['LISTARALMACENES', 'OBTENERALMACEN'];
    $escritura = ['GUARDARALMACEN', 'ELIMINARALMACEN', 'REACTIVARALMACEN'];
    $usuario = exigirSesion();

    if (in_array($accion, $lectura, true)) {
        exigirRol($usuario, ['administrador', 'produccion', 'consulta']);
    } elseif (in_array($accion, $escritura, true)) {
        exigirRol($usuario, ['administrador']);
        bloquearReenvioDuplicado($accion);
    } else {
        responderAcceso(400, 'Acción no reconocida.');
    }

    switch ($accion) {
        case 'LISTARALMACENES':
            listarAlmacenes();
            break;
        case 'OBTENERALMACEN':
            obtenerAlmacen((int) ($_POST['id'] ?? 0));
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
