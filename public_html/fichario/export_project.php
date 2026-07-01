<?php
declare(strict_types=1);

require __DIR__ . '/../../fichario/bootstrap.php';
require_login();

$pdo = db();
$projectId = (int) ($_GET['id'] ?? 0);
$currentUser = current_user();
$userId = (int) ($currentUser['id'] ?? 0);

if ($projectId <= 0) {
    http_response_code(404);
    exit('Projeto nao encontrado.');
}

if (!class_exists(ZipArchive::class)) {
    http_response_code(500);
    exit('A extensao ZIP do PHP nao esta habilitada.');
}

function export_fetch_project(PDO $pdo, int $projectId, int $userId): ?array
{
    $sql = 'SELECT * FROM projects WHERE id = :id';
    $params = [':id' => $projectId];

    if (!is_admin()) {
        $sql .= ' AND owner_user_id = :owner_user_id';
        $params[':owner_user_id'] = $userId;
    }

    $stmt = $pdo->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    $project = $stmt->fetch();

    return $project ?: null;
}

function export_text(?string $value): string
{
    $text = trim((string) $value);
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;

    return trim($text);
}

function export_single_line(?string $value): string
{
    return trim((string) preg_replace('/\s+/u', ' ', export_text($value)));
}

function export_slug(string $value, string $fallback = 'projeto'): string
{
    $value = export_single_line($value);
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($converted)) {
            $value = $converted;
        }
    }
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');

    return $value !== '' ? $value : $fallback;
}

function export_decode_json_array(mixed $value): array
{
    if (is_array($value)) {
        return $value;
    }
    if (!is_string($value) || trim($value) === '') {
        return [];
    }

    $decoded = json_decode($value, true);

    return is_array($decoded) ? $decoded : [];
}

function export_split_authors(?string $authors): array
{
    $authors = export_single_line($authors);
    if ($authors === '') {
        return [];
    }

    $normalized = str_ireplace([' and ', ' & '], ';', $authors);
    $parts = array_map('trim', explode(';', $normalized));
    $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));

    return $parts !== [] ? $parts : [$authors];
}

function export_initials(string $givenNames): string
{
    $givenNames = trim(str_replace(',', ' ', $givenNames));
    if ($givenNames === '') {
        return '';
    }

    $particles = ['da', 'das', 'de', 'di', 'do', 'dos', 'e'];
    $initials = [];
    foreach (preg_split('/\s+/u', $givenNames) ?: [] as $part) {
        $clean = trim($part, " .\t\n\r\0\x0B");
        if ($clean === '' || in_array(mb_strtolower($clean, 'UTF-8'), $particles, true)) {
            continue;
        }
        $initials[] = mb_strtoupper(mb_substr($clean, 0, 1, 'UTF-8'), 'UTF-8') . '.';
    }

    return implode(' ', $initials);
}

function export_format_abnt_author(string $author): string
{
    $author = export_single_line($author);
    if ($author === '') {
        return '';
    }

    if (str_contains($author, ',')) {
        [$lastName, $givenNames] = array_pad(array_map('trim', explode(',', $author, 2)), 2, '');
        $lastName = mb_strtoupper($lastName, 'UTF-8');
        $initials = export_initials($givenNames);

        return trim($lastName . ($initials !== '' ? ', ' . $initials : ''));
    }

    $parts = preg_split('/\s+/u', $author) ?: [];
    if (count($parts) === 1) {
        return mb_strtoupper($parts[0], 'UTF-8');
    }

    $suffixes = ['filho', 'junior', 'júnior', 'neto', 'sobrinho'];
    $last = array_pop($parts);
    $previous = $parts[count($parts) - 1] ?? '';
    if ($previous !== '' && in_array(mb_strtolower($last, 'UTF-8'), $suffixes, true)) {
        $last = array_pop($parts) . ' ' . $last;
    }

    $initials = export_initials(implode(' ', $parts));

    return trim(mb_strtoupper($last, 'UTF-8') . ($initials !== '' ? ', ' . $initials : ''));
}

function export_format_abnt_authors(?string $authors): string
{
    $formatted = [];
    foreach (export_split_authors($authors) as $author) {
        $item = export_format_abnt_author($author);
        if ($item !== '') {
            $formatted[] = $item;
        }
    }

    return implode('; ', $formatted);
}

function export_abnt_reference(array $article): string
{
    $authors = export_format_abnt_authors($article['authors'] ?? '');
    $title = rtrim(export_single_line($article['title'] ?? 'Artigo sem titulo'), '.');
    $journal = rtrim(export_single_line($article['journal'] ?? ''), '.');
    $publisher = rtrim(export_single_line($article['publisher'] ?? ''), '.');
    $year = export_single_line((string) ($article['year'] ?? ''));
    $volume = export_single_line($article['volume'] ?? '');
    $issue = export_single_line($article['issue'] ?? '');
    $pages = export_single_line($article['pages'] ?? '');
    $doi = export_single_line($article['doi'] ?? '');
    $url = export_single_line($article['url'] ?? '');

    $parts = [];
    if ($authors !== '') {
        $parts[] = rtrim($authors, '.') . '.';
    }
    $parts[] = $title . '.';
    if ($journal !== '') {
        $parts[] = $journal . '.';
    } elseif ($publisher !== '') {
        $parts[] = $publisher . '.';
    }
    if ($volume !== '') {
        $parts[] = 'v. ' . $volume . '.';
    }
    if ($issue !== '') {
        $parts[] = 'n. ' . $issue . '.';
    }
    if ($pages !== '') {
        $parts[] = 'p. ' . $pages . '.';
    }
    if ($year !== '') {
        $parts[] = $year . '.';
    }
    if ($doi !== '') {
        $parts[] = 'DOI: ' . $doi . '.';
    }
    if ($url !== '') {
        $parts[] = 'Disponivel em: ' . $url . '.';
    }

    return preg_replace('/\s+/u', ' ', implode(' ', $parts)) ?? implode(' ', $parts);
}

function export_citation_label(array $article): string
{
    $authors = export_split_authors($article['authors'] ?? '');
    $first = $authors[0] ?? '';
    if (str_contains($first, ',')) {
        $surname = trim(explode(',', $first, 2)[0]);
    } else {
        $parts = preg_split('/\s+/u', $first) ?: [];
        $surname = (string) end($parts);
    }
    $surname = $surname !== '' ? mb_strtoupper($surname, 'UTF-8') : 'AUTOR';
    $year = export_single_line((string) ($article['year'] ?? 's.d.'));

    return $surname . ', ' . ($year !== '' ? $year : 's.d.');
}

function export_bibtex_escape(?string $value): string
{
    $value = export_text($value);
    $value = str_replace(['\\', '{', '}'], ['\\\\', '\{', '\}'], $value);

    return $value;
}

function export_bibtex_entry(array $article, string $fallbackKey): string
{
    $raw = trim((string) ($article['bibtex_raw'] ?? ''));
    if ($raw !== '') {
        return $raw;
    }

    $key = export_slug((string) ($article['bibtex_key'] ?? ''), $fallbackKey);
    $fields = [
        'title' => $article['title'] ?? '',
        'author' => $article['authors'] ?? '',
        'year' => (string) ($article['year'] ?? ''),
        'journal' => $article['journal'] ?? '',
        'volume' => $article['volume'] ?? '',
        'number' => $article['issue'] ?? '',
        'pages' => $article['pages'] ?? '',
        'publisher' => $article['publisher'] ?? '',
        'doi' => $article['doi'] ?? '',
        'url' => $article['url'] ?? '',
    ];

    $lines = ['@article{' . $key . ','];
    foreach ($fields as $name => $value) {
        $value = export_bibtex_escape((string) $value);
        if ($value !== '') {
            $lines[] = '  ' . $name . ' = {' . $value . '},';
        }
    }
    $lines[] = '}';

    return implode("\n", $lines);
}

function export_csv_cell(mixed $value): string
{
    $value = str_replace(["\r\n", "\r"], "\n", (string) $value);
    $value = str_replace('"', '""', $value);

    return '"' . $value . '"';
}

function export_doi_url(?string $doi): string
{
    $doi = export_single_line($doi);
    if ($doi === '') {
        return '';
    }

    $doi = preg_replace('~^https?://(dx\.)?doi\.org/~i', '', $doi) ?? $doi;

    return 'https://doi.org/' . ltrim($doi, '/');
}

function export_article_search_queries(array $article): array
{
    $title = export_single_line($article['title'] ?? '');
    $authors = export_single_line($article['authors'] ?? '');
    $year = export_single_line((string) ($article['year'] ?? ''));
    $doi = export_single_line($article['doi'] ?? '');
    $journal = export_single_line($article['journal'] ?? '');

    $queries = [];
    if ($doi !== '') {
        $queries[] = $doi;
        $queries[] = '"' . $doi . '" pdf';
    }
    if ($title !== '') {
        $queries[] = '"' . $title . '"';
        $queries[] = '"' . $title . '" pdf';
        $queries[] = '"' . $title . '" "full text"';
    }
    if ($title !== '' && $year !== '') {
        $queries[] = '"' . $title . '" ' . $year;
    }
    if ($title !== '' && $authors !== '') {
        $firstAuthor = export_split_authors($authors)[0] ?? '';
        $queries[] = '"' . $title . '" "' . export_single_line($firstAuthor) . '"';
    }
    if ($title !== '' && $journal !== '') {
        $queries[] = '"' . $title . '" "' . $journal . '"';
    }

    return array_values(array_unique(array_filter($queries, static fn (string $query): bool => trim($query) !== '')));
}

function export_article_access_urls(array $article): array
{
    $urls = [];
    $doiUrl = export_doi_url($article['doi'] ?? '');
    if ($doiUrl !== '') {
        $urls[] = ['type' => 'doi', 'url' => $doiUrl];
    }
    if (export_single_line($article['url'] ?? '') !== '') {
        $urls[] = ['type' => 'article_url', 'url' => export_single_line($article['url'])];
    }
    if (export_single_line($article['pdf_url'] ?? '') !== '') {
        $urls[] = ['type' => 'pdf_url', 'url' => export_single_line($article['pdf_url'])];
    }

    $seen = [];
    $deduped = [];
    foreach ($urls as $url) {
        $key = strtolower((string) $url['url']);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $deduped[] = $url;
    }

    return $deduped;
}

function export_marking_text(array $marking): string
{
    $lines = [];
    $lines[] = '- Marcação #' . (int) $marking['id'] . ' | Artigo ' . $marking['article_key'] . ' | Citacao curta: ' . $marking['citation_label'];
    if (($marking['tags'] ?? []) !== []) {
        $tagNames = array_map(static fn (array $tag): string => (string) ($tag['name'] ?? ''), $marking['tags']);
        $tagNames = array_values(array_filter($tagNames, static fn (string $name): bool => $name !== ''));
        if ($tagNames !== []) {
            $lines[] = '  Tags da marcação: ' . implode('; ', $tagNames);
        }
    }
    if (export_text($marking['quote_text'] ?? '') !== '') {
        $lines[] = '  Citacao literal:';
        foreach (explode("\n", export_text($marking['quote_text'])) as $line) {
            $lines[] = '  > ' . $line;
        }
    }
    if (export_text($marking['comment'] ?? '') !== '') {
        $lines[] = '  Observacao/fichamento:';
        foreach (explode("\n", export_text($marking['comment'])) as $line) {
            $lines[] = '  ' . $line;
        }
    }

    return implode("\n", $lines);
}

function export_agent_context(array $payload): string
{
    $project = $payload['project'];
    $lines = [];
    $lines[] = '# Pacote de contexto para agente de IA';
    $lines[] = '';
    $lines[] = 'Finalidade: apoiar a redacao de relatorio, documento tecnico, artigo ou sintese com base no projeto exportado do Fichario.';
    $lines[] = '';
    $lines[] = '## Regras para o agente';
    foreach (explode("\n", export_text($payload['agent_instructions'] ?? '')) as $instructionLine) {
        $instructionLine = trim($instructionLine);
        if ($instructionLine !== '') {
            $lines[] = $instructionLine;
        }
    }
    $lines[] = '';
    $lines[] = 'Fonte das orientacoes: ' . (string) ($payload['agent_instructions_source'] ?? 'default');
    $lines[] = '';
    $lines[] = '## Projeto';
    $lines[] = 'ID: ' . (int) $project['id'];
    $lines[] = 'Titulo: ' . export_single_line($project['title'] ?? '');
    $lines[] = 'Exportado em: ' . $payload['generated_at'];
    $lines[] = 'Escopo das marcações: somente marcações vinculadas ao projeto.';
    $lines[] = 'Texto completo dos artigos: nao incluido nesta exportacao; use as URLs, DOI, PDF URL e consultas sugeridas para buscar os textos completos quando necessario.';
    $lines[] = '';
    if (export_text($project['description'] ?? '') !== '') {
        $lines[] = '### Descricao do projeto';
        $lines[] = export_text($project['description']);
        $lines[] = '';
    }
    if (($payload['project_tags'] ?? []) !== []) {
        $lines[] = '### Tags do projeto';
        foreach ($payload['project_tags'] as $tag) {
            $label = export_single_line($tag['label'] ?? $tag['name'] ?? '');
            if ($label === '') {
                continue;
            }
            $lines[] = '- Rotulo: ' . $label;
            if (export_single_line($tag['category'] ?? '') !== '') {
                $lines[] = '  Categoria: ' . export_single_line($tag['category']);
            }
            if (export_text($tag['definition'] ?? '') !== '') {
                $lines[] = '  Definicao: ' . export_single_line($tag['definition']);
            }
        }
        $lines[] = '';
    }

    $lines[] = '## Estrutura, contextos e marcações';
    foreach ($payload['sections'] as $section) {
        $lines[] = '';
        $lines[] = '### Secao ' . (int) $section['order'] . ': ' . export_single_line($section['title'] ?? '');
        $lines[] = 'ID da secao: ' . (int) $section['id'];
        if (export_text($section['context'] ?? '') !== '') {
            $lines[] = 'Contexto da secao:';
            $lines[] = export_text($section['context']);
        } else {
            $lines[] = 'Contexto da secao: nao informado.';
        }
        $sectionMarkings = $section['markings'] ?? [];
        $lines[] = 'Marcações vinculadas nesta secao: ' . count($sectionMarkings);
        if ($sectionMarkings === []) {
            $lines[] = '- Nenhuma marcação vinculada.';
            continue;
        }
        foreach ($sectionMarkings as $marking) {
            $lines[] = export_marking_text($marking);
        }
    }

    $lines[] = '';
    $lines[] = '## Artigos citados no projeto';
    foreach ($payload['articles'] as $article) {
        $lines[] = '';
        $lines[] = '### ' . $article['article_key'] . ' - ' . export_single_line($article['title'] ?? 'Artigo sem titulo');
        $lines[] = 'Citacao curta: ' . $article['citation_label'];
        $lines[] = 'Referencia ABNT: ' . $article['reference_abnt'];
        $lines[] = 'Fonte da ABNT: ' . ($article['reference_abnt_source'] ?? 'stored');
        if (export_single_line($article['reference_abnt_missing'] ?? '') !== '') {
            $lines[] = 'Pendencias da ABNT: ' . export_single_line($article['reference_abnt_missing']);
        }
        if (($article['access_urls'] ?? []) !== []) {
            $lines[] = 'Acesso e busca de texto completo:';
            foreach ($article['access_urls'] as $accessUrl) {
                $lines[] = '- ' . strtoupper((string) $accessUrl['type']) . ': ' . (string) $accessUrl['url'];
            }
        }
        if (($article['search_queries'] ?? []) !== []) {
            $lines[] = 'Consultas sugeridas:';
            foreach (array_slice($article['search_queries'], 0, 5) as $query) {
                $lines[] = '- ' . $query;
            }
        }
        if (export_text($article['abstract'] ?? '') !== '') {
            $lines[] = 'Resumo: ' . export_text($article['abstract']);
        }
        if (export_text($article['analysis'] ?? '') !== '') {
            $lines[] = 'Analise cadastrada: ' . export_text($article['analysis']);
        }
        if (export_text($article['keywords'] ?? '') !== '') {
            $lines[] = 'Palavras-chave: ' . export_single_line($article['keywords']);
        }
        if (export_text($article['references_text'] ?? '') !== '') {
            $lines[] = 'Referencias declaradas no artigo:';
            $lines[] = export_text($article['references_text']);
        }
    }

    $lines[] = '';
    $lines[] = '## Referencias ABNT';
    foreach ($payload['articles'] as $article) {
        $lines[] = '- ' . $article['reference_abnt'];
    }
    $lines[] = '';

    return implode("\n", $lines) . "\n";
}

function export_article_context(array $article): string
{
    $lines = [];
    $lines[] = '# ' . export_single_line($article['title'] ?? 'Artigo sem titulo');
    $lines[] = '';
    $lines[] = 'Chave no pacote: ' . $article['article_key'];
    $lines[] = 'Citacao curta: ' . $article['citation_label'];
    $lines[] = 'Referencia ABNT: ' . $article['reference_abnt'];
    $lines[] = 'Fonte da ABNT: ' . ($article['reference_abnt_source'] ?? 'stored');
    if (export_single_line($article['reference_abnt_missing'] ?? '') !== '') {
        $lines[] = 'Pendencias da ABNT: ' . export_single_line($article['reference_abnt_missing']);
    }
    $lines[] = '';
    $fields = [
        'Autores' => 'authors',
        'Ano' => 'year',
        'Periodico' => 'journal',
        'Volume' => 'volume',
        'Numero' => 'issue',
        'Paginas' => 'pages',
        'Editora' => 'publisher',
        'DOI' => 'doi',
        'URL' => 'url',
        'PDF URL' => 'pdf_url',
        'Palavras-chave' => 'keywords',
    ];
    foreach ($fields as $label => $field) {
        $value = export_single_line((string) ($article[$field] ?? ''));
        if ($value !== '') {
            $lines[] = $label . ': ' . $value;
        }
    }
    if (($article['access_urls'] ?? []) !== []) {
        $lines[] = '';
        $lines[] = '## Acesso e busca de texto completo';
        foreach ($article['access_urls'] as $accessUrl) {
            $lines[] = '- ' . strtoupper((string) $accessUrl['type']) . ': ' . (string) $accessUrl['url'];
        }
    }
    if (($article['search_queries'] ?? []) !== []) {
        $lines[] = '';
        $lines[] = '## Consultas sugeridas';
        foreach ($article['search_queries'] as $query) {
            $lines[] = '- ' . $query;
        }
    }
    if (export_text($article['abstract'] ?? '') !== '') {
        $lines[] = '';
        $lines[] = '## Resumo';
        $lines[] = export_text($article['abstract']);
    }
    if (export_text($article['analysis'] ?? '') !== '') {
        $lines[] = '';
        $lines[] = '## Analise cadastrada';
        $lines[] = export_text($article['analysis']);
    }
    if (export_text($article['references_text'] ?? '') !== '') {
        $lines[] = '';
        $lines[] = '## Referencias declaradas no artigo';
        $lines[] = export_text($article['references_text']);
    }

    return implode("\n", $lines) . "\n";
}

function export_retrieval_guide(array $payload): string
{
    $lines = [];
    $lines[] = '# Guia de busca de textos completos e PDFs';
    $lines[] = '';
    $lines[] = 'Use este arquivo quando a redacao exigir verificacao externa, leitura integral do artigo ou recuperacao do PDF.';
    $lines[] = '';
    $lines[] = '## Ordem recomendada de busca';
    $lines[] = '1. Abrir DOI, se houver.';
    $lines[] = '2. Abrir URL original do artigo, se houver.';
    $lines[] = '3. Abrir PDF URL, se houver.';
    $lines[] = '4. Usar as consultas sugeridas em bases abertas e buscadores academicos.';
    $lines[] = '5. Registrar no relatorio quando o texto completo nao for encontrado.';
    $lines[] = '';
    $lines[] = '## Cuidados';
    $lines[] = '- Nao use fontes sem relacao clara com o artigo.';
    $lines[] = '- Confira titulo, autores e ano antes de incorporar informacoes externas.';
    $lines[] = '- Nao substitua uma referencia ABNT travada por metadados externos sem aviso do usuario.';
    $lines[] = '- Se houver divergencia entre Fichario e fonte externa, aponte a divergencia.';
    $lines[] = '';
    $lines[] = '## Artigos e rotas de acesso';

    foreach ($payload['articles'] as $article) {
        $lines[] = '';
        $lines[] = '### ' . $article['article_key'] . ' - ' . export_single_line($article['title'] ?? 'Artigo sem titulo');
        $lines[] = 'Referencia ABNT: ' . $article['reference_abnt'];
        $lines[] = 'Texto completo armazenado no Fichario: ' . (((int) ($article['full_text_char_count'] ?? 0)) > 0 ? 'sim, mas nao incluido neste pacote' : 'nao identificado');
        if (($article['access_urls'] ?? []) !== []) {
            $lines[] = 'URLs diretas:';
            foreach ($article['access_urls'] as $accessUrl) {
                $lines[] = '- ' . strtoupper((string) $accessUrl['type']) . ': ' . (string) $accessUrl['url'];
            }
        } else {
            $lines[] = 'URLs diretas: nenhuma cadastrada.';
        }
        if (($article['search_queries'] ?? []) !== []) {
            $lines[] = 'Consultas sugeridas:';
            foreach ($article['search_queries'] as $query) {
                $lines[] = '- ' . $query;
            }
        }
    }

    return implode("\n", $lines) . "\n";
}

function export_articles_index_csv(array $articles): string
{
    $headers = [
        'article_key',
        'title',
        'authors',
        'year',
        'journal',
        'doi',
        'doi_url',
        'url',
        'pdf_url',
        'reference_abnt',
        'reference_abnt_source',
        'reference_abnt_missing',
        'full_text_available_in_fichario',
        'search_query_1',
        'search_query_2',
        'search_query_3',
    ];
    $rows = [implode(',', array_map('export_csv_cell', $headers))];

    foreach ($articles as $article) {
        $queries = $article['search_queries'] ?? [];
        $row = [
            $article['article_key'] ?? '',
            $article['title'] ?? '',
            $article['authors'] ?? '',
            $article['year'] ?? '',
            $article['journal'] ?? '',
            $article['doi'] ?? '',
            $article['doi_url'] ?? '',
            $article['url'] ?? '',
            $article['pdf_url'] ?? '',
            $article['reference_abnt'] ?? '',
            $article['reference_abnt_source'] ?? '',
            $article['reference_abnt_missing'] ?? '',
            ((int) ($article['full_text_char_count'] ?? 0)) > 0 ? 'yes' : 'no',
            $queries[0] ?? '',
            $queries[1] ?? '',
            $queries[2] ?? '',
        ];
        $rows[] = implode(',', array_map('export_csv_cell', $row));
    }

    return implode("\n", $rows) . "\n";
}

$project = export_fetch_project($pdo, $projectId, $userId);
if ($project === null) {
    http_response_code(404);
    exit('Projeto nao encontrado.');
}

$projectTagsStmt = $pdo->prepare('
    SELECT t.id, t.name, t.definition, t.category
    FROM project_tags pt
    JOIN tags t ON t.id = pt.tag_id
    WHERE pt.project_id = :project_id
    ORDER BY lower(t.category) ASC, lower(t.name) ASC
');
$projectTagsStmt->execute([':project_id' => $projectId]);
$projectTags = $projectTagsStmt->fetchAll() ?: [];
foreach ($projectTags as &$projectTag) {
    $projectTag['label'] = $projectTag['name'] ?? '';
}
unset($projectTag);

$sectionStmt = $pdo->prepare('
    SELECT id, project_id, title, context, position, created_at, updated_at
    FROM project_sections
    WHERE project_id = :project_id
    ORDER BY position ASC, id ASC
');
$sectionStmt->execute([':project_id' => $projectId]);
$sections = $sectionStmt->fetchAll() ?: [];

$markingsStmt = $pdo->prepare("
    SELECT
        psn.section_id,
        psn.note_id AS marking_id,
        psn.position AS marking_position,
        psn.created_at AS linked_at,
        q.id,
        q.quote_text,
        q.comment,
        q.created_at AS marking_created_at,
        q.updated_at AS marking_updated_at,
        COALESCE((
            SELECT json_agg(json_build_object(
                'id', t.id,
                'name', t.name,
                'definition', t.definition,
                'category', t.category
            ) ORDER BY lower(t.category), lower(t.name))
            FROM article_quote_tags qt
            JOIN tags t ON t.id = qt.tag_id
            WHERE qt.quote_id = q.id
        ), '[]'::json) AS tags_json,
        a.id AS article_id,
        a.title,
        a.authors,
        a.year,
        a.journal,
        a.volume,
        a.issue,
        a.pages,
        a.publisher,
        a.doi,
        a.url,
        a.pdf_url,
        a.abstract,
        a.references_text,
        a.keywords,
        a.bibtex_key,
        a.bibtex_raw,
        a.reference_abnt,
        a.reference_abnt_locked,
        a.reference_abnt_missing,
        length(trim(COALESCE(a.full_text, ''))) AS full_text_char_count,
        a.analysis,
        a.data_year_start,
        a.data_year_end,
        a.created_at AS article_created_at,
        a.updated_at AS article_updated_at
    FROM project_section_notes psn
    JOIN project_sections s ON s.id = psn.section_id
    JOIN article_tag_quotes q ON q.id = psn.note_id
    JOIN articles a ON a.id = q.article_id
    WHERE s.project_id = :project_id
    ORDER BY s.position ASC, s.id ASC, psn.position ASC, psn.note_id ASC
");
$markingsStmt->execute([':project_id' => $projectId]);
$linkedRows = $markingsStmt->fetchAll() ?: [];

$markingsBySection = [];
$articlesById = [];
foreach ($linkedRows as $row) {
    $articleId = (int) $row['article_id'];
    if (!isset($articlesById[$articleId])) {
        $articlesById[$articleId] = [
            'id' => $articleId,
            'title' => $row['title'],
            'authors' => $row['authors'],
            'year' => $row['year'],
            'journal' => $row['journal'],
            'volume' => $row['volume'],
            'issue' => $row['issue'],
            'pages' => $row['pages'],
            'publisher' => $row['publisher'],
            'doi' => $row['doi'],
            'url' => $row['url'],
            'pdf_url' => $row['pdf_url'],
            'abstract' => $row['abstract'],
            'references_text' => $row['references_text'],
            'keywords' => $row['keywords'],
            'bibtex_key' => $row['bibtex_key'],
            'bibtex_raw' => $row['bibtex_raw'],
            'reference_abnt' => $row['reference_abnt'] ?? '',
            'reference_abnt_locked' => $row['reference_abnt_locked'] ?? false,
            'reference_abnt_missing' => $row['reference_abnt_missing'] ?? '',
            'full_text_char_count' => (int) ($row['full_text_char_count'] ?? 0),
            'analysis' => $row['analysis'],
            'data_year_start' => $row['data_year_start'],
            'data_year_end' => $row['data_year_end'],
            'created_at' => $row['article_created_at'],
            'updated_at' => $row['article_updated_at'],
            'marking_ids' => [],
        ];
    }
    $articlesById[$articleId]['marking_ids'][] = (int) $row['marking_id'];

    $markingsBySection[(int) $row['section_id']][] = [
        'id' => (int) $row['marking_id'],
        'position' => (int) $row['marking_position'],
        'linked_at' => $row['linked_at'],
        'quote_text' => $row['quote_text'],
        'comment' => $row['comment'],
        'created_at' => $row['marking_created_at'],
        'updated_at' => $row['marking_updated_at'],
        'tags' => export_decode_json_array($row['tags_json']),
        'article_id' => $articleId,
    ];
}

$articles = array_values($articlesById);
usort($articles, static function (array $left, array $right): int {
    return export_single_line((string) ($left['title'] ?? '')) <=> export_single_line((string) ($right['title'] ?? ''));
});

$articleKeysById = [];
foreach ($articles as $index => &$article) {
    $article['article_key'] = 'A' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
    $article['citation_label'] = export_citation_label($article);
    $storedReference = export_text($article['reference_abnt'] ?? '');
    $article['reference_abnt_source'] = $storedReference !== '' ? 'stored' : 'generated';
    $article['reference_abnt'] = $storedReference !== '' ? $storedReference : export_abnt_reference($article);
    $article['doi_url'] = export_doi_url($article['doi'] ?? '');
    $article['access_urls'] = export_article_access_urls($article);
    $article['search_queries'] = export_article_search_queries($article);
    $article['marking_ids'] = array_values(array_unique(array_map('intval', $article['marking_ids'] ?? [])));
    $articleKeysById[(int) $article['id']] = $article['article_key'];
}
unset($article);

$articlesById = [];
foreach ($articles as $article) {
    $articlesById[(int) $article['id']] = $article;
}

$payloadSections = [];
foreach ($sections as $index => $section) {
    $sectionId = (int) $section['id'];
    $sectionMarkings = $markingsBySection[$sectionId] ?? [];
    foreach ($sectionMarkings as &$marking) {
        $article = $articlesById[(int) $marking['article_id']] ?? [];
        $marking['article_key'] = $article['article_key'] ?? ('artigo-' . (int) $marking['article_id']);
        $marking['citation_label'] = $article['citation_label'] ?? 'AUTOR, s.d.';
    }
    unset($marking);

    $payloadSections[] = [
        'id' => $sectionId,
        'order' => $index + 1,
        'title' => $section['title'],
        'context' => $section['context'],
        'position' => (int) $section['position'],
        'created_at' => $section['created_at'],
        'updated_at' => $section['updated_at'],
        'markings' => $sectionMarkings,
    ];
}

$generatedAt = gmdate('Y-m-d\TH:i:s\Z');
$agentInstructions = effective_project_agent_instructions($project);
$defaultAgentInstructions = default_project_agent_instructions();
$customAgentInstructions = trim((string) ($project['agent_instructions'] ?? ''));
$agentInstructionsSource = ($customAgentInstructions !== '' && $customAgentInstructions !== trim($defaultAgentInstructions))
    ? 'project_custom'
    : 'system_default';
$payload = [
    'export_version' => 'fichario-agent-project-v1',
    'generated_at' => $generatedAt,
    'intended_consumer' => 'AI agent / RAG / report drafting workflow',
    'agent_instructions' => $agentInstructions,
    'agent_instructions_source' => $agentInstructionsSource,
    'scope' => [
        'project_id' => $projectId,
        'markings' => 'only markings linked to this project sections',
        'article_full_text' => 'excluded; retrieval metadata and direct URLs are included',
        'anonymization' => 'none',
    ],
    'project' => $project,
    'project_tags' => $projectTags,
    'sections' => $payloadSections,
    'articles' => $articles,
];

$manifest = [
    'package' => 'fichario-project-agent-export',
    'version' => 1,
    'generated_at' => $generatedAt,
    'project_id' => $projectId,
    'project_title' => $project['title'] ?? '',
    'entrypoint' => 'AGENT_CONTEXT.md',
    'files' => [
        'AGENT_CONTEXT.md' => 'Primary context file for the AI agent.',
        'SOURCE_RETRIEVAL_GUIDE.md' => 'Step-by-step guide for finding full texts and PDFs.',
        'project_export.json' => 'Structured export preserving sections, contexts, markings, articles and ABNT references.',
        'articles_index.csv' => 'Flat article index with DOI, URLs, PDF URLs, ABNT status and search queries.',
        'source_retrieval.json' => 'Machine-readable retrieval checklist for cited articles.',
        'references_abnt.txt' => 'ABNT references for articles cited by linked markings.',
        'references.bib' => 'BibTeX records from stored metadata, when available.',
        'articles/*.md' => 'One support file per cited article, without full_text.',
    ],
];

$slug = export_slug((string) ($project['title'] ?? 'projeto'));
$tmpFile = tempnam(sys_get_temp_dir(), 'fichario_ai_export_');
if ($tmpFile === false) {
    http_response_code(500);
    exit('Nao foi possivel criar arquivo temporario.');
}

$zip = new ZipArchive();
if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    @unlink($tmpFile);
    http_response_code(500);
    exit('Nao foi possivel criar o pacote ZIP.');
}

$jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
$zip->addFromString('manifest.json', json_encode($manifest, $jsonFlags) . "\n");
$zip->addFromString('project_export.json', json_encode($payload, $jsonFlags) . "\n");
$zip->addFromString('AGENT_CONTEXT.md', export_agent_context($payload));
$zip->addFromString('SOURCE_RETRIEVAL_GUIDE.md', export_retrieval_guide($payload));
$zip->addFromString('articles_index.csv', export_articles_index_csv($articles));

$retrievalPayload = [
    'generated_at' => $generatedAt,
    'project_id' => $projectId,
    'project_title' => $project['title'] ?? '',
    'instructions' => [
        'Prioritize DOI, article URL and PDF URL before broad title searches.',
        'Use only legal, verifiable sources for full text and PDFs.',
        'Confirm title, authors and year before using external full-text information.',
        'Record when full text or PDF was not found.',
    ],
    'articles' => array_map(static function (array $article): array {
        return [
            'article_key' => $article['article_key'] ?? '',
            'title' => $article['title'] ?? '',
            'citation_label' => $article['citation_label'] ?? '',
            'reference_abnt' => $article['reference_abnt'] ?? '',
            'reference_abnt_source' => $article['reference_abnt_source'] ?? '',
            'reference_abnt_missing' => $article['reference_abnt_missing'] ?? '',
            'doi' => $article['doi'] ?? '',
            'doi_url' => $article['doi_url'] ?? '',
            'url' => $article['url'] ?? '',
            'pdf_url' => $article['pdf_url'] ?? '',
            'access_urls' => $article['access_urls'] ?? [],
            'search_queries' => $article['search_queries'] ?? [],
            'full_text_available_in_fichario' => ((int) ($article['full_text_char_count'] ?? 0)) > 0,
            'full_text_included_in_package' => false,
        ];
    }, $articles),
];
$zip->addFromString('source_retrieval.json', json_encode($retrievalPayload, $jsonFlags) . "\n");

$abntLines = array_map(static fn (array $article): string => $article['reference_abnt'], $articles);
$zip->addFromString('references_abnt.txt', implode("\n\n", $abntLines) . "\n");

$bibEntries = [];
foreach ($articles as $article) {
    $bibEntries[] = export_bibtex_entry($article, strtolower((string) $article['article_key']));
}
$zip->addFromString('references.bib', implode("\n\n", $bibEntries) . "\n");

foreach ($articles as $article) {
    $articleFile = 'articles/' . strtolower((string) $article['article_key']) . '-' . export_slug((string) ($article['title'] ?? ''), 'artigo') . '.md';
    $zip->addFromString($articleFile, export_article_context($article));
}

$zip->close();

$downloadName = $slug . '-pacote-agente-' . gmdate('Ymd-His') . '.zip';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/zip');
header('Content-Length: ' . (string) filesize($tmpFile));
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

readfile($tmpFile);
@unlink($tmpFile);
exit;
