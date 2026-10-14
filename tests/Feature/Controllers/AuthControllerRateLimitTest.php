<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Controllers\AuthController;
use App\Repositories\LoginAttemptRepository;
use App\Services\UserService;
use App\Support\Config;
use App\Support\RateLimiter;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

final class AuthControllerRateLimitTest extends TestCase
{
    private PDO $pdo;
    private AuthController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec(
            'CREATE TABLE roles ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, description TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP);'
            . 'CREATE TABLE permissions ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, key_name TEXT NOT NULL, description TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP);'
            . 'CREATE TABLE role_permissions (role_id INTEGER NOT NULL, permission_id INTEGER NOT NULL);'
            . 'CREATE TABLE users ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, role_id INTEGER NOT NULL, username TEXT NOT NULL, '
            . 'email TEXT NOT NULL, password_hash TEXT NOT NULL, full_name TEXT NOT NULL, is_active INTEGER NOT NULL, '
            . 'created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP);'
            . 'CREATE TABLE auth_login_attempts ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, username_hash TEXT NOT NULL, source_hash TEXT NOT NULL, '
            . 'attempted_at TEXT NOT NULL, was_successful INTEGER NOT NULL DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP);'
        );
        $this->pdo->exec("INSERT INTO roles (name, description) VALUES ('admin', 'Administrador')");
        $this->pdo->exec(
            "INSERT INTO users (role_id, username, email, password_hash, full_name, is_active)
             VALUES (1, 'admin', 'admin@example.test', '" . password_hash('valid-password', PASSWORD_DEFAULT) . "', 'Admin', 1)"
        );

        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_POST = ['username' => 'admin', 'password' => 'wrong-password'];
        $_ENV['APP_KEY'] = 'test-application-key-for-controller';
        Config::clear();
        Config::load(dirname(__DIR__, 3));

        $rate_limiter = new RateLimiter(
            db: $this->pdo,
            attempt_repository: new LoginAttemptRepository($this->pdo),
            max_attempts: 2,
            window_seconds: 900,
            clock: new DateTimeImmutable('2026-09-07 12:00:00'),
        );
        $this->controller = new AuthController(new UserService($this->pdo), $rate_limiter);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_SERVER = [];
        unset($_ENV['APP_KEY']);
        Config::clear();
        parent::tearDown();
    }

    public function testThirdFailedLoginReturns429WithoutLeakingAccountDetails(): void
    {
        $this->invokeLogin();
        $this->invokeLogin();

        ob_start();
        $this->controller->login();
        $output = (string) ob_get_clean();

        self::assertSame(429, http_response_code());
        self::assertStringContainsString('Demasiados intentos', $output);
        self::assertStringNotContainsString('wrong-password', $output);
    }

    private function invokeLogin(): void
    {
        ob_start();
        $this->controller->login();
        ob_end_clean();
        http_response_code(200);
    }
}
