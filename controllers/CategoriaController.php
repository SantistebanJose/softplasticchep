<?php

require_once __DIR__ . '/../includes/config.php';

class CategoriaController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAll(): array
    {
        return $this->pdo->query('SELECT * FROM categorias_producto WHERE activo = TRUE ORDER BY nombre')->fetchAll();
    }

    public function getById(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM categorias_producto WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: [];
    }

    public function save(array $data): array
    {
        if (empty($data['nombre'])) {
            return ['ok' => false, 'msg' => 'El nombre es obligatorio.'];
        }

        if (!empty($data['id'])) {
            $stmt = $this->pdo->prepare('UPDATE categorias_producto SET nombre = :nombre, descripcion = :descripcion, updated_at = NOW() WHERE id = :id');
            $stmt->execute([
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? null,
                'id' => $data['id'],
            ]);
            return ['ok' => true, 'msg' => 'Categoría actualizada correctamente.'];
        }

        $stmt = $this->pdo->prepare('INSERT INTO categorias_producto (nombre, descripcion) VALUES (:nombre, :descripcion)');
        $stmt->execute([
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'] ?? null,
        ]);
        return ['ok' => true, 'msg' => 'Categoría creada correctamente.'];
    }

    public function delete(int $id): array
    {
        $stmt = $this->pdo->prepare('UPDATE categorias_producto SET activo = FALSE, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return ['ok' => true, 'msg' => 'Categoría eliminada.'];
    }
}
