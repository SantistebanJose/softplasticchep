<?php
require __DIR__ . '/controllers/bd.php';
require_once __DIR__ . '/controllers/clssAyudaVideo.php';

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (!empty($_SESSION['operario_id'])) {
    $rol = strtolower(trim((string)($_SESSION['operario_rol'] ?? 'operario')));
    if ($rol !== 'conductor') $rol = 'operario';
} elseif (!empty($_SESSION['usuario_id'])) {
    $rol = strtolower(trim((string)($_SESSION['rol_usuario'] ?? '')));
} else {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Inicia sesión para ver los videos.']);
    exit;
}

if (!in_array($rol, ['conductor', 'operario', 'administrador'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Tu rol no tiene acceso a los videos de ayuda.']);
    exit;
}

try {
    $controlador = new ClssAyudaVideo(conectar_oll_BD());
    echo json_encode(['ok' => true, 'videos' => $controlador->listarActivosPorRol($rol)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Error cargando videos de ayuda: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'No se pudieron cargar los videos de ayuda.']);
}
