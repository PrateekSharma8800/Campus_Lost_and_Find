<?php
/**
 * Reports: users flag an inappropriate item or a misbehaving user.
 * Admins review them (status: open -> resolved / dismissed).
 */

declare(strict_types=1);

final class Report
{
    /** Categories offered when reporting an item. */
    public const ITEM_REASONS = [
        'spam'          => 'Spam or advertising',
        'fake'          => 'Fake or misleading post',
        'inappropriate' => 'Inappropriate content',
        'duplicate'     => 'Duplicate post',
        'other'         => 'Something else',
    ];

    /** Categories offered when reporting a user. */
    public const USER_REASONS = [
        'suspicious' => 'Suspicious activity',
        'scam'       => 'Scam or false claim',
        'harassment' => 'Harassment or abuse',
        'other'      => 'Something else',
    ];

    public const STATUS_LABELS = ['open' => 'Under review', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'];

    public static function create(int $reporterId, ?int $itemId, ?int $reportedUserId, string $reason, string $details): void
    {
        db()->prepare('INSERT INTO reports (item_id, reported_user_id, reporter_id, reason, details) VALUES (?, ?, ?, ?, ?)')
            ->execute([$itemId, $reportedUserId, $reporterId, $reason, $details]);
    }

    /** Has this user already reported the item (ever) or the user (still open)? */
    public static function alreadyReported(int $reporterId, ?int $itemId, ?int $reportedUserId): bool
    {
        if ($itemId !== null) {
            $stmt = db()->prepare('SELECT 1 FROM reports WHERE reporter_id = ? AND item_id = ?');
            $stmt->execute([$reporterId, $itemId]);
        } else {
            $stmt = db()->prepare("SELECT 1 FROM reports WHERE reporter_id = ? AND item_id IS NULL
                                     AND reported_user_id = ? AND status = 'open'");
            $stmt->execute([$reporterId, $reportedUserId]);
        }
        return (bool) $stmt->fetchColumn();
    }

    /** Reports submitted by one user, with item title / reported user name. */
    public static function forReporter(int $reporterId): array
    {
        $stmt = db()->prepare(
            'SELECT r.*, i.title AS item_title, u.name AS reported_user_name
               FROM reports r
               LEFT JOIN items i ON i.id = r.item_id
               LEFT JOIN users u ON u.id = r.reported_user_id
              WHERE r.reporter_id = ?
              ORDER BY r.created_at DESC, r.id DESC'
        );
        $stmt->execute([$reporterId]);
        return $stmt->fetchAll();
    }

    // ---------- Used by the admin panel ----------

    /** All reports for admins; $status = 'open', 'resolved', 'dismissed' or '' for all. */
    public static function forAdmin(string $status = ''): array
    {
        $sql = 'SELECT r.*, i.title AS item_title, u.name AS reported_user_name, rep.name AS reporter_name
                  FROM reports r
                  LEFT JOIN items i ON i.id = r.item_id
                  LEFT JOIN users u ON u.id = r.reported_user_id
                  JOIN users rep    ON rep.id = r.reporter_id'
             . ($status !== '' ? ' WHERE r.status = ?' : '')
             . " ORDER BY FIELD(r.status, 'open', 'resolved', 'dismissed'), r.created_at DESC";
        $stmt = db()->prepare($sql);
        $stmt->execute($status !== '' ? [$status] : []);
        return $stmt->fetchAll();
    }

    public static function countOpen(): int
    {
        return (int) db()->query("SELECT COUNT(*) FROM reports WHERE status = 'open'")->fetchColumn();
    }

    public static function setStatus(int $id, string $status, int $adminId): void
    {
        db()->prepare('UPDATE reports SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?')
            ->execute([$status, $adminId, $id]);
    }

    /** Human label for a reason code. */
    public static function reasonLabel(string $reason): string
    {
        return self::ITEM_REASONS[$reason] ?? self::USER_REASONS[$reason] ?? ucfirst($reason);
    }
}
