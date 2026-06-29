<?php

declare(strict_types=1);

use Carex\Http\Security;

$config = null;
try {
    $config = require __DIR__ . '/src/bootstrap.php';
} catch (Throwable $e) {
    if (!class_exists(Security::class, true)) {
        require_once __DIR__ . '/src/Http/Security.php';
    }
}

Security::applyHeaders();
Security::allowReadOnlyRequest();
\Carex\Http\Auth::startSession();

$landingFile = __DIR__ . '/landing.md';
$landingContent = file_exists($landingFile) ? file_get_contents($landingFile) : '';

$sobreFile = __DIR__ . '/sobre.md';
$sobreContent = file_exists($sobreFile) ? file_get_contents($sobreFile) : '';

$settingsFile = __DIR__ . '/config/settings.json';
$settings = json_decode(file_exists($settingsFile) ? file_get_contents($settingsFile) : '{}', true);
$allowMarkdownEdit = $settings['allow_markdown_edit'] ?? true;

$numMatrices       = 0;
$numCategories     = 0;
$numClassifications = 0;

try {
    if ($config === null || !isset($config['database'])) {
        throw new RuntimeException("Configuração do banco de dados não carregada.");
    }
    $pdo = Carex\Database\Connection::make($config['database']);
    $numMatrices = (int) $pdo->query("select count(*) from carex.tb_matriz")->fetchColumn();

    $tablesStmt = $pdo->query("select table_name from information_schema.tables where table_schema='carex' and table_type='BASE TABLE' and (table_name like 'cbo_%' or table_name like 'cnae_%') and table_name <> 'cbo_perfil_ocupacional'");
    $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
    $catSum = 0;
    foreach ($tables as $t) {
        $catSum += (int) $pdo->query("select count(*) from carex.\"$t\"")->fetchColumn();
    }
    $numCategories = $catSum;

    $numClassifications = (int) $pdo->query("select count(*) from carex.tb_matriz_classificacao where co_classificacao <> '9'")->fetchColumn();
} catch (Throwable $e) {
    $numMatrices       = 6;
    $numCategories     = 16046;
    $numClassifications = 10214;
}

$bootstrapCss = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
$bootstrapJs  = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
?><!doctype html>
<html lang="pt-BR" data-module="carex">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Carex-BR | Portal</title>
    <link href="public/assets/favicon.png" rel="icon" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root, [data-bs-theme="light"] {
            --bg:        var(--bs-body-bg);
            --surface:   var(--card-bg);
            --surface2:  var(--card-hover-bg);
            --accent:    var(--accent);
            --border:    var(--bs-border-color);
            --border-md: var(--bs-border-color);
            --text:      var(--bs-body-color);
            --muted:     var(--bs-secondary-color);
            --faint:     var(--bs-secondary-color);
        }
        [data-bs-theme="dark"] {
            --bg:        var(--bs-body-bg);
            --surface:   var(--card-bg);
            --surface2:  var(--card-hover-bg);
            --accent:    var(--accent);
            --border:    var(--bs-border-color);
            --border-md: var(--bs-border-color);
            --text:      var(--bs-body-color);
            --muted:     var(--bs-secondary-color);
            --faint:     var(--bs-secondary-color);
        }
        body {
            background: var(--bs-body-bg);
            color: var(--bs-body-color);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }
        /* ── HERO ────────────────────────────────────── */
        .cx-hero {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: clamp(2.5rem, 6vw, 5rem) 24px clamp(2rem, 5vw, 4rem);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .cx-hero::before { display: none; }
        /* logo in hero */
        .cx-hero-logo {
            width: auto;
            max-width: min(520px, 86vw);
            margin-bottom: 2rem;
        }
        @keyframes cx-pulse { 0%,100%{opacity:1} 50%{opacity:.35} }
        /* hero markdown area */
        #cx-hero-content h1 {
            font-size: clamp(2.4rem, 5vw, 4.25rem);
            font-weight: 800;
            letter-spacing: 0;
            line-height: 1.1;
            color: var(--bs-body-color);
            margin-bottom: 1rem;
        }
        #cx-hero-content h1 em,
        #cx-hero-content h1 strong {
            font-style: normal;
            font-weight: inherit;
            color: var(--accent-ui);
        }
        #cx-hero-content h2 {
            font-size: 11px;
            font-weight: 500;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--faint);
            margin-bottom: 20px;
        }
        #cx-hero-content p {
            font-size: clamp(1.1rem, 2vw, 1.35rem);
            color: var(--bs-secondary-color);
            line-height: 1.65;
            max-width: 760px;
            margin: 0 auto 0;
        }
        .cx-hero-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 1.75rem;
            margin-bottom: 2rem;
        }
        .cx-btn-primary {
            background: var(--bs-primary);
            color: #fff;
            border: 1px solid var(--bs-primary);
            border-radius: 10px;
            padding: 13px 28px;
            font-size: 15px;
            font-weight: 500;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: background-color .2s, border-color .2s;
        }
        .cx-btn-primary:hover { background: var(--primary-hover); border-color: var(--primary-hover); color: #fff; }
        .cx-btn-ghost {
            background: transparent;
            color: var(--muted);
            border: 0.5px solid var(--border-md);
            border-radius: 10px;
            padding: 13px 24px;
            font-size: 15px;
            font-family: inherit;
            cursor: pointer;
            transition: all .2s;
        }
        .cx-btn-ghost:hover { background: var(--bs-tertiary-bg); color: var(--text); }
        /* ── STATS ───────────────────────────────────── */
        .cx-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1px;
            background: var(--border);
            border: 0.5px solid var(--border);
            border-radius: var(--bs-border-radius);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
        }
        .cx-stat {
            background: var(--bs-body-bg);
            padding: 22px 16px;
            text-align: center;
        }
        .cx-stat-num {
            font-size: 26px;
            font-weight: 600;
            color: var(--accent-ui);
            letter-spacing: 0;
            line-height: 1;
            margin-bottom: 6px;
        }
        .cx-stat-lbl {
            font-size: 11px;
            color: var(--bs-secondary-color);
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        /* ── DIVIDER ─────────────────────────────────── */
        .cx-hr { border: none; border-top: 0.5px solid var(--border); margin: 0; }
        /* ── SECTIONS ────────────────────────────────── */
        .cx-section { padding: 60px 40px; max-width: 860px; margin: 0 auto; width: 100%; }
        .cx-section-label {
            font-size: 11px;
            color: var(--accent-ui);
            text-transform: uppercase;
            letter-spacing: .12em;
            font-weight: 500;
            margin-bottom: 10px;
        }
        .cx-section-title {
            font-size: 22px;
            font-weight: 500;
            color: var(--text);
            margin-bottom: 28px;
        }
        /* ── SOBRE markdown prose ────────────────────── */
        #cx-sobre-content h1, #cx-sobre-content h2 {
            font-size: 17px;
            font-weight: 600;
            color: var(--text);
            margin: 28px 0 12px;
            padding-left: 12px;
            border-left: 2px solid var(--accent-border);
        }
        #cx-sobre-content h1:first-child, #cx-sobre-content h2:first-child { margin-top: 0; }
        #cx-sobre-content p {
            font-size: 14px;
            color: var(--muted);
            line-height: 1.7;
            margin-bottom: 14px;
        }
        #cx-sobre-content strong { color: var(--text); font-weight: 600; }
        #cx-sobre-content em { color: var(--muted); font-style: italic; }
        /* ── EDIT BUTTONS ────────────────────────────── */
        .cx-edit-btn {
            position: absolute;
            top: 10px; right: 10px;
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 6px;
            background: rgba(255,255,255,0.04);
            border: 0.5px solid var(--border-md);
            color: var(--faint);
            text-decoration: none;
            opacity: .6;
            transition: opacity .2s;
        }
        .cx-edit-btn:hover { opacity: 1; color: var(--text); }
        /* ── FOOTER ──────────────────────────────────── */
        .cx-footer {
            padding: 22px 40px;
            border-top: 0.5px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .cx-footer-copy { font-size: 12px; color: var(--bs-secondary-color); }
        .cx-footer-orgs { display: flex; align-items: center; gap: 14px; }
        .cx-footer-org  { font-size: 11px; color: var(--bs-secondary-color); letter-spacing: .04em; }
        /* ── RESPONSIVE ──────────────────────────────── */
        @media (max-width: 640px) {
            .cx-hero { padding: 52px 24px 48px; }
            .cx-hero-logo { height: 82px; }
            .cx-section { padding: 44px 24px; }
            .cx-footer { padding: 20px 24px; }
            .cx-stats { max-width: 100%; }
        }
    </style>
    <script src="../assets/js/theme-switcher.js"></script>
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="landing-page">
    <!-- Navbar -->
    <?php
    require_once __DIR__ . '/../includes/navbar.php';
    render_platform_navbar('carex', 'landing');
    ?>
    <!-- ── HERO ─────────────────────────────────────── -->
    <section class="cx-hero landing-hero text-center">
        <img src="../assets/img/logo-fundo-escuro-horizontal.png" alt="RENAST" class="platform-logo-img landing-hero-logo cx-hero-logo">
        <!-- Markdown hero content -->
        <div id="cx-hero-content" style="position:relative; width:100%;">
            <!-- preenchido via JS -->
        </div>
        <!-- Buttons -->
        <div class="cx-hero-actions landing-actions">
            <a href="public/matrizes.php" class="cx-btn-primary">
                <i class="bi bi-rocket-takeoff-fill"></i> Acessar plataforma
            </a>
            <button type="button" id="cx-btn-sobre" class="cx-btn-ghost">
                Sobre o Carex-BR
            </button>
        </div>
        <!-- Stats -->
        <div class="cx-stats landing-stat-grid">
            <div class="cx-stat landing-stat">
                <div class="cx-stat-num landing-stat-number counter" data-target="<?= $numMatrices ?>">0</div>
                <div class="cx-stat-lbl landing-stat-label">Matrizes</div>
            </div>
            <div class="cx-stat landing-stat">
                <div class="cx-stat-num landing-stat-number counter" data-target="<?= $numCategories ?>">0</div>
                <div class="cx-stat-lbl landing-stat-label">Categorias</div>
            </div>
            <div class="cx-stat landing-stat">
                <div class="cx-stat-num landing-stat-number counter" data-target="<?= $numClassifications ?>">0</div>
                <div class="cx-stat-lbl landing-stat-label">Analisadas</div>
            </div>
        </div>
    </section>
    <hr class="cx-hr">
    <!-- ── SOBRE ─────────────────────────────────────── -->
    <section id="sobre" class="cx-section" style="position:relative;">
        <?php if ($allowMarkdownEdit): ?>
        <a href="editor.php?file=sobre" class="cx-edit-btn" title="Editar texto">
            <i class="bi bi-pencil-square"></i> Editar
        </a>
        <?php endif; ?>
        <p class="cx-section-label">Contexto</p>
        <h2 class="cx-section-title">O que é o Carex-BR</h2>
        <div id="cx-sobre-content">
            <!-- preenchido via JS -->
        </div>
    </section>
    <hr class="cx-hr">
    <!-- ── FOOTER ────────────────────────────────────── -->
    <footer class="cx-footer">
        <span class="cx-footer-copy">
            Carex-BR &copy; <?= date('Y') ?> &bull; Ministério da Saúde · RENAST
        </span>
        <div class="cx-footer-orgs">
            <span class="cx-footer-org">INCA</span>
            <span class="cx-footer-org">·</span>
            <span class="cx-footer-org">Fundacentro</span>
            <span class="cx-footer-org">·</span>
            <span class="cx-footer-org">DSAST/MS</span>
        </div>
    </footer>
    <!-- ── SCRIPTS ───────────────────────────────────── -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script>
    (() => {
        const landingMd = <?= json_encode($landingContent) ?>;
        const sobreMd   = <?= json_encode($sobreContent)   ?>;
        const allowEdit = <?= json_encode($allowMarkdownEdit) ?>;
        /* ── Render hero markdown ── */
        if (landingMd.trim()) {
            const tmp = document.createElement('div');
            tmp.innerHTML = marked.parse(landingMd);
            const h1 = tmp.querySelector('h1');
            if (h1) h1.removeAttribute('class');
            const h2 = tmp.querySelector('h2');
            if (h2) h2.removeAttribute('class');
            const editBtn = allowEdit
                ? `<a href="editor.php?file=landing" class="cx-edit-btn" title="Editar banner">
                       <i class="bi bi-pencil-square"></i> Editar
                   </a>`
                : '';
            document.getElementById('cx-hero-content').innerHTML =
                editBtn + tmp.innerHTML;
        }
        /* ── Render sobre markdown ── */
        if (sobreMd.trim()) {
            document.getElementById('cx-sobre-content').innerHTML =
                marked.parse(sobreMd);
        }
        /* ── Scroll to #sobre ── */
        document.getElementById('cx-btn-sobre').addEventListener('click', () => {
            document.getElementById('sobre').scrollIntoView({ behavior: 'smooth' });
        });
        /* ── Counter animation ── */
        const duration = 1400;
        document.querySelectorAll('.counter').forEach(el => {
            const target = parseInt(el.dataset.target, 10);
            if (isNaN(target)) return;
            const t0 = performance.now();
            (function tick(now) {
                const p = Math.min((now - t0) / duration, 1);
                const ease = p * (2 - p);
                el.textContent = Math.floor(ease * target).toLocaleString('pt-BR');
                if (p < 1) requestAnimationFrame(tick);
                else el.textContent = target.toLocaleString('pt-BR');
            })(t0);
        });
    })();
    </script>
</body>
</html>
