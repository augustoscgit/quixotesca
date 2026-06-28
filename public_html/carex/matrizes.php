<?php

declare(strict_types=1);

use Carex\Database\Connection;
use Carex\Database\WorkRepository;
use Carex\Http\Security;

$config = require dirname(__DIR__, 2) . '/carex' . '/src/bootstrap.php';

\Carex\Http\Auth::requireLogin();

Security::applyHeaders();
Security::allowReadOnlyRequest();

$bootstrapCss = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
$bootstrapJs = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
$matrices = [];
$error = '';

try {
    $pdo = Connection::make($config['database']);
    $matrices = (new WorkRepository($pdo))->matrices();
} catch (Throwable $exception) {
    $error = $config['app']['debug'] ? $exception->getMessage() : 'Não foi possível carregar as matrizes.';
}
?>
<!doctype html>
<html lang="pt-BR" data-module="carex">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>CAREX | Matrizes</title>
    <link href="../assets/favicon.png" rel="icon" type="image/png">
    <link href="<?= Security::e($bootstrapCss) ?>" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="assets/app.css" rel="stylesheet">
    <style>
        .matrix-grid-card {
            background: var(--bs-body-bg);
            border: 1px solid var(--bs-border-color-translucent);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .matrix-grid-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        }
    </style>
    <script src="../../assets/js/theme-switcher.js"></script>
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php
    $activePage = 'matrizes';
    require dirname(__DIR__, 2) . '/carex' . '/src/templates/navbar.php';
    ?>

    <main class="container-fluid app-shell py-4">

        <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-4">
            <div>
                <h1 class="h3 mb-1 text-primary fw-bold">Painel de Matrizes</h1>
                <div class="text-body-secondary small"><?= count($matrices) ?> matrizes de exposição ativas no sistema</div>
            </div>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger py-2" role="alert"><?= Security::e($error) ?></div>
        <?php endif; ?>

        <!-- Responsive 3 Columns Grid -->
        <section class="row g-4" aria-label="Painel de Matrizes">
            <?php foreach ($matrices as $matrix): ?>
                <?php
                    $percent = (float) $matrix['percentual_classificado'];
                    $progress = max(0, min(100, $percent));
                    $allUrl = 'matriz.php?id_matriz=' . urlencode($matrix['id_matriz']);
                ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <article class="matrix-grid-card h-100 p-4 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                <div>
                                    <h2 class="h5 mb-1 text-body-emphasis fw-bold">
                                        <a href="<?= Security::e($allUrl) ?>" class="text-decoration-none text-body-emphasis hover-primary"><?= Security::e($matrix['no_matriz']) ?></a>
                                    </h2>
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace text-uppercase" style="font-size: 0.7rem;">Código: <?= Security::e($matrix['id_matriz']) ?></span>
                                    <span class="badge bg-primary-subtle text-primary border-primary-subtle font-monospace" style="font-size: 0.7rem;">Versão: <?= Security::e($matrix['versao']) ?></span>
                                </div>
                            </div>
                            
                            <!-- New metadata columns display -->
                            <div class="mb-4 small text-secondary">
                                <div class="mb-1 text-truncate" title="Especialistas: <?= Security::e($matrix['ds_especialistas']) ?>">
                                    <i class="bi bi-people me-1.5 text-primary"></i><strong>Especialistas:</strong> <?= !empty($matrix['ds_especialistas']) ? Security::e($matrix['ds_especialistas']) : 'Nenhum' ?>
                                </div>
                                <div class="mb-1">
                                    <i class="bi bi-database me-1.5 text-primary"></i><strong>Força de Trabalho:</strong> <?= Security::e($matrix['ds_fonte_forca_trabalho']) ?>
                                </div>
                            </div>

                            <div class="work-card-metrics mb-4 bg-body-secondary p-3 rounded-3 border">
                                <div class="text-center">
                                    <div class="text-body-secondary small fw-semibold">Itens</div>
                                    <div class="fs-5 fw-bold text-body-emphasis"><?= number_format((int) $matrix['total_itens'], 0, ',', '.') ?></div>
                                </div>
                                <div class="text-center">
                                    <div class="text-body-secondary small fw-semibold">Classificados</div>
                                    <div class="fs-5 fw-bold text-success"><?= number_format((int) $matrix['total_classificados'], 0, ',', '.') ?></div>
                                </div>
                                <div class="text-center">
                                    <div class="text-body-secondary small fw-semibold">Anos RAIS</div>
                                    <div class="fs-5 fw-bold text-body-emphasis"><?= number_format((int) $matrix['total_anos_rais'], 0, ',', '.') ?></div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="mt-2 mb-3">
                                <div class="d-flex justify-content-between small mb-1 fw-semibold text-secondary">
                                    <span>Avanço Geral</span>
                                    <span class="text-primary"><?= number_format($percent, 1, ',', '.') ?>%</span>
                                </div>
                                <div class="progress work-progress" style="height: 8px;" role="progressbar" aria-valuenow="<?= Security::e($progress) ?>" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar bg-success" style="width: <?= Security::e($progress) ?>%"></div>
                                </div>
                            </div>

                            <a class="btn btn-sm btn-primary w-100 py-2 d-flex align-items-center justify-content-center gap-1" href="<?= Security::e($allUrl) ?>">
                                Ver Matriz <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </section>
    </main>

    <script src="<?= Security::e($bootstrapJs) ?>" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
