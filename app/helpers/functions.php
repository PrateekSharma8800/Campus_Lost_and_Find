<?php
/**
 * Global helper functions used by controllers and views.
 */

declare(strict_types=1);

/** Escape a value for safe output in HTML (text and attribute context). */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Absolute URL for links that leave the site (e.g. password-reset emails). */
function absolute_url(string $path): string
{
    return APP_URL . '/' . ltrim($path, '/');
}

/** Current request path without query string, e.g. "/login". */
function current_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = is_string($path) ? rawurldecode($path) : '/';
    return $path === '/' ? '/' : rtrim($path, '/');
}

/**
 * Only allow local, same-site paths as redirect targets ("/dashboard").
 * Rejects absolute URLs and protocol-relative "//evil.com" or "/\evil.com"
 * tricks, which would otherwise create an open redirect.
 */
function safe_redirect_path(?string $path, string $fallback = '/'): string
{
    if (!is_string($path) || $path === '' || $path[0] !== '/') {
        return $fallback;
    }
    if (str_starts_with($path, '//') || str_contains($path, '\\') || preg_match('/[\x00-\x1F\x7F]/', $path)) {
        return $fallback;
    }
    return $path;
}

function redirect(string $path, int $status = 302): never
{
    header('Location: ' . safe_redirect_path($path), true, $status);
    exit;
}

/** Hidden CSRF input for every POST form. */
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

/** " is-invalid" if the field has a validation error (Bootstrap styling). */
function invalid(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' is-invalid' : '';
}

/** Bootstrap invalid-feedback block for a field, escaped. */
function feedback(array $errors, string $field): string
{
    return isset($errors[$field])
        ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>'
        : '';
}

/** Client IP. REMOTE_ADDR only: X-Forwarded-For is attacker-controlled. */
function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

/** Stop the request with an error page (404, 403, 419, 429 ...). */
function abort(int $code, ?string $message = null): never
{
    $titles = [
        400 => 'Bad request',
        403 => 'Access denied',
        404 => 'Page not found',
        405 => 'Method not allowed',
        413 => 'Upload too large',
        419 => 'Page expired',
        429 => 'Too many requests',
        500 => 'Something went wrong',
    ];
    $defaults = [
        403 => 'You do not have permission to view this page.',
        404 => 'The page you are looking for does not exist.',
        405 => 'This action is not allowed here.',
        419 => 'Your form expired for security reasons. Please go back, refresh the page and try again.',
        429 => 'Please wait a little and try again.',
        500 => 'An unexpected error occurred. Please try again later.',
    ];
    http_response_code($code);
    View::render('errors/error', [
        'title'   => $titles[$code] ?? 'Error',
        'code'    => $code,
        'message' => $message ?? ($defaults[$code] ?? ''),
    ]);
    exit;
}

/** Security headers sent with every HTML response. */
function send_security_headers(): void
{
    header_remove('X-Powered-By');
    header("Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' https://cdn.jsdelivr.net; "
        . "style-src 'self' https://cdn.jsdelivr.net; "
        . "font-src 'self' https://cdn.jsdelivr.net; "
        . "img-src 'self' data: blob:; "
        . "form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    // Dynamic pages are never cached, so "Back" after logout can't show private pages.
    header('Cache-Control: no-store, max-age=0');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

// ---------------------------------------------------------------------
// Item display helpers
// ---------------------------------------------------------------------

/** URL of an uploaded item photo (served through PHP, see ItemController::photo). */
function photo_url(string $fileName): string
{
    return '/uploads/' . rawurlencode($fileName);
}

/** "12 Mar 2026" */
function format_date(?string $date): string
{
    return $date ? date('d M Y', strtotime($date . ' UTC')) : '';
}

/** "5 minutes ago", "3 days ago" ... */
function time_ago(string $datetime): string
{
    $seconds = time() - strtotime($datetime . ' UTC');
    $units = ['year' => 31536000, 'month' => 2592000, 'week' => 604800, 'day' => 86400, 'hour' => 3600, 'minute' => 60];
    foreach ($units as $name => $size) {
        if ($seconds >= $size) {
            $count = intdiv($seconds, $size);
            return $count . ' ' . $name . ($count > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}

/** Coloured "Lost" / "Found" pill. */
function type_badge(string $type): string
{
    return $type === 'lost'
        ? '<span class="pill pill-lost"><i class="bi bi-exclamation-circle"></i>Lost</span>'
        : '<span class="pill pill-found"><i class="bi bi-check-circle"></i>Found</span>';
}

/** Coloured status pill: open, claimed, returned, closed (and claim statuses). */
function status_badge(string $status): string
{
    $labels = [
        'open' => 'Open', 'claimed' => 'Claimed', 'returned' => 'Returned', 'closed' => 'Closed',
        'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected',
    ];
    $label = $labels[$status] ?? ucfirst($status);
    return '<span class="pill pill-status-' . e($status) . '">' . e($label) . '</span>';
}

/** Build a URL to the browse page keeping current filters, e.g. for pagination. */
function items_url(array $filters, array $changes = []): string
{
    $query = array_filter(array_merge($filters, $changes), fn ($v) => $v !== '' && $v !== null && $v !== 1);
    return '/items' . ($query ? '?' . http_build_query($query) : '');
}
