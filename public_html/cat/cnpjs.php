<?php
/**
 * CAT - Agregador por CNPJ, matriz e filial
 */
require_once __DIR__ . '/../../cat/src/db.php';

$db_error = null;
$total_cnpjs = 0;
$total_matrizes = 0;
$total_acidentes_cnpj = 0;
$total_obitos_cnpj = 0;

try {
    $db = getDBConnection();
    $aggregateRows = (int)$db->query("SELECT COUNT(*) FROM cnpj_agregados")->fetchColumn();
    $rawRows = (int)$db->query("SELECT COUNT(*) FROM registros_brutos")->fetchColumn();
    if ($aggregateRows === 0 && $rawRows > 0) {
        refreshCnpjAggregates($db);
    }
    $stats = $db->query("
        SELECT COUNT(*) AS total_cnpjs,
               COUNT(DISTINCT matriz) AS total_matrizes,
               COALESCE(SUM(acidentes), 0) AS total_acidentes,
               COALESCE(SUM(obitos), 0) AS total_obitos
          FROM cnpj_agregados
    ")->fetch();
    $total_cnpjs = (int)($stats['total_cnpjs'] ?? 0);
    $total_matrizes = (int)($stats['total_matrizes'] ?? 0);
    $total_acidentes_cnpj = (int)($stats['total_acidentes'] ?? 0);
    $total_obitos_cnpj = (int)($stats['total_obitos'] ?? 0);
} catch (Exception $e) {
    $db_error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-module="cat">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAT - Agregador por CNPJ</title>
    <link rel="icon" type="image/png" href="../favicon.png">
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
            --accent-color: #464B51;
            --accent-hover: #35383d;
            --text-muted: #64748b;
            --text-color: #1e293b;
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.06);
            --navbar-bg: rgba(241, 245, 249, 0.85);
            --field-bg: #f8fafc;
            --skeleton-base: #e2e8f0;
            --skeleton-wave: #f8fafc;
        }
        [data-bs-theme="dark"] {
            --bg-color: #0b0f19;
            --card-bg: rgba(22, 28, 45, 0.7);
            --border-color: rgba(255, 255, 255, 0.08);
            --accent-color: #464B51;
            --accent-hover: #575d64;
            --text-muted: #94a3b8;
            --text-color: #f8fafc;
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            --navbar-bg: rgba(11, 15, 25, 0.85);
            --field-bg: #111827;
            --skeleton-base: #1f2937;
            --skeleton-wave: #374151;
        }
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Inter', sans-serif;
        }
        h1, h2, h3, h4, h5, h6 { font-family: 'Poppins', sans-serif; }
        .navbar {
            background-color: var(--navbar-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
        }
        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: var(--glass-shadow);
        }
        .form-control,
        .form-select {
            background-color: var(--field-bg) !important;
            color: var(--text-color) !important;
            border-color: var(--border-color) !important;
        }
        .form-control::placeholder {
            color: var(--text-muted);
            opacity: 0.78;
        }
        .form-control:focus,
        .form-select:focus {
            background-color: var(--field-bg) !important;
            color: var(--text-color) !important;
            border-color: var(--accent-color) !important;
            box-shadow: 0 0 0 0.2rem rgba(168, 85, 247, 0.18);
        }
        .text-purple { color: var(--accent-color) !important; }
        .btn-purple {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            color: #fff;
            font-weight: 500;
        }
        .btn-purple:hover,
        .btn-purple:focus {
            background-color: var(--accent-hover);
            border-color: var(--accent-hover);
            color: #fff;
        }
        .btn-outline-purple {
            border-color: var(--accent-color);
            color: var(--accent-color);
            font-weight: 500;
        }
        .btn-outline-purple:hover,
        .btn-outline-purple:focus {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            color: #fff;
        }
        .btn-icon {
            width: 40px;
            height: 40px;
            padding: 0 !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
        }
        .table {
            --bs-table-bg: transparent;
            --bs-table-color: var(--text-color);
            --bs-table-border-color: var(--border-color);
        }
        .table thead th {
            color: var(--text-muted);
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid var(--border-color);
        }
        .cnpj-link {
            color: var(--text-color);
            text-decoration: none;
            font-weight: 700;
        }
        .cnpj-link:hover { color: var(--accent-color); }
        .sort-button {
            border: 0;
            background: transparent;
            color: inherit;
            font: inherit;
            padding: 0;
            text-transform: inherit;
            letter-spacing: inherit;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .sort-button:hover { color: var(--accent-color); }
        .actions-inline {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.35rem;
            white-space: nowrap;
            flex-wrap: nowrap;
        }
        .text-clip {
            display: inline-block;
            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            vertical-align: bottom;
        }
        .skeleton-line {
            display: block;
            min-height: 1rem;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--skeleton-base), var(--skeleton-wave), var(--skeleton-base));
            background-size: 200% 100%;
            animation: skeleton-wave 1.1s ease-in-out infinite;
        }
        @keyframes skeleton-wave {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
    <script src="../assets/js/theme-switcher.js"></script>
</head>
<body>
    <!-- Navbar -->
    <?php
    require_once __DIR__ . '/../includes/navbar.php';
    render_platform_navbar('cat', 'cnpjs');
    ?>

    <main class="container-fluid py-5 px-4">
        <header class="mb-4">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <h1 class="display-6 text-purple mb-2" style="font-weight: 800;">Agregador por CNPJ</h1>
                    <p class="lead text-secondary mb-0">Resumo de acidentes por CNPJ do empregador, raiz da matriz e código da filial.</p>
                </div>
            </div>
        </header>

        <?php if ($db_error): ?>
            <div class="alert alert-danger glass-card border-danger"><?= htmlspecialchars($db_error) ?></div>
        <?php endif; ?>

        <section class="row g-4 mb-4">
            <div class="col-6 col-xl-3">
                <div class="glass-card p-3">
                    <div class="text-muted small">CNPJs com CAT</div>
                    <div class="h4 mb-0"><?= number_format($total_cnpjs, 0, ',', '.') ?></div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="glass-card p-3">
                    <div class="text-muted small">Matrizes distintas</div>
                    <div class="h4 mb-0"><?= number_format($total_matrizes, 0, ',', '.') ?></div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="glass-card p-3">
                    <div class="text-muted small">Acidentes com CNPJ válido</div>
                    <div class="h4 mb-0"><?= number_format($total_acidentes_cnpj, 0, ',', '.') ?></div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="glass-card p-3">
                    <div class="text-muted small">Óbitos</div>
                    <div class="h4 mb-0"><?= number_format($total_obitos_cnpj, 0, ',', '.') ?></div>
                </div>
            </div>
        </section>

        <section class="glass-card p-3 mb-4">
            <form class="row g-3 align-items-end" onsubmit="event.preventDefault(); applySearch();">
                <div class="col-12 col-lg-5">
                    <label for="cnpj-search" class="form-label text-muted small">Busca livre</label>
                    <input id="cnpj-search" class="form-control" autocomplete="off" placeholder="CNPJ, matriz, filial, CNAE, territorio ou empresa">
                </div>
                <div class="col-6 col-lg-2">
                    <label for="filter-estado" class="form-label text-muted small">Estado/UF</label>
                    <input id="filter-estado" class="form-control" autocomplete="off" list="estado-options" placeholder="Digite 3 caracteres">
                    <datalist id="estado-options"></datalist>
                </div>
                <div class="col-6 col-lg-2">
                    <label for="filter-municipio" class="form-label text-muted small">Municipio</label>
                    <input id="filter-municipio" class="form-control" autocomplete="off" list="municipio-options" placeholder="Selecione estado antes" disabled>
                    <datalist id="municipio-options"></datalist>
                </div>
                <div class="col-6 col-lg-2">
                    <label for="filter-matriz" class="form-label text-muted small">Matriz</label>
                    <input id="filter-matriz" class="form-control" inputmode="numeric" autocomplete="off" placeholder="8 digitos">
                </div>
                <div class="col-6 col-lg-2">
                    <label for="filter-filial" class="form-label text-muted small">Filial</label>
                    <input id="filter-filial" class="form-control" inputmode="numeric" autocomplete="off" placeholder="0001">
                </div>
                <div class="col-12 col-lg-4">
                    <label for="filter-razao" class="form-label text-muted small">Razao social / fantasia</label>
                    <input id="filter-razao" class="form-control" autocomplete="off" placeholder="Nome da empresa">
                </div>
                <div class="col-12 col-lg-2">
                    <label for="filter-situacao" class="form-label text-muted small">Situacao</label>
                    <input id="filter-situacao" class="form-control" autocomplete="off" placeholder="Ativa">
                </div>
                <div class="col-12 col-lg-auto d-flex gap-2">
                    <button type="submit" class="btn btn-purple btn-icon" title="Buscar" aria-label="Buscar">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-icon" onclick="clearSearch()" title="Limpar busca" aria-label="Limpar busca">
                        <i class="fa-solid fa-eraser"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-icon" onclick="refreshAggregates()" title="Atualizar agregação" aria-label="Atualizar agregação">
                        <i class="fa-solid fa-rotate"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-icon" onclick="enrichSelectedCnpjs()" title="Atualizar selecionados na OpenCNPJ" aria-label="Atualizar selecionados na OpenCNPJ">
                        <i class="fa-solid fa-cloud-arrow-down"></i>
                    </button>
                </div>
                <div class="col-12 col-lg">
                    <div class="d-flex flex-wrap gap-3 align-items-center">
                        <div class="text-muted small" id="result-summary">Carregando agregados...</div>
                        <label class="form-check-label small text-muted d-none align-items-center gap-2">
                            <input class="form-check-input" type="checkbox" id="allow-stale-cache" checked>
                            usar cache expirado se a API falhar
                        </label>
                    </div>
                </div>
            </form>
        </section>

        <section class="glass-card p-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th><input class="form-check-input" type="checkbox" id="check-all-cnpjs" title="Selecionar CNPJs" aria-label="Selecionar CNPJs"></th>
                            <th>CNPJ</th>
                            <th>Matriz</th>
                            <th>Filial</th>
                            <th>
                                <button type="button" class="sort-button" onclick="setSort('acidentes')" title="Ordenar por acidentes" aria-label="Ordenar por acidentes">
                                    <span>Acidentes</span><i class="fa-solid fa-sort" data-sort-icon="acidentes"></i>
                                </button>
                            </th>
                            <th>
                                <button type="button" class="sort-button" onclick="setSort('obitos')" title="Ordenar por obitos" aria-label="Ordenar por obitos">
                                    <span>Obitos</span><i class="fa-solid fa-sort" data-sort-icon="obitos"></i>
                                </button>
                            </th>
                            <th>Período</th>
                            <th>CNAE</th>
                            <th>Território</th>
                            <th>OpenCNPJ</th>
                            <th>Razão social</th>
                            <th>Situação</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="cnpj-table-body"></tbody>
                </table>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                <div class="d-flex align-items-center gap-2">
                    <label for="page-size-select" class="text-muted small mb-0">Exibir</label>
                    <select id="page-size-select" class="form-select form-select-sm w-auto" onchange="changePageSize(this.value)" title="Numero de CNPJs por pagina" aria-label="Numero de CNPJs por pagina">
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100" selected>100</option>
                        <option value="200">200</option>
                        <option value="300">300</option>
                    </select>
                    <span class="text-muted small">CNPJs</span>
                </div>
                <div class="text-muted small" id="page-summary">-</div>
                <div class="d-flex align-items-center gap-2" role="navigation" aria-label="Paginacao de CNPJs">
                <button type="button" class="btn btn-outline-secondary btn-icon" id="page-first" onclick="goToPage('first')" title="Primeira pagina" aria-label="Primeira pagina">
                    <i class="fa-solid fa-angles-left"></i>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-icon" id="page-prev" onclick="goToPage('prev')" title="Página anterior" aria-label="Página anterior">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <div class="text-muted small" id="page-index">-</div>
                <button type="button" class="btn btn-outline-secondary btn-icon" id="page-next" onclick="goToPage('next')" title="Próxima página" aria-label="Próxima página">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-icon" id="page-last" onclick="goToPage('last')" title="Ultima pagina" aria-label="Ultima pagina">
                    <i class="fa-solid fa-angles-right"></i>
                </button>
                </div>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let pageSize = 100;
        let currentOffset = 0;
        let totalRows = 0;
        let currentQuery = '';
        let currentSort = 'acidentes';
        let currentDir = 'desc';
        let locationSuggestTimer = null;

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('check-all-cnpjs')?.addEventListener('change', event => {
                document.querySelectorAll('.cnpj-row-check').forEach(input => {
                    input.checked = event.target.checked;
                });
            });
            setupLocationSuggest();
            loadAggregates();
        });

        function onlyDigits(value) {
            return String(value || '').replace(/\D+/g, '');
        }

        function formatCnpj(value) {
            const digits = onlyDigits(value);
            if (digits.length !== 14) return digits || '-';
            return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8, 12)}-${digits.slice(12, 14)}`;
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function fmtNumber(value) {
            return new Intl.NumberFormat('pt-BR').format(Number(value || 0));
        }

        function fmtDate(value) {
            if (!value) return '-';
            const [year, month, day] = String(value).split('-');
            return year && month && day ? `${day}/${month}/${year}` : value;
        }

        function stripLeadingCode(value) {
            return String(value || '').replace(/^\s*\d+\s*[-/]\s*/, '').trim();
        }

        function cnaeLabel(row) {
            return row.cnae_rotulo || row.cnae_descricao || row.cnae_codigo || '-';
        }

        function cnaeHierarchyTitle(row) {
            const items = Array.isArray(row.cnae_hierarquia) ? row.cnae_hierarquia : [];
            return items.length
                ? items.map(item => item.rotulo).filter(Boolean).join(' > ')
                : cnaeLabel(row);
        }

        function territoryLabel(row) {
            const city = stripLeadingCode(row.municipio_empregador);
            return [city, row.uf_empregador].filter(Boolean).join(' / ') || '-';
        }

        function renderSkeleton() {
            const body = document.getElementById('cnpj-table-body');
            body.innerHTML = Array.from({ length: 8 }).map(() => `
                <tr>
                    <td><span class="skeleton-line" style="width: 20px;"></span></td>
                    <td><span class="skeleton-line" style="width: 150px;"></span></td>
                    <td><span class="skeleton-line" style="width: 80px;"></span></td>
                    <td><span class="skeleton-line" style="width: 54px;"></span></td>
                    <td><span class="skeleton-line" style="width: 48px;"></span></td>
                    <td><span class="skeleton-line" style="width: 40px;"></span></td>
                    <td><span class="skeleton-line" style="width: 120px;"></span></td>
                    <td><span class="skeleton-line" style="width: 180px;"></span></td>
                    <td><span class="skeleton-line" style="width: 140px;"></span></td>
                    <td><span class="skeleton-line" style="width: 80px;"></span></td>
                    <td><span class="skeleton-line" style="width: 180px;"></span></td>
                    <td><span class="skeleton-line" style="width: 80px;"></span></td>
                    <td><span class="skeleton-line ms-auto" style="width: 40px;"></span></td>
                </tr>
            `).join('');
        }

        async function loadAggregates() {
            renderSkeleton();
            const params = new URLSearchParams({
                action: 'cnpj_aggregates',
                q: currentQuery,
                estado: document.getElementById('filter-estado')?.value || '',
                municipio: document.getElementById('filter-municipio')?.value || '',
                matriz: document.getElementById('filter-matriz')?.value || '',
                filial: document.getElementById('filter-filial')?.value || '',
                razao_social: document.getElementById('filter-razao')?.value || '',
                situacao: document.getElementById('filter-situacao')?.value || '',
                limit: pageSize,
                offset: currentOffset,
                sort: currentSort,
                dir: currentDir,
            });
            try {
                const response = await fetch(`api_etl.php?${params.toString()}`);
                const data = await response.json();
                if (!data.success) throw new Error(data.error || 'Falha ao carregar CNPJs.');
                totalRows = data.total || 0;
                renderRows(data.rows || []);
                updateSummaries();
                updateSortIcons();
                await hydrateOpenCnpjCache(data.rows || []);
            } catch (error) {
                document.getElementById('cnpj-table-body').innerHTML = `
                    <tr><td colspan="13" class="text-center text-danger py-5">${escapeHtml(error.message)}</td></tr>
                `;
                document.getElementById('result-summary').textContent = 'Não foi possível carregar os agregados.';
                document.getElementById('page-summary').textContent = '-';
            }
        }

        async function refreshAggregates() {
            renderSkeleton();
            document.getElementById('result-summary').textContent = 'Atualizando base agregada...';
            try {
                const response = await fetch('api_etl.php?action=refresh_cnpj_aggregates');
                const data = await response.json();
                if (!data.success) throw new Error(data.error || 'Falha ao atualizar agregação.');
                currentOffset = 0;
                await loadAggregates();
            } catch (error) {
                document.getElementById('cnpj-table-body').innerHTML = `
                    <tr><td colspan="13" class="text-center text-danger py-5">${escapeHtml(error.message)}</td></tr>
                `;
                document.getElementById('result-summary').textContent = 'Não foi possível atualizar a base agregada.';
            }
        }

        function renderRows(rows) {
            const body = document.getElementById('cnpj-table-body');
            if (!rows.length) {
                body.innerHTML = '<tr><td colspan="13" class="text-center text-muted py-5">Nenhum CNPJ encontrado.</td></tr>';
                return;
            }
            body.innerHTML = rows.map(row => {
                const cnpj = row.cnpj_digits || '';
                const period = `${fmtDate(row.primeira_ocorrencia)} a ${fmtDate(row.ultima_ocorrencia)}`;
                const cnae = cnaeLabel(row);
                const cnaeTitle = cnaeHierarchyTitle(row);
                const territory = territoryLabel(row);
                return `
                    <tr>
                        <td><input class="form-check-input cnpj-row-check" type="checkbox" value="${escapeHtml(cnpj)}" aria-label="Selecionar ${formatCnpj(cnpj)}"></td>
                        <td><a class="cnpj-link font-monospace" href="cnpj.php?cnpj=${encodeURIComponent(cnpj)}">${formatCnpj(cnpj)}</a></td>
                        <td class="font-monospace">${escapeHtml(row.matriz || '-')}</td>
                        <td class="font-monospace">${escapeHtml(row.filial || '-')}</td>
                        <td class="fw-semibold">${fmtNumber(row.acidentes)}</td>
                        <td>${fmtNumber(row.obitos)}</td>
                        <td>${escapeHtml(period)}</td>
                        <td><span title="${escapeHtml(cnaeTitle)}">${escapeHtml(cnae)}</span></td>
                        <td>${escapeHtml(territory)}</td>
                        <td class="small" data-opencnpj-status="${escapeHtml(cnpj)}"><span class="text-muted">cache...</span></td>
                        <td data-opencnpj-name="${escapeHtml(cnpj)}"><span class="text-muted">-</span></td>
                        <td data-opencnpj-situation="${escapeHtml(cnpj)}"><span class="text-muted">-</span></td>
                        <td class="text-end">
                            <span class="actions-inline">
                            <button type="button" class="btn btn-outline-secondary btn-icon btn-sm" onclick="fetchSingleOpenCnpj('${escapeHtml(cnpj)}', true)" title="Atualizar OpenCNPJ" aria-label="Atualizar OpenCNPJ">
                                <i class="fa-solid fa-cloud-arrow-down"></i>
                            </button>
                            <a class="btn btn-outline-purple btn-icon btn-sm" href="inspecao.php?cnpj=${encodeURIComponent(cnpj)}" title="Navegar acidentes" aria-label="Navegar acidentes">
                                <i class="fa-solid fa-address-card"></i>
                            </a>
                            </span>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function updateSummaries() {
            const start = totalRows ? currentOffset + 1 : 0;
            const end = Math.min(currentOffset + pageSize, totalRows);
            const totalPages = totalRows ? Math.ceil(totalRows / pageSize) : 0;
            const currentPage = totalRows ? Math.floor(currentOffset / pageSize) + 1 : 0;
            const isFirst = currentOffset <= 0;
            const isLast = !totalRows || currentOffset + pageSize >= totalRows;
            document.getElementById('result-summary').textContent = `${fmtNumber(totalRows)} CNPJs encontrados`;
            document.getElementById('page-summary').textContent = `Exibindo ${fmtNumber(start)} a ${fmtNumber(end)} de ${fmtNumber(totalRows)}`;
            document.getElementById('page-index').textContent = totalPages ? `${fmtNumber(currentPage)} / ${fmtNumber(totalPages)}` : '-';
            document.getElementById('page-first').disabled = isFirst;
            document.getElementById('page-prev').disabled = isFirst;
            document.getElementById('page-next').disabled = isLast;
            document.getElementById('page-last').disabled = isLast;
        }

        function setSort(field) {
            if (!['acidentes', 'obitos'].includes(field)) return;
            if (currentSort === field) {
                currentDir = currentDir === 'desc' ? 'asc' : 'desc';
            } else {
                currentSort = field;
                currentDir = 'desc';
            }
            currentOffset = 0;
            updateSortIcons();
            loadAggregates();
        }

        function updateSortIcons() {
            document.querySelectorAll('[data-sort-icon]').forEach(icon => {
                const field = icon.getAttribute('data-sort-icon');
                icon.className = field === currentSort
                    ? `fa-solid ${currentDir === 'desc' ? 'fa-sort-down' : 'fa-sort-up'}`
                    : 'fa-solid fa-sort';
            });
        }

        function setupLocationSuggest() {
            const estado = document.getElementById('filter-estado');
            const municipio = document.getElementById('filter-municipio');
            if (!estado || !municipio) return;

            estado.addEventListener('input', () => {
                municipio.value = '';
                document.getElementById('municipio-options').innerHTML = '';
                municipio.disabled = estado.value.trim().length < 3;
                scheduleLocationSuggest('estado');
            });

            municipio.addEventListener('input', () => {
                scheduleLocationSuggest('municipio');
            });
        }

        function scheduleLocationSuggest(type) {
            window.clearTimeout(locationSuggestTimer);
            locationSuggestTimer = window.setTimeout(() => loadLocationOptions(type), 250);
        }

        async function loadLocationOptions(type) {
            const estado = document.getElementById('filter-estado')?.value.trim() || '';
            const municipio = document.getElementById('filter-municipio')?.value.trim() || '';
            const q = type === 'estado' ? estado : municipio;
            const targetId = type === 'estado' ? 'estado-options' : 'municipio-options';
            const target = document.getElementById(targetId);
            if (!target) return;
            if (q.length < 3 || (type === 'municipio' && estado.length < 3)) {
                target.innerHTML = '';
                return;
            }

            const params = new URLSearchParams({
                action: 'cnpj_filter_options',
                type,
                q,
                estado,
            });
            const response = await fetch(`api_etl.php?${params.toString()}`);
            const data = await response.json();
            if (!data.success) return;
            target.innerHTML = (data.options || [])
                .map(option => `<option value="${escapeHtml(option)}"></option>`)
                .join('');
        }

        function applySearch() {
            currentQuery = document.getElementById('cnpj-search').value.trim();
            currentOffset = 0;
            loadAggregates();
        }

        function clearSearch() {
            document.getElementById('cnpj-search').value = '';
            ['filter-estado', 'filter-municipio', 'filter-matriz', 'filter-filial', 'filter-razao', 'filter-situacao'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            document.getElementById('estado-options').innerHTML = '';
            document.getElementById('municipio-options').innerHTML = '';
            document.getElementById('filter-municipio').disabled = true;
            currentQuery = '';
            currentOffset = 0;
            loadAggregates();
        }

        function changePage(direction) {
            const next = currentOffset + (direction * pageSize);
            if (next < 0 || next >= totalRows) return;
            currentOffset = next;
            loadAggregates();
        }

        function goToPage(target) {
            if (!totalRows) return;
            if (target === 'first') {
                currentOffset = 0;
            } else if (target === 'last') {
                currentOffset = Math.max(0, (Math.ceil(totalRows / pageSize) - 1) * pageSize);
            } else if (target === 'prev') {
                currentOffset = Math.max(0, currentOffset - pageSize);
            } else if (target === 'next') {
                currentOffset = Math.min(Math.max(0, totalRows - 1), currentOffset + pageSize);
            }
            loadAggregates();
        }

        function changePageSize(value) {
            const nextSize = Number(value);
            if (!Number.isFinite(nextSize) || nextSize < 10) return;
            pageSize = Math.min(nextSize, 300);
            currentOffset = 0;
            loadAggregates();
        }

        function visibleCnpjs() {
            return Array.from(document.querySelectorAll('[data-opencnpj-status]'))
                .map(el => el.getAttribute('data-opencnpj-status'))
                .filter(Boolean);
        }

        function selectedCnpjs() {
            return Array.from(document.querySelectorAll('.cnpj-row-check:checked'))
                .map(el => el.value)
                .filter(Boolean);
        }

        function applyOpenCnpjItem(cnpj, item) {
            const statusEl = document.querySelector(`[data-opencnpj-status="${cnpj}"]`);
            const nameEl = document.querySelector(`[data-opencnpj-name="${cnpj}"]`);
            const situationEl = document.querySelector(`[data-opencnpj-situation="${cnpj}"]`);
            if (!statusEl || !nameEl || !situationEl) return;

            if (!item) {
                statusEl.innerHTML = '<span class="text-muted">não consultado</span>';
                return;
            }

            const source = item.source === 'api'
                ? 'API'
                : (item.source === 'stale-cache' || item.is_fresh === false ? 'cache expirado' : 'cache');
            const ok = Number(item.status_http) === 200;
            const notFound = Number(item.status_http) === 404;
            statusEl.innerHTML = ok
                ? `<span class="badge text-bg-success" title="${escapeHtml(item.consultado_em || '')}">${source}</span>`
                : `<span class="badge text-bg-secondary" title="${escapeHtml(item.erro || '')}">${notFound ? 'não encontrado' : 'erro'}</span>`;
            nameEl.innerHTML = escapeHtml(item.razao_social || item.nome_fantasia || '-');
            situationEl.innerHTML = escapeHtml(item.situacao || '-');
        }

        async function hydrateOpenCnpjCache(rows) {
            const cnpjs = rows.map(row => row.cnpj_digits).filter(Boolean);
            if (!cnpjs.length) return;
            for (let i = 0; i < cnpjs.length; i += 25) {
                const batch = cnpjs.slice(i, i + 25);
                const params = new URLSearchParams({ action: 'cnpj_cache_status', cnpjs: batch.join(',') });
                const response = await fetch(`api_etl.php?${params.toString()}`);
                const data = await response.json();
                if (!data.success) continue;
                for (const cnpj of batch) {
                    applyOpenCnpjItem(cnpj, data.cache?.[cnpj] || null);
                }
            }
        }

        async function fetchSingleOpenCnpj(cnpj, force = false) {
            const statusEl = document.querySelector(`[data-opencnpj-status="${cnpj}"]`);
            if (statusEl) statusEl.innerHTML = '<span class="text-muted">consultando...</span>';
            const response = await fetch('api_etl.php?action=fetch_opencnpj', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    cnpj,
                    force,
                    allow_stale: true,
                }),
            });
            const data = await response.json();
            if (!data.success) throw new Error(data.error || 'Falha ao consultar OpenCNPJ.');
            applyOpenCnpjItem(cnpj, data.item);
        }

        async function enrichVisibleCnpjs(force = false) {
            const cnpjs = visibleCnpjs();
            const pending = cnpjs.filter(cnpj => {
                const text = document.querySelector(`[data-opencnpj-status="${cnpj}"]`)?.textContent || '';
                return force || text.includes('não consultado') || text.includes('cache...') || text.includes('cache expirado');
            }).slice(0, 5);
            if (!pending.length) return;
            pending.forEach(cnpj => {
                const el = document.querySelector(`[data-opencnpj-status="${cnpj}"]`);
                if (el) el.innerHTML = '<span class="text-muted">consultando...</span>';
            });
            const response = await fetch('api_etl.php?action=fetch_opencnpj_batch', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    cnpjs: pending,
                    force,
                    allow_stale: true,
                }),
            });
            const data = await response.json();
            if (!data.success) throw new Error(data.error || 'Falha ao consultar OpenCNPJ.');
            for (const [cnpj, item] of Object.entries(data.items || {})) {
                applyOpenCnpjItem(cnpj, item);
            }
        }

        async function enrichSelectedCnpjs() {
            const cnpjs = selectedCnpjs();
            if (!cnpjs.length) return;
            for (let i = 0; i < cnpjs.length; i += 5) {
                const batch = cnpjs.slice(i, i + 5);
                batch.forEach(cnpj => {
                    const el = document.querySelector(`[data-opencnpj-status="${cnpj}"]`);
                    if (el) el.innerHTML = '<span class="text-muted">consultando...</span>';
                });
                const response = await fetch('api_etl.php?action=fetch_opencnpj_batch', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ cnpjs: batch, force: true, allow_stale: true }),
                });
                const data = await response.json();
                if (!data.success) throw new Error(data.error || 'Falha ao consultar OpenCNPJ.');
                for (const [cnpj, item] of Object.entries(data.items || {})) {
                    applyOpenCnpjItem(cnpj, item);
                }
            }
        }
    </script>
</body>
</html>
