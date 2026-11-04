<?php
declare(strict_types=1);

/** In-app notifications (claim received / approved / rejected ...). */
final class Notification
{
    /** Icon and colour for each notification type (used by the views). */
    public const STYLES = [
        'claim_received' => ['bi-patch-question', 'amber'],
        'claim_approved' => ['bi-patch-check', 'green'],
        'claim_rejected' => ['bi-x-circle', 'rose'],
    ];

    public static function create(int $userId, string $type, string $message, string $link): void
    {
        db()->prepare('INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)')
            ->execute([$userId, $type, mb_substr($message, 0, 255), $link]);
    }

    /** Newest notifications for a user; $onlyUnread hides the ones already read. */
    public static function forUser(int $userId, bool $onlyUnread = false, int $limit = 50): array
    {
        $sql = 'SELECT * FROM notifications WHERE user_id = ?'
             . ($onlyUnread ? ' AND read_at IS NULL' : '')
             . ' ORDER BY created_at DESC, id DESC LIMIT ' . (int) $limit;
        $stmt = db()->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function unreadCount(int $userId): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    /** A notification only if it belongs to this user (so users can't touch others' notifications). */
    public static function findForUser(int $id, int $userId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM notifications WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function markRead(int $id, int $userId): void
    {
        db()->prepare('UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ? AND read_at IS NULL')
            ->execute([$id, $userId]);
    }

    public static function markAllRead(int $userId): void
    {
        db()->prepare('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL')
            ->execute([$userId]);
    }
}
