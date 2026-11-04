<?php
/**
 * Authentication: who is logged in, logging in and out.
 *
 * The session stores only the user ID and login time. On every request the
 * user is re-loaded from the database, so deactivating an account or
 * resetting its password takes effect immediately on all sessions.
 */

declare(strict_types=1);

final class Auth
{
    /**
     * Real bcrypt hash of a random string. Verified against when the email
     * doesn't exist, so "unknown email" and "wrong password" take the same
     * time and can't be told apart by timing.
     */
    private const DUMMY_HASH = '$2y$12$TxMF3wYGzCcoSXSaMXMXjOPInII91CYwVDrTz7fQPAcbxE8Qe7vCy';

    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;

        $id = Session::get('user_id');
        if (!is_int($id)) {
            return null;
        }

        $user = User::findById($id);
        $authAt = (int) Session::get('auth_at', 0);

        $invalid = $user === null
            || !$user['is_active']
            || ($user['password_changed_at'] !== null && strtotime($user['password_changed_at'] . ' UTC') > $authAt);

        if ($invalid) {
            self::logout();
            Session::flash('warning', 'You have been signed out. Please log in again.');
            return null;
        }

        return self::$user = $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        return self::user()['id'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? null) === 'admin';
    }

    /**
     * Check credentials with rate limiting.
     * @return array{status: 'ok'|'failed'|'locked'|'inactive', user?: array, retry_after?: int}
     */
    public static function attempt(string $email, string $password, string $ip): array
    {
        $email = mb_strtolower(trim($email));

        $locked = RateLimiter::lockedFor($email, $ip);
        if ($locked > 0) {
            return ['status' => 'locked', 'retry_after' => $locked];
        }

        $user = User::findByEmail($email);
        $valid = password_verify($password, $user['password_hash'] ?? self::DUMMY_HASH) && $user !== null;

        if (!$valid) {
            RateLimiter::hit($email, $ip);
            return ['status' => 'failed'];
        }

        if (!$user['is_active']) {
            return ['status' => 'inactive'];
        }

        RateLimiter::clear($email);

        // Upgrade the hash transparently if PHP's default cost/algorithm changed.
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            User::rehashPassword($user['id'], $password);
        }

        return ['status' => 'ok', 'user' => $user];
    }

    public static function login(array $user): void
    {
        Session::regenerate();   // new session ID: prevents session fixation
        Csrf::rotate();
        Session::set('user_id', (int) $user['id']);
        Session::set('auth_at', time());
        User::touchLogin((int) $user['id']);
        self::$user = $user;
        self::$loaded = true;
    }

    public static function logout(): void
    {
        Session::destroy();
        Session::start();        // fresh, empty session (for the flash message)
        Csrf::rotate();
        self::$user = null;
        self::$loaded = true;
    }
}
