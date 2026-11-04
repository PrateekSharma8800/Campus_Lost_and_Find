<?php
/**
 * Create (or promote) a CampusFind administrator.
 *
 *   php bin/create-admin.php
 *
 * The password is typed at a hidden prompt: it is never passed as a
 * command-line argument (those end up in shell history and `ps` output).
 * When stdin is not a terminal (e.g. piped by tests), one answer per line
 * is read instead: email, [y if account exists], [name if new], password, confirmation.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../app/bootstrap.php';

$interactive = stream_isatty(STDIN);

function out(string $text): void
{
    fwrite(STDOUT, $text . PHP_EOL);
}

function fail(string $text): never
{
    fwrite(STDERR, 'Error: ' . $text . PHP_EOL);
    exit(1);
}

function ask(string $prompt, bool $hidden = false): string
{
    global $interactive;
    fwrite(STDOUT, $prompt);
    if ($hidden && $interactive) {
        shell_exec('stty -echo');
    }
    $line = fgets(STDIN);
    if ($hidden && $interactive) {
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
    }
    if ($line === false) {
        fail('No input. Aborted.');
    }
    return $hidden ? rtrim($line, "\r\n") : trim($line);
}

/** Ask until the answer passes validation (interactive) or fail (piped). */
function askValid(string $prompt, string $field, array $rules, bool $hidden = false): string
{
    global $interactive;
    while (true) {
        $value = ask($prompt, $hidden);
        $v = Validator::make([$field => $value], [$field => $rules]);
        if (!$v->fails()) {
            return $value;
        }
        $msg = $v->errors()[$field];
        if (!$interactive) {
            fail($msg);
        }
        out("  ✗ $msg");
    }
}

// Make sure the terminal echo comes back even if the user presses Ctrl+C.
if ($interactive && function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function () {
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL . 'Aborted.' . PHP_EOL);
        exit(1);
    });
}

out('CampusFind: create an administrator account');
out('--------------------------------------------');

try {
    db()->query('SELECT 1 FROM users LIMIT 1');
} catch (PDOException $e) {
    fail('Cannot reach the database. Check DB_* settings in .env and that schema.sql is loaded.');
}

$email = User::normalizeEmail(askValid('Email: ', 'email', User::EMAIL_RULES));
$existing = User::findByEmail($email);

if ($existing) {
    $what = $existing['role'] === 'admin'
        ? 'is already an admin. Set a new password for it?'
        : "exists as a {$existing['role']}. Promote it to admin and set a new password?";
    $answer = strtolower(ask("An account for $email $what [y/N]: "));
    if ($answer !== 'y' && $answer !== 'yes') {
        out('No changes made.');
        exit(0);
    }
} else {
    $name = askValid('Full name: ', 'name', User::NAME_RULES);
}

while (true) {
    $password = askValid('Password (min 8 chars, letter + number): ', 'password', User::PASSWORD_RULES, true);
    $confirm  = ask('Confirm password: ', true);
    if (hash_equals($password, $confirm)) {
        break;
    }
    if (!$interactive) {
        fail('Passwords do not match.');
    }
    out('  ✗ Passwords do not match. Try again.');
}

if ($existing) {
    $pdo = db();
    $pdo->beginTransaction();
    User::promoteToAdmin((int) $existing['id']);
    User::updatePassword((int) $existing['id'], $password);
    $pdo->commit();
    RateLimiter::clear($email);
    out("✓ $email is now an admin. Its password was changed and any existing sessions were signed out.");
} else {
    User::create(['name' => $name, 'email' => $email, 'password' => $password, 'role' => 'admin']);
    out("✓ Admin account created for $email.");
}
out('  Log in at ' . absolute_url('/login'));
