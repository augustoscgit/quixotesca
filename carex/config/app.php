<?php

declare(strict_types=1);

use Carex\Support\Env;

return [
    'app' => [
        'env' => Env::get('APP_ENV', 'production'),
        'debug' => Env::bool('APP_DEBUG', false),
    ],
    'database' => [
        'host' => Env::required('DB_HOST'),
        'port' => Env::get('DB_PORT', '5432'),
        'database' => Env::required('DB_DATABASE'),
        'username' => Env::required('DB_USERNAME'),
        'password' => Env::required('DB_PASSWORD'),
        'schema' => Env::required('DB_SCHEMA'),
        'sslmode' => Env::get('DB_SSLMODE', ''),
        'allow_writes' => Env::bool('DB_ALLOW_WRITES', false),
    ],
];
