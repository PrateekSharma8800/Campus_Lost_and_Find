<?php
/**
 * Messaging module tests: access rules, sending, validation, XSS, CSRF,
 * unread counts, read receipts and the inbox.
 */

declare(strict_types=1);

$newUser = function (string $email, string $name): Client {
    $client = new Client();
    $client->submit('/register', ['name' => $name, 'email' => $email, 'password' => 'campus123', 'password_confirmation' => 'campus123']);
    return $client;
};
$userId = fn (string $email) => (int) User::findByEmail($email)['id'];
$sendMessage = fn (Client $client, string $path, string $body) => $client->post($path, ['body' => $body, '_token' => $client->token('/messages')]);

$poster   = $newUser('poster@msg.test', 'Priya Poster');
$helper   = $newUser('helper@msg.test', 'Harsh Helper');
$outsider = $newUser('outsider@msg.test', 'Omar Outsider');
$claimant = $newUser('claimant@msg.test', 'Kiran Claimant');

// The poster's lost item, created directly in the database for speed.
$itemId = Item::create($userId('poster@msg.test'), [
    'type' => 'lost', 'title' => 'Silver water bottle', 'description' => 'Steel bottle with a dent near the cap.',
    'category_id' => 8, 'location_id' => 7, 'location_detail' => '', 'date_occurred' => gmdate('Y-m-d'), 'secret_detail' => '',
]);
$posterId = $userId('poster@msg.test');
$helperId = $userId('helper@msg.test');
$threadForHelper = "/messages/$itemId/$posterId";   // helper's view of the conversation
$threadForPoster = "/messages/$itemId/$helperId";   // poster's view of the same conversation

test('Messages: guests are sent to login; empty inbox shows a friendly empty state', function () use ($helper) {
    assert_redirect((new Client())->get('/messages'), '/login');
    $inbox = $helper->get('/messages');
    assert_same(200, $inbox['status']);
    assert_contains('No conversations yet', $inbox['body']);
});

test('Item page shows a "Message" button to other users', function () use ($helper, $itemId) {
    $page = $helper->get("/items/$itemId")['body'];
    assert_contains('I found this, message Priya Poster', $page);
    assert_contains('Log in to contact Priya Poster', (new Client())->get("/items/$itemId")['body']);
});

test('Anyone logged in can open a new conversation with the poster', function () use ($helper, $threadForHelper) {
    $page = $helper->get($threadForHelper);
    assert_same(200, $page['status']);
    assert_contains('Start the conversation', $page['body']);
    assert_contains('Silver water bottle', $page['body']);
});

test('Send: empty and too-long messages are rejected; CSRF token required', function () use ($helper, $sendMessage, $threadForHelper, $itemId) {
    $empty = $sendMessage($helper, $threadForHelper, "   \n  ");
    assert_same(422, $empty['status']);
    assert_contains('Message is required.', $empty['body']);

    $long = $sendMessage($helper, $threadForHelper, str_repeat('a', Message::MAX_LENGTH + 1));
    assert_same(422, $long['status']);
    assert_contains('must not exceed 2000 characters', $long['body']);

    assert_same(419, $helper->post($threadForHelper, ['body' => 'no token'])['status']);
    assert_same(0, (int) test_pdo()->query("SELECT COUNT(*) FROM messages WHERE item_id = $itemId")->fetchColumn());
});

test('Send: message is saved, shown in the thread, and HTML is escaped', function () use ($helper, $sendMessage, $threadForHelper) {
    $response = $sendMessage($helper, $threadForHelper, "Hi! I think I found your bottle.\nIt was at the sports complex.");
    assert_redirect($response, $threadForHelper);
    $sendMessage($helper, $threadForHelper, '<script>alert("x")</script> <b>bold</b>');

    $page = $helper->get($threadForHelper)['body'];
    assert_contains('I think I found your bottle.', $page);
    assert_not_contains('<script>alert("x")</script>', $page);
    assert_contains('&lt;script&gt;', $page);
    assert_not_contains('Start the conversation', $page);
});

test('Unread: poster sees a navbar badge and dashboard alert; opening the chat marks it read', function () use ($poster, $threadForPoster, $posterId) {
    assert_same(2, Message::unreadCount($posterId));
    assert_contains('aria-label="2 unread messages"', $poster->get('/items')['body']);
    assert_contains('You have <strong>2</strong> unread messages', $poster->get('/dashboard')['body']);

    $inbox = $poster->get('/messages')['body'];
    assert_contains('Harsh Helper', $inbox);
    assert_contains('conversation unread', $inbox);

    assert_same(200, $poster->get($threadForPoster)['status']);
    assert_same(0, Message::unreadCount($posterId));
    assert_not_contains('unread messages"', $poster->get('/items')['body']);
});

test('Read receipt: sender sees "Seen" after the poster opened the chat; reply arrives', function () use ($poster, $helper, $sendMessage, $threadForPoster, $threadForHelper, $helperId) {
    assert_contains('Seen', $helper->get($threadForHelper)['body']);
    assert_redirect($sendMessage($poster, $threadForPoster, 'Thank you so much! Can we meet at the library at 4pm?'), $threadForPoster);
    assert_same(1, Message::unreadCount($helperId));
    $page = $helper->get($threadForHelper)['body'];
    assert_contains('Can we meet at the library at 4pm?', $page);
    assert_contains('You: Thank you so much', $poster->get('/messages')['body'], 'inbox preview of last message');
});

test('Access: a third user cannot read or write in someone else\'s conversation', function () use ($outsider, $sendMessage, $threadForPoster, $itemId) {
    assert_same(403, $outsider->get($threadForPoster)['status']);
    assert_same(403, $sendMessage($outsider, $threadForPoster, 'Sneaky message')['status']);
    assert_not_contains('Can we meet', $outsider->get($threadForPoster)['body']);
    assert_same(0, (int) test_pdo()->query("SELECT COUNT(*) FROM messages WHERE body = 'Sneaky message'")->fetchColumn());
});

test('Access: the poster cannot start chats with random users, only with claimants', function () use ($poster, $sendMessage, $itemId, $userId) {
    $outsiderId = $userId('outsider@msg.test');
    assert_same(403, $poster->get("/messages/$itemId/$outsiderId")['status']);
    assert_same(403, $sendMessage($poster, "/messages/$itemId/$outsiderId", 'Hello stranger')['status']);

    // Claims exist on FOUND items: the poster may message whoever claimed theirs.
    $foundId = Item::create($userId('poster@msg.test'), [
        'type' => 'found', 'title' => 'Black earphones', 'description' => 'Wired earphones found in the auditorium.',
        'category_id' => 1, 'location_id' => 6, 'location_detail' => '', 'date_occurred' => gmdate('Y-m-d'), 'secret_detail' => '',
    ]);
    $claimantId = $userId('claimant@msg.test');
    assert_same(403, $poster->get("/messages/$foundId/$claimantId")['status'], 'before the claim');
    Claim::create($foundId, $claimantId, 'These earphones look like mine.', 'Tangled left cable');
    assert_same(200, $poster->get("/messages/$foundId/$claimantId")['status'], 'after the claim');
    assert_contains("/messages/$foundId/$claimantId", $poster->get("/items/$foundId")['body'], 'Message button on claim');
});

test('Access: invalid ids, messaging yourself and hidden items give 404', function () use ($poster, $helper, $itemId, $posterId, $threadForHelper) {
    assert_same(404, $poster->get("/messages/$itemId/$posterId")['status'], 'yourself');
    assert_same(404, $helper->get("/messages/999999/$posterId")['status']);
    assert_same(404, $helper->get("/messages/$itemId/999999")['status']);
    assert_same(404, $helper->get('/messages/abc/def')['status']);

    test_pdo()->exec("UPDATE items SET is_approved = 0 WHERE id = $itemId");
    assert_same(404, $helper->get($threadForHelper)['status']);
    test_pdo()->exec("UPDATE items SET is_approved = 1 WHERE id = $itemId");
});

test('Inbox lists the most recent conversation first', function () use ($helper, $sendMessage, $userId) {
    $otherItem = Item::create($userId('outsider@msg.test'), [
        'type' => 'found', 'title' => 'Red umbrella', 'description' => 'Found a red umbrella in Block A.',
        'category_id' => 10, 'location_id' => 3, 'location_detail' => '', 'date_occurred' => gmdate('Y-m-d'), 'secret_detail' => '',
    ]);
    $sendMessage($helper, "/messages/$otherItem/" . $userId('outsider@msg.test'), 'Is this umbrella still with you?');
    $inbox = $helper->get('/messages')['body'];
    assert_true(strpos($inbox, 'Red umbrella') < strpos($inbox, 'Silver water bottle'), 'newest conversation first');
});
