<?php

declare(strict_types=1);

use App\Support\Config;

return [
    'name' => Config::string('APP_NAME', 'CS-Hotel'),
    'env' => Config::string('APP_ENV', 'production'),
    'debug' => Config::bool('APP_DEBUG', false),
    'url' => Config::string('APP_URL', 'http://localhost'),
    'timezone' => Config::string('APP_TIMEZONE', 'America/Lima'),
    'key' => Config::string('APP_KEY', ''),
];
