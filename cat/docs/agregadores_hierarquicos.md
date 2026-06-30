# Agregadores hierarquicos do CAT

Este documento concentra regras funcionais dos fluxos de agregacao do modulo CAT.

Regras visuais, tema, navbar, botoes, tabelas, badges, filtros e contraste nao devem ser definidas aqui. Para esses assuntos, use:

- `../../docs/identidade-visual-ux.md`
- `../../docs/tema-css-bootstrap-modulos.md`

## Escopo funcional

Os agregadores do CAT devem responder, em cada nivel de navegacao:

1. Onde estou na hierarquia?
2. Quantos eventos existem neste nivel?
3. Quais filhos ou relacionados posso abrir?
4. Quais CATs individuais compoem o agregado?

## Fluxos cobertos

- Territorio.
- CNAE.
- CBO.
- CNPJ, matriz e filial.

## Regras de leitura

- Exibir rotulos descritivos antes de codigos.
- Manter codigos brutos em tooltip, badge secundaria ou coluna discreta quando forem necessarios para auditoria.
- Permitir navegacao do agregado para a lista de CATs individuais.
- Preservar filtros aplicados ao navegar entre lista, detalhe e retorno.
- Priorizar cache local e evitar chamadas externas massivas.

## Relacao com a interface

As telas desses fluxos devem herdar:

- navbar compartilhada de `includes/navbar.php`;
- compatibilidade visual de `public_html/assets/css/style.css`;
- tema claro fixado por `public_html/assets/js/theme-switcher.js`;
- componentes Bootstrap-first sem paleta propria do CAT nesta fase.
