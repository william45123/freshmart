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

<section class="container" style="padding: var(--space-6) 0 var(--space-12);">

    <h1>Your cart <?php if ($cart['count'] > 0): ?><span style="color: var(--color-text-muted); font-weight: 400;">(<?= $cart['count'] ?>)</span><?php endif; ?></h1>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <?php if ($cart['count'] === 0): ?>
        <div class="empty-state" style="margin-top: var(--space-6);">
            <p style="font-size: 1.0625rem; margin-bottom: var(--space-3);">🛒 Your cart is empty</p>
            <a href="<?= url('/shop/browse.php') ?>" class="btn btn-primary">Browse products</a>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: 1fr 320px; gap: var(--space-6); margin-top: var(--space-6);">

            <!-- Items list -->
            <div>
                <?php foreach ($cart['items'] as $item): ?>
                    <div style="display: grid; grid-template-columns: 100px 1fr auto; gap: var(--space-4); background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-4); margin-bottom: var(--space-3); align-items: center;">

                        <a href="<?= url('/shop/product.php?slug=' . urlencode($item['slug'])) ?>"
                           style="aspect-ratio: 1; background: var(--color-bg); border-radius: var(--radius); display: grid; place-items: center; overflow: hidden;">
                            <?php if (!empty($item['primary_image'])): ?>
                                <img src="<?= upload_url($item['primary_image']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <span style="font-size: 2rem;">🥬</span>
                            <?php endif; ?>
                        </a>

                        <div>
                            <a href="<?= url('/shop/product.php?slug=' . urlencode($item['slug'])) ?>"
                               style="color: var(--color-text); font-weight: 600; font-size: 1rem; display: block; margin-bottom: var(--space-1);">
                                <?= e($item['name']) ?>
                            </a>
                            <?php if (!empty($item['origin'])): ?>
                                <div style="color: var(--color-text-muted); font-size: 0.8125rem; margin-bottom: var(--space-2);">
                                    📍 <?= e($item['origin']) ?>
                                </div>
                            <?php endif; ?>
                            <div style="color: var(--color-text-muted); font-size: 0.875rem;">
                                <?= format_myr($item['price_snapshot']) ?> / <?= e($item['unit_code']) ?>
                            </div>

                            <form method="post" style="margin-top: var(--space-2); display: flex; gap: var(--space-2); align-items: center;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                <input type="number" name="quantity"
                                       value="<?= attr((string) $item['quantity']) ?>"
                                       step="0.01" min="0.01"
                                       class="form-control" style="width: 90px;"
                                       onchange="this.form.submit()">
                                <span style="color: var(--color-text-muted); font-size: 0.875rem;"><?= e($item['unit_code']) ?></span>
                            </form>
                        </div>

                        <div style="text-align: right;">
                            <div style="font-size: 1.125rem; font-weight: 700;">
                                <?= format_myr((float) $item['quantity'] * (float) $item['price_snapshot']) ?>
                            </div>
                            <form method="post" style="margin-top: var(--space-2);">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                <button type="submit" class="btn btn-ghost btn-sm"
                                        style="color: var(--color-danger);"
                                        onclick="return confirm('Remove this item?')">
                                    Remove
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Summary -->
            <aside style="position: sticky; top: calc(var(--header-h) + var(--space-4)); align-self: start;">
                <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-5);">
                    <h3 style="font-size: 1.125rem; margin-bottom: var(--space-4);">Order Summary</h3>
                    <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-2);">
                        <span style="color: var(--color-text-muted);">Subtotal</span>
                        <span><?= format_myr($cart['subtotal']) ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-2);">
                        <span style="color: var(--color-text-muted);">Shipping</span>
                        <span>
                            <?php if ($cart['shipping'] == 0): ?>
                                <strong style="color: var(--color-primary);">FREE</strong>
                            <?php else: ?>
                                <?= format_myr($cart['shipping']) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if ($cart['shipping'] > 0): ?>
                        <div style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: var(--space-3); background: var(--color-primary-light); padding: var(--space-2) var(--space-3); border-radius: var(--radius);">
                            Add <?= format_myr(FREE_SHIPPING_THRESHOLD - $cart['subtotal']) ?> more for free shipping!
                        </div>
                    <?php endif; ?>

                    <div style="display: flex; justify-content: space-between; padding-top: var(--space-3); border-top: 1px solid var(--color-border); margin-bottom: var(--space-4);">
                        <strong>Total</strong>
                        <strong style="font-size: 1.25rem;"><?= format_myr($cart['total']) ?></strong>
                    </div>

                    <?php if (auth_check()): ?>
                        <a href="<?= url('/shop/checkout.php') ?>" class="btn btn-primary btn-lg" style="width: 100%;">
                            Proceed to checkout →
                        </a>
                    <?php else: ?>
                        <a href="<?= url('/auth/login.php') ?>" class="btn btn-primary btn-lg" style="width: 100%;">
                            Log in to checkout
                        </a>
                        <p style="text-align: center; font-size: 0.8125rem; color: var(--color-text-muted); margin-top: var(--space-2);">
                            New customer? <a href="<?= url('/auth/register.php') ?>">Create an account</a>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if (!empty($availablePromos)): ?>
                <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-5); margin-top: var(--space-4);">
                    <h3 style="font-size: 0.6875rem; letter-spacing: 0.1em; text-transform: uppercase; color: var(--color-text-muted); margin: 0 0 var(--space-3);">
                        🎟️ Promo codes you can use
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: var(--space-2);">
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
                            <div style="border: 1px dashed <?= $eligible ? 'var(--color-primary)' : 'var(--color-border)' ?>; border-radius: var(--radius); padding: var(--space-3); <?= $eligible ? '' : 'opacity: 0.6;' ?>">
                                <div style="display: flex; justify-content: space-between; align-items: center; gap: var(--space-2);">
                                    <code style="font-size: 0.9375rem; font-weight: 700; letter-spacing: 0.05em; color: var(--color-primary-dark); background: var(--color-primary-light); padding: 2px 8px; border-radius: var(--radius-sm);">
                                        <?= e($promo['code']) ?>
                                    </code>
                                    <span style="font-size: 0.875rem; font-weight: 600; white-space: nowrap;"><?= e($discTxt) ?></span>
                                </div>
                                <?php if (!empty($promo['description'])): ?>
                                    <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 4px;"><?= e($promo['description']) ?></div>
                                <?php endif; ?>
                                <div style="font-size: 0.6875rem; color: var(--color-text-muted); margin-top: 4px;">
                                    <?php if ((float) $promo['min_order_value'] > 0): ?>
                                        <?php if ($eligible): ?>
                                            <span style="color: var(--color-primary);">✓ Min order met</span>
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
                    <p style="font-size: 0.6875rem; color: var(--color-text-muted); margin: var(--space-3) 0 0;">
                        Enter your code at checkout to apply the discount.
                    </p>
                </div>
                <?php endif; ?>
            </aside>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
