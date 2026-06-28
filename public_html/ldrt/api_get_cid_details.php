<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../ldrt/src/db.php';

$cidId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($cidId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid CID ID']);
    exit;
}

try {
    $db = getDBConnection();
    
    // 1. Fetch CID Details and Hierarchy Path using Recursive CTE
    $pathStmt = $db->prepare("
        WITH RECURSIVE hierarchy AS (
            SELECT id, codigo, nivel, descricao, parent_id, 1 as depth
            FROM cid
            WHERE id = :id
            UNION ALL
            SELECT p.id, p.codigo, p.nivel, p.descricao, p.parent_id, h.depth + 1
            FROM cid p
            JOIN hierarchy h ON h.parent_id = p.id
        )
        SELECT id, codigo, nivel, descricao, parent_id 
        FROM hierarchy 
        ORDER BY depth DESC
    ");
    $pathStmt->execute(['id' => $cidId]);
    $hierarchy = $pathStmt->fetchAll();
    
    if (empty($hierarchy)) {
        http_response_code(404);
        echo json_encode(['error' => 'CID not found']);
        exit;
    }
    
    // The target CID is the last element in the hierarchy path
    $cidDetails = $hierarchy[count($hierarchy) - 1];
    
    // 2. Fetch Associated Agents
    $agentsStmt = $db->prepare("
        SELECT a.id, a.descricao, a.cas 
        FROM agentes a
        JOIN agente_cid ac ON ac.agente_id = a.id
        WHERE ac.cid_id = :id
        ORDER BY a.descricao ASC
    ");
    $agentsStmt->execute(['id' => $cidId]);
    $agents = $agentsStmt->fetchAll();
    
    // 3. Fetch Associated Case Reports (Relatos)
    $relatosStmt = $db->prepare("
        SELECT r.id, r.titulo, r.relato, r.old_id,
               cc.codigo as cnae_cbo_codigo, cc.descricao as cnae_cbo_descricao, cc.classificacao
        FROM relatos r
        LEFT JOIN cnae_cbo cc ON r.cnae_cbo_id = cc.id
        WHERE r.cid_id = :id
        ORDER BY r.id DESC
    ");
    $relatosStmt->execute(['id' => $cidId]);
    $relatos = $relatosStmt->fetchAll();
    
    echo json_encode([
        'details' => $cidDetails,
        'hierarchy' => $hierarchy,
        'agents' => $agents,
        'relatos' => $relatos
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
