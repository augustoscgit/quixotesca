# Diretrizes de Identidade Visual e UX - Plataforma RENAST

Este documento centraliza as especificações de design, interface do usuário (UI), tokens visuais, tipografia e diretrizes de experiência do usuário (UX) da Plataforma RENAST.

### Checklist obrigatorio de conformidade visual
Toda tela nova ou modificada deve passar por esta lista antes de ser considerada pronta:

1. Carregar o CSS global (`public_html/assets/css/style.css`) depois de qualquer CSS local do modulo.
2. Declarar `data-module` no elemento `<html>` com um dos valores oficiais: `portal`, `cat`, `carex`, `fichario` ou `ldrt`.
3. Usar `--bs-primary`, `--accent-ui`, `--accent-solid`, `--accent-on-solid`, `--bs-body-bg`, `--bs-tertiary-bg`, `--bs-border-color` e tokens `--brand-{cor}-{degrau}`; nao criar roxo, azul, verde, vermelho ou laranja avulsos.
4. Usar `.app-navbar`, `.landing-page`, `.system-card`, `.panel-card`, `.glass-card`, `.btn-primary`, `.btn-outline-primary`, `.text-accent` e `.bg-accent-subtle` em vez de classes visuais paralelas.
5. Manter cards e paineis com raio maximo de `8px`, borda neutra e sombra nula ou muito discreta.
6. Remover elementos decorativos soltos como blobs, orbs, glows, gradientes radiais e fundos de pagina saturados.
7. Nao usar `backdrop-filter`, blur visual, sombra colorida, gradiente decorativo ou cores em `rgba(...)` fora dos tokens, exceto em casos tecnicos justificados.
8. Barras de progresso devem ser solidas e padronizadas: trilho neutro do tema, preenchimento por `--accent-solid`, texto por `--accent-on-solid`, sem `progress-bar-striped`, sem `progress-bar-animated` e sem classes `bg-*` para progresso comum.

### Auditoria de legado
Ao tocar uma tela antiga, procure e substitua estes sinais de despadronizacao:

- `backdrop-filter: blur(...)` ou `-webkit-backdrop-filter: blur(...)`;
- classes `blob-*`, `orb-*`, `glow-*`, `glass-*` com decoracao propria;
- cores locais como `#4f46e5`, `#6366f1`, `#7c3aed`, `#9333ea` e roxos derivados;
- `border-radius` acima de `8px` em cards, paineis, navcards e superficies;
- `box-shadow` colorido ou com grande deslocamento;
- botoes customizados que duplicam `.btn-primary`, `.btn-outline-primary` ou `.btn-accent`;
- navbars locais que nao usam o padrao compartilhado.

---

## 1. Princípios de Interface e Temas

- **Framework de Design**: A plataforma é estruturada sobre o **Bootstrap 5.3** com suporte nativo a temas dinâmicos via `[data-bs-theme="light"]` e `[data-bs-theme="dark"]`.
- **Tema Automático**: O tema padrão de inicialização é `auto` (detecta e segue a preferência do sistema operacional do usuário).
- **Sem Excesso Decorativo**: Evite glows, cores de fundo saturadas em opacidade alta, sombras coloridas artificiais ou elementos inspirados em *claymorphism* para novos componentes. O padrão é visual sóbrio, fundos planos baseados nos tokens do Bootstrap e bordas sutis.

---

### Regra de contraste e identidade por modulo
A identidade visual do modulo nao deve ser aplicada diretamente como cor de texto, fundo de botao ou hover. Cada modulo deve separar estes papeis:

- `--module-brand`: cor de identidade/logotipo/decoracao pontual. Pode ter contraste insuficiente e nao deve ser usada para texto.
- `--accent-ui`: cor acessivel para texto de acento, icones, bordas ativas e links no tema atual.
- `--accent-solid`: fundo acessivel para botoes, estados selecionados e preenchimentos fortes.
- `--accent-on-solid`: cor do texto sobre `--accent-solid`.
- `--accent-surface`: superficie sutil para badges/chips; preferir neutro quando a escala oficial nao tiver tint seguro.
- `--accent-surface-text`: texto sobre `--accent-surface`.
- `--accent`: alias legado de `--accent-ui`; nao usar em novos componentes.

No tema claro, use degraus escuros (4-6) para texto/acento. No tema escuro, use degraus claros (0-2) para texto/acento e sempre defina `--accent-on-solid` explicitamente. Texto normal deve manter `--bs-body-color` e titulos devem manter `--bs-heading-color`; a cor do modulo deve aparecer como acento, nao como cor dominante de leitura.

---

## 2. Tipografia

- **Família de Fontes Principal**: `Plus Jakarta Sans`, importada na folha de estilo central.
- **Títulos (`h1` a `h6`)**: Devem usar `Plus Jakarta Sans`.
- **Exceções de Legado e Módulo**:
  - O módulo **Fichário Acadêmico** pode utilizar a fonte `Outfit` no corpo dos cards de leitura e fichamento.
  - Os módulos **CAREX** e **LDRT** mantêm a fonte `Inter` em tabelas e grades operacionais densas para preservar legibilidade de dados.

---

## 3. Paleta de Cores e Tokens de Tema

### Claro (`[data-bs-theme="light"]`)
- Fundo do corpo (`--bs-body-bg`): `#ffffff`
- Fundo da página/cards (`--bs-tertiary-bg`): `#f8f9fa`
- Títulos (`--bs-heading-color`): `#212529`
- Textos (`--bs-body-color`): `#212529`
- Textos Secundários (`--bs-secondary-color`): `#6c757d`
- Cor Primária/Ações (`--bs-primary`): `#006392`
- Bordas (`--bs-border-color`): `#dee2e6`

### Escuro (`[data-bs-theme="dark"]`)
- Fundo do corpo (`--bs-body-bg`): `#1F2937`
- Fundo de painéis/cards (`--bs-tertiary-bg`): `#273444`
- Títulos (`--bs-heading-color`): `#f8f9fa`
- Textos (`--bs-body-color`): `#dee2e6`
- Textos Secundários (`--bs-secondary-color`): `#adb5bd`
- Cor Primária/Ações (`--bs-primary`): `#006392`
- Bordas (`--bs-border-color`): `#495057`

### Escala de Apoio Oficial (Figma)
Para criação de gráficos, badges coloridos, ou tabelas comparativas, use os degraus oficiais derivados da marca (o degrau 4 é a recomendação para destaque e texto ativo; os degraus 0-1 são para fundos de badge):

| Degrau | Azul | Vermelho | Verde | Laranja |
|---|---|---|---|---|
| 0 (Muito Claro) | `#E7F2FF` | `#FFECEC` | `#73FC00` | `#FFEDE6` |
| 1 (Claro) | `#A4D2FF` | `#FFBBBA` | `#5DCF00` | `#FFC4A8` |
| 2 (Logo pura) | `#00B0FF` | `#FF7D7D` | `#48A400` | `#FF8C00` |
| 3 (Destaque) | `#0089C7` | `#FF0600` | `#347B00` | `#C86D00` |
| 4 (UI / Padrão) | `#006392` | `#BC0300` | `#225500` | `#944F00` |
| 5 (Forte) | `#004061` | `#7D0100` | `#103100` | `#633300` |
| 6 (Contraste) | `#002033` | `#430000` | `#041500` | `#361900` |

### Matriz acessivel de acento por modulo
Cada modulo define uma identidade (`--module-brand`) e uma cor acessivel para UI (`--accent-ui`). O alias `--accent` existe apenas para compatibilidade com legado e deve apontar para `--accent-ui`.

| Modulo | `--module-brand` | `--accent-ui` claro | `--accent-ui` escuro | texto sobre `--accent-solid` no escuro |
|---|---|---|---|---|
| Portal / Acesso | `#00B0FF` | `#006392` | `#A4D2FF` | `#212529` |
| CAREX | `#FF0600` | `#BC0300` | `#FFBBBA` | `#212529` |
| Fichario | `#5DCF00` | `#225500` | `#5DCF00` | `#041500` |
| LDRT | `#FF8C00` | `#944F00` | `#FFC4A8` | `#212529` |
| CAT | `#464B51` | `#464B51` | `#E5E7EB` | `#212529` |

No Fichario, a cor principal preenchida do modulo deve usar o verde puro da logo (`#5DCF00`) com texto escuro (`#041500`). Para texto de leitura, links e acentos finos em tema claro, manter o verde escuro acessivel (`#225500`).

### Tags do Fichario
Tipos de tag devem usar a mesma paleta em badges, filtros, nuvem de palavras e grafo de relacionamento:

- `Tema`: azul institucional (`--tag-tema-*`).
- `Metodo`: laranja institucional (`--tag-metodo-*`).
- `Fonte`: verde da logo do Fichario (`--tag-fonte-*`, sólido `#5DCF00`).
- Sem agrupamento/outros: neutro (`--tag-neutro-*`).

---

## 4. Componentes Compartilhados (Cards, Chips e Botões)

- **Cards**: O padrão visual é `.system-card` ou `.panel-card`. Use borda neutra sutil com raio (`border-radius`) de `8px`. Sombras devem ser neutras e discretas (`rgba(0,0,0,.08)`).
- **Chips e Badges**: Fundo sutil via `--accent-surface` ou neutro do tema (`--bs-tertiary-bg`), texto via `--accent-surface-text`, cantos totalmente arredondados (`border-radius: 50rem`) e peso `500`.
- **Botões**: Use botões Bootstrap (`.btn-primary`, `.btn-outline-primary`) ou `.btn-accent`; fundos preenchidos devem usar `--accent-solid` com texto `--accent-on-solid`. Ações e botões de comando em formulários ou cards devem ser alinhados à direita do bloco a que pertencem.
- **Barras de Progresso**: Use `.progress` e `.progress-bar` do Bootstrap sem listras, animacao ou gradiente. O trilho deve usar `--bs-tertiary-bg` com borda `--bs-border-color`; o preenchimento deve usar `--accent-solid`; texto interno deve usar `--accent-on-solid`. Classes semanticas como `bg-success`, `bg-primary` ou `bg-warning` nao devem ser usadas para progresso comum, pois conflitam com a identidade acessivel do modulo.
- **Menus de Ação Contextuais**: Em cabeçalhos de visualização de registros, use grupos de botões compactos com ícones (icon-only), providos de `title` e `aria-label`, para ações como voltar, imprimir, exportar ou abrir JSON.

---

## 5. Diretrizes de UX e Preservação do Fluxo

- **Preservação da Leitura**: Evite disparar recarregamento completo da página (`location.reload()`) em ações secundárias (como deletar uma tag, adicionar uma anotação ou favoritar um item). Sempre execute atualizações de dados via requisições assíncronas (AJAX) e atualize os componentes no DOM localmente. Isso evita que o scroll da página seja reiniciado, preservando a posição de leitura do usuário.
- **Status de Carregamento**: Submits demorados devem dar feedback imediato desabilitando o botão correspondente e mostrando estados de espera que evitem duplo clique acidental.
- **Formulários e Inputs**: Campos inativos devem usar as cores do tema com opacidade adequada. Mensagens de ajuda de inputs devem herdar a classe `.form-text` do Bootstrap.

---

## 6. Padrões de Agregadores e Vocabulários Hierárquicos

Módulos que gerenciam dados agregados (como territórios, setores CNAE, cargos CBO ou códigos de doenças CID) devem adotar um layout estruturado de navegação:

### As 4 Perguntas de UX do Agregador
Toda tela que sumariza ou agrupa dados de eventos (como acidentes de trabalho ou artigos cadastrados) deve responder de forma rápida:
1. *Onde estou na hierarquia?* (Exibir caminho visual ou breadcrumbs completos).
2. *Quantos eventos existem neste nível?* (Exibir métricas sumarizadas e contadores de forma proeminente).
3. *Quais relacionados ou filhos posso abrir?* (Exibir links de navegação para subníveis ou relacionados).
4. *Quais registros individuais compõem este número?* (Permitir clicar nos números de eventos para listar as entidades correspondentes que geraram a agregação).

### Normalização e Visualização
- Códigos de chaves de agregação (como CNPJ, CBO, CNAE) devem ser mantidos sem pontuação nos bancos, com zeros à esquerda preservados.
- Na listagem de dados na interface, o rótulo descritivo (nome do município, descrição da atividade econômica) deve vir primeiro. Códigos numéricos brutos devem ser mostrados de forma discreta em tooltips, badges pequenas ou em colunas secundárias.
