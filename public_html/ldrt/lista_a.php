<?php
require_once __DIR__ . '/../../ldrt/src/db.php';

$error_message = null;
$lista_a_data = [];
$lista_b_data = [];
$cidId = isset($_GET['cid_id']) && is_numeric($_GET['cid_id']) ? intval($_GET['cid_id']) : null;
$ancestors = [];

try {
    $db = getDBConnection();
    
    // If cid_id is provided, check if it's valid and get its ancestors
    if ($cidId) {
        $checkStmt = $db->prepare("SELECT id FROM cid WHERE id = :cid_id");
        $checkStmt->execute(['cid_id' => $cidId]);
        if (!$checkStmt->fetch()) {
            $cidId = null; // invalid ID, reset to null
        } else {
            $pathStmt = $db->prepare("
                WITH RECURSIVE ancestors AS (
                    SELECT id, codigo, descricao, parent_id, 1 as depth
                    FROM cid
                    WHERE id = :cid_id
                    UNION ALL
                    SELECT c.id, c.codigo, c.descricao, c.parent_id, a.depth + 1
                    FROM cid c
                    JOIN ancestors a ON c.id = a.parent_id
                )
                SELECT id, codigo, descricao, parent_id FROM ancestors ORDER BY depth DESC
            ");
            $pathStmt->execute(['cid_id' => $cidId]);
            $ancestors = $pathStmt->fetchAll();
        }
    }

    // 1. Fetch Lista A using Recursive CTE to find top-level categories
    if ($cidId) {
        $stmt = $db->prepare("
            WITH RECURSIVE selected_cids AS (
                SELECT id FROM cid WHERE id = :cid_id
                UNION ALL
                SELECT c.id FROM cid c JOIN selected_cids sc ON c.parent_id = sc.id
            ),
            agent_hierarchy AS (
                SELECT id, parent_id, id AS top_parent_id, descricao AS top_parent_name
                FROM agentes
                WHERE parent_id IS NULL
                UNION ALL
                SELECT a.id, a.parent_id, h.top_parent_id, h.top_parent_name
                FROM agentes a
                JOIN agent_hierarchy h ON a.parent_id = h.id
            )
            SELECT 
                h.top_parent_name AS categoria,
                a.id AS agente_id,
                a.descricao AS agente,
                c.codigo AS cid_codigo,
                c.descricao AS cid_descricao
            FROM agente_cid ac
            JOIN agentes a ON ac.agente_id = a.id
            JOIN cid c ON ac.cid_id = c.id
            JOIN agent_hierarchy h ON a.id = h.id
            WHERE c.id IN (SELECT id FROM selected_cids)
            ORDER BY h.top_parent_id, a.descricao, c.codigo
        ");
        $stmt->execute(['cid_id' => $cidId]);
    } else {
        $stmt = $db->query("
            WITH RECURSIVE agent_hierarchy AS (
                SELECT id, parent_id, id AS top_parent_id, descricao AS top_parent_name
                FROM agentes
                WHERE parent_id IS NULL
                UNION ALL
                SELECT a.id, a.parent_id, h.top_parent_id, h.top_parent_name
                FROM agentes a
                JOIN agent_hierarchy h ON a.parent_id = h.id
            )
            SELECT 
                h.top_parent_name AS categoria,
                a.id AS agente_id,
                a.descricao AS agente,
                c.codigo AS cid_codigo,
                c.descricao AS cid_descricao
            FROM agente_cid ac
            JOIN agentes a ON ac.agente_id = a.id
            JOIN cid c ON ac.cid_id = c.id
            JOIN agent_hierarchy h ON a.id = h.id
            ORDER BY h.top_parent_id, a.descricao, c.codigo
        ");
    }
    $lista_a_data = $stmt->fetchAll();

    // 2. Fetch Lista B (grouped by CID)
    if ($cidId) {
        $stmt = $db->prepare("
            WITH RECURSIVE selected_cids AS (
                SELECT id FROM cid WHERE id = :cid_id
                UNION ALL
                SELECT c.id FROM cid c JOIN selected_cids sc ON c.parent_id = sc.id
            )
            SELECT 
                c.codigo AS cid_codigo,
                c.descricao AS cid_descricao,
                c.nivel AS cid_nivel,
                string_agg(a.descricao, ' || ') AS agentes
            FROM agente_cid ac
            JOIN agentes a ON ac.agente_id = a.id
            JOIN cid c ON ac.cid_id = c.id
            WHERE c.id IN (SELECT id FROM selected_cids)
            GROUP BY c.id, c.codigo, c.descricao, c.nivel
            ORDER BY c.codigo ASC
        ");
        $stmt->execute(['cid_id' => $cidId]);
    } else {
        $stmt = $db->query("
            SELECT 
                c.codigo AS cid_codigo,
                c.descricao AS cid_descricao,
                c.nivel AS cid_nivel,
                string_agg(a.descricao, ' || ') AS agentes
            FROM agente_cid ac
            JOIN agentes a ON ac.agente_id = a.id
            JOIN cid c ON ac.cid_id = c.id
            GROUP BY c.id, c.codigo, c.descricao, c.nivel
            ORDER BY c.codigo ASC
        ");
    }
    $lista_b_data = $stmt->fetchAll();

} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-module="ldrt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LDRT - Tabelas Oficiais</title>
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

        .nav-tabs {
            border-bottom: 1px solid var(--border-color);
        }

        .nav-tabs .nav-link {
            border: none;
            color: var(--text-muted);
            font-weight: 500;
            padding: 12px 24px;
        }

        .nav-tabs .nav-link.active {
            background-color: transparent;
            color: var(--accent-color);
            border-bottom: 2px solid var(--accent-border);
        }

        .table-responsive {
            max-height: calc(100vh - 300px);
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: 12px;
        }

        .table th {
            position: sticky;
            top: 0;
            background-color: #1e293b;
            z-index: 10;
            border-bottom: 2px solid var(--border-color);
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
            padding: 10px 15px;
        }

        .form-control:focus,
        .form-select:focus {
            background-color: var(--field-bg) !important;
            border-color: var(--accent-color);
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

        .badge-custom {
            font-size: 0.75rem;
            font-weight: 500;
            padding: 4px 8px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .badge-cid { background-color: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
        .badge-agent { background-color: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }

        .category-header {
            background-color: var(--bs-tertiary-bg) !important;
            font-weight: 600;
            color: var(--accent-ui);
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
    render_platform_navbar('ldrt', 'tabelas');
    ?>

    <div class="container-fluid my-4 px-lg-5">
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                <strong>Erro:</strong> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="glass-card p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                <div>
                    <h3>Tabelas Oficiais da LDRT</h3>
                    <p class="text-muted mb-0 small">Reprodução digitalizada e indexada das Listas A e B da Portaria GM/MS 1.999/2023.</p>
                </div>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <!-- Page Size Selector -->
                    <div class="d-flex align-items-center gap-2">
                        <label for="page_size_select" class="text-muted small text-nowrap">Itens por página:</label>
                        <select id="page_size_select" class="form-select form-select-sm" style="width: 80px;">
                            <option value="15">15</option>
                            <option value="30" selected>30</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                    <!-- Filter Input -->
                    <div style="max-width: 300px;" class="input-group input-group-sm">
                        <span class="input-group-text"><i class="fa-solid fa-filter text-muted"></i></span>
                        <input type="text" id="table_filter" class="form-control" placeholder="Filtrar tabela atual...">
                    </div>
                </div>
            </div>
            <!-- Hierarchical CID-10 Filter Box -->
            <div class="p-3 mb-4 rounded-3 border border-secondary border-opacity-25" style="background: rgba(255, 255, 255, 0.02);">
                <form id="filter_form" method="GET" class="row g-3 align-items-end">
                    <input type="hidden" name="cid_id" id="cid_id_val" value="<?php echo htmlspecialchars($cidId ?? ''); ?>">
                    
                    <div class="col-12 col-md-3">
                        <label for="cid_chapter" class="form-label small text-muted mb-1">Capítulo CID-10</label>
                        <select id="cid_chapter" class="form-select form-select-sm">
                            <option value="">Todos os Capítulos</option>
                        </select>
                    </div>
                    
                    <div class="col-12 col-md-3 d-none" id="block_container">
                        <label for="cid_block" class="form-label small text-muted mb-1">Grupo</label>
                        <select id="cid_block" class="form-select form-select-sm">
                            <option value="">Todos os Grupos</option>
                        </select>
                    </div>
                    
                    <div class="col-12 col-md-3 d-none" id="category_container">
                        <label for="cid_category" class="form-label small text-muted mb-1">Categoria</label>
                        <select id="cid_category" class="form-select form-select-sm">
                            <option value="">Todas as Categorias</option>
                        </select>
                    </div>
                    
                    <div class="col-12 col-md-3 d-none" id="subcategory_container">
                        <label for="cid_subcategory" class="form-label small text-muted mb-1">Subcategoria</label>
                        <select id="cid_subcategory" class="form-select form-select-sm">
                            <option value="">Todas as Subcategorias</option>
                        </select>
                    </div>
                    
                    <?php if ($cidId): ?>
                        <div class="col-auto">
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearCidFilter()">
                                <i class="fa-solid fa-times me-1"></i>Limpar Filtro CID
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-3" id="listTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="list-a-tab" data-bs-toggle="tab" data-bs-target="#list-a-panel" type="button" role="tab">
                        <i class="fa-solid fa-flask me-1"></i> Lista A (Agente ➔ CID)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="list-b-tab" data-bs-toggle="tab" data-bs-target="#list-b-panel" type="button" role="tab">
                        <i class="fa-solid fa-stethoscope me-1"></i> Lista B (CID ➔ Agente)
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="listTabsContent">
                
                <!-- LIST A PANEL -->
                <div class="tab-pane fade show active" id="list-a-panel" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0 align-middle" id="table_lista_a">
                            <thead>
                                <tr>
                                    <th onclick="sortList('a', 'categoria')" style="cursor:pointer; user-select:none;">
                                        Categoria de Risco <span id="sort_icon_a_categoria" class="ms-1 text-muted"><i class="fa-solid fa-sort"></i></span>
                                    </th>
                                    <th onclick="sortList('a', 'agente')" style="cursor:pointer; user-select:none;">
                                        Agente / Fator de Risco <span id="sort_icon_a_agente" class="ms-1 text-muted"><i class="fa-solid fa-sort"></i></span>
                                    </th>
                                    <th onclick="sortList('a', 'cid_codigo')" style="cursor:pointer; user-select:none;">
                                        CID-10 <span id="sort_icon_a_cid_codigo" class="ms-1 text-muted"><i class="fa-solid fa-sort"></i></span>
                                    </th>
                                    <th onclick="sortList('a', 'cid_descricao')" style="cursor:pointer; user-select:none;">
                                        Doença Relacionada ao Trabalho <span id="sort_icon_a_cid_descricao" class="ms-1 text-muted"><i class="fa-solid fa-sort"></i></span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="tbody_lista_a">
                                <!-- Populated dynamically by JS -->
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-3 gap-2">
                        <div class="text-muted small" id="pagination_info_a">
                            Exibindo 0 a 0 de 0 registros
                        </div>
                        <nav aria-label="Navegação da Lista A">
                            <ul class="pagination pagination-sm mb-0 bg-dark" id="pagination_controls_a">
                                <!-- Pagination items -->
                            </ul>
                        </nav>
                    </div>
                </div>
                
                <!-- LIST B PANEL -->
                <div class="tab-pane fade" id="list-b-panel" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0 align-middle" id="table_lista_b">
                            <thead>
                                <tr>
                                    <th onclick="sortList('b', 'cid_codigo')" style="cursor:pointer; user-select:none;">
                                        CID-10 <span id="sort_icon_b_cid_codigo" class="ms-1 text-muted"><i class="fa-solid fa-sort"></i></span>
                                    </th>
                                    <th onclick="sortList('b', 'cid_descricao')" style="cursor:pointer; user-select:none;">
                                        Doença <span id="sort_icon_b_cid_descricao" class="ms-1 text-muted"><i class="fa-solid fa-sort"></i></span>
                                    </th>
                                    <th onclick="sortList('b', 'cid_nivel')" style="cursor:pointer; user-select:none;">
                                        Nível <span id="sort_icon_b_cid_nivel" class="ms-1 text-muted"><i class="fa-solid fa-sort"></i></span>
                                    </th>
                                    <th onclick="sortList('b', 'agentes')" style="cursor:pointer; user-select:none;">
                                        Agentes / Fatores de Risco Relacionados <span id="sort_icon_b_agentes" class="ms-1 text-muted"><i class="fa-solid fa-sort"></i></span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="tbody_lista_b">
                                <!-- Populated dynamically by JS -->
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-3 gap-2">
                        <div class="text-muted small" id="pagination_info_b">
                            Exibindo 0 a 0 de 0 registros
                        </div>
                        <nav aria-label="Navegação da Lista B">
                            <ul class="pagination pagination-sm mb-0 bg-dark" id="pagination_controls_b">
                                <!-- Pagination items -->
                            </ul>
                        </nav>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="text-center py-4 border-top border-secondary mt-5" style="background-color: rgba(11, 15, 25, 0.5);">
        <div class="container">
            <p class="mb-1 text-muted small">LDRT Tabelas Oficiais &copy; 2026 - Conforme Portaria GM/MS 1.999/2023</p>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Client-side sorting, filtering, and pagination script -->
    <script>
        const initialPath = <?php echo json_encode($ancestors, JSON_UNESCAPED_UNICODE); ?>;
        const initialCidId = <?php echo json_encode($cidId); ?>;

        // State variables for Lists
        let currentTab = 'a'; // 'a' or 'b'
        let pageSize = 30;

        const state = {
            a: {
                data: <?php echo json_encode($lista_a_data, JSON_UNESCAPED_UNICODE); ?>,
                filtered: [],
                currentPage: 1,
                sortField: '',
                sortAsc: true
            },
            b: {
                data: <?php echo json_encode($lista_b_data, JSON_UNESCAPED_UNICODE); ?>,
                filtered: [],
                currentPage: 1,
                sortField: '',
                sortAsc: true
            }
        };

        // Initialize lists
        state.a.filtered = [...state.a.data];
        state.b.filtered = [...state.b.data];

        // Function to render a list
        function renderTable(listKey) {
            const listState = state[listKey];
            const tbody = document.getElementById('tbody_lista_' + listKey);
            tbody.innerHTML = '';

            const startIndex = (listState.currentPage - 1) * pageSize;
            const endIndex = Math.min(startIndex + pageSize, listState.filtered.length);
            const pageData = listState.filtered.slice(startIndex, endIndex);

            if (pageData.length === 0) {
                const colSpan = 4;
                tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center text-muted py-4">Nenhum registro encontrado</td></tr>`;
                document.getElementById('pagination_info_' + listKey).textContent = 'Exibindo 0 a 0 de 0 registros';
                document.getElementById('pagination_controls_' + listKey).innerHTML = '';
                return;
            }

            if (listKey === 'a') {
                pageData.forEach(row => {
                    const tr = document.createElement('tr');
                    tr.className = 'data-row';
                    tr.innerHTML = `
                        <td class="small text-muted">${escapeHtml(row.categoria)}</td>
                        <td class="fw-medium text-light">${escapeHtml(row.agente)}</td>
                        <td>
                            <a href="consulta.php?cid=${encodeURIComponent(row.cid_codigo)}" class="badge badge-custom badge-cid text-decoration-none" title="${escapeHtml(row.cid_descricao)}">
                                ${escapeHtml(row.cid_codigo)}
                            </a>
                        </td>
                        <td class="small text-light-emphasis">${escapeHtml(row.cid_descricao)}</td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                pageData.forEach(row => {
                    const tr = document.createElement('tr');
                    tr.className = 'data-row';
                    
                    const agents = (row.agentes || '').split(' || ');
                    let agentsHtml = '<div class="d-flex flex-column gap-1">';
                    agents.forEach(agent => {
                        agentsHtml += `<div class="p-1 rounded bg-dark border border-secondary border-opacity-50"><i class="fa-solid fa-biohazard text-danger me-1 small"></i>${escapeHtml(agent)}</div>`;
                    });
                    agentsHtml += '</div>';

                    tr.innerHTML = `
                        <td>
                            <a href="consulta.php?cid=${encodeURIComponent(row.cid_codigo)}" class="badge badge-custom badge-cid text-decoration-none" title="${escapeHtml(row.cid_descricao)}">
                                ${escapeHtml(row.cid_codigo)}
                            </a>
                        </td>
                        <td class="fw-medium text-light small">${escapeHtml(row.cid_descricao)}</td>
                        <td><span class="badge bg-secondary font-monospace" style="font-size: 0.7rem;">${escapeHtml(row.cid_nivel)}</span></td>
                        <td class="small text-light-emphasis">${agentsHtml}</td>
                    `;
                    tbody.appendChild(tr);
                });
            }

            // Update info labels
            const infoEl = document.getElementById('pagination_info_' + listKey);
            infoEl.textContent = `Exibindo ${startIndex + 1} a ${endIndex} de ${listState.filtered.length} registros`;

            updatePaginationControls(listKey);
        }

        function updatePaginationControls(listKey) {
            const listState = state[listKey];
            const container = document.getElementById('pagination_controls_' + listKey);
            container.innerHTML = '';

            const totalPages = Math.ceil(listState.filtered.length / pageSize);
            if (totalPages <= 1) return;

            // Previous Button
            const prevLi = document.createElement('li');
            prevLi.className = `page-item ${listState.currentPage === 1 ? 'disabled' : ''}`;
            prevLi.innerHTML = `<a class="page-link bg-dark text-light border-secondary" href="#" onclick="changePage('${listKey}', ${listState.currentPage - 1}); return false;">Anterior</a>`;
            container.appendChild(prevLi);

            // Determine page range to show (max 5 pages)
            let startPage = Math.max(1, listState.currentPage - 2);
            let endPage = Math.min(totalPages, startPage + 4);
            if (endPage - startPage < 4) {
                startPage = Math.max(1, endPage - 4);
            }

            for (let i = startPage; i <= endPage; i++) {
                const li = document.createElement('li');
                li.className = `page-item ${listState.currentPage === i ? 'active' : ''}`;
                li.innerHTML = `<a class="page-link ${listState.currentPage === i ? 'bg-indigo border-indigo text-white' : 'bg-dark text-light border-secondary'}" href="#" onclick="changePage('${listKey}', ${i}); return false;">${i}</a>`;
                container.appendChild(li);
            }

            // Next Button
            const nextLi = document.createElement('li');
            nextLi.className = `page-item ${listState.currentPage === totalPages ? 'disabled' : ''}`;
            nextLi.innerHTML = `<a class="page-link bg-dark text-light border-secondary" href="#" onclick="changePage('${listKey}', ${listState.currentPage + 1}); return false;">Próximo</a>`;
            container.appendChild(nextLi);
        }

        function changePage(listKey, pageNum) {
            const listState = state[listKey];
            const totalPages = Math.ceil(listState.filtered.length / pageSize);
            if (pageNum < 1 || pageNum > totalPages) return;
            listState.currentPage = pageNum;
            renderTable(listKey);
        }

        // Sorting Logic
        function sortList(listKey, field) {
            const listState = state[listKey];
            
            // Toggle direction if same field, else default to ascending
            if (listState.sortField === field) {
                listState.sortAsc = !listState.sortAsc;
            } else {
                listState.sortField = field;
                listState.sortAsc = true;
            }

            // Reset all headers for this table
            const headers = listKey === 'a' ? ['categoria', 'agente', 'cid_codigo', 'cid_descricao'] : ['cid_codigo', 'cid_descricao', 'cid_nivel', 'agentes'];
            headers.forEach(h => {
                const iconSpan = document.getElementById(`sort_icon_${listKey}_${h}`);
                if (iconSpan) {
                    iconSpan.innerHTML = '<i class="fa-solid fa-sort"></i>';
                    iconSpan.className = 'ms-1 text-muted';
                }
            });

            const activeIconSpan = document.getElementById(`sort_icon_${listKey}_${field}`);
            if (activeIconSpan) {
                activeIconSpan.innerHTML = listState.sortAsc ? '<i class="fa-solid fa-sort-up"></i>' : '<i class="fa-solid fa-sort-down"></i>';
                activeIconSpan.className = 'ms-1 text-primary';
            }

            // Perform sort
            listState.filtered.sort((rowA, rowB) => {
                let valA = (rowA[field] || '').toString().toLowerCase();
                let valB = (rowB[field] || '').toString().toLowerCase();
                return valA.localeCompare(valB, 'pt-BR', { numeric: true, sensitivity: 'base' }) * (listState.sortAsc ? 1 : -1);
            });

            listState.currentPage = 1;
            renderTable(listKey);
        }

        // Filtering Logic
        function applyFilter(filterVal) {
            const term = filterVal.trim().toLowerCase();

            ['a', 'b'].forEach(listKey => {
                const listState = state[listKey];
                if (term === '') {
                    listState.filtered = [...listState.data];
                } else {
                    listState.filtered = listState.data.filter(row => {
                        return Object.keys(row).some(key => {
                            return (row[key] || '').toString().toLowerCase().includes(term);
                        });
                    });
                }

                // Restore sorting state if active
                if (listState.sortField) {
                    const field = listState.sortField;
                    const asc = listState.sortAsc;
                    listState.filtered.sort((rowA, rowB) => {
                        let valA = (rowA[field] || '').toString().toLowerCase();
                        let valB = (rowB[field] || '').toString().toLowerCase();
                        return valA.localeCompare(valB, 'pt-BR', { numeric: true, sensitivity: 'base' }) * (asc ? 1 : -1);
                    });
                }

                listState.currentPage = 1;
                renderTable(listKey);
            });
        }

        // Helper to escape HTML characters in JS
        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        // Tab switching listeners
        document.getElementById('list-a-tab').addEventListener('shown.bs.tab', function () {
            currentTab = 'a';
        });

        document.getElementById('list-b-tab').addEventListener('shown.bs.tab', function () {
            currentTab = 'b';
        });

        // Filter search input listener
        document.getElementById('table_filter').addEventListener('input', function() {
            applyFilter(this.value);
        });

        // Page size selector listener
        document.getElementById('page_size_select').addEventListener('change', function() {
            pageSize = parseInt(this.value, 10);
            state.a.currentPage = 1;
            state.b.currentPage = 1;
            renderTable('a');
            renderTable('b');
        });

        // Hierarchical CID-10 logic
        async function loadCidOptions(parentId, selectElement, selectContainer) {
            try {
                const response = await fetch(`api_get_children.php?parent_id=${parentId}`);
                if (!response.ok) throw new Error("Erro de rede");
                const data = await response.json();
                
                // Clear and add default option
                const defaultText = selectElement.options[0].text;
                selectElement.innerHTML = `<option value="">${defaultText}</option>`;
                
                if (data.length > 0) {
                    data.forEach(item => {
                        const opt = document.createElement('option');
                        opt.value = item.id;
                        opt.textContent = `${item.codigo} - ${item.descricao} (${item.agente_count || 0})`;
                        opt.dataset.hasChildren = item.has_children ? '1' : '0';
                        selectElement.appendChild(opt);
                    });
                    selectContainer.classList.remove('d-none');
                    return true;
                } else {
                    selectContainer.classList.add('d-none');
                    return false;
                }
            } catch (err) {
                console.error("Erro ao carregar CIDs:", err);
                return false;
            }
        }

        function clearCidFilter() {
            document.getElementById('cid_id_val').value = '';
            document.getElementById('filter_form').submit();
        }

        // Initial rendering & Hierarchical CID-10 loading
        document.addEventListener('DOMContentLoaded', async () => {
            renderTable('a');
            renderTable('b');

            const chSelect = document.getElementById('cid_chapter');
            const blSelect = document.getElementById('cid_block');
            const caSelect = document.getElementById('cid_category');
            const suSelect = document.getElementById('cid_subcategory');
            
            const chCont = chSelect.parentElement;
            const blCont = document.getElementById('block_container');
            const caCont = document.getElementById('category_container');
            const suCont = document.getElementById('subcategory_container');
            
            const cidIdVal = document.getElementById('cid_id_val');
            
            // 1. Load Chapters
            await loadCidOptions(0, chSelect, chCont);
            
            // 2. If there is an initial path, restore the selections
            if (typeof initialPath !== 'undefined' && initialPath && initialPath.length > 0) {
                let currentSelect = chSelect;
                let currentCont = chCont;
                
                for (let i = 0; i < initialPath.length; i++) {
                    const node = initialPath[i];
                    currentSelect.value = node.id;
                    
                    // Determine next level select
                    let nextSelect, nextCont;
                    if (currentSelect === chSelect) {
                        nextSelect = blSelect;
                        nextCont = blCont;
                    } else if (currentSelect === blSelect) {
                        nextSelect = caSelect;
                        nextCont = caCont;
                    } else if (currentSelect === caSelect) {
                        nextSelect = suSelect;
                        nextCont = suCont;
                    }
                    
                    if (nextSelect && i < initialPath.length - 1) {
                        // Load the children for the next select
                        const hasChildren = await loadCidOptions(node.id, nextSelect, nextCont);
                        if (hasChildren) {
                            currentSelect = nextSelect;
                            currentCont = nextCont;
                        } else {
                            break;
                        }
                    } else if (nextSelect && i === initialPath.length - 1) {
                        // Check if the current selected node actually has children, to pre-populate the next select dropdown
                        const selectedOption = currentSelect.selectedOptions[0];
                        if (selectedOption && selectedOption.dataset.hasChildren === '1') {
                            await loadCidOptions(node.id, nextSelect, nextCont);
                        }
                    }
                }
            }
            
            // Helper to reset lower level selects
            function resetLowerLevels(changedSelect) {
                if (changedSelect === chSelect) {
                    blSelect.innerHTML = '<option value="">Todos os Grupos</option>';
                    blCont.classList.add('d-none');
                    caSelect.innerHTML = '<option value="">Todas as Categorias</option>';
                    caCont.classList.add('d-none');
                    suSelect.innerHTML = '<option value="">Todas as Subcategorias</option>';
                    suCont.classList.add('d-none');
                } else if (changedSelect === blSelect) {
                    caSelect.innerHTML = '<option value="">Todas as Categorias</option>';
                    caCont.classList.add('d-none');
                    suSelect.innerHTML = '<option value="">Todas as Subcategorias</option>';
                    suCont.classList.add('d-none');
                } else if (changedSelect === caSelect) {
                    suSelect.innerHTML = '<option value="">Todas as Subcategorias</option>';
                    suCont.classList.add('d-none');
                }
            }
            
            // Event change listeners
            async function handleSelectChange(e) {
                const select = e.target;
                const value = select.value;
                resetLowerLevels(select);
                
                if (value) {
                    cidIdVal.value = value;
                    const selectedOption = select.selectedOptions[0];
                    const hasChildren = selectedOption && selectedOption.dataset.hasChildren === '1';
                    
                    if (hasChildren) {
                        let nextSelect, nextCont;
                        if (select === chSelect) {
                            nextSelect = blSelect;
                            nextCont = blCont;
                        } else if (select === blSelect) {
                            nextSelect = caSelect;
                            nextCont = caCont;
                        } else if (select === caSelect) {
                            nextSelect = suSelect;
                            nextCont = suCont;
                        }
                        
                        if (nextSelect) {
                            await loadCidOptions(value, nextSelect, nextCont);
                        }
                    }
                } else {
                    // If cleared, set cid_id to the parent select's value
                    if (select === chSelect) {
                        cidIdVal.value = '';
                    } else if (select === blSelect) {
                        cidIdVal.value = chSelect.value;
                    } else if (select === caSelect) {
                        cidIdVal.value = blSelect.value;
                    } else if (select === suSelect) {
                        cidIdVal.value = caSelect.value;
                    }
                }
                
                // Submit form to filter server-side
                document.getElementById('filter_form').submit();
            }
            
            chSelect.addEventListener('change', handleSelectChange);
            blSelect.addEventListener('change', handleSelectChange);
            caSelect.addEventListener('change', handleSelectChange);
            suSelect.addEventListener('change', handleSelectChange);
        });
    </script>
</body>
</html>
