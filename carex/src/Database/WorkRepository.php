<?php

declare(strict_types=1);

namespace Carex\Database;

use PDO;

final class WorkRepository
{
    private PDO $pdo;

    /**
     * @var array{cnae: string, cbo: string}|null
     */
    private ?array $jacColumns = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function matrices(): array
    {
        $statement = $this->pdo->query(
            "select m.id_matriz,
                    m.no_matriz,
                    m.versao,
                    m.ds_especialistas,
                    m.ds_fonte_forca_trabalho,
                    count(mc.co_objeto) as total_itens,
                    count(mc.co_classificacao) filter (
                        where mc.co_classificacao is not null
                          and btrim(mc.co_classificacao) <> ''
                          and mc.co_classificacao <> '9'
                    ) as total_classificados,
                    count(distinct mc.co_tp_objeto) as tipos_objeto,
                    (
                        select count(distinct ano)
                          from mvw_rais_serie_ocup_subc_n_vinc
                    ) as total_anos_rais
               from tb_matriz m
               left join tb_matriz_classificacao mc on mc.id_matriz = m.id_matriz
              group by m.id_matriz, m.no_matriz, m.versao, m.ds_especialistas, m.ds_fonte_forca_trabalho
              order by m.no_matriz"
        );

        return array_map(static function (array $row): array {
            $total = (int) $row['total_itens'];
            $classified = (int) $row['total_classificados'];

            return [
                'id_matriz' => (string) $row['id_matriz'],
                'no_matriz' => (string) $row['no_matriz'],
                'total_itens' => $total,
                'total_classificados' => $classified,
                'tipos_objeto' => (int) $row['tipos_objeto'],
                'total_anos_rais' => (int) $row['total_anos_rais'],
                'percentual_classificado' => $total > 0 ? round(($classified / $total) * 100, 1) : 0.0,
                'versao' => (string) ($row['versao'] ?? '1.0'),
                'ds_especialistas' => (string) ($row['ds_especialistas'] ?? ''),
                'ds_fonte_forca_trabalho' => (string) ($row['ds_fonte_forca_trabalho'] ?? 'RAIS'),
            ];
        }, $statement->fetchAll());
    }

    /**
     * @return array{matrix_id: string, columns: array<int, string>, rows: array<int, array<string, mixed>>, page: int, per_page: int, total: int, total_pages: int}
     */
    public function matrixClassifications(string $matrixId, int $page, int $perPage, string $query, array $filters = []): array
    {
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);
        $offset = ($page - 1) * $perPage;

        $params = ['id_matriz' => $matrixId];
        $whereConditions = [];

        if ($query !== '') {
            $parts = [
                'co_objeto ilike :q_search',
                'no_objeto ilike :q_search',
                'no_classificacao ilike :q_search',
                'no_classificacao_consolidada ilike :q_search',
                'co_classificacao_consolidada ilike :q_search',
                'co_nivel_classificacao_consolidada ilike :q_search',
                'no_tp_objeto ilike :q_search',
                'no_origem_objeto ilike :q_search'
            ];
            $params['q_search'] = '%' . $query . '%';
            $whereConditions[] = '(' . implode(' or ', $parts) . ')';
        }

        $validColumns = [
            'id_matriz', 'co_tp_objeto', 'co_objeto', 'co_classificacao',
            'no_classificacao', 'de_classificacao_observacao',
            'nu_probabilidade', 'no_tp_objeto', 'no_origem_objeto', 'no_objeto',
            'co_classificacao_consolidada', 'no_classificacao_consolidada',
            'co_nivel_classificacao_consolidada', 'classificacao_consolidada_origem'
        ];

        if ($filters !== []) {
            foreach ($filters as $fIdx => $filter) {
                $column = $filter['column'] ?? '';
                $values = $filter['values'] ?? [];
                if ($column === '' || $values === [] || !in_array($column, $validColumns, true)) {
                    continue;
                }

                $valuePlaceholders = [];
                foreach ($values as $vIdx => $val) {
                    $paramName = "f_{$fIdx}_{$vIdx}";
                    $valuePlaceholders[] = ":{$paramName}";
                    $params[$paramName] = (string) $val;
                }

                if ($valuePlaceholders !== []) {
                    $whereConditions[] = "cast({$column} as text) in (" . implode(', ', $valuePlaceholders) . ')';
                }
            }
        }

        $where = $whereConditions !== [] ? ' WHERE ' . implode(' AND ', $whereConditions) : '';

        $baseSql = "
            WITH base_query AS (
              SELECT mc.id_matriz,
                     mc.co_tp_objeto,
                     mc.co_objeto,
                     mc.co_classificacao,
                     cl.no_classificacao,
                     mc.de_classificacao_observacao,
                       COALESCE(mv.nu_probabilidade_herdada, CASE 
                            WHEN mv.co_classificacao_herdada = '0' THEN 0.0
                            WHEN mv.co_classificacao_herdada = '1' THEN 0.5
                            WHEN mv.co_classificacao_herdada = '2' THEN 1.0
                            ELSE NULL
                       END) as nu_probabilidade,
                     tp.no_tp_objeto,
                     tp.no_origem_objeto,
                     mv.co_classificacao_herdada,
                     clc.no_classificacao as no_classificacao_herdada,
                     mv.co_nivel_classificacao_herdada,
                     CASE
                         WHEN mv.co_classificacao_herdada IS NULL THEN 'Sem heranca'
                         WHEN mv.co_nivel_classificacao_herdada IN ('n1', 'n2', 'n3', 'n4') THEN 'Herdada'
                         WHEN mv.co_nivel_classificacao_herdada = 'n5' THEN 'Direta no item'
                         ELSE 'Nao classificada'
                     END as classificacao_herdada_origem,
                     COALESCE(
                         o_cbo_ocup.no_cbo_ocup,
                         o_cbo_fami.no_cbo_fami,
                         o_cbo_subg.no_cbo_subg,
                         o_cbo_subg_prin.no_cbo_subg_prin,
                         o_cbo_gran_grup.no_cbo_gran_grup,
                         o_cnae_subc.no_cnae_subc,
                         o_cnae_clas.no_cnae_clas,
                         o_cnae_grup.no_cnae_grup,
                         o_cnae_divi.no_cnae_divi,
                         o_cnae_seca.no_cnae_seca
                     ) as no_objeto
                FROM tb_matriz_classificacao mc
                LEFT JOIN tb_classificacao cl ON cl.co_classificacao = mc.co_classificacao
                LEFT JOIN tb_tp_objeto tp ON tp.co_tp_objeto = mc.co_tp_objeto
                LEFT JOIN mvw_matriz_classificacao_herdada mv
                       ON mv.id_matriz = mc.id_matriz
                      AND mv.n5_co_tp_objeto = mc.co_tp_objeto
                      AND mv.n5_co_objeto = mc.co_objeto
                LEFT JOIN tb_classificacao clc ON clc.co_classificacao = mv.co_classificacao_herdada
                -- CBO tables
                LEFT JOIN cbo_ocup o_cbo_ocup ON mc.co_tp_objeto = 'cbo_ocup' AND o_cbo_ocup.co_cbo_ocup = mc.co_objeto
                LEFT JOIN cbo_fami o_cbo_fami ON mc.co_tp_objeto = 'cbo_fami' AND o_cbo_fami.co_cbo_fami = mc.co_objeto
                LEFT JOIN cbo_subg o_cbo_subg ON mc.co_tp_objeto = 'cbo_subg' AND o_cbo_subg.co_cbo_subg = mc.co_objeto
                LEFT JOIN cbo_subg_prin o_cbo_subg_prin ON mc.co_tp_objeto = 'cbo_subg_prin' AND o_cbo_subg_prin.co_cbo_subg_prin = mc.co_objeto
                LEFT JOIN cbo_gran_grup o_cbo_gran_grup ON mc.co_tp_objeto = 'cbo_gran_grup' AND o_cbo_gran_grup.co_cbo_gran_grup = mc.co_objeto
                -- CNAE tables
                LEFT JOIN cnae_subc o_cnae_subc ON mc.co_tp_objeto = 'cnae_subc' AND o_cnae_subc.co_cnae_subc = mc.co_objeto
                LEFT JOIN cnae_clas o_cnae_clas ON mc.co_tp_objeto = 'cnae_clas' AND o_cnae_clas.co_cnae_clas = mc.co_objeto
                LEFT JOIN cnae_grup o_cnae_grup ON mc.co_tp_objeto = 'cnae_grup' AND o_cnae_grup.co_cnae_grup = mc.co_objeto
                LEFT JOIN cnae_divi o_cnae_divi ON mc.co_tp_objeto = 'cnae_divi' AND o_cnae_divi.co_cnae_divi = mc.co_objeto
                LEFT JOIN cnae_seca o_cnae_seca ON mc.co_tp_objeto = 'cnae_seca' AND o_cnae_seca.co_cnae_seca = mc.co_objeto
               WHERE mc.id_matriz = :id_matriz
            )
        ";

        $countStatement = $this->pdo->prepare("{$baseSql} SELECT count(*) from base_query {$where}");
        $countStatement->execute($params);
        $total = (int) $countStatement->fetchColumn();

        $dataStatement = $this->pdo->prepare("
            {$baseSql}
            SELECT *
              from base_query
              {$where}
             order by no_origem_objeto asc, co_objeto asc
             limit :limit offset :offset
        ");

        foreach ($params as $key => $value) {
            $dataStatement->bindValue($key, $value);
        }
        $dataStatement->bindValue('limit', $perPage, PDO::PARAM_INT);
        $dataStatement->bindValue('offset', $offset, PDO::PARAM_INT);
        $dataStatement->execute();

        return [
            'matrix_id' => $matrixId,
            'columns' => [
                'no_origem_objeto',
                'no_tp_objeto',
                'co_objeto',
                'no_objeto',
                'no_classificacao',
                'no_classificacao_herdada',
                'co_nivel_classificacao_herdada',
                'classificacao_herdada_origem',
                'nu_probabilidade',
                'de_classificacao_observacao'
            ],
            'rows' => $dataStatement->fetchAll(),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function matrixUniqueValues(string $matrixId, string $column): array
    {
        $validColumns = [
            'no_origem_objeto',
            'no_tp_objeto',
            'no_classificacao',
            'co_tp_objeto',
            'no_classificacao_herdada',
            'co_nivel_classificacao_herdada',
            'classificacao_herdada_origem',
        ];
        if (!in_array($column, $validColumns, true)) {
            throw new \InvalidArgumentException('Coluna nao suportada.');
        }

        $baseSql = "
            WITH base_query AS (
              SELECT mc.id_matriz,
                     cl.no_classificacao,
                     clc.no_classificacao as no_classificacao_herdada,
                     mv.co_nivel_classificacao_herdada,
                     CASE
                         WHEN mv.co_classificacao_herdada IS NULL THEN 'Sem heranca'
                         WHEN mv.co_nivel_classificacao_herdada IN ('n1', 'n2', 'n3', 'n4') THEN 'Herdada'
                         WHEN mv.co_nivel_classificacao_herdada = 'n5' THEN 'Direta no item'
                         ELSE 'Nao classificada'
                     END as classificacao_herdada_origem,
                     tp.no_tp_objeto,
                     tp.no_origem_objeto,
                     mc.co_tp_objeto
                FROM tb_matriz_classificacao mc
                LEFT JOIN tb_classificacao cl ON cl.co_classificacao = mc.co_classificacao
                LEFT JOIN tb_tp_objeto tp ON tp.co_tp_objeto = mc.co_tp_objeto
                LEFT JOIN mvw_matriz_classificacao_herdada mv
                       ON mv.id_matriz = mc.id_matriz
                      AND mv.n5_co_tp_objeto = mc.co_tp_objeto
                      AND mv.n5_co_objeto = mc.co_objeto
                LEFT JOIN tb_classificacao clc ON clc.co_classificacao = mv.co_classificacao_herdada
               WHERE mc.id_matriz = :id_matriz
            )
        ";

        $statement = $this->pdo->prepare("
            {$baseSql}
            SELECT DISTINCT cast({$column} as text) as val
              FROM base_query
             WHERE {$column} IS NOT NULL
             ORDER BY val ASC
        ");
        $statement->execute(['id_matriz' => $matrixId]);

        return array_map(static fn (array $row): string => (string) ($row['val'] ?? ''), $statement->fetchAll());
    }

    /**
     * @return array{matrix_id: string, total_anos_rais: int, total_vinculos_por_criterio: float, criteria: array<int, array<string, mixed>>}
     */
    public function matrixLinkEstimates(string $matrixId): array
    {
        $statement = null;
        $matrixStatement = null;
        $minYear = null;
        $maxYear = null;

        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec('set transaction read only');
            $this->pdo->exec("set local statement_timeout = '20000ms'");

            $yearsStmt = $this->pdo->query('select min(ano) as min_ano, max(ano) as max_ano from mvw_rais_serie_ocup_subc_n_vinc');
            $yearsRow = $yearsStmt->fetch();
            if ($yearsRow) {
                $minYear = (int) $yearsRow['min_ano'];
                $maxYear = (int) $yearsRow['max_ano'];
            }

            $statement = $this->pdo->prepare(
                "with criterios as (
                    select generate_series(1, 10) as criterio_num
                ),
                classificacoes as (
                    select co_classificacao, no_classificacao
                      from tb_classificacao
                    union all
                    select '9', 'Nao classificado'
                     where not exists (
                           select 1
                             from tb_classificacao
                            where co_classificacao = '9'
                     )
                ),
                anos_rais as (
                    select greatest(count(distinct ano), 1)::numeric as total_anos
                      from mvw_rais_serie_ocup_subc_n_vinc
                ),
                unpivot as (
                    select criterio_num,
                           co_classificacao,
                           round(sum(coalesce(rais_n_vinc, 0))::numeric / max(anos_rais.total_anos), 2) as vinculos,
                           max(anos_rais.total_anos)::integer as total_anos_rais
                      from mvw_matriz_classificacao_conciliada_vinculos v
                     cross join anos_rais
                     cross join lateral (values
                           (1, v.co_classificacao_conciliada_par_crit_1),
                           (2, v.co_classificacao_conciliada_par_crit_2),
                           (3, v.co_classificacao_conciliada_par_crit_3),
                           (4, v.co_classificacao_conciliada_par_crit_4),
                           (5, v.co_classificacao_conciliada_par_crit_5),
                           (6, v.co_classificacao_conciliada_par_crit_6),
                           (7, v.co_classificacao_conciliada_par_crit_7),
                           (8, v.co_classificacao_conciliada_par_crit_8),
                           (9, v.co_classificacao_conciliada_par_crit_9),
                           (10, v.co_classificacao_conciliada_par_crit_10)
                     ) as c(criterio_num, co_classificacao)
                     where v.id_matriz = :id_matriz
                     group by criterio_num, co_classificacao
                ),
                totals as (
                    select criterio_num,
                           sum(vinculos) as total_vinculos,
                           max(total_anos_rais) as total_anos_rais
                      from unpivot
                     group by criterio_num
                )
                select cr.criterio_num,
                       'Critério ' || cr.criterio_num::text as criterio,
                       cl.co_classificacao,
                       cl.no_classificacao,
                       coalesce(u.vinculos, 0) as vinculos,
                       coalesce(t.total_vinculos, 0) as total_vinculos,
                       coalesce(t.total_anos_rais, 0) as total_anos_rais,
                       case
                           when coalesce(t.total_vinculos, 0) > 0
                           then round((coalesce(u.vinculos, 0)::numeric / t.total_vinculos::numeric) * 100, 2)
                           else 0
                       end as percentual
                  from criterios cr
                 cross join classificacoes cl
                  left join unpivot u
                    on u.criterio_num = cr.criterio_num
                   and coalesce(u.co_classificacao, '9') = cl.co_classificacao
                  left join totals t on t.criterio_num = cr.criterio_num
                 order by cr.criterio_num,
                          case cl.co_classificacao
                              when '2' then 1
                              when '1' then 2
                              when '0' then 3
                              when '8' then 4
                              when '9' then 5
                              else 6
                          end,
                          cl.no_classificacao"
            );
            $statement->execute(['id_matriz' => $matrixId]);
            $rows = $statement->fetchAll();

            $matrixStatement = $this->pdo->prepare(
                "with criterios as (
                    select generate_series(1, 10) as criterio_num
                ),
                classes as (
                    select *
                      from (values
                           ('2'::text, 'Exposto'),
                           ('1'::text, 'Condicionalmente exposto'),
                           ('0'::text, 'Não exposto'),
                           ('8'::text, 'Revisar'),
                           ('9'::text, 'Não classificado')
                      ) as c(code, name)
                ),
                pairs as (
                    select cr.criterio_num,
                           cbo.code as cbo_class,
                           cbo.name as cbo_name,
                           cnae.code as cnae_class,
                           cnae.name as cnae_name,
                           case
                               when cbo.code = '9' or cnae.code = '9' then '9'
                               when cbo.code = '8' or cnae.code = '8' then '8'
                               else case cr.criterio_num
                                   when 1 then case
                                       when cbo.code = '2' and cnae.code = '2' then '2'
                                       else '0'
                                   end
                                   when 2 then case
                                       when cbo.code = '2' and cnae.code <> '0' then '2'
                                       when cnae.code = '2' and cbo.code <> '0' then '2'
                                       else '0'
                                   end
                                   when 3 then case
                                       when cbo.code = '2' and cnae.code <> '0' then '2'
                                       when cnae.code = '2' and cbo.code <> '0' then '2'
                                       when cbo.code = '1' and cnae.code = '1' then '1'
                                       else '0'
                                   end
                                   when 4 then case
                                       when cbo.code = '2' and cnae.code = '0' then '1'
                                       when cbo.code = '0' and cnae.code = '2' then '1'
                                       when cbo.code = '2' or cnae.code = '2' then '2'
                                       when cbo.code = '1' and cnae.code = '1' then '1'
                                       else '0'
                                   end
                                   when 5 then case
                                       when cbo.code = '2' or cnae.code = '2' then '2'
                                       when cbo.code = '1' and cnae.code = '1' then '1'
                                       else '0'
                                   end
                                   when 6 then greatest(cbo.code, cnae.code)
                                   when 7 then case
                                       when cbo.code = '2' then '2'
                                       when cnae.code = '2' and cbo.code = '1' then '2'
                                       when cbo.code = '1' and cnae.code = '1' then '1'
                                       else '0'
                                   end
                                   when 8 then case
                                       when cbo.code = '2' and cnae.code <> '0' then '2'
                                       when cbo.code = '2' and cnae.code = '0' then '1'
                                       when cbo.code = '0' and cnae.code = '2' then '1'
                                       when cnae.code = '2' and cbo.code = '1' then '2'
                                       when cbo.code = '1' and cnae.code = '1' then '1'
                                       else '0'
                                   end
                                   when 9 then case
                                       when cbo.code <> '0' and cnae.code <> '0' then '2'
                                       else '0'
                                   end
                                   when 10 then case
                                       when cbo.code = '0' or cnae.code = '0' then '0'
                                       when cbo.code = '2' and cnae.code = '2' then '2'
                                       else '1'
                                   end
                               end
                           end as result_class
                      from criterios cr
                     cross join classes cbo
                     cross join classes cnae
                ),
                anos_rais as (
                    select greatest(count(distinct ano), 1)::numeric as total_anos
                      from mvw_rais_serie_ocup_subc_n_vinc
                ),
                raw as (
                    select cb.criterio_num,
                           coalesce(v.co_classificacao_herdada_cbo::text, '9') as cbo_class,
                           coalesce(v.co_classificacao_herdada_cnae::text, '9') as cnae_class,
                           coalesce(co_classificacao::text, '9') as result_class,
                           round(sum(coalesce(v.rais_n_vinc, 0))::numeric / max(anos_rais.total_anos), 2) as vinculos,
                           max(anos_rais.total_anos)::integer as total_anos_rais
                      from mvw_matriz_classificacao_conciliada_vinculos v
                     cross join anos_rais
                     cross join lateral (values
                           (1, v.co_classificacao_conciliada_par_crit_1),
                           (2, v.co_classificacao_conciliada_par_crit_2),
                           (3, v.co_classificacao_conciliada_par_crit_3),
                           (4, v.co_classificacao_conciliada_par_crit_4),
                           (5, v.co_classificacao_conciliada_par_crit_5),
                           (6, v.co_classificacao_conciliada_par_crit_6),
                           (7, v.co_classificacao_conciliada_par_crit_7),
                           (8, v.co_classificacao_conciliada_par_crit_8),
                           (9, v.co_classificacao_conciliada_par_crit_9),
                           (10, v.co_classificacao_conciliada_par_crit_10)
                     ) as cb(criterio_num, co_classificacao)
                     where v.id_matriz = :id_matriz
                     group by cb.criterio_num,
                              coalesce(v.co_classificacao_herdada_cbo::text, '9'),
                              coalesce(v.co_classificacao_herdada_cnae::text, '9'),
                              coalesce(co_classificacao::text, '9')
                ),
                totals as (
                    select criterio_num,
                           sum(vinculos) as total_vinculos_3x3,
                           max(total_anos_rais) as total_anos_rais
                      from raw
                     group by criterio_num
                )
                select p.criterio_num,
                       p.cbo_class,
                       p.cbo_name,
                       p.cnae_class,
                       p.cnae_name,
                       p.result_class,
                       coalesce(cl.no_classificacao, 'Não classificado') as result_name,
                       coalesce(r.vinculos, 0) as vinculos,
                       coalesce(t.total_vinculos_3x3, 0) as total_vinculos_3x3,
                       coalesce(t.total_anos_rais, 0) as total_anos_rais,
                       case
                           when coalesce(t.total_vinculos_3x3, 0) > 0
                           then round((coalesce(r.vinculos, 0)::numeric / t.total_vinculos_3x3::numeric) * 100, 2)
                           else 0
                       end as percentual
                  from pairs p
                  left join raw r
                    on r.criterio_num = p.criterio_num
                   and r.cbo_class = p.cbo_class
                   and r.cnae_class = p.cnae_class
                   and r.result_class = p.result_class
                  left join totals t on t.criterio_num = p.criterio_num
                  left join tb_classificacao cl on cl.co_classificacao = p.result_class
                  order by p.criterio_num,
                          case p.cbo_class
                              when '2' then 1
                              when '1' then 2
                              when '0' then 3
                              when '8' then 4
                              when '9' then 5
                              else 6
                          end,
                          case p.cnae_class
                              when '2' then 1
                              when '1' then 2
                              when '0' then 3
                              when '8' then 4
                              when '9' then 5
                              else 6
                          end"
            );
            $matrixStatement->execute(['id_matriz' => $matrixId]);
            $matrixRows = $matrixStatement->fetchAll();

            $this->pdo->commit();
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $error;
        }

        $criteria = [];
        $totalPerCriterion = 0.0;
        $totalYears = 0;
        $matrixByCriterion = [];

        foreach ($rows as $row) {
            $criterionNumber = (int) $row['criterio_num'];
            $totalYears = max($totalYears, (int) $row['total_anos_rais']);

            if (!isset($criteria[$criterionNumber])) {
                $totalPerCriterion = max($totalPerCriterion, (float) $row['total_vinculos']);
                $criteria[$criterionNumber] = [
                    'number' => $criterionNumber,
                    'label' => (string) $row['criterio'],
                    'total_vinculos' => (float) $row['total_vinculos'],
                    'total_anos_rais' => (int) $row['total_anos_rais'],
                    'classifications' => [],
                ];
            }

            $criteria[$criterionNumber]['classifications'][] = [
                'code' => (string) $row['co_classificacao'],
                'name' => (string) $row['no_classificacao'],
                'vinculos' => (float) $row['vinculos'],
                'percentual' => (float) $row['percentual'],
            ];
        }

        foreach ($matrixRows as $row) {
            $criterionNumber = (int) $row['criterio_num'];
            $totalYears = max($totalYears, (int) $row['total_anos_rais']);

            if (!isset($matrixByCriterion[$criterionNumber])) {
                $matrixByCriterion[$criterionNumber] = [
                    'total_vinculos_3x3' => (float) $row['total_vinculos_3x3'],
                    'total_anos_rais' => (int) $row['total_anos_rais'],
                    'cnae_classes' => [
                        ['code' => '2', 'name' => 'Exposto'],
                        ['code' => '1', 'name' => 'Condicionalmente exposto'],
                        ['code' => '0', 'name' => 'Não exposto'],
                        ['code' => '8', 'name' => 'Revisar'],
                        ['code' => '9', 'name' => 'Não classificado'],
                    ],
                    'cbo_classes' => [
                        ['code' => '2', 'name' => 'Exposto'],
                        ['code' => '1', 'name' => 'Condicionalmente exposto'],
                        ['code' => '0', 'name' => 'Não exposto'],
                        ['code' => '8', 'name' => 'Revisar'],
                        ['code' => '9', 'name' => 'Não classificado'],
                    ],
                    'cells' => [],
                ];
            }

            $matrixByCriterion[$criterionNumber]['cells'][] = [
                'cbo_class' => (string) $row['cbo_class'],
                'cnae_class' => (string) $row['cnae_class'],
                'result_code' => (string) $row['result_class'],
                'result_name' => (string) $row['result_name'],
                'vinculos' => (float) $row['vinculos'],
                'percentual' => (float) $row['percentual'],
            ];
        }

        foreach ($criteria as $criterionNumber => $criterion) {
            $criteria[$criterionNumber]['matrix'] = $matrixByCriterion[$criterionNumber] ?? [
                'total_vinculos_3x3' => 0.0,
                'total_anos_rais' => $totalYears,
                'cnae_classes' => [],
                'cbo_classes' => [],
                'cells' => [],
            ];
        }

        return [
            'matrix_id' => $matrixId,
            'total_anos_rais' => $totalYears,
            'total_vinculos_por_criterio' => $totalPerCriterion,
            'min_ano' => $minYear,
            'max_ano' => $maxYear,
            'criteria' => array_values($criteria),
        ];
    }

    public function categoryDetails(string $matrixId, string $code, string $type): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COALESCE(
                    o_cbo_ocup.no_cbo_ocup,
                    o_cbo_fami.no_cbo_fami,
                    o_cbo_subg.no_cbo_subg,
                    o_cbo_subg_prin.no_cbo_subg_prin,
                    o_cbo_gran_grup.no_cbo_gran_grup,
                    o_cnae_subc.no_cnae_subc,
                    o_cnae_clas.no_cnae_clas,
                    o_cnae_grup.no_cnae_grup,
                    o_cnae_divi.no_cnae_divi,
                    o_cnae_seca.no_cnae_seca
                ) as no_objeto,
                COALESCE(o_cbo_ocup.no_cbo_ocup_bread, o_cnae_subc.no_cnae_subc_bread) as breadcrumb,
                
                o_cbo_ocup.co_cbo_fami as cbo_ocup_fami,
                o_cbo_fami.co_cbo_subg as cbo_fami_subg,
                o_cbo_subg.co_cbo_subg_prin as cbo_subg_subg_prin,
                o_cbo_subg_prin.co_cbo_gran_grup as cbo_subg_prin_gran_grup,
                
                o_cnae_subc.co_cnae_clas as cnae_subc_clas,
                o_cnae_clas.co_cnae_grup as cnae_clas_grup,
                o_cnae_grup.co_cnae_divi as cnae_grup_divi,
                o_cnae_divi.co_cnae_seca as cnae_divi_seca
            FROM (SELECT :code as code) c
            LEFT JOIN cbo_ocup o_cbo_ocup ON :type = 'cbo_ocup' AND o_cbo_ocup.co_cbo_ocup = c.code
            LEFT JOIN cbo_fami o_cbo_fami ON :type = 'cbo_fami' AND o_cbo_fami.co_cbo_fami = c.code
            LEFT JOIN cbo_subg o_cbo_subg ON :type = 'cbo_subg' AND o_cbo_subg.co_cbo_subg = c.code
            LEFT JOIN cbo_subg_prin o_cbo_subg_prin ON :type = 'cbo_subg_prin' AND o_cbo_subg_prin.co_cbo_subg_prin = c.code
            LEFT JOIN cbo_gran_grup o_cbo_gran_grup ON :type = 'cbo_gran_grup' AND o_cbo_gran_grup.co_cbo_gran_grup = c.code
            
            LEFT JOIN cnae_subc o_cnae_subc ON :type = 'cnae_subc' AND o_cnae_subc.co_cnae_subc = c.code
            LEFT JOIN cnae_clas o_cnae_clas ON :type = 'cnae_clas' AND o_cnae_clas.co_cnae_clas = c.code
            LEFT JOIN cnae_grup o_cnae_grup ON :type = 'cnae_grup' AND o_cnae_grup.co_cnae_grup = c.code
            LEFT JOIN cnae_divi o_cnae_divi ON :type = 'cnae_divi' AND o_cnae_divi.co_cnae_divi = c.code
            LEFT JOIN cnae_seca o_cnae_seca ON :type = 'cnae_seca' AND o_cnae_seca.co_cnae_seca = c.code
        ");
        $stmt->execute(['code' => $code, 'type' => $type]);
        $row = $stmt->fetch();

        if (!$row || $row['no_objeto'] === null) {
            return null;
        }

        $name = (string) $row['no_objeto'];
        $breadcrumb = (string) ($row['breadcrumb'] ?? '');

        $isCbo = str_starts_with($type, 'cbo_');
        $p5 = $p4 = $p3 = $p2 = $p1 = '';

        if ($isCbo) {
            $p5 = ($type === 'cbo_ocup') ? $code : '';
            $p4 = ($type === 'cbo_fami') ? $code : (($type === 'cbo_ocup') ? (string)($row['cbo_ocup_fami'] ?? substr($code, 0, 4)) : '');
            $p3 = ($type === 'cbo_subg') ? $code : (($type === 'cbo_fami') ? (string)($row['cbo_fami_subg'] ?? substr($code, 0, 3)) : substr($code, 0, 3));
            $p2 = ($type === 'cbo_subg_prin') ? $code : (($type === 'cbo_subg') ? (string)($row['cbo_subg_subg_prin'] ?? substr($code, 0, 2)) : substr($code, 0, 2));
            $p1 = ($type === 'cbo_gran_grup') ? $code : (($type === 'cbo_subg_prin') ? (string)($row['cbo_subg_prin_gran_grup'] ?? substr($code, 0, 1)) : substr($code, 0, 1));
        } else {
            $p5 = ($type === 'cnae_subc') ? $code : '';
            $p4 = ($type === 'cnae_clas') ? $code : (($type === 'cnae_subc') ? (string)($row['cnae_subc_clas'] ?? substr($code, 0, 5)) : '');
            $p3 = ($type === 'cnae_grup') ? $code : (($type === 'cnae_clas') ? (string)($row['cnae_clas_grup'] ?? substr($p4, 0, 3)) : substr($code, 0, 3));
            $p2 = ($type === 'cnae_divi') ? $code : (($type === 'cnae_grup') ? (string)($row['cnae_grup_divi'] ?? substr($p3, 0, 2)) : substr($code, 0, 2));
            
            if ($type === 'cnae_seca') {
                $p1 = $code;
            } else {
                $p1 = (string)($row['cnae_divi_seca'] ?? '');
                if ($p1 === '' && $p2 !== '') {
                    $divStmt = $this->pdo->prepare("SELECT co_cnae_seca FROM cnae_divi WHERE co_cnae_divi = :code");
                    $divStmt->execute(['code' => $p2]);
                    $p1 = (string) $divStmt->fetchColumn();
                }
            }
        }

        $parents = [
            'L5' => ['level' => $isCbo ? 'Ocupação' : 'Subclasse', 'code' => $p5, 'name' => $p5 !== '' ? $name : '-'],
            'L4' => ['level' => $isCbo ? 'Família' : 'Classe', 'code' => $p4, 'name' => $type === ($isCbo ? 'cbo_fami' : 'cnae_clas') ? $name : $this->getParentName($isCbo ? 'cbo_fami' : 'cnae_clas', $isCbo ? 'co_cbo_fami' : 'co_cnae_clas', $isCbo ? 'no_cbo_fami' : 'no_cnae_clas', $p4)],
            'L3' => ['level' => $isCbo ? 'Subgrupo' : 'Grupo', 'code' => $p3, 'name' => $type === ($isCbo ? 'cbo_subg' : 'cnae_grup') ? $name : $this->getParentName($isCbo ? 'cbo_subg' : 'cnae_grup', $isCbo ? 'co_cbo_subg' : 'co_cnae_grup', $isCbo ? 'no_cbo_subg' : 'no_cnae_grup', $p3)],
            'L2' => ['level' => $isCbo ? 'Subgrupo Principal' : 'Divisão', 'code' => $p2, 'name' => $type === ($isCbo ? 'cbo_subg_prin' : 'cnae_divi') ? $name : $this->getParentName($isCbo ? 'cbo_subg_prin' : 'cnae_divi', $isCbo ? 'co_cbo_subg_prin' : 'co_cnae_divi', $isCbo ? 'no_cbo_subg_prin' : 'no_cnae_divi', $p2)],
            'L1' => ['level' => $isCbo ? 'Grande Grupo' : 'Seção', 'code' => $p1, 'name' => $type === ($isCbo ? 'cbo_gran_grup' : 'cnae_seca') ? $name : $this->getParentName($isCbo ? 'cbo_gran_grup' : 'cnae_seca', $isCbo ? 'co_cbo_gran_grup' : 'co_cnae_seca', $isCbo ? 'no_cbo_gran_grup' : 'no_cnae_seca', $p1)],
        ];

        $stmtMatName = $this->pdo->prepare("SELECT no_matriz FROM tb_matriz WHERE id_matriz = :id_matriz");
        $stmtMatName->execute(['id_matriz' => $matrixId]);
        $matrixName = (string) $stmtMatName->fetchColumn();
        if ($matrixName === '') {
            return null;
        }

        $directClassifications = [];
        $stmtDirect = $this->pdo->prepare("
            SELECT co_objeto, co_classificacao 
              FROM tb_matriz_classificacao 
             WHERE id_matriz = :id_matriz 
               AND co_objeto IN (:p5, :p4, :p3, :p2, :p1)
        ");
        $stmtDirect->execute([
            'id_matriz' => $matrixId,
            'p5' => $p5 !== '' ? $p5 : 'dummy',
            'p4' => $p4 !== '' ? $p4 : 'dummy',
            'p3' => $p3 !== '' ? $p3 : 'dummy',
            'p2' => $p2 !== '' ? $p2 : 'dummy',
            'p1' => $p1 !== '' ? $p1 : 'dummy'
        ]);
        foreach ($stmtDirect->fetchAll() as $dRow) {
            $directClassifications[(string) $dRow['co_objeto']] = (string) $dRow['co_classificacao'];
        }

        $finalClass = '9';
        $finalLvl = 'nc';

        if ($type === 'cbo_ocup' || $type === 'cnae_subc') {
            $stmtCons = $this->pdo->prepare("
                SELECT co_classificacao_herdada, co_nivel_classificacao_herdada 
                  FROM mvw_matriz_classificacao_herdada 
                 WHERE id_matriz = :id_matriz 
                   AND n5_co_tp_objeto = :type 
                   AND n5_co_objeto = :code
            ");
            $stmtCons->execute(['id_matriz' => $matrixId, 'type' => $type, 'code' => $code]);
            $consRow = $stmtCons->fetch();
            if ($consRow) {
                $finalClass = (string) $consRow['co_classificacao_herdada'];
                $finalLvl = (string) $consRow['co_nivel_classificacao_herdada'];
            }
        } else {
            $finalClass = $directClassifications[$code] ?? '9';
            $finalLvl = strtolower(substr($type, 4, 2));
            if (!in_array($finalLvl, ['l1', 'l2', 'l3', 'l4'], true)) {
                $finalLvl = strtolower(substr($type, 5, 2));
            }
        }

        $classNames = [];
        $stmtClass = $this->pdo->query("SELECT co_classificacao, no_classificacao FROM tb_classificacao");
        foreach ($stmtClass->fetchAll() as $cRow) {
            $classNames[(string) $cRow['co_classificacao']] = (string) $cRow['no_classificacao'];
        }
        $classNames['9'] = 'Não classificado';

        $resolveClassName = fn(?string $code) => $code !== null ? ($classNames[$code] ?? $code) : 'Não classificado';

        $classificationDetails = [
            'L5' => [
                'level' => $parents['L5']['level'],
                'code' => $parents['L5']['code'],
                'name' => $parents['L5']['name'],
                'classification' => $resolveClassName($parents['L5']['code'] !== '' ? ($directClassifications[$parents['L5']['code']] ?? null) : null),
                'raw_code' => $parents['L5']['code'] !== '' ? ($directClassifications[$parents['L5']['code']] ?? null) : null,
                'children' => []
            ],
            'L4' => [
                'level' => $parents['L4']['level'],
                'code' => $parents['L4']['code'],
                'name' => $parents['L4']['name'],
                'classification' => $resolveClassName($parents['L4']['code'] !== '' ? ($directClassifications[$parents['L4']['code']] ?? null) : null),
                'raw_code' => $parents['L4']['code'] !== '' ? ($directClassifications[$parents['L4']['code']] ?? null) : null,
                'children' => $this->getLevelChildren($matrixId, $isCbo, 'L4', $parents['L4']['code'])
            ],
            'L3' => [
                'level' => $parents['L3']['level'],
                'code' => $parents['L3']['code'],
                'name' => $parents['L3']['name'],
                'classification' => $resolveClassName($parents['L3']['code'] !== '' ? ($directClassifications[$parents['L3']['code']] ?? null) : null),
                'raw_code' => $parents['L3']['code'] !== '' ? ($directClassifications[$parents['L3']['code']] ?? null) : null,
                'children' => $this->getLevelChildren($matrixId, $isCbo, 'L3', $parents['L3']['code'])
            ],
            'L2' => [
                'level' => $parents['L2']['level'],
                'code' => $parents['L2']['code'],
                'name' => $parents['L2']['name'],
                'classification' => $resolveClassName($parents['L2']['code'] !== '' ? ($directClassifications[$parents['L2']['code']] ?? null) : null),
                'raw_code' => $parents['L2']['code'] !== '' ? ($directClassifications[$parents['L2']['code']] ?? null) : null,
                'children' => $this->getLevelChildren($matrixId, $isCbo, 'L2', $parents['L2']['code'])
            ],
            'L1' => [
                'level' => $parents['L1']['level'],
                'code' => $parents['L1']['code'],
                'name' => $parents['L1']['name'],
                'classification' => $resolveClassName($parents['L1']['code'] !== '' ? ($directClassifications[$parents['L1']['code']] ?? null) : null),
                'raw_code' => $parents['L1']['code'] !== '' ? ($directClassifications[$parents['L1']['code']] ?? null) : null,
                'children' => $this->getLevelChildren($matrixId, $isCbo, 'L1', $parents['L1']['code'])
            ],
        ];

        $series = [];
        $stmtSeries = null;
        if ($isCbo) {
            $stmtSeries = $this->pdo->prepare("
                SELECT v.ano, sum(coalesce(v.rais_n_vinc, 0))::bigint as vinculos 
                  FROM mvw_rais_serie_ocup_subc_n_vinc v
                  JOIN cbo_ocup o ON o.co_cbo_ocup = v.co_cbo_ocup
                  LEFT JOIN cbo_fami f ON f.co_cbo_fami = o.co_cbo_fami
                  LEFT JOIN cbo_subg sg ON sg.co_cbo_subg = f.co_cbo_subg
                  LEFT JOIN cbo_subg_prin sp ON sp.co_cbo_subg_prin = sg.co_cbo_subg_prin
                 WHERE (
                    (:type = 'cbo_ocup' AND o.co_cbo_ocup = :code) OR
                    (:type = 'cbo_fami' AND f.co_cbo_fami = :code) OR
                    (:type = 'cbo_subg' AND sg.co_cbo_subg = :code) OR
                    (:type = 'cbo_subg_prin' AND sp.co_cbo_subg_prin = :code) OR
                    (:type = 'cbo_gran_grup' AND sp.co_cbo_gran_grup = :code)
                 )
                 GROUP BY v.ano 
                 ORDER BY v.ano
            ");
        } else {
            $stmtSeries = $this->pdo->prepare("
                SELECT v.ano, sum(coalesce(v.rais_n_vinc, 0))::bigint as vinculos 
                  FROM mvw_rais_serie_ocup_subc_n_vinc v
                  JOIN cnae_subc s ON s.co_cnae_subc = replace(v.co_cnae_subc, '-', '')
                  LEFT JOIN cnae_clas cl ON cl.co_cnae_clas = s.co_cnae_clas
                  LEFT JOIN cnae_grup g ON g.co_cnae_grup = cl.co_cnae_grup
                  LEFT JOIN cnae_divi d ON d.co_cnae_divi = g.co_cnae_divi
                 WHERE (
                    (:type = 'cnae_subc' AND s.co_cnae_subc = :code) OR
                    (:type = 'cnae_clas' AND s.co_cnae_clas = :code) OR
                    (:type = 'cnae_grup' AND g.co_cnae_grup = :code) OR
                    (:type = 'cnae_divi' AND d.co_cnae_divi = :code) OR
                    (:type = 'cnae_seca' AND d.co_cnae_seca = :code)
                 )
                 GROUP BY v.ano 
                 ORDER BY v.ano
            ");
        }
        $stmtSeries->execute(['code' => $code, 'type' => $type]);
        $series = $stmtSeries->fetchAll();

        return [
            'matrix_id' => $matrixId,
            'matrix_name' => $matrixName,
            'code' => $code,
            'type' => $type,
            'type_label' => $this->getTypeLabel($type),
            'origin' => $isCbo ? 'CBO' : 'CNAE',
            'name' => $name,
            'breadcrumb' => $breadcrumb,
            'classificacao_herdada' => $resolveClassName($finalClass),
            'co_nivel_classificacao_herdada' => $finalLvl,
            'levels' => $classificationDetails,
            'series' => $series,
            'related_categories' => $this->relatedJacCategoriesByLevel($matrixId, $code, $type, $isCbo),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function relatedJacCategoriesByLevel(string $matrixId, string $code, string $type, bool $isCbo): array
    {
        $jacColumns = $this->jacColumns();
        $jacCnae = $jacColumns['cnae'];
        $jacCbo = $jacColumns['cbo'];
        $currentLevel = $this->typeLevel($type);

        if ($currentLevel === null) {
            return [];
        }

        $cboByLevel = [
            'L1' => ['table' => 'cbo_gran_grup', 'code' => 'co_cbo_gran_grup', 'name' => 'no_cbo_gran_grup', 'type' => 'cbo_gran_grup'],
            'L2' => ['table' => 'cbo_subg_prin', 'code' => 'co_cbo_subg_prin', 'name' => 'no_cbo_subg_prin', 'type' => 'cbo_subg_prin'],
            'L3' => ['table' => 'cbo_subg', 'code' => 'co_cbo_subg', 'name' => 'no_cbo_subg', 'type' => 'cbo_subg'],
            'L4' => ['table' => 'cbo_fami', 'code' => 'co_cbo_fami', 'name' => 'no_cbo_fami', 'type' => 'cbo_fami'],
            'L5' => ['table' => 'cbo_ocup', 'code' => 'co_cbo_ocup', 'name' => 'no_cbo_ocup', 'type' => 'cbo_ocup'],
        ];
        $cnaeByLevel = [
            'L1' => ['table' => 'cnae_seca', 'code' => 'co_cnae_seca', 'name' => 'no_cnae_seca', 'type' => 'cnae_seca'],
            'L2' => ['table' => 'cnae_divi', 'code' => 'co_cnae_divi', 'name' => 'no_cnae_divi', 'type' => 'cnae_divi'],
            'L3' => ['table' => 'cnae_grup', 'code' => 'co_cnae_grup', 'name' => 'no_cnae_grup', 'type' => 'cnae_grup'],
            'L4' => ['table' => 'cnae_clas', 'code' => 'co_cnae_clas', 'name' => 'no_cnae_clas', 'type' => 'cnae_clas'],
            'L5' => ['table' => 'cnae_subc', 'code' => 'co_cnae_subc', 'name' => 'no_cnae_subc', 'type' => 'cnae_subc'],
        ];

        $relatedConfig = $isCbo ? $cnaeByLevel[$currentLevel] : $cboByLevel[$currentLevel];
        $relatedJacColumn = $isCbo ? $jacCnae : $jacCbo;
        $currentJacColumn = $isCbo ? $jacCbo : $jacCnae;
        $relatedType = $relatedConfig['type'];
        $relatedCode = "rel.{$relatedConfig['code']}";
        $relatedName = "rel.{$relatedConfig['name']}";
        $relatedJoin = "join {$relatedConfig['table']} rel on rel.{$relatedConfig['code']} = j.{$relatedJacColumn}";
        $selectPercentCurrent = $isCbo ? 'max(j.nu_p_cbo)' : 'max(j.nu_p_cnae)';
        $selectPercentRelated = $isCbo ? 'max(j.nu_p_cnae)' : 'max(j.nu_p_cbo)';
        $jacLevel = 'n' . substr($currentLevel, 1);

        $inheritedJoin = '';
        $inheritedSelect = "
                   coalesce(max(cl_direct.no_classificacao), 'Não classificado') as classificacao_herdada,
                   max(coalesce(mc_direct.co_classificacao::text, '9')) as classificacao_herdada_code,";

        if ($currentLevel === 'L5') {
            $inheritedJoin = "
              left join mvw_matriz_classificacao_herdada mv_related
                     on mv_related.id_matriz = :matrix_id
                    and mv_related.n5_co_tp_objeto = '{$relatedType}'
                    and mv_related.n5_co_objeto = {$relatedCode}
              left join tb_classificacao cl_inherited
                     on cl_inherited.co_classificacao = mv_related.co_classificacao_herdada
            ";
            $inheritedSelect = "
                   coalesce(max(cl_inherited.no_classificacao), max(cl_direct.no_classificacao), 'Não classificado') as classificacao_herdada,
                   max(coalesce(mv_related.co_classificacao_herdada::text, mc_direct.co_classificacao::text, '9')) as classificacao_herdada_code,";
        }

        $sql = "
            select {$relatedCode} as code,
                   {$relatedName} as name,
                   max(j.nu_jac)::numeric as jac,
                   sum(coalesce(j.rais_n_vinc, 0))::bigint as vinculos,
                   {$selectPercentCurrent}::numeric as percent_current,
                   {$selectPercentRelated}::numeric as percent_related,
                   coalesce(max(cl_direct.no_classificacao), 'Não classificado') as classificacao,
                   max(mc_direct.co_classificacao::text) as classificacao_code,
                   {$inheritedSelect}
                   count(*)::int as total_pares
              from mvw_jac_subc_ocup j
              {$relatedJoin}
              left join tb_matriz_classificacao mc_direct
                     on mc_direct.id_matriz = :matrix_id
                    and mc_direct.co_tp_objeto = '{$relatedType}'
                    and mc_direct.co_objeto = {$relatedCode}
              left join tb_classificacao cl_direct
                     on cl_direct.co_classificacao = mc_direct.co_classificacao
              {$inheritedJoin}
             where j.{$currentJacColumn} = :code
               and j.nu_tp_objeto_nivel = :jac_level
               and {$relatedCode} is not null
             group by {$relatedCode}, {$relatedName}
             order by max(j.nu_jac) desc nulls last, sum(coalesce(j.rais_n_vinc, 0)) desc, {$relatedCode}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'code' => $code,
            'matrix_id' => $matrixId,
            'jac_level' => $jacLevel,
        ]);

        return array_map(static fn (array $row): array => [
            'code' => (string) $row['code'],
            'name' => $row['name'] === null ? '' : (string) $row['name'],
            'type' => $relatedType,
            'origin' => $isCbo ? 'CNAE' : 'CBO',
            'level' => $currentLevel,
            'classification' => (string) $row['classificacao'],
            'classification_code' => $row['classificacao_code'] === null ? null : (string) $row['classificacao_code'],
            'inherited_classification' => (string) $row['classificacao_herdada'],
            'inherited_classification_code' => $row['classificacao_herdada_code'] === null ? null : (string) $row['classificacao_herdada_code'],
            'jac' => $row['jac'] === null ? null : (float) $row['jac'],
            'vinculos' => (int) $row['vinculos'],
            'percent_current' => $row['percent_current'] === null ? null : (float) $row['percent_current'],
            'percent_related' => $row['percent_related'] === null ? null : (float) $row['percent_related'],
            'total_pares' => (int) $row['total_pares'],
        ], $stmt->fetchAll());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function relatedJacCategories(string $matrixId, string $code, string $type, bool $isCbo): array
    {
        $jacColumns = $this->jacColumns();
        $jacCnae = $jacColumns['cnae'];
        $jacCbo = $jacColumns['cbo'];
        $currentLevel = $this->typeLevel($type);

        if ($currentLevel === null) {
            return [];
        }

        $cboJoins = "
            join cbo_ocup o on o.co_cbo_ocup = j.{$jacCbo}
            left join cbo_fami f on f.co_cbo_fami = o.co_cbo_fami
            left join cbo_subg sg on sg.co_cbo_subg = f.co_cbo_subg
            left join cbo_subg_prin sp on sp.co_cbo_subg_prin = sg.co_cbo_subg_prin
            left join cbo_gran_grup gg on gg.co_cbo_gran_grup = sp.co_cbo_gran_grup
        ";
        $cnaeJoins = "
            join cnae_subc s on s.co_cnae_subc = j.{$jacCnae}
            left join cnae_clas cl on cl.co_cnae_clas = s.co_cnae_clas
            left join cnae_grup g on g.co_cnae_grup = cl.co_cnae_grup
            left join cnae_divi d on d.co_cnae_divi = g.co_cnae_divi
            left join cnae_seca se on se.co_cnae_seca = d.co_cnae_seca
        ";

        $cboByLevel = [
            'L1' => ['code' => 'gg.co_cbo_gran_grup', 'name' => 'gg.no_cbo_gran_grup', 'type' => 'cbo_gran_grup'],
            'L2' => ['code' => 'sp.co_cbo_subg_prin', 'name' => 'sp.no_cbo_subg_prin', 'type' => 'cbo_subg_prin'],
            'L3' => ['code' => 'sg.co_cbo_subg', 'name' => 'sg.no_cbo_subg', 'type' => 'cbo_subg'],
            'L4' => ['code' => 'f.co_cbo_fami', 'name' => 'f.no_cbo_fami', 'type' => 'cbo_fami'],
            'L5' => ['code' => 'o.co_cbo_ocup', 'name' => 'o.no_cbo_ocup', 'type' => 'cbo_ocup'],
        ];
        $cnaeByLevel = [
            'L1' => ['code' => 'se.co_cnae_seca', 'name' => 'se.no_cnae_seca', 'type' => 'cnae_seca'],
            'L2' => ['code' => 'd.co_cnae_divi', 'name' => 'd.no_cnae_divi', 'type' => 'cnae_divi'],
            'L3' => ['code' => 'g.co_cnae_grup', 'name' => 'g.no_cnae_grup', 'type' => 'cnae_grup'],
            'L4' => ['code' => 'cl.co_cnae_clas', 'name' => 'cl.no_cnae_clas', 'type' => 'cnae_clas'],
            'L5' => ['code' => 's.co_cnae_subc', 'name' => 's.no_cnae_subc', 'type' => 'cnae_subc'],
        ];

        $selectPercentCurrent = $isCbo ? 'max(j.nu_p_cbo)' : 'max(j.nu_p_cnae)';
        $selectPercentRelated = $isCbo ? 'max(j.nu_p_cnae)' : 'max(j.nu_p_cbo)';

        if ($isCbo) {
            $relatedCode = $cnaeByLevel[$currentLevel]['code'];
            $relatedName = $cnaeByLevel[$currentLevel]['name'];
            $relatedType = $cnaeByLevel[$currentLevel]['type'];
            $relatedN5Code = 's.co_cnae_subc';
            $relatedN5Type = 'cnae_subc';
            $relatedJoin = $cnaeJoins . $cboJoins;
            $filterWhere = $cboByLevel[$currentLevel]['code'] . ' = :code';
        } else {
            $relatedCode = $cboByLevel[$currentLevel]['code'];
            $relatedName = $cboByLevel[$currentLevel]['name'];
            $relatedType = $cboByLevel[$currentLevel]['type'];
            $relatedN5Code = 'o.co_cbo_ocup';
            $relatedN5Type = 'cbo_ocup';
            $relatedJoin = $cboJoins . $cnaeJoins;
            $filterWhere = $cnaeByLevel[$currentLevel]['code'] . ' = :code';
        }

        $sql = "
            select {$relatedCode} as code,
                   {$relatedName} as name,
                   max(j.nu_jac)::numeric as jac,
                   sum(coalesce(j.rais_n_vinc, 0))::bigint as vinculos,
                   {$selectPercentCurrent}::numeric as percent_current,
                   {$selectPercentRelated}::numeric as percent_related,
                   coalesce(max(cl_direct.no_classificacao), 'Não classificado') as classificacao,
                   max(mc_direct.co_classificacao::text) as classificacao_code,
                   case
                       when count(distinct coalesce(mv_related.co_classificacao_herdada::text, '9')) > 1 then 'Mista'
                       else coalesce(max(cl_inherited.no_classificacao), 'Não classificado')
                   end as classificacao_herdada,
                   case
                       when count(distinct coalesce(mv_related.co_classificacao_herdada::text, '9')) > 1 then 'mixed'
                       else max(coalesce(mv_related.co_classificacao_herdada::text, '9'))
                   end as classificacao_herdada_code,
                   count(*)::int as total_pares
              from mvw_jac_subc_ocup j
              {$relatedJoin}
              left join tb_matriz_classificacao mc_direct
                     on mc_direct.id_matriz = :matrix_id
                    and mc_direct.co_tp_objeto = '{$relatedType}'
                    and mc_direct.co_objeto = {$relatedCode}
              left join tb_classificacao cl_direct
                     on cl_direct.co_classificacao = mc_direct.co_classificacao
              left join mvw_matriz_classificacao_herdada mv_related
                     on mv_related.id_matriz = :matrix_id
                    and mv_related.n5_co_tp_objeto = '{$relatedN5Type}'
                    and mv_related.n5_co_objeto = {$relatedN5Code}
              left join tb_classificacao cl_inherited
                     on cl_inherited.co_classificacao = mv_related.co_classificacao_herdada
             where {$filterWhere}
               and {$relatedCode} is not null
             group by {$relatedCode}, {$relatedName}
             order by max(j.nu_jac) desc nulls last, sum(coalesce(j.rais_n_vinc, 0)) desc, {$relatedCode}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['code' => $code, 'matrix_id' => $matrixId]);

        return array_map(static fn (array $row): array => [
            'code' => (string) $row['code'],
            'name' => $row['name'] === null ? '' : (string) $row['name'],
            'type' => $relatedType,
            'origin' => $isCbo ? 'CNAE' : 'CBO',
            'level' => $currentLevel,
            'classification' => (string) $row['classificacao'],
            'classification_code' => $row['classificacao_code'] === null ? null : (string) $row['classificacao_code'],
            'inherited_classification' => (string) $row['classificacao_herdada'],
            'inherited_classification_code' => $row['classificacao_herdada_code'] === null ? null : (string) $row['classificacao_herdada_code'],
            'jac' => $row['jac'] === null ? null : (float) $row['jac'],
            'vinculos' => (int) $row['vinculos'],
            'percent_current' => $row['percent_current'] === null ? null : (float) $row['percent_current'],
            'percent_related' => $row['percent_related'] === null ? null : (float) $row['percent_related'],
            'total_pares' => (int) $row['total_pares'],
        ], $stmt->fetchAll());
    }

    /**
     * @return array{cnae: string, cbo: string}
     */
    private function jacColumns(): array
    {
        if ($this->jacColumns !== null) {
            return $this->jacColumns;
        }

        $statement = $this->pdo->prepare(
            "select column_name
               from information_schema.columns
              where table_schema = current_schema()
                and table_name = 'mvw_jac_subc_ocup'"
        );
        $statement->execute();
        $columns = array_map(static fn (array $row): string => (string) $row['column_name'], $statement->fetchAll());

        $this->jacColumns = [
            'cnae' => in_array('co_cnae', $columns, true) ? 'co_cnae' : 'co_cnae_subc',
            'cbo' => in_array('co_cbo', $columns, true) ? 'co_cbo' : 'co_cbo_ocup',
        ];

        return $this->jacColumns;
    }

    private function typeLevel(string $type): ?string
    {
        return [
            'cbo_gran_grup' => 'L1',
            'cbo_subg_prin' => 'L2',
            'cbo_subg' => 'L3',
            'cbo_fami' => 'L4',
            'cbo_ocup' => 'L5',
            'cnae_seca' => 'L1',
            'cnae_divi' => 'L2',
            'cnae_grup' => 'L3',
            'cnae_clas' => 'L4',
            'cnae_subc' => 'L5',
        ][$type] ?? null;
    }

    public function getLevelChildren(string $matrixId, bool $isCbo, string $level, string $parentCode): array
    {
        if ($parentCode === '') {
            return [];
        }

        $config = [];
        if ($isCbo) {
            $config = [
                'L1' => ['table' => 'cbo_subg_prin', 'code' => 'co_cbo_subg_prin', 'name' => 'no_cbo_subg_prin', 'parent_col' => 'co_cbo_gran_grup', 'type' => 'cbo_subg_prin'],
                'L2' => ['table' => 'cbo_subg', 'code' => 'co_cbo_subg', 'name' => 'no_cbo_subg', 'parent_col' => 'co_cbo_subg_prin', 'type' => 'cbo_subg'],
                'L3' => ['table' => 'cbo_fami', 'code' => 'co_cbo_fami', 'name' => 'no_cbo_fami', 'parent_col' => 'co_cbo_subg', 'type' => 'cbo_fami'],
                'L4' => ['table' => 'cbo_ocup', 'code' => 'co_cbo_ocup', 'name' => 'no_cbo_ocup', 'parent_col' => 'co_cbo_fami', 'type' => 'cbo_ocup'],
            ];
        } else {
            $config = [
                'L1' => ['table' => 'cnae_divi', 'code' => 'co_cnae_divi', 'name' => 'no_cnae_divi', 'parent_col' => 'co_cnae_seca', 'type' => 'cnae_divi'],
                'L2' => ['table' => 'cnae_grup', 'code' => 'co_cnae_grup', 'name' => 'no_cnae_grup', 'parent_col' => 'co_cnae_divi', 'type' => 'cnae_grup'],
                'L3' => ['table' => 'cnae_clas', 'code' => 'co_cnae_clas', 'name' => 'no_cnae_clas', 'parent_col' => 'co_cnae_grup', 'type' => 'cnae_clas'],
                'L4' => ['table' => 'cnae_subc', 'code' => 'co_cnae_subc', 'name' => 'no_cnae_subc', 'parent_col' => 'co_cnae_clas', 'type' => 'cnae_subc'],
            ];
        }

        if (!isset($config[$level])) {
            return [];
        }

        $cfg = $config[$level];
        $isL5Child = ($level === 'L4');

        $sql = "
            SELECT 
                ch.{$cfg['code']} AS code,
                ch.{$cfg['name']} AS name,
                COALESCE(
                    cl_direct.no_classificacao,
                    " . ($isL5Child ? "cl_cons.no_classificacao" : "NULL") . ",
                    'Não classificado'
                ) AS classification,
                COALESCE(
                    mc.co_classificacao,
                    " . ($isL5Child ? "mv.co_classificacao_herdada" : "NULL") . "
                ) AS classification_code
            FROM {$cfg['table']} ch
            LEFT JOIN tb_matriz_classificacao mc 
                   ON mc.co_objeto = ch.{$cfg['code']} 
                  AND mc.id_matriz = :matrix_id
            LEFT JOIN tb_classificacao cl_direct 
                   ON cl_direct.co_classificacao = mc.co_classificacao
        ";

        if ($isL5Child) {
            $sql .= "
                LEFT JOIN mvw_matriz_classificacao_herdada mv 
                       ON mv.n5_co_objeto = ch.{$cfg['code']} 
                      AND mv.n5_co_tp_objeto = :type
                      AND mv.id_matriz = :matrix_id
                LEFT JOIN tb_classificacao cl_cons 
                       ON cl_cons.co_classificacao = mv.co_classificacao_herdada
            ";
        }

        $sql .= "
            WHERE ch.{$cfg['parent_col']} = :parent_code
            ORDER BY ch.{$cfg['code']}
        ";

        $stmt = $this->pdo->prepare($sql);
        $params = [
            'matrix_id' => $matrixId,
            'parent_code' => $parentCode,
        ];
        if ($isL5Child) {
            $params['type'] = $cfg['type'];
        }

        $stmt->execute($params);
        $results = [];
        foreach ($stmt->fetchAll() as $row) {
            $results[] = [
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
                'classification' => (string) $row['classification'],
                'classification_code' => $row['classification_code'] !== null ? (string) $row['classification_code'] : null,
                'type' => $cfg['type'],
            ];
        }
        return $results;
    }

    private function getTypeLabel(string $type): string
    {
        return [
            'cbo_ocup' => 'CBO Ocupação',
            'cbo_fami' => 'CBO Família',
            'cbo_subg' => 'CBO Subgrupo',
            'cbo_subg_prin' => 'CBO Subgrupo Principal',
            'cbo_gran_grup' => 'CBO Grande Grupo',
            'cnae_subc' => 'CNAE Subclasse',
            'cnae_clas' => 'CNAE Classe',
            'cnae_grup' => 'CNAE Grupo',
            'cnae_divi' => 'CNAE Divisão',
            'cnae_seca' => 'CNAE Seção'
        ][$type] ?? $type;
    }

    private function getParentName(string $table, string $codeCol, string $nameCol, string $code): string
    {
        if ($code === '') {
            return '-';
        }
        $stmt = $this->pdo->prepare("SELECT {$nameCol} FROM {$table} WHERE {$codeCol} = :code");
        $stmt->execute(['code' => $code]);
        $name = (string) $stmt->fetchColumn();
        return $name !== '' ? $name : '-';
    }
}
