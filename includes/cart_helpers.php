<?php
/**
 * Cart helpers — supports both authenticated users and guest sessions.
 * Guest carts use a session-based guest_session_id cookie.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/freshness.php';
require_once __DIR__ . '/fefo.php';

/**
 * Get (or create) the current cart for the active user/guest session.
 */
function cart_get_or_create(): array
{
    auth_init();

    if (auth_check()) {
        $userId = auth_id();
        $cart = db_one('SELECT * FROM carts WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$userId]);
        if ($cart) return $cart;

        db_run('INSERT INTO carts (user_id) VALUES (?)', [$userId]);
        return db_one('SELECT * FROM carts WHERE id = ?', [db_last_id()]);
    }

    // Guest cart
    if (empty($_SESSION['guest_session_id'])) {
        $_SESSION['guest_session_id'] = random_token(16);
    }
    $guest = $_SESSION['guest_session_id'];

    $cart = db_one('SELECT * FROM carts WHERE guest_session_id = ? AND expires_at > NOW() LIMIT 1', [$guest]);
    if ($cart) return $cart;

    db_run(
        'INSERT INTO carts (guest_session_id, expires_at) VALUES (?, DATE_ADD(NOW(), INTERVAL ? HOUR))',
        [$guest, GUEST_CART_HOURS]
    );
    return db_one('SELECT * FROM carts WHERE id = ?', [db_last_id()]);
}

/**
 * Add product to cart (or merge with existing line item).
 */
function cart_add(int $productId, float $quantity): void
{
    if ($quantity <= 0) throw new InvalidArgumentException('Quantity must be positive.');

    $product = db_one(
        'SELECT id, base_price, min_order_qty FROM products
         WHERE id = ? AND is_active = 1 AND deleted_at IS NULL',
        [$productId]
    );
    if (!$product) throw new RuntimeException('Product not found.');

    if ($quantity < (float) $product['min_order_qty']) {
        throw new RuntimeException("Minimum order quantity is {$product['min_order_qty']}.");
    }

    // Check sufficient stock
    $available = fefo_total_stock($productId);
    if ($available < $quantity) {
        throw new RuntimeException("Only {$available} units available.");
    }

    $cart = cart_get_or_create();

    // Compute effective price live from the earliest-expiry batch's freshness.
    // decorate_with_freshness() auto-resolves the retailer's discount settings,
    // so the price always reflects current discounts (no cron dependency).
    $batch = fefo_display_batch($productId);
    $effectivePrice = (float) $product['base_price'];
    if ($batch) {
        $decorated = decorate_with_freshness([
            'id'            => $productId,
            'base_price'    => (float) $product['base_price'],
            'received_date' => $batch['received_date'],
            'expiry_date'   => $batch['expiry_date'],
        ]);
        $effectivePrice = (float) ($decorated['final_price'] ?? $product['base_price']);
    }

    $existing = db_one(
        'SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?',
        [$cart['id'], $productId]
    );
    if ($existing) {
        $newQty = (float) $existing['quantity'] + $quantity;
        if ($available < $newQty) {
            throw new RuntimeException("Only {$available} units available (you already have "
                . number_format((float) $existing['quantity'], 2) . " in cart).");
        }
        db_run(
            'UPDATE cart_items SET quantity = ?, price_snapshot = ? WHERE id = ?',
            [$newQty, $effectivePrice, $existing['id']]
        );
    } else {
        db_run(
            'INSERT INTO cart_items (cart_id, product_id, quantity, price_snapshot) VALUES (?, ?, ?, ?)',
            [$cart['id'], $productId, $quantity, $effectivePrice]
        );
    }
}

function cart_remove(int $productId): void
{
    $cart = cart_get_or_create();
    db_run('DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?', [$cart['id'], $productId]);
}

function cart_update_quantity(int $productId, float $quantity): void
{
    if ($quantity <= 0) { cart_remove($productId); return; }
    $cart = cart_get_or_create();
    db_run(
        'UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND product_id = ?',
        [$quantity, $cart['id'], $productId]
    );
}

/**
 * Get all line items in the current cart, with live freshness re-computed.
 */
function cart_items(): array
{
    $cart = cart_get_or_create();
    return db_all(
        "SELECT ci.*, p.name, p.slug, p.base_price, p.origin,
                ut.code AS unit_code,
                c.slug AS category_slug,
                COALESCE(p.decay_exponent_override, c.decay_exponent, 1.00) AS decay_exponent,
                (SELECT image_path FROM product_images pi
                 WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) AS primary_image
         FROM cart_items ci
         JOIN products p ON p.id = ci.product_id
         JOIN categories c ON c.id = p.category_id
         JOIN unit_types ut ON ut.id = p.unit_type_id
         WHERE ci.cart_id = ?
         ORDER BY ci.added_at DESC",
        [$cart['id']]
    );
}

/**
 * Compute cart totals.
 */
function cart_totals(): array
{
    $items    = cart_items();
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += (float) $item['quantity'] * (float) $item['price_snapshot'];
    }
    $subtotal = round($subtotal, 2);

    $shipping = $subtotal >= FREE_SHIPPING_THRESHOLD ? 0.0 : DEFAULT_SHIPPING_FEE;
    $total    = round($subtotal + $shipping, 2);

    return [
        'items'    => $items,
        'count'    => count($items),
        'subtotal' => $subtotal,
        'shipping' => $shipping,
        'total'    => $total,
    ];
}

/**
 * Clear the current cart (after successful checkout).
 */
function cart_clear(): void
{
    $cart = cart_get_or_create();
    db_run('DELETE FROM cart_items WHERE cart_id = ?', [$cart['id']]);
}
