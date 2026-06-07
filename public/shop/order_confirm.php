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

<section class="container" style="padding: var(--space-8) 0; max-width: 720px;">

    <div style="text-align: center; margin-bottom: var(--space-8);">
        <div style="font-size: 4rem; margin-bottom: var(--space-3);">🎉</div>
        <h1 style="margin-bottom: var(--space-2);">Order placed!</h1>
        <p style="color: var(--color-text-muted); font-size: 1.0625rem;">
            Order <strong><?= e($order['order_number']) ?></strong> is being processed.
        </p>
    </div>

    <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-6); margin-bottom: var(--space-4);">
        <h3 style="margin-top: 0;">Order Items</h3>
        <?php foreach ($items as $item): ?>
            <div style="display: grid; grid-template-columns: 60px 1fr auto; gap: var(--space-3); padding: var(--space-3) 0; border-bottom: 1px solid var(--color-border); align-items: center;">
                <div style="aspect-ratio: 1; background: var(--color-bg); border-radius: var(--radius); overflow: hidden; display: grid; place-items: center;">
                    <?php if (!empty($item['primary_image'])): ?>
                        <img src="<?= upload_url($item['primary_image']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        🥬
                    <?php endif; ?>
                </div>
                <div>
                    <div style="font-weight: 600;"><?= e($item['product_name']) ?></div>
                    <div style="font-size: 0.8125rem; color: var(--color-text-muted);">
                        Qty: <?= number_format((float) $item['quantity'], 2) ?> ·
                        @ <?= format_myr($item['unit_price']) ?> ·
                        from batch <code><?= e($item['batch_code']) ?></code> ·
                        <?= e($item['freshness_at_order']) ?>
                    </div>
                </div>
                <div style="font-weight: 600;"><?= format_myr($item['subtotal']) ?></div>
            </div>
        <?php endforeach; ?>

        <div style="margin-top: var(--space-4); display: flex; flex-direction: column; gap: var(--space-1);">
            <div style="display: flex; justify-content: space-between; color: var(--color-text-muted);">
                <span>Subtotal</span><span><?= format_myr($order['subtotal']) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; color: var(--color-text-muted);">
                <span>Shipping</span><span><?= $order['shipping_fee'] == 0 ? 'FREE' : format_myr($order['shipping_fee']) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 1.125rem; padding-top: var(--space-2); border-top: 1px solid var(--color-border);">
                <span>Total Paid</span><span><?= format_myr($order['total']) ?></span>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); margin-bottom: var(--space-6);">
        <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-5);">
            <h3 style="margin-top: 0; font-size: 1rem;">📍 Shipping to</h3>
            <p style="margin: 0; font-size: 0.9375rem; color: var(--color-text-muted);">
                <strong style="color: var(--color-text);"><?= e($order['recipient_name']) ?></strong><br>
                <?= e($order['line1']) ?><br>
                <?= e($order['city']) ?>, <?= e($order['state']) ?> <?= e($order['postcode']) ?>
            </p>
        </div>
        <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-5);">
            <h3 style="margin-top: 0; font-size: 1rem;">📦 Tracking</h3>
            <p style="margin: 0; font-size: 0.9375rem; color: var(--color-text-muted);">
                <strong style="color: var(--color-text);"><?= e($order['tracking_number']) ?></strong><br>
                <?= e($order['payment_method']) ?> · <?= e($order['transaction_ref']) ?><br>
                <small>Estimated delivery: <?= format_date($order['estimated_delivery']) ?></small>
            </p>
        </div>
    </div>

    <div style="text-align: center;">
        <a href="<?= url('/shop/orders.php') ?>" class="btn btn-secondary">View all orders</a>
        <a href="<?= url('/shop/browse.php') ?>" class="btn btn-primary">Continue shopping</a>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
