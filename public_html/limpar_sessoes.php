<?php
/**
 * Script de Limpeza de Sessões - Plataforma Renast Online
 * 
 * Este script varre os diretórios de sessões personalizadas dos módulos
 * (como fichario/private/sessions/) e limpa arquivos de sessões expirados
 * ou vazios para economizar espaço em disco e manter o servidor otimizado.
 * 
 * Pode ser executado via CLI (linha de comando / cronjob) ou via Web.
 */

declare(strict_types=1);

function load_cleanup_env_file(string $path): void
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
        }
    }
}

load_cleanup_env_file(__DIR__ . '/../secrets/.env');
load_cleanup_env_file(__DIR__ . '/../acesso/secrets/.env');

// Segurança: se executado via Web, exige token configurado fora do código.
if (PHP_SAPI !== 'cli') {
    $tokenHeader = $_SERVER['HTTP_X_CLEANUP_TOKEN'] ?? '';
    $tokenQuery = $_GET['token'] ?? '';
    $expectedToken = (string) (getenv('SESSION_CLEANUP_TOKEN') ?: '');

    if ($expectedToken === '' || (!hash_equals($expectedToken, (string) $tokenHeader) && !hash_equals($expectedToken, (string) $tokenQuery))) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Acesso negado. Chave de acesso invalida, ausente ou nao configurada.']);
        exit;
    }
}

// Configurações de diretórios de sessão a limpar
$sessionDirectories = [
    __DIR__ . '/../acesso/private/sessions',
    __DIR__ . '/../carex/private/sessions',
    __DIR__ . '/../fichario/private/sessions',
];

// Tempo limite padrão de inatividade (4 horas = 14400 segundos)
$sessionLifetime = 14400;
// Tempo limite para sessões vazias (15 minutos = 900 segundos)
$emptyLifetime = 900;

$now = time();
$results = [];
$totalRemoved = 0;

foreach ($sessionDirectories as $dir) {
    $key = str_replace([__DIR__ . '/', __DIR__ . '\\'], '', $dir);
    if (!is_dir($dir) || !is_writable($dir)) {
        $results[$key] = 'Diretório não existe ou não possui permissão de escrita.';
        continue;
    }

    $removed = 0;
    $files = glob($dir . DIRECTORY_SEPARATOR . 'sess_*');
    
    if ($files === false) {
        $results[$key] = 'Falha ao listar arquivos.';
        continue;
    }

    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }

        $age = $now - (int) filemtime($file);
        $isEmpty = (int) filesize($file) === 0;

        // Limpa se a sessão expirou ou se está vazia e ociosa por mais de 15 min
        if ($age > $sessionLifetime || ($isEmpty && $age > $emptyLifetime)) {
            if (@unlink($file)) {
                $removed++;
            }
        }
    }

    $results[$key] = "Limpeza realizada com sucesso. Arquivos removidos: $removed de " . count($files) . " analisados.";
    $totalRemoved += $removed;
}

// Retorna o resultado no formato adequado
if (PHP_SAPI === 'cli') {
    echo "=== Relatório de Limpeza de Sessões (" . date('Y-m-d H:i:s') . ") ===\n";
    foreach ($results as $dirName => $status) {
        echo "- $dirName: $status\n";
    }
    echo "Total de sessões removidas: $totalRemoved\n";
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'timestamp' => date('c'),
        'results' => $results,
        'total_removed' => $totalRemoved
    ], JSON_PRETTY_PRINT);
}
