<?php

declare(strict_types=1);

require __DIR__ . '/../../acesso/src/bootstrap.php';
require __DIR__ . '/../../acesso/src/documentation.php';
require_platform_admin();

$projectRoot = dirname(__DIR__, 2);
$docs = platform_docs_scan($projectRoot, [
    [
        'path' => $projectRoot,
        'module' => 'Plataforma',
        'prefix' => '',
    ],
]);

if ($docs === []) {
    http_response_code(500);
    exit('Nenhum documento Markdown interno encontrado.');
}

$selected = (string) ($_GET['doc'] ?? $_POST['doc'] ?? array_key_first($docs));
if (!isset($docs[$selected])) {
    $selected = (string) array_key_first($docs);
}

$moduleFilter = (string) ($_GET['modulo'] ?? 'Todos');
$categoryFilter = (string) ($_GET['categoria'] ?? 'Todos');
$modules = array_values(array_unique(array_map(static fn (array $doc): string => $doc['module'], $docs)));
$categories = array_values(array_unique(array_map(static fn (array $doc): string => $doc['category'], $docs)));

if (!in_array($moduleFilter, $modules, true)) {
    $moduleFilter = 'Todos';
}
if (!in_array($categoryFilter, $categories, true)) {
    $categoryFilter = 'Todos';
}

$matchesFilters = static function (array $item) use ($moduleFilter, $categoryFilter): bool {
    return ($moduleFilter === 'Todos' || $item['module'] === $moduleFilter)
        && ($categoryFilter === 'Todos' || $item['category'] === $categoryFilter);
};

if (!$matchesFilters($docs[$selected])) {
    foreach ($docs as $key => $item) {
        if ($matchesFilters($item)) {
            $selected = $key;
            break;
        }
    }
}

$errors = [];
$doc = $docs[$selected];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $content = str_replace(["\r\n", "\r"], "\n", (string) ($_POST['content'] ?? ''));
    $target = realpath($doc['path']);
    $projectRootReal = realpath($projectRoot);

    $isInternal = $target !== false
        && $projectRootReal !== false
        && str_starts_with(platform_docs_normalize_path($target), rtrim(platform_docs_normalize_path($projectRootReal), '/') . '/')
        && !str_contains(platform_docs_normalize_path($target), '/public_html/');

    if (!$isInternal || !is_file((string) $target) || !is_writable((string) $target)) {
        $errors[] = 'Documento inexistente, publico ou sem permissao de escrita.';
    } elseif (file_put_contents((string) $target, $content) === false) {
        $errors[] = 'Nao foi possivel salvar o documento.';
    } else {
        flash('notice', 'Documento salvo.');
        header('Location: documentacao.php?doc=' . rawurlencode($selected) . '&modulo=' . rawurlencode($moduleFilter) . '&categoria=' . rawurlencode($categoryFilter));
        exit;
    }
}

$content = is_file($doc['path']) ? (string) file_get_contents($doc['path']) : '';
$filteredDocs = array_filter($docs, $matchesFilters);

render_header('Documentacao', 'documentacao');
?>
<header class="page-header mb-4">
    <div>
        <p class="text-body-secondary small text-uppercase mb-1">Administracao</p>
        <h1 class="h3 mb-2">Documentacao tecnica</h1>
        <p class="text-body-secondary mb-0">Markdowns internos do projeto para inspecao e edicao controlada.</p>
    </div>
    <a class="btn btn-outline-secondary" href="index.php">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>
        Painel
    </a>
</header>

<?php if ($errors !== []): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
            <div><?= h($error) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <aside class="col-lg-4 col-xl-3">
        <section class="card p-3">
            <h2 class="h6 mb-3">Modulos</h2>
            <div class="nav nav-pills flex-column gap-2 mb-4">
                <a class="nav-link <?= $moduleFilter === 'Todos' ? 'active' : '' ?>" href="documentacao.php?doc=<?= h($selected) ?>&modulo=Todos&categoria=<?= h(rawurlencode($categoryFilter)) ?>">Todos</a>
                <?php foreach ($modules as $module): ?>
                    <a class="nav-link <?= $moduleFilter === $module ? 'active' : '' ?>" href="documentacao.php?doc=<?= h($selected) ?>&modulo=<?= h(rawurlencode($module)) ?>&categoria=<?= h(rawurlencode($categoryFilter)) ?>"><?= h($module) ?></a>
                <?php endforeach; ?>
            </div>

            <h2 class="h6 mb-3">Tipos</h2>
            <div class="nav nav-pills flex-column gap-2 mb-4">
                <a class="nav-link <?= $categoryFilter === 'Todos' ? 'active' : '' ?>" href="documentacao.php?doc=<?= h($selected) ?>&modulo=<?= h(rawurlencode($moduleFilter)) ?>&categoria=Todos">Todos</a>
                <?php foreach ($categories as $category): ?>
                    <a class="nav-link <?= $categoryFilter === $category ? 'active' : '' ?>" href="documentacao.php?doc=<?= h($selected) ?>&modulo=<?= h(rawurlencode($moduleFilter)) ?>&categoria=<?= h(rawurlencode($category)) ?>"><?= h($category) ?></a>
                <?php endforeach; ?>
            </div>

            <h2 class="h6 mb-3">Arquivos</h2>
            <div class="list-group doc-list">
                <?php foreach ($filteredDocs as $key => $item): ?>
                    <a class="list-group-item list-group-item-action <?= $selected === $key ? 'active' : '' ?>" href="documentacao.php?doc=<?= h($key) ?>&modulo=<?= h(rawurlencode($moduleFilter)) ?>&categoria=<?= h(rawurlencode($categoryFilter)) ?>">
                        <span class="fw-semibold d-block"><?= h($item['title']) ?></span>
                        <span class="small d-block <?= $selected === $key ? '' : 'text-body-secondary' ?>"><?= h($item['relative']) ?></span>
                        <span class="badge text-bg-secondary mt-2"><?= h($item['category']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    </aside>

    <section class="col-lg-8 col-xl-9">
        <form method="post" class="card p-4">
            <?= csrf_field() ?>
            <input type="hidden" name="doc" value="<?= h($selected) ?>">

            <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-start mb-3">
                <div>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <span class="badge text-bg-primary"><?= h($doc['category']) ?></span>
                        <span class="badge text-bg-secondary"><?= h($doc['module']) ?></span>
                    </div>
                    <h2 class="h4 mb-1"><?= h($doc['title']) ?></h2>
                    <p class="text-body-secondary small mb-0"><?= h($doc['project_relative']) ?></p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-primary" id="btn-doc-edit" title="Editar Markdown">
                        <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>
                        Editar
                    </button>
                    <button type="submit" class="btn btn-primary d-none" id="btn-doc-save" title="Salvar Markdown">
                        <i class="bi bi-save me-1" aria-hidden="true"></i>
                        Salvar
                    </button>
                    <button type="button" class="btn btn-outline-secondary d-none" id="btn-doc-cancel" title="Cancelar edicao">
                        <i class="bi bi-x-lg me-1" aria-hidden="true"></i>
                        Cancelar
                    </button>
                </div>
            </div>

            <article class="markdown-body admin-doc-preview" id="doc-preview">
                <?= platform_docs_render_markdown($content) ?>
            </article>

            <div class="d-none" id="doc-editor-wrap">
                <label class="form-label" for="doc-editor">Markdown</label>
                <textarea class="form-control admin-doc-editor" id="doc-editor" name="content" spellcheck="false"><?= h($content) ?></textarea>
            </div>
        </form>
    </section>
</div>

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
<?php render_footer(); ?>
