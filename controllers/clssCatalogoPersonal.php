<?php
require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
require_once __DIR__ . '/clssVerificarSession.php';
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '0');

if (isset($_POST['accion'])) {
    try { controladorCatalogoPersonal((string) $_POST['accion']); }
    catch (PDOException $e) {
        error_log('Error de base de datos en clssCatalogoPersonal.php: ' . $e->getMessage());
        responder(false, 'Error de base de datos.');
    } catch (Throwable $e) {
        error_log('Error inesperado en clssCatalogoPersonal.php: ' . $e->getMessage());
        responder(false, 'Error inesperado en el servidor.');
    }
}

function validarTipoCatalogoPersonal(): string
{
    $tipo = trim($_POST['tipo'] ?? '');
    if (!in_array($tipo, ['modalidad', 'tipo_gestion'], true)) responder(false, 'Catálogo inválido.');
    return $tipo;
}

function columnasCatalogoPersonal(PDO $db, string $tabla): array
{
    return executeQuery($db, "SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = :tabla", ['tabla' => $tabla]);
}

function controladorCatalogoPersonal(string $accion): void
{
    $usuario = exigirSesion();
    $lectura = ['LISTARCATALOGOPERSONAL', 'OBTENERCATALOGOPERSONAL'];
    $escritura = ['GUARDARCATALOGOPERSONAL', 'DESACTIVARCATALOGOPERSONAL', 'REACTIVARCATALOGOPERSONAL'];
    if (in_array($accion, $lectura, true)) exigirRol($usuario, ['administrador', 'personal'], 'personal');
    elseif (in_array($accion, $escritura, true)) exigirRol($usuario, ['administrador', 'personal'], 'personal');
    else responderAcceso(400, 'Acción no reconocida.');

    $tipo = validarTipoCatalogoPersonal();
    switch ($accion) {
        case 'LISTARCATALOGOPERSONAL': listarCatalogoPersonal($tipo); break;
        case 'OBTENERCATALOGOPERSONAL': obtenerCatalogoPersonal($tipo); break;
        case 'GUARDARCATALOGOPERSONAL': guardarCatalogoPersonal($tipo); break;
        case 'DESACTIVARCATALOGOPERSONAL': cambiarEstadoCatalogoPersonal($tipo, false); break;
        case 'REACTIVARCATALOGOPERSONAL': cambiarEstadoCatalogoPersonal($tipo, true); break;
    }
}

function listarCatalogoPersonal(string $tipo): void
{
    $db = conectar_oll_BD();
    $cols = array_column(columnasCatalogoPersonal($db, $tipo), 'column_name');
    if (!in_array('nombre', $cols, true)) responder(false, "La tabla public.{$tipo} no tiene columna nombre.");
    $filtro = isset($_POST['texto']) ? trim($_POST['texto']) : '';
    $estado = trim($_POST['estado'] ?? 'activos');
    $where = [];
    $params = [];
    if ($filtro !== '') {
        $where[] = "(nombre ILIKE :texto" . (in_array('descripcion', $cols, true) ? " OR COALESCE(descripcion, '') ILIKE :texto" : '') . ')';
        $params['texto'] = '%' . $filtro . '%';
    }
    if (in_array('deleted_at', $cols, true)) {
        if ($estado === 'activos') $where[] = 'deleted_at IS NULL';
        elseif ($estado === 'inactivos') $where[] = 'deleted_at IS NOT NULL';
    }
    $seleccion = ['nombre'];
    foreach (['descripcion', 'created_at', 'update_at', 'deleted_at'] as $col) if (in_array($col, $cols, true)) $seleccion[] = $col;
    $sql = 'SELECT ' . implode(', ', $seleccion) . " FROM public.{$tipo}" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY nombre';
    responder(true, 'OK', ['items' => executeQuery($db, $sql, $params)]);
}

function obtenerCatalogoPersonal(string $tipo): void
{
    $nombre = trim($_POST['nombre'] ?? '');
    $db = conectar_oll_BD();
    $cols = array_column(columnasCatalogoPersonal($db, $tipo), 'column_name');
    $seleccion = ['nombre'];
    if (in_array('descripcion', $cols, true)) $seleccion[] = 'descripcion';
    if (in_array('deleted_at', $cols, true)) $seleccion[] = 'deleted_at';
    $items = executeQuery($db, 'SELECT ' . implode(', ', $seleccion) . " FROM public.{$tipo} WHERE nombre = :nombre", ['nombre' => $nombre]);
    if (!$items) responder(false, 'No se encontró la opción.');
    responder(true, 'OK', ['item' => $items[0]]);
}

function guardarCatalogoPersonal(string $tipo): void
{
    $db = conectar_oll_BD();
    $cols = array_column(columnasCatalogoPersonal($db, $tipo), 'column_name');
    $original = trim($_POST['nombre_original'] ?? '');
    $nombre = mb_strtoupper(trim($_POST['nombre'] ?? ''), 'UTF-8');
    $descripcion = trim($_POST['descripcion'] ?? '');
    if ($nombre === '') responder(false, 'El nombre es obligatorio.');
    if ($tipo === 'modalidad' && mb_strlen($nombre, 'UTF-8') > 20) responder(false, 'La modalidad admite hasta 20 caracteres.');
    $dup = executeQuery($db, "SELECT nombre FROM public.{$tipo} WHERE LOWER(nombre) = LOWER(:nombre) AND nombre <> :original LIMIT 1", ['nombre' => $nombre, 'original' => $original]);
    if ($dup) responder(false, 'Ya existe una opción con ese nombre.');
    $tieneDescripcion = in_array('descripcion', $cols, true);
    $tieneUpdate = in_array('update_at', $cols, true);

    if ($original === '') {
        $columnas = ['nombre']; $valores = [':nombre']; $params = ['nombre' => $nombre];
        if ($tieneDescripcion) { $columnas[] = 'descripcion'; $valores[] = ':descripcion'; $params['descripcion'] = $descripcion !== '' ? $descripcion : null; }
        executeNonQuery($db, 'INSERT INTO public.' . $tipo . ' (' . implode(', ', $columnas) . ') VALUES (' . implode(', ', $valores) . ')', $params);
        responder(true, 'Opción creada correctamente.');
    }

    $actual = executeQuery($db, "SELECT nombre FROM public.{$tipo} WHERE nombre = :nombre", ['nombre' => $original]);
    if (!$actual) responder(false, 'No se encontró la opción para editar.');
    $asignaciones = ['nombre = :nombre']; $params = ['nombre' => $nombre, 'original' => $original];
    if ($tieneDescripcion) { $asignaciones[] = 'descripcion = :descripcion'; $params['descripcion'] = $descripcion !== '' ? $descripcion : null; }
    if ($tieneUpdate) $asignaciones[] = 'update_at = NOW()';
    $db->beginTransaction();
    try {
        executeNonQuery($db, 'UPDATE public.' . $tipo . ' SET ' . implode(', ', $asignaciones) . ' WHERE nombre = :original', $params);
        if ($nombre !== $original) {
            if ($tipo === 'modalidad') {
                executeNonQuery($db, 'UPDATE public.operario SET modalidad = :nombre, updated_at = NOW() WHERE modalidad = :original', ['nombre' => $nombre, 'original' => $original]);
            } else {
                executeNonQuery($db, 'UPDATE public.operario SET tipo_gestion = :nombre, updated_at = NOW() WHERE tipo_gestion = :original', ['nombre' => $nombre, 'original' => $original]);
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    responder(true, 'Opción actualizada correctamente.');
}

function cambiarEstadoCatalogoPersonal(string $tipo, bool $activar): void
{
    $db = conectar_oll_BD();
    $cols = array_column(columnasCatalogoPersonal($db, $tipo), 'column_name');
    if (!in_array('deleted_at', $cols, true)) responder(false, 'Este catálogo no tiene borrado lógico configurado.');
    $nombre = trim($_POST['nombre'] ?? '');
    $actual = executeQuery($db, "SELECT deleted_at FROM public.{$tipo} WHERE nombre = :nombre", ['nombre' => $nombre]);
    if (!$actual) responder(false, 'No se encontró la opción.');
    if ($activar && $actual[0]['deleted_at'] === null) responder(false, 'Esta opción ya está activa.');
    if (!$activar && $actual[0]['deleted_at'] !== null) responder(false, 'Esta opción ya está inactiva.');
    $set = $activar ? 'deleted_at = NULL' : 'deleted_at = NOW()';
    if (in_array('update_at', $cols, true)) $set .= ', update_at = NOW()';
    executeNonQuery($db, "UPDATE public.{$tipo} SET {$set} WHERE nombre = :nombre", ['nombre' => $nombre]);
    responder(true, $activar ? 'Opción reactivada correctamente.' : 'Opción desactivada correctamente.');
}

function responder(bool $success, string $message, array $extra = []): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}
