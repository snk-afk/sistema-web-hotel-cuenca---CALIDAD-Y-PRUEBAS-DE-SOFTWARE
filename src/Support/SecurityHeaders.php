<?php

declare(strict_types=1);

namespace App\Support;

final class SecurityHeaders
{
    /**
     * @return array<string, string>
     */
    public static function forRequest(string $environment, bool $is_https): array
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => "default-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https:; font-src 'self' data: https:; connect-src 'self' https:",
        ];

        if (strtolower($environment) === 'production' && $is_https) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $headers;
    }

    public static function apply(): void
    {
        $environment = Config::string('app.env', 'local');
        foreach (self::forRequest($environment, self::isHttpsRequest()) as $name => $value) {
            header("{$name}: {$value}");
        }
    }

    private static function isHttpsRequest(): bool
    {
        return Session::isHttpsRequest();
    }
}
