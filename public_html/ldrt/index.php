<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="pt-BR" data-module="ldrt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LDRT - RENAST</title>
    <link rel="icon" type="image/png" href="../assets/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="../assets/js/theme-switcher.js?v=20260629-vanilla"></script>
    <link href="../assets/css/style.css?v=20260629-vanilla" rel="stylesheet">
</head>
<body class="landing-page d-flex flex-column min-vh-100">
    <?php
    require_once __DIR__ . '/../../includes/navbar.php';
    render_platform_navbar('ldrt', 'landing');
    ?>

    <main class="flex-grow-1">
        <section class="container landing-hero py-5 my-4">
            <div class="row justify-content-center text-center">
                <div class="col-lg-9 col-xl-8">
                    <img src="../assets/img/logo-fundo-escuro-horizontal.png" alt="RENAST" class="platform-logo-img landing-hero-logo mb-4">
                    <div class="landing-eyebrow mb-3">
                        <i class="bi bi-virus me-1"></i> Lista de Doencas Relacionadas ao Trabalho
                    </div>
                    <h1 class="display-5 fw-semibold mb-3">Modulo LDRT</h1>
                    <p class="lead text-body-secondary mb-4">
                        Consulte relacoes entre doencas, agentes de risco, atividades economicas e ocupacoes em uma base estruturada para apoio tecnico e epidemiologico.
                    </p>
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <a href="consulta.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-search me-2"></i> Iniciar consulta
                        </a>
                        <a href="lista_a.php" class="btn btn-outline-primary btn-lg">
                            <i class="bi bi-table me-2"></i> Ver tabelas
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="container pb-5">
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-4">
                <div class="col">
                    <a href="consulta.php" class="card landing-card h-100 text-decoration-none">
                        <div class="card-body">
                            <span class="landing-card-icon"><i class="bi bi-shuffle"></i></span>
                            <h2 class="h5 mt-3">Consulta cruzada</h2>
                            <p class="text-body-secondary mb-0">Combine CID, CNAE, CBO e agentes para encontrar relacoes da lista.</p>
                        </div>
                    </a>
                </div>
                <div class="col">
                    <a href="explorar_cid.php" class="card landing-card h-100 text-decoration-none">
                        <div class="card-body">
                            <span class="landing-card-icon"><i class="bi bi-folder-tree"></i></span>
                            <h2 class="h5 mt-3">CID</h2>
                            <p class="text-body-secondary mb-0">Navegue pela estrutura de doencas e veja associacoes ocupacionais.</p>
                        </div>
                    </a>
                </div>
                <div class="col">
                    <a href="explorar_cnae_cbo.php" class="card landing-card h-100 text-decoration-none">
                        <div class="card-body">
                            <span class="landing-card-icon"><i class="bi bi-diagram-3"></i></span>
                            <h2 class="h5 mt-3">CNAE e CBO</h2>
                            <p class="text-body-secondary mb-0">Explore atividades economicas, ocupacoes e conexoes relacionadas.</p>
                        </div>
                    </a>
                </div>
                <div class="col">
                    <a href="rag.php" class="card landing-card h-100 text-decoration-none">
                        <div class="card-body">
                            <span class="landing-card-icon"><i class="bi bi-robot"></i></span>
                            <h2 class="h5 mt-3">RAG</h2>
                            <p class="text-body-secondary mb-0">Use recursos assistidos para perguntas sobre a base e seus termos.</p>
                        </div>
                    </a>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-top py-4 mt-auto">
        <div class="container small text-body-secondary text-center">
            Plataforma RENAST Online
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>
