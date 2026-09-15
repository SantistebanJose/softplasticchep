<?php
// controllers/clssVerificarSession.php
declare(strict_types=1);

function iniciarSesionSegura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'path'     => '/',
        'secure'   => true, // Tu sitio usa HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    ini_set('session.use_strict_mode', '1');
    session_start();
}

function responderAcceso(int $codigo, string $mensaje): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'success' => false,
        'message' => $mensaje,
    ]);

    exit;
}

function exigirSesion(): array
{
    iniciarSesionSegura();

    if (empty($_SESSION['usuario_id'])) {
        responderAcceso(401, 'Debes iniciar sesión para acceder a este recurso.');
    }

    return [
        'id'       => (int) $_SESSION['usuario_id'],
        'nombre'   => (string) ($_SESSION['nombre_usuario'] ?? ''),
        'usuario'  => (string) ($_SESSION['user_usuario'] ?? ''),
        'rol'      => (string) ($_SESSION['rol_usuario'] ?? ''),
        'perfiles' => $_SESSION['perfiles'] ?? [],
    ];
}

function exigirRol(array $usuario, array $rolesPermitidos): void
{
    $rolUsuario = strtoupper(trim((string) $usuario['rol']));

    $rolesPermitidos = array_map(
        fn($rol) => strtoupper(trim((string) $rol)),
        $rolesPermitidos
    );

    if (!in_array($rolUsuario, $rolesPermitidos, true)) {
        responderAcceso(403, 'No tienes permiso para realizar esta acción.');
    }
}