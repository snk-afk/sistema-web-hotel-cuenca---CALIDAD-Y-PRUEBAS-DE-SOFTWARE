<?php

declare(strict_types=1);

namespace Tests\Feature\Production;

use App\Support\Config;
use App\Support\ProductionGuard;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProductionBootstrapTest extends TestCase
{
    /** @var array<string, string|null> */
    private array $original_values = [];

    protected function setUp(): void
    {
        parent::setUp();
        Config::clear();

        foreach (['APP_ENV', 'APP_DEBUG', 'APP_URL', 'APP_KEY'] as $key) {
            $this->original_values[$key] = $_ENV[$key] ?? null;
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->original_values as $key => $value) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);

            if ($value !== null) {
                $_ENV[$key] = $value;
                putenv($key . '=' . $value);
            }
        }

        Config::clear();
        parent::tearDown();
    }

    public function testNonProductionBootstrapDoesNotApplyProductionGuard(): void
    {
        $this->setEnvironment([
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'true',
            'APP_URL' => 'http://localhost',
            'APP_KEY' => '',
        ]);

        ProductionGuard::validateIfProduction();

        self::assertTrue(true);
    }

    public function testProductionBootstrapAppliesProductionGuard(): void
    {
        $this->setEnvironment([
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'true',
            'APP_URL' => 'http://hotel.example',
            'APP_KEY' => str_repeat('x', 32),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_DEBUG');

        ProductionGuard::validateIfProduction();
    }

    public function testProductionGuardIsCaseInsensitiveForEnvironmentName(): void
    {
        $this->setEnvironment([
            'APP_ENV' => 'Production',
            'APP_DEBUG' => 'true',
            'APP_URL' => 'http://hotel.example',
            'APP_KEY' => str_repeat('x', 32),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_DEBUG');

        ProductionGuard::validateIfProduction();
    }

    /**
     * @param array<string, string> $values
     */
    private function setEnvironment(array $values): void
    {
        foreach ($values as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }

        Config::clear();
        Config::load(dirname(__DIR__, 3));
    }
}
