const state = {
  tables: [],
  readableObjects: [],
  selectedDataGroup: 'table',
  selectedTable: '',
  page: 1,
  perPage: 50,
  query: '',
  sort: '',
  dir: 'asc',
  view: 'objects',
  inventory: null,
  selectedObjectGroup: 'tables',
  objectPage: 1,
  objectPerPage: 25,
  filters: [],
};
const nodes = {
  docsTab: document.getElementById('docsTab'),
  docsPanel: document.getElementById('docsPanel'),
  docsList: document.getElementById('docsList'),
  docViewerTitle: document.getElementById('docViewerTitle'),
  docViewerContent: document.getElementById('docViewerContent'),
  dataTab: document.getElementById('dataTab'),
  objectsTab: document.getElementById('objectsTab'),
  ambienteTab: document.getElementById('ambienteTab'),
  dataSidebar: document.getElementById('dataSidebar'),
  dataPanel: document.getElementById('dataPanel'),
  objectsPanel: document.getElementById('objectsPanel'),
  ambientePanel: document.getElementById('ambientePanel'),
  dataTypeTabs: document.getElementById('dataTypeTabs'),
  tableFilter: document.getElementById('tableFilter'),
  tableList: document.getElementById('tableList'),
  tableTitle: document.getElementById('tableTitle'),
  tableMeta: document.getElementById('tableMeta'),
  searchForm: document.getElementById('searchForm'),
  searchInput: document.getElementById('searchInput'),
  statusMessage: document.getElementById('statusMessage'),
  dataHead: document.getElementById('dataHead'),
  dataBody: document.getElementById('dataBody'),
  paginationMeta: document.getElementById('paginationMeta'),
  paginationList: document.getElementById('paginationList'),
  refreshObjects: document.getElementById('refreshObjects'),
  objectsMeta: document.getElementById('objectsMeta'),
  objectTypeTabs: document.getElementById('objectTypeTabs'),
  objectTypeContent: document.getElementById('objectTypeContent'),
  objectPaginationMeta: document.getElementById('objectPaginationMeta'),
  objectPaginationList: document.getElementById('objectPaginationList'),
  filterSection: document.getElementById('filterSection'),
  addFilterBtn: document.getElementById('addFilterBtn'),
  filterRowsContainer: document.getElementById('filterRowsContainer'),
  objectsFilter: document.getElementById('objectsFilter'),
};

const objectLabels = {
  tables: 'Tabelas',
  views: 'Views',
  materialized_views: 'Materialized views',
  triggers: 'Triggers',
  routines: 'Rotinas',
  sequences: 'Sequencias',
  indexes: 'Indices',
  constraints: 'Constraints',
  types: 'Tipos',
};

const dataGroupLabels = {
  table: 'Tabelas',
  view: 'Views',
  materialized_view: 'Materialized views',
};

const objectGroupToDataGroup = {
  tables: 'table',
  views: 'view',
  materialized_views: 'materialized_view',
};

function objectItemsFor(inventory, key) {
  if (!inventory) {
    return [];
  }

  if (key === 'tables') {
    return Array.isArray(inventory.relations)
      ? inventory.relations.filter((item) => ['table', 'partitioned_table', 'foreign_table'].includes(item.type))
      : [];
  }

  return Array.isArray(inventory[key]) ? inventory[key] : [];
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

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function showTableSkeleton(columnCount = 5, rowCount = 8) {
  const rows = [];
  for (let i = 0; i < rowCount; i++) {
    const cells = [];
    for (let j = 0; j < columnCount; j++) {
      const widthPercent = 40 + Math.floor(Math.random() * 50); // random width between 40% and 90%
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

function showObjectTabsSkeleton() {
  nodes.objectTypeTabs.innerHTML = Object.values(objectLabels).map((label, index) => `
    <li class="nav-item" role="presentation">
      <button class="nav-link${index === 0 ? ' active' : ''} disabled" type="button" disabled>
        ${escapeHtml(label)}
        <span class="badge rounded-pill text-bg-secondary ms-1 placeholder-glow">
          <span class="placeholder object-tab-count-placeholder"></span>
        </span>
      </button>
    </li>
  `).join('');
}

function showObjectTableSkeleton(columnCount = 5, rowCount = 10) {
  const headers = ['Dados', 'Nome', 'Tipo', 'Linhas', 'Comentario'].slice(0, columnCount);
  const rows = [];

  for (let i = 0; i < rowCount; i++) {
    const cells = [];

    for (let j = 0; j < columnCount; j++) {
      const widthPercent = j === 0 ? 48 : 36 + ((i + j) % 5) * 12;
      cells.push(`
        <td>
          <div class="placeholder-glow">
            <span class="placeholder col-12 object-table-placeholder" data-placeholder-width="${widthPercent}"></span>
          </div>
        </td>
      `);
    }

    rows.push(`<tr>${cells.join('')}</tr>`);
  }

  nodes.objectTypeContent.innerHTML = `
    <section>
      <div class="d-flex align-items-baseline justify-content-between gap-2 mb-2">
        <h2 class="h5 mb-0 placeholder-glow">
          <span class="placeholder object-title-placeholder"></span>
        </h2>
        <span class="text-body-secondary small placeholder-glow">
          <span class="placeholder object-count-placeholder"></span>
        </span>
      </div>
      <div class="table-responsive border rounded-2">
        <table class="table table-sm table-striped object-table mb-0" aria-hidden="true">
          <thead>
            <tr>${headers.map((header) => `<th scope="col">${escapeHtml(header)}</th>`).join('')}</tr>
          </thead>
          <tbody>${rows.join('')}</tbody>
        </table>
      </div>
    </section>
  `;
}

function setStatus(message, type = 'info') {
  nodes.statusMessage.className = `alert alert-${type} py-2`;
  nodes.statusMessage.textContent = message;
  nodes.statusMessage.hidden = !message;
}

async function fetchJson(url) {
  const response = await fetch(url, { method: 'GET', headers: { Accept: 'application/json' } });
  const payload = await response.json();

  if (!response.ok) {
    throw new Error(payload.error || 'Erro na requisicao.');
  }

  return payload;
}

const docFiles = [
  { name: 'Bootstrap-first: planejamento', path: 'platform/docs/bootstrap-first-planejamento.md', desc: 'Fonte de verdade para a reconstrucao visual com Bootstrap vanilla, sem paleta ou tema paralelo.' },
  { name: 'Bootstrap-first: exemplos', path: 'platform/docs/bootstrap-first-exemplos.md', desc: 'Exemplos de pagina, card, tabela, filtro, abas, modal, estado vazio e progresso usando Bootstrap.' },
  { name: 'Diretrizes visuais RENAST', path: 'platform/docs/diretrizes-visuais-renast.md', desc: 'Referencia visual para boxes, botoes, componentes, cores e tema claro.' },
  { name: 'Documentacao visual centralizada', path: 'platform/docs/documentacao-visual-centralizada.md', desc: 'Indice operacional da documentacao visual e dos documentos substituidos.' },
  { name: 'Tema e CSS por modulo', path: 'platform/docs/tema-css-bootstrap-modulos.md', desc: 'Contrato por modulo durante a fase Bootstrap-first.' },
  { name: 'README.md', path: 'README.md', desc: 'Guia de entrada do projeto com instruções de execução local, configuração e segurança.' },
  { name: 'landing.md', path: 'landing.md', desc: 'Página de apresentação inicial da ferramenta com informações institucionais.' },
  { name: 'sobre.md', path: 'sobre.md', desc: 'Apresentação do projeto CAREX, contextualização de saúde pública e objetivos da matriz.' },
  { name: 'criterios-conciliacao.md', path: 'criterios-conciliacao.md', desc: 'Critérios de Consolidação de Vínculos (CNAE x CBO) — 10 lógicas matriciais usadas na view materializada de conciliação.' },
  { name: 'migracao_producao.md', path: 'docs/migracao_producao.md', desc: 'Guia passo a passo para configurar o Google OAuth real e fazer o deploy em produção.' },
  { name: 'api.md', path: 'docs/api.md', desc: 'Documentação detalhada dos endpoints HTTP REST expostos pela aplicação.' },
  { name: 'banco-dados.md', path: 'docs/banco-dados.md', desc: 'Dicionário de dados, configurações do PostgreSQL e inventário completo de objetos.' },
  { name: 'decisoes-e-pendencias.md', path: 'docs/decisoes-e-pendencias.md', desc: 'Registro histórico de decisões de arquitetura e pendências de desenvolvimento.' },
  { name: 'modulo-desenvolvimento.md', path: 'docs/modulo-desenvolvimento.md', desc: 'Detalhamento do módulo de Desenvolvimento, incluindo auditoria estrutural.' },
  { name: 'modulo-trabalho.md', path: 'docs/modulo-trabalho.md', desc: 'Funcionamento operacional da matriz de exposição e das lógicas de classificação.' },
  { name: 'visao-geral.md', path: 'docs/visao-geral.md', desc: 'Visão conceitual e arquitetônica do ecossistema CAREX e da Plataforma RENAST.' }
];

function parseSimpleMarkdown(md) {
  const lines = md.split('\n');
  let inTable = false;
  let tableRows = [];
  let parsedLines = [];
  let inCodeBlock = false;
  let codeBlockLines = [];

  for (let line of lines) {
    if (line.trim().startsWith('```')) {
      if (inCodeBlock) {
        // End of code block
        inCodeBlock = false;
        parsedLines.push(`<pre class="bg-body-tertiary border rounded p-3 my-3 font-monospace small overflow-auto"><code>${escapeHtml(codeBlockLines.join('\n'))}</code></pre>`);
        codeBlockLines = [];
      } else {
        inCodeBlock = true;
      }
      continue;
    }

    if (inCodeBlock) {
      codeBlockLines.push(line);
      continue;
    }

    if (line.trim().startsWith('|')) {
      inTable = true;
      const cells = line.split('|').map(c => c.trim()).filter((c, idx, arr) => idx > 0 && idx < arr.length - 1);
      tableRows.push(cells);
    } else {
      if (inTable) {
        if (tableRows.length > 0) {
          let tableHtml = '<div class="table-responsive my-3"><table class="table table-sm table-striped table-bordered align-middle">';
          tableRows.forEach((row, rIdx) => {
            if (row.every(c => c.startsWith('---') || c.startsWith(':---') || c.endsWith('---'))) {
              return;
            }
            tableHtml += '<tr>';
            row.forEach(cell => {
              const tag = rIdx === 0 ? 'th' : 'td';
              let parsedCell = escapeHtml(cell)
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/`(.*?)`/g, '<code>$1</code>');
              tableHtml += `<${tag}>${parsedCell}</${tag}>`;
            });
            tableHtml += '</tr>';
          });
          tableHtml += '</table></div>';
          parsedLines.push(tableHtml);
        }
        tableRows = [];
        inTable = false;
      }

      let trimmed = line.trim();
      if (trimmed.startsWith('#')) {
        let level = trimmed.match(/^#+/)[0].length;
        let text = trimmed.substring(level).trim();
        let escapedText = escapeHtml(text)
          .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
          .replace(/`(.*?)`/g, '<code>$1</code>');
        parsedLines.push(`<h${level} class="mt-4 mb-2 pb-1 text-primary border-bottom">${escapedText}</h${level}>`);
      } else if (trimmed.startsWith('-') || trimmed.startsWith('*')) {
        let text = trimmed.substring(1).trim();
        let escapedText = escapeHtml(text)
          .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
          .replace(/`(.*?)`/g, '<code>$1</code>');
        escapedText = escapedText.replace(/\[(.*?)\]\((.*?)\)/g, '<a href="#" class="doc-link" data-doc-file="$2">$1</a>');
        parsedLines.push(`<ul class="mb-1"><li>${escapedText}</li></ul>`);
      } else if (trimmed !== '') {
        let escapedText = escapeHtml(line)
          .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
          .replace(/`(.*?)`/g, '<code>$1</code>');
        escapedText = escapedText.replace(/\[(.*?)\]\((.*?)\)/g, '<a href="#" class="doc-link" data-doc-file="$2">$1</a>');
        parsedLines.push(`<p class="mb-2">${escapedText}</p>`);
      } else {
        parsedLines.push('<div class="my-1"></div>');
      }
    }
  }
  return parsedLines.join('\n');
}

function renderDocsList() {
  nodes.docsList.innerHTML = docFiles.map((doc) => {
    return `
      <button type="button" class="list-group-item list-group-item-action flex-column align-items-start py-2 px-3" data-doc-path="${escapeHtml(doc.path)}">
        <div class="d-flex w-100 justify-content-between">
          <h5 class="mb-1 h6 text-primary fw-bold">${escapeHtml(doc.name)}</h5>
          <small class="text-body-secondary">${doc.path.includes('/') ? 'Subpasta' : 'Raiz'}</small>
        </div>
        <p class="mb-1 text-body-secondary small">${escapeHtml(doc.desc)}</p>
      </button>
    `;
  }).join('');
}

async function selectDoc(docPath) {
  const itemBtns = nodes.docsList.querySelectorAll('[data-doc-path]');
  itemBtns.forEach(btn => {
    btn.classList.toggle('active', btn.dataset.docPath === docPath);
  });

  nodes.docViewerTitle.textContent = 'Carregando documento...';
  nodes.docViewerContent.innerHTML = `
    <div class="text-center py-5 text-body-secondary">
      <div class="spinner-border spinner-border-sm me-2" role="status"></div>
      Carregando conteúdo do arquivo markdown...
    </div>
  `;

  try {
    const data = await fetchJson(`api/development/doc_content.php?file=${encodeURIComponent(docPath)}`);
    nodes.docViewerTitle.textContent = data.file;
    nodes.docViewerContent.innerHTML = parseSimpleMarkdown(data.content);

    // Bind inner document links dynamically
    nodes.docViewerContent.querySelectorAll('.doc-link').forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const fileLink = link.dataset.docFile;
        // Resolve simple links
        const target = docFiles.find(d => d.path === fileLink || d.name === fileLink || fileLink.endsWith(d.path));
        if (target) {
          selectDoc(target.path);
        }
      });
    });
  } catch (err) {
    nodes.docViewerTitle.textContent = 'Erro';
    nodes.docViewerContent.innerHTML = `<div class="alert alert-danger py-2">${escapeHtml(err.message)}</div>`;
  }
}

function setView(view) {
  state.view = view;
  const showData = view === 'data';
  const showDocs = view === 'docs';
  const showObjects = view === 'objects';
  const showAmbiente = view === 'ambiente';

  nodes.dataTab.classList.toggle('active', showData);
  nodes.objectsTab.classList.toggle('active', showObjects);
  nodes.docsTab.classList.toggle('active', showDocs);
  if (nodes.ambienteTab) nodes.ambienteTab.classList.toggle('active', showAmbiente);

  nodes.dataSidebar.hidden = !showData;
  nodes.dataPanel.hidden = !showData;
  nodes.objectsPanel.hidden = !showObjects;
  nodes.docsPanel.hidden = !showDocs;
  if (nodes.ambientePanel) nodes.ambientePanel.hidden = !showAmbiente;

  if (showObjects && !state.inventory) {
    loadInventory();
  }
  if (showDocs) {
    renderDocsList();
  }
}

function readableObjectsForSelectedGroup() {
  return state.readableObjects.filter((object) => object.type === state.selectedDataGroup);
}

function renderDataTypeTabs() {
  nodes.dataTypeTabs.innerHTML = Object.entries(dataGroupLabels).map(([key, label]) => {
    const active = key === state.selectedDataGroup ? ' active' : '';
    const count = state.readableObjects.filter((object) => object.type === key).length;

    return `
      <li class="nav-item" role="presentation">
        <button class="nav-link${active}" type="button" data-data-group="${escapeHtml(key)}">
          ${escapeHtml(label)}
          <span class="badge rounded-pill text-bg-secondary ms-1">${count}</span>
        </button>
      </li>
    `;
  }).join('');
}

function renderTables() {
  const query = nodes.tableFilter.value.trim().toLowerCase();
  // Filtra apenas a partir de 3 letras. Caso contrário, exibe todos os objetos do grupo selecionado.
  const tables = (query.length >= 3)
    ? readableObjectsForSelectedGroup().filter((table) => table.name.toLowerCase().includes(query))
    : readableObjectsForSelectedGroup();

  nodes.tableList.innerHTML = tables.map((table) => {
    const active = table.name === state.selectedTable ? ' active' : '';
    const escapedName = escapeHtml(table.name);
    return `
      <button type="button" class="list-group-item list-group-item-action${active}" data-table="${escapedName}" title="${escapedName}">
        <span class="d-block text-truncate" title="${escapedName}">${escapedName}</span>
        <span class="small opacity-75">${table.columns.length} colunas</span>
      </button>
    `;
  }).join('');
}

function renderPagination(page, totalPages) {
  if (totalPages <= 1) {
    nodes.paginationList.innerHTML = '';
    return;
  }

  const items = [];

  // "Primeira" button
  const firstDisabled = page <= 1 ? ' disabled' : '';
  items.push(`
    <li class="page-item${firstDisabled}">
      <button class="page-link" type="button" data-page="1" aria-label="Primeira">
        &laquo;
      </button>
    </li>
  `);

  // "Anterior" button
  items.push(`
    <li class="page-item${firstDisabled}">
      <button class="page-link" type="button" data-page="${page - 1}" aria-label="Anterior">
        &lsaquo;
      </button>
    </li>
  `);

  // Page Numbers
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

  // "Proxima" button
  const lastDisabled = page >= totalPages ? ' disabled' : '';
  items.push(`
    <li class="page-item${lastDisabled}">
      <button class="page-link" type="button" data-page="${page + 1}" aria-label="Proxima">
        &rsaquo;
      </button>
    </li>
  `);

  // "Ultima" button
  items.push(`
    <li class="page-item${lastDisabled}">
      <button class="page-link" type="button" data-page="${totalPages}" aria-label="Ultima">
        &raquo;
      </button>
    </li>
  `);

  nodes.paginationList.innerHTML = items.join('');
}

function renderObjectPagination(totalItems) {
  if (!nodes.objectPaginationMeta || !nodes.objectPaginationList) {
    return;
  }

  const totalPages = Math.max(1, Math.ceil(totalItems / state.objectPerPage));
  state.objectPage = Math.min(Math.max(1, state.objectPage), totalPages);
  const startItem = totalItems === 0 ? 0 : ((state.objectPage - 1) * state.objectPerPage) + 1;
  const endItem = Math.min(totalItems, state.objectPage * state.objectPerPage);

  nodes.objectPaginationMeta.textContent = totalItems > 0
    ? `Exibindo ${startItem}-${endItem} de ${totalItems} objetos`
    : '';

  if (totalPages <= 1) {
    nodes.objectPaginationList.innerHTML = '';
    return;
  }

  const items = [];
  const firstDisabled = state.objectPage <= 1 ? ' disabled' : '';
  const lastDisabled = state.objectPage >= totalPages ? ' disabled' : '';
  const maxDisplayed = 5;
  let startPage = Math.max(1, state.objectPage - 2);
  let endPage = Math.min(totalPages, state.objectPage + 2);

  if (state.objectPage <= 3) {
    endPage = Math.min(totalPages, maxDisplayed);
  } else if (state.objectPage >= totalPages - 2) {
    startPage = Math.max(1, totalPages - maxDisplayed + 1);
  }

  items.push(`
    <li class="page-item${firstDisabled}">
      <button class="page-link" type="button" data-object-page="1" aria-label="Primeira">&laquo;</button>
    </li>
    <li class="page-item${firstDisabled}">
      <button class="page-link" type="button" data-object-page="${state.objectPage - 1}" aria-label="Anterior">&lsaquo;</button>
    </li>
  `);

  if (startPage > 1) {
    items.push(`<li class="page-item"><button class="page-link" type="button" data-object-page="1">1</button></li>`);
    if (startPage > 2) {
      items.push('<li class="page-item disabled"><span class="page-link">...</span></li>');
    }
  }

  for (let page = startPage; page <= endPage; page++) {
    const active = page === state.objectPage ? ' active' : '';
    items.push(`<li class="page-item${active}"><button class="page-link" type="button" data-object-page="${page}">${page}</button></li>`);
  }

  if (endPage < totalPages) {
    if (endPage < totalPages - 1) {
      items.push('<li class="page-item disabled"><span class="page-link">...</span></li>');
    }
    items.push(`<li class="page-item"><button class="page-link" type="button" data-object-page="${totalPages}">${totalPages}</button></li>`);
  }

  items.push(`
    <li class="page-item${lastDisabled}">
      <button class="page-link" type="button" data-object-page="${state.objectPage + 1}" aria-label="Proxima">&rsaquo;</button>
    </li>
    <li class="page-item${lastDisabled}">
      <button class="page-link" type="button" data-object-page="${totalPages}" aria-label="Ultima">&raquo;</button>
    </li>
  `);

  nodes.objectPaginationList.innerHTML = items.join('');
}

function renderRows(payload) {
  nodes.tableTitle.textContent = payload.table;
  nodes.tableMeta.textContent = `${dataGroupLabels[state.selectedDataGroup]} | ${payload.total} registros, ${payload.columns.length} colunas`;
  nodes.paginationMeta.textContent = `Página ${payload.page} de ${payload.total_pages}`;
  renderPagination(payload.page, payload.total_pages);

  nodes.dataHead.innerHTML = `
    <tr>
      ${payload.columns.map((column) => {
        const marker = state.sort === column ? (state.dir === 'asc' ? ' [asc]' : ' [desc]') : '';
        return `<th scope="col"><button type="button" data-sort="${escapeHtml(column)}">${escapeHtml(column)}${marker}</button></th>`;
      }).join('')}
    </tr>
  `;

  nodes.dataBody.innerHTML = payload.rows.map((row) => `
    <tr>
      ${payload.columns.map((column) => `<td title="${escapeHtml(row[column])}">${escapeHtml(row[column])}</td>`).join('')}
    </tr>
  `).join('');

  if (payload.rows.length === 0) {
    nodes.dataBody.innerHTML = `<tr><td colspan="${payload.columns.length}" class="text-center text-body-secondary py-4">Nenhum registro encontrado.</td></tr>`;
  }
}

function renderObjectTabs(inventory) {
  nodes.objectTypeTabs.innerHTML = Object.entries(objectLabels).map(([key, label]) => {
    const active = key === state.selectedObjectGroup ? ' active' : '';
    const count = objectItemsFor(inventory, key).length;

    return `
      <li class="nav-item" role="presentation">
        <button class="nav-link${active}" type="button" data-object-group="${escapeHtml(key)}">
          ${escapeHtml(label)}
          <span class="badge rounded-pill text-bg-secondary ms-1">${count}</span>
        </button>
      </li>
    `;
  }).join('');
}

function renderObjectRows(label, items, groupKey) {
  if (!items.length) {
    return `
      <section>
        <h2 class="h5 mb-2">${escapeHtml(label)}</h2>
        <p class="text-body-secondary mb-0">Nenhum objeto encontrado.</p>
      </section>
    `;
  }

  const columns = Object.keys(items[0]);
  const dataGroup = objectGroupToDataGroup[groupKey] || '';
  const showDataAction = dataGroup !== '';
  const headers = showDataAction ? ['acao', ...columns] : columns;

  const headersMapping = {
    'acao': 'Acoes',
    'name': 'Nome',
    'type': 'Tipo',
    'estimated_rows': 'Linhas',
    'comment': 'Comentário',
    'definition': 'Definição',
    'table_name': 'Tabela',
    'status': 'Status',
    'function_name': 'Função',
    'language': 'Linguagem',
    'arguments': 'Argumentos',
    'returns': 'Retorno',
    'data_type': 'Tipo de dado',
    'start_value': 'Valor inicial',
    'min_value': 'Valor mínimo',
    'max_value': 'Valor máximo',
    'increment_by': 'Incremento',
    'cycle': 'Ciclo',
    'cache_size': 'Tamanho do cache',
    'hasindexes': 'Tem índices',
    'ispopulated': 'Populada'
  };

  return `
    <section>
      <div class="d-flex align-items-baseline justify-content-between gap-2 mb-2">
        <h2 class="h5 mb-0">${escapeHtml(label)}</h2>
        <span class="text-body-secondary small">${items.length} nesta pagina</span>
      </div>
    <div class="table-responsive border rounded-2">
      <table class="table table-sm table-striped object-table mb-0">
        <thead>
          <tr>${headers.map((column) => `<th scope="col">${escapeHtml(headersMapping[column] || column)}</th>`).join('')}</tr>
        </thead>
        <tbody>
          ${items.map((item) => `
            <tr>
              ${showDataAction ? `
                <td class="object-action-cell">
                  <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-primary object-action-button" type="button" data-open-data="${escapeHtml(item.name)}" data-data-group="${escapeHtml(dataGroup)}" title="Visualizar dados">
                      Dados
                    </button>
                    <button class="btn btn-sm btn-outline-secondary btn-show-columns object-action-button" type="button" data-object-name="${escapeHtml(item.name)}" title="Ver colunas">
                      Colunas
                    </button>
                  </div>
                </td>
              ` : ''}
              ${columns.map((column) => {
                const value = item[column];
                const content = column === 'definition'
                  ? `<pre class="object-definition mb-0">${escapeHtml(value)}</pre>`
                  : escapeHtml(value);
                const titleAttr = column === 'definition' ? '' : ` title="${escapeHtml(value)}"`;
                return `<td${titleAttr}>${content}</td>`;
              }).join('')}
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>
    </section>
  `;
}

function renderInventory(inventory) {
  nodes.objectsMeta.textContent = `Schema ${inventory.schema}`;
  renderObjectTabs(inventory);

  const label = objectLabels[state.selectedObjectGroup] || 'Objetos';
  let items = objectItemsFor(inventory, state.selectedObjectGroup);

  const query = nodes.objectsFilter.value.trim().toLowerCase();
  if (query.length >= 3) {
    items = items.filter((item) => {
      return Object.values(item).some((val) => String(val).toLowerCase().includes(query));
    });
  }

  const totalItems = items.length;
  const totalPages = Math.max(1, Math.ceil(totalItems / state.objectPerPage));
  state.objectPage = Math.min(Math.max(1, state.objectPage), totalPages);
  const start = (state.objectPage - 1) * state.objectPerPage;
  const pagedItems = items.slice(start, start + state.objectPerPage);

  nodes.objectTypeContent.innerHTML = renderObjectRows(label, pagedItems, state.selectedObjectGroup);
  renderObjectPagination(totalItems);
}

async function loadRows() {
  if (!state.selectedTable) {
    return;
  }

  setStatus('Carregando dados...');
  const colCount = nodes.dataHead.querySelectorAll('th').length || 6;
  nodes.dataBody.innerHTML = showTableSkeleton(colCount, 8);

  const params = new URLSearchParams({
    table: state.selectedTable,
    page: String(state.page),
    per_page: String(state.perPage),
    q: state.query,
    sort: state.sort,
    dir: state.dir,
  });

  const activeFilters = state.filters
    .filter(f => f.column && f.selectedValues.length > 0)
    .map(f => ({ column: f.column, values: f.selectedValues }));

  if (activeFilters.length > 0) {
    params.set('filters', JSON.stringify(activeFilters));
  }

  const payload = await fetchJson(`api/rows.php?${params.toString()}`);
  renderRows(payload);
  setStatus('');
}

function clearFilters() {
  state.filters = [];
  nodes.filterRowsContainer.innerHTML = '';
}

function updateFilterSectionVisibility() {
  if (state.selectedTable) {
    nodes.filterSection.hidden = false;
  } else {
    nodes.filterSection.hidden = true;
    clearFilters();
  }
}

async function addFilterRow(preselectedColumn = '', preselectedValues = []) {
  const currentObject = state.readableObjects.find(o => o.name === state.selectedTable);
  const columns = currentObject ? currentObject.columns : [];
  if (columns.length === 0) return;

  const filterId = 'filter_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

  state.filters.push({
    id: filterId,
    column: preselectedColumn,
    selectedValues: preselectedValues
  });

  const rowHtml = `
    <div class="d-flex flex-wrap gap-2 align-items-center bg-body p-2 border rounded shadow-sm" id="${filterId}">
      <div class="flex-grow-1 filter-column-field">
        <select class="form-select form-select-sm filter-column-select">
          <option value="">Selecione o campo...</option>
          ${columns.map(col => {
            const selected = col.name === preselectedColumn ? ' selected' : '';
            return `<option value="${escapeHtml(col.name)}"${selected}>${escapeHtml(col.name)} (${escapeHtml(col.type)})</option>`;
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
      const vals = await fetchJson(`api/unique_values.php?table=${encodeURIComponent(state.selectedTable)}&column=${encodeURIComponent(selectedColumn)}`);

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

async function loadInventory(force = false) {
  if (state.inventory && !force) {
    renderInventory(state.inventory);
    return;
  }

  nodes.objectsMeta.textContent = 'Carregando inventário técnico...';
  showObjectTabsSkeleton();
  showObjectTableSkeleton();

  try {
    state.inventory = await fetchJson('api/development/objects.php');
    renderInventory(state.inventory);
  } catch (error) {
    nodes.objectsMeta.textContent = error.message;
  }
}

async function selectTable(tableName) {
  state.selectedTable = tableName;
  state.page = 1;
  state.query = '';
  state.sort = '';
  state.dir = 'asc';
  nodes.searchInput.value = '';
  clearFilters();
  updateFilterSectionVisibility();
  renderTables();
  await loadRows();
}

async function selectDataGroup(group, autoSelectFirst = true) {
  state.selectedDataGroup = group;
  state.selectedTable = '';
  state.page = 1;
  state.query = '';
  state.sort = '';
  state.dir = 'asc';
  nodes.searchInput.value = '';
  nodes.dataHead.innerHTML = '';
  nodes.dataBody.innerHTML = '';
  nodes.paginationMeta.textContent = '';
  clearFilters();
  updateFilterSectionVisibility();
  renderDataTypeTabs();
  renderTables();

  const firstObject = readableObjectsForSelectedGroup()[0];
  if (firstObject && autoSelectFirst) {
    await selectTable(firstObject.name);
  } else if (!firstObject) {
    nodes.tableTitle.textContent = dataGroupLabels[group] || 'Dados';
    nodes.tableMeta.textContent = 'Nenhum objeto encontrado.';
    setStatus('Nenhum objeto consultavel neste tipo.', 'warning');
  }
}

function showColumnsModal(tableName) {
  const currentObject = state.readableObjects.find(o => o.name === tableName);
  const modalLabel = document.getElementById('columnsModalLabel');
  const modalBody = document.getElementById('columnsModalBody');
  
  if (!currentObject) {
    modalLabel.textContent = `Colunas de ${tableName}`;
    modalBody.innerHTML = `<tr><td colspan="4" class="text-center text-danger">Dados do objeto nao encontrados no inventario.</td></tr>`;
    const modalEl = document.getElementById('columnsModal');
    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    modal.show();
    return;
  }
  
  modalLabel.textContent = `Colunas de ${tableName}`;
  const columns = currentObject.columns || [];
  
  if (columns.length === 0) {
    modalBody.innerHTML = `<tr><td colspan="4" class="text-center text-body-secondary py-3">Este objeto nao possui colunas expostas ou nao pode ser analisado.</td></tr>`;
  } else {
    modalBody.innerHTML = columns.map((col, index) => {
      const columnName = col.name ?? col.column_name ?? '';
      const dataType = col.type ?? col.data_type ?? '';
      const nullable = typeof col.nullable === 'boolean'
        ? col.nullable
        : ['YES', 'true', '1', true, 1].includes(col.is_nullable);
      const position = col.ordinal_position ?? index + 1;

      return `
        <tr>
          <td><strong>${escapeHtml(columnName)}</strong></td>
          <td><code>${escapeHtml(dataType)}</code></td>
          <td>
            <span class="badge ${nullable ? 'text-bg-warning' : 'text-bg-success'}">
              ${nullable ? 'Sim' : 'Nao'}
            </span>
          </td>
          <td><span class="badge text-bg-secondary">${escapeHtml(position)}</span></td>
        </tr>
      `;
    }).join('');
  }
  
  const modalEl = document.getElementById('columnsModal');
  const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
  modal.show();
}

async function init() {
  try {
    const payload = await fetchJson('api/tables.php');
    state.tables = payload.tables;
    state.readableObjects = payload.readable_objects || payload.tables;

    const urlParams = new URLSearchParams(window.location.search);
    const targetTable = urlParams.get('table');
    const filtersParam = urlParams.get('filters');
    const requestedView = urlParams.get('view');

    let initialTable = '';
    if (targetTable) {
      const knownObject = state.readableObjects.find(o => o.name === targetTable);
      if (knownObject) {
        state.selectedDataGroup = knownObject.type;
        initialTable = targetTable;
      }
    }

    if (!initialTable && readableObjectsForSelectedGroup().length > 0) {
      initialTable = readableObjectsForSelectedGroup()[0].name;
    }

    renderDataTypeTabs();
    renderTables();
    nodes.tableMeta.textContent = `${state.readableObjects.length} objetos consultaveis no schema ${payload.schema}`;

    if (initialTable) {
      await selectTable(initialTable);

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
            await loadRows();
          }
        } catch (e) {
          console.error("Erro ao processar filtros da URL:", e);
        }
      }
    } else {
      setStatus('Nenhum objeto consultavel encontrado.', 'warning');
    }

    if (requestedView === 'docs') {
      setView('docs');
      if (docFiles.length > 0) {
        await selectDoc(docFiles[0].path);
      }
    } else if (requestedView === 'ambiente') {
      setView('ambiente');
    } else if (targetTable) {
      setView('data');
    } else {
      setView('objects');
    }
  } catch (error) {
    setStatus(error.message, 'danger');
  }
}

nodes.dataTab.addEventListener('click', () => setView('data'));
nodes.objectsTab.addEventListener('click', () => setView('objects'));
nodes.docsTab.addEventListener('click', () => {
  setView('docs');
  if (docFiles.length > 0) {
    selectDoc(docFiles[0].path);
  }
});
if (nodes.ambienteTab) {
  nodes.ambienteTab.addEventListener('click', () => setView('ambiente'));
}
nodes.docsList.addEventListener('click', async (event) => {
  const button = event.target.closest('[data-doc-path]');
  if (!button) {
    return;
  }
  await selectDoc(button.dataset.docPath);
});
nodes.refreshObjects.addEventListener('click', () => {
  state.objectPage = 1;
  loadInventory(true);
});
nodes.dataTypeTabs.addEventListener('click', async (event) => {
  const button = event.target.closest('[data-data-group]');
  if (!button) {
    return;
  }

  await selectDataGroup(button.dataset.dataGroup);
});
nodes.objectTypeTabs.addEventListener('click', (event) => {
  const button = event.target.closest('[data-object-group]');
  if (!button || !state.inventory) {
    return;
  }

  state.selectedObjectGroup = button.dataset.objectGroup;
  state.objectPage = 1;
  renderInventory(state.inventory);
});
nodes.objectTypeContent.addEventListener('click', async (event) => {
  const showColsBtn = event.target.closest('.btn-show-columns');
  if (showColsBtn) {
    const objectName = showColsBtn.dataset.objectName;
    showColumnsModal(objectName);
    return;
  }

  const button = event.target.closest('[data-open-data]');
  if (!button) {
    return;
  }

  setView('data');
  await selectDataGroup(button.dataset.dataGroup, false);
  await selectTable(button.dataset.openData);
});
nodes.tableFilter.addEventListener('input', renderTables);
nodes.objectsFilter.addEventListener('input', () => {
  state.objectPage = 1;
  if (state.inventory) {
    renderInventory(state.inventory);
  }
});

if (nodes.objectPaginationList) {
  nodes.objectPaginationList.addEventListener('click', (event) => {
    const button = event.target.closest('[data-object-page]');
    if (!button || button.parentElement.classList.contains('disabled')) {
      return;
    }

    state.objectPage = parseInt(button.dataset.objectPage, 10);
    if (state.inventory) {
      renderInventory(state.inventory);
    }
  });
}

nodes.tableList.addEventListener('click', async (event) => {
  const button = event.target.closest('[data-table]');
  if (!button) {
    return;
  }

  await selectTable(button.dataset.table);
});

nodes.searchForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  state.query = nodes.searchInput.value.trim();
  state.page = 1;
  await loadRows();
});

nodes.dataHead.addEventListener('click', async (event) => {
  const button = event.target.closest('[data-sort]');
  if (!button) {
    return;
  }

  if (state.sort === button.dataset.sort) {
    state.dir = state.dir === 'asc' ? 'desc' : 'asc';
  } else {
    state.sort = button.dataset.sort;
    state.dir = 'asc';
  }

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

// Ambiente panel: button to open migration guide in docs viewer
const openMigrationBtn = document.getElementById('openMigrationDoc');
if (openMigrationBtn) {
  openMigrationBtn.addEventListener('click', () => {
    setView('docs');
    renderDocsList();
    const migrationDoc = docFiles.find(d => d.name === 'migracao_producao.md');
    if (migrationDoc) {
      selectDoc(migrationDoc.path);
    }
  });
}

nodes.addFilterBtn.addEventListener('click', addFilterRow);

init();
