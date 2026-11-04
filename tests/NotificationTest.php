<?php
/** Notifications module tests. */

declare(strict_types=1);

$client = new Client();
$client->submit('/register', ['name' => 'Nina Notify', 'email' => 'nina@notify.test', 'password' => 'campus123', 'password_confirmation' => 'campus123']);
$ninaId = (int) User::findByEmail('nina@notify.test')['id'];
$other = new Client();
$other->submit('/register', ['name' => 'Otto Other', 'email' => 'otto@notify.test', 'password' => 'campus123', 'password_confirmation' => 'campus123']);
$ottoId = (int) User::findByEmail('otto@notify.test')['id'];
$latestId = fn (int $userId) => (int) Notification::forUser($userId)[0]['id'];

test('Notifications: guests redirected; empty state when there are none', function () use ($client) {
    assert_redirect((new Client())->get('/notifications'), '/login');
    $page = $client->get('/notifications')['body'];
    assert_contains('No notifications yet', $page);
    assert_contains('all caught up', $page);
});

test('Bell badge and dropdown show the latest notifications; text is escaped', function () use ($client, $ninaId) {
    Notification::create($ninaId, 'claim_received', 'Asha claimed your found item "Wallet".', '/items/1');
    Notification::create($ninaId, 'claim_approved', 'Your claim for "<script>x</script>" was approved!', '/my-claims');
    $page = $client->get('/items')['body'];
    assert_contains('Notifications, 2 unread', $page);
    assert_contains('Asha claimed your found item', $page);
    assert_contains('&lt;script&gt;x&lt;/script&gt;', $page);
    assert_not_contains('<script>x</script>', $page);
});

test('Unread filter and the full list', function () use ($client) {
    assert_contains('You have 2 unread notifications.', $client->get('/notifications')['body']);
    assert_same(2, substr_count($client->get('/notifications?filter=unread')['body'], 'class="notification unread"'));
});

test('Opening a notification marks it read and goes to its link', function () use ($client, $ninaId, $latestId) {
    $id = $latestId($ninaId);
    assert_redirect($client->post("/notifications/$id/open", ['_token' => $client->token()]), '/my-claims');
    assert_true(Notification::findForUser($id, $ninaId)['read_at'] !== null);
    assert_same(1, Notification::unreadCount($ninaId));
});

test('Users cannot open or read other users\' notifications; CSRF required', function () use ($client, $other, $ninaId, $latestId) {
    $id = $latestId($ninaId);
    assert_same(404, $other->post("/notifications/$id/open", ['_token' => $other->token()])['status']);
    assert_not_contains('Asha claimed', $other->get('/notifications')['body']);
    assert_same(419, $client->post("/notifications/$id/open", [])['status']);
    assert_same(404, $client->post('/notifications/abc/open', ['_token' => $client->token()])['status']);
});

test('External links stored in a notification are never followed (open redirect)', function () use ($client, $ninaId, $latestId) {
    Notification::create($ninaId, 'claim_rejected', 'Test', 'https://evil.example/phish');
    assert_redirect($client->post('/notifications/' . $latestId($ninaId) . '/open', ['_token' => $client->token()]), '/notifications');
});

test('Mark all read clears the badge and only affects your own notifications', function () use ($client, $ninaId, $ottoId) {
    Notification::create($ninaId, 'claim_received', 'One more', '/items/1');
    Notification::create($ottoId, 'claim_received', 'For Otto', '/items/1');
    assert_redirect($client->post('/notifications/read-all', ['_token' => $client->token()]), '/notifications');
    assert_same(0, Notification::unreadCount($ninaId));
    assert_same(1, Notification::unreadCount($ottoId));
    assert_contains('No unread notifications', $client->get('/notifications?filter=unread')['body']);
    assert_not_contains('Notifications, ', $client->get('/items')['body']);
});
