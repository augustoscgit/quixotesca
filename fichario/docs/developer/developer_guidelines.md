# Orientacoes de desenvolvedor

## Principios

- Preservar o fluxo de leitura: acoes na coluna direita devem usar AJAX local sempre que possivel.
- Evitar `location.reload()` em telas de fichamento, pois isso quebra a posicao de leitura.
- Garantir a compatibilidade do código com o PostgreSQL antes de realizar alterações estruturais nas consultas SQL.
- Separar administracao, navegacao publica e fluxo de fichamento.

## Modelo conceitual atual

- Artigo: registro bibliografico, resumo, texto completo, referencias e analise.
- Tag: vocabulario controlado com agrupamento, filiacao e definicao.
- Nota: objeto de fichamento ligado a um artigo, formado por citacao opcional, observacao opcional e uma ou mais tags.
- Nota-tag: relacionamento muitos-para-muitos entre uma nota e suas tags.
- Vinculo artigo-tag antigo: deve ser tratado como legado ou compatibilidade, nao como modelo principal da interface.
- Uma submissao com multiplas tags deve criar uma unica nota com multiplas tags, inclusive quando citacao e observacao estiverem vazias.
- Excluir uma tag do vocabulario remove apenas o vinculo nota-tag; a nota deve ser mantida e continuar visivel no artigo, mesmo que fique sem tags.

## Cuidados de interface

- Formularios de copia e cola devem ficar embutidos quando o usuario precisa consultar o texto ao lado.
- Modais devem ser usados para tarefas curtas, como criar citacao a partir de selecao.
- A coluna lateral pode atualizar via AJAX; o texto principal nao deve saltar.
- Tags em notas devem aparecer acima do trecho citado ou observado.
- Botoes e grupos de acoes devem ficar alinhados a direita do bloco, cabecalho, formulario ou card correspondente.
- Toda tag exibida como link, badge, chip ou item navegavel deve mostrar a definicao da tag no mouse over.
- Acoes de uma nota devem ficar concentradas na barrinha compacta de icones do card; evitar botoes textuais paralelos para ler, editar ou excluir.
- Em paginas de tag, cada nota deve mostrar todas as tags ligadas a ela, nao apenas a tag da pagina.
- Cards de nota devem mostrar teasers textuais curtos com `...` ao final quando houver corte.
- Citação e observação vazias não devem aparecer no teaser; se ambas estiverem vazias, mostrar apenas o alerta de nota incompleta.
- A leitura completa e a edição de nota devem acontecer em modal ampla, com espaço real para textos longos.
- Operacoes assíncronas ou submits demorados devem sinalizar espera com `FicharioUI.setBusy()` no botão acionado; formularios comuns usam esse padrão automaticamente via `assets/app.js`.
- Usar overlay local com `FicharioUI.setPanelBusy()` apenas quando uma área inteira estiver indisponível. Evitar bloqueio de tela inteira, especialmente em leitura e fichamento, para preservar o contexto do usuário.
- Estados de espera devem evitar duplo clique, manter largura visual do botão e nao deslocar o texto principal.

## Padrao de codificacao

- Arquivos PHP, JS, CSS e Markdown devem ser mantidos em UTF-8 sem BOM.
- Ao editar com PowerShell, evitar `Set-Content`/`Out-File` sem codificacao explicita em arquivos com acentos.
- Para regravacoes mecanicas, usar leitura UTF-8 e escrita com `System.Text.UTF8Encoding($false)`.
- Antes de duplicar lógica, estilos ou marcação, procurar componentes existentes em `assets/`, `src/` e `docs/`. Se a mesma visualizacao ou comportamento for usado por mais de uma pagina, extrair para arquivo reutilizavel de PHP, JS ou CSS e manter nas paginas apenas dados e configuracao.
- Componentes compartilhados devem concentrar renderizacao, estado visual, suporte a tema claro/escuro e listeners comuns; paginas consumidoras nao devem copiar blocos longos de implementacao.
- Antes de finalizar alteracoes com texto acentuado, procurar sinais de mojibake, especialmente sequencias comuns de acentos corrompidos, e corrigir antes de testar.
- Testar importacao BibTeX com `C:\xampp\php\php.exe tests\bibtex_parser_test.php` depois de alterar parser, cadastro de artigo ou normalizacao de texto.
- Em ambiente de producao, manter `APP_DEBUG=false`; nunca expor links diretos de recuperacao de senha, tokens ou atalhos de desenvolvimento.

## Compatibilidade de banco

- Evitar SQL que dependa de recursos ausentes no MySQL 5.7.
- Isolar diferencas de dialeto caso suporte a múltiplos bancos (ex: MySQL) seja reintroduzido.
- Nao assumir disponibilidade de extensoes vetoriais.

## Documentacao

- Requisitos e notas de manutencao ficam em `docs/`.
- A area administrativa de documentacao deve abrir em modo leitura e oferecer um botao para transformar a area de exibicao em editor amplo.
- Evitar textareas sempre visiveis para documentos longos; elas prejudicam a leitura e tornam a tela pouco utilizavel.
- Material de prompt ou instrucoes de sistema pode continuar em `system_md/`, se fizer sentido separa-lo da documentacao editavel.
- Nunca colocar segredos em Markdown versionado.
