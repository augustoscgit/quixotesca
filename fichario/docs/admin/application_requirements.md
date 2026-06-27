# Requisitos da aplicacao

## Ambiente base

- PHP 8.1 ou superior recomendado.
- Extensao PDO habilitada.
- Driver `pdo_pgsql` habilitado para o PostgreSQL.
- Servidor Apache do XAMPP ou hospedagem PHP equivalente.
- Permissao de escrita para a pasta `data/` (usada para logs e arquivos de trava) e `private/sessions/` (para sessões).
- Permissao de leitura para `secrets/.env` ou `private/.env`, quando usado.

## Hospedagem compartilhada por FTP

### Estrutura recomendada

- A pasta publica do site deve conter os arquivos PHP acessiveis pelo navegador, assets e rotas da aplicacao.
- Pastas com segredos, banco SQLite, logs e sessoes devem ficar fora da pasta publica sempre que a hospedagem permitir.
- Se a hospedagem nao permitir pasta privada fora do publico, proteger essas pastas por `.htaccess` ou regra equivalente.
- `assets/` deve permanecer publico, pois contem CSS, JavaScript, imagens e favicon.
- `private/`, `private/sessions/`, `data/`, `logs/`, `tmp/` e `cache/` devem ser privados ou bloqueados para acesso direto.
- `docs/` e `system_md/` nao devem conter segredos; em producao, preferir proteger acesso direto ou nao subir `system_md/`.
- `tests/`, `mockup/`, `.git/`, dumps, backups e arquivos compactados nao devem ser enviados para o FTP publico de producao.

### Pastas sensiveis

- `private/`: guardar `.env` e configuracoes sensiveis (ou na pasta `secrets/`).
- `data/`: guardar logs da aplicação e travas de migração (`*.lock`).
- `sessions/`: guardar sessoes PHP se o `session.save_path` padrao da hospedagem nao persistir sessoes corretamente.
- `logs/`: guardar logs diagnosticos temporarios, sem dados sensiveis.
- `system_md/FTP_SECURITY.md`: referencia detalhada sobre tratamento de pastas no FTP.

### Sessoes PHP

- O login depende de sessao PHP para manter o token CSRF entre a abertura do formulario e o envio.
- Se aparecer `Sessao expirada. Recarregue a pagina.` no login, verificar se o cookie `PHPSESSID` persiste.
- Criar um teste temporario de sessao para confirmar se `session_id()` permanece igual e se um contador em `$_SESSION` aumenta ao recarregar.
- Em hospedagem compartilhada, pode ser necessario configurar `session_save_path()` para uma pasta propria gravavel, antes de `session_start()`.
- A pasta de sessoes deve ter permissao de escrita pelo PHP e nao deve ser acessivel publicamente.
- Sem SSL/HTTPS, o cookie de sessao nao pode exigir `Secure`; se `session.cookie_secure` estiver ativo em HTTP, o navegador nao envia `PHPSESSID` e o CSRF falha.
- A aplicacao configura automaticamente `secure=false` em HTTP e `secure=true` em HTTPS, quando o bootstrap atualizado estiver no servidor.
- Criar/enviar a pasta `private/sessions` e garantir permissao de escrita para o usuario do PHP.
- Arquivos de sessao nao devem ser versionados; apenas `private/sessions/.gitkeep` pode ir para o Git para preservar a pasta.
- O ID de sessao deve ser regenerado ao autenticar o usuario, reduzindo risco de fixacao de sessao.
- Sessoes inativas expiram por padrao apos 4 horas (`SESSION_IDLE_TIMEOUT=14400`).
- Arquivos `sess_*` antigos sao removidos automaticamente por coleta de lixo nativa do PHP e por limpeza leve da aplicacao.
- Arquivos de sessao vazios com mais de 15 minutos (`SESSION_EMPTY_FILE_TIMEOUT=900`) podem ser removidos sem impacto esperado.
- A limpeza automatica da aplicacao e probabilistica e limitada por intervalo (`SESSION_APP_GC_INTERVAL=3600`) para evitar varredura de disco em toda requisicao.
- Em producao, ajustar esses valores no `private/.env` conforme a politica de seguranca e usabilidade desejada.

### Cuidados no FTP

- Subir todos os arquivos de codigo, assets publicos e `docs/`.
- Nao subir `.env` real para pasta publica.
- Nao subir dumps, backups compactados, arquivos `.sqlite` de producao, chaves de API ou senhas em Markdown.
- Conferir se a codificacao dos arquivos permanece UTF-8.
- Conferir se a hospedagem esta usando sempre o mesmo dominio/protocolo no login, evitando alternancia entre `http` e `https` ou entre dominio com e sem `www`.

### Permissoes no WinSCP

- Diretorios comuns: `0755`.
- Arquivos comuns, incluindo PHP, CSS, JS, imagens e Markdown: `0644`.
- `.htaccess`: `0644`.
- `secrets/.env` ou `private/.env`: preferir `0600` ou `0640`; se a hospedagem nao permitir leitura pelo PHP, usar `0644` apenas junto com bloqueio por `.htaccess` e pasta protegida.
- `private/sessions/`: precisa ser gravavel pelo PHP; testar `0755`, depois `0775` se necessario.
- `data/`: precisa ser gravável pelo PHP para logs e arquivos de trava.
- Evitar `0777`; usar apenas como ultimo recurso em pasta protegida.
- Nao aplicar `0755` recursivo em todos os arquivos. No WinSCP, tratar pastas e arquivos separadamente: pastas com permissao de execucao, arquivos comuns sem execucao.

## Banco de dados

### PostgreSQL atual

- A versão consolidada atual da plataforma usa PostgreSQL. As configurações de conexão ficam em `secrets/.env` ou `private/.env`.
- PostgreSQL 15.6 e extensoes `unaccent`, `pg_trgm` sao utilizados para busca textual e autocomplete robustos.
- Tabelas para artigos, tags, usuarios e notas estruturadas com índices GIN e relacionamentos em cascata.
- A estrutura de banco de dados é migrada automaticamente na primeira execução através do `bootstrap.php`.

### MySQL 5.7 futuro

- Deve ser tratado como alvo de compatibilidade limitada.
- Nao usar `WITH RECURSIVE`.
- Evitar recursos exclusivos de MySQL 8.
- Planejar consultas hierarquicas de tags sem CTE recursiva.
- Plano detalhado: `docs/developer/mysql_migration_plan.md`.

## APIs externas

- A aplicacao pode usar Gemini ou outra API externa para apoio a IA.
- Nao ha LLM local previsto neste momento.
- Chaves devem ficar em `private/.env` ou outro local fora do Git.

## Seguranca

- Controle de acesso com usuarios e perfis.
- Confirmacao de e-mail e reCAPTCHA quando configurados.
- CSRF obrigatorio em formularios administrativos e acoes POST.
- Arquivos sensiveis devem permanecer ignorados pelo Git.
- `APP_DEBUG=true` deve ser usado apenas em ambiente local. Recursos auxiliares de desenvolvimento, como link direto de recuperacao de senha quando e-mail falha, nao devem aparecer em producao.
- A importacao de BibTeX tem limite de tamanho para evitar envio acidental de conteudo excessivo.
- A extracao de metadados por URL deve aceitar apenas HTTP/HTTPS publico e rejeitar hosts locais, privados ou reservados.

## Privacidade, cookies e LGPD

- A aplicacao usa cookies necessarios de sessao, como `PHPSESSID`, para login, CSRF e seguranca.
- O aviso de cookies deve informar que esses cookies sao essenciais e nao usados para publicidade.
- O aceite/leitura do aviso pode ser armazenado em `localStorage`, evitando criar um cookie adicional apenas para o banner.
- Quando reCAPTCHA, fontes externas ou CDNs estiverem habilitados, informar que provedores externos podem tratar dados tecnicos conforme suas proprias politicas.
- Manter uma pagina publica de privacidade/cookies (`privacy.php`) com linguagem clara.
- Evitar coletar dados pessoais desnecessarios e manter configuracoes sensiveis fora do Git.

## Indexacao e SEO

- A aplicacao injeta metadados tecnicos em HTML, como `description`, `robots`, URL canonica, Open Graph e JSON-LD.
- Esses elementos sao invisiveis para o usuario comum, mas apropriados para mecanismos de busca e previews.
- Nao usar texto escondido, repeticao artificial de palavras-chave ou conteudo diferente do que o usuario ve; isso e pratica ruim de SEO.
- A indexacao publica e controlada por `APP_ALLOW_INDEXING`.
- Por seguranca, paginas administrativas, login, perfil, recuperacao de senha e setup ficam sempre `noindex,nofollow`.
- Antes de ativar `APP_ALLOW_INDEXING=true` em producao, revisar riscos de expor fichamentos, textos copiados, dados pessoais ou conteudo protegido por direito autoral.

## Documentacao administrativa

- A area `admin_docs.php` permite ler e editar documentos Markdown internos.
- A leitura deve ser o modo padrao; a edicao deve ser acionada apenas quando necessario.
- O editor deve ocupar a area principal de leitura para dar espaco real ao conteudo.
- Somente administradores devem acessar a edicao de documentos.
- Os arquivos editaveis ficam em `docs/` e podem ser versionados, desde que nao contenham segredos.

## Versionamento

- O repositorio deve conter codigo, assets publicos e documentacao.
- Nao versionar arquivos `.env`, `private/.env`, `private/sessions/*`, arquivos `.lock`, dumps ou arquivos compactados sensiveis.
