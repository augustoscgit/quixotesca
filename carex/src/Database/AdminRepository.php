<?php

declare(strict_types=1);

namespace Carex\Database;

use PDO;

final class AdminRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function specialists(): array
    {
        $statement = $this->pdo->query(
            "select e.id_especialista,
                    e.no_especialista,
                    coalesce(
                        json_agg(
                            json_build_object(
                                'id_matriz', m.id_matriz,
                                'no_matriz', m.no_matriz,
                                'total_itens', coalesce(stats.total_itens, 0),
                                'total_classificados', coalesce(stats.total_classificados, 0),
                                'percentual_classificado', coalesce(stats.percentual_classificado, 0)
                            )
                            order by m.no_matriz
                        ) filter (where m.id_matriz is not null),
                        '[]'::json
                    ) as vinculacoes
               from tb_especialista e
               left join tb_matriz_especialista me on me.id_especialista = e.id_especialista
               left join tb_matriz m on m.id_matriz = me.id_matriz
               left join (
                    select id_matriz,
                           count(*) as total_itens,
                           count(co_classificacao) filter (
                               where co_classificacao is not null
                                 and btrim(co_classificacao) <> ''
                                 and co_classificacao <> '9'
                           ) as total_classificados,
                           case
                               when count(*) = 0 then 0
                               else round((count(co_classificacao) filter (
                                   where co_classificacao is not null
                                     and btrim(co_classificacao) <> ''
                                     and co_classificacao <> '9'
                               )::numeric / count(*)::numeric) * 100, 1)
                           end as percentual_classificado
                      from tb_matriz_classificacao
                     group by id_matriz
               ) stats on stats.id_matriz = m.id_matriz
              group by e.id_especialista, e.no_especialista
              order by e.no_especialista"
        );

        return array_map(static function (array $row): array {
            $links = json_decode((string) $row['vinculacoes'], true);
            $links = is_array($links) ? $links : [];

            return [
                'id_especialista' => (int) $row['id_especialista'],
                'no_especialista' => (string) $row['no_especialista'],
                'total_vinculacoes' => count($links),
                'vinculacoes' => array_map(static fn (array $link): array => [
                    'id_matriz' => (string) $link['id_matriz'],
                    'no_matriz' => (string) $link['no_matriz'],
                    'total_itens' => (int) $link['total_itens'],
                    'total_classificados' => (int) $link['total_classificados'],
                    'percentual_classificado' => (float) $link['percentual_classificado'],
                ], $links),
            ];
        }, $statement->fetchAll());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function materializedViews(): array
    {
        $statement = $this->pdo->prepare(
            "select mv.matviewname as name,
                    mv.hasindexes,
                    mv.ispopulated,
                    greatest(coalesce(c.reltuples, 0), 0)::bigint as estimated_rows,
                    obj_description(c.oid, 'pg_class') as comment
               from pg_matviews mv
               join pg_namespace n on n.nspname = mv.schemaname
               join pg_class c on c.relnamespace = n.oid
                              and c.relname = mv.matviewname
              where mv.schemaname = current_schema()
              order by mv.matviewname"
        );
        $statement->execute();

        return array_map(static fn (array $row): array => [
            'name' => (string) $row['name'],
            'hasindexes' => (bool) $row['hasindexes'],
            'ispopulated' => (bool) $row['ispopulated'],
            'estimated_rows' => (int) $row['estimated_rows'],
            'comment' => $row['comment'] === null ? '' : (string) $row['comment'],
        ], $statement->fetchAll());
    }

    public function refreshMaterializedView(string $name): void
    {
        $knownNames = array_map(
            static fn (array $view): string => $view['name'],
            $this->materializedViews()
        );

        if (!in_array($name, $knownNames, true)) {
            throw new InvalidArgumentException('View materializada nao encontrada.');
        }

        $schema = Connection::quoteIdentifier($this->currentSchema());
        $view = Connection::quoteIdentifier($name);

        $this->pdo->exec("refresh materialized view {$schema}.{$view}");
    }

    private function currentSchema(): string
    {
        return (string) $this->pdo->query('select current_schema()')->fetchColumn();
    }
}
