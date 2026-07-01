<?php
declare(strict_types=1);

require_once __DIR__ . '/../../fichario/bootstrap.php';
require_editor();

$pdo = db();
$errors = [];
$notice = '';
$article = [
    'title' => '',
    'authors' => '',
    'year' => '',
    'journal' => '',
    'volume' => '',
    'issue' => '',
    'pages' => '',
    'publisher' => '',
    'doi' => '',
    'url' => '',
    'pdf_url' => '',
    'abstract' => '',
    'full_text' => '',
    'references_text' => '',
    'keywords' => '',
    'bibtex_key' => '',
    'bibtex_raw' => '',
    'reference_abnt' => '',
    'reference_abnt_locked' => '0',
    'reference_abnt_missing' => '',
    'analysis' => '',
    'data_year_start' => '',
    'data_year_end' => '',
];
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$isEditing = false;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM articles WHERE id = :id');
    $stmt->execute([':id' => $editId]);
    $savedArticle = $stmt->fetch();

    if ($savedArticle) {
        $isEditing = true;
        foreach ($article as $field => $_) {
            $article[$field] = (string) ($savedArticle[$field] ?? '');
        }
    } else {
        $errors[] = 'Artigo não encontrado para edição.';
        $editId = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $editId = (int) ($_POST['id'] ?? 0);
    $isEditing = $editId > 0;

    foreach ($article as $field => $_) {
        $article[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    if ($article['bibtex_raw'] === '') {
        $article['bibtex_key'] = '';
    } else {
        $article['bibtex_key'] = '';
        if (strlen($article['bibtex_raw']) > 100000) {
            $errors[] = 'A referencia BibTeX esta muito grande para armazenamento.';
        } else {
            $bibtexParser = new \App\Parsers\BibtexParser();
            $parsedBibtex = $bibtexParser->parse_raw($article['bibtex_raw']);
            if (($parsedBibtex['bibtex_key'] ?? '') !== '') {
                $article['bibtex_key'] = (string) $parsedBibtex['bibtex_key'];
            }
        }
    }

    if ($article['title'] === '') {
        $errors[] = 'Informe o titulo do artigo.';
    }

    if ($article['year'] !== '' && !preg_match('/^\d{4}$/', $article['year'])) {
        $errors[] = 'Informe o ano com quatro digitos.';
    }

    if ($article['data_year_start'] !== '' && !preg_match('/^\d{4}$/', $article['data_year_start'])) {
        $errors[] = 'Informe o ano inicial do período dos dados com quatro dígitos.';
    }

    if ($article['data_year_end'] !== '' && !preg_match('/^\d{4}$/', $article['data_year_end'])) {
        $errors[] = 'Informe o ano final do período dos dados com quatro dígitos.';
    }

    if ($article['data_year_start'] !== '' && $article['data_year_end'] !== '' && (int)$article['data_year_start'] > (int)$article['data_year_end']) {
        $errors[] = 'O ano inicial do período dos dados não pode ser maior que o ano final.';
    }

    $article['reference_abnt_locked'] = ((string) ($_POST['reference_abnt_locked'] ?? '0')) === '1' ? '1' : '0';
    $generatedReferenceAbnt = build_article_abnt_reference($article);
    $referenceMissing = article_abnt_missing_fields($article);
    $article['reference_abnt_missing'] = implode('; ', $referenceMissing);
    if ($article['reference_abnt_locked'] === '1') {
        if ($article['reference_abnt'] === '') {
            $errors[] = 'Informe a referencia ABNT antes de travar.';
        }
    } else {
        $article['reference_abnt'] = $generatedReferenceAbnt;
    }

    if ($errors === []) {
        $params = [
            ':title' => $article['title'],
            ':authors' => $article['authors'],
            ':year' => $article['year'] === '' ? null : (int) $article['year'],
            ':journal' => $article['journal'],
            ':volume' => $article['volume'],
            ':issue' => $article['issue'],
            ':pages' => $article['pages'],
            ':publisher' => $article['publisher'],
            ':doi' => $article['doi'],
            ':url' => $article['url'],
            ':pdf_url' => $article['pdf_url'],
            ':abstract' => $article['abstract'],
            ':full_text' => $article['full_text'],
            ':references_text' => $article['references_text'],
            ':keywords' => $article['keywords'],
            ':bibtex_key' => $article['bibtex_key'],
            ':bibtex_raw' => $article['bibtex_raw'],
            ':reference_abnt' => $article['reference_abnt'],
            ':reference_abnt_locked' => $article['reference_abnt_locked'] === '1' ? 'true' : 'false',
            ':reference_abnt_missing' => $article['reference_abnt_missing'],
            ':analysis' => $article['analysis'],
            ':data_year_start' => $article['data_year_start'] === '' ? null : (int) $article['data_year_start'],
            ':data_year_end' => $article['data_year_end'] === '' ? null : (int) $article['data_year_end'],
        ];

        $pdo->beginTransaction();
        try {
            if ($isEditing) {
                $stmt = $pdo->prepare(
                    "UPDATE articles SET
                        title = :title,
                        authors = :authors,
                        year = :year,
                        journal = :journal,
                        volume = :volume,
                        issue = :issue,
                        pages = :pages,
                        publisher = :publisher,
                        doi = :doi,
                        url = :url,
                        pdf_url = :pdf_url,
                        abstract = :abstract,
                        full_text = :full_text,
                        references_text = :references_text,
                        keywords = :keywords,
                        bibtex_key = :bibtex_key,
                        bibtex_raw = :bibtex_raw,
                        reference_abnt = :reference_abnt,
                        reference_abnt_locked = :reference_abnt_locked,
                        reference_abnt_missing = :reference_abnt_missing,
                        analysis = :analysis,
                        data_year_start = :data_year_start,
                        data_year_end = :data_year_end,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id"
                );
                $params[':id'] = $editId;
                $stmt->execute($params);
                $articleId = $editId;
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO articles (
                        title, authors, year, journal, volume, issue, pages, publisher,
                        doi, url, pdf_url, abstract, full_text, references_text, keywords, bibtex_key, bibtex_raw,
                        reference_abnt, reference_abnt_locked, reference_abnt_missing,
                        analysis, data_year_start, data_year_end
                    ) VALUES (
                        :title, :authors, :year, :journal, :volume, :issue, :pages, :publisher,
                        :doi, :url, :pdf_url, :abstract, :full_text, :references_text, :keywords, :bibtex_key, :bibtex_raw,
                        :reference_abnt, :reference_abnt_locked, :reference_abnt_missing,
                        :analysis, :data_year_start, :data_year_end
                    )"
                );
                $stmt->execute($params);
                $articleId = (int) $pdo->lastInsertId();
            }

            $pdo->commit();

            header('Location: view.php?id=' . $articleId);
            exit;
        } catch (Throwable $dbError) {
            $pdo->rollBack();
            $errors[] = 'Erro ao salvar no banco de dados: ' . $dbError->getMessage();
        }
    }
}

$referenceAbntGenerated = build_article_abnt_reference($article);
$referenceAbntMissingList = article_abnt_missing_fields($article);
$referenceAbntLocked = truthy_value($article['reference_abnt_locked'] ?? false);
if (!$referenceAbntLocked && trim((string) ($article['reference_abnt'] ?? '')) === '') {
    $article['reference_abnt'] = $referenceAbntGenerated;
}

?>
<!doctype html>
<html lang="pt-br" data-module="fichario">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editor - Fichário Acadêmico</title>
    <link rel="icon" type="image/png" href="../assets/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="assets/app.css?v=20260629-vanilla" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="../assets/js/theme-switcher.js?v=20260629-vanilla"></script>
    <link href="../assets/css/style.css?v=20260629-vanilla" rel="stylesheet">
</head>
<body>
    <?php render_navbar('editor'); ?>
    <main class="main-container py-4">
        <header class="page-header mb-4">
            <div>
                <h1 class="h2 mb-2"><?= $isEditing ? 'Editar Artigo' : 'Novo Artigo' ?></h1>
                <p class="text-secondary mb-0"><?= $isEditing ? 'Revise os campos e salve as alterações.' : 'Cadastro de artigo com extrator automático.' ?></p>
            </div>
            <?php if (!$isEditing): ?>
                <div>
                    <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#bibtexModal">
                        <i class="bi bi-filetype-bib me-1"></i>Importar BibTeX
                    </button>
                </div>
            <?php endif; ?>
        </header>

        <?php if ($notice !== ''): ?>
            <div class="alert alert-success bg-success-subtle border-success text-success-emphasis rounded-3" role="alert"><?= h($notice) ?></div>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <div class="alert alert-danger bg-danger-subtle border-danger text-danger-emphasis rounded-3" role="alert">
                <strong>Revise o cadastro:</strong>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="card p-4 mb-4" id="articleForm">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= h((string) $editId) ?>">
            <input type="hidden" name="bibtex_key" id="bibtex_key" value="<?= h($article['bibtex_key']) ?>">

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="title">Título</label>
                    <input class="form-control" type="text" name="title" id="title" value="<?= h($article['title']) ?>" required>
                </div>

                <div class="col-12">
                    <label class="form-label" for="authors">Autores</label>
                    <textarea class="form-control" name="authors" id="authors" rows="2"><?= h($article['authors']) ?></textarea>
                    <div class="form-text text-secondary">Separe autores por ponto e vírgula quando editar manualmente.</div>
                </div>

                <div class="col-md-2">
                    <label class="form-label" for="year">Ano</label>
                    <input class="form-control" type="text" inputmode="numeric" maxlength="4" name="year" id="year" value="<?= h($article['year']) ?>">
                </div>

                <div class="col-md-5">
                    <label class="form-label" for="journal">Periódico ou fonte</label>
                    <input class="form-control" type="text" name="journal" id="journal" value="<?= h($article['journal']) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label" for="volume">Volume</label>
                    <input class="form-control" type="text" name="volume" id="volume" value="<?= h($article['volume']) ?>">
                </div>

                <div class="col-md-1">
                    <label class="form-label" for="issue">No.</label>
                    <input class="form-control" type="text" name="issue" id="issue" value="<?= h($article['issue']) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label" for="pages">Páginas</label>
                    <input class="form-control" type="text" name="pages" id="pages" value="<?= h($article['pages']) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="publisher">Editora/base</label>
                    <input class="form-control" type="text" name="publisher" id="publisher" value="<?= h($article['publisher']) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="doi">DOI</label>
                    <input class="form-control" type="text" name="doi" id="doi" value="<?= h($article['doi']) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="url">URL</label>
                    <div class="input-group">
                        <input class="form-control" type="url" name="url" id="url" value="<?= h($article['url']) ?>">
                        <button class="btn btn-outline-primary" type="button" id="extractUrlButton">Extrair</button>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="pdf_url">Link do PDF</label>
                    <input class="form-control" type="url" name="pdf_url" id="pdf_url" value="<?= h($article['pdf_url']) ?>" placeholder="https://... (abre em nova aba)">
                </div>

                <div class="col-md-2">
                    <label class="form-label" for="data_year_start">Ano inicial dos dados</label>
                    <input class="form-control" type="text" inputmode="numeric" maxlength="4" name="data_year_start" id="data_year_start" value="<?= h((string) ($article['data_year_start'] ?? '')) ?>" placeholder="Ex: 2018">
                </div>

                <div class="col-md-2">
                    <label class="form-label" for="data_year_end">Ano final dos dados</label>
                    <input class="form-control" type="text" inputmode="numeric" maxlength="4" name="data_year_end" id="data_year_end" value="<?= h((string) ($article['data_year_end'] ?? '')) ?>" placeholder="Ex: 2020">
                </div>

                <div class="col-12">
                    <label class="form-label" for="abstract">Resumo</label>
                    <textarea class="form-control" name="abstract" id="abstract" rows="3"><?= h($article['abstract']) ?></textarea>
                </div>

                <div class="col-12">
                    <label class="form-label" for="full_text">Texto completo bruto</label>
                    <textarea class="form-control font-monospace" name="full_text" id="full_text" rows="14" spellcheck="false"><?= h($article['full_text']) ?></textarea>
                    <div class="form-text text-secondary">Cole aqui o conteúdo completo em texto simples.</div>
                </div>

                <div class="col-12">
                    <label class="form-label" for="references_text">Referências</label>
                    <textarea class="form-control font-monospace" name="references_text" id="references_text" rows="8" spellcheck="false"><?= h($article['references_text']) ?></textarea>
                    <div class="form-text text-secondary">Cole aqui a lista de referências do artigo em texto simples.</div>
                </div>

                <div class="col-12">
                    <label class="form-label" for="bibtex_raw">BibTeX original</label>
                    <textarea class="form-control font-monospace" name="bibtex_raw" id="bibtex_raw" rows="8" spellcheck="false"><?= h($article['bibtex_raw']) ?></textarea>
                    <div class="form-text text-secondary">Opcional. Se informado, o BibTeX sera armazenado com o artigo e usado nas exportacoes para agentes.</div>
                </div>

                <div class="col-12">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <label class="form-label mb-0" for="reference_abnt">Referencia ABNT</label>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-sm btn-outline-secondary<?= $referenceAbntLocked ? '' : ' d-none' ?>" type="button" id="unlockReferenceButton">
                                Destravar e recalcular
                            </button>
                            <button class="btn btn-sm btn-outline-primary<?= $referenceAbntLocked ? ' d-none' : '' ?>" type="button" id="lockReferenceButton">
                                Travar versão validada
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="reference_abnt_locked" id="reference_abnt_locked" value="<?= $referenceAbntLocked ? '1' : '0' ?>">
                    <textarea class="form-control font-monospace" name="reference_abnt" id="reference_abnt" rows="4" spellcheck="false"<?= $referenceAbntLocked ? ' readonly' : '' ?>><?= h($article['reference_abnt']) ?></textarea>
                    <div class="form-text text-secondary" id="referenceAbntHelp">
                        <?= $referenceAbntLocked ? 'Referencia travada: o sistema preservara este texto ao salvar.' : 'Referencia gerada pelo sistema. Para colar uma ABNT completa do Google Academico, edite o texto e trave antes de salvar.' ?>
                    </div>
                    <?php if ($referenceAbntMissingList !== []): ?>
                        <div class="alert alert-warning mt-2 mb-0 py-2 small">
                            Faltam informacoes para uma ABNT completa: <?= h(implode('; ', $referenceAbntMissingList)) ?>.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success mt-2 mb-0 py-2 small">
                            Dados essenciais presentes para gerar a referencia ABNT.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <label class="form-label" for="keywords">Palavras-chave</label>
                    <input class="form-control" type="text" name="keywords" id="keywords" value="<?= h($article['keywords']) ?>">
                </div>

                <div class="col-12">
                    <label class="form-label" for="analysis">Fichamento / Análise Geral</label>
                    <textarea class="form-control" name="analysis" id="analysis" rows="5" placeholder="Escreva a síntese ou análise principal do artigo..."><?= h($article['analysis']) ?></textarea>
                </div>
            </div>

            <div class="alert d-none mt-3 mb-0" id="urlExtractMessage" role="alert"></div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a class="btn btn-outline-secondary text-body" href="<?= $isEditing ? 'view.php?id=' . $editId : 'articles.php' ?>">Cancelar</a>
                <button class="btn btn-primary" type="submit"><?= $isEditing ? 'Salvar alterações' : 'Salvar artigo' ?></button>
            </div>
        </form>
    </main>

    <div class="modal fade" id="bibtexModal" tabindex="-1" aria-labelledby="bibtexModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="bibtexModalLabel">Importar BibTeX</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="bibtexInput">Referencia BibTeX</label>
                    <textarea class="form-control font-monospace" id="bibtexInput" rows="12" spellcheck="false"></textarea>
                    <div class="alert alert-danger d-none mt-3 mb-0" id="bibtexError" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="importBibtexButton">Preencher formulario</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script src="assets/app.js?v=20260603c"></script>
    <script>
        const importButton = document.getElementById('importBibtexButton');
        const bibtexInput = document.getElementById('bibtexInput');
        const bibtexError = document.getElementById('bibtexError');
        const modalElement = document.getElementById('bibtexModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);

        const fields = [
            'title', 'authors', 'year', 'journal', 'volume', 'issue', 'pages',
            'publisher', 'doi', 'url', 'pdf_url', 'abstract', 'full_text', 'references_text', 'keywords', 'bibtex_key', 'bibtex_raw',
            'reference_abnt', 'reference_abnt_missing'
        ];
        const extractUrlButton = document.getElementById('extractUrlButton');
        const urlExtractMessage = document.getElementById('urlExtractMessage');
        const urlInput = document.getElementById('url');
        const referenceAbntInput = document.getElementById('reference_abnt');
        const referenceAbntLockedInput = document.getElementById('reference_abnt_locked');
        const lockReferenceButton = document.getElementById('lockReferenceButton');
        const unlockReferenceButton = document.getElementById('unlockReferenceButton');
        const referenceAbntHelp = document.getElementById('referenceAbntHelp');
        const ajaxFormHeaders = {
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest'
        };
        const metadataFields = [
            'title', 'authors', 'year', 'journal', 'volume', 'issue', 'pages',
            'publisher', 'doi', 'url', 'pdf_url', 'abstract', 'keywords', 'full_text', 'references_text',
            'reference_abnt', 'reference_abnt_missing'
        ];

        if (importButton) {
            importButton.addEventListener('click', async () => {
                bibtexError.classList.add('d-none');
                bibtexError.textContent = '';
                const releaseImportBusy = window.FicharioUI
                    ? FicharioUI.setBusy(importButton, true, 'Importando...')
                    : () => {};

                try {
                    const body = new URLSearchParams();
                    body.set('bibtex', bibtexInput.value);
                    body.set('csrf_token', '<?= h(csrf_token()) ?>');

                    const response = await fetch('parse_bibtex.php', {
                        method: 'POST',
                        headers: ajaxFormHeaders,
                        body
                    });

                    const payload = await response.json();

                    if (!response.ok) {
                        throw new Error(payload.error || 'Nao foi possivel importar essa referencia.');
                    }

                    fields.forEach((field) => {
                        const input = document.getElementById(field);
                        if (input && Object.prototype.hasOwnProperty.call(payload.article, field)) {
                            input.value = payload.article[field] ?? '';
                        }
                    });
                    setReferenceLocked(false);

                    modal.hide();
                    document.getElementById('title').focus();
                } catch (error) {
                    bibtexError.textContent = error.message;
                    bibtexError.classList.remove('d-none');
                } finally {
                    releaseImportBusy();
                }
            });
        }

        function setReferenceLocked(locked) {
            if (!referenceAbntInput || !referenceAbntLockedInput) {
                return;
            }

            referenceAbntLockedInput.value = locked ? '1' : '0';
            referenceAbntInput.toggleAttribute('readonly', locked);
            lockReferenceButton?.classList.toggle('d-none', locked);
            unlockReferenceButton?.classList.toggle('d-none', !locked);
            if (referenceAbntHelp) {
                referenceAbntHelp.textContent = locked
                    ? 'Referencia travada: o sistema preservara este texto ao salvar.'
                    : 'Referencia destravada: ao salvar, o sistema recalculara a ABNT com os metadados atuais.';
            }
            if (!locked) {
                referenceAbntInput.focus();
            }
        }

        lockReferenceButton?.addEventListener('click', () => setReferenceLocked(true));
        unlockReferenceButton?.addEventListener('click', () => setReferenceLocked(false));

        extractUrlButton?.addEventListener('click', async () => {
            urlExtractMessage.className = 'alert d-none mt-3 mb-0';
            urlExtractMessage.textContent = '';
            const releaseExtractBusy = window.FicharioUI
                ? FicharioUI.setBusy(extractUrlButton, true, 'Extraindo...')
                : () => {};

            try {
                const body = new URLSearchParams();
                body.set('url', urlInput.value);
                body.set('csrf_token', '<?= h(csrf_token()) ?>');

                const response = await fetch('extract_url.php', {
                    method: 'POST',
                    headers: ajaxFormHeaders,
                    body
                });
                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(payload.error || 'Nao foi possivel extrair metadados dessa URL.');
                }

                let filled = 0;
                metadataFields.forEach((field) => {
                    const input = document.getElementById(field);
                    const value = payload.article[field] ?? '';

                    const shouldReplaceReference = field === 'reference_abnt' && referenceAbntLockedInput?.value !== '1';

                    if (input && String(value).trim() !== '' && (input.value.trim() === '' || shouldReplaceReference)) {
                        input.value = value;
                        filled++;
                    }
                });

                if (filled === 0) {
                    urlExtractMessage.textContent = 'Encontrei metadados, mas nenhum campo vazio precisava ser preenchido.';
                } else {
                    urlExtractMessage.textContent = `Metadados extraidos. ${filled} campo(s) vazio(s) preenchido(s).`;
                }
                urlExtractMessage.className = 'alert alert-success mt-3 mb-0';
            } catch (error) {
                urlExtractMessage.textContent = error.message;
                urlExtractMessage.className = 'alert alert-danger mt-3 mb-0';
            } finally {
                releaseExtractBusy();
            }
        });


    </script>
</body>
</html>
