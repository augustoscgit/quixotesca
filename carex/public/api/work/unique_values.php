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
    $matrixId = trim((string) ($_GET['id_matriz'] ?? ''));
    $column = trim((string) ($_GET['column'] ?? ''));

    if ($matrixId === '') {
        Response::error('Informe a matriz.', 422);
        return;
    }
    if ($column === '') {
        Response::error('Informe a coluna.', 422);
        return;
    }

    $pdo = Connection::make($config['database']);
    $repository = new WorkRepository($pdo);

    Response::json($repository->matrixUniqueValues($matrixId, $column));
} catch (InvalidArgumentException $error) {
    Response::error($error->getMessage(), 404);
} catch (Throwable $error) {
    Response::error($config['app']['debug'] ? $error->getMessage() : 'Erro ao consultar valores únicos.', 500);
}
