<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ErrorHandler;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ErrorHandlerTest extends TestCase
{
    public function testPayloadIsGenericAndContainsRequestId(): void
    {
        $payload = ErrorHandler::payload(
            exception: new RuntimeException('password=super-secret token=hidden'),
            request_id: 'request-123',
            is_debug: false,
        );

        self::assertSame('error', $payload['status']);
        self::assertSame('Ocurrió un error interno.', $payload['message']);
        self::assertSame('request-123', $payload['request_id']);
        self::assertArrayNotHasKey('trace', $payload);
        self::assertArrayNotHasKey('password', $payload);
    }

    public function testDebugPayloadNeverIncludesSensitiveValues(): void
    {
        $payload = ErrorHandler::payload(
            exception: new RuntimeException('password=super-secret token=hidden'),
            request_id: 'request-456',
            is_debug: true,
        );

        self::assertSame('request-456', $payload['request_id']);
        self::assertStringNotContainsString('super-secret', json_encode($payload, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('hidden', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function testLogContextRedactsSecrets(): void
    {
        $context = ErrorHandler::logContext(
            exception: new RuntimeException('password=super-secret token=hidden'),
            request_id: 'request-789',
        );

        self::assertSame('request-789', $context['request_id']);
        self::assertStringNotContainsString('super-secret', json_encode($context, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('hidden', json_encode($context, JSON_THROW_ON_ERROR));
    }
}
