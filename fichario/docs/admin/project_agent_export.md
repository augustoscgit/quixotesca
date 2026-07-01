# Projetos e exportacao para agente de IA

Este documento descreve o pacote de exportacao de projetos do Fichario para consumo por agentes de IA que vao apoiar redacao, revisao e sintese com base nas marcações vinculadas ao projeto.

## Objetivo

O exportador deve produzir um pacote autocontido para leitura por agente, nao um relatorio para humanos. O agente deve conseguir reconstruir:

- o projeto e sua finalidade;
- as secoes e seus contextos;
- as marcações vinculadas a cada secao;
- os artigos citados por essas marcações;
- as referencias bibliograficas em ABNT;
- os caminhos para recuperar texto completo, DOI, URL original e PDF quando disponiveis.

## Formato do pacote

O botao **Exportar para agente** fica na pagina de cada projeto e gera um arquivo `.zip` com:

- `AGENT_CONTEXT.md`: arquivo principal, escrito para ser lido primeiro pelo agente.
- `SOURCE_RETRIEVAL_GUIDE.md`: roteiro de busca de texto completo/PDF e verificacao bibliografica.
- `project_export.json`: estrutura completa do projeto, secoes, marcações, artigos e referencias.
- `source_retrieval.json`: checklist estruturado de recuperacao externa.
- `articles_index.csv`: indice tabular para rastrear DOI, URL, PDF URL, ABNT e consultas sugeridas.
- `references_abnt.txt`: lista de referencias ABNT curadas.
- `references.bib`: BibTeX original quando cadastrado nos artigos.
- `articles/*.md`: um arquivo por artigo citado no projeto.

## Regras de conteudo

- Exportar somente marcações vinculadas ao projeto ou as suas secoes.
- Preservar a ordem e o contexto das secoes como eixo analitico principal.
- Incluir contexto da secao junto das marcações para orientar a redacao.
- Incluir referencia ABNT, citacao curta, DOI, URL, PDF URL e consultas sugeridas por artigo.
- Nao incluir texto completo dos artigos por padrao.
- Sinalizar quando ha texto completo armazenado no Fichario, mas orientar recuperacao externa verificavel.
- Nao anonimizar dados do projeto.

## Uso esperado pelo agente

O agente deve tratar o pacote como fonte primaria de contexto do projeto. Quando precisar pesquisar artigos completos, deve priorizar DOI, URL original, PDF URL, periodico, SciELO, PubMed/PMC, repositorios institucionais e paginas oficiais.

Toda informacao bibliografica ausente deve permanecer como pendencia. O agente nao deve inventar autores, titulo, ano, periodico, paginas, DOI ou editora.

## Pontos de implementacao

- Interface: `public_html/fichario/project.php`.
- Exportador: `public_html/fichario/export_project.php`.
- Orientacoes padrao do agente: `fichario/docs/admin/default_agent_instructions.md`.
- Fallback das orientacoes: `default_project_agent_instructions()` em `fichario/bootstrap.php`.
- Campo por projeto: `projects.agent_instructions`.

## Validacao

Antes de considerar o exportador pronto, verificar:

- o `.zip` abre corretamente;
- `AGENT_CONTEXT.md` contem secoes, contextos e marcações;
- `project_export.json` tem dados estruturados equivalentes ao Markdown;
- `references_abnt.txt` contem referencias curadas ou geradas;
- `references.bib` preserva BibTeX cadastrado;
- `articles_index.csv` permite localizar DOI, URL e PDF URL;
- as orientacoes padrao ou personalizadas aparecem no pacote.
