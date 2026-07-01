<?php
declare(strict_types=1);

require __DIR__ . '/../../fichario/bootstrap.php';
require __DIR__ . '/../../acesso/src/documentation.php';
require_admin();

$projectRoot = dirname(__DIR__, 2);
$moduleRoot = $projectRoot . '/fichario';
$docs = platform_docs_scan($projectRoot, [
    [
        'path' => $moduleRoot,
        'module' => 'Fichario',
        'prefix' => '',
    ],
]);

if ($docs === []) {
    http_response_code(500);
    exit('Nenhum documento Markdown interno encontrado para o Fichario.');
}

$selected = (string) ($_GET['doc'] ?? $_POST['doc'] ?? array_key_first($docs));
if (!isset($docs[$selected])) {
    $selected = (string) array_key_first($docs);
}

$categoryFilter = (string) ($_GET['categoria'] ?? 'Todos');
$categories = array_values(array_unique(array_map(static fn (array $doc): string => $doc['category'], $docs)));
if (!in_array($categoryFilter, $categories, true)) {
    $categoryFilter = 'Todos';
}

$matchesFilter = static fn (array $item): bool => $categoryFilter === 'Todos' || $item['category'] === $categoryFilter;
if (!$matchesFilter($docs[$selected])) {
    foreach ($docs as $key => $item) {
        if ($matchesFilter($item)) {
            $selected = $key;
            break;
        }
    }
}

$notice = '';
$errors = [];
$doc = $docs[$selected];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $content = str_replace(["\r\n", "\r"], "\n", (string) ($_POST['content'] ?? ''));
    $target = realpath($doc['path']);
    $moduleRootReal = realpath($moduleRoot);

    $isInternal = $target !== false
        && $moduleRootReal !== false
        && str_starts_with(platform_docs_normalize_path($target), rtrim(platform_docs_normalize_path($moduleRootReal), '/') . '/')
        && !str_contains(platform_docs_normalize_path($target), '/public_html/');

    if (!$isInternal || !is_file((string) $target) || !is_writable((string) $target)) {
        $errors[] = 'Documento inexistente, publico ou sem permissao de escrita.';
    } elseif (file_put_contents((string) $target, $content) === false) {
        $errors[] = 'Nao foi possivel salvar o documento.';
    } else {
        $notice = 'Documento salvo.';
    }
}

$content = is_file($doc['path']) ? (string) file_get_contents($doc['path']) : '';
$filteredDocs = array_filter($docs, $matchesFilter);
?>
<!doctype html>
<html lang="pt-br" data-module="fichario">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Documentacao - Fichario Academico</title>
    <link rel="icon" type="image/png" href="../assets/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="assets/app.css?v=20260629-vanilla" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="../assets/js/theme-switcher.js?v=20260629-vanilla"></script>
    <link href="../assets/css/style.css?v=20260629-vanilla" rel="stylesheet">
</head>
<body>
    <?php render_admin_navbar('docs'); ?>
    <main class="main-container py-4">
        <header class="page-header mb-4">
            <div>
                <h1 class="h2 mb-2">Documentacao</h1>
                <p class="text-secondary mb-0">Markdowns internos do Fichario para leitura e edicao controlada.</p>
            </div>
            <a class="btn btn-outline-secondary" href="admin.php">Voltar ao painel</a>
        </header>

        <?php if ($notice !== ''): ?>
            <div class="alert alert-success"><?= h($notice) ?></div>
        <?php endif; ?>
        <?php if ($errors !== []): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <aside class="col-lg-3">
                <div class="card p-3">
                    <h2 class="h6 text-body fw-bold mb-3">Tipos</h2>
                    <div class="nav nav-pills flex-column gap-2 mb-4">
                        <a class="nav-link <?= $categoryFilter === 'Todos' ? 'active' : '' ?>" href="admin_docs.php?doc=<?= h($selected) ?>&categoria=Todos">Todos</a>
                        <?php foreach ($categories as $category): ?>
                            <a class="nav-link <?= $categoryFilter === $category ? 'active' : '' ?>" href="admin_docs.php?doc=<?= h($selected) ?>&categoria=<?= h(rawurlencode($category)) ?>"><?= h($category) ?></a>
                        <?php endforeach; ?>
                    </div>

                    <h2 class="h6 text-body fw-bold mb-3">Arquivos</h2>
                    <div class="vstack gap-2">
                        <?php foreach ($filteredDocs as $key => $item): ?>
                            <a class="btn text-start rounded-3 <?= $selected === $key ? 'btn-primary' : 'btn-outline-secondary text-body' ?>" href="admin_docs.php?doc=<?= h($key) ?>&categoria=<?= h(rawurlencode($categoryFilter)) ?>">
                                <span class="fw-semibold d-block"><?= h($item['title']) ?></span>
                                <span class="small opacity-75"><?= h($item['relative']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </aside>

            <section class="col-lg-9">
                <form method="post" class="card p-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="doc" value="<?= h($selected) ?>">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-start mb-3">
                        <div>
                            <span class="badge text-bg-primary mb-2"><?= h($doc['category']) ?></span>
                            <h2 class="h5 text-body fw-bold mb-1"><?= h($doc['title']) ?></h2>
                            <p class="text-secondary small mb-0"><?= h($doc['relative']) ?></p>
                        </div>
                        <div class="d-flex gap-2 doc-actions">
                            <button type="button" class="btn btn-outline-primary" id="btn-doc-edit" title="Editar Markdown">
                                <i class="bi bi-pencil me-1" aria-hidden="true"></i>Editar
                            </button>
                            <button type="submit" class="btn btn-primary d-none" id="btn-doc-save" title="Salvar Markdown">
                                <i class="bi bi-save me-1" aria-hidden="true"></i>Salvar
                            </button>
                            <button type="button" class="btn btn-outline-secondary d-none" id="btn-doc-cancel" title="Cancelar edicao">
                                <i class="bi bi-x-lg me-1" aria-hidden="true"></i>Cancelar
                            </button>
                        </div>
                    </div>

                    <article class="markdown-preview" id="doc-preview">
                        <?= platform_docs_render_markdown($content) ?>
                    </article>

                    <div class="d-none" id="doc-editor-wrap">
                        <textarea class="form-control doc-editor" name="content" id="doc-editor" spellcheck="false"><?= h($content) ?></textarea>
                    </div>
                </form>
            </section>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script src="assets/app.js?v=20260603c"></script>
    <script>
        const docPreview = document.getElementById('doc-preview');
        const docEditorWrap = document.getElementById('doc-editor-wrap');
        const docEditor = document.getElementById('doc-editor');
        const btnDocEdit = document.getElementById('btn-doc-edit');
        const btnDocSave = document.getElementById('btn-doc-save');
        const btnDocCancel = document.getElementById('btn-doc-cancel');
        const originalDocContent = docEditor ? docEditor.value : '';

        function setDocEditMode(active) {
            if (!docPreview || !docEditorWrap || !btnDocEdit || !btnDocSave || !btnDocCancel) {
                return;
            }

            docPreview.classList.toggle('d-none', active);
            docEditorWrap.classList.toggle('d-none', !active);
            btnDocEdit.classList.toggle('d-none', active);
            btnDocSave.classList.toggle('d-none', !active);
            btnDocCancel.classList.toggle('d-none', !active);

            if (active && docEditor) {
                setTimeout(() => docEditor.focus(), 80);
            }
        }

        if (btnDocEdit) {
            btnDocEdit.addEventListener('click', () => setDocEditMode(true));
        }
        if (btnDocCancel) {
            btnDocCancel.addEventListener('click', () => {
                if (docEditor) {
                    docEditor.value = originalDocContent;
                }
                setDocEditMode(false);
            });
        }
    </script>
</body>
</html>
