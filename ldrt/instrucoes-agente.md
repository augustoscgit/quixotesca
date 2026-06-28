# InstruÃ§Ãµes para o Agente Especializado - MÃ³dulo LDRT

Este documento orienta o desenvolvimento e manutenÃ§Ã£o do mÃ³dulo **LDRT** (Lista de DoenÃ§as Relacionadas ao Trabalho - Portaria GM/MS 1.999/2023) de forma integrada e harmonizada com a plataforma principal.

---

## 1. Escopo do MÃ³dulo
O LDRT consiste em uma aplicaÃ§Ã£o de consulta de doenÃ§as ocupacionais e agentes de risco para profissionais de saÃºde e como fonte de RAG para Agentes de IA. O agente deste mÃ³dulo deve focar no desenvolvimento das seguintes frentes:
1. **Consulta Cruzada**: Busca inteligente de doenÃ§as (Lista B por CID-10) e agentes de risco (Lista A).
2. **ExploraÃ§Ã£o de Dados**: Telas para explorar termos por CID e CNAE/CBO.
3. **API e IntegraÃ§Ã£o RAG**: Fornecer endpoints (`api_rag.php`, `rag.php`) otimizados para busca semÃ¢ntica e autocompletes rÃ¡pidos.

> [!IMPORTANT]
> **Limite de Escopo**: O repositÃ³rio da Plataforma gerencia apenas a pÃ¡gina inicial de apresentaÃ§Ã£o do mÃ³dulo (`index.php`), a pÃ¡gina pÃºblica correspondente (`public/index.php`) e suas diretrizes estÃ©ticas bÃ¡sicas. Toda a lÃ³gica de busca semÃ¢ntica, integraÃ§Ã£o RAG de IA, gerenciamento de banco de dados PostgreSQL, exploraÃ§Ã£o de tabelas CID/CNAE/CBO, rotas e APIs sÃ£o de responsabilidade e escopo exclusivo do desenvolvimento deste mÃ³dulo (LDRT).


---

## 2. Padroes de Estilo e Identidade Visual (Obrigatorio)

O guia oficial de estilo, UX, interface, tema, navbar, botoes, tabelas e filtros da plataforma fica em:

`docs/definicao-padroes.md`

O arquivo local `assets/definicao-padroes.md` existe apenas como ponte de compatibilidade. Regras visuais antigas deste modulo sao historicas e nao devem orientar novas telas quando conflitarem com o guia central.

---

## 3. Logotipo e Link de Retorno
Para garantir uma experiÃªncia de navegaÃ§Ã£o integrada e fluida:
- O logotipo horizontal oficial (`assets/logo-fundo-escuro-horizontal.png`) foi adicionado na barra de navegaÃ§Ã£o no lugar/lado do Ã­cone de texto, envolto por um link de retorno.
- **index.php na Raiz**: O link de retorno aponta para `../` (plataforma principal):
  ```html
  <a class="navbar-brand d-flex align-items-center gap-3" href="../">
      <img src="assets/logo-fundo-escuro-horizontal.png" alt="Plataforma Renast Online" style="height: 32px; width: auto;">
      <span class="text-white-50">|</span>
      <span style="font-weight: 700; letter-spacing: 0.5px; font-size: 1.1rem;">LDRT <span class="text-muted" style="font-weight: 300; font-size: 0.85rem;">| Portaria 1.999/2023</span></span>
  </a>
  ```
- **public/index.php**: Como este arquivo fica dentro da pasta `/public/`, o link de retorno deve apontar para `../../` e o logo para `../assets/logo-fundo-escuro-horizontal.png`:
  ```html
  <a class="navbar-brand d-flex align-items-center gap-3" href="../../">
      <img src="../assets/logo-fundo-escuro-horizontal.png" alt="Plataforma Renast Online" style="height: 32px; width: auto;">
      <span class="text-white-50">|</span>
      <span style="font-weight: 700; letter-spacing: 0.5px; font-size: 1.1rem;">LDRT <span class="text-muted" style="font-weight: 300; font-size: 0.85rem;">| Portaria 1.999/2023</span></span>
  </a>
  ```

---

## 4. Banco de Dados e ConexÃ£o
- O banco de dados utilizado Ã© **PostgreSQL** (verificar arquivo `src/db.php` e funÃ§Ã£o `getDBConnection()`).
- O schema PostgreSQL padrÃ£o do mÃ³dulo Ã© `ldrt`.
- VariÃ¡veis de ambiente sÃ£o lidas de `secrets/.env`.
- Falhas de conexÃ£o com a base de dados na landing page principal devem ser tratadas silenciosamente via `try-catch`, exibindo o status de conexÃ£o "Desconectado" e os contadores zerados para que a pÃ¡gina inicial continue respondendo ao usuÃ¡rio.



