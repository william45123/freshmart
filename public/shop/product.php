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

<section class="container u-py-6">

    <!-- Breadcrumb -->
    <nav class="u-t-14 u-muted u-mb-4">
        <a href="<?= url('/shop/browse.php') ?>">Browse</a>
        →
        <a href="<?= url('/shop/browse.php?category=' . $product['category_slug']) ?>"><?= e($product['category_name']) ?></a>
        →
        <span><?= e($product['name']) ?></span>
    </nav>

    <div class="product-layout">

        <!-- Image gallery -->
        <div>
            <div class="u-square u-bg-page u-r-lg u-ovh u-grid u-place-center u-rel">
                <?php if (!empty($images)): ?>
                    <img id="mainImage" src="<?= upload_url($images[0]['image_path']) ?>"
                         alt="<?= attr($images[0]['alt_text']) ?>"
                         class="media-fill">
                <?php else: ?>
                    <span class="img-fallback"><?= icon('leaf', 96) ?></span>
                <?php endif; ?>

                <?php if ($freshness): ?>
                    <?= freshness_ring_html($freshness, 76) ?>
                <?php endif; ?>
                <?php if ($freshness && !empty($freshness['is_discounted'])): ?>
                    <div class="discount-tag u-top-4 u-right-4 u-abs">
                        -<?= (int) $freshness['discount_pct'] ?>%
                    </div>
                <?php endif; ?>
            </div>

            <?php if (count($images) > 1): ?>
                <div class="thumb-grid u-mt-3" style="--n: <?= min(5, count($images)) ?>">
                    <?php foreach ($images as $img): ?>
                        <button onclick="document.getElementById('mainImage').src='<?= upload_url($img['image_path']) ?>'"
                                class="u-square u-bordered u-r u-ovh u-p-0 u-bg-page u-pointer">
                            <img src="<?= upload_url($img['image_path']) ?>" alt="<?= attr($img['alt_text'] ?? $product['name']) ?>" loading="lazy" class="media-fill">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Info & buy -->
        <div>
            <div class="u-t-13 u-muted u-mb-2">
                <?= e($product['retailer_name']) ?>
                <?php if (!empty($product['origin'])): ?>
                    · <?= icon('pin', 16) ?> <?= e($product['origin']) ?>
                <?php endif; ?>
            </div>
            <h1 class="u-mb-3"><?= e($product['name']) ?></h1>

            <?php if ($reviewCount > 0): ?>
                <div class="u-mb-4 u-muted u-t-15">
                    <?= str_repeat('★', round($avgRating)) ?><?= str_repeat('☆', 5 - (int) round($avgRating)) ?>
                    · <?= number_format($avgRating, 1) ?> · <?= $reviewCount ?> review<?= $reviewCount === 1 ? '' : 's' ?>
                </div>
            <?php endif; ?>

            <div class="u-flex u-jc-between u-ai-flexstart u-mb-4">
                <div>
                <?php if ($freshness && !empty($freshness['is_discounted'])): ?>
                    <span class="u-t-32 u-fw-700 u-fg-accent">
                        <?= format_myr($freshness['final_price']) ?>
                    </span>
                    <span class="u-t-18 u-muted u-strike u-ml-2">
                        <?= format_myr($product['base_price']) ?>
                    </span>
                <?php else: ?>
                    <span class="u-t-32 u-fw-700">
                        <?= format_myr($freshness['final_price'] ?? $product['base_price']) ?>
                    </span>
                <?php endif; ?>
                <span class="u-muted u-ml-2">
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
                            class="wish-toggle<?= $inWishlist ? ' is-active' : '' ?>">
                        <?= icon('heart', 20) ?>
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
                <div class="panel u-p-4-5 u-mb-4">
                    <div class="u-flex u-jc-between u-ai-baseline u-mb-2">
                        <span class="u-t-11 u-ls-10 u-upper u-muted">Freshness</span>
                        <span class="u-t-13 u-muted"><?= e($fLabel) ?> · <?= $fDays ?>d left</span>
                    </div>
                    <div class="u-flex u-ai-center u-gap-3">
                        <div class="u-flex-1 u-h-10 u-bg-page u-r-pill u-ovh u-bordered">
                            <div class="fresh-bar-fill" style="--pct: <?= max(2, min(100, $fPct)) ?>%; --fresh: <?= e($fColor) ?>"></div>
                        </div>
                        <span class="fresh-value" style="--fresh: <?= e($fColor) ?>">
                            <?= number_format($fPct, 0) ?>%
                        </span>
                    </div>
                    <div class="u-t-12 u-muted u-mt-2">
                        Calculated live from this batch's age using our
                        <a href="<?= url('/shop/freshness.php') ?>" class="u-muted u-underline">power-law decay model</a>
                        (decay exponent n=<?= number_format((float) $freshness['freshness_exponent'], 1) ?> for <?= e($product['category_name']) ?>).
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($forecast)): ?>
                <div class="forecast-card">
                    <div class="u-flex u-jc-between u-ai-baseline u-mb-3">
                        <span class="u-t-11 u-ls-10 u-upper u-muted">Freshness forecast</span>
                        <span class="u-t-12 u-muted">next 7 days</span>
                    </div>
                    <div class="u-rel u-h-180">
                        <div class="chart-wrap"><canvas id="freshnessChart"></canvas></div>
                    </div>
                    <div class="u-t-12 u-muted u-mt-2">
                        Projected using our power-law model (n=<?= number_format((float) $product['decay_exponent'], 1) ?> for <?= e($product['category_name']) ?>). Buy sooner for peak freshness.
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($displayBatch): ?>
                <div class="u-bg-page u-bordered u-r u-p-3-4 u-mb-4 u-t-15">
                    <div class="u-muted u-t-13 u-mb-2px">Batch info (FEFO will fulfil first)</div>
                    <div><strong>Best before:</strong> <?= format_date($displayBatch['expiry_date']) ?>
                        (<?= relative_date($displayBatch['expiry_date']) ?>)</div>
                    <div class="u-muted u-t-13">
                        Received <?= format_date($displayBatch['received_date']) ?>
                        · <?= number_format($totalStock, 2) ?> <?= e($product['unit_code']) ?> in stock
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($totalStock <= 0): ?>
                <div class="flash flash-error"><?= icon('alert', 18) ?> Out of stock</div>
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
                      class="u-flex u-gap-3 u-mb-5 u-ai-stretch">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    <input type="number" name="quantity"
                           value="<?= attr($startVal) ?>"
                           min="<?= attr($isWeight ? number_format($minVal, 1, '.', '') : (string)(int)$minVal) ?>"
                           max="<?= attr($maxVal) ?>"
                           step="<?= $stepVal ?>"
                           class="form-control u-w-100 u-ta-c">
                    <button type="submit" class="btn btn-primary btn-lg u-flex-1">
                        Add to cart
                    </button>
                </form>

                <?php // §7.3 sticky buy bar. Same action, same fields, same
                      // CSRF token as the form above — a second submitter for
                      // the same operation, not a second code path. Sits above
                      // the tab bar; hidden at >=1024px where the inline form
                      // is on screen anyway. ?>
                <div class="buybar" id="buybar">
                    <div>
                        <?php // Reads $freshness, the same source as the main price
                              // block above. $product has no final_price, so the
                              // earlier fallback quietly showed the undiscounted
                              // figure while the page showed the discounted one. ?>
                        <div class="buybar-price"><?= format_myr((float) ($freshness['final_price'] ?? $product['base_price'])) ?></div>
                        <?php if (!empty($freshness['is_discounted'])): ?>
                            <div class="u-t-12 u-muted u-strike"><?= format_myr((float) $product['base_price']) ?></div>
                        <?php endif; ?>
                    </div>
                    <form method="post" action="<?= url('/shop/cart.php') ?>" class="u-flex u-gap-2 u-ai-stretch">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        <input type="number" name="quantity" id="buybar-qty"
                               value="<?= attr($startVal) ?>"
                               min="<?= attr($isWeight ? number_format($minVal, 1, '.', '') : (string)(int)$minVal) ?>"
                               max="<?= attr($maxVal) ?>"
                               step="<?= $stepVal ?>"
                               inputmode="decimal"
                               class="form-control u-w-70 u-ta-c"
                               aria-label="Quantity">
                        <button type="submit" class="btn btn-primary">Add to cart</button>
                    </form>
                </div>
                <script>
                // Keep the two quantity inputs in step so whichever the
                // customer used last is the one that submits.
                (function () {
                    var a = document.querySelector('form input[name="quantity"]:not(#buybar-qty)');
                    var b = document.getElementById('buybar-qty');
                    if (!a || !b) return;
                    a.addEventListener('input', function () { b.value = a.value; });
                    b.addEventListener('input', function () { a.value = b.value; });
                })();
                </script>
            <?php endif; ?>

            <?php if (!empty($product['description'])): ?>
                <h3 class="u-t-16 u-mb-2 disclosure-head" data-disclosure>About this product<span class="disclosure-mark" aria-hidden="true"></span></h3>
                <p class="u-muted u-mb-4">
                    <?= nl2br(e($product['description'])) ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($product['storage_instruction'])): ?>
                <h3 class="u-t-16 u-mb-2 disclosure-head" data-disclosure>Storage<span class="disclosure-mark" aria-hidden="true"></span></h3>
                <p class="u-muted">
                    <?= nl2br(e($product['storage_instruction'])) ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($reviews) || $canReview): ?>
    <section class="u-mt-12">
        <div class="u-flex u-jc-between u-ai-center u-gap-3 u-wrap">
            <h2 class="u-t-24 u-m-0">Customer reviews</h2>
            <?php if ($canReview): ?>
                <a href="<?= url('/shop/review.php?product_id=' . $product['id']) ?>"
                   class="btn btn-primary btn-sm">
                    <?= icon('edit', 16) ?> Write a Review
                </a>
            <?php endif; ?>
        </div>
        <?php if (empty($reviews) && $canReview): ?>
            <p class="u-muted u-mt-3">
                No reviews yet — be the first to share your experience!
            </p>
        <?php endif; ?>
        <div class="u-grid u-gap-3 u-mt-4">
            <?php foreach ($reviews as $r): ?>
                <div class="panel u-p-4">
                    <div class="u-flex u-ai-center u-gap-2 u-mb-2">
                        <strong><?= e($r['reviewer_name'] ?? 'Customer') ?></strong>
                        <span class="u-fg-warning">
                            <?= str_repeat('★', (int) $r['rating']) ?><?= str_repeat('☆', 5 - (int) $r['rating']) ?>
                        </span>
                        <span class="u-muted u-t-13">
                            <?= format_date($r['created_at']) ?>
                        </span>
                    </div>
                    <?php if (!empty($r['title'])): ?>
                        <div class="u-fw-600 u-mb-1"><?= e($r['title']) ?></div>
                    <?php endif; ?>
                    <p class="u-m-0 u-ink"><?= nl2br(e($r['body'])) ?></p>

                    <?php if (!empty($r['reply_body'])): ?>
                        <div class="u-mt-3 u-ml-4 u-p-3 u-bg-primary-lt u-bl-primary u-r">
                            <div class="u-t-11 u-ls-10 u-upper u-fg-primary-dk u-fw-600 u-mb-1">
                                <?= icon('store', 16) ?> <?= e($r['retailer_name'] ?? 'Seller') ?> replied · <?= format_date($r['reply_at']) ?>
                            </div>
                            <p class="u-m-0 u-ink u-lh-15 u-t-15"><?= nl2br(e($r['reply_body'])) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($recentlyViewed)): ?>
    <section class="u-mt-12">
        <h2 class="u-t-24">Recently viewed</h2>
        <div class="product-grid-4 u-mt-4">
            <?php foreach ($recentlyViewed as $rv): ?>
                <div class="product-card-v2">
                        <a href="<?= url('/shop/product.php?slug=' . urlencode($rv['slug'])) ?>" class="card-link u-fg-inherit u-nodecor" aria-hidden="false" tabindex="0"></a>
                    <div class="product-card-image">
                        <?php if (!empty($rv['primary_image'])): ?>
                            <img src="<?= upload_url($rv['primary_image']) ?>" alt="<?= attr($rv['name']) ?>" loading="lazy" class="media-fill">
                        <?php else: ?>
                            <span><?= icon('leaf', 16) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($rv['expiry_date'])): ?>
                            <?= freshness_badge_html($rv['freshness_level'], $rv['days_remaining']) ?>
                        <?php endif; ?>
                    
                        <?php $__qid = (int) ($rv['id'] ?? 0); if ($__qid): ?>
                        <form method="post" action="<?= url('/shop/cart.php') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= $__qid ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" class="card-quick-add"
                                    aria-label="Add <?= attr($rv['name'] ?? 'product') ?> to cart">
                                <?= icon('plus', 20) ?>
                            </button>
                        </form>
                        <?php endif; ?>
                        </div>
                    <div class="product-card-body">
                        <div class="product-card-name"><?= e($rv['name']) ?></div>
                        <div class="product-card-pricing">
                            <span class="price-final"><?= format_myr($rv['final_price'] ?? $rv['base_price']) ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($frequentlyBoughtTogether)): ?>
    <?= reco_render_section('Frequently Bought Together', 'cart', $frequentlyBoughtTogether,
        'Customers who bought ' . $product['name'] . ' also bought these') ?>
    <?php endif; ?>

    <?php if (!empty($related)): ?>
    <section class="u-mt-12">
        <h2 class="u-t-24">Related products</h2>
        <div class="product-grid-4 u-mt-4">
            <?php foreach ($related as $r): ?>
                <div class="product-card-v2">
                        <a href="<?= url('/shop/product.php?slug=' . urlencode($r['slug'])) ?>" class="card-link u-fg-inherit u-nodecor" aria-hidden="false" tabindex="0"></a>
                    <div class="product-card-image">
                        <?php if (!empty($r['primary_image'])): ?>
                            <img src="<?= upload_url($r['primary_image']) ?>" alt="<?= attr($r['name']) ?>" loading="lazy" class="media-fill">
                        <?php else: ?>
                            <span><?= icon('leaf', 16) ?></span>
                        <?php endif; ?>
                    
                        <?php $__qid = (int) ($p['id'] ?? 0); if ($__qid): ?>
                        <form method="post" action="<?= url('/shop/cart.php') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= $__qid ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" class="card-quick-add"
                                    aria-label="Add <?= attr($p['name'] ?? 'product') ?> to cart">
                                <?= icon('plus', 20) ?>
                            </button>
                        </form>
                        <?php endif; ?>
                        </div>
                    <div class="product-card-body">
                        <div class="product-card-name"><?= e($r['name']) ?></div>
                        <div class="product-card-pricing">
                            <span class="price-final"><?= format_myr($r['base_price']) ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</section>

<?php if (!empty($forecast)): ?>
<script src="<?= asset('js/chart.umd.min.js') ?>"></script>
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

    // Narrow phones: fewer visible tick labels and slightly smaller points.
    // The forecast itself still plots all 8 days — this only thins the labels.
    var isNarrow = window.innerWidth < 480;

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
                pointRadius: isNarrow ? 3 : 4,
                pointHoverRadius: isNarrow ? 4 : 6
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
                    ticks: {
                        font: { size: 10 },
                        maxRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: isNarrow ? 5 : 8
                    }
                }
            }
        }
    });
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
// §7.3 — collapse description/storage on mobile so the buy decision is not
// buried under prose. Progressive: without JS every section stays open, and
// above 1024 the CSS disables the affordance entirely.
(function () {
    var mq = matchMedia('(max-width: 1023px)');
    document.querySelectorAll('[data-disclosure]').forEach(function (head, i) {
        var body = head.nextElementSibling;
        if (!body) return;
        var id = 'disc-' + i;
        body.id = id;
        head.setAttribute('role', 'button');
        head.setAttribute('tabindex', '0');
        head.setAttribute('aria-controls', id);

        function set(open) {
            head.classList.toggle('is-open', open);
            head.setAttribute('aria-expanded', open ? 'true' : 'false');
            body.hidden = !open;
        }
        function sync() { set(!mq.matches); }   // open on desktop, closed on mobile
        sync();
        mq.addEventListener('change', sync);

        head.addEventListener('click', function () {
            if (!mq.matches) return;
            set(body.hidden);
        });
        head.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); head.click(); }
        });
    });
})();
</script>
