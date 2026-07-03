<?php


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

    public function getById(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM usuario WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: [];
    }

    public function saveUser(array $data): array
    {
        if (empty($data['user_']) || empty($data['nombre_completo'])) {
            return ['ok' => false, 'msg' => 'El usuario y nombre completo son obligatorios.'];
        }

        $rolYPerfiles = json_encode($data['rol_y_perfiles'] ?? ['rol' => 'operario', 'perfiles' => []]);
        $isEditing = !empty($data['id']);

        if ($isEditing) {
            $params = [
                'user_' => $data['user_'],
                'nombre_completo' => $data['nombre_completo'],
                'rol_y_perfiles' => $rolYPerfiles,
                'id' => $data['id'],
            ];
            $sql = 'UPDATE usuario SET user_ = :user_, nombre_completo = :nombre_completo, rol_y_perfiles = :rol_y_perfiles, updated_at = NOW()';
            if (!empty($data['password'])) {
                $sql .= ', pass_ = :pass_';
                $params['pass_'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            $sql .= ' WHERE id = :id';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return ['ok' => true, 'msg' => 'Usuario actualizado correctamente.'];
        }

        if (empty($data['password'])) {
            return ['ok' => false, 'msg' => 'La contraseña es obligatoria al crear un usuario.'];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO usuario (user_, pass_, nombre_completo, rol_y_perfiles, created_at, updated_at) VALUES (:user_, :pass_, :nombre_completo, :rol_y_perfiles, NOW(), NOW())'
        );
        $stmt->execute([
            'user_' => $data['user_'],
            'pass_' => password_hash($data['password'], PASSWORD_DEFAULT),
            'nombre_completo' => $data['nombre_completo'],
            'rol_y_perfiles' => $rolYPerfiles,
        ]);
        return ['ok' => true, 'msg' => 'Usuario creado correctamente.'];
    }

    public function deleteUser(int $id): array
    {
        $stmt = $this->pdo->prepare('UPDATE usuario SET deleted_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return ['ok' => true, 'msg' => 'Usuario eliminado correctamente.'];
    }
}
