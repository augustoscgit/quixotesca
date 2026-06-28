<?php
declare(strict_types=1);

require __DIR__ . '/../../fichario/bootstrap.php';

$pdo = db();
$dbError = '';
$articlesByYear = [];
$tagsByArticleId = [];
$selectedTagIds = isset($_GET['tag_ids']) ? array_map('intval', (array) $_GET['tag_ids']) : [];

if (!function_exists('article_tag_ancestor_names')) {
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
}

if (!function_exists('article_tag_ancestor_ids')) {
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
}

if (!function_exists('article_tag_category_rank')) {
    function article_tag_category_rank(?string $category): int
    {
        return match (search_normalize((string) $category)) {
            'metodo' => 1,
            'fonte' => 2,
            'tema' => 3,
            default => 4,
        };
    }
}

try {
    // CTE matching criteria
    $where = ['year IS NOT NULL AND year > 0'];
    $params = [];

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

    $whereSql = implode(' AND ', $where);
    
    // Fetch articles with a valid year, ordered by year desc and title asc, and select their fichamento status
    $stmt = $pdo->prepare("
        SELECT id, title, authors, year, journal,
               (" . article_has_notes_sql('articles') . ") AS is_fichado
        FROM articles
        WHERE $whereSql
        ORDER BY year DESC, lower(title) ASC
    ");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }
    $stmt->execute();
    $articles = $stmt->fetchAll() ?: [];

    foreach ($articles as $article) {
        $year = (int) $article['year'];
        $articlesByYear[$year][] = $article;
    }

    // Fetch tags for these articles in a single query to prevent N+1 queries, ensuring uniqueness
    $tagsStmt = $pdo->query("
        SELECT DISTINCT source_rows.article_id, t.id, t.name, t.category
        FROM tags t
        JOIN (
            SELECT tag_id, article_id FROM article_tags
            UNION
            SELECT qt.tag_id, q.article_id
            FROM article_tag_quotes q
            JOIN article_quote_tags qt ON qt.quote_id = q.id
        ) source_rows ON source_rows.tag_id = t.id
        ORDER BY t.category, t.name
    ");
    while ($row = $tagsStmt->fetch()) {
        $tagsByArticleId[(int)$row['article_id']][] = $row;
    }

    // Fetch all available tags for selection
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

} catch (Throwable $exception) {
    $dbError = app_debug_enabled()
        ? $exception->getMessage()
        : 'Não foi possível conectar ao banco de dados para carregar a timeline.';
}
?>
<!doctype html>
<html lang="pt-br" data-module="fichario">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Linha do Tempo - Fichário Acadêmico</title>
    <link rel="icon" type="image/png" href="../assets/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/app.css?v=20260615" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="../assets/js/theme-switcher.js"></script>
    <style>
        body {
            background: var(--bg-gradient);
        }

        .blob {
            animation: floatBlob 12s infinite alternate ease-in-out;
        }

        .blob-purple {
            animation-delay: -6s;
        }

        @keyframes floatBlob {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(60px, 40px) scale(1.15); }
        }

        /* Timeline Styles */
        .timeline-container {
            position: relative;
            padding: 2rem 0;
            max-width: 900px;
            margin: 2rem auto;
        }

        .timeline-line {
            position: absolute;
            left: 90px;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(to bottom, var(--bs-primary) 0%, rgba(13, 110, 253, 0.1) 100%);
            border-radius: 2px;
            z-index: 1;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
            min-height: 75px;
            z-index: 2;
        }

        /* Empty timeline item to maintain physical scale */
        .timeline-item-empty {
            position: relative;
            height: 60px;
            margin-bottom: 0;
            z-index: 2;
        }

        .timeline-year-label {
            position: absolute;
            left: 0;
            top: 14px;
            width: 75px;
            text-align: right;
            font-weight: 800;
            font-size: 1.4rem;
            color: var(--bs-primary);
            line-height: 1;
        }

        .timeline-year-label-empty {
            position: absolute;
            left: 0;
            top: 13px;
            width: 75px;
            text-align: right;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-muted);
            opacity: 0.35;
            line-height: 1.5;
        }

        .timeline-point {
            position: absolute;
            left: 82px; /* centered on the 4px line at 90px */
            top: 12px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--bs-body-bg);
            border: 4px solid var(--bs-primary);
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
            z-index: 3;
            transition: all 0.3s ease;
        }

        .timeline-point-empty {
            position: absolute;
            left: 88px; /* centered on the 4px line at 90px */
            top: 18px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--bs-border-color);
            z-index: 3;
            opacity: 0.35;
            transition: all 0.3s ease;
        }

        .timeline-item:hover .timeline-point {
            background: var(--bs-primary);
            transform: scale(1.25);
            box-shadow: 0 0 12px var(--bs-primary);
        }

        .timeline-item-empty:hover .timeline-point-empty {
            background: var(--bs-primary);
            opacity: 0.8;
            transform: scale(1.3);
        }

        .timeline-connector {
            position: absolute;
            left: 92px; /* starts from the center of the line */
            width: 38px;
            height: 2px;
            background: var(--bs-border-color);
            top: 21px;
            z-index: 1;
            transition: background-color 0.3s ease;
        }

        .timeline-item:hover .timeline-connector {
            background-color: var(--bs-primary);
        }

        .timeline-card {
            margin-left: 130px;
            padding: 1.25rem 1.75rem;
            background-color: var(--card-bg);
            border: 1px solid var(--card-border) !important;
            border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .timeline-card:hover {
            border-color: var(--bs-primary) !important;
            box-shadow: 0 8px 25px rgba(13, 110, 253, 0.08);
        }

        .timeline-card-header {
            cursor: pointer;
            user-select: none;
            color: var(--text-color);
            transition: color 0.2s ease;
        }

        .timeline-card-header:hover {
            color: var(--bs-primary);
        }

        .timeline-card-header[aria-expanded="true"] {
            color: var(--bs-primary);
        }

        .timeline-card-header[aria-expanded="true"] .bi-chevron-down {
            transform: rotate(180deg);
        }

        .transition-transform {
            transition: transform 0.25s ease-in-out;
        }

        .timeline-article-title {
            font-size: 1.15rem;
            font-weight: 600;
            margin-bottom: 0.3rem;
            line-height: 1.4;
        }

        .timeline-article-title a {
            color: var(--text-color);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .timeline-article-title a:hover {
            color: var(--bs-primary);
        }

        .timeline-article-meta {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }

        .timeline-stack-badge {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.6rem;
            border-radius: 50px;
            background-color: rgba(13, 110, 253, 0.1);
            color: var(--bs-primary);
            border: 1px solid rgba(13, 110, 253, 0.2);
        }

        .timeline-stack {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .timeline-stack-item {
            position: relative;
        }

        /* Responsive timeline */
        @media (max-width: 575.98px) {
            .timeline-line {
                left: 20px;
            }
            .timeline-year-label {
                position: static;
                width: auto;
                text-align: left;
                margin-bottom: 0.75rem;
                padding-left: 35px;
                font-size: 1.25rem;
            }
            .timeline-year-label-empty {
                position: static;
                width: auto;
                text-align: left;
                margin-bottom: 0.25rem;
                padding-left: 35px;
                font-size: 0.95rem;
            }
            .timeline-point {
                left: 12px;
                top: 2px;
            }
            .timeline-point-empty {
                left: 18px;
                top: 6px;
            }
            .timeline-connector {
                display: none;
            }
            .timeline-card {
                margin-left: 35px;
                padding: 1.25rem;
            }
            .timeline-item-empty {
                height: 40px;
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Background Animated Blobs -->
    <div class="blob blob-blue"></div>
    <div class="blob blob-purple"></div>

    <?php render_navbar('timeline'); ?>

    <main class="container py-4 main-container">
        <!-- Breadcrumbs -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Fichário</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page">Timeline</li>
            </ol>
        </nav>

        <!-- Top heading -->
        <div class="mb-4">
            <h1 class="h3 mb-1 text-white fw-bold">Linha do Tempo de Artigos</h1>
            <p class="text-secondary mb-0">Explore a produção acadêmica fichada em ordem cronológica de publicação. Clique nos anos para expandir.</p>
        </div>

        <!-- Filter Form -->
        <form class="glass-card p-4 mb-4" method="get">
            <div class="row g-3 align-items-end">
                <div class="col-md-9">
                    <label class="form-label" for="tag-filter-search">Filtrar por Tags Temáticas (Digite e pressione Enter)</label>
                    
                    <?php $selectedTagIdsMap = array_fill_keys($selectedTagIds, true); ?>
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
                                   placeholder="Buscar tag e pressione Enter..."
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
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100 rounded-pill"><i class="bi bi-funnel me-1"></i> Filtrar</button>
                </div>
            </div>

            <div class="tag-chips-container d-flex flex-wrap gap-1 mt-3" id="tag-chips-container">
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
                          style="background:<?= $cColor['bg'] ?>; color:<?= $cColor['text'] ?>; border-color:<?= $cColor['border'] ?> !important;"
                          <?= tag_tooltip_attrs($tagInfo) ?>>
                        <?= h($tagInfo['name']) ?>
                        <button type="button" class="tag-chip-remove" data-tag-id="<?= $tId ?>" aria-label="Remover tag">&times;</button>
                        <input type="hidden" name="tag_ids[]" value="<?= $tId ?>">
                    </span>
                <?php endforeach; ?>
            </div>
        </form>

        <?php if ($dbError !== ''): ?>
            <div class="alert alert-danger" role="alert">
                <?= h($dbError) ?>
            </div>
        <?php elseif (empty($articlesByYear)): ?>
            <div class="glass-card text-center p-5">
                <p class="text-secondary mb-0">Nenhum artigo encontrado para o filtro selecionado.</p>
            </div>
        <?php else: ?>
            <div class="timeline-container" id="timelineAccordion">
                <div class="timeline-line"></div>

                <?php 
                $maxYear = max(array_keys($articlesByYear));
                $minYear = min(array_keys($articlesByYear));
                
                for ($year = $maxYear; $year >= $minYear; $year--):
                    $hasArticles = isset($articlesByYear[$year]);
                    
                    if ($hasArticles):
                        $yearArticles = $articlesByYear[$year];
                        $collapseId = 'collapse-' . $year;
                        $headerId = 'heading-' . $year;
                        $count = count($yearArticles);

                        // Calculate fichados in this year
                        $fichadosInYear = 0;
                        foreach ($yearArticles as $art) {
                            if (!empty($art['is_fichado'])) {
                                $fichadosInYear++;
                            }
                        }
                        
                        $titleText = $count === 1 ? '1 artigo' : $count . ' artigos';
                        $titleText .= ' (' . $fichadosInYear . '/' . $count . ')';

                        $tooltipText = $fichadosInYear === 1 ? '1 fichado' : $fichadosInYear . ' fichados';
                        $tooltipText .= ' de ';
                        $tooltipText .= $count === 1 ? '1 cadastrado' : $count . ' cadastrados';

                        // Collect and deduplicate tags for this year
                        $yearTags = [];
                        foreach ($yearArticles as $article) {
                            $artId = (int)$article['id'];
                            if (!empty($tagsByArticleId[$artId])) {
                                foreach ($tagsByArticleId[$artId] as $tag) {
                                    $tagId = (int)$tag['id'];
                                    $yearTags[$tagId] = $tag;
                                }
                            }
                        }
                        // Sort tags by category then name
                        usort($yearTags, function($a, $b) {
                            if (($a['category'] ?? '') !== ($b['category'] ?? '')) {
                                return ($a['category'] ?? '') <=> ($b['category'] ?? '');
                            }
                            return ($a['name'] ?? '') <=> ($b['name'] ?? '');
                        });
                ?>
                    <div class="timeline-item">
                        <div class="timeline-point"></div>
                        <div class="timeline-year-label"><?= $year ?></div>
                        <div class="timeline-connector"></div>

                        <div class="timeline-card glass-card">
                            <!-- Clickable Card Header -->
                            <div class="timeline-card-header" 
                                 id="<?= $headerId ?>"
                                 data-bs-toggle="collapse" 
                                 data-bs-target="#<?= $collapseId ?>" 
                                 aria-expanded="false" 
                                 aria-controls="<?= $collapseId ?>"
                                 title="<?= h($tooltipText) ?>">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-folder2-open text-primary fs-5"></i>
                                        <span class="fw-semibold">
                                            <?= h($titleText) ?>
                                        </span>
                                    </div>
                                    <i class="bi bi-chevron-down text-secondary transition-transform"></i>
                                </div>

                                <!-- Unique Tags for the Year (Visible even when collapsed) -->
                                <?php if (!empty($yearTags)): ?>
                                    <div class="d-flex flex-wrap gap-1 mt-2 timeline-year-tags">
                                        <?php foreach ($yearTags as $tag): 
                                            $colors = get_tag_colors($tag['category'] ?? '');
                                        ?>
                                            <span class="tag-badge border" 
                                                  style="background-color: <?= $colors['bg'] ?>; color: <?= $colors['text'] ?>; border-color: <?= $colors['border'] ?> !important; font-size: 0.65rem; padding: 0.15rem 0.5rem;"
                                                  title="<?= h($tag['category'] ?? '') ?>">
                                                <?= h($tag['name']) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Collapsible Content -->
                            <div id="<?= $collapseId ?>" 
                                 class="collapse" 
                                 aria-labelledby="<?= $headerId ?>" 
                                 data-bs-parent="#timelineAccordion">
                                <div class="pt-3 mt-3 border-top">
                                    <div class="timeline-stack">
                                        <?php foreach ($yearArticles as $index => $article): ?>
                                            <div class="timeline-stack-item <?= $index > 0 ? 'border-top pt-3 mt-1' : '' ?>">
                                                <h3 class="timeline-article-title">
                                                    <a href="view.php?id=<?= h((string)$article['id']) ?>">
                                                        <?= h($article['title']) ?>
                                                    </a>
                                                </h3>
                                                <div class="timeline-article-meta text-secondary">
                                                    <?php if (!empty($article['authors'])): ?>
                                                        <span><i class="bi bi-person me-1"></i><?= h($article['authors']) ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($article['journal'])): ?>
                                                        <span class="ms-2"><i class="bi bi-journal-text me-1"></i><?= h($article['journal']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Tags specific to this article -->
                                                <?php if (!empty($tagsByArticleId[(int)$article['id']])): ?>
                                                    <div class="d-flex flex-wrap gap-1 mt-2">
                                                        <?php foreach ($tagsByArticleId[(int)$article['id']] as $tag): 
                                                            $colors = get_tag_colors($tag['category'] ?? '');
                                                        ?>
                                                            <span class="tag-badge border" 
                                                                  style="background-color: <?= $colors['bg'] ?>; color: <?= $colors['text'] ?>; border-color: <?= $colors['border'] ?> !important;"
                                                                  title="<?= h($tag['category'] ?? '') ?>">
                                                                <?= h($tag['name']) ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Empty Year (Maintains vertical scale) -->
                    <div class="timeline-item-empty">
                        <div class="timeline-point-empty"></div>
                        <div class="timeline-year-label-empty"><?= $year ?></div>
                    </div>
                <?php endif; ?>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            function normalizeTagFilterText(text) {
                return (text || '')
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
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
        });
    </script>
</body>
</html>
