<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

require_once __DIR__ . '/../../ldrt/src/db.php';

function normalize_term($str) {
    $str = mb_strtolower($str, 'UTF-8');
    $accented = ['á','à','â','ã','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','ô','õ','ö','ú','ù','û','ü','ç','ý','ñ'];
    $non_accented = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','y','n'];
    return str_replace($accented, $non_accented, $str);
}

$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($type) || empty($query) || strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $db = getDBConnection();
    $results = [];
    $normalized_query = normalize_term($query);

    switch ($type) {
        case 'cid':
            // Always filter by ldrt_cids CTE to return only CIDs that relate to LDRT (with hierarchy)
            $stmt = $db->prepare("
                WITH RECURSIVE ldrt_cids AS (
                    SELECT id, parent_id FROM cid WHERE id IN (SELECT DISTINCT cid_id FROM agente_cid)
                    UNION
                    SELECT p.id, p.parent_id FROM cid p JOIN ldrt_cids child ON child.parent_id = p.id
                )
                SELECT id, codigo AS value, codigo || ' - ' || descricao AS label 
                FROM cid 
                WHERE id IN (SELECT id FROM ldrt_cids) 
                  AND (
                    translate(lower(codigo), 'áàâãäéèêëíìîïóòôõöúùûüçýñ', 'aaaaaeeeeiiiiooooouuuucyn') LIKE :term 
                    OR translate(lower(descricao), 'áàâãäéèêëíìîïóòôõöúùûüçýñ', 'aaaaaeeeeiiiiooooouuuucyn') LIKE :term
                  )
                ORDER BY codigo ASC 
                LIMIT 15
            ");
            $stmt->execute(['term' => "%$normalized_query%"]);
            $results = $stmt->fetchAll();
            break;

        case 'cnae':
            $stmt = $db->prepare("
                SELECT id, codigo AS value, codigo || ' - ' || descricao AS label 
                FROM cnae_cbo 
                WHERE classificacao = 'cnae' 
                  AND (
                    translate(lower(codigo), 'áàâãäéèêëíìîïóòôõöúùûüçýñ', 'aaaaaeeeeiiiiooooouuuucyn') LIKE :term 
                    OR translate(lower(descricao), 'áàâãäéèêëíìîïóòôõöúùûüçýñ', 'aaaaaeeeeiiiiooooouuuucyn') LIKE :term
                  )
                ORDER BY codigo ASC 
                LIMIT 15
            ");
            $stmt->execute(['term' => "%$normalized_query%"]);
            $results = $stmt->fetchAll();
            break;

        case 'cbo':
            $stmt = $db->prepare("
                SELECT id, codigo AS value, codigo || ' - ' || descricao AS label 
                FROM cnae_cbo 
                WHERE classificacao = 'cbo' 
                  AND (
                    translate(lower(codigo), 'áàâãäéèêëíìîïóòôõöúùûüçýñ', 'aaaaaeeeeiiiiooooouuuucyn') LIKE :term 
                    OR translate(lower(descricao), 'áàâãäéèêëíìîïóòôõöúùûüçýñ', 'aaaaaeeeeiiiiooooouuuucyn') LIKE :term
                  )
                ORDER BY codigo ASC 
                LIMIT 15
            ");
            $stmt->execute(['term' => "%$normalized_query%"]);
            $results = $stmt->fetchAll();
            break;

        case 'agente':
            $stmt = $db->prepare("
                SELECT id, id AS value, descricao AS label 
                FROM agentes 
                WHERE translate(lower(descricao), 'áàâãäéèêëíìîïóòôõöúùûüçýñ', 'aaaaaeeeeiiiiooooouuuucyn') LIKE :term 
                ORDER BY id ASC 
                LIMIT 15
            ");
            $stmt->execute(['term' => "%$normalized_query%"]);
            $results = $stmt->fetchAll();
            break;
    }

    echo json_encode($results, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao consultar dados.']);
}
