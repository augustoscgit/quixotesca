<?php
require_once __DIR__ . '/../../ldrt/src/db.php';

$cnae_roots = [];
$cbo_roots = [];
try {
    $db = getDBConnection();
    
    // Fetch top-level CNAE sections
    $stmt = $db->query("
        SELECT id, codigo, descricao,
               (SELECT COUNT(*) FROM cnae_cbo sub WHERE sub.parent_id = cc.id) > 0 AS has_children
        FROM cnae_cbo cc
        WHERE cc.parent_id IS NULL AND cc.classificacao = 'cnae'
        ORDER BY cc.codigo ASC
    ");
    $cnae_roots = $stmt->fetchAll();
    foreach ($cnae_roots as &$node) { $node['has_children'] = (bool)$node['has_children']; }

    // Fetch top-level CBO groups
    $stmt = $db->query("
        SELECT id, codigo, descricao,
               (SELECT COUNT(*) FROM cnae_cbo sub WHERE sub.parent_id = cc.id) > 0 AS has_children
        FROM cnae_cbo cc
        WHERE cc.parent_id IS NULL AND cc.classificacao = 'cbo'
        ORDER BY cc.codigo ASC
    ");
    $cbo_roots = $stmt->fetchAll();
    foreach ($cbo_roots as &$node) { $node['has_children'] = (bool)$node['has_children']; }

} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-module="ldrt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LDRT - Explorador CNAE/CBO</title>
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
    render_platform_navbar('ldrt', 'explorar_cnae_cbo');
    ?>

    <div class="container-fluid my-4 px-lg-5">
        <div class="row g-4">
            
            <!-- Left Panel: CNAE/CBO Tree tabs -->
            <div class="col-lg-5 col-xl-4">
                <div class="card p-4">
                    <h4 class="mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-diagram-3 text-primary"></i>
                        Classificações
                    </h4>
                    
                    <!-- Tabs -->
                    <ul class="nav nav-tabs mb-3" id="treeTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="cnae-tab" data-bs-toggle="tab" data-bs-target="#cnae-panel" type="button" role="tab">CNAE (Atividades)</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="cbo-tab" data-bs-toggle="tab" data-bs-target="#cbo-panel" type="button" role="tab">CBO (Ocupações)</button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="treeTabsContent">
                        
                        <!-- CNAE Tree Panel -->
                        <div class="tab-pane fade show active" id="cnae-panel" role="tabpanel">
                            <div class="tree-container">
                                <ul class="p-0">
                                    <?php foreach ($cnae_roots as $node): ?>
                                        <li class="tree-node" data-id="<?php echo $node['id']; ?>">
                                            <div class="tree-node-label" onclick="selectNode(<?php echo $node['id']; ?>, 'cnae', this)">
                                                <?php if ($node['has_children']): ?>
                                                    <span class="tree-toggle" onclick="toggleNode(event, <?php echo $node['id']; ?>, 'cnae', this)">
                                                        <i class="bi bi-chevron-right"></i>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="tree-toggle"></span>
                                                <?php endif; ?>
                                                <span class="badge text-bg-secondary badge-cnae me-2"><?php echo htmlspecialchars($node['codigo']); ?></span>
                                                <span class="text-truncate"><?php echo htmlspecialchars($node['descricao']); ?></span>
                                            </div>
                                            <ul class="p-0 d-none" id="cnae_children_of_<?php echo $node['id']; ?>"></ul>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- CBO Tree Panel -->
                        <div class="tab-pane fade" id="cbo-panel" role="tabpanel">
                            <div class="tree-container">
                                <ul class="p-0">
                                    <?php foreach ($cbo_roots as $node): ?>
                                        <li class="tree-node" data-id="<?php echo $node['id']; ?>">
                                            <div class="tree-node-label" onclick="selectNode(<?php echo $node['id']; ?>, 'cbo', this)">
                                                <?php if ($node['has_children']): ?>
                                                    <span class="tree-toggle" onclick="toggleNode(event, <?php echo $node['id']; ?>, 'cbo', this)">
                                                        <i class="bi bi-chevron-right"></i>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="tree-toggle"></span>
                                                <?php endif; ?>
                                                <span class="badge text-bg-secondary badge-cbo me-2"><?php echo htmlspecialchars($node['codigo']); ?></span>
                                                <span class="text-truncate"><?php echo htmlspecialchars($node['descricao']); ?></span>
                                            </div>
                                            <ul class="p-0 d-none" id="cbo_children_of_<?php echo $node['id']; ?>"></ul>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Right Panel: details and relations -->
            <div class="col-lg-7 col-xl-8">
                <div class="card p-4 h-100" id="details_panel">
                    <div class="text-center py-5" id="details_default_view">
                        <i class="bi bi-info-circle text-muted mb-4"></i>
                        <h3>Selecione uma Atividade ou Ocupação</h3>
                        <p class="text-muted mx-auto">
                            Escolha a aba CNAE (Atividade Econômica) ou CBO (Ocupação do Trabalhador) à esquerda e navegue na árvore. Clique no termo desejado para ver sua hierarquia completa e os relatos de casos associados.
                        </p>
                    </div>
                    
                    <!-- Dynamic Details View (hidden by default) -->
                    <div class="d-none" id="details_active_view">
                        <!-- Breadcrumb Path -->
                        <nav aria-label="breadcrumb" class="mb-3">
                            <ol class="breadcrumb" id="details_path"></ol>
                        </nav>
                        
                        <div class="info-box" id="details_info_box">
                            <div class="text-muted small font-weight-bold" id="details_level_label">SUBCLASSE</div>
                            <h3 class="mt-1" id="details_code">9521500</h3>
                            <h5 class="text-body" id="details_description">Reparação e manutenção de computadores e de equipamentos periféricos</h5>
                        </div>

                        <div class="row g-4 mt-2">
                            <!-- Associated Case Reports -->
                            <div class="col-12">
                                <h5 class="section-title"><i class="bi bi-file-earmark-medical"></i> Relatos de Casos</h5>
                                <div id="details_relatos_list">
                                    <!-- Dynamic content -->
                                </div>
                                <div id="details_relatos_empty" class="alert alert-secondary border p-4 d-none">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-folder2-open text-muted"></i>
                                        <div>
                                            <h6 class="mb-1 text-body">Sem relatos registrados</h6>
                                            <p class="mb-0 text-muted small">Não há nenhum relato de caso cadastrado para este código. Este setor/ocupação está disponível no banco para futuros registros (a serem configurados em fases posteriores).</p>
                                        </div>
                                    </div>
                                </div>
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
            <p class="mb-1 text-muted small">LDRT Explorador CNAE/CBO &copy; 2026 - Conforme Portaria GM/MS 1.999/2023</p>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>

    <!-- Tree Explorer JS -->
    <script>
        const loadedNodes = {}; 

        function toggleNode(event, nodeId, clazz, element) {
            event.stopPropagation();
            
            const sublist = document.getElementById(clazz + '_children_of_' + nodeId);
            const icon = element.querySelector('i');

            if (sublist.classList.contains('d-none')) {
                sublist.classList.remove('d-none');
                icon.classList.replace('bi-chevron-right', 'bi-chevron-down');
                
                if (!loadedNodes[clazz + '_' + nodeId]) {
                    sublist.innerHTML = '<li class="tree-node text-muted small ps-3 py-1"><i class="bi bi-arrow-clockwise bootstrap-spin me-2"></i>Carregando...</li>';
                    fetch(`api_get_cnae_cbo_children.php?parent_id=${nodeId}&classificacao=${clazz}`)
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
                                labelDiv.setAttribute('onclick', `selectNode(${child.id}, '${clazz}', this)`);
                                
                                if (child.has_children) {
                                    const toggleSpan = document.createElement('span');
                                    toggleSpan.classList.add('tree-toggle');
                                    toggleSpan.setAttribute('onclick', `toggleNode(event, ${child.id}, '${clazz}', this)`);
                                    toggleSpan.innerHTML = '<i class="bi bi-chevron-right"></i>';
                                    labelDiv.appendChild(toggleSpan);
                                } else {
                                    const emptySpan = document.createElement('span');
                                    emptySpan.classList.add('tree-toggle');
                                    labelDiv.appendChild(emptySpan);
                                }
                                
                                const codeBadge = document.createElement('span');
                                codeBadge.classList.add('badge', 'text-bg-secondary', clazz === 'cnae' ? 'badge-cnae' : 'badge-cbo', 'me-2');
                                codeBadge.textContent = child.codigo;
                                labelDiv.appendChild(codeBadge);
                                
                                const textSpan = document.createElement('span');
                                textSpan.classList.add('text-truncate');
                                textSpan.textContent = child.descricao;
                                labelDiv.appendChild(textSpan);
                                
                                childLi.appendChild(labelDiv);
                                
                                if (child.has_children) {
                                    const nestedUl = document.createElement('ul');
                                    nestedUl.id = clazz + '_children_of_' + child.id;
                                    nestedUl.classList.add('p-0', 'd-none');
                                    childLi.appendChild(nestedUl);
                                }
                                
                                sublist.appendChild(childLi);
                            });
                            
                            loadedNodes[clazz + '_' + nodeId] = true;
                        })
                        .catch(err => {
                            sublist.innerHTML = `<li class="tree-node text-danger small ps-3 py-1">Erro ao carregar: ${err.message}</li>`;
                        });
                }
            } else {
                sublist.classList.add('d-none');
                icon.classList.replace('bi-chevron-down', 'bi-chevron-right');
            }
        }

        function selectNode(id, clazz, element) {
            const activeLabel = document.querySelector('.tree-node-label.active');
            if (activeLabel) {
                activeLabel.classList.remove('active');
            }
            element.classList.add('active');
            
            const defaultView = document.getElementById('details_default_view');
            const activeView = document.getElementById('details_active_view');
            defaultView.classList.add('d-none');
            activeView.classList.remove('d-none');
            
            document.getElementById('details_code').textContent = 'Carregando...';
            document.getElementById('details_description').textContent = '';
            document.getElementById('details_level_label').textContent = '';
            document.getElementById('details_path').innerHTML = '';
            document.getElementById('details_relatos_list').innerHTML = '';
            
            const infoBox = document.getElementById('details_info_box');
            infoBox.classList.remove('border-success', 'border-warning');
            if (clazz === 'cnae') {
                infoBox.classList.add('border-success');
                document.getElementById('details_code').className = 'mt-1 text-success';
            } else {
                infoBox.classList.add('border-warning');
                document.getElementById('details_code').className = 'mt-1 text-warning';
            }
            
            fetch(`api_get_cnae_cbo_details.php?id=${id}`)
                .then(res => res.json())
                .then(data => {
                    const details = data.details;
                    
                    document.getElementById('details_code').textContent = details.codigo;
                    document.getElementById('details_description').textContent = details.descricao;
                    document.getElementById('details_level_label').textContent = details.nivel.toUpperCase();
                    
                    // Render path breadcrumbs
                    const pathContainer = document.getElementById('details_path');
                    data.hierarchy.forEach((node, index) => {
                        const li = document.createElement('li');
                        li.classList.add('breadcrumb-item');
                        if (index === data.hierarchy.length - 1) {
                            li.classList.add('active');
                            li.setAttribute('aria-current', 'page');
                            li.textContent = node.codigo;
                        } else {
                            li.innerHTML = `<span class="text-primary text-decoration-none">${node.codigo}</span>`;
                        }
                        pathContainer.appendChild(li);
                    });
                    
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
                                    <div class="border-top border pt-3 mt-3 d-flex flex-wrap gap-2">
                                        <span class="text-muted small">Mapeado na LDRT para:</span>
                                        ${rel.agente_descricao ? `<span class="badge text-bg-secondary badge-agent">Agente: ${rel.agente_descricao}</span>` : ''}
                                        ${rel.cid_codigo ? `<span class="badge text-bg-secondary badge-cid">CID: ${rel.cid_codigo} - ${rel.cid_descricao.substring(0, 30)}...</span>` : ''}
                                    </div>
                                </div>
                            `;
                            relatosList.appendChild(card);
                        });
                    }
                })
                .catch(err => {
                    document.getElementById('details_code').textContent = 'Erro';
                    document.getElementById('details_description').textContent = `Falha ao carregar: ${err.message}`;
                });
        }
    </script>
</body>
</html>
