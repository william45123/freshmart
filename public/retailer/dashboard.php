<?php
/**
 * Retailer dashboard — KPI overview.
 */

require_once __DIR__ . '/../../includes/retailer_layout.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/freshness.php';

$retailer = retailer_current();
$retailerId = (int) $retailer['id'];

// KPIs
$totalProducts = (int) db_scalar(
    'SELECT COUNT(*) FROM products WHERE retailer_id = ? AND deleted_at IS NULL',
    [$retailerId]
);

$activeBatches = (int) db_scalar(
    "SELECT COUNT(*) FROM stock_batches sb
     JOIN products p ON p.id = sb.product_id
     WHERE p.retailer_id = ? AND sb.status = 'ACTIVE'",
    [$retailerId]
);

$lowStock = (int) db_scalar(
    "SELECT COUNT(DISTINCT p.id)
     FROM products p
     WHERE p.retailer_id = ? AND p.is_active = 1 AND p.deleted_at IS NULL
       AND (SELECT COALESCE(SUM(quantity_remaining), 0)
            FROM stock_batches sb
            WHERE sb.product_id = p.id AND sb.status = 'ACTIVE') < p.min_order_qty * 10",
    [$retailerId]
);

// Batches expiring within 3 days
$expiringSoon = db_all(
    "SELECT sb.id, sb.batch_code, sb.expiry_date, sb.quantity_remaining,
            p.name AS product_name, p.id AS product_id,
            COALESCE(p.decay_exponent_override, c.decay_exponent, 1.0) AS decay_exponent,
            sb.received_date
     FROM stock_batches sb
     JOIN products p   ON p.id = sb.product_id
     JOIN categories c ON c.id = p.category_id
     WHERE p.retailer_id = ?
       AND sb.status = 'ACTIVE'
       AND sb.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
       AND sb.expiry_date >= CURDATE()
     ORDER BY sb.expiry_date ASC
     LIMIT 10",
    [$retailerId]
);

$pageTitle = 'Retailer Dashboard';
require_once __DIR__ . '/../../includes/header.php';
retailer_layout_start('dashboard', 'Dashboard');
?>

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Total Products</div>
        <div class="kpi-value"><?= number_format($totalProducts) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Active Stock Batches</div>
        <div class="kpi-value"><?= number_format($activeBatches) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Products with Low Stock</div>
        <div class="kpi-value <?= $lowStock > 0 ? 'is-warn' : '' ?>">
            <?= number_format($lowStock) ?>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Batches Expiring &lt; 3 Days</div>
        <div class="kpi-value <?= count($expiringSoon) > 0 ? 'is-danger' : '' ?>">
            <?= count($expiringSoon) ?>
        </div>
    </div>
</div>

<h2 style="font-size: 1.25rem;">⏰ Batches Expiring Soon</h2>
<?php if (empty($expiringSoon)): ?>
    <div class="empty-state">Nothing expiring in the next 3 days 🎉</div>
<?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Batch</th>
                <th>Product</th>
                <th>Quantity</th>
                <th>Expiry</th>
                <th>Freshness</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($expiringSoon as $b):
                $level = freshness_level($b['received_date'], $b['expiry_date'], (float) $b['decay_exponent']);
                $daysLeft = max(0, days_between(now_my()->format('Y-m-d'), $b['expiry_date']));
            ?>
                <tr>
                    <td><code><?= e($b['batch_code']) ?></code></td>
                    <td><?= e($b['product_name']) ?></td>
                    <td><?= number_format((float) $b['quantity_remaining'], 2) ?></td>
                    <td><?= format_date($b['expiry_date']) ?> <small style="color: var(--color-text-muted);">(<?= relative_date($b['expiry_date']) ?>)</small></td>
                    <td><?= freshness_badge_html($level, $daysLeft) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
retailer_layout_end();
require_once __DIR__ . '/../../includes/footer.php';
