<?php

declare(strict_types=1);

session_start();

require __DIR__ . '/vendor/autoload.php';

use App\Controllers\ContractController;
use App\Core\ErrorHandler;

ErrorHandler::register();

$controller = new ContractController();
$controller->handleRequest();
