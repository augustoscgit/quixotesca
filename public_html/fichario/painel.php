<?php
declare(strict_types=1);

require __DIR__ . '/../../fichario/bootstrap.php';
require_once __DIR__ . '/../../fichario/src/Home/tag_cloud.php';
require_once __DIR__ . '/../../fichario/src/Home/tag_graph.php';

$articleCount = 0;
$tagCount = 0;
$citationCount = 0;
$notesCount = 0;
$cloudTags = [];
$maxCount = 0;
$wordList = [];
$nodes = [];
$edges = [];
$dbError = '';

try {
    $pdo = db();

    // Fetch counts
    $articleCount = (int) $pdo->query('SELECT COUNT(*) FROM articles')->fetchColumn();
    $tagCount = (int) $pdo->query('SELECT COUNT(*) FROM tags')->fetchColumn();
    $notesCount = (int) $pdo->query('SELECT COUNT(*) FROM article_tag_quotes')->fetchColumn();

    // Fetch tags and count of associated articles for the word cloud
    $cloudTags = $pdo->query('
        SELECT t.id, t.name, t.definition, t.category, COUNT(at.article_id) AS article_count
        FROM tags t
        LEFT JOIN article_tags at ON t.id = at.tag_id
        GROUP BY t.id, t.name, t.definition, t.category
        ORDER BY t.name ASC
    ')->fetchAll();

    $cloudData = prepare_home_tag_cloud($cloudTags);
    $cloudTags = $cloudData['cloudTags'];
    $maxCount = $cloudData['maxCount'];
    $wordList = $cloudData['wordList'];

    try {
        $graphData = prepare_home_tag_graph($pdo, $cloudTags);
        $nodes = $graphData['nodes'];
        $edges = $graphData['edges'];
    } catch (Throwable $exception) {
        // Keep the home page usable if the co-occurrence graph query fails.
    }

} catch (Throwable $exception) {
    $dbError = app_debug_enabled()
        ? $exception->getMessage()
        : 'Nao foi possivel conectar ao banco de dados do Fichario.';
}
?>
<!doctype html>
<html lang="pt-br" data-module="fichario">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel do Fichario Academico</title>
    <link rel="icon" type="image/png" href="../assets/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="assets/app.css?v=20260629-vanilla" rel="stylesheet">
    <link href="assets/tag-visualizations.css?v=20260629-vanilla" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="../assets/js/theme-switcher.js?v=20260629-vanilla"></script>
    <link href="../assets/css/style.css?v=20260629-vanilla" rel="stylesheet">
</head>
<body class="module-page">
    <?php render_navbar('painel'); ?>

    <main class="main-container py-4">
        <header class="page-header mb-4">
            <div>
                <p class="text-body-secondary small text-uppercase fw-semibold mb-1">Fichario</p>
                <h1 class="h2 mb-2">Painel</h1>
                <p class="text-body-secondary mb-0">Acompanhe artigos, marcações e tags do acervo academico.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="articles.php" class="btn btn-primary">
                    <i class="bi bi-file-earmark-plus me-1"></i>Artigos
                </a>
                <a href="tags.php" class="btn btn-outline-secondary">
                    <i class="bi bi-tags me-1"></i>Tags
                </a>
            </div>
        </header>

        <?php if ($dbError !== ''): ?>
            <div class="alert alert-warning mb-4" role="alert">
                <?= h($dbError) ?>
            </div>
        <?php endif; ?>

        <section class="row g-3 mb-4" aria-label="Resumo do fichario">
            <div class="col-12 col-md-4">
                <a href="articles.php" class="card h-100 text-decoration-none">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <div>
                                <p class="text-body-secondary small mb-1">Acervo</p>
                                <h2 class="h5 text-body mb-2">Artigos e documentos</h2>
                                <p class="text-body-secondary small mb-0">Lista completa de estudos cadastrados.</p>
                            </div>
                            <span class="badge text-bg-primary fs-6 counter-val" data-target="<?= $articleCount ?>">0</span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-12 col-md-4">
                <a href="articles.php?status=fichado" class="card h-100 text-decoration-none">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <div>
                                <p class="text-body-secondary small mb-1">Leitura</p>
                                <h2 class="h5 text-body mb-2">Citações e marcações</h2>
                                <p class="text-body-secondary small mb-0">Trechos destacados e comentarios de leitura.</p>
                            </div>
                            <span class="badge text-bg-primary fs-6 counter-val" data-target="<?= $notesCount ?>">0</span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-12 col-md-4">
                <a href="tags.php" class="card h-100 text-decoration-none">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <div>
                                <p class="text-body-secondary small mb-1">Indexacao</p>
                                <h2 class="h5 text-body mb-2">Tags indexadas</h2>
                                <p class="text-body-secondary small mb-0">Assuntos e palavras-chave do acervo.</p>
                            </div>
                            <span class="badge text-bg-primary fs-6 counter-val" data-target="<?= $tagCount ?>">0</span>
                        </div>
                    </div>
                </a>
            </div>
        </section>

        <!-- Nuvem de Palavras -->
        <?php if ($tagCount > 0): ?>
            <div class="row g-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-body d-flex align-items-center justify-content-between">
                            <h2 class="h5 mb-0">Nuvem de tags</h2>
                            <a href="tags.php" class="btn btn-sm btn-outline-secondary">Ver tags</a>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-center align-items-center py-2">
                                <canvas id="word-cloud-canvas"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grafico de relacionamento das tags -->
            <?php if (count($nodes) > 0): ?>
                <div class="row g-4 mt-1">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-body">
                                <h2 class="h5 mb-0">Relacionamento entre tags</h2>
                            </div>
                            <div id="tag-network-container" class="tag-network-container">
                                <div id="tag-network-viewport" class="tag-network-viewport"></div>
                                <div id="tag-network-controls" class="tag-network-controls" aria-label="Filtros do grafo de tags"></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script src="assets/app.js?v=20260603c"></script>
    <script>
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => bootstrap.Tooltip.getOrCreateInstance(el));

        function animateCounters() {
            const counters = document.querySelectorAll('.counter-val');
            counters.forEach((counter) => {
                const target = parseInt(counter.getAttribute('data-target')) || 0;
                if (target === 0) {
                    counter.textContent = '0';
                    return;
                }
                const duration = 1200; // 1.2 seconds for animation
                const startTime = performance.now();
                
                function update(currentTime) {
                    const elapsed = currentTime - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    
                    // Easing function: easeOutQuad
                    const ease = progress * (2 - progress);
                    
                    const currentValue = Math.floor(ease * target);
                    counter.textContent = currentValue.toLocaleString('pt-BR');
                    
                    if (progress < 1) {
                        requestAnimationFrame(update);
                    } else {
                        counter.textContent = target.toLocaleString('pt-BR');
                    }
                }
                
                requestAnimationFrame(update);
            });
        }
        
        document.addEventListener('DOMContentLoaded', animateCounters);

        window.FicharioTagVisualizationsConfig = {
            maxCount: <?= (int)$maxCount ?>,
            wordList: <?= json_encode($wordList, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
            nodes: <?= json_encode($nodes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
            edges: <?= json_encode($edges, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
            renderVisibleOnly: false
        };
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/wordcloud2.js/1.2.2/wordcloud2.min.js"></script>
    <script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
    <script src="assets/tag-visualizations.js?v=20260629-tags3"></script>
</body>
</html>
