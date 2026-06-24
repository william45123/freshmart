<?php
/**
 * Recommendations engine — rule-based, no ML.
 *
 * R-APP-36 implementation:
 *   - Frequently Bought Together (FBT)
 *   - You May Also Like (based on browsing/order history)
 *   - Popular This Week (top sellers last 7 days)
 *   - Similar Products (same category) — already in product.php
 *
 * All queries are tuned for small/medium scale (<10k products).
 * For larger catalogs, results should be cached daily.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/freshness.php';

/**
 * Frequently Bought Together — finds products that appear in the same orders
 * as the given product, ranked by co-occurrence frequency.
 *
 * Algorithm:
 *   1. Find all orders containing $productId
 *   2. Get OTHER products in those same orders
 *   3. Rank by frequency (co-occurrence count)
 *   4. Filter for in-stock + active
 *
 * Returns at most $limit products. Empty array if no co-purchase data yet.
 */
function reco_frequently_bought_together(int $productId, int $limit = 4): array {
    $rows = db_all(
        "SELECT
            p.id, p.name, p.slug, p.base_price,
            c.slug AS category_slug,
            ut.code AS unit_code,
            COALESCE(p.decay_exponent_override, c.decay_exponent, 1.0) AS decay_exponent,
            (SELECT image_path FROM product_images pi
             WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) AS primary_image,
            COUNT(*) AS co_purchase_count,
            (SELECT MIN(sb.expiry_date) FROM stock_batches sb
             WHERE sb.product_id = p.id AND sb.status = 'ACTIVE'
               AND sb.quantity_remaining > 0 AND sb.expiry_date > CURDATE()) AS expiry_date,
            (SELECT sb.received_date FROM stock_batches sb
             WHERE sb.product_id = p.id AND sb.status = 'ACTIVE'
               AND sb.quantity_remaining > 0 AND sb.expiry_date > CURDATE()
             ORDER BY sb.expiry_date ASC LIMIT 1) AS received_date
         FROM order_items oi1
         JOIN order_items oi2 ON oi1.order_id = oi2.order_id AND oi2.product_id != oi1.product_id
         JOIN products p ON p.id = oi2.product_id
         JOIN categories c ON c.id = p.category_id
         JOIN unit_types ut ON ut.id = p.unit_type_id
         WHERE oi1.product_id = ?
           AND p.is_active = 1 AND p.deleted_at IS NULL
           AND EXISTS (SELECT 1 FROM stock_batches sb
                       WHERE sb.product_id = p.id
                         AND sb.status = 'ACTIVE'
                         AND sb.quantity_remaining > 0
                         AND sb.expiry_date > CURDATE())
         GROUP BY p.id, p.name, p.slug, p.base_price, c.slug,
                  ut.code, p.decay_exponent_override, c.decay_exponent
         ORDER BY co_purchase_count DESC, p.view_count DESC
         LIMIT $limit",
        [$productId]
    );

    return array_map('decorate_with_freshness', $rows);
}

/**
 * You May Also Like — for logged-in users, based on:
 *   - Categories of past orders
 *   - Categories of recently viewed products (from session)
 *
 * Falls back to most-viewed in catalog if user has no history.
 */
function reco_you_may_like(?int $userId, int $limit = 6): array {
    // Get the user's preferred categories
    $categoryIds = [];
    if ($userId) {
        $rows = db_all(
            "SELECT DISTINCT p.category_id
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             JOIN products p ON p.id = oi.product_id
             WHERE o.user_id = ?
             ORDER BY o.placed_at DESC
             LIMIT 20",
            [$userId]
        );
        $categoryIds = array_column($rows, 'category_id');
    }

    // Add recently viewed product categories
    $recentIds = $_SESSION['recently_viewed'] ?? [];
    if (!empty($recentIds)) {
        $place = implode(',', array_fill(0, count($recentIds), '?'));
        $rows = db_all(
            "SELECT DISTINCT category_id FROM products WHERE id IN ($place)",
            $recentIds
        );
        $categoryIds = array_merge($categoryIds, array_column($rows, 'category_id'));
    }
    $categoryIds = array_unique(array_filter($categoryIds));

    // Build query — prefer products in user's categories, exclude already-purchased
    $excludeIds = [];
    if ($userId) {
        $excludeIds = array_column(db_all(
            "SELECT DISTINCT oi.product_id FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE o.user_id = ?",
            [$userId]
        ), 'product_id');
    }

    if (empty($categoryIds)) {
        // Cold start: just show most viewed products
        $sql = "SELECT p.id, p.name, p.slug, p.base_price,
                       c.slug AS category_slug, ut.code AS unit_code,
                       COALESCE(p.decay_exponent_override, c.decay_exponent, 1.0) AS decay_exponent,
                       (SELECT image_path FROM product_images pi
                        WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) AS primary_image,
                       (SELECT MIN(sb.expiry_date) FROM stock_batches sb
                        WHERE sb.product_id = p.id AND sb.status = 'ACTIVE'
                          AND sb.quantity_remaining > 0 AND sb.expiry_date > CURDATE()) AS expiry_date,
                       (SELECT sb.received_date FROM stock_batches sb
                        WHERE sb.product_id = p.id AND sb.status = 'ACTIVE'
                          AND sb.quantity_remaining > 0 AND sb.expiry_date > CURDATE()
                        ORDER BY sb.expiry_date ASC LIMIT 1) AS received_date
                FROM products p
                JOIN categories c ON c.id = p.category_id
                JOIN unit_types ut ON ut.id = p.unit_type_id
                WHERE p.is_active = 1 AND p.deleted_at IS NULL
                  AND EXISTS (SELECT 1 FROM stock_batches sb
                              WHERE sb.product_id = p.id AND sb.status = 'ACTIVE'
                                AND sb.quantity_remaining > 0 AND sb.expiry_date > CURDATE())
                ORDER BY p.view_count DESC
                LIMIT $limit";
        $rows = db_all($sql);
    } else {
        $catPlace = implode(',', array_fill(0, count($categoryIds), '?'));
        $excludeClause = '';
        $args = $categoryIds;

        if (!empty($excludeIds)) {
            $excPlace = implode(',', array_fill(0, count($excludeIds), '?'));
            $excludeClause = " AND p.id NOT IN ($excPlace) ";
            $args = array_merge($args, $excludeIds);
        }

        $sql = "SELECT p.id, p.name, p.slug, p.base_price,
                       c.slug AS category_slug, ut.code AS unit_code,
                       COALESCE(p.decay_exponent_override, c.decay_exponent, 1.0) AS decay_exponent,
                       (SELECT image_path FROM product_images pi
                        WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) AS primary_image,
                       (SELECT MIN(sb.expiry_date) FROM stock_batches sb
                        WHERE sb.product_id = p.id AND sb.status = 'ACTIVE'
                          AND sb.quantity_remaining > 0 AND sb.expiry_date > CURDATE()) AS expiry_date,
                       (SELECT sb.received_date FROM stock_batches sb
                        WHERE sb.product_id = p.id AND sb.status = 'ACTIVE'
                          AND sb.quantity_remaining > 0 AND sb.expiry_date > CURDATE()
                        ORDER BY sb.expiry_date ASC LIMIT 1) AS received_date
                FROM products p
                JOIN categories c ON c.id = p.category_id
                JOIN unit_types ut ON ut.id = p.unit_type_id
                WHERE p.is_active = 1 AND p.deleted_at IS NULL
                  AND p.category_id IN ($catPlace)
                  $excludeClause
                  AND EXISTS (SELECT 1 FROM stock_batches sb
                              WHERE sb.product_id = p.id AND sb.status = 'ACTIVE'
                                AND sb.quantity_remaining > 0 AND sb.expiry_date > CURDATE())
                ORDER BY p.view_count DESC, RAND()
                LIMIT $limit";
        $rows = db_all($sql, $args);
    }

    return array_map('decorate_with_freshness', $rows);
}

/**
 * Popular This Week — top sellers based on actual order volume in the last 7 days.
 *
 * Counts units sold in completed/in-progress orders (not just placed but might cancel).
 * Falls back to all-time view_count if last 7 days has no orders.
 */
function reco_popular_this_week(int $limit = 6): array {
    $rows = db_all(
        "SELECT p.id, p.name, p.slug, p.base_price,
                c.slug AS category_slug, ut.code AS unit_code,
                COALESCE(p.decay_exponent_override, c.decay_exponent, 1.0) AS decay_exponent,
                (SELECT image_path FROM product_images pi
                 WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) AS primary_image,
                SUM(oi.quantity) AS units_sold_7d,
                (SELECT MIN(sb.expiry_date) FROM stock_batches sb
                 WHERE sb.product_id = p.id AND sb.status = 'ACTIVE'
                   AND sb.quantity_remaining > 0 AND sb.expiry_date > CURDATE()) AS expiry_date,
                (SELECT sb.received_date FROM stock_batches sb
                 WHERE sb.product_id = p.id AND sb.status = 'ACTIVE'
                   AND sb.quantity_remaining > 0 AND sb.expiry_date > CURDATE()
                 ORDER BY sb.expiry_date ASC LIMIT 1) AS received_date
         FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         JOIN products p ON p.id = oi.product_id
         JOIN categories c ON c.id = p.category_id
         JOIN unit_types ut ON ut.id = p.unit_type_id
         WHERE o.placed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
           AND o.status IN ('PLACED','PROCESSING','QUALITY_CHECK','PACKED','OUT_FOR_DELIVERY','DELIVERED')
           AND p.is_active = 1 AND p.deleted_at IS NULL
           AND EXISTS (SELECT 1 FROM stock_batches sb
                       WHERE sb.product_id = p.id AND sb.status = 'ACTIVE'
                         AND sb.quantity_remaining > 0 AND sb.expiry_date > CURDATE())
         GROUP BY p.id, p.name, p.slug, p.base_price, c.slug,
                  ut.code, p.decay_exponent_override, c.decay_exponent
         ORDER BY units_sold_7d DESC, p.view_count DESC
         LIMIT $limit",
        []
    );

    // Fallback: if no orders in last 7 days, show most-viewed products
    if (empty($rows)) {
        $rows = db_all(
            "SELECT p.id, p.name, p.slug, p.base_price,
                    c.slug AS category_slug, ut.code AS unit_code,
                    COALESCE(p.decay_exponent_override, c.decay_exponent, 1.0) AS decay_exponent,
                    (SELECT image_path FROM product_images pi
                     WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) AS primary_image,
                    p.view_count AS units_sold_7d,
                    (SELECT MIN(sb.expiry_date) FROM stock_batches sb
                     WHERE sb.product_id = p.id AND sb.status = 'ACTIVE'
                       AND sb.quantity_remaining > 0 AND sb.expiry_date > CURDATE()) AS expiry_date,
                    (SELECT sb.received_date FROM stock_batches sb
                     WHERE sb.product_id = p.id AND sb.status = 'ACTIVE'
                       AND sb.quantity_remaining > 0 AND sb.expiry_date > CURDATE()
                     ORDER BY sb.expiry_date ASC LIMIT 1) AS received_date
             FROM products p
             JOIN categories c ON c.id = p.category_id
             JOIN unit_types ut ON ut.id = p.unit_type_id
             WHERE p.is_active = 1 AND p.deleted_at IS NULL
               AND EXISTS (SELECT 1 FROM stock_batches sb
                           WHERE sb.product_id = p.id AND sb.status = 'ACTIVE'
                             AND sb.quantity_remaining > 0 AND sb.expiry_date > CURDATE())
             ORDER BY p.view_count DESC
             LIMIT $limit"
        );
    }

    return array_map('decorate_with_freshness', $rows);
}

/**
 * Render a reusable horizontal product card row.
 * Used for FBT, May Like, Popular sections.
 */
/**
 * Render a recommendation section.
 *
 * @param string $layout 'grid' (default, wraps onto multiple rows) or
 *                        'carousel' (single swipeable row with arrows).
 */
function reco_render_section(string $title, string $emoji, array $products, string $subtitle = '', string $layout = 'grid'): string {
    if (empty($products)) return '';

    // Build the product cards once — shared by both layouts.
    ob_start();
    foreach ($products as $p): ?>
        <a href="<?= url('/shop/product.php?slug=' . urlencode($p['slug'])) ?>"
           class="product-card-v2 <?= ($p['freshness_level'] ?? '') === 'LAST_CHANCE' ? 'last-chance' : '' ?>"
           style="color: inherit;">
            <div class="product-card-image">
                <?php if (!empty($p['primary_image'])): ?>
                    <img src="<?= upload_url($p['primary_image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy"
                         style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <span class="img-fallback"><?= icon('leaf', 56) ?></span>
                <?php endif; ?>
                <?= freshness_ring_html($p) ?>
                <?php if (!empty($p['is_discounted'])): ?>
                    <span class="discount-tag">-<?= (int) $p['discount_pct'] ?>%</span>
                <?php endif; ?>
            </div>
            <div class="product-card-body">
                <div class="product-card-name"><?= htmlspecialchars($p['name']) ?></div>
                <div class="product-card-pricing">
                    <span class="price-final"><?= format_myr($p['final_price'] ?? $p['base_price']) ?></span>
                    <?php if (!empty($p['is_discounted'])): ?>
                        <span class="price-base-strike"><?= format_myr($p['base_price']) ?></span>
                    <?php endif; ?>
                    <span style="color: var(--color-text-muted); font-size: 0.8125rem;">
                        / <?= htmlspecialchars($p['unit_code']) ?>
                    </span>
                </div>
            </div>
        </a>
    <?php endforeach;
    $cards = ob_get_clean();

    ob_start();
    ?>
    <section style="margin: var(--space-8) 0;">
        <div style="display: flex; align-items: baseline; justify-content: space-between; margin-bottom: var(--space-4); flex-wrap: wrap;">
            <div>
                <h2 style="margin: 0; font-size: 1.375rem;">
                    <?= $emoji ?> <?= htmlspecialchars($title) ?>
                </h2>
                <?php if ($subtitle): ?>
                    <p style="margin: 4px 0 0; color: var(--color-text-muted); font-size: 0.875rem;">
                        <?= htmlspecialchars($subtitle) ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($layout === 'carousel'): ?>
            <div class="reco-carousel-wrap">
                <button class="reco-arrow" data-reco-dir="prev" type="button" aria-label="Scroll left" hidden>&lsaquo;</button>
                <div class="reco-carousel"><?= $cards ?></div>
                <button class="reco-arrow" data-reco-dir="next" type="button" aria-label="Scroll right">&rsaquo;</button>
            </div>
        <?php else: ?>
            <div class="reco-grid"><?= $cards ?></div>
        <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}
