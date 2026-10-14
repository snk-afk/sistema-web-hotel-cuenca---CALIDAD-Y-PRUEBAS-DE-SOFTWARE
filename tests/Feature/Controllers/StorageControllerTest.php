<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Controllers\StorageController;
use App\Support\Config;
use App\Support\StorageProvider;
use PHPUnit\Framework\TestCase;

final class StorageControllerTest extends TestCase
{
    private string $storage_root;
    private mixed $storage_driver;

    protected function setUp(): void
    {
        parent::setUp();
        http_response_code(200);
        $this->storage_root = sys_get_temp_dir() . '/cs-hotel-storage-' . bin2hex(random_bytes(6));
        $this->storage_driver = $_ENV['STORAGE_DRIVER'] ?? null;
        mkdir($this->storage_root, 0700, true);
        file_put_contents($this->storage_root . '/B001-00000001.pdf', 'valid-pdf-content');
    }

    protected function tearDown(): void
    {
        $valid_file = $this->storage_root . '/B001-00000001.pdf';
        if (is_file($valid_file)) {
            unlink($valid_file);
        }
        if (is_dir($this->storage_root)) {
            rmdir($this->storage_root);
        }
        http_response_code(200);
        if ($this->storage_driver === null) {
            unset($_ENV['STORAGE_DRIVER']);
        } else {
            $_ENV['STORAGE_DRIVER'] = $this->storage_driver;
        }
        Config::clear();
        parent::tearDown();
    }

    public function testDownloadsOnlyValidComprobanteFileFromAllowlistedRoot(): void
    {
        $controller = new StorageController($this->storage_root);

        ob_start();
        $controller->download('B001-00000001.pdf');
        $output = (string) ob_get_clean();

        self::assertSame('valid-pdf-content', $output);
        self::assertSame(200, http_response_code());
    }

    public function testRejectsTraversalAndInvalidFileNames(): void
    {
        $controller = new StorageController($this->storage_root);

        ob_start();
        $controller->download('../.env');
        $output = (string) ob_get_clean();

        self::assertStringNotContainsString('APP_KEY', $output);
        self::assertSame(400, http_response_code());
    }

    public function testReturnsNotFoundForMissingComprobante(): void
    {
        $controller = new StorageController($this->storage_root);

        ob_start();
        $controller->download('B001-99999999.pdf');
        $output = (string) ob_get_clean();

        self::assertStringContainsString('no encontrado', $output);
        self::assertSame(404, http_response_code());
    }

    public function testR2DownloadsThroughProtectedProviderContract(): void
    {
        $_ENV['STORAGE_DRIVER'] = 'r2';
        Config::clear();
        Config::load(dirname(__DIR__, 3));
        $provider = new StorageProvider(
            root_path: $this->storage_root,
            downloader: static fn(string $file): array => [
                'content' => 'private-r2-content',
                'content_type' => 'application/pdf',
            ],
        );
        $controller = new StorageController($this->storage_root, $provider);

        ob_start();
        $controller->download('B001-00000001.pdf');
        $output = (string) ob_get_clean();

        self::assertSame('private-r2-content', $output);
        self::assertSame(200, http_response_code());
    }
}
