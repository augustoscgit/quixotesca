<?php
/**
 * CAT - Pagina de detalhe da matriz de CNPJ
 */
require_once __DIR__ . '/../../cat/src/db.php';
require_once __DIR__ . '/../../cat/src/opencnpj.php';

function h(?string $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatCnpjLocal(?string $value): string
{
    $digits = normalizeCnpjDigits((string)$value);
    if (strlen($digits) !== 14) {
        return $digits !== '' ? $digits : '-';
    }
    return substr($digits, 0, 2) . '.' . substr($digits, 2, 3) . '.' . substr($digits, 5, 3) . '/' . substr($digits, 8, 4) . '-' . substr($digits, 12, 2);
}

$matriz = preg_replace('/\D+/', '', (string)($_GET['matriz'] ?? ''));
$db_error = null;
$summary = null;
$branches = [];
$topTypes = [];
$topTerritories = [];

try {
    if (strlen($matriz) !== 8) {
        throw new InvalidArgumentException('Matriz invalida ou ausente.');
    }

    $db = getDBConnection();

    $stmtSummary = $db->prepare("
        SELECT matriz,
               COUNT(*) AS total_filiais,
               SUM(acidentes) AS acidentes,
               SUM(obitos) AS obitos,
               MIN(primeira_ocorrencia) AS primeira_ocorrencia,
               MAX(ultima_ocorrencia) AS ultima_ocorrencia
          FROM cnpj_agregados
         WHERE matriz = :matriz
         GROUP BY matriz
    ");
    $stmtSummary->execute(['matriz' => $matriz]);
    $summary = $stmtSummary->fetch() ?: null;

    $stmtBranches = $db->prepare("
        SELECT ca.*,
               co.razao_social,
               co.nome_fantasia,
               co.situacao,
               co.municipio AS opencnpj_municipio,
               co.uf AS opencnpj_uf
          FROM cnpj_agregados ca
          LEFT JOIN cnpj_cache_opencnpj co
            ON co.cnpj_digits = ca.cnpj_digits
           AND co.dataset = 'receita'
         WHERE ca.matriz = :matriz
         ORDER BY ca.acidentes DESC, ca.obitos DESC, ca.cnpj_digits
    ");
    $stmtBranches->execute(['matriz' => $matriz]);
    $branches = $stmtBranches->fetchAll();

    $stmtTypes = $db->prepare("
        SELECT COALESCE(NULLIF(rb.dados->>'tipo_do_acidente', ''), 'Nao informado') AS label,
               COUNT(*) AS total
          FROM registros_brutos rb
         WHERE regexp_replace(COALESCE(rb.dados->>'cnpj_cei_empregador', ''), '\\D', '', 'g') LIKE :matriz
         GROUP BY label
         ORDER BY total DESC
         LIMIT 8
    ");
    $stmtTypes->execute(['matriz' => $matriz . '%']);
    $topTypes = $stmtTypes->fetchAll();

    $stmtTerritories = $db->prepare("
        SELECT COALESCE(NULLIF(ca.municipio_empregador, ''), 'Nao informado') AS municipio,
               COALESCE(NULLIF(ca.uf_empregador, ''), '-') AS uf,
               SUM(ca.acidentes) AS total
          FROM cnpj_agregados ca
         WHERE ca.matriz = :matriz
         GROUP BY municipio, uf
         ORDER BY total DESC
         LIMIT 8
    ");
    $stmtTerritories->execute(['matriz' => $matriz]);
    $topTerritories = $stmtTerritories->fetchAll();
} catch (Throwable $e) {
    $db_error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-module="cat">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAT - Matriz <?= h($matriz) ?></title>
    <link rel="icon" type="image/png" href="../assets/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="../assets/js/theme-switcher.js?v=20260629-vanilla"></script>
    <link href="../assets/css/style.css?v=20260629-vanilla" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <?php
    require_once __DIR__ . '/../../includes/navbar.php';
    render_platform_navbar('cat', 'cnpjs');
    ?>

    <main class="container-fluid py-5 px-4">
        <?php if ($db_error): ?>
            <div class="alert alert-danger card border-danger"><?= h($db_error) ?></div>
        <?php else: ?>
            <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                <div>
                    <div class="text-muted mb-1">Raiz da matriz</div>
                    <h1 class="display-6 text-primary mb-2"><?= h($matriz) ?></h1>
                    <p class="lead text-secondary mb-0">Resumo consolidado das CATs e filiais vinculadas a esta matriz.</p>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-secondary btn-icon" href="cnpjs.php" title="Voltar ao agregador" aria-label="Voltar ao agregador"><i class="bi bi-arrow-left"></i></a>
                    <a class="btn btn-outline-primary btn-icon" href="inspecao.php?cnpj=<?= h($matriz) ?>" title="Navegar CATs da matriz" aria-label="Navegar CATs da matriz"><i class="bi bi-card-text"></i></a>
                </div>
            </header>

            <section class="row g-4 mb-4">
                <div class="col-6 col-xl-3"><div class="card p-3"><div class="text-muted small">Acidentes</div><div class="metric-number"><?= number_format((int)($summary['acidentes'] ?? 0), 0, ',', '.') ?></div></div></div>
                <div class="col-6 col-xl-3"><div class="card p-3"><div class="text-muted small">Obitos</div><div class="metric-number"><?= number_format((int)($summary['obitos'] ?? 0), 0, ',', '.') ?></div></div></div>
                <div class="col-6 col-xl-3"><div class="card p-3"><div class="text-muted small">Filiais</div><div class="metric-number"><?= number_format((int)($summary['total_filiais'] ?? 0), 0, ',', '.') ?></div></div></div>
                <div class="col-6 col-xl-3"><div class="card p-3"><div class="text-muted small">Periodo</div><div class="fw-semibold"><?= h(trim(($summary['primeira_ocorrencia'] ?? '') . ' - ' . ($summary['ultima_ocorrencia'] ?? ''), ' -')) ?: '-' ?></div></div></div>
            </section>

            <section class="row g-4 mb-4">
                <div class="col-xl-6">
                    <div class="card p-4 h-100">
                        <h2 class="h5 mb-3 text-primary">Tipos de acidente</h2>
                        <?php foreach ($topTypes as $row): ?>
                            <div class="d-flex justify-content-between border-bottom border border-opacity-25 py-2"><span><?= h($row['label']) ?></span><strong><?= number_format((int)$row['total'], 0, ',', '.') ?></strong></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="card p-4 h-100">
                        <h2 class="h5 mb-3 text-primary">Territorios com CAT</h2>
                        <?php foreach ($topTerritories as $row): ?>
                            <div class="d-flex justify-content-between border-bottom border border-opacity-25 py-2"><span><?= h(trim(($row['municipio'] ?? '') . ' / ' . ($row['uf'] ?? ''), ' /')) ?></span><strong><?= number_format((int)$row['total'], 0, ',', '.') ?></strong></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="card p-4">
                <h2 class="h5 mb-3 text-primary"><i class="bi bi-diagram-3 me-2"></i>Filiais da matriz</h2>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>CNPJ</th><th>Empresa</th><th>Situacao</th><th>Acidentes</th><th>Obitos</th><th>Territorio</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($branches as $branch): ?>
                                <tr>
                                    <td><a class="text-primary font-monospace text-decoration-none fw-semibold" href="cnpj.php?cnpj=<?= h($branch['cnpj_digits']) ?>"><?= h(formatCnpjLocal($branch['cnpj_digits'])) ?></a></td>
                                    <td><div class="text-clip" title="<?= h($branch['razao_social'] ?: $branch['nome_fantasia'] ?: '-') ?>"><?= h($branch['razao_social'] ?: $branch['nome_fantasia'] ?: '-') ?></div></td>
                                    <td><?= h($branch['situacao'] ?? '-') ?></td>
                                    <td><?= number_format((int)$branch['acidentes'], 0, ',', '.') ?></td>
                                    <td><?= number_format((int)$branch['obitos'], 0, ',', '.') ?></td>
                                    <td><?= h(trim(($branch['municipio_empregador'] ?: $branch['opencnpj_municipio'] ?: '') . ' / ' . ($branch['uf_empregador'] ?: $branch['opencnpj_uf'] ?: ''), ' /')) ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-outline-primary btn-icon btn-sm" href="inspecao.php?cnpj=<?= h($branch['cnpj_digits']) ?>" title="Navegar CATs do CNPJ" aria-label="Navegar CATs do CNPJ"><i class="bi bi-card-text"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>
