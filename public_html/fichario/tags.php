<?php
declare(strict_types=1);

require __DIR__ . '/../../fichario/bootstrap.php';
require_once __DIR__ . '/../../fichario/src/Home/tag_cloud.php';
require_once __DIR__ . '/../../fichario/src/Home/tag_graph.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    session_write_close();
}

$pdo = db();
$errors = [];
$notice = '';
$canManageTags = is_admin();
$maxCount = 0;
$wordList = [];
$nodes = [];
$edges = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    require_csrf();
}

// Handle Delete Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $deleteId = (int) ($_POST['id'] ?? 0);
    $clearChildren = ($_POST['clear_children'] ?? '') === '1';
    $confirmNotes = ($_POST['confirm_notes'] ?? '') === '1';
    try {
        $childCountStmt = $pdo->prepare('SELECT COUNT(*) FROM tag_hierarchy WHERE parent_id = :id');
        $childCountStmt->execute([':id' => $deleteId]);
        $childCount = (int) $childCountStmt->fetchColumn();
        $noteCountStmt = $pdo->prepare('SELECT COUNT(DISTINCT quote_id) FROM article_quote_tags WHERE tag_id = :id');
        $noteCountStmt->execute([':id' => $deleteId]);
        $noteCount = (int) $noteCountStmt->fetchColumn();

        if ($childCount > 0 && !$clearChildren) {
            $errors[] = "Esta tag possui {$childCount} sub-tag(s). Confirme a exclusão para remover esta filiação dos filhos antes de excluir.";
        } elseif ($noteCount > 0 && !$confirmNotes) {
            $errors[] = "Esta tag esta vinculada a {$noteCount} nota(s). Confirme a exclusao sabendo que as notas serao mantidas, mas perderao esta tag.";
        } else {
            $pdo->beginTransaction();

            if ($childCount > 0) {
                $clearStmt = $pdo->prepare('DELETE FROM tag_hierarchy WHERE parent_id = :id');
                $clearStmt->execute([':id' => $deleteId]);
            }

            $stmt = $pdo->prepare('DELETE FROM tags WHERE id = :id');
            $stmt->execute([':id' => $deleteId]);

            $pdo->commit();
            header('Location: tags.php');
            exit;
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = 'Erro ao deletar tag: ' . $e->getMessage();
    }
}

// Handle Form Submission (Add / Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? 'save') === 'save') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $definition = trim((string) ($_POST['definition'] ?? ''));
    $category = trim((string) ($_POST['category'] ?? ''));
    $parents = isset($_POST['parents']) ? array_map('intval', (array) $_POST['parents']) : [];
    $editTagId = (int) ($_POST['id'] ?? 0);
    $isEditingSubmit = $editTagId > 0;

    if ($name === '') {
        $errors[] = 'O nome da tag é obrigatório.';
    }

    // Check uniqueness
    $uniqStmt = $pdo->prepare('SELECT id FROM tags WHERE name = :name AND id != :id');
    $uniqStmt->execute([':name' => $name, ':id' => $editTagId]);
    if ($uniqStmt->fetch()) {
        $errors[] = "Já existe uma tag cadastrada com o nome '{$name}'.";
    }

    // Cycle Prevention Check
    if ($isEditingSubmit && $parents !== []) {
        foreach ($parents as $parentId) {
            if ($parentId === $editTagId) {
                $errors[] = 'Uma tag não pode ser superior (pai) de si mesma.';
                break;
            }

            // A proposed parent cannot be a descendant of the current tag
            $cycleStmt = $pdo->prepare("
                WITH RECURSIVE descendants(id) AS (
                    SELECT :current_id
                    UNION
                    SELECT child_id FROM tag_hierarchy th
                    JOIN descendants d ON th.parent_id = d.id
                )
                SELECT 1 FROM descendants WHERE id = :parent_id
            ");
            $cycleStmt->execute([':current_id' => $editTagId, ':parent_id' => $parentId]);
            if ($cycleStmt->fetch()) {
                $parentNameStmt = $pdo->prepare('SELECT name FROM tags WHERE id = :id');
                $parentNameStmt->execute([':id' => $parentId]);
                $parentName = $parentNameStmt->fetchColumn();
                $errors[] = "Relação inválida: a tag '{$parentName}' é uma sub-tag (filha) direta ou indireta de '{$name}'. Isso causaria um ciclo de dependência.";
                break;
            }
        }
    }

    if ($errors === []) {
        $pdo->beginTransaction();
        try {
            if ($isEditingSubmit) {
                // Update tag details
                $stmt = $pdo->prepare('UPDATE tags SET name = :name, definition = :definition, category = :category WHERE id = :id');
                $stmt->execute([
                    ':name' => $name,
                    ':definition' => $definition,
                    ':category' => $category,
                    ':id' => $editTagId
                ]);
                $currentId = $editTagId;
            } else {
                // Insert new tag
                $stmt = $pdo->prepare('INSERT INTO tags (name, definition, category) VALUES (:name, :definition, :category)');
                $stmt->execute([
                    ':name' => $name,
                    ':definition' => $definition,
                    ':category' => $category
                ]);
                $currentId = (int) $pdo->lastInsertId();
            }

            // Sync hierarchy relations (parents)
            $delStmt = $pdo->prepare('DELETE FROM tag_hierarchy WHERE child_id = :child_id');
            $delStmt->execute([':child_id' => $currentId]);

            if ($parents !== []) {
                $insStmt = $pdo->prepare('INSERT INTO tag_hierarchy (parent_id, child_id) VALUES (:parent_id, :child_id)');
                foreach ($parents as $parentId) {
                    $insStmt->execute([
                        ':parent_id' => $parentId,
                        ':child_id' => $currentId
                    ]);
                }
            }

            $pdo->commit();
            header('Location: tag_view.php?tag_id=' . $currentId);
            exit;
        } catch (Throwable $dbError) {
            $pdo->rollBack();
            $errors[] = 'Erro no banco de dados: ' . $dbError->getMessage();
        }
    }
}

// Fetch all tags in database, including article counts used by the cloud/graph tabs.
$tagsList = $pdo->query('
    SELECT t.id, t.name, t.definition, t.category, COUNT(at.article_id) AS article_count
    FROM tags t
    LEFT JOIN article_tags at ON t.id = at.tag_id
    GROUP BY t.id, t.name, t.definition, t.category
    ORDER BY lower(t.category) ASC, lower(t.name) ASC
')->fetchAll();

$cloudData = prepare_home_tag_cloud($tagsList);
$cloudTags = $cloudData['cloudTags'];
$maxCount = $cloudData['maxCount'];
$wordList = $cloudData['wordList'];

try {
    $graphData = prepare_home_tag_graph($pdo, $cloudTags);
    $nodes = $graphData['nodes'];
    $edges = $graphData['edges'];
} catch (Throwable $exception) {
    $nodes = [];
    $edges = [];
}

// Group tags by category
$tagsByCategory = [];
foreach ($tagsList as $tag) {
    $cat = trim((string) ($tag['category'] ?? ''));
    if ($cat === '') {
        $cat = 'Sem agrupamento';
    }
    $tagsByCategory[$cat][] = $tag;
}
ksort($tagsByCategory, SORT_NATURAL | SORT_FLAG_CASE);

// Fetch all hierarchy relations for in-memory tree building
$relations = $pdo->query('SELECT parent_id, child_id FROM tag_hierarchy')->fetchAll();
$parentToChildren = [];
$childToParents = [];
foreach ($relations as $rel) {
    $parentToChildren[(int)$rel['parent_id']][] = (int)$rel['child_id'];
    $childToParents[(int)$rel['child_id']][] = (int)$rel['parent_id'];
}

// Reusable tag statistic query statement
$tagStatsStmt = $pdo->prepare("
    WITH RECURSIVE descendants(id) AS (
        SELECT CAST(:tag_id AS integer)
        UNION
        SELECT child_id FROM tag_hierarchy th
        JOIN descendants d ON th.parent_id = d.id
    )
    SELECT
        (SELECT COUNT(DISTINCT article_id) FROM article_tags WHERE tag_id = :tag_id) AS direct_count,
        (SELECT COUNT(DISTINCT article_id) FROM article_tags WHERE tag_id IN (SELECT id FROM descendants)) AS recursive_count,
        (SELECT COUNT(DISTINCT quote_id) FROM article_quote_tags WHERE tag_id = :tag_id) AS note_count
");

function tag_stats(PDOStatement $stmt, int $tagId): array
{
    $stmt->execute([':tag_id' => $tagId]);
    $row = $stmt->fetch() ?: ['direct_count' => 0, 'recursive_count' => 0, 'note_count' => 0];

    return [
        'direct' => (int) $row['direct_count'],
        'recursive' => (int) $row['recursive_count'],
        'notes' => (int) $row['note_count'],
    ];
}



// Recursive function to render a tag tree node (Drupal taxonomy style)
function render_category_tree(array $tags, array $parentToChildren, array $catTagsById, array $childToParents, array &$visited, int $depth = 0): void {
    global $canManageTags;

    if (empty($tags)) {
        return;
    }
    echo '<ul class="list-unstyled ' . ($depth > 0 ? 'ms-3' : '') . '">';
    foreach ($tags as $tag) {
        $id = (int)$tag['id'];
        if (isset($visited[$id])) {
            continue; // Prevent cycles
        }
        $visited[$id] = true;

        // Get children that are in the same category
        $childTags = [];
        if (isset($parentToChildren[$id])) {
            foreach ($parentToChildren[$id] as $childId) {
                if (isset($catTagsById[$childId])) {
                    $childTags[] = $catTagsById[$childId];
                }
            }
        }
        // Sort children by name case-insensitive
        usort($childTags, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        // Query stats
        global $tagStatsStmt;
        $stats = tag_stats($tagStatsStmt, $id);

        echo '<li class="my-2">';
        echo '  <div class="d-flex align-items-center justify-content-between p-2 tag-tree-item rounded" style="background: rgba(255,255,255,0.015); border: 1px solid rgba(255,255,255,0.04);">';
        echo '    <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">';
        echo '      <a class="text-white fw-medium text-decoration-none text-truncate tag-link" href="tag_view.php?tag_id=' . $id . '"' . tag_tooltip_attrs($tag) . '>' . h($tag['name']) . '</a>';
        if (trim((string)($tag['definition'] ?? '')) === '') {
            echo '      <span class="text-secondary opacity-25" title="Sem definição" style="cursor: help; line-height: 1;">';
            echo '        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
            echo '      </span>';
        }
        echo '    </div>';
        echo '    <div class="d-flex align-items-center gap-2 ms-2">';
        echo '      <span class="badge bg-black bg-opacity-40 border border-secondary border-opacity-20 text-secondary font-monospace" style="font-size:0.65rem;" title="Notas vinculadas">';
        echo $stats['notes'];
        echo '      </span>';
        echo '    </div>';
        echo '  </div>';

        if (!empty($childTags)) {
            render_category_tree($childTags, $parentToChildren, $catTagsById, $childToParents, $visited, $depth + 1);
        }
        echo '</li>';
    }
    echo '</ul>';
}
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Navegar por Tags - Fichário Acadêmico</title>
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/app.css?v=20260603h" rel="stylesheet">
    <link href="assets/tag-visualizations.css?v=20260625" rel="stylesheet">
    <style>
        body {
            background: var(--bg-gradient);
        }

        .blob {
            animation: floatBlob 12s infinite alternate ease-in-out;
        }

        .blob-purple {
            animation-delay: -6s;
        }

        @keyframes floatBlob {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(60px, 40px) scale(1.15); }
        }

        .tag-tree-container::-webkit-scrollbar {
            width: 6px;
        }
        .tag-tree-container::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.02);
            border-radius: 4px;
        }
        .tag-tree-container::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
        }
        .tag-tree-container::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.2);
        }

        .dropdown-toggle::after {
            display: none !important;
        }

        .tag-tree-item {
            transition: all 0.2s;
        }
        .tag-tree-item:hover {
            background: rgba(255, 255, 255, 0.04) !important;
            border-color: rgba(255,255,255,0.12) !important;
        }

        .tag-parents-select {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.15);
            border-radius: 8px;
            padding: 10px;
        }

        .tag-view-tabs {
            border-bottom-color: var(--bs-border-color);
        }

        .tag-view-tabs .nav-link {
            color: var(--bs-secondary-color);
            border: 0;
            border-bottom: 2px solid transparent;
            border-radius: 0;
            padding-inline: 0;
            margin-right: 1.5rem;
            background: transparent;
            font-weight: 600;
        }

        .tag-view-tabs .nav-link:hover,
        .tag-view-tabs .nav-link:focus {
            color: var(--bs-body-color);
            border-bottom-color: var(--bs-border-color);
        }

        .tag-view-tabs .nav-link.active {
            color: var(--bs-primary);
            background: transparent;
            border-bottom-color: var(--bs-primary);
        }

    </style>
</head>
<body>
    <div class="blob blob-blue"></div>
    <div class="blob blob-purple"></div>

    <?php render_navbar('tags'); ?>

    <main class="container py-4 main-container">
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Fichário</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page">Tags</li>
            </ol>
        </nav>

        <!-- Top bar -->
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
            <div>
                <h1 class="h3 mb-1 text-white fw-bold">Navegar por Tags</h1>
                <p class="text-secondary mb-0">Explore e visualize a taxonomia temática de termos em hierarquias e boxes.</p>
            </div>
            <?php if ($canManageTags): ?>
                <div>
                    <button class="btn btn-primary rounded-pill px-4" onclick="openNewTagModal()">
                        + Criar Tag
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($errors !== []): ?>
            <div class="alert alert-danger bg-danger-subtle border-danger text-danger-emphasis rounded-3" role="alert">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <ul class="nav nav-tabs tag-view-tabs mb-4" id="tag-view-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-tree-btn" data-bs-toggle="tab" data-bs-target="#tab-tree" type="button" role="tab" aria-controls="tab-tree" aria-selected="true">Hierarquia</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-cloud-btn" data-bs-toggle="tab" data-bs-target="#tab-cloud" type="button" role="tab" aria-controls="tab-cloud" aria-selected="false">Nuvem</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-graph-btn" data-bs-toggle="tab" data-bs-target="#tab-graph" type="button" role="tab" aria-controls="tab-graph" aria-selected="false">Grafo</button>
            </li>
        </ul>

        <div class="tab-content" id="tag-view-tabs-content">
            <section class="tab-pane fade show active" id="tab-tree" role="tabpanel" aria-labelledby="tab-tree-btn" tabindex="0">
                <div class="glass-card p-3 mb-4">
                    <input type="text" class="form-control" id="tagSearchInput" placeholder="Buscar tag por nome...">
                </div>

                <div class="row g-4">
            <?php if ($tagsByCategory === []): ?>
                <div class="col-12">
                    <div class="glass-card p-5 text-center text-secondary">Nenhuma tag cadastrada no fichário.</div>
                </div>
            <?php else: ?>
                <?php foreach ($tagsByCategory as $catName => $catTags): ?>
                    <?php
                    // Index category tags for fast tree rendering
                    $catTagsById = [];
                    foreach ($catTags as $t) {
                        $catTagsById[(int)$t['id']] = $t;
                    }

                    // Find roots in this category
                    $rootTags = [];
                    foreach ($catTags as $t) {
                        $id = (int)$t['id'];
                        $hasParentInSameCat = false;
                        if (isset($childToParents[$id])) {
                            foreach ($childToParents[$id] as $parentId) {
                                if (isset($catTagsById[$parentId])) {
                                    $hasParentInSameCat = true;
                                    break;
                                }
                            }
                        }
                        if (!$hasParentInSameCat) {
                            $rootTags[] = $t;
                        }
                    }
                    // Sort roots alphabetically
                    usort($rootTags, function($a, $b) {
                        return strcasecmp($a['name'], $b['name']);
                    });

                    $cColor = get_tag_colors($catName);
                    ?>
                    <div class="col-xl-4 col-md-6 tag-box-column" data-box-cat="<?= h($catName) ?>">
                        <div class="glass-card p-4 h-100 d-flex flex-column">
                            <div class="d-flex align-items-center justify-content-between mb-3 border-bottom border-secondary border-opacity-20 pb-2">
                                <h2 class="h5 text-white fw-bold mb-0 d-flex align-items-center gap-2">
                                    <span class="badge border tag-badge" style="background:<?= $cColor['bg'] ?>; color:<?= $cColor['text'] ?>; border-color:<?= $cColor['border'] ?> !important;">
                                        <?= h($catName) ?>
                                    </span>
                                </h2>
                                <span class="text-secondary small"><?= count($catTags) ?> tag(s)</span>
                            </div>
                            
                            <div class="flex-grow-1 tag-tree-container" style="max-height: 480px; overflow-y: auto;">
                                <?php
                                $visited = [];
                                render_category_tree($rootTags, $parentToChildren, $catTagsById, $childToParents, $visited);
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="tab-pane fade" id="tab-cloud" role="tabpanel" aria-labelledby="tab-cloud-btn" tabindex="0">
                <div class="glass-card p-4">
                    <?php if ($wordList === []): ?>
                        <div class="p-5 text-center text-secondary">Nenhuma tag com artigo associado para exibir na nuvem.</div>
                    <?php else: ?>
                        <div class="d-flex justify-content-center align-items-center py-2" style="position: relative; overflow: hidden; width: 100%;">
                            <canvas id="word-cloud-canvas" style="width: 100%; max-width: 900px; height: 420px;"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="tab-pane fade" id="tab-graph" role="tabpanel" aria-labelledby="tab-graph-btn" tabindex="0">
                <?php if (count($nodes) > 0): ?>
                    <div id="tag-network-container" class="glass-card tag-network-container">
                        <div id="tag-network-viewport" class="tag-network-viewport"></div>
                        <div id="tag-network-controls" class="tag-network-controls" aria-label="Filtros do grafo de tags"></div>
                    </div>
                <?php else: ?>
                    <div class="glass-card p-5 text-center text-secondary">Ainda nÃ£o hÃ¡ relaÃ§Ãµes suficientes para exibir o grafo.</div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <?php if ($canManageTags): ?>
    <!-- Create/Edit Modal -->
    <div class="modal fade" id="tagModal" tabindex="-1" aria-labelledby="tagModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5 text-white fw-bold" id="tagModalLabel">Criar Nova Tag</h2>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="tags.php" id="tagForm">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" id="tagFormId" value="0">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Nome / Rótulo</label>
                            <input type="text" class="form-control" name="name" id="tagFormName" placeholder="Ex: Burnout Ocupacional" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="category" class="form-label">Agrupamento</label>
                            <select class="form-select" name="category" id="tagFormCategory">
                                <option value="">Sem agrupamento</option>
                                <option value="Tema">Tema</option>
                                <option value="Método">Método</option>
                                <option value="Fonte">Fonte</option>
                            </select>
                            <div class="form-text text-secondary">A categorização facilita a organização na busca de referências.</div>
                        </div>

                        <div class="mb-3">
                            <label for="definition" class="form-label">Definição Teórica / Critérios</label>
                            <textarea class="form-control" name="definition" id="tagFormDefinition" rows="4" placeholder="Descreva os conceitos ou definições conceituais desta tag..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tags Superiores (Pai)</label>
                            <div class="mb-2">
                                <input type="text" class="form-control form-control-sm" id="parentTagSearchInput" placeholder="Filtrar tags superiores por nome...">
                            </div>
                            <div class="tag-parents-select" id="parents-select-container">
                                <?php if ($tagsList === []): ?>
                                    <p class="text-secondary small mb-0">Nenhuma tag cadastrada ainda para atuar como pai.</p>
                                <?php else: ?>
                                    <?php foreach ($tagsList as $optionTag): ?>
                                        <div class="form-check" id="parent-checkbox-wrapper-<?= $optionTag['id'] ?>">
                                            <input class="form-check-input parent-tag-checkbox" type="checkbox" name="parents[]" value="<?= $optionTag['id'] ?>" id="parent_tag_<?= $optionTag['id'] ?>">
                                            <label class="form-check-label text-white small" for="parent_tag_<?= $optionTag['id'] ?>">
                                                <?= h($optionTag['name']) ?>
                                                <?php if (trim($optionTag['category'] ?? '') !== ''): ?>
                                                    <span class="text-secondary font-monospace" style="font-size:0.75rem;">(<?= h($optionTag['category']) ?>)</span>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="form-text text-secondary">Marque os conceitos mais amplos e genéricos sob os quais este conceito se enquadra hierarquicamente.</div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top border-secondary border-opacity-25">
                            <button type="button" class="btn btn-outline-secondary text-white rounded-pill px-3" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4" id="submitBtn">Criar Tag</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden form for deleting tags -->
    <form method="post" action="tags.php" id="deleteForm" style="display:none;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteFormId" value="0">
        <input type="hidden" name="clear_children" id="deleteFormClearChildren" value="0">
        <input type="hidden" name="confirm_notes" id="deleteFormConfirmNotes" value="0">
    </form>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/app.js?v=20260603c"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/wordcloud2.js/1.2.2/wordcloud2.min.js"></script>
    <script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
    <script>
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => bootstrap.Tooltip.getOrCreateInstance(el));

        const canManageTags = <?= $canManageTags ? 'true' : 'false' ?>;
        <?php if ($canManageTags): ?>
        const tagModalEl = document.getElementById('tagModal');
        const tagModal = bootstrap.Modal.getOrCreateInstance(tagModalEl);
        <?php endif; ?>

        function normalizeStr(str) {
            return (str || '').normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim();
        }

        window.FicharioTagVisualizationsConfig = {
            maxCount: <?= (int) $maxCount ?>,
            wordList: <?= json_encode($wordList, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
            nodes: <?= json_encode($nodes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
            edges: <?= json_encode($edges, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
        };
        // Search tags tree filter
        const tagSearchInput = document.getElementById('tagSearchInput');
        if (tagSearchInput) {
            tagSearchInput.addEventListener('input', () => {
                const q = normalizeStr(tagSearchInput.value);
                
                if (q === '') {
                    // Show everything
                    document.querySelectorAll('.tag-tree-item').forEach(el => {
                        el.style.display = '';
                        const li = el.closest('li');
                        if (li) li.style.display = '';
                    });
                    document.querySelectorAll('.tag-tree-container ul').forEach(ul => {
                        ul.style.display = '';
                    });
                    document.querySelectorAll('.tag-box-column').forEach(col => {
                        col.style.display = '';
                    });
                    return;
                }

                // Hide all first
                document.querySelectorAll('.tag-tree-item').forEach(el => {
                    el.style.display = 'none';
                    const li = el.closest('li');
                    if (li) li.style.display = 'none';
                });

                // Show matching items and their ancestors
                document.querySelectorAll('.tag-tree-item').forEach(el => {
                    const nameEl = el.querySelector('.tag-link');
                    const name = normalizeStr(nameEl ? nameEl.textContent : '');
                    
                    if (name.includes(q)) {
                        el.style.display = '';
                        let curr = el.closest('li');
                        while (curr) {
                            curr.style.display = '';
                            const parentUl = curr.closest('ul');
                            if (parentUl) {
                                parentUl.style.display = '';
                            }
                            curr = parentUl ? parentUl.closest('li') : null;
                        }
                    }
                });

                // Hide columns with no visible elements
                document.querySelectorAll('.tag-box-column').forEach(col => {
                    const visibleItems = Array.from(col.querySelectorAll('.tag-tree-item')).filter(item => item.style.display !== 'none').length;
                    col.style.display = visibleItems === 0 ? 'none' : '';
                });
            });
        }

        // Modals Management
        function openNewTagModal() {
            if (!canManageTags) return;
            document.getElementById('tagModalLabel').textContent = 'Criar Nova Tag';
            document.getElementById('tagFormId').value = '0';
            document.getElementById('tagFormName').value = '';
            document.getElementById('tagFormCategory').value = '';
            document.getElementById('tagFormDefinition').value = '';
            document.getElementById('submitBtn').textContent = 'Criar Tag';
            
            const parentSearch = document.getElementById('parentTagSearchInput');
            if (parentSearch) {
                parentSearch.value = '';
            }

            document.querySelectorAll('.parent-tag-checkbox').forEach(cb => {
                cb.checked = false;
                const wrapper = cb.closest('.form-check');
                if (wrapper) wrapper.style.display = '';
            });

            tagModal.show();
        }

        function openEditTagModal(btnEl) {
            if (!canManageTags) return;
            const id = btnEl.getAttribute('data-tag-id');
            const name = btnEl.getAttribute('data-tag-name');
            const category = btnEl.getAttribute('data-tag-category');
            const definition = btnEl.getAttribute('data-tag-definition');
            const parents = JSON.parse(btnEl.getAttribute('data-tag-parents') || '[]');

            document.getElementById('tagModalLabel').textContent = 'Editar Tag Temática';
            document.getElementById('tagFormId').value = id;
            document.getElementById('tagFormName').value = name;
            document.getElementById('tagFormCategory').value = category;
            document.getElementById('tagFormDefinition').value = definition;
            document.getElementById('submitBtn').textContent = 'Salvar Alterações';

            const parentSearch = document.getElementById('parentTagSearchInput');
            if (parentSearch) {
                parentSearch.value = '';
            }

            document.querySelectorAll('.parent-tag-checkbox').forEach(cb => {
                const val = parseInt(cb.value, 10);
                cb.checked = parents.includes(val);

                const wrapper = cb.closest('.form-check');
                if (val === parseInt(id, 10)) {
                    if (wrapper) wrapper.style.display = 'none';
                } else {
                    if (wrapper) wrapper.style.display = '';
                }
            });

            tagModal.show();
        }

        async function deleteTag(tagId, tagName, childCount, noteCount) {
            if (!canManageTags) return;
            const details = [];
            if (childCount > 0) {
                details.push(`${childCount} sub-tag(s) direta(s) terao a filiacao removida`);
            }
            if (noteCount > 0) {
                details.push(`${noteCount} nota(s) serao mantidas, mas perderao esta tag`);
            }
            const message = details.length > 0
                ? `A tag "${tagName}" possui vinculos: ${details.join('; ')}. Confirmar exclusao da tag?`
                : `Deseja realmente excluir definitivamente a tag "${tagName}"?`;

            const ok = await FicharioUI.confirm({
                title: 'Excluir tag',
                message,
                confirmText: 'Excluir tag',
                variant: 'danger'
            });
            if (!ok) return;

            document.getElementById('deleteFormId').value = tagId;
            document.getElementById('deleteFormClearChildren').value = childCount > 0 ? '1' : '0';
            document.getElementById('deleteFormConfirmNotes').value = noteCount > 0 ? '1' : '0';
            document.getElementById('deleteForm').submit();
        }

        document.querySelectorAll('[data-delete-tag]').forEach((button) => {
            button.addEventListener('click', () => {
                deleteTag(
                    Number(button.dataset.tagId || 0),
                    button.dataset.tagName || '',
                    Number(button.dataset.childCount || 0),
                    Number(button.dataset.noteCount || 0)
                );
            });
        });

        const parentTagSearchInput = document.getElementById('parentTagSearchInput');
        if (parentTagSearchInput) {
            parentTagSearchInput.addEventListener('input', () => {
                const q = normalizeStr(parentTagSearchInput.value);
                document.querySelectorAll('.parent-tag-checkbox').forEach(cb => {
                    const label = cb.nextElementSibling;
                    const name = label ? normalizeStr(label.textContent) : '';
                    const wrapper = cb.closest('.form-check');
                    if (wrapper) {
                        const tagFormId = parseInt(document.getElementById('tagFormId').value, 10);
                        const cbVal = parseInt(cb.value, 10);
                        if (cbVal === tagFormId) {
                            wrapper.style.display = 'none';
                        } else if (q === '' || name.includes(q)) {
                            wrapper.style.display = '';
                        } else {
                            wrapper.style.display = 'none';
                        }
                    }
                });
            });
        }

        // Open creation modal automatically if ?new=1 query param is set
        document.addEventListener('DOMContentLoaded', () => {
            const params = new URLSearchParams(window.location.search);
            if (params.get('new') === '1') {
                const url = new URL(window.location);
                url.searchParams.delete('new');
                history.replaceState(null, '', url);
                
                if (canManageTags) {
                    openNewTagModal();
                }
            }
        });
    </script>
    <script src="assets/tag-visualizations.js?v=20260625"></script>
</body>
</html>
