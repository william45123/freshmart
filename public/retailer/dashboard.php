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

// Stock freshness mix (for the donut chart)
$mix = ['VERY_FRESH' => 0, 'FRESH' => 0, 'ENJOY_SOON' => 0, 'LAST_CHANCE' => 0];
foreach (db_all(
    "SELECT sb.received_date, sb.expiry_date,
            COALESCE(p.decay_exponent_override, c.decay_exponent, 1.0) AS de
     FROM stock_batches sb
     JOIN products p   ON p.id = sb.product_id
     JOIN categories c ON c.id = p.category_id
     WHERE p.retailer_id = ? AND sb.status = 'ACTIVE'",
    [$retailerId]
) as $m) {
    $lv = freshness_level($m['received_date'], $m['expiry_date'], (float) $m['de']);
    if (isset($mix[$lv])) $mix[$lv]++;
}
$mixColors = [
    freshness_info('VERY_FRESH')['color_hex'],
    freshness_info('FRESH')['color_hex'],
    freshness_info('ENJOY_SOON')['color_hex'],
    freshness_info('LAST_CHANCE')['color_hex'],
];

// Sell-through rate per product (avg units/day over the last 30 days) — for forecast
$rate = [];
foreach (db_all(
    "SELECT oi.product_id AS pid, SUM(oi.quantity) / 30.0 AS r
     FROM order_items oi
     JOIN orders o   ON o.id = oi.order_id
     JOIN products p ON p.id = oi.product_id
     WHERE p.retailer_id = ?
       AND o.placed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
       AND o.status IN ('PROCESSING','QUALITY_CHECK','PACKED','OUT_FOR_DELIVERY','DELIVERED')
     GROUP BY oi.product_id",
    [$retailerId]
) as $r) {
    $rate[(int) $r['pid']] = (float) $r['r'];
}

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

<h2 class="u-t-20"><span class="label-ico"><?= icon('alert', 20) ?> Batches Expiring Soon</span></h2>
<?php if (empty($expiringSoon)): ?>
    <div class="empty-state">Nothing expiring in the next 3 days.</div>
<?php else: ?>
    <table class="data-table data-table">
        <thead>
            <tr>
                <th>Batch</th>
                <th>Product</th>
                <th>Quantity</th>
                <th>Expiry</th>
                <th>Freshness</th>
                <th>Forecast</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($expiringSoon as $b):
                $level = freshness_level($b['received_date'], $b['expiry_date'], (float) $b['decay_exponent']);
                $daysLeft = max(0, days_between(now_my()->format('Y-m-d'), $b['expiry_date']));
            ?>
                <tr>
                    <td data-label="Batch"><code><?= e($b['batch_code']) ?></code></td>
                    <td data-label="Product"><?= e($b['product_name']) ?></td>
                    <td data-label="Quantity"><?= number_format((float) $b['quantity_remaining'], 2) ?></td>
                    <td data-label="Expiry"><?= format_date($b['expiry_date']) ?> <small class="u-muted">(<?= relative_date($b['expiry_date']) ?>)</small></td>
                    <td data-label="Freshness"><?= freshness_ring_html([
                        'freshness_percent' => freshness_percent($b['received_date'], $b['expiry_date'], (float) $b['decay_exponent']),
                        'freshness_color'   => freshness_info($level)['color_hex'],
                        'freshness_level'   => $level,
                        'days_remaining'    => $daysLeft,
                    ], 40, true) ?></td>
                    <td data-label="Forecast">
                        <?php
                            $pr   = $rate[(int) $b['product_id']] ?? 0.0;
                            $proj = $pr * $daysLeft;
                            $risk = (float) $b['quantity_remaining'] - $proj;
                        ?>
                        <?php if ($risk > 0.5): ?>
                            <span class="u-fg-accent u-t-13">~<?= number_format($risk, 1) ?> may expire unsold</span>
                        <?php else: ?>
                            <span class="u-fg-primary u-t-13">On track to sell</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<h2 class="u-t-20 u-mt-8">Stock freshness mix</h2>
<div class="panel u-maxw-380 u-p-5">
    <div class="chart-wrap"><canvas id="freshMixChart"></canvas></div>
</div>
<script src="<?= asset('js/chart.umd.min.js') ?>"></script>
<script>
new Chart(document.getElementById('freshMixChart'), {
    type: 'doughnut',
    data: {
        labels: ['Very Fresh', 'Fresh', 'Enjoy Soon', 'Last Chance'],
        datasets: [{
            data: [<?= (int) $mix['VERY_FRESH'] ?>, <?= (int) $mix['FRESH'] ?>, <?= (int) $mix['ENJOY_SOON'] ?>, <?= (int) $mix['LAST_CHANCE'] ?>],
            backgroundColor: <?= json_encode($mixColors) ?>,
            borderWidth: 2,
            borderColor: '#ffffff'
        }]
    },
    options: { maintainAspectRatio: false,
        responsive: true,
        plugins: { legend: { position: 'bottom', labels: { font: { size: 12 } } } },
        cutout: '62%'
    }
});
</script>

<?php
retailer_layout_end();
require_once __DIR__ . '/../../includes/footer.php';
