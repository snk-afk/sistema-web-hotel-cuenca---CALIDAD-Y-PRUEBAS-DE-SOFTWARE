<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SunatProvider;
use PHPUnit\Framework\TestCase;

final class SunatProviderTest extends TestCase
{
    public function testSendDocumentSimulatedMode(): void
    {
        $provider = new SunatProvider();

        $response = $provider->sendDocument([
            'document_type' => 'BOLETA',
            'full_number' => 'B001-00000001',
            'total' => 100.00,
        ]);

        $this->assertEquals('simulated', $response['status']);
        $this->assertEquals('0000', $response['code']);
    }
}
