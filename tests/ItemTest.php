<?php
/**
 * Items module tests: posting, photo uploads, browse/search/filter,
 * detail page, edit, status, delete, claims, my items/claims.
 */

declare(strict_types=1);

// ---------- helpers ----------
$tmpDir = sys_get_temp_dir() . '/campusfind-tests-' . getmypid();
@mkdir($tmpDir);
register_shutdown_function(fn () => array_map('unlink', glob("$tmpDir/*") ?: []) && @rmdir($tmpDir));

// Remove photos uploaded during this test run (keep any that existed before).
$photosBefore = glob(UPLOAD_PATH . '/*.{jpg,png,webp}', GLOB_BRACE) ?: [];
register_shutdown_function(function () use ($photosBefore) {
    foreach (array_diff(glob(UPLOAD_PATH . '/*.{jpg,png,webp}', GLOB_BRACE) ?: [], $photosBefore) as $file) {
        unlink($file);
    }
});

/** Create a small real image file and return its path. */
$makeImage = function (string $name, string $format = 'jpeg', int $width = 120, int $height = 90) use ($tmpDir): string {
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 79, 70, 229));
    $path = "$tmpDir/$name";
    match ($format) {
        'jpeg' => imagejpeg($image, $path),
        'png'  => imagepng($image, $path),
        'webp' => imagewebp($image, $path),
        'gif'  => imagegif($image, $path),
    };
    return $path;
};

/** Log a new user in (registering them) and return the client. */
$userClient = function (string $email, string $name = 'Test User'): Client {
    $client = new Client();
    $client->submit('/register', ['name' => $name, 'email' => $email, 'password' => 'campus123', 'password_confirmation' => 'campus123']);
    return $client;
};

$itemFields = fn (array $overrides = []) => $overrides + [
    'type' => 'found', 'title' => 'Black leather wallet', 'description' => 'Found near the library entrance, has some cards inside.',
    'category_id' => '6', 'location_id' => '1', 'location_detail' => 'Ground floor', 'date_occurred' => gmdate('Y-m-d'),
    'secret_detail' => 'Red sticker inside the coin pocket',
];

/** Post an item and return its new id. */
$postItem = function (Client $client, array $overrides = [], array $files = []) use ($itemFields): int {
    $response = $client->postFiles('/items', $itemFields($overrides) + ['_token' => $client->token('/items/new')], $files);
    assert_redirect($response, parse_url($response['headers']['location'][0] ?? '/x', PHP_URL_PATH));
    return (int) basename(parse_url($response['headers']['location'][0], PHP_URL_PATH));
};

$post = fn (Client $client, string $path, array $fields = []) => $client->post($path, $fields + ['_token' => $client->token()]);
$count = fn (string $sql, array $params = []) => (function () use ($sql, $params) {
    $stmt = test_pdo()->prepare($sql); $stmt->execute($params); return (int) $stmt->fetchColumn();
})();

$finder   = $userClient('finder@items.test', 'Fiona Finder');
$claimer  = $userClient('claimer@items.test', 'Carl Claimer');
$claimer2 = $userClient('claimer2@items.test', 'Cara Second');
$guest    = new Client();
$ids = [];

// ---------- validator rules used by items ----------
test('Validator: in / integer / past_date rules', function () {
    assert_true(!Validator::make(['t' => 'lost'], ['t' => ['in:lost,found']])->fails());
    assert_true(Validator::make(['t' => 'stolen'], ['t' => ['in:lost,found']])->fails());
    assert_true(Validator::make(['n' => '1; DROP'], ['n' => ['integer']])->fails());
    assert_true(!Validator::make(['d' => gmdate('Y-m-d')], ['d' => ['past_date']])->fails(), 'today ok');
    foreach (['2026-02-30', '04/11/2026', gmdate('Y-m-d', strtotime('+5 days')), gmdate('Y-m-d', strtotime('-2 years'))] as $bad) {
        assert_true(Validator::make(['d' => $bad], ['d' => ['past_date']])->fails(), $bad);
    }
});

// ---------- access ----------
test('Items: guests can browse but must log in to post', function () use ($guest) {
    $page = $guest->get('/items');
    assert_same(200, $page['status']);
    assert_contains('Browse lost &amp; found items', $page['body']);
    assert_redirect($guest->get('/items/new'), '/login');
    assert_same(419, $guest->post('/items', ['title' => 'x'])['status'], 'no CSRF token');
});

// ---------- posting ----------
test('Post lost item: saved without photos; secret detail ignored for lost items', function () use ($finder, $postItem, &$ids) {
    $id = $postItem($finder, ['type' => 'lost', 'title' => 'Blue Casio calculator', 'category_id' => '3']);
    $ids['lost'] = $id;
    $item = Item::find($id);
    assert_same('lost', $item['type']);
    assert_same('open', $item['status']);
    assert_same(null, $item['secret_detail']);
    $page = $finder->follow();
    assert_contains('Your lost item has been posted.', $page['body']);
    assert_contains('Blue Casio calculator', $page['body']);
});

test('Post item: validation errors are shown and nothing is saved', function () use ($finder, $itemFields, $count) {
    $before = $count('SELECT COUNT(*) FROM items');
    $response = $finder->postFiles('/items', $itemFields([
        'title' => '', 'description' => 'short', 'category_id' => '9999', 'location_id' => 'abc',
        'date_occurred' => gmdate('Y-m-d', strtotime('+10 days')),
    ]) + ['_token' => $finder->token('/items/new')], []);
    assert_same(422, $response['status']);
    foreach (['Title is required.', 'Description must be at least 10 characters.', 'Please choose a valid category.',
              'Please choose a valid location.', 'within the last year'] as $message) {
        assert_contains($message, $response['body']);
    }
    assert_same($before, $count('SELECT COUNT(*) FROM items'));
});

test('Post found item with JPEG + PNG photos: re-encoded, random names, first is primary', function () use ($finder, $postItem, $makeImage, &$ids) {
    $id = $postItem($finder, [], ['photos[0]' => $makeImage('wallet.jpg'), 'photos[1]' => $makeImage('inside.png', 'png')]);
    $ids['found'] = $id;
    $images = ItemImage::forItem($id);
    assert_same(2, count($images));
    assert_same(1, (int) $images[0]['is_primary']);
    assert_same(['image/jpeg', 'image/png'], array_column($images, 'mime_type'));
    foreach ($images as $image) {
        assert_true((bool) preg_match('/^[a-f0-9]{32}\.(jpg|png)$/', $image['file_name']), $image['file_name']);
        assert_true(is_file(UPLOAD_PATH . '/' . $image['file_name']), 'file stored');
    }
});

test('Upload: WebP accepted; GIF, fake image and >2 MB files rejected', function () use ($finder, $postItem, $itemFields, $makeImage, $tmpDir) {
    $webpId = $postItem($finder, ['title' => 'WebP photo item'], ['photos[0]' => $makeImage('a.webp', 'webp')]);
    assert_same('image/webp', ItemImage::forItem($webpId)[0]['mime_type']);

    file_put_contents("$tmpDir/shell.jpg", "<?php system(\$_GET['c']); ?>");
    $big = imagecreatetruecolor(1400, 1400);
    for ($i = 0; $i < 30000; $i++) {   // random noise does not compress, so the PNG is > 2 MB
        imagefilledrectangle($big, random_int(0, 1399), random_int(0, 1399), random_int(0, 1399), random_int(0, 1399), random_int(0, 0xFFFFFF));
    }
    imagepng($big, "$tmpDir/big.png", 0);

    $cases = [
        'is not a JPG, PNG or WebP image' => $makeImage('anim.gif', 'gif'),
        'shell.jpg&quot; is not a JPG'    => "$tmpDir/shell.jpg",
        'is larger than 2 MB'             => "$tmpDir/big.png",
    ];
    foreach ($cases as $expected => $file) {
        $response = $finder->postFiles('/items', $itemFields() + ['_token' => $finder->token('/items/new')], ['photos[0]' => $file]);
        assert_same(422, $response['status'], $expected);
        assert_contains($expected, $response['body']);
    }
});

test('Upload: more than 3 photos is refused', function () use ($finder, $itemFields, $makeImage) {
    $files = [];
    for ($i = 0; $i < 4; $i++) {
        $files["photos[$i]"] = $makeImage("p$i.jpg");
    }
    $response = $finder->postFiles('/items', $itemFields() + ['_token' => $finder->token('/items/new')], $files);
    assert_same(422, $response['status']);
    assert_contains('at most 3 photos', $response['body']);
});

test('Upload: hidden PHP code appended to a real image is removed by re-encoding', function () use ($finder, $postItem, $makeImage) {
    $path = $makeImage('payload.png', 'png');
    file_put_contents($path, '<?php echo "pwned"; ?>', FILE_APPEND);
    $id = $postItem($finder, ['title' => 'Payload test'], ['photos[0]' => $path]);
    $stored = file_get_contents(UPLOAD_PATH . '/' . ItemImage::forItem($id)[0]['file_name']);
    assert_not_contains('<?php', $stored);
    Item::delete($id);
});

test('Upload: request bigger than post_max_size gets a clear 413 error', function () use ($finder, $tmpDir) {
    file_put_contents("$tmpDir/huge.bin", random_bytes(9 * 1024 * 1024));
    $response = $finder->postFiles('/items', ['_token' => $finder->token()], ['photos[0]' => "$tmpDir/huge.bin"]);
    assert_same(413, $response['status']);
});

test('Photos are served with the stored MIME type, nosniff, and only for valid names', function () use ($guest, &$ids) {
    $image = ItemImage::forItem($ids['found'])[0];
    $response = $guest->get(photo_url($image['file_name']));
    assert_same(200, $response['status']);
    assert_same('image/jpeg', $response['headers']['content-type'][0] ?? null);
    assert_same('nosniff', $response['headers']['x-content-type-options'][0] ?? null);
    assert_same(404, $guest->get('/uploads/../../.env')['status']);
    assert_same(404, $guest->get('/uploads/' . str_repeat('a', 32) . '.jpg')['status']);
    assert_same(404, $guest->get('/storage/uploads/items/' . $image['file_name'])['status'], 'storage not public');
});

// ---------- browse, search, filters ----------
test('Browse: type filter, keyword search, category filter and LIKE wildcards', function () use ($guest) {
    $found = $guest->get('/items?type=found')['body'];
    assert_contains('Black leather wallet', $found);
    assert_not_contains('Blue Casio calculator', $found);

    $search = $guest->get('/items?q=casio')['body'];
    assert_contains('Blue Casio calculator', $search);
    assert_not_contains('Black leather wallet', $search);

    assert_contains('Blue Casio calculator', $guest->get('/items?category=3')['body']);
    assert_not_contains('Blue Casio calculator', $guest->get('/items?category=6')['body']);

    $wildcard = $guest->get('/items?q=%25')['body'];   // "%" must not match everything
    assert_contains('No items match your search', $wildcard);
    assert_same(200, $guest->get('/items?category=abc&type=evil&page=-5&status=hacked')['status'], 'bad filters ignored');
});

test('Browse: pagination shows 12 per page', function () use ($finder, $postItem, $guest) {
    for ($i = 1; $i <= 12; $i++) {
        $postItem($finder, ['type' => 'lost', 'title' => "Pagination item $i", 'secret_detail' => '']);
    }
    $page1 = $guest->get('/items');
    assert_same(12, substr_count($page1['body'], 'class="cf-card item-card"'));
    assert_contains('page=2', $page1['body']);
    $page2 = $guest->get('/items?page=2');
    assert_true(substr_count($page2['body'], 'class="cf-card item-card"') >= 1);
});

test('Browse: hidden items and returned items are not listed by default', function () use ($finder, $postItem, $guest, &$ids) {
    $hidden = $postItem($finder, ['title' => 'Hidden by admin item']);
    test_pdo()->exec("UPDATE items SET is_approved = 0 WHERE id = $hidden");
    $ids['hidden'] = $hidden;
    assert_not_contains('Hidden by admin item', $guest->get('/items?status=all')['body']);
    assert_same(404, $guest->get("/items/$hidden")['status']);
    assert_same(200, $finder->get("/items/$hidden")['status'], 'owner can still see it');

    $returned = $postItem($finder, ['title' => 'Already returned umbrella']);
    Item::setStatus($returned, 'returned');
    assert_not_contains('Already returned umbrella', $guest->get('/items')['body']);
    assert_contains('Already returned umbrella', $guest->get('/items?status=all')['body']);
});

test('Photos of hidden items are private', function () use ($finder, $guest, $makeImage, &$ids) {
    $id = $ids['hidden'];
    $finder->postFiles("/items/$id/edit", ['type' => 'found', 'title' => 'Hidden by admin item', 'description' => 'Hidden item with a photo attached.',
        'category_id' => '1', 'location_id' => '1', 'date_occurred' => gmdate('Y-m-d'), '_token' => $finder->token()],
        ['photos[0]' => $makeImage('hidden.jpg')]);
    $url = photo_url(ItemImage::forItem($id)[0]['file_name']);
    assert_same(404, $guest->get($url)['status']);
    assert_same(200, $finder->get($url)['status']);
});

test('Item title/description with HTML are escaped (no XSS)', function () use ($finder, $postItem, $guest) {
    $id = $postItem($finder, ['title' => '<script>alert(1)</script> bag', 'description' => '<img src=x onerror=alert(2)> blue bag']);
    foreach (["/items/$id", '/items'] as $page) {
        $body = $guest->get($page)['body'];
        assert_not_contains('<script>alert(1)</script>', $body, $page);
        assert_not_contains('<img src=x', $body, $page);
    }
});

// ---------- detail page ----------
test('Detail page: secret detail visible only to the poster', function () use ($finder, $claimer, $guest, &$ids) {
    $id = $ids['found'];
    assert_contains('Red sticker inside the coin pocket', $finder->get("/items/$id")['body']);
    assert_not_contains('Red sticker', $claimer->get("/items/$id")['body']);
    assert_not_contains('Red sticker', $guest->get("/items/$id")['body']);
    assert_contains('Log in to claim this item', $guest->get("/items/$id")['body']);
    assert_same(404, $guest->get('/items/999999')['status']);
    assert_same(404, $guest->get('/items/abc')['status']);
});

// ---------- edit ----------
test('Edit: only the poster may edit; type cannot be changed; photos can be removed', function () use ($finder, $claimer, $makeImage, &$ids) {
    $id = $ids['found'];
    assert_same(403, $claimer->get("/items/$id/edit")['status']);
    assert_same(403, $claimer->post("/items/$id/edit", ['title' => 'Hacked', '_token' => $claimer->token()])['status']);

    $images = ItemImage::forItem($id);
    $removed = $images[0];   // remove the primary photo
    $response = $finder->postFiles("/items/$id/edit", [
        'type' => 'lost', 'title' => 'Black wallet (edited)', 'description' => 'Found near the library entrance, edited.',
        'category_id' => '6', 'location_id' => '2', 'date_occurred' => gmdate('Y-m-d'), 'secret_detail' => 'Red sticker inside the coin pocket',
        'remove_images[0]' => (string) $removed['id'], '_token' => $finder->token(),
    ], ['photos[0]' => $makeImage('new.jpg')]);
    assert_redirect($response, "/items/$id");

    $item = Item::find($id);
    assert_same('Black wallet (edited)', $item['title']);
    assert_same('found', $item['type'], 'type unchanged');
    assert_same(2, count(ItemImage::forItem($id)));
    assert_true(!is_file(UPLOAD_PATH . '/' . $removed['file_name']), 'removed photo deleted from disk');
    assert_same(1, array_sum(array_column(ItemImage::forItem($id), 'is_primary')), 'exactly one primary photo');
});

// ---------- claims ----------
test('Claim: poster cannot claim own item; lost items cannot be claimed; guests must log in', function () use ($finder, $claimer, $guest, $post, $count, &$ids) {
    assert_same(403, $post($finder, "/items/{$ids['found']}/claim", ['message' => 'This is my own item', 'proof_answer' => 'abc'])['status']);
    $post($claimer, "/items/{$ids['lost']}/claim", ['message' => 'Trying to claim a lost item', 'proof_answer' => 'abc']);
    assert_same(0, $count('SELECT COUNT(*) FROM claims WHERE item_id = ?', [$ids['lost']]));
    $guestClaim = $guest->post("/items/{$ids['found']}/claim", ['message' => 'x', '_token' => $guest->token('/login')]);
    assert_redirect($guestClaim, '/login');
});

test('Claim: validation, success with notification, and no duplicates', function () use ($claimer, $claimer2, $post, $count, &$ids) {
    $id = $ids['found'];
    $invalid = $post($claimer, "/items/$id/claim", ['message' => 'short', 'proof_answer' => '']);
    assert_same(422, $invalid['status']);
    assert_contains('Message must be at least 10 characters.', $invalid['body']);

    assert_redirect($post($claimer, "/items/$id/claim", ['message' => 'I lost it on Monday near the library.', 'proof_answer' => 'Red sticker in coin pocket']), "/items/$id");
    assert_contains('Your claim was sent', $claimer->follow()['body']);
    $post($claimer, "/items/$id/claim", ['message' => 'Claiming the same item twice', 'proof_answer' => 'again']);
    assert_same(1, $count('SELECT COUNT(*) FROM claims WHERE item_id = ? AND claimant_id = (SELECT id FROM users WHERE email = ?)', [$id, 'claimer@items.test']));
    assert_same(1, $count("SELECT COUNT(*) FROM notifications WHERE type = 'claim_received' AND link = ?", ["/items/$id"]));

    $post($claimer2, "/items/$id/claim", ['message' => 'I think this wallet is mine.', 'proof_answer' => 'Blue sticker']);
    assert_same(2, $count("SELECT COUNT(*) FROM claims WHERE item_id = ? AND status = 'pending'", [$id]));
});

test('Claims: poster sees claims; others cannot approve', function () use ($finder, $claimer2, $post, &$ids) {
    $page = $finder->get("/items/{$ids['found']}")['body'];
    assert_contains('Carl Claimer', $page);
    assert_contains('Red sticker in coin pocket', $page);
    $claimId = Claim::forItem($ids['found'])[0]['id'];
    assert_same(403, $post($claimer2, "/claims/$claimId/approve")['status']);
});

test('Approve claim: item becomes claimed, other pending claims are rejected', function () use ($finder, $claimer, $claimer2, $post, &$ids) {
    $id = $ids['found'];
    $mine = Claim::byUserForItem((int) User::findByEmail('claimer@items.test')['id'], $id);
    assert_redirect($post($finder, "/claims/{$mine['id']}/approve"), "/items/$id");

    assert_same('claimed', Item::find($id)['status']);
    assert_same('approved', Claim::find((int) $mine['id'])['status']);
    $other = Claim::byUserForItem((int) User::findByEmail('claimer2@items.test')['id'], $id);
    assert_same('rejected', $other['status']);

    assert_contains('Approved', $claimer->get('/my-claims')['body']);
    assert_contains('Rejected', $claimer2->get('/my-claims')['body']);
    $again = $post($finder, "/claims/{$mine['id']}/approve");
    assert_redirect($again, "/items/$id");
    assert_contains('already been reviewed', $finder->follow()['body']);
});

test('Reject claim works and claimant is notified', function () use ($finder, $claimer, $postItem, $post, $count) {
    $id = $postItem($finder, ['title' => 'Grey hoodie']);
    $post($claimer, "/items/$id/claim", ['message' => 'This grey hoodie is mine.', 'proof_answer' => 'Name tag inside']);
    $claim = Claim::forItem($id)[0];
    $post($finder, "/claims/{$claim['id']}/reject");
    assert_same('rejected', Claim::find((int) $claim['id'])['status']);
    assert_same('open', Item::find($id)['status'], 'item stays open');
    assert_same(1, $count("SELECT COUNT(*) FROM notifications WHERE type = 'claim_rejected' AND link = ?", ["/items/$id"]));
});

// ---------- status, my items, delete ----------
test('Mark as returned / closed / reopen; invalid status and non-owners refused', function () use ($finder, $claimer, $post, &$ids) {
    $id = $ids['found'];
    assert_redirect($post($finder, "/items/$id/status", ['status' => 'returned']), "/items/$id");
    assert_same('returned', Item::find($id)['status']);
    assert_same(400, $post($finder, "/items/$id/status", ['status' => 'stolen'])['status']);
    assert_same(403, $post($claimer, "/items/$id/status", ['status' => 'open'])['status']);
    $post($finder, "/items/$id/status", ['status' => 'closed']);
    assert_same('closed', Item::find($id)['status']);
});

test('My items, my claims and dashboard show the user\'s own data', function () use ($finder) {
    $mine = $finder->get('/my-items')['body'];
    assert_contains('Blue Casio calculator', $mine);
    assert_contains('Black wallet (edited)', $mine);
    assert_not_contains('I think this wallet is mine', $mine);

    $dashboard = $finder->get('/dashboard')['body'];
    assert_contains('Lost items posted', $dashboard);
    assert_contains('Your recent posts', $dashboard);

    // A brand-new user sees friendly empty states.
    $newUser = new Client();
    $newUser->submit('/register', ['name' => 'New Person', 'email' => 'new@items.test', 'password' => 'campus123', 'password_confirmation' => 'campus123']);
    assert_contains('No claims yet', $newUser->get('/my-claims')['body']);
    assert_contains('You haven&apos;t posted anything yet', $newUser->get('/my-items')['body']);
});

test('Delete: only the poster; removes item, photos on disk and claims', function () use ($finder, $claimer, $post, $count, &$ids) {
    $id = $ids['found'];
    $files = array_column(ItemImage::forItem($id), 'file_name');
    assert_same(403, $post($claimer, "/items/$id/delete")['status']);
    assert_redirect($post($finder, "/items/$id/delete"), '/my-items');
    assert_same(null, Item::find($id));
    assert_same(0, $count('SELECT COUNT(*) FROM claims WHERE item_id = ?', [$id]));
    foreach ($files as $file) {
        assert_true(!is_file(UPLOAD_PATH . '/' . $file), "$file deleted");
    }
});

test('Home page lists the latest open items', function () use ($guest) {
    $home = $guest->get('/')['body'];
    assert_contains('Recently posted', $home);
    assert_contains('Pagination item 12', $home);
});
