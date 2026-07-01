# Deploy na Locaweb via FTP

Checklist operacional para publicar a Plataforma RENAST Online em hospedagem compartilhada por FTP/WinSCP.

## Regra critica para 2026-07-01

O banco configurado no ambiente atual deve ser tratado como banco de producao. Esta publicacao e uma publicacao de arquivos, nao uma janela de banco.

Antes de subir:

1. Nao execute scripts de carga, migracao, diagnostico de escrita ou importacao.
2. Nao apague arquivos `.lock` remotos do Fichario.
3. Nao altere `APP_ENV=production` nem `APP_DEBUG=false`.
4. Mantenha `DB_ALLOW_WRITES=false` no CAREX.
5. Mantenha `FICHARIO_ALLOW_AUTO_MIGRATIONS=false` no Fichario.
6. Mantenha `CAT_ALLOW_SCHEMA_UPDATES=false` no CAT.

Qualquer DDL em producao exige backup validado, janela operacional e autorizacao explicita. Se uma tela quebrar por coluna/tabela ausente, pare a publicacao e planeje uma migracao controlada; nao resolva apagando locks ou ativando migrations no FTP.

## WinSCP

### Exibir arquivos ocultos

Arquivos que comecam com ponto sao ocultos por padrao no Windows e no WinSCP. Se `.htaccess` nao subir, a hospedagem pode retornar erro 500 ou expor arquivos internos.

1. No WinSCP, use `Ctrl + Alt + H` para ativar arquivos ocultos.
2. Confirme que `.htaccess` aparece nos paineis local e remoto.
3. Confirme que os `.env` ja existem no servidor nas pastas esperadas, mas nao envie `.env` local dentro do pacote padrao.

### Mascara de exclusao

Ao usar upload pelo WinSCP, configure esta mascara em `Configuracoes de transferencia -> Filtro -> Excluir arquivos`:

```text
*/.git/; */.gitattributes; */.gitignore; */.editorconfig; */.agents/; */.codex/; Plataforma Renast/; *.bak; *.tmp; *.log; *.zip; *.rar; *.7z; scratch/; */scratch/; fichario/tests/; fichario/mockup/; carex/scratch/; carex/chrome-debug-profile/; gerar_pacote_deploy.php; deploy_locaweb.zip; *.lock; *.sqlite; *.sqlite3; *.db; .env; */.env
```

## Estrutura no FTP

### Area publica

Suba o conteudo de `public_html/` local para a pasta publica do dominio no FTP.

Estrutura esperada na raiz publica:

```text
/
  .htaccess
  index.php
  favicon.ico
  favicon.png
  limpar_sessoes.php
  assets/
  acesso/
  carex/
  cat/
  fichario/
  ldrt/
  investigacao/
  renastonline/
```

### Area privada

Suba estas pastas/arquivos para a raiz da conta de hospedagem, um nivel acima da pasta publica:

```text
.htaccess
.env.example
README.md
docs/
includes/
secrets/
acesso/
carex/
cat/
fichario/
investigacao/
ldrt/
```

Nao suba:

```text
.git/
.agents/
.codex/
scratch/
_archive/
*.zip
*.log
*.lock locais
*.sqlite
*.db
qualquer .env local
```

## Arquivos ocultos e segredos

Confirme no servidor:

- `.htaccess` existe na raiz privada.
- `public_html/.htaccess` existe na raiz publica.
- `docs/.htaccess` existe se `docs/` for enviado.
- `secrets/.htaccess` existe.
- `acesso/secrets/.htaccess` existe.
- `carex/secrets/.htaccess` existe.
- `fichario/secrets/.htaccess` existe.
- `ldrt/secrets/.htaccess` existe.
- Os `.env` reais existem apenas no servidor, nas pastas esperadas.

## Variaveis de producao

### Portal e Acesso

Arquivos esperados:

```text
secrets/.env
acesso/secrets/.env
```

Valores obrigatorios:

```text
APP_ENV=production
APP_DEBUG=false
ACCESS_ADMIN_PASSWORD=<senha forte, se precisar criar admin inicial>
SESSION_CLEANUP_TOKEN=<token se limpar_sessoes.php for chamado por HTTP>
```

### CAREX

Arquivo esperado:

```text
carex/secrets/.env
```

Valores obrigatorios:

```text
APP_ENV=production
APP_DEBUG=false
DB_SCHEMA=carex
DB_ALLOW_WRITES=false
GOOGLE_REDIRECT_URI=https://www.renastonline.org/carex/public/auth-callback.php
```

Nao habilite `DB_ALLOW_WRITES=true` durante deploy por FTP.

### CAT

Arquivo esperado:

```text
cat/secrets/.env
```

Valores obrigatorios:

```text
DB_SCHEMA=cat
CAT_ALLOW_SCHEMA_UPDATES=false
```

O CAT possui rotinas de criacao/ajuste de schema para preparacao de base e cache operacional. Em deploy FTP contra banco de producao, essas rotinas devem permanecer desabilitadas.

### Fichario

Arquivo esperado:

```text
fichario/secrets/.env
```

Valores obrigatorios:

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://www.renastonline.org/fichario
DB_SCHEMA=public
FICHARIO_ALLOW_AUTO_MIGRATIONS=false
```

O Fichario usa arquivos `.lock` para migrações historicas. Nao envie locks locais e nao apague locks remotos durante publicacao comum. Para qualquer migracao real: backup, janela operacional, autorizacao explicita, `FICHARIO_ALLOW_AUTO_MIGRATIONS=true` temporario e retorno imediato para `false`.

### LDRT

Arquivo esperado:

```text
ldrt/secrets/.env
```

Valores obrigatorios:

```text
APP_ENV=production
APP_DEBUG=false
DB_SCHEMA=ldrt
```

## Permissoes

Padrao recomendado:

- Pastas: `0755`.
- Arquivos: `0644`.
- `.env`: preferir `0640`; se o PHP nao conseguir ler, usar `0644` somente com `.htaccess` bloqueando acesso.
- Pastas de sessao/cache/dados gravaveis: testar `0755`, depois `0775` se necessario.

## Teste apos subir

Abra:

```text
https://www.renastonline.org/
https://www.renastonline.org/acesso/
https://www.renastonline.org/carex/
https://www.renastonline.org/cat/
https://www.renastonline.org/fichario/
https://www.renastonline.org/ldrt/
```

Se algum modulo retornar tela branca:

1. Confirme PHP 8 no painel/handler.
2. Confirme que `.env` e `.htaccess` existem no servidor.
3. Confirme se `pdo_pgsql` esta habilitado.
4. Confira se o banco aceita conexao externa a partir da hospedagem.
5. Consulte logs da hospedagem antes de mudar variaveis de producao.
6. Ative `APP_DEBUG=true` temporariamente apenas para diagnostico e retorne para `false`.
7. Nao habilite `FICHARIO_ALLOW_AUTO_MIGRATIONS`, `CAT_ALLOW_SCHEMA_UPDATES` ou `DB_ALLOW_WRITES` para "testar" sem backup e autorizacao.
