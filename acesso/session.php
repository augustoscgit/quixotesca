<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $user = current_user();
} catch (Throwable $exception) {
    $user = null;
}

if (!$user) {
    echo json_encode([
        'logged_in' => false,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'logged_in' => true,
    'user' => [
        'name' => (string) ($user['name'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
    ],
    'links' => [
        'dashboard' => 'acesso/',
        'users' => 'acesso/usuarios.php',
        'permissions' => 'acesso/permissoes.php',
        'logout' => 'acesso/logout.php',
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
