<?php

declare(strict_types=1);

use App\Support\Config;

return [
    'driver' => Config::string('DB_DRIVER', 'mysql'),
    'host' => Config::string('DB_HOST', '127.0.0.1'),
    'port' => Config::int('DB_PORT', 3306),
    'database' => Config::string('DB_DATABASE', 'railway'), // <--- Cambiado aquí
    'username' => Config::string('DB_USERNAME', 'root'),
    'password' => Config::string('DB_PASSWORD', ''),
    'charset' => Config::string('DB_CHARSET', 'utf8mb4'),
    'collation' => 'utf8mb4_unicode_ci',
    'test_database' => Config::string('DB_TEST_DATABASE', 'railway_test'), // <--- Y aquí opcionalmente
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
];
