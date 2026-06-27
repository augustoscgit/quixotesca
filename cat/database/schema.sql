-- Create schema if not exists
CREATE SCHEMA IF NOT EXISTS cat;
SET search_path TO cat, public;

-- 1. Import Files Table
CREATE TABLE IF NOT EXISTS arquivos_importacao (
    id SERIAL PRIMARY KEY,
    recurso_id VARCHAR(100) UNIQUE NOT NULL,
    nome VARCHAR(255) NOT NULL,
    url_download TEXT NOT NULL,
    situacao_extracao VARCHAR(20) NOT NULL DEFAULT 'Pendente' CHECK (situacao_extracao IN ('Pendente', 'Extraído', 'Falhou')),
    situacao_carga VARCHAR(20) NOT NULL DEFAULT 'Pendente' CHECK (situacao_carga IN ('Pendente', 'Carregando', 'Carregado', 'Falhou')),
    linhas_processadas INTEGER DEFAULT 0,
    total_registros_documentados INTEGER DEFAULT 0,
    total_campos_documentados INTEGER DEFAULT 0,
    documentacao_atualizada_em TIMESTAMP WITHOUT TIME ZONE,
    mensagem_erro TEXT,
    ultima_execucao TIMESTAMP WITHOUT TIME ZONE
);

-- 2. Raw JSONB Records Table
CREATE TABLE IF NOT EXISTS registros_brutos (
    id BIGSERIAL PRIMARY KEY,
    arquivo_importacao_id INTEGER NOT NULL,
    numero_linha_arquivo INTEGER NOT NULL,
    registro_origem_id VARCHAR(80) NOT NULL,
    hash_extended VARCHAR(64) NOT NULL,
    dados JSONB NOT NULL,
    criado_em TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc'),
    CONSTRAINT fk_registros_brutos_arquivo FOREIGN KEY (arquivo_importacao_id) REFERENCES arquivos_importacao(id) ON DELETE CASCADE
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_registros_brutos_arquivo_id ON registros_brutos(arquivo_importacao_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_registros_brutos_origem_id ON registros_brutos(registro_origem_id);
CREATE INDEX IF NOT EXISTS idx_registros_brutos_linha_arquivo ON registros_brutos(arquivo_importacao_id, numero_linha_arquivo);
CREATE INDEX IF NOT EXISTS idx_registros_brutos_hash_extended ON registros_brutos(hash_extended);
CREATE INDEX IF NOT EXISTS idx_registros_brutos_cnpj_empregador_digits ON registros_brutos (
    (regexp_replace(COALESCE(dados->>'cnpj_cei_empregador', ''), '\D', '', 'g'))
);
-- Index for querying data attributes inside JSONB
CREATE INDEX IF NOT EXISTS idx_registros_brutos_dados ON registros_brutos USING gin(dados);
-- Immutable parser function for date index
CREATE OR REPLACE FUNCTION parse_date_immutable(val text) RETURNS date AS $$
BEGIN
    IF val ~ '^[0-3][0-9]/[0-1][0-9]/[0-9]{4}$' THEN
        RETURN to_date(val, 'DD/MM/YYYY');
    ELSE
        RETURN NULL;
    END IF;
EXCEPTION WHEN OTHERS THEN
    RETURN NULL;
END;
$$ LANGUAGE plpgsql IMMUTABLE;

-- Index for parsed accident date queries
CREATE INDEX IF NOT EXISTS idx_registros_brutos_data_acidente_parsed ON registros_brutos (
    (parse_date_immutable(dados->>'data_acidente'))
);

-- 3. Field documentation per import file
CREATE TABLE IF NOT EXISTS campos_arquivo (
    arquivo_importacao_id INTEGER NOT NULL,
    campo VARCHAR(255) NOT NULL,
    ocorrencias INTEGER NOT NULL DEFAULT 0,
    preenchidos INTEGER NOT NULL DEFAULT 0,
    total_registros INTEGER NOT NULL DEFAULT 0,
    formatos_data JSONB,
    exemplos JSONB,
    atualizado_em TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc'),
    PRIMARY KEY (arquivo_importacao_id, campo),
    CONSTRAINT fk_campos_arquivo_arquivo FOREIGN KEY (arquivo_importacao_id) REFERENCES arquivos_importacao(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_campos_arquivo_campo ON campos_arquivo(campo);

-- 4. Execution Logs Table
CREATE TABLE IF NOT EXISTS logs_execucao (
    id BIGSERIAL PRIMARY KEY,
    arquivo_importacao_id INTEGER NOT NULL,
    nivel VARCHAR(10) NOT NULL, -- 'info', 'success', 'warn', 'error'
    mensagem TEXT NOT NULL,
    criado_em TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc'),
    CONSTRAINT fk_logs_execucao_arquivo FOREIGN KEY (arquivo_importacao_id) REFERENCES arquivos_importacao(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_logs_execucao_arquivo ON logs_execucao(arquivo_importacao_id);

-- 5. Aggregated CNPJ base for employer navigation
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
CREATE INDEX IF NOT EXISTS idx_cnpj_agregados_matriz ON cnpj_agregados(matriz);
CREATE INDEX IF NOT EXISTS idx_cnpj_agregados_filial ON cnpj_agregados(filial);
CREATE INDEX IF NOT EXISTS idx_cnpj_agregados_acidentes ON cnpj_agregados(acidentes DESC, ultima_ocorrencia DESC);

-- 6. OpenCNPJ cache and call audit
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
CREATE INDEX IF NOT EXISTS idx_cnpj_cache_opencnpj_expira ON cnpj_cache_opencnpj(expira_em);
CREATE INDEX IF NOT EXISTS idx_cnpj_cache_opencnpj_status ON cnpj_cache_opencnpj(status_http);

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
CREATE INDEX IF NOT EXISTS idx_cnpj_opencnpj_log_criado ON cnpj_opencnpj_log(criado_em);
CREATE INDEX IF NOT EXISTS idx_cnpj_opencnpj_log_cnpj ON cnpj_opencnpj_log(cnpj_digits);
