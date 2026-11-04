<?php
/**
 * Application bootstrap config.
 *
 * Loads key=value pairs from the project's .env file (credentials are never
 * hard-coded in PHP files) and defines core constants.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('UPLOAD_PATH', STORAGE_PATH . '/uploads/items');

/**
 * Minimal .env parser: KEY=VALUE per line, # comments, optional quotes.
 * Values already present in the real environment take precedence.
 */
function load_env(string $file): void
{
    if (!is_readable($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
            $value = substr($value, 1, -1);
        }
        if (getenv($key) === false) {
            $_ENV[$key] = $value;
        }
    }
}

function env(string $key, ?string $default = null): ?string
{
    $real = getenv($key);
    if ($real !== false) {
        return $real;
    }
    return $_ENV[$key] ?? $default;
}

load_env(BASE_PATH . '/.env');

define('APP_DEBUG', filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN));
define('APP_URL', rtrim(env('APP_URL', 'http://localhost:8000'), '/'));

// Everything is stored and computed in UTC; views convert for display.
date_default_timezone_set('UTC');

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');
