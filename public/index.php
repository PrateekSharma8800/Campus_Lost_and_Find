<?php
/**
 * Front controller: every request enters here.
 *
 * Local dev:  php -S localhost:8000 -t public public/index.php
 */

declare(strict_types=1);

// PHP built-in server: let it serve real static files from public/ directly
// (CSS, JS, images), but never dotfiles or PHP files.
if (PHP_SAPI === 'cli-server') {
    $path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $root = realpath(__DIR__);
    $file = realpath(__DIR__ . rawurldecode($path));
    if (
        $path !== '/'
        && $file !== false
        && is_file($file)
        && str_starts_with($file, $root . DIRECTORY_SEPARATOR)
        && !str_contains($path, '/.')
        && strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php'
    ) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

$router = require APP_PATH . '/routes.php';
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
