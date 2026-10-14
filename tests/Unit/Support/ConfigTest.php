<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::clear();
    }

    public function testLoadAndGetEnvironmentVariable(): void
    {
        Config::load(dirname(__DIR__, 3));

        $app_name = Config::get('app.name');
        $this->assertNotEmpty($app_name);
        $this->assertEquals('CS-Hotel', $app_name);
    }

    public function testGetFallbackValueWhenKeyDoesNotExist(): void
    {
        Config::load(dirname(__DIR__, 3));

        $non_existent = Config::get('app.non_existent_key', 'default_fallback');
        $this->assertEquals('default_fallback', $non_existent);
    }
}
