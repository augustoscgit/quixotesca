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
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <!-- FontAwesome -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="../assets/js/theme-switcher.js?v=20260629-vanilla"></script>
    <link href="../assets/css/style.css?v=20260629-vanilla" rel="stylesheet">
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
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Erro:</strong> <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            
            <!-- Sidebar / Search Form -->
            <div class="col-lg-4">
                <div class="card p-4">
                    <h4 class="mb-4 d-flex align-items-center gap-2">
                        <i class="bi bi-shuffle text-primary"></i> 
                        Consulta Cruzada
                    </h4>
                    
                    <form method="GET" action="consulta.php" id="searchForm">
                        
                        <!-- CID Input -->
                        <div class="mb-3 autocomplete-container">
                            <label for="cid_search" class="form-label d-flex justify-content-between">
                                <span><i class="bi bi-virus me-1 text-primary"></i> CID-10 (Doença)</span>
                                <span class="badge text-bg-secondary badge-cid">Doença</span>
                            </label>
                            <?php if ($cid_data): ?>
                                <div class="tag-input-selected form-control">
                                    <span class="badge text-bg-secondary badge-cid text-truncate text-start" title="<?php echo htmlspecialchars($cid_data['codigo'] . ' - ' . $cid_data['descricao']); ?>">
                                        <i class="bi bi-virus me-1"></i>
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
                                <span><i class="bi bi-building-gear me-1 text-success"></i> CNAE (Atividade Econômica)</span>
                                <span class="badge text-bg-secondary badge-cnae">Setor</span>
                            </label>
                            <?php if ($cnae_data): ?>
                                <div class="tag-input-selected form-control">
                                    <span class="badge text-bg-secondary badge-cnae text-truncate text-start" title="<?php echo htmlspecialchars($cnae_data['codigo'] . ' - ' . $cnae_data['descricao']); ?>">
                                        <i class="bi bi-building-gear me-1"></i>
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
                                <span><i class="bi bi-person-badge me-1 text-warning"></i> CBO (Ocupação/Profissão)</span>
                                <span class="badge text-bg-secondary badge-cbo">Profissão</span>
                            </label>
                            <?php if ($cbo_data): ?>
                                <div class="tag-input-selected form-control">
                                    <span class="badge text-bg-secondary badge-cbo text-truncate text-start" title="<?php echo htmlspecialchars($cbo_data['codigo'] . ' - ' . $cbo_data['descricao']); ?>">
                                        <i class="bi bi-person-badge me-1"></i>
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
                                <span><i class="bi bi-radioactive me-1 text-danger"></i> Agente de Risco / Fator</span>
                                <span class="badge text-bg-secondary badge-agent">Agente</span>
                            </label>
                            <?php if ($agente_data): ?>
                                <div class="tag-input-selected form-control">
                                    <span class="badge text-bg-secondary badge-agent text-truncate text-start" title="<?php echo htmlspecialchars($agente_data['descricao']); ?>">
                                        <i class="bi bi-radioactive me-1"></i>
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
                                <i class="bi bi-search me-2"></i>Buscar Relações
                            </button>
                            <?php if ($has_search): ?>
                                <a href="consulta.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-funnel me-2"></i>Limpar Filtros
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Results Panel -->
            <div class="col-lg-8">
                <div class="card p-4 h-100">
                    <?php if (!$has_search): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-shuffle text-muted mb-4"></i>
                            <h3>Nenhuma consulta realizada</h3>
                            <p class="text-muted mx-auto">
                                Preencha um ou mais campos ao lado para buscar relações estabelecidas na Lista de Doenças Relacionadas ao Trabalho (LDRT).
                            </p>
                            <div class="mt-4">
                                <span class="text-muted small">Exemplos de consultas (clique para testar):</span>
                                <div class="mt-2">
                                    <a href="consulta.php?cid=I10" class="badge text-bg-secondary badge-cid me-2 text-decoration-none">Hipertensão (I10)</a>
                                    <a href="consulta.php?agente=89" class="badge text-bg-secondary badge-agent me-2 text-decoration-none">Chumbo (ID 89)</a>
                                    <a href="consulta.php?cnae=9521500" class="badge text-bg-secondary badge-cnae me-2 text-decoration-none">Informática (9521500)</a>
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
                                        <h5 class="mt-1 text-primary"><?php echo htmlspecialchars($cid_data['codigo']); ?></h5>
                                        <p class="mb-0 small"><?php echo htmlspecialchars($cid_data['descricao']); ?></p>
                                        <span class="badge bg-secondary mt-2 small"><?php echo htmlspecialchars($cid_data['nivel']); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($agente_data): ?>
                                <div class="col-md-6">
                                    <div class="info-box">
                                        <div class="text-muted small font-weight-bold">AGENTE DE RISCO / FATOR</div>
                                        <h5 class="mt-1 text-danger">ID: <?php echo htmlspecialchars($agente_data['id']); ?></h5>
                                        <p class="mb-0 small"><?php echo htmlspecialchars($agente_data['descricao']); ?></p>
                                        <?php if (!empty($agente_data['cas'])): ?>
                                            <span class="badge bg-body-tertiary text-body border border mt-2 small">CAS: <?php echo htmlspecialchars($agente_data['cas']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($cnae_data): ?>
                                <div class="col-md-6">
                                    <div class="info-box">
                                        <div class="text-muted small font-weight-bold">ATIVIDADE ECONÔMICA (CNAE)</div>
                                        <h5 class="mt-1 text-success"><?php echo htmlspecialchars($cnae_data['codigo']); ?></h5>
                                        <p class="mb-0 small"><?php echo htmlspecialchars($cnae_data['descricao']); ?></p>
                                        <span class="badge bg-secondary mt-2 small"><?php echo htmlspecialchars($cnae_data['nivel']); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($cbo_data): ?>
                                <div class="col-md-6">
                                    <div class="info-box">
                                        <div class="text-muted small font-weight-bold">OCUPAÇÃO / PROFISSÃO (CBO)</div>
                                        <h5 class="mt-1 text-warning"><?php echo htmlspecialchars($cbo_data['codigo']); ?></h5>
                                        <p class="mb-0 small"><?php echo htmlspecialchars($cbo_data['descricao']); ?></p>
                                        <span class="badge bg-secondary mt-2 small"><?php echo htmlspecialchars($cbo_data['nivel']); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <hr class="border my-4">

                        <!-- Main Relations -->
                        <div class="row g-4">
                            
                            <!-- If CID was selected, show related agents -->
                            <?php if ($cid_data): ?>
                                <div class="col-12">
                                    <h5 class="section-title"><i class="bi bi-radioactive"></i> Agentes de Risco Associados a esta Doença</h5>
                                    <?php if (empty($related_agents)): ?>
                                        <p class="text-muted small">Nenhum agente de risco específico listado diretamente para esta CID na base de dados.</p>
                                    <?php else: ?>
                                        <div class="list-group list-group-flush rounded-3 border border overflow-hidden">
                                            <?php foreach ($related_agents as $agent): ?>
                                                <div class="list-group-item bg-body-tertiary border p-3">
                                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                                        <div>
                                                            <strong class="text-body"><?php echo htmlspecialchars($agent['descricao']); ?></strong>
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
                                    <h5 class="section-title"><i class="bi bi-virus"></i> Doenças (CIDs) Associadas a este Agente</h5>
                                    <?php if (empty($related_cids)): ?>
                                        <p class="text-muted small">Nenhuma CID listada diretamente para este agente de risco na base de dados.</p>
                                    <?php else: ?>
                                        <div class="border border rounded-3">
                                            <table class="table table-hover mb-0 align-middle">
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
                                                            <td><span class="badge text-bg-secondary badge-cid"><?php echo htmlspecialchars($rcid['codigo']); ?></span></td>
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
                                <h5 class="section-title"><i class="bi bi-file-earmark-medical"></i> Relatos de Casos</h5>
                                <?php if (empty($matching_relatos)): ?>
                                    <p class="text-muted small">Nenhum relato de caso específico encontrado para os filtros selecionados.</p>
                                <?php else: ?>
                                    <?php foreach ($matching_relatos as $relato): ?>
                                        <div class="card bg-body-tertiary border mb-3">
                                            <div class="card-header border d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0 text-primary-emphasis"><?php echo htmlspecialchars($relato['titulo']); ?></h6>
                                                <span class="badge bg-secondary font-monospace"><?php echo htmlspecialchars($relato['old_id']); ?></span>
                                            </div>
                                            <div class="card-body">
                                                <p class="card-text small text-body-secondary"><?php echo htmlspecialchars($relato['relato']); ?></p>
                                                
                                                <div class="border-top border pt-3 mt-3 d-flex flex-wrap gap-2 align-items-center">
                                                    <span class="text-muted small me-2">Ligações:</span>
                                                    <?php if ($relato['cnae_cbo_codigo'] && !$cnae_data && !$cbo_data): ?>
                                                        <span class="badge text-bg-secondary <?php echo $relato['classificacao'] === 'cnae' ? 'badge-cnae' : 'badge-cbo'; ?>">
                                                            <?php echo strtoupper($relato['classificacao']) . ': ' . htmlspecialchars($relato['cnae_cbo_codigo']) . ' - ' . htmlspecialchars(substr($relato['cnae_cbo_descricao'], 0, 30)) . '...'; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($relato['agente_descricao'] && !$agente_data): ?>
                                                        <span class="badge text-bg-secondary badge-agent">
                                                            Agente: <?php echo htmlspecialchars(substr($relato['agente_descricao'], 0, 30)); ?>...
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($relato['cid_codigo'] && !$cid_data): ?>
                                                        <span class="badge text-bg-secondary badge-cid">
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
    <footer class="text-center py-4 border-top border mt-5">
        <div class="container">
            <p class="mb-1 text-muted small">LDRT Query Application &copy; 2026 - Em conformidade com a Portaria GM/MS 1.999/2023</p>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>

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
