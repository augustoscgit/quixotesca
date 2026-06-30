<?php
declare(strict_types=1);

ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

require_once __DIR__ . '/../includes/markdown.php';

load_env(__DIR__ . DIRECTORY_SEPARATOR . 'secrets' . DIRECTORY_SEPARATOR . '.env');
load_env(__DIR__ . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . '.env');
load_env(__DIR__ . DIRECTORY_SEPARATOR . '.env');

// Always enable logging of errors to a file in the workspace
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'php_errors.log');

if (app_debug_enabled()) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

if (PHP_SAPI !== 'cli') {
    ob_start('inject_default_html_metadata');
}

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    $sessionDir = configure_session();
    session_start();
    enforce_session_idle_timeout();
    maybe_cleanup_session_files($sessionDir);
}

function configure_session(): string
{
    $sessionDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'acesso' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'sessions';
    $sessionLifetime = env_int('SESSION_IDLE_TIMEOUT', 14400);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
    ini_set('session.gc_probability', (string) env_int('SESSION_NATIVE_GC_PROBABILITY', 1));
    ini_set('session.gc_divisor', (string) env_int('SESSION_NATIVE_GC_DIVISOR', 100));

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

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    return $sessionDir;
}

function enforce_session_idle_timeout(): void
{
    $sessionLifetime = env_int('SESSION_IDLE_TIMEOUT', 14400);
    if ($sessionLifetime <= 0) {
        return;
    }

    $now = time();
    $lastActivity = (int) ($_SESSION['last_activity_at'] ?? 0);
    if ($lastActivity > 0 && ($now - $lastActivity) > $sessionLifetime) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                $now - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }
        session_destroy();
        session_start();
        session_regenerate_id(true);
    }

    $_SESSION['last_activity_at'] = $now;
}

function maybe_cleanup_session_files(string $sessionDir): void
{
    if (!is_dir($sessionDir) || !is_writable($sessionDir)) {
        return;
    }

    $probability = env_int('SESSION_APP_GC_PROBABILITY', 1);
    $divisor = max(1, env_int('SESSION_APP_GC_DIVISOR', 100));
    if ($probability <= 0 || random_int(1, $divisor) > $probability) {
        return;
    }

    $interval = max(60, env_int('SESSION_APP_GC_INTERVAL', 3600));
    $marker = $sessionDir . DIRECTORY_SEPARATOR . '.gc-last-run';
    if (is_file($marker) && (time() - (int) filemtime($marker)) < $interval) {
        return;
    }
    @touch($marker);

    cleanup_session_files($sessionDir);
}

function cleanup_session_files(string $sessionDir): int
{
    $sessionLifetime = max(300, env_int('SESSION_IDLE_TIMEOUT', 14400));
    $emptyLifetime = max(60, env_int('SESSION_EMPTY_FILE_TIMEOUT', 900));
    $currentSession = session_id();
    $now = time();
    $removed = 0;

    foreach (glob($sessionDir . DIRECTORY_SEPARATOR . 'sess_*') ?: [] as $file) {
        if (!is_file($file) || basename($file) === ('sess_' . $currentSession)) {
            continue;
        }

        $age = $now - (int) filemtime($file);
        $isEmpty = (int) filesize($file) === 0;
        if ($age > $sessionLifetime || ($isEmpty && $age > $emptyLifetime)) {
            if (@unlink($file)) {
                $removed++;
            }
        }
    }

    return $removed;
}

function inject_default_html_metadata(string $html): string
{
    if (stripos($html, '<head') === false || stripos($html, 'data-fichario-seo') !== false) {
        return $html;
    }

    $title = 'Fichario Academico';
    if (preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
        $title = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8')) ?: $title;
    }

    $description = 'Fichario Academico para organizar artigos, notas, citacoes, tags tematicas e fichamentos de pesquisa.';
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $page = strtolower(basename($path));
    $privatePages = [
        'admin.php',
        'admin_docs.php',
        'confirm_email.php',
        'editor.php',
        'forgot_password.php',
        'login.php',
        'logout.php',
        'profile.php',
        'project.php',
        'projects.php',
        'reset_password.php',
        'setup.php',
        'users.php',
    ];
    $robots = (!in_array($page, $privatePages, true) && env_bool('APP_ALLOW_INDEXING', false))
        ? 'index,follow'
        : 'noindex,nofollow';
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    $relativeUri = $requestUri;
    if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($relativeUri, $scriptDir . '/')) {
        $relativeUri = substr($relativeUri, strlen($scriptDir) + 1);
    }
    $canonical = app_url(ltrim($relativeUri, '/'));
    $schemaType = match ($page) {
        'view.php' => 'ScholarlyArticle',
        'tag_view.php' => 'DefinedTerm',
        default => 'WebApplication',
    };
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => $schemaType,
        'name' => $title,
        'description' => $description,
        'url' => $canonical,
        'inLanguage' => 'pt-BR',
        'isAccessibleForFree' => true,
    ];

    $metadata = "\n" . implode("\n", [
        '    <meta name="description" content="' . h($description) . '" data-fichario-seo="1">',
        '    <meta name="robots" content="' . h($robots) . '" data-fichario-seo="1">',
        '    <link rel="canonical" href="' . h($canonical) . '" data-fichario-seo="1">',
        '    <meta property="og:title" content="' . h($title) . '" data-fichario-seo="1">',
        '    <meta property="og:description" content="' . h($description) . '" data-fichario-seo="1">',
        '    <meta property="og:type" content="website" data-fichario-seo="1">',
        '    <meta property="og:url" content="' . h($canonical) . '" data-fichario-seo="1">',
        '    <script type="application/ld+json" data-fichario-seo="1">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>',
    ]) . "\n";

    return preg_replace('/(<head\b[^>]*>)/i', '$1' . $metadata, $html, 1) ?? $html;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env_value('DB_HOST');
    $port = env_value('DB_PORT', '5432');
    $database = env_value('DB_DATABASE');
    $username = env_value('DB_USERNAME');
    $password = env_value('DB_PASSWORD');
    $schema = env_value('DB_SCHEMA', 'public');
    $sslmode = env_value('DB_SSLMODE', '');

    $dsn = "pgsql:host=$host;port=$port;dbname=$database";
    if ($sslmode !== '') {
        $dsn .= ";sslmode=$sslmode";
    }
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Set client encoding to UTF-8
    $pdo->exec("SET client_encoding TO 'UTF8'");

    // Set search path
    $quotedSchema = '"' . str_replace('"', '""', $schema) . '"';
    $pdo->exec("SET search_path TO $quotedSchema, public");

    $lockFile1 = __DIR__ . DIRECTORY_SEPARATOR . 'secrets' . DIRECTORY_SEPARATOR . 'migration.lock';
    $lockFile2 = __DIR__ . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'migration.lock';
    $lockFile3 = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'migration.lock';

    if (!file_exists($lockFile1) && !file_exists($lockFile2) && !file_exists($lockFile3)) {
        migrate($pdo);
        @file_put_contents($lockFile1, date('Y-m-d H:i:s'));
        @file_put_contents($lockFile2, date('Y-m-d H:i:s'));
        @file_put_contents($lockFile3, date('Y-m-d H:i:s'));
    }

    $projLockFile1 = __DIR__ . DIRECTORY_SEPARATOR . 'secrets' . DIRECTORY_SEPARATOR . 'project_migration.lock';
    $projLockFile2 = __DIR__ . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'project_migration.lock';
    $projLockFile3 = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'project_migration.lock';

    if (!file_exists($projLockFile1) && !file_exists($projLockFile2) && !file_exists($projLockFile3)) {
        migrate_project_tables($pdo);
        @file_put_contents($projLockFile1, date('Y-m-d H:i:s'));
        @file_put_contents($projLockFile2, date('Y-m-d H:i:s'));
        @file_put_contents($projLockFile3, date('Y-m-d H:i:s'));
    }

    $refLockFile1 = __DIR__ . DIRECTORY_SEPARATOR . 'secrets' . DIRECTORY_SEPARATOR . 'article_reference_migration.lock';
    $refLockFile2 = __DIR__ . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'article_reference_migration.lock';
    $refLockFile3 = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'article_reference_migration.lock';

    if (!file_exists($refLockFile1) && !file_exists($refLockFile2) && !file_exists($refLockFile3)) {
        ensure_article_reference_columns($pdo);
        @file_put_contents($refLockFile1, date('Y-m-d H:i:s'));
        @file_put_contents($refLockFile2, date('Y-m-d H:i:s'));
        @file_put_contents($refLockFile3, date('Y-m-d H:i:s'));
    }

    $agentLockFile1 = __DIR__ . DIRECTORY_SEPARATOR . 'secrets' . DIRECTORY_SEPARATOR . 'project_agent_instructions_migration.lock';
    $agentLockFile2 = __DIR__ . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'project_agent_instructions_migration.lock';
    $agentLockFile3 = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'project_agent_instructions_migration.lock';

    if (!file_exists($agentLockFile1) && !file_exists($agentLockFile2) && !file_exists($agentLockFile3)) {
        ensure_project_agent_instructions_column($pdo);
        @file_put_contents($agentLockFile1, date('Y-m-d H:i:s'));
        @file_put_contents($agentLockFile2, date('Y-m-d H:i:s'));
        @file_put_contents($agentLockFile3, date('Y-m-d H:i:s'));
    }

    return $pdo;
}

function migrate(PDO $pdo): void
{
    $schema = env_value('DB_SCHEMA', 'public');
    $quotedSchema = '"' . str_replace('"', '""', $schema) . '"';

    // Create extensions
    $pdo->exec("CREATE EXTENSION IF NOT EXISTS unaccent");
    $pdo->exec("CREATE EXTENSION IF NOT EXISTS pg_trgm");

    // Create immutable search_norm helper for GIN indexes
    $pdo->exec("
        CREATE OR REPLACE FUNCTION search_norm(val text) RETURNS text AS $$
            SELECT lower(unaccent(coalesce(val, '')));
        $$ LANGUAGE sql IMMUTABLE SET search_path = $quotedSchema, public;
    ");

    // Create articles table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS articles (
            id INT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
            title TEXT NOT NULL,
            authors TEXT,
            year INTEGER,
            journal TEXT,
            volume TEXT,
            issue TEXT,
            pages TEXT,
            publisher TEXT,
            doi TEXT,
            url TEXT,
            abstract TEXT,
            full_text TEXT,
            references_text TEXT,
            keywords TEXT,
            bibtex_key TEXT,
            bibtex_raw TEXT,
            reference_abnt TEXT NOT NULL DEFAULT '',
            reference_abnt_locked BOOLEAN NOT NULL DEFAULT false,
            reference_abnt_missing TEXT NOT NULL DEFAULT '',
            analysis TEXT,
            pdf_url TEXT,
            data_year_start INTEGER,
            data_year_end INTEGER,
            created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc'),
            updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc')
        )
    ");

    // Create tags table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tags (
            id INT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
            name TEXT UNIQUE NOT NULL,
            definition TEXT,
            category TEXT NOT NULL DEFAULT '',
            created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc')
        )
    ");

    // Create tag_hierarchy table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tag_hierarchy (
            parent_id INTEGER NOT NULL,
            child_id INTEGER NOT NULL,
            PRIMARY KEY (parent_id, child_id),
            FOREIGN KEY (parent_id) REFERENCES tags (id) ON DELETE CASCADE,
            FOREIGN KEY (child_id) REFERENCES tags (id) ON DELETE CASCADE,
            CHECK (parent_id != child_id)
        )
    ");

    // Create article_tag_quotes table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS article_tag_quotes (
            id INT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
            article_id INTEGER NOT NULL,
            quote_text TEXT NOT NULL,
            comment TEXT NOT NULL DEFAULT '',
            created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc'),
            updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc'),
            FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE
        )
    ");

    // Create article_quote_tags table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS article_quote_tags (
            quote_id INTEGER NOT NULL,
            tag_id INTEGER NOT NULL,
            PRIMARY KEY (quote_id, tag_id),
            FOREIGN KEY (quote_id) REFERENCES article_tag_quotes (id) ON DELETE CASCADE,
            FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE
        )
    ");

    // Create view
    $pdo->exec("
        CREATE OR REPLACE VIEW article_tags AS
        SELECT
            q.article_id,
            qt.tag_id,
            q.comment,
            q.quote_text AS quote
        FROM article_tag_quotes q
        JOIN article_quote_tags qt ON qt.quote_id = q.id
    ");

    // Create GIN indexes
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_articles_title_trgm ON articles USING gin (search_norm(title) gin_trgm_ops)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_articles_authors_trgm ON articles USING gin (search_norm(authors) gin_trgm_ops)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_articles_journal_trgm ON articles USING gin (search_norm(journal) gin_trgm_ops)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_articles_keywords_trgm ON articles USING gin (search_norm(keywords) gin_trgm_ops)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_articles_abstract_trgm ON articles USING gin (search_norm(abstract) gin_trgm_ops)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_articles_full_text_trgm ON articles USING gin (search_norm(full_text) gin_trgm_ops)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_articles_references_text_trgm ON articles USING gin (search_norm(references_text) gin_trgm_ops)");

    // Create B-Tree indexes for foreign keys
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tag_hierarchy_child_id ON tag_hierarchy(child_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_article_tag_quotes_article_id ON article_tag_quotes(article_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_article_quote_tags_tag_id ON article_quote_tags(tag_id)");
}

function migrate_project_tables(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS projects (
            id INT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
            owner_user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT '',
            agent_instructions TEXT NOT NULL DEFAULT '',
            created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc'),
            updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc')
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS project_sections (
            id INT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
            project_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            context TEXT NOT NULL DEFAULT '',
            position INTEGER NOT NULL DEFAULT 0,
            created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc'),
            updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc'),
            FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS project_tags (
            project_id INTEGER NOT NULL,
            tag_id INTEGER NOT NULL,
            created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc'),
            PRIMARY KEY (project_id, tag_id),
            FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
            FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS project_section_notes (
            section_id INTEGER NOT NULL,
            note_id INTEGER NOT NULL,
            position INTEGER NOT NULL DEFAULT 0,
            created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT (now() at time zone 'utc'),
            PRIMARY KEY (section_id, note_id),
            FOREIGN KEY (section_id) REFERENCES project_sections (id) ON DELETE CASCADE,
            FOREIGN KEY (note_id) REFERENCES article_tag_quotes (id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_projects_owner_user_id ON projects(owner_user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_project_tags_tag_id ON project_tags(tag_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_project_sections_project_position ON project_sections(project_id, position, id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_project_section_notes_note_id ON project_section_notes(note_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_project_section_notes_section_position ON project_section_notes(section_id, position, note_id)");
}

function ensure_article_reference_columns(PDO $pdo): void
{
    $pdo->exec("ALTER TABLE articles ADD COLUMN IF NOT EXISTS reference_abnt TEXT NOT NULL DEFAULT ''");
    $pdo->exec("ALTER TABLE articles ADD COLUMN IF NOT EXISTS reference_abnt_locked BOOLEAN NOT NULL DEFAULT false");
    $pdo->exec("ALTER TABLE articles ADD COLUMN IF NOT EXISTS reference_abnt_missing TEXT NOT NULL DEFAULT ''");
}

function ensure_project_agent_instructions_column(PDO $pdo): void
{
    $pdo->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS agent_instructions TEXT NOT NULL DEFAULT ''");
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
        $value = trim($value);
        $value = trim($value, "\"'");

        if ($key !== '' && !array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            @putenv($key . '=' . $value);
        }
    }
}

function env_value(string $key, ?string $default = null): ?string
{
    if (array_key_exists($key, $_ENV)) {
        return (string) $_ENV[$key];
    }
    if (array_key_exists($key, $_SERVER)) {
        return (string) $_SERVER[$key];
    }

    $value = getenv($key);
    if ($value === false) {
        return $default;
    }

    return $value;
}

function env_bool(string $key, bool $default = false): bool
{
    $value = env_value($key);
    if ($value === null) {
        return $default;
    }

    return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
}

function truthy_value(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value) || is_float($value)) {
        return (int) $value === 1;
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 't', 'yes', 'on'], true);
}

function default_project_agent_instructions(): string
{
    return implode("\n", [
        '- Use somente as informacoes deste pacote como base factual, salvo instrucao explicita do usuario.',
        '- Se for pesquisar texto completo, registre quais fontes externas foram consultadas e se o texto completo/PDF foi encontrado.',
        '- Priorize DOI, URL original e PDF URL antes de buscas amplas por titulo.',
        '- Use apenas fontes legais e verificaveis: DOI/editora, periodico, SciELO, PubMed/PMC, repositorios institucionais, paginas oficiais e bases academicas abertas.',
        '- Nao invente dados bibliograficos ausentes. Se algo faltar, mantenha a pendencia explicitamente.',
        '- Preserve a estrutura das secoes do projeto como eixo analitico principal.',
        '- Use apenas as notas vinculadas ao projeto/secoes; outras notas do fichario nao foram exportadas.',
        '- Cite os artigos usando a citacao curta indicada em cada nota e monte a lista final com as referencias ABNT fornecidas.',
        '- Diferencie citacao literal, observacao/fichamento e metadados bibliograficos.',
        '- Quando uma conclusao depender de inferencia, sinalize a inferencia.',
    ]);
}

function effective_project_agent_instructions(array $project): string
{
    $custom = trim((string) ($project['agent_instructions'] ?? ''));

    return $custom !== '' ? $custom : default_project_agent_instructions();
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

spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function fichario_markdown_render_inline(string $text): string
{
    return platform_markdown_render_inline($text);
}

function fichario_render_markdown(?string $markdown): string
{
    return platform_markdown_render($markdown);
}

function app_url(string $path = ''): string
{
    $base = rtrim((string) env_value('APP_URL', ''), '/');
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/quixotesca/fichario/index.php')), '/');
        $base = $scheme . '://' . $host . ($scriptDir === '' ? '' : $scriptDir);
    }

    return $base . ($path === '' ? '' : '/' . ltrim($path, '/'));
}

function access_schema_name(): string
{
    return env_value('ACCESS_DB_SCHEMA', 'acesso') ?: 'acesso';
}

function qi(string $identifier): string
{
    return '"' . str_replace('"', '""', $identifier) . '"';
}

function access_table_name(string $table): string
{
    return qi(access_schema_name()) . '.' . qi($table);
}

function access_url(string $path = ''): string
{
    $currentScript = $_SERVER['SCRIPT_FILENAME'] ?? '';
    if ($currentScript !== '') {
        $projectRootPath = realpath(dirname(__DIR__) . '/public_html');
        $realScript = realpath($currentScript);
        if ($projectRootPath !== false && $realScript !== false) {
            $currentDir = str_replace('\\', '/', dirname($realScript));
            $projectRoot = str_replace('\\', '/', $projectRootPath);
            if (str_starts_with($currentDir, $projectRoot)) {
                $subPath = substr($currentDir, strlen($projectRoot));
                $subPath = trim($subPath, '/');
                $count = $subPath === '' ? 0 : substr_count($subPath, '/') + 1;
                $relPath = str_repeat('../', $count);
                return $relPath . 'acesso' . ($path === '' ? '' : '/' . ltrim($path, '/'));
            }
        }
    }
    return '../acesso' . ($path === '' ? '' : '/' . ltrim($path, '/'));
}

function redirect_to_access(string $path = 'index.php'): void
{
    header('Location: ' . access_url($path));
    exit;
}

function access_users_count(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM ' . access_table_name('users'))->fetchColumn();
}

function access_inactive_users_count(): int
{
    return (int) db()->query("SELECT COUNT(*) FROM " . access_table_name('users') . " WHERE status <> 'active'")->fetchColumn();
}

function current_user(): ?array
{
    $userId = (int) ($_SESSION['_acesso_user_id'] ?? 0);
    if ($userId <= 0) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM ' . access_table_name('users') . ' WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    if (!$user || ($user['status'] ?? '') !== 'active') {
        unset($_SESSION['_acesso_user_id']);
        return null;
    }

    $permissions = user_permissions($userId);
    $user['_permissions'] = $permissions;
    $user['role'] = in_array('fichario.admin', $permissions, true) || in_array('acesso.admin', $permissions, true)
        ? 'admin'
        : 'reader';

    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    return has_permission('fichario.admin');
}

function can_edit_content(): bool
{
    return has_permission('fichario.admin');
}

function user_permissions(int $userId): array
{
    $stmt = db()->prepare('
        SELECT DISTINCT p.slug
          FROM ' . access_table_name('permissions') . ' p
          JOIN ' . access_table_name('role_permissions') . ' rp ON rp.permission_id = p.id
          JOIN ' . access_table_name('user_roles') . ' ur ON ur.role_id = rp.role_id
         WHERE ur.user_id = :user_id
         ORDER BY p.slug
    ');
    $stmt->execute(['user_id' => $userId]);

    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function has_permission(string $permission): bool
{
    $user = current_user();
    if ($user === null) {
        return false;
    }

    $permissions = $user['_permissions'] ?? [];
    if (in_array('acesso.admin', $permissions, true) || in_array($permission, $permissions, true)) {
        return true;
    }

    return $permission === 'fichario.access' && in_array('fichario.admin', $permissions, true);
}

function request_is_ajax(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

    return str_contains($accept, 'application/json') || strtolower($requestedWith) === 'xmlhttprequest';
}

function require_login(): void
{
    if (is_logged_in() && has_permission('fichario.access')) {
        return;
    }

    if (request_is_ajax()) {
        http_response_code(is_logged_in() ? 403 : 401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => is_logged_in() ? 'Acesso restrito ao Fichario.' : 'Login necessario.']);
        exit;
    }

    if (is_logged_in()) {
        http_response_code(403);
        echo 'Acesso restrito ao Fichario.';
        exit;
    }

    $next = $_SERVER['REQUEST_URI'] ?? 'index.php';
    header('Location: ' . access_url('login.php?next=' . rawurlencode($next)));
    exit;
}

function require_admin(): void
{
    require_login();

    if (is_admin()) {
        return;
    }

    if (request_is_ajax()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Acesso restrito a administradores.']);
        exit;
    }

    http_response_code(403);
    echo 'Acesso restrito a administradores.';
    exit;
}

function require_editor(): void
{
    require_login();

    if (can_edit_content()) {
        return;
    }

    if (request_is_ajax()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Acesso restrito a administradores do Fichario.']);
        exit;
    }

    http_response_code(403);
    echo 'Acesso restrito a administradores do Fichario.';
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function require_csrf(): void
{
    $token = (string) ($_POST['csrf_token'] ?? '');
    if ($token !== '' && hash_equals(csrf_token(), $token)) {
        return;
    }

    http_response_code(419);
    if (request_is_ajax()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Sessao expirada. Recarregue a pagina.']);
        exit;
    }

    echo 'Sessao expirada. Recarregue a pagina.';
    exit;
}

function search_normalize(?string $value): string
{
    $value = mb_strtolower((string) $value, 'UTF-8');

    return strtr($value, [
        'á' => 'a',
        'à' => 'a',
        'ã' => 'a',
        'â' => 'a',
        'ä' => 'a',
        'é' => 'e',
        'è' => 'e',
        'ê' => 'e',
        'ë' => 'e',
        'í' => 'i',
        'ì' => 'i',
        'î' => 'i',
        'ï' => 'i',
        'ó' => 'o',
        'ò' => 'o',
        'õ' => 'o',
        'ô' => 'o',
        'ö' => 'o',
        'ú' => 'u',
        'ù' => 'u',
        'û' => 'u',
        'ü' => 'u',
        'ç' => 'c',
        'ñ' => 'n',
    ]);
}

function get_tag_colors(string $category): array
{
    $normalized = search_normalize(trim($category));
    $colors = [
        'tema' => [
            'bg' => 'var(--tag-tema-bg)',
            'text' => 'var(--tag-tema-text)',
            'border' => 'var(--tag-tema-border)',
            'solid' => '#006392',
        ],
        'metodo' => [
            'bg' => 'var(--tag-metodo-bg)',
            'text' => 'var(--tag-metodo-text)',
            'border' => 'var(--tag-metodo-border)',
            'solid' => '#944F00',
        ],
        'fonte' => [
            'bg' => 'var(--tag-fonte-bg)',
            'text' => 'var(--tag-fonte-text)',
            'border' => 'var(--tag-fonte-border)',
            'solid' => '#5DCF00',
        ],
        'sem agrupamento' => [
            'bg' => 'var(--tag-neutro-bg)',
            'text' => 'var(--tag-neutro-text)',
            'border' => 'var(--tag-neutro-border)',
            'solid' => '#464B51',
        ],
        'outros' => [
            'bg' => 'var(--tag-neutro-bg)',
            'text' => 'var(--tag-neutro-text)',
            'border' => 'var(--tag-neutro-border)',
            'solid' => '#464B51',
        ],
    ];
    return $colors[$normalized] ?? [
        'bg' => 'var(--tag-neutro-bg)',
        'text' => 'var(--tag-neutro-text)',
        'border' => 'var(--tag-neutro-border)',
        'solid' => '#464B51',
    ];
}

function tag_definition_text(array $tag): string
{
    $definition = trim((string) ($tag['definition'] ?? ''));
    return $definition !== '' ? $definition : 'Sem definição cadastrada.';
}

function tag_tooltip_attrs(array $tag): string
{
    $tooltip = tag_definition_text($tag);
    return ' title="' . h($tooltip) . '" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="' . h($tooltip) . '"';
}

function render_navbar(string $activePage = ''): void
{
    require_once __DIR__ . '/../includes/navbar.php';
    render_platform_navbar('fichario', $activePage);
}

function render_admin_navbar(string $activePage = ''): void
{
    require_once __DIR__ . '/../includes/navbar.php';
    render_platform_navbar('fichario', $activePage);
    return;
}

function count_words(string $text): int
{
    $text = trim($text);
    if ($text === '') {
        return 0;
    }
    return preg_match_all('/\p{L}+/u', $text);
}

function text_teaser(string $text, int $maxLength = 180): string
{
    $normalized = trim((string) preg_replace('/\s+/u', ' ', $text));
    if ($normalized === '' || $maxLength <= 0) {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($normalized, 'UTF-8') <= $maxLength) {
            return $normalized;
        }

        return rtrim(mb_substr($normalized, 0, $maxLength, 'UTF-8')) . '...';
    }

    preg_match_all('/./us', $normalized, $chars);
    $chars = $chars[0] ?? [];
    if (count($chars) <= $maxLength) {
        return $normalized;
    }

    return rtrim(implode('', array_slice($chars, 0, $maxLength))) . '...';
}

function article_abnt_single_line(?string $value): string
{
    return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
}

function article_abnt_authors(?string $authors): array
{
    $authors = article_abnt_single_line($authors);
    if ($authors === '') {
        return [];
    }

    $normalized = str_ireplace([' and ', ' & '], ';', $authors);
    $parts = array_map('trim', explode(';', $normalized));

    return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
}

function article_abnt_initials(string $givenNames): string
{
    $particles = ['da', 'das', 'de', 'di', 'do', 'dos', 'e'];
    $initials = [];

    foreach (preg_split('/\s+/u', trim(str_replace(',', ' ', $givenNames))) ?: [] as $part) {
        $part = trim($part, " .\t\n\r\0\x0B");
        if ($part === '' || in_array(mb_strtolower($part, 'UTF-8'), $particles, true)) {
            continue;
        }
        $initials[] = mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8') . '.';
    }

    return implode(' ', $initials);
}

function article_abnt_author(string $author): string
{
    $author = article_abnt_single_line($author);
    if ($author === '') {
        return '';
    }

    if (str_contains($author, ',')) {
        [$lastName, $givenNames] = array_pad(array_map('trim', explode(',', $author, 2)), 2, '');
        $initials = article_abnt_initials($givenNames);

        return trim(mb_strtoupper($lastName, 'UTF-8') . ($initials !== '' ? ', ' . $initials : ''));
    }

    $parts = preg_split('/\s+/u', $author) ?: [];
    if (count($parts) === 1) {
        return mb_strtoupper($parts[0], 'UTF-8');
    }

    $suffixes = ['filho', 'junior', 'júnior', 'neto', 'sobrinho'];
    $lastName = (string) array_pop($parts);
    $previous = $parts[count($parts) - 1] ?? '';
    if ($previous !== '' && in_array(mb_strtolower($lastName, 'UTF-8'), $suffixes, true)) {
        $lastName = (string) array_pop($parts) . ' ' . $lastName;
    }

    $initials = article_abnt_initials(implode(' ', $parts));

    return trim(mb_strtoupper($lastName, 'UTF-8') . ($initials !== '' ? ', ' . $initials : ''));
}

function article_abnt_author_list(?string $authors): string
{
    $formatted = [];
    foreach (article_abnt_authors($authors) as $author) {
        $item = article_abnt_author($author);
        if ($item !== '') {
            $formatted[] = $item;
        }
    }

    return implode('; ', $formatted);
}

function article_abnt_missing_fields(array $article): array
{
    $missing = [];
    if (article_abnt_single_line($article['authors'] ?? '') === '') {
        $missing[] = 'autores';
    }
    if (article_abnt_single_line($article['title'] ?? '') === '') {
        $missing[] = 'titulo';
    }
    if (article_abnt_single_line((string) ($article['year'] ?? '')) === '') {
        $missing[] = 'ano';
    }
    if (
        article_abnt_single_line($article['journal'] ?? '') === ''
        && article_abnt_single_line($article['publisher'] ?? '') === ''
    ) {
        $missing[] = 'periodico/fonte ou editora';
    }
    if (article_abnt_single_line($article['pages'] ?? '') === '') {
        $missing[] = 'paginas';
    }
    if (
        article_abnt_single_line($article['doi'] ?? '') === ''
        && article_abnt_single_line($article['url'] ?? '') === ''
    ) {
        $missing[] = 'DOI ou URL';
    }

    return $missing;
}

function build_article_abnt_reference(array $article): string
{
    $authors = article_abnt_author_list($article['authors'] ?? '');
    $title = rtrim(article_abnt_single_line($article['title'] ?? 'Artigo sem titulo'), '.');
    $journal = rtrim(article_abnt_single_line($article['journal'] ?? ''), '.');
    $publisher = rtrim(article_abnt_single_line($article['publisher'] ?? ''), '.');
    $year = article_abnt_single_line((string) ($article['year'] ?? ''));
    $volume = article_abnt_single_line($article['volume'] ?? '');
    $issue = article_abnt_single_line($article['issue'] ?? '');
    $pages = article_abnt_single_line($article['pages'] ?? '');
    $doi = article_abnt_single_line($article['doi'] ?? '');
    $url = article_abnt_single_line($article['url'] ?? '');
    $pdfUrl = article_abnt_single_line($article['pdf_url'] ?? '');

    $parts = [];
    if ($authors !== '') {
        $parts[] = rtrim($authors, '.') . '.';
    }
    if ($title !== '') {
        $parts[] = $title . '.';
    }
    if ($journal !== '') {
        $parts[] = $journal . '.';
    } elseif ($publisher !== '') {
        $parts[] = $publisher . '.';
    }
    if ($volume !== '') {
        $parts[] = 'v. ' . $volume . '.';
    }
    if ($issue !== '') {
        $parts[] = 'n. ' . $issue . '.';
    }
    if ($pages !== '') {
        $parts[] = 'p. ' . $pages . '.';
    }
    if ($year !== '') {
        $parts[] = $year . '.';
    }
    if ($doi !== '') {
        $parts[] = 'DOI: ' . $doi . '.';
    }
    if ($url !== '') {
        $parts[] = 'Disponivel em: ' . $url . '.';
    } elseif ($pdfUrl !== '') {
        $parts[] = 'Disponivel em: ' . $pdfUrl . '.';
    }

    return trim((string) preg_replace('/\s+/u', ' ', implode(' ', $parts)));
}

function article_has_notes_sql(string $tableAlias = 'articles'): string
{
    return "(
        EXISTS (
            SELECT 1 FROM article_tags at 
            WHERE at.article_id = {$tableAlias}.id 
              AND (trim(coalesce(at.quote, '')) <> '' OR trim(coalesce(at.comment, '')) <> '')
        ) OR EXISTS (
            SELECT 1 FROM article_tag_quotes q 
            WHERE q.article_id = {$tableAlias}.id
              AND (trim(coalesce(q.quote_text, '')) <> '' OR trim(coalesce(q.comment, '')) <> '')
        )
    )";
}
