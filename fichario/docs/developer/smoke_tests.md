# Fichario - smoke tests manuais

Data: 2026-07-01

Use esta lista antes de releases ou depois de refatoracoes em cadastro, notas, projetos e exportacao.

## Cadastro e edicao de artigo

1. Abrir `public_html/fichario/editor.php`.
2. Criar artigo com titulo, autores, ano, periodico, paginas, DOI e URL.
3. Confirmar que a referencia ABNT e gerada automaticamente.
4. Editar a referencia ABNT manualmente, travar, salvar e reabrir.
5. Confirmar que a referencia travada nao foi recalculada.
6. Destravar, salvar e confirmar novo calculo automatico.

## BibTeX

1. Abrir modal "Importar BibTeX" no cadastro de artigo.
2. Colar um `@article` com `title`, `author`, `journal`, `year`, `volume`, `number`, `pages`, `doi` e `url`.
3. Confirmar preenchimento dos campos do formulario.
4. Confirmar armazenamento de `bibtex_raw` e `bibtex_key`.
5. Confirmar que a ABNT gerada aponta pendencias quando faltarem paginas, fonte ou DOI/URL.

## Notas e Markdown

1. Abrir artigo com notas.
2. Criar ou editar nota com negrito, lista recuada, citacao, link e tabela Markdown.
3. Salvar via modal.
4. Confirmar que a nota atualiza por Ajax sem reload.
5. Confirmar que a tabela renderiza como tabela, nao texto bruto.

## Tags e vinculacao a projeto

1. Abrir `tag_view.php` com projeto ativo.
2. Vincular nota a projeto e secao existente.
3. Criar nova secao pelo controle de vinculacao.
4. Remover vinculo.
5. Confirmar que chips/rotulos continuam legiveis e que o Ajax atualiza o bloco correto.

## Projeto

1. Abrir `project.php?id=<id>`.
2. Confirmar que secoes iniciam recolhidas.
3. Editar secao com contexto contendo tabela Markdown.
4. Salvar e confirmar atualizacao Ajax do titulo, contexto e opcoes "Mover para secao".
5. Mover nota entre secoes e confirmar abertura da secao de destino.
6. Expandir/recolher todas as secoes.

## Exportacao

1. Abrir projeto com notas, artigos, tags e referencias.
2. Clicar em "Exportar".
3. Conferir o ZIP gerado.
4. Validar `AGENT_CONTEXT.md`, `project_export.json`, `articles_index.csv`, `references_abnt.txt` e `references.bib`.
5. Confirmar inclusao de tags do projeto com rotulo, categoria e definicao.
6. Confirmar presenca de DOI, URL, PDF URL e consultas sugeridas para texto completo.
