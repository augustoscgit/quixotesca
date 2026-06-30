<?php
function renderHierarchicalFlowPage(array $flow): void
{
    $slug = $flow['slug'] ?? '';
    $title = $flow['title'] ?? 'Fluxo hierarquico';
    $subtitle = $flow['subtitle'] ?? '';
    $levels = $flow['levels'] ?? [];
    $metrics = $flow['metrics'] ?? [];
    $pages = $flow['pages'] ?? [];
?>
<!DOCTYPE html>
<html lang="pt-BR" data-module="cat">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAT - <?= htmlspecialchars($title) ?></title>
    <link rel="icon" type="image/png" href="../favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --bg-color: var(--bs-body-bg);
            --card-bg: var(--bs-body-bg);
            --border-color: var(--bs-border-color);
            --accent-color: var(--accent-ui);
            --text-color: var(--bs-body-color);
            --text-muted: var(--bs-secondary-color);
            --navbar-bg: var(--bs-body-bg);
        }
        body { background: var(--bg-color); color: var(--text-color); font-family: var(--bs-body-font-family); }
        .navbar { background: var(--navbar-bg); border-bottom: 1px solid var(--border-color); }
        .glass-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 8px; box-shadow: none; }
        .text-accent { color: var(--accent-color) !important; }
        .text-muted { color: var(--text-muted) !important; }
        .flow-step { border-left: 3px solid var(--accent-border); padding-left: 1rem; }
        .btn-icon { width: 40px; height: 40px; padding: 0 !important; display: inline-flex; align-items: center; justify-content: center; }
    </style>
    <link href="../assets/css/style.css?v=20260629-vanilla" rel="stylesheet">
    <script src="../assets/js/theme-switcher.js?v=20260629-vanilla"></script>
</head>
<body>
    <!-- Navbar -->
    <?php
    require_once __DIR__ . '/../../includes/navbar.php';
    render_platform_navbar('cat', $slug);
    ?>

    <main class="container py-5">
        <header class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
            <div>
                <h1 class="display-6 text-accent mb-2" style="font-weight: 800;"><?= htmlspecialchars($title) ?></h1>
                <p class="lead text-secondary mb-0"><?= htmlspecialchars($subtitle) ?></p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary btn-icon" href="docs/agregadores_hierarquicos.md" title="Ver padrão documentado" aria-label="Ver padrão documentado"><i class="bi bi-file-earmark-text"></i></a>
            </div>
        </header>

        <section class="glass-card p-4 mb-4">
            <h2 class="h5 text-accent mb-3">Hierarquia do fluxo</h2>
            <div class="row g-3">
                <?php foreach ($levels as $index => $level): ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="flow-step">
                            <div class="text-muted small">Nível <?= $index + 1 ?></div>
                            <div class="fw-semibold"><?= htmlspecialchars($level) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="row g-4">
            <div class="col-lg-6">
                <div class="glass-card p-4 h-100">
                    <h2 class="h5 text-accent mb-3">Sumarização padrão</h2>
                    <ul class="mb-0">
                        <?php foreach ($metrics as $metric): ?>
                            <li><?= htmlspecialchars($metric) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="glass-card p-4 h-100">
                    <h2 class="h5 text-accent mb-3">Páginas previstas</h2>
                    <ul class="mb-0">
                        <?php foreach ($pages as $page): ?>
                            <li><code><?= htmlspecialchars($page) ?></code></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>
<?php
}
