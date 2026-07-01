<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$failures = [];
$testsRun = 0;

function fichario_test_assert(bool $condition, string $message): void
{
    global $failures, $testsRun;
    $testsRun++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function fichario_test_same(mixed $expected, mixed $actual, string $message): void
{
    fichario_test_assert($expected === $actual, $message . ' | esperado=' . var_export($expected, true) . ' atual=' . var_export($actual, true));
}

function fichario_test_contains(string $needle, string $haystack, string $message): void
{
    fichario_test_assert(str_contains($haystack, $needle), $message . ' | trecho ausente=' . $needle);
}

$markdown = platform_markdown_render("| Campo | Valor |\n| --- | ---: |\n| Total | 12 |");
fichario_test_contains('<table', $markdown, 'Markdown renderiza tabela');
fichario_test_contains('<th>Campo</th>', $markdown, 'Markdown renderiza cabecalho de tabela');
fichario_test_contains('style="text-align: right"', $markdown, 'Markdown preserva alinhamento de tabela');

$escaped = platform_markdown_render('<script>alert(1)</script>');
fichario_test_contains('&lt;script&gt;alert(1)&lt;/script&gt;', $escaped, 'Markdown escapa HTML bruto');

$abntArticle = [
    'authors' => 'Doe, John and Smith, Jane',
    'title' => 'Teste de saude',
    'journal' => 'Revista X',
    'volume' => '10',
    'issue' => '2',
    'pages' => '12-20',
    'year' => '2024',
    'doi' => '10.1/test',
    'url' => '',
    'pdf_url' => '',
];
$abnt = build_article_abnt_reference($abntArticle);
fichario_test_contains('DOE, J.; SMITH, J.', $abnt, 'ABNT formata autores');
fichario_test_contains('Teste de saude.', $abnt, 'ABNT inclui titulo');
fichario_test_contains('DOI: 10.1/test.', $abnt, 'ABNT inclui DOI');
fichario_test_same([], article_abnt_missing_fields($abntArticle), 'ABNT sem pendencias com metadados essenciais');

$missing = article_abnt_missing_fields([
    'title' => 'Sem fonte',
    'authors' => '',
    'year' => '',
    'journal' => '',
    'publisher' => '',
    'pages' => '',
    'doi' => '',
    'url' => '',
]);
fichario_test_assert(in_array('autores', $missing, true), 'ABNT aponta autores ausentes');
fichario_test_assert(in_array('periodico/fonte ou editora', $missing, true), 'ABNT aponta fonte ausente');
fichario_test_assert(in_array('DOI ou URL', $missing, true), 'ABNT aponta DOI/URL ausente');

$bibtex = <<<'BIB'
@article{doe2024,
  title = {Teste de saude},
  author = {Doe, John and Smith, Jane},
  journal = {Revista X},
  year = {2024},
  volume = {10},
  number = {2},
  pages = {12--20},
  doi = {10.1/test},
  url = {https://example.org/artigo}
}
BIB;

$parser = new \App\Parsers\BibtexParser();
$parsed = $parser->parse($bibtex);
fichario_test_same('doe2024', $parsed['bibtex_key'] ?? '', 'BibTeX preserva chave');
fichario_test_same('Teste de saude', $parsed['title'] ?? '', 'BibTeX extrai titulo');
fichario_test_same('Doe, John; Smith, Jane', $parsed['authors'] ?? '', 'BibTeX extrai autores');
fichario_test_same('12-20', $parsed['pages'] ?? '', 'BibTeX normaliza intervalo de paginas');

if ($failures !== []) {
    fwrite(STDERR, "Falhas nos testes do Fichario:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "OK: {$testsRun} testes do Fichario passaram.\n";
