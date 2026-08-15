<?php
/**
 * FreshMart — Hybrid Landing Page (v2)
 *
 * Layout sections (in order):
 *   1. Header (clean, Apple-esque)
 *   2. Stats bar (transparency-first dashboard KPIs)
 *   3. Editorial hero (serif font, spotlight Last Chance item)
 *   4. Category chips (Shopee-style horizontal scroll)
 *   5. Product grid (4-col with freshness progress bars)
 *   6. Recently viewed (if any)
 *   7. Freshness explainer footer section
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_helpers.php';
require_once __DIR__ . '/../includes/freshness.php';
require_once __DIR__ . '/../includes/fefo.php';

$pageTitle = 'FreshMart — Fresh Produce, Transparently';

// ============================================================
// 1) HERO PRODUCT — Pick a Last Chance item for storytelling
// ============================================================
$heroProduct = db_one(
    "SELECT
        p.id, p.name, p.slug, p.base_price, p.origin, p.description,
        c.name AS category_name,
        COALESCE(p.decay_exponent_override, c.decay_exponent, 1.00) AS decay_exponent,
        pi.image_path AS primary_image,
        sb.id AS display_batch_id, sb.received_date, sb.expiry_date
     FROM products p
     JOIN categories c ON c.id = p.category_id
     JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
        AND pi.image_path NOT LIKE 'placeholders/%'
     JOIN stock_batches sb ON sb.id = (
        SELECT id FROM stock_batches
        WHERE product_id = p.id AND status = 'ACTIVE'
          AND quantity_remaining > 0 AND expiry_date > CURDATE()
        ORDER BY expiry_date ASC LIMIT 1
     )
     WHERE p.is_active = 1 AND p.deleted_at IS NULL
     ORDER BY p.is_featured DESC, RAND()
     LIMIT 1"
);
if ($heroProduct) {
    $heroProduct = decorate_with_freshness($heroProduct);
}

// ============================================================
// 2) FEATURED PRODUCTS — 8 items across freshness spectrum
// ============================================================
$featuredFilter = $heroProduct ? "AND p.id != " . (int) $heroProduct['id'] : "";
$products = db_all(
    "SELECT
        p.id, p.name, p.slug, p.base_price, p.origin,
        c.name AS category_name, c.icon AS category_icon,
        COALESCE(p.decay_exponent_override, c.decay_exponent, 1.00) AS decay_exponent,
        (SELECT image_path FROM product_images pi
         WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) AS primary_image,
        (
            SELECT sb.id FROM stock_batches sb
            WHERE sb.product_id = p.id AND sb.status = 'ACTIVE'
              AND sb.quantity_remaining > 0 AND sb.expiry_date > CURDATE()
            ORDER BY sb.expiry_date ASC LIMIT 1
        ) AS display_batch_id,
        (
            SELECT sb.expiry_date FROM stock_batches sb
            WHERE sb.product_id = p.id AND sb.status = 'ACTIVE'
              AND sb.quantity_remaining > 0 AND sb.expiry_date > CURDATE()
            ORDER BY sb.expiry_date ASC LIMIT 1
        ) AS expiry_date,
        (
            SELECT sb.received_date FROM stock_batches sb
            WHERE sb.product_id = p.id AND sb.status = 'ACTIVE'
              AND sb.quantity_remaining > 0 AND sb.expiry_date > CURDATE()
            ORDER BY sb.expiry_date ASC LIMIT 1
        ) AS received_date
     FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE p.is_active = 1 AND p.deleted_at IS NULL
       $featuredFilter
     ORDER BY p.is_featured DESC, p.id ASC
     LIMIT 8"
);

$products = array_map(function ($p) {
    if (empty($p['expiry_date'])) {
        $p['freshness_level'] = 'EXPIRED';
        $p['final_price'] = $p['base_price'];
        $p['is_discounted'] = false;
        $p['days_remaining'] = 0;
        $p['freshness_pct'] = 0;
        return $p;
    }
    $p = decorate_with_freshness($p);
    // also calculate freshness percent for the progress bar
    $p['freshness_pct'] = (int) max(0, min(100, freshness_percent($p['received_date'], $p['expiry_date'], $p['decay_exponent'])));
    return $p;
}, $products);

// ============================================================
// 3) DASHBOARD STATS — sustainability + freshness KPIs
// ============================================================

// Compute Last Chance count + rescued kg LIVE from freshness (not from the
// stale selling_price_override column, which we no longer write to).
$activeBatches = db_all(
    "SELECT sb.quantity_remaining,
            COALESCE(p.decay_exponent_override, c.decay_exponent, 1.00) AS decay_exponent,
            sb.received_date, sb.expiry_date
     FROM stock_batches sb
     JOIN products p   ON p.id = sb.product_id
     JOIN categories c ON c.id = p.category_id
     WHERE sb.status = 'ACTIVE' AND sb.quantity_remaining > 0
       AND sb.expiry_date > CURDATE()"
);
$lastChanceCount = 0;
foreach ($activeBatches as $b) {
    $lvl = freshness_level($b['received_date'], $b['expiry_date'], (float) $b['decay_exponent']);
    if ($lvl === 'LAST_CHANCE') {
        $lastChanceCount++;
    }
}

// "Saved from waste" = units actually sold while in Last Chance state.
// Uses the SAME definition as the admin dashboard so the two figures match.
$savedUnits = (float) db_scalar(
    "SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE freshness_at_order = 'LAST_CHANCE'"
);

$stats = [
    'products'   => (int) db_scalar("SELECT COUNT(*) FROM products WHERE is_active = 1"),
    'retailers'  => (int) db_scalar("SELECT COUNT(*) FROM retailers WHERE approval_status = 'APPROVED'"),
    'last_chance' => $lastChanceCount,
    'saved_kg'   => $savedUnits,
];

// ============================================================
// 4) CATEGORIES — for chip row
// ============================================================
$homeCategories = db_all(
    "SELECT c.id, c.name, c.slug,
            (SELECT COUNT(*) FROM products p
             WHERE p.category_id = c.id AND p.is_active = 1 AND p.deleted_at IS NULL) AS product_count,
            (SELECT pi.image_path
             FROM products p
             JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
             WHERE p.category_id = c.id AND p.is_active = 1 AND p.deleted_at IS NULL
               AND pi.image_path NOT LIKE 'placeholders/%'
             ORDER BY p.is_featured DESC, p.id ASC
             LIMIT 1) AS cover_image
     FROM categories c
     WHERE c.is_active = 1
     ORDER BY c.display_order
     LIMIT 8"
);

// emoji map (frontend display only)
$catEmoji = [
    'vegetables' => '🥬', 'fruits' => '🍎', 'dairy' => '🥛',
    'meat' => '🥩', 'seafood' => '🐟', 'bakery' => '🥖',
    'eggs-tofu' => '🥚', 'herbs-spice' => '🌿',
];

// ============================================================
// 4b) ACTIVE VOUCHERS — for the homepage voucher strip
// ============================================================
$homeVouchers = db_all(
    "SELECT code, description, discount_type, discount_value, min_order_value, max_discount, expires_at
     FROM promo_codes
     WHERE is_active = 1
       AND (starts_at IS NULL OR starts_at <= NOW())
       AND (expires_at IS NULL OR expires_at >= NOW())
       AND (usage_limit IS NULL OR usage_count < usage_limit)
     ORDER BY min_order_value ASC, discount_value DESC
     LIMIT 6"
);

// ============================================================
// 5) RECENTLY VIEWED (R-APP-22)
// ============================================================
require_once __DIR__ . '/../includes/recently_viewed.php';
$recentlyViewed = recently_viewed_products(limit: 6);
$recentlyViewed = array_map('decorate_with_freshness', $recentlyViewed);

// ============================================================
// 6) RECOMMENDATIONS (R-APP-36)
// ============================================================
require_once __DIR__ . '/../includes/recommendations.php';
$popularThisWeek = reco_popular_this_week(6);

// Platform-wide sustainability impact — total food bought while in Last Chance
// (i.e. rescued from being thrown away). Mirrors the admin "kg saved" metric.
$kgRescued = (float) db_scalar(
    "SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE freshness_at_order = 'LAST_CHANCE'"
);

// "You May Also Like" — pull extra, then remove anything already shown in
// "Popular This Week" so the two rows don't look identical.
$mayAlsoLikeRaw = reco_you_may_like(auth_check() ? auth_id() : null, 12);
$popularIds     = array_column($popularThisWeek, 'id');
$mayAlsoLike    = array_values(array_filter(
    $mayAlsoLikeRaw,
    fn($p) => !in_array($p['id'], $popularIds, true)
));
$mayAlsoLike    = array_slice($mayAlsoLike, 0, 6);

// Top customer reviews for the homepage testimonials strip (real 4-5 star reviews).
$testimonials = db_all(
    "SELECT r.rating, r.title, r.body, r.created_at,
            pr.full_name AS reviewer_name,
            p.name AS product_name
     FROM reviews r
     JOIN users u    ON u.id = r.user_id
     LEFT JOIN profiles pr ON pr.user_id = r.user_id
     JOIN products p ON p.id = r.product_id
     WHERE r.is_approved = 1 AND r.rating >= 4
       AND r.body IS NOT NULL AND CHAR_LENGTH(r.body) > 15
     ORDER BY r.rating DESC, r.created_at DESC
     LIMIT 3"
);

require_once __DIR__ . '/../includes/header.php';

// freshness level → color mapping for progress bars
function fresh_color($level) {
    return [
        'VERY_FRESH'  => '#16a34a',
        'FRESH'       => '#84cc16',
        'ENJOY_SOON'  => '#eab308',
        'LAST_CHANCE' => '#ea580c',
        'EXPIRED'     => '#dc2626',
    ][$level] ?? '#9ca3af';
}
?>

<!-- ============ SECTION 1: DASHBOARD STATS BAR ============ -->
<section class="stats-bar">
    <div class="container">
        <div class="stats-bar-grid">
            <div class="stat-item">
                <div class="stat-label">Active products</div>
                <div class="stat-value"><?= number_format($stats['products']) ?></div>
            </div>
            <div class="stat-item highlight">
                <div class="stat-label"><?= icon('leaf', 16) ?> Saved from waste</div>
                <div class="stat-value"><?= number_format($stats['saved_kg'], 0) ?> <span class="stat-unit">items</span></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Last Chance items</div>
                <div class="stat-value alert"><?= number_format($stats['last_chance']) ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Approved retailers</div>
                <div class="stat-value"><?= number_format($stats['retailers']) ?></div>
            </div>
        </div>
    </div>
</section>

<!-- ============ SECTION 2: HERO POSTER BANNER ============ -->
<section class="hero-poster-section">
    <div class="container">
        <div class="hero-poster">
            <div class="hero-poster-text">
                <div class="hero-poster-eyebrow"><?= icon('leaf', 16) ?> Farm fresh, delivered</div>
                <h1 class="hero-poster-title">
                    Fresh produce,<br><span class="scribble">honest freshness.</span>
                </h1>
                <p class="hero-poster-desc">
                    Every product shows a live freshness score. Shop with confidence,
                    save money on Last Chance deals, and help cut food waste.
                </p>
                <div class="hero-poster-actions">
                    <a href="<?= url('/shop/browse.php') ?>" class="hero-poster-btn hero-poster-btn-primary">
                        Start shopping
                    </a>
                    <a href="<?= url('/shop/freshness.php') ?>" class="hero-poster-btn hero-poster-btn-ghost">
                        How freshness works →
                    </a>
                </div>
                <div class="hero-poster-stats">
                    <div class="hps-item">
                        <span class="hps-num"><?= (int) $stats['products'] ?>+</span>
                        <span class="hps-label">Fresh products</span>
                    </div>
                    <div class="hps-item">
                        <span class="hps-num"><?= number_format($stats['saved_kg'], 0) ?></span>
                        <span class="hps-label">Items saved</span>
                    </div>
                    <div class="hps-item">
                        <span class="hps-num"><?= (int) $stats['retailers'] ?></span>
                        <span class="hps-label">Local retailers</span>
                    </div>
                </div>
            </div>
            <div class="hero-poster-image">
                <?php if ($heroProduct && !empty($heroProduct['primary_image'])): ?>
                    <img src="<?= upload_url($heroProduct['primary_image']) ?>" alt="Fresh produce" loading="eager">
                <?php else: ?>
                    <div class="hero-poster-image-fallback"><?= icon('leaf', 120) ?></div>
                <?php endif; ?>
                <?php if ($heroProduct): ?>
                    <a href="<?= url('/shop/product.php?slug=' . urlencode($heroProduct['slug'])) ?>" class="hero-poster-tag">
                        <span class="hpt-name"><?= e($heroProduct['name']) ?></span>
                        <span class="hpt-price"><?= format_myr($heroProduct['final_price'] ?? $heroProduct['base_price']) ?></span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ============ SECTION 2b: TRUST BADGES ============ -->
<section class="trust-badges-section">
    <div class="container">
        <div class="trust-badges">
            <div class="trust-badge">
                <span class="trust-badge-icon"><?= icon('truck', 16) ?></span>
                <div class="trust-badge-text">
                    <div class="trust-badge-title">Free delivery over RM50</div>
                    <div class="trust-badge-sub">On all orders</div>
                </div>
            </div>
            <div class="trust-badge">
                <span class="trust-badge-icon"><?= icon('leaf', 16) ?></span>
                <div class="trust-badge-text">
                    <div class="trust-badge-title">Farm fresh daily</div>
                    <div class="trust-badge-sub">Sourced locally</div>
                </div>
            </div>
            <div class="trust-badge">
                <span class="trust-badge-icon"><?= icon('recycle', 16) ?></span>
                <div class="trust-badge-text">
                    <div class="trust-badge-title">Zero-waste mission</div>
                    <div class="trust-badge-sub">Last Chance deals</div>
                </div>
            </div>
            <div class="trust-badge">
                <span class="trust-badge-icon"><?= icon('lock', 16) ?></span>
                <div class="trust-badge-text">
                    <div class="trust-badge-title">Secure checkout</div>
                    <div class="trust-badge-sub">Safe & encrypted</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ SECTION 3: CATEGORY CIRCLES ============ -->
<section class="category-circles-section">
    <div class="container">
        <div class="section-header u-mb-6">
            <h2>Shop by <span class="scribble">Category</span></h2>
            <a href="<?= url('/shop/browse.php') ?>" class="section-link">View all →</a>
        </div>
        <div class="category-circles">
            <?php foreach ($homeCategories as $c):
                $emoji = $catEmoji[$c['slug']] ?? '🛒';
            ?>
                <a href="<?= url('/shop/browse.php?category=' . urlencode($c['slug'])) ?>" class="cat-circle">
                    <div class="cat-circle-img">
                        <?php if (!empty($c['cover_image'])): ?>
                            <img src="<?= upload_url($c['cover_image']) ?>" alt="<?= attr($c['name']) ?>" loading="lazy">
                        <?php else: ?>
                            <span class="cat-circle-emoji"><?= $emoji ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="cat-circle-name"><?= e($c['name']) ?></div>
                    <div class="cat-circle-count"><?= (int) $c['product_count'] ?> items</div>
                </a>
            <?php endforeach; ?>
            <?php if ($stats['last_chance'] > 0): ?>
                <a href="<?= url('/shop/browse.php?freshness=LAST_CHANCE') ?>" class="cat-circle cat-circle-alert">
                    <div class="cat-circle-img">
                        <span class="cat-circle-emoji"><?= icon('flame', 16) ?></span>
                    </div>
                    <div class="cat-circle-name">Last Chance</div>
                    <div class="cat-circle-count"><?= $stats['last_chance'] ?> deals</div>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ============ SECTION 3a: LAST CHANCE PROMO BANNER ============ -->
<section class="section u-pt-6 u-pb-0">
    <div class="container">
        <a href="<?= url('/shop/browse.php?freshness=LAST_CHANCE') ?>" class="lc-promo">
            <div class="lc-promo-content">
                <div class="lc-promo-kicker"><?= icon('flame', 16) ?> Last Chance</div>
                <div class="lc-promo-title">15% off — or more</div>
                <div class="lc-promo-sub">
                    Every item near its best-before date is automatically discounted.
                    Great produce, better price, less waste.
                </div>
            </div>
            <div class="lc-promo-cta">Shop Last Chance →</div>
        </a>
    </div>
</section>

<!-- ============ SECTION 3b: VOUCHER STRIP ============ -->
<?php if (!empty($homeVouchers)): ?>
<section class="section u-pt-0">
    <div class="container">
        <div class="u-bg-grad-warm u-bordered u-r-lg u-p-5">
            <div class="u-flex u-ai-baseline u-jc-between u-mb-4 u-wrap u-gap-2">
                <div>
                    <div class="banner-eyebrow">Grab a deal</div>
                    <h2 class="u-m-1-0-0 u-t-22"><?= icon('ticket', 16) ?>️ Vouchers you can use</h2>
                </div>
                <span class="u-t-13 u-muted">
                    Copy a code, then paste it at checkout
                </span>
            </div>

            <div class="u-grid u-cols-fit-220 u-gap-3">
                <?php foreach ($homeVouchers as $v):
                    if ($v['discount_type'] === 'PERCENTAGE') {
                        $headline = (int) $v['discount_value'] . '% OFF';
                        if (!empty($v['max_discount'])) $headline .= ' up to ' . format_myr($v['max_discount']);
                    } else {
                        $headline = format_myr($v['discount_value']) . ' OFF';
                    }
                ?>
                    <div class="u-bg-surface u-bordered-dashed-primary u-r u-p-4 u-rel u-ovh">
                        <div class="u-t-20 u-fw-700 u-fg-primary-dk">
                            <?= e($headline) ?>
                        </div>
                        <?php if (!empty($v['description'])): ?>
                            <div class="u-t-13 u-muted u-m-1-0-3">
                                <?= e($v['description']) ?>
                            </div>
                        <?php else: ?>
                            <div class="u-mb-3"></div>
                        <?php endif; ?>
                        <div class="u-flex u-ai-center u-gap-2">
                            <code class="u-flex-1 u-bg-primary-lt u-fg-primary-dk u-fw-700 u-ls-08 u-p-pill-sm u-r-sm u-t-15 u-ta-c">
                                <?= e($v['code']) ?>
                            </code>
                            <button type="button"
                                    onclick="navigator.clipboard.writeText('<?= e($v['code']) ?>'); this.textContent='✓'; setTimeout(()=>this.textContent='Copy',1500);"
                                    class="btn btn-secondary btn-sm u-nowrap">Copy</button>
                        </div>
                        <div class="u-t-11 u-muted u-mt-2">
                            <?php if ((float) $v['min_order_value'] > 0): ?>
                                Min spend <?= format_myr($v['min_order_value']) ?>
                            <?php else: ?>
                                No minimum spend
                            <?php endif; ?>
                            <?php if (!empty($v['expires_at'])): ?>
                                · until <?= format_date($v['expires_at']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ SECTION 4: PRODUCT GRID (4-col with freshness bars) ============ -->

<section class="section reveal" id="fresh-picks-section">
    <div class="container">
        <div class="section-header">
            <h2>Today's Fresh <span class="scribble">Picks</span></h2>
            <a href="<?= url('/shop/browse.php') ?>" class="section-link">View all →</a>
        </div>
        <?php if (empty($products)): ?>
            <p class="u-muted">No products available yet.</p>
        <?php else: ?>
            <div class="fresh-picks-carousel-wrap">
                <button type="button" class="carousel-nav carousel-nav-prev" id="fpPrev" aria-label="Previous">
                    ‹
                </button>
                <div class="fresh-picks-carousel" id="freshPicksCarousel">
                <?php foreach ($products as $p):
                    $isLastChance = ($p['freshness_level'] === 'LAST_CHANCE');
                    $barColor     = fresh_color($p['freshness_level']);
                ?>
                    <a href="<?= url('/shop/product.php?slug=' . urlencode($p['slug'])) ?>"
                       class="product-card-v2 carousel-slide <?= $isLastChance ? 'last-chance' : '' ?>">

                        <div class="product-card-image">
                            <?php if (!empty($p['primary_image'])): ?>
                                <img src="<?= upload_url($p['primary_image']) ?>" alt="<?= attr($p['name']) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="img-fallback"><?= icon('leaf', 56) ?></span>
                            <?php endif; ?>

                            <?= freshness_ring_html($p) ?>

                            <?php if (!empty($p['is_discounted'])): ?>
                                <span class="discount-tag-tr">−<?= (int) $p['discount_pct'] ?>%</span>
                            <?php endif; ?>
                        </div>

                        <div class="product-card-body">
                            <div class="product-card-name"><?= e($p['name']) ?></div>
                            <?php if (!empty($p['origin'])): ?>
                                <div class="product-card-origin"><?= icon('pin', 14) ?> <?= e($p['origin']) ?></div>
                            <?php endif; ?>

                            <div class="product-card-pricing">
                                <span class="price-final <?= $isLastChance ? 'price-alert' : '' ?>">
                                    <?= format_myr($p['final_price'] ?? $p['base_price']) ?>
                                </span>
                                <?php if (!empty($p['is_discounted'])): ?>
                                    <span class="price-base-strike"><?= format_myr($p['base_price']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
                </div>
                <button type="button" class="carousel-nav carousel-nav-next" id="fpNext" aria-label="Next">
                    ›
                </button>
            </div>
            <div class="carousel-dots" id="fpDots"></div>
        <?php endif; ?>
    </div>
</section>


<script>
(function() {
    const track = document.getElementById('freshPicksCarousel');
    const prevBtn = document.getElementById('fpPrev');
    const nextBtn = document.getElementById('fpNext');
    const dotsWrap = document.getElementById('fpDots');
    if (!track || !prevBtn || !nextBtn) return;

    function getSlidesPerView() {
        const w = window.innerWidth;
        if (w <= 480) return 1;
        if (w <= 768) return 2;
        if (w <= 1024) return 3;
        return 4;
    }

    function updateNav() {
        prevBtn.disabled = track.scrollLeft <= 4;
        nextBtn.disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 4;
        // Update dots
        const slidesPerView = getSlidesPerView();
        const totalSlides = track.children.length;
        const totalPages = Math.max(1, Math.ceil(totalSlides / slidesPerView));
        const currentPage = Math.round(track.scrollLeft / (track.clientWidth || 1));
        [...dotsWrap.children].forEach((d, i) => {
            d.classList.toggle('is-active', i === Math.min(currentPage, totalPages - 1));
        });
    }

    function renderDots() {
        const slidesPerView = getSlidesPerView();
        const totalSlides = track.children.length;
        const totalPages = Math.max(1, Math.ceil(totalSlides / slidesPerView));
        dotsWrap.innerHTML = '';
        for (let i = 0; i < totalPages; i++) {
            const dot = document.createElement('button');
            dot.className = 'dot' + (i === 0 ? ' is-active' : '');
            dot.type = 'button';
            dot.setAttribute('aria-label', 'Page ' + (i + 1));
            dot.addEventListener('click', () => {
                track.scrollTo({ left: i * track.clientWidth, behavior: 'smooth' });
            });
            dotsWrap.appendChild(dot);
        }
    }

    function scrollByPage(direction) {
        track.scrollBy({ left: direction * track.clientWidth, behavior: 'smooth' });
    }

    prevBtn.addEventListener('click', () => scrollByPage(-1));
    nextBtn.addEventListener('click', () => scrollByPage(1));
    track.addEventListener('scroll', updateNav, { passive: true });
    window.addEventListener('resize', () => {
        renderDots();
        updateNav();
    });

    renderDots();
    setTimeout(updateNav, 100);
})();
</script>

<!-- ============ RECENTLY VIEWED (only if any) ============ -->
<?php if (!empty($recentlyViewed)): ?>
<section class="section u-pt-0">
    <div class="container">
        <div class="section-header">
            <h2 class="u-t-18">Recently viewed</h2>
        </div>
        <div class="product-grid-recent">
            <?php foreach ($recentlyViewed as $rv):
                $barColor = fresh_color($rv['freshness_level'] ?? 'FRESH');
            ?>
                <a href="<?= url('/shop/product.php?slug=' . urlencode($rv['slug'])) ?>"
                   class="product-card-v2">
                    <div class="product-card-image">
                        <?php if (!empty($rv['primary_image'])): ?>
                            <img src="<?= upload_url($rv['primary_image']) ?>" alt="<?= attr($rv['name']) ?>" loading="lazy">
                        <?php else: ?>
                            <span class="product-emoji"><?= icon('cart', 16) ?></span>
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
    </div>
</section>
<?php endif; ?>

<!-- ============ POPULAR THIS WEEK + YOU MAY ALSO LIKE (R-APP-36) ============ -->
<?php if (!empty($popularThisWeek)): ?>
<section class="section u-pt-0">
    <div class="container">
        <?= reco_render_section('Popular This Week', '🔥', $popularThisWeek,
            'Best sellers in the last 7 days') ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($mayAlsoLike)): ?>
<section class="section u-pt-0">
    <div class="container">
        <?= reco_render_section('You May Also Like', '✨', $mayAlsoLike,
            auth_check() ? 'Picked for you based on your shopping history' : 'Customer favourites') ?>
    </div>
</section>
<?php endif; ?>

<!-- ============ SECTION: TESTIMONIALS ============ -->
<?php if (!empty($testimonials)): ?>
<section class="section">
    <div class="container">
        <div class="section-header u-jc-center u-ta-c u-col u-gap-1">
            <h2>What our <span class="scribble">customers say</span></h2>
            <p class="u-muted u-t-144 u-m-0">Real reviews from verified buyers</p>
        </div>
        <div class="testimonials">
            <?php foreach ($testimonials as $t): ?>
                <div class="testimonial-card">
                    <div class="testimonial-stars">
                        <?= str_repeat('★', (int) $t['rating']) . str_repeat('☆', 5 - (int) $t['rating']) ?>
                    </div>
                    <?php if (!empty($t['title'])): ?>
                        <div class="testimonial-title"><?= e($t['title']) ?></div>
                    <?php endif; ?>
                    <p class="testimonial-body">"<?= e($t['body']) ?>"</p>
                    <div class="testimonial-author">
                        <span class="testimonial-name"><?= e($t['reviewer_name'] ?? 'Verified Buyer') ?></span>
                        <span class="testimonial-product">on <?= e($t['product_name']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ SECTION: SERVICE BANNERS ============ -->
<section class="section u-pb-0">
    <div class="container">
        <div class="service-banners">
            <div class="service-banner service-banner-green">
                <div class="service-banner-icon"><?= icon('truck', 16) ?></div>
                <div class="service-banner-text">
                    <div class="service-banner-title">Free shipping above RM50</div>
                    <div class="service-banner-sub">Stock up and save — delivery's on us over RM50.</div>
                </div>
            </div>
            <div class="service-banner service-banner-cream">
                <div class="service-banner-icon"><?= icon('calendar', 16) ?></div>
                <div class="service-banner-text">
                    <div class="service-banner-title">Choose your delivery date</div>
                    <div class="service-banner-sub">Pick the day that suits you — freshness planned around it.</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ FRESHNESS EXPLAINER (footer banner) ============ -->

<section class="section freshness-banner reveal">
    <div class="container">
        <div class="freshness-banner-grid">
            <div>
                <div class="banner-eyebrow">How it works</div>
                <h2 class="u-mt-1">Our Freshness <span class="scribble">Promise</span></h2>
                <p>
                    Every product carries one of four freshness levels, calculated automatically
                    from each batch's age using a category-aware power-law decay model.
                    <strong>Last Chance items get an automatic 15% discount</strong> —
                    cutting waste while keeping prices fair.
                </p>
                <a href="<?= url('/shop/freshness.php') ?>" class="btn-pill btn-pill-outline">Learn more →</a>

                <?php if ($kgRescued > 0): ?>
                    <div class="impact-callout">
                        <div class="impact-figure"><?= number_format($kgRescued, 0) ?> kg</div>
                        <div class="impact-text">
                            of food <strong>rescued from waste</strong> by FreshMart shoppers so far —
                            every Last Chance item you buy adds to this.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="freshness-levels-grid">
                <div class="level-card level-card-very">
                    <div class="level-dot"></div>
                    <div class="level-name">Very Fresh</div>
                    <div class="level-range">&gt;75%</div>
                </div>
                <div class="level-card level-card-fresh">
                    <div class="level-dot"></div>
                    <div class="level-name">Fresh</div>
                    <div class="level-range">50-75%</div>
                </div>
                <div class="level-card level-card-soon">
                    <div class="level-dot"></div>
                    <div class="level-name">Enjoy Soon</div>
                    <div class="level-range">25-50%</div>
                </div>
                <div class="level-card level-card-last">
                    <div class="level-dot"></div>
                    <div class="level-name">Last Chance</div>
                    <div class="level-range">&lt;25% −15%</div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
