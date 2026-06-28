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
    <title>Fichário Acadêmico</title>
    <link rel="icon" type="image/png" href="../assets/favicon.png">
    <!-- Google Fonts: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/app.css?v=20260603h" rel="stylesheet">
    <link href="assets/tag-visualizations.css?v=20260625" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="../assets/js/theme-switcher.js"></script>
    
    <style>
        :root, [data-bs-theme="light"] {
            --color-citations: var(--accent);
            --color-citations-glow: transparent;
            --color-articles-counter: var(--accent);
            --color-citations-counter: var(--accent);
            --color-tags-counter: var(--accent);
        }
        [data-bs-theme="dark"] {
            --color-citations: var(--accent);
            --color-citations-glow: transparent;
            --color-articles-counter: var(--accent);
            --color-citations-counter: var(--accent);
            --color-tags-counter: var(--accent);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bs-body-bg);
            color: var(--bs-body-color);
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        .main-container {
            z-index: 10;
            position: relative;
            max-width: 1100px;
            width: 100%;
            padding: 2rem;
            margin: 0 auto;
        }

        .glass-header {
            backdrop-filter: none;
            background-color: var(--bs-body-bg);
            border: 1px solid var(--bs-border-color);
            border-radius: 8px;
            padding: clamp(2rem, 5vw, 4rem);
            margin-bottom: 2.5rem;
            text-align: center;
            box-shadow: none;
        }

        .glass-header h1 {
            font-size: clamp(2.4rem, 5vw, 4.25rem) !important;
            line-height: 1.05;
        }

        .main-title {
            font-weight: 800;
            font-size: 3rem;
            background: none;
            -webkit-background-clip: initial;
            -webkit-text-fill-color: initial;
            letter-spacing: 0;
            color: var(--bs-heading-color);
        }

        .subtitle {
            color: var(--bs-secondary-color);
            font-size: clamp(1.1rem, 2vw, 1.35rem);
            font-weight: 400;
            max-width: 760px;
            margin: 0.5rem auto 0 auto;
        }

        /* Large Action Cards as Big Buttons */
        .action-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            backdrop-filter: none;
            background-color: var(--bs-body-bg);
            border: 1px solid var(--bs-border-color);
            border-radius: 8px;
            padding: 3rem 2rem;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            color: inherit;
            position: relative;
            overflow: hidden;
            box-shadow: none;
            height: 100%;
            text-align: center;
        }

        .action-card::before {
            display: none;
        }

        .action-card-articles:hover,
        .action-card-citations:hover,
        .action-card-tags:hover {
            transform: none;
            border-color: var(--accent) !important;
            box-shadow: none !important;
        }

        .card-counter {
            font-size: clamp(3.5rem, 8vw, 4.8rem);
            font-weight: 800;
            line-height: 1;
            margin: 0 auto 1.5rem auto;
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            display: inline-block;
            letter-spacing: 0;
        }

        .action-card:hover .card-counter {
            transform: none;
        }

        .action-card-articles .card-counter {
            color: var(--color-articles-counter);
            text-shadow: none;
        }

        .action-card-citations .card-counter {
            color: var(--color-citations-counter);
            text-shadow: none;
        }

        .action-card-tags .card-counter {
            color: var(--color-tags-counter);
            text-shadow: none;
        }

        .card-label {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            letter-spacing: 0;
            display: inline-block;
            padding: 0.35rem 1.1rem;
            border-radius: 50px;
        }

        .action-card-articles .card-label {
            background: var(--bs-tertiary-bg);
            color: var(--accent);
            border: 1px solid var(--bs-border-color);
        }

        .action-card-citations .card-label {
            background: var(--bs-tertiary-bg);
            color: var(--accent);
            border: 1px solid var(--bs-border-color);
        }

        .action-card-tags .card-label {
            background: var(--bs-tertiary-bg);
            color: var(--accent);
            border: 1px solid var(--bs-border-color);
        }

        .card-desc {
            color: var(--bs-secondary-color);
            font-size: 0.95rem;
            font-weight: 300;
            line-height: 1.5;
            margin-bottom: 1.5rem;
        }



        .quick-actions-bar {
            text-align: center;
            margin-top: 3.5rem;
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .btn-register-action {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.9rem 2.2rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.05rem;
            color: #ffffff;
            background: var(--bs-primary);
            border: 1px solid var(--bs-primary);
            box-shadow: none;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            text-decoration: none;
        }

        .btn-register-action:hover {
            transform: none;
            box-shadow: none;
            color: #ffffff;
        }

        .btn-register-action svg {
            transition: transform 0.3s;
        }

        .btn-register-action:hover svg {
            transform: none;
        }
        
        .btn-secondary-action {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.9rem 2.2rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.05rem;
            color: #ffffff;
            background: transparent;
            border: 1px solid var(--bs-primary);
            box-shadow: none;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            text-decoration: none;
        }
        
        .btn-secondary-action:hover {
            transform: none;
            background: var(--bs-tertiary-bg);
            border-color: var(--bs-primary);
            color: var(--bs-primary);
        }
        
        .btn-secondary-action svg {
            transition: transform 0.3s;
        }
        .btn-secondary-action:hover svg {
            transform: none;
        }
        .tag-badge:hover {
            transform: none !important;
            box-shadow: none;
        }

        /* Light theme overrides for glass header, action cards, and text contrast */
        [data-bs-theme="light"] .action-card {
            background-color: var(--bs-body-bg) !important;
            border-color: rgba(0, 0, 0, 0.08) !important;
            box-shadow: none !important;
        }
        
        [data-bs-theme="light"] .subtitle,
        [data-bs-theme="light"] .card-desc {
            color: var(--text-muted) !important;
        }

        [data-bs-theme="light"] .action-card::before {
            background: transparent !important;
        }

        /* Bootstrap 5.3 baseline for the landing boxes-as-buttons */
        .action-card,
        .action-card-articles,
        .action-card-citations,
        .action-card-tags {
            background: var(--bs-body-bg) !important;
            border: 1px solid var(--bs-border-color) !important;
            border-radius: var(--bs-border-radius) !important;
            color: var(--bs-body-color) !important;
            box-shadow: none !important;
            transform: none !important;
        }

        .action-card:hover,
        .action-card:focus,
        .action-card:focus-visible,
        .action-card-articles:hover,
        .action-card-citations:hover,
        .action-card-tags:hover {
            background: var(--bs-tertiary-bg) !important;
            border-color: var(--bs-border-color) !important;
            color: var(--bs-body-color) !important;
            box-shadow: none !important;
            outline: 0 !important;
            transform: none !important;
        }

        .action-card:focus-visible {
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
        }



        .action-card .card-label {
            -webkit-background-clip: initial !important;
            background-clip: initial !important;
            -webkit-text-fill-color: initial !important;
            letter-spacing: 0 !important;
        }

        .action-card-articles .card-label {
            background: var(--bs-tertiary-bg) !important;
            color: var(--accent) !important;
            border: 1px solid var(--bs-border-color) !important;
        }

        .action-card-citations .card-label {
            background: var(--bs-tertiary-bg) !important;
            color: var(--accent) !important;
            border: 1px solid var(--bs-border-color) !important;
        }

        .action-card-tags .card-label {
            background: var(--bs-tertiary-bg) !important;
            color: var(--accent) !important;
            border: 1px solid var(--bs-border-color) !important;
        }


        .action-card .card-desc {
            color: var(--bs-secondary-color) !important;
            font-weight: 400 !important;
        }



        .glass-card .text-white {
            color: var(--bs-body-color) !important;
        }

        .word-cloud-item {
            color: var(--bs-body-color);
            transition: all 0.25s ease-in-out;
        }

        .word-cloud-item:hover {
            color: var(--accent) !important;
            opacity: 1 !important;
            transform: none;
        }

    </style>
    
</head>
<body class="landing-page">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg app-navbar py-3">
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

                <!-- Theme Toggler -->
                <div class="dropdown">
                    <button class="btn btn-link nav-link dropdown-toggle d-flex align-items-center" id="bd-theme" type="button" aria-expanded="false" data-bs-toggle="dropdown" aria-label="Alternar tema (auto)">
                        <i class="theme-icon-active bi bi-circle-half"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="bd-theme">
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center gap-2" data-bs-theme-value="light" aria-pressed="false">
                                <i class="bi bi-sun-fill opacity-50"></i> Claro <i class="bi bi-check2 ms-auto d-none"></i>
                            </button>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center gap-2" data-bs-theme-value="dark" aria-pressed="false">
                                <i class="bi bi-moon-stars-fill opacity-50"></i> Escuro <i class="bi bi-check2 ms-auto d-none"></i>
                            </button>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center gap-2" data-bs-theme-value="auto" aria-pressed="true">
                                <i class="bi bi-circle-half opacity-50"></i> Auto <i class="bi bi-check2 ms-auto d-none"></i>
                            </button>
                        </li>
                    </ul>
                </div>
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
                    <div class="glass-card p-4">
                        <div class="d-flex justify-content-center align-items-center py-2" style="position: relative; overflow: hidden; width: 100%;">
                            <canvas id="word-cloud-canvas" style="width: 100%; max-width: 900px; height: 420px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráfico de Relacionamento das Tags -->
            <?php if (count($nodes) > 0): ?>
                <div class="row mt-5">
                    <div class="col-12">
                        <div id="tag-network-container" class="glass-card tag-network-container">
                            <div id="tag-network-viewport" class="tag-network-viewport"></div>
                            <div id="tag-network-controls" class="tag-network-controls" aria-label="Filtros do grafo de tags"></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
    <script src="assets/tag-visualizations.js?v=20260625"></script>
</body>
</html>
