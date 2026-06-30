# Instruções para o Agente Especializado - Módulo Carex-BR

Este documento orienta o desenvolvimento e manutenção do módulo **Carex-BR** (Matrizes de Exposição Ocupacional a Agentes Carcinogênicos no Brasil) de forma integrada e harmonizada com a plataforma principal.

---

## 1. Escopo do Módulo
O Carex-BR consiste em um sistema de consulta de matrizes de risco carcinogênico com base em CNAE e CBO. O agente deste módulo deve focar no desenvolvimento das seguintes frentes:
1. **Consulta de Matrizes**: Visualização e filtragem da relação entre ocupações (CBO), setores econômicos (CNAE) e agentes químicos/físicos/biológicos associados a câncer.
2. **Cadastro e Gestão**: Área administrativa para gerenciamento das tabelas de matrizes e classificações de risco.
3. **Markdown Editável**: Manter o recurso de edição dinâmica das seções `landing.md` e `sobre.md` via `editor.php`.

> [!IMPORTANT]
> **Limite de Escopo**: O repositório da Plataforma gerencia apenas a página inicial de apresentação do módulo (`index.php`) e suas diretrizes estéticas básicas. Toda a lógica de processamento de matrizes, banco de dados, área administrativa interna, autenticação, rotas e APIs são de responsabilidade e escopo exclusivo do desenvolvimento deste módulo (Carex-BR).


---

## 2. Padroes de Estilo e Identidade Visual (Obrigatorio)

O guia oficial de estilo, UX, interface, tema, navbar, botoes, tabelas e filtros da plataforma fica em:

`../docs/identidade-visual-ux.md`, `../docs/tema-css-bootstrap-modulos.md` e `../docs/desenvolvimento-seguranca.md`

As regras especificas de identidade do CAREX ficam em `docs/identidade-visual.md`, apenas para ativos, nome do modulo e compatibilidade local. Regras visuais antigas deste modulo sao historicas e nao devem orientar novas telas quando conflitarem com o guia central.

---

## 3. Logotipo e Link de Retorno
Para garantir uma experiência de navegação integrada e fluida:
- O logotipo exibido no cabeçalho/banner principal (`assets/logo-fundo-escuro-horizontal.png`) **deve** estar envolvido por um link apontando de volta para a landing page da plataforma principal:
  ```html
  <a href="../"><img src="assets/logo-fundo-escuro-horizontal.png" alt="Logo da plataforma" class="cx-hero-logo"></a>
  ```
- **Nota**: Manter o caminho de retorno relativo `../` (ou `../index.html`), garantindo que o redirecionamento funcione tanto no ambiente de homologação quanto em produção, de forma isolada de portas ou domínios locais.

---

## 4. Banco de Dados e Conexão
- O banco de dados utilizado é **PostgreSQL** ou **MySQL** (verificar arquivo `src/bootstrap.php`).
- A inicialização da conexão e o carregamento das variáveis de ambiente devem ser feitas através do arquivo `src/bootstrap.php`.
- Em caso de falha de conexão com a base de dados, a landing page principal (`index.php`) deve tratar o erro silenciosamente via bloco `try-catch`, exibindo os valores estatísticos padrão (Matrizes, Categorias, Analisadas) para não interromper a navegação do usuário.



