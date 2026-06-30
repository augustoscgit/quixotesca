<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="pt-BR" data-module="carex">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Carex-BR - RENAST</title>
    <link rel="icon" type="image/png" href="../assets/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="../assets/js/theme-switcher.js?v=20260629-vanilla"></script>
    <link href="../assets/css/style.css?v=20260629-vanilla" rel="stylesheet">
</head>
<body class="landing-page d-flex flex-column min-vh-100">
    <?php
    require_once __DIR__ . '/../../includes/navbar.php';
    render_platform_navbar('carex', 'landing');
    ?>

    <main class="flex-grow-1">
        <section class="container landing-hero py-5 my-4">
            <div class="row justify-content-center text-center">
                <div class="col-lg-9 col-xl-8">
                    <img src="../assets/img/logo-fundo-escuro-horizontal.png" alt="RENAST" class="platform-logo-img landing-hero-logo mb-4">
                    <div class="landing-eyebrow mb-3">
                        <i class="bi bi-activity me-1"></i> Exposicao ocupacional a carcinogenicos
                    </div>
                    <h1 class="display-5 fw-semibold mb-3">Carex-BR</h1>
                    <p class="lead text-body-secondary mb-4">
                        Acesse matrizes, metodologia e recursos de administracao para estimativas e classificacoes de exposicao ocupacional no Brasil.
                    </p>
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <a href="matrizes.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-table me-2"></i> Ver matrizes
                        </a>
                        <a href="metodologia.php" class="btn btn-outline-primary btn-lg">
                            <i class="bi bi-journal-text me-2"></i> Metodologia
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="container pb-5">
            <div class="row row-cols-1 row-cols-md-3 g-4">
                <div class="col">
                    <a href="matrizes.php" class="card landing-card h-100 text-decoration-none">
                        <div class="card-body">
                            <span class="landing-card-icon"><i class="bi bi-table"></i></span>
                            <h2 class="h5 mt-3">Matrizes</h2>
                            <p class="text-body-secondary mb-0">Consulte matrizes de classificacao e suas categorias relacionadas.</p>
                        </div>
                    </a>
                </div>
                <div class="col">
                    <a href="metodologia.php" class="card landing-card h-100 text-decoration-none">
                        <div class="card-body">
                            <span class="landing-card-icon"><i class="bi bi-journal-text"></i></span>
                            <h2 class="h5 mt-3">Metodologia</h2>
                            <p class="text-body-secondary mb-0">Veja criterios, conceitos e orientacoes de uso das matrizes.</p>
                        </div>
                    </a>
                </div>
                <div class="col">
                    <a href="administrativo.php" class="card landing-card h-100 text-decoration-none">
                        <div class="card-body">
                            <span class="landing-card-icon"><i class="bi bi-shield-lock"></i></span>
                            <h2 class="h5 mt-3">Administrativo</h2>
                            <p class="text-body-secondary mb-0">Acesse rotinas restritas de gestao do modulo quando autorizado.</p>
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
