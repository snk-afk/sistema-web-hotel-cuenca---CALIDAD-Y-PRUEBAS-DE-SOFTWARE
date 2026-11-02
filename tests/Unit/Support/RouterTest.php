<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\AuthMiddleware;
use App\Support\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
        $_SERVER = ['REQUEST_METHOD' => 'GET'];
        http_response_code(200);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_SERVER = [];
        http_response_code(200);

        parent::tearDown();
    }

    public function testRouterDispatchesRegisteredGetRoute(): void
    {
        $router = new Router();
        $router->get('/test', function () {
            return 'matched_route';
        });

        $result = $router->dispatch('GET', '/test');
        $this->assertEquals('matched_route', $result);
    }

    public function testRouterReturnsNullOnUnmatchedRoute(): void
    {
        $router = new Router();

        ob_start();
        $result = $router->dispatch('GET', '/non-existent-route');
        $output = ob_get_clean();

        $this->assertNull($result);
        $this->assertStringContainsString('Not Found', (string) $output);
        $this->assertEquals(404, http_response_code());
    }

    public function testRouterRedirectsUnauthenticatedUiRouteToLogin(): void
    {
        $router = new Router();
        $router->get('/protected-ui', function (): never {
            $this->fail('Unauthenticated UI route must not execute its handler');
        });
        $router->setAuthMiddleware(new AuthMiddleware());

        ob_start();
        $result = $router->dispatch('GET', '/protected-ui');
        $output = ob_get_clean();

        $this->assertNull($result);
        $this->assertSame('', $output);
        $this->assertEquals(302, http_response_code());
    }

    public function testRouterResolvesDynamicIntegerRouteParameter(): void
    {
        $router = new Router();
        $router->get('/api/invoices/{id}/escpos', static fn(int $id): int => $id);

        $result = $router->dispatch('GET', '/api/invoices/42/escpos');

        self::assertSame(42, $result);
    }

    public function testRouterRejectsNonNumericIntegerRouteParameter(): void
    {
        $router = new Router();
        $router->get('/api/invoices/{id}/escpos', static fn(int $id): int => $id);

        ob_start();
        $result = $router->dispatch('GET', '/api/invoices/not-a-number/escpos');
        $output = (string) ob_get_clean();

        self::assertNull($result);
        self::assertStringContainsString('Not Found', $output);
        self::assertSame(404, http_response_code());
    }
}
