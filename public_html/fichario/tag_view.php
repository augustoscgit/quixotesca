<?php
declare(strict_types=1);

require __DIR__ . '/../../fichario/bootstrap.php';

$pdo = db();
$selectedTagId = isset($_GET['tag_id']) ? (int) $_GET['tag_id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);
$includeHierarchy = (string) ($_GET['include_hierarchy'] ?? '1') === '1';
$errors = [];
$notice = '';
$canManageTags = is_admin();
$canEditNotes = can_edit_content();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = (string) ($_POST['action'] ?? 'save');
    if ($postAction === 'update_note') {
        require_login();
        require_csrf();
        header('Content-Type: application/json; charset=utf-8');

        if (!can_edit_content()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Sem permissão para editar notas.']);
            exit;
        }

        $noteId = (int) ($_POST['note_id'] ?? 0);
        $quoteText = trim((string) ($_POST['quote_text'] ?? ''));
        $comment = trim((string) ($_POST['comment'] ?? ''));

        if ($noteId <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Nota inválida.']);
            exit;
        }

        $exists = $pdo->prepare('SELECT id FROM article_tag_quotes WHERE id = :id');
        $exists->execute([':id' => $noteId]);
        if (!$exists->fetchColumn()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Nota não encontrada.']);
            exit;
        }

        $update = $pdo->prepare('UPDATE article_tag_quotes SET quote_text = :quote_text, comment = :comment, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $update->execute([
            ':quote_text' => $quoteText,
            ':comment' => $comment,
            ':id' => $noteId,
        ]);

        echo json_encode([
            'success' => true,
            'note' => [
                'id' => $noteId,
                'quote_text' => $quoteText,
                'comment' => $comment,
                'quote_teaser' => text_teaser($quoteText, 210),
                'comment_teaser' => text_teaser($comment, 170),
            ],
        ]);
        exit;
    }

    if ($postAction === 'set_active_project') {
        require_login();
        require_csrf();
        header('Content-Type: application/json; charset=utf-8');
        
        $projectId = (int) ($_POST['project_id'] ?? 0);
        $_SESSION['active_project_id'] = $projectId;
        
        echo json_encode(['success' => true]);
        exit;
    }

    if ($postAction === 'link_note_to_section') {
        require_login();
        require_csrf();
        header('Content-Type: application/json; charset=utf-8');

        if (!can_edit_content()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Sem permissão para editar notas.']);
            exit;
        }

        $noteId = (int) ($_POST['note_id'] ?? 0);
        $sectionId = (int) ($_POST['section_id'] ?? 0);
        $activeProjectId = (int) ($_SESSION['active_project_id'] ?? 0);

        if ($activeProjectId <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Nenhum projeto ativo selecionado.']);
            exit;
        }
        if ($noteId <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Nota inválida.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $noteStmt = $pdo->prepare('SELECT id FROM article_tag_quotes WHERE id = :id');
            $noteStmt->execute([':id' => $noteId]);
            if (!$noteStmt->fetchColumn()) {
                throw new RuntimeException('Nota não encontrada.');
            }

            if ($sectionId <= 0) {
                $sectStmt = $pdo->prepare("SELECT id FROM project_sections WHERE project_id = :project_id AND lower(title) = 'geral' LIMIT 1");
                $sectStmt->execute([':project_id' => $activeProjectId]);
                $sectId = $sectStmt->fetchColumn();
                if ($sectId) {
                    $sectionId = (int) $sectId;
                } else {
                    $insSect = $pdo->prepare('INSERT INTO project_sections (project_id, title, context, position) VALUES (:project_id, :title, :context, :position)');
                    $stmtPos = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM project_sections WHERE project_id = :project_id');
                    $stmtPos->execute([':project_id' => $activeProjectId]);
                    $pos = (int) $stmtPos->fetchColumn();

                    $insSect->execute([
                        ':project_id' => $activeProjectId,
                        ':title' => 'Geral',
                        ':context' => 'Notas vinculadas diretamente ao projeto.',
                        ':position' => $pos
                    ]);
                    $sectionId = (int) $pdo->lastInsertId();
                }
            } else {
                $sectCheck = $pdo->prepare('SELECT id FROM project_sections WHERE id = :id AND project_id = :project_id LIMIT 1');
                $sectCheck->execute([':id' => $sectionId, ':project_id' => $activeProjectId]);
                if (!$sectCheck->fetchColumn()) {
                    throw new RuntimeException('Seção não encontrada neste projeto.');
                }
            }

            $stmtNotePos = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM project_section_notes WHERE section_id = :section_id');
            $stmtNotePos->execute([':section_id' => $sectionId]);
            $notePos = (int) $stmtNotePos->fetchColumn();

            $insLink = $pdo->prepare('
                INSERT INTO project_section_notes (section_id, note_id, position)
                VALUES (:section_id, :note_id, :position)
                ON CONFLICT (section_id, note_id) DO NOTHING
            ');
            $insLink->execute([
                ':section_id' => $sectionId,
                ':note_id' => $noteId,
                ':position' => $notePos
            ]);

            $pdo->commit();

            $linkStmt = $pdo->prepare("
                SELECT psn.note_id, psn.section_id, ps.title AS section_title
                FROM project_section_notes psn
                JOIN project_sections ps ON ps.id = psn.section_id
                WHERE ps.project_id = :project_id AND psn.note_id = :note_id
            ");
            $linkStmt->execute([':project_id' => $activeProjectId, ':note_id' => $noteId]);
            $updatedLinks = $linkStmt->fetchAll() ?: [];

            $sectStmt = $pdo->prepare('SELECT * FROM project_sections WHERE project_id = :project_id ORDER BY position ASC, id ASC');
            $sectStmt->execute([':project_id' => $activeProjectId]);
            $activeProjectSections = $sectStmt->fetchAll() ?: [];

            $html = render_note_project_linking_html($noteId, $updatedLinks, true, true, $activeProjectId, $activeProjectSections);

            echo json_encode(['success' => true, 'html' => $html]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($postAction === 'create_section_and_link_note') {
        require_login();
        require_csrf();
        header('Content-Type: application/json; charset=utf-8');

        if (!can_edit_content()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Sem permissão para editar notas.']);
            exit;
        }

        $noteId = (int) ($_POST['note_id'] ?? 0);
        $sectionTitle = trim((string) ($_POST['section_title'] ?? ''));
        $activeProjectId = (int) ($_SESSION['active_project_id'] ?? 0);

        if ($activeProjectId <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Nenhum projeto ativo selecionado.']);
            exit;
        }
        if ($noteId <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Nota inválida.']);
            exit;
        }
        if ($sectionTitle === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Título da seção é obrigatório.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $noteStmt = $pdo->prepare('SELECT id FROM article_tag_quotes WHERE id = :id');
            $noteStmt->execute([':id' => $noteId]);
            if (!$noteStmt->fetchColumn()) {
                throw new RuntimeException('Nota não encontrada.');
            }

            $stmtPos = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM project_sections WHERE project_id = :project_id');
            $stmtPos->execute([':project_id' => $activeProjectId]);
            $pos = (int) $stmtPos->fetchColumn();

            $insSect = $pdo->prepare('INSERT INTO project_sections (project_id, title, context, position) VALUES (:project_id, :title, :context, :position)');
            $insSect->execute([
                ':project_id' => $activeProjectId,
                ':title' => $sectionTitle,
                ':context' => '',
                ':position' => $pos
            ]);
            $sectionId = (int) $pdo->lastInsertId();

            $stmtNotePos = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM project_section_notes WHERE section_id = :section_id');
            $stmtNotePos->execute([':section_id' => $sectionId]);
            $notePos = (int) $stmtNotePos->fetchColumn();

            $insLink = $pdo->prepare('
                INSERT INTO project_section_notes (section_id, note_id, position)
                VALUES (:section_id, :note_id, :position)
                ON CONFLICT (section_id, note_id) DO NOTHING
            ');
            $insLink->execute([
                ':section_id' => $sectionId,
                ':note_id' => $noteId,
                ':position' => $notePos
            ]);

            $pdo->commit();

            $linkStmt = $pdo->prepare("
                SELECT psn.note_id, psn.section_id, ps.title AS section_title
                FROM project_section_notes psn
                JOIN project_sections ps ON ps.id = psn.section_id
                WHERE ps.project_id = :project_id AND psn.note_id = :note_id
            ");
            $linkStmt->execute([':project_id' => $activeProjectId, ':note_id' => $noteId]);
            $updatedLinks = $linkStmt->fetchAll() ?: [];

            $sectStmt = $pdo->prepare('SELECT * FROM project_sections WHERE project_id = :project_id ORDER BY position ASC, id ASC');
            $sectStmt->execute([':project_id' => $activeProjectId]);
            $activeProjectSections = $sectStmt->fetchAll() ?: [];

            $html = render_note_project_linking_html($noteId, $updatedLinks, true, true, $activeProjectId, $activeProjectSections);

            echo json_encode(['success' => true, 'html' => $html]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($postAction === 'unlink_note_from_section') {
        require_login();
        require_csrf();
        header('Content-Type: application/json; charset=utf-8');

        if (!can_edit_content()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Sem permissão para editar notas.']);
            exit;
        }

        $noteId = (int) ($_POST['note_id'] ?? 0);
        $sectionId = (int) ($_POST['section_id'] ?? 0);
        $activeProjectId = (int) ($_SESSION['active_project_id'] ?? 0);

        if ($activeProjectId <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Nenhum projeto ativo selecionado.']);
            exit;
        }
        if ($noteId <= 0 || $sectionId <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Nota ou seção inválida.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $delLink = $pdo->prepare('DELETE FROM project_section_notes WHERE section_id = :section_id AND note_id = :note_id');
            $delLink->execute([
                ':section_id' => $sectionId,
                ':note_id' => $noteId
            ]);

            $pdo->commit();

            $linkStmt = $pdo->prepare("
                SELECT psn.note_id, psn.section_id, ps.title AS section_title
                FROM project_section_notes psn
                JOIN project_sections ps ON ps.id = psn.section_id
                WHERE ps.project_id = :project_id AND psn.note_id = :note_id
            ");
            $linkStmt->execute([':project_id' => $activeProjectId, ':note_id' => $noteId]);
            $updatedLinks = $linkStmt->fetchAll() ?: [];

            $sectStmt = $pdo->prepare('SELECT * FROM project_sections WHERE project_id = :project_id ORDER BY position ASC, id ASC');
            $sectStmt->execute([':project_id' => $activeProjectId]);
            $activeProjectSections = $sectStmt->fetchAll() ?: [];

            $html = render_note_project_linking_html($noteId, $updatedLinks, true, true, $activeProjectId, $activeProjectSections);

            echo json_encode(['success' => true, 'html' => $html]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

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

// Handle Form Submission (Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? 'save') === 'save') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $definition = trim((string) ($_POST['definition'] ?? ''));
    $category = trim((string) ($_POST['category'] ?? ''));
    $parents = isset($_POST['parents']) ? array_map('intval', (array) $_POST['parents']) : [];
    $editTagId = (int) ($_POST['id'] ?? 0);

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
    if ($editTagId > 0 && $parents !== []) {
        foreach ($parents as $parentId) {
            if ($parentId === $editTagId) {
                $errors[] = 'Uma tag não pode ser superior (pai) de si mesma.';
                break;
            }

            $cycleStmt = $pdo->prepare("
                WITH RECURSIVE descendants(id) AS (
                    SELECT CAST(:current_id AS integer)
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
            $stmt = $pdo->prepare('UPDATE tags SET name = :name, definition = :definition, category = :category WHERE id = :id');
            $stmt->execute([
                ':name' => $name,
                ':definition' => $definition,
                ':category' => $category,
                ':id' => $editTagId
            ]);

            // Sync hierarchy relations (parents)
            $delStmt = $pdo->prepare('DELETE FROM tag_hierarchy WHERE child_id = :child_id');
            $delStmt->execute([':child_id' => $editTagId]);

            if ($parents !== []) {
                $insStmt = $pdo->prepare('INSERT INTO tag_hierarchy (parent_id, child_id) VALUES (:parent_id, :child_id)');
                foreach ($parents as $parentId) {
                    $insStmt->execute([
                        ':parent_id' => $parentId,
                        ':child_id' => $editTagId
                    ]);
                }
            }

            $pdo->commit();
            header('Location: tag_view.php?tag_id=' . $editTagId);
            exit;
        } catch (Throwable $dbError) {
            $pdo->rollBack();
            $errors[] = 'Erro no banco de dados: ' . $dbError->getMessage();
        }
    }
}

// Fetch tag info
if ($selectedTagId <= 0) {
    header('Location: tags.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM tags WHERE id = :id');
$stmt->execute([':id' => $selectedTagId]);
$selectedTag = $stmt->fetch() ?: null;

if (!$selectedTag) {
    header('Location: tags.php');
    exit;
}

// Fetch all tags for editing modal (excluding current tag to avoid self-parenting)
$allTagsList = $pdo->query('SELECT * FROM tags ORDER BY lower(category) ASC, lower(name) ASC')->fetchAll();

// Get active parents of this tag
$pStmt = $pdo->prepare('SELECT parent_id FROM tag_hierarchy WHERE child_id = :child_id');
$pStmt->execute([':child_id' => $selectedTagId]);
$activeParents = $pStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

// Tag stats
$tagStatsStmt = $pdo->prepare("
    WITH RECURSIVE descendants(id) AS (
        SELECT CAST(:tag_id AS integer)
        UNION
        SELECT child_id FROM tag_hierarchy th
        JOIN descendants d ON th.parent_id = d.id
    )
    SELECT
        (
            SELECT COUNT(DISTINCT article_id)
            FROM (
                SELECT q.article_id
                FROM article_tag_quotes q
                JOIN article_quote_tags qt ON qt.quote_id = q.id
                WHERE qt.tag_id = :tag_id
                UNION
                SELECT article_id
                FROM article_tags
                WHERE tag_id = :tag_id
            ) AS sub_direct
        ) AS direct_count,
        (
            SELECT COUNT(DISTINCT article_id)
            FROM (
                SELECT q.article_id
                FROM article_tag_quotes q
                JOIN article_quote_tags qt ON qt.quote_id = q.id
                WHERE qt.tag_id IN (SELECT id FROM descendants)
                UNION
                SELECT article_id
                FROM article_tags
                WHERE tag_id IN (SELECT id FROM descendants)
            ) AS sub_recursive
        ) AS recursive_count,
        (
            SELECT COUNT(DISTINCT q.id)
            FROM article_tag_quotes q
            JOIN article_quote_tags qt ON qt.quote_id = q.id
            WHERE qt.tag_id IN (SELECT id FROM descendants)
        ) AS note_count
");
$tagStatsStmt->execute([':tag_id' => $selectedTagId]);
$statsRow = $tagStatsStmt->fetch();
$stats = [
    'direct' => (int) ($statsRow['direct_count'] ?? 0),
    'recursive' => (int) ($statsRow['recursive_count'] ?? 0),
    'notes' => (int) ($statsRow['note_count'] ?? 0),
];

$directNoteCountStmt = $pdo->prepare('SELECT COUNT(DISTINCT quote_id) FROM article_quote_tags WHERE tag_id = :tag_id');
$directNoteCountStmt->execute([':tag_id' => $selectedTagId]);
$directNoteCount = (int) $directNoteCountStmt->fetchColumn();
$displayNoteCount = $includeHierarchy ? $stats['notes'] : $directNoteCount;

// Parents query
$parentsStmt = $pdo->prepare("
    SELECT id, name, definition, category FROM tags
    WHERE id IN (SELECT parent_id FROM tag_hierarchy WHERE child_id = :tag_id)
    ORDER BY lower(name) ASC
");
$parentsStmt->execute([':tag_id' => $selectedTagId]);
$parents = $parentsStmt->fetchAll() ?: [];

// Children query
$childrenStmt = $pdo->prepare("
    SELECT id, name, definition, category FROM tags
    WHERE id IN (SELECT child_id FROM tag_hierarchy WHERE parent_id = :tag_id)
    ORDER BY lower(name) ASC
");
$childrenStmt->execute([':tag_id' => $selectedTagId]);
$children = $childrenStmt->fetchAll() ?: [];

// Associated notes and articles
$matchingNotesWhere = $includeHierarchy
    ? 'qt.tag_id IN (SELECT id FROM descendants)'
    : 'qt.tag_id = :tag_id';
$noteStmt = $pdo->prepare("
    WITH RECURSIVE descendants(id) AS (
        SELECT CAST(:tag_id AS integer)
        UNION
        SELECT child_id FROM tag_hierarchy th
        JOIN descendants d ON th.parent_id = d.id
    ),
    matching_notes(id) AS (
        SELECT DISTINCT q.id
        FROM article_tag_quotes q
        JOIN article_quote_tags qt ON qt.quote_id = q.id
        WHERE {$matchingNotesWhere}
    )
    SELECT
        a.id AS article_id,
        a.title,
        a.authors,
        a.year,
        a.journal,
        a.abstract,
        a.pdf_url,
        a.url,
        q.id AS note_id,
        q.quote_text,
        q.comment AS note_comment,
        q.created_at,
        q.updated_at,
        t.id AS tag_id,
        t.name AS tag_name,
        t.definition AS tag_definition,
        t.category AS tag_category
    FROM articles a
    JOIN article_tag_quotes q ON q.article_id = a.id
    JOIN matching_notes mn ON mn.id = q.id
    JOIN article_quote_tags qt_all ON qt_all.quote_id = q.id
    JOIN tags t ON t.id = qt_all.tag_id
    ORDER BY
        a.year DESC,
        lower(a.title) ASC,
        q.id DESC,
        CASE
            WHEN lower(t.category) = 'método' OR lower(t.category) = 'metodo' THEN 1
            WHEN lower(t.category) = 'fonte' THEN 2
            WHEN lower(t.category) = 'tema' THEN 3
            ELSE 4
        END,
        lower(t.name) ASC
");
$noteStmt->execute([':tag_id' => $selectedTagId]);
$noteRows = $noteStmt->fetchAll() ?: [];

$selectedArticles = [];
foreach ($noteRows as $row) {
    $articleId = (int) $row['article_id'];
    $noteId = (int) $row['note_id'];

    if (!isset($selectedArticles[$articleId])) {
        $selectedArticles[$articleId] = [
            'id' => $articleId,
            'title' => $row['title'],
            'authors' => $row['authors'],
            'year' => $row['year'],
            'journal' => $row['journal'],
            'abstract' => $row['abstract'],
            'pdf_url' => $row['pdf_url'],
            'url' => $row['url'],
            'notes' => [],
        ];
    }

    if (!isset($selectedArticles[$articleId]['notes'][$noteId])) {
        $selectedArticles[$articleId]['notes'][$noteId] = [
            'id' => $noteId,
            'quote_text' => $row['quote_text'] ?? '',
            'comment' => $row['note_comment'] ?? '',
            'created_at' => $row['created_at'] ?? '',
            'updated_at' => $row['updated_at'] ?? '',
        'tags' => [],
        ];
    }

    $selectedArticles[$articleId]['notes'][$noteId]['tags'][] = [
        'id' => (int) $row['tag_id'],
        'name' => $row['tag_name'],
        'definition' => $row['tag_definition'] ?? '',
        'category' => trim((string) ($row['tag_category'] ?? '')) !== '' ? $row['tag_category'] : 'Outros',
    ];
}

foreach ($selectedArticles as &$articleGroup) {
    $articleGroup['notes'] = array_values($articleGroup['notes']);
}
unset($articleGroup);
$selectedArticles = array_values($selectedArticles);

// Load user projects and active project state
$currentUser = current_user();
$userId = $currentUser ? (int) $currentUser['id'] : 0;
$isLoggedIn = $currentUser !== null;

$userProjects = [];
$activeProject = null;
$activeProjectSections = [];
$linkedNotesState = [];
$activeProjectId = 0;

if ($isLoggedIn) {
    $projSql = 'SELECT id, title FROM projects';
    $projParams = [];
    if (!is_admin()) {
        $projSql .= ' WHERE owner_user_id = :owner_user_id';
        $projParams[':owner_user_id'] = $userId;
    }
    $projSql .= ' ORDER BY title ASC';
    $projStmt = $pdo->prepare($projSql);
    $projStmt->execute($projParams);
    $userProjects = $projStmt->fetchAll() ?: [];

    $activeProjectId = (int) ($_SESSION['active_project_id'] ?? 0);
    if ($activeProjectId > 0) {
        $projCheckSql = 'SELECT * FROM projects WHERE id = :id';
        $projCheckParams = [':id' => $activeProjectId];
        if (!is_admin()) {
            $projCheckSql .= ' AND owner_user_id = :owner_user_id';
            $projCheckParams[':owner_user_id'] = $userId;
        }
        $projCheckStmt = $pdo->prepare($projCheckSql);
        $projCheckStmt->execute($projCheckParams);
        $activeProject = $projCheckStmt->fetch();

        if ($activeProject) {
            $sectStmt = $pdo->prepare('SELECT * FROM project_sections WHERE project_id = :project_id ORDER BY position ASC, id ASC');
            $sectStmt->execute([':project_id' => $activeProjectId]);
            $activeProjectSections = $sectStmt->fetchAll() ?: [];

            // Find all note IDs in tag_view.php matching notes list
            $allNoteIds = [];
            foreach ($selectedArticles as $artGroup) {
                foreach ($artGroup['notes'] as $nt) {
                    $allNoteIds[] = (int) $nt['id'];
                }
            }
            if ($allNoteIds !== []) {
                $inClause = implode(',', $allNoteIds);
                $linkStmt = $pdo->prepare("
                    SELECT psn.note_id, psn.section_id, ps.title AS section_title
                    FROM project_section_notes psn
                    JOIN project_sections ps ON ps.id = psn.section_id
                    WHERE ps.project_id = :project_id AND psn.note_id IN ($inClause)
                ");
                $linkStmt->execute([':project_id' => $activeProjectId]);
                foreach (($linkStmt->fetchAll() ?: []) as $linkRow) {
                    $linkedNotesState[(int)$linkRow['note_id']][] = [
                        'section_id' => (int)$linkRow['section_id'],
                        'section_title' => $linkRow['section_title'],
                    ];
                }
            }
        } else {
            $_SESSION['active_project_id'] = 0;
            $activeProjectId = 0;
        }
    }
}

// Helper function to render note project linking controls
function render_note_project_linking_html(int $noteId, array $links, bool $isLoggedIn, bool $canEditNotes, int $activeProjectId, array $activeProjectSections): string {
    if ($activeProjectId <= 0 || !$isLoggedIn) {
        return '';
    }
    
    ob_start();
    ?>
    <div class="mt-3 pt-2 border-top border-secondary border-opacity-25" style="font-size: 0.8rem;">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
            <span class="text-secondary small d-flex align-items-center gap-1">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                Projeto Ativo:
            </span>
            
            <div class="d-flex flex-wrap gap-1 align-items-center" id="note-links-container-<?= $noteId ?>">
                <?php if ($links !== []): ?>
                    <?php foreach ($links as $link): ?>
                        <span class="badge bg-primary bg-opacity-15 text-primary border border-primary border-opacity-25 d-inline-flex align-items-center gap-1 py-1 px-2" style="font-size: 0.7rem; border-radius: 6px; background-color: rgba(59, 130, 246, 0.15) !important; color: #93c5fd !important; border-color: rgba(59, 130, 246, 0.25) !important;">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-1"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path><path d="m9 14 2 2 4-4"></path></svg>
                            <?= h($link['section_title']) ?>
                            <?php if ($canEditNotes): ?>
                                <button type="button" class="btn btn-sm btn-link p-0 text-danger border-0 d-inline-flex align-items-center" onclick="unlinkNoteFromSection(<?= $noteId ?>, <?= $link['section_id'] ?>)" title="Remover vínculo" style="vertical-align: middle;">
                                    <span class="text-danger ms-1 fw-bold" style="font-size: 0.85rem; line-height: 1;">&times;</span>
                                </button>
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="text-secondary italic" style="font-size: 0.75rem;">Não vinculado</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($canEditNotes): ?>
            <div class="d-flex gap-1 align-items-center">
                <select class="form-select form-select-sm py-0 px-2" id="note-link-select-<?= $noteId ?>" style="font-size: 0.72rem; height: auto;" onchange="handleNoteLinkAction(<?= $noteId ?>, this)">
                    <option value="">+ Vincular a uma seção...</option>
                    <option value="0">Vincular diretamente (Geral)</option>
                    <?php foreach ($activeProjectSections as $sect): ?>
                        <?php 
                        $alreadyLinked = false;
                        foreach ($links as $lk) {
                            if ($lk['section_id'] === (int)$sect['id']) {
                                $alreadyLinked = true;
                                break;
                            }
                        }
                        if ($alreadyLinked) continue;
                        ?>
                        <option value="<?= (int)$sect['id'] ?>"><?= h($sect['title']) ?></option>
                    <?php endforeach; ?>
                    <option value="new">+ Criar nova seção...</option>
                </select>
            </div>

            <!-- Inline form for new section creation -->
            <div class="d-none mt-2" id="new-section-input-container-<?= $noteId ?>">
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" id="new-section-title-<?= $noteId ?>" placeholder="Nome da seção" style="font-size: 0.72rem;">
                    <button class="btn btn-primary" type="button" onclick="submitCreateSectionAndLink(<?= $noteId ?>)" style="font-size: 0.72rem;">Salvar</button>
                    <button class="btn btn-outline-secondary text-white" type="button" onclick="cancelCreateSectionInline(<?= $noteId ?>)" style="font-size: 0.72rem;">X</button>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

$cColor = get_tag_colors($selectedTag['category'] ?? '');
?>
<!doctype html>
<html lang="pt-br" data-module="fichario">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($selectedTag['name']) ?> - Detalhes da Tag</title>
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/app.css?v=20260615" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script>
        if (window.history && 'scrollRestoration' in window.history) {
            window.history.scrollRestoration = 'manual';
        }

        if (!window.location.hash) {
            window.addEventListener('pageshow', () => window.scrollTo(0, 0), { once: true });
            window.addEventListener('load', () => requestAnimationFrame(() => window.scrollTo(0, 0)), { once: true });
        }
    </script>
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

        .tag-parents-select {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.15);
            border-radius: 8px;
            padding: 10px;
        }

        .article-card {
            transition: all 0.25s;
        }
        .article-card:hover {
            background-color: rgba(255,255,255,0.05);
            border-color: rgba(59, 130, 246, 0.4) !important;
            box-shadow: 0 8px 32px var(--color-primary-glow);
        }

        .text-block {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            word-break: break-word;
            line-height: 1.6;
        }

        .marking-preview {
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-word;
            line-height: 1.45;
            font-size: 0.82rem;
        }

        .note-teaser-label {
            display: block;
            color: var(--text-muted, #9ca3af);
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 0.15rem;
        }

        .tag-badge-current {
            box-shadow: 0 0 0 1px rgba(255,255,255,0.35), 0 0 18px rgba(59,130,246,0.18);
        }

        .note-modal-text {
            max-height: 62vh;
            overflow-y: auto;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            word-break: break-word;
            line-height: 1.65;
        }

        .note-edit-textarea {
            min-height: 34vh;
            resize: vertical;
        }

        .hierarchy-switch-control {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            min-height: 1.75rem;
        }

        .hierarchy-switch-control .form-check-input {
            width: 2.35rem;
            height: 1.25rem;
            margin: 0;
            border-radius: 999px !important;
            background: radial-gradient(circle at 0.58rem 50%, rgba(108, 117, 125, 0.95) 0 0.39rem, transparent 0.41rem) var(--bs-body-bg) no-repeat left center / 100% 100% !important;
            flex: 0 0 auto;
        }

        .hierarchy-switch-control .form-check-input:checked {
            background: radial-gradient(circle at calc(100% - 0.58rem) 50%, #fff 0 0.39rem, transparent 0.41rem) var(--bs-primary) no-repeat right center / 100% 100% !important;
            border-color: var(--bs-primary) !important;
        }

        .hierarchy-switch-control .form-check-label {
            margin: 0;
            line-height: 1.2;
            white-space: nowrap;
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
                <li class="breadcrumb-item"><a href="tags.php">Tags</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page"><?= h($selectedTag['name']) ?></li>
            </ol>
        </nav>

        <!-- Back navigation button -->
        <div class="mb-4">
            <a href="tags.php" class="btn btn-outline-secondary rounded-pill px-3 d-inline-flex align-items-center gap-2">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                Voltar para Navegação
            </a>
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

        <div class="row g-4">
            <!-- Left Side: Tag Metadata & Taxonomy Tree Context -->
            <div class="col-lg-5">
                <article class="glass-card p-4 h-100">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                        <div>
                            <span class="badge border tag-badge mb-2" style="background:<?= $cColor['bg'] ?>; color:<?= $cColor['text'] ?>; border-color:<?= $cColor['border'] ?> !important;" <?= tag_tooltip_attrs($selectedTag) ?>>
                                <?= h($selectedTag['category'] ?: 'Sem agrupamento') ?>
                            </span>
                            <h1 class="h3 text-white fw-bold mb-1"><?= h($selectedTag['name']) ?></h1>
                            <div class="text-secondary small mt-2">
                                Artigos indexados: <strong class="text-white-50"><?= $stats['direct'] ?></strong> direto(s) | <strong class="text-white-50"><?= $stats['recursive'] ?></strong> total (incluindo sub-tags)
                            </div>
                            <div class="text-secondary small mt-1">
                                Notas relacionadas: <strong class="text-white-50"><?= $displayNoteCount ?></strong>
                                <?php if (!$includeHierarchy && $stats['notes'] > $directNoteCount): ?>
                                    <span class="text-secondary">(<?= $stats['notes'] ?> com sub-tags)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($canManageTags): ?>
                    <div class="d-flex justify-content-end gap-2 mb-4">
                        <button class="btn btn-sm btn-outline-primary rounded-pill px-3" 
                                data-tag-id="<?= $selectedTag['id'] ?>" 
                                data-tag-name="<?= h($selectedTag['name']) ?>" 
                                data-tag-category="<?= h($selectedTag['category']) ?>" 
                                data-tag-definition="<?= h($selectedTag['definition']) ?>"
                                data-tag-parents='<?= json_encode($activeParents) ?>'
                                onclick="openEditTagModal(this)">
                            Editar Tag
                        </button>
                        <button class="btn btn-sm btn-outline-danger rounded-pill px-3"
                                type="button"
                                data-delete-tag="1"
                                data-tag-id="<?= (int) $selectedTag['id'] ?>"
                                data-tag-name="<?= h($selectedTag['name']) ?>"
                                data-child-count="<?= count($children) ?>"
                                data-note-count="<?= $directNoteCount ?>">
                            Excluir Tag
                        </button>
                    </div>
                    <?php endif; ?>

                    <hr class="border-secondary border-opacity-25 mb-4">

                    <!-- Theoretical definition / Criteria -->
                    <div class="mb-4 bg-black bg-opacity-20 p-3 rounded-3 border border-secondary border-opacity-10">
                        <h3 class="h6 text-secondary fw-semibold small mb-2 text-uppercase" style="letter-spacing: 0.04em;">Definição Teórica / Critérios</h3>
                        <?php if (trim((string) ($selectedTag['definition'] ?? '')) !== ''): ?>
                            <p class="text-white-50 small mb-0 text-block"><?= h($selectedTag['definition']) ?></p>
                        <?php else: ?>
                            <p class="text-secondary small mb-0 style-italic">Nenhuma definição teórica cadastrada para este conceito.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Hierarchy Connections -->
                    <div class="vstack gap-3 border-top border-secondary border-opacity-20 pt-4">
                        <div>
                            <h3 class="h6 text-secondary fw-semibold small mb-2 text-uppercase" style="letter-spacing: 0.04em;">Relações Superiores (Pai)</h3>
                            <?php if ($parents !== []): ?>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($parents as $parent): ?>
                                        <?php $pColor = get_tag_colors($parent['category'] ?? ''); ?>
                                        <a class="badge border tag-badge text-decoration-none"
                                           style="background:<?= $pColor['bg'] ?>; color:<?= $pColor['text'] ?>; border-color:<?= $pColor['border'] ?> !important;" 
                                           <?= tag_tooltip_attrs($parent) ?>
                                           href="tag_view.php?tag_id=<?= (int) $parent['id'] ?>"><?= h($parent['name']) ?></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-secondary small mb-0">Esta tag é um conceito de nível raiz (não possui superiores).</p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <h3 class="h6 text-secondary fw-semibold small mb-2 text-uppercase" style="letter-spacing: 0.04em;">Sub-tags (Filhas / Especificações)</h3>
                            <?php if ($children !== []): ?>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($children as $child): ?>
                                        <?php $chColor = get_tag_colors($child['category'] ?? ''); ?>
                                        <a class="badge border tag-badge text-decoration-none"
                                           style="background:<?= $chColor['bg'] ?>; color:<?= $chColor['text'] ?>; border-color:<?= $chColor['border'] ?> !important;" 
                                           <?= tag_tooltip_attrs($child) ?>
                                           href="tag_view.php?tag_id=<?= (int) $child['id'] ?>"><?= h($child['name']) ?></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-secondary small mb-0">Não possui especificações/sub-tags vinculadas.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            </div>

            <!-- Right Side: Associated Articles & Notes -->
            <div class="col-lg-7">
                <section class="glass-card p-4 h-100">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4 border-bottom border-secondary border-opacity-20 pb-3">
                        <div>
                            <h2 class="h5 text-white fw-bold mb-1">Artigos & Notas Relacionadas</h2>
                            <span class="text-secondary small fw-medium"><?= count($selectedArticles) ?> artigo(s) | <?= $displayNoteCount ?> nota(s)</span>
                        </div>
                        <div class="ms-auto d-flex align-items-center gap-3 flex-wrap justify-content-end text-end">
                            <form method="get" action="tag_view.php" class="m-0">
                                <input type="hidden" name="tag_id" value="<?= $selectedTagId ?>">
                                <input type="hidden" name="include_hierarchy" value="0">
                                <div class="form-check form-switch hierarchy-switch-control ps-0 mb-0">
                                    <label class="form-check-label text-secondary small" for="include-hierarchy-switch">Incluir hierarquia</label>
                                    <input class="form-check-input" type="checkbox" role="switch" id="include-hierarchy-switch" name="include_hierarchy" value="1" <?= $includeHierarchy ? 'checked' : '' ?> onchange="this.form.submit()">
                                </div>
                            </form>
                            <div class="form-check form-switch hierarchy-switch-control ps-0 mb-0">
                                <label class="form-check-label text-secondary small" for="expand-articles-switch">Expandir artigos</label>
                                <input class="form-check-input" type="checkbox" role="switch" id="expand-articles-switch" checked>
                            </div>
                        </div>
                    </div>

                    <?php if ($isLoggedIn): ?>
                        <div class="mb-4 p-3 bg-black bg-opacity-25 border border-secondary border-opacity-25 rounded-3">
                            <label class="form-label text-secondary small mb-1" for="active-project-select">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-1" style="vertical-align:-1px;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                                Projeto de Trabalho Ativo:
                            </label>
                            <select class="form-select form-select-sm" id="active-project-select" onchange="setActiveProject(this.value)">
                                <option value="">Nenhum - Trabalhar sem vincular a projeto</option>
                                <?php foreach ($userProjects as $proj): ?>
                                    <option value="<?= $proj['id'] ?>" <?= $proj['id'] === $activeProjectId ? 'selected' : '' ?>>
                                        <?= h($proj['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($selectedArticles === []): ?>
                        <div class="text-center py-5 text-secondary">
                            <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24" class="mb-3" style="opacity:0.4;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"/></svg>
                            <p class="small mb-0">Nenhuma nota indexada sob este conceito ainda.</p>
                        </div>
                    <?php else: ?>
                        <div class="vstack gap-3">
                            <?php foreach ($selectedArticles as $article): ?>
                                <div class="p-3 border border-secondary border-opacity-10 rounded-3 article-card mb-3" style="background: rgba(255,255,255,0.01);">
                                    <div class="mb-3">
                                        <h3 class="h6 mb-0">
                                            <a href="view.php?id=<?= (int) $article['id'] ?>" class="text-white text-decoration-none fw-bold hover-primary"><?= h($article['title']) ?></a>
                                            <?php if ((string) ($article['year'] ?? '') !== ''): ?>
                                                <span class="badge border border-secondary border-opacity-25 bg-secondary bg-opacity-10 text-white-50 ms-2" style="font-size: 0.72rem; font-weight: 500; vertical-align: middle;">
                                                    <?= h((string) $article['year']) ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php
                                                $tooltipTitle = '';
                                                if (trim((string) ($article['authors'] ?? '')) !== '') {
                                                    $tooltipTitle .= 'Autores: ' . h($article['authors']) . "\n";
                                                }
                                                if (trim((string) ($article['journal'] ?? '')) !== '') {
                                                    $tooltipTitle .= 'Fonte: ' . h($article['journal']);
                                                }
                                                $tooltipTitle = trim($tooltipTitle);
                                            ?>
                                            <?php if ($tooltipTitle !== ''): ?>
                                                <span class="text-secondary cursor-help d-inline-flex align-items-center ms-2" 
                                                      data-bs-toggle="tooltip" 
                                                      data-bs-placement="top" 
                                                      title="<?= $tooltipTitle ?>"
                                                      style="cursor: help; vertical-align: middle;">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white-50 opacity-75"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                                                </span>
                                            <?php endif; ?>
                                        </h3>
                                    </div>
                                    
                                    <div class="vstack gap-2 mb-3">
                                        <?php foreach ($article['notes'] as $note): ?>
                                            <?php
                                                $noteQuote = trim((string) ($note['quote_text'] ?? ''));
                                                $noteComment = trim((string) ($note['comment'] ?? ''));
                                                $noteEmpty = $noteQuote === '' && $noteComment === '';
                                                $noteQuoteTeaser = text_teaser($noteQuote, 210);
                                                $noteCommentTeaser = text_teaser($noteComment, 170);
                                            ?>
                                            <div class="note-card p-2 rounded-3 border border-secondary border-opacity-25 bg-black bg-opacity-25 small text-white-50"
                                                 data-note-id="<?= (int) $note['id'] ?>"
                                                 data-note-article-id="<?= (int) $article['id'] ?>"
                                                 data-note-article-title="<?= h($article['title']) ?>"
                                                 data-note-quote="<?= h($noteQuote) ?>"
                                                 data-note-comment="<?= h($noteComment) ?>"
                                                 data-note-tags="<?= h(json_encode($note['tags'] ?? [], JSON_UNESCAPED_UNICODE)) ?>">
                                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                                    <?php if (($note['tags'] ?? []) !== []): ?>
                                                        <div class="d-flex flex-wrap gap-1">
                                                            <?php foreach ($note['tags'] as $noteTag): ?>
                                                                <?php
                                                                    $nColor = get_tag_colors($noteTag['category'] ?? '');
                                                                    $isCurrentTag = (int) $noteTag['id'] === $selectedTagId;
                                                                ?>
                                                                <a href="tag_view.php?tag_id=<?= (int) $noteTag['id'] ?>"
                                                               class="badge border tag-badge text-decoration-none <?= $isCurrentTag ? 'tag-badge-current' : '' ?>"
                                                               style="background:<?= $nColor['bg'] ?>; color:<?= $nColor['text'] ?>; border-color:<?= $nColor['border'] ?> !important;"
                                                               <?= tag_tooltip_attrs($noteTag) ?>>
                                                                <?= h($noteTag['name']) ?>
                                                            </a>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="d-flex gap-2 flex-shrink-0">
                                                        <button class="btn btn-sm btn-link p-0 text-white-50" type="button" onclick="openTagNoteReadFromButton(this)" title="Ler nota">
                                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                                        </button>
                                                        <?php if ($canEditNotes): ?>
                                                            <button class="btn btn-sm btn-link p-0 text-white-50" type="button" onclick="openTagNoteEditFromButton(this)" title="Editar nota">
                                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <div class="note-content">
                                                 <?php if ($noteEmpty): ?>
                                                     <em class="text-secondary">Nota sem citação ou observação.</em>
                                                 <?php else: ?>
                                                     <?php if ($noteQuote !== ''): ?>
                                                         <div class="marking-preview marking-preview-quote mb-2">
                                                             <span class="note-teaser-label">Citação</span>
                                                             <div class="quote-box expandable-text" onclick="toggleExpandableText(this)" title="Clique para expandir/recolher"><?= h($noteQuote) ?></div>
                                                         </div>
                                                     <?php endif; ?>
                                                     <?php if ($noteComment !== ''): ?>
                                                         <div class="marking-preview marking-preview-comment">
                                                             <span class="note-teaser-label">Observação</span>
                                                             <div class="observation-box expandable-text" onclick="toggleExpandableText(this)" title="Clique para expandir/recolher"><?= h($noteComment) ?></div>
                                                         </div>
                                                     <?php endif; ?>
                                                 <?php endif; ?>
                                                 </div>

                                                  <!-- Note Project Linking controls wrapper -->
                                                  <div id="note-project-linking-wrapper-<?= (int)$note['id'] ?>">
                                                      <?= render_note_project_linking_html(
                                                          (int)$note['id'],
                                                          $linkedNotesState[(int)$note['id']] ?? [],
                                                          $isLoggedIn,
                                                          $canEditNotes,
                                                          $activeProjectId,
                                                          $activeProjectSections
                                                      ) ?>
                                                  </div>
                                             </div>
                                        <?php endforeach; ?>
                                    </div>


                                    <div class="d-flex justify-content-end gap-2 pt-2 border-top border-secondary border-opacity-10">
                                        <?php if (trim((string) ($article['url'] ?? '')) !== ''): ?>
                                            <a class="btn btn-sm btn-outline-info px-3 rounded-pill d-inline-flex align-items-center gap-1" style="font-size:0.75rem;"
                                               href="<?= h($article['url']) ?>" 
                                               target="_blank" 
                                               rel="noopener noreferrer" 
                                               title="Abrir URL original em nova aba">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                                                URL ↗
                                            </a>
                                        <?php endif; ?>
                                        <?php if (trim((string) ($article['pdf_url'] ?? '')) !== ''): ?>
                                            <a class="btn btn-sm btn-outline-danger px-3 rounded-pill d-inline-flex align-items-center gap-1" style="font-size:0.75rem;"
                                               href="<?= h($article['pdf_url']) ?>" 
                                               target="_blank" 
                                               rel="noopener noreferrer" 
                                               title="Abrir PDF original em nova aba">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                                                PDF ↗
                                            </a>
                                        <?php endif; ?>
                                        <a href="view.php?id=<?= (int) $article['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 text-nowrap" style="font-size:0.75rem;">Abrir Ficha</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </main>

    <div class="modal fade" id="tagNoteModal" tabindex="-1" aria-labelledby="tagNoteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title h5 text-white fw-bold mb-1" id="tagNoteModalLabel">Leitura da nota</h2>
                        <div class="text-secondary small" id="tag-note-article-title"></div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex flex-wrap gap-2 mb-3" id="tag-note-modal-tags"></div>

                    <div id="tag-note-read-panel">
                        <section class="mb-3 d-none" id="tag-note-read-quote-section">
                            <h3 class="h6 text-secondary text-uppercase small mb-2">Citação</h3>
                            <div class="note-modal-text text-white p-3 rounded-3 bg-black bg-opacity-25" id="tag-note-read-quote"></div>
                        </section>
                        <section class="mb-0 d-none" id="tag-note-read-comment-section">
                            <h3 class="h6 text-secondary text-uppercase small mb-2">Observação</h3>
                            <div class="note-modal-text text-white-50 p-3 rounded-3 bg-black bg-opacity-25" id="tag-note-read-comment"></div>
                        </section>
                        <div class="text-warning d-none" id="tag-note-read-empty">! Nota sem citação ou observação.</div>
                    </div>

                    <?php if ($canEditNotes): ?>
                        <div class="d-none" id="tag-note-edit-panel">
                            <div class="mb-3">
                                <label class="form-label" for="tag-note-edit-quote">Citação</label>
                                <textarea class="form-control note-edit-textarea" id="tag-note-edit-quote"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="tag-note-edit-comment">Observação</label>
                                <textarea class="form-control" id="tag-note-edit-comment" rows="7"></textarea>
                            </div>
                            <div class="alert alert-danger d-none mb-0" id="tag-note-edit-error"></div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary text-white rounded-pill" data-bs-dismiss="modal">Fechar</button>
                    <?php if ($canEditNotes): ?>
                        <button type="button" class="btn btn-outline-primary rounded-pill" id="btn-tag-note-edit">Editar</button>
                        <button type="button" class="btn btn-outline-secondary text-white rounded-pill d-none" id="btn-tag-note-cancel-edit">Cancelar edição</button>
                        <button type="button" class="btn btn-primary rounded-pill d-none" id="btn-tag-note-save">Salvar nota</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($canManageTags): ?>
    <!-- Edit Modal -->
    <div class="modal fade" id="tagModal" tabindex="-1" aria-labelledby="tagModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5 text-white fw-bold" id="tagModalLabel">Editar Tag Temática</h2>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="tag_view.php?tag_id=<?= $selectedTagId ?>" id="tagForm">
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
                                <?php if ($allTagsList === []): ?>
                                    <p class="text-secondary small mb-0">Nenhuma tag cadastrada ainda para atuar como pai.</p>
                                <?php else: ?>
                                    <?php foreach ($allTagsList as $optionTag): ?>
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
                            <button type="submit" class="btn btn-primary rounded-pill px-4" id="submitBtn">Salvar Alterações</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden form for deleting tags -->
    <form method="post" action="tag_view.php?tag_id=<?= $selectedTagId ?>" id="deleteForm" style="display:none;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteFormId" value="0">
        <input type="hidden" name="clear_children" id="deleteFormClearChildren" value="0">
        <input type="hidden" name="confirm_notes" id="deleteFormConfirmNotes" value="0">
    </form>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/app.js?v=20260615"></script>
    <script>
        const canManageTags = <?= $canManageTags ? 'true' : 'false' ?>;
        const canEditNotes = <?= $canEditNotes ? 'true' : 'false' ?>;
        const selectedTagId = <?= (int) $selectedTagId ?>;
        const csrfToken = '<?= h(csrf_token()) ?>';
        const tagNoteModalEl = document.getElementById('tagNoteModal');
        const tagNoteModal = tagNoteModalEl ? bootstrap.Modal.getOrCreateInstance(tagNoteModalEl) : null;
        let currentTagNoteCard = null;
        let tagArticleNotesExpanded = true;

        <?php if ($canManageTags): ?>
        const tagModalEl = document.getElementById('tagModal');
        const tagModal = bootstrap.Modal.getOrCreateInstance(tagModalEl);
        <?php endif; ?>

        function escapeHtml(str) {
            return String(str || '').replace(/[&<>"']/g, char => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char]));
        }

        function textTeaser(text, maxLength) {
            const normalized = String(text || '').replace(/\s+/g, ' ').trim();
            if (!normalized || normalized.length <= maxLength) {
                return normalized;
            }
            return normalized.slice(0, maxLength).trimEnd() + '...';
        }

        function getNoteTagColors(category) {
            const normalized = normalizeStr(category || '');
            if (normalized === 'metodo') {
                return { bg: 'rgba(168, 85, 247, 0.15)', text: '#c084fc', border: 'rgba(168, 85, 247, 0.3)' };
            }
            if (normalized === 'fonte') {
                return { bg: 'rgba(16, 185, 129, 0.15)', text: '#34d399', border: 'rgba(16, 185, 129, 0.3)' };
            }
            if (normalized === 'tema') {
                return { bg: 'rgba(59, 130, 246, 0.15)', text: '#60a5fa', border: 'rgba(59, 130, 246, 0.3)' };
            }
            return { bg: 'rgba(255,255,255,0.05)', text: '#e5e7eb', border: 'rgba(255,255,255,0.1)' };
        }

        function initTagTooltips(scope = document) {
            scope.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
                bootstrap.Tooltip.getOrCreateInstance(el);
            });
        }

        initTagTooltips();

        function readNoteCardTags(card) {
            try {
                const parsed = JSON.parse(card.dataset.noteTags || '[]');
                return Array.isArray(parsed) ? parsed : [];
            } catch (error) {
                return [];
            }
        }

        function renderTagNoteModalTags(tags) {
            const container = document.getElementById('tag-note-modal-tags');
            if (!container) return;
            container.innerHTML = '';

            tags.forEach(tag => {
                const colors = getNoteTagColors(tag.category || '');
                const badge = document.createElement('a');
                badge.href = `tag_view.php?tag_id=${Number(tag.id)}`;
                badge.className = `badge border tag-badge text-decoration-none ${Number(tag.id) === selectedTagId ? 'tag-badge-current' : ''}`;
                badge.textContent = tag.name || '';
                badge.title = tag.definition || 'Sem definição cadastrada.';
                badge.dataset.bsToggle = 'tooltip';
                badge.dataset.bsPlacement = 'top';
                badge.dataset.bsTitle = badge.title;
                badge.style.background = colors.bg;
                badge.style.color = colors.text;
                badge.style.borderColor = colors.border;
                container.appendChild(badge);
            });

            initTagTooltips(container);
        }

        function fillNoteReadSection(sectionId, contentId, text) {
            const section = document.getElementById(sectionId);
            const content = document.getElementById(contentId);
            if (!section || !content) return false;

            const value = String(text || '').trim();
            section.classList.toggle('d-none', value === '');
            content.textContent = value;
            return value !== '';
        }

        function setTagNoteModalMode(mode) {
            const readPanel = document.getElementById('tag-note-read-panel');
            const editPanel = document.getElementById('tag-note-edit-panel');
            const editButton = document.getElementById('btn-tag-note-edit');
            const cancelButton = document.getElementById('btn-tag-note-cancel-edit');
            const saveButton = document.getElementById('btn-tag-note-save');
            const title = document.getElementById('tagNoteModalLabel');
            const editing = mode === 'edit';

            if (title) {
                title.textContent = editing ? 'Editar nota' : 'Leitura da nota';
            }
            if (readPanel) readPanel.classList.toggle('d-none', editing);
            if (editPanel) editPanel.classList.toggle('d-none', !editing);
            if (editButton) editButton.classList.toggle('d-none', editing);
            if (cancelButton) cancelButton.classList.toggle('d-none', !editing);
            if (saveButton) saveButton.classList.toggle('d-none', !editing);

            if (editing) {
                setTimeout(() => document.getElementById('tag-note-edit-quote')?.focus(), 100);
            }
        }

        function hydrateTagNoteModal(card) {
            currentTagNoteCard = card;
            const quote = card.dataset.noteQuote || '';
            const comment = card.dataset.noteComment || '';
            const hasQuote = fillNoteReadSection('tag-note-read-quote-section', 'tag-note-read-quote', quote);
            const hasComment = fillNoteReadSection('tag-note-read-comment-section', 'tag-note-read-comment', comment);
            const empty = document.getElementById('tag-note-read-empty');
            if (empty) empty.classList.toggle('d-none', hasQuote || hasComment);

            document.getElementById('tag-note-article-title').textContent = card.dataset.noteArticleTitle || '';
            renderTagNoteModalTags(readNoteCardTags(card));

            const quoteInput = document.getElementById('tag-note-edit-quote');
            const commentInput = document.getElementById('tag-note-edit-comment');
            if (quoteInput) quoteInput.value = quote;
            if (commentInput) commentInput.value = comment;
            const error = document.getElementById('tag-note-edit-error');
            if (error) {
                error.classList.add('d-none');
                error.textContent = '';
            }
        }

        function openTagNoteReadFromButton(button) {
            const card = button.closest('[data-note-id]');
            if (!card || !tagNoteModal) return;
            hydrateTagNoteModal(card);
            setTagNoteModalMode('read');
            tagNoteModal.show();
        }

        function openTagNoteEditFromButton(button) {
            if (!canEditNotes) return;
            const card = button.closest('[data-note-id]');
            if (!card || !tagNoteModal) return;
            hydrateTagNoteModal(card);
            setTagNoteModalMode('edit');
            tagNoteModal.show();
        }

        function renderNoteCardContent(card, quote, comment) {
            const container = card.querySelector('.note-content');
            if (!container) return;
            const quoteValue = String(quote || '').trim();
            const commentValue = String(comment || '').trim();

            if (!quoteValue && !commentValue) {
                container.innerHTML = '<em class="text-secondary">Nota sem citação ou observação.</em>';
                return;
            }

            const parts = [];
            const collapsedClass = tagArticleNotesExpanded ? '' : ' collapsed';
            if (quoteValue) {
                parts.push(`
                    <div class="marking-preview marking-preview-quote mb-2">
                        <span class="note-teaser-label">Citação</span>
                        <div class="quote-box expandable-text${collapsedClass}" onclick="toggleExpandableText(this)" title="Clique para expandir/recolher">${escapeHtml(quoteValue)}</div>
                    </div>
                `);
            }
            if (commentValue) {
                parts.push(`
                    <div class="marking-preview marking-preview-comment">
                        <span class="note-teaser-label">Observação</span>
                        <div class="observation-box expandable-text${collapsedClass}" onclick="toggleExpandableText(this)" title="Clique para expandir/recolher">${escapeHtml(commentValue)}</div>
                    </div>
                `);
            }
            container.innerHTML = parts.join('');
        }

        function setTagArticleNotesExpanded(expanded) {
            tagArticleNotesExpanded = expanded;
            document.querySelectorAll('.article-card .expandable-text').forEach((el) => {
                el.classList.toggle('collapsed', !expanded);
            });
        }

        const expandArticlesSwitch = document.getElementById('expand-articles-switch');
        if (expandArticlesSwitch) {
            setTagArticleNotesExpanded(expandArticlesSwitch.checked);
            expandArticlesSwitch.addEventListener('change', () => {
                setTagArticleNotesExpanded(expandArticlesSwitch.checked);
            });
        }

        const btnTagNoteEdit = document.getElementById('btn-tag-note-edit');
        if (btnTagNoteEdit) {
            btnTagNoteEdit.addEventListener('click', () => setTagNoteModalMode('edit'));
        }

        const btnTagNoteCancelEdit = document.getElementById('btn-tag-note-cancel-edit');
        if (btnTagNoteCancelEdit) {
            btnTagNoteCancelEdit.addEventListener('click', () => {
                if (currentTagNoteCard) {
                    hydrateTagNoteModal(currentTagNoteCard);
                }
                setTagNoteModalMode('read');
            });
        }

        const btnTagNoteSave = document.getElementById('btn-tag-note-save');
        if (btnTagNoteSave) {
            btnTagNoteSave.addEventListener('click', () => {
                if (!currentTagNoteCard) return;
                const error = document.getElementById('tag-note-edit-error');
                const quote = document.getElementById('tag-note-edit-quote').value.trim();
                const comment = document.getElementById('tag-note-edit-comment').value.trim();

                if (error) {
                    error.classList.add('d-none');
                    error.textContent = '';
                }

                const releaseNoteBusy = window.FicharioUI
                    ? FicharioUI.setBusy(btnTagNoteSave, true, 'Salvando...')
                    : () => {};

                const formData = new URLSearchParams();
                formData.append('csrf_token', csrfToken);
                formData.append('action', 'update_note');
                formData.append('note_id', currentTagNoteCard.dataset.noteId || '');
                formData.append('quote_text', quote);
                formData.append('comment', comment);

                fetch(`tag_view.php?tag_id=${selectedTagId}`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.error || 'Não foi possível salvar a nota.');
                    }

                    currentTagNoteCard.dataset.noteQuote = data.note.quote_text || '';
                    currentTagNoteCard.dataset.noteComment = data.note.comment || '';
                    renderNoteCardContent(currentTagNoteCard, data.note.quote_text || '', data.note.comment || '');
                    hydrateTagNoteModal(currentTagNoteCard);
                    setTagNoteModalMode('read');
                })
                .catch(err => {
                    if (error) {
                        error.textContent = err.message || 'Falha de rede ao salvar a nota.';
                        error.classList.remove('d-none');
                    }
                })
                .finally(() => {
                    releaseNoteBusy();
                });
            });
        }

        function normalizeStr(str) {
            return (str || '').normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim();
        }

        function openEditTagModal(btnEl) {
            if (!canManageTags) return;
            const id = btnEl.getAttribute('data-tag-id');
            const name = btnEl.getAttribute('data-tag-name');
            const category = btnEl.getAttribute('data-tag-category');
            const definition = btnEl.getAttribute('data-tag-definition');
            const parents = JSON.parse(btnEl.getAttribute('data-tag-parents') || '[]');

            document.getElementById('tagFormId').value = id;
            document.getElementById('tagFormName').value = name;
            document.getElementById('tagFormCategory').value = category;
            document.getElementById('tagFormDefinition').value = definition;

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

        // Project linking functions
        function setActiveProject(projectId) {
            const selector = document.getElementById('active-project-select');
            const releaseBusy = window.FicharioUI
                ? FicharioUI.setBusy(selector, true, 'Alterando...')
                : () => {};

            const formData = new URLSearchParams();
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'set_active_project');
            formData.append('project_id', projectId);

            fetch(`tag_view.php?tag_id=${selectedTagId}`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Erro ao definir projeto ativo: ' + (data.error || 'Erro desconhecido'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('Erro de rede ao definir projeto ativo.');
            })
            .finally(releaseBusy);
        }

        function handleNoteLinkAction(noteId, selectEl) {
            const val = selectEl.value;
            if (val === '') return;

            if (val === 'new') {
                document.getElementById(`new-section-input-container-${noteId}`).classList.remove('d-none');
                selectEl.classList.add('d-none');
                return;
            }

            const releaseBusy = window.FicharioUI
                ? FicharioUI.setBusy(selectEl, true, 'Vinculando...')
                : () => {};

            const formData = new URLSearchParams();
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'link_note_to_section');
            formData.append('note_id', String(noteId));
            formData.append('section_id', val);

            fetch(`tag_view.php?tag_id=${selectedTagId}`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const wrapper = document.getElementById(`note-project-linking-wrapper-${noteId}`);
                    if (wrapper) {
                        wrapper.innerHTML = data.html;
                    }
                } else {
                    alert('Erro ao vincular nota: ' + (data.error || 'Erro desconhecido'));
                    selectEl.value = '';
                }
            })
            .catch(err => {
                console.error(err);
                alert('Erro de rede ao vincular nota.');
                selectEl.value = '';
            })
            .finally(releaseBusy);
        }

        function unlinkNoteFromSection(noteId, sectionId) {
            if (!confirm('Deseja realmente desvincular esta nota desta seção do projeto?')) {
                return;
            }

            const container = document.getElementById(`note-links-container-${noteId}`);
            const releaseBusy = window.FicharioUI
                ? FicharioUI.setBusy(container, true, '')
                : () => {};

            const formData = new URLSearchParams();
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'unlink_note_from_section');
            formData.append('note_id', String(noteId));
            formData.append('section_id', String(sectionId));

            fetch(`tag_view.php?tag_id=${selectedTagId}`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const wrapper = document.getElementById(`note-project-linking-wrapper-${noteId}`);
                    if (wrapper) {
                        wrapper.innerHTML = data.html;
                    }
                } else {
                    alert('Erro ao desvincular nota: ' + (data.error || 'Erro desconhecido'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('Erro de rede ao desvincular nota.');
            })
            .finally(releaseBusy);
        }

        function submitCreateSectionAndLink(noteId) {
            const inputEl = document.getElementById(`new-section-title-${noteId}`);
            const btnEl = inputEl.nextElementSibling;
            const title = inputEl.value.trim();
            if (title === '') {
                alert('O título da seção é obrigatório.');
                return;
            }

            const releaseBusy = window.FicharioUI
                ? FicharioUI.setBusy(btnEl, true, 'Criando...')
                : () => {};

            const formData = new URLSearchParams();
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'create_section_and_link_note');
            formData.append('note_id', String(noteId));
            formData.append('section_title', title);

            fetch(`tag_view.php?tag_id=${selectedTagId}`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const wrapper = document.getElementById(`note-project-linking-wrapper-${noteId}`);
                    if (wrapper) {
                        wrapper.innerHTML = data.html;
                    }
                } else {
                    alert('Erro ao criar seção e vincular nota: ' + (data.error || 'Erro desconhecido'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('Erro de rede.');
            })
            .finally(releaseBusy);
        }

        function cancelCreateSectionInline(noteId) {
            document.getElementById(`new-section-input-container-${noteId}`).classList.add('d-none');
            const selectEl = document.getElementById(`note-link-select-${noteId}`);
            if (selectEl) {
                selectEl.classList.remove('d-none');
                selectEl.value = '';
            }
        }
    </script>
</body>
</html>
