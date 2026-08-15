<?php
/**
 * Admin: Promo Code management.
 */

require_once __DIR__ . '/../../includes/admin_layout.php';
require_once __DIR__ . '/../../includes/helpers.php';

// Authorisation gate. Runs before any request handling or output so the
// redirect/403 can still be issued; admin_layout_start() calls this again
// further down, which is harmless (require_role is idempotent).
admin_check();

$errors = [];

if (is_post() && csrf_verify()) {
    $action = (string) input('action', '');

    if ($action === 'create') {
        try {
            $code   = strtoupper(trim((string) input('code', '')));
            $type   = (string) input('discount_type', 'PERCENTAGE');
            $value  = (float) input('discount_value', 0);
            $minOrd = (float) input('min_order_value', 0);
            $maxDisc= input('max_discount') !== '' ? (float) input('max_discount') : null;
            $usage  = input('usage_limit') !== '' ? (int) input('usage_limit') : null;
            $userL  = (int) input('user_limit', 1);
            $starts = (string) input('starts_at', date('Y-m-d H:i:s'));
            $ends   = (string) input('expires_at', date('Y-m-d H:i:s', strtotime('+30 days')));
            $desc   = trim((string) input('description', ''));

            if ($code === '')        $errors[] = 'Code is required.';
            if ($value <= 0)         $errors[] = 'Discount value must be positive.';
            if (!in_array($type, ['PERCENTAGE','FIXED_AMOUNT'], true)) $errors[] = 'Invalid type.';

            if (empty($errors)) {
                db_run(
                    "INSERT INTO promo_codes
                        (code, description, discount_type, discount_value, min_order_value,
                         max_discount, usage_limit, user_limit, starts_at, expires_at, is_active, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)",
                    [$code, $desc, $type, $value, $minOrd, $maxDisc, $usage, $userL,
                     $starts, $ends, auth_id()]
                );
                flash_set('success', "Promo $code created.");
                redirect('/admin/promos.php');
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    } elseif ($action === 'toggle') {
        $id = (int) input('promo_id');
        db_run('UPDATE promo_codes SET is_active = NOT is_active WHERE id = ?', [$id]);
        redirect('/admin/promos.php');
    }
}

$promos = db_all(
    "SELECT pc.*, p.full_name AS created_by_name,
            (SELECT COUNT(*) FROM promo_code_usages WHERE promo_code_id = pc.id) AS use_count
     FROM promo_codes pc
     LEFT JOIN profiles p ON p.user_id = pc.created_by
     ORDER BY pc.created_at DESC"
);

$pageTitle = 'Promo Codes — Admin';
require_once __DIR__ . '/../../includes/header.php';
admin_layout_start('promos', 'Promo Codes');
?>

<?php foreach ($errors as $err): ?>
    <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<details class="u-mb-4">
    <summary class="btn btn-primary u-inline-block">+ New Promo Code</summary>

    <form method="post" class="panel u-mt-3 u-p-5 u-maxw-720">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">

        <div class="u-grid u-cols-2 u-gap-4">
            <div class="form-group">
                <label>Code *</label>
                <input type="text" name="code" required class="form-control u-upper" placeholder="WELCOME10" maxlength="50">
            </div>
            <div class="form-group">
                <label>Description</label>
                <input type="text" name="description" class="form-control" maxlength="255">
            </div>
            <div class="form-group">
                <label>Discount Type *</label>
                <select name="discount_type" class="form-control">
                    <option value="PERCENTAGE">% Percentage</option>
                    <option value="FIXED_AMOUNT">RM Fixed Amount</option>
                </select>
            </div>
            <div class="form-group">
                <label>Discount Value *</label>
                <input type="number" name="discount_value" required step="0.01" min="0.01" class="form-control" placeholder="10.00">
            </div>
            <div class="form-group">
                <label>Min Order (MYR)</label>
                <input type="number" name="min_order_value" step="0.01" min="0" class="form-control" value="0">
            </div>
            <div class="form-group">
                <label>Max Discount Cap (MYR)</label>
                <input type="number" name="max_discount" step="0.01" min="0" class="form-control" placeholder="for %-type">
            </div>
            <div class="form-group">
                <label>Total Usage Limit</label>
                <input type="number" name="usage_limit" min="1" class="form-control" placeholder="leave blank = unlimited">
            </div>
            <div class="form-group">
                <label>Per-User Limit</label>
                <input type="number" name="user_limit" min="1" class="form-control" value="1">
            </div>
            <div class="form-group">
                <label>Starts</label>
                <input type="datetime-local" name="starts_at" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
            </div>
            <div class="form-group">
                <label>Expires</label>
                <input type="datetime-local" name="expires_at" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime('+30 days')) ?>">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Create Code</button>
    </form>
</details>

<?php if (empty($promos)): ?>
    <div class="empty-state">No promo codes yet.</div>
<?php else: ?>
    <table class="data-table data-table">
        <thead>
            <tr><th>Code</th><th>Discount</th><th>Min Order</th><th>Usage</th><th>Validity</th><th>Status</th><th>Action</th></tr>
        </thead>
        <tbody>
        <?php foreach ($promos as $p): ?>
            <tr>
                <td data-label="Code">
                    <code class="u-t-15 u-bg-primary-lt u-p-pill-xs u-r-sm u-fg-primary-dk u-fw-600">
                        <?= e($p['code']) ?>
                    </code>
                    <?php if (!empty($p['description'])): ?>
                        <br><small class="u-muted"><?= e($p['description']) ?></small>
                    <?php endif; ?>
                </td>
                <td data-label="Discount">
                    <?php if ($p['discount_type'] === 'PERCENTAGE'): ?>
                        <?= number_format((float) $p['discount_value'], 0) ?>%
                        <?php if ($p['max_discount']): ?>
                            <br><small>max <?= format_myr($p['max_discount']) ?></small>
                        <?php endif; ?>
                    <?php else: ?>
                        −<?= format_myr($p['discount_value']) ?>
                    <?php endif; ?>
                </td>
                <td data-label="Min Order"><?= (float) $p['min_order_value'] > 0 ? format_myr($p['min_order_value']) : '—' ?></td>
                <td data-label="Usage">
                    <?= (int) $p['use_count'] ?> / <?= $p['usage_limit'] === null ? '∞' : (int) $p['usage_limit'] ?>
                </td>
                <td data-label="Validity" class="u-t-13 u-muted">
                    <?= format_date($p['starts_at']) ?><br>→ <?= format_date($p['expires_at']) ?>
                </td>
                <td data-label="Status">
                    <?php if ($p['is_active'] && strtotime($p['expires_at']) > time()): ?>
                        <span class="status-pill status-active">Active</span>
                    <?php else: ?>
                        <span class="status-pill status-suspended">Inactive</span>
                    <?php endif; ?>
                </td>
                <td data-label="Action">
                    <form method="post" class="u-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="promo_id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn btn-ghost btn-sm">
                            <?= $p['is_active'] ? 'Disable' : 'Enable' ?>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
admin_layout_end();
require_once __DIR__ . '/../../includes/footer.php';
