<?php

declare(strict_types=1);

use Carex\Database\Connection;

$config = require dirname(__DIR__) . '/src/bootstrap.php';

try {
    echo "Iniciando migração da tabela de usuários...\n";

    // Enable DB writes dynamically if not already enabled for CLI run
    $config['database']['allow_writes'] = true;
    
    $pdo = Connection::make($config['database']);

    // Check if table users already exists
    $stmt = $pdo->prepare("
        SELECT EXISTS (
            SELECT FROM pg_tables 
            WHERE schemaname = :schema 
              AND tablename  = 'users'
        )
    ");
    $stmt->execute(['schema' => $config['database']['schema']]);
    $tableExists = (bool) $stmt->fetchColumn();

    if ($tableExists) {
        echo "A tabela 'users' já existe no schema '{$config['database']['schema']}'. Nenhuma ação necessária.\n";
        exit(0);
    }

    $pdo->beginTransaction();

    $ddl = "
        CREATE TABLE users (
            id SERIAL PRIMARY KEY,
            google_id VARCHAR(255) UNIQUE NOT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            profile_picture VARCHAR(1000),
            role VARCHAR(50) NOT NULL DEFAULT 'usuario',
            status VARCHAR(50) NOT NULL DEFAULT 'ativo',
            remember_token TEXT,
            created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX idx_users_google_id ON users(google_id);
        CREATE INDEX idx_users_email ON users(email);

        COMMENT ON TABLE users IS 'Tabela de usuários autenticados via login do Google OAuth 2.0.';
        COMMENT ON COLUMN users.google_id IS 'ID único retornado pelo Google (sub claim).';
        COMMENT ON COLUMN users.role IS 'Nível de acesso do usuário: usuario, especialista ou admin.';
        COMMENT ON COLUMN users.status IS 'Situação cadastral do usuário: ativo ou desligado.';
        COMMENT ON COLUMN users.remember_token IS 'Hash seguro para validar o \"Manter-me conectado\" entre sessões.';
    ";

    $pdo->exec($ddl);
    $pdo->commit();

    echo "Tabela 'users' criada com sucesso com todos os índices e comentários!\n";

} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Erro na migração: " . $exception->getMessage() . "\n";
    exit(1);
}
