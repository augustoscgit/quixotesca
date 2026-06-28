<?php
/**
 * CAT - Visualizacao de registros
 */
require_once __DIR__ . '/../../cat/src/db.php';

$db_status = "Desconectado";
$db_error = null;
$total_files = 0;
$loaded_files = 0;
$total_rows = 0;
$failed_files = 0;
$files = [];

try {
    $db = getDBConnection();
    $db_status = "Conectado";
    
    // Fetch stats
    $total_files = (int)$db->query("SELECT COUNT(*) FROM arquivos_importacao")->fetchColumn();
    $loaded_files = (int)$db->query("SELECT COUNT(*) FROM arquivos_importacao WHERE situacao_carga = 'Carregado'")->fetchColumn();
    $total_rows = (int)$db->query("SELECT COUNT(*) FROM registros_brutos")->fetchColumn();
    $failed_files = (int)$db->query("SELECT COUNT(*) FROM arquivos_importacao WHERE situacao_extracao = 'Falhou' OR situacao_carga = 'Falhou'")->fetchColumn();

    // Fetch loaded files list for filter dropdown
    $stmt = $db->query("SELECT id, nome FROM arquivos_importacao WHERE linhas_processadas > 0 ORDER BY id DESC");
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
    <title>CAT - Registros de CAT</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/favicon.png">
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
        .inspect-position-input {
            background-color: transparent !important;
            border: 0 !important;
            color: #fff !important;
            box-shadow: none !important;
            outline: none !important;
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

        /* CAT Details Styling */
        .inspect-title {
            font-size: 1.1rem;
            font-weight: 600;
            border-bottom: 2px solid var(--accent-color);
            padding-bottom: 8px;
            margin-bottom: 20px;
            color: var(--text-color);
        }

        .inspect-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        .inspect-value {
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--text-color);
            background-color: var(--field-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 10px 14px;
            min-height: 43px;
            word-break: break-word;
        }

        .inspect-value-empty {
            color: var(--text-muted);
            font-style: italic;
        }
        .semantic-section {
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1.25rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 1rem;
        }
        .semantic-section > h6,
        .semantic-section > .row,
        .semantic-section .wide-field {
            grid-column: 1 / -1;
        }
        .semantic-section .mb-3,
        .company-profile-grid .mb-3 {
            margin-bottom: 0 !important;
        }
        .semantic-section + .semantic-section {
            padding-top: 0.25rem;
        }
        #inspect-content .col-12.mt-2:has(#val-cbo-cod):has(#val-cid-cod) {
            display: none !important;
        }

        .hash-line {
            min-width: 100%;
        }
        .hash-duplicate-badge {
            white-space: nowrap;
        }
        .extra-field-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }
        .extra-field-card {
            background-color: var(--field-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 10px 14px;
            min-height: 76px;
        }
        .extra-field-value {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-color);
            word-break: break-word;
        }
        .company-profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 1rem;
        }
        .company-profile-grid .wide-field {
            grid-column: 1 / -1;
        }
        .entity-link-row {
            display: flex;
            align-items: center;
            gap: .5rem;
            min-width: 0;
        }
        .entity-link-row .entity-value {
            min-width: 0;
            overflow-wrap: anywhere;
        }
        .opencnpj-status {
            align-self: flex-start;
            white-space: nowrap;
        }
        .raw-json-panel {
            background: var(--console-bg, #0f172a);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: #d1d5db;
            max-height: 420px;
            overflow: auto;
            padding: 1rem;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .skeleton-block {
            position: relative;
            overflow: hidden;
            background: rgba(148, 163, 184, 0.16);
            border-radius: 8px;
        }
        .skeleton-block::after {
            content: "";
            position: absolute;
            inset: 0;
            transform: translateX(-100%);
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.14), transparent);
            animation: skeleton-shimmer 1.35s infinite;
        }
        .skeleton-line {
            height: 12px;
            margin-bottom: 12px;
        }
        .skeleton-card {
            height: 72px;
        }
        @keyframes skeleton-shimmer {
            100% { transform: translateX(100%); }
        }

        /* Hover effect for navbar page navigation */
        .hover-purple {
            transition: color 0.2s ease;
        }
        .hover-purple:hover {
            color: #464B51 !important;
        }
    </style>
    <script src="../assets/js/theme-switcher.js"></script>
</head>
<body>

    <!-- Navbar -->
    <!-- Navbar -->
    <?php
    require_once __DIR__ . '/../includes/navbar.php';
    render_platform_navbar('cat', 'inspecao');
    ?>

    <!-- Main Container -->
    <main class="container py-5">
        
        <!-- Header Section -->
        <header class="row mb-4 align-items-center">
            <div class="col-md-9">
                <h1 class="display-5 text-purple mb-2" style="font-weight: 800; color: #464B51;">Registros de CAT</h1>
                <p class="lead text-secondary">
                    Faça filtros cruzados sobre as comunicações de acidentes de trabalho e navegue individualmente pelos registros brutos da CAT.
                </p>
            </div>
        </header>

        <!-- CAT Record Section -->
        <div class="row g-4">
            
            <!-- Sidebar: Filters -->
            <div class="col-lg-3">
                <div class="glass-card p-4">
                    <h5 class="mb-4 text-white-50"><i class="fa-solid fa-filter me-2"></i>Filtros de Busca</h5>
                    <form id="filter-form" onsubmit="event.preventDefault(); applyFilters();">
                        <div class="mb-3">
                            <label class="form-label inspect-label">Arquivo de Origem</label>
                            <select id="filter-file" class="form-select">
                                <option value="0">Todos os arquivos</option>
                                <?php foreach ($files as $f): ?>
                                    <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label inspect-label">CBO (Código/Nome)</label>
                            <input type="text" id="filter-cbo" class="form-control" placeholder="Ex: 515105 ou Enfermeiro">
                        </div>
                        <div class="mb-3">
                            <label class="form-label inspect-label">CID-10 (Código/Diagnóstico)</label>
                            <input type="text" id="filter-cid" class="form-control" placeholder="Ex: S62 ou Fratura">
                        </div>
                        <div class="mb-3">
                            <label class="form-label inspect-label">CNAE (Código/Atividade)</label>
                            <input type="text" id="filter-cnae" class="form-control" placeholder="Ex: 8610 ou Hospital">
                        </div>
                        <div class="mb-3">
                            <label class="form-label inspect-label">CNPJ do empregador</label>
                            <input type="text" id="filter-cnpj" class="form-control" placeholder="Raiz, matriz ou CNPJ completo">
                        </div>
                        <div class="mb-3">
                            <label class="form-label inspect-label">Sexo</label>
                            <select id="filter-sexo" class="form-select">
                                <option value="">Todos</option>
                                <option value="Masculino">Masculino</option>
                                <option value="Feminino">Feminino</option>
                                <option value="Não Informado">Não Informado</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label inspect-label">Tipo do Acidente</label>
                            <select id="filter-tipo" class="form-select">
                                <option value="">Todos</option>
                                <option value="Típico">Típico</option>
                                <option value="Doença">Doença</option>
                                <option value="Trajeto">Trajeto</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label inspect-label">Indica Óbito</label>
                            <select id="filter-obito" class="form-select">
                                <option value="">Todos</option>
                                <option value="Sim">Sim</option>
                                <option value="Não">Não</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label inspect-label">Estado (UF)</label>
                            <input type="text" id="filter-estado" list="states-list" class="form-control" placeholder="Digite ou selecione o estado...">
                            <datalist id="states-list"></datalist>
                        </div>
                        <div class="mb-3">
                            <label class="form-label inspect-label">Município</label>
                            <input type="text" id="filter-municipio" list="cities-list" class="form-control" placeholder="Digite ou selecione o município...">
                            <datalist id="cities-list"></datalist>
                        </div>
                        <div class="mb-3">
                            <label class="form-label inspect-label">Data Inicial Acidente</label>
                            <input type="date" id="filter-data-inicio" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label inspect-label">Data Final Acidente</label>
                            <input type="date" id="filter-data-fim" class="form-control">
                        </div>
                        
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" id="btn-inspect-filter" class="btn btn-purple btn-icon rounded-circle" title="Filtrar" aria-label="Filtrar">
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </button>
                            <button type="button" onclick="clearFilters()" class="btn btn-outline-secondary btn-icon rounded-circle" title="Limpar filtros" aria-label="Limpar filtros">
                                <i class="fa-solid fa-filter-circle-xmark"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Main Content: Record Viewer -->
            <div class="col-lg-9">
                <div class="glass-card p-4 h-100 d-flex flex-column">
                    
                    <!-- Search Results Summary Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 pb-3 border-bottom border-secondary">
                        <h4 class="mb-0 text-light"><i class="fa-solid fa-address-card text-purple me-2"></i>CAT individual</h4>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-purple-subtle text-purple border border-purple-subtle px-3 py-2 rounded-pill font-monospace" id="inspect-total-badge">0 registros encontrados</span>
                            <button type="button" id="btn-raw-json" class="btn btn-outline-secondary btn-icon rounded-circle" onclick="openRawJsonModal()" title="Mostrar JSON bruto" aria-label="Mostrar JSON bruto">
                                <i class="fa-solid fa-code"></i>
                            </button>
                        </div>
                    </div>

                    <div id="inspect-loading" class="d-none">
                        <div class="skeleton-block skeleton-line" style="width: 45%;"></div>
                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <div class="skeleton-block skeleton-card mb-3"></div>
                                <div class="skeleton-block skeleton-card mb-3"></div>
                                <div class="skeleton-block skeleton-card"></div>
                            </div>
                            <div class="col-md-6">
                                <div class="skeleton-block skeleton-card mb-3"></div>
                                <div class="skeleton-block skeleton-card mb-3"></div>
                                <div class="skeleton-block skeleton-card"></div>
                            </div>
                            <div class="col-12">
                                <div class="skeleton-block skeleton-card"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Record Content Card -->
                    <div id="inspect-content" class="flex-fill d-none">
                        
                        <!-- File Source Info -->
                        <div class="alert alert-secondary glass-card py-2 px-3 border border-secondary mb-4 small d-flex align-items-center flex-wrap gap-2">
                            <i class="fa-solid fa-file-csv text-muted me-2 fs-5"></i>
                            <span class="text-muted">Origem: <strong class="text-light" id="val-arquivo-origem">-</strong></span>
                            <span class="ms-auto text-muted">ID rastreável: <strong class="text-light font-monospace" id="val-registro-origem">-</strong></span>
                            <span class="hash-line text-muted d-flex align-items-center flex-wrap gap-2">
                                <span>Hash extended: <strong class="text-light font-monospace" id="val-hash-extended">-</strong></span>
                                <span id="val-duplicate-status" class="badge rounded-pill text-bg-secondary hash-duplicate-badge">Duplicidade não verificada</span>
                                <button type="button" id="btn-hash-duplicates" class="btn btn-outline-warning btn-sm btn-icon rounded d-none" onclick="showHashDuplicates()" title="Ver registros com o mesmo hash" aria-label="Ver registros com o mesmo hash">
                                    <i class="fa-solid fa-copy"></i>
                                </button>
                            </span>
                        </div>

                        <div class="row g-4 mb-4">
                            <!-- 1. Dados do trabalhador -->
                            <div class="col-12 semantic-section">
                                <h6 class="mb-3 text-purple" style="font-weight:600;"><i class="fa-solid fa-user me-2"></i>Dados do trabalhador</h6>
                                
                                <div class="mb-3">
                                    <div class="inspect-label">Sexo / Gênero</div>
                                    <div class="inspect-value" id="val-sexo">-</div>
                                </div>
                                <div class="mb-3">
                                    <div class="inspect-label">Data de Nascimento</div>
                                    <div class="inspect-value" id="val-data-nasc">-</div>
                                </div>
                                <div class="mb-3">
                                    <div class="inspect-label">Filiação do segurado</div>
                                    <div class="inspect-value" id="val-filiacao-segurado">-</div>
                                </div>
                                <div class="mb-3">
                                    <div class="inspect-label d-flex justify-content-between align-items-center">
                                        <span>CBO (OcupaÃ§Ã£o)</span>
                                        <button id="btn-cbo-hierarchy" class="btn btn-sm p-0 text-purple d-none" type="button" style="font-size: 0.65rem;" onclick="toggleCboHierarchy()" title="Mostrar/ocultar hierarquia CBO" aria-label="Mostrar/ocultar hierarquia CBO">
                                            <i class="fa-solid fa-sitemap"></i>
                                        </button>
                                    </div>
                                    <div class="inspect-value d-flex flex-column align-items-start gap-1">
                                        <span class="badge bg-purple-subtle text-purple border border-purple-subtle font-monospace me-1" id="val-cbo-cod">-</span>
                                        <span class="small" id="val-cbo-desc">-</span>
                                        <div id="cbo-hierarchy-container" class="d-none mt-2 pt-2 border-top border-secondary w-100" style="font-size: 0.75rem; line-height: 1.4;">
                                            <div class="mb-1 text-muted"><strong class="text-white-50">Grande Grupo:</strong> <span id="val-cbo-gg">-</span></div>
                                            <div class="mb-1 text-muted"><strong class="text-white-50">Subgrupo Principal:</strong> <span id="val-cbo-sp">-</span></div>
                                            <div class="mb-1 text-muted"><strong class="text-white-50">Subgrupo:</strong> <span id="val-cbo-sg">-</span></div>
                                            <div class="mb-1 text-muted"><strong class="text-white-50">FamÃ­lia:</strong> <span id="val-cbo-fa">-</span></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 2. Dados do acidente -->
                            <div class="col-12 semantic-section">
                                <h6 class="mb-3 text-purple" style="font-weight:600;"><i class="fa-solid fa-calendar-check me-2"></i>Dados do acidente</h6>
                                
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="mb-3">
                                            <div class="inspect-label">Data do Acidente</div>
                                            <div class="inspect-value font-monospace" id="val-data-acidente">-</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="mb-3">
                                            <div class="inspect-label">Hora do Acidente</div>
                                            <div class="inspect-value font-monospace" id="val-hora-acidente">-</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="inspect-label">Tipo do Acidente</div>
                                    <div class="inspect-value" id="val-tipo-acidente">-</div>
                                </div>
                                <div class="mb-3">
                                    <div class="inspect-label">Houve Óbito?</div>
                                    <div class="inspect-value" id="val-obito">-</div>
                                </div>
                                <div class="mb-3">
                                    <div class="inspect-label">Data de afastamento</div>
                                    <div class="inspect-value font-monospace" id="val-data-afastamento">-</div>
                                </div>
                                <div class="mb-3">
                                    <div class="inspect-label">Parte do corpo atingida</div>
                                    <div class="inspect-value" id="val-parte-corpo">-</div>
                                </div>
                                <div class="mb-3">
                                    <div class="inspect-label">Agente causador</div>
                                    <div class="inspect-value" id="val-agente-causador">-</div>
                                </div>
                                <div class="mb-3">
                                    <div class="inspect-label">Natureza da lesão</div>
                                    <div class="inspect-value" id="val-natureza-lesao">-</div>
                                </div>
                                <div class="mb-3">
                                    <div class="inspect-label d-flex justify-content-between align-items-center">
                                         <span>CID-10 (DiagnÃ³stico)</span>
                                         <button id="btn-cid-hierarchy" class="btn btn-sm p-0 text-purple d-none" type="button" style="font-size: 0.65rem;" onclick="toggleCidHierarchy()" title="Mostrar/ocultar hierarquia CID-10" aria-label="Mostrar/ocultar hierarquia CID-10">
                                             <i class="fa-solid fa-sitemap"></i>
                                         </button>
                                     </div>
                                     <div class="inspect-value d-flex flex-column align-items-start gap-1">
                                         <span class="badge bg-purple-subtle text-purple border border-purple-subtle font-monospace me-1" id="val-cid-cod">-</span>
                                         <span class="small" id="val-cid-desc">-</span>
                                         <div id="cid-hierarchy-container" class="d-none mt-2 pt-2 border-top border-secondary w-100" style="font-size: 0.75rem; line-height: 1.4;">
                                             <div class="mb-1 text-muted"><strong class="text-white-50">CapÃ­tulo:</strong> <span id="val-cid-cap">-</span></div>
                                             <div class="mb-1 text-muted"><strong class="text-white-50">Grupo:</strong> <span id="val-cid-grup">-</span></div>
                                             <div class="mb-1 text-muted"><strong class="text-white-50">Categoria:</strong> <span id="val-cid-cat">-</span></div>
                                         </div>
                                     </div>
                                </div>
                                <div class="mb-3">
                                    <div class="inspect-label">Local / Município do Empregador</div>
                                    <div class="inspect-value d-flex flex-column align-items-start gap-1">
                                        <span id="val-municipio-empregador">-</span>
                                        <span class="small text-muted" id="val-territorio-empregador">-</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="inspect-label">UF Empregador / UF Acidente</div>
                                    <div class="inspect-value" id="val-uf-empregador">-</div>
                                </div>
                            </div>

                            <!-- 3. Classificações relacionadas -->
                            <div class="col-12 mt-2">
                                <hr class="border-secondary my-4">
                                <h6 class="mb-3 text-purple" style="font-weight:600;"><i class="fa-solid fa-stethoscope me-2"></i>Códigos com dicionários</h6>
                                
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="inspect-label d-flex justify-content-between align-items-center">
                                            <span>CBO (Ocupação)</span>
                                            <button id="btn-cbo-hierarchy" class="btn btn-sm p-0 text-purple d-none" type="button" style="font-size: 0.65rem;" onclick="toggleCboHierarchy()" title="Mostrar/ocultar hierarquia CBO" aria-label="Mostrar/ocultar hierarquia CBO">
                                                <i class="fa-solid fa-sitemap"></i>
                                            </button>
                                        </div>
                                        <div class="inspect-value d-flex flex-column align-items-start gap-1">
                                            <span class="badge bg-purple-subtle text-purple border border-purple-subtle font-monospace me-1" id="val-cbo-cod">-</span>
                                            <span class="small" id="val-cbo-desc">-</span>
                                            
                                            <!-- Collapsible CBO Hierarchy Tree -->
                                            <div id="cbo-hierarchy-container" class="d-none mt-2 pt-2 border-top border-secondary w-100" style="font-size: 0.75rem; line-height: 1.4;">
                                                <div class="mb-1 text-muted"><strong class="text-white-50">Grande Grupo:</strong> <span id="val-cbo-gg">-</span></div>
                                                <div class="mb-1 text-muted"><strong class="text-white-50">Subgrupo Principal:</strong> <span id="val-cbo-sp">-</span></div>
                                                <div class="mb-1 text-muted"><strong class="text-white-50">Subgrupo:</strong> <span id="val-cbo-sg">-</span></div>
                                                <div class="mb-1 text-muted"><strong class="text-white-50">Família:</strong> <span id="val-cbo-fa">-</span></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="inspect-label">CNAE (Atividade)</div>
                                        <div class="inspect-value d-flex flex-column align-items-start gap-1">
                                            <span class="badge bg-purple-subtle text-purple border border-purple-subtle font-monospace me-1" id="val-cnae-cod">-</span>
                                            <span class="small" id="val-cnae-desc">-</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="inspect-label d-flex justify-content-between align-items-center">
                                             <span>CID-10 (Diagnóstico)</span>
                                             <button id="btn-cid-hierarchy" class="btn btn-sm p-0 text-purple d-none" type="button" style="font-size: 0.65rem;" onclick="toggleCidHierarchy()" title="Mostrar/ocultar hierarquia CID-10" aria-label="Mostrar/ocultar hierarquia CID-10">
                                                 <i class="fa-solid fa-sitemap"></i>
                                             </button>
                                         </div>
                                         <div class="inspect-value d-flex flex-column align-items-start gap-1">
                                             <span class="badge bg-purple-subtle text-purple border border-purple-subtle font-monospace me-1" id="val-cid-cod">-</span>
                                             <span class="small" id="val-cid-desc">-</span>
                                             
                                             <!-- Collapsible CID Hierarchy Tree -->
                                             <div id="cid-hierarchy-container" class="d-none mt-2 pt-2 border-top border-secondary w-100" style="font-size: 0.75rem; line-height: 1.4;">
                                                 <div class="mb-1 text-muted"><strong class="text-white-50">Capítulo:</strong> <span id="val-cid-cap">-</span></div>
                                                 <div class="mb-1 text-muted"><strong class="text-white-50">Grupo:</strong> <span id="val-cid-grup">-</span></div>
                                                 <div class="mb-1 text-muted"><strong class="text-white-50">Categoria:</strong> <span id="val-cid-cat">-</span></div>
                                             </div>
                                         </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 4. Grouped JSON fields -->
                            <div class="col-12 mt-2">
                                <hr class="border-secondary my-4">
                                <h6 class="mb-3 text-purple" style="font-weight:600;"><i class="fa-solid fa-building me-2"></i>Dados da empresa</h6>
                                <div class="company-profile-grid mb-3">
                                    <div>
                                        <div class="inspect-label">CNPJ do empregador</div>
                                        <div class="inspect-value entity-link-row">
                                            <span class="font-monospace entity-value" id="val-cnpj-empresa">-</span>
                                            <a id="btn-cnpj-page" class="btn btn-outline-purple btn-icon btn-sm d-none" href="#" title="Abrir pagina do CNPJ" aria-label="Abrir pagina do CNPJ">
                                                <i class="fa-solid fa-building-user"></i>
                                            </a>
                                            <button id="btn-cnpj-refresh" class="btn btn-outline-secondary btn-icon btn-sm d-none" type="button" onclick="refreshCurrentCompanyCnpj()" title="Atualizar OpenCNPJ" aria-label="Atualizar OpenCNPJ">
                                                <i class="fa-solid fa-cloud-arrow-down"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="inspect-label">Matriz</div>
                                        <div class="inspect-value entity-link-row">
                                            <span class="font-monospace entity-value" id="val-cnpj-matriz">-</span>
                                            <a id="btn-matriz-page" class="btn btn-outline-purple btn-icon btn-sm d-none" href="#" title="Abrir pagina da matriz" aria-label="Abrir pagina da matriz">
                                                <i class="fa-solid fa-sitemap"></i>
                                            </a>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="inspect-label">Filial</div>
                                        <div class="inspect-value font-monospace" id="val-cnpj-filial">-</div>
                                    </div>
                                    <div>
                                        <div class="inspect-label">Tipo de empregador</div>
                                        <div class="inspect-value" id="val-tipo-empregador">-</div>
                                    </div>
                                    <div class="wide-field">
                                        <div class="inspect-label">CNAE (Atividade)</div>
                                        <div class="inspect-value d-flex flex-column align-items-start gap-1">
                                            <span class="badge bg-purple-subtle text-purple border border-purple-subtle font-monospace me-1" id="val-cnae-cod">-</span>
                                            <span class="small" id="val-cnae-desc">-</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="company-profile-grid mb-3">
                                    <div class="wide-field d-flex justify-content-between align-items-center gap-2">
                                        <h6 class="mb-0 text-purple" style="font-weight:600;"><i class="fa-solid fa-cloud me-2"></i>Enriquecimento OpenCNPJ</h6>
                                        <span id="val-opencnpj-status" class="badge text-bg-secondary opencnpj-status">nao consultado</span>
                                    </div>
                                    <div class="wide-field">
                                        <div class="inspect-label">Razao social</div>
                                        <div class="inspect-value" id="val-opencnpj-razao">-</div>
                                    </div>
                                    <div>
                                        <div class="inspect-label">Nome fantasia</div>
                                        <div class="inspect-value" id="val-opencnpj-fantasia">-</div>
                                    </div>
                                    <div>
                                        <div class="inspect-label">Situacao cadastral</div>
                                        <div class="inspect-value" id="val-opencnpj-situacao">-</div>
                                    </div>
                                    <div>
                                        <div class="inspect-label">Municipio / UF</div>
                                        <div class="inspect-value" id="val-opencnpj-territorio">-</div>
                                    </div>
                                    <div>
                                        <div class="inspect-label">Ultima consulta</div>
                                        <div class="inspect-value font-monospace" id="val-opencnpj-consulta">-</div>
                                    </div>
                                </div>
                                <div id="company-fields-grid" class="extra-field-grid"></div>
                            </div>
                            <div class="col-12 mt-2">
                                <hr class="border-secondary my-4">
                                <h6 class="mb-3 text-purple" style="font-weight:600;"><i class="fa-solid fa-landmark me-2"></i>Dados da unidade administrativa</h6>
                                <div id="admin-fields-grid" class="extra-field-grid"></div>
                            </div>
                            <div class="col-12 mt-2">
                                <hr class="border-secondary my-4">
                                <h6 class="mb-3 text-purple" style="font-weight:600;"><i class="fa-solid fa-list-check me-2"></i>Outros</h6>
                                <div id="other-fields-grid" class="extra-field-grid"></div>
                            </div>
                            <div class="col-12 mt-2">
                                <hr class="border-secondary my-4">
                                <div class="d-flex align-items-center justify-content-center gap-3 bg-dark bg-opacity-25 px-3 py-2 rounded-pill border border-secondary border-opacity-10 shadow-sm mx-auto" style="max-width: 360px;">
                                    <button onclick="navigateRecord('first')" class="btn btn-link btn-sm p-0 text-white-50 hover-purple" title="Primeiro registro" aria-label="Primeiro registro">
                                        <i class="fa-solid fa-angles-left"></i>
                                    </button>
                                    <button onclick="navigateRecord('prev')" class="btn btn-link btn-sm p-0 text-white-50 hover-purple" title="Registro anterior" aria-label="Registro anterior">
                                        <i class="fa-solid fa-angle-left"></i>
                                    </button>
                                    <div class="d-flex align-items-center gap-1 font-monospace text-light small">
                                        <input type="text" class="form-control form-control-sm text-center p-0 fw-semibold inspect-position-input" id="inspect-current" style="width: 60px;" value="0">
                                        <span class="text-white-50">/</span>
                                        <span id="inspect-total" class="fw-semibold">0</span>
                                    </div>
                                    <button onclick="navigateRecord('next')" class="btn btn-link btn-sm p-0 text-white-50 hover-purple" title="Próximo registro" aria-label="Próximo registro">
                                        <i class="fa-solid fa-angle-right"></i>
                                    </button>
                                    <button onclick="navigateRecord('last')" class="btn btn-link btn-sm p-0 text-white-50 hover-purple" title="Último registro" aria-label="Último registro">
                                        <i class="fa-solid fa-angles-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Empty State -->
                    <div id="inspect-empty" class="text-center py-5 text-secondary my-auto">
                        <i class="fa-solid fa-magnifying-glass display-4 mb-3 d-block text-muted"></i>
                        <p class="mb-0">Nenhum registro correspondente aos filtros selecionados foi encontrado.</p>
                    </div>

                </div>
            </div>

        </div>

    </main>

    <div class="modal fade" id="rawJsonModal" tabindex="-1" aria-labelledby="rawJsonModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content glass-card border border-secondary">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-purple" id="rawJsonModalLabel"><i class="fa-solid fa-code me-2"></i>JSON bruto da CAT</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" title="Fechar" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <pre id="raw-json-panel" class="raw-json-panel mb-0"></pre>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary btn-icon rounded-circle" data-bs-dismiss="modal" title="Fechar" aria-label="Fechar">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Javascript Actions and AJAX CAT Record Engine -->
    <script>
        let currentOffset = 0;
        let totalRecords = 0;
        let activeHashFilter = '';
        let activeRecordOriginFilter = '';
        let currentHashForDuplicates = '';
        let currentRawRecord = null;
        let currentCompanyCnpj = '';

        document.addEventListener('DOMContentLoaded', function () {
            removeLegacyDictionaryBlock();
            loadStatesList();
            loadCitiesList();
            const urlCnpj = new URLSearchParams(window.location.search).get('cnpj');
            if (urlCnpj) {
                document.getElementById('filter-cnpj').value = urlCnpj;
            }
            const urlRegistro = new URLSearchParams(window.location.search).get('registro');
            if (urlRegistro) {
                activeRecordOriginFilter = urlRegistro;
                currentOffset = 0;
                loadCatRecord();
            } else {
                applyFilters();
            }
        });

        function removeLegacyDictionaryBlock() {
            const cboNodes = document.querySelectorAll('[id="val-cbo-cod"]');
            cboNodes.forEach((node, index) => {
                if (index === 0) return;
                const legacyBlock = node.closest('.col-12.mt-2');
                if (legacyBlock) legacyBlock.remove();
            });
        }

        function applyFilters() {
            activeHashFilter = '';
            activeRecordOriginFilter = '';
            currentOffset = 0;
            loadCatRecord();
        }

        function resetFilterControls() {
            document.getElementById('filter-file').value = '0';
            document.getElementById('filter-cbo').value = '';
            document.getElementById('filter-cid').value = '';
            document.getElementById('filter-cnae').value = '';
            document.getElementById('filter-cnpj').value = '';
            document.getElementById('filter-sexo').value = '';
            document.getElementById('filter-tipo').value = '';
            document.getElementById('filter-obito').value = '';
            document.getElementById('filter-estado').value = '';
            document.getElementById('filter-municipio').value = '';
            document.getElementById('filter-data-inicio').value = '';
            document.getElementById('filter-data-fim').value = '';
        }

        function clearFilters() {
            resetFilterControls();
            activeHashFilter = '';
            applyFilters();
        }

        function showHashDuplicates() {
            if (!currentHashForDuplicates) return;
            resetFilterControls();
            activeRecordOriginFilter = '';
            activeHashFilter = currentHashForDuplicates;
            currentOffset = 0;
            loadCatRecord();
        }

        function navigateRecord(direction) {
            if (totalRecords === 0) return;
            if (direction === 'first') currentOffset = 0;
            else if (direction === 'prev') currentOffset = Math.max(0, currentOffset - 1);
            else if (direction === 'next') currentOffset = Math.min(totalRecords - 1, currentOffset + 1);
            else if (direction === 'last') currentOffset = totalRecords - 1;
            
            loadCatRecord();
        }

        function htmlspecialchars(str) {
            if (typeof str !== 'string') return '';
            return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        function fallbackText(val) {
            const text = val === null || val === undefined ? '' : String(val);
            return text.trim() !== '' ? htmlspecialchars(text) : '<span class="inspect-value-empty">Não informado</span>';
        }

        // Handle typing directly in index box
        document.getElementById('inspect-current').addEventListener('change', (e) => {
            let val = parseInt(e.target.value);
            if (isNaN(val) || val < 1) val = 1;
            if (val > totalRecords) val = totalRecords;
            currentOffset = val - 1;
            loadCatRecord();
        });

        async function loadCatRecord() {
            setCatLoading(true);
            const fileId = document.getElementById('filter-file').value;
            const cbo = document.getElementById('filter-cbo').value;
            const cid = document.getElementById('filter-cid').value;
            const cnae = document.getElementById('filter-cnae').value;
            const cnpj = document.getElementById('filter-cnpj').value;
            const sexo = document.getElementById('filter-sexo').value;
            const tipo = document.getElementById('filter-tipo').value;
            const obito = document.getElementById('filter-obito').value;
            const estado = document.getElementById('filter-estado').value;
            const municipio = document.getElementById('filter-municipio').value;
            const dataInicio = document.getElementById('filter-data-inicio').value;
            const dataFim = document.getElementById('filter-data-fim').value;

            const url = `api_etl.php?action=query_records&offset=${currentOffset}` +
                `&arquivo_id=${fileId}&cbo=${encodeURIComponent(cbo)}&cid=${encodeURIComponent(cid)}` +
                `&cnae=${encodeURIComponent(cnae)}&cnpj=${encodeURIComponent(cnpj)}&sexo=${encodeURIComponent(sexo)}` +
                `&tipo=${encodeURIComponent(tipo)}&obito=${encodeURIComponent(obito)}` +
                `&estado=${encodeURIComponent(estado)}&municipio=${encodeURIComponent(municipio)}` +
                `&data_inicio=${dataInicio}&data_fim=${dataFim}` +
                `&hash_extended=${encodeURIComponent(activeHashFilter)}` +
                `&registro_origem_id=${encodeURIComponent(activeRecordOriginFilter)}`;

            try {
                const response = await fetch(url);
                const data = await response.json();

                if (data.success) {
                    totalRecords = data.total;
                    
                    // Update paginator numbers in the header navbar
                    document.getElementById('inspect-total').textContent = new Intl.NumberFormat('pt-BR').format(totalRecords);
                    document.getElementById('inspect-current').value = totalRecords > 0 ? (currentOffset + 1) : 0;
                    document.getElementById('inspect-total-badge').textContent = `${new Intl.NumberFormat('pt-BR').format(totalRecords)} registros encontrados`;

                    const contentArea = document.getElementById('inspect-content');
                    const emptyArea = document.getElementById('inspect-empty');

                    if (totalRecords > 0 && data.record) {
                        contentArea.classList.remove('d-none');
                        emptyArea.classList.add('d-none');

                        const rec = data.record;
                        currentRawRecord = rec;
                        
                        // Set basic fields (with fallback for empty values)
                        const fallbackText = (val) => {
                            const text = val === null || val === undefined ? '' : String(val);
                            return text.trim() !== '' ? htmlspecialchars(text) : '<span class="inspect-value-empty">Não informado</span>';
                        };
                        document.getElementById('val-arquivo-origem').textContent = data.arquivo_nome;
                        document.getElementById('val-registro-origem').textContent = rec._registro_origem_id || '-';
                        document.getElementById('val-hash-extended').textContent = rec._hash_extended || '-';
                        updateDuplicateStatus(rec._hash_extended || '', data.duplicate_total || 0);
                        document.getElementById('val-sexo').innerHTML = fallbackText(rec.sexo);
                        document.getElementById('val-data-nasc').innerHTML = fallbackText(rec.data_nascimento);
                        document.getElementById('val-filiacao-segurado').innerHTML = fallbackText(rec.filiacao_segurado);
                        document.getElementById('val-parte-corpo').innerHTML = fallbackText(rec.parte_corpo_atingida);
                        document.getElementById('val-agente-causador').innerHTML = fallbackText(rec.agente_causador_acidente || rec.agente_causador);
                        document.getElementById('val-natureza-lesao').innerHTML = fallbackText(rec.natureza_da_lesao);
                        document.getElementById('val-data-acidente').innerHTML = fallbackText(rec.data_acidente);
                        document.getElementById('val-hora-acidente').innerHTML = fallbackText(rec.hora_acidente);
                        document.getElementById('val-tipo-acidente').innerHTML = fallbackText(rec.tipo_do_acidente);
                        document.getElementById('val-obito').innerHTML = fallbackText(rec.indica_obito_acidente || rec.indica_bito_acidente);
                        document.getElementById('val-data-afastamento').innerHTML = fallbackText(rec.data_afastamento);
                        document.getElementById('val-municipio-empregador').innerHTML = fallbackText(rec.munic_empr);
                        const territory = rec.territorio_empregador_enriched;
                        const territoryText = territory
                            ? [
                                territory.codigo_municipio ? `IBGE ${territory.codigo_municipio}` : '',
                                territory.municipio || '',
                                territory.uf || '',
                                territory.regiao || ''
                            ].filter(Boolean).join(' · ')
                            : '';
                        document.getElementById('val-territorio-empregador').innerHTML = fallbackText(territoryText);
                        document.getElementById('val-uf-empregador').innerHTML = fallbackText(
                            (rec.uf_munic_empregador || '-') + ' / ' + (rec.uf_munic_acidente || '-')
                        );

                        // CBO code & description (with hierarchy lookup fallback)
                        document.getElementById('val-cbo-cod').textContent = rec.cbo || '-';
                        
                        const enriched = rec.cbo_enriched;
                        const btnHierarchy = document.getElementById('btn-cbo-hierarchy');
                        const hierarchyContainer = document.getElementById('cbo-hierarchy-container');
                        
                        // Always collapse hierarchy when loading a new record
                        if (hierarchyContainer) hierarchyContainer.classList.add('d-none');
                        
                        if (enriched) {
                            document.getElementById('val-cbo-desc').innerHTML = fallbackText(enriched.ocupacao || rec.cbo_1 || rec.cbo_desc);
                            document.getElementById('val-cbo-gg').textContent = enriched.grande_grupo || '-';
                            document.getElementById('val-cbo-sp').textContent = enriched.subgrupo_principal || '-';
                            document.getElementById('val-cbo-sg').textContent = enriched.subgrupo || '-';
                            document.getElementById('val-cbo-fa').textContent = enriched.familia || '-';
                            if (btnHierarchy) btnHierarchy.classList.remove('d-none');
                        } else {
                            document.getElementById('val-cbo-desc').innerHTML = fallbackText(rec.cbo_1 || rec.cbo_desc);
                            if (btnHierarchy) btnHierarchy.classList.add('d-none');
                        }

                        // CNAE code & description
                        document.getElementById('val-cnae-cod').textContent = rec.cnae2_0_empregador || '-';
                        document.getElementById('val-cnae-desc').innerHTML = fallbackText(rec.cnae2_0_empregador_1 || rec.cnae_desc);
                        updateCompanyIdentity(rec);

                        // CID code & description (with hierarchy lookup fallback)
                        document.getElementById('val-cid-cod').textContent = rec.cid_10 || '-';
                        
                        const cidEnriched = rec.cid_enriched;
                        const btnCidHierarchy = document.getElementById('btn-cid-hierarchy');
                        const cidHierarchyContainer = document.getElementById('cid-hierarchy-container');
                        
                        // Always collapse hierarchy when loading a new record
                        if (cidHierarchyContainer) cidHierarchyContainer.classList.add('d-none');
                        
                        if (cidEnriched) {
                            document.getElementById('val-cid-desc').innerHTML = fallbackText(cidEnriched.subcategoria || cidEnriched.categoria || rec.cid_10_1 || rec.cid_desc);
                            document.getElementById('val-cid-cap').textContent = cidEnriched.capitulo || '-';
                            document.getElementById('val-cid-grup').textContent = cidEnriched.grupo || '-';
                            document.getElementById('val-cid-cat').textContent = cidEnriched.categoria || '-';
                            if (btnCidHierarchy) btnCidHierarchy.classList.remove('d-none');
                        } else {
                            document.getElementById('val-cid-desc').innerHTML = fallbackText(rec.cid_10_1 || rec.cid_desc);
                            if (btnCidHierarchy) btnCidHierarchy.classList.add('d-none');
                        }

                        renderExtraFields(rec);
                        renderRawJson();

                    } else {
                        contentArea.classList.add('d-none');
                        emptyArea.classList.remove('d-none');
                        updateDuplicateStatus('', 0);
                        currentRawRecord = null;
                        renderExtraFields(null);
                        updateCompanyIdentity(null);
                        renderRawJson();
                    }
                }
            } catch (error) {
                console.error("Erro ao carregar registro de CAT:", error);
                const emptyArea = document.getElementById('inspect-empty');
                if (emptyArea) {
                    emptyArea.classList.remove('d-none');
                    emptyArea.innerHTML = '<i class="fa-solid fa-triangle-exclamation display-4 mb-3 d-block text-danger"></i><p class="mb-0">Não foi possível carregar o registro solicitado.</p>';
                }
            } finally {
                setCatLoading(false);
            }
        }

        function setCatLoading(isLoading) {
            const loading = document.getElementById('inspect-loading');
            const contentArea = document.getElementById('inspect-content');
            const emptyArea = document.getElementById('inspect-empty');
            const filterButton = document.getElementById('btn-inspect-filter');

            if (loading) loading.classList.toggle('d-none', !isLoading);
            if (contentArea && isLoading) contentArea.classList.add('d-none');
            if (emptyArea && isLoading) emptyArea.classList.add('d-none');

            if (!filterButton) return;
            filterButton.disabled = isLoading;
            filterButton.setAttribute('title', isLoading ? 'Consultando registros' : 'Filtrar');
            filterButton.setAttribute('aria-label', isLoading ? 'Consultando registros' : 'Filtrar');
            filterButton.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i>';
        }

        function openRawJsonModal() {
            const panel = document.getElementById('raw-json-panel');
            const modalEl = document.getElementById('rawJsonModal');
            if (!panel || !modalEl) return;
            renderRawJson();
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        function renderRawJson() {
            const panel = document.getElementById('raw-json-panel');
            if (!panel) return;
            panel.textContent = currentRawRecord ? JSON.stringify(currentRawRecord, null, 2) : '';
        }

        function normalizeCnpj(value) {
            return String(value || '').replace(/\D+/g, '');
        }

        function formatCnpj(value) {
            const digits = normalizeCnpj(value);
            if (digits.length !== 14) return digits || '-';
            return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8, 12)}-${digits.slice(12)}`;
        }

        function setTextValue(id, value) {
            const el = document.getElementById(id);
            if (el) el.innerHTML = fallbackText(value);
        }

        function resetCompanyEnrichment(status = 'nao consultado') {
            setTextValue('val-opencnpj-razao', '');
            setTextValue('val-opencnpj-fantasia', '');
            setTextValue('val-opencnpj-situacao', '');
            setTextValue('val-opencnpj-territorio', '');
            setTextValue('val-opencnpj-consulta', '');
            const badge = document.getElementById('val-opencnpj-status');
            if (badge) {
                badge.className = 'badge text-bg-secondary opencnpj-status';
                badge.textContent = status;
            }
        }

        function applyCompanyEnrichment(item) {
            if (!item) {
                resetCompanyEnrichment('sem cache');
                return;
            }
            setTextValue('val-opencnpj-razao', item.razao_social || '');
            setTextValue('val-opencnpj-fantasia', item.nome_fantasia || '');
            setTextValue('val-opencnpj-situacao', item.situacao || '');
            setTextValue('val-opencnpj-territorio', [item.municipio, item.uf].filter(Boolean).join(' / '));
            setTextValue('val-opencnpj-consulta', item.consultado_em || '');
            const badge = document.getElementById('val-opencnpj-status');
            if (badge) {
                const fresh = item.is_fresh !== false;
                badge.className = `badge ${fresh ? 'text-bg-success' : 'text-bg-warning'} opencnpj-status`;
                badge.textContent = item.source === 'api' ? 'API atualizada' : (fresh ? 'cache valido' : 'cache expirado');
            }
        }

        async function loadCompanyEnrichment(cnpj, force = false) {
            if (!cnpj || cnpj.length !== 14) {
                resetCompanyEnrichment('CNPJ invalido');
                return;
            }

            const badge = document.getElementById('val-opencnpj-status');
            if (badge) {
                badge.className = 'badge text-bg-secondary opencnpj-status';
                badge.textContent = force ? 'atualizando...' : 'consultando cache...';
            }

            try {
                if (!force) {
                    const cacheResponse = await fetch(`api_etl.php?action=cnpj_cache_status&cnpjs=${encodeURIComponent(cnpj)}`);
                    const cacheData = await cacheResponse.json();
                    const cached = cacheData?.cache?.[cnpj] || null;
                    if (cached) {
                        applyCompanyEnrichment(cached);
                        return;
                    }
                }

                const response = await fetch('api_etl.php?action=fetch_opencnpj', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ cnpj, force, allow_stale: true }),
                });
                const data = await response.json();
                if (!data.success) throw new Error(data.error || 'Falha ao consultar OpenCNPJ.');
                applyCompanyEnrichment(data.item || null);
            } catch (error) {
                console.error('Erro ao enriquecer CNPJ:', error);
                const badge = document.getElementById('val-opencnpj-status');
                if (badge) {
                    badge.className = 'badge text-bg-danger opencnpj-status';
                    badge.textContent = 'erro';
                }
            }
        }

        function refreshCurrentCompanyCnpj() {
            if (currentCompanyCnpj) loadCompanyEnrichment(currentCompanyCnpj, true);
        }

        function updateCompanyIdentity(record) {
            currentCompanyCnpj = normalizeCnpj(record?.cnpj_cei_empregador || '');
            const cnpjPage = document.getElementById('btn-cnpj-page');
            const matrixPage = document.getElementById('btn-matriz-page');
            const refreshButton = document.getElementById('btn-cnpj-refresh');
            const matrix = currentCompanyCnpj.length >= 8 ? currentCompanyCnpj.slice(0, 8) : '';
            const branch = currentCompanyCnpj.length >= 12 ? currentCompanyCnpj.slice(8, 12) : '';

            setTextValue('val-cnpj-empresa', currentCompanyCnpj.length === 14 ? formatCnpj(currentCompanyCnpj) : (record?.cnpj_cei_empregador || ''));
            setTextValue('val-cnpj-matriz', matrix);
            setTextValue('val-cnpj-filial', branch);
            setTextValue('val-tipo-empregador', record?.tipo_de_empregador || '');

            [cnpjPage, matrixPage, refreshButton].forEach(el => el?.classList.add('d-none'));
            if (currentCompanyCnpj.length === 14) {
                if (cnpjPage) {
                    cnpjPage.href = `cnpj.php?cnpj=${encodeURIComponent(currentCompanyCnpj)}`;
                    cnpjPage.classList.remove('d-none');
                }
                if (refreshButton) refreshButton.classList.remove('d-none');
                loadCompanyEnrichment(currentCompanyCnpj, false);
            } else {
                resetCompanyEnrichment('CNPJ ausente');
            }
            if (matrix.length === 8 && matrixPage) {
                matrixPage.href = `matriz.php?matriz=${encodeURIComponent(matrix)}`;
                matrixPage.classList.remove('d-none');
            }
        }

        function renderExtraFields(record) {
            const grids = {
                company: document.getElementById('company-fields-grid'),
                admin: document.getElementById('admin-fields-grid'),
                other: document.getElementById('other-fields-grid'),
            };
            Object.values(grids).forEach(grid => {
                if (grid) grid.innerHTML = '';
            });
            if (!record) return;

            const representedFields = new Set([
                'sexo',
                'data_nascimento',
                'filiacao_segurado',
                'parte_corpo_atingida',
                'agente_causador',
                'agente_causador_acidente',
                'natureza_da_lesao',
                'data_acidente',
                'data_afastamento',
                'hora_acidente',
                'tipo_do_acidente',
                'indica_obito_acidente',
                'indica_bito_acidente',
                'munic_empr',
                'uf_munic_empregador',
                'uf_munic_acidente',
                'cnpj_cei_empregador',
                'tipo_de_empregador',
                'cbo',
                'cbo_1',
                'cid_10',
                'cid_10_1',
                'cnae2_0_empregador',
                'cnae2_0_empregador_1',
                'data_acidente_1',
            ]);

            const labels = {
                cnpj_cei_empregador: 'CNPJ/CEI do empregador',
                data_afastamento: 'Data de afastamento',
                data_despacho_beneficio: 'Data do despacho do benefício',
                data_emissao_cat: 'Data de emissão da CAT',
                emitente_cat: 'Emitente da CAT',
                especie_do_beneficio: 'Espécie do benefício',
                filiacao_segurado: 'Filiação do segurado',
                origem_de_cadastramento_cat: 'Origem de cadastramento da CAT',
                tipo_de_empregador: 'Tipo de empregador',
            };
            const groups = {
                company: new Set([
                    'cnpj_cei_empregador',
                    'tipo_de_empregador',
                ]),
                admin: new Set([
                    'emitente_cat',
                    'origem_de_cadastramento_cat',
                    'especie_do_beneficio',
                    'data_emissao_cat',
                    'data_despacho_beneficio',
                ]),
                other: new Set([
                ]),
            };

            const escapeHtml = (value) => String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
            const formatLabel = (key) => labels[key] || key
                .replace(/^_+/, '')
                .replace(/_/g, ' ')
                .replace(/\b\w/g, char => char.toUpperCase());
            const isEmpty = (value) => value === null || value === undefined || String(value).trim() === '';
            const isInternal = (key, value) => key.startsWith('_') || key.endsWith('_enriched') || typeof value === 'object';

            const entries = Object.entries(record)
                .filter(([key, value]) => !isInternal(key, value))
                .filter(([key]) => !representedFields.has(key));

            const renderCard = ([key, value]) => {
                const valueHtml = isEmpty(value)
                    ? '<span class="inspect-value-empty">Não informado</span>'
                    : escapeHtml(value);
                return `
                    <div class="extra-field-card">
                        <div class="inspect-label">${escapeHtml(formatLabel(key))}</div>
                        <div class="extra-field-value">${valueHtml}</div>
                    </div>
                `;
            };

            const grouped = { company: [], admin: [], other: [] };
            entries.forEach(entry => {
                const [key] = entry;
                if (groups.company.has(key)) grouped.company.push(entry);
                else if (groups.admin.has(key)) grouped.admin.push(entry);
                else grouped.other.push(entry);
            });

            Object.entries(grouped).forEach(([group, groupEntries]) => {
                const grid = grids[group];
                if (!grid) return;
                grid.innerHTML = groupEntries.length > 0
                    ? groupEntries.map(renderCard).join('')
                    : '<div class="text-muted small">Sem campos adicionais não duplicados neste grupo.</div>';
            });
        }

        function updateDuplicateStatus(hash, duplicateTotal) {
            const status = document.getElementById('val-duplicate-status');
            const button = document.getElementById('btn-hash-duplicates');
            currentHashForDuplicates = hash || '';

            if (!status || !button) return;

            status.className = 'badge rounded-pill hash-duplicate-badge ';
            button.classList.add('d-none');

            if (!hash) {
                status.className += 'text-bg-secondary';
                status.textContent = 'Hash indisponível';
                return;
            }

            if (duplicateTotal > 1) {
                status.className += 'text-bg-warning';
                status.textContent = `${new Intl.NumberFormat('pt-BR').format(duplicateTotal)} ocorrências com o mesmo hash`;
                button.classList.remove('d-none');
                return;
            }

            status.className += 'text-bg-success';
            status.textContent = 'Sem duplicidade';
        }

        async function loadStatesList() {
            try {
                const response = await fetch('api_etl.php?action=get_states');
                const data = await response.json();
                if (data.success) {
                    const datalist = document.getElementById('states-list');
                    if (datalist) {
                        datalist.innerHTML = '';
                        data.states.forEach(state => {
                            const opt = document.createElement('option');
                            opt.value = state;
                            datalist.appendChild(opt);
                        });
                    }
                }
            } catch (error) {
                console.error("Failed to load states:", error);
            }
        }

        async function loadCitiesList() {
            const estadoInput = document.getElementById('filter-estado');
            const estado = estadoInput ? estadoInput.value : '';
            try {
                const response = await fetch(`api_etl.php?action=get_cities&estado=${encodeURIComponent(estado)}`);
                const data = await response.json();
                if (data.success) {
                    const datalist = document.getElementById('cities-list');
                    if (datalist) {
                        datalist.innerHTML = '';
                        data.cities.forEach(city => {
                            const opt = document.createElement('option');
                            opt.value = city;
                            datalist.appendChild(opt);
                        });
                    }
                }
            } catch (error) {
                console.error("Failed to load cities:", error);
            }
        }

        const estadoEl = document.getElementById('filter-estado');
        if (estadoEl) {
            estadoEl.addEventListener('input', loadCitiesList);
        }

        function toggleCboHierarchy() {
            const container = document.getElementById('cbo-hierarchy-container');
            if (container) {
                container.classList.toggle('d-none');
            }
        }

        function toggleCidHierarchy() {
            const container = document.getElementById('cid-hierarchy-container');
            if (container) {
                container.classList.toggle('d-none');
            }
        }
    </script>
</body>
</html>
