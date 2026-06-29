<?php
require_once __DIR__ . '/../../ldrt/src/db.php';
// We don't need active database connection queries on initial load, but we can verify it
$db_status = "Desconectado";
try {
    $db = getDBConnection();
    $db_status = "Conectado";
} catch (Exception $e) {
    $db_error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-module="ldrt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LDRT - Busca RAG (Agente de IA)</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/favicon.png">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
                :root, [data-bs-theme="light"] {
            --bg-color: #f1f5f9;
            --bg-glow-1: rgba(99, 102, 241, 0.06);
            --bg-glow-2: rgba(168, 85, 247, 0.04);
            --card-bg: rgba(255, 255, 255, 0.65);
            --border-color: rgba(0, 0, 0, 0.08);
            --accent-color: #4f46e5;
            --accent-hover: #3730a3;
            --text-muted: #64748b;
            --text-color: #1e293b;
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.06);
            --navbar-bg: rgba(241, 245, 249, 0.85);
            --field-bg: #ffffff;
        }

        [data-bs-theme="dark"] {
            --bg-color: #0b0f19;
            --bg-glow-1: rgba(99, 102, 241, 0.12);
            --bg-glow-2: rgba(168, 85, 247, 0.08);
            --card-bg: rgba(22, 28, 45, 0.7);
            --border-color: rgba(255, 255, 255, 0.08);
            --accent-color: #6366f1;
            --accent-hover: #4f46e5;
            --text-muted: #94a3b8;
            --text-color: #f8fafc;
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            --navbar-bg: rgba(11, 15, 25, 0.85);
            --field-bg: #111827;
        }

                body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(at 0% 0%, var(--bg-glow-1) 0px, transparent 50%),
                radial-gradient(at 100% 100%, var(--bg-glow-2) 0px, transparent 50%);
            color: var(--text-color);
            min-height: 100vh;
        }

        h1, h2, h3, h4, h5 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }

                .navbar {
            background-color: var(--navbar-bg);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
        }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: var(--glass-shadow);
        }

        .form-control,
        .form-select,
        .input-group-text {
            background-color: var(--field-bg) !important;
            border: 1px solid var(--border-color);
            color: var(--text-color) !important;
        }

        .form-control:focus,
        .form-select:focus {
            background-color: var(--field-bg) !important;
            border-color: var(--accent-color);
            color: var(--text-color) !important;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
        }

        .form-control::placeholder {
            color: var(--text-muted);
            opacity: 0.78;
        }

        .form-select option {
            background-color: var(--field-bg);
            color: var(--text-color);
        }

        .json-response {
            background-color: #0f172a;
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 15px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.85rem;
            max-height: 450px;
            overflow: auto;
            color: #38bdf8;
        }

        .badge-custom {
            font-size: 0.75rem;
            font-weight: 500;
            padding: 4px 8px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .badge-cid { background-color: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
        .badge-rag-agente { background-color: rgba(99, 102, 241, 0.15); color: #a5b4fc; border: 1px solid rgba(99, 102, 241, 0.3); }
        .badge-rag-relato { background-color: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }

        .chunk-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: border-color 0.15s ease;
        }

        .chunk-card:hover {
            border-color: rgba(99, 102, 241, 0.4);
        }

        .chunk-text {
            font-size: 0.9rem;
            line-height: 1.5;
            color: #cbd5e1;
            white-space: pre-wrap;
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="../assets/js/theme-switcher.js"></script>
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

    <!-- Navbar -->
    <!-- Navbar -->
    <?php
    require_once __DIR__ . '/../../includes/navbar.php';
    render_platform_navbar('ldrt', 'rag');
    ?>

    <div class="container my-5">
        <div class="row g-4">
            
            <!-- Left Column: Documentation -->
            <div class="col-lg-5">
                <div class="glass-card p-4">
                    <h3 class="mb-3 d-flex align-items-center gap-2">
                        <i class="fa-solid fa-robot text-primary" style="color: var(--accent-color) !important;"></i>
                        Base de Conhecimento RAG
                    </h3>
                    <p class="text-muted small">
                        O banco de dados consolidado do LDRT foi estruturado para servir como fonte de dados para **Retrieval-Augmented Generation (RAG)**. Ele agrupa dados clínicos complexos e relações regulatórias em segmentos textuais ("chunks") semanticamente coesos.
                    </p>
                    <p class="text-muted small">
                        Esses chunks estão salvos na view do PostgreSQL <code>v_rag_chunks</code> e são pesquisados localmente utilizando indexação de busca textual (FTS) nativa configurada em português.
                    </p>
                    
                    <div class="mt-4">
                        <h6 class="text-light"><i class="fa-solid fa-code text-indigo me-1"></i> Endpoint da API RAG</h6>
                        <p class="text-muted small mb-2">Qualquer ferramenta ou agente de IA pode consumir nossa API enviando parâmetros via método GET:</p>
                        <pre class="bg-dark p-2 border border-secondary rounded text-light font-monospace small">GET api_rag.php?q=ruido&limit=5</pre>
                        
                        <h6 class="text-light mt-3 small font-weight-bold">Parâmetros suportados:</h6>
                        <ul class="text-muted small ps-3">
                            <li><code>q</code>: termo de busca (executa index FTS ou fallback aproximado)</li>
                            <li><code>type</code>: filtrar por tipo (<code>agente_cid</code> ou <code>relato</code>)</li>
                            <li><code>limit</code>: quantidade de chunks retornados (padrão: 50, máx: 200)</li>
                        </ul>
                    </div>

                    <div class="mt-4 p-3 rounded" style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color);">
                        <h6 class="text-light small font-weight-bold mb-2">Exemplo de Integração Python:</h6>
                        <pre class="bg-dark p-2 border border-secondary rounded text-light font-monospace" style="font-size: 0.75rem;">import requests

url = "http://localhost/quixotesca/public_html/ldrt/api_rag.php"
params = {"q": "benzeno", "limit": 3}
response = requests.get(url, params=params).json()

for chunk in response["results"]:
    print(f"[{chunk['chunk_type'].upper()}]: {chunk['chunk_text']}\n")</pre>
                    </div>
                </div>

                <div class="glass-card p-4 mt-4">
                    <h5 class="mb-3 text-light d-flex align-items-center gap-2">
                        <i class="fa-solid fa-brain text-primary" style="color: var(--accent-color) !important;"></i>
                        Agente no Google AI Studio
                    </h5>
                    <p class="text-muted small">
                        Você pode construir um agente inteligente no <strong>Google AI Studio</strong> capaz de interagir com essa base de dados via <strong>Function Calling</strong> (ferramentas). Siga as etapas abaixo:
                    </p>
                    <ol class="text-muted small ps-3">
                        <li class="mb-2"><strong>Acesse o AI Studio:</strong> Crie um novo <em>Chat Prompt</em> no <a href="https://aistudio.google.com/" target="_blank" class="text-indigo text-decoration-none">Google AI Studio</a>.</li>
                        <li class="mb-2"><strong>Instrução do Sistema (System Instruction):</strong> Insira uma orientação de comportamento, por exemplo:
                            <div class="p-2 my-1 bg-dark rounded border border-secondary text-light-emphasis" style="font-size: 0.8rem;">
                                "Você é um assistente médico especialista em Doenças Relacionadas ao Trabalho. Use a ferramenta 'buscar_base_ldrt' para pesquisar riscos ocupacionais ou nexos causais. Sempre baseie suas respostas nos dados retornados pela ferramenta."
                            </div>
                        </li>
                        <li class="mb-2"><strong>Ative Function Calling:</strong> Vá no painel lateral em <em>Tools</em>, adicione uma nova função e configure o seguinte schema JSON:</li>
                    </ol>
                    <pre class="bg-dark p-2 border border-secondary rounded text-light font-monospace" style="font-size: 0.75rem;">{
  "name": "buscar_base_ldrt",
  "description": "Busca informações na base de dados da LDRT sobre riscos, doenças e relatos de caso.",
  "parameters": {
    "type": "OBJECT",
    "properties": {
      "q": {
        "type": "STRING",
        "description": "Termo clínico, sintoma, risco ou CID-10 (ex: 'chumbo', 'M51.0')."
      },
      "limit": {
        "type": "INTEGER",
        "description": "Número máximo de chunks (ex: 5)."
      }
    },
    "required": ["q"]
  }
}</pre>
                    <p class="text-muted small mb-0">
                        Quando o modelo de IA decidir executar a função <code>buscar_base_ldrt</code>, faça a chamada para a nossa API <code>api_rag.php?q=...</code> e retorne os resultados para o Gemini prosseguir com a resposta ao usuário.
                    </p>
                </div>
            </div>

            <!-- Right Column: Interactive Simulator -->
            <div class="col-lg-7">
                <div class="glass-card p-4 h-100">
                    <h4 class="mb-3 d-flex align-items-center gap-2">
                        <i class="fa-solid fa-magnifying-glass-chart text-primary" style="color: var(--accent-color) !important;"></i>
                        Simulador de Busca Semântica
                    </h4>
                    
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <input type="text" id="rag_query" class="form-control" placeholder="Buscar por termo clínico, risco...">
                        </div>
                        <div class="col-md-3">
                            <select id="rag_type" class="form-select">
                                <option value="">Todos os tipos</option>
                                <option value="agente_cid">Lista B (Risco/CID)</option>
                                <option value="relato">Relatos de Casos</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select id="rag_limit" class="form-select">
                                <option value="3">3 Chunks</option>
                                <option value="5" selected>5 Chunks</option>
                                <option value="10">10 Chunks</option>
                                <option value="20">20 Chunks</option>
                            </select>
                        </div>
                    </div>
                    <div class="d-grid mb-3">
                        <button class="btn btn-primary" type="button" onclick="searchRag()">
                            <i class="fa-solid fa-bolt me-1"></i> Executar Busca RAG
                        </button>
                    </div>

                    <div id="rag_loader" class="d-none text-center my-4">
                        <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
                        <span class="ms-2 text-muted small">Consultando base de dados RAG...</span>
                    </div>

                    <div id="rag_results_wrapper" class="d-none">
                        <!-- Navigation Tabs for results -->
                        <ul class="nav nav-tabs mb-3" id="resultTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="formatted-tab" data-bs-toggle="tab" data-bs-target="#formatted-panel" type="button" role="tab">
                                    <i class="fa-solid fa-list-check me-1"></i> Resultados Formatados
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="raw-tab" data-bs-toggle="tab" data-bs-target="#raw-panel" type="button" role="tab">
                                    <i class="fa-solid fa-code me-1"></i> Retorno JSON Bruto
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="resultTabsContent">
                            <!-- Formatted view panel -->
                            <div class="tab-pane fade show active" id="formatted-panel" role="tabpanel">
                                <div id="formatted_results_list" style="max-height: 450px; overflow-y: auto;">
                                    <!-- Dynamic formatted chunks -->
                                </div>
                            </div>
                            
                            <!-- Raw JSON view panel -->
                            <div class="tab-pane fade" id="raw-panel" role="tabpanel">
                                <pre class="json-response" id="rag_results_json"></pre>
                            </div>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div id="rag_empty_state" class="text-center py-5 text-muted">
                        <i class="fa-solid fa-server mb-3" style="font-size: 3rem; opacity: 0.2;"></i>
                        <p class="small">Digite os termos desejados e clique no botão acima para visualizar os dados de contextualização da IA.</p>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Footer -->
    <footer class="text-center py-4 border-top border-secondary mt-5" style="background-color: rgba(11, 15, 25, 0.5);">
        <div class="container">
            <p class="mb-1 text-muted small">LDRT Portal RAG &copy; 2026 - Em conformidade com a Portaria GM/MS 1.999/2023</p>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function searchRag() {
            const query = document.getElementById('rag_query').value.trim();
            const type = document.getElementById('rag_type').value;
            const limit = document.getElementById('rag_limit').value;
            
            const loader = document.getElementById('rag_loader');
            const wrapper = document.getElementById('rag_results_wrapper');
            const emptyState = document.getElementById('rag_empty_state');
            const formattedList = document.getElementById('formatted_results_list');
            const rawJson = document.getElementById('rag_results_json');

            if (!query) {
                alert("Por favor, insira um termo de busca.");
                return;
            }

            loader.classList.remove('d-none');
            wrapper.classList.add('d-none');
            emptyState.classList.add('d-none');
            formattedList.innerHTML = '';

            let url = `api_rag.php?q=${encodeURIComponent(query)}&limit=${limit}`;
            if (type) {
                url += `&type=${type}`;
            }

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    loader.classList.add('d-none');
                    wrapper.classList.remove('d-none');
                    rawJson.textContent = JSON.stringify(data, null, 2);

                    if (data.results.length === 0) {
                        formattedList.innerHTML = '<div class="alert alert-dark text-muted text-center py-4">Nenhum chunk de dados correspondeu aos termos na busca de texto completo.</div>';
                        return;
                    }

                    data.results.forEach(chunk => {
                        const card = document.createElement('div');
                        card.className = 'chunk-card';
                        
                        let badgeClass = chunk.chunk_type === 'agente_cid' ? 'badge-rag-agente' : 'badge-rag-relato';
                        let badgeLabel = chunk.chunk_type === 'agente_cid' ? 'Lista B (Relação Risco-CID)' : 'Relato de Caso';
                        
                        let metaHtml = '';
                        if (chunk.chunk_type === 'agente_cid') {
                            metaHtml = `
                                <div class="mt-2 small text-muted">
                                    <span class="me-2"><strong class="text-light">Agente:</strong> ${escapeHtml(chunk.agent_name)}</span>
                                    <span><strong class="text-light">CID-10:</strong> <a href="consulta.php?cid=${encodeURIComponent(chunk.cid_code)}" class="badge badge-custom badge-cid text-decoration-none">${escapeHtml(chunk.cid_code)}</a> (${escapeHtml(chunk.cid_name)})</span>
                                </div>
                            `;
                        } else if (chunk.chunk_type === 'relato') {
                            metaHtml = `
                                <div class="mt-2 small text-muted">
                                    <span class="d-block mb-1"><strong class="text-light">Relato:</strong> ${escapeHtml(chunk.relato_title)}</span>
                                    ${chunk.cid_code ? `<span class="me-2"><strong class="text-light">CID-10:</strong> <a href="consulta.php?cid=${encodeURIComponent(chunk.cid_code)}" class="badge badge-custom badge-cid text-decoration-none">${escapeHtml(chunk.cid_code)}</a></span>` : ''}
                                    ${chunk.cnae_cbo_code ? `<span class="me-2"><strong class="text-light">${chunk.cnae_cbo_type.toUpperCase()}:</strong> ${escapeHtml(chunk.cnae_cbo_code)}</span>` : ''}
                                </div>
                            `;
                        }

                        card.innerHTML = `
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge badge-custom ${badgeClass}">${badgeLabel}</span>
                                <span class="text-muted font-monospace" style="font-size: 0.75rem;">Source ID: ${chunk.source_id}</span>
                            </div>
                            <div class="chunk-text">${escapeHtml(chunk.chunk_text)}</div>
                            ${metaHtml}
                        `;
                        formattedList.appendChild(card);
                    });
                })
                .catch(err => {
                    loader.classList.add('d-none');
                    wrapper.classList.remove('d-none');
                    formattedList.innerHTML = `<div class="alert alert-danger">Erro na busca: ${escapeHtml(err.message)}</div>`;
                    rawJson.textContent = JSON.stringify({ error: err.message }, null, 2);
                });
        }

        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    </script>
</body>
</html>
