<?php
/**
 * §7.1 — bottom tab bar + full-screen mobile search.
 *
 * Five items, always. Wishlist and Wallet live under Account rather than
 * making it six. Hidden on checkout (§7.3), hidden at >=1024px by CSS, and
 * padded for the Android gesture bar via env(safe-area-inset-bottom).
 */

$__path = current_path();
$__isCheckout = str_contains($__path, '/shop/checkout.php');

if (!$__isCheckout):
    $__role = auth_role();

    if ($__role === 'RETAILER') {
        $__tabs = [
            ['/retailer/dashboard.php', 'chart',    'Dashboard'],
            ['/retailer/inventory.php', 'package',  'Inventory'],
            ['/retailer/orders.php',    'cart',     'Orders'],
            ['/notifications.php',      'bell',     'Alerts', $unreadNotifs ?? 0],
            ['/retailer/profile.php',   'user',     'Account'],
        ];
    } elseif ($__role === 'ADMIN') {
        $__tabs = [
            ['/admin/dashboard.php', 'chart',   'Dashboard'],
            ['/admin/orders.php',    'cart',    'Orders'],
            ['/admin/users.php',     'user',    'Users'],
            ['/admin/reviews.php',   'star',    'Reviews'],
            ['/profile.php',         'settings','Account'],
        ];
    } else {
        $__tabs = [
            ['/index.php',        'leaf',    'Home'],
            ['/shop/browse.php',  'search',  'Browse'],
            ['/shop/cart.php',    'cart',    'Cart',   $cartCount ?? 0],
            ['/shop/orders.php',  'package', 'Orders'],
            ['/profile.php',      'user',    'Account'],
        ];
    }
?>
<nav class="tabbar" id="tabbar" aria-label="Primary">
    <?php foreach ($__tabs as $t):
        [$href, $ico, $label] = $t;
        $count   = $t[3] ?? 0;
        $current = str_contains($__path, rtrim($href, '/'));
    ?>
        <a href="<?= url($href) ?>"
           class="<?= $current ? 'is-current' : '' ?>"
           <?= $current ? 'aria-current="page"' : '' ?>>
            <span class="u-rel">
                <?= icon($ico, 22) ?>
                <?php if ($count > 0): ?>
                    <span class="tabbar-count"><?= min(99, (int) $count) ?></span>
                <?php endif; ?>
            </span>
            <span class="tabbar-label"><?= e($label) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<script>
// Stow on scroll-down, restore on scroll-up. rAF-throttled and reading
// scrollY only — §8 forbids offsetTop reads in scroll handlers.
(function () {
    var bar = document.getElementById('tabbar');
    if (!bar) return;
    var last = window.scrollY, ticking = false;
    addEventListener('scroll', function () {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(function () {
            var y = window.scrollY;
            if (Math.abs(y - last) > 8) {
                bar.classList.toggle('is-stowed', y > last && y > 120);
                last = y;
            }
            ticking = false;
        });
    }, { passive: true });
})();
</script>
<?php endif; ?>

<!-- §7.1 full-screen search. Replaces `.nav-search { display:none }`, which
     removed search from phones entirely. -->
<div class="search-overlay" id="search-overlay" role="dialog" aria-modal="true" aria-label="Search products">
    <form class="search-overlay-bar" method="get" action="<?= url('/shop/browse.php') ?>">
        <input type="search" name="q" id="search-overlay-input"
               placeholder="Search fresh produce…" autocomplete="off"
               enterkeyhint="search" aria-label="Search products">
        <button type="button" class="btn btn-ghost btn-icon" id="search-close" aria-label="Close search">
            <?= icon('x', 20) ?>
        </button>
    </form>
    <div class="search-overlay-body">
        <p class="u-t-11 u-upper u-ls-08 u-muted u-mb-2">Recent searches</p>
        <div id="search-recent"></div>
    </div>
</div>

<script>
(function () {
    var overlay = document.getElementById('search-overlay');
    var input   = document.getElementById('search-overlay-input');
    var recent  = document.getElementById('search-recent');
    if (!overlay) return;

    function readRecent() {
        try { return JSON.parse(sessionStorage.getItem('fm_recent') || '[]'); }
        catch (e) { return []; }
    }
    function paint() {
        var items = readRecent();
        recent.innerHTML = '';
        items.slice(0, 6).forEach(function (q) {
            var a = document.createElement('a');
            a.className = 'search-recent-item';
            a.href = <?= json_encode(url('/shop/browse.php')) ?> + '?q=' + encodeURIComponent(q);
            a.textContent = q;
            recent.appendChild(a);
        });
    }
    function open() {
        overlay.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        paint();
        input.focus();
    }
    function close() {
        overlay.classList.remove('is-open');
        document.body.style.overflow = '';
    }
    document.querySelectorAll('.search-trigger').forEach(function (b) {
        b.addEventListener('click', function (e) { e.preventDefault(); open(); });
    });
    document.getElementById('search-close').addEventListener('click', close);
    addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    overlay.querySelector('form').addEventListener('submit', function () {
        var q = input.value.trim();
        if (!q) return;
        var items = readRecent().filter(function (x) { return x !== q; });
        items.unshift(q);
        try { sessionStorage.setItem('fm_recent', JSON.stringify(items.slice(0, 6))); } catch (e) {}
    });
})();
</script>
