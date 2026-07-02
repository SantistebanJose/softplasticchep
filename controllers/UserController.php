<?php

require_once __DIR__ . '/../includes/config.php';

class UserController
{
    private PDO $pdo;
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAllUsers(): array
    {
        return $this->pdo->query('SELECT id, user_, nombre_completo, rol_y_perfiles, deleted_at, created_at, updated_at FROM usuario ORDER BY nombre_completo')->fetchAll();
    }

    public function getUserCount(): array
    {
        return [
            'total' => (int) $this->pdo->query('SELECT COUNT(*) FROM usuario')->fetchColumn(),
            'active' => (int) $this->pdo->query('SELECT COUNT(*) FROM usuario WHERE deleted_at IS NULL')->fetchColumn(),
        ];
    }

    public function createUser(array $data): array
    {
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare(
            'INSERT INTO usuario (user_, pass_, nombre_completo, rol_y_perfiles, created_at, updated_at) VALUES (:user_, :pass_, :nombre_completo, :rol_y_perfiles, NOW(), NOW())'
        );
        $stmt->execute([
            'user_' => $data['user_'],
            'pass_' => $passwordHash,
            'nombre_completo' => $data['nombre_completo'],
            'rol_y_perfiles' => json_encode($data['rol_y_perfiles'] ?? ['rol' => 'operario', 'perfiles' => []]),
        ]);
        return ['ok' => true, 'msg' => 'Usuario creado correctamente.'];
    }
}
