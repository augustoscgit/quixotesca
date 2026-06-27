<?php
// Function to load .env variables
if (!function_exists('load_platform_env')) {
function load_platform_env(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim(trim($value), "\"'");

        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
}

// Load env at the root secrets folder
load_platform_env(__DIR__ . '/../secrets/.env');

$host = getenv('DB_HOST') ?: $_ENV['DB_HOST'] ?? 'localhost';
$db   = getenv('DB_DATABASE') ?: $_ENV['DB_DATABASE'] ?? '';
$user = getenv('DB_USERNAME') ?: $_ENV['DB_USERNAME'] ?? '';
$pass = getenv('DB_PASSWORD') ?: $_ENV['DB_PASSWORD'] ?? '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Corrige acentuação para banco em latin1
$conn->set_charset("latin1");
?>
