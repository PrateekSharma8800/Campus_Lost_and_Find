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
    foreach (['/admin', '/admin/users', '/admin/items', '/admin/reports'] as $page) {
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

test('Admin items: list includes hidden items; search and filters work', function () use ($admin, $studentItem) {
    test_pdo()->exec("UPDATE items SET is_approved = 0 WHERE id = $studentItem");
    $page = $admin->get('/admin/items?visibility=hidden')['body'];
    assert_contains('Admin test notebook', $page);
    assert_contains('Hidden', $page);
    assert_contains('Admin test notebook', $admin->get('/admin/items?q=maths+notes')['body']);
    assert_not_contains('Admin test notebook', $admin->get('/admin/items?type=found')['body']);
    test_pdo()->exec("UPDATE items SET is_approved = 1 WHERE id = $studentItem");
});

test('Admin items: hide notifies the poster and removes it from public view; show restores it', function () use ($adminPost, $studentItem, $studentId) {
    assert_redirect($adminPost("/admin/items/$studentItem/visibility", ['approved' => '0']), '/admin/items');
    assert_same(0, (int) Item::find($studentItem)['is_approved']);
    assert_same(404, (new Client())->get("/items/$studentItem")['status']);
    assert_same('item_moderated', Notification::forUser($studentId)[0]['type']);
    $adminPost("/admin/items/$studentItem/visibility", ['approved' => '1']);
    assert_same(200, (new Client())->get("/items/$studentItem")['status']);
});

test('Admin items: delete removes the post; students cannot moderate', function () use ($adminPost, $student, $studentId) {
    $victim = Item::create($studentId, ['type' => 'found', 'title' => 'Spam post to delete', 'description' => 'Buy cheap stuff at my shop.',
        'category_id' => 10, 'location_id' => 1, 'location_detail' => '', 'date_occurred' => gmdate('Y-m-d'), 'secret_detail' => '']);
    assert_same(403, $student->post("/admin/items/$victim/delete", ['_token' => $student->token('/dashboard')])['status']);
    assert_redirect($adminPost("/admin/items/$victim/delete"), '/admin/items');
    assert_same(null, Item::find($victim));
    assert_same(404, $adminPost('/admin/items/999999/delete')['status']);
});

test('Admin reports: open list, detail page with post + owner, students blocked', function () use ($admin, $student, $studentItem, $studentId) {
    $reporterId = User::create(['name' => 'Rep Orter', 'email' => 'rep@admin.test', 'password' => 'campus123']);
    Report::create($reporterId, $studentItem, $studentId, 'fake', 'This notebook post looks fake to me.');
    $reportId = (int) test_pdo()->query("SELECT id FROM reports WHERE reporter_id = $reporterId")->fetchColumn();
    $GLOBALS['adminReportId'] = $reportId;

    $list = $admin->get('/admin/reports')['body'];
    assert_contains('Admin test notebook', $list);
    assert_contains('Fake or misleading post', $list);
    $detail = $admin->get("/admin/reports/$reportId")['body'];
    foreach (['This notebook post looks fake', 'Rep Orter', 'Stu Dent', 'Deactivate account', 'Resolve', 'Dismiss'] as $text) {
        assert_contains($text, $detail);
    }
    assert_same(403, $student->get("/admin/reports/$reportId")['status']);
    assert_same(404, $admin->get('/admin/reports/999999')['status']);
    assert_contains('No reports here', $admin->get('/admin/reports?status=dismissed')['body']);
});

test('Admin reports: resolve notifies the reporter; reopen and invalid status handled', function () use ($adminPost) {
    $reportId = $GLOBALS['adminReportId'];
    assert_redirect($adminPost("/admin/reports/$reportId/status", ['status' => 'resolved']), "/admin/reports/$reportId");
    $row = Report::findForAdmin($reportId);
    assert_same('resolved', $row['status']);
    assert_same('Ada Admin', $row['reviewer_name']);
    assert_same('report_update', Notification::forUser((int) $row['reporter_id'])[0]['type']);
    assert_same(400, $adminPost("/admin/reports/$reportId/status", ['status' => 'deleted'])['status']);
    $adminPost("/admin/reports/$reportId/status", ['status' => 'open']);
    assert_same('open', Report::findForAdmin($reportId)['status']);
    test_pdo()->exec("DELETE FROM reports WHERE id = $reportId");   // keep other test files' report counts independent
});
