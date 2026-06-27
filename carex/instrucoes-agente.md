# InstruÃ§Ãµes para o Agente Especializado - MÃ³dulo Carex-BR

Este documento orienta o desenvolvimento e manutenÃ§Ã£o do mÃ³dulo **Carex-BR** (Matrizes de ExposiÃ§Ã£o Ocupacional a Agentes CarcinogÃªnicos no Brasil) de forma integrada e harmonizada com a plataforma principal.

---

## 1. Escopo do MÃ³dulo
O Carex-BR consiste em um sistema de consulta de matrizes de risco carcinogÃªnico com base em CNAE e CBO. O agente deste mÃ³dulo deve focar no desenvolvimento das seguintes frentes:
1. **Consulta de Matrizes**: VisualizaÃ§Ã£o e filtragem da relaÃ§Ã£o entre ocupaÃ§Ãµes (CBO), setores econÃ´micos (CNAE) e agentes quÃ­micos/fÃ­sicos/biolÃ³gicos associados a cÃ¢ncer.
2. **Cadastro e GestÃ£o**: Ãrea administrativa para gerenciamento das tabelas de matrizes e classificaÃ§Ãµes de risco.
3. **Markdown EditÃ¡vel**: Manter o recurso de ediÃ§Ã£o dinÃ¢mica das seÃ§Ãµes `landing.md` e `sobre.md` via `editor.php`.

> [!IMPORTANT]
> **Limite de Escopo**: O repositÃ³rio da Plataforma gerencia apenas a pÃ¡gina inicial de apresentaÃ§Ã£o do mÃ³dulo (`index.php`) e suas diretrizes estÃ©ticas bÃ¡sicas. Toda a lÃ³gica de processamento de matrizes, banco de dados, Ã¡rea administrativa interna, autenticaÃ§Ã£o, rotas e APIs sÃ£o de responsabilidade e escopo exclusivo do desenvolvimento deste mÃ³dulo (Carex-BR).


---

## 2. Padroes de Estilo e Identidade Visual (Obrigatorio)

O guia oficial de estilo, UX, interface, tema, navbar, botoes, tabelas e filtros da plataforma fica em:

`../assets/css/definicao-padroes.md`

As regras especificas de identidade do CAREX ficam em `docs/identidade-visual.md`, apenas para ativos, nome do modulo e compatibilidade local. Regras visuais antigas deste modulo sao historicas e nao devem orientar novas telas quando conflitarem com o guia central.

---

## 3. Logotipo e Link de Retorno
Para garantir uma experiÃªncia de navegaÃ§Ã£o integrada e fluida:
- O logotipo exibido no cabeÃ§alho/banner principal (`assets/logo-fundo-escuro-horizontal.png`) **deve** estar envolvido por um link apontando de volta para a landing page da plataforma principal:
  ```html
  <a href="../"><img src="assets/logo-fundo-escuro-horizontal.png" alt="Logo da plataforma" class="cx-hero-logo"></a>
  ```
- **Nota**: Manter o caminho de retorno relativo `../` (ou `../index.html`), garantindo que o redirecionamento funcione tanto no ambiente de homologaÃ§Ã£o quanto em produÃ§Ã£o, de forma isolada de portas ou domÃ­nios locais.

---

## 4. Banco de Dados e ConexÃ£o
- O banco de dados utilizado Ã© **PostgreSQL** ou **MySQL** (verificar arquivo `src/bootstrap.php`).
- A inicializaÃ§Ã£o da conexÃ£o e o carregamento das variÃ¡veis de ambiente devem ser feitas atravÃ©s do arquivo `src/bootstrap.php`.
- Em caso de falha de conexÃ£o com a base de dados, a landing page principal (`index.php`) deve tratar o erro silenciosamente via bloco `try-catch`, exibindo os valores estatÃ­sticos padrÃ£o (Matrizes, Categorias, Analisadas) para nÃ£o interromper a navegaÃ§Ã£o do usuÃ¡rio.



