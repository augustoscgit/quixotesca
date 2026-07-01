# Fichario Academico

## Documentacao visual e tema

As regras de tema, CSS, Bootstrap, navbar, botoes, tags, nuvem de palavras, grafo e contraste do Fichario ficam centralizadas em:

- `../docs/identidade-visual-ux.md`
- `../docs/tema-css-bootstrap-modulos.md`

O Fichario pode manter CSS local para layout e visualizacoes (`public_html/fichario/assets/app.css` e `public_html/fichario/assets/tag-visualizations.css`), mas paleta, tags, navbar e tema claro devem obedecer ao guia central Bootstrap-first.

Sistema pessoal de fichamento de artigos academicos, com cadastro bibliografico, texto completo em texto simples, referencias, tags tematicas, comentarios e base em PostgreSQL.

## Stack atual

- PHP 8.x
- Bootstrap 5
- PostgreSQL 15+ via PDO
- Apache/XAMPP no desenvolvimento local

## Configuracao local

1. Coloque o projeto em `htdocs/quixotesca/fichario`.
2. Copie `.env.example` para `secrets/.env`.
3. Ajuste as credenciais de banco de dados (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SCHEMA`).
4. Garanta que o modulo `acesso` esteja configurado e com o administrador inicial criado.
5. Atribua ao usuario as permissoes `fichario.access` e, quando necessario, `fichario.admin` no modulo Acesso.

## Arquivos locais e segredos

Nao versionar:

- `secrets/.env` ou `private/.env`
- `private/sessions/`
- arquivos `.lock` de migração
- chaves, certificados, senhas e arquivos temporarios

As pastas `secrets/`, `private/` e `data/` possuem `.htaccess` para bloquear acesso direto via Apache.

## Banco de dados

A versão consolidada atual usa PostgreSQL. As configurações de conexão ficam em:

```text
secrets/.env
```

A estrutura é migrada automaticamente por `bootstrap.php` apenas quando `FICHARIO_ALLOW_AUTO_MIGRATIONS=true` ou quando o ambiente nao for `production` e a variavel estiver ausente. Em publicacao FTP contra banco de producao, mantenha `FICHARIO_ALLOW_AUTO_MIGRATIONS=false` e nao apague arquivos `.lock` remotos sem backup e janela operacional.

## Cadastro bibliografico e referencias

O cadastro de artigo mantem metadados bibliograficos, texto completo em texto simples, lista de referencias do artigo, BibTeX original e uma referencia ABNT curada.

- `bibtex_raw`: armazena o BibTeX original quando informado no cadastro ou via importador.
- `bibtex_key`: chave extraida automaticamente do BibTeX, quando possivel.
- `reference_abnt`: referencia ABNT usada em visualizacao e exportacao.
- `reference_abnt_locked`: indica que a referencia foi validada/editada manualmente e nao deve ser recalculada automaticamente.
- `reference_abnt_missing`: lista pendencias de metadados para ABNT completa, como paginas, DOI/URL ou fonte.

Fluxo recomendado:

1. Cadastre ou importe metadados do artigo.
2. O sistema gera uma referencia ABNT inicial.
3. Revise pendencias apontadas pelo formulario.
4. Se colar uma referencia completa do Google Academico ou de gerenciador bibliografico, clique em **Travar versao validada**.
5. Use **Destravar e recalcular** quando quiser voltar a gerar a ABNT pelos metadados atuais.

## Projetos e exportacao para agente de IA

Cada projeto organiza marcações vinculadas em secoes com contexto proprio. A pagina do projeto possui o botao **Exportar**, que gera um pacote `.zip` para uso em ChatGPT, Claude, agentes RAG ou fluxos de redacao assistida.

A especificacao do pacote fica em `docs/admin/project_agent_export.md`, editavel pelo painel administrativo de documentacao.

As orientacoes gerais do agente ficam em `docs/admin/default_agent_instructions.md`. A funcao `default_project_agent_instructions()` usa o trecho entre os marcadores `agent-default:start` e `agent-default:end` como padrao operacional. Na interface do projeto, esse texto aparece como padrao editavel; quando o usuario personaliza, o texto fica salvo em `projects.agent_instructions` e passa a ser incluido no pacote exportado.

## Testes rapidos

Para validar sintaxe dos arquivos PHP principais no PowerShell, a partir da pasta do projeto:

```powershell
C:\xampp\php\php.exe -l bootstrap.php
C:\xampp\php\php.exe -l ..\public_html\fichario\editor.php
C:\xampp\php\php.exe -l ..\public_html\fichario\project.php
C:\xampp\php\php.exe -l ..\public_html\fichario\export_project.php
C:\xampp\php\php.exe -l ..\public_html\fichario\view.php
```

## Segurança de Acesso e Proteção de Dados

Para garantir a privacidade e restrição de acesso aos conteúdos protegidos, o sistema aplica as seguintes regras para usuários não logados (público):
- **Ocultação de Texto Completo**: O campo `full_text` do banco de dados é omitido de todas as queries de busca e cálculos de pontuação de relevância. Não é possível encontrar artigos buscando por termos contidos exclusivamente em seu texto completo.
- **Filtros e Parâmetros Restritos**: Filtros avançados como ordenar por volume de texto (`text_desc`) ou filtrar por artigos com/sem texto completo (`with_text`, `without_text`) são desativados no formulário HTML e limpos na entrada do backend.
- **Métricas de Leitura Ocultas**: O indicador de contagem de palavras do texto completo e do artigo completo são substituídos por um cálculo exclusivo de metadados públicos (`Palavras (Metadados)`), que desconsidera o texto completo.
- **Painéis e Abas Bloqueados**: A aba "Texto Completo", o painel de "Leitura Focada" e o botão correspondente no cabeçalho não são renderizados no DOM, impedindo vazamentos ou carregamento de dados confidenciais na página.

## Usabilidade e Layout Premium

- **Persistência de Busca**: Os critérios de filtros da busca inteligente (palavras-chave, tags, ordenação, etc.) são salvos na sessão do usuário (`$_SESSION['articles_filter']`). Ao sair da página de listagem e retornar, o filtro anterior é mantido automaticamente. Para limpar, clique no botão "Limpar".
- **Overlay de Carregamento**: Implementação de um indicador visual de carregamento (ampulheta em ouro/âmbar com animação e efeito de desfoque *glassmorphism* no fundo) que impede cliques múltiplos durante form submits, AJAX lento ou carregamento de páginas.
- **Regras Customizadas de Alerta (`!`)**: Ícones de alerta visual ativo são exibidos para artigos que não contenham as tags recomendadas (Objetivo, Método, Fonte). Caso o Método do artigo seja classificado como "ensaio" ou "revisão", a exigência de uma tag de Fonte é automaticamente ignorada.

## Preparação para Git e Deploy Seguro

Antes de realizar o push para produção ou disponibilizar publicamente, certifique-se de realizar o seguinte checklist de segurança:

1. **Ignorar Segredos e Travas no Git**:
   - `secrets/.env` e `private/.env` **nunca** devem ser versionados.
   - Os arquivos de trava de migração (`*.lock`) locais não devem ser enviados para o servidor de produção.
   - A pasta `private/sessions/` de sessões locais deve conter apenas o arquivo `.gitkeep` para manter a estrutura.
2. **Proteção de Pastas Críticas via Servidor (Apache/Nginx)**:
   - Certifique-se de que os arquivos `.htaccess` contendo `Require all denied` estejam presentes e ativos nas pastas `/data`, `/private` e `/secrets`.
   - Se o servidor for **Nginx**, configure blocos de restrição equivalentes no `nginx.conf`:
     ```nginx
     location ~ ^/(data|private|secrets) {
         deny all;
         return 403;
     }
     ```
3. **HTTPS Obrigatório**:
   - Configure a diretiva `APP_URL` com `https://` no arquivo `.env` para garantir a geração de URLs seguras e o tráfego criptografado de dados de login e sessões.
4. **Permissões de Arquivos no Servidor**:
   - Garanta permissão de escrita (`chmod 775` ou equivalente do usuário de execução do PHP/Apache) para o diretório `/private/sessions` e `/data` (caso arquivos de migração ou logs precisem ser gerados) para gravação de sessões, mantendo o restante dos scripts PHP apenas com permissão de leitura.
