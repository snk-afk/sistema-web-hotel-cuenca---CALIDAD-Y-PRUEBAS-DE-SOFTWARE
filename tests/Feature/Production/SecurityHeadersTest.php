<?php

declare(strict_types=1);

namespace Tests\Feature\Production;

use App\Support\SecurityHeaders;
use PHPUnit\Framework\TestCase;

final class SecurityHeadersTest extends TestCase
{
    public function testProductionResponseContractIncludesTransportAndContentPolicies(): void
    {
        $headers = SecurityHeaders::forRequest('production', true);

        self::assertSame('DENY', $headers['X-Frame-Options']);
        self::assertSame('nosniff', $headers['X-Content-Type-Options']);
        self::assertArrayHasKey('Content-Security-Policy', $headers);
        self::assertArrayHasKey('Strict-Transport-Security', $headers);
    }
}
