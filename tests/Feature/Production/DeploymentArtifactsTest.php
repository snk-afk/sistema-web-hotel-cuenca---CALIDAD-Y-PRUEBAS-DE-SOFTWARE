<?php

declare(strict_types=1);

namespace Tests\Feature\Production;

use PHPUnit\Framework\TestCase;

final class DeploymentArtifactsTest extends TestCase
{
    private string $project_root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project_root = dirname(__DIR__, 3);
    }

    public function testDeploymentDocumentationAndTemplatesExist(): void
    {
        foreach ([
            'docs/operations/deployment.md',
            'docs/operations/production-runbook.md',
            'docs/operations/hotel-uat-checklist.md',
            'deploy/nginx/cs-hotel.conf.example',
            'deploy/php-fpm/cs-hotel.conf.example',
            'deploy/scripts/deploy.sh',
            'deploy/scripts/backup.sh',
            'deploy/scripts/health-check.sh',
            'deploy/scripts/restore-verify.sh',
            'bin/verify-production-config.php',
        ] as $relative_path) {
            self::assertFileExists($this->project_root . '/' . $relative_path);
            self::assertFileIsReadable($this->project_root . '/' . $relative_path);

            if (str_ends_with($relative_path, '.sh')) {
                self::assertTrue(is_executable($this->project_root . '/' . $relative_path));
            }
        }
    }

    public function testServerTemplatesKeepThePublicRootAndSingleFrontController(): void
    {
        $nginx = file_get_contents($this->project_root . '/deploy/nginx/cs-hotel.conf.example');
        self::assertIsString($nginx);
        self::assertStringContainsString('root /var/www/cs-hotel/current/public;', $nginx);
        self::assertStringContainsString('location = /index.php', $nginx);
        self::assertStringContainsString('location ~ \\.php$', $nginx);
        self::assertStringContainsString('deny all;', $nginx);
        self::assertStringContainsString('return 308 https://$host$request_uri;', $nginx);
    }

    public function testOperationalScriptsFailClosedAndProtectDatabaseRecovery(): void
    {
        foreach ([
            'deploy/scripts/deploy.sh',
            'deploy/scripts/backup.sh',
            'deploy/scripts/health-check.sh',
            'deploy/scripts/restore-verify.sh',
        ] as $relative_path) {
            $script = file_get_contents($this->project_root . '/' . $relative_path);
            self::assertIsString($script);
            self::assertStringContainsString('set -Eeuo pipefail', $script);
        }

        $backup = file_get_contents($this->project_root . '/deploy/scripts/backup.sh');
        $restore = file_get_contents($this->project_root . '/deploy/scripts/restore-verify.sh');
        $deploy = file_get_contents($this->project_root . '/deploy/scripts/deploy.sh');

        self::assertIsString($backup);
        self::assertIsString($restore);
        self::assertIsString($deploy);
        self::assertStringContainsString('--single-transaction', $backup);
        self::assertStringContainsString('railway_restore_', $restore);
        self::assertStringContainsString('bin/verify-production-config.php', $deploy);
        self::assertStringNotContainsString('DROP DATABASE', $restore);
    }

    public function testProductionConfigCliDelegatesToProductionGuard(): void
    {
        $script = file_get_contents($this->project_root . '/bin/verify-production-config.php');
        self::assertIsString($script);
        self::assertStringContainsString('ProductionGuard::validate()', $script);
        self::assertStringContainsString('Production configuration rejected', $script);
    }
}
