# Documentacao visual centralizada

Esta e a central de documentacao de aparencia, UX e interface da Plataforma RENAST durante a fase Bootstrap-first.

## Fonte de verdade atual

1. `docs/bootstrap-first-planejamento.md`
2. `docs/bootstrap-first-exemplos.md`
3. `docs/diretrizes-visuais-renast.md`
4. `docs/tema-css-bootstrap-modulos.md`
5. `public_html/assets/css/style.css`
6. `public_html/assets/js/theme-switcher.js`

## Documentos substituidos

Os documentos abaixo permanecem apenas como pontes para preservar links antigos:

- `docs/identidade-visual-ux.md`

Qualquer regra antiga sobre paleta institucional, cores por modulo, fontes customizadas, gradientes, sombras ou componentes proprietarios esta suspensa ate novo planejamento.

## Onde consultar na interface

Os documentos centrais ficam expostos no painel:

- Administracao -> CAREX-BR -> Desenvolvimento -> aba Documentacao

O painel de Administracao tambem deve oferecer um atalho direto para esses padroes.

## Como propor nova personalizacao

Antes de adicionar identidade visual centralizada novamente:

1. Garantir que a tela funciona com Bootstrap vanilla.
2. Registrar a necessidade e o problema real de UX.
3. Definir tokens centrais em documento, nao em CSS local.
4. Validar o tema claro e registrar os requisitos para uma futura derivacao de tema escuro.
5. Aplicar primeiro em uma tela piloto.
6. So depois promover para plataforma.

## Auditoria de legado

Ao revisar telas antigas, procurar:

- `<style>`;
- `style="..."`;
- classes antigas de acento, paleta, glass, glow, orb, gradient ou shadow custom;
- imports de fontes externas;
- imports de temas concorrentes ao Bootstrap;
- cores hexadecimais locais;
- botao, card, tabela, badge ou navbar implementado fora de Bootstrap sem necessidade.
