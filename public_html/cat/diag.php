<?php
/**
 * Diagnostic Script for CAT Module
 */
require_once __DIR__ . '/../../acesso/src/bootstrap.php';

require_platform_admin();

if (app_debug_enabled()) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== CAT MODULE DIAGNOSTICS ===\n\n";

// 1. Check PHP Version
echo "1. PHP Version:\n";
echo "   Current version: " . PHP_VERSION . "\n\n";

// 2. Check Secrets & .env
echo "2. Checking secrets directory and .env file:\n";
$secretsDir = __DIR__ . '/secrets';
$envPath = $secretsDir . '/.env';

if (!is_dir($secretsDir)) {
    echo "   [ERROR] Secrets directory does NOT exist at: $secretsDir\n";
} else {
    echo "   [OK] Secrets directory exists.\n";
    if (!file_exists($envPath)) {
        echo "   [ERROR] .env file does NOT exist at: $envPath\n";
    } else {
        echo "   [OK] .env file found.\n";
        
        $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            echo "   [ERROR] Cannot read .env file. Check file permissions.\n";
        } else {
            echo "   [OK] .env file is readable. Loaded variables (without passwords):\n";
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                $parts = explode('=', $line, 2);
                if (count($parts) == 2) {
                    $name = trim($parts[0]);
                    $val = trim($parts[1]);
                    if (strpos($name, 'PASS') !== false || strpos($name, 'KEY') !== false) {
                        $val = '[HIDDEN]';
                    }
                    echo "        $name = $val\n";
                }
            }
        }
    }
}
echo "\n";

// 3. Check PHP extensions
echo "3. Checking database and utility extensions:\n";
$extensions = ['pdo', 'pdo_pgsql', 'pgsql', 'curl', 'zip', 'mbstring'];
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "   [OK] Extension '$ext' is LOADED.\n";
    } else {
        echo "   [ERROR] Extension '$ext' is NOT loaded.\n";
        if ($ext === 'pdo_pgsql') {
            echo "           (Required for PostgreSQL connection)\n";
        }
        if ($ext === 'zip') {
            echo "           (Required to extract zipped CSV datasets)\n";
        }
        if ($ext === 'curl') {
            echo "           (Required to download government datasets)\n";
        }
    }
}
echo "\n";

// 4. Folder permissions
echo "4. Checking temporary directory permissions:\n";
$tmpDir = __DIR__ . '/tmp';
if (!is_dir($tmpDir)) {
    @mkdir($tmpDir, 0755, true);
}
if (is_dir($tmpDir)) {
    echo "   [OK] Temporary directory exists at: $tmpDir\n";
    if (is_writable($tmpDir)) {
        echo "   [OK] Temporary directory is WRITABLE.\n";
    } else {
        echo "   [ERROR] Temporary directory is NOT writable. Check permissions.\n";
    }
} else {
    echo "   [ERROR] Temporary directory does not exist and could not be created.\n";
}
echo "\n";

// 5. Try database connection
echo "5. Testing Database Connection & Schema:\n";
if (file_exists($envPath) && extension_loaded('pdo_pgsql')) {
    try {
        require_once __DIR__ . '/../../cat/src/db.php';
        $db = getDBConnection();
        echo "   [OK] Successfully connected to the database!\n";
        
        $filesCount = $db->query("SELECT COUNT(*) FROM arquivos_importacao")->fetchColumn();
        echo "   [OK] Query executed successfully. Total files: $filesCount\n";
        
        $rowsCount = $db->query("SELECT COUNT(*) FROM registros_brutos")->fetchColumn();
        echo "   [OK] Query executed successfully. Total rows loaded: $rowsCount\n";
    } catch (Exception $e) {
        echo "   [ERROR] Database connection/schema failed:\n";
        echo "           " . $e->getMessage() . "\n";
    }
} else {
    echo "   [SKIP] Cannot test connection due to missing .env or missing pdo_pgsql extension.\n";
}
echo "\n";

// 6. Test Outbound Connectivity
echo "6. Testing Outbound Connectivity to CKAN API:\n";
if (extension_loaded('curl')) {
    $url = 'https://dadosabertos.inss.gov.br/api/3/action/package_show?id=comunicacoes-de-acidente-de-trabalho-cat-plano-de-dados-abertos-jun-2023-a-jun-2025';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        echo "   [OK] Connection to CKAN API was successful (HTTP 200).\n";
    } else {
        echo "   [ERROR] Connection to CKAN API failed with HTTP Code: $httpCode\n";
    }
} else {
    echo "   [SKIP] Cannot test connectivity due to missing cURL extension.\n";
}

echo "\n=== DIAGNOSTICS COMPLETE ===\n";
