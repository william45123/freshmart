<?php
/**
 * Cart page.
 *
 * GET  → show cart
 * POST action=add        → add product (called from product page)
 * POST action=update     → update quantity
 * POST action=remove     → remove item
 */

require_once __DIR__ . '/../../includes/cart_helpers.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/freshness.php';

$errors = [];

if (is_post()) {
    if (!csrf_verify()) {
        $errors[] = 'CSRF mismatch.';
    } else {
        $action    = (string) input('action', '');
        $productId = (int) input('product_id', 0);

        try {
            if ($action === 'add') {
                $qty = (float) input('quantity', 1);
                cart_add($productId, $qty);
                flash_set('success', 'Added to cart.');
                redirect('/shop/cart.php');
            } elseif ($action === 'update') {
                $qty = (float) input('quantity', 0);
                cart_update_quantity($productId, $qty);
                redirect('/shop/cart.php');
            } elseif ($action === 'remove') {
                cart_remove($productId);
                flash_set('info', 'Removed from cart.');
                redirect('/shop/cart.php');
            }
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
            redirect('/shop/cart.php');
        }
    }
}

$cart = cart_totals();

// Active promo codes the customer can use right now (visible, valid, not exhausted)
$availablePromos = db_all(
    "SELECT code, description, discount_type, discount_value, min_order_value, max_discount, expires_at
     FROM promo_codes
     WHERE is_active = 1
       AND starts_at <= NOW()
       AND expires_at >= NOW()
       AND (usage_limit IS NULL OR usage_count < usage_limit)
     ORDER BY min_order_value ASC, discount_value DESC"
);

$pageTitle = 'Cart — FreshMart';
require_once __DIR__ . '/../../includes/header.php';
?>

<section class="container u-page-head">

    <h1>Your cart <?php if ($cart['count'] > 0): ?><span class="u-muted u-fw-400">(<?= $cart['count'] ?>)</span><?php endif; ?></h1>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <?php if ($cart['count'] === 0): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><?= icon('cart', 16) ?></div>
            <div class="empty-state-title">Your cart is empty</div>
            <div class="empty-state-text">Looks like you haven't added anything yet. Explore our fresh produce and Last Chance deals.</div>
            <a href="<?= url('/shop/browse.php') ?>" class="btn btn-primary btn-lg">Browse products</a>
        </div>
    <?php else: ?>
        <div class="cart-layout">

            <!-- Items list -->
            <div>
                <?php foreach ($cart['items'] as $item): ?>
                    <div class="cart-item" data-product-id="<?= (int) $item['product_id'] ?>">

                        <a href="<?= url('/shop/product.php?slug=' . urlencode($item['slug'])) ?>"
                           class="u-square u-bg-page u-r u-grid u-place-center u-ovh">
                            <?php if (!empty($item['primary_image'])): ?>
                                <img src="<?= upload_url($item['primary_image']) ?>" alt="<?= attr($item['name']) ?>" loading="lazy" class="media-fill">
                            <?php else: ?>
                                <span class="u-t-32"><?= icon('leaf', 16) ?></span>
                            <?php endif; ?>
                        </a>

                        <div>
                            <a href="<?= url('/shop/product.php?slug=' . urlencode($item['slug'])) ?>"
                               class="u-ink u-fw-600 u-t-16 u-block u-mb-1">
                                <?= e($item['name']) ?>
                            </a>
                            <?php if (!empty($item['origin'])): ?>
                                <div class="u-muted u-t-13 u-mb-2">
                                    <?= icon('pin', 16) ?> <?= e($item['origin']) ?>
                                </div>
                            <?php endif; ?>
                            <div class="u-muted u-t-14">
                                <?= format_myr($item['price_snapshot']) ?> / <?= e($item['unit_code']) ?>
                            </div>

                            <form method="post" class="u-mt-2 u-flex u-gap-2 u-ai-center">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                <?php // §7.3 — 48px stepper controls either side of the
                                      // field. The number input alone had no touch
                                      // affordance; its spinners are ~12px and vanish
                                      // on mobile browsers entirely. ?>
                                <div class="stepper">
                                    <button type="button" class="stepper-btn" data-step="-1"
                                            aria-label="Decrease quantity">&minus;</button>
                                    <input type="number" name="quantity"
                                           value="<?= attr((string) $item['quantity']) ?>"
                                           step="0.01" min="0.01"
                                           inputmode="decimal"
                                           class="form-control stepper-input"
                                           aria-label="Quantity"
                                           onchange="this.form.submit()">
                                    <button type="button" class="stepper-btn" data-step="1"
                                            aria-label="Increase quantity">+</button>
                                </div>
                                <span class="u-muted u-t-14"><?= e($item['unit_code']) ?></span>
                            </form>
                        </div>

                        <div class="cart-item-total u-ta-r">
                            <div class="u-t-18 u-fw-700">
                                <?= format_myr((float) $item['quantity'] * (float) $item['price_snapshot']) ?>
                            </div>
                            <form method="post" class="u-mt-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                <?php // No confirm() dialog: §7.2 wants an Undo, not a
                                      // modal blocking a one-tap action. ?>
                                <button type="submit" class="btn btn-ghost btn-sm u-fg-danger" data-remove>
                                    <?= icon('trash', 16) ?> Remove
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Summary -->
            <aside class="u-sticky u-top-sticky u-as-start">
                <div class="panel u-p-5">
                    <h3 class="u-t-18 u-mb-4">Order Summary</h3>
                    <div class="u-flex u-jc-between u-mb-2">
                        <span class="u-muted">Subtotal</span>
                        <span><?= format_myr($cart['subtotal']) ?></span>
                    </div>
                    <div class="u-flex u-jc-between u-mb-2">
                        <span class="u-muted">Shipping</span>
                        <span>
                            <?php if ($cart['shipping'] == 0): ?>
                                <strong class="u-fg-primary">FREE</strong>
                            <?php else: ?>
                                <?= format_myr($cart['shipping']) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php
                        $threshold = (float) FREE_SHIPPING_THRESHOLD;
                        $sub       = (float) $cart['subtotal'];
                        $pct       = $threshold > 0 ? min(100, ($sub / $threshold) * 100) : 100;
                        $remaining = max(0, $threshold - $sub);
                    ?>
                    <div class="ship-progress <?= $remaining <= 0 ? 'is-complete' : '' ?>">
                        <div class="ship-progress-label">
                            <?php if ($remaining > 0): ?>
                                Add <strong><?= format_myr($remaining) ?></strong> more for <strong>FREE</strong> shipping
                            <?php else: ?>
                                <span class="label-ico"><?= icon('sparkles', 16) ?> You&rsquo;ve unlocked <strong>free shipping!</strong></span>
                            <?php endif; ?>
                        </div>
                        <div class="ship-progress-track">
                            <div class="ship-progress-fill" style="--pct: <?= number_format($pct, 1) ?>%"></div>
                        </div>
                    </div>

                    <div class="u-flex u-jc-between u-pt-3 u-bt u-mb-4">
                        <strong>Total</strong>
                        <strong class="u-t-20"><?= format_myr($cart['total']) ?></strong>
                    </div>

                    <?php if (auth_check()): ?>
                        <a href="<?= url('/shop/checkout.php') ?>" class="btn btn-primary btn-lg u-w-full">
                            Proceed to checkout →
                        </a>
                    <?php else: ?>
                        <a href="<?= url('/auth/login.php') ?>" class="btn btn-primary btn-lg u-w-full">
                            Log in to checkout
                        </a>
                        <p class="u-ta-c u-t-13 u-muted u-mt-2">
                            New customer? <a href="<?= url('/auth/register.php') ?>">Create an account</a>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if (!empty($availablePromos)): ?>
                <div class="panel u-p-5 u-mt-4">
                    <h3 class="u-t-11 u-ls-10 u-upper u-muted u-m-0-0-3">
                        <?= icon('ticket', 16) ?>️ Promo codes you can use
                    </h3>
                    <div class="u-flex u-col u-gap-2">
                        <?php foreach ($availablePromos as $promo):
                            $eligible = (float) $cart['subtotal'] >= (float) $promo['min_order_value'];
                            if ($promo['discount_type'] === 'PERCENTAGE') {
                                $discTxt = number_format((float) $promo['discount_value'], 0) . '% off';
                                if (!empty($promo['max_discount'])) {
                                    $discTxt .= ' (max ' . format_myr($promo['max_discount']) . ')';
                                }
                            } else {
                                $discTxt = format_myr($promo['discount_value']) . ' off';
                            }
                        ?>
                            <div class="voucher-option<?= $eligible ? ' is-eligible' : '' ?>">
                                <div class="u-flex u-jc-between u-ai-center u-gap-2">
                                    <code class="u-t-15 u-fw-700 u-ls-05 u-fg-primary-dk u-bg-primary-lt u-p-pill-xs u-r-sm">
                                        <?= e($promo['code']) ?>
                                    </code>
                                    <span class="u-t-14 u-fw-600 u-nowrap"><?= e($discTxt) ?></span>
                                </div>
                                <?php if (!empty($promo['description'])): ?>
                                    <div class="u-t-12 u-muted u-mt-1"><?= e($promo['description']) ?></div>
                                <?php endif; ?>
                                <div class="u-t-11 u-muted u-mt-1">
                                    <?php if ((float) $promo['min_order_value'] > 0): ?>
                                        <?php if ($eligible): ?>
                                            <span class="u-fg-primary">✓ Min order met</span>
                                        <?php else: ?>
                                            Spend <?= format_myr((float) $promo['min_order_value'] - (float) $cart['subtotal']) ?> more to unlock
                                        <?php endif; ?>
                                        ·
                                    <?php endif; ?>
                                    Expires <?= format_date($promo['expires_at']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="u-t-11 u-muted u-m-3-0-0">
                        Enter your code at checkout to apply the discount.
                    </p>
                </div>
                <?php endif; ?>
            </aside>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
// §7.3 cart interactions. Steppers drive the existing form; swipe-left is a
// shortcut to the Remove button that is already there, never the only route.
(function () {
    document.querySelectorAll('.stepper').forEach(function (st) {
        var input = st.querySelector('input');
        st.querySelectorAll('.stepper-btn').forEach(function (b) {
            b.addEventListener('click', function () {
                var step = parseFloat(input.step) || 1;
                var min  = parseFloat(input.min) || 0;
                var next = (parseFloat(input.value) || 0) + step * parseInt(b.dataset.step, 10);
                input.value = (Math.max(min, next)).toFixed(2).replace(/\.00$/, '');
                input.form.submit();
            });
        });
    });

    // Swipe-left reveals removal; the row snaps back if the gesture is
    // abandoned, and Undo restores it without a round trip.
    document.querySelectorAll('.cart-item').forEach(function (row) {
        var x0 = null, dx = 0;
        row.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
        row.addEventListener('touchmove', function (e) {
            if (x0 === null) return;
            dx = Math.min(0, e.touches[0].clientX - x0);
            row.style.transform = 'translateX(' + dx + 'px)';
        }, { passive: true });
        row.addEventListener('touchend', function () {
            row.style.transform = '';
            if (dx < -90) {
                var btn = row.querySelector('[data-remove]');
                if (btn) btn.click();
            }
            x0 = null; dx = 0;
        });
    });
})();
</script>
