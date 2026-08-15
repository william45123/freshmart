<?php
/**
 * Customer Browse page — list all products with filters and search.
 *
 * Filters:
 *   ?category=<slug>            Category filter
 *   ?subcategory=<slug>         Subcategory filter
 *   ?freshness=LAST_CHANCE      Show only items in a freshness level
 *   ?q=<search>                 Full-text search on name+description
 *   ?sort=newest|price-asc|price-desc|expiring  Sorting
 *   ?page=N                     Pagination
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/freshness.php';
require_once __DIR__ . '/../../includes/fefo.php';
require_once __DIR__ . '/../../includes/stock_helpers.php';

$catSlug    = trim((string) input('category', ''));
$subSlug    = trim((string) input('subcategory', ''));
$freshness  = trim((string) input('freshness', ''));
$availability = trim((string) input('availability', ''));    // R-APP-19
$query      = trim((string) input('q', ''));
$sort       = (string) input('sort', 'newest');
$page       = max(1, (int) input('page', 1));
$perPage    = BROWSE_PAGE_SIZE;
$offset     = ($page - 1) * $perPage;

// F2: freshness and availability are filtered in SQL, not in PHP after
// LIMIT/OFFSET. The old code paged first and filtered second, so
// ?freshness=LAST_CHANCE returned only the matches that happened to land on
// the current page while the count and pagination reported the unfiltered
// total. Everything below filters against the display batch, so the count,
// the pagination and the result set are all derived from one predicate.
$filtersFreshness = ($freshness !== '' || in_array($sort, ['fresh-desc', 'value'], true));

// A batch created between cron runs has no cached freshness, which would make
// it invisible to the query below — absent, not merely stale. Fill any gaps
// first. Only called here because this is a filter/sort path; display paths
// compute live through decorate_with_freshness() and must not trigger it.
if ($filtersFreshness) {
    freshness_sync_batches();
}

// The display batch: earliest expiry among ACTIVE, in-stock, unexpired batches.
// Joining it once (rather than repeating correlated subqueries) is what lets
// the filters move into SQL, and it also removes a latent inconsistency — the
// old expiry_date / received_date / earliest_expiry subqueries had no
// tiebreaker, so on equal expiry dates they could each pick a different batch.
$displayBatchJoin = "
    JOIN stock_batches db ON db.id = (
        SELECT sb.id FROM stock_batches sb
         WHERE sb.product_id = p.id
           AND sb.status = 'ACTIVE'
           AND sb.quantity_remaining > 0
           AND " . sql_deliverable("sb.expiry_date") . "
         ORDER BY sb.expiry_date ASC, sb.id ASC
         LIMIT 1
    )";

// Total live stock across every qualifying batch, used by the availability
// filter and shown on the card. Defined once so filter and display agree.
$totalStockExpr = "(SELECT COALESCE(SUM(sb.quantity_remaining),0) FROM stock_batches sb
                     WHERE sb.product_id = p.id AND sb.status = 'ACTIVE'
                       AND " . sql_deliverable("sb.expiry_date") . ")";

// Build WHERE clauses. The display-batch JOIN above replaces the old
// EXISTS(...) guard: a product with no qualifying batch no longer joins.
$where = [
    'p.is_active = 1',
    'p.deleted_at IS NULL',
];
$args = [];

if ($catSlug !== '') {
    $where[] = 'c.slug = ?';
    $args[]  = $catSlug;
}
if ($subSlug !== '') {
    $where[] = 'sc.slug = ?';
    $args[]  = $subSlug;
}
if ($query !== '') {
    $where[] = 'MATCH(p.name, p.description) AGAINST(? IN NATURAL LANGUAGE MODE)';
    $args[]  = $query;
}

// F2: freshness filter, straight off the F1 cache and its index.
if ($freshness !== '') {
    $where[] = 'db.freshness_level = ?';
    $args[]  = $freshness;
}

// F2: availability filter, previously applied in PHP after paging.
// COALESCE mirrors the old PHP default of 10 when the threshold is NULL.
if ($availability === 'in_stock') {
    $where[] = "$totalStockExpr > COALESCE(p.low_stock_threshold, 10)";
} elseif ($availability === 'low_stock') {
    $where[] = "$totalStockExpr > 0";
    $where[] = "$totalStockExpr <= COALESCE(p.low_stock_threshold, 10)";
}

// Best Value Today: how much is discounted, per remaining day. A batch with a
// big markdown and little time left ranks highest. Uses the price actually
// charged (selling_price_override) rather than a nominal rate, so it cannot
// advertise a discount the checkout will not honour.
$valueExpr = "(100 * (1 - COALESCE(db.selling_price_override, p.base_price)
                          / NULLIF(p.base_price, 0)))
              / GREATEST(DATEDIFF(db.expiry_date, CURDATE()), 1)";

// Sorting
$orderBy = match($sort) {
    'price-asc'  => 'p.base_price ASC',
    'price-desc' => 'p.base_price DESC',
    'expiring'   => 'db.expiry_date ASC',
    'fresh-desc' => 'db.freshness_pct DESC, db.expiry_date ASC',
    'value'      => "$valueExpr DESC, db.expiry_date ASC",
    default      => 'p.is_featured DESC, p.created_at DESC',
};

// Count total matching products (for pagination)
$countSql = "
    SELECT COUNT(*)
    FROM products p
    JOIN categories c ON c.id = p.category_id
    LEFT JOIN subcategories sc ON sc.id = p.subcategory_id
    JOIN unit_types ut ON ut.id = p.unit_type_id
    $displayBatchJoin
    WHERE " . implode(' AND ', $where);
$totalProducts = (int) db_scalar($countSql, $args);
$totalPages    = max(1, (int) ceil($totalProducts / $perPage));

// Pull products with their display batch (earliest expiry)
$sql = "
    SELECT
        p.id, p.name, p.slug, p.sku, p.base_price, p.origin, p.low_stock_threshold,
        c.name AS category_name, c.slug AS category_slug,
        ut.code AS unit_code,
        COALESCE(p.decay_exponent_override, c.decay_exponent, 1.00) AS decay_exponent,
        (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) AS primary_image,
        $totalStockExpr AS total_stock,
        db.expiry_date   AS expiry_date,
        db.received_date AS received_date,
        db.expiry_date   AS earliest_expiry,
        db.id            AS display_batch_id,
        (SELECT ROUND(AVG(r.rating),1) FROM reviews r
         WHERE r.product_id = p.id AND r.is_approved = 1) AS avg_rating,
        (SELECT COUNT(*) FROM reviews r
         WHERE r.product_id = p.id AND r.is_approved = 1) AS review_count
    FROM products p
    JOIN categories c ON c.id = p.category_id
    LEFT JOIN subcategories sc ON sc.id = p.subcategory_id
    JOIN unit_types ut ON ut.id = p.unit_type_id
    $displayBatchJoin
    WHERE " . implode(' AND ', $where) . "
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
";

$products = db_all($sql, $args);

// Display values are still computed live — the cached columns decide which
// rows come back and in what order, decorate_with_freshness() decides what
// each card shows. The freshness/availability filters that used to run here
// now live in the SQL above, so this page's results agree with $totalProducts.
$products = array_map('decorate_with_freshness', $products);

// Get category list for sidebar
$categories = db_all('SELECT id, name, slug FROM categories WHERE is_active = 1 ORDER BY display_order');
$activeCat  = $catSlug !== '' ? db_one('SELECT id, name FROM categories WHERE slug = ?', [$catSlug]) : null;
$subcategories = $activeCat
    ? db_all('SELECT name, slug FROM subcategories WHERE category_id = ? AND is_active = 1 ORDER BY display_order', [$activeCat['id']])
    : [];

$pageTitle = ($query ? "Search: $query" : 'Browse') . ' — FreshMart';
require_once __DIR__ . '/../../includes/header.php';

/** Build current URL with one param overridden */
function url_with($overrides = []): string {
    $params = array_filter(array_merge($_GET, $overrides), fn($v) => $v !== '' && $v !== null);
    return url('/shop/browse.php' . (empty($params) ? '' : '?' . http_build_query($params)));
}
?>

<section class="container u-pt-6">
    <h1 class="u-t-28">
        <?php if ($query): ?>
            Results for "<?= e($query) ?>"
        <?php elseif ($activeCat): ?>
            <?= e($activeCat['name']) ?>
        <?php elseif ($freshness === 'LAST_CHANCE'): ?>
            <span class="label-ico"><?= icon('flame', 22) ?> Last Chance Deals</span>
        <?php else: ?>
            Browse all
        <?php endif; ?>
    </h1>

    <!-- Search bar -->
    <form method="get" action="<?= url('/shop/browse.php') ?>"
          class="u-m-4-0 u-flex u-gap-2">
        <input type="search" name="q" placeholder="Search products, e.g. lettuce, mango..."
               value="<?= attr($query) ?>" class="form-control u-flex-1">
        <button type="submit" class="btn btn-primary">Search</button>
    </form>
</section>

<section class="container browse-layout u-pt-4 u-pb-12">

    <!-- Sidebar filters -->
    <!-- §7.3 Below the laptop breakpoint this aside becomes a bottom sheet:
         the same controls, reachable without scrolling past them to get to
         the products. Above it, it is the sidebar it always was. -->
    <button type="button" class="btn btn-secondary filter-trigger" id="filter-open"
            aria-expanded="false" aria-controls="filter-sheet">
        <?= icon('filter', 18) ?> Filters<?= ($catSlug || $subSlug || $availability || $freshness) ? ' · on' : '' ?>
    </button>
    <div class="sheet" id="filter-sheet">
        <div class="sheet-scrim" data-sheet-close></div>
        <div class="sheet-panel" role="dialog" aria-modal="true" aria-label="Filters">
            <div><span class="sheet-handle"></span></div>
            <div class="sheet-body">
    <aside>
        <h3 class="u-t-12 u-upper u-ls-05 u-muted u-mb-3">Categories</h3>
        <div class="u-flex u-col u-gap-1 u-mb-6">
            <a href="<?= url_with(['category' => null, 'subcategory' => null, 'page' => null]) ?>"
               class="facet-link<?= $catSlug === '' ? ' is-active' : '' ?>">
                All
            </a>
            <?php foreach ($categories as $c): ?>
                <a href="<?= url_with(['category' => $c['slug'], 'subcategory' => null, 'page' => null]) ?>"
                   class="facet-link<?= $catSlug === $c['slug'] ? ' is-active' : '' ?>">
                    <?= e($c['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($subcategories)): ?>
        <h3 class="u-t-12 u-upper u-ls-05 u-muted u-mb-3">Subcategories</h3>
        <div class="u-flex u-col u-gap-1 u-mb-6">
            <?php foreach ($subcategories as $s): ?>
                <a href="<?= url_with(['subcategory' => $s['slug'], 'page' => null]) ?>"
                   class="facet-link facet-link-sub<?= $subSlug === $s['slug'] ? ' is-active' : '' ?>">
                    <?= e($s['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <h3 class="u-t-12 u-upper u-ls-05 u-muted u-mb-3">Availability</h3>
        <div class="u-flex u-col u-gap-1 u-mb-6">
            <?php foreach (['' => 'Any', 'in_stock' => 'In Stock', 'low_stock' => 'Low Stock'] as $key => $label): ?>
                <a href="<?= url_with(['availability' => $key ?: null, 'page' => null]) ?>"
                   class="facet-link<?= $availability === $key ? ' is-active' : '' ?>">
                    <?php $__fi = ['in_stock' => 'check', 'low_stock' => 'alert'][$key] ?? null; ?><?php if ($__fi): ?><?= icon($__fi, 16) ?> <?php endif; ?><?= e($label) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <h3 class="u-t-12 u-upper u-ls-05 u-muted u-mb-3">Freshness</h3>
        <div class="u-flex u-col u-gap-1">
            <?php foreach (['' => 'Any', 'VERY_FRESH' => 'Very Fresh', 'FRESH' => 'Fresh', 'ENJOY_SOON' => 'Enjoy Soon', 'LAST_CHANCE' => 'Last Chance'] as $key => $label): ?>
                <a href="<?= url_with(['freshness' => $key ?: null, 'page' => null]) ?>"
                   class="facet-link<?= $freshness === $key ? ' is-active' : '' ?>">
                    <?= e($label) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </aside>
            </div>
            <div class="sheet-foot">
                <button type="button" class="btn btn-primary" data-sheet-close>
                    Apply filters<span id="filter-count"> (<?= (int) $totalProducts ?>)</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Product grid -->
    <div>
        <?php // §7.3 — the count and sort controls stay reachable while
              // scrolling a long grid, instead of being left at the top. ?>
        <div class="browse-bar u-flex u-jc-between u-ai-center u-mb-4">
            <span class="u-muted">
                <?php // The filtered total, not the page slice. Before F2 the
                      // freshness filter ran in PHP after LIMIT, so this could
                      // only ever report the current page; now the count query
                      // shares the main query's predicate and the two agree. ?>
                <?= $totalProducts ?> product<?= $totalProducts === 1 ? '' : 's' ?>
            </span>
            <form method="get" class="u-flex u-gap-2 u-ai-center">
                <?php foreach ($_GET as $k => $v): if ($k === 'sort') continue; ?>
                    <input type="hidden" name="<?= e($k) ?>" value="<?= attr((string) $v) ?>">
                <?php endforeach; ?>
                <label class="u-t-14 u-muted">Sort:</label>
                <select name="sort" onchange="this.form.submit()" class="form-control u-w-auto u-p-pill-sm">
                    <option value="newest"     <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                    <option value="expiring"   <?= $sort === 'expiring' ? 'selected' : '' ?>>Expiring soonest</option>
                    <option value="fresh-desc" <?= $sort === 'fresh-desc' ? 'selected' : '' ?>>Freshest first</option>
                    <option value="value"      <?= $sort === 'value' ? 'selected' : '' ?>>Best Value Today</option>
                    <option value="price-asc"  <?= $sort === 'price-asc' ? 'selected' : '' ?>>Price: Low → High</option>
                    <option value="price-desc" <?= $sort === 'price-desc' ? 'selected' : '' ?>>Price: High → Low</option>
                </select>
            </form>
        </div>

        <?php if (empty($products)): ?>
            <div class="empty-state">
                <div class="empty-ico"><?= icon('search', 40) ?></div>
                <p class="u-t-17">No products match your filters.</p>
                <p class="u-muted">Try removing some filters or
                    <a href="<?= url('/shop/browse.php') ?>">browse all</a>.</p>
            </div>
        <?php else: ?>
            <div class="product-grid-4">
                <?php foreach ($products as $p): ?>
                    <div class="product-card-v2 <?= ($p['freshness_level'] === 'LAST_CHANCE') ? 'last-chance' : '' ?>">
                        <a href="<?= url('/shop/product.php?slug=' . urlencode($p['slug'])) ?>"
                           class="card-link u-fg-inherit u-nodecor"
                           aria-label="<?= attr($p['name']) ?>"></a>
                        <div class="product-card-image">
                            <?php if (!empty($p['primary_image'])): ?>
                                <img src="<?= upload_url($p['primary_image']) ?>" alt="<?= attr($p['name']) ?>" loading="lazy"
                                     class="media-fill">
                            <?php else: ?>
                                <span class="img-fallback"><?= icon('leaf', 56) ?></span>
                            <?php endif; ?>
                            <?= freshness_ring_html($p) ?>
                            <?php if (!empty($p['is_discounted'])): ?>
                                <span class="discount-tag">-<?= (int) $p['discount_pct'] ?>%</span>
                            <?php endif; ?>
                        <?php // §7.2 quick add — always visible where there is no
                              // hover, so the primary action is not hidden behind
                              // an interaction phones do not have. ?>
                        <form method="post" action="<?= url('/shop/cart.php') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" class="card-quick-add"
                                    aria-label="Add <?= attr($p['name']) ?> to cart">
                                <?= icon('plus', 20) ?>
                            </button>
                        </form>
                        </div>
                        <div class="product-card-body">
                            <div class="product-card-name"><?= e($p['name']) ?></div>
                            <?php if (!empty($p['review_count']) && (int)$p['review_count'] > 0): ?>
                                <div class="product-card-rating">
                                    <span class="pcr-stars"><?= str_repeat('★', (int) round($p['avg_rating'])) ?><?= str_repeat('☆', 5 - (int) round($p['avg_rating'])) ?></span>
                                    <span class="pcr-count"><?= number_format((float)$p['avg_rating'], 1) ?> (<?= (int)$p['review_count'] ?>)</span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($p['origin'])): ?>
                                <div class="product-card-origin"><?= icon('pin', 14) ?> <?= e($p['origin']) ?></div>
                            <?php endif; ?>
                            <div class="product-card-pricing">
                                <span class="price-final"><?= format_myr($p['final_price'] ?? $p['base_price']) ?></span>
                                <?php if (!empty($p['is_discounted'])): ?>
                                    <span class="price-base-strike"><?= format_myr($p['base_price']) ?></span>
                                <?php endif; ?>
                                <span class="u-muted u-t-13">/ <?= e($p['unit_code']) ?></span>
                            </div>
                            <?php if (!empty($p['expiry_date'])): ?>
                                <div class="expiry-hint">
                                    Best before <?= format_date($p['expiry_date']) ?>
                                </div>
                            <?php endif; ?>
                            <?php
                            $stockBadge = stock_alert_badge_html(
                                (float) ($p['total_stock'] ?? 0),
                                (float) ($p['low_stock_threshold'] ?? 10)
                            );
                            if ($stockBadge): ?>
                                <div class="u-mt-1"><?= $stockBadge ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Product pages">
                <?php if ($page > 1): ?>
                    <a class="page-btn page-nav" href="<?= url_with(['page' => $page - 1]) ?>">← Prev</a>
                <?php else: ?>
                    <span class="page-btn page-nav is-disabled">← Prev</span>
                <?php endif; ?>

                <?php
                // Show a compact page window: first, current-1..current+1, last
                $window = [];
                for ($i = 1; $i <= $totalPages; $i++) {
                    if ($i == 1 || $i == $totalPages || abs($i - $page) <= 1) {
                        $window[] = $i;
                    }
                }
                $prev = 0;
                foreach ($window as $i):
                    if ($prev && $i - $prev > 1): ?>
                        <span class="page-ellipsis">…</span>
                    <?php endif; ?>
                    <?php if ($i == $page): ?>
                        <span class="page-btn is-current"><?= $i ?></span>
                    <?php else: ?>
                        <a class="page-btn" href="<?= url_with(['page' => $i]) ?>"><?= $i ?></a>
                    <?php endif; ?>
                    <?php $prev = $i;
                endforeach; ?>

                <?php if ($page < $totalPages): ?>
                    <a class="page-btn page-nav" href="<?= url_with(['page' => $page + 1]) ?>">Next →</a>
                <?php else: ?>
                    <span class="page-btn page-nav is-disabled">Next →</span>
                <?php endif; ?>
            </nav>
            <p class="pagination-info">
                Showing page <?= $page ?> of <?= $totalPages ?> · <?= $totalProducts ?> products total
            </p>
        <?php endif; ?>
    </div>
</section>


<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
// §7.3 filter sheet. The facet links are ordinary <a> navigations, so the
// count is live by definition — it is re-rendered by the server on each
// change. The sheet reopens after navigating so the user keeps their place.
(function () {
    var sheet = document.getElementById('filter-sheet');
    var open  = document.getElementById('filter-open');
    if (!sheet || !open) return;
    function setOpen(on) {
        sheet.classList.toggle('is-open', on);
        open.setAttribute('aria-expanded', on ? 'true' : 'false');
        document.body.style.overflow = on ? 'hidden' : '';
        if (on) { try { sessionStorage.setItem('fm_sheet', '1'); } catch (e) {} }
        else    { try { sessionStorage.removeItem('fm_sheet'); } catch (e) {} }
    }
    open.addEventListener('click', function () { setOpen(true); });
    sheet.querySelectorAll('[data-sheet-close]').forEach(function (el) {
        el.addEventListener('click', function () { setOpen(false); });
    });
    addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
    try {
        if (sessionStorage.getItem('fm_sheet') === '1' && matchMedia('(max-width: 1023px)').matches) {
            sheet.classList.add('is-open');
            open.setAttribute('aria-expanded', 'true');
        }
    } catch (e) {}
})();
</script>
