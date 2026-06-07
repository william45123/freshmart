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
        (SELECT image_path FROM product_images pi
         WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) AS primary_image,
        sb.id AS display_batch_id, sb.received_date, sb.expiry_date,
        sb.selling_price_override
     FROM products p
     JOIN categories c ON c.id = p.category_id
     JOIN stock_batches sb ON sb.id = (
        SELECT id FROM stock_batches
        WHERE product_id = p.id AND status = 'ACTIVE'
          AND quantity_remaining > 0 AND expiry_date > CURDATE()
          AND selling_price_override IS NOT NULL
        ORDER BY expiry_date ASC LIMIT 1
     )
     WHERE p.is_active = 1 AND p.deleted_at IS NULL
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
$stats = [
    'products'   => (int) db_scalar("SELECT COUNT(*) FROM products WHERE is_active = 1"),
    'retailers'  => (int) db_scalar("SELECT COUNT(*) FROM retailers WHERE approval_status = 'APPROVED'"),
    'last_chance' => (int) db_scalar(
        "SELECT COUNT(*) FROM stock_batches sb
         JOIN products p ON p.id = sb.product_id
         WHERE sb.status = 'ACTIVE' AND sb.quantity_remaining > 0
           AND sb.selling_price_override IS NOT NULL"
    ),
    // "saved from waste" estimate: kg of LAST_CHANCE batches currently available + items already sold
    'saved_kg'   => (float) db_scalar(
        "SELECT COALESCE(SUM(quantity_remaining * 0.5), 0) +
                COALESCE((SELECT SUM(quantity) FROM order_items WHERE freshness_at_order = 'LAST_CHANCE'), 0)
         FROM stock_batches WHERE selling_price_override IS NOT NULL AND status='ACTIVE'"
    ),
];

// ============================================================
// 4) CATEGORIES — for chip row
// ============================================================
$homeCategories = db_all(
    "SELECT c.id, c.name, c.slug,
            (SELECT COUNT(*) FROM products p
             WHERE p.category_id = c.id AND p.is_active = 1 AND p.deleted_at IS NULL) AS product_count
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
$mayAlsoLike     = reco_you_may_like(auth_check() ? auth_id() : null, 6);

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
                <div class="stat-label">🌱 Saved from waste</div>
                <div class="stat-value"><?= number_format($stats['saved_kg'], 1) ?> <span class="stat-unit">kg</span></div>
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

<!-- ============ SECTION 2: EDITORIAL HERO (Last Chance spotlight) ============ -->
<?php if ($heroProduct): ?>
<section class="editorial-hero">
    <div class="container">
        <div class="hero-eyebrow">Featured today · Last Chance</div>
        <div class="hero-grid">
            <div class="hero-text">
                <h1 class="hero-title">
                    <?= e($heroProduct['name']) ?>.<br>
                    <em>Save it from waste.</em>
                </h1>
                <p class="hero-description">
                    Last batch, expires <?= relative_date($heroProduct['expiry_date']) ?>.
                    Auto-discounted because every <?= e(strtolower(explode(' ', $heroProduct['name'])[0])) ?>
                    sold means one less thrown away.
                </p>
                <div class="hero-pricing">
                    <span class="hero-price-final"><?= format_myr($heroProduct['final_price']) ?></span>
                    <?php if (!empty($heroProduct['is_discounted'])): ?>
                        <span class="hero-price-strike"><?= format_myr($heroProduct['base_price']) ?></span>
                        <span class="hero-discount-tag">−<?= (int) $heroProduct['discount_pct'] ?>% AUTO</span>
                    <?php endif; ?>
                </div>
                <div class="hero-actions">
                    <a href="<?= url('/shop/product.php?slug=' . urlencode($heroProduct['slug'])) ?>"
                       class="btn-pill btn-pill-primary">
                        Add to Cart
                    </a>
                    <a href="<?= url('/shop/freshness.php') ?>" class="btn-pill btn-pill-outline">
                        How freshness works →
                    </a>
                </div>
            </div>
            <div class="hero-image">
                <?php if (!empty($heroProduct['primary_image'])): ?>
                    <img src="<?= upload_url($heroProduct['primary_image']) ?>" alt="<?= attr($heroProduct['name']) ?>">
                <?php else: ?>
                    <span class="hero-emoji">🥭</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ SECTION 3: CATEGORY CHIPS ============ -->
<section class="category-chips-section">
    <div class="container">
        <div class="category-chips">
            <a href="<?= url('/shop/browse.php') ?>" class="chip chip-active">All · <?= $stats['products'] ?></a>
            <?php foreach ($homeCategories as $c):
                $emoji = $catEmoji[$c['slug']] ?? '🛒';
            ?>
                <a href="<?= url('/shop/browse.php?category=' . urlencode($c['slug'])) ?>" class="chip">
                    <?= $emoji ?> <?= e($c['name']) ?> · <?= (int) $c['product_count'] ?>
                </a>
            <?php endforeach; ?>
            <?php if ($stats['last_chance'] > 0): ?>
                <a href="<?= url('/shop/browse.php?freshness=LAST_CHANCE') ?>" class="chip chip-alert">
                    🟠 Last Chance · <?= $stats['last_chance'] ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ============ SECTION 3b: VOUCHER STRIP ============ -->
<?php if (!empty($homeVouchers)): ?>
<section class="section" style="padding-top: 0;">
    <div class="container">
        <div style="background: linear-gradient(135deg, var(--color-primary-light), var(--color-surface)); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-5);">
            <div style="display: flex; align-items: baseline; justify-content: space-between; margin-bottom: var(--space-4); flex-wrap: wrap; gap: var(--space-2);">
                <div>
                    <div class="banner-eyebrow">Grab a deal</div>
                    <h2 style="margin: 4px 0 0; font-size: 1.375rem;">🎟️ Vouchers you can use</h2>
                </div>
                <span style="font-size: 0.8125rem; color: var(--color-text-muted);">
                    Copy a code, then paste it at checkout
                </span>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--space-3);">
                <?php foreach ($homeVouchers as $v):
                    if ($v['discount_type'] === 'PERCENTAGE') {
                        $headline = (int) $v['discount_value'] . '% OFF';
                        if (!empty($v['max_discount'])) $headline .= ' up to ' . format_myr($v['max_discount']);
                    } else {
                        $headline = format_myr($v['discount_value']) . ' OFF';
                    }
                ?>
                    <div style="background: var(--color-surface); border: 1px dashed var(--color-primary); border-radius: var(--radius); padding: var(--space-4); position: relative; overflow: hidden;">
                        <div style="font-size: 1.25rem; font-weight: 700; color: var(--color-primary-dark);">
                            <?= e($headline) ?>
                        </div>
                        <?php if (!empty($v['description'])): ?>
                            <div style="font-size: 0.8125rem; color: var(--color-text-muted); margin: 4px 0 var(--space-3);">
                                <?= e($v['description']) ?>
                            </div>
                        <?php else: ?>
                            <div style="margin-bottom: var(--space-3);"></div>
                        <?php endif; ?>
                        <div style="display: flex; align-items: center; gap: var(--space-2);">
                            <code style="flex: 1; background: var(--color-primary-light); color: var(--color-primary-dark); font-weight: 700; letter-spacing: 0.08em; padding: 6px 10px; border-radius: var(--radius-sm); font-size: 0.9375rem; text-align: center;">
                                <?= e($v['code']) ?>
                            </code>
                            <button type="button"
                                    onclick="navigator.clipboard.writeText('<?= e($v['code']) ?>'); this.textContent='✓'; setTimeout(()=>this.textContent='Copy',1500);"
                                    class="btn btn-secondary btn-sm" style="white-space: nowrap;">Copy</button>
                        </div>
                        <div style="font-size: 0.6875rem; color: var(--color-text-muted); margin-top: var(--space-2);">
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
<section class="section">
    <div class="container">
        <div class="section-header">
            <h2>Today's Fresh Picks</h2>
            <a href="<?= url('/shop/browse.php') ?>" class="section-link">View all →</a>
        </div>
        <?php if (empty($products)): ?>
            <p style="color: var(--color-text-muted)">No products available yet.</p>
        <?php else: ?>
            <div class="product-grid-4">
                <?php foreach ($products as $p):
                    $isLastChance = ($p['freshness_level'] === 'LAST_CHANCE');
                    $barColor     = fresh_color($p['freshness_level']);
                ?>
                    <a href="<?= url('/shop/product.php?slug=' . urlencode($p['slug'])) ?>"
                       class="product-card-v2 <?= $isLastChance ? 'last-chance' : '' ?>">

                        <div class="product-card-image">
                            <?php if (!empty($p['primary_image'])): ?>
                                <img src="<?= upload_url($p['primary_image']) ?>" alt="<?= attr($p['name']) ?>">
                            <?php else: ?>
                                <span class="product-emoji">🛒</span>
                            <?php endif; ?>

                            <span class="freshness-badge-tl level-<?= e($p['freshness_level']) ?>">
                                <?= e(str_replace('_', ' ', $p['freshness_level'])) ?>
                            </span>

                            <?php if (!empty($p['is_discounted'])): ?>
                                <span class="discount-tag-tr">−<?= (int) $p['discount_pct'] ?>%</span>
                            <?php endif; ?>
                        </div>

                        <div class="product-card-body">
                            <div class="product-card-name"><?= e($p['name']) ?></div>
                            <?php if (!empty($p['origin'])): ?>
                                <div class="product-card-origin">📍 <?= e($p['origin']) ?></div>
                            <?php endif; ?>

                            <!-- Freshness progress bar -->
                            <div class="freshness-progress">
                                <div class="freshness-progress-fill"
                                     style="width: <?= (int) $p['freshness_pct'] ?>%; background: <?= $barColor ?>;"></div>
                            </div>

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
        <?php endif; ?>
    </div>
</section>

<!-- ============ RECENTLY VIEWED (only if any) ============ -->
<?php if (!empty($recentlyViewed)): ?>
<section class="section" style="padding-top: 0;">
    <div class="container">
        <div class="section-header">
            <h2 style="font-size: 1.125rem;">Recently viewed</h2>
        </div>
        <div class="product-grid-recent">
            <?php foreach ($recentlyViewed as $rv):
                $barColor = fresh_color($rv['freshness_level'] ?? 'FRESH');
            ?>
                <a href="<?= url('/shop/product.php?slug=' . urlencode($rv['slug'])) ?>"
                   class="product-card-v2">
                    <div class="product-card-image">
                        <?php if (!empty($rv['primary_image'])): ?>
                            <img src="<?= upload_url($rv['primary_image']) ?>" alt="<?= attr($rv['name']) ?>">
                        <?php else: ?>
                            <span class="product-emoji">🛒</span>
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
<section class="section" style="padding-top: 0;">
    <div class="container">
        <?= reco_render_section('Popular This Week', '🔥', $popularThisWeek,
            'Best sellers in the last 7 days') ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($mayAlsoLike)): ?>
<section class="section" style="padding-top: 0;">
    <div class="container">
        <?= reco_render_section('You May Also Like', '✨', $mayAlsoLike,
            auth_check() ? 'Picked for you based on your shopping history' : 'Customer favourites') ?>
    </div>
</section>
<?php endif; ?>

<!-- ============ FRESHNESS EXPLAINER (footer banner) ============ -->
<section class="section freshness-banner">
    <div class="container">
        <div class="freshness-banner-grid">
            <div>
                <div class="banner-eyebrow">How it works</div>
                <h2 style="margin-top: 4px;">Our Freshness Promise</h2>
                <p>
                    Every product carries one of four freshness levels, calculated automatically
                    from each batch's age using a category-aware power-law decay model.
                    <strong>Last Chance items get an automatic 15% discount</strong> —
                    cutting waste while keeping prices fair.
                </p>
                <a href="<?= url('/shop/freshness.php') ?>" class="btn-pill btn-pill-outline">Learn more →</a>
            </div>
            <div class="freshness-levels-grid">
                <div class="level-card" style="--c: #16a34a;">
                    <div class="level-dot"></div>
                    <div class="level-name">Very Fresh</div>
                    <div class="level-range">&gt;75%</div>
                </div>
                <div class="level-card" style="--c: #84cc16;">
                    <div class="level-dot"></div>
                    <div class="level-name">Fresh</div>
                    <div class="level-range">50-75%</div>
                </div>
                <div class="level-card" style="--c: #eab308;">
                    <div class="level-dot"></div>
                    <div class="level-name">Enjoy Soon</div>
                    <div class="level-range">25-50%</div>
                </div>
                <div class="level-card" style="--c: #ea580c;">
                    <div class="level-dot"></div>
                    <div class="level-name">Last Chance</div>
                    <div class="level-range">&lt;25% −15%</div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
