<?php

require_once __DIR__ . '/../includes/config.php';

class ProductController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAllActive(): array
    {
        return $this->pdo->query(
            "SELECT p.*, c.nombre AS categoria_nombre, m.nombre AS modelo_nombre
             FROM productos p
             JOIN categorias_producto c ON c.id = p.categoria_id
             LEFT JOIN modelos_producto m ON m.id = p.modelo_id
             WHERE p.activo = TRUE
             ORDER BY p.nombre"
        )->fetchAll();
    }

    public function getById(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM productos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: [];
    }

    public function save(array $data): array
    {
        if (empty($data['nombre']) || empty($data['categoria_id'])) {
            return ['ok' => false, 'msg' => 'El nombre y la categoría son obligatorios.'];
        }

        if (!empty($data['id'])) {
            $stmt = $this->pdo->prepare(
                'UPDATE productos SET codigo = :codigo, nombre = :nombre, categoria_id = :categoria_id, modelo_id = :modelo_id, color = :color, medida = :medida, precio = :precio, stock_minimo = :stock_minimo, updated_at = NOW() WHERE id = :id'
            );
            $stmt->execute([
                'codigo' => $data['codigo'],
                'nombre' => $data['nombre'],
                'categoria_id' => $data['categoria_id'],
                'modelo_id' => $data['modelo_id'] ?: null,
                'color' => $data['color'],
                'medida' => $data['medida'],
                'precio' => $data['precio'] ?: 0,
                'stock_minimo' => $data['stock_minimo'] ?: 0,
                'id' => $data['id'],
            ]);
            return ['ok' => true, 'msg' => 'Producto actualizado correctamente.'];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO productos (codigo, nombre, categoria_id, modelo_id, color, medida, precio, stock_minimo) VALUES (:codigo, :nombre, :categoria_id, :modelo_id, :color, :medida, :precio, :stock_minimo)'
        );
        $stmt->execute([
            'codigo' => $data['codigo'],
            'nombre' => $data['nombre'],
            'categoria_id' => $data['categoria_id'],
            'modelo_id' => $data['modelo_id'] ?: null,
            'color' => $data['color'],
            'medida' => $data['medida'],
            'precio' => $data['precio'] ?: 0,
            'stock_minimo' => $data['stock_minimo'] ?: 0,
        ]);
        return ['ok' => true, 'msg' => 'Producto creado correctamente.'];
    }

    public function delete(int $id): array
    {
        $stmt = $this->pdo->prepare('UPDATE productos SET activo = FALSE, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return ['ok' => true, 'msg' => 'Producto eliminado.'];
    }

    public function getModelsByCategory(int $categoriaId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, nombre FROM modelos_producto WHERE categoria_id = :cid AND activo = TRUE ORDER BY nombre');
        $stmt->execute(['cid' => $categoriaId]);
        return $stmt->fetchAll();
    }
}
