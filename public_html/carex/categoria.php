<?php

declare(strict_types=1);

use Carex\Database\Connection;
use Carex\Database\WorkRepository;
use Carex\Http\Security;

$config = require dirname(__DIR__, 2) . '/carex' . '/src/bootstrap.php';

\Carex\Http\Auth::requireLogin();

Security::applyHeaders();
Security::allowReadOnlyRequest();

$matrixId = trim((string) ($_GET['id_matriz'] ?? ''));
$code = trim((string) ($_GET['co_objeto'] ?? ''));
$type = trim((string) ($_GET['co_tp_objeto'] ?? ''));

$details = null;
$error = '';

try {
    if ($matrixId === '' || $code === '' || $type === '') {
        $error = 'Parâmetros inválidos. É necessário informar id_matriz, co_objeto e co_tp_objeto.';
    } else {
        $pdo = Connection::make($config['database']);
        $repository = new WorkRepository($pdo);
        $details = $repository->categoryDetails($matrixId, $code, $type);

        if (!$details) {
            $error = 'Categoria não encontrada ou não pertence a esta matriz.';
        }
    }
} catch (Throwable $exception) {
    $error = $config['app']['debug'] ? $exception->getMessage() : 'Não foi possível carregar os detalhes da categoria.';
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
    <title>CAREX | Detalhes da Categoria <?= Security::e($code) ?></title>
    <link href="../assets/favicon.png" rel="icon" type="image/png">
    <link href="<?= Security::e($bootstrapCss) ?>" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="assets/app.css" rel="stylesheet">
    <script src="../assets/js/theme-switcher.js"></script>
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php
    $activePage = 'matrizes';
    require dirname(__DIR__, 2) . '/carex' . '/src/templates/navbar.php';
    ?>

    <main class="container-fluid app-shell py-4">
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger py-2" role="alert"><?= Security::e($error) ?></div>
            <a href="matrizes.php" class="btn btn-primary btn-sm">Voltar para Matrizes</a>
        <?php else: ?>
            <?php
                $order = ['L1', 'L2', 'L3', 'L4', 'L5'];
                $typeMap = $details['origin'] === 'CBO' ? [
                    'L1' => 'cbo_gran_grup',
                    'L2' => 'cbo_subg_prin',
                    'L3' => 'cbo_subg',
                    'L4' => 'cbo_fami',
                    'L5' => 'cbo_ocup'
                ] : [
                    'L1' => 'cnae_seca',
                    'L2' => 'cnae_divi',
                    'L3' => 'cnae_grup',
                    'L4' => 'cnae_clas',
                    'L5' => 'cnae_subc'
                ];

                $hierarchyItems = [];
                $lastLabel = null;
                foreach ($order as $lvl) {
                    $itemLvl = $details['levels'][$lvl] ?? null;
                    if (!$itemLvl || $itemLvl['code'] === '' || $itemLvl['name'] === '-') {
                        continue;
                    }

                    $label = trim((string) $itemLvl['name']);
                    $isActive = $details['code'] === $itemLvl['code'];
                    if (!$isActive && $lastLabel !== null && $label === $lastLabel) {
                        continue;
                    }

                    $lastLabel = $label;
                    $hierarchyItems[] = [
                        'level' => $lvl,
                        'label' => $label,
                        'code' => (string) $itemLvl['code'],
                        'type' => $typeMap[$lvl],
                        'active' => $isActive,
                        'level_label' => (string) $itemLvl['level'],
                    ];
                }
            ?>
            <nav aria-label="Localização da categoria" class="category-breadcrumb-wrap mb-3">
                <ol class="breadcrumb category-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="matrizes.php" class="category-crumb category-crumb-home" title="Voltar para a lista de matrizes">
                            Matrizes
                        </a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="matriz.php?id_matriz=<?= Security::e($details['matrix_id']) ?>" class="category-crumb" title="<?= Security::e($details['matrix_name']) ?>">
                            <?= Security::e($details['matrix_name']) ?>
                        </a>
                    </li>
                    <?php foreach ($hierarchyItems as $item): ?>
                        <?php if ($item['active']): ?>
                            <li class="breadcrumb-item active" aria-current="page">
                                <span class="category-crumb category-crumb-active" title="<?= Security::e($item['level_label']) ?> <?= Security::e($item['code']) ?> - <?= Security::e($item['label']) ?>">
                                    <?= Security::e($item['label']) ?>
                                </span>
                            </li>
                        <?php else: ?>
                            <li class="breadcrumb-item">
                                <a href="categoria.php?id_matriz=<?= Security::e($details['matrix_id']) ?>&co_objeto=<?= Security::e($item['code']) ?>&co_tp_objeto=<?= Security::e($item['type']) ?>" class="category-crumb" title="<?= Security::e($item['level_label']) ?> <?= Security::e($item['code']) ?> - <?= Security::e($item['label']) ?>">
                                    <?= Security::e($item['label']) ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ol>
            </nav>

            <div class="row g-4 mb-4">
                <!-- Left column: Main info & hierarchy -->
                <div class="col-lg-7">
                    <!-- Category title card -->
                    <div class="card mb-4 border-secondary-subtle shadow-sm">
                        <div class="card-body p-4">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle mb-2"><?= Security::e($details['type_label']) ?></span>
                            <h1 class="h3 mb-2 text-body-emphasis"><?= Security::e($details['name']) ?></h1>
                            <div class="text-body-secondary small mb-3">
                                Código: <code class="fs-6 fw-bold"><?= Security::e($details['code']) ?></code> &middot; Origem: <strong><?= Security::e($details['origin']) ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Hierarchy detail timeline -->
                    <div class="card border-secondary-subtle shadow-sm mb-4">
                        <div class="card-header bg-body-tertiary py-3 border-secondary-subtle">
                            <h2 class="h5 mb-0 text-primary"><i class="bi bi-diagram-3-fill"></i> Detalhamento da Herança Hierárquica</h2>
                        </div>
                        <div class="card-body p-4">
                            <div class="timeline">
                                <?php 
                                $classBadgeMap = [
                                    'Exposto' => 'bg-danger-subtle text-danger border border-danger-subtle',
                                    'Não exposto' => 'bg-success-subtle text-success border border-success-subtle',
                                    'Condicionalmente exposto' => 'bg-warning-subtle text-warning border border-warning-subtle',
                                    'Revisar' => 'bg-info-subtle text-info border border-info-subtle',
                                    'Mista' => 'bg-info-subtle text-info border border-info-subtle',
                                    'Não classificado' => 'bg-light text-secondary border'
                                ];

                                $levels = ['L1', 'L2', 'L3', 'L4', 'L5'];
                                foreach ($levels as $lvl): 
                                    $itemLvl = $details['levels'][$lvl];
                                    if ($itemLvl['code'] === '') {
                                        continue;
                                    }
                                    $badgeClass = $classBadgeMap[$itemLvl['classification']] ?? 'bg-light text-secondary border';
                                    $isConsolidationOrigin = $details['co_nivel_classificacao_herdada'] === strtolower($lvl);
                                    $rowStyle = $isConsolidationOrigin ? 'border-primary bg-primary-subtle bg-opacity-25' : 'border-light-subtle';
                                ?>
                                    <div class="p-3 mb-3 border rounded <?= $rowStyle ?>">
                                        <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="fw-bold text-primary" style="font-size: 0.85rem;"><?= Security::e($itemLvl['level']) ?></span>
                                                <code class="small"><?= Security::e($itemLvl['code']) ?></code>
                                                <?php if ($isConsolidationOrigin): ?>
                                                    <span class="badge bg-primary text-white py-1.5 px-2" style="font-size: 0.65rem;">Origem da Decisão</span>
                                                 <?php endif; ?>
                                            </div>
                                            <span class="badge py-1.5 px-2.5 rounded <?= $badgeClass ?>"><?= Security::e($itemLvl['classification']) ?></span>
                                        </div>
                                        <div class="small text-body-secondary fw-semibold"><?= Security::e($itemLvl['name']) ?></div>

                                        <?php if (!empty($itemLvl['children'])): ?>
                                            <div class="mt-2 pt-2 border-top border-light-subtle">
                                                <button class="btn btn-link p-0 text-decoration-none small d-flex align-items-center gap-1" type="button" data-bs-toggle="collapse" data-bs-target="#children-<?= $lvl ?>" aria-expanded="false" aria-controls="children-<?= $lvl ?>" style="font-size: 0.8rem;">
                                                    <i class="bi bi-chevron-down"></i> Ver subcategorias filhas (<?= count($itemLvl['children']) ?>)
                                                </button>
                                                <div class="collapse mt-2" id="children-<?= $lvl ?>">
                                                    <div class="list-group list-group-flush border rounded-2" style="max-height: 250px; overflow-y: auto;">
                                                        <?php foreach ($itemLvl['children'] as $child): 
                                                            $childBadgeClass = $classBadgeMap[$child['classification']] ?? 'bg-light text-secondary border';
                                                            $isCurrentChild = ($details['code'] === $child['code']);
                                                            
                                                            $badgeClassAttr = ($child['classification'] === 'Não classificado') ? '' : $childBadgeClass;
                                                            $badgeStyleAttr = ($child['classification'] === 'Não classificado') ? 'background-color: var(--bs-body-secondary-bg); color: var(--bs-secondary-color); border: 1px solid var(--bs-border-color);' : '';
                                                            if ($child['classification'] === 'Condicionalmente exposto') {
                                                                $badgeStyleAttr .= ' max-width: 140px;';
                                                            }
                                                        ?>
                                                            <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 <?= $isCurrentChild ? 'bg-primary-subtle bg-opacity-25 fw-bold' : '' ?>">
                                                                <div class="d-flex flex-column gap-0.5" style="max-width: 70%;">
                                                                    <span class="small text-wrap">
                                                                        <code class="me-2"><?= Security::e($child['code']) ?></code>
                                                                        <?php if ($isCurrentChild): ?>
                                                                            <span class="text-body"><?= Security::e($child['name']) ?></span>
                                                                        <?php else: ?>
                                                                            <a href="categoria.php?id_matriz=<?= Security::e($details['matrix_id']) ?>&co_objeto=<?= Security::e($child['code']) ?>&co_tp_objeto=<?= Security::e($child['type']) ?>" class="text-primary text-decoration-none hover-underline"><?= Security::e($child['name']) ?></a>
                                                                        <?php endif; ?>
                                                                    </span>
                                                                </div>
                                                                <span class="badge py-1.5 px-2.5 text-wrap rounded <?= $badgeClassAttr ?>" style="font-size: 0.75rem; <?= $badgeStyleAttr ?>"><?= Security::e($child['classification']) ?></span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right column: Consolidated Status & RAIS series -->
                <div class="col-lg-5">
                    <!-- Consolidated Status Card -->
                    <div class="card border-light-subtle shadow-sm mb-4">
                        <div class="card-body p-4 text-center">
                            <div class="text-body-secondary small fw-semibold text-uppercase mb-2" style="letter-spacing: 0.5px;">Classificação Final Herdada</div>
                            <?php 
                            $finalClass = $details['classificacao_herdada'];
                            $finalBadge = $classBadgeMap[$finalClass] ?? 'bg-light text-secondary border';
                            ?>
                            <div class="d-inline-block badge fs-5 py-2 px-3 mb-3 <?= $finalBadge ?>">
                                <?= Security::e($finalClass) ?>
                            </div>
                            <div class="text-body-secondary small">
                                Nível da Herança: <strong><?= strtoupper(Security::e($details['co_nivel_classificacao_herdada'])) ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- RAIS Historical Series -->
                    <div class="card border-secondary-subtle shadow-sm">
                        <div class="card-header bg-body-tertiary py-3 border-secondary-subtle">
                            <h2 class="h5 mb-0 text-primary"><i class="bi bi-graph-up"></i> Série Histórica de Vínculos RAIS</h2>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($details['series'])): ?>
                                <p class="text-body-secondary p-4 mb-0">Nenhum vínculo RAIS registrado na série histórica para este código.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover align-middle mb-0 text-center">
                                        <thead class="table-light">
                                            <tr>
                                                <th scope="col" class="py-2">Ano</th>
                                                <th scope="col" class="py-2">Vínculos</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($details['series'] as $sRow): ?>
                                                <tr>
                                                    <td class="fw-semibold py-2.5 text-secondary"><?= Security::e($sRow['ano']) ?></td>
                                                    <td class="fw-bold text-body-emphasis py-2.5"><?= number_format((float)$sRow['vinculos'], 0, ',', '.') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-secondary-subtle shadow-sm mb-4">
                <div class="card-header bg-body-tertiary py-3 border-secondary-subtle d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h2 class="h5 mb-0 text-primary"><i class="bi bi-link-45deg"></i> Categorias relacionadas por JAC</h2>
                        <div class="small text-body-secondary mt-1">
                            <?= $details['origin'] === 'CBO' ? 'Categorias CNAE' : 'Categorias CBO' ?> no mesmo nível hierárquico, ordenadas pelo maior JAC.
                        </div>
                    </div>
                    <span class="badge text-bg-light border"><span id="relatedVisibleCount"><?= count($details['related_categories'] ?? []) ?></span> itens</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($details['related_categories'])): ?>
                        <p class="text-body-secondary p-4 mb-0">Nenhuma categoria relacionada encontrada na matriz JAC para este código.</p>
                    <?php else: ?>
                        <div class="related-category-controls p-3 border-bottom border-light-subtle">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-8 col-lg-6">
                                    <label for="relatedSearch" class="form-label small fw-semibold mb-1">Buscar nos rótulos</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                        <input type="search" class="form-control" id="relatedSearch" placeholder="Código ou categoria relacionada" autocomplete="off">
                                    </div>
                                </div>
                                <div class="col-6 col-md-2">
                                    <label for="relatedPageSize" class="form-label small fw-semibold mb-1">Itens por página</label>
                                    <select class="form-select form-select-sm" id="relatedPageSize">
                                        <option value="10">10</option>
                                        <option value="25" selected>25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                        <option value="all">Todos</option>
                                    </select>
                                </div>
                                <div class="col-6 col-md-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="relatedReset">
                                        <i class="bi bi-arrow-counterclockwise"></i> Limpar
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle mb-0 related-category-table">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col" class="py-2">
                                            <button type="button" class="related-sort-button" data-related-sort="code" aria-label="Ordenar por código">
                                                Código <i class="bi bi-arrow-down-up"></i>
                                            </button>
                                        </th>
                                        <th scope="col" class="py-2">
                                            <button type="button" class="related-sort-button" data-related-sort="name" aria-label="Ordenar por categoria relacionada">
                                                Categoria relacionada <i class="bi bi-arrow-down-up"></i>
                                            </button>
                                        </th>
                                        <th scope="col" class="py-2">
                                            <span class="related-header-help justify-content-start">
                                                <button type="button" class="related-sort-button" data-related-sort="classification" aria-label="Ordenar por classificação">
                                                    Classificação <i class="bi bi-arrow-down-up"></i>
                                                </button>
                                                <i class="bi bi-info-circle text-secondary" data-bs-toggle="tooltip" data-bs-placement="top" title="Classificação direta registrada para a categoria relacionada nesta matriz."></i>
                                            </span>
                                        </th>
                                        <th scope="col" class="py-2">
                                            <span class="related-header-help justify-content-start">
                                                <button type="button" class="related-sort-button" data-related-sort="inheritedClassification" aria-label="Ordenar por classificação herdada">
                                                    Classificação herdada <i class="bi bi-arrow-down-up"></i>
                                                </button>
                                                <i class="bi bi-info-circle text-secondary" data-bs-toggle="tooltip" data-bs-placement="top" title="Classificação consolidada herdada a partir da hierarquia da matriz. Em níveis agregados, pode aparecer como Mista quando os itens filhos têm classificações herdadas diferentes."></i>
                                            </span>
                                        </th>
                                        <th scope="col" class="py-2 text-end">
                                            <span class="related-header-help">
                                                <button type="button" class="related-sort-button justify-content-end" data-related-sort="jac" aria-label="Ordenar por JAC">
                                                    JAC <i class="bi bi-arrow-down-up"></i>
                                                </button>
                                                <i class="bi bi-info-circle text-secondary" data-bs-toggle="tooltip" data-bs-placement="top" title="Coeficiente de Jaccard do par CNAE/CBO."></i>
                                            </span>
                                        </th>
                                        <th scope="col" class="py-2 text-end">
                                            <span class="related-header-help">
                                                <button type="button" class="related-sort-button justify-content-end" data-related-sort="vinculos" aria-label="Ordenar por vínculos">
                                                    Vínculos <i class="bi bi-arrow-down-up"></i>
                                                </button>
                                                <i class="bi bi-info-circle text-secondary" data-bs-toggle="tooltip" data-bs-placement="top" title="Total de vínculos RAIS agregados nos pares que ligam a categoria atual à categoria relacionada."></i>
                                            </span>
                                        </th>
                                        <th scope="col" class="py-2 text-end">
                                            <span class="related-header-help">
                                                <button type="button" class="related-sort-button justify-content-end" data-related-sort="percentCurrent" aria-label="Ordenar por percentual atual">
                                                    % atual <i class="bi bi-arrow-down-up"></i>
                                                </button>
                                                <i class="bi bi-info-circle text-secondary" data-bs-toggle="tooltip" data-bs-placement="top" title="Percentual que o par representa do <?= Security::e($details['origin']) ?> atual (<?= $details['origin'] === 'CBO' ? 'nu_p_cbo' : 'nu_p_cnae' ?>)."></i>
                                            </span>
                                        </th>
                                        <th scope="col" class="py-2 text-end">
                                            <span class="related-header-help">
                                                <button type="button" class="related-sort-button justify-content-end" data-related-sort="percentRelated" aria-label="Ordenar por percentual relacionado">
                                                    % relacionada <i class="bi bi-arrow-down-up"></i>
                                                </button>
                                                <i class="bi bi-info-circle text-secondary" data-bs-toggle="tooltip" data-bs-placement="top" title="Percentual que o par representa do <?= $details['origin'] === 'CBO' ? 'CNAE' : 'CBO' ?> relacionado (<?= $details['origin'] === 'CBO' ? 'nu_p_cnae' : 'nu_p_cbo' ?>)."></i>
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="relatedCategoryBody">
                                    <?php foreach ($details['related_categories'] as $related): ?>
                                        <?php
                                            $relatedClassification = (string) ($related['classification'] ?? 'Não classificado');
                                            $relatedInheritedClassification = (string) ($related['inherited_classification'] ?? 'Não classificado');
                                            $relatedClassificationBadge = $classBadgeMap[$relatedClassification] ?? 'bg-light text-secondary border';
                                            $relatedInheritedBadge = $classBadgeMap[$relatedInheritedClassification] ?? 'bg-light text-secondary border';
                                            $relatedSearchText = trim($related['code'] . ' ' . ($related['name'] !== '' ? $related['name'] : 'Sem descrição') . ' ' . $relatedClassification . ' ' . $relatedInheritedClassification);
                                        ?>
                                        <tr
                                            data-related-row
                                            data-code="<?= Security::e($related['code']) ?>"
                                            data-name="<?= Security::e($related['name'] !== '' ? $related['name'] : 'Sem descrição') ?>"
                                            data-search="<?= Security::e($relatedSearchText) ?>"
                                            data-classification="<?= Security::e($relatedClassification) ?>"
                                            data-inherited-classification="<?= Security::e($relatedInheritedClassification) ?>"
                                            data-jac="<?= $related['jac'] === null ? '' : Security::e((string) $related['jac']) ?>"
                                            data-vinculos="<?= Security::e((string) $related['vinculos']) ?>"
                                            data-percent-current="<?= $related['percent_current'] === null ? '' : Security::e((string) $related['percent_current']) ?>"
                                            data-percent-related="<?= $related['percent_related'] === null ? '' : Security::e((string) $related['percent_related']) ?>"
                                        >
                                            <td class="fw-semibold font-monospace">
                                                <a href="categoria.php?id_matriz=<?= Security::e($details['matrix_id']) ?>&co_objeto=<?= Security::e($related['code']) ?>&co_tp_objeto=<?= Security::e($related['type']) ?>" class="text-decoration-none">
                                                    <?= Security::e($related['code']) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <a href="categoria.php?id_matriz=<?= Security::e($details['matrix_id']) ?>&co_objeto=<?= Security::e($related['code']) ?>&co_tp_objeto=<?= Security::e($related['type']) ?>" class="link-dark text-decoration-none fw-semibold">
                                                    <?= Security::e($related['name'] !== '' ? $related['name'] : 'Sem descrição') ?>
                                                </a>
                                                <?php if (($related['total_pares'] ?? 1) > 1): ?>
                                                    <div class="small text-body-secondary"><?= number_format((int) $related['total_pares'], 0, ',', '.') ?> pares agregados</div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge related-classification-badge <?= $relatedClassificationBadge ?>"><?= Security::e($relatedClassification) ?></span>
                                            </td>
                                            <td>
                                                <span class="badge related-classification-badge <?= $relatedInheritedBadge ?>"><?= Security::e($relatedInheritedClassification) ?></span>
                                            </td>
                                            <td class="text-end fw-bold"><?= $related['jac'] === null ? '-' : number_format((float) $related['jac'], 6, ',', '.') ?></td>
                                            <td class="text-end"><?= number_format((int) $related['vinculos'], 0, ',', '.') ?></td>
                                            <td class="text-end"><?= $related['percent_current'] === null ? '-' : number_format((float) $related['percent_current'] * 100, 2, ',', '.') . '%' ?></td>
                                            <td class="text-end"><?= $related['percent_related'] === null ? '-' : number_format((float) $related['percent_related'] * 100, 2, ',', '.') . '%' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-body-secondary p-4 mb-0 d-none" id="relatedEmptyMessage">Nenhuma categoria relacionada encontrada para esta busca.</p>
                        <div class="related-category-footer d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 border-top border-light-subtle">
                            <div id="relatedPaginationMeta" class="text-body-secondary small"></div>
                            <nav aria-label="Paginação de categorias relacionadas">
                                <ul id="relatedPaginationList" class="pagination pagination-sm mb-0"></ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script src="<?= Security::e($bootstrapJs) ?>" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        (() => {
            const body = document.getElementById('relatedCategoryBody');
            if (!body) {
                return;
            }

            const searchInput = document.getElementById('relatedSearch');
            const pageSizeSelect = document.getElementById('relatedPageSize');
            const resetButton = document.getElementById('relatedReset');
            const visibleCount = document.getElementById('relatedVisibleCount');
            const meta = document.getElementById('relatedPaginationMeta');
            const paginationList = document.getElementById('relatedPaginationList');
            const emptyMessage = document.getElementById('relatedEmptyMessage');
            const footer = document.querySelector('.related-category-footer');
            const sortButtons = Array.from(document.querySelectorAll('[data-related-sort]'));
            const allRows = Array.from(body.querySelectorAll('[data-related-row]'));
            const collator = new Intl.Collator('pt-BR', { numeric: true, sensitivity: 'base' });

            let sortKey = 'jac';
            let sortDirection = 'desc';
            let currentPage = 1;

            const normalize = (value) => (value || '')
                .toString()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase();

            const numericValue = (row, key) => {
                const raw = row.dataset[key] || '';
                if (raw === '') {
                    return Number.NEGATIVE_INFINITY;
                }

                return Number.parseFloat(raw);
            };

            const rowValue = (row, key) => {
                if (key === 'percentCurrent') {
                    return numericValue(row, 'percentCurrent');
                }
                if (key === 'percentRelated') {
                    return numericValue(row, 'percentRelated');
                }
                if (key === 'jac' || key === 'vinculos') {
                    return numericValue(row, key);
                }

                return row.dataset[key] || '';
            };

            const filteredRows = () => {
                const term = normalize(searchInput?.value || '');
                if (term === '') {
                    return [...allRows];
                }

                return allRows.filter((row) => normalize(row.dataset.search).includes(term));
            };

            const sortedRows = (rows) => rows.sort((a, b) => {
                const aValue = rowValue(a, sortKey);
                const bValue = rowValue(b, sortKey);
                const factor = sortDirection === 'asc' ? 1 : -1;

                if (typeof aValue === 'number' && typeof bValue === 'number') {
                    return (aValue - bValue) * factor;
                }

                return collator.compare(aValue, bValue) * factor;
            });

            const pageWindow = (page, totalPages) => {
                const pages = new Set([1, totalPages, page - 1, page, page + 1]);
                return Array.from(pages)
                    .filter((item) => item >= 1 && item <= totalPages)
                    .sort((a, b) => a - b);
            };

            const paginationItem = (label, page, disabled = false, active = false, title = '') => `
                <li class="page-item${disabled ? ' disabled' : ''}${active ? ' active' : ''}">
                    <button type="button" class="page-link" data-related-page="${page}"${disabled ? ' tabindex="-1" aria-disabled="true"' : ''}${active ? ' aria-current="page"' : ''}${title ? ` title="${title}"` : ''}>${label}</button>
                </li>
            `;

            const render = () => {
                const rows = sortedRows(filteredRows());
                const total = rows.length;
                const pageSizeRaw = pageSizeSelect?.value || '25';
                const pageSize = pageSizeRaw === 'all' ? Math.max(total, 1) : Number.parseInt(pageSizeRaw, 10);
                const totalPages = Math.max(1, Math.ceil(total / pageSize));
                currentPage = Math.min(Math.max(currentPage, 1), totalPages);
                const start = total === 0 ? 0 : (currentPage - 1) * pageSize;
                const end = pageSizeRaw === 'all' ? total : Math.min(start + pageSize, total);
                const visibleRows = new Set(rows.slice(start, end));

                allRows.forEach((row) => {
                    row.classList.toggle('d-none', !visibleRows.has(row));
                });
                rows.forEach((row) => body.appendChild(row));

                if (visibleCount) {
                    visibleCount.textContent = total.toLocaleString('pt-BR');
                }
                if (emptyMessage) {
                    emptyMessage.classList.toggle('d-none', total > 0);
                }
                if (footer) {
                    footer.classList.toggle('d-none', total === 0);
                }
                if (meta) {
                    meta.textContent = total === 0
                        ? 'Nenhum item'
                        : `Mostrando ${start + 1}-${end} de ${total.toLocaleString('pt-BR')} itens`;
                }

                sortButtons.forEach((button) => {
                    const icon = button.querySelector('.bi');
                    const active = button.dataset.relatedSort === sortKey;
                    button.classList.toggle('active', active);
                    if (icon) {
                        icon.className = active
                            ? `bi bi-sort-${sortDirection === 'asc' ? 'down' : 'up'}`
                            : 'bi bi-arrow-down-up';
                    }
                });

                if (!paginationList) {
                    return;
                }

                if (pageSizeRaw === 'all' || totalPages <= 1) {
                    paginationList.innerHTML = '';
                    return;
                }

                const items = [
                    paginationItem('&laquo;', 1, currentPage === 1, false, 'Primeira página'),
                    paginationItem('&lsaquo;', currentPage - 1, currentPage === 1, false, 'Página anterior'),
                ];
                let previous = 0;
                pageWindow(currentPage, totalPages).forEach((page) => {
                    if (previous > 0 && page - previous > 1) {
                        items.push('<li class="page-item disabled"><span class="page-link">...</span></li>');
                    }
                    items.push(paginationItem(String(page), page, false, page === currentPage));
                    previous = page;
                });
                items.push(
                    paginationItem('&rsaquo;', currentPage + 1, currentPage === totalPages, false, 'Próxima página'),
                    paginationItem('&raquo;', totalPages, currentPage === totalPages, false, 'Última página')
                );
                paginationList.innerHTML = items.join('');
            };

            searchInput?.addEventListener('input', () => {
                currentPage = 1;
                render();
            });
            pageSizeSelect?.addEventListener('change', () => {
                currentPage = 1;
                render();
            });
            resetButton?.addEventListener('click', () => {
                if (searchInput) {
                    searchInput.value = '';
                }
                if (pageSizeSelect) {
                    pageSizeSelect.value = '25';
                }
                sortKey = 'jac';
                sortDirection = 'desc';
                currentPage = 1;
                render();
            });
            sortButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const nextKey = button.dataset.relatedSort || 'jac';
                    if (sortKey === nextKey) {
                        sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
                    } else {
                        sortKey = nextKey;
                        sortDirection = ['code', 'name', 'classification', 'inheritedClassification'].includes(sortKey) ? 'asc' : 'desc';
                    }
                    currentPage = 1;
                    render();
                });
            });
            paginationList?.addEventListener('click', (event) => {
                const button = event.target.closest('[data-related-page]');
                if (!button || button.closest('.disabled')) {
                    return;
                }
                currentPage = Number.parseInt(button.dataset.relatedPage, 10) || 1;
                render();
            });

            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
                if (window.bootstrap?.Tooltip) {
                    new bootstrap.Tooltip(element);
                }
            });

            render();
        })();
    </script>
</body>
</html>
