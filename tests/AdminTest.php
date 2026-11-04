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
    foreach (['/admin', '/admin/users'] as $page) {
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

test('Admin users: list, search and status filter', function () use ($admin) {
    $page = $admin->get('/admin/users')['body'];
    assert_contains('Stu Dent', $page);
    assert_contains('Protected', $page, 'admins cannot be deactivated');
    $search = $admin->get('/admin/users?q=stu%40admin')['body'];
    assert_contains('Stu Dent', $search);
    assert_not_contains('Ada Admin</div>', $search);
    assert_contains('No users found', $admin->get('/admin/users?q=nobody-zzz')['body']);
    assert_not_contains('Stu Dent', $admin->get('/admin/users?status=inactive')['body'], 'status filter');
});

test('Admin users: deactivate signs the student out; reactivate restores login', function () use ($adminPost, $admin, $student, $studentId) {
    assert_same(200, $student->get('/dashboard')['status']);
    assert_redirect($adminPost("/admin/users/$studentId/status", ['active' => '0']), '/admin/users');
    assert_same(0, (int) User::findById($studentId)['is_active']);
    assert_redirect($student->get('/dashboard'), '/login', 'signed out immediately');
    assert_contains('Deactivated', $admin->get('/admin/users?status=inactive')['body']);

    $adminPost("/admin/users/$studentId/status", ['active' => '1']);
    assert_same(1, (int) User::findById($studentId)['is_active']);
});

test('Admin users: admins cannot be deactivated; CSRF and admin role required', function () use ($adminPost, $admin, $student, $adminId, $studentId) {
    $adminPost("/admin/users/$adminId/status", ['active' => '0']);
    assert_same(1, (int) User::findById($adminId)['is_active']);
    assert_same(419, $admin->post("/admin/users/$studentId/status", ['active' => '0'])['status']);
    $student->submit('/login', ['email' => 'stu@admin.test', 'password' => 'campus123']);
    assert_same(403, $student->post("/admin/users/$adminId/status", ['active' => '0', '_token' => $student->token('/dashboard')])['status']);
    assert_same(404, $adminPost('/admin/users/999999/status', ['active' => '0'])['status']);
});
