# Saneamento de UX da Plataforma

Este documento registra a execucao incremental de padronizacao visual e UX.

## Objetivo

Sanear a interface da plataforma com Bootstrap como base, tema claro, navbar comum e paginas internas organizadas por componentes reutilizaveis.

## Padroes ativos

- Navbar comum em `includes/navbar.php`.
- Tema claro forcado por `public_html/assets/js/theme-switcher.js`.
- CSS canonico em `public_html/assets/css/style.css`.
- Cabecalho interno com `.page-header`.
- Landpages simples, sem paineis operacionais complexos.
- Paginas internas com cards, formularios, tabelas, badges e alerts Bootstrap.
- Interface e textos em portugues brasileiro.
- Arquivos devem permanecer em UTF-8 sem BOM.
- Respostas HTML/JSON devem declarar `charset=utf-8`.
- Conexoes PostgreSQL devem usar `client_encoding=UTF8`.
- Markdown de conteudo editavel deve usar o helper central `includes/markdown.php`.
- Renderizacao Markdown deve escapar HTML do usuario antes de aplicar formatacao permitida.
- Campos longos com Markdown devem usar `.markdown-input`; saidas devem usar `.markdown-body.fichario-markdown` ou classe equivalente do modulo.

## Fila de execucao

1. Fichario: usar como referencia de paginas internas saneadas.
2. Acesso e Administracao: padronizar cabecalhos, cards e formularios de entrada.
3. CAREX: remover cabeçalhos com cor/acento local em matrizes e matriz.
4. CAT: reduzir H1 de landing em paginas internas para cabecalhos operacionais.
5. LDRT: revisar paginas de consulta e exploracao apos CAT.

## Checklist por pagina

- Usa navbar comum quando aplicavel.
- Usa `.page-header` em pagina interna.
- Nao tem seletor de tema visivel.
- Nao usa `table-dark`, `alert-dark`, `dark-mode` ou `btn-close-white`.
- Botoes nao dependem de formato manual como `rounded-pill px-4`.
- Tabelas estao dentro de `.table-responsive`.
- Formularios usam labels visiveis e grid Bootstrap.
- Conteudo nao cria overflow horizontal.
- Textos com acentos renderizam corretamente em portugues brasileiro.
- Endpoints JSON e formularios AJAX informam UTF-8.

## Estado

- Fichario: painel, tags, artigos, projetos, projeto, timeline, editor e admin receberam cabecalho/padroes comuns.
- Fichario: `tag_view.php` recebeu cabecalho operacional, acao de retorno no topo e botoes de gestao com icones Bootstrap.
- Fichario: `view.php` recebeu cabecalho `.page-header`, botoes principais com Bootstrap Icons e remocao de emoji quebrado no periodo de dados.
- Fichario: `view.php` e `project.php` passam a renderizar citacao, observacao, contexto e descricao de projeto com Markdown seguro centralizado.
- Fichario: `timeline.php` foi redesenhada como linha do tempo vertical, com anos como marcos laterais, resumo anual e artigos visiveis em cards compactos.
- Fichario: toasts migrados de estilo inline em JS para classes estruturais em `assets/app.css`.
- Fichario: runtime PHP reforcado com `default_charset=UTF-8`, `mb_internal_encoding('UTF-8')`, HTML `text/html; charset=utf-8`, JSON `application/json; charset=utf-8` e AJAX `application/x-www-form-urlencoded;charset=UTF-8`.
- Banco do Fichario verificado em 2026-06-30: `client_encoding=UTF8`, `server_encoding=UTF8`; amostras e busca por sequencias tipicas de mojibake sem problemas em artigos, tags e projetos.
- Plataforma: helper Markdown comum criado em `includes/markdown.php`; Acesso/Admin, CAREX e Fichario passam a poder consumir a mesma maquinaria.
- CAT: modais de ETL e inspecao corrigidos para tema claro.
- Proxima frente: Acesso e Administracao.
