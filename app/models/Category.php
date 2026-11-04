<?php
declare(strict_types=1);

/** Item categories (Electronics, Keys, ...). */
final class Category
{
    public static function all(): array
    {
        return db()->query('SELECT id, name, slug, icon FROM categories ORDER BY name')->fetchAll();
    }

    public static function exists(int $id): bool
    {
        $stmt = db()->prepare('SELECT 1 FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }
}
