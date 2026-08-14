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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,400&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/main.css') ?>">
    <noscript><style>@layer utilities{.reveal{opacity:1;transform:none}}</style></noscript>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥬</text></svg>">
</head>
<body class="<?= $isAdmin ? 'role-admin' : ($isRetailer ? 'role-retailer' : 'role-customer') ?>">
<header class="site-header <?= $isAdmin ? 'header-admin' : ($isRetailer ? 'header-retailer' : '') ?>">
    <div class="container">

        <!-- Brand (varies by role) -->
        <a href="<?= url($isAdmin ? '/admin/dashboard.php' : ($isRetailer ? '/retailer/dashboard.php' : '/')) ?>" class="brand">
            <span class="brand-logo"><?= icon('leaf', 24) ?></span>
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
                <a href="<?= url('/admin/refunds.php') ?>">Refunds</a>
                <a href="<?= url('/admin/reviews.php') ?>">Reviews</a>
                <a href="<?= url('/admin/promos.php') ?>">Promos</a>
                <a href="<?= url('/admin/settings.php') ?>">Settings</a>
            <?php elseif ($isRetailer): ?>
                <!-- RETAILER NAV -->
                <a href="<?= url('/retailer/dashboard.php') ?>">Dashboard</a>
                <a href="<?= url('/retailer/products.php') ?>">Products</a>
                <a href="<?= url('/retailer/inventory.php') ?>">Inventory</a>
                <a href="<?= url('/retailer/orders.php') ?>">Orders</a>
                <a href="<?= url('/retailer/refunds.php') ?>">Refunds</a>
                <a href="<?= url('/retailer/reviews.php') ?>">Reviews</a>
                <a href="<?= url('/retailer/reports.php') ?>">Reports</a>
                <a href="<?= url('/retailer/discounts.php') ?>">Discounts</a>
            <?php else: ?>
                <!-- CUSTOMER / GUEST NAV -->
                <a href="<?= url('/shop/browse.php') ?>">Browse</a>
                <a href="<?= url('/shop/browse.php?category=vegetables') ?>">Vegetables</a>
                <a href="<?= url('/shop/browse.php?category=fruits') ?>">Fruits</a>
                <a href="<?= url('/shop/browse.php?freshness=LAST_CHANCE') ?>" class="label-ico"><?= icon('flame', 15) ?> Last Chance</a>
            <?php endif; ?>
        </nav>

        <?php if (!$isAdmin && !$isRetailer): ?>
        <!-- Header search (customer/guest) -->
        <form class="nav-search" method="get" action="<?= url('/shop/browse.php') ?>" role="search">
            <span class="nav-search-icon"><?= icon('search', 16) ?></span>
            <input type="search" name="q" placeholder="Search fresh produce..."
                   value="<?= attr($_GET['q'] ?? '') ?>" aria-label="Search products">
        </form>
        <?php endif; ?>

        <!-- Right-side actions (varies by role) -->
        <div class="nav-actions">

            <?php if ($isCustomer): ?>
                <!-- Customer cart icon with badge -->
                <a href="<?= url('/shop/cart.php') ?>" class="btn btn-ghost btn-sm btn-icon u-rel" title="Cart">
                    <?= icon('cart') ?>
                    <?php if ($cartCount > 0): ?>
                        <span class="nav-badge">
                            <?= min(99, $cartCount) ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <?php if (auth_check()): ?>
                <!-- Notifications (everyone logged in) -->
                <a href="<?= url('/notifications.php') ?>" class="btn btn-ghost btn-sm btn-icon u-rel" title="Notifications">
                    <?= icon('bell') ?>
                    <?php if ($unreadNotifs > 0): ?>
                        <span class="nav-badge nav-badge-alert">
                            <?= min(99, $unreadNotifs) ?>
                        </span>
                    <?php endif; ?>
                </a>

                <?php if ($isCustomer): ?>
                    <!-- Customer extras: wishlist + orders + wallet -->
                    <a href="<?= url('/wishlist.php') ?>" class="btn btn-ghost btn-sm btn-icon" title="Wishlist"><?= icon('heart') ?></a>
                    <a href="<?= url('/shop/orders.php') ?>" class="btn btn-ghost btn-sm btn-icon" title="My orders"><?= icon('package') ?></a>
                    <a href="<?= url('/wallet.php') ?>" class="btn btn-ghost btn-sm btn-icon nav-wallet" title="My wallet">💳</a>
                <?php endif; ?>

                <!-- Greeting + Profile link -->
                <a href="<?= url($isRetailer ? '/retailer/profile.php' : '/profile.php') ?>" class="btn btn-ghost btn-sm u-pointer" title="Profile">
                    Hi, <?= e(auth_name()) ?>
                </a>

                <a href="<?= url('/auth/logout.php') ?>" class="btn btn-ghost btn-sm">Logout</a>

            <?php else: ?>
                <!-- Guest: Login + Sign Up buttons -->
                <a href="<?= url('/auth/login.php') ?>" class="btn btn-ghost btn-sm">Login</a>
                <a href="<?= url('/auth/register.php') ?>" class="btn btn-primary btn-sm">Sign Up</a>
            <?php endif; ?>
        </div>

        <!-- Mobile hamburger (shown < 860px) -->
        <button class="nav-toggle" type="button" aria-label="Open menu" aria-expanded="false" data-mobile-open>&#9776;</button>
    </div>
</header>

<!-- Mobile slide-in navigation -->
<div class="mobile-nav-backdrop" data-mobile-close></div>
<nav class="mobile-nav" aria-label="Mobile menu">
    <div class="mobile-nav-head">
        <span class="brand"><span class="brand-logo"><?= icon('leaf', 22) ?></span> FreshMart</span>
        <button class="mobile-nav-close" type="button" aria-label="Close menu" data-mobile-close>&times;</button>
    </div>

    <?php if (!$isAdmin && !$isRetailer): ?>
        <form class="mobile-nav-search" method="get" action="<?= url('/shop/browse.php') ?>" role="search">
            <input type="search" name="q" placeholder="Search fresh produce..."
                   value="<?= attr($_GET['q'] ?? '') ?>" aria-label="Search products">
            <button type="submit"><?= icon('search', 16) ?></button>
        </form>
    <?php endif; ?>

    <div class="mobile-nav-group">Menu</div>
    <?php if ($isAdmin): ?>
        <a href="<?= url('/admin/dashboard.php') ?>">Dashboard</a>
        <a href="<?= url('/admin/users.php') ?>">Users</a>
        <a href="<?= url('/admin/retailers.php') ?>">Retailers</a>
        <a href="<?= url('/admin/orders.php') ?>">Orders</a>
        <a href="<?= url('/admin/refunds.php') ?>">Refunds</a>
        <a href="<?= url('/admin/reviews.php') ?>">Reviews</a>
        <a href="<?= url('/admin/promos.php') ?>">Promos</a>
        <a href="<?= url('/admin/settings.php') ?>">Settings</a>
    <?php elseif ($isRetailer): ?>
        <a href="<?= url('/retailer/dashboard.php') ?>">Dashboard</a>
        <a href="<?= url('/retailer/products.php') ?>">Products</a>
        <a href="<?= url('/retailer/inventory.php') ?>">Inventory</a>
        <a href="<?= url('/retailer/orders.php') ?>">Orders</a>
        <a href="<?= url('/retailer/refunds.php') ?>">Refunds</a>
        <a href="<?= url('/retailer/reviews.php') ?>">Reviews</a>
        <a href="<?= url('/retailer/reports.php') ?>">Reports</a>
        <a href="<?= url('/retailer/discounts.php') ?>">Discounts</a>
    <?php else: ?>
        <a href="<?= url('/shop/browse.php') ?>">Browse</a>
        <a href="<?= url('/shop/browse.php?category=vegetables') ?>">Vegetables</a>
        <a href="<?= url('/shop/browse.php?category=fruits') ?>">Fruits</a>
        <a href="<?= url('/shop/browse.php?freshness=LAST_CHANCE') ?>"><?= icon('flame', 18) ?> Last Chance</a>
    <?php endif; ?>

    <div class="mobile-nav-group">Account</div>
    <?php if ($isCustomer): ?>
        <a href="<?= url('/shop/cart.php') ?>"><?= icon('cart', 18) ?> Cart<?= $cartCount > 0 ? ' (' . min(99, $cartCount) . ')' : '' ?></a>
    <?php endif; ?>
    <?php if (auth_check()): ?>
        <a href="<?= url('/notifications.php') ?>"><?= icon('bell', 18) ?> Notifications<?= $unreadNotifs > 0 ? ' (' . min(99, $unreadNotifs) . ')' : '' ?></a>
        <?php if ($isCustomer): ?>
            <a href="<?= url('/wishlist.php') ?>"><?= icon('heart', 18) ?> Wishlist</a>
            <a href="<?= url('/shop/orders.php') ?>"><?= icon('package', 18) ?> My orders</a>
            <a href="<?= url('/wallet.php') ?>">💳 My wallet</a>
        <?php endif; ?>
        <a href="<?= url($isRetailer ? '/retailer/profile.php' : '/profile.php') ?>"><?= icon('user', 18) ?> <?= e(auth_name()) ?></a>
        <a class="btn btn-outline mobile-nav-cta" href="<?= url('/auth/logout.php') ?>">Logout</a>
    <?php else: ?>
        <a href="<?= url('/auth/login.php') ?>">Login</a>
        <a class="btn btn-primary mobile-nav-cta" href="<?= url('/auth/register.php') ?>">Sign Up</a>
    <?php endif; ?>
</nav>

<?php if (!empty($flashes)): ?>
<div class="container u-mt-4">
    <?php foreach ($flashes as $f): ?>
        <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<main>
