<?php
/**
 * CAT - Painel Geral
 */
require_once __DIR__ . '/../../cat/src/db.php';

$db_status = "Desconectado";
$db_error = null;
$total_files = 0;
$loaded_files = 0;
$total_rows = 0;
$failed_files = 0;

try {
    $db = getDBConnection();
    $db_status = "Conectado";
    
    // Fetch initial stats
    $total_files = (int)$db->query("SELECT COUNT(*) FROM arquivos_importacao")->fetchColumn();
    $loaded_files = (int)$db->query("SELECT COUNT(*) FROM arquivos_importacao WHERE situacao_carga = 'Carregado'")->fetchColumn();
    $total_rows = (int)$db->query("SELECT COALESCE(SUM(linhas_processadas), 0) FROM arquivos_importacao WHERE situacao_carga = 'Carregado'")->fetchColumn();
    $failed_files = (int)$db->query("SELECT COUNT(*) FROM arquivos_importacao WHERE situacao_extracao = 'Falhou' OR situacao_carga = 'Falhou'")->fetchColumn();
} catch (Exception $e) {
    $db_error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-module="cat">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAT - Painel de Acidentes de Trabalho</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/favicon.png">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- FontAwesome -->
<script src="../assets/js/theme-switcher.js?v=20260629-vanilla"></script>
    <link href="../assets/css/style.css?v=20260629-vanilla" rel="stylesheet">
</head>
<body class="module-page">

    <!-- Navbar -->
    <!-- Navbar -->
    <?php
    require_once __DIR__ . '/../../includes/navbar.php';
    render_platform_navbar('cat', 'painel');
    ?>

    <!-- Main Container -->
    <main class="container py-5">
        
        <!-- Header Section -->
        <header class="landing-hero text-center mb-5">
            <img src="../assets/img/logo-fundo-escuro-horizontal.png" alt="RENAST" class="platform-logo-img landing-hero-logo mb-4">
            <div class="landing-eyebrow mb-3">
                <i class="bi bi-file-earmark-medical me-1"></i> Comunicações de Acidente de Trabalho
            </div>
            <h1 class="display-5 text-primary mb-2">Painel de Acidentes de Trabalho</h1>
            <p class="lead text-secondary">
                Consolidado nacional de Comunicações de Acidentes de Trabalho (CAT). Visualize tendências temporais, analise o impacto por região e filtre os dados dinamicamente.
            </p>
        </header>

        <?php if ($db_error): ?>
            <div class="alert alert-danger p-4 card border-danger mb-5" role="alert">
                <h4 class="alert-heading text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Erro de Conexão com o Banco de Dados</h4>
                <p class="mb-0 font-monospace text-body bg-body-tertiary bg-opacity-50 p-3 rounded border border-danger mt-3"><?= htmlspecialchars($db_error) ?></p>
            </div>
        <?php endif; ?>

        <!-- Global Filters -->
        <form id="dashboard-filter-form" class="card row g-3 mb-5 p-3 align-items-end" onsubmit="event.preventDefault(); applyDashboardFilters();">
            <div class="col-md-2">
                <label class="form-label small text-muted text-uppercase fw-semibold">Indica Óbito</label>
                <select id="filter-obito" class="form-select">
                    <option value="">Todos</option>
                    <option value="Sim">Sim</option>
                    <option value="Não">Não</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted text-uppercase fw-semibold">Data Inicial</label>
                <input type="date" id="filter-data-inicio" class="form-control" title="Início do período de ocorrência do acidente/óbito">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted text-uppercase fw-semibold">Data Final</label>
                <input type="date" id="filter-data-fim" class="form-control" title="Fim do período de ocorrência do acidente/óbito">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted text-uppercase fw-semibold">Estado (UF)</label>
                <input type="text" id="filter-estado" list="states-list" class="form-control" placeholder="Digite 3 letras...">
                <datalist id="states-list"></datalist>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted text-uppercase fw-semibold">Município do Empregador</label>
                <input type="text" id="filter-municipio" list="cities-list" class="form-control" placeholder="Escolha UF e digite 3 letras..." disabled>
                <datalist id="cities-list"></datalist>
            </div>
            <div class="col-md-1 d-flex gap-2">
                <button type="submit" id="btn-chart-filter" class="btn btn-primary btn-icon rounded-circle" title="Filtrar painel" aria-label="Filtrar painel">
                    <i class="bi bi-funnel"></i>
                </button>
                <button type="button" onclick="clearDashboardFilters()" class="btn btn-outline-secondary btn-icon rounded-circle" title="Limpar filtros" aria-label="Limpar filtros">
                    <i class="bi bi-funnel"></i>
                </button>
            </div>
        </form>

        <!-- Stats Section -->
        <section class="row g-4 mb-5">
            <div class="col-6 col-md-3">
                <div class="card stat-card">
                    <div class="stat-number" id="stats-total-files"><?= number_format($total_files, 0, ',', '.') ?></div>
                    <div class="stat-label">Arquivos Sincronizados</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card">
                    <div class="stat-number text-success" id="stats-loaded-files"><?= number_format($loaded_files, 0, ',', '.') ?></div>
                    <div class="stat-label">Arquivos Carregados</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card">
                    <div class="stat-number text-warning" id="stats-total-rows"><?= number_format($total_rows, 0, ',', '.') ?></div>
                    <div class="stat-label">Acidentes de Trabalho</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card">
                    <div class="stat-number text-danger" id="stats-failed-files"><?= number_format($failed_files, 0, ',', '.') ?></div>
                    <div class="stat-label">Falhas de ETL</div>
                </div>
            </div>
        </section>

        <!-- Distribution Dashboard -->
        <section class="card p-4 mb-4">
            <h4 class="mb-3 text-body" id="distribution-dashboard-title"><i class="bi bi-bar-chart text-primary me-2"></i>Distribuições básicas</h4>
            <div class="row g-3 d-none" id="distribution-dashboard">
                <div class="col-12 text-muted small">Carregando...</div>
            </div>
        </section>

        <!-- Data Quality Dashboard -->
        <section class="card p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h4 class="mb-1 text-body"><i class="bi bi-shield-check text-primary me-2"></i>Qualidade dos Campos</h4>
                    <div class="text-muted small" id="quality-scope-note">Achados calculados sobre o arquivo carregado mais recente.</div>
                </div>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill font-monospace" id="quality-total-records">0 registros</span>
            </div>
            <div id="quality-loading" class="row g-4">
                <div class="col-12">
                    <div class="skeleton-block skeleton-line"></div>
                </div>
                <div class="col-lg-7">
                    <div class="skeleton-block skeleton-chart"></div>
                </div>
                <div class="col-lg-5 d-flex flex-column gap-3">
                    <div class="skeleton-block skeleton-card"></div>
                    <div class="skeleton-block skeleton-card"></div>
                    <div class="skeleton-block skeleton-card"></div>
                </div>
            </div>

            <div class="row g-4 mb-4 d-none" id="quality-dashboard-content">
                <div class="col-12">
                    <div class="quality-chart-container">
                        <canvas id="qualityFindingsChart"></canvas>
                    </div>
                    <div class="text-muted small mt-2">Passe o mouse sobre as barras para ver a frequência absoluta.</div>
                </div>
            </div>

        </section>

        <!-- Dashboard Content -->
        <div class="card p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <h4 class="mb-0 text-body"><i class="bi bi-graph-up text-primary me-2"></i>Evolução de Acidentes por Mês/Ano</h4>
            </div>

            <!-- Chart Canvas -->
            <div class="chart-container">
                <div id="chart-skeleton" class="skeleton-block skeleton-chart d-none"></div>
                <canvas id="accidentsChart"></canvas>
            </div>
        </div>

    </main>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Javascript Dashboard Logic -->
    <script>
        let myChart = null;
        let qualityChart = null;
        let qualityDashboardData = null;

        function cssVar(name) {
            return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        }

        function rgbaVar(name, alpha, fallbackRgb = '13, 110, 253') {
            const rgb = cssVar(name) || fallbackRgb;
            return `rgba(${rgb}, ${alpha})`;
        }

        function hexToRgba(hex, alpha) {
            const normalized = hex.replace('#', '');
            const value = normalized.length === 3
                ? normalized.split('').map(char => char + char).join('')
                : normalized;
            const bigint = parseInt(value, 16);
            const r = (bigint >> 16) & 255;
            const g = (bigint >> 8) & 255;
            const b = bigint & 255;
            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        }

        document.addEventListener('DOMContentLoaded', function () {
            setupFilterAutocomplete();
            applyDashboardFilters();
        });

        async function loadChartData() {
            setChartLoading(true);
            const filters = getDashboardFilters();
            
            const url = `api_etl.php?action=chart_data&${filters.toString()}`;
            
            try {
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success) {
                    // Update total rows stats dynamically based on filters
                    document.getElementById('stats-total-rows').textContent = new Intl.NumberFormat('pt-BR').format(data.total_acidentes);
                    
                    // Render/update Chart
                    renderChart(data.labels, data.values);
                }
            } catch (e) {
                console.error("Erro ao carregar dados do gráfico:", e);
            } finally {
                setChartLoading(false);
            }
        }

        function setChartLoading(isLoading) {
            const badge = document.getElementById('chart-loading');
            const button = document.getElementById('btn-chart-filter');
            if (badge) badge.classList.toggle('d-none', !isLoading);
            if (!button) return;
            button.disabled = isLoading;
            button.setAttribute('title', isLoading ? 'Calculando painel' : 'Filtrar painel');
            button.setAttribute('aria-label', isLoading ? 'Calculando painel' : 'Filtrar painel');
            button.innerHTML = '<i class="bi bi-funnel"></i>';
            const chartSkeleton = document.getElementById('chart-skeleton');
            const chartCanvas = document.getElementById('accidentsChart');
            if (chartSkeleton) chartSkeleton.classList.toggle('d-none', !isLoading);
            if (chartCanvas) chartCanvas.classList.toggle('d-none', isLoading);
        }

        async function loadQualityDashboard() {
            setQualityLoading(true);
            try {
                const filters = getDashboardFilters();
                const response = await fetch(`api_etl.php?action=quality_dashboard&${filters.toString()}`);
                const data = await response.json();
                if (!data.success) return;
                qualityDashboardData = data;

                document.getElementById('quality-total-records').textContent =
                    `${new Intl.NumberFormat('pt-BR').format(data.total_records || 0)} registros`;
                const scopeNote = document.getElementById('quality-scope-note');
                if (scopeNote && data.scope?.arquivo_nome) {
                    scopeNote.textContent = `Achados calculados sobre: ${data.scope.arquivo_nome}`;
                }

                renderQualityChart(data.quality_findings || []);
                renderDistributions(data.distributions || {});
            } catch (error) {
                console.error("Erro ao carregar dashboard de qualidade:", error);
                const loading = document.getElementById('quality-loading');
                if (loading) {
                    loading.innerHTML = '<div class="col-12 text-danger small"><i class="bi bi-exclamation-triangle me-2"></i>Não foi possível calcular a qualidade dos campos.</div>';
                    loading.classList.remove('d-none');
                }
                return;
            } finally {
                if (qualityDashboardData) setQualityLoading(false);
            }
        }

        function setQualityLoading(isLoading) {
            const loading = document.getElementById('quality-loading');
            const content = document.getElementById('quality-dashboard-content');
            const dist = document.getElementById('distribution-dashboard');
            if (loading) loading.classList.toggle('d-none', !isLoading);
            if (content) content.classList.toggle('d-none', isLoading);
            if (dist) dist.classList.toggle('d-none', isLoading);
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function renderQualityChart(findings) {
            const ctx = document.getElementById('qualityFindingsChart').getContext('2d');
            const textColor = cssVar('--bs-secondary-color') || cssVar('--bs-body-color');
            const gridColor = rgbaVar('--bs-body-color-rgb', 0.08, '33, 37, 41');
            const accentColor = cssVar('--accent') || cssVar('--bs-secondary');
            const topFindings = findings.filter(item => (item.count || 0) > 0).slice(0, 18);

            if (qualityChart) {
                qualityChart.destroy();
            }

            qualityChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: topFindings.map(item => item.label || `${item.field} - ${item.status}`),
                    datasets: [{
                        data: topFindings.map(item => item.percent || 0),
                        counts: topFindings.map(item => item.count || 0),
                        backgroundColor: hexToRgba(accentColor, 0.72),
                        borderColor: accentColor,
                        borderWidth: 1,
                        borderRadius: 6
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            displayColors: false,
                            callbacks: {
                                label: context => {
                                    const count = context.dataset.counts?.[context.dataIndex] || 0;
                                    const percent = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(context.raw || 0);
                                    return `${percent}% (${new Intl.NumberFormat('pt-BR').format(count)} registros)`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            min: 0,
                            max: 100,
                            grid: { color: gridColor },
                            ticks: {
                                color: textColor,
                                callback: value => `${new Intl.NumberFormat('pt-BR').format(value)}%`
                            }
                        },
                        y: {
                            grid: { display: false },
                            ticks: { color: textColor, font: { size: 10 } }
                        }
                    }
                }
            });
        }

        function renderDistributions(distributions) {
            const container = document.getElementById('distribution-dashboard');
            if (!container) return;

            const labels = {
                tipo_acidente: 'Tipo de acidente',
                indica_obito_acidente: 'Indica óbito',
                sexo: 'Sexo',
                emitente_cat: 'Emitente CAT',
                origem_cadastramento_cat: 'Origem cadastramento',
                filiacao_segurado: 'Filiação segurado',
                tipo_empregador: 'Tipo empregador'
            };

            container.innerHTML = Object.entries(labels).map(([field, label]) => {
                const rows = distributions[field] || [];
                const rowsHtml = rows.length > 0 ? rows.map(row => `
                    <div class="distribution-row small">
                        <div class="text-body text-truncate" title="${escapeHtml(row.value)} - ${new Intl.NumberFormat('pt-BR').format(row.count || 0)} registros">${escapeHtml(row.value)}</div>
                        <div class="distribution-bar-track"><div class="distribution-bar"></div></div>
                        <div class="font-monospace text-muted" title="${new Intl.NumberFormat('pt-BR').format(row.count || 0)} registros">${new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(row.percent || 0)}%</div>
                    </div>
                `).join('') : '<div class="text-muted small">Sem dados.</div>';

                return `
                    <div class="col-md-6 col-xl-4">
                        <div class="card p-3 h-100">
                            <div class="text-muted small text-uppercase fw-semibold mb-2">${escapeHtml(label)}</div>
                            <div class="d-flex flex-column gap-2">${rowsHtml}</div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function renderChart(labels, values) {
            const ctx = document.getElementById('accidentsChart').getContext('2d');
            const gridColor = rgbaVar('--bs-body-color-rgb', 0.08, '33, 37, 41');
            const textColor = cssVar('--bs-secondary-color') || cssVar('--bs-body-color');
            const accentColor = cssVar('--accent') || cssVar('--bs-secondary');
            
            if (myChart) {
                myChart.destroy();
            }
            
            const gradient = ctx.createLinearGradient(0, 0, 0, 350);
            gradient.addColorStop(0, hexToRgba(accentColor, 0.20));
            gradient.addColorStop(1, hexToRgba(accentColor, 0));
            
            myChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Acidentes',
                        data: values,
                        borderColor: accentColor,
                        borderWidth: 3,
                        backgroundColor: gradient,
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: accentColor,
                        pointHoverRadius: 7,
                        pointRadius: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: cssVar('--bs-body-bg'),
                            titleColor: cssVar('--bs-body-color'),
                            bodyColor: accentColor,
                            borderColor: hexToRgba(accentColor, 0.28),
                            borderWidth: 1,
                            padding: 12,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return `Acidentes: ${new Intl.NumberFormat('pt-BR').format(context.raw)}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: textColor,
                                font: {
                                    family: 'system-ui',
                                    size: 11
                                }
                            }
                        },
                        y: {
                            grid: {
                                color: gridColor
                            },
                            ticks: {
                                color: textColor,
                                font: {
                                    family: 'system-ui',
                                    size: 11
                                },
                                callback: function(value) {
                                    return new Intl.NumberFormat('pt-BR').format(value);
                                }
                            }
                        }
                    }
                }
            });
        }

        function getDashboardFilters() {
            const params = new URLSearchParams();
            const obito = document.getElementById('filter-obito').value;
            const estado = document.getElementById('filter-estado').value.trim();
            const municipio = document.getElementById('filter-municipio').value.trim();
            const dataInicio = document.getElementById('filter-data-inicio').value;
            const dataFim = document.getElementById('filter-data-fim').value;

            if (obito) params.set('obito', obito);
            if (estado) params.set('estado', estado);
            if (municipio) params.set('municipio', municipio);
            if (dataInicio) params.set('data_inicio', dataInicio);
            if (dataFim) params.set('data_fim', dataFim);

            return params;
        }

        function applyDashboardFilters() {
            loadChartData();
            loadQualityDashboard();
        }

        function clearDashboardFilters() {
            document.getElementById('filter-obito').value = '';
            document.getElementById('filter-estado').value = '';
            document.getElementById('filter-municipio').value = '';
            document.getElementById('filter-data-inicio').value = '';
            document.getElementById('filter-data-fim').value = '';
            document.getElementById('states-list').innerHTML = '';
            document.getElementById('cities-list').innerHTML = '';
            updateMunicipioAvailability();
            applyDashboardFilters();
        }

        function setupFilterAutocomplete() {
            const estadoEl = document.getElementById('filter-estado');
            const municipioEl = document.getElementById('filter-municipio');
            if (estadoEl) {
                estadoEl.addEventListener('input', () => {
                    document.getElementById('filter-municipio').value = '';
                    document.getElementById('cities-list').innerHTML = '';
                    updateMunicipioAvailability();
                    loadStatesList();
                });
            }
            if (municipioEl) {
                municipioEl.addEventListener('input', loadCitiesList);
            }
            updateMunicipioAvailability();
        }

        function updateMunicipioAvailability() {
            const estado = document.getElementById('filter-estado').value.trim();
            const municipio = document.getElementById('filter-municipio');
            if (!municipio) return;
            const enabled = estado.length >= 3;
            municipio.disabled = !enabled;
            municipio.placeholder = enabled ? 'Digite 3 letras...' : 'Escolha UF primeiro...';
        }

        async function loadStatesList() {
            const query = document.getElementById('filter-estado').value.trim();
            const datalist = document.getElementById('states-list');
            if (!datalist) return;
            datalist.innerHTML = '';
            if (query.length < 3) return;

            try {
                const response = await fetch(`api_etl.php?action=get_states&q=${encodeURIComponent(query)}`);
                const data = await response.json();
                if (data.success) {
                    data.states.forEach(state => {
                        const opt = document.createElement('option');
                        opt.value = state;
                        datalist.appendChild(opt);
                    });
                }
            } catch (error) {
                console.error("Failed to load states:", error);
            }
        }

        async function loadCitiesList() {
            const estadoInput = document.getElementById('filter-estado');
            const municipioInput = document.getElementById('filter-municipio');
            const estado = estadoInput ? estadoInput.value : '';
            const query = municipioInput ? municipioInput.value.trim() : '';
            const datalist = document.getElementById('cities-list');
            if (!datalist) return;
            datalist.innerHTML = '';
            if (estado.trim().length < 3 || query.length < 3) return;

            try {
                const response = await fetch(`api_etl.php?action=get_cities&estado=${encodeURIComponent(estado)}&q=${encodeURIComponent(query)}`);
                const data = await response.json();
                if (data.success) {
                    data.cities.forEach(city => {
                        const opt = document.createElement('option');
                        opt.value = city;
                        datalist.appendChild(opt);
                    });
                }
            } catch (error) {
                console.error("Failed to load cities:", error);
            }
        }
    </script>
</body>
</html>
