<?php
/**
 * Customer order history.
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth_helpers.php';
require_once __DIR__ . '/../../includes/wallet_helpers.php';

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

// Handle cancel order (before it ships)
if (is_post() && input('action') === 'cancel_order' && csrf_verify()) {
    $oid = (int) input('order_id');
    try {
        order_cancel($oid, auth_id(), trim((string) input('cancel_reason', '')) ?: null);
        flash_set('success', 'Order cancelled. Your payment has been refunded to your wallet.');
    } catch (Throwable $e) {
        flash_set('error', $e->getMessage());
    }
    redirect('/shop/orders.php?id=' . $oid);
}

// Handle refund request (full or partial) for a delivered order
if (is_post() && input('action') === 'request_refund' && csrf_verify()) {
    $oid   = (int) input('order_id');
    $scope = input('scope') === 'PARTIAL' ? 'PARTIAL' : 'FULL';
    $reason = (string) input('reason', 'NOT_FRESH');
    $detail = trim((string) input('detail', '')) ?: null;

    $items = [];
    if ($scope === 'PARTIAL') {
        $selected = $_POST['refund_item'] ?? [];   // [order_item_id => qty]
        if (is_array($selected)) {
            foreach ($selected as $oiId => $qty) {
                if ((float) $qty > 0) {
                    $items[] = ['order_item_id' => (int) $oiId, 'quantity' => (float) $qty];
                }
            }
        }
    }

    try {
        refund_create($oid, auth_id(), $scope, $reason, $detail, $items);
        flash_set('success', 'Refund request submitted. The seller will review it shortly.');
    } catch (Throwable $e) {
        flash_set('error', $e->getMessage());
    }
    redirect('/shop/orders.php?id=' . $oid);
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

    <section class="container u-page-head u-maxw-800">

        <a href="<?= url('/shop/orders.php') ?>" class="u-muted u-t-14">← All orders</a>

        <div class="u-flex u-ai-baseline u-gap-3 u-m-3-0-2 u-jc-between">
            <div class="u-flex u-ai-baseline u-gap-3">
                <h1 class="u-m-0">Order <?= e($order['order_number']) ?></h1>
                <span class="status-pill status-<?= strtolower($order['status']) === 'delivered' ? 'active' : 'pending' ?>">
                    <?= e(str_replace('_', ' ', $order['status'])) ?>
                </span>
            </div>
            <form method="post" class="u-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reorder">
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                <button type="submit" class="btn btn-secondary btn-sm"><?= icon('refresh', 16) ?> Reorder</button>
            </form>
        </div>
        <p class="u-muted">
            Placed <?= format_datetime($order['placed_at']) ?>
            <?php if (!empty($order['preferred_delivery_date'])): ?>
                · Preferred delivery: <strong><?= format_date($order['preferred_delivery_date']) ?></strong>
            <?php endif; ?>
        </p>

        <?php
        // Refund state for this order
        $openRefund = db_one(
            "SELECT * FROM refund_requests WHERE order_id = ? ORDER BY id DESC LIMIT 1",
            [$order['id']]
        );
        $hasOpenRefund = $openRefund && in_array($openRefund['status'], ['REQUESTED','ESCALATED'], true);
        $canCancel = order_can_cancel($order);
        $canRefund = ($order['status'] === 'DELIVERED') && !$hasOpenRefund
                     && (!$openRefund || $openRefund['status'] !== 'APPROVED');
        ?>

        <?php if ($openRefund): ?>
            <div class="refund-status-banner refund-status-<?= strtolower($openRefund['status']) ?>">
                <?php
                $statusText = [
                    'REQUESTED' => '⏳ Refund requested — the seller is reviewing your request.',
                    'ESCALATED' => '⏳ Refund escalated — an admin is making a final decision.',
                    'APPROVED'  => '✓ Refund approved — ' . format_myr((float)$openRefund['amount']) . ' credited to your wallet.',
                    'REJECTED'  => '✕ Refund declined.' . (!empty($openRefund['decision_note']) ? ' Reason: ' . e($openRefund['decision_note']) : ''),
                    'CANCELLED' => 'Refund request cancelled.',
                ][$openRefund['status']] ?? $openRefund['status'];
                echo $statusText;
                ?>
            </div>
        <?php endif; ?>

        <?php if ($canCancel || $canRefund): ?>
            <div class="order-actions-bar">
                <?php if ($canCancel): ?>
                    <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('cancelBox').style.display='block';this.style.display='none';">
                        Cancel order
                    </button>
                <?php endif; ?>
                <?php if ($canRefund): ?>
                    <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('refundBox').style.display='block';this.style.display='none';">
                        Request refund
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($canCancel): ?>
            <div id="cancelBox" class="action-box u-hidden">
                <h3 class="u-m-0-0-2 u-t-16">Cancel this order?</h3>
                <p class="u-muted u-t-14 u-m-0-0-3">
                    Your payment of <strong><?= format_myr((float)$order['total']) ?></strong> will be refunded to your wallet, and the items returned to stock. This can't be undone.
                </p>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cancel_order">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <input type="text" name="cancel_reason" placeholder="Reason (optional)" class="form-control u-mb-3">
                    <div class="u-flex u-gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">Confirm cancellation</button>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('cancelBox').style.display='none';">Keep order</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($canRefund): ?>
            <div id="refundBox" class="action-box u-hidden">
                <h3 class="u-m-0-0-3 u-t-16">Request a refund</h3>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="request_refund">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">

                    <label class="u-block u-t-14 u-fw-600 u-mb-2">What's the issue?</label>
                    <select name="reason" class="form-control u-mb-3">
                        <option value="NOT_FRESH">Item not fresh</option>
                        <option value="DAMAGED">Item damaged</option>
                        <option value="MISSING_ITEM">Item missing</option>
                        <option value="WRONG_ITEM">Wrong item received</option>
                        <option value="OTHER">Other</option>
                    </select>

                    <label class="u-block u-t-14 u-fw-600 u-mb-2">Refund type</label>
                    <div class="u-flex u-gap-4 u-mb-3">
                        <label class="u-t-144 u-pointer">
                            <input type="radio" name="scope" value="FULL" checked onclick="document.getElementById('partialItems').style.display='none';">
                            Whole order (<?= format_myr((float)$order['subtotal'] - (float)$order['discount_amount']) ?>)
                        </label>
                        <label class="u-t-144 u-pointer">
                            <input type="radio" name="scope" value="PARTIAL" onclick="document.getElementById('partialItems').style.display='block';">
                            Selected items
                        </label>
                    </div>

                    <div id="partialItems" class="u-hidden u-bg-warm u-r-12 u-p-3 u-mb-3">
                        <p class="u-t-128 u-muted u-m-0-0-2">Tick items and set the quantity to refund:</p>
                        <?php foreach ($items as $it): ?>
                            <div class="u-flex u-ai-center u-gap-2 u-mb-2">
                                <span class="u-flex-1 u-t-14"><?= e($it['product_name']) ?> <span class="u-sage">(<?= rtrim(rtrim(number_format((float)$it['quantity'],2), '0'), '.') ?> × <?= format_myr((float)$it['unit_price']) ?>)</span></span>
                                <input type="number" name="refund_item[<?= $it['id'] ?>]" min="0" max="<?= (float)$it['quantity'] ?>" step="0.1" value="0"
                                       class="u-w-70 u-p-1-2 u-bordered u-r-6 u-ta-c">
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <label class="u-block u-t-14 u-fw-600 u-mb-2">Details (optional)</label>
                    <textarea name="detail" rows="2" class="form-control u-mb-3" placeholder="Tell the seller what went wrong..."></textarea>

                    <div class="u-flex u-gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">Submit request</button>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('refundBox').style.display='none';">Cancel</button>
                    </div>
                    <p class="u-t-12 u-sage u-m-3-0-0">
                        Approved refunds are credited to your FreshMart wallet. Shipping fees aren't refundable.
                    </p>
                </form>
            </div>
        <?php endif; ?>

        <?php
        // Visual 6-step progress
        $steps    = ['PLACED','PROCESSING','QUALITY_CHECK','PACKED','OUT_FOR_DELIVERY','DELIVERED'];
        $labels   = ['Placed','Processing','Quality Check','Packed','Out for Delivery','Delivered'];
        $currentIdx = array_search($order['status'], $steps, true);
        if ($currentIdx === false) $currentIdx = -1;  // CANCELLED etc.
        ?>
        <div class="panel u-p-5-4 u-m-4-0">
            <div class="order-steps">
                <?php foreach ($steps as $i => $step):
                    $done    = $i <= $currentIdx;
                    $current = $i === $currentIdx;
                ?>
                <div class="order-step<?= $done ? ' is-done' : '' ?><?= $current ? ' is-current' : '' ?>">
                    <div class="order-step-dot">
                        <?= $done ? '✓' : $i + 1 ?>
                    </div>
                    <div class="order-step-label">
                        <?= e($labels[$i]) ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="order-step-rail"></div>
                <?php if ($currentIdx > 0): ?>
                    <div class="order-step-rail-fill" style="--step: <?= $currentIdx ?>"></div>
                <?php endif; ?>
            </div>
        </div>

        <h3 class="u-t-18 u-mt-6">Order timeline</h3>
        <div class="panel u-p-4 u-mb-4">
            <?php foreach ($history as $i => $h): ?>
                <div class="timeline-row<?= $i < count($history) - 1 ? ' is-divided' : '' ?>">
                    <div class="u-t-13 u-muted u-nowrap u-minw-140">
                        <?= format_datetime($h['created_at'], 'd M, H:i') ?>
                    </div>
                    <div>
                        <strong><?= e($h['new_status']) ?></strong>
                        <?php if (!empty($h['notes'])): ?>
                            <div class="u-t-14 u-muted"><?= e($h['notes']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <h3 class="u-t-18">Items</h3>
        <div class="panel u-p-4 u-mb-4">
            <?php foreach ($items as $item): ?>
                <div class="u-grid u-cols-media u-gap-3 u-py-3 u-ai-center u-bb">
                    <div class="u-square u-bg-page u-r u-ovh u-grid u-place-center">
                        <?php if (!empty($item['primary_image'])): ?>
                            <img src="<?= upload_url($item['primary_image']) ?>" alt="<?= attr($item['product_name']) ?>" loading="lazy" class="media-fill">
                        <?php else: ?>
                            <?= icon('leaf', 16) ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <a href="<?= url('/shop/product.php?slug=' . urlencode($item['slug'])) ?>"
                           class="u-ink u-fw-600">
                            <?= e($item['product_name']) ?>
                        </a>
                        <div class="u-t-13 u-muted">
                            <?= number_format((float) $item['quantity'], 2) ?> ×
                            <?= format_myr($item['unit_price']) ?>
                            · Batch <code><?= e($item['batch_code']) ?></code> ·
                            <?= e($item['freshness_at_order']) ?>
                        </div>
                    </div>
                    <div class="u-fw-600"><?= format_myr($item['subtotal']) ?></div>
                </div>
            <?php endforeach; ?>

            <div class="u-mt-3 u-flex u-col u-gap-1">
                <div class="u-flex u-jc-between u-muted">
                    <span>Subtotal</span><span><?= format_myr($order['subtotal']) ?></span>
                </div>
                <div class="u-flex u-jc-between u-muted">
                    <span>Shipping</span><span><?= $order['shipping_fee'] == 0 ? 'FREE' : format_myr($order['shipping_fee']) ?></span>
                </div>
                <?php if ((float) $order['discount_amount'] > 0): ?>
                <div class="u-flex u-jc-between u-fg-accent">
                    <span>Discount</span><span>−<?= format_myr($order['discount_amount']) ?></span>
                </div>
                <?php endif; ?>
                <div class="u-flex u-jc-between u-fw-700 u-t-18 u-pt-2 u-bt">
                    <span>Total</span><span><?= format_myr($order['total']) ?></span>
                </div>
            </div>
        </div>

        <div class="u-grid u-cols-2 u-gap-4">
            <div class="panel u-p-4">
                <h4 class="u-mt-0 u-t-15"><?= icon('pin', 16) ?> Delivery</h4>
                <p class="u-m-0 u-t-14 u-muted">
                    <?= e($order['recipient_name']) ?><br>
                    <?= e($order['line1']) ?><br>
                    <?= e($order['city']) ?>, <?= e($order['state']) ?> <?= e($order['postcode']) ?>
                </p>
                <?php if (!empty($order['tracking_number'])): ?>
                    <p class="u-m-2-0-0 u-t-13">
                        Tracking: <code><?= e($order['tracking_number']) ?></code>
                    </p>
                <?php endif; ?>
            </div>
            <div class="panel u-p-4">
                <h4 class="u-mt-0 u-t-15"><?= icon('wallet', 16) ?> Payment</h4>
                <p class="u-m-0 u-t-14 u-muted">
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

<section class="container u-page-head">
    <h1>My orders</h1>

    <?php if (empty($orders)): ?>
        <div class="empty-state u-mt-6">
            <p class="u-t-17"><?= icon('package', 16) ?> No orders yet</p>
            <a href="<?= url('/shop/browse.php') ?>" class="btn btn-primary u-mt-3">Browse products</a>
        </div>
    <?php else: ?>
        <div class="u-flex u-col u-gap-3 u-mt-4">
            <?php foreach ($orders as $o): ?>
                <a href="<?= url('/shop/orders.php?id=' . $o['id']) ?>"
                   class="panel order-row">
                    <div>
                        <div class="u-fw-600"><?= e($o['order_number']) ?></div>
                        <div class="u-t-14 u-muted">
                            <?= format_datetime($o['placed_at'], 'd M Y, H:i') ?>
                        </div>
                    </div>
                    <div class="u-muted u-t-14">
                        <?= $o['item_count'] ?> item<?= $o['item_count'] === 1 ? '' : 's' ?>
                    </div>
                    <div>
                        <span class="status-pill status-<?= strtolower($o['status']) === 'delivered' ? 'active' : 'pending' ?>">
                            <?= e($o['status']) ?>
                        </span>
                    </div>
                    <div class="u-ta-r u-fw-700">
                        <?= format_myr($o['total']) ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
