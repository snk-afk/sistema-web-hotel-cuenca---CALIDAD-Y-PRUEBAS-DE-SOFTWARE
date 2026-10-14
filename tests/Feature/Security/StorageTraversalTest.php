<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Controllers\StorageController;
use App\Support\Router;
use PHPUnit\Framework\TestCase;

final class StorageTraversalTest extends TestCase
{
    public function testRouterDoesNotServeArbitraryStoragePath(): void
    {
        $router = new Router();
        $router->get('/storage/comprobantes/{file}', [StorageController::class, 'download']);

        ob_start();
        $router->dispatch('GET', '/storage/../.env');
        $output = (string) ob_get_clean();

        self::assertStringNotContainsString('APP_KEY=', $output);
        self::assertStringContainsString('Not Found', $output);
    }
}
