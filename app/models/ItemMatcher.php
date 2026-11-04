<?php
/**
 * Possible Matches: a simple, rule-based score between a lost item and
 * found items (or the other way round). No AI, just clear rules:
 *
 *   Same category ...................... 40 points
 *   Same campus location ............... 20 points
 *   Shared title words ................. 10 each, max 25
 *   Shared description words ........... 2 each,  max 10
 *   Dates close together ............... 5 (within 3 days) or 3 (within 7 days)
 *                                        ---------
 *                                        max 100
 *
 * Only OPEN, visible items of the OPPOSITE type, posted by someone else,
 * within 30 days of each other are compared. Matches are calculated live,
 * so deleted, closed or returned items simply stop appearing.
 */

declare(strict_types=1);

final class ItemMatcher
{
    public const MIN_SCORE   = 45;   // below this a suggestion is not shown
    public const MAX_RESULTS = 4;
    public const DAY_WINDOW  = 30;

    /** Common words that say nothing about an item. */
    private const STOP_WORDS = ['the', 'and', 'with', 'for', 'near', 'has', 'have', 'was', 'are', 'found', 'lost',
        'item', 'from', 'this', 'that', 'some', 'inside', 'my', 'its', 'in', 'on', 'at', 'of', 'a', 'an', 'is', 'it', 'to'];

    /** Best matches for an item: list of ['item' => row, 'score' => int, 'reasons' => [..]] */
    public static function findFor(array $item): array
    {
        if ($item['status'] !== 'open' || !$item['is_approved']) {
            return [];   // nothing to match for closed/returned/hidden items
        }

        $oppositeType = $item['type'] === 'lost' ? 'found' : 'lost';
        $stmt = db()->prepare(
            "SELECT i.id, i.user_id, i.type, i.title, i.description, i.category_id, i.location_id,
                    i.date_occurred, i.status, i.created_at,
                    c.name AS category_name, c.icon AS category_icon, l.name AS location_name,
                    (SELECT file_name FROM item_images im WHERE im.item_id = i.id
                      ORDER BY im.is_primary DESC, im.id LIMIT 1) AS photo
               FROM items i
               JOIN categories c ON c.id = i.category_id
               JOIN locations  l ON l.id = i.location_id
              WHERE i.type = ? AND i.status = 'open' AND i.is_approved = 1
                AND i.user_id <> ? AND i.id <> ?
                AND ABS(DATEDIFF(i.date_occurred, ?)) <= " . self::DAY_WINDOW . '
              LIMIT 200'
        );
        $stmt->execute([$oppositeType, $item['user_id'], $item['id'], $item['date_occurred']]);

        $matches = [];
        foreach ($stmt->fetchAll() as $candidate) {
            $result = self::score($item, $candidate);
            if ($result['score'] >= self::MIN_SCORE) {
                $matches[] = ['item' => $candidate] + $result;
            }
        }

        // Highest score first; newest first when scores are equal.
        usort($matches, fn ($a, $b) => [$b['score'], $b['item']['id']] <=> [$a['score'], $a['item']['id']]);
        return array_slice($matches, 0, self::MAX_RESULTS);
    }

    /** Score two items and explain why. Returns ['score' => int, 'reasons' => string[]] */
    public static function score(array $a, array $b): array
    {
        $score = 0;
        $reasons = [];

        if ((int) $a['category_id'] === (int) $b['category_id']) {
            $score += 40;
            $reasons[] = 'Same category';
        }
        if ((int) $a['location_id'] === (int) $b['location_id']) {
            $score += 20;
            $reasons[] = 'Same location';
        }

        $titleWords = array_intersect(self::keywords($a['title']), self::keywords($b['title']));
        if ($titleWords) {
            $score += min(25, 10 * count($titleWords));
            $reasons[] = 'Title: ' . implode(', ', array_slice($titleWords, 0, 3));
        }

        $descriptionWords = array_intersect(self::keywords($a['description']), self::keywords($b['description']));
        if ($descriptionWords) {
            $score += min(10, 2 * count($descriptionWords));
            $reasons[] = count($descriptionWords) . ' similar description word' . (count($descriptionWords) > 1 ? 's' : '');
        }

        $days = abs((strtotime($a['date_occurred']) - strtotime($b['date_occurred'])) / 86400);
        if ($days <= 3) {
            $score += 5;
            $reasons[] = $days == 0 ? 'Same day' : 'Within ' . (int) $days . ' day' . ($days > 1 ? 's' : '');
        } elseif ($days <= 7) {
            $score += 3;
            $reasons[] = 'Within a week';
        }

        return ['score' => $score, 'reasons' => $reasons];
    }

    /** Lower-case meaningful words: "Black Leather Wallet!" -> ['black', 'leather', 'wallet'] */
    public static function keywords(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        $words = array_filter($words, fn ($word) => mb_strlen($word) >= 3 && !in_array($word, self::STOP_WORDS, true));
        return array_values(array_unique($words));
    }
}
