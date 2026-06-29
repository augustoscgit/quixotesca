<?php
require_once __DIR__ . '/../../ldrt/src/db.php';

function normalize_term($str) {
    $str = mb_strtolower($str, 'UTF-8');
    $accented = ['á','à','â','ã','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','ô','õ','ö','ú','ù','û','ü','ç','ý','ñ'];
    $non_accented = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','y','n'];
    return str_replace($accented, $non_accented, $str);
}

// Initialize variables (support direct input search parameters as fallback)
$selected_cid = isset($_GET['cid']) ? trim($_GET['cid']) : '';
if (empty($selected_cid) && isset($_GET['cid_search'])) {
    $selected_cid = trim($_GET['cid_search']);
}

$selected_cnae = isset($_GET['cnae']) ? trim($_GET['cnae']) : '';
if (empty($selected_cnae) && isset($_GET['cnae_search'])) {
    $selected_cnae = trim($_GET['cnae_search']);
}

$selected_cbo = isset($_GET['cbo']) ? trim($_GET['cbo']) : '';
if (empty($selected_cbo) && isset($_GET['cbo_search'])) {
    $selected_cbo = trim($_GET['cbo_search']);
}

$selected_agente = isset($_GET['agente']) ? trim($_GET['agente']) : '';
if (empty($selected_agente) && isset($_GET['agente_search'])) {
    $selected_agente = trim($_GET['agente_search']);
}

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

        // 1. Fetch details of selected entities using the database tables as dictionaries (match code or label)
        if (!empty($selected_cid)) {
            $stmt = $db->prepare("SELECT * FROM cid WHERE codigo = :code");
            $stmt->execute(['code' => $selected_cid]);
            $cid_data = $stmt->fetch();
            
            if (!$cid_data) {
                $normalized = normalize_term($selected_cid);
                $stmt = $db->prepare("
                    SELECT * FROM cid 
                    WHERE translate(lower(codigo), 'áàâãäéèêëíìîïóòôõöúùûüçýñ', 'aaaaaeeeeiiiiooooouuuucyn') LIKE :term 
                       OR translate(lower(descricao), 'áàâãäéèêëíìîïóòôõöúùûüçýñ', 'aaaaaeeeeiiiiooooouuuucyn') LIKE :term 
                    ORDER BY codigo ASC LIMIT 1
                ");
                $stmt->execute(['term' => "%$normalized%"]);
                $cid_data = $stmt->fetch();
            }
        }
        
        if (!empty($selected_cnae)) {
            $stmt = $db->prepare("SELECT * FROM cnae_cbo WHERE classificacao = 'cnae' AND codigo = :code");
            $stmt->execute(['code' => $selected_cnae]);
            $cnae_data = $stmt->fetch();
            
            if (!$cnae_data) {
                $normalized = normalize_term($selected_cnae);
                $stmt = $db->prepare("
                    SELECT * FROM cnae_cbo 
                    WHERE classificacao = 'cnae' 
                      AND (
                        translate(lower(codigo), 'áàâãäéèêëíìîïóòôõöúùûüçýñ', 'aaaaaeeeeiiiiooooouuuucyn') LIKE :term 
                        OR translate(lower(descricao), 'áàâãäéèêëíìîïóòôõöúùûüçýñ', 'aaaaaeeeeiiiiooooouuuucyn') LIKE :term
                      ) 
                    ORDER BY codigo ASC LIMIT 1
                ");
                $stmt->execute(['term' => "%$normalized%"]);
                $cnae_data = $stmt->fetch();
            }
        }
        
        if (!empty($selected_cbo)) {
            $stmt = $db->prepare("SELECT * FROM cnae_cbo WHERE classificacao = 'cbo' AND codigo = :code");
            $stmt->execute(['code' => $selected_cbo]);
            $cbo_data = $stmt->fetch();
            
            if (!$cbo_data) {
                $normalized = normalize_term($selected_cbo);
                $stmt = $db->prepare("
                    SELECT * FROM cnae_cbo 
                    WHERE classificacao = 'cbo' 
                      AND (
                        translate(lower(codigo), 'áàâãäéèêëíìîïóòôõöúùûüçýñ', 'aaaaaeeeeiiiiooooouuuucyn') LIKE :term 
                        OR translate(lower(descricao), 'áàâãäéèêëíìîïóòôõöúùûüçýñ', 'aaaaaeeeeiiiiooooouuuucyn') LIKE :term
                      ) 
                    ORDER BY codigo ASC LIMIT 1
                ");
                $stmt->execute(['term' => "%$normalized%"]);
                $cbo_data = $stmt->fetch();
            }
        }
        
        if (!empty($selected_agente)) {
            $stmt = $db->prepare("SELECT * FROM agentes WHERE id = :id");
            $id_val = is_numeric($selected_agente) ? intval($selected_agente) : 0;
            $stmt->execute(['id' => $id_val]);
            $agente_data = $stmt->fetch();
            
            if (!$agente_data) {
                $normalized = normalize_term($selected_agente);
                $stmt = $db->prepare("
                    SELECT * FROM agentes 
                    WHERE translate(lower(descricao), 'áàâãäéèêëíìîïóòôõöúùûüçýñ', 'aaaaaeeeeiiiiooooouuuucyn') LIKE :term 
                    ORDER BY id ASC LIMIT 1
                ");
                $stmt->execute(['term' => "%$normalized%"]);
                $agente_data = $stmt->fetch();
            }
        }

        // 2. Fetch relations (use busca hierárquica recursive CTEs for CID)
        // If CID selected, get related agents (associated with the CID or any of its descendants)
        if ($cid_data) {
            $stmt = $db->prepare("
                WITH RECURSIVE sub_cids AS (
                    SELECT id FROM cid WHERE id = :cid_id
                    UNION ALL
                    SELECT c.id FROM cid c JOIN sub_cids p ON c.parent_id = p.id
                )
                SELECT DISTINCT a.id, a.descricao, a.cas 
                FROM agentes a 
                JOIN agente_cid ac ON ac.agente_id = a.id 
                WHERE ac.cid_id IN (SELECT id FROM sub_cids)
                ORDER BY a.descricao ASC
            ");
            $stmt->execute(['cid_id' => $cid_data['id']]);
            $related_agents = $stmt->fetchAll();
        }

        // If Agent selected, get related CIDs (no hierarchical search needed for agent per rules)
        if ($agente_data) {
            $stmt = $db->prepare("
                SELECT DISTINCT c.codigo, c.descricao, c.nivel 
                FROM cid c 
                JOIN agente_cid ac ON ac.cid_id = c.id 
                WHERE ac.agente_id = :agente_id
                ORDER BY c.codigo ASC
            ");
            $stmt->execute(['agente_id' => $agente_data['id']]);
            $related_cids = $stmt->fetchAll();
        }

        // 3. Fetch matching Relatos (Case Reports) using busca hierárquica recursive CTEs for CID, CNAE, and CBO
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
            $relato_sql .= " AND r.cid_id IN (
                WITH RECURSIVE sub_cids AS (
                    SELECT id FROM cid WHERE id = :cid_id
                    UNION ALL
                    SELECT c.id FROM cid c JOIN sub_cids p ON c.parent_id = p.id
                )
                SELECT id FROM sub_cids
            )";
            $relato_params['cid_id'] = $cid_data['id'];
        }
        if ($agente_data) {
            $relato_sql .= " AND r.agente_id = :agente_id";
            $relato_params['agente_id'] = $agente_data['id'];
        }
        if ($cnae_data) {
            $relato_sql .= " AND r.cnae_cbo_id IN (
                WITH RECURSIVE sub_cnaes AS (
                    SELECT id FROM cnae_cbo WHERE id = :cnae_id
                    UNION ALL
                    SELECT c.id FROM cnae_cbo c JOIN sub_cnaes p ON c.parent_id = p.id
                )
                SELECT id FROM sub_cnaes
            )";
            $relato_params['cnae_id'] = $cnae_data['id'];
        } elseif ($cbo_data) {
            $relato_sql .= " AND r.cnae_cbo_id IN (
                WITH RECURSIVE sub_cbos AS (
                    SELECT id FROM cnae_cbo WHERE id = :cbo_id
                    UNION ALL
                    SELECT c.id FROM cnae_cbo c JOIN sub_cbos p ON c.parent_id = p.id
                )
                SELECT id FROM sub_cbos
            )";
            $relato_params['cbo_id'] = $cbo_data['id'];
        }
        
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
    <title>LDRT - Consulta Cruzada</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/favicon.png">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
                :root, [data-bs-theme="light"] {
            --bg-color: #f1f5f9;
            --bg-glow-1: transparent;
            --bg-glow-2: transparent;
            --card-bg: rgba(255, 255, 255, 0.65);
            --border-color: rgba(0, 0, 0, 0.08);
            --accent-color: var(--accent-ui);
            --accent-hover: var(--brand-laranja-4);
            --text-muted: #64748b;
            --text-color: #1e293b;
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.06);
            --navbar-bg: rgba(241, 245, 249, 0.85);
            --field-bg: #ffffff;
        }

        [data-bs-theme="dark"] {
            --bg-color: #0b0f19;
            --bg-glow-1: transparent;
            --bg-glow-2: transparent;
            --card-bg: rgba(22, 28, 45, 0.7);
            --border-color: rgba(255, 255, 255, 0.08);
            --accent-color: var(--accent-ui);
            --accent-hover: var(--brand-laranja-4);
            --text-muted: #94a3b8;
            --text-color: #f8fafc;
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            --navbar-bg: rgba(11, 15, 25, 0.85);
            --field-bg: #111827;
        }

                body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            background-image: none;
            color: var(--text-color);
            min-height: 100vh;
        }

        h1, h2, h3, h4, h5 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
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
        }

        .btn-primary {
            background-color: var(--accent-solid);
            border-color: var(--accent-border);
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background-color: var(--accent-solid-hover);
            border-color: var(--accent-solid-hover);
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

        .form-control,
        .form-select,
        .input-group-text {
            background-color: var(--field-bg) !important;
            border: 1px solid var(--border-color);
            color: var(--text-color) !important;
            border-radius: 8px;
        }

        .form-control {
            padding: 12px;
            transition: all 0.2s ease;
        }

        .form-control:focus,
        .form-select:focus {
            background-color: var(--field-bg) !important;
            border-color: var(--accent-border);
            box-shadow: 0 0 0 0.2rem rgba(0, 99, 146, 0.25);
            color: var(--text-color) !important;
        }

        .form-control::placeholder {
            color: var(--text-muted);
            opacity: 0.78;
        }

        .form-select option {
            background-color: var(--field-bg);
            color: var(--text-color);
        }

        .autocomplete-container {
            position: relative;
        }

        .autocomplete-container:focus-within {
            z-index: 10;
        }

        .autocomplete-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--field-bg);
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
            border-bottom: 1px solid var(--border-color);
            transition: background 0.15s ease;
            font-size: 0.9rem;
            color: var(--text-color);
        }

        .suggestion-item:last-child {
            border-bottom: none;
        }

        .suggestion-item:hover {
            background-color: var(--bs-tertiary-bg);
            color: var(--text-color);
        }

        .tag-input-selected {
            background-color: var(--field-bg) !important;
            border: 1px solid var(--border-color);
            color: var(--text-color) !important;
            border-radius: 8px;
            height: 48px;
            padding: 0 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .tag-input-selected .btn-close {
            font-size: 0.75rem;
        }

        [data-bs-theme="dark"] .tag-input-selected .btn-close {
            filter: invert(1) grayscale(1) brightness(2);
        }

        .badge-custom {
            font-size: 0.75rem;
            font-weight: 500;
            padding: 4px 8px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        
        .badge-cid { background-color: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
        .badge-cnae { background-color: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-cbo { background-color: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .badge-agent { background-color: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }

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

        .tag-pill {
            display: inline-flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            padding: 4px 10px;
            border-radius: 8px;
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
            border-left: 3px solid var(--accent-border);
            padding: 15px;
            border-radius: 0 8px 8px 0;
            margin-bottom: 15px;
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="../assets/js/theme-switcher.js"></script>
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

    <!-- Navbar -->
    <!-- Navbar -->
    <?php
    require_once __DIR__ . '/../../includes/navbar.php';
    render_platform_navbar('ldrt', 'consulta');
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
                        <i class="fa-solid fa-shuffle text-primary" style="color: var(--accent-color) !important;"></i> 
                        Consulta Cruzada
                    </h4>
                    
                    <form method="GET" action="consulta.php" id="searchForm">
                        
                        <!-- CID Input -->
                        <div class="mb-3 autocomplete-container">
                            <label for="cid_search" class="form-label d-flex justify-content-between">
                                <span><i class="fa-solid fa-virus-covid me-1 text-primary" style="color: var(--accent-color) !important;"></i> CID-10 (Doença)</span>
                                <span class="badge badge-custom badge-cid">Doença</span>
                            </label>
                            <?php if ($cid_data): ?>
                                <div class="tag-input-selected form-control">
                                    <span class="badge badge-custom badge-cid text-truncate text-start" title="<?php echo htmlspecialchars($cid_data['codigo'] . ' - ' . $cid_data['descricao']); ?>" style="max-width: calc(100% - 25px); font-size: 0.85rem; padding: 6px 10px;">
                                        <i class="fa-solid fa-virus-covid me-1"></i>
                                        <?php echo htmlspecialchars($cid_data['codigo'] . ' - ' . $cid_data['descricao']); ?>
                                    </span>
                                    <button type="button" class="btn-close" onclick="removeFilter('cid')" aria-label="Remover"></button>
                                </div>
                                <input type="hidden" name="cid" id="cid_val" value="<?php echo htmlspecialchars($selected_cid); ?>">
                            <?php else: ?>
                                <input type="text" class="form-control" id="cid_search" placeholder="Ex: M51.0 ou Lombalgia" autocomplete="off" value="">
                                <input type="hidden" name="cid" id="cid_val" value="">
                                <div class="autocomplete-suggestions d-none" id="cid_suggestions"></div>
                            <?php endif; ?>
                        </div>

                        <!-- CNAE Input -->
                        <div class="mb-3 autocomplete-container">
                            <label for="cnae_search" class="form-label d-flex justify-content-between">
                                <span><i class="fa-solid fa-industry me-1 text-success"></i> CNAE (Atividade Econômica)</span>
                                <span class="badge badge-custom badge-cnae">Setor</span>
                            </label>
                            <?php if ($cnae_data): ?>
                                <div class="tag-input-selected form-control">
                                    <span class="badge badge-custom badge-cnae text-truncate text-start" title="<?php echo htmlspecialchars($cnae_data['codigo'] . ' - ' . $cnae_data['descricao']); ?>" style="max-width: calc(100% - 25px); font-size: 0.85rem; padding: 6px 10px;">
                                        <i class="fa-solid fa-industry me-1"></i>
                                        <?php echo htmlspecialchars($cnae_data['codigo'] . ' - ' . $cnae_data['descricao']); ?>
                                    </span>
                                    <button type="button" class="btn-close" onclick="removeFilter('cnae')" aria-label="Remover"></button>
                                </div>
                                <input type="hidden" name="cnae" id="cnae_val" value="<?php echo htmlspecialchars($selected_cnae); ?>">
                            <?php else: ?>
                                <input type="text" class="form-control" id="cnae_search" placeholder="Ex: 9521500 ou Informática" autocomplete="off" value="">
                                <input type="hidden" name="cnae" id="cnae_val" value="">
                                <div class="autocomplete-suggestions d-none" id="cnae_suggestions"></div>
                            <?php endif; ?>
                        </div>

                        <!-- CBO Input -->
                        <div class="mb-3 autocomplete-container">
                            <label for="cbo_search" class="form-label d-flex justify-content-between">
                                <span><i class="fa-solid fa-user-doctor me-1 text-warning"></i> CBO (Ocupação/Profissão)</span>
                                <span class="badge badge-custom badge-cbo">Profissão</span>
                            </label>
                            <?php if ($cbo_data): ?>
                                <div class="tag-input-selected form-control">
                                    <span class="badge badge-custom badge-cbo text-truncate text-start" title="<?php echo htmlspecialchars($cbo_data['codigo'] . ' - ' . $cbo_data['descricao']); ?>" style="max-width: calc(100% - 25px); font-size: 0.85rem; padding: 6px 10px;">
                                        <i class="fa-solid fa-user-doctor me-1"></i>
                                        <?php echo htmlspecialchars($cbo_data['codigo'] . ' - ' . $cbo_data['descricao']); ?>
                                    </span>
                                    <button type="button" class="btn-close" onclick="removeFilter('cbo')" aria-label="Remover"></button>
                                </div>
                                <input type="hidden" name="cbo" id="cbo_val" value="<?php echo htmlspecialchars($selected_cbo); ?>">
                            <?php else: ?>
                                <input type="text" class="form-control" id="cbo_search" placeholder="Ex: 3222 ou Enfermagem" autocomplete="off" value="">
                                <input type="hidden" name="cbo" id="cbo_val" value="">
                                <div class="autocomplete-suggestions d-none" id="cbo_suggestions"></div>
                            <?php endif; ?>
                        </div>

                        <!-- Agent Input -->
                        <div class="mb-4 autocomplete-container">
                            <label for="agente_search" class="form-label d-flex justify-content-between">
                                <span><i class="fa-solid fa-biohazard me-1 text-danger"></i> Agente de Risco / Fator</span>
                                <span class="badge badge-custom badge-agent">Agente</span>
                            </label>
                            <?php if ($agente_data): ?>
                                <div class="tag-input-selected form-control">
                                    <span class="badge badge-custom badge-agent text-truncate text-start" title="<?php echo htmlspecialchars($agente_data['descricao']); ?>" style="max-width: calc(100% - 25px); font-size: 0.85rem; padding: 6px 10px;">
                                        <i class="fa-solid fa-biohazard me-1"></i>
                                        <?php echo htmlspecialchars($agente_data['descricao']); ?>
                                    </span>
                                    <button type="button" class="btn-close" onclick="removeFilter('agente')" aria-label="Remover"></button>
                                </div>
                                <input type="hidden" name="agente" id="agente_val" value="<?php echo htmlspecialchars($selected_agente); ?>">
                            <?php else: ?>
                                <input type="text" class="form-control" id="agente_search" placeholder="Ex: Ruído, Chumbo, Benzeno" autocomplete="off" value="">
                                <input type="hidden" name="agente" id="agente_val" value="">
                                <div class="autocomplete-suggestions d-none" id="agente_suggestions"></div>
                            <?php endif; ?>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-magnifying-glass me-2"></i>Buscar Relações
                            </button>
                            <?php if ($has_search): ?>
                                <a href="consulta.php" class="btn btn-outline-secondary">
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
                            <i class="fa-solid fa-shuffle text-muted mb-4" style="font-size: 4rem; opacity: 0.25;"></i>
                            <h3>Nenhuma consulta realizada</h3>
                            <p class="text-muted mx-auto" style="max-width: 480px;">
                                Preencha um ou mais campos ao lado para buscar relações estabelecidas na Lista de Doenças Relacionadas ao Trabalho (LDRT).
                            </p>
                            <div class="mt-4">
                                <span class="text-muted small">Exemplos de consultas (clique para testar):</span>
                                <div class="mt-2">
                                    <a href="consulta.php?cid=I10" class="badge badge-custom badge-cid me-2 text-decoration-none">Hipertensão (I10)</a>
                                    <a href="consulta.php?agente=89" class="badge badge-custom badge-agent me-2 text-decoration-none">Chumbo (ID 89)</a>
                                    <a href="consulta.php?cnae=9521500" class="badge badge-custom badge-cnae me-2 text-decoration-none">Informática (9521500)</a>
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
                                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                                        <div style="min-width: 0; flex: 1;">
                                                            <strong class="text-light" style="word-break: break-word; white-space: normal;"><?php echo htmlspecialchars($agent['descricao']); ?></strong>
                                                            <?php if (!empty($agent['cas'])): ?>
                                                                <div class="text-muted small mt-1">CAS: <?php echo htmlspecialchars($agent['cas']); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <a href="consulta.php?agente=<?php echo $agent['id']; ?>&cid=<?php echo urlencode($selected_cid); ?>" class="btn btn-sm btn-outline-secondary flex-shrink-0">Filtrar por Ambos</a>
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
                                                                <a href="consulta.php?cid=<?php echo urlencode($rcid['codigo']); ?>&agente=<?php echo $agente_data['id']; ?>" class="btn btn-sm btn-outline-secondary py-0 px-2">Filtrar</a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Relatos de Casos -->
                            <div class="col-12 mt-4">
                                <h5 class="section-title"><i class="fa-solid fa-file-medical"></i> Relatos de Casos</h5>
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
                                                    <span class="text-muted small me-2">Ligações:</span>
                                                    <?php if ($relato['cnae_cbo_codigo'] && !$cnae_data && !$cbo_data): ?>
                                                        <span class="badge badge-custom <?php echo $relato['classificacao'] === 'cnae' ? 'badge-cnae' : 'badge-cbo'; ?>">
                                                            <?php echo strtoupper($relato['classificacao']) . ': ' . htmlspecialchars($relato['cnae_cbo_codigo']) . ' - ' . htmlspecialchars(substr($relato['cnae_cbo_descricao'], 0, 30)) . '...'; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($relato['agente_descricao'] && !$agente_data): ?>
                                                        <span class="badge badge-custom badge-agent">
                                                            Agente: <?php echo htmlspecialchars(substr($relato['agente_descricao'], 0, 30)); ?>...
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($relato['cid_codigo'] && !$cid_data): ?>
                                                        <span class="badge badge-custom badge-cid">
                                                            CID: <?php echo htmlspecialchars($relato['cid_codigo']) . ' - ' . htmlspecialchars(substr($relato['cid_descricao'], 0, 30)) . '...'; ?>
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
    </div>

    <!-- Footer -->
    <footer class="text-center py-4 border-top border-secondary mt-5" style="background-color: rgba(11, 15, 25, 0.5);">
        <div class="container">
            <p class="mb-1 text-muted small">LDRT Query Application &copy; 2026 - Em conformidade com a Portaria GM/MS 1.999/2023</p>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Autocomplete Script -->
    <script>
        setupAutocomplete('cid_search', 'cid_val', 'cid_suggestions', 'cid');
        setupAutocomplete('cnae_search', 'cnae_val', 'cnae_suggestions', 'cnae');
        setupAutocomplete('cbo_search', 'cbo_val', 'cbo_suggestions', 'cbo');
        setupAutocomplete('agente_search', 'agente_val', 'agente_suggestions', 'agente');

        function setupAutocomplete(inputId, hiddenId, suggestionsId, type) {
            const input = document.getElementById(inputId);
            const hidden = document.getElementById(hiddenId);
            const suggestions = document.getElementById(suggestionsId);
            if (!input) return;
            let debounceTimer;

            input.addEventListener('input', function() {
                const query = this.value.trim();
                
                clearTimeout(debounceTimer);
                if (query.length < 2) {
                    suggestions.classList.add('d-none');
                    return;
                }

                debounceTimer = setTimeout(() => {
                    fetch(`api_autocomplete.php?type=${type}&q=${encodeURIComponent(query)}`)
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
                                    document.getElementById('searchForm').submit();
                                });
                                suggestions.appendChild(div);
                            });
                            suggestions.classList.remove('d-none');
                        })
                        .catch(err => console.error('Error fetching suggestions:', err));
                }, 200);
            });

            document.addEventListener('click', function(e) {
                if (e.target !== input && e.target !== suggestions) {
                    suggestions.classList.add('d-none');
                }
            });
        }

        function removeFilter(filterName) {
            const valInput = document.getElementById(filterName + '_val');
            const searchInput = document.getElementById(filterName + '_search');
            if (valInput) valInput.value = '';
            if (searchInput) searchInput.value = '';
            document.getElementById('searchForm').submit();
        }

        // Intercept form submission to copy text searches into values (dicionário / fallback)
        document.getElementById('searchForm').addEventListener('submit', function() {
            ['cid', 'cnae', 'cbo', 'agente'].forEach(type => {
                const searchInput = document.getElementById(type + '_search');
                const valInput = document.getElementById(type + '_val');
                if (searchInput && valInput && !valInput.value && searchInput.value.trim()) {
                    valInput.value = searchInput.value.trim();
                }
            });
        });
    </script>
</body>
</html>
