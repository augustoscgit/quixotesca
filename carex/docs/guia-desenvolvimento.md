# Guia de Desenvolvimento CAREX

## Ambiente local

Requisitos:

- PHP 8.1+.
- Extensao `pdo_pgsql`.
- Apache/XAMPP.
- PostgreSQL acessivel.

Passos:

```powershell
Copy-Item .env.example .env
```

Edite `.env` localmente. Nunca versionar credenciais reais.

URLs principais:

```text
http://localhost/quixotesca/public_html/carex/
http://localhost/quixotesca/public_html/carex/desenvolvimento.php
http://localhost/quixotesca/public_html/carex/administrativo.php
http://localhost/quixotesca/public_html/carex/matrizes.php
```

## Validacoes

PHP:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { & C:\xampp\php\php.exe -l $_.FullName }
```

JavaScript:

```powershell
node --check public\assets\app.js
node --check public\assets\admin.js
node --check public\assets\matriz.js
```

Conexao segura:

```powershell
& C:\xampp\php\php.exe tools\check_connection.php
```

Resultado esperado:

```text
read_only=on
```

## Padroes de codigo

- PHP com `declare(strict_types=1);`.
- Namespace `Carex\` para classes em `src/`.
- Sem framework PHP.
- Sem build frontend.
- CSS central em `public/assets/app.css`.
- JS por modulo em arquivos separados.
- Comentarios apenas quando ajudam a entender uma regra nao obvia.

## Padroes de banco

- Use PDO.
- Use parametros preparados para valores.
- Valide identificadores por allowlist antes de montar SQL.
- Nunca concatene tabela/coluna recebida diretamente do usuario.
- Use paginacao e limites.
- Para consulta pesada, use timeout ou carregamento sob demanda.

## Como adicionar endpoint

1. Criar arquivo em `public/api/...`.
2. Incluir `src/bootstrap.php`.
3. Aplicar `Security::applyHeaders()`.
4. Aplicar metodo permitido.
5. Validar parametros.
6. Chamar repositorio.
7. Retornar com `Response::json()`.
8. Documentar em `docs/api.md`.

## Como adicionar tela

1. Reusar `src/templates/navbar.php`.
2. Manter Bootstrap e `public/assets/app.css`.
3. Evitar landing pages desnecessarias para ferramentas internas.
4. Usar skeleton/loading em consultas lentas.
5. Evitar contagens pesadas na carga inicial.

## Antes de entregar

- Rodar lint PHP nos arquivos alterados.
- Rodar `node --check` nos JS alterados.
- Testar via HTTP local.
- Confirmar que endpoints de escrita continuam bloqueados.
- Atualizar documentacao relacionada.
