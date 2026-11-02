<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use PHPUnit\Framework\TestCase;

final class DevelopmentRouterSecurityTest extends TestCase
{
    public function testDevelopmentRouterRestrictsStaticFilesToPublicRoot(): void
    {
        $router = (string) file_get_contents(dirname(__DIR__, 3) . '/router.php');

        self::assertStringContainsString('rawurldecode($raw_path)', $router);
        self::assertStringContainsString("realpath(\$public_root . DIRECTORY_SEPARATOR . ltrim(\$uri, '/'))", $router);
        self::assertStringContainsString('str_starts_with($file, $public_root . DIRECTORY_SEPARATOR)', $router);
        self::assertStringNotContainsString("__DIR__ . '/public' . \$uri", $router);
    }
}
