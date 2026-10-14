<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\Config;
use App\Support\ErrorHandler;
use App\Support\HttpsRedirect;
use App\Support\ProductionGuard;
use App\Support\Router;
use App\Support\SecurityHeaders;

Config::load(dirname(__DIR__));
ErrorHandler::register();
SecurityHeaders::apply();
ProductionGuard::validateIfProduction();
HttpsRedirect::enforceIfRequired();

/** @var Router $router */
$router = require dirname(__DIR__) . '/routes/web.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

$router->dispatch($method, $uri);
