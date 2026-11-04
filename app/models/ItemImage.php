<?php
declare(strict_types=1);

/** Photos attached to an item (files are stored in storage/uploads/items/). */
final class ItemImage
{
    public static function forItem(int $itemId): array
    {
        $stmt = db()->prepare('SELECT * FROM item_images WHERE item_id = ? ORDER BY is_primary DESC, id');
        $stmt->execute([$itemId]);
        return $stmt->fetchAll();
    }

    public static function countForItem(int $itemId): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM item_images WHERE item_id = ?');
        $stmt->execute([$itemId]);
        return (int) $stmt->fetchColumn();
    }

    public static function add(int $itemId, array $saved, bool $isPrimary): void
    {
        db()->prepare('INSERT INTO item_images (item_id, file_name, mime_type, file_size, is_primary) VALUES (?, ?, ?, ?, ?)')
            ->execute([$itemId, $saved['file_name'], $saved['mime_type'], $saved['file_size'], (int) $isPrimary]);
    }

    /** Photo row plus whether its item is publicly visible. */
    public static function findByFileName(string $fileName): ?array
    {
        $stmt = db()->prepare(
            'SELECT im.*, i.user_id, i.is_approved FROM item_images im JOIN items i ON i.id = im.item_id WHERE im.file_name = ?'
        );
        $stmt->execute([$fileName]);
        return $stmt->fetch() ?: null;
    }

    /** Delete one photo of an item (file + row). Makes another photo primary if needed. */
    public static function delete(int $itemId, int $imageId): void
    {
        $stmt = db()->prepare('SELECT file_name FROM item_images WHERE id = ? AND item_id = ?');
        $stmt->execute([$imageId, $itemId]);
        $fileName = $stmt->fetchColumn();
        if ($fileName === false) {
            return;   // not this item's photo: ignore
        }
        ImageUpload::deleteFile($fileName);
        db()->prepare('DELETE FROM item_images WHERE id = ?')->execute([$imageId]);

        // If we deleted the main (primary) photo, make the oldest remaining photo the main one.
        $check = db()->prepare('SELECT COUNT(*) FROM item_images WHERE item_id = ? AND is_primary = 1');
        $check->execute([$itemId]);
        if ((int) $check->fetchColumn() === 0) {
            db()->prepare('UPDATE item_images SET is_primary = 1 WHERE item_id = ? ORDER BY id LIMIT 1')
                ->execute([$itemId]);
        }
    }
}
