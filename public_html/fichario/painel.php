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
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg legacy-hidden-navbar py-3 d-none" hidden aria-hidden="true">
        <div class="container">
            <div class="d-flex align-items-center gap-3 ms-auto">
                <!-- User / Login Menu -->
                <?php
                $user = current_user();
                if ($user !== null):
                    $displayName = !empty($user['first_name']) ? $user['first_name'] : $user['name'];
                    $adminItem = '';
                    if (is_admin()) {
                        $adminItem = '<li><a class="dropdown-item" href="admin.php">Painel Admin</a></li>';
                    }
                ?>
                    <div class="dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-1 text-decoration-none" href="#" id="navbarUserDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle fs-5 me-1"></i>
                            Olá, <?= h($displayName) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="navbarUserDropdown">
                            <li><a class="dropdown-item" href="<?= h(access_url('index.php')) ?>">Minha conta</a></li>
                            <?= $adminItem ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= h(access_url('logout.php')) ?>">Sair</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <?php $next = $_SERVER['REQUEST_URI'] ?? app_url('index.php'); ?>
                    <a class="btn btn-outline-primary btn-sm px-3 rounded-pill text-decoration-none" href="<?= h(access_url('login.php?next=' . rawurlencode($next))) ?>">Entrar</a>
                <?php endif; ?>

            </div>
        </div>
    </nav>

    <div class="main-container mt-4">
        <!-- Dashboard Header -->
        <div class="row mb-4">
            <div class="col-12">
                <header class="landing-hero mb-0">
                    <div class="mb-4 text-center">
                        <img src="../assets/img/logo-fundo-escuro-horizontal.png" alt="RENAST" class="platform-logo-img landing-hero-logo mb-4">
                        <h1 class="fw-bold mb-3">Fichário Acadêmico</h1>
                    </div>
                    <p class="subtitle">Sua biblioteca de estudos e pesquisas sobre Saúde do Trabalhador. Salve artigos colando apenas o link da internet, destaque trechos importantes e organize tudo por temas de forma simples e visual.</p>
                    <?php if ($dbError !== ''): ?>
                        <div class="alert alert-warning mt-4 mb-0 text-start" role="alert">
                            <?= h($dbError) ?>
                        </div>
                    <?php endif; ?>
                </header>
            </div>
        </div>

        <!-- Big Buttons Grid -->
        <div class="row g-4 justify-content-center">
            <!-- Articles Navigation -->
            <div class="col-md-4 col-sm-6">
                <a href="articles.php" class="action-card action-card-articles landing-nav-card">
                    <div class="card-counter">
                        <span class="counter-val" data-target="<?= $articleCount ?>">0</span>
                    </div>
                    <h2 class="card-label">Artigos e documentos</h2>
                    <p class="card-desc">Acesse a lista completa de estudos e pesquisas cadastrados, adicione novos materiais e pesquise informações importantes.</p>
                </a>
            </div>

            <!-- Citations Navigation -->
            <div class="col-md-4 col-sm-6">
                <a href="articles.php" class="action-card action-card-citations landing-nav-card">
                    <div class="card-counter">
                        <span class="counter-val" data-target="<?= $notesCount ?>">0</span>
                    </div>
                    <h2 class="card-label">Citações e notas</h2>
                    <p class="card-desc">Veja todos os trechos destacados dos textos, acompanhados de comentários e anotações de leitura.</p>
                </a>
            </div>

            <!-- Tags Navigation -->
            <div class="col-md-4 col-sm-6">
                <a href="tags.php" class="action-card action-card-tags landing-nav-card">
                    <div class="card-counter">
                        <span class="counter-val" data-target="<?= $tagCount ?>">0</span>
                    </div>
                    <h2 class="card-label">Tags indexadas</h2>
                    <p class="card-desc">Explore a lista de assuntos e palavras-chave para encontrar rapidamente todos os estudos que tratam de um mesmo tema.</p>
                </a>
            </div>
        </div>

        <!-- Nuvem de Palavras -->
        <?php if ($tagCount > 0): ?>
            <div class="row mt-5">
                <div class="col-12">
                    <div class="card p-4">
                        <div class="d-flex justify-content-center align-items-center py-2">
                            <canvas id="word-cloud-canvas"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráfico de Relacionamento das Tags -->
            <?php if (count($nodes) > 0): ?>
                <div class="row mt-5">
                    <div class="col-12">
                        <div id="tag-network-container" class="card tag-network-container">
                            <div id="tag-network-viewport" class="tag-network-viewport"></div>
                            <div id="tag-network-controls" class="tag-network-controls" aria-label="Filtros do grafo de tags"></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </div>

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
