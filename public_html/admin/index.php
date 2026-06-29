<?php

declare(strict_types=1);

require __DIR__ . '/../../acesso/src/bootstrap.php';
require_platform_admin();

// Query statistics with fallback try-catch blocks
$stats = [
    'acesso_users' => 0,
    'acesso_inactive' => 0,
    'carex_matrices' => 0,
    'carex_specialists' => 0,
    'fichario_articles' => 0,
    'fichario_projects' => 0,
    'cat_files' => 0,
    'cat_records' => 0,
    'ldrt_diseases' => 0,
];

try {
    $stats['acesso_users'] = (int) db()->query('SELECT COUNT(*) FROM acesso.users')->fetchColumn();
    $stats['acesso_inactive'] = (int) db()->query("SELECT COUNT(*) FROM acesso.users WHERE status <> 'active'")->fetchColumn();
} catch (Throwable $e) {}

try {
    $stats['carex_matrices'] = (int) db()->query('SELECT COUNT(*) FROM carex.tb_matriz')->fetchColumn();
    $stats['carex_specialists'] = (int) db()->query('SELECT COUNT(*) FROM carex.tb_especialista')->fetchColumn();
} catch (Throwable $e) {}

try {
    $stats['fichario_articles'] = (int) db()->query('SELECT COUNT(*) FROM fichario.articles')->fetchColumn();
    $stats['fichario_projects'] = (int) db()->query('SELECT COUNT(*) FROM fichario.projects')->fetchColumn();
} catch (Throwable $e) {}

try {
    $stats['cat_files'] = (int) db()->query('SELECT COUNT(*) FROM cat.arquivos_importacao')->fetchColumn();
    $stats['cat_records'] = (int) db()->query('SELECT COUNT(*) FROM cat.registros_brutos')->fetchColumn();
} catch (Throwable $e) {}

try {
    $stats['ldrt_diseases'] = (int) db()->query('SELECT COUNT(*) FROM ldrt.cid')->fetchColumn();
} catch (Throwable $e) {}

render_header('Administração');
?>
<link href="assets/app.css" rel="stylesheet">

<div class="mb-4">
    <div class="section-title mb-2">Painel Administrativo</div>
    <h1 class="h3 mb-2">Ambiente Administrativo Integrado</h1>
    <p class="muted">Interface integradora de funções administrativas e de controle dos módulos da Plataforma RENAST.</p>
</div>

<div class="row g-4 mb-4">
    <!-- 1. Acesso & Usuarios -->
    <div class="col-md-6 col-lg-4">
        <div class="admin-card accent-acesso p-4">
            <div class="d-flex justify-content-between align-items-start">
                <div class="icon-container">
                    <i class="bi bi-people-fill text-primary"></i>
                </div>
                <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill px-3">Acesso</span>
            </div>
            <h3 class="h5 mb-3">Usuários e Permissões</h3>
            <div class="admin-metric text-primary mb-1"><?= htmlspecialchars((string) $stats['acesso_users']) ?></div>
            <p class="small text-muted mb-4">
                Usuários totais cadastrados.<br>
                <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
                <strong><?= htmlspecialchars((string) $stats['acesso_inactive']) ?></strong> usuários inativos.
            </p>
            <div class="mt-auto d-grid gap-2">
                <a href="usuarios.php" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-person-gear me-2"></i>Gerenciar Usuários</a>
                <a href="permissoes.php" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-shield-lock-fill me-2"></i>Gerenciar Papéis</a>
            </div>
        </div>
    </div>

    <!-- 2. CAREX-BR -->
    <div class="col-md-6 col-lg-4">
        <div class="admin-card accent-carex p-4">
            <div class="d-flex justify-content-between align-items-start">
                <div class="icon-container text-danger">
                    <i class="bi bi-activity"></i>
                </div>
                <span class="badge bg-danger-subtle text-danger-emphasis rounded-pill px-3">CAREX-BR</span>
            </div>
            <h3 class="h5 mb-3">Matrizes de Exposição</h3>
            <div class="admin-metric text-danger mb-1"><?= htmlspecialchars((string) $stats['carex_matrices']) ?></div>
            <p class="small text-muted mb-4">
                Matrizes de exposição ativas.<br>
                <i class="bi bi-person-badge-fill me-1"></i>
                <strong><?= htmlspecialchars((string) $stats['carex_specialists']) ?></strong> especialistas cadastrados.
            </p>
            <div class="mt-auto d-grid gap-2">
                <a href="../carex/administrativo.php" class="btn btn-outline-danger btn-sm text-start"><i class="bi bi-table me-2"></i>Gerenciar Matrizes</a>
                <a href="../carex/desenvolvimento.php" class="btn btn-outline-danger btn-sm text-start"><i class="bi bi-code-slash me-2"></i>Desenvolvimento</a>
            </div>
        </div>
    </div>

    <!-- 3. Fichário Acadêmico -->
    <div class="col-md-6 col-lg-4">
        <div class="admin-card accent-fichario p-4">
            <div class="d-flex justify-content-between align-items-start">
                <div class="icon-container text-success">
                    <i class="bi bi-journal-text"></i>
                </div>
                <span class="badge bg-success-subtle text-success-emphasis rounded-pill px-3">Fichário</span>
            </div>
            <h3 class="h5 mb-3">Acervo Acadêmico</h3>
            <div class="admin-metric text-success mb-1"><?= htmlspecialchars((string) $stats['fichario_articles']) ?></div>
            <p class="small text-muted mb-4">
                Artigos indexados na base.<br>
                <i class="bi bi-folder-fill me-1"></i>
                <strong><?= htmlspecialchars((string) $stats['fichario_projects']) ?></strong> projetos de pesquisa ativos.
            </p>
            <div class="mt-auto d-grid gap-2">
                <a href="../fichario/admin.php" class="btn btn-outline-success btn-sm text-start"><i class="bi bi-sliders me-2"></i>Painel do Fichário</a>
                <a href="../fichario/admin_docs.php" class="btn btn-outline-success btn-sm text-start"><i class="bi bi-file-earmark-text me-2"></i>Documentos do Admin</a>
            </div>
        </div>
    </div>

    <!-- 4. CAT -->
    <div class="col-md-6 col-lg-4">
        <div class="admin-card accent-cat p-4">
            <div class="d-flex justify-content-between align-items-start">
                <div class="icon-container text-secondary">
                    <i class="bi bi-file-earmark-medical"></i>
                </div>
                <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill px-3">CAT</span>
            </div>
            <h3 class="h5 mb-3">Processamento ETL</h3>
            <div class="admin-metric text-secondary mb-1"><?= htmlspecialchars((string) $stats['cat_files']) ?></div>
            <p class="small text-muted mb-4">
                Arquivos de importação processados.<br>
                <i class="bi bi-database me-1"></i>
                <strong><?= htmlspecialchars(number_format($stats['cat_records'], 0, ',', '.')) ?></strong> registros brutos carregados.
            </p>
            <div class="mt-auto d-grid gap-2">
                <a href="../cat/etl.php" class="btn btn-outline-secondary btn-sm text-start"><i class="bi bi-cogs me-2"></i>Processos ETL</a>
                <a href="../cat/campos.php" class="btn btn-outline-secondary btn-sm text-start"><i class="bi bi-table-list me-2"></i>Mapeamento de Campos</a>
            </div>
        </div>
    </div>

    <!-- 5. LDRT -->
    <div class="col-md-6 col-lg-4">
        <div class="admin-card accent-ldrt p-4">
            <div class="d-flex justify-content-between align-items-start">
                <div class="icon-container text-warning" style="color: #944F00 !important;">
                    <i class="bi bi-virus"></i>
                </div>
                <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-3" style="background-color: #FFEDE6 !important; color: #944F00 !important;">LDRT</span>
            </div>
            <h3 class="h5 mb-3">Lista de Doenças</h3>
            <div class="admin-metric text-warning mb-1" style="color: #944F00 !important;"><?= htmlspecialchars((string) $stats['ldrt_diseases']) ?></div>
            <p class="small text-muted mb-4">
                CIDs catalogados na Lista de Doenças Relacionadas ao Trabalho.
            </p>
            <div class="mt-auto d-grid gap-2">
                <a href="../ldrt/rag.php" class="btn btn-outline-warning btn-sm text-start" style="border-color: #944F00 !important; color: #944F00 !important;"><i class="bi bi-robot me-2"></i>Ajustar RAG (Inteligência Artificial)</a>
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>
