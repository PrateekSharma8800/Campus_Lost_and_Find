<?php
/** Admin panel tests. */

declare(strict_types=1);

$adminId = User::create(['name' => 'Ada Admin', 'email' => 'ada@admin.test', 'password' => 'admin12345', 'role' => 'admin']);
$admin = new Client();
$admin->submit('/login', ['email' => 'ada@admin.test', 'password' => 'admin12345']);
$student = new Client();
$student->submit('/register', ['name' => 'Stu Dent', 'email' => 'stu@admin.test', 'password' => 'campus123', 'password_confirmation' => 'campus123']);
$studentId = (int) User::findByEmail('stu@admin.test')['id'];
$studentItem = Item::create($studentId, ['type' => 'lost', 'title' => 'Admin test notebook', 'description' => 'Blue notebook with maths notes.',
    'category_id' => 3, 'location_id' => 1, 'location_detail' => '', 'date_occurred' => gmdate('Y-m-d'), 'secret_detail' => '']);
$adminPost = fn (string $path, array $fields = []) => $admin->post($path, $fields + ['_token' => $admin->token('/admin')]);

test('Admin: guests go to login, students get 403 on every admin page', function () use ($student) {
    foreach (['/admin'] as $page) {   // more pages are added as each admin section is built
        assert_redirect((new Client())->get($page), '/login', $page);
        assert_same(403, $student->get($page)['status'], $page);
    }
});

test('Admin overview: stat cards, charts with table view, recent activity', function () use ($admin) {
    $page = $admin->get('/admin');
    assert_same(200, $page['status']);
    foreach (['Total users', 'Lost items', 'Found items', 'Open reports', 'Items posted', 'View as table', 'Item status',
              'Items by category', 'Recent activity', 'item posted', 'id="confirmModal"'] as $text) {
        assert_contains($text, $page['body']);
    }
    assert_not_contains('style="', $page['body'], 'no inline styles (CSP)');
});

test('AdminStats: totals and 14-day series are consistent', function () {
    $totals = AdminStats::totals();
    assert_same($totals['lost'] + $totals['found'], $totals['items']);
    $perDay = AdminStats::itemsPerDay(14);
    assert_same(14, count($perDay));
    assert_same(gmdate('Y-m-d'), array_key_last($perDay));
    assert_same($totals['items'], array_sum(AdminStats::itemsByStatus()));
});
