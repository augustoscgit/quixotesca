<?php
/**
 * Database connection helper using PDO and .env credentials
 * Automatically runs migrations if database tables are missing.
 */

function getDBConnection(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $envPath = __DIR__ . '/../secrets/.env';
    // Fallback to platform secrets if module secrets doesn't exist
    if (!is_file($envPath)) {
        $envPath = __DIR__ . '/../../secrets/.env';
    }

    if (!is_file($envPath) || !is_readable($envPath)) {
        throw new Exception("Configuration file (.env) not found at: " . $envPath);
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        throw new Exception("Configuration file (.env) could not be read.");
    }

    $env = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim(trim($value), "\"'");
        if ($name !== '') {
            $env[$name] = $value;
        }
    }

    $host = $env['DB_HOST'] ?? '';
    $port = $env['DB_PORT'] ?? '5432';
    $db   = $env['DB_DATABASE'] ?? '';
    $user = $env['DB_USERNAME'] ?? '';
    $pass = $env['DB_PASSWORD'] ?? '';
    $schema = $env['DB_SCHEMA'] ?? 'cat';
    $sslmode = $env['DB_SSLMODE'] ?? '';

    foreach (['DB_HOST' => $host, 'DB_DATABASE' => $db, 'DB_USERNAME' => $user, 'DB_PASSWORD' => $pass] as $key => $value) {
        if ($value === '') {
            throw new Exception("$key not configured.");
        }
    }

    $dsn = "pgsql:host=$host;port=$port;dbname=$db;connect_timeout=5";
    if ($sslmode !== '') {
        $dsn .= ";sslmode=$sslmode";
    }
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        $pdo->exec("SET client_encoding TO 'UTF8'");
        $pdo->exec("SET lock_timeout TO '5s'");
        $quotedSchema = '"' . str_replace('"', '""', $schema) . '"';
        $pdo->exec("SET search_path TO $quotedSchema, public");
        
        $stmt = $pdo->prepare("
            SELECT EXISTS (
                SELECT 1
                  FROM information_schema.tables
                 WHERE table_schema = :schema
                   AND table_name = 'arquivos_importacao'
            )
        ");
        $stmt->execute(['schema' => $schema]);
        $baseTableExists = filter_var($stmt->fetchColumn(), FILTER_VALIDATE_BOOLEAN);

        if (!$baseTableExists) {
            runMigration($pdo);
        }

        ensureRuntimeSchema($pdo);

        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}

function ensureRuntimeSchema(PDO $pdo): void
{
    $lockAcquired = false;
    try {
        $lockAcquired = filter_var(
            $pdo->query("SELECT pg_try_advisory_lock(hashtext('cat_runtime_schema'))")->fetchColumn(),
            FILTER_VALIDATE_BOOLEAN
        );
        if (!$lockAcquired) {
            return;
        }

        $pdo->exec("
            ALTER TABLE arquivos_importacao
                ADD COLUMN IF NOT EXISTS total_registros_documentados INTEGER DEFAULT 0,
                ADD COLUMN IF NOT EXISTS total_campos_documentados INTEGER DEFAULT 0,
                ADD COLUMN IF NOT EXISTS documentacao_atualizada_em TIMESTAMP WITHOUT TIME ZONE;

            ALTER TABLE registros_brutos
                ADD COLUMN IF NOT EXISTS numero_linha_arquivo INTEGER,
                ADD COLUMN IF NOT EXISTS registro_origem_id VARCHAR(80),
                ADD COLUMN IF NOT EXISTS hash_extended VARCHAR(64);

            UPDATE registros_brutos
               SET numero_linha_arquivo = COALESCE(numero_linha_arquivo, id::integer),
                   registro_origem_id = COALESCE(registro_origem_id, arquivo_importacao_id::text || '-' || id::text),
                   hash_extended = COALESCE(hash_extended, md5(dados::text))
             WHERE numero_linha_arquivo IS NULL
                OR registro_origem_id IS NULL
                OR hash_extended IS NULL;

            ALTER TABLE registros_brutos
                ALTER COLUMN numero_linha_arquivo SET NOT NULL,
                ALTER COLUMN registro_origem_id SET NOT NULL,
                ALTER COLUMN hash_extended SET NOT NULL;

            CREATE TABLE IF NOT EXISTS logs_execucao (
                id BIGSERIAL PRIMARY KEY,
                arquivo_importacao_id INTEGER NOT NULL REFERENCES arquivos_importacao(id) ON DELETE CASCADE,
                nivel VARCHAR(10) NOT NULL,
                mensagem TEXT NOT NULL,
                criado_em TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc')
            );

            CREATE TABLE IF NOT EXISTS campos_arquivo (
                arquivo_importacao_id INTEGER NOT NULL REFERENCES arquivos_importacao(id) ON DELETE CASCADE,
                campo VARCHAR(255) NOT NULL,
                ocorrencias INTEGER NOT NULL DEFAULT 0,
                preenchidos INTEGER NOT NULL DEFAULT 0,
                total_registros INTEGER NOT NULL DEFAULT 0,
                formatos_data JSONB,
                exemplos JSONB,
                atualizado_em TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc'),
                PRIMARY KEY (arquivo_importacao_id, campo)
            );

            CREATE TABLE IF NOT EXISTS cnpj_agregados (
                cnpj_digits VARCHAR(14) PRIMARY KEY,
                matriz VARCHAR(8) NOT NULL,
                filial VARCHAR(4) NOT NULL,
                tipo_empregador TEXT,
                cnae_codigo TEXT,
                cnae_descricao TEXT,
                municipio_empregador TEXT,
                uf_empregador TEXT,
                acidentes INTEGER NOT NULL DEFAULT 0,
                obitos INTEGER NOT NULL DEFAULT 0,
                primeira_ocorrencia DATE,
                ultima_ocorrencia DATE,
                atualizado_em TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc')
            );

            CREATE TABLE IF NOT EXISTS cnpj_cache_opencnpj (
                cnpj_digits VARCHAR(14) NOT NULL,
                dataset VARCHAR(20) NOT NULL DEFAULT 'receita',
                status_http INTEGER,
                dados_json JSONB,
                razao_social TEXT,
                nome_fantasia TEXT,
                situacao TEXT,
                atividade_principal TEXT,
                municipio TEXT,
                uf VARCHAR(2),
                consultado_em TIMESTAMP WITHOUT TIME ZONE,
                expira_em TIMESTAMP WITHOUT TIME ZONE,
                erro TEXT,
                tentativas INTEGER NOT NULL DEFAULT 0,
                atualizado_em TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc'),
                PRIMARY KEY (cnpj_digits, dataset)
            );

            CREATE TABLE IF NOT EXISTS cnpj_opencnpj_log (
                id BIGSERIAL PRIMARY KEY,
                cnpj_digits VARCHAR(14) NOT NULL,
                dataset VARCHAR(20) NOT NULL DEFAULT 'receita',
                status_http INTEGER,
                duracao_ms INTEGER,
                origem VARCHAR(40) NOT NULL DEFAULT 'cat',
                erro TEXT,
                criado_em TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc')
            );
        ");

        $functionExists = (bool)$pdo
            ->query("SELECT EXISTS (SELECT 1 FROM pg_proc WHERE proname = 'parse_date_immutable')")
            ->fetchColumn();

        if (!$functionExists) {
            $pdo->exec("
                CREATE FUNCTION parse_date_immutable(val text) RETURNS date AS $$
            DECLARE
                parsed_date date;
            BEGIN
                IF val ~ '^[0-3][0-9]/[0-1][0-9]/[0-9]{4}$' THEN
                    parsed_date := to_date(val, 'DD/MM/YYYY');
                    -- Verify that the formatted string matches the original to catch overflow dates
                    IF to_char(parsed_date, 'DD/MM/YYYY') = val THEN
                        RETURN parsed_date;
                    END IF;
                END IF;
                RETURN NULL;
            EXCEPTION WHEN OTHERS THEN
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql IMMUTABLE;
            ");
        }

        $pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_logs_execucao_arquivo ON logs_execucao(arquivo_importacao_id);
            CREATE UNIQUE INDEX IF NOT EXISTS idx_registros_brutos_origem_id ON registros_brutos(registro_origem_id);
            CREATE INDEX IF NOT EXISTS idx_registros_brutos_linha_arquivo ON registros_brutos(arquivo_importacao_id, numero_linha_arquivo);
            CREATE INDEX IF NOT EXISTS idx_registros_brutos_hash_extended ON registros_brutos(hash_extended);
            CREATE INDEX IF NOT EXISTS idx_registros_brutos_cnpj_empregador_digits ON registros_brutos (
                (regexp_replace(COALESCE(dados->>'cnpj_cei_empregador', ''), '\\D', '', 'g'))
            );
            CREATE INDEX IF NOT EXISTS idx_registros_brutos_data_acidente_parsed ON registros_brutos (
                (parse_date_immutable(dados->>'data_acidente'))
            );
            CREATE INDEX IF NOT EXISTS idx_campos_arquivo_campo ON campos_arquivo(campo);
            CREATE INDEX IF NOT EXISTS idx_cnpj_agregados_matriz ON cnpj_agregados(matriz);
            CREATE INDEX IF NOT EXISTS idx_cnpj_agregados_filial ON cnpj_agregados(filial);
            CREATE INDEX IF NOT EXISTS idx_cnpj_agregados_acidentes ON cnpj_agregados(acidentes DESC, ultima_ocorrencia DESC);
            CREATE INDEX IF NOT EXISTS idx_cnpj_cache_opencnpj_expira ON cnpj_cache_opencnpj(expira_em);
            CREATE INDEX IF NOT EXISTS idx_cnpj_cache_opencnpj_status ON cnpj_cache_opencnpj(status_http);
            CREATE INDEX IF NOT EXISTS idx_cnpj_opencnpj_log_criado ON cnpj_opencnpj_log(criado_em);
            CREATE INDEX IF NOT EXISTS idx_cnpj_opencnpj_log_cnpj ON cnpj_opencnpj_log(cnpj_digits);
        ");
        $pdo->query("SELECT pg_advisory_unlock(hashtext('cat_runtime_schema'))");
    } catch (PDOException $e) {
        if ($lockAcquired) {
            try {
                $pdo->query("SELECT pg_advisory_unlock(hashtext('cat_runtime_schema'))");
            } catch (PDOException $unlockException) {
                // Keep the original schema error visible to the caller.
            }
        }
        throw new Exception("Database runtime schema update failed: " . $e->getMessage());
    }
}

function refreshCnpjAggregates(PDO $pdo): int
{
    $pdo->beginTransaction();
    try {
        $pdo->exec("TRUNCATE TABLE cnpj_agregados");
        $inserted = $pdo->exec("
            INSERT INTO cnpj_agregados (
                cnpj_digits, matriz, filial, tipo_empregador, cnae_codigo, cnae_descricao,
                municipio_empregador, uf_empregador, acidentes, obitos, primeira_ocorrencia,
                ultima_ocorrencia, atualizado_em
            )
            WITH base AS (
                SELECT dados,
                       regexp_replace(COALESCE(dados->>'cnpj_cei_empregador', ''), '\\D', '', 'g') AS cnpj_digits,
                       parse_date_immutable(dados->>'data_acidente') AS data_acidente
                  FROM registros_brutos
            )
            SELECT cnpj_digits,
                   substring(cnpj_digits from 1 for 8) AS matriz,
                   substring(cnpj_digits from 9 for 4) AS filial,
                   MAX(NULLIF(dados->>'tipo_de_empregador', '')) AS tipo_empregador,
                   MAX(NULLIF(dados->>'cnae2_0_empregador', '')) AS cnae_codigo,
                   MAX(NULLIF(dados->>'cnae2_0_empregador_1', '')) AS cnae_descricao,
                   MAX(NULLIF(dados->>'munic_empr', '')) AS municipio_empregador,
                   MAX(NULLIF(dados->>'uf_munic_empregador', '')) AS uf_empregador,
                   COUNT(*)::integer AS acidentes,
                   COUNT(*) FILTER (WHERE COALESCE(dados->>'indica_obito_acidente', dados->>'indica_bito_acidente') = 'Sim')::integer AS obitos,
                   MIN(data_acidente) AS primeira_ocorrencia,
                   MAX(data_acidente) AS ultima_ocorrencia,
                   now() at time zone 'utc'
              FROM base
             WHERE length(cnpj_digits) = 14
               AND cnpj_digits <> '00000000000000'
             GROUP BY cnpj_digits
        ");
        $pdo->commit();
        return (int)$inserted;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function runMigration(PDO $pdo): void
{
    $schemaSqlPath = __DIR__ . '/../database/schema.sql';
    if (!is_file($schemaSqlPath) || !is_readable($schemaSqlPath)) {
        throw new Exception("Database schema file not found at: " . $schemaSqlPath);
    }

    $sql = file_get_contents($schemaSqlPath);
    if ($sql === false) {
        throw new Exception("Could not read database schema file.");
    }

    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        throw new Exception("Database migration failed: " . $e->getMessage());
    }
}
// stray PHP tag removed
function auditDataAcidente(PDO $db, ?int $arquivoId = null): array {
    // Build base where clause
    $where = '';
    $params = [];
    if ($arquivoId !== null) {
        $where = 'WHERE arquivo_importacao_id = :arquivo_id';
        $params['arquivo_id'] = $arquivoId;
    }

    // Total records (optional filter)
    $totalQuery = "SELECT COUNT(*) FROM registros_brutos " . $where;
    $stmt = $db->prepare($totalQuery);
    if ($arquivoId !== null) $stmt->bindValue('arquivo_id', $arquivoId, PDO::PARAM_INT);
    $stmt->execute();
    $total = (int)$stmt->fetchColumn();

    // Valid dates count (matches DD/MM/YYYY)
    $validWhere = $where . ($where ? ' AND ' : 'WHERE ') . "dados->>'data_acidente' ~ '^[0-3][0-9]/[0-1][0-9]/[0-9]{4}$'";
    $validStmt = $db->prepare("SELECT COUNT(*) FROM registros_brutos $validWhere");
    if ($arquivoId !== null) $validStmt->bindValue('arquivo_id', $arquivoId, PDO::PARAM_INT);
    $validStmt->execute();
    $valid = (int)$validStmt->fetchColumn();

    $invalid = $total - $valid;

    // Sample of invalid dates (up to 20 examples)
    $invalidWhere = $where . ($where ? ' AND ' : 'WHERE ') . "NOT (dados->>'data_acidente' ~ '^[0-3][0-9]/[0-1][0-9]/[0-9]{4}$')";
    $sampleStmt = $db->prepare("SELECT dados->>'data_acidente' AS d FROM registros_brutos $invalidWhere LIMIT 20");
    if ($arquivoId !== null) $sampleStmt->bindValue('arquivo_id', $arquivoId, PDO::PARAM_INT);
    $sampleStmt->execute();
    $sampleInvalid = $sampleStmt->fetchAll(PDO::FETCH_COLUMN);

    return [
        'total_records' => $total,
        'valid_dates'   => $valid,
        'invalid_dates' => $invalid,
        'sample_invalid_dates' => $sampleInvalid,
    ];
}
?>
