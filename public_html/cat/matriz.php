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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root, [data-bs-theme="light"] {
            --bg-color: #f1f5f9;
            --card-bg: rgba(255, 255, 255, 0.7);
            --border-color: rgba(0, 0, 0, 0.08);
            --accent-color: var(--accent-ui);
            --accent-hover: var(--brand-cinza-4);
            --text-muted: #64748b;
            --text-color: #1e293b;
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.06);
            --navbar-bg: var(--bs-body-bg);
            --field-bg: #f8fafc;
        }
        [data-bs-theme="dark"] {
            --bg-color: #0b0f19;
            --card-bg: rgba(22, 28, 45, 0.7);
            --border-color: rgba(255, 255, 255, 0.08);
            --accent-color: var(--accent-ui);
            --accent-hover: var(--brand-cinza-4);
            --text-muted: #94a3b8;
            --text-color: #f8fafc;
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            --navbar-bg: var(--bs-body-bg);
            --field-bg: #111827;
        }
        body { background-color: var(--bg-color); color: var(--text-color); font-family: 'Inter', sans-serif; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Poppins', sans-serif; }
        .navbar { background-color: var(--navbar-bg); backdrop-filter: none; border-bottom: 1px solid var(--border-color); }
        .glass-card { background: var(--card-bg); backdrop-filter: none; border: 1px solid var(--border-color); border-radius: 8px; box-shadow: none; }
        .text-accent { color: var(--accent-color) !important; }
        .btn-icon { width: 40px; height: 40px; padding: 0 !important; display: inline-flex; align-items: center; justify-content: center; }
        .metric-number { font-size: 1.75rem; font-weight: 800; line-height: 1; }
        .table { --bs-table-bg: transparent; --bs-table-color: var(--text-color); --bs-table-border-color: var(--border-color); }
        .table thead th { color: var(--text-muted); font-size: .76rem; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }
        .text-clip { max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    </style>
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="../assets/js/theme-switcher.js"></script>
</head>
<body>
    <!-- Navbar -->
    <?php
    require_once __DIR__ . '/../../includes/navbar.php';
    render_platform_navbar('cat', 'cnpjs');
    ?>

    <main class="container-fluid py-5 px-4">
        <?php if ($db_error): ?>
            <div class="alert alert-danger glass-card border-danger"><?= h($db_error) ?></div>
        <?php else: ?>
            <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                <div>
                    <div class="text-muted mb-1">Raiz da matriz</div>
                    <h1 class="display-6 text-accent mb-2" style="font-weight: 800;"><?= h($matriz) ?></h1>
                    <p class="lead text-secondary mb-0">Resumo consolidado das CATs e filiais vinculadas a esta matriz.</p>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-secondary btn-icon" href="cnpjs.php" title="Voltar ao agregador" aria-label="Voltar ao agregador"><i class="fa-solid fa-arrow-left"></i></a>
                    <a class="btn btn-outline-accent btn-icon" href="inspecao.php?cnpj=<?= h($matriz) ?>" title="Navegar CATs da matriz" aria-label="Navegar CATs da matriz"><i class="fa-solid fa-address-card"></i></a>
                </div>
            </header>

            <section class="row g-4 mb-4">
                <div class="col-6 col-xl-3"><div class="glass-card p-3"><div class="text-muted small">Acidentes</div><div class="metric-number"><?= number_format((int)($summary['acidentes'] ?? 0), 0, ',', '.') ?></div></div></div>
                <div class="col-6 col-xl-3"><div class="glass-card p-3"><div class="text-muted small">Obitos</div><div class="metric-number"><?= number_format((int)($summary['obitos'] ?? 0), 0, ',', '.') ?></div></div></div>
                <div class="col-6 col-xl-3"><div class="glass-card p-3"><div class="text-muted small">Filiais</div><div class="metric-number"><?= number_format((int)($summary['total_filiais'] ?? 0), 0, ',', '.') ?></div></div></div>
                <div class="col-6 col-xl-3"><div class="glass-card p-3"><div class="text-muted small">Periodo</div><div class="fw-semibold"><?= h(trim(($summary['primeira_ocorrencia'] ?? '') . ' - ' . ($summary['ultima_ocorrencia'] ?? ''), ' -')) ?: '-' ?></div></div></div>
            </section>

            <section class="row g-4 mb-4">
                <div class="col-xl-6">
                    <div class="glass-card p-4 h-100">
                        <h2 class="h5 mb-3 text-accent">Tipos de acidente</h2>
                        <?php foreach ($topTypes as $row): ?>
                            <div class="d-flex justify-content-between border-bottom border-secondary border-opacity-25 py-2"><span><?= h($row['label']) ?></span><strong><?= number_format((int)$row['total'], 0, ',', '.') ?></strong></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="glass-card p-4 h-100">
                        <h2 class="h5 mb-3 text-accent">Territorios com CAT</h2>
                        <?php foreach ($topTerritories as $row): ?>
                            <div class="d-flex justify-content-between border-bottom border-secondary border-opacity-25 py-2"><span><?= h(trim(($row['municipio'] ?? '') . ' / ' . ($row['uf'] ?? ''), ' /')) ?></span><strong><?= number_format((int)$row['total'], 0, ',', '.') ?></strong></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="glass-card p-4">
                <h2 class="h5 mb-3 text-accent"><i class="fa-solid fa-code-branch me-2"></i>Filiais da matriz</h2>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>CNPJ</th><th>Empresa</th><th>Situacao</th><th>Acidentes</th><th>Obitos</th><th>Territorio</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($branches as $branch): ?>
                                <tr>
                                    <td><a class="text-accent font-monospace text-decoration-none fw-semibold" href="cnpj.php?cnpj=<?= h($branch['cnpj_digits']) ?>"><?= h(formatCnpjLocal($branch['cnpj_digits'])) ?></a></td>
                                    <td><div class="text-clip" title="<?= h($branch['razao_social'] ?: $branch['nome_fantasia'] ?: '-') ?>"><?= h($branch['razao_social'] ?: $branch['nome_fantasia'] ?: '-') ?></div></td>
                                    <td><?= h($branch['situacao'] ?? '-') ?></td>
                                    <td><?= number_format((int)$branch['acidentes'], 0, ',', '.') ?></td>
                                    <td><?= number_format((int)$branch['obitos'], 0, ',', '.') ?></td>
                                    <td><?= h(trim(($branch['municipio_empregador'] ?: $branch['opencnpj_municipio'] ?: '') . ' / ' . ($branch['uf_empregador'] ?: $branch['opencnpj_uf'] ?: ''), ' /')) ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-outline-accent btn-icon btn-sm" href="inspecao.php?cnpj=<?= h($branch['cnpj_digits']) ?>" title="Navegar CATs do CNPJ" aria-label="Navegar CATs do CNPJ"><i class="fa-solid fa-address-card"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
