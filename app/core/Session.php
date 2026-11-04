<?php
/**
 * Hardened PHP session handling.
 *
 *  - Cookie: HttpOnly (no JS access), SameSite=Lax, Secure on HTTPS, no
 *    fixed lifetime (cleared when the browser closes).
 *  - Strict mode: PHP refuses session IDs it did not create (stops
 *    session fixation with made-up IDs).
 *  - Session ID only via cookie, never in URLs.
 *  - Idle timeout and absolute lifetime.
 *  - Session files stored in storage/sessions/ (outside the web root).
 */

declare(strict_types=1);

final class Session
{
    public const NAME             = 'campusfind_session';
    public const IDLE_TIMEOUT     = 7200;   // 2 hours without activity
    public const ABSOLUTE_TIMEOUT = 43200;  // 12 hours max, however active

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) self::IDLE_TIMEOUT);

        $path = STORAGE_PATH . '/sessions';
        if (is_dir($path) && is_writable($path)) {
            session_save_path($path);
        }

        session_name(self::NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $now = time();
        if (self::isExpired($_SESSION, $now)) {
            $wasLoggedIn = isset($_SESSION['user_id']);
            self::destroy();
            session_start();
            if ($wasLoggedIn) {
                self::flash('warning', 'Your session expired. Please log in again.');
            }
        }

        $_SESSION['_created']       ??= $now;
        $_SESSION['_last_activity']   = $now;
    }

    /** Pure function so timeouts can be unit tested. */
    public static function isExpired(array $session, int $now): bool
    {
        if (isset($session['_last_activity']) && $now - (int) $session['_last_activity'] > self::IDLE_TIMEOUT) {
            return true;
        }
        if (isset($session['_created']) && $now - (int) $session['_created'] > self::ABSOLUTE_TIMEOUT) {
            return true;
        }
        return false;
    }

    /** New session ID, same data: call on login and privilege changes. */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'],
            ]);
            session_destroy();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        return $value;
    }

    /** One-time message shown on the next page (success, danger, warning, info). */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function pullFlash(): array
    {
        return self::pull('_flash', []);
    }
}
