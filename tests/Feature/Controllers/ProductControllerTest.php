<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Controllers\ProductController;
use App\Repositories\ProductRepository;
use App\Services\StoreService;
use Tests\Support\MySqlTestCase;

final class ProductControllerTest extends MySqlTestCase
{
    private StoreService $store_service;

    protected function setUp(): void
    {
        parent::setUp();

        $repo = new ProductRepository($this->pdo);
        $repo->create('Inca Kola 500ml', 4.00, 2.00, 20);

        $this->store_service = new StoreService($this->pdo);
    }

    public function testIndexReturnsActiveProductsJson(): void
    {
        $controller = new ProductController($this->store_service);

        ob_start();
        $controller->index();
        $output = (string) ob_get_clean();

        $data = json_decode($output, true);

        $this->assertIsArray($data);
        $this->assertEquals('success', $data['status']);
        $this->assertCount(1, $data['data']);
        $this->assertEquals('Inca Kola 500ml', $data['data'][0]['name']);
    }
}
