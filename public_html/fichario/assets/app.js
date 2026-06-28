(function () {
    'use strict';

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char]));
    }

    function inferLoadingText(button) {
        const explicit = button?.dataset?.loadingText;
        if (explicit) {
            return explicit;
        }

        const text = (button?.textContent || '').trim().toLowerCase();
        if (text.includes('salvar')) return 'Salvando...';
        if (text.includes('buscar')) return 'Buscando...';
        if (text.includes('entrar')) return 'Entrando...';
        if (text.includes('criar')) return 'Criando...';
        if (text.includes('enviar')) return 'Enviando...';
        if (text.includes('atualizar')) return 'Atualizando...';
        if (text.includes('excluir') || text.includes('remover')) return 'Excluindo...';
        if (text.includes('importar') || text.includes('preencher')) return 'Importando...';
        if (text.includes('extrair')) return 'Extraindo...';

        return 'Processando...';
    }

    function resolveElement(target) {
        if (!target) return null;
        if (typeof target === 'string') return document.querySelector(target);
        return target;
    }

    function setBusy(target, busy = true, label = null) {
        const element = resolveElement(target);
        if (!element) {
            return function noop() {};
        }

        if (!busy) {
            clearBusy(element);
            return function noop() {};
        }

        if (element.dataset.busy === '1') {
            return () => clearBusy(element);
        }

        const rect = element.getBoundingClientRect();
        if (rect.width > 0 && !element.style.minWidth) {
            element.dataset.busyMinWidthSet = '1';
            element.style.minWidth = `${Math.ceil(rect.width)}px`;
        }

        element.dataset.busy = '1';
        element.dataset.originalHtml = element.innerHTML;
        element.dataset.originalDisabled = element.disabled ? '1' : '0';
        element.disabled = true;
        element.setAttribute('aria-busy', 'true');
        element.classList.add('is-busy');

        const loadingText = label || inferLoadingText(element);
        element.innerHTML = [
            '<span class="spinner-border spinner-border-sm fichario-spinner" role="status" aria-hidden="true"></span>',
            `<span>${escapeHtml(loadingText)}</span>`
        ].join('');

        return () => clearBusy(element);
    }

    function clearBusy(target) {
        const element = resolveElement(target);
        if (!element || element.dataset.busy !== '1') {
            return;
        }

        element.innerHTML = element.dataset.originalHtml || '';
        element.disabled = element.dataset.originalDisabled === '1';
        element.removeAttribute('aria-busy');
        element.classList.remove('is-busy');

        if (element.dataset.busyMinWidthSet === '1') {
            element.style.minWidth = '';
        }

        delete element.dataset.busy;
        delete element.dataset.originalHtml;
        delete element.dataset.originalDisabled;
        delete element.dataset.busyMinWidthSet;
    }

    function withBusy(target, task, label = null) {
        const release = setBusy(target, true, label);
        return Promise.resolve()
            .then(task)
            .finally(release);
    }

    function setPanelBusy(target, busy = true, label = 'Carregando...') {
        const panel = resolveElement(target);
        if (!panel) return;

        if (!busy) {
            const overlay = panel.querySelector(':scope > .fichario-loading-overlay');
            if (overlay) overlay.remove();
            panel.classList.remove('is-panel-busy');
            panel.removeAttribute('aria-busy');
            return;
        }

        if (getComputedStyle(panel).position === 'static') {
            panel.classList.add('position-relative');
        }

        panel.classList.add('is-panel-busy');
        panel.setAttribute('aria-busy', 'true');

        if (panel.querySelector(':scope > .fichario-loading-overlay')) {
            return;
        }

        const overlay = document.createElement('div');
        overlay.className = 'fichario-loading-overlay';
        overlay.innerHTML = [
            '<div class="fichario-loading-box">',
            '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>',
            `<span>${escapeHtml(label)}</span>`,
            '</div>'
        ].join('');
        panel.appendChild(overlay);
    }

    function ensureConfirmModal() {
        let modal = document.getElementById('appConfirmModal');
        if (modal) return modal;

        modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'appConfirmModal';
        modal.tabIndex = -1;
        modal.setAttribute('aria-labelledby', 'appConfirmModalTitle');
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML = [
            '<div class="modal-dialog modal-dialog-centered">',
            '<div class="modal-content">',
            '<div class="modal-header">',
            '<h2 class="modal-title fs-5" id="appConfirmModalTitle">Confirmar ação</h2>',
            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>',
            '</div>',
            '<div class="modal-body">',
            '<p class="mb-0" id="appConfirmModalMessage"></p>',
            '</div>',
            '<div class="modal-footer">',
            '<button type="button" class="btn btn-outline-secondary text-white rounded-pill" data-bs-dismiss="modal" id="appConfirmCancel">Cancelar</button>',
            '<button type="button" class="btn btn-danger rounded-pill" id="appConfirmAccept">Confirmar</button>',
            '</div>',
            '</div>',
            '</div>'
        ].join('');
        document.body.appendChild(modal);
        return modal;
    }

    function confirmAction(options = {}) {
        const modalEl = ensureConfirmModal();
        const titleEl = modalEl.querySelector('#appConfirmModalTitle');
        const messageEl = modalEl.querySelector('#appConfirmModalMessage');
        const acceptEl = modalEl.querySelector('#appConfirmAccept');
        const cancelEl = modalEl.querySelector('#appConfirmCancel');
        const title = options.title || 'Confirmar ação';
        const message = options.message || 'Deseja continuar?';
        const confirmText = options.confirmText || 'Confirmar';
        const cancelText = options.cancelText || 'Cancelar';
        const variant = options.variant || 'danger';

        titleEl.textContent = title;
        messageEl.textContent = message;
        acceptEl.textContent = confirmText;
        cancelEl.textContent = cancelText;
        acceptEl.className = `btn btn-${variant} rounded-pill`;

        return new Promise((resolve) => {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            let settled = false;

            const cleanup = () => {
                acceptEl.removeEventListener('click', onAccept);
                modalEl.removeEventListener('hidden.bs.modal', onHidden);
            };
            const onAccept = () => {
                settled = true;
                cleanup();
                modal.hide();
                resolve(true);
            };
            const onHidden = () => {
                cleanup();
                if (!settled) {
                    resolve(false);
                }
            };

            acceptEl.addEventListener('click', onAccept);
            modalEl.addEventListener('hidden.bs.modal', onHidden, { once: true });
            modal.show();
        });
    }

    function initConfirmForms(root = document) {
        root.querySelectorAll('form[data-confirm-message]').forEach((form) => {
            if (form.dataset.confirmReady === '1') return;
            form.dataset.confirmReady = '1';

            form.addEventListener('submit', async (event) => {
                if (form.dataset.confirmedSubmit === '1') {
                    delete form.dataset.confirmedSubmit;
                    return;
                }

                event.preventDefault();
                const ok = await confirmAction({
                    title: form.dataset.confirmTitle || 'Confirmar ação',
                    message: form.dataset.confirmMessage || 'Deseja continuar?',
                    confirmText: form.dataset.confirmButton || 'Confirmar',
                    cancelText: form.dataset.cancelButton || 'Cancelar',
                    variant: form.dataset.confirmVariant || 'danger'
                });
                if (!ok) return;

                form.dataset.confirmedSubmit = '1';
                if (event.submitter) {
                    form.requestSubmit(event.submitter);
                } else {
                    form.requestSubmit();
                }
            });
        });
    }

    function storageGet(key) {
        try {
            return window.localStorage.getItem(key);
        } catch (error) {
            return null;
        }
    }

    function storageSet(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch (error) {
            // Se o navegador bloquear localStorage, o aviso pode reaparecer sem afetar a aplicacao.
        }
    }

    function initCookieNotice() {
        const consentKey = 'fichario_cookie_notice_v1';
        if (storageGet(consentKey) === 'accepted' || document.getElementById('cookie-notice')) {
            return;
        }

        const notice = document.createElement('aside');
        notice.id = 'cookie-notice';
        notice.className = 'cookie-notice';
        notice.setAttribute('role', 'dialog');
        notice.setAttribute('aria-live', 'polite');
        notice.setAttribute('aria-label', 'Aviso de cookies');
        notice.innerHTML = [
            '<div class="cookie-notice__text">',
            '<strong>Cookies necessários</strong>',
            '<span>Usamos cookies essenciais de sessão e segurança para login, CSRF e funcionamento da aplicação. Não usamos cookies de publicidade. Recursos externos, como reCAPTCHA, podem usar cookies próprios quando habilitados.</span>',
            '<a href="privacy.php" class="cookie-notice__link">Saiba mais</a>',
            '</div>',
            '<button type="button" class="btn btn-primary rounded-pill cookie-notice__button">Entendi</button>'
        ].join('');

        notice.querySelector('button')?.addEventListener('click', () => {
            storageSet(consentKey, 'accepted');
            notice.remove();
        });

        document.body.appendChild(notice);
    }

    function initFormBusy(root = document) {
        root.querySelectorAll('form:not([data-busy-ignore])').forEach((form) => {
            if (form.dataset.busyReady === '1') return;
            form.dataset.busyReady = '1';

            form.addEventListener('submit', (event) => {
                if (event.defaultPrevented) return;

                const submitter = event.submitter
                    || form.querySelector('button[type="submit"], input[type="submit"]');

                if (!submitter || submitter.dataset.busyIgnore === '1') return;
                setBusy(submitter, true);
            });
        });
    }

    function toggleExpandableText(el) {
        el.classList.toggle('collapsed');
    }

    function toggleAllMarkings(btn, parentSelector = '.pt-3') {
        const parent = btn.closest(parentSelector);
        if (!parent) return;
        const elements = parent.querySelectorAll('.expandable-text');
        const text = (btn.textContent || '').trim().toLowerCase();
        const isExpanding = text.includes('expandir');
        
        elements.forEach(el => {
            if (isExpanding) {
                el.classList.remove('collapsed');
            } else {
                el.classList.add('collapsed');
            }
        });
        
        btn.textContent = isExpanding ? 'Recolher todas' : 'Expandir todas';
    }

    function showToast(message, type = 'success') {
        let container = document.getElementById('fichario-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'fichario-toast-container';
            container.style.cssText = 'position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 10000; display: flex; flex-direction: column; align-items: center; gap: 10px; pointer-events: none; width: 90%; max-width: 480px;';
            document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} shadow-lg m-0`;
        toast.style.cssText = 'pointer-events: auto; width: 100%; border-radius: 12px; font-weight: 500; font-family: "Outfit", sans-serif; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3); border: 1px solid; opacity: 0; transform: translateY(-10px); transition: opacity 0.3s ease, transform 0.3s ease;';
        
        const iconMap = {
            success: '<i class="bi bi-check-circle-fill me-2 fs-5"></i>',
            danger: '<i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>',
            warning: '<i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>',
            info: '<i class="bi bi-info-circle-fill me-2 fs-5"></i>'
        };
        const icon = iconMap[type] || '';
        toast.innerHTML = `<div class="d-flex align-items-center w-100">${icon}<div class="toast-message-text flex-grow-1"></div></div>`;
        toast.querySelector('.toast-message-text').textContent = message;
        
        container.appendChild(toast);
        
        // Force reflow and animate in
        toast.offsetHeight;
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-10px)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    function convertAlertsToToasts() {
        const alerts = document.querySelectorAll('.alert:not(.d-none)');
        alerts.forEach((alert) => {
            if (alert.closest('#fichario-toast-container')) {
                return;
            }
            
            const text = alert.textContent.trim();
            if (!text) return;
            
            let type = 'success';
            if (alert.classList.contains('alert-danger')) {
                type = 'danger';
            } else if (alert.classList.contains('alert-warning')) {
                type = 'warning';
            } else if (alert.classList.contains('alert-info')) {
                type = 'info';
            }
            
            alert.remove();
            showToast(text, type);
        });
    }

    function initMovement() {
        document.addEventListener('submit', async (event) => {
            const form = event.target;
            if (!form) return;

            const actionInput = form.querySelector('input[name="action"]');
            if (!actionInput) return;
            
            const action = actionInput.value;
            const isNote = action === 'move_note';
            const isSection = action === 'move_section';
            const isMoveToSection = action === 'move_note_to_section';
            
            if (!isNote && !isSection && !isMoveToSection) return;

            event.preventDefault();

            if (isMoveToSection) {
                const select = form.querySelector('select[name="to_section_id"]');
                const toSectionId = select ? parseInt(select.value) : 0;
                if (!toSectionId) return;

                const card = form.closest('.note-card');
                if (!card) return;

                const fromSectionInput = form.querySelector('input[name="from_section_id"]');
                const fromSectionId = fromSectionInput ? parseInt(fromSectionInput.value) : 0;

                const getGeneralSectionId = () => {
                    const input = document.querySelector('#section-general input[name="section_id"]');
                    return input ? parseInt(input.value) : 0;
                };

                const generalSectionId = getGeneralSectionId();
                const getSectionContainerId = (id) => {
                    return id === generalSectionId ? 'section-general' : `section-${id}`;
                };

                const sourceContainerId = getSectionContainerId(fromSectionId);
                const targetContainerId = getSectionContainerId(toSectionId);

                const sourceBody = document.querySelector(`#${sourceContainerId} .section-body`);
                const targetBody = document.querySelector(`#${targetContainerId} .section-body`);

                if (!sourceBody || !targetBody) return;

                // Backups for revert on failure
                const sourceHtmlBackup = sourceBody.innerHTML;
                const targetHtmlBackup = targetBody.innerHTML;

                const getSectionBadge = (id) => {
                    const containerId = getSectionContainerId(id);
                    const secEl = document.getElementById(containerId);
                    return secEl ? secEl.querySelector('h2 .badge') : null;
                };

                const sourceBadge = getSectionBadge(fromSectionId);
                const targetBadge = getSectionBadge(toSectionId);

                const updateBadgeCount = (badge, change) => {
                    if (!badge) return;
                    const match = badge.textContent.match(/(\d+)/);
                    if (match) {
                        const count = parseInt(match[1]) + change;
                        badge.textContent = `${count} nota(s)`;
                    }
                };

                // Disable select dropdown during request
                select.disabled = true;
                const releaseBusy = () => { select.disabled = false; };

                // Perform move in DOM
                let targetStack = targetBody.querySelector('.vstack');
                if (!targetStack) {
                    const placeholder = targetBody.querySelector('.text-center');
                    if (placeholder) {
                        placeholder.remove();
                    }
                    targetStack = document.createElement('div');
                    targetStack.className = 'vstack gap-2 mb-3';
                    targetBody.appendChild(targetStack);
                }

                targetStack.appendChild(card);

                // Check if source section is empty
                const sourceStack = sourceBody.querySelector('.vstack');
                if (sourceStack && sourceStack.querySelector('.note-card') === null) {
                    let placeholderHtml = '';
                    if (sourceContainerId === 'section-general') {
                        placeholderHtml = '<div class="p-3 rounded-3 bg-black bg-opacity-25 text-secondary text-center mb-3">Nenhuma nota vinculada diretamente ao projeto.</div>';
                    } else {
                        placeholderHtml = '<div class="p-3 rounded-3 bg-black bg-opacity-25 text-secondary text-center mb-3">Nenhuma nota vinculada a esta seção.</div>';
                    }
                    sourceStack.remove();
                    sourceBody.innerHTML = placeholderHtml;
                }

                // Update note card inputs and select
                const updateNoteCardInputs = (cardEl, newSecId) => {
                    const noteId = cardEl.id.split('-').pop();
                    cardEl.id = `note-card-${newSecId}-${noteId}`;
                    
                    cardEl.querySelectorAll('input[name="section_id"]').forEach((input) => {
                        input.value = newSecId;
                    });
                    cardEl.querySelectorAll('input[name="from_section_id"]').forEach((input) => {
                        input.value = newSecId;
                    });
                    
                    const sel = cardEl.querySelector('select[name="to_section_id"]');
                    if (sel) {
                        sel.value = "";
                        Array.from(sel.options).forEach((opt) => {
                            if (opt.value === "") {
                                opt.disabled = true;
                                opt.selected = true;
                            } else if (parseInt(opt.value) === newSecId) {
                                opt.disabled = true;
                                opt.style.display = 'none';
                            } else {
                                opt.disabled = false;
                                opt.style.display = '';
                            }
                        });
                    }
                };

                updateNoteCardInputs(card, toSectionId);

                // Update badge counts
                updateBadgeCount(sourceBadge, -1);
                updateBadgeCount(targetBadge, 1);

                // Scroll to target card location and animate
                card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

                try {
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!response.ok) {
                        let errorMessage = 'Erro ao mover a nota de seção.';
                        try {
                            const errResult = await response.json();
                            if (errResult && errResult.error) {
                                errorMessage = errResult.error;
                            }
                        } catch (e) {}
                        throw new Error(errorMessage);
                    }

                    const result = await response.json();
                    if (!result.success) {
                        throw new Error(result.error || 'Erro ao mover a nota de seção.');
                    }

                    // Success glow
                    card.classList.remove('note-card-highlight');
                    void card.offsetWidth;
                    card.classList.add('note-card-highlight');

                } catch (error) {
                    // Revert innerHTML on failure
                    sourceBody.innerHTML = sourceHtmlBackup;
                    targetBody.innerHTML = targetHtmlBackup;

                    // Revert counts
                    updateBadgeCount(sourceBadge, 1);
                    updateBadgeCount(targetBadge, -1);

                    const revertedCard = document.getElementById(`note-card-${fromSectionId}-${card.id.split('-').pop()}`);
                    if (revertedCard) {
                        revertedCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }

                    showToast(error.message || 'Erro de conexão.', 'danger');
                } finally {
                    releaseBusy();
                }
                return;
            }

            // Select classes based on what is being moved
            const cardSelector = isNote ? '.note-card' : '.glass-card';
            const siblingClass = isNote ? 'note-card' : 'glass-card';

            const card = form.closest(cardSelector);
            if (!card) return;

            const container = card.parentNode;
            if (!container) return;

            const directionInput = form.querySelector('input[name="direction"]');
            if (!directionInput) return;
            const direction = directionInput.value;

            const sibling = direction === 'up' ? card.previousElementSibling : card.nextElementSibling;
            if (!sibling || !sibling.classList.contains(siblingClass)) {
                return;
            }

            // For sections, make sure we don't swap with "Geral" section which doesn't have id="section-..."
            if (isSection) {
                if (!card.id || !card.id.startsWith('section-')) return;
                if (!sibling.id || !sibling.id.startsWith('section-')) return;
            }

            const clickedButton = event.submitter || form.querySelector('button[type="submit"]');

            // Set loading state on the button
            const releaseBusy = clickedButton ? setBusy(clickedButton, true) : () => {};

            // Optimistic swap in DOM
            if (direction === 'up') {
                container.insertBefore(card, sibling);
            } else {
                container.insertBefore(sibling, card);
            }

            // Scroll to keep focused card in view
            card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    let errorMessage = isNote ? 'Erro ao mover a nota.' : 'Erro ao mover a seção.';
                    try {
                        const errResult = await response.json();
                        if (errResult && errResult.error) {
                            errorMessage = errResult.error;
                        }
                    } catch (e) {}
                    throw new Error(errorMessage);
                }

                const result = await response.json();
                if (!result.success) {
                    throw new Error(result.error || (isNote ? 'Erro ao mover a nota.' : 'Erro ao mover a seção.'));
                }

                // Success! Apply highlight to the card
                card.classList.remove('note-card-highlight');
                void card.offsetWidth; // trigger reflow
                card.classList.add('note-card-highlight');

                // Restore focus to the button
                if (clickedButton) {
                    setTimeout(() => clickedButton.focus(), 50);
                }

            } catch (error) {
                // Revert swap in DOM on failure
                if (direction === 'up') {
                    container.insertBefore(sibling, card);
                } else {
                    container.insertBefore(card, sibling);
                }
                card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

                // Show error toast
                showToast(error.message || 'Erro de conexão.', 'danger');
            } finally {
                releaseBusy();
            }
        });
    }

    function initNoteEditing() {
        const form = document.getElementById('editNoteForm');
        if (!form) return;

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const submitButton = form.querySelector('button[type="submit"]');
            const releaseBusy = submitButton ? setBusy(submitButton, true) : () => {};

            const noteId = form.querySelector('#edit-note-id').value;
            const quoteText = form.querySelector('#edit-note-quote').value.trim();
            const comment = form.querySelector('#edit-note-comment').value.trim();

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    let errorMessage = 'Erro ao atualizar a nota.';
                    try {
                        const errResult = await response.json();
                        if (errResult && errResult.error) {
                            errorMessage = errResult.error;
                        }
                    } catch (e) {}
                    throw new Error(errorMessage);
                }

                const result = await response.json();
                if (!result.success) {
                    throw new Error(result.error || 'Erro ao atualizar a nota.');
                }

                // Close the modal
                const modalEl = document.getElementById('editNoteModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }

                // Update note-cards in DOM
                const noteCards = document.querySelectorAll(`[id$="-${noteId}"]`);
                noteCards.forEach((noteCard) => {
                    const editBtn = noteCard.querySelector('button[data-bs-target="#editNoteModal"]');
                    if (editBtn) {
                        editBtn.setAttribute('data-quote-text', quoteText);
                        editBtn.setAttribute('data-comment', comment);
                    }

                    const contentDiv = noteCard.querySelector('.note-content');
                    if (contentDiv) {
                        let html = '';
                        if (quoteText !== '') {
                            html += `
                                <div class="marking-preview marking-preview-quote mb-2">
                                    <span class="note-teaser-label">Citação</span>
                                    <div class="quote-box expandable-text collapsed" onclick="toggleExpandableText(this)" title="Clique para expandir/recolher">${escapeHtml(quoteText)}</div>
                                </div>
                            `;
                        }
                        if (comment !== '') {
                            html += `
                                <div class="marking-preview marking-preview-comment">
                                    <span class="note-teaser-label">Observação</span>
                                    <div class="observation-box expandable-text collapsed" onclick="toggleExpandableText(this)" title="Clique para expandir/recolher">${escapeHtml(comment)}</div>
                                </div>
                            `;
                        }
                        contentDiv.innerHTML = html;
                    }

                    noteCard.classList.remove('note-card-highlight');
                    void noteCard.offsetWidth; // trigger reflow
                    noteCard.classList.add('note-card-highlight');
                });

                showToast('Nota atualizada com sucesso!');

            } catch (error) {
                showToast(error.message || 'Erro ao salvar alterações.', 'danger');
            } finally {
                releaseBusy();
            }
        });
    }

    function initSectionEditing() {
        const modal = document.getElementById('editSectionModal');
        if (!modal) return;

        const form = modal.querySelector('form');
        if (!form) return;

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const submitButton = form.querySelector('button[type="submit"]');
            const releaseBusy = submitButton ? setBusy(submitButton, true) : () => {};

            const sectionId = form.querySelector('#edit-section-id').value;
            const title = form.querySelector('#edit-section-title').value.trim();
            const context = form.querySelector('#edit-section-context').value.trim();

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    let errorMessage = 'Erro ao salvar alterações da seção.';
                    try {
                        const errResult = await response.json();
                        if (errResult && errResult.error) {
                            errorMessage = errResult.error;
                        }
                    } catch (e) {}
                    throw new Error(errorMessage);
                }

                const result = await response.json();
                if (!result.success) {
                    throw new Error(result.error || 'Erro ao salvar alterações da seção.');
                }

                // Close the modal
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                }

                // Update section in DOM
                const sectionContainer = document.getElementById(`section-${sectionId}`);
                if (sectionContainer) {
                    // Update header title
                    const btn = sectionContainer.querySelector('h2 button');
                    if (btn) {
                        const icon = btn.querySelector('.section-toggle-icon');
                        const badge = btn.querySelector('.badge');
                        
                        btn.innerHTML = '';
                        if (icon) btn.appendChild(icon);
                        btn.appendChild(document.createTextNode(' ' + title));
                        if (badge) btn.appendChild(badge);
                    }
                    
                    // Update edit button data attributes
                    const editBtn = sectionContainer.querySelector('button[data-bs-target="#editSectionModal"]');
                    if (editBtn) {
                        editBtn.setAttribute('data-section-title', title);
                        editBtn.setAttribute('data-section-context', context);
                    }

                    // Update context text in body
                    const sectionBody = document.getElementById(`section-body-${sectionId}`);
                    if (sectionBody) {
                        let contextP = sectionBody.querySelector('.text-white-50.small');
                        if (context !== '') {
                            if (!contextP) {
                                contextP = document.createElement('p');
                                contextP.className = 'text-white-50 small mb-4';
                                contextP.style.cssText = 'white-space: pre-wrap; line-height: 1.6; font-size: 0.88rem;';
                                sectionBody.insertBefore(contextP, sectionBody.firstChild);
                            }
                            contextP.textContent = context;
                        } else {
                            if (contextP) {
                                contextP.remove();
                            }
                        }
                    }
                }

                // Update select dropdown options of all note cards
                document.querySelectorAll(`select[name="to_section_id"] option[value="${sectionId}"]`).forEach((opt) => {
                    opt.textContent = title;
                });

                showToast('Seção atualizada.');

            } catch (error) {
                showToast(error.message || 'Erro ao salvar alterações.', 'danger');
            } finally {
                releaseBusy();
            }
        });
    }

    function toggleSectionCollapse(sectionId) {
        const body = document.getElementById(`section-body-${sectionId}`);
        const icon = document.getElementById(`section-toggle-icon-${sectionId}`);
        if (!body || !icon) return;
        
        const isCollapsed = body.classList.toggle('collapsed');
        if (isCollapsed) {
            body.classList.add('d-none');
            icon.classList.add('collapsed');
        } else {
            body.classList.remove('d-none');
            icon.classList.remove('collapsed');
        }
    }

    function toggleAllSections(button) {
        const sectionBodies = document.querySelectorAll('.section-body');
        const icons = document.querySelectorAll('.section-toggle-icon');
        const text = (button.textContent || '').trim().toLowerCase();
        const isExpanding = text.includes('expandir');
        
        sectionBodies.forEach(body => {
            if (isExpanding) {
                body.classList.remove('collapsed', 'd-none');
            } else {
                body.classList.add('collapsed', 'd-none');
            }
        });
        
        icons.forEach(icon => {
            if (isExpanding) {
                icon.classList.remove('collapsed');
            } else {
                icon.classList.add('collapsed');
            }
        });
        
        button.textContent = isExpanding ? 'Recolher todas as seções' : 'Expandir todas as seções';
    }

    window.toggleExpandableText = toggleExpandableText;
    window.toggleAllMarkings = toggleAllMarkings;
    window.toggleSectionCollapse = toggleSectionCollapse;
    window.toggleAllSections = toggleAllSections;
    window.showToast = showToast;

    window.FicharioUI = {
        clearBusy,
        confirm: confirmAction,
        initCookieNotice,
        initConfirmForms,
        initFormBusy,
        initMovement,
        initNoteEditing,
        initSectionEditing,
        setBusy,
        setPanelBusy,
        toggleSectionCollapse,
        toggleAllSections,
        withBusy
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initCookieNotice();
            initConfirmForms();
            initFormBusy();
            initMovement();
            initNoteEditing();
            initSectionEditing();
            convertAlertsToToasts();
        });
    } else {
        initCookieNotice();
        initConfirmForms();
        initFormBusy();
        initMovement();
        initNoteEditing();
        initSectionEditing();
        convertAlertsToToasts();
    }
}());
