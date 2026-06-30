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

if (!function_exists('platform_nav_canonical_role')) {
    function platform_nav_canonical_role(array $roles = [], array $permissions = [], string $fallback = 'public'): string
    {
        $adminPermissions = [
            'platform.admin',
            'acesso.admin',
            'fichario.admin',
            'ldrt.admin',
            'carex.admin',
            'cat.admin',
        ];

        if (in_array('admin', $roles, true) || array_intersect($adminPermissions, $permissions) !== []) {
            return 'admin';
        }

        if (in_array('user', $roles, true) || in_array('content.edit', $permissions, true)) {
            return 'user';
        }

        return in_array($fallback, ['admin', 'user', 'public'], true) ? $fallback : 'public';
    }
}

if (!function_exists('platform_nav_role_label')) {
    function platform_nav_role_label(string $role): string
    {
        return match ($role) {
            'admin' => 'Administrador',
            'user' => 'Editor',
            default => 'Usuário',
        };
    }
}

if (!function_exists('platform_nav_fetch_auth_profile')) {
    function platform_nav_fetch_auth_profile(PDO $pdo, int $userId): ?array
    {
        $stmt = $pdo->prepare('SELECT name, email FROM acesso.users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$dbUser) {
            return null;
        }

        $permStmt = $pdo->prepare("
            SELECT DISTINCT p.slug
              FROM acesso.permissions p
              JOIN acesso.role_permissions rp ON rp.permission_id = p.id
              JOIN acesso.user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = :user_id
        ");
        $permStmt->execute(['user_id' => $userId]);
        $permissions = array_map('strval', $permStmt->fetchAll(PDO::FETCH_COLUMN));

        $roleStmt = $pdo->prepare("
            SELECT DISTINCT r.slug
              FROM acesso.roles r
              JOIN acesso.user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = :user_id
        ");
        $roleStmt->execute(['user_id' => $userId]);
        $roles = array_map('strval', $roleStmt->fetchAll(PDO::FETCH_COLUMN));

        return [
            'name' => (string) ($dbUser['name'] ?? ''),
            'email' => (string) ($dbUser['email'] ?? ''),
            'roles' => $roles,
            'permissions' => $permissions,
            'role' => platform_nav_canonical_role($roles, $permissions),
        ];
    }
}

if (!function_exists('render_platform_navbar')) {
    function render_platform_navbar(string $module, string $activePage = '', array $extraConfig = []): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            if (!headers_sent()) {
                $sessionDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'acesso' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'sessions';
                if (!is_dir($sessionDir)) {
                    @mkdir($sessionDir, 0775, true);
                }
                if (is_dir($sessionDir) && is_writable($sessionDir)) {
                    session_save_path($sessionDir);
                }

                ini_set('session.use_strict_mode', '1');
                ini_set('session.gc_maxlifetime', '14400');

                $isHttps = (
                    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
                );
                session_set_cookie_params([
                    'lifetime' => 0,
                    'path' => '/',
                    'secure' => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
            @session_start();
        }

        $relPath = get_platform_root_relative_path();
        $user = null;

        // Try getting user info from Acesso / Fichário
        if (isset($_SESSION['_acesso_user_id'])) {
            // Lazy-load details into session if missing (e.g. active legacy session)
            if (!isset($_SESSION['_acesso_user_name']) || $_SESSION['_acesso_user_name'] === '') {
                try {
                    $pdo = null;
                    if (function_exists('db')) {
                        $pdo = db();
                    } elseif (function_exists('getDBConnection')) {
                        $pdo = getDBConnection();
                    }

                    if ($pdo) {
                        $stmt = $pdo->prepare('SELECT name, email FROM acesso.users WHERE id = :id LIMIT 1');
                        $stmt->execute(['id' => $_SESSION['_acesso_user_id']]);
                        $dbUser = $stmt->fetch();
                        if ($dbUser) {
                            $_SESSION['_acesso_user_name'] = (string) $dbUser['name'];
                            $_SESSION['_acesso_user_email'] = (string) $dbUser['email'];

                            // Check canonical platform role
                            $permStmt = $pdo->prepare("
                                SELECT DISTINCT p.slug
                                  FROM acesso.permissions p
                                  JOIN acesso.role_permissions rp ON rp.permission_id = p.id
                                  JOIN acesso.user_roles ur ON ur.role_id = rp.role_id
                                 WHERE ur.user_id = :user_id
                            ");
                            $permStmt->execute(['user_id' => $_SESSION['_acesso_user_id']]);
                            $perms = $permStmt->fetchAll(PDO::FETCH_COLUMN);

                            $roleStmt = $pdo->prepare("
                                SELECT DISTINCT r.slug
                                  FROM acesso.roles r
                                  JOIN acesso.user_roles ur ON ur.role_id = r.id
                                 WHERE ur.user_id = :user_id
                            ");
                            $roleStmt->execute(['user_id' => $_SESSION['_acesso_user_id']]);
                            $roles = $roleStmt->fetchAll(PDO::FETCH_COLUMN);

                            $legacyAdmin = array_intersect(['platform.admin', 'acesso.admin', 'fichario.admin', 'ldrt.admin', 'carex.admin'], $perms) !== [];
                            $_SESSION['_acesso_user_role'] = (in_array('admin', $roles, true) || $legacyAdmin)
                                ? 'admin'
                                : (in_array('user', $roles, true) || in_array('content.edit', $perms, true) ? 'user' : 'public');
                        }
                    }
                } catch (Throwable $t) {
                    // Ignore database errors
                }
            }

            $user = [
                'name' => $_SESSION['_acesso_user_name'] ?? 'Usuário',
                'email' => $_SESSION['_acesso_user_email'] ?? '',
                'role' => $_SESSION['_acesso_user_role'] ?? '',
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
                        $user['role'] = $profile['role'] ?? $user['role'];
                    }
                } catch (Throwable $e) {
                    // Ignore DB failures on external modules
                }
            }
        }

        if ($user !== null && isset($_SESSION['_acesso_user_id'])) {
            $user['roles'] = $user['roles'] ?? [];
            $user['permissions'] = $user['permissions'] ?? [];
            $user['role'] = platform_nav_canonical_role((array) $user['roles'], (array) $user['permissions'], (string) ($user['role'] ?? 'public'));

            try {
                $pdo = null;
                if (function_exists('db')) {
                    $pdo = db();
                } elseif (function_exists('getDBConnection')) {
                    $pdo = getDBConnection();
                }

                if ($pdo instanceof PDO) {
                    $authProfile = platform_nav_fetch_auth_profile($pdo, (int) $_SESSION['_acesso_user_id']);
                    if ($authProfile) {
                        $user['name'] = $authProfile['name'] !== '' ? $authProfile['name'] : $user['name'];
                        $user['email'] = $authProfile['email'];
                        $user['roles'] = $authProfile['roles'];
                        $user['permissions'] = $authProfile['permissions'];
                        $user['role'] = $authProfile['role'];
                    }
                }
            } catch (Throwable $e) {
                // Keep the session/profile fallback when a module cannot reach the auth schema.
            }

            if (function_exists('current_user')) {
                try {
                    $profile = current_user();
                    if ($profile) {
                        $profilePermissions = array_map('strval', $profile['_permissions'] ?? []);
                        $profileRoles = array_map('strval', $profile['_roles'] ?? []);
                        $user['permissions'] = array_values(array_unique(array_merge((array) $user['permissions'], $profilePermissions)));
                        $user['roles'] = array_values(array_unique(array_merge((array) $user['roles'], $profileRoles)));
                        $user['role'] = platform_nav_canonical_role($user['roles'], $user['permissions'], (string) $user['role']);
                    }
                } catch (Throwable $e) {
                    // Ignore DB failures on external modules.
                }
            }

            $_SESSION['_acesso_user_name'] = (string) $user['name'];
            $_SESSION['_acesso_user_email'] = (string) $user['email'];
            $_SESSION['_acesso_user_role'] = (string) $user['role'];
        }

        // Define menus for each module
        $menus = [
            'acesso' => [
                'brand_label' => 'acesso',
                'brand_url' => $relPath . 'acesso/index.php',
                'items' => [
                    'index' => ['url' => $relPath . 'acesso/index.php', 'label' => 'Painel', 'icon' => 'bi bi-grid-fill'],
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
                    'painel' => ['url' => $relPath . 'fichario/painel.php', 'label' => 'Painel', 'icon' => 'bi bi-speedometer2'],
                    'articles' => ['url' => $relPath . 'fichario/articles.php', 'label' => 'Artigos', 'icon' => 'bi bi-file-earmark-text'],
                    'projects' => ['url' => $relPath . 'fichario/projects.php', 'label' => 'Projetos', 'icon' => 'bi bi-folder2'],
                    'tags' => ['url' => $relPath . 'fichario/tags.php', 'label' => 'Tags', 'icon' => 'bi bi-tags'],
                    'timeline' => ['url' => $relPath . 'fichario/timeline.php', 'label' => 'Timeline', 'icon' => 'bi bi-clock-history'],
                    'admin' => ['url' => $relPath . 'fichario/admin.php', 'label' => 'Admin', 'icon' => 'bi bi-shield-lock', 'role' => 'admin'],
                    'docs' => ['url' => $relPath . 'fichario/admin_docs.php', 'label' => 'Documentos', 'icon' => 'bi bi-file-earmark-text', 'role' => 'admin'],
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
                    'inicio' => ['url' => $relPath . 'cat/index.php', 'label' => 'Início', 'icon' => 'bi bi-house'],
                    'painel' => ['url' => $relPath . 'cat/painel.php', 'label' => 'Painel', 'icon' => 'bi bi-chart-line'],
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
            ],
            'admin' => [
                'brand_label' => 'administração',
                'brand_url' => $relPath . 'admin/index.php',
                'items' => [
                    'inicio' => ['url' => $relPath . 'admin/index.php', 'label' => 'Painel', 'icon' => 'bi bi-grid-fill'],
                    'usuarios' => ['url' => $relPath . 'admin/usuarios.php', 'label' => 'Usuários', 'icon' => 'bi bi-people-fill'],
                    'permissoes' => ['url' => $relPath . 'admin/permissoes.php', 'label' => 'Papéis', 'icon' => 'bi bi-shield-lock-fill'],
                ]
            ]
        ];

        $currentMenu = $menus[$module] ?? null;
        $isLanding = ($module === 'portal' || $module === 'landing' || $activePage === 'landing');

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
            echo '        <span class="module-brand-text text-uppercase">' . htmlspecialchars($currentMenu['brand_label'], ENT_QUOTES, 'UTF-8') . '</span>';
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
            echo '      <ul class="navbar-nav platform-module-nav mx-lg-auto mb-2 mb-lg-0 gap-2 align-items-center">';
            foreach ($currentMenu['items'] as $key => $item) {
                // Check role restrictions
                if (isset($item['role'])) {
                    $allowedRoles = (array) $item['role'];
                    if (!$user || !in_array((string) ($user['role'] ?? 'public'), $allowedRoles, true)) {
                        continue;
                    }
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
        $rightSideMargin = (!$isLanding && $currentMenu) ? 'ms-lg-3' : 'ms-auto';
        echo '      <div class="platform-navbar-actions d-flex align-items-center gap-3 ' . $rightSideMargin . '">';
        
        // Module Specific Badges
        if (isset($extraConfig['badge_text'])) {
            $badgeClass = $extraConfig['badge_class'] ?? 'bg-secondary';
            echo '      <span class="badge ' . $badgeClass . ' d-none d-md-inline-block">' . htmlspecialchars($extraConfig['badge_text'], ENT_QUOTES, 'UTF-8') . '</span>';
        }

        // User Dropdown / Login Button
        if ($user) {
            $userRoleBadge = '';
            if (!empty($user['role'])) {
                $userRoleBadge = ' <span class="badge text-bg-secondary ms-1">' . htmlspecialchars(platform_nav_role_label((string) $user['role']), ENT_QUOTES, 'UTF-8') . '</span>';
            }
            echo '      <div class="dropdown">';
            echo '        <button class="btn btn-link nav-link dropdown-toggle d-flex align-items-center gap-1 text-decoration-none" id="userMenuDropdown" type="button" data-bs-toggle="dropdown" aria-expanded="false">';
            echo '          <i class="bi bi-person-circle fs-5"></i> <span class="small d-none d-sm-inline-block">Olá, ' . htmlspecialchars(explode(' ', trim($user['name']))[0], ENT_QUOTES, 'UTF-8') . '</span>';
            echo '        </button>';
            echo '        <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="userMenuDropdown">';
            echo '          <li class="dropdown-header">';
            echo '            <strong class="d-block text-truncate">' . htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') . '</strong>';
            if ($user['email']) {
                echo '            <span class="small text-body-secondary d-block text-truncate">' . htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') . '</span>';
            }
            if ($userRoleBadge) {
                echo '            <div class="mt-1">' . $userRoleBadge . '</div>';
            }
            echo '          </li>';
            echo '          <li><hr class="dropdown-divider"></li>';
            echo '          <li><a class="dropdown-item" href="' . $user['profile_url'] . '"><i class="bi bi-person-gear me-2"></i>Minha Conta</a></li>';

            // Check if user has administrative rights
            $isAdmin = (string) ($user['role'] ?? 'public') === 'admin';
            if ($isAdmin) {
                echo '          <li><a class="dropdown-item" href="' . $relPath . 'admin/index.php"><i class="bi bi-shield-lock me-2"></i>Painel Admin</a></li>';
            }
            echo '          <li><hr class="dropdown-divider"></li>';
            echo '          <li><a class="dropdown-item text-danger" href="' . $user['logout_url'] . '"><i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>';
            echo '        </ul>';
            echo '      </div>';
        } else {
            // "Entrar" link
            $loginNext = $_SERVER['REQUEST_URI'] ?? $relPath . 'index.php';
            echo '      <a class="btn btn-outline-primary btn-sm px-3 rounded-pill text-decoration-none" href="' . $relPath . 'acesso/login.php?next=' . rawurlencode($loginNext) . '">Entrar</a>';
        }

        echo '      </div>'; // End right side
        echo '    </div>'; // End collapse navbar
        echo '  </div>'; // End container
        echo '</nav>';
        echo "\n<!-- End Unified Platform Navbar -->\n";
    }
}
