<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Models\Product;
use App\Models\StoreSale;
use App\Repositories\InventoryMovementRepository;
use App\Repositories\ProductRepository;
use App\Repositories\RoomRepository;
use App\Repositories\StoreSaleRepository;
use DomainException;
use InvalidArgumentException;
use PDO;

final class StoreService
{
    /** @var list<string> */
    private const DIRECT_PAYMENT_METHODS = ['cash', 'yape', 'plin', 'card'];

    private PDO $db;
    private ProductRepository $product_repository;
    private StoreSaleRepository $sale_repository;
    private InventoryMovementRepository $inventory_repository;
    private RoomRepository $room_repository;
    private FolioService $folio_service;
    private CashboxService $cashbox_service;

    public function __construct(
        ?PDO $db = null,
        ?ProductRepository $product_repository = null,
        ?StoreSaleRepository $sale_repository = null,
        ?InventoryMovementRepository $inventory_repository = null,
        ?RoomRepository $room_repository = null,
        ?FolioService $folio_service = null,
        ?CashboxService $cashbox_service = null
    ) {
        $this->db = $db ?? Database::connection();
        $this->product_repository = $product_repository ?? new ProductRepository($this->db);
        $this->sale_repository = $sale_repository ?? new StoreSaleRepository($this->db);
        $this->inventory_repository = $inventory_repository ?? new InventoryMovementRepository($this->db);
        $this->room_repository = $room_repository ?? new RoomRepository($this->db);
        $this->folio_service = $folio_service ?? new FolioService($this->db);
        $this->cashbox_service = $cashbox_service ?? new CashboxService($this->db);
    }

    public function getProductRepository(): ProductRepository
    {
        return $this->product_repository;
    }

    public function createProduct(
        string $name,
        float $sale_price,
        float $cost_price = 0.0,
        int $stock = 0,
        int $min_stock = 5,
        ?string $sku = null,
        ?string $description = null
    ): Product {
        if (trim($name) === '') {
            throw new InvalidArgumentException("El nombre del producto no puede estar vacío.");
        }

        if (!is_finite($sale_price) || $sale_price < 0 || abs($sale_price - round($sale_price, 2)) > 0.000001) {
            throw new InvalidArgumentException("El precio de venta no puede ser negativo.");
        }

        if (!is_finite($cost_price) || $cost_price < 0 || abs($cost_price - round($cost_price, 2)) > 0.000001) {
            throw new InvalidArgumentException('El costo del producto debe ser válido y tener como máximo dos decimales.');
        }

        return $this->product_repository->create(
            name: $name,
            sale_price: $sale_price,
            cost_price: $cost_price,
            stock: $stock,
            min_stock: $min_stock,
            sku: $sku,
            description: $description
        );
    }

    /**
     * @return array<int, Product>
     */
    public function getActiveProducts(): array
    {
        return $this->product_repository->findAllActive();
    }

    /**
     * @param array<int, array{product_id: int, quantity: int}> $items_payload
     */
    public function sellDirectPOS(
        int $user_id,
        array $items_payload,
        string $payment_method = 'cash',
        ?string $idempotency_key = null
    ): StoreSale
    {
        if (count($items_payload) === 0) {
            throw new InvalidArgumentException("Debe incluir al menos un producto en la venta.");
        }

        if (!in_array($payment_method, self::DIRECT_PAYMENT_METHODS, true)) {
            throw new InvalidArgumentException('Método de pago inválido para una venta directa.');
        }

        $idempotency_key = $this->normalizeIdempotencyKey($idempotency_key);
        if ($idempotency_key !== null) {
            $existing_sale = $this->sale_repository->findByIdempotencyKey($idempotency_key);
            if ($existing_sale !== null) {
                if ($existing_sale->created_by_user_id !== $user_id) {
                    throw new DomainException('La clave de idempotencia pertenece a otra operación.');
                }

                return $existing_sale;
            }
        }

        $shift = $this->cashbox_service->getCurrentShift($user_id);
        if ($shift === null) {
            throw new DomainException("Debe abrir un turno de caja antes de realizar ventas.");
        }

        $owns_transaction = !$this->db->inTransaction();
        if ($owns_transaction) {
            $this->db->beginTransaction();
        }

        try {
            $total_amount = 0.0;
            $items_to_save = [];

            foreach ($items_payload as $item) {
                $product_id = $item['product_id'];
                $quantity = $item['quantity'];

                if ($quantity <= 0) {
                    throw new InvalidArgumentException("La cantidad debe ser mayor a cero.");
                }

                $product = $this->product_repository->findById($product_id);
                if ($product === null || !$product->is_active) {
                    throw new InvalidArgumentException("Producto con ID {$product_id} no encontrado o inactivo.");
                }

                if ($product->stock < $quantity) {
                    throw new DomainException("Stock insuficiente para '{$product->name}'. Disponible: {$product->stock}, solicitado: {$quantity}.");
                }

                // Atomic stock decrement: SQL-level check prevents race conditions
                $decremented = $this->product_repository->decrementStock($product_id, $quantity);
                if (!$decremented) {
                    throw new DomainException("Stock insuficiente para '{$product->name}'. Otro proceso vendió las unidades disponibles.");
                }

                $subtotal = $product->sale_price * $quantity;
                $total_amount += $subtotal;

                $new_stock = $product->stock - $quantity;

                $this->inventory_repository->create(
                    product_id: $product_id,
                    movement_type: 'sale',
                    quantity: -$quantity,
                    resulting_stock: $new_stock,
                    concept: "Venta Directa POS",
                    created_by_user_id: $user_id
                );

                $items_to_save[] = [
                    'product_id' => $product_id,
                    'quantity' => $quantity,
                    'unit_price' => $product->sale_price,
                    'subtotal' => $subtotal,
                ];
            }

            $sale = $this->sale_repository->create(
                cashbox_shift_id: $shift->id,
                total_amount: $total_amount,
                payment_method: $payment_method,
                created_by_user_id: $user_id,
                items: $items_to_save,
                idempotency_key: $idempotency_key,
            );

            $this->cashbox_service->recordMovement(
                user_id: $user_id,
                type: 'income',
                category: 'store_sale',
                concept: "Venta Tienda POS #{$sale->id}",
                amount: $total_amount,
                payment_method: $payment_method,
                reference_id: $sale->id,
                reference_type: 'store_sale'
            );

            if ($owns_transaction) {
                $this->db->commit();
            }
            return $sale;
        } catch (\Throwable $e) {
            if ($owns_transaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ($idempotency_key !== null) {
                $existing_sale = $this->sale_repository->findByIdempotencyKey($idempotency_key);
                if ($existing_sale !== null) {
                    if ($existing_sale->created_by_user_id === $user_id) {
                        return $existing_sale;
                    }

                    throw new DomainException('La clave de idempotencia pertenece a otra operación.');
                }
            }

            throw $e;
        }
    }

    private function normalizeIdempotencyKey(?string $idempotency_key): ?string
    {
        $idempotency_key = trim((string) $idempotency_key);
        return $idempotency_key === '' ? null : hash('sha256', $idempotency_key);
    }

    /**
     * @param array<int, array{product_id: int, quantity: int}> $items_payload
     */
    public function chargeToRoom(int $room_id, array $items_payload, int $user_id): void
    {
        if (count($items_payload) === 0) {
            throw new InvalidArgumentException("Debe incluir al menos un producto para cargar a la habitación.");
        }

        $room = $this->room_repository->findById($room_id);
        if ($room === null || $room->status !== 'occupied' || $room->current_stay === null) {
            throw new DomainException("La habitación no posee una estancia activa.");
        }

        $stay_id = $room->current_stay->id;

        $owns_transaction = !$this->db->inTransaction();
        if ($owns_transaction) {
            $this->db->beginTransaction();
        }

        try {
            foreach ($items_payload as $item) {
                $product_id = $item['product_id'];
                $quantity = $item['quantity'];

                if ($quantity <= 0) {
                    throw new InvalidArgumentException("La cantidad debe ser mayor a cero.");
                }

                $product = $this->product_repository->findById($product_id);
                if ($product === null || !$product->is_active) {
                    throw new InvalidArgumentException("Producto con ID {$product_id} no encontrado o inactivo.");
                }

                if ($product->stock < $quantity) {
                    throw new DomainException("Stock insuficiente para '{$product->name}'. Disponible: {$product->stock}, solicitado: {$quantity}.");
                }

                // Atomic stock decrement: SQL-level check prevents race conditions
                $decremented = $this->product_repository->decrementStock($product_id, $quantity);
                if (!$decremented) {
                    throw new DomainException("Stock insuficiente para '{$product->name}'. Otro proceso vendió las unidades disponibles.");
                }

                $new_stock = $product->stock - $quantity;

                $this->inventory_repository->create(
                    product_id: $product_id,
                    movement_type: 'charge_to_room',
                    quantity: -$quantity,
                    resulting_stock: $new_stock,
                    concept: "Consumo cargado a Habitación {$room->number}",
                    created_by_user_id: $user_id
                );

                $this->folio_service->addCharge(
                    stay_id: $stay_id,
                    charge_type: 'store_item',
                    description: "Consumo: {$product->name} x{$quantity}",
                    quantity: $quantity,
                    unit_price: $product->sale_price,
                    created_by_user_id: $user_id
                );
            }

            if ($owns_transaction) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($owns_transaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
