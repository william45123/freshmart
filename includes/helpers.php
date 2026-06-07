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
