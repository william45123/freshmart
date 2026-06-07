<?php
/**
 * Authentication Helpers
 * ----------------------------------------------------------------
 * PHP-session-based auth. Uses bcrypt via password_hash() / password_verify().
 * Role-based access control: CUSTOMER | RETAILER | ADMIN.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/**
 * Start the session (idempotent). Call this before any session_* usage.
 */
function auth_init(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => APP_ENV === 'production',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Attempt login. Returns the user array on success or null on failure.
 * Updates last_activity on success.
 */
function auth_attempt(string $email, string $password): ?array
{
    auth_init();

    $user = db_one(
        "SELECT u.*, p.full_name
         FROM users u
         LEFT JOIN profiles p ON p.user_id = u.id
         WHERE u.email = ? AND u.deleted_at IS NULL",
        [$email]
    );

    if (!$user) return null;

    if (!password_verify($password, $user['password_hash'])) {
        return null;
    }

    if ($user['status'] !== 'ACTIVE') {
        return null;
    }

    // Refresh session ID to prevent fixation
    session_regenerate_id(true);

    $_SESSION['user_id']   = (int) $user['id'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['full_name'] = $user['full_name'] ?? '';
    $_SESSION['email']     = $user['email'];
    $_SESSION['logged_in_at'] = time();

    // Update rehash if needed (e.g. bcrypt cost was upgraded)
    if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT)) {
        $newHash = password_hash($password, PASSWORD_BCRYPT);
        db_run('UPDATE users SET password_hash = ? WHERE id = ?', [$newHash, $user['id']]);
    }

    return $user;
}

/**
 * Log the user out.
 */
function auth_logout(): void
{
    auth_init();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path']);
    }
    session_destroy();
}

/** Currently logged in? */
function auth_check(): bool
{
    auth_init();
    return !empty($_SESSION['user_id']);
}

/** Get current user id or null. */
function auth_id(): ?int
{
    auth_init();
    return $_SESSION['user_id'] ?? null;
}

/** Get current user role or null. */
function auth_role(): ?string
{
    auth_init();
    return $_SESSION['role'] ?? null;
}

/** Get current full name or 'Guest'. */
function auth_name(): string
{
    auth_init();
    return $_SESSION['full_name'] ?? 'Guest';
}

/** Get the full current user row (fresh from DB, no caching). */
function auth_user(): ?array
{
    if (!auth_check()) return null;
    return db_one(
        "SELECT u.*, p.full_name, p.phone, p.avatar_path
         FROM users u LEFT JOIN profiles p ON p.user_id = u.id
         WHERE u.id = ?",
        [auth_id()]
    );
}

/**
 * Guard: redirect to login if not authenticated.
 */
function require_login(string $redirectTo = '/auth/login.php'): void
{
    if (!auth_check()) {
        flash_set('error', 'Please log in to continue.');
        $_SESSION['intended_url'] = current_path();
        redirect($redirectTo);
    }
}

/**
 * Guard: require a specific role (or one of several).
 *   require_role('ADMIN')
 *   require_role(['RETAILER', 'ADMIN'])
 */
function require_role($roles): void
{
    require_login();
    $allowed = (array) $roles;
    if (!in_array(auth_role(), $allowed, true)) {
        http_response_code(403);
        die('Access denied. Required role: ' . implode(' or ', $allowed));
    }
}

/**
 * Register a new user (Customer or Retailer).
 * Returns the new user_id on success, throws RuntimeException on failure.
 */
function auth_register(
    string $email,
    string $password,
    string $fullName,
    string $role = 'CUSTOMER',
    ?string $phone = null,
    array $retailerInfo = []
): int {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Invalid email address.');
    }
    if (strlen($password) < 8) {
        throw new RuntimeException('Password must be at least 8 characters.');
    }
    if (!in_array($role, ['CUSTOMER', 'RETAILER'], true)) {
        throw new RuntimeException('Invalid role.');
    }

    // Email uniqueness
    $existing = db_scalar('SELECT id FROM users WHERE email = ?', [$email]);
    if ($existing) {
        throw new RuntimeException('That email is already registered.');
    }

    return db_transaction(function () use ($email, $password, $fullName, $role, $phone, $retailerInfo) {
        // Retailers start as PENDING — admin approval required
        $status = $role === 'RETAILER' ? 'PENDING' : 'ACTIVE';

        db_run(
            'INSERT INTO users (email, password_hash, role, status, email_verified)
             VALUES (?, ?, ?, ?, 0)',
            [$email, password_hash($password, PASSWORD_BCRYPT), $role, $status]
        );
        $userId = db_last_id();

        db_run(
            'INSERT INTO profiles (user_id, full_name, phone) VALUES (?, ?, ?)',
            [$userId, $fullName, $phone]
        );

        if ($role === 'RETAILER') {
            db_run(
                "INSERT INTO retailers
                    (user_id, company_name, business_reg_no, contact_phone,
                     business_address, approval_status)
                 VALUES (?, ?, ?, ?, ?, 'PENDING')",
                [
                    $userId,
                    $retailerInfo['company_name']   ?? $fullName,
                    $retailerInfo['business_reg_no'] ?? '',
                    $retailerInfo['contact_phone']   ?? $phone,
                    $retailerInfo['business_address'] ?? '',
                ]
            );
        } else {
            // Customers get an empty wishlist
            db_run('INSERT INTO wishlists (user_id) VALUES (?)', [$userId]);
        }

        return $userId;
    });
}
