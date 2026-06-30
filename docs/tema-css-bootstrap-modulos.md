# Tema, CSS e Bootstrap por modulo

Este documento registra o contrato operacional por modulo durante a fase Bootstrap-first.

Fonte de verdade:

- `docs/bootstrap-first-planejamento.md`
- `docs/bootstrap-first-exemplos.md`
- `public_html/assets/css/style.css`
- `public_html/assets/js/theme-switcher.js`

## Regra geral

Todos os modulos devem usar Bootstrap oficial como base visual e seguir as diretrizes registradas em `docs/diretrizes-visuais-renast.md`. Nao ha, nesta fase, tema escuro, cor principal por modulo, fonte propria por modulo ou componente visual proprietario por modulo.

## CSS permitido por modulo

CSS local e permitido somente para:

- dimensoes minimas de graficos, mapas, canvases e visualizacoes;
- grids funcionais que Bootstrap nao resolva de forma limpa;
- areas sticky;
- pequenos ajustes de compatibilidade temporaria com markup legado;
- integracao com bibliotecas de terceiros.

CSS local nao deve definir:

- paleta;
- fonte;
- botoes;
- cards;
- navbar;
- breadcrumbs;
- tabelas;
- badges;
- progresso;
- tema claro.

## Checklist por pagina

1. Bootstrap 5.3.8 carregado.
2. Bootstrap Icons carregado quando houver `bi`.
3. CSS local, se existir, carregado antes do CSS global.
4. `public_html/assets/css/style.css` carregado por ultimo.
5. Tema claro ativo e sem seletor claro/escuro visivel.
6. Sem `<style>` local.
7. Sem `style="..."` manual.
8. Sem cores hexadecimais locais em markup.
9. Sem classes antigas de tema ou identidade visual.
10. Navbar compartilhada quando aplicavel.
11. Componentes principais feitos com Bootstrap.

## Modulos

| Modulo | Regra nesta fase |
|---|---|
| Portal | Hub Bootstrap-first da plataforma. |
| Acesso | Formularios e administracao de sessao com Bootstrap nativo. |
| Administracao | Vitrine operacional dos padroes Bootstrap-first. |
| CAREX-BR | Tabelas, matriz, sumarios e desenvolvimento sem cor de modulo. |
| Fichario | Tags, nuvem e grafo com Bootstrap/tons neutros sempre que possivel. |
| CAT | ETL e inspecao em componentes Bootstrap neutros. |
| LDRT | Listas e RAG sem acento laranja institucional nesta fase. |

## Migracao

Ao tocar uma tela, remover primeiro classes e regras visuais antigas. Depois reconstruir com os exemplos de `docs/bootstrap-first-exemplos.md`.
