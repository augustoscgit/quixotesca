<?php

declare(strict_types=1);

namespace Carex\Database;

use PDO;

final class ReadonlyRepository
{
    private const MAX_PER_PAGE = 100;

    private PDO $pdo;
    private string $schema;
    private SchemaRepository $schemaRepository;

    public function __construct(
        PDO $pdo,
        string $schema,
        SchemaRepository $schemaRepository
    ) {
        $this->pdo = $pdo;
        $this->schema = $schema;
        $this->schemaRepository = $schemaRepository;
    }

    /**
     * @return array{table: string, columns: array<int, string>, rows: array<int, array<string, mixed>>, page: int, per_page: int, total: int, total_pages: int}
     */
    public function rows(string $table, int $page, int $perPage, string $query, string $sort, string $direction, array $filters = []): array
    {
        $columns = $this->schemaRepository->columnsFor($table);

        if ($columns === []) {
            throw new \InvalidArgumentException('Tabela nao encontrada.');
        }

        $columnNames = array_map(static fn (array $column): string => $column['name'], $columns);
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), self::MAX_PER_PAGE);
        $offset = ($page - 1) * $perPage;
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $sort = in_array($sort, $columnNames, true) ? $sort : $columnNames[0];

        $params = [];
        $whereConditions = [];

        if ($query !== '') {
            $parts = [];
            foreach ($columnNames as $index => $column) {
                $param = "q{$index}";
                $parts[] = 'cast(' . Connection::quoteIdentifier($column) . " as text) ilike :{$param}";
                $params[$param] = '%' . $query . '%';
            }
            $whereConditions[] = '(' . implode(' or ', $parts) . ')';
        }

        if ($filters !== []) {
            foreach ($filters as $fIdx => $filter) {
                $column = $filter['column'] ?? '';
                $values = $filter['values'] ?? [];
                if ($column === '' || $values === [] || !in_array($column, $columnNames, true)) {
                    continue;
                }

                $valuePlaceholders = [];
                foreach ($values as $vIdx => $val) {
                    $paramName = "f_{$fIdx}_{$vIdx}";
                    $valuePlaceholders[] = ":{$paramName}";
                    $params[$paramName] = (string) $val;
                }

                if ($valuePlaceholders !== []) {
                    $whereConditions[] = 'cast(' . Connection::quoteIdentifier($column) . ' as text) in (' . implode(', ', $valuePlaceholders) . ')';
                }
            }
        }

        $where = $whereConditions !== [] ? ' where ' . implode(' and ', $whereConditions) : '';

        $qualifiedTable = Connection::quoteIdentifier($this->schema) . '.' . Connection::quoteIdentifier($table);
        $orderBy = Connection::quoteIdentifier($sort) . ' ' . $direction;

        $countStatement = $this->pdo->prepare("select count(*) from {$qualifiedTable}{$where}");
        $countStatement->execute($params);
        $total = (int) $countStatement->fetchColumn();

        $dataStatement = $this->pdo->prepare(
            "select *
               from {$qualifiedTable}
               {$where}
              order by {$orderBy}
              limit :limit offset :offset"
        );

        foreach ($params as $key => $value) {
            $dataStatement->bindValue($key, $value);
        }
        $dataStatement->bindValue('limit', $perPage, PDO::PARAM_INT);
        $dataStatement->bindValue('offset', $offset, PDO::PARAM_INT);
        $dataStatement->execute();

        return [
            'table' => $table,
            'columns' => $columnNames,
            'rows' => $dataStatement->fetchAll(),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function uniqueValues(string $table, string $column): array
    {
        $columns = $this->schemaRepository->columnsFor($table);
        $columnNames = array_map(static fn (array $col): string => $col['name'], $columns);

        if (!in_array($column, $columnNames, true)) {
            throw new \InvalidArgumentException('Coluna nao encontrada.');
        }

        $qualifiedTable = Connection::quoteIdentifier($this->schema) . '.' . Connection::quoteIdentifier($table);
        $quotedColumn = Connection::quoteIdentifier($column);

        $statement = $this->pdo->prepare(
            "select distinct cast({$quotedColumn} as text) as val
               from {$qualifiedTable}
              where {$quotedColumn} is not null
              order by val asc
              limit 1000"
        );
        $statement->execute();

        return array_map(static fn (array $row): string => (string) ($row['val'] ?? ''), $statement->fetchAll());
    }
}
