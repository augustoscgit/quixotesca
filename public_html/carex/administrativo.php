<?php

declare(strict_types=1);

use Carex\Database\AdminRepository;
use Carex\Database\Connection;
use Carex\Database\WorkRepository;
use Carex\Http\Security;

$config = require dirname(__DIR__, 2) . '/carex' . '/src/bootstrap.php';

\Carex\Http\Auth::requireAdmin();

Security::applyHeaders();

// Handle settings saving
$settingsFile = dirname(__DIR__, 2) . '/carex' . '/config/settings.json';
// Usa secrets/.env se existir ou se a raiz nao contiver um arquivo .env
$envPath = dirname(__DIR__, 2) . '/carex' . '/secrets/.env';
if (!file_exists($envPath) && file_exists(dirname(__DIR__, 2) . '/carex' . '/.env')) {
    $envPath = dirname(__DIR__, 2) . '/carex' . '/.env';
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
        $redirectToAdmin(['user_error' => 'Usuarios, papeis e status agora sao gerenciados no modulo Acesso.']);
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


}

Security::allowReadOnlyRequest();

$settings = json_decode(file_exists($settingsFile) ? file_get_contents($settingsFile) : '{}', true);
$allowMarkdownEdit = $settings['allow_markdown_edit'] ?? true;

$bootstrapCss = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
$bootstrapJs = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
$csrfToken = Security::csrfToken();
$databaseWritesAllowed = Connection::writesAllowed($config['database']);

$activeTab = $_GET['tab'] ?? 'usuarios';
if (!in_array($activeTab, ['usuarios', 'matrizes', 'matviews', 'settings'], true)) {
    $activeTab = 'usuarios';
}

$matrices = [];
$materializedViews = [];
$error = '';

try {
    $pdo = Connection::make($config['database']);
    $adminRepository = new AdminRepository($pdo);
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
    <link href="../assets/favicon.png" rel="icon" type="image/png">
    <link href="<?= Security::e($bootstrapCss) ?>" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="assets/app.css" rel="stylesheet">
    <script src="../assets/js/theme-switcher.js"></script>
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>


    <?php
    $activePage = 'administrativo';
    require dirname(__DIR__, 2) . '/carex' . '/src/templates/navbar.php';
    ?>

    <main class="container-fluid app-shell py-3">
        <?php if (isset($_GET['saved']) && $_GET['saved'] === 'true'): ?>
            <div class="alert alert-success alert-dismissible fade show py-2 mb-3 shadow-sm border-success-subtle" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> Configura��es salvas com sucesso!
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
                    <i class="bi bi-people-fill me-1"></i> Usu�rios
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'matrizes' ? 'active' : '' ?>" id="matrizes-tab" data-bs-toggle="tab" data-bs-target="#matrizes-panel" type="button" role="tab" aria-controls="matrizes-panel" aria-selected="<?= $activeTab === 'matrizes' ? 'true' : 'false' ?>">
                    <i class="bi bi-table me-1"></i> Configura��es de Matrizes
                </button>
            </li>

            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'matviews' ? 'active' : '' ?>" id="matviews-tab" data-bs-toggle="tab" data-bs-target="#matviews-panel" type="button" role="tab" aria-controls="matviews-panel" aria-selected="<?= $activeTab === 'matviews' ? 'true' : 'false' ?>">
                    Views materializadas
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'settings' ? 'active' : '' ?>" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings-panel" type="button" role="tab" aria-controls="settings-panel" aria-selected="<?= $activeTab === 'settings' ? 'true' : 'false' ?>">
                    <i class="bi bi-gear-fill me-1"></i> Configura��es de Sistema
                </button>
            </li>
        </ul>

        <div class="tab-content" id="adminTabContent">
            <!-- Panel 1: Usu�rios -->
            <div class="tab-pane fade <?= $activeTab === 'usuarios' ? 'show active' : '' ?>" id="usuarios-panel" role="tabpanel" aria-labelledby="usuarios-tab">
                <section class="panel p-4">
                    <h2 class="h4 mb-2">Usuarios e papeis centralizados</h2>
                    <p class="text-body-secondary mb-4">O CAREX nao gerencia mais perfis locais. Use o modulo Acesso para criar usuarios, alterar status e atribuir os papeis centrais admin ou user.</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-primary" href="../admin/usuarios.php">
                            <i class="bi bi-people-fill me-1"></i> Gerenciar usuarios
                        </a>
                        <a class="btn btn-outline-primary" href="../admin/permissoes.php">
                            <i class="bi bi-shield-lock-fill me-1"></i> Gerenciar papeis
                        </a>
                    </div>
                </section>
            </div>

            <!-- Panel 2: Configura��es de Matrizes e Especialistas Vinculados -->
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
                                    <th scope="col">C�digo</th>
                                    <th scope="col">Itens da Matriz</th>
                                    <th scope="col">Itens Classificados</th>
                                    <th scope="col">Avan�o Geral</th>
                                    <th scope="col" style="width: 150px;">A��es</th>
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
                                                    <div class="progress-bar" style="width: <?= Security::e($matrix['percentual_classificado']) ?>%"></div>
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

            <!-- Panel 3: Configura��es de Sistema -->
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
                                                <div class="progress-bar" data-refresh-progress-bar style="width: 0%">0%</div>
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
                        <h2 class="h4 mb-0">Par�metros do Sistema</h2>
                        <div class="text-body-secondary small">Defina restri��es globais e credenciais da plataforma</div>
                    </div>
                </div>

                <div class="d-flex flex-column gap-4" style="max-width: 680px;">



                    <!-- Markdown Editor Toggle -->
                    <div class="card border-light-subtle shadow-sm p-4">
                        <h3 class="h5 mb-3 d-flex align-items-center gap-2"><i class="bi bi-file-earmark-code text-primary"></i> Edi��o de Arquivos MD</h3>
                        <form method="POST" action="administrativo.php" id="markdownSettingsForm">
                            <input type="hidden" name="action" value="save_settings">
                            <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken) ?>">
                            <div class="mb-4">
                                <label class="form-label d-block fw-semibold mb-2">Editor Markdown Externo (Landing Page)</label>
                                <div class="form-text text-muted mb-3">Ative ou desative a edi��o dos arquivos de documenta��o (<code>landing.md</code> e <code>sobre.md</code>) diretamente na plataforma.</div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="allowMarkdownEdit" name="allow_markdown_edit" value="1" <?= $allowMarkdownEdit ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="allowMarkdownEdit">Permitir edi��o de arquivos MD externos</label>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 small text-body-secondary mb-2" id="markdownSettingsStatus" role="status" aria-live="polite">
                                <i class="bi bi-check-circle text-success"></i>
                                <span>Alteracoes salvas automaticamente ao mudar o switch.</span>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm d-none align-items-center gap-1 shadow-sm">
                                <i class="bi bi-save-fill"></i> Salvar Configura��es
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
