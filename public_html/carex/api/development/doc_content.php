<?php

declare(strict_types=1);

use Carex\Http\Auth;
use Carex\Http\Response;
use Carex\Http\Security;

$config = require dirname(__DIR__, 4) . '/carex' . '/src/bootstrap.php';

Auth::requireApiAdmin();

Security::applyHeaders();
Security::allowReadOnlyRequest();

try {
    $file = trim((string) ($_GET['file'] ?? ''));

    if ($file === '') {
        Response::error('Informe o documento.', 422);
        return;
    }

    // Allowlist of existing markdown files to prevent LFI (Local File Inclusion)
    $allowlist = [
        'README.md' => 'README.md',
        'landing.md' => 'landing.md',
        'sobre.md' => 'sobre.md',
        'criterios-conciliacao.md' => 'criterios-conciliacao.md',
        'docs/api.md' => 'docs/api.md',
        'docs/banco-dados.md' => 'docs/banco-dados.md',
        'docs/decisoes-e-pendencias.md' => 'docs/decisoes-e-pendencias.md',
        'docs/modulo-desenvolvimento.md' => 'docs/modulo-desenvolvimento.md',
        'docs/modulo-trabalho.md' => 'docs/modulo-trabalho.md',
        'docs/visao-geral.md' => 'docs/visao-geral.md',
        'docs/migracao_producao.md' => 'docs/migracao_producao.md',
    ];

    if (!isset($allowlist[$file])) {
        Response::error('Documento não autorizado ou inexistente.', 403);
        return;
    }

    $filePath = dirname(__DIR__, 4) . '/carex' . '/' . $allowlist[$file];

    if (!file_exists($filePath)) {
        Response::error('Documento não encontrado no servidor.', 404);
        return;
    }

    $content = file_get_contents($filePath);

    Response::json([
        'file' => $file,
        'title' => basename($file),
        'content' => $content
    ]);

} catch (Throwable $error) {
    Response::error($config['app']['debug'] ? $error->getMessage() : 'Erro ao carregar documento.', 500);
}
