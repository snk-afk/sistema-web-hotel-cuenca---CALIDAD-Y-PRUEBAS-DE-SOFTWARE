<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\ProductRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas del stock atómico (SPEC-0036 — CA-5).
 */
final class ProductRepositoryTest extends TestCase
{
    private PDO $pdo;
    private ProductRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec('
            CREATE TABLE products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sku TEXT UNIQUE,
                name TEXT NOT NULL,
                description TEXT,
                sale_price REAL NOT NULL,
                cost_price REAL DEFAULT 0.0,
                stock INTEGER DEFAULT 0,
                min_stock INTEGER DEFAULT 5,
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');

        $this->repo = new ProductRepository($this->pdo);
    }

    public function testDecrementStockSucceedsWhenSufficient(): void
    {
        $product = $this->repo->create('Test Product', 10.00, 5.00, 10, 2);

        $result = $this->repo->decrementStock($product->id, 3);

        $this->assertTrue($result);

        $updated = $this->repo->findById($product->id);
        $this->assertNotNull($updated);
        $this->assertEquals(7, $updated->stock);
    }

    public function testDecrementStockFailsWhenInsufficient(): void
    {
        $product = $this->repo->create('Low Stock Product', 10.00, 5.00, 3, 2);

        // Try to decrement more than available
        $result = $this->repo->decrementStock($product->id, 5);

        $this->assertFalse($result);

        // Stock should remain unchanged
        $updated = $this->repo->findById($product->id);
        $this->assertNotNull($updated);
        $this->assertEquals(3, $updated->stock);
    }

    public function testDecrementStockFailsWhenExactBoundaryExceeded(): void
    {
        $product = $this->repo->create('Exact Stock Product', 10.00, 5.00, 5, 2);

        // Decrement exactly the available amount — should succeed
        $result = $this->repo->decrementStock($product->id, 5);
        $this->assertTrue($result);

        // Try to decrement 1 more — should fail (stock is now 0)
        $result2 = $this->repo->decrementStock($product->id, 1);
        $this->assertFalse($result2);
    }

    public function testDecrementStockIsAtomic(): void
    {
        // CA-5: Two concurrent decrements should not result in negative stock.
        // We simulate this by decrementing the same product twice
        // with quantities that individually fit but together exceed stock.
        $product = $this->repo->create('Race Product', 10.00, 5.00, 3, 2);

        // First decrement of 2 — should succeed (stock becomes 1)
        $result1 = $this->repo->decrementStock($product->id, 2);
        $this->assertTrue($result1);

        // Second decrement of 2 — should fail (stock is now 1, not enough)
        $result2 = $this->repo->decrementStock($product->id, 2);
        $this->assertFalse($result2);

        // Final stock should be 1, not -1
        $updated = $this->repo->findById($product->id);
        $this->assertNotNull($updated);
        $this->assertEquals(1, $updated->stock);
        $this->assertGreaterThanOrEqual(0, $updated->stock, 'Stock must never be negative');
    }
}
