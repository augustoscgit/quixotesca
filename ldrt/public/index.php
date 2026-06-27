<?php
require_once __DIR__ . '/../src/db.php';

// Initialize variables
$selected_cid = isset($_GET['cid']) ? trim($_GET['cid']) : '';
$selected_cnae = isset($_GET['cnae']) ? trim($_GET['cnae']) : '';
$selected_cbo = isset($_GET['cbo']) ? trim($_GET['cbo']) : '';
$selected_agente = isset($_GET['agente']) ? trim($_GET['agente']) : '';

$cid_data = null;
$cnae_data = null;
$cbo_data = null;
$agente_data = null;

$related_agents = [];
$related_cids = [];
$matching_relatos = [];

$has_search = (!empty($selected_cid) || !empty($selected_cnae) || !empty($selected_cbo) || !empty($selected_agente));

if ($has_search) {
    try {
        $db = getDBConnection();

        // 1. Fetch details of selected entities
        if (!empty($selected_cid)) {
            $stmt = $db->prepare("SELECT * FROM cid WHERE codigo = :code");
            $stmt->execute(['code' => $selected_cid]);
            $cid_data = $stmt->fetch();
        }
        
        if (!empty($selected_cnae)) {
            $stmt = $db->prepare("SELECT * FROM cnae_cbo WHERE classificacao = 'cnae' AND codigo = :code");
            $stmt->execute(['code' => $selected_cnae]);
            $cnae_data = $stmt->fetch();
        }
        
        if (!empty($selected_cbo)) {
            $stmt = $db->prepare("SELECT * FROM cnae_cbo WHERE classificacao = 'cbo' AND codigo = :code");
            $stmt->execute(['code' => $selected_cbo]);
            $cbo_data = $stmt->fetch();
        }
        
        if (!empty($selected_agente)) {
            $stmt = $db->prepare("SELECT * FROM agentes WHERE id = :id");
            $stmt->execute(['id' => $selected_agente]);
            $agente_data = $stmt->fetch();
        }

        // 2. Fetch relations
        // If CID selected, get related agents
        if ($cid_data) {
            $stmt = $db->prepare("
                SELECT a.id, a.descricao, a.cas 
                FROM agentes a 
                JOIN agente_cid ac ON ac.agente_id = a.id 
                WHERE ac.cid_id = :cid_id
                ORDER BY a.descricao ASC
            ");
            $stmt->execute(['cid_id' => $cid_data['id']]);
            $related_agents = $stmt->fetchAll();
        }

        // If Agent selected, get related CIDs
        if ($agente_data) {
            $stmt = $db->prepare("
                SELECT c.codigo, c.descricao, c.nivel 
                FROM cid c 
                JOIN agente_cid ac ON ac.cid_id = c.id 
                WHERE ac.agente_id = :agente_id
                ORDER BY c.codigo ASC
            ");
            $stmt->execute(['agente_id' => $agente_data['id']]);
            $related_cids = $stmt->fetchAll();
        }

        // 3. Fetch matching Relatos (Case Reports)
        $relato_sql = "
            SELECT r.*, 
                   c.codigo as cid_codigo, c.descricao as cid_descricao, 
                   a.descricao as agente_descricao, 
                   cc.codigo as cnae_cbo_codigo, cc.descricao as cnae_cbo_descricao, cc.classificacao
            FROM relatos r
            LEFT JOIN cid c ON r.cid_id = c.id
            LEFT JOIN agentes a ON r.agente_id = a.id
            LEFT JOIN cnae_cbo cc ON r.cnae_cbo_id = cc.id
            WHERE 1=1
        ";
        $relato_params = [];
        
        if ($cid_data) {
            $relato_sql .= " AND r.cid_id = :cid_id";
            $relato_params['cid_id'] = $cid_data['id'];
        }
        if ($agente_data) {
            $relato_sql .= " AND r.agente_id = :agente_id";
            $relato_params['agente_id'] = $agente_data['id'];
        }
        if ($cnae_data) {
            $relato_sql .= " AND r.cnae_cbo_id = :cnae_id";
            $relato_params['cnae_id'] = $cnae_data['id'];
        } elseif ($cbo_data) {
            $relato_sql .= " AND r.cnae_cbo_id = :cbo_id";
            $relato_params['cbo_id'] = $cbo_data['id'];
        }
        
        // Only run if we actually added some filters, otherwise it could return everything
        if (!empty($relato_params)) {
            $stmt = $db->prepare($relato_sql);
            $stmt->execute($relato_params);
            $matching_relatos = $stmt->fetchAll();
        }

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-module="ldrt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LDRT - Consulta à Lista de Doenças Relacionadas ao Trabalho</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/favicon.png">
    <!-- Google Fonts Inter & Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>        :root, [data-bs-theme="light"] {
            --bg-color: #f1f5f9;
            --bg-glow-1: rgba(99, 102, 241, 0.06);
            --bg-glow-2: rgba(168, 85, 247, 0.04);
            --card-bg: rgba(255, 255, 255, 0.65);
            --border-color: rgba(0, 0, 0, 0.08);
            --accent-color: var(--bs-primary);
            --accent-hover: var(--primary-hover);
            --text-muted: #64748b;
            --text-color: #1e293b;
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.06);
            --navbar-bg: rgba(241, 245, 249, 0.85);
        }

        [data-bs-theme="dark"] {
            --bg-color: #0b0f19;
            --bg-glow-1: rgba(0, 176, 255, 0.12);
            --bg-glow-2: rgba(168, 85, 247, 0.08);
            --card-bg: rgba(22, 28, 45, 0.7);
            --border-color: rgba(255, 255, 255, 0.08);
            --accent-color: var(--bs-primary);
            --accent-hover: var(--primary-hover);
            --text-muted: #94a3b8;
            --text-color: #f8fafc;
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            --navbar-bg: rgba(11, 15, 25, 0.85);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(at 0% 0%, var(--bg-glow-1) 0px, transparent 50%),
                radial-gradient(at 100% 100%, var(--bg-glow-2) 0px, transparent 50%);
            color: var(--text-color);
            min-height: 100vh;
        }

        h1, h2, h3, h4, h5 {
            font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif;
            font-weight: 700;
        }

        .navbar {
            background-color: var(--navbar-bg);
            backdrop-filter: none;
            border-bottom: 1px solid var(--border-color);
        }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: none;
            -webkit-backdrop-filter: none;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .glass-card:hover {
            border-color: rgba(99, 102, 241, 0.2);
        }

        .btn-primary {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background-color: var(--accent-hover);
            border-color: var(--accent-hover);
            transform: translateY(-1px);
        }

        .btn-outline-secondary {
            border-color: var(--border-color);
            color: var(--text-color);
            border-radius: 8px;
        }

        .btn-outline-secondary:hover {
            background-color: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.15);
            color: var(--text-color);
        }

        .form-control {
            background-color: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border-color);
            color: var(--text-color);
            border-radius: 8px;
            padding: 12px;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            background-color: rgba(15, 23, 42, 0.8);
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
            color: var(--text-color);
        }

        .autocomplete-container {
            position: relative;
        }

        .autocomplete-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #1e293b;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            max-height: 250px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
            margin-top: 4px;
        }

        .suggestion-item {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            transition: background 0.15s ease;
            font-size: 0.9rem;
        }

        .suggestion-item:last-child {
            border-bottom: none;
        }

        .suggestion-item:hover {
            background-color: rgba(99, 102, 241, 0.15);
            color: var(--text-color);
        }

        .badge-custom {
            font-size: 0.75rem;
            font-weight: 500;
            padding: 4px 8px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        
        .badge-cid { background-color: var(--info-surface) !important; color: var(--info-surface-text) !important; border: 1px solid rgba(0, 176, 255, 0.2) !important; }
        .badge-cnae { background-color: var(--success-surface) !important; color: var(--success-surface-text) !important; border: 1px solid rgba(93, 207, 0, 0.2) !important; }
        .badge-cbo { background-color: var(--warn-surface) !important; color: var(--warn-surface-text) !important; border: 1px solid rgba(255, 140, 0, 0.2) !important; }
        .badge-agent { background-color: var(--danger-surface) !important; color: var(--danger-surface-text) !important; border: 1px solid rgba(255, 6, 0, 0.2) !important; }

        .section-title {
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--accent-color);
            margin-bottom: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Styling scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(11, 15, 25, 0.5);
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .tag-pill {
            display: inline-flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-right: 8px;
            margin-bottom: 8px;
        }
        
        .tag-pill i {
            margin-left: 6px;
            cursor: pointer;
            color: var(--text-muted);
            transition: color 0.15s ease;
        }
        .tag-pill i:hover {
            color: #ef4444;
        }

        .info-box {
            background: rgba(255, 255, 255, 0.02);
            border-left: 3px solid var(--accent-color);
            padding: 15px;
            border-radius: 0 8px 8px 0;
            margin-bottom: 15px;
        }

        .json-response {
            background-color: #0f172a;
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 15px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.85rem;
            max-height: 300px;
            overflow: auto;
            color: #38bdf8;
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="../../assets/js/theme-switcher.js"></script>
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>

    <!-- Navbar -->
    <!-- Navbar -->
    <?php
    require_once __DIR__ . '/../../includes/navbar.php';
    render_platform_navbar('ldrt', 'inicio');
    ?>

    <div class="container my-5">
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                <strong>Erro:</strong> <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            
            <!-- Sidebar / Search Form -->
            <div class="col-lg-4">
                <div class="glass-card p-4">
                    <h4 class="mb-4 d-flex align-items-center gap-2">
                        <i class="fa-solid fa-sliders text-primary" style="color: var(--accent-color) !important;"></i> 
                        Filtros de Busca
                    </h4>
                    
                    <form method="GET" action="index.php" id="searchForm">
                        
                        <!-- Active Filters / Tags -->
                        <?php if ($has_search): ?>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Filtros ativos:</label>
                                <div class="d-flex flex-wrap">
                                    <?php if ($cid_data): ?>
                                        <span class="tag-pill badge-cid">
                                            CID: <?php echo htmlspecialchars($cid_data['codigo']); ?>
                                            <i class="fa-solid fa-times" onclick="removeFilter('cid')"></i>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($cnae_data): ?>
                                        <span class="tag-pill badge-cnae">
                                            CNAE: <?php echo htmlspecialchars($cnae_data['codigo']); ?>
                                            <i class="fa-solid fa-times" onclick="removeFilter('cnae')"></i>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($cbo_data): ?>
                                        <span class="tag-pill badge-cbo">
                                            CBO: <?php echo htmlspecialchars($cbo_data['codigo']); ?>
                                            <i class="fa-solid fa-times" onclick="removeFilter('cbo')"></i>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($agente_data): ?>
                                        <span class="tag-pill badge-agent">
                                            Agente: ID <?php echo htmlspecialchars($agente_data['id']); ?>
                                            <i class="fa-solid fa-times" onclick="removeFilter('agente')"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- CID Input -->
                        <div class="mb-3 autocomplete-container">
                            <label for="cid_search" class="form-label d-flex justify-content-between">
                                <span><i class="fa-solid fa-virus-covid me-1 text-primary" style="color: var(--accent-color) !important;"></i> CID-10 (Doença)</span>
                                <span class="badge badge-custom badge-cid">Doença</span>
                            </label>
                            <input type="text" class="form-control" id="cid_search" placeholder="Ex: M51.0 ou Lombalgia" autocomplete="off" 
                                   value="<?php echo $cid_data ? htmlspecialchars($cid_data['codigo'] . ' - ' . $cid_data['descricao']) : ''; ?>">
                            <input type="hidden" name="cid" id="cid_val" value="<?php echo htmlspecialchars($selected_cid); ?>">
                            <div class="autocomplete-suggestions d-none" id="cid_suggestions"></div>
                        </div>

                        <!-- CNAE Input -->
                        <div class="mb-3 autocomplete-container">
                            <label for="cnae_search" class="form-label d-flex justify-content-between">
                                <span><i class="fa-solid fa-industry me-1 text-success"></i> CNAE (Atividade Econômica)</span>
                                <span class="badge badge-custom badge-cnae">Setor</span>
                            </label>
                            <input type="text" class="form-control" id="cnae_search" placeholder="Ex: 9521500 ou Informática" autocomplete="off"
                                   value="<?php echo $cnae_data ? htmlspecialchars($cnae_data['codigo'] . ' - ' . $cnae_data['descricao']) : ''; ?>">
                            <input type="hidden" name="cnae" id="cnae_val" value="<?php echo htmlspecialchars($selected_cnae); ?>">
                            <div class="autocomplete-suggestions d-none" id="cnae_suggestions"></div>
                        </div>

                        <!-- CBO Input -->
                        <div class="mb-3 autocomplete-container">
                            <label for="cbo_search" class="form-label d-flex justify-content-between">
                                <span><i class="fa-solid fa-user-doctor me-1 text-warning"></i> CBO (Ocupação/Profissão)</span>
                                <span class="badge badge-custom badge-cbo">Profissão</span>
                            </label>
                            <input type="text" class="form-control" id="cbo_search" placeholder="Ex: 3222 ou Enfermagem" autocomplete="off"
                                   value="<?php echo $cbo_data ? htmlspecialchars($cbo_data['codigo'] . ' - ' . $cbo_data['descricao']) : ''; ?>">
                            <input type="hidden" name="cbo" id="cbo_val" value="<?php echo htmlspecialchars($selected_cbo); ?>">
                            <div class="autocomplete-suggestions d-none" id="cbo_suggestions"></div>
                        </div>

                        <!-- Agent Input -->
                        <div class="mb-4 autocomplete-container">
                            <label for="agente_search" class="form-label d-flex justify-content-between">
                                <span><i class="fa-solid fa-biohazard me-1 text-danger"></i> Agente de Risco / Fator</span>
                                <span class="badge badge-custom badge-agent">Agente</span>
                            </label>
                            <input type="text" class="form-control" id="agente_search" placeholder="Ex: Ruído, Chumbo, Benzeno" autocomplete="off"
                                   value="<?php echo $agente_data ? htmlspecialchars($agente_data['descricao']) : ''; ?>">
                            <input type="hidden" name="agente" id="agente_val" value="<?php echo htmlspecialchars($selected_agente); ?>">
                            <div class="autocomplete-suggestions d-none" id="agente_suggestions"></div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-magnifying-glass me-2"></i>Buscar Relações
                            </button>
                            <?php if ($has_search): ?>
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="fa-solid fa-filter-circle-xmark me-2"></i>Limpar Filtros
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Results Panel -->
            <div class="col-lg-8">
                <div class="glass-card p-4 h-100">
                    <?php if (!$has_search): ?>
                        <div class="text-center py-5">
                            <i class="fa-solid fa-file-invoice-dollar text-muted mb-4" style="font-size: 4rem; opacity: 0.25;"></i>
                            <h3>Nenhuma consulta realizada</h3>
                            <p class="text-muted mx-auto" style="max-width: 480px;">
                                Preencha um ou mais campos ao lado para buscar relações estabelecidas na Lista de Doenças Relacionadas ao Trabalho (LDRT).
                            </p>
                            <div class="mt-4">
                                <span class="text-muted small">Consultas sugeridas (clique para testar):</span>
                                <div class="mt-2">
                                    <a href="index.php?cid=I10" class="badge badge-custom badge-cid me-2 text-decoration-none">CID: I10 (Hipertensão)</a>
                                    <a href="index.php?cid=M510" class="badge badge-custom badge-cid me-2 text-decoration-none">CID: M510 (Lombalgia)</a>
                                    <a href="index.php?cnae=9521500" class="badge badge-custom badge-cnae me-2 text-decoration-none">CNAE: 9521500 (Informática)</a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <h3 class="mb-4">Resultados da Busca</h3>
                        
                        <!-- Details of Selected Entities -->
                        <div class="row g-3 mb-4">
                            <?php if ($cid_data): ?>
                                <div class="col-md-6">
                                    <div class="info-box">
                                        <div class="text-muted small font-weight-bold">DIAGNÓSTICO (CID-10)</div>
                                        <h5 class="mt-1 text-primary" style="color: #60a5fa !important;"><?php echo htmlspecialchars($cid_data['codigo']); ?></h5>
                                        <p class="mb-0 small"><?php echo htmlspecialchars($cid_data['descricao']); ?></p>
                                        <span class="badge bg-secondary mt-2 small"><?php echo htmlspecialchars($cid_data['nivel']); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($agente_data): ?>
                                <div class="col-md-6">
                                    <div class="info-box" style="border-left-color: #f87171;">
                                        <div class="text-muted small font-weight-bold">AGENTE DE RISCO / FATOR</div>
                                        <h5 class="mt-1 text-danger" style="color: #f87171 !important;">ID: <?php echo htmlspecialchars($agente_data['id']); ?></h5>
                                        <p class="mb-0 small"><?php echo htmlspecialchars($agente_data['descricao']); ?></p>
                                        <?php if (!empty($agente_data['cas'])): ?>
                                            <span class="badge bg-dark text-light border border-secondary mt-2 small">CAS: <?php echo htmlspecialchars($agente_data['cas']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($cnae_data): ?>
                                <div class="col-md-6">
                                    <div class="info-box" style="border-left-color: #34d399;">
                                        <div class="text-muted small font-weight-bold">ATIVIDADE ECONÔMICA (CNAE)</div>
                                        <h5 class="mt-1 text-success" style="color: #34d399 !important;"><?php echo htmlspecialchars($cnae_data['codigo']); ?></h5>
                                        <p class="mb-0 small"><?php echo htmlspecialchars($cnae_data['descricao']); ?></p>
                                        <span class="badge bg-secondary mt-2 small"><?php echo htmlspecialchars($cnae_data['nivel']); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($cbo_data): ?>
                                <div class="col-md-6">
                                    <div class="info-box" style="border-left-color: #fbbf24;">
                                        <div class="text-muted small font-weight-bold">OCUPAÇÃO / PROFISSÃO (CBO)</div>
                                        <h5 class="mt-1 text-warning" style="color: #fbbf24 !important;"><?php echo htmlspecialchars($cbo_data['codigo']); ?></h5>
                                        <p class="mb-0 small"><?php echo htmlspecialchars($cbo_data['descricao']); ?></p>
                                        <span class="badge bg-secondary mt-2 small"><?php echo htmlspecialchars($cbo_data['nivel']); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <hr class="border-secondary my-4">

                        <!-- Main Relations -->
                        <div class="row g-4">
                            
                            <!-- If CID was selected, show related agents -->
                            <?php if ($cid_data): ?>
                                <div class="col-12">
                                    <h5 class="section-title"><i class="fa-solid fa-biohazard"></i> Agentes de Risco Associados a esta Doença</h5>
                                    <?php if (empty($related_agents)): ?>
                                        <p class="text-muted small">Nenhum agente de risco específico listado diretamente para esta CID na base de dados.</p>
                                    <?php else: ?>
                                        <div class="list-group list-group-flush rounded-3 border border-secondary overflow-hidden">
                                            <?php foreach ($related_agents as $agent): ?>
                                                <div class="list-group-item bg-dark border-secondary p-3">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <strong class="text-light"><?php echo htmlspecialchars($agent['descricao']); ?></strong>
                                                            <?php if (!empty($agent['cas'])): ?>
                                                                <div class="text-muted small mt-1">CAS: <?php echo htmlspecialchars($agent['cas']); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <a href="index.php?agente=<?php echo $agent['id']; ?>&cid=<?php echo urlencode($selected_cid); ?>" class="btn btn-sm btn-outline-secondary">Filtrar por Ambos</a>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- If Agent was selected, show related CIDs -->
                            <?php if ($agente_data): ?>
                                <div class="col-12 mt-4">
                                    <h5 class="section-title"><i class="fa-solid fa-virus-covid"></i> Doenças (CIDs) Associadas a este Agente</h5>
                                    <?php if (empty($related_cids)): ?>
                                        <p class="text-muted small">Nenhuma CID listada diretamente para este agente de risco na base de dados.</p>
                                    <?php else: ?>
                                        <div style="max-height: 400px; overflow-y: auto;" class="border border-secondary rounded-3">
                                            <table class="table table-dark table-hover mb-0 align-middle">
                                                <thead>
                                                    <tr>
                                                        <th>Código</th>
                                                        <th>Descrição</th>
                                                        <th>Ações</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($related_cids as $rcid): ?>
                                                        <tr>
                                                            <td><span class="badge badge-custom badge-cid"><?php echo htmlspecialchars($rcid['codigo']); ?></span></td>
                                                            <td class="small"><?php echo htmlspecialchars($rcid['descricao']); ?></td>
                                                            <td>
                                                                <a href="index.php?cid=<?php echo urlencode($rcid['codigo']); ?>&agente=<?php echo $agente_data['id']; ?>" class="btn btn-sm btn-outline-secondary py-0 px-2">Filtrar</a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Relatos de Casos (Case Reports) -->
                            <div class="col-12 mt-4">
                                <h5 class="section-title"><i class="fa-solid fa-file-medical"></i> Relatos de Casos e Jurisprudência Clinica</h5>
                                <?php if (empty($matching_relatos)): ?>
                                    <p class="text-muted small">Nenhum relato de caso específico encontrado para os filtros selecionados.</p>
                                <?php else: ?>
                                    <?php foreach ($matching_relatos as $relato): ?>
                                        <div class="card bg-dark border-secondary mb-3">
                                            <div class="card-header border-secondary d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0 text-primary-emphasis"><?php echo htmlspecialchars($relato['titulo']); ?></h6>
                                                <span class="badge bg-secondary font-monospace"><?php echo htmlspecialchars($relato['old_id']); ?></span>
                                            </div>
                                            <div class="card-body">
                                                <p class="card-text small text-light-emphasis" style="white-space: pre-wrap;"><?php echo htmlspecialchars($relato['relato']); ?></p>
                                                
                                                <div class="border-top border-secondary pt-3 mt-3 d-flex flex-wrap gap-2 align-items-center">
                                                    <span class="text-muted small me-2">Ligações do relato:</span>
                                                    <?php if ($relato['cnae_cbo_codigo']): ?>
                                                        <span class="badge badge-custom <?php echo $relato['classificacao'] === 'cnae' ? 'badge-cnae' : 'badge-cbo'; ?>">
                                                            <?php echo strtoupper($relato['classificacao']) . ': ' . htmlspecialchars($relato['cnae_cbo_codigo']); ?> 
                                                            (<?php echo htmlspecialchars(substr($relato['cnae_cbo_descricao'], 0, 30)); ?>...)
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($relato['agente_descricao']): ?>
                                                        <span class="badge badge-custom badge-agent">
                                                            Agente: <?php echo htmlspecialchars(substr($relato['agente_descricao'], 0, 30)); ?>...
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($relato['cid_codigo']): ?>
                                                        <span class="badge badge-custom badge-cid">
                                                            CID: <?php echo htmlspecialchars($relato['cid_codigo']); ?> 
                                                            (<?php echo htmlspecialchars(substr($relato['cid_descricao'], 0, 30)); ?>...)
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- RAG / AI Agent Section -->
        <div class="row g-4 mt-5" id="rag-section">
            <div class="col-12">
                <div class="glass-card p-4">
                    <h3 class="mb-3 d-flex align-items-center gap-2">
                        <i class="fa-solid fa-robot text-primary" style="color: var(--accent-color) !important;"></i> 
                        Preparação para RAG (Retrieval-Augmented Generation) & Agente de IA
                    </h3>
                    <p class="text-muted">
                        Esta base de dados foi estruturada e otimizada para alimentar Agentes de IA. Ao unificar as relações complexas da LDRT e os relatos em uma única *view* de blocos de texto (chunks), qualquer LLM (como Gemini, GPT, etc.) pode fazer buscas rápidas e obter contextos exatos em linguagem natural.
                    </p>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h5>Como Funciona o Endpoint de RAG:</h5>
                            <p class="small text-muted">
                                O endpoint <code>/api/rag_search.php?q=TERMO</code> realiza uma busca de texto completo no PostgreSQL (usando índices GIN otimizados em português) e retorna pedaços de informações em linguagem natural que contêm o relacionamento exato.
                            </p>
                            <div class="mb-3">
                                <strong>Exemplo de chamada HTTP:</strong>
                                <pre class="bg-dark p-2 border border-secondary rounded text-light small mt-1">GET /api/rag_search.php?q=pressao+sonora&limit=2</pre>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h5>Simulador de Busca de RAG (Ao Vivo):</h5>
                            <div class="input-group mb-3">
                                <input type="text" id="rag_query" class="form-control" placeholder="Digite termos como 'chumbo', 'ruído', 'lombalgia'...">
                                <button class="btn btn-primary" type="button" onclick="testRagSearch()">Buscar Chunks</button>
                            </div>
                            
                            <div id="rag_loader" class="d-none text-center my-3">
                                <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
                                <span class="ms-2 text-muted small">Buscando no banco...</span>
                            </div>
                            
                            <div id="rag_results_container" class="d-none">
                                <div class="text-muted small mb-1" id="rag_count_label"></div>
                                <pre class="json-response" id="rag_results_json"></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Footer -->
    <footer class="text-center py-4 border-top border-secondary mt-5" style="background-color: rgba(11, 15, 25, 0.5);">
        <div class="container">
            <p class="mb-1 text-muted small">LDRT Query Application &copy; 2026 - Preparado para RAG & Agentes de IA</p>
            <p class="mb-0 text-muted" style="font-size: 0.75rem;">Desenvolvido em PHP 8.2, Bootstrap 5 e PostgreSQL</p>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Autocomplete and Utilities JS -->
    <script>
        // Setup Autocomplete for inputs
        setupAutocomplete('cid_search', 'cid_val', 'cid_suggestions', 'cid');
        setupAutocomplete('cnae_search', 'cnae_val', 'cnae_suggestions', 'cnae');
        setupAutocomplete('cbo_search', 'cbo_val', 'cbo_suggestions', 'cbo');
        setupAutocomplete('agente_search', 'agente_val', 'agente_suggestions', 'agente');

        function setupAutocomplete(inputId, hiddenId, suggestionsId, type) {
            const input = document.getElementById(inputId);
            const hidden = document.getElementById(hiddenId);
            const suggestions = document.getElementById(suggestionsId);
            let debounceTimer;

            input.addEventListener('input', function() {
                const query = this.value.trim();
                
                clearTimeout(debounceTimer);
                if (query.length < 2) {
                    suggestions.classList.add('d-none');
                    return;
                }

                debounceTimer = setTimeout(() => {
                    fetch(`api/autocomplete.php?type=${type}&q=${encodeURIComponent(query)}`)
                        .then(response => response.json())
                        .then(data => {
                            suggestions.innerHTML = '';
                            if (data.length === 0) {
                                suggestions.classList.add('d-none');
                                return;
                            }

                            data.forEach(item => {
                                const div = document.createElement('div');
                                div.classList.add('suggestion-item');
                                div.textContent = item.label;
                                div.addEventListener('click', () => {
                                    input.value = item.label;
                                    hidden.value = item.value;
                                    suggestions.classList.add('d-none');
                                });
                                suggestions.appendChild(div);
                            });
                            suggestions.classList.remove('d-none');
                        })
                        .catch(err => console.error('Error fetching suggestions:', err));
                }, 200);
            });

            // Close suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (e.target !== input && e.target !== suggestions) {
                    suggestions.classList.add('d-none');
                }
            });
        }

        function removeFilter(filterName) {
            document.getElementById(filterName + '_val').value = '';
            document.getElementById(filterName + '_search').value = '';
            document.getElementById('searchForm').submit();
        }

        function testRagSearch() {
            const query = document.getElementById('rag_query').value.trim();
            const loader = document.getElementById('rag_loader');
            const resultsContainer = document.getElementById('rag_results_container');
            const resultsJson = document.getElementById('rag_results_json');
            const countLabel = document.getElementById('rag_count_label');

            if (!query) return;

            loader.classList.remove('d-none');
            resultsContainer.classList.add('d-none');

            fetch(`api/rag_search.php?q=${encodeURIComponent(query)}&limit=3`)
                .then(res => res.json())
                .then(data => {
                    loader.classList.add('d-none');
                    resultsContainer.classList.remove('d-none');
                    countLabel.textContent = `Encontrados: ${data.count} chunks (Exibindo até 3)`;
                    resultsJson.textContent = JSON.stringify(data, null, 2);
                })
                .catch(err => {
                    loader.classList.add('d-none');
                    resultsContainer.classList.remove('d-none');
                    countLabel.textContent = 'Erro ao buscar';
                    resultsJson.textContent = JSON.stringify({ error: err.message }, null, 2);
                });
        }
    </script>
</body>
</html>

