# DefiniÃ§Ãµes de PadrÃµes Visuais, Interface e Temas - Plataforma RENAST

Este Ã© o guia central de estilo, interface e experiÃªncia da Plataforma RENAST. Ele substitui as definiÃ§Ãµes locais antigas em `carex/`, `fichario/` e `ldrt/` sempre que houver conflito.

Escopo: Portal Central, Acesso, Carex-BR, FichÃ¡rio AcadÃªmico, LDRT e pÃ¡ginas internas integradas.

## 1. Fonte de Verdade

- O CSS global da plataforma fica em `public_html/assets/css/style.css`.
- O mapa da documentação visual fica em `public_html/docs/documentacao-visual-centralizada.md`.
- O seletor de tema e a troca automática de logos ficam em `public_html/assets/js/theme-switcher.js`.
- As folhas locais (`public_html/acesso/assets/app.css`, `public_html/carex/assets/app.css`, `public_html/fichario/assets/app.css` e estilos inline de landing pages) podem definir componentes específicos, mas devem herdar os tokens globais e respeitar este guia.
- Guias locais de design devem apontar para este arquivo, evitando decisÃµes paralelas de paleta, tipografia, tema ou navegaÃ§Ã£o.

## 2. PrincÃ­pios de Interface

- A plataforma usa Bootstrap 5.3 com suporte a `[data-bs-theme="light"]` e `[data-bs-theme="dark"]`.
- O tema inicial Ã© `auto`, seguindo a preferÃªncia do sistema operacional.
- Landing pages devem ser diretas e institucionais, com identidade RENAST grande no hero principal e navegaÃ§Ã£o superior simplificada.
- PÃ¡ginas internas devem priorizar leitura, busca, tabelas e fluxos operacionais sem excesso decorativo.
- Componentes devem preservar contraste, foco visÃ­vel, estados de hover/ativo/desabilitado/carregando e estabilidade de layout.
- Fundos devem seguir o padrÃ£o bÃ¡sico do Bootstrap: `--bs-body-bg`, `--bs-tertiary-bg`, `--bs-border-color` e `--bs-secondary-color`, sem glows, blobs ou gradientes decorativos como base visual.
- Antes de criar novo CSS, JavaScript ou marcaÃ§Ã£o repetida, verificar se jÃ¡ existe componente, helper, parcial, folha ou script reutilizÃ¡vel para o mesmo padrÃ£o. Quando a mesma UI aparecer em mais de uma pÃ¡gina, extrair para arquivo compartilhado e deixar cada pÃ¡gina apenas com dados/configuraÃ§Ã£o local.

## 3. Tipografia

- Fonte global preferencial: `Plus Jakarta Sans`, carregada em `assets/css/style.css`.
- TÃ­tulos (`h1` a `h6`, `.h1`, `.h2`, `.h3`, `.display-5`) devem usar `Plus Jakarta Sans`.
- Hierarquia global:
  - `h1`, `.h1`, `.display-*`, `.page-title`, `.main-title`, `.hero-title`: peso 700, `clamp(2rem, 4vw, 3.25rem)`, sem letter-spacing negativo.
  - `h2`, `.h2`, `.section-title`: peso 600, `clamp(1.5rem, 2.4vw, 2rem)`.
  - `h3`, `.h3`, `.card-title`: peso 600, `1.25rem`.
  - `h4`, `h5`, `h6`: peso 600.
- ExceÃ§Ãµes locais permitidas:
  - FichÃ¡rio AcadÃªmico pode usar `Outfit` no corpo e em elementos de fichamento, mantendo tÃ­tulos alinhados ao padrÃ£o global quando o CSS global estiver carregado.
  - CAREX e LDRT podem manter `Inter` em Ã¡reas operacionais jÃ¡ existentes, desde que cores, contraste, navbar e tema sigam os tokens globais.

## 4. Tokens Globais de Tema (Bootstrap 5.3)

Os tokens abaixo sÃ£o a base para fundos, textos, bordas, cards, menus e componentes compartilhados.

### Claro (`[data-bs-theme="light"]`)

- `--bs-body-bg` (bg): `#ffffff`
- `--bs-tertiary-bg` (bg-page): `#f8f9fa`
- `--bs-heading-color` (text-heading): `#212529`
- `--bs-body-color` (text-body): `#212529`
- `--bs-secondary-color` (text-secondary): `#6c757d`
- `--bs-border-color` (border): `#dee2e6`
- `--bs-primary` (primary): `#006392` (`--brand-azul-4`)
- `--bs-success` (success): `#198754`
- `--bs-warning` (warning): `#ffc107`
- `--bs-danger` (danger): `#dc3545`
- `--logo-text`: `#212529`

### Escuro (`[data-bs-theme="dark"]`)

- `--bs-body-bg` (bg): `#1F2937`
- `--bs-tertiary-bg` (bg-surface): `#273444`
- `--bs-heading-color` (text-heading): `#f8f9fa`
- `--bs-body-color` (text-body): `#dee2e6`
- `--bs-secondary-color` (text-secondary): `#adb5bd`
- `--bs-border-color` (border): `#495057`
- `--bs-primary` (primary): `#006392` (`--brand-azul-4`)
- `--bs-success` (success): `#198754`
- `--bs-warning` (warning): `#ffc107`
- `--bs-danger` (danger): `#dc3545`
- `--logo-text`: `#f8f9fa`

Nota: `--bs-primary` usa o azul institucional oficial do Figma (`--brand-azul-4` / `#006392`), nao o azul Bootstrap stock `#0d6efd` nem o azul puro da marca `#00B0FF`. A justificativa de contraste e uso esta na secao "Paleta Institucional Derivada".

## 5. Paleta, Acentos e SuperfÃ­cies SemÃ¢nticas

Todas as interfaces devem usar a paleta basica do Bootstrap 5.3 como esquema de cores de UI, com a cor primaria oficial substituida pelo azul institucional RENAST:

- **Primaria**: `--bs-primary` / `#006392` (`--brand-azul-4`) para acoes principais, links, foco e CTAs. Este valor vem da escala oficial do Figma e pertence a faixa recomendada para componentes de interface.
- **Sucesso**: `--bs-success` / `#198754`.
- **Aviso**: `--bs-warning` / `#ffc107`.
- **Perigo**: `--bs-danger` / `#dc3545`.
- **InformaÃ§Ã£o**: `--bs-info` / `#0dcaf0`.
- **Bordas**: `--bs-border-color` (`#dee2e6` claro, `#495057` escuro).
- Componentes customizados antigos (`.btn-carex`, `.btn-fichario`, `.btn-ldrt`, `.badge-soft`, `.glass-card`, `.panel-card`) devem renderizar com os mesmos tokens.

### Cores Institucionais RENAST (Apoio Visual)

A paleta extraÃ­da da marca pode ser usada como apoio visual secundÃ¡rio, nunca substituindo os tokens de tema ativos:
- Ciano `#009FE3`, azul `#312783`, verde `#009640`, magenta `#E6007E`, vermelho `#E30613`, amarelo `#FFED00`.
- Neutros: preto `#1D1D1B`, grafite `#3C3C3B`, cinza mÃ©dio `#878786`, cinza claro `#EBEBEB`, branco `#FFFFFF`.
- Usar a paleta da marca para ilustraÃ§Ãµes, grÃ¡ficos, swatches e destaques controlados.

### Paleta da Logo RENAST

A paleta operacional extraida da logo RENAST deve estar documentada de forma explicita para uso em logotipo, marcas, swatches, graficos, ilustracoes e pequenos destaques institucionais. Ela nao deve ser usada como fundo de botao, badge, card, glow ou area dominante de interface. Para componentes de UI, usar sempre a paleta institucional derivada.

- Azul `#00B0FF`
- Vermelho `#FF0600`
- Verde `#5DCF00`
- Laranja `#FF8C00`
- Cinza `#666666`
- Branco `#FFFFFF`

Uso recomendado:

- `#00B0FF`: logotipo, swatches, ilustracoes e decoracao pontual.
- `#FF0600`: logotipo, swatches, ilustracoes e decoracao pontual.
- `#5DCF00`: logotipo, swatches, ilustracoes e decoracao pontual.
- `#FF8C00`: logotipo, swatches, ilustracoes e decoracao pontual.
- `#666666`: logotipo, swatches e referencia neutra da marca.
- `#FFFFFF`: contraste sobre fundos escuros ou blocos de cor da marca.

### Paleta Institucional Derivada

A fonte de verdade da paleta institucional e a escala oficial de 7 degraus exportada do Figma. Os tokens devem ser nomeados como `--brand-{cor}-{degrau}` para permitir uso consistente de tints, cores puras e shades sem inventar valores paralelos.

| Degrau | Azul | Vermelho | Verde | Laranja |
|---|---|---|---|---|
| 0 | `#E7F2FF` | `#FFECEC` | `#73FC00` | `#FFEDE6` |
| 1 | `#A4D2FF` | `#FFBBBA` | `#5DCF00` | `#FFC4A8` |
| 2 | `#00B0FF` | `#FF7D7D` | `#48A400` | `#FF8C00` |
| 3 | `#0089C7` | `#FF0600` | `#347B00` | `#C86D00` |
| 4 | `#006392` | `#BC0300` | `#225500` | `#944F00` |
| 5 | `#004061` | `#7D0100` | `#103100` | `#633300` |
| 6 | `#002033` | `#430000` | `#041500` | `#361900` |

Atencao de governanca: a cor pura da logo nao esta no mesmo degrau em todas as colunas. Azul `#00B0FF` e laranja `#FF8C00` estao no degrau 2. Vermelho `#FF0600` esta no degrau 3 e verde `#5DCF00` esta no degrau 1; essas escalas devem ser ajustadas no Figma para que a cor pura fique no degrau 2 antes de uma versao definitiva do design system. Ate essa correcao, os tokens de UI usam os valores oficiais exportados no degrau 4.

Uso por degrau:

- Degraus 0-1: fundo de superficie/tint muito claro, chip, hover sutil e fundo de badge; nunca texto.
- Degrau 2: logotipo, ilustracao, swatch e decoracao pontual; nunca texto ou fundo de botao com texto branco.
- Degrau 3: limite para texto grande ou icone; nao usar como texto de paragrafo.
- Degrau 4: faixa recomendada para componentes de interface (`--bs-primary`, links, texto de acento e acento de modulo).
- Degraus 5-6: texto de alto contraste, titulos sobre fundo claro e usos pontuais no tema escuro.

Regra de uso: cor pura da logo = logotipo, ilustracao, swatch e decoracao pequena; degrau 4 = botao, link, foco, icone, borda, indicador, grafico, acento de modulo e qualquer componente de interface.

`--bs-success` e `--bs-warning` continuam com os valores semanticos Bootstrap (`#198754` e `#ffc107`). O verde institucional `--brand-verde-4` e o laranja institucional `--brand-laranja-4` sao identidade de modulo, nao substitutos globais para sucesso ou aviso.

### Acento por Modulo

Cada modulo declara `--accent` uma unica vez via `[data-module="..."]`. O acento serve para icone de destaque, borda/indicador pontual, grafico e hover de card quando previsto. O botao `.btn-primary` global continua usando `--bs-primary` em todos os modulos.

- Portal / Acesso: `#006392` / `--brand-azul-4` (azul institucional).
- CAT: `#464B51` (cinza institucional). Nao usar `#a855f7`; o roxo e legado removido e nao pertence a paleta da logo.
- CAREX-BR: `#BC0300` / `--brand-vermelho-4` (vermelho institucional).
- Fichario Academico: `#225500` / `--brand-verde-4` (verde institucional).
- LDRT: `#944F00` / `--brand-laranja-4` (laranja institucional).

## 6. Cards, PainÃ©is e Fundos

- Cards compartilhados usam `.system-card`, `.glass-card` ou `.panel-card`.
- Fundo e borda devem vir de tokens Bootstrap: `--bs-body-bg`, `--bs-tertiary-bg` e `--bs-border-color`.
- O padrÃ£o visual atual Ã© Bootstrap bÃ¡sico: fundo sÃ³lido, borda discreta, raio de `8px` e sem `backdrop-filter`.
- Raios grandes sÃ³ devem aparecer em exceÃ§Ãµes justificadas; em ferramentas operacionais, preferir `8px` para tabelas, painÃ©is e controles densos.
- NÃ£o usar sistemas paralelos como "claymorphism" para novas telas da plataforma.
- Nao usar gradientes decorativos em botoes, cards, headers de cards ou wrappers de icone. Tokens historicos como `--primary-gradient`, `--carex-gradient`, `--fichario-gradient`, `--ldrt-gradient`, `--access-gradient`, `--cat-gradient` e `--fiocruz-gradient` devem resolver para cor solida institucional ou ser evitados.
- Nao usar `box-shadow` colorido com `rgba()` da propria cor do componente em opacidade alta (`.2` a `.4`) para criar efeito glow. Sombras, quando necessarias, devem ser neutras e discretas (`rgba(0,0,0,.08)` a `rgba(0,0,0,.15)`).
- A camada global `assets/css/style.css` padroniza componentes Bootstrap e classes legadas equivalentes: botÃµes, cards, modais, dropdowns, tabelas, formulÃ¡rios, badges, nav-tabs, nav-pills e list-groups.
- Boxes/painÃ©is equivalentes (`.box`, `.info-box`, `.panel`, `.glass-card`, `.glass-header`, `.action-card`, `.work-card`, `.admin-user-card`, `.estimate-*`) devem ter fundo `--bs-body-bg`, borda `--bs-border-color`, raio `--bs-border-radius` e sem sombra.
- Componentes visuais especializados que forem usados por mais de uma tela devem ter CSS e JS prÃ³prios, versionados junto do mÃ³dulo ou na camada global. Evitar copiar blocos longos de estilo ou renderizaÃ§Ã£o entre pÃ¡ginas.

## 7. Chips, Badges e Tags

- Chips, badges e tags devem usar o mesmo padrÃ£o: `inline-flex`, `border-radius: 50rem`, fundo `--bs-tertiary-bg`, borda `--bs-border-color`, texto `--bs-body-color`, peso 500 e sem transformaÃ§Ã£o para maiÃºsculas.
- Classes cobertas pela camada base: `.chip`, `.badge`, `.badge-custom`, `.badge-soft`, `.badge-ok`, `.badge-warn`, `.tag-pill`, `.tag-badge`, `.app-badge-*`, `.related-classification-badge`, `.status-pill`, `.count-badge`.
- Estado ativo ou primÃ¡rio usa `--bs-primary` com texto branco.
- Badges contextuais nativos (`.bg-success`, `.bg-warning`, `.bg-danger`, `.bg-info`, `.text-bg-*`) preservam as cores semÃ¢nticas Bootstrap.

## 8. BotÃµes

- Usar classes Bootstrap nativas sempre que possÃ­vel: `.btn`, `.btn-primary`, `.btn-outline-primary`, `.btn-outline-secondary`.
- Classes legadas (`.btn-system`, `.btn-carex`, `.btn-fichario`, `.btn-ldrt`, `.btn-register-action`, `.cx-btn-primary`, `.cookie-btn`) devem renderizar como `.btn-primary`.
- BotÃµes alternativos (`.btn-secondary-action`, `.cx-btn-ghost`) devem renderizar como `.btn-outline-primary`.
- Todos os botÃµes usam `--bs-border-radius`, peso 500 e sem sombras.
- Grupos de botÃµes e aÃ§Ãµes devem ficar alinhados Ã  direita do bloco, cabeÃ§alho, formulÃ¡rio ou card a que pertencem.

- Em paginas internas, comandos globais da tela devem preferencialmente aparecer como menu de acoes no canto superior direito do cabecalho: grupo compacto de botoes icon-only, com dimensoes estaveis, `title` e `aria-label`. Use esse padrao para acoes como voltar, atualizar, abrir entidade relacionada, exportar, ver JSON ou navegar para detalhes. Evite duplicar ali itens que ja estao no menu horizontal principal.

## 9. Formularios e Textos de Orientacao

- Campos devem usar o padrao Bootstrap 5.3: `.form-control`, `.form-select`, `.form-check-input` e `.input-group-text`.
- Fundo: `--bs-body-bg`; borda: `--bs-border-color`; texto: `--bs-body-color`.
- Foco: borda `--bs-primary` e `box-shadow` discreto `rgba(0, 99, 146, 0.25)`.
- Placeholders devem usar `--bs-secondary-color` com opacidade reduzida, nunca branco fixo ou cinza customizado fora dos tokens.
- Campos `disabled` e `readonly` devem usar `--bs-tertiary-bg`, texto `--bs-secondary-color` e `opacity: 1`.
- Textos de orientacao de campo devem usar `.form-text` sempre que possivel. Classes legadas equivalentes (`.field-help`, `.field-hint`, `.field-note`, `.form-help`, `.input-help`, `.help-text`, `.hint-text`, `.orientation-text`, `.orientacao-campo`, `.descricao-campo`) devem renderizar como texto secundario Bootstrap.
- Autocomplete, sugestoes e listas de filtro devem usar `--bs-body-bg`, `--bs-border-color` e sem sombras decorativas.

## 10. Navbar e Identidade Visual

### Landing Pages

- Navbar simplificada: sem logotipo ou tÃ­tulo de mÃ³dulo no lado esquerdo.
- Mostrar apenas seletor de tema e controle de sessÃ£o de usuÃ¡rio.
- A identidade RENAST e o nome do mÃ³dulo devem aparecer no hero principal.
- Logo de hero deve usar `.landing-hero-logo` junto de `.platform-logo-img`, com altura grande e responsiva.
- Logo de navbar deve usar `.navbar-logo-img` junto de `.platform-logo-img`, limitado a `28px` de altura e `132px` de largura maxima.
- TÃ­tulo e texto de apresentaÃ§Ã£o do hero devem usar escala de destaque (`clamp(...)`) e largura confortÃ¡vel para leitura.

### PÃ¡ginas Internas

- Usar `.app-navbar` ou a navbar Bootstrap padronizada pelo CSS global.
- Lado esquerdo: `Logotipo RENAST | TÃTULO DO MÃ“DULO`.
- Logotipo com classes `.platform-logo-img navbar-logo-img`, altura visual padronizada em `28px`.
- Links de navegaÃ§Ã£o devem mostrar estado ativo e hover com contraste suficiente.

### Links de Retorno

- O logotipo RENAST deve apontar para a landing central da plataforma.
- Em mÃ³dulos na raiz de subpasta, usar caminho relativo como `../` ou `../index.php`.
- Em subpastas aninhadas, usar `../../` ou helper equivalente.

## 11. Logos e Favicon

- Para fundo claro: `logo-fundo-claro_horizontal.png` ou `logo-fundo-claro-vertical.png`.
- Para fundo escuro: `logo-fundo-escuro-horizontal.png` ou `logo-fundo-escuro-vertical.png`.
- CAREX também usa os aliases servidos em `public_html/carex/assets/` (`logo-renast-horizontal.png` e `logo-renast-horizontal-dark.png`).
- `theme-switcher.js` troca automaticamente as versÃµes de logo ao alternar tema; novas imagens adicionadas ao DOM tambÃ©m devem receber a versÃ£o correta.

## 12. Contraste e Compatibilidade

Algumas telas nasceram com classes hardcoded para dark mode. No modo claro:

- `.text-white` deve ser remapeada para `var(--text-main)` quando nÃ£o estiver sobre botÃ£o, badge ou fundo sÃ³lido.
- `.text-white-50` e `em.text-white-50` devem ir para `var(--text-muted)`.
- `.bg-black.bg-opacity-25` deve virar sobreposiÃ§Ã£o clara sutil (`rgba(0, 0, 0, 0.04)`).
- Badges soft devem ter texto escuro em fundo claro. O mÃ³dulo Acesso jÃ¡ define `.badge-soft`, `.badge-ok` e `.badge-warn` com contraste adequado.

## 13. Estados e InteraÃ§Ã£o

- TransiÃ§Ãµes devem ser curtas e previsÃ­veis, entre `150ms` e `300ms`.
- BotÃµes e links precisam manter foco visÃ­vel.
- AÃ§Ãµes assÃ­ncronas devem sinalizar espera sem deslocar layout.
- No FichÃ¡rio, usar `FicharioUI.setBusy()` e `FicharioUI.setPanelBusy()` conforme as regras de interface do mÃ³dulo.
- Evitar bloqueio de tela inteira quando apenas um painel estiver aguardando resposta.

## 14. RodapÃ©

RodapÃ©s devem usar texto curto, borda sutil e tokens de tema:

```html
<footer class="text-center py-4 border-top">
    <p class="mb-0 text-muted">&copy; 2026 Plataforma RENAST Online. Todos os direitos reservados.</p>
</footer>
```

Se a pÃ¡gina precisar citar o mÃ³dulo ou instituiÃ§Ãµes parceiras, manter a assinatura complementar em texto secundÃ¡rio.

## 15. Legado Consolidado

- As definiÃ§Ãµes antigas de "Premium Dark Mode" foram incorporadas apenas como referÃªncia histÃ³rica do modo escuro; o padrÃ£o atual Ã© Bootstrap bÃ¡sico em claro/escuro/auto.
- A paleta antiga do CAREX foi incorporada como referÃªncia institucional e nÃ£o substitui os tokens globais.
- Os arquivos antigos de "claymorphism" em CAREX/FichÃ¡rio sÃ£o referÃªncias legadas e nÃ£o devem orientar novas telas.
- O bloco legado "INSTITUTIONAL DESIGN SYSTEM OVERRIDES" do CSS, baseado em cores puras hardcoded com `!important`, gradientes e glow, foi substituido pelos tokens institucionais oficiais. Overrides locais hardcoded de cor de marca, gradiente decorativo ou glow nao devem ser recriados; qualquer nova necessidade deve usar `--bs-primary` ou `--accent` na variante institucional.
- Qualquer novo padrÃ£o de UI, tema, tipografia ou identidade deve ser registrado neste arquivo primeiro.
## 16. Atualizacao de Padrao - Aplicacao Operacional

Esta secao consolida o padrao atual para telas operacionais e fluxos analiticos da plataforma. Ela deve orientar novos desenvolvimentos em todos os modulos.

### 16.1 Fluxos analiticos e hierarquicos

Fluxos com agregacao, vocabulario ou hierarquia devem seguir o desenho:

```text
Lista agregada -> Detalhe do item -> Pai/filhos -> Registros relacionados
```

Cada fluxo deve oferecer:

- listagem agregada;
- pagina de detalhe;
- metricas sumarizadas;
- navegacao para registros que compoem o agregado;
- navegacao para pai e filhos quando houver hierarquia;
- filtros padronizados;
- paginacao completa;
- ordenacao por metricas relevantes.

Quando um modulo tiver fluxos equivalentes, eles devem aparecer no menu horizontal como itens proprios ou agrupados em dropdown claro. No modulo CAT, o padrao e o dropdown `Fluxos` com:

- Territorios;
- CNAE;
- CBO;
- CNPJ.

### 16.2 Vocabularios controlados

Vocabularios controlados devem ter:

- codigo normalizado;
- rotulo;
- nivel;
- codigo do pai;
- caminho hierarquico;
- versao ou fonte documentada.

Exemplos de hierarquia:

- Territorio: regiao -> UF -> municipio.
- CNAE: secao -> divisao -> grupo -> classe -> subclasse.
- CBO: grande grupo -> subgrupo principal -> subgrupo -> familia -> ocupacao.
- CNPJ: matriz -> filial -> CNPJ.

Na interface, o rotulo deve vir primeiro. Codigos tecnicos devem aparecer apenas quando forem importantes para auditoria, preferencialmente em `title`, badge discreta, metadado ou pagina de detalhe.

### 16.3 Menus e acoes

- Menus superiores mostram areas e fluxos principais.
- Comandos contextuais da pagina ficam no grupo de acoes do canto superior direito.
- Evitar duplicar no cabecalho comandos que ja existem no menu horizontal.
- Botoes operacionais devem ser icon-only por padrao.
- Todo botao icon-only deve ter `title` e `aria-label`.
- Botoes de linha em tabelas devem ficar lado a lado, com wrapper `inline-flex`, `gap` curto e `white-space: nowrap`.

### 16.4 Tabelas e paginacao

- Tabelas devem ficar dentro de `.table-responsive`.
- A tabela nao deve escapar do box/painel.
- Listagens paginadas devem mostrar `Exibindo x a y de z`.
- Listagens extensas devem ter seletor de numero de linhas por pagina.
- Paginador completo deve incluir primeira, anterior, proxima e ultima pagina.
- Colunas numericas analiticas relevantes devem ser ordenaveis, com icone indicando direcao.
- Celulas com texto longo devem usar truncamento controlado, tooltip ou quebra responsiva.
- Estados vazio, erro e carregamento devem preservar a estrutura visual da tabela.

### 16.5 Filtros e busca

- Filtros de vocabularios extensos devem usar dropdown/sugestoes a partir do terceiro caractere.
- Filtros dependentes devem respeitar a hierarquia do dominio.
- Municipio depende de UF.
- Subclasse CNAE depende de classe/grupo/divisao quando filtros superiores estiverem ativos.
- Ocupacao CBO depende de familia/subgrupo quando aplicavel.
- Filtros essenciais devem vir da base local ou agregada do modulo, nao de cache externo.

### 16.6 Skeleton screen e carregamento

- Preferir skeleton screen a ampulhetas/spinners em interfaces de dados.
- Skeleton deve ocupar aproximadamente a mesma geometria do conteudo real.
- Spinner pode ser usado apenas em microacoes curtas ou dentro de botao.
- Carregamentos devem atualizar o menor painel possivel, sem bloquear a tela inteira.

### 16.7 Formularios e temas

- Campos devem usar `.form-control`, `.form-select`, `.form-check-input` e tokens Bootstrap.
- Fundo, texto e borda devem funcionar em tema claro e escuro.
- Placeholders devem usar cor secundaria do tema.
- Campos `disabled` e `readonly` devem parecer inativos, mas continuar legiveis.
- Autocomplete e sugestoes devem usar fundo, borda e texto do tema ativo.
