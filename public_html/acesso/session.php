<?php

declare(strict_types=1);

require __DIR__ . '/../../acesso/src/bootstrap.php';

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

$isAdmin = false;
try {
    $isAdmin = platform_is_admin($user);
} catch (Throwable $e) {}

echo json_encode([
    'logged_in' => true,
    'user' => [
        'name' => (string) ($user['name'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
        'is_admin' => $isAdmin,
    ],
    'links' => [
        'dashboard' => 'acesso/',
        'admin' => $isAdmin ? 'admin/index.php' : null,
        'users' => $isAdmin ? 'admin/usuarios.php' : null,
        'permissions' => $isAdmin ? 'admin/permissoes.php' : null,
        'logout' => 'acesso/logout.php',
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
