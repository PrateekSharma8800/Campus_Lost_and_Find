<?php
/**
 * User model. All queries use prepared statements.
 */

declare(strict_types=1);

final class User
{
    /** Shared validation rules (web registration, password reset, CLI). */
    public const NAME_RULES     = ['required', 'min:2', 'max:100', 'regex:/^[^\x00-\x1F\x7F<>]+$/u'];
    public const EMAIL_RULES    = ['required', 'email', 'max:190'];
    /** bcrypt only uses the first 72 bytes, so longer passwords are refused rather than silently cut. */
    public const PASSWORD_RULES = ['required', 'min:8', 'max_bytes:72', 'password'];

    private const COLUMNS = 'id, name, email, password_hash, phone, student_id, role, is_active,
                             email_verified_at, password_changed_at, last_login_at, created_at';

    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare('SELECT ' . self::COLUMNS . ' FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = db()->prepare('SELECT ' . self::COLUMNS . ' FROM users WHERE email = ?');
        $stmt->execute([self::normalizeEmail($email)]);
        return $stmt->fetch() ?: null;
    }

    public static function emailExists(string $email): bool
    {
        $stmt = db()->prepare('SELECT 1 FROM users WHERE email = ?');
        $stmt->execute([self::normalizeEmail($email)]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * @param array{name:string, email:string, password:string, phone?:?string, student_id?:?string, role?:string} $data
     * @throws PDOException 23000 on duplicate email (race between check and insert)
     */
    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO users (name, email, password_hash, phone, student_id, role, email_verified_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $role = $data['role'] ?? 'student';
        $stmt->execute([
            $data['name'],
            self::normalizeEmail($data['email']),
            password_hash($data['password'], PASSWORD_DEFAULT),
            ($data['phone'] ?? '') !== '' ? $data['phone'] : null,
            ($data['student_id'] ?? '') !== '' ? $data['student_id'] : null,
            $role,
            $role === 'admin' ? gmdate('Y-m-d H:i:s') : null,
        ]);
        return (int) db()->lastInsertId();
    }

    /** Set a new password and mark the change time (logs out older sessions). */
    public static function updatePassword(int $id, string $password): void
    {
        db()->prepare('UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    /** Same password, stronger hash. Does NOT log anyone out. */
    public static function rehashPassword(int $id, string $password): void
    {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    public static function promoteToAdmin(int $id): void
    {
        db()->prepare("UPDATE users SET role = 'admin', is_active = 1,
                       email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?")
            ->execute([$id]);
    }

    public static function touchLogin(int $id): void
    {
        db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$id]);
    }

    /** @return array{total:int, students:int, staff:int, admins:int} */
    public static function roleCounts(): array
    {
        $row = db()->query("SELECT COUNT(*) AS total,
                                   COALESCE(SUM(role = 'student'), 0) AS students,
                                   COALESCE(SUM(role = 'staff'), 0)   AS staff,
                                   COALESCE(SUM(role = 'admin'), 0)   AS admins
                              FROM users")->fetch();
        return array_map('intval', $row);
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
