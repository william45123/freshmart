<?php
/**
 * Customer order history.
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth_helpers.php';

require_login();

// Single order view via ?id=N
$orderId = (int) input('id', 0);

// Handle reorder action
if (is_post() && input('action') === 'reorder' && csrf_verify()) {
    $oid = (int) input('order_id');
    $owned = db_scalar('SELECT id FROM orders WHERE id = ? AND user_id = ?', [$oid, auth_id()]);
    if ($owned) {
        require_once __DIR__ . '/../../includes/cart_helpers.php';
        $items = db_all('SELECT product_id, quantity FROM order_items WHERE order_id = ?', [$oid]);
        $added = 0; $errs = [];
        foreach ($items as $it) {
            try {
                cart_add((int) $it['product_id'], (float) $it['quantity']);
                $added++;
            } catch (Throwable $e) {
                $errs[] = $it['product_id'] . ': ' . $e->getMessage();
            }
        }
        if ($added > 0) flash_set('success', "Re-added $added items to your cart.");
        foreach ($errs as $err) flash_set('error', $err);
        redirect('/shop/cart.php');
    }
}

if ($orderId > 0) {
    $order = db_one(
        "SELECT o.*, s.tracking_number, s.estimated_delivery, s.shipped_at, s.delivered_at,
                pm.payment_method, pm.status AS payment_status, pm.transaction_ref,
                a.recipient_name, a.line1, a.city, a.state, a.postcode
         FROM orders o
         LEFT JOIN shipments s ON s.order_id = o.id
         LEFT JOIN payments pm ON pm.order_id = o.id
         LEFT JOIN addresses a ON a.id = o.shipping_address_id
         WHERE o.id = ? AND o.user_id = ?",
        [$orderId, auth_id()]
    );

    if (!$order) {
        flash_set('error', 'Order not found.');
        redirect('/shop/orders.php');
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

    $history = db_all(
        "SELECT oh.*, p.full_name AS changed_by_name
         FROM order_history oh
         LEFT JOIN profiles p ON p.user_id = oh.changed_by
         WHERE oh.order_id = ?
         ORDER BY oh.created_at ASC",
        [$orderId]
    );

    $pageTitle = 'Order ' . $order['order_number'] . ' — FreshMart';
    require_once __DIR__ . '/../../includes/header.php';
    ?>

    <section class="container" style="padding: var(--space-6) 0 var(--space-12); max-width: 800px;">

        <a href="<?= url('/shop/orders.php') ?>" style="color: var(--color-text-muted); font-size: 0.875rem;">← All orders</a>

        <div style="display: flex; align-items: baseline; gap: var(--space-3); margin: var(--space-3) 0 var(--space-2); justify-content: space-between;">
            <div style="display: flex; align-items: baseline; gap: var(--space-3);">
                <h1 style="margin: 0;">Order <?= e($order['order_number']) ?></h1>
                <span class="status-pill status-<?= strtolower($order['status']) === 'delivered' ? 'active' : 'pending' ?>">
                    <?= e(str_replace('_', ' ', $order['status'])) ?>
                </span>
            </div>
            <form method="post" style="display: inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reorder">
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                <button type="submit" class="btn btn-secondary btn-sm">🔄 Reorder</button>
            </form>
        </div>
        <p style="color: var(--color-text-muted);">
            Placed <?= format_datetime($order['placed_at']) ?>
            <?php if (!empty($order['preferred_delivery_date'])): ?>
                · Preferred delivery: <strong><?= format_date($order['preferred_delivery_date']) ?></strong>
            <?php endif; ?>
        </p>

        <?php
        // Visual 6-step progress
        $steps    = ['PLACED','PROCESSING','QUALITY_CHECK','PACKED','OUT_FOR_DELIVERY','DELIVERED'];
        $labels   = ['Placed','Processing','Quality Check','Packed','Out for Delivery','Delivered'];
        $currentIdx = array_search($order['status'], $steps, true);
        if ($currentIdx === false) $currentIdx = -1;  // CANCELLED etc.
        ?>
        <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-5) var(--space-4); margin: var(--space-4) 0;">
            <div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 0; position: relative; padding: 0 8px;">
                <?php foreach ($steps as $i => $step):
                    $done    = $i <= $currentIdx;
                    $current = $i === $currentIdx;
                ?>
                <div style="text-align: center; position: relative; z-index: 1;">
                    <div style="width: 34px; height: 34px; margin: 0 auto var(--space-2); border-radius: 50%;
                                display: grid; place-items: center;
                                background: <?= $done ? 'var(--color-primary)' : 'var(--color-bg)' ?>;
                                color: <?= $done ? 'white' : 'var(--color-text-muted)' ?>;
                                border: 2px solid <?= $done ? 'var(--color-primary)' : 'var(--color-border)' ?>;
                                font-weight: 700; font-size: 0.875rem;">
                        <?= $done ? '✓' : $i + 1 ?>
                    </div>
                    <div style="font-size: 0.75rem; color: <?= $current ? 'var(--color-text)' : 'var(--color-text-muted)' ?>;
                                font-weight: <?= $current ? '600' : '400' ?>;">
                        <?= e($labels[$i]) ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <div style="position: absolute; top: 17px; left: calc(8.33% + 17px); right: calc(8.33% + 17px); height: 2px; background: var(--color-border); z-index: 0;"></div>
                <?php if ($currentIdx > 0): ?>
                    <div style="position: absolute; top: 17px; left: calc(8.33% + 17px); width: calc((<?= $currentIdx ?> / 5) * (100% - 16.66% - 34px)); height: 2px; background: var(--color-primary); z-index: 0;"></div>
                <?php endif; ?>
            </div>
        </div>

        <h3 style="font-size: 1.125rem; margin-top: var(--space-6);">Order timeline</h3>
        <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-4); margin-bottom: var(--space-4);">
            <?php foreach ($history as $i => $h): ?>
                <div style="display: flex; gap: var(--space-3); padding: var(--space-2) 0; <?= $i < count($history) - 1 ? 'border-bottom: 1px solid var(--color-border);' : '' ?>">
                    <div style="font-size: 0.8125rem; color: var(--color-text-muted); white-space: nowrap; min-width: 140px;">
                        <?= format_datetime($h['created_at'], 'd M, H:i') ?>
                    </div>
                    <div>
                        <strong><?= e($h['new_status']) ?></strong>
                        <?php if (!empty($h['notes'])): ?>
                            <div style="font-size: 0.875rem; color: var(--color-text-muted);"><?= e($h['notes']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <h3 style="font-size: 1.125rem;">Items</h3>
        <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-4); margin-bottom: var(--space-4);">
            <?php foreach ($items as $item): ?>
                <div style="display: grid; grid-template-columns: 60px 1fr auto; gap: var(--space-3); padding: var(--space-3) 0; align-items: center; border-bottom: 1px solid var(--color-border);">
                    <div style="aspect-ratio: 1; background: var(--color-bg); border-radius: var(--radius); overflow: hidden; display: grid; place-items: center;">
                        <?php if (!empty($item['primary_image'])): ?>
                            <img src="<?= upload_url($item['primary_image']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                            🥬
                        <?php endif; ?>
                    </div>
                    <div>
                        <a href="<?= url('/shop/product.php?slug=' . urlencode($item['slug'])) ?>"
                           style="color: var(--color-text); font-weight: 600;">
                            <?= e($item['product_name']) ?>
                        </a>
                        <div style="font-size: 0.8125rem; color: var(--color-text-muted);">
                            <?= number_format((float) $item['quantity'], 2) ?> ×
                            <?= format_myr($item['unit_price']) ?>
                            · Batch <code><?= e($item['batch_code']) ?></code> ·
                            <?= e($item['freshness_at_order']) ?>
                        </div>
                    </div>
                    <div style="font-weight: 600;"><?= format_myr($item['subtotal']) ?></div>
                </div>
            <?php endforeach; ?>

            <div style="margin-top: var(--space-3); display: flex; flex-direction: column; gap: var(--space-1);">
                <div style="display: flex; justify-content: space-between; color: var(--color-text-muted);">
                    <span>Subtotal</span><span><?= format_myr($order['subtotal']) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; color: var(--color-text-muted);">
                    <span>Shipping</span><span><?= $order['shipping_fee'] == 0 ? 'FREE' : format_myr($order['shipping_fee']) ?></span>
                </div>
                <?php if ((float) $order['discount_amount'] > 0): ?>
                <div style="display: flex; justify-content: space-between; color: var(--color-accent);">
                    <span>Discount</span><span>−<?= format_myr($order['discount_amount']) ?></span>
                </div>
                <?php endif; ?>
                <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 1.125rem; padding-top: var(--space-2); border-top: 1px solid var(--color-border);">
                    <span>Total</span><span><?= format_myr($order['total']) ?></span>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
            <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-4);">
                <h4 style="margin-top: 0; font-size: 0.9375rem;">📍 Delivery</h4>
                <p style="margin: 0; font-size: 0.875rem; color: var(--color-text-muted);">
                    <?= e($order['recipient_name']) ?><br>
                    <?= e($order['line1']) ?><br>
                    <?= e($order['city']) ?>, <?= e($order['state']) ?> <?= e($order['postcode']) ?>
                </p>
                <?php if (!empty($order['tracking_number'])): ?>
                    <p style="margin: var(--space-2) 0 0; font-size: 0.8125rem;">
                        Tracking: <code><?= e($order['tracking_number']) ?></code>
                    </p>
                <?php endif; ?>
            </div>
            <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-4);">
                <h4 style="margin-top: 0; font-size: 0.9375rem;">💳 Payment</h4>
                <p style="margin: 0; font-size: 0.875rem; color: var(--color-text-muted);">
                    <?= e($order['payment_method']) ?> · <?= e($order['payment_status']) ?><br>
                    Ref: <code><?= e($order['transaction_ref']) ?></code>
                </p>
            </div>
        </div>
    </section>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    <?php
    return;
}

// ---- LIST VIEW ----
$orders = db_all(
    "SELECT o.*,
            (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count
     FROM orders o
     WHERE o.user_id = ?
     ORDER BY o.placed_at DESC",
    [auth_id()]
);

$pageTitle = 'My Orders — FreshMart';
require_once __DIR__ . '/../../includes/header.php';
?>

<section class="container" style="padding: var(--space-6) 0 var(--space-12);">
    <h1>My orders</h1>

    <?php if (empty($orders)): ?>
        <div class="empty-state" style="margin-top: var(--space-6);">
            <p style="font-size: 1.0625rem;">📦 No orders yet</p>
            <a href="<?= url('/shop/browse.php') ?>" class="btn btn-primary" style="margin-top: var(--space-3);">Browse products</a>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: var(--space-3); margin-top: var(--space-4);">
            <?php foreach ($orders as $o): ?>
                <a href="<?= url('/shop/orders.php?id=' . $o['id']) ?>"
                   style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg);
                          padding: var(--space-4) var(--space-5); display: grid;
                          grid-template-columns: 1fr 120px 100px 100px;
                          gap: var(--space-4); align-items: center; color: inherit;">
                    <div>
                        <div style="font-weight: 600;"><?= e($o['order_number']) ?></div>
                        <div style="font-size: 0.875rem; color: var(--color-text-muted);">
                            <?= format_datetime($o['placed_at'], 'd M Y, H:i') ?>
                        </div>
                    </div>
                    <div style="color: var(--color-text-muted); font-size: 0.875rem;">
                        <?= $o['item_count'] ?> item<?= $o['item_count'] === 1 ? '' : 's' ?>
                    </div>
                    <div>
                        <span class="status-pill status-<?= strtolower($o['status']) === 'delivered' ? 'active' : 'pending' ?>">
                            <?= e($o['status']) ?>
                        </span>
                    </div>
                    <div style="text-align: right; font-weight: 700;">
                        <?= format_myr($o['total']) ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
