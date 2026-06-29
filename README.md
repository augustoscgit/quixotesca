# Plataforma Renast Online - Portal de Sistemas

Este repositório é a central da **Plataforma Renast Online**, servindo como o portal unificado de entrada e o hub de diretrizes visuais para os sistemas integrados.

---

## 📌 Limite de Escopo do Projeto

Este projeto tem o seu escopo estritamente delimitado para garantir a independência de desenvolvimento dos módulos. 

### ✅ O que está no escopo deste projeto:
1. **Landing Page Principal**: O portal de entrada unificado ([index.html](index.html)) que apresenta e fornece acesso aos sistemas.
2. **Landing Pages dos Módulos**: As páginas de boas-vindas/índice iniciais de cada módulo ([carex/index.php](carex/index.php), [fichario/index.php](fichario/index.php), [ldrt/index.php](ldrt/index.php), [ldrt/public/index.php](ldrt/public/index.php)).
3. **Identidade Visual e Ativos Globais**: Logotipos, folha de estilo global ([assets/css/style.css](assets/css/style.css)), favicons e o guias de [identidade visual e UX](docs/identidade-visual-ux.md) e [desenvolvimento e segurança](docs/desenvolvimento-seguranca.md).
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

A plataforma é dividida entre uma área pública (Document Root do servidor) e pastas lógicas e de configuração privadas (fora do acesso HTTP):

### 🌐 Área Pública (`public_html/`)
Contém apenas os pontos de entrada acessíveis via navegador e ativos estáticos:
- **`public_html/index.php` / `index.html`**: Portal de entrada principal da plataforma.
- **`public_html/assets/`**: Estilos CSS globais da landing page, Javascripts de tema e logotipos oficiais.
- **`public_html/acesso/`**: Telas de login, cadastro, controle de usuários e papéis do módulo Acesso.
- **`public_html/carex/`**: Telas do módulo Carex-BR (matrizes, categoria, administrativo, desenvolvimento).
- **`public_html/fichario/`**: Telas do módulo Fichário Acadêmico (artigos, projetos, tags, timeline).
- **`public_html/ldrt/`**: Telas de consulta e buscas cruzadas do módulo LDRT.
- **`public_html/cat/`**: Telas de acompanhamento, inspeção e CNAE/CBO do módulo CAT.
- **`public_html/investigacao/`**: Tela de investigação de óbito por causas externas.
- **`public_html/renastonline/`**: Pasta de integração do sistema central.

### 🔒 Área Privada (Fora da `public_html/`)
Lógica de negócios e informações sensíveis blindadas de acesso HTTP direto:
- **`acesso/`**: Lógica do módulo Acesso (`src/`), sessões ativas (`private/sessions/`) e configurações locais.
- **`carex/`**: Código do CAREX (`src/`), documentação e configurações locais (`config/settings.json`).
- **`fichario/`**: Código do Fichário (`src/`), banco de dados SQLite (`data/`) e bootstrap de inicialização.
- **`ldrt/`**: Lógica do LDRT (`src/`), scripts de carga e banco de dados SQLite/PostgreSQL.
- **`cat/`**: Código do CAT (`src/`), dicionários locais de CNAE/CBO/territórios e banco de dados.
- **`investigacao/`**: Arquivos brutos de dicionários médicos e regionais.
- **`includes/`**: Arquivos PHP globais compartilhados entre os cabeçalhos e barras de navegação da plataforma.
- **`secrets/`**: Arquivos `.env` contendo as credenciais de acesso real aos bancos de dados de produção.

---

## 🛠️ Diretrizes de Integração de Módulos

Ao integrar ou atualizar um módulo, certifique-se de:
1. **Seguir o Guia de Design**: Manter o padrão visual unificado com temas claro, escuro e auto, conforme os guias de [identidade visual e UX](docs/identidade-visual-ux.md) e [desenvolvimento e segurança](docs/desenvolvimento-seguranca.md).
2. **Link de Retorno**: Garantir que o logotipo do cabeçalho da landing page do módulo aponte de volta para o portal (`../` ou `../../`).
3. **Resiliência a Falhas**: Páginas de entrada do módulo devem tratar falhas de banco de dados silenciosamente para evitar que a indisponibilidade de banco derrube a página de apresentação.


## Documentação visual e UX

A organização da documentação de aparência, UX, design e interface está centralizada em:

- `assets/css/documentacao-visual-centralizada.md`
- `docs/desenvolvimento-seguranca.md`
- `docs/identidade-visual-ux.md`

Documentos de módulo devem conter apenas regras específicas do módulo e apontar para o guia central quando tratarem de visual, UX, tema, navbar, botões, tabelas ou formulários.
