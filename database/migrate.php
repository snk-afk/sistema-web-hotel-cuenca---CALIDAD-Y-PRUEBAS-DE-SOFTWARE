<?php

declare(strict_types=1);

use App\Database\Database;
use App\Database\Migrator;
use App\Support\Config;
use App\Support\ProductionGuard;

require_once __DIR__ . '/../vendor/autoload.php';

Config::load(dirname(__DIR__));
ProductionGuard::validateIfProduction();

try {
    $db = Database::connection();
    $migrator = new Migrator($db, __DIR__ . '/migrations');

    echo "🚀 Iniciando proceso de migraciones en PHP...\n";
    $executed = $migrator->migrate();

    if ($executed === []) {
        echo "✅ La base de datos ya está actualizada. No hay migraciones pendientes.\n";
    } else {
        echo "🎉 Migraciones ejecutadas exitosamente:\n";
        foreach ($executed as $migration) {
            echo "   - {$migration}\n";
        }
    }
} catch (\Throwable $e) {
    echo "❌ ERROR FATAL en migración: " . $e->getMessage() . "\n";
    exit(1);
}
