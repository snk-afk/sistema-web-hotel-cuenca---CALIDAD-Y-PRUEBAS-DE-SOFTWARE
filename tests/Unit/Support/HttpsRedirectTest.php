<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Config;
use App\Support\HttpsRedirect;
use PHPUnit\Framework\TestCase;

final class HttpsRedirectTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $original = [];

    protected function setUp(): void
    {
        parent::setUp();
        Config::clear();
        foreach (['APP_ENV', 'APP_URL', 'HTTPS', 'SERVER_PORT', 'REQUEST_URI'] as $key) {
            $this->original[$key] = $_ENV[$key] ?? $_SERVER[$key] ?? null;
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    protected function tearDown(): void
    {
        foreach (['APP_ENV', 'APP_URL', 'HTTPS', 'SERVER_PORT', 'REQUEST_URI'] as $key) {
            unset($_ENV[$key], $_SERVER[$key]);
            if ($this->original[$key] !== null) {
                $_ENV[$key] = $this->original[$key];
            }
        }
        Config::clear();
        parent::tearDown();
    }

    public function testBuildsSafeHttpsLocationFromConfiguredHost(): void
    {
        $_ENV['APP_ENV'] = 'production';
        $_ENV['APP_URL'] = 'https://hotel.example';
        $_SERVER['REQUEST_URI'] = '/api/status?check=1';
        $_SERVER['HTTPS'] = 'off';
        $_SERVER['SERVER_PORT'] = '80';
        Config::load(dirname(__DIR__, 3));

        self::assertSame(
            'https://hotel.example/api/status?check=1',
            HttpsRedirect::locationIfRequired(),
        );
    }

    public function testDoesNotRedirectTrustedHttpsRequest(): void
    {
        $_ENV['APP_ENV'] = 'production';
        $_ENV['APP_URL'] = 'https://hotel.example';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTPS'] = 'on';
        Config::load(dirname(__DIR__, 3));

        self::assertNull(HttpsRedirect::locationIfRequired());
    }
}
