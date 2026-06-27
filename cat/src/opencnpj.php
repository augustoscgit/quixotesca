<?php
declare(strict_types=1);

const OPENCNPJ_DATASET = 'receita';
const OPENCNPJ_BASE_URL = 'https://api.opencnpj.org';
const OPENCNPJ_TIMEOUT_SECONDS = 10;
const OPENCNPJ_BATCH_LIMIT = 5;
const OPENCNPJ_MAX_REQUEST_BYTES = 8192;
const OPENCNPJ_MAX_RESPONSE_BYTES = 1048576;
const OPENCNPJ_GLOBAL_LIMIT_PER_MINUTE = 90;
const OPENCNPJ_CNPJ_LIMIT_PER_TEN_MINUTES = 6;
const OPENCNPJ_FORCE_LIMIT_PER_TEN_MINUTES = 3;

function normalizeCnpjDigits(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function isValidCnpjDigits(string $cnpj): bool
{
    if (preg_match('/^\d{14}$/', $cnpj) !== 1 || preg_match('/^(\d)\1{13}$/', $cnpj) === 1) {
        return false;
    }

    $digits = array_map('intval', str_split($cnpj));
    $calc = static function (array $base, array $weights): int {
        $sum = 0;
        foreach ($weights as $index => $weight) {
            $sum += $base[$index] * $weight;
        }
        $rest = $sum % 11;
        return $rest < 2 ? 0 : 11 - $rest;
    };

    $first = $calc($digits, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
    $second = $calc($digits, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

    return $digits[12] === $first && $digits[13] === $second;
}

function sanitizeOpenCnpjText(?string $value, int $limit = 500): ?string
{
    if ($value === null) {
        return null;
    }
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    return mb_substr($value, 0, $limit);
}

function sanitizeOpenCnpjPayload(mixed $value, int $depth = 0): mixed
{
    if ($depth > 12) {
        return null;
    }
    if (is_string($value)) {
        return sanitizeOpenCnpjText($value, 1000);
    }
    if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
        return $value;
    }
    if (!is_array($value)) {
        return null;
    }

    $result = [];
    $count = 0;
    foreach ($value as $key => $item) {
        if (++$count > 250) {
            break;
        }
        $safeKey = is_int($key) ? $key : sanitizeOpenCnpjText((string)$key, 80);
        if ($safeKey === null || $safeKey === '') {
            continue;
        }
        $result[$safeKey] = sanitizeOpenCnpjPayload($item, $depth + 1);
    }
    return $result;
}

function getNestedValue(array $data, array $paths): ?string
{
    foreach ($paths as $path) {
        $current = $data;
        foreach ($path as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                $current = null;
                break;
            }
            $current = $current[$key];
        }
        if (is_scalar($current)) {
            return sanitizeOpenCnpjText((string)$current, 500);
        }
    }
    return null;
}

function readJsonPayload(int $maxBytes = OPENCNPJ_MAX_REQUEST_BYTES): array
{
    $raw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
    if ($raw === false) {
        throw new InvalidArgumentException('Nao foi possivel ler o corpo da requisicao.');
    }
    if (strlen($raw) > $maxBytes) {
        http_response_code(413);
        throw new InvalidArgumentException('Corpo da requisicao excede o limite permitido.');
    }
    if (trim($raw) === '') {
        return [];
    }
    try {
        $payload = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new InvalidArgumentException('JSON invalido.');
    }
    if (!is_array($payload)) {
        throw new InvalidArgumentException('JSON deve ser um objeto.');
    }
    return $payload;
}

function assertOpenCnpjRateLimit(PDO $db, string $cnpj, bool $force): void
{
    $stmtGlobal = $db->prepare("
        SELECT COUNT(*)
          FROM cnpj_opencnpj_log
         WHERE criado_em >= (now() at time zone 'utc') - interval '1 minute'
    ");
    $stmtGlobal->execute();
    if ((int)$stmtGlobal->fetchColumn() >= OPENCNPJ_GLOBAL_LIMIT_PER_MINUTE) {
        http_response_code(429);
        throw new RuntimeException('Limite temporario de consultas OpenCNPJ atingido.');
    }

    $stmtCnpj = $db->prepare("
        SELECT COUNT(*)
          FROM cnpj_opencnpj_log
         WHERE cnpj_digits = :cnpj
           AND criado_em >= (now() at time zone 'utc') - interval '10 minutes'
    ");
    $stmtCnpj->execute(['cnpj' => $cnpj]);
    if ((int)$stmtCnpj->fetchColumn() >= OPENCNPJ_CNPJ_LIMIT_PER_TEN_MINUTES) {
        http_response_code(429);
        throw new RuntimeException('Limite temporario para este CNPJ atingido.');
    }

    if ($force) {
        $stmtForce = $db->prepare("
            SELECT COUNT(*)
              FROM cnpj_opencnpj_log
             WHERE cnpj_digits = :cnpj
               AND origem = 'cat_agregador_force'
               AND criado_em >= (now() at time zone 'utc') - interval '10 minutes'
        ");
        $stmtForce->execute(['cnpj' => $cnpj]);
        if ((int)$stmtForce->fetchColumn() >= OPENCNPJ_FORCE_LIMIT_PER_TEN_MINUTES) {
            http_response_code(429);
            throw new RuntimeException('Atualizacoes forcadas temporariamente limitadas para este CNPJ.');
        }
    }
}

function extractOpenCnpjSummary(?array $data): array
{
    if (!$data) {
        return [
            'razao_social' => null,
            'nome_fantasia' => null,
            'situacao' => null,
            'atividade_principal' => null,
            'municipio' => null,
            'uf' => null,
        ];
    }

    $atividade = getNestedValue($data, [
        ['atividade_principal', 'descricao'],
        ['atividade_principal'],
        ['cnae_fiscal_descricao'],
        ['cnae', 'descricao'],
    ]);

    $uf = getNestedValue($data, [
        ['uf'],
        ['endereco', 'uf'],
        ['estabelecimento', 'estado', 'sigla'],
        ['estabelecimento', 'uf'],
    ]);

    return [
        'razao_social' => getNestedValue($data, [
            ['razao_social'],
            ['nome_empresarial'],
            ['empresa', 'razao_social'],
        ]),
        'nome_fantasia' => getNestedValue($data, [
            ['nome_fantasia'],
            ['fantasia'],
            ['estabelecimento', 'nome_fantasia'],
        ]),
        'situacao' => getNestedValue($data, [
            ['situacao_cadastral'],
            ['situacao'],
            ['estabelecimento', 'situacao_cadastral'],
        ]),
        'atividade_principal' => $atividade,
        'municipio' => getNestedValue($data, [
            ['municipio'],
            ['cidade'],
            ['endereco', 'municipio'],
            ['estabelecimento', 'cidade', 'nome'],
        ]),
        'uf' => $uf ? mb_substr(strtoupper($uf), 0, 2) : null,
    ];
}

function openCnpjCacheRowToPayload(?array $row): ?array
{
    if (!$row) {
        return null;
    }

    return [
        'cnpj_digits' => $row['cnpj_digits'],
        'dataset' => $row['dataset'],
        'status_http' => isset($row['status_http']) ? (int)$row['status_http'] : null,
        'razao_social' => $row['razao_social'] ?? null,
        'nome_fantasia' => $row['nome_fantasia'] ?? null,
        'situacao' => $row['situacao'] ?? null,
        'atividade_principal' => $row['atividade_principal'] ?? null,
        'municipio' => $row['municipio'] ?? null,
        'uf' => $row['uf'] ?? null,
        'consultado_em' => $row['consultado_em'] ?? null,
        'expira_em' => $row['expira_em'] ?? null,
        'erro' => $row['erro'] ?? null,
        'is_fresh' => !empty($row['expira_em']) && strtotime((string)$row['expira_em']) > time(),
    ];
}

function getOpenCnpjCache(PDO $db, string $cnpj, string $dataset = OPENCNPJ_DATASET): ?array
{
    $stmt = $db->prepare("
        SELECT *
          FROM cnpj_cache_opencnpj
         WHERE cnpj_digits = :cnpj
           AND dataset = :dataset
    ");
    $stmt->execute(['cnpj' => $cnpj, 'dataset' => $dataset]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function saveOpenCnpjCache(PDO $db, string $cnpj, int $status, ?array $data, ?string $error, int $durationMs, bool $force = false, string $dataset = OPENCNPJ_DATASET): array
{
    $data = is_array($data) ? sanitizeOpenCnpjPayload($data) : null;
    $summary = extractOpenCnpjSummary($data);
    $ttl = $status === 200 ? '30 days' : ($status === 404 ? '7 days' : '2 hours');
    $json = $data ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) : null;
    if ($json !== null && strlen($json) > OPENCNPJ_MAX_RESPONSE_BYTES) {
        $json = null;
        $error = 'Resposta OpenCNPJ excedeu o tamanho permitido para cache.';
        $status = 0;
    }

    $stmt = $db->prepare("
        INSERT INTO cnpj_cache_opencnpj (
            cnpj_digits, dataset, status_http, dados_json, razao_social, nome_fantasia,
            situacao, atividade_principal, municipio, uf, consultado_em, expira_em,
            erro, tentativas, atualizado_em
        ) VALUES (
            :cnpj, :dataset, :status_http, CAST(:dados_json AS jsonb), :razao_social, :nome_fantasia,
            :situacao, :atividade_principal, :municipio, :uf, now() at time zone 'utc',
            (now() at time zone 'utc') + (:ttl)::interval, :erro, 1, now() at time zone 'utc'
        )
        ON CONFLICT (cnpj_digits, dataset) DO UPDATE SET
            status_http = EXCLUDED.status_http,
            dados_json = EXCLUDED.dados_json,
            razao_social = EXCLUDED.razao_social,
            nome_fantasia = EXCLUDED.nome_fantasia,
            situacao = EXCLUDED.situacao,
            atividade_principal = EXCLUDED.atividade_principal,
            municipio = EXCLUDED.municipio,
            uf = EXCLUDED.uf,
            consultado_em = EXCLUDED.consultado_em,
            expira_em = EXCLUDED.expira_em,
            erro = EXCLUDED.erro,
            tentativas = cnpj_cache_opencnpj.tentativas + 1,
            atualizado_em = EXCLUDED.atualizado_em
    ");
    $stmt->execute([
        'cnpj' => $cnpj,
        'dataset' => $dataset,
        'status_http' => $status,
        'dados_json' => $json,
        'razao_social' => $summary['razao_social'],
        'nome_fantasia' => $summary['nome_fantasia'],
        'situacao' => $summary['situacao'],
        'atividade_principal' => $summary['atividade_principal'],
        'municipio' => $summary['municipio'],
        'uf' => $summary['uf'],
        'ttl' => $ttl,
        'erro' => $error ? mb_substr($error, 0, 1000) : null,
    ]);

    $log = $db->prepare("
        INSERT INTO cnpj_opencnpj_log (cnpj_digits, dataset, status_http, duracao_ms, origem, erro)
        VALUES (:cnpj, :dataset, :status_http, :duracao_ms, :origem, :erro)
    ");
    $log->execute([
        'cnpj' => $cnpj,
        'dataset' => $dataset,
        'status_http' => $status,
        'duracao_ms' => $durationMs,
        'origem' => $force ? 'cat_agregador_force' : 'cat_agregador',
        'erro' => $error ? mb_substr($error, 0, 1000) : null,
    ]);

    return getOpenCnpjCache($db, $cnpj, $dataset) ?: [];
}

function fetchOpenCnpj(PDO $db, string $cnpj, bool $force = false, bool $allowStale = true): array
{
    $cnpj = normalizeCnpjDigits($cnpj);
    if (!isValidCnpjDigits($cnpj)) {
        throw new InvalidArgumentException('CNPJ invÃ¡lido para consulta OpenCNPJ.');
    }

    $cached = getOpenCnpjCache($db, $cnpj);
    if (!$force && $cached && !empty($cached['expira_em']) && strtotime((string)$cached['expira_em']) > time()) {
        $payload = openCnpjCacheRowToPayload($cached);
        $payload['source'] = 'cache';
        return $payload;
    }
    assertOpenCnpjRateLimit($db, $cnpj, $force);

    $url = OPENCNPJ_BASE_URL . '/' . rawurlencode($cnpj) . '?dataset=' . rawurlencode(OPENCNPJ_DATASET);
    $parts = parse_url($url);
    if (($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'api.opencnpj.org') {
        throw new RuntimeException('Destino OpenCNPJ nÃ£o permitido.');
    }

    $started = microtime(true);
    $status = 0;
    $data = null;
    $error = null;

    try {
        $ch = curl_init($url);
        if (!$ch) {
            throw new RuntimeException('Falha ao inicializar cliente HTTP.');
        }
        $bodyBuffer = '';
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => OPENCNPJ_TIMEOUT_SECONDS,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'quixotesca-cat/1.0',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_PROXY => '',
            CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$bodyBuffer): int {
                $bodyBuffer .= $chunk;
                if (strlen($bodyBuffer) > OPENCNPJ_MAX_RESPONSE_BYTES) {
                    return 0;
                }
                return strlen($chunk);
            },
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            if (strlen($bodyBuffer) > OPENCNPJ_MAX_RESPONSE_BYTES) {
                throw new RuntimeException('Resposta OpenCNPJ excedeu o limite permitido.');
            }
            throw new RuntimeException($curlError ?: 'Falha na chamada HTTP.');
        }
        $body = $bodyBuffer;
        if ($status === 200) {
            if (stripos($body, '<script') !== false || stripos($body, '<?php') !== false) {
                throw new RuntimeException('Resposta OpenCNPJ recusada por conteudo inesperado.');
            }
            $decoded = json_decode($body, true, 16);
            if (!is_array($decoded)) {
                throw new RuntimeException('Resposta OpenCNPJ nÃ£o Ã© JSON vÃ¡lido.');
            }
            $data = sanitizeOpenCnpjPayload($decoded);
        } elseif ($status === 404) {
            $error = 'CNPJ nÃ£o encontrado na OpenCNPJ.';
        } elseif ($status === 429) {
            $error = 'Limite temporÃ¡rio da OpenCNPJ atingido.';
        } else {
            $error = 'OpenCNPJ retornou HTTP ' . $status . '.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    $durationMs = (int)round((microtime(true) - $started) * 1000);
    $saved = saveOpenCnpjCache($db, $cnpj, $status, $data, $error, $durationMs, $force);
    $payload = openCnpjCacheRowToPayload($saved);
    $payload['source'] = 'api';

    if ($error && $cached && $allowStale) {
        $payload = openCnpjCacheRowToPayload($cached);
        $payload['source'] = 'stale-cache';
        $payload['erro'] = $error;
    }

    return $payload;
}
