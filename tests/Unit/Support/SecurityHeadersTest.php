<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SecurityHeaders;
use PHPUnit\Framework\TestCase;

final class SecurityHeadersTest extends TestCase
{
    public function testProductionHttpsHeadersContainRequiredPolicies(): void
    {
        $headers = SecurityHeaders::forRequest('production', true);

        self::assertSame('nosniff', $headers['X-Content-Type-Options']);
        self::assertSame('DENY', $headers['X-Frame-Options']);
        self::assertSame('strict-origin-when-cross-origin', $headers['Referrer-Policy']);
        self::assertArrayHasKey('Content-Security-Policy', $headers);
        self::assertSame('max-age=31536000; includeSubDomains', $headers['Strict-Transport-Security']);
    }

    public function testProductionHeadersAreCaseInsensitiveForEnvironmentName(): void
    {
        self::assertArrayHasKey('Strict-Transport-Security', SecurityHeaders::forRequest('Production', true));
    }

    public function testProductionHttpDoesNotEmitHstsBeforeHttps(): void
    {
        $headers = SecurityHeaders::forRequest('production', false);

        self::assertArrayNotHasKey('Strict-Transport-Security', $headers);
        self::assertArrayHasKey('Content-Security-Policy', $headers);
    }

    public function testNonProductionKeepsBaseSecurityHeadersWithoutHsts(): void
    {
        $headers = SecurityHeaders::forRequest('testing', true);

        self::assertSame('nosniff', $headers['X-Content-Type-Options']);
        self::assertSame('DENY', $headers['X-Frame-Options']);
        self::assertArrayNotHasKey('Strict-Transport-Security', $headers);
    }
}
