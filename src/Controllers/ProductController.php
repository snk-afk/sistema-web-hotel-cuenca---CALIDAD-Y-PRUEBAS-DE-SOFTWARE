<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\StoreService;
use InvalidArgumentException;

final class ProductController
{
    private StoreService $store_service;

    public function __construct(?StoreService $store_service = null)
    {
        $this->store_service = $store_service ?? new StoreService();
    }

    public function index(): void
    {
        $products = $this->store_service->getActiveProducts();

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $products,
        ]);
    }

    public function create(): void
    {
        /** @var string|false $raw_input */
        $raw_input = file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : $_POST;

        if (!is_array($body)) {
            $body = [];
        }

        $name = is_string($body['name'] ?? null) ? $body['name'] : '';
        $sale_price = is_numeric($body['sale_price'] ?? null) ? (float) $body['sale_price'] : 0.0;
        $cost_price = is_numeric($body['cost_price'] ?? null) ? (float) $body['cost_price'] : 0.0;
        $stock = is_numeric($body['stock'] ?? null) ? (int) $body['stock'] : 0;
        $min_stock = is_numeric($body['min_stock'] ?? null) ? (int) $body['min_stock'] : 5;
        $sku = is_string($body['sku'] ?? null) ? $body['sku'] : null;
        $description = is_string($body['description'] ?? null) ? $body['description'] : null;

        try {
            $product = $this->store_service->createProduct(
                name: $name,
                sale_price: $sale_price,
                cost_price: $cost_price,
                stock: $stock,
                min_stock: $min_stock,
                sku: $sku,
                description: $description
            );

            http_response_code(201);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Producto registrado exitosamente',
                'data' => $product,
            ]);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
