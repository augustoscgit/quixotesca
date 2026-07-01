# Diretrizes visuais RENAST

Este documento registra a referencia visual adotada para orientar caixas, botoes, formularios, tabelas, navegacao, espacamentos e cores da Plataforma RENAST.

Referencias estudadas:

- `https://www.gov.br/ds/components/visao-geral`
- `https://govbr-ds.gitlab.io/tools/govbr-ds-wiki//`

## Decisao

A Plataforma RENAST usa essas referencias apenas como apoio visual. Nao e um site governamental, nao deve parecer um portal oficial e nao deve copiar tema, barras, marcas, rodapes ou composicao institucional.

Implementacao pratica:

- Bootstrap 5.3 continua sendo a base tecnica.
- A logo da Plataforma RENAST permanece como marca principal.
- O visual deve ser limpo, claro, previsivel e confortavel para uso operacional.
- Componentes devem ser equivalentes em funcao, mas nao identicos em identidade visual.
- A customizacao deve ser pequena, centralizada e compativel com Bootstrap.

## Tema

Durante esta fase, apenas tema claro esta ativo.

- `data-bs-theme` deve ser sempre `light`.
- Controles de alternancia claro/escuro devem ficar ocultos.
- O script `public_html/assets/js/theme-switcher.js` existe apenas como bootstrapper do tema claro e gancho futuro.
- Tema escuro sera derivado depois do claro, sem decisao visual paralela agora.

## Aparencia

1. Fundos claros e neutros.
2. Cards com borda visivel, sombra nula ou minima e raio discreto.
3. Botoes com hierarquia clara: primario, secundario, outline e link.
4. Tabelas legiveis, com cabecalho limpo e densidade adequada.
5. Formularios com labels visiveis, campos alinhados e feedback perto do campo.
6. Badges e alertas usando cores semanticas do Bootstrap, sem paleta por modulo.
7. Espacamento consistente entre secoes, cards e barras de acao.
8. Navbar comum com variacao simples para landing pages e completa para paginas internas.

## Distincao institucional

Para evitar imitacao:

- nao usar barra oficial;
- nao usar marcas oficiais externas como identidade;
- nao copiar cabecalhos ou rodapes oficiais;
- nao buscar pixel-perfect do modelo;
- manter a composicao Bootstrap;
- manter a marca RENAST como sinal primario.

## Navbar

A navbar comum e obrigatoria:

- `includes/navbar.php` e a fonte unica;
- landpages usam variacao simples;
- paginas internas usam itens comuns e itens do modulo;
- usuario, papel, link de administracao, conta e logout pertencem ao dropdown central;
- modulos nao devem criar menus locais "Ola, ..." nem seletor de tema proprio.

## Landing pages

Landpages sao portas de entrada simples:

- devem apresentar a logo RENAST, nome do modulo, resumo curto e acoes principais;
- devem usar hero central, botoes Bootstrap e cards de navegacao com borda;
- nao devem carregar consultas pesadas, graficos, filtros extensos ou paineis operacionais;
- quando ja existir conteudo de painel no `index.php`, mover para pagina interna como `painel.php`;
- landpages usam a variacao simples da navbar comum;
- paginas internas usam a navbar completa com itens do modulo.

## Paginas internas

Paginas internas sao ambientes de trabalho:

- devem usar a navbar completa do modulo, sempre pela fonte comum;
- devem iniciar com `.page-header`: titulo objetivo, subtitulo curto quando necessario e acoes principais alinhadas;
- breadcrumbs usam `.breadcrumb` sem cor fixa;
- filtros ficam em `.card` ou faixa de formulario Bootstrap, com labels visiveis;
- tabelas usam `.table`, `.table-responsive`, `.align-middle` e cabecalho claro;
- graficos e visualizacoes usam fundo transparente ou card claro, sem tema escuro proprio;
- barras de progresso usam `.progress` e `.progress-bar` solidas, sem listras/animacoes;
- estados usam `.alert` e `.badge` semanticos do Bootstrap;
- paginas internas nao devem criar menus locais de usuario nem alternadores de tema.

## Componentes

Use sempre o componente Bootstrap mais proximo antes de criar qualquer CSS:

| Necessidade | Bootstrap |
|---|---|
| Acao primaria | `.btn .btn-primary` |
| Acao secundaria | `.btn .btn-outline-secondary` |
| Navegacao local | `.nav`, `.nav-tabs`, `.breadcrumb` |
| Conteudo agrupado | `.card` |
| Dados tabulares | `.table`, `.table-responsive` |
| Feedback | `.alert` |
| Status compacto | `.badge` |
| Dialogo | `.modal` |
| Selecoes | `.form-select`, `.form-check` |
| Busca | `.form-control`, `.input-group` |

## CSS permitido

CSS central pode:

- organizar navbar;
- padronizar cards, botoes, tabelas e formularios quando Bootstrap precisar de complemento;
- preservar dimensoes estaveis de graficos e areas funcionais;
- remover tema escuro nesta fase;
- manter compatibilidade temporaria com legado.

CSS central nao deve:

- copiar tema externo literalmente;
- recriar componentes Bootstrap;
- criar paletas por modulo;
- adicionar decoracao visual sem funcao;
- reativar tema escuro antes da fase propria.

## Checklist de tela

1. A tela esta em tema claro.
2. Nao existe seletor de tema visivel.
3. A navbar vem de `includes/navbar.php` ou de wrapper que chama `render_platform_navbar`.
4. O menu de usuario vem da navbar central.
5. Componentes principais sao Bootstrap.
6. Boxes, botoes, tabelas e formularios seguem a hierarquia visual definida aqui.
7. A pagina nao usa cores ou estrutura que pareca portal oficial externo.
8. A logo RENAST esta presente quando fizer sentido.
