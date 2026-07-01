<?php

declare(strict_types=1);

use Carex\Http\Auth;
use Carex\Http\Response;
use Carex\Http\Security;

$config = require dirname(__DIR__, 4) . '/carex' . '/src/bootstrap.php';
require dirname(__DIR__, 4) . '/acesso/src/documentation.php';

Auth::requireApiAdmin();

Security::applyHeaders();
Security::allowReadOnlyRequest();

try {
    $projectRoot = dirname(__DIR__, 4);
    $docs = platform_docs_scan($projectRoot, [
        [
            'path' => $projectRoot . '/docs',
            'module' => 'Plataforma',
            'prefix' => 'platform/docs',
        ],
        [
            'path' => $projectRoot . '/carex',
            'module' => 'CAREX',
            'prefix' => '',
        ],
    ]);

    $file = trim((string) ($_GET['file'] ?? ''));

    if (($_GET['list'] ?? '') === '1' || $file === '') {
        Response::json([
            'documents' => array_map(static fn (array $doc): array => [
                'name' => $doc['title'],
                'path' => $doc['relative'],
                'desc' => $doc['description'],
                'category' => $doc['category'],
                'module' => $doc['module'],
            ], array_values($docs)),
        ]);
        return;
    }

    $selected = null;
    foreach ($docs as $doc) {
        if ($doc['relative'] === $file) {
            $selected = $doc;
            break;
        }
    }

    if ($selected === null) {
        Response::error('Documento nao autorizado ou inexistente.', 403);
        return;
    }

    if (!is_file($selected['path'])) {
        Response::error('Documento nao encontrado no servidor.', 404);
        return;
    }

    Response::json([
        'file' => $selected['relative'],
        'title' => $selected['title'],
        'content' => (string) file_get_contents($selected['path']),
    ]);
} catch (Throwable $error) {
    Response::error($config['app']['debug'] ? $error->getMessage() : 'Erro ao carregar documento.', 500);
}
