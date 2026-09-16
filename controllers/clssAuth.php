<?php

/**
 * controllers/clssAuth.php
 * Lógica de autenticación: login y logout.
 *
 * Usa la misma sesión segura del proyecto y guarda la identidad en:
 * $_SESSION['usuario_id'], nombre_usuario, user_usuario,
 * rol_usuario y perfiles.
 */

declare(strict_types=1);

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
require_once __DIR__ . '/auditoria.php';
require_once __DIR__ . '/clssVerificarSession.php';

/**
 * Intenta autenticar un usuario.
 *
 * @return array{success: bool, error: ?string}
 */
function intentarLogin(string $user_, string $password): array
{
    iniciarSesionSegura();

    $user_ = trim($user_);

    // No aplicar trim() a la contraseña: los espacios pueden formar parte de ella.
    if ($user_ === '' || $password === '') {
        return [
            'success' => false,
            'error'   => 'Ingresa tu usuario y tu contraseña.',
        ];
    }

    $conectar = conectar_oll_BD();

    // LOWER permite ingresar cuentas antiguas sin depender de mayúsculas/minúsculas.
    $result = executeQuery(
        $conectar,
        "SELECT id, user_, pass_, nombre_completo, rol_y_perfiles, deleted_at
         FROM usuario
         WHERE LOWER(user_) = LOWER(:user_)
         LIMIT 1",
        ['user_' => $user_]
    );

    if (empty($result)) {
        return [
            'success' => false,
            'error'   => 'Usuario o contraseña incorrectos.',
        ];
    }

    $usuario = $result[0];

    [$passwordValido, $necesitaRehash] = verificarPasswordUsuario(
        $password,
        (string) ($usuario['pass_'] ?? '')
    );

    if (!$passwordValido) {
        return [
            'success' => false,
            'error'   => 'Usuario o contraseña incorrectos.',
        ];
    }

    if (!empty($usuario['deleted_at'])) {
        return [
            'success' => false,
            'error'   => 'Este usuario está desactivado. Contacta a un administrador.',
        ];
    }

    // Evita fijación de sesión tras un login exitoso.
    session_regenerate_id(true);

    // Migra contraseñas antiguas en texto plano o hashes obsoletos.
    if ($necesitaRehash) {
        actualizarHashUsuario($conectar, (int) $usuario['id'], $password);
    }

    guardarSesionUsuario($usuario);

    return [
        'success' => true,
        'error'   => null,
    ];
}

/**
 * Verifica contraseña contra hash moderno o contraseña legada en texto plano.
 *
 * @return array{0: bool, 1: bool} [válida, necesitaRehash]
 */
function verificarPasswordUsuario(string $password, string $storedPassword): array
{
    if ($storedPassword === '') {
        return [false, false];
    }

    $storedIsHash = password_get_info($storedPassword)['algo'] !== 0;

    if ($storedIsHash) {
        if (!password_verify($password, $storedPassword)) {
            return [false, false];
        }

        return [
            true,
            password_needs_rehash($storedPassword, PASSWORD_DEFAULT),
        ];
    }

    // Compatibilidad temporal para contraseñas antiguas sin hash.
    if (hash_equals($storedPassword, $password)) {
        return [true, true];
    }

    return [false, false];
}

/**
 * Actualiza una contraseña a un hash seguro.
 */
function actualizarHashUsuario($conectar, int $usuarioId, string $password): void
{
    $newHash = password_hash($password, PASSWORD_DEFAULT);

    if ($newHash === false) {
        throw new RuntimeException('No se pudo generar el hash de la contraseña.');
    }

    executeQuery(
        $conectar,
        "UPDATE usuario
         SET pass_ = :pass_, updated_at = NOW()
         WHERE id = :id",
        [
            'pass_' => $newHash,
            'id'    => $usuarioId,
        ]
    );
}

/**
 * Registra la identidad del usuario autenticado en la sesión.
 */
function guardarSesionUsuario(array $usuario): void
{
    $rolPerfilesRaw = $usuario['rol_y_perfiles'] ?? [];
    $rolPerfiles = [];

    if (is_array($rolPerfilesRaw)) {
        $rolPerfiles = $rolPerfilesRaw;
    } elseif (is_string($rolPerfilesRaw) && $rolPerfilesRaw !== '') {
        $decoded = json_decode($rolPerfilesRaw, true);
        $rolPerfiles = is_array($decoded) ? $decoded : [];
    }

    $_SESSION['usuario_id']          = (int) $usuario['id'];
    $_SESSION['nombre_usuario']      = (string) ($usuario['nombre_completo'] ?? '');
    $_SESSION['user_usuario']        = (string) ($usuario['user_'] ?? '');
    $_SESSION['rol_usuario']         = (string) ($rolPerfiles['rol'] ?? '');
    $_SESSION['perfiles']            = is_array($rolPerfiles['perfiles'] ?? null)
        ? $rolPerfiles['perfiles']
        : [];
    $_SESSION['mostrar_bienvenida']  = true;
}

/**
 * Cierra la sesión actual y elimina su cookie.
 */
function cerrarSesionUsuario(): void
{
    iniciarSesionSegura();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'] ?? '/',
            'domain'   => $params['domain'] ?? '',
            'secure'   => (bool) ($params['secure'] ?? true),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}