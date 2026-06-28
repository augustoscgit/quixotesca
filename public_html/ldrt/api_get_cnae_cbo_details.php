<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../ldrt/src/db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

try {
    $db = getDBConnection();
    
    // 1. Fetch Details and Hierarchy Path
    $pathStmt = $db->prepare("
        WITH RECURSIVE hierarchy AS (
            SELECT id, classificacao, codigo, nivel, descricao, parent_id, 1 as depth
            FROM cnae_cbo
            WHERE id = :id
            UNION ALL
            SELECT p.id, p.classificacao, p.codigo, p.nivel, p.descricao, p.parent_id, h.depth + 1
            FROM cnae_cbo p
            JOIN hierarchy h ON h.parent_id = p.id
        )
        SELECT id, classificacao, codigo, nivel, descricao, parent_id 
        FROM hierarchy 
        ORDER BY depth DESC
    ");
    $pathStmt->execute(['id' => $id]);
    $hierarchy = $pathStmt->fetchAll();
    
    if (empty($hierarchy)) {
        http_response_code(404);
        echo json_encode(['error' => 'Record not found']);
        exit;
    }
    
    $details = $hierarchy[count($hierarchy) - 1];
    
    // 2. Fetch Associated Case Reports (Relatos)
    $relatosStmt = $db->prepare("
        SELECT r.id, r.titulo, r.relato, r.old_id,
               c.codigo as cid_codigo, c.descricao as cid_descricao,
               a.descricao as agente_descricao
        FROM relatos r
        LEFT JOIN cid c ON r.cid_id = c.id
        LEFT JOIN agentes a ON r.agente_id = a.id
        WHERE r.cnae_cbo_id = :id
        ORDER BY r.id DESC
    ");
    $relatosStmt->execute(['id' => $id]);
    $relatos = $relatosStmt->fetchAll();
    
    echo json_encode([
        'details' => $details,
        'hierarchy' => $hierarchy,
        'relatos' => $relatos
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
