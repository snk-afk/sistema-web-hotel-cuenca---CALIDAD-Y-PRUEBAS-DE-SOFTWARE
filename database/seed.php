<?php

declare(strict_types=1);

use App\Database\Database;
use App\Support\Config;
use App\Support\ProductionGuard;
use Database\Seeds\DatabaseSeeder;

require_once __DIR__ . '/../vendor/autoload.php';

Config::load(dirname(__DIR__));

$options = getopt('', ['env:']);
$env = isset($options['env']) && is_string($options['env']) ? $options['env'] : 'development';

if (in_array(strtolower($env), ['production', 'prod'], true)) {
    ProductionGuard::validate();
} else {
    ProductionGuard::validateIfProduction();
}

try {
    $db = Database::connection();
    $seeder = new DatabaseSeeder($db, $env);

    echo "🌱 Iniciando siembra de datos iniciales para Entorno: [" . strtoupper($env) . "]...\n";
    $seeder->run();
    echo "🎉 Poblado de datos iniciales completado exitosamente para [" . strtoupper($env) . "].\n\n";
} catch (\Throwable $e) {
    echo "❌ ERROR FATAL en seeder: " . $e->getMessage() . "\n";
    exit(1);
}
