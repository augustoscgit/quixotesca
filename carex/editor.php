<?php
declare(strict_types=1);

use Carex\Http\Security;

$bootstrapError = null;

try {
    require __DIR__ . '/src/bootstrap.php';
} catch (Throwable $exception) {
    $bootstrapError = $exception;
    if (!class_exists(Security::class, true)) {
        require_once __DIR__ . '/src/Http/Security.php';
    }
}

function editor_escape(string $value): string
{
    if (class_exists(Security::class)) {
        return Security::e($value);
    }

    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$targetFile = trim((string) ($_GET['file'] ?? $_POST['file'] ?? 'landing'));
if (!in_array($targetFile, ['landing', 'sobre'], true)) {
    $targetFile = 'landing';
}

$mdFile = __DIR__ . '/' . $targetFile . '.md';
$settingsFile = __DIR__ . '/config/settings.json';
$settings = json_decode(is_file($settingsFile) ? (string) file_get_contents($settingsFile) : '{}', true);
$allowMarkdownEdit = is_array($settings) ? ($settings['allow_markdown_edit'] ?? true) : true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allowMarkdownEdit) {
    file_put_contents($mdFile, (string) ($_POST['content'] ?? ''));
    header('Location: editor.php?file=' . urlencode($targetFile) . '&saved=true');
    exit;
}

if (class_exists(Security::class)) {
    Security::applyHeaders();
}

$markdownContent = is_file($mdFile) ? (string) file_get_contents($mdFile) : '';
$bootstrapCss = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css';
$bootstrapJs = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js';
?>
<!doctype html>
<html lang="pt-BR" data-module="carex">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CAREX | Editor de Documentacao</title>
    <link href="public/assets/favicon.png" rel="icon" type="image/png">
    <link href="<?= editor_escape($bootstrapCss) ?>" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="../assets/js/theme-switcher.js?v=20260629-vanilla"></script>
    <link href="../assets/css/style.css?v=20260629-vanilla" rel="stylesheet">
    <style>
        body {
            font-family: var(--bs-body-font-family);
        }

        .editor-shell {
            max-width: 1280px;
        }

        .editor-card,
        .preview-area {
            background: var(--bs-body-bg);
            border: 1px solid var(--bs-border-color);
            border-radius: 8px;
            box-shadow: none;
        }

        .preview-area,
        .editor-textarea {
            min-height: 420px;
        }

        .editor-textarea {
            border-radius: 8px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            line-height: 1.55;
            resize: vertical;
        }

        .preview-area {
            padding: 1.5rem;
            line-height: 1.7;
        }
    </style>
</head>
<body>
    <header class="app-navbar py-3 mb-4">
        <div class="container d-flex justify-content-between align-items-center gap-3">
            <a href="index.php" class="d-flex align-items-center gap-2 text-decoration-none">
                <img src="public/assets/favicon.png" alt="CAREX" class="navbar-logo-img">
                <span class="module-brand-text">CAREX</span>
            </a>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                Voltar
            </a>
        </div>
    </header>

    <main class="container editor-shell pb-5">
        <?php if ($bootstrapError instanceof Throwable): ?>
            <div class="alert alert-warning" role="alert">
                Bootstrap local do CAREX nao carregou: <?= editor_escape($bootstrapError->getMessage()) ?>
            </div>
        <?php endif; ?>

        <?php if (!$allowMarkdownEdit): ?>
            <div class="editor-card p-4 text-center">
                <i class="bi bi-shield-slash text-danger display-5 d-block mb-3" aria-hidden="true"></i>
                <h1 class="h4 fw-bold mb-2">Edicao desativada</h1>
                <p class="text-body-secondary mb-0">A edicao de arquivos Markdown externos esta desativada nas configuracoes administrativas.</p>
            </div>
        <?php else: ?>
            <?php if (($_GET['saved'] ?? '') === 'true'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    Documento salvo com sucesso.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            <?php endif; ?>

            <section class="editor-card p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                    <div>
                        <h1 class="h4 fw-bold mb-1">Documentacao dinamica</h1>
                        <p class="text-body-secondary mb-0">Editando <?= editor_escape($targetFile) ?>.md</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Modo de visualizacao">
                            <button type="button" id="btnPreview" class="btn btn-primary">
                                <i class="bi bi-eye-fill" aria-hidden="true"></i>
                                Visualizar
                            </button>
                            <button type="button" id="btnEdit" class="btn btn-outline-primary">
                                <i class="bi bi-pencil-fill" aria-hidden="true"></i>
                                Editar
                            </button>
                        </div>
                        <button type="submit" form="editorForm" class="btn btn-primary btn-sm">
                            <i class="bi bi-cloud-arrow-up-fill" aria-hidden="true"></i>
                            Salvar
                        </button>
                    </div>
                </div>

                <form id="editorForm" method="POST" action="editor.php">
                    <input type="hidden" name="file" value="<?= editor_escape($targetFile) ?>">
                    <textarea id="markdownEditor" name="content" class="form-control editor-textarea d-none"><?= editor_escape($markdownContent) ?></textarea>
                </form>

                <div id="markdownPreview" class="preview-area"></div>
            </section>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="<?= editor_escape($bootstrapJs) ?>"></script>
    <script>
        const editor = document.getElementById('markdownEditor');
        const preview = document.getElementById('markdownPreview');
        const btnPreview = document.getElementById('btnPreview');
        const btnEdit = document.getElementById('btnEdit');

        function updatePreview() {
            if (editor && preview && window.marked) {
                preview.innerHTML = marked.parse(editor.value);
            }
        }

        btnPreview?.addEventListener('click', () => {
            btnPreview.classList.replace('btn-outline-primary', 'btn-primary');
            btnEdit.classList.replace('btn-primary', 'btn-outline-primary');
            editor?.classList.add('d-none');
            preview?.classList.remove('d-none');
            updatePreview();
        });

        btnEdit?.addEventListener('click', () => {
            btnEdit.classList.replace('btn-outline-primary', 'btn-primary');
            btnPreview.classList.replace('btn-primary', 'btn-outline-primary');
            preview?.classList.add('d-none');
            editor?.classList.remove('d-none');
        });

        updatePreview();
    </script>
</body>
</html>
