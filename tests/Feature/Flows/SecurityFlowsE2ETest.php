<?php

declare(strict_types=1);

namespace Tests\Feature\Flows;

use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Support\AuthException;
use App\Support\AuthMiddleware;
use App\Support\Session;
use Tests\Support\MySqlTestCase;

/**
 * Pruebas E2E de seguridad (SPEC-0036 — CA-1, CA-2, CA-3, CA-4, CA-8, CA-9, CA-11).
 */
final class SecurityFlowsE2ETest extends MySqlTestCase
{
    private UserRepository $user_repo;
    private RoleRepository $role_repo;
    private AuthMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
        $_POST = [];
        $_SERVER = ['REQUEST_METHOD' => 'GET'];

        $this->role_repo = new RoleRepository($this->pdo);
        $this->user_repo = new UserRepository($this->pdo, $this->role_repo);
        $this->middleware = new AuthMiddleware($this->user_repo);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_SERVER = [];
        parent::tearDown();
    }

    /**
     * CA-1: Acceso sin sesión rechazado.
     */
    public function testUnauthenticatedAccessReturns401(): void
    {
        try {
            $this->middleware->check('GET', '/api/rooms');
            $this->fail('Expected AuthException with 401');
        } catch (AuthException $e) {
            $this->assertEquals(401, $e->http_status);
            $this->assertEquals('No autenticado', $e->getMessage());
        }
    }

    /**
     * CA-1: Acceso sin sesión a mutation rechazado.
     */
    public function testUnauthenticatedPostReturns401(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        try {
            $this->middleware->check('POST', '/api/cashbox/open');
            $this->fail('Expected AuthException with 401');
        } catch (AuthException $e) {
            $this->assertEquals(401, $e->http_status);
        }
    }

    /**
     * CA-2: Acceso con sesión pero sin permiso rechazado.
     */
    public function testHousekeeperCannotIssueInvoices(): void
    {
        $housekeeper_role = $this->role_repo->create('housekeeper', 'Mucama');
        $user = $this->user_repo->create($housekeeper_role->id, 'cleaner', 'clean@hotel.com', 'hash', 'Cleaner');

        Session::loginUser($user->id, 'cleaner', 'housekeeper');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = Session::csrfToken();

        try {
            $this->middleware->check('POST', '/api/invoices/issue');
            $this->fail('Expected AuthException with 403');
        } catch (AuthException $e) {
            $this->assertEquals(403, $e->http_status);
            $this->assertEquals('Permiso insuficiente', $e->getMessage());
        }
    }

    /**
     * CA-3: user_id proviene de la sesión, no del request.
     */
    public function testUserIdComesFromSessionNotRequest(): void
    {
        $admin_role = $this->role_repo->create('admin', 'Administrador');
        $user = $this->user_repo->create($admin_role->id, 'admin', 'admin@hotel.com', 'hash', 'Admin');

        Session::loginUser($user->id, 'admin', 'admin');

        // Attacker sends user_id=1 in the body, but session user_id is different
        $_POST['user_id'] = '1';

        $authenticated_user_id = Session::requireAuth();

        // Must return the session user_id, not the body value
        $this->assertEquals($user->id, $authenticated_user_id);
    }

    /**
     * CA-4: CSRF requerido en POST.
     */
    public function testPostWithoutCsrfReturns419(): void
    {
        $admin_role = $this->role_repo->create('admin', 'Administrador');
        $user = $this->user_repo->create($admin_role->id, 'admin', 'admin@hotel.com', 'hash', 'Admin');

        Session::loginUser($user->id, 'admin', 'admin');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        // No CSRF token set in header or body

        try {
            $this->middleware->check('POST', '/api/cashbox/open');
            $this->fail('Expected AuthException with 419');
        } catch (AuthException $e) {
            $this->assertEquals(419, $e->http_status);
            $this->assertEquals('Token CSRF inválido', $e->getMessage());
        }
    }

    /**
     * CA-4: CSRF con token inválido rechazado.
     */
    public function testPostWithInvalidCsrfReturns419(): void
    {
        $admin_role = $this->role_repo->create('admin', 'Administrador');
        $user = $this->user_repo->create($admin_role->id, 'admin', 'admin@hotel.com', 'hash', 'Admin');

        Session::loginUser($user->id, 'admin', 'admin');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong_token';

        try {
            $this->middleware->check('POST', '/api/cashbox/open');
            $this->fail('Expected AuthException with 419');
        } catch (AuthException $e) {
            $this->assertEquals(419, $e->http_status);
        }
    }

    /**
     * CA-4: CSRF con token válido pasa.
     */
    public function testPostWithValidCsrfPasses(): void
    {
        $admin_role = $this->role_repo->create('admin', 'Administrador');
        $user = $this->user_repo->create($admin_role->id, 'admin', 'admin@hotel.com', 'hash', 'Admin');

        Session::loginUser($user->id, 'admin', 'admin');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = Session::csrfToken();

        $result = $this->middleware->check('POST', '/api/cashbox/open');
        $this->assertEquals($user->id, $result);
    }

    /**
     * CA-8: Login establece datos de auditoría en sesión.
     */
    public function testLoginSetsSessionAuditData(): void
    {
        $admin_role = $this->role_repo->create('admin', 'Administrador');
        $user = $this->user_repo->create($admin_role->id, 'admin', 'admin@hotel.com', 'hash', 'Admin');

        Session::loginUser($user->id, 'admin', 'admin');

        $this->assertEquals($user->id, $_SESSION['user_id']);
        $this->assertEquals('admin', $_SESSION['username']);
        $this->assertEquals('admin', $_SESSION['role']);
    }

    /**
     * CA-11: Logout destruye sesión.
     */
    public function testLogoutDestroysSession(): void
    {
        $admin_role = $this->role_repo->create('admin', 'Administrador');
        $user = $this->user_repo->create($admin_role->id, 'admin', 'admin@hotel.com', 'hash', 'Admin');

        Session::loginUser($user->id, 'admin', 'admin');
        $this->assertEquals($user->id, Session::userId());

        Session::destroy();

        // After logout, userId must be null
        $this->assertNull(Session::userId());
    }

    /**
     * CA-2 (edge): Usuario desactivado con sesión activa es rechazado.
     */
    public function testDeactivatedUserWithActiveSessionIsRejected(): void
    {
        $admin_role = $this->role_repo->create('admin', 'Administrador');
        $user = $this->user_repo->create($admin_role->id, 'admin', 'admin@hotel.com', 'hash', 'Admin');

        Session::loginUser($user->id, 'admin', 'admin');

        // Deactivate user in DB
        $stmt = $this->pdo->prepare('UPDATE users SET is_active = 0 WHERE id = :id');
        $stmt->execute(['id' => $user->id]);

        try {
            $this->middleware->check('GET', '/api/rooms');
            $this->fail('Expected AuthException with 403');
        } catch (AuthException $e) {
            $this->assertEquals(403, $e->http_status);
        }
    }

}
