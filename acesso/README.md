# Modulo Acesso

Modulo comum de autenticacao da Plataforma RENAST Online.

## Escopo desta fase

- Criado em paralelo aos modulos atuais.
- Nao altera CAREX, Fichario, LDRT, RENAST Online, landing page, `assets/` ou `includes/`.
- Login por e-mail ou nome de usuario + senha.
- Sem Google OAuth nesta fase.
- Sem bloqueio por tentativas nesta fase, mas tentativas sao registradas.
- Auto-cadastro e verificacao de e-mail existem apenas como mockups.

## Configuracao

Criar no FTP:

```text
acesso/secrets/.env
```

Use `acesso/.env.example` como base.

## Banco de dados

Ao acessar o modulo com o `.env` configurado, o PHP cria automaticamente o schema indicado por `DB_SCHEMA` e as tabelas:

```text
users
apps
roles
permissions
user_roles
role_permissions
password_resets
email_verifications
login_attempts
```

Tambem cria os papeis e permissoes minimos de preparacao futura:

```text
acesso.admin
fichario.reader
fichario.admin
ldrt.reader
ldrt.admin
carex.reader
carex.admin
```

## Administrador inicial

Se `acesso.users` estiver vazia, o modulo cria o administrador inicial apenas quando `ACCESS_ADMIN_PASSWORD` estiver configurada no `.env` de producao.

```text
ACCESS_ADMIN_EMAIL=seu-email@example.org
ACCESS_ADMIN_USERNAME=admin
ACCESS_ADMIN_PASSWORD=uma-senha-forte
```

Em ambiente local/debug, se `ACCESS_ADMIN_PASSWORD` estiver vazia, ainda e permitido o fallback `admin` para facilitar desenvolvimento. Nao use esse fallback em producao.

## Evolucao futura

- Ativar auto-cadastro com usuario pendente.
- Ativar confirmacao de e-mail usando `email_verifications`.
- Integrar CAREX, Fichario e LDRT ao login comum por etapas, sem quebrar URLs atuais.
- Adicionar politicas de senha e bloqueio por tentativas quando aprovado.

## Documentacao visual e tema

As regras de tema, CSS, Bootstrap, navbar, botoes, formularios e contraste do Acesso ficam centralizadas em:

- `../docs/identidade-visual-ux.md`
- `../docs/tema-css-bootstrap-modulos.md`

O Acesso aplica essas regras principalmente em `acesso/src/bootstrap.php`, que tambem serve de base para a Administracao (`data-module="admin"`).
