<?php

declare(strict_types=1);

use Carex\Http\Auth;
use Carex\Http\Response;
use Carex\Http\Security;

$config = require dirname(__DIR__, 4) . '/carex' . '/src/bootstrap.php';

Auth::requireApiAdmin();

Security::applyHeaders();
Security::allowReadOnlyRequest();

Response::json([
    'schema' => $config['database']['schema'],
    'usuarios' => [],
    'centralized' => true,
    'message' => 'Usuarios, papeis e status sao gerenciados no modulo Acesso.',
    'links' => [
        'usuarios' => '../admin/usuarios.php',
        'papeis' => '../admin/permissoes.php',
    ],
]);
