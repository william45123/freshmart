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

/**
 * SQL fragment for "this batch is still good on the earliest day it could be
 * delivered". Written once so the catalogue and checkout cannot drift apart.
 *
 * At DELIVERY_LEAD_DAYS = 1 this is identical to the old `> CURDATE()` on a
 * DATE column; the point is that the rule now states its reason and follows
 * the lead time if it ever changes.
 */
function sql_deliverable(string $col = 'expiry_date'): string
{
    return "$col >= DATE_ADD(CURDATE(), INTERVAL " . (int) DELIVERY_LEAD_DAYS . " DAY)";
}

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
        // Phase 3 / C8 — extended set so UI chrome never falls back to emoji.
        'wallet'          => '<path d="M19 7V5a2 2 0 0 0-2-2H5a2 2 0 0 0 0 4h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5"/><path d="M17.5 12a.5.5 0 1 0 0 1 .5.5 0 0 0 0-1"/>',
        'truck'           => '<path d="M14 18V6a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h2"/><path d="M14 9h4l3 3v5a1 1 0 0 1-1 1h-1"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'lock'            => '<rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'mail'            => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
        'phone'           => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92"/>',
        'ticket'          => '<path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/>',
        'recycle'         => '<path d="M12 3.5 14.2 7.3"/><path d="m12 3.5-2.2 3.8"/><path d="M9.8 7.3H14.2"/><path d="m5.6 15.6-2.2-3.8a1.6 1.6 0 0 1 1.4-2.4h3"/><path d="m6.6 12.4-1 3.2 3.2 1"/><path d="m18.4 15.6 2.2-3.8a1.6 1.6 0 0 0-1.4-2.4h-3"/><path d="m17.4 12.4 1 3.2-3.2 1"/><path d="M8.2 20.5h7.6a1.6 1.6 0 0 0 1.4-2.4"/><path d="M6.8 18.1a1.6 1.6 0 0 0 1.4 2.4"/>',
        'store'           => '<path d="m2 7 1.5-4h17L22 7"/><path d="M2 7h20v3a3 3 0 0 1-6 0 3 3 0 0 1-6 0 3 3 0 0 1-6 0Z"/><path d="M4 12v8a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-8"/>',
        'chart'           => '<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="m7 15 3-4 3 3 5-6"/>',
        'coins'           => '<circle cx="9" cy="9" r="6"/><path d="M15.5 4.2a6 6 0 0 1 0 15.6"/><path d="M9 6v6l3 2"/>',
        'lightbulb'       => '<path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a6 6 0 0 0-3.6 10.8c.6.5.9 1.1 1 1.7l.1.5h5l.1-.5c.1-.6.4-1.2 1-1.7A6 6 0 0 0 12 2"/>',
        'star'            => '<path d="m12 3 2.9 5.8 6.4.9-4.6 4.5 1.1 6.4L12 17.6 6.2 20.6l1.1-6.4L2.7 9.7l6.4-.9Z"/>',
        'clock'           => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'calendar'        => '<rect width="18" height="17" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="M8 2v4"/><path d="M16 2v4"/>',
        'trash'           => '<path d="M3 6h18"/><path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>',
        'edit'            => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.4 2.6a2 2 0 0 1 3 3L12 15l-4 1 1-4Z"/>',
        'plus'            => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'minus'           => '<path d="M5 12h14"/>',
        'chevron-down'    => '<path d="m6 9 6 6 6-6"/>',
        'chevron-right'   => '<path d="m9 18 6-6-6-6"/>',
        'filter'          => '<path d="M3 4h18l-7 8v7l-4 2v-9Z"/>',
        'droplet'         => '<path d="M12 22a7 7 0 0 0 7-7c0-4-7-13-7-13S5 11 5 15a7 7 0 0 0 7 7"/>',
        'thermometer'     => '<path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0"/>',
        'shield'          => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/>',
        'download'        => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
        'refresh'         => '<path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/>',
        'info'            => '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
        'settings'        => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1A1.7 1.7 0 0 0 8.9 19a1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 4.6 8.4a1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1"/>',
        'logout'          => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'eye'             => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7"/><circle cx="12" cy="12" r="3"/>',
        'file-text'       => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v5h6"/><path d="M9 13h6"/><path d="M9 17h6"/>',
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
