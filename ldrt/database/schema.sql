-- Create schema if not exists
CREATE SCHEMA IF NOT EXISTS ldrt;
SET search_path TO ldrt, public;

-- Drop existing views and tables if they exist (for a clean reinstall)
DROP VIEW IF EXISTS v_rag_chunks;
DROP TABLE IF EXISTS relatos;
DROP TABLE IF EXISTS agente_cid;
DROP TABLE IF EXISTS agentes;
DROP TABLE IF EXISTS cnae_cbo;
DROP TABLE IF EXISTS cid;

-- 1. CID Table (without self-referencing foreign key initially)
CREATE TABLE cid (
    id SERIAL PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL,
    nivel VARCHAR(50) NOT NULL,
    descricao TEXT NOT NULL,
    parent_id INT,
    CONSTRAINT unique_cid_codigo UNIQUE (codigo)
);

-- 2. CNAE & CBO Table (without self-referencing foreign key initially)
CREATE TABLE cnae_cbo (
    id SERIAL PRIMARY KEY,
    classificacao VARCHAR(10) NOT NULL CHECK (classificacao IN ('cnae', 'cbo')),
    codigo VARCHAR(20) NOT NULL,
    descricao TEXT NOT NULL,
    nivel VARCHAR(50) NOT NULL,
    parent_id INT,
    CONSTRAINT unique_cnae_cbo UNIQUE (classificacao, codigo)
);

-- 3. Agentes de Risco Table (without self-referencing foreign key initially)
CREATE TABLE agentes (
    id SERIAL PRIMARY KEY,
    descricao TEXT NOT NULL,
    cas VARCHAR(50),
    parent_id INT,
    old_id VARCHAR(50) NOT NULL,
    CONSTRAINT unique_agente_old_id UNIQUE (old_id)
);

-- 4. Agente - CID Junction Table (Many-to-Many)
CREATE TABLE agente_cid (
    agente_id INT NOT NULL,
    cid_id INT NOT NULL,
    PRIMARY KEY (agente_id, cid_id),
    CONSTRAINT fk_agente_cid_agente FOREIGN KEY (agente_id) REFERENCES agentes(id) ON DELETE CASCADE,
    CONSTRAINT fk_agente_cid_cid FOREIGN KEY (cid_id) REFERENCES cid(id) ON DELETE CASCADE
);

-- 5. Relatos (Case Reports) Table
CREATE TABLE relatos (
    id SERIAL PRIMARY KEY,
    cnae_cbo_id INT,
    agente_id INT,
    cid_id INT,
    titulo VARCHAR(255) NOT NULL,
    relato TEXT NOT NULL,
    old_id VARCHAR(50) NOT NULL,
    CONSTRAINT unique_relato_old_id UNIQUE (old_id),
    CONSTRAINT fk_relatos_cnae_cbo FOREIGN KEY (cnae_cbo_id) REFERENCES cnae_cbo(id) ON DELETE SET NULL,
    CONSTRAINT fk_relatos_agente FOREIGN KEY (agente_id) REFERENCES agentes(id) ON DELETE SET NULL,
    CONSTRAINT fk_relatos_cid FOREIGN KEY (cid_id) REFERENCES cid(id) ON DELETE SET NULL
);

-- Create Full-Text Search Indexes
CREATE INDEX idx_cid_search ON cid USING gin(to_tsvector('portuguese', codigo || ' ' || descricao));
CREATE INDEX idx_cnae_cbo_search ON cnae_cbo USING gin(to_tsvector('portuguese', codigo || ' ' || descricao));
CREATE INDEX idx_agentes_search ON agentes USING gin(to_tsvector('portuguese', descricao));
CREATE INDEX idx_relatos_search ON relatos USING gin(to_tsvector('portuguese', titulo || ' ' || relato));

-- (Self-referencing foreign keys and RAG view will be added by the loader after data is inserted)
