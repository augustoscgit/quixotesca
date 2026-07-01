# Plano de migracao para MySQL 5.7 ou superior

## Objetivo

Este documento avalia a viabilidade de migrar a base SQLite atual do Fichario Academico para MySQL 5.7 ou superior, com foco em hospedagem compartilhada Locaweb/Percona 5.7, preservacao da versao SQLite e possibilidade futura de ida e volta entre bancos.

## Conclusao preliminar

A migracao e viavel, mas MySQL 5.7 deve ser tratado como alvo de compatibilidade limitada. Ele resolve melhor hospedagem compartilhada, concorrencia e operacao remota, mas perde simplicidade local do SQLite e nao oferece recursos modernos como `WITH RECURSIVE`, window functions e alguns recursos robustos de JSON/DDL presentes em versoes mais novas.

Para a aplicacao atual, o ponto mais sensivel nao e armazenar artigos ou marcações. O ponto sensivel e a navegacao hierarquica de tags, pois hoje algumas consultas usam CTE recursiva no SQLite. Em MySQL 5.7, sera necessario substituir esse padrao por uma estrategia propria.

## Escopo da base atual

Tabelas centrais:

- `articles`: metadados, resumo, texto completo, referencias e campos bibliograficos.
- `tags`: vocabulario controlado de temas, fontes e metodos.
- `tag_hierarchy`: relacao pai/filho entre tags.
- `article_tag_quotes`: marcações; conceitualmente uma marcação tem artigo, citacao opcional e observacao opcional.
- `article_quote_tags`: relacao N:N entre marcações e tags.
- `users`: usuarios, perfis e confirmacao/autenticacao.
- `article_tags`: atualmente e uma view de compatibilidade sobre marcações e tags.

## Compatibilidade por versao

### MySQL 5.7 / Percona 5.7

Disponivel na hospedagem informada: Percona Server 5.7.32.

Pontos positivos:

- Suporta InnoDB, chaves estrangeiras, indices e transacoes.
- Suporta `FULLTEXT` em InnoDB.
- Suporta `GROUP_CONCAT`.
- Suporta `utf8mb4` e collations case/accent-insensitive em muitas configuracoes.
- Boa compatibilidade com hospedagem compartilhada via phpMyAdmin e PDO MySQL.

Limitacoes importantes:

- Nao suporta `WITH RECURSIVE`.
- Nao suporta window functions.
- `CHECK` constraints sao ignoradas em MySQL 5.7.
- JSON existe, mas e menos util do que em MySQL 8.
- DDL e migrations sao menos confortaveis que no SQLite atual.
- `FULLTEXT` tem regras proprias de tokenizacao, stopwords e tamanho minimo de palavra.

### MySQL 8.0 ou superior

Melhor alvo tecnico, se disponivel.

Vantagens sobre 5.7:

- `WITH RECURSIVE`, facilitando hierarquia de tags.
- Window functions.
- Melhor suporte a JSON.
- Collations modernas como `utf8mb4_0900_ai_ci`.
- Melhor plano de execucao e mais recursos de diagnostico.

Mesmo com MySQL 8, ainda sera necessario isolar dialetos SQL para preservar SQLite.

### MariaDB local do XAMPP

O ambiente local informado usa MariaDB 10.4.32. Ele pode ser usado para testes, mas nao deve ser considerado identico ao Percona/MySQL 5.7 da hospedagem.

Cuidados:

- MariaDB e MySQL divergem em detalhes de sintaxe, otimizador, collations e DDL.
- Se o alvo de producao for MySQL/Percona 5.7, os testes locais devem evitar recursos disponiveis no MariaDB 10.4 mas ausentes no MySQL 5.7.

## Principais diferencas tecnicas

### Tipos e colunas

SQLite usa tipagem flexivel; MySQL exige definicoes mais claras.

Mapeamento inicial:

- `INTEGER PRIMARY KEY AUTOINCREMENT` -> `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`.
- `TEXT` -> `TEXT`, `MEDIUMTEXT` ou `LONGTEXT`, conforme campo.
- `created_at TEXT DEFAULT CURRENT_TIMESTAMP` -> `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`.
- `updated_at TEXT` -> `DATETIME`, idealmente atualizado pela aplicacao ou `ON UPDATE CURRENT_TIMESTAMP` quando fizer sentido.
- campos longos como `full_text`, `references_text`, `bibtex_raw`, `quote_text`, `comment` devem ser `MEDIUMTEXT` para evitar truncamento futuro.

### Charset e collation

Usar `utf8mb4` em todas as tabelas.

Sugestao para MySQL 5.7:

- charset: `utf8mb4`;
- collation: `utf8mb4_unicode_ci` ou a collation disponivel mais adequada.

Isso ajuda busca case-insensitive e parcialmente accent-insensitive. Mesmo assim, a funcao PHP `search_normalize()` deve continuar existindo para comportamento previsivel entre bancos.

### Chaves estrangeiras

No SQLite, `PRAGMA foreign_keys = ON` ativa cascatas. No MySQL, as cascatas dependem de InnoDB.

Todas as tabelas relacionais devem usar `ENGINE=InnoDB`.

Riscos:

- Se alguma tabela for criada como MyISAM, chaves estrangeiras nao funcionam.
- Hospedagem compartilhada pode impor configuracoes padrao; scripts de criacao devem declarar `ENGINE=InnoDB`.

### Views

A view `article_tags` pode ser mantida por compatibilidade:

- no SQLite: `CREATE VIEW IF NOT EXISTS`;
- no MySQL: `CREATE OR REPLACE VIEW`, com cuidado em permissoes.

Fragilidade:

- Em hospedagem compartilhada, criacao de view pode depender de privilegios.
- Se view for problema, criar uma camada PHP que substitua a compatibilidade sem depender de view.

### Comandos SQL que mudam

Substituicoes provaveis:

- `INSERT OR IGNORE` -> `INSERT IGNORE` ou `INSERT ... ON DUPLICATE KEY UPDATE`.
- `COLLATE NOCASE` -> collation da coluna ou `LOWER(...)`.
- `char(10)` -> `CHAR(10)`.
- `length(...)` -> `CHAR_LENGTH(...)` para caracteres ou `LENGTH(...)` para bytes.
- `PRAGMA table_info` -> `INFORMATION_SCHEMA.COLUMNS`.
- `lastInsertId()` continua via PDO.

## Hierarquia de tags sem WITH RECURSIVE

Este e o maior ponto de atencao para MySQL 5.7.

Hoje o filtro de artigos por tag usa descendentes via CTE recursiva. Em MySQL 5.7, ha quatro caminhos possiveis:

### Opcao A: closure table

Criar tabela `tag_closure`:

- `ancestor_id`
- `descendant_id`
- `depth`

Exemplo:

- Qualidade -> Qualidade, depth 0
- Qualidade -> Cobertura, depth 1
- Qualidade -> Completude, depth 1

Vantagens:

- Consulta muito rapida.
- Funciona bem em MySQL 5.7, SQLite e PostgreSQL.
- Facilita grafo, navegacao e filtros.

Desvantagens:

- Ao editar filiacao, precisa recalcular closure table.
- Mais uma tabela para manter consistente.

Esta e a opcao recomendada.

### Opcao B: caminho materializado

Guardar caminho textual ou numerico na tag, por exemplo `/13/15/`.

Vantagens:

- Simples para buscas de descendentes por prefixo.

Desvantagens:

- Dificil com multiplos pais.
- Mais fragil para reorganizacao do vocabulario.

Nao e ideal para o modelo atual, que permite filiacoes multiplas.

### Opcao C: resolver hierarquia em PHP

Buscar `tag_hierarchy` inteira e calcular descendentes em PHP.

Vantagens:

- Simples para bases pequenas.
- Funciona igual em todos os bancos.

Desvantagens:

- Pode ficar custoso se a taxonomia crescer muito.
- Exige cache para desempenho consistente.

Pode servir como fase intermediaria.

### Opcao D: limitar MySQL 5.7 e exigir MySQL 8+

Usar `WITH RECURSIVE` apenas em MySQL 8+.

Vantagem:

- Menos mudanca conceitual.

Desvantagem:

- Nao atende a hospedagem compartilhada atual com MySQL/Percona 5.7.

## Busca e desempenho

### Possiveis ganhos com MySQL

MySQL pode melhorar:

- acesso concorrente com varios usuarios;
- confiabilidade em hospedagem compartilhada;
- consultas filtradas com indices adequados;
- pagina de artigos quando a base crescer;
- backups e administracao via painel/phpMyAdmin.

Indices recomendados:

- `articles(year)`;
- `articles(journal)`;
- `articles(created_at)`;
- `articles(title)`;
- `tags(name)`;
- `tags(category)`;
- `tag_hierarchy(parent_id, child_id)`;
- `tag_hierarchy(child_id)`;
- `article_tag_quotes(article_id)`;
- `article_quote_tags(tag_id, quote_id)`;
- `article_quote_tags(quote_id, tag_id)`;
- `users(email)`;
- `users(status)`;
- `users(email_verification_token)`;
- `users(password_reset_token)`.

### FULLTEXT

MySQL 5.7 suporta `FULLTEXT` em InnoDB. Pode ser avaliado para:

- `articles(title, authors, journal, keywords, abstract, full_text, references_text)`;
- talvez marcações em `article_tag_quotes(quote_text, comment)`.

Fragilidades:

- stopwords podem prejudicar portugues;
- tamanho minimo de termo pode ignorar palavras curtas;
- ranking do MySQL pode nao corresponder ao score manual atual;
- textos academicos longos podem exigir ajuste de `innodb_ft_min_token_size`, normalmente impossivel em hospedagem compartilhada.

Recomendacao:

- manter a busca atual por `LIKE` normalizado no primeiro ciclo;
- adicionar FULLTEXT como melhoria opcional depois de testes reais.

### Campos normalizados de busca

Como MySQL nao tera a funcao SQLite `search_norm`, considerar:

- normalizar no PHP no momento de salvar;
- criar colunas auxiliares como `title_search`, `abstract_search`, `full_text_search`;
- indexar campos curtos normalizados;
- evitar indexar integralmente campos muito longos sem necessidade.

Isso aumenta complexidade, mas melhora portabilidade e previsibilidade.

## Camada de acesso a dados

Antes de migrar, recomenda-se reduzir SQL espalhado em arquivos PHP.

Plano:

- criar configuracao `DB_CONNECTION=sqlite|mysql`;
- centralizar DSN em `bootstrap.php`;
- criar helpers simples para diferencas de dialeto;
- mover queries mais complexas para funcoes/repositórios;
- isolar operacoes de hierarquia de tags;
- evitar SQL especifico de SQLite em novas telas.

Nao e necessario criar um framework pesado. Basta uma camada fina para:

- conexao;
- migrations;
- placeholders e execucao;
- funcoes de dialeto;
- busca de descendentes de tags.

## Estrategia de migracao

### Fase 1: preparacao sem trocar banco

- Documentar schema conceitual atual.
- Remover dependencia de `article_tags` como fonte principal, mantendo-a apenas como view de compatibilidade.
- Isolar consultas com `WITH RECURSIVE`.
- Criar testes de integridade para marcações, tags e exclusao em cascata.
- Criar exportador neutro em JSON/NDJSON ou CSV com ordem consistente.

### Fase 2: schema MySQL

- Criar script `schema_mysql_57.sql`.
- Usar `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`.
- Substituir `CHECK` por validacao na aplicacao.
- Definir `MEDIUMTEXT` para textos longos.
- Criar indices.
- Avaliar view `article_tags` ou substituicao por consulta PHP.

### Fase 3: importacao inicial

- Exportar SQLite para formato neutro.
- Importar em MySQL respeitando ordem:
  - usuarios;
  - artigos;
  - tags;
  - hierarquia;
  - marcações;
  - vinculos marcação-tag.
- Preservar IDs sempre que possivel para manter URLs internas.
- Recriar `AUTO_INCREMENT` apos importacao.
- Rodar validacoes de contagem e integridade.

### Fase 4: compatibilidade da aplicacao

- Implementar `DB_CONNECTION=mysql`.
- Ajustar queries por dialeto.
- Substituir hierarquia recursiva por closure table ou resolucao em PHP.
- Testar telas principais:
  - lista de artigos;
  - filtro por tag pai e filhos;
  - visualizacao de artigo;
  - criacao/edicao/exclusao de marcações;
  - administracao de tags;
  - login e usuarios.

### Fase 5: homologacao

- Rodar a mesma base em SQLite e MySQL.
- Comparar contagens:
  - total de artigos;
  - total de tags;
  - total de marcações;
  - total de vinculos marcação-tag;
  - total de relacoes hierarquicas;
  - artigos retornados por filtros de tags importantes.
- Fazer backup antes de qualquer deploy.
- Subir em ambiente de teste da hospedagem antes da virada.

### Fase 6: virada controlada

- Congelar escrita temporariamente.
- Exportar SQLite final.
- Importar MySQL.
- Rodar validacoes.
- Alterar `private/.env` para `DB_CONNECTION=mysql`.
- Testar login, lista, visualizacao e edicao.
- Manter backup SQLite intacto.

## Ida e volta entre SQLite e MySQL

Se a intencao e preservar uma versao SQLite e outra MySQL, nao depender de dumps SQL especificos como formato principal.

Formato recomendado para intercambio:

- `export/articles.ndjson`
- `export/tags.ndjson`
- `export/tag_hierarchy.ndjson`
- `export/article_tag_quotes.ndjson`
- `export/article_quote_tags.ndjson`
- `export/users.ndjson` somente se necessario e com cuidado extra.

Vantagens:

- independente de dialeto;
- preserva IDs;
- facilita comparacao;
- permite testes automatizados.

Cuidados:

- senhas e tokens de usuarios sao dados sensiveis;
- exports nao devem ir para Git;
- textos completos podem conter conteudo protegido ou sensivel;
- dumps devem ficar fora da pasta publica.

## Possiveis problemas e fragilidades

- MySQL 5.7 nao suporta `WITH RECURSIVE`; filtros hierarquicos precisam ser redesenhados.
- MySQL 5.7 ignora `CHECK`; validacoes de `role`, `status` e regras similares precisam ficar na aplicacao.
- Diferencas de collation podem alterar ordenacao e busca sem acento.
- `FULLTEXT` pode nao funcionar bem para portugues sem ajustes de servidor.
- Hospedagem compartilhada pode limitar tamanho de conexao, tempo de execucao, importacao e privilegios de view.
- Textos longos podem exceder `TEXT`; usar `MEDIUMTEXT` nos campos certos.
- `GROUP_CONCAT` tem limite configuravel; marcações agregadas podem truncar se o limite for baixo.
- Transacoes dependem de InnoDB.
- Backups e restores por phpMyAdmin podem falhar em bases grandes.
- O ambiente local MariaDB pode aceitar SQL que o Percona 5.7 de producao nao aceita.
- A aplicacao hoje tem SQL espalhado em varias paginas; migrar sem isolar dialeto aumenta risco de bugs.
- Preservar IDs e URLs e importante; se IDs mudarem, links antigos quebram.

## Oportunidades de melhoria

- Melhor concorrencia para multiplos usuarios.
- Melhor administracao remota por painel da hospedagem.
- Indices mais previsiveis para lista de artigos, tags e marcações.
- Possibilidade de `FULLTEXT` para busca textual, se os testes forem bons.
- Closure table de tags melhora desempenho em filtros, navegacao e futuros grafos.
- Separacao clara entre modelo conceitual e nomes legados do banco.
- Criacao de testes de paridade entre SQLite e MySQL.
- Preparacao para uma camada futura PostgreSQL sem reescrever a aplicacao inteira.

## Recomendacao tecnica

Nao migrar diretamente a aplicacao inteira para MySQL 5.7 sem uma fase de preparacao.

Sequencia recomendada:

1. Manter SQLite como versao estavel.
2. Isolar consultas especificas de banco.
3. Implementar closure table de tags ou resolver descendentes em PHP.
4. Criar export/import neutro.
5. Criar schema MySQL 5.7.
6. Testar localmente com cuidado, evitando recursos de MariaDB ausentes em MySQL 5.7.
7. Homologar na hospedagem compartilhada.
8. So entao ativar `DB_CONNECTION=mysql` em producao.

Para MySQL 5.7, a melhoria mais estrategica antes da migracao e criar uma tabela `tag_closure`. Ela remove a dependencia de CTE recursiva, melhora desempenho dos filtros e prepara a aplicacao para grafos de relacao entre tags.
