<?php

declare(strict_types=1);

namespace Carex\Database;

use PDO;

final class SchemaRepository
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
     * @return array<int, array{name: string, type: string, columns: array<int, array{name: string, type: string, nullable: bool}>}>
     */
    public function readableObjects(): array
    {
        $statement = $this->pdo->prepare(
            "select c.relname as object_name,
                    case c.relkind
                        when 'r' then 'table'
                        when 'p' then 'table'
                        when 'f' then 'table'
                        when 'v' then 'view'
                        when 'm' then 'materialized_view'
                    end as object_type,
                    a.attname as column_name,
                    format_type(a.atttypid, a.atttypmod) as data_type,
                    a.attnotnull as is_not_null,
                    a.attnum as ordinal_position
               from pg_class c
               join pg_namespace n on n.oid = c.relnamespace
               join pg_attribute a on a.attrelid = c.oid
              where n.nspname = :schema
                and c.relkind in ('r', 'p', 'f', 'v', 'm')
                and a.attnum > 0
                and not a.attisdropped
              order by object_type, c.relname, a.attnum"
        );
        $statement->execute(['schema' => $this->schema]);

        $objects = [];
        foreach ($statement->fetchAll() as $row) {
            $object = (string) $row['object_name'];

            if (!isset($objects[$object])) {
                $objects[$object] = [
                    'name' => $object,
                    'type' => (string) $row['object_type'],
                    'columns' => [],
                ];
            }

            $objects[$object]['columns'][] = [
                'name' => (string) $row['column_name'],
                'type' => (string) $row['data_type'],
                'nullable' => !in_array($row['is_not_null'], [true, 't', 'true', '1', 1], true),
            ];
        }

        return array_values($objects);
    }

    /**
     * @return array<int, array{name: string, type: string, columns: array<int, array{name: string, type: string, nullable: bool}>}>
     */
    public function tables(): array
    {
        return array_values(array_filter(
            $this->readableObjects(),
            static fn (array $object): bool => $object['type'] === 'table'
        ));
    }

    /**
     * @return array<int, array{name: string, type: string, nullable: bool}>
     */
    public function columnsFor(string $table): array
    {
        foreach ($this->readableObjects() as $knownObject) {
            if ($knownObject['name'] === $table) {
                return $knownObject['columns'];
            }
        }

        return [];
    }

    public function tableExists(string $table): bool
    {
        return $this->columnsFor($table) !== [];
    }
}
