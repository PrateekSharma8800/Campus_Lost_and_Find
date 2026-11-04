<?php
/**
 * Private messages. A conversation = one item + two users
 * (one of them is always the person who posted the item).
 */

declare(strict_types=1);

final class Message
{
    public const MAX_LENGTH = 2000;

    public static function send(int $itemId, int $senderId, int $receiverId, string $body): void
    {
        db()->prepare('INSERT INTO messages (item_id, sender_id, receiver_id, body) VALUES (?, ?, ?, ?)')
            ->execute([$itemId, $senderId, $receiverId, $body]);
    }

    /** All messages between two users about one item, oldest first. */
    public static function thread(int $itemId, int $userA, int $userB): array
    {
        $stmt = db()->prepare(
            'SELECT id, sender_id, receiver_id, body, read_at, created_at
               FROM messages
              WHERE item_id = ?
                AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
              ORDER BY created_at, id'
        );
        $stmt->execute([$itemId, $userA, $userB, $userB, $userA]);
        return $stmt->fetchAll();
    }

    public static function conversationExists(int $itemId, int $userA, int $userB): bool
    {
        $stmt = db()->prepare(
            'SELECT 1 FROM messages
              WHERE item_id = ? AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
              LIMIT 1'
        );
        $stmt->execute([$itemId, $userA, $userB, $userB, $userA]);
        return (bool) $stmt->fetchColumn();
    }

    /** Mark everything the other person sent me in this conversation as read. */
    public static function markRead(int $itemId, int $otherUserId, int $myId): void
    {
        db()->prepare('UPDATE messages SET read_at = NOW()
                        WHERE item_id = ? AND sender_id = ? AND receiver_id = ? AND read_at IS NULL')
            ->execute([$itemId, $otherUserId, $myId]);
    }

    /** Number of unread messages for the navbar badge. */
    public static function unreadCount(int $userId): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND read_at IS NULL');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * The user's conversations (newest first) with the last message,
     * the other person, the item and the unread count.
     */
    public static function conversationsFor(int $userId): array
    {
        // Inner query: one row per (item, other person), with the id of the newest message.
        $sql = '
            SELECT c.item_id, c.other_id, c.unread,
                   last.body AS last_body, last.sender_id AS last_sender_id, last.created_at AS last_at,
                   i.title AS item_title, i.type AS item_type, i.category_id,
                   u.name AS other_name,
                   (SELECT file_name FROM item_images im WHERE im.item_id = i.id
                     ORDER BY im.is_primary DESC, im.id LIMIT 1) AS photo
              FROM (SELECT item_id,
                           IF(sender_id = ?, receiver_id, sender_id) AS other_id,
                           MAX(id) AS last_id,
                           SUM(receiver_id = ? AND read_at IS NULL) AS unread
                      FROM messages
                     WHERE sender_id = ? OR receiver_id = ?
                     GROUP BY item_id, other_id) AS c
              JOIN messages last ON last.id = c.last_id
              JOIN items i       ON i.id = c.item_id
              JOIN users u       ON u.id = c.other_id
             ORDER BY c.last_id DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute([$userId, $userId, $userId, $userId]);
        return $stmt->fetchAll();
    }
}
