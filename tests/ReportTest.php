<?php
/** Reports module tests. */

declare(strict_types=1);

$login = function (string $email, string $name): Client {
    $client = new Client();
    $client->submit('/register', ['name' => $name, 'email' => $email, 'password' => 'campus123', 'password_confirmation' => 'campus123']);
    return $client;
};
$owner    = $login('owner@report.test', 'Rita Owner');
$reporter = $login('reporter@report.test', 'Ravi Reporter');
$stranger = $login('stranger@report.test', 'Sam Stranger');
$ownerId    = (int) User::findByEmail('owner@report.test')['id'];
$reporterId = (int) User::findByEmail('reporter@report.test')['id'];
$strangerId = (int) User::findByEmail('stranger@report.test')['id'];
$itemId = Item::create($ownerId, ['type' => 'found', 'title' => 'Free iPhone giveaway', 'description' => 'Click my link to win a free phone.',
    'category_id' => 1, 'location_id' => 1, 'location_detail' => '', 'date_occurred' => gmdate('Y-m-d'), 'secret_detail' => '']);
$submit = fn (Client $c, array $fields) => $c->post('/reports', $fields + ['_token' => $c->token('/my-reports')]);
$reportCount = fn () => (int) test_pdo()->query('SELECT COUNT(*) FROM reports')->fetchColumn();

test('Reports: guests must log in; empty "My reports" page', function () use ($reporter, $itemId) {
    assert_redirect((new Client())->get("/reports/new?item=$itemId"), '/login');
    assert_contains('No reports submitted', $reporter->get('/my-reports')['body']);
});

test('Report item: link on item page, form shows item reasons; owner cannot report own post', function () use ($reporter, $owner, $itemId) {
    assert_contains("/reports/new?item=$itemId", $reporter->get("/items/$itemId")['body']);
    $form = $reporter->get("/reports/new?item=$itemId");
    assert_same(200, $form['status']);
    assert_contains('Fake or misleading post', $form['body']);
    assert_contains('Free iPhone giveaway', $form['body']);
    assert_same(403, $owner->get("/reports/new?item=$itemId")['status']);
    assert_same(404, $reporter->get('/reports/new?item=999999')['status']);
    assert_same(404, $reporter->get('/reports/new')['status']);
});

test('Report item: validation, CSRF, and invalid reason rejected', function () use ($reporter, $submit, $itemId, $reportCount) {
    $bad = $submit($reporter, ['item_id' => $itemId, 'reason' => 'harassment', 'details' => 'short']);   // user-only reason
    assert_same(422, $bad['status']);
    assert_contains('Please choose a valid reason.', $bad['body']);
    assert_contains('Description must be at least 10 characters.', $bad['body']);
    assert_same(419, $reporter->post('/reports', ['item_id' => $itemId, 'reason' => 'spam', 'details' => 'No token here at all'])['status']);
    assert_same(0, $reportCount());
});

test('Report item: saved with item + owner reference, shown in My reports, escaped', function () use ($reporter, $submit, $itemId, $ownerId) {
    $response = $submit($reporter, ['item_id' => $itemId, 'reason' => 'spam', 'details' => 'Looks like a scam <script>alert(1)</script>']);
    assert_redirect($response, '/my-reports');
    $row = test_pdo()->query("SELECT * FROM reports WHERE item_id = $itemId")->fetch();
    assert_same('open', $row['status']);
    assert_same($ownerId, (int) $row['reported_user_id']);
    $page = $reporter->follow()['body'];
    assert_contains('Your report was sent', $page);
    assert_contains('Under review', $page);
    assert_contains('Spam or advertising', $page);
    assert_not_contains('<script>alert(1)</script>', $page);
});

test('Report item: duplicates are refused', function () use ($reporter, $submit, $itemId, $reportCount) {
    assert_redirect($reporter->get("/reports/new?item=$itemId"), "/items/$itemId");
    $submit($reporter, ['item_id' => $itemId, 'reason' => 'fake', 'details' => 'Reporting the same post again']);
    assert_same(1, $reportCount());
});

test('Report user: only after exchanging messages; never yourself', function () use ($reporter, $stranger, $owner, $itemId, $ownerId, $reporterId, $strangerId) {
    assert_same(403, $reporter->get("/reports/new?user=$strangerId")['status'], 'no conversation');
    assert_same(403, $reporter->get("/reports/new?user=$reporterId")['status'], 'self');
    assert_same(404, $reporter->get('/reports/new?user=abc')['status']);

    Message::send($itemId, $reporterId, $ownerId, 'Is this giveaway real?');
    assert_contains("/reports/new?user=$ownerId", $reporter->get("/messages/$itemId/$ownerId")['body'], 'flag button in chat');
    $form = $reporter->get("/reports/new?user=$ownerId");
    assert_same(200, $form['status']);
    assert_contains('Harassment or abuse', $form['body']);
    assert_not_contains('Duplicate post', $form['body']);
});

test('Report user: saved without item; second open report refused; others cannot see it', function () use ($reporter, $stranger, $submit, $ownerId) {
    assert_redirect($submit($reporter, ['user_id' => $ownerId, 'reason' => 'scam', 'details' => 'Asked me to pay a fee to get my item back.']), '/my-reports');
    $row = test_pdo()->query("SELECT * FROM reports WHERE item_id IS NULL AND reported_user_id = $ownerId")->fetch();
    assert_same('scam', $row['reason']);
    assert_redirect($reporter->get("/reports/new?user=$ownerId"), '/messages', 'duplicate open report');
    assert_contains('User: Rita Owner', $reporter->get('/my-reports')['body']);
    assert_not_contains('Rita Owner', $stranger->get('/my-reports')['body']);
});

test('Admin helpers: list, count open, set status (shown to reporter)', function () use ($reporter) {
    assert_same(2, Report::countOpen());
    $reports = Report::forAdmin('open');
    assert_same('Ravi Reporter', $reports[0]['reporter_name']);
    $adminId = User::create(['name' => 'Report Admin', 'email' => 'admin@report.test', 'password' => 'admin12345', 'role' => 'admin']);
    Report::setStatus((int) $reports[0]['id'], 'resolved', $adminId);
    assert_same(1, Report::countOpen());
    assert_contains('Resolved', $reporter->get('/my-reports')['body']);
});
