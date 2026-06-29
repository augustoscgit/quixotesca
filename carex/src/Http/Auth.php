<?php

declare(strict_types=1);

namespace Carex\Http;

use Carex\Database\Connection;

final class Auth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        self::configureSessionStorage();

        session_set_cookie_params([
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);

        session_start();
    }

    private static function configureSessionStorage(): void
    {
        if (headers_sent() || session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $sessionDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'acesso' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'sessions';
        if (!is_dir($sessionDir)) {
            @mkdir($sessionDir, 0775, true);
        }

        if (!is_dir($sessionDir) || !is_writable($sessionDir)) {
            $fallbackDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . 'carex_sessions_' . substr(hash('sha256', dirname(__DIR__, 3)), 0, 12);
            if (!is_dir($fallbackDir)) {
                @mkdir($fallbackDir, 0700, true);
            }
            $sessionDir = $fallbackDir;
        }

        if (is_dir($sessionDir) && is_writable($sessionDir)) {
            ini_set('session.save_path', $sessionDir);
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', '14400');
    }

    public static function isAuthenticated(): bool
    {
        return self::currentUser() !== null;
    }

    public static function currentUser(): ?array
    {
        self::startSession();
        $acessoUserId = (int) ($_SESSION['_acesso_user_id'] ?? 0);
        if ($acessoUserId <= 0) {
            return null;
        }

        try {
            $config = require dirname(__DIR__) . '/bootstrap.php';
            $pdo = Connection::make($config['database']);

            $stmt = $pdo->prepare('SELECT id, name, email, username, status FROM acesso.users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $acessoUserId]);
            $user = $stmt->fetch();

            if (!$user || ($user['status'] ?? '') !== 'active') {
                unset($_SESSION['_acesso_user_id'], $_SESSION['_acesso_user_name'], $_SESSION['_acesso_user_email'], $_SESSION['_acesso_user_role']);
                return null;
            }

            $rolesStmt = $pdo->prepare('
                SELECT DISTINCT r.slug
                  FROM acesso.roles r
                  JOIN acesso.user_roles ur ON ur.role_id = r.id
                 WHERE ur.user_id = :user_id
                 ORDER BY r.slug
            ');
            $rolesStmt->execute(['user_id' => $acessoUserId]);
            $roles = array_map('strval', $rolesStmt->fetchAll(\PDO::FETCH_COLUMN));

            $permStmt = $pdo->prepare('
                SELECT DISTINCT p.slug
                  FROM acesso.permissions p
                  JOIN acesso.role_permissions rp ON rp.permission_id = p.id
                  JOIN acesso.user_roles ur ON ur.role_id = rp.role_id
                 WHERE ur.user_id = :user_id
                 ORDER BY p.slug
            ');
            $permStmt->execute(['user_id' => $acessoUserId]);
            $permissions = array_map('strval', $permStmt->fetchAll(\PDO::FETCH_COLUMN));

            $legacyAdmin = array_intersect(['platform.admin', 'acesso.admin', 'fichario.admin', 'ldrt.admin', 'carex.admin'], $permissions) !== [];
            $role = (in_array('admin', $roles, true) || $legacyAdmin)
                ? 'admin'
                : (in_array('user', $roles, true) || in_array('content.edit', $permissions, true) ? 'user' : 'public');

            $_SESSION['_acesso_user_name'] = (string) $user['name'];
            $_SESSION['_acesso_user_email'] = (string) $user['email'];
            $_SESSION['_acesso_user_role'] = $role;

            return [
                'id' => (int) $user['id'],
                'name' => (string) $user['name'],
                'email' => (string) $user['email'],
                'username' => (string) ($user['username'] ?? ''),
                'status' => (string) $user['status'],
                'role' => $role,
                '_roles' => $roles,
                '_permissions' => $permissions,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Updates the authenticated user's session payload after an administrative change.
     */
    public static function updateCurrentUser(array $user): void
    {
        $_SESSION['_acesso_user_name'] = (string) ($user['name'] ?? ($_SESSION['_acesso_user_name'] ?? ''));
        $_SESSION['_acesso_user_email'] = (string) ($user['email'] ?? ($_SESSION['_acesso_user_email'] ?? ''));
    }

    /**
     * Clears user session.
     */
    public static function logout(): void
    {
        self::startSession();
        unset($_SESSION['_acesso_user_id'], $_SESSION['_acesso_user_name'], $_SESSION['_acesso_user_email'], $_SESSION['_acesso_user_role']);
    }

    /**
     * Enforces authentication on web pages.
     */
    public static function requireLogin(): void
    {
        self::startSession();

        if (self::isAuthenticated()) {
            return;
        }

        // Save requested page to redirect back after login
        $next = $_SERVER['REQUEST_URI'] ?? 'matrizes.php';

        header('Location: ../acesso/login.php?next=' . rawurlencode($next));
        exit;
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();

        if ((self::currentUser()['role'] ?? 'public') === 'admin') {
            return;
        }

        http_response_code(403);
        exit('Acesso restrito para administradores.');
    }

    /**
     * Enforces authentication on API endpoints.
     */
    public static function requireApiLogin(): void
    {
        self::startSession();

        if (self::isAuthenticated()) {
            return;
        }

        Response::error('Não autorizado.', 401);
        exit;
    }

    public static function requireApiAdmin(): void
    {
        self::requireApiLogin();

        if ((self::currentUser()['role'] ?? 'public') === 'admin') {
            return;
        }

        Response::error('Forbidden', 403);
        exit;
    }
}
