# Auditoria CSS, Bootstrap e tema - 2026-06-29

## Decisoes adotadas

- Bootstrap oficial via CDN deve ser padronizado em `5.3.8`.
- O CSS canonico da plataforma permanece em `public_html/assets/css/style.css`.
- CSS local de modulo pode existir apenas para layout/comportamento especifico, sem paleta paralela.
- CSS legado deve ser arquivado/removido de imports gradualmente, depois de validacao visual.
- Registro historico: esta auditoria foi produzida antes da decisao de suspender tema escuro. A regra atual fica em `docs/diretrizes-visuais-renast.md`: apenas tema claro ativo.

## CDN Bootstrap oficial

CSS:

```html
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
```

JS:

```html
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
```

## Arquivos CSS ativos encontrados

- `public_html/assets/css/style.css`: CSS canonico da plataforma.
- `public_html/acesso/assets/app.css`: CSS especifico do modulo Acesso.
- `public_html/admin/assets/app.css`: CSS especifico da administracao.
- `public_html/carex/assets/app.css`: CSS especifico do CAREX-BR.
- `public_html/fichario/assets/app.css`: CSS especifico do Fichario.
- `public_html/fichario/assets/tag-visualizations.css`: CSS funcional de visualizacoes de tags.

## Arquivos CSS movidos para quarentena

- `_archive/css-theme-legacy/carex-system-md-tokens.css`: antiga referencia documental de tokens CAREX.
- `_archive/css-theme-legacy/fichario-system-md-tokens.css`: antiga referencia documental de tokens Fichario.

Esses arquivos nao devem ser usados como tema ativo. O padrao valido deve estar documentado e aplicado em `public_html/assets/css/style.css`.

## Principais pontos de entrada visual

- `includes/navbar.php`: navbar unificada de plataforma e modulos.
- `includes/header.php`: header legado ainda usado por partes antigas.
- `acesso/src/bootstrap.php`: renderizacao base do Acesso.
- `fichario/bootstrap.php`: helpers e renderizacao base do Fichario.
- Paginas PHP avulsas em `public_html/carex`, `public_html/cat`, `public_html/ldrt` e `public_html/fichario`.

## Achados de Bootstrap

- Bootstrap `5.3.3` ainda aparecia em varias paginas e helpers.
- Nao foram encontrados Bootswatch, AdminLTE, Tabler ou CoreUI ativos no primeiro inventario.
- Muitas paginas ainda importam Bootstrap diretamente, sem passar por um unico helper de `<head>`.

## Riscos visuais recorrentes

- Cores hardcoded em `<style>` dentro de paginas CAT, LDRT, CAREX e Fichario.
- Variaveis locais como `--bg-color`, `--card-bg`, `--text-color` e `--field-bg` repetidas por pagina.
- Uso de `rgba(...)`, sombras e backgrounds transluidos que quebram contraste entre tema claro e escuro.
- Badges, breadcrumbs, tabelas e botoes com overrides locais.
- Barras de progresso listradas/animadas ainda precisam ser neutralizadas pelo CSS global.
- Alguns estilos inline ainda definem cor/fundo/borda diretamente.

## Plano de saneamento incremental

1. Padronizar todos os links Bootstrap para CDN `5.3.8`.
2. Manter `style.css` como fonte canonica de tokens e componentes globais.
3. Manter CSS local ativo apenas quando ele for estrutural ou funcional.
4. Migrar cores locais para variaveis Bootstrap e tokens `--brand-*`/`--module-*`.
5. Remover estilos inline que controlam paleta.
6. Validar visualmente os modulos na ordem: Portal/Acesso, Fichario, CAREX-BR, CAT, LDRT.
7. Arquivar/remover arquivos e imports legados somente depois de confirmar que nao ha regressao.

## Checklist de validacao

- `rg "bootstrap@5.3.3|bootstrap@4|bootstrap@5.2|bootswatch|adminlte|tabler|coreui"`
- `rg "progress-bar-striped|progress-bar-animated"`
- `rg "style=\"" public_html includes acesso cat carex fichario ldrt`
- `rg "#[0-9a-fA-F]{3,8}|rgb\\(|rgba\\(|hsl\\(" public_html includes acesso cat carex fichario ldrt`
- `php -l` nos arquivos PHP alterados.
- Registro historico: validacao atual deve ser feita em tema claro.
