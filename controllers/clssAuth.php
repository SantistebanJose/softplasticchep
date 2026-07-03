<?php

/**
 * controllers/clssAuth.php
 * Lógica de autenticación (login/logout). Sin clases — funciones sueltas.
 * Usa el $pdo global creado en includes/config.php.
 */

/**
 * Intenta autenticar al usuario. Si tiene éxito, deja la sesión seteada.
 * Devuelve ['success' => bool, 'error' => string|null]
 */
function intentarLogin(PDO $pdo, string $user_, string $password): array
{
    $user_    = trim($user_);
    $password = trim($password);

    if ($user_ === '' || $password === '') {
        return ['success' => false, 'error' => 'Ingresa tu usuario y tu contraseña.'];
    }

    $stmt = $pdo->prepare('
        SELECT id, user_, pass_, nombre_completo, rol_y_perfiles, deleted_at
        FROM usuario
        WHERE user_ = :user_
    ');
    $stmt->execute(['user_' => $user_]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        return ['success' => false, 'error' => 'Usuario o contraseña incorrectos.'];
    }

    [$passwordValid, $needsRehash] = verificarPasswordUsuario($password, $usuario['pass_']);

    if (!$passwordValid) {
        return ['success' => false, 'error' => 'Usuario o contraseña incorrectos.'];
    }

    if ($usuario['deleted_at'] !== null) {
        return ['success' => false, 'error' => 'Este usuario está desactivado. Contacta a un administrador.'];
    }

    session_regenerate_id(true);

    if ($needsRehash) {
        actualizarHashUsuario($pdo, $usuario['id'], $password);
    }

    guardarSesionUsuario($usuario);

    return ['success' => true, 'error' => null];
}

/**
 * Verifica la contraseña contra el hash almacenado.
 * Soporta el caso legado de contraseñas guardadas en texto plano,
 * marcándolas para actualizar a hash seguro en el próximo login exitoso.
 * Devuelve [passwordValid, needsRehash]
 */
function verificarPasswordUsuario(string $password, string $storedPassword): array
{
    $storedIsHash = password_get_info($storedPassword)['algo'] !== 0;

    if ($storedIsHash) {
        if (password_verify($password, $storedPassword)) {
            $needsRehash = password_needs_rehash($storedPassword, PASSWORD_DEFAULT);
            return [true, $needsRehash];
        }
        return [false, false];
    }

    // Contraseña en texto plano (legado): aceptar y marcar para migrar a hash
    if ($password === $storedPassword) {
        return [true, true];
    }

    return [false, false];
}

function actualizarHashUsuario(PDO $pdo, int $usuarioId, string $password): void
{
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    if ($newHash !== false) {
        $update = $pdo->prepare('UPDATE usuario SET pass_ = :pass_ WHERE id = :id');
        $update->execute(['pass_' => $newHash, 'id' => $usuarioId]);
    }
}

function guardarSesionUsuario(array $usuario): void
{
    $rolPerfiles = $usuario['rol_y_perfiles']
        ? json_decode($usuario['rol_y_perfiles'], true)
        : [];

    $_SESSION['usuario_id']     = $usuario['id'];
    $_SESSION['nombre_usuario'] = $usuario['nombre_completo'];
    $_SESSION['user_usuario']   = $usuario['user_'];
    $_SESSION['rol_usuario']    = $rolPerfiles['rol'] ?? null;
    $_SESSION['perfiles']       = $rolPerfiles['perfiles'] ?? [];
}

function cerrarSesionUsuario(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}