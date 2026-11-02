<?php

declare(strict_types=1);

use App\Support\Config;

return [
    'mode' => Config::string('SUNAT_MODE', 'simulated'),
    'endpoint' => Config::string('SUNAT_ENDPOINT', ''),
    'timeout_seconds' => Config::int('SUNAT_TIMEOUT_SECONDS', 15),
    'ruc' => Config::string('SUNAT_RUC', '20123456789'),
    'sol_user' => Config::string('SUNAT_SOL_USER', 'MODDATOS'),
    'sol_pass' => Config::string('SUNAT_SOL_PASS', 'moddatos'),
    'cert_path' => Config::string('SUNAT_CERT_PATH', ''),
    'cert_pass' => Config::string('SUNAT_CERT_PASS', ''),
];
