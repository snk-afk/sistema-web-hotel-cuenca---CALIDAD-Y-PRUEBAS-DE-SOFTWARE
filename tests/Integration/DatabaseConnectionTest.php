<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Database\Database;
use App\Support\Config;
use PDO;
use PHPUnit\Framework\TestCase;

final class DatabaseConnectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::clear();
        Config::load(dirname(__DIR__, 2));
    }

    public function testDatabaseConnectionConfigurator(): void
    {
        $db_driver = Config::get('database.driver');
        $this->assertEquals('mysql', $db_driver);
    }
}
