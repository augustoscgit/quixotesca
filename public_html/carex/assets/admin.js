const formatElapsed = (totalSeconds) => {
  const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
  const seconds = String(totalSeconds % 60).padStart(2, '0');
  return `${minutes}:${seconds}`;
};

const estimateRefreshSeconds = (estimatedRows) => {
  const rows = Number.parseInt(estimatedRows, 10);
  if (!Number.isFinite(rows) || rows <= 0) {
    return 90;
  }

  return Math.min(900, Math.max(90, Math.ceil(Math.log10(rows + 1) * 55)));
};

const startRefreshProgress = (progressRow, estimatedRows) => {
  const bar = progressRow?.querySelector('[data-refresh-progress-bar]');
  const shell = progressRow?.querySelector('[role="progressbar"]');
  const label = progressRow?.querySelector('[data-refresh-progress-label]');
  const elapsedLabel = progressRow?.querySelector('[data-refresh-progress-elapsed]');
  const note = progressRow?.querySelector('[data-refresh-progress-note]');

  if (!progressRow || !bar || !shell || !label || !elapsedLabel || !note) {
    return () => {};
  }

  const startedAt = Date.now();
  const estimatedSeconds = estimateRefreshSeconds(estimatedRows);
  progressRow.classList.remove('d-none');
  label.textContent = 'Atualizacao em andamento';
  note.textContent = 'Progresso estimado por tempo decorrido; a conclusao real e confirmada pelo banco.';
  bar.classList.add('progress-bar-animated', 'progress-bar-striped');
  bar.classList.remove('bg-success', 'bg-danger');

  const render = () => {
    const elapsedSeconds = Math.floor((Date.now() - startedAt) / 1000);
    const ratio = Math.min(elapsedSeconds / estimatedSeconds, 1);
    const eased = 1 - Math.pow(1 - ratio, 2.4);
    const percent = Math.min(95, Math.max(2, Math.round(eased * 95)));

    elapsedLabel.textContent = formatElapsed(elapsedSeconds);
    shell.setAttribute('aria-valuenow', String(percent));
    bar.style.width = `${percent}%`;
    bar.textContent = `${percent}%`;
  };

  render();
  const timer = window.setInterval(render, 1000);
  return (state, message) => {
    window.clearInterval(timer);
    const elapsedSeconds = Math.floor((Date.now() - startedAt) / 1000);
    const percent = state === 'success' ? 100 : 100;

    elapsedLabel.textContent = formatElapsed(elapsedSeconds);
    shell.setAttribute('aria-valuenow', String(percent));
    bar.style.width = `${percent}%`;
    bar.textContent = state === 'success' ? '100%' : 'Erro';
    bar.classList.remove('progress-bar-animated', 'progress-bar-striped');
    bar.classList.toggle('bg-success', state === 'success');
    bar.classList.toggle('bg-danger', state === 'error');
    label.textContent = message;
    note.textContent = state === 'success'
      ? 'Atualizacao concluida e confirmada pelo banco.'
      : 'A atualizacao nao foi concluida. Revise a mensagem de erro antes de tentar novamente.';
  };
};

document.addEventListener('click', async (event) => {
  const button = event.target.closest('[data-refresh-matview]');
  if (!button) {
    return;
  }

  const name = button.dataset.refreshMatview;
  const row = button.closest('[data-matview-row]');
  const progressRow = document.querySelector(`[data-matview-progress-row="${CSS.escape(name)}"]`);
  const status = row?.querySelector('[data-refresh-status]');
  const originalText = button.textContent;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  let finishProgress = () => {};

  if (!window.confirm(`Atualizar a view materializada "${name}" agora?`)) {
    return;
  }

  button.disabled = true;
  button.textContent = 'Atualizando...';
  finishProgress = startRefreshProgress(progressRow, button.dataset.estimatedRows);
  if (status) {
    status.textContent = 'Atualizacao em andamento';
    status.className = 'badge text-bg-warning';
  }

  try {
    const body = new URLSearchParams({ name, csrf_token: csrfToken });
    const response = await fetch('api/admin/refresh_materialized_view.php', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
        'X-CSRF-Token': csrfToken,
      },
      body,
    });
    const payload = await response.json();

    if (!response.ok) {
      throw new Error(payload.error || 'Falha ao atualizar.');
    }

    if (status) {
      status.textContent = `Atualizada em ${payload.elapsed_seconds}s`;
      status.className = 'badge text-bg-success';
    }
    finishProgress('success', `Atualizada em ${payload.elapsed_seconds}s`);
  } catch (error) {
    if (status) {
      status.textContent = error.message;
      status.className = 'badge text-bg-danger';
    }
    finishProgress('error', error.message);
  } finally {
    button.disabled = false;
    button.textContent = originalText;
  }
});

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('markdownSettingsForm');
  const switchInput = document.getElementById('allowMarkdownEdit');
  const status = document.getElementById('markdownSettingsStatus');

  if (!form || !switchInput || !status) {
    return;
  }

  const setStatus = (state, message) => {
    const iconClass = state === 'saving'
      ? 'bi-arrow-repeat text-primary'
      : state === 'error'
        ? 'bi-exclamation-triangle text-danger'
        : 'bi-check-circle text-success';

    const icon = document.createElement('i');
    const label = document.createElement('span');
    icon.className = `bi ${iconClass}`;
    label.textContent = message;
    status.replaceChildren(icon, label);
    status.classList.toggle('text-danger', state === 'error');
    status.classList.toggle('text-body-secondary', state !== 'error');
  };

  form.addEventListener('submit', (event) => {
    event.preventDefault();
  });

  switchInput.addEventListener('change', async () => {
    const nextChecked = switchInput.checked;
    const body = new URLSearchParams(new FormData(form));
    body.set('allow_markdown_edit', nextChecked ? '1' : '0');

    switchInput.disabled = true;
    setStatus('saving', 'Salvando alteracao...');

    try {
      const response = await fetch('administrativo.php', {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
        },
        body,
      });
      const contentType = response.headers.get('content-type') || '';
      const payload = contentType.includes('application/json') ? await response.json() : {};

      if (!response.ok || !payload.ok) {
        throw new Error(payload.error || 'Nao foi possivel salvar. Recarregue a pagina e tente novamente.');
      }

      switchInput.checked = Boolean(payload.allow_markdown_edit);
      setStatus('success', 'Alteracao salva automaticamente.');
    } catch (error) {
      switchInput.checked = !nextChecked;
      setStatus('error', error.message || 'Nao foi possivel salvar.');
    } finally {
      switchInput.disabled = false;
    }
  });
});
