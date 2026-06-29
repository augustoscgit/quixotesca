# Diretrizes de Desenvolvimento e Segurança - Plataforma RENAST

Este documento centraliza todas as especificações técnicas, padrões de codificação, compatibilidade de banco de dados e requisitos de segurança operacional da Plataforma RENAST.

---

## 1. Separação de Código Público e Privado (Arquitetura)

Para blindar a plataforma contra varreduras de arquivos sensíveis e tentativas de acesso direto via HTTP:

- **Área Pública (`public_html/`)**:
  - Aloja exclusivamente os pontos de entrada executáveis pelo navegador do usuário (`index.php`, `index.html`, telas de visualização dos módulos como `matrizes.php`, `login.php`, `consulta.php`) e ativos estáticos (`css/`, `js/`, imagens, favicons).
  - Nenhuma classe interna de banco de dados, arquivos de credenciais `.env` ou dumps de SQL pode residir sob esta pasta.
- **Área Privada (Raiz do Projeto)**:
  - Todas as pastas do backend dos módulos (`acesso/`, `carex/`, `fichario/`, `ldrt/`, `cat/`, `investigacao/`), helpers de layout (`includes/`) e credenciais reais (`secrets/`) residem fora do Document Root público do servidor.
  - Bancos de dados locais (SQLite) e diretórios de sessão em arquivo devem ser alocados estritamente nas subpastas privadas (ex: `fichario/data/` e `acesso/private/sessions/`).

---

## 2. Inclusões Seguras (Bootstrap e Conexões)

Os pontos de entrada em `public_html/` devem incluir o bootstrap privado do respectivo módulo usando caminhos relativos baseados no diretório do arquivo (`__DIR__`):

```php
// Exemplo em public_html/acesso/login.php
require __DIR__ . '/../../acesso/src/bootstrap.php';
```

O bootstrap privado é responsável por configurar o autoload, carregar as variáveis do `.env` (usando caminhos relativos ao bootstrap), inicializar as sessões e retornar as conexões com o banco de dados.

---

## 3. Padrões de Codificação e Caracteres (Evitar Mojibake)

- **UTF-8 sem BOM**: Todos os arquivos fonte (PHP, JS, CSS e Markdown) devem ser criados e mantidos em UTF-8 sem BOM (Byte Order Mark).
- **Acentuação e Windows/PowerShell**: Ao editar arquivos com acentos por scripts automatizados, PowerShell ou editores locais, certifique-se de forçar a leitura/escrita em UTF-8. Evite comandos que convertam implicitamente os caracteres para encodings de sistema (como Windows-1252), pois isso corrompe a acentuação (gerando *mojibake*).
- **IA e patches de código**: Agentes inteligentes não devem realizar normalização global de acentos junto com mudanças funcionais para não poluir os diffs do Git. Prefira manter a grafia e acentuação existente do arquivo original e valide visualmente qualquer alteração de texto acentuado na interface.

---

## 4. Segurança Operacional e Credenciais

- **Credenciais Isoladas**: Credenciais reais de banco de dados e chaves privadas de API (como Google Client Secret) devem existir apenas em arquivos `.env` privados e isolados na área privada do servidor. O código em PHP não deve conter fallbacks, hosts ou senhas de produção hardcoded.
- **Modo Somente Leitura**: Conexões analíticas de módulos (como o CAREX) devem operar em modo somente leitura por padrão. Quando a variável de escrita (`DB_ALLOW_WRITES`) for falsa, o bootstrap deve forçar políticas de banco como:
  ```sql
  SET default_transaction_read_only TO on;
  SET statement_timeout TO '30000ms';
  SET idle_in_transaction_session_timeout TO '10000ms';
  ```
- **Ambiente de Produção**: Em servidores de produção, certifique-se de configurar `APP_ENV=production` e `APP_DEBUG=false` no arquivo `.env` para desabilitar relatórios de erros detalhados que exponham o código-fonte.

---

## 5. Permissões de Servidor e Segurança no FTP (Locaweb)

Para deploys manuais ou automatizados em hospedagem compartilhada (Locaweb/WinSCP/FileZilla), siga rigorosamente a máscara de permissões:

- **Diretórios públicos e privados**: Máscara `0755` (leitura, escrita e execução para o proprietário; leitura e execução para grupo/outros). O bit de execução é obrigatório para acessar/entrar nas pastas.
- **Arquivos comuns e scripts PHP**: Máscara `0644` (leitura e escrita para o proprietário; leitura para os demais). **Nunca** aplique permissão de execução (`0755`) em arquivos PHP comuns.
- **Pastas graváveis pelo PHP (`private/sessions/`, `data/`)**: Se o PHP falhar ao gravar logs ou sessões, tente primeiro `0755`. Se necessário, use `0775`. Permissões abertas como `0777` são proibidas na área pública e permitidas na área privada apenas como último recurso.
- **Bloqueios de Apache**: Caso a hospedagem impeça a criação de pastas privadas fora da raiz pública, bloqueie o acesso direto via HTTP inserindo um arquivo `.htaccess` nas pastas privadas com a regra:
  ```apache
  Require all denied
  Deny from all
  ```

---

## 6. Gerenciamento Probabilístico de Sessões

Em hospedagens compartilhadas que não limpam automaticamente sessões PHP inativas do disco:
- O bootstrap deve invocar uma rotina probabilística (ex: 1% de chance a cada requisição) para expurgar arquivos de sessões expirados do disco privado.
- **Padrão operacional**: 4 horas de inatividade (`14400` segundos) expira a sessão; arquivos vazios com mais de 15 minutos (`900` segundos) são elegíveis para remoção. A limpeza integrada ao bootstrap não deve bloquear o usuário ativo.

---

## 7. Compatibilidade de Bancos de Dados e APIs

- **MySQL 5.7 e PostgreSQL**: Mantenha as consultas SQL e migrações compatíveis com o PostgreSQL (usado nos módulos CAT e CAREX) e evite comandos SQL que dependam de recursos do MySQL superiores à versão 5.7 (para manter compatibilidade caso o suporte a múltiplos bancos seja reativado).
- **Busca Vetorial e IA**: Não assuma a presença de extensões de banco de dados para busca vetorial em ambientes de produção. Sempre forneça fallbacks baseados em busca textual estruturada (com `LIKE` ou busca indexada por chaves).
- **Controles de LGPD e Indexação**: Páginas administrativas, rotas de login e APIs de dados devem conter metadados e cabeçalhos impedindo indexação por robôs (`meta name="robots" content="noindex, nofollow"`).
