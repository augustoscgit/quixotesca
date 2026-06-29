<?php

declare(strict_types=1);

use Carex\Database\Connection;
use Carex\Http\Security;

$config = require dirname(__DIR__, 2) . '/carex' . '/src/bootstrap.php';

\Carex\Http\Auth::requireLogin();

Security::applyHeaders();
Security::allowReadOnlyRequest();

$matrixId = trim((string) ($_GET['id_matriz'] ?? ''));
$matrix = null;
$error = '';

try {
    $pdo = Connection::make($config['database']);
    if ($matrixId !== '') {
        $stmt = $pdo->prepare(
            "select m.id_matriz,
                    m.no_matriz,
                    m.versao,
                    m.dt_criacao,
                    m.dt_revisao,
                    m.ds_agente_definicao,
                    m.ds_fonte_forca_trabalho,
                    m.ds_niveis_avaliados,
                    m.ds_especialistas,
                    m.ds_fontes_tecnicas,
                    m.ds_criterio_heranca,
                    m.co_criterio_consolidacao,
                    m.ds_categorias_condicionais,
                    m.ds_vinculos_discordantes,
                    m.ds_uso_probabilidades,
                    m.ds_escopo_estimativa,
                    m.ds_limitacoes,
                    count(mc.co_objeto) as total_itens,
                    count(mc.co_classificacao) filter (
                        where mc.co_classificacao is not null
                          and btrim(mc.co_classificacao) <> ''
                          and mc.co_classificacao <> '9'
                    ) as total_classificados,
                    count(distinct mc.co_tp_objeto) as tipos_objeto
               from tb_matriz m
               left join tb_matriz_classificacao mc on mc.id_matriz = m.id_matriz
              where m.id_matriz = :id_matriz
              group by m.id_matriz, m.no_matriz, m.versao, m.dt_criacao, m.dt_revisao, m.ds_agente_definicao, m.ds_fonte_forca_trabalho, m.ds_niveis_avaliados, m.ds_especialistas, m.ds_fontes_tecnicas, m.ds_criterio_heranca, m.co_criterio_consolidacao, m.ds_categorias_condicionais, m.ds_vinculos_discordantes, m.ds_uso_probabilidades, m.ds_escopo_estimativa, m.ds_limitacoes"
        );
        $stmt->execute(['id_matriz' => $matrixId]);
        $row = $stmt->fetch();
        if ($row) {
            $total = (int) $row['total_itens'];
            $classified = (int) $row['total_classificados'];
            $matrix = [
                'id_matriz' => (string) $row['id_matriz'],
                'no_matriz' => (string) $row['no_matriz'],
                'total_itens' => $total,
                'total_classificados' => $classified,
                'tipos_objeto' => (int) $row['tipos_objeto'],
                'percentual_classificado' => $total > 0 ? round(($classified / $total) * 100, 1) : 0.0,
                'versao' => (string) ($row['versao'] ?? '1.0'),
                'dt_criacao' => (string) ($row['dt_criacao'] ?? ''),
                'dt_revisao' => (string) ($row['dt_revisao'] ?? ''),
                'ds_agente_definicao' => (string) ($row['ds_agente_definicao'] ?? ''),
                'ds_fonte_forca_trabalho' => (string) ($row['ds_fonte_forca_trabalho'] ?? 'RAIS'),
                'ds_niveis_avaliados' => (string) ($row['ds_niveis_avaliados'] ?? ''),
                'ds_especialistas' => (string) ($row['ds_especialistas'] ?? ''),
                'ds_fontes_tecnicas' => (string) ($row['ds_fontes_tecnicas'] ?? ''),
                'ds_criterio_heranca' => (string) ($row['ds_criterio_heranca'] ?? ''),
                'co_criterio_consolidacao' => (string) ($row['co_criterio_consolidacao'] ?? ''),
                'ds_categorias_condicionais' => (string) ($row['ds_categorias_condicionais'] ?? ''),
                'ds_vinculos_discordantes' => (string) ($row['ds_vinculos_discordantes'] ?? ''),
                'ds_uso_probabilidades' => (string) ($row['ds_uso_probabilidades'] ?? ''),
                'ds_escopo_estimativa' => (string) ($row['ds_escopo_estimativa'] ?? ''),
                'ds_limitacoes' => (string) ($row['ds_limitacoes'] ?? ''),
            ];
            $linkedSpecialists = [];
            $stmtSpec = $pdo->prepare("
                SELECT e.id_especialista, e.no_especialista 
                  FROM carex.tb_especialista e
                  JOIN carex.tb_matriz_especialista me ON me.id_especialista = e.id_especialista
                 WHERE me.id_matriz = :id_matriz
                 ORDER BY e.no_especialista
            ");
            $stmtSpec->execute(['id_matriz' => $matrixId]);
            $linkedSpecialists = $stmtSpec->fetchAll();
        } else {
            $error = 'Matriz não encontrada.';
        }
    } else {
        $error = 'Informe a matriz.';
    }
} catch (Throwable $exception) {
    $error = $config['app']['debug'] ? $exception->getMessage() : 'Não foi possível carregar os detalhes da matriz.';
}

$bootstrapCss = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
$bootstrapJs = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
?>
<!doctype html>
<html lang="pt-BR" data-module="carex">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>CAREX | Matriz <?= Security::e($matrix ? $matrix['no_matriz'] : '') ?></title>
    <link href="../assets/favicon.png" rel="icon" type="image/png">
    <link href="<?= Security::e($bootstrapCss) ?>" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="assets/app.css" rel="stylesheet">
    <script src="../assets/js/theme-switcher.js"></script>
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php
    $activePage = 'trabalho';
    require dirname(__DIR__, 2) . '/carex' . '/src/templates/navbar.php';
    ?>

    <main class="container-fluid app-shell py-3">

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger py-2" role="alert"><?= Security::e($error) ?></div>
        <?php else: ?>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="matrizes.php">Matrizes</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= Security::e($matrix['no_matriz']) ?></li>
                </ol>
            </nav>

            <!-- Tabs Navigation -->
            <ul class="nav nav-tabs mb-4" id="matrixTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info-panel" type="button" role="tab" aria-controls="info-panel" aria-selected="true">
                        <i class="bi bi-info-circle-fill me-1"></i> Informações Gerais
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="classifications-tab" data-bs-toggle="tab" data-bs-target="#classifications-panel" type="button" role="tab" aria-controls="classifications-panel" aria-selected="false">
                        <i class="bi bi-table me-1"></i> Classificações
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="estimates-tab" data-bs-toggle="tab" data-bs-target="#estimates-panel" type="button" role="tab" aria-controls="estimates-panel" aria-selected="false">
                        <i class="bi bi-bar-chart-fill me-1"></i> Estimativas de vínculos
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="matrixTabContent">
                <!-- Tab 1: Informações Gerais -->
                <div class="tab-pane fade show active" id="info-panel" role="tabpanel" aria-labelledby="info-tab">
                    <div class="card mb-4 border-secondary-subtle shadow-sm">
                        <div class="card-body">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                                <div>
                                    <h1 class="h3 mb-1 text-primary"><?= Security::e($matrix['no_matriz']) ?></h1>
                                    <div class="text-body-secondary small">Código da Matriz: <strong><?= Security::e($matrix['id_matriz']) ?></strong></div>
                                </div>
                                <div class="d-flex gap-4">
                                    <div>
                                        <div class="text-body-secondary small">Itens da Matriz</div>
                                        <div class="fs-4 fw-bold text-body-emphasis"><?= number_format($matrix['total_itens'], 0, ',', '.') ?></div>
                                    </div>
                                    <div>
                                        <div class="text-body-secondary small">Classificados</div>
                                        <div class="fs-4 fw-bold text-success"><?= number_format($matrix['total_classificados'], 0, ',', '.') ?></div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="text-body-secondary fw-semibold">Avanço da Classificação</span>
                                    <span class="fw-bold text-primary"><?= number_format($matrix['percentual_classificado'], 1, ',', '.') ?>%</span>
                                </div>
                                <div class="progress" style="height: 10px;" role="progressbar" aria-valuenow="<?= Security::e($matrix['percentual_classificado']) ?>" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" style="width: <?= Security::e($matrix['percentual_classificado']) ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Linked Specialists Card -->
                    <div class="card border-secondary-subtle shadow-sm p-4 mb-4">
                        <h2 class="h5 mb-3 text-primary"><i class="bi bi-people-fill"></i> Especialistas Vinculados</h2>
                        <?php if (empty($linkedSpecialists)): ?>
                            <p class="text-body-secondary mb-0">Nenhum especialista vinculado a esta matriz no momento.</p>
                        <?php else: ?>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($linkedSpecialists as $spec): ?>
                                    <span class="badge bg-body-secondary text-body-emphasis border p-2 fs-6">
                                        <i class="bi bi-person-fill text-secondary me-1"></i>
                                        <?= Security::e($spec['no_especialista']) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Specifications & Metadata Panel -->
                    <div class="card border-secondary-subtle shadow-sm p-4 mb-4">
                        <h2 class="h5 mb-3 text-primary"><i class="bi bi-file-earmark-text-fill"></i> Especificações e Metadados da Matriz</h2>
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label class="small fw-semibold text-secondary d-block mb-1">Versão da Matriz</label>
                                <div class="fw-semibold text-body-emphasis"><?= Security::e($matrix['versao']) ?></div>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="small fw-semibold text-secondary d-block mb-1">Data de Criação</label>
                                <div class="text-body"><?= !empty($matrix['dt_criacao']) ? date('d/m/Y \à\s H:i', strtotime($matrix['dt_criacao'])) : '-' ?></div>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="small fw-semibold text-secondary d-block mb-1">Data de Revisão/Última Classificação</label>
                                <div class="text-body"><?= !empty($matrix['dt_revisao']) ? date('d/m/Y \à\s H:i', strtotime($matrix['dt_revisao'])) : '-' ?></div>
                            </div>
                            
                            <div class="col-12 col-md-4">
                                <label class="small fw-semibold text-secondary d-block mb-1">Força de Trabalho Utilizada</label>
                                <div class="text-body"><?= Security::e($matrix['ds_fonte_forca_trabalho']) ?></div>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="small fw-semibold text-secondary d-block mb-1">Níveis CBO e CNAE Avaliados</label>
                                <div class="text-body"><?= !empty($matrix['ds_niveis_avaliados']) ? Security::e($matrix['ds_niveis_avaliados']) : 'Disponíveis na base' ?></div>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="small fw-semibold text-secondary d-block mb-1">Critério de Consolidação</label>
                                <div class="text-body"><?= !empty($matrix['co_criterio_consolidacao']) ? 'Critério ' . Security::e($matrix['co_criterio_consolidacao']) : 'Não selecionado (Padrão/Em branco)' ?></div>
                            </div>

                            <div class="col-12">
                                <label class="small fw-semibold text-secondary d-block mb-1">Especialistas Participantes</label>
                                <div class="text-body"><?= !empty($matrix['ds_especialistas']) ? Security::e($matrix['ds_especialistas']) : 'Nenhum' ?></div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="small fw-semibold text-secondary d-block mb-1">Agente e Definição Operacional de Exposição</label>
                                <div class="text-body-secondary small"><?= !empty($matrix['ds_agente_definicao']) ? nl2br(Security::e($matrix['ds_agente_definicao'])) : 'Em branco' ?></div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="small fw-semibold text-secondary d-block mb-1">Fontes Técnicas Consultadas</label>
                                <div class="text-body-secondary small"><?= !empty($matrix['ds_fontes_tecnicas']) ? nl2br(Security::e($matrix['ds_fontes_tecnicas'])) : 'Em branco' ?></div>
                            </div>
                            
                            <div class="col-12 col-md-6">
                                <label class="small fw-semibold text-secondary d-block mb-1">Critério de Herança</label>
                                <div class="text-body-secondary small"><?= !empty($matrix['ds_criterio_heranca']) ? nl2br(Security::e($matrix['ds_criterio_heranca'])) : 'Em branco' ?></div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="small fw-semibold text-secondary d-block mb-1">Tratamento de Categorias Condicionais</label>
                                <div class="text-body-secondary small"><?= !empty($matrix['ds_categorias_condicionais']) ? nl2br(Security::e($matrix['ds_categorias_condicionais'])) : 'Em branco' ?></div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="small fw-semibold text-secondary d-block mb-1">Tratamento de Vínculos Discordantes</label>
                                <div class="text-body-secondary small"><?= !empty($matrix['ds_vinculos_discordantes']) ? nl2br(Security::e($matrix['ds_vinculos_discordantes'])) : 'Em branco' ?></div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="small fw-semibold text-secondary d-block mb-1">Uso de Probabilidades de Exposição</label>
                                <div class="text-body-secondary small"><?= !empty($matrix['ds_uso_probabilidades']) ? nl2br(Security::e($matrix['ds_uso_probabilidades'])) : 'Em branco' ?></div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="small fw-semibold text-secondary d-block mb-1">Escopo da Estimativa</label>
                                <div class="text-body-secondary small"><?= !empty($matrix['ds_escopo_estimativa']) ? nl2br(Security::e($matrix['ds_escopo_estimativa'])) : 'Em branco' ?></div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="small fw-semibold text-secondary d-block mb-1">Limitações Específicas</label>
                                <div class="text-body-secondary small"><?= !empty($matrix['ds_limitacoes']) ? nl2br(Security::e($matrix['ds_limitacoes'])) : 'Em branco' ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Classificações -->
                <div class="tab-pane fade" id="classifications-panel" role="tabpanel" aria-labelledby="classifications-tab">
                    <!-- Toolbar & Search -->
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <h2 class="h5 mb-0">Itens e Classificações</h2>
                        <form id="searchForm" class="d-flex gap-2" role="search">
                            <input id="searchInput" class="form-control form-control-sm search-input" style="min-width: 250px;" type="search" placeholder="Buscar código, nome ou classificação..." autocomplete="off">
                            <button class="btn btn-sm btn-primary" type="submit">Buscar</button>
                        </form>
                    </div>

                    <!-- Dynamic Filters -->
                    <div id="filterSection" class="card mb-3 border-light-subtle shadow-sm bg-body-tertiary">
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

                    <div id="statusMessage" class="alert alert-info py-2" role="status" hidden>Carregando classificações...</div>

                    <!-- Table -->
                    <div class="table-responsive border rounded-2 bg-white shadow-sm mb-3">
                        <table class="table table-striped table-hover align-middle mb-0" style="font-size: 0.9rem;">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" style="width: 80px;">Origem</th>
                                    <th scope="col" style="width: 130px;">Tipo de Objeto</th>
                                    <th scope="col" style="width: 100px;">Código</th>
                                    <th scope="col">Nome/Descrição do Objeto</th>
                                    <th scope="col" style="width: 160px;">Classificação direta</th>
                                    <th scope="col" style="width: 240px;">Classificação final</th>
                                    <th scope="col" style="width: 100px;">Probabilidade</th>
                                    <th scope="col">Observações</th>
                                </tr>
                            </thead>
                            <tbody id="dataBody">
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-body-secondary">
                                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                        Carregando dados da matriz...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 mb-5">
                        <div id="paginationMeta" class="text-body-secondary small"></div>
                        <nav aria-label="Paginação da matriz">
                            <ul id="paginationList" class="pagination pagination-sm mb-0"></ul>
                        </nav>
                    </div>
                </div>

                <!-- Tab 3: Estimativas de vínculos -->
                <div class="tab-pane fade" id="estimates-panel" role="tabpanel" aria-labelledby="estimates-tab">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <h2 class="h5 mb-0">Estimativas de vínculos por critério</h2>
                            <div class="text-body-secondary small">Media anual estimada de vinculos RAIS por classificacao final em cada criterio.</div>
                        </div>
                        <button id="refreshEstimatesBtn" class="btn btn-sm btn-outline-primary" type="button">
                            Atualizar leitura
                        </button>
                    </div>

                    <div id="estimatesStatus" class="alert alert-info py-2" role="status" hidden>Carregando estimativas...</div>
                    <div id="estimatesSummary" class="row g-3 mb-3"></div>

                    <div id="estimatesGrid" class="estimate-criteria-grid mb-5">
                        <div class="text-center py-4 text-body-secondary border rounded bg-white">Abra a aba para carregar as estimativas.</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script src="<?= Security::e($bootstrapJs) ?>" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        const state = {
            matrixId: <?= json_encode($matrixId) ?>,
            page: 1,
            perPage: 50,
            query: '',
            filters: []
        };
    </script>
    <script src="assets/matriz.js?v=<?= time() ?>"></script>
</body>
</html>
