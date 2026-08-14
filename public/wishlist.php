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

<section class="container u-page-head">
    <div class="u-flex u-jc-between u-ai-baseline u-mb-4">
        <h1 class="u-m-0"><span class="label-ico"><?= icon('heart', 22) ?> My Wishlist</span></h1>
        <span class="u-muted"><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?></span>
    </div>

    <?php if (empty($items)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">💚</div>
            <div class="empty-state-title">Your wishlist is empty</div>
            <div class="empty-state-text">Tap the heart on any product to save it here for later.</div>
            <a href="<?= url('/shop/browse.php') ?>" class="btn btn-primary btn-lg">Browse products</a>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($items as $p): ?>
                <div class="product-card u-rel">
                    <a href="<?= url('/shop/product.php?slug=' . urlencode($p['slug'])) ?>"
                       class="u-fg-inherit u-block">
                        <div class="product-card-image">
                            <?php if (!empty($p['primary_image'])): ?>
                                <img src="<?= upload_url($p['primary_image']) ?>" alt="<?= attr($p['name']) ?>" loading="lazy" class="media-fill">
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
                    <form method="post" class="u-abs u-top-10 u-right-10">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        <button type="submit" class="remove-btn u-bg-white-90 u-fg-danger u-bordered u-w-32 u-h-32 u-r-circle u-pointer u-t-16px"
                                title="Remove from wishlist"
                                onclick="return confirm('Remove from wishlist?')"
                               >×</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
