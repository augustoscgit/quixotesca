<?php
/**
 * ETL Helper functions for CAT Module
 */

function normalizeHeader(string $header): string
{
    // Trim and convert to lower case
    $str = mb_strtolower(trim($header), 'UTF-8');
    
    // Replace typical Portuguese accented letters
    $translits = [
        'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','æ'=>'ae',
        'ç'=>'c',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'ñ'=>'n',
        'ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'ý'=>'y','ÿ'=>'y',
    ];
    $str = strtr($str, $translits);
    
    // Replace non-alphanumeric characters with spaces
    $str = preg_replace('/[^a-z0-9]/', ' ', $str);
    
    // Replace multiple spaces with a single underscore
    $str = preg_replace('/\s+/', '_', trim($str));
    
    return $str;
}

function canonicalizeRecordForHash(array $record): array
{
    $canonical = [];
    foreach ($record as $key => $value) {
        if (str_starts_with((string)$key, '_')) {
            continue;
        }
        $canonical[$key] = is_string($value) ? trim($value) : $value;
    }

    ksort($canonical);
    return $canonical;
}

function calculateExtendedRecordHash(array $record): string
{
    $canonical = canonicalizeRecordForHash($record);
    return hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function downloadFile(string $url, string $destPath): void
{
    $dir = dirname($destPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $ch = curl_init($url);
    if ($ch === false) {
        throw new Exception("Failed to initialize cURL.");
    }

    $fp = fopen($destPath, 'wb');
    if ($fp === false) {
        curl_close($ch);
        throw new Exception("Failed to open local file for writing: $destPath");
    }

    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt_array($ch, [
        CURLOPT_TIMEOUT        => 300,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $success = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    fclose($fp);
    curl_close($ch);

    if (!$success || $httpCode >= 400) {
        if (file_exists($destPath)) {
            @unlink($destPath);
        }
        throw new Exception("Download failed with HTTP Code: $httpCode");
    }
}

function extractZip(string $zipPath, string $extractDir): string
{
    if (!class_exists('ZipArchive')) {
        throw new Exception("PHP ZipArchive extension is not enabled.");
    }
    
    $zip = new ZipArchive();
    $res = $zip->open($zipPath);
    if ($res !== true) {
        throw new Exception("Failed to open ZIP archive (not a valid ZIP). Code: " . $res);
    }
    
    // Find the first JSON file inside the ZIP
    $jsonFile = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'json') {
            $jsonFile = $filename;
            break;
        }
    }
    
    if (!$jsonFile) {
        $zip->close();
        throw new Exception("Validação de arquivo: O arquivo ZIP não contém o arquivo JSON esperado.");
    }
    
    if (!is_dir($extractDir)) {
        mkdir($extractDir, 0755, true);
    }
    
    // Extract only the JSON file
    $extracted = $zip->extractTo($extractDir, $jsonFile);
    $zip->close();
    
    if (!$extracted) {
        throw new Exception("Failed to extract JSON file from ZIP.");
    }
    
    return rtrim($extractDir, '/\\') . DIRECTORY_SEPARATOR . $jsonFile;
}

function getCSVRowCount(string $csvPath): int
{
    if (!is_file($csvPath) || !is_readable($csvPath)) {
        return 0;
    }
    $handle = fopen($csvPath, 'r');
    if (!$handle) return 0;
    
    $rows = 0;
    // Skip header line
    fgets($handle);
    while (fgets($handle) !== false) {
        $rows++;
    }
    fclose($handle);
    return $rows;
}

function readCSVBatch(string $csvPath, int $startRow, int $limit): array
{
    if (!is_file($csvPath) || !is_readable($csvPath)) {
        throw new Exception("CSV file not readable.");
    }
    
    $handle = fopen($csvPath, 'r');
    if (!$handle) {
        throw new Exception("Failed to open CSV file.");
    }
    
    // Detect delimiter
    $firstLine = fgets($handle);
    $delimiter = ';';
    if ($firstLine !== false) {
        if (strpos($firstLine, ',') !== false && strpos($firstLine, ';') === false) {
            $delimiter = ',';
        }
    }
    rewind($handle);
    
    // Read header row
    $headerLine = fgetcsv($handle, 0, $delimiter);
    if (!$headerLine) {
        fclose($handle);
        throw new Exception("Failed to read CSV header.");
    }
    
    $headers = [];
    $headerCounts = [];
    foreach ($headerLine as $h) {
        // Handle potential encoding conversion (often Latin1 in gov databases)
        $h = mb_convert_encoding($h, 'UTF-8', 'ISO-8859-1, UTF-8, Windows-1252');
        $normalized = normalizeHeader($h);
        if (!isset($headerCounts[$normalized])) {
            $headerCounts[$normalized] = 1;
            $headers[] = $normalized;
        } else {
            $headerCounts[$normalized]++;
            $headers[] = $normalized . '_' . ($headerCounts[$normalized] - 1);
        }
    }
    
    // Skip to startRow
    $currentRow = 0;
    while ($currentRow < $startRow) {
        if (fgetcsv($handle, 0, $delimiter) === false) {
            break;
        }
        $currentRow++;
    }
    
    // Read batch
    $batch = [];
    $count = 0;
    while ($count < $limit) {
        $row = fgetcsv($handle, 0, $delimiter);
        if ($row === false) {
            break;
        }
        
        $record = [];
        foreach ($headers as $index => $key) {
            $val = isset($row[$index]) ? trim($row[$index]) : '';
            $val = mb_convert_encoding($val, 'UTF-8', 'ISO-8859-1, UTF-8, Windows-1252');
            $record[$key] = ($val === '') ? null : $val;
        }
        $batch[] = $record;
        $count++;
    }
    
    fclose($handle);
    return $batch;
}

function cleanTempFiles(string ...$paths): void
{
    foreach ($paths as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function getJSONRecordCount(string $jsonPath): int
{
    if (!is_file($jsonPath) || !is_readable($jsonPath)) {
        return 0;
    }
    $handle = fopen($jsonPath, 'r');
    if (!$handle) return 0;
    $count = 0;
    $separator = '}},{"node":{';
    while (!feof($handle)) {
        $chunk = fread($handle, 81920);
        if ($chunk === false) break;
        $count += substr_count($chunk, $separator);
    }
    fclose($handle);
    // Add 1 for the first record if any content exists
    return $count > 0 ? $count + 1 : (filesize($jsonPath) > 50 ? 1 : 0);
}

function detectDateFormat(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $formats = [
        '/^\d{2}\/\d{2}\/\d{4}$/' => 'DD/MM/YYYY',
        '/^\d{4}-\d{2}-\d{2}$/' => 'YYYY-MM-DD',
        '/^\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2}$/' => 'DD/MM/YYYY HH:MM',
        '/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/' => 'YYYY-MM-DD HH:MM:SS',
        '/^\d{2}:\d{2}$/' => 'HH:MM',
        '/^\d{2}:\d{2}:\d{2}$/' => 'HH:MM:SS',
    ];

    foreach ($formats as $pattern => $label) {
        if (preg_match($pattern, $value)) {
            return $label;
        }
    }

    return null;
}

function inspectJSONStructure(string $jsonPath): array
{
    $handle = fopen($jsonPath, 'r');
    if (!$handle) {
        throw new Exception("Failed to open JSON file.");
    }

    $buffer = '';
    $currentRow = 0;
    $separator = '}},{"node":{';
    $fields = [];

    $addRecord = function (array $record) use (&$fields): void {
        foreach ($record as $field => $value) {
            if (!isset($fields[$field])) {
                $fields[$field] = [
                    'campo' => $field,
                    'ocorrencias' => 0,
                    'preenchidos' => 0,
                    'formatos_data' => [],
                    'exemplos' => [],
                ];
            }

            $fields[$field]['ocorrencias']++;

            if ($value !== null && trim((string)$value) !== '') {
                $fields[$field]['preenchidos']++;

                if (count($fields[$field]['exemplos']) < 3 && !in_array($value, $fields[$field]['exemplos'], true)) {
                    $fields[$field]['exemplos'][] = $value;
                }

                $format = detectDateFormat((string)$value);
                if ($format !== null && !in_array($format, $fields[$field]['formatos_data'], true)) {
                    $fields[$field]['formatos_data'][] = $format;
                }
            }
        }
    };

    while (!feof($handle)) {
        $chunk = fread($handle, 8192);
        if ($chunk === false) {
            break;
        }
        $buffer .= $chunk;

        while (($pos = strpos($buffer, $separator)) !== false) {
            $item = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + strlen($separator));
            $addRecord(cleanAndNormalizeJSONRecord($item, $currentRow === 0));
            $currentRow++;
        }
    }

    if (trim($buffer) !== '') {
        $item = preg_replace('/\]\}$/', '', trim($buffer));
        $item = preg_replace('/\}\}$/', '', $item);
        if ($item !== '') {
            $addRecord(cleanAndNormalizeJSONRecord($item, false));
            $currentRow++;
        }
    }

    fclose($handle);
    ksort($fields);

    return [
        'total_registros' => $currentRow,
        'total_campos' => count($fields),
        'campos' => array_values($fields),
    ];
}

function cleanAndNormalizeJSONRecord(string $item, bool $isFirst): array
{
    // Convert encoding to UTF-8 (since input typically uses ISO-8859-1 / Windows-1252)
    $item = mb_convert_encoding($item, 'UTF-8', 'ISO-8859-1, UTF-8, Windows-1252');

    if ($isFirst) {
        $pos = strpos($item, '{"nodes":[{"node":{');
        if ($pos !== false) {
            $item = substr($item, $pos + strlen('{"nodes":[{"node":{'));
        }
    }
    
    // Match key-value pairs with optional escaped characters
    $pattern = '/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"\s*:\s*"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/';
    preg_match_all($pattern, $item, $matches, PREG_SET_ORDER);
    
    $record = [];
    $keyCounts = [];
    foreach ($matches as $match) {
        $key = normalizeHeader($match[1]);
        $val = stripslashes($match[2]);
        
        if (!isset($keyCounts[$key])) {
            $keyCounts[$key] = 1;
            $record[$key] = ($val === '') ? null : $val;
        } else {
            $keyCounts[$key]++;
            $record[$key . '_' . ($keyCounts[$key] - 1)] = ($val === '') ? null : $val;
        }
    }
    return $record;
}

function readJSONBatch(string $jsonPath, int $startRow, int $limit): array
{
    $handle = fopen($jsonPath, 'r');
    if (!$handle) throw new Exception("Failed to open JSON file.");
    
    $buffer = '';
    $currentRow = 0;
    $records = [];
    $separator = '}},{"node":{';
    
    while (!feof($handle) && count($records) < $limit) {
        $chunk = fread($handle, 8192);
        if ($chunk === false) break;
        $buffer .= $chunk;
        
        while (($pos = strpos($buffer, $separator)) !== false) {
            $item = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + strlen($separator));
            
            if ($currentRow >= $startRow) {
                $records[] = cleanAndNormalizeJSONRecord($item, $currentRow === 0);
            }
            $currentRow++;
            
            if (count($records) >= $limit) {
                break 2;
            }
        }
    }
    
    if (count($records) < $limit && trim($buffer) !== '') {
        $item = preg_replace('/\]\}$/', '', trim($buffer));
        $item = preg_replace('/\}\}$/', '', $item);
        if ($currentRow >= $startRow && $item !== '') {
            $records[] = cleanAndNormalizeJSONRecord($item, false);
        }
    }
    
    fclose($handle);
    return $records;
}
