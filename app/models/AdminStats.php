<?php
/**
 * Numbers and chart data for the admin dashboard.
 */

declare(strict_types=1);

final class AdminStats
{
    /** Headline numbers for the stat cards. */
    public static function totals(): array
    {
        $row = db()->query(
            "SELECT (SELECT COUNT(*) FROM users)                                  AS users,
                    (SELECT COUNT(*) FROM users WHERE is_active = 0)              AS inactive_users,
                    (SELECT COUNT(*) FROM items)                                  AS items,
                    (SELECT COUNT(*) FROM items WHERE type = 'lost')              AS lost,
                    (SELECT COUNT(*) FROM items WHERE type = 'found')             AS found,
                    (SELECT COUNT(*) FROM items WHERE status = 'returned')        AS returned,
                    (SELECT COUNT(*) FROM items WHERE is_approved = 0)            AS hidden,
                    (SELECT COUNT(*) FROM reports WHERE status = 'open')          AS open_reports,
                    (SELECT COUNT(*) FROM claims WHERE status = 'pending')        AS pending_claims"
        )->fetch();
        return array_map('intval', $row);
    }

    /**
     * Lost and found items posted per day for the last $days days (oldest first).
     * Days without posts are filled with 0 so the chart has no gaps.
     */
    public static function itemsPerDay(int $days = 14): array
    {
        $stmt = db()->prepare(
            'SELECT DATE(created_at) AS day, type, COUNT(*) AS total
               FROM items
              WHERE created_at >= CURDATE() - INTERVAL ? DAY
              GROUP BY day, type'
        );
        $stmt->execute([$days - 1]);

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $series[gmdate('Y-m-d', strtotime("-$i days"))] = ['lost' => 0, 'found' => 0];
        }
        foreach ($stmt->fetchAll() as $row) {
            if (isset($series[$row['day']])) {
                $series[$row['day']][$row['type']] = (int) $row['total'];
            }
        }
        return $series;
    }

    /** Number of items in each category (largest first). */
    public static function itemsByCategory(): array
    {
        return db()->query(
            'SELECT c.name, COUNT(i.id) AS total
               FROM categories c LEFT JOIN items i ON i.category_id = c.id
              GROUP BY c.id, c.name
              ORDER BY total DESC, c.name'
        )->fetchAll();
    }

    /** Number of items per status, in a fixed order. */
    public static function itemsByStatus(): array
    {
        $counts = array_fill_keys(Item::STATUSES, 0);
        foreach (db()->query('SELECT status, COUNT(*) AS total FROM items GROUP BY status')->fetchAll() as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }
        return $counts;
    }

    /** Latest events across the site: new users, items, claims and reports. */
    public static function recentActivity(int $limit = 8): array
    {
        return db()->query(
            "(SELECT 'user' AS kind, name AS label, '/admin/users' AS link, created_at FROM users)
             UNION ALL
             (SELECT CONCAT('item_', type), title, CONCAT('/items/', id), created_at FROM items)
             UNION ALL
             (SELECT 'claim', i.title, CONCAT('/items/', i.id), cl.created_at FROM claims cl JOIN items i ON i.id = cl.item_id)
             UNION ALL
             (SELECT 'report', reason, CONCAT('/admin/reports/', id), created_at FROM reports)
             ORDER BY created_at DESC
             LIMIT " . (int) $limit
        )->fetchAll();
    }
}
