<?php

declare(strict_types=1);

use Carex\Database\Connection;
use Carex\Database\ReadonlyRepository;
use Carex\Database\SchemaRepository;
use Carex\Http\Auth;
use Carex\Http\Response;
use Carex\Http\Security;

$config = require dirname(__DIR__, 3) . '/carex' . '/src/bootstrap.php';

Auth::requireApiAdmin();

Security::applyHeaders();
Security::allowReadOnlyRequest();

try {
    $table = trim((string) ($_GET['table'] ?? ''));
    $column = trim((string) ($_GET['column'] ?? ''));

    if ($table === '') {
        Response::error('Informe uma tabela.', 422);
        return;
    }
    if ($column === '') {
        Response::error('Informe uma coluna.', 422);
        return;
    }

    $pdo = Connection::make($config['database']);
    $schema = new SchemaRepository($pdo, $config['database']['schema']);
    $repository = new ReadonlyRepository($pdo, $config['database']['schema'], $schema);

    Response::json($repository->uniqueValues($table, $column));
} catch (InvalidArgumentException $error) {
    Response::error($error->getMessage(), 404);
} catch (Throwable $error) {
    Response::error($config['app']['debug'] ? $error->getMessage() : 'Erro ao consultar valores.', 500);
}
