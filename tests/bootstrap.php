<?php
/**
 * Tiny dependency-free test harness.
 *
 * Safety: before anything touches a database, DB_NAME is switched to the
 * TEST database (DB_TEST_NAME), which is dropped and rebuilt from
 * database/schema.sql + seed.sql once per run. The development database
 * is never used by tests.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Tests must be run from the command line.\n");
}

require_once __DIR__ . '/../app/config/config.php';

// --- point everything at the test database ---------------------------
$__testDb = env('DB_TEST_NAME', 'campusfind_test');
if ($__testDb === '' || $__testDb === env('DB_NAME')) {
    exit("Refusing to run: DB_TEST_NAME must be set and differ from DB_NAME.\n");
}
putenv('DB_NAME=' . $__testDb);
putenv('MAIL_LOG=' . STORAGE_PATH . '/logs/mail-test.log');
putenv('APP_DEBUG=false');

require_once __DIR__ . '/../app/bootstrap.php';

$GLOBALS['__tests'] = [];

function test(string $name, callable $fn): void
{
    $GLOBALS['__tests'][] = [$name, $fn];
}

final class AssertionFailed extends Exception {}

function assert_true(bool $cond, string $msg = 'Expected true'): void
{
    if (!$cond) {
        throw new AssertionFailed($msg);
    }
}

function assert_same(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new AssertionFailed(($msg ? "$msg: " : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assert_contains(string $needle, string $haystack, string $msg = ''): void
{
    if (!str_contains($haystack, $needle)) {
        throw new AssertionFailed(($msg ? "$msg: " : '') . "expected to find '$needle'");
    }
}

function assert_not_contains(string $needle, string $haystack, string $msg = ''): void
{
    if (str_contains($haystack, $needle)) {
        throw new AssertionFailed(($msg ? "$msg: " : '') . "did not expect to find '$needle'");
    }
}

/** Assert that $fn throws a PDOException whose SQLSTATE/driver code matches. */
function assert_db_error(callable $fn, string $sqlState, ?int $driverCode = null, string $msg = ''): void
{
    try {
        $fn();
    } catch (PDOException $e) {
        $state = $e->errorInfo[0] ?? (string) $e->getCode();
        $code  = $e->errorInfo[1] ?? null;
        if ($state !== $sqlState || ($driverCode !== null && $code !== $driverCode)) {
            throw new AssertionFailed(($msg ? "$msg: " : '') . "expected SQLSTATE $sqlState/" . ($driverCode ?? '*') . ", got $state/$code ({$e->getMessage()})");
        }
        return;
    }
    throw new AssertionFailed(($msg ? "$msg: " : '') . "expected a database error ($sqlState) but the query succeeded");
}

/** Split a .sql file into statements (our SQL files have no ';' inside strings). */
function sql_statements(string $file): array
{
    $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents($file));
    return array_values(array_filter(array_map('trim', explode(';', $sql)), fn ($s) => $s !== ''));
}

function rebuild_test_database(): void
{
    $name = env('DB_NAME');
    $server = make_pdo(null);
    $server->exec("DROP DATABASE IF EXISTS `$name`");
    $server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo = make_pdo($name);
    foreach (['schema.sql', 'seed.sql'] as $f) {
        foreach (sql_statements(BASE_PATH . '/database/' . $f) as $stmt) {
            $pdo->exec($stmt);
        }
    }
}

/** The shared test connection (same one the app code gets from db()). */
function test_pdo(): PDO
{
    return db();
}

function run_tests(): never
{
    $pass = $fail = 0;
    foreach ($GLOBALS['__tests'] as [$name, $fn]) {
        try {
            $fn();
            $pass++;
            echo "  \033[32m✓\033[0m $name\n";
        } catch (Throwable $e) {
            $fail++;
            echo "  \033[31m✗ $name\033[0m\n      " . get_class($e) . ': ' . $e->getMessage() . "\n";
        }
    }
    echo "\n" . ($fail ? "\033[31m" : "\033[32m") . "$pass passed, $fail failed\033[0m\n";
    exit($fail ? 1 : 0);
}

// =====================================================================
// HTTP testing: a real `php -S` server running against the test database
// =====================================================================

final class TestServer
{
    private static $proc = null;
    public static string $base = '';

    public static function start(): void
    {
        if (self::$proc) {
            return;
        }
        $sock = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($sock, false), ':'), 1);
        fclose($sock);

        $env = getenv() + ['DB_NAME' => env('DB_NAME')];
        $env['DB_NAME']  = env('DB_NAME');
        $env['MAIL_LOG'] = env('MAIL_LOG');
        $env['APP_DEBUG'] = 'false';
        $env['APP_URL']  = "http://127.0.0.1:$port";

        $cmd = [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', BASE_PATH . '/public', BASE_PATH . '/public/index.php'];
        self::$proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, BASE_PATH, $env);
        self::$base = "http://127.0.0.1:$port";

        for ($i = 0; $i < 50; $i++) {
            $c = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            if ($c) {
                fclose($c);
                register_shutdown_function([self::class, 'stop']);
                return;
            }
            usleep(100_000);
        }
        throw new RuntimeException('Test server did not start');
    }

    public static function stop(): void
    {
        if (self::$proc) {
            proc_terminate(self::$proc);
            self::$proc = null;
        }
    }
}

/** A browser-like client with its own cookie jar. */
final class Client
{
    private CurlHandle $ch;
    public array $last = [];

    public function __construct()
    {
        TestServer::start();
        $this->ch = curl_init();
        curl_setopt_array($this->ch, [
            CURLOPT_COOKIEFILE     => '',      // in-memory cookie jar
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PATH_AS_IS     => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
    }

    public function get(string $path): array
    {
        curl_setopt($this->ch, CURLOPT_HTTPGET, true);
        return $this->send($path);
    }

    public function post(string $path, array $fields): array
    {
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        return $this->send($path);
    }

    /** POST multipart/form-data (file uploads). $files: field name => local path. */
    public function postFiles(string $path, array $fields, array $files): array
    {
        $body = $fields;
        foreach ($files as $name => $file) {
            $body[$name] = new CURLFile($file, '', basename($file));
        }
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $body);
        return $this->send($path);
    }

    /** CSRF token from any page the client can open. */
    public function token(string $page = '/'): string
    {
        return self::csrf($this->get($page)['body']);
    }

    /** GET the form page, then POST with its CSRF token. */
    public function submit(string $formPath, array $fields, ?string $action = null): array
    {
        $page = $this->get($formPath);
        return $this->post($action ?? $formPath, $fields + ['_token' => self::csrf($page['body'])]);
    }

    /** Follow a redirect returned by the previous response. */
    public function follow(): array
    {
        $loc = $this->last['headers']['location'][0] ?? null;
        if ($loc === null) {
            throw new AssertionFailed('Expected a redirect, got status ' . $this->last['status']);
        }
        return $this->get(parse_url($loc, PHP_URL_PATH) . (($q = parse_url($loc, PHP_URL_QUERY)) ? "?$q" : ''));
    }

    public function cookie(string $name): ?string
    {
        foreach (curl_getinfo($this->ch, CURLINFO_COOKIELIST) as $line) {
            $parts = explode("\t", $line);
            if (($parts[5] ?? '') === $name) {
                return $parts[6] ?? null;
            }
        }
        return null;
    }

    /** Replace the session cookie (used to test reuse of a stolen/old session ID). */
    public function setSessionCookie(string $value): void
    {
        curl_setopt($this->ch, CURLOPT_COOKIELIST, 'ALL');
        curl_setopt($this->ch, CURLOPT_COOKIELIST, "Set-Cookie: " . Session::NAME . "=$value; path=/");
    }

    public static function csrf(string $html): string
    {
        if (!preg_match('/name="_token" value="([a-f0-9]{64})"/', $html, $m)) {
            throw new AssertionFailed('No CSRF token found in page');
        }
        return $m[1];
    }

    private function send(string $path): array
    {
        curl_setopt($this->ch, CURLOPT_URL, TestServer::$base . $path);
        $raw = curl_exec($this->ch);
        if ($raw === false) {
            throw new RuntimeException('HTTP request failed: ' . curl_error($this->ch));
        }
        $headerSize = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
        $headers = [];
        foreach (explode("\r\n", substr($raw, 0, $headerSize)) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $headers[strtolower(trim($k))][] = trim($v);
            }
        }
        return $this->last = [
            'status'  => curl_getinfo($this->ch, CURLINFO_RESPONSE_CODE),
            'headers' => $headers,
            'body'    => substr($raw, $headerSize),
        ];
    }
}

function assert_redirect(array $res, string $path, string $msg = ''): void
{
    $loc = $res['headers']['location'][0] ?? '(none)';
    assert_true(in_array($res['status'], [301, 302, 303], true), ($msg ? "$msg: " : '') . "expected redirect, got {$res['status']}");
    assert_same($path, parse_url($loc, PHP_URL_PATH), ($msg ? "$msg: " : '') . 'redirect target');
}

rebuild_test_database();
