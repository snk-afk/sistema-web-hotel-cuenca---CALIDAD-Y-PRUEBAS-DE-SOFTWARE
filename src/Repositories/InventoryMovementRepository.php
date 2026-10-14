<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\InventoryMovement;
use PDO;

final class InventoryMovementRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function create(
        int $product_id,
        string $movement_type,
        int $quantity,
        int $resulting_stock,
        string $concept,
        int $created_by_user_id
    ): InventoryMovement {
        $db_movement_type = match ($movement_type) {
            'in', 'entry', 'purchase' => 'in',
            'out', 'exit', 'sale', 'charge_to_room' => 'out',
            default => 'adjustment',
        };

        $previous_stock = $resulting_stock - $quantity;

        $stmt = $this->db->prepare(
            'INSERT INTO inventory_movements (product_id, movement_type, quantity, previous_stock, new_stock, reference, created_by_user_id)
             VALUES (:product_id, :movement_type, :quantity, :previous_stock, :new_stock, :reference, :created_by_user_id)'
        );

        $stmt->execute([
            'product_id' => $product_id,
            'movement_type' => $db_movement_type,
            'quantity' => abs($quantity),
            'previous_stock' => $previous_stock,
            'new_stock' => $resulting_stock,
            'reference' => $concept,
            'created_by_user_id' => $created_by_user_id,
        ]);

        $id = (int) $this->db->lastInsertId();

        return new InventoryMovement(
            id: $id,
            product_id: $product_id,
            movement_type: $movement_type,
            quantity: $quantity,
            resulting_stock: $resulting_stock,
            concept: $concept,
            created_by_user_id: $created_by_user_id,
            created_at: date('Y-m-d H:i:s'),
        );
    }
}
