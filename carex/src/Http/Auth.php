<?php

declare(strict_types=1);

namespace Carex\Http;

use Carex\Database\Connection;
use Carex\Database\UserRepository;
use RuntimeException;

final class Auth
{
    private const SESSION_USER_KEY = '_carex_auth_user';
    private const REMEMBER_COOKIE_NAME = '_carex_remember_token';

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        self::configureSessionStorage();

        session_set_cookie_params([
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

        $sessionDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'sessions';
        if (!is_dir($sessionDir)) {
            @mkdir($sessionDir, 0775, true);
        }

        if (!is_dir($sessionDir) || !is_writable($sessionDir)) {
            $fallbackDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . 'carex_sessions_' . substr(hash('sha256', dirname(__DIR__, 2)), 0, 12);
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
        self::startSession();
        return isset($_SESSION[self::SESSION_USER_KEY]) && is_array($_SESSION[self::SESSION_USER_KEY]);
    }

    public static function currentUser(): ?array
    {
        self::startSession();
        return $_SESSION[self::SESSION_USER_KEY] ?? null;
    }

    /**
     * Updates the authenticated user's session payload after an administrative change.
     */
    public static function updateCurrentUser(array $user): void
    {
        self::startSession();

        if (isset($_SESSION[self::SESSION_USER_KEY]) && is_array($_SESSION[self::SESSION_USER_KEY])) {
            $_SESSION[self::SESSION_USER_KEY] = array_merge($_SESSION[self::SESSION_USER_KEY], $user);
        }
    }

    /**
     * Authenticates the user in the session and optionally sets the remember cookie.
     */
    public static function login(array $user, bool $rememberMe = false): void
    {
        self::startSession();
        $_SESSION[self::SESSION_USER_KEY] = $user;

        if ($rememberMe) {
            try {
                $token = bin2hex(random_bytes(32));

                // Save remember token in DB
                $config = require dirname(__DIR__) . '/bootstrap.php';
                $config['database']['allow_writes'] = true; // Force write connection for auth updates
                $pdo = Connection::make($config['database']);
                $userRepo = new UserRepository($pdo);
                $userRepo->updateRememberToken((int) $user['id'], $token);

                // Set cookie
                setcookie(
                    self::REMEMBER_COOKIE_NAME,
                    $token,
                    [
                        'expires' => time() + 30 * 24 * 60 * 60, // 30 days
                        'path' => '/',
                        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]
                );
            } catch (\Throwable $error) {
                $_SESSION['_carex_remember_error'] = $error->getMessage();
            }
        }
    }

    /**
     * Clears user session and deletes the remember cookie.
     */
    public static function logout(): void
    {
        self::startSession();
        $user = self::currentUser();

        if ($user) {
            // Invalidate remember token in DB
            try {
                $config = require dirname(__DIR__) . '/bootstrap.php';
                $config['database']['allow_writes'] = true;
                $pdo = Connection::make($config['database']);
                $userRepo = new UserRepository($pdo);
                $userRepo->updateRememberToken((int) $user['id'], null);
            } catch (\Throwable $e) {
                // Ignore DB error on logout
            }
        }

        unset($_SESSION[self::SESSION_USER_KEY]);

        // Clear cookie
        if (isset($_COOKIE[self::REMEMBER_COOKIE_NAME])) {
            setcookie(
                self::REMEMBER_COOKIE_NAME,
                '',
                [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );
            unset($_COOKIE[self::REMEMBER_COOKIE_NAME]);
        }
    }

    /**
     * Enforces authentication on web pages.
     */
    public static function requireLogin(): void
    {
        self::startSession();

        if (self::isAuthenticated()) {
            $user = self::currentUser();
            
            // Check if status is suspended (desligado) in DB
            try {
                $config = require dirname(__DIR__) . '/bootstrap.php';
                $pdo = Connection::make($config['database']);
                $userRepo = new UserRepository($pdo);
                $dbUser = $userRepo->getUserById((int) $user['id']);

                if ($dbUser && $dbUser['status'] === 'desligado') {
                    self::logout();
                    header('Location: login.php?error=desligado');
                    exit;
                }

                if ($dbUser) {
                    $_SESSION[self::SESSION_USER_KEY] = $dbUser;
                }
            } catch (\Throwable $e) {
                // Allow proceeding if database fails during check
            }
            return;
        }

        // Try remember me re-authentication
        $cookieToken = $_COOKIE[self::REMEMBER_COOKIE_NAME] ?? '';
        if (is_string($cookieToken) && $cookieToken !== '') {
            try {
                $config = require dirname(__DIR__) . '/bootstrap.php';
                $config['database']['allow_writes'] = true; // writes allowed for token validation and updates
                $pdo = Connection::make($config['database']);
                $userRepo = new UserRepository($pdo);
                $user = $userRepo->getUserByRememberToken($cookieToken);

                if ($user) {
                    $_SESSION[self::SESSION_USER_KEY] = $user;
                    return;
                }
            } catch (RuntimeException $e) {
                // Suspended user detected by getUserByRememberToken
                self::logout();
                header('Location: login.php?error=desligado');
                exit;
            } catch (\Throwable $e) {
                // Other DB errors - delete corrupted cookie
                self::logout();
            }
        }

        // Save requested page to redirect back after login
        $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'] ?? 'matrizes.php';

        header('Location: login.php');
        exit;
    }

    /**
     * Enforces authentication on API endpoints.
     */
    public static function requireApiLogin(): void
    {
        self::startSession();

        if (self::isAuthenticated()) {
            $user = self::currentUser();
            try {
                $config = require dirname(__DIR__) . '/bootstrap.php';
                $pdo = Connection::make($config['database']);
                $userRepo = new UserRepository($pdo);
                $dbUser = $userRepo->getUserById((int) $user['id']);

                if ($dbUser && $dbUser['status'] === 'desligado') {
                    self::logout();
                    Response::error('Aviso de desligamento. Por favor, entre em contato com o administrador.', 401);
                    return;
                }

                if ($dbUser) {
                    $_SESSION[self::SESSION_USER_KEY] = $dbUser;
                }
            } catch (\Throwable $e) {
                // Fallback: assume valid if DB fails
            }
            return;
        }

        // Try remember me re-authentication
        $cookieToken = $_COOKIE[self::REMEMBER_COOKIE_NAME] ?? '';
        if (is_string($cookieToken) && $cookieToken !== '') {
            try {
                $config = require dirname(__DIR__) . '/bootstrap.php';
                $config['database']['allow_writes'] = true;
                $pdo = Connection::make($config['database']);
                $userRepo = new UserRepository($pdo);
                $user = $userRepo->getUserByRememberToken($cookieToken);

                if ($user) {
                    $_SESSION[self::SESSION_USER_KEY] = $user;
                    return;
                }
            } catch (RuntimeException $e) {
                self::logout();
                Response::error('Aviso de desligamento. Por favor, entre em contato com o administrador.', 401);
                return;
            } catch (\Throwable $e) {
                self::logout();
            }
        }

        Response::error('Não autorizado.', 401);
        exit;
    }
}
