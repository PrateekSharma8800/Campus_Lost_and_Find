<?php
/**
 * Lost and found items.
 */

declare(strict_types=1);

final class Item
{
    public const PER_PAGE = 12;
    public const STATUSES = ['open', 'claimed', 'returned', 'closed'];

    /**
     * Columns used by list pages, joined with category/location names and the
     * first photo (primary photo first).
     */
    private const LIST_SELECT = "
        SELECT i.id, i.user_id, i.type, i.title, i.description, i.status, i.date_occurred, i.created_at,
               c.name AS category_name, c.icon AS category_icon, l.name AS location_name,
               (SELECT file_name FROM item_images im WHERE im.item_id = i.id
                 ORDER BY im.is_primary DESC, im.id LIMIT 1) AS photo
          FROM items i
          JOIN categories c ON c.id = i.category_id
          JOIN locations  l ON l.id = i.location_id";

    public static function create(int $userId, array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO items (user_id, type, title, description, category_id, location_id,
                                location_detail, date_occurred, secret_detail)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId, $data['type'], $data['title'], $data['description'],
            (int) $data['category_id'], (int) $data['location_id'],
            $data['location_detail'] !== '' ? $data['location_detail'] : null,
            $data['date_occurred'],
            $data['secret_detail'] !== '' ? $data['secret_detail'] : null,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE items SET title = ?, description = ?, category_id = ?, location_id = ?,
                              location_detail = ?, date_occurred = ?, secret_detail = ?
              WHERE id = ?'
        );
        $stmt->execute([
            $data['title'], $data['description'], (int) $data['category_id'], (int) $data['location_id'],
            $data['location_detail'] !== '' ? $data['location_detail'] : null,
            $data['date_occurred'],
            $data['secret_detail'] !== '' ? $data['secret_detail'] : null,
            $id,
        ]);
    }

    /** One item with category, location and poster details, or null. */
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT i.*, c.name AS category_name, c.icon AS category_icon,
                    l.name AS location_name, l.building AS location_building,
                    u.name AS owner_name
               FROM items i
               JOIN categories c ON c.id = i.category_id
               JOIN locations  l ON l.id = i.location_id
               JOIN users      u ON u.id = i.user_id
              WHERE i.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Browse/search with filters. Returns ['items' => [...], 'total' => n].
     * Filters: q (keyword), type, category, location, status, sort, page.
     */
    public static function search(array $filters): array
    {
        // Build the WHERE clause step by step; values always go in $params.
        $where  = ['i.is_approved = 1'];
        $params = [];

        if ($filters['q'] !== '') {
            // Escape LIKE wildcards so "%" or "_" typed by the user are matched literally.
            $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
            $where[] = '(i.title LIKE ? OR i.description LIKE ?)';
            array_push($params, $like, $like);
        }
        if ($filters['type'] !== '') {
            $where[] = 'i.type = ?';
            $params[] = $filters['type'];
        }
        if ($filters['category'] !== '') {
            $where[] = 'i.category_id = ?';
            $params[] = (int) $filters['category'];
        }
        if ($filters['location'] !== '') {
            $where[] = 'i.location_id = ?';
            $params[] = (int) $filters['location'];
        }
        if ($filters['status'] === 'all') {
            // no status filter
        } elseif ($filters['status'] !== '') {
            $where[] = 'i.status = ?';
            $params[] = $filters['status'];
        } else {
            $where[] = "i.status IN ('open', 'claimed')";   // default: still active items
        }

        $whereSql = ' WHERE ' . implode(' AND ', $where);

        $count = db()->prepare('SELECT COUNT(*) FROM items i' . $whereSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $order  = $filters['sort'] === 'oldest' ? 'i.created_at ASC, i.id ASC' : 'i.created_at DESC, i.id DESC';
        $offset = (max(1, (int) $filters['page']) - 1) * self::PER_PAGE;

        // LIMIT/OFFSET are integers we calculated ourselves, so they are safe to insert.
        $stmt = db()->prepare(self::LIST_SELECT . $whereSql . " ORDER BY $order LIMIT " . self::PER_PAGE . " OFFSET $offset");
        $stmt->execute($params);

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    /** Newest active items for the home page. */
    public static function latest(int $limit = 8): array
    {
        return db()->query(self::LIST_SELECT . " WHERE i.is_approved = 1 AND i.status = 'open'
                                                ORDER BY i.created_at DESC, i.id DESC LIMIT " . (int) $limit)->fetchAll();
    }

    /** All items posted by a user, with the number of pending claims on each. */
    public static function forUser(int $userId): array
    {
        $stmt = db()->prepare(self::LIST_SELECT . ' WHERE i.user_id = ? ORDER BY i.created_at DESC, i.id DESC');
        $stmt->execute([$userId]);
        $items = $stmt->fetchAll();

        // Add the number of claims still waiting for a decision.
        $countClaims = db()->prepare("SELECT COUNT(*) FROM claims WHERE item_id = ? AND status = 'pending'");
        foreach ($items as $index => $item) {
            $countClaims->execute([$item['id']]);
            $items[$index]['pending_claims'] = (int) $countClaims->fetchColumn();
        }
        return $items;
    }

    /** Dashboard numbers for one user. */
    public static function statsForUser(int $userId): array
    {
        $stmt = db()->prepare(
            "SELECT COALESCE(SUM(type = 'lost'), 0)  AS lost,
                    COALESCE(SUM(type = 'found'), 0) AS found,
                    COALESCE(SUM(status = 'returned'), 0) AS returned
               FROM items WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        return array_map('intval', $stmt->fetch());
    }

    public static function setStatus(int $id, string $status): void
    {
        db()->prepare('UPDATE items SET status = ? WHERE id = ?')->execute([$status, $id]);
    }

    /** Delete the item, its photos on disk, and (by CASCADE) its images/claims rows. */
    public static function delete(int $id): void
    {
        foreach (ItemImage::forItem($id) as $image) {
            ImageUpload::deleteFile($image['file_name']);
        }
        db()->prepare('DELETE FROM items WHERE id = ?')->execute([$id]);
    }
}
