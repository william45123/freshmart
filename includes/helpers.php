<?php
/**
 * Generic Helpers
 * ----------------------------------------------------------------
 * String, currency, date, URL, and escaping utilities used everywhere.
 */

require_once __DIR__ . '/config.php';

// =====================================================================
// Output safety
// =====================================================================

/** Escape for HTML output (prevents XSS). Use everywhere you echo user data. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escape for HTML attribute (same as e() but with explicit intent). */
function attr(?string $value): string
{
    return e($value);
}

// =====================================================================
// Currency (MYR)
// =====================================================================

/** Format MYR — e.g. format_myr(12.5) → "RM 12.50" */
function format_myr($amount, bool $withSymbol = true): string
{
    $formatted = number_format((float) $amount, 2, '.', ',');
    return $withSymbol ? APP_CURRENCY_SYMBOL . ' ' . $formatted : $formatted;
}

// =====================================================================
// Dates (always GMT+8)
// =====================================================================

/** Now() as DateTimeImmutable in Malaysia time. */
function now_my(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));
}

/** Format a datetime/date string for display. */
function format_datetime($value, string $format = 'd M Y, h:i A'): string
{
    if (!$value) return '';
    $dt = $value instanceof DateTimeInterface
        ? $value
        : new DateTimeImmutable($value, new DateTimeZone(APP_TIMEZONE));
    return $dt->format($format);
}

function format_date($value, string $format = 'd M Y'): string
{
    return format_datetime($value, $format);
}

/** Days between two date strings (positive = $b is later). */
function days_between($a, $b): int
{
    $da = new DateTimeImmutable($a, new DateTimeZone(APP_TIMEZONE));
    $db = new DateTimeImmutable($b, new DateTimeZone(APP_TIMEZONE));
    $diff = $da->diff($db);
    return $diff->invert ? -$diff->days : $diff->days;
}

/** Human-friendly "in 3 days", "today", "2 days ago" — for expiry hints. */
function relative_date($date): string
{
    $today = now_my()->setTime(0, 0, 0);
    $target = (new DateTimeImmutable($date, new DateTimeZone(APP_TIMEZONE)))->setTime(0, 0, 0);
    $days = (int) $today->diff($target)->format('%r%a');

    if ($days === 0)  return 'today';
    if ($days === 1)  return 'tomorrow';
    if ($days === -1) return 'yesterday';
    if ($days > 0)    return "in $days days";
    return abs($days) . ' days ago';
}

// =====================================================================
// URL / path helpers
// =====================================================================

function url(string $path = ''): string
{
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function upload_url(string $filename): string
{
    return url('uploads/' . ltrim($filename, '/'));
}

function current_path(): string
{
    return strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
}

function redirect(string $to): void
{
    header('Location: ' . (str_starts_with($to, 'http') ? $to : url($to)));
    exit;
}

// =====================================================================
// Strings
// =====================================================================

/** Make a URL-safe slug from text. */
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

/** Generate a unique-ish order number: FM-20260519-XXXX */
function generate_order_number(): string
{
    return sprintf('FM-%s-%04d', now_my()->format('Ymd'), random_int(1, 9999));
}

/** Random hex token for password resets etc. */
function random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

// =====================================================================
// Flash messages (for redirects after POST)
// =====================================================================

function flash_set(string $type, string $message): void
{
    _ensure_session();
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get(): array
{
    _ensure_session();
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

/** Internal: start the session with the proper name. */
function _ensure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    if (defined('SESSION_NAME')) session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 0,
        'path'     => '/',
        'secure'   => defined('APP_ENV') && APP_ENV === 'production',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// =====================================================================
// Request helpers
// =====================================================================

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function input(string $key, $default = null)
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

/** CSRF token (per session). */
function csrf_token(): string
{
    _ensure_session();
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = random_token(32);
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . attr(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $token = $_POST['_csrf'] ?? '';
    return is_string($token) && hash_equals(csrf_token(), $token);
}

/**
 * Inline SVG icon (Lucide set) — replaces emoji for a consistent, professional look.
 * Uses currentColor so it inherits text color (works on light + dark headers).
 */
function icon(string $name, int $size = 20, float $stroke = 1.75): string
{
    static $paths = [
        'cart'    => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>',
        'bell'    => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'heart'   => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>',
        'package' => '<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
        'search'  => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'leaf'    => '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6"/>',
        'arrow'   => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'flame'   => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5Z"/>',
        'user'    => '<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/>',
        'x'       => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'menu'    => '<path d="M4 12h16"/><path d="M4 6h16"/><path d="M4 18h16"/>',
        'pin'     => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'sparkles'=> '<path d="M9.94 14.06A2 2 0 0 0 8.5 12.62l-5.62-1.45a.5.5 0 0 1 0-.96L8.5 8.76A2 2 0 0 0 9.94 7.3l1.45-5.62a.5.5 0 0 1 .96 0L13.8 7.3a2 2 0 0 0 1.44 1.44l5.62 1.45a.5.5 0 0 1 0 .96l-5.62 1.45a2 2 0 0 0-1.44 1.44l-1.45 5.62a.5.5 0 0 1-.96 0Z"/><path d="M20 3v4"/><path d="M22 5h-4"/>',
        'check'   => '<path d="M20 6 9 17l-5-5"/>',
        'alert'   => '<path d="m21.7 18-8-14a2 2 0 0 0-3.4 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'inbox'   => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
    ];
    $p = $paths[$name] ?? '';
    if ($p === '') return '';
    return sprintf(
        '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="%2$s" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%3$s</svg>',
        $size, $stroke, $p
    );
}
