<?php
/**
 * CAT - Painel de Controle de ETL (Ingestão de Dados)
 */
require_once __DIR__ . '/src/db.php';

$db_status = "Desconectado";
$db_error = null;
$total_files = 0;
$loaded_files = 0;
$total_rows = 0;
$duplicate_rows = 0;
$failed_files = 0;
$files = [];

try {
    $db = getDBConnection();
    $db_status = "Conectado";
    
    // Fetch stats
    $total_files = (int)$db->query("SELECT COUNT(*) FROM arquivos_importacao")->fetchColumn();
    $loaded_files = (int)$db->query("SELECT COUNT(*) FROM arquivos_importacao WHERE situacao_carga = 'Carregado'")->fetchColumn();
    $total_rows = (int)$db->query("SELECT COUNT(*) FROM registros_brutos")->fetchColumn();
    $duplicate_rows = (int)$db->query("
        SELECT COALESCE(SUM(qtd), 0)
          FROM (
              SELECT COUNT(*) AS qtd
                FROM registros_brutos
               GROUP BY hash_extended
              HAVING COUNT(*) > 1
          ) duplicados
    ")->fetchColumn();
    $failed_files = (int)$db->query("SELECT COUNT(*) FROM arquivos_importacao WHERE situacao_extracao = 'Falhou' OR situacao_carga = 'Falhou'")->fetchColumn();

    // Fetch files list with min/max dates from raw records
    $stmt = $db->query("
        SELECT ai.*, 
               to_char(d.menor_data, 'DD/MM/YYYY') as menor_data, 
               to_char(d.maior_data, 'DD/MM/YYYY') as maior_data
          FROM arquivos_importacao ai
          LEFT JOIN (
              SELECT arquivo_importacao_id,
                     MIN(CASE 
                         WHEN dados->>'data_acidente' ~ '^[0-3][0-9]/[0-1][0-9]/[0-9]{4}$' 
                         THEN to_date(dados->>'data_acidente', 'DD/MM/YYYY')
                         ELSE NULL 
                     END) as menor_data,
                     MAX(CASE 
                         WHEN dados->>'data_acidente' ~ '^[0-3][0-9]/[0-1][0-9]/[0-9]{4}$' 
                         THEN to_date(dados->>'data_acidente', 'DD/MM/YYYY')
                         ELSE NULL 
                     END) as maior_data
                FROM registros_brutos
               GROUP BY arquivo_importacao_id
          ) d ON d.arquivo_importacao_id = ai.id
         ORDER BY ai.id DESC
    ");
    $files = $stmt->fetchAll();
} catch (Exception $e) {
    $db_error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-module="cat">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAT - Painel de Controle de ETL</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../favicon.png">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- FontAwesome -->
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
            --console-bg: #0f172a;
            --field-bg: #f8fafc;
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
            --console-bg: #090d16;
            --field-bg: #111827;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Inter', sans-serif;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
        }

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
        .etl-table-card {
            overflow: hidden;
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
        .form-select option {
            background-color: var(--field-bg);
            color: var(--text-color);
        }
        [data-bs-theme="light"] input[type="date"] { color-scheme: light; }
        [data-bs-theme="dark"] input[type="date"] { color-scheme: dark; }

        /* Stats Cards */
        .stat-card {
            padding: 20px;
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 8px;
        }
        .stat-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Buttons & Color Overrides */
        .btn-purple {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            color: #fff;
            font-weight: 500;
        }
        .btn-purple:hover, .btn-purple:focus {
            background-color: var(--accent-hover);
            border-color: var(--accent-hover);
            color: #fff;
        }
        .btn-outline-purple {
            border-color: var(--accent-color);
            color: var(--accent-color);
            font-weight: 500;
        }
        .btn-outline-purple:hover {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            color: #fff;
        }
        .text-purple {
            color: var(--accent-color) !important;
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

        .btn-icon.btn-sm {
            width: 34px;
            height: 34px;
        }

        /* Table Styling */
        .etl-table-scroll {
            max-width: 100%;
            overflow-x: auto;
            overflow-y: visible;
            scrollbar-color: var(--accent-color) transparent;
            scrollbar-width: thin;
        }
        .etl-table-scroll::-webkit-scrollbar {
            height: 8px;
        }
        .etl-table-scroll::-webkit-scrollbar-thumb {
            background: rgba(168, 85, 247, 0.45);
            border-radius: 999px;
        }
        .table-custom {
            border-collapse: separate;
            border-spacing: 0 8px;
            min-width: 1040px;
            width: 100%;
            table-layout: fixed;
        }
        .table-custom thead th {
            border: none;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            padding: 12px 16px;
        }
        .table-custom tbody tr {
            background-color: var(--card-bg);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .table-custom tbody tr:hover {
            transform: translateY(-2px);
            box-shadow: var(--glass-shadow);
        }
        .table-custom tbody td {
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
            padding: 14px 12px;
            vertical-align: middle;
            overflow: hidden;
        }
        .table-custom tbody td:first-child {
            border-left: 1px solid var(--border-color);
            border-top-left-radius: 12px;
            border-bottom-left-radius: 12px;
        }
        .table-custom tbody td:last-child {
            border-right: 1px solid var(--border-color);
            border-top-right-radius: 12px;
            border-bottom-right-radius: 12px;
        }

        .cursor-help {
            cursor: help;
        }
        .etl-file-cell {
            width: 34%;
            min-width: 280px;
        }
        .etl-file-name,
        .etl-file-url {
            display: block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .etl-status-cell {
            width: 92px;
        }
        .etl-number-cell {
            width: 120px;
            white-space: nowrap;
        }
        .etl-date-cell {
            width: 118px;
            white-space: nowrap;
        }
        .etl-doc-cell {
            width: 130px;
            white-space: nowrap;
        }
        .etl-actions-cell {
            width: 190px;
            min-width: 190px;
            overflow: visible !important;
        }
        .etl-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: .5rem;
            flex-wrap: nowrap;
        }

        .cursor-pointer {
            cursor: pointer;
            user-select: none;
        }

        .sortable:hover {
            color: var(--accent-color) !important;
        }

        .progress-bar-custom {
            height: 8px;
            background-color: rgba(255, 255, 255, 0.05);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #6c757d, #464B51);
            width: 0%;
            transition: width 0.2s ease;
        }

        .modal-console {
            background-color: var(--console-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 15px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.8rem;
            height: 180px;
            overflow-y: auto;
            color: #e2e8f0;
            box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.8);
        }

        .console-line {
            margin-bottom: 4px;
        }
        .console-line.info { color: #94a3b8; }
        .console-line.success { color: #22c55e; }
        .console-line.warn { color: #eab308; }
        .console-line.error { color: #ef4444; }

        .step-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 0;
            color: var(--text-muted);
        }

        .step-item.active {
            color: #464B51;
            font-weight: 600;
        }

        .step-item.success {
            color: #22c55e;
        }

        .step-item.error {
            color: #ef4444;
        }

        .step-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            border: 1.5px solid currentColor;
            font-size: 0.75rem;
        }
    </style>
    <script src="../assets/js/theme-switcher.js"></script>
</head>
<body>

    <!-- Navbar -->
    <!-- Navbar -->
    <?php
    require_once __DIR__ . '/../includes/navbar.php';
    render_platform_navbar('cat', 'inicio');
    ?>

    <!-- Main Container -->
    <main class="container py-5">
        
        <!-- Header Section -->
        <header class="row mb-4 align-items-center">
            <div class="col-md-8">
                <h1 class="display-5 text-purple mb-2" style="font-weight: 800; color: #464B51;">Gerenciamento de Ingestão (ETL)</h1>
                <p class="lead text-secondary">
                    Controle de sincronização e ingestão das bases de dados de CAT do governo federal. Execute cargas incrementais, verifique logs de erros de processamento e limpe caches temporários.
                </p>
            </div>
            <div class="col-md-4 text-md-end text-start mt-3 mt-md-0 d-flex gap-2 justify-content-md-end">
                <button id="btn-sync-api" class="btn btn-purple btn-icon rounded-circle" title="Sincronizar lista" aria-label="Sincronizar lista" <?= ($db_status !== "Conectado") ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-rotate"></i>
                </button>
            </div>
        </header>

        <?php if ($db_error): ?>
            <div class="alert alert-danger p-4 glass-card border-danger mb-5" role="alert">
                <h4 class="alert-heading text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Erro de Conexão com o Banco de Dados</h4>
                <p class="mb-0 font-monospace text-light bg-dark bg-opacity-50 p-3 rounded border border-danger mt-3"><?= htmlspecialchars($db_error) ?></p>
            </div>
        <?php endif; ?>

        <!-- Stats Section -->
        <section class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-4 mb-4">
            <div class="col">
                <div class="glass-card stat-card">
                    <div class="stat-number" id="stats-total-files"><?= number_format($total_files, 0, ',', '.') ?></div>
                    <div class="stat-label">Arquivos Sincronizados</div>
                </div>
            </div>
            <div class="col">
                <div class="glass-card stat-card">
                    <div class="stat-number text-success" id="stats-loaded-files"><?= number_format($loaded_files, 0, ',', '.') ?></div>
                    <div class="stat-label">Arquivos Carregados</div>
                </div>
            </div>
            <div class="col">
                <div class="glass-card stat-card">
                    <div class="stat-number text-warning" id="stats-total-rows"><?= number_format($total_rows, 0, ',', '.') ?></div>
                    <div class="stat-label">Acidentes de Trabalho</div>
                </div>
            </div>
            <div class="col">
                <div class="glass-card stat-card">
                    <div class="stat-number text-info" id="stats-duplicate-rows"><?= number_format($duplicate_rows, 0, ',', '.') ?></div>
                    <div class="stat-label">Registros Duplicados</div>
                </div>
            </div>
            <div class="col">
                <div class="glass-card stat-card">
                    <div class="stat-number text-danger" id="stats-failed-files"><?= number_format($failed_files, 0, ',', '.') ?></div>
                    <div class="stat-label">Falhas de ETL</div>
                </div>
            </div>
        </section>

        <!-- ETL Pane -->
        <div class="glass-card p-4 etl-table-card">
            <h4 class="mb-4 text-light"><i class="fa-solid fa-cloud-arrow-down text-purple me-2"></i>Repositório de Arquivos Públicos (INSS)</h4>
            
            <?php if (empty($files)): ?>
                <div class="text-center py-5 text-secondary">
                    <i class="fa-solid fa-folder-open display-4 mb-3 d-block text-muted"></i>
                    <p class="mb-3">Nenhum arquivo catalogado ainda. Clique no botão de sincronização para listar as fontes da API do INSS.</p>
                    <button id="btn-sync-api-empty" class="btn btn-purple btn-icon rounded-circle" title="Buscar arquivos públicos" aria-label="Buscar arquivos públicos" <?= ($db_status !== "Conectado") ? 'disabled' : '' ?>>
                        <i class="fa-solid fa-rotate"></i>
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive etl-table-scroll">
                    <table class="table table-custom table-hover mb-0">
                         <thead>
                             <tr>
                                 <th>Nome do Arquivo / Período</th>
                                 <th class="text-center">Status</th>
                                 <th>Linhas Carregadas</th>
                                 <th class="sortable cursor-pointer" onclick="sortTableByDate(3)" id="th-menor-data" title="Ordenar por Menor Data do acidente">
                                     Menor Data <i class="fa-solid fa-sort ms-1 text-muted small"></i>
                                 </th>
                                 <th class="sortable cursor-pointer" onclick="sortTableByDate(4)" id="th-maior-data" title="Ordenar por Maior Data do acidente">
                                     Maior Data <i class="fa-solid fa-sort ms-1 text-muted small"></i>
                                 </th>
                                 <th class="sortable cursor-pointer" onclick="sortTableByDate(5)" id="th-last-run" title="Ordenar por Última Execução do ETL">
                                     Última Execução <i class="fa-solid fa-sort ms-1 text-muted small"></i>
                                 </th>
                                 <th>Documentação</th>
                                 <th class="text-end">Ações</th>
                             </tr>
                         </thead>
                        <tbody>
                            <?php foreach ($files as $file): ?>
                                <tr id="file-row-<?= $file['id'] ?>">
                                    <td class="etl-file-cell">
                                        <strong class="text-light etl-file-name" title="<?= htmlspecialchars($file['nome']) ?>"><?= htmlspecialchars($file['nome']) ?></strong>
                                        <span class="small text-muted etl-file-url" title="<?= htmlspecialchars($file['url_download']) ?>"><?= htmlspecialchars($file['url_download']) ?></span>
                                    </td>
                                     <td class="text-center etl-status-cell">
                                         <div class="d-inline-flex gap-3 fs-5">
                                             <!-- Extração Icon -->
                                             <?php if ($file['situacao_extracao'] === 'Extraído'): ?>
                                                 <i class="fa-solid fa-file-zipper text-success cursor-help" title="Extração: Arquivo ZIP extraído e validado com sucesso"></i>
                                             <?php elseif ($file['situacao_extracao'] === 'Falhou'): ?>
                                                 <i class="fa-solid fa-file-zipper text-danger cursor-help" title="Extração: Erro ao baixar ou extrair arquivo (verifique o log)"></i>
                                             <?php else: ?>
                                                 <i class="fa-solid fa-file-zipper text-muted cursor-help" title="Extração: Pendente"></i>
                                             <?php endif; ?>

                                             <!-- Carga Icon -->
                                             <?php if ($file['situacao_carga'] === 'Carregado'): ?>
                                                 <i class="fa-solid fa-database text-success cursor-help" title="Carga: Carga concluída com sucesso"></i>
                                             <?php elseif ($file['situacao_carga'] === 'Carregando'): ?>
                                                 <i class="fa-solid fa-spinner fa-spin text-warning cursor-help" title="Carga: Importação em andamento..."></i>
                                             <?php elseif ($file['situacao_carga'] === 'Falhou'): ?>
                                                 <i class="fa-solid fa-database text-danger cursor-help" title="Carga: Falhou (Erro: <?= htmlspecialchars($file['mensagem_erro'] ?? '') ?>)"></i>
                                             <?php else: ?>
                                                 <i class="fa-solid fa-database text-muted cursor-help" title="Carga: Pendente"></i>
                                             <?php endif; ?>
                                         </div>
                                     </td>
                                     <td class="etl-number-cell">
                                         <span class="font-monospace text-light" id="row-count-<?= $file['id'] ?>"><?= number_format($file['linhas_processadas'], 0, ',', '.') ?></span>
                                     </td>
                                     <td class="etl-date-cell">
                                         <span class="font-monospace text-muted"><?= htmlspecialchars($file['menor_data'] ?? '-') ?></span>
                                     </td>
                                     <td class="etl-date-cell">
                                         <span class="font-monospace text-muted"><?= htmlspecialchars($file['maior_data'] ?? '-') ?></span>
                                     </td>
                                     <td class="small text-muted font-monospace etl-date-cell" id="last-run-<?= $file['id'] ?>">
                                         <?= $file['ultima_execucao'] ? date('d/m/Y H:i', strtotime($file['ultima_execucao'])) : '-' ?>
                                     </td>
                                     <td class="etl-doc-cell">
                                         <?php if (!empty($file['documentacao_atualizada_em'])): ?>
                                             <div class="small text-light">
                                                 <?= number_format((int)$file['total_registros_documentados'], 0, ',', '.') ?> registros
                                             </div>
                                             <div class="small text-muted">
                                                 <?= number_format((int)$file['total_campos_documentados'], 0, ',', '.') ?> campos
                                             </div>
                                         <?php else: ?>
                                             <span class="small text-muted">Pendente</span>
                                         <?php endif; ?>
                                     </td>
                                    <td class="text-end etl-actions-cell">
                                        <?php $fileNameJs = json_encode($file['nome'], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
                                        <div class="etl-actions">
                                             <?php if ($file['situacao_carga'] === 'Carregado'): ?>
                                                 <button onclick="triggerETL(<?= $file['id'] ?>, <?= htmlspecialchars($fileNameJs, ENT_QUOTES, 'UTF-8') ?>, false)" class="btn btn-outline-warning btn-sm btn-icon rounded" title="Reexecutar ETL completo" aria-label="Reexecutar ETL completo">
                                                     <i class="fa-solid fa-arrow-rotate-right"></i>
                                                 </button>
                                             <?php else: ?>
                                                 <?php if ($file['linhas_processadas'] > 0): ?>
                                                     <button onclick="triggerETL(<?= $file['id'] ?>, <?= htmlspecialchars($fileNameJs, ENT_QUOTES, 'UTF-8') ?>, true)" class="btn btn-purple btn-sm btn-icon rounded" title="Continuar ETL do ponto de parada" aria-label="Continuar ETL do ponto de parada">
                                                         <i class="fa-solid fa-play"></i>
                                                     </button>
                                                     <button onclick="triggerETL(<?= $file['id'] ?>, <?= htmlspecialchars($fileNameJs, ENT_QUOTES, 'UTF-8') ?>, false)" class="btn btn-outline-purple btn-sm btn-icon rounded" title="Reiniciar do zero" aria-label="Reiniciar do zero">
                                                         <i class="fa-solid fa-backward"></i>
                                                     </button>
                                                 <?php else: ?>
                                                     <button onclick="triggerETL(<?= $file['id'] ?>, <?= htmlspecialchars($fileNameJs, ENT_QUOTES, 'UTF-8') ?>, false)" class="btn btn-purple btn-sm btn-icon rounded" title="Executar ETL completo" aria-label="Executar ETL completo">
                                                         <i class="fa-solid fa-play"></i>
                                                     </button>
                                                 <?php endif; ?>
                                             <?php endif; ?>
                                            
                                             <button onclick="showLogHistory(<?= $file['id'] ?>, <?= htmlspecialchars($fileNameJs, ENT_QUOTES, 'UTF-8') ?>)" class="btn btn-outline-info btn-sm btn-icon rounded" title="Visualizar logs de execução" aria-label="Visualizar logs de execução">
                                                 <i class="fa-solid fa-list-ul"></i>
                                             </button>
                                             <button onclick="showFileInfo(<?= $file['id'] ?>, <?= htmlspecialchars($fileNameJs, ENT_QUOTES, 'UTF-8') ?>)" class="btn btn-outline-secondary btn-sm btn-icon rounded" title="Ver documentação do arquivo" aria-label="Ver documentação do arquivo">
                                                 <i class="fa-solid fa-circle-info"></i>
                                             </button>
                                             <button onclick="resetETL(<?= $file['id'] ?>)" class="btn btn-outline-danger btn-sm btn-icon rounded" title="Limpar dados carregados" aria-label="Limpar dados carregados" <?= ($file['situacao_extracao'] === 'Pendente' && $file['situacao_carga'] === 'Pendente' && $file['linhas_processadas'] == 0) ? 'disabled' : '' ?>>
                                                 <i class="fa-solid fa-trash-can"></i>
                                             </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- ETL Execution Progress Modal -->
    <div class="modal fade" id="etlModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="etlModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content glass-card border border-secondary text-light">
                <div class="modal-header border-secondary pb-3">
                    <h5 class="modal-title" id="etlModalLabel"><i class="fa-solid fa-cogs text-purple me-2"></i>Executando Pipeline ETL</h5>
                </div>
                <div class="modal-body py-4">
                    
                    <h6 class="mb-3 text-light" id="etl-target-name">Processando...</h6>
                    
                    <!-- Steps Checklist -->
                    <div class="mb-4">
                        <div class="step-item" id="step-download">
                            <span class="step-icon" id="step-icon-download"><i class="fa-solid fa-spinner fa-spin d-none"></i>1</span>
                            <span>Download: Baixando arquivo ZIP do S3 do governo...</span>
                        </div>
                        <div class="step-item" id="step-extract">
                            <span class="step-icon" id="step-icon-extract">2</span>
                            <span>Descompactação & Validação: Validando formato ZIP e buscando JSON...</span>
                        </div>
                        <div class="step-item" id="step-load">
                            <span class="step-icon" id="step-icon-load">3</span>
                            <span>Ingestão de Dados: Carregando JSON em lotes de 1.000 linhas...</span>
                        </div>
                        <div class="step-item" id="step-cleanup">
                            <span class="step-icon" id="step-icon-cleanup">4</span>
                            <span>Limpeza de Cache: Removendo ZIP e CSV temporários...</span>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="mb-4 d-none" id="modal-progress-wrapper">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Ingestão de Linhas</span>
                            <span id="modal-progress-text">0%</span>
                        </div>
                        <div class="progress-bar-custom">
                            <div class="progress-fill" id="modal-progress-fill"></div>
                        </div>
                    </div>

                    <!-- Console Logs Drawer -->
                    <div class="modal-console" id="modal-console-log">
                        <div class="console-line info">Inicializando console do modal...</div>
                    </div>

                </div>
                <div class="modal-footer border-secondary pt-3">
                    <button type="button" id="btn-modal-close" class="btn btn-outline-secondary btn-icon rounded-circle" data-bs-dismiss="modal" title="Fechar" aria-label="Fechar" disabled><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Log History Modal -->
    <div class="modal fade" id="logHistoryModal" tabindex="-1" aria-labelledby="logHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content glass-card border border-secondary text-light">
                <div class="modal-header border-secondary pb-3">
                    <h5 class="modal-title" id="logHistoryModalLabel"><i class="fa-solid fa-file-invoice text-info me-2"></i>Histórico de Logs de Execução</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" title="Fechar" aria-label="Fechar"></button>
                </div>
                <div class="modal-body py-4">
                    <h6 class="mb-3 text-light" id="log-history-target-name">Arquivo...</h6>
                    <div class="modal-console" id="log-history-content" style="max-height: 400px; overflow-y: auto;">
                        <div class="text-muted text-center py-4">Nenhum log registrado para este arquivo.</div>
                    </div>
                </div>
                <div class="modal-footer border-secondary pt-3">
                    <button type="button" class="btn btn-outline-secondary btn-icon rounded-circle" data-bs-dismiss="modal" title="Fechar" aria-label="Fechar"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="fileInfoModal" tabindex="-1" aria-labelledby="fileInfoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content glass-card border border-secondary text-light">
                <div class="modal-header border-secondary pb-3">
                    <h5 class="modal-title" id="fileInfoModalLabel"><i class="fa-solid fa-circle-info text-purple me-2"></i>Documentação do Arquivo</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" title="Fechar" aria-label="Fechar"></button>
                </div>
                <div class="modal-body py-4">
                    <h6 id="file-info-target-name" class="text-purple mb-3">-</h6>
                    <div id="file-info-content" class="small text-muted">Carregando...</div>
                </div>
                <div class="modal-footer border-secondary pt-3">
                    <a href="campos.php" class="btn btn-outline-purple btn-icon rounded-circle" title="Ver matriz completa" aria-label="Ver matriz completa">
                        <i class="fa-solid fa-table-list"></i>
                    </a>
                    <button type="button" class="btn btn-outline-secondary btn-icon rounded-circle" data-bs-dismiss="modal" title="Fechar" aria-label="Fechar"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Javascript Actions and AJAX ETL Engine -->
    <!-- Confirmation Modal (custom instead of native confirm) -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border border-secondary text-light">
            <div class="modal-header border-secondary pb-3">
                <h5 class="modal-title" id="confirmModalLabel"><i class="fa-solid fa-exclamation-triangle text-warning me-2"></i>Confirmação</h5>
            </div>
            <div class="modal-body">
                <p id="confirmModalMessage"></p>
            </div>
            <div class="modal-footer border-secondary pt-3">
                <button type="button" class="btn btn-outline-secondary btn-icon rounded-circle" data-bs-dismiss="modal" title="Cancelar" aria-label="Cancelar"><i class="fa-solid fa-xmark"></i></button>
                <button type="button" class="btn btn-primary btn-icon rounded-circle" id="confirmModalConfirmBtn" title="Confirmar" aria-label="Confirmar"><i class="fa-solid fa-check"></i></button>
            </div>
        </div>
    </div>
</div>
<script>
function showConfirmModal(message, onConfirm) {
    const modalEl = document.getElementById('confirmModal');
    const modal = new bootstrap.Modal(modalEl);
    document.getElementById('confirmModalMessage').textContent = message;
    // Replace any previous listener to avoid duplicates
    const confirmBtn = document.getElementById('confirmModalConfirmBtn');
    const freshBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(freshBtn, confirmBtn);
    freshBtn.addEventListener('click', () => {
        try { onConfirm(); } finally { modal.hide(); }
    });
    modal.show();
}
        const btnSyncApi = document.getElementById('btn-sync-api');
        const btnSyncApiEmpty = document.getElementById('btn-sync-api-empty');

        // Modal Elements
        const etlModal = new bootstrap.Modal(document.getElementById('etlModal'), {
            keyboard: false,
            backdrop: 'static'
        });
        const mConsoleLog = document.getElementById('modal-console-log');
        const mProgressWrapper = document.getElementById('modal-progress-wrapper');
        const mProgressFill = document.getElementById('modal-progress-fill');
        const mProgressText = document.getElementById('modal-progress-text');
        const mBtnClose = document.getElementById('btn-modal-close');

        if (btnSyncApi) btnSyncApi.addEventListener('click', syncApiList);
        if (btnSyncApiEmpty) btnSyncApiEmpty.addEventListener('click', syncApiList);

        async function fetchWithRetry(url, options = {}, retries = 3, delay = 2000, fileId = null) {
            for (let i = 0; i < retries; i++) {
                try {
                    const response = await fetch(url, options);
                    const clone = response.clone();
                    let data;
                    try {
                        data = await clone.json();
                    } catch (e) {
                        const text = await response.text();
                        throw new Error(`Resposta inválida do servidor: ${text.substring(0, 100)}...`);
                    }
                    
                    if (!response.ok || (data && data.success === false)) {
                        const errMsg = (data && data.error) ? data.error : `HTTP ${response.status}`;
                        throw new Error(errMsg);
                    }
                    
                    return data;
                } catch (error) {
                    const isLast = (i === retries - 1);
                    const warnMsg = `Falha na requisição (${error.message}). ` + 
                        (isLast ? `Abortando após ${retries} tentativas.` : `Tentando novamente em ${delay/1000}s... (Tentativa ${i + 1}/${retries})`);
                    
                    logConsole(warnMsg, isLast ? 'error' : 'warn', fileId);
                    
                    if (isLast) {
                        throw error;
                    }
                    await new Promise(resolve => setTimeout(resolve, delay));
                }
            }
        }

        function logConsole(message, type = 'info', id = null) {
            const time = new Date().toLocaleTimeString();
            const line = document.createElement('div');
            line.className = `console-line ${type}`;
            line.innerHTML = `[${time}] ${message}`;
            mConsoleLog.appendChild(line);
            mConsoleLog.scrollTop = mConsoleLog.scrollHeight;

            if (id) {
                try {
                    const formData = new FormData();
                    formData.append('id', id);
                    formData.append('nivel', type);
                    formData.append('mensagem', message);
                    fetch('api_etl.php?action=log', {
                        method: 'POST',
                        body: formData
                    }).catch(err => console.error("Erro assíncrono ao salvar log no banco:", err));
                } catch (e) {
                    console.error("Falha ao salvar log no banco:", e);
                }
            }
        }

        function updateStepStatus(stepId, status) {
            const el = document.getElementById(`step-${stepId}`);
            const icon = document.getElementById(`step-icon-${stepId}`);
            
            // Remove previous classes
            el.classList.remove('active', 'success', 'error');
            
            if (status === 'active') {
                el.classList.add('active');
                icon.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i>`;
            } else if (status === 'success') {
                el.classList.add('success');
                icon.innerHTML = `<i class="fa-solid fa-circle-check"></i>`;
            } else if (status === 'error') {
                el.classList.add('error');
                icon.innerHTML = `<i class="fa-solid fa-circle-xmark"></i>`;
            }
        }

        async function updateGlobalStats() {
            try {
                const response = await fetch('api_etl.php?action=stats');
                const data = await response.json();
                if (data.success) {
                    document.getElementById('stats-total-files').textContent = new Intl.NumberFormat('pt-BR').format(data.total_files);
                    document.getElementById('stats-loaded-files').textContent = new Intl.NumberFormat('pt-BR').format(data.loaded_files);
                    document.getElementById('stats-total-rows').textContent = new Intl.NumberFormat('pt-BR').format(data.total_rows);
                    document.getElementById('stats-duplicate-rows').textContent = new Intl.NumberFormat('pt-BR').format(data.duplicate_rows);
                    document.getElementById('stats-failed-files').textContent = new Intl.NumberFormat('pt-BR').format(data.failed_files);
                }
            } catch (error) {
                console.error("Failed to update stats:", error);
            }
        }

        async function syncApiList() {
            const btn = btnSyncApi || btnSyncApiEmpty;
            const originalText = btn.innerHTML;
            const originalTitle = btn.getAttribute('title') || '';
            btn.disabled = true;
            btn.setAttribute('title', 'Sincronizando lista');
            btn.setAttribute('aria-label', 'Sincronizando lista');
            btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i>`;

            document.getElementById('etl-target-name').textContent = "Atualizando metadados da API pública do INSS";
            mConsoleLog.innerHTML = '';
            mBtnClose.disabled = true;
            etlModal.show();
            
            // Activate step 1 as api query
            document.getElementById('step-download').querySelector('span:nth-child(2)').textContent = "Sincronização: Consultando API CKAN...";
            updateStepStatus('download', 'active');
            logConsole("Conectando à API do Portal de Dados Abertos...", "info");

            try {
                const response = await fetch('api_etl.php?action=sync');
                const data = await response.json();

                if (data.success) {
                    updateStepStatus('download', 'success');
                    logConsole(`Concluído! ${data.inserted} novos arquivos descobertos, ${data.updated} atualizados no banco.`, "success");
                    updateStepStatus('extract', 'active');

                    const files = Array.isArray(data.files) ? data.files : [];
                    if (files.length > 0) {
                        logConsole(`Gerando documentação básica de ${files.length} arquivos...`, "info");
                    }

                    for (const file of files) {
                        logConsole(`Documentando: ${file.nome}`, "info");
                        const doc = await fetchWithRetry(`api_etl.php?action=document_file&id=${file.id}`, {}, 2, 1500);
                        logConsole(`Documentado: ${new Intl.NumberFormat('pt-BR').format(doc.total_registros)} registros, ${doc.total_campos} campos.`, "success");
                    }

                    updateStepStatus('extract', 'success');
                    updateStepStatus('load', 'success');
                    mBtnClose.disabled = false;
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    updateStepStatus('download', 'error');
                    logConsole(`Erro: ${data.error}`, "error");
                    mBtnClose.disabled = false;
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    btn.setAttribute('title', originalTitle);
                    btn.setAttribute('aria-label', originalTitle);
                }
            } catch (error) {
                updateStepStatus('download', 'error');
                logConsole(`Falha crítica na requisição: ${error.message}`, "error");
                mBtnClose.disabled = false;
                btn.disabled = false;
                btn.innerHTML = originalText;
                btn.setAttribute('title', originalTitle);
                btn.setAttribute('aria-label', originalTitle);
            }
        }

        async function triggerETL(id, nome, resume = false) {
            // Reset modal states
            mConsoleLog.innerHTML = '';
            mBtnClose.disabled = true;
            mProgressWrapper.classList.add('d-none');
            mProgressFill.style.width = '0%';
            mProgressText.textContent = '0%';
            
            // Restore default step text
            document.getElementById('step-download').querySelector('span:nth-child(2)').textContent = "Download: Baixando arquivo ZIP do S3 do governo...";
            
            // Set all steps to pending (removing status styles)
            ['download', 'extract', 'load', 'cleanup'].forEach(step => {
                const el = document.getElementById(`step-${step}`);
                el.classList.remove('active', 'success', 'error');
                const icon = document.getElementById(`step-icon-${step}`);
                icon.innerHTML = step === 'download' ? '1' : step === 'extract' ? '2' : step === 'load' ? '3' : '4';
            });

            document.getElementById('etl-target-name').textContent = nome;
            etlModal.show();

            const initMsg = resume ? `Retomando processamento de ETL de: ${nome}` : `Iniciando novo pipeline completo de ETL para: ${nome}`;
            logConsole(initMsg, "info", id);
            updateStepStatus('download', 'active');

            try {
                // Passo 1: Download & extract zip
                const step1Msg = resume ? "Verificando se arquivos temporários já estão descompactados no servidor..." : "Baixando arquivo ZIP para o diretório temporário...";
                logConsole(step1Msg, "info", id);
                
                const dataExt = await fetchWithRetry(`api_etl.php?action=download_extract&id=${id}&resume=${resume}`, {}, 3, 2000, id);

                const totalRows = dataExt.total_rows;
                updateStepStatus('download', 'success');
                updateStepStatus('extract', 'active');
                
                const step2Msg = resume ? "Arquivos temporários localizados e validados com sucesso." : "Arquivo ZIP baixado e extraído com sucesso.";
                logConsole(step2Msg, "success", id);
                logConsole("Validação de formato concluída. Arquivo JSON localizado e validado.", "success", id);
                logConsole(`Total de registros detectados no JSON: ${new Intl.NumberFormat('pt-BR').format(totalRows)}`, "info", id);
                
                updateStepStatus('extract', 'success');
                
                if (totalRows === 0) {
                    logConsole("O arquivo JSON está vazio. Concluindo sem carga.", "warn", id);
                    updateStepStatus('load', 'success');
                    updateStepStatus('cleanup', 'active');
                    await fetchWithRetry(`api_etl.php?action=cleanup&id=${id}`, {}, 3, 2000, id);
                    updateStepStatus('cleanup', 'success');
                    mBtnClose.disabled = false;
                    await updateGlobalStats();
                    return;
                }

                // Passo 2: Carga incremental
                updateStepStatus('load', 'active');
                mProgressWrapper.classList.remove('d-none');
                
                const limit = 1000;
                let offset = resume ? (parseInt(document.getElementById(`row-count-${id}`).textContent.replace(/\./g, '')) || 0) : 0;
                
                const percentageStart = Math.round((offset / totalRows) * 100);
                mProgressFill.style.width = `${percentageStart}%`;
                mProgressText.textContent = `${percentageStart}%`;

                const loadMsg = resume ? `Retomando gravação de registros a partir do offset ${new Intl.NumberFormat('pt-BR').format(offset)}...` : `Iniciando gravação de registros em lotes de 1.000 linhas...`;
                logConsole(loadMsg, "info", id);

                while (offset < totalRows) {
                    logConsole(`Ingerindo lote: registros ${new Intl.NumberFormat('pt-BR').format(offset)} a ${new Intl.NumberFormat('pt-BR').format(Math.min(offset + limit, totalRows))}...`, "info", id);
                    
                    const dataBatch = await fetchWithRetry(`api_etl.php?action=load_batch&id=${id}&offset=${offset}&limit=${limit}`, {}, 3, 2000, id);

                    offset += dataBatch.read_rows;
                    
                    const percentage = Math.round((offset / totalRows) * 100);
                    mProgressFill.style.width = `${percentage}%`;
                    mProgressText.textContent = `${percentage}%`;
                    
                    if (dataBatch.read_rows === 0) {
                        break; 
                    }
                }

                updateStepStatus('load', 'success');
                logConsole(`Carga de ${new Intl.NumberFormat('pt-BR').format(offset)} registros concluída com chaves normalizadas no PostgreSQL.`, "success", id);

                // Passo 3: Limpeza de Cache
                updateStepStatus('cleanup', 'active');
                logConsole("Removendo arquivos de cache locais (ZIP e JSON)...", "info", id);
                const dataClean = await fetchWithRetry(`api_etl.php?action=cleanup&id=${id}`, {}, 3, 2000, id);

                if (dataClean.success) {
                    updateStepStatus('cleanup', 'success');
                    logConsole("Diretório temporário limpo. Pipeline finalizado com sucesso!", "success", id);
                    mBtnClose.disabled = false;
                    await updateGlobalStats();
                    
                    // Reload page after a delay to update the table
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                }

            } catch (error) {
                logConsole(`[ETL ERRO] Falha crítica: ${error.message}`, "error", id);
                
                // Identify which step failed to color it red
                const activeStep = document.querySelector('.step-item.active');
                if (activeStep) {
                    const stepId = activeStep.id.replace('step-', '');
                    updateStepStatus(stepId, 'error');
                }
                
                mBtnClose.disabled = false;
                await updateGlobalStats();
            }
        }

        async function resetETL(id) {
        // Show confirmation using a Bootstrap modal instead of native confirm
        showConfirmModal('Tem certeza que deseja apagar todos os registros carregados deste arquivo? Esta ação é irreversível e removerá também o histórico de logs.', async () => {
            // User confirmed, proceed with reset
            mConsoleLog.innerHTML = '';
            mBtnClose.disabled = true;
            mProgressWrapper.classList.add('d-none');
            document.getElementById('etl-target-name').textContent = "Limpando Carga de Arquivo";
            etlModal.show();

            updateStepStatus('download', 'active');
            document.getElementById('step-download').querySelector('span:nth-child(2)').textContent = "Limpando banco de dados...";
            
            logConsole(`Iniciando exclusão de registros e logs do ID #${id}...`, "warn", id);

            try {
                const formData = new FormData();
                formData.append('id', id);
                const data = await fetchWithRetry('api_etl.php?action=reset', {
                    method: 'POST',
                    body: formData
                }, 3, 2000, id);

                if (data.success) {
                    updateStepStatus('download', 'success');
                    logConsole("Banco de dados e diretórios temporários limpos com sucesso.", "success", id);
                    mBtnClose.disabled = false;
                    await updateGlobalStats();
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                }
            } catch (error) {
                updateStepStatus('download', 'error');
                logConsole(`Falha ao limpar carga: ${error.message}`, "error", id);
                mBtnClose.disabled = false;
            }
        });
        }

        async function showLogHistory(id, nome) {
            const modalEl = document.getElementById('logHistoryModal');
            const targetNameEl = document.getElementById('log-history-target-name');
            const contentEl = document.getElementById('log-history-content');
            
            targetNameEl.textContent = nome;
            contentEl.innerHTML = '<div class="text-muted text-center py-4"><i class="fa-solid fa-spinner fa-spin me-2"></i>Carregando logs...</div>';
            
            const logModal = new bootstrap.Modal(modalEl);
            logModal.show();
            
            try {
                const response = await fetch(`api_etl.php?action=get_logs&id=${id}`);
                const data = await response.json();
                
                if (data.success && data.logs && data.logs.length > 0) {
                    contentEl.innerHTML = '';
                    data.logs.forEach(log => {
                        const line = document.createElement('div');
                        line.className = `console-line ${log.nivel}`;
                        line.innerHTML = `[${log.data_hora}] ${log.mensagem}`;
                        contentEl.appendChild(line);
                    });
                    contentEl.scrollTop = contentEl.scrollHeight;
                } else {
                    contentEl.innerHTML = '<div class="text-muted text-center py-4">Nenhum log registrado para este arquivo.</div>';
                }
            } catch (error) {
                contentEl.innerHTML = `<div class="console-line error">[ERRO] Falha ao carregar logs: ${error.message}</div>`;
            }
        }

        async function showFileInfo(id, nome) {
            const modalEl = document.getElementById('fileInfoModal');
            const targetNameEl = document.getElementById('file-info-target-name');
            const contentEl = document.getElementById('file-info-content');

            targetNameEl.textContent = nome;
            contentEl.innerHTML = '<div class="text-muted text-center py-4"><i class="fa-solid fa-spinner fa-spin me-2"></i>Carregando documentação...</div>';

            const infoModal = new bootstrap.Modal(modalEl);
            infoModal.show();

            try {
                const response = await fetch(`api_etl.php?action=file_info&id=${id}`);
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.error || 'Falha ao carregar documentação.');
                }

                const file = data.file;
                const fields = data.fields || [];
                const dateFields = data.date_fields || [];

                const fieldRows = fields.slice(0, 12).map(field => {
                    const formats = (field.formatos_data || []).join(', ') || '-';
                    const examples = (field.exemplos || []).join(' | ') || '-';
                    return `
                        <tr>
                            <td class="font-monospace text-light">${escapeHtml(field.campo)}</td>
                            <td class="text-end">${new Intl.NumberFormat('pt-BR').format(field.ocorrencias)}</td>
                            <td class="text-end">${new Intl.NumberFormat('pt-BR').format(field.preenchidos)}</td>
                            <td>${escapeHtml(formats)}</td>
                            <td class="text-muted">${escapeHtml(examples)}</td>
                        </tr>
                    `;
                }).join('');

                const dateSummary = dateFields.length > 0
                    ? dateFields.map(field => `${escapeHtml(field.campo)}: ${(field.formatos_data || []).map(escapeHtml).join(', ')}`).join('<br>')
                    : 'Nenhum formato de data detectado.';

                contentEl.innerHTML = `
                    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3 mb-4">
                        <div class="col">
                            <div class="glass-card p-3">
                                <div class="text-muted small">Registros no arquivo</div>
                                <div class="h5 mb-0 text-light">${new Intl.NumberFormat('pt-BR').format(file.total_registros_documentados || 0)}</div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="glass-card p-3">
                                <div class="text-muted small">Campos presentes</div>
                                <div class="h5 mb-0 text-light">${new Intl.NumberFormat('pt-BR').format(file.total_campos_documentados || fields.length)}</div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="glass-card p-3">
                                <div class="text-muted small">Registros duplicados</div>
                                <div class="h5 mb-0 text-warning">${new Intl.NumberFormat('pt-BR').format(file.duplicate_rows || 0)}</div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="glass-card p-3">
                                <div class="text-muted small">Atualizado em</div>
                                <div class="small text-light">${escapeHtml(file.documentacao_atualizada_em || 'Pendente')}</div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-secondary glass-card border border-secondary small">
                        <strong class="text-light">Formatos de data detectados</strong><br>${dateSummary}
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-dark table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Campo</th>
                                    <th class="text-end">Ocorrências</th>
                                    <th class="text-end">Preenchidos</th>
                                    <th>Formato de data</th>
                                    <th>Exemplos</th>
                                </tr>
                            </thead>
                            <tbody>${fieldRows || '<tr><td colspan="5" class="text-center text-muted py-4">Documentação pendente.</td></tr>'}</tbody>
                        </table>
                    </div>
                `;
            } catch (error) {
                contentEl.innerHTML = `<div class="console-line error">[ERRO] ${escapeHtml(error.message)}</div>`;
            }
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // ========================================================
        // CLIENT-SIDE TABLE SORTING LOGIC
        // ========================================================
        let currentSortCol = -1;
        let currentSortDesc = false;

        function sortTableByDate(colIndex) {
            const table = document.querySelector('.table-custom');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            // Toggle direction if clicking same column
            if (currentSortCol === colIndex) {
                currentSortDesc = !currentSortDesc;
            } else {
                currentSortCol = colIndex;
                currentSortDesc = true; // Default to descending order first
            }
            
            // Update icons
            const headers = table.querySelectorAll('thead th.sortable');
            headers.forEach(th => {
                const icon = th.querySelector('i');
                if (icon) {
                    icon.className = 'fa-solid fa-sort ms-1 text-muted small';
                }
            });
            
            // Set active icon
            const activeTh = colIndex === 3 ? document.getElementById('th-menor-data') : 
                             colIndex === 4 ? document.getElementById('th-maior-data') : 
                             document.getElementById('th-last-run');
            
            if (activeTh) {
                const icon = activeTh.querySelector('i');
                if (icon) {
                    icon.className = currentSortDesc ? 'fa-solid fa-sort-down ms-1 text-purple small' : 'fa-solid fa-sort-up ms-1 text-purple small';
                }
            }

            rows.sort((a, b) => {
                let cellA = a.cells[colIndex].textContent.trim();
                let cellB = b.cells[colIndex].textContent.trim();
                
                let valA, valB;
                
                if (colIndex === 5) {
                    // DateTime parsing: DD/MM/YYYY HH:MM or -
                    valA = parseDateTime(cellA);
                    valB = parseDateTime(cellB);
                } else {
                    // Date parsing: DD/MM/YYYY or -
                    valA = parseDate(cellA);
                    valB = parseDate(cellB);
                }
                
                // Keep '-' (val = 0) at the bottom in both directions
                if (valA.getTime() === 0 && valB.getTime() !== 0) return 1;
                if (valB.getTime() === 0 && valA.getTime() !== 0) return -1;
                if (valA.getTime() === 0 && valB.getTime() === 0) return 0;
                
                return currentSortDesc ? (valB - valA) : (valA - valB);
            });
            
            // Re-append rows in new order
            rows.forEach(row => tbody.appendChild(row));
        }

        function parseDate(str) {
            if (!str || str === '-') return new Date(0);
            const parts = str.split('/');
            if (parts.length !== 3) return new Date(0);
            return new Date(parts[2], parts[1] - 1, parts[0]);
        }

        function parseDateTime(str) {
            if (!str || str === '-') return new Date(0);
            const [datePart, timePart] = str.split(' ');
            const dParts = datePart.split('/');
            if (dParts.length !== 3) return new Date(0);
            const tParts = timePart ? timePart.split(':') : [0, 0];
            return new Date(dParts[2], dParts[1] - 1, dParts[0], tParts[0], tParts[1]);
        }
    </script>
</body>
</html>
