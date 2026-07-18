<?php
/**
 * Product Detail page.
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/freshness.php';
require_once __DIR__ . '/../../includes/fefo.php';
require_once __DIR__ . '/../../includes/auth_helpers.php';
require_once __DIR__ . '/../../includes/wishlist_helpers.php';
require_once __DIR__ . '/../../includes/recently_viewed.php';
require_once __DIR__ . '/../../includes/recommendations.php';

auth_init();

$slug = trim((string) input('slug', ''));
if ($slug === '') redirect('/shop/browse.php');

$product = db_one(
    "SELECT p.*, c.name AS category_name, c.slug AS category_slug,
            COALESCE(p.decay_exponent_override, c.decay_exponent, 1.00) AS decay_exponent,
            ut.code AS unit_code, ut.name AS unit_name, ut.is_weight AS unit_is_weight,
            r.company_name AS retailer_name
     FROM products p
     JOIN categories c ON c.id = p.category_id
     JOIN unit_types ut ON ut.id = p.unit_type_id
     JOIN retailers r ON r.id = p.retailer_id
     WHERE p.slug = ? AND p.is_active = 1 AND p.deleted_at IS NULL",
    [$slug]
);

if (!$product) {
    http_response_code(404);
    flash_set('error', 'Product not found.');
    redirect('/shop/browse.php');
}

// Increment view counter (best-effort, not awaited)
db_run('UPDATE products SET view_count = view_count + 1 WHERE id = ?', [$product['id']]);

// Track in session for "Recently Viewed" feature
recently_viewed_track((int) $product['id']);

// Pull recently viewed (excluding current product)
$recentlyViewed = recently_viewed_products(excludeProductId: (int) $product['id'], limit: 6);
$recentlyViewed = array_map('decorate_with_freshness', $recentlyViewed);

// Available stock + display batch
$displayBatch = fefo_display_batch((int) $product['id']);
$totalStock   = fefo_total_stock((int) $product['id']);

// Compute freshness from display batch
$freshness = null;
$forecast  = [];   // 7-day freshness forecast for the trend chart
if ($displayBatch) {
    $freshness = decorate_with_freshness([
        'id'              => $product['id'],
        'base_price'      => $product['base_price'],
        'received_date'   => $displayBatch['received_date'],
        'expiry_date'     => $displayBatch['expiry_date'],
        'decay_exponent'  => $product['decay_exponent'],
    ]);

    // Build a forecast of how freshness % will decay over the next 7 days,
    // using the SAME power-law model (freshness% = (1 - t/T)^n × 100).
    $expN     = (float) $product['decay_exponent'];
    $recTs    = strtotime($displayBatch['received_date'] . ' 00:00:00');
    $expTs    = strtotime($displayBatch['expiry_date'] . ' 23:59:59');
    $totalSec = max(1, $expTs - $recTs);
    for ($d = 0; $d <= 7; $d++) {
        $pointTs   = strtotime("+$d days", strtotime(date('Y-m-d') . ' 00:00:00'));
        $elapsed   = $pointTs - $recTs;
        $ratio     = min(1, max(0, $elapsed / $totalSec));
        $pct       = ($elapsed >= $totalSec) ? 0.0 : round(pow(1 - $ratio, max(0.1, $expN)) * 100, 1);
        // Determine level for that day (for coloring/annotation)
        $lvl = $pct <= 0 ? 'EXPIRED' : ($pct < 25 ? 'LAST_CHANCE' : ($pct < 50 ? 'ENJOY_SOON' : ($pct < 75 ? 'FRESH' : 'VERY_FRESH')));
        $forecast[] = [
            'label' => $d === 0 ? 'Today' : ('+' . $d . 'd'),
            'date'  => date('d M', $pointTs),
            'pct'   => $pct,
            'level' => $lvl,
        ];
    }
}

// Product images
$images = db_all(
    'SELECT image_path, alt_text FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, display_order',
    [$product['id']]
);

// Reviews (verified purchases only)
$reviews = db_all(
    "SELECT r.*,
            p.full_name AS reviewer_name,
            rep.body AS reply_body,
            rep.created_at AS reply_at,
            rt.company_name AS retailer_name
     FROM reviews r
     LEFT JOIN profiles p ON p.user_id = r.user_id
     LEFT JOIN review_replies rep ON rep.review_id = r.id
     LEFT JOIN retailers rt ON rt.id = rep.retailer_id
     WHERE r.product_id = ? AND r.is_approved = 1
     ORDER BY r.created_at DESC LIMIT 5",
    [$product['id']]
);
$avgRating = (float) db_scalar(
    'SELECT AVG(rating) FROM reviews WHERE product_id = ? AND is_approved = 1',
    [$product['id']]
);
$reviewCount = (int) db_scalar(
    'SELECT COUNT(*) FROM reviews WHERE product_id = ? AND is_approved = 1',
    [$product['id']]
);

// Check if current user can review this product (bought it + not yet reviewed)
$canReview = false;
if (auth_check() && auth_role() === 'CUSTOMER') {
    $canReview = (bool) db_scalar(
        "SELECT COUNT(*) FROM orders o
         JOIN order_items oi ON oi.order_id = o.id
         WHERE o.user_id = ? AND oi.product_id = ?
           AND o.status IN ('PROCESSING','QUALITY_CHECK','PACKED','OUT_FOR_DELIVERY','DELIVERED')
           AND NOT EXISTS (
               SELECT 1 FROM reviews r
               WHERE r.user_id = o.user_id
                 AND r.product_id = oi.product_id
                 AND r.order_id = o.id
           )",
        [auth_id(), $product['id']]
    );
}

// Related products (same category, different products)
$related = db_all(
    "SELECT p.id, p.name, p.slug, p.base_price,
            (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
     FROM products p
     WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1 AND p.deleted_at IS NULL
     LIMIT 4",
    [$product['category_id'], $product['id']]
);

// R-APP-36: Frequently Bought Together (rule-based, no ML)
$frequentlyBoughtTogether = reco_frequently_bought_together((int) $product['id'], 4);

$pageTitle = $product['name'] . ' — FreshMart';
require_once __DIR__ . '/../../includes/header.php';
?>

<?php
// SEO: schema.org Product structured data (rich snippets + AI shopping agents)
$ld = [
    '@context'    => 'https://schema.org',
    '@type'       => 'Product',
    'name'        => $product['name'],
    'description' => trim(strip_tags((string) ($product['description'] ?? ''))),
    'sku'         => $product['sku'] ?? null,
    'category'    => $product['category_name'] ?? null,
    'brand'       => ['@type' => 'Brand', 'name' => $product['retailer_name'] ?? 'FreshMart'],
    'image'       => !empty($images) ? upload_url($images[0]['image_path']) : null,
    'offers'      => [
        '@type'         => 'Offer',
        'priceCurrency' => 'MYR',
        'price'         => number_format((float) ($freshness['final_price'] ?? $product['base_price']), 2, '.', ''),
        'availability'  => $totalStock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'url'           => url('/shop/product.php?slug=' . urlencode($product['slug'])),
    ],
];
if ($reviewCount > 0) {
    $ld['aggregateRating'] = [
        '@type'       => 'AggregateRating',
        'ratingValue' => number_format($avgRating, 1),
        'reviewCount' => $reviewCount,
    ];
}
$ld = array_filter($ld, fn($v) => $v !== null && $v !== '');
?>
<script type="application/ld+json">
<?= json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
</script>

<section class="container" style="padding: var(--space-6) 0;">

    <!-- Breadcrumb -->
    <nav style="font-size: 0.875rem; color: var(--color-text-muted); margin-bottom: var(--space-4);">
        <a href="<?= url('/shop/browse.php') ?>">Browse</a>
        →
        <a href="<?= url('/shop/browse.php?category=' . $product['category_slug']) ?>"><?= e($product['category_name']) ?></a>
        →
        <span><?= e($product['name']) ?></span>
    </nav>

    <div class="product-layout">

        <!-- Image gallery -->
        <div>
            <div style="aspect-ratio: 1; background: var(--color-bg); border-radius: var(--radius-lg); overflow: hidden; display: grid; place-items: center; position: relative;">
                <?php if (!empty($images)): ?>
                    <img id="mainImage" src="<?= upload_url($images[0]['image_path']) ?>"
                         alt="<?= attr($images[0]['alt_text']) ?>"
                         style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <span class="img-fallback"><?= icon('leaf', 96) ?></span>
                <?php endif; ?>

                <?php if ($freshness): ?>
                    <?= freshness_ring_html($freshness, 76) ?>
                <?php endif; ?>
                <?php if ($freshness && !empty($freshness['is_discounted'])): ?>
                    <div class="discount-tag" style="top: var(--space-4); right: var(--space-4); position: absolute;">
                        -<?= (int) $freshness['discount_pct'] ?>%
                    </div>
                <?php endif; ?>
            </div>

            <?php if (count($images) > 1): ?>
                <div style="display: grid; grid-template-columns: repeat(<?= min(5, count($images)) ?>, 1fr); gap: var(--space-2); margin-top: var(--space-3);">
                    <?php foreach ($images as $img): ?>
                        <button onclick="document.getElementById('mainImage').src='<?= upload_url($img['image_path']) ?>'"
                                style="aspect-ratio: 1; border: 1px solid var(--color-border); border-radius: var(--radius); overflow: hidden; padding: 0; background: var(--color-bg); cursor: pointer;">
                            <img src="<?= upload_url($img['image_path']) ?>" alt="<?= attr($img['alt_text'] ?? $product['name']) ?>" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Info & buy -->
        <div>
            <div style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: var(--space-2);">
                <?= e($product['retailer_name']) ?>
                <?php if (!empty($product['origin'])): ?>
                    · 📍 <?= e($product['origin']) ?>
                <?php endif; ?>
            </div>
            <h1 style="margin-bottom: var(--space-3);"><?= e($product['name']) ?></h1>

            <?php if ($reviewCount > 0): ?>
                <div style="margin-bottom: var(--space-4); color: var(--color-text-muted); font-size: 0.9375rem;">
                    <?= str_repeat('★', round($avgRating)) ?><?= str_repeat('☆', 5 - (int) round($avgRating)) ?>
                    · <?= number_format($avgRating, 1) ?> · <?= $reviewCount ?> review<?= $reviewCount === 1 ? '' : 's' ?>
                </div>
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: var(--space-4);">
                <div>
                <?php if ($freshness && !empty($freshness['is_discounted'])): ?>
                    <span style="font-size: 2rem; font-weight: 700; color: var(--color-accent);">
                        <?= format_myr($freshness['final_price']) ?>
                    </span>
                    <span style="font-size: 1.125rem; color: var(--color-text-muted); text-decoration: line-through; margin-left: var(--space-2);">
                        <?= format_myr($product['base_price']) ?>
                    </span>
                <?php else: ?>
                    <span style="font-size: 2rem; font-weight: 700;">
                        <?= format_myr($freshness['final_price'] ?? $product['base_price']) ?>
                    </span>
                <?php endif; ?>
                <span style="color: var(--color-text-muted); margin-left: var(--space-2);">
                    per <?= e($product['unit_code']) ?>
                </span>
                </div>
                <?php if (auth_check() && auth_role() === 'CUSTOMER'):
                    $inWishlist = wishlist_has(auth_id(), (int) $product['id']);
                ?>
                <form method="post" action="<?= url('/wishlist.php') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    <input type="hidden" name="return_to" value="<?= attr('/shop/product.php?slug=' . urlencode($slug)) ?>">
                    <button type="submit"
                            title="<?= $inWishlist ? 'Remove from wishlist' : 'Add to wishlist' ?>"
                            style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 50%; width: 44px; height: 44px; font-size: 1.25rem; cursor: pointer; color: <?= $inWishlist ? 'var(--color-danger)' : 'var(--color-text-muted)' ?>;">
                        <?= $inWishlist ? '❤️' : '🤍' ?>
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <?php if ($freshness): ?>
                <?php
                    $fPct   = (float) $freshness['freshness_percent'];
                    $fColor = $freshness['freshness_color'];
                    $fLabel = $freshness['freshness_label'];
                    $fDays  = (int) $freshness['days_remaining'];
                ?>
                <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-4) var(--space-5); margin-bottom: var(--space-4);">
                    <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: var(--space-2);">
                        <span style="font-size: 0.6875rem; letter-spacing: 0.1em; text-transform: uppercase; color: var(--color-text-muted);">Freshness</span>
                        <span style="font-size: 0.8125rem; color: var(--color-text-muted);"><?= e($fLabel) ?> · <?= $fDays ?>d left</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: var(--space-3);">
                        <div style="flex: 1; height: 10px; background: var(--color-bg); border-radius: 999px; overflow: hidden; border: 1px solid var(--color-border);">
                            <div style="height: 100%; width: <?= max(2, min(100, $fPct)) ?>%; background: <?= e($fColor) ?>; border-radius: 999px; transition: width 0.4s ease;"></div>
                        </div>
                        <span style="font-size: 1.375rem; font-weight: 700; color: <?= e($fColor) ?>; min-width: 56px; text-align: right; font-variant-numeric: tabular-nums;">
                            <?= number_format($fPct, 0) ?>%
                        </span>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: var(--space-2);">
                        Calculated live from this batch's age using our
                        <a href="<?= url('/shop/freshness.php') ?>" style="color: var(--color-text-muted); text-decoration: underline;">power-law decay model</a>
                        (decay exponent n=<?= number_format((float) $freshness['freshness_exponent'], 1) ?> for <?= e($product['category_name']) ?>).
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($forecast)): ?>
                <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-4) var(--space-5); margin-bottom: var(--space-4);">
                    <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: var(--space-3);">
                        <span style="font-size: 0.6875rem; letter-spacing: 0.1em; text-transform: uppercase; color: var(--color-text-muted);">Freshness forecast</span>
                        <span style="font-size: 0.75rem; color: var(--color-text-muted);">next 7 days</span>
                    </div>
                    <div style="position: relative; height: 180px;">
                        <canvas id="freshnessChart"></canvas>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: var(--space-2);">
                        Projected using our power-law model (n=<?= number_format((float) $product['decay_exponent'], 1) ?> for <?= e($product['category_name']) ?>). Buy sooner for peak freshness.
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($displayBatch): ?>
                <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius); padding: var(--space-3) var(--space-4); margin-bottom: var(--space-4); font-size: 0.9375rem;">
                    <div style="color: var(--color-text-muted); font-size: 0.8125rem; margin-bottom: 2px;">Batch info (FEFO will fulfil first)</div>
                    <div><strong>Best before:</strong> <?= format_date($displayBatch['expiry_date']) ?>
                        (<?= relative_date($displayBatch['expiry_date']) ?>)</div>
                    <div style="color: var(--color-text-muted); font-size: 0.8125rem;">
                        Received <?= format_date($displayBatch['received_date']) ?>
                        · <?= number_format($totalStock, 2) ?> <?= e($product['unit_code']) ?> in stock
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($totalStock <= 0): ?>
                <div class="flash flash-error">⚠️ Out of stock</div>
            <?php else: ?>
                <?php
                    // Sensible quantity stepping:
                    //  - weight items (kg/g): step 0.1 (100g), minimum 0.1
                    //  - countable items (piece/dozen/loaf...): step 1, minimum 1
                    $isWeight = !empty($product['unit_is_weight']);
                    $rawMin   = (float) $product['min_order_qty'];
                    if ($isWeight) {
                        $stepVal = '0.1';
                        $minVal  = max(0.1, $rawMin);
                        $startVal = number_format($minVal, 1, '.', '');
                        $maxVal  = number_format($totalStock, 1, '.', '');
                    } else {
                        $stepVal = '1';
                        $minVal  = max(1, ceil($rawMin));
                        $startVal = (string) (int) $minVal;
                        $maxVal  = (string) (int) floor($totalStock);
                    }
                ?>
                <form method="post" action="<?= url('/shop/cart.php') ?>"
                      style="display: flex; gap: var(--space-3); margin-bottom: var(--space-5); align-items: stretch;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    <input type="number" name="quantity"
                           value="<?= attr($startVal) ?>"
                           min="<?= attr($isWeight ? number_format($minVal, 1, '.', '') : (string)(int)$minVal) ?>"
                           max="<?= attr($maxVal) ?>"
                           step="<?= $stepVal ?>"
                           class="form-control" style="width: 100px; text-align: center;">
                    <button type="submit" class="btn btn-primary btn-lg" style="flex: 1;">
                        Add to cart
                    </button>
                </form>
            <?php endif; ?>

            <?php if (!empty($product['description'])): ?>
                <h3 style="font-size: 1rem; margin-bottom: var(--space-2);">About this product</h3>
                <p style="color: var(--color-text-muted); margin-bottom: var(--space-4);">
                    <?= nl2br(e($product['description'])) ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($product['storage_instruction'])): ?>
                <h3 style="font-size: 1rem; margin-bottom: var(--space-2);">Storage</h3>
                <p style="color: var(--color-text-muted);">
                    <?= nl2br(e($product['storage_instruction'])) ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($reviews) || $canReview): ?>
    <section style="margin-top: var(--space-12);">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: var(--space-3); flex-wrap: wrap;">
            <h2 style="font-size: 1.5rem; margin: 0;">Customer reviews</h2>
            <?php if ($canReview): ?>
                <a href="<?= url('/shop/review.php?product_id=' . $product['id']) ?>"
                   class="btn btn-primary btn-sm">
                    ✏️ Write a Review
                </a>
            <?php endif; ?>
        </div>
        <?php if (empty($reviews) && $canReview): ?>
            <p style="color: var(--color-text-muted); margin-top: var(--space-3);">
                No reviews yet — be the first to share your experience!
            </p>
        <?php endif; ?>
        <div style="display: grid; gap: var(--space-3); margin-top: var(--space-4);">
            <?php foreach ($reviews as $r): ?>
                <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-4);">
                    <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-2);">
                        <strong><?= e($r['reviewer_name'] ?? 'Customer') ?></strong>
                        <span style="color: var(--color-warning);">
                            <?= str_repeat('★', (int) $r['rating']) ?><?= str_repeat('☆', 5 - (int) $r['rating']) ?>
                        </span>
                        <span style="color: var(--color-text-muted); font-size: 0.8125rem;">
                            <?= format_date($r['created_at']) ?>
                        </span>
                    </div>
                    <?php if (!empty($r['title'])): ?>
                        <div style="font-weight: 600; margin-bottom: var(--space-1);"><?= e($r['title']) ?></div>
                    <?php endif; ?>
                    <p style="margin: 0; color: var(--color-text);"><?= nl2br(e($r['body'])) ?></p>

                    <?php if (!empty($r['reply_body'])): ?>
                        <div style="margin-top: var(--space-3); margin-left: var(--space-4); padding: var(--space-3); background: var(--color-primary-light); border-left: 3px solid var(--color-primary); border-radius: var(--radius);">
                            <div style="font-size: 0.6875rem; letter-spacing: 0.1em; text-transform: uppercase; color: var(--color-primary-dark); font-weight: 600; margin-bottom: 4px;">
                                🏢 <?= e($r['retailer_name'] ?? 'Seller') ?> replied · <?= format_date($r['reply_at']) ?>
                            </div>
                            <p style="margin: 0; color: var(--color-text); line-height: 1.5; font-size: 0.9375rem;"><?= nl2br(e($r['reply_body'])) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($recentlyViewed)): ?>
    <section style="margin-top: var(--space-12);">
        <h2 style="font-size: 1.5rem;">Recently viewed</h2>
        <div class="product-grid-4" style="margin-top: var(--space-4);">
            <?php foreach ($recentlyViewed as $rv): ?>
                <a href="<?= url('/shop/product.php?slug=' . urlencode($rv['slug'])) ?>"
                   class="product-card-v2" style="color: inherit;">
                    <div class="product-card-image">
                        <?php if (!empty($rv['primary_image'])): ?>
                            <img src="<?= upload_url($rv['primary_image']) ?>" alt="<?= attr($rv['name']) ?>" loading="lazy" style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                            <span>🥬</span>
                        <?php endif; ?>
                        <?php if (!empty($rv['expiry_date'])): ?>
                            <?= freshness_badge_html($rv['freshness_level'], $rv['days_remaining']) ?>
                        <?php endif; ?>
                    </div>
                    <div class="product-card-body">
                        <div class="product-card-name"><?= e($rv['name']) ?></div>
                        <div class="product-card-pricing">
                            <span class="price-final"><?= format_myr($rv['final_price'] ?? $rv['base_price']) ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($frequentlyBoughtTogether)): ?>
    <?= reco_render_section('Frequently Bought Together', '🛒', $frequentlyBoughtTogether,
        'Customers who bought ' . $product['name'] . ' also bought these') ?>
    <?php endif; ?>

    <?php if (!empty($related)): ?>
    <section style="margin-top: var(--space-12);">
        <h2 style="font-size: 1.5rem;">Related products</h2>
        <div class="product-grid-4" style="margin-top: var(--space-4);">
            <?php foreach ($related as $r): ?>
                <a href="<?= url('/shop/product.php?slug=' . urlencode($r['slug'])) ?>"
                   class="product-card-v2" style="color: inherit;">
                    <div class="product-card-image">
                        <?php if (!empty($r['primary_image'])): ?>
                            <img src="<?= upload_url($r['primary_image']) ?>" alt="<?= attr($r['name']) ?>" loading="lazy" style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                            <span>🥬</span>
                        <?php endif; ?>
                    </div>
                    <div class="product-card-body">
                        <div class="product-card-name"><?= e($r['name']) ?></div>
                        <div class="product-card-pricing">
                            <span class="price-final"><?= format_myr($r['base_price']) ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</section>

<?php if (!empty($forecast)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    var canvas = document.getElementById('freshnessChart');
    if (!canvas || typeof Chart === 'undefined') return;

    var forecast = <?= json_encode($forecast) ?>;
    var labels = forecast.map(function (f) { return f.label; });
    var data   = forecast.map(function (f) { return f.pct; });

    // Boutique palette to match the freshness levels
    var levelColor = {
        VERY_FRESH:  '#4a5a3a',
        FRESH:       '#7a8467',
        ENJOY_SOON:  '#c9a55a',
        LAST_CHANCE: '#b85c38',
        EXPIRED:     '#9a3b22'
    };
    var pointColors = forecast.map(function (f) { return levelColor[f.level] || '#7a8467'; });

    new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                borderColor: '#4a5a3a',
                borderWidth: 2,
                tension: 0.3,
                fill: true,
                backgroundColor: 'rgba(74,90,58,0.08)',
                pointBackgroundColor: pointColors,
                pointBorderColor: pointColors,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: function (items) {
                            var f = forecast[items[0].dataIndex];
                            return f.label + ' (' + f.date + ')';
                        },
                        label: function (item) {
                            var f = forecast[item.dataIndex];
                            return f.pct + '% · ' + f.level.replace('_', ' ').toLowerCase();
                        }
                    }
                }
            },
            scales: {
                y: {
                    min: 0, max: 100,
                    ticks: { callback: function (v) { return v + '%'; }, font: { size: 10 } },
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 10 } }
                }
            }
        }
    });
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
