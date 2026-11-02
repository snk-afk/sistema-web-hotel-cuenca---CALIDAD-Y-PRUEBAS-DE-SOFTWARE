<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\StoreSale;
use App\Models\StoreSaleItem;
use PDO;

final class StoreSaleRepository
{
    private PDO $db;
    private ProductRepository $product_repository;

    public function __construct(?PDO $db = null, ?ProductRepository $product_repository = null)
    {
        $this->db = $db ?? Database::connection();
        $this->product_repository = $product_repository ?? new ProductRepository($this->db);
    }

    public function findById(int $id): ?StoreSale
    {
        $stmt = $this->db->prepare(
            'SELECT id, cashbox_shift_id, total_amount, payment_type, created_by_user_id, created_at
             FROM store_sales
             WHERE id = :id'
        );

        $stmt->execute(['id' => $id]);
        /** @var array{id: int|string, cashbox_shift_id: int|string, total_amount: float|string, payment_type: string, created_by_user_id: int|string, created_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $item_stmt = $this->db->prepare(
            'SELECT id, store_sale_id, product_id, quantity, unit_price, subtotal
             FROM store_sale_items
             WHERE store_sale_id = :sale_id'
        );
        $item_stmt->execute(['sale_id' => $id]);
        /** @var array<int, array{id: int|string, store_sale_id: int|string, product_id: int|string, quantity: int|string, unit_price: float|string, subtotal: float|string}> $item_rows */
        $item_rows = $item_stmt->fetchAll();

        $items = array_map(
            function (array $item_row): StoreSaleItem {
                $product = $this->product_repository->findById((int) $item_row['product_id']);
                return new StoreSaleItem(
                    id: (int) $item_row['id'],
                    store_sale_id: (int) $item_row['store_sale_id'],
                    product_id: (int) $item_row['product_id'],
                    quantity: (int) $item_row['quantity'],
                    unit_price: (float) $item_row['unit_price'],
                    subtotal: (float) $item_row['subtotal'],
                    product: $product,
                );
            },
            $item_rows
        );

        return new StoreSale(
            id: (int) $row['id'],
            cashbox_shift_id: (int) $row['cashbox_shift_id'],
            total_amount: (float) $row['total_amount'],
            payment_method: $row['payment_type'],
            created_by_user_id: (int) $row['created_by_user_id'],
            created_at: $row['created_at'],
            items: $items,
        );
    }

    public function findByIdempotencyKey(string $idempotency_key): ?StoreSale
    {
        $statement = $this->db->prepare(
            'SELECT id FROM store_sales WHERE idempotency_key = :idempotency_key'
        );
        $statement->execute(['idempotency_key' => $idempotency_key]);
        /** @var int|string|false $sale_id */
        $sale_id = $statement->fetchColumn();

        return $sale_id === false ? null : $this->findById((int) $sale_id);
    }

    /**
     * @param array<int, array{product_id: int, quantity: int, unit_price: float, subtotal: float}> $items
     */
    public function create(
        int $cashbox_shift_id,
        float $total_amount,
        string $payment_method,
        int $created_by_user_id,
        array $items,
        ?string $idempotency_key = null
    ): StoreSale {
        $stmt = $idempotency_key === null
            ? $this->db->prepare(
                'INSERT INTO store_sales (cashbox_shift_id, total_amount, payment_type, created_by_user_id)
                 VALUES (:cashbox_shift_id, :total_amount, :payment_type, :created_by_user_id)'
            )
            : $this->db->prepare(
                'INSERT INTO store_sales (cashbox_shift_id, total_amount, payment_type, created_by_user_id, idempotency_key)
                 VALUES (:cashbox_shift_id, :total_amount, :payment_type, :created_by_user_id, :idempotency_key)'
            );

        $parameters = [
            'cashbox_shift_id' => $cashbox_shift_id,
            'total_amount' => $total_amount,
            'payment_type' => $payment_method,
            'created_by_user_id' => $created_by_user_id,
        ];
        if ($idempotency_key !== null) {
            $parameters['idempotency_key'] = $idempotency_key;
        }
        $stmt->execute($parameters);

        $sale_id = (int) $this->db->lastInsertId();

        $item_stmt = $this->db->prepare(
            'INSERT INTO store_sale_items (store_sale_id, product_id, quantity, unit_price, subtotal)
             VALUES (:store_sale_id, :product_id, :quantity, :unit_price, :subtotal)'
        );

        foreach ($items as $item) {
            $item_stmt->execute([
                'store_sale_id' => $sale_id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'subtotal' => $item['subtotal'],
            ]);
        }

        /** @var StoreSale */
        return $this->findById($sale_id);
    }
}
