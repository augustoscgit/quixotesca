# Seguranca Operacional CAREX

Este projeto esta configurado para operar a base PostgreSQL em modo somente leitura por padrao.

## Credenciais

- Credenciais reais devem existir apenas em `.env` ou variaveis de ambiente do servidor.
- `.env` e variantes `.env.*` ficam fora do Git.
- `.env.example` usa valores ficticios e nao deve conter host, usuario ou senha reais.
- O codigo nao possui fallback para host, banco, usuario ou senha de producao.
- O Apache bloqueia acesso HTTP a dotfiles, `config/`, `src/`, `tools/`, `docs/` e arquivos de backup/dump.

## Banco de dados

- `DB_ALLOW_WRITES=false` e o padrao seguro.
- Quando `DB_ALLOW_WRITES` nao esta explicitamente `true`, a conexao executa:

```sql
SET default_transaction_read_only TO on;
SET statement_timeout TO '30000ms';
SET idle_in_transaction_session_timeout TO '10000ms';
```

- A rota de refresh de materialized view retorna `403` quando a aplicacao esta em modo somente leitura.
- A interface administrativa desabilita botoes de atualizacao quando a base esta protegida.

## Recomendacao obrigatoria para producao

A protecao mais forte deve existir tambem no PostgreSQL: o usuario configurado em `DB_USERNAME` deve ser um usuario somente leitura, sem permissoes de escrita.

Exemplo conceitual para o DBA adaptar em janela segura e com backup:

```sql
revoke insert, update, delete, truncate, references, trigger on all tables in schema carex from usuario_app;
revoke create on schema carex from usuario_app;
grant usage on schema carex to usuario_app;
grant select on all tables in schema carex to usuario_app;
alter default privileges in schema carex grant select on tables to usuario_app;
```

Nao execute scripts de permissao diretamente em producao sem backup e validacao do DBA.
