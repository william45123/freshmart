<?php
/**
 * FreshMart Configuration
 * ----------------------------------------------------------------
 * Copy this file to config.php and fill in your actual values.
 * Do NOT commit config.php to git (already in .gitignore).
 */

// --- Database Connection -----------------------------------------
define('DB_HOST',     'localhost');
define('DB_PORT',     '3306');
define('DB_NAME',     'freshmart');
define('DB_USER',     'root');        // Change to a dedicated user in production
define('DB_PASS',     '');            // Set your MySQL root password here
define('DB_CHARSET',  'utf8mb4');

// --- Application -------------------------------------------------
define('APP_NAME',    'FreshMart');
define('APP_URL',     'http://127.0.0.1:8899');
define('APP_ENV',     'development');           // 'development' | 'production'
define('APP_DEBUG',   false);
define('APP_TIMEZONE','Asia/Kuala_Lumpur');

// --- Security ----------------------------------------------------
// Generate with: php -r "echo bin2hex(random_bytes(32));"
define('APP_SECRET',  'change-me-to-a-long-random-string-in-production');
define('SESSION_NAME','freshmart_session');
define('SESSION_LIFETIME', 60 * 60 * 24 * 7);   // 7 days

// --- File Uploads ------------------------------------------------
define('UPLOAD_DIR',          __DIR__ . '/../public/uploads');
define('UPLOAD_MAX_SIZE',     5 * 1024 * 1024); // 5 MB
define('UPLOAD_ALLOWED_MIME', ['image/jpeg', 'image/png', 'image/webp']);
define('PRODUCT_IMAGE_MAX',   5);

// --- Business Rules ----------------------------------------------
define('CURRENCY',          'MYR');
define('APP_CURRENCY_SYMBOL',   'RM');
define('GUEST_CART_HOURS',  24);
define('DEFAULT_SHIPPING_FEE',   5.00);
define('FREE_SHIPPING_THRESHOLD', 50.00);

// --- Apply timezone immediately ---------------------------------
date_default_timezone_set(APP_TIMEZONE);

// --- Error reporting --------------------------------------------
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
