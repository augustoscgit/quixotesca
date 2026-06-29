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

$bootstrapCss = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
$bootstrapJs  = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
?>
<!doctype html>
<html lang="pt-BR" data-module="carex">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>CAREX | Metodologia</title>
    <link href="../assets/favicon.png" rel="icon" type="image/png">
    <link href="<?= Security::e($bootstrapCss) ?>" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="assets/app.css" rel="stylesheet">
    <style>
        /* ── Layout ───────────────────────────────────────────────── */
        .metod-layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 1.5rem;
            min-height: calc(100vh - 60px);
            padding-inline: 1.5rem;
        }
        @media (max-width: 991px) {
            .metod-layout { grid-template-columns: 1fr; }
            .metod-toc { display: none; }
        }

        /* ── Sumário lateral ─────────────────────────────────────── */
        .metod-toc {
            position: sticky;
            top: 76px;
            height: fit-content;
            max-height: calc(100vh - 96px);
            overflow-y: auto;
            border: 1px solid var(--bs-border-color);
            border-radius: 8px;
            background: var(--bs-body-bg);
            padding: 1rem;
            margin-top: 1.5rem;
            box-shadow: none;
        }
        .metod-toc h6 {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0;
            color: var(--bs-secondary-color);
            font-weight: 700;
            margin-bottom: 0.75rem;
        }
        .toc-link {
            display: block;
            font-size: 0.82rem;
            color: var(--bs-body-color);
            text-decoration: none;
            padding: 0.35rem 0.5rem;
            border-radius: 6px;
            border-left: 2px solid transparent;
            line-height: 1.4;
            transition: background-color 0.15s, border-color 0.15s, color 0.15s;
        }
        .toc-link:hover { background: var(--bs-tertiary-bg); color: var(--accent-ui); }
        .toc-link.active { background: var(--accent-surface); border-left-color: var(--accent-border); color: var(--accent-surface-text); font-weight: 600; }
        .toc-link.toc-h3 { padding-left: 1.25rem; font-size: 0.78rem; color: var(--bs-secondary-color); }
        .toc-link.toc-h4 { padding-left: 2rem; font-size: 0.75rem; color: var(--bs-tertiary-color); }

        /* ── Conteúdo principal ──────────────────────────────────── */
        .metod-body {
            padding: 2rem 1rem 4rem;
            max-width: 860px;
            font-size: 0.97rem;
            line-height: 1.75;
        }
        .metod-body h1 { font-size: 1.65rem; font-weight: 700; margin-bottom: 0.25rem; }
        .metod-body h2 { font-size: 1.2rem; font-weight: 700; margin-top: 2.5rem; margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--bs-border-color); color: var(--accent-ui) !important; scroll-margin-top: 80px; }
        .metod-body h3 { font-size: 1rem; font-weight: 600; margin-top: 1.75rem; margin-bottom: 0.5rem; scroll-margin-top: 80px; }
        .metod-body h4 { font-size: 0.9rem; font-weight: 600; margin-top: 1.25rem; margin-bottom: 0.4rem; scroll-margin-top: 80px; }
        .metod-body p  { margin-bottom: 0.9rem; }
        .metod-body ul, .metod-body ol { padding-left: 1.5rem; margin-bottom: 0.9rem; }
        .metod-body li { margin-bottom: 0.3rem; }
        .metod-body code { font-size: 0.85em; background: var(--bs-tertiary-bg); padding: 1px 5px; border-radius: 3px; }
        .metod-body pre { background: var(--bs-tertiary-bg); border: 1px solid var(--bs-border-color); border-radius: 6px; padding: 1rem; font-size: 0.85rem; overflow-x: auto; margin: 1rem 0; }
        .metod-body pre code { background: none; padding: 0; }
        .metod-body table { width: 100%; margin: 1rem 0; font-size: 0.875rem; }
        .metod-body table th { background: var(--bs-tertiary-bg); font-weight: 600; }
        .metod-body table th, .metod-body table td { padding: 0.5rem 0.75rem; border: 1px solid var(--bs-border-color); vertical-align: top; }
        .metod-body hr { margin: 2rem 0; border-color: var(--bs-border-color); }
        .metod-body blockquote { border-left: 4px solid var(--accent-border); padding: 0.5rem 1rem; background: var(--accent-surface); color: var(--bs-body-color); border-radius: 0 6px 6px 0; margin: 1rem 0; }
        .metod-body strong { font-weight: 600; }

        /* ── Barra de progresso de leitura ──────────────────────── */
        #readProgress {
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            background: var(--accent-solid);
            width: 0%;
            z-index: 2000;
            transition: width 0.1s linear;
        }

        /* ── Metadados do documento ──────────────────────────────── */
        .doc-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .doc-meta .badge { font-size: 0.75rem; font-weight: 500; }
    </style>
    <script src="../assets/js/theme-switcher.js"></script>
    <link href="../assets/css/style.css" rel="stylesheet">
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
            <div id="mdContent" style="display:none;"><?= Security::e($mdContent) ?></div>
            <div id="mdRendered"></div>
        </div>
    </div>

    <script src="<?= Security::e($bootstrapJs) ?>" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
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
