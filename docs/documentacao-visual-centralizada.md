# Documentacao visual centralizada

Este arquivo organiza a documentacao de aparencia, UX, design e interface da Plataforma RENAST.

## Fonte geral

A fonte geral e obrigatoria e:

- `docs/identidade-visual-ux.md`

Esse guia central define regras compartilhadas para:

- tokens Bootstrap e temas claro/escuro/auto;
- paleta institucional;
- paleta da logo RENAST: `#FF0600`, `#5DCF00`, `#666666`, `#FFFFFF`, `#00B0FF`, `#FF8C00`;
- tipografia;
- cards, paineis e superficies;
- botoes e acoes;
- formularios;
- navbar;
- logos;
- tabelas;
- paginacao;
- skeleton screen;
- filtros;
- fluxos hierarquicos;
- vocabularios controlados;
- comportamento operacional de UX.

O CSS global correspondente fica em:

- `assets/css/style.css`

## Regra operacional de auditoria visual

Antes de finalizar qualquer ajuste de interface, rode uma varredura nos arquivos alterados e elimine estilos locais que recriem o design system. Em especial, nao deixe passar:

- `backdrop-filter`, blur visual, blobs, orbs, glows ou gradientes radiais decorativos;
- roxos legados (`#4f46e5`, `#6366f1`, `#7c3aed`, `#9333ea`) e cores de modulo fora dos tokens;
- `border-radius` acima de `8px` em cards, paineis, caixas de filtro, navcards e superficies;
- sombras coloridas, sombras profundas ou fundos sem relacao com `--bs-body-bg` e `--bs-tertiary-bg`;
- barras de progresso com gradiente, `progress-bar-striped`, `progress-bar-animated` ou classes `bg-*` para progresso comum;
- paginas de modulo sem `data-module` no `<html>` ou sem `assets/css/style.css` carregado depois do CSS local.

O CSS local de modulo pode existir apenas para layout e comportamento especifico. Paleta, tema, navbar, cards, botoes, tabelas, filtros, badges e estados de hover devem herdar do guia central.

## Regra de contraste

Toda cor de modulo deve passar pela matriz de tokens acessiveis. Nao usar a cor pura da marca diretamente como texto ou fundo interativo. Use:

- `--module-brand` somente para identidade/logotipo/decoracao pontual;
- `--accent-ui` para texto, icone, link e borda ativa;
- `--accent-solid` com `--accent-on-solid` para botoes e estados preenchidos;
- `--accent-surface` com `--accent-surface-text` para badges e chips.

O alias `--accent` existe apenas para compatibilidade e deve apontar para `--accent-ui`.

Barras de progresso tambem seguem essa matriz: o preenchimento padrao usa `--accent-solid` e o texto usa `--accent-on-solid`. Use estado semantico colorido apenas quando o progresso representa erro ou alerta real, nao para indicar progresso normal.

## Documentacao especifica por modulo

Documentos especificos podem detalhar apenas o que e proprio do modulo. Eles nao devem redefinir tokens, paleta, tema, navbar, botoes ou layout geral.

### CAT

- `cat/README.md`
  - Escopo do modulo, paginas, ETL, rastreabilidade, agregador CNPJ, dicionarios e comportamento funcional.
- `cat/docs/agregadores_hierarquicos.md`
  - Padrao funcional dos fluxos Territorio, CNAE, CBO e CNPJ no CAT.
- `cat/docs/opencnpj.md`
  - Integracao OpenCNPJ, cache, seguranca e endpoints.
- `cat/instrucoes-agente.md`
  - Orientacao do agente do modulo; aponta para o guia central para visual/UX.

### CAREX

- `carex/docs/identidade-visual.md`
  - Ativos especificos, nome do modulo e compatibilidade local.
- `carex/instrucoes-agente.md`
  - Orientacao do agente do modulo; aponta para o guia central para visual/UX.

### LDRT

- `ldrt/instrucoes-agente.md`
  - Orientacao do agente do modulo; aponta para o guia central para visual/UX.
- `ldrt/README.md`
  - Documentacao funcional e de dados do modulo.

### Fichario

- `fichario/instrucoes-agente.md`
  - Orientacao do agente do modulo; aponta para o guia central para visual/UX.
- `fichario/README.md`
  - Documentacao funcional do modulo.
- `fichario/docs/developer/developer_guidelines.md`
  - Regras tecnicas internas; quando tratar de UI, deve obedecer ao guia central.

## Pontes locais de compatibilidade

Estes arquivos nao sao fonte de decisao visual. Eles existem para preservar links antigos e apontar ao guia central:

- `system.md`
- `carex/assets/definicao-padroes.md`
- `carex/assets/system.md/DESIGN.md`
- `ldrt/assets/definicao-padroes.md`
- `fichario/assets/definicao-padroes.md`
- `fichario/system_md/DESIGN.md`
- `fichario/system_md/palheta.md`

## Arquivos que nao devem concentrar regras visuais gerais

Estes arquivos podem mencionar telas ou interface, mas nao devem definir padroes globais de aparencia/UX:

- READMEs funcionais de modulo;
- documentacao de banco de dados;
- documentacao de API;
- documentacao de seguranca;
- documentacao de deploy;
- especificacoes de negocio;
- planos de migracao.

Quando algum deles precisar citar visual/UX, deve apontar para `docs/identidade-visual-ux.md` ou para uma documentacao especifica do modulo.

## Regra de atualizacao

Ao surgir uma nova regra de aparencia, UX, design ou interface:

1. Se for geral, atualizar `docs/identidade-visual-ux.md`.
2. Se for especifica de modulo, registrar no documento especifico do modulo e apontar para o guia central.
3. Se uma regra antiga conflitar com o guia central, tratar a regra antiga como legado.
4. Nao criar novo guia paralelo de tema, paleta, botao, navbar, tabela, formulario ou layout.

## Prioridade em caso de conflito

1. `docs/identidade-visual-ux.md`
2. `assets/css/style.css`
3. documento especifico do modulo, somente no que for especifico do modulo
4. `instrucoes-agente.md` do modulo
5. pontes locais de compatibilidade
6. material historico legado
