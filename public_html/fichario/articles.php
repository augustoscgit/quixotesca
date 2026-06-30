<?php
declare(strict_types=1);

require __DIR__ . '/../../fichario/bootstrap.php';

// Session filter persistence
if (isset($_GET['clear'])) {
    unset($_SESSION['articles_filter']);
    header('Location: articles.php');
    exit;
}

$hasFilterInput = false;
foreach ($_GET as $key => $val) {
    if ($key !== 'page') {
        $hasFilterInput = true;
        break;
    }
}

if ($hasFilterInput) {
    $_SESSION['articles_filter'] = $_GET;
} else {
    if (isset($_SESSION['articles_filter'])) {
        $params = $_SESSION['articles_filter'];
        if (isset($_GET['page'])) {
            $params['page'] = $_GET['page'];
        }
        header('Location: articles.php?' . http_build_query($params));
        exit;
    }
}
session_write_close();

$pdo = db();
$q = trim((string) ($_GET['q'] ?? ''));
$yearFrom = trim((string) ($_GET['year_from'] ?? ''));
$yearTo = trim((string) ($_GET['year_to'] ?? ''));
$journal = trim((string) ($_GET['journal'] ?? ''));
$selectedTagIds = isset($_GET['tag_ids']) ? array_map('intval', (array) $_GET['tag_ids']) : [];
$selectedProjectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : 0;
$content = trim((string) ($_GET['content'] ?? ''));
$sort = trim((string) ($_GET['sort'] ?? 'year_desc'));
$status = trim((string) ($_GET['status'] ?? ''));

if (!is_logged_in()) {
    if ($content === 'with_text' || $content === 'without_text') {
        $content = '';
    }
    if ($sort === 'text_desc') {
        $sort = 'year_desc';
    }
}

$currentUser = current_user();
$userId = $currentUser ? (int) ($currentUser['id'] ?? 0) : 0;
$projects = [];
if ($userId > 0) {
    $projectsSql = 'SELECT id, title FROM projects';
    $projectsParams = [];
    if (!is_admin()) {
        $projectsSql .= ' WHERE owner_user_id = :user_id';
        $projectsParams[':user_id'] = $userId;
    }
    $projectsSql .= ' ORDER BY lower(title) ASC';
    $projectsStmt = $pdo->prepare($projectsSql);
    $projectsStmt->execute($projectsParams);
    $projects = $projectsStmt->fetchAll() ?: [];
}

$tokens = array_values(array_filter(preg_split('/\s+/', $q) ?: [], fn($token) => mb_strlen($token, 'UTF-8') >= 2));

$where = [];
$params = [];
$scoreParts = ['0'];

if ($tokens !== []) {
    foreach ($tokens as $index => $token) {
        $param = ':q' . $index;
        $like = '%' . search_normalize($token) . '%';
        $params[$param] = $like;

        $fieldsToSearch = [
            "search_norm(title) LIKE $param",
            "search_norm(authors) LIKE $param",
            "search_norm(journal) LIKE $param",
            "search_norm(keywords) LIKE $param",
            "search_norm(abstract) LIKE $param",
            "search_norm(references_text) LIKE $param"
        ];
        if (is_logged_in()) {
            $fieldsToSearch[] = "search_norm(full_text) LIKE $param";
        }
        $where[] = "(" . implode(' OR ', $fieldsToSearch) . ")";

        $scoreParts[] = "(CASE WHEN search_norm(title) LIKE $param THEN 40 ELSE 0 END)";
        $scoreParts[] = "(CASE WHEN search_norm(keywords) LIKE $param THEN 30 ELSE 0 END)";
        $scoreParts[] = "(CASE WHEN search_norm(authors) LIKE $param THEN 20 ELSE 0 END)";
        $scoreParts[] = "(CASE WHEN search_norm(journal) LIKE $param THEN 16 ELSE 0 END)";
        $scoreParts[] = "(CASE WHEN search_norm(abstract) LIKE $param THEN 10 ELSE 0 END)";
        if (is_logged_in()) {
            $scoreParts[] = "(CASE WHEN search_norm(full_text) LIKE $param THEN 5 ELSE 0 END)";
        }
        $scoreParts[] = "(CASE WHEN search_norm(references_text) LIKE $param THEN 2 ELSE 0 END)";
    }
}

if ($yearFrom !== '' && preg_match('/^\d{4}$/', $yearFrom)) {
    $where[] = 'year >= :year_from';
    $params[':year_from'] = (int) $yearFrom;
}

if ($yearTo !== '' && preg_match('/^\d{4}$/', $yearTo)) {
    $where[] = 'year <= :year_to';
    $params[':year_to'] = (int) $yearTo;
}

if ($journal !== '') {
    $where[] = 'journal = :journal';
    $params[':journal'] = $journal;
}

// Hierarchical tag filtering (CTE recursive descendants) for multiple tags
if ($selectedTagIds !== []) {
    foreach ($selectedTagIds as $idx => $tId) {
        $paramName = ':tag_id_' . $idx;
        $where[] = "id IN (
            WITH RECURSIVE descendants(id) AS (
                SELECT CAST($paramName AS integer)
                UNION
                SELECT child_id FROM tag_hierarchy th
                JOIN descendants d ON th.parent_id = d.id
            )
            SELECT article_id FROM article_tags WHERE tag_id IN (SELECT id FROM descendants)
            UNION
            SELECT q.article_id
            FROM article_tag_quotes q
            JOIN article_quote_tags qt ON qt.quote_id = q.id
            WHERE qt.tag_id IN (SELECT id FROM descendants)
        )";
        $params[$paramName] = $tId;
    }
}

// Project filtering
if ($selectedProjectId > 0) {
    $where[] = "id IN (
        SELECT DISTINCT q.article_id
        FROM article_tag_quotes q
        JOIN project_section_notes psn ON psn.note_id = q.id
        JOIN project_sections s ON s.id = psn.section_id
        WHERE s.project_id = :project_id
    )";
    $params[':project_id'] = $selectedProjectId;
}

if ($content === 'with_text') {
    $where[] = "length(trim(COALESCE(full_text, ''))) > 0";
} elseif ($content === 'without_text') {
    $where[] = "length(trim(COALESCE(full_text, ''))) = 0";
} elseif ($content === 'with_references') {
    $where[] = "length(trim(COALESCE(references_text, ''))) > 0";
} elseif ($content === 'without_references') {
    $where[] = "length(trim(COALESCE(references_text, ''))) = 0";
}

if ($status === 'fichado') {
    $where[] = article_has_notes_sql('articles');
} elseif ($status === 'cadastrado') {
    $where[] = 'NOT ' . article_has_notes_sql('articles');
}

$orderOptions = [
    'recent' => 'id DESC',
    'title_asc' => 'lower(title) ASC',
    'year_desc' => 'year DESC, lower(title) ASC',
    'year_asc' => 'year ASC, lower(title) ASC',
    'source_asc' => 'lower(journal) ASC, year DESC',
];

if (is_logged_in()) {
    $orderOptions['text_desc'] = 'full_text_length DESC';
}

if ($tokens !== []) {
    $orderOptions['relevance'] = 'relevance DESC, year DESC, lower(title) ASC';
    if (!isset($_GET['sort'])) {
        $sort = 'relevance';
    }
}

if (!isset($orderOptions[$sort])) {
    $sort = $tokens !== [] ? 'relevance' : 'year_desc';
}

$whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
$scoreSql = implode(' + ', $scoreParts);

// Count total matching articles for pagination
$countSql = "SELECT COUNT(*) FROM articles $whereSql";
$countStmt = $pdo->prepare($countSql);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$countStmt->execute();
$totalArticles = (int) $countStmt->fetchColumn();

$limit = 10;
$totalPages = (int) ceil($totalArticles / $limit);
if ($totalPages < 1) {
    $totalPages = 1;
}

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
} elseif ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $limit;

$sql = "
    SELECT
        id,
        title,
        authors,
        year,
        journal,
        abstract,
        keywords,
        full_text,
        references_text,
        pdf_url,
        url,
        data_year_start,
        data_year_end,
        length(COALESCE(full_text, '')) AS full_text_length,
        length(COALESCE(references_text, '')) AS references_length,
        ($scoreSql) AS relevance,
        " . article_has_notes_sql('articles') . " AS is_fichado
    FROM articles
    $whereSql
    ORDER BY {$orderOptions[$sort]}
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$articles = $stmt->fetchAll();

// Pagination URL helper
function get_pagination_url(int $targetPage): string {
    $getParams = $_GET;
    $getParams['page'] = $targetPage;
    return 'articles.php?' . http_build_query($getParams);
}

// Fetch journals for filtering list
$journals = $pdo
    ->query("SELECT journal FROM (SELECT DISTINCT journal FROM articles WHERE journal IS NOT NULL AND trim(journal) <> '') AS sub ORDER BY lower(journal) ASC")
    ->fetchAll(PDO::FETCH_COLUMN);

function render_article_list_alerts_button(array $articleTags): string
{
    $hasMetodo = false;
    $hasFonte = false;
    $hasObjetivo = false;
    $isEnsaioOuRevisao = false;

    foreach ($articleTags as $tag) {
        $catNormalized = search_normalize($tag['category'] ?? '');
        $nameNormalized = search_normalize(trim($tag['name'] ?? ''));
        if ($catNormalized === 'metodo') {
            $hasMetodo = true;
            if (str_contains($nameNormalized, 'ensaio') || str_contains($nameNormalized, 'revisao')) {
                $isEnsaioOuRevisao = true;
            }
        }
        if ($catNormalized === 'fonte') {
            $hasFonte = true;
        }
        if ($nameNormalized === 'objetivo' || (int)($tag['id'] ?? 0) === 44) {
            $hasObjetivo = true;
        }
    }

    $missing = [];
    if (!$hasMetodo) {
        $missing[] = 'tag de Método';
    }
    if (!$hasFonte && !$isEnsaioOuRevisao) {
        $missing[] = 'tag de Fonte';
    }
    if (!$hasObjetivo) {
        $missing[] = 'tag Objetivo';
    }

    $hasAlerts = count($missing) > 0;
    
    if ($hasAlerts) {
        $tooltipText = '<strong>Faltam tags no artigo:</strong><br>';
        foreach ($missing as $item) {
            $tooltipText .= '• ' . $item . '<br>';
        }
    } else {
        $tooltipText = 'Todos os requisitos de fichamento cumpridos.';
    }

    ob_start();
    ?>
    <div class="d-inline-block" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="bottom" title="<?= h($tooltipText) ?>">
        <button class="btn <?= $hasAlerts ? 'btn-alert-active' : 'btn-alert-inactive' ?> rounded-circle d-flex align-items-center justify-content-center"
               
                <?= $hasAlerts ? '' : 'disabled' ?>>
            !
        </button>
    </div>
    <?php
    return (string) ob_get_clean();
}

function article_tag_category_rank(?string $category): int
{
    return match (search_normalize((string) $category)) {
        'metodo' => 1,
        'fonte' => 2,
        'tema' => 3,
        default => 4,
    };
}

function article_tag_ancestor_names(int $tagId, array $parentsByChild, array $tagNamesById, array $visited = []): array
{
    if (isset($visited[$tagId])) {
        return [];
    }
    $visited[$tagId] = true;

    $names = [];
    foreach (($parentsByChild[$tagId] ?? []) as $parentId) {
        if (isset($tagNamesById[$parentId])) {
            $names[] = $tagNamesById[$parentId];
        }
        $names = array_merge($names, article_tag_ancestor_names((int) $parentId, $parentsByChild, $tagNamesById, $visited));
    }

    return array_values(array_unique($names));
}

function article_tag_ancestor_ids(int $tagId, array $parentsByChild, array $visited = []): array
{
    if (isset($visited[$tagId])) {
        return [];
    }
    $visited[$tagId] = true;

    $ids = [];
    foreach (($parentsByChild[$tagId] ?? []) as $parentId) {
        $parentId = (int) $parentId;
        $ids[] = $parentId;
        $ids = array_merge($ids, article_tag_ancestor_ids($parentId, $parentsByChild, $visited));
    }

    return array_values(array_unique($ids));
}

// Fetch all available tags for selection (with category)
$allTags = $pdo->query('SELECT * FROM tags ORDER BY lower(name) ASC')->fetchAll();
$tagNamesById = [];
foreach ($allTags as $tag) {
    $tagNamesById[(int) $tag['id']] = (string) ($tag['name'] ?? '');
}
$parentsByChild = [];
foreach ($pdo->query('SELECT parent_id, child_id FROM tag_hierarchy')->fetchAll() ?: [] as $relation) {
    $parentsByChild[(int) $relation['child_id']][] = (int) $relation['parent_id'];
}
$ancestorNamesByTagId = [];
$ancestorIdsByTagId = [];
foreach (array_keys($tagNamesById) as $tagId) {
    $ancestorNamesByTagId[$tagId] = article_tag_ancestor_names((int) $tagId, $parentsByChild, $tagNamesById);
    $ancestorIdsByTagId[$tagId] = article_tag_ancestor_ids((int) $tagId, $parentsByChild);
}

usort($allTags, static function (array $left, array $right): int {
    $leftRank = article_tag_category_rank($left['category'] ?? '');
    $rightRank = article_tag_category_rank($right['category'] ?? '');
    if ($leftRank !== $rightRank) {
        return $leftRank <=> $rightRank;
    }

    return search_normalize((string) ($left['name'] ?? '')) <=> search_normalize((string) ($right['name'] ?? ''));
});

// Group tags by category for optgroup rendering
$tagsByCategory = [];
foreach ($allTags as $tag) {
    $cat = trim((string) ($tag['category'] ?? ''));
    $tagsByCategory[$cat][] = $tag;
}
uksort($tagsByCategory, static function (string $left, string $right): int {
    $leftRank = article_tag_category_rank($left);
    $rightRank = article_tag_category_rank($right);
    if ($leftRank !== $rightRank) {
        return $leftRank <=> $rightRank;
    }

    return search_normalize($left) <=> search_normalize($right);
});

$categoryColors = [
    'Tema'   => ['css_bg' => 'var(--tag-tema-bg)', 'css_text' => 'var(--tag-tema-text)'],
    'Método' => ['css_bg' => 'var(--tag-metodo-bg)', 'css_text' => 'var(--tag-metodo-text)'],
    'Fonte'  => ['css_bg' => 'var(--tag-fonte-bg)', 'css_text' => 'var(--tag-fonte-text)'],
];

// Batch tag fetch statement removed to resolve N+1 query and run in batch

function first_match_snippet(array $article, array $tokens): string
{
    if ($tokens === []) {
        return '';
    }

    $fields = ['title', 'keywords', 'abstract', 'references_text'];
    if (is_logged_in()) {
        $fields[] = 'full_text';
    }

    foreach ($fields as $field) {
        $text = (string) ($article[$field] ?? '');
        if ($text === '') {
            continue;
        }

        foreach ($tokens as $token) {
            $position = mb_stripos(search_normalize($text), search_normalize($token), 0, 'UTF-8');
            if ($position !== false) {
                $start = max(0, $position - 90);
                $snippet = mb_substr($text, $start, 220, 'UTF-8');
                $prefix = $start > 0 ? '...' : '';
                $suffix = ($start + 220) < mb_strlen($text, 'UTF-8') ? '...' : '';

                return $prefix . trim(preg_replace('/\s+/', ' ', $snippet) ?? $snippet) . $suffix;
            }
        }
    }

    return '';
}

function selected_attr(string $current, string $value): string
{
    return $current === $value ? ' selected' : '';
}
?>
<!doctype html>
<html lang="pt-br" data-module="fichario">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Artigos - Fichário Acadêmico</title>
    <link rel="icon" type="image/png" href="../assets/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="assets/app.css?v=20260629-vanilla" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="../assets/js/theme-switcher.js?v=20260629-vanilla"></script>
<link href="../assets/css/style.css?v=20260629-vanilla" rel="stylesheet">
</head>
<body>
    <!-- Background Animated Blobs -->


    <?php render_navbar('articles'); ?>

    <main class="container py-4 main-container">
        <!-- Breadcrumbs -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Fichário</a></li>
                <li class="breadcrumb-item active text-body" aria-current="page">Artigos</li>
            </ol>
        </nav>

        <!-- Top heading -->
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
            <div>
                <h1 class="h3 mb-1 text-body fw-bold">Artigos Acadêmicos</h1>
                <p class="text-secondary mb-0">Busca inteligente, filtragem temática e leitura do acervo bibliográfico.</p>
            </div>
            <?php if (can_edit_content()): ?>
                <div>
                    <a class="btn btn-primary rounded-pill px-4" href="editor.php">
                        + Novo Artigo
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Filter Form -->
        <form class="card article-filter-card p-4 mb-4" method="get">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="q">Busca inteligente</label>
                    <input class="form-control" type="search" name="q" id="q" value="<?= h($q) ?>" placeholder="Título, autor, tema, resumo, trechos de fichamento...">
                </div>

                <?php $selectedTagIdsMap = array_fill_keys($selectedTagIds, true); ?>
                <div class="<?= is_logged_in() && $projects !== [] ? 'col-md-3' : 'col-md-4' ?>">
                    <label class="form-label" for="journal">Fonte</label>
                    <select class="form-select" name="journal" id="journal">
                        <option value="">Todas</option>
                        <?php foreach ($journals as $source): ?>
                            <option value="<?= h((string) $source) ?>"<?= selected_attr($journal, (string) $source) ?>><?= h((string) $source) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="<?= is_logged_in() && $projects !== [] ? 'col-md-3' : 'col-md-4' ?>">
                    <label class="form-label" for="tag-filter-search">Tag temática</label>
                    <div class="tag-filter-combobox" data-tag-filter-combobox>
                    <select class="d-none" id="tag_id" aria-hidden="true" tabindex="-1">
                        <option value="">Todas (inclui filhas)</option>
                        <?php foreach ($tagsByCategory as $catName => $catTags): ?>
                            <?php if ($catName !== ''): ?>
                                <optgroup label="<?= h($catName) ?>">
                                    <?php foreach ($catTags as $tagOption): ?>
                                        <option value="<?= (int) $tagOption['id'] ?>"<?= isset($selectedTagIdsMap[(int)$tagOption['id']]) ? ' selected' : '' ?>><?= h($tagOption['name']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php else: ?>
                                <?php foreach ($catTags as $tagOption): ?>
                                    <option value="<?= (int) $tagOption['id'] ?>"<?= isset($selectedTagIdsMap[(int)$tagOption['id']]) ? ' selected' : '' ?>><?= h($tagOption['name']) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                        <div class="tag-filter-input-wrap">
                            <input class="form-control tag-filter-input"
                                   type="search"
                                   id="tag-filter-search"
                                   autocomplete="off"
                                   role="combobox"
                                   aria-autocomplete="list"
                                   aria-controls="tag-filter-list"
                                   aria-expanded="false"
                                   placeholder="Buscar tag..."
                                   value="">
                            <button class="tag-filter-clear<?= $selectedTagIds !== [] ? '' : ' d-none' ?>"
                                    type="button"
                                    data-tag-filter-clear
                                    aria-label="Limpar tags selecionadas">&times;</button>
                        </div>
                        <div class="tag-filter-list" id="tag-filter-list" role="listbox" hidden>
                            <button class="tag-filter-option<?= $selectedTagIds === [] ? ' is-selected' : '' ?>"
                                    type="button"
                                    role="option"
                                    data-tag-filter-option
                                    data-tag-value=""
                                    data-tag-label="Todas (inclui filhas)"
                                    data-tag-search="todas inclui filhas">
                                <span>Todas (inclui filhas)</span>
                            </button>
                            <?php foreach ($allTags as $index => $tagOption): ?>
                                <?php
                                    $tagId = (int) $tagOption['id'];
                                    $tagName = (string) ($tagOption['name'] ?? '');
                                    $catLabel = trim((string) ($tagOption['category'] ?? '')) !== '' ? (string) $tagOption['category'] : 'Sem agrupamento';
                                    $ancestorNames = implode(' ', $ancestorNamesByTagId[$tagId] ?? []);
                                    $ancestorIds = implode(',', $ancestorIdsByTagId[$tagId] ?? []);
                                    $ownSearch = search_normalize($catLabel . ' ' . $tagName . ' ' . ($tagOption['definition'] ?? ''));
                                    $ancestorSearch = search_normalize($ancestorNames);
                                    $tagSearch = trim($ownSearch . ' ' . $ancestorSearch);
                                    $isTagSelected = isset($selectedTagIdsMap[$tagId]);
                                ?>
                                <button class="tag-filter-option<?= $isTagSelected ? ' is-selected' : '' ?>"
                                        type="button"
                                        role="option"
                                        data-tag-filter-option
                                        data-tag-value="<?= $tagId ?>"
                                        data-tag-label="<?= h($tagName) ?>"
                                        data-tag-search="<?= h($tagSearch) ?>"
                                        data-tag-own-search="<?= h($ownSearch) ?>"
                                        data-tag-ancestor-search="<?= h($ancestorSearch) ?>"
                                        data-tag-ancestor-ids="<?= h($ancestorIds) ?>"
                                        data-tag-original-index="<?= $index + 1 ?>"
                                        title="<?= h(tag_definition_text($tagOption)) ?>">
                                    <span><?= h($tagName) ?></span>
                                </button>
                            <?php endforeach; ?>
                            <div class="tag-filter-empty" data-tag-filter-empty hidden>Nenhuma tag encontrada.</div>
                        </div>
                    </div>

                    <div class="tag-chips-container d-flex flex-wrap gap-1 mt-2" id="tag-chips-container">
                        <?php foreach ($selectedTagIds as $tId): ?>
                            <?php 
                                $tagInfo = null;
                                foreach ($allTags as $t) {
                                    if ((int)$t['id'] === $tId) {
                                        $tagInfo = $t;
                                        break;
                                    }
                                }
                                if ($tagInfo === null) continue;
                                $cColor = get_tag_colors($tagInfo['category'] ?? '');
                            ?>
                            <span class="badge border tag-badge d-inline-flex align-items-center gap-1 py-1 px-2"
                                 
                                  <?= tag_tooltip_attrs($tagInfo) ?>>
                                <?= h($tagInfo['name']) ?>
                                <button type="button" class="tag-chip-remove" data-tag-id="<?= $tId ?>" aria-label="Remover tag">&times;</button>
                                <input type="hidden" name="tag_ids[]" value="<?= $tId ?>">
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (is_logged_in() && $projects !== []): ?>
                    <div class="col-md-3">
                        <label class="form-label" for="project_id">Projeto</label>
                        <select class="form-select" name="project_id" id="project_id">
                            <option value="">Todos</option>
                            <?php foreach ($projects as $proj): ?>
                                <option value="<?= (int) $proj['id'] ?>"<?= selected_attr((string)$selectedProjectId, (string)$proj['id']) ?>><?= h($proj['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="<?= is_logged_in() && $projects !== [] ? 'col-md-3' : 'col-md-4' ?>">
                    <label class="form-label" for="content">Conteúdo</label>
                    <select class="form-select" name="content" id="content">
                        <option value="">Todos</option>
                        <?php if (is_logged_in()): ?>
                            <option value="with_text"<?= selected_attr($content, 'with_text') ?>>Com texto completo</option>
                            <option value="without_text"<?= selected_attr($content, 'without_text') ?>>Sem texto completo</option>
                        <?php endif; ?>
                        <option value="with_references"<?= selected_attr($content, 'with_references') ?>>Com referências</option>
                        <option value="without_references"<?= selected_attr($content, 'without_references') ?>>Sem referências</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label" for="year_from">Ano inicial</label>
                    <input class="form-control" type="text" inputmode="numeric" maxlength="4" name="year_from" id="year_from" value="<?= h($yearFrom) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label" for="year_to">Ano final</label>
                    <input class="form-control" type="text" inputmode="numeric" maxlength="4" name="year_to" id="year_to" value="<?= h($yearTo) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label" for="status">Fichamento</label>
                    <select class="form-select" name="status" id="status">
                        <option value="">Todos</option>
                        <option value="fichado"<?= selected_attr($status, 'fichado') ?>>Apenas fichados</option>
                        <option value="cadastrado"<?= selected_attr($status, 'cadastrado') ?>>Apenas cadastrados</option>
                    </select>
                </div>

                <div class="col-12 d-flex justify-content-between align-items-center mt-3 border-top border pt-3">
                    <div>
                        <label class="form-label" for="sort">Ordenar por</label>
                        <select class="form-select" name="sort" id="sort">
                            <?php if ($tokens !== []): ?>
                                <option value="relevance"<?= selected_attr($sort, 'relevance') ?>>Relevância</option>
                            <?php endif; ?>
                            <option value="year_desc"<?= selected_attr($sort, 'year_desc') ?>>Ano decrescente</option>
                            <option value="year_asc"<?= selected_attr($sort, 'year_asc') ?>>Ano crescente</option>
                            <option value="recent"<?= selected_attr($sort, 'recent') ?>>Mais recentes no cadastro</option>
                            <option value="title_asc"<?= selected_attr($sort, 'title_asc') ?>>Título A-Z</option>
                            <option value="source_asc"<?= selected_attr($sort, 'source_asc') ?>>Fonte A-Z</option>
                            <?php if (is_logged_in()): ?>
                                <option value="text_desc"<?= selected_attr($sort, 'text_desc') ?>>Volume de texto</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="d-flex gap-2 align-self-end">
                        <a class="btn btn-outline-secondary px-4 rounded-pill" href="articles.php?clear=1">Limpar</a>
                        <button class="btn btn-primary px-4 rounded-pill" type="submit">Buscar</button>
                    </div>
                </div>
            </div>
        </form>

        <!-- Search Results -->
        <section>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <h2 class="h5 mb-0 text-body fw-bold">Resultados</h2>
                <span class="text-secondary"><?= count($articles) ?> artigo(s) encontrado(s)</span>
            </div>

            <?php if ($articles === []): ?>
                <div class="card p-5 text-center text-secondary">
                    <p class="mb-0">Nenhum artigo encontrado com os filtros selecionados.</p>
                </div>
            <?php else: ?>
                <?php
                // Fetch projects and tags in batch to optimize database roundtrips (fixing N+1 queries)
                $articleIds = array_column($articles, 'id');
                $currentUser = current_user();
                $userId = $currentUser ? (int)$currentUser['id'] : 0;
                $isAdmin = is_admin() ? 1 : 0;
                $articleProjectsMap = [];
                $articleTagsMap = [];

                if ($articleIds !== []) {
                    $placeholders = implode(',', array_map('intval', $articleIds));

                    // Fetch projects linked with notes
                    if ($userId > 0) {
                        $projectSql = "
                            SELECT 
                                atq.article_id,
                                p.id AS project_id,
                                p.title AS project_title,
                                COUNT(psn.note_id) AS note_count
                            FROM projects p
                            JOIN project_sections ps ON ps.project_id = p.id
                            JOIN project_section_notes psn ON psn.section_id = ps.id
                            JOIN article_tag_quotes atq ON atq.id = psn.note_id
                            WHERE atq.article_id IN ($placeholders)
                              AND (p.owner_user_id = :user_id OR :is_admin)
                            GROUP BY atq.article_id, p.id, p.title
                            ORDER BY lower(p.title) ASC
                        ";
                        $projectStmt = $pdo->prepare($projectSql);
                        $projectStmt->execute([
                            ':user_id' => $userId,
                            ':is_admin' => $isAdmin ? 1 : 0
                        ]);
                        foreach ($projectStmt->fetchAll() as $row) {
                            $articleProjectsMap[(int)$row['article_id']][] = [
                                'id' => (int)$row['project_id'],
                                'title' => (string)$row['project_title'],
                                'note_count' => (int)$row['note_count'],
                            ];
                        }
                    }

                    // Fetch tags for all articles in a single query
                    $tagsSql = "
                        SELECT
                            source_rows.article_id,
                            t.id,
                            t.name,
                            t.definition,
                            t.category,
                            MAX(CASE WHEN source_rows.has_note = 1 THEN 1 ELSE 0 END) AS has_note,
                            string_agg(NULLIF(source_rows.tag_quote, ''), E'\n---\n') AS tag_quote,
                            string_agg(NULLIF(source_rows.tag_comment, ''), E'\n---\n') AS tag_comment
                        FROM tags t
                        JOIN (
                            SELECT
                                at.tag_id,
                                at.article_id,
                                at.quote AS tag_quote,
                                at.comment AS tag_comment,
                                CASE
                                    WHEN TRIM(COALESCE(at.quote, '')) <> ''
                                      OR TRIM(COALESCE(at.comment, '')) <> ''
                                    THEN 1 ELSE 0
                                END AS has_note
                            FROM article_tags at

                            UNION ALL

                            SELECT
                                qt.tag_id,
                                q.article_id,
                                q.quote_text AS tag_quote,
                                q.comment AS tag_comment,
                                1 AS has_note
                            FROM article_tag_quotes q
                            JOIN article_quote_tags qt ON qt.quote_id = q.id
                        ) source_rows ON source_rows.tag_id = t.id
                        WHERE source_rows.article_id IN ($placeholders)
                        GROUP BY source_rows.article_id, t.id, t.name, t.definition, t.category
                        ORDER BY source_rows.article_id, lower(t.category) ASC, lower(t.name) ASC
                    ";
                    $tagsStmt = $pdo->query($tagsSql);
                    foreach ($tagsStmt->fetchAll() as $row) {
                        $aId = (int)$row['article_id'];
                        $articleTagsMap[$aId][] = [
                            'id' => $row['id'],
                            'name' => $row['name'],
                            'definition' => $row['definition'],
                            'category' => $row['category'],
                            'has_note' => $row['has_note'],
                            'tag_quote' => $row['tag_quote'],
                            'tag_comment' => $row['tag_comment'],
                        ];
                    }
                }
                ?>
                <div class="vstack gap-3">
                    <?php foreach ($articles as $article): ?>
                        <?php 
                        $snippet = first_match_snippet($article, $tokens); 
                        
                        // Retrieve pre-fetched tags and projects
                        $articleTags = $articleTagsMap[(int)$article['id']] ?? [];
                        $articleProjects = $articleProjectsMap[(int)$article['id']] ?? [];
                        ?>
                        <article class="card p-4">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                                <div class="flex-grow-1">
                                    <h3 class="h5 article-title mb-2">
                                        <?php if ((string) ($article['year'] ?? '') !== ''): ?>
                                            <span class="text-secondary me-2 fw-normal">[<?= h((string) $article['year']) ?>]</span>
                                        <?php endif; ?>
                                        <a href="view.php?id=<?= h((string) $article['id']) ?>"><?= h($article['title']) ?></a>
                                    </h3>
                                    
                                    <div class="text-secondary small mb-2">
                                        <strong><?= h($article['authors']) ?></strong>
                                        <?php if ((string) ($article['journal'] ?? '') !== ''): ?>
                                            <span class="mx-2">|</span><em class="text-body-secondary"><?= h($article['journal']) ?></em>
                                        <?php endif; ?>
                                        <?php 
                                            $hasStart = !empty($article['data_year_start']);
                                            $hasEnd = !empty($article['data_year_end']);
                                        ?>
                                        <?php if ($hasStart || $hasEnd): ?>
                                            <span class="mx-2">|</span>
                                            <span class="text-body-secondary" title="Período dos dados de coleta">
                                                📅 Dados: 
                                                <?php if ($hasStart && $hasEnd): ?>
                                                    <?= h((string) $article['data_year_start']) ?> a <?= h((string) $article['data_year_end']) ?>
                                                <?php elseif ($hasStart): ?>
                                                    a partir de <?= h((string) $article['data_year_start']) ?>
                                                <?php else: ?>
                                                    até <?= h((string) $article['data_year_end']) ?>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Badges -->
                                     <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                                         <span class="text-secondary small d-flex align-items-center gap-1">
                                             <i class="bi bi-tags"></i> Tags:
                                         </span>
                                         <?php if ($articleTags !== []): ?>
                                             <?php foreach ($articleTags as $tag): ?>
                                                 <?php 
                                                 $cColor = get_tag_colors($tag['category'] ?? '');
                                                 
                                                 $hasCommentOrQuote = (trim($tag['tag_quote'] ?? '') !== '' || trim($tag['tag_comment'] ?? '') !== '');
                                                 ?>
                                                 <a href="tag_view.php?tag_id=<?= (int)$tag['id'] ?>" class="badge border tag-badge text-decoration-none" 
                                                      
                                                       <?= tag_tooltip_attrs($tag) ?>>
                                                     <?= h($tag['name']) ?>
                                                     <?php if ($hasCommentOrQuote): ?>
                                                         <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                                                     <?php else: ?>
                                                         <span class="ms-1 fw-bold text-warning" title="Sem citação ou observação">!</span>
                                                     <?php endif; ?>
                                                 </a>
                                             <?php endforeach; ?>
                                         <?php endif; ?>
                                         
                                         <!-- Alert Button at the end of the tags -->
                                         <?= render_article_list_alerts_button($articleTags) ?>
                                     </div>

                                    <!-- Projects Badges -->
                                    <?php if ($articleProjects !== []): ?>
                                        <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                                            <span class="text-secondary small d-flex align-items-center gap-1">
                                                <i class="bi bi-folder2-open"></i> Projetos:
                                            </span>
                                            <?php foreach ($articleProjects as $project): ?>
                                                <a href="project.php?id=<?= (int)$project['id'] ?>" class="project-badge text-decoration-none">
                                                    <?= h($project['title']) ?>
                                                    <span class="project-badge-count"><?= (int)$project['note_count'] ?></span>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                            <?php if ($snippet !== ''): ?>
                                <p class="text-body-secondary small mt-3 mb-0"><?= h($snippet) ?></p>
                            <?php endif; ?>

                            <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 pt-3 border-top border border-opacity-10">
                                <div class="d-flex flex-wrap gap-3 text-secondary small">
                                    <?php if (is_logged_in()): ?>
                                        <span class="metric">Texto Completo: <?= count_words($article['full_text'] ?? '') ?> palavras</span>
                                        <span class="metric">Artigo Completo: <?= count_words(implode(' ', [
                                            $article['title'] ?? '',
                                            $article['authors'] ?? '',
                                            $article['journal'] ?? '',
                                            $article['abstract'] ?? '',
                                            $article['keywords'] ?? '',
                                            $article['full_text'] ?? '',
                                            $article['references_text'] ?? ''
                                        ])) ?> palavras</span>
                                    <?php else: ?>
                                        <span class="metric">Palavras (Metadados): <?= count_words(implode(' ', [
                                            $article['title'] ?? '',
                                            $article['authors'] ?? '',
                                            $article['journal'] ?? '',
                                            $article['abstract'] ?? '',
                                            $article['keywords'] ?? '',
                                            $article['references_text'] ?? ''
                                        ])) ?> palavras</span>
                                    <?php endif; ?>
                                    <?php if ($tokens !== []): ?>
                                        <span class="metric">Relevância: <?= h((string) $article['relevance']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex gap-2 mt-2 mt-sm-0 ms-auto">
                                    <?php if (trim((string) ($article['url'] ?? '')) !== ''): ?>
                                        <a class="btn btn-sm btn-outline-primary px-3 rounded-pill d-inline-flex align-items-center gap-1"
                                           href="<?= h($article['url']) ?>" 
                                           target="_blank" 
                                           rel="noopener noreferrer" 
                                           title="Abrir URL original em nova aba">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                                            URL ↗
                                        </a>
                                    <?php endif; ?>
                                    <?php if (trim((string) ($article['pdf_url'] ?? '')) !== ''): ?>
                                        <a class="btn btn-sm btn-outline-primary px-3 rounded-pill d-inline-flex align-items-center gap-1"
                                           href="<?= h($article['pdf_url']) ?>" 
                                           target="_blank" 
                                           rel="noopener noreferrer" 
                                           title="Abrir PDF original em nova aba">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                                            PDF ↗
                                        </a>
                                    <?php endif; ?>
                                                              <?php if (trim((string) ($article['abstract'] ?? '')) !== ''): ?>
                                        <button class="btn btn-sm btn-outline-info px-3 rounded-pill d-inline-flex align-items-center gap-1"
                                                type="button"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#abstractModal<?= (int) $article['id'] ?>"
                                                title="Ler resumo do artigo">
                                            <i class="bi bi-book-half"></i> Resumo
                                        </button>
                                    <?php endif; ?>
                                    <a class="btn btn-sm btn-primary px-3 rounded-pill" href="view.php?id=<?= h((string) $article['id']) ?>"><?= is_logged_in() ? 'Fichar & Ler' : 'Ficha' ?></a>
                                 </div>
                             </div>
                         </article>
                         
                         <!-- Modal de Resumo -->
                         <?php if (trim((string) ($article['abstract'] ?? '')) !== ''): ?>
                             <div class="modal fade" id="abstractModal<?= (int) $article['id'] ?>" tabindex="-1" aria-labelledby="abstractModalLabel<?= (int) $article['id'] ?>" aria-hidden="true">
                                 <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                                     <div class="modal-content">
                                         <div class="modal-header">
                                             <h5 class="modal-title fw-bold" id="abstractModalLabel<?= (int) $article['id'] ?>">
                                                 <i class="bi bi-book-half text-info me-2"></i> Resumo do Artigo
                                             </h5>
                                             <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                         </div>
                                         <div class="modal-body py-4 text-start">
                                             <h6 class="text-secondary small mb-1">Título do Artigo</h6>
                                             <p class="fw-semibold mb-3"><?= h($article['title']) ?></p>
                                             
                                             <div class="row g-2 mb-3">
                                                 <div class="col-sm-4">
                                                     <h6 class="text-secondary small mb-1">Autores</h6>
                                                     <p class="small mb-0"><?= h($article['authors']) ?></p>
                                                 </div>
                                                 <div class="col-sm-2">
                                                     <h6 class="text-secondary small mb-1">Ano</h6>
                                                     <p class="small mb-0"><?= h((string)($article['year'] ?? '-')) ?></p>
                                                 </div>
                                                 <div class="col-sm-3">
                                                     <h6 class="text-secondary small mb-1">Fonte / Revista</h6>
                                                     <p class="small mb-0"><?= h((string)($article['journal'] ?? '-')) ?></p>
                                                 </div>
                                                 <div class="col-sm-3">
                                                     <h6 class="text-secondary small mb-1">Período dos dados</h6>
                                                     <p class="small mb-0">
                                                         <?php 
                                                             $hasStart = !empty($article['data_year_start']);
                                                             $hasEnd = !empty($article['data_year_end']);
                                                         ?>
                                                         <?php if ($hasStart && $hasEnd): ?>
                                                             <?= h((string) $article['data_year_start']) ?> a <?= h((string) $article['data_year_end']) ?>
                                                         <?php elseif ($hasStart): ?>
                                                             A partir de <?= h((string) $article['data_year_start']) ?>
                                                         <?php elseif ($hasEnd): ?>
                                                             Até <?= h((string) $article['data_year_end']) ?>
                                                         <?php else: ?>
                                                             -
                                                         <?php endif; ?>
                                                     </p>
                                                 </div>
                                             </div>

                                             <?php if (trim((string)($article['keywords'] ?? '')) !== ''): ?>
                                                 <h6 class="text-secondary small mb-1">Palavras-chave</h6>
                                                 <p class="small mb-3"><?= h($article['keywords']) ?></p>
                                             <?php endif; ?>
                                             
                                             <h6 class="text-secondary small mb-1">Resumo / Abstract</h6>
                                             <p><?= h($article['abstract']) ?></p>
                                         </div>
                                         <div class="modal-footer">
                                             <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Fechar</button>
                                             <a class="btn btn-primary rounded-pill px-4" href="view.php?id=<?= h((string) $article['id']) ?>"><?= is_logged_in() ? 'Fichar & Ler' : 'Visualizar Ficha' ?></a>
                                         </div>
                                     </div>
                                 </div>
                             </div>
                          <?php endif; ?>
                        <?php endforeach; ?>
                </div>

                <?php
                // Generate paginator items
                $range = 2;
                $pagesToDisplay = [];
                for ($i = 1; $i <= $totalPages; $i++) {
                    if ($i === 1 || $i === $totalPages || ($i >= $page - $range && $i <= $page + $range)) {
                        $pagesToDisplay[] = $i;
                    }
                }
                $paginatorItems = [];
                $prev = null;
                foreach ($pagesToDisplay as $p) {
                    if ($prev !== null && $p - $prev > 1) {
                        $paginatorItems[] = '...';
                    }
                    $paginatorItems[] = $p;
                    $prev = $p;
                }
                ?>

                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Navegação de páginas" class="mt-4">
                        <ul class="pagination pagination-sm justify-content-center flex-wrap gap-1">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link rounded-pill px-3" href="<?= get_pagination_url(1) ?>" aria-label="Primeira">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link rounded-pill px-3" href="<?= get_pagination_url($page - 1) ?>" aria-label="Anterior">
                                    <span aria-hidden="true">&lsaquo;</span>
                                </a>
                            </li>

                            <?php foreach ($paginatorItems as $item): ?>
                                <?php if ($item === '...'): ?>
                                    <li class="page-item disabled"><span class="page-link border-0 bg-transparent text-secondary">...</span></li>
                                <?php else: ?>
                                    <li class="page-item <?= $item === $page ? 'active' : '' ?>">
                                        <a class="page-link rounded-pill px-3" href="<?= get_pagination_url((int)$item) ?>"><?= $item ?></a>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link rounded-pill px-3" href="<?= get_pagination_url($page + 1) ?>" aria-label="Próxima">
                                    <span aria-hidden="true">&rsaquo;</span>
                                </a>
                            </li>
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link rounded-pill px-3" href="<?= get_pagination_url($totalPages) ?>" aria-label="Última">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                        <div class="text-center text-secondary mt-2 small">
                            Exibindo <?= min($offset + 1, $totalArticles) ?>-<?= min($offset + $limit, $totalArticles) ?> de <?= $totalArticles ?> artigos
                        </div>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script src="assets/app.js?v=20260615"></script>
    <script>
        // Initialize tooltips
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

        function normalizeTagFilterText(value) {
            return String(value || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .trim();
        }

        const tagCombobox = document.querySelector('[data-tag-filter-combobox]');
        if (tagCombobox) {
            const select = tagCombobox.querySelector('#tag_id');
            const input = tagCombobox.querySelector('#tag-filter-search');
            const list = tagCombobox.querySelector('#tag-filter-list');
            const clearButton = tagCombobox.querySelector('[data-tag-filter-clear]');
            const emptyState = tagCombobox.querySelector('[data-tag-filter-empty]');
            const options = Array.from(tagCombobox.querySelectorAll('[data-tag-filter-option]'));
            const optionByValue = new Map(options.map(option => [option.dataset.tagValue || '', option]));
            let activeIndex = -1;

            const visibleOptions = () => options.filter(option => !option.hidden);

            function openTagList() {
                list.hidden = false;
                input.setAttribute('aria-expanded', 'true');
                filterTagOptions(input.value);
            }

            function closeTagList() {
                list.hidden = true;
                input.setAttribute('aria-expanded', 'false');
                activeIndex = -1;
                options.forEach(option => option.classList.remove('is-active'));
            }

            function setActiveOption(index) {
                const visible = visibleOptions();
                activeIndex = visible.length === 0 ? -1 : Math.max(0, Math.min(index, visible.length - 1));
                options.forEach(option => option.classList.remove('is-active'));
                if (activeIndex >= 0) {
                    visible[activeIndex].classList.add('is-active');
                    visible[activeIndex].scrollIntoView({ block: 'nearest' });
                }
            }

            function filterTagOptions(query) {
                const normalized = normalizeTagFilterText(query);
                let visibleCount = 0;
                const matchedParentIds = new Set();

                options.forEach(option => {
                    const value = option.dataset.tagValue || '';
                    if (value === '' || normalized === '') return;
                    const ownText = option.dataset.tagOwnSearch || normalizeTagFilterText(option.dataset.tagLabel);
                    if (ownText.includes(normalized)) {
                        matchedParentIds.add(value);
                    }
                });

                options.forEach(option => {
                    const isAllOption = option.dataset.tagValue === '';
                    const ownText = option.dataset.tagOwnSearch || normalizeTagFilterText(option.dataset.tagLabel);
                    const ancestorText = option.dataset.tagAncestorSearch || '';
                    const matchesOwn = normalized === '' || ownText.includes(normalized);
                    const matchesAncestor = normalized !== '' && ancestorText.includes(normalized);
                    const matches = isAllOption ? normalized === '' : (matchesOwn || matchesAncestor);
                    option.hidden = !matches;
                    option.classList.toggle('is-child-result', normalized !== '' && !matchesOwn && matchesAncestor);
                    option.dataset.tagMatchesOwn = matchesOwn ? '1' : '0';
                    if (matches) visibleCount++;
                });

                const visible = visibleOptions().sort((left, right) => compareTagOptionsForSearch(left, right, normalized, matchedParentIds));
                visible.forEach(option => list.insertBefore(option, emptyState));

                if (emptyState) {
                    emptyState.hidden = visibleCount > 0;
                }
                setActiveOption(visibleCount > 0 ? 0 : -1);
            }

            function compareTagOptionsForSearch(left, right, query, matchedParentIds) {
                return tagOptionSearchRank(left, query, matchedParentIds)
                    .localeCompare(tagOptionSearchRank(right, query, matchedParentIds), 'pt-BR', { numeric: true });
            }

            function tagOptionSearchRank(option, query, matchedParentIds) {
                const value = option.dataset.tagValue || '';
                const label = normalizeTagFilterText(option.dataset.tagLabel || '');
                const original = String(Number(option.dataset.tagOriginalIndex || (value === '' ? 0 : 9999))).padStart(5, '0');
                if (query === '') {
                    return `${original}-0-${label}`;
                }
                if (value === '') {
                    return `99999-9-${label}`;
                }

                const ancestorIds = (option.dataset.tagAncestorIds || '').split(',').filter(Boolean);
                const matchedAncestorId = ancestorIds.find(id => matchedParentIds.has(id));
                if (matchedAncestorId && option.dataset.tagMatchesOwn !== '1') {
                    const parentOption = optionByValue.get(matchedAncestorId);
                    const parentOrder = String(Number(parentOption?.dataset.tagOriginalIndex || 9999)).padStart(5, '0');
                    const depth = String(Math.max(1, ancestorIds.indexOf(matchedAncestorId) + 1)).padStart(2, '0');
                    return `${parentOrder}-8-${depth}-${label}`;
                }

                if (label === query) {
                    return `${original}-0-${label}`;
                }
                if (label.startsWith(query)) {
                    return `${original}-1-${label}`;
                }
                return `${original}-2-${label}`;
            }

            function selectTagOption(option) {
                if (!option) return;
                const val = option.dataset.tagValue || '';
                if (val === '') {
                    tagCombobox.querySelectorAll('input[name="tag_ids[]"]').forEach(el => el.remove());
                    tagCombobox.closest('form').submit();
                    return;
                }
                
                let existing = tagCombobox.querySelector('input[name="tag_ids[]"][value="' + val + '"]');
                if (!existing) {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'tag_ids[]';
                    hidden.value = val;
                    tagCombobox.appendChild(hidden);
                    tagCombobox.closest('form').submit();
                } else {
                    closeTagList();
                    input.value = '';
                }
            }

            input.addEventListener('focus', openTagList);
            input.addEventListener('click', openTagList);
            input.addEventListener('input', () => {
                options.forEach(option => option.classList.remove('is-selected'));
                openTagList();
                filterTagOptions(input.value);
            });
            input.addEventListener('keydown', (event) => {
                const visible = visibleOptions();
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    openTagList();
                    setActiveOption(activeIndex + 1);
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    openTagList();
                    setActiveOption(activeIndex <= 0 ? visible.length - 1 : activeIndex - 1);
                } else if (event.key === 'Enter' && !list.hidden) {
                    event.preventDefault();
                    selectTagOption(visible[activeIndex] || visible[0]);
                } else if (event.key === 'Escape') {
                    closeTagList();
                }
            });

            options.forEach(option => {
                option.addEventListener('click', () => selectTagOption(option));
                option.addEventListener('mouseenter', () => setActiveOption(visibleOptions().indexOf(option)));
            });

            clearButton?.addEventListener('click', () => {
                const allOption = options.find(option => option.dataset.tagValue === '');
                selectTagOption(allOption);
            });

            document.addEventListener('click', (event) => {
                if (!tagCombobox.contains(event.target)) {
                    closeTagList();
                }
            });

            document.querySelectorAll('.tag-chip-remove').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const badge = btn.closest('.tag-badge');
                    if (badge) {
                        badge.remove();
                        btn.closest('form').submit();
                    }
                });
            });
        }
    </script>
    <!-- Loading overlay (hourglass) -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="d-flex flex-column align-items-center gap-3">
            <svg class="loading-hourglass" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 2h14"></path>
                <path d="M5 22h14"></path>
                <path d="M19 2v10a7 7 0 0 1-14 0V2"></path>
                <path d="M5 22v-10a7 7 0 0 1 14 0v10"></path>
            </svg>
            <span class="text-body fw-medium small tracking-wide">Aguarde...</span>
        </div>
    </div>
<script>
        (function() {
            let fetchTimer = null;
            let activeFetchCount = 0;
            
            function showOverlay(instant = false) {
                activeFetchCount++;
                if (instant) {
                    const overlay = document.getElementById('loading-overlay');
                    if (overlay) overlay.classList.add('active');
                } else if (!fetchTimer) {
                    fetchTimer = setTimeout(() => {
                        const overlay = document.getElementById('loading-overlay');
                        if (overlay) overlay.classList.add('active');
                    }, 250); // 250ms threshold
                }
            }

            function hideOverlay() {
                activeFetchCount--;
                if (activeFetchCount <= 0) {
                    activeFetchCount = 0;
                    if (fetchTimer) {
                        clearTimeout(fetchTimer);
                        fetchTimer = null;
                    }
                    const overlay = document.getElementById('loading-overlay');
                    if (overlay) overlay.classList.remove('active');
                }
            }

            // Intercept global fetch calls (AJAX)
            const originalFetch = window.fetch;
            window.fetch = function(...args) {
                showOverlay();
                return originalFetch(...args).finally(() => {
                    hideOverlay();
                });
            };

            // Intercept form submits (Full page load)
            document.addEventListener('submit', (event) => {
                if (event.defaultPrevented) return;
                const target = event.target.getAttribute('target');
                if (target === '_blank') return;
                showOverlay(true);
            });

            // Show on navigation away / beforeunload (with auto-hide timeout to prevent freezes on downloads)
            window.addEventListener('beforeunload', () => {
                showOverlay(true);
                setTimeout(hideOverlay, 5000);
            });
        })();
    </script>
</body>
</html>
