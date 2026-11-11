<?php
// controllers/clssVerificarSession.php
declare(strict_types=1);

function iniciarSesionSegura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // En desarrollo local suele usarse HTTP; marcar la cookie como Secure
    // en ese caso hace que el navegador no la envíe y la sesión parezca perdida.
    // En producción detrás de un proxy (p. ej. Render) se respeta X-Forwarded-Proto.
    $esHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0])) === 'https';

    session_set_cookie_params([
        'path'     => '/',
        'secure'   => $esHttps,
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

/**
 * Detiene reenvíos del mismo POST dentro de la misma sesión durante unos
 * segundos. Se invoca después de validar permisos, mientras PHP aún mantiene
 * bloqueada la sesión, para que dos peticiones simultáneas no pasen juntas.
 */
function bloquearReenvioDuplicado(string $accion, int $ventanaSegundos = 5): void
{
    static $verificadoEnEstaPeticion = false;
    if ($verificadoEnEstaPeticion) return;
    $verificadoEnEstaPeticion = true;

    if (session_status() !== PHP_SESSION_ACTIVE || strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
        return;
    }

    $datos = $_POST;
    unset($datos['device_id'], $datos['device_nombre'], $datos['device_modelo']);
    ksort($datos);
    $huella = hash('sha256', $accion . '|' . json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $ahora = microtime(true);
    $anteriores = $_SESSION['_solicitudes_recientes'] ?? [];
    if (!is_array($anteriores)) $anteriores = [];

    foreach ($anteriores as $clave => $marcaTiempo) {
        if (!is_numeric($marcaTiempo) || $ahora - (float) $marcaTiempo > $ventanaSegundos) {
            unset($anteriores[$clave]);
        }
    }

    if (isset($anteriores[$huella])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'message' => 'Este registro ya fue recibido. Evitamos guardarlo nuevamente.',
            'duplicado' => true,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $anteriores[$huella] = $ahora;
    if (count($anteriores) > 40) {
        asort($anteriores);
        $anteriores = array_slice($anteriores, -40, null, true);
    }
    $_SESSION['_solicitudes_recientes'] = $anteriores;
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
        // Al abrir una página desde el navegador, lleva al login en vez de
        // dejar el JSON de error visible como si fuera el contenido de la página.
        $aceptaHtml = str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'text/html')
            && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET';
        if ($aceptaHtml) {
            $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
            $segmentos = array_values(array_filter(explode('/', trim(dirname($script), '/'))));
            // El primer segmento es la carpeta raíz de la aplicación (si existe).
            $profundidad = max(0, count($segmentos) - 1);
            $login = str_repeat('../', $profundidad) . 'login.php';
            header('Location: ' . $login);
            exit;
        }
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
        if (usuarioTieneRol($usuario, (string)$rol, $modulo)) {
            $accion = strtoupper((string) ($_POST['accion'] ?? ''));
            if (preg_match('/^(GUARDAR|CREAR|REGISTRAR|AGREGAR|EDITAR|ELIMINAR|REACTIVAR|INICIAR|FINALIZAR|ENVIAR|PASAR|COMPLEMENTAR|SELECCIONAR|ACTUALIZAR|CERRAR|ANULAR|APROBAR|RECHAZAR|ASIGNAR|DESASIGNAR|COMPRAR)/', $accion)) {
                bloquearReenvioDuplicado($accion);
            }
            return;
        }
    }
    responderAcceso(403, 'No tienes permiso para realizar esta acción.');
}
