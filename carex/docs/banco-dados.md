# Banco de Dados CAREX

O CAREX consulta um PostgreSQL com schema `carex`.

## Regra operacional

A base conectada deve ser tratada como producao sem backup confirmado. Nao execute escrita sem backup, janela e autorizacao explicita.

## Configuracao

Variaveis obrigatorias:

| Variavel | Descricao |
| --- | --- |
| `DB_HOST` | Host PostgreSQL. |
| `DB_PORT` | Porta, normalmente `5432`. |
| `DB_DATABASE` | Nome do banco. |
| `DB_USERNAME` | Usuario da aplicacao. Deve ser somente leitura em producao. |
| `DB_PASSWORD` | Senha. Nunca versionar. |
| `DB_SCHEMA` | Schema, atualmente `carex`. |
| `DB_ALLOW_WRITES` | Deve ficar `false` por padrao. |

## Protecoes aplicadas pela aplicacao

`Carex\Database\Connection` executa:

```sql
SET application_name TO 'carex-web';
SET search_path TO "schema";
SET statement_timeout TO '30000ms';
SET idle_in_transaction_session_timeout TO '10000ms';
SET default_transaction_read_only TO on;
```

`default_transaction_read_only` so nao e aplicado quando `DB_ALLOW_WRITES=true`.

## Objetos centrais usados pela aplicacao

| Objeto | Uso |
| --- | --- |
| `tb_matriz` | Lista de matrizes. |
| `tb_matriz_classificacao` | Itens e classificacoes diretas por matriz. |
| `tb_classificacao` | Dominio de classificacoes. |
| `tb_tp_objeto` | Tipos de objeto CBO/CNAE. |
| `tb_especialista` | Especialistas administrativos. |
| `tb_matriz_especialista` | Vinculo matriz-especialista. |
| `mvw_matriz_classificacao_herdada` | Classificacao final por item nivel 5. |
| `mvw_matriz_classificacao_conciliada_vinculos` | Estimativas de vinculos RAIS por pares CNAE/CBO e criterios. |
| `mvw_rais_n_vinc` | Materialized view relacionada a vinculos RAIS. |
| `mvw_jac_subc_ocup` | Pares JAC CNAE/CBO com chaves padronizadas `co_cnae` e `co_cbo`. |

## Classificacao final

Na tela da matriz, a classificacao final e obtida a partir de `mvw_matriz_classificacao_herdada`.

Interpretacao:

| Origem | Significado |
| --- | --- |
| `Herdada` | Classificacao vem de nivel superior (`n1` a `n4`). |
| `Direta no item` | Classificacao vem do proprio item (`n5`). |
| `Sem heranca` | Categoria CBO/CNAE sem heranca aplicavel na view consolidada. |
| `Nao classificada` | A consolidacao indica nao classificado. |

## Estimativas de vinculos

A aba de estimativas usa `mvw_matriz_classificacao_conciliada_vinculos` e divide as estimativas pela quantidade de anos unicos em `mvw_rais_serie_ocup_subc_n_vinc`.

Colunas de criterio:

- `co_classificacao_conciliada_par_crit_1`
- `co_classificacao_conciliada_par_crit_2`
- `co_classificacao_conciliada_par_crit_3`
- `co_classificacao_conciliada_par_crit_4`
- `co_classificacao_conciliada_par_crit_5`
- `co_classificacao_conciliada_par_crit_6`
- `co_classificacao_conciliada_par_crit_7`
- `co_classificacao_conciliada_par_crit_8`
- `co_classificacao_conciliada_par_crit_9`
- `co_classificacao_conciliada_par_crit_10`

O backend soma `rais_n_vinc` por criterio e classificacao, retornando tambem percentual dentro de cada criterio.

## Pares JAC CNAE/CBO

A tabela `mvw_jac_subc_ocup` usa os nomes padronizados `co_cnae` e `co_cbo` para os codigos de subclasse CNAE e ocupacao CBO. A aplicacao ainda aceita os nomes historicos `co_cnae_subc` e `co_cbo_ocup` como fallback de compatibilidade, mas a base atual deve expor os nomes curtos.

## Catálogo Completo de Objetos da Base de Dados

Abaixo estão listados todos os objetos catalogados no schema `carex`, com a respectiva 'observação' proposta para registro futuro via `COMMENT ON` no PostgreSQL.

| Nome do Objeto | Tipo | Observação Proposta para o Banco de Dados |
| --- | --- | --- |
| `mvw_matriz_classificacao_conciliada_vinculos` | Materialized View | View materializada contendo as estimativas de vínculos RAIS conciliadas por par CNAE/CBO para os 10 critérios da matriz. |
| `mvw_matriz_classificacao_herdada` | Materialized View | View materializada que resolve a classificação herdada na hierarquia CNAE/CBO (do nível 1 ao 5) para cada matriz. |
| `mvw_rais_n_vinc` | Materialized View | View materializada de vínculos RAIS sem exposição direta associada, otimizada para cruzamento com a matriz. |
| `cbo_fami` | Tabela | Tabela de domínio CBO contendo as Famílias (Nível 4). |
| `cbo_gran_grup` | Tabela | Tabela de domínio CBO contendo os Grandes Grupos (Nível 1). |
| `cbo_ocup` | Tabela | Tabela de domínio CBO contendo as Ocupações (Nível 5). |
| `cbo_ocup_rais` | Tabela | Tabela de correlação de ocupações CBO com registros históricos da RAIS. |
| `cbo_perfil_ocupacional` | Tabela | Tabela com perfis ocupacionais adicionais vinculados à CBO. |
| `cbo_sino` | Tabela | Tabela de sinônimos de nomenclaturas da CBO. |
| `cbo_subg` | Tabela | Tabela de domínio CBO contendo os Subgrupos (Nível 3). |
| `cbo_subg_prin` | Tabela | Tabela de domínio CBO contendo os Subgrupos Principais (Nível 2). |
| `cnae_clas` | Tabela | Tabela de domínio CNAE contendo as Classes (Nível 4). |
| `cnae_divi` | Tabela | Tabela de domínio CNAE contendo as Divisões (Nível 2). |
| `cnae_grup` | Tabela | Tabela de domínio CNAE contendo os Grupos (Nível 3). |
| `cnae_seca` | Tabela | Tabela de domínio CNAE contendo as Seções (Nível 1). |
| `cnae_subc` | Tabela | Tabela de domínio CNAE contendo as Subclasses (Nível 5). |
| `mvw_jac_subc_ocup` | Tabela | Tabela resultante de cruzamento da matriz JAC entre subclasses CNAE e ocupações CBO, com chaves `co_cnae` e `co_cbo`. |
| `mvw_jac_subc_ocup_copy1` | Tabela | Cópia de backup temporária da tabela mvw_jac_subc_ocup. |
| `mvw_jac_subc_ocup_copy2` | Tabela | Cópia de backup temporária da tabela mvw_jac_subc_ocup. |
| `mvw_jac_subc_ocup_copy3` | Tabela | Cópia de backup temporária da tabela mvw_jac_subc_ocup. |
| `mvw_jac_subc_ocup_copy4` | Tabela | Cópia de backup temporária da tabela mvw_jac_subc_ocup. |
| `mvw_jac_subc_ocup_copy6` | Tabela | Cópia de backup temporária da tabela mvw_jac_subc_ocup. |
| `mvw_jac_subc_ocup_old` | Tabela | Cópia antiga descontinuada da tabela mvw_jac_subc_ocup. |
| `mvw_rais_serie_ocup_subc_n_vinc` | Tabela | Tabela física contendo a série histórica de vínculos RAIS por ano para pares CBO/CNAE. |
| `mvw_rais_serie_ocup_subc_n_vinc_2` | Tabela | Versão alternativa ou cópia de trabalho da série histórica de vínculos RAIS. |
| `tb_cbo_denorm` | Tabela | Tabela desnormalizada da estrutura CBO para fins de otimização de busca. |
| `tb_classificacao` | Tabela | Tabela de domínio com os códigos e descrições das classificações possíveis (ex: Não exposto, Condicionalmente exposto, Exposto). |
| `tb_cnae_cbo` | Tabela | Tabela unificada com o mapeamento completo e hierárquico das classificações CBO e CNAE. |
| `tb_cnae_cbo_copy1` | Tabela | Cópia de backup temporária da tabela tb_cnae_cbo. |
| `tb_cnae_cbo_copy2` | Tabela | Cópia de backup temporária da tabela tb_cnae_cbo. |
| `tb_cnae_subc_denorm` | Tabela | Tabela desnormalizada da estrutura CNAE subclasse para aceleração de consultas. |
| `tb_especialista` | Tabela | Tabela com o cadastro dos especialistas administrativos que operam as matrizes. |
| `tb_matriz` | Tabela | Tabela principal com o cadastro das matrizes de exposição a agentes químicos/físicos. |
| `tb_matriz_classificacao` | Tabela | Tabela que armazena as classificações de exposição diretas inseridas para os objetos CBO/CNAE em cada matriz. |
| `tb_matriz_classificacao_bkp_2019-04-25` | Tabela | Tabela de backup ou cópia temporária de segurança criada para salvaguardar dados durante migrações. |
| `tb_matriz_classificacao_bkp_2019-04-29` | Tabela | Tabela de backup ou cópia temporária de segurança criada para salvaguardar dados durante migrações. |
| `tb_matriz_classificacao_bkp_2019-05-01` | Tabela | Tabela de backup ou cópia temporária de segurança criada para salvaguardar dados durante migrações. |
| `tb_matriz_classificacao_bkp_2019-05-15` | Tabela | Tabela de backup ou cópia temporária de segurança criada para salvaguardar dados durante migrações. |
| `tb_matriz_classificacao_bkp_2019-05-17` | Tabela | Tabela de backup ou cópia temporária de segurança criada para salvaguardar dados durante migrações. |
| `tb_matriz_classificacao_bkp_2019-05-25` | Tabela | Tabela de backup ou cópia temporária de segurança criada para salvaguardar dados durante migrações. |
| `tb_matriz_classificacao_bkp_2019-07-24` | Tabela | Tabela de backup ou cópia temporária de segurança criada para salvaguardar dados durante migrações. |
| `tb_matriz_classificacao_bkp_2019-11-06` | Tabela | Tabela de backup ou cópia temporária de segurança criada para salvaguardar dados durante migrações. |
| `tb_matriz_classificacao_bkp_2019-12-02` | Tabela | Tabela de backup ou cópia temporária de segurança criada para salvaguardar dados durante migrações. |
| `tb_matriz_classificacao_bkp_2020_01_22` | Tabela | Tabela de backup ou cópia temporária de segurança criada para salvaguardar dados durante migrações. |
| `tb_matriz_classificacao_bkp_2021_03_06` | Tabela | Tabela de backup ou cópia temporária de segurança criada para salvaguardar dados durante migrações. |
| `tb_matriz_classificacao_bkp_2021_11_14` | Tabela | Tabela de backup ou cópia temporária de segurança criada para salvaguardar dados durante migrações. |
| `tb_matriz_especialista` | Tabela | Tabela de associação de especialistas a matrizes específicas. |
| `tb_matriz_especialista_classificacao` | Tabela | Tabela para controle ou histórico de alterações de classificação feitas por especialistas. |
| `tb_tp_objeto` | Tabela | Tabela de domínio com os tipos de objetos (CBO grande grupo, CBO ocupação, CNAE subclasse, etc.). |
| `tb_tp_objeto_copy1` | Tabela | Cópia de backup temporária da tabela tb_tp_objeto. |
| `users` | Tabela | Tabela de usuários autenticados via login do Google OAuth 2.0. |
| `vw_avanco_classificacao` | View | View que calcula o percentual e a quantidade de itens classificados por matriz para acompanhamento de progresso. |
| `vw_cbo_ocup_relacionados` | View | View auxiliar para obter ocupações CBO relacionadas. |
| `vw_matriz_classificacao_conciliada_vinculos` | View | View base de conciliação de vínculos RAIS para pares de objetos e os 10 critérios. |
| `vw_matriz_classificacao_conciliada_vinculos_1` | View | Versão preliminar ou alternativa da view de conciliação de vínculos por critérios. |
| `vw_matriz_classificacao_herdada` | View | View base hierárquica que calcula a classificação herdada a partir dos níveis L1 a L5. |
| `vw_matriz_classificacao_pai` | View | View que identifica os nós pais e suas respectivas classificações na árvore hierárquica. |

## Inventario tecnico

O modulo Desenvolvimento le catalogos PostgreSQL:

- `pg_class`
- `pg_namespace`
- `pg_views`
- `pg_matviews`
- `pg_trigger`
- `pg_proc`
- `pg_sequences`
- `pg_indexes`
- `pg_constraint`
- `pg_type`

Essas leituras existem para evitar SQL manual exploratorio em producao.
