<?php
header('Content-Type: text/plain; charset=utf-8');

$envPath = __DIR__ . '/../secrets/.env';
if (!file_exists($envPath)) {
    die("Error: .env file not found at $envPath\n");
}

// Simple env loader
function loadEnv($path) {
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}
loadEnv($envPath);

$host = $_ENV['DB_HOST'] ?? '';
$port = $_ENV['DB_PORT'] ?? '5432';
$db   = $_ENV['DB_DATABASE'] ?? '';
$user = $_ENV['DB_USERNAME'] ?? '';
$pass = $_ENV['DB_PASSWORD'] ?? '';
$schema = $_ENV['DB_SCHEMA'] ?? 'ldrt';

echo "=== LDRT Database Loader ===\n";
echo "Connecting to: $host:$port ($db)...\n";

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$db";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 60, // set timeout
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "Connected successfully!\n";
    
    echo "Reading SQL file...\n";
    $sqlFile = __DIR__ . '/schema_and_data.sql';
    if (!file_exists($sqlFile)) {
        die("Error: schema_and_data.sql not found! Run generate_sql.py first.\n");
    }
    
    $sql = file_get_contents($sqlFile);
    echo "Executing SQL statements (this may take a few seconds)...\n";
    
    $start = microtime(true);
    $pdo->exec($sql);
    $duration = microtime(true) - $start;
    
    echo "Database schema and data loaded successfully in " . round($duration, 2) . " seconds!\n\n";
    
    echo "=== Running Data Consistency Checks ===\n";
    
    // Set search path
    $pdo->exec("SET search_path TO $schema, public");
    
    // 1. Row counts
    $tables = ['cid', 'cnae_cbo', 'agentes', 'agente_cid', 'relatos'];
    $expected = [
        'cid' => 14799,
        'cnae_cbo' => 5907,
        'agentes' => 370,
        'agente_cid' => 1690, // 1691 minus 1 skipped mismatch
        'relatos' => 1
    ];
    
    $errors = 0;
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        $exp = $expected[$table];
        
        if ($count == $exp) {
            echo "✔ Table '$table': count = $count (Matches expected $exp)\n";
        } else {
            echo "❌ Table '$table': count = $count (EXPECTED $exp!)\n";
            $errors++;
        }
    }
    
    // 2. Referential integrity check
    echo "\n=== Checking Referential Integrity ===\n";
    
    // Check for orphaned parent_ids in cid
    $stmt = $pdo->query("SELECT COUNT(*) FROM cid c LEFT JOIN cid p ON c.parent_id = p.id WHERE c.parent_id IS NOT NULL AND p.id IS NULL");
    $orphans = $stmt->fetchColumn();
    if ($orphans == 0) {
        echo "✔ Table 'cid': No orphaned parent_ids\n";
    } else {
        echo "❌ Table 'cid': Found $orphans orphaned parent_ids!\n";
        $errors++;
    }
    
    // Check for orphaned parent_ids in cnae_cbo
    $stmt = $pdo->query("SELECT COUNT(*) FROM cnae_cbo c LEFT JOIN cnae_cbo p ON c.parent_id = p.id WHERE c.parent_id IS NOT NULL AND p.id IS NULL");
    $orphans = $stmt->fetchColumn();
    if ($orphans == 0) {
        echo "✔ Table 'cnae_cbo': No orphaned parent_ids\n";
    } else {
        echo "❌ Table 'cnae_cbo': Found $orphans orphaned parent_ids!\n";
        $errors++;
    }
    
    // Check for orphaned parent_ids in agentes
    $stmt = $pdo->query("SELECT COUNT(*) FROM agentes c LEFT JOIN agentes p ON c.parent_id = p.id WHERE c.parent_id IS NOT NULL AND p.id IS NULL");
    $orphans = $stmt->fetchColumn();
    if ($orphans == 0) {
        echo "✔ Table 'agentes': No orphaned parent_ids\n";
    } else {
        echo "❌ Table 'agentes': Found $orphans orphaned parent_ids!\n";
        $errors++;
    }
    
    // 3. Test Full-Text Search
    echo "\n=== Testing Full-Text Search (FTS) ===\n";
    $searchQuery = 'chumbo'; // search for Lead
    $stmt = $pdo->prepare("SELECT id, descricao FROM agentes WHERE to_tsvector('portuguese', descricao) @@ to_tsquery('portuguese', :query) LIMIT 3");
    $stmt->execute(['query' => $searchQuery]);
    $results = $stmt->fetchAll();
    
    if (count($results) > 0) {
        echo "✔ FTS search on 'agentes' returned " . count($results) . " results for keyword '$searchQuery'.\n";
        foreach ($results as $r) {
            echo "  - [ID: {$r['id']}] " . substr($r['descricao'], 0, 80) . "...\n";
        }
    } else {
        echo "❌ FTS search on 'agentes' returned NO results for '$searchQuery'!\n";
        $errors++;
    }
    
    // 4. Test RAG Chunks View
    echo "\n=== Testing RAG Chunks View ===\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM v_rag_chunks");
    $chunkCount = $stmt->fetchColumn();
    echo "✔ View 'v_rag_chunks': Total generated text chunks = $chunkCount\n";
    
    // Show a sample chunk
    $stmt = $pdo->query("SELECT chunk_type, chunk_text FROM v_rag_chunks ORDER BY chunk_type DESC LIMIT 2");
    $chunks = $stmt->fetchAll();
    foreach ($chunks as $c) {
        echo "  - Type: {$c['chunk_type']} | Text: " . substr($c['chunk_text'], 0, 150) . "...\n";
    }
    
    echo "\n----------------------------------------\n";
    if ($errors === 0) {
        echo "🎉 DATABASE LOAD COMPLETED & VALIDATED SUCCESSFULLY WITH ZERO ERRORS!\n";
    } else {
        echo "⚠ DATABASE LOAD INCOMPLETE OR HAS CONSISTENCY ERRORS: $errors error(s) found.\n";
    }
    
} catch (PDOException $e) {
    echo "Error: Database execution failed!\n";
    echo $e->getMessage() . "\n";
}
