<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

require_login();

$pdo = db();
$articleCount = (int) $pdo->query('SELECT COUNT(*) FROM articles')->fetchColumn();
$tagCount = (int) $pdo->query('SELECT COUNT(*) FROM tags')->fetchColumn();
$userCount = access_users_count();
$inactiveUsers = access_inactive_users_count();
?>
<!doctype html>
<html lang="pt-br" data-module="fichario">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel Administrativo - Fichário Acadêmico</title>
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/app.css?v=20260603h" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
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
    </style>
</head>
<body>
    <!-- Background Animated Blobs -->
    <div class="blob blob-blue"></div>
    <div class="blob blob-purple"></div>

    <?php render_admin_navbar('admin'); ?>
    <main class="container py-4 main-container" style="position: relative; z-index: 10;">
        <h1 class="h3 mb-4 text-white fw-bold">Painel Administrativo</h1>
        
        <div class="row g-3 mb-5">
            <div class="col-md-3">
                <div class="glass-card p-3 text-center">
                    <div class="text-secondary small mb-1">Artigos</div>
                    <div class="h3 mb-0 text-white fw-bold"><?= $articleCount ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="glass-card p-3 text-center">
                    <div class="text-secondary small mb-1">Tags</div>
                    <div class="h3 mb-0 text-white fw-bold"><?= $tagCount ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="glass-card p-3 text-center">
                    <div class="text-secondary small mb-1">Usuários</div>
                    <div class="h3 mb-0 text-white fw-bold"><?= $userCount ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="glass-card p-3 text-center">
                    <div class="text-secondary small mb-1">Inativos</div>
                    <div class="h3 mb-0 text-white fw-bold"><?= $inactiveUsers ?></div>
                </div>
            </div>
        </div>
        
        <div class="row g-3 justify-content-center">
            <?php if (is_admin()): ?>
                <div class="col-md-6">
                    <a class="d-block glass-card p-4 text-decoration-none text-reset" href="<?= h(access_url('usuarios.php')) ?>">
                        <h2 class="h5 text-white fw-bold mb-2">Gerenciar Usuários</h2>
                        <p class="text-secondary mb-0">Criar usuários, reenviar confirmação por e-mail e bloquear ou gerenciar acessos de editores/leitores.</p>
                    </a>
                </div>
                <div class="col-md-6">
                    <a class="d-block glass-card p-4 text-decoration-none text-reset" href="admin_docs.php">
                        <h2 class="h5 text-white fw-bold mb-2">Documentação</h2>
                        <p class="text-secondary mb-0">Ler e manter requisitos, documentação administrativa e orientações de desenvolvedor em Markdown versionável.</p>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/app.js?v=20260603c"></script>
</body>
</html>
