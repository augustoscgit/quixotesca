<?php

declare(strict_types=1);

use Carex\Database\Connection;

$config = require dirname(__DIR__) . '/src/bootstrap.php';

try {
    $pdo = Connection::make($config['database']);
    $row = $pdo->query('select current_database() as db, current_user as user_name, current_schema() as schema_name')
        ->fetch();
    $readOnly = $pdo->query('show default_transaction_read_only')->fetchColumn();
    $statementTimeout = $pdo->query('show statement_timeout')->fetchColumn();

    echo "CONNECTED db={$row['db']} user={$row['user_name']} schema={$row['schema_name']} read_only={$readOnly} statement_timeout={$statementTimeout}\n";
} catch (Throwable $error) {
    fwrite(STDERR, "ERROR: {$error->getMessage()}\n");
    exit(1);
}
