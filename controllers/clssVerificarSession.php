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

function bloquearOperarioEnSistemaWeb(): void
{
    if (!empty($_SESSION['usuario_id'])
        && strtolower(trim((string)($_SESSION['rol_usuario'] ?? ''))) === 'operario') {
        http_response_code(403);
        $aceptaHtml = str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'text/html')
            && strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET';
        if ($aceptaHtml) {
            echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
                . '<title>Acceso de Operario</title><body style="font-family:Arial,sans-serif;background:#f3f5f9;display:grid;place-items:center;min-height:100vh;margin:0;color:#17233a">'
                . '<main style="background:white;padding:32px;border-radius:16px;max-width:480px;text-align:center;box-shadow:0 8px 30px #14213d18">'
                . '<h1 style="font-size:1.4rem">Acceso no disponible</h1><p>Las cuentas de Operario deben ingresar desde el acceso de operarios.</p>'
                . '<a href="tablet/loginoperarios.php">Ir al acceso de operarios</a><br><br><a href="logout.php">Cerrar esta sesión</a>'
                . '</main></body></html>';
            exit;
        }
        responderAcceso(403, 'Las cuentas de Operario deben ingresar desde el acceso de operarios.');
    }
}

function exigirSesion(): array
{
    iniciarSesionSegura();

    if (empty($_SESSION['usuario_id'])) {
        responderAcceso(401, 'Debes iniciar sesión para acceder a este recurso.');
    }

    bloquearOperarioEnSistemaWeb();

    return [
        'id'       => (int) $_SESSION['usuario_id'],
        'nombre'   => (string) ($_SESSION['nombre_usuario'] ?? ''),
        'usuario'  => (string) ($_SESSION['user_usuario'] ?? ''),
        'rol'      => (string) ($_SESSION['rol_usuario'] ?? ''),
        'roles_permitidos' => is_array($_SESSION['roles_permitidos'] ?? null) ? $_SESSION['roles_permitidos'] : [],
        'restricciones_rol' => is_array($_SESSION['restricciones_rol'] ?? null) ? $_SESSION['restricciones_rol'] : [],
        'perfiles' => $_SESSION['perfiles'] ?? [],
    ];
}

function usuarioTieneRol(array $usuario, string $rol, string $modulo = ''): bool
{
    $rolUsuario = strtoupper(trim((string) $usuario['rol']));
    $rolesEfectivos = array_map(
        fn($rol) => strtoupper(trim((string) $rol)),
        (array)($usuario['roles_permitidos'] ?? [])
    );
    $modulosRestringidos = array_map('strtolower', (array)($usuario['restricciones_rol'] ?? []));
    $rolSolicitado = strtoupper(trim($rol));
    if ($rolUsuario === 'ADMINISTRADOR') return $rolSolicitado === 'ADMINISTRADOR' || $rolSolicitado !== '';
    if ($modulo !== '' && in_array(strtolower($modulo), $modulosRestringidos, true)) {
        return false;
    }
    return $rolUsuario === $rolSolicitado || in_array($rolSolicitado, $rolesEfectivos, true);
}

function exigirRol(array $usuario, array $rolesPermitidos, string $modulo = ''): void
{
    foreach ($rolesPermitidos as $rol) {
        if (usuarioTieneRol($usuario, (string)$rol, $modulo)) return;
    }
    responderAcceso(403, 'No tienes permiso para realizar esta acción.');
}
