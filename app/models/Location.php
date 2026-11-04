<?php
declare(strict_types=1);

/** Campus locations (Library, Cafeteria, ...). */
final class Location
{
    public static function all(): array
    {
        return db()->query('SELECT id, name, building FROM locations ORDER BY name')->fetchAll();
    }

    public static function exists(int $id): bool
    {
        $stmt = db()->prepare('SELECT 1 FROM locations WHERE id = ?');
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }
}
