<?php

class UserController
{
    private PDO $pdo;
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAllUsers(string $texto = '', string $estado = ''): array
    {
        $where = ['1=1'];
        $params = [];

        if ($texto !== '') {
            $where[] = '(LOWER(user_) LIKE LOWER(:texto) OR LOWER(nombre_completo) LIKE LOWER(:texto))';
            $params['texto'] = "%$texto%";
        }
        if ($estado === 'activa') {
            $where[] = 'deleted_at IS NULL';
        } elseif ($estado === 'inactiva') {
            $where[] = 'deleted_at IS NOT NULL';
        }

        $sql = 'SELECT id, user_, nombre_completo, rol_y_perfiles, operario_id, deleted_at, created_at, updated_at
                FROM usuario WHERE ' . implode(' AND ', $where) . ' ORDER BY nombre_completo';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
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

        // Normalización: login siempre en minúsculas (evita colisiones tipo
        // "Admin" / "admin" / "ADMIN" como si fueran cuentas distintas),
        // nombre completo en mayúsculas para consistencia con área/cargo/operario.
        $data['user_']           = mb_strtolower(trim($data['user_']), 'UTF-8');
        $data['nombre_completo'] = mb_strtoupper(trim($data['nombre_completo']), 'UTF-8');

        $isEditing = !empty($data['id']);

        // Evita logins duplicados, sea cuenta manual u originada desde un operario.
        // Comparación case-insensitive: aunque ya normalizamos el nuevo valor
        // arriba, esto también protege contra registros viejos que aún no
        // hayan pasado por la migración de normalización.
        $stmtDup = $this->pdo->prepare('SELECT id FROM usuario WHERE LOWER(user_) = LOWER(:user_) AND id <> :id');
        $stmtDup->execute(['user_' => $data['user_'], 'id' => $data['id'] ?? 0]);
        if ($stmtDup->fetch()) {
            return ['ok' => false, 'msg' => 'Ya existe un usuario con ese login.'];
        }

        $rolYPerfiles = json_encode($data['rol_y_perfiles'] ?? ['rol' => 'operario', 'perfiles' => []]);

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
            $this->pdo->prepare($sql)->execute($params);
            return ['ok' => true, 'msg' => 'Usuario actualizado correctamente.'];
        }

        if (empty($data['password'])) {
            return ['ok' => false, 'msg' => 'La contraseña es obligatoria al crear un usuario.'];
        }

        // Cuenta creada manualmente desde este panel -> operario_id queda NULL
        $stmt = $this->pdo->prepare(
            'INSERT INTO usuario (user_, pass_, nombre_completo, rol_y_perfiles, operario_id, created_at, updated_at)
             VALUES (:user_, :pass_, :nombre_completo, :rol_y_perfiles, NULL, NOW(), NOW())'
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