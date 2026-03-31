<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Controllers\ContractController;
use App\Core\Env;
use App\Core\ErrorHandler;

Env::load(__DIR__ . '/.env');

session_set_cookie_params([
    'httponly' => true,
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Lax',
]);

session_start();

ErrorHandler::register();

$controller = new ContractController();
$controller->handleRequest();
