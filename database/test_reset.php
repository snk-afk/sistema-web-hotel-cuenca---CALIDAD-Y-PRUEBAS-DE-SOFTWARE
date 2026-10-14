<?php

declare(strict_types=1);

use App\Database\TestDatabaseManager;
use App\Support\Config;

require_once __DIR__ . '/../vendor/autoload.php';

Config::load(dirname(__DIR__));

try {
    $manager = TestDatabaseManager::fromConfig();
    $manager->assertSafeEnvironment(Config::string('app.env', 'production'));
    $manager->recreate(__DIR__ . '/migrations');

    echo "Base de pruebas reconstruida y migrada correctamente.\n";
} catch (\Throwable $exception) {
    fwrite(STDERR, "No se pudo reconstruir la base de pruebas: {$exception->getMessage()}\n");
    exit(1);
}
