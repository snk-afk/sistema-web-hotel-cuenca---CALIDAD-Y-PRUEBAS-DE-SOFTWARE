<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Config;
use App\Support\ProductionGuard;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProductionGuardTest extends TestCase
{
    /** @var array<string, string|null> */
    private array $original_values = [];

    protected function setUp(): void
    {
        parent::setUp();
        Config::clear();

        foreach ($this->environmentKeys() as $key) {
            $this->original_values[$key] = $_ENV[$key] ?? null;
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->environmentKeys() as $key) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);

            if ($this->original_values[$key] !== null) {
                $_ENV[$key] = $this->original_values[$key];
                putenv($key . '=' . $this->original_values[$key]);
            }
        }

        Config::clear();
        parent::tearDown();
    }

    public function testRejectsProductionWhenDebugIsEnabled(): void
    {
        $this->loadEnvironment(['APP_DEBUG' => 'true']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_DEBUG');

        ProductionGuard::validate();
    }

    public function testRejectsProductionWhenApplicationUrlIsNotHttps(): void
    {
        $this->loadEnvironment(['APP_URL' => 'http://hotel.example']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_URL');

        ProductionGuard::validate();
    }

    public function testRejectsMissingOrExampleApplicationKey(): void
    {
        foreach (['', 'secret_app_key_32_characters_long_str', str_repeat('a', 31)] as $key) {
            $this->loadEnvironment(['APP_KEY' => $key]);

            try {
                ProductionGuard::validate();
                self::fail('Se esperaba que APP_KEY inválida fuera rechazada.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('APP_KEY', $exception->getMessage());
            }
        }
    }

    public function testRejectsNonMysqlDatabaseDriverOrMissingCredentials(): void
    {
        $this->loadEnvironment([
            'DB_DRIVER' => 'sqlite',
        ]);

        try {
            ProductionGuard::validate();
            self::fail('Se esperaba que el driver no MySQL fuera rechazado.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('DB_DRIVER', $exception->getMessage());
        }

        $this->loadEnvironment([
            'DB_DRIVER' => 'mysql',
            'DB_USERNAME' => '',
            'DB_PASSWORD' => '',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DB_USERNAME');

        ProductionGuard::validate();
    }

    public function testRejectsSimulatedSunatInProduction(): void
    {
        $this->loadEnvironment(['SUNAT_MODE' => 'simulated']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SUNAT_MODE');

        ProductionGuard::validate();
    }

    public function testAcceptsCompleteProductionConfiguration(): void
    {
        $this->loadEnvironment([
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'https://hotel.example',
            'APP_KEY' => bin2hex(random_bytes(32)),
            'DB_DRIVER' => 'mysql',
            'DB_HOST' => 'db.internal',
            'DB_USERNAME' => 'cs_hotel_app',
            'DB_PASSWORD' => 'not-a-real-secret-used-only-in-test',
            'DB_CHARSET' => 'utf8mb4',
            'SUNAT_MODE' => 'real',
            'SUNAT_ENDPOINT' => 'https://sunat.example.test/api',
            'SUNAT_RUC' => '20123456789',
            'SUNAT_SOL_USER' => 'test-user',
            'SUNAT_SOL_PASS' => 'test-password',
            'HEALTH_TOKEN' => str_repeat('h', 32),
            'STORAGE_DRIVER' => 'local',
        ]);

        ProductionGuard::validate();

        self::assertTrue(true);
    }

    /**
     * @param array<string, string> $overrides
     */
    private function loadEnvironment(array $overrides = []): void
    {
        $values = array_merge([
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'https://hotel.example',
            'APP_KEY' => bin2hex(random_bytes(32)),
            'DB_DRIVER' => 'mysql',
            'DB_HOST' => 'db.internal',
            'DB_USERNAME' => 'cs_hotel_app',
            'DB_PASSWORD' => 'not-a-real-secret-used-only-in-test',
            'DB_CHARSET' => 'utf8mb4',
            'SUNAT_MODE' => 'real',
            'SUNAT_ENDPOINT' => 'https://sunat.example.test/api',
            'SUNAT_RUC' => '20123456789',
            'SUNAT_SOL_USER' => 'test-user',
            'SUNAT_SOL_PASS' => 'test-password',
            'STORAGE_DRIVER' => 'local',
        ], $overrides);

        foreach ($values as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }

        Config::clear();
        Config::load(dirname(__DIR__, 3));
    }

    /**
     * @return list<string>
     */
    private function environmentKeys(): array
    {
        return [
            'APP_ENV',
            'APP_DEBUG',
            'APP_URL',
            'APP_KEY',
            'DB_DRIVER',
            'DB_HOST',
            'DB_USERNAME',
            'DB_PASSWORD',
            'DB_CHARSET',
            'SUNAT_MODE',
            'SUNAT_ENDPOINT',
            'SUNAT_RUC',
            'SUNAT_SOL_USER',
            'SUNAT_SOL_PASS',
            'HEALTH_TOKEN',
            'STORAGE_DRIVER',
            'STORAGE_PROVIDER',
        ];
    }
}
