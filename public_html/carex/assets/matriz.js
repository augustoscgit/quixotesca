const nodes = {
  searchForm: document.getElementById('searchForm'),
  searchInput: document.getElementById('searchInput'),
  statusMessage: document.getElementById('statusMessage'),
  filterSection: document.getElementById('filterSection'),
  addFilterBtn: document.getElementById('addFilterBtn'),
  filterRowsContainer: document.getElementById('filterRowsContainer'),
  dataBody: document.getElementById('dataBody'),
  paginationMeta: document.getElementById('paginationMeta'),
  paginationList: document.getElementById('paginationList'),
  estimatesTab: document.getElementById('estimates-tab'),
  refreshEstimatesBtn: document.getElementById('refreshEstimatesBtn'),
  estimatesStatus: document.getElementById('estimatesStatus'),
  estimatesSummary: document.getElementById('estimatesSummary'),
  estimatesGrid: document.getElementById('estimatesGrid'),
  estimatesBody: document.getElementById('estimatesBody'),
  estimatesMatrices: document.getElementById('estimatesMatrices'),
};

let estimatesLoaded = false;

const filterFields = [
  { name: 'no_origem_objeto', label: 'Origem (CBO/CNAE)' },
  { name: 'no_tp_objeto', label: 'Tipo de Objeto' },
  { name: 'co_tp_objeto', label: 'Tipo de Objeto (Código)' },
  { name: 'no_classificacao', label: 'Classificação direta' },
  { name: 'no_classificacao_herdada', label: 'Classificação herdada' },
  { name: 'co_nivel_classificacao_herdada', label: 'Nível da classificação herdada' },
  { name: 'classificacao_herdada_origem', label: 'Origem da classificação herdada' }
];

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function syncBootstrapResetMetrics(root = document) {
  root.querySelectorAll('[data-progress-percent]').forEach((element) => {
    const value = Number.parseFloat(element.getAttribute('data-progress-percent') || '0');
    const percent = Math.max(0, Math.min(100, Number.isFinite(value) ? value : 0));
    element.style.width = `${percent}%`;
  });

  root.querySelectorAll('[data-placeholder-width]').forEach((element) => {
    const value = Number.parseFloat(element.getAttribute('data-placeholder-width') || '100');
    const percent = Math.max(10, Math.min(100, Number.isFinite(value) ? value : 100));
    element.style.width = `${percent}%`;
  });
}

document.addEventListener('DOMContentLoaded', () => {
  syncBootstrapResetMetrics();
  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (node instanceof Element) {
          syncBootstrapResetMetrics(node);
        }
      });
    });
  });
  observer.observe(document.body, { childList: true, subtree: true });
});

function showTableSkeleton(columnCount = 8, rowCount = 8) {
  const rows = [];
  for (let i = 0; i < rowCount; i++) {
    const cells = [];
    for (let j = 0; j < columnCount; j++) {
      const widthPercent = 40 + Math.floor(Math.random() * 50);
      cells.push(`
        <td>
          <div class="placeholder-glow">
            <span class="placeholder col-12 placeholder-line" data-placeholder-width="${widthPercent}"></span>
          </div>
        </td>
      `);
    }
    rows.push(`<tr>${cells.join('')}</tr>`);
  }
  return rows.join('');
}

function setStatus(message, type = 'info') {
  if (nodes.statusMessage) {
    nodes.statusMessage.className = `alert alert-${type} py-2 mb-3`;
    nodes.statusMessage.textContent = message;
    nodes.statusMessage.hidden = !message;
  }
}

function setEstimatesStatus(message, type = 'info') {
  if (nodes.estimatesStatus) {
    nodes.estimatesStatus.className = `alert alert-${type} py-2 mb-3`;
    nodes.estimatesStatus.textContent = message;
    nodes.estimatesStatus.hidden = !message;
  }
}

async function fetchJson(url) {
  const response = await fetch(url, { method: 'GET', headers: { Accept: 'application/json' } });
  const payload = await response.json();

  if (!response.ok) {
    throw new Error(payload.error || 'Erro na requisição.');
  }

  return payload;
}

function renderPagination(page, totalPages) {
  if (totalPages <= 1) {
    nodes.paginationList.innerHTML = '';
    return;
  }

  const items = [];

  const firstDisabled = page <= 1 ? ' disabled' : '';
  items.push(`
    <li class="page-item${firstDisabled}">
      <button class="page-link" type="button" data-page="1" aria-label="Primeira">
        &laquo;
      </button>
    </li>
  `);

  items.push(`
    <li class="page-item${firstDisabled}">
      <button class="page-link" type="button" data-page="${page - 1}" aria-label="Anterior">
        &lsaquo;
      </button>
    </li>
  `);

  const maxDisplayed = 5;
  let startPage = Math.max(1, page - 2);
  let endPage = Math.min(totalPages, page + 2);

  if (page <= 3) {
    endPage = Math.min(totalPages, maxDisplayed);
  } else if (page >= totalPages - 2) {
    startPage = Math.max(1, totalPages - maxDisplayed + 1);
  }

  if (startPage > 1) {
    items.push(`
      <li class="page-item">
        <button class="page-link" type="button" data-page="1">1</button>
      </li>
    `);
    if (startPage > 2) {
      items.push(`
        <li class="page-item disabled">
          <span class="page-link">...</span>
        </li>
      `);
    }
  }

  for (let i = startPage; i <= endPage; i++) {
    const active = i === page ? ' active' : '';
    items.push(`
      <li class="page-item${active}">
        <button class="page-link" type="button" data-page="${i}">${i}</button>
      </li>
    `);
  }

  if (endPage < totalPages) {
    if (endPage < totalPages - 1) {
      items.push(`
        <li class="page-item disabled">
          <span class="page-link">...</span>
        </li>
      `);
    }
    items.push(`
      <li class="page-item">
        <button class="page-link" type="button" data-page="${totalPages}">${totalPages}</button>
      </li>
    `);
  }

  const lastDisabled = page >= totalPages ? ' disabled' : '';
  items.push(`
    <li class="page-item${lastDisabled}">
      <button class="page-link" type="button" data-page="${page + 1}" aria-label="Próxima">
        &rsaquo;
      </button>
    </li>
  `);

  items.push(`
    <li class="page-item${lastDisabled}">
      <button class="page-link" type="button" data-page="${totalPages}" aria-label="Última">
        &raquo;
      </button>
    </li>
  `);

  nodes.paginationList.innerHTML = items.join('');
}

function renderRows(payload) {
  nodes.paginationMeta.textContent = `Página ${payload.page} de ${payload.total_pages} (${payload.total} registros)`;
  renderPagination(payload.page, payload.total_pages);

  nodes.dataBody.innerHTML = payload.rows.map((row) => {
    const classBadgeMap = {
      'Exposto': 'bg-danger-subtle text-danger border border-danger-subtle',
      'Não exposto': 'bg-success-subtle text-success border border-success-subtle',
      'Condicionalmente exposto': 'bg-warning-subtle text-warning border border-warning-subtle',
      'Revisar': 'bg-info-subtle text-info border border-info-subtle',
      'Não classificado': 'bg-light text-secondary border'
    };
    const directBadgeClass = classBadgeMap[row.no_classificacao] || 'bg-light text-secondary border';
    const inherited = row.classificacao_herdada_origem === 'Herdada';
    const finalClassificationLabel = row.no_classificacao_herdada || row.no_classificacao || 'Não classificado';
    const finalBadgeClass = classBadgeMap[finalClassificationLabel] || 'bg-light text-secondary border';
    const isCbo = String(row.no_origem_objeto).toUpperCase() === 'CBO';
    const cboLevels = {
      n1: 'Grande Grupo',
      n2: 'Subgrupo Principal',
      n3: 'Subgrupo',
      n4: 'Família',
      n5: 'Ocupação',
      nc: 'Não classificado'
    };
    const cnaeLevels = {
      n1: 'Seção',
      n2: 'Divisão',
      n3: 'Grupo',
      n4: 'Classe',
      n5: 'Subclasse',
      nc: 'Não classificado'
    };
    const levelLabel = isCbo
      ? (cboLevels[row.co_nivel_classificacao_herdada] || row.co_nivel_classificacao_herdada || '-')
      : (cnaeLevels[row.co_nivel_classificacao_herdada] || row.co_nivel_classificacao_herdada || '-');

    const finalOriginLabel = inherited
      ? `Herdada - ${levelLabel}`
      : row.classificacao_herdada_origem === 'Direta no item'
        ? 'Direta - Item'
        : row.classificacao_herdada_origem === 'Nao classificada'
          ? 'Não classificada'
          : 'Sem herança';
    const finalTagLabel = `${finalClassificationLabel} · ${finalOriginLabel}`;

    return `
      <tr>
        <td><span class="badge bg-secondary-subtle text-secondary-emphasis">${escapeHtml(row.no_origem_objeto)}</span></td>
        <td><span class="small text-body-secondary fw-semibold">${escapeHtml(row.no_tp_objeto)}</span></td>
        <td><a href="categoria.php?id_matriz=${encodeURIComponent(state.matrixId)}&co_objeto=${encodeURIComponent(row.co_objeto)}&co_tp_objeto=${encodeURIComponent(row.co_tp_objeto)}" class="text-decoration-none matrix-link-accent fw-bold">${escapeHtml(row.co_objeto)}</a></td>
        <td><a href="categoria.php?id_matriz=${encodeURIComponent(state.matrixId)}&co_objeto=${encodeURIComponent(row.co_objeto)}&co_tp_objeto=${encodeURIComponent(row.co_tp_objeto)}" class="text-decoration-none link-body-emphasis fw-semibold">${escapeHtml(row.no_objeto)}</a></td>
        <td><span class="badge py-1.5 px-2.5 rounded ${directBadgeClass}">${escapeHtml(row.no_classificacao)}</span></td>
        <td>
          <span class="badge py-1.5 px-2.5 rounded ${finalBadgeClass}">${escapeHtml(finalTagLabel)}</span>
        </td>
        <td>
          ${row.nu_probabilidade !== null && row.nu_probabilidade !== undefined && String(row.nu_probabilidade).trim() !== ''
            ? `<span class="badge bg-secondary-subtle text-body-emphasis border">${escapeHtml(row.nu_probabilidade)}${inherited ? ` · Herdada - ${levelLabel}` : ''}</span>`
            : '<span class="text-muted opacity-50 small">-</span>'
          }
        </td>
        <td class="small text-body-secondary">${escapeHtml(row.de_classificacao_observacao || '')}</td>
      </tr>
    `;
  }).join('');

  if (payload.rows.length === 0) {
    nodes.dataBody.innerHTML = `<tr><td colspan="8" class="text-center text-body-secondary py-4">Nenhum registro encontrado.</td></tr>`;
  }
}

function showEstimatesSkeleton() {
  if (nodes.estimatesSummary) {
    nodes.estimatesSummary.innerHTML = `
      <div class="col-md-4">
        <div class="matrix-empty-state p-3 placeholder-glow">
          <span class="placeholder col-6 mb-2"></span>
          <span class="placeholder col-10 d-block"></span>
        </div>
      </div>
      <div class="col-md-4">
        <div class="matrix-empty-state p-3 placeholder-glow">
          <span class="placeholder col-5 mb-2"></span>
          <span class="placeholder col-9 d-block"></span>
        </div>
      </div>
    `;
  }

  if (nodes.estimatesBody) {
    nodes.estimatesBody.innerHTML = showTableSkeleton(4, 10);
  }

  const estimatesTarget = nodes.estimatesGrid || nodes.estimatesMatrices;
  if (estimatesTarget) {
    estimatesTarget.innerHTML = Array.from({ length: 3 }, () => `
      <div class="estimate-criterion-card placeholder-glow">
        <span class="placeholder col-4 mb-3"></span>
        <span class="placeholder col-12 d-block mb-2"></span>
        <span class="placeholder col-12 d-block mb-2"></span>
        <span class="placeholder col-12 d-block"></span>
      </div>
    `).join('');
  }
}

function renderEstimates(payload) {
  const criteria = Array.isArray(payload.criteria) ? payload.criteria : [];
  const totalYears = Number(payload.total_anos_rais || 0);
  const formatAverage = (value) => Number(value || 0).toLocaleString('pt-BR', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  });
  const classBadgeMap = {
    'Exposto': 'bg-danger-subtle text-danger border border-danger-subtle',
    'Não exposto': 'bg-success-subtle text-success border border-success-subtle',
    'Condicionalmente exposto': 'bg-warning-subtle text-warning border border-warning-subtle',
    'Revisar': 'bg-info-subtle text-info border border-info-subtle',
    'Não classificado': 'bg-light text-secondary border',
    'Nao classificado': 'bg-light text-secondary border'
  };

  if (nodes.estimatesSummary) {
    nodes.estimatesSummary.innerHTML = `
      <div class="col-md-4">
        <div class="border rounded bg-body-secondary p-3 h-100">
          <div class="text-body-secondary small">Média anual de vínculos entre ${escapeHtml(payload.min_ano ?? '')} e ${escapeHtml(payload.max_ano ?? '')}</div>
          <div class="fs-4 fw-bold matrix-section-title">${formatAverage(payload.total_vinculos_por_criterio)}</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="border rounded bg-body-secondary p-3 h-100">
          <div class="text-body-secondary small">Criterios avaliados</div>
          <div class="fs-4 fw-bold text-body-emphasis">${criteria.length.toLocaleString('pt-BR')}</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="border rounded bg-body-secondary p-3 h-100">
          <div class="text-body-secondary small">Anos RAIS na media</div>
          <div class="fs-4 fw-bold text-body-emphasis">${totalYears.toLocaleString('pt-BR')}</div>
        </div>
      </div>
    `;
  }

  const rows = [];
  criteria.forEach((criterion) => {
    const classifications = Array.isArray(criterion.classifications) ? criterion.classifications : [];

    classifications.forEach((classification, index) => {
      const percent = Number(classification.percentual || 0);
      const badgeClass = classBadgeMap[classification.name] || 'bg-light text-secondary border';
      rows.push(`
        <tr>
          <td>${index === 0 ? `<span class="fw-semibold">${escapeHtml(criterion.label)}</span><div class="text-body-secondary small">${formatAverage(criterion.total_vinculos)} vinculos/ano</div>` : ''}</td>
          <td><span class="badge py-1.5 px-2.5 rounded ${badgeClass}">${escapeHtml(classification.name)}</span></td>
          <td class="fw-semibold">${formatAverage(classification.vinculos)}</td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="progress flex-grow-1 work-progress" role="progressbar" aria-valuenow="${escapeHtml(percent)}" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" data-progress-percent="${Math.max(0, Math.min(100, percent))}"></div>
              </div>
              <span class="small fw-semibold estimate-percent">${percent.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%</span>
            </div>
          </td>
        </tr>
      `);
    });
  });

  if (nodes.estimatesBody) {
    nodes.estimatesBody.innerHTML = rows.length > 0
      ? rows.join('')
      : '<tr><td colspan="4" class="text-center text-body-secondary py-4">Nenhuma estimativa encontrada.</td></tr>';
  }

  renderEstimateGrid(criteria, classBadgeMap, formatAverage);
}

function renderEstimateGrid(criteria, classBadgeMap, formatAverage) {
  const estimatesTarget = nodes.estimatesGrid || nodes.estimatesMatrices;
  if (!estimatesTarget) {
    return;
  }

  estimatesTarget.innerHTML = criteria.length > 0
    ? criteria.map((criterion) => renderEstimateCriterionCard(criterion, classBadgeMap, formatAverage)).join('')
    : '<div class="matrix-empty-state text-body-secondary text-center p-4">Nenhuma estimativa encontrada.</div>';
}

function renderEstimateCriterionCard(criterion, classBadgeMap, formatAverage) {
  const matrix = criterion.matrix || {};
  const cnaeClasses = Array.isArray(matrix.cnae_classes) ? matrix.cnae_classes : [];
  const cboClasses = Array.isArray(matrix.cbo_classes) ? matrix.cbo_classes : [];
  const cells = Array.isArray(matrix.cells) ? matrix.cells : [];
  const cellByPair = new Map(cells.map((cell) => [`${cell.cbo_class}_${cell.cnae_class}`, cell]));
  const classifications = Array.isArray(criterion.classifications) ? criterion.classifications : [];

  const matrixClassMap = {
    '0': 'estimate-cell-nex',
    '1': 'estimate-cell-cex',
    '2': 'estimate-cell-exp',
    '8': 'estimate-cell-review',
    '9': 'estimate-cell-ncl'
  };

  const tableRows = cboClasses.map((cbo) => {
    const classification = classifications.find(c => c.code === cbo.code) || {};
    const percent = Number(classification.percentual || 0);
    const badgeClass = classBadgeMap[classification.name] || 'bg-light text-secondary border';
    const classificationName = classification.name || cbo.name;
    const classificationVinculos = classification.vinculos !== undefined ? formatAverage(classification.vinculos) : '0';
    const percentText = percent.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';

    const leftCols = `
      <td class="border-0 bg-transparent py-2 px-1 align-middle matrix-col-classification">
        <span class="badge rounded ${badgeClass} w-100 d-block text-center py-1.5 px-2">${escapeHtml(classificationName)}</span>
      </td>
      <td class="border-0 bg-transparent py-2 px-2 align-middle matrix-col-progress">
        <div class="progress work-progress progress-thin" role="progressbar" aria-valuenow="${escapeHtml(percent)}" aria-valuemin="0" aria-valuemax="100">
          <div class="progress-bar" data-progress-percent="${Math.max(0, Math.min(100, percent))}"></div>
        </div>
      </td>
      <td class="border-0 bg-transparent py-2 px-1 align-middle text-end fw-semibold small text-nowrap matrix-col-total">
        ${classificationVinculos} (${percentText})
      </td>
    `;

    const rightCols = `
      <th scope="row" class="text-nowrap py-2 px-3 align-middle bg-body-tertiary fw-semibold matrix-small-text">
        ${escapeHtml(cbo.name)}
      </th>
      ${cnaeClasses.map((cnae) => {
        const cell = cellByPair.get(`${cbo.code}_${cnae.code}`) || {};
        const cellPercent = Number(cell.percentual || 0);
        const resultCode = String(cell.result_code || '9');
        return `
          <td class="align-middle text-center text-nowrap matrix-cell ${matrixClassMap[resultCode] || 'estimate-cell-ncl'}" title="${escapeHtml(cell.result_name || '')}">
            <span>${formatAverage(cell.vinculos)} (${cellPercent.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%)</span>
          </td>
        `;
      }).join('')}
    `;

    return `<tr>${leftCols}${rightCols}</tr>`;
  }).join('');

  return `
    <section class="estimate-criterion-card">
      <div class="estimate-criterion-header mb-3">
        <div>
          <h3 class="h6 mb-1">${escapeHtml(criterion.label)}</h3>
          <div class="text-body-secondary small">${formatAverage(criterion.total_vinculos)} vinculos/ano na media de ${Number(criterion.total_anos_rais || 0).toLocaleString('pt-BR')} anos</div>
        </div>
      </div>
      <div class="table-responsive matrix-table-wrap">
        <table class="table table-sm estimate-matrix-table matrix-table mb-0 align-middle">
          <thead>
            <tr>
              <th colspan="3" class="border-0 bg-transparent"></th>
              <th scope="col" class="text-nowrap bg-body-tertiary text-center align-middle matrix-small-heading">CBO \\ CNAE</th>
              ${cnaeClasses.map((item) => `<th scope="col" class="text-nowrap bg-body-tertiary text-center align-middle matrix-small-heading">${escapeHtml(item.name)}</th>`).join('')}
            </tr>
          </thead>
          <tbody>
            ${tableRows}
          </tbody>
        </table>
      </div>
    </section>
  `;
}

function renderEstimateMatrices(criteria, classBadgeMap) {
  if (!nodes.estimatesMatrices) {
    return;
  }

  const matrixClassMap = {
    '0': 'estimate-cell-nex',
    '1': 'estimate-cell-cex',
    '2': 'estimate-cell-exp',
    '8': 'estimate-cell-review',
    '9': 'estimate-cell-ncl'
  };

  const sections = criteria.map((criterion) => {
    const matrix = criterion.matrix || {};
    const cnaeClasses = Array.isArray(matrix.cnae_classes) ? matrix.cnae_classes : [];
    const cboClasses = Array.isArray(matrix.cbo_classes) ? matrix.cbo_classes : [];
    const cells = Array.isArray(matrix.cells) ? matrix.cells : [];
    const cellByPair = new Map(cells.map((cell) => [`${cell.cbo_class}_${cell.cnae_class}`, cell]));

    return `
      <section class="estimate-matrix-card">
        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
          <div>
            <h3 class="h6 mb-0">${escapeHtml(criterion.label)}</h3>
            <div class="text-body-secondary small">CBO x CNAE · ${formatAverage(matrix.total_vinculos_3x3)} vinculos/ano no 3x3</div>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm estimate-matrix-table matrix-table mb-0">
            <thead>
              <tr>
                <th scope="col">CBO \\ CNAE</th>
                ${cnaeClasses.map((item) => `<th scope="col">${escapeHtml(item.name)}</th>`).join('')}
              </tr>
            </thead>
            <tbody>
              ${cboClasses.map((cbo) => `
                <tr>
                  <th scope="row">${escapeHtml(cbo.name)}</th>
                  ${cnaeClasses.map((cnae) => {
                    const cell = cellByPair.get(`${cbo.code}_${cnae.code}`) || {};
                    const percent = Number(cell.percentual || 0);
                    const resultCode = String(cell.result_code || '9');
                    return `
                      <td class="${matrixClassMap[resultCode] || 'estimate-cell-ncl'}" title="${escapeHtml(cell.result_name || '')}">
                        <span>${formatAverage(cell.vinculos)} (${percent.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%)</span>
                      </td>
                    `;
                  }).join('')}
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </section>
    `;
  });

  nodes.estimatesMatrices.innerHTML = sections.length > 0
    ? sections.join('')
    : '<div class="matrix-empty-state text-body-secondary text-center p-4">Nenhuma tabela 3x3 encontrada.</div>';
}

async function loadEstimates(force = false) {
  if (estimatesLoaded && !force) {
    return;
  }

  setEstimatesStatus('Carregando estimativas de vínculos...');
  showEstimatesSkeleton();

  try {
    const params = new URLSearchParams({ id_matriz: state.matrixId });
    const payload = await fetchJson(`api/work/vinculos_estimativas.php?${params.toString()}`);
    renderEstimates(payload);
    estimatesLoaded = true;
    setEstimatesStatus('');
  } catch (err) {
    setEstimatesStatus(`${err.message} A consulta foi limitada para proteger a base de produção.`, 'danger');
  }
}

async function loadRows() {
  setStatus('Carregando dados...');
  nodes.dataBody.innerHTML = showTableSkeleton(8, 8);

  const params = new URLSearchParams({
    id_matriz: state.matrixId,
    page: String(state.page),
    per_page: String(state.perPage),
    q: state.query,
  });

  const activeFilters = state.filters
    .filter(f => f.column && f.selectedValues.length > 0)
    .map(f => ({ column: f.column, values: f.selectedValues }));

  if (activeFilters.length > 0) {
    params.set('filters', JSON.stringify(activeFilters));
  }

  try {
    const payload = await fetchJson(`api/work/classificacoes.php?${params.toString()}`);
    renderRows(payload);
    setStatus('');
  } catch (err) {
    setStatus(err.message, 'danger');
  }
}

function clearFilters() {
  state.filters = [];
  nodes.filterRowsContainer.innerHTML = '';
}

async function addFilterRow(preselectedColumn = '', preselectedValues = []) {
  const filterId = 'filter_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

  state.filters.push({
    id: filterId,
    column: preselectedColumn,
    selectedValues: preselectedValues
  });

  const rowHtml = `
    <div class="d-flex flex-wrap gap-2 align-items-center p-2 matrix-empty-state" id="${filterId}">
      <div class="flex-grow-1 filter-column-field">
        <select class="form-select form-select-sm filter-column-select">
          <option value="">Selecione o campo...</option>
          ${filterFields.map(field => {
            const selected = field.name === preselectedColumn ? ' selected' : '';
            return `<option value="${escapeHtml(field.name)}"${selected}>${escapeHtml(field.label)}</option>`;
          }).join('')}
        </select>
      </div>
      <div class="flex-grow-1 filter-values-field">
        <div class="dropdown filter-values-dropdown w-100">
          <button class="btn btn-sm btn-outline-secondary dropdown-toggle w-100 text-start d-flex justify-content-between align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" ${preselectedColumn ? '' : 'disabled'}>
            <span>Selecione valores...</span>
          </button>
          <ul class="dropdown-menu p-2 shadow-sm dropdown-menu-scroll">
          </ul>
        </div>
      </div>
      <button class="btn btn-sm btn-outline-danger remove-filter-btn" type="button" title="Remover filtro">
        &times;
      </button>
    </div>
  `;

  nodes.filterRowsContainer.insertAdjacentHTML('beforeend', rowHtml);

  const rowElement = document.getElementById(filterId);
  const colSelect = rowElement.querySelector('.filter-column-select');
  const removeBtn = rowElement.querySelector('.remove-filter-btn');
  const dropdownToggleBtn = rowElement.querySelector('.dropdown-toggle');
  const dropdownMenu = rowElement.querySelector('.dropdown-menu');

  removeBtn.addEventListener('click', async () => {
    state.filters = state.filters.filter(f => f.id !== filterId);
    rowElement.remove();
    state.page = 1;
    await loadRows();
  });

  async function loadValuesForSelect(selectedColumn, selectedVals = []) {
    dropdownToggleBtn.disabled = false;
    dropdownToggleBtn.querySelector('span').textContent = 'Carregando...';
    dropdownMenu.innerHTML = '<li class="text-center py-2 text-body-secondary"><span class="spinner-border spinner-border-sm me-2"></span>Carregando...</li>';

    try {
      const vals = await fetchJson(`api/work/unique_values.php?id_matriz=${encodeURIComponent(state.matrixId)}&column=${encodeURIComponent(selectedColumn)}`);

      if (vals.length === 0) {
        dropdownMenu.innerHTML = '<li class="text-center py-2 text-body-secondary">Nenhum valor encontrado</li>';
        dropdownToggleBtn.querySelector('span').textContent = 'Sem valores';
        dropdownToggleBtn.disabled = true;
        return;
      }

      const btnSpan = dropdownToggleBtn.querySelector('span');
      if (selectedVals.length === 0) {
        btnSpan.textContent = 'Selecione valores...';
      } else if (selectedVals.length === 1) {
        btnSpan.textContent = selectedVals[0] === '' ? '[vazio]' : selectedVals[0];
      } else {
        btnSpan.textContent = `${selectedVals.length} selecionados`;
      }

      dropdownMenu.innerHTML = vals.map((val, idx) => {
        const escapedVal = escapeHtml(val);
        const checked = selectedVals.includes(val) ? ' checked' : '';
        const checkId = `${filterId}_val_${idx}`;
        return `
          <li>
            <div class="form-check">
              <input class="form-check-input filter-value-checkbox" type="checkbox" value="${escapedVal}" id="${checkId}"${checked}>
              <label class="form-check-label text-truncate d-block w-100" for="${checkId}" title="${escapedVal}">
                ${escapedVal === '' ? '<em>[vazio]</em>' : escapedVal}
              </label>
            </div>
          </li>
        `;
      }).join('');

      const checkboxes = dropdownMenu.querySelectorAll('.filter-value-checkbox');
      checkboxes.forEach(chk => {
        chk.addEventListener('change', async () => {
          const checkedVals = Array.from(checkboxes)
            .filter(c => c.checked)
            .map(c => c.value);

          const filterItem = state.filters.find(f => f.id === filterId);
          if (filterItem) {
            filterItem.selectedValues = checkedVals;
          }

          if (checkedVals.length === 0) {
            btnSpan.textContent = 'Selecione valores...';
          } else if (checkedVals.length === 1) {
            btnSpan.textContent = checkedVals[0] === '' ? '[vazio]' : checkedVals[0];
          } else {
            btnSpan.textContent = `${checkedVals.length} selecionados`;
          }

          state.page = 1;
          await loadRows();
        });
      });

    } catch (err) {
      dropdownMenu.innerHTML = `<li class="text-danger p-2 small">Erro: ${escapeHtml(err.message)}</li>`;
      dropdownToggleBtn.querySelector('span').textContent = 'Erro ao carregar';
    }
  }

  colSelect.addEventListener('change', async () => {
    const selectedColumn = colSelect.value;
    const filterItem = state.filters.find(f => f.id === filterId);
    if (filterItem) {
      filterItem.column = selectedColumn;
      filterItem.selectedValues = [];
    }

    if (!selectedColumn) {
      dropdownToggleBtn.disabled = true;
      dropdownToggleBtn.querySelector('span').textContent = 'Selecione valores...';
      dropdownMenu.innerHTML = '';
      state.page = 1;
      await loadRows();
      return;
    }

    await loadValuesForSelect(selectedColumn);
    state.page = 1;
    await loadRows();
  });

  if (preselectedColumn) {
    await loadValuesForSelect(preselectedColumn, preselectedValues);
  }
}

async function init() {
  const urlParams = new URLSearchParams(window.location.search);
  const filtersParam = urlParams.get('filters');

  nodes.searchForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    state.query = nodes.searchInput.value.trim();
    state.page = 1;
    await loadRows();
  });

  nodes.paginationList.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-page]');
    if (!button || button.parentElement.classList.contains('disabled')) {
      return;
    }

    state.page = parseInt(button.dataset.page, 10);
    await loadRows();
  });

  nodes.addFilterBtn.addEventListener('click', () => addFilterRow());
  if (nodes.estimatesTab) {
    nodes.estimatesTab.addEventListener('shown.bs.tab', () => loadEstimates());
  }
  if (nodes.refreshEstimatesBtn) {
    nodes.refreshEstimatesBtn.addEventListener('click', () => loadEstimates(true));
  }

  // Apply url parameters if present
  if (filtersParam) {
    try {
      const decodedFilters = JSON.parse(filtersParam);
      if (Array.isArray(decodedFilters)) {
        clearFilters();
        for (const f of decodedFilters) {
          if (f.column) {
            await addFilterRow(f.column, f.values || []);
          }
        }
      }
    } catch (e) {
      console.error("Erro ao aplicar filtros da URL:", e);
    }
  }

  await loadRows();
}

init();
