<?php
/**
 * Recently Viewed Products (R-APP-22).
 *
 * Stores last 10 product IDs in the session for the current user (or guest).
 * No database table needed — session storage is enough for this feature.
 */

require_once __DIR__ . '/helpers.php';

const RECENTLY_VIEWED_MAX = 10;

/** Record a product view (call from product detail page). */
function recently_viewed_track(int $productId): void
{
    _ensure_session();
    $list = $_SESSION['_recently_viewed'] ?? [];

    // Remove if already present (move-to-front)
    $list = array_values(array_filter($list, fn($id) => $id !== $productId));

    // Prepend
    array_unshift($list, $productId);

    // Cap to N
    if (count($list) > RECENTLY_VIEWED_MAX) {
        $list = array_slice($list, 0, RECENTLY_VIEWED_MAX);
    }

    $_SESSION['_recently_viewed'] = $list;
}

/** Get the recently-viewed product IDs (in order). */
function recently_viewed_ids(?int $excludeProductId = null): array
{
    _ensure_session();
    $list = $_SESSION['_recently_viewed'] ?? [];
    if ($excludeProductId !== null) {
        $list = array_values(array_filter($list, fn($id) => $id !== $excludeProductId));
    }
    return $list;
}

/** Get full product data for recently-viewed items, in viewing order. */
function recently_viewed_products(?int $excludeProductId = null, int $limit = 6): array
{
    require_once __DIR__ . '/db.php';
    $ids = recently_viewed_ids($excludeProductId);
    if (empty($ids)) return [];

    $ids = array_slice($ids, 0, $limit);

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $rows = db_all(
        "SELECT p.id, p.name, p.slug, p.base_price,
                c.slug AS category_slug,
                COALESCE(p.decay_exponent_override, c.decay_exponent, 1.00) AS decay_exponent,
                (SELECT image_path FROM product_images pi
                 WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) AS primary_image,
                (SELECT sb.expiry_date FROM stock_batches sb
                 WHERE sb.product_id = p.id AND sb.status = 'ACTIVE'
                   AND sb.quantity_remaining > 0 AND sb.expiry_date > CURDATE()
                 ORDER BY sb.expiry_date ASC LIMIT 1) AS expiry_date,
                (SELECT sb.received_date FROM stock_batches sb
                 WHERE sb.product_id = p.id AND sb.status = 'ACTIVE'
                   AND sb.quantity_remaining > 0 AND sb.expiry_date > CURDATE()
                 ORDER BY sb.expiry_date ASC LIMIT 1) AS received_date
         FROM products p
         JOIN categories c ON c.id = p.category_id
         WHERE p.id IN ($placeholders) AND p.is_active = 1 AND p.deleted_at IS NULL",
        $ids
    );

    // Re-sort by viewing order (SQL doesn't preserve IN list order)
    $byId = [];
    foreach ($rows as $r) $byId[$r['id']] = $r;

    $sorted = [];
    foreach ($ids as $id) {
        if (isset($byId[$id])) $sorted[] = $byId[$id];
    }
    return $sorted;
}
