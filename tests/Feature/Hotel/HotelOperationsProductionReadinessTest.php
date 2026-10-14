<?php

declare(strict_types=1);

namespace Tests\Feature\Hotel;

use App\Repositories\ProductRepository;
use App\Services\CashboxService;
use App\Services\ExpenseService;
use App\Services\StoreService;
use DomainException;
use InvalidArgumentException;
use Tests\Support\MySqlTestCase;

final class HotelOperationsProductionReadinessTest extends MySqlTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createUserFixture(1, 'hotel-ops');
        (new CashboxService($this->pdo()))->openShift(1, 100.00);
    }

    public function testInvalidPaymentMethodIsRejectedBeforeAnySaleOrStockMutation(): void
    {
        $product = (new ProductRepository($this->pdo()))->create(
            name: 'Producto protegido',
            sale_price: 10.00,
            cost_price: 3.00,
            stock: 5,
            min_stock: 1,
            sku: 'OPS-001',
        );

        $this->pdo()->exec('SAVEPOINT invalid_payment_method');
        try {
            (new StoreService($this->pdo()))->sellDirectPOS(
                user_id: 1,
                items_payload: [['product_id' => $product->id, 'quantity' => 1]],
                payment_method: 'bitcoin',
            );
            self::fail('El método de pago inválido debía rechazarse.');
        } catch (InvalidArgumentException) {
            $this->pdo()->exec('ROLLBACK TO SAVEPOINT invalid_payment_method');
        } finally {
            self::assertSame(5, (int) $this->pdo()->query('SELECT stock FROM products WHERE id = ' . $product->id)->fetchColumn());
            self::assertSame(0, (int) $this->pdo()->query('SELECT COUNT(*) FROM store_sales')->fetchColumn());
        }
    }

    public function testMoneyOperationsRejectMoreThanTwoDecimals(): void
    {
        $expense_service = new ExpenseService($this->pdo());

        $this->pdo()->exec('SAVEPOINT invalid_expense_amount');
        try {
            $expense_service->recordExpense(1, 'supplies', 'Monto inválido', 1.001, 'cash');
            self::fail('El egreso con más de dos decimales debía rechazarse.');
        } catch (InvalidArgumentException) {
            $this->pdo()->exec('ROLLBACK TO SAVEPOINT invalid_expense_amount');
            self::assertSame(0, (int) $this->pdo()->query('SELECT COUNT(*) FROM expenses')->fetchColumn());
        }

        $this->pdo()->exec('SAVEPOINT invalid_income_amount');
        try {
            $expense_service->recordOtherIncome(1, 'other_income', 'Monto inválido', 2.001, 'cash');
            self::fail('El ingreso con más de dos decimales debía rechazarse.');
        } catch (InvalidArgumentException) {
            $this->pdo()->exec('ROLLBACK TO SAVEPOINT invalid_income_amount');
            self::assertSame(0, (int) $this->pdo()->query('SELECT COUNT(*) FROM other_incomes')->fetchColumn());
        }
    }

    public function testFailedMultiItemSaleRollsBackStockAndInventory(): void
    {
        $repository = new ProductRepository($this->pdo());
        $first = $repository->create('Producto uno', 10.00, 3.00, 5, 1, 'OPS-002');
        $second = $repository->create('Producto dos', 20.00, 8.00, 1, 1, 'OPS-003');

        $this->pdo()->exec('SAVEPOINT failed_multi_item_sale');
        try {
            (new StoreService($this->pdo()))->sellDirectPOS(1, [
                ['product_id' => $first->id, 'quantity' => 2],
                ['product_id' => $second->id, 'quantity' => 2],
            ]);
            self::fail('La venta con stock insuficiente debía rechazarse.');
        } catch (DomainException) {
            $this->pdo()->exec('ROLLBACK TO SAVEPOINT failed_multi_item_sale');
        } finally {
            self::assertSame(5, (int) $this->pdo()->query('SELECT stock FROM products WHERE id = ' . $first->id)->fetchColumn());
            self::assertSame(1, (int) $this->pdo()->query('SELECT stock FROM products WHERE id = ' . $second->id)->fetchColumn());
            self::assertSame(0, (int) $this->pdo()->query('SELECT COUNT(*) FROM inventory_movements')->fetchColumn());
            self::assertSame(0, (int) $this->pdo()->query('SELECT COUNT(*) FROM store_sales')->fetchColumn());
        }
    }

    public function testRepeatedPosRequestDoesNotDuplicateStockOrCash(): void
    {
        $product = (new ProductRepository($this->pdo()))->create(
            name: 'Producto idempotente',
            sale_price: 12.50,
            cost_price: 5.00,
            stock: 4,
            min_stock: 1,
            sku: 'OPS-004',
        );
        $service = new StoreService($this->pdo());

        $first = $service->sellDirectPOS(
            user_id: 1,
            items_payload: [['product_id' => $product->id, 'quantity' => 1]],
            payment_method: 'cash',
            idempotency_key: 'pos-request-001',
        );
        $second = $service->sellDirectPOS(
            user_id: 1,
            items_payload: [['product_id' => $product->id, 'quantity' => 1]],
            payment_method: 'cash',
            idempotency_key: 'pos-request-001',
        );

        self::assertSame($first->id, $second->id);
        self::assertSame(3, (int) $this->pdo()->query('SELECT stock FROM products WHERE id = ' . $product->id)->fetchColumn());
        self::assertSame(1, (int) $this->pdo()->query('SELECT COUNT(*) FROM store_sales')->fetchColumn());
        self::assertSame(1, (int) $this->pdo()->query('SELECT COUNT(*) FROM cash_movements WHERE reference_type = "store_sale"')->fetchColumn());
    }
}
