# Diretrizes de Desenvolvimento e Segurança do Workspace

Estas diretrizes são obrigatórias para todo desenvolvimento futuro na plataforma. Elas orientam a arquitetura dos módulos e a separação de responsabilidades para garantir a segurança da plataforma e dos dados.

## 1. Separação de Código Público e Privado (Document Root)

A raiz pública do servidor (Document Root) mapeia exclusivamente o conteúdo da pasta `public_html/`. Todo código PHP lógico, arquivos de configuração, bancos de dados, sessões e chaves secretas devem permanecer fora da pasta pública.

- **Área Pública (`public_html/`)**:
  - Contém exclusivamente pontos de entrada (`index.php`, telas de visualização dos módulos como `matrizes.php`, `login.php`, `consulta.php`) e ativos estáticos (`css/`, `js/`, imagens, favicons).
  - Nenhuma lógica profunda, query SQL direta, conexão ou arquivos `.env` deve residir aqui.
  
- **Área Privada (Raiz do Projeto)**:
  - Todas as pastas lógicas dos módulos (`acesso/`, `carex/`, `fichario/`, `ldrt/`, `cat/`, `investigacao/`), helpers globais (`includes/`) e credenciais (`secrets/`) residem fora de `public_html/`.
  - Bancos de dados locais (como arquivos SQLite `.db` do módulo fichario ou LDRT) devem ser mantidos exclusivamente em subdiretórios privados (ex: `fichario/data/`).
  - Armazenamentos de sessões em arquivo PHP devem ser direcionados para subpastas privadas (ex: `acesso/private/sessions/`).

## 2. Inclusões Seguras (Bootstrap e Conexões)

Os pontos de entrada em `public_html/` devem incluir o bootstrap privado do respectivo módulo usando caminhos relativos baseados no diretório do arquivo (`__DIR__`):

```php
// Exemplo em public_html/acesso/login.php
require __DIR__ . '/../../acesso/src/bootstrap.php';
```

O bootstrap privado é responsável por configurar o autoload, carregar as variáveis do `.env` (usando caminhos relativos ao bootstrap), inicializar as sessões e retornar as conexões com o banco de dados.

## 3. Portabilidade Total (Links Relativos)

Toda e qualquer navegação, requisição de ativos ou carregamento de páginas entre os módulos deve ser realizada usando **links estritamente relativos** (ex: `../acesso/login.php`, `../carex/matrizes.php`, `../assets/favicon.png`).
- Nunca utilize caminhos absolutos locais (como `/quixotesca/...`) ou URLs fixas (como `http://localhost/...`) nas tags HTML ou redirecionamentos HTTP do PHP, garantindo que o código rode idêntico localmente e em produção.

## 4. Localização de Documentação

- Arquivos de documentação Markdown (`.md`) são destinados a desenvolvedores e IAs. Eles **nunca** devem ser inseridos na pasta pública `public_html/`.
- Todos os guias centrais e manuais devem ser armazenados na pasta privada [docs/](file:///c:/xampp/htdocs/quixotesca/docs/) na raiz do projeto ou em subdiretórios privados de documentação dos módulos (ex: `carex/docs/`).
