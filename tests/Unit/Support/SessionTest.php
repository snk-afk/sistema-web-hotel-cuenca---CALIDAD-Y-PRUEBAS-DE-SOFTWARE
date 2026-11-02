<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Config;
use App\Support\Session;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas unitarias de Session (SPEC-0036 — CA-3, CA-4, CA-10, CA-11).
 */
final class SessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_SERVER = [];
        parent::tearDown();
    }

    public function testUserIdReturnsNullWhenNoSession(): void
    {
        $this->assertNull(Session::userId());
    }

    public function testRequireAuthThrowsWhenNoSession(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No autenticado');
        Session::requireAuth();
    }

    public function testLoginUserSetsSessionData(): void
    {
        Session::loginUser(5, 'admin', 'admin');

        $this->assertEquals(5, $_SESSION['user_id']);
        $this->assertEquals('admin', $_SESSION['username']);
        $this->assertEquals('admin', $_SESSION['role']);
        $this->assertNotEmpty($_SESSION['_csrf_token']);
    }

    public function testUserIdReturnsIdAfterLogin(): void
    {
        Session::loginUser(7, 'receptionist', 'receptionist');

        $this->assertEquals(7, Session::userId());
    }

    public function testRequireAuthReturnsIdAfterLogin(): void
    {
        Session::loginUser(3, 'accountant', 'accountant');

        $this->assertEquals(3, Session::requireAuth());
    }

    public function testCsrfTokenIsGeneratedOnceAndStable(): void
    {
        Session::loginUser(1, 'admin', 'admin');

        $token1 = Session::csrfToken();
        $token2 = Session::csrfToken();

        $this->assertSame($token1, $token2);
        $this->assertSame(64, strlen($token1));
    }

    public function testVerifyCsrfReturnsTrueForValidToken(): void
    {
        Session::loginUser(1, 'admin', 'admin');

        $token = Session::csrfToken();
        $this->assertTrue(Session::verifyCsrf($token));
    }

    public function testVerifyCsrfReturnsFalseForInvalidToken(): void
    {
        Session::loginUser(1, 'admin', 'admin');

        $this->assertFalse(Session::verifyCsrf('invalid_token'));
        $this->assertFalse(Session::verifyCsrf(''));
    }

    public function testIsMutationReturnsTrueForPost(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->assertTrue(Session::isMutation());
    }

    public function testIsMutationReturnsFalseForGet(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->assertFalse(Session::isMutation());
    }

    public function testCsrfTokenFromRequestReadsHeader(): void
    {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'header_token_123';
        $this->assertEquals('header_token_123', Session::csrfTokenFromRequest());
    }

    public function testCsrfTokenFromRequestReadsBody(): void
    {
        $_POST['_csrf_token'] = 'body_token_456';
        $this->assertEquals('body_token_456', Session::csrfTokenFromRequest());
    }

    public function testCsrfTokenFromRequestReturnsEmptyWhenNotPresent(): void
    {
        $this->assertEquals('', Session::csrfTokenFromRequest());
    }

    public function testUserIdFromSessionIgnoresRequestBody(): void
    {
        // CA-3: user_id comes from session, not request
        Session::loginUser(5, 'admin', 'admin');

        // Simulate attacker sending user_id=1 in the body
        $_POST['user_id'] = '1';

        // Session::userId() must return the session value (5), not the body value (1)
        $this->assertEquals(5, Session::userId());
        $this->assertEquals(5, Session::requireAuth());
    }

    public function testUntrustedForwardedProtocolCannotMarkRequestAsHttps(): void
    {
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        $_ENV['TRUSTED_PROXY_ENABLED'] = 'true';
        $_ENV['TRUSTED_PROXIES'] = '127.0.0.1';
        Config::clear();
        Config::load(dirname(__DIR__, 3));

        self::assertFalse(Session::isHttpsRequest());
    }

    public function testTrustedProxyHttpsProtocolMarksRequestAsSecure(): void
    {
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_ENV['TRUSTED_PROXY_ENABLED'] = 'true';
        $_ENV['TRUSTED_PROXIES'] = '127.0.0.1';
        Config::clear();
        Config::load(dirname(__DIR__, 3));

        self::assertTrue(Session::isHttpsRequest());
    }
}
