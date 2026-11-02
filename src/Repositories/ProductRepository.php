<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\Product;
use PDO;

final class ProductRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function findById(int $id): ?Product
    {
        $stmt = $this->db->prepare(
            'SELECT id, sku, name, description, sale_price, cost_price, stock, min_stock, is_active, created_at, updated_at
             FROM products
             WHERE id = :id'
        );

        $stmt->execute(['id' => $id]);
        /** @var array{id: int|string, sku: ?string, name: string, description: ?string, sale_price: float|string, cost_price: float|string, stock: int|string, min_stock: int|string, is_active: int|string, created_at: string, updated_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return new Product(
            id: (int) $row['id'],
            sku: $row['sku'],
            name: $row['name'],
            description: $row['description'],
            sale_price: (float) $row['sale_price'],
            cost_price: (float) $row['cost_price'],
            stock: (int) $row['stock'],
            min_stock: (int) $row['min_stock'],
            is_active: (bool) $row['is_active'],
            created_at: $row['created_at'],
            updated_at: $row['updated_at'],
        );
    }

    /**
     * @return array<int, Product>
     */
    public function findAllActive(): array
    {
        $stmt = $this->db->query(
            'SELECT id, sku, name, description, sale_price, cost_price, stock, min_stock, is_active, created_at, updated_at
             FROM products
             WHERE is_active = 1
             ORDER BY name ASC'
        );

        if ($stmt === false) {
            return [];
        }

        /** @var array<int, array{id: int|string, sku: ?string, name: string, description: ?string, sale_price: float|string, cost_price: float|string, stock: int|string, min_stock: int|string, is_active: int|string, created_at: string, updated_at: string}> $rows */
        $rows = $stmt->fetchAll();

        return array_map(
            fn(array $row): Product => new Product(
                id: (int) $row['id'],
                sku: $row['sku'],
                name: $row['name'],
                description: $row['description'],
                sale_price: (float) $row['sale_price'],
                cost_price: (float) $row['cost_price'],
                stock: (int) $row['stock'],
                min_stock: (int) $row['min_stock'],
                is_active: (bool) $row['is_active'],
                created_at: $row['created_at'],
                updated_at: $row['updated_at'],
            ),
            $rows
        );
    }

    public function create(
        string $name,
        float $sale_price,
        float $cost_price = 0.0,
        int $stock = 0,
        int $min_stock = 5,
        ?string $sku = null,
        ?string $description = null
    ): Product {
        $stmt = $this->db->prepare(
            'INSERT INTO products (sku, name, description, sale_price, cost_price, stock, min_stock, is_active)
             VALUES (:sku, :name, :description, :sale_price, :cost_price, :stock, :min_stock, 1)'
        );

        $stmt->execute([
            'sku' => $sku,
            'name' => $name,
            'description' => $description,
            'sale_price' => $sale_price,
            'cost_price' => $cost_price,
            'stock' => $stock,
            'min_stock' => $min_stock,
        ]);

        $id = (int) $this->db->lastInsertId();
        /** @var Product */
        return $this->findById($id);
    }

    public function updateStock(int $product_id, int $new_stock): void
    {
        $stmt = $this->db->prepare(
            'UPDATE products
             SET stock = :stock
             WHERE id = :id'
        );

        $stmt->execute([
            'id' => $product_id,
            'stock' => $new_stock,
        ]);
    }

    /**
     * Decrementa el stock atómicamente a nivel SQL.
     * Solo decrementa si hay stock suficiente.
     *
     * @return bool true si el stock fue decrementado, false si no había suficiente.
     */
    public function decrementStock(int $product_id, int $quantity): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE products
             SET stock = stock - :qty
             WHERE id = :id AND stock >= :min_stock'
        );

        $stmt->execute([
            'id' => $product_id,
            'qty' => $quantity,
            'min_stock' => $quantity,
        ]);

        return $stmt->rowCount() > 0;
    }
}
