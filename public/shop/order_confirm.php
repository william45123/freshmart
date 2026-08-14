<?php
/**
 * Order confirmation page after successful checkout.
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth_helpers.php';

require_login();

$orderId = (int) input('id', 0);
$order = db_one(
    "SELECT o.*, s.tracking_number, s.estimated_delivery,
            pm.payment_method, pm.transaction_ref,
            a.recipient_name, a.line1, a.city, a.state, a.postcode
     FROM orders o
     LEFT JOIN shipments s ON s.order_id = o.id
     LEFT JOIN payments pm ON pm.order_id = o.id
     LEFT JOIN addresses a ON a.id = o.shipping_address_id
     WHERE o.id = ? AND o.user_id = ?",
    [$orderId, auth_id()]
);

if (!$order) {
    http_response_code(404);
    flash_set('error', 'Order not found.');
    redirect('/');
}

$items = db_all(
    "SELECT oi.*, p.slug,
            (SELECT image_path FROM product_images pi
             WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) AS primary_image,
            sb.batch_code
     FROM order_items oi
     JOIN products p ON p.id = oi.product_id
     LEFT JOIN stock_batches sb ON sb.id = oi.stock_batch_id
     WHERE oi.order_id = ?",
    [$orderId]
);

$pageTitle = 'Order Confirmed — FreshMart';
require_once __DIR__ . '/../../includes/header.php';
?>

<section class="container u-py-8 u-maxw-720">

    <div class="u-ta-c u-mb-8">
        <div class="empty-ico u-mb-3 u-fg-primary"><?= icon('sparkles', 56) ?></div>
        <h1 class="u-mb-2">Order placed!</h1>
        <p class="u-muted u-t-17">
            Order <strong><?= e($order['order_number']) ?></strong> is being processed.
        </p>
    </div>

    <div class="panel u-p-6 u-mb-4">
        <h3 class="u-mt-0">Order Items</h3>
        <?php foreach ($items as $item): ?>
            <div class="u-grid u-cols-media u-gap-3 u-py-3 u-bb u-ai-center">
                <div class="u-square u-bg-page u-r u-ovh u-grid u-place-center">
                    <?php if (!empty($item['primary_image'])): ?>
                        <img src="<?= upload_url($item['primary_image']) ?>" alt="<?= attr($item['name']) ?>" loading="lazy" class="media-fill">
                    <?php else: ?>
                        🥬
                    <?php endif; ?>
                </div>
                <div>
                    <div class="u-fw-600"><?= e($item['product_name']) ?></div>
                    <div class="u-t-13 u-muted">
                        Qty: <?= number_format((float) $item['quantity'], 2) ?> ·
                        @ <?= format_myr($item['unit_price']) ?> ·
                        from batch <code><?= e($item['batch_code']) ?></code> ·
                        <?= e($item['freshness_at_order']) ?>
                    </div>
                </div>
                <div class="u-fw-600"><?= format_myr($item['subtotal']) ?></div>
            </div>
        <?php endforeach; ?>

        <div class="u-mt-4 u-flex u-col u-gap-1">
            <div class="u-flex u-jc-between u-muted">
                <span>Subtotal</span><span><?= format_myr($order['subtotal']) ?></span>
            </div>
            <div class="u-flex u-jc-between u-muted">
                <span>Shipping</span><span><?= $order['shipping_fee'] == 0 ? 'FREE' : format_myr($order['shipping_fee']) ?></span>
            </div>
            <div class="u-flex u-jc-between u-fw-700 u-t-18 u-pt-2 u-bt">
                <span>Total Paid</span><span><?= format_myr($order['total']) ?></span>
            </div>
        </div>
    </div>

    <div class="u-grid u-cols-2 u-gap-4 u-mb-6">
        <div class="panel u-p-5">
            <h3 class="u-mt-0 u-t-16">📍 Shipping to</h3>
            <p class="u-m-0 u-t-15 u-muted">
                <strong class="u-ink"><?= e($order['recipient_name']) ?></strong><br>
                <?= e($order['line1']) ?><br>
                <?= e($order['city']) ?>, <?= e($order['state']) ?> <?= e($order['postcode']) ?>
            </p>
        </div>
        <div class="panel u-p-5">
            <h3 class="u-mt-0 u-t-16">📦 Tracking</h3>
            <p class="u-m-0 u-t-15 u-muted">
                <strong class="u-ink"><?= e($order['tracking_number']) ?></strong><br>
                <?= e($order['payment_method']) ?> · <?= e($order['transaction_ref']) ?><br>
                <small>Estimated delivery: <?= format_date($order['estimated_delivery']) ?></small>
            </p>
        </div>
    </div>

    <div class="u-ta-c">
        <a href="<?= url('/shop/orders.php') ?>" class="btn btn-secondary">View all orders</a>
        <a href="<?= url('/shop/browse.php') ?>" class="btn btn-primary">Continue shopping</a>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
