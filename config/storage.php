<?php

declare(strict_types=1);

use App\Support\Config;

$storage_root = Config::string('STORAGE_ROOT', dirname(__DIR__) . '/storage');

return [
    'driver' => Config::string('STORAGE_DRIVER', 'local'),
    'root_path' => $storage_root,
    'local_path' => Config::string('STORAGE_LOCAL_PATH', $storage_root . '/app'),
    'comprobantes_path' => Config::string('STORAGE_COMPROBANTES_PATH', $storage_root . '/comprobantes'),
    'r2' => [
        'account_id' => Config::string('R2_ACCOUNT_ID', ''),
        'access_key_id' => Config::string('R2_ACCESS_KEY_ID', ''),
        'secret_access_key' => Config::string('R2_SECRET_ACCESS_KEY', ''),
        'bucket_name' => Config::string('R2_BUCKET_NAME', ''),
    ],
];
