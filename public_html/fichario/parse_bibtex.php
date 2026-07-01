<?php
declare(strict_types=1);

require __DIR__ . '/../../fichario/bootstrap.php';
require_editor();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo nao permitido.']);
    exit;
}

require_csrf();

$bibtex = trim((string) ($_POST['bibtex'] ?? ''));

if ($bibtex === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Cole uma referencia BibTeX antes de importar.']);
    exit;
}

if (strlen($bibtex) > 100000) {
    http_response_code(413);
    echo json_encode(['error' => 'A referencia BibTeX esta muito grande para importacao.']);
    exit;
}

$parser = new \App\Parsers\BibtexParser();
$article = $parser->parse($bibtex);
$article['reference_abnt'] = build_article_abnt_reference($article);
$article['reference_abnt_missing'] = implode('; ', article_abnt_missing_fields($article));
$article['reference_abnt_locked'] = '0';

if ($article['title'] === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Nao consegui identificar um titulo nessa referencia BibTeX.']);
    exit;
}

echo json_encode(['article' => $article], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
