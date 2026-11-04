<?php
/**
 * Password-reset tokens.
 *
 *  - 32 random bytes (64 hex chars) from random_bytes().
 *  - Only the SHA-256 hash is stored; the raw token exists only in the email.
 *  - Valid for 60 minutes, single use; a new request replaces older tokens.
 */

declare(strict_types=1);

final class PasswordReset
{
    public const TTL_MINUTES      = 60;
    public const THROTTLE_SECONDS = 60;  // at most one email per user per minute

    /** Create a token and return the raw value (to put in the email link). */
    public static function create(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$userId]);
            $pdo->prepare(sprintf(
                'INSERT INTO password_resets (user_id, token_hash, expires_at)
                 VALUES (?, ?, NOW() + INTERVAL %d MINUTE)', self::TTL_MINUTES
            ))->execute([$userId, hash('sha256', $token)]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return $token;
    }

    public static function recentlyRequested(int $userId): bool
    {
        $stmt = db()->prepare(sprintf(
            'SELECT 1 FROM password_resets WHERE user_id = ? AND created_at > NOW() - INTERVAL %d SECOND',
            self::THROTTLE_SECONDS
        ));
        $stmt->execute([$userId]);
        return (bool) $stmt->fetchColumn();
    }

    /** The reset row joined with its (active) user, or null if invalid/expired/used. */
    public static function findValid(string $token): ?array
    {
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            return null;
        }
        $stmt = db()->prepare(
            'SELECT pr.id, pr.user_id, u.email
               FROM password_resets pr
               JOIN users u ON u.id = pr.user_id
              WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW() AND u.is_active = 1'
        );
        $stmt->execute([hash('sha256', strtolower($token))]);
        return $stmt->fetch() ?: null;
    }

    /** Mark used and remove every other token for the user. */
    public static function consume(int $id, int $userId): void
    {
        db()->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')->execute([$id]);
        db()->prepare('DELETE FROM password_resets WHERE user_id = ? AND id <> ?')->execute([$userId, $id]);
    }
}
