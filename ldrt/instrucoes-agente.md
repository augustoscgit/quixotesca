# Instruções para o Agente Especializado - Módulo LDRT

Este documento orienta o desenvolvimento e manutenção do módulo **LDRT** (Lista de Doenças Relacionadas ao Trabalho - Portaria GM/MS 1.999/2023) de forma integrada e harmonizada com a plataforma principal.

---

## 1. Escopo do Módulo
O LDRT consiste em uma aplicação de consulta de doenças ocupacionais e agentes de risco para profissionais de saúde e como fonte de RAG para Agentes de IA. O agente deste módulo deve focar no desenvolvimento das seguintes frentes:
1. **Consulta Cruzada**: Busca inteligente de doenças (Lista B por CID-10) e agentes de risco (Lista A).
2. **Exploração de Dados**: Telas para explorar termos por CID e CNAE/CBO.
3. **API e Integração RAG**: Fornecer endpoints (`api_rag.php`, `rag.php`) otimizados para busca semântica e autocompletes rápidos.

> [!IMPORTANT]
> **Limite de Escopo**: O repositório da Plataforma gerencia apenas a página inicial de apresentação do módulo (`index.php`), a página pública correspondente (`public/index.php`) e suas diretrizes estéticas básicas. Toda a lógica de busca semântica, integração RAG de IA, gerenciamento de banco de dados PostgreSQL, exploração de tabelas CID/CNAE/CBO, rotas e APIs são de responsabilidade e escopo exclusivo do desenvolvimento deste módulo (LDRT).


---

## 2. Padroes de Estilo e Identidade Visual (Obrigatorio)

O guia oficial de estilo, UX, interface, tema, navbar, botoes, tabelas e filtros da plataforma fica em:

`../docs/identidade-visual-ux.md`, `../docs/tema-css-bootstrap-modulos.md` e `../docs/desenvolvimento-seguranca.md`

Arquivos historicos de definicao visual local, quando reaparecerem em migrações antigas, devem ser tratados apenas como legado. Regras visuais antigas deste modulo nao devem orientar novas telas quando conflitarem com o guia central.

---

## 3. Logotipo e Link de Retorno
Para garantir uma experiência de navegação integrada e fluida:
- O logotipo horizontal oficial (`assets/logo-fundo-escuro-horizontal.png`) foi adicionado na barra de navegação no lugar/lado do ícone de texto, envolto por um link de retorno.
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

## 4. Banco de Dados e Conexão
- O banco de dados utilizado é **PostgreSQL** (verificar arquivo `src/db.php` e função `getDBConnection()`).
- O schema PostgreSQL padrão do módulo é `ldrt`.
- Variáveis de ambiente são lidas de `secrets/.env`.
- Falhas de conexão com a base de dados na landing page principal devem ser tratadas silenciosamente via `try-catch`, exibindo o status de conexão "Desconectado" e os contadores zerados para que a página inicial continue respondendo ao usuário.



