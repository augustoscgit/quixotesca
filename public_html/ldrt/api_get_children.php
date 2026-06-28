<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../ldrt/src/db.php';

$parentId = isset($_GET['parent_id']) ? intval($_GET['parent_id']) : 0;
$ldrtOnly = isset($_GET['ldrt_only']) && $_GET['ldrt_only'] == '1';

try {
    $db = getDBConnection();
    
    // Condition for parent
    $parentCond = ($parentId === 0) ? "c.parent_id IS NULL" : "c.parent_id = :parent_id";
    
    if ($ldrtOnly) {
        // Fetch only CIDs that are in the LDRT (or are ancestors of CIDs in the LDRT)
        $sql = "
            WITH RECURSIVE ldrt_cids AS (
                SELECT id, parent_id FROM cid WHERE id IN (SELECT DISTINCT cid_id FROM agente_cid)
                UNION
                SELECT p.id, p.parent_id FROM cid p JOIN ldrt_cids child ON child.parent_id = p.id
            )
            SELECT c.id, c.codigo, c.nivel, c.descricao,
                   (SELECT COUNT(*) FROM cid sub WHERE sub.parent_id = c.id AND sub.id IN (SELECT id FROM ldrt_cids)) > 0 AS has_children
            FROM cid c
            WHERE $parentCond AND c.id IN (SELECT id FROM ldrt_cids)
            ORDER BY " . ($parentId === 0 ? "c.id ASC" : "c.codigo ASC");
    } else {
        // Fetch all CIDs
        $sql = "
            SELECT c.id, c.codigo, c.nivel, c.descricao,
                   (SELECT COUNT(*) FROM cid sub WHERE sub.parent_id = c.id) > 0 AS has_children
            FROM cid c
            WHERE $parentCond
            ORDER BY " . ($parentId === 0 ? "c.id ASC" : "c.codigo ASC");
    }
    
    $stmt = $db->prepare($sql);
    if ($parentId > 0) {
        $stmt->bindValue('parent_id', $parentId, PDO::PARAM_INT);
    }
    $stmt->execute();
    $children = $stmt->fetchAll();
    
    // Map has_children to boolean
    foreach ($children as &$child) {
        $child['has_children'] = (bool)$child['has_children'];
    }
    
    echo json_encode($children, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
