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
<div class="mb-4">
    <p class="text-body-secondary small text-uppercase mb-1">Painel Administrativo</p>
    <h1 class="h3 mb-2">Ambiente Administrativo Integrado</h1>
    <p class="text-body-secondary">Interface integradora de funções administrativas e de controle dos módulos da Plataforma RENAST.</p>
</div>

<div class="card mb-4">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h2 class="h5 mb-1">Padroes Bootstrap-first</h2>
            <p class="text-body-secondary mb-0">Planejamento, contrato visual por modulo e exemplos para reconstruir a interface sem tema paralelo.</p>
        </div>
        <a href="../carex/desenvolvimento.php?view=docs" class="btn btn-primary">
            <i class="bi bi-book me-1" aria-hidden="true"></i>
            Abrir documentacao
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- 1. Acesso & Usuarios -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 p-4">
            <div class="d-flex justify-content-between align-items-start">
                <div class="fs-3 text-body-secondary">
                    <i class="bi bi-people-fill text-primary"></i>
                </div>
                <span class="badge text-bg-secondary rounded-pill px-3">Acesso</span>
            </div>
            <h3 class="h5 mb-3">Usuários e Permissões</h3>
            <div class="display-6 mb-1"><?= htmlspecialchars((string) $stats['acesso_users']) ?></div>
            <p class="small text-body-secondary mb-4">
                Usuários totais cadastrados.<br>
                <i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>
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
        <div class="card h-100 p-4">
            <div class="d-flex justify-content-between align-items-start">
                <div class="fs-3 text-body-secondary">
                    <i class="bi bi-activity"></i>
                </div>
                <span class="badge text-bg-secondary rounded-pill px-3">CAREX-BR</span>
            </div>
            <h3 class="h5 mb-3">Matrizes de Exposição</h3>
            <div class="display-6 mb-1"><?= htmlspecialchars((string) $stats['carex_matrices']) ?></div>
            <p class="small text-body-secondary mb-4">
                Matrizes de exposição ativas.<br>
                <i class="bi bi-person-badge-fill me-1"></i>
                <strong><?= htmlspecialchars((string) $stats['carex_specialists']) ?></strong> especialistas cadastrados.
            </p>
            <div class="mt-auto d-grid gap-2">
                <a href="../carex/administrativo.php" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-table me-2"></i>Gerenciar Matrizes</a>
                <a href="../carex/desenvolvimento.php?view=docs" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-code-slash me-2"></i>Desenvolvimento</a>
            </div>
        </div>
    </div>

    <!-- 3. Fichário Acadêmico -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 p-4">
            <div class="d-flex justify-content-between align-items-start">
                <div class="fs-3 text-body-secondary">
                    <i class="bi bi-journal-text"></i>
                </div>
                <span class="badge text-bg-secondary rounded-pill px-3">Fichário</span>
            </div>
            <h3 class="h5 mb-3">Acervo Acadêmico</h3>
            <div class="display-6 mb-1"><?= htmlspecialchars((string) $stats['fichario_articles']) ?></div>
            <p class="small text-body-secondary mb-4">
                Artigos indexados na base.<br>
                <i class="bi bi-folder-fill me-1"></i>
                <strong><?= htmlspecialchars((string) $stats['fichario_projects']) ?></strong> projetos de pesquisa ativos.
            </p>
            <div class="mt-auto d-grid gap-2">
                <a href="../fichario/admin.php" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-sliders me-2"></i>Painel do Fichário</a>
                <a href="../fichario/admin_docs.php" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-file-earmark-text me-2"></i>Documentos do Admin</a>
            </div>
        </div>
    </div>

    <!-- 4. CAT -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 p-4">
            <div class="d-flex justify-content-between align-items-start">
                <div class="fs-3 text-body-secondary">
                    <i class="bi bi-file-earmark-medical"></i>
                </div>
                <span class="badge text-bg-secondary rounded-pill px-3">CAT</span>
            </div>
            <h3 class="h5 mb-3">Processamento ETL</h3>
            <div class="display-6 mb-1"><?= htmlspecialchars((string) $stats['cat_files']) ?></div>
            <p class="small text-body-secondary mb-4">
                Arquivos de importação processados.<br>
                <i class="bi bi-database me-1"></i>
                <strong><?= htmlspecialchars(number_format($stats['cat_records'], 0, ',', '.')) ?></strong> registros brutos carregados.
            </p>
            <div class="mt-auto d-grid gap-2">
                <a href="../cat/etl.php" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-cogs me-2"></i>Processos ETL</a>
                <a href="../cat/campos.php" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-table-list me-2"></i>Mapeamento de Campos</a>
            </div>
        </div>
    </div>

    <!-- 5. LDRT -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 p-4">
            <div class="d-flex justify-content-between align-items-start">
                <div class="fs-3 text-body-secondary">
                    <i class="bi bi-virus"></i>
                </div>
                <span class="badge text-bg-secondary rounded-pill px-3">LDRT</span>
            </div>
            <h3 class="h5 mb-3">Lista de Doenças</h3>
            <div class="display-6 mb-1"><?= htmlspecialchars((string) $stats['ldrt_diseases']) ?></div>
            <p class="small text-body-secondary mb-4">
                CIDs catalogados na Lista de Doenças Relacionadas ao Trabalho.
            </p>
            <div class="mt-auto d-grid gap-2">
                <a href="../ldrt/rag.php" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-robot me-2"></i>Ajustar RAG (Inteligência Artificial)</a>
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>
