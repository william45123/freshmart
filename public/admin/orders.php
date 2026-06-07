<?php
/**
 * Admin: All Orders across the whole platform.
 * Admin can view every order, filter by status, and see full details.
 */

require_once __DIR__ . '/../../includes/admin_layout.php';
require_once __DIR__ . '/../../includes/helpers.php';

$errors = [];

// ---- Handle status update (admin can override any order's status) ----
if (is_post() && csrf_verify() && input('action') === 'update_status') {
    $orderId   = (int) input('order_id');
    $newStatus = (string) input('new_status', '');
    $allowed   = ['PLACED','PROCESSING','QUALITY_CHECK','PACKED','OUT_FOR_DELIVERY','DELIVERED','CANCELLED','REFUNDED'];

    if (!in_array($newStatus, $allowed, true)) {
        $errors[] = 'Invalid status.';
    } else {
        try {
            db_transaction(function () use ($orderId, $newStatus) {
                $current = db_scalar('SELECT status FROM orders WHERE id = ?', [$orderId]);
                db_run('UPDATE orders SET status = ? WHERE id = ?', [$newStatus, $orderId]);
                db_run(
                    "INSERT INTO order_history (order_id, previous_status, new_status, changed_by, notes)
                     VALUES (?, ?, ?, ?, ?)",
                    [$orderId, $current, $newStatus, auth_id(), 'Changed by admin']
                );
                db_run(
                    "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, new_values)
                     VALUES (?, 'ORDER_STATUS_CHANGE', 'order', ?, ?)",
                    [auth_id(), $orderId, json_encode(['to' => $newStatus])]
                );
                // Notify customer
                $customerId = db_scalar('SELECT user_id FROM orders WHERE id = ?', [$orderId]);
                db_run(
                    "INSERT INTO notifications (user_id, type, title, body, link)
                     VALUES (?, 'ORDER_UPDATE', ?, ?, ?)",
                    [$customerId, "Order is now $newStatus",
                     "Your order status was updated to $newStatus.",
                     "/shop/orders.php?id=$orderId"]
                );
            });
            flash_set('success', "Order status updated to $newStatus.");
            redirect('/admin/orders.php' . (input('status') ? '?status=' . input('status') : ''));
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

// ---- Single order detail view ----
$orderId = (int) input('id', 0);

if ($orderId > 0) {
    $order = db_one(
        "SELECT o.*, pr.full_name AS customer_name, u.email AS customer_email,
                s.tracking_number, s.estimated_delivery, s.shipped_at, s.delivered_at,
                pm.payment_method, pm.status AS payment_status, pm.transaction_ref,
                a.recipient_name, a.line1, a.city, a.state, a.postcode,
                pc.code AS promo_code
         FROM orders o
         JOIN users u ON u.id = o.user_id
         LEFT JOIN profiles pr ON pr.user_id = o.user_id
         LEFT JOIN shipments s ON s.order_id = o.id
         LEFT JOIN payments pm ON pm.order_id = o.id
         LEFT JOIN addresses a ON a.id = o.shipping_address_id
         LEFT JOIN promo_codes pc ON pc.id = o.promo_code_id
         WHERE o.id = ?",
        [$orderId]
    );

    if (!$order) {
        flash_set('error', 'Order not found.');
        redirect('/admin/orders.php');
    }

    $items = db_all(
        "SELECT oi.*, p.slug, r.company_name AS retailer_name,
                sb.batch_code
         FROM order_items oi
         JOIN products p ON p.id = oi.product_id
         JOIN retailers r ON r.id = p.retailer_id
         LEFT JOIN stock_batches sb ON sb.id = oi.stock_batch_id
         WHERE oi.order_id = ?",
        [$orderId]
    );

    $history = db_all(
        "SELECT oh.*, pr.full_name AS changed_by_name
         FROM order_history oh
         LEFT JOIN profiles pr ON pr.user_id = oh.changed_by
         WHERE oh.order_id = ?
         ORDER BY oh.created_at ASC",
        [$orderId]
    );

    $pageTitle = 'Order ' . $order['order_number'] . ' — Admin';
    require_once __DIR__ . '/../../includes/header.php';
    admin_layout_start('orders', 'Order ' . $order['order_number']);
    ?>

    <a href="<?= url('/admin/orders.php') ?>" style="color: var(--color-text-muted); font-size: 0.875rem;">← All orders</a>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error" style="margin-top: var(--space-3);"><?= e($err) ?></div>
    <?php endforeach; ?>

    <div style="display: flex; align-items: center; gap: var(--space-3); margin: var(--space-3) 0 var(--space-4);">
        <h2 style="margin: 0; font-size: 1.5rem;"><?= e($order['order_number']) ?></h2>
        <span class="status-pill status-<?= strtolower($order['status']) === 'delivered' ? 'active' : 'pending' ?>">
            <?= e(str_replace('_', ' ', $order['status'])) ?>
        </span>
    </div>

    <!-- Admin status override -->
    <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-4); margin-bottom: var(--space-4);">
        <form method="post" style="display: flex; gap: var(--space-2); align-items: center; flex-wrap: wrap;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
            <label style="font-size: 0.875rem; color: var(--color-text-muted);">Override status:</label>
            <select name="new_status" class="form-control" style="width: auto;">
                <?php foreach (['PLACED','PROCESSING','QUALITY_CHECK','PACKED','OUT_FOR_DELIVERY','DELIVERED','CANCELLED','REFUNDED'] as $st): ?>
                    <option value="<?= $st ?>" <?= $order['status'] === $st ? 'selected' : '' ?>><?= str_replace('_',' ',$st) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Change order status?')">Update</button>
        </form>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: var(--space-4);">
        <!-- Items -->
        <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-5);">
            <h3 style="margin-top: 0;">Items</h3>
            <?php foreach ($items as $item): ?>
                <div style="display: grid; grid-template-columns: 1fr auto; gap: var(--space-3); padding: var(--space-3) 0; border-bottom: 1px solid var(--color-border);">
                    <div>
                        <div style="font-weight: 600;"><?= e($item['product_name']) ?></div>
                        <div style="font-size: 0.8125rem; color: var(--color-text-muted);">
                            <?= e($item['retailer_name']) ?> ·
                            <?= number_format((float) $item['quantity'], 2) ?> × <?= format_myr($item['unit_price']) ?>
                            · Batch <code><?= e($item['batch_code']) ?></code>
                            · <?= e($item['freshness_at_order']) ?>
                        </div>
                    </div>
                    <div style="font-weight: 600;"><?= format_myr($item['subtotal']) ?></div>
                </div>
            <?php endforeach; ?>
            <div style="margin-top: var(--space-3); display: flex; flex-direction: column; gap: var(--space-1);">
                <div style="display: flex; justify-content: space-between; color: var(--color-text-muted);">
                    <span>Subtotal</span><span><?= format_myr($order['subtotal']) ?></span>
                </div>
                <?php if ((float) $order['discount_amount'] > 0): ?>
                <div style="display: flex; justify-content: space-between; color: var(--color-accent);">
                    <span>Discount <?= $order['promo_code'] ? '(' . e($order['promo_code']) . ')' : '' ?></span>
                    <span>−<?= format_myr($order['discount_amount']) ?></span>
                </div>
                <?php endif; ?>
                <div style="display: flex; justify-content: space-between; color: var(--color-text-muted);">
                    <span>Shipping</span><span><?= $order['shipping_fee'] == 0 ? 'FREE' : format_myr($order['shipping_fee']) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 1.125rem; padding-top: var(--space-2); border-top: 1px solid var(--color-border);">
                    <span>Total</span><span><?= format_myr($order['total']) ?></span>
                </div>
            </div>
        </div>

        <!-- Customer + delivery + payment -->
        <div style="display: flex; flex-direction: column; gap: var(--space-4);">
            <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-4);">
                <h4 style="margin-top: 0; font-size: 0.9375rem;">👤 Customer</h4>
                <p style="margin: 0; font-size: 0.875rem; color: var(--color-text-muted);">
                    <strong style="color: var(--color-text);"><?= e($order['customer_name'] ?? '—') ?></strong><br>
                    <?= e($order['customer_email']) ?>
                </p>
            </div>
            <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-4);">
                <h4 style="margin-top: 0; font-size: 0.9375rem;">📍 Delivery</h4>
                <p style="margin: 0; font-size: 0.875rem; color: var(--color-text-muted);">
                    <?= e($order['recipient_name']) ?><br>
                    <?= e($order['line1']) ?><br>
                    <?= e($order['city']) ?>, <?= e($order['state']) ?> <?= e($order['postcode']) ?>
                    <?php if (!empty($order['tracking_number'])): ?>
                        <br>Tracking: <code><?= e($order['tracking_number']) ?></code>
                    <?php endif; ?>
                </p>
            </div>
            <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-4);">
                <h4 style="margin-top: 0; font-size: 0.9375rem;">💳 Payment</h4>
                <p style="margin: 0; font-size: 0.875rem; color: var(--color-text-muted);">
                    <?= e($order['payment_method'] ?? '—') ?> · <?= e($order['payment_status'] ?? '—') ?><br>
                    <?php if (!empty($order['transaction_ref'])): ?>Ref: <code><?= e($order['transaction_ref']) ?></code><?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Timeline -->
    <h3 style="font-size: 1.125rem; margin-top: var(--space-6);">Status history</h3>
    <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-4);">
        <?php if (empty($history)): ?>
            <p style="color: var(--color-text-muted); margin: 0;">No history yet.</p>
        <?php else: ?>
            <?php foreach ($history as $i => $h): ?>
                <div style="display: flex; gap: var(--space-3); padding: var(--space-2) 0; <?= $i < count($history) - 1 ? 'border-bottom: 1px solid var(--color-border);' : '' ?>">
                    <div style="font-size: 0.8125rem; color: var(--color-text-muted); min-width: 150px;">
                        <?= format_datetime($h['created_at'], 'd M Y, H:i') ?>
                    </div>
                    <div style="font-size: 0.875rem;">
                        <strong><?= e(str_replace('_',' ',$h['new_status'])) ?></strong>
                        <?php if (!empty($h['changed_by_name'])): ?>
                            <span style="color: var(--color-text-muted);">by <?= e($h['changed_by_name']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($h['notes'])): ?>
                            <div style="color: var(--color-text-muted);"><?= e($h['notes']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php
    admin_layout_end();
    require_once __DIR__ . '/../../includes/footer.php';
    return;
}

// ---- LIST VIEW ----
$statusFilter = (string) input('status', '');
$search       = trim((string) input('q', ''));

$where = ['1=1'];
$args  = [];
if ($statusFilter !== '') {
    $where[] = 'o.status = ?';
    $args[]  = $statusFilter;
}
if ($search !== '') {
    $where[] = '(o.order_number LIKE ? OR u.email LIKE ? OR pr.full_name LIKE ?)';
    $args[]  = "%$search%";
    $args[]  = "%$search%";
    $args[]  = "%$search%";
}

$orders = db_all(
    "SELECT o.*, pr.full_name AS customer_name, u.email AS customer_email,
            (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count
     FROM orders o
     JOIN users u ON u.id = o.user_id
     LEFT JOIN profiles pr ON pr.user_id = o.user_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY o.placed_at DESC
     LIMIT 200",
    $args
);

// Status counts for filter pills
$statusCounts = db_all("SELECT status, COUNT(*) AS c FROM orders GROUP BY status");
$counts = [];
foreach ($statusCounts as $r) $counts[$r['status']] = (int) $r['c'];

// Quick KPIs
$totalRevenue = (float) db_scalar("SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ('PROCESSING','QUALITY_CHECK','PACKED','OUT_FOR_DELIVERY','DELIVERED')");
$totalOrders  = (int) db_scalar("SELECT COUNT(*) FROM orders");

$pageTitle = 'All Orders — Admin';
require_once __DIR__ . '/../../includes/header.php';
admin_layout_start('orders', 'All Orders');
?>

<?php foreach ($errors as $err): ?>
    <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<!-- KPIs -->
<div class="kpi-grid" style="margin-bottom: var(--space-4);">
    <div class="kpi-card">
        <div class="kpi-label">Total Orders</div>
        <div class="kpi-value"><?= number_format($totalOrders) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Revenue (paid orders)</div>
        <div class="kpi-value"><?= format_myr($totalRevenue) ?></div>
    </div>
</div>

<!-- Search -->
<form method="get" style="display: flex; gap: var(--space-2); margin-bottom: var(--space-3);">
    <?php if ($statusFilter): ?><input type="hidden" name="status" value="<?= attr($statusFilter) ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= attr($search) ?>" placeholder="Search order #, email, or name..."
           class="form-control" style="max-width: 360px;">
    <button type="submit" class="btn btn-secondary">Search</button>
    <?php if ($search): ?><a href="<?= url('/admin/orders.php' . ($statusFilter ? '?status='.$statusFilter : '')) ?>" class="btn btn-ghost">Clear</a><?php endif; ?>
</form>

<!-- Status filter pills -->
<div style="display: flex; gap: var(--space-2); margin-bottom: var(--space-4); flex-wrap: wrap;">
    <a href="<?= url('/admin/orders.php') ?>"
       class="btn <?= $statusFilter === '' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
        All <?= array_sum($counts) > 0 ? '(' . array_sum($counts) . ')' : '' ?>
    </a>
    <?php foreach (['PLACED','PROCESSING','QUALITY_CHECK','PACKED','OUT_FOR_DELIVERY','DELIVERED','CANCELLED','REFUNDED'] as $st):
        $c = $counts[$st] ?? 0;
    ?>
        <a href="<?= url('/admin/orders.php?status=' . $st) ?>"
           class="btn <?= $statusFilter === $st ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
            <?= str_replace('_',' ',$st) ?> <?= $c > 0 ? "($c)" : '' ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if (empty($orders)): ?>
    <div class="empty-state">📦 No orders found.</div>
<?php else: ?>
    <table class="data-table">
        <thead>
            <tr><th>Order</th><th>Customer</th><th>Placed</th><th>Items</th><th>Total</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><code style="font-size: 0.8125rem;"><?= e($o['order_number']) ?></code></td>
                    <td>
                        <strong><?= e($o['customer_name'] ?? '—') ?></strong>
                        <br><small style="color: var(--color-text-muted);"><?= e($o['customer_email']) ?></small>
                    </td>
                    <td>
                        <?= format_datetime($o['placed_at'], 'd M Y') ?>
                        <br><small style="color: var(--color-text-muted);"><?= format_datetime($o['placed_at'], 'H:i') ?></small>
                    </td>
                    <td><?= (int) $o['item_count'] ?></td>
                    <td><strong><?= format_myr($o['total']) ?></strong></td>
                    <td>
                        <span class="status-pill status-<?= strtolower($o['status']) === 'delivered' ? 'active' : 'pending' ?>">
                            <?= e(str_replace('_',' ',$o['status'])) ?>
                        </span>
                    </td>
                    <td>
                        <a href="<?= url('/admin/orders.php?id=' . $o['id']) ?>" class="btn btn-secondary btn-sm">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
admin_layout_end();
require_once __DIR__ . '/../../includes/footer.php';
