<?php
/**
 * End-to-end tests for the Core + Authentication module, driven through a
 * real `php -S` server like a browser would (cookies, CSRF tokens,
 * redirects), plus the create-admin CLI command.
 */

declare(strict_types=1);

$mailLog = env('MAIL_LOG');
@unlink($mailLog);

$register = function (Client $c, array $overrides = []): array {
    return $c->submit('/register', $overrides + [
        'name' => 'Asha Student', 'email' => 'asha@example.com', 'student_id' => 'CS-2024/017',
        'phone' => '+91 98765 43210', 'password' => 'campus123', 'password_confirmation' => 'campus123',
    ]);
};
$login = fn (Client $c, string $email, string $password) => $c->submit('/login', ['email' => $email, 'password' => $password]);
$logout = function (Client $c): array {
    $page = $c->get('/dashboard');
    return $c->post('/logout', ['_token' => Client::csrf($page['body'])]);
};
$lastResetToken = function () use ($mailLog): ?string {
    $log = is_file($mailLog) ? file_get_contents($mailLog) : '';
    return preg_match_all('#/reset-password\?token=([a-f0-9]{64})#', $log, $m) ? end($m[1]) : null;
};
$createAdminCli = function (string $stdin): array {
    $env = getenv();
    $proc = proc_open([PHP_BINARY, BASE_PATH . '/bin/create-admin.php'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, BASE_PATH, $env);
    fwrite($pipes[0], $stdin);
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    return ['code' => proc_close($proc), 'out' => $out];
};

// --- Core: routing, headers, file exposure ------------------------------
test('HTTP: home page renders with Bootstrap layout', function () {
    $r = (new Client())->get('/');
    assert_same(200, $r['status']);
    assert_contains('bootstrap@5.3.3', $r['body']);
    assert_contains('integrity="sha384-', $r['body'], 'SRI on CDN assets');
    assert_contains('name="viewport"', $r['body'], 'responsive viewport');
    assert_contains('navbar-toggler', $r['body'], 'mobile nav toggle');
});

test('HTTP: security headers present, X-Powered-By removed', function () {
    $h = (new Client())->get('/')['headers'];
    assert_contains("default-src 'self'", $h['content-security-policy'][0] ?? '');
    assert_contains("frame-ancestors 'none'", $h['content-security-policy'][0] ?? '');
    assert_same('nosniff', $h['x-content-type-options'][0] ?? null);
    assert_same('DENY', $h['x-frame-options'][0] ?? null);
    assert_same('same-origin', $h['referrer-policy'][0] ?? null);
    assert_contains('no-store', $h['cache-control'][0] ?? '');
    assert_true(!isset($h['x-powered-by']), 'X-Powered-By should be absent');
});

test('HTTP: session cookie is HttpOnly + SameSite=Lax', function () {
    $h = (new Client())->get('/login')['headers'];
    $cookie = implode("\n", $h['set-cookie'] ?? []);
    assert_contains(Session::NAME . '=', $cookie);
    assert_contains('HttpOnly', $cookie);
    assert_contains('SameSite=Lax', $cookie);
});

test('HTTP: .env, app code, SQL and dotfiles are not reachable', function () {
    $c = new Client();
    foreach (['/.env', '/../.env', '/assets/../../.env', '/app/config/database.php', '/database/schema.sql',
              '/storage/logs/mail.log', '/assets/css/../../index.php', '/.htaccess', '/index.php', '/bin/create-admin.php'] as $p) {
        $r = $c->get($p);
        assert_same(404, $r['status'], $p);
        assert_not_contains('DB_PASS', $r['body'], $p);
    }
});

test('HTTP: static assets are served', function () {
    $c = new Client();
    $css = $c->get('/assets/css/app.css');
    assert_same(200, $css['status']);
    assert_contains('text/css', $css['headers']['content-type'][0] ?? '');
    assert_same(200, $c->get('/assets/js/app.js')['status']);
});

test('HTTP: unknown route -> 404 page; wrong method -> 405', function () {
    $c = new Client();
    $r = $c->get('/no-such-page');
    assert_same(404, $r['status']);
    assert_contains('Page not found', $r['body']);
    $r = $c->get('/logout');
    assert_same(405, $r['status']);
    assert_same('POST', $r['headers']['allow'][0] ?? null);
});

// --- CSRF ---------------------------------------------------------------
test('CSRF: POST without a token is rejected with 419', function () {
    $c = new Client();
    $c->get('/register');
    $r = $c->post('/register', ['name' => 'X', 'email' => 'csrf@example.com', 'password' => 'campus123', 'password_confirmation' => 'campus123']);
    assert_same(419, $r['status']);
    assert_same(0, (int) test_pdo()->query("SELECT COUNT(*) FROM users WHERE email = 'csrf@example.com'")->fetchColumn());
});

test('CSRF: forged token and token from another session are both rejected', function () {
    $attacker = new Client();
    $stolen = Client::csrf($attacker->get('/login')['body']);
    $victim = new Client();
    $victim->get('/login');
    assert_same(419, $victim->post('/login', ['email' => 'a@b.co', 'password' => 'x', '_token' => str_repeat('a', 64)])['status']);
    assert_same(419, $victim->post('/login', ['email' => 'a@b.co', 'password' => 'x', '_token' => $stolen])['status']);
});

// --- Registration ---------------------------------------------------------
test('Register: invalid input shows errors, keeps fields, never echoes passwords', function () {
    $c = new Client();
    $r = $c->submit('/register', ['name' => 'A', 'email' => 'not-an-email', 'phone' => 'abc',
        'password' => 'short', 'password_confirmation' => 'different']);
    assert_same(422, $r['status']);
    assert_contains('Name must be at least 2 characters.', $r['body']);
    assert_contains('Email must be a valid email address.', $r['body']);
    assert_contains('Phone number format is invalid.', $r['body']);
    assert_contains('Password must be at least 8 characters.', $r['body']);
    assert_contains('value="not-an-email"', $r['body'], 'old input kept');
    assert_not_contains('value="short"', $r['body']);
    assert_not_contains('value="different"', $r['body']);
});

test('Register: mismatched confirmation is rejected', function () {
    $r = (new Client())->submit('/register', ['name' => 'Mismatch', 'email' => 'mm@example.com',
        'password' => 'campus123', 'password_confirmation' => 'campus124']);
    assert_same(422, $r['status']);
    assert_contains('Password does not match.', $r['body']);
});

test('Register: HTML in input is escaped on redisplay (no XSS)', function () {
    $r = (new Client())->submit('/register', ['name' => 'x', 'email' => '"><script>alert(1)</script>',
        'password' => 'campus123', 'password_confirmation' => 'campus123']);
    assert_not_contains('<script>alert(1)</script>', $r['body']);
    assert_contains('&quot;&gt;&lt;script&gt;', $r['body']);
});

test('Register: success hashes password, logs in, regenerates session, redirects to dashboard', function () use ($register) {
    $c = new Client();
    $c->get('/register');
    $before = $c->cookie(Session::NAME);
    $r = $register($c);
    assert_redirect($r, '/dashboard');
    assert_true($c->cookie(Session::NAME) !== $before, 'session ID must change on login');

    $dash = $c->follow();
    assert_same(200, $dash['status']);
    assert_contains('Hello, Asha Student', $dash['body']);
    assert_contains('Your account has been created', $dash['body']);

    $u = User::findByEmail('asha@example.com');
    assert_same('student', $u['role']);
    assert_same('CS-2024/017', $u['student_id']);
    assert_true(password_verify('campus123', $u['password_hash']));
    assert_not_contains('campus123', $u['password_hash']);
});

test('Register: duplicate email is refused (case-insensitive)', function () use ($register) {
    $r = $register(new Client(), ['email' => 'ASHA@Example.COM']);
    assert_same(422, $r['status']);
    assert_contains('An account with this email already exists.', $r['body']);
});

// --- Login / logout -------------------------------------------------------
test('Login: wrong password and unknown email give the same generic error', function () use ($login) {
    $a = $login(new Client(), 'asha@example.com', 'wrongpass1');
    $b = $login(new Client(), 'ghost@example.com', 'wrongpass1');
    assert_same(422, $a['status']);
    assert_contains('Invalid email or password.', $a['body']);
    assert_contains('Invalid email or password.', $b['body']);
    assert_not_contains('wrongpass1', $a['body'], 'password not echoed');
});

test('Login: success (email case-insensitive), CSRF token rotates, session ID changes', function () use ($login) {
    $c = new Client();
    $page = $c->get('/login');
    $tokenBefore = Client::csrf($page['body']);
    $sidBefore = $c->cookie(Session::NAME);
    $r = $c->post('/login', ['email' => '  Asha@Example.com ', 'password' => 'campus123', '_token' => $tokenBefore]);
    assert_redirect($r, '/dashboard');
    assert_true($c->cookie(Session::NAME) !== $sidBefore, 'session ID changed');
    $dash = $c->follow();
    assert_contains('Welcome back, Asha Student!', $dash['body']);
    assert_true(Client::csrf($dash['body']) !== $tokenBefore, 'CSRF token rotated');
    assert_true(User::findByEmail('asha@example.com')['last_login_at'] !== null);
});

test('Guest pages redirect logged-in users; private pages redirect guests to login', function () use ($login) {
    $guest = new Client();
    assert_redirect($guest->get('/dashboard'), '/login');
    assert_contains('Please log in to continue.', $guest->follow()['body']);

    $c = new Client();
    $login($c, 'asha@example.com', 'campus123');
    foreach (['/login', '/register', '/forgot-password'] as $p) {
        assert_redirect($c->get($p), '/dashboard', $p);
    }
});

test('Logout: POST only, destroys session, old session cookie cannot be reused', function () use ($login, $logout) {
    $c = new Client();
    $login($c, 'asha@example.com', 'campus123');
    $c->get('/dashboard');
    $oldSid = $c->cookie(Session::NAME);

    assert_redirect($logout($c), '/');
    assert_contains('You have been logged out.', $c->follow()['body']);
    assert_redirect($c->get('/dashboard'), '/login', 'after logout');

    $thief = new Client();
    $thief->setSessionCookie($oldSid);
    assert_redirect($thief->get('/dashboard'), '/login', 'reusing old session ID');
});

test('Logout without a CSRF token is rejected (cannot be forced by another site)', function () use ($login) {
    $c = new Client();
    $login($c, 'asha@example.com', 'campus123');
    assert_same(419, $c->post('/logout', [])['status']);
    assert_same(200, $c->get('/dashboard')['status'], 'still logged in');
});

test('Deactivated user: login refused and existing session ended immediately', function () use ($login) {
    $c = new Client();
    $c->submit('/register', ['name' => 'Soon Inactive', 'email' => 'inactive@example.com',
        'password' => 'campus123', 'password_confirmation' => 'campus123']);
    assert_same(200, $c->get('/dashboard')['status']);

    test_pdo()->exec("UPDATE users SET is_active = 0 WHERE email = 'inactive@example.com'");
    assert_redirect($c->get('/dashboard'), '/login', 'existing session ended');

    $r = $login(new Client(), 'inactive@example.com', 'campus123');
    assert_same(403, $r['status']);
    assert_contains('This account has been deactivated.', $r['body']);
});

// --- Rate limiting ----------------------------------------------------------
test('Rate limit: 5 wrong passwords lock the email, even the right password is refused', function () use ($login) {
    $c = new Client();
    $c->submit('/register', ['name' => 'Locked Out', 'email' => 'locked@example.com',
        'password' => 'campus123', 'password_confirmation' => 'campus123']);
    for ($i = 1; $i <= 5; $i++) {
        assert_same(422, $login(new Client(), 'locked@example.com', "wrong{$i}xx")['status'], "attempt $i");
    }
    $r = $login(new Client(), 'locked@example.com', 'campus123');
    assert_same(429, $r['status']);
    assert_contains('Too many failed login attempts', $r['body']);
    assert_true((int) ($r['headers']['retry-after'][0] ?? 0) > 0, 'Retry-After header');

    // Other accounts from the same IP still work.
    assert_redirect($login(new Client(), 'asha@example.com', 'campus123'), '/dashboard');
});

test('Rate limit: lock expires after the 15-minute window', function () use ($login) {
    test_pdo()->exec("UPDATE login_attempts SET attempted_at = NOW() - INTERVAL 16 MINUTE WHERE email = 'locked@example.com'");
    assert_redirect($login(new Client(), 'locked@example.com', 'campus123'), '/dashboard');
});

// --- Forgot / reset password ------------------------------------------------
test('Forgot password: same response for unknown and known emails; email only for real account', function () use ($lastResetToken) {
    $r = (new Client())->submit('/forgot-password', ['email' => 'nobody@example.com']);
    assert_redirect($r, '/forgot-password');
    assert_same(null, $lastResetToken(), 'no email for unknown address');

    $c = new Client();
    $r2 = $c->submit('/forgot-password', ['email' => 'asha@example.com']);
    assert_redirect($r2, '/forgot-password');
    assert_contains('If an account exists for that email', $c->follow()['body']);
    assert_true($lastResetToken() !== null, 'reset email written');
});

test('Forgot password: a second request within a minute does not send another email', function () use ($mailLog) {
    $before = substr_count(file_get_contents($mailLog), 'Subject: Reset your CampusFind password');
    (new Client())->submit('/forgot-password', ['email' => 'asha@example.com']);
    assert_same($before, substr_count(file_get_contents($mailLog), 'Subject: Reset your CampusFind password'));
});

test('Reset password: bad token rejected; weak password rejected; valid reset works once', function () use ($lastResetToken, $login) {
    // A session logged in BEFORE the reset...
    $other = new Client();
    $login($other, 'asha@example.com', 'campus123');
    assert_same(200, $other->get('/dashboard')['status']);
    sleep(1); // password_changed_at has 1-second precision

    $token = $lastResetToken();
    $c = new Client();
    $bad = $c->get('/reset-password?token=' . str_repeat('0', 64));
    assert_same(400, $bad['status']);
    assert_contains('invalid or has expired', $bad['body']);

    $form = $c->get('/reset-password?token=' . $token);
    assert_same(200, $form['status']);
    $csrf = Client::csrf($form['body']);

    $weak = $c->post('/reset-password', ['token' => $token, 'password' => 'abc', 'password_confirmation' => 'abc', '_token' => $csrf]);
    assert_same(422, $weak['status']);

    $ok = $c->post('/reset-password', ['token' => $token, 'password' => 'newpass456', 'password_confirmation' => 'newpass456', '_token' => $csrf]);
    assert_redirect($ok, '/login');
    assert_contains('Your password has been reset', $c->follow()['body']);

    // ...is now signed out.
    assert_redirect($other->get('/dashboard'), '/login', 'old session invalidated by reset');

    assert_same(422, $login(new Client(), 'asha@example.com', 'campus123')['status'], 'old password');
    assert_redirect($login(new Client(), 'asha@example.com', 'newpass456'), '/dashboard', 'new password');

    $again = $c->get('/reset-password?token=' . $token);
    assert_same(400, $again['status'], 'token is single-use');
});

test('Reset password: expired token is rejected', function () use ($lastResetToken) {
    test_pdo()->exec("UPDATE password_resets SET created_at = NOW() - INTERVAL 2 MINUTE");
    (new Client())->submit('/forgot-password', ['email' => 'locked@example.com']);
    $token = $lastResetToken();
    test_pdo()->exec("UPDATE password_resets SET expires_at = NOW() - INTERVAL 1 MINUTE");
    assert_same(400, (new Client())->get('/reset-password?token=' . $token)['status']);
});

// --- Admin CLI + admin access -------------------------------------------------
test('CLI create-admin: weak password and mismatch are refused', function () use ($createAdminCli) {
    $r = $createAdminCli("boss@example.com\nBoss\nweak\nweak\n");
    assert_same(1, $r['code']);
    assert_contains('Password must be at least 8 characters.', $r['out']);
    $r = $createAdminCli("boss@example.com\nBoss\nadmin12345\nadmin12346\n");
    assert_same(1, $r['code']);
    assert_contains('Passwords do not match.', $r['out']);
    assert_same(null, User::findByEmail('boss@example.com'));
});

test('CLI create-admin: creates an admin; password never printed', function () use ($createAdminCli) {
    $r = $createAdminCli("Boss@Example.com\nCampus Admin\nadmin12345\nadmin12345\n");
    assert_same(0, $r['code'], $r['out']);
    assert_contains('Admin account created for boss@example.com', $r['out']);
    assert_not_contains('admin12345', $r['out']);
    $u = User::findByEmail('boss@example.com');
    assert_same('admin', $u['role']);
    assert_true(password_verify('admin12345', $u['password_hash']));
});

test('CLI create-admin: promotes an existing student after confirmation', function () use ($createAdminCli) {
    User::create(['name' => 'Staffer', 'email' => 'promote@example.com', 'password' => 'student123']);
    $r = $createAdminCli("promote@example.com\nn\n");
    assert_contains('No changes made.', $r['out']);
    assert_same('student', User::findByEmail('promote@example.com')['role']);

    $r = $createAdminCli("promote@example.com\ny\nadmin67890\nadmin67890\n");
    assert_same(0, $r['code'], $r['out']);
    assert_same('admin', User::findByEmail('promote@example.com')['role']);
});

test('Admin access: student gets 403, guest is sent to login, admin sees panel', function () use ($login) {
    $student = new Client();
    $login($student, 'asha@example.com', 'newpass456');
    assert_same(403, $student->get('/admin')['status']);
    assert_not_contains('href="/admin"', $student->get('/dashboard')['body'], 'no admin link for students');

    // Guest tries /admin, logs in as admin, lands back on /admin.
    $admin = new Client();
    assert_redirect($admin->get('/admin'), '/login');
    $r = $admin->submit('/login', ['email' => 'boss@example.com', 'password' => 'admin12345']);
    assert_redirect($r, '/admin', 'redirected to originally requested page');
    $panel = $admin->follow();
    assert_same(200, $panel['status']);
    assert_contains('Admin panel', $panel['body']);
    assert_contains('Total users', $panel['body']);
});

test('No PHP errors or warnings were logged during the HTTP tests', function () {
    $log = STORAGE_PATH . '/logs/php-error.log';
    $content = is_file($log) ? file_get_contents($log) : '';
    assert_true($content === '' || !str_contains($content, gmdate('d-M-Y')), "php-error.log has entries:\n" . substr($content, -800));
});
