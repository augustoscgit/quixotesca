<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

require_once __DIR__ . '/../../ldrt/src/db.php';

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;

if ($limit <= 0 || $limit > 200) {
    $limit = 50;
}

try {
    $db = getDBConnection();
    
    $params = [];
    $sql = "SELECT chunk_type, source_id, agent_name, cid_code, cid_name, cnae_cbo_type, cnae_cbo_code, cnae_cbo_name, relato_title, chunk_text 
            FROM v_rag_chunks WHERE 1=1";
            
    if (!empty($type)) {
        $sql .= " AND chunk_type = :type";
        $params['type'] = $type;
    }
    
    if (!empty($query)) {
        // Use full-text search with plainto_tsquery
        $sql .= " AND to_tsvector('portuguese', chunk_text) @@ plainto_tsquery('portuguese', :query)";
        $params['query'] = $query;
    }
    
    $sql .= " LIMIT :limit";
    
    // Prepare and bind
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $results = $stmt->fetchAll();
    
    // If no results found with Full-Text Search, try a fallback ILIKE search
    if (empty($results) && !empty($query)) {
        $sql_fallback = "SELECT chunk_type, source_id, agent_name, cid_code, cid_name, cnae_cbo_type, cnae_cbo_code, cnae_cbo_name, relato_title, chunk_text 
                         FROM v_rag_chunks 
                         WHERE chunk_text ILIKE :query_like";
        if (!empty($type)) {
            $sql_fallback .= " AND chunk_type = :type";
        }
        $sql_fallback .= " LIMIT :limit";
        
        $stmt_fallback = $db->prepare($sql_fallback);
        $stmt_fallback->bindValue('query_like', '%' . $query . '%');
        if (!empty($type)) {
            $stmt_fallback->bindValue('type', $type);
        }
        $stmt_fallback->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt_fallback->execute();
        $results = $stmt_fallback->fetchAll();
    }
    
    echo json_encode([
        'status' => 'success',
        'count' => count($results),
        'results' => $results
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao consultar dados.'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
