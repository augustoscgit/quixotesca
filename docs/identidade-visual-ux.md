# Diretrizes de Identidade Visual e UX - Plataforma RENAST

Este documento centraliza as especificações de design, interface do usuário (UI), tokens visuais, tipografia e diretrizes de experiência do usuário (UX) da Plataforma RENAST.

---

## 1. Princípios de Interface e Temas

- **Framework de Design**: A plataforma é estruturada sobre o **Bootstrap 5.3** com suporte nativo a temas dinâmicos via `[data-bs-theme="light"]` e `[data-bs-theme="dark"]`.
- **Tema Automático**: O tema padrão de inicialização é `auto` (detecta e segue a preferência do sistema operacional do usuário).
- **Sem Excesso Decorativo**: Evite glows, cores de fundo saturadas em opacidade alta, sombras coloridas artificiais ou elementos inspirados em *claymorphism* para novos componentes. O padrão é visual sóbrio, fundos planos baseados nos tokens do Bootstrap e bordas sutis.

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

### Cor de Acento por Módulo (`--accent`)
Cada módulo define a sua cor de acento (aplicada em ícones de menu lateral, pequenos frisos superiores em cards ou bullets):
- **Portal / Acesso**: Azul institucional (`#006392`)
- **CAT**: Cinza escuro (`#464B51`)
- **CAREX**: Vermelho institucional (`#BC0300`)
- **Fichário Acadêmico**: Verde institucional (`#225500`)
- **LDRT**: Laranja institucional (`#944F00`)

---

## 4. Componentes Compartilhados (Cards, Chips e Botões)

- **Cards**: O padrão visual é `.system-card` ou `.panel-card`. Use borda neutra sutil com raio (`border-radius`) de `8px`. Sombras devem ser neutras e discretas (`rgba(0,0,0,.08)`).
- **Chips e Badges**: Fundo neutro do tema (`--bs-tertiary-bg`), cantos totalmente arredondados (`border-radius: 50rem`), texto com peso `500` em caixa baixa (sem `text-transform: uppercase`).
- **Botões**: Use botões Bootstrap (`.btn-primary`, `.btn-outline-primary`). Ações e botões de comando em formulários ou cards devem ser alinhados à direita do bloco a que pertencem.
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
