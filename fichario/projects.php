<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_login();

$pdo = db();
$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$errors = [];
$flash = $_SESSION['project_flash'] ?? null;
unset($_SESSION['project_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_project') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($title === '') {
            $errors[] = 'Informe um nome para o projeto.';
        }

        if ($errors === []) {
            $stmt = $pdo->prepare('
                INSERT INTO projects (owner_user_id, title, description)
                VALUES (:owner_user_id, :title, :description)
            ');
            $stmt->execute([
                ':owner_user_id' => $userId,
                ':title' => $title,
                ':description' => $description,
            ]);

            header('Location: project.php?id=' . (int) $pdo->lastInsertId());
            exit;
        }
    }
}

$stmt = $pdo->prepare('
    SELECT
        p.*,
        COUNT(DISTINCT s.id) AS section_count,
        COUNT(psn.note_id) AS note_count,
        COUNT(DISTINCT q.article_id) AS article_count
    FROM projects p
    LEFT JOIN project_sections s ON s.project_id = p.id
    LEFT JOIN project_section_notes psn ON psn.section_id = s.id
    LEFT JOIN article_tag_quotes q ON q.id = psn.note_id
    WHERE p.owner_user_id = :owner_user_id
    GROUP BY p.id
    ORDER BY p.updated_at DESC, p.id DESC
');
$stmt->execute([':owner_user_id' => $userId]);
$projects = $stmt->fetchAll() ?: [];
?>
<!doctype html>
<html lang="pt-br" data-module="fichario">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Projetos - Fichario Academico</title>
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="assets/app.css?v=20260629-vanilla" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=20260629-vanilla" rel="stylesheet">
    <script src="../assets/js/theme-switcher.js?v=20260629-vanilla"></script>
    <style>
        .project-card-link {
            color: inherit;
            text-decoration: none;
        }

        .project-card-link:hover .project-title {
            color: #93c5fd;
        }

        .project-title {
            transition: color 0.2s;
        }

        .project-metric {
            color: #cbd5e1;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 999px;
            padding: 0.28rem 0.7rem;
            font-size: 0.82rem;
            white-space: nowrap;
        }
    </style>
</head>
<body>


    <?php render_navbar('projects'); ?>

    <main class="container py-4 main-container">
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Fichário</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page">Projetos</li>
            </ol>
        </nav>

        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
            <div>
                <h1 class="h3 mb-1 text-white fw-bold">Projetos</h1>
                <p class="text-secondary mb-0">Organize notas de fichamento em seções com contexto próprio.</p>
            </div>
        </div>

        <?php if ($errors !== []): ?>
            <div class="alert alert-danger" role="alert">
                <?= h(implode(' ', $errors)) ?>
            </div>
        <?php endif; ?>

        <?php if (is_array($flash)): ?>
            <div class="alert alert-<?= h((string) ($flash['type'] ?? 'success')) ?>" role="alert">
                <?= h((string) ($flash['message'] ?? '')) ?>
            </div>
        <?php endif; ?>

        <section class="glass-card p-4 mb-4">
            <h2 class="h5 text-white fw-bold mb-3">Novo projeto</h2>
            <form method="post" class="row g-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_project">
                <div class="col-md-5">
                    <label class="form-label" for="title">Nome</label>
                    <input class="form-control" id="title" name="title" maxlength="180" required>
                </div>
                <div class="col-md-7">
                    <label class="form-label" for="description">Descricao</label>
                    <input class="form-control" id="description" name="description" maxlength="500" placeholder="Opcional">
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button class="btn btn-primary rounded-pill px-4" type="submit">Criar projeto</button>
                </div>
            </form>
        </section>

        <section>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <h2 class="h5 mb-0 text-white fw-bold">Meus projetos</h2>
                <span class="text-secondary"><?= count($projects) ?> projeto(s)</span>
            </div>

            <?php if ($projects === []): ?>
                <div class="glass-card p-5 text-center text-secondary">
                    <p class="mb-0">Nenhum projeto criado ainda.</p>
                </div>
            <?php else: ?>
                <div class="vstack gap-3">
                    <?php foreach ($projects as $project): ?>
                        <a class="project-card-link" href="project.php?id=<?= (int) $project['id'] ?>">
                            <article class="glass-card p-4">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                                    <div class="flex-grow-1">
                                        <h3 class="h5 project-title text-white fw-bold mb-2"><?= h($project['title']) ?></h3>
                                        <?php if (trim((string) ($project['description'] ?? '')) !== ''): ?>
                                            <p class="text-white-50 mb-0"><?= h(text_teaser((string) $project['description'], 220)) ?></p>
                                        <?php else: ?>
                                            <p class="text-secondary mb-0">Sem descricao.</p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="project-metric"><?= (int) $project['section_count'] ?> seções</span>
                                        <span class="project-metric"><?= (int) $project['article_count'] ?> artigos</span>
                                        <span class="project-metric"><?= (int) $project['note_count'] ?> notas</span>
                                    </div>
                                </div>
                            </article>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script src="assets/app.js?v=20260608-projects"></script>
</body>
</html>
