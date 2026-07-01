<?php
declare(strict_types=1);

require __DIR__ . '/../../fichario/bootstrap.php';

require_admin();

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
    <link rel="icon" type="image/png" href="../assets/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="assets/app.css?v=20260629-vanilla" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="../assets/js/theme-switcher.js?v=20260629-vanilla"></script>
    <link href="../assets/css/style.css?v=20260629-vanilla" rel="stylesheet">
</head>
<body>
    <?php render_admin_navbar('admin'); ?>
    <main class="main-container py-4">
        <header class="page-header mb-4">
            <div>
                <h1 class="h2 mb-2">Painel Administrativo</h1>
                <p class="text-body-secondary mb-0">Acompanhe o acervo e acesse rotinas de manutencao do Fichario.</p>
            </div>
        </header>
        
        <div class="row g-3 mb-5">
            <div class="col-md-3">
                <div class="card p-3 text-center">
                    <div class="text-secondary small mb-1">Artigos</div>
                    <div class="h3 mb-0 text-body fw-bold"><?= $articleCount ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3 text-center">
                    <div class="text-secondary small mb-1">Tags</div>
                    <div class="h3 mb-0 text-body fw-bold"><?= $tagCount ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3 text-center">
                    <div class="text-secondary small mb-1">Usuários</div>
                    <div class="h3 mb-0 text-body fw-bold"><?= $userCount ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3 text-center">
                    <div class="text-secondary small mb-1">Inativos</div>
                    <div class="h3 mb-0 text-body fw-bold"><?= $inactiveUsers ?></div>
                </div>
            </div>
        </div>
        
        <div class="row g-3 justify-content-center">
            <?php if (is_admin()): ?>
                <div class="col-md-6">
                    <a class="d-block card p-4 text-decoration-none text-reset" href="<?= h(access_url('usuarios.php')) ?>">
                        <h2 class="h5 text-body fw-bold mb-2">Gerenciar Usuários</h2>
                        <p class="text-secondary mb-0">Criar usuários, reenviar confirmação por e-mail e bloquear ou gerenciar acessos de editores/leitores.</p>
                    </a>
                </div>
                <div class="col-md-6">
                    <a class="d-block card p-4 text-decoration-none text-reset" href="admin_docs.php">
                        <h2 class="h5 text-body fw-bold mb-2">Documentação</h2>
                        <p class="text-secondary mb-0">Ler e manter requisitos, documentação administrativa e orientações de desenvolvedor em Markdown versionável.</p>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script src="assets/app.js?v=20260603c"></script>
</body>
</html>
