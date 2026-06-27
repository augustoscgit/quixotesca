<?php

declare(strict_types=1);

use Carex\Database\Connection;
use Carex\Database\SchemaRepository;
use Carex\Http\Auth;
use Carex\Http\Response;
use Carex\Http\Security;

$config = require dirname(__DIR__, 2) . '/src/bootstrap.php';

Auth::requireApiLogin();
if ((Auth::currentUser()['role'] ?? '') !== 'admin') {
    Response::error('Forbidden', 403);
    exit;
}

Security::applyHeaders();
Security::allowReadOnlyRequest();

try {
    $pdo = Connection::make($config['database']);
    $schema = new SchemaRepository($pdo, $config['database']['schema']);

    Response::json([
        'schema' => $config['database']['schema'],
        'tables' => $schema->tables(),
        'readable_objects' => $schema->readableObjects(),
    ]);
} catch (Throwable $error) {
    Response::error($config['app']['debug'] ? $error->getMessage() : 'Erro ao listar tabelas.', 500);
}
