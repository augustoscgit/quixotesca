<?php

declare(strict_types=1);

use Carex\Http\Security;

$config = require dirname(__DIR__, 2) . '/carex' . '/src/bootstrap.php';

Security::applyHeaders();
Security::allowReadOnlyRequest();

$mdFile = dirname(__DIR__, 2) . '/carex' . '/docs/carex_br_metodologia_especialistas.md';
$mdContent = file_exists($mdFile) ? file_get_contents($mdFile) : '';
$criteriaFile = dirname(__DIR__, 2) . '/carex' . '/criterios-conciliacao.md';
$criteriaContent = file_exists($criteriaFile) ? file_get_contents($criteriaFile) : '';

if (trim($criteriaContent) !== '') {
    $mdContent = trim($mdContent) . "\n\n---\n\n" . trim($criteriaContent);
}

$bootstrapCss = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css';
$bootstrapJs  = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js';
?>
<!doctype html>
<html lang="pt-BR" data-module="carex">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>CAREX | Metodologia</title>
    <link href="../assets/favicon.png" rel="icon" type="image/png">
    <link href="<?= Security::e($bootstrapCss) ?>" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="assets/app.css?v=20260629-vanilla" rel="stylesheet">
<script src="../assets/js/theme-switcher.js?v=20260629-vanilla"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=20260629-vanilla" rel="stylesheet">
</head>
<body>
    <div id="readProgress" aria-hidden="true"></div>

    <?php
    $activePage = 'metodologia';
    require dirname(__DIR__, 2) . '/carex' . '/src/templates/navbar.php';
    ?>

    <div class="metod-layout">
        <!-- Sumário lateral (gerado via JS) -->
        <aside class="metod-toc" id="tocSidebar" aria-label="Sumário do documento">
            <h6><i class="bi bi-list-ul me-1"></i>Sumário</h6>
            <nav id="tocNav"></nav>
        </aside>

        <!-- Conteúdo principal -->
        <div class="metod-body" id="metodBody">
            <!-- Renderizado via JS a partir do Markdown -->
            <div id="mdContent" hidden><?= Security::e($mdContent) ?></div>
            <div id="mdRendered"></div>
        </div>
    </div>

    <script src="<?= Security::e($bootstrapJs) ?>" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script>
    (function () {
        const raw = document.getElementById('mdContent').textContent;
        const rendered = document.getElementById('mdRendered');
        const tocNav   = document.getElementById('tocNav');

        // ── Renderiza Markdown ─────────────────────────────────────
        marked.setOptions({ gfm: true, breaks: false });
        rendered.innerHTML = marked.parse(raw);

        // Estiliza tabelas com Bootstrap
        rendered.querySelectorAll('table').forEach(t => {
            t.className = 'table table-bordered table-striped table-hover align-middle';
        });

        // ── Gera IDs e Sumário ─────────────────────────────────────
        const headings = rendered.querySelectorAll('h1, h2, h3, h4');
        const tocLinks = [];

        headings.forEach((h, i) => {
            const slug = 'sec-' + i + '-' + h.textContent.trim()
                .toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .substring(0, 50);
            h.id = slug;

            const level = h.tagName.toLowerCase(); // h1, h2, h3, h4
            const link  = document.createElement('a');
            link.href = '#' + slug;
            link.textContent = h.textContent;
            link.className = 'toc-link toc-' + level;
            link.dataset.target = slug;
            tocNav.appendChild(link);
            tocLinks.push(link);
        });

        // ── Destaque de seção ativa via IntersectionObserver ────────
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    tocLinks.forEach(l => l.classList.remove('active'));
                    const active = tocLinks.find(l => l.dataset.target === entry.target.id);
                    if (active) {
                        active.classList.add('active');
                        active.scrollIntoView({ block: 'nearest' });
                    }
                }
            });
        }, { rootMargin: '-10% 0px -75% 0px' });

        headings.forEach(h => observer.observe(h));

        // ── Barra de progresso de leitura ───────────────────────────
        const bar = document.getElementById('readProgress');
        window.addEventListener('scroll', () => {
            const docH = document.documentElement.scrollHeight - window.innerHeight;
            const pct  = docH > 0 ? (window.scrollY / docH) * 100 : 0;
            bar.style.width = pct.toFixed(1) + '%';
        }, { passive: true });
    })();
    </script>
</body>
</html>
