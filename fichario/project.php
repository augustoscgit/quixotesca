<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_login();

$pdo = db();
$projectId = (int) ($_GET['id'] ?? 0);
$currentUser = current_user();
$userId = (int) ($currentUser['id'] ?? 0);

if ($projectId <= 0) {
    http_response_code(404);
    exit('Projeto nao encontrado.');
}

function set_project_flash(string $message, string $type = 'success'): void
{
    $_SESSION['project_flash'] = ['message' => $message, 'type' => $type];
}

function take_project_flash(): ?array
{
    $flash = $_SESSION['project_flash'] ?? null;
    unset($_SESSION['project_flash']);
    return is_array($flash) ? $flash : null;
}

function redirect_to_project(int $projectId): void
{
    header('Location: project.php?id=' . $projectId);
    exit;
}

function fetch_project(PDO $pdo, int $projectId, int $userId): ?array
{
    $sql = 'SELECT * FROM projects WHERE id = :id';
    $params = [':id' => $projectId];

    if (!is_admin()) {
        $sql .= ' AND owner_user_id = :owner_user_id';
        $params[':owner_user_id'] = $userId;
    }

    $stmt = $pdo->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    $project = $stmt->fetch();

    return $project ?: null;
}

function fetch_project_section(PDO $pdo, int $projectId, int $sectionId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM project_sections WHERE id = :id AND project_id = :project_id LIMIT 1');
    $stmt->execute([':id' => $sectionId, ':project_id' => $projectId]);
    $section = $stmt->fetch();

    return $section ?: null;
}

function touch_project(PDO $pdo, int $projectId): void
{
    $stmt = $pdo->prepare('UPDATE projects SET updated_at = CURRENT_TIMESTAMP WHERE id = :id');
    $stmt->execute([':id' => $projectId]);
}

function next_section_position(PDO $pdo, int $projectId): int
{
    $stmt = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM project_sections WHERE project_id = :project_id');
    $stmt->execute([':project_id' => $projectId]);

    return (int) $stmt->fetchColumn();
}

function next_note_position(PDO $pdo, int $sectionId): int
{
    $stmt = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM project_section_notes WHERE section_id = :section_id');
    $stmt->execute([':section_id' => $sectionId]);

    return (int) $stmt->fetchColumn();
}

function move_project_section(PDO $pdo, int $projectId, int $sectionId, string $direction): void
{
    $current = fetch_project_section($pdo, $projectId, $sectionId);
    if ($current === null) {
        throw new RuntimeException('Seção não encontrada.');
    }

    $operator = $direction === 'up' ? '<' : '>';
    $order = $direction === 'up' ? 'DESC' : 'ASC';
    $stmt = $pdo->prepare("
        SELECT * FROM project_sections
        WHERE project_id = :project_id AND position $operator :position
        ORDER BY position $order, id $order
        LIMIT 1
    ");
    $stmt->execute([':project_id' => $projectId, ':position' => (int) $current['position']]);
    $target = $stmt->fetch();

    if (!$target) {
        return;
    }

    $swap = $pdo->prepare('UPDATE project_sections SET position = :position, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
    $swap->execute([':position' => (int) $target['position'], ':id' => $sectionId]);
    $swap->execute([':position' => (int) $current['position'], ':id' => (int) $target['id']]);
}

function move_section_note(PDO $pdo, int $sectionId, int $noteId, string $direction): void
{
    $stmt = $pdo->prepare('SELECT * FROM project_section_notes WHERE section_id = :section_id AND note_id = :note_id LIMIT 1');
    $stmt->execute([':section_id' => $sectionId, ':note_id' => $noteId]);
    $current = $stmt->fetch();

    if (!$current) {
        throw new RuntimeException('Marcação não vinculada a esta seção.');
    }

    $operator = $direction === 'up' ? '<' : '>';
    $order = $direction === 'up' ? 'DESC' : 'ASC';
    $targetStmt = $pdo->prepare("
        SELECT * FROM project_section_notes
        WHERE section_id = :section_id AND position $operator :position
        ORDER BY position $order, note_id $order
        LIMIT 1
    ");
    $targetStmt->execute([':section_id' => $sectionId, ':position' => (int) $current['position']]);
    $target = $targetStmt->fetch();

    if (!$target) {
        return;
    }

    $swap = $pdo->prepare('UPDATE project_section_notes SET position = :position WHERE section_id = :section_id AND note_id = :note_id');
    $swap->execute([
        ':position' => (int) $target['position'],
        ':section_id' => $sectionId,
        ':note_id' => $noteId,
    ]);
    $swap->execute([
        ':position' => (int) $current['position'],
        ':section_id' => $sectionId,
        ':note_id' => (int) $target['note_id'],
    ]);
}

function note_option_label(array $note): string
{
    $article = trim((string) ($note['article_title'] ?? 'Artigo sem titulo'));
    $year = trim((string) ($note['year'] ?? ''));
    $teaserSource = trim((string) ($note['comment'] ?? '')) !== ''
        ? (string) $note['comment']
        : (string) ($note['quote_text'] ?? '');
    $teaser = text_teaser($teaserSource, 90);
    $prefix = '#' . (int) $note['id'] . ' - ' . $article . ($year !== '' ? ' (' . $year . ')' : '');

    return $teaser !== '' ? $prefix . ' - ' . $teaser : $prefix;
}

$project = fetch_project($pdo, $projectId, $userId);
if ($project === null) {
    http_response_code(404);
    exit('Projeto nao encontrado.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'update_project') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            if ($title === '') {
                throw new RuntimeException('Informe um nome para o projeto.');
            }

            $stmt = $pdo->prepare('
                UPDATE projects
                SET title = :title, description = :description, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ');
            $stmt->execute([':title' => $title, ':description' => $description, ':id' => $projectId]);
            set_project_flash('Projeto atualizado.');
            redirect_to_project($projectId);
        }

        if ($action === 'delete_project') {
            $stmt = $pdo->prepare('DELETE FROM projects WHERE id = :id');
            $stmt->execute([':id' => $projectId]);
            set_project_flash('Projeto excluido.');
            header('Location: projects.php');
            exit;
        }

        if ($action === 'link_project_tag') {
            $tagId = (int) ($_POST['tag_id'] ?? 0);
            if ($tagId <= 0) {
                throw new RuntimeException('Selecione uma tag.');
            }

            $tagStmt = $pdo->prepare('SELECT id FROM tags WHERE id = :id LIMIT 1');
            $tagStmt->execute([':id' => $tagId]);
            if (!$tagStmt->fetchColumn()) {
                throw new RuntimeException('Tag nao encontrada.');
            }

            $stmt = $pdo->prepare('
                INSERT INTO project_tags (project_id, tag_id)
                VALUES (:project_id, :tag_id)
                ON CONFLICT (project_id, tag_id) DO NOTHING
            ');
            $stmt->execute([':project_id' => $projectId, ':tag_id' => $tagId]);
            touch_project($pdo, $projectId);
            set_project_flash('Tag vinculada ao projeto.');
            redirect_to_project($projectId);
        }

        if ($action === 'unlink_project_tag') {
            $tagId = (int) ($_POST['tag_id'] ?? 0);
            if ($tagId <= 0) {
                throw new RuntimeException('Tag invalida.');
            }

            $stmt = $pdo->prepare('DELETE FROM project_tags WHERE project_id = :project_id AND tag_id = :tag_id');
            $stmt->execute([':project_id' => $projectId, ':tag_id' => $tagId]);
            touch_project($pdo, $projectId);
            set_project_flash('Tag removida do projeto.');
            redirect_to_project($projectId);
        }

        if ($action === 'create_section') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $context = trim((string) ($_POST['context'] ?? ''));
            if ($title === '') {
                throw new RuntimeException('Informe um título para a seção.');
            }

            $stmt = $pdo->prepare('
                INSERT INTO project_sections (project_id, title, context, position)
                VALUES (:project_id, :title, :context, :position)
            ');
            $stmt->execute([
                ':project_id' => $projectId,
                ':title' => $title,
                ':context' => $context,
                ':position' => next_section_position($pdo, $projectId),
            ]);
            touch_project($pdo, $projectId);
            set_project_flash('Seção criada.');
            redirect_to_project($projectId);
        }

        if ($action === 'update_section') {
            $sectionId = (int) ($_POST['section_id'] ?? 0);
            $section = fetch_project_section($pdo, $projectId, $sectionId);
            if ($section === null) {
                throw new RuntimeException('Seção não encontrada.');
            }

            $title = trim((string) ($_POST['title'] ?? ''));
            $context = trim((string) ($_POST['context'] ?? ''));
            if ($title === '') {
                throw new RuntimeException('Informe um título para a seção.');
            }

            $stmt = $pdo->prepare('
                UPDATE project_sections
                SET title = :title, context = :context, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND project_id = :project_id
            ');
            $stmt->execute([
                ':title' => $title,
                ':context' => $context,
                ':id' => $sectionId,
                ':project_id' => $projectId,
            ]);
            touch_project($pdo, $projectId);

            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true]);
                exit;
            }

            set_project_flash('Seção atualizada.');
            redirect_to_project($projectId);
        }

        if ($action === 'delete_section') {
            $sectionId = (int) ($_POST['section_id'] ?? 0);
            if (fetch_project_section($pdo, $projectId, $sectionId) === null) {
                throw new RuntimeException('Seção não encontrada.');
            }

            $stmt = $pdo->prepare('DELETE FROM project_sections WHERE id = :id AND project_id = :project_id');
            $stmt->execute([':id' => $sectionId, ':project_id' => $projectId]);
            touch_project($pdo, $projectId);
            set_project_flash('Seção excluída.');
            redirect_to_project($projectId);
        }

        if ($action === 'move_section') {
            $sectionId = (int) ($_POST['section_id'] ?? 0);
            $direction = (string) ($_POST['direction'] ?? '');
            if (!in_array($direction, ['up', 'down'], true)) {
                throw new RuntimeException('Direcao invalida.');
            }

            move_project_section($pdo, $projectId, $sectionId, $direction);
            touch_project($pdo, $projectId);

            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true]);
                exit;
            }

            redirect_to_project($projectId);
        }

        if ($action === 'link_note') {
            $sectionId = (int) ($_POST['section_id'] ?? 0);
            $noteId = (int) ($_POST['note_id'] ?? 0);
            if (fetch_project_section($pdo, $projectId, $sectionId) === null) {
                throw new RuntimeException('Seção não encontrada.');
            }

            $noteStmt = $pdo->prepare('SELECT id FROM article_tag_quotes WHERE id = :id LIMIT 1');
            $noteStmt->execute([':id' => $noteId]);
            if (!$noteStmt->fetchColumn()) {
                throw new RuntimeException('Marcação nao encontrada.');
            }

            $stmt = $pdo->prepare('
                INSERT INTO project_section_notes (section_id, note_id, position)
                VALUES (:section_id, :note_id, :position)
                ON CONFLICT (section_id, note_id) DO NOTHING
            ');
            $stmt->execute([
                ':section_id' => $sectionId,
                ':note_id' => $noteId,
                ':position' => next_note_position($pdo, $sectionId),
            ]);
            touch_project($pdo, $projectId);
            set_project_flash('Marcação vinculada.');
            redirect_to_project($projectId);
        }

        if ($action === 'unlink_note') {
            $sectionId = (int) ($_POST['section_id'] ?? 0);
            $noteId = (int) ($_POST['note_id'] ?? 0);
            if (fetch_project_section($pdo, $projectId, $sectionId) === null) {
                throw new RuntimeException('Seção não encontrada.');
            }

            $stmt = $pdo->prepare('DELETE FROM project_section_notes WHERE section_id = :section_id AND note_id = :note_id');
            $stmt->execute([':section_id' => $sectionId, ':note_id' => $noteId]);
            touch_project($pdo, $projectId);
            set_project_flash('Marcação removida da seção.');
            redirect_to_project($projectId);
        }

        if ($action === 'link_direct_note') {
            $noteId = (int) ($_POST['note_id'] ?? 0);

            // Find or create Geral section
            $sectStmt = $pdo->prepare("SELECT id FROM project_sections WHERE project_id = :project_id AND lower(title) = 'geral' LIMIT 1");
            $sectStmt->execute([':project_id' => $projectId]);
            $sectId = $sectStmt->fetchColumn();

            if ($sectId) {
                $sectionId = (int) $sectId;
            } else {
                $insSect = $pdo->prepare('INSERT INTO project_sections (project_id, title, context, position) VALUES (:project_id, :title, :context, :position)');
                $stmtPos = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM project_sections WHERE project_id = :project_id');
                $stmtPos->execute([':project_id' => $projectId]);
                $pos = (int) $stmtPos->fetchColumn();

                $insSect->execute([
                    ':project_id' => $projectId,
                    ':title' => 'Geral',
                    ':context' => 'Marcações vinculadas diretamente ao projeto.',
                    ':position' => $pos
                ]);
                $sectionId = (int) $pdo->lastInsertId();
            }

            // Verify note exists
            $noteStmt = $pdo->prepare('SELECT id FROM article_tag_quotes WHERE id = :id LIMIT 1');
            $noteStmt->execute([':id' => $noteId]);
            if (!$noteStmt->fetchColumn()) {
                throw new RuntimeException('Marcação nao encontrada.');
            }

            // Link note to section
            $stmtNotePos = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM project_section_notes WHERE section_id = :section_id');
            $stmtNotePos->execute([':section_id' => $sectionId]);
            $notePos = (int) $stmtNotePos->fetchColumn();

            $stmt = $pdo->prepare('
                INSERT INTO project_section_notes (section_id, note_id, position)
                VALUES (:section_id, :note_id, :position)
                ON CONFLICT (section_id, note_id) DO NOTHING
            ');
            $stmt->execute([
                ':section_id' => $sectionId,
                ':note_id' => $noteId,
                ':position' => $notePos,
            ]);

            touch_project($pdo, $projectId);
            set_project_flash('Marcação vinculada diretamente ao projeto.');
            redirect_to_project($projectId);
        }

        if ($action === 'move_note') {
            $sectionId = (int) ($_POST['section_id'] ?? 0);
            $noteId = (int) ($_POST['note_id'] ?? 0);
            $direction = (string) ($_POST['direction'] ?? '');
            if (!in_array($direction, ['up', 'down'], true)) {
                throw new RuntimeException('Direção inválida.');
            }
            if (fetch_project_section($pdo, $projectId, $sectionId) === null) {
                throw new RuntimeException('Seção não encontrada.');
            }

            move_section_note($pdo, $sectionId, $noteId, $direction);
            touch_project($pdo, $projectId);

            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true]);
                exit;
            }

            redirect_to_project($projectId);
        }

        if ($action === 'update_note') {
            if (!can_edit_content()) {
                throw new RuntimeException('Sem permissao para editar marcações.');
            }

            $noteId = (int) ($_POST['note_id'] ?? 0);
            $quoteText = trim((string) ($_POST['quote_text'] ?? ''));
            $comment = trim((string) ($_POST['comment'] ?? ''));

            if ($noteId <= 0) {
                throw new RuntimeException('Marcação invalida.');
            }

            $exists = $pdo->prepare('SELECT id FROM article_tag_quotes WHERE id = :id');
            $exists->execute([':id' => $noteId]);
            if (!$exists->fetchColumn()) {
                throw new RuntimeException('Marcação nao encontrada.');
            }

            $update = $pdo->prepare('UPDATE article_tag_quotes SET quote_text = :quote_text, comment = :comment, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $update->execute([
                ':quote_text' => $quoteText,
                ':comment' => $comment,
                ':id' => $noteId,
            ]);

            touch_project($pdo, $projectId);

            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'note' => [
                        'id' => $noteId,
                        'quote_text' => $quoteText,
                        'comment' => $comment,
                    ]
                ]);
                exit;
            }

            set_project_flash('Marcação atualizada.');
            redirect_to_project($projectId);
        }

        if ($action === 'move_note_to_section') {
            $fromSectionId = (int) ($_POST['from_section_id'] ?? 0);
            $toSectionId = (int) ($_POST['to_section_id'] ?? 0);
            $noteId = (int) ($_POST['note_id'] ?? 0);

            if ($fromSectionId === $toSectionId) {
                throw new RuntimeException('As seções de origem e destino são iguais.');
            }

            if (fetch_project_section($pdo, $projectId, $fromSectionId) === null) {
                throw new RuntimeException('Seção de origem não encontrada.');
            }

            if (fetch_project_section($pdo, $projectId, $toSectionId) === null) {
                throw new RuntimeException('Seção de destino não encontrada.');
            }

            // Check if note is linked to the from section
            $checkStmt = $pdo->prepare('SELECT 1 FROM project_section_notes WHERE section_id = :section_id AND note_id = :note_id');
            $checkStmt->execute([':section_id' => $fromSectionId, ':note_id' => $noteId]);
            if (!$checkStmt->fetchColumn()) {
                throw new RuntimeException('Marcação não encontrada na seção de origem.');
            }

            $pdo->beginTransaction();
            try {
                // Remove from old section
                $delStmt = $pdo->prepare('DELETE FROM project_section_notes WHERE section_id = :section_id AND note_id = :note_id');
                $delStmt->execute([':section_id' => $fromSectionId, ':note_id' => $noteId]);

                // Insert into new section
                $insStmt = $pdo->prepare('
                    INSERT INTO project_section_notes (section_id, note_id, position)
                    VALUES (:section_id, :note_id, :position)
                    ON CONFLICT (section_id, note_id) DO NOTHING
                ');
                $insStmt->execute([
                    ':section_id' => $toSectionId,
                    ':note_id' => $noteId,
                    ':position' => next_note_position($pdo, $toSectionId),
                ]);

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            touch_project($pdo, $projectId);

            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true]);
                exit;
            }

            set_project_flash('Marcação movida de seção.');
            redirect_to_project($projectId);
        }
    } catch (Throwable $e) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
        set_project_flash($e->getMessage(), 'danger');
        redirect_to_project($projectId);
    }
}

$project = fetch_project($pdo, $projectId, $userId);
$flash = take_project_flash();

$projectTagsStmt = $pdo->prepare('
    SELECT t.*
    FROM project_tags pt
    JOIN tags t ON t.id = pt.tag_id
    WHERE pt.project_id = :project_id
    ORDER BY lower(t.category) ASC, lower(t.name) ASC
');
$projectTagsStmt->execute([':project_id' => $projectId]);
$projectTags = $projectTagsStmt->fetchAll() ?: [];
$projectTagIds = [];
foreach ($projectTags as $projectTag) {
    $projectTagIds[(int) $projectTag['id']] = true;
}

$availableProjectTags = $pdo
    ->query('SELECT * FROM tags ORDER BY lower(category) ASC, lower(name) ASC')
    ->fetchAll() ?: [];

$sectionStmt = $pdo->prepare('
    SELECT *
    FROM project_sections
    WHERE project_id = :project_id
    ORDER BY position ASC, id ASC
');
$sectionStmt->execute([':project_id' => $projectId]);
$sections = $sectionStmt->fetchAll() ?: [];

$generalSection = null;
$normalSections = [];
foreach ($sections as $sec) {
    if (strtolower(trim((string)$sec['title'])) === 'geral') {
        $generalSection = $sec;
    } else {
        $normalSections[] = $sec;
    }
}
$generalSectionId = $generalSection ? (int)$generalSection['id'] : 0;

$allNotes = $pdo->query("
    SELECT
        q.id,
        q.quote_text,
        q.comment,
        a.id AS article_id,
        a.title AS article_title,
        a.year,
        COALESCE(string_agg(t.name, ', ' ORDER BY lower(t.name)) FILTER (WHERE t.id IS NOT NULL), '') AS tag_names
    FROM article_tag_quotes q
    JOIN articles a ON a.id = q.article_id
    LEFT JOIN article_quote_tags qt ON qt.quote_id = q.id
    LEFT JOIN tags t ON t.id = qt.tag_id
    GROUP BY q.id, q.quote_text, q.comment, a.id, a.title, a.year
    ORDER BY lower(a.title) ASC, q.id DESC
")->fetchAll() ?: [];

$allNotesById = [];
foreach ($allNotes as $note) {
    $allNotesById[(int) $note['id']] = $note;
}

$linkedStmt = $pdo->prepare("
    SELECT
        psn.section_id,
        psn.note_id,
        psn.position,
        q.quote_text,
        q.comment,
        a.id AS article_id,
        a.title AS article_title,
        a.year,
        COALESCE(
            json_agg(
                json_build_object('id', t.id, 'name', t.name, 'category', t.category)
                ORDER BY lower(t.name)
            ) FILTER (WHERE t.id IS NOT NULL),
            '[]'::json
        ) AS tags_json
    FROM project_section_notes psn
    JOIN project_sections s ON s.id = psn.section_id
    JOIN article_tag_quotes q ON q.id = psn.note_id
    JOIN articles a ON a.id = q.article_id
    LEFT JOIN article_quote_tags qt ON qt.quote_id = q.id
    LEFT JOIN tags t ON t.id = qt.tag_id
    WHERE s.project_id = :project_id
    GROUP BY psn.section_id, psn.note_id, psn.position, q.quote_text, q.comment, a.id, a.title, a.year
    ORDER BY psn.section_id ASC, psn.position ASC, psn.note_id ASC
");
$linkedStmt->execute([':project_id' => $projectId]);
$notesBySection = [];
$linkedNoteIdsBySection = [];
foreach (($linkedStmt->fetchAll() ?: []) as $note) {
    $sectionId = (int) $note['section_id'];
    $noteId = (int) $note['note_id'];
    $notesBySection[$sectionId][] = $note;
    $linkedNoteIdsBySection[$sectionId][$noteId] = true;
}
$generalNotes = $generalSectionId > 0 ? ($notesBySection[$generalSectionId] ?? []) : [];
$linkedInGeneral = $generalSectionId > 0 ? ($linkedNoteIdsBySection[$generalSectionId] ?? []) : [];
?>
<!doctype html>
<html lang="pt-br" data-module="fichario">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h((string) ($project['title'] ?? 'Projeto')) ?> - Projetos - Fichario Academico</title>
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="assets/app.css?v=20260629-vanilla" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=20260629-vanilla" rel="stylesheet">
    <script src="../assets/js/theme-switcher.js?v=20260629-vanilla"></script>
    <style>
        .project-layout {
            display: block;
        }

        .section-context {
            min-height: 9rem;
        }

        .note-meta {
            color: #94a3b8;
            font-size: 0.78rem;
        }


        .icon-button {
            width: 2rem;
            height: 2rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border-radius: 999px;
        }

        /* Media query removed since sidebar layout is single-column block now */
    </style>
</head>
<body>


    <?php render_navbar('projects'); ?>

    <main class="container py-4 main-container">
        <!-- Breadcrumbs -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Fichário</a></li>
                <li class="breadcrumb-item"><a href="projects.php">Projetos</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page"><?= h((string) ($project['title'] ?? 'Projeto')) ?></li>
            </ol>
        </nav>

        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
            <div>
                <h1 class="h3 mb-1 text-white fw-bold"><?= h((string) ($project['title'] ?? 'Projeto')) ?></h1>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary rounded-pill px-4 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#editProjectModal">
                    <i class="bi bi-pencil"></i> Editar projeto
                </button>
                <form method="post" 
                      data-confirm-title="Excluir Projeto" 
                      data-confirm-message="Excluir este projeto e todas as suas seções? Esta ação não pode ser desfeita." 
                      data-confirm-button="Excluir projeto" 
                      data-confirm-variant="danger" 
                      class="m-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_project">
                    <button class="btn btn-outline-danger rounded-pill px-4" type="submit">Excluir projeto</button>
                </form>
            </div>
        </div>

        <?php if ($flash !== null): ?>
            <div class="alert alert-<?= h((string) $flash['type']) ?>" role="alert">
                <?= h((string) $flash['message']) ?>
            </div>
        <?php endif; ?>

        <section class="glass-card p-4 mb-4">
            <!-- Sobre o Projeto -->
            <div class="mb-4">
                <h2 class="h5 text-white fw-bold mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-info-circle text-primary"></i> Sobre o Projeto
                </h2>
                <?php if (trim((string) ($project['description'] ?? '')) !== ''): ?>
                    <div class="text-white-50 small mb-0" style="white-space: pre-wrap; line-height: 1.6; font-size: 0.88rem;"><?= h((string) $project['description']) ?></div>
                <?php else: ?>
                    <div class="text-secondary small mb-0">Sem descrição cadastrada.</div>
                <?php endif; ?>
            </div>

            <hr class="border-secondary border-opacity-25 my-4">

            <!-- Tags do projeto -->
            <div>
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h2 class="h5 text-white fw-bold mb-1">Tags do projeto</h2>
                        <p class="text-secondary mb-0 small">Conceitos gerais que orientam o projeto como um todo.</p>
                    </div>
                </div>

            <?php if ($projectTags === []): ?>
                <div class="p-3 rounded-3 bg-black bg-opacity-25 text-secondary text-center mb-3">
                    Nenhuma tag vinculada ao projeto.
                </div>
            <?php else: ?>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php foreach ($projectTags as $tag): ?>
                        <?php
                            $tagId = (int) $tag['id'];
                            $tagColor = get_tag_colors((string) ($tag['category'] ?? ''));
                        ?>
                        <div class="d-inline-flex align-items-center gap-2 badge border tag-badge"
                             style="background:<?= $tagColor['bg'] ?>; color:<?= $tagColor['text'] ?>; border-color:<?= $tagColor['border'] ?> !important;"
                             <?= tag_tooltip_attrs($tag) ?>>
                            <a class="text-decoration-none" style="color: inherit;" href="tag_view.php?tag_id=<?= $tagId ?>"><?= h((string) $tag['name']) ?></a>
                            <form method="post" class="m-0" data-confirm-message="Remover esta tag do projeto?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="unlink_project_tag">
                                <input type="hidden" name="tag_id" value="<?= $tagId ?>">
                                <button class="btn btn-link btn-sm p-0 border-0 text-danger" type="submit" title="Remover tag" style="line-height: 1;">&times;</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="link_project_tag">
                <div class="col-lg">
                    <label class="form-label" for="project-tag-id">Vincular tag existente</label>
                    <select class="form-select" id="project-tag-id" name="tag_id" required>
                        <option value="">Selecione uma tag...</option>
                        <?php foreach ($availableProjectTags as $tagOption): ?>
                            <?php if (isset($projectTagIds[(int) $tagOption['id']])) continue; ?>
                            <?php
                                $category = trim((string) ($tagOption['category'] ?? ''));
                                $label = ($category !== '' ? '[' . $category . '] ' : '') . (string) $tagOption['name'];
                            ?>
                            <option value="<?= (int) $tagOption['id'] ?>"><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-auto text-end">
                    <button class="btn btn-primary rounded-pill px-4" type="submit">Vincular tag</button>
                </div>
            </form>
            </div>
        </section>

        <div class="project-layout">
            <section class="vstack gap-4">
                <div class="d-flex justify-content-end align-items-center gap-2 mb-1">
                    <button class="btn btn-sm btn-outline-secondary rounded-pill text-white px-3" type="button" onclick="toggleAllSections(this)">
                        Recolher todas as seções
                    </button>
                    <button class="btn btn-primary btn-sm rounded-pill px-3 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#newSectionModal">
                        <i class="bi bi-plus-lg"></i> Nova seção
                    </button>
                </div>
                <!-- Marcações vinculadas diretamente ao projeto -->
                <article class="glass-card p-4" id="section-general">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                        <div>
                            <h2 class="h5 text-white fw-bold mb-1">
                                <button class="btn btn-link p-0 text-white text-decoration-none border-0 d-inline-flex align-items-center gap-2" type="button" onclick="toggleSectionCollapse('general')">
                                    <i class="bi bi-chevron-down section-toggle-icon" id="section-toggle-icon-general"></i>
                                    Marcações vinculadas diretamente ao projeto
                                    <span class="badge bg-secondary bg-opacity-25 text-secondary fs-6 fw-normal ms-2 rounded-pill"><?= count($generalNotes) ?> marcação(ões)</span>
                                </button>
                            </h2>
                            <p class="text-secondary mb-0 small ms-4">Marcações associadas ao projeto como um todo, sem seção específica.</p>
                        </div>
                        <?php if ($generalNotes !== []): ?>
                            <div class="d-flex align-items-center gap-3">
                                <button class="btn btn-sm btn-link p-0 text-secondary text-decoration-none" style="font-size: 0.72rem;" type="button" onclick="toggleAllMarkings(this, '.glass-card')">
                                    Expandir todas
                                </button>
                                <span class="text-secondary small"><?= count($generalNotes) ?> marcação(ões)</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div id="section-body-general" class="section-body">
                        <?php if ($generalNotes === []): ?>
                        <div class="p-3 rounded-3 bg-black bg-opacity-25 text-secondary text-center mb-3">
                            Nenhuma marcação vinculada diretamente ao projeto.
                        </div>
                    <?php else: ?>
                        <div class="vstack gap-2 mb-3">
                            <?php foreach ($generalNotes as $note): ?>
                                <?php
                                    $noteId = (int) $note['note_id'];
                                    $quoteText = trim((string) ($note['quote_text'] ?? ''));
                                    $comment = trim((string) ($note['comment'] ?? ''));
                                ?>
                                <div id="note-card-<?= $generalSectionId ?>-<?= $noteId ?>" class="note-card p-2 rounded-3 border border-secondary border-opacity-25 bg-black bg-opacity-25 small text-white-50">
                                    <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                        <div class="min-w-0">
                                            <a class="text-white fw-semibold text-decoration-none" href="view.php?id=<?= (int) $note['article_id'] ?>">
                                                <?= h((string) $note['article_title']) ?>
                                            </a>
                                            <?php if (trim((string) ($note['year'] ?? '')) !== ''): ?>
                                                <span class="text-secondary small ms-1">(<?= h((string) $note['year']) ?>)</span>
                                            <?php endif; ?>
                                            <div class="note-meta d-flex flex-wrap align-items-center gap-2 mt-1">
                                                <span class="text-secondary">Marcação #<?= $noteId ?></span>
                                                <?php
                                                    $noteTags = [];
                                                    if (isset($note['tags_json'])) {
                                                        $noteTags = is_string($note['tags_json']) ? (json_decode($note['tags_json'], true) ?: []) : $note['tags_json'];
                                                    }
                                                ?>
                                                <?php if ($noteTags !== []): ?>
                                                    <?php foreach ($noteTags as $noteTag): ?>
                                                        <?php
                                                            if (empty($noteTag['id'])) continue;
                                                            $nColor = get_tag_colors($noteTag['category'] ?? '');
                                                        ?>
                                                        <a href="tag_view.php?tag_id=<?= (int) $noteTag['id'] ?>"
                                                           class="badge border tag-badge text-decoration-none"
                                                           style="background:<?= $nColor['bg'] ?>; color:<?= $nColor['text'] ?>; border-color:<?= $nColor['border'] ?> !important;"
                                                           <?= tag_tooltip_attrs($noteTag) ?>>
                                                            <?= h($noteTag['name']) ?>
                                                        </a>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2 flex-shrink-0">
                                            <form method="post" class="m-0" data-busy-ignore="1">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="move_note">
                                                <input type="hidden" name="section_id" value="<?= $generalSectionId ?>">
                                                <input type="hidden" name="note_id" value="<?= $noteId ?>">
                                                <input type="hidden" name="direction" value="up">
                                                <button class="btn btn-outline-secondary icon-button text-white" type="submit" title="Subir marcação">↑</button>
                                            </form>
                                            <form method="post" class="m-0" data-busy-ignore="1">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="move_note">
                                                <input type="hidden" name="section_id" value="<?= $generalSectionId ?>">
                                                <input type="hidden" name="note_id" value="<?= $noteId ?>">
                                                <input type="hidden" name="direction" value="down">
                                                <button class="btn btn-outline-secondary icon-button text-white" type="submit" title="Descer marcação">↓</button>
                                            </form>
                                            <form method="post" class="m-0 d-inline-block">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="move_note_to_section">
                                                <input type="hidden" name="from_section_id" value="<?= $generalSectionId ?>">
                                                <input type="hidden" name="note_id" value="<?= $noteId ?>">
                                                <select name="to_section_id" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                                    <option value="" selected disabled>Mover para...</option>
                                                    <?php foreach ($sections as $targetSection): ?>
                                                        <?php if ((int)$targetSection['id'] !== $generalSectionId): ?>
                                                            <option value="<?= (int)$targetSection['id'] ?>"><?= h((string)$targetSection['title']) ?></option>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                            <button class="btn btn-outline-secondary icon-button text-white" 
                                                    type="button" 
                                                    title="Editar marcação"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editNoteModal" 
                                                    data-note-id="<?= $noteId ?>" 
                                                    data-quote-text="<?= h($quoteText) ?>" 
                                                    data-comment="<?= h($comment) ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="post" class="m-0" data-confirm-message="Remover esta marcação do projeto? A marcação original sera preservada.">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="unlink_note">
                                                <input type="hidden" name="section_id" value="<?= $generalSectionId ?>">
                                                <input type="hidden" name="note_id" value="<?= $noteId ?>">
                                                <button class="btn btn-outline-danger icon-button" type="submit" title="Remover marcação">&times;</button>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="note-content">
                                        <?php if ($quoteText !== ''): ?>
                                            <div class="marking-preview marking-preview-quote mb-2">
                                                <span class="note-teaser-label">Citação</span>
                                                <div class="quote-box expandable-text collapsed" onclick="toggleExpandableText(this)" title="Clique para expandir/recolher"><?= h($quoteText) ?></div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($comment !== ''): ?>
                                            <div class="marking-preview marking-preview-comment">
                                                <span class="note-teaser-label">Observação</span>
                                                <div class="observation-box expandable-text collapsed" onclick="toggleExpandableText(this)" title="Clique para expandir/recolher"><?= h($comment) ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    </div>
                </article>

                <?php if ($normalSections === []): ?>
                    <div class="glass-card p-5 text-center text-secondary">
                        <p class="mb-0">Nenhuma seção criada. Comece pelo painel lateral.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($normalSections as $index => $section): ?>
                        <?php
                            $sectionId = (int) $section['id'];
                            $sectionNotes = $notesBySection[$sectionId] ?? [];
                            $linkedInSection = $linkedNoteIdsBySection[$sectionId] ?? [];
                        ?>
                        <article class="glass-card p-4" id="section-<?= $sectionId ?>">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                                <div>
                                    <div class="text-secondary small fw-semibold mb-1">Seção <?= $index + 1 ?></div>
                                    <h2 class="h4 text-white fw-bold mb-0">
                                        <button class="btn btn-link p-0 text-white text-decoration-none border-0 d-inline-flex align-items-center gap-2" type="button" onclick="toggleSectionCollapse(<?= $sectionId ?>)">
                                            <i class="bi bi-chevron-down section-toggle-icon" id="section-toggle-icon-<?= $sectionId ?>"></i>
                                            <?= h((string) $section['title']) ?>
                                            <span class="badge bg-secondary bg-opacity-25 text-secondary fs-6 fw-normal ms-2 rounded-pill"><?= count($sectionNotes) ?> marcação(ões)</span>
                                        </button>
                                    </h2>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <form method="post" data-busy-ignore="1">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="move_section">
                                        <input type="hidden" name="section_id" value="<?= $sectionId ?>">
                                        <input type="hidden" name="direction" value="up">
                                        <button class="btn btn-outline-secondary icon-button text-white" type="submit" title="Subir seção">↑</button>
                                    </form>
                                    <form method="post" data-busy-ignore="1">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="move_section">
                                        <input type="hidden" name="section_id" value="<?= $sectionId ?>">
                                        <input type="hidden" name="direction" value="down">
                                        <button class="btn btn-outline-secondary icon-button text-white" type="submit" title="Descer seção">↓</button>
                                    </form>
                                    <button class="btn btn-outline-secondary icon-button text-white" 
                                            type="button" 
                                            title="Editar seção" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editSectionModal" 
                                            data-section-id="<?= $sectionId ?>" 
                                            data-section-title="<?= h((string) $section['title']) ?>" 
                                            data-section-context="<?= h((string) ($section['context'] ?? '')) ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="post" data-confirm-message="Excluir esta seção? As marcações serão apenas desvinculadas do projeto.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_section">
                                        <input type="hidden" name="section_id" value="<?= $sectionId ?>">
                                        <button class="btn btn-outline-danger icon-button" type="submit" title="Excluir seção">&times;</button>
                                    </form>
                                </div>
                            </div>

                            <div id="section-body-<?= $sectionId ?>" class="section-body">
                                <?php if (trim((string) ($section['context'] ?? '')) !== ''): ?>
                                <p class="text-white-50 small mb-4" style="white-space: pre-wrap; line-height: 1.6; font-size: 0.88rem;"><?= h((string) $section['context']) ?></p>
                            <?php endif; ?>

                            <div class="border-top border-secondary border-opacity-25 pt-4">
                                 <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                                     <h3 class="h5 text-white fw-bold mb-0">Marcações vinculadas</h3>
                                     <div class="d-flex align-items-center gap-3">
                                         <button class="btn btn-sm btn-link p-0 text-secondary text-decoration-none" style="font-size: 0.72rem;" type="button" onclick="toggleAllMarkings(this, '.border-top')">
                                             Expandir todas
                                         </button>
                                         <span class="text-secondary small"><?= count($sectionNotes) ?> marcação(ões)</span>
                                     </div>
                                 </div>

                                <?php if ($sectionNotes === []): ?>
                                    <div class="p-3 rounded-3 bg-black bg-opacity-25 text-secondary text-center mb-3">
                                        Nenhuma marcação vinculada a esta seção.
                                    </div>
                                <?php else: ?>
                                    <div class="vstack gap-2 mb-3">
                                        <?php foreach ($sectionNotes as $note): ?>
                                            <?php
                                                $noteId = (int) $note['note_id'];
                                                $quoteText = trim((string) ($note['quote_text'] ?? ''));
                                                $comment = trim((string) ($note['comment'] ?? ''));
                                            ?>
                                            <div id="note-card-<?= $sectionId ?>-<?= $noteId ?>" class="note-card p-2 rounded-3 border border-secondary border-opacity-25 bg-black bg-opacity-25 small text-white-50">
                                                <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                                    <div class="min-w-0">
                                                        <a class="text-white fw-semibold text-decoration-none" href="view.php?id=<?= (int) $note['article_id'] ?>">
                                                            <?= h((string) $note['article_title']) ?>
                                                        </a>
                                                        <?php if (trim((string) ($note['year'] ?? '')) !== ''): ?>
                                                            <span class="text-secondary small ms-1">(<?= h((string) $note['year']) ?>)</span>
                                                        <?php endif; ?>
                                                        <div class="note-meta d-flex flex-wrap align-items-center gap-2 mt-1">
                                                            <span class="text-secondary">Marcação #<?= $noteId ?></span>
                                                            <?php
                                                                $noteTags = [];
                                                                if (isset($note['tags_json'])) {
                                                                    $noteTags = is_string($note['tags_json']) ? (json_decode($note['tags_json'], true) ?: []) : $note['tags_json'];
                                                                }
                                                            ?>
                                                            <?php if ($noteTags !== []): ?>
                                                                <?php foreach ($noteTags as $noteTag): ?>
                                                                    <?php
                                                                        if (empty($noteTag['id'])) continue;
                                                                        $nColor = get_tag_colors($noteTag['category'] ?? '');
                                                                    ?>
                                                                    <a href="tag_view.php?tag_id=<?= (int) $noteTag['id'] ?>"
                                                                       class="badge border tag-badge text-decoration-none"
                                                                       style="background:<?= $nColor['bg'] ?>; color:<?= $nColor['text'] ?>; border-color:<?= $nColor['border'] ?> !important;"
                                                                       <?= tag_tooltip_attrs($noteTag) ?>>
                                                                        <?= h($noteTag['name']) ?>
                                                                    </a>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex flex-wrap gap-2 flex-shrink-0">
                                                        <form method="post" class="m-0" data-busy-ignore="1">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="move_note">
                                                            <input type="hidden" name="section_id" value="<?= $sectionId ?>">
                                                            <input type="hidden" name="note_id" value="<?= $noteId ?>">
                                                            <input type="hidden" name="direction" value="up">
                                                            <button class="btn btn-outline-secondary icon-button text-white" type="submit" title="Subir marcação">↑</button>
                                                        </form>
                                                        <form method="post" class="m-0" data-busy-ignore="1">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="move_note">
                                                            <input type="hidden" name="section_id" value="<?= $sectionId ?>">
                                                            <input type="hidden" name="note_id" value="<?= $noteId ?>">
                                                            <input type="hidden" name="direction" value="down">
                                                            <button class="btn btn-outline-secondary icon-button text-white" type="submit" title="Descer marcação">↓</button>
                                                        </form>
                                                        <form method="post" class="m-0 d-inline-block">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="move_note_to_section">
                                                            <input type="hidden" name="from_section_id" value="<?= $sectionId ?>">
                                                            <input type="hidden" name="note_id" value="<?= $noteId ?>">
                                                            <select name="to_section_id" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                                                <option value="" selected disabled>Mover para...</option>
                                                                <?php foreach ($sections as $targetSection): ?>
                                                                    <?php if ((int)$targetSection['id'] !== $sectionId): ?>
                                                                        <option value="<?= (int)$targetSection['id'] ?>"><?= h((string)$targetSection['title']) ?></option>
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </form>
                                                        <button class="btn btn-outline-secondary icon-button text-white" 
                                                                type="button" 
                                                                title="Editar marcação"
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editNoteModal" 
                                                                data-note-id="<?= $noteId ?>" 
                                                                data-quote-text="<?= h($quoteText) ?>" 
                                                                data-comment="<?= h($comment) ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <form method="post" class="m-0" data-confirm-message="Remover esta marcação da seção? A marcação original será preservada.">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="unlink_note">
                                                            <input type="hidden" name="section_id" value="<?= $sectionId ?>">
                                                            <input type="hidden" name="note_id" value="<?= $noteId ?>">
                                                            <button class="btn btn-outline-danger icon-button" type="submit" title="Remover marcação">&times;</button>
                                                        </form>
                                                    </div>
                                                </div>

                                                <div class="note-content">
                                                    <?php if ($quoteText !== ''): ?>
                                                        <div class="marking-preview marking-preview-quote mb-2">
                                                            <span class="note-teaser-label">Citação</span>
                                                            <div class="quote-box expandable-text collapsed" onclick="toggleExpandableText(this)" title="Clique para expandir/recolher"><?= h($quoteText) ?></div>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($comment !== ''): ?>
                                                        <div class="marking-preview marking-preview-comment">
                                                            <span class="note-teaser-label">Observação</span>
                                                            <div class="observation-box expandable-text collapsed" onclick="toggleExpandableText(this)" title="Clique para expandir/recolher"><?= h($comment) ?></div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

        </div>
    </main>

    <!-- Modal: Editar Projeto -->
    <div class="modal fade" id="editProjectModal" tabindex="-1" aria-labelledby="editProjectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content glass-card">
                <div class="modal-header border-secondary border-opacity-25">
                    <h5 class="modal-title text-white fw-bold" id="editProjectModalLabel">Editar Dados do Projeto</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_project">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="project-modal-title">Nome</label>
                            <input class="form-control" id="project-modal-title" name="title" value="<?= h((string) ($project['title'] ?? '')) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="project-modal-description">Descrição</label>
                            <textarea class="form-control" id="project-modal-description" name="description" rows="5"><?= h((string) ($project['description'] ?? '')) ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary border-opacity-25">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">Salvar alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Nova Seção -->
    <div class="modal fade" id="newSectionModal" tabindex="-1" aria-labelledby="newSectionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content glass-card">
                <div class="modal-header border-secondary border-opacity-25">
                    <h5 class="modal-title text-white fw-bold" id="newSectionModalLabel">Nova Seção</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_section">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="new-section-modal-title">Título</label>
                            <input class="form-control" id="new-section-modal-title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="new-section-modal-context">Contexto</label>
                            <textarea class="form-control" id="new-section-modal-context" name="context" rows="6" placeholder="Texto longo de contexto da seção..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary border-opacity-25">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">Criar seção</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Editar Seção -->
    <div class="modal fade" id="editSectionModal" tabindex="-1" aria-labelledby="editSectionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content glass-card">
                <div class="modal-header border-secondary border-opacity-25">
                    <h5 class="modal-title text-white fw-bold" id="editSectionModalLabel">Editar Seção</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="post" data-busy-ignore="1">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_section">
                    <input type="hidden" name="section_id" id="edit-section-id" value="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="edit-section-title">Título</label>
                            <input class="form-control" id="edit-section-title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="edit-section-context">Contexto</label>
                            <textarea class="form-control" id="edit-section-context" name="context" rows="6" placeholder="Contexto longo desta seção..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary border-opacity-25">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">Salvar alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Editar Marcação -->
    <div class="modal fade" id="editNoteModal" tabindex="-1" aria-labelledby="editNoteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content glass-card">
                <div class="modal-header border-secondary border-opacity-25">
                    <h5 class="modal-title text-white fw-bold" id="editNoteModalLabel">Editar Marcação</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="post" id="editNoteForm" data-busy-ignore="1">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_note">
                    <input type="hidden" name="note_id" id="edit-note-id" value="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label text-white-50" for="edit-note-quote">Citação</label>
                            <textarea class="form-control" id="edit-note-quote" name="quote_text" rows="5" placeholder="Texto da citação..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white-50" for="edit-note-comment">Observação</label>
                            <textarea class="form-control" id="edit-note-comment" name="comment" rows="5" placeholder="Observações/Comentários..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary border-opacity-25">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">Salvar alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script src="assets/app.js?v=20260625e"></script>
    <script>
        const editSectionModal = document.getElementById('editSectionModal');
        if (editSectionModal) {
            editSectionModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const sectionId = button.getAttribute('data-section-id');
                const sectionTitle = button.getAttribute('data-section-title');
                const sectionContext = button.getAttribute('data-section-context');

                const modalIdInput = editSectionModal.querySelector('#edit-section-id');
                const modalTitleInput = editSectionModal.querySelector('#edit-section-title');
                const modalContextInput = editSectionModal.querySelector('#edit-section-context');

                modalIdInput.value = sectionId;
                modalTitleInput.value = sectionTitle;
                modalContextInput.value = sectionContext;
            });
        }

        const editNoteModal = document.getElementById('editNoteModal');
        if (editNoteModal) {
            editNoteModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const noteId = button.getAttribute('data-note-id');
                const quoteText = button.getAttribute('data-quote-text');
                const comment = button.getAttribute('data-comment');

                const modalIdInput = editNoteModal.querySelector('#edit-note-id');
                const modalQuoteInput = editNoteModal.querySelector('#edit-note-quote');
                const modalCommentInput = editNoteModal.querySelector('#edit-note-comment');

                modalIdInput.value = noteId;
                modalQuoteInput.value = quoteText || '';
                modalCommentInput.value = comment || '';
            });
        }
    </script>
</body>
</html>
