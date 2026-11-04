<?php
/**
 * Claims: a user says "this found item is mine".
 * The person who posted the item approves or rejects each claim.
 */

declare(strict_types=1);

final class Claim
{
    public static function create(int $itemId, int $claimantId, string $message, string $proofAnswer): int
    {
        db()->prepare('INSERT INTO claims (item_id, claimant_id, message, proof_answer) VALUES (?, ?, ?, ?)')
            ->execute([$itemId, $claimantId, $message, $proofAnswer !== '' ? $proofAnswer : null]);
        return (int) db()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM claims WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** The current user's claim on an item (or null). */
    public static function byUserForItem(int $userId, int $itemId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM claims WHERE claimant_id = ? AND item_id = ?');
        $stmt->execute([$userId, $itemId]);
        return $stmt->fetch() ?: null;
    }

    /** All claims on an item, with claimant names (for the item owner). */
    public static function forItem(int $itemId): array
    {
        $stmt = db()->prepare(
            "SELECT cl.*, u.name AS claimant_name, u.email AS claimant_email
               FROM claims cl JOIN users u ON u.id = cl.claimant_id
              WHERE cl.item_id = ?
              ORDER BY FIELD(cl.status, 'pending', 'approved', 'rejected'), cl.created_at"
        );
        $stmt->execute([$itemId]);
        return $stmt->fetchAll();
    }

    /** Claims made by a user, with item info. */
    public static function forUser(int $userId): array
    {
        $stmt = db()->prepare(
            'SELECT cl.*, i.title AS item_title, i.status AS item_status,
                    (SELECT file_name FROM item_images im WHERE im.item_id = i.id ORDER BY im.is_primary DESC, im.id LIMIT 1) AS photo
               FROM claims cl JOIN items i ON i.id = cl.item_id
              WHERE cl.claimant_id = ?
              ORDER BY cl.created_at DESC, cl.id DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function countForUser(int $userId): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM claims WHERE claimant_id = ?');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    /** Pending claims waiting for this user's decision (on items they posted). */
    public static function pendingForOwner(int $ownerId): int
    {
        $stmt = db()->prepare(
            "SELECT COUNT(*) FROM claims cl JOIN items i ON i.id = cl.item_id
              WHERE i.user_id = ? AND cl.status = 'pending'"
        );
        $stmt->execute([$ownerId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Approve one claim: the item becomes "claimed" and every other
     * pending claim on it is rejected. Runs as one transaction.
     */
    public static function approve(array $claim, int $reviewerId): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE claims SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
                ->execute([$reviewerId, $claim['id']]);
            $pdo->prepare("UPDATE claims SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW()
                            WHERE item_id = ? AND id <> ? AND status = 'pending'")
                ->execute([$reviewerId, $claim['item_id'], $claim['id']]);
            Item::setStatus((int) $claim['item_id'], 'claimed');
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function reject(array $claim, int $reviewerId): void
    {
        db()->prepare("UPDATE claims SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
            ->execute([$reviewerId, $claim['id']]);
    }
}
