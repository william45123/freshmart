<?php
/**
 * Retailer Inventory / Stock Batches page.
 *
 * Shows all batches across products with live freshness, and provides
 * forms to add new batches (FEFO restock) and adjust existing ones.
 */

require_once __DIR__ . '/../../includes/retailer_layout.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/freshness.php';
require_once __DIR__ . '/../../includes/fefo.php';

$retailer    = retailer_current();
$retailerId  = (int) $retailer['id'];
$filterProductId = (int) input('product_id', 0);
// F3: expiry alerts link here with ?batch=<id>. Highlight and scroll to it so
// the retailer lands on the batch the notification is about, not just the page.
$highlightBatch  = (int) input('batch', 0);
$errors      = [];

// ---------- Handle batch creation ----------
if (is_post() && input('action') === 'create') {
    if (!csrf_verify()) {
        $errors[] = 'CSRF mismatch.';
    } else {
        $productId    = (int) input('product_id');
        $supplierId   = (int) input('supplier_id') ?: null;
        $batchCode    = trim((string) input('batch_code', ''));
        $receivedDate = (string) input('received_date', '');
        $expiryDate   = (string) input('expiry_date', '');
        $quantity     = (float) input('quantity', 0);
        $costPerUnit  = (float) input('cost_per_unit', 0);
        $storage      = trim((string) input('storage_location', ''));

        // Validate product belongs to this retailer
        $owned = db_scalar('SELECT id FROM products WHERE id = ? AND retailer_id = ?',
                           [$productId, $retailerId]);
        if (!$owned)                  $errors[] = 'Invalid product.';
        if ($batchCode === '')         $errors[] = 'Batch code is required.';
        if ($receivedDate === '')      $errors[] = 'Received date is required.';
        if ($expiryDate === '')        $errors[] = 'Expiry date is required.';
        if (strtotime($expiryDate) <= strtotime($receivedDate))
            $errors[] = 'Expiry must be after received date.';
        if ($quantity <= 0)            $errors[] = 'Quantity must be positive.';

        if (empty($errors)) {
            try {
                fefo_restock(
                    $productId, $supplierId, $batchCode, $receivedDate, $expiryDate,
                    $quantity, $costPerUnit, auth_id(), $storage ?: null
                );
                flash_set('success', "Batch $batchCode added with " . number_format($quantity, 2) . ' units.');
                redirect('/retailer/inventory.php' . ($filterProductId ? "?product_id=$filterProductId" : ''));
            } catch (Throwable $e) {
                $errors[] = 'Restock failed: ' . $e->getMessage();
            }
        }
    }
}

// ---------- Handle batch adjustment ----------
if (is_post() && input('action') === 'adjust') {
    if (!csrf_verify()) {
        $errors[] = 'CSRF mismatch.';
    } else {
        $batchId    = (int) input('batch_id');
        $newQty     = (float) input('new_quantity');
        $reason     = trim((string) input('reason', '')) ?: 'Manual adjustment';

        // Verify ownership
        $owned = db_scalar(
            "SELECT sb.id FROM stock_batches sb
             JOIN products p ON p.id = sb.product_id
             WHERE sb.id = ? AND p.retailer_id = ?",
            [$batchId, $retailerId]
        );
        if (!$owned) {
            $errors[] = 'Invalid batch.';
        } else {
            try {
                fefo_adjust($batchId, $newQty, $reason, auth_id());
                flash_set('success', 'Batch quantity adjusted.');
                redirect('/retailer/inventory.php' . ($filterProductId ? "?product_id=$filterProductId" : ''));
            } catch (Throwable $e) {
                $errors[] = 'Adjust failed: ' . $e->getMessage();
            }
        }
    }
}

// ---------- Handle batch discard (write-off as waste) ----------
if (is_post() && input('action') === 'discard') {
    if (!csrf_verify()) {
        $errors[] = 'CSRF mismatch.';
    } else {
        $batchId = (int) input('batch_id');
        $mtype   = (string) input('movement_type', 'EXPIRED');
        $reason  = trim((string) input('reason', '')) ?: 'Written off by retailer';

        $owned = db_scalar(
            "SELECT sb.id FROM stock_batches sb
             JOIN products p ON p.id = sb.product_id
             WHERE sb.id = ? AND p.retailer_id = ?",
            [$batchId, $retailerId]
        );
        if (!$owned) {
            $errors[] = 'Invalid batch.';
        } else {
            try {
                $qty = fefo_discard($batchId, $mtype, $reason, auth_id());
                flash_set('success', 'Batch written off — ' . number_format($qty, 2) . ' units recorded as waste.');
                redirect('/retailer/inventory.php' . ($filterProductId ? "?product_id=$filterProductId" : ''));
            } catch (Throwable $e) {
                $errors[] = 'Write-off failed: ' . $e->getMessage();
            }
        }
    }
}

// ---------- Load batches ----------
$where = ['p.retailer_id = ?'];
$args  = [$retailerId];
if ($filterProductId > 0) {
    $where[] = 'sb.product_id = ?';
    $args[]  = $filterProductId;
}
$batches = db_all(
    "SELECT sb.*, p.name AS product_name, p.id AS product_id,
            ut.code AS unit_code,
            COALESCE(p.decay_exponent_override, c.decay_exponent, 1.0) AS decay_exponent,
            s.name AS supplier_name
     FROM stock_batches sb
     JOIN products p     ON p.id = sb.product_id
     JOIN categories c   ON c.id = p.category_id
     JOIN unit_types ut  ON ut.id = p.unit_type_id
     LEFT JOIN suppliers s ON s.id = sb.supplier_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY
       FIELD(sb.status, 'ACTIVE','DEPLETED','EXPIRED','RECALLED'),
       sb.expiry_date ASC, sb.id DESC",
    $args
);

// Products list for the form dropdown + filter
$products = db_all(
    'SELECT id, name, sku FROM products WHERE retailer_id = ? AND is_active = 1 AND deleted_at IS NULL ORDER BY name',
    [$retailerId]
);
$suppliers = db_all(
    'SELECT id, name FROM suppliers WHERE retailer_id = ? AND is_active = 1 ORDER BY name',
    [$retailerId]
);

$filterProduct = $filterProductId > 0
    ? db_one('SELECT * FROM products WHERE id = ? AND retailer_id = ?', [$filterProductId, $retailerId])
    : null;

$pageTitle = 'Inventory (Batches)';
$action    = '<a href="#new-batch" class="btn btn-primary">+ New Batch</a>';

require_once __DIR__ . '/../../includes/header.php';
retailer_layout_start('inventory', 'Inventory · FEFO Batches', $action);
?>

<?php foreach ($errors as $err): ?>
    <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<?php if ($filterProduct): ?>
    <div class="flash flash-info">
        Showing batches for <strong><?= e($filterProduct['name']) ?></strong> ·
        <a href="<?= url('/retailer/inventory.php') ?>">Show all</a>
    </div>
<?php endif; ?>

<div class="u-bg-primary-lt u-bordered-primary u-r-lg u-p-4 u-mb-6 u-t-15">
    <strong>📌 FEFO (First-Expired-First-Out):</strong>
    Customer orders are automatically fulfilled from the batch with the earliest expiry first.
    Sort below is by expiry ASC — what's at the top sells next.
</div>

<?php if (empty($batches)): ?>
    <div class="empty-state">
        🥬 No batches yet. Add your first stock batch below.
    </div>
<?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Batch</th>
                <th>Product</th>
                <th>Received</th>
                <th>Expiry</th>
                <th>Quantity</th>
                <th>Freshness</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($batches as $b):
                $level = $b['status'] === 'EXPIRED' ? 'EXPIRED'
                       : freshness_level($b['received_date'], $b['expiry_date'], (float) $b['decay_exponent']);
                $days  = max(0, days_between(now_my()->format('Y-m-d'), $b['expiry_date']));
                $isTarget = ($highlightBatch > 0 && (int) $b['id'] === $highlightBatch);
            ?>
                <tr id="batch-<?= (int) $b['id'] ?>"<?= $isTarget ? ' class="is-flagged" tabindex="-1"' : '' ?>>
                    <td><code><?= e($b['batch_code']) ?></code></td>
                    <td>
                        <a href="<?= url('/retailer/inventory.php?product_id=' . $b['product_id']) ?>">
                            <?= e($b['product_name']) ?>
                        </a>
                    </td>
                    <td><?= format_date($b['received_date']) ?></td>
                    <td>
                        <?= format_date($b['expiry_date']) ?>
                        <br><small class="u-muted">
                            <?= relative_date($b['expiry_date']) ?>
                        </small>
                    </td>
                    <td>
                        <?= number_format((float) $b['quantity_remaining'], 2) ?>
                        / <?= number_format((float) $b['original_quantity'], 2) ?>
                        <?= e($b['unit_code']) ?>
                    </td>
                    <td>
                        <?php if ($b['status'] === 'ACTIVE'): ?>
                            <?= freshness_ring_html([
                                'freshness_percent' => freshness_percent($b['received_date'], $b['expiry_date'], (float) $b['decay_exponent']),
                                'freshness_color'   => freshness_info($level)['color_hex'],
                                'freshness_level'   => $level,
                                'days_remaining'    => $days,
                            ], 42, true) ?>
                        <?php else: ?>
                            <span class="u-muted u-t-14">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-pill status-<?= $b['status'] === 'ACTIVE' ? 'active' : 'suspended' ?>">
                            <?= e($b['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($b['status'] === 'ACTIVE'): ?>
                            <details>
                                <summary class="btn btn-secondary btn-sm u-inline-block">Adjust</summary>
                                <form method="post" class="u-mt-2 u-flex u-gap-2 u-ai-center">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="adjust">
                                    <input type="hidden" name="batch_id" value="<?= $b['id'] ?>">
                                    <input type="number" name="new_quantity" step="0.01" min="0"
                                           class="form-control u-w-100"
                                           value="<?= attr((string) $b['quantity_remaining']) ?>">
                                    <input type="text" name="reason" placeholder="Reason"
                                           class="form-control u-w-150" maxlength="100">
                                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                </form>
                            </details>
                            <details class="u-mt-2">
                                <summary class="btn btn-danger btn-sm u-inline-block">Discard</summary>
                                <form method="post" onsubmit="return confirm('Write off ALL remaining units of this batch as waste? This cannot be undone.');" class="u-mt-2 u-flex u-gap-2 u-ai-center u-wrap">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="discard">
                                    <input type="hidden" name="batch_id" value="<?= $b['id'] ?>">
                                    <select name="movement_type" class="form-control u-w-130">
                                        <option value="EXPIRED">Expired</option>
                                        <option value="DAMAGED">Damaged</option>
                                    </select>
                                    <input type="text" name="reason" placeholder="Reason (optional)"
                                           class="form-control u-w-160" maxlength="100">
                                    <button type="submit" class="btn btn-danger btn-sm">Confirm write-off</button>
                                </form>
                            </details>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<h2 id="new-batch" class="u-mt-10 u-t-20">+ Add New Batch</h2>

<form method="post" class="panel u-maxw-720 u-p-6">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">

    <div class="u-grid u-cols-2 u-gap-4">
        <div class="form-group">
            <label for="product_id">Product *</label>
            <select id="product_id" name="product_id" required class="form-control">
                <option value="">— Choose —</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>"
                            <?= $filterProductId == $p['id'] ? 'selected' : '' ?>>
                        <?= e($p['name']) ?> (<?= e($p['sku']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="supplier_id">Supplier</label>
            <select id="supplier_id" name="supplier_id" class="form-control">
                <option value="">—</option>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="batch_code">Batch Code *</label>
            <input type="text" id="batch_code" name="batch_code" required class="form-control"
                   placeholder="e.g. LET-B042" maxlength="50">
        </div>

        <div class="form-group">
            <label for="storage_location">Storage Location</label>
            <input type="text" id="storage_location" name="storage_location" class="form-control"
                   placeholder="e.g. Cold Room 1" maxlength="100">
        </div>

        <div class="form-group">
            <label for="received_date">Received Date *</label>
            <input type="date" id="received_date" name="received_date" required class="form-control"
                   value="<?= date('Y-m-d') ?>">
        </div>

        <div class="form-group">
            <label for="expiry_date">Expiry Date *</label>
            <input type="date" id="expiry_date" name="expiry_date" required class="form-control"
                   value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
            <div class="form-help">Auto-suggested from category shelf life</div>
        </div>

        <div class="form-group">
            <label for="quantity">Quantity *</label>
            <input type="number" id="quantity" name="quantity" required step="0.01" min="0.01"
                   class="form-control" placeholder="50">
        </div>

        <div class="form-group">
            <label for="cost_per_unit">Cost Per Unit (MYR)</label>
            <input type="number" id="cost_per_unit" name="cost_per_unit" step="0.01" min="0"
                   class="form-control" placeholder="3.00">
            <div class="form-help">Internal use only — not shown to customers</div>
        </div>
    </div>

    <div class="u-flex u-gap-3 u-mt-2">
        <button type="submit" class="btn btn-primary">Add Batch</button>
    </div>
</form>

<script>
    // Auto-populate expiry date based on selected product's shelf life
    const productSel = document.getElementById('product_id');
    const recDate    = document.getElementById('received_date');
    const expDate    = document.getElementById('expiry_date');
    const shelfLives = {};
    <?php
    $sl = db_all(
        'SELECT p.id, COALESCE(p.shelf_life_days, c.default_shelf_life_days, 7) AS days
         FROM products p JOIN categories c ON c.id = p.category_id
         WHERE p.retailer_id = ?',
        [$retailerId]
    );
    foreach ($sl as $r) echo "    shelfLives[{$r['id']}] = {$r['days']};\n";
    ?>
    function updateExpiry() {
        const days = shelfLives[productSel.value];
        if (!days || !recDate.value) return;
        const d = new Date(recDate.value);
        d.setDate(d.getDate() + parseInt(days));
        expDate.value = d.toISOString().split('T')[0];
    }
    productSel.addEventListener('change', updateExpiry);
    recDate.addEventListener('change', updateExpiry);
</script>

<?php if ($highlightBatch > 0): ?>
<script>
    // Bring the batch the alert pointed at into view. Focus as well as scroll,
    // so the jump is announced rather than only visual.
    (function () {
        var row = document.getElementById('batch-<?= (int) $highlightBatch ?>');
        if (!row) return;
        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        row.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
        row.focus({ preventScroll: true });
    })();
</script>
<?php endif; ?>

<?php
retailer_layout_end();
require_once __DIR__ . '/../../includes/footer.php';
