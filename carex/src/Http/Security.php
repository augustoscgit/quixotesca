<?php

declare(strict_types=1);

namespace Carex\Http;

final class Security
{
    private const CSRF_SESSION_KEY = '_carex_csrf_token';

    public static function applyHeaders(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data:; font-src 'self' https://cdn.jsdelivr.net data:; connect-src 'self'");
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    }

    public static function allowReadOnlyRequest(): void
    {
        self::allowMethods(['GET', 'HEAD']);
    }

    /**
     * @param array<int, string> $methods
     */
    public static function allowMethods(array $methods): void
    {
        $allowed = array_values(array_unique(array_map('strtoupper', $methods)));
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (!in_array($method, $allowed, true)) {
            http_response_code(405);
            header('Allow: ' . implode(', ', $allowed));
            exit;
        }
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function csrfToken(): string
    {
        self::startSession();

        if (empty($_SESSION[self::CSRF_SESSION_KEY]) || !is_string($_SESSION[self::CSRF_SESSION_KEY])) {
            $_SESSION[self::CSRF_SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::CSRF_SESSION_KEY];
    }

    public static function isValidCsrfToken(mixed $token): bool
    {
        self::startSession();

        return is_string($token)
            && isset($_SESSION[self::CSRF_SESSION_KEY])
            && is_string($_SESSION[self::CSRF_SESSION_KEY])
            && hash_equals($_SESSION[self::CSRF_SESSION_KEY], $token);
    }

    private static function startSession(): void
    {
        Auth::startSession();
    }
}
