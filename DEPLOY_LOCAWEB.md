# Deploy na Locaweb

Checklist operacional para publicar a Plataforma RENAST Online por FTP em hospedagem compartilhada.

## Passo 1: Configuração do WinSCP para Upload Direto

Para publicar os arquivos diretamente para a pasta `public_html` (ou raiz do domínio) da Locaweb utilizando o **WinSCP**, siga estas etapas para garantir um processo rápido, limpo e seguro:

### A. Exibir Arquivos Ocultos (.htaccess e .env)
Arquivos que começam com ponto são ocultos por padrão no Windows e no WinSCP. Se você não os enviar, a plataforma não funcionará (retornará erro 500 ou vazamento de banco).
1. No WinSCP, use o atalho **Ctrl + Alt + H** para ativar a exibição de arquivos ocultos.
2. Certifique-se de que os arquivos `.htaccess` e `.env` estão visíveis tanto no painel esquerdo (local) quanto no direito (servidor).

### B. Configurar Máscara de Exclusão (Ignorar arquivos locais/desenvolvimento)
Para evitar o upload de arquivos desnecessários (como versionamento `.git`, testes locais e backups), configure um filtro no WinSCP:
1. Ao arrastar e soltar a pasta do projeto (ou clicar em **F5/Upload**), a caixa de diálogo "Enviar" (Upload) será exibida.
2. Clique no botão **Configurações de transferência...** (Transfer settings).
3. Na barra lateral esquerda da nova janela, clique em **Filtro** (Filters).
4. No campo **Excluir arquivos** (Exclude files), cole a seguinte máscara exata:
   ```text
   */.git/; */.gitattributes; */.gitignore; */.editorconfig; */.agents/; */.codex/; Plataforma Renast/; *.bak; *.tmp; *.log; fichario/tests/; fichario/mockup/; carex/scratch/; carex/chrome-debug-profile/; gerar_pacote_deploy.php; deploy_locaweb.zip; *.lock
   ```
5. Clique em **OK** para aplicar. O WinSCP agora ignorará todos os arquivos de desenvolvimento e transferirá apenas o código de produção de forma rápida e segura.

## Estrutura de Pastas e Segurança no FTP (Locaweb)

Para garantir segurança contra acessos diretos via HTTP, a plataforma é dividida entre arquivos **públicos** e **privados**:

1. **Arquivos Públicos (Document Root)**:
   Suba **apenas o conteúdo** da pasta `public_html/` (local) diretamente para a pasta pública do seu FTP (`public_html/` no servidor). O visitante acessará `https://www.renastonline.org/` e visualizará diretamente os arquivos desta pasta.
   
   A estrutura dentro da pasta pública será:
   ```text
   / (raiz publica do dominio)
     .htaccess
     index.html
     index.php
     favicon.ico
     favicon.png
     limpar_sessoes.php
     assets/
     acesso/
     carex/
     fichario/
     ldrt/
     cat/
     investigacao/
     renastonline/
   ```

2. **Arquivos Privados (Fora da área pública)**:
   Suba as pastas privadas do projeto (`acesso/`, `carex/`, `fichario/`, `ldrt/`, `cat/`, `investigacao/`, `includes/`, `secrets/`) para a **raiz da sua conta de hospedagem** (uma pasta acima de `public_html/`). 
   Desse modo, o servidor de hospedagem conseguirá rodar a lógica do backend (via PHP), mas nenhum usuário mal-intencionado poderá acessar os arquivos sensíveis de banco de dados ou credenciais do `.env` pela internet.

## PHP

O `.htaccess` da raiz força `AddHandler php80-script .php`, pois os módulos usam PHP 8.

Se o servidor retornar erro 500 logo ao abrir qualquer PHP, confira no painel da Locaweb se o handler correto para PHP 8.0 está disponível. Se a conta usar outro handler, ajuste a primeira linha do `.htaccess`.

## Arquivos ocultos

Clientes FTP frequentemente não enviam arquivos que começam com ponto.

Confirme no servidor:

- `.htaccess` existe na raiz.
- `secrets/.htaccess` existe na raiz.
- `secrets/.env` existe na raiz.
- `carex/secrets/.htaccess` existe.
- `carex/private/.htaccess` existe.
- `carex/private/sessions/` existe e esta gravavel pelo PHP.
- `fichario/private/.htaccess`, `fichario/secrets/.htaccess` e `fichario/data/.htaccess` existem.
- `ldrt/secrets/.htaccess` existe.
- Os arquivos reais `.env` existem nas pastas esperadas.

## Configuracao por modulo

### Portal (Raiz)

Arquivo esperado:

```text
secrets/.env
```

Use `.env.example` na raiz como base para criar o arquivo com as credenciais do banco de dados principal.

### CAREX

Arquivo esperado:

```text
carex/secrets/.env
```

Pasta esperada para sessoes PHP:

```text
carex/private/sessions/
```

Essa pasta deve existir no FTP e precisa permitir escrita pelo PHP. Se aparecer erro com `/var/lib/php80/session`, a aplicacao nao conseguiu usar uma pasta de sessao propria.

Variaveis principais:

```text
APP_ENV=production
APP_DEBUG=false
DB_HOST=...
DB_PORT=5432
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
DB_SCHEMA=carex
DB_SSLMODE=
DB_ALLOW_WRITES=false
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=https://www.renastonline.org/carex/public/auth-callback.php
```

No Google Cloud Console:

- Origem JavaScript autorizada: `https://www.renastonline.org`
- URI de redirecionamento: `https://www.renastonline.org/carex/public/auth-callback.php`

### Fichario

Arquivo esperado:

```text
fichario/secrets/.env
```

Use `fichario/.env.example` como base. O código atual usa PostgreSQL via PDO.

#### Lock de Migração
- A aplicação utiliza arquivos de trava para migrações de banco:
  - `fichario/secrets/migration.lock` (migração principal)
  - `fichario/secrets/project_migration.lock` (tabelas do módulo de projetos)
- Estes arquivos de trava são gerados automaticamente na primeira execução no servidor e evitam consultas DDL redundantes e travamentos de banco (erros 500) em acessos simultâneos.
- **Importante**: Não envie seus arquivos `.lock` locais para o servidor. Se precisar refazer as migrações no banco de produção, exclua os arquivos `.lock` via FTP para que rodem novamente.

#### Otimizações de Concorrência e Performance
- **Carregamento Seguro de Ambiente**: Variáveis do arquivo `.env` são mantidas de forma thread-safe (usando `$_ENV` e `$_SERVER` em vez de `putenv`/`getenv`) para evitar a corrupção de credenciais de conexão ao banco de dados sob acessos paralelos simultâneos no servidor.
- **Liberação Precoce de Sessão**: Páginas puras de consulta (como requisições `GET` em `articles.php` e `tags.php`) chamam `session_write_close()` logo após ler os dados da sessão, liberando o travamento de arquivo imediatamente e permitindo o processamento assíncrono e simultâneo de requisições paralelas sem serialização.
- **Otimização de Consultas N+1**: A listagem de artigos em `articles.php` realiza busca em lote (batch-fetching) das tags de todos os artigos da página de uma só vez, reduzindo drasticamente o número de viagens de ida e volta (roundtrips) ao banco PostgreSQL remoto, economizando conexões e otimizando o tempo de resposta.

### LDRT

Arquivo esperado:

```text
ldrt/secrets/.env
```

Use `ldrt/.env.example` como base.

## Permissoes

Padrao recomendado no FTP:

- Pastas: `0755`
- Arquivos: `0644`
- `.env`: preferir `0640`; se o PHP nao conseguir ler, usar `0644` somente com `.htaccess` bloqueando acesso.
- Pastas de sessao/cache/dados gravaveis: testar `0755`, depois `0775` se necessario.

## Teste apos subir

Abra:

```text
https://www.renastonline.org/
https://www.renastonline.org/carex/
https://www.renastonline.org/fichario/
https://www.renastonline.org/ldrt/
https://www.renastonline.org/renastonline/login.php
```

Se algum modulo retornar tela branca:

1. Confirme PHP 8 no painel/handler.
2. Confirme que `.env` e `.htaccess` subiram pelo FTP.
3. Confirme se `pdo_pgsql` está habilitado.
4. Confira se o banco aceita conexao externa a partir da hospedagem.
5. Ative `APP_DEBUG=true` temporariamente apenas para diagnostico e retorne para `false`.
