<?php
require_once __DIR__ . '/src/db.php';

$ldrtOnly = isset($_GET['ldrt']) && $_GET['ldrt'] == '1';
$chapters = [];
try {
    $db = getDBConnection();
    // Fetch top-level chapters (where parent_id is null)
    if ($ldrtOnly) {
        $stmt = $db->query("
            WITH RECURSIVE ldrt_cids AS (
                SELECT id, parent_id FROM cid WHERE id IN (SELECT DISTINCT cid_id FROM agente_cid)
                UNION
                SELECT p.id, p.parent_id FROM cid p JOIN ldrt_cids child ON child.parent_id = p.id
            )
            SELECT c.id, c.codigo, c.descricao,
                   (SELECT COUNT(*) FROM cid sub WHERE sub.parent_id = c.id AND sub.id IN (SELECT id FROM ldrt_cids)) > 0 AS has_children
            FROM cid c
            WHERE c.parent_id IS NULL AND c.id IN (SELECT id FROM ldrt_cids)
            ORDER BY c.id ASC
        ");
    } else {
        $stmt = $db->query("
            SELECT id, codigo, descricao,
                   (SELECT COUNT(*) FROM cid sub WHERE sub.parent_id = c.id) > 0 AS has_children
            FROM cid c
            WHERE c.parent_id IS NULL
            ORDER BY c.id ASC
        ");
    }
    $chapters = $stmt->fetchAll();
    
    // Map has_children to boolean
    foreach ($chapters as &$chapter) {
        $chapter['has_children'] = (bool)$chapter['has_children'];
    }
} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-module="ldrt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LDRT - Explorador CID-10</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/favicon.png">
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
            --bg-glow-1: rgba(99, 102, 241, 0.06);
            --bg-glow-2: rgba(168, 85, 247, 0.04);
            --card-bg: rgba(255, 255, 255, 0.65);
            --border-color: rgba(0, 0, 0, 0.08);
            --accent-color: #4f46e5;
            --accent-hover: #3730a3;
            --text-muted: #64748b;
            --text-color: #1e293b;
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.06);
            --navbar-bg: rgba(241, 245, 249, 0.85);
            --field-bg: #ffffff;
        }

        [data-bs-theme="dark"] {
            --bg-color: #0b0f19;
            --bg-glow-1: rgba(99, 102, 241, 0.12);
            --bg-glow-2: rgba(168, 85, 247, 0.08);
            --card-bg: rgba(22, 28, 45, 0.7);
            --border-color: rgba(255, 255, 255, 0.08);
            --accent-color: #6366f1;
            --accent-hover: #4f46e5;
            --text-muted: #94a3b8;
            --text-color: #f8fafc;
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            --navbar-bg: rgba(11, 15, 25, 0.85);
            --field-bg: #111827;
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
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }

                .navbar {
            background-color: var(--navbar-bg);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
        }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: var(--glass-shadow);
        }

        .tree-container {
            max-height: calc(100vh - 220px);
            overflow-y: auto;
            padding-right: 10px;
        }

        .tree-node {
            list-style: none;
            padding-left: 15px;
            margin-top: 5px;
        }

        .tree-node-label {
            display: flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s ease;
            font-size: 0.9rem;
            color: #cbd5e1;
        }

        .tree-node-label:hover {
            background-color: rgba(255, 255, 255, 0.04);
            color: var(--text-color);
        }

        .tree-node-label.active {
            background-color: rgba(99, 102, 241, 0.15);
            color: #a5b4fc;
            border-left: 3px solid var(--accent-color);
        }

        .tree-toggle {
            cursor: pointer;
            width: 20px;
            display: inline-block;
            text-align: center;
            margin-right: 5px;
            color: var(--text-muted);
            transition: color 0.15s ease;
        }

        .tree-toggle:hover {
            color: var(--text-color);
        }

        .info-box {
            background: rgba(255, 255, 255, 0.02);
            border-left: 3px solid var(--accent-color);
            padding: 15px;
            border-radius: 0 8px 8px 0;
            margin-bottom: 20px;
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

        .breadcrumb-item + .breadcrumb-item::before {
            color: var(--text-muted);
            content: "\f105";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
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
    require_once __DIR__ . '/../includes/navbar.php';
    render_platform_navbar('ldrt', 'explorar_cid');
    ?>

    <div class="container-fluid my-4 px-lg-5">
        <div class="row g-4">
            
            <!-- Left Panel: CID-10 Tree Explorer -->
            <div class="col-lg-5 col-xl-4">
                <div class="glass-card p-4">
                    <h4 class="mb-3 d-flex align-items-center gap-2">
                        <i class="fa-solid fa-folder-tree text-primary" style="color: var(--accent-color) !important;"></i>
                        Árvore CID-10
                    </h4>
                    
                    <!-- Search inside tree -->
                    <div class="input-group mb-2">
                        <span class="input-group-text"><i class="fa-solid fa-search text-muted"></i></span>
                        <input type="text" id="tree_search" class="form-control" placeholder="Buscar código ou termo na árvore...">
                    </div>
                    
                    <!-- Toggle Switch LDRT only -->
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="ldrt_switch" <?php echo $ldrtOnly ? 'checked' : ''; ?>>
                        <label class="form-check-label text-light small" for="ldrt_switch">
                            Apenas CIDs relacionados à LDRT
                        </label>
                    </div>
                    
                    <div class="tree-container">
                        <ul class="p-0" id="cid_root_list">
                            <?php foreach ($chapters as $ch): ?>
                                <li class="tree-node" data-id="<?php echo $ch['id']; ?>">
                                    <div class="tree-node-label" onclick="selectCid(<?php echo $ch['id']; ?>, this)">
                                        <?php if ($ch['has_children']): ?>
                                            <span class="tree-toggle" onclick="toggleNode(event, <?php echo $ch['id']; ?>, this)">
                                                <i class="fa-solid fa-chevron-right"></i>
                                            </span>
                                        <?php else: ?>
                                            <span class="tree-toggle"></span>
                                        <?php endif; ?>
                                        <span class="badge badge-custom badge-cid me-2"><?php echo htmlspecialchars($ch['codigo']); ?></span>
                                        <span class="text-truncate"><?php echo htmlspecialchars($ch['descricao']); ?></span>
                                    </div>
                                    <ul class="p-0 d-none" id="children_of_<?php echo $ch['id']; ?>"></ul>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Right Panel: CID details and relations -->
            <div class="col-lg-7 col-xl-8">
                <div class="glass-card p-4 h-100" id="details_panel">
                    <div class="text-center py-5" id="details_default_view">
                        <i class="fa-solid fa-circle-info text-muted mb-4" style="font-size: 4rem; opacity: 0.25;"></i>
                        <h3>Selecione um item da árvore</h3>
                        <p class="text-muted mx-auto" style="max-width: 480px;">
                            Navegue na árvore hierárquica do CID-10 à esquerda. Expanda os capítulos e grupos até chegar na categoria ou subcategory desejada para visualizar os riscos ocupacionais e os relatos associados na LDRT.
                        </p>
                    </div>
                    
                    <!-- Dynamic Details View (hidden by default) -->
                    <div class="d-none" id="details_active_view">
                        <!-- Breadcrumb Path -->
                        <nav aria-label="breadcrumb" class="mb-3">
                            <ol class="breadcrumb" id="details_path"></ol>
                        </nav>
                        
                        <div class="info-box">
                            <div class="text-muted small font-weight-bold" id="details_level_label">SUBCATEGORIA</div>
                            <h3 class="mt-1 text-primary" id="details_code">M51.0</h3>
                            <h5 class="text-light" id="details_description">Transtornos de discos lombares e de outros discos intervertebrais com mielopatia</h5>
                        </div>

                        <div class="row g-4 mt-2">
                            <!-- Associated Agents -->
                            <div class="col-12">
                                <h5 class="section-title"><i class="fa-solid fa-biohazard"></i> Agentes de Risco Relacionados (LDRT Lista B)</h5>
                                <div id="details_agents_list" class="list-group list-group-flush rounded border border-secondary overflow-hidden">
                                    <!-- Dynamic content -->
                                </div>
                                <p id="details_agents_empty" class="text-muted small d-none">Nenhum agente causador direto mapeado especificamente para este código.</p>
                            </div>

                            <!-- Associated Case Reports -->
                            <div class="col-12 mt-4">
                                <h5 class="section-title"><i class="fa-solid fa-file-medical"></i> Relatos de Casos</h5>
                                <div id="details_relatos_list">
                                    <!-- Dynamic content -->
                                </div>
                                <p id="details_relatos_empty" class="text-muted small d-none">Não há relatos de caso cadastrados para este código CID-10.</p>
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
            <p class="mb-1 text-muted small">LDRT Explorador CID-10 &copy; 2026 - Conforme Portaria GM/MS 1.999/2023</p>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Tree Explorer JS -->
    <script>
        const loadedNodes = {}; // Tracks which node children have been loaded via AJAX
        const ldrtOnly = <?php echo $ldrtOnly ? 'true' : 'false'; ?>;

        document.getElementById('ldrt_switch').addEventListener('change', function() {
            window.location.href = 'explorar_cid.php?ldrt=' + (this.checked ? '1' : '0');
        });

        function toggleNode(event, nodeId, element) {
            // Prevent event bubbling to the label click event
            event.stopPropagation();
            
            const li = element.closest('.tree-node');
            const sublist = document.getElementById('children_of_' + nodeId);
            const icon = element.querySelector('i');

            if (sublist.classList.contains('d-none')) {
                // Expanding node
                sublist.classList.remove('d-none');
                icon.classList.replace('fa-chevron-right', 'fa-chevron-down');
                
                // Load children via AJAX if not already loaded
                if (!loadedNodes[nodeId]) {
                    sublist.innerHTML = '<li class="tree-node text-muted small ps-3 py-1"><i class="fa-solid fa-spinner fa-spin me-2"></i>Carregando...</li>';
                    fetch(`api_get_children.php?parent_id=${nodeId}&ldrt_only=${ldrtOnly ? '1' : '0'}`)
                        .then(res => res.json())
                        .then(data => {
                            sublist.innerHTML = '';
                            if (data.length === 0) {
                                sublist.innerHTML = '<li class="tree-node text-muted small ps-3 py-1">Sem subpastas</li>';
                                return;
                            }
                            
                            data.forEach(child => {
                                const childLi = document.createElement('li');
                                childLi.classList.add('tree-node');
                                childLi.dataset.id = child.id;
                                
                                const labelDiv = document.createElement('div');
                                labelDiv.classList.add('tree-node-label');
                                labelDiv.setAttribute('onclick', `selectCid(${child.id}, this)`);
                                
                                if (child.has_children) {
                                    const toggleSpan = document.createElement('span');
                                    toggleSpan.classList.add('tree-toggle');
                                    toggleSpan.setAttribute('onclick', `toggleNode(event, ${child.id}, this)`);
                                    toggleSpan.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
                                    labelDiv.appendChild(toggleSpan);
                                } else {
                                    const emptySpan = document.createElement('span');
                                    emptySpan.classList.add('tree-toggle');
                                    labelDiv.appendChild(emptySpan);
                                }
                                
                                const codeBadge = document.createElement('span');
                                codeBadge.classList.add('badge', 'badge-custom', 'badge-cid', 'me-2');
                                codeBadge.textContent = child.codigo;
                                labelDiv.appendChild(codeBadge);
                                
                                const textSpan = document.createElement('span');
                                textSpan.classList.add('text-truncate');
                                textSpan.textContent = child.descricao;
                                labelDiv.appendChild(textSpan);
                                
                                childLi.appendChild(labelDiv);
                                
                                if (child.has_children) {
                                    const nestedUl = document.createElement('ul');
                                    nestedUl.id = 'children_of_' + child.id;
                                    nestedUl.classList.add('p-0', 'd-none');
                                    childLi.appendChild(nestedUl);
                                }
                                
                                sublist.appendChild(childLi);
                            });
                            
                            loadedNodes[nodeId] = true;
                        })
                        .catch(err => {
                            sublist.innerHTML = `<li class="tree-node text-danger small ps-3 py-1">Erro ao carregar: ${err.message}</li>`;
                        });
                }
            } else {
                // Collapsing node
                sublist.classList.add('d-none');
                icon.classList.replace('fa-chevron-down', 'fa-chevron-right');
            }
        }

        function selectCid(cidId, element) {
            // Remove active class from previous active label
            const activeLabel = document.querySelector('.tree-node-label.active');
            if (activeLabel) {
                activeLabel.classList.remove('active');
            }
            element.classList.add('active');
            
            // Show loader in details panel
            const defaultView = document.getElementById('details_default_view');
            const activeView = document.getElementById('details_active_view');
            defaultView.classList.add('d-none');
            activeView.classList.remove('d-none');
            
            document.getElementById('details_code').textContent = 'Carregando...';
            document.getElementById('details_description').textContent = '';
            document.getElementById('details_level_label').textContent = '';
            document.getElementById('details_path').innerHTML = '';
            document.getElementById('details_agents_list').innerHTML = '';
            document.getElementById('details_relatos_list').innerHTML = '';
            
            // Fetch CID details via AJAX
            fetch(`api_get_cid_details.php?id=${cidId}`)
                .then(res => res.json())
                .then(data => {
                    const details = data.details;
                    
                    // Render details
                    document.getElementById('details_code').textContent = details.codigo;
                    document.getElementById('details_description').textContent = details.descricao;
                    document.getElementById('details_level_label').textContent = details.nivel.toUpperCase();
                    
                    // Render breadcrumb path
                    const pathContainer = document.getElementById('details_path');
                    data.hierarchy.forEach((node, index) => {
                        const li = document.createElement('li');
                        li.classList.add('breadcrumb-item');
                        if (index === data.hierarchy.length - 1) {
                            li.classList.add('active');
                            li.setAttribute('aria-current', 'page');
                            li.textContent = node.codigo;
                        } else {
                            li.innerHTML = `<a href="#" class="text-indigo text-decoration-none" onclick="navigateToCid(${node.id})">${node.codigo}</a>`;
                        }
                        pathContainer.appendChild(li);
                    });
                    
                    // Render agents
                    const agentsList = document.getElementById('details_agents_list');
                    const agentsEmpty = document.getElementById('details_agents_empty');
                    
                    if (data.agents.length === 0) {
                        agentsList.classList.add('d-none');
                        agentsEmpty.classList.remove('d-none');
                    } else {
                        agentsList.classList.remove('d-none');
                        agentsEmpty.classList.add('d-none');
                        data.agents.forEach(agent => {
                            const div = document.createElement('div');
                            div.classList.add('list-group-item', 'bg-dark', 'border-secondary', 'p-3', 'd-flex', 'justify-content-between', 'align-items-center');
                            div.innerHTML = `
                                <div>
                                    <strong class="text-light">${agent.descricao}</strong>
                                    ${agent.cas ? `<div class="text-muted small mt-1">CAS: ${agent.cas}</div>` : ''}
                                </div>
                                <a href="consulta.php?cid=${encodeURIComponent(details.codigo)}&agente=${agent.id}" class="btn btn-sm btn-outline-secondary">Consulta Completa</a>
                            `;
                            agentsList.appendChild(div);
                        });
                    }
                    
                    // Render relatos
                    const relatosList = document.getElementById('details_relatos_list');
                    const relatosEmpty = document.getElementById('details_relatos_empty');
                    
                    if (data.relatos.length === 0) {
                        relatosEmpty.classList.remove('d-none');
                    } else {
                        relatosEmpty.classList.add('d-none');
                        data.relatos.forEach(rel => {
                            const card = document.createElement('div');
                            card.classList.add('card', 'bg-dark', 'border-secondary', 'mb-3');
                            card.innerHTML = `
                                <div class="card-header border-secondary d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0 text-primary-emphasis">${rel.titulo}</h6>
                                    <span class="badge bg-secondary font-monospace">${rel.old_id}</span>
                                </div>
                                <div class="card-body">
                                    <p class="card-text small text-light-emphasis" style="white-space: pre-wrap;">${rel.relato}</p>
                                    <div class="border-top border-secondary pt-3 mt-3 d-flex gap-2">
                                        <span class="text-muted small">CNAE/CBO vinculado:</span>
                                        <span class="badge badge-custom ${rel.classificacao === 'cnae' ? 'badge-cnae' : 'badge-cbo'}">
                                            ${rel.classificacao.toUpperCase()}: ${rel.cnae_cbo_codigo} - ${rel.cnae_cbo_descricao.substring(0, 30)}...
                                        </span>
                                    </div>
                                </div>
                            `;
                            relatosList.appendChild(card);
                        });
                    }
                })
                .catch(err => {
                    document.getElementById('details_code').textContent = 'Erro';
                    document.getElementById('details_description').textContent = `Falha ao carregar detalhes: ${err.message}`;
                });
        }

        function navigateToCid(cidId) {
            // Find in tree and click
            const node = document.querySelector(`.tree-node[data-id="${cidId}"] > .tree-node-label`);
            if (node) {
                node.click();
            }
        }
        
        // Tree Search logic
        document.getElementById('tree_search').addEventListener('input', function() {
            const term = this.value.trim().toLowerCase();
            const rootItems = document.querySelectorAll('#cid_root_list > .tree-node');
            
            if (term.length < 2) {
                // Restore all root nodes
                rootItems.forEach(item => {
                    item.classList.remove('d-none');
                });
                return;
            }
            
            // Basic filter on root items (for a deeper search, we suggest using the main "Consulta Cruzada" page)
            rootItems.forEach(item => {
                const labelText = item.querySelector('.tree-node-label').textContent.toLowerCase();
                if (labelText.includes(term)) {
                    item.classList.remove('d-none');
                } else {
                    item.classList.add('d-none');
                }
            });
        });
    </script>
</body>
</html>
