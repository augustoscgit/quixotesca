<?php

declare(strict_types=1);

load_env(__DIR__ . '/../secrets/.env');
load_env(__DIR__ . '/../.env');

if (app_debug_enabled()) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    configure_session();
    session_start();
}

function load_env(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim(trim($value), "\"'");

        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

function env_value(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

function env_bool(string $key, bool $default = false): bool
{
    $value = env_value($key);
    if ($value === null) {
        return $default;
    }

    return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
}

function env_int(string $key, int $default): int
{
    $value = env_value($key);
    if ($value === null || trim($value) === '' || !is_numeric($value)) {
        return $default;
    }

    return (int) $value;
}

function app_debug_enabled(): bool
{
    return env_bool('APP_DEBUG', false);
}

function configure_session(): void
{
    $sessionDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'sessions';
    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0775, true);
    }

    if (is_dir($sessionDir) && is_writable($sessionDir)) {
        session_save_path($sessionDir);
    }

    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    );

    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '14400');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env_value('DB_HOST', '');
    $port = env_value('DB_PORT', '5432');
    $database = env_value('DB_DATABASE', '');
    $username = env_value('DB_USERNAME', '');
    $password = env_value('DB_PASSWORD', '');
    $sslmode = env_value('DB_SSLMODE', '');

    foreach (['DB_HOST' => $host, 'DB_DATABASE' => $database, 'DB_USERNAME' => $username, 'DB_PASSWORD' => $password] as $key => $value) {
        if ((string) $value === '') {
            throw new RuntimeException($key . ' nao configurado.');
        }
    }

    $dsn = "pgsql:host=$host;port=$port;dbname=$database";
    if ($sslmode !== '') {
        $dsn .= ";sslmode=$sslmode";
    }

    $pdo = new PDO($dsn, (string) $username, (string) $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec("SET client_encoding TO 'UTF8'");
    ensure_schema_ready($pdo);

    return $pdo;
}

function schema_name(): string
{
    return env_value('DB_SCHEMA', 'acesso') ?: 'acesso';
}

function qi(string $identifier): string
{
    return '"' . str_replace('"', '""', $identifier) . '"';
}

function table_name(string $table): string
{
    return qi(schema_name()) . '.' . qi($table);
}

function ensure_schema_ready(PDO $pdo): void
{
    $schema = qi(schema_name());
    $pdo->exec("CREATE SCHEMA IF NOT EXISTS $schema");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS $schema.schema_versions (
            name TEXT PRIMARY KEY,
            version INTEGER NOT NULL,
            applied_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc')
        )
    ");

    $stmt = $pdo->prepare('SELECT version FROM ' . table_name('schema_versions') . ' WHERE name = :name');
    $stmt->execute(['name' => 'acesso']);
    $version = (int) ($stmt->fetchColumn() ?: 0);

    if ($version >= 1) {
        seed_initial_data($pdo);
        return;
    }

    migrate($pdo);
    seed_initial_data($pdo);

    $mark = $pdo->prepare('
        INSERT INTO ' . table_name('schema_versions') . " (name, version, applied_at)
        VALUES ('acesso', 1, (now() at time zone 'utc'))
        ON CONFLICT (name) DO UPDATE
            SET version = EXCLUDED.version,
                applied_at = EXCLUDED.applied_at
    ");
    $mark->execute();
}

function migrate(PDO $pdo): void
{
    $schema = qi(schema_name());

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS $schema.users (
            id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            username TEXT,
            password_hash TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
            email_verified_at TIMESTAMP WITHOUT TIME ZONE,
            must_change_password BOOLEAN NOT NULL DEFAULT false,
            last_login_at TIMESTAMP WITHOUT TIME ZONE,
            created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc'),
            updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc')
        )
    ");

    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS acesso_users_email_unique ON $schema.users (lower(email))");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS acesso_users_username_unique ON $schema.users (lower(username)) WHERE username IS NOT NULL AND username <> ''");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS $schema.apps (
            id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
            slug TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc')
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS $schema.roles (
            id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
            app_slug TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT '',
            created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc')
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS $schema.permissions (
            id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
            app_slug TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT '',
            created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc')
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS $schema.user_roles (
            user_id BIGINT NOT NULL REFERENCES $schema.users(id) ON DELETE CASCADE,
            role_id BIGINT NOT NULL REFERENCES $schema.roles(id) ON DELETE CASCADE,
            created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc'),
            PRIMARY KEY (user_id, role_id)
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS $schema.role_permissions (
            role_id BIGINT NOT NULL REFERENCES $schema.roles(id) ON DELETE CASCADE,
            permission_id BIGINT NOT NULL REFERENCES $schema.permissions(id) ON DELETE CASCADE,
            created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc'),
            PRIMARY KEY (role_id, permission_id)
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS $schema.password_resets (
            id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES $schema.users(id) ON DELETE CASCADE,
            token_hash TEXT NOT NULL,
            expires_at TIMESTAMP WITHOUT TIME ZONE NOT NULL,
            used_at TIMESTAMP WITHOUT TIME ZONE,
            created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc')
        )
    ");

    $pdo->exec("CREATE INDEX IF NOT EXISTS acesso_password_resets_token_hash_idx ON $schema.password_resets (token_hash)");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS $schema.email_verifications (
            id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES $schema.users(id) ON DELETE CASCADE,
            token_hash TEXT NOT NULL,
            expires_at TIMESTAMP WITHOUT TIME ZONE NOT NULL,
            used_at TIMESTAMP WITHOUT TIME ZONE,
            created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc')
        )
    ");

    $pdo->exec("CREATE INDEX IF NOT EXISTS acesso_email_verifications_token_hash_idx ON $schema.email_verifications (token_hash)");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS $schema.login_attempts (
            id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
            login TEXT NOT NULL,
            ip_address TEXT NOT NULL DEFAULT '',
            successful BOOLEAN NOT NULL,
            created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc')
        )
    ");
}

function seed_initial_data(PDO $pdo): void
{
    seed_apps($pdo);
    seed_permissions($pdo);
    seed_roles($pdo);
    seed_role_permissions($pdo);
    seed_admin_user($pdo);
}

function seed_apps(PDO $pdo): void
{
    $apps = [
        ['acesso', 'Acesso'],
        ['fichario', 'Fichario Academico'],
        ['ldrt', 'LDRT'],
        ['carex', 'CAREX-BR'],
    ];

    $stmt = $pdo->prepare('INSERT INTO ' . table_name('apps') . ' (slug, name) VALUES (:slug, :name) ON CONFLICT (slug) DO UPDATE SET name = EXCLUDED.name');
    foreach ($apps as [$slug, $name]) {
        $stmt->execute(['slug' => $slug, 'name' => $name]);
    }
}

function seed_permissions(PDO $pdo): void
{
    $permissions = [
        ['platform', 'platform.admin', 'Administrar plataforma'],
        ['platform', 'content.edit', 'Editar conteudo'],
        ['acesso', 'acesso.admin', 'Administrar acesso'],
        ['acesso', 'acesso.users.read', 'Ler usuarios'],
        ['acesso', 'acesso.users.create', 'Criar usuarios'],
        ['acesso', 'acesso.users.update', 'Atualizar usuarios'],
        ['acesso', 'acesso.users.permissions', 'Gerenciar papeis de usuarios'],
        ['fichario', 'fichario.access', 'Acessar Fichario'],
        ['fichario', 'fichario.admin', 'Administrar Fichario'],
        ['ldrt', 'ldrt.access', 'Acessar LDRT'],
        ['ldrt', 'ldrt.admin', 'Administrar LDRT'],
        ['carex', 'carex.access', 'Acessar CAREX'],
        ['carex', 'carex.admin', 'Administrar CAREX'],
    ];

    $stmt = $pdo->prepare('INSERT INTO ' . table_name('permissions') . ' (app_slug, slug, name) VALUES (:app_slug, :slug, :name) ON CONFLICT (slug) DO UPDATE SET app_slug = EXCLUDED.app_slug, name = EXCLUDED.name');
    foreach ($permissions as [$app, $slug, $name]) {
        $stmt->execute(['app_slug' => $app, 'slug' => $slug, 'name' => $name]);
    }
}

function seed_roles(PDO $pdo): void
{
    $roles = [
        ['platform', 'admin', 'Administrador'],
        ['platform', 'user', 'Usuario editor'],
        ['acesso', 'acesso.admin', 'Administrador do Acesso'],
        ['fichario', 'fichario.reader', 'Leitor do Fichario'],
        ['fichario', 'fichario.admin', 'Administrador do Fichario'],
        ['ldrt', 'ldrt.reader', 'Leitor da LDRT'],
        ['ldrt', 'ldrt.admin', 'Administrador da LDRT'],
        ['carex', 'carex.reader', 'Leitor do CAREX'],
        ['carex', 'carex.admin', 'Administrador do CAREX'],
    ];

    $stmt = $pdo->prepare('INSERT INTO ' . table_name('roles') . ' (app_slug, slug, name) VALUES (:app_slug, :slug, :name) ON CONFLICT (slug) DO UPDATE SET app_slug = EXCLUDED.app_slug, name = EXCLUDED.name');
    foreach ($roles as [$app, $slug, $name]) {
        $stmt->execute(['app_slug' => $app, 'slug' => $slug, 'name' => $name]);
    }
}

function seed_role_permissions(PDO $pdo): void
{
    $map = [
        'admin' => ['platform.admin', 'content.edit', 'acesso.admin', 'acesso.users.read', 'acesso.users.create', 'acesso.users.update', 'acesso.users.permissions'],
        'user' => ['content.edit'],
        'acesso.admin' => ['acesso.admin', 'acesso.users.read', 'acesso.users.create', 'acesso.users.update', 'acesso.users.permissions'],
        'fichario.reader' => ['fichario.access'],
        'fichario.admin' => ['fichario.access', 'fichario.admin'],
        'ldrt.reader' => ['ldrt.access'],
        'ldrt.admin' => ['ldrt.access', 'ldrt.admin'],
        'carex.reader' => ['carex.access'],
        'carex.admin' => ['carex.access', 'carex.admin'],
    ];

    $roleStmt = $pdo->prepare('SELECT id FROM ' . table_name('roles') . ' WHERE slug = :slug');
    $permissionStmt = $pdo->prepare('SELECT id FROM ' . table_name('permissions') . ' WHERE slug = :slug');
    $insertStmt = $pdo->prepare('INSERT INTO ' . table_name('role_permissions') . ' (role_id, permission_id) VALUES (:role_id, :permission_id) ON CONFLICT DO NOTHING');

    foreach ($map as $roleSlug => $permissionSlugs) {
        $roleStmt->execute(['slug' => $roleSlug]);
        $roleId = $roleStmt->fetchColumn();
        if (!$roleId) {
            continue;
        }

        foreach ($permissionSlugs as $permissionSlug) {
            $permissionStmt->execute(['slug' => $permissionSlug]);
            $permissionId = $permissionStmt->fetchColumn();
            if ($permissionId) {
                $insertStmt->execute(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }
}

function seed_admin_user(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM ' . table_name('users'))->fetchColumn();
    if ($count > 0) {
        return;
    }

    $stmt = $pdo->prepare('
        INSERT INTO ' . table_name('users') . " (name, email, username, password_hash, status, email_verified_at, must_change_password)
        VALUES (:name, :email, :username, :password_hash, 'active', (now() at time zone 'utc'), true)
        RETURNING id
    ");
    $stmt->execute([
        'name' => 'Administrador',
        'email' => 'augustosc@gmail.com',
        'username' => 'augustosc',
        'password_hash' => password_hash('admin', PASSWORD_DEFAULT),
    ]);
    $userId = (int) $stmt->fetchColumn();

    $roleStmt = $pdo->prepare('SELECT id FROM ' . table_name('roles') . " WHERE slug = 'admin'");
    $roleStmt->execute();
    $roleId = (int) $roleStmt->fetchColumn();

    if ($userId > 0 && $roleId > 0) {
        $assignStmt = $pdo->prepare('INSERT INTO ' . table_name('user_roles') . ' (user_id, role_id) VALUES (:user_id, :role_id) ON CONFLICT DO NOTHING');
        $assignStmt->execute(['user_id' => $userId, 'role_id' => $roleId]);
    }
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_url(string $path = ''): string
{
    $base = rtrim((string) env_value('APP_URL', ''), '/');
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/acesso/index.php')), '/');
        $base = $scheme . '://' . $host . ($scriptDir === '' ? '' : $scriptDir);
    }

    return $base . ($path === '' ? '' : '/' . ltrim($path, '/'));
}

function local_url(string $path = ''): string
{
    return h($path === '' ? 'index.php' : $path);
}

function csrf_token(): string
{
    if (empty($_SESSION['_acesso_csrf']) || !is_string($_SESSION['_acesso_csrf'])) {
        $_SESSION['_acesso_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_acesso_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function require_csrf(): void
{
    $token = (string) ($_POST['csrf_token'] ?? '');
    if ($token !== '' && isset($_SESSION['_acesso_csrf']) && hash_equals((string) $_SESSION['_acesso_csrf'], $token)) {
        return;
    }

    http_response_code(419);
    exit('Sessao expirada. Recarregue a pagina.');
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return is_string($value) ? $value : null;
}

function current_user(): ?array
{
    $userId = (int) ($_SESSION['_acesso_user_id'] ?? 0);
    if ($userId <= 0) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM ' . table_name('users') . ' WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    if (!$user || ($user['status'] ?? '') !== 'active') {
        unset($_SESSION['_acesso_user_id']);
        return null;
    }

    $permissions = user_permissions((int) $user['id']);
    $roles = user_role_slugs((int) $user['id']);
    $user['_permissions'] = $permissions;
    $user['_roles'] = $roles;
    $user['role'] = canonical_user_role($user, $roles, $permissions);

    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['_acesso_user_id'] = (int) $user['id'];
    $_SESSION['_acesso_user_name'] = (string) ($user['name'] ?? '');
    $_SESSION['_acesso_user_email'] = (string) ($user['email'] ?? '');

    try {
        $permissions = user_permissions((int) $user['id']);
        $roles = user_role_slugs((int) $user['id']);
        $_SESSION['_acesso_user_role'] = canonical_user_role($user, $roles, $permissions);
    } catch (Throwable $e) {
        $_SESSION['_acesso_user_role'] = 'user';
    }
}

function logout_user(): void
{
    unset($_SESSION['_acesso_user_id']);
    session_regenerate_id(true);
}

function require_login(): void
{
    if (is_logged_in()) {
        return;
    }

    $next = $_SERVER['REQUEST_URI'] ?? 'index.php';
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $loginUrl = str_contains($scriptName, '/acesso/') ? 'login.php' : '../acesso/login.php';
    header('Location: ' . $loginUrl . '?next=' . rawurlencode($next));
    exit;
}

function safe_redirect_target(string $target, string $fallback = 'index.php'): string
{
    $target = trim($target);
    if ($target === '' || preg_match('~^[a-z][a-z0-9+.-]*://~i', $target) || str_starts_with($target, '//')) {
        return $fallback;
    }

    if (str_contains($target, "\r") || str_contains($target, "\n")) {
        return $fallback;
    }

    return $target;
}

function user_permissions(int $userId): array
{
    $stmt = db()->prepare('
        SELECT DISTINCT p.slug
          FROM ' . table_name('permissions') . ' p
          JOIN ' . table_name('role_permissions') . ' rp ON rp.permission_id = p.id
          JOIN ' . table_name('user_roles') . ' ur ON ur.role_id = rp.role_id
         WHERE ur.user_id = :user_id
         ORDER BY p.slug
    ');
    $stmt->execute(['user_id' => $userId]);

    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function user_role_slugs(int $userId): array
{
    $stmt = db()->prepare('
        SELECT DISTINCT r.slug
          FROM ' . table_name('roles') . ' r
          JOIN ' . table_name('user_roles') . ' ur ON ur.role_id = r.id
         WHERE ur.user_id = :user_id
         ORDER BY r.slug
    ');
    $stmt->execute(['user_id' => $userId]);

    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function canonical_user_role(?array $user = null, ?array $roles = null, ?array $permissions = null): string
{
    if ($user === null) {
        return 'public';
    }

    $userId = (int) ($user['id'] ?? 0);
    $roles ??= $userId > 0 ? user_role_slugs($userId) : [];
    $permissions ??= $userId > 0 ? user_permissions($userId) : [];

    $legacyAdminPermissions = [
        'platform.admin',
        'acesso.admin',
        'fichario.admin',
        'ldrt.admin',
        'carex.admin',
    ];

    if (in_array('admin', $roles, true) || array_intersect($legacyAdminPermissions, $permissions) !== []) {
        return 'admin';
    }

    if (in_array('user', $roles, true) || in_array('content.edit', $permissions, true)) {
        return 'user';
    }

    return 'public';
}

function platform_is_admin(?array $user = null): bool
{
    return canonical_user_role($user ?? current_user()) === 'admin';
}

function platform_can_edit(?array $user = null): bool
{
    $role = canonical_user_role($user ?? current_user());
    return $role === 'admin' || $role === 'user';
}

function has_permission(string $permission): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }

    if (platform_is_admin($user)) {
        return true;
    }

    if ($permission === 'content.edit') {
        return platform_can_edit($user);
    }

    if (str_ends_with($permission, '.admin') || str_starts_with($permission, 'acesso.users.') || $permission === 'platform.admin') {
        return false;
    }

    $permissions = $user['_permissions'] ?? user_permissions((int) $user['id']);
    return in_array($permission, $permissions, true);
}

function require_permission(string $permission): void
{
    require_login();

    if (has_permission($permission)) {
        return;
    }

    http_response_code(403);
    exit('Acesso restrito.');
}

function require_platform_admin(): void
{
    require_login();

    if (platform_is_admin()) {
        return;
    }

    http_response_code(403);
    exit('Acesso restrito a administradores.');
}

function require_platform_user(): void
{
    require_login();

    if (platform_can_edit()) {
        return;
    }

    http_response_code(403);
    exit('Acesso restrito a usuarios autenticados.');
}

function find_user_by_login(string $login): ?array
{
    $stmt = db()->prepare('
        SELECT *
          FROM ' . table_name('users') . '
         WHERE lower(email) = lower(:login)
            OR lower(coalesce(username, \'\')) = lower(:login)
         LIMIT 1
    ');
    $stmt->execute(['login' => trim($login)]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function record_login_attempt(string $login, bool $successful): void
{
    try {
        $stmt = db()->prepare('INSERT INTO ' . table_name('login_attempts') . ' (login, ip_address, successful) VALUES (:login, :ip_address, :successful)');
        $stmt->execute([
            'login' => mb_strtolower(trim($login), 'UTF-8'),
            'ip_address' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            'successful' => $successful,
        ]);
    } catch (Throwable $e) {
        // Login attempts are useful for audit, but must not block login feedback.
    }
}

function validate_password_policy(string $password): array
{
    if ($password === '') {
        return ['Informe uma senha.'];
    }

    return [];
}

function send_password_reset_email(array $user, string $token): bool
{
    $link = app_url('reset_password.php?token=' . rawurlencode($token));
    $subject = 'Recuperacao de senha - Plataforma RENAST Online';
    $message = "Ola, " . ($user['name'] ?? '') . "\n\n";
    $message .= "Recebemos uma solicitacao para redefinir sua senha no modulo Acesso.\n\n";
    $message .= "Use o link abaixo para definir uma nova senha:\n$link\n\n";
    $message .= "Se voce nao solicitou esta recuperacao, ignore este e-mail.\n";
    $headers = 'From: ' . (env_value('MAIL_FROM', 'no-reply@renastonline.org') ?: 'no-reply@renastonline.org');

    return function_exists('mail') && @mail((string) $user['email'], $subject, $message, $headers);
}

function render_header(string $title, string $active = ''): void
{
    $user = current_user();
    $notice = flash('notice');
    $error = flash('error');
    $module = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') ? 'admin' : 'acesso';

    echo '<!doctype html><html lang="pt-BR" data-module="' . h($module) . '"><head>';
    echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . h($title) . ' - Acesso RENAST</title>';
    echo '<link rel="icon" type="image/png" href="../favicon.png">';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">';
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">';
    echo '<link href="assets/app.css?v=20260629-vanilla" rel="stylesheet">';
    echo '<link href="../assets/css/style.css?v=20260629-vanilla" rel="stylesheet">';
    echo '<script src="../assets/js/theme-switcher.js?v=20260629-vanilla"></script>';
    echo '</head><body>';
    require_once __DIR__ . '/../../includes/navbar.php';
    render_platform_navbar($module, $active);
    echo '<main class="container py-4">';

    if ($notice) {
        echo '<div class="alert alert-success">' . h($notice) . '</div>';
    }
    if ($error) {
        echo '<div class="alert alert-danger">' . h($error) . '</div>';
    }
}

function render_footer(): void
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $appJs = str_contains($scriptName, '/admin/') ? '../acesso/assets/app.js?v=20260629-vanilla' : 'assets/app.js?v=20260629-vanilla';

    echo '</main>';
    echo '<div id="cookieBanner" class="cookie-banner" role="region" aria-label="Aviso de cookies">';
    echo '<div>Usamos cookies essenciais para manter sua sessao autenticada e proteger o acesso.</div>';
    echo '<button class="btn btn-sm btn-primary" type="button" id="acceptCookies">Entendi</button>';
    echo '</div>';
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>';
    echo '<script src="' . h($appJs) . '"></script>';
    echo '</body></html>';
}
