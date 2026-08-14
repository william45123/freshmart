<?php
/**
 * Retailer Products list — all products belonging to this retailer.
 *
 *  - Filter by category, status (active / inactive), stock level (low / out)
 *  - Search by name / SKU
 *  - Quick actions: edit, toggle active, delete (soft)
 *  - Link to Add New Product
 */

require_once __DIR__ . '/../../includes/retailer_layout.php';
require_once __DIR__ . '/../../includes/helpers.php';

$retailer   = retailer_current();
$retailerId = (int) $retailer['id'];
$errors     = [];

// ---- Handle actions (toggle active / soft delete) ----
if (is_post() && csrf_verify()) {
    $action    = (string) input('action', '');
    $productId = (int) input('product_id', 0);

    // Verify ownership
    $owns = (int) db_scalar(
        'SELECT COUNT(*) FROM products WHERE id = ? AND retailer_id = ? AND deleted_at IS NULL',
        [$productId, $retailerId]
    );

    if ($productId > 0 && $owns > 0) {
        if ($action === 'toggle_active') {
            db_run('UPDATE products SET is_active = NOT is_active WHERE id = ?', [$productId]);
            flash_set('success', 'Product status updated.');
        } elseif ($action === 'delete') {
            // R-APP-09: deletion not allowed if there are active orders
            $activeOrders = (int) db_scalar(
                "SELECT COUNT(DISTINCT o.id) FROM orders o
                 JOIN order_items oi ON oi.order_id = o.id
                 WHERE oi.product_id = ?
                   AND o.status IN ('PLACED','PROCESSING','QUALITY_CHECK','PACKED','OUT_FOR_DELIVERY')",
                [$productId]
            );
            if ($activeOrders > 0) {
                flash_set('error', "Cannot delete — $activeOrders active order(s) contain this product. Deactivate it instead.");
            } else {
                db_run('UPDATE products SET deleted_at = NOW(), is_active = 0 WHERE id = ?', [$productId]);
                flash_set('info', 'Product removed.');
            }
        }
        redirect('/retailer/products.php');
    } else {
        $errors[] = 'Product not found or not yours.';
    }
}

// ---- Filters ----
$q            = trim((string) input('q', ''));
$catFilter    = (int) input('category', 0);
$statusFilter = (string) input('status', '');   // '', active, inactive
$stockFilter  = (string) input('stock', '');    // '', low, out

$where = ['p.retailer_id = ?', 'p.deleted_at IS NULL'];
$args  = [$retailerId];

if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.sku LIKE ?)';
    $args[]  = "%$q%";
    $args[]  = "%$q%";
}
if ($catFilter > 0) {
    $where[] = 'p.category_id = ?';
    $args[]  = $catFilter;
}
if ($statusFilter === 'active') {
    $where[] = 'p.is_active = 1';
} elseif ($statusFilter === 'inactive') {
    $where[] = 'p.is_active = 0';
}

$products = db_all(
    "SELECT p.id, p.name, p.sku, p.base_price, p.is_active, p.is_featured,
            p.view_count, p.min_order_qty, p.created_at,
            c.name AS category_name,
            ut.code AS unit_code,
            (SELECT image_path FROM product_images pi
             WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) AS primary_image,
            COALESCE((SELECT SUM(quantity_remaining) FROM stock_batches sb
                      WHERE sb.product_id = p.id AND sb.status = 'ACTIVE'), 0) AS current_stock,
            (SELECT COUNT(*) FROM stock_batches sb
             WHERE sb.product_id = p.id AND sb.status = 'ACTIVE') AS active_batches
     FROM products p
     JOIN categories c ON c.id = p.category_id
     JOIN unit_types ut ON ut.id = p.unit_type_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY p.is_active DESC, p.created_at DESC",
    $args
);

// Post-filter for stock (needs calculated current_stock)
if ($stockFilter === 'low') {
    $products = array_values(array_filter($products, fn($p) =>
        (float) $p['current_stock'] > 0 && (float) $p['current_stock'] <= (float) $p['min_order_qty'] * 10
    ));
} elseif ($stockFilter === 'out') {
    $products = array_values(array_filter($products, fn($p) => (float) $p['current_stock'] <= 0));
}

// KPIs
$totalProducts = (int) db_scalar(
    'SELECT COUNT(*) FROM products WHERE retailer_id = ? AND deleted_at IS NULL', [$retailerId]
);
$activeProducts = (int) db_scalar(
    'SELECT COUNT(*) FROM products WHERE retailer_id = ? AND is_active = 1 AND deleted_at IS NULL',
    [$retailerId]
);
$outOfStock = (int) db_scalar(
    "SELECT COUNT(*) FROM products p
     WHERE p.retailer_id = ? AND p.is_active = 1 AND p.deleted_at IS NULL
       AND COALESCE((SELECT SUM(quantity_remaining) FROM stock_batches sb
                     WHERE sb.product_id = p.id AND sb.status = 'ACTIVE'), 0) <= 0",
    [$retailerId]
);

// Categories for filter dropdown
$categories = db_all('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY display_order');

$pageTitle = 'My Products — Retailer';
require_once __DIR__ . '/../../includes/header.php';
retailer_layout_start('products', 'My Products');
?>

<?php foreach ($errors as $err): ?>
    <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<!-- KPIs -->
<div class="kpi-grid u-mb-4 u-maxw-800">
    <div class="kpi-card">
        <div class="kpi-label">Total Products</div>
        <div class="kpi-value"><?= number_format($totalProducts) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Active Listings</div>
        <div class="kpi-value"><?= number_format($activeProducts) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Out of Stock</div>
        <div class="kpi-value <?= $outOfStock > 0 ? 'is-danger' : '' ?>">
            <?= number_format($outOfStock) ?>
        </div>
    </div>
</div>

<!-- Top action bar -->
<div class="u-flex u-jc-between u-ai-center u-mb-4 u-wrap u-gap-3">
    <form method="get" class="u-flex u-gap-2 u-ai-center u-wrap">
        <input type="search" name="q" value="<?= attr($q) ?>"
               placeholder="Search name or SKU..."
               class="form-control u-w-240">
        <select name="category" class="form-control u-w-160" onchange="this.form.submit()">
            <option value="0">All categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $catFilter === (int) $c['id'] ? 'selected' : '' ?>>
                    <?= e($c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="form-control u-w-130" onchange="this.form.submit()">
            <option value="">All status</option>
            <option value="active"   <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
        <select name="stock" class="form-control u-w-140" onchange="this.form.submit()">
            <option value="">Any stock</option>
            <option value="low" <?= $stockFilter === 'low' ? 'selected' : '' ?>>⚠️ Low stock</option>
            <option value="out" <?= $stockFilter === 'out' ? 'selected' : '' ?>>❌ Out of stock</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <?php if ($q || $catFilter || $statusFilter || $stockFilter): ?>
            <a href="<?= url('/retailer/products.php') ?>" class="btn btn-ghost btn-sm">Clear</a>
        <?php endif; ?>
    </form>
    <a href="<?= url('/retailer/product_edit.php') ?>" class="btn btn-primary">
        + Add New Product
    </a>
</div>

<?php if (empty($products)): ?>
    <div class="empty-state">
        <?php if ($q || $catFilter || $statusFilter || $stockFilter): ?>
            <p class="u-t-17">No products match your filters.</p>
            <a href="<?= url('/retailer/products.php') ?>" class="btn btn-secondary btn-sm">Clear filters</a>
        <?php else: ?>
            <p class="u-t-17">📦 No products yet.</p>
            <a href="<?= url('/retailer/product_edit.php') ?>" class="btn btn-primary u-mt-2">
                Add your first product
            </a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th class="u-w-60">Image</th>
                <th>Product</th>
                <th>Category</th>
                <th class="u-ta-r">Price</th>
                <th class="u-ta-r">Stock</th>
                <th class="u-ta-c">Batches</th>
                <th class="u-ta-r">Views</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p):
                $stockLow = (float) $p['current_stock'] > 0
                            && (float) $p['current_stock'] <= (float) $p['min_order_qty'] * 10;
                $stockOut = (float) $p['current_stock'] <= 0;
            ?>
                <tr>
                    <td>
                        <div class="u-w-44 u-h-44 u-bg-page u-r u-grid u-place-center u-ovh">
                            <?php if (!empty($p['primary_image'])): ?>
                                <img src="<?= upload_url($p['primary_image']) ?>" alt="<?= attr($p['name']) ?>"
                                     class="media-fill">
                            <?php else: ?>
                                <span class="u-t-20">🥬</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <strong><?= e($p['name']) ?></strong>
                        <?php if ($p['is_featured']): ?>
                            <span class="u-bg-mustard u-fg-white u-t-10 u-p-2-6 u-r-pill u-ls-05 u-upper u-ml-1">★ Featured</span>
                        <?php endif; ?>
                        <br><small class="u-muted"><code><?= e($p['sku']) ?></code></small>
                    </td>
                    <td class="u-t-14"><?= e($p['category_name']) ?></td>
                    <td class="u-ta-r">
                        <strong><?= format_myr($p['base_price']) ?></strong>
                        <br><small class="u-muted">/ <?= e($p['unit_code']) ?></small>
                    </td>
                    <td class="u-ta-r">
                        <?php if ($stockOut): ?>
                            <span class="u-fg-accent u-fw-600">Out of stock</span>
                        <?php elseif ($stockLow): ?>
                            <span class="u-fg-mustard u-fw-600">
                                <?= number_format((float) $p['current_stock'], 1) ?> <?= e($p['unit_code']) ?> ⚠️
                            </span>
                        <?php else: ?>
                            <?= number_format((float) $p['current_stock'], 1) ?> <?= e($p['unit_code']) ?>
                        <?php endif; ?>
                    </td>
                    <td class="u-ta-c">
                        <?= (int) $p['active_batches'] ?>
                    </td>
                    <td class="u-ta-r u-muted">
                        <?= number_format((int) $p['view_count']) ?>
                    </td>
                    <td>
                        <?php if ($p['is_active']): ?>
                            <span class="status-pill status-active">Active</span>
                        <?php else: ?>
                            <span class="status-pill status-suspended">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="u-flex u-gap-1">
                            <a href="<?= url('/retailer/product_edit.php?id=' . $p['id']) ?>"
                               class="btn btn-secondary btn-sm">Edit</a>
                            <form method="post" class="u-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-ghost btn-sm"
                                        title="<?= $p['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                    <?= $p['is_active'] ? '🚫' : '✓' ?>
                                </button>
                            </form>
                            <form method="post" class="u-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-ghost btn-sm u-fg-danger"
                                       
                                        onclick="return confirm('Delete <?= attr($p['name']) ?>? This can be undone by contacting admin.')">
                                    🗑
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
retailer_layout_end();
require_once __DIR__ . '/../../includes/footer.php';
