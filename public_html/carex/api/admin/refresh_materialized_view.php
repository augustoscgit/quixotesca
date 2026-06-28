<?php

declare(strict_types=1);

use Carex\Database\AdminRepository;
use Carex\Database\Connection;
use Carex\Http\Auth;
use Carex\Http\Response;
use Carex\Http\Security;

$config = require dirname(__DIR__, 4) . '/carex' . '/src/bootstrap.php';

Auth::requireApiLogin();
if (Auth::currentUser()['role'] !== 'admin') {
    Response::error('Forbidden', 403);
    exit;
}

Security::applyHeaders();
Security::allowMethods(['POST']);

try {
    if (!Connection::writesAllowed($config['database'])) {
        Response::error('Operacao bloqueada: conexao com a base esta em modo somente leitura.', 403);
        return;
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $csrfToken = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

    if (!Security::isValidCsrfToken($csrfToken)) {
        Response::error('Token CSRF invalido.', 403);
        return;
    }

    if ($name === '') {
        Response::error('Informe a view materializada.', 422);
        return;
    }

    $pdo = Connection::make($config['database']);
    $repository = new AdminRepository($pdo);
    $startedAt = microtime(true);
    $repository->refreshMaterializedView($name);

    Response::json([
        'ok' => true,
        'name' => $name,
        'elapsed_seconds' => round(microtime(true) - $startedAt, 3),
    ]);
} catch (InvalidArgumentException $error) {
    Response::error($error->getMessage(), 404);
} catch (Throwable $error) {
    Response::error($config['app']['debug'] ? $error->getMessage() : 'Erro ao atualizar view materializada.', 500);
}
