<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Gestión de sesión segura y CSRF.
 *
 * Centraliza el acceso a $_SESSION: el user_id siempre proviene de aquí,
 * nunca del request. Genera y verifica tokens CSRF.
 */
final class Session
{
    private const string CSRF_TOKEN_KEY = '_csrf_token';

    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $is_https = self::isHttpsRequest();

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $is_https,
        ]);

        session_start();
        self::$started = true;
    }

    /**
     * Devuelve el ID de usuario autenticado o null si no hay sesión.
     */
    public static function userId(): ?int
    {
        self::ensureStarted();

        $user_id = $_SESSION['user_id'] ?? null;

        if (!is_int($user_id) && !(is_string($user_id) && ctype_digit($user_id))) {
            return null;
        }

        return (int) $user_id;
    }

    /**
     * Devuelve el ID de usuario autenticado o lanza excepción si no hay sesión.
     *
     * @return int El ID del usuario de la sesión.
     * @throws RuntimeException Si no hay sesión activa.
     */
    public static function requireAuth(): int
    {
        $user_id = self::userId();

        if ($user_id === null) {
            throw new RuntimeException('No autenticado');
        }

        return $user_id;
    }

    /**
     * Establece los datos del usuario en sesión tras un login exitoso.
     */
    public static function loginUser(int $user_id, string $username, string $role_name): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role_name;
        $_SESSION[self::CSRF_TOKEN_KEY] = self::generateToken();
    }

    /**
     * Obtiene el token CSRF actual de la sesión, generándolo si no existe.
     */
    public static function csrfToken(): string
    {
        self::ensureStarted();

        $token = $_SESSION[self::CSRF_TOKEN_KEY] ?? null;

        if (!is_string($token) || $token === '') {
            $token = self::generateToken();
            $_SESSION[self::CSRF_TOKEN_KEY] = $token;
        }

        return $token;
    }

    /**
     * Verifica que el token CSRF proporcionado coincide con el de la sesión.
     */
    public static function verifyCsrf(string $token): bool
    {
        $session_token = self::csrfToken();

        if ($token === '' || $session_token === '') {
            return false;
        }

        return hash_equals($session_token, $token);
    }

    /**
     * Regenera el ID de sesión (anti session-fixation).
     */
    public static function regenerate(): void
    {
        self::ensureStarted();
        session_regenerate_id(true);
    }

    /**
     * Destruye la sesión completamente (logout).
     */
    public static function destroy(): void
    {
        self::ensureStarted();

        $_SESSION = [];

        $use_cookies = ini_get('session.use_cookies');
        if ($use_cookies !== false && $use_cookies !== '0' && $use_cookies !== '') {
            $params = session_get_cookie_params();
            $session_name = session_name();
            if (is_string($session_name)) {
                setcookie(
                    $session_name,
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly'],
                );
            }
        }

        session_destroy();
        self::$started = false;
    }

    public static function isHttpsRequest(): bool
    {
        if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443')) {
            return true;
        }

        if (!Config::bool('TRUSTED_PROXY_ENABLED', false)) {
            return false;
        }

        $remote_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $trusted_proxies = Config::string('TRUSTED_PROXIES', '');
        if (!is_string($remote_address) || trim($trusted_proxies) === '') {
            return false;
        }

        $trusted = array_map('trim', explode(',', $trusted_proxies));
        if (!in_array($remote_address, $trusted, true)) {
            return false;
        }

        $forwarded_protocol = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        $protocol = is_string($forwarded_protocol)
            ? strtolower(trim(explode(',', $forwarded_protocol)[0]))
            : '';

        return $protocol === 'https';
    }

    /**
     * Verifica si la petición actual es un mutation (POST/PUT/PATCH/DELETE).
     */
    public static function isMutation(): bool
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        return in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    /**
     * Extrae el token CSRF del header X-CSRF-Token o del body (_csrf_token).
     */
    public static function csrfTokenFromRequest(): string
    {
        $header_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (is_string($header_token) && $header_token !== '') {
            return $header_token;
        }

        $raw_input = file_get_contents('php://input');
        if (is_string($raw_input) && $raw_input !== '') {
            /** @var array<string, mixed>|null $body */
            $body = json_decode($raw_input, true);
            if (is_array($body) && is_string($body['_csrf_token'] ?? null)) {
                return $body['_csrf_token'];
            }
        }

        if (is_string($_POST['_csrf_token'] ?? null)) {
            return $_POST['_csrf_token'];
        }

        return '';
    }

    private static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private static function ensureStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::start();
        }
    }
}
