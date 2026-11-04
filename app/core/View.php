<?php
/**
 * Renders PHP view templates inside a layout.
 * View names are always chosen by code, never by user input.
 */

declare(strict_types=1);

final class View
{
    public static function render(string $view, array $data = [], string $layout = 'main'): void
    {
        $content = self::capture($view, $data);
        echo self::capture('layouts/' . $layout, $data + ['content' => $content]);
    }

    public static function partial(string $name, array $data = []): void
    {
        echo self::capture('partials/' . $name, $data);
    }

    private static function capture(string $view, array $data): string
    {
        $file = APP_PATH . '/views/' . $view . '.php';
        if (!preg_match('#^[a-z0-9_/-]+$#', $view) || !is_file($file)) {
            throw new RuntimeException("View not found: $view");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
