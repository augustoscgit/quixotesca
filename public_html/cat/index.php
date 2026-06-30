<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="pt-BR" data-module="cat">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CAT - RENAST</title>
    <link rel="icon" type="image/png" href="../assets/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="../assets/js/theme-switcher.js?v=20260629-vanilla"></script>
    <link href="../assets/css/style.css?v=20260629-vanilla" rel="stylesheet">
</head>
<body class="landing-page d-flex flex-column min-vh-100">
    <?php
    require_once __DIR__ . '/../../includes/navbar.php';
    render_platform_navbar('cat', 'landing');
    ?>

    <main class="flex-grow-1">
        <section class="container landing-hero py-5 my-4">
            <div class="row justify-content-center text-center">
                <div class="col-lg-9 col-xl-8">
                    <img src="../assets/img/logo-fundo-escuro-horizontal.png" alt="RENAST" class="platform-logo-img landing-hero-logo mb-4">
                    <div class="landing-eyebrow mb-3">
                        <i class="bi bi-file-earmark-medical me-1"></i> Comunicacoes de Acidente de Trabalho
                    </div>
                    <h1 class="display-5 fw-semibold mb-3">Modulo CAT</h1>
                    <p class="lead text-body-secondary mb-4">
                        Consulte registros, acompanhe processamento de dados publicos e acesse ferramentas de inspecao da base de Comunicacoes de Acidente de Trabalho.
                    </p>
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <a href="inspecao.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-search me-2"></i> Inspecionar CAT
                        </a>
                        <a href="painel.php" class="btn btn-outline-primary btn-lg">
                            <i class="bi bi-chart-line me-2"></i> Ver painel
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="container pb-5">
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-4">
                <div class="col">
                    <a href="inspecao.php" class="card landing-card h-100 text-decoration-none">
                        <div class="card-body">
                            <span class="landing-card-icon"><i class="bi bi-address-card"></i></span>
                            <h2 class="h5 mt-3">Inspecao</h2>
                            <p class="text-body-secondary mb-0">Localize registros e revise detalhes normalizados da base CAT.</p>
                        </div>
                    </a>
                </div>
                <div class="col">
                    <a href="painel.php" class="card landing-card h-100 text-decoration-none">
                        <div class="card-body">
                            <span class="landing-card-icon"><i class="bi bi-bar-chart"></i></span>
                            <h2 class="h5 mt-3">Painel</h2>
                            <p class="text-body-secondary mb-0">Acompanhe indicadores, filtros e distribuicoes em uma tela interna.</p>
                        </div>
                    </a>
                </div>
                <div class="col">
                    <a href="cnpjs.php" class="card landing-card h-100 text-decoration-none">
                        <div class="card-body">
                            <span class="landing-card-icon"><i class="bi bi-buildings"></i></span>
                            <h2 class="h5 mt-3">CNPJ</h2>
                            <p class="text-body-secondary mb-0">Explore empregadores e relacoes de fluxo por empresas.</p>
                        </div>
                    </a>
                </div>
                <div class="col">
                    <a href="etl.php" class="card landing-card h-100 text-decoration-none">
                        <div class="card-body">
                            <span class="landing-card-icon"><i class="bi bi-database-gear"></i></span>
                            <h2 class="h5 mt-3">ETL</h2>
                            <p class="text-body-secondary mb-0">Controle extracao, conversao, carga e diagnostico dos arquivos.</p>
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
