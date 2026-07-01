<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

require_once __DIR__ . '/../../ldrt/src/db.php';

$parentId = isset($_GET['parent_id']) ? intval($_GET['parent_id']) : 0;
$class = isset($_GET['classificacao']) ? trim($_GET['classificacao']) : '';

if ($parentId <= 0 || !in_array($class, ['cnae', 'cbo'])) {
    echo json_encode([]);
    exit;
}

try {
    $db = getDBConnection();
    
    $stmt = $db->prepare("
        SELECT id, codigo, nivel, descricao,
               (SELECT COUNT(*) FROM cnae_cbo sub WHERE sub.parent_id = cc.id) > 0 AS has_children
        FROM cnae_cbo cc
        WHERE cc.parent_id = :parent_id AND cc.classificacao = :class
        ORDER BY cc.codigo ASC
    ");
    $stmt->execute(['parent_id' => $parentId, 'class' => $class]);
    $children = $stmt->fetchAll();
    
    foreach ($children as &$child) {
        $child['has_children'] = (bool)$child['has_children'];
    }
    
    echo json_encode($children, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao consultar dados.']);
}
