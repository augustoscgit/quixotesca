# Modulo de Desenvolvimento CAREX

Este modulo e a area tecnica inicial do CAREX. Ele existe para apoiar evolucao, auditoria e entendimento da base antes da construcao dos demais modulos: administrativo, trabalho e resultados Herdados.

## Escopo atual

- Consultar tabelas, views e materialized views do schema `carex` em modo somente leitura.
- Exibir linhas com paginacao, busca textual e ordenacao por colunas reais.
- Mapear objetos tecnicos da base: tabelas, views, materialized views, triggers, rotinas, sequencias, indices, constraints e tipos.
- Navegar pelo inventario e pelos dados em abas com contadores por tipo de objeto.
- Abrir os dados diretamente a partir das linhas de tabelas, views e materialized views no inventario.
- Manter credenciais fora do Git por meio de `.env`.

## Modulos previstos

| Modulo | Estado | Papel |
| --- | --- | --- |
| Desenvolvimento | Em desenvolvimento | Inventario tecnico, leitura de tabelas e apoio a manutencao. |
| Administrativo | Primeiro corte implementado | Especialistas e vinculacoes com matrizes. |
| Trabalho | Primeiro corte implementado | Boxes de matrizes e indicadores iniciais de classificacao. |
| Resultados Herdados | Planejado | Consultas consolidadas, indicadores e visoes finais para analise. |

## Endpoints do modulo

| Endpoint | Metodo | Descricao |
| --- | --- | --- |
| `/public/api/tables.php` | GET | Lista tabelas base e suas colunas. |
| `/public/api/rows.php` | GET | Retorna registros paginados de uma tabela validada. |
| `/public/api/development/objects.php` | GET | Retorna inventario dos objetos tecnicos do schema. |

## Padroes de seguranca aplicados

- Apenas `GET` e `HEAD` sao aceitos nos endpoints atuais.
- Tabelas e colunas sao validadas contra `information_schema` antes de qualquer consulta.
- Valores de busca, pagina e limite usam parametros preparados.
- Identificadores SQL sao escapados e aceitos apenas depois de validacao por allowlist.
- `.env`, `src`, `config` e `tools` sao bloqueados no Apache.
- Headers HTTP incluem CSP, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` e `Permissions-Policy`.
- `.env.example` documenta variaveis sem expor a senha real.

## Inventario de objetos

O inventario e montado por catalogos nativos do PostgreSQL:

- `pg_class`, `pg_namespace` e `pg_stat_user_tables` para relacoes e estimativas de linhas.
- `pg_views` e `pg_matviews` para definicoes de views.
- `pg_trigger`, `pg_proc` e `pg_get_triggerdef` para triggers.
- `pg_proc`, `pg_language` e `pg_get_functiondef` para funcoes e procedures.
- `pg_sequences` para sequencias.
- `pg_indexes` para indices.
- `pg_constraint` e `pg_get_constraintdef` para constraints.
- `pg_type` e `pg_enum` para tipos compostos, dominios, enums e ranges.

### Mapeamento atual do schema `carex`

| Grupo | Quantidade |
| --- | ---: |
| Relacoes | 57 |
| Views | 5 |
| Materialized views | 3 |
| Triggers | 0 |
| Rotinas | 0 |
| Sequencias | 2 |
| Indices | 185 |
| Constraints | 63 |
| Tipos | 55 |

Observacao: o grupo de relacoes inclui tabelas, views, materialized views e sequencias. O backend de consulta de dados permite leitura de tabelas, views e materialized views validadas por metadados.

Observacao de desempenho: a coluna de linhas em Objetos da base usa estimativas do catalogo PostgreSQL. Ela evita `count(*)` em todos os objetos durante a carga inicial, mantendo a tela de inventario responsiva.

Observacao de interface: a tabela de objetos da base usa paginacao client-side para limitar a quantidade de linhas renderizadas por vez. Problemas conhecidos de codificacao de caracteres estao documentados em [`desenvolvimento-codificacao.md`](desenvolvimento-codificacao.md).

## Como evoluir

1. Manter o modulo de desenvolvimento como area tecnica.
2. Criar rotas especificas para os modulos futuros em vez de misturar funcionalidades.
3. Antes de qualquer escrita no banco, separar permissoes de leitura e escrita no PostgreSQL.
4. Adicionar autenticacao e autorizacao antes dos modulos administrativo e trabalho.
5. Registrar migrations ou scripts SQL versionados quando houver mudanca estrutural na base.
