<?php
/**
 * Retailer Orders — view all orders containing this retailer's products,
 * with workflow to advance status (PROCESSING → SHIPPED → DELIVERED).
 */

require_once __DIR__ . '/../../includes/retailer_layout.php';
require_once __DIR__ . '/../../includes/helpers.php';

$retailer    = retailer_current();
$retailerId  = (int) $retailer['id'];
$errors      = [];

// ---- Handle status update ----
if (is_post() && input('action') === 'update_status') {
    if (!csrf_verify()) {
        $errors[] = 'CSRF mismatch.';
    } else {
        $orderId    = (int) input('order_id');
        $newStatus  = (string) input('new_status', '');
        $allowed    = ['PLACED','PROCESSING','QUALITY_CHECK','PACKED','OUT_FOR_DELIVERY','DELIVERED','CANCELLED'];

        // Verify retailer has products in this order
        $belongs = db_scalar(
            "SELECT COUNT(*) FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = ? AND p.retailer_id = ?",
            [$orderId, $retailerId]
        );

        if (!$belongs) {
            $errors[] = 'You do not have items in this order.';
        } elseif (!in_array($newStatus, $allowed, true)) {
            $errors[] = 'Invalid status.';
        } else {
            try {
                db_transaction(function () use ($orderId, $newStatus) {
                    $current = db_scalar('SELECT status FROM orders WHERE id = ?', [$orderId]);
                    db_run('UPDATE orders SET status = ? WHERE id = ?', [$newStatus, $orderId]);

                    db_run(
                        "INSERT INTO order_history (order_id, previous_status, new_status, changed_by, notes)
                         VALUES (?, ?, ?, ?, ?)",
                        [$orderId, $current, $newStatus, auth_id(), "Updated by retailer"]
                    );

                    if ($newStatus === 'OUT_FOR_DELIVERY') {
                        db_run('UPDATE shipments SET shipped_at = NOW() WHERE order_id = ?', [$orderId]);
                    } elseif ($newStatus === 'DELIVERED') {
                        db_run('UPDATE shipments SET delivered_at = NOW() WHERE order_id = ?', [$orderId]);
                    }

                    // Notify the customer
                    $customerId = db_scalar('SELECT user_id FROM orders WHERE id = ?', [$orderId]);
                    db_run(
                        "INSERT INTO notifications (user_id, type, title, body, link)
                         VALUES (?, 'ORDER_UPDATE', ?, ?, ?)",
                        [
                            $customerId,
                            "Order is now $newStatus",
                            "Your order status has been updated to $newStatus.",
                            "/shop/orders.php?id=$orderId",
                        ]
                    );
                });
                flash_set('success', "Order status updated to $newStatus.");
                redirect('/retailer/orders.php');
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

// ---- Load orders ----
$statusFilter = (string) input('status', '');
$where = ['EXISTS (SELECT 1 FROM order_items oi JOIN products p ON p.id = oi.product_id
                  WHERE oi.order_id = o.id AND p.retailer_id = ?)'];
$args  = [$retailerId];
if ($statusFilter !== '') {
    $where[] = 'o.status = ?';
    $args[]  = $statusFilter;
}

$orders = db_all(
    "SELECT o.*,
            pr.full_name AS customer_name,
            u.email AS customer_email,
            (SELECT SUM(oi.subtotal)
             FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = o.id AND p.retailer_id = ?) AS retailer_subtotal,
            (SELECT COUNT(*)
             FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = o.id AND p.retailer_id = ?) AS retailer_items
     FROM orders o
     JOIN users u ON u.id = o.user_id
     LEFT JOIN profiles pr ON pr.user_id = o.user_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY o.placed_at DESC
     LIMIT 100",
    array_merge([$retailerId, $retailerId], $args)
);

$statusCounts = db_all(
    "SELECT o.status, COUNT(*) AS c
     FROM orders o
     WHERE EXISTS (SELECT 1 FROM order_items oi JOIN products p ON p.id = oi.product_id
                   WHERE oi.order_id = o.id AND p.retailer_id = ?)
     GROUP BY o.status",
    [$retailerId]
);
$counts = [];
foreach ($statusCounts as $r) $counts[$r['status']] = (int) $r['c'];

$pageTitle = 'Orders — Retailer';
require_once __DIR__ . '/../../includes/header.php';
retailer_layout_start('orders', 'Orders');
?>

<?php foreach ($errors as $err): ?>
    <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<!-- Status filter pills -->
<div style="display: flex; gap: var(--space-2); margin-bottom: var(--space-4); flex-wrap: wrap;">
    <a href="<?= url('/retailer/orders.php') ?>"
       class="btn <?= $statusFilter === '' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
        All <?= array_sum($counts) > 0 ? '(' . array_sum($counts) . ')' : '' ?>
    </a>
    <?php foreach (['PLACED','PROCESSING','QUALITY_CHECK','PACKED','OUT_FOR_DELIVERY','DELIVERED','CANCELLED'] as $st):
        $c = $counts[$st] ?? 0;
    ?>
        <a href="<?= url('/retailer/orders.php?status=' . $st) ?>"
           class="btn <?= $statusFilter === $st ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
            <?= e($st) ?> <?= $c > 0 ? "($c)" : '' ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if (empty($orders)): ?>
    <div class="empty-state">
        📦 No orders yet.
    </div>
<?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Placed</th>
                <th>Items</th>
                <th>Your Sales</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $o):
                $nextStatus = match ($o['status']) {
                    'PLACED'            => 'PROCESSING',
                    'PROCESSING'        => 'QUALITY_CHECK',
                    'QUALITY_CHECK'     => 'PACKED',
                    'PACKED'            => 'OUT_FOR_DELIVERY',
                    'OUT_FOR_DELIVERY'  => 'DELIVERED',
                    default             => null,
                };
            ?>
                <tr>
                    <td>
                        <code style="font-size: 0.8125rem;"><?= e($o['order_number']) ?></code>
                    </td>
                    <td>
                        <strong><?= e($o['customer_name'] ?? $o['customer_email']) ?></strong>
                        <br><small style="color: var(--color-text-muted);"><?= e($o['customer_email']) ?></small>
                    </td>
                    <td>
                        <?= format_datetime($o['placed_at'], 'd M Y') ?>
                        <br><small style="color: var(--color-text-muted);"><?= format_datetime($o['placed_at'], 'H:i') ?></small>
                    </td>
                    <td><?= (int) $o['retailer_items'] ?></td>
                    <td><strong><?= format_myr($o['retailer_subtotal']) ?></strong></td>
                    <td>
                        <span class="status-pill status-<?= strtolower($o['status']) === 'delivered' ? 'active' : 'pending' ?>">
                            <?= e($o['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($nextStatus): ?>
                            <form method="post" style="display: inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                <input type="hidden" name="new_status" value="<?= $nextStatus ?>">
                                <button type="submit" class="btn btn-primary btn-sm"
                                        onclick="return confirm('Mark as <?= $nextStatus ?>?')">
                                    → <?= $nextStatus ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
retailer_layout_end();
require_once __DIR__ . '/../../includes/footer.php';
