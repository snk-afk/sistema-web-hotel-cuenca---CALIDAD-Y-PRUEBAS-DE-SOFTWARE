<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Config;
use App\Support\SunatProvider;
use PHPUnit\Framework\TestCase;

final class SunatProviderContractTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $environment = [];

    protected function setUp(): void
    {
        parent::setUp();
        Config::clear();
        foreach (['SUNAT_MODE', 'SUNAT_ENDPOINT', 'SUNAT_SOL_USER', 'SUNAT_SOL_PASS'] as $key) {
            $this->environment[$key] = $_ENV[$key] ?? null;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->environment as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
        Config::clear();
        parent::tearDown();
    }

    public function testRealModeCannotAcceptWithoutConfiguredProviderResponse(): void
    {
        $_ENV['SUNAT_MODE'] = 'real';
        $_ENV['SUNAT_ENDPOINT'] = '';
        Config::load(dirname(__DIR__, 3));

        $response = (new SunatProvider())
            ->sendDocument(['full_number' => 'B001-00000001']);

        self::assertSame('failed', $response['status']);
        self::assertNotSame('accepted', $response['status']);
    }

    public function testRealModeAcceptsOnlyValidatedProviderResponse(): void
    {
        $_ENV['SUNAT_MODE'] = 'real';
        Config::load(dirname(__DIR__, 3));

        $provider = new SunatProvider(static fn(array $payload): array => [
            'status_code' => 200,
            'body' => '{"status":"accepted","code":"0","message":"Aceptado"}',
        ]);

        $response = $provider->sendDocument(['full_number' => 'B001-00000001']);

        self::assertSame('accepted', $response['status']);
        self::assertSame('0', $response['code']);
    }

    public function testTimeoutAndMalformedResponsesBecomeFailed(): void
    {
        $_ENV['SUNAT_MODE'] = 'real';
        Config::load(dirname(__DIR__, 3));

        $timeout = new SunatProvider(static function (array $payload): array {
            throw new \RuntimeException('timeout');
        });
        self::assertSame('failed', $timeout->sendDocument([])['status']);

        $malformed = new SunatProvider(static fn(array $payload): array => [
            'status_code' => 200,
            'body' => '{"unexpected":true}',
        ]);
        self::assertSame('failed', $malformed->sendDocument([])['status']);
    }
}
