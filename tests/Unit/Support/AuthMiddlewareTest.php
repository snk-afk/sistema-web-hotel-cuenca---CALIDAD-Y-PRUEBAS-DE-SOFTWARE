<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Support\AuthException;
use App\Support\AuthMiddleware;
use App\Support\Session;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas unitarias de AuthMiddleware (SPEC-0036 — CA-1, CA-2, CA-4).
 */
final class AuthMiddlewareTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $user_repo;
    private RoleRepository $role_repo;

    protected function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
        $_POST = [];
        $_SERVER = ['REQUEST_METHOD' => 'GET'];

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->createTables();

        $this->role_repo = new RoleRepository($this->pdo);
        $perm_repo = new PermissionRepository($this->pdo);
        $this->user_repo = new UserRepository($this->pdo, $this->role_repo);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_SERVER = [];
        parent::tearDown();
    }

    public function testPublicRouteDoesNotRequireAuth(): void
    {
        $middleware = new AuthMiddleware($this->user_repo);

        // /api/status is public — should return 0 (no user needed)
        $this->assertEquals(0, $middleware->check('GET', '/api/status'));
    }

    public function testLoginRouteDoesNotRequireAuth(): void
    {
        $middleware = new AuthMiddleware($this->user_repo);

        $this->assertEquals(0, $middleware->check('GET', '/login'));
    }

    public function testUiRouteWithoutSessionRedirectsToLogin(): void
    {
        $middleware = new AuthMiddleware($this->user_repo);

        try {
            $middleware->check('GET', '/');
            $this->fail('Expected unauthenticated UI route to redirect to login');
        } catch (AuthException $e) {
            $this->assertEquals(302, $e->http_status);
            $this->assertEquals('/login', $e->redirect_location);
            $this->assertEquals('No autenticado', $e->getMessage());
        }
    }

    public function testApiRouteWithoutSessionThrows401(): void
    {
        $middleware = new AuthMiddleware($this->user_repo);

        $this->expectException(AuthException::class);
        try {
            $middleware->check('GET', '/api/rooms');
        } catch (AuthException $e) {
            $this->assertEquals(401, $e->http_status);
            throw $e;
        }
    }

    public function testApiRouteWithSessionReturnsUserId(): void
    {
        $admin_role = $this->role_repo->create('admin', 'Administrador');
        $user = $this->user_repo->create($admin_role->id, 'admin', 'admin@hotel.com', 'hash', 'Admin');

        Session::loginUser($user->id, 'admin', 'admin');

        $middleware = new AuthMiddleware($this->user_repo);

        $this->assertEquals($user->id, $middleware->check('GET', '/api/rooms'));
    }

    public function testPostWithoutCsrfThrows419(): void
    {
        $admin_role = $this->role_repo->create('admin', 'Administrador');
        $user = $this->user_repo->create($admin_role->id, 'admin', 'admin@hotel.com', 'hash', 'Admin');

        Session::loginUser($user->id, 'admin', 'admin');

        $_SERVER['REQUEST_METHOD'] = 'POST';

        $middleware = new AuthMiddleware($this->user_repo);

        $this->expectException(AuthException::class);
        try {
            $middleware->check('POST', '/api/cashbox/open');
        } catch (AuthException $e) {
            $this->assertEquals(419, $e->http_status);
            throw $e;
        }
    }

    public function testPostWithValidCsrfPasses(): void
    {
        $admin_role = $this->role_repo->create('admin', 'Administrador');
        $user = $this->user_repo->create($admin_role->id, 'admin', 'admin@hotel.com', 'hash', 'Admin');

        Session::loginUser($user->id, 'admin', 'admin');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = Session::csrfToken();

        $middleware = new AuthMiddleware($this->user_repo);

        $this->assertEquals($user->id, $middleware->check('POST', '/api/cashbox/open'));
    }

    public function testUserWithoutPermissionGets403(): void
    {
        $housekeeper_role = $this->role_repo->create('housekeeper', 'Mucama');
        $user = $this->user_repo->create($housekeeper_role->id, 'cleaner', 'clean@hotel.com', 'hash', 'Cleaner');

        Session::loginUser($user->id, 'cleaner', 'housekeeper');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = Session::csrfToken();

        $middleware = new AuthMiddleware($this->user_repo);

        $this->expectException(AuthException::class);
        try {
            $middleware->check('POST', '/api/invoices/issue');
        } catch (AuthException $e) {
            $this->assertEquals(403, $e->http_status);
            throw $e;
        }
    }

    public function testUserWithoutInvoicePermissionCannotDownloadEscpos(): void
    {
        $housekeeper_role = $this->role_repo->create('housekeeper', 'Mucama');
        $user = $this->user_repo->create($housekeeper_role->id, 'cleaner', 'clean@hotel.com', 'hash', 'Cleaner');

        Session::loginUser($user->id, 'cleaner', 'housekeeper');

        $middleware = new AuthMiddleware($this->user_repo);

        $this->expectException(AuthException::class);
        try {
            $middleware->check('GET', '/api/invoices/1/escpos');
        } catch (AuthException $exception) {
            self::assertSame(403, $exception->http_status);
            throw $exception;
        }
    }

    public function testAdminCanAccessAnyRoute(): void
    {
        $admin_role = $this->role_repo->create('admin', 'Administrador');
        $user = $this->user_repo->create($admin_role->id, 'admin', 'admin@hotel.com', 'hash', 'Admin');

        Session::loginUser($user->id, 'admin', 'admin');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = Session::csrfToken();

        $middleware = new AuthMiddleware($this->user_repo);

        // Admin should pass all permission checks
        $this->assertEquals($user->id, $middleware->check('POST', '/api/invoices/issue'));
    }

    public function testAccountantWithRefundPermissionCanApproveRefund(): void
    {
        $accountant_role = $this->role_repo->create('accountant', 'Contador');
        $permission_repository = new PermissionRepository($this->pdo);
        $permission = $permission_repository->create('reservations.refund', 'Ejecutar devoluciones');
        $this->role_repo->attachPermission($accountant_role->id, $permission->id);
        $user = $this->user_repo->create(
            $accountant_role->id,
            'accountant',
            'accountant@hotel.com',
            'hash',
            'Accountant'
        );

        Session::loginUser($user->id, 'accountant', 'accountant');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = Session::csrfToken();

        $middleware = new AuthMiddleware($this->user_repo);

        self::assertSame(
            $user->id,
            $middleware->check('POST', '/api/reservations/refunds/approve')
        );
    }

    public function testReceptionistWithoutRefundPermissionGets403(): void
    {
        $receptionist_role = $this->role_repo->create('receptionist', 'Recepcionista');
        $user = $this->user_repo->create(
            $receptionist_role->id,
            'receptionist',
            'receptionist@hotel.com',
            'hash',
            'Receptionist'
        );

        Session::loginUser($user->id, 'receptionist', 'receptionist');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = Session::csrfToken();

        $middleware = new AuthMiddleware($this->user_repo);

        $this->expectException(AuthException::class);
        try {
            $middleware->check('POST', '/api/reservations/refunds/approve');
        } catch (AuthException $e) {
            self::assertSame(403, $e->http_status);
            throw $e;
        }
    }

    public function testInvoiceDownloadRequiresInvoicePermissionOnDynamicPath(): void
    {
        $housekeeper_role = $this->role_repo->create('housekeeper', 'Mucama');
        $user = $this->user_repo->create(
            $housekeeper_role->id,
            'housekeeper',
            'housekeeper@hotel.com',
            'hash',
            'Housekeeper'
        );

        Session::loginUser($user->id, 'housekeeper', 'housekeeper');

        $middleware = new AuthMiddleware($this->user_repo);

        $this->expectException(AuthException::class);
        try {
            $middleware->check('GET', '/storage/comprobantes/B001-00000001.pdf');
        } catch (AuthException $exception) {
            self::assertSame(403, $exception->http_status);
            throw $exception;
        }
    }

    private function createTables(): void
    {
        $this->pdo->exec('
            CREATE TABLE roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                description TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE permissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key_name TEXT NOT NULL UNIQUE,
                description TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE role_permissions (
                role_id INTEGER NOT NULL,
                permission_id INTEGER NOT NULL,
                PRIMARY KEY (role_id, permission_id)
            );

            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                role_id INTEGER NOT NULL,
                username TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                full_name TEXT NOT NULL,
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');
    }
}
