<?php

require_once __DIR__ . '/../includes/config.php';

class ModelController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAll(): array
    {
        return $this->pdo->query(
            'SELECT m.*, c.nombre AS categoria_nombre
             FROM modelos_producto m
             JOIN categorias_producto c ON c.id = m.categoria_id
             WHERE m.activo = TRUE
             ORDER BY c.nombre, m.nombre'
        )->fetchAll();
    }

    public function getById(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM modelos_producto WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: [];
    }

    public function getByCategory(int $categoriaId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, nombre FROM modelos_producto WHERE categoria_id = :cid AND activo = TRUE ORDER BY nombre');
        $stmt->execute(['cid' => $categoriaId]);
        return $stmt->fetchAll();
    }

    public function save(array $data): array
    {
        if (empty($data['nombre']) || empty($data['categoria_id'])) {
            return ['ok' => false, 'msg' => 'La categoría y el nombre son obligatorios.'];
        }

        if (!empty($data['id'])) {
            $stmt = $this->pdo->prepare('UPDATE modelos_producto SET categoria_id = :categoria_id, nombre = :nombre, updated_at = NOW() WHERE id = :id');
            $stmt->execute([
                'categoria_id' => $data['categoria_id'],
                'nombre' => $data['nombre'],
                'id' => $data['id'],
            ]);
            return ['ok' => true, 'msg' => 'Modelo actualizado correctamente.'];
        }

        $stmt = $this->pdo->prepare('INSERT INTO modelos_producto (categoria_id, nombre) VALUES (:categoria_id, :nombre)');
        $stmt->execute([
            'categoria_id' => $data['categoria_id'],
            'nombre' => $data['nombre'],
        ]);
        return ['ok' => true, 'msg' => 'Modelo creado correctamente.'];
    }

    public function delete(int $id): array
    {
        $stmt = $this->pdo->prepare('UPDATE modelos_producto SET activo = FALSE, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return ['ok' => true, 'msg' => 'Modelo eliminado.'];
    }
}
