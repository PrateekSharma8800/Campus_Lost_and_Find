<?php
/**
 * Database schema tests: structure, constraints, relationships and
 * delete behaviour. Run with: php tests/run.php
 */

declare(strict_types=1);

$pdo = test_pdo();

// --- helpers ---------------------------------------------------------
$makeUser = function (string $email, string $role = 'student') use ($pdo): int {
    $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)')
        ->execute(['Test User', $email, password_hash('secret', PASSWORD_DEFAULT), $role]);
    return (int) $pdo->lastInsertId();
};
$makeItem = function (int $userId, string $type = 'found', string $title = 'Black wallet', int $categoryId = 1) use ($pdo): int {
    $pdo->prepare('INSERT INTO items (user_id, type, title, description, category_id, location_id, date_occurred)
                   VALUES (?, ?, ?, ?, ?, 1, CURDATE())')
        ->execute([$userId, $type, $title, 'Leather wallet found near the library entrance', $categoryId]);
    return (int) $pdo->lastInsertId();
};
$count = fn (string $sql, array $p = []) => (int) (function () use ($pdo, $sql, $p) {
    $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchColumn();
})();

$expectedTables = ['categories', 'claims', 'item_images', 'items', 'locations', 'login_attempts',
                   'messages', 'notifications', 'password_resets', 'reports', 'users'];

// --- structure -------------------------------------------------------
test('all 11 tables exist', function () use ($pdo, $expectedTables) {
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    sort($tables);
    assert_same($expectedTables, $tables);
});

test('every table uses InnoDB and utf8mb4_unicode_ci', function () use ($pdo) {
    $rows = $pdo->query("SELECT table_name, engine, table_collation FROM information_schema.tables
                         WHERE table_schema = DATABASE()")->fetchAll();
    foreach ($rows as $r) {
        $r = array_change_key_case($r);
        assert_same('InnoDB', $r['engine'], $r['table_name']);
        assert_same('utf8mb4_unicode_ci', $r['table_collation'], $r['table_name']);
    }
});

test('foreign keys are all defined (16 total)', function () use ($count) {
    assert_same(16, $count("SELECT COUNT(*) FROM information_schema.referential_constraints
                            WHERE constraint_schema = DATABASE()"));
});

test('server runs in strict SQL mode (invalid data is rejected, not truncated)', function () use ($pdo) {
    assert_true(str_contains($pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn(), 'STRICT_TRANS_TABLES'));
});

test('connection refuses stacked (multi) statements', function () use ($pdo) {
    $ok = false;
    try { $pdo->query('SELECT 1; SELECT 2'); } catch (PDOException) { $ok = true; }
    assert_true($ok, 'multi-statement query should fail');
});

// --- seed data -------------------------------------------------------
test('seed loads 10 categories and 11 locations', function () use ($count) {
    assert_same(10, $count('SELECT COUNT(*) FROM categories'));
    assert_same(11, $count('SELECT COUNT(*) FROM locations'));
});

test('seed is safe to re-run (no duplicates)', function () use ($pdo, $count) {
    foreach (sql_statements(BASE_PATH . '/database/seed.sql') as $s) { $pdo->exec($s); }
    assert_same(10, $count('SELECT COUNT(*) FROM categories'));
});

// --- users -----------------------------------------------------------
test('user defaults: role=student, active, timestamps set', function () use ($pdo, $makeUser) {
    $id = $makeUser('defaults@example.com');
    $u = $pdo->query("SELECT role, is_active, created_at FROM users WHERE id = $id")->fetch();
    assert_same('student', $u['role']);
    assert_same(1, (int) $u['is_active']);
    assert_true($u['created_at'] !== null);
});

test('duplicate email is rejected', function () use ($makeUser) {
    $makeUser('dupe@example.com');
    assert_db_error(fn () => $makeUser('dupe@example.com'), '23000', 1062);
});

test('email uniqueness is case-insensitive', function () use ($makeUser) {
    $makeUser('case@example.com');
    assert_db_error(fn () => $makeUser('CASE@Example.com'), '23000', 1062);
});

test('invalid role is rejected', function () use ($makeUser) {
    assert_db_error(fn () => $makeUser('badrole@example.com', 'superuser'), '01000', 1265);
});

test('stored password hash verifies with password_verify()', function () use ($pdo, $makeUser) {
    $id = $makeUser('hash@example.com');
    $hash = $pdo->query("SELECT password_hash FROM users WHERE id = $id")->fetchColumn();
    assert_true(password_verify('secret', $hash));
    assert_true(!password_verify('wrong', $hash));
});

test('utf8mb4 round-trip (Hindi text + emoji)', function () use ($pdo, $makeUser) {
    $id = $makeUser('utf8@example.com');
    $name = 'पुस्तक 🎒';
    $pdo->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([$name, $id]);
    assert_same($name, $pdo->query("SELECT name FROM users WHERE id = $id")->fetchColumn());
});

// --- items -----------------------------------------------------------
test('item defaults: status=open, approved', function () use ($pdo, $makeUser, $makeItem) {
    $id = $makeItem($makeUser('itemdef@example.com'));
    $i = $pdo->query("SELECT status, is_approved FROM items WHERE id = $id")->fetch();
    assert_same('open', $i['status']);
    assert_same(1, (int) $i['is_approved']);
});

test('item with a non-existent category is rejected (FK)', function () use ($makeUser, $makeItem) {
    $uid = $makeUser('badcat@example.com');
    assert_db_error(fn () => $makeItem($uid, 'lost', 'x', 9999), '23000', 1452);
});

test('item with invalid type is rejected', function () use ($makeUser, $makeItem) {
    $uid = $makeUser('badtype@example.com');
    assert_db_error(fn () => $makeItem($uid, 'stolen'), '01000', 1265);
});

test('FULLTEXT search finds items by keyword', function () use ($pdo, $makeUser, $makeItem, $count) {
    $makeItem($makeUser('ft@example.com'), 'lost', 'Blue Casio calculator');
    assert_true($count("SELECT COUNT(*) FROM items WHERE MATCH(title, description) AGAINST (? IN BOOLEAN MODE)",
                       ['calculator*']) >= 1);
});

test('category in use cannot be deleted (RESTRICT)', function () use ($pdo, $makeUser, $makeItem) {
    $makeItem($makeUser('restrict@example.com'), 'found', 'Keys', 4);
    assert_db_error(fn () => $pdo->exec('DELETE FROM categories WHERE id = 4'), '23000', 1451);
});

// --- images ----------------------------------------------------------
test('image with disallowed MIME type is rejected', function () use ($pdo, $makeUser, $makeItem) {
    $iid = $makeItem($makeUser('mime@example.com'));
    assert_db_error(fn () => $pdo->prepare('INSERT INTO item_images (item_id, file_name, mime_type, file_size) VALUES (?, ?, ?, ?)')
        ->execute([$iid, 'evil.php', 'application/x-php', 100]), '01000', 1265);
});

test('image with zero size is rejected (CHECK)', function () use ($pdo, $makeUser, $makeItem) {
    $iid = $makeItem($makeUser('zero@example.com'));
    assert_db_error(fn () => $pdo->prepare('INSERT INTO item_images (item_id, file_name, mime_type, file_size) VALUES (?, ?, ?, ?)')
        ->execute([$iid, 'a.jpg', 'image/jpeg', 0]), 'HY000', 3819);
});

// --- claims / reports uniqueness ------------------------------------
test('a user can claim the same item only once', function () use ($pdo, $makeUser, $makeItem) {
    $iid = $makeItem($makeUser('finder1@example.com'));
    $cid = $makeUser('claimer1@example.com');
    $ins = fn () => $pdo->prepare('INSERT INTO claims (item_id, claimant_id, message) VALUES (?, ?, ?)')
                        ->execute([$iid, $cid, 'It is mine']);
    $ins();
    assert_db_error($ins, '23000', 1062);
});

test('a user can report the same item only once', function () use ($pdo, $makeUser, $makeItem) {
    $iid = $makeItem($makeUser('poster2@example.com'));
    $rid = $makeUser('reporter2@example.com');
    $ins = fn () => $pdo->prepare("INSERT INTO reports (item_id, reporter_id, reason) VALUES (?, ?, 'spam')")
                        ->execute([$iid, $rid]);
    $ins();
    assert_db_error($ins, '23000', 1062);
});

// --- delete behaviour -----------------------------------------------
test('deleting an item cascades to images, claims, messages and reports', function () use ($pdo, $makeUser, $makeItem, $count) {
    $finder = $makeUser('finder3@example.com');
    $other  = $makeUser('other3@example.com');
    $iid    = $makeItem($finder);
    $pdo->prepare("INSERT INTO item_images (item_id, file_name, mime_type, file_size) VALUES (?, 'f3.jpg', 'image/jpeg', 10)")->execute([$iid]);
    $pdo->prepare("INSERT INTO claims (item_id, claimant_id, message) VALUES (?, ?, 'mine')")->execute([$iid, $other]);
    $pdo->prepare("INSERT INTO messages (item_id, sender_id, receiver_id, body) VALUES (?, ?, ?, 'hi')")->execute([$iid, $other, $finder]);
    $pdo->prepare("INSERT INTO reports (item_id, reporter_id, reason) VALUES (?, ?, 'fake')")->execute([$iid, $other]);

    $pdo->exec("DELETE FROM items WHERE id = $iid");
    foreach (['item_images', 'claims', 'messages', 'reports'] as $t) {
        assert_same(0, $count("SELECT COUNT(*) FROM $t WHERE item_id = ?", [$iid]), $t);
    }
});

test('deleting a user cascades to their items and notifications', function () use ($pdo, $makeUser, $makeItem, $count) {
    $uid = $makeUser('gone@example.com');
    $makeItem($uid);
    $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'test', 'hello')")->execute([$uid]);
    $pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL 1 HOUR)")
        ->execute([$uid, hash('sha256', 'tok')]);
    $pdo->exec("DELETE FROM users WHERE id = $uid");
    assert_same(0, $count('SELECT COUNT(*) FROM items WHERE user_id = ?', [$uid]));
    assert_same(0, $count('SELECT COUNT(*) FROM notifications WHERE user_id = ?', [$uid]));
    assert_same(0, $count('SELECT COUNT(*) FROM password_resets WHERE user_id = ?', [$uid]));
});

test('deleting a reviewing admin keeps the claim but clears reviewed_by (SET NULL)', function () use ($pdo, $makeUser, $makeItem) {
    $admin = $makeUser('admin4@example.com', 'admin');
    $iid   = $makeItem($makeUser('finder4@example.com'));
    $pdo->prepare("INSERT INTO claims (item_id, claimant_id, message, status, reviewed_by, reviewed_at)
                   VALUES (?, ?, 'mine', 'approved', ?, NOW())")
        ->execute([$iid, $makeUser('claimer4@example.com'), $admin]);
    $claimId = (int) $pdo->lastInsertId();
    $pdo->exec("DELETE FROM users WHERE id = $admin");
    $row = $pdo->query("SELECT status, reviewed_by FROM claims WHERE id = $claimId")->fetch();
    assert_same('approved', $row['status']);
    assert_same(null, $row['reviewed_by']);
});

test('Possible Matches index covers (type, category_id, date_occurred) in that order', function () use ($pdo) {
    // Checks the index definition rather than EXPLAIN output: on a tiny test
    // table the optimizer may legitimately pick a different index.
    $cols = $pdo->query("SELECT column_name FROM information_schema.statistics
                         WHERE table_schema = DATABASE() AND table_name = 'items' AND index_name = 'idx_items_match'
                         ORDER BY seq_in_index")->fetchAll(PDO::FETCH_COLUMN);
    assert_same(['type', 'category_id', 'date_occurred'], $cols);
});

test('connection session time zone is UTC', function () use ($pdo) {
    assert_same('+00:00', $pdo->query('SELECT @@session.time_zone')->fetchColumn());
});
