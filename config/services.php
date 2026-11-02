<?php

declare(strict_types=1);

use App\Support\Config;

return [
    'jsonpe' => [
        'enabled' => Config::bool('JSONPE_ENABLED', false),
        'api_token' => Config::string('JSONPE_API_TOKEN', ''),
        'base_url' => Config::string('JSONPE_BASE_URL', 'https://api.jsonpe.com'),
    ],
];
