<?php

declare(strict_types=1);

use Carex\Database\AdminRepository;
use Carex\Database\Connection;
use Carex\Database\WorkRepository;
use Carex\Http\Security;

$config = require dirname(__DIR__) . '/src/bootstrap.php';

\Carex\Http\Auth::requireLogin();
if ((\Carex\Http\Auth::currentUser()['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Acesso restrito para administradores.');
}

Security::applyHeaders();

// Handle settings saving
$settingsFile = dirname(__DIR__) . '/config/settings.json';
// Usa secrets/.env se existir ou se a raiz nao contiver um arquivo .env
$envPath = dirname(__DIR__) . '/secrets/.env';
if (!file_exists($envPath) && file_exists(dirname(__DIR__) . '/.env')) {
    $envPath = dirname(__DIR__) . '/.env';
}
$redirectToAdmin = static function (array $params = []): void {
    header('Location: administrativo.php?' . http_build_query(array_merge(['tab' => 'usuarios'], $params)));
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Security::isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Token CSRF invalido.');
    }

    $action = $_POST['action'];

    if ($action === 'update_user') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $role = trim((string) ($_POST['role'] ?? ''));
        $status = trim((string) ($_POST['status'] ?? ''));
        $currentUser = \Carex\Http\Auth::currentUser();

        if ($userId <= 0) {
            $redirectToAdmin(['user_error' => 'Usuario invalido.']);
        }

        if ((int) ($currentUser['id'] ?? 0) === $userId && ($role !== 'admin' || $status !== 'ativo')) {
            $redirectToAdmin(['user_error' => 'Voce nao pode remover seu proprio acesso administrativo.']);
        }

        try {
            $writeConfig = $config['database'];
            $writeConfig['allow_writes'] = true;
            $pdo = Connection::make($writeConfig);
            $adminRepository = new AdminRepository($pdo);
            $updatedUser = $adminRepository->updateUserAccess($userId, $role, $status);

            if ((int) ($currentUser['id'] ?? 0) === (int) $updatedUser['id']) {
                \Carex\Http\Auth::updateCurrentUser($updatedUser);
            }

            $redirectToAdmin(['user_saved' => 'true']);
        } catch (InvalidArgumentException $exception) {
            $redirectToAdmin(['user_error' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            $message = $config['app']['debug'] ? $exception->getMessage() : 'Nao foi possivel atualizar o usuario.';
            $redirectToAdmin(['user_error' => $message]);
        }
    }

    if ($action === 'save_settings') {
        $currentSettings = json_decode(file_exists($settingsFile) ? file_get_contents($settingsFile) : '{}', true) ?: [];
        $currentSettings['allow_markdown_edit'] = ($_POST['allow_markdown_edit'] ?? '0') === '1';
        file_put_contents($settingsFile, json_encode($currentSettings, JSON_PRETTY_PRINT));

        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'allow_markdown_edit' => $currentSettings['allow_markdown_edit'],
            ]);
            exit;
        }

        header('Location: administrativo.php?saved=true&tab=settings');
        exit;
    }

    if ($action === 'toggle_dev_login') {
        $currentSettings = json_decode(file_exists($settingsFile) ? file_get_contents($settingsFile) : '{}', true) ?: [];
        $currentSettings['dev_login_visible'] = ($_POST['dev_login_visible'] ?? '0') === '1';
        file_put_contents($settingsFile, json_encode($currentSettings, JSON_PRETTY_PRINT));
        header('Location: administrativo.php?saved=true&tab=settings');
        exit;
    }

    if ($action === 'save_oauth') {
        $clientId     = trim($_POST['google_client_id'] ?? '');
        $clientSecret = trim($_POST['google_client_secret'] ?? '');
        $redirectUri  = trim($_POST['google_redirect_uri'] ?? '');
        $appEnv       = trim($_POST['app_env'] ?? 'local');

        if ($clientId !== '' && $redirectUri !== '') {
            $envContent = file_exists($envPath) ? file_get_contents($envPath) : '';
            $currentEnv = [];
            if (preg_match_all('/^([A-Z0-9_]+)=(.*)$/m', $envContent, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $currentEnv[$match[1]] = $match[2];
                }
            }

            if ($clientSecret === '') {
                $clientSecret = $currentEnv['GOOGLE_CLIENT_SECRET'] ?? ($config['google']['client_secret'] ?? '');
            }

            $redirectParts = parse_url($redirectUri);
            $redirectPath = $redirectParts['path'] ?? '';
            if ($redirectPath === '' || $redirectPath === '/') {
                $publicPath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/quixotesca/carex/public/administrativo.php')), '/');
                $redirectUri = rtrim($redirectUri, '/') . $publicPath . '/auth-callback.php';
            }

            $replace = [
                'GOOGLE_CLIENT_ID'     => $clientId,
                'GOOGLE_CLIENT_SECRET' => $clientSecret,
                'GOOGLE_REDIRECT_URI'  => $redirectUri,
                'APP_ENV'             => $appEnv,
            ];

            foreach ($replace as $key => $value) {
                if (preg_match('/^' . preg_quote($key, '/') . '=/m', $envContent)) {
                    $envContent = preg_replace('/^' . preg_quote($key, '/') . '=.*/m', $key . '=' . $value, $envContent);
                } else {
                    $envContent .= "\n" . $key . '=' . $value;
                }
            }

            file_put_contents($envPath, $envContent);
        }

        header('Location: administrativo.php?saved=true&tab=settings');
        exit;
    }
}

Security::allowReadOnlyRequest();

$settings = json_decode(file_exists($settingsFile) ? file_get_contents($settingsFile) : '{}', true);
$allowMarkdownEdit = $settings['allow_markdown_edit'] ?? true;
$devLoginVisible   = $settings['dev_login_visible'] ?? true;

$bootstrapCss = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
$bootstrapJs = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
$csrfToken = Security::csrfToken();
$databaseWritesAllowed = Connection::writesAllowed($config['database']);

// Current OAuth config for display
$currentClientId    = $config['google']['client_id'] ?? '';
$currentRedirectUri = $config['google']['redirect_uri'] ?? '';
$isOAuthDummy       = str_starts_with($currentClientId, 'dummy') || $currentClientId === '';
$isEnvLocal         = ($config['app']['env'] ?? '') === 'local';

$activeTab = $_GET['tab'] ?? 'usuarios';
if (!in_array($activeTab, ['usuarios', 'matrizes', 'matviews', 'settings'], true)) {
    $activeTab = 'usuarios';
}

$users = [];
$matrices = [];
$materializedViews = [];
$error = '';

try {
    $pdo = Connection::make($config['database']);
    $adminRepository = new AdminRepository($pdo);
    $users = $adminRepository->users();
    $materializedViews = $adminRepository->materializedViews();
    $matrices = (new WorkRepository($pdo))->matrices();
} catch (Throwable $exception) {
    $error = $config['app']['debug'] ? $exception->getMessage() : 'Nao foi possivel carregar os dados administrativos.';
}
?>
<!doctype html>
<html lang="pt-BR" data-module="carex">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="<?= Security::e($csrfToken) ?>">
    <title>CAREX | Administrativo</title>
    <link href="assets/favicon.png" rel="icon" type="image/png">
    <link href="<?= Security::e($bootstrapCss) ?>" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="assets/app.css" rel="stylesheet">
    <script src="../../assets/js/theme-switcher.js"></script>
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>


    <?php
    $activePage = 'administrativo';
    require dirname(__DIR__) . '/src/templates/navbar.php';
    ?>

    <main class="container-fluid app-shell py-3">
        <?php if (isset($_GET['saved']) && $_GET['saved'] === 'true'): ?>
            <div class="alert alert-success alert-dismissible fade show py-2 mb-3 shadow-sm border-success-subtle" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> Configurações salvas com sucesso!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['user_saved']) && $_GET['user_saved'] === 'true'): ?>
            <div class="alert alert-success alert-dismissible fade show py-2 mb-3 shadow-sm border-success-subtle" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> Usuario atualizado com sucesso.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['user_error']) && is_string($_GET['user_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show py-2 mb-3 shadow-sm border-danger-subtle" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= Security::e($_GET['user_error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger py-2 mb-3" role="alert"><?= Security::e($error) ?></div>
        <?php endif; ?>

        <!-- Administrative Navigation Tabs -->
        <ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'usuarios' ? 'active' : '' ?>" id="usuarios-tab" data-bs-toggle="tab" data-bs-target="#usuarios-panel" type="button" role="tab" aria-controls="usuarios-panel" aria-selected="<?= $activeTab === 'usuarios' ? 'true' : 'false' ?>">
                    <i class="bi bi-people-fill me-1"></i> Usuários
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'matrizes' ? 'active' : '' ?>" id="matrizes-tab" data-bs-toggle="tab" data-bs-target="#matrizes-panel" type="button" role="tab" aria-controls="matrizes-panel" aria-selected="<?= $activeTab === 'matrizes' ? 'true' : 'false' ?>">
                    <i class="bi bi-table me-1"></i> Configurações de Matrizes
                </button>
            </li>

            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'matviews' ? 'active' : '' ?>" id="matviews-tab" data-bs-toggle="tab" data-bs-target="#matviews-panel" type="button" role="tab" aria-controls="matviews-panel" aria-selected="<?= $activeTab === 'matviews' ? 'true' : 'false' ?>">
                    Views materializadas
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'settings' ? 'active' : '' ?>" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings-panel" type="button" role="tab" aria-controls="settings-panel" aria-selected="<?= $activeTab === 'settings' ? 'true' : 'false' ?>">
                    <i class="bi bi-gear-fill me-1"></i> Configurações de Sistema
                </button>
            </li>
        </ul>

        <div class="tab-content" id="adminTabContent">
            <!-- Panel 1: Usuários -->
            <div class="tab-pane fade <?= $activeTab === 'usuarios' ? 'show active' : '' ?>" id="usuarios-panel" role="tabpanel" aria-labelledby="usuarios-tab">
                <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
                    <div>
                        <h2 class="h4 mb-0">Usuários cadastrados</h2>
                        <div class="text-body-secondary small"><?= count($users) ?> usuários cadastrados no sistema</div>
                    </div>
                </div>

                <section class="admin-user-grid" aria-label="Usuários cadastrados">
                    <?php foreach ($users as $user): ?>
                        <?php
                            $isCurrentUser = (int) ($user['id'] ?? 0) === (int) (\Carex\Http\Auth::currentUser()['id'] ?? 0);
                            $isPrimaryAdmin = strtolower((string) $user['email']) === 'augustosc@gmail.com';
                            $editLocked = $isCurrentUser || $isPrimaryAdmin;
                        ?>
                        <article class="admin-user-card">
                            <div class="d-flex align-items-start gap-3">
                                <!-- Profile Picture / Icon -->
                                <?php if (!empty($user['profile_picture'])): ?>
                                    <img src="<?= Security::e($user['profile_picture']) ?>" alt="Foto de <?= Security::e($user['name']) ?>" class="rounded-circle border" style="width: 48px; height: 48px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary-subtle d-flex align-items-center justify-content-center border" style="width: 48px; height: 48px;">
                                        <i class="bi bi-person-fill text-secondary fs-4"></i>
                                    </div>
                                <?php endif; ?>

                                <!-- User details -->
                                <div class="flex-grow-1 min-w-0">
                                    <h3 class="h5 mb-1 text-truncate" title="<?= Security::e($user['name']) ?>"><?= Security::e($user['name']) ?></h3>
                                    <div class="text-body-secondary small text-truncate mb-2" title="<?= Security::e($user['email']) ?>"><?= Security::e($user['email']) ?></div>
                                    
                                    <!-- Badges -->
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <?php
                                            $roleClass = match($user['role']) {
                                                'admin' => 'bg-primary-subtle text-primary border-primary-subtle',
                                                'especialista' => 'bg-success-subtle text-success border-success-subtle',
                                                default => 'bg-secondary-subtle text-secondary border-secondary-subtle',
                                            };
                                            $statusClass = match($user['status']) {
                                                'ativo' => 'bg-success-subtle text-success border-success-subtle',
                                                'desligado' => 'bg-danger-subtle text-danger border-danger-subtle',
                                                default => 'bg-warning-subtle text-warning border-warning-subtle',
                                            };
                                        ?>
                                        <span class="badge border <?= $roleClass ?> text-capitalize" style="font-size: 0.75rem; font-weight: 600; padding: 4px 8px;">
                                            <?= Security::e($user['role']) ?>
                                        </span>
                                        <span class="badge border <?= $statusClass ?> text-capitalize" style="font-size: 0.75rem; font-weight: 600; padding: 4px 8px;">
                                            <?= Security::e($user['status']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <form method="POST" action="administrativo.php" class="admin-user-access-form border-top pt-3 mt-1">
                                <input type="hidden" name="action" value="update_user">
                                <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken) ?>">
                                <input type="hidden" name="user_id" value="<?= Security::e($user['id']) ?>">

                                <div class="row g-2 align-items-end">
                                    <div class="col-12 col-sm-6">
                                        <label class="form-label small fw-semibold mb-1" for="userRole<?= Security::e($user['id']) ?>">Perfil</label>
                                        <select class="form-select form-select-sm" id="userRole<?= Security::e($user['id']) ?>" name="role" <?= $editLocked ? 'disabled' : '' ?>>
                                            <option value="usuario" <?= $user['role'] === 'usuario' ? 'selected' : '' ?>>Usuario cadastrado</option>
                                            <option value="especialista" <?= $user['role'] === 'especialista' ? 'selected' : '' ?>>Especialista</option>
                                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <label class="form-label small fw-semibold mb-1" for="userStatus<?= Security::e($user['id']) ?>">Status</label>
                                        <select class="form-select form-select-sm" id="userStatus<?= Security::e($user['id']) ?>" name="status" <?= $editLocked ? 'disabled' : '' ?>>
                                            <option value="ativo" <?= $user['status'] === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                                            <option value="desligado" <?= $user['status'] === 'desligado' ? 'selected' : '' ?>>Desligado</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                                    <?php if ($isCurrentUser): ?>
                                        <div class="small text-body-secondary">
                                            Seu proprio acesso admin fica protegido contra alteracao acidental.
                                        </div>
                                    <?php elseif ($isPrimaryAdmin): ?>
                                        <div class="small text-body-secondary">
                                            Conta administradora principal protegida.
                                        </div>
                                    <?php else: ?>
                                        <div class="small text-body-secondary">
                                            Especialista e usuario cadastrado nao acessam Administracao nem Desenvolvimento.
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-primary d-flex align-items-center gap-1">
                                            <i class="bi bi-save-fill"></i> Salvar acesso
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </form>

                            <!-- Google linkage details -->
                            <div class="border-top pt-3 mt-1 text-body-secondary small">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-semibold">ID do Google:</span>
                                    <span class="text-end font-monospace text-truncate ms-2" style="max-width: 180px;" title="<?= Security::e($user['google_id']) ?>">
                                        <?= Security::e($user['google_id']) ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Cadastrado em:</span>
                                    <span class="text-end text-body"><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Último Login:</span>
                                    <span class="text-end text-body"><?= date('d/m/Y H:i', strtotime($user['updated_at'])) ?></span>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
            </div>

            <!-- Panel 2: Configurações de Matrizes e Especialistas Vinculados -->
            <div class="tab-pane fade <?= $activeTab === 'matrizes' ? 'show active' : '' ?>" id="matrizes-panel" role="tabpanel" aria-labelledby="matrizes-tab">
                <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
                    <div>
                        <h2 class="h4 mb-0">Matrizes e Classificadores</h2>
                        <div class="text-body-secondary small"><?= count($matrices) ?> matrizes mapeadas no sistema</div>
                    </div>
                </div>

                <div class="card border-light-subtle shadow-sm p-4 mb-4 bg-white">
                    <div class="table-responsive">
                        <table class="table align-middle table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Matriz</th>
                                    <th scope="col">Código</th>
                                    <th scope="col">Itens da Matriz</th>
                                    <th scope="col">Itens Classificados</th>
                                    <th scope="col">Avanço Geral</th>
                                    <th scope="col" style="width: 150px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($matrices as $matrix): ?>
                                    <tr>
                                        <td><strong><?= Security::e($matrix['no_matriz']) ?></strong></td>
                                        <td><code><?= Security::e($matrix['id_matriz']) ?></code></td>
                                        <td><?= number_format((int) $matrix['total_itens'], 0, ',', '.') ?></td>
                                        <td><?= number_format((int) $matrix['total_classificados'], 0, ',', '.') ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="height: 6px; min-width: 80px;" role="progressbar" aria-valuenow="<?= Security::e($matrix['percentual_classificado']) ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <div class="progress-bar bg-success" style="width: <?= Security::e($matrix['percentual_classificado']) ?>%"></div>
                                                </div>
                                                <span class="small fw-semibold"><?= number_format((float) $matrix['percentual_classificado'], 1, ',', '.') ?>%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <a class="btn btn-xs btn-outline-primary py-1 px-2" style="font-size: 0.75rem;" href="matriz.php?id_matriz=<?= urlencode($matrix['id_matriz']) ?>">
                                                Gerenciar
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Panel 3: Configurações de Sistema -->
            <div class="tab-pane fade <?= $activeTab === 'matviews' ? 'show active' : '' ?>" id="matviews-panel" role="tabpanel" aria-labelledby="matviews-tab">
                <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
                    <div>
                        <h2 class="h4 mb-0">Atualizacao de views materializadas</h2>
                        <div class="text-body-secondary small"><?= count($materializedViews) ?> views materializadas no schema atual</div>
                    </div>
                </div>

                <div class="alert <?= $databaseWritesAllowed ? 'alert-warning' : 'alert-danger' ?> py-2" role="alert">
                    <?php if ($databaseWritesAllowed): ?>
                        Atualize uma view por vez. A operacao executa <code>REFRESH MATERIALIZED VIEW</code> e pode levar alguns segundos ou minutos conforme o volume de dados.
                    <?php else: ?>
                        Base em modo somente leitura. Atualizacao de views materializadas esta bloqueada por configuracao de seguranca.
                    <?php endif; ?>
                </div>

                <div class="table-responsive border rounded-2">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">View materializada</th>
                                <th scope="col">Linhas estimadas</th>
                                <th scope="col">Indices</th>
                                <th scope="col">Populada</th>
                                <th scope="col">Status</th>
                                <th scope="col">Acao</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materializedViews as $view): ?>
                                <tr data-matview-row="<?= Security::e($view['name']) ?>">
                                    <td>
                                        <div class="fw-semibold"><?= Security::e($view['name']) ?></div>
                                        <?php if ($view['comment'] !== ''): ?>
                                            <div class="text-body-secondary small"><?= Security::e($view['comment']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= number_format((int) $view['estimated_rows'], 0, ',', '.') ?></td>
                                    <td>
                                        <span class="badge <?= $view['hasindexes'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                            <?= $view['hasindexes'] ? 'Sim' : 'Nao' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $view['ispopulated'] ? 'text-bg-success' : 'text-bg-warning' ?>">
                                            <?= $view['ispopulated'] ? 'Sim' : 'Nao' ?>
                                        </span>
                                    </td>
                                    <td><span class="badge text-bg-light border" data-refresh-status>Pronta</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" type="button" data-refresh-matview="<?= Security::e($view['name']) ?>" data-estimated-rows="<?= Security::e($view['estimated_rows']) ?>" <?= $databaseWritesAllowed ? '' : 'disabled' ?>>
                                            <?= $databaseWritesAllowed ? 'Atualizar' : 'Bloqueado' ?>
                                        </button>
                                    </td>
                                </tr>
                                <tr class="matview-progress-row d-none" data-matview-progress-row="<?= Security::e($view['name']) ?>">
                                    <td colspan="6">
                                        <div class="matview-progress-shell" aria-live="polite">
                                            <div class="d-flex justify-content-between gap-3 small mb-1">
                                                <span class="fw-semibold" data-refresh-progress-label>Aguardando atualizacao</span>
                                                <span class="text-body-secondary" data-refresh-progress-elapsed>00:00</span>
                                            </div>
                                            <div class="progress matview-refresh-progress" role="progressbar" aria-label="Progresso estimado da atualizacao" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                                <div class="progress-bar progress-bar-striped progress-bar-animated" data-refresh-progress-bar style="width: 0%">0%</div>
                                            </div>
                                            <div class="text-body-secondary small mt-1" data-refresh-progress-note>
                                                Progresso estimado por tempo decorrido; a conclusao real e confirmada pelo banco.
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade <?= $activeTab === 'settings' ? 'show active' : '' ?>" id="settings-panel" role="tabpanel" aria-labelledby="settings-tab">
                <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
                    <div>
                        <h2 class="h4 mb-0">Parâmetros do Sistema</h2>
                        <div class="text-body-secondary small">Defina restrições globais e credenciais da plataforma</div>
                    </div>
                </div>

                <div class="d-flex flex-column gap-4" style="max-width: 680px;">

                    <!-- Google OAuth Config -->
                    <div class="card border-light-subtle shadow-sm p-4">
                        <?php
                            $proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                            $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
                            $publicPath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/quixotesca/carex/public/administrativo.php')), '/');
                            $suggestedOrigin = $proto . '://' . $host;
                            $suggestedUri = $suggestedOrigin . $publicPath . '/auth-callback.php';
                        ?>
                        <h3 class="h5 mb-1 d-flex align-items-center gap-2">
                            <svg viewBox="0 0 24 24" width="20" height="20" xmlns="http://www.w3.org/2000/svg">
                                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/>
                                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/>
                            </svg>
                            Google OAuth
                            <?php if ($isOAuthDummy): ?>
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-1" style="font-size:0.7rem;">DEMO / Sem credenciais reais</span>
                            <?php else: ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size:0.7rem;"><i class="bi bi-check-circle-fill me-1"></i>Credenciais configuradas</span>
                            <?php endif; ?>
                        </h3>
                        <p class="text-body-secondary small mb-3">Configure as credenciais do Google Cloud Console para ativar o login real com Google.</p>

                        <?php if ($isOAuthDummy): ?>
                        <div class="alert alert-warning py-2 small mb-3">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            <strong>Modo Demo ativo:</strong> o botão "Entrar com Google" está usando mock login. Siga o guia abaixo para ativar o OAuth real.
                        </div>
                        <?php endif; ?>

                        <!-- Guia passo a passo colapsável -->
                        <div class="mb-4">
                            <button class="btn btn-outline-primary btn-sm d-flex align-items-center gap-2 mb-3" type="button"
                                data-bs-toggle="collapse" data-bs-target="#oauthGuide"
                                aria-expanded="<?= $isOAuthDummy ? 'true' : 'false' ?>" aria-controls="oauthGuide">
                                <i class="bi bi-question-circle-fill"></i>
                                Como obter as credenciais no Google Cloud Console
                                <i class="bi bi-chevron-down ms-1"></i>
                            </button>

                            <div class="collapse <?= $isOAuthDummy ? 'show' : '' ?>" id="oauthGuide">
                                <div class="border rounded-3 p-3 bg-light" style="font-size: 0.875rem; line-height: 1.7;">

                                    <div class="d-flex align-items-center gap-2 mb-3">
                                        <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener"
                                           class="btn btn-sm btn-primary d-flex align-items-center gap-1">
                                            <i class="bi bi-box-arrow-up-right"></i> Abrir Google Cloud Console
                                        </a>
                                        <span class="text-muted small">Faça login com sua conta Google</span>
                                    </div>

                                    <hr class="my-2">

                                    <!-- Passo 1 -->
                                    <div class="d-flex gap-3 mb-3">
                                        <div class="flex-shrink-0 rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold"
                                             style="width:28px;height:28px;min-width:28px;font-size:0.8rem;">1</div>
                                        <div>
                                            <strong>Criar ou selecionar um projeto</strong>
                                            <div class="text-muted mt-1">No topo da página clique em <kbd>Selecionar projeto</kbd> → <kbd>Novo Projeto</kbd>. Nomeie como <strong>CAREX</strong> e clique em <kbd>Criar</kbd>.</div>
                                        </div>
                                    </div>

                                    <!-- Passo 2 -->
                                    <div class="d-flex gap-3 mb-3">
                                        <div class="flex-shrink-0 rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold"
                                             style="width:28px;height:28px;min-width:28px;font-size:0.8rem;">2</div>
                                        <div>
                                            <strong>Configurar a Tela de Consentimento OAuth</strong>
                                            <div class="text-muted mt-1">No menu lateral: <strong>APIs e Serviços</strong> → <strong>Tela de consentimento do OAuth</strong>.</div>
                                            <ul class="mb-1 ps-3 mt-1 text-muted">
                                                <li>Tipo de usuário: <strong>Externo</strong> (ou Interno se tiver Google Workspace)</li>
                                                <li>Nome do app: <code>CAREX</code> &nbsp;·&nbsp; E-mail de suporte: seu e-mail</li>
                                                <li>Escopos: clique em <kbd>Adicionar escopos</kbd> e marque <strong>email</strong> e <strong>profile</strong></li>
                                                <li>Usuários de teste: adicione seu e-mail para testes sem aprovação do Google</li>
                                            </ul>
                                            <div class="text-warning-emphasis small mt-1"><i class="bi bi-info-circle me-1"></i>Salve e avance até <strong>Publicar o aplicativo</strong>, ou deixe em teste e adicione usuários manualmente.</div>
                                        </div>
                                    </div>

                                    <!-- Passo 3 -->
                                    <div class="d-flex gap-3 mb-3">
                                        <div class="flex-shrink-0 rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold"
                                             style="width:28px;height:28px;min-width:28px;font-size:0.8rem;">3</div>
                                        <div>
                                            <strong>Criar as credenciais OAuth 2.0</strong>
                                            <div class="alert alert-info py-2 my-2 small">
                                                <i class="bi bi-info-circle-fill me-1"></i>
                                                No Google existem dois campos parecidos. No campo de cima, <strong>Origens JavaScript autorizadas</strong>, cole apenas <code><?= Security::e($suggestedOrigin) ?></code>. No campo de baixo, <strong>URIs de redirecionamento autorizados</strong>, cole <code><?= Security::e($suggestedUri) ?></code>.
                                            </div>
                                            <div class="row g-2 mb-3">
                                                <div class="col-12 col-md-6">
                                                    <div class="border rounded bg-white p-2 h-100">
                                                        <div class="fw-semibold text-danger small mb-1">Campo de cima: Origens JavaScript</div>
                                                        <code class="small text-break d-block"><?= Security::e($suggestedOrigin) ?></code>
                                                        <div class="text-muted small mt-1">Sem <code>/carex</code>, sem <code>/public</code>, sem <code>/auth-callback.php</code> e sem barra final.</div>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <div class="border rounded bg-white p-2 h-100">
                                                        <div class="fw-semibold text-success small mb-1">Campo de baixo: URIs de redirecionamento</div>
                                                        <code class="small text-break d-block"><?= Security::e($suggestedUri) ?></code>
                                                        <div class="text-muted small mt-1">Aqui entra a callback completa do CAREX.</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-muted mt-1">No menu lateral: <strong>Credenciais</strong> → <kbd>+ Criar Credenciais</kbd> → <strong>ID do cliente OAuth</strong>.</div>
                                            <ul class="mb-2 ps-3 mt-1 text-muted">
                                                <li>Tipo de aplicativo: <strong>Aplicativo da Web</strong></li>
                                                <li>Nome: <code>CAREX Web</code></li>
                                                <li>URIs de redirecionamento autorizados → <kbd>+ Adicionar URI</kbd>:</li>
                                            </ul>
                                            <div class="bg-white border rounded p-2 font-monospace small mb-2 d-flex align-items-center gap-2 flex-wrap">
                                                <span class="text-break flex-grow-1"><?= Security::e($suggestedUri) ?></span>
                                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 flex-shrink-0" style="font-size:0.75rem;"
                                                    onclick="navigator.clipboard.writeText(<?= json_encode($suggestedUri) ?>).then(()=>{this.innerHTML='<i class=\'bi bi-check-lg text-success\'></i> Copiado!';setTimeout(()=>this.innerHTML='<i class=\'bi bi-clipboard\'></i> Copiar',1500)})">
                                                    <i class="bi bi-clipboard"></i> Copiar
                                                </button>
                                            </div>
                                            <div class="text-muted small">Clique em <kbd>Criar</kbd>. Uma janela exibirá o <strong>Client ID</strong> e o <strong>Client Secret</strong> — copie os dois antes de fechar.</div>
                                        </div>
                                    </div>

                                    <!-- Passo 4 -->
                                    <div class="d-flex gap-3">
                                        <div class="flex-shrink-0 rounded-circle bg-success text-white d-flex align-items-center justify-content-center fw-bold"
                                             style="width:28px;height:28px;min-width:28px;font-size:0.8rem;">4</div>
                                        <div>
                                            <strong>Cole os valores nos campos abaixo e salve</strong>
                                            <div class="text-muted mt-1">Cole o <strong>Client ID</strong>, o <strong>Client Secret</strong> e confirme a <strong>Redirect URI</strong>. Clique em <kbd>Salvar Credenciais OAuth</kbd>.</div>
                                            <div class="text-success-emphasis small mt-1"><i class="bi bi-check-circle-fill me-1"></i>Após salvar, o botão "Entrar com Google" usará OAuth real automaticamente, mesmo com <code>APP_ENV=local</code>.</div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <!-- Formulário de credenciais -->
                        <form method="POST" action="administrativo.php">
                            <input type="hidden" name="action" value="save_oauth">
                            <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken) ?>">

                            <div class="mb-3">
                                <label for="googleClientId" class="form-label fw-semibold small">
                                    Client ID <span class="text-danger">*</span>
                                    <span class="fw-normal text-muted">— copiado da janela de credenciais</span>
                                </label>
                                <input type="text" class="form-control form-control-sm font-monospace"
                                    id="googleClientId" name="google_client_id"
                                    placeholder="123456789-abcdefghij.apps.googleusercontent.com"
                                    value="<?= Security::e($isOAuthDummy ? '' : $currentClientId) ?>"
                                    autocomplete="off">
                                <div class="form-text">Termina sempre em <code>.apps.googleusercontent.com</code></div>
                            </div>

                            <div class="mb-3">
                                <label for="googleClientSecret" class="form-label fw-semibold small">
                                    Client Secret <span class="text-danger">*</span>
                                    <span class="fw-normal text-muted">— copiado junto com o Client ID</span>
                                </label>
                                <div class="input-group input-group-sm">
                                    <input type="password" class="form-control font-monospace"
                                        id="googleClientSecret" name="google_client_secret"
                                        placeholder="GOCSPX-..."
                                        autocomplete="new-password">
                                    <button class="btn btn-outline-secondary" type="button" id="toggleSecret"
                                        onclick="const f=document.getElementById('googleClientSecret');f.type=f.type==='password'?'text':'password';this.innerHTML=f.type==='password'?'<i class=\'bi bi-eye\'></i>':'<i class=\'bi bi-eye-slash\'></i>'">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Começa com <code>GOCSPX-</code>. Deixe em branco para manter o valor atual sem alteração.</div>
                            </div>

                            <div class="mb-3">
                                <label for="googleRedirectUri" class="form-label fw-semibold small">
                                    Redirect URI <span class="text-danger">*</span>
                                    <span class="fw-normal text-muted">— deve ser idêntica ao que foi cadastrado no Google (Passo 3)</span>
                                </label>
                                <div class="input-group input-group-sm">
                                    <input type="url" class="form-control font-monospace"
                                        id="googleRedirectUri" name="google_redirect_uri"
                                        placeholder="https://www.renastonline.org/carex/public/auth-callback.php"
                                        value="<?= Security::e($currentRedirectUri) ?>"
                                        autocomplete="off">
                                    <button class="btn btn-outline-secondary" type="button"
                                        onclick="document.getElementById('googleRedirectUri').value=<?= json_encode($suggestedUri) ?>"
                                        title="Preencher com a URI detectada deste servidor">
                                        <i class="bi bi-magic"></i>
                                    </button>
                                </div>
                                <div class="form-text">
                                    URI detectada deste servidor: <code class="user-select-all"><?= Security::e($suggestedUri) ?></code>
                                    <br>
                                    Origem JavaScript para o Google, sem caminho: <code class="user-select-all"><?= Security::e($suggestedOrigin) ?></code>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="appEnvSelect" class="form-label fw-semibold small">
                                    Ambiente (APP_ENV)
                                    <span class="fw-normal text-muted">— define o modo de operação do sistema</span>
                                </label>
                                <select class="form-select form-select-sm" id="appEnvSelect" name="app_env">
                                    <option value="local" <?= $isEnvLocal ? 'selected' : '' ?>>
                                        local — Desenvolvimento: botões de bypass visíveis na tela de login
                                    </option>
                                    <option value="production" <?= !$isEnvLocal ? 'selected' : '' ?>>
                                        production — Produção: somente login Google real, sem bypass
                                    </option>
                                </select>
                                <div class="form-text">
                                    <?php if ($isEnvLocal): ?>
                                        <i class="bi bi-info-circle text-warning me-1"></i>Atual: <strong>local</strong>. Mude para <strong>production</strong> ao fazer o deploy no servidor real.
                                    <?php else: ?>
                                        <i class="bi bi-check-circle text-success me-1"></i>Atual: <strong>production</strong>. Os botões de bypass estão desativados na tela de login.
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                                    <i class="bi bi-save-fill"></i> Salvar Credenciais OAuth
                                </button>
                                <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-box-arrow-up-right me-1"></i> Google Cloud Console
                                </a>
                                <?php if (!$isOAuthDummy): ?>
                                    <a href="login.php" target="_blank" class="btn btn-outline-success btn-sm">
                                        <i class="bi bi-box-arrow-in-right me-1"></i> Testar Login
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                    <!-- Dev Login Bypass Toggle -->
                    <?php if ($isEnvLocal): ?>
                    <div class="card border-warning-subtle shadow-sm p-4">
                        <h3 class="h5 mb-1 d-flex align-items-center gap-2">
                            <i class="bi bi-code-slash text-warning"></i> Modo de Desenvolvimento
                            <?php if ($devLoginVisible): ?>
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-1" style="font-size:0.7rem;">ATIVO</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border ms-1" style="font-size:0.7rem;">Desativado</span>
                            <?php endif; ?>
                        </h3>
                        <p class="text-body-secondary small mb-3">Controla a visibilidade dos botões de bypass (mock login) na tela de login. Útil para ocultar os atalhos sem alterar o <code>APP_ENV</code>.</p>
                        <form method="POST" action="administrativo.php">
                            <input type="hidden" name="action" value="toggle_dev_login">
                            <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken) ?>">
                            <?php if ($devLoginVisible): ?>
                                <input type="hidden" name="dev_login_visible" value="0">
                                <button type="submit" class="btn btn-sm btn-outline-warning d-flex align-items-center gap-1">
                                    <i class="bi bi-eye-slash"></i> Ocultar botões de bypass no login
                                </button>
                            <?php else: ?>
                                <input type="hidden" name="dev_login_visible" value="1">
                                <button type="submit" class="btn btn-sm btn-outline-success d-flex align-items-center gap-1">
                                    <i class="bi bi-eye"></i> Mostrar botões de bypass no login
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                    <?php endif; ?>

                    <!-- Markdown Editor Toggle -->
                    <div class="card border-light-subtle shadow-sm p-4">
                        <h3 class="h5 mb-3 d-flex align-items-center gap-2"><i class="bi bi-file-earmark-code text-primary"></i> Edição de Arquivos MD</h3>
                        <form method="POST" action="administrativo.php" id="markdownSettingsForm">
                            <input type="hidden" name="action" value="save_settings">
                            <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken) ?>">
                            <div class="mb-4">
                                <label class="form-label d-block fw-semibold mb-2">Editor Markdown Externo (Landing Page)</label>
                                <div class="form-text text-muted mb-3">Ative ou desative a edição dos arquivos de documentação (<code>landing.md</code> e <code>sobre.md</code>) diretamente na plataforma.</div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="allowMarkdownEdit" name="allow_markdown_edit" value="1" <?= $allowMarkdownEdit ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="allowMarkdownEdit">Permitir edição de arquivos MD externos</label>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 small text-body-secondary mb-2" id="markdownSettingsStatus" role="status" aria-live="polite">
                                <i class="bi bi-check-circle text-success"></i>
                                <span>Alteracoes salvas automaticamente ao mudar o switch.</span>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm d-none align-items-center gap-1 shadow-sm">
                                <i class="bi bi-save-fill"></i> Salvar Configurações
                            </button>
                        </form>
                    </div>

                </div>
            </div>


        </div>
    </main>

    <script src="<?= Security::e($bootstrapJs) ?>" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="assets/admin.js?v=<?= time() ?>"></script>
</body>
</html>
