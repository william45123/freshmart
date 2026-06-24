<?php
/**
 * Wishlist / Favorites page.
 */

require_once __DIR__ . '/../includes/wishlist_helpers.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/freshness.php';

require_login();
$userId = auth_id();

if (is_post() && csrf_verify()) {
    $productId = (int) input('product_id', 0);
    if ($productId > 0) {
        if (input('action') === 'toggle') {
            wishlist_toggle($userId, $productId);
        } elseif (input('action') === 'remove') {
            $w = wishlist_get_or_create($userId);
            db_run('DELETE FROM wishlist_items WHERE wishlist_id = ? AND product_id = ?',
                   [$w['id'], $productId]);
            flash_set('info', 'Removed from wishlist.');
        }
    }
    redirect(input('return_to') ?: '/wishlist.php');
}

$items = wishlist_items($userId);
$items = array_map('decorate_with_freshness', $items);

$pageTitle = 'My Wishlist — FreshMart';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="container" style="padding: var(--space-6) 0 var(--space-12);">
    <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: var(--space-4);">
        <h1 style="margin: 0;"><span class="label-ico"><?= icon('heart', 22) ?> My Wishlist</span></h1>
        <span style="color: var(--color-text-muted);"><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?></span>
    </div>

    <?php if (empty($items)): ?>
        <div class="empty-state">
            <div class="empty-ico"><?= icon('heart', 44) ?></div>
            <p style="font-size: 1.0625rem; margin-bottom: var(--space-3);">Your wishlist is empty</p>
            <p>Tap the heart on any product to save it for later.</p>
            <a href="<?= url('/shop/browse.php') ?>" class="btn btn-primary" style="margin-top: var(--space-3);">Browse products</a>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($items as $p): ?>
                <div class="product-card" style="position: relative;">
                    <a href="<?= url('/shop/product.php?slug=' . urlencode($p['slug'])) ?>"
                       style="color: inherit; display: block;">
                        <div class="product-card-image">
                            <?php if (!empty($p['primary_image'])): ?>
                                <img src="<?= upload_url($p['primary_image']) ?>" alt="<?= attr($p['name']) ?>" loading="lazy" style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                                <span class="img-fallback"><?= icon('leaf', 56) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($p['expiry_date'])): ?>
                                <?= freshness_ring_html($p) ?>
                                <?php if (!empty($p['is_discounted'])): ?>
                                    <span class="discount-tag">-<?= (int) $p['discount_pct'] ?>%</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="product-card-body">
                            <div class="product-card-name"><?= e($p['name']) ?></div>
                            <div class="product-card-pricing">
                                <span class="price-final">
                                    <?= format_myr($p['final_price'] ?? $p['base_price']) ?>
                                </span>
                                <?php if (!empty($p['is_discounted'])): ?>
                                    <span class="price-base-strike"><?= format_myr($p['base_price']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <form method="post" style="position: absolute; top: 10px; right: 10px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        <button type="submit" class="remove-btn"
                                title="Remove from wishlist"
                                onclick="return confirm('Remove from wishlist?')"
                                style="background: rgba(255,255,255,0.9); color: var(--color-danger); border: 1px solid var(--color-border); width: 32px; height: 32px; border-radius: 50%; cursor: pointer; font-size: 16px;">×</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
