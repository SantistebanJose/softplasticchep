<?php
require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
require_once __DIR__ . '/clssVerificarSession.php';

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '0');

if (isset($_POST['accion'])) {
    try {
        controladorBanco((string) $_POST['accion']);
    } catch (PDOException $e) {
        error_log('Error de base de datos en clssBanco.php: ' . $e->getMessage());
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
        if ($sqlState === '42501') {
            responder(false, 'El usuario de la aplicación no tiene permisos sobre public.banco. Ejecuta sql/grant_banco_usrwebapp.sql en PostgreSQL.');
        }
        if ($sqlState === '42P01') {
            responder(false, 'No se encontró public.banco. Verifica que la tabla esté creada en la base de datos de la aplicación.');
        }
        responder(false, 'Error de base de datos.');
    } catch (Throwable $e) {
        error_log('Error inesperado en clssBanco.php: ' . $e->getMessage());
        responder(false, 'Error inesperado en el servidor.');
    }
}

function controladorBanco(string $accion): void
{
    $usuario = exigirSesion();
    $lectura = ['LISTARBANCOS', 'OBTENERBANCO'];
    $escritura = ['GUARDARBANCO', 'DESACTIVARBANCO', 'REACTIVARBANCO'];
    if (in_array($accion, $lectura, true)) {
        exigirRol($usuario, ['administrador', 'personal'], 'personal');
    } elseif (in_array($accion, $escritura, true)) {
        exigirRol($usuario, ['administrador', 'personal'], 'personal');
    } else {
        responderAcceso(400, 'Acción no reconocida.');
    }

    switch ($accion) {
        case 'LISTARBANCOS': listarBancos(); break;
        case 'OBTENERBANCO': obtenerBanco((string) ($_POST['nombre'] ?? '')); break;
        case 'GUARDARBANCO': guardarBanco(); break;
        case 'DESACTIVARBANCO': cambiarEstadoBanco((string) ($_POST['nombre'] ?? ''), false); break;
        case 'REACTIVARBANCO': cambiarEstadoBanco((string) ($_POST['nombre'] ?? ''), true); break;
    }
}

function listarBancos(): void
{
    $conectar = conectar_oll_BD();
    $texto = trim($_POST['texto'] ?? '');
    $estado = trim($_POST['estado'] ?? 'activos');
    $where = ['1=1'];
    $params = [];
    if ($texto !== '') {
        $where[] = "(nombre ILIKE :texto OR COALESCE(descripcion, '') ILIKE :texto)";
        $params['texto'] = '%' . $texto . '%';
    }
    if ($estado === 'activos') $where[] = 'deleted_at IS NULL';
    elseif ($estado === 'inactivos') $where[] = 'deleted_at IS NOT NULL';
    $bancos = executeQuery($conectar, 'SELECT nombre, descripcion, created_at, update_at, deleted_at FROM public.banco WHERE ' . implode(' AND ', $where) . ' ORDER BY nombre', $params);
    responder(true, 'OK', ['bancos' => $bancos]);
}

function obtenerBanco(string $nombre): void
{
    $nombre = trim($nombre);
    if ($nombre === '') responder(false, 'Selecciona un banco válido.');
    $conectar = conectar_oll_BD();
    $banco = executeQuery($conectar, 'SELECT nombre, descripcion, deleted_at FROM public.banco WHERE nombre = :nombre', ['nombre' => $nombre]);
    if (!$banco) responder(false, 'Banco no encontrado.');
    responder(true, 'OK', ['banco' => $banco[0]]);
}

function guardarBanco(): void
{
    $conectar = conectar_oll_BD();
    $nombreOriginal = trim($_POST['nombre_original'] ?? '');
    $nombre = mb_strtoupper(trim($_POST['nombre'] ?? ''), 'UTF-8');
    $descripcion = trim($_POST['descripcion'] ?? '');
    if ($nombre === '') responder(false, 'El nombre del banco es obligatorio.');
    if (mb_strlen($nombre, 'UTF-8') > 20) responder(false, 'El nombre admite hasta 20 caracteres.');
    $duplicado = executeQuery($conectar, 'SELECT nombre FROM public.banco WHERE LOWER(nombre) = LOWER(:nombre) AND nombre <> :original LIMIT 1', [
        'nombre' => $nombre,
        'original' => $nombreOriginal,
    ]);
    if ($duplicado) responder(false, 'Ya existe un banco con ese nombre.');

    if ($nombreOriginal === '') {
        executeNonQuery($conectar, 'INSERT INTO public.banco (nombre, descripcion) VALUES (:nombre, :descripcion)', [
            'nombre' => $nombre,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
        ]);
        responder(true, 'Banco creado correctamente.');
    }

    $actual = executeQuery($conectar, 'SELECT nombre, deleted_at FROM public.banco WHERE nombre = :nombre', ['nombre' => $nombreOriginal]);
    if (!$actual) responder(false, 'Banco no encontrado.');
    if ($actual[0]['deleted_at'] !== null) responder(false, 'Reactiva el banco antes de editarlo.');

    $conectar->beginTransaction();
    try {
        executeNonQuery($conectar, 'UPDATE public.banco SET nombre = :nombre, descripcion = :descripcion, update_at = NOW() WHERE nombre = :original', [
            'nombre' => $nombre,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
            'original' => $nombreOriginal,
        ]);
        if ($nombre !== $nombreOriginal) {
            executeNonQuery($conectar, "UPDATE public.operario SET banco = jsonb_set(banco, '{nombre}', to_jsonb(CAST(:nombre AS text)), false) WHERE banco->>'nombre' = :original", [
                'nombre' => $nombre,
                'original' => $nombreOriginal,
            ]);
        }
        $conectar->commit();
    } catch (Throwable $e) {
        if ($conectar->inTransaction()) $conectar->rollBack();
        throw $e;
    }
    responder(true, 'Banco actualizado correctamente.');
}

function cambiarEstadoBanco(string $nombre, bool $activar): void
{
    $nombre = trim($nombre);
    if ($nombre === '') responder(false, 'Banco inválido.');
    $conectar = conectar_oll_BD();
    $actual = executeQuery($conectar, 'SELECT nombre, deleted_at FROM public.banco WHERE nombre = :nombre', ['nombre' => $nombre]);
    if (!$actual) responder(false, 'Banco no encontrado.');
    if ($activar && $actual[0]['deleted_at'] === null) responder(false, 'Este banco ya está activo.');
    if (!$activar && $actual[0]['deleted_at'] !== null) responder(false, 'Este banco ya está inactivo.');
    executeNonQuery($conectar, 'UPDATE public.banco SET deleted_at = ' . ($activar ? 'NULL' : 'NOW()') . ', update_at = NOW() WHERE nombre = :nombre', ['nombre' => $nombre]);
    responder(true, $activar ? 'Banco reactivado correctamente.' : 'Banco desactivado correctamente.');
}

function responder(bool $success, string $message, array $extra = []): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}
