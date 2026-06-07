<?php
/**
 * Wishlist helpers — favorites for registered customers.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helpers.php';

/** Get (or create) the user's wishlist. */
function wishlist_get_or_create(int $userId): array
{
    $w = db_one('SELECT * FROM wishlists WHERE user_id = ?', [$userId]);
    if ($w) return $w;
    db_run('INSERT INTO wishlists (user_id) VALUES (?)', [$userId]);
    return db_one('SELECT * FROM wishlists WHERE id = ?', [db_last_id()]);
}

function wishlist_has(int $userId, int $productId): bool
{
    $w = wishlist_get_or_create($userId);
    return (bool) db_scalar(
        'SELECT id FROM wishlist_items WHERE wishlist_id = ? AND product_id = ?',
        [$w['id'], $productId]
    );
}

function wishlist_toggle(int $userId, int $productId): bool
{
    $w = wishlist_get_or_create($userId);
    if (wishlist_has($userId, $productId)) {
        db_run('DELETE FROM wishlist_items WHERE wishlist_id = ? AND product_id = ?',
               [$w['id'], $productId]);
        return false;
    }
    db_run('INSERT INTO wishlist_items (wishlist_id, product_id) VALUES (?, ?)',
           [$w['id'], $productId]);
    return true;
}

function wishlist_items(int $userId): array
{
    $w = wishlist_get_or_create($userId);
    return db_all(
        "SELECT p.id, p.name, p.slug, p.base_price, p.origin,
                c.slug AS category_slug, c.name AS category_name,
                ut.code AS unit_code,
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
                 ORDER BY sb.expiry_date ASC LIMIT 1) AS received_date,
                wi.added_at
         FROM wishlist_items wi
         JOIN products p ON p.id = wi.product_id
         JOIN categories c ON c.id = p.category_id
         JOIN unit_types ut ON ut.id = p.unit_type_id
         WHERE wi.wishlist_id = ?
           AND p.is_active = 1 AND p.deleted_at IS NULL
         ORDER BY wi.added_at DESC",
        [$w['id']]
    );
}

function wishlist_count(int $userId): int
{
    return (int) db_scalar(
        'SELECT COUNT(*) FROM wishlist_items wi
         JOIN wishlists w ON w.id = wi.wishlist_id
         WHERE w.user_id = ?',
        [$userId]
    );
}
