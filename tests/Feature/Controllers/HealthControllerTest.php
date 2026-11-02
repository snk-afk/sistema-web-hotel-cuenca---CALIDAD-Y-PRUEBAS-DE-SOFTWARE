<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Controllers\HealthController;
use App\Repositories\HealthRepository;
use App\Support\Config;
use PDO;
use PHPUnit\Framework\TestCase;

final class HealthControllerTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $_ENV['HEALTH_TOKEN'] = 'health-test-token';
        Config::clear();
    }

    protected function tearDown(): void
    {
        unset($_ENV['HEALTH_TOKEN'], $_SERVER['HTTP_X_HEALTH_TOKEN']);
        Config::clear();
        parent::tearDown();
    }

    public function testLiveDoesNotRequireDatabaseAndReturnsOk(): void
    {
        ob_start();
        (new HealthController())->live();
        $output = (string) ob_get_clean();

        self::assertSame(200, http_response_code());
        self::assertSame(['status' => 'ok'], json_decode($output, true));
    }

    public function testReadyRequiresToken(): void
    {
        ob_start();
        (new HealthController())->ready();
        $output = (string) ob_get_clean();

        self::assertSame(401, http_response_code());
        self::assertStringNotContainsString('mysql', $output);
        self::assertStringNotContainsString('password', $output);
    }

    public function testReadyReturns503WithoutLeakingDependencyDetails(): void
    {
        $_SERVER['HTTP_X_HEALTH_TOKEN'] = 'health-test-token';
        $repository = new HealthRepository($this->pdo, '/path/that/does/not/exist', '/path/that/does/not/exist');

        ob_start();
        (new HealthController($repository))->ready();
        $output = (string) ob_get_clean();

        self::assertSame(503, http_response_code());
        self::assertStringContainsString('not_ready', $output);
        self::assertStringNotContainsString('does/not/exist', $output);
        self::assertStringNotContainsString('SQLSTATE', $output);
    }
}
