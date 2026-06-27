# Plataforma Renast Online - Portal de Sistemas

Este repositório é a central da **Plataforma Renast Online**, servindo como o portal unificado de entrada e o hub de diretrizes visuais para os sistemas integrados.

---

## 📌 Limite de Escopo do Projeto

Este projeto tem o seu escopo estritamente delimitado para garantir a independência de desenvolvimento dos módulos. 

### ✅ O que está no escopo deste projeto:
1. **Landing Page Principal**: O portal de entrada unificado ([index.html](index.html)) que apresenta e fornece acesso aos sistemas.
2. **Landing Pages dos Módulos**: As páginas de boas-vindas/índice iniciais de cada módulo ([carex/index.php](carex/index.php), [fichario/index.php](fichario/index.php), [ldrt/index.php](ldrt/index.php), [ldrt/public/index.php](ldrt/public/index.php)).
3. **Identidade Visual e Ativos Globais**: Logotipos, folha de estilo global ([assets/css/style.css](assets/css/style.css)), favicons e o guia de padrões de design ([assets/css/definicao-padroes.md](assets/css/definicao-padroes.md)).
4. **Orientações para Agentes**: Manuais de diretrizes técnicas e visuais (`instrucoes-agente.md`) hospedados na pasta de cada módulo para guiar os respectivos desenvolvedores/agentes especialistas.

### ❌ O que NÃO está no escopo deste projeto:
1. **Lógica de Negócios dos Módulos**: As funcionalidades internas, telas de cadastro, ferramentas administrativas específicas, lógica de parsing e scripts de backend.
2. **Bancos de Dados**: Estruturação de schemas, migrações de dados, e manutenção de queries específicas dos bancos de dados (como PostgreSQL do LDRT, SQLite do Fichário Acadêmico, ou banco do Carex-BR).
3. **APIs e Endpoints**: Scripts de integração e rotas internas (ex: endpoints RAG, feeds RSS, etc.).
4. **Desenvolvimento do RENAST Online**: O módulo `renastonline` é externo e gerenciado separadamente; este projeto apenas direciona o link de acesso ao seu endpoint de login.

> [!IMPORTANT]
> Qualquer alteração nas regras de negócio, banco de dados ou funcionalidades internas dos sistemas **deve ser delegada e executada exclusivamente pelos agentes especializados** responsáveis por cada módulo. Este repositório deve manter-se focado estritamente na harmonia visual e integridade dos pontos de entrada.

---

## 📂 Estrutura de Pastas e Componentes

A estrutura de diretórios e a divisão de responsabilidades estão organizadas da seguinte forma:

- **[/assets](assets)**: Logotipos oficiais, estilos CSS globais da landing page e o [guia de padrões visuais](assets/css/definicao-padroes.md).
- **[/carex](carex)**: Módulo Carex-BR.
  - Landing page de entrada: [carex/index.php](carex/index.php).
  - Manual de desenvolvimento: [carex/instrucoes-agente.md](carex/instrucoes-agente.md).
- **[/fichario](fichario)**: Módulo Fichário Acadêmico.
  - Landing page de entrada: [fichario/index.php](fichario/index.php).
  - Manual de desenvolvimento: [fichario/instrucoes-agente.md](fichario/instrucoes-agente.md).
- **[/ldrt](ldrt)**: Módulo LDRT.
  - Landing page de entrada: [ldrt/index.php](ldrt/index.php) (e [ldrt/public/index.php](ldrt/public/index.php)).
  - Manual de desenvolvimento: [ldrt/instrucoes-agente.md](ldrt/instrucoes-agente.md).
- **[/renastonline](renastonline)**: Pasta reservada para a integração do sistema central.

---

## 🛠️ Diretrizes de Integração de Módulos

Ao integrar ou atualizar um módulo, certifique-se de:
1. **Seguir o Guia de Design**: Manter o padrão visual unificado com temas claro, escuro e auto, conforme [definicao-padroes.md](assets/css/definicao-padroes.md).
2. **Link de Retorno**: Garantir que o logotipo do cabeçalho da landing page do módulo aponte de volta para o portal (`../` ou `../../`).
3. **Resiliência a Falhas**: Páginas de entrada do módulo devem tratar falhas de banco de dados silenciosamente para evitar que a indisponibilidade de banco derrube a página de apresentação.


## Documentação visual e UX

A organização da documentação de aparência, UX, design e interface está centralizada em:

- `assets/css/documentacao-visual-centralizada.md`
- `assets/css/definicao-padroes.md`

Documentos de módulo devem conter apenas regras específicas do módulo e apontar para o guia central quando tratarem de visual, UX, tema, navbar, botões, tabelas ou formulários.
