<?php

require_once __DIR__ . '/../includes/config.php';

class AuthController
{
    private PDO $pdo;
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function login(string $user_, string $password): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_, pass_, nombre_completo, rol_y_perfiles, deleted_at FROM usuario WHERE user_ = :user_'
        );
        $stmt->execute(['user_' => $user_]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            return ['ok' => false, 'msg' => 'Usuario o contraseña incorrectos.'];
        }

        $storedPassword = $usuario['pass_'];
        $storedIsHash = password_get_info($storedPassword)['algo'] !== 0;
        $passwordValid = false;
        $needsRehash = false;

        if ($storedIsHash && password_verify($password, $storedPassword)) {
            $passwordValid = true;
            if (password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
                $needsRehash = true;
            }
        } elseif (!$storedIsHash && $password === $storedPassword) {
            $passwordValid = true;
            $needsRehash = true;
        }

        if (!$passwordValid) {
            return ['ok' => false, 'msg' => 'Usuario o contraseña incorrectos.'];
        }

        if ($usuario['deleted_at'] !== null) {
            return ['ok' => false, 'msg' => 'Este usuario está desactivado. Contacta a un administrador.'];
        }

        if ($needsRehash) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            if ($newHash !== false) {
                $update = $this->pdo->prepare('UPDATE usuario SET pass_ = :pass_ WHERE id = :id');
                $update->execute(['pass_' => $newHash, 'id' => $usuario['id']]);
            }
        }

        session_regenerate_id(true);

        $rolPerfiles = $usuario['rol_y_perfiles'] ? json_decode($usuario['rol_y_perfiles'], true) : [];

        $_SESSION['usuario_id']     = $usuario['id'];
        $_SESSION['nombre_usuario'] = $usuario['nombre_completo'];
        $_SESSION['user_usuario']   = $usuario['user_'];
        $_SESSION['rol_usuario']    = $rolPerfiles['rol'] ?? null;
        $_SESSION['perfiles']       = $rolPerfiles['perfiles'] ?? [];

        return ['ok' => true];
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }
}
