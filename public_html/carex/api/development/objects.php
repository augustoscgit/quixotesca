<?php

declare(strict_types=1);

use Carex\Database\Connection;
use Carex\Database\DevelopmentInventoryRepository;
use Carex\Http\Auth;
use Carex\Http\Response;
use Carex\Http\Security;

$config = require dirname(__DIR__, 4) . '/carex' . '/src/bootstrap.php';

Auth::requireApiAdmin();

Security::applyHeaders();
Security::allowReadOnlyRequest();

try {
    $pdo = Connection::make($config['database']);
    $repository = new DevelopmentInventoryRepository($pdo, $config['database']['schema']);

    Response::json($repository->inventory());
} catch (Throwable $error) {
    Response::error($config['app']['debug'] ? $error->getMessage() : 'Erro ao mapear objetos da base.', 500);
}
