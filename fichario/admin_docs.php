<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_admin();

$docs = [
    'requirements' => [
        'title' => 'Requisitos da aplicação',
        'path' => __DIR__ . '/docs/admin/application_requirements.md',
        'description' => 'Ambiente, banco de dados, segurança, APIs externas e versionamento.',
    ],
    'admin_docs' => [
        'title' => 'Documentação administrativa',
        'path' => __DIR__ . '/docs/admin/admin_documentation.md',
        'description' => 'Como manter a documentação interna pela área administrativa.',
    ],
    'developer' => [
        'title' => 'Orientações de desenvolvedor',
        'path' => __DIR__ . '/docs/developer/developer_guidelines.md',
        'description' => 'Convenções para manutenção, fluxo de leitura e evolução da aplicação.',
    ],
    'mysql_migration' => [
        'title' => 'Migração para MySQL',
        'path' => __DIR__ . '/docs/developer/mysql_migration_plan.md',
        'description' => 'Proposta, riscos e planejamento para MySQL 5.7 ou superior.',
    ],
    'ftp_security' => [
        'title' => 'Seguranca no FTP',
        'path' => __DIR__ . '/system_md/FTP_SECURITY.md',
        'description' => 'Tratamento de pastas publicas, privadas e protegidas na hospedagem por FTP.',
    ],
];

$selected = (string) ($_GET['doc'] ?? $_POST['doc'] ?? 'requirements');
if (!isset($docs[$selected])) {
    $selected = 'requirements';
}

$notice = '';
$errors = [];
$doc = $docs[$selected];

function render_markdown(string $markdown): string
{
    $lines = preg_split('/\R/', $markdown) ?: [];
    $html = '';
    $inList = false;
    $paragraph = [];

    $flushParagraph = static function () use (&$html, &$paragraph): void {
        if ($paragraph === []) {
            return;
        }
        $text = h(implode(' ', $paragraph));
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        $html .= '<p>' . $text . '</p>';
        $paragraph = [];
    };

    $closeList = static function () use (&$html, &$inList): void {
        if ($inList) {
            $html .= '</ul>';
            $inList = false;
        }
    };

    foreach ($lines as $line) {
        $trim = trim($line);

        if ($trim === '') {
            $flushParagraph();
            $closeList();
            continue;
        }

        if (preg_match('/^(#{1,3})\s+(.+)$/', $trim, $m)) {
            $flushParagraph();
            $closeList();
            $level = strlen($m[1]);
            $class = $level === 1 ? 'h3 text-white fw-bold mt-0' : ($level === 2 ? 'h5 text-white fw-semibold mt-4' : 'h6 text-secondary text-uppercase mt-3');
            $html .= '<h' . $level . ' class="' . $class . '">' . h($m[2]) . '</h' . $level . '>';
            continue;
        }

        if (preg_match('/^[-*]\s+(.+)$/', $trim, $m)) {
            $flushParagraph();
            if (!$inList) {
                $html .= '<ul>';
                $inList = true;
            }
            $item = h($m[1]);
            $item = preg_replace('/`([^`]+)`/', '<code>$1</code>', $item);
            $html .= '<li>' . $item . '</li>';
            continue;
        }

        $paragraph[] = $trim;
    }

    $flushParagraph();
    $closeList();

    return $html;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $content = (string) ($_POST['content'] ?? '');
    $path = $doc['path'];
    $allowedRoots = array_filter([
        realpath(__DIR__ . '/docs'),
        realpath(__DIR__ . '/system_md'),
    ]);
    $targetDir = realpath(dirname($path));
    $isAllowedTarget = false;

    foreach ($allowedRoots as $root) {
        if ($targetDir !== false && str_starts_with($targetDir, $root)) {
            $isAllowedTarget = true;
            break;
        }
    }

    if (!$isAllowedTarget) {
        $errors[] = 'Documento fora da pasta permitida.';
    } else {
        $ok = file_put_contents($path, str_replace(["\r\n", "\r"], "\n", $content));
        if ($ok === false) {
            $errors[] = 'Não foi possível salvar o documento.';
        } else {
            $notice = 'Documento salvo.';
        }
    }
}

$content = is_file($doc['path']) ? (string) file_get_contents($doc['path']) : '';
?>
<!doctype html>
<html lang="pt-br" data-module="fichario">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Documentação - Fichário Acadêmico</title>
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/app.css?v=20260603h" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        body { background: var(--bg-gradient); }
        .blob { animation: floatBlob 12s infinite alternate ease-in-out; }
        .blob-purple { animation-delay: -6s; }
        @keyframes floatBlob {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(60px, 40px) scale(1.15); }
        }
        .markdown-preview p,
        .markdown-preview li {
            color: #d1d5db;
            line-height: 1.65;
        }
        .markdown-preview code {
            color: #bfdbfe;
            background: rgba(59, 130, 246, 0.12);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 6px;
            padding: 0.1rem 0.35rem;
        }
        .doc-editor {
            min-height: min(72vh, 860px);
            font-family: Consolas, Monaco, monospace;
            font-size: 0.92rem;
            line-height: 1.55;
            resize: vertical;
        }
        .doc-actions .btn {
            min-width: 2.4rem;
        }
        .markdown-preview {
            min-height: min(64vh, 760px);
        }
    </style>
</head>
<body>
    <div class="blob blob-blue"></div>
    <div class="blob blob-purple"></div>

    <?php render_admin_navbar('docs'); ?>
    <main class="container py-4 main-container" style="position: relative; z-index: 10;">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center mb-4">
            <div>
                <h1 class="h3 text-white fw-bold mb-1">Documentação</h1>
                <p class="text-secondary mb-0">Requisitos, manutenção e orientações internas editáveis em Markdown.</p>
            </div>
            <a class="btn btn-outline-secondary text-white rounded-pill px-3" href="admin.php">Voltar ao painel</a>
        </div>

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
                <div class="glass-card p-3">
                    <h2 class="h6 text-white fw-bold mb-3">Arquivos</h2>
                    <div class="vstack gap-2">
                        <?php foreach ($docs as $key => $item): ?>
                            <a class="btn text-start rounded-3 <?= $selected === $key ? 'btn-primary' : 'btn-outline-secondary text-white' ?>" href="admin_docs.php?doc=<?= h($key) ?>">
                                <span class="fw-semibold d-block"><?= h($item['title']) ?></span>
                                <span class="small opacity-75"><?= h($item['description']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </aside>

            <section class="col-lg-9">
                <form method="post" class="glass-card p-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="doc" value="<?= h($selected) ?>">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-start mb-3">
                        <div>
                            <h2 class="h5 text-white fw-bold mb-1"><?= h($doc['title']) ?></h2>
                            <p class="text-secondary small mb-0"><?= h(str_replace(__DIR__ . DIRECTORY_SEPARATOR, '', $doc['path'])) ?></p>
                        </div>
                        <div class="d-flex gap-2 doc-actions">
                            <button type="button" class="btn btn-outline-primary rounded-pill px-3" id="btn-doc-edit" title="Editar Markdown">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                                <span class="ms-1">Editar</span>
                            </button>
                            <button type="submit" class="btn btn-primary rounded-pill px-3 d-none" id="btn-doc-save" title="Salvar Markdown">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/></svg>
                                <span class="ms-1">Salvar</span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary text-white rounded-pill px-3 d-none" id="btn-doc-cancel" title="Cancelar edição">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                                <span class="ms-1">Cancelar</span>
                            </button>
                        </div>
                    </div>

                    <article class="markdown-preview" id="doc-preview">
                        <?= render_markdown($content) ?>
                    </article>

                    <div class="d-none" id="doc-editor-wrap">
                        <textarea class="form-control doc-editor" name="content" id="doc-editor" spellcheck="false"><?= h($content) ?></textarea>
                    </div>
                </form>
            </section>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
