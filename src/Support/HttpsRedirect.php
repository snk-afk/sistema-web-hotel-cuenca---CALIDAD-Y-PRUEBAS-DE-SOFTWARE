<?php

declare(strict_types=1);

namespace App\Support;

final class HttpsRedirect
{
    public static function enforceIfRequired(): void
    {
        $location = self::locationIfRequired();
        if ($location === null) {
            return;
        }

        header('Location: ' . $location, true, 308);
        exit;
    }

    public static function locationIfRequired(): ?string
    {
        $environment = Config::string('app.env', Config::string('APP_ENV', 'local'));
        if (strtolower($environment) !== 'production' || Session::isHttpsRequest()) {
            return null;
        }

        $app_url = Config::string('app.url', Config::string('APP_URL', ''));
        $host = parse_url($app_url, PHP_URL_HOST);
        if (!is_string($host) || $host === '' || preg_match('/\A[A-Za-z0-9.-]+\z/', $host) !== 1) {
            return null;
        }

        $port = parse_url($app_url, PHP_URL_PORT);
        $host_with_port = $host . (is_int($port) ? ':' . $port : '');
        $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
        if (!is_string($request_uri) || $request_uri === '' || preg_match('/[\r\n]/', $request_uri) === 1) {
            $request_uri = '/';
        }
        if (!str_starts_with($request_uri, '/')) {
            $request_uri = '/' . ltrim($request_uri, '/');
        }

        return 'https://' . $host_with_port . $request_uri;
    }
}
