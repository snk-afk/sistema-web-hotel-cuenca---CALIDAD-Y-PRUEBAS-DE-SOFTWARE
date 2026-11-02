<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Config;
use App\Support\DocumentLookup;
use PHPUnit\Framework\TestCase;

final class DocumentLookupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::clear();
    }

    public function testLookupReturnsNullWhenDisabled(): void
    {
        Config::load(dirname(__DIR__, 3));

        $lookup = new DocumentLookup();
        $result = $lookup->lookup('DNI', '72819283');

        $this->assertNull($result);
    }
}
