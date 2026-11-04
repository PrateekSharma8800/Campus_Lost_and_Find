<?php
/**
 * Base controller with small request/response helpers.
 */

declare(strict_types=1);

abstract class Controller
{
    protected function view(string $view, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        View::render($view, $data);
    }

    /** A trimmed string from POST data; arrays and missing keys become ''. */
    protected function input(string $key): string
    {
        $v = $_POST[$key] ?? '';
        return is_string($v) ? trim($v) : '';
    }

    /** Raw (untrimmed) string: used for passwords, where spaces are allowed. */
    protected function rawInput(string $key): string
    {
        $v = $_POST[$key] ?? '';
        return is_string($v) ? $v : '';
    }

    protected function query(string $key): string
    {
        $v = $_GET[$key] ?? '';
        return is_string($v) ? trim($v) : '';
    }
}
