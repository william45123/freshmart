<?php
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
auth_init();
$flashes = flash_get();

// Determine current role for header customisation
$role = auth_check() ? auth_role() : null;
$isCustomer = ($role === 'CUSTOMER' || $role === null);  // Guests get customer nav
$isRetailer = ($role === 'RETAILER');
$isAdmin    = ($role === 'ADMIN');

// Compute cart count — only for customers/guests (admin & retailer don't shop)
$cartCount = 0;
if ($isCustomer) {
    if (auth_check()) {
        $cartCount = (int) db_scalar(
            "SELECT COALESCE(SUM(ci.quantity), 0)
             FROM cart_items ci
             JOIN carts c ON c.id = ci.cart_id
             WHERE c.user_id = ?",
            [auth_id()]
        );
    } elseif (!empty($_SESSION['guest_session_id'])) {
        $cartCount = (int) db_scalar(
            "SELECT COALESCE(SUM(ci.quantity), 0)
             FROM cart_items ci
             JOIN carts c ON c.id = ci.cart_id
             WHERE c.guest_session_id = ? AND c.expires_at > NOW()",
            [$_SESSION['guest_session_id']]
        );
    }
}

// Unread notification count for logged-in users
$unreadNotifs = 0;
if (auth_check()) {
    $unreadNotifs = (int) db_scalar(
        'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0',
        [auth_id()]
    );
}

// Brand label varies by role
$brandSubLabel = $isAdmin ? 'Admin' : ($isRetailer ? 'Retailer' : '');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'FreshMart — Fresh Produce, Delivered') ?></title>
    <meta name="description" content="Malaysia's freshness-first online grocery — backed by FEFO inventory and a transparent Freshness Indicator.">
    <link rel="stylesheet" href="<?= asset('css/main.css') ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥬</text></svg>">
</head>
<body>
<header class="site-header <?= $isAdmin ? 'header-admin' : ($isRetailer ? 'header-retailer' : '') ?>">
    <div class="container">

        <!-- Brand (varies by role) -->
        <a href="<?= url($isAdmin ? '/admin/dashboard.php' : ($isRetailer ? '/retailer/dashboard.php' : '/')) ?>" class="brand">
            <span class="brand-logo">🥬</span>
            <span>FreshMart</span>
            <?php if ($brandSubLabel): ?>
                <span class="brand-sublabel"><?= e($brandSubLabel) ?></span>
            <?php endif; ?>
        </a>

        <!-- Main nav (varies by role) -->
        <nav class="nav-main">
            <?php if ($isAdmin): ?>
                <!-- ADMIN NAV -->
                <a href="<?= url('/admin/dashboard.php') ?>">Dashboard</a>
                <a href="<?= url('/admin/users.php') ?>">Users</a>
                <a href="<?= url('/admin/retailers.php') ?>">Retailers</a>
                <a href="<?= url('/admin/orders.php') ?>">Orders</a>
                <a href="<?= url('/admin/reviews.php') ?>">Reviews</a>
                <a href="<?= url('/admin/promos.php') ?>">Promos</a>
                <a href="<?= url('/admin/settings.php') ?>">Settings</a>
            <?php elseif ($isRetailer): ?>
                <!-- RETAILER NAV -->
                <a href="<?= url('/retailer/dashboard.php') ?>">Dashboard</a>
                <a href="<?= url('/retailer/products.php') ?>">Products</a>
                <a href="<?= url('/retailer/inventory.php') ?>">Inventory</a>
                <a href="<?= url('/retailer/orders.php') ?>">Orders</a>
                <a href="<?= url('/retailer/reviews.php') ?>">Reviews</a>
                <a href="<?= url('/retailer/reports.php') ?>">Reports</a>
            <?php else: ?>
                <!-- CUSTOMER / GUEST NAV -->
                <a href="<?= url('/shop/browse.php') ?>">Browse</a>
                <a href="<?= url('/shop/browse.php?category=vegetables') ?>">Vegetables</a>
                <a href="<?= url('/shop/browse.php?category=fruits') ?>">Fruits</a>
                <a href="<?= url('/shop/browse.php?freshness=LAST_CHANCE') ?>">🔥 Last Chance</a>
            <?php endif; ?>
        </nav>

        <!-- Right-side actions (varies by role) -->
        <div class="nav-actions">

            <?php if ($isCustomer): ?>
                <!-- Customer cart icon with badge -->
                <a href="<?= url('/shop/cart.php') ?>" class="btn btn-ghost btn-sm" style="position: relative;" title="Cart">
                    🛒
                    <?php if ($cartCount > 0): ?>
                        <span style="position: absolute; top: -2px; right: -2px; background: var(--color-primary); color: white; font-size: 0.625rem; font-weight: 700; border-radius: 999px; min-width: 18px; height: 18px; display: grid; place-items: center; padding: 0 5px; line-height: 1;">
                            <?= min(99, $cartCount) ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <?php if (auth_check()): ?>
                <!-- Notifications (everyone logged in) -->
                <a href="<?= url('/notifications.php') ?>" class="btn btn-ghost btn-sm" style="position: relative;" title="Notifications">
                    🔔
                    <?php if ($unreadNotifs > 0): ?>
                        <span style="position: absolute; top: -2px; right: -2px; background: var(--color-danger); color: white; font-size: 0.625rem; border-radius: 999px; min-width: 16px; height: 16px; display: grid; place-items: center; padding: 0 4px;">
                            <?= min(99, $unreadNotifs) ?>
                        </span>
                    <?php endif; ?>
                </a>

                <?php if ($isCustomer): ?>
                    <!-- Customer extras: wishlist + orders -->
                    <a href="<?= url('/wishlist.php') ?>" class="btn btn-ghost btn-sm" title="Wishlist">❤️</a>
                    <a href="<?= url('/shop/orders.php') ?>" class="btn btn-ghost btn-sm" title="My orders">📦</a>
                <?php endif; ?>

                <!-- Greeting + Profile link -->
                <a href="<?= url($isRetailer ? '/retailer/profile.php' : '/profile.php') ?>" class="btn btn-ghost btn-sm" style="cursor: pointer;" title="Profile">
                    Hi, <?= e(auth_name()) ?>
                </a>

                <a href="<?= url('/auth/logout.php') ?>" class="btn btn-ghost btn-sm">Logout</a>

            <?php else: ?>
                <!-- Guest: Login + Sign Up buttons -->
                <a href="<?= url('/auth/login.php') ?>" class="btn btn-ghost btn-sm">Login</a>
                <a href="<?= url('/auth/register.php') ?>" class="btn btn-primary btn-sm">Sign Up</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<?php if (!empty($flashes)): ?>
<div class="container" style="margin-top: var(--space-4);">
    <?php foreach ($flashes as $f): ?>
        <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<main>
