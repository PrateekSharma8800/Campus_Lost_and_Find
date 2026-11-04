<?php
/**
 * Login rate limiting backed by the login_attempts table.
 *
 *  - 5 failed attempts per email in 15 minutes  -> that email is locked
 *  - 20 failed attempts per IP in 15 minutes    -> that IP is locked
 *  - A successful login clears the email's failures.
 *  The lock is checked BEFORE the password, so even the correct password
 *  is refused while locked (otherwise guessing could continue).
 */

declare(strict_types=1);

final class RateLimiter
{
    public const MAX_PER_EMAIL  = 5;
    public const MAX_PER_IP     = 20;
    public const WINDOW_MINUTES = 15;

    /** Seconds until the caller may try again; 0 means not locked. */
    public static function lockedFor(string $email, string $ip): int
    {
        return max(
            self::secondsUntilUnlock('email', $email, self::MAX_PER_EMAIL),
            self::secondsUntilUnlock('ip_address', $ip, self::MAX_PER_IP)
        );
    }

    public static function hit(string $email, string $ip): void
    {
        db()->prepare('INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)')
            ->execute([mb_substr($email, 0, 190), $ip]);

        // Occasionally purge old rows so the table stays small.
        if (random_int(1, 50) === 1) {
            db()->exec('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');
        }
    }

    public static function clear(string $email): void
    {
        db()->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$email]);
    }

    /**
     * The lock lifts when the Nth most recent failure leaves the window,
     * so look at exactly that row.
     */
    private static function secondsUntilUnlock(string $column, string $value, int $max): int
    {
        // $column and the integers are constants from this class, never user input.
        $sql = sprintf(
            'SELECT TIMESTAMPDIFF(SECOND, NOW(), attempted_at + INTERVAL %d MINUTE)
               FROM login_attempts
              WHERE %s = ? AND attempted_at > NOW() - INTERVAL %d MINUTE
              ORDER BY attempted_at DESC, id DESC
              LIMIT 1 OFFSET %d',
            self::WINDOW_MINUTES, $column, self::WINDOW_MINUTES, $max - 1
        );
        $stmt = db()->prepare($sql);
        $stmt->execute([$value]);
        $seconds = $stmt->fetchColumn();
        return $seconds === false ? 0 : max(1, (int) $seconds);
    }
}
