<?php

declare(strict_types=1);

namespace Carex\Database;

use PDO;
use RuntimeException;

final class Connection
{
    /**
     * @param array<string, string> $config
     */
    public static function make(array $config): PDO
    {
        if (($config['password'] ?? '') === '') {
            throw new RuntimeException('DB_PASSWORD nao configurado.');
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $config['host'],
            $config['port'],
            $config['database']
        );
        if (($config['sslmode'] ?? '') !== '') {
            $dsn .= ';sslmode=' . $config['sslmode'];
        }

        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $pdo->exec("SET client_encoding TO 'UTF8'");

        $schema = self::quoteIdentifier($config['schema']);
        $pdo->exec("SET application_name TO 'carex-web'");
        $pdo->exec("SET search_path TO {$schema}");
        $pdo->exec("SET statement_timeout TO '30000ms'");
        $pdo->exec("SET idle_in_transaction_session_timeout TO '10000ms'");

        if (($config['allow_writes'] ?? false) !== true) {
            $pdo->exec('SET default_transaction_read_only TO on');
        }

        return $pdo;
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function writesAllowed(array $config): bool
    {
        return ($config['allow_writes'] ?? false) === true;
    }

    public static function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
