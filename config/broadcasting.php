<?php

declare(strict_types=1);

use App\Support\Config;

return [
    'enabled' => Config::bool('PUSHER_ENABLED', false),
    'app_id' => Config::string('PUSHER_APP_ID', ''),
    'key' => Config::string('PUSHER_APP_KEY', ''),
    'secret' => Config::string('PUSHER_APP_SECRET', ''),
    'cluster' => Config::string('PUSHER_APP_CLUSTER', 'mt1'),
    'host' => Config::string('PUSHER_HOST', '127.0.0.1'),
    'port' => Config::int('PUSHER_PORT', 6001),
    'scheme' => Config::string('PUSHER_SCHEME', 'http'),
];
