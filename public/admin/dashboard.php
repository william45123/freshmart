<?php
/**
 * Admin Dashboard — platform-wide KPIs + sales charts + sustainability metrics.
 *
 * R-APP-14 + R-APP-30: full business intelligence with auto-refresh.
 *
 * Section order:
 *   1. KPI grid (8 metric cards)
 *   2. Action-required cards (clickable shortcuts for pending items)
 *   3. Sales charts row (revenue trend + status doughnut)
 *   4. Insights row (top sellers + customer growth + category revenue)
 *   5. Recent orders table (scrollable on small screens)
 *   6. Export CSV bar (collapsible)
 */

require_once __DIR__ . '/../../includes/admin_layout.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/freshness.php';

// ============================================================
// KPI metrics
// ============================================================
$kpis = [
    'total_users'      => (int) db_scalar("SELECT COUNT(*) FROM users WHERE role='CUSTOMER' AND deleted_at IS NULL"),
    'total_retailers'  => (int) db_scalar("SELECT COUNT(*) FROM retailers WHERE approval_status='APPROVED'"),
    'pending_retailers'=> (int) db_scalar("SELECT COUNT(*) FROM retailers WHERE approval_status='PENDING'"),
    'total_products'   => (int) db_scalar("SELECT COUNT(*) FROM products WHERE is_active=1 AND deleted_at IS NULL"),
    'total_orders'     => (int) db_scalar("SELECT COUNT(*) FROM orders"),
    'total_revenue'    => (float) db_scalar("SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ('PROCESSING','QUALITY_CHECK','PACKED','OUT_FOR_DELIVERY','DELIVERED')"),
    'active_batches'   => (int) db_scalar("SELECT COUNT(*) FROM stock_batches WHERE status='ACTIVE'"),
    'expired_batches'  => (int) db_scalar("SELECT COUNT(*) FROM stock_batches WHERE status='EXPIRED'"),
];

// Sustainability metric — units sold while in LAST_CHANCE state
$kgSaved = (float) db_scalar(
    "SELECT COALESCE(SUM(quantity), 0) FROM order_items
     WHERE freshness_at_order = 'LAST_CHANCE'"
);

// Waste & rescue (platform-wide, last 30 days)
$wasteAgg = db_one(
    "SELECT COALESCE(SUM(-il.quantity_change),0) AS units,
            COALESCE(SUM(-il.quantity_change * sb.cost_per_unit),0) AS cost
     FROM inventory_logs il
     JOIN stock_batches sb ON sb.id = il.stock_batch_id
     WHERE il.movement_type IN ('EXPIRED','DAMAGED')
       AND il.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
);
$discUnits30 = (float) ($wasteAgg['units'] ?? 0);
$discCost30  = (float) ($wasteAgg['cost'] ?? 0);
$rescued30 = (float) db_scalar(
    "SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE oi.freshness_at_order = 'LAST_CHANCE'
       AND o.placed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
);
$sold30 = (float) db_scalar(
    "SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE o.placed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
       AND o.status IN ('PROCESSING','QUALITY_CHECK','PACKED','OUT_FOR_DELIVERY','DELIVERED')"
);
$wasteRate30 = ($sold30 + $discUnits30) > 0 ? ($discUnits30 / ($sold30 + $discUnits30)) * 100 : 0.0;

// Pending reviews count
$pendingReviews = (int) db_scalar("SELECT COUNT(*) FROM reviews WHERE is_approved = 0");

// ============================================================
// Chart data
// ============================================================

// 14-day revenue trend
$dailyRevenue = db_all(
    "SELECT DATE(placed_at) AS d, SUM(total) AS rev, COUNT(*) AS cnt
     FROM orders
     WHERE placed_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
       AND status IN ('PROCESSING','QUALITY_CHECK','PACKED','OUT_FOR_DELIVERY','DELIVERED')
     GROUP BY DATE(placed_at)
     ORDER BY d"
);

// Orders by status (doughnut)
$ordersByStatus = db_all(
    "SELECT status, COUNT(*) AS c FROM orders GROUP BY status"
);

// Top 5 selling products (30d)
$topProducts = db_all(
    "SELECT p.name, SUM(oi.quantity) AS units_sold, SUM(oi.subtotal) AS revenue
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     JOIN products p ON p.id = oi.product_id
     WHERE o.placed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
       AND o.status IN ('PROCESSING','QUALITY_CHECK','PACKED','OUT_FOR_DELIVERY','DELIVERED')
     GROUP BY p.id, p.name
     ORDER BY units_sold DESC
     LIMIT 5"
);

// User growth (30d)
$userGrowth = db_all(
    "SELECT DATE(created_at) AS d, COUNT(*) AS new_users
     FROM users
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
       AND role = 'CUSTOMER'
     GROUP BY DATE(created_at)
     ORDER BY d"
);

// Revenue by category (30d)
$revByCategory = db_all(
    "SELECT c.name AS category, COALESCE(SUM(oi.subtotal), 0) AS revenue
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id
     LEFT JOIN order_items oi ON oi.product_id = p.id
     LEFT JOIN orders o ON o.id = oi.order_id
       AND o.placed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
       AND o.status IN ('PROCESSING','QUALITY_CHECK','PACKED','OUT_FOR_DELIVERY','DELIVERED')
     WHERE c.is_active = 1
     GROUP BY c.id, c.name
     HAVING revenue > 0
     ORDER BY revenue DESC"
);

// Recent activity
$recentOrders = db_all(
    "SELECT o.order_number, o.total, o.status, o.placed_at, p.full_name AS customer
     FROM orders o
     LEFT JOIN profiles p ON p.user_id = o.user_id
     ORDER BY o.placed_at DESC LIMIT 10"
);

// ============================================================
// Build chart series with zero-fill for missing days
// ============================================================
$series = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $series[$d] = ['date' => $d, 'rev' => 0, 'cnt' => 0];
}
foreach ($dailyRevenue as $r) {
    if (isset($series[$r['d']])) {
        $series[$r['d']]['rev'] = (float) $r['rev'];
        $series[$r['d']]['cnt'] = (int) $r['cnt'];
    }
}
$series = array_values($series);

// Cumulative user growth 30d
$totalUsersBefore = (int) db_scalar(
    "SELECT COUNT(*) FROM users
     WHERE role = 'CUSTOMER' AND created_at < DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
);
$userSeries = [];
$running = $totalUsersBefore;
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $userSeries[$d] = ['date' => $d, 'cumulative' => $running];
}
foreach ($userGrowth as $r) {
    if (isset($userSeries[$r['d']])) {
        $running += (int) $r['new_users'];
        foreach ($userSeries as $dk => &$row) {
            if ($dk >= $r['d']) $row['cumulative'] = $running;
        }
        unset($row);
    }
}
$userSeries = array_values($userSeries);

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../../includes/header.php';

// R-APP-30: auto-refresh every 5 minutes
echo '<meta http-equiv="refresh" content="300">' . "\n";

admin_layout_start('dashboard', 'Platform Dashboard');
?>

<!-- ===== Section 1: Hero stat (revenue) ===== -->
<div class="hero-stat">
    <div class="hero-stat-label">Total revenue · all time</div>
    <div class="hero-stat-value"><?= format_myr($kpis['total_revenue']) ?></div>
    <div class="hero-stat-meta">from <?= number_format($kpis['total_orders']) ?> paid order<?= $kpis['total_orders'] === 1 ? '' : 's' ?></div>
</div>

<!-- ===== Section 2: 3 main business stats ===== -->
<div class="kpi-row-3">
    <div class="kpi-card">
        <div class="kpi-label"><span class="label-ico"><?= icon('package',16) ?> Orders</span></div>
        <div class="kpi-value"><?= number_format($kpis['total_orders']) ?></div>
        <div class="kpi-meta">Lifetime</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label"><span class="label-ico"><?= icon('user',16) ?> Customers</span></div>
        <div class="kpi-value"><?= number_format($kpis['total_users']) ?></div>
        <div class="kpi-meta">Active accounts</div>
    </div>
    <div class="kpi-card kpi-card-accent">
        <div class="kpi-label"><span class="label-ico"><?= icon('leaf',16) ?> Saved from waste</span></div>
        <div class="kpi-value"><?= number_format($kgSaved, 1) ?></div>
        <div class="kpi-meta">Last Chance units sold</div>
    </div>
</div>

<!-- ===== Section 3: System health (compact 4-stat strip) ===== -->
<div class="kpi-strip">
    <div class="kpi-strip-item">
        <div class="kpi-strip-label">Products</div>
        <div class="kpi-strip-value"><?= number_format($kpis['total_products']) ?></div>
    </div>
    <div class="kpi-strip-item">
        <div class="kpi-strip-label">Retailers</div>
        <div class="kpi-strip-value"><?= number_format($kpis['total_retailers']) ?></div>
    </div>
    <div class="kpi-strip-item">
        <div class="kpi-strip-label">Active batches</div>
        <div class="kpi-strip-value"><?= number_format($kpis['active_batches']) ?></div>
    </div>
    <div class="kpi-strip-item">
        <div class="kpi-strip-label">Expired</div>
        <div class="kpi-strip-value <?= $kpis['expired_batches'] > 0 ? 'is-warn' : '' ?>"><?= number_format($kpis['expired_batches']) ?></div>
    </div>
</div>

<!-- ===== Section: Waste & rescue (last 30 days) ===== -->
<h2 style="font-size: 1.125rem; margin: var(--space-6) 0 var(--space-3);">Waste &amp; rescue · last 30 days</h2>
<div class="kpi-row-3">
    <div class="kpi-card kpi-card-accent">
        <div class="kpi-label">Rescued from waste</div>
        <div class="kpi-value"><?= number_format($rescued30, 1) ?></div>
        <div class="kpi-meta">Last Chance units sold</div>
    </div>
    <div class="kpi-card" style="background: #fbeee8; border-color: #b85c38;">
        <div class="kpi-label">Discarded</div>
        <div class="kpi-value" style="color: #b85c38;"><?= number_format($discUnits30, 1) ?></div>
        <div class="kpi-meta">Loss <?= format_myr($discCost30) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Waste rate</div>
        <div class="kpi-value"><?= number_format($wasteRate30, 1) ?>%</div>
        <div class="kpi-meta">discarded ÷ (sold + discarded)</div>
    </div>
</div>

<!-- ===== Section 2: Action required (clickable) ===== -->
<?php if ($kpis['pending_retailers'] > 0 || $pendingReviews > 0): ?>
<div class="dash-section">
    <h2 class="dash-section-title">Action required</h2>
    <div class="action-grid">
        <?php if ($kpis['pending_retailers'] > 0): ?>
            <a href="<?= url('/admin/retailers.php?filter=PENDING') ?>" class="action-card action-card-warn">
                <div class="action-icon">⏳</div>
                <div class="action-body">
                    <div class="action-count"><?= $kpis['pending_retailers'] ?></div>
                    <div class="action-label">Retailer<?= $kpis['pending_retailers'] === 1 ? '' : 's' ?> awaiting approval</div>
                </div>
                <div class="action-arrow">→</div>
            </a>
        <?php endif; ?>
        <?php if ($pendingReviews > 0): ?>
            <a href="<?= url('/admin/reviews.php?filter=pending') ?>" class="action-card action-card-warn">
                <div class="action-icon">⭐</div>
                <div class="action-body">
                    <div class="action-count"><?= $pendingReviews ?></div>
                    <div class="action-label">Review<?= $pendingReviews === 1 ? '' : 's' ?> awaiting moderation</div>
                </div>
                <div class="action-arrow">→</div>
            </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- ===== Section 3: Sales overview ===== -->
<div class="dash-section">
    <h2 class="dash-section-title">Sales overview</h2>
    <div class="chart-grid chart-grid-2-1">
        <div class="chart-card">
            <h3 class="chart-title">Revenue · last 14 days</h3>
            <div class="chart-canvas-wrap">
                <canvas id="revChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <h3 class="chart-title">Orders by status</h3>
            <div class="chart-canvas-wrap">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ===== Section 4: Business insights ===== -->
<div class="dash-section">
    <h2 class="dash-section-title">Business insights · last 30 days</h2>
    <div class="chart-grid chart-grid-3">
        <div class="chart-card">
            <h3 class="chart-title">Top selling products</h3>
            <?php if (empty($topProducts)): ?>
                <p class="chart-empty">No sales yet in the last 30 days.</p>
            <?php else: ?>
                <div class="chart-canvas-wrap" style="height: 240px;">
                    <canvas id="topProductsChart"></canvas>
                </div>
            <?php endif; ?>
        </div>
        <div class="chart-card">
            <h3 class="chart-title">Customer growth</h3>
            <div class="chart-canvas-wrap" style="height: 240px;">
                <canvas id="userGrowthChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <h3 class="chart-title">Revenue by category</h3>
            <?php if (empty($revByCategory)): ?>
                <p class="chart-empty">No category sales in the last 30 days.</p>
            <?php else: ?>
                <div class="chart-canvas-wrap" style="height: 240px;">
                    <canvas id="catRevenueChart"></canvas>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ===== Section 5: Recent orders (scrollable) ===== -->
<div class="dash-section">
    <h2 class="dash-section-title">Recent orders</h2>
    <?php if (empty($recentOrders)): ?>
        <div class="empty-state">No orders yet.</div>
    <?php else: ?>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Placed</th>
                        <th>Status</th>
                        <th style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOrders as $o): ?>
                        <tr>
                            <td><code><?= e($o['order_number']) ?></code></td>
                            <td><?= e($o['customer'] ?? '—') ?></td>
                            <td><?= format_datetime($o['placed_at'], 'd M, H:i') ?></td>
                            <td><span class="status-pill status-<?= strtolower($o['status']) === 'delivered' ? 'active' : 'pending' ?>"><?= e($o['status']) ?></span></td>
                            <td style="text-align: right;"><strong><?= format_myr($o['total']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ===== Section 6: Export CSV (R-APP-38) ===== -->
<div class="dash-section">
    <details>
        <summary class="btn btn-secondary btn-sm" style="display: inline-block;">📥 Export business data as CSV</summary>
        <div style="margin-top: var(--space-3); background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-4); max-width: 720px;">
            <form method="get" action="<?= url('/admin/export.php') ?>" style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: var(--space-2); align-items: end;">
                <div>
                    <label style="font-size: 0.75rem; color: var(--color-text-muted); display: block; margin-bottom: 4px;">Type</label>
                    <select name="type" class="form-control">
                        <option value="orders">Orders</option>
                        <option value="users">Users</option>
                        <option value="products">Products</option>
                        <option value="retailers">Retailers</option>
                        <option value="low_stock">Low Stock Alert</option>
                        <option value="freshness_audit">Freshness Audit</option>
                    </select>
                </div>
                <div>
                    <label style="font-size: 0.75rem; color: var(--color-text-muted); display: block; margin-bottom: 4px;">From</label>
                    <input type="date" name="from" class="form-control" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                </div>
                <div>
                    <label style="font-size: 0.75rem; color: var(--color-text-muted); display: block; margin-bottom: 4px;">To</label>
                    <input type="date" name="to" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <button type="submit" class="btn btn-primary">Download</button>
            </form>
            <p style="margin: var(--space-3) 0 0; font-size: 0.75rem; color: var(--color-text-muted);">
                UTF-8 with BOM for Excel compatibility. Every export is logged.
            </p>
        </div>
    </details>
    <p style="margin: var(--space-3) 0 0; font-size: 0.75rem; color: var(--color-text-muted); text-align: right;">
        Auto-refreshes every 5 min · Last: <?= date('H:i:s') ?>
    </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const series   = <?= json_encode($series) ?>;
const byStatus = <?= json_encode($ordersByStatus) ?>;

// Common Chart.js options for compact look
const compactOpts = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
};

new Chart(document.getElementById('revChart'), {
    type: 'line',
    data: {
        labels: series.map(s => s.date.slice(5)),
        datasets: [{
            label: 'Revenue (RM)',
            data: series.map(s => s.rev),
            borderColor: '#4a5a3a',
            backgroundColor: 'rgba(74, 90, 58, 0.10)',
            borderWidth: 2,
            tension: 0.3,
            fill: true,
            pointRadius: 2,
            pointHoverRadius: 5,
        }]
    },
    options: { ...compactOpts, scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: byStatus.map(s => s.status),
        datasets: [{
            data: byStatus.map(s => parseInt(s.c)),
            backgroundColor: ['#a8b598','#7a8467','#4a5a3a','#c9a55a','#b85c38','#9a3b22','#5b6770'],
            borderWidth: 2,
            borderColor: '#faf8f3',
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 10 } }
        }
    }
});

<?php if (!empty($topProducts)): ?>
const topProducts = <?= json_encode($topProducts) ?>;
new Chart(document.getElementById('topProductsChart'), {
    type: 'bar',
    data: {
        labels: topProducts.map(p => p.name.length > 16 ? p.name.slice(0, 15) + '…' : p.name),
        datasets: [{
            label: 'Units sold',
            data: topProducts.map(p => parseFloat(p.units_sold)),
            backgroundColor: '#7a8467',
            borderRadius: 4,
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { beginAtZero: true, ticks: { font: { size: 10 } } },
            y: { ticks: { font: { size: 10 } } },
        }
    }
});
<?php endif; ?>

const userSeries = <?= json_encode($userSeries) ?>;
new Chart(document.getElementById('userGrowthChart'), {
    type: 'line',
    data: {
        labels: userSeries.map(s => s.date.slice(5)),
        datasets: [{
            label: 'Total customers',
            data: userSeries.map(s => parseInt(s.cumulative)),
            borderColor: '#c9a55a',
            backgroundColor: 'rgba(201, 165, 90, 0.15)',
            tension: 0.2,
            fill: true,
            pointRadius: 0,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { font: { size: 10 }, maxTicksLimit: 6 } },
            y: { beginAtZero: false, ticks: { font: { size: 10 } } },
        }
    }
});

<?php if (!empty($revByCategory)): ?>
const revByCat = <?= json_encode($revByCategory) ?>;
new Chart(document.getElementById('catRevenueChart'), {
    type: 'bar',
    data: {
        labels: revByCat.map(c => c.category),
        datasets: [{
            label: 'Revenue (RM)',
            data: revByCat.map(c => parseFloat(c.revenue)),
            backgroundColor: ['#4a5a3a','#7a8467','#c9a55a','#b85c38','#a8b598','#5b6770','#9a3b22','#888780'],
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { font: { size: 9 }, maxRotation: 45, minRotation: 0 } },
            y: { beginAtZero: true, ticks: { font: { size: 10 }, callback: v => 'RM ' + v } },
        }
    }
});
<?php endif; ?>
</script>

<?php
admin_layout_end();
require_once __DIR__ . '/../../includes/footer.php';
