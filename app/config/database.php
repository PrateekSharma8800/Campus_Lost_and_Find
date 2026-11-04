<?php
/**
 * PDO database connection.
 *
 * Security settings:
 *  - ERRMODE_EXCEPTION: failures throw instead of failing silently.
 *  - EMULATE_PREPARES = false: real server-side prepared statements, so
 *    user input is always sent separately from the SQL text.
 *  - MULTI_STATEMENTS = false: a query can never contain a second, stacked
 *    statement (blocks "...; DROP TABLE users" style injection).
 *  - utf8mb4 charset in the DSN and a UTC session time zone.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Build a new connection. $dbName = null connects to the server without
 * selecting a database (used by the test suite to create its own DB).
 */
function make_pdo(?string $dbName = ''): PDO
{
    $dbName = $dbName === '' ? env('DB_NAME', 'campusfind') : $dbName;

    $dsn = sprintf(
        'mysql:host=%s;port=%s;charset=utf8mb4%s',
        env('DB_HOST', '127.0.0.1'),
        env('DB_PORT', '3306'),
        $dbName !== null ? ';dbname=' . $dbName : ''
    );

    // PHP 8.4+ moved driver constants to Pdo\Mysql; support both.
    $multiStatements = class_exists(\Pdo\Mysql::class)
        ? \Pdo\Mysql::ATTR_MULTI_STATEMENTS
        : PDO::MYSQL_ATTR_MULTI_STATEMENTS;

    $pdo = new PDO($dsn, env('DB_USER', ''), env('DB_PASS', ''), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        $multiStatements             => false,
    ]);
    $pdo->exec("SET time_zone = '+00:00'");

    return $pdo;
}

/** Shared application connection (created once per request). */
function db(): PDO
{
    static $pdo = null;
    return $pdo ??= make_pdo();
}
