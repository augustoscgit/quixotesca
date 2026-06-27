# Arquitetura CAREX

CAREX e uma aplicacao PHP sem framework, organizada em paginas publicas, endpoints JSON, classes de repositorio, utilitarios HTTP e suporte de autenticacao.

## Camadas

```text
public/
  paginas HTML/PHP e endpoints HTTP
public/assets/
  CSS, JavaScript, logos e favicon
src/
  bootstrap, conexao, repositorios, autenticacao, seguranca e respostas HTTP
config/
  configuracao da aplicacao e settings locais
docs/
  documentacao tecnica e metodologica
tools/
  scripts locais de diagnostico/migracao
```

## Fluxo de uma requisicao

1. Uma pagina ou endpoint inclui `src/bootstrap.php`.
2. O bootstrap registra autoload simples para o namespace `Carex\`.
3. `Carex\Support\Env` carrega `.env`.
4. `config/app.php` monta configuracao obrigatoria.
5. Paginas internas chamam `Carex\Http\Auth::requireLogin()`.
6. Endpoints internos chamam `Carex\Http\Auth::requireApiLogin()`.
7. `Carex\Http\Security` aplica headers, metodo permitido e CSRF quando necessario.
8. Repositorios em `src/Database/` consultam PostgreSQL por PDO.
9. Endpoints retornam JSON por `Carex\Http\Response`.

## Classes principais

| Classe | Papel |
| --- | --- |
| `Carex\Database\Connection` | Cria PDO, define `search_path`, timeouts e read-only por padrao. |
| `Carex\Database\SchemaRepository` | Lista tabelas e objetos consultaveis. |
| `Carex\Database\ReadonlyRepository` | Consulta linhas paginadas de tabelas/views/materialized views validadas. |
| `Carex\Database\DevelopmentInventoryRepository` | Le catalogos PostgreSQL para inventario tecnico. |
| `Carex\Database\AdminRepository` | Consulta usuarios, especialistas legados, materialized views e refresh protegido. |
| `Carex\Database\UserRepository` | Upsert de usuarios Google, remember token e busca por usuario. |
| `Carex\Database\WorkRepository` | Consulta matrizes, classificacoes, filtros e estimativas de vinculos. |
| `Carex\Http\Auth` | Sessao, login, logout, remember-me, protecao de paginas/APIs e bloqueio de desligados. |
| `Carex\Http\Security` | Headers, allowlist de metodos, escape HTML e CSRF. |
| `Carex\Http\Response` | JSON e erros padronizados. |
| `Carex\Support\Env` | Le variaveis de ambiente e exige obrigatorias. |
| `Carex\Support\GoogleClient` | Gera URL OAuth e troca codigo por perfil Google. |

## Paginas

| Arquivo | URL | Descricao |
| --- | --- | --- |
| `public/index.php` | `/public/` | Redireciona para `matrizes.php`. |
| `public/login.php` | `/public/login.php` | Tela de login Google OAuth e bypass local opcional. |
| `public/auth-callback.php` | `/public/auth-callback.php` | Callback OAuth, upsert de usuario e criacao de sessao. |
| `public/logout.php` | `/public/logout.php` | Encerra sessao e limpa remember token. |
| `public/matrizes.php` | `/public/matrizes.php` | Cards/lista de matrizes. |
| `public/matriz.php` | `/public/matriz.php?id_matriz=agr` | Detalhe operacional da matriz. |
| `public/metodologia.php` | `/public/metodologia.php` | Renderizacao da metodologia Carex-BR. |
| `public/administrativo.php` | `/public/administrativo.php` | Usuarios, matrizes, criterios, settings e materialized views. Requer admin. |
| `public/desenvolvimento.php` | `/public/desenvolvimento.php` | Inventario tecnico, leitura de objetos, docs e ambiente. |
| `index.php` | `/` | Landing/portal no nivel raiz do projeto. |
| `editor.php` | `/editor.php` | Editor Markdown local, controlado por setting e fora do fluxo de banco. |

## JavaScript

| Arquivo | Uso |
| --- | --- |
| `public/assets/app.js` | Modulo Desenvolvimento: inventario, dados, filtros, docs e ambiente. |
| `public/assets/admin.js` | Administrativo: materialized views, progresso estimado e interacoes de admin. |
| `public/assets/matriz.js` | Detalhe da matriz: classificacoes, filtros, paginacao e estimativas de vinculos. |

## Configuracao

| Arquivo | Papel |
| --- | --- |
| `.env` | Configuracao sensivel local/servidor. Nao versionar. |
| `.env.example` | Modelo sem segredos. |
| `config/app.php` | Le ambiente, banco e Google OAuth via `Env`. |
| `config/settings.json` | Settings locais, como editor Markdown e bypass de login. |

## Padroes importantes

- Identificadores SQL devem vir de metadados/allowlist antes de entrar em query.
- Valores devem usar parametros preparados.
- Endpoints de leitura usam `Security::allowReadOnlyRequest()`.
- Endpoints protegidos usam `Auth::requireApiLogin()`.
- Paginas internas usam `Auth::requireLogin()`.
- Administracao sensivel deve validar `role=admin`.
- Endpoints `POST` usam `Security::allowMethods(['POST'])` e CSRF.
- Conexao fica read-only por padrao, mesmo que algum codigo tente escrever.
- Escritas do fluxo de autenticacao sao excecoes controladas e devem continuar bem delimitadas.
