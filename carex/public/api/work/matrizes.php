<?php

declare(strict_types=1);

use Carex\Database\Connection;
use Carex\Database\WorkRepository;
use Carex\Http\Auth;
use Carex\Http\Response;
use Carex\Http\Security;

$config = require dirname(__DIR__, 3) . '/src/bootstrap.php';

Auth::requireApiLogin();

Security::applyHeaders();
Security::allowReadOnlyRequest();

try {
    $pdo = Connection::make($config['database']);
    $repository = new WorkRepository($pdo);

    Response::json([
        'schema' => $config['database']['schema'],
        'matrizes' => $repository->matrices(),
    ]);
} catch (Throwable $error) {
    Response::error($config['app']['debug'] ? $error->getMessage() : 'Erro ao listar matrizes.', 500);
}
