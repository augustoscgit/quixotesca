# Seguranca de pastas no FTP

Este documento define como as pastas do Fichario Academico devem ser tratadas ao publicar a aplicacao por FTP em hospedagem compartilhada.

## Regra principal

Sempre que a hospedagem permitir, separar o que e publico do que e privado:

- Publico: arquivos PHP acessiveis pelo navegador e `assets/`.
- Privado: configuracoes, banco, sessoes, logs, backups e documentos internos.

Em hospedagem compartilhada que nao permite criar uma pasta privada fora do diretorio publico, proteger as pastas sensiveis com `.htaccess` contendo `Deny from all` ou regra equivalente.

## Pastas publicas

### Raiz da aplicacao

Exemplo: `/fichario/` dentro do FTP publico.

Pode conter:

- `index.php`
- `articles.php`
- `view.php`
- `editor.php`
- `tags.php`
- `tag_view.php`
- demais rotas PHP da aplicacao
- `.htaccess` publico, se houver regra de Apache necessaria

Cuidados:

- Nao colocar `.env` real na raiz publica.
- Nao colocar dumps, backups, `.zip`, `.rar`, `.sql` ou arquivos de sessao.

### `assets/`

Pasta publica. Deve ser acessivel pelo navegador.

Pode conter:

- CSS
- JavaScript
- imagens
- favicon

Cuidados:

- Nao colocar credenciais, tokens ou configuracoes privadas em arquivos JS/CSS.
- Arquivos em `assets/` podem ser vistos por qualquer visitante.

## Pastas privadas ou protegidas

## Permissoes no WinSCP

No WinSCP, tratar arquivos e diretorios separadamente.

### Padrao recomendado

- Diretorios comuns: `0755`.
- Arquivos comuns: `0644`.
- Scripts PHP: `0644`, nao `0755`.
- Arquivos `.htaccess`: `0644`.
- `secrets/.env` ou `private/.env`: preferir `0600` ou `0640`; se a hospedagem nao permitir leitura pelo PHP, usar `0644` apenas junto com bloqueio por `.htaccess` e pasta protegida.
- `data/`: precisa permitir escrita pelo PHP para logs e arquivos de trava.
- `private/sessions/`: precisa permitir escrita pelo PHP.

### Como interpretar `0755`

`0755` significa:

- proprietario: ler, escrever e executar;
- grupo: ler e executar;
- outros: ler e executar.

Para diretorios, o `X` de executar e necessario para entrar/listar a pasta. Por isso `0755` e normal para pastas publicas.

Para arquivos, `X` geralmente nao e necessario. Por isso arquivos PHP, CSS, JS, imagens e Markdown normalmente ficam em `0644`.

### Quando usar `0775` ou `0777`

Se `private/sessions/` ou `data/` nao conseguirem gravar:

1. tentar `0755`;
2. se falhar, tentar `0775`;
3. usar `0777` apenas como ultimo recurso e somente em pasta protegida, nunca em pasta publica sem bloqueio.

Em hospedagem compartilhada, muitas vezes o PHP roda como o mesmo usuario do FTP. Nesse caso `0755` para pastas e `0644` para arquivos costumam funcionar.

### Opcao "Adicionar X aos diretorios"

No WinSCP, ao aplicar permissoes em lote:

- para pastas, `X` deve ficar ativo;
- para arquivos comuns, nao marcar execucao;
- evitar aplicar `0755` recursivo em tudo, porque isso deixa arquivos comuns executaveis sem necessidade.

Aplicar permissoes em lote com cuidado:

- pastas: `0755`;
- arquivos: `0644`;
- pastas gravaveis pelo PHP: testar `0755`, depois `0775` se necessario.

### `private/`

Deve ficar fora da pasta publica quando possivel.

Conteudo esperado:

- `private/.env`
- configuracoes sensiveis
- subpastas privadas, como `private/sessions/`

Se precisar ficar dentro da pasta publica, manter `.htaccess` bloqueando acesso direto.

Nao versionar:

- `private/.env`
- `private/*.env`
- qualquer arquivo com senha, token, chave de API ou credencial.

### `private/sessions/`

Deve ser gravavel pelo PHP e inacessivel publicamente.

Uso:

- armazenar sessoes PHP quando a hospedagem compartilhada nao mantem bem o `session.save_path` padrao.

Seguranca:

- Nao versionar arquivos `sess_*`.
- Versionar apenas `private/sessions/.gitkeep`, se necessario, para preservar a pasta vazia.
- Confirmar permissao de escrita no FTP/painel da hospedagem.
- Manter coleta automatica de sessoes ativa. A aplicacao usa `SESSION_IDLE_TIMEOUT` para expirar sessoes inativas e remove arquivos antigos de forma probabilistica.
- Padrao atual: 4 horas de inatividade (`14400` segundos), arquivos vazios removiveis apos 15 minutos (`900` segundos) e limpeza no maximo uma vez por hora.
- Em hospedagem compartilhada, evitar cron dependente do servidor quando nao houver acesso; a limpeza integrada ao bootstrap e suficiente para baixo volume.
- Em sites com maior movimento, pode-se complementar com cron/painel da hospedagem chamando uma rotina CLI propria, mas nunca por URL publica sem controle de acesso.
- Nao limpar manualmente a sessao ativa em uso; remova apenas `sess_*` antigos ou vazios.

### `data/`

Deve ficar fora da pasta publica quando possivel.

Conteudo esperado:

- logs de erros, como `data/php_errors.log`
- arquivos auxiliares de dados locais e travas de migração (`*.lock`)

Se precisar ficar dentro da pasta publica, manter `.htaccess` bloqueando acesso direto.

Nao versionar:

- arquivos de trava `.lock` locais
- logs de erros e backups do banco.

### `logs/`, `tmp/`, `cache/`

Devem ser privadas ou protegidas.

Uso:

- diagnostico temporario
- arquivos de cache
- arquivos gerados em execucao

Seguranca:

- Nao registrar senhas, tokens, textos sensiveis ou dados pessoais desnecessarios.
- Nao versionar.
- Limpar periodicamente.

## Pastas de documentacao

### `docs/`

Pode ser versionada, mas deve ser tratada como documentacao interna.

Recomendacao de FTP:

- Se a hospedagem permitir, proteger acesso direto a `docs/`.
- A aplicacao deve exibir/editar esses arquivos pela area administrativa, nao por acesso direto ao Markdown.

Cuidados:

- Nunca colocar senhas, chaves, tokens, credenciais, dumps ou dados pessoais sensiveis em Markdown.

### `system_md/`

Documentacao de sistema, design e decisoes tecnicas.

Recomendacao de FTP:

- Nao e necessaria para a execucao publica da aplicacao.
- Preferir nao subir para a hospedagem de producao.
- Se for subida, proteger acesso direto.

Cuidados:

- Nao deve conter segredos.
- Pode conter orientacoes tecnicas que nao precisam ficar publicas.

### `tests/`

Pasta de testes locais.

Recomendacao de FTP:

- Nao subir para producao, salvo necessidade temporaria de diagnostico.
- Se for subida, proteger acesso direto.

## Pastas que nao devem ir para o FTP de producao

Estas pastas/arquivos nao sao necessarios para o funcionamento publico da aplicacao:

- `.git/`
- `mockup/`
- `system_md/`, salvo se houver motivo administrativo
- `tests/`, salvo diagnostico temporario
- arquivos compactados como `*.zip`, `*.rar`, `*.7z`
- backups como `*.bak`, `*.backup`
- bancos locais e dumps

## Checklist antes de subir por FTP

- `private/.env` existe no servidor, mas nao esta em pasta acessivel publicamente.
- `private/sessions/` existe e e gravavel pelo PHP.
- `data/` existe, e protegida ou fora do publico.
- arquivos `.env` e `.lock` nao podem ser baixados por URL.
- `private/sessions/sess_*` nao pode ser baixado por URL.
- `APP_DEBUG=false` em producao.
- `APP_URL` aponta para o dominio real e protocolo usado.
- `APP_ALLOW_INDEXING` foi revisado antes da publicacao. Manter `false` enquanto houver risco de expor fichamentos, textos copiados, dados pessoais ou paginas internas.
- A pagina `privacy.php` esta publicada e o aviso de cookies aparece para novos navegadores.
- Nao ha `.git/`, dumps, backups ou arquivos compactados sensiveis no FTP publico.

## Cookies, LGPD e indexacao

- O sistema usa cookie de sessao para login e seguranca. Esse uso deve ser informado de forma clara ao usuario.
- O aviso de cookies deve explicar que os cookies essenciais nao sao de publicidade.
- Se reCAPTCHA, Google Fonts, CDNs ou APIs externas forem ativados, mencionar que provedores externos podem tratar dados tecnicos.
- A indexacao por mecanismos de busca deve usar metadados legitimos: `meta description`, `robots`, URL canonica, Open Graph e JSON-LD.
- Nao usar texto oculto de palavras-chave ou conteudo invisivel diferente do conteudo real da pagina.
- Paginas administrativas e de autenticacao devem permanecer `noindex,nofollow`.
