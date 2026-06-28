<?php

declare(strict_types=1);

use Carex\Database\Connection;
use Carex\Database\ReadonlyRepository;
use Carex\Database\SchemaRepository;
use Carex\Http\Auth;
use Carex\Http\Response;
use Carex\Http\Security;

$config = require dirname(__DIR__, 3) . '/carex' . '/src/bootstrap.php';

Auth::requireApiLogin();
if ((Auth::currentUser()['role'] ?? '') !== 'admin') {
    Response::error('Forbidden', 403);
    exit;
}

Security::applyHeaders();
Security::allowReadOnlyRequest();

try {
    $table = trim((string) ($_GET['table'] ?? ''));

    if ($table === '') {
        Response::error('Informe uma tabela.', 422);
        return;
    }

    $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1]]);
    $perPage = filter_var($_GET['per_page'] ?? 50, FILTER_VALIDATE_INT, ['options' => ['default' => 50]]);
    $query = trim((string) ($_GET['q'] ?? ''));
    $sort = trim((string) ($_GET['sort'] ?? ''));
    $direction = trim((string) ($_GET['dir'] ?? 'asc'));
    $filters = [];

    $filtersRaw = trim((string) ($_GET['filters'] ?? ''));
    if ($filtersRaw !== '') {
        $decoded = json_decode($filtersRaw, true);
        if (is_array($decoded)) {
            $filters = $decoded;
        }
    }

    $pdo = Connection::make($config['database']);
    $schema = new SchemaRepository($pdo, $config['database']['schema']);
    $repository = new ReadonlyRepository($pdo, $config['database']['schema'], $schema);

    Response::json($repository->rows($table, (int) $page, (int) $perPage, $query, $sort, $direction, $filters));
} catch (InvalidArgumentException $error) {
    Response::error($error->getMessage(), 404);
} catch (Throwable $error) {
    Response::error($config['app']['debug'] ? $error->getMessage() : 'Erro ao consultar dados.', 500);
}
