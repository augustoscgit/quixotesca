<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/src/db.php';

$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($type) || empty($query) || strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $db = getDBConnection();
    $results = [];

    switch ($type) {
        case 'cid':
            $stmt = $db->prepare("
                SELECT codigo AS value, codigo || ' - ' || descricao AS label 
                FROM cid 
                WHERE codigo ILIKE :term OR descricao ILIKE :term 
                ORDER BY codigo ASC 
                LIMIT 15
            ");
            $stmt->execute(['term' => "%$query%"]);
            $results = $stmt->fetchAll();
            break;

        case 'cnae':
            $stmt = $db->prepare("
                SELECT codigo AS value, codigo || ' - ' || descricao AS label 
                FROM cnae_cbo 
                WHERE classificacao = 'cnae' AND (codigo ILIKE :term OR descricao ILIKE :term)
                ORDER BY codigo ASC 
                LIMIT 15
            ");
            $stmt->execute(['term' => "%$query%"]);
            $results = $stmt->fetchAll();
            break;

        case 'cbo':
            $stmt = $db->prepare("
                SELECT codigo AS value, codigo || ' - ' || descricao AS label 
                FROM cnae_cbo 
                WHERE classificacao = 'cbo' AND (codigo ILIKE :term OR descricao ILIKE :term)
                ORDER BY codigo ASC 
                LIMIT 15
            ");
            $stmt->execute(['term' => "%$query%"]);
            $results = $stmt->fetchAll();
            break;

        case 'agente':
            $stmt = $db->prepare("
                SELECT id AS value, descricao AS label 
                FROM agentes 
                WHERE descricao ILIKE :term 
                ORDER BY id ASC 
                LIMIT 15
            ");
            $stmt->execute(['term' => "%$query%"]);
            $results = $stmt->fetchAll();
            break;
    }

    echo json_encode($results, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
