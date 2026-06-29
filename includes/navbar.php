<?php
declare(strict_types=1);

if (!function_exists('get_platform_root_relative_path')) {
    function get_platform_root_relative_path(): string
    {
        $projectRootPath = realpath(dirname(__DIR__) . '/public_html');
        if ($projectRootPath === false) {
            return './';
        }
        $projectRoot = str_replace('\\', '/', $projectRootPath);
        
        $currentScript = $_SERVER['SCRIPT_FILENAME'] ?? '';
        if ($currentScript !== '') {
            $realScript = realpath($currentScript);
            if ($realScript === false) {
                return './';
            }
            $currentDir = str_replace('\\', '/', dirname($realScript));
            $projectRoot = rtrim($projectRoot, '/');
            $currentDir = rtrim($currentDir, '/');
            
            if ($projectRoot === $currentDir) {
                return './';
            }
            
            if (str_starts_with($currentDir, $projectRoot)) {
                $subPath = substr($currentDir, strlen($projectRoot));
                $subPath = trim($subPath, '/');
                if ($subPath === '') {
                    return './';
                }
                $count = substr_count($subPath, '/');
                return str_repeat('../', $count + 1);
            }
        }
        return './';
    }
}

if (!function_exists('render_platform_navbar')) {
    function render_platform_navbar(string $module, string $activePage = '', array $extraConfig = []): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $relPath = get_platform_root_relative_path();
        $user = null;

        // Try getting user info from Acesso / Fichário
        if (isset($_SESSION['_acesso_user_id'])) {
            $user = [
                'name' => $_SESSION['_acesso_user_name'] ?? 'Usuário',
                'email' => $_SESSION['_acesso_user_email'] ?? '',
                'role' => '',
                'logout_url' => $relPath . 'acesso/logout.php',
                'profile_url' => $relPath . 'acesso/index.php'
            ];
            
            // Check if current_user() function is defined to retrieve full profile details
            if (function_exists('current_user')) {
                try {
                    $profile = current_user();
                    if ($profile) {
                        $user['name'] = $profile['name'] ?? $user['name'];
                        $user['email'] = $profile['email'] ?? $user['email'];
                        $user['role'] = $profile['role'] ?? '';
                    }
                } catch (Throwable $e) {
                    // Ignore DB failures on external modules
                }
            }
        } 
        // Try getting user info from Carex-BR
        elseif (isset($_SESSION['_carex_auth_user'])) {
            $carexUser = $_SESSION['_carex_auth_user'];
            $user = [
                'name' => $carexUser['name'] ?? 'Usuário',
                'email' => $carexUser['email'] ?? '',
                'role' => $carexUser['role'] ?? '',
                'logout_url' => $relPath . 'carex/logout.php',
                'profile_url' => $relPath . 'carex/perfil.php'
            ];
        }

        // Define menus for each module
        $menus = [
            'acesso' => [
                'brand_label' => 'acesso',
                'brand_url' => $relPath . 'acesso/index.php',
                'items' => [
                    'index' => ['url' => $relPath . 'acesso/index.php', 'label' => 'Painel', 'icon' => 'bi bi-grid-fill'],
                    'usuarios' => ['url' => $relPath . 'acesso/usuarios.php', 'label' => 'Usuários', 'icon' => 'bi bi-people-fill'],
                    'permissoes' => ['url' => $relPath . 'acesso/permissoes.php', 'label' => 'Papéis', 'icon' => 'bi bi-shield-lock-fill'],
                ]
            ],
            'carex' => [
                'brand_label' => 'carex-br',
                'brand_url' => $relPath . 'carex/index.php',
                'items' => [
                    'matrizes' => ['url' => $relPath . 'carex/matrizes.php', 'label' => 'Matrizes', 'icon' => 'bi bi-table'],
                    'metodologia' => ['url' => $relPath . 'carex/metodologia.php', 'label' => 'Metodologia', 'icon' => 'bi bi-journal-text'],
                    'administrativo' => ['url' => $relPath . 'carex/administrativo.php', 'label' => 'Administrativo', 'icon' => 'bi bi-shield-lock', 'role' => 'admin'],
                    'desenvolvimento' => ['url' => $relPath . 'carex/desenvolvimento.php', 'label' => 'Desenvolvimento', 'icon' => 'bi bi-code-slash', 'role' => 'admin'],
                ]
            ],
            'fichario' => [
                'brand_label' => 'fichário',
                'brand_url' => $relPath . 'fichario/index.php',
                'items' => [
                    'articles' => ['url' => $relPath . 'fichario/articles.php', 'label' => 'Artigos', 'icon' => 'bi bi-file-earmark-text'],
                    'projects' => ['url' => $relPath . 'fichario/projects.php', 'label' => 'Projetos', 'icon' => 'bi bi-folder2'],
                    'tags' => ['url' => $relPath . 'fichario/tags.php', 'label' => 'Tags', 'icon' => 'bi bi-tags'],
                    'timeline' => ['url' => $relPath . 'fichario/timeline.php', 'label' => 'Timeline', 'icon' => 'bi bi-clock-history'],
                ]
            ],
            'ldrt' => [
                'brand_label' => 'ldrt',
                'brand_url' => $relPath . 'ldrt/index.php',
                'items' => [
                    'inicio' => ['url' => $relPath . 'ldrt/index.php', 'label' => 'Início', 'icon' => 'bi bi-house'],
                    'consulta' => ['url' => $relPath . 'ldrt/consulta.php', 'label' => 'Consulta Cruzada', 'icon' => 'bi bi-search'],
                    'explorar_cid' => ['url' => $relPath . 'ldrt/explorar_cid.php', 'label' => 'CID', 'icon' => 'bi bi-folder-tree'],
                    'explorar_cnae_cbo' => ['url' => $relPath . 'ldrt/explorar_cnae_cbo.php', 'label' => 'CNAE/CBO', 'icon' => 'bi bi-sitemap'],
                    'tabelas' => ['url' => $relPath . 'ldrt/lista_a.php', 'label' => 'LDRT', 'icon' => 'bi bi-table'],
                    'rag' => ['url' => $relPath . 'ldrt/rag.php', 'label' => 'RAG', 'icon' => 'bi bi-robot'],
                ]
            ],
            'cat' => [
                'brand_label' => 'cat',
                'brand_url' => $relPath . 'cat/index.php',
                'items' => [
                    'inicio' => ['url' => $relPath . 'cat/index.php', 'label' => 'Painel', 'icon' => 'bi bi-chart-line'],
                    'inspecao' => ['url' => $relPath . 'cat/inspecao.php', 'label' => 'CAT', 'icon' => 'bi bi-address-card'],
                    'fluxos' => [
                        'label' => 'Fluxos',
                        'icon' => 'bi bi-diagram-project',
                        'dropdown' => [
                            'territorios' => ['url' => $relPath . 'cat/territorios.php', 'label' => 'Territórios', 'icon' => 'bi bi-map'],
                            'cnaes' => ['url' => $relPath . 'cat/cnaes.php', 'label' => 'CNAE', 'icon' => 'bi bi-building'],
                            'cbos' => ['url' => $relPath . 'cat/cbos.php', 'label' => 'CBO', 'icon' => 'bi bi-person-gear'],
                            'cnpjs' => ['url' => $relPath . 'cat/cnpjs.php', 'label' => 'CNPJ', 'icon' => 'bi bi-buildings'],
                        ]
                    ],
                    'etl' => [
                        'label' => 'ETL',
                        'icon' => 'bi bi-database-gear',
                        'dropdown' => [
                            'processos' => ['url' => $relPath . 'cat/etl.php', 'label' => 'Processos', 'icon' => 'bi bi-cogs'],
                            'campos' => ['url' => $relPath . 'cat/campos.php', 'label' => 'Campos', 'icon' => 'bi bi-table-list'],
                        ]
                    ]
                ]
            ]
        ];

        $currentMenu = $menus[$module] ?? null;
        $isLanding = ($module === 'portal' || $module === 'landing');

        echo "\n<!-- Unified Platform Navbar -->\n";
        echo '<nav class="navbar navbar-expand-lg app-navbar sticky-top">';
        echo '  <div class="container-fluid">';
        
        // Brand / Left Side (If not simplified portal landing)
        if (!$isLanding && $currentMenu) {
            echo '    <div class="d-flex align-items-center me-3">';
            echo '      <a class="navbar-brand d-flex align-items-center me-0" href="' . $relPath . 'index.php">';
            echo '        <img src="' . $relPath . 'assets/img/logo-fundo-escuro-horizontal.png" alt="RENAST" class="platform-logo-img navbar-logo-img">';
            echo '      </a>';
            echo '      <span class="navbar-brand-divider">|</span>';
            echo '      <a class="navbar-brand d-flex align-items-center me-0" href="' . $currentMenu['brand_url'] . '">';
            echo '        <span class="module-brand-text">' . htmlspecialchars($currentMenu['brand_label'], ENT_QUOTES, 'UTF-8') . '</span>';
            echo '      </a>';
            echo '    </div>';
        } else {
            // Simplified portal brand or minimal brand
            echo '    <a class="navbar-brand d-flex align-items-center me-0" href="' . $relPath . 'index.php">';
            echo '      <img src="' . $relPath . 'assets/img/logo-fundo-escuro-horizontal.png" alt="RENAST" class="platform-logo-img navbar-logo-img">';
            echo '    </a>';
        }

        // Toggle button for mobile
        if (!$isLanding && $currentMenu) {
            echo '    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Alternar navegação">';
            echo '      <span class="navbar-toggler-icon"></span>';
            echo '    </button>';
        }

        // Navbar Links and Controls
        $collapseClass = $isLanding ? '' : 'collapse navbar-collapse align-items-center';
        echo '    <div class="' . $collapseClass . '" id="navbarContent">';
        
        if (!$isLanding && $currentMenu) {
            echo '      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 gap-2 align-items-center">';
            foreach ($currentMenu['items'] as $key => $item) {
                // Check role restrictions
                if (isset($item['role']) && $user && ($user['role'] ?? '') !== $item['role']) {
                    continue;
                }

                // Check dropdowns
                if (isset($item['dropdown'])) {
                    $isDropdownActive = false;
                    $dropdownHtml = '';
                    foreach ($item['dropdown'] as $subKey => $subItem) {
                        $isSubActive = ($activePage === $subKey);
                        if ($isSubActive) {
                            $isDropdownActive = true;
                        }
                        $subActiveAttr = $isSubActive ? 'active' : '';
                        $dropdownHtml .= '<li><a class="dropdown-item ' . $subActiveAttr . '" href="' . $subItem['url'] . '">' . htmlspecialchars($subItem['label'], ENT_QUOTES, 'UTF-8') . '</a></li>';
                    }
                    $activeClass = $isDropdownActive ? 'active fw-bold' : '';
                    echo '      <li class="nav-item dropdown">';
                    echo '        <a class="nav-link dropdown-toggle ' . $activeClass . '" href="#" id="navbarDrop_' . $key . '" role="button" data-bs-toggle="dropdown" aria-expanded="false">';
                    echo '          ' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
                    echo '        </a>';
                    echo '        <ul class="dropdown-menu shadow-sm" aria-labelledby="navbarDrop_' . $key . '">';
                    echo $dropdownHtml;
                    echo '        </ul>';
                    echo '      </li>';
                } else {
                    $isActive = ($activePage === $key);
                    $activeAttr = $isActive ? 'active' : '';
                    echo '      <li class="nav-item">';
                    echo '        <a class="nav-link ' . $activeAttr . '" href="' . $item['url'] . '">' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</a>';
                    echo '      </li>';
                }
            }
            echo '      </ul>';
        }

        // Right side (Theme + Sessions)
        $rightSideMargin = (!$isLanding && $currentMenu) ? 'ms-3' : 'ms-auto';
        echo '      <div class="d-flex align-items-center gap-3 ' . $rightSideMargin . '">';
        
        // Module Specific Badges
        if (isset($extraConfig['badge_text'])) {
            $badgeClass = $extraConfig['badge_class'] ?? 'bg-secondary';
            echo '      <span class="badge ' . $badgeClass . ' d-none d-md-inline-block">' . htmlspecialchars($extraConfig['badge_text'], ENT_QUOTES, 'UTF-8') . '</span>';
        }

        // User Dropdown / Login Button
        if ($user) {
            $userRoleBadge = '';
            if (!empty($user['role'])) {
                $userRoleBadge = ' <span class="badge bg-secondary-subtle text-secondary-emphasis small ms-1" style="font-size:0.6rem;">' . htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') . '</span>';
            }
            echo '      <div class="dropdown">';
            echo '        <button class="btn btn-link nav-link dropdown-toggle d-flex align-items-center gap-1 text-decoration-none" id="userMenuDropdown" type="button" data-bs-toggle="dropdown" aria-expanded="false">';
            echo '          <i class="bi bi-person-circle fs-5"></i> <span class="small d-none d-sm-inline-block">Olá, ' . htmlspecialchars(explode(' ', trim($user['name']))[0], ENT_QUOTES, 'UTF-8') . '</span>';
            echo '        </button>';
            echo '        <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="userMenuDropdown">';
            echo '          <li class="dropdown-header">';
            echo '            <strong class="text-dark d-block text-truncate" style="max-width:180px;">' . htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') . '</strong>';
            if ($user['email']) {
                echo '            <span class="small text-muted d-block text-truncate" style="max-width:180px;">' . htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') . '</span>';
            }
            if ($userRoleBadge) {
                echo '            <div class="mt-1">' . $userRoleBadge . '</div>';
            }
            echo '          </li>';
            echo '          <li><hr class="dropdown-divider"></li>';
            echo '          <li><a class="dropdown-item" href="' . $user['profile_url'] . '"><i class="bi bi-person-gear me-2"></i>Minha Conta</a></li>';
            echo '          <li><hr class="dropdown-divider"></li>';
            echo '          <li><a class="dropdown-item text-danger" href="' . $user['logout_url'] . '"><i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>';
            echo '        </ul>';
            echo '      </div>';
        } else {
            // "Entrar" link
            $loginNext = $_SERVER['REQUEST_URI'] ?? $relPath . 'index.php';
            echo '      <a class="btn btn-outline-primary btn-sm px-3 rounded-pill text-decoration-none" href="' . $relPath . 'acesso/login.php?next=' . rawurlencode($loginNext) . '">Entrar</a>';
        }

        // Theme Switcher Dropdown
        echo '      <div class="dropdown">';
        echo '        <button class="btn btn-link nav-link dropdown-toggle d-flex align-items-center" id="bd-theme-' . $module . '" type="button" aria-expanded="false" data-bs-toggle="dropdown" aria-label="Alternar tema (auto)">';
        echo '          <i class="theme-icon-active bi bi-circle-half"></i>';
        echo '        </button>';
        echo '        <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="bd-theme-' . $module . '">';
        echo '          <li><button type="button" class="dropdown-item d-flex align-items-center gap-2" data-bs-theme-value="light" aria-pressed="false"><i class="bi bi-sun-fill opacity-50"></i> Claro <i class="bi bi-check2 ms-auto d-none"></i></button></li>';
        echo '          <li><button type="button" class="dropdown-item d-flex align-items-center gap-2" data-bs-theme-value="dark" aria-pressed="false"><i class="bi bi-moon-stars-fill opacity-50"></i> Escuro <i class="bi bi-check2 ms-auto d-none"></i></button></li>';
        echo '          <li><button type="button" class="dropdown-item d-flex align-items-center gap-2" data-bs-theme-value="auto" aria-pressed="true"><i class="bi bi-circle-half opacity-50"></i> Auto <i class="bi bi-check2 ms-auto d-none"></i></button></li>';
        echo '        </ul>';
        echo '      </div>';

        echo '      </div>'; // End right side
        echo '    </div>'; // End collapse navbar
        echo '  </div>'; // End container
        echo '</nav>';
        echo "\n<!-- End Unified Platform Navbar -->\n";
    }
}
