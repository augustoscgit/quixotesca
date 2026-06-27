<?php

declare(strict_types=1);

namespace Carex\Database;

use PDO;

final class DevelopmentInventoryRepository
{
    private PDO $pdo;
    private string $schema;

    public function __construct(
        PDO $pdo,
        string $schema
    ) {
        $this->pdo = $pdo;
        $this->schema = $schema;
    }

    /**
     * @return array<string, mixed>
     */
    public function inventory(): array
    {
        return [
            'schema' => $this->schema,
            'relations' => $this->relations(),
            'views' => $this->views(),
            'materialized_views' => $this->materializedViews(),
            'triggers' => $this->triggers(),
            'routines' => $this->routines(),
            'sequences' => $this->sequences(),
            'indexes' => $this->indexes(),
            'constraints' => $this->constraints(),
            'types' => $this->types(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function relations(): array
    {
        $statement = $this->pdo->prepare(
            "select c.relname as name,
                    case c.relkind
                        when 'r' then 'table'
                        when 'p' then 'partitioned_table'
                        when 'v' then 'view'
                        when 'm' then 'materialized_view'
                        when 'S' then 'sequence'
                        when 'f' then 'foreign_table'
                        else c.relkind::text
                    end as type,
                    greatest(coalesce(s.n_live_tup, c.reltuples, 0), 0)::bigint as estimated_rows,
                    obj_description(c.oid, 'pg_class') as comment
               from pg_class c
               join pg_namespace n on n.oid = c.relnamespace
               left join pg_stat_user_tables s on s.relid = c.oid
              where n.nspname = :schema
                and c.relkind in ('r', 'p', 'v', 'm', 'S', 'f')
              order by type, name"
        );
        $statement->execute(['schema' => $this->schema]);

        $relations = $statement->fetchAll();
        foreach ($relations as &$relation) {
            $relation['estimated_rows'] = (int) $relation['estimated_rows'];
        }

        return $relations;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function views(): array
    {
        $statement = $this->pdo->prepare(
            "select viewname as name,
                    definition
               from pg_views
              where schemaname = :schema
              order by viewname"
        );
        $statement->execute(['schema' => $this->schema]);

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function materializedViews(): array
    {
        $statement = $this->pdo->prepare(
            "select matviewname as name,
                    hasindexes,
                    ispopulated,
                    definition
               from pg_matviews
              where schemaname = :schema
              order by matviewname"
        );
        $statement->execute(['schema' => $this->schema]);

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function triggers(): array
    {
        $statement = $this->pdo->prepare(
            "select t.tgname as name,
                    c.relname as table_name,
                    case t.tgenabled
                        when 'O' then 'enabled'
                        when 'D' then 'disabled'
                        when 'R' then 'replica'
                        when 'A' then 'always'
                        else t.tgenabled::text
                    end as status,
                    p.proname as function_name,
                    pg_get_triggerdef(t.oid, true) as definition
               from pg_trigger t
               join pg_class c on c.oid = t.tgrelid
               join pg_namespace n on n.oid = c.relnamespace
               join pg_proc p on p.oid = t.tgfoid
              where n.nspname = :schema
                and not t.tgisinternal
              order by c.relname, t.tgname"
        );
        $statement->execute(['schema' => $this->schema]);

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function routines(): array
    {
        $statement = $this->pdo->prepare(
            "select p.proname as name,
                    case p.prokind
                        when 'f' then 'function'
                        when 'p' then 'procedure'
                        when 'a' then 'aggregate'
                        when 'w' then 'window'
                        else p.prokind::text
                    end as type,
                    l.lanname as language,
                    pg_get_function_arguments(p.oid) as arguments,
                    pg_get_function_result(p.oid) as returns,
                    pg_get_functiondef(p.oid) as definition
               from pg_proc p
               join pg_namespace n on n.oid = p.pronamespace
               join pg_language l on l.oid = p.prolang
              where n.nspname = :schema
              order by p.proname, arguments"
        );
        $statement->execute(['schema' => $this->schema]);

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sequences(): array
    {
        $statement = $this->pdo->prepare(
            "select sequencename as name,
                    data_type,
                    start_value,
                    min_value,
                    max_value,
                    increment_by,
                    cycle,
                    cache_size
               from pg_sequences
              where schemaname = :schema
              order by sequencename"
        );
        $statement->execute(['schema' => $this->schema]);

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function indexes(): array
    {
        $statement = $this->pdo->prepare(
            "select tablename as table_name,
                    indexname as name,
                    indexdef as definition
               from pg_indexes
              where schemaname = :schema
              order by tablename, indexname"
        );
        $statement->execute(['schema' => $this->schema]);

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function constraints(): array
    {
        $statement = $this->pdo->prepare(
            "select con.conname as name,
                    c.relname as table_name,
                    case con.contype
                        when 'p' then 'primary_key'
                        when 'f' then 'foreign_key'
                        when 'u' then 'unique'
                        when 'c' then 'check'
                        when 'x' then 'exclusion'
                        else con.contype::text
                    end as type,
                    pg_get_constraintdef(con.oid, true) as definition
               from pg_constraint con
               join pg_class c on c.oid = con.conrelid
               join pg_namespace n on n.oid = c.relnamespace
              where n.nspname = :schema
              order by c.relname, con.conname"
        );
        $statement->execute(['schema' => $this->schema]);

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function types(): array
    {
        $statement = $this->pdo->prepare(
            "select t.typname as name,
                    case t.typtype
                        when 'b' then 'base'
                        when 'c' then 'composite'
                        when 'd' then 'domain'
                        when 'e' then 'enum'
                        when 'p' then 'pseudo'
                        when 'r' then 'range'
                        when 'm' then 'multirange'
                        else t.typtype::text
                    end as type,
                    coalesce(string_agg(e.enumlabel, ', ' order by e.enumsortorder), '') as labels
               from pg_type t
               join pg_namespace n on n.oid = t.typnamespace
               left join pg_enum e on e.enumtypid = t.oid
              where n.nspname = :schema
                and t.typtype in ('c', 'd', 'e', 'r', 'm')
              group by t.typname, t.typtype
              order by type, name"
        );
        $statement->execute(['schema' => $this->schema]);

        return $statement->fetchAll();
    }
}
