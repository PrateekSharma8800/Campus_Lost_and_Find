<?php
/** Possible Matches tests: scoring rules, filtering and the item page. */

declare(strict_types=1);

$makeUser = function (string $email): int {
    return User::create(['name' => 'Match ' . strtok($email, '@'), 'email' => $email, 'password' => 'campus123']);
};
$makeItem = function (int $userId, string $type, string $title, array $extra = []): int {
    return Item::create($userId, $extra + [
        'type' => $type, 'title' => $title, 'description' => 'Black leather wallet with college ID card',
        'category_id' => 6, 'location_id' => 1, 'location_detail' => '', 'date_occurred' => gmdate('Y-m-d'), 'secret_detail' => '',
    ]);
};
$matchIds = fn (int $itemId) => array_map(fn ($m) => (int) $m['item']['id'], ItemMatcher::findFor(Item::find($itemId)));

$loser  = $makeUser('loser@match.test');
$finder = $makeUser('finder@match.test');
$lostId = $makeItem($loser, 'lost', 'Black leather wallet');

test('Matcher: keywords drop short and common words', function () {
    assert_same(['black', 'leather', 'wallet', 'card'], ItemMatcher::keywords('The Black leather WALLET, found near ID card!'));
});

test('Matcher: score is transparent and adds up as documented', function () {
    $a = ['category_id' => 6, 'location_id' => 1, 'title' => 'Black leather wallet', 'description' => 'brown strap cards', 'date_occurred' => '2026-11-01'];
    $b = ['category_id' => 6, 'location_id' => 1, 'title' => 'Leather wallet found', 'description' => 'cards and strap', 'date_occurred' => '2026-11-03'];
    $result = ItemMatcher::score($a, $b);
    assert_same(40 + 20 + 20 + 4 + 5, $result['score']);   // category + location + 2 title words + 2 description words + 2 days
    assert_same(['Same category', 'Same location', 'Title: leather, wallet', '2 similar description words', 'Within 2 days'], $result['reasons']);
    $unrelated = ['category_id' => 1, 'location_id' => 2, 'title' => 'Laptop charger', 'description' => 'white cable', 'date_occurred' => '2026-10-20'];
    assert_same(0, ItemMatcher::score($a, $unrelated)['score']);
});

test('Matcher: finds an opposite-type match and ranks the best first', function () use ($makeItem, $finder, $lostId, $matchIds) {
    $good = $makeItem($finder, 'found', 'Leather wallet black');
    $weak = $makeItem($finder, 'found', 'Purse', ['location_id' => 3, 'description' => 'small purse']);   // category only = 40+5 → shown last
    $ids = $matchIds($lostId);
    assert_same($good, $ids[0]);
    // Category + same day only = 45: just enough to count as a match.
    assert_same(ItemMatcher::MIN_SCORE, ItemMatcher::score(Item::find($lostId), Item::find($weak))['score']);
    assert_true(in_array($lostId, $matchIds($good), true), 'match works both ways');
});

test('Matcher: ignores irrelevant, same-type, own, old and hidden items', function () use ($makeItem, $finder, $loser, $lostId, $matchIds) {
    $irrelevant = $makeItem($finder, 'found', 'Laptop charger', ['category_id' => 1, 'location_id' => 4, 'description' => 'white usb cable']);
    $sameType   = $makeItem($finder, 'lost', 'Black leather wallet');
    $own        = $makeItem($loser, 'found', 'Black leather wallet');
    $tooOld     = $makeItem($finder, 'found', 'Black leather wallet', ['date_occurred' => gmdate('Y-m-d', strtotime('-60 days'))]);
    $hidden     = $makeItem($finder, 'found', 'Black leather wallet');
    test_pdo()->exec("UPDATE items SET is_approved = 0 WHERE id = $hidden");
    $ids = $matchIds($lostId);
    foreach (compact('irrelevant', 'sameType', 'own', 'tooOld', 'hidden') as $name => $id) {
        assert_true(!in_array($id, $ids, true), "$name should not match");
    }
    assert_true(count($ids) <= ItemMatcher::MAX_RESULTS);
    assert_same(count($ids), count(array_unique($ids)), 'no duplicates');
});

test('Matcher: returned, closed and deleted items drop out; closed items get no suggestions', function () use ($makeItem, $finder, $lostId, $matchIds) {
    $returned = $makeItem($finder, 'found', 'Black leather wallet returned');
    $closed   = $makeItem($finder, 'found', 'Black leather wallet closed');
    $deleted  = $makeItem($finder, 'found', 'Black leather wallet deleted');
    Item::setStatus($returned, 'returned');
    Item::setStatus($closed, 'closed');
    Item::delete($deleted);
    $ids = $matchIds($lostId);
    foreach ([$returned, $closed, $deleted] as $id) {
        assert_true(!in_array($id, $ids, true));
    }
    Item::setStatus($lostId, 'returned');
    assert_same([], $matchIds($lostId));
    Item::setStatus($lostId, 'open');
});

test('Item page shows the Possible matches section with score and reasons', function () use ($lostId) {
    $page = (new Client())->get("/items/$lostId")['body'];
    assert_contains('Possible matches', $page);
    assert_contains('Same category', $page);
    assert_contains('aria-label="Match score"', $page);
    assert_not_contains('style=', substr($page, strpos($page, 'Possible matches')), 'no inline styles (CSP)');
});

test('Item page hides the section when there are no matches', function () use ($makeItem, $makeUser) {
    $lonely = $makeItem($makeUser('lonely@match.test'), 'lost', 'Unique telescope', ['category_id' => 9, 'location_id' => 11, 'description' => 'Rare brass telescope']);
    assert_not_contains('Possible matches', (new Client())->get("/items/$lonely")['body']);
});
