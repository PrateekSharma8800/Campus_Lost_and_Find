<?php
/**
 * Application bootstrap: shared by the web front controller, CLI scripts
 * and the test suite.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/functions.php';

// Autoload classes from core/, controllers/ and models/ (no namespaces).
spl_autoload_register(function (string $class): void {
    if (!preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $class)) {
        return;
    }
    foreach (['core', 'controllers', 'models'] as $dir) {
        $file = APP_PATH . "/$dir/$class.php";
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

// Turn PHP warnings/notices into exceptions so bugs never pass silently.
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (Throwable $e): void {
    error_log(sprintf('[%s] %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    // Details only in debug mode; never show stack traces to real users.
    $message = APP_DEBUG ? get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' : null;
    try {
        View::render('errors/error', ['title' => 'Something went wrong', 'code' => 500,
            'message' => $message ?? 'An unexpected error occurred. Please try again later.']);
    } catch (Throwable) {
        echo 'An unexpected error occurred.';
    }
});

if (PHP_SAPI !== 'cli') {
    send_security_headers();
    Session::start();
    Auth::user(); // load + validate the logged-in user before any output
}
