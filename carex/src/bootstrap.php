<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Carex\\';
    $baseDir = __DIR__ . DIRECTORY_SEPARATOR;

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

// Carrega o .env da raiz do projeto, se existir
\Carex\Support\Env::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');
// Carrega o .env da pasta secrets, se existir
\Carex\Support\Env::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'secrets' . DIRECTORY_SEPARATOR . '.env');

return require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
