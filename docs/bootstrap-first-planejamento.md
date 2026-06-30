# Plataforma RENAST - Planejamento Bootstrap-first

Este documento e a fonte de verdade para a reconstrução visual iniciada apos o reset completo do tema. A regra de base e simples: usar Bootstrap 5.3 oficial sempre que possivel, sem tema paralelo, sem paleta propria e sem fonte customizada. A unica identidade visual mantida nesta fase e a logo.

## Objetivo

Reconstruir a interface da plataforma e dos modulos com uma base previsivel, clara e facil de manter. A partir desta etapa, a interface adota uma referencia visual externa para orientar boxes, botoes, componentes e cores, mas continua implementada em Bootstrap e sem se apresentar como site governamental.

## Decisoes de base

1. Bootstrap 5.3 e a fonte visual principal.
2. Bootstrap Icons e a fonte de icones padrao quando houver icones.
3. `public_html/assets/css/style.css` fica pequeno e canônico, apenas com compatibilidade estrutural temporaria.
4. CSS local de modulo so pode resolver layout funcional, dimensoes de graficos, areas sticky e compatibilidade com bibliotecas.
5. Nao criar cores, fontes, sombras, gradientes, efeitos, bordas ou componentes visuais proprietarios nesta fase.
6. Logos podem aparecer como ativo institucional; cores da logo nao devem virar botao, texto, badge, progresso ou tabela.
7. Tema claro e obrigatorio nesta fase; `data-bs-theme` deve ficar em `light`.
8. Tema escuro fica suspenso e sera derivado futuramente do claro.
9. A referencia visual orienta clareza, consistencia e reuso de componentes, mas nao deve ser copiada como tema literal.

## Fora do escopo nesta fase

- Tema escuro.
- Paleta institucional por modulo.
- Tipografia proprietaria.
- Gradientes, fundos decorativos, blobs, cards vitrificados ou sombras coloridas.
- Componentes desenhados do zero quando Bootstrap ja oferece equivalente.
- Diferenciacao visual forte entre modulos por cor.

## Contrato minimo de uma pagina

Toda pagina publica, administrativa ou operacional deve:

1. Carregar Bootstrap CSS oficial.
2. Carregar Bootstrap Icons quando usar classes `bi`.
3. Carregar CSS local estrutural, se existir.
4. Carregar `public_html/assets/css/style.css` por ultimo.
5. Carregar `public_html/assets/js/theme-switcher.js` para fixar o tema claro e preparar extensao futura.
6. Usar componentes Bootstrap para navbar, cards, formularios, tabelas, alertas, tabs, modais, paginacao, breadcrumbs, badges e barras de progresso.
7. Evitar `<style>` em paginas PHP.
8. Evitar `style="..."` em markup; excecoes devem ser tecnicas, documentadas e preferencialmente geradas por biblioteca.
9. Preferir utilitarios Bootstrap (`mb-3`, `d-flex`, `gap-2`, `text-body-secondary`, `border`, `rounded`) a classes novas.

## Ordem recomendada no head

```html
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/app.css" rel="stylesheet">
<link href="../assets/css/style.css" rel="stylesheet">
<script src="../assets/js/theme-switcher.js"></script>
```

Adapte os caminhos relativos conforme a pasta. Se a tela nao tiver CSS local, remova essa linha.

## Navbar

A barra superior deve ser compartilhada sempre que possivel. Ela deve conter:

- Logo e nome da plataforma.
- Itens comuns da plataforma.
- Itens especificos do modulo atual.
- Tema claro fixo por `public_html/assets/js/theme-switcher.js`, sem seletor visivel nesta fase.
- Area de autenticacao quando aplicavel.

Use classes Bootstrap (`navbar`, `navbar-expand-lg`, `navbar-toggler`, `navbar-nav`, `nav-link`, `dropdown`) e evite menus customizados.

Implementacao canonica:

- usar `includes/navbar.php`;
- chamar `render_platform_navbar($module, $activePage)` em paginas internas;
- usar a mesma funcao em landpages, deixando a propria navbar aplicar a variacao simplificada;
- nao criar menus locais de usuario como "Ola, ..." dentro dos modulos;
- nao calcular papel administrativo dentro de cada modulo para decidir a area de usuario;
- o dropdown de usuario, o link "Painel Admin", o link "Minha Conta" e o logout pertencem a navbar central.

Itens especificos de modulo podem variar por pagina, mas a area direita da navbar deve ser comum para todos os modulos e papeis.

## Componentes preferidos

| Necessidade | Padrao Bootstrap |
|---|---|
| Bloco de conteudo | `.card` |
| Listagem tabular | `.table`, `.table-striped`, `.table-hover`, `.table-responsive` |
| Formulario | `.form-label`, `.form-control`, `.form-select`, `.form-check` |
| Acoes | `.btn`, `.btn-primary`, `.btn-outline-secondary`, `.btn-link` |
| Estado informativo | `.alert` |
| Indicador compacto | `.badge` |
| Abas | `.nav`, `.nav-tabs`, `.tab-content` |
| Navegacao hierarquica | `.breadcrumb` |
| Modal | `.modal` |
| Progresso | `.progress`, `.progress-bar` |
| Placeholder | `.placeholder`, `.placeholder-glow` |

## Barras de progresso

Barras de progresso devem ser solidas e padronizadas:

- usar `.progress` e `.progress-bar`;
- usar a cor padrao do Bootstrap, salvo excecao funcional;
- nao usar `progress-bar-striped`;
- nao usar `progress-bar-animated`;
- nao usar gradiente;
- texto dentro da barra so quando houver espaco e contraste.

## Tabelas

Tabelas devem priorizar leitura:

- envolver em `.table-responsive`;
- usar `.table` como base;
- adicionar `.table-sm` em telas densas;
- adicionar `.align-middle` quando houver botoes, badges ou controles;
- evitar cores por coluna, por linha ou por modulo;
- usar badges Bootstrap para estados, com texto claro e objetivo.

## Formularios e filtros

Filtros devem usar componentes Bootstrap nativos:

- labels visiveis ou `aria-label` quando compactos;
- grupos com `.row`, `.col-*`, `.input-group` e `.d-flex`;
- botoes com icones Bootstrap Icons quando a acao for recorrente;
- feedback com `.invalid-feedback`, `.valid-feedback` ou `.alert`.

## Tema claro

Nao codificar cores de tema localmente. Usar:

- `text-body`;
- `text-body-secondary`;
- `bg-body`;
- `bg-body-tertiary`;
- `border`;
- `link-*` somente quando fizer sentido sem quebrar contraste.

O teste minimo e verificar navbar, cards, tabelas, formularios, breadcrumbs, badges, botoes e progresso em tema claro. Nao deve haver seletor claro/escuro visivel nesta fase.

## Regras para CSS novo

Antes de criar uma classe CSS, perguntar:

1. Existe um componente Bootstrap que resolve?
2. Existe um utilitario Bootstrap que resolve?
3. A regra e estrutural ou visual?
4. Ela funciona no tema claro sem cor fixa?
5. Ela sera reutilizada em mais de uma tela?

Se a resposta nao justificar CSS novo, usar Bootstrap.

## Roteiro de implementacao

1. Consolidar documentacao e exemplos no painel administrativo/desenvolvimento.
2. Remover referencias a diretrizes antigas de identidade visual por modulo.
3. Revisar admin, acesso e landing geral como referencia Bootstrap-first.
4. Revisar landing pages dos modulos.
5. Revisar telas internas por modulo, uma area por vez.
6. Remover CSS legado restante depois que as telas estiverem validadas.
7. Planejar personalizacao central futura somente depois da base vanilla estar estavel.

## Criterio de pronto

Uma tela esta pronta nesta fase quando:

- nao depende de CSS visual proprietario;
- nao usa cores de modulo para leitura;
- funciona corretamente em tema claro;
- usa Bootstrap para os componentes principais;
- nao tem `<style>` local;
- nao tem `style="..."` manual;
- passa por `php -l` quando for PHP;
- nao gera erro de console nas interacoes principais.
