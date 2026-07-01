# Orientacoes gerais padrao do agente

Este documento guarda o texto operacional usado como orientacao padrao nos projetos do Fichario. A interface de cada projeto mostra esse texto como base editavel; quando o usuario personaliza, a versao do projeto fica salva em `projects.agent_instructions`.

Edite apenas o trecho entre os marcadores abaixo para alterar o padrao herdado por projetos sem orientacao personalizada.

<!-- agent-default:start -->
- Use somente as informacoes deste pacote como base factual, salvo instrucao explicita do usuario.
- Se for pesquisar texto completo, registre quais fontes externas foram consultadas e se o texto completo/PDF foi encontrado.
- Priorize DOI, URL original e PDF URL antes de buscas amplas por titulo.
- Use apenas fontes legais e verificaveis: DOI/editora, periodico, SciELO, PubMed/PMC, repositorios institucionais, paginas oficiais e bases academicas abertas.
- Nao invente dados bibliograficos ausentes. Se algo faltar, mantenha a pendencia explicitamente.
- Preserve a estrutura das secoes do projeto como eixo analitico principal.
- Use apenas as marcações vinculadas ao projeto/secoes; outras marcações do fichario nao foram exportadas.
- Cite os artigos usando a citacao curta indicada em cada marcação e monte a lista final com as referencias ABNT fornecidas.
- Diferencie citacao literal, observacao/fichamento e metadados bibliograficos.
- Quando uma conclusao depender de inferencia, sinalize a inferencia.
<!-- agent-default:end -->

## Relacao com a interface

- Projetos sem texto proprio usam automaticamente o trecho padrao acima.
- Ao salvar uma orientacao diferente na pagina do projeto, o texto fica vinculado somente aquele projeto.
- Ao voltar o texto do projeto para o padrao, o sistema pode armazenar vazio e herdar futuras alteracoes deste documento.

## Relacao com a exportacao

O exportador inclui as orientacoes efetivas no `AGENT_CONTEXT.md` e no `project_export.json`, indicando se a fonte foi o padrao do sistema ou uma personalizacao do projeto.
