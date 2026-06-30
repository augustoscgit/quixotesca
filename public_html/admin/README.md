# Administracao da Plataforma

Esta pasta contem as telas administrativas compartilhadas da Plataforma RENAST.

## Documentacao visual e tema

As regras de tema, CSS, Bootstrap, navbar, botoes, formularios, tabelas e contraste da Administracao ficam centralizadas em:

- `../../docs/identidade-visual-ux.md`
- `../../docs/tema-css-bootstrap-modulos.md`
- `../../acesso/README.md`

As telas administrativas usam o bootstrap comum do modulo Acesso (`acesso/src/bootstrap.php`) e devem renderizar com `data-module="admin"`.

## Regras locais

- Nao carregar CSS de CAT, CAREX, Fichario ou LDRT.
- Nao criar paleta administrativa paralela.
- Usar `public_html/admin/assets/app.css` apenas para layout especifico da area administrativa.
- Usar `.btn-primary`, `.btn-outline-primary`, tabelas Bootstrap e componentes herdados de `public_html/assets/css/style.css`.
- Evitar `btn-light`, `btn-outline-light`, fundos escuros fixos, cores inline e badges customizadas fora do Bootstrap-first.
