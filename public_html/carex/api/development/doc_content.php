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

    $projectRoot = dirname(__DIR__, 4);

    // Allowlist of existing markdown files to prevent LFI (Local File Inclusion)
    $allowlist = [
        'platform/docs/bootstrap-first-planejamento.md' => $projectRoot . '/docs/bootstrap-first-planejamento.md',
        'platform/docs/bootstrap-first-exemplos.md' => $projectRoot . '/docs/bootstrap-first-exemplos.md',
        'platform/docs/diretrizes-visuais-renast.md' => $projectRoot . '/docs/diretrizes-visuais-renast.md',
        'platform/docs/documentacao-visual-centralizada.md' => $projectRoot . '/docs/documentacao-visual-centralizada.md',
        'platform/docs/tema-css-bootstrap-modulos.md' => $projectRoot . '/docs/tema-css-bootstrap-modulos.md',
        'README.md' => $projectRoot . '/carex/README.md',
        'landing.md' => $projectRoot . '/carex/landing.md',
        'sobre.md' => $projectRoot . '/carex/sobre.md',
        'criterios-conciliacao.md' => $projectRoot . '/carex/criterios-conciliacao.md',
        'docs/api.md' => $projectRoot . '/carex/docs/api.md',
        'docs/banco-dados.md' => $projectRoot . '/carex/docs/banco-dados.md',
        'docs/decisoes-e-pendencias.md' => $projectRoot . '/carex/docs/decisoes-e-pendencias.md',
        'docs/modulo-desenvolvimento.md' => $projectRoot . '/carex/docs/modulo-desenvolvimento.md',
        'docs/modulo-trabalho.md' => $projectRoot . '/carex/docs/modulo-trabalho.md',
        'docs/visao-geral.md' => $projectRoot . '/carex/docs/visao-geral.md',
        'docs/migracao_producao.md' => $projectRoot . '/carex/docs/migracao_producao.md',
    ];

    if (!isset($allowlist[$file])) {
        Response::error('Documento não autorizado ou inexistente.', 403);
        return;
    }

    $filePath = $allowlist[$file];

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
