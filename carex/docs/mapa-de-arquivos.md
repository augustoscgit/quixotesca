# Mapa de Arquivos CAREX

## Raiz

| Arquivo | Papel |
| --- | --- |
| `.env` | Configuracao local sensivel. Nao versionar. |
| `.env.example` | Modelo sem segredos reais. |
| `.gitignore` | Exclui credenciais, logs e temporarios. |
| `.htaccess` | Bloqueia acesso HTTP a arquivos/diretorios sensiveis. |
| `README.md` | Entrada principal da documentacao. |
| `index.php` | Portal/landing raiz. |
| `editor.php` | Editor Markdown local, controlado por setting. |
| `landing.md` | Conteudo Markdown da landing. |
| `sobre.md` | Conteudo Markdown sobre o projeto. |
| `criterios-conciliacao.md` | Documento de criterios CNAE x CBO renderizado em Metodologia. |

## `config/`

| Arquivo | Papel |
| --- | --- |
| `app.php` | Configuracao de app, banco e Google OAuth a partir de variaveis de ambiente. |
| `settings.json` | Settings locais, como bloqueio do editor Markdown e bypass de login local. |

## `src/`

| Arquivo | Papel |
| --- | --- |
| `bootstrap.php` | Autoload simples e carregamento de `.env`. |
| `Database/Connection.php` | PDO, schema, timeouts e read-only por padrao. |
| `Database/SchemaRepository.php` | Metadados de objetos consultaveis. |
| `Database/ReadonlyRepository.php` | Leitura paginada generica de objetos validados. |
| `Database/DevelopmentInventoryRepository.php` | Inventario tecnico via catalogos PostgreSQL. |
| `Database/AdminRepository.php` | Usuarios, especialistas legados, materialized views e refresh protegido. |
| `Database/UserRepository.php` | Upsert/busca de usuarios Google e remember token. |
| `Database/WorkRepository.php` | Matrizes, classificacoes, filtros e estimativas de vinculos. |
| `Http/Auth.php` | Sessao, login, logout, remember-me e protecao de paginas/APIs. |
| `Http/Security.php` | Headers, metodos permitidos, escape e CSRF. |
| `Http/Response.php` | Respostas JSON. |
| `Support/Env.php` | Leitura de variaveis de ambiente. |
| `Support/GoogleClient.php` | Integracao OAuth 2.0 com Google. |
| `templates/navbar.php` | Navegacao compartilhada. |

## `public/`

| Arquivo | Papel |
| --- | --- |
| `.htaccess` | Bloqueia dotfiles em area publica. |
| `index.php` | Redireciona para `matrizes.php`. |
| `login.php` | Tela de login Google OAuth e bypass local opcional. |
| `auth-callback.php` | Callback OAuth/mock, upsert de usuario e criacao de sessao. |
| `logout.php` | Logout e limpeza de remember token. |
| `matrizes.php` | Lista de matrizes. |
| `matriz.php` | Detalhe da matriz. |
| `metodologia.php` | Renderizacao da metodologia Markdown. |
| `administrativo.php` | Modulo administrativo restrito a admin. |
| `desenvolvimento.php` | Modulo de desenvolvimento. |

## `public/api/`

| Arquivo | Papel |
| --- | --- |
| `tables.php` | Lista objetos consultaveis. |
| `rows.php` | Le linhas de objeto validado. |
| `unique_values.php` | Valores unicos para filtros genericos. |
| `development/objects.php` | Inventario tecnico. |
| `development/doc_content.php` | Conteudo de Markdown validado. |
| `admin/usuarios.php` | Usuarios cadastrados. Requer admin. |
| `admin/materialized_views.php` | Materialized views do schema. |
| `admin/refresh_materialized_view.php` | Refresh protegido e bloqueado por padrao. |
| `work/matrizes.php` | Lista matrizes. |
| `work/classificacoes.php` | Itens e classificacoes da matriz. |
| `work/unique_values.php` | Valores unicos dos filtros da matriz. |
| `work/vinculos_estimativas.php` | Estimativas de vinculos por criterio. |

## `public/assets/`

| Arquivo | Papel |
| --- | --- |
| `app.css` | Estilos globais. |
| `app.js` | Desenvolvimento. |
| `admin.js` | Administrativo. |
| `matriz.js` | Detalhe da matriz. |
| logos e favicon | Identidade visual RENAST/CAREX. |

## `docs/`

| Arquivo | Papel |
| --- | --- |
| `indice.md` | Ordem recomendada de leitura. |
| `visao-geral.md` | Contexto geral atualizado do projeto. |
| `arquitetura.md` | Camadas, fluxo, classes e paginas. |
| `api.md` | Endpoints HTTP. |
| `seguranca.md` | Regras de seguranca operacional. |
| `guia-ia.md` | Guia para continuidade por agentes de IA. |
| `migracao_producao.md` | Checklist de producao/OAuth real. |
| demais `.md` | Documentacao de modulo, banco, metodologia, identidade e decisoes. |

## `tools/`

| Arquivo | Papel |
| --- | --- |
| `check_connection.php` | Diagnostico read-only da conexao. |
| `inspect_schema.php` | Inspecao local do schema via repositorio/conexao segura. |
| `migrate_users.php` | Migracao/ajuste da tabela `users`; executar somente com autorizacao explicita. |
| `view_def.txt` | Registro auxiliar de definicao de view. |

`tools/` nao deve ser exposto via HTTP.
