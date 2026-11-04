<?php
/**
 * CSRF protection (synchronizer token pattern).
 *
 * One random token per session, embedded in every form as _token and
 * compared in constant time on every POST (see Router::dispatch).
 * The token is rotated on login and logout.
 */

declare(strict_types=1);

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verify(?string $token): bool
    {
        return is_string($token)
            && isset($_SESSION['_csrf'])
            && is_string($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $token);
    }

    public static function rotate(): void
    {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
}
