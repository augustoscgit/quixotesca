<?php

declare(strict_types=1);

use Carex\Database\AdminRepository;
use Carex\Database\Connection;
use Carex\Http\Auth;
use Carex\Http\Response;
use Carex\Http\Security;

$config = require dirname(__DIR__, 3) . '/src/bootstrap.php';

Auth::requireApiLogin();
if (Auth::currentUser()['role'] !== 'admin') {
    Response::error('Forbidden', 403);
    exit;
}

Security::applyHeaders();
Security::allowReadOnlyRequest();

try {
    $pdo = Connection::make($config['database']);
    $repository = new AdminRepository($pdo);

    Response::json([
        'schema' => $config['database']['schema'],
        'usuarios' => $repository->users(),
    ]);
} catch (Throwable $error) {
    Response::error($config['app']['debug'] ? $error->getMessage() : 'Erro ao listar usuários.', 500);
}
