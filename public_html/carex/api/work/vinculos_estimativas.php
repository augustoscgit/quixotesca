<?php

declare(strict_types=1);

use Carex\Database\Connection;
use Carex\Database\WorkRepository;
use Carex\Http\Auth;
use Carex\Http\Response;
use Carex\Http\Security;

$config = require dirname(__DIR__, 4) . '/carex' . '/src/bootstrap.php';

Auth::requireApiLogin();

Security::applyHeaders();
Security::allowReadOnlyRequest();

try {
    $matrixId = trim((string) ($_GET['id_matriz'] ?? ''));

    if ($matrixId === '') {
        Response::error('Informe a matriz.', 422);
        return;
    }

    $pdo = Connection::make($config['database']);
    $repository = new WorkRepository($pdo);

    Response::json($repository->matrixLinkEstimates($matrixId));
} catch (Throwable $error) {
    Response::error($config['app']['debug'] ? $error->getMessage() : 'Erro ao consultar estimativas de vinculos.', 500);
}
