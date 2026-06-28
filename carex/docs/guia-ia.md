# Guia para IA no Projeto CAREX

Este arquivo existe para agentes de IA que assumirem continuidade do projeto.

## Contexto essencial

- Projeto: CAREX.
- Stack: PHP puro, Bootstrap, HTML e JavaScript sem framework de build.
- Banco: PostgreSQL, schema `carex`.
- Ambiente local observado: XAMPP em `C:\xampp\htdocs\quixotesca\carex`.
- URL local principal: `http://localhost/quixotesca/public_html/carex/`.
- Entrada interna atual: `public/index.php` redireciona para `matrizes.php`.
- Area interna exige login por `Carex\Http\Auth`.
- Login oficial: Google OAuth 2.0 via `public/login.php` e `public/auth-callback.php`.
- Base conectada: producao ou equivalente sensivel.
- Backup: nao confirmado.

## Fotografia funcional atual

- `matrizes.php`: lista matrizes.
- `matriz.php`: detalhe da matriz, classificacoes, filtros e estimativas de vinculos.
- `metodologia.php`: renderiza a metodologia em Markdown.
- `administrativo.php`: area restrita a `role=admin`.
- `desenvolvimento.php`: inventario tecnico, leitura de objetos, documentacao e ambiente.
- `editor.php`: editor Markdown local controlado por setting.

## Autenticacao e usuarios

- Usuarios ficam na tabela `users`.
- `UserRepository` faz upsert de perfil Google e atualiza dados de login.
- `Auth` guarda usuario em sessao PHP.
- "Manter-me conectado" usa `remember_token` e cookie HttpOnly/SameSite.
- `status='desligado'` bloqueia login e revalidacao.
- `augustosc@gmail.com` e forcado como `admin`.
- Em `APP_ENV=local` com credenciais dummy, a tela de login pode mostrar bypass de desenvolvimento.
- A visibilidade do bypass fica em `config/settings.json` (`dev_login_visible`).

## Regras de seguranca para IA

- Nao execute `INSERT`, `UPDATE`, `DELETE`, `TRUNCATE`, `ALTER`, `DROP`, `CREATE`, `REINDEX`, `VACUUM FULL`, `GRANT`, `REVOKE` ou `REFRESH MATERIALIZED VIEW` sem ordem explicita do usuario.
- Excecao conceitual ja existente no codigo: o fluxo de autenticacao pode escrever em `users` e `remember_token`; nao acione esse fluxo em massa nem simule logins sem necessidade.
- Nao dispare refresh real de materialized view. O endpoint existe, mas fica bloqueado por padrao quando `DB_ALLOW_WRITES=false`.
- Prefira `SELECT`, endpoints `GET` e leitura de arquivos.
- Antes de qualquer consulta potencialmente pesada, explique o risco e limite pagina/timeout.
- Nao exponha senha, host real, usuario real, client secret Google ou conteudo de `.env` em respostas.
- Nao versionar `.env`, `.env.*`, dumps, backups, logs, perfis temporarios de navegador ou arquivos em `secrets/`.
- Nao remova defesas de `Connection`, `.htaccess`, CSRF, autenticacao, validacoes de role ou allowlists.
- Nao promova `DB_ALLOW_WRITES=true` como solucao geral.

## Estado seguro esperado

O comando:

```powershell
& C:\xampp\php\php.exe tools\check_connection.php
```

deve mostrar `read_only=on` quando `DB_ALLOW_WRITES=false`.

O endpoint:

```text
POST /public/api/admin/refresh_materialized_view.php
```

deve retornar `403` quando `DB_ALLOW_WRITES=false` ou quando o usuario nao for admin/autenticado.

## Como trabalhar

1. Leia `docs/indice.md`, `docs/visao-geral.md`, `docs/arquitetura.md`, `docs/api.md` e `docs/seguranca.md`.
2. Inspecione o codigo local antes de inferir padroes.
3. Mantenha alteracoes pequenas e localizadas.
4. Use `apply_patch` para edicoes manuais.
5. Valide PHP com `& C:\xampp\php\php.exe -l caminho\arquivo.php`.
6. Valide JavaScript com `node --check` quando `node` estiver disponivel.
7. Use HTTP local para validar telas/endpoints sem acionar escrita administrativa.
8. Se precisar testar login, prefira ambiente local controlado e nao divulgue credenciais.

## Sinais de perigo

- Usuario pedir "atualizar view", "rodar script", "corrigir direto na base" ou "executar SQL".
- Alteracoes em `src/Database/Connection.php` que desliguem `default_transaction_read_only`.
- `DB_ALLOW_WRITES=true` em ambiente de producao.
- Codigo que aceite nome de tabela, coluna ou view sem allowlist/metadados.
- Endpoints `POST` sem CSRF.
- Endpoints internos sem `Auth::requireApiLogin()` ou paginas internas sem `Auth::requireLogin()`.
- Acesso administrativo sem checar `role=admin`.
- Consulta em materialized view grande sem timeout, filtro ou paginacao.
- Escrita de credenciais Google em docs, respostas ou arquivos versionaveis.

## Resumo dos modulos

- Login: Google OAuth 2.0, sessao PHP e remember-me.
- Matrizes: lista e entrada operacional das matrizes.
- Detalhe da matriz: classificacoes, filtros e estimativas de vinculos.
- Metodologia: documentacao metodologica renderizada na interface.
- Administrativo: usuarios, matrizes, criterios de conciliacao, settings locais e painel bloqueado de materialized views.
- Desenvolvimento: inventario tecnico e leitura segura de objetos do banco.
- Resultados consolidados: planejado.
