# Migracao para Producao

Guia passo a passo para configurar o CAREX em ambiente de producao com autenticacao Google OAuth real.

## 1. Configurar projeto no Google Cloud Console

### 1.1 Criar ou selecionar projeto

1. Acesse `https://console.cloud.google.com`.
2. No topo, clique em `Selecionar projeto`.
3. Crie um novo projeto ou selecione o projeto existente do CAREX.

### 1.2 Configurar tela de consentimento OAuth

1. Acesse `APIs e servicos` > `Tela de consentimento do OAuth`.
2. Escolha o tipo de usuario adequado:
   - `Interno`, se a instituicao usa Google Workspace.
   - `Externo`, se contas fora do Workspace poderao entrar.
3. Informe nome do app, e-mail de suporte e dominio autorizado.
4. Adicione escopos basicos: `openid`, `profile` e `email`.
5. Se o app estiver em teste, adicione os usuarios de teste.

### 1.3 Criar credencial OAuth 2.0

1. Acesse `APIs e servicos` > `Credenciais`.
2. Clique em `+ Criar credenciais` > `ID do cliente OAuth`.
3. Em `Tipo de aplicativo`, escolha `Aplicativo da Web`.
4. Nome sugerido: `CAREX Web`.

#### Origens JavaScript autorizadas

Use somente a origem: esquema, host e, se existir, porta. Nao inclua caminho e nao termine com `/`.

Local:

```text
http://localhost
```

Producao:

```text
https://seudominio.com
```

Nao cole aqui:

```text
http://localhost/quixotesca/public_html/carex/auth-callback.php
```

Se o Google mostrar a mensagem `Origem invalida: nao e permitido que URIs de origem contenham um caminho ou terminem com "/"`, a URL de callback foi colada no campo de origem JavaScript.

#### URIs de redirecionamento autorizados

Aqui sim use a URL completa da callback PHP.

Local:

```text
http://localhost/quixotesca/public_html/carex/auth-callback.php
```

Producao:

```text
https://www.renastonline.org/carex/public/auth-callback.php
```

Essa URI precisa ser identica ao valor salvo em `GOOGLE_REDIRECT_URI`.

### 1.4 Copiar credenciais

Depois de criar a credencial, copie:

- `Client ID`
- `Client Secret`

Guarde o segredo com cuidado. Ele deve ficar apenas no `.env` do servidor ou em um cofre de segredos.

## 2. Configurar variaveis de ambiente

No painel administrativo ou manualmente no `.env`:

```env
APP_ENV=production
APP_DEBUG=false

DB_HOST=seu-host-postgresql
DB_PORT=5432
DB_DATABASE=seu-database
DB_USERNAME=seu-usuario
DB_PASSWORD=sua-senha-segura
DB_SCHEMA=carex
DB_ALLOW_WRITES=false

GOOGLE_CLIENT_ID=123456789-abcdefghij.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-sua-chave-secreta-aqui
GOOGLE_REDIRECT_URI=https://www.renastonline.org/carex/public/auth-callback.php
```

Nunca versione `.env` com credenciais reais.

## 3. Deploy na hospedagem PHP

Envie os arquivos do projeto para o servidor, exceto:

- `.env` local.
- `chrome-debug-profile/`.
- `node_modules/`, se existir.
- dumps, backups, logs e arquivos temporarios.

Crie o `.env` diretamente no servidor.

## 4. Checklist pre-producao

### Ambiente

- [ ] `APP_ENV=production`.
- [ ] `APP_DEBUG=false`.
- [ ] Credenciais Google OAuth reais configuradas.
- [ ] `GOOGLE_REDIRECT_URI` igual a URI cadastrada no Google.

### Google Cloud Console

- [ ] Cliente OAuth do tipo `Aplicativo da Web`.
- [ ] Origem JavaScript cadastrada sem caminho, exemplo `https://seudominio.com`.
- [ ] URI de redirecionamento cadastrada com caminho completo, exemplo `https://www.renastonline.org/carex/public/auth-callback.php`.
- [ ] Tela de consentimento configurada.
- [ ] Usuarios de teste adicionados, se o app estiver em modo teste.

### Seguranca

- [ ] `.env` nao esta acessivel pela web.
- [ ] `.env` nao esta versionado.
- [ ] HTTPS ativo em producao.
- [ ] `DB_ALLOW_WRITES=false`, salvo janela operacional autorizada.

### Banco de dados

- [ ] Tabela `users` criada.
- [ ] Colunas esperadas: `id`, `google_id`, `email`, `name`, `profile_picture`, `role`, `status`, `remember_token`, `created_at`, `updated_at`.
- [ ] Pelo menos um usuario admin disponivel apos o primeiro login.

## 5. Primeiro acesso em producao

1. Acesse `public/login.php`.
2. Entre com uma conta Google autorizada.
3. Confirme se o usuario foi criado em `users`.
4. Garanta que o usuario administrador tenha `role='admin'`.
5. Acesse `public/administrativo.php`.

## 6. Erros comuns

| Erro | Causa provavel | Correcao |
| --- | --- | --- |
| `Origem invalida` no painel do Google | Callback completa colada em `Origens JavaScript autorizadas`. | Cole apenas `http://localhost` ou `https://seudominio.com` nesse campo. |
| `redirect_uri_mismatch` no login | `GOOGLE_REDIRECT_URI` diferente da URI cadastrada no Google. | Cadastre exatamente a callback completa em `URIs de redirecionamento autorizados`. |
| Login volta para erro OAuth | Client ID/Secret incorretos, state expirado ou callback divergente. | Revise `.env`, Google Console e tente novamente. |
| Bypass aparece em ambiente real | `APP_ENV=local` ou setting local habilitado. | Use `APP_ENV=production` em producao. |
