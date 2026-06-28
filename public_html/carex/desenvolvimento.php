<?php

declare(strict_types=1);

use Carex\Http\Security;

$config = require dirname(__DIR__, 2) . '/carex' . '/src/bootstrap.php';

\Carex\Http\Auth::requireLogin();
if ((\Carex\Http\Auth::currentUser()['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Acesso restrito para administradores.');
}

Security::applyHeaders();
Security::allowReadOnlyRequest();

// Load settings
$settingsFile    = dirname(__DIR__, 2) . '/carex' . '/config/settings.json';
$appSettings     = json_decode(file_exists($settingsFile) ? file_get_contents($settingsFile) : '{}', true);
$devLoginVisible = $appSettings['dev_login_visible'] ?? true;
$currentClientId = $config['google']['client_id'] ?? '';
$isOAuthDummy    = str_starts_with($currentClientId, 'dummy') || $currentClientId === '';
$isEnvLocal      = ($config['app']['env'] ?? '') === 'local';

$bootstrapCss = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
$bootstrapJs = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
?>
<!doctype html>
<html lang="pt-BR" data-module="carex">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>CAREX | Desenvolvimento</title>
    <link href="../assets/favicon.png" rel="icon" type="image/png">
    <link href="<?= Security::e($bootstrapCss) ?>" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="assets/app.css" rel="stylesheet">
    <script src="../../assets/js/theme-switcher.js"></script>
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php
    $activePage = 'desenvolvimento';
    require dirname(__DIR__, 2) . '/carex' . '/src/templates/navbar.php';
    ?>

    <main class="container-fluid app-shell py-3">

        <ul class="nav nav-tabs mb-3" aria-label="Areas do modulo de desenvolvimento">
            <li class="nav-item">
                <button id="objectsTab" class="nav-link active" type="button" data-view="objects">Objetos da base</button>
            </li>
            <li class="nav-item">
                <button id="dataTab" class="nav-link" type="button" data-view="data">Dados</button>
            </li>
            <li class="nav-item">
                <button id="docsTab" class="nav-link" type="button" data-view="docs">Documentação</button>
            </li>
            <li class="nav-item ms-auto">
                <button id="ambienteTab" class="nav-link d-flex align-items-center gap-1" type="button" data-view="ambiente">
                    <?php if ($isOAuthDummy && $isEnvLocal): ?>
                        <span class="badge bg-warning rounded-circle p-1" style="width:8px;height:8px;display:inline-block;"></span>
                    <?php else: ?>
                        <span class="badge bg-success rounded-circle p-1" style="width:8px;height:8px;display:inline-block;"></span>
                    <?php endif; ?>
                    Ambiente
                </button>
            </li>
        </ul>

        <div class="row g-3">
            <aside id="dataSidebar" class="col-12 col-lg-3 col-xl-2" hidden>
                <div class="toolbar mb-2">
                    <input id="tableFilter" class="form-control form-control-sm" type="search" placeholder="Filtrar objetos" autocomplete="off">
                </div>
                <div id="tableList" class="list-group table-list" aria-label="Objetos consultaveis"></div>
            </aside>

            <section id="dataPanel" class="col-12 col-lg-9 col-xl-10" hidden>
                <ul id="dataTypeTabs" class="nav nav-tabs data-type-tabs mb-3" aria-label="Tipos de dados consultaveis"></ul>

                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div>
                        <h1 id="tableTitle" class="h4 mb-0">Tabelas</h1>
                        <div id="tableMeta" class="text-body-secondary small">Carregando metadados...</div>
                    </div>
                    <form id="searchForm" class="d-flex gap-2" role="search">
                        <input id="searchInput" class="form-control form-control-sm search-input" type="search" placeholder="Buscar" autocomplete="off">
                        <button class="btn btn-sm btn-primary" type="submit">Buscar</button>
                    </form>
                </div>

                <div id="filterSection" class="card mb-3 border-light-subtle shadow-sm bg-body-tertiary" hidden>
                    <div class="card-body py-2 px-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fw-semibold small text-uppercase text-body-secondary" style="letter-spacing: 0.5px;">Filtros Dinâmicos</span>
                            <button id="addFilterBtn" class="btn btn-xs btn-outline-primary py-1 px-2 small" style="font-size: 0.75rem;" type="button">
                                <strong>+ Adicionar Filtro</strong>
                            </button>
                        </div>
                        <div id="filterRowsContainer" class="d-flex flex-column gap-2">
                            <!-- Filters will be generated dynamically here -->
                        </div>
                    </div>
                </div>

                <div id="statusMessage" class="alert alert-info py-2" role="status">Selecione uma tabela para visualizar os dados.</div>

                <div class="table-responsive border rounded-2">
                    <table class="table table-sm table-striped table-hover align-middle mb-0">
                        <thead id="dataHead"></thead>
                        <tbody id="dataBody"></tbody>
                    </table>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                    <div id="paginationMeta" class="text-body-secondary small"></div>
                    <nav aria-label="Paginação">
                        <ul id="paginationList" class="pagination pagination-sm mb-0"></ul>
                    </nav>
                </div>
            </section>

            <section id="objectsPanel" class="col-12" hidden>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div>
                        <h1 class="h4 mb-0">Objetos da Base</h1>
                        <div id="objectsMeta" class="text-body-secondary small">Carregando inventário técnico...</div>
                    </div>
                    <button id="refreshObjects" class="btn btn-sm btn-outline-primary" type="button">Atualizar</button>
                </div>

                <ul id="objectTypeTabs" class="nav nav-tabs mb-3" aria-label="Tipos de objetos da base"></ul>
                
                <div class="mb-3" style="max-width: 350px;">
                    <input id="objectsFilter" class="form-control form-control-sm" type="search" placeholder="Buscar objetos da base..." autocomplete="off">
                </div>

                <div id="objectTypeContent"></div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                    <div id="objectPaginationMeta" class="text-body-secondary small"></div>
                    <nav aria-label="Paginação de objetos da base">
                        <ul id="objectPaginationList" class="pagination pagination-sm mb-0"></ul>
                    </nav>
                </div>
            </section>

            <section id="docsPanel" class="col-12" hidden>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div>
                        <h1 class="h4 mb-0">Documentação do Projeto</h1>
                        <div class="text-body-secondary small">Lista de arquivos markdown explicativos do ecossistema CAREX.</div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-12 col-md-5 col-lg-4">
                        <div class="list-group shadow-sm" id="docsList" aria-label="Lista de documentos">
                            <!-- Loaded dynamically via JS -->
                        </div>
                    </div>

                    <div class="col-12 col-md-7 col-lg-8">
                        <div class="card shadow-sm border-light-subtle">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-primary" id="docViewerTitle">Selecione um documento</span>
                            </div>
                            <div class="card-body" style="max-height: 70vh; overflow-y: auto;">
                                <div id="docViewerContent" class="text-body" style="font-size: 0.95rem; line-height: 1.6;">
                                    <p class="text-body-secondary mb-0">Selecione um dos documentos markdown ao lado para visualizar seu conteúdo diretamente no painel de desenvolvimento.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Painel: Ambiente -->
            <section id="ambientePanel" class="col-12" hidden>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                    <div>
                        <h1 class="h4 mb-0">Ambiente e Configuração</h1>
                        <div class="text-body-secondary small">Status do ambiente de execução e configurações de autenticação.</div>
                    </div>
                    <?php if ((\Carex\Http\Auth::currentUser()['role'] ?? '') === 'admin'): ?>
                    <a href="administrativo.php?tab=settings" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1">
                        <i class="bi bi-gear-fill"></i> Gerenciar Configurações
                    </a>
                    <?php endif; ?>
                </div>

                <div class="row g-3" style="max-width: 900px;">

                    <!-- Card: APP_ENV -->
                    <div class="col-12 col-md-6">
                        <div class="card h-100 border-<?= $isEnvLocal ? 'warning' : 'success' ?>-subtle shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <i class="bi bi-server fs-5 text-<?= $isEnvLocal ? 'warning' : 'success' ?>"></i>
                                    <span class="fw-semibold">Ambiente (APP_ENV)</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge fs-6 bg-<?= $isEnvLocal ? 'warning' : 'success' ?>-subtle text-<?= $isEnvLocal ? 'warning' : 'success' ?> border border-<?= $isEnvLocal ? 'warning' : 'success' ?>-subtle">
                                        <?= $isEnvLocal ? 'local' : 'production' ?>
                                    </span>
                                    <span class="text-body-secondary small"><?= $isEnvLocal ? 'Modo de desenvolvimento' : 'Modo de produção' ?></span>
                                </div>
                                <?php if ($isEnvLocal): ?>
                                <div class="mt-2 small text-warning-emphasis">
                                    <i class="bi bi-info-circle me-1"></i>Mude para <code>production</code> antes do deploy.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Card: Google OAuth -->
                    <div class="col-12 col-md-6">
                        <div class="card h-100 border-<?= $isOAuthDummy ? 'warning' : 'success' ?>-subtle shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <svg viewBox="0 0 24 24" width="20" height="20" xmlns="http://www.w3.org/2000/svg" class="flex-shrink-0">
                                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/>
                                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/>
                                    </svg>
                                    <span class="fw-semibold">Google OAuth</span>
                                </div>
                                <?php if ($isOAuthDummy): ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Credenciais demo (dummy)</span>
                                    <div class="mt-2 small text-body-secondary">
                                        <i class="bi bi-exclamation-triangle me-1"></i>Login com Google real não está ativo.
                                    </div>
                                    <?php if ((\Carex\Http\Auth::currentUser()['role'] ?? '') === 'admin'): ?>
                                    <a href="administrativo.php?tab=settings" class="btn btn-warning btn-sm mt-2">
                                        <i class="bi bi-key-fill me-1"></i> Configurar credenciais reais
                                    </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle-fill me-1"></i>Credenciais configuradas</span>
                                    <div class="mt-2 small text-body-secondary font-monospace text-truncate" title="<?= Security::e($currentClientId) ?>">
                                        <?= Security::e(substr($currentClientId, 0, 40)) ?>...
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Card: Modo Dev / Bypass Login -->
                    <?php if ($isEnvLocal): ?>
                    <div class="col-12 col-md-6">
                        <div class="card h-100 border-<?= $devLoginVisible ? 'warning' : 'secondary' ?>-subtle shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <i class="bi bi-code-slash fs-5 text-<?= $devLoginVisible ? 'warning' : 'secondary' ?>"></i>
                                    <span class="fw-semibold">Bypass de Login (Dev)</span>
                                </div>
                                <?php if ($devLoginVisible): ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Botões visíveis na tela de login</span>
                                    <div class="mt-2 small text-body-secondary">
                                        Qualquer visitante pode entrar como Admin, Especialista, Usuário cadastrado ou simular desligamento.
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border">Botões ocultados</span>
                                    <div class="mt-2 small text-body-secondary">
                                        A tela de login exibe apenas o botão oficial do Google.
                                    </div>
                                <?php endif; ?>
                                <?php if ((\Carex\Http\Auth::currentUser()['role'] ?? '') === 'admin'): ?>
                                <a href="administrativo.php?tab=settings" class="btn btn-sm btn-outline-secondary mt-2">
                                    <i class="bi bi-toggle-<?= $devLoginVisible ? 'on' : 'off' ?> me-1"></i> <?= $devLoginVisible ? 'Desativar' : 'Ativar' ?> Bypass
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Card: Guia de Migração -->
                    <div class="col-12 col-md-6">
                        <div class="card h-100 border-light-subtle shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <i class="bi bi-rocket-takeoff fs-5 text-primary"></i>
                                    <span class="fw-semibold">Guia de Migração para Produção</span>
                                </div>
                                <p class="text-body-secondary small mb-3">Checklist e instruções passo-a-passo para configurar o Google OAuth real e fazer o deploy em hospedagem PHP.</p>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="openMigrationDoc">
                                    <i class="bi bi-file-earmark-text me-1"></i> Ver documentação
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </section>
        </div>
    </main>

    <!-- Modal for Columns List -->
    <div class="modal fade" id="columnsModal" tabindex="-1" aria-labelledby="columnsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="columnsModalLabel">Colunas do Objeto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Coluna</th>
                                    <th>Tipo de Dado</th>
                                    <th>Nulavel</th>
                                    <th>Posicao</th>
                                </tr>
                            </thead>
                            <tbody id="columnsModalBody">
                                <!-- Loaded dynamically via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= Security::e($bootstrapJs) ?>" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="assets/app.js?v=<?= time() ?>"></script>
</body>
</html>
