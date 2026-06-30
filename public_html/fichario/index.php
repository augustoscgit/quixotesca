<?php
declare(strict_types=1);

require __DIR__ . '/../../fichario/bootstrap.php';
?>
<!doctype html>
<html lang="pt-br" data-module="fichario">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fichario Academico - RENAST</title>
    <link rel="icon" type="image/png" href="../assets/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="../assets/js/theme-switcher.js?v=20260629-vanilla"></script>
    <link href="../assets/css/style.css?v=20260629-vanilla" rel="stylesheet">
    <link href="assets/app.css?v=20260629-vanilla" rel="stylesheet">
</head>
<body class="landing-page d-flex flex-column min-vh-100">
    <?php render_navbar('landing'); ?>

    <main class="flex-grow-1">
        <section class="container landing-hero py-5 my-4">
            <div class="row justify-content-center text-center">
                <div class="col-lg-9 col-xl-8">
                    <img src="../assets/img/logo-fundo-escuro-horizontal.png" alt="RENAST" class="platform-logo-img landing-hero-logo mb-4">
                    <div class="landing-eyebrow mb-3">
                        <i class="bi bi-journal-text me-1"></i> Biblioteca de leitura e pesquisa
                    </div>
                    <h1 class="display-5 fw-semibold mb-3">Fichario Academico</h1>
                    <p class="lead text-body-secondary mb-4">
                        Organize artigos, documentos, citacoes, notas de leitura e tags tematicas em um ambiente unico para pesquisa em Saude do Trabalhador.
                    </p>
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <a href="articles.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-file-earmark-text me-2"></i> Acessar artigos
                        </a>
                        <a href="painel.php" class="btn btn-outline-primary btn-lg">
                            <i class="bi bi-speedometer2 me-2"></i> Ver painel
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="container pb-5">
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-4">
                <div class="col">
                    <a href="articles.php" class="card landing-card h-100 text-decoration-none">
                        <div class="card-body">
                            <span class="landing-card-icon"><i class="bi bi-file-earmark-text"></i></span>
                            <h2 class="h5 mt-3">Artigos e documentos</h2>
                            <p class="text-body-secondary mb-0">Cadastre, consulte e revise materiais bibliograficos do acervo.</p>
                        </div>
                    </a>
                </div>
                <div class="col">
                    <a href="projects.php" class="card landing-card h-100 text-decoration-none">
                        <div class="card-body">
                            <span class="landing-card-icon"><i class="bi bi-folder2-open"></i></span>
                            <h2 class="h5 mt-3">Projetos</h2>
                            <p class="text-body-secondary mb-0">Agrupe leituras e fichamentos por linhas de pesquisa ou produtos.</p>
                        </div>
                    </a>
                </div>
                <div class="col">
                    <a href="tags.php" class="card landing-card h-100 text-decoration-none">
                        <div class="card-body">
                            <span class="landing-card-icon"><i class="bi bi-tags"></i></span>
                            <h2 class="h5 mt-3">Tags</h2>
                            <p class="text-body-secondary mb-0">Explore temas, conceitos e relacoes entre assuntos do acervo.</p>
                        </div>
                    </a>
                </div>
                <div class="col">
                    <a href="timeline.php" class="card landing-card h-100 text-decoration-none">
                        <div class="card-body">
                            <span class="landing-card-icon"><i class="bi bi-clock-history"></i></span>
                            <h2 class="h5 mt-3">Linha do tempo</h2>
                            <p class="text-body-secondary mb-0">Acompanhe leituras, fichamentos e movimentos recentes da base.</p>
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
