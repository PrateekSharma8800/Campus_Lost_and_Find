<?php
declare(strict_types=1);

/** In-app notifications (shown to users in a later module). */
final class Notification
{
    public static function create(int $userId, string $type, string $message, string $link): void
    {
        db()->prepare('INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)')
            ->execute([$userId, $type, mb_substr($message, 0, 255), $link]);
    }
}
