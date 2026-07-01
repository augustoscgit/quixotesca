# Fichario - memoria de continuidade

Data: 2026-07-01

## Revisao senior breve

### Codigo
- Consolidar o renderizador Markdown: hoje existe helper central proprio em `includes/markdown.php` e fallback JS no Fichario. Proximo passo ideal e substituir por biblioteca CommonMark/Parsedown quando o projeto tiver fluxo de dependencias definido.
- Reduzir duplicacao entre `fichario/project.php` e `public_html/fichario/project.php`; manter uma unica fonte de verdade ou documentar claramente o espelhamento.
- Separar handlers POST grandes em servicos/acoes menores para projetos, artigos, notas e tags. O arquivo de projeto esta funcional, mas ja passou do ponto confortavel para manutencao.

### Desempenho
- Continuar medindo `articles.php`, especialmente listagem, filtros e carregamento de metadados/tags. Priorizar paginacao real, indices e carregamento incremental.
- Revisar consultas com agregacoes JSON de tags/notas em telas densas para evitar recomputacao em cada carga.
- Manter cache-busting explicito de JS/CSS apenas quando necessario; idealmente centralizar versao dos assets.

### Seguranca
- Padronizar respostas Ajax e validacao CSRF em helpers compartilhados; ja ha protecao, mas os endpoints repetem padroes.
- Revisar todos os campos ricos em Markdown: manter escape antes de renderizar e permitir somente HTML gerado pelo renderer.
- Avaliar autorizacao por usuario/admin em todas as consultas de projeto, artigo e tag para evitar acesso transversal.

### UX
- Projetos melhorou com secoes recolhidas e controles padronizados; proximo foco e reduzir ainda mais densidade visual nas notas e padronizar modais.
- Garantir feedback consistente em Ajax: salvar, mover, editar e remover devem manter estado visual, foco e toast previsiveis.
- Melhorar editor de Markdown com preview opcional para contexto de projeto/secao e notas longas.

### Testes e operacao
- Criar testes pequenos para renderizador Markdown, ABNT/BibTeX e exportacao de projeto.
- Adicionar smoke tests manuais documentados para: cadastro de artigo, preenchimento BibTeX/URL, vinculacao de tags, edicao de nota, edicao de secao e exportacao.
- Validar no navegador autenticado antes de releases, principalmente fluxos Ajax.

## Proximas prioridades sugeridas

1. Extrair servicos de projeto e handlers Ajax.
2. Trocar parser Markdown proprio por biblioteca padrao quando dependencias forem formalizadas.
3. Criar suite minima de testes de exportacao/Markdown/BibTeX.
4. Perfilar `articles.php` com dados reais e corrigir gargalos restantes.
5. Revisar autorizacao em consultas de tags/notas/projetos.

## Execucao em 2026-07-01

- Criada suite CLI minima em `fichario/tests/run.php` cobrindo Markdown, escape de HTML, ABNT e parse BibTeX.
- Criado checklist de smoke tests manuais em `fichario/docs/developer/smoke_tests.md` para cadastro, BibTeX, notas, tags, projetos e exportacao.
- Extraida camada inicial `App\Projects\ProjectService` para operacoes de projeto, secao e notas usadas por `public_html/fichario/project.php`.
- Padronizadas respostas Ajax principais da pagina de projeto com `project_json_response()` e `request_is_ajax()`.
- Proximo bloco recomendado: mover criacao/edicao/exclusao de secoes e tags de projeto para o mesmo servico, ou criar controller/handler dedicado para POST.
