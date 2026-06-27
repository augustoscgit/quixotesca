<?php

declare(strict_types=1);

use Carex\Database\Connection;

$config = require dirname(__DIR__) . '/src/bootstrap.php';

$schema = $config['database']['schema'];
$pdo = Connection::make($config['database']);

$tables = $pdo->prepare(
    "select table_name
       from information_schema.tables
      where table_schema = :schema
        and table_type = 'BASE TABLE'
      order by table_name"
);
$tables->execute(['schema' => $schema]);

$columns = $pdo->prepare(
    "select table_name, column_name, data_type, is_nullable, ordinal_position
       from information_schema.columns
      where table_schema = :schema
      order by table_name, ordinal_position"
);
$columns->execute(['schema' => $schema]);

echo json_encode([
    'schema' => $schema,
    'tables' => $tables->fetchAll(),
    'columns' => $columns->fetchAll(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
