<?php
require_once __DIR__ . '/../../acesso/src/bootstrap.php';

$action = $_GET['action'] ?? '';
$publicReadActions = [
    'chart_data',
    'quality_dashboard',
    'get_states',
    'get_cities',
    'query_records',
    'cnpj_filter_options',
    'cnpj_aggregates',
    'cnpj_cache_status',
    'cnpj_cats',
];

if (!in_array($action, $publicReadActions, true)) {
    require_platform_admin();
}

/**
 * AJAX API for CAT ETL Pipeline
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

require_once __DIR__ . '/../../cat/src/db.php';
require_once __DIR__ . '/../../cat/src/etl_helper.php';
require_once __DIR__ . '/../../cat/src/opencnpj.php';

// Simple error handler returning JSON
set_exception_handler(function (Throwable $e) {
    $statusCode = http_response_code();
    if ($statusCode < 400) {
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'error'   => app_debug_enabled() ? $e->getMessage() : 'Erro ao processar solicitacao.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

$db = getDBConnection();
$tmpDir = __DIR__ . '/tmp';

function removeDirectory(string $dir): void
{
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($path)) {
            removeDirectory($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function findJSONFile(string $dir): ?string
{
    if (!is_dir($dir)) return null;
    $files = scandir($dir);
    foreach ($files as $file) {
        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'json') {
            return $dir . DIRECTORY_SEPARATOR . $file;
        }
    }
    return null;
}

function parseSimpleDictionaryFile(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }
    $content = file_get_contents($path);
    if ($content === false) {
        return [];
    }
    preg_match_all('/[\'"]([^\'"]+)[\'"]\s*:\s*[\'"]([^\'"]*)[\'"]/', $content, $matches, PREG_SET_ORDER);
    $dict = [];
    foreach ($matches as $match) {
        $dict[$match[1]] = $match[2];
    }
    return $dict;
}

function cnaeDictionaries(): array
{
    static $dicts = null;
    if ($dicts !== null) {
        return $dicts;
    }
    $dir = __DIR__ . '/../../cat/src/dicionarios';
    $dicts = [
        'seca' => parseSimpleDictionaryFile($dir . '/dict_cnae_seca.txt'),
        'divi' => parseSimpleDictionaryFile($dir . '/dict_cnae_divi.txt'),
        'grup' => parseSimpleDictionaryFile($dir . '/dict_cnae_grup.txt'),
        'class' => parseSimpleDictionaryFile($dir . '/dict_cnae_class.txt'),
        'subc' => parseSimpleDictionaryFile($dir . '/dict_cnae_subc.txt'),
        'divi_seca' => parseSimpleDictionaryFile($dir . '/dict_cnae_divi_seca.txt'),
    ];
    return $dicts;
}

function cnaeMatch(array $dict, string $code): array
{
    if ($code === '' || !$dict) {
        return ['', ''];
    }
    if (isset($dict[$code])) {
        return [$code, $dict[$code]];
    }
    $matches = [];
    foreach ($dict as $key => $label) {
        if (str_starts_with($key, $code) || str_starts_with($code, $key)) {
            $matches[$key] = $label;
        }
    }
    if (!$matches) {
        return ['', ''];
    }
    uksort($matches, fn($a, $b) => strlen($b) <=> strlen($a));
    $key = array_key_first($matches);
    return [$key, $matches[$key]];
}

function resolveCnaeHierarchy(string $rawCode): array
{
    $code = preg_replace('/\D+/', '', $rawCode);
    if ($code === '') {
        return [];
    }
    $dicts = cnaeDictionaries();
    [$subCode, $subLabel] = cnaeMatch($dicts['subc'], $code);
    [$classCode, $classLabel] = cnaeMatch($dicts['class'], $code);
    [$groupCode, $groupLabel] = cnaeMatch($dicts['grup'], substr($classCode ?: $subCode ?: $code, 0, 3));
    [$divisionCode, $divisionLabel] = cnaeMatch($dicts['divi'], substr($groupCode ?: $classCode ?: $subCode ?: $code, 0, 2));
    $sectionCode = $dicts['divi_seca'][$divisionCode] ?? '';
    $sectionLabel = $sectionCode !== '' ? ($dicts['seca'][$sectionCode] ?? '') : '';

    return array_values(array_filter([
        $sectionCode && $sectionLabel ? ['nivel' => 'secao', 'codigo' => $sectionCode, 'rotulo' => $sectionLabel] : null,
        $divisionCode && $divisionLabel ? ['nivel' => 'divisao', 'codigo' => $divisionCode, 'rotulo' => $divisionLabel] : null,
        $groupCode && $groupLabel ? ['nivel' => 'grupo', 'codigo' => $groupCode, 'rotulo' => $groupLabel] : null,
        $classCode && $classLabel ? ['nivel' => 'classe', 'codigo' => $classCode, 'rotulo' => $classLabel] : null,
        $subCode && $subLabel ? ['nivel' => 'subclasse', 'codigo' => $subCode, 'rotulo' => $subLabel] : null,
    ]));
}

function enrichCnaeLabels(PDO $db, array $rows): array
{
    foreach ($rows as &$row) {
        $hierarchy = resolveCnaeHierarchy((string)($row['cnae_codigo'] ?? ''));
        $best = end($hierarchy);
        $row['cnae_rotulo'] = $best['rotulo'] ?? ($row['cnae_descricao'] ?? null);
        $row['cnae_hierarquia'] = $hierarchy;
    }
    return $rows;
}

function saveFileDocumentation(PDO $db, int $arquivoId, array $documentation): void
{
    $db->beginTransaction();
    try {
        $db->prepare("DELETE FROM campos_arquivo WHERE arquivo_importacao_id = ?")->execute([$arquivoId]);

        $stmt = $db->prepare("
            INSERT INTO campos_arquivo (
                arquivo_importacao_id, campo, ocorrencias, preenchidos, total_registros,
                formatos_data, exemplos, atualizado_em
            ) VALUES (?, ?, ?, ?, ?, ?::jsonb, ?::jsonb, NOW())
            ON CONFLICT (arquivo_importacao_id, campo) DO UPDATE SET
                ocorrencias = EXCLUDED.ocorrencias,
                preenchidos = EXCLUDED.preenchidos,
                total_registros = EXCLUDED.total_registros,
                formatos_data = EXCLUDED.formatos_data,
                exemplos = EXCLUDED.exemplos,
                atualizado_em = NOW()
        ");

        foreach ($documentation['campos'] as $field) {
            $stmt->execute([
                $arquivoId,
                $field['campo'],
                (int)$field['ocorrencias'],
                (int)$field['preenchidos'],
                (int)$documentation['total_registros'],
                json_encode(array_values($field['formatos_data']), JSON_UNESCAPED_UNICODE),
                json_encode(array_values($field['exemplos']), JSON_UNESCAPED_UNICODE),
            ]);
        }

        $db->prepare("
            UPDATE arquivos_importacao
               SET total_registros_documentados = ?,
                   total_campos_documentados = ?,
                   documentacao_atualizada_em = NOW()
             WHERE id = ?
        ")->execute([
            (int)$documentation['total_registros'],
            (int)$documentation['total_campos'],
            $arquivoId,
        ]);

        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

switch ($action) {
    case 'stats':
        $totalFiles = (int)$db->query("SELECT COUNT(*) FROM arquivos_importacao")->fetchColumn();
        $loadedFiles = (int)$db->query("SELECT COUNT(*) FROM arquivos_importacao WHERE situacao_carga = 'Carregado'")->fetchColumn();
        $totalRows = (int)$db->query("SELECT COUNT(*) FROM registros_brutos")->fetchColumn();
        $duplicateRows = (int)$db->query("
            SELECT COALESCE(SUM(qtd), 0)
              FROM (
                  SELECT COUNT(*) AS qtd
                    FROM registros_brutos
                   GROUP BY hash_extended
                  HAVING COUNT(*) > 1
              ) duplicados
        ")->fetchColumn();
        $failedFiles = (int)$db->query("SELECT COUNT(*) FROM arquivos_importacao WHERE situacao_extracao = 'Falhou' OR situacao_carga = 'Falhou'")->fetchColumn();

        echo json_encode([
            'success'      => true,
            'total_files'  => $totalFiles,
            'loaded_files' => $loadedFiles,
            'total_rows'   => $totalRows,
            'duplicate_rows' => $duplicateRows,
            'failed_files' => $failedFiles
        ]);
        break;

    case 'file_info':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            throw new Exception("ID de arquivo invÃ¡lido.");
        }

        $stmt = $db->prepare("
            SELECT id, nome, total_registros_documentados, total_campos_documentados,
                   documentacao_atualizada_em
              FROM arquivos_importacao
             WHERE id = ?
        ");
        $stmt->execute([$id]);
        $file = $stmt->fetch();

        if (!$file) {
            throw new Exception("Arquivo nÃ£o encontrado.");
        }

        $stmtDuplicateRows = $db->prepare("
            SELECT COALESCE(SUM(qtd), 0)
              FROM (
                    SELECT COUNT(*) AS qtd
                      FROM registros_brutos
                     WHERE arquivo_importacao_id = ?
                     GROUP BY hash_extended
                    HAVING COUNT(*) > 1
              ) duplicados
        ");
        $stmtDuplicateRows->execute([$id]);
        $file['duplicate_rows'] = (int)$stmtDuplicateRows->fetchColumn();

        $stmtFields = $db->prepare("
            SELECT campo, ocorrencias, preenchidos, total_registros, formatos_data, exemplos
              FROM campos_arquivo
             WHERE arquivo_importacao_id = ?
             ORDER BY campo
        ");
        $stmtFields->execute([$id]);
        $fields = [];
        $dateFields = [];

        foreach ($stmtFields->fetchAll() as $field) {
            $formats = json_decode($field['formatos_data'] ?? '[]', true) ?: [];
            $examples = json_decode($field['exemplos'] ?? '[]', true) ?: [];
            $item = [
                'campo' => $field['campo'],
                'ocorrencias' => (int)$field['ocorrencias'],
                'preenchidos' => (int)$field['preenchidos'],
                'formatos_data' => $formats,
                'exemplos' => $examples,
            ];
            $fields[] = $item;
            if (!empty($formats)) {
                $dateFields[] = $item;
            }
        }

        echo json_encode([
            'success' => true,
            'file' => $file,
            'fields' => $fields,
            'date_fields' => $dateFields,
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'sync':
        // 1. Fetch resources list from CKAN API
        $url = 'https://dadosabertos.inss.gov.br/api/3/action/package_show?id=comunicacoes-de-acidente-de-trabalho-cat-plano-de-dados-abertos-jun-2023-a-jun-2025';
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$response) {
            throw new Exception("Falha ao consultar a API do CKAN. Código HTTP: $httpCode");
        }
        
        $payload = json_decode($response, true);
        if (!isset($payload['success']) || !$payload['success'] || !isset($payload['result']['resources'])) {
            throw new Exception("Estrutura de resposta inválida da API do CKAN.");
        }
        
        $resources = $payload['result']['resources'];
        $inserted = 0;
        $updated = 0;
        
        $stmtCheck = $db->prepare("SELECT id, nome, url_download FROM arquivos_importacao WHERE recurso_id = ?");
        $stmtInsert = $db->prepare("INSERT INTO arquivos_importacao (recurso_id, nome, url_download) VALUES (?, ?, ?) RETURNING id");
        $stmtUpdate = $db->prepare("UPDATE arquivos_importacao SET nome = ?, url_download = ? WHERE recurso_id = ?");
        $syncedFiles = [];
        
        foreach ($resources as $res) {
            // We are interested in CSV format resources (even if zipped)
            if (strtoupper($res['format'] ?? '') !== 'CSV') {
                continue;
            }
            
            $recursoId = $res['id'];
            $nome = $res['name'];
            $downloadUrl = $res['url'];
            
            $stmtCheck->execute([$recursoId]);
            $existing = $stmtCheck->fetch();
            
            if ($existing) {
                if ($existing['nome'] !== $nome || $existing['url_download'] !== $downloadUrl) {
                    $stmtUpdate->execute([$nome, $downloadUrl, $recursoId]);
                    $updated++;
                }
                $syncedFiles[] = [
                    'id' => (int)$existing['id'],
                    'nome' => $nome,
                ];
            } else {
                $stmtInsert->execute([$recursoId, $nome, $downloadUrl]);
                $newId = (int)$stmtInsert->fetchColumn();
                $inserted++;
                $syncedFiles[] = [
                    'id' => $newId,
                    'nome' => $nome,
                ];
            }
        }
        
        echo json_encode([
            'success'  => true,
            'inserted' => $inserted,
            'updated'  => $updated,
            'files'    => $syncedFiles
        ]);
        break;

    case 'download_extract':
        $id = (int)($_GET['id'] ?? 0);
        $resume = (($_GET['resume'] ?? '') === 'true');
        if ($id <= 0) {
            throw new Exception("ID de arquivo inválido.");
        }

        $stmt = $db->prepare("SELECT * FROM arquivos_importacao WHERE id = ?");
        $stmt->execute([$id]);
        $file = $stmt->fetch();

        if (!$file) {
            throw new Exception("Arquivo não encontrado.");
        }

        $zipPath = $tmpDir . '/' . $id . '.zip';
        $extractPath = $tmpDir . '/' . $id;

        if (!$resume) {
            // Clean run: Delete any existing raw records and reset count
            $db->beginTransaction();
            try {
                $db->prepare("DELETE FROM registros_brutos WHERE arquivo_importacao_id = ?")->execute([$id]);
                $db->prepare("UPDATE arquivos_importacao SET situacao_extracao = 'Pendente', situacao_carga = 'Carregando', mensagem_erro = NULL, linhas_processadas = 0 WHERE id = ?")->execute([$id]);
                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            
            // Delete old files to ensure fresh download
            if (is_file($zipPath)) @unlink($zipPath);
            if (is_dir($extractPath)) removeDirectory($extractPath);
        } else {
            // Resume run: update state to Carregando but do not delete records
            $db->prepare("UPDATE arquivos_importacao SET situacao_carga = 'Carregando', mensagem_erro = NULL WHERE id = ?")->execute([$id]);
        }

        try {
            $jsonPath = findJSONFile($extractPath);
            
            if ($resume && $jsonPath && is_file($jsonPath)) {
                // Reuse existing extracted JSON file
                $rowCount = getJSONRecordCount($jsonPath);
                $documentation = inspectJSONStructure($jsonPath);
                saveFileDocumentation($db, $id, $documentation);
            } else {
                // Download and extract fresh
                if (is_file($zipPath)) @unlink($zipPath);
                if (is_dir($extractPath)) removeDirectory($extractPath);
                
                downloadFile($file['url_download'], $zipPath);
                $jsonPath = extractZip($zipPath, $extractPath);
                $rowCount = getJSONRecordCount($jsonPath);
                $documentation = inspectJSONStructure($jsonPath);
                saveFileDocumentation($db, $id, $documentation);
            }

            $db->prepare("UPDATE arquivos_importacao SET situacao_extracao = 'Extraído', ultima_execucao = NOW() WHERE id = ?")->execute([$id]);

            echo json_encode([
                'success'    => true,
                'total_rows' => $rowCount
            ]);
        } catch (Exception $e) {
            // Only delete files if it is a clean run
            if (!$resume) {
                if (is_file($zipPath)) @unlink($zipPath);
                if (is_dir($extractPath)) removeDirectory($extractPath);
            }
            
            $db->prepare("UPDATE arquivos_importacao SET situacao_carga = 'Falhou', mensagem_erro = ? WHERE id = ?")->execute([$e->getMessage(), $id]);
            throw $e;
        }
        break;

    case 'document_file':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            throw new Exception("ID de arquivo invÃ¡lido.");
        }

        $stmt = $db->prepare("SELECT * FROM arquivos_importacao WHERE id = ?");
        $stmt->execute([$id]);
        $file = $stmt->fetch();

        if (!$file) {
            throw new Exception("Arquivo nÃ£o encontrado.");
        }

        $zipPath = $tmpDir . '/' . $id . '.zip';
        $extractPath = $tmpDir . '/' . $id;

        if (is_file($zipPath)) @unlink($zipPath);
        if (is_dir($extractPath)) removeDirectory($extractPath);

        try {
            downloadFile($file['url_download'], $zipPath);
            $jsonPath = extractZip($zipPath, $extractPath);
            $documentation = inspectJSONStructure($jsonPath);
            saveFileDocumentation($db, $id, $documentation);

            echo json_encode([
                'success' => true,
                'id' => $id,
                'total_registros' => (int)$documentation['total_registros'],
                'total_campos' => (int)$documentation['total_campos']
            ]);
        } finally {
            if (is_file($zipPath)) @unlink($zipPath);
            if (is_dir($extractPath)) removeDirectory($extractPath);
        }
        break;

    case 'load_batch':
        $id = (int)($_GET['id'] ?? 0);
        $offset = (int)($_GET['offset'] ?? 0);
        $limit = (int)($_GET['limit'] ?? 5000);

        if ($id <= 0 || $offset < 0 || $limit <= 0) {
            throw new Exception("Parâmetros de lote inválidos.");
        }

        $extractPath = $tmpDir . '/' . $id;
        $jsonPath = findJSONFile($extractPath);

        if (!$jsonPath) {
            throw new Exception("Arquivo JSON descompactado não encontrado. Por favor, execute a extração novamente.");
        }

        try {
            $batch = readJSONBatch($jsonPath, $offset, $limit);
            
            if (count($batch) > 0) {
                $db->beginTransaction();
                
                $placeholders = [];
                $params = [];
                foreach ($batch as $index => $record) {
                    $lineNumber = $offset + $index + 1;
                    $sourceRecordId = $id . '-' . $lineNumber;
                    $hashExtended = calculateExtendedRecordHash($record);
                    // Inject source file tracking inside the raw JSON record
                    $record['_arquivo_importacao_id'] = $id;
                    $record['_numero_linha_arquivo'] = $lineNumber;
                    $record['_registro_origem_id'] = $sourceRecordId;
                    $record['_hash_extended'] = $hashExtended;
                    $json = json_encode($record, JSON_UNESCAPED_UNICODE);
                    if ($json === false) {
                        throw new Exception("Falha ao codificar registro como JSON: " . json_last_error_msg());
                    }
                    $placeholders[] = "(?, ?, ?, ?, ?)";
                    $params[] = $id;
                    $params[] = $lineNumber;
                    $params[] = $sourceRecordId;
                    $params[] = $hashExtended;
                    $params[] = $json;
                }
                
                $sql = "INSERT INTO registros_brutos (arquivo_importacao_id, numero_linha_arquivo, registro_origem_id, hash_extended, dados) VALUES " . implode(', ', $placeholders);
                $stmtInsert = $db->prepare($sql);
                $stmtInsert->execute($params);
                
                $db->commit();
            }

            $processedCount = $offset + count($batch);
            
            $db->prepare("UPDATE arquivos_importacao SET linhas_processadas = ? WHERE id = ?")->execute([$processedCount, $id]);

            echo json_encode([
                'success'   => true,
                'read_rows' => count($batch),
                'total_so_far' => $processedCount
            ]);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $db->prepare("UPDATE arquivos_importacao SET situacao_carga = 'Falhou', mensagem_erro = ? WHERE id = ?")->execute([$e->getMessage(), $id]);
            throw $e;
        }
        break;

    case 'cleanup':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            throw new Exception("ID de arquivo inválido.");
        }

        $zipPath = $tmpDir . '/' . $id . '.zip';
        $extractPath = $tmpDir . '/' . $id;

        // Delete files
        if (is_file($zipPath)) @unlink($zipPath);
        if (is_dir($extractPath)) removeDirectory($extractPath);

        $db->prepare("UPDATE arquivos_importacao SET situacao_carga = 'Carregado', ultima_execucao = NOW() WHERE id = ?")->execute([$id]);
        refreshCatDashboardDailyCache($db);

        echo json_encode([
            'success' => true
        ]);
        break;

    case 'reset':
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            throw new Exception("Reset de carga exige requisição POST.");
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new Exception("ID de arquivo inválido.");
        }

        $db->beginTransaction();
        // 1. Delete rows in registros_brutos
        $db->prepare("DELETE FROM registros_brutos WHERE arquivo_importacao_id = ?")->execute([$id]);
        // 2. Reset status in arquivos_importacao
        $db->prepare("UPDATE arquivos_importacao SET situacao_extracao = 'Pendente', situacao_carga = 'Pendente', linhas_processadas = 0, mensagem_erro = NULL, ultima_execucao = NULL WHERE id = ?")->execute([$id]);
        $db->commit();
        refreshCatDashboardDailyCache($db);

        // Also clean up any lingering temp files
        $zipPath = $tmpDir . '/' . $id . '.zip';
        $extractPath = $tmpDir . '/' . $id;
        if (is_file($zipPath)) @unlink($zipPath);
        if (is_dir($extractPath)) removeDirectory($extractPath);

        echo json_encode([
            'success' => true
        ]);
        break;

    case 'query_records':
        $offset = (int)($_GET['offset'] ?? 0);
        $arquivoId = (int)($_GET['arquivo_id'] ?? 0);
        $cbo = trim($_GET['cbo'] ?? '');
        $cid = trim($_GET['cid'] ?? '');
        $cnae = trim($_GET['cnae'] ?? '');
        $cnpj = preg_replace('/\D+/', '', trim($_GET['cnpj'] ?? ''));
        $sexo = trim($_GET['sexo'] ?? '');
        $tipo = trim($_GET['tipo'] ?? '');
        $obito = trim($_GET['obito'] ?? '');
        $estado = trim($_GET['estado'] ?? '');
        $municipio = trim($_GET['municipio'] ?? '');
        $dataInicio = trim($_GET['data_inicio'] ?? '');
        $dataFim = trim($_GET['data_fim'] ?? '');
        $hashExtended = strtolower(trim($_GET['hash_extended'] ?? ''));
        $registroOrigemId = trim($_GET['registro_origem_id'] ?? '');
        
        $where = [];
        $params = [];
        
        if ($arquivoId > 0) {
            $where[] = "rb.arquivo_importacao_id = :arquivo_id";
            $params['arquivo_id'] = $arquivoId;
        }
        if ($hashExtended !== '') {
            if (!preg_match('/^[a-f0-9]{64}$/', $hashExtended)) {
                throw new Exception("Hash extended inválido.");
            }
            $where[] = "rb.hash_extended = :hash_extended";
            $params['hash_extended'] = $hashExtended;
        }
        if ($registroOrigemId !== '') {
            if (!preg_match('/^[0-9]+-[0-9]+$/', $registroOrigemId)) {
                throw new Exception("ID rastreavel invalido.");
            }
            $where[] = "rb.registro_origem_id = :registro_origem_id";
            $params['registro_origem_id'] = $registroOrigemId;
        }
        if ($cbo !== '') {
            $where[] = "rb.dados->>'cbo' LIKE :cbo";
            $params['cbo'] = '%' . $cbo . '%';
        }
        if ($cid !== '') {
            $where[] = "rb.dados->>'cid_10' LIKE :cid";
            $params['cid'] = '%' . $cid . '%';
        }
        if ($cnae !== '') {
            $where[] = "rb.dados->>'cnae2_0_empregador' LIKE :cnae";
            $params['cnae'] = '%' . $cnae . '%';
        }
        if ($cnpj !== '') {
            if (strlen($cnpj) < 8 || strlen($cnpj) > 14) {
                throw new Exception("CNPJ invÃ¡lido.");
            }
            $where[] = "regexp_replace(COALESCE(rb.dados->>'cnpj_cei_empregador', ''), '\\D', '', 'g') LIKE :cnpj";
            $params['cnpj'] = $cnpj . '%';
        }
        if ($sexo !== '') {
            $where[] = "rb.dados->>'sexo' = :sexo";
            $params['sexo'] = $sexo;
        }
        if ($tipo !== '') {
            $where[] = "rb.dados->>'tipo_do_acidente' = :tipo";
            $params['tipo'] = $tipo;
        }
        if ($obito !== '') {
            $where[] = "(rb.dados->>'indica_obito_acidente' = :obito OR rb.dados->>'indica_bito_acidente' = :obito)";
            $params['obito'] = $obito;
        }
        if ($estado !== '') {
            $where[] = "(rb.dados->>'uf_munic_acidente' ILIKE :estado OR rb.dados->>'uf_munic_empregador' ILIKE :estado)";
            $params['estado'] = '%' . $estado . '%';
        }
        if ($municipio !== '') {
            $where[] = "(rb.dados->>'munic_empr' ILIKE :municipio)";
            $params['municipio'] = '%' . $municipio . '%';
        }
        if ($dataInicio !== '') {
            $where[] = "(CASE 
                WHEN rb.dados->>'data_acidente' ~ '^[0-3][0-9]/[0-1][0-9]/[0-9]{4}$' 
                THEN to_date(rb.dados->>'data_acidente', 'DD/MM/YYYY')
                ELSE NULL 
             END) >= :data_inicio::date";
            $params['data_inicio'] = $dataInicio;
        }
        if ($dataFim !== '') {
            $where[] = "(CASE 
                WHEN rb.dados->>'data_acidente' ~ '^[0-3][0-9]/[0-1][0-9]/[0-9]{4}$' 
                THEN to_date(rb.dados->>'data_acidente', 'DD/MM/YYYY')
                ELSE NULL 
             END) <= :data_fim::date";
            $params['data_fim'] = $dataFim;
        }
        
        $whereSql = '';
        if (count($where) > 0) {
            $whereSql = 'WHERE ' . implode(' AND ', $where);
        }
        
        // Get total matching count
        $countQuery = "SELECT COUNT(*) FROM registros_brutos rb $whereSql";
        $stmtCount = $db->prepare($countQuery);
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();
        
        $record = null;
        $arquivoNome = '';
        $duplicateTotal = 0;
        
        if ($total > 0 && $offset >= 0 && $offset < $total) {
            $query = "
                SELECT rb.*, ai.nome as arquivo_nome 
                  FROM registros_brutos rb
                  JOIN arquivos_importacao ai ON ai.id = rb.arquivo_importacao_id
                  $whereSql
                 ORDER BY (CASE 
                             WHEN rb.dados->>'data_acidente' ~ '^[0-3][0-9]/[0-1][0-9]/[0-9]{4}$' 
                             THEN to_date(rb.dados->>'data_acidente', 'DD/MM/YYYY')
                             ELSE NULL 
                           END) DESC NULLS LAST, rb.id DESC
                 LIMIT 1 OFFSET :offset
            ";
            $stmt = $db->prepare($query);
            
            // Add parameters
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $res = $stmt->fetch();
            if ($res) {
                $record = json_decode($res['dados'], true);
                $arquivoNome = $res['arquivo_nome'];
                $record['_registro_origem_id'] = $res['registro_origem_id'] ?? ($record['_registro_origem_id'] ?? null);
                $record['_numero_linha_arquivo'] = $res['numero_linha_arquivo'] ?? ($record['_numero_linha_arquivo'] ?? null);
                $record['_hash_extended'] = $res['hash_extended'] ?? ($record['_hash_extended'] ?? null);
                if (!empty($res['hash_extended'])) {
                    $stmtDuplicates = $db->prepare("SELECT COUNT(*) FROM registros_brutos WHERE hash_extended = :hash_extended");
                    $stmtDuplicates->execute(['hash_extended' => $res['hash_extended']]);
                    $duplicateTotal = (int)$stmtDuplicates->fetchColumn();
                }
                
                // Enrich CBO details using dictionaries if code exists
                if (!empty($record['cbo'])) {
                    $record['cbo_enriched'] = enrichCBO($record['cbo']);
                }
                
                // Enrich CID details using dictionaries if code exists
                if (!empty($record['cid_10'])) {
                    $record['cid_enriched'] = enrichCID($record['cid_10']);
                }

                // Enrich territory details using IBGE dictionaries if a municipality code is present.
                if (!empty($record['munic_empr'])) {
                    $record['territorio_empregador_enriched'] = enrichTerritory($record['munic_empr']);
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'total'   => $total,
            'offset'  => $offset,
            'record'  => $record,
            'arquivo_nome' => $arquivoNome,
            'duplicate_total' => $duplicateTotal
        ]);
        break;

    case 'cnpj_filter_options':
        $type = trim($_GET['type'] ?? '');
        $q = trim($_GET['q'] ?? '');
        $estado = trim($_GET['estado'] ?? '');
        if (!in_array($type, ['estado', 'municipio'], true)) {
            throw new Exception('Tipo de filtro invalido.');
        }
        if (mb_strlen($q) < 3) {
            echo json_encode(['success' => true, 'options' => []], JSON_UNESCAPED_UNICODE);
            break;
        }

        if ($type === 'estado') {
            $stmt = $db->prepare("
                SELECT DISTINCT ca.uf_empregador AS option_value
                  FROM cnpj_agregados ca
                 WHERE ca.uf_empregador IS NOT NULL
                   AND ca.uf_empregador <> ''
                   AND ca.uf_empregador ILIKE :q
                 ORDER BY ca.uf_empregador
                 LIMIT 30
            ");
            $stmt->execute(['q' => '%' . $q . '%']);
            echo json_encode(['success' => true, 'options' => $stmt->fetchAll(PDO::FETCH_COLUMN)], JSON_UNESCAPED_UNICODE);
            break;
        }

        if ($estado === '') {
            echo json_encode(['success' => true, 'options' => []], JSON_UNESCAPED_UNICODE);
            break;
        }

        $stmt = $db->prepare("
            SELECT DISTINCT ca.municipio_empregador AS option_value
              FROM cnpj_agregados ca
             WHERE ca.municipio_empregador IS NOT NULL
               AND ca.municipio_empregador <> ''
               AND ca.uf_empregador ILIKE :estado
               AND ca.municipio_empregador ILIKE :q
             ORDER BY ca.municipio_empregador
             LIMIT 30
        ");
        $stmt->execute([
            'q' => '%' . $q . '%',
            'estado' => '%' . $estado . '%',
        ]);
        echo json_encode(['success' => true, 'options' => $stmt->fetchAll(PDO::FETCH_COLUMN)], JSON_UNESCAPED_UNICODE);
        break;

    case 'cnpj_aggregates':
        $rawQ = trim($_GET['q'] ?? '');
        $q = preg_replace('/\D+/', '', $rawQ);
        $textQ = trim($rawQ);
        $estadoFiltro = trim($_GET['estado'] ?? '');
        $municipioFiltro = trim($_GET['municipio'] ?? '');
        $matrizFiltro = preg_replace('/\D+/', '', trim($_GET['matriz'] ?? ''));
        $filialFiltro = preg_replace('/\D+/', '', trim($_GET['filial'] ?? ''));
        $razaoFiltro = trim($_GET['razao_social'] ?? '');
        $situacaoFiltro = trim($_GET['situacao'] ?? '');
        $limit = min(max((int)($_GET['limit'] ?? 100), 10), 300);
        $offset = max((int)($_GET['offset'] ?? 0), 0);
        $sort = $_GET['sort'] ?? 'acidentes';
        $dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $sortMap = [
            'acidentes' => 'ca.acidentes',
            'obitos' => 'ca.obitos',
            'ultima_ocorrencia' => 'ca.ultima_ocorrencia',
            'cnpj' => 'ca.cnpj_digits',
        ];
        $sortExpr = $sortMap[$sort] ?? $sortMap['acidentes'];

        $whereParts = [];
        $params = [];
        if ($rawQ !== '') {
            if (mb_strlen($textQ) < 3 && strlen($q) < 3) {
                echo json_encode([
                    'success' => true,
                    'total' => 0,
                    'rows' => [],
                    'message' => 'Digite ao menos 3 dÃ­gitos para buscar.'
                ], JSON_UNESCAPED_UNICODE);
                break;
            }
            $conditions = [];
            if (strlen($q) >= 3) {
                $conditions[] = "ca.cnpj_digits LIKE :q_digits";
                $conditions[] = "ca.matriz LIKE :q_digits";
                $conditions[] = "ca.filial LIKE :q_digits";
                $params['q_digits'] = $q . '%';
            }
            if (mb_strlen($textQ) >= 3) {
                $conditions[] = "ca.cnae_descricao ILIKE :q_text";
                $conditions[] = "ca.municipio_empregador ILIKE :q_text";
                $conditions[] = "ca.uf_empregador ILIKE :q_text";
                $conditions[] = "co.razao_social ILIKE :q_text";
                $conditions[] = "co.nome_fantasia ILIKE :q_text";
                $params['q_text'] = '%' . $textQ . '%';
            }
            $whereParts[] = "(" . implode(' OR ', $conditions) . ")";
        }
        if ($estadoFiltro !== '') {
            $whereParts[] = "(ca.uf_empregador ILIKE :estado OR co.uf ILIKE :estado)";
            $params['estado'] = '%' . $estadoFiltro . '%';
        }
        if ($municipioFiltro !== '') {
            $whereParts[] = "(ca.municipio_empregador ILIKE :municipio OR co.municipio ILIKE :municipio)";
            $params['municipio'] = '%' . $municipioFiltro . '%';
        }
        if ($matrizFiltro !== '') {
            $whereParts[] = "ca.matriz LIKE :matriz";
            $params['matriz'] = $matrizFiltro . '%';
        }
        if ($filialFiltro !== '') {
            $whereParts[] = "ca.filial LIKE :filial";
            $params['filial'] = $filialFiltro . '%';
        }
        if ($razaoFiltro !== '') {
            $whereParts[] = "(co.razao_social ILIKE :razao_social OR co.nome_fantasia ILIKE :razao_social)";
            $params['razao_social'] = '%' . $razaoFiltro . '%';
        }
        if ($situacaoFiltro !== '') {
            $whereParts[] = "co.situacao ILIKE :situacao";
            $params['situacao'] = '%' . $situacaoFiltro . '%';
        }
        $where = count($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

        $stmtTotal = $db->prepare("
            SELECT COUNT(*)
              FROM cnpj_agregados ca
              LEFT JOIN cnpj_cache_opencnpj co
                ON co.cnpj_digits = ca.cnpj_digits
               AND co.dataset = 'receita'
              $where
        ");
        $stmtTotal->execute($params);
        $total = (int)$stmtTotal->fetchColumn();

        $stmtRows = $db->prepare("
            SELECT ca.*
              FROM cnpj_agregados ca
              LEFT JOIN cnpj_cache_opencnpj co
                ON co.cnpj_digits = ca.cnpj_digits
               AND co.dataset = 'receita'
              $where
             ORDER BY $sortExpr $dir NULLS LAST, ca.ultima_ocorrencia DESC NULLS LAST, ca.cnpj_digits
             LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $key => $val) {
            $stmtRows->bindValue($key, $val);
        }
        $stmtRows->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmtRows->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmtRows->execute();
        $rows = enrichCnaeLabels($db, $stmtRows->fetchAll());

        echo json_encode([
            'success' => true,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'rows' => $rows,
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'cnpj_cache_status':
        $cnpjs = $_GET['cnpjs'] ?? '';
        $items = array_values(array_unique(array_filter(array_map(
            'normalizeCnpjDigits',
            explode(',', (string)$cnpjs)
        ))));
        if (count($items) > 25) {
            throw new Exception('Consulta de cache limitada a 25 CNPJs por chamada.');
        }

        $rows = [];
        foreach ($items as $cnpj) {
            if (!isValidCnpjDigits($cnpj)) {
                continue;
            }
            $rows[$cnpj] = openCnpjCacheRowToPayload(getOpenCnpjCache($db, $cnpj));
        }

        echo json_encode([
            'success' => true,
            'cache' => $rows,
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'fetch_opencnpj':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            throw new Exception('Use POST para consultar a OpenCNPJ.');
        }
        $payload = readJsonPayload();
        $cnpj = normalizeCnpjDigits((string)($payload['cnpj'] ?? ''));
        $force = !empty($payload['force']);
        $allowStale = array_key_exists('allow_stale', $payload) ? (bool)$payload['allow_stale'] : true;

        echo json_encode([
            'success' => true,
            'item' => fetchOpenCnpj($db, $cnpj, $force, $allowStale),
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'fetch_opencnpj_batch':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            throw new Exception('Use POST para consultar a OpenCNPJ.');
        }
        $payload = readJsonPayload();
        $items = $payload['cnpjs'] ?? [];
        if (!is_array($items)) {
            throw new Exception('Lista de CNPJs invÃ¡lida.');
        }
        $items = array_values(array_unique(array_filter(array_map(
            fn($item) => normalizeCnpjDigits((string)$item),
            $items
        ))));
        if (count($items) > OPENCNPJ_BATCH_LIMIT) {
            throw new Exception('Lote limitado a ' . OPENCNPJ_BATCH_LIMIT . ' CNPJs por chamada.');
        }
        $force = !empty($payload['force']);
        $allowStale = array_key_exists('allow_stale', $payload) ? (bool)$payload['allow_stale'] : true;
        $result = [];
        foreach ($items as $cnpj) {
            if (!isValidCnpjDigits($cnpj)) {
                $result[$cnpj] = ['cnpj_digits' => $cnpj, 'erro' => 'CNPJ invÃ¡lido.'];
                continue;
            }
            $result[$cnpj] = fetchOpenCnpj($db, $cnpj, $force, $allowStale);
        }

        echo json_encode([
            'success' => true,
            'items' => $result,
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'refresh_cnpj_aggregates':
        $inserted = refreshCnpjAggregates($db);
        echo json_encode([
            'success' => true,
            'rows' => $inserted,
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'refresh_dashboard_cache':
        $inserted = refreshCatDashboardDailyCache($db);
        echo json_encode([
            'success' => true,
            'rows' => $inserted,
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'cnpj_cats':
        $cnpj = normalizeCnpjDigits((string)($_GET['cnpj'] ?? ''));
        if (!isValidCnpjDigits($cnpj)) {
            throw new Exception('CNPJ invalido.');
        }
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = (int)($_GET['per_page'] ?? 25);
        $allowedPerPage = [10, 25, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 25;
        }

        $stmtTotal = $db->prepare("
            SELECT COUNT(*)
              FROM registros_brutos rb
             WHERE regexp_replace(COALESCE(rb.dados->>'cnpj_cei_empregador', ''), '\\D', '', 'g') = :cnpj
        ");
        $stmtTotal->execute(['cnpj' => $cnpj]);
        $total = (int)$stmtTotal->fetchColumn();
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $stmtRows = $db->prepare("
            SELECT rb.registro_origem_id,
                   rb.dados->>'data_acidente' AS data_acidente,
                   rb.dados->>'tipo_do_acidente' AS tipo_acidente,
                   COALESCE(rb.dados->>'indica_obito_acidente', rb.dados->>'indica_bito_acidente') AS obito,
                   rb.dados->>'cid_10' AS cid,
                   rb.dados->>'cbo' AS cbo
              FROM registros_brutos rb
             WHERE regexp_replace(COALESCE(rb.dados->>'cnpj_cei_empregador', ''), '\\D', '', 'g') = :cnpj
             ORDER BY parse_date_immutable(rb.dados->>'data_acidente') DESC NULLS LAST, rb.id DESC
             LIMIT :limit OFFSET :offset
        ");
        $stmtRows->bindValue('cnpj', $cnpj);
        $stmtRows->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmtRows->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmtRows->execute();

        $rows = array_map(static function (array $row): array {
            $cid = trim((string)($row['cid'] ?? ''));
            $cbo = trim((string)($row['cbo'] ?? ''));
            $cidInfo = $cid !== '' ? enrichCID($cid) : null;
            $cboInfo = $cbo !== '' ? enrichCBO($cbo) : null;
            return [
                'registro_origem_id' => $row['registro_origem_id'] ?? null,
                'data_acidente' => $row['data_acidente'] ?? null,
                'tipo_acidente' => $row['tipo_acidente'] ?? null,
                'obito' => $row['obito'] ?? null,
                'cid' => $cid,
                'cid_label' => $cidInfo['subcategoria'] ?? $cidInfo['categoria'] ?? null,
                'cbo' => $cbo,
                'cbo_label' => $cboInfo['ocupacao'] ?? null,
            ];
        }, $stmtRows->fetchAll());

        echo json_encode([
            'success' => true,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => min($offset + $perPage, $total),
            'rows' => $rows,
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'chart_data':
        ensureCatDashboardDailyCache($db);

        $obito = trim($_GET['obito'] ?? '');
        $estado = trim($_GET['estado'] ?? '');
        $municipio = trim($_GET['municipio'] ?? '');
        $dataInicio = trim($_GET['data_inicio'] ?? '');
        $dataFim = trim($_GET['data_fim'] ?? '');
        
        $where = [];
        $params = [];
        
        if ($obito !== '') {
            $where[] = "obito = :obito";
            $params['obito'] = $obito;
        }
        if ($estado !== '') {
            $where[] = "(uf_empregador = :estado OR uf_acidente = :estado)";
            $params['estado'] = $estado;
        }
        if ($municipio !== '') {
            $where[] = "municipio_empregador ILIKE :municipio";
            $params['municipio'] = '%' . $municipio . '%';
        }
        if ($dataInicio !== '') {
            $where[] = "data_acidente >= :data_inicio::date";
            $params['data_inicio'] = $dataInicio;
        }
        if ($dataFim !== '') {
            $where[] = "data_acidente <= :data_fim::date";
            $params['data_fim'] = $dataFim;
        }
        
        $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $query = "
            SELECT 
                to_char(date_trunc('month', data_acidente), 'YYYY-MM') as mes_ano,
                SUM(total) as total
            FROM cat_dashboard_daily_cache
            $whereSql
            GROUP BY mes_ano
            ORDER BY mes_ano ASC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        
        $labels = [];
        $values = [];
        
        foreach ($rows as $row) {
            $parts = explode('-', $row['mes_ano']);
            if (count($parts) === 2) {
                $labels[] = $parts[1] . '/' . $parts[0];
            } else {
                $labels[] = $row['mes_ano'];
            }
            $values[] = (int)$row['total'];
        }
        
        // Also get filtered totals to update landing page cards dynamically!
        $totalRowsQuery = "
            SELECT COALESCE(SUM(total), 0)
            FROM cat_dashboard_daily_cache
            $whereSql
        ";
        $stmtTotal = $db->prepare($totalRowsQuery);
        $stmtTotal->execute($params);
        $filteredTotal = (int)$stmtTotal->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'labels'  => $labels,
            'values'  => $values,
            'total_acidentes' => $filteredTotal
        ]);
        break;

    case 'quality_dashboard':
        $obito = trim($_GET['obito'] ?? '');
        $estado = trim($_GET['estado'] ?? '');
        $municipio = trim($_GET['municipio'] ?? '');
        $dataInicio = trim($_GET['data_inicio'] ?? '');
        $dataFim = trim($_GET['data_fim'] ?? '');

        $scopeFile = $db->query("
            SELECT id, nome
             FROM arquivos_importacao
             WHERE linhas_processadas > 0
             ORDER BY id DESC
             LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC) ?: null;
        $scopeFileId = $scopeFile ? (int)$scopeFile['id'] : 0;
        $qualityWhere = [];
        $distributionWhere = [];
        $qualityParams = [];

        if ($scopeFileId > 0) {
            $qualityWhere[] = "arquivo_importacao_id = :arquivo_qualidade_id";
            $distributionWhere[] = "rb.arquivo_importacao_id = :arquivo_qualidade_id";
            $qualityParams['arquivo_qualidade_id'] = $scopeFileId;
        }
        if ($obito !== '') {
            $qualityWhere[] = "(dados->>'indica_obito_acidente' = :obito OR dados->>'indica_bito_acidente' = :obito)";
            $distributionWhere[] = "(rb.dados->>'indica_obito_acidente' = :obito OR rb.dados->>'indica_bito_acidente' = :obito)";
            $qualityParams['obito'] = $obito;
        }
        if ($estado !== '') {
            $qualityWhere[] = "(dados->>'uf_munic_empregador' = :estado OR dados->>'uf_munic_acidente' = :estado)";
            $distributionWhere[] = "(rb.dados->>'uf_munic_empregador' = :estado OR rb.dados->>'uf_munic_acidente' = :estado)";
            $qualityParams['estado'] = $estado;
        }
        if ($municipio !== '') {
            $qualityWhere[] = "dados->>'munic_empr' ILIKE :municipio";
            $distributionWhere[] = "rb.dados->>'munic_empr' ILIKE :municipio";
            $qualityParams['municipio'] = '%' . $municipio . '%';
        }
        if ($dataInicio !== '') {
            $qualityWhere[] = "parse_date_immutable(dados->>'data_acidente') >= :data_inicio::date";
            $distributionWhere[] = "parse_date_immutable(rb.dados->>'data_acidente') >= :data_inicio::date";
            $qualityParams['data_inicio'] = $dataInicio;
        }
        if ($dataFim !== '') {
            $qualityWhere[] = "parse_date_immutable(dados->>'data_acidente') <= :data_fim::date";
            $distributionWhere[] = "parse_date_immutable(rb.dados->>'data_acidente') <= :data_fim::date";
            $qualityParams['data_fim'] = $dataFim;
        }

        $qualityWhereSql = count($qualityWhere) > 0 ? 'WHERE ' . implode(' AND ', $qualityWhere) : '';
        $distributionWhereSql = count($distributionWhere) > 0 ? 'WHERE ' . implode(' AND ', $distributionWhere) : '';

        $stmtQualityTotal = $db->prepare("SELECT COUNT(*) FROM registros_brutos $qualityWhereSql");
        $stmtQualityTotal->execute($qualityParams);
        $totalQualityRows = (int)$stmtQualityTotal->fetchColumn();

        $qualityFields = [
            'cbo' => ['expr' => "dados->>'cbo'", 'type' => 'code'],
            'sexo' => ['expr' => "dados->>'sexo'", 'type' => 'category'],
            'cid_10' => ['expr' => "dados->>'cid_10'", 'type' => 'code'],
            'munic_empr' => ['expr' => "dados->>'munic_empr'", 'type' => 'code'],
            'emitente_cat' => ['expr' => "dados->>'emitente_cat'", 'type' => 'category'],
            'data_acidente' => ['expr' => "dados->>'data_acidente'", 'type' => 'date'],
            'data_nascimento' => ['expr' => "dados->>'data_nascimento'", 'type' => 'date'],
            'data_afastamento' => ['expr' => "dados->>'data_afastamento'", 'type' => 'date'],
            'data_emissao_cat' => ['expr' => "dados->>'data_emissao_cat'", 'type' => 'date'],
            'tipo_do_acidente' => ['expr' => "dados->>'tipo_do_acidente'", 'type' => 'category'],
            'filiacao_segurado' => ['expr' => "dados->>'filiacao_segurado'", 'type' => 'category'],
            'natureza_da_lesao' => ['expr' => "dados->>'natureza_da_lesao'", 'type' => 'category'],
            'uf_munic_acidente' => ['expr' => "dados->>'uf_munic_acidente'", 'type' => 'category'],
            'cnae2_0_empregador' => ['expr' => "dados->>'cnae2_0_empregador'", 'type' => 'code'],
            'tipo_de_empregador' => ['expr' => "dados->>'tipo_de_empregador'", 'type' => 'category'],
            'cnpj_cei_empregador' => ['expr' => "dados->>'cnpj_cei_empregador'", 'type' => 'code'],
            'uf_munic_empregador' => ['expr' => "dados->>'uf_munic_empregador'", 'type' => 'category'],
            'especie_do_beneficio' => ['expr' => "dados->>'especie_do_beneficio'", 'type' => 'category'],
            'parte_corpo_atingida' => ['expr' => "dados->>'parte_corpo_atingida'", 'type' => 'category'],
            'indica_obito_acidente' => ['expr' => "COALESCE(dados->>'indica_obito_acidente', dados->>'indica_bito_acidente')", 'type' => 'category'],
            'data_despacho_beneficio' => ['expr' => "dados->>'data_despacho_beneficio'", 'type' => 'date'],
            'agente_causador_acidente' => ['expr' => "COALESCE(dados->>'agente_causador_acidente', dados->>'agente_causador')", 'type' => 'category'],
            'origem_de_cadastramento_cat' => ['expr' => "dados->>'origem_de_cadastramento_cat'", 'type' => 'category'],
        ];

        $selects = [];
        foreach ($qualityFields as $field => $definition) {
            $expr = '(' . $definition['expr'] . ')';
            $notFilled = "$expr IS NULL OR btrim($expr) = ''";
            $ignored = "$expr IS NOT NULL AND btrim($expr) <> '' AND (lower(btrim($expr)) IN ('{ñ class}', '{n class}', 'nao informado', 'não informado', 'indeterminado', 'ignorado') OR lower(btrim($expr)) LIKE '%ignorado%' OR lower(btrim($expr)) LIKE 'zerado%')";

            $selects[] = "COUNT(*) FILTER (WHERE $notFilled) AS {$field}_not_filled";
            $selects[] = "COUNT(*) FILTER (WHERE $ignored) AS {$field}_ignored";

            if ($definition['type'] === 'date') {
                $selects[] = "COUNT(*) FILTER (WHERE NOT ($notFilled) AND ($expr = '00/00/0000' OR parse_date_immutable($expr) IS NULL)) AS {$field}_invalid";
            } elseif ($field === 'cnae2_0_empregador') {
                $selects[] = "COUNT(*) FILTER (WHERE $expr = '0000') AS {$field}_invalid";
            }
        }

        $stmtQuality = $db->prepare("SELECT " . implode(",\n", $selects) . " FROM registros_brutos $qualityWhereSql");
        $stmtQuality->execute($qualityParams);
        $quality = $stmtQuality->fetch(PDO::FETCH_ASSOC) ?: [];
        $findings = [];
        foreach ($qualityFields as $field => $definition) {
            $statuses = [
                'not_filled' => 'Não preenchido',
                'ignored' => 'Ignorado / não classificado',
                'invalid' => 'Inválido',
            ];
            foreach ($statuses as $suffix => $statusLabel) {
                $key = $field . '_' . $suffix;
                if (!array_key_exists($key, $quality)) {
                    continue;
                }
                $count = (int)$quality[$key];
                if ($count <= 0) {
                    continue;
                }
                $findings[] = [
                    'field' => $field,
                    'status' => $statusLabel,
                    'label' => $field . ' - ' . $statusLabel,
                    'count' => $count,
                    'percent' => $totalQualityRows > 0 ? round(($count / $totalQualityRows) * 100, 1) : 0,
                ];
            }
        }
        usort($findings, fn($a, $b) => ($b['count'] <=> $a['count']) ?: strcmp($a['label'], $b['label']));

        $distributionSql = "
            SELECT campo, valor, COUNT(*) AS total
              FROM (
                    SELECT campo,
                           CASE
                               WHEN valor IS NULL OR btrim(valor) = '' THEN 'Ignorado / não informado'
                               WHEN lower(btrim(valor)) IN ('{ñ class}', '{n class}', 'nao informado', 'não informado', 'indeterminado', 'ignorado') THEN 'Ignorado / não informado'
                               WHEN lower(btrim(valor)) LIKE '%ignorado%' OR lower(btrim(valor)) LIKE 'zerado%' THEN 'Ignorado / não informado'
                               ELSE valor
                           END AS valor
                      FROM registros_brutos rb
                      CROSS JOIN LATERAL (
                            VALUES
                                ('tipo_acidente', rb.dados->>'tipo_do_acidente'),
                                ('indica_obito_acidente', COALESCE(rb.dados->>'indica_obito_acidente', rb.dados->>'indica_bito_acidente')),
                                ('sexo', rb.dados->>'sexo'),
                                ('emitente_cat', rb.dados->>'emitente_cat'),
                                ('origem_cadastramento_cat', rb.dados->>'origem_de_cadastramento_cat'),
                                ('filiacao_segurado', rb.dados->>'filiacao_segurado'),
                                ('tipo_empregador', rb.dados->>'tipo_de_empregador')
                      ) AS dist(campo, valor)
                     $distributionWhereSql
              ) normalized
             GROUP BY campo, valor
             ORDER BY campo, total DESC, valor ASC
        ";
        $stmtDistribution = $db->prepare($distributionSql);
        $stmtDistribution->execute($qualityParams);
        $distributionRows = $stmtDistribution->fetchAll(PDO::FETCH_ASSOC);
        $distributions = [];
        foreach ($distributionRows as $row) {
            $field = $row['campo'];
            if (!isset($distributions[$field])) {
                $distributions[$field] = [];
            }
            if (count($distributions[$field]) >= 6) {
                continue;
            }
            $count = (int)$row['total'];
            $distributions[$field][] = [
                'value' => $row['valor'],
                'count' => $count,
                'percent' => $totalQualityRows > 0 ? round(($count / $totalQualityRows) * 100, 1) : 0,
            ];
        }

        echo json_encode([
            'success' => true,
            'total_records' => $totalQualityRows,
            'scope' => [
                'arquivo_id' => $scopeFileId,
                'arquivo_nome' => $scopeFile['nome'] ?? null,
            ],
            'quality_findings' => $findings,
            'distributions' => $distributions,
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'get_states':
        ensureCatDashboardDailyCache($db);

        $q = trim($_GET['q'] ?? '');
        $whereState = "
             WHERE uf_empregador IS NOT NULL
               AND uf_empregador != '{Ã± class}'
               AND uf_empregador != ''
        ";
        $stateParams = [];
        if ($q !== '') {
            $whereState .= " AND uf_empregador ILIKE :q";
            $stateParams['q'] = '%' . $q . '%';
        }
        $stmt = $db->prepare("
            SELECT DISTINCT uf_empregador as estado
              FROM cat_dashboard_daily_cache
              $whereState
             ORDER BY estado ASC
             LIMIT 30
        ");
        $stmt->execute($stateParams);
        $states = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['success' => true, 'states' => $states]);
        break;

    case 'get_cities':
        ensureCatDashboardDailyCache($db);

        $estado = trim($_GET['estado'] ?? '');
        $q = trim($_GET['q'] ?? '');
        $where = "WHERE municipio_empregador IS NOT NULL AND municipio_empregador != ''";
        $params = [];
        if ($estado !== '') {
            $where .= " AND (uf_empregador = :estado OR uf_acidente = :estado)";
            $params['estado'] = $estado;
        } else {
            echo json_encode(['success' => true, 'cities' => []]);
            break;
        }
        if ($q !== '') {
            $where .= " AND municipio_empregador ILIKE :q";
            $params['q'] = '%' . $q . '%';
        }

        $stmt = $db->prepare("
            SELECT DISTINCT municipio_empregador as municipio
              FROM cat_dashboard_daily_cache
              $where
             ORDER BY municipio ASC
             LIMIT 30
        ");
        $stmt->execute($params);
        $cities = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['success' => true, 'cities' => $cities]);
        break;
    case 'log':
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $nivel = trim($_POST['nivel'] ?? $_GET['nivel'] ?? 'info');
        $mensagem = trim($_POST['mensagem'] ?? $_GET['mensagem'] ?? '');
        
        if ($id <= 0 || $mensagem === '') {
            throw new Exception("Parâmetros de log inválidos.");
        }
        
        $stmt = $db->prepare("INSERT INTO logs_execucao (arquivo_importacao_id, nivel, mensagem) VALUES (?, ?, ?)");
        $stmt->execute([$id, $nivel, $mensagem]);
        
        echo json_encode(['success' => true]);
        break;

    case 'get_logs':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            throw new Exception("ID de arquivo inválido.");
        }
        
        $stmt = $db->prepare("
            SELECT nivel, mensagem, criado_em AT TIME ZONE 'UTC' AT TIME ZONE 'America/Sao_Paulo' as data_hora 
              FROM logs_execucao 
             WHERE arquivo_importacao_id = ? 
             ORDER BY id ASC
        ");
        $stmt->execute([$id]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'logs' => $logs]);
        break;

    case 'rebuild_date_index':
        // Drop and recreate the date parsing index to use updated function
        $db->beginTransaction();
        try {
            $db->exec('DROP INDEX IF EXISTS idx_registros_brutos_data_acidente_parsed');
            $db->exec("CREATE INDEX idx_registros_brutos_data_acidente_parsed ON registros_brutos (parse_date_immutable(dados->>'data_acidente'))");
            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Date index rebuilt']);
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
        break;
    case 'audit_dates':
        // Optional file filter via GET parameter
        $arquivoId = isset($_GET['arquivo_id']) && $_GET['arquivo_id'] !== '' ? (int)$_GET['arquivo_id'] : null;
        $result = auditDataAcidente($db, $arquivoId);
        echo json_encode(['success' => true, 'data' => $result]);
        break;
}

// ========================================================
// CBO DICTIONARY HELPERS
// ========================================================
function loadCBODictionary($filepath) {
    static $cache = [];
    if (isset($cache[$filepath])) {
        return $cache[$filepath];
    }
    $dict = [];
    if (!file_exists($filepath)) {
        return $dict;
    }
    $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '{' || $line === '}' || $line === '},') continue;
        if (strpos($line, '{') === 0) {
            $line = substr($line, 1);
        }
        if (substr($line, -1) === '}') {
            $line = substr($line, 0, -1);
        }
        if (substr($line, -1) === ',') {
            $line = substr($line, 0, -1);
        }
        $line = trim($line);
        if ($line === '') continue;
        
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0], " \t\n\r\0\x0B'\"");
            $val = trim($parts[1], " \t\n\r\0\x0B'\",");
            $dict[$key] = $val;
        }
    }
    $cache[$filepath] = $dict;
    return $dict;
}

function enrichCBO($cboCode) {
    if (!$cboCode) return null;
    
    // Normalize code: strip non-alphanumeric
    $code = preg_replace('/[^a-zA-Z0-9]/', '', $cboCode);
    if ($code === '') return null;
    
    $dir = __DIR__ . '/../../cat/src/dicionarios';
    $gg = loadCBODictionary($dir . '/dict_cbo_gg.txt');
    $sp = loadCBODictionary($dir . '/dict_cbo_sp.txt');
    $sg = loadCBODictionary($dir . '/dict_cbo_sg.txt');
    $fa = loadCBODictionary($dir . '/dict_cbo_fa.txt');
    $oc = loadCBODictionary($dir . '/dict_cbo_oc.txt');
    
    $result = [
        'codigo' => $cboCode,
        'ocupacao' => $oc[$code] ?? null,
        'familia' => null,
        'subgrupo' => null,
        'subgrupo_principal' => null,
        'grande_grupo' => null
    ];
    
    if (strlen($code) >= 4) {
        $result['familia'] = $fa[substr($code, 0, 4)] ?? null;
    }
    if (strlen($code) >= 3) {
        $result['subgrupo'] = $sg[substr($code, 0, 3)] ?? null;
    }
    if (strlen($code) >= 2) {
        $result['subgrupo_principal'] = $sp[substr($code, 0, 2)] ?? null;
    }
    if (strlen($code) >= 1) {
        $result['grande_grupo'] = $gg[substr($code, 0, 1)] ?? null;
    }
    
    return $result;
}

function enrichCID($cidCode) {
    if (!$cidCode) return null;
    
    // Normalize code: strip spaces, make uppercase, strip dots
    $code = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $cidCode));
    if ($code === '') return null;
    
    $dir = __DIR__ . '/../../cat/src/dicionarios';
    $cap = loadCBODictionary($dir . '/dict_cid_cap.txt');
    $cat = loadCBODictionary($dir . '/dict_cid_cat.txt');
    $catCap = loadCBODictionary($dir . '/dict_cid_cat_cap.txt');
    $catGrup = loadCBODictionary($dir . '/dict_cid_cat_grup.txt');
    $grup = loadCBODictionary($dir . '/dict_cid_grup.txt');
    $scat = loadCBODictionary($dir . '/dict_cid_scat.txt');
    
    $scatDesc = $scat[$code] ?? null;
    
    $catCode = substr($code, 0, 3);
    $catDesc = $cat[$catCode] ?? null;
    
    $grupCode = $catGrup[$catCode] ?? null;
    $grupDesc = $grupCode ? ($grup[$grupCode] ?? null) : null;
    
    $capCode = $catCap[$catCode] ?? null;
    $capDesc = $capCode ? ($cap[$capCode] ?? null) : null;
    
    return [
        'codigo' => $cidCode,
        'subcategoria' => $scatDesc,
        'categoria' => $catDesc,
        'grupo' => $grupDesc,
        'capitulo' => $capDesc
    ];
}

function enrichTerritory($municipalityValue) {
    if (!$municipalityValue) return null;

    $code = preg_replace('/\D/', '', (string)$municipalityValue);
    if ($code === '') return null;

    $municipalityCode = substr($code, 0, 6);
    $ufCode = substr($municipalityCode, 0, 2);
    $regionCode = substr($municipalityCode, 0, 1);

    $dir = __DIR__ . '/../../cat/src/dicionarios';
    $municipalities = loadCBODictionary($dir . '/dict_municipio.txt');
    $ufs = loadCBODictionary($dir . '/dict_uf.txt');
    $regions = loadCBODictionary($dir . '/dict_regiao.txt');

    return [
        'codigo_municipio' => $municipalityCode,
        'municipio' => $municipalities[$municipalityCode] ?? null,
        'codigo_uf' => $ufCode,
        'uf' => $ufs[$ufCode] ?? null,
        'codigo_regiao' => $regionCode,
        'regiao' => $regions[$regionCode] ?? null,
    ];
}
