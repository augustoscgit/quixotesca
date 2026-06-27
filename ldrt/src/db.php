<?php
/**
 * Database connection helper using PDO and .env credentials
 */

function getDBConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $envPath = __DIR__ . '/../secrets/.env';
    if (!is_file($envPath) || !is_readable($envPath)) {
        throw new Exception("Configuration file (.env) not found.");
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        throw new Exception("Configuration file (.env) could not be read.");
    }

    $env = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim(trim($value), "\"'");
        if ($name !== '') {
            $env[$name] = $value;
        }
    }

    $host = $env['DB_HOST'] ?? '';
    $port = $env['DB_PORT'] ?? '5432';
    $db   = $env['DB_DATABASE'] ?? '';
    $user = $env['DB_USERNAME'] ?? '';
    $pass = $env['DB_PASSWORD'] ?? '';
    $schema = $env['DB_SCHEMA'] ?? 'ldrt';
    $sslmode = $env['DB_SSLMODE'] ?? '';

    foreach (['DB_HOST' => $host, 'DB_DATABASE' => $db, 'DB_USERNAME' => $user, 'DB_PASSWORD' => $pass] as $key => $value) {
        if ($value === '') {
            throw new Exception("$key not configured.");
        }
    }

    $dsn = "pgsql:host=$host;port=$port;dbname=$db";
    if ($sslmode !== '') {
        $dsn .= ";sslmode=$sslmode";
    }
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        $pdo->exec("SET client_encoding TO 'UTF8'");
        $quotedSchema = '"' . str_replace('"', '""', $schema) . '"';
        $pdo->exec("SET search_path TO $quotedSchema, public");
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}
