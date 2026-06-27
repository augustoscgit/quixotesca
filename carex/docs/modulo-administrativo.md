# Modulo Administrativo CAREX

O modulo Administrativo concentra funcoes de gestao do CAREX e e restrito a usuarios autenticados com `role=admin`.

## Acesso

- Pagina: `/public/administrativo.php`.
- Requer `Auth::requireLogin()`.
- Bloqueia usuarios que nao tenham `role=admin`.
- APIs administrativas sensiveis tambem exigem `Auth::requireApiLogin()` e checagem de role.

## Abas atuais

| Aba | Papel |
| --- | --- |
| `Usuarios` | Lista usuarios cadastrados na tabela `users` e permite editar perfil/status. |
| `Configuracoes de Matrizes` | Lista matrizes, progresso de classificacao e link para gerenciamento. |
| `Views materializadas` | Lista materialized views e controla refresh individual bloqueado por padrao. |
| `Configuracoes de Sistema` | Controla editor Markdown, bypass local, Google OAuth e `APP_ENV`. |

## Fontes principais

- `users`
- `tb_matriz`
- `tb_matriz_classificacao`
- `tb_especialista`
- `tb_matriz_especialista`
- `pg_matviews`
- `pg_class`
- `pg_namespace`
- `config/settings.json`
- `.env`

## Usuarios

A aba `Usuarios` mostra:

- Nome.
- E-mail.
- Foto de perfil, quando disponivel.
- Google ID.
- Role.
- Status.
- Data de cadastro.
- Ultimo login/atualizacao.
- Controles administrativos para alterar perfil entre `admin`, `especialista` e `usuario`.
- Controles administrativos para alterar status entre `ativo` e `desligado`.

O cadastro e a sincronizacao de usuarios acontecem no fluxo de login via `UserRepository`.

Regras de seguranca:

- Somente `role=admin` acessa a aba e executa alteracoes.
- Toda alteracao exige token CSRF.
- A conta administradora principal permanece protegida como `admin` e `ativo`.
- O admin logado nao consegue remover o proprio acesso administrativo pela interface.
- Ao marcar um usuario como `desligado`, o remember token e removido.
- Perfil e status sao recarregados do banco nas requisicoes autenticadas, entao mudancas de acesso passam a valer no proximo request do usuario.

## Matrizes

A aba de matrizes mostra:

- Nome e codigo da matriz.
- Total de itens.
- Total de classificados.
- Percentual de avanco.
- Link para `matriz.php?id_matriz=...`.

## Views materializadas

A aba `Views materializadas` lista as materialized views do schema configurado, incluindo estimativa de linhas, estado de populacao, indices e comentario.

Quando a escrita esta explicitamente habilitada, cada linha possui um botao de atualizacao que executa:

```sql
REFRESH MATERIALIZED VIEW "schema"."nome_da_view";
```

O refresh e individual para evitar operacoes amplas e reduzir risco operacional. Em producao, com `DB_ALLOW_WRITES=false`, a interface mostra os botoes como bloqueados e o endpoint retorna `403`.

Durante a execucao, a tela mostra uma barra longa de progresso por tempo decorrido, calibrada pela estimativa de linhas da view. O PostgreSQL nao informa percentual nativo para `REFRESH MATERIALIZED VIEW`; por isso, a barra avanca ate 95% por estimativa e so fecha em 100% quando o endpoint confirma a conclusao.

## Configuracoes de sistema

A aba de configuracoes permite:

- Habilitar/desabilitar edicao de Markdown externo (`allow_markdown_edit`).
- Mostrar/ocultar botoes de bypass local (`dev_login_visible`) quando `APP_ENV=local`.
- Salvar credenciais Google OAuth no `.env`.
- Ajustar `APP_ENV` entre `local` e `production`.
- Abrir instrucoes para configurar o Google Cloud Console.

Cuidados:

- `.env` contem segredos e nunca deve ser versionado.
- Alterar OAuth/APP_ENV afeta login.
- Bypass local deve ficar invisivel/desabilitado em producao.

## Endpoints

| Endpoint | Metodo | Descricao |
| --- | --- | --- |
| `/public/api/admin/usuarios.php` | GET | Lista usuarios cadastrados. Requer admin. |
| `/public/api/admin/materialized_views.php` | GET | Lista views materializadas do schema atual. |
| `/public/api/admin/refresh_materialized_view.php` | POST | Atualiza uma view materializada validada por allowlist, CSRF e `DB_ALLOW_WRITES=true`. |

## Estado atual

- Interface interna autenticada.
- Area administrativa restrita a admin.
- Banco de negocio permanece majoritariamente somente leitura.
- Escritas existentes ficam concentradas em autenticacao, settings locais, `.env` via painel e refresh bloqueado/condicional.
- Refresh real de materialized view so deve ser habilitado com backup, janela operacional e autorizacao explicita.
