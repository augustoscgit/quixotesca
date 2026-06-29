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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root, [data-bs-theme="light"] {
            --bg-color: #f1f5f9;
            --card-bg: rgba(255, 255, 255, 0.78);
            --border-color: rgba(0, 0, 0, 0.08);
            --accent-color: var(--accent-ui);
            --text-color: #1e293b;
            --text-muted: #64748b;
            --navbar-bg: var(--bs-body-bg);
        }
        [data-bs-theme="dark"] {
            --bg-color: #0b0f19;
            --card-bg: rgba(22, 28, 45, 0.74);
            --border-color: rgba(255, 255, 255, 0.08);
            --accent-color: var(--accent-ui);
            --text-color: #f8fafc;
            --text-muted: #94a3b8;
            --navbar-bg: var(--bs-body-bg);
        }
        body { background: var(--bg-color); color: var(--text-color); font-family: Inter, system-ui, sans-serif; }
        .navbar { background: var(--navbar-bg); border-bottom: 1px solid var(--border-color); }
        .glass-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 8px; box-shadow: none; }
        .text-accent { color: var(--accent-color) !important; }
        .text-muted { color: var(--text-muted) !important; }
        .flow-step { border-left: 3px solid var(--accent-border); padding-left: 1rem; }
        .btn-icon { width: 40px; height: 40px; padding: 0 !important; display: inline-flex; align-items: center; justify-content: center; }
    </style>
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="../assets/js/theme-switcher.js"></script>
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
                <a class="btn btn-outline-secondary btn-icon" href="docs/agregadores_hierarquicos.md" title="Ver padrão documentado" aria-label="Ver padrão documentado"><i class="fa-solid fa-file-lines"></i></a>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
}
