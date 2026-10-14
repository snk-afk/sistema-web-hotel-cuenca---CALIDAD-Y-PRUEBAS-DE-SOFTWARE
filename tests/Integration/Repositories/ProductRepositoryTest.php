<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Repositories\ProductRepository;
use Tests\Support\MySqlTestCase;

final class ProductRepositoryTest extends MySqlTestCase
{
    public function testCreateProductAndUpdateStock(): void
    {
        $repo = new ProductRepository($this->pdo());

        $product = $repo->create('Galletas', 2.50, 1.00, 15, 5, 'SKU123');
        $this->assertEquals('Galletas', $product->name);
        $this->assertEquals(15, $product->stock);

        $repo->updateStock($product->id, 12);
        $reloaded = $repo->findById($product->id);

        $this->assertNotNull($reloaded);
        $this->assertEquals(12, $reloaded->stock);
    }
}
