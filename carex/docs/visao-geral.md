# Visao Geral da Aplicacao CAREX

CAREX e uma aplicacao PHP sem framework para consulta, inventario, apoio operacional e administracao controlada do schema PostgreSQL `carex`. A interface usa HTML, Bootstrap e JavaScript sem etapa de build, integrada a identidade visual da Plataforma RENAST online.

O sistema trabalha sobre uma base PostgreSQL considerada de producao. A regra geral continua sendo leitura segura: consultas usam PDO, paginacao, allowlists e conexao read-only por padrao. Existem, porem, escritas controladas para autenticacao, sessao persistente, configuracoes locais e operacoes administrativas explicitamente protegidas.

## Identidade

- Marca principal: Plataforma RENAST online.
- Produto/modulo: CAREX.
- Ativos publicos em `public/assets/`.
- Diretrizes visuais gerais registradas em `../../docs/bootstrap-first-planejamento.md`, `../../docs/bootstrap-first-exemplos.md` e `../../docs/tema-css-bootstrap-modulos.md`; a ponte local fica em `docs/identidade-visual.md`.

## Modulos atuais

| Modulo | URL | Estado | Papel atual |
| --- | --- | --- | --- |
| Login | `/public/login.php` | Implementado | Entrada autenticada via Google OAuth 2.0, com bypass local opcional em `APP_ENV=local`. |
| Matrizes | `/public/matrizes.php` | Implementado | Lista matrizes de `tb_matriz`, indicadores de classificacao e anos RAIS. |
| Detalhe da matriz | `/public/matriz.php?id_matriz=...` | Implementado | Exibe informacoes gerais, especialistas vinculados, classificacoes, filtros dinamicos e estimativas de vinculos. |
| Metodologia | `/public/metodologia.php` | Implementado | Renderiza `docs/carex_br_metodologia_especialistas.md` e `criterios-conciliacao.md` com sumario lateral e progresso de leitura. |
| Administrativo | `/public/administrativo.php` | Implementado | Area restrita a `role=admin` para usuarios, matrizes, criterios de conciliacao, views materializadas e configuracoes. |
| Desenvolvimento | `/public/desenvolvimento.php` | Implementado | Area restrita a `role=admin` para inventario tecnico da base, leitura paginada de objetos, documentacao e painel de ambiente. |
| Portal raiz | `/index.php` | Implementado | Landing/portal institucional fora da area autenticada principal. |
| Editor Markdown | `/editor.php` | Implementado | Editor local de Markdown controlado por `config/settings.json`. |
| Resultados consolidados | - | Planejado | Deve concentrar indicadores e visoes herdadas para analise. |

## Autenticacao e perfis

- Todas as paginas internas relevantes chamam `Auth::requireLogin()`.
- APIs protegidas chamam `Auth::requireApiLogin()`.
- Login oficial usa Google OAuth 2.0 Authorization Code Flow.
- Em ambiente local com credenciais dummy, a tela de login pode exibir bypass de desenvolvimento para admin, especialista e usuario desligado.
- O cadastro/sincronizacao de usuarios usa a tabela `users`.
- Campos centrais esperados: `google_id`, `name`, `email`, `profile_picture`, `role`, `status`, `remember_token`, `created_at`, `updated_at`.
- Roles atuais observadas: `admin`, `especialista`, `usuario`.
- `especialista` e `usuario` acessam as areas operacionais, mas nao acessam Administrativo nem Desenvolvimento.
- Usuarios com `status='desligado'` sao bloqueados no login e em revalidacoes de sessao.
- A conta `augustosc@gmail.com` e forcada como `admin` pelo repositorio de usuarios.
- O recurso "Manter-me conectado" grava `remember_token` e usa cookie HttpOnly/SameSite.

## Administrativo

- Acesso restrito a usuarios autenticados com `role=admin`.
- Aba `Usuarios`: lista usuarios cadastrados, foto, e-mail, role, status e datas.
- Aba `Configuracoes de Matrizes`: lista matrizes e progresso, com atalho para gerenciar cada matriz.
- A pagina `Metodologia` renderiza o documento metodologico principal e tambem `criterios-conciliacao.md`.
- Aba `Views materializadas`: lista materialized views e permite refresh individual apenas quando `DB_ALLOW_WRITES=true`, com CSRF e allowlist.
- Aba `Configuracoes de Sistema`: controla editor Markdown, visibilidade do bypass local, variaveis Google OAuth e `APP_ENV`.

## Desenvolvimento

- Inventario de objetos do schema `carex`.
- Abas por tipo de objeto: tabelas, views, materialized views, triggers, rotinas, sequencias, indices, constraints e tipos.
- Leitura paginada de tabelas, views e materialized views validadas.
- Busca textual, filtros dinamicos e ordenacao por colunas reais.
- Estimativas de linhas por catalogo PostgreSQL para evitar contagens pesadas na carga inicial.
- Aba de documentacao que lista e renderiza arquivos Markdown validados.
- Aba de ambiente com status de `APP_ENV`, Google OAuth, bypass de login e link para guia de migracao.

## Trabalho / Matrizes

- Cards de matrizes de `tb_matriz`.
- Indicadores: total de itens, total classificado, percentual de avanco e anos RAIS disponiveis.
- Detalhe de matriz com informacoes gerais, especialistas vinculados, classificacoes e estimativas de vinculos.
- Classificacao final considera classificacao direta e classificacao herdada quando aplicavel.
- Estimativas de vinculos usam media anual e matriz 3x3 CBO x CNAE por criterio.

## Endpoints principais

| Endpoint | Metodo | Modulo | Descricao |
| --- | --- | --- | --- |
| `/public/api/tables.php` | GET | Desenvolvimento | Lista objetos consultaveis e colunas. |
| `/public/api/rows.php` | GET | Desenvolvimento | Consulta dados paginados de objeto validado. |
| `/public/api/unique_values.php` | GET | Desenvolvimento | Lista valores unicos para filtros genericos. |
| `/public/api/development/objects.php` | GET | Desenvolvimento | Lista objetos tecnicos do schema. |
| `/public/api/development/doc_content.php` | GET | Desenvolvimento | Retorna conteudo Markdown validado do projeto. |
| `/public/api/admin/usuarios.php` | GET | Administrativo | Lista usuarios cadastrados. Requer admin. |
| `/public/api/admin/materialized_views.php` | GET | Administrativo | Lista views materializadas do schema atual. |
| `/public/api/admin/refresh_materialized_view.php` | POST | Administrativo | Atualiza uma view materializada validada. Requer admin, CSRF e escrita habilitada. |
| `/public/api/work/matrizes.php` | GET | Trabalho | Lista matrizes e indicadores. |
| `/public/api/work/classificacoes.php` | GET | Trabalho | Lista itens, classificacao direta, final e filtros. |
| `/public/api/work/unique_values.php` | GET | Trabalho | Lista valores unicos para filtros da matriz. |
| `/public/api/work/vinculos_estimativas.php` | GET | Trabalho | Lista estimativas de vinculos por criterio e classificacao. |

## Estrutura principal

```text
config/
  app.php
  settings.json
docs/
  *.md
public/
  index.php
  login.php
  auth-callback.php
  logout.php
  matrizes.php
  matriz.php
  metodologia.php
  administrativo.php
  desenvolvimento.php
  api/
  assets/
src/
  bootstrap.php
  Database/
  Http/
  Support/
  templates/
tools/
  check_connection.php
  inspect_schema.php
  migrate_users.php
landing.md
sobre.md
criterios-conciliacao.md
```

## Banco de Dados

- Banco PostgreSQL configurado por `.env`.
- Banco observado/documentado: `carex`.
- Schema: `carex`.
- Conexao via PDO PostgreSQL.
- `search_path` definido para o schema configurado.
- `statement_timeout` e `idle_in_transaction_session_timeout` definidos na conexao.
- `default_transaction_read_only=on` quando `DB_ALLOW_WRITES` nao esta explicitamente habilitado.

## Seguranca atual

- Credenciais fora do Git em `.env`.
- `.env.example` documenta variaveis sem segredo real.
- Configuracao da base exige variaveis obrigatorias, sem fallback para host, usuario ou senha reais.
- Conexao PostgreSQL fica em modo somente leitura por padrao.
- Endpoints de consulta aceitam apenas `GET` e `HEAD`.
- Endpoints protegidos exigem sessao autenticada.
- Area administrativa exige `role=admin`.
- Operacoes administrativas POST usam token CSRF.
- Refresh de materialized view valida o nome contra allowlist do catalogo antes de executar SQL.
- Refresh de materialized view e bloqueado quando `DB_ALLOW_WRITES=false`.
- Consultas usam PDO e parametros preparados.
- Objetos e colunas sao validados por metadados antes de consulta.
- Diretorios internos `src`, `config` e `tools` devem permanecer bloqueados via HTTP.
- Headers de seguranca sao aplicados pelas paginas/endpoints.

## Documentacao complementar

- `README.md`: entrada do projeto, requisitos, execucao local e links.
- `docs/indice.md`: ordem recomendada de leitura.
- `docs/arquitetura.md`: camadas, fluxo de requisicao, classes e paginas.
- `docs/api.md`: contratos dos endpoints HTTP.
- `docs/banco-dados.md`: catalogo e observacoes do PostgreSQL.
- `docs/seguranca.md`: regras operacionais e limites de escrita.
- `docs/guia-ia.md`: contexto essencial para agentes de IA.
- `docs/modulo-desenvolvimento.md`: detalhes do modulo Desenvolvimento.
- `docs/modulo-administrativo.md`: detalhes do modulo Administrativo.
- `docs/modulo-trabalho.md`: detalhes do modulo Trabalho/Matrizes.
- `docs/especificacao_autenticacao_google.md`: regra de negocio e arquitetura do login Google.
- `docs/migracao_producao.md`: checklist de deploy e OAuth real.
- `criterios-conciliacao.md`: criterios CNAE x CBO renderizados na pagina de Metodologia.
- `landing.md` e `sobre.md`: conteudo institucional do portal/editor.

## Proximas evolucoes provaveis

1. Revisar o modelo de permissoes por role, especialmente acesso de `especialista` versus `usuario`.
2. Formalizar migrations/versionamento SQL para tabela `users` e eventuais ajustes estruturais.
3. Separar usuario PostgreSQL de leitura e usuario de escrita antes de habilitar rotinas administrativas em producao.
4. Implementar o modulo de resultados consolidados.
5. Revisar textos com acentuacao corrompida em documentos e telas legadas.
