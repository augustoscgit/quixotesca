<?php
declare(strict_types=1);

$currentYear = date('Y');
?>
<!doctype html>
<html lang="pt-br" data-module="portal">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portal de Sistemas - RENAST</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <script src="assets/js/theme-switcher.js?v=20260629-vanilla"></script>
    <link href="assets/css/style.css?v=20260629-vanilla" rel="stylesheet">
</head>
<body class="landing-page d-flex flex-column min-vh-100">
    <?php
    require_once __DIR__ . '/../includes/navbar.php';
    render_platform_navbar('portal', 'landing');
    ?>

    <main class="flex-grow-1">
        <section class="container landing-hero py-5 my-4">
            <div class="row justify-content-center text-center">
                <div class="col-lg-9 col-xl-8">
                    <img src="assets/img/logo-fundo-escuro-horizontal.png" alt="RENAST" class="platform-logo-img landing-hero-logo mb-4">
                    <div class="landing-eyebrow mb-3">
                        <i class="bi bi-grid-3x3-gap me-1"></i> Plataforma integrada
                    </div>
                    <h1 class="display-5 fw-semibold mb-3">Portal de Sistemas</h1>
                    <p class="lead text-body-secondary mb-4">
                        Acesse ferramentas, bases de dados e ambientes de trabalho voltados a pesquisa, vigilancia e cuidado em Saude do Trabalhador.
                    </p>
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <a id="access-card" href="acesso/login.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-person-check me-2"></i> <span id="access-title">Entrar na plataforma</span>
                        </a>
                        <a href="#sistemas" class="btn btn-outline-primary btn-lg">
                            <i class="bi bi-compass me-2"></i> Ver sistemas
                        </a>
                    </div>
                    <p id="access-description" class="small text-body-secondary mt-3 mb-0">
                        Acesso central para autenticacao, perfis de usuario e recursos internos.
                    </p>
                </div>
            </div>
        </section>

        <section class="container pb-5" id="sistemas">
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                <div class="col">
                    <a href="carex/index.php" class="card landing-card h-100 text-decoration-none">
                        <div class="card-body">
                            <span class="landing-card-icon"><i class="bi bi-activity"></i></span>
                            <h2 class="h5 mt-3">Carex-BR</h2>
                            <p class="text-body-secondary mb-0">Matrizes e classificacoes para exposicao ocupacional a agentes carcinogenicos.</p>
                        </div>
                    </a>
                </div>
                <div class="col">
                    <a href="fichario/index.php" class="card landing-card h-100 text-decoration-none">
                        <div class="card-body">
                            <span class="landing-card-icon"><i class="bi bi-journal-text"></i></span>
                            <h2 class="h5 mt-3">Fichario Academico</h2>
                            <p class="text-body-secondary mb-0">Organizacao de artigos, documentos, citacoes, notas e tags de pesquisa.</p>
                        </div>
                    </a>
                </div>
                <div class="col">
                    <a href="ldrt/index.php" class="card landing-card h-100 text-decoration-none">
                        <div class="card-body">
                            <span class="landing-card-icon"><i class="bi bi-virus"></i></span>
                            <h2 class="h5 mt-3">LDRT</h2>
                            <p class="text-body-secondary mb-0">Consulta estruturada de doencas, agentes de risco, CNAE e CBO.</p>
                        </div>
                    </a>
                </div>
                <div class="col">
                    <a href="cat/index.php" class="card landing-card h-100 text-decoration-none">
                        <div class="card-body">
                            <span class="landing-card-icon"><i class="bi bi-file-earmark-medical"></i></span>
                            <h2 class="h5 mt-3">CAT</h2>
                            <p class="text-body-secondary mb-0">Inspecao, processamento e paineis de Comunicacoes de Acidente de Trabalho.</p>
                        </div>
                    </a>
                </div>
                <div class="col">
                    <a href="acesso/index.php" class="card landing-card h-100 text-decoration-none">
                        <div class="card-body">
                            <span class="landing-card-icon"><i class="bi bi-person-gear"></i></span>
                            <h2 class="h5 mt-3">Acesso</h2>
                            <p class="text-body-secondary mb-0">Conta, autenticacao, sessoes e perfis de uso da plataforma.</p>
                        </div>
                    </a>
                </div>
                <div class="col">
                    <a href="https://renastonline.ensp.fiocruz.br/" target="_blank" rel="noopener" class="card landing-card h-100 text-decoration-none">
                        <div class="card-body">
                            <span class="landing-card-icon"><i class="bi bi-box-arrow-up-right"></i></span>
                            <h2 class="h5 mt-3">RENAST Online</h2>
                            <p class="text-body-secondary mb-0">Portal externo com biblioteca, noticias e publicacoes da rede.</p>
                        </div>
                    </a>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-top py-4 mt-auto">
        <div class="container text-center small text-body-secondary">
            &copy; <?php echo $currentYear; ?> Plataforma RENAST Online.
        </div>
    </footer>

    <div id="cookie-banner" class="cookie-banner">
        <p class="cookie-text">
            Utilizamos cookies e tecnologias semelhantes para melhorar sua experiencia e seguranca. Ao continuar navegando, voce concorda com esta utilizacao.
        </p>
        <button id="accept-cookies" class="btn btn-primary btn-sm">Aceitar e continuar</button>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script>
        const banner = document.getElementById('cookie-banner');
        const acceptBtn = document.getElementById('accept-cookies');

        if (banner && acceptBtn && !localStorage.getItem('cookies-accepted')) {
            setTimeout(() => {
                banner.classList.add('show');
            }, 1000);

            acceptBtn.addEventListener('click', () => {
                localStorage.setItem('cookies-accepted', 'true');
                banner.classList.remove('show');
            });
        }

        const accessCard = document.getElementById('access-card');
        const accessTitle = document.getElementById('access-title');
        const accessDescription = document.getElementById('access-description');

        function firstName(name) {
            return String(name || 'usuario').trim().split(/\s+/)[0] || 'usuario';
        }

        function setLoggedInState(payload) {
            const user = payload.user || {};
            const displayName = firstName(user.name);

            accessCard.href = 'acesso/';
            accessTitle.textContent = `Ola, ${displayName}`;
            accessDescription.textContent = 'Sua sessao esta ativa. Acesse sua conta ou continue pelos sistemas abaixo.';
        }

        fetch('acesso/session.php', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
        })
            .then((response) => response.ok ? response.json() : null)
            .then((payload) => {
                if (payload && payload.logged_in && accessCard && accessTitle && accessDescription) {
                    setLoggedInState(payload);
                }
            })
            .catch(() => {});
    </script>
</body>
</html>
