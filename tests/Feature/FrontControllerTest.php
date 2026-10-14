<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Config;
use App\Support\Router;
use PHPUnit\Framework\TestCase;

final class FrontControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::clear();
        Config::load(dirname(__DIR__, 2));
    }

    public function testFrontControllerWebRoutesDispatch(): void
    {
        /** @var Router $router */
        $router = require dirname(__DIR__, 2) . '/routes/web.php';

        ob_start();
        $response = $router->dispatch('GET', '/api/status');
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('CS-Hotel API Active', $output);
    }
}
