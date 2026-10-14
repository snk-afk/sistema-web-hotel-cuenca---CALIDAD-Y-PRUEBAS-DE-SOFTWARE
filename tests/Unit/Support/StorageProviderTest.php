<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\StorageProvider;
use PHPUnit\Framework\TestCase;

final class StorageProviderTest extends TestCase
{
    public function testStoreInvoiceFilesReturnsUrls(): void
    {
        $provider = new StorageProvider();

        $urls = $provider->storeInvoiceFiles('B001-00000001', 'PDF', 'XML', 'VOUCHER');

        $this->assertStringContainsString('B001-00000001.pdf', $urls['pdf_url']);
        $this->assertStringContainsString('B001-00000001.xml', $urls['xml_url']);
        $this->assertStringContainsString('B001-00000001_voucher.pdf', $urls['voucher_url']);
    }
}
