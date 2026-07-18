<?php
/**
 * Retailer Product Performance Report (R-APP-12).
 *
 * Shows per-product breakdown:
 *   - Total sales volume (units sold)
 *   - Revenue (MYR)
 *   - Current stock
 *   - Views, conversion rate
 *   - Filterable by date range
 *
 * Can also export as CSV.
 */

require_once __DIR__ . '/../../includes/retailer_layout.php';
require_once __DIR__ . '/../../includes/helpers.php';

$retailer   = retailer_current();
$retailerId = (int) $retailer['id'];

$from = (string) input('from', date('Y-m-d', strtotime('-30 days')));
$to   = (string) input('to', date('Y-m-d'));
$export = (string) input('export', '');

// Validate date strings
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d', strtotime('-30 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

$report = db_all(
    "SELECT
        p.id,
        p.sku,
        p.name,
        p.base_price,
        p.view_count,
        c.name AS category,
        ut.code AS unit_code,

        -- Current stock
        COALESCE((SELECT SUM(quantity_remaining) FROM stock_batches sb
                  WHERE sb.product_id = p.id AND sb.status = 'ACTIVE'), 0) AS current_stock,

        -- Sales in date range
        COALESCE((SELECT SUM(oi.quantity)
                  FROM order_items oi JOIN orders o ON o.id = oi.order_id
                  WHERE oi.product_id = p.id
                    AND DATE(o.placed_at) BETWEEN ? AND ?
                    AND o.status IN ('PROCESSING','QUALITY_CHECK','PACKED','OUT_FOR_DELIVERY','DELIVERED')), 0) AS units_sold,

        COALESCE((SELECT SUM(oi.subtotal)
                  FROM order_items oi JOIN orders o ON o.id = oi.order_id
                  WHERE oi.product_id = p.id
                    AND DATE(o.placed_at) BETWEEN ? AND ?
                    AND o.status IN ('PROCESSING','QUALITY_CHECK','PACKED','OUT_FOR_DELIVERY','DELIVERED')), 0) AS revenue,

        COALESCE((SELECT COUNT(DISTINCT o.id)
                  FROM order_items oi JOIN orders o ON o.id = oi.order_id
                  WHERE oi.product_id = p.id
                    AND DATE(o.placed_at) BETWEEN ? AND ?), 0) AS order_count,

        -- Sold from LAST_CHANCE (sustainability)
        COALESCE((SELECT SUM(oi.quantity)
                  FROM order_items oi JOIN orders o ON o.id = oi.order_id
                  WHERE oi.product_id = p.id
                    AND oi.freshness_at_order = 'LAST_CHANCE'
                    AND DATE(o.placed_at) BETWEEN ? AND ?), 0) AS units_saved_from_waste

     FROM products p
     JOIN categories c ON c.id = p.category_id
     JOIN unit_types ut ON ut.id = p.unit_type_id
     WHERE p.retailer_id = ? AND p.deleted_at IS NULL
     ORDER BY revenue DESC, units_sold DESC",
    [$from, $to, $from, $to, $from, $to, $from, $to, $retailerId]
);

// Aggregate totals
$totalRev    = array_sum(array_column($report, 'revenue'));
$totalUnits  = array_sum(array_column($report, 'units_sold'));
$totalOrders = array_sum(array_column($report, 'order_count'));
$totalSaved  = array_sum(array_column($report, 'units_saved_from_waste'));

// Commission + net payout for this retailer's orders in the date range.
// (Each order records commission_amount + retailer_payout at checkout.)
$payoutAgg = db_one(
    "SELECT COALESCE(SUM(o.commission_amount),0) AS commission,
            COALESCE(SUM(o.retailer_payout),0)   AS payout
     FROM orders o
     WHERE o.id IN (
        SELECT DISTINCT oi.order_id FROM order_items oi
        JOIN products p ON p.id = oi.product_id
        WHERE p.retailer_id = ?
     )
     AND DATE(o.placed_at) BETWEEN ? AND ?
     AND o.status IN ('PROCESSING','QUALITY_CHECK','PACKED','OUT_FOR_DELIVERY','DELIVERED')",
    [$retailerId, $from, $to]
);
$totalCommission = (float) ($payoutAgg['commission'] ?? 0);
$totalPayout     = (float) ($payoutAgg['payout'] ?? 0);

// CSV export branch
if ($export === 'csv') {
    db_run("INSERT INTO audit_logs (user_id, action, entity_type, new_values)
            VALUES (?, 'CSV_EXPORT', 'retailer_report', ?)",
           [auth_id(), json_encode(['from' => $from, 'to' => $to])]);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="product_report_' . $from . '_to_' . $to . '.csv"');
    $fp = fopen('php://output', 'w');
    fputs($fp, "\xEF\xBB\xBF");
    fputcsv($fp, ['SKU', 'Product', 'Category', 'Unit', 'Base Price (MYR)',
                  'Units Sold', 'Orders', 'Revenue (MYR)', 'Current Stock',
                  'Views', 'Saved from Waste']);
    foreach ($report as $r) {
        fputcsv($fp, [
            $r['sku'], $r['name'], $r['category'], $r['unit_code'], $r['base_price'],
            $r['units_sold'], $r['order_count'], $r['revenue'], $r['current_stock'],
            $r['view_count'], $r['units_saved_from_waste'],
        ]);
    }
    fclose($fp);
    exit;
}

// ---- Waste & rescue summary (from inventory write-offs in the date range) ----
$waste = db_one(
    "SELECT
        COALESCE(SUM(-il.quantity_change), 0)                    AS units,
        COALESCE(SUM(-il.quantity_change * sb.cost_per_unit), 0) AS cost
     FROM inventory_logs il
     JOIN stock_batches sb ON sb.id = il.stock_batch_id
     JOIN products p       ON p.id  = sb.product_id
     WHERE p.retailer_id = ?
       AND il.movement_type IN ('EXPIRED','DAMAGED')
       AND DATE(il.created_at) BETWEEN ? AND ?",
    [$retailerId, $from, $to]
);
$wasteUnits = (float) ($waste['units'] ?? 0);
$wasteCost  = (float) ($waste['cost'] ?? 0);
$wasteRate  = ($totalUnits + $wasteUnits) > 0
    ? ($wasteUnits / ($totalUnits + $wasteUnits)) * 100 : 0.0;

$topWasted = db_all(
    "SELECT p.name, ut.code AS unit_code,
            SUM(-il.quantity_change)                     AS qty,
            SUM(-il.quantity_change * sb.cost_per_unit)  AS cost
     FROM inventory_logs il
     JOIN stock_batches sb ON sb.id = il.stock_batch_id
     JOIN products p       ON p.id  = sb.product_id
     JOIN unit_types ut    ON ut.id = p.unit_type_id
     WHERE p.retailer_id = ?
       AND il.movement_type IN ('EXPIRED','DAMAGED')
       AND DATE(il.created_at) BETWEEN ? AND ?
     GROUP BY p.id, p.name, ut.code
     ORDER BY qty DESC
     LIMIT 5",
    [$retailerId, $from, $to]
);

$pageTitle = 'Product Report — Retailer';
require_once __DIR__ . '/../../includes/header.php';
retailer_layout_start('reports', 'Product Performance Report');
?>

<!-- Date range filter -->
<form method="get" style="display: flex; gap: var(--space-3); align-items: end; margin-bottom: var(--space-4); background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-4);">
    <div>
        <label style="font-size: 0.75rem; color: var(--color-text-muted); display: block;">From</label>
        <input type="date" name="from" value="<?= attr($from) ?>" class="form-control">
    </div>
    <div>
        <label style="font-size: 0.75rem; color: var(--color-text-muted); display: block;">To</label>
        <input type="date" name="to" value="<?= attr($to) ?>" class="form-control">
    </div>
    <button type="submit" class="btn btn-primary">Update</button>
    <a href="<?= url('/retailer/reports.php?from=' . $from . '&to=' . $to . '&export=csv') ?>"
       class="btn btn-secondary">📥 Export CSV</a>
</form>

<!-- KPI Summary -->
<div class="kpi-grid" style="margin-bottom: var(--space-6);">
    <div class="kpi-card">
        <div class="kpi-label">Gross Sales</div>
        <div class="kpi-value"><?= format_myr($totalRev) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Platform Commission</div>
        <div class="kpi-value" style="color: var(--color-accent);">−<?= format_myr($totalCommission) ?></div>
    </div>
    <div class="kpi-card" style="background: #e6f4ea; border-color: #1a7a3a;">
        <div class="kpi-label">💰 Your Net Payout</div>
        <div class="kpi-value" style="color: #1a7a3a;"><?= format_myr($totalPayout) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Units Sold</div>
        <div class="kpi-value"><?= number_format($totalUnits, 2) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Orders</div>
        <div class="kpi-value"><?= number_format($totalOrders) ?></div>
    </div>
    <div class="kpi-card" style="background: var(--color-primary-light); border-color: var(--color-primary);">
        <div class="kpi-label">🌱 Saved from Waste</div>
        <div class="kpi-value" style="color: var(--color-primary-dark);">
            <?= number_format($totalSaved, 2) ?> units
        </div>
    </div>
    <div class="kpi-card" style="background: #fbeee8; border-color: #b85c38;">
        <div class="kpi-label">Discarded (Waste)</div>
        <div class="kpi-value" style="color: #b85c38;">
            <?= number_format($wasteUnits, 2) ?> units
        </div>
        <div class="kpi-meta">Loss: <?= format_myr($wasteCost) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Waste Rate</div>
        <div class="kpi-value"><?= number_format($wasteRate, 1) ?>%</div>
        <div class="kpi-meta">discarded ÷ (sold + discarded)</div>
    </div>
</div>

<?php if (!empty($topWasted)): ?>
    <div style="margin-bottom: var(--space-6); padding: var(--space-4); border: 1px solid var(--color-border); border-radius: var(--radius-lg); background: var(--color-surface);">
        <h3 style="margin: 0 0 var(--space-3); font-size: 1rem;">Most-wasted products (<?= e($from) ?> → <?= e($to) ?>)</h3>
        <table class="data-table">
            <thead><tr><th>Product</th><th style="text-align:right;">Discarded</th><th style="text-align:right;">Loss</th></tr></thead>
            <tbody>
            <?php foreach ($topWasted as $w): ?>
                <tr>
                    <td><?= e($w['name']) ?></td>
                    <td style="text-align:right;"><?= number_format((float) $w['qty'], 2) ?> <?= e($w['unit_code']) ?></td>
                    <td style="text-align:right;"><?= format_myr((float) $w['cost']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php if (empty($report)): ?>
    <div class="empty-state">No products yet. <a href="<?= url('/retailer/product_edit.php') ?>">Add your first product</a>.</div>
<?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Category</th>
                <th style="text-align: right;">Sold</th>
                <th style="text-align: right;">Orders</th>
                <th style="text-align: right;">Revenue</th>
                <th style="text-align: right;">Stock</th>
                <th style="text-align: right;">Views</th>
                <th style="text-align: right;">Conv. Rate</th>
                <th style="text-align: right;">🌱 Saved</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($report as $r):
                $convRate = (int) $r['view_count'] > 0
                    ? ((int) $r['order_count'] / (int) $r['view_count']) * 100
                    : 0;
            ?>
                <tr>
                    <td>
                        <strong><?= e($r['name']) ?></strong>
                        <br><small style="color: var(--color-text-muted);"><?= e($r['sku']) ?></small>
                    </td>
                    <td><?= e($r['category']) ?></td>
                    <td style="text-align: right;"><?= number_format((float) $r['units_sold'], 2) ?> <?= e($r['unit_code']) ?></td>
                    <td style="text-align: right;"><?= number_format((int) $r['order_count']) ?></td>
                    <td style="text-align: right;"><strong><?= format_myr($r['revenue']) ?></strong></td>
                    <td style="text-align: right;"><?= number_format((float) $r['current_stock'], 2) ?></td>
                    <td style="text-align: right; color: var(--color-text-muted);"><?= number_format((int) $r['view_count']) ?></td>
                    <td style="text-align: right;"><?= number_format($convRate, 1) ?>%</td>
                    <td style="text-align: right; color: <?= (float) $r['units_saved_from_waste'] > 0 ? 'var(--color-primary-dark)' : 'var(--color-text-muted)' ?>;">
                        <?= number_format((float) $r['units_saved_from_waste'], 2) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p style="margin-top: var(--space-4); font-size: 0.8125rem; color: var(--color-text-muted);">
    📊 "Saved from waste" counts units sold while the batch was in Last Chance status —
    these would likely have expired and been thrown away otherwise.
</p>

<?php
retailer_layout_end();
require_once __DIR__ . '/../../includes/footer.php';
