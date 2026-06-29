<?php

declare(strict_types=1);

$currentYear = date('Y');
?>
<!DOCTYPE html>
<html lang="pt-br" data-module="portal">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Portal de Sistemas - RENAST</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
    <link rel="icon" type="image/png" href="assets/favicon.png" />
    <link rel="stylesheet" href="assets/css/style.css" />
    <script src="assets/js/theme-switcher.js"></script>
</head>
<body class="landing-page d-flex flex-column justify-content-between min-vh-100">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg app-navbar py-3">
        <div class="container">
            <div class="d-flex align-items-center gap-3 ms-auto">
                <!-- Login Button -->
                <a id="login-button" href="acesso/login.php" class="btn btn-outline-primary btn-sm px-3 rounded-pill text-decoration-none">Entrar</a>

                <!-- User Menu -->
                <div id="user-menu" class="user-menu-navbar dropdown" hidden>
                    <button id="user-menu-button" class="btn btn-link nav-link dropdown-toggle d-flex align-items-center gap-2 text-decoration-none" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle fs-5"></i>
                        <span id="user-menu-name">Usuário</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="user-menu-button">
                        <li class="dropdown-header">
                            <strong id="user-menu-fullname">Usuário</strong><br>
                            <span id="user-menu-email" class="text-muted small"></span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a href="acesso/" class="dropdown-item">Painel de acesso</a></li>
                        <li id="menu-admin-item" hidden><a href="admin/index.php" class="dropdown-item">Painel Admin</a></li>
                        <li id="menu-users-item" hidden><a href="admin/usuarios.php" class="dropdown-item">Usuários</a></li>
                        <li id="menu-permissions-item" hidden><a href="admin/permissoes.php" class="dropdown-item">Permissões</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a href="acesso/logout.php" class="dropdown-item text-danger">Sair</a></li>
                    </ul>
                </div>
                
                <!-- Theme Toggler -->
                <div class="dropdown">
                    <button class="btn btn-link nav-link dropdown-toggle d-flex align-items-center" id="bd-theme" type="button" aria-expanded="false" data-bs-toggle="dropdown" aria-label="Alternar tema (auto)">
                        <i class="theme-icon-active bi bi-circle-half"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="bd-theme">
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center gap-2" data-bs-theme-value="light" aria-pressed="false">
                                <i class="bi bi-sun-fill opacity-50"></i> Claro <i class="bi bi-check2 ms-auto d-none"></i>
                            </button>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center gap-2" data-bs-theme-value="dark" aria-pressed="false">
                                <i class="bi bi-moon-stars-fill opacity-50"></i> Escuro <i class="bi bi-check2 ms-auto d-none"></i>
                            </button>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center gap-2" data-bs-theme-value="auto" aria-pressed="true">
                                <i class="bi bi-circle-half opacity-50"></i> Auto <i class="bi bi-check2 ms-auto d-none"></i>
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <header class="container text-center landing-header landing-hero my-5">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <img src="assets/img/logo-fundo-escuro-horizontal.png" alt="RENAST" class="platform-logo-img landing-hero-logo mb-4">
                <h1 class="display-5">Portal de Sistemas</h1>
                <p class="lead text-muted mx-auto" style="max-width: 760px;">
                    Acesse as ferramentas e bases de dados voltadas para a promoção, prevenção e vigilância em Saúde do Trabalhador.
                </p>
            </div>
        </div>
    </header>

    <main class="container landing-container">
        <div class="row g-4 justify-content-center">
            <!-- 1. Acesso (First Card) -->
            <div class="col-12 col-md-6 col-lg-4">
                <a id="access-card" href="acesso/login.php" class="system-card access-theme text-decoration-none">
                    <div>
                        <div class="icon-wrapper">
                            <i class="bi bi-person-check-fill"></i>
                        </div>
                        <h3 id="access-title">Acesso</h3>
                        <p id="access-description">Entrada central da plataforma para autenticação, perfis de usuário e acesso aos recursos internos da Rede RENAST.</p>
                    </div>
                    <div class="text-end mt-3">
                        <span class="btn-system-icon"><i class="bi bi-arrow-right fs-4"></i></span>
                    </div>
                </a>
            </div>

            <!-- 2. RENAST / Fiocruz (Second Card) -->
            <div class="col-12 col-md-6 col-lg-4">
                <a href="https://renastonline.ensp.fiocruz.br/" target="_blank" rel="noopener" class="system-card fiocruz-theme text-decoration-none">
                    <div>
                        <div class="icon-wrapper">
                            <i class="bi bi-globe"></i>
                        </div>
                        <h3>Renast Online</h3>
                        <p>Enlace da Rede Nacional de Atenção Integral à Saúde do Trabalhador. Biblioteca, notícias e publicações oficiais.</p>
                    </div>
                    <div class="text-end mt-3">
                        <span class="btn-system-icon"><i class="bi bi-arrow-right fs-4"></i></span>
                    </div>
                </a>
            </div>

            <!-- 3. Carex-BR (Third Card) -->
            <div class="col-12 col-md-6 col-lg-4">
                <a href="carex/index.php" class="system-card carex-theme text-decoration-none">
                    <div>
                        <div class="icon-wrapper">
                            <i class="bi bi-activity"></i>
                        </div>
                        <h3>Carex-BR</h3>
                        <p>Sistema de estimativa e monitoramento da exposição de trabalhadores brasileiros a agentes carcinogênicos nos ambientes de trabalho.</p>
                    </div>
                    <div class="text-end mt-3">
                        <span class="btn-system-icon"><i class="bi bi-arrow-right fs-4"></i></span>
                    </div>
                </a>
            </div>

            <!-- 4. Fichário Acadêmico (Fourth Card) -->
            <div class="col-12 col-md-6 col-lg-4">
                <a href="fichario/index.php" class="system-card fichario-theme text-decoration-none">
                    <div>
                        <div class="icon-wrapper">
                            <i class="bi bi-journal-text"></i>
                        </div>
                        <h3>Fichário Acadêmico</h3>
                        <p>Sistema de fichamento de artigos acadêmicos, com cadastro bibliográfico, referências, tags temáticas, comentários e base de dados local.</p>
                    </div>
                    <div class="text-end mt-3">
                        <span class="btn-system-icon"><i class="bi bi-arrow-right fs-4"></i></span>
                    </div>
                </a>
            </div>

            <!-- 5. LDRT (Fifth Card) -->
            <div class="col-12 col-md-6 col-lg-4">
                <a href="ldrt/index.php" class="system-card ldrt-theme text-decoration-none">
                    <div>
                        <div class="icon-wrapper">
                            <i class="bi bi-virus"></i>
                        </div>
                        <h3>LDRT</h3>
                        <p>Consulta e exploração da Lista de Doenças Relacionadas ao Trabalho (LDRT). Permite buscas por CID, CNAE, CBO, além de suporte para IA Generativa (RAG).</p>
                    </div>
                    <div class="text-end mt-3">
                        <span class="btn-system-icon"><i class="bi bi-arrow-right fs-4"></i></span>
                    </div>
                </a>
            </div>

            <!-- 6. CAT (Sixth Card) -->
            <div class="col-12 col-md-6 col-lg-4">
                <a href="cat/index.php" class="system-card cat-theme text-decoration-none">
                    <div>
                        <div class="icon-wrapper">
                            <i class="bi bi-file-earmark-medical"></i>
                        </div>
                        <h3>CAT</h3>
                        <p>Processamento e ETL de dados públicos de Comunicações de Acidente de Trabalho. Controle a extração, conversão e carga incremental no banco de dados.</p>
                    </div>
                    <div class="text-end mt-3">
                        <span class="btn-system-icon"><i class="bi bi-arrow-right fs-4"></i></span>
                    </div>
                </a>
            </div>
        </div>
    </main>

    <footer class="text-center py-4 mt-auto">
        <div class="container">
            <p class="mb-0 text-muted">&copy; <?php echo $currentYear; ?> Plataforma RENAST Online. Todos os direitos reservados.</p>
        </div>
    </footer>

    <div id="cookie-banner" class="cookie-banner">
        <p class="cookie-text">
            Nós utilizamos cookies e tecnologias semelhantes para melhorar a sua experiência e segurança em nosso portal. Ao continuar navegando, você concorda com esta utilização.
        </p>
        <button id="accept-cookies" class="cookie-btn">Aceitar e Continuar</button>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const banner = document.getElementById('cookie-banner');
        const acceptBtn = document.getElementById('accept-cookies');

        if (!localStorage.getItem('cookies-accepted')) {
            setTimeout(() => {
                banner.classList.add('show');
            }, 1000);
        }

        acceptBtn.addEventListener('click', () => {
            localStorage.setItem('cookies-accepted', 'true');
            banner.classList.remove('show');
        });

        const userMenu = document.getElementById('user-menu');
        const userMenuButton = document.getElementById('user-menu-button');
        
        const userMenuName = document.getElementById('user-menu-name');
        const userMenuFullname = document.getElementById('user-menu-fullname');
        const userMenuEmail = document.getElementById('user-menu-email');
        const accessCard = document.getElementById('access-card');
        const accessTitle = document.getElementById('access-title');
        const accessDescription = document.getElementById('access-description');

        function firstName(name) {
            return String(name || 'usuário').trim().split(/\s+/)[0] || 'usuário';
        }

        function setLoggedInState(payload) {
            const user = payload.user || {};
            const displayName = firstName(user.name);

            accessCard.classList.add('is-logged');
            accessCard.href = 'acesso/';
            accessTitle.textContent = `Olá, ${displayName}!`;
            accessDescription.textContent = 'Seja bem vindo à Plataforma Renast.';

            userMenuName.textContent = displayName;
            userMenuFullname.textContent = user.name || displayName;
            userMenuEmail.textContent = user.email || '';
            userMenu.hidden = false;

            if (user.is_admin) {
                const adminItem = document.getElementById('menu-admin-item');
                const usersItem = document.getElementById('menu-users-item');
                const permsItem = document.getElementById('menu-permissions-item');
                if (adminItem) adminItem.hidden = false;
                if (usersItem) usersItem.hidden = false;
                if (permsItem) permsItem.hidden = false;
            }
            
            const loginBtn = document.getElementById('login-button');
            if (loginBtn) loginBtn.hidden = true;
        }

        fetch('acesso/session.php', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
        })
            .then((response) => response.ok ? response.json() : null)
            .then((payload) => {
                if (payload && payload.logged_in) {
                    setLoggedInState(payload);
                }
            })
            .catch(() => {
                // A landing continua funcional mesmo sem estado de sessão disponível.
            });
    </script>
</body>
</html>
