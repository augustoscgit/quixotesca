<?php
declare(strict_types=1);

require __DIR__ . '/../../fichario/bootstrap.php';

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(404);
    exit('Artigo não encontrado.');
}

function tag_category_rank(?string $category): int
{
    $normalized = search_normalize((string) $category);
    return match ($normalized) {
        'metodo' => 1,
        'fonte' => 2,
        'tema' => 3,
        default => 4,
    };
}

function compare_tag_rows(array $left, array $right): int
{
    $leftRank = tag_category_rank($left['category'] ?? '');
    $rightRank = tag_category_rank($right['category'] ?? '');
    if ($leftRank !== $rightRank) {
        return $leftRank <=> $rightRank;
    }

    $leftCategory = search_normalize((string) ($left['category'] ?? ''));
    $rightCategory = search_normalize((string) ($right['category'] ?? ''));
    if ($leftCategory !== $rightCategory) {
        return $leftCategory <=> $rightCategory;
    }

    return search_normalize((string) ($left['name'] ?? '')) <=> search_normalize((string) ($right['name'] ?? ''));
}

function sort_tag_rows(array &$tags): void
{
    usort($tags, 'compare_tag_rows');
}

function sort_tag_category_groups(array &$groups): void
{
    uksort($groups, static function (string $left, string $right): int {
        $leftRank = tag_category_rank($left);
        $rightRank = tag_category_rank($right);
        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }

        return search_normalize($left) <=> search_normalize($right);
    });
}

function marking_primary_category_rank(array $marking): int
{
    $ranks = array_map(
        static fn (array $tag): int => tag_category_rank($tag['category'] ?? ''),
        $marking['tags'] ?? []
    );

    return $ranks === [] ? 4 : min($ranks);
}

function compare_marking_rows(array $left, array $right): int
{
    $leftRank = marking_primary_category_rank($left);
    $rightRank = marking_primary_category_rank($right);
    if ($leftRank !== $rightRank) {
        return $leftRank <=> $rightRank;
    }

    return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
}

function sort_marking_rows(array &$markings): void
{
    usort($markings, 'compare_marking_rows');
}

function render_article_header_tags(array $headerTagsByCategory): string
{
    ob_start();
    ?>
    <div id="article-header-tags">
        <div class="d-flex flex-wrap gap-2 mb-0 align-items-center">
            <?php if ($headerTagsByCategory !== []): ?>
                <?php foreach ($headerTagsByCategory as $catLabel => $catTags): ?>
                    <?php $cColor = get_tag_colors($catLabel); ?>
                    <?php foreach ($catTags as $tag): ?>
                        <a href="tag_view.php?tag_id=<?= (int)$tag['id'] ?>" class="badge border tag-badge text-decoration-none"
                           style="background:<?= $cColor['bg'] ?>; color:<?= $cColor['text'] ?>; border-color:<?= $cColor['border'] ?> !important;"
                           <?= tag_tooltip_attrs($tag) ?>>
                            <?= h($tag['name']) ?>
                        </a>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            <?= render_article_alerts_button($headerTagsByCategory) ?>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

function render_article_alerts_button(array $headerTagsByCategory): string
{
    $hasMetodo = false;
    $hasFonte = false;
    $hasObjetivo = false;
    $isEnsaioOuRevisao = false;

    foreach ($headerTagsByCategory as $catTags) {
        foreach ($catTags as $tag) {
            $catNormalized = search_normalize($tag['category'] ?? '');
            $nameNormalized = search_normalize(trim($tag['name'] ?? ''));
            if ($catNormalized === 'metodo') {
                $hasMetodo = true;
                if (str_contains($nameNormalized, 'ensaio') || str_contains($nameNormalized, 'revisao')) {
                    $isEnsaioOuRevisao = true;
                }
            }
            if ($catNormalized === 'fonte') {
                $hasFonte = true;
            }
            if ($nameNormalized === 'objetivo' || (int)($tag['id'] ?? 0) === 44) {
                $hasObjetivo = true;
            }
        }
    }

    $missing = [];
    if (!$hasMetodo) {
        $missing[] = 'tag de Método';
    }
    if (!$hasFonte && !$isEnsaioOuRevisao) {
        $missing[] = 'tag de Fonte';
    }
    if (!$hasObjetivo) {
        $missing[] = 'tag Objetivo';
    }

    $hasAlerts = count($missing) > 0;
    
    if ($hasAlerts) {
        $tooltipText = '<strong>Faltam tags no artigo:</strong><br>';
        foreach ($missing as $item) {
            $tooltipText .= '• ' . $item . '<br>';
        }
    } else {
        $tooltipText = 'Todos os requisitos de fichamento cumpridos.';
    }

    ob_start();
    ?>
    <div class="d-inline-block" id="article-alerts-container" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="bottom" title="<?= h($tooltipText) ?>">
        <button class="btn <?= $hasAlerts ? 'btn-alert-active' : 'btn-alert-inactive' ?> rounded-circle d-flex align-items-center justify-content-center"
                style="width: 34px; height: 34px; padding: 0; font-weight: bold; font-size: 1.1rem;"
                <?= $hasAlerts ? '' : 'disabled' ?>>
            !
        </button>
    </div>
    <?php
    return (string) ob_get_clean();
}

function get_article_tags_state(PDO $pdo, int $articleId): array
{
    // A coluna lateral exibe notas como objetos; tags entram como atributos delas.
    $tags = [];
    $headerTagsById = [];

    $quotesStmt = $pdo->prepare("
        SELECT q.*, t.id AS tag_id, t.name AS tag_name, t.definition AS tag_definition, t.category AS tag_category
        FROM article_tag_quotes q
        LEFT JOIN article_quote_tags qt ON qt.quote_id = q.id
        LEFT JOIN tags t ON t.id = qt.tag_id
        WHERE q.article_id = :article_id
        ORDER BY q.id DESC, lower(t.name) ASC
    ");
    $quotesStmt->execute([':article_id' => $articleId]);
    $quotes = [];
    foreach (($quotesStmt->fetchAll() ?: []) as $quote) {
        $quoteId = (int) $quote['id'];
        if (!isset($quotes[$quoteId])) {
            $quotes[$quoteId] = [
                'id' => $quoteId,
                'article_id' => (int) $quote['article_id'],
                'quote_text' => $quote['quote_text'],
                'comment' => $quote['comment'] ?? '',
                'created_at' => $quote['created_at'] ?? '',
                'updated_at' => $quote['updated_at'] ?? '',
                'tags' => [],
            ];
        }

        $tagId = (int) $quote['tag_id'];
        if ($tagId > 0) {
            $cat = trim($quote['tag_category'] ?? '') !== '' ? $quote['tag_category'] : 'Outros';
            $quotes[$quoteId]['tags'][] = [
                'id' => $tagId,
                'name' => $quote['tag_name'],
                'definition' => $quote['tag_definition'] ?? '',
                'category' => $cat,
            ];

            if (!isset($headerTagsById[$tagId])) {
                $headerTagsById[$tagId] = [
                    'id' => $tagId,
                    'name' => $quote['tag_name'],
                    'definition' => $quote['tag_definition'] ?? '',
                    'category' => $cat,
                    'tag_quote' => '',
                    'tag_comment' => '',
                ];
            }
        }
    }

    $legacyTagsStmt = $pdo->prepare("
        SELECT t.id, t.name, t.definition, t.category, at.quote AS tag_quote, at.comment AS tag_comment
        FROM article_tags at
        JOIN tags t ON t.id = at.tag_id
        WHERE at.article_id = :article_id
        ORDER BY lower(t.name) ASC
    ");
    $legacyTagsStmt->execute([':article_id' => $articleId]);
    foreach (($legacyTagsStmt->fetchAll() ?: []) as $legacyTag) {
        $tagId = (int) $legacyTag['id'];
        $cat = trim((string) ($legacyTag['category'] ?? '')) !== '' ? $legacyTag['category'] : 'Outros';
        if (!isset($headerTagsById[$tagId])) {
            $headerTagsById[$tagId] = [
                'id' => $tagId,
                'name' => $legacyTag['name'],
                'definition' => $legacyTag['definition'] ?? '',
                'category' => $cat,
                'tag_quote' => $legacyTag['tag_quote'] ?? '',
                'tag_comment' => $legacyTag['tag_comment'] ?? '',
            ];
        }
    }

    $headerTags = array_values($headerTagsById);
    sort_tag_rows($headerTags);
    foreach ($quotes as &$quoteGroup) {
        sort_tag_rows($quoteGroup['tags']);
    }
    unset($quoteGroup);
    sort_marking_rows($quotes);

    $viewTagsByCategory = [];
    foreach ($tags as $tag) {
        $cat = trim($tag['category'] ?? '') !== '' ? $tag['category'] : '(Sem agrupamento)';
        $viewTagsByCategory[$cat][] = $tag;
    }
    sort_tag_category_groups($viewTagsByCategory);

    $headerTagsByCategory = [];
    foreach ($headerTags as $tag) {
        $cat = trim($tag['category'] ?? '') !== '' ? $tag['category'] : '(Sem agrupamento)';
        $headerTagsByCategory[$cat][] = $tag;
    }
    sort_tag_category_groups($headerTagsByCategory);

    $allTagsQuery = $pdo->query('SELECT * FROM tags ORDER BY lower(name) ASC')->fetchAll();
    $availableTags = [];
    $flatAvailableTags = [];
    $flatAllTags = [];
    foreach ($allTagsQuery as $t) {
        $cat = trim($t['category'] ?? '') !== '' ? $t['category'] : 'Outros';
        $flatAllTags[] = [
            'id' => (int) $t['id'],
            'name' => $t['name'],
            'definition' => $t['definition'] ?? '',
            'category' => $cat,
        ];
        $availableTags[$cat][] = $t;
        $flatAvailableTags[] = [
            'id' => (int) $t['id'],
            'name' => $t['name'],
            'definition' => $t['definition'] ?? '',
            'category' => $cat,
        ];
    }
    sort_tag_rows($flatAllTags);
    sort_tag_rows($flatAvailableTags);
    sort_tag_category_groups($availableTags);

    return [
        'tags' => $tags,
        'quotes' => array_values($quotes),
        'view_tags_by_category' => $viewTagsByCategory,
        'header_tags_by_category' => $headerTagsByCategory,
        'available_tags' => $availableTags,
        'flat_available_tags' => $flatAvailableTags,
        'flat_all_tags' => $flatAllTags,
        'is_empty' => $tags === [] && $quotes === [],
    ];
}

function render_article_tags_panel(array $state, bool $canEditArticle): string
{
    $tags = $state['tags'];
    $quotes = $state['quotes'];
    $viewTagsByCategory = $state['view_tags_by_category'];
    $availableTags = $state['available_tags'];
    $isTagsEmpty = $state['is_empty'];

    $pdo = db();
    $currentUser = current_user();
    $userId = (int) ($currentUser['id'] ?? 0);
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

                if ($quotes !== []) {
                    $quoteIds = array_column($quotes, 'id');
                    $inClause = implode(',', array_map('intval', $quoteIds));
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

    ob_start();
    ?>
    <article class="glass-card p-4" id="article-tags-panel">
        <div class="d-flex justify-content-between align-items-center mb-3" onclick="toggleCollapseCard('tags-card-body', 'tags-collapse-icon')" style="cursor: pointer; user-select: none;">
            <h2 class="h5 text-white fw-bold mb-0">Tags & Conceitos Vinculados</h2>
            <span id="tags-collapse-icon" class="collapse-btn"><?= $isTagsEmpty ? "+" : "-" ?></span>
        </div>

        <div id="tags-card-body" class="<?= $isTagsEmpty ? 'd-none' : '' ?>">
            <?php if ($isLoggedIn): ?>
                <div class="mb-4 p-3 rounded-3 bg-black bg-opacity-25 border border-secondary border-opacity-25">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <label class="form-label small mb-0 fw-semibold text-white d-flex align-items-center gap-1" for="active-project-selector">
                            <i class="bi bi-folder2-open text-primary"></i> Projeto de Trabalho Ativo
                        </label>
                        <?php if ($activeProjectId > 0): ?>
                            <a href="project.php?id=<?= $activeProjectId ?>" class="btn btn-sm btn-link p-0 text-primary text-decoration-none small" style="font-size: 0.75rem;">
                                Ver Projeto <i class="bi bi-arrow-right-short"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <select class="form-select form-select-sm" id="active-project-selector" onchange="setActiveProject(this.value)">
                        <option value="0">-- Nenhum projeto ativo --</option>
                        <?php foreach ($userProjects as $proj): ?>
                            <option value="<?= (int) $proj['id'] ?>" <?= $activeProjectId === (int)$proj['id'] ? 'selected' : '' ?>>
                                <?= h($proj['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($tags === [] && $quotes === []): ?>
                <div class="text-secondary small mb-3 p-3 bg-black bg-opacity-25 rounded-3 text-center" id="no-tags-notice">
                    Nenhuma tag vinculada a este artigo. Use o formulário abaixo para vincular.
                </div>
            <?php else: ?>
                <?php if ($tags !== []): ?>
                <div class="vstack gap-3 mb-4" id="linked-tags-list">
                    <?php foreach ($viewTagsByCategory as $catLabel => $catTags): ?>
                        <?php $cColor = get_tag_colors($catLabel); ?>
                        <div class="pb-2 border-bottom border-secondary border-opacity-25">
                            <h3 class="h6 text-secondary text-uppercase mb-2" style="font-size:0.7rem; letter-spacing:0.05em;"><?= h($catLabel) ?></h3>
                            <div class="vstack gap-2">
                                <?php foreach ($catTags as $tag): ?>
                                    <div class="p-2 tag-row d-flex flex-column gap-1">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <a href="tag_view.php?tag_id=<?= (int)$tag['id'] ?>" class="badge border tag-badge text-decoration-none" style="background:<?= $cColor['bg'] ?>; color:<?= $cColor['text'] ?>; border-color:<?= $cColor['border'] ?> !important;" <?= tag_tooltip_attrs($tag) ?>>
                                                <?= h($tag['name']) ?>
                                            </a>
                                            <?php if ($canEditArticle): ?>
                                                <div class="d-flex gap-2">
                                                    <button class="btn btn-sm btn-link p-0 text-white-50" onclick="toggleEditComment(<?= $tag['id'] ?>)" title="Editar citação e observações">
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                                    </button>
                                                    <button class="btn btn-sm btn-link p-0 text-danger" onclick="unlinkTag(<?= $tag['id'] ?>, '<?= h($tag['name']) ?>')" title="Desvincular tag">&times;</button>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="small text-white-50" id="comment-display-<?= $tag['id'] ?>">
                                            <?php if (trim($tag['tag_quote'] ?? '') === '' && trim($tag['tag_comment'] ?? '') === ''): ?>
                                                <em class="text-secondary" style="font-size:0.8rem;">Sem citação ou observações.</em>
                                            <?php else: ?>
                                                <?php if (trim($tag['tag_quote'] ?? '') !== ''): ?>
                                                    <div class="note-text mb-2 text-white">
                                                        <span class="note-meta d-block text-uppercase fw-bold mb-1">Citação</span>
                                                        <div class="quote-box expandable-text collapsed" onclick="toggleExpandableText(this)" id="quote-text-<?= $tag['id'] ?>" title="Clique para expandir/recolher"><?= h($tag['tag_quote']) ?></div>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (trim($tag['tag_comment'] ?? '') !== ''): ?>
                                                    <div class="note-text text-white-50">
                                                        <span class="note-meta d-block text-uppercase fw-bold mb-1">Observação</span>
                                                        <div class="observation-box expandable-text collapsed" onclick="toggleExpandableText(this)" id="comment-text-<?= $tag['id'] ?>" title="Clique para expandir/recolher"><?= h($tag['tag_comment']) ?></div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($canEditArticle): ?>
                                            <div class="d-none mt-2" id="comment-edit-<?= $tag['id'] ?>">
                                                <div class="mb-2">
                                                    <label class="form-label text-secondary mb-1" style="font-size: 0.72rem;">Citação da Tag</label>
                                                    <textarea class="form-control form-control-sm" id="input-quote-<?= $tag['id'] ?>" rows="2" placeholder="Citação (trecho do texto)..." style="font-size: 0.8rem;"><?= h($tag['tag_quote'] ?? '') ?></textarea>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label text-secondary mb-1" style="font-size: 0.72rem;">Observações / Análise</label>
                                                    <textarea class="form-control form-control-sm" id="input-comment-<?= $tag['id'] ?>" rows="2" placeholder="Observações..." style="font-size: 0.8rem;"><?= h($tag['tag_comment'] ?? '') ?></textarea>
                                                </div>
                                                <div class="d-flex justify-content-end gap-2">
                                                    <button class="btn btn-sm btn-outline-secondary text-white rounded-pill px-3" onclick="toggleEditComment(<?= $tag['id'] ?>)">Cancelar</button>
                                                    <button class="btn btn-sm btn-primary rounded-pill px-3" onclick="saveTagComment(<?= $tag['id'] ?>)">Salvar</button>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($quotes !== []): ?>
                    <div class="pt-3 mt-3 border-top border-secondary border-opacity-25">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h3 class="h6 text-secondary text-uppercase mb-0" style="font-size:0.7rem; letter-spacing:0.05em;">Marcações</h3>
                            <button class="btn btn-sm btn-link p-0 text-secondary text-decoration-none" style="font-size: 0.7rem;" type="button" onclick="toggleAllMarkings(this, '.pt-3')">
                                Expandir todas
                            </button>
                        </div>
                        <div class="vstack gap-2">
                            <?php foreach ($quotes as $quote): ?>
                                <?php
                                    $quoteText = trim((string) ($quote['quote_text'] ?? ''));
                                    $quoteComment = trim((string) ($quote['comment'] ?? ''));
                                    $isIncompleteMarking = $quoteText === '' && $quoteComment === '';
                                    $quoteTextTeaser = text_teaser($quoteText, 170);
                                    $quoteCommentTeaser = text_teaser($quoteComment, 150);
                                    $noteId = (int)$quote['id'];
                                ?>
                                <div class="note-card p-2 rounded-3 border border-secondary border-opacity-25 bg-black bg-opacity-25 small text-white-50"
                                     data-quote-id="<?= $noteId ?>"
                                     data-quote-text="<?= h($quote['quote_text']) ?>"
                                     data-quote-comment="<?= h((string) ($quote['comment'] ?? '')) ?>"
                                     data-quote-tags="<?= h(json_encode($quote['tags'] ?? [], JSON_UNESCAPED_UNICODE)) ?>">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php if ($isIncompleteMarking): ?>
                                                <span class="badge rounded-pill bg-warning text-dark" title="Nota sem citação ou observação">!</span>
                                            <?php endif; ?>
                                            <?php if (($quote['tags'] ?? []) !== []): ?>
                                                <?php foreach ($quote['tags'] as $quoteTag): ?>
                                                    <?php $qColor = get_tag_colors($quoteTag['category']); ?>
                                                    <a href="tag_view.php?tag_id=<?= (int) $quoteTag['id'] ?>" class="badge border tag-badge text-decoration-none" style="background:<?= $qColor['bg'] ?>; color:<?= $qColor['text'] ?>; border-color:<?= $qColor['border'] ?> !important;" <?= tag_tooltip_attrs($quoteTag) ?>>
                                                        <?= h($quoteTag['name']) ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="badge rounded-pill border border-warning text-warning bg-warning bg-opacity-10" title="A tag vinculada a esta nota foi excluída do vocabulário.">Sem tag</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex gap-2 flex-shrink-0">
                                            <button class="btn btn-sm btn-link p-0 text-white-50" type="button" onclick="openMarkingReadFromButton(this)" title="Ler nota">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                            </button>
                                            <?php if ($canEditArticle): ?>
                                                <button class="btn btn-sm btn-link p-0 text-white-50" type="button" onclick="editQuoteFromButton(this)" title="Editar nota">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                                </button>
                                                <button class="btn btn-sm btn-link p-0 text-danger" type="button" onclick="deleteQuoteFromButton(this)" title="Excluir nota">&times;</button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-link p-0 text-white-50 locked-action" type="button" onclick="showAuthRequired()" title="Editar nota">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                                </button>
                                                <button class="btn btn-sm btn-link p-0 text-danger locked-action" type="button" onclick="showAuthRequired()" title="Excluir nota">&times;</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="note-content">
                                        <?php if ($isIncompleteMarking): ?>
                                            <em class="text-secondary">Nota sem citação ou observação.</em>
                                        <?php else: ?>
                                            <?php if ($quoteText !== ''): ?>
                                                <div class="marking-preview marking-preview-quote mb-2">
                                                    <span class="note-teaser-label">Citação</span>
                                                    <div class="quote-box expandable-text collapsed" onclick="toggleExpandableText(this)" title="Clique para expandir/recolher"><?= h($quoteText) ?></div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($quoteComment !== ''): ?>
                                                <div class="marking-preview marking-preview-comment">
                                                    <span class="note-teaser-label">Observação</span>
                                                    <div class="observation-box expandable-text collapsed" onclick="toggleExpandableText(this)" title="Clique para expandir/recolher"><?= h($quoteComment) ?></div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($activeProjectId > 0 && $isLoggedIn): ?>
                                        <div class="mt-3 pt-2 border-top border-secondary border-opacity-25" style="font-size: 0.8rem;">
                                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                                <span class="text-secondary small d-flex align-items-center gap-1">
                                                    <i class="bi bi-link-45deg"></i> Projeto Ativo:
                                                </span>
                                                
                                                <?php $links = $linkedNotesState[$noteId] ?? []; ?>
                                                
                                                <div class="d-flex flex-wrap gap-1 align-items-center" id="note-links-container-<?= $noteId ?>">
                                                    <?php if ($links !== []): ?>
                                                        <?php foreach ($links as $link): ?>
                                                            <span class="badge bg-primary bg-opacity-15 text-primary border border-primary border-opacity-25 d-inline-flex align-items-center gap-1 py-1 px-2" style="font-size: 0.7rem; border-radius: 6px;">
                                                                <i class="bi bi-folder-check"></i> <?= h($link['section_title']) ?>
                                                                <?php if ($canEditArticle): ?>
                                                                    <button type="button" class="btn btn-sm btn-link p-0 text-danger border-0 d-inline-flex align-items-center" onclick="unlinkNoteFromSection(<?= $noteId ?>, <?= $link['section_id'] ?>)" title="Remover vínculo" style="vertical-align: middle;">
                                                                        <i class="bi bi-x-circle-fill text-danger ms-1" style="font-size: 0.82rem;"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <span class="text-secondary italic" style="font-size: 0.75rem;">Não vinculado</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <?php if ($canEditArticle): ?>
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
                                                            <option value="<?= (int) $sect['id'] ?>"><?= h($sect['title']) ?></option>
                                                        <?php endforeach; ?>
                                                        <option value="new_section">+ Criar nova seção...</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="mt-2 d-none" id="new-section-input-container-<?= $noteId ?>">
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" class="form-control form-control-sm" id="new-section-title-<?= $noteId ?>" placeholder="Nome da nova seção..." style="font-size: 0.72rem;">
                                                        <button class="btn btn-primary btn-sm" type="button" onclick="submitCreateSectionAndLink(<?= $noteId ?>)" style="font-size: 0.72rem;">Criar</button>
                                                        <button class="btn btn-outline-secondary text-white btn-sm" type="button" onclick="cancelCreateSectionInline(<?= $noteId ?>)" style="font-size: 0.72rem;">X</button>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="d-flex justify-content-end gap-2">
                <?php if ($canEditArticle): ?>
                    <button class="btn btn-sm btn-primary rounded-pill px-3" onclick="toggleLinkPanel()">+ Vincular Tag</button>
                <?php else: ?>
                    <button class="btn btn-sm btn-primary rounded-pill px-3 locked-action" type="button" onclick="showAuthRequired()">+ Vincular Tag</button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($canEditArticle): ?>
            <div class="mt-4 p-3 bg-black bg-opacity-25 border border-secondary border-opacity-25 rounded-3 d-none" id="link-tag-panel">
                <h3 class="h6 text-white mb-3">Vincular Tag Existente</h3>
                <?php if ($availableTags === []): ?>
                    <p class="text-secondary small mb-0">Todas as tags do sistema já estão vinculadas a este artigo.</p>
                <?php else: ?>
                    <div class="mb-3 position-relative">
                        <label class="form-label small" for="search-tag-link-input">Selecione uma ou mais tags</label>
                        <input type="text" class="form-control" id="search-tag-link-input" placeholder="Digite para buscar tag..." autocomplete="off">
                        <input type="hidden" id="select-tag-ids" value="">
                        <div id="autocomplete-tag-dropdown" class="autocomplete-dropdown d-none"></div>
                        <div class="d-flex flex-wrap gap-2 mt-2" id="link-selected-tags"></div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small" for="link-quote">Citação da Tag (Opcional)</label>
                        <textarea class="form-control" id="link-quote" rows="2" placeholder="Citação (trecho do texto)..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small" for="link-comment">Observações / Análise (Opcional)</label>
                        <textarea class="form-control" id="link-comment" rows="2" placeholder="Observações sobre o vínculo desta tag..."></textarea>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button class="btn btn-sm btn-outline-secondary text-white rounded-pill" onclick="toggleLinkPanel()">Cancelar</button>
                        <button class="btn btn-sm btn-primary rounded-pill px-3" onclick="submitLinkTag()">Confirmar</button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </article>
    <?php
    return (string) ob_get_clean();
}

function article_tags_payload(PDO $pdo, int $articleId, bool $canEditArticle): array
{
    $state = get_article_tags_state($pdo, $articleId);
    return [
        'tags_panel_html' => render_article_tags_panel($state, $canEditArticle),
        'header_tags_html' => render_article_header_tags($state['header_tags_by_category']),
        'available_tags' => $state['flat_available_tags'],
        'all_tags' => $state['flat_all_tags'],
        'is_tags_empty' => $state['is_empty'],
    ];
}

// Controller for POST/AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    require_login();
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action !== 'delete_article') {
        require_editor();
    }

    if ($action === 'save_fichamento') {
        $analysis = trim((string) ($_POST['analysis'] ?? ''));
        try {
            $up = $pdo->prepare('UPDATE articles SET analysis = :analysis WHERE id = :id');
            $up->execute([':analysis' => $analysis, ':id' => $id]);
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'save_full_text') {
        $fullText = trim((string) ($_POST['full_text'] ?? ''));
        try {
            $up = $pdo->prepare('UPDATE articles SET full_text = :full_text WHERE id = :id');
            $up->execute([':full_text' => $fullText, ':id' => $id]);
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'link_tag') {
        $tagIds = array_values(array_unique(array_filter(array_map(
            'intval',
            explode(',', (string) ($_POST['tag_ids'] ?? ($_POST['tag_id'] ?? '')))
        ), static fn (int $tagId): bool => $tagId > 0)));
        $quote = trim((string) ($_POST['quote'] ?? ''));
        $comment = trim((string) ($_POST['comment'] ?? ''));
        if ($tagIds !== []) {
            try {
                $pdo->beginTransaction();
                $validTagStmt = $pdo->prepare('SELECT id FROM tags WHERE id = :id');
                $ins = $pdo->prepare('
                    INSERT INTO article_tag_quotes (article_id, quote_text, comment)
                    VALUES (:article_id, :quote_text, :comment)
                ');
                $quoteTagLink = $pdo->prepare('INSERT INTO article_quote_tags (quote_id, tag_id) VALUES (:quote_id, :tag_id) ON CONFLICT DO NOTHING');

                foreach ($tagIds as $tagId) {
                    $validTagStmt->execute([':id' => $tagId]);
                    if (!$validTagStmt->fetchColumn()) {
                        throw new RuntimeException('Uma das tags selecionadas nao existe.');
                    }
                }

                $ins->execute([':article_id' => $id, ':quote_text' => $quote, ':comment' => $comment]);
                $quoteId = (int) $pdo->lastInsertId();
                foreach ($tagIds as $tagId) {
                    $quoteTagLink->execute([':quote_id' => $quoteId, ':tag_id' => $tagId]);
                }
                $pdo->commit();
                echo json_encode(['success' => true] + article_tags_payload($pdo, $id, can_edit_content()));
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }
        echo json_encode(['success' => false, 'error' => 'Tag inválida.']);
        exit;
    }

    if ($action === 'unlink_tag') {
        $tagId = (int) ($_POST['tag_id'] ?? 0);
        if ($tagId > 0) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("
                    SELECT q.id
                    FROM article_tag_quotes q
                    JOIN article_quote_tags qt ON qt.quote_id = q.id
                    WHERE q.article_id = :article_id AND qt.tag_id = :tag_id
                ");
                $stmt->execute([':article_id' => $id, ':tag_id' => $tagId]);
                $quoteIds = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

                if ($quoteIds !== []) {
                    $delLink = $pdo->prepare("DELETE FROM article_quote_tags WHERE quote_id = :quote_id AND tag_id = :tag_id");
                    $checkCount = $pdo->prepare("SELECT COUNT(*) FROM article_quote_tags WHERE quote_id = :quote_id");
                    $delQuote = $pdo->prepare("DELETE FROM article_tag_quotes WHERE id = :id");

                    foreach ($quoteIds as $quoteId) {
                        $delLink->execute([':quote_id' => (int)$quoteId, ':tag_id' => $tagId]);
                        $checkCount->execute([':quote_id' => (int)$quoteId]);
                        if ((int)$checkCount->fetchColumn() === 0) {
                            $delQuote->execute([':id' => (int)$quoteId]);
                        }
                    }
                }
                $pdo->commit();
                echo json_encode(['success' => true] + article_tags_payload($pdo, $id, can_edit_content()));
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }
        echo json_encode(['success' => false, 'error' => 'Tag inválida.']);
        exit;
    }

    if ($action === 'update_tag_comment') {
        $tagId = (int) ($_POST['tag_id'] ?? 0);
        $quote = trim((string) ($_POST['quote'] ?? ''));
        $comment = trim((string) ($_POST['comment'] ?? ''));
        if ($tagId > 0) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("
                    SELECT q.id
                    FROM article_tag_quotes q
                    JOIN article_quote_tags qt ON qt.quote_id = q.id
                    WHERE q.article_id = :article_id AND qt.tag_id = :tag_id
                    LIMIT 1
                ");
                $stmt->execute([':article_id' => $id, ':tag_id' => $tagId]);
                $quoteId = (int)$stmt->fetchColumn();

                if ($quoteId > 0) {
                    $up = $pdo->prepare('UPDATE article_tag_quotes SET quote_text = :quote_text, comment = :comment, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
                    $up->execute([':quote_text' => $quote, ':comment' => $comment, ':id' => $quoteId]);
                } else {
                    $ins = $pdo->prepare('INSERT INTO article_tag_quotes (article_id, quote_text, comment) VALUES (:article_id, :quote_text, :comment)');
                    $ins->execute([':article_id' => $id, ':quote_text' => $quote, ':comment' => $comment]);
                    $newQuoteId = (int)$pdo->lastInsertId();
                    $quoteTagLink = $pdo->prepare('INSERT INTO article_quote_tags (quote_id, tag_id) VALUES (:quote_id, :tag_id) ON CONFLICT DO NOTHING');
                    $quoteTagLink->execute([':quote_id' => $newQuoteId, ':tag_id' => $tagId]);
                }
                $pdo->commit();
                echo json_encode(['success' => true] + article_tags_payload($pdo, $id, can_edit_content()));
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }
        echo json_encode(['success' => false, 'error' => 'Tag inválida.']);
        exit;
    }

    if ($action === 'create_tag_quote') {
        $tagIds = array_values(array_unique(array_filter(array_map(
            'intval',
            explode(',', (string) ($_POST['tag_ids'] ?? ($_POST['tag_id'] ?? '')))
        ), static fn (int $tagId): bool => $tagId > 0)));
        $quoteText = trim((string) ($_POST['quote_text'] ?? ''));
        $comment = trim((string) ($_POST['comment'] ?? ''));

        if ($tagIds === []) {
            echo json_encode(['success' => false, 'error' => 'Selecione pelo menos uma tag.']);
            exit;
        }

        try {
            $pdo->beginTransaction();
            $validTagStmt = $pdo->prepare('SELECT id FROM tags WHERE id = :id');
            $quoteTagLink = $pdo->prepare("INSERT INTO article_quote_tags (quote_id, tag_id) VALUES (:quote_id, :tag_id) ON CONFLICT DO NOTHING");

            $ins = $pdo->prepare("
                INSERT INTO article_tag_quotes (article_id, quote_text, comment)
                VALUES (:article_id, :quote_text, :comment)
            ");

            foreach ($tagIds as $tagId) {
                $validTagStmt->execute([':id' => $tagId]);
                if (!$validTagStmt->fetchColumn()) {
                    throw new RuntimeException('Uma das tags selecionadas não existe.');
                }
            }

            $ins->execute([
                ':article_id' => $id,
                ':quote_text' => $quoteText,
                ':comment' => $comment,
            ]);
            $quoteId = (int) $pdo->lastInsertId();
            foreach ($tagIds as $tagId) {
                $quoteTagLink->execute([':quote_id' => $quoteId, ':tag_id' => $tagId]);
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'quote_id' => $quoteId] + article_tags_payload($pdo, $id, can_edit_content()));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'update_tag_quote') {
        $quoteId = (int) ($_POST['quote_id'] ?? 0);
        $tagIds = array_values(array_unique(array_filter(array_map(
            'intval',
            explode(',', (string) ($_POST['tag_ids'] ?? ''))
        ), static fn (int $tagId): bool => $tagId > 0)));
        $quoteText = trim((string) ($_POST['quote_text'] ?? ''));
        $comment = trim((string) ($_POST['comment'] ?? ''));

        if ($quoteId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Marcação inválida.']);
            exit;
        }

        if ($tagIds === []) {
            echo json_encode(['success' => false, 'error' => 'Selecione pelo menos uma tag.']);
            exit;
        }

        try {
            $exists = $pdo->prepare('SELECT id FROM article_tag_quotes WHERE id = :id AND article_id = :article_id');
            $exists->execute([':id' => $quoteId, ':article_id' => $id]);
            if (!$exists->fetchColumn()) {
                throw new RuntimeException('Marcação não encontrada para este artigo.');
            }

            $pdo->beginTransaction();
            $validTagStmt = $pdo->prepare('SELECT id FROM tags WHERE id = :id');
            foreach ($tagIds as $tagId) {
                $validTagStmt->execute([':id' => $tagId]);
                if (!$validTagStmt->fetchColumn()) {
                    throw new RuntimeException('Uma das tags selecionadas nao existe.');
                }
            }

            $up = $pdo->prepare('
                UPDATE article_tag_quotes
                SET quote_text = :quote_text, comment = :comment, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND article_id = :article_id
            ');
            $up->execute([
                ':quote_text' => $quoteText,
                ':comment' => $comment,
                ':id' => $quoteId,
                ':article_id' => $id,
            ]);

            $delLinks = $pdo->prepare('DELETE FROM article_quote_tags WHERE quote_id = :quote_id');
            $delLinks->execute([':quote_id' => $quoteId]);

            $quoteTagLink = $pdo->prepare('INSERT INTO article_quote_tags (quote_id, tag_id) VALUES (:quote_id, :tag_id) ON CONFLICT DO NOTHING');
            foreach ($tagIds as $tagId) {
                $quoteTagLink->execute([':quote_id' => $quoteId, ':tag_id' => $tagId]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'quote_id' => $quoteId] + article_tags_payload($pdo, $id, can_edit_content()));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete_tag_quote') {
        $quoteId = (int) ($_POST['quote_id'] ?? 0);
        if ($quoteId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Marcação inválida.']);
            exit;
        }

        try {
            $exists = $pdo->prepare('SELECT id FROM article_tag_quotes WHERE id = :id AND article_id = :article_id');
            $exists->execute([':id' => $quoteId, ':article_id' => $id]);
            if (!$exists->fetchColumn()) {
                throw new RuntimeException('Marcação não encontrada para este artigo.');
            }

            $pdo->beginTransaction();
            $delLinks = $pdo->prepare('DELETE FROM article_quote_tags WHERE quote_id = :quote_id');
            $delLinks->execute([':quote_id' => $quoteId]);
            $delQuote = $pdo->prepare('DELETE FROM article_tag_quotes WHERE id = :id AND article_id = :article_id');
            $delQuote->execute([':id' => $quoteId, ':article_id' => $id]);
            $pdo->commit();

            echo json_encode(['success' => true] + article_tags_payload($pdo, $id, can_edit_content()));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'create_and_link_tag') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? ''));
        $definition = trim((string) ($_POST['definition'] ?? ''));
        $quote = trim((string) ($_POST['quote'] ?? ''));
        $comment = trim((string) ($_POST['comment'] ?? ''));

        if ($name === '') {
             echo json_encode(['success' => false, 'error' => 'Nome da tag é obrigatório.']);
             exit;
        }

        try {
            $pdo->beginTransaction();
            // Check if tag name exists
            $check = $pdo->prepare('SELECT id FROM tags WHERE name = :name');
            $check->execute([':name' => $name]);
            $existingId = $check->fetchColumn();

            if ($existingId) {
                $tagId = (int) $existingId;
            } else {
                $insTag = $pdo->prepare('INSERT INTO tags (name, category, definition) VALUES (:name, :category, :definition)');
                $insTag->execute([':name' => $name, ':category' => $category, ':definition' => $definition]);
                $tagId = (int) $pdo->lastInsertId();
            }

            // Insert quote directly
            $insQuote = $pdo->prepare('INSERT INTO article_tag_quotes (article_id, quote_text, comment) VALUES (:article_id, :quote_text, :comment)');
            $insQuote->execute([':article_id' => $id, ':quote_text' => $quote, ':comment' => $comment]);
            $quoteId = (int) $pdo->lastInsertId();

            $insLink = $pdo->prepare('INSERT INTO article_quote_tags (quote_id, tag_id) VALUES (:quote_id, :tag_id) ON CONFLICT DO NOTHING');
            $insLink->execute([':quote_id' => $quoteId, ':tag_id' => $tagId]);

            $pdo->commit();
            echo json_encode(['success' => true, 'tag_id' => $tagId] + article_tags_payload($pdo, $id, can_edit_content()));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete_article') {
        if (!is_admin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Apenas administradores podem excluir artigos.']);
            exit;
        }
        try {
            $del = $pdo->prepare('DELETE FROM articles WHERE id = :id');
            $del->execute([':id' => $id]);
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'set_active_project') {
        $projId = (int) ($_POST['project_id'] ?? 0);
        $_SESSION['active_project_id'] = $projId;
        echo json_encode(['success' => true] + article_tags_payload($pdo, $id, can_edit_content()));
        exit;
    }

    if ($action === 'link_note_to_section') {
        $noteId = (int) ($_POST['note_id'] ?? 0);
        $sectionId = (int) ($_POST['section_id'] ?? 0);
        $activeProjectId = (int) ($_SESSION['active_project_id'] ?? 0);

        if ($activeProjectId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Nenhum projeto ativo selecionado.']);
            exit;
        }
        if ($noteId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Nota inválida.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Verify note belongs to this article
            $noteStmt = $pdo->prepare('SELECT id FROM article_tag_quotes WHERE id = :id AND article_id = :article_id LIMIT 1');
            $noteStmt->execute([':id' => $noteId, ':article_id' => $id]);
            if (!$noteStmt->fetchColumn()) {
                throw new RuntimeException('Nota não encontrada para este artigo.');
            }

            // If sectionId is 0, find/create "Geral" section
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
                // Verify section belongs to active project
                $sectCheck = $pdo->prepare('SELECT id FROM project_sections WHERE id = :id AND project_id = :project_id LIMIT 1');
                $sectCheck->execute([':id' => $sectionId, ':project_id' => $activeProjectId]);
                if (!$sectCheck->fetchColumn()) {
                    throw new RuntimeException('Seção não encontrada neste projeto.');
                }
            }

            // Link note to section (ON CONFLICT DO NOTHING)
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
            echo json_encode(['success' => true] + article_tags_payload($pdo, $id, can_edit_content()));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'create_section_and_link_note') {
        $noteId = (int) ($_POST['note_id'] ?? 0);
        $sectionTitle = trim((string) ($_POST['section_title'] ?? ''));
        $activeProjectId = (int) ($_SESSION['active_project_id'] ?? 0);

        if ($activeProjectId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Nenhum projeto ativo selecionado.']);
            exit;
        }
        if ($noteId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Nota inválida.']);
            exit;
        }
        if ($sectionTitle === '') {
            echo json_encode(['success' => false, 'error' => 'Título da seção é obrigatório.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Verify note belongs to this article
            $noteStmt = $pdo->prepare('SELECT id FROM article_tag_quotes WHERE id = :id AND article_id = :article_id LIMIT 1');
            $noteStmt->execute([':id' => $noteId, ':article_id' => $id]);
            if (!$noteStmt->fetchColumn()) {
                throw new RuntimeException('Nota não encontrada para este artigo.');
            }

            // Create new section
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

            // Link note to section
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
            echo json_encode(['success' => true] + article_tags_payload($pdo, $id, can_edit_content()));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'unlink_note_from_section') {
        $noteId = (int) ($_POST['note_id'] ?? 0);
        $sectionId = (int) ($_POST['section_id'] ?? 0);

        if ($noteId <= 0 || $sectionId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Dados inválidos.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM project_section_notes WHERE section_id = :section_id AND note_id = :note_id');
            $stmt->execute([':section_id' => $sectionId, ':note_id' => $noteId]);
            echo json_encode(['success' => true] + article_tags_payload($pdo, $id, can_edit_content()));
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

$stmt = $pdo->prepare('SELECT * FROM articles WHERE id = :id');
$stmt->execute([':id' => $id]);
$article = $stmt->fetch();

if (!$article) {
    http_response_code(404);
    exit('Artigo não encontrado.');
}

$canEditArticle = can_edit_content();
$canDeleteArticle = is_admin();
$currentUser = current_user();
$isLoggedIn = $currentUser !== null;
$currentPath = $_SERVER['REQUEST_URI'] ?? ('view.php?id=' . $id);
$loginUrl = access_url('login.php?next=' . rawurlencode($currentPath));

$articleTagsState = get_article_tags_state($pdo, $id);
$tags = $articleTagsState['tags'];
$articleNotesCount = count($articleTagsState['quotes']);
$viewTagsByCategory = $articleTagsState['view_tags_by_category'];
$headerTagsByCategory = $articleTagsState['header_tags_by_category'];
$availableTags = $articleTagsState['available_tags'];
$flatAvailableTags = $articleTagsState['flat_available_tags'];
$isTagsEmpty = $articleTagsState['is_empty'];
$allTagsQuery = $articleTagsState['flat_all_tags'];

$metadata = [
    'Autores' => $article['authors'] ?? '',
    'Ano' => $article['year'] ?? '',
    'Periódico ou fonte' => $article['journal'] ?? '',
    'Volume' => $article['volume'] ?? '',
    'No.' => $article['issue'] ?? '',
    'Páginas' => $article['pages'] ?? '',
    'Editora/base' => $article['publisher'] ?? '',
    'DOI' => $article['doi'] ?? '',
    'URL' => $article['url'] ?? '',
    'PDF' => $article['pdf_url'] ?? '',
];
if (is_logged_in()) {
    $metadata['Palavras (Texto Completo)'] = (string) count_words($article['full_text'] ?? '');
    $metadata['Palavras (Artigo Completo)'] = (string) count_words(implode(' ', [
        $article['title'] ?? '',
        $article['authors'] ?? '',
        $article['journal'] ?? '',
        $article['abstract'] ?? '',
        $article['keywords'] ?? '',
        $article['full_text'] ?? '',
        $article['references_text'] ?? ''
    ]));
} else {
    $metadata['Palavras (Metadados)'] = (string) count_words(implode(' ', [
        $article['title'] ?? '',
        $article['authors'] ?? '',
        $article['journal'] ?? '',
        $article['abstract'] ?? '',
        $article['keywords'] ?? '',
        $article['references_text'] ?? ''
    ]));
}

$hasFullText = trim((string) ($article['full_text'] ?? '')) !== '';
$isAnalysisEmpty = trim((string) ($article['analysis'] ?? '')) === '';
?>
<!doctype html>
<html lang="pt-br" data-module="fichario">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($article['title']) ?> - Fichário Acadêmico</title>
    <link rel="icon" type="image/png" href="../assets/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/app.css?v=20260615" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
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

        .text-block {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            word-break: break-word;
            line-height: 1.6;
            color: #d1d5db;
        }

        .full-text-box {
            flex-grow: 1;
            height: 0;
            overflow-y: auto;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255,255,255,0.05);
            padding: 20px;
            border-radius: 12px;
            font-family: inherit;
        }

        .metadata-value,
        .metadata-value a {
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .tag-badge {
            font-size: 0.72rem;
            padding: 0.35em 0.85em;
            border-radius: 99px;
            cursor: help;
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
            color: var(--text-muted);
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 0.15rem;
        }

        .note-meta {
            color: #94a3b8;
            font-size: 0.78rem;
        }

        .note-text {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            word-break: break-word;
            line-height: 1.5;
            font-size: 0.88rem;
        }


        .marking-modal-text {
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

        .form-control, .form-select {
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #f3f4f6;
            border-radius: 10px;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            background-color: rgba(255, 255, 255, 0.08);
            border-color: #3b82f6;
            color: #ffffff;
            box-shadow: 0 0 10px var(--color-primary-glow);
        }

        .form-select option {
            background-color: #121528;
            color: #f3f4f6;
        }

        .form-label {
            color: #9ca3af;
            font-weight: 500;
        }

        .nav-tabs {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .nav-tabs .nav-link {
            color: #9ca3af;
            border: none;
            border-bottom: 2px solid transparent;
            font-weight: 500;
            padding: 0.75rem 1.2rem;
            transition: all 0.2s;
        }

        .nav-tabs .nav-link:hover {
            color: #ffffff;
            border-bottom-color: rgba(255, 255, 255, 0.2);
            background: none;
        }

        .nav-tabs .nav-link.active {
            color: #3b82f6;
            background: none;
            border: none;
            border-bottom: 2px solid #3b82f6;
        }

        .autosave-status {
            font-size: 0.8rem;
            color: #9ca3af;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .main-container {
            position: relative;
            z-index: 10;
        }
.tag-row {
            transition: all 0.2s;
            border-radius: 8px;
        }

        .collapse-btn {
            width: 28px;
            height: 28px;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            color: #9ca3af;
            transition: all 0.2s ease;
            font-size: 0.75rem;
            cursor: pointer;
            user-select: none;
        }
        .collapse-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.25);
            color: #ffffff;
            transform: scale(1.1);
        }

        #normal-tabs-card {
            transition: background-color 0.4s ease, color 0.4s ease, border-color 0.4s ease;
            min-height: 600px;
        }
        #normal-tabs-card:not(.d-none) {
            display: flex !important;
            flex-direction: column;
        }
        #reading-tabs-content .tab-pane.active {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        /* Full Text Legibility Custom Styles (Embedded) */
        #focused-reading-card {
            transition: background-color 0.4s ease, color 0.4s ease, border-color 0.4s ease;
            min-height: 600px;
        }
        #focused-reading-card:not(.d-none) {
            display: flex !important;
            flex-direction: column;
        }
        #focused-reading-card.dark-mode {
            background-color: rgba(18, 21, 40, 0.95) !important;
            color: #f3f4f6 !important;
            border: 1px solid rgba(255, 255, 255, 0.12) !important;
        }
        #focused-reading-card.light-mode {
            background-color: #fcfaf2 !important;
            color: #1f2937 !important;
            border: 1px solid rgba(0, 0, 0, 0.15) !important;
        }
        #focused-reading-card.light-mode .text-white,
        #focused-reading-card.light-mode h5,
        #focused-reading-card.light-mode label {
            color: #111827 !important;
        }
        #focused-reading-card.light-mode .btn-outline-light {
            color: #1f2937 !important;
            border-color: #1f2937 !important;
        }
        #focused-reading-card.light-mode .btn-outline-light:hover {
            background-color: rgba(0, 0, 0, 0.05) !important;
        }
        #focused-reading-card.light-mode .btn-outline-dark {
            color: #1f2937 !important;
            border-color: #1f2937 !important;
        }
        #focused-reading-card.light-mode .btn-outline-dark:hover {
            background-color: rgba(0, 0, 0, 0.08) !important;
        }
        .reading-modal-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 1rem;
        }
        #focused-reading-card.light-mode .reading-modal-header {
            border-bottom-color: rgba(0, 0, 0, 0.1);
        }
        .reading-modal-body {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .reading-text-view {
            font-size: 1.25rem;
            line-height: 2.0;
            max-width: 100%;
            margin: 0;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            word-break: break-word;
            font-family: 'Outfit', sans-serif;
            font-weight: 300;

            /* Adaptive height updates */
            flex-grow: 1;
            height: 0;
            overflow-y: auto;
        }
        .reading-textarea {
            font-size: 1.15rem;
            line-height: 1.6;
            max-width: 100%;
            font-family: inherit;
            background-color: rgba(0, 0, 0, 0.3) !important;
            border: 1px solid #dac8b9 !important;
            color: #ffffff !important;
            border-radius: 12px;
            padding: 1.25rem;
            resize: none;
        }
        #focused-reading-card.light-mode .reading-textarea {
            background-color: #ffffff !important;
            color: #1f2937 !important;
            border: 1px solid rgba(0, 0, 0, 0.2) !important;
        }
        #focused-reading-card.light-mode .form-label {
            color: #4b5563 !important;
        }
        .btn-light-toggle {
            transition: all 0.3s;
        }

        /* Autocomplete dropdown list styling */
        .autocomplete-dropdown {
            background: rgba(18, 21, 40, 0.98) !important;
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.12) !important;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            max-height: 220px;
            overflow-y: auto;
            position: absolute;
            width: 100%;
            z-index: 1100;
            margin-top: 4px;
        }
        .autocomplete-item {
            padding: 10px 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #5a4b43;
            font-size: 0.875rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        }
        .autocomplete-item:last-child {
            border-bottom: none;
        }
        .autocomplete-item:hover {
            background: rgba(59, 130, 246, 0.2);
            color: #ffffff;
        }
        .autocomplete-item.active {
            background: rgba(59, 130, 246, 0.35);
            color: #ffffff;
        }
        .locked-action {
            opacity: 0.62;
            cursor: not-allowed;
        }
        .locked-action:hover {
            opacity: 0.82;
        }
        .btn-alert-active {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
            color: #000000 !important;
            border: none !important;
            font-weight: bold;
            box-shadow: 0 0 10px rgba(245, 158, 11, 0.4);
            animation: pulseAlertGlow 2s infinite ease-in-out;
        }
        .btn-alert-active:hover {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%) !important;
            color: #000000 !important;
            box-shadow: 0 0 15px rgba(245, 158, 11, 0.6);
        }
        .btn-alert-inactive {
            background: transparent !important;
            color: #9ca3af !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            font-weight: bold;
        }
        .btn-alert-inactive:hover {
            color: #ffffff !important;
            background: rgba(255, 255, 255, 0.05) !important;
            border-color: rgba(255, 255, 255, 0.3) !important;
        }
        @keyframes pulseAlertGlow {
            0% {
                box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7);
            }
            70% {
                box-shadow: 0 0 0 8px rgba(245, 158, 11, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(245, 158, 11, 0);
            }
        }
    </style>

</head>
<body>
    <!-- Background Animated Blobs -->
    <div class="blob blob-blue"></div>
    <div class="blob blob-purple"></div>

    <?php render_navbar('articles'); ?>

    <main class="container py-4 main-container">
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Fichário</a></li>
                <li class="breadcrumb-item"><a href="articles.php">Artigos</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page"><?= h($article['title']) ?></li>
            </ol>
        </nav>

        <!-- Top bar/Header -->
        <header class="glass-card p-4 mb-4">
            <div class="d-flex flex-column gap-3">
                <div>
                    <h1 class="h3 mb-2 text-white fw-bold"><?= h($article['title']) ?></h1>
                    <p class="text-secondary mb-3">
                        <strong><?= h($article['authors']) ?></strong>
                        <?php if ((string) ($article['year'] ?? '') !== ''): ?>
                            <span class="mx-2">|</span><?= h((string) $article['year']) ?>
                        <?php endif; ?>
                        <?php if ((string) ($article['journal'] ?? '') !== ''): ?>
                            <span class="mx-2">|</span><em class="text-white-50"><?= h($article['journal']) ?></em>
                        <?php endif; ?>
                        <?php 
                            $hasStart = !empty($article['data_year_start']);
                            $hasEnd = !empty($article['data_year_end']);
                        ?>
                        <?php if ($hasStart || $hasEnd): ?>
                            <span class="mx-2">|</span>
                            <span class="text-white-50" title="Período dos dados de coleta">
                                📅 Dados: 
                                <?php if ($hasStart && $hasEnd): ?>
                                    <?= h((string) $article['data_year_start']) ?> a <?= h((string) $article['data_year_end']) ?>
                                <?php elseif ($hasStart): ?>
                                    a partir de <?= h((string) $article['data_year_start']) ?>
                                <?php else: ?>
                                    até <?= h((string) $article['data_year_end']) ?>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </p>


                    <!-- Linked tags at top -->
                    <?= render_article_header_tags($headerTagsByCategory) ?>
                </div>
                <div class="d-flex flex-wrap gap-2 justify-content-end align-items-center mt-2">
                    <a class="btn btn-outline-secondary text-white rounded-pill px-3" href="articles.php">Voltar</a>
                    <?php if (is_logged_in()): ?>
                        <button class="btn btn-outline-primary rounded-pill px-3 d-inline-flex align-items-center gap-1"
                                id="btn-reading-modal"
                                onclick="toggleFocusedReading()"
                                <?= $hasFullText ? '' : 'disabled title="Nenhum texto completo cadastrado para este artigo"' ?>>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3-3h7z"></path></svg>
                            Leitura Focada
                        </button>
                    <?php endif; ?>
                    <?php if (trim((string) ($article['pdf_url'] ?? '')) !== ''): ?>
                        <a class="btn btn-outline-primary rounded-pill px-3 d-inline-flex align-items-center gap-1"
                           href="<?= h($article['pdf_url']) ?>"
                           target="_blank"
                           rel="noopener noreferrer"
                           title="Abrir PDF original em nova aba">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                            Abrir PDF
                        </a>
                    <?php endif; ?>
                    <?php if (trim((string) ($article['url'] ?? '')) !== ''): ?>
                        <a class="btn btn-outline-primary rounded-pill px-3 d-inline-flex align-items-center gap-1"
                           href="<?= h($article['url']) ?>"
                           target="_blank"
                           rel="noopener noreferrer"
                           title="Abrir URL original em nova aba">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                            Abrir URL
                        </a>
                    <?php endif; ?>
                    <?php if ($canEditArticle): ?>
                        <a class="btn btn-outline-primary rounded-pill px-3" href="editor.php?edit=<?= h((string) $article['id']) ?>">Editar Metadados</a>
                    <?php else: ?>
                        <button class="btn btn-outline-primary rounded-pill px-3 locked-action" type="button" onclick="showAuthRequired()">Editar Metadados</button>
                    <?php endif; ?>
                    <?php if ($canDeleteArticle): ?>
                        <button class="btn btn-outline-danger rounded-pill px-3"
                                type="button"
                                data-article-notes-count="<?= $articleNotesCount ?>"
                                onclick="confirmDelete(this)">
                            Excluir Artigo
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <!-- Main Workspace: Split Pane -->
        <div class="row g-4">
            <!-- Left Panel: Reading Pane -->
            <section class="col-lg-7 order-2 order-lg-1 d-flex flex-column">
                <!-- Normal Tabs Card -->
                <div class="glass-card p-4 h-100" id="normal-tabs-card">
                    <nav>
                        <div class="nav nav-tabs mb-4" id="reading-tabs" role="tablist">
                            <button class="nav-link active" id="tab-meta-btn" data-bs-toggle="tab" data-bs-target="#tab-meta" type="button" role="tab" aria-controls="tab-meta" aria-selected="true">
                                Ficha Bibliográfica
                            </button>
                            <?php if (is_logged_in()): ?>
                                <button class="nav-link" id="tab-text-btn" data-bs-toggle="tab" data-bs-target="#tab-text" type="button" role="tab" aria-controls="tab-text" aria-selected="false">
                                    Texto Completo
                                </button>
                            <?php endif; ?>
                            <button class="nav-link" id="tab-refs-btn" data-bs-toggle="tab" data-bs-target="#tab-refs" type="button" role="tab" aria-controls="tab-refs" aria-selected="false">
                                Referências
                            </button>
                        </div>
                    </nav>

                    <div class="tab-content flex-grow-1 d-flex flex-column" id="reading-tabs-content">
                        <!-- Metadata Tab -->
                        <div class="tab-pane fade show active" id="tab-meta" role="tabpanel" aria-labelledby="tab-meta-btn">
                            <h2 class="h5 text-white mb-3">Dados Bibliográficos</h2>
                            <dl class="row border-bottom border-secondary pb-3 mb-4">
                                <?php foreach ($metadata as $label => $value): ?>
                                    <?php if (trim((string) $value) !== ''): ?>
                                        <dt class="col-sm-4 text-secondary small"><?= h($label) ?></dt>
                                        <dd class="col-sm-8 text-white metadata-value small">
                                            <?php if ($label === 'URL' || $label === 'PDF'): ?>
                                                <a href="<?= h((string) $value) ?>" target="_blank" rel="noopener noreferrer" class="text-primary"><?= h((string) $value) ?></a>
                                            <?php elseif ($label === 'DOI'): ?>
                                                <a href="https://doi.org/<?= h((string) $value) ?>" target="_blank" rel="noopener noreferrer" class="text-primary"><?= h((string) $value) ?></a>
                                            <?php else: ?>
                                                <?= h((string) $value) ?>
                                            <?php endif; ?>
                                        </dd>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </dl>

                            <?php if (trim((string) ($article['abstract'] ?? '')) !== ''): ?>
                                <div class="mb-4">
                                    <h3 class="h6 text-white fw-bold mb-2">Resumo / Abstract</h3>
                                    <div class="text-block small bg-black bg-opacity-20 p-3 rounded-3" style="border: 1px solid rgba(255,255,255,0.03);"><?= h($article['abstract']) ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if (trim((string) ($article['keywords'] ?? '')) !== ''): ?>
                                <div>
                                    <h3 class="h6 text-white fw-bold mb-2">Palavras-chave</h3>
                                    <div class="text-white-50 small"><?= h($article['keywords']) ?></div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Full Text Tab -->
                        <?php if (is_logged_in()): ?>
                            <div class="tab-pane fade" id="tab-text" role="tabpanel" aria-labelledby="tab-text-btn">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h2 class="h5 text-white mb-0">
                                        Texto Completo Bruto
                                        <?php if ($hasFullText): ?>
                                            <span class="badge bg-secondary ms-2" style="font-size: 0.75rem; font-weight: normal; opacity: 0.8;">
                                                <?= count_words($article['full_text'] ?? '') ?> palavras
                                            </span>
                                        <?php endif; ?>
                                    </h2>
                                    <?php if ($hasFullText): ?>
                                        <button class="btn btn-sm btn-outline-info rounded-pill px-3 d-inline-flex align-items-center gap-1" onclick="openReadingModal()">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3-3h7z"></path></svg>
                                            Modo Leitura Focada
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <?php if ($hasFullText): ?>
                                    <div class="full-text-box text-block small" id="full-text-display-pane"><?= h($article['full_text']) ?></div>
                                <?php else: ?>
                                    <div class="text-center py-5 text-secondary">
                                        <p class="mb-0">Nenhum texto completo cadastrado para este artigo.</p>
                                        <?php if ($canEditArticle): ?>
                                            <a href="editor.php?edit=<?= $article['id'] ?>" class="btn btn-sm btn-outline-primary mt-3 rounded-pill">Adicionar texto</a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-primary mt-3 rounded-pill locked-action" type="button" onclick="showAuthRequired()">Adicionar texto</button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- References Tab -->
                        <div class="tab-pane fade" id="tab-refs" role="tabpanel" aria-labelledby="tab-refs-btn">
                            <h2 class="h5 text-white mb-3">Referências Bibliográficas</h2>
                            <?php if (trim((string) ($article['references_text'] ?? '')) !== ''): ?>
                                <div class="full-text-box text-block small"><?= h($article['references_text']) ?></div>
                            <?php else: ?>
                                <p class="text-secondary small">Nenhuma referência cadastrada para este artigo.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Focused Reading Card (Embedded & Toggleable) -->
                <?php if (is_logged_in()): ?>
                    <div class="glass-card p-4 h-100 d-none dark-mode" id="focused-reading-card">
                        <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-secondary border-opacity-20 reading-modal-header">
                            <h5 class="fw-bold mb-0 text-white">Leitura Focada</h5>
                            <div class="d-flex align-items-center gap-2">
                                <button class="btn btn-sm btn-outline-light rounded-pill px-3 btn-light-toggle" id="btn-toggle-light" onclick="toggleReadingLight()">
                                                    <span>Apagar a Luz</span>
                                </button>
                                <?php if ($canEditArticle): ?>
                                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3" id="btn-toggle-edit" onclick="toggleReadingEditMode()">
                                        Editar Texto
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3 locked-action" id="btn-toggle-edit-locked" type="button" onclick="showAuthRequired()">
                                        Editar Texto
                                    </button>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 text-white" onclick="exitFocusedReading()">
                                    Voltar
                                </button>
                            </div>
                        </div>
                        <div class="reading-modal-body p-0">
                            <!-- Display mode -->
                            <div id="reading-view-container" class="reading-text-view mt-0" style="font-size: 1.15rem; line-height: 1.8; font-weight: 300;"></div>

                            <?php if ($canEditArticle): ?>
                            <!-- Edit mode -->
                            <div id="reading-edit-container" class="d-none max-width-100 flex-grow-1 flex-column">
                                <div class="mb-3 d-flex flex-column flex-grow-1">
                                    <label class="form-label fw-semibold mb-2" for="reading-full-text-textarea">Conteúdo do Texto Completo</label>
                                    <textarea class="form-control reading-textarea flex-grow-1" id="reading-full-text-textarea" placeholder="Digite ou cole o texto completo do artigo científico..."></textarea>
                                </div>
                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <button class="btn btn-outline-secondary text-white rounded-pill px-4" onclick="cancelReadingEdit()">Cancelar</button>
                                    <button class="btn btn-primary rounded-pill px-4" id="btn-save-full-text" onclick="saveFullTextFromModal()">Salvar Alterações</button>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Right Panel: Fichamento Workspace -->
            <section class="col-lg-5 order-1 order-lg-2 vstack gap-4">
                <!-- Overall Fichamento Card (Collapsible) -->
                <article class="glass-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3" onclick="toggleCollapseCard('fichamento-card-body', 'fichamento-collapse-icon')" style="cursor: pointer; user-select: none;">
                        <h2 class="h5 text-white fw-bold mb-0">Análise Geral / Síntese</h2>
                        <span id="fichamento-collapse-icon" class="collapse-btn"><?= $isAnalysisEmpty ? "+" : "-" ?></span>
                    </div>

                    <div id="fichamento-card-body" class="<?= $isAnalysisEmpty ? 'd-none' : '' ?>">
                        <div class="mb-3">
                            <textarea class="form-control" id="analysis" rows="12" placeholder="Escreva aqui a sua análise do artigo, fichando ideias principais..." <?= $canEditArticle ? '' : 'readonly' ?>><?= h($article['analysis'] ?? '') ?></textarea>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="autosave-status" id="save-status">
                                <svg width="12" height="12" fill="currentColor" viewBox="0 0 16 16" style="vertical-align:-1px; opacity:0.8;"><path d="M12.736 14H5.66L1.833 7.082l.746-.746.01.01L5.66 12.34l7.082-7.082.746.746-.01-.01L12.736 14zm-9.3-5.264l-.746-.746.01-.01 2.828-2.828.746.746-.01.01L3.436 8.736z"/></svg>
                                <span>Salvo</span>
                            </div>
                            <?php if ($canEditArticle): ?>
                                <button class="btn btn-sm btn-primary rounded-pill px-4" id="btn-save-analysis" onclick="triggerSave()">Salvar Análise</button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-primary rounded-pill px-4 locked-action" type="button" onclick="showAuthRequired()">Salvar Análise</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>

                <?= render_article_tags_panel($articleTagsState, $canEditArticle) ?>
</section>
        </div>
    </main>

    <div id="quote-context-menu" class="d-none position-fixed bg-dark border border-secondary rounded-3 shadow-lg py-1" style="z-index: 2000; min-width: 150px;">
        <button type="button" class="btn btn-sm btn-link text-white text-decoration-none w-100 text-start px-3" id="btn-context-copy-quote">Copiar</button>
        <button type="button" class="btn btn-sm btn-link text-white text-decoration-none w-100 text-start px-3<?= $canEditArticle ? '' : ' locked-action' ?>" id="btn-context-create-quote">Criar marcação</button>
    </div>

    <?php if ($canEditArticle): ?>
        <div class="modal fade" id="quoteModal" tabindex="-1" aria-labelledby="quoteModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="quoteModalLabel">Criar nota</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="quote-modal-tag-search">Tag existente</label>
                            <div class="position-relative">
                                <input type="text" class="form-control" id="quote-modal-tag-search" placeholder="Digite para buscar por tag ou agrupamento..." autocomplete="off">
                                <input type="hidden" id="quote-modal-tag-ids" value="">
                                <div id="quote-modal-tag-dropdown" class="autocomplete-dropdown d-none"></div>
                            </div>
                            <div class="d-flex flex-wrap gap-2 mt-2" id="quote-modal-selected-tags"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="quote-modal-text">Citação (opcional)</label>
                            <textarea class="form-control note-edit-textarea" id="quote-modal-text"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="quote-modal-comment">Observação (opcional)</label>
                            <textarea class="form-control" id="quote-modal-comment" rows="7" placeholder="Observação sobre o trecho"></textarea>
                        </div>
                        <div class="alert alert-danger d-none mt-3 mb-0" id="quote-modal-error"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary text-white rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary rounded-pill" id="btn-save-tag-quote">Salvar nota</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="modal fade" id="markingReadModal" tabindex="-1" aria-labelledby="markingReadModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="markingReadModalLabel">Leitura da nota</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex flex-wrap gap-2 mb-3" id="marking-read-tags"></div>
                    <section class="mb-3 d-none" id="marking-read-quote-section">
                        <h6 class="text-secondary text-uppercase small mb-2">Citação</h6>
                        <div class="marking-modal-text text-white p-3 rounded-3 bg-black bg-opacity-25" id="marking-read-quote"></div>
                    </section>
                    <section class="mb-0 d-none" id="marking-read-comment-section">
                        <h6 class="text-secondary text-uppercase small mb-2">Observação</h6>
                        <div class="marking-modal-text text-white-50 p-3 rounded-3 bg-black bg-opacity-25" id="marking-read-comment"></div>
                    </section>
                    <div class="text-warning d-none" id="marking-read-empty">! Marcação sem citação ou observação.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary text-white rounded-pill" data-bs-dismiss="modal">Fechar</button>
                    <?php if ($canEditArticle): ?>
                        <button type="button" class="btn btn-primary rounded-pill" id="btn-edit-marking-from-read">Editar</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="authRequiredModal" tabindex="-1" aria-labelledby="authRequiredModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="authRequiredModalLabel">Acesso necessário</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <?php if ($isLoggedIn): ?>
                        <p class="mb-0">Seu usuário não tem permissão para editar fichamentos. Fale com um administrador para solicitar acesso de editor.</p>
                    <?php else: ?>
                        <p class="mb-0">Entre na sua conta para criar citações, vincular tags e editar fichamentos. Se ainda não tiver acesso, solicite o cadastro ao administrador.</p>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary text-white rounded-pill" data-bs-dismiss="modal">Agora não</button>
                    <?php if (!$isLoggedIn): ?>
                        <a class="btn btn-primary rounded-pill" href="<?= h($loginUrl) ?>">Entrar</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/app.js?v=20260615"></script>
    <script>
        const csrfToken = '<?= h(csrf_token()) ?>';
        const canEditArticle = <?= $canEditArticle ? 'true' : 'false' ?>;
        const ajaxFormHeaders = {
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest'
        };
        const authRequiredModalEl = document.getElementById('authRequiredModal');
        const authRequiredModal = authRequiredModalEl ? new bootstrap.Modal(authRequiredModalEl) : null;

        function showAuthRequired() {
            if (authRequiredModal) {
                authRequiredModal.show();
            }
        }
        function initTagTooltips(scope = document) {
            scope.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
                bootstrap.Tooltip.getOrCreateInstance(el);
            });
        }

        initTagTooltips();

        // Disable automatic scroll restoration by the browser
        if ('scrollRestoration' in history) {
            history.scrollRestoration = 'manual';
        }

        // Restore state on load (tabs, reading mode, collapse cards, scroll position)
        document.addEventListener('DOMContentLoaded', () => {
            // Restore active tab
            const activeTabId = sessionStorage.getItem('activeTabId');
            if (activeTabId) {
                const tabTriggerEl = document.getElementById(activeTabId);
                if (tabTriggerEl) {
                    const tab = bootstrap.Tab.getOrCreateInstance(tabTriggerEl);
                    tab.show();
                }
            }

            // Restore focused reading mode
            if (sessionStorage.getItem('focusedReadingActive') === '1') {
                openReadingModal();
            }

            // Restore card collapse states
            const isAnalysisEmpty = <?= $isAnalysisEmpty ? 'true' : 'false' ?>;
            const isTagsEmpty = <?= $isTagsEmpty ? 'true' : 'false' ?>;

            const fichamentoCollapsed = sessionStorage.getItem('fichamento-card-body_collapsed');
            if (isAnalysisEmpty || fichamentoCollapsed === '1') {
                const body = document.getElementById('fichamento-card-body');
                const icon = document.getElementById('fichamento-collapse-icon');
                if (body && icon) {
                    body.classList.add('d-none');
                    icon.textContent = '+';
                }
            } else if (fichamentoCollapsed === '0') {
                const body = document.getElementById('fichamento-card-body');
                const icon = document.getElementById('fichamento-collapse-icon');
                if (body && icon) {
                    body.classList.remove('d-none');
                    icon.textContent = '+';
                }
            }

            const tagsCollapsed = sessionStorage.getItem('tags-card-body_collapsed');
            if (isTagsEmpty || tagsCollapsed === '1') {
                const body = document.getElementById('tags-card-body');
                const icon = document.getElementById('tags-collapse-icon');
                if (body && icon) {
                    body.classList.add('d-none');
                    icon.textContent = '+';
                }
            } else if (tagsCollapsed === '0') {
                const body = document.getElementById('tags-card-body');
                const icon = document.getElementById('tags-collapse-icon');
                if (body && icon) {
                    body.classList.remove('d-none');
                    icon.textContent = '+';
                }
            }

            // Restore tab change listener
            const tabElements = document.querySelectorAll('button[data-bs-toggle="tab"]');
            tabElements.forEach(tabEl => {
                tabEl.addEventListener('shown.bs.tab', (event) => {
                    sessionStorage.setItem('activeTabId', event.target.id);
                });
            });

            // Restore scroll position
            const scrollPos = sessionStorage.getItem('scrollPosition');
            if (scrollPos) {
                window.scrollTo(0, parseInt(scrollPos, 10));
                setTimeout(() => {
                    window.scrollTo(0, parseInt(scrollPos, 10));
                }, 50);
                sessionStorage.removeItem('scrollPosition');
            }
        });

        // Save scroll position before unload
        window.addEventListener('beforeunload', () => {
            sessionStorage.setItem('scrollPosition', window.scrollY);
        });

        // Save logic for Fichamento
        const articleId = <?= $id ?>;
        const analysisEl = document.getElementById('analysis');
        const saveStatusEl = document.getElementById('save-status');
        const btnSaveAnalysis = document.getElementById('btn-save-analysis');

        function triggerSave() {
            const releaseSaveAnalysisBusy = btnSaveAnalysis && window.FicharioUI
                ? FicharioUI.setBusy(btnSaveAnalysis, true, 'Salvando...')
                : () => {};
            if (btnSaveAnalysis) {
                btnSaveAnalysis.disabled = true;
            }
            saveStatusEl.style.opacity = '0.5';
            saveStatusEl.querySelector('span').textContent = 'Salvando...';

            const formData = new URLSearchParams();
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'save_fichamento');
            formData.append('analysis', analysisEl.value);

            fetch(`view.php?id=${articleId}`, {
                method: 'POST',
                headers: ajaxFormHeaders,
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                releaseSaveAnalysisBusy();
                if (data.success) {
                    saveStatusEl.style.opacity = '1';
                    saveStatusEl.querySelector('span').textContent = 'Salvo';
                    saveStatusEl.style.color = ''; // Reset to default CSS color
                } else {
                    saveStatusEl.style.opacity = '1';
                    saveStatusEl.querySelector('span').textContent = 'Erro';
                    saveStatusEl.style.color = '#f87171'; // light red
                    console.error(data.error);
                }
            })
            .catch(err => {
                releaseSaveAnalysisBusy();
                saveStatusEl.style.opacity = '1';
                saveStatusEl.querySelector('span').textContent = 'Falha de rede';
                saveStatusEl.style.color = '#f87171'; // light red
                console.error(err);
            });
        }

        function markUnsaved() {
            saveStatusEl.style.opacity = '1';
            saveStatusEl.querySelector('span').textContent = 'Alterações pendentes';
            saveStatusEl.style.color = '#fbbf24'; // Amber
        }

        analysisEl.addEventListener('input', markUnsaved);

        // Collapsible Cards Toggle (Saves state in sessionStorage)
        function toggleCollapseCard(bodyId, iconId) {
            const body = document.getElementById(bodyId);
            const icon = document.getElementById(iconId);
            if (body.classList.contains('d-none')) {
                body.classList.remove('d-none');
                icon.textContent = '+';
                sessionStorage.setItem(bodyId + '_collapsed', '0');
            } else {
                body.classList.add('d-none');
                icon.textContent = '+';
                sessionStorage.setItem(bodyId + '_collapsed', '1');
            }
        }

        // UI Panel Toggles
        // UI Panel Toggles
        function toggleLinkPanel() {
            const createPanel = document.getElementById('create-tag-panel');
            if (createPanel) {
                createPanel.classList.add('d-none');
            }
            const linkPanel = document.getElementById('link-tag-panel');
            if (!linkPanel) {
                return;
            }
            linkPanel.classList.toggle('d-none');
            if (!linkPanel.classList.contains('d-none')) {
                const searchInp = document.getElementById('search-tag-link-input');
                if (searchInp) {
                    searchInp.value = '';
                    selectedLinkTags = [];
                    renderSelectedLinkTags();
                    searchInp.focus();
                }
            }
        }

        function toggleCreatePanel() {
            const linkPanel = document.getElementById('link-tag-panel');
            const createPanel = document.getElementById('create-tag-panel');
            if (linkPanel) {
                linkPanel.classList.add('d-none');
            }
            if (createPanel) {
                createPanel.classList.toggle('d-none');
            }
        }

        // Helper function for diacritic-insensitive search
        function normalizeStr(str) {
            return (str || '').normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim();
        }

        function tagCategoryRank(category) {
            const normalized = normalizeStr(category);
            if (normalized === 'metodo') return 1;
            if (normalized === 'fonte') return 2;
            if (normalized === 'tema') return 3;
            return 4;
        }

        function compareTagObjects(left, right) {
            const rankDiff = tagCategoryRank(left.category) - tagCategoryRank(right.category);
            if (rankDiff !== 0) return rankDiff;

            const leftCategory = normalizeStr(left.category);
            const rightCategory = normalizeStr(right.category);
            if (leftCategory !== rightCategory) {
                return leftCategory < rightCategory ? -1 : 1;
            }

            const leftName = normalizeStr(left.name);
            const rightName = normalizeStr(right.name);
            if (leftName === rightName) return 0;
            return leftName < rightName ? -1 : 1;
        }

        // Tag Autocomplete Link Selector
        let availableTags = <?= json_encode($flatAvailableTags) ?>;
        let allTagsForQuote = <?= json_encode(array_map(static function (array $tag): array {
            $category = trim($tag['category'] ?? '') !== '' ? $tag['category'] : 'Outros';
            return [
                'id' => (int) $tag['id'],
                'name' => $tag['name'],
                'category' => $category,
            ];
        }, $allTagsQuery), JSON_UNESCAPED_UNICODE) ?>;
        let indexedQuoteTags = allTagsForQuote.map(tag => ({
            ...tag,
            search: normalizeStr(`${tag.name} ${tag.category}`)
        }));
        let quoteTagMatches = [];
        let selectedQuoteTagIndex = -1;
        let selectedQuoteTags = [];
        let selectedLinkTags = [];
        const searchInput = document.getElementById('search-tag-link-input');
        const dropdown = document.getElementById('autocomplete-tag-dropdown');
        const hiddenTagIds = document.getElementById('select-tag-ids');
        let selectedDropdownIndex = -1;
        let filteredTags = [];

        function renderDropdown(tagsList) {
            filteredTags = tagsList;
            dropdown.innerHTML = '';
            selectedDropdownIndex = -1;

            if (tagsList.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'autocomplete-item text-secondary style-italic';
                empty.textContent = 'Nenhuma tag disponível encontrada';
                dropdown.appendChild(empty);
                return;
            }

            tagsList.forEach((tag, idx) => {
                const item = document.createElement('div');
                item.className = 'autocomplete-item d-flex justify-content-between align-items-center';
                item.setAttribute('data-id', tag.id);
                item.setAttribute('data-idx', idx);

                const catColors = {
                    'Tema': 'background: #ead6c7; color: #2b211c; border: 1px solid #dac8b9;',
                    'Método': 'background: #fff8f1; color: #5a4b43; border: 1px solid #dac8b9;',
                    'Fonte': 'background: #e8f0e5; color: #2f6f40; border: 1px solid #b8cdb0;'
                };
                const style = catColors[tag.category] || 'background: rgba(255,255,255,0.06); color: #5a4b43; border: 1px solid rgba(255,255,255,0.15);';

                const catSpan = `<span class="badge border" style="${style} font-size:0.65rem;">${tag.category}</span>`;
                item.innerHTML = `<strong>${tag.name}</strong> ${catSpan}`;

                item.addEventListener('click', () => {
                    selectTag(tag);
                });
                dropdown.appendChild(item);
            });
        }

        function selectTag(tag) {
            if (!selectedLinkTags.some(selected => Number(selected.id) === Number(tag.id))) {
                selectedLinkTags.push(tag);
            }
            const currentSearchInput = document.getElementById('search-tag-link-input');
            const currentDropdown = document.getElementById('autocomplete-tag-dropdown');
            if (currentSearchInput) {
                currentSearchInput.value = '';
            }
            renderSelectedLinkTags();
            if (currentDropdown) {
                currentDropdown.classList.add('d-none');
            }
        }

        function removeSelectedLinkTag(tagId) {
            selectedLinkTags = selectedLinkTags.filter(tag => Number(tag.id) !== Number(tagId));
            renderSelectedLinkTags();
        }

        function renderSelectedLinkTags() {
            const holder = document.getElementById('link-selected-tags');
            const hidden = document.getElementById('select-tag-ids');
            if (!holder || !hidden) return;

            const orderedTags = [...selectedLinkTags].sort(compareTagObjects);
            hidden.value = orderedTags.map(tag => tag.id).join(',');
            holder.innerHTML = '';

            orderedTags.forEach((tag) => {
                const chip = document.createElement('span');
                chip.className = 'badge border tag-badge d-inline-flex align-items-center gap-2';
                chip.title = tag.definition || 'Sem definição cadastrada.';
                chip.dataset.bsToggle = 'tooltip';
                chip.dataset.bsPlacement = 'top';
                chip.dataset.bsTitle = chip.title;
                chip.innerHTML = `<span>${tag.name}</span><button type="button" class="btn btn-sm btn-link text-white p-0 lh-1" aria-label="Remover tag">&times;</button>`;
                chip.querySelector('button').addEventListener('click', () => removeSelectedLinkTag(tag.id));
                holder.appendChild(chip);
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                const q = normalizeStr(searchInput.value);
                dropdown.classList.remove('d-none');

                const matches = availableTags
                    .filter(tag => !selectedLinkTags.some(selected => Number(selected.id) === Number(tag.id)))
                    .filter(tag => q === '' || normalizeStr(tag.name).includes(q) || normalizeStr(tag.category).includes(q));

                renderDropdown(matches);
            });

            searchInput.addEventListener('focus', () => {
                const q = normalizeStr(searchInput.value);
                dropdown.classList.remove('d-none');
                const matches = availableTags
                    .filter(tag => !selectedLinkTags.some(selected => Number(selected.id) === Number(tag.id)))
                    .filter(tag => q === '' || normalizeStr(tag.name).includes(q) || normalizeStr(tag.category).includes(q));
                renderDropdown(matches);
            });

            document.addEventListener('click', (e) => {
                if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.add('d-none');
                }
            });

            searchInput.addEventListener('keydown', (e) => {
                const items = dropdown.querySelectorAll('.autocomplete-item');
                if (dropdown.classList.contains('d-none')) {
                    if (e.key === 'ArrowDown') {
                        dropdown.classList.remove('d-none');
                    }
                    return;
                }

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (selectedDropdownIndex < items.length - 1) {
                        selectedDropdownIndex++;
                        updateActiveItem(items);
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (selectedDropdownIndex > 0) {
                        selectedDropdownIndex--;
                        updateActiveItem(items);
                    }
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (selectedDropdownIndex >= 0 && selectedDropdownIndex < filteredTags.length) {
                        selectTag(filteredTags[selectedDropdownIndex]);
                    }
                } else if (e.key === 'Escape') {
                    dropdown.classList.add('d-none');
                    searchInput.blur();
                }
            });
        }

        function updateActiveItem(items) {
            items.forEach((item, idx) => {
                if (idx === selectedDropdownIndex) {
                    item.classList.add('active');
                    item.scrollIntoView({ block: 'nearest' });
                } else {
                    item.classList.remove('active');
                }
            });
        }

        function updateTagsPanel(data) {
            if (data.available_tags) {
                availableTags = data.available_tags;
            }
            if (data.all_tags) {
                allTagsForQuote = data.all_tags;
                indexedQuoteTags = allTagsForQuote.map(tag => ({
                    ...tag,
                    search: normalizeStr(`${tag.name} ${tag.category}`)
                }));
            }

            if (data.tags_panel_html) {
                const panel = document.getElementById('article-tags-panel');
                if (panel) {
                    panel.outerHTML = data.tags_panel_html;
                }
            }

            if (data.header_tags_html) {
                const headerTags = document.getElementById('article-header-tags');
                if (headerTags) {
                    const alertsContainer = headerTags.querySelector('#article-alerts-container');
                    if (alertsContainer) {
                        const inst = bootstrap.Tooltip.getInstance(alertsContainer);
                        if (inst) {
                            inst.dispose();
                        }
                    }
                    headerTags.outerHTML = data.header_tags_html;
                }
            }

            initTagTooltips();

            const body = document.getElementById('tags-card-body');
            const icon = document.getElementById('tags-collapse-icon');
            const collapsed = sessionStorage.getItem('tags-card-body_collapsed') === '1';
            if (body && icon && collapsed) {
                body.classList.add('d-none');
                icon.textContent = '+';
            }
        }

        function renderCurrentTagDropdown(query = '') {
            const currentDropdown = document.getElementById('autocomplete-tag-dropdown');
            if (!currentDropdown) return;

            const q = normalizeStr(query);
            currentDropdown.classList.remove('d-none');
            currentDropdown.innerHTML = '';

            const matches = availableTags
                .filter(tag => !selectedLinkTags.some(selected => Number(selected.id) === Number(tag.id)))
                .filter(tag => q === '' || normalizeStr(tag.name).includes(q) || normalizeStr(tag.category).includes(q));

            if (matches.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'autocomplete-item text-secondary style-italic';
                empty.textContent = 'Nenhuma tag disponível encontrada';
                currentDropdown.appendChild(empty);
                return;
            }

            matches.forEach((tag) => {
                const item = document.createElement('div');
                item.className = 'autocomplete-item d-flex justify-content-between align-items-center';
                item.innerHTML = `<strong>${tag.name}</strong> <span class="badge border" style="font-size:0.65rem;">${tag.category}</span>`;
                item.addEventListener('click', () => {
                    selectTag(tag);
                    currentDropdown.classList.add('d-none');
                });
                currentDropdown.appendChild(item);
            });
        }

        document.addEventListener('input', (event) => {
            if (event.target && event.target.id === 'search-tag-link-input') {
                renderCurrentTagDropdown(event.target.value);
            }
        });

        document.addEventListener('focusin', (event) => {
            if (event.target && event.target.id === 'search-tag-link-input') {
                renderCurrentTagDropdown(event.target.value);
            }
        });

        function renderQuoteTagDropdown(query = '') {
            const input = document.getElementById('quote-modal-tag-search');
            const hidden = document.getElementById('quote-modal-tag-ids');
            const dropdown = document.getElementById('quote-modal-tag-dropdown');
            if (!input || !hidden || !dropdown) return;

            const q = normalizeStr(query);
            selectedQuoteTagIndex = -1;
            dropdown.classList.remove('d-none');
            dropdown.innerHTML = '';

            quoteTagMatches = indexedQuoteTags
                .filter(tag => !selectedQuoteTags.some(selected => Number(selected.id) === Number(tag.id)))
                .filter(tag => q === '' || tag.search.includes(q))
                .slice(0, 25);

            if (quoteTagMatches.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'autocomplete-item text-secondary';
                empty.textContent = 'Nenhuma tag encontrada';
                dropdown.appendChild(empty);
                return;
            }

            quoteTagMatches.forEach((tag) => {
                const item = document.createElement('div');
                item.className = 'autocomplete-item d-flex justify-content-between align-items-center';
                item.innerHTML = `<strong>${tag.name}</strong> <span class="badge border" style="font-size:0.65rem;">${tag.category}</span>`;
                item.addEventListener('click', () => {
                    selectQuoteTag(tag);
                });
                dropdown.appendChild(item);
            });
        }

        function selectQuoteTag(tag) {
            const input = document.getElementById('quote-modal-tag-search');
            const dropdown = document.getElementById('quote-modal-tag-dropdown');
            if (!input || !dropdown) return;

            if (!selectedQuoteTags.some(selected => Number(selected.id) === Number(tag.id))) {
                selectedQuoteTags.push(tag);
            }

            input.value = '';
            dropdown.classList.add('d-none');
            renderSelectedQuoteTags();
        }

        function removeSelectedQuoteTag(tagId) {
            selectedQuoteTags = selectedQuoteTags.filter(tag => Number(tag.id) !== Number(tagId));
            renderSelectedQuoteTags();
        }

        function renderSelectedQuoteTags() {
            const holder = document.getElementById('quote-modal-selected-tags');
            const hidden = document.getElementById('quote-modal-tag-ids');
            if (!holder || !hidden) return;

            const orderedTags = [...selectedQuoteTags].sort(compareTagObjects);
            hidden.value = orderedTags.map(tag => tag.id).join(',');
            holder.innerHTML = '';

            orderedTags.forEach((tag) => {
                const chip = document.createElement('span');
                chip.className = 'badge border tag-badge d-inline-flex align-items-center gap-2';
                chip.title = tag.definition || 'Sem definição cadastrada.';
                chip.dataset.bsToggle = 'tooltip';
                chip.dataset.bsPlacement = 'top';
                chip.dataset.bsTitle = chip.title;
                chip.innerHTML = `<span>${tag.name}</span><button type="button" class="btn btn-sm btn-link text-white p-0 lh-1" aria-label="Remover tag">&times;</button>`;
                chip.querySelector('button').addEventListener('click', () => removeSelectedQuoteTag(tag.id));
                holder.appendChild(chip);
            });
        }

        function updateActiveQuoteTagItem(items) {
            items.forEach((item, idx) => {
                if (idx === selectedQuoteTagIndex) {
                    item.classList.add('active');
                    item.scrollIntoView({ block: 'nearest' });
                } else {
                    item.classList.remove('active');
                }
            });
        }

        document.addEventListener('input', (event) => {
            if (event.target && event.target.id === 'quote-modal-tag-search') {
                renderQuoteTagDropdown(event.target.value);
            }
        });

        document.addEventListener('focusin', (event) => {
            if (event.target && event.target.id === 'quote-modal-tag-search') {
                renderQuoteTagDropdown(event.target.value);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (!event.target || event.target.id !== 'quote-modal-tag-search') {
                return;
            }

            const dropdown = document.getElementById('quote-modal-tag-dropdown');
            if (!dropdown || dropdown.classList.contains('d-none')) {
                return;
            }

            const items = dropdown.querySelectorAll('.autocomplete-item');
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                selectedQuoteTagIndex = Math.min(selectedQuoteTagIndex + 1, quoteTagMatches.length - 1);
                updateActiveQuoteTagItem(items);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                selectedQuoteTagIndex = Math.max(selectedQuoteTagIndex - 1, 0);
                updateActiveQuoteTagItem(items);
            } else if (event.key === 'Enter') {
                event.preventDefault();
                if (selectedQuoteTagIndex >= 0 && quoteTagMatches[selectedQuoteTagIndex]) {
                    selectQuoteTag(quoteTagMatches[selectedQuoteTagIndex]);
                } else if (quoteTagMatches.length === 1) {
                    selectQuoteTag(quoteTagMatches[0]);
                }
            } else if (event.key === 'Escape') {
                dropdown.classList.add('d-none');
            }
        });

        function toggleEditComment(tagId) {
            document.getElementById(`comment-display-${tagId}`).classList.toggle('d-none');
            document.getElementById(`comment-edit-${tagId}`).classList.toggle('d-none');
            const quoteEl = document.getElementById(`input-quote-${tagId}`);
            if (quoteEl) {
                quoteEl.focus();
            } else {
                document.getElementById(`input-comment-${tagId}`).focus();
            }
        }

        function toggleCommentExpand(tagId) {
            const el = document.getElementById(`comment-text-${tagId}`);
            if (!el) return;
            const isExpanded = el.dataset.expanded === '1';
            if (isExpanded) {
                el.style.webkitLineClamp = '3';
                el.style.display = '-webkit-box';
                el.dataset.expanded = '0';
                el.title = 'Clique para expandir/recolher';
            } else {
                el.style.webkitLineClamp = 'unset';
                el.style.display = 'block';
                el.dataset.expanded = '1';
                el.title = 'Clique para recolher';
            }
        }

        // Action controllers using fetch
        function submitLinkTag() {
            const tagIds = document.getElementById('select-tag-ids').value;
            const quote = document.getElementById('link-quote').value;
            const comment = document.getElementById('link-comment').value;

            if (!tagIds) {
                alert('Selecione pelo menos uma tag.');
                return;
            }

            const formData = new URLSearchParams();
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'link_tag');
            formData.append('tag_ids', tagIds);
            formData.append('quote', quote);
            formData.append('comment', comment);

            fetch(`view.php?id=${articleId}`, {
                method: 'POST',
                headers: ajaxFormHeaders,
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    selectedLinkTags = [];
                    updateTagsPanel(data);
                } else {
                    alert('Erro: ' + data.error);
                }
            });
        }

        async function unlinkTag(tagId, tagName) {
            const ok = await FicharioUI.confirm({
                title: 'Remover vínculo',
                message: `Remover o vínculo da tag "${tagName}" deste artigo?`,
                confirmText: 'Remover vínculo',
                variant: 'warning'
            });
            if (!ok) return;

            const formData = new URLSearchParams();
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'unlink_tag');
            formData.append('tag_id', tagId);

            fetch(`view.php?id=${articleId}`, {
                method: 'POST',
                headers: ajaxFormHeaders,
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateTagsPanel(data);
                } else {
                    alert('Erro: ' + data.error);
                }
            });
        }

        function saveTagComment(tagId) {
            const quote = document.getElementById(`input-quote-${tagId}`).value;
            const comment = document.getElementById(`input-comment-${tagId}`).value;

            const formData = new URLSearchParams();
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'update_tag_comment');
            formData.append('tag_id', tagId);
            formData.append('quote', quote);
            formData.append('comment', comment);

            fetch(`view.php?id=${articleId}`, {
                method: 'POST',
                headers: ajaxFormHeaders,
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateTagsPanel(data);
                } else {
                    alert('Erro: ' + data.error);
                }
            });
        }

        function submitCreateAndLinkTag() {
            const name = document.getElementById('create-tag-name').value.trim();
            const category = document.getElementById('create-tag-category').value;
            const definition = document.getElementById('create-tag-definition').value;
            const quote = document.getElementById('create-tag-quote').value;
            const comment = document.getElementById('create-tag-comment').value;

            if (!name) {
                alert('O nome da tag é obrigatório.');
                return;
            }

            const formData = new URLSearchParams();
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'create_and_link_tag');
            formData.append('name', name);
            formData.append('category', category);
            formData.append('definition', definition);
            formData.append('quote', quote);
            formData.append('comment', comment);

            fetch(`view.php?id=${articleId}`, {
                method: 'POST',
                headers: ajaxFormHeaders,
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateTagsPanel(data);
                } else {
                    alert('Erro: ' + data.error);
                }
            });
        }

        async function confirmDelete(button) {
            const notesCount = Number(button?.dataset.articleNotesCount || 0);
            const notesMessage = notesCount > 0
                ? ` Este artigo possui ${notesCount} nota(s); elas e seus vínculos com tags também serão excluídos.`
                : ' Este artigo não possui notas vinculadas.';
            const ok = await FicharioUI.confirm({
                title: 'Excluir artigo',
                message: `Excluir definitivamente este artigo do acervo?${notesMessage} Esta ação não pode ser desfeita.`,
                confirmText: 'Excluir artigo',
                variant: 'danger'
            });
            if (!ok) return;

            const formData = new URLSearchParams();
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'delete_article');

                fetch(`view.php?id=${articleId}`, {
                    method: 'POST',
                    headers: ajaxFormHeaders,
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'articles.php';
                    } else {
                        alert('Erro ao excluir: ' + data.error);
                    }
                });
        }

        // Full Text Reader Embedded Scripting
        let isLightMode = false;
        let isEditMode = false;

        function toggleFocusedReading() {
            const isActive = sessionStorage.getItem('focusedReadingActive') === '1';
            if (isActive) {
                exitFocusedReading();
            } else {
                openReadingModal();
            }
        }

        function openReadingModal() {
            const displayPane = document.getElementById('full-text-display-pane');
            const currentText = displayPane ? displayPane.textContent : '';
            const readingView = document.getElementById('reading-view-container');
            const readingTextarea = document.getElementById('reading-full-text-textarea');
            const normalCard = document.getElementById('normal-tabs-card');
            const focusedCard = document.getElementById('focused-reading-card');

            if (!readingView || !normalCard || !focusedCard) {
                return;
            }

            readingView.textContent = currentText;
            if (readingTextarea) {
                readingTextarea.value = currentText;
            }

            setEditMode(false);

            normalCard.classList.add('d-none');
            focusedCard.classList.remove('d-none');
            sessionStorage.setItem('focusedReadingActive', '1');

            const btn = document.getElementById('btn-reading-modal');
            if (btn) {
                btn.classList.remove('btn-outline-primary');
                btn.classList.add('btn-primary');
            }
        }

        function exitFocusedReading() {
            const focusedCard = document.getElementById('focused-reading-card');
            const normalCard = document.getElementById('normal-tabs-card');
            if (focusedCard) {
                focusedCard.classList.add('d-none');
            }
            if (normalCard) {
                normalCard.classList.remove('d-none');
            }
            sessionStorage.removeItem('focusedReadingActive');

            const btn = document.getElementById('btn-reading-modal');
            if (btn) {
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-outline-primary');
            }
        }

        function setEditMode(active) {
            isEditMode = active;
            const viewContainer = document.getElementById('reading-view-container');
            const editContainer = document.getElementById('reading-edit-container');
            const btnEdit = document.getElementById('btn-toggle-edit');

            if (!editContainer || !btnEdit) {
                if (viewContainer) {
                    viewContainer.classList.remove('d-none');
                }
                return;
            }

            if (active) {
                viewContainer.classList.add('d-none');
                editContainer.classList.remove('d-none');
                editContainer.classList.add('d-flex');
                btnEdit.textContent = 'Modo Leitura';
                btnEdit.classList.remove('btn-outline-primary');
                btnEdit.classList.add('btn-outline-secondary');
            } else {
                viewContainer.classList.remove('d-none');
                editContainer.classList.add('d-none');
                editContainer.classList.remove('d-flex');
                btnEdit.textContent = 'Editar Texto';
                btnEdit.classList.remove('btn-outline-secondary');
                btnEdit.classList.add('btn-outline-primary');
            }
        }

        function toggleReadingEditMode() {
            setEditMode(!isEditMode);
        }

        function cancelReadingEdit() {
            setEditMode(false);
        }

        const quoteContextMenu = document.getElementById('quote-context-menu');
        const quoteModalEl = document.getElementById('quoteModal');
        const quoteModal = quoteModalEl ? new bootstrap.Modal(quoteModalEl) : null;
        const markingReadModalEl = document.getElementById('markingReadModal');
        const markingReadModal = markingReadModalEl ? new bootstrap.Modal(markingReadModalEl) : null;
        let selectedFocusedQuote = '';
        let editingQuoteId = 0;
        let currentMarkingReadCard = null;

        function hideQuoteContextMenu() {
            if (quoteContextMenu) {
                quoteContextMenu.classList.add('d-none');
            }
        }

        function getFocusedReadingSelection() {
            const readingContainer = document.getElementById('reading-view-container');
            const selection = window.getSelection();
            if (!readingContainer || !selection || selection.rangeCount === 0) {
                return '';
            }

            const range = selection.getRangeAt(0);
            if (!readingContainer.contains(range.commonAncestorContainer)) {
                return '';
            }

            return selection.toString().trim();
        }

        document.addEventListener('contextmenu', (event) => {
            const focusedActive = sessionStorage.getItem('focusedReadingActive') === '1';
            if (!focusedActive || isEditMode || !quoteContextMenu) {
                return;
            }

            const selectedText = getFocusedReadingSelection();
            if (selectedText === '') {
                hideQuoteContextMenu();
                return;
            }

            event.preventDefault();
            selectedFocusedQuote = selectedText;
            quoteContextMenu.style.left = `${event.clientX}px`;
            quoteContextMenu.style.top = `${event.clientY}px`;
            quoteContextMenu.classList.remove('d-none');
        });

        document.addEventListener('click', (event) => {
            if (quoteContextMenu && !quoteContextMenu.contains(event.target)) {
                hideQuoteContextMenu();
            }
            const quoteTagDropdown = document.getElementById('quote-modal-tag-dropdown');
            const quoteTagSearch = document.getElementById('quote-modal-tag-search');
            if (
                quoteTagDropdown &&
                quoteTagSearch &&
                event.target !== quoteTagSearch &&
                !quoteTagDropdown.contains(event.target)
            ) {
                quoteTagDropdown.classList.add('d-none');
            }
        });

        function copySelectedFocusedQuote() {
            const text = selectedFocusedQuote;
            if (!text) {
                return;
            }

            const fallbackCopy = () => {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.setAttribute('readonly', 'readonly');
                textarea.style.position = 'fixed';
                textarea.style.left = '-9999px';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                textarea.remove();
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).catch(fallbackCopy);
            } else {
                fallbackCopy();
            }
        }

        const btnContextCopyQuote = document.getElementById('btn-context-copy-quote');
        if (btnContextCopyQuote) {
            btnContextCopyQuote.addEventListener('click', () => {
                copySelectedFocusedQuote();
                hideQuoteContextMenu();
            });
        }

        const btnContextCreateQuote = document.getElementById('btn-context-create-quote');
        if (btnContextCreateQuote) {
            btnContextCreateQuote.addEventListener('click', () => {
                hideQuoteContextMenu();
                if (!canEditArticle || !quoteModal) {
                    showAuthRequired();
                    return;
                }
                openQuoteModalForCreate(selectedFocusedQuote);
            });
        }

        function setQuoteModalMode(mode) {
            const title = document.getElementById('quoteModalLabel');
            const saveButton = document.getElementById('btn-save-tag-quote');
            if (title) {
                title.textContent = mode === 'edit' ? 'Editar nota' : 'Criar nota';
            }
            if (saveButton) {
                saveButton.textContent = mode === 'edit' ? 'Salvar alterações' : 'Salvar nota';
            }
        }

        function resetQuoteModalError() {
            const errorEl = document.getElementById('quote-modal-error');
            if (errorEl) {
                errorEl.classList.add('d-none');
                errorEl.textContent = '';
            }
        }

        function openQuoteModalForCreate(text) {
            editingQuoteId = 0;
            setQuoteModalMode('create');
            document.getElementById('quote-modal-text').value = text || '';
            document.getElementById('quote-modal-comment').value = '';
            document.getElementById('quote-modal-tag-search').value = '';
            selectedQuoteTags = [];
            renderSelectedQuoteTags();
            document.getElementById('quote-modal-tag-dropdown').classList.add('d-none');
            resetQuoteModalError();
            quoteModal.show();
            setTimeout(() => document.getElementById('quote-modal-tag-search').focus(), 150);
        }

        function readQuoteCardTags(card) {
            try {
                const parsed = JSON.parse(card.dataset.quoteTags || '[]');
                if (!Array.isArray(parsed)) {
                    return [];
                }
                return parsed
                    .map(tag => ({
                        id: Number(tag.id),
                        name: String(tag.name || ''),
                        definition: String(tag.definition || ''),
                        category: String(tag.category || 'Outros')
                    }))
                    .filter(tag => tag.id > 0 && tag.name !== '');
            } catch (error) {
                return [];
            }
        }

        function openQuoteModalForEditCard(card) {
            if (!quoteModal) return;
            if (!card) return;

            editingQuoteId = Number(card.dataset.quoteId || 0);
            if (!editingQuoteId) return;

            setQuoteModalMode('edit');
            document.getElementById('quote-modal-text').value = card.dataset.quoteText || '';
            document.getElementById('quote-modal-comment').value = card.dataset.quoteComment || '';
            document.getElementById('quote-modal-tag-search').value = '';
            selectedQuoteTags = readQuoteCardTags(card);
            renderSelectedQuoteTags();
            document.getElementById('quote-modal-tag-dropdown').classList.add('d-none');
            resetQuoteModalError();
            quoteModal.show();
            setTimeout(() => document.getElementById('quote-modal-text').focus(), 150);
        }

        function editQuoteFromButton(button) {
            openQuoteModalForEditCard(button.closest('[data-quote-id]'));
        }

        function fillMarkingReadSection(sectionId, contentId, text) {
            const section = document.getElementById(sectionId);
            const content = document.getElementById(contentId);
            if (!section || !content) return false;

            const value = String(text || '').trim();
            section.classList.toggle('d-none', value === '');
            content.textContent = value;
            return value !== '';
        }

        function getMarkingReadTagColors(category) {
            const normalized = String(category || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase();

            if (normalized === 'metodo') {
                return { bg: 'rgba(168, 85, 247, 0.15)', text: '#c084fc', border: 'rgba(168, 85, 247, 0.3)' };
            }
            if (normalized === 'fonte') {
                return { bg: 'rgba(16, 185, 129, 0.15)', text: '#34d399', border: 'rgba(16, 185, 129, 0.3)' };
            }
            if (normalized === 'tema') {
                return { bg: 'rgba(59, 130, 246, 0.15)', text: '#60a5fa', border: 'rgba(59, 130, 246, 0.3)' };
            }

            return { bg: 'rgba(255, 255, 255, 0.05)', text: '#e5e7eb', border: 'rgba(255, 255, 255, 0.1)' };
        }

        function renderMarkingReadTags(tags) {
            const container = document.getElementById('marking-read-tags');
            if (!container) return;

            container.innerHTML = '';
            tags.forEach(tag => {
                const colors = getMarkingReadTagColors(tag.category);
                const badge = document.createElement('span');
                badge.className = 'badge rounded-pill border tag-badge';
                badge.textContent = tag.name;
                badge.title = tag.definition || 'Sem definição cadastrada.';
                badge.dataset.bsToggle = 'tooltip';
                badge.dataset.bsPlacement = 'top';
                badge.dataset.bsTitle = badge.title;
                badge.style.background = colors.bg;
                badge.style.color = colors.text;
                badge.style.borderColor = colors.border;
                container.appendChild(badge);
            });
        }

        function openMarkingReadFromButton(button) {
            if (!markingReadModal) return;
            const card = button.closest('[data-quote-id]');
            if (!card) return;

            currentMarkingReadCard = card;
            renderMarkingReadTags(readQuoteCardTags(card));

            const hasQuote = fillMarkingReadSection('marking-read-quote-section', 'marking-read-quote', card.dataset.quoteText || '');
            const hasComment = fillMarkingReadSection('marking-read-comment-section', 'marking-read-comment', card.dataset.quoteComment || '');
            const emptyEl = document.getElementById('marking-read-empty');
            if (emptyEl) {
                emptyEl.classList.toggle('d-none', hasQuote || hasComment);
            }

            markingReadModal.show();
        }

        const btnEditMarkingFromRead = document.getElementById('btn-edit-marking-from-read');
        if (btnEditMarkingFromRead) {
            btnEditMarkingFromRead.addEventListener('click', () => {
                if (!currentMarkingReadCard) return;
                const cardToEdit = currentMarkingReadCard;
                const openEditModal = () => openQuoteModalForEditCard(cardToEdit);
                if (markingReadModalEl && markingReadModalEl.classList.contains('show')) {
                    markingReadModalEl.addEventListener('hidden.bs.modal', openEditModal, { once: true });
                    markingReadModal.hide();
                    return;
                }

                markingReadModal.hide();
                openEditModal();
            });
        }

        async function deleteQuoteFromButton(button) {
            const card = button.closest('[data-quote-id]');
            const quoteId = card ? Number(card.dataset.quoteId || 0) : 0;
            if (!quoteId) return;
            const ok = await FicharioUI.confirm({
                title: 'Excluir nota',
                message: 'Excluir esta nota? Os vínculos com tags também serão removidos.',
                confirmText: 'Excluir nota',
                variant: 'danger'
            });
            if (!ok) return;
            const releaseDeleteBusy = window.FicharioUI
                ? FicharioUI.setBusy(button, true, 'Excluindo...')
                : () => {};

            const formData = new URLSearchParams();
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'delete_tag_quote');
            formData.append('quote_id', String(quoteId));

            fetch(`view.php?id=${articleId}`, {
                method: 'POST',
                headers: ajaxFormHeaders,
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateTagsPanel(data);
                } else {
                    alert(data.error || 'Não foi possível excluir a nota.');
                }
            })
            .catch(() => alert('Falha de rede ao excluir a nota.'))
            .finally(releaseDeleteBusy);
        }

        const btnSaveTagQuote = document.getElementById('btn-save-tag-quote');
        if (btnSaveTagQuote && quoteModal) {
            btnSaveTagQuote.addEventListener('click', () => {
                const errorEl = document.getElementById('quote-modal-error');
                const tagIds = document.getElementById('quote-modal-tag-ids').value;
                const quoteText = document.getElementById('quote-modal-text').value.trim();
                const comment = document.getElementById('quote-modal-comment').value.trim();

                errorEl.classList.add('d-none');
                errorEl.textContent = '';

                if (!tagIds) {
                    errorEl.textContent = 'Selecione pelo menos uma tag.';
                    errorEl.classList.remove('d-none');
                    return;
                }

                const releaseQuoteBusy = window.FicharioUI
                    ? FicharioUI.setBusy(btnSaveTagQuote, true, 'Salvando...')
                    : () => {};

                const formData = new URLSearchParams();
                formData.append('csrf_token', csrfToken);
                formData.append('action', editingQuoteId ? 'update_tag_quote' : 'create_tag_quote');
                if (editingQuoteId) {
                    formData.append('quote_id', String(editingQuoteId));
                }
                formData.append('tag_ids', tagIds);
                formData.append('quote_text', quoteText);
                formData.append('comment', comment);

                fetch(`view.php?id=${articleId}`, {
                    method: 'POST',
                    headers: ajaxFormHeaders,
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        updateTagsPanel(data);
                        quoteModal.hide();
                        editingQuoteId = 0;
                    } else {
                        errorEl.textContent = data.error || 'Não foi possível salvar a nota.';
                        errorEl.classList.remove('d-none');
                    }
                })
                .catch(() => {
                    errorEl.textContent = 'Falha de rede ao salvar a nota.';
                    errorEl.classList.remove('d-none');
                })
                .finally(() => {
                    releaseQuoteBusy();
                });
            });
        }

        function toggleReadingLight() {
            const card = document.getElementById('focused-reading-card');
            const btnLight = document.getElementById('btn-toggle-light');
            isLightMode = !isLightMode;

            if (isLightMode) {
                card.classList.remove('dark-mode');
                card.classList.add('light-mode');
                btnLight.innerHTML = '<span>Acender a Luz</span>';
                btnLight.classList.remove('btn-outline-light');
                btnLight.classList.add('btn-outline-dark');
            } else {
                card.classList.remove('light-mode');
                card.classList.add('dark-mode');
                btnLight.innerHTML = '<span>Apagar a Luz</span>';
                btnLight.classList.remove('btn-outline-dark');
                btnLight.classList.add('btn-outline-light');
            }
        }

        function saveFullTextFromModal() {
            const textarea = document.getElementById('reading-full-text-textarea');
            const newText = textarea.value;
            const btnSave = document.getElementById('btn-save-full-text');
            const releaseFullTextBusy = window.FicharioUI
                ? FicharioUI.setBusy(btnSave, true, 'Salvando...')
                : () => {};

            const formData = new URLSearchParams();
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'save_full_text');
            formData.append('full_text', newText);

            fetch(`view.php?id=${articleId}`, {
                method: 'POST',
                headers: ajaxFormHeaders,
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                releaseFullTextBusy();
                if (data.success) {
                    const displayPane = document.getElementById('full-text-display-pane');
                    if (displayPane) {
                        displayPane.textContent = newText;
                    }
                    const readingView = document.getElementById('reading-view-container');
                    if (readingView) {
                        readingView.textContent = newText;
                    }
                    setEditMode(false);
                } else {
                    alert('Erro ao salvar texto: ' + data.error);
                }
            })
            .catch(err => {
                releaseFullTextBusy();
                alert('Falha na rede ao salvar.');
                console.error(err);
            });
        }

        function setActiveProject(projectId) {
            const selector = document.getElementById('active-project-selector');
            const releaseBusy = window.FicharioUI
                ? FicharioUI.setBusy(selector, true, 'Alterando...')
                : () => {};

            const formData = new URLSearchParams();
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'set_active_project');
            formData.append('project_id', projectId);

            fetch(`view.php?id=${articleId}`, {
                method: 'POST',
                headers: ajaxFormHeaders,
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateTagsPanel(data);
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

            if (val === 'new_section') {
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

            fetch(`view.php?id=${articleId}`, {
                method: 'POST',
                headers: ajaxFormHeaders,
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateTagsPanel(data);
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

            fetch(`view.php?id=${articleId}`, {
                method: 'POST',
                headers: ajaxFormHeaders,
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateTagsPanel(data);
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

            fetch(`view.php?id=${articleId}`, {
                method: 'POST',
                headers: ajaxFormHeaders,
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateTagsPanel(data);
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
    <!-- Loading overlay (hourglass) -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="d-flex flex-column align-items-center gap-3">
            <svg class="loading-hourglass" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 2h14"></path>
                <path d="M5 22h14"></path>
                <path d="M19 2v10a7 7 0 0 1-14 0V2"></path>
                <path d="M5 22v-10a7 7 0 0 1 14 0v10"></path>
            </svg>
            <span class="text-white fw-medium small tracking-wide">Aguarde...</span>
        </div>
    </div>
    
    <style>
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 99999;
            opacity: 0;
            transition: opacity 0.25s ease-in-out;
            pointer-events: none;
        }
        .loading-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }
        .loading-hourglass {
            color: #f59e0b;
            animation: rotateHourglass 2s infinite ease-in-out;
        }
        @keyframes rotateHourglass {
            0% {
                transform: rotate(0deg);
            }
            40% {
                transform: rotate(180deg);
            }
            60% {
                transform: rotate(180deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }
    </style>

    <script>
        (function() {
            let fetchTimer = null;
            let activeFetchCount = 0;
            
            function showOverlay(instant = false) {
                activeFetchCount++;
                if (instant) {
                    const overlay = document.getElementById('loading-overlay');
                    if (overlay) overlay.classList.add('active');
                } else if (!fetchTimer) {
                    fetchTimer = setTimeout(() => {
                        const overlay = document.getElementById('loading-overlay');
                        if (overlay) overlay.classList.add('active');
                    }, 250); // 250ms threshold
                }
            }

            function hideOverlay() {
                activeFetchCount--;
                if (activeFetchCount <= 0) {
                    activeFetchCount = 0;
                    if (fetchTimer) {
                        clearTimeout(fetchTimer);
                        fetchTimer = null;
                    }
                    const overlay = document.getElementById('loading-overlay');
                    if (overlay) overlay.classList.remove('active');
                }
            }

            // Intercept global fetch calls (AJAX)
            const originalFetch = window.fetch;
            window.fetch = function(...args) {
                showOverlay();
                return originalFetch(...args).finally(() => {
                    hideOverlay();
                });
            };

            // Intercept form submits (Full page load)
            document.addEventListener('submit', (event) => {
                if (event.defaultPrevented) return;
                const target = event.target.getAttribute('target');
                if (target === '_blank') return;
                showOverlay(true);
            });

            // Show on navigation away / beforeunload (with auto-hide timeout to prevent freezes on downloads)
            window.addEventListener('beforeunload', () => {
                showOverlay(true);
                setTimeout(hideOverlay, 5000);
            });
        })();
    </script>
</body>
</html>
