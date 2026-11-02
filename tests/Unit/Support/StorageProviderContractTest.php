<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Config;
use App\Support\StorageProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class StorageProviderContractTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $environment = [];

    protected function setUp(): void
    {
        parent::setUp();
        Config::clear();
        foreach (['STORAGE_DRIVER', 'STORAGE_PROVIDER', 'STORAGE_ROOT', 'STORAGE_LOCAL_PATH', 'STORAGE_COMPROBANTES_PATH'] as $key) {
            $this->environment[$key] = $_ENV[$key] ?? null;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->environment as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
        Config::clear();
        parent::tearDown();
    }

    public function testLocalProviderReturnsUrlsOnlyAfterWritingAllFiles(): void
    {
        $root = sys_get_temp_dir() . '/cs-hotel-storage-' . bin2hex(random_bytes(5));
        $_ENV['STORAGE_DRIVER'] = 'local';
        Config::load(dirname(__DIR__, 3));

        try {
            $urls = (new StorageProvider(root_path: $root))->storeInvoiceFiles(
                'B001-00000001',
                'PDF',
                'XML',
                'VOUCHER',
            );

            self::assertFileExists($root . '/B001-00000001.pdf');
            self::assertFileExists($root . '/B001-00000001.xml');
            self::assertFileExists($root . '/B001-00000001_voucher.pdf');
            self::assertStringContainsString('B001-00000001.pdf', $urls['pdf_url']);
        } finally {
            foreach (glob($root . '/*') ?: [] as $file) {
                unlink($file);
            }
            @rmdir($root);
        }
    }

    public function testR2ProviderReturnsUrlsOnlyAfterEveryUploadIsConfirmed(): void
    {
        $_ENV['STORAGE_DRIVER'] = 'r2';
        Config::load(dirname(__DIR__, 3));
        $uploaded = [];

        $provider = new StorageProvider(
            uploader: static function (string $key, string $content, string $content_type) use (&$uploaded): bool {
                $uploaded[] = [$key, $content, $content_type];
                return true;
            },
        );

        $urls = $provider->storeInvoiceFiles('B001-00000001', 'PDF', 'XML', 'VOUCHER');

        self::assertCount(3, $uploaded);
        self::assertSame('/storage/comprobantes/B001-00000001.pdf', $urls['pdf_url']);
    }

    public function testR2FailureDoesNotReturnFalseUrls(): void
    {
        $_ENV['STORAGE_DRIVER'] = 'r2';
        Config::load(dirname(__DIR__, 3));

        $this->expectException(RuntimeException::class);
        (new StorageProvider(
            uploader: static fn(string $key, string $content, string $content_type): bool => false,
        ))->storeInvoiceFiles('B001-00000001', 'PDF', 'XML', 'VOUCHER');
    }

    public function testConfiguredStorageRootCanLiveOutsideTheRelease(): void
    {
        $root = sys_get_temp_dir() . '/cs-hotel-persistent-storage-' . bin2hex(random_bytes(5));
        $_ENV['STORAGE_ROOT'] = $root;
        Config::load(dirname(__DIR__, 3));

        self::assertSame($root . '/comprobantes', Config::string('storage.comprobantes_path'));
    }
}
