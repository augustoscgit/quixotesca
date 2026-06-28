<?php
declare(strict_types=1);

// Disable output buffering to send output in real-time if needed
if (function_exists('ob_end_clean')) {
    @ob_end_clean();
}

$results = [];

// 1. PHP Version
$phpVersion = PHP_VERSION;
$phpOk = version_compare($phpVersion, '8.0.0', '>=');
$results['php_version'] = [
    'title' => 'Versão do PHP',
    'value' => $phpVersion,
    'status' => $phpOk ? 'success' : 'danger',
    'message' => $phpOk ? 'Versão compatível (>= 8.0.0).' : 'O projeto exige PHP 8.0 ou superior.',
];

// 2. PDO PostgreSQL Driver
$pdoDrivers = class_exists('PDO') ? PDO::getAvailableDrivers() : [];
$pgsqlOk = in_array('pgsql', $pdoDrivers, true);
$results['pgsql_driver'] = [
    'title' => 'Driver PDO PostgreSQL',
    'value' => $pgsqlOk ? 'Habilitado' : 'Não encontrado',
    'status' => $pgsqlOk ? 'success' : 'danger',
    'message' => $pgsqlOk ? 'Driver PDO PostgreSQL (pdo_pgsql) está ativo.' : 'O driver pdo_pgsql precisa estar habilitado no php.ini da hospedagem.',
];

// 3. Secrets Folder & .env
$secretsDir = __DIR__ . DIRECTORY_SEPARATOR . 'secrets';
$secretsEnv = $secretsDir . DIRECTORY_SEPARATOR . '.env';
$secretsDirExists = is_dir($secretsDir);
$secretsDirWritable = $secretsDirExists && is_writable($secretsDir);
$secretsEnvExists = is_file($secretsEnv);
$secretsEnvReadable = $secretsEnvExists && is_readable($secretsEnv);

$results['secrets_dir'] = [
    'title' => 'Diretório /secrets',
    'value' => $secretsDirExists ? ($secretsDirWritable ? 'Existe e Gravável' : 'Existe (Apenas Leitura)') : 'Não existe',
    'status' => ($secretsDirExists && $secretsDirWritable) ? 'success' : 'warning',
    'message' => ($secretsDirExists && $secretsDirWritable) ? 'Diretório está configurado corretamente.' : 'O PHP precisa de permissão de escrita nesta pasta para gerenciar os arquivos de configuração.',
];

$results['secrets_env'] = [
    'title' => 'Arquivo secrets/.env',
    'value' => $secretsEnvExists ? ($secretsEnvReadable ? 'Presente e Leitura OK' : 'Presente (Sem permissão de leitura)') : 'Não encontrado',
    'status' => ($secretsEnvExists && $secretsEnvReadable) ? 'success' : 'danger',
    'message' => ($secretsEnvExists && $secretsEnvReadable) ? 'Variáveis de ambiente lidas com sucesso.' : 'Copie o arquivo .env.example para secrets/.env.',
];

// 4. Parse environment variables for connection test
$host = '';
$port = '5432';
$database = '';
$username = '';
$password = '';
$schema = 'public';

if ($secretsEnvExists && $secretsEnvReadable) {
    $lines = file($secretsEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim(trim($value), "\"'");
        if ($key === 'DB_HOST') $host = $value;
        if ($key === 'DB_PORT') $port = $value;
        if ($key === 'DB_DATABASE') $database = $value;
        if ($key === 'DB_USERNAME') $username = $value;
        if ($key === 'DB_PASSWORD') $password = $value;
        if ($key === 'DB_SCHEMA') $schema = $value;
    }
}

// 5. DB Write Test
$dbWriteOk = false;
$dbWriteMsg = '';
if ($pgsqlOk && $host !== '' && $database !== '') {
    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$database";
        $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        
        $quotedSchema = '"' . str_replace('"', '""', $schema) . '"';
        $pdo->exec("SET search_path TO $quotedSchema, public");
        
        // Try creating a temp table, writing, reading, and dropping
        $pdo->exec('CREATE TEMP TABLE test_write (val TEXT)');
        $stmt = $pdo->prepare('INSERT INTO test_write (val) VALUES (:val)');
        $stmt->execute([':val' => 'teste']);
        $read = $pdo->query('SELECT val FROM test_write LIMIT 1')->fetchColumn();
        $pdo->exec('DROP TABLE test_write');
        
        if ($read === 'teste') {
            $dbWriteOk = true;
            $dbWriteMsg = "Conexão e escrita no PostgreSQL (esquema '$schema') validadas com sucesso!";
        } else {
            $dbWriteMsg = 'Os dados lidos da tabela temporária não batem.';
        }
    } catch (Throwable $e) {
        $dbWriteMsg = 'Falha ao conectar/escrever no PostgreSQL: ' . $e->getMessage();
    }
} else {
    $dbWriteMsg = 'Sem driver PostgreSQL ou credenciais incompletas no secrets/.env.';
}

$results['db_write_test'] = [
    'title' => 'Teste de Conexão e Escrita no Banco',
    'value' => $dbWriteOk ? 'Sucesso' : 'Falhou',
    'status' => $dbWriteOk ? 'success' : 'danger',
    'message' => $dbWriteMsg,
];

// 7. Check if environment keys are loaded
$envKeysLoaded = false;
$envKeysMsg = [];
if ($secretsEnvExists && $secretsEnvReadable) {
    // Manually parse to check
    $lines = file($secretsEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key !== '') {
                $envKeysMsg[] = "$key: Definido (" . strlen(trim($value, "\"'")) . " caracteres)";
            }
        }
    }
    $envKeysLoaded = count($envKeysMsg) > 0;
}

$results['env_keys'] = [
    'title' => 'Configurações carregadas',
    'value' => $envKeysLoaded ? 'Carregadas' : 'Nenhuma detectada',
    'status' => $envKeysLoaded ? 'success' : 'warning',
    'message' => $envKeysLoaded ? implode('<br>', $envKeysMsg) : 'Nenhuma configuração ativa carregada do arquivo secrets/.env.',
];
?>
<!doctype html>
<html lang="pt-br" data-module="fichario">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Diagnóstico - Fichário Acadêmico</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-gradient: radial-gradient(circle at 50% 50%, #151932 0%, #0c0e1b 100%);
        }
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-gradient);
            color: #f3f4f6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .glass-card {
            backdrop-filter: blur(16px) saturate(180%);
            background-color: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
            padding: 2.5rem;
            max-width: 800px;
            width: 100%;
        }
        .status-dot {
            height: 12px;
            width: 12px;
            border-radius: 50%;
            display: inline-block;
        }
        .status-success { background-color: #10b981; box-shadow: 0 0 8px #10b981; }
        .status-warning { background-color: #f59e0b; box-shadow: 0 0 8px #f59e0b; }
        .status-danger { background-color: #ef4444; box-shadow: 0 0 8px #ef4444; }
        
        .list-group-item {
            background-color: rgba(255, 255, 255, 0.015);
            border: 1px solid rgba(255, 255, 255, 0.05);
            color: #e5e7eb;
            margin-bottom: 0.75rem;
            border-radius: 12px !important;
            transition: all 0.2s;
        }
        .list-group-item:hover {
            background-color: rgba(255, 255, 255, 0.03);
            border-color: rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body>
    <div class="glass-card">
        <div class="text-center mb-4">
            <h1 class="h3 text-white fw-bold mb-1">Diagnóstico do Sistema</h1>
            <p class="text-secondary small">Fichário Acadêmico - Verificação de Integridade para Hospedagem</p>
        </div>

        <div class="list-group">
            <?php foreach ($results as $key => $item): ?>
                <div class="list-group-item p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="h6 mb-0 text-white fw-bold d-flex align-items-center gap-2">
                            <span class="status-dot status-<?= $item['status'] ?>"></span>
                            <?= htmlspecialchars($item['title']) ?>
                        </h2>
                        <span class="badge bg-<?= $item['status'] === 'success' ? 'success-subtle text-success' : ($item['status'] === 'warning' ? 'warning-subtle text-warning' : 'danger-subtle text-danger') ?> px-2.5 py-1 rounded-pill" style="font-size: 0.75rem;">
                            <?= htmlspecialchars($item['value']) ?>
                        </span>
                    </div>
                    <p class="mb-0 text-secondary small" style="line-height: 1.5;">
                        <?= $item['message'] ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-4 pt-3 border-top border-secondary border-opacity-20 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <a href="index.php" class="btn btn-outline-primary rounded-pill px-4 text-white border-primary" style="font-size: 0.9rem;">
                Ir para o Início
            </a>
            <span class="text-secondary small">Sugestão: Exclua este arquivo após resolver os problemas para evitar expor o status do servidor.</span>
        </div>
    </div>
</body>
</html>
