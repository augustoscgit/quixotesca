# Preparacao para Git

Este projeto esta preparado para versionamento, mas o ambiente atual pode nao ter `git` instalado.

## Antes do primeiro commit

1. Confirme que `git` esta instalado:

```powershell
git --version
```

2. Confirme que arquivos sensiveis nao aparecem:

```powershell
git status --short
```

Nunca versionar:

- `.env`
- `.env.*`
- dumps/backups SQL
- logs
- `chrome-debug-profile/`
- `vendor/`
- `node_modules/`
- arquivos compactados temporarios

## Inicializacao sugerida

```powershell
git init
git add .gitattributes .gitignore .editorconfig README.md docs public src config assets landing.md sobre.md criterios-conciliacao.md index.php editor.php
git status --short
```

Revise cuidadosamente o resultado antes de commitar.

## Commit inicial sugerido

```powershell
git commit -m "Initial CAREX application structure"
```

## Branches

Sugestao:

- `main`: linha estavel.
- `develop`: evolucao controlada.
- `feature/<nome>`: funcionalidades.
- `hotfix/<nome>`: correcao urgente.

## Checklist de seguranca antes de push

```powershell
Get-ChildItem -Recurse -File | Select-String -Pattern 'DB_PASSWORD=|DB_HOST=|senha-real|host-real'
```

Resultado aceitavel: valores reais apenas em `.env`, que nao deve estar versionado. `.env.example` deve conter somente placeholders.

Tambem confirme:

```powershell
& C:\xampp\php\php.exe tools\check_connection.php
```

Resultado esperado: `read_only=on`.

## GitHub

Ao publicar no GitHub:

- Configure o repositorio como privado enquanto houver duvida sobre credenciais historicas.
- Ative secret scanning.
- Proteja `main`.
- Exija pull request para alteracoes.
- Nunca adicione secrets como arquivos; use GitHub Actions Secrets quando necessario.

## Producao sem backup

Nao aceite PRs que:

- removam `default_transaction_read_only`;
- habilitem `DB_ALLOW_WRITES=true`;
- adicionem SQL destrutivo;
- exponham `.env`;
- removam `.htaccess` de protecao;
- adicionem endpoint `POST` sem CSRF e sem autorizacao.
