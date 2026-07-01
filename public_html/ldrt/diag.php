<?php
/**
 * Diagnostic Script for LDRT Deployment
 * Upload this to the server to debug why index.php is not loading.
 */
require_once __DIR__ . '/../../acesso/src/bootstrap.php';

require_platform_admin();

if (app_debug_enabled()) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== LDRT DEPLOYMENT DIAGNOSTICS ===\n\n";

// 1. Check PHP Version
echo "1. PHP Version:\n";
echo "   Current version: " . PHP_VERSION . "\n\n";

// 2. Check Secrets & .env
echo "2. Checking secrets directory and .env file:\n";
$secretsDir = __DIR__ . '/../../ldrt/secrets';
$envPath = $secretsDir . '/.env';

if (!is_dir($secretsDir)) {
    echo "   [ERROR] Secrets directory does NOT exist at: $secretsDir\n";
} else {
    echo "   [OK] Secrets directory exists.\n";
    if (!file_exists($envPath)) {
        echo "   [ERROR] .env file does NOT exist at: $envPath\n";
        echo "           (Note: FTP clients often ignore hidden files starting with '.' by default during upload. Check your transfer settings.)\n";
    } else {
        echo "   [OK] .env file found.\n";
        
        // Parse .env to check if we can read it
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
echo "3. Checking database extensions:\n";
$extensions = ['pdo', 'pdo_pgsql', 'pgsql'];
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "   [OK] Extension '$ext' is LOADED.\n";
    } else {
        echo "   [ERROR] Extension '$ext' is NOT loaded.\n";
        if ($ext === 'pdo_pgsql') {
            echo "           (This is required to connect to PostgreSQL. You need to enable it in your hosting cPanel/Plesk under PHP Selector / Extensions.)\n";
        }
    }
}
echo "\n";

// 4. Try database connection
echo "4. Testing Database Connection:\n";
if (file_exists($envPath) && extension_loaded('pdo_pgsql')) {
    try {
        require_once __DIR__ . '/../../ldrt/src/db.php';
        $db = getDBConnection();
        echo "   [OK] Successfully connected to the database!\n";
        $cid_count = $db->query("SELECT COUNT(*) FROM cid")->fetchColumn();
        echo "   [OK] Query executed successfully. Total CIDs: $cid_count\n";
    } catch (Exception $e) {
        echo "   [ERROR] Database connection failed:\n";
        echo "           " . $e->getMessage() . "\n";
    }
} else {
    echo "   [SKIP] Cannot test connection due to missing .env or missing pdo_pgsql extension.\n";
}

echo "\n=== DIAGNOSTICS COMPLETE ===\n";
