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

    $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1]]);
    $perPage = filter_var($_GET['per_page'] ?? 50, FILTER_VALIDATE_INT, ['options' => ['default' => 50]]);
    $query = trim((string) ($_GET['q'] ?? ''));
    
    $filters = [];
    $filtersRaw = trim((string) ($_GET['filters'] ?? ''));
    if ($filtersRaw !== '') {
        $decoded = json_decode($filtersRaw, true);
        if (is_array($decoded)) {
            $filters = $decoded;
        }
    }

    $pdo = Connection::make($config['database']);
    $repository = new WorkRepository($pdo);

    Response::json($repository->matrixClassifications($matrixId, (int) $page, (int) $perPage, $query, $filters));
} catch (InvalidArgumentException $error) {
    Response::error($error->getMessage(), 404);
} catch (Throwable $error) {
    Response::error($config['app']['debug'] ? $error->getMessage() : 'Erro ao consultar classificações.', 500);
}
