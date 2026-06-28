<?php

declare(strict_types=1);

use Carex\Http\Security;
use Carex\Support\GoogleClient;

$config = require dirname(__DIR__, 2) . '/carex' . '/src/bootstrap.php';

Security::applyHeaders();

// If already authenticated, redirect to matrizes.php
if (\Carex\Http\Auth::isAuthenticated()) {
    header('Location: matrizes.php');
    exit;
}

$errorMsg = '';
$errorType = $_GET['error'] ?? '';
$debugErrorMsg = '';

if ($errorType === 'desligado') {
    $errorMsg = 'Aviso de desligamento. Por favor, entre em contato com o administrador.';
} elseif ($errorType === 'oauth') {
    $errorMsg = 'Falha na autenticação com o Google. Tente novamente.';
    if (($config['app']['debug'] ?? false) || (($config['app']['env'] ?? '') === 'local')) {
        \Carex\Http\Auth::startSession();
        $debugErrorMsg = (string) ($_SESSION['_carex_oauth_error'] ?? '');
        unset($_SESSION['_carex_oauth_error']);
    }
} elseif ($errorType === 'config') {
    $errorMsg = 'Erro de configuração da autenticação Google no servidor.';
}

// Load settings for dev mode
$settingsFile  = dirname(__DIR__, 2) . '/carex' . '/config/settings.json';
$appSettings   = json_decode(file_exists($settingsFile) ? file_get_contents($settingsFile) : '{}', true);
$devLoginVisible = $appSettings['dev_login_visible'] ?? true;

$googleClient = null;
$googleAuthUrl = '#';

try {
    $gConfig = $config['google'] ?? [];
    if (!empty($gConfig['client_id']) && !empty($gConfig['client_secret']) && !empty($gConfig['redirect_uri'])) {
        $googleClient = new GoogleClient(
            $gConfig['client_id'],
            $gConfig['client_secret'],
            $gConfig['redirect_uri']
        );

        \Carex\Http\Auth::startSession();
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;

        // If local env AND credentials are dummy, route the main Google login button to mock login
        // If real credentials are set, always use real OAuth even on local
        $isDummyCreds = str_starts_with($gConfig['client_id'], 'dummy');
        if (($config['app']['env'] ?? '') === 'local' && $isDummyCreds) {
            $googleAuthUrl = 'auth-callback.php?mock_login=admin';
        } else {
            $googleAuthUrl = $googleClient->getAuthUrl($state);
        }
    } else {
        if ($errorType === '') {
            $errorMsg = 'A autenticação via Google OAuth não está configurada no servidor (.env).';
        }
    }
} catch (Throwable $e) {
    $errorMsg = 'Erro na inicialização da autenticação: ' . $e->getMessage();
}

// Check if remember_me parameter needs to be carried to authorization state
if (isset($_GET['remember'])) {
    $_SESSION['remember_me'] = true;
} else {
    unset($_SESSION['remember_me']);
}

$bootstrapCss = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
?>
<!doctype html>
<html lang="pt-BR" data-module="carex">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Carex-BR | Login</title>
    <link href="assets/favicon.png" rel="icon" type="image/png">
    <link href="<?= Security::e($bootstrapCss) ?>" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Google Fonts Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.05);
            max-width: 440px;
            width: 100%;
            padding: 40px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
        }

        .login-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .login-logo {
            max-height: 48px;
            margin-bottom: 15px;
        }

        .login-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            color: #0b2545;
            font-size: 2rem;
            margin-bottom: 5px;
            letter-spacing: -0.5px;
        }

        .login-subtitle {
            font-size: 0.9rem;
            color: #5c677d;
            font-weight: 500;
        }

        .google-btn {
            background-color: #ffffff;
            color: #1f1f1f;
            border: 1px solid #dadce0;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 12px 24px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
            text-decoration: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
        }

        .google-btn:hover {
            background-color: #f8f9fa;
            border-color: #c3c6ca;
            box-shadow: 0 4px 8px rgba(0,0,0,0.06);
            color: #1f1f1f;
        }

        .google-icon {
            width: 20px;
            height: 20px;
        }

        .form-check-input:checked {
            background-color: #0b2545;
            border-color: #0b2545;
        }

        .alert-desligado {
            background-color: #fff5f5;
            border-left: 4px solid #e53e3e;
            color: #c53030;
            font-weight: 500;
            border-radius: 8px;
            padding: 15px;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .app-footer {
            text-align: center;
            margin-top: 30px;
            font-size: 0.75rem;
            color: #8d99ae;
        }
    </style>
    <script src="../../assets/js/theme-switcher.js"></script>
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container d-flex flex-column align-items-center">
        <main class="login-card">
            <div class="login-header">
                <a href="../../" aria-label="Plataforma RENAST online" class="d-block">
                    <img src="assets/logo-renast-horizontal.png" alt="Plataforma RENAST online" class="login-logo">
                </a>
                <a href="../../carex/" class="text-decoration-none">
                    <h1 class="login-title">Carex-BR</h1>
                </a>
            </div>

            <?php if ($errorMsg !== ''): ?>
                <div class="mb-4">
                    <?php if ($errorType === 'desligado'): ?>
                        <div class="alert-desligado shadow-sm">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?= Security::e($errorMsg) ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger py-3 border-danger-subtle rounded-3 small" role="alert">
                            <i class="bi bi-x-circle-fill me-2"></i>
                            <?= Security::e($errorMsg) ?>
                            <?php if ($debugErrorMsg !== ''): ?>
                                <hr class="my-2">
                                <div class="small">
                                    <strong>Detalhe local:</strong>
                                    <code class="text-break d-block mt-1"><?= Security::e($debugErrorMsg) ?></code>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($googleClient !== null): ?>
                <div class="mb-4">
                    <a href="<?= Security::e($googleAuthUrl) ?>" class="google-btn" id="googleLoginLink">
                        <svg class="google-icon" viewBox="0 0 24 24" width="20" height="20" xmlns="http://www.w3.org/2000/svg">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/>
                        </svg>
                        Entrar com o Google
                    </a>
                </div>
            <?php else: ?>
                <div class="text-center py-3">
                    <button class="btn btn-secondary w-100 py-2.5 rounded-3" disabled>Login indisponível</button>
                </div>
            <?php endif; ?>

            <?php if (($config['app']['env'] ?? '') === 'local' && $devLoginVisible): ?>
                <hr class="my-4 text-muted" style="border-style: dashed;">
                <div class="text-center">
                    <p class="small text-muted mb-3 fw-semibold"><i class="bi bi-code-slash me-1"></i> Modo de Desenvolvimento</p>
                    <div class="d-grid gap-2">
                        <a href="auth-callback.php?mock_login=especialista" class="btn btn-outline-dark btn-sm rounded-3 py-2 text-start px-3 d-flex align-items-center justify-content-between">
                            <span><i class="bi bi-person-workspace me-2 text-success"></i> Entrar como Especialista</span>
                            <span class="badge bg-secondary-subtle text-secondary small">especialista@carex.com</span>
                        </a>
                        <a href="auth-callback.php?mock_login=usuario" class="btn btn-outline-dark btn-sm rounded-3 py-2 text-start px-3 d-flex align-items-center justify-content-between">
                            <span><i class="bi bi-person-check-fill me-2 text-info"></i> Entrar como Usuário Cadastrado</span>
                            <span class="badge bg-secondary-subtle text-secondary small">usuario@carex.com</span>
                        </a>
                        <a href="auth-callback.php?mock_login=desligado" class="btn btn-outline-danger btn-sm rounded-3 py-2 text-start px-3 d-flex align-items-center justify-content-between">
                            <span><i class="bi bi-person-x-fill me-2"></i> Simular Usuário Desligado</span>
                            <span class="badge bg-danger-subtle text-danger small">desligado@carex.com</span>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </main>

        <?php if ($googleClient !== null): ?>
            <div style="max-width: 440px; width: 100%;" class="d-flex justify-content-end mt-2">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="rememberMe" checked>
                    <label class="form-check-label small text-body-secondary fw-semibold" for="rememberMe">Manter-me conectado</label>
                </div>
            </div>
        <?php endif; ?>
        
        <footer class="app-footer">
            <p class="mb-1">&copy; <?= date('Y') ?> Plataforma RENAST online</p>
            <p class="mb-0 text-muted" style="font-size: 0.7rem;">Ministério da Saúde - Carex-BR v1.1</p>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const rememberCheckbox = document.getElementById('rememberMe');
            const googleLink = document.getElementById('googleLoginLink');

            if (rememberCheckbox && googleLink) {
                const updateLink = () => {
                    const baseUrl = <?= json_encode($googleAuthUrl) ?>;
                    if (rememberCheckbox.checked) {
                        // Carry the remember parameter so callback knows
                        googleLink.href = 'login.php?remember=1';
                        // Wait, if clicked, we should set session and redirect
                        googleLink.addEventListener('click', (e) => {
                            e.preventDefault();
                            window.location.href = baseUrl + '&remember=1';
                        });
                    } else {
                        googleLink.href = baseUrl;
                        googleLink.addEventListener('click', (e) => {
                            e.preventDefault();
                            window.location.href = baseUrl;
                        });
                    }
                };

                rememberCheckbox.addEventListener('change', updateLink);
                // Run once
                if (rememberCheckbox.checked) {
                    googleLink.addEventListener('click', (e) => {
                        e.preventDefault();
                        window.location.href = googleLink.href + '&remember=1';
                    });
                }
            }

            // Shortcut to login as admin via Ctrl+Shift+A
            document.addEventListener('keydown', (e) => {
                if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'a') {
                    e.preventDefault();
                    const rememberMeCheckbox = document.getElementById('rememberMe');
                    let targetUrl = 'auth-callback.php?mock_login=admin';
                    if (rememberMeCheckbox && rememberMeCheckbox.checked) {
                        targetUrl += '&remember=1';
                    }
                    window.location.href = targetUrl;
                }
            });
        });
    </script>
</body>
</html>
