<?php
/**
 * Unit tests for core building blocks: escaping, redirects, validation,
 * CSRF, session timeouts, rate limiter, password-reset tokens.
 */

declare(strict_types=1);

// --- escaping & redirects --------------------------------------------
test('e() escapes HTML special characters and quotes', function () {
    assert_same('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; &apos;', e('<script>alert("x")</script> & \''));
});

test('safe_redirect_path() allows local paths only', function () {
    assert_same('/admin?tab=1', safe_redirect_path('/admin?tab=1'));
    foreach (['https://evil.com', '//evil.com', '/\\evil.com', 'evil.com', '', null, "/ok\r\nSet-Cookie: x"] as $bad) {
        assert_same('/dashboard', safe_redirect_path($bad, '/dashboard'), var_export($bad, true));
    }
});

// --- Validator --------------------------------------------------------
test('Validator: required / email / min / max', function () {
    $v = Validator::make(['a' => '', 'b' => 'not-an-email', 'c' => 'ab', 'd' => str_repeat('x', 11)],
        ['a' => ['required'], 'b' => ['email'], 'c' => ['min:3'], 'd' => ['max:10']]);
    assert_same(['a', 'b', 'c', 'd'], array_keys($v->errors()));
    assert_same('A is required.', $v->errors()['a']);
});

test('Validator: valid data passes', function () {
    $v = Validator::make(['email' => 'student@college.edu', 'password' => 'abc12345', 'password_confirmation' => 'abc12345'],
        ['email' => User::EMAIL_RULES, 'password' => array_merge(User::PASSWORD_RULES, ['same:password_confirmation'])]);
    assert_true(!$v->fails(), json_encode($v->errors()));
});

test('Validator: password needs letter + number, 8+ chars, max 72 bytes', function () {
    foreach (['short1', 'onlyletters', '12345678', str_repeat('a1', 37)] as $bad) {
        assert_true(Validator::make(['p' => $bad], ['p' => User::PASSWORD_RULES])->fails(), $bad);
    }
    assert_true(!Validator::make(['p' => 'pässwört9'], ['p' => User::PASSWORD_RULES])->fails(), 'unicode letters allowed');
});

test('Validator: "same" rule detects mismatch', function () {
    $v = Validator::make(['p' => 'abc12345', 'p2' => 'abc12346'], ['p' => ['same:p2']]);
    assert_same('P does not match.', $v->errors()['p'] ?? null);
});

test('Validator: nullable fields may be empty but are validated when filled', function () {
    $rules = ['phone' => ['nullable', 'regex:/^\+?[0-9\s\-()]{7,20}$/']];
    assert_true(!Validator::make(['phone' => ''], $rules)->fails());
    assert_true(Validator::make(['phone' => 'call me'], $rules)->fails());
});

test('Validator: arrays and invalid UTF-8 are rejected', function () {
    assert_true(Validator::make(['email' => ['x@y.com']], ['email' => ['required']])->fails(), 'array');
    assert_true(Validator::make(['name' => "\xC3\x28"], ['name' => ['required']])->fails(), 'bad utf-8');
});

test('Validator: name rejects HTML brackets and control characters', function () {
    assert_true(Validator::make(['n' => '<b>Bob</b>'], ['n' => User::NAME_RULES])->fails());
    assert_true(Validator::make(['n' => "Bob\x07"], ['n' => User::NAME_RULES])->fails());
    assert_true(!Validator::make(['n' => "Anjali D'Souza-Rao"], ['n' => User::NAME_RULES])->fails());
});

// --- CSRF -------------------------------------------------------------
test('CSRF: token is 64 hex chars, stable, verified in constant time', function () {
    $_SESSION = [];
    $t = Csrf::token();
    assert_true((bool) preg_match('/^[a-f0-9]{64}$/', $t));
    assert_same($t, Csrf::token());
    assert_true(Csrf::verify($t));
    assert_true(!Csrf::verify(null));
    assert_true(!Csrf::verify(''));
    assert_true(!Csrf::verify(str_repeat('0', 64)));
});

test('CSRF: rotate() invalidates the old token', function () {
    $_SESSION = [];
    $old = Csrf::token();
    Csrf::rotate();
    assert_true(!Csrf::verify($old));
    assert_true(Csrf::verify(Csrf::token()));
});

// --- Session timeouts -------------------------------------------------
test('Session: idle timeout and absolute lifetime', function () {
    $now = 1_000_000;
    assert_true(!Session::isExpired([], $now), 'new session');
    assert_true(!Session::isExpired(['_last_activity' => $now - 60, '_created' => $now - 3600], $now), 'active');
    assert_true(Session::isExpired(['_last_activity' => $now - Session::IDLE_TIMEOUT - 1, '_created' => $now - 10], $now), 'idle');
    assert_true(Session::isExpired(['_last_activity' => $now, '_created' => $now - Session::ABSOLUTE_TIMEOUT - 1], $now), 'absolute');
});

// --- RateLimiter ------------------------------------------------------
test('RateLimiter: locks an email after 5 failures, clear() unlocks', function () {
    $email = 'rl-unit@example.com';
    for ($i = 0; $i < RateLimiter::MAX_PER_EMAIL - 1; $i++) {
        RateLimiter::hit($email, '10.0.0.1');
    }
    assert_same(0, RateLimiter::lockedFor($email, '10.0.0.1'), 'not yet locked after 4');
    RateLimiter::hit($email, '10.0.0.1');
    $wait = RateLimiter::lockedFor($email, '10.0.0.1');
    assert_true($wait > 0 && $wait <= RateLimiter::WINDOW_MINUTES * 60, "locked for $wait s");
    RateLimiter::clear($email);
    assert_same(0, RateLimiter::lockedFor($email, '10.0.0.1'));
});

test('RateLimiter: locks an IP after 20 failures across many emails', function () {
    for ($i = 0; $i < RateLimiter::MAX_PER_IP; $i++) {
        RateLimiter::hit("spray$i@example.com", '10.0.0.99');
    }
    assert_true(RateLimiter::lockedFor('fresh@example.com', '10.0.0.99') > 0, 'IP locked');
    assert_same(0, RateLimiter::lockedFor('fresh@example.com', '10.0.0.100'), 'other IP fine');
});

test('RateLimiter: failures older than the window no longer count', function () {
    $email = 'rl-old@example.com';
    for ($i = 0; $i < 5; $i++) {
        RateLimiter::hit($email, '10.0.0.2');
    }
    test_pdo()->prepare('UPDATE login_attempts SET attempted_at = NOW() - INTERVAL 16 MINUTE WHERE email = ?')->execute([$email]);
    assert_same(0, RateLimiter::lockedFor($email, '10.0.0.2'));
});

// --- Auth::attempt ----------------------------------------------------
test('Auth::attempt: ok / failed / inactive, and password is stored hashed', function () {
    $id = User::create(['name' => 'Unit', 'email' => 'Attempt@Example.com', 'password' => 'right123']);
    $u = User::findById($id);
    assert_same('attempt@example.com', $u['email'], 'email normalized');
    assert_true(str_starts_with($u['password_hash'], '$2y$'), 'bcrypt hash');
    assert_true($u['password_hash'] !== 'right123');

    assert_same('ok', Auth::attempt('ATTEMPT@example.com', 'right123', '10.0.0.3')['status']);
    assert_same('failed', Auth::attempt('attempt@example.com', 'wrong123', '10.0.0.3')['status']);
    assert_same('failed', Auth::attempt('nobody@example.com', 'right123', '10.0.0.3')['status']);

    test_pdo()->exec("UPDATE users SET is_active = 0 WHERE id = $id");
    assert_same('inactive', Auth::attempt('attempt@example.com', 'right123', '10.0.0.3')['status']);
});

test('Auth::attempt: unknown email takes about as long as a wrong password (no timing leak)', function () {
    User::create(['name' => 'Timing', 'email' => 'timing@example.com', 'password' => 'right123']);
    $time = function (string $email): float {
        $t = hrtime(true);
        Auth::attempt($email, 'wrong123', '10.0.0.4');
        return (hrtime(true) - $t) / 1e6;
    };
    $known = $time('timing@example.com');
    $unknown = $time('nobody-timing@example.com');
    assert_true($unknown > $known * 0.5, sprintf('unknown %.1fms vs known %.1fms', $unknown, $known));
});

// --- Password reset tokens ---------------------------------------------
test('PasswordReset: token stored only as SHA-256 hash; valid once; expires', function () {
    $id = User::create(['name' => 'Reset', 'email' => 'reset-unit@example.com', 'password' => 'right123']);
    $token = PasswordReset::create($id);
    assert_true((bool) preg_match('/^[a-f0-9]{64}$/', $token));

    $stored = test_pdo()->query("SELECT token_hash FROM password_resets WHERE user_id = $id")->fetchColumn();
    assert_same(hash('sha256', $token), $stored);
    assert_true($stored !== $token);

    $row = PasswordReset::findValid($token);
    assert_true($row !== null, 'valid');
    assert_same(null, PasswordReset::findValid('not-a-token'));
    assert_same(null, PasswordReset::findValid(str_repeat('a', 64)));

    PasswordReset::consume((int) $row['id'], $id);
    assert_same(null, PasswordReset::findValid($token), 'single use');

    $t2 = PasswordReset::create($id);
    test_pdo()->exec("UPDATE password_resets SET expires_at = NOW() - INTERVAL 1 SECOND WHERE user_id = $id");
    assert_same(null, PasswordReset::findValid($t2), 'expired');
});

test('PasswordReset: a new request replaces the previous token', function () {
    $id = User::create(['name' => 'Reset2', 'email' => 'reset-unit2@example.com', 'password' => 'right123']);
    $first = PasswordReset::create($id);
    $second = PasswordReset::create($id);
    assert_same(null, PasswordReset::findValid($first));
    assert_true(PasswordReset::findValid($second) !== null);
    assert_true(PasswordReset::recentlyRequested($id));
});
