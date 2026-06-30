<?php
require_once __DIR__ . '/../../ldrt/src/db.php';

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
            ORDER BY CAST(c.codigo AS INTEGER) ASC
        ");
    } else {
        $stmt = $db->query("
            SELECT id, codigo, descricao,
                   (SELECT COUNT(*) FROM cid sub WHERE sub.parent_id = c.id) > 0 AS has_children
            FROM cid c
            WHERE c.parent_id IS NULL
            ORDER BY CAST(c.codigo AS INTEGER) ASC
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
    <?php
    require_once __DIR__ . '/../../includes/navbar.php';
    render_platform_navbar('ldrt', 'explorar_cid');
    ?>

    <div class="container-fluid my-4 px-lg-5">
        <div class="row g-4">
            
            <!-- Left Panel: CID-10 Tree Explorer -->
            <div class="col-lg-5 col-xl-4">
                <div class="card p-4">
                    <h4 class="mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-folder-symlink text-primary"></i>
                        Árvore CID-10
                    </h4>
                    
                    <!-- Search inside tree -->
                    <div class="input-group mb-2">
                        <span class="input-group-text" id="search_btn"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="tree_search" class="form-control" placeholder="Buscar código ou termo na árvore..." autocomplete="off">
                    </div>
                    
                    <!-- Toggle Switch LDRT only -->
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="ldrt_switch" <?php echo $ldrtOnly ? 'checked' : ''; ?>>
                        <label class="form-check-label text-body small" for="ldrt_switch">
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
                                                <i class="bi bi-chevron-right"></i>
                                            </span>
                                        <?php else: ?>
                                            <span class="tree-toggle"></span>
                                        <?php endif; ?>
                                        <span class="text-truncate"><?php echo htmlspecialchars($ch['codigo'] . ' - ' . $ch['descricao']); ?></span>
                                    </div>
                                    <ul class="p-0 d-none" id="children_of_<?php echo $ch['id']; ?>"></ul>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <ul class="p-0 d-none" id="cid_search_results"></ul>
                    </div>
                </div>
            </div>

            <!-- Right Panel: CID details and relations -->
            <div class="col-lg-7 col-xl-8">
                <div class="card p-4 h-100" id="details_panel">
                    <div class="text-center py-5" id="details_default_view">
                        <i class="bi bi-info-circle text-muted mb-4"></i>
                        <h3>Selecione um item da árvore</h3>
                        <p class="text-muted mx-auto">
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
                            <h5 class="text-body" id="details_description">Transtornos de discos lombares e de outros discos intervertebrais com mielopatia</h5>
                        </div>

                        <div class="row g-4 mt-2">
                            <!-- Associated Agents -->
                            <div class="col-12">
                                <h5 class="section-title"><i class="bi bi-radioactive"></i> Agentes de Risco Relacionados (LDRT Lista B)</h5>
                                <div id="details_agents_list" class="list-group list-group-flush rounded border border overflow-hidden">
                                    <!-- Dynamic content -->
                                </div>
                                <p id="details_agents_empty" class="text-muted small d-none">Nenhum agente causador direto mapeado especificamente para este código.</p>
                            </div>

                            <!-- Associated Case Reports -->
                            <div class="col-12 mt-4">
                                <h5 class="section-title"><i class="bi bi-file-earmark-medical"></i> Relatos de Casos</h5>
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
    <footer class="text-center py-4 border-top border mt-5">
        <div class="container">
            <p class="mb-1 text-muted small">LDRT Explorador CID-10 &copy; 2026 - Conforme Portaria GM/MS 1.999/2023</p>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>

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
                icon.classList.replace('bi-chevron-right', 'bi-chevron-down');
                
                // Load children via AJAX if not already loaded
                if (!loadedNodes[nodeId]) {
                    sublist.innerHTML = '<li class="tree-node text-muted small ps-3 py-1"><i class="bi bi-arrow-clockwise bootstrap-spin me-2"></i>Carregando...</li>';
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
                                    toggleSpan.innerHTML = '<i class="bi bi-chevron-right"></i>';
                                    labelDiv.appendChild(toggleSpan);
                                } else {
                                    const emptySpan = document.createElement('span');
                                    emptySpan.classList.add('tree-toggle');
                                    labelDiv.appendChild(emptySpan);
                                }
                                
                                const textSpan = document.createElement('span');
                                textSpan.classList.add('text-truncate');
                                textSpan.textContent = `${child.codigo} - ${child.descricao}`;
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
                icon.classList.replace('bi-chevron-down', 'bi-chevron-right');
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
                            li.innerHTML = `<a href="#" class="text-primary text-decoration-none" onclick="navigateToCid(${node.id})">${node.codigo}</a>`;
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
                            div.classList.add('list-group-item', 'bg-body-tertiary', 'border', 'p-3', 'd-flex', 'justify-content-between', 'align-items-start', 'gap-3');
                            div.innerHTML = `
                                <div>
                                    <strong class="text-body">${agent.descricao}</strong>
                                    ${agent.cas ? `<div class="text-muted small mt-1">CAS: ${agent.cas}</div>` : ''}
                                </div>
                                <a href="consulta.php?cid=${encodeURIComponent(details.codigo)}&agente=${agent.id}" class="btn btn-sm btn-outline-secondary flex-shrink-0">Consulta Completa</a>
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
                            card.classList.add('card', 'bg-body-tertiary', 'border', 'mb-3');
                            card.innerHTML = `
                                <div class="card-header border d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0 text-primary-emphasis">${rel.titulo}</h6>
                                    <span class="badge bg-secondary font-monospace">${rel.old_id}</span>
                                </div>
                                <div class="card-body">
                                    <p class="card-text small text-body-secondary">${rel.relato}</p>
                                    <div class="border-top border pt-3 mt-3 d-flex gap-2">
                                        <span class="text-muted small">CNAE/CBO vinculado:</span>
                                        <span class="badge text-bg-secondary ${rel.classificacao === 'cnae' ? 'badge-cnae' : 'badge-cbo'}">
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
        
        // Setup Tree Search
        setupTreeSearch('tree_search', 'search_btn');

        function setupTreeSearch(inputId, buttonId) {
            const input = document.getElementById(inputId);
            const button = document.getElementById(buttonId);
            const rootList = document.getElementById('cid_root_list');
            const searchResults = document.getElementById('cid_search_results');

            function performSearch() {
                const query = input.value.trim();
                if (query.length < 2) {
                    // Restore tree view
                    searchResults.innerHTML = '';
                    searchResults.classList.add('d-none');
                    rootList.classList.remove('d-none');
                    return;
                }
                   // Keep tree view visible; no need to hide root list or show flat results
                // Show a loading status in the details panel while searching
                const defaultView = document.getElementById('details_default_view');
                const activeView = document.getElementById('details_active_view');
                defaultView.classList.add('d-none');
                activeView.classList.remove('d-none');
                document.getElementById('details_code').textContent = 'Buscando...';
                document.getElementById('details_description').textContent = '';
                document.getElementById('details_level_label').textContent = '';
                document.getElementById('details_path').innerHTML = '';
                document.getElementById('details_agents_list').innerHTML = '';
                document.getElementById('details_relatos_list').innerHTML = '';

                fetch(`api_autocomplete.php?type=cid&q=${encodeURIComponent(query)}&ldrt_only=${ldrtOnly ? '1' : '0'}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data && data.length > 0) {
                            const item = data[0];
                            input.value = item.label;

                            // Fetch CID details and navigate the hierarchy, expanding children automatically
                            fetch(`api_get_cid_details.php?id=${item.id}`)
                                .then(res => res.json())
                                .then(detailsData => {
                                    navigateToCidPath(detailsData.hierarchy);
                                })
                                .catch(err => {
                                    console.error('Error fetching details:', err);
                                    showSearchError(err.message);
                                });
                        } else {
                            showSearchError('Nenhum resultado encontrado.');
                        }
                    })
                    .catch(err => {
                        console.error('Error searching:', err);
                        showSearchError(err.message);
                    });
            }

            function showSearchError(message) {
                // Restore tree
                searchResults.innerHTML = '';
                searchResults.classList.add('d-none');
                rootList.classList.remove('d-none');

                document.getElementById('details_code').textContent = 'Não Encontrado';
                document.getElementById('details_description').textContent = message;
            }

            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    performSearch();
                }
            });

            input.addEventListener('input', function() {
                if (this.value.trim().length < 2) {
                    searchResults.innerHTML = '';
                    searchResults.classList.add('d-none');
                    rootList.classList.remove('d-none');
                }
            });

            if (button) {
                button.addEventListener('click', performSearch);
            }
        }

        async function navigateToCidPath(path) {
            if (!path || path.length === 0) return;
            
            // Loop through each element in the path to expand it
            for (let i = 0; i < path.length; i++) {
                const node = path[i];
                let li = document.querySelector(`.tree-node[data-id="${node.id}"]`);
                
                // If it's not the last element and it's not loaded, we expand it
                if (i < path.length - 1) {
                    const sublist = document.getElementById('children_of_' + node.id);
                    if (sublist && sublist.classList.contains('d-none')) {
                        const toggle = document.querySelector(`.tree-node[data-id="${node.id}"] .tree-toggle`);
                        if (toggle) {
                            toggle.click();
                            // Wait for the children to be loaded
                            await new Promise(resolve => {
                                const checkLoaded = () => {
                                    if (loadedNodes[node.id]) {
                                        resolve();
                                    } else {
                                        setTimeout(checkLoaded, 50);
                                    }
                                };
                                checkLoaded();
                            });
                        }
                    }
                } else {
                    // It's the target node
                    const label = document.querySelector(`.tree-node[data-id="${node.id}"] > .tree-node-label`);
                    if (label) {
                        selectCid(node.id, label);
                        label.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            }
        };
    </script>
</body>
</html>
