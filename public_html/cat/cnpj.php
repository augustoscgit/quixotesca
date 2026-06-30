<?php
/**
 * CAT - Página de detalhe do CNPJ empregador
 */
require_once __DIR__ . '/../../cat/src/db.php';
require_once __DIR__ . '/../../cat/src/opencnpj.php';

function h(?string $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatCnpj(?string $value): string
{
    $digits = normalizeCnpjDigits((string)$value);
    if (strlen($digits) !== 14) {
        return $digits !== '' ? $digits : '-';
    }
    return substr($digits, 0, 2) . '.' . substr($digits, 2, 3) . '.' . substr($digits, 5, 3) . '/' . substr($digits, 8, 4) . '-' . substr($digits, 12, 2);
}

function openValue(?array $data, array $paths): ?string
{
    if (!$data) return null;
    foreach ($paths as $path) {
        $current = $data;
        foreach ($path as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                $current = null;
                break;
            }
            $current = $current[$key];
        }
        if (is_scalar($current) && trim((string)$current) !== '') {
            return mb_substr(trim((string)$current), 0, 500);
        }
    }
    return null;
}

function openList(?array $data, array $paths): array
{
    if (!$data) return [];
    foreach ($paths as $path) {
        $current = $data;
        foreach ($path as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                $current = null;
                break;
            }
            $current = $current[$key];
        }
        if (is_array($current)) {
            return array_values($current);
        }
    }
    return [];
}

$cnpj = normalizeCnpjDigits((string)($_GET['cnpj'] ?? ''));
$db_error = null;
$aggregate = null;
$cache = null;
$openCnpjRaw = null;
$recentAccidents = [];
$typeDistribution = [];
$sexDistribution = [];
$matrixBranches = [];
$catPage = max(1, (int)($_GET['cat_page'] ?? 1));
$catPerPage = (int)($_GET['per_page'] ?? 25);
if (!in_array($catPerPage, [10, 25, 50, 100], true)) {
    $catPerPage = 25;
}
$catTotal = 0;
$catTotalPages = 1;

try {
    if (!isValidCnpjDigits($cnpj)) {
        throw new InvalidArgumentException('CNPJ inválido ou ausente.');
    }

    $db = getDBConnection();

    $stmt = $db->prepare("SELECT * FROM cnpj_agregados WHERE cnpj_digits = :cnpj");
    $stmt->execute(['cnpj' => $cnpj]);
    $aggregate = $stmt->fetch() ?: null;

    $cacheRow = getOpenCnpjCache($db, $cnpj);
    $cache = openCnpjCacheRowToPayload($cacheRow);
    $openCnpjRaw = !empty($cacheRow['dados_json']) ? json_decode((string)$cacheRow['dados_json'], true) : null;

    $stmtCatTotal = $db->prepare("
        SELECT COUNT(*)
          FROM registros_brutos rb
         WHERE regexp_replace(COALESCE(rb.dados->>'cnpj_cei_empregador', ''), '\\D', '', 'g') = :cnpj
    ");
    $stmtCatTotal->execute(['cnpj' => $cnpj]);
    $catTotal = (int)$stmtCatTotal->fetchColumn();
    $catTotalPages = max(1, (int)ceil($catTotal / $catPerPage));
    $catPage = min($catPage, $catTotalPages);
    $catOffset = ($catPage - 1) * $catPerPage;

    $recentAccidents = [];

    $stmtType = $db->prepare("
        SELECT COALESCE(NULLIF(rb.dados->>'tipo_do_acidente', ''), 'Não informado') AS label,
               COUNT(*) AS total
          FROM registros_brutos rb
         WHERE regexp_replace(COALESCE(rb.dados->>'cnpj_cei_empregador', ''), '\\D', '', 'g') = :cnpj
         GROUP BY label
         ORDER BY total DESC
    ");
    $stmtType->execute(['cnpj' => $cnpj]);
    $typeDistribution = $stmtType->fetchAll();

    $stmtSex = $db->prepare("
        SELECT COALESCE(NULLIF(rb.dados->>'sexo', ''), 'Não informado') AS label,
               COUNT(*) AS total
          FROM registros_brutos rb
         WHERE regexp_replace(COALESCE(rb.dados->>'cnpj_cei_empregador', ''), '\\D', '', 'g') = :cnpj
         GROUP BY label
         ORDER BY total DESC
    ");
    $stmtSex->execute(['cnpj' => $cnpj]);
    $sexDistribution = $stmtSex->fetchAll();

    if ($aggregate) {
        $stmtBranches = $db->prepare("
            SELECT cnpj_digits, filial, acidentes, obitos, municipio_empregador, uf_empregador
              FROM cnpj_agregados
             WHERE matriz = :matriz
             ORDER BY acidentes DESC, cnpj_digits
             LIMIT 20
        ");
        $stmtBranches->execute(['matriz' => $aggregate['matriz']]);
        $matrixBranches = $stmtBranches->fetchAll();
    }
} catch (Throwable $e) {
    $db_error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-module="cat">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAT - CNPJ <?= h(formatCnpj($cnpj)) ?></title>
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
                    <div class="text-muted mb-1">CNPJ do empregador</div>
                    <h1 class="display-6 text-primary mb-2"><?= h(formatCnpj($cnpj)) ?></h1>
                    <p class="lead text-secondary mb-0" id="opencnpj-title"><?= h($cache['razao_social'] ?? $cache['nome_fantasia'] ?? 'Dados cadastrais ainda não carregados') ?></p>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-secondary btn-icon" href="cnpjs.php" title="Voltar ao agregador" aria-label="Voltar ao agregador"><i class="bi bi-arrow-left"></i></a>
                    <?php if (!empty($aggregate['matriz'])): ?>
                        <a class="btn btn-outline-primary btn-icon" href="matriz.php?matriz=<?= h($aggregate['matriz']) ?>" title="Abrir matriz" aria-label="Abrir matriz"><i class="bi bi-diagram-3"></i></a>
                    <?php endif; ?>
                    <a class="btn btn-outline-primary btn-icon" href="inspecao.php?cnpj=<?= h($cnpj) ?>" title="Navegar acidentes" aria-label="Navegar acidentes"><i class="bi bi-card-text"></i></a>
                    <button type="button" class="btn btn-outline-secondary btn-icon" onclick="refreshOpenCnpj()" title="Atualizar OpenCNPJ" aria-label="Atualizar OpenCNPJ"><i class="bi bi-cloud-arrow-down"></i></button>
                </div>
            </header>

            <section class="row g-4 mb-4">
                <div class="col-6 col-xl-3"><div class="card p-3"><div class="text-muted small">Acidentes</div><div class="metric-number"><?= number_format((int)($aggregate['acidentes'] ?? 0), 0, ',', '.') ?></div></div></div>
                <div class="col-6 col-xl-3"><div class="card p-3"><div class="text-muted small">Óbitos</div><div class="metric-number"><?= number_format((int)($aggregate['obitos'] ?? 0), 0, ',', '.') ?></div></div></div>
                <div class="col-6 col-xl-3"><div class="card p-3"><div class="text-muted small">Matriz</div><div class="metric-number font-monospace"><?php if (!empty($aggregate['matriz'])): ?><a class="text-primary text-decoration-none" href="matriz.php?matriz=<?= h($aggregate['matriz']) ?>"><?= h($aggregate['matriz']) ?></a><?php else: ?>-<?php endif; ?></div></div></div>
                <div class="col-6 col-xl-3"><div class="card p-3"><div class="text-muted small">Filial</div><div class="metric-number font-monospace"><?= h($aggregate['filial'] ?? '-') ?></div></div></div>
            </section>

            <?php
                $endereco = array_filter([
                    openValue($openCnpjRaw, [['logradouro'], ['endereco', 'logradouro'], ['estabelecimento', 'logradouro']]),
                    openValue($openCnpjRaw, [['numero'], ['endereco', 'numero'], ['estabelecimento', 'numero']]),
                    openValue($openCnpjRaw, [['complemento'], ['endereco', 'complemento'], ['estabelecimento', 'complemento']]),
                    openValue($openCnpjRaw, [['bairro'], ['endereco', 'bairro'], ['estabelecimento', 'bairro']]),
                    openValue($openCnpjRaw, [['cep'], ['endereco', 'cep'], ['estabelecimento', 'cep']]),
                ]);
                $atividadesSecundarias = openList($openCnpjRaw, [
                    ['atividades_secundarias'],
                    ['cnaes_secundarios'],
                    ['estabelecimento', 'atividades_secundarias'],
                    ['estabelecimento', 'cnaes_secundarios'],
                ]);
                $socios = openList($openCnpjRaw, [
                    ['socios'],
                    ['qsa'],
                    ['empresa', 'socios'],
                ]);
            ?>
            <section class="card p-4 mb-4">
                <h2 class="h5 mb-3 text-primary"><i class="bi bi-database me-2"></i>Cadastro completo OpenCNPJ</h2>
                <?php if (!$openCnpjRaw): ?>
                    <div class="text-muted small">Dados completos ainda nao estao no cache. Use o botao de atualizacao da API no canto superior direito.</div>
                <?php else: ?>
                    <div class="row g-3">
                        <div class="col-xl-6">
                            <div class="info-label">Endereco</div>
                            <div class="info-value"><?= h(implode(', ', $endereco) ?: '-') ?></div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="info-label">Natureza juridica</div>
                            <div class="info-value"><?= h(openValue($openCnpjRaw, [['natureza_juridica'], ['natureza_juridica', 'descricao'], ['empresa', 'natureza_juridica', 'descricao']]) ?? '-') ?></div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="info-label">Porte</div>
                            <div class="info-value"><?= h(openValue($openCnpjRaw, [['porte'], ['porte', 'descricao'], ['empresa', 'porte', 'descricao']]) ?? '-') ?></div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="info-label">Abertura</div>
                            <div class="info-value"><?= h(openValue($openCnpjRaw, [['data_abertura'], ['abertura'], ['estabelecimento', 'data_inicio_atividade']]) ?? '-') ?></div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="info-label">Capital social</div>
                            <div class="info-value"><?= h(openValue($openCnpjRaw, [['capital_social'], ['empresa', 'capital_social']]) ?? '-') ?></div>
                        </div>
                        <div class="col-12">
                            <div class="info-label">CNAEs secundarios</div>
                            <div class="info-value">
                                <?php if ($atividadesSecundarias): ?>
                                    <?php foreach ($atividadesSecundarias as $atividade): ?>
                                        <?php
                                            $codigo = is_array($atividade) ? ($atividade['codigo'] ?? $atividade['cnae'] ?? '') : '';
                                            $descricao = is_array($atividade) ? ($atividade['descricao'] ?? $atividade['nome'] ?? '') : (string)$atividade;
                                        ?>
                                        <div><span class="font-monospace"><?= h((string)$codigo) ?></span> <?= h((string)$descricao) ?></div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="info-label">Quadro societario</div>
                            <div class="info-value">
                                <?php if ($socios): ?>
                                    <?php foreach ($socios as $socio): ?>
                                        <?php
                                            $nome = is_array($socio) ? ($socio['nome'] ?? $socio['nome_socio'] ?? $socio['razao_social'] ?? '') : (string)$socio;
                                            $qualificacao = is_array($socio) ? ($socio['qualificacao'] ?? $socio['qualificacao_socio'] ?? '') : '';
                                        ?>
                                        <div><?= h((string)$nome) ?><?= $qualificacao ? ' - ' . h((string)$qualificacao) : '' ?></div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-12">
                            <details class="mt-2">
                                <summary class="small text-primary fw-semibold">JSON completo armazenado</summary>
                                <pre class="bg-body-tertiary border rounded p-3 mt-2 small overflow-auto"><?= h(json_encode($openCnpjRaw, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
                            </details>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <section class="row g-4 mb-4">
                <div class="col-xl-7">
                    <div class="card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="h5 mb-0 text-primary"><i class="bi bi-person-vcard me-2"></i>Cadastro OpenCNPJ</h2>
                            <span class="badge text-bg-secondary" id="opencnpj-status"><?= $cache ? h(($cache['is_fresh'] ?? false) ? 'cache válido' : 'cache expirado') : 'não consultado' ?></span>
                        </div>
                        <div class="row g-3">
                            <div class="col-12"><div class="info-label">Razão social</div><div class="info-value" id="val-razao-social"><?= h($cache['razao_social'] ?? '-') ?></div></div>
                            <div class="col-md-6"><div class="info-label">Nome fantasia</div><div class="info-value" id="val-nome-fantasia"><?= h($cache['nome_fantasia'] ?? '-') ?></div></div>
                            <div class="col-md-6"><div class="info-label">Situação</div><div class="info-value" id="val-situacao"><?= h($cache['situacao'] ?? '-') ?></div></div>
                            <div class="col-12"><div class="info-label">Atividade principal</div><div class="info-value" id="val-atividade-principal"><?= h($cache['atividade_principal'] ?? '-') ?></div></div>
                            <div class="col-md-6"><div class="info-label">Município</div><div class="info-value" id="val-municipio"><?= h($cache['municipio'] ?? '-') ?></div></div>
                            <div class="col-md-6"><div class="info-label">UF</div><div class="info-value" id="val-uf"><?= h($cache['uf'] ?? '-') ?></div></div>
                            <div class="col-12"><div class="info-label">Última consulta</div><div class="info-value" id="val-consultado-em"><?= h($cache['consultado_em'] ?? '-') ?></div></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-5">
                    <div class="card p-4 h-100">
                        <h2 class="h5 mb-3 text-primary"><i class="bi bi-building me-2"></i>Dados locais CAT</h2>
                        <div class="row g-3">
                            <div class="col-12"><div class="info-label">CNAE no arquivo CAT</div><div class="info-value"><?= h(trim(($aggregate['cnae_codigo'] ?? '') . ' - ' . ($aggregate['cnae_descricao'] ?? ''), ' -')) ?: '-' ?></div></div>
                            <div class="col-md-6"><div class="info-label">Município empregador</div><div class="info-value"><?= h($aggregate['municipio_empregador'] ?? '-') ?></div></div>
                            <div class="col-md-6"><div class="info-label">UF empregador</div><div class="info-value"><?= h($aggregate['uf_empregador'] ?? '-') ?></div></div>
                            <div class="col-md-6"><div class="info-label">Primeira ocorrência</div><div class="info-value"><?= h($aggregate['primeira_ocorrencia'] ?? '-') ?></div></div>
                            <div class="col-md-6"><div class="info-label">Última ocorrência</div><div class="info-value"><?= h($aggregate['ultima_ocorrencia'] ?? '-') ?></div></div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="row g-4 mb-4">
                <div class="col-xl-6">
                    <div class="card p-4 h-100">
                        <h2 class="h5 mb-3 text-primary">Distribuição por tipo de acidente</h2>
                        <?php foreach ($typeDistribution as $row): ?>
                            <div class="d-flex justify-content-between border-bottom border border-opacity-25 py-2"><span><?= h($row['label']) ?></span><strong><?= number_format((int)$row['total'], 0, ',', '.') ?></strong></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="card p-4 h-100">
                        <h2 class="h5 mb-3 text-primary">Distribuição por sexo</h2>
                        <?php foreach ($sexDistribution as $row): ?>
                            <div class="d-flex justify-content-between border-bottom border border-opacity-25 py-2"><span><?= h($row['label']) ?></span><strong><?= number_format((int)$row['total'], 0, ',', '.') ?></strong></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="card p-4 mb-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h2 class="h5 mb-0 text-primary"><i class="bi bi-list-ul me-2"></i>CATs registradas</h2>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="small text-muted" id="cat-range-summary">Carregando CATs...</span>
                        <span class="small text-muted">Linhas</span>
                        <select id="cat-per-page" class="form-select form-select-sm" title="Linhas por pagina" aria-label="Linhas por pagina">
                            <?php foreach ([10, 25, 50, 100] as $option): ?>
                                <option value="<?= $option ?>" <?= $option === $catPerPage ? 'selected' : '' ?>><?= $option ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Data</th><th>Tipo</th><th>Óbito</th><th>CID</th><th>CBO</th><th>ID</th><th>Origem</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($recentAccidents as $row): ?>
                                <tr>
                                    <td class="font-monospace"><?= h($row['data_acidente'] ?? '-') ?></td>
                                    <td><?= h($row['tipo_acidente'] ?? '-') ?></td>
                                    <td><?= h($row['obito'] ?? '-') ?></td>
                                    <td class="font-monospace"><?= h($row['cid'] ?? '-') ?></td>
                                    <td class="font-monospace"><?= h($row['cbo'] ?? '-') ?></td>
                                    <td class="font-monospace"><?= h($row['registro_origem_id'] ?? '-') ?></td>
                                    <td class="small text-muted"><?= h($row['arquivo_nome'] ?? '-') ?></td>
                                    <td class="text-end"><a class="btn btn-outline-primary btn-icon btn-sm" href="inspecao.php?cnpj=<?= h($cnpj) ?>&registro=<?= h($row['registro_origem_id'] ?? '') ?>" title="Abrir esta CAT" aria-label="Abrir esta CAT"><i class="bi bi-card-text"></i></a></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentAccidents)): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">Nenhuma CAT encontrada para este CNPJ.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($catTotalPages > 1): ?>
                    <?php
                        $pageWindowStart = max(1, $catPage - 2);
                        $pageWindowEnd = min($catTotalPages, $catPage + 2);
                    ?>
                    <nav class="d-flex justify-content-center mt-3" aria-label="Paginacao das CATs do CNPJ">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $catPage <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="cnpj.php?cnpj=<?= h($cnpj) ?>&cat_page=<?= max(1, $catPage - 1) ?>" title="Pagina anterior" aria-label="Pagina anterior"><i class="bi bi-chevron-left"></i></a>
                            </li>
                            <?php for ($page = $pageWindowStart; $page <= $pageWindowEnd; $page++): ?>
                                <li class="page-item <?= $page === $catPage ? 'active' : '' ?>">
                                    <a class="page-link" href="cnpj.php?cnpj=<?= h($cnpj) ?>&cat_page=<?= $page ?>"><?= number_format($page, 0, ',', '.') ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $catPage >= $catTotalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="cnpj.php?cnpj=<?= h($cnpj) ?>&cat_page=<?= min($catTotalPages, $catPage + 1) ?>" title="Proxima pagina" aria-label="Proxima pagina"><i class="bi bi-chevron-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </section>

            <?php if (count($matrixBranches) > 1): ?>
                <section class="card p-4">
                    <h2 class="h5 mb-3 text-primary"><i class="bi bi-diagram-3 me-2"></i>Filiais da mesma matriz</h2>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>CNPJ</th><th>Filial</th><th>Acidentes</th><th>Óbitos</th><th>Território</th></tr></thead>
                            <tbody>
                                <?php foreach ($matrixBranches as $branch): ?>
                                    <tr>
                                        <td><a class="text-primary font-monospace text-decoration-none fw-semibold" href="cnpj.php?cnpj=<?= h($branch['cnpj_digits']) ?>"><?= h(formatCnpj($branch['cnpj_digits'])) ?></a></td>
                                        <td class="font-monospace"><?= h($branch['filial']) ?></td>
                                        <td><?= number_format((int)$branch['acidentes'], 0, ',', '.') ?></td>
                                        <td><?= number_format((int)$branch['obitos'], 0, ',', '.') ?></td>
                                        <td><?= h(trim(($branch['municipio_empregador'] ?? '') . ' / ' . ($branch['uf_empregador'] ?? ''), ' /')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script>
        const cnpj = <?= json_encode($cnpj, JSON_UNESCAPED_UNICODE) ?>;
        const hasOpenCnpjCache = <?= $cache ? 'true' : 'false' ?>;
        let catCurrentPage = <?= (int)$catPage ?>;
        let catPerPage = <?= (int)$catPerPage ?>;

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function setText(id, value) {
            const el = document.getElementById(id);
            if (el) el.innerHTML = escapeHtml(value || '-');
        }

        function formatNumber(value) {
            return new Intl.NumberFormat('pt-BR').format(Number(value || 0));
        }

        function getCatTableElements() {
            const title = Array.from(document.querySelectorAll('h2')).find(el => el.textContent.includes('CATs registradas'));
            const section = title ? title.closest('section') : null;
            return {
                section,
                table: section?.querySelector('table') || null,
                thead: section?.querySelector('thead') || null,
                tbody: section?.querySelector('tbody') || null,
                pagination: section?.querySelector('.pagination') || null,
                summary: document.getElementById('cat-range-summary'),
                perPage: document.getElementById('cat-per-page'),
            };
        }

        function renderCatCode(code, label) {
            const safeCode = escapeHtml(code || '-');
            const safeLabel = escapeHtml(label || '');
            return `
                <div class="d-flex flex-column gap-1">
                    <span class="font-monospace">${safeCode}</span>
                    <span class="small text-muted">${safeLabel || '-'}</span>
                </div>
            `;
        }

        function renderCatPagination(data) {
            const { pagination } = getCatTableElements();
            if (!pagination) return;
            const totalPages = Number(data.total_pages || 1);
            const current = Number(data.page || 1);
            if (totalPages <= 1) {
                pagination.innerHTML = '';
                return;
            }
            const start = Math.max(1, current - 2);
            const end = Math.min(totalPages, current + 2);
            const items = [];
            const pageItem = (page, html, title, disabled = false, active = false) => `
                <li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
                    <a class="page-link" href="#" data-cat-page="${page}" title="${escapeHtml(title)}" aria-label="${escapeHtml(title)}">${html}</a>
                </li>
            `;
            items.push(pageItem(Math.max(1, current - 1), '<i class="bi bi-chevron-left"></i>', 'Pagina anterior', current <= 1));
            for (let page = start; page <= end; page++) {
                items.push(pageItem(page, formatNumber(page), `Pagina ${page}`, false, page === current));
            }
            items.push(pageItem(Math.min(totalPages, current + 1), '<i class="bi bi-chevron-right"></i>', 'Proxima pagina', current >= totalPages));
            pagination.innerHTML = items.join('');
            pagination.querySelectorAll('[data-cat-page]').forEach(link => {
                link.addEventListener('click', event => {
                    event.preventDefault();
                    if (link.closest('.page-item')?.classList.contains('disabled')) return;
                    loadCnpjCats(Number(link.dataset.catPage || 1));
                });
            });
        }

        async function loadCnpjCats(page = catCurrentPage) {
            const elements = getCatTableElements();
            if (!elements.tbody || !elements.thead) return;
            catPerPage = Number(elements.perPage?.value || catPerPage || 25);
            elements.thead.innerHTML = '<tr><th>Data</th><th>Tipo</th><th>Óbito</th><th>CID</th><th>CBO</th><th></th></tr>';
            elements.tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Carregando CATs...</td></tr>';
            if (elements.summary) elements.summary.textContent = 'Carregando CATs...';

            const params = new URLSearchParams({
                action: 'cnpj_cats',
                cnpj,
                page: String(page),
                per_page: String(catPerPage),
            });
            let data;
            try {
                const response = await fetch(`api_etl.php?${params.toString()}`, { headers: { 'Accept': 'application/json' } });
                data = await response.json();
            } catch (error) {
                elements.tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Nao foi possivel carregar as CATs.</td></tr>';
                if (elements.summary) elements.summary.textContent = 'Erro ao carregar CATs';
                return;
            }
            if (!data.success) {
                elements.tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Nao foi possivel carregar as CATs.</td></tr>';
                if (elements.summary) elements.summary.textContent = 'Erro ao carregar CATs';
                return;
            }
            catCurrentPage = Number(data.page || 1);
            catPerPage = Number(data.per_page || catPerPage);

            if (!data.rows || data.rows.length === 0) {
                elements.tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Nenhuma CAT encontrada para este CNPJ.</td></tr>';
            } else {
                elements.tbody.innerHTML = data.rows.map(row => `
                    <tr>
                        <td class="font-monospace">${escapeHtml(row.data_acidente || '-')}</td>
                        <td>${escapeHtml(row.tipo_acidente || '-')}</td>
                        <td>${escapeHtml(row.obito || '-')}</td>
                        <td>${renderCatCode(row.cid, row.cid_label)}</td>
                        <td>${renderCatCode(row.cbo, row.cbo_label)}</td>
                        <td class="text-end">
                            <a class="btn btn-outline-primary btn-icon btn-sm" href="inspecao.php?cnpj=${encodeURIComponent(cnpj)}&registro=${encodeURIComponent(row.registro_origem_id || '')}" title="Abrir esta CAT" aria-label="Abrir esta CAT">
                                <i class="bi bi-card-text"></i>
                            </a>
                        </td>
                    </tr>
                `).join('');
            }
            if (elements.summary) {
                elements.summary.textContent = `Exibindo ${formatNumber(data.from)} a ${formatNumber(data.to)} de ${formatNumber(data.total)}`;
            }
            renderCatPagination(data);
            const url = new URL(window.location.href);
            url.searchParams.set('cat_page', String(catCurrentPage));
            url.searchParams.set('per_page', String(catPerPage));
            window.history.replaceState({}, '', url.toString());
        }

        async function refreshOpenCnpj(force = true) {
            const status = document.getElementById('opencnpj-status');
            if (status) status.textContent = force ? 'atualizando...' : 'consultando API...';
            const response = await fetch('api_etl.php?action=fetch_opencnpj', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ cnpj, force, allow_stale: true }),
            });
            const data = await response.json();
            if (!data.success) {
                if (status) status.textContent = 'erro';
                return;
            }
            const item = data.item || {};
            setText('val-razao-social', item.razao_social);
            setText('val-nome-fantasia', item.nome_fantasia);
            setText('val-situacao', item.situacao);
            setText('val-atividade-principal', item.atividade_principal);
            setText('val-municipio', item.municipio);
            setText('val-uf', item.uf);
            setText('val-consultado-em', item.consultado_em);
            setText('opencnpj-title', item.razao_social || item.nome_fantasia || 'Dados cadastrais carregados');
            if (status) status.textContent = item.source === 'api' ? 'API atualizada' : 'cache';
            if (force) {
                window.setTimeout(() => window.location.reload(), 600);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (!hasOpenCnpjCache && cnpj) {
                refreshOpenCnpj(false);
            }
            const elements = getCatTableElements();
            if (elements.perPage) {
                elements.perPage.addEventListener('change', () => loadCnpjCats(1));
            }
            loadCnpjCats(catCurrentPage);
        });
    </script>
</body>
</html>
